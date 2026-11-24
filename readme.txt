=== khrlzhfr Simple Markdown and llms.txt ===
Contributors: khrlzhfr
Tags: markdown, front matter, llms.txt, ai agents, content negotiation
Requires at least: 5.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Serve any post or page as Markdown with YAML front matter, by adding .md to its URL or asking for text/markdown. Also publishes /llms.txt.

== Description ==

khrlzhfr Simple Markdown and llms.txt gives every public post, page and custom post type a Markdown version. There is nothing to set up.

**Getting the Markdown**

* `/hello-world/` → `/hello-world.md` (the trailing slash is replaced)
* `/hello-world` → `/hello-world.md` (for permalink structures without a trailing slash, `.md` is added)
* `/?p=123&format=md` or `/hello-world/?format=md` (works with any permalink setting)
* `/hello-world/` requested with `Accept: text/markdown` (content negotiation: same URL, Markdown for agents that ask for it, HTML for browsers)

The static front page, drafts and previews use `format=md`.

**How AI agents find it**

* Every page with a Markdown version gets `<link rel="alternate" type="text/markdown">` in its `<head>` and the same link as an HTTP `Link:` header.
* `/llms.txt` lists your Markdown pages grouped by post type, following the llmstxt.org proposal. Pages point to it with `rel="describedby"`.

**Keeping llms.txt up to date**

llms.txt is cached. It's rebuilt automatically only when a person creates, updates, trashes, restores or deletes a post or page on a wp-admin screen (classic editor, block editor, Quick Edit, bulk actions, Empty Trash). Nothing else triggers a rebuild: not `wp_insert_post()` or `wp_update_post()` called from plugins or theme code (even during an admin request), WP-CLI, cron, XML-RPC, the REST API with an application password, or front-end forms. Call `Simple_Markdown_LLMS::flush_llms_txt()` from your own code if you want it to. They show up when the cache expires after 12 hours, or straight away if you click **Refresh llms.txt** under Settings → Reading. That screen also shows when llms.txt was last built and how many entries it has.

**Which posts get a Markdown version**

A post is skipped when:

* it is built with a page builder that keeps its content outside `post_content` (Bricks pages set to "Render with Bricks", Elementor pages), or
* its `post_content` has fewer than 200 characters of real text, not counting markup, block comments or shortcodes.

Skipped posts return 404 at their `.md` URL and are left out of llms.txt. Their HTML pages carry no Markdown links.

**Output**

    ---
    title: "Hello world!"
    id: 1
    type: "post"
    slug: "hello-world"
    url: "https://example.com/hello-world/"
    author: "Khairil Zhafri"
    date: "2026-11-20"
    time: "17:40:00"
    timezone: "Asia/Kuala_Lumpur"
    published: "2026-11-20T17:40:00+08:00"
    modified: "2026-11-20T18:02:11+08:00"
    language: "en-US"
    categories:
      - "Uncategorized"
    tags:
      - "markdown"
    ---

    (post_content)

