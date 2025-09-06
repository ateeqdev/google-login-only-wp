<?php

class GLO_AdminSettings
{

    private $plugin_name;
    private $version;
    private $option_name = 'glo_settings';

    /**
     * Initialize the class and set its properties.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('admin_menu', [$this, 'addPluginPage']);
        add_action('admin_init', [$this, 'pageInit']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    /**
     * Enqueue styles and scripts for the admin page.
     */
    public function enqueueAdminAssets($hook)
    {
        // Only load on our specific plugin page
        if ($hook !== 'settings_page_' . $this->plugin_name) {
            return;
        }

        // Add enhanced styling for the admin page
        add_action('admin_head', fn() => print '
            <style>
                .glo-settings-wrap {
                    max-width: 1200px;
                }
                
                .glo-section {
                    background: #fff;
                    padding: 20px;
                    margin: 20px 0;
                    border-radius: 8px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                
                .glo-section h3 {
                    margin-top: 0;
                    color: #333;
                    border-bottom: 2px solid #f0f0f0;
                    padding-bottom: 10px;
                }
                
                .glo-info-box {
                    background: #e7f3ff;
                    border: 1px solid #b3d9ff;
                    border-radius: 6px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                
                .glo-info-box h4 {
                    margin-top: 0;
                    color: #0073aa;
                }
                
                .glo-warning-box {
                    background: #fff8e1;
                    border: 1px solid #ffb74d;
                    border-radius: 6px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                
                .glo-warning-box h4 {
                    margin-top: 0;
                    color: #f57c00;
                }
                
                .glo-step {
                    background: #f9f9f9;
                    border-left: 4px solid #4285F4;
                    padding: 15px;
                    margin: 10px 0;
                    border-radius: 0 6px 6px 0;
                }
                
                .glo-step h5 {
                    margin-top: 0;
                    color: #333;
                }
                
                .glo-redirect-uri {
                    background: #f1f1f1;
                    padding: 8px 12px;
                    border-radius: 4px;
                    font-family: monospace;
                    word-break: break-all;
                    margin: 10px 0;
                    border: 1px solid #ddd;
                }
                
                .glo-copy-btn {
                    background: #0073aa;
                    color: white;
                    border: none;
                    padding: 6px 12px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 12px;
                    margin-left: 10px;
                }
                
                .glo-copy-btn:hover {
                    background: #005a87;
                }
                
                .glo-security-feature {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-bottom: 15px;
                    padding: 12px;
                    background: #f8f9fa;
                    border-radius: 6px;
                }
                
                .glo-security-toggle {
                    margin-right: auto;
                }
                
                .glo-feature-impact {
                    font-size: 13px;
                    color: #666;
                    font-style: italic;
                    margin-top: 5px;
                }
                
                .hardtoskip-credit {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 20px;
                    border-radius: 8px;
                    text-align: center;
                    margin: 20px 0;
                }
                
                .hardtoskip-credit a {
                    color: #fff;
                    text-decoration: underline;
                    font-weight: bold;
                }
                
                .hardtoskip-credit a:hover {
                    color: #f0f0f0;
                }
                
                /* Rest of existing styles... */
                .glo-user-row { 
                    display: flex; 
                    align-items: center; 
                    margin-bottom: 15px; 
                    gap: 15px;
                    padding: 15px;
                    background: #f9f9f9;
                    border-radius: 6px;
                    border-left: 4px solid #4285F4;
                }
                
                .glo-user-row input[type="email"] { 
                    width: 300px;
                    padding: 8px 12px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }
                
                .glo-user-row select {
                    padding: 8px 12px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    min-width: 120px;
                }
                
                #glo-user-repeater-wrapper { 
                    padding-top: 10px; 
                }
                
                #glo-add-user { 
                    margin-top: 15px;
                    background: #4285F4;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 4px;
                    cursor: pointer;
                }
                
                #glo-add-user:hover {
                    background: #3367D6;
                }
                
                .glo-remove-user {
                    background: #dc3545;
                    color: white;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 4px;
                    cursor: pointer;
                }
                
                .glo-remove-user:hover {
                    background: #c82333;
                }
                
                .glo-wp-users-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 15px;
                }
                
                .glo-wp-users-table th,
                .glo-wp-users-table td {
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }
                
                .glo-wp-users-table th {
                    background: #f8f9fa;
                    font-weight: 600;
                    color: #333;
                }
                
                .glo-wp-users-table tr:hover {
                    background: #f8f9fa;
                }
                
                .glo-user-avatar {
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    object-fit: cover;
                }
                
                .glo-status-badge {
                    padding: 4px 8px;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 500;
                }
                
                .glo-status-pending {
                    background: #fff3cd;
                    color: #856404;
                }
                
                .glo-status-active {
                    background: #d4edda;
                    color: #155724;
                }
                
                .glo-remove-wp-user {
                    background: #dc3545;
                    color: white;
                    border: none;
                    padding: 6px 12px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 12px;
                }
                
                .glo-remove-wp-user:hover {
                    background: #c82333;
                }
                
                .glo-info-box {
                    background: #e7f3ff;
                    border: 1px solid #b3d9ff;
                    border-radius: 6px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                
                .glo-info-box h4 {
                    margin-top: 0;
                    color: #0073aa;
                }
                
                .glo-redirect-uri {
                    background: #f1f1f1;
                    padding: 8px 12px;
                    border-radius: 4px;
                    font-family: monospace;
                    word-break: break-all;
                    margin: 10px 0;
                }
                
                .glo-copy-btn {
                    background: #0073aa;
                    color: white;
                    border: none;
                    padding: 6px 12px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 12px;
                    margin-left: 10px;
                }
                
                .glo-copy-btn:hover {
                    background: #005a87;
                }
            </style>
        ');

        add_action('admin_footer', [$this, 'enhancedAdminScript']);
    }

    /**
     * Add options page.
     */
    public function addPluginPage()
    {
        add_options_page(
            esc_html__('Google Login Only Settings', 'google-login-only'),
            esc_html__('Google Login Only', 'google-login-only'),
            'manage_options',
            $this->plugin_name,
            [$this, 'createAdminPage']
        );
    }

    /**
     * Register and add settings.
     */
    public function pageInit()
    {
        register_setting(
            $this->plugin_name,
            $this->option_name,
            [$this, 'sanitize']
        );

        add_settings_section(
            'setting_section_id',
            esc_html__('Google API Configuration', 'google-login-only'),
            [$this, 'apiSectionCallback'],
            $this->plugin_name
        );

        add_settings_field('client_id', esc_html__('Client ID', 'google-login-only'), [$this, 'clientIdCallback'], $this->plugin_name, 'setting_section_id');
        add_settings_field('client_secret', esc_html__('Client Secret', 'google-login-only'), [$this, 'clientSecretCallback'], $this->plugin_name, 'setting_section_id');

        add_settings_section(
            'security_section',
            esc_html__('Security Features', 'google-login-only'),
            [$this, 'securitySectionCallback'],
            $this->plugin_name
        );
        add_settings_field('security_features', esc_html__('Enhanced Security Options', 'google-login-only'), [$this, 'securityFeaturesCallback'], $this->plugin_name, 'security_section');

        add_settings_section(
            'one_tap_section',
            esc_html__('Google One Tap Settings', 'google-login-only'),
            [$this, 'oneTapSectionCallback'],
            $this->plugin_name
        );
        add_settings_field('one_tap_enabled', esc_html__('Enable One Tap on Homepage', 'google-login-only'), [$this, 'oneTapEnabledCallback'], $this->plugin_name, 'one_tap_section');

        add_settings_section(
            'allowed_users_section',
            esc_html__('User Management', 'google-login-only'),
            [$this, 'userManagementSectionCallback'],
            $this->plugin_name
        );
        add_settings_field('allowed_users', esc_html__('Pending Users (Not Yet Registered)', 'google-login-only'), [$this, 'allowedUsersCallback'], $this->plugin_name, 'allowed_users_section');
        add_settings_field('wordpress_users', esc_html__('Active Google Users', 'google-login-only'), [$this, 'wordpressUsersCallback'], $this->plugin_name, 'allowed_users_section');
    }

    /**
     * API section description callback with detailed setup instructions.
     */
    public function apiSectionCallback()
    {
        echo '<div class="glo-info-box">';
        echo '<h4>' . esc_html__('📋 Complete Setup Guide', 'google-login-only') . '</h4>';
        echo '<p>' . esc_html__('Follow these steps to set up Google OAuth for your WordPress site:', 'google-login-only') . '</p>';

        echo '<div class="glo-step">';
        echo '<h5>' . esc_html__('Step 1: Access Google Cloud Console', 'google-login-only') . '</h5>';
        echo '<p>' . sprintf(esc_html__('Go to %1$sGoogle Cloud Console%2$s and sign in with your Google account.', 'google-login-only'), '<a href="https://console.cloud.google.com/" target="_blank">', '</a>') . '</p>';
        echo '</div>';

        echo '<div class="glo-step">';
        echo '<h5>' . esc_html__('Step 2: Create or Select a Project', 'google-login-only') . '</h5>';
        echo '<p>' . esc_html__('Create a new project or select an existing one from the dropdown at the top of the page.', 'google-login-only') . '</p>';
        echo '</div>';

        echo '<div class="glo-step">';
        echo '<h5>' . esc_html__('Step 3: Create OAuth Credentials', 'google-login-only') . '</h5>';
        echo '<p>' . esc_html__('Go to "APIs & Services" → "Credentials" → "Create Credentials" → "OAuth client ID"', 'google-login-only') . '</p>';
        echo '<p>' . esc_html__('Choose "Web application" as the application type.', 'google-login-only') . '</p>';
        echo '</div>';

        echo '<div class="glo-step">';
        echo '<h5>' . esc_html__('Step 4: Configure Authorized Redirect URIs', 'google-login-only') . '</h5>';
        echo '<p><strong>' . esc_html__('Add these EXACT URIs to your OAuth client:', 'google-login-only') . '</strong></p>';
        echo '<div class="glo-redirect-uri">' . home_url('?action=google_login_callback') . '</div>';
        echo '<button type="button" class="glo-copy-btn" onclick="copyToClipboard(\'' . esc_js(home_url('?action=google_login_callback')) . '\')">' . esc_html__('Copy URI', 'google-login-only') . '</button>';
        echo '<div class="glo-redirect-uri">' . home_url('?action=google_one_tap_callback') . '</div>';
        echo '<button type="button" class="glo-copy-btn" onclick="copyToClipboard(\'' . esc_js(home_url('?action=google_one_tap_callback')) . '\')">' . esc_html__('Copy URI', 'google-login-only') . '</button>';
        echo '</div>';

        echo '<div class="glo-step">';
        echo '<h5>' . esc_html__('Step 5: Configure Authorized JavaScript Origins', 'google-login-only') . '</h5>';
        echo '<p>' . esc_html__('In the same OAuth client settings, add both versions of your site as Authorized JavaScript Origins:', 'google-login-only') . '</p>';
        echo '<div class="glo-redirect-uri">' . esc_url(home_url()) . '</div>';
        echo '<button type="button" class="glo-copy-btn" onclick="copyToClipboard(\'' . esc_js(home_url()) . '\')">' . esc_html__('Copy Origin', 'google-login-only') . '</button>';
        echo '<div class="glo-redirect-uri">' . esc_url(str_replace("https://", "https://www.", home_url())) . '</div>';
        echo '<button type="button" class="glo-copy-btn" onclick="copyToClipboard(\'' . esc_js(str_replace("https://", "https://www.", home_url())) . '\')">' . esc_html__('Copy Origin', 'google-login-only') . '</button>';
        echo '</div>';

        echo '<div class="glo-step">';
        echo '<h5>' . esc_html__('Step 6: Copy Client ID & Secret', 'google-login-only') . '</h5>';
        echo '<p>' . sprintf(esc_html__('After creating the OAuth client, copy the %1$sClient ID%2$s and %3$sClient Secret%4$s and paste them in the fields below.', 'google-login-only'), '<strong>', '</strong>', '<strong>', '</strong>') . '</p>';
        echo '</div>';

        echo '</div>';
    }


    /**
     * Security section callback with detailed warnings.
     */
    public function securitySectionCallback()
    {
        echo '<div class="glo-warning-box">';
        echo '<h4>' . esc_html__('⚠️ Important Security Information', 'google-login-only') . '</h4>';
        echo '<p><strong>' . esc_html__('Background:', 'google-login-only') . '</strong> ' . sprintf(esc_html__('This plugin was created after %1$sHardToSkip.com%2$s was compromised through a brute-force password attack.', 'google-login-only'), '<a href="https://hardtoskip.com" target="_blank" rel="dofollow">', '</a>') . '</p>';
        echo '<p><strong>' . esc_html__('Purpose:', 'google-login-only') . '</strong> ' . esc_html__('These security features help protect against similar attacks by disabling various WordPress entry points and features that attackers commonly exploit.', 'google-login-only') . '</p>';
        echo '<p><strong>' . esc_html__('Important Limitations:', 'google-login-only') . '</strong></p>';
        echo '<ul>';
        echo '<li>' . sprintf(esc_html__('These features are %1$snot a complete security solution%2$s', 'google-login-only'), '<strong>', '</strong>') . '</li>';
        echo '<li>' . esc_html__('They may break some plugins or themes that depend on the disabled features', 'google-login-only') . '</li>';
        echo '<li>' . esc_html__('Regular security updates and good hosting are still essential', 'google-login-only') . '</li>';
        echo '<li>' . esc_html__('Consider additional security measures like WAF, 2FA for admin accounts, etc.', 'google-login-only') . '</li>';
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Security features callback with detailed impact descriptions.
     */
    public function securityFeaturesCallback()
    {
        $options = get_option($this->option_name);
        $security = $options['security_features'] ?? [];

        $features = [
            'disable_xmlrpc' => [
                'label' => esc_html__('Disable XML-RPC', 'google-login-only'),
                'impact' => esc_html__('May break: Mobile apps, Jetpack features, some backup plugins', 'google-login-only'),
                'description' => esc_html__('XML-RPC is commonly targeted for brute-force attacks', 'google-login-only')
            ],
            'disable_file_editing' => [
                'label' => esc_html__('Disable File Editing in Admin', 'google-login-only'),
                'impact' => esc_html__('May break: Theme/plugin editors in admin dashboard', 'google-login-only'),
                'description' => esc_html__('Prevents code injection if admin account is compromised', 'google-login-only')
            ],
            'hide_wp_version' => [
                'label' => esc_html__('Hide WordPress Version', 'google-login-only'),
                'impact' => esc_html__('Generally safe, no functionality impact', 'google-login-only'),
                'description' => esc_html__('Makes it harder for attackers to identify known vulnerabilities', 'google-login-only')
            ],
            'restrict_rest_api' => [
                'label' => esc_html__('Restrict REST API to Logged-in Users', 'google-login-only'),
                'impact' => esc_html__('May break: Public API access, some plugins, headless setups', 'google-login-only'),
                'description' => esc_html__('Prevents unauthorized data access via REST API', 'google-login-only')
            ],
            'block_sensitive_files' => [
                'label' => esc_html__('Block Access to Sensitive Files', 'google-login-only'),
                'impact' => esc_html__('Generally safe, blocks direct file access', 'google-login-only'),
                'description' => esc_html__('Prevents direct access to wp-config.php, .htaccess, etc.', 'google-login-only')
            ]
        ];

        foreach ($features as $key => $feature) {
            $checked = isset($security[$key]) && $security[$key] ? 'checked' : '';
            echo '<div class="glo-security-feature">';
            echo '<label class="glo-security-toggle">';
            echo '<input type="checkbox" name="' . $this->option_name . '[security_features][' . $key . ']" value="1" ' . $checked . ' />';
            echo '<strong>' . $feature['label'] . '</strong>';
            echo '<div class="glo-feature-impact">' . esc_html__('Impact:', 'google-login-only') . ' ' . $feature['impact'] . '</div>';
            echo '</label>';
            echo '<p style="margin: 0; font-size: 13px; color: #555;">' . $feature['description'] . '</p>';
            echo '</div>';
        }
    }

    /**
     * One Tap section callback.
     */
    public function oneTapSectionCallback()
    {
        echo '<div class="glo-info-box">';
        echo '<h4>' . esc_html__('Google One Tap Configuration', 'google-login-only') . '</h4>';
        echo '<p><strong>' . esc_html__('Login Page:', 'google-login-only') . '</strong> ' . esc_html__('One Tap is always enabled on the login page for the best user experience.', 'google-login-only') . '</p>';
        echo '<p><strong>' . esc_html__('Homepage/Other Pages:', 'google-login-only') . '</strong> ' . esc_html__('You can choose whether to enable One Tap on your homepage and other public pages. Some users prefer to keep the homepage URL clean for app signups or other purposes.', 'google-login-only') . '</p>';
        echo '</div>';
    }

    /**
     * One Tap enabled callback.
     */
    public function oneTapEnabledCallback()
    {
        $options = get_option($this->option_name);
        $enabled = isset($options['one_tap_homepage']) && $options['one_tap_homepage'] ? 'checked' : '';

        echo '<label>';
        echo '<input type="checkbox" name="' . $this->option_name . '[one_tap_homepage]" value="1" ' . $enabled . ' />';
        echo esc_html__('Enable Google One Tap on homepage and public pages', 'google-login-only');
        echo '</label>';
        echo '<p class="description">' . esc_html__('When enabled, visitors to your homepage will see the Google One Tap prompt if they\'re signed into Google. The login page will always show One Tap regardless of this setting.', 'google-login-only') . '</p>';
    }

    /**
     * User management section description callback.
     */
    public function userManagementSectionCallback()
    {
        echo '<div class="glo-info-box">';
        echo '<h4>' . esc_html__('How User Management Works', 'google-login-only') . '</h4>';
        echo '<p><strong>' . esc_html__('Pending Users:', 'google-login-only') . '</strong> ' . esc_html__('Add email addresses here for users who haven\'t signed up yet. Once they sign in with Google, they\'ll be automatically moved to the WordPress users table and removed from this list.', 'google-login-only') . '</p>';
        echo '<p><strong>' . esc_html__('Active Google Users:', 'google-login-only') . '</strong> ' . esc_html__('WordPress users who have signed in via Google. They can continue to access the site without being in the pending list.', 'google-login-only') . '</p>';
        echo '</div>';
    }

    /**
     * Create the enhanced settings page view.
     */
    public function createAdminPage()
    {
?>
        <div class="wrap glo-settings-wrap">
            <h1><?php esc_html_e('Google Login Only Settings', 'google-login-only'); ?></h1>

            <!-- HardToSkip Credit -->
            <div class="hardtoskip-credit">
                <p><strong><?php printf(esc_html__('Plugin developed by %1$sHardToSkip%2$s', 'google-login-only'), '<a href="https://hardtoskip.com" target="_blank">', '</a>'); ?></strong><?php esc_html_e(' - AI Meme Generator', 'google-login-only'); ?></p>
                <p><?php esc_html_e('Born from necessity after a security breach, this plugin enforces Google-only authentication to keep your WordPress site secure.', 'google-login-only'); ?></p>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields($this->plugin_name);
                do_settings_sections($this->plugin_name);
                submit_button(esc_html__('Save Settings', 'google-login-only'), 'primary large');
                ?>
            </form>
        </div>
    <?php
    }

    /**
     * Sanitize each setting field as needed.
     */
    public function sanitize($input)
    {
        $sanitized_input = [];
        $sanitized_input['client_id'] = sanitize_text_field($input['client_id'] ?? '');
        $sanitized_input['client_secret'] = sanitize_text_field($input['client_secret'] ?? '');
        $sanitized_input['one_tap_homepage'] = isset($input['one_tap_homepage']) ? 1 : 0;

        // Sanitize security features
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
                if (!empty($user['email']) && is_email($user['email']) && in_array($user['role'], ['administrator', 'editor', 'contributor', 'subscriber'])) {
                    $allowed_users[] = [
                        'email' => sanitize_email($user['email']),
                        'role' => sanitize_key($user['role']),
                    ];
                }
            }
            $sanitized_input['allowed_users'] = $allowed_users;
        }

        return $sanitized_input;
    }

