<?php // In: src/frontend/SparxATWaveform.php
namespace SPARXSTAR\src\frontend;

if (!defined('ABSPATH')) exit;

class SparxATWaveform {
    private static $instance;

    public static function instance() {
        if (is_null(self::$instance)) { self::$instance = new self(); }
        return self::$instance;
    }

    /**
     * Renders the hidden modal template in the admin footer.
     * This method is CALLED BY a hook in SparxATCPTHooks, it does not add the hook itself.
     */
    public static function render_editor_modal_template() {
        $screen = get_current_screen();
        // Ensure we are on the 'track' CPT edit screen
        if (!$screen || $screen->post_type !== 'track') { 
            return;
        }

        // Use the path to your template file. Assumes main plugin file defined a constant for the root path.
        $template_path = SPARXAT_PATH . 'templates/sparxstar-audio-tools-ui.php';
        if (file_exists($template_path)) {
            echo '<div id="sparxstar-modal-container" style="display:none;">';
            include $template_path;
            echo '</div>';
        }
    }
}