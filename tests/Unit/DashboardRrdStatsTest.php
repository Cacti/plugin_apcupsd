<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The UPS Dashboard bullets and gauges read their min / average / max from the
 * device's RRD files. Two things used to leave them blank: the dashboard looked
 * for data source names the Device Package does not use (batt_volts, line_volts,
 * output_volts), and the minimum was fetched with a MIN consolidation that most
 * RRDs do not keep.
 */

if (!function_exists('number_format_i18n')) {
	function number_format_i18n($number, $decimals = 0) {
		return number_format((float) $number, (int) $decimals, '.', ',');
	}
}

if (!function_exists('get_data_source_path')) {
	function get_data_source_path($local_data_id, $expand = false) {
		return $GLOBALS['__rrd_paths'][(int) $local_data_id] ?? '';
	}
}

if (!function_exists('rrdtool_function_fetch')) {
	function rrdtool_function_fetch($local_data_id, $start, $end, $resolution = 0, $show_unknown = false, $rrd_file = '', $cf = 'AVERAGE') {
		$GLOBALS['__rrd_calls'][] = [(int) $local_data_id, $cf];

		return $GLOBALS['__rrd_data'][(int) $local_data_id][$cf] ?? [];
	}
}

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/dashboard.php';

/**
 * A fetch result for one data source, shaped like rrdtool_function_fetch().
 *
 * @param string  $ds     The data source name.
 * @param float[] $values The values, oldest first.
 *
 * @return array
 */
function apcupsd_test_rrd_fetch(string $ds, array $values): array {
	$series = [];

	foreach ($values as $i => $value) {
		$series[1700000000 + $i * 300] = (string) $value;
	}

	return ['data_source_names' => [$ds], 'values' => [0 => $series]];
}

/**
 * Describe a data source's RRD: its path, the consolidations its profile keeps
 * and the series each consolidation returns.
 *
 * @param int                  $id     The local data id.
 * @param string               $ds     The data source name.
 * @param array<string, array> $series Consolidation function => values.
 *
 * @return void
 */
function apcupsd_test_rrd_setup(int $id, string $ds, array $series): void {
	$cf_ids = ['AVERAGE' => 1, 'MIN' => 2, 'MAX' => 3, 'LAST' => 4];

	$GLOBALS['__rrd_paths'][$id] = "/rra/ds_{$id}.rrd";

	foreach ($series as $cf => $values) {
		$GLOBALS['__rrd_data'][$id][$cf] = apcupsd_test_rrd_fetch($ds, $values);
	}

	apcupsd_test_mock_db('db_fetch_assoc_prepared', function ($sql, $params) use ($id) {
		return strpos($sql, 'data_source_profiles_cf') !== false && $params === [$id];
	}, array_map(function ($cf) use ($cf_ids) {
		return ['cf' => $cf_ids[$cf]];
	}, $GLOBALS['__rrd_profile'][$id] ?? array_keys($series)));
}

/**
 * Register the fleet's estimable UPSes and which host has a load data source.
 *
 * @param array<int, array{0: int, 1: float, 2: float, 3: int}> $upses host_id, nominal watts, load %, load data source id (0 for none)
 *
 * @return void
 */
function apcupsd_test_fleet(array $upses): void {
	$rows = [];

	foreach ($upses as [$host, $nominal, $load, $ds]) {
		$rows[] = ['host_id' => $host, 'ups_nominal_power' => $nominal, 'ups_load_percent' => $load];

		apcupsd_test_mock_db('db_fetch_assoc_prepared', function ($sql, $params) use ($host) {
			return strpos($sql, 'FROM data_local') !== false && $params === [$host];
		}, $ds > 0 ? [['local_data_id' => $ds, 'data_source_name' => 'loadpct']] : []);
	}

	apcupsd_test_mock_db('db_fetch_assoc', 'FROM apcupsd_ups AS ups', $rows);
}

