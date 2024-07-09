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

    protected $chosen_variants = [];

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

        // Setup post variant filter hook
        add_filter('split_tests_post_variant', [$this, 'post_variant']);

        // Modify posts coming out of WP_Query
        add_filter('posts_results', function($posts) {
            foreach ($posts as $post) {
                $post = apply_filters('split_tests_post_variant', $post);
            }
            return $posts;
        });

        // Modify post permalinks
        add_filter('pre_post_link', function($permalink, $post) {
            $post = apply_filters('split_tests_post_variant', $post);
            return $permalink;
        }, 10, 2);
    }

    function post_variant($post) {
        if (is_single()) {
            return $post;
        }
        $variants = get_field('title_variants', $post->ID);
        if (empty($variants)) {
            return $post;
        }
        $variant = $this->get_variant($variants, $post);
        $post->post_title = $variant['headline'];
        $post->post_name = $variant['url_slug'];
        return $post;
    }

    function get_variant($variants, $post) {
        if (! empty($this->chosen_variants[$post->ID])) {
            return $this->chosen_variants[$post->ID];
        }
        // Add default choice
        $variants[] = [
            'headline' => $post->post_title,
            'url_slug' => $post->post_name
        ];
        $index = rand(0, count($variants) - 1);
        $variant = $variants[$index];
        $this->chosen_variants[$post->ID] = $variant;
        return $variant;
    }
}