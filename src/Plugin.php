<?php
/**
 * Class Plugin
 *
 * @package   SplitTests
 * @author    The Markup
 * @license   GPL-2.0-or-later
 * @link      https://themarkup.org/
 * @copyright 2024 The Markup
 */

namespace SplitTests;

class Plugin {

	/**
	 * Setup plugin stuff
	 *
	 * @return void
	 */
	function __construct() {
        // Load ACF fields
        add_filter('acf/settings/load_json', function($paths) {
            $paths[] = dirname(__DIR__) . '/acf-fields';
            return $paths;
        });
    }
}