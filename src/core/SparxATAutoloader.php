<?php
namespace SPARXSTAR\src\includes;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Simple PSR-4 class autoloader.
 *
 * This autoloader is for projects that do not use Composer.
 * It expects classes to be in the 'SPARXSTAR\src\includes' namespace
 * and reside in the 'src/' directory relative to the plugin root.
 */
class SparxATAutoloader {

    /**
     * Registers the autoloader with SPL.
     */
    public static function register(): void {
        spl_autoload_register( [ __CLASS__, 'loadClass' ] );
    }

    /**
     * Unregisters the autoloader.
     */
    public static function unregister(): void {
        spl_autoload_unregister( [ __CLASS__, 'loadClass' ] );
    }

    /**
     * Loads the class file for a given class name.
     *
     * @param string $className The fully qualified class name.
     */
    public static function loadClass( $className ): void {
        // Define your base namespace and base directory.
        if ( ! defined( 'SPARXAT_NAMESPACE' ) || ! defined( 'SPARXAT_PATH' ) ) {
            error_log( "Autoloader: SPARXAT_NAMESPACE or SPARXAT_PATH is not defined." );
            return;
        }
        $baseNamespace = SPARXAT_NAMESPACE; // Adjust this to match your namespace.
        $baseDir = SPARXAT_PATH . 'src/'; // SPARXAT_PATH is defined in main plugin file.

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
            error_log( "Autoloader: File not found for class {$className} at {$file}" );
        }
    }
}