<?php
namespace MandinkaGames\includes;

class MandinkaFlashcards {

    private string $pluginPath;
    private string $pluginUrl;
    private string $version;

    public function __construct( string $pluginPath, string $pluginUrl, string $version ) {
        $this->pluginPath = $pluginPath;
        $this->pluginUrl  = $pluginUrl;
        $this->version    = $version;

        add_shortcode( 'mandinka_flashcard_quiz', array( $this, 'mfc_display_quiz_shortcode' ) );
    }

    /**
     * Enqueue scripts and styles.
     */
    private function enqueueAssets(): void {
        wp_register_style(
            'mfc-style',
            $this->pluginUrl . 'assets/css/mandinka-flashcards-style.css',
            array(),
            $this->version
        );

        wp_register_script(
            'mfc-script',
            $this->pluginUrl . 'assets/js/mandinka-flashcards-script.js',
            array(),
            $this->version,
            true
        );

        // In your my_mandinka_quiz_enqueue_scripts function:
        wp_localize_script('mandinka-main-app', 'mandinkaQuizData', array(
            'dictionaryPath' => plugin_dir_url(__FILE__) . 'dictionary_json/dictionary_mandinka.json', // Ensure this path is correct
            'ajax_url' => admin_url('admin-ajax.php'), // For future AJAX improvements
            'nonce' => wp_create_nonce('mandinka_quiz_nonce')
        ));
    }

    /**
     * Shortcode function to display the quiz.
     */
    public function mfc_display_quiz_shortcode( $atts ): string {
        $this->enqueueAssets();
        wp_enqueue_style( 'mfc-style' );
        wp_enqueue_script( 'mfc-script' );

        ob_start();
        include $this->pluginPath . 'templates/mandinka-games-ui.php';
        return ob_get_clean();
    }
}
