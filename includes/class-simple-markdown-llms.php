<?php
/**
 * Markdown output, content negotiation and /llms.txt.
 *
 * @package SimpleMarkdownLLMS
 */

defined( 'ABSPATH' ) || exit;

final class Simple_Markdown_LLMS {

	const LLMS_TRANSIENT = 'simple_markdown_llms_txt_cache';
	const META_LENGTH    = '_simple_markdown_llms_text_length';

	/** @var bool Whether the current request came in with a .md suffix. */
	private static $md_suffix = false;

	/** @var string|null The REQUEST_URI before we stripped the .md suffix. */
	private static $original_uri = null;

	/** @var array Per-request cache of is_enabled_for() results. */
	private static $enabled_cache = [];

	public static function init() {
		add_filter( 'do_parse_request', [ __CLASS__, 'parse_request' ], 1 );
		add_action( 'template_redirect', [ __CLASS__, 'maybe_serve' ], 0 );
		add_action( 'wp_head', [ __CLASS__, 'head_links' ] );

		// Count text once at save time, not on every page view.
		add_action( 'save_post', [ __CLASS__, 'store_text_length' ], 10, 2 );

		// Rebuild /llms.txt when content changes in the dashboard.
		foreach ( [ 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' ] as $hook ) {
			add_action( $hook, [ __CLASS__, 'maybe_flush_llms_txt' ] );
		}
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_hooks' ] );
		add_action( 'update_option_blogname', [ __CLASS__, 'flush_llms_txt' ] );
		add_action( 'update_option_blogdescription', [ __CLASS__, 'flush_llms_txt' ] );

	}

	/* ---------------------------------------------------------------------
	 * Request routing
	 * ------------------------------------------------------------------ */

	/**
	 * Runs before WordPress parses the request.
	 *
	 * - Serves /llms.txt.
	 * - Strips a trailing ".md" so WordPress resolves the post as usual:
	 *     /hello-world.md   -> /hello-world/  (structure ends with "/")
	 *     /hello-world.md   -> /hello-world   (structure without trailing slash)
	 *     /parent/child.md  -> /parent/child/
	 *
	 * @param bool $do_parse Whether to parse the request.
	 * @return bool
	 */
	public static function parse_request( $do_parse ) {
		$structure = (string) get_option( 'permalink_structure' );
		if ( ! $do_parse || '' === $structure || empty( $_SERVER['REQUEST_URI'] ) ) {
			return $do_parse;
		}

		$uri  = $_SERVER['REQUEST_URI']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = strtok( $uri, '?' );

		if ( strtolower( $path ) === self::home_path() . '/llms.txt' && self::llms_txt_enabled() ) {
			self::serve_llms_txt();
		}

		$stripped = self::strip_md_suffix( $uri, '/' === substr( $structure, -1 ) );
		if ( null !== $stripped ) {
			self::$md_suffix        = true;
			self::$original_uri     = $uri;
			$_SERVER['REQUEST_URI'] = $stripped;
		}

		return $do_parse;
	}

	/**
	 * Strip a trailing ".md" from the path part of a URI.
	 *
	 * @param string $uri            Request URI (path plus optional query string).
	 * @param bool   $trailing_slash Whether to add a trailing slash after stripping.
	 * @return string|null The rewritten URI, or null if there was no .md suffix.
	 */
	public static function strip_md_suffix( $uri, $trailing_slash ) {
		$parts = explode( '?', $uri, 2 );

		// Need at least one character before ".md" that isn't a slash.
		if ( ! preg_match( '#^(/.*[^/])\.md$#i', $parts[0], $m ) ) {
			return null;
		}

		$path = $trailing_slash ? $m[1] . '/' : $m[1];

		return isset( $parts[1] ) ? $path . '?' . $parts[1] : $path;
	}

	/**
	 * Decide between Markdown and HTML once WordPress knows what's being viewed.
	 */
	public static function maybe_serve() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$via_query = isset( $_GET['format'] ) && 'md' === strtolower( sanitize_key( wp_unslash( $_GET['format'] ) ) );
		$explicit  = self::$md_suffix || $via_query;

		if ( self::$md_suffix ) {
			$_SERVER['REQUEST_URI'] = self::$original_uri;
			// Don't let WordPress "guess" a redirect for a .md URL that didn't resolve.
			remove_action( 'template_redirect', 'redirect_canonical' );
		}

		$post    = is_singular() ? get_queried_object() : null;
		$enabled = $post instanceof WP_Post && self::is_enabled_for( $post );

		// Explicit .md or ?format=md request.
		if ( $explicit ) {
			if ( ! $enabled ) {
				// A ".md" URL with no Markdown behind it is a 404. A "?format=md"
				// on anything else is ignored and the normal page is shown.
				if ( self::$md_suffix ) {
					global $wp_query;
					$wp_query->set_404();
					status_header( 404 );
					nocache_headers();
				}
				return;
			}
			if ( post_password_required( $post ) ) {
				self::send_forbidden();
			}
			self::send( $post );
		}

		if ( ! $enabled || post_password_required( $post ) ) {
			return;
		}

		// Content negotiation: same URL, Markdown for clients that prefer it.
		if ( self::negotiation_enabled() ) {
			header( 'Vary: Accept', false );

			$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? (string) $_SERVER['HTTP_ACCEPT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( self::prefers_markdown( $accept ) ) {
				// Stop page-cache plugins from storing Markdown under the HTML URL.
				if ( ! defined( 'DONOTCACHEPAGE' ) ) {
					define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- standard flag read by page-cache plugins.
				}
				nocache_headers();
				header( 'Content-Location: ' . esc_url_raw( simple_markdown_llms_get_url( $post ) ) );
				self::send( $post );
			}
		}

		// Normal HTML page: advertise the Markdown version in the headers too.
		header( 'Link: <' . esc_url_raw( simple_markdown_llms_get_url( $post ) ) . '>; rel="alternate"; type="text/markdown"', false );
		if ( self::llms_txt_enabled() ) {
			header( 'Link: <' . esc_url_raw( self::llms_txt_url() ) . '>; rel="describedby"', false );
		}
	}

