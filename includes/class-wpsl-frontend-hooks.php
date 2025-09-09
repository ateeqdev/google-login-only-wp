<?php

class WPSL_FrontendHooks
{
    private $google_auth;
    private $settings;

    public function __construct($plugin_name, $version)
    {
        $this->google_auth = new WPSL_GoogleAuth($plugin_name, $version);
        $this->settings = get_option('wpsl_settings', []);

        add_action('login_enqueue_scripts', [$this, 'enqueueLoginAssets']);
        add_action('login_form', [$this, 'renderLoginForm']);
        add_filter('login_headerurl', fn() => home_url());
        add_filter('login_headertext', fn() => get_bloginfo('name'));

        if (!empty($this->settings['one_tap_homepage']) && !is_user_logged_in()) {
            add_action('wp_footer', [$this, 'enqueueFrontendAssets']);
        }

        add_filter('authenticate', [$this, 'disablePasswordLogin'], 20, 3);
        add_filter('allow_password_reset', '__return_false');
        add_action('login_form_lostpassword', '__return_false');
        add_action('login_form_rp', '__return_false');
        add_action('login_form_register', '__return_false');
        add_action('wp_login_errors', [$this, 'displayLoginErrors']);
    }

    public function enqueueLoginAssets()
    {
        wp_enqueue_style('wpsl-login', WPSL_PLUGIN_URL . 'assets/css/login.css', [], WPSL_VERSION);
        $this->prepareGoogleSignInScripts('signin', true);
    }

    public function enqueueFrontendAssets()
    {
        if (is_admin() || is_login()) return;
        $this->prepareGoogleSignInScripts('use', true);
    }

    private function prepareGoogleSignInScripts($context = 'signin', $show_prompt = false)
    {
        $client_id = $this->settings['client_id'] ?? '';
        if (empty($client_id)) return;

        wp_enqueue_script('google-gsi', 'https://accounts.google.com/gsi/client', [], null, true);
        wp_enqueue_script('wpsl-login', WPSL_PLUGIN_URL . 'assets/js/login.js', ['google-gsi'], WPSL_VERSION, true);

        $csrf_token = bin2hex(random_bytes(32));
        setcookie('wpsl_csrf_token', $csrf_token, [
            'expires' => time() + HOUR_IN_SECONDS,
            'path' => '/',
            'domain' => COOKIE_DOMAIN,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        wp_localize_script('wpsl-login', 'wpsl_login_params', [
            'client_id' => $client_id,
            'callback_url' => home_url('?action=google_one_tap_callback'),
            'nonce' => wp_create_nonce('google_one_tap_nonce'),
            'csrf_token' => $csrf_token,
            'context' => $context,
            'show_prompt' => $show_prompt,
            'authenticating' => __('Authenticating with Google...', 'wp-social-login'),
        ]);
    }

    public function renderLoginForm()
    {
        $auth_url = $this->google_auth->getAuthUrl();
?>
        <div class="wpsl-login-container">
            <div class="wpsl-login-header">
                <h2><?php esc_html_e('Sign In', 'wp-social-login'); ?></h2>
                <p><?php esc_html_e('Use your Google Account to continue.', 'wp-social-login'); ?></p>
            </div>
            <a href="<?php echo esc_url($auth_url); ?>" class="wpsl-google-button" id="google-login-btn">
                <svg class="wpsl-google-logo" viewBox="0 0 24 24">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                </svg>
                <div class="wpsl-spinner"></div>
                <span><?php esc_html_e('Continue with Google', 'wp-social-login'); ?></span>
            </a>
            <div class="wpsl-footer">
                <p>
                    <?php esc_html_e('Secure Sign-In by', 'wp-social-login'); ?>
                    <a href="https://hardtoskip.com/" target="_blank">HardToSkip</a>
                </p>
            </div>
        </div>
<?php
    }

    public function disablePasswordLogin($user, $username, $password)
    {
        if (!empty($username) || !empty($password)) {
            return new WP_Error(
                'authentication_disabled',
                '<strong>' . esc_html__('Login Disabled:', 'wp-social-login') . '</strong> ' .
                    esc_html__('Password-based login is disabled. Please use Google Sign-In.', 'wp-social-login')
            );
        }
        return $user;
    }

    public function displayLoginErrors($errors)
    {
        if (isset($_GET['wpsl_error_key'])) {
            $error_key = sanitize_key($_GET['wpsl_error_key']);
            $message = get_transient($error_key);
            if ($message) {
                $errors->add('google_auth_error', $message, 'error');
                delete_transient($error_key);
            }
        }
        return $errors;
    }
}
