<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for plugin_apcupsd_install(): verifies every hook
 * and the realm the plugin depends on at runtime are actually registered,
 * together with the tables it needs, in a single end-to-end pass.
 *
 * apcupsd_setup_table() include_once()s Cacti core's database.php via
 * $config['library_path'], so that is pointed at a throwaway empty stub
 * file for the duration of this test.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';

	$stubLibraryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apcupsd-test-lib-stub';

	if (!is_dir($stubLibraryPath)) {
		mkdir($stubLibraryPath, 0777, true);
	}

	file_put_contents($stubLibraryPath . '/database.php', "<?php\n");

	$GLOBALS['config']['library_path'] = $stubLibraryPath;
});

beforeEach(function () {
	$GLOBALS['__test_registered_hooks']  = array();
	$GLOBALS['__test_registered_realms'] = array();
	$GLOBALS['__test_db_calls']          = array();
});

it('registers every hook apcupsd depends on, its realm, and provisions its tables', function () {
	plugin_apcupsd_install();

	$hooks = array();
	foreach ($GLOBALS['__test_registered_hooks'] as $registered) {
		$hooks[$registered['hook']] = $registered;
	}

	foreach (array('config_arrays', 'config_settings', 'poller_bottom', 'draw_navigation_text', 'replicate_out') as $expected) {
		expect($hooks)->toHaveKey($expected);
		expect($hooks[$expected]['name'])->toBe('apcupsd');
		expect($hooks[$expected]['file'])->toBe('setup.php');
	}

	expect($GLOBALS['__test_registered_realms'])->toHaveCount(1);
	expect($GLOBALS['__test_registered_realms'][0]['file'])->toBe('upses.php');

	$sql = implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql'));

	expect($sql)->toContain('apcupsd_ups');
	expect($sql)->toContain('apcupsd_ups_stats');
});
