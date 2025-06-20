<?php
namespace MandinkaGames;
// uninstall.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Check if the main plugin class and DatabaseManager class exist before trying to use them
// This is important because uninstall can be triggered even if the plugin had errors loading
// Path to DatabaseManager class file
$dbManagerClassFile = Starisian Technologies_PATH . 'src/core/DatabaseManager.php';

if (file_exists($dbManagerClassFile)) {
    require_once $dbManagerClassFile;
    if (class_exists('AiWESTAFRICA\src\core\DatabaseManager')) {
        \AiWESTAFRICA\src\core\DatabaseManager::handleUninstall();
    }
}

// Delete any options stored in wp_options
// delete_option('my_plugin_option_name_1');
// delete_option('my_plugin_option_name_2');

// Additional cleanup (e.g., remove user roles if any were created by the plugin)