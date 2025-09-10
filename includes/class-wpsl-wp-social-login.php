<?php

class WPSL_WpSocialLogin
{

    protected $plugin_name;
    protected $version;

    public function __construct()
    {
        $this->plugin_name = 'wp-social-login';
        $this->version = WPSL_VERSION;
    }

    public function run()
    {
        $this->loadDependencies();
        $this->initializeClasses();
        $this->defineHooks();
    }

    private function loadDependencies()
    {
        require_once WPSL_PLUGIN_PATH . 'includes/class-wpsl-admin-settings.php';
        require_once WPSL_PLUGIN_PATH . 'includes/class-wpsl-google-auth.php';
        require_once WPSL_PLUGIN_PATH . 'includes/class-wpsl-frontend-hooks.php';
        require_once WPSL_PLUGIN_PATH . 'includes/class-wpsl-error-handler.php';
    }

    private function initializeClasses()
    {
        new WPSL_AdminSettings($this->plugin_name, $this->version);
        new WPSL_GoogleAuth($this->plugin_name, $this->version);
        new WPSL_FrontendHooks($this->plugin_name, $this->version);
    }

    private function defineHooks()
    {
        add_action('show_user_profile', [$this, 'addCustomUserProfileFields']);
        add_action('edit_user_profile', [$this, 'addCustomUserProfileFields']);
        add_filter('get_avatar', [$this, 'useGoogleProfilePicture'], 10, 6);
        add_action('init', [$this, 'securityEnhancements']);
        add_filter('rest_authentication_errors', [$this, 'restrictRestApiAccess']);
    }

    public function addCustomUserProfileFields($user)
    {
        if (get_user_meta($user->ID, 'google_profile_picture', true)) {
?>
            <h3><?php esc_html_e('Google Account Information', 'wp-social-login'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e('Google Profile Picture', 'wp-social-login'); ?></label></th>
                    <td>
                        <img src="<?php echo esc_url(get_user_meta($user->ID, 'google_profile_picture', true)); ?>" alt="<?php esc_attr_e('Google Profile Picture', 'wp-social-login'); ?>" class="wpsl-profile-picture">
                        <p class="description"><?php esc_html_e('This profile picture is synced from your Google account.', 'wp-social-login'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e('Authentication Method', 'wp-social-login'); ?></label></th>
                    <td>
                        <span class="wpsl-auth-badge">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                            </svg>
                            <?php esc_html_e('Google Sign-In', 'wp-social-login'); ?>
                        </span>
                        <p class="description"><?php esc_html_e('This user authenticates via Google OAuth.', 'wp-social-login'); ?></p>
                    </td>
                </tr>
            </table>
<?php
        }
    }

    public function useGoogleProfilePicture($avatar, $id_or_email, $size, $default, $alt, $args)
    {
        $user_id = 0;
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_object($id_or_email) && isset($id_or_email->user_id)) {
            $user_id = (int) $id_or_email->user_id;
        } elseif (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            $user_id = $user ? $user->ID : 0;
        }

        if ($user_id > 0) {
            $google_picture = get_user_meta($user_id, 'google_profile_picture', true);
            if ($google_picture && filter_var($google_picture, FILTER_VALIDATE_URL)) {
                return sprintf(
                    '<img alt="%s" src="%s" class="avatar avatar-%d photo" height="%d" width="%d" loading="lazy" decoding="async" />',
                    esc_attr($alt),
                    esc_url($google_picture),
                    esc_attr($size),
                    esc_attr($size),
                    esc_attr($size)
                );
            }
        }
        return $avatar;
    }

    public function securityEnhancements()
    {
        $settings = get_option('wpsl_settings', []);
        $security = $settings['security_features'] ?? [];

        if (!empty($security['disable_xmlrpc'])) {
            add_filter('xmlrpc_enabled', '__return_false');
        }
        if (!empty($security['hide_wp_version'])) {
            remove_action('wp_head', 'wp_generator');
        }
        if (!empty($security['disable_file_editing'])) {
            if (!defined('DISALLOW_FILE_EDIT')) {
                define('DISALLOW_FILE_EDIT', true);
            }
        }
        if (!empty($security['block_sensitive_files'])) {
            $this->blockSensitiveFiles();
        }
    }

    /**
     * Important Notice:
     * This check can be easily bypassed. For robust security, it is necessary to handle this at the server level (e.g., via Nginx, Caddy, or Apache configurations). This feature provides a basic layer of protection.
     */
    private function blockSensitiveFiles()
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $sensitive_files = ['wp-config.php', '.htaccess', 'readme.html'];
        foreach ($sensitive_files as $file) {
            if (stripos($request_uri, $file) !== false) {
                status_header(403);
                die(esc_html__('Access Denied', 'wp-social-login'));
            }
        }
    }

    public function restrictRestApiAccess($result)
    {
        $settings = get_option('wpsl_settings', []);
        if (empty($settings['security_features']['restrict_rest_api'])) {
            return $result;
        }

        if (!empty($result) || is_user_logged_in()) {
            return $result;
        }
        return new WP_Error('rest_not_logged_in', esc_html__('You are not currently logged in.', 'wp-social-login'), ['status' => 401]);
    }
}
