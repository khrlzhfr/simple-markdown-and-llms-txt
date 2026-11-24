<?php
/**
 * Removes the plugin's stored data: the text length saved on each post and
 * the cached llms.txt, on every site.
 *
 * @package SimpleMarkdownLLMS
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/** Whether another copy of this plugin (same main file, other folder) is still installed. */
function simple_markdown_llms_other_copy_installed() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	foreach ( array_keys( get_plugins() ) as $file ) {
		if ( basename( $file ) === basename( WP_UNINSTALL_PLUGIN ) && $file !== WP_UNINSTALL_PLUGIN ) {
			return true;
		}
	}
	return false;
}

// Deleting one copy while another stays installed must not remove the data they share.
if ( simple_markdown_llms_other_copy_installed() ) {
	return;
}

/** Cleans up the current site. */
function simple_markdown_llms_uninstall_site() {
	delete_post_meta_by_key( '_simple_markdown_llms_text_length' );
	delete_transient( 'simple_markdown_llms_txt_cache' );
}

/** Cleans up every site on a network, or the single site. */
function simple_markdown_llms_uninstall() {
	if ( is_multisite() ) {
		foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $site_id ) {
			switch_to_blog( $site_id );
			simple_markdown_llms_uninstall_site();
			restore_current_blog();
		}
	} else {
		simple_markdown_llms_uninstall_site();
	}
}

simple_markdown_llms_uninstall();
