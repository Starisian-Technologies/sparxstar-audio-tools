<?php
namespace SPARXSTAR\src\frontend;
// In includes/class-SparxAT-shortcodes.php (or main plugin file)

if ( ! defined( 'ABSPATH' ) ) exit;

class SparxATShortcodes {

    private static $instance;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'SparxAT_mastering_status', [ $this, 'render_mastering_status_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

        // AJAX action for frontend interactions
        add_action( 'wp_ajax_SparxAT_frontend_action', [ $this, 'handle_frontend_ajax_action' ] );
        add_action( 'wp_ajax_nopriv_SparxAT_frontend_action', [ $this, 'handle_frontend_ajax_action' ] ); // For non-logged-in users if applicable

        // AJAX action for download (could also be a REST API endpoint)
        add_action( 'wp_ajax_SparxAT_download_mastered_file', [ $this, 'handle_download_mastered_file' ] );
        add_action( 'wp_ajax_nopriv_SparxAT_download_mastered_file', [ $this, 'handle_download_mastered_file' ] );
    }

    public function enqueue_frontend_assets() {
        // Only enqueue if the shortcode is likely present or on specific pages.
        // For simplicity, let's assume it could be anywhere for now.
        // A more optimized way is to enqueue only when the shortcode is actually used.
        global $post;
        if ( is_a( $post, 'WP_Post' ) && (has_shortcode( $post->post_content, 'SparxAT_mastering_status' ) || get_post_type($post) === 'music_track' /* your CPT */) ) {
            wp_enqueue_style( 'SparxAT-frontend-style', SparxAT_PLUGIN_URL . 'assets/css/frontend-style.css', [], SparxAT_VERSION ); // Define SparxAT_VERSION
            wp_enqueue_script( 'SparxAT-frontend-script', SparxAT_PLUGIN_URL . 'assets/js/frontend-script.js', ['jquery'], SparxAT_VERSION, true );
            wp_localize_script( 'SparxAT-frontend-script', 'SparxAT_ajax_object', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'SparxAT_frontend_nonce' ),
                // 'download_nonce' => wp_create_nonce( 'SparxAT_download_nonce'), // If using separate nonce for download
            ]);
        }
    }

    public function render_mastering_status_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'post_id' => get_the_ID(), // Default to current post ID
        ], $atts, 'SparxAT_mastering_status' );

        $post_id = absint( $atts['post_id'] );

        if ( ! $post_id || get_post_type( $post_id ) !== 'music_track' /* <<< YOUR CPT SLUG HERE */ ) {
            if (current_user_can('edit_posts')) { // Show error to admin/editor
                return '<p class="SparxAT-error">AI Mastering: Invalid post ID or wrong post type for shortcode.</p>';
            }
            return ''; // Fail silently for public users
        }

        // Check if user has permission to view this track's status (e.g., is it their track?)
        // For simplicity, we assume public if the post is public. Add your own permission checks.
        // Example: if ( !current_user_can('read_post', $post_id ) ) { return '<p>You do not have permission to view this.</p>'; }


        $job_id         = get_post_meta( $post_id, '_SparxAT_job_id', true );
        $status         = get_post_meta( $post_id, '_SparxAT_status', true );
        $report_data    = get_post_meta( $post_id, '_SparxAT_report_data', true ); // This is the full API response from GET /masterings/{id}
        $report_message = get_post_meta( $post_id, '_SparxAT_report_message', true );
        $audio_file_id  = get_post_meta( $post_id, '_audio_attachment_id', true ); // Or your file path meta key

        ob_start();
        ?>
        <div class="SparxAT-status-wrapper" data-postid="<?php echo esc_attr( $post_id ); ?>">
            <h3>AI Mastering Status for: <?php echo esc_html( get_the_title( $post_id ) ); ?></h3>

            <?php if ( empty( $status ) || empty( $job_id ) ) : ?>
                <?php if ( ! empty( $audio_file_id ) ) : ?>
                    <p>This track has not yet been submitted for AI Mastering.</p>
                    <button class="SparxAT-button SparxAT-start-mastering-btn">Start AI Mastering</button>
                <?php else : ?>
                    <p>No audio file found for this track. Please upload an audio file first.</p>
                <?php endif; ?>

            <?php else : ?>
                <div class="SparxAT-status-current">
                    <p><strong>Status:</strong> <span class="SparxAT-status-text"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?></span></p>
                    <?php if ( $job_id ) : ?>
                        <p><strong>Job ID:</strong> <?php echo esc_html( $job_id ); ?></p>
                    <?php endif; ?>
                    <?php if ( $report_message ) : ?>
                        <p><em><?php echo esc_html( $report_message ); ?></em></p>
                    <?php endif; ?>
                </div>

                <?php if ( in_array( $status, ['submitted_processing', 'processing'] ) ) : ?>
                    <button class="SparxAT-button SparxAT-refresh-status-btn">Refresh Status</button>
                <?php endif; ?>

                <?php if ( $status === 'completed' && ! empty( $report_data ) ) : ?>
                    <h4>Mastering Report:</h4>
                    <pre class="SparxAT-report-data"><?php echo esc_html( print_r( $report_data, true ) ); ?></pre>
                    <?php
                    // --- Download Link Logic ---
                    // You need to determine how the download URL/ID is provided in $report_data
                    // Example: Assuming $report_data['output_audio_id'] or $report_data['result']['audio_id']
                    // Or $report_data['outputs'][0]['download_url']
                    $download_url = '';
                    $output_audio_id = null;

                    if (isset($report_data['result_audio_id'])) { // Hypothetical field
                        $output_audio_id = $report_data['result_audio_id'];
                    } elseif (isset($report_data['output_audio_id'])) { // Another hypothetical field
                        $output_audio_id = $report_data['output_audio_id'];
                    } elseif (isset($report_data['outputs']) && is_array($report_data['outputs']) && !empty($report_data['outputs'][0]['id'])) {
                         $output_audio_id = $report_data['outputs'][0]['id']; // If it's an ID
                    } elseif (isset($report_data['outputs']) && is_array($report_data['outputs']) && !empty($report_data['outputs'][0]['url'])) {
                         $download_url = $report_data['outputs'][0]['url']; // If direct URL
                    }


                    if ( $output_audio_id ) {
                        $ajax_download_url = add_query_arg([
                            'action' => 'SparxAT_download_mastered_file',
                            'post_id' => $post_id,
                            'output_audio_id' => $output_audio_id,
                            '_ajax_nonce' => wp_create_nonce('SparxAT_download_mastered_file_' . $post_id . '_' . $output_audio_id) // Specific nonce
                        ], admin_url('admin-ajax.php'));
                        echo '<a href="' . esc_url( $ajax_download_url ) . '" class="SparxAT-button SparxAT-download-btn" target="_blank">Download Mastered Track</a>';
                    } elseif ($download_url) {
                         echo '<a href="' . esc_url( $download_url ) . '" class="SparxAT-button SparxAT-download-btn" target="_blank" rel="noopener noreferrer">Download Mastered Track (Direct)</a>';
                    } else {
                        echo '<p>Mastered file download information not found in the report.</p>';
                    }
                    ?>
                <?php elseif ( in_array($status, ['submission_failed', 'failed', 'error_fetching_status'] ) ) : ?>
                    <p class="SparxAT-error">There was an issue with the mastering process.</p>
                    <?php if ( ! empty( $audio_file_id ) ) : ?>
                        <button class="SparxAT-button SparxAT-start-mastering-btn SparxAT-retry-btn">Retry Mastering</button>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            <div class="SparxAT-ajax-message" style="display:none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    // Add this inside jQuery(document).ready(function($) { ... });

