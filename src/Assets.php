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
     * Return JavaScript details for the front-end.
     *
     * @return array
     */
    function get_script_details() {
        $url = plugins_url('build/split-tests.js', __DIR__);
        $asset = include(dirname(__DIR__) . '/build/split-tests.asset.php');
        return [
            'url' => $url,
            ... $asset,
            'localize' => [
                'endpoint_url' => admin_url('admin-ajax.php') . '?action=split_tests',
                'nonce' => wp_create_nonce('split_tests_event'),
                'onload' => $this->plugin->onload_events,
                'dom' => $this->plugin->dom_tests->get_variants()
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