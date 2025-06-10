<?php
namespace SPARXSTAR\src\core;

if ( ! defined( 'ABSPATH' ) ) exit;

class SparxATLoader {
    /**
     * The single instance of the class.
     */
    private static $instance = null;

    /**
     * Main SparxATLoader Instance.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor. Registers all hooks.
     */
    private function __construct() {
        // --- Core Plugin Hooks ---
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
    }

    /**
     * Setup the Admin Menu.
     */
    public function admin_menu() {
        add_menu_page('Sparxstar Audio Tools Universe', 'Sparxstar Audio', 'manage_options', 'sparxstar-audio-tools-universe', [\SPARXSTAR\src\admin\SparxATAdminDisplay::class, 'render'], 'dashicons-admin-generic', 6);
    }

    /**
     * Enqueues scripts and styles for the WordPress admin area.
     */
    public function enqueue_admin_assets($hook) {
        // Only load our editor assets on the 'track' CPT edit screen.
        if (('post.php' === $hook || 'post-new.php' === $hook) && get_post_type() === 'track') {
            
            wp_enqueue_style('sparxstar-editor-style', SPARXAT_URL . 'assets/css/sparxstar-audio-tools.css', [], SPARXAT_VERSION);
            
            // The main JS controller that watches the ACF field
            wp_enqueue_script('sparxstar-main-controller', SPARXAT_URL . 'assets/js/sparxstar-audio-tools.js', ['jquery', 'acf-input'], SPARXAT_VERSION, true);

            // The Waveform editor logic (depends on the controller)
            wp_enqueue_script('sparxstar-waveform-editor', SPARXAT_URL . 'assets/js/sparxstar-audio-tools-waveform.js', ['sparxstar-main-controller'], SPARXAT_VERSION, true);
            
            // The API uploader logic (depends on the controller)
            wp_enqueue_script('sparxstar-api-uploader', SPARXAT_URL . 'assets/js/sparxstar-audio-tools-aimastering.js', ['sparxstar-main-controller'], SPARXAT_VERSION, true);
            
            // Make the REST API nonce available to our scripts
            wp_localize_script('sparxstar-main-controller', 'sparxstar_ajax', [
                'nonce'   => wp_create_nonce('wp_rest'),
                'post_id' => get_the_ID(),
            ]);
        }
    }

    /**
     * Enqueues assets for the public-facing side of the site (for shortcodes).
     */
    public function enqueue_frontend_assets() {
        global $post;
        // This is for your existing [SparxAT_mastering_status] shortcode
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'SparxAT_mastering_status')) {
            wp_enqueue_style('sparxstar-frontend-style', SPARXAT_URL . 'assets/css/frontend-style.css', [], SPARXAT_VERSION);
            wp_enqueue_script('sparxstar-frontend-script', SPARXAT_URL . 'assets/js/frontend-script.js', ['jquery'], SPARXAT_VERSION, true);
            wp_localize_script('sparxstar-frontend-script', 'SparxAT_ajax_object', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('SparxAT_frontend_nonce'),
            ]);
        }
    }
}