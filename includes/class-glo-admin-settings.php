<?php
// === ./includes/class-glo-admin-settings.php ===

class GLO_AdminSettings
{
    private $plugin_name;
    private $version;
    private $option_name = 'glo_settings';
    private $wizard_progress_option = 'glo_wizard_progress';

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('admin_menu', [$this, 'addPluginPage']);
        add_action('admin_init', [$this, 'pageInit']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_ajax_glo_update_step', [$this, 'ajaxUpdateStep']);
        add_action('wp_ajax_glo_test_connection', [$this, 'ajaxTestConnection']);
    }

    public function enqueueAdminAssets($hook)
    {
        if ($hook !== 'settings_page_' . $this->plugin_name) {
            return;
        }

        wp_enqueue_script('glo-admin', GLO_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], $this->version, true);
        wp_localize_script('glo-admin', 'glo_admin', [
            'nonce' => wp_create_nonce('glo_admin_nonce'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'strings' => [
                'saving' => __('Saving...', 'google-login-only'),
                'saved' => __('Saved!', 'google-login-only'),
                'error' => __('Error occurred', 'google-login-only'),
                'testing' => __('Testing...', 'google-login-only'),
                'connection_success' => __('Connection successful!', 'google-login-only'),
                'connection_failed' => __('Connection failed', 'google-login-only'),
                'confirm_reset' => __('Are you sure you want to reset this step? All current settings will be lost.', 'google-login-only')
            ]
        ]);

        add_action('admin_head', [$this, 'addAdminStyles']);
    }

    public function addAdminStyles()
    {
?>
        <style>
            .glo-wizard-container {
                max-width: 1200px;
                margin: 20px 0;
            }

            .glo-hero-section {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 40px;
                border-radius: 12px;
                text-align: center;
                margin-bottom: 30px;
                position: relative;
                overflow: hidden;
            }

            .glo-hero-section::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="rgba(255,255,255,0.1)"><polygon points="1000,0 1000,100 0,100"/></svg>') no-repeat;
                background-size: cover;
            }

            .glo-hero-content {
                position: relative;
                z-index: 1;
            }

            .glo-hero-title {
                font-size: 32px;
                margin-bottom: 10px;
                font-weight: 600;
            }

            .glo-hero-subtitle {
                font-size: 18px;
                opacity: 0.9;
                margin-bottom: 20px;
            }

            .glo-hero-credit {
                background: rgba(255, 255, 255, 0.15);
                border-radius: 25px;
                padding: 10px 20px;
                display: inline-block;
                backdrop-filter: blur(10px);
            }

            .glo-hero-credit a {
                color: white;
                text-decoration: none;
                font-weight: bold;
                font-size: 16px;
            }

            .glo-hero-credit a:hover {
                text-decoration: underline;
            }

            .glo-wizard-nav {
                background: white;
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 30px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }

            .glo-progress-bar {
                background: #f0f0f0;
                height: 8px;
                border-radius: 4px;
                margin-bottom: 25px;
                overflow: hidden;
            }

