<?php
namespace SparxstarAiMastering;
/**
 * Plugin Name:       AI Mastering Integration
 * Plugin URI:        https://yourwebsite.com/
 * Description:       Integrates AI Mastering SDK with WordPress CPTs.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://yourwebsite.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-mastering-integration
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'AIM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader (if using Composer)
if ( file_exists( AIM_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once AIM_PLUGIN_DIR . 'vendor/autoload.php';
}

// Include core files
require_once AIM_PLUGIN_DIR . 'includes/class-aim-api-handler.php';
require_once AIM_PLUGIN_DIR . 'includes/class-aim-cpt-hooks.php';
require_once AIM_PLUGIN_DIR . 'includes/class-aim-admin-display.php';
require_once AIM_PLUGIN_DIR . 'includes/class-aim-cron.php';

/**
 * Initialize the plugin.
 */
function aim_init() {
    AIM_API_Handler::instance();
    AIM_CPT_Hooks::instance();
    AIM_Admin_Display::instance();
    AIM_Cron::instance(); // Initialize cron tasks
}
add_action( 'plugins_loaded', 'aim_init' );

/**
 * Activation hook.
 */
function aim_activate() {
    AIM_Cron::schedule_events();
}
register_activation_hook( __FILE__, 'aim_activate' );

/**
 * Deactivation hook.
 */
function aim_deactivate() {
    AIM_Cron::unschedule_events();
}
register_deactivation_hook( __FILE__, 'aim_deactivate' );

// Plugin settings page (optional, for API key etc.)
// add_action( 'admin_menu', 'aim_add_admin_menu' );
// function aim_add_admin_menu() { ... }
// function aim_settings_page_html() { ... }
// add_action( 'admin_init', 'aim_settings_init' );
// function aim_settings_init() { ... }