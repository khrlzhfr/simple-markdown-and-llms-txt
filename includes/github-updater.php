<?php
/**
 * Updates from GitHub Releases, for copies installed from GitHub.
 *
 * The WordPress.org build leaves this file and lib/ out; those copies update
 * through WordPress.org instead.
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/lib/plugin-update-checker/plugin-update-checker.php';

$simple_markdown_llms_updates = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/khrlzhfr/simple-markdown-and-llms-txt/',
	SIMPLE_MARKDOWN_LLMS_FILE,
	dirname( plugin_basename( SIMPLE_MARKDOWN_LLMS_FILE ) ) // Keep updates in whichever folder this copy was installed in.
);
$simple_markdown_llms_updates->getVcsApi()->enableReleaseAssets( '/simple-markdown-and-llms-txt\.zip$/' );
