<?php
/**
 * Plugin Name:     Split Tests
 * Plugin URI:      https://github.com/the-markup/split-tests
 * Description:     Simple A/B testing in WordPress.
 * Author:          The Markup <info@themarkup.org>
 * Author URI:      https://themarkup.org/
 * Text Domain:     split-tests
 * Domain Path:     /languages
 * Version:         0.0.9
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
    require_once __DIR__ . '/src/API.php';
    require_once __DIR__ . '/src/Assets.php';
    require_once __DIR__ . '/src/Cron.php';
    require_once __DIR__ . '/src/Database.php';
    require_once __DIR__ . '/src/PostType.php';
    require_once __DIR__ . '/src/DomTests.php';
    require_once __DIR__ . '/src/TitleTests.php';
	require_once __DIR__ . '/src/Plugin.php';
}

 add_action('plugins_loaded', function() {
    SplitTests\Plugin::init();
});

register_activation_hook(__FILE__, function() {
    $plugin = SplitTests\Plugin::init();
    $plugin->activate();
});

register_deactivation_hook(__FILE__, function() {
    $plugin = SplitTests\Plugin::init();
    $plugin->deactivate();
});