            .glo-progress-fill {
                background: linear-gradient(90deg, #4285F4, #34A853);
                height: 100%;
                border-radius: 4px;
                transition: width 0.3s ease;
            }

            .glo-steps {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
            }

            .glo-step {
                flex: 1;
                text-align: center;
                padding: 15px 10px;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.3s ease;
                min-width: 120px;
                position: relative;
            }

            .glo-step.active {
                background: #e3f2fd;
                border: 2px solid #2196f3;
            }

            .glo-step.completed {
                background: #e8f5e8;
                border: 2px solid #4caf50;
            }

            .glo-step.pending {
                background: #fafafa;
                border: 2px solid #e0e0e0;
                opacity: 0.7;
            }

            .glo-step-icon {
                width: 32px;
                height: 32px;
                margin: 0 auto 8px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                color: white;
            }

            .glo-step.active .glo-step-icon {
                background: #2196f3;
            }

            .glo-step.completed .glo-step-icon {
                background: #4caf50;
            }

            .glo-step.pending .glo-step-icon {
                background: #ccc;
            }

            .glo-step-title {
                font-weight: 600;
                margin-bottom: 5px;
                font-size: 14px;
            }

            .glo-step-description {
                font-size: 12px;
                color: #666;
                line-height: 1.3;
            }

            .glo-wizard-content {
                background: white;
                border-radius: 12px;
                padding: 40px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }

            .glo-step-header {
                text-align: center;
                margin-bottom: 40px;
            }

            .glo-step-header h2 {
                font-size: 28px;
                margin-bottom: 10px;
                color: #333;
            }

            .glo-step-header p {
                font-size: 16px;
                color: #666;
                max-width: 600px;
                margin: 0 auto;
                line-height: 1.5;
            }

            .glo-wizard-actions {
                margin-top: 40px;
                padding-top: 30px;
                border-top: 1px solid #eee;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .glo-btn {
                padding: 12px 24px;
                border-radius: 6px;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s ease;
                border: none;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .glo-btn-primary {
                background: #4285F4;
                color: white;
            }

            .glo-btn-primary:hover {
                background: #3367D6;
                transform: translateY(-1px);
            }

            .glo-btn-secondary {
                background: #f5f5f5;
                color: #333;
                border: 1px solid #ddd;
            }

            .glo-btn-secondary:hover {
                background: #e9e9e9;
            }

            .glo-btn-success {
                background: #34A853;
                color: white;
            }

            .glo-btn-success:hover {
                background: #2e7d32;
            }

            .glo-form-group {
                margin-bottom: 25px;
            }

            .glo-form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                color: #333;
            }

            .glo-form-group input,
            .glo-form-group select,
            .glo-form-group textarea {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid #e1e5e9;
                border-radius: 6px;
                font-size: 14px;
                transition: border-color 0.3s ease;
            }

            .glo-form-group input:focus,
            .glo-form-group select:focus,
            .glo-form-group textarea:focus {
                outline: none;
                border-color: #4285F4;
                box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
            }

            .glo-info-card {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
            }

            .glo-info-card.success {
                background: #d4edda;
                border-color: #c3e6cb;
                color: #155724;
            }

            .glo-info-card.warning {
                background: #fff3cd;
                border-color: #ffeaa7;
                color: #856404;
            }

            .glo-info-card.error {
                background: #f8d7da;
                border-color: #f5c6cb;
                color: #721c24;
            }

            .glo-copy-field {
                display: flex;
                gap: 10px;
                align-items: stretch;
            }

            .glo-copy-field input {
                flex: 1;
                font-family: monospace;
                background: #f8f9fa;
            }

            .glo-copy-btn {
                padding: 12px 16px;
                background: #6c757d;
                color: white;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                white-space: nowrap;
            }

            .glo-copy-btn:hover {
                background: #5a6268;
            }

            .glo-security-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }

            .glo-security-card {
                background: #f8f9fa;
                border: 2px solid #e9ecef;
                border-radius: 8px;
                padding: 20px;
                transition: border-color 0.3s ease;
            }

            .glo-security-card.enabled {
                border-color: #28a745;
                background: #d4edda;
            }

