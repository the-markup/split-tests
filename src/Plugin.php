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
     * Upon fielding a new page request, store the URL slug in order to check
     * against known variant url_slug values.
     *
     * @var string
     */
    protected $request_slug;

    /**
     * Cache chosen variants on a per-post-ID basis, in case a post appears
     * more than once on a page.
     *
     * @var array
     */
    protected $chosen_variants = [];

    /**
     * Ensure the conversion is only counted once.
     *
     * @var bool
     */
    protected $converted = false;

    /**
     * Avoid double-applying the variant post filter.
     *
     * @var bool
     */
    protected $ignore_post_variants = false;

    /**
     * Database version lets us apply updates to the db schema in the future.
     *
     * @var int
     */
    protected $db_version = 1;

	/**
	 * Setup filters.
	 *
	 * @return void
	 */
	function __construct() {
        $this->setup_db();
        $this->setup_hooks();
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
        // This filter is ours, for adjusting individual posts with a variant
        add_filter('split_tests_post_variant', [$this, 'post_variant']);

        // Init hook
        add_action('init', [$this, 'init']);
        
        // Incoming page request
        add_filter('request', [$this, 'request']);

        // WP_Query posts filter
        add_filter('posts_results', [$this, 'posts_results'], 10, 2);
        
        // Permalink filter
        add_filter('pre_post_link', [$this, 'pre_post_link'], 10, 2);

        // Add split_test posts whenever you save a post with a split test
        add_action('save_post', [$this, 'save_post'], 10, 2);

        // Show test results on split_test edit page
        add_action('edit_form_after_title', [$this, 'edit_form_after_title']);
        
        // ACF JSON path
        add_filter('acf/settings/load_json', [$this, 'load_acf_json']);
    }

    /**
     * Handle 'init' action.
     *
     * @return void
     */
    function init() {
        register_post_type('split_test', [
            'label' => 'Split Tests',
            'labels' => $this->post_type_labels([
                'name' => 'Split Tests',
                'singular_name' => 'Split Test'
            ]),
            'description' => 'A/B test resuls',
            'show_ui' => true,
            'supports' => ['title']
        ]);
    }

    /**
     * Handle 'request' filter.
     *
     * @return array
     */
    function request($query_vars) {
        if (!empty($query_vars['name'])) {
            $this->request_slug = $query_vars['name'];
        }
        return $query_vars;
    }

    /**
     * Handle 'posts_results' filter from WP_Query.
     *
     * @return array
     */
    function posts_results($posts, $query) {
        // Don't adjust posts in the admin dashboard.
        if (is_admin()) {
            return $posts;
        }

        // Avoid double-applying the variant filter.
        // (See also: 'check_variant_url_slugs' called below.)
        if ($this->ignore_post_variants) {
            return $posts;
        }

        // Found a non-existant slug? Let's check for variant 'url_slug'
        // postmeta values.
        if (empty($posts) && !empty($this->request_slug)) {
            $request_slug = $this->request_slug;
            $this->request_slug = '';
            // We may now have a single post that matches the requested slug.
            $posts = $this->check_variant_url_slugs($request_slug, $query);
        }

        // Apply variants to each post.
        foreach ($posts as $post) {
            $post = apply_filters('split_tests_post_variant', $post);
        }
        return $posts;
    }

    /**
     * Applies variant values to a post when a variant is found.
     *
     * @return WP_Post
     */
    function post_variant($post) {
        $variant = $this->get_variant($post);
        if (!empty($variant)) {
            $post->post_title = $variant['headline'];
            $post->post_name = $variant['url_slug'];
        }
        return $post;
    }

    /**
     * Returns an array of variant values, either chosen at random or based on
     * a previous selection.
     *
     * @return array
     */
    function get_variant($post) {
        if (is_single() && $post->post_type == 'post') {
            $variant = null;
            if (! empty($this->chosen_variants[$post->ID])) {
                $variant = $this->chosen_variants[$post->ID];
                $variant_index = $variant['index'];
            } else {
                $variant_index = 0;
            }
            if (! $this->converted) {
                $this->converted = true;
                $variants = get_field('title_variants', $post->ID);
                if (count($variants) > 0) {
                    $this->variant_convert($post->ID, 'title', $variant_index);
                }
            }
            return $variant;
        }

        // Check if we already chose a variant for this post.
        if (! empty($this->chosen_variants[$post->ID])) {
            return $this->chosen_variants[$post->ID];
        }

        // Query for any variants on this post.
        $_variants = get_field('title_variants', $post->ID);
        if (empty($_variants)) {
            return null;
        }

        // Add a choice from the post's original values.
        $variants = [
            [
                'headline' => $post->post_title,
                'url_slug' => $post->post_name
            ],
            ... $_variants
        ];
        $index = rand(0, count($variants) - 1);
        $variant = $variants[$index];

        // Cache this variant choice.
        $this->chosen_variants[$post->ID] = $variant;

        // Boop the 'test' variable for this variant.
        $this->variant_test($post->ID, 'title', $index);

        return $variant;
    }

    /**
	 * Handle 'pre_post_link' filter on post permalinks.
	 *
	 * @return string
	 */
    function pre_post_link($permalink, $post) {
        // Don't adjust links in the admin dashboard.
        if (is_admin()) {
            return $permalink;
        }
        $post = apply_filters('split_tests_post_variant', $post);
        return $permalink;
    }

    /**
     * Check the requested URL slug against known variant slugs and return any
     * resulting posts.
     *
     * @return array
     */
    function check_variant_url_slugs($request_slug, $query) {
        global $wpdb;
		$post_ids = $wpdb->get_col($wpdb->prepare("
			SELECT post_id
			FROM $wpdb->postmeta
			WHERE meta_key LIKE 'title_variants_%_url_slug'
			AND meta_value = '%s'
		", $request_slug), 0);
		if (empty($post_ids)) {
			return [];
		}

        $query_vars = $query->query_vars;
        unset($query_vars['name']);
        $query_vars['post__in'] = $post_ids;
        $this->ignore_post_variants = true;
        $posts = get_posts($query_vars);
        $this->ignore_post_variants = false;

        if (is_single() && count($posts) == 1) {
            $variants = get_field('title_variants', $posts[0]->ID);
            foreach ($variants as $index => $variant) {
                if ($variant['url_slug'] == $request_slug) {
                    // Add one to the index to account for index 0 being 'unmodified.'
                    $variant['index'] = $index + 1;
                    $this->chosen_variants[$posts[0]->ID] = $variant;
                    break;
                }
            }
        }
        return $posts;
    }

    /**
     * Handle 'save_post' action whenever someone saves a post.
     *
     * @return void
     */
    function save_post($post_id, $post) {
        if ($post->post_type !== 'post') {
            return;
        }

        $variants = get_field('title_variants', $post_id);
        if (empty($variants)) {
            return;
        }

        $split_test_post_id = get_post_meta($post_id, 'split_test_post_id', true);
        if (empty($split_test_post_id)) {
            $split_test_post_id = wp_insert_post([
                'post_type' => 'split_test',
                'post_title' => "Post $post_id Title",
            ]);
            update_post_meta($post_id, 'split_test_post_id', $split_test_post_id);
            update_post_meta($split_test_post_id, 'target_post_id', $post_id);
            update_post_meta($split_test_post_id, 'variant_count', count($variants) + 1);
            for ($i = 0; $i <= count($variants); $i++) {
                update_post_meta($split_test_post_id, "variant_{$i}_test", 0);
                update_post_meta($split_test_post_id, "variant_{$i}_convert", 0);
            }
        } else {
            $old_count = get_post_meta($split_test_post_id, 'variant_count', true);
            if ($old_count < $new_count) {
                for ($i = $old_count; $i < $new_count; $i++) {
                    update_post_meta($split_test_post_id, "variant_{$i}_test", 0);
                    update_post_meta($split_test_post_id, "variant_{$i}_convert", 0);
                }
            }
        }
    }

    /**
     * Update, or "boop," a variant statistic ('test' or 'convert') for a given
     * split test post ID.
     *
     * @return void
     */
    function boop_split_test($split_test_id, $test_type, $variant_index, $boop_type) {
        global $wpdb;
        $now = wp_date('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare("
            INSERT INTO {$wpdb->prefix}split_tests
            (split_test_id, test_type, variant_index, test_or_convert, created_time)
            VALUES (%d, %s, %d, %s, %s)
        ", $split_test_id, $test_type, $variant_index, $boop_type, $now));
    }

    /**
     * Retrieve the number of "boops" for a given variant statistic.
     *
     * @return void
     */
    function get_boop_count($split_test_id, $test_type, $variant_index, $boop_type) {
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
     * Retrieve the variant permalink for a given split test ID and index.
     *
     * @return string
     */
    function get_variant_permalink($split_test_id, $url_slug) {
        $target_post_id = get_post_meta($split_test_id, 'target_post_id', true);
        $link_template = get_the_permalink($target_post_id, true);
        return str_replace('%postname%', $url_slug, $link_template);
    }

    /**
     * "Boop" the 'test' variable for a given target post ID.
     *
     * @return void
     */
    function variant_test($target_id, $test_type, $variant_index) {
        $split_test_id = get_post_meta($target_id, 'split_test_post_id', true);
        if (!$split_test_id) {
            return;
        }
        $this->boop_split_test($split_test_id, $test_type, $variant_index, 'test');
    }

    /**
     * "Boop" the 'convert' variable for a given target post ID.
     *
     * @return void
     */
    function variant_convert($target_id, $test_type, $variant_index) {
        $split_test_id = get_post_meta($target_id, 'split_test_post_id', true);
        if (!$split_test_id) {
            return;
        }
        $this->boop_split_test($split_test_id, $test_type, $variant_index, 'convert');
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

        $target_id = get_post_meta($post->ID, 'target_post_id', true);
        $target_post = get_post($target_id);
        $_variants = get_field('title_variants', $target_id);

        if (empty($_variants)) {
            echo "Error: No variants found.";
            return;
        }

        $variants = [
            [
                'name' => 'Default',
                'headline' => $target_post->post_title,
                'url_slug' => $target_post->post_name,
            ],
            ... $_variants
        ];

        ?>
        <table class="variant-test-results">
            <tr>
                <th>Variant</th>
                <th>Headline</th>
                <th>Tests</th>
                <th>Conversions</th>
                <th>Rate</th>
            </tr>
            <?php foreach ($variants as $index => $variant) {

                $num_tests = intval($this->get_boop_count($post->ID, 'title', $index, 'test'));
                $num_converts = intval($this->get_boop_count($post->ID, 'title', $index, 'convert'));
                if ($num_tests > 0) {
                    $rate = number_format($num_converts / $num_tests * 100, 1) . '%';
                } else {
                    $rate = '&mdash;';
                }

                $variant_link = $this->get_variant_permalink($post->ID, $variant['url_slug']);

                ?>
                <tr>
                    <td><?php echo $variant['name']; ?></td>
                    <td><a href="<?php echo $variant_link; ?>"><?php echo $variant['headline']; ?></a></td>
                    <td><?php echo $num_tests; ?></td>
                    <td><?php echo $num_converts; ?></td>
                    <td><?php echo $rate; ?></td>
                </tr>
            <?php } ?>
        </table>
        <?php
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