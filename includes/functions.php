<?php
/**
 * Template functions for themes and other plugins.
 *
 * @package SimpleMarkdownLLMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get the Markdown URL for a post.
 *
 * Pretty permalinks get ".md" (replacing any trailing slash). Anything else —
 * plain permalinks, drafts, the static front page — gets "format=md".
 *
 * @param int|WP_Post|null $post Post ID or object. Defaults to the current post.
 * @return string
 */
function simple_markdown_llms_get_url( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}

	$permalink = (string) get_permalink( $post );

	if ( get_option( 'permalink_structure' ) && false === strpos( $permalink, '?' ) ) {
		$path      = rtrim( (string) wp_parse_url( $permalink, PHP_URL_PATH ), '/' );
		$home_path = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( $path !== $home_path ) {
			return rtrim( $permalink, '/' ) . '.md';
		}
	}

	return add_query_arg( 'format', 'md', $permalink );
}

/**
 * Whether a post has a Markdown version.
 *
 * @param int|WP_Post|null $post Post ID or object. Defaults to the current post.
 * @return bool
 */
function simple_markdown_llms_has_markdown( $post = null ) {
	$post = get_post( $post );
	return $post instanceof WP_Post && Simple_Markdown_LLMS::is_enabled_for( $post );
}
