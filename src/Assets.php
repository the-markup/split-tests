<?php
/**
 * Class Assets
 *
 * @package   SplitTests
 * @author    The Markup
 * @license   GPL-2.0-or-later
 * @link      https://themarkup.org/
 * @copyright 2024 The Markup
 */

namespace SplitTests;

class Assets {

    /**
     * Keeps a reference to the parent plugin.
     *
     * @var SplitTests\Plugin
     */
    protected $plugin = null;

    /**
     * Enqueue JS and CSS assets.
     *
     * @return SplitTests\Assets
     */
    function __construct($plugin) {
        $this->plugin = $plugin;

        // Enqueue front-end JS
        add_action('wp_enqueue_scripts', [$this, 'wp_enqueue_scripts']);

        // Output any active DOM test CSS
        add_action('wp_print_scripts', [$this, 'wp_print_scripts']);

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
    }

    /**
     * Enqueue JavaScript for the front-end.
     *
     * @return void
     */
    function wp_enqueue_scripts() {
        $script = $this->get_script_details();

        wp_enqueue_script(
            'split-tests',
            $script['url'],
            $script['dependencies'],
            $script['version']
        );

        wp_localize_script('split-tests', 'split_tests', $script['localize']);
    }

    /**
     * Add CSS style when the scripts are getting output.
     *
     * @return void
     */
    function wp_print_scripts() {
        if (is_admin()) {
            return;
        }
        $css = $this->plugin->dom_tests->get_css();
        if (!empty($css)) {
            echo "<style>\n$css</style>\n";
        }
    }

    /**
     * Return JavaScript details for the front-end.
     *
     * @return array
     */
    function get_script_details() {
        $url = plugins_url('build/split-tests.js', __DIR__);
        $asset = include(dirname(__DIR__) . '/build/split-tests.asset.php');
        $admin_ajax_url = admin_url('admin-ajax.php') . '?action=split_tests';
        $endpoint_url = apply_filters('split_tests_endpoint_url', $admin_ajax_url);
        return [
            'url' => $url,
            ... $asset,
            'localize' => [
                'endpoint_url' => $endpoint_url,
                'nonce' => wp_create_nonce('split_tests_event'),
                'tests' => [
                    ... $this->plugin->dom_tests->get_tests(),
                    ... $this->plugin->title_tests->get_tests(),
                ],
                'onload' => $this->plugin->onload_events,
                'css' => $this->plugin->dom_tests->get_css(),
            ]
        ];
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
}