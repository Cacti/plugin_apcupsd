<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the plugin lifecycle contract functions in setup.php:
 * plugin_apcupsd_check_config(), plugin_apcupsd_upgrade(),
 * plugin_apcupsd_uninstall(), and apcupsd_check_upgrade().
 *
 * apcupsd_check_upgrade() include_once()s Cacti core's database.php/
 * functions.php via $config['library_path'], so that is pointed at
 * throwaway empty stub files for the duration of these tests.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
	// Define apcupsd_upgrade_tables() etc. from the real checkout so
	// apcupsd_check_upgrade() can run while base_path is sandboxed for the prune.
	require_once dirname(__DIR__, 2) . '/includes/database.php';

	$stubLibraryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apcupsd-test-lib-stub';

	if (!is_dir($stubLibraryPath)) {
		mkdir($stubLibraryPath, 0777, true);
	}

	file_put_contents($stubLibraryPath . '/database.php', "<?php\n");
	file_put_contents($stubLibraryPath . '/functions.php', "<?php\n");

	$GLOBALS['config']['library_path'] = $stubLibraryPath;
});

beforeEach(function () {
	apcupsd_test_reset_db_mocks();
	$GLOBALS['__test_db_calls']           = array();
	$GLOBALS['__test_enabled_hooks_calls'] = array();
	$_SERVER['PHP_SELF']                  = '/upses.php';

	// Sandbox base_path (minimal INFO + empty includes/database.php stub) so any
	// upgrade-path test runs apcupsd_prune_files() against a throwaway
	// tree, never the real checkout.
	$GLOBALS['__apcupsd_base_restore'] = $GLOBALS['config']['base_path'];
	$base = sys_get_temp_dir() . '/apcupsd-test-' . uniqid();
	mkdir($base . '/plugins/apcupsd/includes', 0777, true);
	file_put_contents($base . '/plugins/apcupsd/INFO', "[info]\nversion = 9.9.9\nname = apcupsd\nlongname = APC UPS Daemon\nauthor = x\nhomepage = x\n");
	file_put_contents($base . '/plugins/apcupsd/includes/database.php', "<?php\n");
	$GLOBALS['config']['base_path'] = $base;
});

afterEach(function () {
	if (isset($GLOBALS['__apcupsd_base_restore'])) {
		$GLOBALS['config']['base_path'] = $GLOBALS['__apcupsd_base_restore'];
	}
});

it('reports the config as always valid', function () {
	expect(plugin_apcupsd_check_config())->toBeTrue();
});

it('reports that no upgrade is pending', function () {
	expect(plugin_apcupsd_upgrade())->toBeTrue();
});

it('drops both plugin tables on uninstall', function () {
	plugin_apcupsd_uninstall();

	$drops = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'DROP TABLE') !== false;
	});

	expect($drops)->toHaveCount(2);
});

it('skips the version check on pages that do not need it', function () {
	$_SERVER['PHP_SELF'] = '/graphs.php';

	apcupsd_check_upgrade();

	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
	expect($GLOBALS['__test_enabled_hooks_calls'])->toBeEmpty();
});

it('re-enables hooks and updates plugin_config when the version drifts', function () {
	apcupsd_test_mock_db('db_fetch_cell_prepared', 'plugin_config', '0.0.0');

	apcupsd_check_upgrade();

	expect($GLOBALS['__test_enabled_hooks_calls'])->toBe(array('apcupsd'));

	$updates = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_config') !== false;
	}));

	expect($updates)->toHaveCount(1);
});

it('does nothing when the stored version already matches the plugin version', function () {
	$info = plugin_apcupsd_version();

	apcupsd_test_mock_db('db_fetch_cell_prepared', 'plugin_config', $info['version']);

	apcupsd_check_upgrade();

	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
	expect($GLOBALS['__test_enabled_hooks_calls'])->toBeEmpty();
});
