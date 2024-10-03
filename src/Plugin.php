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
     * A list of variant test/convert events to synchronize upon the page loading.
     *
     * @var array
     */
    public $onload_events;

    /**
     * Singleton instance var. There should only be one plugin per request.
     *
     * @var SplitTests\Plugin
     */
    private static $instance = null;

    /**
     * Singleton initiation.
     *
     * @return SplitTests\Plugin
     */
    static function init() {
        if (! self::$instance) {
            self::$instance = new Plugin();
        }
        return self::$instance;
    }

	/**
	 * Setup sub-classes, tests, and ACF path filter.
	 *
	 * @return SplitTests\Plugin
	 */
	function __construct() {
        // Setup sub-classes
        $this->database = new Database();
        $this->post_type = new PostType($this);
        $this->assets = new Assets($this);
        $this->api = new API($this);
        $this->cron = new Cron();

        // Setup tests
        $this->title_tests = new TitleTests($this);
        $this->dom_tests = new DomTests($this);

        // Add ACF JSON path
        add_filter('acf/settings/load_json', function($paths) {
            $paths[] = dirname(__DIR__) . '/acf-fields';
            return $paths;
        });
    }

    /**
     * Runs upon plugin activation.
     *
     * @return void
     */
    function activate() {
        $this->cron->schedule();
    }

    /**
     * Runs upon plugin deactivation.
     *
     * @return void
     */
    function deactivate() {
        $this->cron->unschedule();
    }

    /**
     * Check that a test is published and its configured context matches the
     * current page request.
     *
     * @return bool
     */
    function check_context($test_id) {
        if (get_post_status($test_id) != 'publish') {
            return false;
        }
        $context = get_field('test_context', $test_id);
        if ($context == 'all') {
            return true;
        } else if ($context == 'home') {
            return is_front_page() || apply_filters('split_tests_current_url', $_SERVER['REQUEST_URI']) == '/';
        } else if ($context == 'url') {
            return $this->check_context_url($test_id);
        }
    }

    /**
     * Check that a test's configured URL pattern matches the current page URL.
     *
     * @return bool
     */
    function check_context_url($test_id) {
        $current_url = apply_filters('split_tests_current_url', $_SERVER['REQUEST_URI']);
        $current_url = trailingslashit($current_url);
        $current_url = strtolower($current_url);

        $test_url = get_field('test_context_url', $test_id);
        $test_url = strtolower($test_url);

        if (strpos($test_url, '*') === false) {
            $test_url = trailingslashit($test_url);
            return $test_url === $current_url;
        }

        $test_pattern = str_replace('*', '.*', $test_url);
	    $test_regex = '@^' . $test_pattern . '$@';

        return preg_match($test_regex, $current_url);
    }

    /**
     * Set up a front-end REST API increment call.
     *
     * @return void
     */
    function increment($test_or_convert, $split_test_id, $variant_index) {
        $this->onload_events[] = [$test_or_convert, $split_test_id, $variant_index];
    }

    /**
     * Redirect browser to a URL onload.
     *
     * @return void
     */
    function redirect($url) {
        if (apply_filters('split_tests_is_headless', false)) {
            $this->onload_events[] = ['redirect', $url];
        } else {
            wp_redirect($url);
        }
    }

    /**
     * Record a variant statistic ('test' or 'convert') event for a given
     * split test post ID in the 'wp_split_tests' table.
     *
     * @return void
     */
    function insert_split_test_event($test_or_convert, $split_test_id, $test_type, $variant_index) {
        global $wpdb;
        $now = wp_date('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare("
            INSERT INTO {$wpdb->prefix}split_tests
            (split_test_id, test_type, variant_index, test_or_convert, granularity, count, created_time)
            VALUES (%d, %s, %d, %s, %s, %d, %s)
        ", $split_test_id, $test_type, $variant_index, $test_or_convert, 'raw', 1, $now));
    }

    /**
     * Retrieve the count for a given variant statistic.
     *
     * @return void
     */
    function get_count($split_test_id, $test_type, $variant_index, $test_or_convert) {
        global $wpdb;
        $now = wp_date('Y-m-d H:i:s');
        return $wpdb->get_var($wpdb->prepare("
            SELECT SUM(count)
            FROM {$wpdb->prefix}split_tests
            WHERE split_test_id = %d
              AND test_type = %s
              AND variant_index = %d
              AND test_or_convert = %s
        ", $split_test_id, $test_type, $variant_index, $test_or_convert));
    }
}