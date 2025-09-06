<?php

class GLO_FrontendHooks
{
    private $google_auth;

    public function __construct($plugin_name, $version)
    {
        $this->google_auth = new GLO_GoogleAuth($plugin_name, $version);

        add_action('login_enqueue_scripts', [$this, 'loginStyles']);
        add_action('login_form', [$this, 'addGoogleLoginButton']);
        add_action('login_footer', [$this, 'addOneTapScript']);
        add_action('login_footer', [$this, 'addCustomLoginFooter'], 99);
        add_filter('authenticate', [$this, 'disablePasswordLogin'], 20, 3);
        add_filter('allow_password_reset', '__return_false');
        add_filter('lostpassword_url', '__return_false');
        add_action('login_errors', [$this, 'customLoginErrors']);

        // Add One Tap to homepage if enabled
        $settings = get_option('glo_settings', []);
        if (!empty($settings['one_tap_homepage'])) {
            add_action('wp_head', [$this, 'addOneTapToFrontend']);
        }
    }

    public function loginStyles()
    {
?>
        <style type="text/css">
            /* Reset and base styles */
            * {
                box-sizing: border-box;
            }

            /* Hide default WordPress login elements */
            #loginform p,
            #loginform .user-pass-wrap,
            #loginform .forgetmenot,
            #loginform .submit,
            #nav,
            #backtoblog,
            .privacy-policy-page-link {
                display: none !important;
            }

            /* Modern body styling with professional gradient */
            body.login {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                background-attachment: fixed;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                margin: 0;
                padding: 20px;
                position: relative;
                overflow: hidden;
            }

            /* Subtle background pattern */
            body.login::before {
                content: "";
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background:
                    radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                    radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                    radial-gradient(circle at 40% 40%, rgba(120, 119, 198, 0.15) 0%, transparent 50%);
                pointer-events: none;
                z-index: 0;
            }

            /* Main login container */
            #login {
                width: 100%;
                max-width: 420px;
                padding: 0;
                position: relative;
                z-index: 10;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 30px;
            }

            /* WordPress logo styling */
            .login h1 {
                margin: 0 0 20px 0;
                text-align: center;
            }

            .login h1 a {
                background: none !important;
                width: auto !important;
                height: auto !important;
                text-indent: 0 !important;
                font-size: 24px !important;
                font-weight: 300 !important;
                color: rgba(255, 255, 255, 0.9) !important;
                text-decoration: none !important;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
                letter-spacing: 0.5px;
                display: block !important;
                text-align: center;
                opacity: 0.8;
            }

