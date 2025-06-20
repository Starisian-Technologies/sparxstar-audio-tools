<?php
namespace SPARXSTAR\src\frontend; // Your namespace

if ( ! defined( 'ABSPATH' ) ) exit;

// Ensure your API Handler class is available. You might need to include/require it.
// Example: require_once SPARXSTAR_PLUGIN_DIR . 'includes/class-sparxat-api-handler.php';
// Or if you're using an autoloader that handles your namespace.

class SparxATFrontendHandler {

    private static $instance;
    private $cpt_slug = 'music_track'; // <<< CONFIRM YOUR CPT SLUG
    private $audio_file_meta_key = '_audio_attachment_id'; // <<< CONFIRM YOUR AUDIO META KEY

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Shortcodes
        add_shortcode( 'sparxat_mastering_status', [ $this, 'render_mastering_status_shortcode' ] );
        add_shortcode( 'sparxat_mastering_form', [ $this, 'render_mastering_form_shortcode' ] );

        // Enqueue assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

        // AJAX actions
        add_action( 'wp_ajax_sparxat_frontend_action', [ $this, 'handle_frontend_ajax_action' ] );
        add_action( 'wp_ajax_nopriv_sparxat_frontend_action', [ $this, 'handle_frontend_ajax_action' ] );

        add_action( 'wp_ajax_sparxat_download_mastered_file', [ $this, 'handle_download_mastered_file' ] );
        add_action( 'wp_ajax_nopriv_sparxat_download_mastered_file', [ $this, 'handle_download_mastered_file' ] );
    }

    public function enqueue_frontend_assets() {
        global $post;
        // Define SPARXSTAR_PLUGIN_URL and SPARXSTAR_VERSION in your main plugin file
        $plugin_url = defined('SPARXSTAR_PLUGIN_URL') ? SPARXSTAR_PLUGIN_URL : plugin_dir_url( __FILE__ . '/../../sparxstar-main-plugin-file.php'); // Adjust path if needed
        $plugin_version = defined('SPARXSTAR_VERSION') ? SPARXSTAR_VERSION : '1.0.0';

        if ( is_a( $post, 'WP_Post' ) && (
                has_shortcode( $post->post_content, 'sparxat_mastering_status' ) ||
                has_shortcode( $post->post_content, 'sparxat_mastering_form' ) ||
                get_post_type($post) === $this->cpt_slug
            )
        ) {
            wp_enqueue_style( 'sparxat-frontend-style', $plugin_url . 'assets/css/frontend-style.css', [], $plugin_version );
            wp_enqueue_script( 'sparxat-frontend-script', $plugin_url . 'assets/js/frontend-script.js', ['jquery'], $plugin_version, true );
            wp_localize_script( 'sparxat-frontend-script', 'sparxat_ajax_object', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'sparxat_frontend_nonce' ),
            ]);
        }
    }

    /**
     * Shortcode to display mastering status and report.
     * Usage: [sparxat_mastering_status post_id="123"]
     */
    public function render_mastering_status_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'post_id' => get_the_ID(),
        ], $atts, 'sparxat_mastering_status' );

        $post_id = absint( $atts['post_id'] );

        if ( ! $post_id || get_post_type( $post_id ) !== $this->cpt_slug ) {
            return current_user_can('edit_posts') ? '<p class="sparxat-error">Status: Invalid post ID or CPT.</p>' : '';
        }

        $job_id         = get_post_meta( $post_id, '_sparxat_job_id', true );
        $status         = get_post_meta( $post_id, '_sparxat_status', true );
        $report_data    = get_post_meta( $post_id, '_sparxat_report_data', true );
        $report_message = get_post_meta( $post_id, '_sparxat_report_message', true );
        $audio_file_id  = get_post_meta( $post_id, $this->audio_file_meta_key, true );

        ob_start();
        ?>
        <div class="sparxat-status-wrapper" data-postid="<?php echo esc_attr( $post_id ); ?>">
            <h3>AI Mastering Status for: <?php echo esc_html( get_the_title( $post_id ) ); ?></h3>

            <?php if ( empty( $status ) || empty( $job_id ) ) : ?>
                <?php if ( ! empty( $audio_file_id ) && get_attached_file( $audio_file_id ) ) : ?>
                    <p>This track has not yet been submitted for AI Mastering.</p>
                    <button class="sparxat-button sparxat-start-mastering-btn">Start AI Mastering (Default)</button>
                <?php else : ?>
                    <p>No audio file found for this track. Please upload an audio file first.</p>
                <?php endif; ?>
            <?php else : ?>
                <div class="sparxat-status-current">
                    <p><strong>Status:</strong> <span class="sparxat-status-text"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?></span></p>
                    <?php if ( $job_id ) : ?><p><strong>Job ID:</strong> <?php echo esc_html( $job_id ); ?></p><?php endif; ?>
                    <?php if ( $report_message ) : ?><p><em><?php echo esc_html( $report_message ); ?></em></p><?php endif; ?>
                </div>

                <?php if ( in_array( $status, ['submitted_processing', 'processing', 'submitted_processing_form'] ) ) : ?>
                    <button class="sparxat-button sparxat-refresh-status-btn">Refresh Status</button>
                <?php endif; ?>

                <?php if ( $status === 'completed' && ! empty( $report_data ) && is_array($report_data) ) : ?>
                    <h4>Mastering Report:</h4>
                    <pre class="sparxat-report-data"><?php echo esc_html( print_r( $report_data['report_data'] ?? $report_data, true ) ); // Prefer 'report_data' sub-array if exists ?></pre>
                    <?php
                    // Download Link Logic (ensure $report_data is the full API response for GET /masterings/{id})
                    $download_url = '';
                    $output_audio_id = null;
                    $actual_report_content = $report_data['report_data'] ?? $report_data; // The actual content from API

                    if (isset($actual_report_content['result_audio_id'])) {
                        $output_audio_id = $actual_report_content['result_audio_id'];
                    } elseif (isset($actual_report_content['output_audio_id'])) {
                        $output_audio_id = $actual_report_content['output_audio_id'];
                    } elseif (isset($actual_report_content['outputs']) && is_array($actual_report_content['outputs']) && !empty($actual_report_content['outputs'][0]['id'])) {
                         $output_audio_id = $actual_report_content['outputs'][0]['id'];
                    } elseif (isset($actual_report_content['outputs']) && is_array($actual_report_content['outputs']) && !empty($actual_report_content['outputs'][0]['url'])) {
                         $download_url = $actual_report_content['outputs'][0]['url'];
                    }

                    if ( $output_audio_id ) {
                        $ajax_download_url = add_query_arg([
                            'action' => 'sparxat_download_mastered_file',
                            'post_id' => $post_id,
                            'output_audio_id' => $output_audio_id,
                            '_ajax_nonce' => wp_create_nonce('sparxat_download_mastered_file_' . $post_id . '_' . $output_audio_id)
                        ], admin_url('admin-ajax.php'));
                        echo '<a href="' . esc_url( $ajax_download_url ) . '" class="sparxat-button sparxat-download-btn" target="_blank">Download Mastered Track</a>';
                    } elseif ($download_url) {
                         echo '<a href="' . esc_url( $download_url ) . '" class="sparxat-button sparxat-download-btn" target="_blank" rel="noopener noreferrer">Download Mastered Track (Direct)</a>';
                    } else {
                        echo '<p>Mastered file download information not found in the report.</p>';
                    }
                    ?>
                <?php elseif ( in_array($status, ['submission_failed', 'failed', 'error_fetching_status', 'submission_failed_form'] ) ) : ?>
                    <p class="sparxat-error">There was an issue with the mastering process.</p>
                    <?php if ( ! empty( $audio_file_id ) && get_attached_file( $audio_file_id ) ) : ?>
                        <button class="sparxat-button sparxat-start-mastering-btn sparxat-retry-btn">Retry Mastering (Default)</button>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            <div class="sparxat-ajax-message" style="display:none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode to display mastering options form.
     * Usage: [sparxat_mastering_form post_id="123"]
     */
    public function render_mastering_form_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'post_id' => get_the_ID(),
        ], $atts, 'sparxat_mastering_form' );

        $post_id = absint( $atts['post_id'] );

        if ( ! $post_id || get_post_type( $post_id ) !== $this->cpt_slug ) {
            return current_user_can('edit_posts') ? '<p class="sparxat-error">Form: Invalid post ID or CPT.</p>' : '';
        }

        $job_id        = get_post_meta( $post_id, '_sparxat_job_id', true );
        $current_status= get_post_meta( $post_id, '_sparxat_status', true );
        $audio_file_id = get_post_meta( $post_id, $this->audio_file_meta_key, true );

        ob_start();
        ?>
        <div class="sparxat-mastering-form-wrapper" data-postid="<?php echo esc_attr( $post_id ); ?>">
            <h3>Master Your Track: <?php echo esc_html( get_the_title( $post_id ) ); ?></h3>

            <?php if ( empty( $audio_file_id ) || ! get_attached_file( $audio_file_id ) ) : ?>
                <p class="sparxat-message sparxat-warning">An audio file must be uploaded and associated with this track before mastering options are available.</p>
            <?php elseif ( $job_id && !in_array($current_status, ['failed', 'submission_failed', 'error_fetching_status', 'cancelled', 'submission_failed_form']) ) : ?>
                <p class="sparxat-message sparxat-info">This track is currently being processed or has already been mastered. Status: <?php echo esc_html( ucfirst( str_replace('_', ' ', $current_status ) ) ); ?>.</p>
                <p>View report using <code>[sparxat_mastering_status post_id="<?php echo esc_attr($post_id); ?>"]</code>.</p>
            <?php else : ?>
                <form id="sparxat-mastering-options-form-<?php echo esc_attr($post_id); /* Unique ID for form */ ?>" class="sparxat-mastering-options-form">
                    <input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
                    <input type="hidden" name="action" value="sparxat_frontend_action">
                    <input type="hidden" name="sub_action" value="submit_mastering_with_options">
                    <input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr( wp_create_nonce( 'sparxat_frontend_nonce' ) ); ?>">

                    <div class="sparxat-form-field">
                        <label for="sparxat_target_loudness_<?php echo esc_attr($post_id); ?>">Target Loudness (LUFS):</label>
                        <input type="number" id="sparxat_target_loudness_<?php echo esc_attr($post_id); ?>" name="mastering_options[targetLoudness]" step="0.1" value="<?php echo esc_attr(get_option('sparxat_default_target_loudness', -10.0)); ?>" min="-24" max="0">
                    </div>
                    <div class="sparxat-form-field">
                        <label for="sparxat_output_format_<?php echo esc_attr($post_id); ?>">Output Format:</label>
                        <select id="sparxat_output_format_<?php echo esc_attr($post_id); ?>" name="mastering_options[outputFormat]">
                            <?php
                            $output_formats = ['wav' => 'WAV', 'mp3' => 'MP3'];
                            $default_output_format = get_option('sparxat_default_output_format', 'wav');
                            foreach ($output_formats as $val => $label) {
                                echo '<option value="' . esc_attr($val) . '" ' . selected($default_output_format, $val, false) . '>' . esc_html($label) . '</option>';
                            } ?>
                        </select>
                    </div>
                    <div class="sparxat-form-field">
                        <label for="sparxat_mastering_algorithm_<?php echo esc_attr($post_id); ?>">Mastering Algorithm:</label>
                        <select id="sparxat_mastering_algorithm_<?php echo esc_attr($post_id); ?>" name="mastering_options[masteringAlgorithm]">
                            <?php
                            $algorithms = ['v2' => 'Version 2 (Default)', 'v3' => 'Version 3 (If available)'];
                            $default_algorithm = get_option('sparxat_default_mastering_algorithm', 'v2');
                            foreach ($algorithms as $val => $label) {
                                echo '<option value="' . esc_attr($val) . '" ' . selected($default_algorithm, $val, false) . '>' . esc_html($label) . '</option>';
                            } ?>
                        </select>
                    </div>
                    <div class="sparxat-form-field">
                        <label for="sparxat_bass_preservation_<?php echo esc_attr($post_id); ?>">
                            <input type="checkbox" id="sparxat_bass_preservation_<?php echo esc_attr($post_id); ?>" name="mastering_options[bassPreservation]" value="true" <?php checked(get_option('sparxat_default_bass_preservation', false)); ?>>
                            Preserve Bass
                        </label>
                    </div>
                    <div class="sparxat-form-field">
                        <button type="submit" class="sparxat-button sparxat-submit-mastering-options-btn">Master This Track with Options</button>
                    </div>
                </form>
                <div class="sparxat-ajax-message" style="display:none;"></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handles frontend AJAX actions for mastering.
     */
    public function handle_frontend_ajax_action() {
        check_ajax_referer( 'sparxat_frontend_nonce', '_ajax_nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $sub_action = isset( $_POST['sub_action'] ) ? sanitize_text_field( $_POST['sub_action'] ) : '';

        if ( ! $post_id || get_post_type( $post_id ) !== $this->cpt_slug ) {
            wp_send_json_error( ['message' => 'Invalid Post ID for AJAX action.'] );
        }

        // Ensure SparxATAPIHandler is loaded correctly
        // Replace with your actual class name if different or ensure autoloader works
        if (!class_exists('\SPARXSTAR\src\includes\SparxATAPIHandler')) { // Assuming full namespace
             // Try to include it if not autoloaded
             $api_handler_path = SPARXSTAR_PLUGIN_DIR . 'includes/class-sparxat-api-handler.php'; // Adjust path
             if (file_exists($api_handler_path)) {
                 require_once $api_handler_path;
             } else {
                 wp_send_json_error( ['message' => 'API Handler class not found.'] );
                 return;
             }
        }
        $api_handler = \SPARXSTAR\src\includes\SparxATAPIHandler::instance();


        switch ( $sub_action ) {
            case 'start_mastering': // For the simple "Start Mastering" button
                $audio_attachment_id = get_post_meta( $post_id, $this->audio_file_meta_key, true );
                $file_path = $audio_attachment_id ? get_attached_file( $audio_attachment_id ) : null;

                if ( ! $file_path || ! file_exists( $file_path ) ) {
                    wp_send_json_error( ['message' => 'Audio file not found or path is invalid.'] );
                }

                delete_post_meta( $post_id, '_sparxat_job_id' );
                delete_post_meta( $post_id, '_sparxat_report_data' );
                delete_post_meta( $post_id, '_sparxat_report_message' );
                update_post_meta( $post_id, '_sparxat_status', 'pending_submission' );

                // Default mastering parameters (from plugin settings)
                $mastering_params = [
                    'targetLoudness' => (float) get_option( 'sparxat_default_target_loudness', -10.0 ),
                    'outputFormat'   => sanitize_text_field(get_option('sparxat_default_output_format', 'wav')),
                    'masteringAlgorithm' => sanitize_text_field(get_option('sparxat_default_mastering_algorithm', 'v2')),
                    'bassPreservation' => get_option('sparxat_default_bass_preservation', false) ? 'true' : 'false',
                    // Add other defaults here based on API docs for POST /masterings
                ];

                $job_id_or_error = $api_handler->submit_audio_for_initial_process( $file_path, $mastering_params );

                if ( is_wp_error( $job_id_or_error ) ) {
                    update_post_meta( $post_id, '_sparxat_status', 'submission_failed' );
                    update_post_meta( $post_id, '_sparxat_report_message', $job_id_or_error->get_error_message() );
                    wp_send_json_error( ['message' => $job_id_or_error->get_error_message()] );
                } else {
                    update_post_meta( $post_id, '_sparxat_job_id', $job_id_or_error );
                    update_post_meta( $post_id, '_sparxat_status', 'submitted_processing' );
                    update_post_meta( $post_id, '_sparxat_report_message', 'Submitted (default). Job ID: ' . $job_id_or_error );
                    wp_send_json_success( ['message' => 'Mastering initiated (default).', 'job_id' => $job_id_or_error] );
                }
                break;

            case 'submit_mastering_with_options': // For the form submission
                $mastering_options_raw = isset( $_POST['mastering_options'] ) && is_array( $_POST['mastering_options'] )
                                        ? $_POST['mastering_options']
                                        : [];
                $api_mastering_params = [];

                if ( isset( $mastering_options_raw['targetLoudness'] ) ) {
                    $api_mastering_params['targetLoudness'] = (float) $mastering_options_raw['targetLoudness'];
                }
                if ( isset( $mastering_options_raw['outputFormat'] ) && in_array( $mastering_options_raw['outputFormat'], ['wav', 'mp3'] ) ) {
                    $api_mastering_params['outputFormat'] = sanitize_text_field( $mastering_options_raw['outputFormat'] );
                }
                if ( isset( $mastering_options_raw['masteringAlgorithm'] ) ) {
                     $api_mastering_params['masteringAlgorithm'] = sanitize_text_field( $mastering_options_raw['masteringAlgorithm'] );
                }
                if ( isset( $mastering_options_raw['bassPreservation'] ) ) {
                    $api_mastering_params['bassPreservation'] = ($mastering_options_raw['bassPreservation'] === 'true' || $mastering_options_raw['bassPreservation'] === true) ? 'true' : 'false';
                }
                // Add more options here, ensuring keys match API (camelCase)

                $audio_attachment_id = get_post_meta( $post_id, $this->audio_file_meta_key, true );
                $file_path = $audio_attachment_id ? get_attached_file( $audio_attachment_id ) : null;

                if ( ! $file_path || ! file_exists( $file_path ) ) {
                    wp_send_json_error( ['message' => 'Audio file not found for Post ID ' . $post_id] );
                }

                delete_post_meta( $post_id, '_sparxat_job_id' );
                delete_post_meta( $post_id, '_sparxat_report_data' );
                delete_post_meta( $post_id, '_sparxat_report_message' );
                update_post_meta( $post_id, '_sparxat_status', 'pending_submission_form' );

                $job_id_or_error = $api_handler->submit_audio_for_initial_process( $file_path, $api_mastering_params );

                if ( is_wp_error( $job_id_or_error ) ) {
                    update_post_meta( $post_id, '_sparxat_status', 'submission_failed_form' );
                    update_post_meta( $post_id, '_sparxat_report_message', $job_id_or_error->get_error_message() );
                    wp_send_json_error( ['message' => $job_id_or_error->get_error_message()] );
                } else {
                    update_post_meta( $post_id, '_sparxat_job_id', $job_id_or_error );
                    update_post_meta( $post_id, '_sparxat_status', 'submitted_processing_form' );
                    update_post_meta( $post_id, '_sparxat_report_message', 'Submitted (form). Job ID: ' . $job_id_or_error );
                    wp_send_json_success( [
                        'message' => 'Mastering initiated with options.',
                        'job_id' => $job_id_or_error,
                    ] );
                }
                break;

            case 'refresh_status':
                $job_id = get_post_meta( $post_id, '_sparxat_job_id', true );
                if ( empty( $job_id ) ) {
                    wp_send_json_error( ['message' => 'No Job ID to refresh.'] );
                }

                $report_or_error = $api_handler->get_job_status_report( $job_id );

                if ( is_wp_error( $report_or_error ) ) {
                    update_post_meta( $post_id, '_sparxat_status', 'error_fetching_status' );
                    update_post_meta( $post_id, '_sparxat_report_message', 'Error refreshing: ' . $report_or_error->get_error_message() );
                    wp_send_json_error( ['message' => 'Error refreshing: ' . $report_or_error->get_error_message()] );
                } else {
                    $new_status = isset($report_or_error['status']) ? $report_or_error['status'] : 'unknown';
                    $new_report_data = $report_or_error['report_data'] ?? $report_or_error; // Use the whole thing if 'report_data' key isn't there

                    update_post_meta( $post_id, '_sparxat_status', $new_status );
                    update_post_meta( $post_id, '_sparxat_report_data', $new_report_data );

                    $message = 'Status: ' . ucfirst(str_replace('_', ' ', $new_status)) . '.';
                    if (!empty($report_or_error['error_message'])) {
                        $message .= ' Error: ' . $report_or_error['error_message'];
                    } elseif (isset($new_report_data['message'])) { // Message from the actual API report content
                         $message .= ' API Message: ' . $new_report_data['message'];
                    }
                    update_post_meta( $post_id, '_sparxat_report_message', $message);

                    $new_html_output = $this->render_mastering_status_shortcode(['post_id' => $post_id]);
                    // Also consider re-rendering the form shortcode if status allows retrying form
                    // $new_form_html = $this->render_mastering_form_shortcode(['post_id' => $post_id]);


                    wp_send_json_success( [
                        'message' => 'Status refreshed.',
                        'status_text' => ucfirst(str_replace('_', ' ', $new_status)),
                        'report_data' => $new_report_data,
                        'html_status' => $new_html_output, // Send updated HTML for the status shortcode
                        // 'html_form' => $new_form_html // Optionally send updated form HTML too
                    ] );
                }
                break;

            default:
                wp_send_json_error( ['message' => 'Invalid sub-action.'] );
                break;
        }
        wp_die(); // this is required to terminate immediately and return a proper response
    }


    /**
     * Handles AJAX request to download the mastered file.
     */
    public function handle_download_mastered_file() {
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        $output_audio_id = isset($_GET['output_audio_id']) ? sanitize_text_field($_GET['output_audio_id']) : null;

        check_ajax_referer('sparxat_download_mastered_file_' . $post_id . '_' . $output_audio_id, '_ajax_nonce');

        if (!$post_id || !$output_audio_id) {
            wp_die('Invalid download parameters.', 'Download Error', ['response' => 400]);
        }

        // Ensure SparxATAPIHandler is loaded
        if (!class_exists('\SPARXSTAR\src\includes\SparxATAPIHandler')) {
             $api_handler_path = SPARXSTAR_PLUGIN_DIR . 'includes/class-sparxat-api-handler.php'; // Adjust path
             if (file_exists($api_handler_path)) { require_once $api_handler_path; }
             else { wp_die('API Handler class for download not found.'); }
        }
        $api_handler = \SPARXSTAR\src\includes\SparxATAPIHandler::instance();
        $report_data = get_post_meta($post_id, '_sparxat_report_data', true);
        $actual_report_content = $report_data['report_data'] ?? $report_data;


        $direct_download_url = null;
        if (isset($actual_report_content['outputs']) && !empty($actual_report_content['outputs'][0]['url'])) {
            $direct_download_url = $actual_report_content['outputs'][0]['url'];
        } elseif (isset($actual_report_content['result_audio_url'])) {
            $direct_download_url = $actual_report_content['result_audio_url'];
        }

        if ($direct_download_url) {
            wp_redirect($direct_download_url);
            exit;
        }

        $output_audio_id_from_report = null;
        if (isset($actual_report_content['result_audio_id'])) {
            $output_audio_id_from_report = $actual_report_content['result_audio_id'];
        } elseif (isset($actual_report_content['output_audio_id'])) {
            $output_audio_id_from_report = $actual_report_content['output_audio_id'];
        } elseif (isset($actual_report_content['outputs']) && is_array($actual_report_content['outputs']) && !empty($actual_report_content['outputs'][0]['id'])) {
            $output_audio_id_from_report = $actual_report_content['outputs'][0]['id'];
        }

        if ($output_audio_id_from_report && $output_audio_id_from_report === $output_audio_id) {
            $download_details = $api_handler->get_audio_download_details($output_audio_id); // This method is in SparxATAPIHandler

            if (!is_wp_error($download_details) && isset($download_details['token'])) {
                $filename = get_the_title($post_id) ? sanitize_file_name(get_the_title($post_id) . '_mastered.wav') : 'mastered_audio.wav';
                $api_handler->stream_file_via_api($download_details['token'], $filename, true); // This method is in SparxATAPIHandler
                exit;
            } else {
                // Try direct download by ID if token failed & API might support it
                // Check if SparxATAPIHandler::stream_file_via_api handles the case where $is_token is false
                // and it tries GET /audios/{id}/download
                $error_msg = is_wp_error($download_details) ? $download_details->get_error_message() : 'Could not obtain a download token.';
                // If direct download by ID is a valid fallback:
                // $filename = get_the_title($post_id) ? sanitize_file_name(get_the_title($post_id) . '_mastered.wav') : 'mastered_audio.wav';
                // $api_handler->stream_file_via_api($output_audio_id, $filename, false); // Attempt direct ID download
                // exit;
                wp_die('Download Error: ' . esc_html($error_msg));
            }
        } else {
            wp_die('Mastered file ID is missing or inconsistent in the report. Cannot download.');
        }
        wp_die(); // Should not reach here if download succeeds or other wp_die occurs
    }
} // End class SparxATFrontendHandler