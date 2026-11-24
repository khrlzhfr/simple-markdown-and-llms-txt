<?php
/**
 * The llms.txt section under Settings → Reading, its Refresh button and the
 * Settings link on the Plugins screen.
 *
 * @package SimpleMarkdownLLMS
 */

defined( 'ABSPATH' ) || exit;

final class Simple_Markdown_LLMS_Admin {

	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'register_settings_field' ] );
		add_action( 'admin_post_simple_markdown_llms_refresh', [ __CLASS__, 'handle_refresh' ] );
		add_action( 'admin_notices', [ __CLASS__, 'refresh_notice' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( SIMPLE_MARKDOWN_LLMS_FILE ), [ __CLASS__, 'action_links' ] );
	}

	public static function register_settings_field() {
		add_settings_field(
			'simple_markdown_llms_txt',
			esc_html__( 'llms.txt', 'khrlzhfr-simple-markdown-and-llms-txt' ),
			[ __CLASS__, 'render_settings_field' ],
			'reading'
		);
	}

	public static function render_settings_field() {
		echo '<div id="simple-markdown-llms-txt">';

		if ( ! Simple_Markdown_LLMS::llms_txt_enabled() ) {
			if ( '' === (string) get_option( 'permalink_structure' ) ) {
				esc_html_e( 'Not served. This plugin publishes llms.txt only when pretty permalinks are on.', 'khrlzhfr-simple-markdown-and-llms-txt' );
			} elseif ( file_exists( ABSPATH . 'llms.txt' ) ) {
				esc_html_e( 'Not served by this plugin. There is already an llms.txt file in the WordPress folder.', 'khrlzhfr-simple-markdown-and-llms-txt' );
			} else {
				esc_html_e( 'Turned off by the simple_markdown_llms_txt_enabled filter.', 'khrlzhfr-simple-markdown-and-llms-txt' );
			}
			echo '</div>';
			return;
		}

		$url    = Simple_Markdown_LLMS::llms_txt_url();
		$cached = get_transient( Simple_Markdown_LLMS::LLMS_TRANSIENT );

		printf( '<p><a href="%1$s" target="_blank" rel="noopener">%2$s</a></p>', esc_url( $url ), esc_html( $url ) );

		echo '<p>';
		if ( is_array( $cached ) && isset( $cached['time'] ) ) {
			printf(
				/* translators: 1: time since last build, 2: number of entries. */
				esc_html__( 'Last built %1$s ago with %2$s entries.', 'khrlzhfr-simple-markdown-and-llms-txt' ),
				esc_html( human_time_diff( (int) $cached['time'] ) ),
				esc_html( number_format_i18n( (int) $cached['count'] ) )
			);
		} else {
			esc_html_e( 'Will be built the next time someone opens it.', 'khrlzhfr-simple-markdown-and-llms-txt' );
		}
		echo '</p>';

		printf(
			'<p><a href="%1$s" class="button">%2$s</a></p>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=simple_markdown_llms_refresh' ), 'simple_markdown_llms_refresh' ) ),
			esc_html__( 'Refresh llms.txt', 'khrlzhfr-simple-markdown-and-llms-txt' )
		);

		echo '<p class="description">' . esc_html__( 'Rebuilt automatically when posts or pages are saved, trashed or deleted in the dashboard. Changes made through the REST API with an application password, XML-RPC or WP-CLI appear within 12 hours, or straight away when you click Refresh.', 'khrlzhfr-simple-markdown-and-llms-txt' ) . '</p>';
		echo '</div>';
	}

	public static function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'khrlzhfr-simple-markdown-and-llms-txt' ), 403 );
		}
		check_admin_referer( 'simple_markdown_llms_refresh' );

		Simple_Markdown_LLMS::flush_llms_txt();
		if ( Simple_Markdown_LLMS::llms_txt_enabled() ) {
			Simple_Markdown_LLMS::llms_txt();
		}

		wp_safe_redirect( add_query_arg( 'simple-markdown-llms-refreshed', '1', admin_url( 'options-reading.php' ) ) . '#simple-markdown-llms-txt' );
		exit;
	}

	public static function refresh_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		// Display-only flag set by handle_refresh(), which checked the nonce.
		if ( empty( $_GET['simple-markdown-llms-refreshed'] ) || ! $screen || 'options-reading' !== $screen->id ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'llms.txt has been rebuilt.', 'khrlzhfr-simple-markdown-and-llms-txt' ) . '</p></div>';
	}

	/**
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-reading.php#simple-markdown-llms-txt' ) ) . '">' . esc_html__( 'Settings', 'khrlzhfr-simple-markdown-and-llms-txt' ) . '</a>'
		);
		return $links;
	}
}