            .glo-security-card h4 {
                margin-top: 0;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .glo-security-toggle {
                width: 20px;
                height: 20px;
            }

            .glo-user-list {
                margin-top: 20px;
            }

            .glo-user-item {
                display: flex;
                align-items: center;
                gap: 15px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 8px;
                margin-bottom: 10px;
            }

            .glo-user-item input {
                flex: 1;
                margin: 0;
            }

            .glo-remove-user {
                background: #dc3545;
                color: white;
                border: none;
                padding: 8px 12px;
                border-radius: 4px;
                cursor: pointer;
            }

            .glo-loading {
                display: inline-block;
                width: 16px;
                height: 16px;
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

            .glo-hidden {
                display: none !important;
            }

            @media (max-width: 768px) {
                .glo-steps {
                    flex-direction: column;
                }

                .glo-step {
                    min-width: auto;
                }

                .glo-wizard-actions {
                    flex-direction: column;
                    gap: 15px;
                }

                .glo-security-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    <?php
    }

    public function addPluginPage()
    {
        add_options_page(
            __('Google Login Only Settings', 'google-login-only'),
            __('Google Login Only', 'google-login-only'),
            'manage_options',
            $this->plugin_name,
            [$this, 'createAdminPage']
        );
    }

    public function pageInit()
    {
        register_setting(
            $this->plugin_name,
            $this->option_name,
            [$this, 'sanitize']
        );
    }

    public function createAdminPage()
    {
        $current_step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'overview';
        $progress = get_option($this->wizard_progress_option, []);

    ?>
        <div class="wrap glo-wizard-container">
            <?php $this->renderHeroSection(); ?>
            <?php $this->renderWizardNavigation($current_step, $progress); ?>
            <?php $this->renderWizardContent($current_step, $progress); ?>
        </div>
    <?php
    }

    private function renderHeroSection()
    {
    ?>
        <div class="glo-hero-section">
            <div class="glo-hero-content">
                <h1 class="glo-hero-title"><?php _e('Google Login Only', 'google-login-only'); ?></h1>
                <p class="glo-hero-subtitle"><?php _e('Secure, beautiful, and user-friendly Google authentication for WordPress', 'google-login-only'); ?></p>
            </div>
        </div>
    <?php
    }

    private function renderWizardNavigation($current_step, $progress)
    {
        $steps = $this->getWizardSteps();
        $completed_steps = array_keys(array_filter($progress));
        $total_steps = count($steps);
        $progress_percentage = (count($completed_steps) / $total_steps) * 100;

    ?>
        <div class="glo-wizard-nav">
            <div class="glo-progress-bar">
                <div class="glo-progress-fill" style="width: <?php echo esc_attr($progress_percentage); ?>%"></div>
            </div>
            <div class="glo-steps">
                <?php foreach ($steps as $key => $step): ?>
                    <?php
                    $is_current = ($current_step === $key);
                    $is_completed = in_array($key, $completed_steps);
                    $step_class = $is_current ? 'active' : ($is_completed ? 'completed' : 'pending');
                    ?>
                    <div class="glo-step <?php echo esc_attr($step_class); ?>" onclick="window.location.href='<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=' . $key)); ?>'">
                        <div class="glo-step-icon">
                            <?php if ($is_completed): ?>
                                ✓
                            <?php else: ?>
                                <?php echo esc_html($step['number']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="glo-step-title"><?php echo esc_html($step['title']); ?></div>
                        <div class="glo-step-description"><?php echo esc_html($step['description']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php
    }

    private function renderWizardContent($current_step, $progress)
    {
    ?>
        <div class="glo-wizard-content">
            <?php
            switch ($current_step) {
                case 'overview':
                    $this->renderOverviewStep($progress);
                    break;
                case 'google_api':
                    $this->renderGoogleApiStep();
                    break;
                case 'security':
                    $this->renderSecurityStep();
                    break;
                case 'users':
                    $this->renderUsersStep();
                    break;
                case 'one_tap':
                    $this->renderOneTapStep();
                    break;
                case 'complete':
                    $this->renderCompleteStep();
                    break;
                default:
                    $this->renderOverviewStep($progress);
            }
            ?>
        </div>
    <?php
    }

    private function renderOverviewStep($progress)
    {
        $settings = get_option($this->option_name, []);
        $is_configured = !empty($settings['client_id']) && !empty($settings['client_secret']);

    ?>
        <div class="glo-step-header">
            <h2><?php _e('Welcome to Google Login Only', 'google-login-only'); ?></h2>
            <p><?php _e('This setup wizard will guide you through configuring secure Google authentication for your WordPress site. Each step builds upon the previous one to ensure a complete and secure setup.', 'google-login-only'); ?></p>
        </div>

        <?php if (!$is_configured): ?>
            <div class="glo-info-card warning">
                <h4><?php _e('⚠️ Setup Required', 'google-login-only'); ?></h4>
                <p><?php _e('Your plugin is not yet configured. Users cannot log in until you complete the Google API setup.', 'google-login-only'); ?></p>
            </div>
        <?php else: ?>
            <div class="glo-info-card success">
                <h4><?php _e('✅ Plugin is Active', 'google-login-only'); ?></h4>
                <p><?php _e('Google Login Only is configured and working. You can still modify settings or enable additional features.', 'google-login-only'); ?></p>
            </div>
        <?php endif; ?>

        <div class="glo-info-card">
            <h4><?php _e('🎯 About This Plugin', 'google-login-only'); ?></h4>
            <p><?php printf(
                    __('This plugin was created by %1$s after a successful brute-force attack demonstrated the vulnerabilities of password-based authentication. By enforcing Google OAuth, we eliminate password-related security risks while providing a superior user experience.', 'google-login-only'),
                    '<a href="https://hardtoskip.com" target="_blank"><strong>HardToSkip.com</strong></a>'
                ); ?></p>
        </div>

        <div class="glo-wizard-actions">
            <div></div>
            <a href="<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=google_api')); ?>" class="glo-btn glo-btn-primary">
                <?php _e('Start Setup', 'google-login-only'); ?>
                <span>→</span>
            </a>
        </div>
    <?php
    }

    private function renderGoogleApiStep()
    {
        $settings = get_option($this->option_name, []);

    ?>
        <div class="glo-step-header">
            <h2><?php _e('Google API Configuration', 'google-login-only'); ?></h2>
            <p><?php _e('Set up your Google OAuth credentials to enable secure authentication. This is the foundation of the entire system.', 'google-login-only'); ?></p>
        </div>

        <form method="post" action="options.php" id="google-api-form">
            <?php settings_fields($this->plugin_name); ?>

            <div class="glo-info-card">
                <h4><?php _e('📋 Step-by-Step Instructions', 'google-login-only'); ?></h4>
                <ol>
                    <li><?php printf(__('Go to the %s', 'google-login-only'), '<a href="https://console.cloud.google.com/" target="_blank">' . __('Google Cloud Console', 'google-login-only') . '</a>'); ?></li>
                    <li><?php _e('Create a new project or select an existing one', 'google-login-only'); ?></li>
                    <li><?php _e('Navigate to "APIs & Services" → "Credentials"', 'google-login-only'); ?></li>
                    <li><?php _e('Click "Create Credentials" → "OAuth client ID"', 'google-login-only'); ?></li>
                    <li><?php _e('Choose "Web application" as the application type', 'google-login-only'); ?></li>
                    <li><?php _e('Add the redirect URIs below to your OAuth client', 'google-login-only'); ?></li>
                </ol>
            </div>

            <div class="glo-form-group">
                <label><?php _e('Authorized Redirect URIs (copy these exactly)', 'google-login-only'); ?></label>
                <div class="glo-copy-field">
                    <input type="text" readonly value="<?php echo esc_attr(home_url('?action=google_login_callback')); ?>">
                    <button type="button" class="glo-copy-btn" onclick="copyToClipboard(this)"><?php _e('Copy', 'google-login-only'); ?></button>
                </div>
                <div class="glo-copy-field" style="margin-top: 10px;">
                    <input type="text" readonly value="<?php echo esc_attr(home_url('?action=google_one_tap_callback')); ?>">
                    <button type="button" class="glo-copy-btn" onclick="copyToClipboard(this)"><?php _e('Copy', 'google-login-only'); ?></button>
                </div>
            </div>

            <div class="glo-form-group">
                <label><?php _e('Authorized JavaScript Origins', 'google-login-only'); ?></label>
                <div class="glo-copy-field">
                    <input type="text" readonly value="<?php echo esc_attr(home_url()); ?>">
                    <button type="button" class="glo-copy-btn" onclick="copyToClipboard(this)"><?php _e('Copy', 'google-login-only'); ?></button>
                </div>
            </div>

            <div class="glo-form-group">
                <label for="client_id"><?php _e('Google Client ID', 'google-login-only'); ?></label>
                <input type="text" id="client_id" name="<?php echo esc_attr($this->option_name); ?>[client_id]" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>" placeholder="<?php esc_attr_e('Your Google OAuth Client ID', 'google-login-only'); ?>" required>
            </div>

            <div class="glo-form-group">
                <label for="client_secret"><?php _e('Google Client Secret', 'google-login-only'); ?></label>
                <input type="password" id="client_secret" name="<?php echo esc_attr($this->option_name); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>" placeholder="<?php esc_attr_e('Your Google OAuth Client Secret', 'google-login-only'); ?>" required>
            </div>

            <div class="glo-wizard-actions">
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=overview')); ?>" class="glo-btn glo-btn-secondary">
                    <span>←</span>
                    <?php _e('Back', 'google-login-only'); ?>
                </a>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="glo-btn glo-btn-secondary" onclick="testConnection()"><?php _e('Test Connection', 'google-login-only'); ?></button>
                    <button type="submit" class="glo-btn glo-btn-primary">
                        <?php _e('Save & Continue', 'google-login-only'); ?>
                        <span>→</span>
                    </button>
                </div>
            </div>
        </form>

        <script>
            function copyToClipboard(button) {
                const input = button.previousElementSibling;
                input.select();
                document.execCommand('copy');

                const originalText = button.textContent;
                button.textContent = '<?php esc_js('Copied!', 'google-login-only'); ?>';
                button.style.background = '#28a745';

                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.background = '';
                }, 2000);
            }

            function testConnection() {
                // Implementation for testing Google API connection
                alert('<?php esc_js('Connection test functionality coming soon!', 'google-login-only'); ?>');
            }

            document.getElementById('google-api-form').addEventListener('submit', function(e) {
                const clientId = document.getElementById('client_id').value;
                const clientSecret = document.getElementById('client_secret').value;

                if (!clientId || !clientSecret) {
                    e.preventDefault();
                    alert('<?php esc_js('Please fill in both Client ID and Client Secret', 'google-login-only'); ?>');
                }
            });
        </script>
    <?php
    }

    private function renderSecurityStep()
    {
        $settings = get_option($this->option_name, []);
        $security = $settings['security_features'] ?? [];

    ?>
        <div class="glo-step-header">
            <h2><?php _e('Security Features', 'google-login-only'); ?></h2>
            <p><?php _e('Enable additional security measures to protect your site from common attack vectors. These features were implemented based on real-world security incidents.', 'google-login-only'); ?></p>
        </div>

        <div class="glo-info-card warning">
            <h4><?php _e('⚠️ Important Security Notice', 'google-login-only'); ?></h4>
            <p><?php _e(
                    'This plugin was developed in response to real-world brute-force attacks on WordPress sites. The included security features help reduce such risks, but they are not a complete security solution. Always keep WordPress updated and use reliable hosting for maximum protection.',
                    'google-login-only'
                ); ?></p>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields($this->plugin_name); ?>
            <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_id]" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>">
            <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>">

            <div class="glo-security-grid">
                <?php
                $security_features = [
                    'disable_xmlrpc' => [
                        'title' => __('Disable XML-RPC', 'google-login-only'),
                        'description' => __('Prevents brute-force attacks via XML-RPC protocol', 'google-login-only'),
                        'impact' => __('May break: Mobile apps, Jetpack features, some backup plugins', 'google-login-only')
                    ],
                    'disable_file_editing' => [
                        'title' => __('Disable File Editing', 'google-login-only'),
                        'description' => __('Prevents code injection if admin account is compromised', 'google-login-only'),
                        'impact' => __('May break: Theme/plugin editors in admin dashboard', 'google-login-only')
                    ],
                    'hide_wp_version' => [
                        'title' => __('Hide WordPress Version', 'google-login-only'),
                        'description' => __('Makes it harder for attackers to identify vulnerabilities', 'google-login-only'),
                        'impact' => __('Generally safe, no functionality impact', 'google-login-only')
                    ],
                    'restrict_rest_api' => [
                        'title' => __('Restrict REST API', 'google-login-only'),
                        'description' => __('Prevents unauthorized data access via REST API', 'google-login-only'),
                        'impact' => __('May break: Public API access, some plugins, headless setups', 'google-login-only')
                    ],
                    'block_sensitive_files' => [
                        'title' => __('Block Sensitive Files', 'google-login-only'),
                        'description' => __('Prevents direct access to wp-config.php, .htaccess, etc.', 'google-login-only'),
                        'impact' => __('Generally safe, blocks direct file access', 'google-login-only')
                    ]
                ];

                foreach ($security_features as $key => $feature):
                    $is_enabled = !empty($security[$key]);
                ?>
                    <div class="glo-security-card <?php echo $is_enabled ? 'enabled' : ''; ?>">
                        <h4>
                            <input type="checkbox" class="glo-security-toggle" name="<?php echo esc_attr($this->option_name); ?>[security_features][<?php echo esc_attr($key); ?>]" value="1" <?php checked($is_enabled); ?>>
                            <?php echo esc_html($feature['title']); ?>
                        </h4>
                        <p><?php echo esc_html($feature['description']); ?></p>
                        <small style="color: #666;"><strong><?php _e('Impact:', 'google-login-only'); ?></strong> <?php echo esc_html($feature['impact']); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="glo-wizard-actions">
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=google_api')); ?>" class="glo-btn glo-btn-secondary">
                    <span>←</span>
                    <?php _e('Back', 'google-login-only'); ?>
                </a>
                <button type="submit" class="glo-btn glo-btn-primary">
                    <?php _e('Save & Continue', 'google-login-only'); ?>
                    <span>→</span>
                </button>
            </div>
        </form>
    <?php
    }

    private function renderUsersStep()
    {
        $settings = get_option($this->option_name, []);
        $allowed_users = $settings['allowed_users'] ?? [];

    ?>
        <div class="glo-step-header">
            <h2><?php _e('User Management', 'google-login-only'); ?></h2>
            <p><?php _e('Manage who can access your site. Add users who should be able to log in with their Google accounts.', 'google-login-only'); ?></p>
        </div>

        <div class="glo-info-card">
            <h4><?php _e('📝 How User Management Works', 'google-login-only'); ?></h4>
            <p><?php _e('Add email addresses for users who should be able to access your site. When they sign in with Google for the first time, they\'ll automatically be created as WordPress users with the role you specify.', 'google-login-only'); ?></p>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields($this->plugin_name); ?>
            <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_id]" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>">
            <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>">

            <div class="glo-form-group">
                <label><?php _e('Authorized Users', 'google-login-only'); ?></label>
                <div class="glo-user-list" id="user-list">
                    <?php if (empty($allowed_users)): ?>
                        <div class="glo-user-item">
                            <input type="email" name="<?php echo esc_attr($this->option_name); ?>[allowed_users][0][email]" placeholder="<?php esc_attr_e('user@example.com', 'google-login-only'); ?>" required>
                            <select name="<?php echo esc_attr($this->option_name); ?>[allowed_users][0][role]">
                                <option value="administrator"><?php _e('Administrator', 'google-login-only'); ?></option>
                                <option value="editor"><?php _e('Editor', 'google-login-only'); ?></option>
                                <option value="author"><?php _e('Author', 'google-login-only'); ?></option>
                                <option value="contributor"><?php _e('Contributor', 'google-login-only'); ?></option>
                                <option value="subscriber" selected><?php _e('Subscriber', 'google-login-only'); ?></option>
                            </select>
                            <button type="button" class="glo-remove-user" onclick="removeUser(this)"><?php _e('Remove', 'google-login-only'); ?></button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($allowed_users as $index => $user): ?>
                            <div class="glo-user-item">
                                <input type="email" name="<?php echo esc_attr($this->option_name); ?>[allowed_users][<?php echo esc_attr($index); ?>][email]" value="<?php echo esc_attr($user['email']); ?>" required>
                                <select name="<?php echo esc_attr($this->option_name); ?>[allowed_users][<?php echo esc_attr($index); ?>][role]">
                                    <option value="administrator" <?php selected($user['role'], 'administrator'); ?>><?php _e('Administrator', 'google-login-only'); ?></option>
                                    <option value="editor" <?php selected($user['role'], 'editor'); ?>><?php _e('Editor', 'google-login-only'); ?></option>
                                    <option value="author" <?php selected($user['role'], 'author'); ?>><?php _e('Author', 'google-login-only'); ?></option>
                                    <option value="contributor" <?php selected($user['role'], 'contributor'); ?>><?php _e('Contributor', 'google-login-only'); ?></option>
                                    <option value="subscriber" <?php selected($user['role'], 'subscriber'); ?>><?php _e('Subscriber', 'google-login-only'); ?></option>
                                </select>
                                <button type="button" class="glo-remove-user" onclick="removeUser(this)"><?php _e('Remove', 'google-login-only'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="glo-btn glo-btn-secondary" onclick="addUser()" style="margin-top: 15px;">
                    <?php _e('+ Add User', 'google-login-only'); ?>
                </button>
            </div>

            <div class="glo-wizard-actions">
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=security')); ?>" class="glo-btn glo-btn-secondary">
                    <span>←</span>
                    <?php _e('Back', 'google-login-only'); ?>
                </a>
                <button type="submit" class="glo-btn glo-btn-primary">
                    <?php _e('Save & Continue', 'google-login-only'); ?>
                    <span>→</span>
                </button>
            </div>
        </form>

        <script>
            let userIndex = <?php echo count($allowed_users); ?>;

            function addUser() {
                const userList = document.getElementById('user-list');
                const newUser = document.createElement('div');
                newUser.className = 'glo-user-item';
                newUser.innerHTML = `
                <input type="email" name="<?php echo esc_attr($this->option_name); ?>[allowed_users][${userIndex}][email]" placeholder="<?php esc_attr_e('user@example.com', 'google-login-only'); ?>" required>
                <select name="<?php echo esc_attr($this->option_name); ?>[allowed_users][${userIndex}][role]">
                    <option value="administrator"><?php _e('Administrator', 'google-login-only'); ?></option>
                    <option value="editor"><?php _e('Editor', 'google-login-only'); ?></option>
                    <option value="author"><?php _e('Author', 'google-login-only'); ?></option>
                    <option value="contributor"><?php _e('Contributor', 'google-login-only'); ?></option>
                    <option value="subscriber" selected><?php _e('Subscriber', 'google-login-only'); ?></option>
                </select>
                <button type="button" class="glo-remove-user" onclick="removeUser(this)"><?php _e('Remove', 'google-login-only'); ?></button>
            `;
                userList.appendChild(newUser);
                userIndex++;
            }

            function removeUser(button) {
                if (confirm('<?php esc_js('Are you sure you want to remove this user?', 'google-login-only'); ?>')) {
                    button.closest('.glo-user-item').remove();
                }
            }
        </script>
    <?php
    }

    private function renderOneTapStep()
    {
        $settings = get_option($this->option_name, []);

    ?>
        <div class="glo-step-header">
            <h2><?php _e('Google One Tap Configuration', 'google-login-only'); ?></h2>
            <p><?php _e('Configure Google One Tap for seamless user authentication. One Tap allows users to sign in with a single click if they\'re already logged into their Google account.', 'google-login-only'); ?></p>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields($this->plugin_name); ?>
            <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_id]" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>">
            <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>">

            <div class="glo-info-card">
                <h4><?php _e('🚀 About Google One Tap', 'google-login-only'); ?></h4>
                <p><?php _e('One Tap is always enabled on the login page for the best user experience. Here you can choose whether to enable it on public pages like your homepage.', 'google-login-only'); ?></p>
            </div>

            <div class="glo-form-group">
                <label>
                    <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[one_tap_homepage]" value="1" <?php checked(!empty($settings['one_tap_homepage'])); ?>>
                    <?php _e('Enable One Tap on Homepage and Public Pages', 'google-login-only'); ?>
                </label>
                <p style="color: #666; margin-top: 8px; font-size: 14px;">
                    <?php _e('When enabled, visitors to your homepage will see the Google One Tap prompt if they\'re signed into Google. This can improve user experience but some prefer to keep the homepage clean for other purposes.', 'google-login-only'); ?>
                </p>
            </div>

            <div class="glo-wizard-actions">
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=users')); ?>" class="glo-btn glo-btn-secondary">
                    <span>←</span>
                    <?php _e('Back', 'google-login-only'); ?>
                </a>
                <button type="submit" class="glo-btn glo-btn-primary">
                    <?php _e('Save & Continue', 'google-login-only'); ?>
                    <span>→</span>
                </button>
            </div>
        </form>
    <?php
    }

