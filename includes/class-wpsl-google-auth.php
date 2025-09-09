<?php

class WPSL_GoogleAuth
{
    private $settings;

    public function __construct($plugin_name, $version)
    {
        $this->settings = get_option('wpsl_settings');
        add_action('init', [$this, 'handleGoogleCallback']);
        add_action('init', [$this, 'handleOneTapCallback']);
    }

    public function getAuthUrl()
    {
        if (empty($this->settings['client_id'])) return '#';

        $state_token = bin2hex(random_bytes(32));
        $cookie_options = [
            'expires' => time() + (15 * MINUTE_IN_SECONDS),
            'path' => '/',
            'domain' => COOKIE_DOMAIN,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        setcookie('wpsl_oauth_state', $state_token, $cookie_options);

        $params = [
            'response_type' => 'code',
            'client_id' => $this->settings['client_id'],
            'redirect_uri' => home_url('?action=google_login_callback'),
            'scope' => 'email profile openid',
            'access_type' => 'online',
            'state' => $state_token,
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function handleOneTapCallback()
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'google_one_tap_callback') return;
        if (!isset($_POST['credential'])) $this->storeErrorAndRedirect('invalid_credential');

        if (
            !isset($_POST['wpsl_csrf_token'], $_COOKIE['wpsl_csrf_token']) ||
            !hash_equals($_COOKIE['wpsl_csrf_token'], $_POST['wpsl_csrf_token'])
        ) {
            $this->storeErrorAndRedirect('invalid_state');
        }

        setcookie('wpsl_csrf_token', '', time() - 3600, '/', COOKIE_DOMAIN);
        $user_data = $this->decodeAndVerifyJwt($_POST['credential']);

        if (!$user_data || empty($user_data['email'])) $this->storeErrorAndRedirect('invalid_credential');
        $this->loginOrRegisterUser($user_data);
    }

    private function decodeAndVerifyJwt($jwt)
    {
        if (empty($this->settings['client_id'])) return false;

        $response = wp_remote_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($jwt));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            error_log('[WPSL] Failed to verify JWT with Google tokeninfo endpoint.');
            return false;
        }

        $payload = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($payload['aud']) || $payload['aud'] !== $this->settings['client_id']) {
            error_log('[WPSL] JWT verification failed: Audience (aud) does not match Client ID.');
            return false;
        }

        if (empty($payload['iss']) || !in_array($payload['iss'], ['accounts.google.com', 'https://accounts.google.com'])) {
            error_log('[WPSL] JWT verification failed: Invalid issuer (iss).');
            return false;
        }

        return $payload;
    }

    public function handleGoogleCallback()
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'google_login_callback' || !isset($_GET['code'])) return;

        if (empty($_GET['state']) || empty($_COOKIE['wpsl_oauth_state']) || !hash_equals($_COOKIE['wpsl_oauth_state'], $_GET['state'])) {
            $this->storeErrorAndRedirect('invalid_state');
        }
        setcookie('wpsl_oauth_state', '', time() - 3600, '/', COOKIE_DOMAIN);

        $token_response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => sanitize_text_field($_GET['code']),
                'client_id' => $this->settings['client_id'],
                'client_secret' => $this->settings['client_secret'],
                'redirect_uri' => home_url('?action=google_login_callback'),
                'grant_type' => 'authorization_code',
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($token_response)) $this->storeErrorAndRedirect('token_exchange_failed');

        $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
        if (empty($token_data['access_token'])) $this->storeErrorAndRedirect('token_missing');

        $user_info_response = wp_remote_get('https://www.googleapis.com/oauth2/v3/userinfo', [
            'headers' => ['Authorization' => 'Bearer ' . $token_data['access_token']],
            'timeout' => 30
        ]);

        if (is_wp_error($user_info_response)) $this->storeErrorAndRedirect('userinfo_failed');

        $user_data = json_decode(wp_remote_retrieve_body($user_info_response), true);
        if (empty($user_data['email'])) $this->storeErrorAndRedirect('email_missing');

        $this->loginOrRegisterUser($user_data);
    }

    private function loginOrRegisterUser($google_user)
    {
        $user_email = strtolower(trim($google_user['email']));
        $existing_user = get_user_by('email', $user_email);

        if ($existing_user) {
            $this->updateUserMeta($existing_user->ID, $google_user);
            $this->loginUser($existing_user);
            return;
        }

        $allowed_users_list = $this->settings['allowed_users'] ?? [];
        $allowed_users = array_column($allowed_users_list, 'role', 'email');
        $allowed_users = array_change_key_case($allowed_users, CASE_LOWER);

        if (array_key_exists($user_email, $allowed_users)) {
            $new_user = $this->createUser($google_user, $allowed_users[$user_email]);
            if ($new_user) {
                $this->removeUserFromSettings($user_email);
                $this->loginUser($new_user);
            } else {
                $this->storeErrorAndRedirect('user_creation_failed');
            }
            return;
        }

        if (!empty($this->settings['allow_new_signups'])) {
            $default_role = $this->settings['default_signup_role'] ?? 'subscriber';
            $new_user = $this->createUser($google_user, $default_role);
            if ($new_user) {
                $this->loginUser($new_user);
            } else {
                $this->storeErrorAndRedirect('user_creation_failed');
            }
            return;
        }

        $this->storeErrorAndRedirect('not_allowed');
    }

    private function createUser($google_user, $role)
    {
        $user_email = strtolower(trim($google_user['email']));
        $username = sanitize_user(explode('@', $user_email)[0], true);
        if (username_exists($username)) $username .= wp_rand(100, 999);

        $user_id = wp_create_user($username, wp_generate_password(20), $user_email);
        if (is_wp_error($user_id)) return false;

        $user_data = [
            'ID' => $user_id,
            'first_name' => sanitize_text_field($google_user['given_name'] ?? ''),
            'last_name' => sanitize_text_field($google_user['family_name'] ?? ''),
            'display_name' => sanitize_text_field($google_user['name'] ?? $username),
            'role' => $role,
        ];
        wp_update_user($user_data);
        $this->updateUserMeta($user_id, $google_user);
        return get_user_by('id', $user_id);
    }

    private function updateUserMeta($user_id, $google_user)
    {
        if (!empty($google_user['picture'])) {
            update_user_meta($user_id, 'google_profile_picture', esc_url_raw($google_user['picture']));
        }
    }

    private function loginUser($user)
    {
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        wp_safe_redirect(admin_url());
        exit;
    }

    private function removeUserFromSettings($email)
    {
        $email = strtolower(trim($email));
        $current_settings = get_option('wpsl_settings', []);
        $allowed_users = $current_settings['allowed_users'] ?? [];

        $updated_users = array_filter($allowed_users, fn($user) => strtolower(trim($user['email'])) !== $email);

        if (count($updated_users) < count($allowed_users)) {
            $current_settings['allowed_users'] = array_values($updated_users);
            update_option('wpsl_settings', $current_settings);
        }
    }

    private function storeErrorAndRedirect($error_code)
    {
        $message = WPSL_ErrorHandler::getMessage($error_code);
        $transient_key = 'wpsl_login_error_' . uniqid();
        set_transient($transient_key, $message, 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('wpsl_error_key', $transient_key, wp_login_url()));
        exit;
    }
}
