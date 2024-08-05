<?php
/**
 * Plugin Name:     Split Tests
 * Plugin URI:      https://github.com/the-markup/split-tests
 * Description:     Simple A/B testing in WordPress.
 * Author:          The Markup <info@themarkup.org>
 * Author URI:      https://themarkup.org/
 * Text Domain:     split-tests
 * Domain Path:     /languages
 * Version:         0.0.3
 *
 * @package         SplitTests
 */

 if (! function_exists('dbug')) {
    function dbug() {
         $args = func_get_args();
         $out = array();
         foreach ( $args as $arg ) {
             if ( ! is_scalar( $arg ) ) {
                 $arg = print_r( $arg, true );
             }
             $out[] = $arg;
         }
         $out = implode( "\n", $out );
         error_log( "\n$out" );
     }
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
	require_once(__DIR__ . '/vendor/autoload.php');
} else {
    require_once __DIR__ . '/src/Database.php';
    require_once __DIR__ . '/src/TitleTests.php';
    require_once __DIR__ . '/src/DOMTests.php';
	require_once __DIR__ . '/src/Plugin.php';
}

 add_action('plugins_loaded', function() {
    global $split_tests_plugin;
    $split_tests_plugin = new SplitTests\Plugin();
});
