<?php

class WPSL_AdminSettings
{
    private $plugin_name;
    private $version;
    private $option_name = 'wpsl_settings';
    private $wizard_progress_option = 'wpsl_wizard_progress';

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('admin_menu', [$this, 'addPluginPage']);
        add_action('admin_init', [$this, 'pageInit']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_ajax_wpsl_update_step', [$this, 'ajaxUpdateStep']);
        add_action('wp_ajax_wpsl_test_connection', [$this, 'ajaxTestConnection']);
    }

    public function enqueueAdminAssets($hook)
    {
        if (!in_array($hook, ['toplevel_page_' . $this->plugin_name, 'profile.php', 'user-edit.php'])) {
            return;
        }

        wp_enqueue_style('wpsl-admin', WPSL_PLUGIN_URL . 'assets/css/admin.css', [], filemtime(WPSL_PLUGIN_PATH . 'assets/css/admin.css'));

        wp_enqueue_script('wpsl-admin', WPSL_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], filemtime(WPSL_PLUGIN_PATH . 'assets/js/admin.js'), true);

        $settings = get_option($this->option_name, []);
        $allowed_users = $settings['allowed_users'] ?? [];

        $user_template = '
            <input type="email" name="' . esc_attr($this->option_name) . '[allowed_users][__INDEX__][email]" placeholder="' . esc_attr__('user@example.com', 'wp-social-login') . '" required>
            <select name="' . esc_attr($this->option_name) . '[allowed_users][__INDEX__][role]">' .
            $this->getRoleOptions('subscriber') .
            '</select>
            <button type="button" class="wpsl-remove-user">' . esc_html__('Remove', 'wp-social-login') . '</button>';

        wp_localize_script('wpsl-admin', 'wpsl_admin', [
            'nonce' => wp_create_nonce('wpsl_admin_nonce'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'initial_user_count' => count($allowed_users),
            'user_template' => $user_template,
            'strings' => [
                'saving' => __('Saving...', 'wp-social-login'),
                'saved' => __('Settings Saved!', 'wp-social-login'),
                'error' => __('Error occurred', 'wp-social-login'),
                'testing' => __('Testing...', 'wp-social-login'),
                'connection_success' => __('Connection successful!', 'wp-social-login'),
                'connection_failed' => __('Connection failed', 'wp-social-login'),
                'confirm_remove_user' => __('Are you sure you want to remove this user?', 'wp-social-login'),
                'fill_both_fields' => __('Please fill in both Client ID and Client Secret.', 'wp-social-login'),
                'copied' => __('Copied!', 'wp-social-login'),
                'copy_failed' => __('Failed to copy.', 'wp-social-login'),
            ]
        ]);
    }

