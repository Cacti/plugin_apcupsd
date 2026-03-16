<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | Regression checks for prepared DB helper migration in apcupsd plugin    |
 |                                                                         |
 | Run: php tests/test_prepared_statements.php                             |
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

$setup_file = __DIR__ . '/../setup.php';
$upses_file = __DIR__ . '/../upses.php';

$setup_contents = file_get_contents($setup_file);
$upses_contents = file_get_contents($upses_file);

assert_true('setup.php is readable', $setup_contents !== false);
assert_true('upses.php is readable', $upses_contents !== false);

$setup_contents = ($setup_contents === false ? '' : $setup_contents);
$upses_contents = ($upses_contents === false ? '' : $upses_contents);

assert_true(
	'setup.php uses prepared plugin_config version lookup',
	preg_match('/db_fetch_cell_prepared\s*\(\s*[\'"]SELECT\s+version/s', $setup_contents) === 1
);
assert_true(
	'setup.php has no raw db_fetch_cell calls',
	preg_match('/\bdb_fetch_cell\s*\(/', $setup_contents) === 0
);
assert_true(
	'setup.php uses prepared replicate_out reads',
	preg_match_all('/\bdb_fetch_assoc_prepared\s*\(/', $setup_contents, $setup_prepared_matches) >= 2
);

assert_true(
	'upses.php has no raw db_fetch_assoc calls',
	preg_match('/\bdb_fetch_assoc\s*\(/', $upses_contents) === 0
);
assert_true(
	'upses.php uses prepared action updates/deletes',
	preg_match_all('/\bdb_execute_prepared\s*\(/', $upses_contents, $upses_prepared_matches) >= 2
);
assert_true(
	'upses.php no longer builds SQL with array_to_sql_or',
	strpos($upses_contents, 'array_to_sql_or(') === false
);

echo "\n";
echo "Results: $pass passed, $fail failed\n";

exit($fail > 0 ? 1 : 0);
