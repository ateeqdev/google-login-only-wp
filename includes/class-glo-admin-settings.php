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
            'Google Login Only Settings',
            'Google Login Only',
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
            'Google API Configuration',
            [$this, 'apiSectionCallback'],
            $this->plugin_name
        );

        add_settings_field('client_id', 'Client ID', [$this, 'clientIdCallback'], $this->plugin_name, 'setting_section_id');
        add_settings_field('client_secret', 'Client Secret', [$this, 'clientSecretCallback'], $this->plugin_name, 'setting_section_id');

        add_settings_section(
            'security_section',
            'Security Features',
            [$this, 'securitySectionCallback'],
            $this->plugin_name
        );
        add_settings_field('security_features', 'Enhanced Security Options', [$this, 'securityFeaturesCallback'], $this->plugin_name, 'security_section');

        add_settings_section(
            'one_tap_section',
            'Google One Tap Settings',
            [$this, 'oneTapSectionCallback'],
            $this->plugin_name
        );
        add_settings_field('one_tap_enabled', 'Enable One Tap on Homepage', [$this, 'oneTapEnabledCallback'], $this->plugin_name, 'one_tap_section');

        add_settings_section(
            'allowed_users_section',
            'User Management',
            [$this, 'userManagementSectionCallback'],
            $this->plugin_name
        );
        add_settings_field('allowed_users', 'Pending Users (Not Yet Registered)', [$this, 'allowedUsersCallback'], $this->plugin_name, 'allowed_users_section');
        add_settings_field('wordpress_users', 'Active Google Users', [$this, 'wordpressUsersCallback'], $this->plugin_name, 'allowed_users_section');
    }

    /**
     * API section description callback with detailed setup instructions.
     */
    public function apiSectionCallback()
    {
        echo '<div class="glo-info-box">';
        echo '<h4>📋 Complete Setup Guide</h4>';
        echo '<p>Follow these steps to set up Google OAuth for your WordPress site:</p>';

        echo '<div class="glo-step">';
        echo '<h5>Step 1: Access Google Cloud Console</h5>';
        echo '<p>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> and sign in with your Google account.</p>';
        echo '</div>';

        echo '<div class="glo-step">';
        echo '<h5>Step 2: Create or Select a Project</h5>';
        echo '<p>Create a new project or select an existing one from the dropdown at the top of the page.</p>';
        echo '</div>';

        echo '<div class="glo-step">';
        echo '<h5>Step 3: Create OAuth Credentials</h5>';
        echo '<p>Go to "APIs & Services" → "Credentials" → "Create Credentials" → "OAuth client ID"</p>';
        echo '<p>Choose "Web application" as the application type.</p>';
        echo '</div>';

        echo '<div class="glo-step">';
        echo '<h5>Step 4: Configure Authorized Redirect URIs</h5>';
        echo '<p><strong>Add these EXACT URIs to your OAuth client:</strong></p>';
        echo '<div class="glo-redirect-uri">' . home_url('?action=google_login_callback') . '</div>';
        echo '<button type="button" class="glo-copy-btn" onclick="copyToClipboard(\'' . esc_js(home_url('?action=google_login_callback')) . '\')">Copy URI</button>';
        echo '<div class="glo-redirect-uri">' . home_url('?action=google_one_tap_callback') . '</div>';
        echo '<button type="button" class="glo-copy-btn" onclick="copyToClipboard(\'' . esc_js(home_url('?action=google_one_tap_callback')) . '\')">Copy URI</button>';
        echo '</div>';

        echo '<div class="glo-step">';
        echo '<h5>Step 6: Copy Client ID & Secret</h5>';
        echo '<p>After creating the OAuth client, copy the <strong>Client ID</strong> and <strong>Client Secret</strong> and paste them in the fields below.</p>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Security section callback with detailed warnings.
     */
    public function securitySectionCallback()
    {
        echo '<div class="glo-warning-box">';
        echo '<h4>⚠️ Important Security Information</h4>';
        echo '<p><strong>Background:</strong> This plugin was created after <a href="https://hardtoskip.com" target="_blank" rel="dofollow">HardToSkip.com</a> was compromised through a brute-force password attack.</p>';
        echo '<p><strong>Purpose:</strong> These security features help protect against similar attacks by disabling various WordPress entry points and features that attackers commonly exploit.</p>';
        echo '<p><strong>Important Limitations:</strong></p>';
        echo '<ul>';
        echo '<li>These features are <strong>not a complete security solution</strong></li>';
        echo '<li>They may break some plugins or themes that depend on the disabled features</li>';
        echo '<li>Regular security updates and good hosting are still essential</li>';
        echo '<li>Consider additional security measures like WAF, 2FA for admin accounts, etc.</li>';
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
                'label' => 'Disable XML-RPC',
                'impact' => 'May break: Mobile apps, Jetpack features, some backup plugins',
                'description' => 'XML-RPC is commonly targeted for brute-force attacks'
            ],
            'disable_file_editing' => [
                'label' => 'Disable File Editing in Admin',
                'impact' => 'May break: Theme/plugin editors in admin dashboard',
                'description' => 'Prevents code injection if admin account is compromised'
            ],
            'hide_wp_version' => [
                'label' => 'Hide WordPress Version',
                'impact' => 'Generally safe, no functionality impact',
                'description' => 'Makes it harder for attackers to identify known vulnerabilities'
            ],
            'restrict_rest_api' => [
                'label' => 'Restrict REST API to Logged-in Users',
                'impact' => 'May break: Public API access, some plugins, headless setups',
                'description' => 'Prevents unauthorized data access via REST API'
            ],
            'block_sensitive_files' => [
                'label' => 'Block Access to Sensitive Files',
                'impact' => 'Generally safe, blocks direct file access',
                'description' => 'Prevents direct access to wp-config.php, .htaccess, etc.'
            ]
        ];

        foreach ($features as $key => $feature) {
            $checked = isset($security[$key]) && $security[$key] ? 'checked' : '';
            echo '<div class="glo-security-feature">';
            echo '<label class="glo-security-toggle">';
            echo '<input type="checkbox" name="' . $this->option_name . '[security_features][' . $key . ']" value="1" ' . $checked . ' />';
            echo '<strong>' . $feature['label'] . '</strong>';
            echo '<div class="glo-feature-impact">Impact: ' . $feature['impact'] . '</div>';
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
        echo '<h4>Google One Tap Configuration</h4>';
        echo '<p><strong>Login Page:</strong> One Tap is always enabled on the login page for the best user experience.</p>';
        echo '<p><strong>Homepage/Other Pages:</strong> You can choose whether to enable One Tap on your homepage and other public pages. Some users prefer to keep the homepage URL clean for app signups or other purposes.</p>';
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
        echo 'Enable Google One Tap on homepage and public pages';
        echo '</label>';
        echo '<p class="description">When enabled, visitors to your homepage will see the Google One Tap prompt if they\'re signed into Google. The login page will always show One Tap regardless of this setting.</p>';
    }

    /**
     * User management section description callback.
     */
    public function userManagementSectionCallback()
    {
        echo '<div class="glo-info-box">';
        echo '<h4>How User Management Works</h4>';
        echo '<p><strong>Pending Users:</strong> Add email addresses here for users who haven\'t signed up yet. Once they sign in with Google, they\'ll be automatically moved to the WordPress users table and removed from this list.</p>';
        echo '<p><strong>Active Google Users:</strong> WordPress users who have signed in via Google. They can continue to access the site without being in the pending list.</p>';
        echo '</div>';
    }

    /**
     * Create the enhanced settings page view.
     */
    public function createAdminPage()
    {
?>
        <div class="wrap glo-settings-wrap">
            <h1>Google Login Only Settings</h1>

            <!-- HardToSkip Credit -->
            <div class="hardtoskip-credit">
                <p><strong>Plugin developed by <a href="https://hardtoskip.com" target="_blank" rel="dofollow">HardToSkip</a></strong> - AI Meme Generator</p>
                <p>Born from necessity after a security breach, this plugin enforces Google-only authentication to keep your WordPress site secure.</p>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields($this->plugin_name);
                do_settings_sections($this->plugin_name);
                submit_button('Save Settings', 'primary large');
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
        printf('<input type="text" id="client_id" name="%s[client_id]" value="%s" class="regular-text" placeholder="Your Google OAuth Client ID" />', $this->option_name, $options['client_id'] ?? '');
    }

    public function clientSecretCallback()
    {
        $options = get_option($this->option_name);
        printf('<input type="password" id="client_secret" name="%s[client_secret]" value="%s" class="regular-text" placeholder="Your Google OAuth Client Secret" />', $this->option_name, $options['client_secret'] ?? '');
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
                    <p><em>No pending users. Users will be automatically removed from this list once they sign in and become WordPress users.</em></p>
                <?php else : ?>
                    <?php foreach ($users as $index => $user) : ?>
                        <div class="glo-user-row">
                            <input type="email" name="<?php echo $this->option_name; ?>[allowed_users][<?php echo $index; ?>][email]" value="<?php echo esc_attr($user['email']); ?>" placeholder="user@example.com" required />
                            <select name="<?php echo $this->option_name; ?>[allowed_users][<?php echo $index; ?>][role]">
                                <option value="administrator" <?php selected($user['role'], 'administrator'); ?>>Administrator</option>
                                <option value="editor" <?php selected($user['role'], 'editor'); ?>>Editor</option>
                                <option value="contributor" <?php selected($user['role'], 'contributor'); ?>>Contributor</option>
                                <option value="subscriber" <?php selected($user['role'], 'subscriber'); ?>>Subscriber</option>
                            </select>
                            <button type="button" class="glo-remove-user">Remove</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" id="glo-add-user" class="button">+ Add Pending User</button>
        </div>

        <!-- JS Template for new rows -->
        <template id="glo-repeater-template">
            <div class="glo-user-row">
                <input type="email" name="<?php echo $this->option_name; ?>[allowed_users][__INDEX__][email]" placeholder="user@example.com" required />
                <select name="<?php echo $this->option_name; ?>[allowed_users][__INDEX__][role]">
                    <option value="administrator">Administrator</option>
                    <option value="editor">Editor</option>
                    <option value="contributor">Contributor</option>
                    <option value="subscriber">Subscriber</option>
                </select>
                <button type="button" class="glo-remove-user">Remove</button>
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
            echo '<p><em>' . __('No WordPress users found.', 'google-login-only') . '</em></p>';
            return;
        }
    ?>
        <table class="glo-wp-users-table">
            <thead>
                <tr>
                    <th><?php _e('User', 'google-login-only'); ?></th>
                    <th><?php _e('Email', 'google-login-only'); ?></th>
                    <th><?php _e('Role', 'google-login-only'); ?></th>
                    <th><?php _e('Registered', 'google-login-only'); ?></th>
                    <th><?php _e('Status', 'google-login-only'); ?></th>
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
                                <img src="<?php echo esc_url($avatar_url); ?>" alt="Avatar" class="glo-user-avatar">
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
                            echo esc_html(ucfirst($roles[0] ?? 'No role'));
                            ?>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($user->user_registered)); ?></td>
                        <td>
                            <span class="glo-status-badge glo-status-active"><?php _e('Active', 'google-login-only'); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="description">
            <?php _e('To remove user access, please use the WordPress Users section in the admin menu.', 'google-login-only'); ?>
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
                        if (confirm('Are you sure you want to remove this pending user?')) {
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
                        showCopyFeedback(event.target, 'Copied!', '#28a745');
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
                        showCopyFeedback(event.target, 'Copied!', '#28a745');
                    } else {
                        showCopyFeedback(event.target, 'Failed to copy', '#dc3545');
                    }
                } catch (err) {
                    showCopyFeedback(event.target, 'Failed to copy', '#dc3545');
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
