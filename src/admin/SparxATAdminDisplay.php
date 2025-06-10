<?php
namespace SPARXSTAR\src\includes;

if ( ! defined( 'ABSPATH' ) ) exit;

class SparxATAdminDisplay {

    private static $instance;
    private $cpt_slug = 'music_track'; // <<< IMPORTANT: Change this to your CPT slug

    public static function instance(): SparxATAdminDisplay {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_status_meta_box' ] );
        // Enqueue admin scripts/styles if needed for AJAX updates or styling
        // add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    // public function enqueue_admin_assets( $hook_suffix ) {
    //     global $post_type;
    //     if ( ( $hook_suffix == 'post.php' || $hook_suffix == 'post-new.php' ) && $post_type == $this->cpt_slug ) {
    //         wp_enqueue_style( 'SparxAT-admin-style', SparxAT_URL . 'assets/css/admin-style.css', [], '1.0.0' );
    //         wp_enqueue_script( 'SparxAT-admin-script', SparxAT_URL . 'assets/js/admin-script.js', ['jquery'], '1.0.0', true );
    //         wp_localize_script( 'SparxAT-admin-script', 'SparxAT_ajax', [
    //             'ajax_url' => admin_url( 'admin-ajax.php' ),
    //             'nonce'    => wp_create_nonce( 'SparxAT_status_nonce' ),
    //             'post_id'  => get_the_ID()
    //         ]);
    //     }
    // }

    public function add_status_meta_box() {
        add_meta_box(
            'SparxAT_status_report_metabox',        // Unique ID
            'AI Mastering Status & Report',     // Box title
            [ $this, 'render_status_meta_box' ],// Callback function
            $this->cpt_slug,                    // Admin page (or post type)
            'normal',                           // Context (normal, side, advanced)
            'high'                              // Priority
        );
    }

    public function render_status_meta_box( $post ) {
        // wp_nonce_field( 'SparxAT_save_meta_box_data', 'SparxAT_meta_box_nonce' ); // Not strictly needed if not saving from here

        $job_id = get_post_meta( $post->ID, '_SparxAT_job_id', true );
        $status = get_post_meta( $post->ID, '_SparxAT_status', true );
        $report_data = get_post_meta( $post->ID, '_SparxAT_report_data', true ); // Stored as serialized array/object
        $report_message = get_post_meta( $post->ID, '_SparxAT_report_message', true );

        // Fix: Unserialize report_data if needed
        if ( ! empty( $report_data ) && is_string( $report_data ) ) {
            $report_data = maybe_unserialize( $report_data );
        }

        echo '<div id="SparxAT-status-container">';
        if ( empty( $status ) ) {
            echo '<p>No AI Mastering process initiated yet for this track.</p>';
        } else {
            echo '<p><strong>Job ID:</strong> ' . esc_html( $job_id ?: 'N/A' ) . '</p>';
            echo '<p><strong>Current Status:</strong> ' . esc_html( ucfirst( str_replace('_', ' ', $status ) ) ) . '</p>';

            if ( ! empty( $report_message ) ) {
                echo '<p><em>' . esc_html( $report_message ) . '</em></p>';
            }

            if ( ! empty( $report_data ) && is_array( $report_data ) ) {
                echo '<h4>Analysis Report:</h4>';
                echo '<pre style="white-space: pre-wrap; background: #f9f9f9; padding: 10px; border: 1px solid #ccc;">';
                echo esc_html( print_r( $report_data, true ) );
                echo '</pre>';
            } elseif ( $status === 'completed' && empty($report_data) ) {
                 echo '<p>Report data is missing or not in expected format.</p>';
            }
        }
        echo '</div>';
        // Optional: Add a manual "Refresh Status" button that uses AJAX
        // echo '<p><button type="button" id="SparxAT-refresh-status-btn" class="button">Refresh Status</button></p>';
    }
}