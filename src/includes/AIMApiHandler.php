<?php
namespace SparxstarAiMastering\src\includes;

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AiMApiHandler {

    private static $instance;
    private $api_key;
    private $api_base_url = 'https://api.bakuage.com'; // Port 443 is implicit with https

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api_key = get_option( 'aim_api_key', '' ); // Get from plugin settings
        // Or use a constant: defined('AIM_API_KEY') ? AIM_API_KEY : '';

        if ( empty( $this->api_key ) ) {
            error_log('AI Mastering API Key is not set in plugin settings.');
        }
    }

    private function get_api_headers() {
        if ( empty( $this->api_key ) ) {
            return false;
        }
        return [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Accept'        => 'application/json',
        ];
    }

    /**
     * Submits an audio file and creates a mastering job.
     * This combines "createAudio" and "createMastering".
     *
     * @param string $file_path Absolute path to the audio file on the server.
     * @param array $mastering_params Parameters for mastering, e.g., ['target_loudness' => -8.0]
     * @return string|WP_Error Mastering Job ID on success, WP_Error on failure.
     */
    public function submit_audio_for_initial_process( $file_path, $mastering_params = [] ) {
        $headers = $this->get_api_headers();
        if ( ! $headers ) {
            return new WP_Error( 'api_key_missing', 'AI Mastering API Key is not configured.' );
        }

        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', 'Audio file not found at: ' . $file_path );
        }

        // Inside submit_audio_for_initial_process, for POST /audios
// ...
$payload = '';
$boundary = wp_generate_password( 24, false );

// File part (name="file" is confirmed)
$payload .= '--' . $boundary;
$payload .= "\r\n";
$payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $file_path ) . '"' . "\r\n";
$file_mime_type = mime_content_type( $file_path ); // Get actual mime type
$payload .= 'Content-Type: ' . ($file_mime_type ?: 'application/octet-stream') . "\r\n";
$payload .= "\r\n";
$payload .= file_get_contents( $file_path );
$payload .= "\r\n";

// Optional: Add 'name' parameter if you want to provide it
$audio_custom_name = basename( $file_path ); // Or get from post title, CPT field, etc.
// if ( $audio_custom_name ) {
//     $payload .= '--' . $boundary;
//     $payload .= "\r\n";
//     $payload .= 'Content-Disposition: form-data; name="name"' . "\r\n";
//     $payload .= "\r\n";
//     $payload .= $audio_custom_name;
//     $payload .= "\r\n";
// }

$payload .= '--' . $boundary . '--';
$payload .= "\r\n";

