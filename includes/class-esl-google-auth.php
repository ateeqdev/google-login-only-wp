<?php

class OTL_GoogleAuth
{
    private $settings;

    public function __construct($plugin_name, $version)
    {
        $this->settings = get_option('otl_settings');
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
        setcookie('otl_oauth_state', $state_token, $cookie_options);

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

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'google_one_tap_nonce')) {
            $this->storeErrorAndRedirect('invalid_state');
        }

        if (!isset($_POST['credential'])) $this->storeErrorAndRedirect('invalid_credential');

        if (
            !isset($_POST['otl_csrf_token'], $_COOKIE['otl_csrf_token']) ||
            !hash_equals(wp_unslash($_COOKIE['otl_csrf_token']), wp_unslash($_POST['otl_csrf_token']))
        ) {
            $this->storeErrorAndRedirect('invalid_state');
        }

        setcookie('otl_csrf_token', '', time() - 3600, '/', COOKIE_DOMAIN);
        $credential = isset($_POST['credential']) ? sanitize_text_field(wp_unslash($_POST['credential'])) : '';
        $user_data = $this->decodeAndVerifyJwt($credential);

        if (!$user_data || empty($user_data['email'])) $this->storeErrorAndRedirect('invalid_credential');
        $this->loginOrRegisterUser($user_data);
    }

    private function decodeAndVerifyJwt($jwt)
    {
        if (empty($this->settings['client_id'])) return false;

        $response = wp_remote_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($jwt));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $payload = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($payload['aud']) || $payload['aud'] !== $this->settings['client_id']) {
            return false;
        }

        if (empty($payload['iss']) || !in_array($payload['iss'], ['accounts.google.com', 'https://accounts.google.com'])) {
            return false;
        }

        return $payload;
    }

    public function handleGoogleCallback()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['action']) || $_GET['action'] !== 'google_login_callback' || !isset($_GET['code'])) return;

        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $cookie_state = isset($_COOKIE['otl_oauth_state']) ? sanitize_text_field(wp_unslash($_COOKIE['otl_oauth_state'])) : '';

        if (empty($state) || empty($cookie_state) || !hash_equals($cookie_state, $state)) {
            $this->storeErrorAndRedirect('invalid_state');
        }
        setcookie('otl_oauth_state', '', time() - 3600, '/', COOKIE_DOMAIN);

        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        // phpcs:enable

        $token_response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => $code,
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
        $current_settings = get_option('otl_settings', []);
        $allowed_users = $current_settings['allowed_users'] ?? [];

        $updated_users = array_filter($allowed_users, fn($user) => strtolower(trim($user['email'])) !== $email);

        if (count($updated_users) < count($allowed_users)) {
            $current_settings['allowed_users'] = array_values($updated_users);
            update_option('otl_settings', $current_settings);
        }
    }

    private function storeErrorAndRedirect($error_code)
    {
        $message = OTL_ErrorHandler::getMessage($error_code);
        $transient_key = 'otl_login_error_' . uniqid();
        set_transient($transient_key, $message, 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('otl_error_key', $transient_key, wp_login_url()));
        exit;
    }
}