    public function addPluginPage()
    {
        add_menu_page(
            __('WP Social Login', 'wp-social-login'),
            __('Social Login', 'wp-social-login'),
            'manage_options',
            $this->plugin_name,
            [$this, 'createAdminPage'],
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>'),
            30
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
        <div class="wpsl-admin-wrap">
            <?php $this->renderHeader(); ?>
            <?php $this->renderProgressBar($current_step, $progress); ?>

            <div class="wpsl-content-wrapper">
                <div class="wpsl-main-content">
                    <?php $this->renderWizardContent($current_step, $progress); ?>
                </div>
                <div class="wpsl-sidebar">
                    <?php $this->renderSidebar(); ?>
                </div>
            </div>
        </div>
    <?php
    }

    private function renderHeader()
    {
    ?>
        <div class="wpsl-header">
            <div class="wpsl-header-content">
                <div class="wpsl-header-icon">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                    </svg>
                </div>
                <div class="wpsl-header-text">
                    <h1><?php _e('WP Social Login', 'wp-social-login'); ?></h1>
                    <p><?php _e('Premium Google Authentication for WordPress', 'wp-social-login'); ?></p>
                </div>
            </div>
        </div>
    <?php
    }

    private function renderProgressBar($current_step, $progress)
    {
        $steps = $this->getWizardSteps();
        $completed_steps = array_keys(array_filter($progress));
        $total_steps = count($steps);
        $progress_percentage = (count($completed_steps) / $total_steps) * 100;

    ?>
        <div class="wpsl-progress-container">
            <div class="wpsl-progress-bar">
                <div class="wpsl-progress-fill" style="width: <?php echo esc_attr($progress_percentage); ?>%"></div>
            </div>
            <div class="wpsl-steps-nav">
                <?php foreach ($steps as $key => $step): ?>
                    <?php
                    $is_current = ($current_step === $key);
                    $is_completed = in_array($key, $completed_steps);
                    $step_class = $is_current ? 'current' : ($is_completed ? 'completed' : 'pending');
                    ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=' . $key)); ?>"
                        class="wpsl-step-nav <?php echo esc_attr($step_class); ?>">
                        <span class="wpsl-step-number"><?php echo esc_html($step['number']); ?></span>
                        <span class="wpsl-step-label"><?php echo esc_html($step['title']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php
    }

    private function renderSidebar()
    {
        $settings = get_option($this->option_name, []);
        $is_configured = !empty($settings['client_id']) && !empty($settings['client_secret']);
    ?>
        <div class="wpsl-sidebar-card">
            <h3><?php _e('Quick Status', 'wp-social-login'); ?></h3>
            <div class="wpsl-status-item">
                <span class="wpsl-status-label"><?php _e('Configuration:', 'wp-social-login'); ?></span>
                <span class="wpsl-status-value <?php echo $is_configured ? 'success' : 'warning'; ?>">
                    <?php echo $is_configured ? __('Complete', 'wp-social-login') : __('Pending', 'wp-social-login'); ?>
                </span>
            </div>
            <div class="wpsl-status-item">
                <span class="wpsl-status-label"><?php _e('Authorized Users:', 'wp-social-login'); ?></span>
                <span class="wpsl-status-value"><?php echo count($settings['allowed_users'] ?? []); ?></span>
            </div>
            <div class="wpsl-status-item">
                <span class="wpsl-status-label"><?php _e('Security Features:', 'wp-social-login'); ?></span>
                <span class="wpsl-status-value"><?php echo count(array_filter($settings['security_features'] ?? [])); ?>/5</span>
            </div>
        </div>

        <div class="wpsl-sidebar-card">
            <h3><?php _e('Quick Links', 'wp-social-login'); ?></h3>
            <div class="wpsl-quick-links">
                <a href="<?php echo wp_login_url(); ?>" target="_blank" class="wpsl-quick-link">
                    <span class="dashicons dashicons-external"></span>
                    <?php _e('Test Login Page', 'wp-social-login'); ?>
                </a>
                <a href="https://console.cloud.google.com/" target="_blank" class="wpsl-quick-link">
                    <span class="dashicons dashicons-cloud"></span>
                    <?php _e('Google Console', 'wp-social-login'); ?>
                </a>
                <a href="https://hardtoskip.com/" target="_blank" class="wpsl-quick-link">
                    <span class="dashicons dashicons-heart"></span>
                    <?php _e('Plugin Creator', 'wp-social-login'); ?>
                </a>
            </div>
        </div>
    <?php
    }

    private function renderWizardContent($current_step, $progress)
    {
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
    }

    private function renderOverviewStep($progress)
    {
        $settings = get_option($this->option_name, []);
        $is_configured = !empty($settings['client_id']) && !empty($settings['client_secret']);

    ?>
        <div class="wpsl-step-content">
            <div class="wpsl-step-header">
                <h2><?php _e('Welcome to WP Social Login', 'wp-social-login'); ?></h2>
                <p><?php _e('Transform your WordPress authentication with secure Google Sign-In. This setup wizard will guide you through each step to ensure optimal security and user experience.', 'wp-social-login'); ?></p>
            </div>

            <div class="wpsl-cards-grid">
                <div class="wpsl-feature-card">
                    <div class="wpsl-feature-icon security">
                        <span class="dashicons dashicons-shield-alt"></span>
                    </div>
                    <h3><?php _e('Enhanced Security', 'wp-social-login'); ?></h3>
                    <p><?php _e('Eliminate password-based vulnerabilities with Google\'s robust OAuth authentication system.', 'wp-social-login'); ?></p>
                </div>

                <div class="wpsl-feature-card">
                    <div class="wpsl-feature-icon user">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <h3><?php _e('User Management', 'wp-social-login'); ?></h3>
                    <p><?php _e('Control exactly who can access your site with email-based authorization and role assignment.', 'wp-social-login'); ?></p>
                </div>

                <div class="wpsl-feature-card">
                    <div class="wpsl-feature-icon experience">
                        <span class="dashicons dashicons-thumbs-up"></span>
                    </div>
                    <h3><?php _e('Seamless Experience', 'wp-social-login'); ?></h3>
                    <p><?php _e('Google One Tap provides instant authentication for users already signed into Google.', 'wp-social-login'); ?></p>
                </div>
            </div>

            <?php if (!$is_configured): ?>
                <div class="wpsl-alert warning">
                    <div class="wpsl-alert-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="wpsl-alert-content">
                        <h4><?php _e('Setup Required', 'wp-social-login'); ?></h4>
                        <p><?php _e('Your plugin is not yet configured. Users cannot log in until you complete the Google API setup.', 'wp-social-login'); ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="wpsl-alert success">
                    <div class="wpsl-alert-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="wpsl-alert-content">
                        <h4><?php _e('Plugin is Active', 'wp-social-login'); ?></h4>
                        <p><?php _e('WP Social Login is configured and working. You can still modify settings or enable additional features.', 'wp-social-login'); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="wpsl-step-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=google_api')); ?>" class="wpsl-btn primary large">
                    <?php _e('Start Configuration', 'wp-social-login'); ?>
                    <span class="dashicons dashicons-arrow-right-alt"></span>
                </a>
            </div>
        </div>
    <?php
    }

    private function renderGoogleApiStep()
    {
        $settings = get_option($this->option_name, []);

    ?>
        <div class="wpsl-step-content">
            <div class="wpsl-step-header">
                <h2><?php _e('Google API Configuration', 'wp-social-login'); ?></h2>
                <p><?php _e('Set up your Google OAuth credentials to enable secure authentication.', 'wp-social-login'); ?></p>
            </div>

            <form method="post" action="options.php" id="wpsl-google-api-form" class="wpsl-form">
                <?php settings_fields($this->plugin_name); ?>

                <div class="wpsl-setup-instructions">
                    <h3><?php _e('Setup Instructions', 'wp-social-login'); ?></h3>
                    <ol>
                        <li><?php printf(__('Visit the %s', 'wp-social-login'), '<a href="https://console.cloud.google.com/" target="_blank" class="wpsl-external-link">' . __('Google Cloud Console', 'wp-social-login') . ' <span class="dashicons dashicons-external"></span></a>'); ?></li>
                        <li><?php _e('Create a new project or select an existing one', 'wp-social-login'); ?></li>
                        <li><?php _e('Navigate to "APIs & Services" → "Credentials"', 'wp-social-login'); ?></li>
                        <li><?php _e('Click "Create Credentials" → "OAuth client ID"', 'wp-social-login'); ?></li>
                        <li><?php _e('Choose "Web application" as the application type', 'wp-social-login'); ?></li>
                        <li><?php _e('Add the redirect URIs and origins below', 'wp-social-login'); ?></li>
                    </ol>
                </div>

                <div class="wpsl-form-section">
                    <h3><?php _e('Required URLs for Google Console', 'wp-social-login'); ?></h3>

                    <div class="wpsl-url-group">
                        <label><?php _e('Authorized Redirect URIs', 'wp-social-login'); ?></label>
                        <div class="wpsl-copy-field">
                            <input type="text" readonly value="<?php echo esc_attr(home_url('?action=google_login_callback')); ?>">
                            <button type="button" class="wpsl-copy-btn"><?php _e('Copy', 'wp-social-login'); ?></button>
                        </div>
                        <div class="wpsl-copy-field">
                            <input type="text" readonly value="<?php echo esc_attr(home_url('?action=google_one_tap_callback')); ?>">
                            <button type="button" class="wpsl-copy-btn"><?php _e('Copy', 'wp-social-login'); ?></button>
                        </div>
                    </div>

                    <div class="wpsl-url-group">
                        <label><?php _e('Authorized JavaScript Origins', 'wp-social-login'); ?></label>
                        <div class="wpsl-copy-field">
                            <input type="text" readonly value="<?php echo esc_attr(home_url()); ?>">
                            <button type="button" class="wpsl-copy-btn"><?php _e('Copy', 'wp-social-login'); ?></button>
                        </div>
                    </div>
                </div>

                <div class="wpsl-form-section">
                    <h3><?php _e('Google OAuth Credentials', 'wp-social-login'); ?></h3>

                    <div class="wpsl-form-group">
                        <label for="client_id"><?php _e('Google Client ID', 'wp-social-login'); ?></label>
                        <input type="text" id="client_id" name="<?php echo esc_attr($this->option_name); ?>[client_id]"
                            value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>"
                            placeholder="<?php esc_attr_e('123456789.apps.googleusercontent.com', 'wp-social-login'); ?>" required>
                    </div>

                    <div class="wpsl-form-group">
                        <label for="client_secret"><?php _e('Google Client Secret', 'wp-social-login'); ?></label>
                        <input type="password" id="client_secret" name="<?php echo esc_attr($this->option_name); ?>[client_secret]"
                            value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>"
                            placeholder="<?php esc_attr_e('GOCSPX-...', 'wp-social-login'); ?>" required>
                    </div>
                </div>

                <div class="wpsl-step-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=overview')); ?>" class="wpsl-btn secondary">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <?php _e('Back', 'wp-social-login'); ?>
                    </a>
                    <div class="wpsl-actions-right">
                        <button type="button" id="wpsl-test-connection-btn" class="wpsl-btn outline"><?php _e('Test Connection', 'wp-social-login'); ?></button>
                        <button type="submit" class="wpsl-btn primary">
                            <?php _e('Save & Continue', 'wp-social-login'); ?>
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    <?php
    }

    private function renderSecurityStep()
    {
        $settings = get_option($this->option_name, []);
        $security = $settings['security_features'] ?? [];

    ?>
        <div class="wpsl-step-content">
            <div class="wpsl-step-header">
                <h2><?php _e('Security Features', 'wp-social-login'); ?></h2>
                <p><?php _e('Enable additional security measures to protect your site from common attack vectors.', 'wp-social-login'); ?></p>
            </div>

            <form method="post" action="options.php" class="wpsl-form">
                <?php settings_fields($this->plugin_name); ?>
                <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_id]" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>">
                <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>">

                <div class="wpsl-alert info">
                    <div class="wpsl-alert-icon">
                        <span class="dashicons dashicons-info"></span>
                    </div>
                    <div class="wpsl-alert-content">
                        <h4><?php _e('Security Notice', 'wp-social-login'); ?></h4>
                        <p><?php _e('This plugin was developed in response to real brute-force attacks. These features help reduce common risks but should be part of a comprehensive security strategy.', 'wp-social-login'); ?></p>
                    </div>
                </div>

                <div class="wpsl-security-grid">
                    <?php
                    $security_features = [
                        'disable_xmlrpc' => [
                            'title' => __('Disable XML-RPC', 'wp-social-login'),
                            'description' => __('Prevents brute-force attacks via XML-RPC protocol', 'wp-social-login'),
                            'impact' => __('May affect mobile apps, Jetpack features, some backup plugins', 'wp-social-login'),
                            'icon' => 'shield-alt'
                        ],
                        'disable_file_editing' => [
                            'title' => __('Disable File Editing', 'wp-social-login'),
                            'description' => __('Prevents code injection if admin account is compromised', 'wp-social-login'),
                            'impact' => __('Disables theme/plugin editors in admin dashboard', 'wp-social-login'),
                            'icon' => 'edit'
                        ],
                        'hide_wp_version' => [
                            'title' => __('Hide WordPress Version', 'wp-social-login'),
                            'description' => __('Makes it harder for attackers to identify vulnerabilities', 'wp-social-login'),
                            'impact' => __('Generally safe, no functionality impact', 'wp-social-login'),
                            'icon' => 'hidden'
                        ],
                        'restrict_rest_api' => [
                            'title' => __('Restrict REST API', 'wp-social-login'),
                            'description' => __('Prevents unauthorized data access via REST API', 'wp-social-login'),
                            'impact' => __('May affect public API access, some plugins, headless setups', 'wp-social-login'),
                            'icon' => 'rest-api'
                        ],
                        'block_sensitive_files' => [
                            'title' => __('Block Sensitive Files', 'wp-social-login'),
                            'description' => __('Prevents direct access to wp-config.php, .htaccess, etc.', 'wp-social-login'),
                            'impact' => __('Generally safe, blocks direct file access attempts', 'wp-social-login'),
                            'icon' => 'lock'
                        ]
                    ];

                    foreach ($security_features as $key => $feature):
                        $is_enabled = !empty($security[$key]);
                    ?>
                        <div class="wpsl-security-card <?php echo $is_enabled ? 'enabled' : ''; ?>">
                            <div class="wpsl-security-header">
                                <div class="wpsl-security-icon">
                                    <span class="dashicons dashicons-<?php echo esc_attr($feature['icon']); ?>"></span>
                                </div>
                                <label class="wpsl-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[security_features][<?php echo esc_attr($key); ?>]"
                                        value="1" <?php checked($is_enabled); ?> class="wpsl-toggle-input">
                                    <span class="wpsl-toggle-slider"></span>
                                </label>
                            </div>
                            <h4><?php echo esc_html($feature['title']); ?></h4>
                            <p class="wpsl-security-description"><?php echo esc_html($feature['description']); ?></p>
                            <div class="wpsl-security-impact">
                                <strong><?php _e('Impact:', 'wp-social-login'); ?></strong> <?php echo esc_html($feature['impact']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="wpsl-step-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=google_api')); ?>" class="wpsl-btn secondary">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <?php _e('Back', 'wp-social-login'); ?>
                    </a>
                    <button type="submit" class="wpsl-btn primary">
                        <?php _e('Save & Continue', 'wp-social-login'); ?>
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                    </button>
                </div>
            </form>
        </div>
    <?php
    }

    private function renderUsersStep()
    {
        $settings = get_option($this->option_name, []);
        $allowed_users = $settings['allowed_users'] ?? [];

    ?>
        <div class="wpsl-step-content">
            <div class="wpsl-step-header">
                <h2><?php _e('User Management', 'wp-social-login'); ?></h2>
                <p><?php _e('Control who can access your site by adding their Google email addresses.', 'wp-social-login'); ?></p>
            </div>

            <form method="post" action="options.php" class="wpsl-form">
                <?php settings_fields($this->plugin_name); ?>
                <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_id]" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>">
                <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>">

                <div class="wpsl-form-section">
                    <div class="wpsl-section-header">
                        <h3><?php _e('Authorized Users', 'wp-social-login'); ?></h3>
                        <button type="button" id="wpsl-add-user-btn" class="wpsl-btn outline small">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php _e('Add User', 'wp-social-login'); ?>
                        </button>
                    </div>

                    <div class="wpsl-user-list" id="user-list">
                        <?php if (empty($allowed_users)): ?>
                            <div class="wpsl-user-item">
                                <input type="email" name="<?php echo esc_attr($this->option_name); ?>[allowed_users][0][email]"
                                    placeholder="<?php esc_attr_e('user@example.com', 'wp-social-login'); ?>" required>
                                <select name="<?php echo esc_attr($this->option_name); ?>[allowed_users][0][role]">
                                    <?php echo $this->getRoleOptions('subscriber'); ?>
                                </select>
                                <button type="button" class="wpsl-remove-user">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($allowed_users as $index => $user): ?>
                                <div class="wpsl-user-item">
                                    <input type="email" name="<?php echo esc_attr($this->option_name); ?>[allowed_users][<?php echo esc_attr($index); ?>][email]"
                                        value="<?php echo esc_attr($user['email']); ?>" required>
                                    <select name="<?php echo esc_attr($this->option_name); ?>[allowed_users][<?php echo esc_attr($index); ?>][role]">
                                        <?php echo $this->getRoleOptions($user['role']); ?>
                                    </select>
                                    <button type="button" class="wpsl-remove-user">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="wpsl-form-section">
                    <h3><?php _e('New User Registration', 'wp-social-login'); ?></h3>

                    <div class="wpsl-form-group">
                        <label class="wpsl-checkbox-label">
                            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[allow_new_signups]"
                                value="1" <?php checked(!empty($settings['allow_new_signups'])); ?> id="wpsl-allow-signups">
                            <span class="wpsl-checkmark"></span>
                            <?php _e('Allow New User Sign-Ups', 'wp-social-login'); ?>
                        </label>
                        <p class="wpsl-field-description"><?php _e('Allow unknown users to create accounts automatically. This bypasses the authorized users list above.', 'wp-social-login'); ?></p>
                    </div>

                    <div class="wpsl-conditional-section" id="wpsl-signup-role-section" style="<?php echo empty($settings['allow_new_signups']) ? 'display: none;' : ''; ?>">
                        <div class="wpsl-form-group">
                            <label for="default_signup_role"><?php _e('Default Role for New Users', 'wp-social-login'); ?></label>
                            <select id="default_signup_role" name="<?php echo esc_attr($this->option_name); ?>[default_signup_role]">
                                <option value="subscriber" <?php selected($settings['default_signup_role'] ?? 'subscriber', 'subscriber'); ?>><?php _e('Subscriber (Recommended)', 'wp-social-login'); ?></option>
                                <option value="contributor" <?php selected($settings['default_signup_role'] ?? 'subscriber', 'contributor'); ?>><?php _e('Contributor', 'wp-social-login'); ?></option>
                                <option value="author" <?php selected($settings['default_signup_role'] ?? 'subscriber', 'author'); ?>><?php _e('Author', 'wp-social-login'); ?></option>
                            </select>
                            <p class="wpsl-field-description"><?php _e('Subscriber is the safest option for new users.', 'wp-social-login'); ?></p>
                        </div>

                        <div class="wpsl-alert warning" id="wpsl-signup-warning">
                            <div class="wpsl-alert-content">
                                <strong><?php _e('Security Warning:', 'wp-social-login'); ?></strong>
                                <?php _e('Open registration increases security risks. Only enable if you need public access.', 'wp-social-login'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wpsl-step-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=security')); ?>" class="wpsl-btn secondary">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <?php _e('Back', 'wp-social-login'); ?>
                    </a>
                    <button type="submit" class="wpsl-btn primary">
                        <?php _e('Save & Continue', 'wp-social-login'); ?>
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                    </button>
                </div>
            </form>
        </div>
    <?php
    }

    private function renderOneTapStep()
    {
        $settings = get_option($this->option_name, []);

    ?>
        <div class="wpsl-step-content">
            <div class="wpsl-step-header">
                <h2><?php _e('Google One Tap Configuration', 'wp-social-login'); ?></h2>
                <p><?php _e('Configure Google One Tap for seamless user authentication.', 'wp-social-login'); ?></p>
            </div>

            <form method="post" action="options.php" class="wpsl-form">
                <?php settings_fields($this->plugin_name); ?>
                <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_id]" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>">
                <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>">

                <div class="wpsl-form-section">
                    <h3><?php _e('What is Google One Tap?', 'wp-social-login'); ?></h3>
                    <p><?php _e('One Tap allows users already signed into Google to authenticate instantly. It\'s automatically enabled on login pages for the best experience.', 'wp-social-login'); ?></p>
                </div>

                <div class="wpsl-form-section">
                    <div class="wpsl-form-group">
                        <label class="wpsl-checkbox-label">
                            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[one_tap_homepage]"
                                value="1" <?php checked(!empty($settings['one_tap_homepage'])); ?>>
                            <span class="wpsl-checkmark"></span>
                            <?php _e('Enable One Tap on Homepage', 'wp-social-login'); ?>
                        </label>
                        <p class="wpsl-field-description"><?php _e('Show One Tap prompt to visitors on your homepage and public pages.', 'wp-social-login'); ?></p>
                    </div>
                </div>

                <div class="wpsl-step-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=users')); ?>" class="wpsl-btn secondary">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <?php _e('Back', 'wp-social-login'); ?>
                    </a>
                    <button type="submit" class="wpsl-btn primary">
                        <?php _e('Save & Continue', 'wp-social-login'); ?>
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                    </button>
                </div>
            </form>
        </div>
    <?php
    }

    private function renderCompleteStep()
    {
        $settings = get_option($this->option_name, []);
        $is_fully_configured = !empty($settings['client_id']) && !empty($settings['client_secret']);

    ?>
        <div class="wpsl-step-content">
            <div class="wpsl-completion-header">
                <div class="wpsl-completion-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <h2><?php _e('Setup Complete!', 'wp-social-login'); ?></h2>
                <p><?php _e('Congratulations! Your WP Social Login plugin is now configured and ready to secure your WordPress site.', 'wp-social-login'); ?></p>
            </div>

            <?php if ($is_fully_configured): ?>
                <div class="wpsl-alert success large">
                    <div class="wpsl-alert-icon">
                        <span class="dashicons dashicons-shield-alt"></span>
                    </div>
                    <div class="wpsl-alert-content">
                        <h4><?php _e('Your Site is Now Secured', 'wp-social-login'); ?></h4>
                        <p><?php _e('Password-based authentication has been disabled. All users must now authenticate through Google, significantly reducing security risks.', 'wp-social-login'); ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="wpsl-alert error large">
                    <div class="wpsl-alert-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="wpsl-alert-content">
                        <h4><?php _e('Configuration Incomplete', 'wp-social-login'); ?></h4>
                        <p><?php _e('Some required settings are missing. Please complete the Google API configuration before users can log in.', 'wp-social-login'); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="wpsl-completion-actions">
                <div class="wpsl-action-cards">
                    <a href="<?php echo wp_login_url(); ?>" target="_blank" class="wpsl-action-card primary">
                        <div class="wpsl-action-icon">
                            <span class="dashicons dashicons-external"></span>
                        </div>
                        <div class="wpsl-action-content">
                            <h4><?php _e('Test Login', 'wp-social-login'); ?></h4>
                            <p><?php _e('Try the new login experience', 'wp-social-login'); ?></p>
                        </div>
                    </a>

                    <a href="<?php echo admin_url('users.php'); ?>" class="wpsl-action-card">
                        <div class="wpsl-action-icon">
                            <span class="dashicons dashicons-admin-users"></span>
                        </div>
                        <div class="wpsl-action-content">
                            <h4><?php _e('Manage Users', 'wp-social-login'); ?></h4>
                            <p><?php _e('View your WordPress users', 'wp-social-login'); ?></p>
                        </div>
                    </a>

                    <a href="https://hardtoskip.com/" target="_blank" class="wpsl-action-card">
                        <div class="wpsl-action-icon">
                            <span class="dashicons dashicons-heart"></span>
                        </div>
                        <div class="wpsl-action-content">
                            <h4><?php _e('Plugin Creator', 'wp-social-login'); ?></h4>
                            <p><?php _e('Visit HardToSkip.com', 'wp-social-login'); ?></p>
                        </div>
                    </a>
                </div>
            </div>

            <div class="wpsl-next-steps">
                <h3><?php _e('Next Steps', 'wp-social-login'); ?></h3>
                <ul class="wpsl-checklist">
                    <li>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Test the login functionality with an authorized email address', 'wp-social-login'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Add more authorized users as your team grows', 'wp-social-login'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Monitor your site for any compatibility issues', 'wp-social-login'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Keep your WordPress installation updated for optimal security', 'wp-social-login'); ?>
                    </li>
                </ul>
            </div>

            <div class="wpsl-step-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=one_tap')); ?>" class="wpsl-btn secondary">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <?php _e('Back', 'wp-social-login'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=overview')); ?>" class="wpsl-btn outline">
                    <?php _e('Return to Overview', 'wp-social-login'); ?>
                </a>
            </div>
        </div>
<?php
    }

    // Keep the rest of the methods unchanged but update menu references
    private function getWizardSteps()
    {
        return [
            'overview' => [
                'number' => 1,
                'title' => __('Overview', 'wp-social-login'),
                'description' => __('Getting started', 'wp-social-login')
            ],
            'google_api' => [
                'number' => 2,
                'title' => __('Google API', 'wp-social-login'),
                'description' => __('OAuth setup', 'wp-social-login')
            ],
            'security' => [
                'number' => 3,
                'title' => __('Security', 'wp-social-login'),
                'description' => __('Safety features', 'wp-social-login')
            ],
            'users' => [
                'number' => 4,
                'title' => __('Users', 'wp-social-login'),
                'description' => __('Manage access', 'wp-social-login')
            ],
            'one_tap' => [
                'number' => 5,
                'title' => __('One Tap', 'wp-social-login'),
                'description' => __('Seamless login', 'wp-social-login')
            ],
            'complete' => [
                'number' => 6,
                'title' => __('Complete', 'wp-social-login'),
                'description' => __('All done!', 'wp-social-login')
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
        $sanitized_input['allow_new_signups'] = isset($input['allow_new_signups']) ? 1 : 0;

        if (isset($input['default_signup_role']) && in_array($input['default_signup_role'], ['subscriber', 'contributor', 'author'])) {
            $sanitized_input['default_signup_role'] = sanitize_key($input['default_signup_role']);
        } else {
            $sanitized_input['default_signup_role'] = 'subscriber';
        }

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
                wp_redirect(admin_url('admin.php?page=' . $this->plugin_name . '&step=' . $next_step . '&updated=1'));
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
        check_ajax_referer('wpsl_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-social-login'));
        }

        $step = sanitize_key($_POST['step'] ?? '');
        $completed = !empty($_POST['completed']);

        $progress = get_option($this->wizard_progress_option, []);
        $progress[$step] = $completed;
        update_option($this->wizard_progress_option, $progress);

        wp_send_json_success([
            'message' => __('Step updated successfully', 'wp-social-login'),
            'progress' => $progress
        ]);
    }

    public function ajaxTestConnection()
    {
        check_ajax_referer('wpsl_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-social-login'));
        }

