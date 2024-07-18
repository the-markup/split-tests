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

        return $variant;
    }

    /**
     * Display the split test results.
     *
     * @return void
     */
    function show_results($post) {
        $_variants = get_field('dom_variants', $post->ID);

        if (empty($_variants)) {
            return;
        }

        foreach ($_variants as $index => $variant) {
            $_variants[$index]['description'] = count($variant['content']) . ' content changes';
        }

        $variants = [
            [
                'name' => 'Default',
                'description' => 'No content changes'
            ],
            ... $_variants
        ];

        ?>
        <table class="variant-test-results">
            <tr>
                <th>Variant</th>
                <th>Rate</th>
                <th>Description</th>
                <th>Tests</th>
                <th>Conversions</th>
            </tr>
            <?php foreach ($variants as $index => $variant) {

                $num_tests = intval($this->plugin->get_count($post->ID, 'dom', $index, 'test'));
                $num_converts = intval($this->plugin->get_count($post->ID, 'dom', $index, 'convert'));
                if ($num_tests > 0) {
                    $rate = number_format($num_converts / $num_tests * 100, 1) . '%';
                } else {
                    $rate = '&mdash;';
                }

                ?>
                <tr>
                    <td><?php echo $variant['name']; ?></td>
                    <td><?php echo $rate; ?></td>
                    <td><?php echo $variant['description']; ?></a></td>
                    <td><?php echo $num_tests; ?></td>
                    <td><?php echo $num_converts; ?></td>
                </tr>
            <?php } ?>
        </table>
        <?php
    }

    /**
     * Show a brief summary of the results, for the split test table column.
     *
     * @return void
     */
    function show_results_summary($post_id) {
        $_variants = get_field('dom_variants', $post_id);
        $variants = [
            ['name' => 'Default'],
            ... $_variants
        ];
        $results = [];
        $top_rate = 0;
        foreach ($variants as $index => $variant) {
            $num_tests = intval($this->plugin->get_count($post_id, 'dom', $index, 'test'));
            $num_converts = intval($this->plugin->get_count($post_id, 'dom', $index, 'convert'));
            if ($num_tests > 0) {
                $rate = $num_converts / $num_tests * 100;
                if ($rate > $top_rate) {
                    $top_rate = $rate;
                    $top_index = $index;
                }
                $rate_str = number_format($rate, 1) . '%';
            } else {
                $rate = 0;
                $rate_str = '&mdash;';
            }
            $results[] = "$rate_str {$variant['name']}";
        }
        if (isset($top_index)) {
            $results[$top_index] = "<strong class=\"split-tests-winner\">$results[$top_index]</strong>";
        }
        echo implode("<br>\n", $results);
    }
}