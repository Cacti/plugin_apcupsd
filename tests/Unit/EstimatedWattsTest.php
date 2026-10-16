<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../includes/functions.php';

describe('apcupsd_estimate_watts', function () {
	it('multiplies the nominal power by the load percent', function () {
		expect(apcupsd_estimate_watts(1500, 40))->toBe(600.0);
		expect(apcupsd_estimate_watts(2200, 12.5))->toBe(275.0);
	});

	it('accepts numeric strings as polled from the database', function () {
		expect(apcupsd_estimate_watts('1000', '13'))->toBe(130.0);
	});

	it('reports zero watts for an idle UPS', function () {
		expect(apcupsd_estimate_watts(1500, 0))->toBe(0.0);
	});

	it('does not clamp an overloaded UPS', function () {
		expect(apcupsd_estimate_watts(1000, 110))->toBe(1100.0);
	});

	it('returns null when either input is missing or not numeric', function () {
		expect(apcupsd_estimate_watts(null, 40))->toBeNull();
		expect(apcupsd_estimate_watts(1500, null))->toBeNull();
		expect(apcupsd_estimate_watts('', 40))->toBeNull();
		expect(apcupsd_estimate_watts(1500, 'n/a'))->toBeNull();
	});

	it('returns null without a positive nominal power or with a negative load', function () {
		expect(apcupsd_estimate_watts(0, 40))->toBeNull();
		expect(apcupsd_estimate_watts(-1500, 40))->toBeNull();
		expect(apcupsd_estimate_watts(1500, -5))->toBeNull();
	});
});

describe('apcupsd_is_slave_status', function () {
	it('flags slave statuses', function () {
		expect(apcupsd_is_slave_status('ONLINE SLAVE'))->toBeTrue();
		expect(apcupsd_is_slave_status('SLAVEDOWN'))->toBeTrue();
		expect(apcupsd_is_slave_status('online slave'))->toBeTrue();
	});

	it('does not flag master or non-string statuses', function () {
		expect(apcupsd_is_slave_status('ONLINE'))->toBeFalse();
		expect(apcupsd_is_slave_status('ONBATT'))->toBeFalse();
		expect(apcupsd_is_slave_status(''))->toBeFalse();
		expect(apcupsd_is_slave_status(null))->toBeFalse();
	});
});

describe('estimated watts wiring', function () {
	$read = function (string $relative): string {
		$contents = file_get_contents(__DIR__ . '/../../' . $relative);

		expect($contents)->not->toBeFalse("Unable to read {$relative}");

		return (string) $contents;
	};

	it('sums the estimate across enabled UPSes in the fleet aggregates', function () use ($read) {
		$source = $read('includes/dashboard.php');

		expect($source)->toContain('AS estWatts');
		expect($source)->toContain('AS estCapacity');
		expect($source)->toContain('AS estCount');
		expect($source)->toContain("NOT LIKE '%SLAVE%'");
		expect($source)->toContain("'power'    => ['title' => __('Fleet Power', 'apcupsd')");
	});

	it('shows the estimate in the Fleet Dashboard table with a fleet total', function () use ($read) {
		$source = $read('includes/dashboard.php');

		expect($source)->toContain("\$th('power', __('Est. Power', 'apcupsd')");
		expect($source)->toContain('apcupsd_estimate_watts(');
		expect($source)->toContain('!apcupsd_is_slave_status(');
		expect($source)->toContain('apcupsdDashTotalRow');
	});

	it('makes the Fleet Dashboard table columns sortable without inline handlers', function () use ($read) {
		$source = $read('includes/dashboard.php');

		foreach (['name', 'status', 'load', 'charge', 'timeleft', 'power', 'updated'] as $key) {
			expect($source)->toContain("\$th('{$key}'");
		}

		expect(preg_match('/\sonclick\s*=/i', $source))->toBe(0);

		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->toContain("on('click.apcupsdsort', '.apcupsdDashSortBtn'");
		expect($js)->toContain('apcupsdDashApplySort(card)');
		expect($js)->toContain('aria-sort');
	});

	it('keeps the active sort in memory so it works without browser storage', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->toContain('var apcupsdDashSortState = null;');
		expect($js)->toContain('apcupsdDashSortState = state;');
		expect(preg_match('/function apcupsdDashSortRead\(\) \{\s*if \(apcupsdDashSortState === null\)/', $js))->toBe(1);
	});

	it('adds a sortable estimated power column to the UPS list', function () use ($read) {
		$source = $read('upses.php');

		expect($source)->toContain("'est_watts' => [");
		expect($source)->toContain('AS est_watts');
		expect($source)->toContain("\$ups['est_watts']");
	});
});