	/**
	 * Whether an Accept header prefers Markdown over HTML.
	 *
	 * True only when text/markdown is listed explicitly and its quality is at
	 * least as high as text/html's. Browsers never list text/markdown, so they
	 * always get HTML. "text/markdown, text/html, *\/*" (Claude Code, Cursor)
	 * gets Markdown.
	 *
	 * @param string $accept Accept header.
	 * @return bool
	 */
	public static function prefers_markdown( $accept ) {
		if ( '' === $accept || false === stripos( $accept, 'text/markdown' ) ) {
			return false;
		}

		$md   = 0.0;
		$html = 0.0;

		foreach ( explode( ',', $accept ) as $range ) {
			$params  = array_map( 'trim', explode( ';', $range ) );
			$type    = strtolower( (string) array_shift( $params ) );
			$quality = 1.0;
			foreach ( $params as $param ) {
				if ( preg_match( '/^q\s*=\s*([0-9.]+)$/i', $param, $m ) ) {
					$quality = (float) $m[1];
				}
			}
			if ( 'text/markdown' === $type ) {
				$md = max( $md, $quality );
			} elseif ( 'text/html' === $type || 'application/xhtml+xml' === $type ) {
				$html = max( $html, $quality );
			}
		}

		return $md > 0 && $md >= $html;
	}

	/* ---------------------------------------------------------------------
	 * Eligibility
	 * ------------------------------------------------------------------ */

	/**
	 * Whether Markdown output is available for a post.
	 *
	 * Requires a publicly viewable post type, no page builder in charge of
	 * rendering, and enough real text in post_content.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	public static function is_enabled_for( WP_Post $post ) {
		if ( isset( self::$enabled_cache[ $post->ID ] ) ) {
			return self::$enabled_cache[ $post->ID ];
		}

		$enabled = is_post_type_viewable( $post->post_type )
			&& ! self::page_builder( $post )
			&& self::has_enough_content( $post );

		/**
		 * Filter whether a post can be served as Markdown.
		 *
		 * @param bool    $enabled Default result of the built-in checks.
		 * @param WP_Post $post    The post.
		 */
		$enabled = (bool) apply_filters( 'simple_markdown_llms_enabled', $enabled, $post );

