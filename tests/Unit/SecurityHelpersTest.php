<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../includes/functions.php';

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
});
