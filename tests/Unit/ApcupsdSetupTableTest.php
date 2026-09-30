<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for apcupsd_setup_table() in setup.php.
 *
 * It include_once()s Cacti core's database.php via $config['library_path'],
 * so that is pointed at a throwaway empty stub file for the duration of
 * these tests.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
	require_once __DIR__ . '/../../includes/database.php';

	$stubLibraryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apcupsd-test-lib-stub';

	if (!is_dir($stubLibraryPath)) {
		mkdir($stubLibraryPath, 0777, true);
	}

	file_put_contents($stubLibraryPath . '/database.php', "<?php\n");

	$GLOBALS['config']['library_path'] = $stubLibraryPath;
});

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = array();
});

it('creates the apcupsd_ups and apcupsd_ups_stats tables', function () {
	apcupsd_setup_table();

	$sql = implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql'));

	expect($sql)->toContain('apcupsd_ups');
	expect($sql)->toContain('apcupsd_ups_stats');
});

it('returns true', function () {
	expect(apcupsd_setup_table())->toBeTrue();
});

it('passes a scalar primary key to the create API', function () {
	// api_plugin_db_table_create() interpolates data['primary'] straight into
	// PRIMARY KEY (`...`) down to the 1.2.24 compat floor, so an array would
	// render as PRIMARY KEY (`Array`) and the create would fail.
	apcupsd_setup_table();

	$creates = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'api_plugin_db_table_create';
	}));

	expect($creates)->toHaveCount(2);

	foreach ($creates as $create) {
		expect($create['data']['primary'])->toBeString();
	}
});

it('creates absent tables on upgrade without attempting rename ALTERs', function () {
	$GLOBALS['__test_table_exists'] = array();

	apcupsd_upgrade_tables();

	$alters = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'ALTER TABLE') !== false;
	});

	expect($alters)->toBeEmpty();

	$creates = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'api_plugin_db_table_create';
	}));

	expect($creates)->toHaveCount(2);
});