		self::$enabled_cache[ $post->ID ] = $enabled;
		return $enabled;
	}

	/**
	 * Detect a page builder that renders the post from its own data, in which
	 * case post_content is empty or stale.
	 *
	 * @param WP_Post $post Post.
	 * @return string|false Builder name, or false.
	 */
	public static function page_builder( WP_Post $post ) {
		$builder = false;

		if ( 'bricks' === get_post_meta( $post->ID, '_bricks_editor_mode', true ) ) {
			$builder = 'bricks';
		} elseif ( 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
			$builder = 'elementor';
		}

		/**
		 * Filter page builder detection. Return a builder name to skip the post,
		 * or false to treat post_content as the real content.
		 *
		 * @param string|false $builder Detected builder.
		 * @param WP_Post      $post    The post.
		 */
		return apply_filters( 'simple_markdown_llms_page_builder', $builder, $post );
	}

	/**
	 * Whether post_content holds at least the minimum amount of real text,
	 * not counting markup, block comments or shortcodes.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	public static function has_enough_content( WP_Post $post ) {
		/**
		 * Filter the minimum number of text characters post_content must have.
		 *
		 * @param int     $min  Default 200.
		 * @param WP_Post $post The post.
		 */
		$min     = max( 1, (int) apply_filters( 'simple_markdown_llms_min_length', 200, $post ) );
		$content = (string) $post->post_content;

		// Fast exit: fewer bytes than the minimum means fewer characters too.
		if ( strlen( $content ) < $min ) {
			return false;
		}

		// Use the count stored at save time if it matches this revision of the post.
		$stored = (string) get_post_meta( $post->ID, self::META_LENGTH, true );
		if ( '' !== $stored ) {
			list( $length, $modified ) = array_pad( explode( '|', $stored, 2 ), 2, '' );
			if ( $modified === $post->post_modified_gmt ) {
				return (int) $length >= $min;
			}
		}

		// Posts not saved since the plugin was activated: count on the fly.
		return self::text_length( $content ) >= $min;
	}

	/**
	 * Store the text length of a post when it's saved.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function store_text_length( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, self::META_LENGTH, self::text_length( (string) $post->post_content ) . '|' . $post->post_modified_gmt );
	}

	/**
	 * Count visible text characters in a chunk of post_content.
	 *
	 * @param string $content Raw post_content.
	 * @return int
	 */
	public static function text_length( $content ) {
		$text = strip_shortcodes( self::strip_block_comments( $content ) );
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/* ---------------------------------------------------------------------
	 * Markdown output
	 * ------------------------------------------------------------------ */

	/**
	 * Output the Markdown document and exit.
	 *
	 * @param WP_Post $post Post.
	 */
	private static function send( WP_Post $post ) {
		$markdown = self::render( $post );

		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Disposition: inline; filename="' . ( $post->post_name ? $post->post_name : $post->ID ) . '.md"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Link: <' . esc_url_raw( get_permalink( $post ) ) . '>; rel="canonical"', false );
		if ( self::llms_txt_enabled() ) {
			header( 'Link: <' . esc_url_raw( self::llms_txt_url() ) . '>; rel="describedby"', false );
		}

		if ( 'publish' !== $post->post_status ) {
			nocache_headers();
		}

		echo $markdown; // phpcs:ignore WordPress.Security.EscapeOutput -- Plain-text Markdown response.
		exit;
	}

	private static function send_forbidden() {
		status_header( 403 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
		echo esc_html__( 'This content is password protected.', 'simple-markdown-and-llms-txt' );
		exit;
	}

	/**
	 * Build the full Markdown document: front matter + content.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function render( WP_Post $post ) {
		$markdown = self::to_yaml( self::front_matter( $post ) ) . "\n" . self::content( $post ) . "\n";

		/**
		 * Filter the final Markdown document.
		 *
		 * @param string  $markdown Markdown output.
		 * @param WP_Post $post     The post.
		 */
		return (string) apply_filters( 'simple_markdown_llms_output', $markdown, $post );
	}

	/**
	 * Collect front matter fields.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	public static function front_matter( WP_Post $post ) {
		$author    = get_userdata( (int) $post->post_author );
		$published = get_post_datetime( $post, 'date' );
		$modified  = get_post_datetime( $post, 'modified' );

		$data = [
			'title'          => $post->post_title,
			'id'             => (int) $post->ID,
			'type'           => $post->post_type,
			'slug'           => $post->post_name,
			'url'            => get_permalink( $post ),
			'author'         => $author ? $author->display_name : '',
			'date'           => $published ? $published->format( 'Y-m-d' ) : '',
			'time'           => $published ? $published->format( 'H:i:s' ) : '',
			'timezone'       => wp_timezone_string(),
			'published'      => $published ? $published->format( DATE_ATOM ) : '',
			'modified'       => $modified ? $modified->format( DATE_ATOM ) : '',
			'status'         => 'publish' === $post->post_status ? '' : $post->post_status,
			'language'       => get_bloginfo( 'language' ),
			'excerpt'        => $post->post_excerpt,
			'featured_image' => has_post_thumbnail( $post ) ? (string) get_the_post_thumbnail_url( $post, 'full' ) : '',
		];

		if ( $post->post_parent ) {
			$data['parent'] = (int) $post->post_parent;
		}

		// Public taxonomies: categories, tags, and any custom ones.
		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
			if ( ! $taxonomy->public || 'post_format' === $taxonomy->name ) {
				continue;
			}
			$terms = get_the_terms( $post, $taxonomy->name );
			if ( ! $terms || is_wp_error( $terms ) ) {
				continue;
			}
			$key          = [ 'category' => 'categories', 'post_tag' => 'tags' ][ $taxonomy->name ] ?? $taxonomy->name;
			$data[ $key ] = wp_list_pluck( $terms, 'name' );
		}

		/**
		 * Filter the front matter fields. Empty values are dropped.
		 *
		 * @param array   $data Field => value. Values may be strings, numbers, booleans or lists.
		 * @param WP_Post $post The post.
		 */
		$data = (array) apply_filters( 'simple_markdown_llms_front_matter', $data, $post );

		return array_filter(
			$data,
			static function ( $value ) {
				return ! ( null === $value || '' === $value || [] === $value );
			}
		);
	}

	/**
	 * Prepare post_content for Markdown output.
	 *
	 * Block editor comment delimiters (<!-- wp:paragraph --> etc.) are removed;
	 * everything else is passed through as written, since Markdown allows HTML.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function content( WP_Post $post ) {
		$content = self::strip_block_comments( (string) $post->post_content );
		$content = str_replace( [ "\r\n", "\r" ], "\n", $content );
		$content = preg_replace( "/\n{3,}/", "\n\n", $content );
		$content = trim( $content );

		/**
		 * Filter the Markdown body.
		 *
		 * @param string  $content Cleaned post_content.
		 * @param WP_Post $post    The post.
		 */
		return (string) apply_filters( 'simple_markdown_llms_content', $content, $post );
	}

	/**
	 * Remove block editor comment delimiters.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function strip_block_comments( $content ) {
		return (string) preg_replace( '#<!--\s+/?wp:.*?-->[ \t]*\R?#s', '', $content );
	}

	/**
	 * Encode a flat array (with optional lists) as a YAML front matter block.
	 *
	 * @param array $data Fields.
	 * @return string
	 */
	public static function to_yaml( array $data ) {
		$out = "---\n";
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$out .= $key . ":\n";
				foreach ( $value as $item ) {
					$out .= '  - ' . self::yaml_scalar( $item ) . "\n";
				}
			} else {
				$out .= $key . ': ' . self::yaml_scalar( $value ) . "\n";
			}
		}
		return $out . "---\n";
	}

	/**
	 * Encode one value as a YAML scalar. Strings are always double-quoted.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function yaml_scalar( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $value );
		$value = str_replace(
			[ '\\', '"', "\r\n", "\r", "\n", "\t" ],
			[ '\\\\', '\\"', '\n', '\n', '\n', '\t' ],
			$value
		);
		return '"' . $value . '"';
	}

	/* ---------------------------------------------------------------------
	 * Discovery
	 * ------------------------------------------------------------------ */

	/**
	 * Advertise the Markdown version and llms.txt in the page <head>.
	 */
	public static function head_links() {
		$post = is_singular() ? get_queried_object() : null;

		if ( $post instanceof WP_Post && self::is_enabled_for( $post ) && ! post_password_required( $post ) ) {
			printf(
				'<link rel="alternate" type="text/markdown" title="%s" href="%s" />' . "\n",
				esc_attr__( 'Markdown', 'simple-markdown-and-llms-txt' ),
				esc_url( simple_markdown_llms_get_url( $post ) )
			);
		}

		if ( self::llms_txt_enabled() ) {
			printf( '<link rel="describedby" type="text/markdown" href="%s" />' . "\n", esc_url( self::llms_txt_url() ) );
		}
	}

	private static function negotiation_enabled() {
		/**
		 * Filter whether to serve Markdown on the normal URL to clients that
		 * send "Accept: text/markdown".
		 *
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'simple_markdown_llms_negotiation', true );
	}

	/* ---------------------------------------------------------------------
	 * /llms.txt
	 * ------------------------------------------------------------------ */

	/**
	 * Whether this plugin should serve /llms.txt.
	 *
	 * Off with plain permalinks (the server won't route /llms.txt to WordPress)
	 * and when a real llms.txt file already exists.
	 *
	 * @return bool
	 */
	public static function llms_txt_enabled() {
		static $enabled = null;
		if ( null === $enabled ) {
			$enabled = '' !== (string) get_option( 'permalink_structure' )
				&& ! file_exists( ABSPATH . 'llms.txt' );

			/**
			 * Filter whether this plugin serves /llms.txt.
			 *
			 * @param bool $enabled Default true when pretty permalinks are on and no llms.txt file exists.
			 */
			$enabled = (bool) apply_filters( 'simple_markdown_llms_txt_enabled', $enabled );
		}
		return $enabled;
	}

	public static function llms_txt_url() {
		return home_url( '/llms.txt' );
	}

	private static function serve_llms_txt() {
		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=' . get_option( 'blog_charset' ) );
		header( 'X-Content-Type-Options: nosniff' );
		echo self::llms_txt(); // phpcs:ignore WordPress.Security.EscapeOutput -- Plain-text Markdown response.
		exit;
	}

	/**
	 * Build the llms.txt document (cached for 12 hours, cleared on post changes).
	 *
	 * @return string
	 */
	public static function llms_txt() {
		$cached = get_transient( self::LLMS_TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['body'] ) ) {
			return $cached['body'];
		}

		$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$desc = wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES );

		$out = '# ' . $name . "\n\n";
		if ( '' !== trim( $desc ) ) {
			$out .= '> ' . trim( $desc ) . "\n\n";
		}

		/**
		 * Filter the maximum number of entries per post type in llms.txt.
		 *
		 * @param int $limit Default 100.
		 */
		$limit = max( 1, (int) apply_filters( 'simple_markdown_llms_txt_limit', 100 ) );

		$types = get_post_types( [ 'public' => true ], 'objects' );
		unset( $types['attachment'] );

		foreach ( $types as $type ) {
			$hierarchical = is_post_type_hierarchical( $type->name );
			$posts        = get_posts(
				[
					'post_type'        => $type->name,
					'post_status'      => 'publish',
					'has_password'     => false,
					'numberposts'      => $limit,
					'orderby'          => $hierarchical ? 'menu_order title' : 'date',
					'order'            => $hierarchical ? 'ASC' : 'DESC',
					'suppress_filters' => false,
				]
			);

			$lines = [];
			foreach ( $posts as $post ) {
				if ( ! self::is_enabled_for( $post ) ) {
					continue;
				}
				$title = str_replace( [ '[', ']' ], [ '\[', '\]' ], wp_specialchars_decode( $post->post_title, ENT_QUOTES ) );
				$line  = '- [' . ( '' !== $title ? $title : '#' . $post->ID ) . '](' . simple_markdown_llms_get_url( $post ) . ')';
				$note  = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $post->post_excerpt ) ) );
				if ( '' !== $note ) {
					$line .= ': ' . $note;
				}
				$lines[] = $line;
			}

			if ( $lines ) {
				$out .= '## ' . $type->labels->name . "\n\n" . implode( "\n", $lines ) . "\n\n";
			}
		}

		/**
		 * Filter the llms.txt document.
		 *
		 * @param string $out Markdown.
		 */
		$out = rtrim( (string) apply_filters( 'simple_markdown_llms_txt_output', $out ) ) . "\n";

		set_transient(
			self::LLMS_TRANSIENT,
			[
				'body'  => $out,
				'time'  => time(),
				'count' => (int) preg_match_all( '/^- \[/m', $out ),
			],
			12 * HOUR_IN_SECONDS
		);
		return $out;
	}

	public static function flush_llms_txt() {
		delete_transient( self::LLMS_TRANSIENT );
	}

	/**
	 * Clear the llms.txt cache when a post is created, updated, trashed,
	 * restored or deleted by a person using a wp-admin screen.
	 *
	 * Only the post that the screen itself submitted counts. Posts created or
	 * changed by other code during the same request (plugins, theme code,
	 * hooks), and anything done through WP-CLI, cron, XML-RPC, the REST API
	 * with an application password, or front-end requests, never trigger it.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function maybe_flush_llms_txt( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$ids = self::admin_screen_post_ids();
		if ( in_array( '*', $ids, true ) || in_array( (int) $post_id, $ids, true ) ) {
			self::flush_llms_txt();
		}
	}

	/**
	 * IDs of the posts the current wp-admin request was submitted for.
	 *
	 * - post.php / post-new.php: classic editor save, trash, restore, delete
	 *   (and the block editor's meta box save).
	 * - edit.php: bulk edit, bulk trash/restore/delete; "Empty Trash" gives "*".
	 * - admin-ajax.php "inline-save": Quick Edit.
	 *
	 * Block editor saves go through the REST API and are handled in
	 * register_rest_hooks().
	 *
	 * @return array<int|string>
	 */
	private static function admin_screen_post_ids() {
		global $pagenow;

		if ( ! is_admin() || ! is_user_logged_in() ) {
			return [];
		}

		// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		if ( wp_doing_ajax() ) {
			if ( isset( $_POST['action'] ) && 'inline-save' === $_POST['action'] && isset( $_POST['post_ID'] ) ) {
				return [ (int) $_POST['post_ID'] ];
			}
			return [];
		}

		if ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) {
			if ( isset( $_POST['post_ID'] ) ) {
				return [ (int) $_POST['post_ID'] ];
			}
			if ( isset( $_GET['post'] ) && ! is_array( $_GET['post'] ) ) {
				return [ (int) $_GET['post'] ];
			}
			return [];
		}

		if ( 'edit.php' === $pagenow ) {
			if ( isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) ) {
				return [ '*' ];
			}
			if ( isset( $_REQUEST['post'] ) ) {
				return array_map( 'intval', (array) $_REQUEST['post'] );
			}
		}
		// phpcs:enable

		return [];
	}

	/**
	 * Block editor saves: the REST posts controller fires these hooks for the
	 * post in the request URL. Count them only when the request comes from a
	 * browser session in wp-admin (valid wp_rest nonce, no application password).
	 */
	public static function register_rest_hooks() {
		foreach ( get_post_types( [ 'show_in_rest' => true ] ) as $type ) {
			add_action( "rest_after_insert_{$type}", [ __CLASS__, 'rest_flush' ] );
			add_action( "rest_delete_{$type}", [ __CLASS__, 'rest_flush' ] );
		}
	}

	public static function rest_flush() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		if ( function_exists( 'rest_get_authenticated_app_password' ) && rest_get_authenticated_app_password() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification
		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? $_SERVER['HTTP_X_WP_NONCE'] : ( isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : '' );
		if ( ! is_string( $nonce ) || '' === $nonce || false === wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return;
		}
		$referer = wp_get_raw_referer();
		if ( ! $referer || 0 !== strpos( $referer, admin_url() ) ) {
			return;
		}
		self::flush_llms_txt();
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	private static function home_path() {
		return strtolower( rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' ) );
	}
}
