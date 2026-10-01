<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Return the CSP nonce attribute for inline <script> tags, safely across
 * Cacti versions. Newer Cacti releases enforce a Content-Security-Policy that
 * requires a per-request nonce on parser-inserted scripts; older releases lack
 * the CactiSecureHeaders class, so this returns an empty string there.
 *
 * @return string The nonce attribute when supported, otherwise empty string.
 */
function plugin_apcupsd_csp_nonce(): string {
	if (class_exists('CactiSecureHeaders')) {
		return CactiSecureHeaders::getNonceAttribute();
	}

	return '';
}

/**
 * Installs the APCUPSD plugin: registers its Cacti hooks (config_arrays,
 * config_settings, poller_bottom, draw_navigation_text, replicate_out),
 * registers its upses.php realm, and creates its database tables. Invoked
 * by Cacti's plugin architecture when an administrator installs this
 * plugin from Console > Plugin Management.
 *
 * @return void
 */
function plugin_apcupsd_install(): void {
	global $config;

	require_once($config['base_path'] . '/plugins/apcupsd/includes/database.php');

	api_plugin_register_hook('apcupsd', 'config_arrays',        'apcupsd_config_arrays',        'setup.php');
	api_plugin_register_hook('apcupsd', 'config_settings',      'apcupsd_config_settings',      'setup.php');
	api_plugin_register_hook('apcupsd', 'poller_bottom',        'apcupsd_poller_bottom',        'setup.php');
	api_plugin_register_hook('apcupsd', 'draw_navigation_text', 'apcupsd_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('apcupsd', 'replicate_out',        'apcupsd_replicate_out',        'setup.php');

	api_plugin_register_realm('apcupsd', 'upses.php', __('Manage UPS\'s', 'apcupsd'), 1);

	apcupsd_setup_table();
}

/**
 * Uninstalls the APCUPSD plugin, dropping its apcupsd_ups and
 * apcupsd_ups_stats tables. Invoked by Cacti's plugin architecture when
 * an administrator uninstalls this plugin from Console > Plugin
 * Management.
 *
 * @return bool Always returns true.
 */
function plugin_apcupsd_uninstall(): bool {
	global $config;

	require_once($config['base_path'] . '/plugins/apcupsd/includes/database.php');

	apcupsd_drop_tables();

	return true;
}

/**
 * Verifies the plugin's configuration; currently a no-op placeholder.
 * Invoked by Cacti's plugin architecture on relevant page loads.
 *
 * @return bool Always returns true.
 */
function plugin_apcupsd_check_config(): bool {
	return true;
}

/**
 * Performs any schema/data migrations needed when upgrading to a newer
 * version of this plugin; currently a no-op placeholder. Invoked by
 * Cacti's plugin architecture when an installed plugin's version
 * increases.
 *
 * @return bool Always returns true.
 */
function plugin_apcupsd_upgrade(): bool {
	return true;
}

/**
 * Detects whether the installed plugin_config version differs from this
 * plugin's INFO file version and, if so, re-enables its hooks (to pick
 * up any newly added ones), updates the stored plugin_config record, and
 * applies a handful of one-off apcupsd_ups_stats column migrations
 * (renaming/adding columns from older releases). Only runs on
 * plugins.php/upses.php. Called from apcupsd_config_arrays() on every
 * relevant page load.
 *
 * @return void
 *
 * @global array  $config           Cacti global configuration array; used
 *                                   to load database.php/functions.php.
 * @global object $database_default Cacti's default database connection
 *                                   handle (unused directly here;
 *                                   declared for parity with other
 *                                   database-touching functions in this
 *                                   file).
 */
function apcupsd_check_upgrade(): void {
	global $config, $database_default;

	$files = ['plugins.php', 'upses.php'];

	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files, true)) {
		return;
	}

	require_once($config['library_path'] . '/database.php');
	require_once($config['library_path'] . '/functions.php');
	require_once($config['base_path'] . '/plugins/apcupsd/includes/database.php');

	$info    = plugin_apcupsd_version();
	$current = $info['version'];
	$old     = db_fetch_cell_prepared('SELECT version
		FROM plugin_config
		WHERE directory = ?',
		['apcupsd']);

	if ($current != $old) {
		if (api_plugin_is_enabled('apcupsd')) {
			// may sound ridiculous, but enables new hooks
			api_plugin_enable_hooks('apcupsd');
		}

		db_execute_prepared('UPDATE plugin_config SET
			version = ?, name = ?, author = ?, webpage = ?
			WHERE directory = ?',
			[
				$info['version'],
				$info['longname'],
				$info['author'],
				$info['homepage'],
				$info['name']
			]
		);

		apcupsd_upgrade_tables();

		// Installations that ran the old install routine (which registered
		// 'replicate_out' twice) are stuck with a stale duplicate hook row
		// that re-enabling hooks alone does not remove.
		$hook_count = (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_hooks WHERE name = ? AND hook = ?', ['apcupsd', 'replicate_out']);

		if ($hook_count > 1) {
			$keep_id = db_fetch_cell_prepared('SELECT MIN(id) FROM plugin_hooks WHERE name = ? AND hook = ?', ['apcupsd', 'replicate_out']);
			db_execute_prepared('DELETE FROM plugin_hooks WHERE name = ? AND hook = ? AND id != ?', ['apcupsd', 'replicate_out', $keep_id]);
		}

		// Remove files tombstoned in manifest.json plus the dev-only tests/ tree.
		plugin_apcupsd_prune_files();
	}
}

