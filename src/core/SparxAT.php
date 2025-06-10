<?php // In: src/core/SparxAT.php
namespace SPARXSTAR\src\core;

if (!defined('ABSPATH')) exit;

class SparxAT {
    public static $instance = null;

    public static function getInstance(): SparxAT {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // This is the main entry point for the Sparxstar Audio Tools plugin.
        $this->load_dependencies();  
        // The autoloader has already run, so we can directly initialize the loader.
        SparxATLoader::instance();
    }
    // The init(), load(), and other methods can be removed as the loader's constructor now handles everything.
    public function load_dependencies(): void {
        // In your main SparxstarAudioTools.php or a central loader
        require_once SPARXAT_PATH . 'libs/getid3/getid3.php';
        require_once SPARXAT_PATH . 'libs/getid3/write.php'; // The specific writer file
        require_once SPARXAT_PATH . 'libs/getid3/write.audio.php'; // The audio writer file
        require_once SPARXAT_PATH . 'libs/getid3/write.tags.php';
        require_once SPARXAT_PATH . 'libs/getid3/write.id3v2.php'; // The ID3v2 writer file
        require_once SPARXAT_PATH . 'libs/getid3/write.id3v1.php'; // The ID3v1 writer file


    }
}