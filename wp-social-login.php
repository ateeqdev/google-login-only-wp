<?php

/**
 * Plugin Name:       WP Social Login
 * Plugin URI:        https://hardtoskip.com/
 * Description:       Replaces standard WordPress password authentication with Social Sign-In (currently Google) and One Tap. Features beautiful UI, automatic user management, and enhanced security options. Born from necessity after a successful brute-force attack on hardtoskip, this plugin enforces Google-only authentication to keep your site secure.
 * Version:           2.0.1
 * Author:            HardToSkip
 * Author URI:        https://hardtoskip.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-social-login
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Tested up to:      6.4
 * Requires PHP:      7.4
 * Network:           false
 * 
 * @package WPSL_WpSocialLogin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Plugin version.
 */
define('WPSL_VERSION', '2.0.1');

/**
 * Plugin paths and URLs.
 */
define('WPSL_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WPSL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPSL_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Load the main plugin class.
 */
require_once WPSL_PLUGIN_PATH . 'includes/class-wpsl-wp-social-login.php';

/**
 * Plugin activation hook.
 * Sets up default options and creates initial configuration.
 */
function wpsl_activate()
{
    $default_options = [
        'client_id' => '',
        'client_secret' => '',
        'allowed_users' => [],
        'one_tap_homepage' => false,
        'security_features' => [
            'disable_xmlrpc' => true,
            'disable_file_editing' => true,
            'hide_wp_version' => false,
            'restrict_rest_api' => true,
            'block_sensitive_files' => true,
        ]
    ];

    if (!get_option('wpsl_settings')) {
        add_option('wpsl_settings', $default_options);
    }

    add_option('wpsl_show_setup_notice', true);

    if (function_exists('error_log')) {
        error_log('WP Social Login: Plugin activated - Version ' . WPSL_VERSION);
    }
}

/**
 * Plugin deactivation hook.
 * Cleans up temporary data but preserves settings.
 */
function wpsl_deactivate()
{
    delete_option('wpsl_show_setup_notice');

    if (function_exists('error_log')) {
        error_log('WP Social Login: Plugin deactivated');
    }
}

/**
 * Plugin uninstall hook (defined in separate uninstall.php file).
 * This would completely remove all plugin data if the user chooses to delete the plugin.
 */
register_uninstall_hook(__FILE__, 'wpsl_uninstall');
function wpsl_uninstall()
{
    delete_option('wpsl_settings');
    delete_option('wpsl_show_setup_notice');
    delete_option('wpsl_wizard_progress');

    $users = get_users(['meta_key' => 'google_profile_picture']);
    foreach ($users as $user) {
        delete_user_meta($user->ID, 'google_profile_picture');
    }
}

/**
 * Show admin notice for first-time setup and important warnings.
 */
function wpsl_admin_notices()
{
    $current_screen = get_current_screen();

    if (get_option('wpsl_show_setup_notice')) {
        $settings_url = admin_url('admin.php?page=wp-social-login');
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>' . esc_html__('WP Social Login:', 'wp-social-login') . '</strong> ';
        printf(
            esc_html__('Please %1$sconfigure your Google OAuth credentials%2$s to enable Google Sign-In. %3$sThis Plugin was created by HardToSkip.com which generates viral social media posts for businesses.%4$s.', 'wp-social-login'),
            '<a href="' . esc_url($settings_url) . '">',
            '</a>',
            '<a href="https://hardtoskip.com/" target="_blank">',
            '</a>'
        );
        echo '</p>';
        echo '</div>';

        if (!$current_screen || $current_screen->id !== 'toplevel_page_wp-social-login') {
            delete_option('wpsl_show_setup_notice');
        }
    }

    $settings = get_option('wpsl_settings', []);
    if (empty($settings['client_id']) || empty($settings['client_secret'])) {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>' . esc_html__('Warning:', 'wp-social-login') . '</strong> ' . esc_html__('WP Social Login is not fully configured. Users may not be able to log in until you complete the setup.', 'wp-social-login') . '</p>';
        echo '</div>';
    }

    $security_features = $settings['security_features'] ?? [];
    $any_security_enabled = array_filter($security_features);
    if (empty($any_security_enabled) && $current_screen && $current_screen->id === 'toplevel_page_wp-social-login') {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>' . esc_html__('Security Note:', 'wp-social-login') . '</strong> ' . esc_html__('Consider enabling some security features below to protect against common attack vectors.', 'wp-social-login') . '</p>';
        echo '</div>';
    }
}

/**
 * Add plugin action links in the plugins list.
 */
function wpsl_plugin_action_links($links)
{
    $settings_link = '<a href="' . admin_url('admin.php?page=wp-social-login') . '">' . esc_html__('Settings', 'wp-social-login') . '</a>';
    $creator_link = '<a href="https://linkedin.com/in/ateeqdev" target="_blank">' . esc_html__('Plugin Creator', 'wp-social-login') . '</a>';

    array_unshift($links, $creator_link);
    array_unshift($links, $settings_link);

    return $links;
}

/**
 * Add plugin meta links in the plugins list.
 */
function wpsl_plugin_row_meta($links, $file)
{
    if (WPSL_PLUGIN_BASENAME === $file) {
        $meta_links = [
            'developer' => '<a href="https://linkedin.com/in/ateeqdev" target="_blank">' . esc_html__('Contact Developer', 'wp-social-login') . '</a>',
            'rate' => '<a href="https://wordpress.org/plugins/wp-social-login/#reviews" target="_blank" rel="nofollow">' . esc_html__('Rate Plugin', 'wp-social-login') . '</a>',
        ];
        $links = array_merge($links, $meta_links);
    }
    return $links;
}

/**
 * Load plugin textdomain for translations.
 */
function wpsl_load_plugin_textdomain()
{
    load_plugin_textdomain(
        'wp-social-login',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}

/**
 * Check if current user can manage the plugin.
 */
function wpsl_current_user_can_manage()
{
    return current_user_can('manage_options');
}

/**
 * Get plugin information for debugging purposes.
 */
function wpsl_get_plugin_info()
{
    if (!wpsl_current_user_can_manage()) {
        return false;
    }

    $settings = get_option('wpsl_settings', []);

    return [
        'version' => WPSL_VERSION,
        'php_version' => PHP_VERSION,
        'wp_version' => get_bloginfo('version'),
        'client_id_configured' => !empty($settings['client_id']),
        'client_secret_configured' => !empty($settings['client_secret']),
        'pending_users_count' => count($settings['allowed_users'] ?? []),
        'wp_users_count' => count(get_users()),
        'one_tap_homepage_enabled' => !empty($settings['one_tap_homepage']),
        'security_features_enabled' => count(array_filter($settings['security_features'] ?? [])),
        'callback_urls' => [
            'oauth' => home_url('?action=google_login_callback'),
            'one_tap' => home_url('?action=google_one_tap_callback')
        ]
    ];
}

/**
 * AJAX handler for plugin diagnostics (admin only).
 */
function wpsl_ajax_diagnostics()
{
    check_ajax_referer('wpsl_admin_nonce', 'nonce');

    if (!wpsl_current_user_can_manage()) {
        wp_send_json_error(esc_html__('Insufficient permissions', 'wp-social-login'));
    }

    wp_send_json_success(wpsl_get_plugin_info());
}

/**
 * Register AJAX handlers.
 */
function wpsl_register_ajax_handlers()
{
    add_action('wp_ajax_wpsl_diagnostics', 'wpsl_ajax_diagnostics');
}

/**
 * Add admin bar menu for quick access (for admins only).
 */
function wpsl_admin_bar_menu($wp_admin_bar)
{
    if (!wpsl_current_user_can_manage() || !is_admin_bar_showing()) {
        return;
    }

    $settings = get_option('wpsl_settings', []);
    $is_configured = !empty($settings['client_id']) && !empty($settings['client_secret']);

    $wp_admin_bar->add_node([
        'id' => 'wpsl_quick_access',
        'title' => esc_html__('Social Login', 'wp-social-login') . ($is_configured ? '' : ' ⚠'),
        'href' => admin_url('admin.php?page=wp-social-login'),
        'meta' => [
            'title' => $is_configured ? esc_html__('WP Social Login Settings', 'wp-social-login') : esc_html__('WP Social Login - Configuration Required', 'wp-social-login')
        ]
    ]);
}

/**
 * Handle plugin errors gracefully.
 */
function wpsl_handle_fatal_error()
{
    $error = error_get_last();

    if ($error && strpos($error['file'], WPSL_PLUGIN_PATH) !== false) {
        if (function_exists('error_log')) {
            error_log(
                sprintf(
                    '[WP Social Login] Fatal error: %s in %s on line %d',
                    $error['message'],
                    $error['file'],
                    $error['line']
                )
            );
        }

        set_transient('wpsl_plugin_last_error', $error, 60);
    }
}

/**
 * Show an admin notice if the plugin caused a fatal error.
 */
function wpsl_show_admin_error_notice()
{
    if (get_transient('wpsl_plugin_last_error')) {
?>
        <div class="notice notice-error is-dismissible">
            <p><strong>WP Social Login:</strong>
                A fatal error was detected and logged. Please check your PHP error log for details.</p>
        </div>
        <?php
        delete_transient('wpsl_plugin_last_error');
    }
}

/**
 * Initialize and run the plugin.
 */
function run_wp_social_login()
{
    try {
        $plugin = new WPSL_WpSocialLogin();
        $plugin->run();
    } catch (Exception $e) {
        // Log the error for debugging
        if (function_exists('error_log')) {
            error_log(
                sprintf(
                    '[WP Social Login] Initialization error: %s in %s on line %d',
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                )
            );
        }

        set_transient('wpsl_plugin_init_error', $e->getMessage(), 60);

        add_action('admin_notices', function () {
            if ($error_message = get_transient('wpsl_plugin_init_error')) {
        ?>
                <div class="notice notice-error is-dismissible">
                    <p>
                        <strong><?php echo esc_html__('WP Social Login Error:', 'wp-social-login'); ?></strong>
                        <?php echo esc_html($error_message); ?>
                    </p>
                    <p><?php echo esc_html__('Please check your PHP error log for details. The plugin may not function correctly until this issue is resolved.', 'wp-social-login'); ?></p>
                </div>
<?php
                delete_transient('wpsl_plugin_init_error');
            }
        });
    }
}


// Register hooks
register_activation_hook(__FILE__, 'wpsl_activate');
register_deactivation_hook(__FILE__, 'wpsl_deactivate');

// Admin hooks
add_action('admin_notices', 'wpsl_admin_notices');
add_action('admin_bar_menu', 'wpsl_admin_bar_menu', 100);
add_action('init', 'wpsl_register_ajax_handlers');

// Plugin list hooks
add_filter('plugin_action_links_' . WPSL_PLUGIN_BASENAME, 'wpsl_plugin_action_links');
add_filter('plugin_row_meta', 'wpsl_plugin_row_meta', 10, 2);

// Internationalization
add_action('plugins_loaded', 'wpsl_load_plugin_textdomain');

// Error handling
register_shutdown_function('wpsl_handle_fatal_error');
add_action('admin_notices', 'wpsl_show_admin_error_notice');

// Start the plugin
run_wp_social_login();
