<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for apcupsd_log_valid_event() in setup.php - decides
 * whether the current request should be recorded in the audit log.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	test_set_request(array());
	test_set_config_option('apcupsd_enabled', 'on');
	$_SERVER['SCRIPT_NAME'] = '/some_page.php';
	$_POST = array();
});

it('is never valid when the plugin is disabled', function () {
	test_set_config_option('apcupsd_enabled', '');
	$_POST = array('anything' => 'here');

	expect(apcupsd_log_valid_event())->toBeFalse();
});

it('is not valid on graph_view.php', function () {
	$_SERVER['SCRIPT_NAME'] = '/graph_view.php';

	expect(apcupsd_log_valid_event())->toBeFalse();
});

it('is not valid on a checkpass request to user_admin.php', function () {
	$_SERVER['SCRIPT_NAME'] = '/user_admin.php';
	test_set_request(array('action' => 'checkpass'));

	expect(apcupsd_log_valid_event())->toBeFalse();
});

it('is valid on plugins.php when a mode is present, and records the mode as the action', function () {
	global $action;

	$_SERVER['SCRIPT_NAME'] = '/plugins.php';
	test_set_request(array('mode' => 'enable'));

	expect(apcupsd_log_valid_event())->toBeTrue();
	expect($action)->toBe('enable');
});

it('is not valid on auth_profile.php, index.php, or auth_changepassword.php', function () {
	foreach (array('/auth_profile.php', '/index.php', '/auth_changepassword.php') as $script) {
		$_SERVER['SCRIPT_NAME'] = $script;

		expect(apcupsd_log_valid_event())->toBeFalse();
	}
});

it('is valid whenever POST data is present on an otherwise unmatched page', function () {
	$_POST = array('field' => 'value');

	expect(apcupsd_log_valid_event())->toBeTrue();
});

it('is valid for a purge_continue request and records the purge action', function () {
	global $action;

	test_set_request(array('purge_continue' => '1'));

	expect(apcupsd_log_valid_event())->toBeTrue();
	expect($action)->toBe('purge');
});

it('is not valid when nothing matches', function () {
	expect(apcupsd_log_valid_event())->toBeFalse();
});
