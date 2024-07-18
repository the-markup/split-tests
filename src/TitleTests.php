<?php
/**
 * Class TitleTests
 *
 * @package   SplitTests
 * @author    The Markup
 * @license   GPL-2.0-or-later
 * @link      https://themarkup.org/
 * @copyright 2024 The Markup
 */

namespace SplitTests;

class TitleTests {
    /**
     * Keeps a reference to the parent plugin.
     *
     * @var Plugin
     */
    protected $plugin = null;

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
     * A flag for turning off the 'posts_results' filter, to avoid
     * double-filtering.
     *
     * @var bool
     */
    protected $ignore_post_variants = false;

    /**
	 * Setup title test hooks.
	 *
	 * @return void
	 */
	function __construct($plugin) {
        $this->plugin = $plugin;
        $this->setup_hooks();
    }

    /**
     * Setup action/filter handlers.
     *
     * @return void
     */
    function setup_hooks() {
        // This filter is ours, for adjusting individual posts with a variant
        add_filter('split_tests_post_variant', [$this, 'post_variant']);
        
        // Incoming page request
        add_filter('request', [$this, 'request']);

        // WP_Query posts filter
        add_filter('posts_results', [$this, 'posts_results'], 10, 2);
        
        // Permalink filter
        add_filter('pre_post_link', [$this, 'pre_post_link'], 10, 2);

        // Add split_test posts whenever you save a post with a split test
        add_action('save_post', [$this, 'save_post'], 10, 2);

        // Add some JS for Split Test post editor
        add_action('admin_print_scripts', [$this, 'admin_print_scripts']);
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
        if (is_single() && empty($posts) && !empty($this->request_slug)) {
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
                    $this->increment_convert_count($post->ID, $variant_index);
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

        // Increment the 'test' variable for this variant.
        $this->increment_test_count($post->ID, $index);

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

        // Look up variant URL slugs that match the unknown request slug
        $post_ids = $wpdb->get_col($wpdb->prepare("
			SELECT post_id
			FROM $wpdb->postmeta
			WHERE meta_key LIKE 'title_variants_%_url_slug'
			AND meta_value = '%s'
		", $request_slug), 0);
		if (empty($post_ids)) {
			return [];
		}

        // Replace the 'name' query with a list of IDs that have a variant slug
        $query_vars = $query->query_vars;
        unset($query_vars['name']);
        $query_vars['post__in'] = $post_ids;

        // We don't want this lookup query to be modified by the 'posts_results' filter
        $this->ignore_post_variants = true;
        $posts = get_posts($query_vars);
        $this->ignore_post_variants = false;

        if (count($posts) == 1) {
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
     * Adds some JS for Split Test post types specific to title tests.
     *
     * @return void
     */
    function admin_print_scripts() {
        global $post;
        if (!$post || $post->post_type != 'split_test') {
            return;
        }
        $target_post_id = get_post_meta($post->ID, 'target_post_id', true) ?: 'null';
        echo <<<END
<script>
// Added for post title split tests
const targetPostId = $target_post_id;
</script>
END;
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
     * Increment the 'test' statistic.
     *
     * @return void
     */
    function increment_test_count($target_id, $variant_index) {
        $split_test_id = get_post_meta($target_id, 'split_test_post_id', true);
        if (!$split_test_id) {
            return;
        }
        $split_test_id = intval($split_test_id);
        $this->plugin->increment('test', $split_test_id, $variant_index);
    }

    /**
     * Increment the 'convert' statistic.
     *
     * @return void
     */
    function increment_convert_count($target_id, $variant_index) {
        $split_test_id = get_post_meta($target_id, 'split_test_post_id', true);
        if (!$split_test_id) {
            return;
        }
        $split_test_id = intval($split_test_id);
        $this->plugin->increment('convert', $split_test_id, $variant_index);
    }

    function show_results($post) {
        $target_id = get_post_meta($post->ID, 'target_post_id', true);
        $target_post = get_post($target_id);
        $_variants = get_field('title_variants', $target_id);

        if (empty($_variants)) {
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
                <th>Rate</th>
                <th>Headline</th>
                <th>Tests</th>
                <th>Conversions</th>
            </tr>
            <?php foreach ($variants as $index => $variant) {

                $num_tests = intval($this->plugin->get_count($post->ID, 'title', $index, 'test'));
                $num_converts = intval($this->plugin->get_count($post->ID, 'title', $index, 'convert'));
                if ($num_tests > 0) {
                    $rate = number_format($num_converts / $num_tests * 100, 1) . '%';
                } else {
                    $rate = '&mdash;';
                }

                $variant_link = $this->get_variant_permalink($post->ID, $variant['url_slug']);

                ?>
                <tr>
                    <td><?php echo $variant['name']; ?></td>
                    <td><?php echo $rate; ?></td>
                    <td><a href="<?php echo $variant_link; ?>"><?php echo $variant['headline']; ?></a></td>
                    <td><?php echo $num_tests; ?></td>
                    <td><?php echo $num_converts; ?></td>
                </tr>
            <?php } ?>
        </table>
        <?php
    }
}