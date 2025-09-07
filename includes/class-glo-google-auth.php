<?php

class GLO_GoogleAuth
{

    private $settings;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct($plugin_name, $version)
    {
        $this->settings = get_option('glo_settings');
        add_action('init', [$this, 'startSession'], 1);
        add_action('init', [$this, 'handleGoogleCallback']);
        add_action('init', [$this, 'handleOneTapCallback']);
    }

    /**
     * Ensures session is started for OAuth state parameter.
     */
    public function startSession()
    {
        if (!session_id()) {
            session_start();
        }
    }

    /**
     * Generates the Google OAuth2 URL.
     */
    public function getAuthUrl($email_hint = '')
    {
        if (empty($this->settings['client_id'])) {
            return '#'; // Or some error state
        }

        $state_token = bin2hex(random_bytes(32));
        $_SESSION['glo_oauth_state'] = $state_token;

        $params = [
            'response_type' => 'code',
            'client_id' => $this->settings['client_id'],
            'redirect_uri' => home_url('?action=google_login_callback'),
            'scope' => 'email profile',
            'access_type' => 'online',
            'state' => $state_token, // Add state to the request
        ];

        if (!empty($email_hint)) {
            $params['login_hint'] = $email_hint;
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Handles the One Tap callback from Google.
     */
    public function handleOneTapCallback()
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'google_one_tap_callback') {
            return;
        }

        if (!isset($_POST['credential'])) {
            $this->storeErrorAndRedirect('invalid_credential');
            return;
        }

        if (
            !isset($_POST['glo_csrf_token'], $_COOKIE['glo_csrf_token']) ||
            !hash_equals($_COOKIE['glo_csrf_token'], $_POST['glo_csrf_token'])
        ) {
            $this->storeErrorAndRedirect('invalid_state');
            return;
        }

        // Clear the CSRF cookie after successful validation
        setcookie('glo_csrf_token', '', time() - 3600, '/', COOKIE_DOMAIN, is_ssl(), true);

        $credential = sanitize_text_field($_POST['credential']);

        // Decode AND VERIFY the JWT token using the secure, lightweight function
        $user_data = $this->decodeJWT_secure_lightweight($credential);

        if (!$user_data || empty($user_data['email'])) {
            $this->storeErrorAndRedirect('invalid_credential');
            return;
        }

        $google_user = [
            'email' => $user_data['email'],
            'given_name' => $user_data['given_name'] ?? '',
            'family_name' => $user_data['family_name'] ?? '',
            'name' => $user_data['name'] ?? $user_data['email'],
            'picture' => $user_data['picture'] ?? ''
        ];

        $this->loginOrRegisterUser($google_user);
    }

    /**
     * Securely decodes and VERIFIES the JWT token using Google's tokeninfo endpoint.
     * This is a lightweight and secure alternative to using a full library.
     *
     * @param string $jwt The ID token from Google.
     * @return array|false The user payload if the token is valid, otherwise false.
     */
    private function decodeJWT_secure_lightweight($jwt)
    {
        if (empty($this->settings['client_id'])) {
            error_log(sprintf('[%s] %s', 'google-login-only', __('Google Client ID is not configured.', 'google-login-only')));
            return false;
        }

        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($jwt);
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            error_log(sprintf(
                '[%s] %s: %s',
                'google-login-only',
                __('Failed to connect to Google tokeninfo endpoint.', 'google-login-only'),
                $response->get_error_message()
            ));
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log(sprintf(
                '[%s] %s: %s %d',
                'google-login-only',
                __('Invalid ID token. Google responded with status', 'google-login-only'),
                $status_code
            ));
            return false;
        }

        $payload = json_decode(wp_remote_retrieve_body($response), true);

        // Final security check: ensure the token was issued to our app.
        if (empty($payload['aud']) || $payload['aud'] !== $this->settings['client_id']) {
            error_log(sprintf('[%s] %s', 'google-login-only', __('Token audience (aud) does not match Client ID.', 'google-login-only')));
            return false;
        }