    public function clientIdCallback()
    {
        $options = get_option($this->option_name);
        printf('<input type="text" id="client_id" name="%s[client_id]" value="%s" class="regular-text" placeholder="%s" />', $this->option_name, $options['client_id'] ?? '', esc_attr__('Your Google OAuth Client ID', 'google-login-only'));
    }

    public function clientSecretCallback()
    {
        $options = get_option($this->option_name);
        printf('<input type="password" id="client_secret" name="%s[client_secret]" value="%s" class="regular-text" placeholder="%s" />', $this->option_name, $options['client_secret'] ?? '', esc_attr__('Your Google OAuth Client Secret', 'google-login-only'));
    }

    /**
     * Callback for the pending users repeater field.
     */
    public function allowedUsersCallback()
    {
        $options = get_option($this->option_name);
        $users = $options['allowed_users'] ?? [];
    ?>
        <div id="glo-user-repeater-wrapper">
            <div id="glo-user-repeater">
                <?php if (empty($users)) : ?>
                    <p><em><?php esc_html_e('No pending users. Users will be automatically removed from this list once they sign in and become WordPress users.', 'google-login-only'); ?></em></p>
                <?php else : ?>
                    <?php foreach ($users as $index => $user) : ?>
                        <div class="glo-user-row">
                            <input type="email" name="<?php echo $this->option_name; ?>[allowed_users][<?php echo $index; ?>][email]" value="<?php echo esc_attr($user['email']); ?>" placeholder="<?php esc_attr_e('user@example.com', 'google-login-only'); ?>" required />
                            <select name="<?php echo $this->option_name; ?>[allowed_users][<?php echo $index; ?>][role]">
                                <option value="administrator" <?php selected($user['role'], 'administrator'); ?>><?php esc_html_e('Administrator', 'google-login-only'); ?></option>
                                <option value="editor" <?php selected($user['role'], 'editor'); ?>><?php esc_html_e('Editor', 'google-login-only'); ?></option>
                                <option value="contributor" <?php selected($user['role'], 'contributor'); ?>><?php esc_html_e('Contributor', 'google-login-only'); ?></option>
                                <option value="subscriber" <?php selected($user['role'], 'subscriber'); ?>><?php esc_html_e('Subscriber', 'google-login-only'); ?></option>
                            </select>
                            <button type="button" class="glo-remove-user"><?php esc_html_e('Remove', 'google-login-only'); ?></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" id="glo-add-user" class="button"><?php esc_html_e('+ Add Pending User', 'google-login-only'); ?></button>
        </div>

        <!-- JS Template for new rows -->
        <template id="glo-repeater-template">
            <div class="glo-user-row">
                <input type="email" name="<?php echo $this->option_name; ?>[allowed_users][__INDEX__][email]" placeholder="<?php esc_attr_e('user@example.com', 'google-login-only'); ?>" required />
                <select name="<?php echo $this->option_name; ?>[allowed_users][__INDEX__][role]">
                    <option value="administrator"><?php esc_html_e('Administrator', 'google-login-only'); ?></option>
                    <option value="editor"><?php esc_html_e('Editor', 'google-login-only'); ?></option>
                    <option value="contributor"><?php esc_html_e('Contributor', 'google-login-only'); ?></option>
                    <option value="subscriber"><?php esc_html_e('Subscriber', 'google-login-only'); ?></option>
                </select>
                <button type="button" class="glo-remove-user"><?php esc_html_e('Remove', 'google-login-only'); ?></button>
            </div>
        </template>
    <?php
    }