$upload_args = [
    'method'  => 'POST',
    'headers' => array_merge($headers, [ // $headers includes Authorization
        'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
    ]),
    'body'    => $payload,
    'timeout' => 60,
];

        $upload_response = wp_remote_post( $audio_upload_url, $upload_args );

        if ( is_wp_error( $upload_response ) ) {
            error_log( 'AI Mastering Audio Upload WP_Error: ' . $upload_response->get_error_message() );
            return $upload_response;
        }

        $upload_response_code = wp_remote_retrieve_response_code( $upload_response );
        $upload_response_body = wp_remote_retrieve_body( $upload_response );
        $upload_data = json_decode( $upload_response_body, true );

        if ( $upload_response_code !== 200 && $upload_response_code !== 201 ) { // 201 Created is also common
            $error_message = isset($upload_data['message']) ? $upload_data['message'] : 'Failed to upload audio. Code: ' . $upload_response_code;
            error_log( 'AI Mastering Audio Upload API Error: ' . $error_message . ' Body: ' . $upload_response_body );
            return new WP_Error( 'audio_upload_failed', $error_message );
        }

        $audio_id = isset( $upload_data['id'] ) ? $upload_data['id'] : null;
        if ( empty( $audio_id ) ) {
            error_log( 'AI Mastering Audio Upload: No audio ID returned. Body: ' . $upload_response_body );
            return new WP_Error( 'audio_upload_no_id', 'Audio uploaded, but no ID returned by API.' );
        }

        // === Step 2: Create Mastering Job (POST /masterings) ===
        $mastering_create_url = $this->api_base_url . '/masterings';
        
        // Default parameters - these might need to be discovered from API docs or experimentation
        $default_mastering_params = [
            // The API docs for "Mastering" model would specify the exact parameter name for audio_id
            'input_audio_id'   => $audio_id, // This is a guess, check API docs for actual field name
            // 'audio_id' => $audio_id, // another common way
            'target_loudness'  => (float) get_option('aim_target_loudness', -10.0), // Make this configurable
            // 'output_format' => 'wav', // Example
            // Add other parameters as needed/supported
        ];
        $final_mastering_params = array_merge($default_mastering_params, $mastering_params);


        $mastering_args = [
            'method'  => 'POST',
            'headers' => array_merge($headers, ['Content-Type' => 'application/json']),
            'body'    => wp_json_encode( $final_mastering_params ),
            'timeout' => 30,
        ];

        $mastering_response = wp_remote_post( $mastering_create_url, $mastering_args );

        if ( is_wp_error( $mastering_response ) ) {
            error_log( 'AI Mastering Create Mastering WP_Error: ' . $mastering_response->get_error_message() );
            return $mastering_response;
        }

        $mastering_response_code = wp_remote_retrieve_response_code( $mastering_response );
        $mastering_response_body = wp_remote_retrieve_body( $mastering_response );
        $mastering_data = json_decode( $mastering_response_body, true );

        if ( $mastering_response_code !== 200 && $mastering_response_code !== 201 && $mastering_response_code !== 202 ) { // 202 Accepted is also common for job submissions
            $error_message = isset($mastering_data['message']) ? $mastering_data['message'] : 'Failed to create mastering job. Code: ' . $mastering_response_code;
            error_log( 'AI Mastering Create Mastering API Error: ' . $error_message . ' Body: ' . $mastering_response_body );
            return new WP_Error( 'mastering_create_failed', $error_message );
        }

        $mastering_job_id = isset( $mastering_data['id'] ) ? $mastering_data['id'] : null;
        if ( empty( $mastering_job_id ) ) {
            error_log( 'AI Mastering Create Mastering: No mastering job ID returned. Body: ' . $mastering_response_body );
            return new WP_Error( 'mastering_create_no_id', 'Mastering job created, but no Job ID returned.' );
        }

    }

    /**
     * Gets the status report for a given mastering job ID.
     *
     * @param string $job_id The AI Mastering Job ID (mastering_id).
     * @return array|WP_Error Report data on success, WP_Error on failure.
     */
    public function get_job_status_report( $job_id ) {
        $headers = $this->get_api_headers();
        if ( ! $headers ) {
            return new WP_Error( 'api_key_missing', 'AI Mastering API Key is not configured.' );
        }

        if ( empty( $job_id ) ) {
            return new WP_Error( 'job_id_missing', 'Job ID is required to fetch status.' );
        }

        $status_url = $this->api_base_url . '/masterings/' . $job_id;

        $status_args = [
            'method'  => 'GET',
            'headers' => $headers,
            'timeout' => 30,
        ];

        $status_response = wp_remote_get( $status_url, $status_args );

        if ( is_wp_error( $status_response ) ) {
            error_log( 'AI Mastering Get Status WP_Error for Job ID ' . $job_id . ': ' . $status_response->get_error_message() );
            return $status_response;
        }

        $response_code = wp_remote_retrieve_response_code( $status_response );
        $response_body = wp_remote_retrieve_body( $status_response );
        $report_data = json_decode( $response_body, true );

        if ( $response_code !== 200 ) {
            $error_message = isset($report_data['message']) ? $report_data['message'] : 'Failed to get job status. Code: ' . $response_code;
            error_log( 'AI Mastering Get Status API Error for Job ID ' . $job_id . ': ' . $error_message . ' Body: ' . $response_body );
            return new WP_Error( 'api_status_error', $error_message );
        }

        // The $report_data IS the report. You need to map its fields to your plugin's expectation.
        // Example mapping (ADJUST BASED ON ACTUAL API RESPONSE):
        // 'status' key might be 'status', 'state', etc.
        // 'report_data' could be the whole $report_data or a sub-object like $report_data['result']
        
        $mapped_report = [
            'id'          => isset($report_data['id']) ? $report_data['id'] : $job_id,
            'status'      => isset($report_data['status']) ? sanitize_text_field($report_data['status']) : 'unknown',
            'progress'    => isset($report_data['progress_percent']) ? intval($report_data['progress_percent']) : (isset($report_data['progress']) ? intval($report_data['progress']) : 0),
            'report_data' => $report_data, // Store the whole thing for flexibility
            'error_message' => isset($report_data['error']) ? $report_data['error'] : (isset($report_data['error_message']) ? $report_data['error_message'] : null),
            // Potentially add links to mastered files if present in $report_data['outputs'] or similar
        ];

        return $mapped_report;
    }

    // In class AIM_API_Handler