            /* Main login form container */
            #loginform {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 20px;
                padding: 48px 40px 40px !important;
                box-shadow:
                    0 20px 40px rgba(0, 0, 0, 0.1),
                    0 8px 16px rgba(0, 0, 0, 0.1),
                    inset 0 1px 0 rgba(255, 255, 255, 0.8);
                border: 1px solid rgba(255, 255, 255, 0.2);
                text-align: center;
                position: relative;
                overflow: hidden;
                margin: 0;
                width: 100%;
                animation: slideInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            }

            /* Login form header */
            .glo-login-header {
                margin-bottom: 32px;
                animation: fadeInUp 0.8s ease-out 0.2s both;
            }

            .glo-login-title {
                font-size: 32px;
                color: #2c3e50;
                margin: 0 0 8px 0 !important;
                font-weight: 700;
                letter-spacing: -0.5px;
                line-height: 1.2;
            }

            .glo-login-subtitle {
                font-size: 16px;
                color: #64748b;
                margin: 0 !important;
                font-weight: 400;
                opacity: 0.8;
                line-height: 1.4;
            }

            /* Google Login Button Container */
            .google-login-container {
                margin: 0 0 24px 0;
                animation: fadeInUp 0.8s ease-out 0.4s both;
            }

            /* Modern Google login button */
            .google-login-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                background: #ffffff;
                color: #3c4043;
                border: 2px solid #dadce0;
                border-radius: 12px;
                padding: 16px 24px;
                text-decoration: none;
                font-size: 16px;
                font-weight: 500;
                text-align: center;
                width: 100%;
                box-sizing: border-box;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                min-height: 56px;
            }

            .google-login-button::before {
                content: "";
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(66, 133, 244, 0.05), transparent);
                transition: left 0.5s ease;
            }

            .google-login-button:hover::before {
                left: 100%;
            }

            .google-login-button:hover {
                background: #f8f9fa;
                border-color: #4285f4;
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(66, 133, 244, 0.15);
            }

            .google-login-button:active {
                transform: translateY(0);
                transition-duration: 0.1s;
            }

            .google-login-button:focus {
                outline: 3px solid rgba(66, 133, 244, 0.3);
                outline-offset: 2px;
            }

            /* Google logo */
            .google-logo {
                width: 20px;
                height: 20px;
                flex-shrink: 0;
                filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.1));
            }

            /* Loading animation */
            .glo-loading {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 2px solid #f3f3f3;
                border-top: 2px solid #4285F4;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }

            .google-login-button.loading {
                cursor: not-allowed;
                opacity: 0.7;
            }

            .google-login-button .glo-loading {
                display: none;
            }

            .google-login-button.loading .google-logo {
                display: none;
            }

            .google-login-button.loading .glo-loading {
                display: inline-block;
            }

            /* One Tap section */
            .one-tap-section {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid rgba(0, 0, 0, 0.08);
                animation: fadeInUp 0.8s ease-out 0.6s both;
            }

            .one-tap-info {
                font-size: 14px;
                color: #64748b;
                margin-bottom: 16px;
                opacity: 0.7;
            }

            #g_id_signin {
                display: none !important;
            }

            /* Modern footer credits - prominent and well-integrated */
            .glo-login-footer {
                margin-top: 40px;
                width: 100%;
                max-width: 420px;
                animation: fadeInUp 0.8s ease-out 0.8s both;
            }

            .glo-developer-credit {
                background: rgba(255, 255, 255, 0.25);
                backdrop-filter: blur(10px);
                padding: 20px 24px;
                border-radius: 16px;
                text-align: center;
                border: 1px solid rgba(255, 255, 255, 0.3);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            }

            .glo-developer-credit .credit-title {
                font-size: 14px;
                margin-bottom: 8px;
                color: rgba(255, 255, 255, 0.9);
                font-weight: 400;
                letter-spacing: 0.3px;
            }

            .glo-developer-credit .credit-link {
                color: #ffffff;
                text-decoration: none;
                font-size: 18px;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s ease;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            }

            .glo-developer-credit .credit-link:hover {
                color: #f8f9fa;
                transform: translateY(-1px);
                text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            }

            .glo-developer-credit .credit-link svg {
                filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
            }

            .glo-developer-credit .credit-description {
                font-size: 13px;
                color: rgba(255, 255, 255, 0.8);
                margin-top: 6px;
                font-style: italic;
                opacity: 0.9;
            }

            /* Error and success messages */
            #login_error {
                background: rgba(248, 215, 218, 0.95) !important;
                border: none !important;
                border-left: 4px solid #dc3545 !important;
                border-radius: 12px !important;
                backdrop-filter: blur(10px);
                box-shadow: 0 4px 12px rgba(220, 53, 69, 0.15);
                animation: slideInDown 0.4s ease-out;
                margin-bottom: 20px !important;
            }

            .message {
                background: rgba(212, 237, 218, 0.95) !important;
                border: none !important;
                border-left: 4px solid #28a745 !important;
                border-radius: 12px !important;
                backdrop-filter: blur(10px);
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
                margin-bottom: 20px !important;
            }

            /* Animations */
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px) scale(0.95);
                }

                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }

            @keyframes slideInDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Responsive design */
            @media (max-width: 480px) {
                body.login {
                    padding: 15px;
                }

                #login {
                    max-width: 100%;
                }

                #loginform {
                    padding: 36px 24px 32px !important;
                    border-radius: 16px;
                }

                .glo-login-title {
                    font-size: 28px;
                }

                .glo-login-subtitle {
                    font-size: 15px;
                }

                .google-login-button {
                    padding: 14px 20px;
                    font-size: 15px;
                    min-height: 52px;
                }

                .glo-developer-credit {
                    padding: 18px 20px;
                }

                .glo-developer-credit .credit-link {
                    font-size: 16px;
                }
            }

            @media (max-width: 360px) {
                #loginform {
                    padding: 32px 20px 28px !important;
                }

                .glo-login-title {
                    font-size: 26px;
                }

                .google-login-button {
                    font-size: 14px;
                    gap: 10px;
                }
            }

            /* High contrast mode support */
            @media (prefers-contrast: high) {
                body.login {
                    background: #000;
                }

                #loginform {
                    background: #fff;
                    border: 2px solid #000;
                }

                .glo-developer-credit {
                    background: rgba(0, 0, 0, 0.8);
                    border: 1px solid #fff;
                }

                .glo-developer-credit .credit-link {
                    color: #fff;
                }
            }

            /* Reduced motion support */
            @media (prefers-reduced-motion: reduce) {
                * {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                }
            }
        </style>
    <?php
    }

    public function addGoogleLoginButton()
    {
        $auth_url = $this->google_auth->getAuthUrl();

    ?>
        <div class="glo-login-header">
            <h2 class="glo-login-title"><?php _e('Welcome Back', 'google-login-only'); ?></h2>
            <p class="glo-login-subtitle"><?php _e('Sign in to continue to your account', 'google-login-only'); ?></p>
        </div>

        <div class="google-login-container">
            <a href="<?php echo esc_url($auth_url); ?>" class="google-login-button" id="google-login-btn">
                <svg class="google-logo" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                </svg>
                <div class="glo-loading"></div>
                <span><?php _e('Continue with Google', 'google-login-only'); ?></span>
            </a>
        </div>

        <div class="one-tap-section">
            <div class="one-tap-info"><?php _e('Or wait for One Tap to appear automatically', 'google-login-only'); ?></div>
            <div id="g_id_onload"></div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const loginBtn = document.getElementById("google-login-btn");
                if (loginBtn) {
                    loginBtn.addEventListener("click", function() {
                        this.classList.add("loading");
                        this.style.pointerEvents = "none";

                        setTimeout(() => {
                            this.classList.remove("loading");
                            this.style.pointerEvents = "auto";
                        }, 5000);
                    });
                }
            });
        </script>
    <?php
    }

    public function addOneTapScript()
    {
        $settings = get_option('glo_settings');
        $client_id = $settings['client_id'] ?? '';

        if (empty($client_id)) {
            return;
        }

        $callback_url = home_url('?action=google_one_tap_callback');

    ?>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
        <script>
            window.addEventListener("load", function() {
                if (typeof google !== "undefined" && google.accounts) {
                    google.accounts.id.initialize({
                        client_id: "<?php echo esc_js($client_id); ?>",
                        callback: handleCredentialResponse,
                        auto_select: false,
                        cancel_on_tap_outside: true,
                        context: 'signin'
                    });

                    setTimeout(() => {
                        google.accounts.id.prompt((notification) => {
                            if (notification.isNotDisplayed()) {
                                console.log('<?php esc_js('One Tap not displayed:', 'google-login-only'); ?>', notification.getNotDisplayedReason());
                            } else if (notification.isSkippedMoment()) {
                                console.log('<?php esc_js('One Tap skipped:', 'google-login-only'); ?>', notification.getSkippedReason());
                            }
                        });
                    }, 1000);
                }
            });

            function handleCredentialResponse(response) {
                const form = document.createElement("form");
                form.method = "POST";
                form.action = "<?php echo esc_url($callback_url); ?>";

                const input = document.createElement("input");
                input.type = "hidden";
                input.name = "credential";
                input.value = response.credential;

                const nonceInput = document.createElement("input");
                nonceInput.type = "hidden";
                nonceInput.name = "nonce";
                nonceInput.value = "<?php echo wp_create_nonce('google_one_tap_nonce'); ?>";

                form.appendChild(input);
                form.appendChild(nonceInput);
                document.body.appendChild(form);
                form.submit();
            }
        </script>
    <?php
    }

    public function addCustomLoginFooter()
    {
    ?>
        <div class="glo-login-footer">
            <div class="glo-developer-credit">
                <div class="credit-title"><?php _e('Secure Authentication Powered by', 'google-login-only'); ?></div>
                <a href="https://hardtoskip.com" target="_blank" rel="dofollow" class="credit-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2L13.09 7.26L19.5 3L16.75 9.38L23 7L17.74 12L23 17L16.75 14.62L19.5 21L13.09 16.74L12 22L10.91 16.74L4.5 21L7.25 14.62L1 17L6.26 12L1 7L7.25 9.38L4.5 3L10.91 7.26L12 2Z" />
                    </svg>
                    HardToSkip.com
                </a>
                <div class="credit-description"><?php _e('AI-Powered Viral Content Generator', 'google-login-only'); ?></div>
            </div>
        </div>
    <?php
    }

    public function addOneTapToFrontend()
    {
        if (is_user_logged_in() || is_admin()) {
            return;
        }

        $settings = get_option('glo_settings');
        $client_id = $settings['client_id'] ?? '';

        if (empty($client_id)) {
            return;
        }

        $callback_url = home_url('?action=google_one_tap_callback');

    ?>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
        <script>
            window.addEventListener("load", function() {
                if (typeof google !== "undefined" && google.accounts) {
                    google.accounts.id.initialize({
                        client_id: "<?php echo esc_js($client_id); ?>",
                        callback: handleFrontendCredentialResponse,
                        auto_select: false,
                        cancel_on_tap_outside: true,
                        context: 'use'
                    });

                    if (window.location.pathname === '/' || window.location.pathname === '') {
                        setTimeout(() => {
                            google.accounts.id.prompt();
                        }, 2000);
                    }
                }
            });

            function handleFrontendCredentialResponse(response) {
                const form = document.createElement("form");
                form.method = "POST";
                form.action = "<?php echo esc_url($callback_url); ?>";

                const input = document.createElement("input");
                input.type = "hidden";
                input.name = "credential";
                input.value = response.credential;

                const nonceInput = document.createElement("input");
                nonceInput.type = "hidden";
                nonceInput.name = "nonce";
                nonceInput.value = "<?php echo wp_create_nonce('google_one_tap_nonce'); ?>";

                form.appendChild(input);
                form.appendChild(nonceInput);
                document.body.appendChild(form);
                form.submit();
            }
        </script>
<?php
    }

    public function disablePasswordLogin($user, $username, $password)
    {
        if (!empty($username) || !empty($password)) {
            if (isset($_GET['login_error'])) {
                return $user;
            }
            return new WP_Error(
                'authentication_disabled',
                '<strong>' . __('Authentication Error:', 'google-login-only') . '</strong> ' .
                    __('Password-based authentication is disabled for security. Please use Google Sign-In above.', 'google-login-only')
            );
        }
        return $user;
    }

    public function customLoginErrors($errors)
    {
        if (isset($_GET['login_error'])) {
            $error_code = sanitize_key($_GET['login_error']);
            $messages = [
                'not_allowed' => __('Your email is not authorized to access this site. Please contact an administrator.', 'google-login-only'),
                'token_exchange_failed' => __('Could not connect to Google. Please try again.', 'google-login-only'),
                'user_creation_failed' => __('Could not create a user account. Please try again.', 'google-login-only'),
                'email_missing' => __('Could not retrieve your email from Google. Please try again.', 'google-login-only'),
                'token_missing' => __('Authentication failed. No access token received from Google.', 'google-login-only'),
                'userinfo_failed' => __('Could not retrieve your user information from Google.', 'google-login-only'),
                'invalid_credential' => __('Invalid Google credential received. Please try again.', 'google-login-only')
            ];

            $message = $messages[$error_code] ?? __('An unknown authentication error occurred.', 'google-login-only');
            return new WP_Error('google_login_error', $message, 'error');
        }
        return $errors;
    }
}
