<?php
namespace SparxstarAiMastering\src\includes;

if ( ! defined( 'ABSPATH' ) ) exit;

class AIMCron {

    private static $instance;
    const CRON_HOOK = 'aim_check_pending_jobs_status';
    private $cpt_slug = 'music_track'; // <<< IMPORTANT: Change this to your CPT slug

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( self::CRON_HOOK, [ $this, 'check_pending_jobs' ] );
    }

    public static function schedule_events() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'every_five_minutes', self::CRON_HOOK ); // Or 'hourly', etc.
        }
    }

    public static function unschedule_events() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    public function check_pending_jobs() {
        $api_handler = AIM_API_Handler::instance();

        $args = [
            'post_type'      => $this->cpt_slug,
            'posts_per_page' => 20, // Process in batches
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_aim_job_id',
                    'compare' => 'EXISTS',
                ],
                [
                    'key'     => '_aim_job_id', // Ensure it's not empty
                    'value'   => '',
                    'compare' => '!=',
                ],
                [
                    'key'     => '_aim_status',
                    'value'   => ['submitted_processing', 'processing'], // Only check these statuses
                    'compare' => 'IN',
                ],
            ],
        ];

        $pending_posts = get_posts( $args );

        if ( empty( $pending_posts ) ) {
            // error_log('AIM Cron: No pending jobs found.');
            return;
        }

        // error_log('AIM Cron: Found ' . count($pending_posts) . ' pending jobs.');

        foreach ( $pending_posts as $post ) {
            $job_id = get_post_meta( $post->ID, '_aim_job_id', true );
            if ( empty( $job_id ) ) {
                continue;
            }

            // error_log("AIM Cron: Checking status for Post ID {$post->ID}, Job ID: {$job_id}");
            $report_or_error = $api_handler->get_job_status_report( $job_id );

            if ( is_wp_error( $report_or_error ) ) {
                update_post_meta( $post->ID, '_aim_status', 'error_fetching_status' );
                update_post_meta( $post->ID, '_aim_report_message', 'Error fetching status: ' . $report_or_error->get_error_message() );
                error_log("AIM Cron Error for Post ID {$post->ID}: " . $report_or_error->get_error_message());
            } else if ( isset( $report_or_error['status'] ) ) {
                $new_status = sanitize_text_field( $report_or_error['status'] );
                update_post_meta( $post->ID, '_aim_status', $new_status );

                $message = 'Status updated: ' . ucfirst($new_status) . '.';
                if (isset($report_or_error['progress'])) {
                    $message .= ' Progress: ' . intval($report_or_error['progress']) . '%';
                }
                update_post_meta( $post->ID, '_aim_report_message', $message );

                if ( $new_status === 'completed' && isset( $report_or_error['report_data'] ) ) {
                    update_post_meta( $post->ID, '_aim_report_data', $report_or_error['report_data'] ); // Store the full report
                    update_post_meta( $post->ID, '_aim_report_message', 'Analysis completed successfully.');
                    // error_log("AIM Cron: Job {$job_id} for Post ID {$post->ID} completed.");
                } elseif ( $new_status === 'failed' && isset( $report_or_error['error_message'] ) ) {
                    update_post_meta( $post->ID, '_aim_report_message', 'Processing failed: ' . esc_html( $report_or_error['error_message'] ) );
                    // error_log("AIM Cron: Job {$job_id} for Post ID {$post->ID} failed: " . $report_or_error['error_message']);
                } elseif ($new_status !== 'processing' && $new_status !== 'submitted_processing') {
                    // Some other terminal status we didn't expect
                    update_post_meta( $post->ID, '_aim_report_message', 'Job finished with status: ' . ucfirst($new_status) );
                }
            }
        }
    }
}

// Add custom cron schedules if needed (e.g., 'every_five_minutes')
add_filter( 'cron_schedules', 'aim_add_cron_schedules' );
function aim_add_cron_schedules( $schedules ) {
    if (!isset($schedules["every_five_minutes"])) {
        $schedules['every_five_minutes'] = array(
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => esc_html__( 'Every Five Minutes' ),
        );
    }
    return $schedules;
}