Empty fields are left out. `excerpt`, `featured_image`, `parent`, `status` (for anything that isn't published) and custom public taxonomies are added when they have a value.

The body is the raw `post_content`. Block editor comments (`<!-- wp:paragraph -->`) are removed, and any HTML is kept as written, because Markdown allows HTML.

Password-protected posts return 403.

**Filters**

* `simple_markdown_llms_min_length` (int, WP_Post) — minimum text length, default 200
* `simple_markdown_llms_page_builder` (string|false, WP_Post) — change page builder detection; return false to use post_content anyway
* `simple_markdown_llms_enabled` (bool, WP_Post) — final say on whether a post gets a Markdown version
* `simple_markdown_llms_front_matter` (array, WP_Post) — add, remove or change fields
* `simple_markdown_llms_content` (string, WP_Post) — change the body, e.g. to convert HTML to Markdown
* `simple_markdown_llms_output` (string, WP_Post) — change the whole document
* `simple_markdown_llms_negotiation` (bool) — turn `Accept: text/markdown` handling off
* `simple_markdown_llms_txt_enabled` (bool) — turn /llms.txt off
* `simple_markdown_llms_txt_limit` (int) — entries per post type in llms.txt, default 100
* `simple_markdown_llms_txt_output` (string) — change the llms.txt document

Example: lower the minimum to 50 characters.

    add_filter( 'simple_markdown_llms_min_length', fn() => 50 );

**Template functions**

* `simple_markdown_llms_get_url( $post )` returns the Markdown URL for a post.
* `simple_markdown_llms_has_markdown( $post )` says whether a post has one.

== Installation ==

1. Upload the `khrlzhfr-simple-markdown-and-llms-txt` folder to `/wp-content/plugins/`, or upload the zip under Plugins → Add New → Upload Plugin.
2. Activate it. You don't need to flush permalinks.

== Frequently Asked Questions ==

= I was using Dot MD =

This is Dot MD under a new name. WordPress sees it as a different plugin, so deactivate and delete Dot MD, then install this one; running both at once would answer the same .md URLs twice. Nothing is lost: Dot MD only stored a cache, which rebuilds itself. If your theme or another plugin used a `dotmd_` filter or function, rename it as listed in the 2.0.0 changelog.


= The .md URL shows my web server's 404 page, not WordPress's =

Your server is handling `.md` itself instead of passing it to WordPress. Standard Apache `.htaccess` and nginx `try_files $uri $uri/ /index.php?$args;` setups work. If nginx has a `location` block for static file extensions that includes `md` or `txt`, remove them from it.

= Will content negotiation confuse my page cache? =

Markdown responses sent via `Accept: text/markdown` set `DONOTCACHEPAGE` and no-cache headers, so WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache and CDNs won't store them under the HTML URL. HTML pages also send `Vary: Accept`. If you use a reverse proxy that ignores both, turn negotiation off with `add_filter( 'simple_markdown_llms_negotiation', '__return_false' );`. The `.md` URLs still work.

= I already have an llms.txt =

If there's an `llms.txt` file in the WordPress root, the plugin leaves it alone and doesn't serve its own. If another plugin generates one dynamically, turn this plugin's off with `add_filter( 'simple_markdown_llms_txt_enabled', '__return_false' );`.

= Does this slow down my site? =

No. Markdown is only built when someone asks for it. On a normal page view the plugin does a few string checks and reads the post's text length, which it worked out when the post was last saved. llms.txt is built once and cached.

= llms.txt doesn't update straight away =

Changes made outside the dashboard don't rebuild it. Click Refresh llms.txt under Settings → Reading, or wait for the 12-hour cache to expire.

== Changelog ==

= 2.1.0 =
* Renamed to khrlzhfr Simple Markdown and llms.txt. The folder and text domain are now `khrlzhfr-simple-markdown-and-llms-txt`. To switch a site, install this version, then delete the old copy: settings and data are kept.
* If two copies are installed, only one runs and the Plugins screen says which. Deleting a copy no longer removes data another installed copy still uses.

= 2.0.0 =
* Renamed from Dot MD to Simple Markdown and llms.txt. The folder, text domain, filters and functions now use the new name: `dotmd_…` filters are `simple_markdown_llms_…`, `dotmd_llms_txt` is `simple_markdown_llms_txt_enabled`, and `dotmd_get_url()` / `dotmd_has_markdown()` are `simple_markdown_llms_get_url()` / `simple_markdown_llms_has_markdown()`.
* Updates now come from GitHub Releases (or WordPress.org, once approved).
* Uninstalling cleans up every site on a multisite network.

= 1.2.1 =
* Rebuild llms.txt only for the post a wp-admin screen submitted, not for posts created or changed by other code.

= 1.2.0 =
* Add a Refresh llms.txt button and build status under Settings → Reading.
* Rebuild llms.txt only for changes made in the dashboard.
* Add a Settings link on the Plugins screen.

= 1.1.1 =
* Count a post's text when it's saved instead of on every page view.
* Add uninstall cleanup.

= 1.1.0 =
* Serve Markdown to clients that send `Accept: text/markdown`.
* Add `Link` headers for the Markdown version and llms.txt on HTML pages.
* Publish `/llms.txt`.
* Skip posts built with Bricks or Elementor, and posts with less than 200 characters of text.
* Add `dotmd_has_markdown()`.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 2.1.0 =
New name and folder. Install this version first, then delete the old copy; your settings and data are kept.

= 2.0.0 =
Renamed from Dot MD. Delete Dot MD before activating, and rename any dotmd_ filters or functions in your own code.