beforeEach(function () {
	$GLOBALS['__rrd_paths'] = [];
	$GLOBALS['__rrd_data']  = [];
	$GLOBALS['__rrd_calls'] = [];
	$GLOBALS['__rrd_profile'] = [];

	apcupsd_test_reset_db_mocks();
});

describe('data source matching against the Device Package names', function () {
	it('finds a data source for every dashboard metric', function () {
		$names = ['comonbatt', 'numxfers', 'max_line_volts', 'min_line_volts', 'high_transfer', 'low_transfer', 'internal_temp',
			'frequency', 'batt_charge', 'loadpct', 'timeleft', 'batt_volts', 'line_volts', 'output_volts'];

		$rows = [];

		foreach ($names as $i => $name) {
			$rows[] = ['local_data_id' => 100 + $i, 'data_source_name' => $name];
		}

		apcupsd_test_mock_db('db_fetch_assoc_prepared', 'FROM data_local', $rows);

		$expected = [
			'line_voltage'    => 'line_volts',
			'output_voltage'  => 'output_volts',
			'line_frequency'  => 'frequency',
			'load_percent'    => 'loadpct',
			'battery_charge'  => 'batt_charge',
			'battery_voltage' => 'batt_volts',
			'timeleft'        => 'timeleft',
			'temperature'     => 'internal_temp',
		];

		foreach (apcupsd_dashboard_metrics() as $key => $def) {
			[$id, $ds] = apcupsd_dashboard_find_data_source(901, $def['aliases']);

			expect($ds)->toBe($expected[$key], "metric {$key}");
			expect($id)->toBeGreaterThan(0);
		}
	});

	it('prefers the exact data source over a longer name that merely contains it', function () {
		apcupsd_test_mock_db('db_fetch_assoc_prepared', 'FROM data_local', [
			['local_data_id' => 1, 'data_source_name' => 'max_line_volts'],
			['local_data_id' => 2, 'data_source_name' => 'line_volts'],
		]);

		expect(apcupsd_dashboard_find_data_source(902, apcupsd_dashboard_metrics()['line_voltage']['aliases']))->toBe([2, 'line_volts']);
	});
});

