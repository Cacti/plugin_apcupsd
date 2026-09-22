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
