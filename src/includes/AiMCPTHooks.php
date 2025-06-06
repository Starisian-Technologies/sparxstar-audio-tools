<?php
namespace SparxstarAiMastering\src\includes;

if ( ! defined( 'ABSPATH' ) ) exit;

class AiMCPTHooks {

    private static $instance;
    private $cpt_slug = 'music_track'; // <<< IMPORTANT: Change this to your CPT slug
    private $audio_file_meta_key = '_audio_attachment_id'; // <<< IMPORTANT: Change this (or _audio_file_path)

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Hook into post save. Priority 100 to run after most other plugins/theme might save meta.
        add_action( 'save_post_' . $this->cpt_slug, [ $this, 'handle_cpt_save' ], 100, 3 );
    }

    /**
     * Handles the CPT save action to initiate AI Mastering process.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated or not.
     */
    public function handle_cpt_save( $post_id, $post, $update ) {
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        // Verify this came from the our screen and with proper authorization,
        // because save_post can be triggered at other times.
        // (Add nonce check if your form has one, though save_post for CPT often doesn't need it here if triggered by WP admin)

        // Check if we already have a job ID and it's not completed/failed,
        // to prevent re-submission unless explicitly desired (e.g., file changed).
        $existing_job_id = get_post_meta( $post_id, '_aim_job_id', true );
        $existing_status = get_post_meta( $post_id, '_aim_status', true );

        // Get the audio file path or ID
        $audio_info = get_post_meta( $post_id, $this->audio_file_meta_key, true );
        if ( empty( $audio_info ) ) {
            // update_post_meta( $post_id, '_aim_status', 'error_no_file' );
            // update_post_meta( $post_id, '_aim_report', 'No audio file associated with this post.' );
            return; // No audio file to process
        }

        $file_path = '';
        if ( is_numeric( $audio_info ) ) { // Assuming it's an attachment ID
            $file_path = get_attached_file( $audio_info ); // Gets server path
        } elseif ( filter_var( $audio_info, FILTER_VALIDATE_URL ) ) {
            // If it's a URL, you might need to download it first or ensure the SDK can handle URLs.
            // For now, assume it's a path or attachment ID.
            // $file_path = download_url($audio_info); // Risky, better to have local path
        } else { // Assuming it's a direct file path (less common for standard WP uploads)
            $file_path = $audio_info;
        }

        if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
            update_post_meta( $post_id, '_aim_status', 'error_file_path' );
            update_post_meta( $post_id, '_aim_report_message', 'Audio file path is invalid or file does not exist.' );
            return;
        }

        // OPTIONAL: Only re-process if the file has changed or no job exists
        $current_file_hash = md5_file($file_path);
        $previous_file_hash = get_post_meta( $post_id, '_aim_processed_file_hash', true );

        if ($existing_job_id && $existing_status !== 'failed' && $current_file_hash === $previous_file_hash) {
            // Already processed or processing this version of the file
            return;
        }

        // Update status to "pending_submission"
        update_post_meta( $post_id, '_aim_job_id', '' ); // Clear old job ID if reprocessing
        update_post_meta( $post_id, '_aim_status', 'pending_submission' );
        update_post_meta( $post_id, '_aim_report_data', '' ); // Clear old report
        update_post_meta( $post_id, '_aim_report_message', 'Submitting to AI Mastering...' );
        update_post_meta( $post_id, '_aim_processed_file_hash', $current_file_hash ); // Store hash of current file

        // --- Offload to Action Scheduler or WP Cron for immediate return ---
        // This makes the save_post action return quickly.
        if ( function_exists( 'as_enqueue_async_action' ) ) { // Action Scheduler (recommended)
            as_enqueue_async_action(
                'aim_submit_to_mastering_service',
                [ 'post_id' => $post_id, 'file_path' => $file_path ],
                'aim-mastering-group'
            );
        } else { // Fallback to simple WP Cron (less immediate, but better than blocking)
            wp_schedule_single_event( time() + 5, 'aim_submit_to_mastering_service_cron', [ ['post_id' => $post_id, 'file_path' => $file_path ] ] );
        }
    }
}

// --- Action Scheduler / WP Cron Hook for processing the submission ---
// This function runs in the background.

/**
 * Action Scheduler: Process the submission.
 * (This hook is registered by Action Scheduler when as_enqueue_async_action is called)
 */
add_action( 'aim_submit_to_mastering_service', 'aim_do_submit_to_mastering_service', 10, 2 );

/**
 * WP Cron: Process the submission.
 * (This hook is registered when wp_schedule_single_event is called)
 */
add_action( 'aim_submit_to_mastering_service_cron', 'aim_do_submit_to_mastering_service', 10, 2 );


function aim_do_submit_to_mastering_service( $post_id, $file_path ) {
    $api_handler = AIM_API_Handler::instance();
    $job_id_or_error = $api_handler->submit_audio_for_initial_process( $file_path );

    if ( is_wp_error( $job_id_or_error ) ) {
        update_post_meta( $post_id, '_aim_status', 'submission_failed' );
        update_post_meta( $post_id, '_aim_report_message', 'Submission Error: ' . $job_id_or_error->get_error_message() );
        error_log("AIM Submission Error for Post ID $post_id: " . $job_id_or_error->get_error_message());
    } else {
        update_post_meta( $post_id, '_aim_job_id', $job_id_or_error );
        update_post_meta( $post_id, '_aim_status', 'submitted_processing' ); // Or 'pending_analysis'
        update_post_meta( $post_id, '_aim_report_message', 'Successfully submitted. Job ID: ' . $job_id_or_error . '. Awaiting report.' );
        // If you have a cron job for polling, it will pick this up.
    }
}