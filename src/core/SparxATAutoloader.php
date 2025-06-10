<?php
namespace SPARXSTAR\src\includes;

/**
 * Simple PSR-4 class autoloader.
 *
 * This autoloader is for projects that do not use Composer.
 * It expects classes to be in the 'SPARXSTAR\src\includes' namespace
 * and reside in the 'src/' directory relative to the plugin root.
 */
class Autoloader {

    /**
     * Registers the autoloader with SPL.
     */
    public static function register() {
        spl_autoload_register( [ __CLASS__, 'loadClass' ] );
    }

    /**
     * Unregisters the autoloader.
     */
    public static function unregister() {
        spl_autoload_unregister( [ __CLASS__, 'loadClass' ] );
    }

    /**
     * Loads the class file for a given class name.
     *
     * @param string $className The fully qualified class name.
     */
    public static function loadClass( $className ) {
        // Define your base namespace and base directory.
        $baseNamespace = 'SPARXSTAR\\src\\'; // Adjust this to match your namespace.
        $baseDir = SparxAT_PATH . 'src/'; // SparxAT_PATH is defined in main plugin file.

        // Does the class use the namespace prefix?
        $len = strlen( $baseNamespace );
        if ( strncmp( $baseNamespace, $className, $len ) !== 0 ) {
            // No, move to the next registered autoloader.
            return;
        }

        // Get the relative class name.
        $relativeClass = substr( $className, $len );

        // Replace the namespace prefix with the base directory,
        // replace namespace separators with directory separators in the relative class name,
        // append with .php
        $file = $baseDir . str_replace( '\\', '/', $relativeClass ) . '.php';

        // If the file exists, require it.
        if ( file_exists( $file ) ) {
            require_once $file;
        } else {
            // Optionally, log an error if a class file is expected but not found.
             // error_log( "Autoloader: File not found for class {$className} at {$file}" );
        }
    }
}