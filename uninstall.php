<?php
/**
 * Removes the plugin's stored data: the text length saved on each post and
 * the cached llms.txt, on every site.
 *
 * @package SimpleMarkdownLLMS
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

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
