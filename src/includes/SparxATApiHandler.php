<?php
namespace SPARXSTAR\src\includes; // Your namespace

if ( ! defined( 'ABSPATH' ) ) exit;

// It's good practice to list only the WP functions you actually use if you're not using a `use function ...;` block for all of them.
// However, the previous list was comprehensive, so I'll keep it for now.
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
// ... (other use function statements if you prefer that style) ...

/**
 * Class SparxATApiHandler
 *
 * Handles all communication with the external AI Mastering API (api.bakuage.com)
 * and provides internal REST API endpoints for the frontend editor (if you still have/need this).
 */
class SparxATApiHandler {
    private static $instance;
    private $api_key;
    private $api_base_url = 'https://api.bakuage.com'; // Port 443 is implicit with https

    public static function instance(): SparxATApiHandler { // Added return type hint
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Get API key from wp-config.php constant (recommended for security) or WordPress option
        $this->api_key = defined('SPARXAT_AIMASTERING_KEY')
                         ? SPARXAT_AIMASTERING_KEY
                         : get_option('sparxat_api_key', ''); // Fallback to option

        if ( empty( $this->api_key ) ) {
            // Log an error or add an admin notice if the key is missing.
            // For now, it will just fail silently when trying to get headers.
            error_log('SPARXAT AI Mastering API Key is not configured.');
        }

        // Register the internal REST API route for the frontend editor, if still needed.
        // If this WaveSurfer editor integration is separate or not currently used for AI Mastering,
        // you might remove this part or ensure it doesn't conflict.
        add_action( 'rest_api_init', [$this, 'register_editor_routes'] );
    }

