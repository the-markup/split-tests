<?php
/**
 * Class PostType
 *
 * @package   SplitTests
 * @author    The Markup
 * @license   GPL-2.0-or-later
 * @link      https://themarkup.org/
 * @copyright 2024 The Markup
 */

namespace SplitTests;

class PostType {

    /**
     * Keeps a reference to the parent plugin.
     *
     * @var SplitTests\Plugin
     */
    protected $plugin = null;

    /**
     * Registers a split_test post type.
     *
     * @return SplitTests\PostType
     */
    function __construct($plugin) {
        $this->plugin = $plugin;
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

        // Show test results on split_test edit page
        add_action('edit_form_after_title', [$this, 'edit_form_after_title']);

        // Add results column header to Split Tests table
        add_filter('manage_split_test_posts_columns', [$this, 'manage_split_test_posts_columns']);

        // Add results column values to Split Tests table
        add_filter('manage_split_test_posts_custom_column', [$this, 'manage_split_test_posts_custom_column'], 10, 2);
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
            $this->plugin->title_tests->show_results($post);
        } else if ($test_type == 'dom') {
            $this->plugin->dom_tests->show_results($post);
        }
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
            $this->plugin->title_tests->show_results_summary($post_id);
        } else if ($type == 'dom') {
            $this->plugin->dom_tests->show_results_summary($post_id);
        }
    }
}