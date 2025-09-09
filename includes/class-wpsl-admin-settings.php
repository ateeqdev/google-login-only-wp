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
        if (!in_array($hook, ['settings_page_' . $this->plugin_name, 'profile.php', 'user-edit.php'])) {
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
        add_options_page(
            __('WP Social Login Settings', 'wp-social-login'),
            __('WP Social Login', 'wp-social-login'),
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
        <div class="wrap wpsl-wizard-container">
            <?php $this->renderHeroSection(); ?>
            <?php $this->renderWizardNavigation($current_step, $progress); ?>
            <?php $this->renderWizardContent($current_step, $progress); ?>
        </div>
    <?php
    }

    private function renderHeroSection()
    {
    ?>
        <div class="wpsl-hero-section">
            <div class="wpsl-hero-content">
                <h1 class="wpsl-hero-title"><?php _e('WP Social Login', 'wp-social-login'); ?></h1>
                <p class="wpsl-hero-subtitle"><?php _e('Secure, beautiful, and user-friendly social authentication for WordPress', 'wp-social-login'); ?></p>
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
        <div class="wpsl-wizard-nav">
            <div class="wpsl-progress-bar">
                <div class="wpsl-progress-fill" style="width: <?php echo esc_attr($progress_percentage); ?>%"></div>
            </div>
            <div class="wpsl-steps">
                <?php foreach ($steps as $key => $step): ?>
                    <?php
                    $is_current = ($current_step === $key);
                    $is_completed = in_array($key, $completed_steps);
                    $step_class = $is_current ? 'active' : ($is_completed ? 'completed' : 'pending');
                    ?>
                    <div class="wpsl-step <?php echo esc_attr($step_class); ?>" onclick="window.location.href='<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=' . $key)); ?>'">
                        <div class="wpsl-step-icon">
                            <?php if ($is_completed): ?>
                                <span class="wpsl-checkmark"></span>
                            <?php else: ?>
                                <?php echo esc_html($step['number']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="wpsl-step-title"><?php echo esc_html($step['title']); ?></div>
                        <div class="wpsl-step-description"><?php echo esc_html($step['description']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php
    }

    private function renderWizardContent($current_step, $progress)
    {
    ?>
        <div class="wpsl-wizard-content">
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
        <div class="wpsl-step-header">
            <h2><?php _e('Welcome to WP Social Login', 'wp-social-login'); ?></h2>
            <p><?php _e('This setup wizard will guide you through configuring secure Google authentication for your WordPress site. Each step builds upon the previous one to ensure a complete and secure setup.', 'wp-social-login'); ?></p>
        </div>

        <?php if (!$is_configured): ?>
            <div class="wpsl-info-card warning">
                <h4><?php _e('⚠️ Setup Required', 'wp-social-login'); ?></h4>
                <p><?php _e('Your plugin is not yet configured. Users cannot log in until you complete the Google API setup.', 'wp-social-login'); ?></p>
            </div>
        <?php else: ?>
            <div class="wpsl-info-card success">
                <h4><?php _e('✅ Plugin is Active', 'wp-social-login'); ?></h4>
                <p><?php _e('WP Social Login is configured and working. You can still modify settings or enable additional features.', 'wp-social-login'); ?></p>
            </div>
        <?php endif; ?>

        <div class="wpsl-info-card">
            <h4><?php _e('🎯 About This Plugin', 'wp-social-login'); ?></h4>
            <p><?php printf(
                    __('This plugin was created by %1$s after a successful brute-force attack demonstrated the vulnerabilities of password-based authentication. By enforcing Google OAuth, we eliminate password-related security risks while providing a superior user experience.', 'wp-social-login'),
                    '<a href="https://hardtoskip.com" target="_blank"><strong>HardToSkip.com</strong></a>'
                ); ?></p>
        </div>

        <div class="wpsl-wizard-actions">
            <div></div>
            <a href="<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=google_api')); ?>" class="wpsl-btn wpsl-btn-primary">
                <?php _e('Start Setup', 'wp-social-login'); ?>
                <span>→</span>
            </a>
        </div>
    <?php
    }

    private function renderGoogleApiStep()
    {
        $settings = get_option($this->option_name, []);

    ?>
        <div class="wpsl-step-header">
            <h2><?php _e('Google API Configuration', 'wp-social-login'); ?></h2>
            <p><?php _e('Set up your Google OAuth credentials to enable secure authentication.', 'wp-social-login'); ?></p>
        </div>

        <form method="post" action="options.php" id="wpsl-google-api-form">
            <?php settings_fields($this->plugin_name); ?>

            <div class="wpsl-info-card">
                <h4><?php _e('Step-by-Step Instructions', 'wp-social-login'); ?></h4>
                <ol>
                    <li><?php printf(__('Go to the %s', 'wp-social-login'), '<a href="https://console.cloud.google.com/" target="_blank">' . __('Google Cloud Console', 'wp-social-login') . '</a>'); ?></li>
                    <li><?php _e('Create a new project or select an existing one', 'wp-social-login'); ?></li>
                    <li><?php _e('Navigate to "APIs & Services" → "Credentials"', 'wp-social-login'); ?></li>
                    <li><?php _e('Click "Create Credentials" → "OAuth client ID"', 'wp-social-login'); ?></li>
                    <li><?php _e('Choose "Web application" as the application type', 'wp-social-login'); ?></li>
                    <li><?php _e('Add the redirect URIs below to your OAuth client', 'wp-social-login'); ?></li>
                </ol>
            </div>

            <div class="wpsl-form-group">
                <label><?php _e('Authorized Redirect URIs (copy these exactly)', 'wp-social-login'); ?></label>
                <div class="wpsl-copy-field">
                    <input type="text" readonly value="<?php echo esc_attr(home_url('?action=google_login_callback')); ?>">
                    <button type="button" class="wpsl-copy-btn"><?php _e('Copy', 'wp-social-login'); ?></button>
                    <input type="text" readonly value="<?php echo esc_attr(home_url('?action=google_one_tap_callback')); ?>">
                    <button type="button" class="wpsl-copy-btn"><?php _e('Copy', 'wp-social-login'); ?></button>
                </div>
            </div>

            <div class="wpsl-form-group">
                <label><?php _e('Authorized JavaScript Origins', 'wp-social-login'); ?></label>
                <div class="wpsl-copy-field">
                    <input type="text" readonly value="<?php echo esc_attr(home_url()); ?>">
                    <button type="button" class="wpsl-copy-btn"><?php _e('Copy', 'wp-social-login'); ?></button>
                </div>
            </div>

            <div class="wpsl-form-group">
                <label for="client_id"><?php _e('Google Client ID', 'wp-social-login'); ?></label>
                <input type="text" id="client_id" name="<?php echo esc_attr($this->option_name); ?>[client_id]" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>" required>
            </div>

            <div class="wpsl-form-group">
                <label for="client_secret"><?php _e('Google Client Secret', 'wp-social-login'); ?></label>
                <input type="password" id="client_secret" name="<?php echo esc_attr($this->option_name); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>" required>
            </div>

            <div class="wpsl-wizard-actions">
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=overview')); ?>" class="wpsl-btn wpsl-btn-secondary">&larr; <?php _e('Back', 'wp-social-login'); ?></a>
                <div>
                    <button type="button" id="wpsl-test-connection-btn" class="wpsl-btn wpsl-btn-secondary"><?php _e('Test Connection', 'wp-social-login'); ?></button>
                    <button type="submit" class="wpsl-btn wpsl-btn-primary"><?php _e('Save & Continue', 'wp-social-login'); ?> &rarr;</button>
                </div>
            </div>
        </form>
    <?php
    }

    private function renderSecurityStep()
    {
        $settings = get_option($this->option_name, []);
        $security = $settings['security_features'] ?? [];

    ?>
        <div class="wpsl-step-header">
            <h2><?php _e('Security Features', 'wp-social-login'); ?></h2>
            <p><?php _e('Enable additional security measures to protect your site from common attack vectors. These features were implemented based on real-world security incidents.', 'wp-social-login'); ?></p>
        </div>

        <div class="wpsl-info-card warning">
            <h4><?php _e('⚠️ Important Security Notice', 'wp-social-login'); ?></h4>
            <p><?php _e(
                    'This plugin was developed in response to real-world brute-force attacks on WordPress sites. The included security features help reduce such risks, but they are not a complete security solution. Always keep WordPress updated and use reliable hosting for maximum protection.',
                    'wp-social-login'
                ); ?></p>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields($this->plugin_name); ?>
            <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_id]" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>">
            <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>">

            <div class="wpsl-security-grid">
                <?php
                $security_features = [
                    'disable_xmlrpc' => [
                        'title' => __('Disable XML-RPC', 'wp-social-login'),
                        'description' => __('Prevents brute-force attacks via XML-RPC protocol', 'wp-social-login'),
                        'impact' => __('May break: Mobile apps, Jetpack features, some backup plugins', 'wp-social-login')
                    ],
                    'disable_file_editing' => [
                        'title' => __('Disable File Editing', 'wp-social-login'),
                        'description' => __('Prevents code injection if admin account is compromised', 'wp-social-login'),
                        'impact' => __('May break: Theme/plugin editors in admin dashboard', 'wp-social-login')
                    ],
                    'hide_wp_version' => [
                        'title' => __('Hide WordPress Version', 'wp-social-login'),
                        'description' => __('Makes it harder for attackers to identify vulnerabilities', 'wp-social-login'),
                        'impact' => __('Generally safe, no functionality impact', 'wp-social-login')
                    ],
                    'restrict_rest_api' => [
                        'title' => __('Restrict REST API', 'wp-social-login'),
                        'description' => __('Prevents unauthorized data access via REST API', 'wp-social-login'),
                        'impact' => __('May break: Public API access, some plugins, headless setups', 'wp-social-login')
                    ],
                    'block_sensitive_files' => [
                        'title' => __('Block Sensitive Files', 'wp-social-login'),
                        'description' => __('Prevents direct access to wp-config.php, .htaccess, etc.', 'wp-social-login'),
                        'impact' => __('Generally safe, blocks direct file access', 'wp-social-login')
                    ]
                ];

                foreach ($security_features as $key => $feature):
                    $is_enabled = !empty($security[$key]);
                ?>
                    <div class="wpsl-security-card <?php echo $is_enabled ? 'enabled' : ''; ?>">
                        <h4>
                            <input type="checkbox" class="wpsl-security-toggle" name="<?php echo esc_attr($this->option_name); ?>[security_features][<?php echo esc_attr($key); ?>]" value="1" <?php checked($is_enabled); ?>>
                            <?php echo esc_html($feature['title']); ?>
                        </h4>
                        <p><?php echo esc_html($feature['description']); ?></p>
                        <small style="color: #666;"><strong><?php _e('Impact:', 'wp-social-login'); ?></strong> <?php echo esc_html($feature['impact']); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="wpsl-wizard-actions">
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=google_api')); ?>" class="wpsl-btn wpsl-btn-secondary">
                    <span>←</span>
                    <?php _e('Back', 'wp-social-login'); ?>
                </a>
                <button type="submit" class="wpsl-btn wpsl-btn-primary">
                    <?php _e('Save & Continue', 'wp-social-login'); ?>
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
        <div class="wpsl-step-header">
            <h2><?php _e('User Management', 'wp-social-login'); ?></h2>
            <p><?php _e('Manage who can access your site by adding their Google email addresses.', 'wp-social-login'); ?></p>
        </div>
        <form method="post" action="options.php">
            <?php settings_fields($this->plugin_name); ?>
            <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_id]" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>">
            <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>">

            <div class="wpsl-form-group">
                <label><?php _e('Authorized Users', 'wp-social-login'); ?></label>
                <div class="wpsl-user-list" id="user-list">
                    <?php if (empty($allowed_users)): ?>
                        <div class="wpsl-user-item">
                            <input type="email" name="<?php echo esc_attr($this->option_name); ?>[allowed_users][0][email]" placeholder="<?php esc_attr_e('user@example.com', 'wp-social-login'); ?>" required>
                            <select name="<?php echo esc_attr($this->option_name); ?>[allowed_users][0][role]">
                                <?php echo $this->getRoleOptions('subscriber'); ?>
                            </select>
                            <button type="button" class="wpsl-remove-user"><?php _e('Remove', 'wp-social-login'); ?></button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($allowed_users as $index => $user): ?>
                            <div class="wpsl-user-item">
                                <input type="email" name="<?php echo esc_attr($this->option_name); ?>[allowed_users][<?php echo esc_attr($index); ?>][email]" value="<?php echo esc_attr($user['email']); ?>" required>
                                <select name="<?php echo esc_attr($this->option_name); ?>[allowed_users][<?php echo esc_attr($index); ?>][role]">
                                    <?php echo $this->getRoleOptions($user['role']); ?>
                                </select>
                                <button type="button" class="wpsl-remove-user"><?php _e('Remove', 'wp-social-login'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" id="wpsl-add-user-btn" class="wpsl-btn wpsl-btn-secondary" style="margin-top: 15px;">+ <?php _e('Add User', 'wp-social-login'); ?></button>
            </div>

            <div class="wpsl-wizard-actions">
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=security')); ?>" class="wpsl-btn wpsl-btn-secondary">&larr; <?php _e('Back', 'wp-social-login'); ?></a>
                <button type="submit" class="wpsl-btn wpsl-btn-primary"><?php _e('Save & Continue', 'wp-social-login'); ?> &rarr;</button>
            </div>
        </form>
    <?php
    }

    private function renderOneTapStep()
    {
        $settings = get_option($this->option_name, []);

    ?>
        <div class="wpsl-step-header">
            <h2><?php _e('Google One Tap Configuration', 'wp-social-login'); ?></h2>
            <p><?php _e('Configure Google One Tap for seamless user authentication. One Tap allows users to sign in with a single click if they\'re already logged into their Google account.', 'wp-social-login'); ?></p>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields($this->plugin_name); ?>
            <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_id]" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>">
            <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>">

            <div class="wpsl-info-card">
                <h4><?php _e('🚀 About Google One Tap', 'wp-social-login'); ?></h4>
                <p><?php _e('One Tap is always enabled on the login page for the best user experience. Here you can choose whether to enable it on public pages like your homepage.', 'wp-social-login'); ?></p>
            </div>

            <div class="wpsl-form-group">
                <label>
                    <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[one_tap_homepage]" value="1" <?php checked(!empty($settings['one_tap_homepage'])); ?>>
                    <?php _e('Enable One Tap on Homepage and Public Pages', 'wp-social-login'); ?>
                </label>
                <p style="color: #666; margin-top: 8px; font-size: 14px;">
                    <?php _e('When enabled, visitors to your homepage will see the Google One Tap prompt if they\'re signed into Google. This can improve user experience but some prefer to keep the homepage clean for other purposes.', 'wp-social-login'); ?>
                </p>
            </div>

            <div class="wpsl-wizard-actions">
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=users')); ?>" class="wpsl-btn wpsl-btn-secondary">
                    <span>←</span>
                    <?php _e('Back', 'wp-social-login'); ?>
                </a>
                <button type="submit" class="wpsl-btn wpsl-btn-primary">
                    <?php _e('Save & Continue', 'wp-social-login'); ?>
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
        <div class="wpsl-step-header">
            <h2><?php _e('🎉 Setup Complete!', 'wp-social-login'); ?></h2>
            <p><?php _e('Congratulations! Your WP Social Login plugin is now configured and ready to use.', 'wp-social-login'); ?></p>
        </div>

        <?php if ($is_fully_configured): ?>
            <div class="wpsl-info-card success">
                <h4><?php _e('✅ Plugin is Active and Ready', 'wp-social-login'); ?></h4>
                <p><?php _e('Users can now sign in with their Google accounts. Password-based authentication has been disabled for enhanced security.', 'wp-social-login'); ?></p>
            </div>
        <?php else: ?>
            <div class="wpsl-info-card error">
                <h4><?php _e('❌ Configuration Incomplete', 'wp-social-login'); ?></h4>
                <p><?php _e('Some required settings are missing. Please complete the Google API configuration before users can log in.', 'wp-social-login'); ?></p>
            </div>
        <?php endif; ?>

        <div class="wpsl-info-card">
            <h4><?php _e('🔗 Important Links', 'wp-social-login'); ?></h4>
            <ul>
                <li><a href="<?php echo wp_login_url(); ?>" target="_blank"><?php _e('Test Your Login Page', 'wp-social-login'); ?></a></li>
                <li><a href="<?php echo admin_url('users.php'); ?>"><?php _e('Manage WordPress Users', 'wp-social-login'); ?></a></li>
                <li><a href="https://hardtoskip.com" target="_blank"><?php _e('Visit HardToSkip.com (Plugin Creator)', 'wp-social-login'); ?></a></li>
            </ul>
        </div>

        <div class="wpsl-info-card">
            <h4><?php _e('💡 Next Steps', 'wp-social-login'); ?></h4>
            <ul>
                <li><?php _e('Test the login functionality with an authorized email address', 'wp-social-login'); ?></li>
                <li><?php _e('Add more authorized users as needed', 'wp-social-login'); ?></li>
                <li><?php _e('Review and adjust security features based on your needs', 'wp-social-login'); ?></li>
                <li><?php _e('Monitor your site for any compatibility issues', 'wp-social-login'); ?></li>
            </ul>
        </div>

        <div class="wpsl-wizard-actions">
            <a href="<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=one_tap')); ?>" class="wpsl-btn wpsl-btn-secondary">
                <span>←</span>
                <?php _e('Back', 'wp-social-login'); ?>
            </a>
            <div style="display: flex; gap: 10px;">
                <a href="<?php echo wp_login_url(); ?>" target="_blank" class="wpsl-btn wpsl-btn-success">
                    <?php _e('Test Login Page', 'wp-social-login'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . $this->plugin_name . '&step=overview')); ?>" class="wpsl-btn wpsl-btn-primary">
                    <?php _e('Return to Overview', 'wp-social-login'); ?>
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
