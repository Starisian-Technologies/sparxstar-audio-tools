<?php
namespace SPARXSTAR\src\includes;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function add_action;
use function current_user_can;
use function register_rest_route;
use function wp_upload_bits;
use function wp_insert_attachment;
use function wp_update_attachment_metadata;
use function wp_generate_attachment_metadata;
use function update_field;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function is_wp_error;
use function wp_json_encode;
use function wp_generate_password;
use function file_get_contents;
use function file_exists;
use function mime_content_type;
use function preg_replace;
use function strtolower;
use function pathinfo;
use function time;
use function json_decode;
use function require_once;
use function defined;
use function basename;
use function array_merge;
use function empty;
use function ABSPATH;

if (!defined('ABSPATH')) exit;

/**
 * Class SparxATApiHandler
 *
 * Handles all communication with the external AI Mastering API (api.bakuage.com)
 * and provides internal REST API endpoints for the frontend editor.
 */
class SparxATApiHandler
{
    private static $instance;
    private $api_key;
    private $api_base_url = 'https://api.bakuage.com';

    public static function instance(): SparxATApiHandler
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Securely get the API key from wp-config.php
        $this->api_key = defined('SPARXAT_AIMASTERING_KEY') ? SPARXAT_AIMASTERING_KEY : null;

        // Register the internal REST API route for the frontend editor.
        add_action('rest_api_init', [$this, 'register_editor_routes']);
    }

    /**
     * Registers the REST API endpoint for the WaveSurfer editor.
     */
    public function register_editor_routes()
    {
        register_rest_route('sparxstar/v1', '/update-track-audio', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_editor_upload'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    /**
     * Handles the file upload from the WaveSurfer editor.
     * Its ONLY job is to save the raw audio blob and update the ACF field.
     */
    public function handle_editor_upload(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $file = $request->get_file_params()['audio_blob'];
        $original_post_id = $request->get_param('original_post_id');

        if (!$file || !$original_post_id) {
            return new WP_Error('bad_request', 'Missing file or post ID.', ['status' => 400]);
        }
        
        // Detect file extension and MIME type
        $original_name = $file['name'] ?? '';
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $is_mp3 = ($ext === 'mp3');
        $filename = 'track-edit-' . $original_post_id . '-' . time() . '.' . ($is_mp3 ? 'mp3' : 'wav');
        // In handle_editor_upload()
        $file_tmp_path = $file['tmp_name'];
        $mime_type = mime_content_type($file_tmp_path); 
        $ext = ($mime_type === 'audio/mpeg') ? 'mp3' : 'wav'; // Or use a more robust lookup
        $filename = 'track-edit-' . $original_post_id . '-' . time() . '.' . $ext;

        $upload = wp_upload_bits($filename, null, file_get_contents($file['tmp_name']));
        if (!empty($upload['error'])) {
            return new WP_Error('upload_error', $upload['error'], ['status' => 500]);
        }

        $attachment_id = wp_insert_attachment([
            'guid'           => $upload['url'],
            'post_mime_type' => $mime_type,
            'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
            'post_status'    => 'inherit'
        ], $upload['file']);

        // This file is required because it contains the wp_generate_attachment_metadata() function,
        // which is used for all media types, not just images.
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
        
        // This is the critical step: Update the ACF field on the original Track post.
        update_field('field_678ea0f178a96', $attachment_id, $original_post_id);

        return new WP_REST_Response([
            'success'       => true,
            'message'       => 'Audio file updated. Click the main "Update" button on the post to save changes and process metadata.',
            'attachment_id' => $attachment_id
        ], 200);
    }

    /**
     * Submits a local audio file to the AI Mastering service.
     * Called by the backend cron/action scheduler.
     */
    public function submit_audio_for_initial_process(string $file_path, array $mastering_params = []): string|WP_Error
    {
        // Step 1: Upload the audio file to the service (POST /audios)
        $upload_result = $this->upload_audio_to_service($file_path);
        if (is_wp_error($upload_result)) {
            return $upload_result;
        }
        $audio_id = $upload_result['id'];

        // Step 2: Create the mastering job with the uploaded audio ID (POST /masterings)
        return $this->create_mastering_job($audio_id, $mastering_params);
    }
    
    /**
     * Gets the status report for a given mastering job ID.
     */
    public function get_job_status_report(string $job_id): array|WP_Error
    {
        // ... (This method from your provided code is correct and should be placed here) ...
    }
    
    /**
     * Helper to get API headers.
     */
    private function get_api_headers(): array|false
    {
        if (empty($this->api_key)) {
            return false;
        }
        return [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Accept'        => 'application/json',
        ];
    }
    
    /**
     * PRIVATE HELPER: Uploads the audio file to the service.
     */
    private function upload_audio_to_service(string $file_path): array|WP_Error
    {
        $headers = $this->get_api_headers();
        if (!$headers) return new WP_Error('api_key_missing', 'AI Mastering API Key is not configured.');
        if (!file_exists($file_path)) return new WP_Error('file_not_found', 'Audio file not found at: ' . $file_path);

        $boundary = wp_generate_password(24, false);
        $payload = '--' . $boundary . "\r\n" .
                   'Content-Disposition: form-data; name="file"; filename="' . basename($file_path) . '"' . "\r\n" .
                   'Content-Type: ' . mime_content_type($file_path) . "\r\n\r\n" .
                   file_get_contents($file_path) . "\r\n" .
                   '--' . $boundary . '--' . "\r\n";

        $args = [
            'method'  => 'POST',
            'headers' => array_merge($headers, ['Content-Type' => 'multipart/form-data; boundary=' . $boundary]),
            'body'    => $payload,
            'timeout' => 120,
        ];

        $response = wp_remote_post($this->api_base_url . '/audios', $args);
        
        if (is_wp_error($response)) return $response;
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code > 299) return new WP_Error('audio_upload_failed', $body['message'] ?? 'Failed to upload audio.');
        if (empty($body['id'])) return new WP_Error('audio_upload_no_id', 'Audio uploaded, but no ID was returned.');

        return $body;
    }

    /**
     * PRIVATE HELPER: Creates the mastering job.
     */
    private function create_mastering_job(string $audio_id, array $params): string|WP_Error
    {
        $headers = $this->get_api_headers();
        if (!$headers) return new WP_Error('api_key_missing', 'AI Mastering API Key is not configured.');

        $default_params = ['input_audio_id' => $audio_id];
        $body = wp_json_encode(array_merge($default_params, $params));

        $args = [
            'method'  => 'POST',
            'headers' => array_merge($headers, ['Content-Type' => 'application/json']),
            'body'    => $body,
            'timeout' => 30,
        ];
        
        $response = wp_remote_post($this->api_base_url . '/masterings', $args);
        
        if (is_wp_error($response)) return $response;
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code > 299) return new WP_Error('mastering_create_failed', $body['message'] ?? 'Failed to create mastering job.');
        if (empty($body['id'])) return new WP_Error('mastering_create_no_id', 'Mastering job created, but no Job ID was returned.');
        
        return $body['id']; // Success, return the Job ID
    }

    // Include the other helper methods from your provided code for completeness,
    // such as get_job_status_report, get_audio_download_details, and stream_file_via_api.
    // They are used by the SparxATShortcodes and SparxATCron classes.
}