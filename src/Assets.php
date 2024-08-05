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
        $asset = include(dirname(__DIR__) . '/build/split-tests.asset.php');
        wp_enqueue_script(
            'split-tests',
            plugins_url('build/split-tests.js', __DIR__),
            $asset['dependencies'],
            $asset['version']
        );

        wp_localize_script('split-tests', 'split_tests', [
            'nonce' => wp_create_nonce('wp_rest'),
            'onload' => $this->plugin->onload_events,
            'dom' => $this->plugin->dom_tests->get_variants()
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
}