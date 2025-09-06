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
        add_action('login_footer', [$this, 'addCustomLoginFooter'], 99); // Added custom footer
        add_filter('authenticate', [$this, 'disablePasswordLogin'], 20, 3);
        add_filter('allow_password_reset', '__return_false');
        add_filter('lostpassword_url', '__return_false');
        add_action('login_errors', [$this, 'customLoginErrors']);
    }

    /**
     * Add custom CSS to create a beautiful login page design.
     */
    public function loginStyles()
    {
        echo '
        <style type="text/css">
            /* Hide default WordPress login elements */
            #loginform p, #loginform .user-pass-wrap, #loginform .forgetmenot, #loginform .submit, #nav, #backtoblog {
                display: none !important;
            }
            
            /* Main login container */
            body.login {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                background-attachment: fixed;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            #login {
                width: 400px;
                padding: 0;
                position: relative;
            }
            
            /* Login form container */
            #loginform {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border-radius: 20px;
                padding: 50px 40px 40px 40px !important;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.2);
                text-align: center;
                position: relative;
                overflow: hidden;
            }
            
            /* Decorative elements */
            #loginform::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, #4285F4, #34A853, #FBBC05, #EA4335);
            }
            
            /* Logo/Title styling */
            .glo-login-header {
                margin-bottom: 30px;
            }
            
            .glo-login-title {
                font-size: 28px;
                color: #333;
                margin-bottom: 8px !important;
                font-weight: 600;
                letter-spacing: -0.5px;
            }
            
            .glo-login-subtitle {
                font-size: 16px;
                color: #666;
                margin-bottom: 0 !important;
                font-weight: 400;
            }
            
            /* Google Login Button - Made wider and more prominent */
            .google-login-container {
                margin-top: 30px;
            }
            
            .google-login-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                background: #fff;
                color: #333;
                border: 2px solid #dadce0;
                border-radius: 50px;
                padding: 16px 40px; /* Increased padding */
                text-decoration: none;
                font-size: 16px;
                font-weight: 500;
                text-align: center;
                width: 100%; /* Full width */
                max-width: 320px; /* Increased max width */
                box-sizing: border-box;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
                margin-bottom: 20px; /* Add margin to separate from One Tap */
            }
            
            .google-login-button::before {
                content: "";
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(66, 133, 244, 0.1), transparent);
                transition: left 0.5s;
            }
            
            .google-login-button:hover::before {
                left: 100%;
            }
            
            .google-login-button:hover {
                background: #f8f9fa;
                border-color: #4285f4;
                color: #333;
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(66, 133, 244, 0.15);
            }
            
            .google-login-button:active {
                transform: translateY(0);
            }
            
            .google-logo {
                width: 20px;
                height: 20px;
                flex-shrink: 0;
            }
            
            /* One Tap section - clearly separated */
            .one-tap-section {
                margin-top: 25px;
                padding-top: 25px;
                border-top: 1px solid #e0e0e0;
            }
            
            .one-tap-info {
                font-size: 14px;
                color: #666;
                margin-bottom: 15px;
            }
            
            /* Hide the duplicate Google button from One Tap */
            #g_id_signin {
                display: none !important;
            }
            
            /* WordPress logo styling */
            h1 a {
                background-image: none !important;
                width: auto !important;
                height: auto !important;
                text-indent: 0 !important;
                font-size: 24px !important;
                font-weight: 600 !important;
                color: #fff !important;
                text-decoration: none !important;
                margin-bottom: 20px !important;
                display: block !important;
            }
            
            /* Responsive design */
            @media (max-width: 480px) {
                #login {
                    width: 90%;
                    max-width: 350px;
                }
                
                #loginform {
                    padding: 40px 30px 30px 30px !important;
                }
                
                .glo-login-title {
                    font-size: 24px;
                }
                
                .google-login-button {
                    padding: 14px 32px;
                    font-size: 15px;
                }
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
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
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

            /* Custom Login Footer Styling */
            .glo-login-footer {
                margin-top: 25px;
                text-align: center;
                font-size: 13px;
                color: rgba(255, 255, 255, 0.7);
            }
            .glo-login-footer a {
                color: rgba(255, 255, 255, 0.9);
                text-decoration: underline;
                font-weight: bold;
            }
            .glo-login-footer a:hover {
                color: white;
            }
        </style>';
    }

    /**
     * Add the Google login interface.
     */
    public function addGoogleLoginButton()
    {
        $auth_url = $this->google_auth->getAuthUrl();

        echo '<div class="glo-login-header">';
        echo '<h2 class="glo-login-title">' . esc_html__('Welcome Back', 'google-login-only') . '</h2>';
        echo '<p class="glo-login-subtitle">' . esc_html__('Sign in to continue to your account', 'google-login-only') . '</p>';
        echo '</div>';

        echo '<div class="google-login-container">';
        echo '<a href="' . esc_url($auth_url) . '" class="google-login-button" id="google-login-btn">';
        echo '<svg class="google-logo" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">';
        echo '<path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>';
        echo '<path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>';
        echo '<path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>';
        echo '<path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>';
        echo '</svg>';
        echo '<div class="glo-loading"></div>';
        echo '<span>' . esc_html__('Continue with Google', 'google-login-only') . '</span>';
        echo '</a>';
        echo '</div>';

        // One Tap section - only for one tap, no duplicate button
        echo '<div class="one-tap-section">';
        echo '<div class="one-tap-info">' . esc_html__('Or wait for One Tap to appear automatically', 'google-login-only') . '</div>';
        echo '<div id="g_id_onload"></div>';
        echo '</div>';

        // Add click handler for loading animation
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const loginBtn = document.getElementById("google-login-btn");
            if (loginBtn) {
                loginBtn.addEventListener("click", function() {
                    this.classList.add("loading");
                });
            }
        });
        </script>';
    }

    /**
     * Add Google One Tap script to login footer.
     */
    public function addOneTapScript()
    {
        $settings = get_option('glo_settings');
        $client_id = $settings['client_id'] ?? '';

        if (empty($client_id)) {
            return;
        }

        $callback_url = home_url('?action=google_one_tap_callback');

        echo '<script src="https://accounts.google.com/gsi/client" async defer></script>';
        echo '<script>
        window.addEventListener("load", function() {
            if (typeof google !== "undefined" && google.accounts) {
                google.accounts.id.initialize({
                    client_id: "' . esc_js($client_id) . '",
                    callback: handleCredentialResponse,
                    auto_select: false,
                    cancel_on_tap_outside: true
                });
                
                // Display One Tap prompt
                google.accounts.id.prompt();
            }
        });
        
        function handleCredentialResponse(response) {
            // Create form and submit credential
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "' . esc_url($callback_url) . '";
            
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = "credential";
            input.value = response.credential;
            
            const nonceInput = document.createElement("input");
            nonceInput.type = "hidden";
            nonceInput.name = "nonce";
            nonceInput.value = "' . wp_create_nonce('google_one_tap_nonce') . '";
            
            form.appendChild(input);
            form.appendChild(nonceInput);
            document.body.appendChild(form);
            form.submit();
        }
        </script>';
    }

    /**
     * Adds a custom footer message to the login page.
     */
    public function addCustomLoginFooter()
    {
        echo '<div class="glo-login-footer">';
        echo '<p>' . sprintf(
            /* translators: %1$s is opening anchor tag, %2$s is closing anchor tag */
            esc_html__('Login With Google is Powered by %1$sHardToSkip.com%2$s', 'google-login-only'),
            '<a href="https://hardtoskip.com" target="_blank">',
            '</a>'
        ) . '</p>';
        echo '</div>';
    }

    /**
     * Completely block password-based logins.
     */
    public function disablePasswordLogin($user, $username, $password)
    {
        if (!empty($username) || !empty($password)) {
            // Allow the custom error messages to be shown without being overridden
            if (isset($_GET['login_error'])) {
                return $user;
            }
            return new WP_Error('authentication_disabled', '<strong>' . esc_html__('Error:', 'google-login-only') . '</strong> ' . esc_html__('Password-based authentication is disabled. Please use Google Sign-In.', 'google-login-only'));
        }
        return $user;
    }

    /**
     * Display custom error messages on the login page.
     */
    public function customLoginErrors($errors)
    {
        if (isset($_GET['login_error'])) {
            $error_code = sanitize_key($_GET['login_error']);
            $messages = [
                'not_allowed' => esc_html__('Your email is not authorized to access this site. Please contact an administrator.', 'google-login-only'),
                'token_exchange_failed' => esc_html__('Could not connect to Google. Please try again.', 'google-login-only'),
                'user_creation_failed' => esc_html__('Could not create a user account for you. Please try again.', 'google-login-only'),
                'email_missing' => esc_html__('Could not retrieve your email from Google. Please try again.', 'google-login-only'),
                'token_missing' => esc_html__('Authentication failed. No access token received from Google.', 'google-login-only'),
                'userinfo_failed' => esc_html__('Could not retrieve your user information from Google.', 'google-login-only'),
                'invalid_credential' => esc_html__('Invalid Google credential received. Please try again.', 'google-login-only')
            ];
            $message = $messages[$error_code] ?? esc_html__('An unknown authentication error occurred.', 'google-login-only');

            // Return a new WP_Error object to be displayed.
            return new WP_Error('google_login_error', $message, 'error');
        }
        return $errors;
    }
}
