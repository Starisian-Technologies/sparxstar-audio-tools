<?php
namespace SPARXSTAR;
/**
 * Plugin Name:       SPARXSTAR Audio Tools
 * Plugin URI:        https://yourwebsite.com/
 * Description:       SPARXSTAR Ai Mastering is a powerful WordPress plugin designed to enhance your audio mastering experience. It provides seamless integration with AI-driven mastering services, allowing you to achieve professional-quality audio results directly from your WordPress dashboard.
 * Version:           1.0.0
 * Author:            Starisian Technologies (Max Barrett)
 * Author URI:        https://www.starisian.com/
 * Contributor:       Max Barrett
 * License:           Proprietary
 * License URI:       
 * Text Domain:       sparxstar-audio-tools
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Tested up to:      6.4
 * GitHub Plugin URI: 
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}


if (!defined('SPARXAT_PATH')) {
	define('SPARXAT_PATH', plugin_dir_path(__FILE__));
}
if (!defined('SPARXAT_URL')) {
	define('SPARXAT_URL', plugin_dir_url(__FILE__));
}
if (!defined('SPARXAT_VERSION')) {
	define('SPARXAT_VERSION', '1.0.0'); // Match plugin header version
}

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

define('SPARX_AT_AIMASTERING_API_KEY', $_ENV['AIMASTERING_API_KEY']);
/**
 * Class SPARXSTAR
 *
 * This class serves as the main entry point for the SPARXSTAR Ai Mastering plugin.
 * It initializes the plugin, checks for compatibility, and loads necessary dependencies.
 * This class is declared as final and cannot be extended.
 *
 * @package SparxstarAudioTools
 */
final class SparxstarAudioTools
{
	/**
	 * Plugin version constant.
	 *
	 * Represents the current version of the SPARXSTAR Ai Mastering plugin.
	 *
	 * @var string
	 */
	const VERSION = SPARXAT_VERSION; // Use defined constant for consistency
	const MINIMUM_PHP_VERSION = '8.2';
	const MINIMUM_WP_VERSION = '6.4';

	private static ?SparxstarAudioTools $instance = null;
	private string $pluginPath;
	private string $pluginUrl;
	private string $version;

	private function __construct()
	{
		$this->pluginPath = SPARXAT_PATH;
		$this->pluginUrl = SPARXAT_URL;
		$this->version = self::VERSION; // Use class constant

		if (!$this->check_compatibility()) {
			add_action('admin_notices', array($this, 'admin_notice_compatibility'));
			// If compatibility check fails, we do not proceed with loading the plugin.
			return;
		}
		$this->SPARXSTAR_autoloader();
		$this->load_textdomain();
		$this->load_dependencies();

	}

