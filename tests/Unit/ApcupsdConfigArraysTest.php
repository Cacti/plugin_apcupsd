<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for apcupsd_config_arrays() in setup.php - wires up the
 * plugin's menu entry (it also calls apcupsd_check_upgrade() internally,
 * so the same library_path stub trick used for that function applies
 * here too).
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';

	$stubLibraryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apcupsd-test-lib-stub';

	if (!is_dir($stubLibraryPath)) {
		mkdir($stubLibraryPath, 0777, true);
	}

	file_put_contents($stubLibraryPath . '/database.php', "<?php\n");
	file_put_contents($stubLibraryPath . '/functions.php', "<?php\n");

	$GLOBALS['config']['library_path'] = $stubLibraryPath;
});

beforeEach(function () {
	$GLOBALS['menu'] = array();
	apcupsd_test_reset_db_mocks();
	$_SERVER['PHP_SELF'] = '/graphs.php';
});

it('registers the UPSes menu entry under Management', function () {
	global $menu;

	apcupsd_config_arrays();

	expect($menu)->toHaveKey('Management');
	expect($menu['Management'])->toBe(array(
		'plugins/apcupsd/upses.php' => 'UPSes',
	));
});