    /**
     * Display active WordPress users who can login via Google.
     */
    public function wordpressUsersCallback()
    {
        $wp_users = get_users([
            'orderby' => 'registered',
            'order' => 'DESC'
        ]);

        if (empty($wp_users)) {
            echo '<p><em>' . esc_html__('No WordPress users found.', 'google-login-only') . '</em></p>';
            return;
        }
    ?>
        <table class="glo-wp-users-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('User', 'google-login-only'); ?></th>
                    <th><?php esc_html_e('Email', 'google-login-only'); ?></th>
                    <th><?php esc_html_e('Role', 'google-login-only'); ?></th>
                    <th><?php esc_html_e('Registered', 'google-login-only'); ?></th>
                    <th><?php esc_html_e('Status', 'google-login-only'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wp_users as $user) : ?>
                    <?php
                    $google_picture = get_user_meta($user->ID, 'google_profile_picture', true);
                    $avatar_url = $google_picture ?: get_avatar_url($user->ID, ['size' => 32]);
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php esc_attr_e('Avatar', 'google-login-only'); ?>" class="glo-user-avatar">
                                <div>
                                    <strong><?php echo esc_html($user->display_name); ?></strong>
                                    <br><small>@<?php echo esc_html($user->user_login); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td>
                            <?php
                            $roles = $user->roles;
                            echo esc_html(ucfirst($roles[0] ?? esc_html__('No role', 'google-login-only')));
                            ?>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($user->user_registered)); ?></td>
                        <td>
                            <span class="glo-status-badge glo-status-active"><?php esc_html_e('Active', 'google-login-only'); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="description">
            <?php esc_html_e('To remove user access, please use the WordPress Users section in the admin menu.', 'google-login-only'); ?>
        </p>
    <?php
    }

    /**
     * Enhanced admin script with additional functionality.
     */
    public function enhancedAdminScript()
    {
    ?>
        <script>
            const glo_admin = {
                nonce: '<?php echo wp_create_nonce('glo_admin_nonce'); ?>'
            };

            document.addEventListener('DOMContentLoaded', function() {
                const addButton = document.getElementById('glo-add-user');
                const repeater = document.getElementById('glo-user-repeater');
                const template = document.getElementById('glo-repeater-template');
                let userIndex = <?php echo count(get_option($this->option_name)['allowed_users'] ?? []); ?>;

                if (!addButton || !repeater || !template) return;

                // Remove initial "No users" message on first add
                const removePlaceholder = () => {
                    const placeholder = repeater.querySelector('p em');
                    if (placeholder && placeholder.closest('p')) {
                        placeholder.closest('p').remove();
                    }
                };

                addButton.addEventListener('click', function() {
                    removePlaceholder();
                    const clone = template.content.cloneNode(true);
                    const newRow = clone.firstElementChild;

                    // Update input names with the correct index
                    newRow.querySelectorAll('[name]').forEach(el => {
                        el.name = el.name.replace('__INDEX__', userIndex);
                    });

                    repeater.appendChild(newRow);
                    userIndex++;

                    // Focus on the email input
                    newRow.querySelector('input[type="email"]').focus();
                });

                repeater.addEventListener('click', function(e) {
                    if (e.target && e.target.classList.contains('glo-remove-user')) {
                        if (confirm('<?php echo esc_js(__('Are you sure you want to remove this pending user?', 'google-login-only')); ?>')) {
                            e.target.closest('.glo-user-row').remove();
                        }
                    }
                });
            });

            // Fixed copy to clipboard function
            function copyToClipboard(text) {
                if (navigator.clipboard && window.isSecureContext) {
                    // Modern approach for secure contexts
                    navigator.clipboard.writeText(text).then(function() {
                        showCopyFeedback(event.target, '<?php echo esc_js(__('Copied!', 'google-login-only')); ?>', '#28a745');
                    }).catch(function() {
                        fallbackCopyTextToClipboard(text);
                    });
                } else {
                    // Fallback for older browsers or non-secure contexts
                    fallbackCopyTextToClipboard(text);
                }
            }

            function fallbackCopyTextToClipboard(text) {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.left = "-999999px";
                textArea.style.top = "-999999px";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();

                try {
                    const successful = document.execCommand('copy');
                    if (successful) {
                        showCopyFeedback(event.target, '<?php echo esc_js(__('Copied!', 'google-login-only')); ?>', '#28a745');
                    } else {
                        showCopyFeedback(event.target, '<?php echo esc_js(__('Failed to copy', 'google-login-only')); ?>', '#dc3545');
                    }
                } catch (err) {
                    showCopyFeedback(event.target, '<?php echo esc_js(__('Failed to copy', 'google-login-only')); ?>', '#dc3545');
                }

                document.body.removeChild(textArea);
            }

            function showCopyFeedback(btn, message, color) {
                const originalText = btn.textContent;
                const originalColor = btn.style.backgroundColor;

                btn.textContent = message;
                btn.style.backgroundColor = color;

                setTimeout(function() {
                    btn.textContent = originalText;
                    btn.style.backgroundColor = originalColor || '#0073aa';
                }, 2000);
            }
        </script>
<?php
    }
}
