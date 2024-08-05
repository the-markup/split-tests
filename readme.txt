=== Split Tests ===
Contributors: themarkup
Donate link: https://themarkup.org/donate
Tags: split-tests
Requires at least: 4.5
Tested up to: 6.6.1
Requires PHP: 5.6
Stable tag: 0.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Basic A/B testing for WordPress.

== Description ==

A WordPRess plugin to add A/B split tests that without tracking individual users. Currently there are two kind of tests:

* **Post title tests**: test multiple headlines for a single post.
* **DOM tests**: test arbitrary changes to text based on DOM manipluations.

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload `split-tests/` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Did you say there's no tracking? =

Yes, we count how many times a test is seen and how many times it converts, but we don't set/read cookies or otherwise attempt to track individual requests.

= How do you define a conversion? =

There are currently two kinds of conversions: **page loads** and **clicks** (on a specific configured element).

= Does the plugin handle front-end caching? =

Yes, the tests will work fine with HTML generated behind a CDN, or using other kinds of front-end caching.

== Screenshots ==

TK

== Changelog ==

= 0.0.1 =
Split tests for post titles

= 0.0.2 =
Split tests for DOM changes

= 0.0.3 =
Context for where tests run ('all', 'home', or a 'url' pattern)