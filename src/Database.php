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
    protected $db_version = 2;

    /**
     * Checks if the database schema requires updating, then updates it if necessary.
     *
     * @return SplitTests\Database
     */
    function __construct() {
        $curr_db_version = get_option('split_tests_db_version', 0);
        if ($curr_db_version < $this->db_version) {
            $this->migrate_db($curr_db_version);
        }
    }

    /**
     * Migrates the database schema based on a version number.
     *
     * @return void
     */
    function migrate_db($curr_version) {
        // According to a comment on the dbDelta docs, you should always use
        // CREATE, there's no need to UPDATE tables. Testing bears this out.
        // https://developer.wordpress.org/reference/functions/dbdelta/#comment-4925
        if ($curr_version < 1) {
            $sql = $this->migrate_db_1();
        } else if ($curr_version < 2) {
            $sql = $this->migrate_db_2();
        }
	    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	    dbDelta($sql);
        update_option('split_tests_db_version', $this->db_version);
    }

    /**
     * Database migration 1 creates a basic split_tests db table.
     *
     * @return void
     */
    function migrate_db_1() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'split_tests';
        $charset_collate = $wpdb->get_charset_collate();
        return "CREATE TABLE $table_name (
            split_test_id BIGINT(20) UNSIGNED NOT NULL,
            test_type VARCHAR(255) NOT NULL,
            variant_index TINYINT UNSIGNED NOT NULL,
            test_or_convert ENUM('test', 'convert'),
            created_time DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL
        ) $charset_collate;";
    }

    /**
     * Database migration 2 adds two columns to the split_tests table:
     * granularity (default 'raw') and count (default 1).
     *
     * @return void
     */
    function migrate_db_2() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'split_tests';
        $charset_collate = $wpdb->get_charset_collate();
        return "CREATE TABLE $table_name (
            split_test_id BIGINT(20) UNSIGNED NOT NULL,
            test_type VARCHAR(255) NOT NULL,
            variant_index TINYINT UNSIGNED NOT NULL,
            test_or_convert ENUM('test', 'convert'),
            granularity VARCHAR(255) DEFAULT 'raw',
            count INT(8) UNSIGNED NOT NULL DEFAULT 1,
            created_time DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL
        ) $charset_collate;";
    }
}