/**
 * Hook implementation for Cacti's 'poller_bottom' filter. Launches
 * poller_apcupsd.php as a background process to poll all configured
 * UPS devices. Called by Cacti's poller via
 * api_plugin_hook('poller_bottom', ...) at the end of each polling cycle.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to locate
 *                        the PHP binary and this plugin's poller script.
 */
function apcupsd_poller_bottom(): void {
	global $config;

	include_once($config['base_path'] . '/lib/poller.php');

	exec_background(read_config_option('path_php_binary'), ' -q ' . $config['base_path'] . '/plugins/apcupsd/poller_apcupsd.php');
}

/**
 * Reads this plugin's INFO file and returns its [info] section. Used by
 * Cacti's plugin architecture via the api_plugin_version hook, and
 * internally by apcupsd_check_upgrade() and poller_apcupsd.php's
 * display_version() to detect/report the plugin's version.
 *
 * @return array The parsed [info] section of the plugin's INFO file (keys
 *               such as name, version, author, homepage, longname).
 *
 * @global array $config Cacti global configuration array; used to locate
 *                        the plugin's base path.
 */
function plugin_apcupsd_version(): array {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/apcupsd/INFO', true);
	$info = is_array($info) ? $info : [];

	return isset($info['info']) && is_array($info['info']) ? $info['info'] : [];
}

/**
 * Determines whether the current request represents a "valid" auditable
 * event for this plugin's logging purposes (e.g. a POST submission or a
 * plugins.php mode change), excluding known noisy/irrelevant pages such
 * as graph_view.php and the login/password pages. Used by Cacti core's
 * auditing hook to decide whether to record an event for the current
 * page load, when the 'apcupsd_enabled' setting is on.
 *
 * @return bool True when the current request should be logged as a
 *              valid event.
 *
 * @global string $action Set to the detected action ('purge', or the
 *                         plugins.php 'mode' value) for the caller to
 *                         include in its log entry.
 */
function apcupsd_log_valid_event(): bool {
	global $action;

	$valid = false;

	if (read_config_option('apcupsd_enabled') == 'on') {
		if (strpos($_SERVER['SCRIPT_NAME'], 'graph_view.php') !== false) {
			$valid = false;
		} elseif (strpos($_SERVER['SCRIPT_NAME'], 'user_admin.php') !== false &&
			isset_request_var('action') && get_nfilter_request_var('action') == 'checkpass') {
			$valid = false;
		} elseif (strpos($_SERVER['SCRIPT_NAME'], 'plugins.php') !== false) {
			if (isset_request_var('mode')) {
				$valid  = true;
				$action = get_nfilter_request_var('mode');
			}
		} elseif (strpos($_SERVER['SCRIPT_NAME'], 'auth_profile.php') !== false) {
			$valid = false;
		} elseif (strpos($_SERVER['SCRIPT_NAME'], 'index.php') !== false) {
			$valid = false;
		} elseif (strpos($_SERVER['SCRIPT_NAME'], 'auth_changepassword.php') !== false) {
			$valid = false;
		} elseif (cacti_sizeof($_POST)) {
			$valid = true;
		} elseif (isset_request_var('purge_continue')) {
			$valid  = true;
			$action = 'purge';
		}
	}

	return $valid;
}

/**
 * Hook implementation for Cacti's 'config_arrays' filter. Adds the
 * "UPSes" entry under the Management section of Cacti's menu, augments
 * the System Administration role with this plugin's page where
 * supported, and triggers this plugin's upgrade check. Called by Cacti
 * core via api_plugin_hook('config_arrays', ...) while building the
 * navigation menu.
 *
 * @return void
 *
 * @global array $menu Cacti's main navigation menu array, extended here
 *                      with this plugin's entry.
 */