        $client_id = isset($_POST['client_id']) ? sanitize_text_field($_POST['client_id']) : '';
        $client_secret = isset($_POST['client_secret']) ? sanitize_text_field($_POST['client_secret']) : '';

        if (empty($client_id) || empty($client_secret)) {
            wp_send_json_error(__('Please enter both Client ID and Client Secret before testing.', 'wp-social-login'));
        }

        // Simple validation checks on the format
        if (strpos($client_id, '.apps.googleusercontent.com') === false) {
            wp_send_json_error(__('The Client ID does not appear to be in the correct format.', 'wp-social-login'));
        }

        if (strlen($client_secret) < 10) { // Google secrets are typically much longer
            wp_send_json_error(__('The Client Secret appears to be too short.', 'wp-social-login'));
        }

        wp_send_json_success([
            'message' => __('Credentials format validation passed. Save your settings to apply them.', 'wp-social-login')
        ]);
    }

    /**
     * Generates HTML options for a user role select dropdown.
     *
     * @param string $selected_role The role that should be pre-selected.
     * @return string The generated HTML <option> tags.
     */
    private function getRoleOptions($selected_role = 'subscriber')
    {
        $roles = [
            'administrator' => __('Administrator', 'wp-social-login'),
            'editor'        => __('Editor', 'wp-social-login'),
            'author'        => __('Author', 'wp-social-login'),
            'contributor'   => __('Contributor', 'wp-social-login'),
            'subscriber'    => __('Subscriber', 'wp-social-login'),
        ];

        $options_html = '';
        foreach ($roles as $value => $label) {
            $options_html .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($selected_role, $value, false),
                esc_html($label)
            );
        }
        return $options_html;
    }
}