    private function renderCompleteStep()
    {
        $settings = get_option($this->option_name, []);
        $is_fully_configured = !empty($settings['client_id']) && !empty($settings['client_secret']);

    ?>
        <div class="glo-step-header">
            <h2><?php _e('🎉 Setup Complete!', 'google-login-only'); ?></h2>
            <p><?php _e('Congratulations! Your Google Login Only plugin is now configured and ready to use.', 'google-login-only'); ?></p>
        </div>

        <?php if ($is_fully_configured): ?>
            <div class="glo-info-card success">
                <h4><?php _e('✅ Plugin is Active and Ready', 'google-login-only'); ?></h4>
                <p><?php _e('Users can now sign in with their Google accounts. Password-based authentication has been disabled for enhanced security.', 'google-login-only'); ?></p>
            </div>
        <?php else: ?>
            <div class="glo-info-card error">
                <h4><?php _e('❌ Configuration Incomplete', 'google-login-only'); ?></h4>
                <p><?php _e('Some required settings are missing. Please complete the Google API configuration before users can log in.', 'google-login-only'); ?></p>
            </div>
        <?php endif; ?>

        <div class="glo-info-card">
            <h4><?php _e('🔗 Important Links', 'google-login-only'); ?></h4>
            <ul>
                <li><a href="<?php echo wp_login_url(); ?>" target="_blank"><?php _e('Test Your Login Page', 'google-login-only'); ?></a></li>
                <li><a href="<?php echo admin_url('users.php'); ?>"><?php _e('Manage WordPress Users', 'google-login-only'); ?></a></li>
                <li><a href="https://hardtoskip.com" target="_blank"><?php _e('Visit HardToSkip.com (Plugin Creator)', 'google-login-only'); ?></a></li>
            </ul>
        </div>

        <div class="glo-info-card">
            <h4><?php _e('💡 Next Steps', 'google-login-only'); ?></h4>
            <ul>
                <li><?php _e('Test the login functionality with an authorized email address', 'google-login-only'); ?></li>
                <li><?php _e('Add more authorized users as needed', 'google-login-only'); ?></li>
                <li><?php _e('Review and adjust security features based on your needs', 'google-login-only'); ?></li>
                <li><?php _e('Monitor your site for any compatibility issues', 'google-login-only'); ?></li>
            </ul>
        </div>

        <div class="glo-wizard-actions">
            <a href="<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=one_tap')); ?>" class="glo-btn glo-btn-secondary">
                <span>←</span>
                <?php _e('Back', 'google-login-only'); ?>
            </a>
            <div style="display: flex; gap: 10px;">
                <a href="<?php echo wp_login_url(); ?>" target="_blank" class="glo-btn glo-btn-success">
                    <?php _e('Test Login Page', 'google-login-only'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=overview')); ?>" class="glo-btn glo-btn-primary">
                    <?php _e('Return to Overview', 'google-login-only'); ?>
                </a>
            </div>
        </div>
<?php
    }

    private function getWizardSteps()
    {
        return [
            'overview' => [
                'number' => 1,
                'title' => __('Overview', 'google-login-only'),
                'description' => __('Getting started', 'google-login-only')
            ],
            'google_api' => [
                'number' => 2,
                'title' => __('Google API', 'google-login-only'),
                'description' => __('OAuth setup', 'google-login-only')
            ],
            'security' => [
                'number' => 3,
                'title' => __('Security', 'google-login-only'),
                'description' => __('Safety features', 'google-login-only')
            ],
            'users' => [
                'number' => 4,
                'title' => __('Users', 'google-login-only'),
                'description' => __('Manage access', 'google-login-only')
            ],
            'one_tap' => [
                'number' => 5,
                'title' => __('One Tap', 'google-login-only'),
                'description' => __('Seamless login', 'google-login-only')
            ],
            'complete' => [
                'number' => 6,
                'title' => __('Complete', 'google-login-only'),
                'description' => __('All done!', 'google-login-only')
            ]
        ];
    }

    public function sanitize($input)
    {
        $sanitized_input = [];

        if (isset($input['client_id'])) {
            $sanitized_input['client_id'] = sanitize_text_field($input['client_id']);
        }

        if (isset($input['client_secret'])) {
            $sanitized_input['client_secret'] = sanitize_text_field($input['client_secret']);
        }

        $sanitized_input['one_tap_homepage'] = isset($input['one_tap_homepage']) ? 1 : 0;

        if (isset($input['security_features']) && is_array($input['security_features'])) {
            $allowed_features = ['disable_xmlrpc', 'disable_file_editing', 'hide_wp_version', 'restrict_rest_api', 'block_sensitive_files'];
            $sanitized_input['security_features'] = [];
            foreach ($allowed_features as $feature) {
                $sanitized_input['security_features'][$feature] = isset($input['security_features'][$feature]) ? 1 : 0;
            }
        }

        if (isset($input['allowed_users']) && is_array($input['allowed_users'])) {
            $allowed_users = [];
            foreach ($input['allowed_users'] as $user) {
                if (!empty($user['email']) && is_email($user['email']) && in_array($user['role'], ['administrator', 'editor', 'author', 'contributor', 'subscriber'])) {
                    $allowed_users[] = [
                        'email' => sanitize_email($user['email']),
                        'role' => sanitize_key($user['role']),
                    ];
                }
            }
            $sanitized_input['allowed_users'] = $allowed_users;
        }

        // Update wizard progress
        $this->updateWizardProgress();

        // Redirect to next step after save
        if (!empty($_POST['submit'])) {
            $current_step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'overview';
            $next_step = $this->getNextStep($current_step);
            if ($next_step) {
                wp_redirect(admin_url('options-general.php?page=' . $this->plugin_name . '&step=' . $next_step . '&updated=1'));
                exit;
            }
        }

        return $sanitized_input;
    }

    private function updateWizardProgress()
    {
        $settings = get_option($this->option_name, []);
        $progress = get_option($this->wizard_progress_option, []);

        // Mark steps as complete based on settings
        $progress['overview'] = true; // Always complete once visited
        $progress['google_api'] = !empty($settings['client_id']) && !empty($settings['client_secret']);
        $progress['security'] = true; // Complete once visited (optional step)
        $progress['users'] = !empty($settings['allowed_users']); // Complete if users are added
        $progress['one_tap'] = true; // Complete once visited (optional step)
        $progress['complete'] = $progress['google_api'] && $progress['users'];

        update_option($this->wizard_progress_option, $progress);
    }

    private function getNextStep($current_step)
    {
        $steps = array_keys($this->getWizardSteps());
        $current_index = array_search($current_step, $steps);

        if ($current_index !== false && isset($steps[$current_index + 1])) {
            return $steps[$current_index + 1];
        }

        return null;
    }

    public function ajaxUpdateStep()
    {
        check_ajax_referer('glo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'google-login-only'));
        }

        $step = sanitize_key($_POST['step'] ?? '');
        $completed = !empty($_POST['completed']);

        $progress = get_option($this->wizard_progress_option, []);
        $progress[$step] = $completed;
        update_option($this->wizard_progress_option, $progress);

        wp_send_json_success([
            'message' => __('Step updated successfully', 'google-login-only'),
            'progress' => $progress
        ]);
    }

    public function ajaxTestConnection()
    {
        check_ajax_referer('glo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'google-login-only'));
        }

        $settings = get_option($this->option_name, []);
        $client_id = $settings['client_id'] ?? '';

        if (empty($client_id)) {
            wp_send_json_error(__('Client ID is required for testing', 'google-login-only'));
        }

        // Simple test to check if the client ID format looks valid
        if (strpos($client_id, 'googleusercontent.com') === false) {
            wp_send_json_error(__('Client ID format appears invalid', 'google-login-only'));
        }

        wp_send_json_success([
            'message' => __('Basic validation passed. Full testing requires user authentication.', 'google-login-only')
        ]);
    }
}