function apcupsd_config_arrays(): void {
	global $menu;

	$menu[__('Management')]['plugins/apcupsd/upses.php'] = __('UPSes', 'apcupsd');

	if (function_exists('auth_augment_roles')) {
		auth_augment_roles(__('System Administration'), ['upses.php']);
	}

	apcupsd_check_upgrade();
}

/**
 * Hook implementation for Cacti's 'config_settings' filter. Intended to
 * register this plugin's Settings page tab/fields; currently a no-op
 * (the function body is empty). Called by Cacti core via
 * api_plugin_hook('config_settings', ...) while building the Settings
 * page.
 *
 * @return void
 *
 * @global array $tabs                Cacti's registered Settings page
 *                                     tabs (unused directly here).
 * @global array $settings            Cacti's registered Settings page
 *                                     fields (unused directly here).
 * @global array $item_rows           Rows-per-page options offered by
 *                                     Cacti core (unused directly here).
 * @global array $apcupsd_retentions  Reserved for this plugin's data
 *                                     retention options (unused directly
 *                                     here).
 */
function apcupsd_config_settings(): void {
	global $tabs, $settings, $item_rows, $apcupsd_retentions;
}

/**
 * Hook implementation for Cacti's 'replicate_out' filter. Replicates this
 * plugin's apcupsd_ups and apcupsd_ups_stats table contents out to a
 * remote poller in a distributed Cacti setup. Called by Cacti core via
 * api_plugin_hook('replicate_out', ...) during remote poller data
 * replication.
 *
 * @param array $data The replication context, including 'rcnn_id' (the
 *                    remote connection id) and 'remote_poller_id'.
 *
 * @return array The unmodified $data array (this hook does not modify
 *               its payload).
 *
 * @global array $config Cacti global configuration array; used to load
 *                        lib/poller.php.
 */
function apcupsd_replicate_out($data): array {
	global $config;

	include_once($config['base_path'] . '/lib/poller.php');

	$upsdata = db_fetch_assoc_prepared('SELECT *
		FROM apcupsd_ups',
		[]);

	replicate_out_table($data['rcnn_id'], $upsdata, 'apcupsd_ups', $data['remote_poller_id']);

	$upsdata = db_fetch_assoc_prepared('SELECT *
		FROM apcupsd_ups_stats',
		[]);

	replicate_out_table($data['rcnn_id'], $upsdata, 'apcupsd_ups_stats', $data['remote_poller_id']);

	return $data;
}

/**
 * Hook implementation for Cacti's 'draw_navigation_text' filter. Adds a
 * breadcrumb entry for upses.php's default view. Called by Cacti core
 * via api_plugin_hook('draw_navigation_text', ...) while rendering the
 * page breadcrumb trail.
 *
 * @param array $nav The existing breadcrumb map contributed by Cacti
 *                   core and other plugins.
 *
 * @return array The $nav array with this plugin's breadcrumb entry
 *               added.
 */
function apcupsd_draw_navigation_text($nav): array {
	$nav['upses.php:'] = [
		'title'   => __('Manage UPSes', 'apcupsd'),
		'mapping' => 'index.php:',
		'url'     => 'upses.php',
		'level'   => '1'
	];

	return $nav;
}

/**
 * Removes files and directories that a previous version of this plugin
 * shipped but that have since moved or been deleted, using the tombstone
 * and whitelist lists in manifest.json. Whitelisted (user-data) paths and
 * any VCS metadata (.git*) are never touched; the dev-only tests/ tree is
 * removed. Any path that resolves outside the plugin directory (a tampered
 * manifest.json) is refused, and any file/directory that cannot be removed
 * (e.g. read-only) is reported to the Cacti log. Any top-level entry that is
 * neither expected nor a tombstone nor whitelisted is logged to the Cacti
 * log and left in place. Called on a plugin version change.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to resolve
 *                       the plugin directory.
 */
