<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('prepared DB helper migration in the apcupsd plugin', function () {
	$setupContents = file_get_contents(realpath(__DIR__ . '/../../setup.php'));
	$upsesContents = file_get_contents(realpath(__DIR__ . '/../../upses.php'));

	it('uses a prepared plugin_config version lookup in setup.php', function () use ($setupContents) {
		expect(preg_match('/db_fetch_cell_prepared\s*\(\s*[\'"]SELECT\s+version/s', $setupContents))->toBe(1);
	});

	it('has no raw db_fetch_cell calls in setup.php', function () use ($setupContents) {
		expect(preg_match('/\bdb_fetch_cell\s*\(/', $setupContents))->toBe(0);
	});

	it('uses prepared replicate_out reads in setup.php', function () use ($setupContents) {
		expect(preg_match_all('/\bdb_fetch_assoc_prepared\s*\(/', $setupContents))->toBeGreaterThanOrEqual(2);
	});

	it('has no raw db_fetch_assoc calls in upses.php', function () use ($upsesContents) {
		expect(preg_match('/\bdb_fetch_assoc\s*\(/', $upsesContents))->toBe(0);
	});

	it('uses prepared action updates/deletes in upses.php', function () use ($upsesContents) {
		expect(preg_match_all('/\bdb_execute_prepared\s*\(/', $upsesContents))->toBeGreaterThanOrEqual(2);
	});

	it('no longer builds SQL with array_to_sql_or in upses.php', function () use ($upsesContents) {
		expect($upsesContents)->not->toContain('array_to_sql_or(');
	});

	it('guards bulk actions on non-empty selected items in upses.php', function () use ($upsesContents) {
		expect($upsesContents)->toContain('$selected_items != false && cacti_sizeof($selected_items)');
	});

	it('derives IN-clause placeholders from the selected item count in upses.php', function () use ($upsesContents) {
		expect($upsesContents)->toContain("\$selected_placeholders = implode(',', array_fill(0, cacti_sizeof(\$selected_items), '?'));");
	});

	it('has no remaining raw db helpers in upses.php', function () use ($upsesContents) {
		expect(preg_match('/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)\s*\(/', $upsesContents))->toBe(0);
	});
});
