<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

if (!function_exists('cacti_escapeshellcmd')) {
	function cacti_escapeshellcmd($value) {
		return '[cmd]' . $value . '[/cmd]';
	}
}

if (!function_exists('cacti_escapeshellarg')) {
	function cacti_escapeshellarg($value) {
		return '[arg]' . $value . '[/arg]';
	}
}

require_once __DIR__ . '/../../apcupsd_functions.php';

describe('apcupsd security helpers', function () {
	it('normalizes valid integers', function () {
		expect(apcupsd_normalize_positive_int('3551', 0))->toBe(3551);
	});

	it('falls back to the default on invalid data', function () {
		expect(apcupsd_normalize_positive_int('0 OR 1=1', 7))->toBe(7);
	});

	it('rejects mixed numeric payloads in the site filter', function () {
		expect(apcupsd_get_site_sql_where('9 OR 1=1'))->toBe('');
	});

	it('never returns a zero autocomplete rows limit', function () {
		expect(apcupsd_get_autocomplete_rows_limit('0'))->toBe(1);
	});

	it('escapes the binary and host target in the apcaccess command', function () {
		expect(apcupsd_build_apcaccess_command('/usr/bin/apcaccess', 'ups.example.com', '3551'))
			->toBe('[cmd]/usr/bin/apcaccess[/cmd] -u -h [arg]ups.example.com:3551[/arg]');
	});

	it('rejects invalid ports in the apcaccess command', function () {
		expect(apcupsd_build_apcaccess_command('/usr/bin/apcaccess', 'ups.example.com', '3551;id'))->toBeFalse();
	});
});
