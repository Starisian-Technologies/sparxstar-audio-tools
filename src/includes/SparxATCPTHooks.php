<?php
namespace SPARXSTAR\src\includes;

use function add_action;
use function is_admin;
use function get_post_meta;
use function get_attached_file;
use function update_post_meta;
use function as_enqueue_async_action;
use function wp_schedule_single_event;
use function esc_attr;
use function is_wp_error;
use WP_Post;
use SPARXSTAR\src\frontend\SparxATWaveform;

if (!defined('ABSPATH'))
    exit;


class SparxATCPTHooks {
    // ... your properties ...
    private $audio_file_acf_key = 'field_678ea0f178a96'; // <<< CONFIRMED
    private $audio_file_meta_key = 'audio_file'; // <-- Added: ACF field name for audio file
    private $cpt_slug = 'your_cpt_slug'; // <-- Replace with your actual CPT slug

    public static function instance(): void { /* ... */ }

    private function __construct() {
        // KEEP THIS. This is the final step in our chain.
        add_action('save_post_' . $this->cpt_slug, [$this, 'handle_cpt_save'], 100, 3);

        // NEW: Hooks to inject the editor on the admin page.
        if (is_admin()) {
            add_action('acf/render_field/key=' . $this->audio_file_acf_key, [$this, 'inject_editor_launch_panel']);
            add_action('admin_footer', [SparxATWaveform::class, 'render_editor_modal_template']);
        }
    }

    /**
     * Injects the placeholder div our JS will target to add the "Edit Audio" button.
     */
    public function inject_editor_launch_panel($field): void
    {
        echo '<div id="sparxstar-editor-root" data-field-key="' . esc_attr($field['key']) . '"></div>';
    }
    /**
     * Handles the CPT save action to initiate AI Mastering process.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated or not.
     */
    public function handle_cpt_save($post_id, $post, $update): void
    {
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        // Verify this came from the our screen and with proper authorization,
        // because save_post can be triggered at other times.
        // (Add nonce check if your form has one, though save_post for CPT often doesn't need it here if triggered by WP admin)

        // Check if we already have a job ID and it's not completed/failed,
        // to prevent re-submission unless explicitly desired (e.g., file changed).
        $existing_job_id = get_post_meta($post_id, '_SparxAT_job_id', true);
        $existing_status = get_post_meta($post_id, '_SparxAT_status', true);

        // Get the audio file path or ID
        $audio_info = get_post_meta($post_id, $this->audio_file_meta_key, true);
        if (empty($audio_info)) {
            // update_post_meta( $post_id, '_SparxAT_status', 'error_no_file' );
            // update_post_meta( $post_id, '_SparxAT_report', 'No audio file associated with this post.' );
            return; // No audio file to process
        }

        $file_path = '';
        if (is_numeric($audio_info)) { // Assuming it's an attachment ID
            $file_path = get_attached_file($audio_info); // Gets server path
        } elseif (filter_var($audio_info, FILTER_VALIDATE_URL)) {
            // If it's a URL, you might need to download it first or ensure the SDK can handle URLs.
            // For now, assume it's a path or attachment ID.
            // $file_path = download_url($audio_info); // Risky, better to have local path
        } else { // Assuming it's a direct file path (less common for standard WP uploads)
            $file_path = $audio_info;
        }

        if (empty($file_path) || !file_exists($file_path)) {
            update_post_meta($post_id, '_SparxAT_status', 'error_file_path');
            update_post_meta($post_id, '_SparxAT_report_message', 'Audio file path is invalid or file does not exist.');
            return;
        }

        // OPTIONAL: Only re-process if the file has changed or no job exists
        $current_file_hash = md5_file($file_path);
        $previous_file_hash = get_post_meta($post_id, '_SparxAT_processed_file_hash', true);

        if ($existing_job_id && $existing_status !== 'failed' && $current_file_hash === $previous_file_hash) {
            // Already processed or processing this version of the file
            return;
        }

        // Update status to "pending_submission"
        update_post_meta($post_id, '_SparxAT_job_id', ''); // Clear old job ID if reprocessing
        update_post_meta($post_id, '_SparxAT_status', 'pending_submission');
        update_post_meta($post_id, '_SparxAT_report_data', ''); // Clear old report
        update_post_meta($post_id, '_SparxAT_report_message', 'Submitting to AI Mastering...');
        update_post_meta($post_id, '_SparxAT_processed_file_hash', $current_file_hash); // Store hash of current file

        // --- Offload to Action Scheduler or WP Cron for immediate return ---
        // This makes the save_post action return quickly.
        if (function_exists('as_enqueue_async_action')) { // Action Scheduler (recommended)
            as_enqueue_async_action(
                'SparxAT_submit_to_mastering_service',
                ['post_id' => $post_id, 'file_path' => $file_path],
                'SparxAT-mastering-group'
            );
        } else { // Fallback to simple WP Cron (less immediate, but better than blocking)
            wp_schedule_single_event(time() + 5, 'SparxAT_submit_to_mastering_service_cron', [['post_id' => $post_id, 'file_path' => $file_path]]);
        }
    }
}

