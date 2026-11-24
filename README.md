# khrlzhfr Simple Markdown and llms.txt

By [Khairil Zhafri](https://khrlzh.fr). Version 2.1.0. Requires WordPress 5.7+ and PHP 7.4+. Folder and text domain: `khrlzhfr-simple-markdown-and-llms-txt`; main file `simple-markdown-and-llms-txt.php`. Formerly **Dot MD**.

Serve any post or page as Markdown with YAML front matter, and publish `/llms.txt`. There is nothing to set up.

- **`.md` URLs:** `/hello-world/` → `/hello-world.md`. With plain permalinks, for drafts and for the static front page, use `?format=md`.
- **Content negotiation:** the normal URL returns Markdown to clients that send `Accept: text/markdown`, and HTML to browsers.
- **Discovery:** pages link to their Markdown version in `<head>` and in a `Link:` header, and point to `/llms.txt`.
- **llms.txt:** lists Markdown pages by post type. Cached, rebuilt when posts change in the dashboard, with a **Refresh llms.txt** button under Settings → Reading.
- **Skipped posts:** pages built with Bricks or Elementor, and posts with less than 200 characters of text, have no Markdown version.

Filters, template functions and output examples are in [readme.txt](readme.txt).

## Installing

**From GitHub (gets updates straight away):** download `khrlzhfr-simple-markdown-and-llms-txt.zip` from the [latest release](https://github.com/khrlzhfr/simple-markdown-and-llms-txt/releases/latest), then upload it under Plugins → Add New → Upload Plugin. New versions appear under Dashboard → Updates as soon as they are released on GitHub; select **Enable auto-updates** next to the plugin to install them automatically.

**From WordPress.org:** once approved in the Plugin Directory, install **Simple Markdown and llms.txt** from Plugins → Add New. Updates arrive through WordPress.org.

Both are the same plugin. The GitHub copy also contains an updater (`includes/github-updater.php` and `lib/plugin-update-checker`, [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker), MIT). WordPress.org doesn't allow updaters, so its copy leaves out everything listed in `.distignore`.

**Moving from Dot MD:** deactivate and delete Dot MD, then install this plugin. Nothing is lost, because Dot MD only stored a cache. If your own code used `dotmd_` filters or functions, rename them to `simple_markdown_llms_` (`dotmd_llms_txt` becomes `simple_markdown_llms_txt_enabled`).

### Releasing a new version

1. Bump `Version:` and `SIMPLE_MARKDOWN_LLMS_VERSION` in `simple-markdown-and-llms-txt.php`, `Stable tag:` in `readme.txt`, and add a changelog entry to both readmes.
2. Commit, then publish a release with a matching tag (for example `v2.0.1`) on GitHub's Releases page, or push the tag.
3. The Release workflow checks the version and attaches `khrlzhfr-simple-markdown-and-llms-txt.zip`. Sites installed from GitHub see the update straight away.
4. Once the plugin is approved on WordPress.org, copy the same version to its SVN repository, leaving out the files in `.distignore`.

## Server setup

`.md` and `/llms.txt` must reach WordPress. Standard Apache `.htaccess` and nginx `try_files $uri $uri/ /index.php?$args;` work. If an nginx `location` block for static files lists `md` or `txt`, remove them from it.

Deleting the plugin removes its stored text lengths and cached llms.txt from every site.

## Changelog

**2.1.0** — Renamed to khrlzhfr Simple Markdown and llms.txt. The folder and text domain are now `khrlzhfr-simple-markdown-and-llms-txt`. To switch a site, install this version, then delete the old copy: settings and data are kept; If two copies are installed, only one runs and the Plugins screen says which. Deleting a copy no longer removes data another installed copy still uses.

**2.0.0** — Renamed from Dot MD; filters and functions use the `simple_markdown_llms_` prefix; GitHub updates; multisite uninstall. Earlier versions are listed in readme.txt.

## Licence

GPL-2.0-or-later.