    /**
     * Registers REST API routes.
     */
    public function register_editor_routes() {
        // This route seems to be for a specific editor functionality.
        // Ensure its purpose is clear and doesn't conflict.
        register_rest_route( 'sparxstar/v1', '/update-track-audio', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_editor_upload'],
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
            // Add schema for args if you want to be more specific
        ] );
    }

    /**
     * Handles file upload from a specific editor (e.g., WaveSurfer).
     * This method's primary job is to save the audio to WordPress media library
     * and update an ACF field. It does NOT directly interact with AI Mastering API.
     */
    public function handle_editor_upload( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $file_data = $request->get_file_params();
        $file = $file_data['audio_blob'] ?? null; // Assuming 'audio_blob' is the field name
        $original_post_id = $request->get_param( 'original_post_id' );
        $original_filename = $request->get_param('original_filename') ?: ($file['name'] ?? 'edited-audio.wav'); // Get original filename if sent

        if ( ! $file || empty( $file['tmp_name'] ) || ! $original_post_id ) {
            return new WP_Error( 'bad_request', 'Missing audio data, temporary file path, or post ID.', ['status' => 400] );
        }

        $file_tmp_path = $file['tmp_name'];
        $detected_type = wp_check_filetype( $original_filename, null ); // Use original name for better type detection
        $mime_type = $detected_type['type'];
        $ext = $detected_type['ext'];

        // Sanitize original filename for use
        $sane_original_filename = sanitize_file_name($original_filename);
        $filename_parts = pathinfo($sane_original_filename);
        $base_name = $filename_parts['filename'];

        $filename = $base_name . '-edit-' . $original_post_id . '-' . time() . '.' . $ext;

        $upload = wp_upload_bits( $filename, null, file_get_contents( $file_tmp_path ) );

        if ( ! empty( $upload['error'] ) ) {
            return new WP_Error( 'upload_error', 'WordPress upload error: ' . $upload['error'], ['status' => 500] );
        }

        $attachment_id = wp_insert_attachment( [
            'guid'           => $upload['url'],
            'post_mime_type' => $mime_type,
            'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ), // Title without extension
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $upload['file'], $original_post_id ); // Associate with parent post if desired

        if ( is_wp_error( $attachment_id ) ) {
            return new WP_Error( 'attachment_error', 'Could not create attachment: ' . $attachment_id->get_error_message(), ['status' => 500] );
        }

        require_once( ABSPATH . 'wp-admin/includes/image.php' ); // For wp_generate_attachment_metadata
        wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

        // Update ACF field (replace 'field_xxxxxxxxxxxxxx' with your actual ACF field key)
        $acf_field_key = 'field_678ea0f178a96'; // <<< YOUR ACF FIELD KEY
        if ( function_exists('update_field') ) {
            update_field( $acf_field_key, $attachment_id, $original_post_id );
        } else {
            // Fallback or log error if ACF is not active or update_field isn't available
            // update_post_meta($original_post_id, 'your_acf_field_name', $attachment_id); // If you know the raw meta key
            error_log('SPARXAT: ACF function update_field() not found. Cannot update audio field.');
        }


        return new WP_REST_Response( [
            'success'       => true,
            'message'       => 'Audio file updated in media library and linked via ACF. Please "Update" the main post to save all changes and trigger further processing if applicable.',
            'attachment_id' => $attachment_id,
            'file_url'      => $upload['url'],
        ], 200 );
    }


    /**
     * Helper to get API headers with Authorization.
     */
    private function get_api_headers(): array|false {
        if ( empty( $this->api_key ) ) {
            error_log('SPARXAT API Key is missing, cannot create API headers.');
            return false;
        }
        return [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Accept'        => 'application/json', // Default, can be overridden per request
        ];
    }

    /**
     * Submits an audio file and creates a mastering job with AI Mastering.
     *
     * @param string $file_path Absolute path to the audio file on the server.
     * @param array $mastering_params Parameters for mastering (e.g., targetLoudness). Keys should match API spec.
     * @return string|WP_Error Mastering Job ID on success, WP_Error on failure.
     */
    public function submit_audio_for_initial_process( string $file_path, array $mastering_params = [] ): string|WP_Error {
        // Step 1: Upload the audio file to the service (POST /audios)
        $upload_result = $this->upload_audio_to_bakuage_service( $file_path );
        if ( is_wp_error( $upload_result ) ) {
            return $upload_result;
        }

        $audio_id = $upload_result['id'] ?? null;
        if ( empty( $audio_id ) ) {
            return new WP_Error('audio_upload_malformed_response', 'Audio uploaded, but no ID found in response from AI Mastering.', $upload_result);
        }

        // Step 2: Create the mastering job with the uploaded audio ID (POST /masterings)
        return $this->create_mastering_job_on_bakuage_service( (string) $audio_id, $mastering_params );
    }

    /**
     * PRIVATE HELPER: Uploads the audio file to api.bakuage.com/audios.
     */
    private function upload_audio_to_bakuage_service( string $file_path ): array|WP_Error {
        $api_headers_base = $this->get_api_headers();
        if ( ! $api_headers_base ) {
            return new WP_Error( 'api_key_missing', 'AI Mastering API Key is not configured.' );
        }
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', 'Local audio file not found at: ' . $file_path );
        }

        $boundary = wp_generate_password( 24, false );
        $payload = '';

        // File part (name="file")
        $payload .= '--' . $boundary;
        $payload .= "\r\n";
        $payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $file_path ) . '"' . "\r\n";
        $file_mime_type = mime_content_type( $file_path );
        $payload .= 'Content-Type: ' . ( $file_mime_type ?: 'application/octet-stream' ) . "\r\n";
        $payload .= "\r\n";
        $payload .= file_get_contents( $file_path );
        $payload .= "\r\n";
        // Optional: 'name' parameter for the audio
        // $audio_custom_name = basename($file_path); // Or some other source
        // $payload .= '--' . $boundary . "\r\n";
        // $payload .= 'Content-Disposition: form-data; name="name"' . "\r\n\r\n";
        // $payload .= $audio_custom_name . "\r\n";
        $payload .= '--' . $boundary . '--';
        $payload .= "\r\n";

        $args = [
            'method'  => 'POST',
            'headers' => array_merge( $api_headers_base, ['Content-Type' => 'multipart/form-data; boundary=' . $boundary] ),
            'body'    => $payload,
            'timeout' => 120, // Increased timeout for potentially large file uploads
        ];

        $response = wp_remote_post( $this->api_base_url . '/audios', $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'SPARXAT Bakuage Audio Upload WP_Error: ' . $response->get_error_message() );
            return $response;
        }

        $response_body_raw = wp_remote_retrieve_body( $response );
        $response_data = json_decode( $response_body_raw, true );
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code >= 300 || $response_code < 200 ) {
            $error_message = $response_data['message'] ?? (is_array($response_data) ? print_r($response_data, true) : 'Failed to upload audio to AI Mastering.');
            error_log( 'SPARXAT Bakuage Audio Upload API Error (Code ' . $response_code . '): ' . $error_message . ' | Raw Body: ' . $response_body_raw );
            return new WP_Error( 'audio_upload_api_failed', $error_message, ['status_code' => $response_code, 'body' => $response_data] );
        }
        if ( empty( $response_data['id'] ) ) {
            error_log( 'SPARXAT Bakuage Audio Upload: No "id" in successful response. Body: ' . $response_body_raw );
            return new WP_Error( 'audio_upload_no_id_returned', 'Audio uploaded to AI Mastering, but no ID was returned.', ['body' => $response_data] );
        }
        return $response_data; // Should contain ['id' => '...', ...]
    }

    /**
     * PRIVATE HELPER: Creates the mastering job on api.bakuage.com/masterings.
     * This now correctly sends multipart/form-data as per API documentation.
     */
    private function create_mastering_job_on_bakuage_service( string $audio_id, array $user_params = [] ): string|WP_Error {
        $api_headers_base = $this->get_api_headers();
        if ( ! $api_headers_base ) {
            return new WP_Error( 'api_key_missing', 'AI Mastering API Key is not configured.' );
        }

        $boundary = wp_generate_password( 24, false );
        $payload_parts = [];

        // Required: inputAudioId
        $payload_parts[] = ['name' => 'inputAudioId', 'contents' => $audio_id];

        // Default parameters from plugin settings, overridden by $user_params
        $default_api_params = [
            'targetLoudness'     => (string) get_option( 'sparxat_default_target_loudness', -10.0 ),
            'outputFormat'       => sanitize_text_field( get_option( 'sparxat_default_output_format', 'wav' ) ),
            'masteringAlgorithm' => sanitize_text_field( get_option( 'sparxat_default_mastering_algorithm', 'v2' ) ),
            'bassPreservation'   => get_option( 'sparxat_default_bass_preservation', false ) ? 'true' : 'false',
            'mode'               => 'default', // API default
            // Add other supported API parameters here with their defaults if desired
        ];

        // User params should already have keys matching API spec (camelCase)
        // and booleans converted to 'true'/'false' strings by the AJAX handler if coming from form.
        $final_api_params = array_merge( $default_api_params, $user_params );

        foreach ( $final_api_params as $key => $value ) {
            // All values sent as strings in multipart/form-data
            $payload_parts[] = ['name' => $key, 'contents' => (string) $value];
        }

        $payload = '';
        foreach ( $payload_parts as $part ) {
            $payload .= '--' . $boundary;
            $payload .= "\r\n";
            $payload .= 'Content-Disposition: form-data; name="' . esc_attr( $part['name'] ) . '"' . "\r\n";
            $payload .= "\r\n";
            $payload .= $part['contents'];
            $payload .= "\r\n";
        }
        $payload .= '--' . $boundary . '--';
        $payload .= "\r\n";

        $args = [
            'method'  => 'POST',
            'headers' => array_merge( $api_headers_base, ['Content-Type' => 'multipart/form-data; boundary=' . $boundary] ),
            'body'    => $payload,
            'timeout' => 45, // Slightly longer timeout for this step
        ];

        $response = wp_remote_post( $this->api_base_url . '/masterings', $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'SPARXAT Create Mastering Job WP_Error: ' . $response->get_error_message() );
            return $response;
        }

        $response_body_raw = wp_remote_retrieve_body( $response );
        $response_data = json_decode( $response_body_raw, true );
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code >= 300 || $response_code < 200 ) { // 201 or 202 might also be success for job creation
            $error_message = $response_data['message'] ?? (is_array($response_data) ? print_r($response_data, true) : 'Failed to create mastering job on AI Mastering.');
            error_log( 'SPARXAT Create Mastering Job API Error (Code ' . $response_code . '): ' . $error_message . ' | Raw Body: ' . $response_body_raw );
            return new WP_Error( 'mastering_create_api_failed', $error_message, ['status_code' => $response_code, 'body' => $response_data] );
        }

        $mastering_job_id = $response_data['id'] ?? null;
        if ( empty( $mastering_job_id ) ) {
            error_log( 'SPARXAT Create Mastering Job: No "id" (job ID) in successful response. Body: ' . $response_body_raw );
            return new WP_Error( 'mastering_create_no_id_returned', 'Mastering job submitted to AI Mastering, but no Job ID was returned.', ['body' => $response_data] );
        }
        return (string) $mastering_job_id; // Return the mastering job ID
    }


    /**
     * Gets the status report for a given mastering job ID from api.bakuage.com.
     *
     * @param string $job_id The AI Mastering Job ID (mastering_id).
     * @return array|WP_Error Mapped report data on success, WP_Error on failure.
     */
    public function get_job_status_report( string $job_id ): array|WP_Error {
        $api_headers = $this->get_api_headers();
        if ( ! $api_headers ) {
            return new WP_Error( 'api_key_missing', 'AI Mastering API Key is not configured for status check.' );
        }
        if ( empty( $job_id ) ) {
            return new WP_Error( 'job_id_missing', 'Job ID is required to fetch status.' );
        }

        $status_url = $this->api_base_url . '/masterings/' . $job_id;
        $args = [
            'method'  => 'GET',
            'headers' => $api_headers,
            'timeout' => 30,
        ];

        $response = wp_remote_get( $status_url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'SPARXAT Get Job Status WP_Error (Job ' . $job_id . '): ' . $response->get_error_message() );
            return $response;
        }

        $response_body_raw = wp_remote_retrieve_body( $response );
        $api_response_data = json_decode( $response_body_raw, true ); // This is the full mastering object from API
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code !== 200 ) {
            $error_message = $api_response_data['message'] ?? (is_array($api_response_data) ? print_r($api_response_data, true) : 'Failed to get job status from AI Mastering.');
            error_log( 'SPARXAT Get Job Status API Error (Job ' . $job_id . ', Code ' . $response_code . '): ' . $error_message . ' | Raw Body: ' . $response_body_raw );
            return new WP_Error( 'api_status_error', $error_message, ['status_code' => $response_code, 'body' => $api_response_data] );
        }

        // Map the API response to a consistent structure for the plugin
        $mapped_report = [
            'id'            => $api_response_data['id'] ?? $job_id,
            'status'        => isset($api_response_data['status']) ? sanitize_text_field($api_response_data['status']) : 'unknown',
            'progress'      => isset($api_response_data['progress_percent']) ? intval($api_response_data['progress_percent']) : (isset($api_response_data['progress']) ? intval($api_response_data['progress']) : 0),
            'report_data'   => $api_response_data, // Store the entire API response object for flexibility
            'error_message' => $api_response_data['error'] ?? ($api_response_data['error_message'] ?? null),
        ];
        return $mapped_report;
    }

    /**
     * Gets download information (like a temporary token) for an audio ID from AI Mastering.
     *
     * @param string $audio_id The ID of the audio file on AI Mastering (usually the output_audio_id).
     * @return array|WP_Error ['token' => 'DOWNLOAD_TOKEN'] on success, or WP_Error.
     */
    public function get_audio_download_details( string $audio_id ): array|WP_Error {
        $api_headers = $this->get_api_headers();
        if ( ! $api_headers ) {
            return new WP_Error( 'api_key_missing', 'AI Mastering API Key missing for download details.' );
        }
        if ( empty( $audio_id ) ) {
            return new WP_Error( 'audio_id_missing', 'Audio ID is required to get download details.' );
        }

        // Endpoint: GET /audios/{id}/download_token
        $token_url = $this->api_base_url . '/audios/' . $audio_id . '/download_token';
        $args = ['headers' => $api_headers, 'timeout' => 20];
        $response = wp_remote_get( $token_url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'SPARXAT Get Download Token WP_Error (Audio ' . $audio_id . '): ' . $response->get_error_message() );
            return $response;
        }

        $response_body_raw = wp_remote_retrieve_body( $response );
        $token_data = json_decode( $response_body_raw, true );
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code === 200 && isset( $token_data['audio_download_token'] ) ) {
            return ['token' => $token_data['audio_download_token']];
        }

        $error_message = $token_data['message'] ?? (is_array($token_data) ? print_r($token_data, true) : 'Could not get download token from AI Mastering.');
        error_log( 'SPARXAT Get Download Token API Error (Audio ' . $audio_id . ', Code ' . $response_code . '): ' . $error_message . ' | Raw Body: ' . $response_body_raw);
        return new WP_Error( 'download_token_api_failed', $error_message, ['status_code' => $response_code, 'body' => $token_data] );
    }

    /**
     * Streams an audio file from AI Mastering to the browser.
     * Uses either a download token or a direct audio ID.
     *
     * @param string $identifier Can be an audio_id or an audio_download_token.
     * @param string $filename The desired filename for the download.
     * @param bool $is_token If true, $identifier is treated as a token.
     */
    public function stream_file_via_api( string $identifier, string $filename = 'mastered_audio.wav', bool $is_token = false ): void {
        // Headers might be needed for the actual download call, even if tokenized.
        // The API docs for /download_by_token and /audios/{id}/download specify "bearer" auth.
        $api_headers = $this->get_api_headers();
        if ( ! $api_headers ) {
            status_header(401); // Unauthorized
            wp_die('API Authentication failed for file stream. Please check plugin configuration.');
        }

        if ( $is_token ) {
            // Endpoint: GET /audios/download_by_token?audio_download_token={TOKEN}
            $download_url = add_query_arg( 'audio_download_token', urlencode( $identifier ), $this->api_base_url . '/audios/download_by_token' );
        } else {
            // Endpoint: GET /audios/{id}/download
            $download_url = $this->api_base_url . '/audios/' . $identifier . '/download';
        }

        $args = [
            'headers' => $api_headers, // API requires Bearer token for these download endpoints
            'timeout' => 300,        // Long timeout for file download
            'stream'  => false,      // Get the body in memory. For true proxy streaming, this is complex.
        ];
        $response = wp_remote_get( $download_url, $args );

        if ( is_wp_error( $response ) ) {
            status_header(502); // Bad Gateway
            wp_die( 'Error contacting AI Mastering download server: ' . esc_html( $response->get_error_message() ) );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $content_type  = wp_remote_retrieve_header( $response, 'content-type' );
        $content_length= wp_remote_retrieve_header( $response, 'content-length' );

        if ( $response_code !== 200 ) {
            status_header( $response_code );
            $body_content = wp_remote_retrieve_body( $response );
            $error_detail = is_string($body_content) && strlen($body_content) < 500 ? esc_html($body_content) : 'Please check server logs for details.';
            wp_die( 'Error downloading file from AI Mastering. Status: ' . esc_html( $response_code ) . '<br>' . $error_detail );
        }

        // Send headers to browser for download
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: ' . ( $content_type ?: 'application/octet-stream' ) );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"');
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        if ( $content_length ) {
            header( 'Content-Length: ' . $content_length );
        }

        // Prevent output buffering issues
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        echo wp_remote_retrieve_body( $response );
        exit; // Terminate script execution
    }

} // End class SparxATApiHandler