function plugin_apcupsd_prune_files(): void {
	global $config;

	$plugin_dir    = $config['base_path'] . '/plugins/apcupsd';
	$manifest_path = $plugin_dir . '/manifest.json';

	if (!is_readable($manifest_path)) {
		return;
	}

	$manifest = json_decode((string) file_get_contents($manifest_path), true);

	if (!is_array($manifest)) {
		cacti_log('WARNING: apcupsd manifest.json could not be parsed; skipping file prune', false, 'APCUPSD');

		return;
	}

	$tombstones = isset($manifest['tombstones']) && is_array($manifest['tombstones']) ? $manifest['tombstones'] : [];
	$expected   = isset($manifest['expected'])   && is_array($manifest['expected'])   ? $manifest['expected']   : [];
	$whitelist  = isset($manifest['whitelist'])  && is_array($manifest['whitelist'])  ? $manifest['whitelist']  : [];

	$protected = function (string $rel) use ($whitelist): bool {
		if (strncmp($rel, '.git', 4) === 0 || strncmp($rel, '.md', 3) === 0) {
			return true;
		}

		foreach ($whitelist as $entry) {
			$entry = trim((string) $entry, '/');

			if ($entry !== '' && ($rel === $entry
				|| strncmp($rel, $entry . '/', strlen($entry) + 1) === 0
				|| strncmp($entry, $rel . '/', strlen($rel) + 1) === 0)) {
				return true;
			}
		}

		return false;
	};

	// Security: resolve the plugin directory so a tampered manifest.json
	// cannot steer the prune outside of it.
	$plugin_real = realpath($plugin_dir);

	// Remove tombstoned (moved/deleted) paths plus the dev-only tests/
	// tree and the phpunit.xml test configuration.
	$remove   = $tombstones;
	$remove[] = 'tests/';
	$remove[] = 'phpunit.xml';

	foreach ($remove as $rel) {
		$rel = trim((string) $rel, '/');

		if ($rel === '' || $protected($rel)) {
			continue;
		}

		// A tombstone must never contain '.'/'..' segments; a tampered manifest
		// could use them to escape the plugin directory or target its root.
		$segments = explode('/', $rel);

		if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
			cacti_log(sprintf('WARNING: apcupsd prune refused to remove %s: path contains a traversal segment (tampered manifest.json?)', $rel), false, 'APCUPSD');

			continue;
		}

		$path = $plugin_dir . '/' . $rel;

		if (!is_link($path) && !file_exists($path)) {
			continue;
		}

		// Refuse any path that, after resolving symlinks and ../ segments,
		// escapes the plugin directory (protects user data from a tampered
		// manifest.json).
		$anchor = is_link($path) ? dirname($path) : $path;
		$real   = realpath($anchor);

		if ($real === false || ($real !== $plugin_real && strncmp($real, $plugin_real . DIRECTORY_SEPARATOR, strlen((string) $plugin_real) + 1) !== 0)) {
			cacti_log(sprintf('WARNING: apcupsd prune refused to remove %s: path resolves outside the plugin directory (tampered manifest.json?)', $rel), false, 'APCUPSD');

			continue;
		}

		if (is_dir($path) && !is_link($path)) {
			$removed = plugin_apcupsd_rmtree($path);
		} else {
			$removed = @unlink($path);
		}

		if (!$removed) {
			cacti_log(sprintf('WARNING: apcupsd upgrade could not remove %s (check file/directory permissions)', $rel), false, 'APCUPSD');
		}
	}

	// Surface any top-level entry the manifest does not account for.
	$known = [];

	foreach (array_merge($expected, $tombstones) as $entry) {
		$top = explode('/', trim((string) $entry, '/'))[0];

		if ($top !== '') {
			$known[$top] = true;
		}
	}

	$entries = scandir($plugin_dir);

	foreach (($entries !== false ? $entries : []) as $entry) {
		if ($entry === '.' || $entry === '..' || $entry === 'tests' || $entry === 'phpunit.xml' || $protected($entry) || isset($known[$entry])) {
			continue;
		}

		cacti_log(sprintf('WARNING: apcupsd upgrade found a file/directory not described in manifest.json: %s (left in place)', $entry), false, 'APCUPSD');
	}
}

/**
 * Recursively deletes a directory and its contents. Symlinks are removed
 * without being followed. Helper for plugin_apcupsd_prune_files().
 *
 * @param string $dir Absolute path to the directory to remove.
 *
 * @return bool True if the directory and everything under it was removed;
 *              false if any entry could not be deleted.
 */
function plugin_apcupsd_rmtree(string $dir): bool {
	$entries = scandir($dir);
	$ok      = true;

	foreach (($entries !== false ? $entries : []) as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}

		$path = $dir . '/' . $entry;

		if (is_dir($path) && !is_link($path)) {
			if (!plugin_apcupsd_rmtree($path)) {
				$ok = false;
			}
		} elseif (!@unlink($path)) {
			$ok = false;
		}
	}

	if (!@rmdir($dir)) {
		$ok = false;
	}

	return $ok;
}
