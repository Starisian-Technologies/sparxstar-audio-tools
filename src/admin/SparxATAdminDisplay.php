<?php
namespace SPARXSTAR\src\admin; // Your namespace

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class SparxATAdminDisplay
 *
 * Handles the display of AI Mastering status and reports in a meta box
 * on the Custom Post Type edit screen.
 */
class SparxATAdminDisplay {

    private static $instance;
    private string $cpt_slug = 'music_track'; // <<< IMPORTANT: Change this to your CPT slug
    private string $audio_file_meta_key = '_audio_attachment_id'; // Meta key for the original audio file attachment ID

    /**
     * Gets the singleton instance of this class.
     *
     * @return SparxATAdminDisplay
     */
    public static function instance(): SparxATAdminDisplay {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     * Sets up WordPress hooks.
     */
    private function __construct() {
        // Hook to add meta boxes to the CPT edit screen
        add_action( 'add_meta_boxes_' . $this->cpt_slug, [ $this, 'add_status_meta_box' ] );
        // If you only want it for one CPT, you can use add_meta_boxes_{cpt_slug}
        // If for multiple, use add_meta_boxes and check post type inside the callback.
        // For simplicity, assuming it's for $this->cpt_slug only.

        // Uncomment to enqueue admin-specific assets if needed (e.g., for an AJAX refresh button)
        // add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Enqueues admin scripts and styles.
     * Only loads on the relevant CPT edit screen.
     *
     * @param string $hook_suffix The current admin page.
     */
    // public function enqueue_admin_assets( string $hook_suffix ): void {
    //     global $post, $typenow; // $typenow is more reliable than $post_type on some hooks.

    //     // Define SPARXAT_PLUGIN_URL and SPARXAT_VERSION in your main plugin file for this to work
    //     $plugin_url = defined('SPARXAT_PLUGIN_URL') ? SPARXAT_PLUGIN_URL : plugins_url( '../..', __FILE__ ); // Adjust path as necessary
    //     $plugin_version = defined('SPARXAT_VERSION') ? SPARXAT_VERSION : '1.0.0';


    //     if ( ( $hook_suffix === 'post.php' || $hook_suffix === 'post-new.php' ) && $typenow === $this->cpt_slug ) {
    //         wp_enqueue_style(
    //             'sparxat-admin-style',
    //             $plugin_url . 'assets/css/admin-style.css',
    //             [],
    //             $plugin_version
    //         );
    //         wp_enqueue_script(
    //             'sparxat-admin-script',
    //             $plugin_url . 'assets/js/admin-script.js',
    //             ['jquery'],
    //             $plugin_version,
    //             true
    //         );
    //         wp_localize_script( 'sparxat-admin-script', 'sparxat_admin_ajax', [
    //             'ajax_url' => admin_url( 'admin-ajax.php' ),
    //             'nonce'    => wp_create_nonce( 'sparxat_admin_status_nonce' ), // Specific nonce for admin actions
    //             'post_id'  => $post ? $post->ID : 0
    //         ]);
    //     }
    // }

    /**
     * Adds the meta box to the CPT edit screen.
     *
     * @param \WP_Post $post The current post object.
     */
    public function add_status_meta_box( \WP_Post $post ): void {
        // Check if the current post type matches the target CPT slug
        // This check is redundant if using add_meta_boxes_{cpt_slug} hook.
        // if ($post->post_type !== $this->cpt_slug) {
        //     return;
        // }

        add_meta_box(
            'sparxat_status_report_metabox',        // Unique ID for the meta box
            __( 'AI Mastering Status & Report', 'sparxstar-audio-tools' ), // Box title (translatable)
            [ $this, 'render_status_meta_box_content' ], // Callback function to render content
            $this->cpt_slug,                        // Screen to show on (CPT slug)
            'normal',                               // Context (normal, side, advanced)
            'high'                                  // Priority (high, core, default, low)
        );
    }

    /**
     * Renders the content of the status and report meta box.
     *
     * @param \WP_Post $post The current post object.
     */
    public function render_status_meta_box_content( \WP_Post $post ): void {
        // Add a nonce field if you plan to save data from this meta box,
        // though this meta box is primarily for display.
        // wp_nonce_field( 'sparxat_save_status_meta_box', 'sparxat_status_meta_box_nonce' );

        $job_id         = get_post_meta( $post->ID, '_sparxat_job_id', true );
        $status         = get_post_meta( $post->ID, '_sparxat_status', true );
        $saved_report_wrapper = get_post_meta( $post->ID, '_sparxat_report_data', true ); // This contains the mapped array
        $report_message = get_post_meta( $post->ID, '_sparxat_report_message', true );

        // The actual API response data is nested inside the '_sparxat_report_data' meta.
        $actual_api_report_data = '';
        if ( is_array( $saved_report_wrapper ) && isset( $saved_report_wrapper['report_data'] ) ) {
            $actual_api_report_data = $saved_report_wrapper['report_data'];
        } elseif (is_array($saved_report_wrapper)) {
            // Fallback if the structure is flat (older data or direct API response saved)
            $actual_api_report_data = $saved_report_wrapper;
        }


        echo '<div id="sparxat-status-container-' . esc_attr( $post->ID ) . '" class="sparxat-status-container">';

        if ( empty( $status ) ) {
            $audio_file_id = get_post_meta( $post->ID, $this->audio_file_meta_key, true );
            if ( ! empty( $audio_file_id ) && get_attached_file( $audio_file_id ) ) {
                 echo '<p>' . esc_html__( 'No AI Mastering process has been initiated for this track yet. Save or update the post to trigger processing if an audio file is attached.', 'sparxstar-audio-tools' ) . '</p>';
            } else {
                 echo '<p>' . esc_html__( 'No audio file is currently associated with this track via the designated field. Upload one to enable AI Mastering.', 'sparxstar-audio-tools' ) . '</p>';
            }
        } else {
            echo '<p><strong>' . esc_html__( 'Job ID:', 'sparxstar-audio-tools' ) . '</strong> ' . esc_html( $job_id ?: 'N/A' ) . '</p>';
            echo '<p><strong>' . esc_html__( 'Current Status:', 'sparxstar-audio-tools' ) . '</strong> ' . esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ) . '</p>';

            if ( ! empty( $report_message ) ) {
                echo '<p><em>' . esc_html( $report_message ) . '</em></p>';
            }

            // Display the actual API report data
            if ( ! empty( $actual_api_report_data ) && is_array( $actual_api_report_data ) ) {
                echo '<h4>' . esc_html__( 'AI Mastering API Report:', 'sparxstar-audio-tools' ) . '</h4>';
                echo '<pre style="white-space: pre-wrap; background: #f9f9f9; padding: 10px; border: 1px solid #ccc; max-height: 300px; overflow-y: auto;">';
                echo esc_html( print_r( $actual_api_report_data, true ) );
                echo '</pre>';
            } elseif ( $status === 'completed' && empty( $actual_api_report_data ) ) {
                 echo '<p>' . esc_html__( 'Mastering is marked as completed, but the detailed report data from the API is missing or not in the expected format.', 'sparxstar-audio-tools' ) . '</p>';
            }
        }

        // Optional: Add a manual "Refresh Status" button that could use AJAX
        // if ( ! empty( $job_id ) && in_array( $status, ['submitted_processing', 'processing', 'submitted_processing_form'] ) ) {
        //     echo '<p><button type="button" id="sparxat-admin-refresh-status-btn-' . esc_attr( $post->ID ) . '" class="button sparxat-admin-refresh-status-btn" data-postid="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Refresh Status from Admin', 'sparxstar-audio-tools' ) . '</button></p>';
        //     echo '<div id="sparxat-admin-ajax-message-' . esc_attr( $post->ID ) . '" class="sparxat-admin-ajax-message" style="display:none; margin-top:10px;"></div>';
        // }

        echo '</div>'; // End #sparxat-status-container
    }

} // End class SparxATAdminDisplay