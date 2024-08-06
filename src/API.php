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

        add_action('rest_api_init', function() {
            // Setup events route: /wp-json/split-tests/v1/events
            register_rest_route('split-tests/v1', 'events', [
                'methods' => 'POST',
                'callback' => [$this, 'rest_api_events'],
                'permission_callback' => '__return_true',
          ]);
        });
    }

    /**
     * Handle incoming 'event' API request.
     *
     * @return array
     */
    function rest_api_events($request) {
        $ok_rsp = true;
        try {
            $nonce = $request->get_param('_wpnonce');
            $events = json_decode($request->get_body(), 'as array');
            if (! wp_verify_nonce($nonce, 'wp_rest')) {
                throw new \Exception("rest_api_events: invalid nonce '$nonce'");
            }
            foreach ($events as $event) {
                if (count($event) != 3) {
                    continue;
                }
                list($test_or_convert, $split_test_id, $variant_index) = $event;
                $test_type = get_field('test_type', $split_test_id);
                $this->plugin->insert_split_test_event(
                    $test_or_convert,
                    $split_test_id,
                    $test_type,
                    $variant_index
                );
            }
        } catch(\Exception $err) {
            $ok_rsp = false;
            error_log($err);
        }
        return [
            'ok' => $ok_rsp
        ];
    }

}