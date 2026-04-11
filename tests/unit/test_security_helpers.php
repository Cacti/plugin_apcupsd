<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | Unit checks for apcupsd security helpers.                               |
 |                                                                         |
 | Run: php tests/unit/test_security_helpers.php                           |
 +-------------------------------------------------------------------------+
 */

$pass = 0;
$fail = 0;

function assert_true($label, $value) {
	global $pass, $fail;

	if ($value) {
		echo "PASS  $label\n";
		$pass++;
	} else {
		echo "FAIL  $label\n";
		$fail++;
	}
}

function cacti_escapeshellcmd($value) {
	return '[cmd]' . $value . '[/cmd]';
}

function cacti_escapeshellarg($value) {
	return '[arg]' . $value . '[/arg]';
}

require_once __DIR__ . '/../../apcupsd_functions.php';

assert_true(
	'positive int normalization keeps valid integers',
	apcupsd_normalize_positive_int('3551', 0) === 3551
);

assert_true(
	'positive int normalization falls back on invalid data',
	apcupsd_normalize_positive_int('0 OR 1=1', 7) === 7
);

assert_true(
	'site filter rejects mixed numeric payloads',
	apcupsd_get_site_sql_where('9 OR 1=1') === ''
);

assert_true(
	'autocomplete rows limit never returns zero',
	apcupsd_get_autocomplete_rows_limit('0') === 1
);

assert_true(
	'apcaccess command escapes the binary and the host target',
	apcupsd_build_apcaccess_command('/usr/bin/apcaccess', 'ups.example.com', '3551') === '[cmd]/usr/bin/apcaccess[/cmd] -u -h [arg]ups.example.com:3551[/arg]'
);

assert_true(
	'apcaccess command rejects invalid ports',
	apcupsd_build_apcaccess_command('/usr/bin/apcaccess', 'ups.example.com', '3551;id') === false
);

echo "\n";
echo "Results: $pass passed, $fail failed\n";

exit($fail > 0 ? 1 : 0);
