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
    protected $onload_events;

	/**
	 * Setup the database table, hooks, and tests.
	 *
	 * @return SplitTests\Plugin
	 */
	function __construct() {
        $this->database = new Database();
        $this->post_type = new PostType($this);
        $this->setup_hooks();
        $this->setup_tests();
    }

    /**
     * Setup action/filter handlers.
     *
     * @return void
     */
    function setup_hooks() {
        // Enqueue front-end JS
        add_action('wp_enqueue_scripts', [$this, 'wp_enqueue_scripts']);

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

        // Expose API endpoint
        add_action('rest_api_init', [$this, 'rest_api_init']);

        // ACF JSON path
        add_filter('acf/settings/load_json', [$this, 'load_acf_json']);
    }

    /**
     * Setup different kinds of split tests.
     *
     * @return void
     */
    function setup_tests() {
        $this->title_tests = new TitleTests($this);
        $this->dom_tests = new DOMTests($this);
    }

    /**
     * Check that a test context matches the current page request.
     *
     * @return bool
     */
    function check_context($test_id) {
        $context = get_field('test_context', $test_id);
        if ($context == 'all') {
            return true;
        } else if ($context == 'home') {
            return is_front_page();
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
     * Handle incoming 'increment' API request.
     *
     * @return array
     */
    function rest_api_events($request) {
        $ok_rsp = true;
        try {
            $nonce = $request->get_param('_wpnonce');
            $events = json_decode($request->get_body(), 'as array');
            if (! wp_verify_nonce($nonce, 'wp_rest')) {
                throw new \Exception("rest_api_events: invalid nonce '$nonce'");
            }
            foreach ($events as $event) {
                if (count($event) != 3) {
                    continue;
                }
                list($test_or_convert, $split_test_id, $variant_index) = $event;
                $test_type = get_field('test_type', $split_test_id);
                $this->insert_split_test_event($test_or_convert, $split_test_id, $test_type, $variant_index);
            }
        } catch(\Exception $err) {
            $ok_rsp = false;
            error_log($err);
        }
        return [
            'ok' => $ok_rsp
        ];
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

    /**
     * Setup acf-fields directory path, filtering on 'acf/settings/load_json'.
     *
     * @return array
     */
    function load_acf_json($paths) {
        $paths[] = dirname(__DIR__) . '/acf-fields';
        return $paths;
    }

    /**
     * Enqueue JavaScript for the front-end.
     *
     * @return void
     */
    function wp_enqueue_scripts() {
        $asset = include(dirname(__DIR__) . '/build/split-tests.asset.php');
        wp_enqueue_script(
            'split-tests',
            plugins_url('build/split-tests.js', __DIR__),
            $asset['dependencies'],
            $asset['version']
        );

        wp_localize_script('split-tests', 'split_tests', [
            'nonce' => wp_create_nonce('wp_rest'),
            'onload' => $this->onload_events,
            'dom' => $this->dom_tests->get_variants()
        ]);
    }

    /**
     * Enqueue assets for the Split Tests edit page.
     *
     * @return void
     */
    function admin_enqueue_scripts() {
        $asset = include(dirname(__DIR__) . '/build/admin.asset.php');
        wp_enqueue_script(
            'split-tests-admin',
            plugins_url('build/admin.js', __DIR__),
            ['acf-input', 'jquery', 'wp-url'],
            $asset['version']
        );
        wp_enqueue_style(
            'split-tests-admin',
            plugins_url('build/admin.css', __DIR__),
            [],
            $asset['version']
        );
    }

    /**
     * Setup API endpoint.
     *
     * @return void
     */
    function rest_api_init() {
        $worked = register_rest_route('split-tests/v1', 'events', [
              'methods' => 'POST',
              'callback' => [$this, 'rest_api_events'],
              'permission_callback' => '__return_true',
        ]);
    }
}