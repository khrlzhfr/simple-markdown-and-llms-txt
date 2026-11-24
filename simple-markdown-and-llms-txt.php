<?php
/**
 * Plugin Name:       khrlzhfr Simple Markdown and llms.txt
 * Plugin URI:        https://github.com/khrlzhfr/simple-markdown-and-llms-txt
 * Description:       Serve any post or page as Markdown with YAML front matter. Add <code>.md</code> to a pretty permalink, add <code>format=md</code> to the query string, or request the page with <code>Accept: text/markdown</code>. Also publishes <code>/llms.txt</code>.
 * Version:           2.1.0
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Author:            Khairil Zhafri
 * Author URI:        https://khrlzh.fr
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       khrlzhfr-simple-markdown-and-llms-txt
 *
 * @package SimpleMarkdownLLMS
 */

defined( 'ABSPATH' ) || exit;

// Another copy of this plugin is already running (for example one installed under its earlier folder name): stay inactive and say so on the Plugins screen.
if ( defined( 'SIMPLE_MARKDOWN_LLMS_FILE' ) ) {
	add_action(
		'admin_notices',
		static function () {
			global $pagenow;
			if ( 'plugins.php' !== $pagenow || ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: plugin name, 2: folder of the copy that is running */
						__( '%1$s is installed twice. The copy in the %2$s folder is running. Deactivate and delete the other copy; its settings and data are kept.', 'khrlzhfr-simple-markdown-and-llms-txt' ),
						'khrlzhfr Simple Markdown and llms.txt',
						dirname( plugin_basename( SIMPLE_MARKDOWN_LLMS_FILE ) )
					)
				)
			);
		}
	);
	return;
}

define( 'SIMPLE_MARKDOWN_LLMS_FILE', __FILE__ );
define( 'SIMPLE_MARKDOWN_LLMS_VERSION', '2.1.0' );

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
