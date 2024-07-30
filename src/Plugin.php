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
     * Database version lets us apply updates to the db schema in the future.
     *
     * @var int
     */
    protected $db_version = 1;

    /**
     * A list of variant test/convert events to synchronize upon the page loading.
     *
     * @var array
     */
    protected $increment_events;

	/**
	 * Setup the database table, hooks, and tests.
	 *
	 * @return void
	 */
	function __construct() {
        $this->setup_db();
        $this->setup_hooks();
        $this->setup_post_type();
        $this->setup_tests();
    }

    /**
     * Checks if the database schema requires updating, then updates it if necessary.
     *
     * @return void
     */
    function setup_db() {
        $curr_db_version = get_option('split_tests_db_version', 0);
        if ($curr_db_version < $this->db_version) {
            $this->migrate_db($curr_db_version);
        }
    }

    /**
     * Setup action/filter handlers.
     *
     * @return void
     */
    function setup_hooks() {
        // Show test results on split_test edit page
        add_action('edit_form_after_title', [$this, 'edit_form_after_title']);

        // Enqueue front-end JS
        add_action('wp_enqueue_scripts', [$this, 'wp_enqueue_scripts']);

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

        // Expose API endpoint
        add_action('rest_api_init', [$this, 'rest_api_init']);

        // Add results column header to Split Tests table
        add_filter('manage_split_test_posts_columns', [$this, 'manage_split_test_posts_columns']);

        // Add results column values to Split Tests table
        add_filter('manage_split_test_posts_custom_column', [$this, 'manage_split_test_posts_custom_column'], 10, 2);

        // ACF JSON path
        add_filter('acf/settings/load_json', [$this, 'load_acf_json']);
    }

    /**
     * Registers a split_test post type.
     *
     * @return void
     */
    function setup_post_type() {
        $labels = $this->post_type_labels([
            'name' => 'Split Tests',
            'singular_name' => 'Split Test'
        ]);
        add_action('init', function() use ($labels) {
            register_post_type('split_test', [
                'label' => 'Split Tests',
                'labels' => $labels,
                'description' => 'A/B test resuls',
                'show_ui' => true,
                'supports' => ['title']
            ]);
        });
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
        $this->increment_events[] = [$test_or_convert, $split_test_id, $variant_index];
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
            (split_test_id, test_type, variant_index, test_or_convert, created_time)
            VALUES (%d, %s, %d, %s, %s)
        ", $split_test_id, $test_type, $variant_index, $test_or_convert, $now));
    }

    /**
     * Retrieve the count for a given variant statistic.
     *
     * @return void
     */
    function get_count($split_test_id, $test_type, $variant_index, $boop_type) {
        global $wpdb;
        $now = wp_date('Y-m-d H:i:s');
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(split_test_id)
            FROM {$wpdb->prefix}split_tests
            WHERE split_test_id = %d
              AND test_type = %s
              AND variant_index = %d
              AND test_or_convert = %s
        ", $split_test_id, $test_type, $variant_index, $boop_type));
    }

    /**
     * Show test results on the split_test post editor page.
     *
     * @return void
     */
    function edit_form_after_title($post) {
        if ($post->post_type != 'split_test') {
            return;
        }

        $test_type = get_field('test_type');

        if ($test_type == 'title') {
            $this->title_tests->show_results($post);
        } else if ($test_type == 'dom') {
            $this->dom_tests->show_results($post);
        }
    }

    /**
     * Migrates the database schema based on a version number.
     *
     * @return void
     */
    function migrate_db($curr_version) {
        global $wpdb;
        if ($curr_version < 1) {
            $table_name = $wpdb->prefix . 'split_tests';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                split_test_id BIGINT(20) UNSIGNED NOT NULL,
                test_type VARCHAR(255) NOT NULL,
                variant_index TINYINT UNSIGNED NOT NULL,
                test_or_convert ENUM('test', 'convert'),
                created_time DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL
            ) $charset_collate;";
        }
	    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	    dbDelta($sql);
        update_option('split_tests_db_version', $this->db_version);
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
        wp_register_script(
            'split-tests',
            plugins_url('build/split-tests.js', __DIR__),
            $asset['dependencies'],
            $asset['version']
        );

        if (!empty($this->increment_events)) {
            $nonce = wp_create_nonce('wp_rest');
            wp_localize_script('split-tests', 'split_tests', [
                'nonce' => $nonce,
                'onload' => $this->increment_events,
                'dom' => $this->dom_tests->get_variants()
            ]);
            wp_enqueue_script('split-tests');
        }
    }

    /**
     * Synchronize any increment events.
     *
     * @return void
     */
    function wp_print_scripts() {
        if (is_admin()) {
            return;
        }
        if (! empty($this->increment_events)) {
            $events = json_encode($this->increment_events);
            echo <<<END
<script>
split_test_syn($events);
</script>
END;
        }
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

    /**
     * Add a Results column header to the Split Tests posts table.
     *
     * @return array
     */
    function manage_split_test_posts_columns($_columns) {
        $columns = [];
        foreach ($_columns as $key => $value) {
            $columns[$key] = $value;
            if ($key == 'title') {
                $columns['conversions'] = 'Conversions';
            }
        }
        return $columns;
    }

    /**
     * Add Results column values to the Split Tests posts table.
     *
     * @return array
     */
    function manage_split_test_posts_custom_column($column, $post_id) {
        if ($column != 'conversions') {
            return;
        }
        $type = get_field('test_type', $post_id);
        if ($type == 'title') {
            $this->title_tests->show_results_summary($post_id);
        } else if ($type == 'dom') {
            $this->dom_tests->show_results_summary($post_id);
        }
    }

    /**
     * Assigns reasonable labels for custom post types.
     *
     * @return array
     */
    function post_type_labels($labels) {
        $label_templates = [
            'add_new' => 'Add New %singular_name%',
            'add_new_item' => 'Add New %singular_name%',
            'edit_item' => 'Edit %singular_name%',
            'new_item' => 'New %singular_name%',
            'view_item' => 'View %singular_name%',
            'view_items' => 'View %name%',
            'search_items' => 'Search %name%',
            'not_found' => 'No %name% found',
            'not_found_in_trash' => 'No %name% found in trash',
            'parent_item_colon' => 'Parent %singular_name%:',
            'all_items' => 'All %name%',
            'archives' => '%singular_name% Archives',
            'attributes' => '%singular_name% Attributes',
            'insert_into_item' => 'Insert into %singular_name%',
            'uploaded_to_this_item' => 'Uploaded to this %singular_name%',
            'filter_items_list' => 'Filter %name% list',
            'items_list_navigation' => '%name% list navigation',
            'items_list' => '%name% list',
            'item_published' => '%singular_name% published.',
            'item_published_privately' => '%singular_name% published privately.',
            'item_reverted_to_draft' => '%singular_name% reverted to draft.',
            'item_trashed' => '%singular_name% trashed.',
            'item_scheduled' => '%singular_name% scheduled.',
            'item_updated' => '%singular_name% updated.',
            'item_link' => '%singular_name% Link',
            'item_link_description' => 'A link to a %singular_name%'
        ];

        foreach ($label_templates as $name => $value) {
            if (isset($labels[$name])) {
                continue;
            }
            $value = str_replace('%name%', $labels['name'], $value);
            $value = str_replace('%singular_name%', $labels['singular_name'], $value);
            $labels[$name] = $value;
        }
        return $labels;
    }
}