        return $payload;
    }


    /**
     * Handles the callback from Google after user authentication.
     */
    public function handleGoogleCallback()
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'google_login_callback' || !isset($_GET['code'])) {
            return;
        }

        if (
            empty($_GET['state']) ||
            !isset($_SESSION['glo_oauth_state']) ||
            !hash_equals($_SESSION['glo_oauth_state'], $_GET['state'])
        ) {
            $this->storeErrorAndRedirect('invalid_state');
            return;
        }

        unset($_SESSION['glo_oauth_state']);

        // 1. Exchange authorization code for an access token
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

        if (is_wp_error($token_response)) {
            $this->storeErrorAndRedirect('token_exchange_failed');
            return;
        }

        $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
        if (empty($token_data['access_token'])) {
            $this->storeErrorAndRedirect('token_missing');
            return;
        }

        // 2. Use access token to get user info
        $user_info_response = wp_remote_get('https://www.googleapis.com/oauth2/v3/userinfo', [
            'headers' => ['Authorization' => 'Bearer ' . $token_data['access_token']],
            'timeout' => 30
        ]);

        if (is_wp_error($user_info_response)) {
            $this->storeErrorAndRedirect('userinfo_failed');
            return;
        }

        $user_data = json_decode(wp_remote_retrieve_body($user_info_response), true);
        if (empty($user_data['email'])) {
            $this->storeErrorAndRedirect('email_missing');
            return;
        }

        // 3. Authenticate or create user in WordPress
        $this->loginOrRegisterUser($user_data);
    }

    /**
     * Logs in an existing user or registers a new one.
     */
    private function loginOrRegisterUser($google_user)
    {
        $user_email = strtolower(trim($google_user['email']));

        $existing_user = get_user_by('email', $user_email);

        if ($existing_user) {
            $this->removeUserFromSettings($user_email);
            $this->loginUser($existing_user);
            return;
        }

        $allowed_users_list = $this->settings['allowed_users'] ?? [];
        $allowed_users = array_column($allowed_users_list, 'role', 'email');
        $allowed_users = array_change_key_case($allowed_users, CASE_LOWER);

        if (!array_key_exists($user_email, $allowed_users)) {
            $this->storeErrorAndRedirect('not_allowed');
            return;
        }

        $user_role = $allowed_users[$user_email];
        $new_user = $this->createUser($google_user, $user_role);

        if ($new_user) {
            $this->removeUserFromSettings($user_email);
            $this->loginUser($new_user);
        } else {
            $this->storeErrorAndRedirect('user_creation_failed');
        }
    }

    /**
     * Create a new WordPress user from Google data.
     */
    private function createUser($google_user, $role)
    {
        $user_email = strtolower(trim($google_user['email']));

        $username = sanitize_user(explode('@', $user_email)[0], true);

        if (username_exists($username)) {
            // If username exists, append a random number
            $username = $username . wp_rand(100, 999);
        }

        $user_id = wp_create_user($username, wp_generate_password(20), $user_email);

        if (is_wp_error($user_id)) {
            return false;
        }

        // Update user with Google data
        $user_data = [
            'ID' => $user_id,
            'first_name' => sanitize_text_field($google_user['given_name'] ?? ''),
            'last_name' => sanitize_text_field($google_user['family_name'] ?? ''),
            'display_name' => sanitize_text_field($google_user['name'] ?? $username),
            'role' => $role,
        ];

        wp_update_user($user_data);

        // Store Google profile picture if available
        if (!empty($google_user['picture'])) {
            update_user_meta($user_id, 'google_profile_picture', esc_url($google_user['picture']));
        }

        return get_user_by('id', $user_id);
    }

    /**
     * Log in a WordPress user.
     */
    private function loginUser($user)
    {
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        wp_redirect(admin_url());
        exit;
    }

    /**
     * Remove user from allowed_users settings after they become a WordPress user.
     */
    private function removeUserFromSettings($email)
    {
        $email = strtolower(trim($email));
        $current_settings = get_option('glo_settings', []);
        $allowed_users = $current_settings['allowed_users'] ?? [];

        $updated_users = array_filter($allowed_users, function ($user) use ($email) {
            return strtolower(trim($user['email'])) !== $email;
        });

        if (count($updated_users) !== count($allowed_users)) {
            $current_settings['allowed_users'] = array_values($updated_users); // Reset array indexes
            update_option('glo_settings', $current_settings);

            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
        }
    }

    /**
     * Check if a user (by email) is allowed to access the site.
     */
    public function isUserAllowed($email)
    {
        $email = strtolower(trim($email));

        // Check if user exists in WordPress
        if (get_user_by('email', $email)) {
            return true;
        }

        // Check if user is in allowed_users settings
        $allowed_users_list = $this->settings['allowed_users'] ?? [];
        $allowed_emails = array_map(function ($user) {
            return strtolower(trim($user['email']));
        }, $allowed_users_list);

        return in_array($email, $allowed_emails);
    }

    /**
     * Store error message in transient and redirect to login page.
     * @param string $error_code The error code to store and display.
     */
    private function storeErrorAndRedirect($error_code)
    {
        $message = GLO_ErrorHandler::getMessage($error_code);

        // Format the error message with styling
        $formatted_message = '<div class="glo-error-message"><strong>' .
            __('Google Authentication Error:', 'google-login-only') .
            '</strong><br>' . $message . '</div>';

        // Store error in transient using session ID as key
        $transient_key = 'glo_login_error_' . session_id();
        set_transient($transient_key, $formatted_message, 5 * MINUTE_IN_SECONDS);

        // Redirect to login page without error parameters in URL
        wp_redirect(wp_login_url());
        exit;
    }
}
