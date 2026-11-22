<?php
/**
 * Plugin Name:       Simple Markdown and llms.txt
 * Plugin URI:        https://github.com/khrlzhfr/simple-markdown-and-llms-txt
 * Description:       Serve any post or page as Markdown with YAML front matter. Add <code>.md</code> to a pretty permalink, add <code>format=md</code> to the query string, or request the page with <code>Accept: text/markdown</code>. Also publishes <code>/llms.txt</code>.
 * Version:           2.0.0
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Author:            Khairil Zhafri
 * Author URI:        https://khrlzh.fr
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-markdown-and-llms-txt
 *
 * @package SimpleMarkdownLLMS
 */

defined( 'ABSPATH' ) || exit;

define( 'SIMPLE_MARKDOWN_LLMS_FILE', __FILE__ );
define( 'SIMPLE_MARKDOWN_LLMS_VERSION', '2.0.0' );

require_once __DIR__ . '/includes/class-simple-markdown-llms.php';
require_once __DIR__ . '/includes/functions.php';

// Copies installed from GitHub update from GitHub Releases; the WordPress.org build leaves this file out.
if ( file_exists( __DIR__ . '/includes/github-updater.php' ) ) {
	require_once __DIR__ . '/includes/github-updater.php';
}

Simple_Markdown_LLMS::init();

if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-simple-markdown-llms-admin.php';
	Simple_Markdown_LLMS_Admin::init();
}
