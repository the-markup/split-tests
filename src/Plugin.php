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
	 * Setup filters.
	 *
	 * @return void
	 */
	function __construct() {
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
        // Check if we already chose a variant for this post.
        if (! empty($this->chosen_variants[$post->ID])) {
            return $this->chosen_variants[$post->ID];
        }

        if (is_single()) {
            return null;
        }

        // Query for any variants on this post.
        $variants = get_field('title_variants', $post->ID);
        if (empty($variants)) {
            return null;
        }

        // Add a choice from the post's original values.
        $variants[] = [
            'headline' => $post->post_title,
            'url_slug' => $post->post_name
        ];
        $index = rand(0, count($variants) - 1);
        $variant = $variants[$index];

        // Cache this variant choice
        $this->chosen_variants[$post->ID] = $variant;
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
        $posts = get_posts($query_vars);
        if (is_single() && count($posts) == 1) {
            $variants = get_field('title_variants', $posts[0]->ID);
            foreach ($variants as $variant) {
                if ($variant['url_slug'] == $request_slug) {
                    $this->chosen_variants[$posts[0]->ID] = $variant;
                    break;
                }
            }
        }
        return $posts;
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
            'not_found_in_trash' => 'No %name found in trash',
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