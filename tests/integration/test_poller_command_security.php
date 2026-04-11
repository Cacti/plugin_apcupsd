<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | Integration checks for the poller command construction path.            |
 |                                                                         |
 | Run: php tests/integration/test_poller_command_security.php             |
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

require_once __DIR__ . '/../../apcupsd_functions.php';

$valid = apcupsd_build_apcaccess_command('/usr/sbin/apcaccess', 'rack-ups.example.com', '3551');
$invalid_host = apcupsd_build_apcaccess_command('/usr/sbin/apcaccess', '', '3551');
$invalid_port = apcupsd_build_apcaccess_command('/usr/sbin/apcaccess', 'rack-ups.example.com', '3551;touch /tmp/pwned');

assert_true(
	'valid poller command stays available',
	$valid !== false && strpos($valid, 'rack-ups.example.com:3551') !== false
);

assert_true(
	'blank host is rejected before exec',
	$invalid_host === false
);

assert_true(
	'non-numeric port is rejected before exec',
	$invalid_port === false
);

echo "\n";
echo "Results: $pass passed, $fail failed\n";

exit($fail > 0 ? 1 : 0);
