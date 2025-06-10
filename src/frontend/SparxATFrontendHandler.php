<?php

class SparxATFrontendHandler {

    // Continuing SparxAT_Shortcodes class from above...

public function handle_frontend_ajax_action() {
    check_ajax_referer( 'SparxAT_frontend_nonce', '_ajax_nonce' );

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $sub_action = isset( $_POST['sub_action'] ) ? sanitize_text_field( $_POST['sub_action'] ) : '';

    if ( ! $post_id || get_post_type( $post_id ) !== 'music_track' /* <<< YOUR CPT SLUG */ ) {
        wp_send_json_error( ['message' => 'Invalid Post ID.'] );
    }

    // Add permission checks here if needed: e.g., if (!current_user_can('edit_post', $post_id)) { ... }

    $api_handler = SparxAT_API_Handler::instance(); // Make sure this class is loaded

    switch ( $sub_action ) {
        case 'start_mastering':
            $audio_attachment_id = get_post_meta( $post_id, '_audio_attachment_id', true ); // Or your file path meta key
            $file_path = $audio_attachment_id ? get_attached_file( $audio_attachment_id ) : null;

            if ( ! $file_path || ! file_exists( $file_path ) ) {
                wp_send_json_error( ['message' => 'Audio file not found or path is invalid.'] );
            }

            // Clear previous job data if retrying
            delete_post_meta( $post_id, '_SparxAT_job_id' );
            delete_post_meta( $post_id, '_SparxAT_report_data' );
            delete_post_meta( $post_id, '_SparxAT_report_message' );
            update_post_meta( $post_id, '_SparxAT_status', 'pending_submission' );


            // Parameters for mastering (get from plugin settings or defaults)
            $mastering_params = [
                'target_loudness' => (float) get_option( 'SparxAT_target_loudness', -10.0 ),
                // Add other relevant parameters for POST /masterings from your settings
            ];

            // This is the combined "upload audio" & "create mastering" call
            $job_id_or_error = $api_handler->submit_audio_for_initial_process( $file_path, $mastering_params );

            if ( is_wp_error( $job_id_or_error ) ) {
                update_post_meta( $post_id, '_SparxAT_status', 'submission_failed' );
                update_post_meta( $post_id, '_SparxAT_report_message', $job_id_or_error->get_error_message() );
                wp_send_json_error( ['message' => $job_id_or_error->get_error_message()] );
            } else {
                update_post_meta( $post_id, '_SparxAT_job_id', $job_id_or_error );
                update_post_meta( $post_id, '_SparxAT_status', 'submitted_processing' );
                update_post_meta( $post_id, '_SparxAT_report_message', 'Submitted to AI Mastering. Job ID: ' . $job_id_or_error );
                wp_send_json_success( ['message' => 'Mastering process initiated.', 'job_id' => $job_id_or_error] );
            }
            break;

        case 'refresh_status':
            $job_id = get_post_meta( $post_id, '_SparxAT_job_id', true );
            if ( empty( $job_id ) ) {
                wp_send_json_error( ['message' => 'No Job ID found for this track to refresh.'] );
            }

            $report_or_error = $api_handler->get_job_status_report( $job_id );

            if ( is_wp_error( $report_or_error ) ) {
                update_post_meta( $post_id, '_SparxAT_status', 'error_fetching_status' );
                // Don't overwrite good report data with an error, but update message
                update_post_meta( $post_id, '_SparxAT_report_message', 'Error refreshing status: ' . $report_or_error->get_error_message() );
                wp_send_json_error( ['message' => 'Error refreshing status: ' . $report_or_error->get_error_message()] );
            } else {
                // $report_or_error is already the mapped array from get_job_status_report
                $new_status = isset($report_or_error['status']) ? $report_or_error['status'] : 'unknown';
                update_post_meta( $post_id, '_SparxAT_status', $new_status );
                update_post_meta( $post_id, '_SparxAT_report_data', $report_or_error['report_data'] ); // Store the full sub-report
                
                $message = 'Status updated: ' . ucfirst(str_replace('_', ' ', $new_status)) . '.';
                if (isset($report_or_error['error_message']) && $report_or_error['error_message']) {
                    $message .= ' Error: ' . $report_or_error['error_message'];
                } elseif (isset($report_or_error['report_data']['message'])) {
                     $message .= ' API Message: ' . $report_or_error['report_data']['message'];
                }
                update_post_meta( $post_id, '_SparxAT_report_message', $message);
                
                // Re-render the shortcode content to send back for dynamic update
                $new_html_output = $this->render_mastering_status_shortcode(['post_id' => $post_id]);

                wp_send_json_success( [
                    'message' => 'Status refreshed successfully.',
                    'status_text' => ucfirst(str_replace('_', ' ', $new_status)),
                    'report_data' => $report_or_error['report_data'], // Send back the new report
                    'html' => $new_html_output // Send updated HTML of the shortcode
                ] );
            }
            break;

        default:
            wp_send_json_error( ['message' => 'Invalid sub-action.'] );
            break;
    }
    // In SparxAT_Shortcodes::handle_frontend_ajax_action()
// ...
    switch ( $sub_action ) {
        case 'start_mastering':
            // ... (existing code for the button in [SparxAT_mastering_status]) ...
            break;

        case 'submit_mastering_with_options': // New case for the form
            $mastering_options_raw = isset( $_POST['mastering_options'] ) && is_array( $_POST['mastering_options'] )
                                    ? $_POST['mastering_options']
                                    : [];

            // Sanitize and prepare mastering options
            $api_mastering_params = [];

            // --- Sanitize and map options from $mastering_options_raw to $api_mastering_params ---
            // --- Ensure names match the API's expected multipart form field names ---
            // Based on SparxATastering.MasteringApi.createMastering documentation:

            if ( isset( $mastering_options_raw['targetLoudness'] ) ) {
                $api_mastering_params['targetLoudness'] = (float) $mastering_options_raw['targetLoudness'];
            }
            if ( isset( $mastering_options_raw['outputFormat'] ) && in_array( $mastering_options_raw['outputFormat'], ['wav', 'mp3'] ) ) { // Whitelist
                $api_mastering_params['outputFormat'] = sanitize_text_field( $mastering_options_raw['outputFormat'] );
            }
            if ( isset( $mastering_options_raw['masteringAlgorithm'] ) ) { // Whitelist if known values
                 $api_mastering_params['masteringAlgorithm'] = sanitize_text_field( $mastering_options_raw['masteringAlgorithm'] );
            }

            // Boolean parameters: API expects boolean, form sends "true" or "false" string, or parameter is absent if unchecked
            if ( isset( $mastering_options_raw['bassPreservation'] ) ) {
                // The multipart construction in API handler will send this as a string "true" or "false".
                // The API needs to be tolerant of this or PHP handler should send 1/0 if API needs that for booleans in multipart.
                // For now, assume string "true"/"false" is okay as it's form data.
                $api_mastering_params['bassPreservation'] = ($mastering_options_raw['bassPreservation'] === 'true' || $mastering_options_raw['bassPreservation'] === true) ? 'true' : 'false';
            }
            // Example for 'mode':
            // if (isset($mastering_options_raw['mode'])) {
            //     $api_mastering_params['mode'] = sanitize_text_field($mastering_options_raw['mode']);
            // }


            // Get file path
            $audio_attachment_id = get_post_meta( $post_id, '_audio_attachment_id', true );
            $file_path = $audio_attachment_id ? get_attached_file( $audio_attachment_id ) : null;

            if ( ! $file_path || ! file_exists( $file_path ) ) {
                wp_send_json_error( ['message' => 'Audio file not found or path is invalid for Post ID ' . $post_id] );
            }

            // Clear previous job data for a fresh submission
            delete_post_meta( $post_id, '_SparxAT_job_id' );
            delete_post_meta( $post_id, '_SparxAT_report_data' );
            delete_post_meta( $post_id, '_SparxAT_report_message' );
            update_post_meta( $post_id, '_SparxAT_status', 'pending_submission_form' ); // Differentiate if needed

            // Call the API Handler
            // The $api_mastering_params here will be passed as the second arg to submit_audio_for_initial_process
            // and will be used to construct the multipart form data for POST /masterings
            $job_id_or_error = $api_handler->submit_audio_for_initial_process( $file_path, $api_mastering_params );

            if ( is_wp_error( $job_id_or_error ) ) {
                update_post_meta( $post_id, '_SparxAT_status', 'submission_failed_form' );
                update_post_meta( $post_id, '_SparxAT_report_message', $job_id_or_error->get_error_message() );
                wp_send_json_error( ['message' => $job_id_or_error->get_error_message()] );
            } else {
                update_post_meta( $post_id, '_SparxAT_job_id', $job_id_or_error );
                update_post_meta( $post_id, '_SparxAT_status', 'submitted_processing_form' );
                update_post_meta( $post_id, '_SparxAT_report_message', 'Submitted via form. Job ID: ' . $job_id_or_error );
                wp_send_json_success( [
                    'message' => 'Mastering process initiated with selected options.',
                    'job_id' => $job_id_or_error,
                    // 'status_page_url' => get_permalink($post_id) // Example
                ] );
            }
            break;

        case 'refresh_status':
            // ... (existing code) ...
            break;
        default:
            wp_send_json_error( ['message' => 'Invalid sub-action specified.'] );
            break;
    }
// ...
}
}