$('#SparxAT-mastering-options-form').on('submit', function(e) {
    e.preventDefault();
    const $form = $(this);
    const $button = $form.find('.SparxAT-submit-mastering-options-btn');
    const $wrapper = $form.closest('.SparxAT-mastering-form-wrapper');
    const $messageDiv = $wrapper.find('.SparxAT-ajax-message');
    let formData = $form.serialize(); // Includes action, sub_action, _ajax_nonce, post_id, and mastering_options fields

    $button.prop('disabled', true).text('Submitting for Mastering...');
    $messageDiv.hide().removeClass('SparxAT-error SparxAT-success').empty();

    // Convert checkbox values (if any were unchecked, they won't be in serialize())
    // For 'bassPreservation' example:
    // If you need to explicitly send 'false' when unchecked:
    let parsedData = new URLSearchParams(formData);
    if (!parsedData.has('mastering_options[bassPreservation]')) {
         formData += '&mastering_options[bassPreservation]=false';
    }
    // For other booleans, ensure your PHP handler correctly interprets "true"/"false" strings or presence/absence of key.

    $.ajax({
        url: SparxAT_ajax_object.ajax_url, // Already localized
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                $form.hide();
                $messageDiv.text('Mastering process initiated successfully! Job ID: ' + (response.data.job_id || 'N/A') + '. You will be redirected to the status page shortly.').addClass('SparxAT-success').show();
                // Redirect to status page or refresh.
                // For now, let's assume the status page URL could be the current page if [SparxAT_mastering_status] is also there,
                // or a specific status page.
                setTimeout(function() {
                    // Option 1: Reload current page to show status via [SparxAT_mastering_status] shortcode
                     window.location.reload(); 
                    // Option 2: Redirect to a specific status permalink (if you have one)
                    // window.location.href = response.data.status_page_url; // If you construct this URL
                }, 3000);
            } else {
                $messageDiv.text('Error: ' + response.data.message).addClass('SparxAT-error').show();
                $button.prop('disabled', false).text('Master This Track');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $messageDiv.text('AJAX Error: ' + textStatus + ' - ' + errorThrown).addClass('SparxAT-error').show();
            $button.prop('disabled', false).text('Master This Track');
        }
    });
});
}

 public static function render(): void
    {
        // Check if the user has permission to access this page
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        // Render the UI for the audio tools
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'SparxStar Audio Tools', 'sparxstar-audio-tools' ); ?></h1>
            <p><?php esc_html_e( 'Use these tools to enhance your audio files.', 'sparxstar-audio-tools' ); ?></p>
            <!-- Add your UI elements here -->
        </div>
        <?php
    }
// Initialize