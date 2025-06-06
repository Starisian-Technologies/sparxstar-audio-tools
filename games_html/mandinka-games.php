<?php
namespace MandinkaGames;

/**
 * Plugin Name:       Mandinka Flashcards
 * Plugin URI:        https://example.com/plugins/mandinka-flashcards/
 * Description:       A flashcard quiz for learning Mandinka, implemented as a shortcode.
 * Version:           1.0.0
 * Author:            Starisian Technologies (Max Barrett)
 * Author URI:        https://starisian.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mandinka-games
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MANDINKA_GAMES_PATH', plugin_dir_path( __FILE__ ) );
define( 'MANDINKA_GAMES_URL', plugin_dir_url( __FILE__ ) );
define( 'MANDINKA_GAMES_VERSION', '1.0.0' );

final class MandinkaGames {
    private string $pluginPath;
    private string $pluginUrl;
    private string $version;
    private static ?MandinkaGames $instance = null;
    public $flashcards = null;

    public function __construct() {
        $this->pluginPath = MANDINKA_GAMES_PATH;
        $this->pluginUrl  = MANDINKA_GAMES_URL;
        $this->version    = MANDINKA_GAMES_VERSION;

        $this->loadDependencies();
        $this->flashcards = new \MandinkaGames\MandinkaFlashcardsShortcode( $this->pluginUrl, $this->version );
    }

    public static function getInstance(): MandinkaGames {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadDependencies(): void {
        $file = $this->pluginPath . 'includes/mandinka-flashcards-shortcode.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }

    public static function activate(): void {
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    public static function uninstall(): void {
        // Cleanup logic if needed
    }
}

// Correct class used here — MandinkaGames, not MandinkaFlashcards
register_activation_hook( __FILE__, array( MandinkaGames::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( MandinkaGames::class, 'deactivate' ) );
register_uninstall_hook( __FILE__, array( MandinkaGames::class, 'uninstall' ) );

// Run the plugin
add_action( 'plugins_loaded', function () {
    MandinkaGames::getInstance();
});