	/**
	 * Retrieves the singleton instance of the AudioRecorder class.
	 *
	 * @return SparxstarAudioTools The single instance of the AudioRecorder.
	 */
	public static function get_instance(): SparxstarAudioTools
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Loads the necessary dependencies for the plugin.
	 *
	 * This method includes the autoloader and other required files for the plugin to function.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		// The SparxAT class is in the 'core' namespace based on provided context
		if(!class_exists('\SPARXSTAR\src\core\SparxAT', false)) {
			// If the main SparxAT class is not loaded, we cannot proceed.
			error_log('Sparxstar Audio Tools: Main class SparxAT not found.');
			return;
		}
		$this->SparxATPlugin = new \SPARXSTAR\src\core\SparxAT();
		$this->SparxATPlugin->init();
	}

	private function check_compatibility(): bool
	{
		if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
			return false;
		}

		global $wp_version;
		if (version_compare($wp_version, self::MINIMUM_WP_VERSION, '<')) {
			return false;
		}

		return true;
	}

	/**
	 * Displays an admin notice regarding compatibility issues.
	 *
	 * This method outputs a notice in the WordPress admin area to inform users about
	 * compatibility concerns related to the plugin or its environment.
	 *
	 * @return void
	 */
	public function admin_notice_compatibility(): void
	{
		echo '<div class="notice notice-error"><p>';
		if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
			echo esc_html__('Sparxstar Ai Mastering requires PHP version ' . self::MINIMUM_PHP_VERSION . ' or higher.', 'SparxstarSparxAT') . '<br>';
		}
		if (version_compare($GLOBALS['wp_version'], self::MINIMUM_WP_VERSION, '<')) {
			echo esc_html__('Sparxstar Ai Mastering requires WordPress version ' . self::MINIMUM_WP_VERSION . ' or higher.', 'SparxstarSparxAT');
		}
		echo '</p></div>';
	}



	private function SPARXSTAR_autoloader(): void
	{
		if (file_exists($this->pluginPath . 'src/includes/Autoloader.php')) {
			require_once $this->pluginPath . 'src/includes/Autoloader.php';
			// The Autoloader itself will need its internal baseNamespace updated
			\SPARXSTAR\src\includes\Autoloader::register(); // Assuming Autoloader class uses the new namespace
		} else {
			add_action('admin_notices', function (): void {
				echo '<div class="error"><p>' . esc_html__('Sparxstar Ai Mastering: Critical file Autoloader.php is missing.', 'SPARXSTAR') . '</p></div>';
			});
			return;
		}
	}

	private function load_textdomain(): void
	{
		load_plugin_textdomain('SPARXSTAR', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	private function __clone(): void
	{
		// Prevent cloning of the instance.
		_doing_it_wrong(__FUNCTION__, esc_html__('Cloning of this class is not allowed.', 'SPARXSTAR'), self::VERSION);
	}

	private function __wakeup(): void
	{
		// Prevent unserializing of the instance.
		_doing_it_wrong(__FUNCTION__, esc_html__('Unserializing of this class is not allowed.', 'SPARXSTAR'), self::VERSION);
	}

	private function __sleep(): array
	{
		// Prevent serialization of the instance.
		_doing_it_wrong(__FUNCTION__, esc_html__('Serialization of this class is not allowed.', 'SPARXSTAR'), self::VERSION);
		return array();
	}

	private function __destruct()
	{
		// Prevent direct destruction of the instance.
		_doing_it_wrong(__FUNCTION__, esc_html__('Direct destruction of this class is not allowed.', 'SPARXSTAR'), self::VERSION);
	}

	public function __call($name, $arguments): void
	{
		// Prevent calling non-existent methods.
		_doing_it_wrong(__FUNCTION__, esc_html__('Calling non-existent methods is not allowed.', 'SPARXSTAR'), self::VERSION);
	}


	/**
	 * Executes the main functionality of the SPARXSTAR Ai Mastering plugin.
	 *
	 * This static method is the entry point for running the plugin's core logic.
	 * It should be called to initialize and start the audio recording features.
	 *
	 * @return void
	 */
	public static function SPARXSTAR_run(): void
	{
		if (
			!isset($GLOBALS['SPARXSTAR']) ||
			!$GLOBALS['SPARXSTAR'] instanceof self
		) {
			$GLOBALS['SPARXSTAR'] = self::get_instance();
		}
		// Autoloader check can be removed here if constructor handles it sufficiently.
		// If SPARXSTAR_autoloader() in constructor fails and returns, 
		// get_instance() might not even be called if it's part of the constructor's early exit.
		// However, if an instance is created, autoloader should be loaded.
		// For robustness, you might ensure $GLOBALS['SPARXSTAR'] is fully initialized.
		$GLOBALS['SPARXSTAR']->SparxATPlugin->SparxAT_run(); // Assuming SparxAT class has a run method
	}

	public static function SPARXSTAR_activate(): void
	{
		// Schedule events for the plugin.
		\SPARXSTAR\src\includes\SparxATCron::schedule_events();
		// Flush rewrite rules to ensure custom post types and endpoints are registered.
		flush_rewrite_rules();
	}

	public static function SPARXSTAR_deactivate(): void
	{
		\SPARXSTAR\src\includes\SparxATCron::unschedule_events();
		flush_rewrite_rules();
	}

	public static function SPARXSTAR_uninstall(): void
	{
		// Optional cleanup logic for uninstall.
		// Example: delete_option('SPARXSTAR_audio_recorder_settings');
	}

}



// Use fully qualified class name for hooks
register_activation_hook(__FILE__, array('SPARXSTAR\SparxstarAudioTools', 'SPARXSTAR_activate'));
register_deactivation_hook(__FILE__, array('SPARXSTAR\SparxstarAudioTools', 'SPARXSTAR_deactivate'));
register_uninstall_hook(__FILE__, array('SPARXSTAR\SparxstarAudioTools', 'SPARXSTAR_uninstall'));
// Initialize the plugin
add_action('plugins_loaded', array('SPARXSTAR\SparxstarAudioTools', 'SPARXSTAR_run'));