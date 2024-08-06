# Split Tests #
Basic A/B testing for WordPress.

## Description ##

A WordPRess plugin to add A/B split tests without tracking individual users. Currently there are two kind of tests:

* **Post title tests**: test multiple headlines for a single post.
* **DOM tests**: test arbitrary changes to text based on DOM manipluations.

Depends on [Advanced Custom Fields Pro plugin](https://www.advancedcustomfields.com/pro/), which you will need to install and license separately.

## Installation ##

This section describes how to install the plugin and get it working.

1. Upload `split-tests/` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

## Developer setup ##

This repo contains everything you need to get a test environment setup using the official [WordPress docker image](https://hub.docker.com/_/wordpress).

__Developer dependencies__

* [node.js](https://nodejs.org/) (tested on v20)
* [nvm](https://github.com/nvm-sh/nvm#readme)
* [Docker Desktop](https://www.docker.com/products/docker-desktop)

__Build and start__

        ./bin/build
        ./bin/start

__Local website__

Once you've built and started the docker containers, you can load up the website at [localhost:8080](http://localhost:8080). If you reload the page a couple times, you should see parts of the page change in response to two tests that are set up.

1. **DOM test:** there are two variants for the about text "A commitment to innovation and sustainability" and "A commitment to maintenance and durability". If you click on the "About us" button, that will register as a conversion for a given variant.
2. **Post title:** there are three variants for the Hello World post, if you scroll down to "Watch, Read, Listen" and reload you should see English, Spanish, and French versions of "Hello World." Clicking through to load the post will register as a conversion for that test.

__WordPress admin credentials__

Username: `admin`  
Password: `password`

You can explore the example tests by clicking on [Split Tests](http://localhost:8080/wp-admin/edit.php?post_type=split_test) in the admin sidebar.

## Frequently Asked Questions ##

### Did you say there's no tracking? ###

Yes, we count how many times a test is seen and how many times it converts, but we don't set/read cookies or otherwise attempt to track individual requests.

### How do you define a conversion? ###

There are currently two kinds of conversions: **page loads** and **clicks** (on a specific configured element).

### Does the plugin handle front-end caching? ###

Yes, the tests will work fine with HTML generated behind a CDN, or using other kinds of front-end caching.

## Screenshots ##

### 1. Split Tests posts page ###
![Split Tests posts page](assets/screenshot-1.png)

### 2. Split Test post editor ###
![Split Test post editor](assets/screenshot-2.png)

### 3. Post Title variants ###
![Post Title variants](assets/screenshot-3.png)


## Changelog ##

### 0.0.4 ###
Adds a cron mechanism to combine raw events in the database into daily aggregates

### 0.0.3 ###
Context for where tests run ('all', 'home', or a 'url' pattern)

### 0.0.2 ###
Split tests for DOM changes

### 0.0.1 ###
Split tests for post titles