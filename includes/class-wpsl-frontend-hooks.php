<?php

class WPSL_FrontendHooks
{
    private $google_auth;
    private $settings;

    public function __construct($plugin_name, $version)
    {
        $this->google_auth = new WPSL_GoogleAuth($plugin_name, $version);
        $this->settings = get_option('wpsl_settings', []);

        // Login Page Hooks
        add_action('login_enqueue_scripts', [$this, 'enqueueLoginAssets']);
        add_action('login_form', [$this, 'addGoogleLoginButton']);
        add_action('login_footer', [$this, 'addCustomLoginFooter']);

        // Frontend Hooks
        if (!empty($this->settings['one_tap_homepage'])) {
            add_action('wp_head', [$this, 'enqueueFrontendAssets']);
        }

        // Security Hooks
        add_filter('authenticate', [$this, 'disablePasswordLogin'], 20, 3);
        add_filter('allow_password_reset', '__return_false');
        add_filter('lostpassword_url', '__return_false');
        add_action('login_errors', [$this, 'customLoginErrors']);

        // Additional hooks for better error handling
        add_action('wp_login_errors', [$this, 'processStoredErrors']);
    }

    /**
     * Enqueue styles and scripts for the WordPress login page.
     */
    public function enqueueLoginAssets()
    {
        wp_enqueue_style('wpsl-login', WPSL_PLUGIN_URL . 'assets/css/login.css', [], filemtime(WPSL_PLUGIN_PATH . 'assets/css/login.css'));

        $this->prepareGoogleSignInScripts('signin', true);
    }

    /**
     * Enqueue scripts for the public-facing frontend (e.g., homepage).
     */
    public function enqueueFrontendAssets()
    {
        if (is_user_logged_in() || is_admin() || !is_front_page()) {
            return;
        }
        $this->prepareGoogleSignInScripts('use', true);
    }

    /**
     * Helper function to enqueue and localize Google Sign-In scripts.
     * @param string $context - The GSI context ('signin', 'signup', 'use').
     * @param bool $show_prompt - Whether to automatically trigger the One Tap prompt.
     */
    private function prepareGoogleSignInScripts($context = 'signin', $show_prompt = false)
    {
        $client_id = $this->settings['client_id'] ?? '';
        if (empty($client_id)) {
            return;
        }

        // Enqueue the remote Google GSI client library
        wp_enqueue_script('google-gsi', 'https://accounts.google.com/gsi/client', [], null, true);
        // Enqueue our local login script, making it dependent on the GSI client
        wp_enqueue_script('wpsl-login', WPSL_PLUGIN_URL . 'assets/js/login.js', ['google-gsi'], filemtime(WPSL_PLUGIN_PATH . 'assets/js/login.js'), true);

        $csrf_token = bin2hex(random_bytes(32));
        setcookie(
            'wpsl_csrf_token',
            $csrf_token,
            [
                'expires' => time() + HOUR_IN_SECONDS,
                'path' => '/',
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );

        wp_localize_script('wpsl-login', 'wpsl_login_params', [
            'client_id'             => $client_id,
            'callback_url'          => home_url('?action=google_one_tap_callback'),
            'nonce'                 => wp_create_nonce('google_one_tap_nonce'),
            'csrf_token'            => $csrf_token,
            'context'               => $context,
            'show_prompt'           => $show_prompt,
            'authenticating'        => __('Authenticating with Google...', 'wp-social-login'),
            'one_tap_not_displayed' => __('One Tap not displayed:', 'wp-social-login'),
            'one_tap_skipped'       => __('One Tap skipped:', 'wp-social-login'),
        ]);
    }

    /**
     * Display the custom Google login button on the login form.
     */
    public function addGoogleLoginButton()
    {
        $auth_url = $this->google_auth->getAuthUrl();
?>
        <div class="wpsl-login-header">
            <h2 class="wpsl-login-title"><?php _e('Welcome Back', 'wp-social-login'); ?></h2>
            <p class="wpsl-login-subtitle"><?php _e('Sign in to continue to your account', 'wp-social-login'); ?></p>
        </div>
        <div class="google-login-container">
            <a href="<?php echo esc_url($auth_url); ?>" class="google-login-button" id="google-login-btn">
                <svg class="google-logo" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                </svg>
                <div class="wpsl-loading"></div>
                <span><?php _e('Continue with Google', 'wp-social-login'); ?></span>
            </a>
        </div>
        <div class="one-tap-section">
            <p class="one-tap-info"><?php _e('Or wait for One Tap to appear automatically', 'wp-social-login'); ?></p>
        </div>
    <?php
    }

    /**
     * Display a custom footer credit on the login page.
     */
    public function addCustomLoginFooter()
    {
    ?>
        <div class="wpsl-login-footer">
            <div class="wpsl-credit"><?php _e('Secure Authentication by', 'wp-social-login'); ?>
                <a href="https://hardtoskip.com" target="_blank" rel="dofollow" class="credit-link">HardToSkip.com</a>
                <?php _e('AI-Powered Viral Content Generator', 'wp-social-login'); ?>
            </div>
        </div>
<?php
    }

    /**
     * Disable traditional username/password authentication.
     */
    public function disablePasswordLogin($user, $username, $password)
    {
        // Allow login if it's coming from our own error redirection
        if (isset($_GET['login_error'])) {
            return $user;
        }

        if (!empty($username) || !empty($password)) {
            return new WP_Error(
                'authentication_disabled',
                '<strong>' . __('Login Disabled:', 'wp-social-login') . '</strong> ' .
                    __('Password-based login is disabled. Please use the Google Sign-In button.', 'wp-social-login')
            );
        }
        return $user;
    }

    /**
     * Process and display stored login errors
     */
    public function processStoredErrors($errors)
    {
        // Check for transient error messages (more reliable than URL parameters)
        $stored_error = get_transient('wpsl_login_error_' . session_id());
        if ($stored_error) {
            delete_transient('wpsl_login_error_' . session_id());

            if (!is_wp_error($errors)) {
                $errors = new WP_Error();
            }

            $errors->add('google_login_error', $stored_error);
        }

        return $errors;
    }

    /**
     * Provide clear, user-friendly error messages on the login screen.
     */
    public function customLoginErrors($errors)
    {
        // Check URL parameters as a fallback (transient is handled by `processStoredErrors`)
        if (isset($_GET['login_error'])) {
            $error_code = sanitize_key($_GET['login_error']);
            $message = WPSL_ErrorHandler::getMessage($error_code);

            $error_message = '<div class="wpsl-error-message"><strong>' .
                __('Google Authentication Error:', 'wp-social-login') .
                '</strong><br>' . $message . '</div>';

            if (is_wp_error($errors)) {
                $errors->add('google_login_error', $error_message);
                return $errors;
            } else {
                return new WP_Error('google_login_error', $error_message);
            }
        }

        return $errors;
    }
}
