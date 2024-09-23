<?php
/**
 * Class API
 *
 * @package   SplitTests
 * @author    The Markup
 * @license   GPL-2.0-or-later
 * @link      https://themarkup.org/
 * @copyright 2024 The Markup
 */

namespace SplitTests;

class API {

    /**
     * Keeps a reference to the parent plugin.
     *
     * @var SplitTests\Plugin
     */
    protected $plugin = null;

    /**
     * Register API endpoints.
     *
     * @return SplitTests\API
     */
    function __construct($plugin) {
        $this->plugin = $plugin;
        add_action('wp_ajax_split_tests', [$this, 'ajax_handler']);
        add_action('wp_ajax_nopriv_split_tests', [$this, 'ajax_handler']);
    }

    /**
     * Handle incoming 'event' API request.
     *
     * @return array
     */
    function ajax_handler() {
        $ok_rsp = true;
        try {
            check_ajax_referer('split_tests_event', 'n');
            $test_or_convert = $_POST['t'];
            $split_test_id = intval($_POST['i']);
            $variant_index = intval($_POST['v']);
            $test_type = get_field('test_type', $split_test_id);
            $this->plugin->insert_split_test_event(
                $test_or_convert,
                $split_test_id,
                $test_type,
                $variant_index
            );
        } catch(\Exception $err) {
            $ok_rsp = false;
            error_log($err);
        }
        return [
            'ok' => $ok_rsp
        ];
    }

}