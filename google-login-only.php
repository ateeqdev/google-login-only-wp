<?php

/**
 * Plugin Name:       Google Login Only
 * Plugin URI:        https://hardtoskip.com/
 * Description:       Replaces standard WordPress password authentication with Google Sign-In and One Tap. Features beautiful UI, automatic user management, and enhanced security options. Born from necessity after a successful brute-force attack on hardtoskip, this plugin enforces Google-only authentication to keep your site secure.
 * Version:           2.0.0
 * Author:            HardToSkip
 * Author URI:        https://hardtoskip.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       google-login-only
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Tested up to:      6.4
 * Requires PHP:      7.4
 * Network:           false
 * 
 * @package GLO_GoogleLoginOnly
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Plugin version.
 */
define('GLO_VERSION', '2.0.0');

/**
 * Plugin paths and URLs.
 */
define('GLO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GLO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GLO_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Load the main plugin class.
 */
require_once GLO_PLUGIN_PATH . 'includes/class-glo-google-login-only.php';

/**
 * Plugin activation hook.
 * Sets up default options and creates initial configuration.
 */
function glo_activate()
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

    if (!get_option('glo_settings')) {
        add_option('glo_settings', $default_options);
    }

    add_option('glo_show_setup_notice', true);

    if (function_exists('error_log')) {
        error_log('Google Login Only: Plugin activated - Version ' . GLO_VERSION);
    }
}

/**
 * Plugin deactivation hook.
 * Cleans up temporary data but preserves settings.
 */
function glo_deactivate()
{
    delete_option('glo_show_setup_notice');

    if (function_exists('error_log')) {
        error_log('Google Login Only: Plugin deactivated');
    }
}

/**
 * Plugin uninstall hook (defined in separate uninstall.php file).
 * This would completely remove all plugin data if the user chooses to delete the plugin.
 */
register_uninstall_hook(__FILE__, 'glo_uninstall');
function glo_uninstall()
{
    delete_option('glo_settings');
    delete_option('glo_show_setup_notice');

    $users = get_users(['meta_key' => 'google_profile_picture']);
    foreach ($users as $user) {
        delete_user_meta($user->ID, 'google_profile_picture');
    }
}

/**
 * Show admin notice for first-time setup and important warnings.
 */
function glo_admin_notices()
{
    $current_screen = get_current_screen();

    if (get_option('glo_show_setup_notice')) {
        $settings_url = admin_url('options-general.php?page=google-login-only');
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>' . esc_html__('Google Login Only:', 'google-login-only') . '</strong> ';
        printf(
            esc_html__('Please %1$sconfigure your Google OAuth credentials%2$s to enable Google Sign-In. %3$sThis Plugin was created by HardToSkip.com which generates viral social media posts for businesses.%4$s.', 'google-login-only'),
            '<a href="' . esc_url($settings_url) . '">',
            '</a>',
            '<a href="https://hardtoskip.com/" target="_blank">',
            '</a>'
        );
        echo '</p>';
        echo '</div>';

        if (!$current_screen || $current_screen->id !== 'settings_page_google-login-only') {
            delete_option('glo_show_setup_notice');
        }
    }

    $settings = get_option('glo_settings', []);
    if (empty($settings['client_id']) || empty($settings['client_secret'])) {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>' . esc_html__('Warning:', 'google-login-only') . '</strong> ' . esc_html__('Google Login Only is not fully configured. Users may not be able to log in until you complete the setup.', 'google-login-only') . '</p>';
        echo '</div>';
    }

    $security_features = $settings['security_features'] ?? [];
    $any_security_enabled = array_filter($security_features);
    if (empty($any_security_enabled) && $current_screen && $current_screen->id === 'settings_page_google-login-only') {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>' . esc_html__('Security Note:', 'google-login-only') . '</strong> ' . esc_html__('Consider enabling some security features below to protect against common attack vectors.', 'google-login-only') . '</p>';
        echo '</div>';
    }
}

/**
 * Add plugin action links in the plugins list.
 */
function glo_plugin_action_links($links)
{
    $settings_link = '<a href="' . admin_url('options-general.php?page=google-login-only') . '">' . esc_html__('Settings', 'google-login-only') . '</a>';
    $creator_link = '<a href="https://linkedin.com/in/ateeqdev" target="_blank">' . esc_html__('Plugin Creator', 'google-login-only') . '</a>';

    array_unshift($links, $creator_link);
    array_unshift($links, $settings_link);

    return $links;
}

/**
 * Add plugin meta links in the plugins list.
 */
