<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | End-to-end wiring checks for the apcupsd security fixes.                |
 |                                                                         |
 | Run: php tests/e2e/test_security_wiring.php                             |
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

$poller_source = file_get_contents(__DIR__ . '/../../poller_apcupsd.php');
$ui_source     = file_get_contents(__DIR__ . '/../../upses.php');

assert_true(
	'poller uses the shared apcaccess command builder',
	strpos($poller_source, "apcupsd_build_apcaccess_command(") !== false
);

assert_true(
	'poller rejects invalid apcupsd host or port before exec',
	strpos($poller_source, "Invalid apcupsd hostname or port configuration") !== false
);

assert_true(
	'ajax host filters use integer-normalized site clauses',
	substr_count($ui_source, "apcupsd_get_site_sql_where(get_request_var('site_id'))") === 2
);

assert_true(
	'UI enforces numeric validation for APCUPSD and SNMP ports',
	strpos($ui_source, "form_input_validate(get_nfilter_request_var('port'), 'port', '^[0-9]+$', true, 3)") !== false &&
	strpos($ui_source, "form_input_validate(get_nfilter_request_var('snmp_port'), 'snmp_port', '^[0-9]+$', true, 3)") !== false
);

assert_true(
	'autocomplete limit is normalized before entering SQL',
	strpos($ui_source, "apcupsd_get_autocomplete_rows_limit(read_config_option('autocomplete_rows'))") !== false
);

echo "\n";
echo "Results: $pass passed, $fail failed\n";

exit($fail > 0 ? 1 : 0);
