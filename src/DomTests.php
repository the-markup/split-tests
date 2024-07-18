<?php
/**
 * Class DomTests
 *
 * @package   SplitTests
 * @author    The Markup
 * @license   GPL-2.0-or-later
 * @link      https://themarkup.org/
 * @copyright 2024 The Markup
 */

namespace SplitTests;

class DOMTests {
    /**
     * Keeps a reference to the parent plugin.
     *
     * @var SplitTests\Plugin
     */
    protected $plugin = null;

    /**
	 * Setup DOM test hooks.
	 *
	 * @return void
	 */
	function __construct($plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Query for DOM variants and select one per test.
     *
     * @return array
     */
    function get_variants() {
        $tests = get_posts([
            'post_type' => 'split_test',
            'meta_key' => 'test_type',
            'meta_value' => 'dom'
        ]);
        $variants = [];
        foreach ($tests as $post) {
            $variant = $this->choose_variant($post);
            if (!empty($variant)) {
                $variants[$post->ID] = $variant;
            }
        }
        return $variants;
    }

    /**
     * Select a variant from a split_test post.
     *
     * @return array | null
     */
    function choose_variant($post) {
        // Query for any variants on this post.
        $_variants = get_field('dom_variants', $post->ID);
        if (empty($_variants)) {
            return null;
        }

        // Add a choice from the post's original values.
        $variants = [
            [
                'noop' => true
            ],
            ... $_variants
        ];
        $index = rand(0, count($variants) - 1);
        $variant = $variants[$index];

        // Don't include the internal 'name' value.
        unset($variant['name']);

        // Add the index number
        $variant['index'] = $index;

        // Add the conversion type
        $variant['conversion'] = get_field('conversion', $post->ID);

        // Add click conversion details
        if ($variant['conversion'] == 'click') {
            $variant['click_selector'] = get_field('click_selector', $post->ID);
            $variant['click_content'] = get_field('click_content', $post->ID);
        }

        // Increment the 'test' variable for this variant.
        // $this->plugin->increment('test', $post->ID, $index);

        return $variant;
    }


}