describe('RRD series and stats', function () {
	it('reads the finite values of one data source', function () {
		apcupsd_test_rrd_setup(11, 'loadpct', ['AVERAGE' => [10, 'nan', 30]]);

		expect(apcupsd_dashboard_rrd_series(11, 'loadpct', -3600, 0, 'AVERAGE'))->toBe([10.0, 30.0]);
	});

	it('returns nothing for a missing RRD, an unknown column or an empty fetch', function () {
		expect(apcupsd_dashboard_rrd_series(0, 'loadpct', -3600, 0, 'AVERAGE'))->toBe([]);
		expect(apcupsd_dashboard_rrd_series(12, 'loadpct', -3600, 0, 'AVERAGE'))->toBe([]);

		apcupsd_test_rrd_setup(13, 'loadpct', ['AVERAGE' => [5]]);

		expect(apcupsd_dashboard_rrd_series(13, 'other', -3600, 0, 'AVERAGE'))->toBe([]);
		expect(apcupsd_dashboard_rrd_series(13, 'loadpct', -3600, 0, 'MAX'))->toBe([]);
	});

	it('uses the MIN and MAX archives when the RRD keeps them', function () {
		$GLOBALS['__rrd_profile'][21] = ['AVERAGE', 'MIN', 'MAX', 'LAST'];
		apcupsd_test_rrd_setup(21, 'timeleft', ['AVERAGE' => [20, 24, 28], 'MIN' => [2, 9], 'MAX' => [30, 31]]);

		$stats = apcupsd_dashboard_rrd_stats(21, 'timeleft', -3600, 0);

		expect($stats)->toBe(['min' => 2.0, 'avg' => 24.0, 'max' => 31.0, 'last' => 28.0]);
		expect($GLOBALS['__rrd_calls'])->toBe([[21, 'AVERAGE'], [21, 'MIN'], [21, 'MAX']]);
	});

	it('bounds the window by its averages when the RRD has no MIN archive, without fetching one', function () {
		$GLOBALS['__rrd_profile'][22] = ['AVERAGE', 'MAX'];
		apcupsd_test_rrd_setup(22, 'timeleft', ['AVERAGE' => [20, 24, 28], 'MAX' => [30, 31]]);

		$stats = apcupsd_dashboard_rrd_stats(22, 'timeleft', -3600, 0);

		expect($stats['min'])->toBe(20.0);
		expect($stats['max'])->toBe(31.0);
		expect($GLOBALS['__rrd_calls'])->toBe([[22, 'AVERAGE'], [22, 'MAX']]);
	});

	it('bounds the window by its averages when the RRD has neither MIN nor MAX', function () {
		$GLOBALS['__rrd_profile'][23] = ['AVERAGE'];
		apcupsd_test_rrd_setup(23, 'batt_volts', ['AVERAGE' => [26.5, 27.0, 27.5]]);

		$stats = apcupsd_dashboard_rrd_stats(23, 'batt_volts', -3600, 0);

		expect($stats)->toBe(['min' => 26.5, 'avg' => 27.0, 'max' => 27.5, 'last' => 27.5]);
	});

	it('assumes AVERAGE and MAX when the profile cannot be read', function () {
		apcupsd_test_rrd_setup(24, 'loadpct', ['AVERAGE' => [10, 20], 'MAX' => [40]]);
		apcupsd_test_reset_db_mocks();

		expect(apcupsd_dashboard_rrd_consolidations(24))->toBe(['AVERAGE', 'MAX']);
	});

	it('reports every statistic as unknown when the RRD has no data', function () {
		apcupsd_test_rrd_setup(25, 'loadpct', []);

		expect(apcupsd_dashboard_rrd_stats(25, 'loadpct', -3600, 0))->toBe(['min' => null, 'avg' => null, 'max' => null, 'last' => null]);
		expect($GLOBALS['__rrd_calls'])->toBe([[25, 'AVERAGE'], [25, 'MAX']]);
	});

	it('still reports the bounds when only the MIN and MAX archives have data', function () {
		$GLOBALS['__rrd_profile'][27] = ['AVERAGE', 'MIN', 'MAX'];
		apcupsd_test_rrd_setup(27, 'loadpct', ['MIN' => [4, 6], 'MAX' => [30, 25]]);

		expect(apcupsd_dashboard_rrd_stats(27, 'loadpct', -3600, 0))->toBe(['min' => 4.0, 'avg' => null, 'max' => 30.0, 'last' => null]);
	});

	it('reads each window once per request', function () {
		$GLOBALS['__rrd_profile'][26] = ['AVERAGE'];
		apcupsd_test_rrd_setup(26, 'loadpct', ['AVERAGE' => [10, 20]]);

		apcupsd_dashboard_rrd_stats(26, 'loadpct', -3600, 0);
		apcupsd_dashboard_rrd_stats(26, 'loadpct', -3600, 0);

		expect(count($GLOBALS['__rrd_calls']))->toBe(1);
	});
});

