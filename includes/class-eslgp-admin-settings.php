<?php

class ESLGP_AdminSettings
{
    private $plugin_name;
    private $version;
    private $option_name = 'eslgp_settings';
    private $wizard_progress_option = 'eslgp_wizard_progress';

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('admin_menu', [$this, 'addPluginPage']);
        add_action('admin_init', [$this, 'pageInit']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_ajax_eslgp_test_connection', [$this, 'ajaxTestConnection']);
    }

    public function enqueueAdminAssets($hook)
    {
        if ('toplevel_page_' . $this->plugin_name !== $hook) {
            return;
        }

        wp_enqueue_style('eslgp-admin', ESLGP_PLUGIN_URL . 'assets/css/admin.css', [], $this->version);
        wp_enqueue_script('eslgp-admin', ESLGP_PLUGIN_URL . 'assets/js/admin.js', [], $this->version, true);

        $settings = get_option($this->option_name, []);
        $allowed_users = $settings['allowed_users'] ?? [];

        $user_template = '
            <div class="eslgp-user-item">
                <input type="email" name="' . esc_attr($this->option_name) . '[allowed_users][__INDEX__][email]" placeholder="' . esc_attr__('user@example.com', 'easy-secure-login') . '" required>
                <select name="' . esc_attr($this->option_name) . '[allowed_users][__INDEX__][role]">' .
            wp_kses_post($this->getRoleOptions('subscriber')) .
            '</select>
                <button type="button" class="eslgp-remove-user"><span class="dashicons dashicons-trash"></span></button>
            </div>';

        wp_localize_script('eslgp-admin', 'eslgp_admin', [
            'nonce' => wp_create_nonce('eslgp_admin_nonce'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'initial_user_count' => count($allowed_users),
            'user_template' => $user_template,
            'strings' => [
                'saving' => __('Saving...', 'easy-secure-login'),
                'saved' => __('Settings Saved!', 'easy-secure-login'),
                'error' => __('An error occurred.', 'easy-secure-login'),
                'testing' => __('Testing...', 'easy-secure-login'),
                'connection_success' => __('Validation successful!', 'easy-secure-login'),
                'connection_failed' => __('Validation failed', 'easy-secure-login'),
                'confirm_remove_user' => __('Are you sure you want to remove this user?', 'easy-secure-login'),
                'fill_both_fields' => __('Please fill in both Client ID and Client Secret.', 'easy-secure-login'),
                'copied' => __('Copied!', 'easy-secure-login'),
                'copy_failed' => __('Failed to copy.', 'easy-secure-login'),
            ]
        ]);
    }

    public function addPluginPage()
    {
        add_menu_page(
            __('Easy Secure Login Settings', 'easy-secure-login'),
            __('Easy Secure Login', 'easy-secure-login'),
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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'overview';
        $progress = get_option($this->wizard_progress_option, []);
?>
        <div class="eslgp-admin-wrap">
            <?php $this->renderHeader(); ?>

            <div class="eslgp-content-wrapper">
                <div class="eslgp-main-content">
                    <?php $this->renderProgressBar($current_step, $progress); ?>
                    <?php $this->renderWizardContent($current_step, $progress); ?>
                </div>
                <div class="eslgp-sidebar">
                    <?php $this->renderSidebar(); ?>
                </div>
            </div>
        </div>
    <?php
    }

    private function renderHeader()
    {
    ?>
        <div class="eslgp-header">
            <div class="eslgp-header-content">
                <div class="eslgp-header-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                    </svg>
                </div>
                <div class="eslgp-header-text">
                    <h1><?php esc_html_e('Easy Secure Login', 'easy-secure-login'); ?></h1>
                    <p><?php esc_html_e('Secure Google Authentication for WordPress', 'easy-secure-login'); ?></p>
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
        <div class="eslgp-progress-container">
            <div class="eslgp-steps-nav">
                <?php foreach ($steps as $key => $step) : ?>
                    <?php
                    $is_current = ($current_step === $key);
                    $is_completed = in_array($key, $completed_steps);
                    $step_class = $is_current ? 'current' : ($is_completed ? 'completed' : 'pending');
                    ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=' . $key)); ?>" class="eslgp-step-nav <?php echo esc_attr($step_class); ?>">
                        <span class="eslgp-step-number"><?php echo esc_html($step['number']); ?></span>
                        <span class="eslgp-step-label"><?php echo esc_html($step['title']); ?></span>
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
        <div class="eslgp-sidebar-card">
            <h3><?php esc_html_e('Quick Status', 'easy-secure-login'); ?></h3>
            <div class="eslgp-status-item">
                <span class="eslgp-status-label"><?php esc_html_e('Configuration:', 'easy-secure-login'); ?></span>
                <span class="eslgp-status-value <?php echo $is_configured ? 'success' : 'warning'; ?>">
                    <?php echo $is_configured ? esc_html__('Complete', 'easy-secure-login') : esc_html__('Pending', 'easy-secure-login'); ?>
                </span>
            </div>
            <div class="eslgp-status-item">
                <span class="eslgp-status-label"><?php esc_html_e('Authorized Users:', 'easy-secure-login'); ?></span>
                <span class="eslgp-status-value"><?php echo count($settings['allowed_users'] ?? []); ?></span>
            </div>
            <div class="eslgp-status-item">
                <span class="eslgp-status-label"><?php esc_html_e('Security Features:', 'easy-secure-login'); ?></span>
                <span class="eslgp-status-value"><?php echo count(array_filter($settings['security_features'] ?? [])); ?>/5</span>
            </div>
        </div>

        <div class="eslgp-sidebar-card">
            <h3><?php esc_html_e('Quick Links', 'easy-secure-login'); ?></h3>
            <div class="eslgp-quick-links">
                <a href="<?php echo esc_url(wp_login_url()); ?>" target="_blank" class="eslgp-quick-link">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e('Test Login Page', 'easy-secure-login'); ?>
                </a>
                <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="eslgp-quick-link">
                    <span class="dashicons dashicons-cloud"></span>
                    <?php esc_html_e('Google Cloud Console', 'easy-secure-login'); ?>
                </a>
                <a href="https://hardtoskip.com/" target="_blank" class="eslgp-quick-link">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php esc_html_e('Plugin Creator', 'easy-secure-login'); ?>
                </a>
                <a href="https://linkedin.com/in/ateeqdev" target="_blank" class="eslgp-quick-link">
                    <span class="dashicons dashicons-businessperson"></span>
                    <?php esc_html_e('Hire Developer', 'easy-secure-login'); ?>
                </a>
            </div>
        </div>
    <?php
    }

    private function renderWizardContent($current_step, $progress)
    {
        switch ($current_step) {
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
            case 'overview':
            default:
                $this->renderOverviewStep($progress);
        }
    }

    private function renderOverviewStep($progress)
    {
        $settings = get_option($this->option_name, []);
        $is_configured = !empty($settings['client_id']) && !empty($settings['client_secret']);
    ?>
        <div class="eslgp-step-content">
            <div class="eslgp-step-header">
                <h2><?php esc_html_e('Welcome to Easy Secure Login', 'easy-secure-login'); ?></h2>
                <p><?php esc_html_e('Transform your WordPress authentication with secure Google Sign-In. This setup wizard will guide you through each step to ensure optimal security and user experience.', 'easy-secure-login'); ?></p>
            </div>

            <div class="eslgp-cards-grid">
                <div class="eslgp-feature-card">
                    <div class="eslgp-feature-icon security"><span class="dashicons dashicons-shield-alt"></span></div>
                    <h3><?php esc_html_e('Enhanced Security', 'easy-secure-login'); ?></h3>
                    <p><?php esc_html_e('Eliminate password-based vulnerabilities with Google\'s robust OAuth authentication system.', 'easy-secure-login'); ?></p>
                </div>
                <div class="eslgp-feature-card">
                    <div class="eslgp-feature-icon user"><span class="dashicons dashicons-admin-users"></span></div>
                    <h3><?php esc_html_e('User Management', 'easy-secure-login'); ?></h3>
                    <p><?php esc_html_e('Control exactly who can access your site with email-based authorization and role assignment.', 'easy-secure-login'); ?></p>
                </div>
                <div class="eslgp-feature-card">
                    <div class="eslgp-feature-icon experience"><span class="dashicons dashicons-thumbs-up"></span></div>
                    <h3><?php esc_html_e('Seamless Experience', 'easy-secure-login'); ?></h3>
                    <p><?php esc_html_e('Google One Tap provides instant authentication for users already signed into Google.', 'easy-secure-login'); ?></p>
                </div>
            </div>

            <?php if (!$is_configured) : ?>
                <div class="eslgp-alert warning">
                    <span class="dashicons dashicons-warning"></span>
                    <p><?php esc_html_e('Your plugin is not yet configured. Users cannot log in until you complete the Google API setup.', 'easy-secure-login'); ?></p>
                </div>
            <?php else : ?>
                <div class="eslgp-alert success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <p><?php esc_html_e('Easy Secure Login is configured and working. You can modify settings or enable additional features anytime.', 'easy-secure-login'); ?></p>
                </div>
            <?php endif; ?>

            <div class="eslgp-step-actions">
                <span></span>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=google_api')); ?>" class="eslgp-btn primary large">
                    <?php esc_html_e('Start Configuration', 'easy-secure-login'); ?>
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
        <div class="eslgp-step-content">
            <div class="eslgp-step-header">
                <h2><?php esc_html_e('Google API Configuration', 'easy-secure-login'); ?></h2>
                <p><?php esc_html_e('Set up your Google OAuth credentials to enable secure authentication. This is the most important step.', 'easy-secure-login'); ?></p>
            </div>

            <form method="post" action="options.php" id="eslgp-google-api-form" class="eslgp-form">
                <?php settings_fields($this->plugin_name); ?>
                <div class="eslgp-setup-instructions">
                    <h3><?php esc_html_e('Setup Instructions', 'easy-secure-login'); ?></h3>
                    <ol>
                        <li>
                            <?php
                            echo wp_kses_post(
                                sprintf(
                                    /* translators: %s: link to Google Cloud Console. */
                                    __('Visit the %s', 'easy-secure-login'),
                                    '<a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="eslgp-external-link">' . esc_html__('Google Cloud Console', 'easy-secure-login') . ' <span class="dashicons dashicons-external"></span></a>'
                                )
                            );
                            ?>
                        </li>
                        <li><?php esc_html_e('Create a new project or select an existing one.', 'easy-secure-login'); ?></li>
                        <li><?php esc_html_e('Navigate to "APIs & Services" → "Credentials".', 'easy-secure-login'); ?></li>
                        <li><?php esc_html_e('Click "+ CREATE CREDENTIALS" → "OAuth client ID".', 'easy-secure-login'); ?></li>
                        <li><?php esc_html_e('Choose "Web application" as the application type.', 'easy-secure-login'); ?></li>
                        <li><?php esc_html_e('Add the redirect URIs and origins provided below.', 'easy-secure-login'); ?></li>
                    </ol>
                </div>

                <div class="eslgp-form-section">
                    <h3><?php esc_html_e('Required URLs for Google Console', 'easy-secure-login'); ?></h3>
                    <div class="eslgp-url-group">
                        <label for="eslgp-uri-1"><?php esc_html_e('Authorized redirect URIs (add both)', 'easy-secure-login'); ?></label>
                        <div class="eslgp-copy-input">
                            <input id="eslgp-uri-1" type="text" readonly value="<?php echo esc_attr(home_url('?action=google_login_callback')); ?>">
                            <button type="button" class="eslgp-copy-btn"><?php esc_html_e('Copy', 'easy-secure-login'); ?></button>
                        </div>
                    </div>
                    <div class="eslgp-url-group">
                        <div class="eslgp-copy-input">
                            <input type="text" readonly value="<?php echo esc_attr(home_url('?action=google_one_tap_callback')); ?>">
                            <button type="button" class="eslgp-copy-btn"><?php esc_html_e('Copy', 'easy-secure-login'); ?></button>
                        </div>
                    </div>
                    <div class="eslgp-url-group">
                        <label for="eslgp-origin-1"><?php esc_html_e('Authorized JavaScript origins', 'easy-secure-login'); ?></label>
                        <div class="eslgp-copy-input">
                            <input id="eslgp-origin-1" type="text" readonly value="<?php echo esc_attr(home_url()); ?>">
                            <button type="button" class="eslgp-copy-btn"><?php esc_html_e('Copy', 'easy-secure-login'); ?></button>
                        </div>
                    </div>
                </div>

                <div class="eslgp-form-section">
                    <h3><?php esc_html_e('Google OAuth Credentials', 'easy-secure-login'); ?></h3>
                    <div class="eslgp-form-group">
                        <label for="client_id"><?php esc_html_e('Google Client ID', 'easy-secure-login'); ?></label>
                        <input type="text" id="client_id" name="<?php echo esc_attr($this->option_name); ?>[client_id]" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>" placeholder="<?php esc_attr_e('e.g., 123456789.apps.googleusercontent.com', 'easy-secure-login'); ?>" required>
                    </div>
                    <div class="eslgp-form-group">
                        <label for="client_secret"><?php esc_html_e('Google Client Secret', 'easy-secure-login'); ?></label>
                        <input type="password" id="client_secret" name="<?php echo esc_attr($this->option_name); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>" placeholder="<?php esc_attr_e('e.g., GOCSPX-xxxxxxxxxx', 'easy-secure-login'); ?>" required>
                    </div>
                </div>

                <div class="eslgp-step-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=overview')); ?>" class="eslgp-btn secondary"><span class="dashicons dashicons-arrow-left-alt"></span><?php esc_html_e('Back', 'easy-secure-login'); ?></a>
                    <div class="eslgp-actions-right">
                        <button type="button" id="eslgp-test-connection-btn" class="eslgp-btn outline"><?php esc_html_e('Test Connection', 'easy-secure-login'); ?></button>
                        <button type="submit" name="submit" class="eslgp-btn primary"><?php esc_html_e('Save & Continue', 'easy-secure-login'); ?><span class="dashicons dashicons-arrow-right-alt"></span></button>
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
        <div class="eslgp-step-content">
            <div class="eslgp-step-header">
                <h2><?php esc_html_e('Security Features', 'easy-secure-login'); ?></h2>
                <p><?php esc_html_e('Enable additional security measures to protect your site from common attack vectors.', 'easy-secure-login'); ?></p>
            </div>

            <form method="post" action="options.php" class="eslgp-form">
                <?php settings_fields($this->plugin_name); ?>
                <div class="eslgp-alert info">
                    <span class="dashicons dashicons-info"></span>
                    <p><strong><?php esc_html_e('Important Note:', 'easy-secure-login'); ?></strong> <?php esc_html_e('This plugin was developed in response to real brute-force attacks. These features help reduce common risks but should be part of a comprehensive security strategy.', 'easy-secure-login'); ?></p>
                </div>

                <div class="eslgp-security-grid">
                    <?php
                    $security_features = [
                        'disable_xmlrpc' => ['title' => __('Disable XML-RPC', 'easy-secure-login'), 'description' => __('Prevents brute-force attacks via XML-RPC. May affect mobile apps or Jetpack.', 'easy-secure-login'), 'icon' => 'shield-alt'],
                        'disable_file_editing' => ['title' => __('Disable File Editing', 'easy-secure-login'), 'description' => __('Prevents code injection via the admin dashboard if an account is compromised.', 'easy-secure-login'), 'icon' => 'edit'],
                        'hide_wp_version' => ['title' => __('Hide WordPress Version', 'easy-secure-login'), 'description' => __('Makes it harder for attackers to identify vulnerabilities for a specific WP version.', 'easy-secure-login'), 'icon' => 'hidden'],
                        'restrict_rest_api' => ['title' => __('Restrict REST API', 'easy-secure-login'), 'description' => __('Prevents unauthorized data access via REST API. May affect some plugins.', 'easy-secure-login'), 'icon' => 'rest-api'],
                        'block_sensitive_files' => ['title' => __('Block Sensitive Files', 'easy-secure-login'), 'description' => __('Attempts to block direct public access to wp-config.php and other sensitive files.', 'easy-secure-login'), 'icon' => 'lock']
                    ];
                    foreach ($security_features as $key => $feature) :
                        $is_enabled = !empty($security[$key]);
                    ?>
                        <div class="eslgp-security-card <?php echo $is_enabled ? 'enabled' : ''; ?>">
                            <div class="eslgp-security-header">
                                <div class="eslgp-security-icon"><span class="dashicons dashicons-<?php echo esc_attr($feature['icon']); ?>"></span></div>
                                <label class="eslgp-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[security_features][<?php echo esc_attr($key); ?>]" value="1" <?php checked($is_enabled); ?> class="eslgp-toggle-input">
                                    <span class="eslgp-toggle-slider"></span>
                                </label>
                            </div>
                            <h4><?php echo esc_html($feature['title']); ?></h4>
                            <p class="eslgp-security-description"><?php echo esc_html($feature['description']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="eslgp-step-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=google_api')); ?>" class="eslgp-btn secondary"><span class="dashicons dashicons-arrow-left-alt"></span><?php esc_html_e('Back', 'easy-secure-login'); ?></a>
                    <button type="submit" name="submit" class="eslgp-btn primary"><?php esc_html_e('Save & Continue', 'easy-secure-login'); ?><span class="dashicons dashicons-arrow-right-alt"></span></button>
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
        <div class="eslgp-step-content">
            <div class="eslgp-step-header">
                <h2><?php esc_html_e('User Management', 'easy-secure-login'); ?></h2>
                <p><?php esc_html_e('Control who can access your site. Add Google email addresses and assign roles for authorized users.', 'easy-secure-login'); ?></p>
            </div>

            <form method="post" action="options.php" class="eslgp-form">
                <?php settings_fields($this->plugin_name); ?>
                <div class="eslgp-form-section">
                    <div class="eslgp-section-header">
                        <h3><?php esc_html_e('Authorized Users', 'easy-secure-login'); ?></h3>
                        <button type="button" id="eslgp-add-user-btn" class="eslgp-btn outline small"><span class="dashicons dashicons-plus-alt"></span><?php esc_html_e('Add User', 'easy-secure-login'); ?></button>
                    </div>
                    <div class="eslgp-user-list-header">
                        <label><?php esc_html_e('Email Address', 'easy-secure-login'); ?></label>
                        <label><?php esc_html_e('Role', 'easy-secure-login'); ?></label>
                    </div>
                    <div class="eslgp-user-list" id="user-list">
                        <?php if (empty($allowed_users)) : ?>
                            <div class="eslgp-user-item">
                                <input type="email" name="<?php echo esc_attr($this->option_name); ?>[allowed_users][0][email]" placeholder="<?php esc_attr_e('user@example.com', 'easy-secure-login'); ?>" required>
                                <select name="<?php echo esc_attr($this->option_name); ?>[allowed_users][0][role]"><?php echo wp_kses_post($this->getRoleOptions('subscriber')); ?></select>
                                <button type="button" class="eslgp-remove-user"><span class="dashicons dashicons-trash"></span></button>
                            </div>
                        <?php else : ?>
                            <?php foreach ($allowed_users as $index => $user) : ?>
                                <div class="eslgp-user-item">
                                    <input type="email" name="<?php echo esc_attr($this->option_name); ?>[allowed_users][<?php echo esc_attr($index); ?>][email]" value="<?php echo esc_attr($user['email']); ?>" required>
                                    <select name="<?php echo esc_attr($this->option_name); ?>[allowed_users][<?php echo esc_attr($index); ?>][role]"><?php echo wp_kses_post($this->getRoleOptions($user['role'])); ?></select>
                                    <button type="button" class="eslgp-remove-user"><span class="dashicons dashicons-trash"></span></button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="eslgp-form-section">
                    <h3><?php esc_html_e('New User Registration', 'easy-secure-login'); ?></h3>
                    <div class="eslgp-form-group eslgp-toggle-group">
                        <label class="eslgp-toggle-label">
                            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[allow_new_signups]" value="1" <?php checked(!empty($settings['allow_new_signups'])); ?> id="eslgp-allow-signups">
                            <div class="eslgp-toggle-content">
                                <span class="eslgp-toggle-title"><?php esc_html_e('Allow New User Sign-Ups', 'easy-secure-login'); ?></span>
                                <p class="eslgp-toggle-description"><?php esc_html_e('Allow any Google user to create an account. This bypasses the authorized user list above.', 'easy-secure-login'); ?></p>
                            </div>
                        </label>
                    </div>
                    <div class="eslgp-signup-role-section" id="eslgp-signup-role-section" style="<?php echo empty($settings['allow_new_signups']) ? 'display: none;' : ''; ?>">
                        <div class="eslgp-form-group">
                            <label for="default_signup_role"><?php esc_html_e('Default Role for New Users', 'easy-secure-login'); ?></label>
                            <select id="default_signup_role" name="<?php echo esc_attr($this->option_name); ?>[default_signup_role]">
                                <?php echo wp_kses_post($this->getRoleOptions($settings['default_signup_role'] ?? 'subscriber', false)); ?>
                            </select>
                            <p class="eslgp-field-description"><?php esc_html_e('Subscriber is the safest and recommended option for new public registrations.', 'easy-secure-login'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="eslgp-step-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=security')); ?>" class="eslgp-btn secondary"><span class="dashicons dashicons-arrow-left-alt"></span><?php esc_html_e('Back', 'easy-secure-login'); ?></a>
                    <button type="submit" name="submit" class="eslgp-btn primary"><?php esc_html_e('Save & Continue', 'easy-secure-login'); ?><span class="dashicons dashicons-arrow-right-alt"></span></button>
                </div>
            </form>
        </div>
    <?php
    }

    private function renderOneTapStep()
    {
        $settings = get_option($this->option_name, []);
    ?>
        <div class="eslgp-step-content">
            <div class="eslgp-step-header">
                <h2><?php esc_html_e('Google One Tap', 'easy-secure-login'); ?></h2>
                <p><?php esc_html_e('Configure Google One Tap for a seamless, one-click user authentication experience.', 'easy-secure-login'); ?></p>
            </div>

            <form method="post" action="options.php" class="eslgp-form">
                <?php settings_fields($this->plugin_name); ?>

                <div class="eslgp-form-section">
                    <h3><?php esc_html_e('What is Google One Tap?', 'easy-secure-login'); ?></h3>
                    <p><?php esc_html_e('One Tap displays a non-intrusive prompt that allows users already signed into Google to authenticate instantly. It is automatically enabled on the WordPress login page.', 'easy-secure-login'); ?></p>
                </div>

                <div class="eslgp-form-section">
                    <div class="eslgp-form-group eslgp-toggle-group">
                        <label class="eslgp-toggle-label">
                            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[one_tap_homepage]" value="1" <?php checked(!empty($settings['one_tap_homepage'])); ?>>
                            <div class="eslgp-toggle-content">
                                <span class="eslgp-toggle-title"><?php esc_html_e('Enable One Tap on Homepage', 'easy-secure-login'); ?></span>
                                <p class="eslgp-toggle-description"><?php esc_html_e('Show the One Tap prompt to logged-out visitors on your homepage and other public pages.', 'easy-secure-login'); ?></p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="eslgp-step-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=users')); ?>" class="eslgp-btn secondary"><span class="dashicons dashicons-arrow-left-alt"></span><?php esc_html_e('Back', 'easy-secure-login'); ?></a>
                    <button type="submit" name="submit" class="eslgp-btn primary"><?php esc_html_e('Save & Continue', 'easy-secure-login'); ?><span class="dashicons dashicons-arrow-right-alt"></span></button>
                </div>
            </form>
        </div>
    <?php
    }

    private function renderCompleteStep()
    {
    ?>
        <div class="eslgp-step-content">
            <div class="eslgp-completion-header">
                <div class="eslgp-completion-icon"><span class="dashicons dashicons-yes-alt"></span></div>
                <h2><?php esc_html_e('Setup Complete!', 'easy-secure-login'); ?></h2>
                <p><?php esc_html_e('Congratulations! Your Easy Secure Login plugin is now configured and ready to secure your WordPress site.', 'easy-secure-login'); ?></p>
            </div>

            <div class="eslgp-alert success large">
                <div class="eslgp-alert-icon"><span class="dashicons dashicons-shield-alt"></span></div>
                <div class="eslgp-alert-content">
                    <h4><?php esc_html_e('Your Site is Now Secured', 'easy-secure-login'); ?></h4>
                    <p><?php esc_html_e('Password-based authentication has been disabled. Users must now authenticate through Google, significantly reducing security risks.', 'easy-secure-login'); ?></p>
                </div>
            </div>

            <div class="eslgp-action-cards">
                <a href="<?php echo esc_url(wp_login_url()); ?>" target="_blank" class="eslgp-action-card primary">
                    <div class="eslgp-action-icon"><span class="dashicons dashicons-external"></span></div>
                    <div class="eslgp-action-content">
                        <h4><?php esc_html_e('Test Login', 'easy-secure-login'); ?></h4>
                        <p><?php esc_html_e('Try the new login experience', 'easy-secure-login'); ?></p>
                    </div>
                </a>
                <a href="<?php echo esc_url(admin_url('users.php')); ?>" class="eslgp-action-card">
                    <div class="eslgp-action-icon"><span class="dashicons dashicons-admin-users"></span></div>
                    <div class="eslgp-action-content">
                        <h4><?php esc_html_e('Manage Users', 'easy-secure-login'); ?></h4>
                        <p><?php esc_html_e('View your WordPress users', 'easy-secure-login'); ?></p>
                    </div>
                </a>
            </div>

            <div class="eslgp-step-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=one_tap')); ?>" class="eslgp-btn secondary"><span class="dashicons dashicons-arrow-left-alt"></span><?php esc_html_e('Back', 'easy-secure-login'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name . '&step=overview')); ?>" class="eslgp-btn outline"><?php esc_html_e('Return to Overview', 'easy-secure-login'); ?></a>
            </div>
        </div>
<?php
    }

    private function getWizardSteps()
    {
        return [
            'overview' => ['number' => 1, 'title' => __('Overview', 'easy-secure-login')],
            'google_api' => ['number' => 2, 'title' => __('Google API', 'easy-secure-login')],
            'security' => ['number' => 3, 'title' => __('Security', 'easy-secure-login')],
            'users' => ['number' => 4, 'title' => __('Users', 'easy-secure-login')],
            'one_tap' => ['number' => 5, 'title' => __('One Tap', 'easy-secure-login')],
            'complete' => ['number' => 6, 'title' => __('Complete', 'easy-secure-login')],
        ];
    }

    public function sanitize($input)
    {
        check_admin_referer($this->plugin_name . '-options');

        $old_settings = get_option($this->option_name, []);
        $sanitized_input = $old_settings;

        if (isset($input['client_id'])) $sanitized_input['client_id'] = sanitize_text_field($input['client_id']);
        if (isset($input['client_secret'])) $sanitized_input['client_secret'] = sanitize_text_field($input['client_secret']);
        $sanitized_input['one_tap_homepage'] = isset($input['one_tap_homepage']) ? 1 : 0;
        $sanitized_input['allow_new_signups'] = isset($input['allow_new_signups']) ? 1 : 0;

        if (isset($input['default_signup_role'])) {
            $allowed_roles = array_keys(get_editable_roles());
            if (in_array($input['default_signup_role'], $allowed_roles)) {
                $sanitized_input['default_signup_role'] = sanitize_key($input['default_signup_role']);
            }
        } else {
            $sanitized_input['default_signup_role'] = 'subscriber';
        }

        if (isset($input['security_features']) && is_array($input['security_features'])) {
            $allowed_features = ['disable_xmlrpc', 'disable_file_editing', 'hide_wp_version', 'restrict_rest_api', 'block_sensitive_files'];
            foreach ($allowed_features as $feature) {
                $sanitized_input['security_features'][$feature] = isset($input['security_features'][$feature]) ? 1 : 0;
            }
        }

        if (isset($input['allowed_users']) && is_array($input['allowed_users'])) {
            $sanitized_input['allowed_users'] = array_values(array_filter(array_map(function ($user) {
                if (!empty($user['email']) && is_email($user['email'])) {
                    $allowed_roles = array_keys(get_editable_roles());
                    if (in_array($user['role'], $allowed_roles)) {
                        return ['email' => sanitize_email($user['email']), 'role' => sanitize_key($user['role'])];
                    }
                }
                return null;
            }, $input['allowed_users'])));
        }

        $this->updateWizardProgress($sanitized_input);

        if (isset($_POST['submit'])) {
            $current_step = 'overview';
            if (!empty($_POST['_wp_http_referer'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $referer_url = wp_unslash($_POST['_wp_http_referer']);
                $referer_query = wp_parse_url($referer_url, PHP_URL_QUERY);
                if ($referer_query) {
                    wp_parse_str($referer_query, $query_args);
                    if (!empty($query_args['step'])) {
                        $current_step = sanitize_key($query_args['step']);
                    }
                }
            }

            $next_step = $this->getNextStep($current_step);
            if ($next_step) {
                wp_redirect(admin_url('admin.php?page=' . $this->plugin_name . '&step=' . $next_step . '&settings-updated=true'));
                exit;
            }
        }

        return $sanitized_input;
    }

    private function updateWizardProgress($settings)
    {
        $progress = [];
        $progress['overview'] = true;
        $progress['google_api'] = !empty($settings['client_id']) && !empty($settings['client_secret']);
        $progress['security'] = true; // Always considered complete as it's optional
        $progress['users'] = true; // Always considered complete
        $progress['one_tap'] = true; // Always considered complete
        $progress['complete'] = $progress['google_api'];
        update_option($this->wizard_progress_option, $progress);
    }

    private function getNextStep($current_step)
    {
        $steps = array_keys($this->getWizardSteps());
        $current_index = array_search($current_step, $steps);
        return $current_index !== false && isset($steps[$current_index + 1]) ? $steps[$current_index + 1] : null;
    }

    public function ajaxTestConnection()
    {
        check_ajax_referer('eslgp_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('Insufficient permissions.', 'easy-secure-login'));

        $client_id = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
        $client_secret = isset($_POST['client_secret']) ? sanitize_text_field(wp_unslash($_POST['client_secret'])) : '';

        if (empty($client_id) || empty($client_secret)) wp_send_json_error(__('Please enter both Client ID and Client Secret.', 'easy-secure-login'));
        if (strpos($client_id, '.apps.googleusercontent.com') === false) wp_send_json_error(__('The Client ID does not appear to be in the correct format.', 'easy-secure-login'));
        if (strlen($client_secret) < 10) wp_send_json_error(__('The Client Secret appears to be too short.', 'easy-secure-login'));

        wp_send_json_success(['message' => __('Credentials format validation passed. Save your settings to apply them.', 'easy-secure-login')]);
    }

    private function getRoleOptions($selected_role, $include_admin = true)
    {
        $roles = get_editable_roles();

        if (!$include_admin) {
            unset($roles['administrator']);
            unset($roles['editor']);
        }

        $options_html = '';
        foreach ($roles as $key => $role) {
            $options_html .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr($key),
                selected($selected_role, $key, false),
                esc_html($role['name'])
            );
        }

        return $options_html;
    }
}
