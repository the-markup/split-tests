<?php
/**
 * Class Database
 *
 * @package   SplitTests
 * @author    The Markup
 * @license   GPL-2.0-or-later
 * @link      https://themarkup.org/
 * @copyright 2024 The Markup
 */

namespace SplitTests;

class Database {

    /**
     * Database version lets us apply updates to the db schema in the future.
     *
     * @var int
     */
    protected $db_version = 3;

    /**
     * Checks if the database schema requires updating, then updates it if necessary.
     *
     * @return SplitTests\Database
     */
    function __construct() {
        $curr_db_version = get_option('split_tests_db_version', 0);
        if ($curr_db_version < $this->db_version) {
            $this->migrate_db();
        }
    }

    /**
     * Migrates the database table schema using dbDelta.
     *
     * @return void
     */
    function migrate_db() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'split_tests';
        $charset_collate = $wpdb->get_charset_collate();

        // See also: https://developer.wordpress.org/plugins/creating-tables-with-plugins/#creating-or-updating-the-table
        $sql = "CREATE TABLE $table_name (
            split_test_id int(20) UNSIGNED NOT NULL,
            test_type varchar(255) NOT NULL,
            variant_index int(4) UNSIGNED NOT NULL,
            test_or_convert enum('test', 'convert'),
            granularity varchar(255) DEFAULT 'raw',
            count int(11) UNSIGNED NOT NULL DEFAULT 1,
            created_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            KEY split_test_idx (split_test_id, test_type, variant_index)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        update_option('split_tests_db_version', $this->db_version);
    }
}