describe('bullet and gauge text', function () {
	it('writes the current, min, avg and max readings above the track', function () {
		$html = apcupsd_dashboard_bullet('Battery Voltage', 27.2, 26.1, 27.0, 27.4, 30.0, ' V', '');

		foreach (['now 27.2 V', 'min 26.1 V', 'avg 27 V', 'max 27.4 V'] as $text) {
			expect($html)->toContain($text);
		}
	});

	it('labels the track ends with the axis, not with N/A', function () {
		$html = apcupsd_dashboard_bullet('Battery Voltage', 27.2, null, null, null, 30.0, ' V', '');

		expect($html)->toContain('<div class="apcupsdBulletScale"><span>0 V</span><span>30 V</span></div>');
		expect($html)->not->toContain('N/A');
	});

	it('leaves out only the readings that are unknown', function () {
		$html = apcupsd_dashboard_bullet('Load', 20.0, null, null, 35.0, 100.0, ' W', '');

		expect($html)->toContain('now 20 W');
		expect($html)->toContain('max 35 W');
		expect($html)->not->toContain('min ');
		expect($html)->not->toContain('avg ');
		expect($html)->not->toContain('N/A');
	});

	it('shows time left from zero minutes', function () {
		$html = apcupsd_dashboard_bullet('Time Left', 25.0, null, null, 40.0, 52.0, ' min', '');

		expect($html)->toContain('<span>0 min</span><span>52 min</span>');
	});

	it('lists only the known statistics under a gauge', function () {
		expect(apcupsd_dashboard_stats_caption(1.0, 2.0, 3.0, '%'))->toBe('min 1% · avg 2% · max 3%');
		expect(apcupsd_dashboard_stats_caption(1.0, null, 3.0, '%'))->toBe('min 1% · max 3%');
		expect(apcupsd_dashboard_stats_caption(null, 2.0, null, '%'))->toBe('avg 2%');
		expect(apcupsd_dashboard_stats_caption(null, null, null, '%'))->toBe('');
	});

	it('does not print N/A in a gauge when a bound is unknown', function () {
		$html = apcupsd_dashboard_gauge_from_reading(['label' => 'Load', 'current' => 12.0, 'min' => null, 'avg' => 14.0, 'max' => 20.0, 'scale' => 100.0, 'unit' => '%', 'severity' => 'up']);

		expect($html)->toContain('avg 14% · max 20%');
		expect($html)->not->toContain('N/A');
	});
});

describe('fleet power range from the load RRDs', function () {
	it('scales each UPS\'s load history by its nominal power and sums the fleet', function () {
		$GLOBALS['__rrd_profile'][71] = ['AVERAGE', 'MIN', 'MAX'];
		apcupsd_test_rrd_setup(71, 'loadpct', ['AVERAGE' => [10, 14], 'MIN' => [8], 'MAX' => [20]]);

		// UPS 1: 1000 W rated with load history. UPS 2: 500 W rated at 40% with no history.
		apcupsd_test_fleet([[7, 1000.0, 12.0, 71], [8, 500.0, 40.0, 0]]);

		// UPS 2 counts at its latest estimate (200 W) in every bound, so the range stays consistent.
		expect(apcupsd_fleet_power_stats(-3600, 0))->toBe(['min' => 280.0, 'avg' => 320.0, 'max' => 400.0]);
	});

	it('reports no range when no UPS has load history', function () {
		apcupsd_test_fleet([[9, 1000.0, 12.0, 0], [10, 500.0, 40.0, 0]]);

		expect(apcupsd_fleet_power_stats(-7200, 0))->toBe(['min' => null, 'avg' => null, 'max' => null]);
	});

	it('only counts the UPSes the fleet total counts', function () {
		$source = (string) file_get_contents(__DIR__ . '/../../includes/dashboard.php');

		expect(substr_count($source, 'apcupsd_fleet_estimable_sql()'))->toBeGreaterThan(2);
		expect($source)->toContain('$range = apcupsd_fleet_power_stats(get_current_graph_start(), get_current_graph_end());');
		expect($source)->toContain('apcupsd_dashboard_gauge($est_watts, $range[\'min\'], $range[\'max\'], $est_capacity');
	});
});

describe('RRD reading wiring', function () {
	it('reads every dashboard surface through the shared stats function', function () {
		$source = (string) file_get_contents(__DIR__ . '/../../includes/dashboard.php');

		expect($source)->not->toContain('apcupsd_dashboard_rrd_value');
		expect(substr_count($source, 'apcupsd_dashboard_rrd_stats('))->toBeGreaterThan(3);
	});
});