public function handle_download_mastered_file() {
    $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
    $output_audio_id = isset($_GET['output_audio_id']) ? sanitize_text_field($_GET['output_audio_id']) : null;

    // Nonce check should be specific to this action and include post_id & output_audio_id
    check_ajax_referer('SparxAT_download_mastered_file_' . $post_id . '_' . $output_audio_id, '_ajax_nonce');

    if (!$post_id || !$output_audio_id) {
        wp_die('Invalid download parameters.', 'Download Error', ['response' => 400]);
    }
    // Inside handle_download_mastered_file after nonce check...
    $api_handler = SparxAT_API_Handler::instance();
    $report_data = get_post_meta($post_id, '_SparxAT_report_data', true);

    // Try to get a direct URL first from the report (most ideal)
    $direct_download_url = null;
    // ... (logic to find direct_download_url as before) ...

    if ($direct_download_url) {
        wp_redirect($direct_download_url); // Use wp_redirect for safety
        exit;
    }

    // If no direct URL, try using output_audio_id to get a token then stream
    $output_audio_id_from_report = null;
    if (isset($report_data['result_audio_id'])) {
        $output_audio_id_from_report = $report_data['result_audio_id'];
    } elseif (isset($report_data['output_audio_id'])) {
        $output_audio_id_from_report = $report_data['output_audio_id'];
    } // etc.

    if ($output_audio_id_from_report && $output_audio_id_from_report === $output_audio_id) { // Ensure consistency
        $download_details = $api_handler->get_audio_download_details($output_audio_id);

        if (!is_wp_error($download_details) && isset($download_details['token'])) {
            // We have a token, stream using the token
            $filename = get_the_title($post_id) ? sanitize_file_name(get_the_title($post_id) . '_mastered.wav') : 'mastered_audio.wav';
            $api_handler->stream_file_via_api($download_details['token'], $filename, true); // true for is_token
            exit; // stream_file_via_api should exit
        } else {
            // No token, try streaming by ID (if API supports GET /audios/{id}/download directly)
            // This is less certain to work without prior token exchange for some APIs.
            // Check AI Mastering API docs if GET /audios/{id}/download is a direct file stream.
            // If it is, then you can call:
            // $filename = get_the_title($post_id) ? sanitize_file_name(get_the_title($post_id) . '_mastered.wav') : 'mastered_audio.wav';
            // $api_handler->stream_file_via_api($output_audio_id, $filename, false); // false for is_token
            // exit;

            $error_msg = is_wp_error($download_details) ? $download_details->get_error_message() : 'Could not obtain a valid download token.';
            wp_die('Download Error: ' . esc_html($error_msg));
        }
    } else {
        wp_die('Mastered file information is missing or inconsistent in the report.');
    }
    // Optional: Permission check (e.g., does current user own this track or have purchase rights?)
    // if (!current_user_can('download_mastered_track', $post_id)) { // Custom capability
    //     wp_die('You do not have permission to download this file.', 'Permission Denied', ['response' => 403]);
    // }

    $api_handler = SparxAT_API_Handler::instance();

    // This method needs to be created in SparxAT_API_Handler
    // It should fetch the actual audio file stream from the API.
    // E.g., by first getting a temporary download URL/token using $output_audio_id via:
    // GET /audios/{output_audio_id}/download_token -> gives { "audio_download_token": "..." }
    // Then GET /audios/download_by_token?audio_download_token={token} -> streams file
    // OR directly GET /audios/{output_audio_id}/download -> streams file

    // For now, let's assume a placeholder function that returns a direct URL or file path from API.
    // In a real scenario, you'd handle streaming.

    // --- This is where the new API Handler method would be called ---
    // $download_details = $api_handler->get_mastered_audio_stream_details($output_audio_id);
    // if (is_wp_error($download_details)) {
    //     wp_die('Could not retrieve download details: ' . $download_details->get_error_message(), 'Download Error');
    // }

    // If $api_handler->get_mastered_audio_stream_details directly streams and exits:
    // $api_handler->stream_mastered_audio($output_audio_id); // This function would set headers and echo file content then die().

    // --- SIMPLIFIED Placeholder: Assume we got a direct, public S3 URL or similar ---
    // The actual GET /masterings/{id} might return a direct download URL in `report_data`
    $report_data = get_post_meta($post_id, '_SparxAT_report_data', true);
    $direct_download_url = null;
    if (isset($report_data['outputs']) && !empty($report_data['outputs'][0]['url'])) {
        $direct_download_url = $report_data['outputs'][0]['url'];
    } elseif (isset($report_data['result_audio_url'])) { // another hypothetical field
        $direct_download_url = $report_data['result_audio_url'];
    }


    if ($direct_download_url) {
        header('Location: ' . $direct_download_url);
        exit;
    } else {
        // Fallback if no direct URL: You MUST implement the streaming via your server
        // by calling the AI Mastering API endpoints that provide the file data.
        // This part is highly dependent on the exact API response for completed mastering.
        // For example, if you get an $output_audio_id, you'd use it with
        // GET /audios/{id}/download or GET /audios/{id}/download_token + GET /audios/download_by_token

        // Example of initiating a stream if your SparxAT_API_Handler had such a method:
        // $api_handler->stream_file_via_api($output_audio_id, "mastered_track.wav");
        // This stream_file_via_api function would make the API call to get the file data
        // and then echo it with appropriate headers.
        
        wp_die('Download functionality not fully configured for this report structure, or direct URL missing. Developer needs to implement server-side streaming for output_audio_id: ' . esc_html($output_audio_id), 'Download Error');
    }
}
}