public function handle_cpt_save($post_id, $post, $update) {
    // ... (your existing initial checks for autosave, file_exists, etc.) ...
    
    // Check the file hash to see if the audio file has actually changed.
    $current_file_hash = md5_file($file_path);
    $previous_file_hash = get_post_meta($post_id, '_SparxAT_processed_file_hash', true);

    if ($current_file_hash === $previous_file_hash && !isset($_POST['sparxat_force_reprocess'])) {
        // Nothing to do if the file hasn't changed, unless a re-process is forced.
        return;
    }
    
    update_post_meta($post_id, '_SparxAT_status', 'pending_metadata');
    update_post_meta($post_id, '_SparxAT_report_message', 'Queued for metadata update.');
    update_post_meta($post_id, '_SparxAT_processed_file_hash', $current_file_hash);

    // --- NEW: Schedule the METADATA update first ---
    wp_schedule_single_event(time() + 10, 'sparxat_update_id3_tags_hook', [$post_id]);
}

// --- Action Scheduler / WP Cron Hook for processing the submission ---
// This function runs in the background.

/**
 * Action Scheduler: Process the submission.
 * (This hook is registered by Action Scheduler when as_enqueue_async_action is called)
 */
add_action('SparxAT_submit_to_mastering_service', 'SparxAT_do_submit_to_mastering_service', 10, 2);

/**
 * WP Cron: Process the submission.
 * (This hook is registered when wp_schedule_single_event is called)
 */
add_action('SparxAT_submit_to_mastering_service_cron', 'SparxAT_do_submit_to_mastering_service', 10, 2);


function SparxAT_do_submit_to_mastering_service($post_id, $file_path): void
{
    // Ensure the post ID is valid and the file path exists
    if (empty($post_id) || empty($file_path) || !file_exists($file_path)) {
        error_log("SparxAT Submission Error: Invalid post ID or file path.");
        return;
    }
    // Ensure the API handler is available
    if (!class_exists('\\SPARXSTAR\\src\\includes\\SparxATApiHandler')) {
        error_log("SparxAT Submission Error: SparxATApiHandler class not found.");
        return;
    }
    $api_handler = \SPARXSTAR\src\includes\SparxATApiHandler::instance();
    $job_id_or_error = $api_handler->submit_audio_for_initial_process($file_path);

    if (is_wp_error($job_id_or_error)) {
        update_post_meta($post_id, '_SparxAT_status', 'submission_failed');
        update_post_meta($post_id, '_SparxAT_report_message', 'Submission Error: ' . $job_id_or_error->get_error_message());
        error_log("SparxAT Submission Error for Post ID $post_id: " . $job_id_or_error->get_error_message());
    } else {
        update_post_meta($post_id, '_SparxAT_job_id', $job_id_or_error);
        update_post_meta($post_id, '_SparxAT_status', 'submitted_processing'); // Or 'pending_analysis'
        update_post_meta($post_id, '_SparxAT_report_message', 'Successfully submitted. Job ID: ' . $job_id_or_error . '. Awaiting report.');
        // If you have a cron job for polling, it will pick this up.
    }
}