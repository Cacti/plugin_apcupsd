<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../apcupsd_functions.php';

describe('poller command construction path', function () {
	it('keeps a valid poller command available', function () {
		$valid = apcupsd_build_apcaccess_command('/usr/sbin/apcaccess', 'rack-ups.example.com', '3551');

		expect($valid)->not->toBeFalse();
		expect($valid)->toContain('rack-ups.example.com:3551');
	});

	it('rejects a blank host before exec', function () {
		expect(apcupsd_build_apcaccess_command('/usr/sbin/apcaccess', '', '3551'))->toBeFalse();
	});

	it('rejects a non-numeric port before exec', function () {
		expect(apcupsd_build_apcaccess_command('/usr/sbin/apcaccess', 'rack-ups.example.com', '3551;touch /tmp/pwned'))->toBeFalse();
	});
});
