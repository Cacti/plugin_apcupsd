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

		expect($source)->toContain("\$th(__('Est. Power', 'apcupsd')");
		expect($source)->toContain('apcupsd_estimate_watts(');
		expect($source)->toContain('!apcupsd_is_slave_status(');
		expect($source)->toContain('apcupsdDashTotalRow');
	});

	it('sorts the Fleet Dashboard table like the HMIB dashboards, without inline handlers', function () use ($read) {
		$source = $read('includes/dashboard.php');

		foreach (['UPS', 'Status', 'Load', 'Charge', 'Time Left', 'Est. Power', 'Last Updated'] as $label) {
			expect($source)->toContain("\$th(__('{$label}', 'apcupsd')");
		}

		expect($source)->toContain("class=\"apcupsdDashSortable'");
		expect($source)->toContain('data-sort="num"');
		expect($source)->toContain('data-sort-value="');
		expect($source)->not->toContain('apcupsdDashSortBtn');
		expect($source)->not->toContain('data-sort-key');
		expect(preg_match('/\sonclick\s*=/i', $source))->toBe(0);

		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->toContain("on('click.apcupsdsort', 'th.apcupsdDashSortable'");
		expect($js)->toContain("on('keydown.apcupsdsort', 'th.apcupsdDashSortable'");
		expect($js)->toContain('function apcupsdDashSortTable(th)');
		expect($js)->toContain('apcupsdDashPrepSortHeaders(card)');
		expect($js)->toContain("setAttribute('aria-sort'");
	});

	it('also sorts in the maximize dialog', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->toContain('apcupsdDashPrepSortHeaders(dialog);');
		expect($js)->toContain('apcupsdDashBindSort(dialog);');
	});

	it('keeps no persisted sort state beyond a click-to-sort header like HMIB', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->not->toContain('localStorage');
		expect($js)->not->toContain('apcupsdDashSortState');
	});

	it('right-aligns the numeric and last-updated columns of the UPSes card', function () use ($read) {
		$source = $read('includes/dashboard.php');

		foreach (['Load', 'Charge', 'Time Left', 'Est. Power'] as $label) {
			expect($source)->toContain("\$th(__('{$label}', 'apcupsd'), true, true)");
		}

		expect($source)->toContain("\$th(__('Last Updated', 'apcupsd'), false, true)");
		expect($source)->toContain("'<td class=\"apcupsdDashNum\">' . html_escape((string) (\$ups['last_updated']");

		$css = $read('css/apcupsd.css');

		// Must outrank ".apcupsdDashTable th, .apcupsdDashTable td { text-align: left }".
		expect($css)->toContain('.apcupsdDashTable td.apcupsdDashNum,');
		expect($css)->toContain('.apcupsdDashTable th.apcupsdDashNum {');
	});

	it('adds a sortable estimated power column to the UPS list', function () use ($read) {
		$source = $read('upses.php');

		expect($source)->toContain("'est_watts' => [");
		expect($source)->toContain('AS est_watts');
		expect($source)->toContain("\$ups['est_watts']");
	});
});
