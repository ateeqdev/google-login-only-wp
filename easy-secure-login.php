<?php

/**
 * Plugin Name:       Easy Secure Login
 * Description:       Replaces standard WordPress password authentication with Social Sign-In (currently Google) and One Tap. Features beautiful UI, automatic user management, and enhanced security options. Born from necessity after a successful brute-force attack on hardtoskip, this plugin enforces Google-only authentication to keep your site secure.
 * Version:           2.1.1
 * Author:            HardToSkip
 * Author URI:        https://hardtoskip.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       easy-secure-login
 * Domain Path:       /languages/
 * Requires at least: 5.0
 * Tested up to:      6.8
 * Requires PHP:      7.4
 * 
 * @package ESL_EasySecureLogin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Plugin version.
 */
define('ESL_VERSION', '2.1.1');

/**
 * Plugin paths and URLs.
 */
define('ESL_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ESL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ESL_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Load the main plugin class.
 */
require_once ESL_PLUGIN_PATH . 'includes/class-esl-easy-secure-login.php';

/**
 * Plugin activation hook.
 * Sets up default options and creates initial configuration.
 */
function esl_activate()
{
    $default_options = [
        'client_id' => '',
        'client_secret' => '',
        'allowed_users' => [],
        'one_tap_homepage' => false,
        'allow_new_signups' => false,
        'default_signup_role' => 'subscriber',
        'security_features' => [
            'disable_xmlrpc' => true,
            'disable_file_editing' => true,
            'hide_wp_version' => false,
            'restrict_rest_api' => true,
            'block_sensitive_files' => true,
        ]
    ];

    if (!get_option('esl_settings')) {
        add_option('esl_settings', $default_options);
    }

    add_option('esl_show_setup_notice', true);
}

/**
 * Plugin deactivation hook.
 * Cleans up temporary data but preserves settings.
 */
function esl_deactivate()
{
    delete_option('esl_show_setup_notice');
}

/**
 * Plugin uninstall hook (defined in separate uninstall.php file).
 */
register_uninstall_hook(__FILE__, 'esl_uninstall');
function esl_uninstall()
{
    delete_option('esl_settings');
    delete_option('esl_show_setup_notice');
    delete_option('esl_wizard_progress');

    $users = get_users([
        'meta_key' => 'google_profile_picture',
        'meta_value'   => '',
        'meta_compare' => '!=',
    ]);
    foreach ($users as $user) {
        delete_user_meta($user->ID, 'google_profile_picture');
    }
}

/**
 * Show admin notice for first-time setup and important warnings.
 */
function esl_admin_notices()
{
    $current_screen = get_current_screen();

    if (get_option('esl_show_setup_notice') && $current_screen && $current_screen->id !== 'toplevel_page_easy-secure-login') {
        $settings_url = admin_url('admin.php?page=easy-secure-login');
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>' . esc_html__('Easy Secure Login:', 'easy-secure-login') . '</strong> ';
        printf(
            /* translators: 1: Opening anchor tag, 2: Closing anchor tag */
            esc_html__('Please %1$sconfigure your Google OAuth credentials%2$s to enable secure sign-in.', 'easy-secure-login'),
            '<a href="' . esc_url($settings_url) . '">',
            '</a>'
        );
        echo '</p>';
        echo '</div>';
    }

    $settings = get_option('esl_settings', []);
    if (empty($settings['client_id']) || empty($settings['client_secret'])) {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>' . esc_html__('Warning:', 'easy-secure-login') . '</strong> ' . esc_html__('Easy Secure Login is not fully configured. Users may not be able to log in until you complete the setup.', 'easy-secure-login') . '</p>';
        echo '</div>';
    }

    if ($current_screen && $current_screen->id === 'toplevel_page_easy-secure-login') {
        if (!empty($settings['allow_new_signups'])) {
            $default_role = $settings['default_signup_role'] ?? 'subscriber';
            $is_risky_role = in_array($default_role, ['author', 'editor', 'administrator']);
            $notice_class = $is_risky_role ? 'error' : 'warning';
            $message = $is_risky_role
                ? __('New user sign-ups are enabled with elevated permissions. Monitor your site for unauthorized content.', 'easy-secure-login')
                : __('New user sign-ups are enabled. Monitor your user registrations for potential spam accounts.', 'easy-secure-login');

            echo '<div class="notice notice-' . esc_attr($notice_class) . '">';
            echo '<p><strong>' . esc_html__('Security Notice:', 'easy-secure-login') . '</strong> ' . esc_html($message) . '</p>';
            echo '</div>';
        }
    }
}

/**
 * Add plugin action links in the plugins list.
 */
function esl_plugin_action_links($links)
{
    $settings_link = '<a href="' . admin_url('admin.php?page=easy-secure-login') . '">' . esc_html__('Settings', 'easy-secure-login') . '</a>';

    array_unshift($links, $settings_link);

    return $links;
}

/**
 * Add plugin meta links in the plugins list.
 */
function esl_plugin_row_meta($links, $file)
{
    if (ESL_PLUGIN_BASENAME === $file) {
        $meta_links = [
            'developer' => '<a href="https://linkedin.com/in/ateeqdev" target="_blank">' . esc_html__('Plugin Creator', 'easy-secure-login') . '</a>',
            'rate' => '<a href="https://wordpress.org/support/plugin/easy-secure-login/reviews/?filter=5#new-post" target="_blank" rel="nofollow">' . esc_html__('Rate Plugin ★★★★★', 'easy-secure-login') . '</a>',
        ];
        $links = array_merge($links, $meta_links);
    }
    return $links;
}


/**
 * Handle plugin errors gracefully.
 */
function esl_handle_fatal_error()
{
    $error = error_get_last();

    if ($error && strpos($error['file'], ESL_PLUGIN_PATH) !== false) {
        set_transient('esl_plugin_last_error', $error, 60);
    }
}

/**
 * Show an admin notice if the plugin caused a fatal error.
 */
function esl_show_admin_error_notice()
{
    if (get_transient('esl_plugin_last_error')) {
?>
        <div class="notice notice-error is-dismissible">
            <p><strong><?php esc_html_e('Easy Secure Login Error:', 'easy-secure-login'); ?></strong>
                <?php esc_html_e('A fatal error was detected and logged. Please check your PHP error log for details.', 'easy-secure-login'); ?></p>
        </div>
        <?php
        delete_transient('esl_plugin_last_error');
    }
}

/**
 * Initialize and run the plugin.
 */
function run_easy_secure_login()
{
    try {
        $plugin = new ESL_EasySecureLogin();
        $plugin->run();
    } catch (Exception $e) {
        add_action('admin_notices', function () use ($e) {
        ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php echo esc_html__('Easy Secure Login Error:', 'easy-secure-login'); ?></strong>
                    <?php echo esc_html($e->getMessage()); ?>
                </p>
            </div>
<?php
        });
    }
}

// Register hooks
register_activation_hook(__FILE__, 'esl_activate');
register_deactivation_hook(__FILE__, 'esl_deactivate');

// Admin hooks
add_action('admin_notices', 'esl_admin_notices');

// Plugin list hooks
add_filter('plugin_action_links_' . ESL_PLUGIN_BASENAME, 'esl_plugin_action_links');
add_filter('plugin_row_meta', 'esl_plugin_row_meta', 10, 2);

// Error handling
register_shutdown_function('esl_handle_fatal_error');
add_action('admin_notices', 'esl_show_admin_error_notice');

// Start the plugin
run_easy_secure_login();