function glo_plugin_row_meta($links, $file)
{
    if (GLO_PLUGIN_BASENAME === $file) {
        $meta_links = [
            'developer' => '<a href="https://linkedin.com/in/ateeqdev" target="_blank">' . esc_html__('Contact Developer', 'google-login-only') . '</a>',
            'rate' => '<a href="https://wordpress.org/plugins/google-login-only/#reviews" target="_blank" rel="nofollow">' . esc_html__('Rate Plugin', 'google-login-only') . '</a>',
        ];
        $links = array_merge($links, $meta_links);
    }
    return $links;
}

/**
 * Load plugin textdomain for translations.
 */
function glo_load_plugin_textdomain()
{
    load_plugin_textdomain(
        'google-login-only',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}

/**
 * Check if current user can manage the plugin.
 */
function glo_current_user_can_manage()
{
    return current_user_can('manage_options');
}

/**
 * Get plugin information for debugging purposes.
 */
function glo_get_plugin_info()
{
    if (!glo_current_user_can_manage()) {
        return false;
    }

    $settings = get_option('glo_settings', []);

    return [
        'version' => GLO_VERSION,
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
function glo_ajax_diagnostics()
{
    check_ajax_referer('glo_admin_nonce', 'nonce');

    if (!glo_current_user_can_manage()) {
        wp_send_json_error(esc_html__('Insufficient permissions', 'google-login-only'));
    }

    wp_send_json_success(glo_get_plugin_info());
}

/**
 * Register AJAX handlers.
 */
function glo_register_ajax_handlers()
{
    add_action('wp_ajax_glo_diagnostics', 'glo_ajax_diagnostics');
}

/**
 * Add admin bar menu for quick access (for admins only).
 */
function glo_admin_bar_menu($wp_admin_bar)
{
    if (!glo_current_user_can_manage() || !is_admin_bar_showing()) {
        return;
    }

    $settings = get_option('glo_settings', []);
    $is_configured = !empty($settings['client_id']) && !empty($settings['client_secret']);

    $wp_admin_bar->add_node([
        'id' => 'glo_quick_access',
        'title' => esc_html__('Google Login', 'google-login-only') . ($is_configured ? '' : ' ⚠'),
        'href' => admin_url('options-general.php?page=google-login-only'),
        'meta' => [
            'title' => $is_configured ? esc_html__('Google Login Only Settings', 'google-login-only') : esc_html__('Google Login Only - Configuration Required', 'google-login-only')
        ]
    ]);
}

/**
 * Handle plugin errors gracefully.
 */
function glo_handle_fatal_error()
{
    $error = error_get_last();
    if ($error && strpos($error['file'], GLO_PLUGIN_PATH) !== false) {
        if (function_exists('error_log')) {
            error_log('Google Login Only Fatal Error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
        }

        if (function_exists('deactivate_plugins')) {
            deactivate_plugins(GLO_PLUGIN_BASENAME);
        }
    }
}

/**
 * Initialize and run the plugin.
 */
function run_google_login_only()
{
    try {
        $plugin = new GLO_GoogleLoginOnly();
        $plugin->run();
    } catch (Exception $e) {
        if (function_exists('error_log')) {
            error_log('Google Login Only Error: ' . $e->getMessage());
        }

        add_action('admin_notices', function () use ($e) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>' . esc_html__('Google Login Only Error:', 'google-login-only') . '</strong> ' . esc_html($e->getMessage()) . '</p>';
            echo '<p>' . esc_html__('The plugin has been deactivated to prevent further issues.', 'google-login-only') . '</p>';
            echo '</div>';
        });

        if (function_exists('deactivate_plugins')) {
            deactivate_plugins(GLO_PLUGIN_BASENAME);
        }
        return;
    }
}

// Register hooks
register_activation_hook(__FILE__, 'glo_activate');
register_deactivation_hook(__FILE__, 'glo_deactivate');

// Admin hooks
add_action('admin_notices', 'glo_admin_notices');
add_action('admin_bar_menu', 'glo_admin_bar_menu', 100);
add_action('init', 'glo_register_ajax_handlers');

// Plugin list hooks
add_filter('plugin_action_links_' . GLO_PLUGIN_BASENAME, 'glo_plugin_action_links');
add_filter('plugin_row_meta', 'glo_plugin_row_meta', 10, 2);

// Internationalization
add_action('plugins_loaded', 'glo_load_plugin_textdomain');

// Error handling
register_shutdown_function('glo_handle_fatal_error');

// Start the plugin
run_google_login_only();
