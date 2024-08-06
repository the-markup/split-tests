<?php
/**
 * Class Cron
 *
 * @package   SplitTests
 * @author    The Markup
 * @license   GPL-2.0-or-later
 * @link      https://themarkup.org/
 * @copyright 2024 The Markup
 */

namespace SplitTests;

class Cron {

    /**
     * Schedule WP_Cron events.
     *
     * @return SplitTests\Cron
     */
    function __construct() {
        // Add cron handler
        add_action('split_tests_cron', [$this, 'split_tests_cron']);

        // Add an endpoint for testing purposes
        // i.e., http://localhost:8080/wp-admin/admin-ajax.php?action=split_tests_cron
        add_action('wp_ajax_split_tests_cron', [$this, 'split_tests_cron']);
    }

    /**
     * Schedule cron events.
     *
     * @return void
     */
    function schedule() {
        if (! wp_next_scheduled('split_tests_cron')) {
            wp_schedule_event(time(), 'twicedaily', 'split_tests_cron');
        }
    }

    /**
     * Unschedule cron events.
     *
     * @return void
     */
    function unschedule() {
        wp_clear_scheduled_hook('split_tests_cron');
    }

    /**
     * Load split test events and aggregate any records older than 24 hours.
     *
     * @return void
     */
    function split_tests_cron() {
        global $wpdb;

        $seconds_per_day = 60 * 60 * 24;
        $today = wp_date('Y-m-d');

        // Select raw aggregate counts by day
        $days = $wpdb->get_results($wpdb->prepare("
            SELECT split_test_id,
                   test_type,
                   variant_index,
                   test_or_convert,
                   DATE(created_time) AS created_date,
                   SUM(count) AS count
            FROM {$wpdb->prefix}split_tests
            WHERE DATE(created_time) < %s
              AND granularity = 'raw'
            GROUP BY split_test_id, variant_index, test_or_convert, created_date
        ", $today));

        foreach ($days as $day) {
            // Insert aggregate results, granularity = 'day'
            $wpdb->query($wpdb->prepare("
                INSERT INTO {$wpdb->prefix}split_tests
                (split_test_id, test_type, variant_index, test_or_convert, granularity, count, created_time)
                VALUES (%d, %s, %d, %s, %s, %d, %s)
            ", $day->split_test_id, $day->test_type, $day->variant_index, $day->test_or_convert, 'day', $day->count, $day->created_date));
        }

        // Delete raw results
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}split_tests
            WHERE DATE(created_time) < %s
                AND granularity = 'raw'
        ", $today));
    }
}