<?php

class GLO_GoogleLoginOnly
{

    /**
     * The unique identifier of this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct()
    {
        $this->plugin_name = 'google-login-only';
        $this->version = GLO_VERSION;
    }

    /**
     * Load the required dependencies and set up the hooks.
     */
    public function run()
    {
        $this->loadDependencies();
        $this->initializeClasses();
        $this->defineHooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function loadDependencies()
    {
        require_once GLO_PLUGIN_PATH . 'includes/class-glo-admin-settings.php';
        require_once GLO_PLUGIN_PATH . 'includes/class-glo-google-auth.php';
        require_once GLO_PLUGIN_PATH . 'includes/class-glo-frontend-hooks.php';
    }

    /**
     * Initialize the classes.
     */
    private function initializeClasses()
    {
        new GLO_AdminSettings($this->plugin_name, $this->version);
        new GLO_GoogleAuth($this->plugin_name, $this->version);
        new GLO_FrontendHooks($this->plugin_name, $this->version);
    }

    /**
     * Define additional hooks for plugin functionality.
     */
    private function defineHooks()
    {
        add_action('show_user_profile', [$this, 'addCustomUserProfileFields']);
        add_action('edit_user_profile', [$this, 'addCustomUserProfileFields']);

        add_filter('get_avatar', [$this, 'useGoogleProfilePicture'], 10, 6);

        add_action('init', [$this, 'securityEnhancements']);

        add_filter('rest_authentication_errors', [$this, 'restrictRestApiAccess']);
    }

    /**
     * Add custom fields to user profile showing Google information.
     */
    public function addCustomUserProfileFields($user)
    {
        $google_picture = get_user_meta($user->ID, 'google_profile_picture', true);

        if ($google_picture) {
?>
            <h3><?php esc_html_e('Google Account Information', 'google-login-only'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e('Google Profile Picture', 'google-login-only'); ?></label></th>
                    <td>
                        <img src="<?php echo esc_url($google_picture); ?>" alt="<?php esc_attr_e('Google Profile Picture', 'google-login-only'); ?>" style="width: 96px; height: 96px; border-radius: 50%; object-fit: cover;">
                        <p class="description"><?php esc_html_e('This profile picture is synced from your Google account.', 'google-login-only'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e('Authentication Method', 'google-login-only'); ?></label></th>
                    <td>
                        <span style="display: inline-flex; align-items: center; gap: 8px; background: #4285F4; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                            </svg>
                            <?php esc_html_e('Google Sign-In', 'google-login-only'); ?>
                        </span>
                        <p class="description"><?php esc_html_e('This user authenticates via Google OAuth.', 'google-login-only'); ?></p>
                    </td>
                </tr>
            </table>
<?php
        }
    }

    /**
     * Use Google profile picture if available.
     */
    public function useGoogleProfilePicture($avatar, $id_or_email, $size, $default, $alt, $args)
    {
        // Handle different input types more robustly
        $user_id = 0;

        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_object($id_or_email)) {
            if (isset($id_or_email->user_id)) {
                $user_id = (int) $id_or_email->user_id;
            } elseif (isset($id_or_email->ID)) {
                $user_id = (int) $id_or_email->ID;
            }
        } elseif (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            $user_id = $user ? $user->ID : 0;
        }

        if (!$user_id || $user_id <= 0) {
            return $avatar;
        }

        $google_picture = get_user_meta($user_id, 'google_profile_picture', true);

        if ($google_picture && filter_var($google_picture, FILTER_VALIDATE_URL)) {
            $avatar = sprintf(
                '<img alt="%s" src="%s" class="avatar avatar-%d photo" height="%d" width="%d" loading="lazy" decoding="async" />',
                esc_attr($alt),
                esc_url($google_picture),
                esc_attr($size),
                esc_attr($size),
                esc_attr($size)
            );
        }

        return $avatar;
    }

    /**
     * Enhance security by disabling various WordPress features that could be exploited.
     */
    public function securityEnhancements()
    {
        add_filter('xmlrpc_enabled', '__return_false');

        remove_action('wp_head', 'wp_generator');

        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }

        // Block access to sensitive files
        $this->blockSensitiveFiles();
    }

    /**
     * Block direct access to sensitive files.
     */
    private function blockSensitiveFiles()
    {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $sensitive_files = [
            'wp-config.php',
            'wp-config-sample.php',
            '.htaccess',
            'readme.html',
            'readme.txt',
            'license.txt'
        ];

        foreach ($sensitive_files as $file) {
            if (strpos($request_uri, $file) !== false) {
                status_header(403);
                die(esc_html__('Access denied', 'google-login-only'));
            }
        }
    }

    /**
     * Restrict REST API access to authenticated users only.
     */
    public function restrictRestApiAccess($result)
    {
        if (!empty($result)) {
            return $result;
        }

        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                esc_html__('You are not currently logged in.', 'google-login-only'),
                array('status' => 401)
            );
        }

        return $result;
    }

    /**
     * Get plugin information for debugging.
     */
    public function getPluginInfo()
    {
        $settings = get_option('glo_settings', []);

        return [
            'version' => $this->version,
            'client_id_configured' => !empty($settings['client_id']),
            'client_secret_configured' => !empty($settings['client_secret']),
            'pending_users_count' => count($settings['allowed_users'] ?? []),
            'wp_users_count' => count(get_users()),
            'callback_urls' => [
                'oauth' => home_url('?action=google_login_callback'),
                'one_tap' => home_url('?action=google_one_tap_callback')
            ]
        ];
    }

    /**
     * Check if the plugin is properly configured.
     */
    public function isConfigured()
    {
        $settings = get_option('glo_settings', []);
        return !empty($settings['client_id']) && !empty($settings['client_secret']);
    }

    /**
     * Get the plugin name.
     */
    public function getPluginName()
    {
        return $this->plugin_name;
    }

    /**
     * Get the plugin version.
     */
    public function getVersion()
    {
        return $this->version;
    }
}