/**
 * Gets download information (like a temporary token or direct URL) for an audio ID.
 *
 * @param string $audio_id The ID of the audio file on AI Mastering.
 * @return array|WP_Error Download token/URL info or WP_Error.
 */
public function get_audio_download_details( $audio_id ) {
    $headers = $this->get_api_headers();
    if ( ! $headers ) return new WP_Error( 'api_key_missing', 'AI Mastering API Key missing.' );

    // First, try to get a download token
    $token_url = $this->api_base_url . '/audios/' . $audio_id . '/download_token';
    $token_response = wp_remote_get( $token_url, ['headers' => $headers, 'timeout' => 20] );

    if ( is_wp_error( $token_response ) ) return $token_response;

    $token_response_code = wp_remote_retrieve_response_code( $token_response );
    $token_body = wp_remote_retrieve_body( $token_response );
    $token_data = json_decode( $token_body, true );

    if ( $token_response_code === 200 && isset( $token_data['audio_download_token'] ) ) {
        return ['token' => $token_data['audio_download_token']];
    }

    // If token endpoint fails or not found, maybe there's a direct download (less likely for mastered files without token)
    // Or the /audios/{id}/download endpoint itself streams directly without needing a separate token call first.
    // This part needs to be confirmed by API documentation for /audios/{id}/download.

    // Fallback if no token logic above works, assume /audios/{id}/download might work directly
    // but this is risky as it might not be a public URL.
    // For now, let error if token not found.
    $error_message = isset($token_data['message']) ? $token_data['message'] : 'Could not get download token. Code: ' . $token_response_code;
    return new WP_Error('download_token_failed', $error_message . ' Body: ' . $token_body);
}


/**
 * Streams an audio file from AI Mastering using a download token or direct ID.
 *
 * @param string $identifier Can be an audio_id or an audio_download_token.
 * @param string $filename The desired filename for the download.
 * @param bool $is_token If true, $identifier is treated as a token.
 */
public function stream_file_via_api( $identifier, $filename = 'mastered_audio.wav', $is_token = false ) {
    $headers = $this->get_api_headers(); // Auth might be needed even for token-based downloads.
    if ( ! $headers ) {
        status_header(401);
        wp_die('API Key missing for download.');
    }

    if ($is_token) {
        $download_url = $this->api_base_url . '/audios/download_by_token?audio_download_token=' . urlencode($identifier);
    } else { // $identifier is an audio_id
        $download_url = $this->api_base_url . '/audios/' . $identifier . '/download';
    }

    // Make a streaming request. WordPress HTTP API is not ideal for true streaming proxying
    // of large files as it loads the whole response into memory.
    // For very large files, cURL direct might be better if server allows.

    $response = wp_remote_get( $download_url, [
        'headers' => $headers, // API may or may not require auth for this direct download URL
        'timeout' => 300,     // Long timeout for download
        'stream'  => false,   // Setting to true would save to a file, false gets body.
                              // True streaming proxy is harder with wp_remote_get.
    ]);

    if ( is_wp_error( $response ) ) {
        status_header(500);
        wp_die( 'Error contacting download server: ' . $response->get_error_message() );
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    $content_type = wp_remote_retrieve_header( $response, 'content-type' );

    if ( $response_code !== 200 ) {
        status_header($response_code);
        $body = wp_remote_retrieve_body($response);
        wp_die( 'Error downloading file from API. Status: ' . $response_code . '<br><pre>' . esc_html($body) . '</pre>' );
    }

    // Set headers for browser download
    header( 'Content-Description: File Transfer' );
    header( 'Content-Type: ' . ($content_type ?: 'application/octet-stream') );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"');
    header( 'Expires: 0' );
    header( 'Cache-Control: must-revalidate' );
    header( 'Pragma: public' );
    header( 'Content-Length: ' . wp_remote_retrieve_header( $response, 'content-length' ) );

    echo wp_remote_retrieve_body( $response );
    exit;
}
}