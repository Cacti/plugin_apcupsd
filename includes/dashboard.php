<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * UPS Dashboard: the per-UPS drilldown. A draggable grid of panel/cards that
 * brings together a device's identity, min/avg/max bullet charts (sourced from
 * the UPS's RRD files over the selected Cacti timespan) and every Cacti graph
 * for the UPS in one place. Card order, expanded state, the selected UPS and
 * the auto-refresh interval persist per user; the graph timespan uses Cacti's
 * standard session timespan selector. Mirrors the card interaction model used
 * by the hmib, intropage, mactrack and syslog dashboards.
 */

if (!function_exists('apcupsd_dashboard_print_css')) {
	/**
	 * Emit this plugin's Dashboard stylesheet: the base rules plus the active
	 * theme's override file when one is shipped. The theme name is validated
	 * against the shipped whitelist before it reaches a path, since
	 * get_selected_theme() can return an unvalidated session value on older
	 * Cacti releases.
	 *
	 * @return void
	 *
	 * @global array $config Cacti global configuration array.
	 */
	function apcupsd_dashboard_print_css(): void {
		global $config;

		print get_md5_include_css('plugins/apcupsd/css/apcupsd.css');

		$theme = get_selected_theme();

		$shipped = ['classic', 'dark', 'deepness', 'midwinter', 'modern', 'paper-plane', 'paw', 'sunrise'];

		if (in_array($theme, $shipped, true)) {
			$theme_css = 'plugins/apcupsd/css/' . $theme . '.css';

			if (file_exists($config['base_path'] . '/' . $theme_css)) {
				print get_md5_include_css($theme_css);
			}
		}
	}
}

if (!function_exists('apcupsd_dashboard_ups_exists')) {
	/**
	 * Whether a UPS id refers to a UPS this plugin manages.
	 *
	 * @param int $ups_id The apcupsd_ups id to test.
	 *
	 * @return bool True when the UPS exists.
	 */
	function apcupsd_dashboard_ups_exists($ups_id): bool {
		if ((int) $ups_id <= 0) {
			return false;
		}

		return db_fetch_cell_prepared('SELECT COUNT(*)
			FROM apcupsd_ups
			WHERE id = ?',
			[(int) $ups_id]) > 0;
	}
}

if (!function_exists('apcupsd_dashboard_resolve_ups')) {
	/**
	 * Resolve the UPS the Dashboard should display. A validated 'id' request
	 * variable wins and is remembered as the user's last-viewed UPS; otherwise
	 * the previously remembered UPS is used. Returns 0 when neither yields a
	 * known UPS.
	 *
	 * @return int The resolved apcupsd_ups id, or 0 when none is available.
	 */
	function apcupsd_dashboard_resolve_ups(): int {
		$ups_id = 0;

		if (isset_request_var('id')) {
			$candidate = get_nfilter_request_var('id');

			if (preg_match('/^[0-9]+$/', (string) $candidate)) {
				$candidate = (int) $candidate;

				if (apcupsd_dashboard_ups_exists($candidate)) {
					$ups_id = $candidate;

					if ((int) read_user_setting('apcupsd_dashboard_ups', 0) !== $ups_id) {
						set_user_setting('apcupsd_dashboard_ups', $ups_id);
					}
				}
			}
		}

		if ($ups_id === 0) {
			$stored = (int) read_user_setting('apcupsd_dashboard_ups', 0);

			if (apcupsd_dashboard_ups_exists($stored)) {
				$ups_id = $stored;
			}
		}

		return $ups_id;
	}
}

if (!function_exists('apcupsd_dashboard_ups')) {
	/**
	 * Memoized UPS row joined to its latest polled readings and linked Cacti
	 * device, used by every Dashboard card.
	 *
	 * @param int $ups_id The UPS to load.
	 *
	 * @return array The joined apcupsd_ups/apcupsd_ups_stats/host row, or [].
	 */
	function apcupsd_dashboard_ups($ups_id): array {
		static $cache = [];

		$ups_id = (int) $ups_id;

		if (!array_key_exists($ups_id, $cache)) {
			$row = db_fetch_row_prepared('SELECT ups.*, stats.*, host.description AS host_description,
				host.status AS host_status, host.disabled AS host_disabled
				FROM apcupsd_ups AS ups
				LEFT JOIN apcupsd_ups_stats AS stats ON stats.ups_id = ups.id
				LEFT JOIN host ON host.id = ups.host_id
				WHERE ups.id = ?',
				[$ups_id]);

			$cache[$ups_id] = is_array($row) ? $row : [];
		}

		return $cache[$ups_id];
	}
}

if (!function_exists('apcupsd_dashboard_init_timespan')) {
	/**
	 * Restore Cacti's standard graph timespan from the session/request so the
	 * graph cards and RRD bullet queries share a single selected window. Safe to
	 * call more than once per request.
	 *
	 * @return void
	 */
	function apcupsd_dashboard_init_timespan(): void {
		static $done = false;

		if ($done) {
			return;
		}

		$done = true;

		global $config;

		include($config['base_path'] . '/lib/timespan_settings.php');
	}
}

if (!function_exists('apcupsd_dashboard_graphs')) {
	/**
	 * The permitted Cacti graphs for a UPS's linked device, in title order.
	 *
	 * @param int $host_id The UPS's Cacti host id (0 when none is linked).
	 *
	 * @return array<int, array{local_graph_id: int, title_cache: string}>
	 */
	function apcupsd_dashboard_graphs($host_id): array {
		static $cache = [];

		$host_id = (int) $host_id;

		if ($host_id <= 0) {
			return [];
		}

		if (!array_key_exists($host_id, $cache)) {
			$total = 0;
			$graphs = get_allowed_graphs('gl.host_id=' . $host_id, 'gtg.title_cache', '', $total);
			$cache[$host_id] = is_array($graphs) ? $graphs : [];
		}

		return $cache[$host_id];
	}
}

if (!function_exists('apcupsd_dashboard_metrics')) {
	/**
	 * The catalogue of numeric UPS readings that can be shown as min/avg/max
	 * bullet charts. Each entry defines its live apcupsd_ups_stats column, unit,
	 * value grouping, the RRD data-source name aliases used to locate the
	 * matching RRD, a sensible full-scale floor and whether it is a percentage.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function apcupsd_dashboard_metrics(): array {
		return [
			'line_voltage' => [
				'label'   => __('Line Voltage', 'apcupsd'),
				'col'     => 'ups_line_voltage',
				'unit'    => ' V',
				'group'   => 'power',
				'aliases' => ['linev', 'line_voltage', 'voltage', 'inputv'],
				'scale'   => 150.0,
				'nominal' => 'ups_nominal_voltage',
				'pct'     => false,
			],
			'output_voltage' => [
				'label'   => __('Output Voltage', 'apcupsd'),
				'col'     => 'ups_output_voltage',
				'unit'    => ' V',
				'group'   => 'power',
				'aliases' => ['outputv', 'output_voltage', 'outv'],
				'scale'   => 150.0,
				'nominal' => 'ups_nominal_output_voltage',
				'pct'     => false,
			],
			'line_frequency' => [
				'label'   => __('Line Frequency', 'apcupsd'),
				'col'     => 'ups_line_frequency',
				'unit'    => ' Hz',
				'group'   => 'power',
				'aliases' => ['linefreq', 'line_frequency', 'frequency', 'freq'],
				'scale'   => 70.0,
				'nominal' => '',
				'pct'     => false,
			],
			'load_percent' => [
				'label'   => __('Load', 'apcupsd'),
				'col'     => 'ups_load_percent',
				'unit'    => '%',
				'group'   => 'power',
				'aliases' => ['loadpct', 'load_percent', 'load', 'percent'],
				'scale'   => 100.0,
				'nominal' => '',
				'pct'     => true,
			],
			'battery_charge' => [
				'label'   => __('Battery Charge', 'apcupsd'),
				'col'     => 'ups_battery_charge',
				'unit'    => '%',
				'group'   => 'battery',
				'aliases' => ['bcharge', 'battery_charge', 'charge'],
				'scale'   => 100.0,
				'nominal' => '',
				'pct'     => true,
			],
			'battery_voltage' => [
				'label'   => __('Battery Voltage', 'apcupsd'),
				'col'     => 'ups_battery_voltage',
				'unit'    => ' V',
				'group'   => 'battery',
				'aliases' => ['battv', 'battery_voltage', 'batteryv'],
				'scale'   => 30.0,
				'nominal' => 'ups_nominal_batt_voltage',
				'pct'     => false,
			],
			'timeleft' => [
				'label'   => __('Time Left', 'apcupsd'),
				'col'     => 'ups_timeleft',
				'unit'    => ' min',
				'group'   => 'battery',
				'aliases' => ['timeleft', 'runtime', 'time_left'],
				'scale'   => 30.0,
				'nominal' => 'ups_maxtime',
				'pct'     => false,
			],
			'temperature' => [
				'label'   => __('Temperature', 'apcupsd'),
				'col'     => 'ups_internal_temp',
				'unit'    => '°C',
				'group'   => 'battery',
				'aliases' => ['itemp', 'internal_temp', 'temp', 'ambtemp'],
				'scale'   => 80.0,
				'nominal' => '',
				'pct'     => false,
			],
		];
	}
}

if (!function_exists('apcupsd_dashboard_metric_severity')) {
	/**
	 * Map a reading to a severity token used to tint its bullet band. Only the
	 * readings with a meaningful "good/bad" direction carry a severity; the rest
	 * render neutral.
	 *
	 * @param string     $metric The metric key.
	 * @param float|null $value  The current value, or null when unknown.
	 *
	 * @return string One of 'up', 'recovering', 'down' or '' (neutral).
	 */
	function apcupsd_dashboard_metric_severity(string $metric, ?float $value): string {
		if ($value === null) {
			return '';
		}

		switch ($metric) {
			case 'load_percent':
				return $value >= 90 ? 'down' : ($value >= 70 ? 'recovering' : 'up');
			case 'battery_charge':
				return $value < 30 ? 'down' : ($value < 60 ? 'recovering' : 'up');
			case 'timeleft':
				return $value < 5 ? 'down' : ($value < 15 ? 'recovering' : 'up');
		}

		return '';
	}
}

if (!function_exists('apcupsd_dashboard_find_data_source')) {
	/**
	 * Locate the RRD data source on a device whose name best matches a reading,
	 * so its historical min/avg/max can be fetched. Exact alias matches win over
	 * substring matches.
	 *
	 * @param int      $host_id The device to search.
	 * @param string[] $aliases Candidate data-source names for the reading.
	 *
	 * @return array{0: int, 1: string} [local_data_id, data_source_name]; [0, '']
	 *                                   when no data source matches.
	 */
	function apcupsd_dashboard_find_data_source($host_id, array $aliases): array {
		$host_id = (int) $host_id;

		if ($host_id <= 0) {
			return [0, ''];
		}

		static $cache = [];

		if (!array_key_exists($host_id, $cache)) {
			$rows = db_fetch_assoc_prepared('SELECT dl.id AS local_data_id, dtr.data_source_name
				FROM data_local AS dl
				INNER JOIN data_template_rrd AS dtr ON dtr.local_data_id = dl.id
				WHERE dl.host_id = ?',
				[$host_id]);

			$cache[$host_id] = is_array($rows) ? $rows : [];
		}

		$rows = $cache[$host_id];

		foreach ($rows as $row) {
			$name = strtolower((string) $row['data_source_name']);

			foreach ($aliases as $alias) {
				if ($name === strtolower($alias)) {
					return [(int) $row['local_data_id'], (string) $row['data_source_name']];
				}
			}
		}

		foreach ($rows as $row) {
			$name = strtolower((string) $row['data_source_name']);

			foreach ($aliases as $alias) {
				if ($alias !== '' && strpos($name, strtolower($alias)) !== false) {
					return [(int) $row['local_data_id'], (string) $row['data_source_name']];
				}
			}
		}

		return [0, ''];
	}
}

if (!function_exists('apcupsd_dashboard_rrd_value')) {
	/**
	 * Fetch a single consolidated value for a data source over a window from its
	 * RRD file. The requested consolidation function is applied across the whole
	 * series (min of the MINs, max of the MAXs, etc.).
	 *
	 * @param int    $local_data_id The data source to read.
	 * @param string $ds_name       The RRD data-source (column) name.
	 * @param int    $start         The window start (relative seconds, negative).
	 * @param int    $end           The window end (relative seconds, <= 0).
	 * @param string $cf            'MIN', 'MAX', 'AVERAGE' or 'LAST'.
	 *
	 * @return float|null The reduced value, or null when no finite data exists.
	 */
	function apcupsd_dashboard_rrd_value($local_data_id, string $ds_name, $start, $end, string $cf): ?float {
		if ((int) $local_data_id <= 0) {
			return null;
		}

		// Resolve the RRD path explicitly: on Cacti develop the sixth parameter of
		// rrdtool_function_fetch() is a non-nullable string, so passing null raises a
		// TypeError. Passing the path is also compatible with Cacti 1.2.x.
		$rrd_path = get_data_source_path((int) $local_data_id, true);

		if (empty($rrd_path)) {
			return null;
		}

		$fetch = rrdtool_function_fetch((int) $local_data_id, $start, $end, 0, false, $rrd_path, $cf);

		if (!is_array($fetch) || empty($fetch['data_source_names']) || empty($fetch['values'])) {
			return null;
		}

		$index = array_search($ds_name, $fetch['data_source_names'], true);

		if ($index === false || !isset($fetch['values'][$index])) {
			return null;
		}

		$numbers = [];

		foreach ($fetch['values'][$index] as $value) {
			if (is_numeric($value)) {
				$numbers[] = (float) $value;
			}
		}

		if (!cacti_sizeof($numbers)) {
			return null;
		}

		switch ($cf) {
			case 'MIN':
				return min($numbers);
			case 'MAX':
				return max($numbers);
			case 'LAST':
				return (float) end($numbers);
			default:
				return array_sum($numbers) / count($numbers);
		}
	}
}

if (!function_exists('apcupsd_dashboard_bullet')) {
	/**
	 * Render a min/avg/max bullet chart: a 0..scale track, a translucent band
	 * from min to max, and a strong marker at the current value. The average and
	 * min/max endpoints are labelled.
	 *
	 * @param string     $label    The reading label.
	 * @param float|null $current  The current value (marker), or null.
	 * @param float|null $min      The window minimum, or null.
	 * @param float|null $avg      The window average, or null.
	 * @param float|null $max      The window maximum, or null.
	 * @param float      $scale    The axis maximum (full-width value).
	 * @param string     $unit     The value unit suffix (e.g. ' V', '%').
	 * @param string     $severity 'up'/'recovering'/'down' band tint, else ''.
	 *
	 * @return string The bullet HTML.
	 */
	function apcupsd_dashboard_bullet(string $label, ?float $current, ?float $min, ?float $avg, ?float $max, float $scale, string $unit, string $severity): string {
		$fmt = function(?float $value) use ($unit): string {
			if ($value === null) {
				return __('N/A', 'apcupsd');
			}

			$decimals = (abs($value) >= 100 || floor($value) == $value) ? 0 : 1;

			return number_format_i18n($value, $decimals) . $unit;
		};

		$marker_value = $current !== null ? $current : $avg;

		$summary = [];

		if ($current !== null) {
			$summary[] = __('now %s', $fmt($current), 'apcupsd');
		}

		if ($avg !== null) {
			$summary[] = __('avg %s', $fmt($avg), 'apcupsd');
		}

		$head = '<div class="apcupsdBulletHead">'
			. '<span class="apcupsdBulletLabel">' . html_escape($label) . '</span>'
			. '<span class="apcupsdBulletAvg">' . html_escape(implode(' · ', $summary)) . '</span>'
			. '</div>';

		if ($marker_value === null || $scale <= 0) {
			return '<div class="apcupsdBullet">' . $head . '<div class="apcupsdBulletTrack"></div></div>';
		}

		$lo = max(0.0, min($scale, (float) ($min !== null ? $min : $marker_value)));
		$hi = max(0.0, min($scale, (float) ($max !== null ? $max : $marker_value)));
		$mk = max(0.0, min($scale, (float) $marker_value));

		$left  = $lo / $scale * 100;
		$width = max(0.75, ($hi - $lo) / $scale * 100);
		$mark  = $mk / $scale * 100;

		$sev = in_array($severity, ['up', 'recovering', 'down'], true) ? $severity : 'none';

		// A faint tick marks the historical average, distinct from the strong marker
		// at the current reading, so both are visible when they differ.
		$avg_marker = '';

		if ($avg !== null) {
			$avg_pos = max(0.0, min($scale, (float) $avg)) / $scale * 100;
			$avg_marker = '<div class="apcupsdBulletAvgMarker" style="left:' . number_format($avg_pos, 2, '.', '') . '%" title="' . __esc('Average', 'apcupsd') . '"></div>';
		}

		return '<div class="apcupsdBullet">' . $head
			. '<div class="apcupsdBulletTrack">'
			. '<div class="apcupsdBulletRange apcupsdBulletRange--' . $sev . '" style="left:' . number_format($left, 2, '.', '') . '%;width:' . number_format($width, 2, '.', '') . '%"></div>'
			. $avg_marker
			. '<div class="apcupsdBulletMarker" style="left:' . number_format($mark, 2, '.', '') . '%"></div>'
			. '</div>'
			. '<div class="apcupsdBulletScale"><span>' . html_escape($fmt($min)) . '</span><span>' . html_escape($fmt($max)) . '</span></div>'
			. '</div>';
	}
}

if (!function_exists('apcupsd_dashboard_gauge')) {
	/**
	 * Render a half-donut gauge: a 0..scale semicircular track, a current-value
	 * sweep tinted by severity, faint ticks at the window min and max, and the
	 * value printed large in the centre. Mirrors the intropage / hmib gauge
	 * visuals that sit alongside the bullet charts.
	 *
	 * @param float|null $current      The current value (sweep + centre), or null.
	 * @param float|null $min          The window minimum (faint tick), or null.
	 * @param float|null $max          The window maximum (faint tick), or null.
	 * @param float      $scale        The axis maximum (full sweep value).
	 * @param string     $severity     'up'/'recovering'/'down' sweep tint, else ''.
	 * @param string     $center_label The large value printed inside the dial.
	 * @param string     $sub_label    An optional caption under the value.
	 *
	 * @return string The gauge HTML.
	 */
	function apcupsd_dashboard_gauge(?float $current, ?float $min, ?float $max, float $scale, string $severity, string $center_label, string $sub_label = ''): string {
		$sev = in_array($severity, ['up', 'recovering', 'down'], true) ? $severity : 'none';

		$frac = ($current !== null && $scale > 0) ? max(0.0, min(1.0, $current / $scale)) : 0.0;
		$dash = number_format($frac * 100, 2, '.', '');

		// Faint radial ticks mark the window min and max on the 180° arc (centre
		// (60,60), radius 50), straddling the 11px-wide value band.
		$tick = function(?float $value) use ($scale): string {
			if ($value === null || $scale <= 0) {
				return '';
			}

			$frac  = max(0.0, min(1.0, $value / $scale));
			$angle = M_PI * (1 - $frac);
			$ix = 60 + 44 * cos($angle);
			$iy = 60 - 44 * sin($angle);
			$ox = 60 + 56 * cos($angle);
			$oy = 60 - 56 * sin($angle);

			return '<line class="apcupsdGaugeTick" x1="' . number_format($ix, 2, '.', '') . '" y1="' . number_format($iy, 2, '.', '')
				. '" x2="' . number_format($ox, 2, '.', '') . '" y2="' . number_format($oy, 2, '.', '') . '" />';
		};

		return '<div class="apcupsdGauge">'
			. '<svg class="apcupsdGaugeSvg" viewBox="0 0 120 68" role="img" aria-label="' . html_escape($center_label) . '">'
			. '<path class="apcupsdGaugeTrack" d="M10 60 A50 50 0 0 1 110 60" fill="none" pathLength="100" />'
			. '<path class="apcupsdGaugeValue apcupsdGaugeValue--' . $sev . '" d="M10 60 A50 50 0 0 1 110 60" fill="none" pathLength="100" stroke-dasharray="' . $dash . ' 100" />'
			. $tick($min) . $tick($max)
			. '</svg>'
			. '<div class="apcupsdGaugeText">'
			. '<span class="apcupsdGaugeValueText">' . html_escape($center_label) . '</span>'
			. ($sub_label !== '' ? '<span class="apcupsdGaugeSub">' . html_escape($sub_label) . '</span>' : '')
			. '</div></div>';
	}
}

if (!function_exists('apcupsd_dashboard_render_bullets')) {
	/**
	 * Render every reading in a bullet group for a UPS, pulling the current value
	 * from the latest poll and the min/avg/max from the UPS's RRD files over the
	 * selected timespan (falling back to a current-only bullet when no matching
	 * RRD is found).
	 *
	 * @param string $group  The metric group ('power' or 'battery').
	 * @param int    $ups_id The UPS to render for.
	 *
	 * @return string The bullets HTML, or an empty-state message.
	 */
	function apcupsd_dashboard_render_bullets(string $group, $ups_id): string {
		$ups = apcupsd_dashboard_ups($ups_id);

		if (!cacti_sizeof($ups)) {
			return '<p class="apcupsdDashEmpty">' . __esc('No readings have been collected for this UPS yet.', 'apcupsd') . '</p>';
		}

		apcupsd_dashboard_init_timespan();

		$start   = get_current_graph_start();
		$end     = get_current_graph_end();
		$host_id = (int) ($ups['host_id'] ?? 0);

		$out  = '';
		$rows = 0;

		foreach (apcupsd_dashboard_metrics() as $key => $def) {
			if ($def['group'] !== $group) {
				continue;
			}

			$current = isset($ups[$def['col']]) && is_numeric($ups[$def['col']]) ? (float) $ups[$def['col']] : null;

			list($local_data_id, $ds_name) = apcupsd_dashboard_find_data_source($host_id, $def['aliases']);

			$min = $avg = $max = null;

			if ($local_data_id > 0) {
				$min = apcupsd_dashboard_rrd_value($local_data_id, $ds_name, $start, $end, 'MIN');
				$avg = apcupsd_dashboard_rrd_value($local_data_id, $ds_name, $start, $end, 'AVERAGE');
				$max = apcupsd_dashboard_rrd_value($local_data_id, $ds_name, $start, $end, 'MAX');

				if ($current === null) {
					$current = apcupsd_dashboard_rrd_value($local_data_id, $ds_name, $start, $end, 'LAST');
				}
			}

			if ($current === null && $avg === null) {
				continue;
			}

			$scale = (float) $def['scale'];

			if (!$def['pct']) {
				if (!empty($def['nominal']) && isset($ups[$def['nominal']]) && is_numeric($ups[$def['nominal']]) && (float) $ups[$def['nominal']] > 0) {
					$scale = max($scale, (float) $ups[$def['nominal']] * 1.3);
				}

				foreach ([$current, $max] as $candidate) {
					if ($candidate !== null) {
						$scale = max($scale, (float) $candidate * 1.15);
					}
				}
			}

			$severity = apcupsd_dashboard_metric_severity($key, $current);

			$out .= apcupsd_dashboard_bullet($def['label'], $current, $min, $avg, $max, $scale, $def['unit'], $severity);
			$rows++;
		}

		if ($rows === 0) {
			return '<p class="apcupsdDashEmpty">' . __esc('No readings are available for this group yet.', 'apcupsd') . '</p>';
		}

		return $out;
	}
}

if (!function_exists('apcupsd_dashboard_format_value')) {
	/**
	 * Format a reading with its unit, dropping decimals for whole or large values.
	 *
	 * @param float|null $value The value, or null for an unknown reading.
	 * @param string     $unit  The unit suffix (e.g. ' V', '%').
	 *
	 * @return string The formatted value, or a localized 'N/A'.
	 */
	function apcupsd_dashboard_format_value(?float $value, string $unit): string {
		if ($value === null) {
			return __('N/A', 'apcupsd');
		}

		$decimals = (abs($value) >= 100 || floor($value) == $value) ? 0 : 1;

		return number_format_i18n($value, $decimals) . $unit;
	}
}

if (!function_exists('apcupsd_dashboard_gauge_cards')) {
	/**
	 * The per-UPS gauge cards, each mapping a card key to the metric it renders.
	 * One gauge per card, mirroring the hmib CPU/memory/swap gauge cards.
	 *
	 * @return array<string, string> Card key => metric key.
	 */
	function apcupsd_dashboard_gauge_cards(): array {
		return [
			'gauge_charge'  => 'battery_charge',
			'gauge_load'    => 'load_percent',
			'gauge_voltage' => 'line_voltage',
			'gauge_runtime' => 'timeleft',
		];
	}
}

if (!function_exists('apcupsd_dashboard_metric_reading')) {
	/**
	 * Resolve a UPS metric's current value plus its min/avg/max from the RRD files
	 * over the selected timespan, and the display scale for a gauge.
	 *
	 * @param string $key    A key from apcupsd_dashboard_metrics().
	 * @param int    $ups_id The UPS to read.
	 *
	 * @return array{label: string, current: float|null, min: float|null, avg: float|null, max: float|null, scale: float, unit: string, severity: string}|null
	 */
	function apcupsd_dashboard_metric_reading(string $key, $ups_id): ?array {
		// Memoized per request so the gauge card header and body resolve one reading
		// (and read the RRD once) rather than twice.
		static $cache = [];

		$cache_key = $key . ':' . (int) $ups_id;

		if (array_key_exists($cache_key, $cache)) {
			return $cache[$cache_key];
		}

		$metrics = apcupsd_dashboard_metrics();

		if (!isset($metrics[$key])) {
			return $cache[$cache_key] = null;
		}

		$ups = apcupsd_dashboard_ups($ups_id);

		if (!cacti_sizeof($ups)) {
			return $cache[$cache_key] = null;
		}

		apcupsd_dashboard_init_timespan();

		$start   = get_current_graph_start();
		$end     = get_current_graph_end();
		$host_id = (int) ($ups['host_id'] ?? 0);
		$def     = $metrics[$key];

		$current = isset($ups[$def['col']]) && is_numeric($ups[$def['col']]) ? (float) $ups[$def['col']] : null;

		list($local_data_id, $ds_name) = apcupsd_dashboard_find_data_source($host_id, $def['aliases']);

		$min = $avg = $max = null;

		if ($local_data_id > 0) {
			$min = apcupsd_dashboard_rrd_value($local_data_id, $ds_name, $start, $end, 'MIN');
			$avg = apcupsd_dashboard_rrd_value($local_data_id, $ds_name, $start, $end, 'AVERAGE');
			$max = apcupsd_dashboard_rrd_value($local_data_id, $ds_name, $start, $end, 'MAX');

			if ($current === null) {
				$current = apcupsd_dashboard_rrd_value($local_data_id, $ds_name, $start, $end, 'LAST');
			}
		}

		$scale = (float) $def['scale'];

		if (!$def['pct']) {
			if (!empty($def['nominal']) && isset($ups[$def['nominal']]) && is_numeric($ups[$def['nominal']]) && (float) $ups[$def['nominal']] > 0) {
				$scale = max($scale, (float) $ups[$def['nominal']] * 1.3);
			}

			foreach ([$current, $max] as $candidate) {
				if ($candidate !== null) {
					$scale = max($scale, (float) $candidate * 1.15);
				}
			}
		}

		// Severity follows the value the gauge actually displays (the polled reading,
		// else the RRD fallback) so the card header accent matches the sweep.
		$display = $current !== null ? $current : $avg;

		return $cache[$cache_key] = [
			'label'    => $def['label'],
			'current'  => $current,
			'min'      => $min,
			'avg'      => $avg,
			'max'      => $max,
			'scale'    => $scale,
			'unit'     => $def['unit'],
			'severity' => apcupsd_dashboard_metric_severity($key, $display),
		];
	}
}

if (!function_exists('apcupsd_dashboard_gauge_card_body')) {
	/**
	 * Render a single-metric gauge card body: the current reading swept on the
	 * dial with the value in the centre and a min/max caption from the RRD window.
	 *
	 * @param string $metric_key A key from apcupsd_dashboard_metrics().
	 * @param int    $ups_id     The UPS to render for.
	 *
	 * @return string The gauge HTML, or an empty-state message.
	 */
	function apcupsd_dashboard_gauge_card_body(string $metric_key, $ups_id): string {
		$reading = apcupsd_dashboard_metric_reading($metric_key, $ups_id);

		if ($reading === null) {
			return '<p class="apcupsdDashEmpty">' . __esc('No readings have been collected for this UPS yet.', 'apcupsd') . '</p>';
		}

		$value = $reading['current'] !== null ? $reading['current'] : $reading['avg'];

		if ($value === null && $reading['min'] === null && $reading['max'] === null) {
			return '<p class="apcupsdDashEmpty">' . __esc('No readings are available for this gauge yet.', 'apcupsd') . '</p>';
		}

		$center = apcupsd_dashboard_format_value($value, $reading['unit']);

		$sub = '';

		if ($reading['min'] !== null || $reading['max'] !== null) {
			$sub = __('min %s · max %s', apcupsd_dashboard_format_value($reading['min'], $reading['unit']), apcupsd_dashboard_format_value($reading['max'], $reading['unit']), 'apcupsd');
		}

		return apcupsd_dashboard_gauge($value, $reading['min'], $reading['max'], $reading['scale'], $reading['severity'], $center, $sub);
	}
}

if (!function_exists('apcupsd_dashboard_card_meta')) {
	/**
	 * The Dashboard card registry for a UPS: each fixed card plus one card per
	 * Cacti graph on the UPS's device. Values are the card title, column span
	 * (out of 8) and whether the card clips with a "show more" control.
	 *
	 * @param int $ups_id The UPS the cards describe.
	 *
	 * @return array<string, array{title: string, span: int, expandable: bool}>
	 */
	function apcupsd_dashboard_card_meta($ups_id): array {
		$metrics = apcupsd_dashboard_metrics();

		$meta = [
			'identity' => ['title' => __('UPS Information', 'apcupsd'), 'span' => 4, 'expandable' => false],
		];

		foreach (apcupsd_dashboard_gauge_cards() as $card_key => $metric_key) {
			if (isset($metrics[$metric_key])) {
				$meta[$card_key] = ['title' => (string) $metrics[$metric_key]['label'], 'span' => 2, 'expandable' => false];
			}
		}

		$meta['power']   = ['title' => __('Input and Load', 'apcupsd'),       'span' => 2, 'expandable' => false];
		$meta['battery'] = ['title' => __('Battery and Runtime', 'apcupsd'),  'span' => 2, 'expandable' => false];
		$meta['events']  = ['title' => __('Events and Transfers', 'apcupsd'), 'span' => 4, 'expandable' => true];

		$ups = apcupsd_dashboard_ups($ups_id);
		$host_id = (int) ($ups['host_id'] ?? 0);

		foreach (apcupsd_dashboard_graphs($host_id) as $graph) {
			$meta['graph_' . (int) $graph['local_graph_id']] = [
				'title'      => (string) $graph['title_cache'],
				'span'       => 4,
				'expandable' => false,
			];
		}

		return $meta;
	}
}

if (!function_exists('apcupsd_dashboard_available_cards')) {
	/**
	 * The Dashboard card keys available for a UPS, in default order.
	 *
	 * @param int $ups_id The UPS the cards describe.
	 *
	 * @return string[]
	 */
	function apcupsd_dashboard_available_cards($ups_id): array {
		return array_keys(apcupsd_dashboard_card_meta($ups_id));
	}
}

if (!function_exists('apcupsd_dashboard_card_state')) {
	/**
	 * The header severity accent for a card, or '' for a neutral header. The
	 * load and battery cards reflect the UPS's live status.
	 *
	 * @param string $key    The card identifier.
	 * @param int    $ups_id The UPS the card describes.
	 *
	 * @return string One of 'up', 'recovering', 'down' or '' (neutral).
	 */
	function apcupsd_dashboard_card_state(string $key, $ups_id): string {
		$ups = apcupsd_dashboard_ups($ups_id);

		if (!cacti_sizeof($ups)) {
			return '';
		}

		$status = strtoupper((string) ($ups['ups_status'] ?? ''));

		if (strncmp($key, 'gauge_', 6) === 0) {
			$cards = apcupsd_dashboard_gauge_cards();

			if (isset($cards[$key])) {
				// Reuse the gauge body's resolved reading so the header accent and the
				// sweep share one value (and one RRD read).
				$reading = apcupsd_dashboard_metric_reading($cards[$key], $ups_id);

				if ($reading !== null) {
					return $reading['severity'];
				}
			}

			return '';
		}

		if ($key === 'power') {
			if (strpos($status, 'ONBATT') !== false) {
				return 'recovering';
			}

			$load = isset($ups['ups_load_percent']) && is_numeric($ups['ups_load_percent']) ? (float) $ups['ups_load_percent'] : null;

			return $load === null ? '' : apcupsd_dashboard_metric_severity('load_percent', $load);
		}

		if ($key === 'battery') {
			$charge = isset($ups['ups_battery_charge']) && is_numeric($ups['ups_battery_charge']) ? (float) $ups['ups_battery_charge'] : null;

			return $charge === null ? '' : apcupsd_dashboard_metric_severity('battery_charge', $charge);
		}

		return '';
	}
}

if (!function_exists('apcupsd_dashboard_status_pill')) {
	/**
	 * Render a coloured status pill for a UPS STATUS string (ONLINE, ONBATT,
	 * etc.).
	 *
	 * @param string $status The raw UPS status string.
	 *
	 * @return string The pill HTML.
	 */
	function apcupsd_dashboard_status_pill(string $status): string {
		$status = trim($status);
		$upper  = strtoupper($status);

		$class = 'apcupsdDashPill--unknown';

		if ($upper === '') {
			$status = __('Unknown', 'apcupsd');
		} elseif (strpos($upper, 'ONBATT') !== false || strpos($upper, 'LOWBATT') !== false) {
			$class = 'apcupsdDashPill--down';
		} elseif (strpos($upper, 'ONLINE') !== false) {
			$class = 'apcupsdDashPill--up';
		} elseif (strpos($upper, 'CAL') !== false || strpos($upper, 'TRIM') !== false || strpos($upper, 'BOOST') !== false) {
			$class = 'apcupsdDashPill--warn';
		}

		return '<span class="apcupsdDashPill ' . $class . '">' . html_escape($status) . '</span>';
	}
}

if (!function_exists('apcupsd_dashboard_card_body')) {
	/**
	 * Render the inner body HTML of a single Dashboard card for a UPS.
	 *
	 * @param string $key    The card identifier.
	 * @param int    $ups_id The UPS to render for.
	 *
	 * @return string The card body HTML (empty for an unknown key).
	 */
	function apcupsd_dashboard_card_body(string $key, $ups_id): string {
		global $config;

		$ups = apcupsd_dashboard_ups($ups_id);

		if (strncmp($key, 'graph_', 6) === 0) {
			$local_graph_id = (int) substr($key, 6);
			$host_id        = (int) ($ups['host_id'] ?? 0);

			$allowed = false;

			foreach (apcupsd_dashboard_graphs($host_id) as $graph) {
				if ((int) $graph['local_graph_id'] === $local_graph_id) {
					$allowed = true;

					break;
				}
			}

			if (!$allowed) {
				return '<p class="apcupsdDashEmpty">' . __esc('This graph is no longer available.', 'apcupsd') . '</p>';
			}

			apcupsd_dashboard_init_timespan();

			$start = get_current_graph_start();
			$end   = get_current_graph_end();

			$image = $config['url_path'] . 'graph_image.php?action=view&local_graph_id=' . $local_graph_id
				. '&rra_id=0&graph_start=' . $start . '&graph_end=' . $end
				. '&graph_height=180&graph_width=500';

			$view = $config['url_path'] . 'graph.php?action=view&local_graph_id=' . $local_graph_id . '&rra_id=0';

			return '<div class="apcupsdDashGraph">'
				. '<a href="' . html_escape($view) . '">'
				. '<img class="apcupsdDashGraphImg" loading="lazy" src="' . html_escape($image) . '" alt="' . __esc('UPS graph', 'apcupsd') . '">'
				. '</a></div>';
		}

		if (strncmp($key, 'gauge_', 6) === 0) {
			$cards = apcupsd_dashboard_gauge_cards();

			if (!isset($cards[$key])) {
				return '<p class="apcupsdDashEmpty">' . __esc('This gauge is no longer available.', 'apcupsd') . '</p>';
			}

			return apcupsd_dashboard_gauge_card_body($cards[$key], $ups_id);
		}

		ob_start();

		switch ($key) {
			case 'identity':
				if (!cacti_sizeof($ups)) {
					print '<p class="apcupsdDashEmpty">' . __esc('No information has been collected for this UPS yet.', 'apcupsd') . '</p>';

					break;
				}

				$kv = function(string $label, $value): void {
					$value = trim((string) $value);

					if ($value === '') {
						$value = __('N/A', 'apcupsd');
					}

					print '<tr><th>' . html_escape($label) . '</th><td>' . html_escape($value) . '</td></tr>';
				};

				$type = (int) ($ups['type_id'] ?? 0) === 1 ? __('APC UPSD', 'apcupsd') : __('SNMP', 'apcupsd');
				$port = (int) ($ups['type_id'] ?? 0) === 1 ? ($ups['port'] ?? '') : ($ups['snmp_port'] ?? '');
				$host = trim((string) ($ups['hostname'] ?? '')) . ($port !== '' ? ':' . $port : '');

				$volts = function($value): string {
					if ($value === null || !is_numeric($value)) {
						return '';
					}

					$decimals = floor((float) $value) == (float) $value ? 0 : 1;

					return number_format_i18n((float) $value, $decimals) . ' V';
				};

				$watts = function($value): string {
					return $value !== null && is_numeric($value) ? number_format_i18n((float) $value, 0) . ' W' : '';
				};

				print '<div class="apcupsdDashStatus">' . apcupsd_dashboard_status_pill((string) ($ups['ups_status'] ?? '')) . '</div>';
				print '<table class="apcupsdDashTable apcupsdDashKv"><tbody>';
				$kv(__('UPS Name', 'apcupsd'), $ups['name'] ?? '');
				$kv(__('Model', 'apcupsd'), $ups['ups_model'] ?? '');
				$kv(__('Serial Number', 'apcupsd'), $ups['ups_serialno'] ?? '');
				$kv(__('Firmware', 'apcupsd'), $ups['ups_firmware'] ?? '');
				$kv(__('Cable', 'apcupsd'), $ups['ups_cable'] ?? '');
				$kv(__('Driver', 'apcupsd'), $ups['ups_driver'] ?? '');
				$kv(__('Collector', 'apcupsd'), $type);
				$kv(__('Hostname', 'apcupsd'), $host);
				$kv(__('Nominal Power', 'apcupsd'), $watts($ups['ups_nominal_power'] ?? null));
				$kv(__('Nominal Input Voltage', 'apcupsd'), $volts($ups['ups_nominal_voltage'] ?? null));
				$kv(__('Nominal Battery Voltage', 'apcupsd'), $volts($ups['ups_nominal_batt_voltage'] ?? null));
				$kv(__('Output Voltage', 'apcupsd'), $volts($ups['ups_output_voltage'] ?? null));
				$kv(__('Recorded Line Min', 'apcupsd'), $volts($ups['ups_min_line_voltage'] ?? null));
				$kv(__('Recorded Line Max', 'apcupsd'), $volts($ups['ups_max_line_voltage'] ?? null));
				$kv(__('Battery Date', 'apcupsd'), $ups['ups_battery_date'] ?? '');
				$kv(__('Manufactured', 'apcupsd'), $ups['ups_mandate'] ?? '');
				$kv(__('Last Updated', 'apcupsd'), $ups['last_updated'] ?? '');
				print '</tbody></table>';

				break;
			case 'power':
				print apcupsd_dashboard_render_bullets('power', $ups_id);

				break;
			case 'battery':
				print apcupsd_dashboard_render_bullets('battery', $ups_id);

				break;
			case 'events':
				if (!cacti_sizeof($ups)) {
					print '<p class="apcupsdDashEmpty">' . __esc('No event data has been collected for this UPS yet.', 'apcupsd') . '</p>';

					break;
				}

				$kv = function(string $label, $value): void {
					$value = trim((string) $value);

					if ($value === '') {
						$value = __('N/A', 'apcupsd');
					}

					print '<tr><th>' . html_escape($label) . '</th><td>' . html_escape($value) . '</td></tr>';
				};

				print '<table class="apcupsdDashTable apcupsdDashKv"><tbody>';
				$kv(__('Last Transfer Reason', 'apcupsd'), $ups['ups_lastxfer'] ?? '');
				$kv(__('Number of Transfers', 'apcupsd'), isset($ups['ups_numxfers']) && $ups['ups_numxfers'] !== null ? number_format_i18n((int) $ups['ups_numxfers'], 0) : '');
				$kv(__('Time on Battery', 'apcupsd'), isset($ups['ups_tonbatt']) && $ups['ups_tonbatt'] !== null ? number_format_i18n((int) $ups['ups_tonbatt'], 0) . ' ' . __('sec', 'apcupsd') : '');
				$kv(__('Cumulative on Battery', 'apcupsd'), isset($ups['ups_cumonbatt']) && $ups['ups_cumonbatt'] !== null ? number_format_i18n((int) $ups['ups_cumonbatt'], 0) . ' ' . __('sec', 'apcupsd') : '');
				$kv(__('Last Self Test', 'apcupsd'), $ups['ups_laststest'] ?? '');
				$kv(__('Self Test Result', 'apcupsd'), $ups['ups_selftest'] ?? '');
				$kv(__('Last On Battery', 'apcupsd'), $ups['ups_xonbatt'] ?? '');
				$kv(__('Last Off Battery', 'apcupsd'), isset($ups['ups_xoffbatt']) && $ups['ups_xoffbatt'] !== null ? number_format_i18n((int) $ups['ups_xoffbatt'], 0) : '');
				print '</tbody></table>';

				break;
		}

		return (string) ob_get_clean();
	}
}

if (!function_exists('apcupsd_dashboard_card_chrome')) {
	/**
	 * Wrap a card body in the shared panel/card chrome (section, drag handle,
	 * title and the expand/collapse/maximize/refresh/remove toolbar). Shared by
	 * the per-UPS and fleet dashboards.
	 *
	 * @param string $key        The card identifier (data-card).
	 * @param string $title      The card title.
	 * @param int    $span       The column span (out of 8).
	 * @param bool   $expandable Whether the card clips with a "show more" control.
	 * @param bool   $expanded   Whether it opens expanded.
	 * @param string $state      Header severity accent ('up'/'recovering'/'down'/'').
	 * @param string $body       The card body HTML.
	 *
	 * @return string The card HTML.
	 */
	function apcupsd_dashboard_card_chrome(string $key, string $title, int $span, bool $expandable, bool $expanded, string $state, string $body): string {
		$tools = '';

		if ($expandable) {
			$tools .= '<button type="button" class="apcupsdDashCardTool" data-tool="expand" aria-label="' . __esc('Show more', 'apcupsd') . '" title="' . __esc('Show more', 'apcupsd') . '"><i class="fas fa-chevron-down" aria-hidden="true"></i></button>';
			$tools .= '<button type="button" class="apcupsdDashCardTool" data-tool="collapse" aria-label="' . __esc('Show less', 'apcupsd') . '" title="' . __esc('Show less', 'apcupsd') . '"><i class="fas fa-chevron-up" aria-hidden="true"></i></button>';
		}

		$tools .= '<button type="button" class="apcupsdDashCardTool" data-tool="maximize" aria-label="' . __esc('Open in a dialog', 'apcupsd') . '" title="' . __esc('Open in a dialog', 'apcupsd') . '"><i class="fas fa-window-maximize" aria-hidden="true"></i></button>';
		$tools .= '<button type="button" class="apcupsdDashCardTool" data-tool="refresh" aria-label="' . __esc('Refresh', 'apcupsd') . '" title="' . __esc('Refresh', 'apcupsd') . '"><i class="fas fa-sync-alt" aria-hidden="true"></i></button>';
		$tools .= '<button type="button" class="apcupsdDashCardTool" data-tool="remove" aria-label="' . __esc('Remove from page', 'apcupsd') . '" title="' . __esc('Remove from page', 'apcupsd') . '"><i class="fas fa-times" aria-hidden="true"></i></button>';

		$classes = 'apcupsdDashCard apcupsdDashSpan' . $span;

		if ($expandable) {
			$classes .= ' apcupsdDashCardExpandable';
		}

		if ($expanded) {
			$classes .= ' apcupsdDashCardExpanded';
		}

		$header_class = 'apcupsdDashCardHeader' . ($state !== '' ? ' apcupsdDashCardHeader--' . $state : '');

		return '<section class="' . $classes . '" data-card="' . html_escape($key) . '">'
			. '<header class="' . $header_class . '">'
			. '<button type="button" class="apcupsdDashCardDrag" aria-label="' . __esc('Drag to reorder card', 'apcupsd') . '"><i class="fas fa-bars" aria-hidden="true"></i></button>'
			. '<h2 class="apcupsdDashCardTitle">' . html_escape($title) . '</h2>'
			. '<span class="apcupsdDashCardTools">' . $tools . '</span>'
			. '</header>'
			. '<div class="apcupsdDashCardBody">' . $body . '</div>'
			. '</section>';
	}
}

if (!function_exists('apcupsd_dashboard_render_card')) {
	/**
	 * Render a complete Dashboard card (section, header toolbar and body) for a
	 * UPS.
	 *
	 * @param string $key      The card identifier.
	 * @param int    $ups_id   The UPS to render for.
	 * @param bool   $expanded Whether the card opens in its expanded state.
	 *
	 * @return string The card HTML, or '' for an unknown key.
	 */
	function apcupsd_dashboard_render_card(string $key, $ups_id, bool $expanded = false): string {
		$meta = apcupsd_dashboard_card_meta($ups_id);

		if (!isset($meta[$key])) {
			return '';
		}

		$def = $meta[$key];

		return apcupsd_dashboard_card_chrome(
			$key,
			(string) $def['title'],
			(int) $def['span'],
			(bool) $def['expandable'],
			$expanded,
			apcupsd_dashboard_card_state($key, $ups_id),
			apcupsd_dashboard_card_body($key, $ups_id)
		);
	}
}

if (!function_exists('apcupsd_dashboard_layout_key')) {
	/**
	 * The per-UPS settings_user key the Dashboard layout is stored under. Each UPS
	 * uses its own row so no single value can exceed Cacti 1.2.x's 4096-character
	 * settings_user.value limit.
	 *
	 * @param int $ups_id The UPS id.
	 *
	 * @return string The settings key.
	 */
	function apcupsd_dashboard_layout_key($ups_id): string {
		return 'apcupsd_dashboard_layout_' . (int) $ups_id;
	}
}

if (!function_exists('apcupsd_dashboard_layout_read')) {
	/**
	 * Read a UPS's stored layout document, migrating a layout authored under the
	 * pre-release combined map when the per-UPS row is not yet present.
	 *
	 * @param int $ups_id The UPS id.
	 *
	 * @return array|null The {order, expanded} document, or null when none saved.
	 */
	function apcupsd_dashboard_layout_read($ups_id): ?array {
		$saved = json_decode((string) read_user_setting(apcupsd_dashboard_layout_key($ups_id), '', true), true);

		if (is_array($saved) && isset($saved['order']) && is_array($saved['order'])) {
			return $saved;
		}

		$all = json_decode((string) read_user_setting('apcupsd_dashboard_layout', '', true), true);

		if (is_array($all) && isset($all[(string) $ups_id]) && is_array($all[(string) $ups_id])) {
			return $all[(string) $ups_id];
		}

		return null;
	}
}

if (!function_exists('apcupsd_dashboard_layout')) {
	/**
	 * The current user's saved layout for a UPS: the present cards in order plus
	 * their expanded state. With no saved layout every card is present in the
	 * default order; cards absent from a saved order live in the "Add" catalogue.
	 *
	 * @param int $ups_id The UPS the layout is rendered for.
	 *
	 * @return array{order: string[], expanded: array<string, bool>}
	 */
	function apcupsd_dashboard_layout($ups_id): array {
		$available = apcupsd_dashboard_available_cards($ups_id);
		$saved     = apcupsd_dashboard_layout_read($ups_id);

		if (!is_array($saved) || !isset($saved['order']) || !is_array($saved['order'])) {
			return ['order' => $available, 'expanded' => []];
		}

		$order = [];

		foreach ($saved['order'] as $key) {
			// Migrate the retired combined 'gauges' card to the per-metric gauge cards
			// so existing layouts surface the new gauges where the old card sat.
			if ($key === 'gauges') {
				foreach (array_keys(apcupsd_dashboard_gauge_cards()) as $gauge_key) {
					if (in_array($gauge_key, $available, true) && !in_array($gauge_key, $order, true)) {
						$order[] = $gauge_key;
					}
				}

				continue;
			}

			if (is_string($key) && in_array($key, $available, true) && !in_array($key, $order, true)) {
				$order[] = $key;
			}
		}

		$expanded = [];

		if (isset($saved['expanded']) && is_array($saved['expanded'])) {
			foreach ($saved['expanded'] as $key => $on) {
				if ($on && in_array($key, $available, true)) {
					$expanded[(string) $key] = true;
				}
			}
		}

		return ['order' => $order, 'expanded' => $expanded];
	}
}

if (!function_exists('apcupsd_dashboard_layout_save')) {
	/**
	 * Persist the posted layout (card order and expanded state) for a UPS for the
	 * current user, validated to the UPS's known cards and merged into the user's
	 * per-UPS layout map.
	 *
	 * @return string A JSON status document.
	 */
	function apcupsd_dashboard_layout_save(): string {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !function_exists('csrf_check') || !csrf_check(false)) {
			return (string) json_encode(['error' => __('Invalid request. Please try again.', 'apcupsd')]);
		}

		$ups_id = apcupsd_dashboard_resolve_ups();

		if ($ups_id === 0) {
			return (string) json_encode(['error' => __('No UPS selected.', 'apcupsd')]);
		}

		$available = apcupsd_dashboard_available_cards($ups_id);
		$posted    = json_decode(isset_request_var('layout') ? (string) get_nfilter_request_var('layout') : '', true);

		$order    = [];
		$expanded = [];

		if (is_array($posted)) {
			if (isset($posted['order']) && is_array($posted['order'])) {
				foreach ($posted['order'] as $key) {
					if (is_string($key) && in_array($key, $available, true) && !in_array($key, $order, true)) {
						$order[] = $key;
					}
				}
			}

			if (isset($posted['expanded']) && is_array($posted['expanded'])) {
				foreach ($posted['expanded'] as $key => $on) {
					if ($on && is_string($key) && in_array($key, $available, true)) {
						$expanded[$key] = true;
					}
				}
			}
		}

		$encoded = json_encode(['order' => $order, 'expanded' => $expanded]);

		// Cacti 1.2.x caps settings_user.value at 4096 characters; refuse rather than
		// silently truncate a per-UPS layout and lose the user's saved preferences.
		if ($encoded === false || strlen($encoded) > 4096) {
			return (string) json_encode(['error' => __('This dashboard has too many cards to save its layout.', 'apcupsd')]);
		}

		set_user_setting(apcupsd_dashboard_layout_key($ups_id), $encoded);

		return (string) json_encode(['ok' => true]);
	}
}

if (!function_exists('apcupsd_dashboard_refresh_save')) {
	/**
	 * Persist the posted auto-refresh interval for the current user. Accepts only
	 * a CSRF-validated POST carrying a value the refresh picker offers (0 = off,
	 * or a key of $page_refresh_interval), so a drive-by GET cannot force a
	 * reload loop.
	 *
	 * @return string A JSON status document.
	 *
	 * @global array $page_refresh_interval Cacti's allowed refresh intervals.
	 */
	function apcupsd_dashboard_refresh_save(): string {
		global $page_refresh_interval;

		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !function_exists('csrf_check') || !csrf_check(false)) {
			return (string) json_encode(['error' => __('Invalid request. Please try again.', 'apcupsd')]);
		}

		$posted    = isset_request_var('refresh') ? (string) get_nfilter_request_var('refresh') : '';
		$intervals = (is_array($page_refresh_interval) && cacti_sizeof($page_refresh_interval)) ? $page_refresh_interval : [30 => '', 60 => '', 300 => ''];
		$allowed   = array_map('intval', array_keys($intervals));
		$allowed[] = 0;

		if (!preg_match('/^[0-9]+$/', $posted) || !in_array((int) $posted, $allowed, true)) {
			return (string) json_encode(['error' => __('Invalid refresh interval.', 'apcupsd')]);
		}

		set_user_setting('apcupsd_dashboard_refresh', (int) $posted);

		return (string) json_encode(['ok' => true]);
	}
}

if (!function_exists('apcupsd_dashboard_card_ajax')) {
	/**
	 * AJAX: render a single Dashboard card, used when adding one from the
	 * catalogue or refreshing one in place.
	 *
	 * @return string A JSON document with the card key and rendered HTML.
	 */
	function apcupsd_dashboard_card_ajax(): string {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !function_exists('csrf_check') || !csrf_check(false)) {
			return (string) json_encode(['error' => __('Invalid request. Please try again.', 'apcupsd')]);
		}

		$ups_id = apcupsd_dashboard_resolve_ups();

		if ($ups_id === 0) {
			return (string) json_encode(['error' => __('No UPS selected.', 'apcupsd')]);
		}

		$key = isset_request_var('card') ? (string) get_nfilter_request_var('card') : '';

		if (!in_array($key, apcupsd_dashboard_available_cards($ups_id), true)) {
			return (string) json_encode(['error' => __('Unknown card.', 'apcupsd')]);
		}

		$expanded = isset_request_var('expanded') && get_nfilter_request_var('expanded') === '1';

		return (string) json_encode(['card' => $key, 'html' => apcupsd_dashboard_render_card($key, $ups_id, $expanded)]);
	}
}

if (!function_exists('apcupsd_dashboard')) {
	/**
	 * Render the per-UPS Dashboard view: a draggable grid of panel cards bringing
	 * together the UPS's identity, min/avg/max bullet charts (from the UPS's RRD
	 * files over the selected Cacti timespan) and every Cacti graph for the UPS.
	 * Card order, expanded state, the selected UPS and the auto-refresh interval
	 * persist per user. Called from upses.php's dispatcher when action=view.
	 *
	 * @return void
	 *
	 * @global array $config                Cacti global configuration array.
	 * @global array $page_refresh_interval Cacti's standard refresh-interval list.
	 */
	function apcupsd_dashboard(): void {
		global $config, $page_refresh_interval;

		$ups_id = apcupsd_dashboard_resolve_ups();

		if ($ups_id === 0) {
			apcupsd_dashboard_print_css();
			apcupsd_dashboard_tabs('view');
			html_start_box(__('UPS Dashboard', 'apcupsd'), '100%', false, 3, 'center', '');
			print '<tr class="even"><td class="textArea"><p>' . __esc('Open a UPS Dashboard from the Manage UPSes list by clicking the UPS\'s dashboard glyph. The Dashboard remembers the last UPS you opened.', 'apcupsd') . '</p></td></tr>';
			html_end_box();

			return;
		}

		apcupsd_dashboard_print_css();
		apcupsd_dashboard_tabs('view');

		apcupsd_dashboard_init_timespan();

		$current_refresh = (int) read_user_setting('apcupsd_dashboard_refresh', 0);

		if ($current_refresh > 0 && function_exists('set_page_refresh')) {
			set_page_refresh([
				'seconds' => $current_refresh,
				'page'    => $config['url_path'] . 'plugins/apcupsd/upses.php?action=view&id=' . $ups_id . '&header=false',
				'logout'  => 'false'
			]);
		}

		print '<script type="text/javascript" ' . plugin_apcupsd_csp_nonce() . ' src="' . $config['url_path'] . 'plugins/apcupsd/js/apcupsd_dashboard.js?v=' . filemtime($config['base_path'] . '/plugins/apcupsd/js/apcupsd_dashboard.js') . '"></script>';

		$ups  = apcupsd_dashboard_ups($ups_id);
		$meta = apcupsd_dashboard_card_meta($ups_id);
		$layout = apcupsd_dashboard_layout($ups_id);
		$absent = array_values(array_diff(apcupsd_dashboard_available_cards($ups_id), $layout['order']));

		if (empty($page_refresh_interval) || !is_array($page_refresh_interval)) {
			$page_refresh_interval = [0 => __('No Refresh', 'apcupsd'), 30 => __('30 Seconds', 'apcupsd'), 60 => __('1 Minute', 'apcupsd'), 300 => __('5 Minutes', 'apcupsd')];
		}

		print '<div class="apcupsdDashToolbar">';

		print '<label class="apcupsdDashToolbarLabel" for="apcupsd_dashboard_ups">' . __esc('UPS', 'apcupsd') . '</label>';
		print '<select id="apcupsd_dashboard_ups" class="apcupsdDashUps">';

		$upses = db_fetch_assoc("SELECT id, name
			FROM apcupsd_ups
			WHERE name != ''
			ORDER BY name");

		if (cacti_sizeof($upses)) {
			foreach ($upses as $row) {
				print '<option value="' . (int) $row['id'] . '"' . ((int) $row['id'] === $ups_id ? ' selected' : '') . '>' . html_escape($row['name']) . '</option>';
			}
		}

		print '</select>';

		print '<label class="apcupsdDashToolbarLabel" for="apcupsd_dashboard_timespan">' . __esc('Timespan', 'apcupsd') . '</label>';
		print '<select id="apcupsd_dashboard_timespan" class="apcupsdDashTimespan">';

		global $graph_timespans;
		$timespans = is_array($graph_timespans) ? $graph_timespans : [];

		$current_span = isset($_SESSION['sess_current_timespan']) ? (int) $_SESSION['sess_current_timespan'] : 0;

		foreach ($timespans as $key => $display_text) {
			print '<option value="' . (int) $key . '"' . ($current_span === (int) $key ? ' selected' : '') . '>' . html_escape($display_text) . '</option>';
		}

		print '</select>';

		print '<label class="apcupsdDashToolbarLabel" for="apcupsd_dashboard_add">' . __esc('Add card', 'apcupsd') . '</label>';
		print '<select id="apcupsd_dashboard_add" class="apcupsdDashAdd"><option value="">' . __esc('Add a card…', 'apcupsd') . '</option>';

		foreach ($absent as $key) {
			print '<option value="' . html_escape($key) . '">' . html_escape($meta[$key]['title']) . '</option>';
		}

		print '</select>';

		print '<div class="apcupsdDashRefresh">';
		print '<label class="apcupsdDashToolbarLabel" for="apcupsd_dashboard_refresh">' . __esc('Refresh', 'apcupsd') . '</label>';
		print '<select id="apcupsd_dashboard_refresh" class="apcupsdDashRefreshInterval">';
		print '<option value="0"' . ($current_refresh == 0 ? ' selected' : '') . '>' . __esc('No Refresh', 'apcupsd') . '</option>';

		foreach ($page_refresh_interval as $seconds => $display_text) {
			if ((int) $seconds === 0) {
				continue;
			}

			print '<option value="' . (int) $seconds . '"' . ($current_refresh == $seconds ? ' selected' : '') . '>' . html_escape($display_text) . '</option>';
		}

		print '</select>';
		print '<button type="button" id="apcupsd_dashboard_refresh_now" class="apcupsdDashCardTool apcupsdDashRefreshNow" aria-label="' . __esc('Refresh', 'apcupsd') . '" title="' . __esc('Refresh', 'apcupsd') . '"><i class="fas fa-sync-alt" aria-hidden="true"></i></button>';
		print '</div>';
		print '<span class="apcupsdDashSaveStatus" role="status" aria-live="polite" hidden></span>';
		print '</div>';

		print '<div id="apcupsd_dashboard" class="apcupsdDashGrid" data-ups="' . $ups_id . '"'
			. ' data-scope-param="id" data-scope="' . $ups_id . '"'
			. ' data-reload="' . html_escape('upses.php?action=view&id=' . $ups_id) . '">';

		foreach ($layout['order'] as $key) {
			print apcupsd_dashboard_render_card($key, $ups_id, !empty($layout['expanded'][$key]));
		}

		print '</div>';

		print '<div id="apcupsd_dashboard_dialog" class="apcupsdDashDialog" style="display:none"></div>';

		$catalog = [];

		foreach (apcupsd_dashboard_available_cards($ups_id) as $key) {
			$catalog[$key] = $meta[$key]['title'];
		}

		// JSON_HEX_* prevents a card title from breaking out of the inline <script>.
		print '<script type="text/javascript" ' . plugin_apcupsd_csp_nonce() . '>var apcupsdDashCatalog = ' . json_encode($catalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '; initApcupsdDashboard();</script>';
	}
}

/* ============================================================================
 * Fleet Dashboard: a high-level overview across every managed UPS, with the
 * same draggable panel/card chrome as the per-UPS Dashboard. Each UPS row in
 * the overview carries a drilldown glyph into its per-UPS Dashboard.
 * ========================================================================== */

if (!function_exists('apcupsd_dashboard_tabs')) {
	/**
	 * Render the tab bar linking the Fleet Dashboard, the UPSes list and (once a
	 * UPS has been opened) its per-UPS Dashboard.
	 *
	 * @param string $current The current tab key ('dashboard', 'list' or 'view').
	 *
	 * @return void
	 */
	function apcupsd_dashboard_tabs(string $current): void {
		$tabs = [
			'dashboard' => ['label' => __('Fleet Dashboard', 'apcupsd'), 'url' => 'upses.php?action=dashboard'],
			'list'      => ['label' => __('UPSes', 'apcupsd'),           'url' => 'upses.php'],
		];

		$selected_ups = (int) read_user_setting('apcupsd_dashboard_ups', 0);

		if ($current === 'view' || apcupsd_dashboard_ups_exists($selected_ups)) {
			$tabs['view'] = [
				'label' => __('UPS Dashboard', 'apcupsd'),
				'url'   => 'upses.php?action=view' . (apcupsd_dashboard_ups_exists($selected_ups) ? '&id=' . $selected_ups : ''),
			];
		}

		print "<div class='tabs'><nav><ul>";

		foreach ($tabs as $key => $tab) {
			print "<li><a class='pic" . ($key === $current ? " selected'" : "'") . " href='" . html_escape($tab['url']) . "'>" . html_escape($tab['label']) . '</a></li>';
		}

		print '</ul></nav></div>';
	}
}

if (!function_exists('apcupsd_fleet_stats')) {
	/**
	 * Memoized fleet aggregates: UPS counts by status (online/on-battery/other/
	 * disabled) and the min/avg/max of load, battery charge and runtime across
	 * the enabled UPSes.
	 *
	 * @return array The aggregate row.
	 */
	function apcupsd_fleet_stats(): array {
		static $cache = null;

		if ($cache === null) {
			$row = db_fetch_row("SELECT
				COUNT(*) AS total,
				SUM(CASE WHEN ups.enabled = 'on' THEN 1 ELSE 0 END) AS enabled,
				SUM(CASE WHEN ups.enabled <> 'on' THEN 1 ELSE 0 END) AS disabled,
				SUM(CASE WHEN ups.enabled = 'on' AND stats.ups_status LIKE '%ONLINE%' THEN 1 ELSE 0 END) AS online,
				SUM(CASE WHEN ups.enabled = 'on' AND stats.ups_status LIKE '%ONBATT%' THEN 1 ELSE 0 END) AS onbatt,
				MIN(CASE WHEN ups.enabled = 'on' THEN stats.ups_load_percent END) AS minLoad,
				AVG(CASE WHEN ups.enabled = 'on' THEN stats.ups_load_percent END) AS avgLoad,
				MAX(CASE WHEN ups.enabled = 'on' THEN stats.ups_load_percent END) AS maxLoad,
				MIN(CASE WHEN ups.enabled = 'on' THEN stats.ups_battery_charge END) AS minCharge,
				AVG(CASE WHEN ups.enabled = 'on' THEN stats.ups_battery_charge END) AS avgCharge,
				MAX(CASE WHEN ups.enabled = 'on' THEN stats.ups_battery_charge END) AS maxCharge,
				MIN(CASE WHEN ups.enabled = 'on' THEN stats.ups_timeleft END) AS minTime,
				AVG(CASE WHEN ups.enabled = 'on' THEN stats.ups_timeleft END) AS avgTime,
				MAX(CASE WHEN ups.enabled = 'on' THEN stats.ups_timeleft END) AS maxTime
				FROM apcupsd_ups AS ups
				LEFT JOIN apcupsd_ups_stats AS stats ON stats.ups_id = ups.id");

			$cache = is_array($row) ? $row : [];
		}

		return $cache;
	}
}

if (!function_exists('apcupsd_fleet_card_meta')) {
	/**
	 * The Fleet Dashboard card registry: title, column span (out of 8) and
	 * whether the card clips with a "show more" control.
	 *
	 * @return array<string, array{title: string, span: int, expandable: bool}>
	 */
	function apcupsd_fleet_card_meta(): array {
		return [
			'overview' => ['title' => __('Fleet Overview', 'apcupsd'),    'span' => 4, 'expandable' => false],
			'status'   => ['title' => __('Status Distribution', 'apcupsd'), 'span' => 4, 'expandable' => false],
			'readings' => ['title' => __('Fleet Readings', 'apcupsd'),     'span' => 4, 'expandable' => false],
			'gauges'   => ['title' => __('Fleet Gauges', 'apcupsd'),       'span' => 4, 'expandable' => false],
			'upses'    => ['title' => __('UPSes', 'apcupsd'),              'span' => 8, 'expandable' => true],
		];
	}
}

if (!function_exists('apcupsd_fleet_available_cards')) {
	/**
	 * The Fleet Dashboard card keys, in default order.
	 *
	 * @return string[]
	 */
	function apcupsd_fleet_available_cards(): array {
		return array_keys(apcupsd_fleet_card_meta());
	}
}

if (!function_exists('apcupsd_fleet_tile')) {
	/**
	 * Render a single fleet stat tile.
	 *
	 * @param string $value    The already-formatted value.
	 * @param string $label    The tile label.
	 * @param string $modifier An optional severity modifier ('up'/'rec'/'down').
	 *
	 * @return string The tile HTML.
	 */
	function apcupsd_fleet_tile(string $value, string $label, string $modifier = ''): string {
		return '<div class="apcupsdFleetTile' . ($modifier !== '' ? ' apcupsdFleetTile--' . $modifier : '') . '">'
			. '<span class="apcupsdFleetTileValue">' . html_escape($value) . '</span>'
			. '<span class="apcupsdFleetTileLabel">' . html_escape($label) . '</span>'
			. '</div>';
	}
}

if (!function_exists('apcupsd_fleet_rrd_stats')) {
	/**
	 * Aggregate a reading across every enabled, linked UPS from their RRD files
	 * over the selected timespan: the minimum of the per-UPS minimums, the mean of
	 * the per-UPS averages and the maximum of the per-UPS maximums.
	 *
	 * The result is held in a short-lived per-user session cache keyed by metric,
	 * window and fleet scope, so the separate "Refresh all" POSTs for the Fleet
	 * Readings and Fleet Gauges cards do not each re-read the same RRD files.
	 *
	 * @param string $metric_key A key from apcupsd_dashboard_metrics().
	 *
	 * @return array{min: float|null, avg: float|null, max: float|null}
	 */
	function apcupsd_fleet_rrd_stats(string $metric_key): array {
		static $cache = [];

		if (array_key_exists($metric_key, $cache)) {
			return $cache[$metric_key];
		}

		$metrics = apcupsd_dashboard_metrics();

		if (!isset($metrics[$metric_key])) {
			return $cache[$metric_key] = ['min' => null, 'avg' => null, 'max' => null];
		}

		apcupsd_dashboard_init_timespan();

		$start   = get_current_graph_start();
		$end     = get_current_graph_end();
		$aliases = $metrics[$metric_key]['aliases'];

		// Best-effort cross-request reuse: the Fleet Readings and Fleet Gauges cards
		// refresh as separate POSTs that request the same metrics over the same
		// window, which the request-local static above cannot span. The window is
		// quantized to the minute and the key carries a fleet-scope count; entries
		// expire quickly so fresh polls still surface. If the session is read-only
		// in this context the write is simply dropped and the value is recomputed.
		$scope = (int) db_fetch_cell("SELECT COUNT(*)
			FROM apcupsd_ups
			WHERE enabled = 'on' AND host_id > 0");

		$sig = $metric_key . '|' . (int) floor((int) $start / 60) . '|' . (int) floor((int) $end / 60) . '|' . $scope;

		if (isset($_SESSION['apcupsd_fleet_rrd_cache'][$sig]['stats'], $_SESSION['apcupsd_fleet_rrd_cache'][$sig]['expires'])
			&& (int) $_SESSION['apcupsd_fleet_rrd_cache'][$sig]['expires'] > time()) {
			return $cache[$metric_key] = $_SESSION['apcupsd_fleet_rrd_cache'][$sig]['stats'];
		}

		$hosts = db_fetch_assoc("SELECT DISTINCT host_id
			FROM apcupsd_ups
			WHERE enabled = 'on' AND host_id > 0");

		$mins = $avgs = $maxs = [];

		if (cacti_sizeof($hosts)) {
			foreach ($hosts as $host) {
				list($local_data_id, $ds_name) = apcupsd_dashboard_find_data_source((int) $host['host_id'], $aliases);

				if ($local_data_id <= 0) {
					continue;
				}

				$min = apcupsd_dashboard_rrd_value($local_data_id, $ds_name, $start, $end, 'MIN');
				$avg = apcupsd_dashboard_rrd_value($local_data_id, $ds_name, $start, $end, 'AVERAGE');
				$max = apcupsd_dashboard_rrd_value($local_data_id, $ds_name, $start, $end, 'MAX');

				if ($min !== null) {
					$mins[] = $min;
				}

				if ($avg !== null) {
					$avgs[] = $avg;
				}

				if ($max !== null) {
					$maxs[] = $max;
				}
			}
		}

		$result = [
			'min' => cacti_sizeof($mins) ? min($mins) : null,
			'avg' => cacti_sizeof($avgs) ? array_sum($avgs) / count($avgs) : null,
			'max' => cacti_sizeof($maxs) ? max($maxs) : null,
		];

		// Prune expired entries before storing so the cache cannot grow unbounded.
		$kept = [];

		foreach ((array) ($_SESSION['apcupsd_fleet_rrd_cache'] ?? []) as $key => $entry) {
			if (is_array($entry) && (int) ($entry['expires'] ?? 0) > time()) {
				$kept[$key] = $entry;
			}
		}

		$kept[$sig] = ['expires' => time() + 30, 'stats' => $result];
		$_SESSION['apcupsd_fleet_rrd_cache'] = $kept;

		return $cache[$metric_key] = $result;
	}
}

if (!function_exists('apcupsd_fleet_readings')) {
	/**
	 * The fleet load, battery-charge and runtime aggregates shared by the reading
	 * and gauge cards: min/avg/max pulled from every enabled UPS's RRD files over
	 * the selected timespan, with each metric falling back to its latest-polled
	 * aggregate independently when that metric has no RRD history.
	 *
	 * @return array<string, array{min: float|null, avg: float|null, max: float|null}>
	 */
	function apcupsd_fleet_readings(): array {
		$stats = apcupsd_fleet_stats();

		$num = function($value): ?float {
			return $value !== null && is_numeric($value) ? (float) $value : null;
		};

		// Each metric falls back to its latest-polled aggregate independently, so a
		// metric with RRD history is never blanked just because another metric lacks
		// any.
		$resolve = function(string $metric_key, string $min_col, string $avg_col, string $max_col) use ($stats, $num): array {
			$rrd = apcupsd_fleet_rrd_stats($metric_key);

			if ($rrd['avg'] !== null) {
				return $rrd;
			}

			return [
				'min' => $num($stats[$min_col] ?? null),
				'avg' => $num($stats[$avg_col] ?? null),
				'max' => $num($stats[$max_col] ?? null),
			];
		};

		return [
			'load'   => $resolve('load_percent',   'minLoad',   'avgLoad',   'maxLoad'),
			'charge' => $resolve('battery_charge', 'minCharge', 'avgCharge', 'maxCharge'),
			'time'   => $resolve('timeleft',       'minTime',   'avgTime',   'maxTime'),
		];
	}
}

if (!function_exists('apcupsd_fleet_card_body')) {
	/**
	 * Render the inner body HTML of a single Fleet Dashboard card.
	 *
	 * @param string $key The card identifier.
	 *
	 * @return string The card body HTML (empty for an unknown key).
	 */
	function apcupsd_fleet_card_body(string $key): string {
		$stats = apcupsd_fleet_stats();

		$total    = (int) ($stats['total'] ?? 0);
		$enabled  = (int) ($stats['enabled'] ?? 0);
		$disabled = (int) ($stats['disabled'] ?? 0);
		$online   = (int) ($stats['online'] ?? 0);
		$onbatt   = (int) ($stats['onbatt'] ?? 0);
		$other    = max(0, $enabled - $online - $onbatt);

		ob_start();

		switch ($key) {
			case 'overview':
				if ($total === 0) {
					print '<p class="apcupsdDashEmpty">' . __esc('No UPSes are configured yet.', 'apcupsd') . '</p>';

					break;
				}

				print '<div class="apcupsdFleetTiles">';
				print apcupsd_fleet_tile(number_format_i18n($total, 0), __('Total', 'apcupsd'));
				print apcupsd_fleet_tile(number_format_i18n($online, 0), __('Online', 'apcupsd'), $online > 0 ? 'up' : '');
				print apcupsd_fleet_tile(number_format_i18n($onbatt, 0), __('On Battery', 'apcupsd'), $onbatt > 0 ? 'down' : '');
				print apcupsd_fleet_tile(number_format_i18n($other, 0), __('Other', 'apcupsd'), $other > 0 ? 'rec' : '');
				print apcupsd_fleet_tile(number_format_i18n($disabled, 0), __('Disabled', 'apcupsd'));
				print '</div>';

				break;
			case 'status':
				$segments = [
					['count' => $online,   'class' => 'up',   'label' => __('Online', 'apcupsd')],
					['count' => $onbatt,   'class' => 'down', 'label' => __('On Battery', 'apcupsd')],
					['count' => $other,    'class' => 'rec',  'label' => __('Other', 'apcupsd')],
					['count' => $disabled, 'class' => 'dis',  'label' => __('Disabled', 'apcupsd')],
				];

				$bar_total = array_sum(array_column($segments, 'count'));

				if ($bar_total === 0) {
					print '<p class="apcupsdDashEmpty">' . __esc('No UPSes are configured yet.', 'apcupsd') . '</p>';

					break;
				}

				print '<div class="apcupsdStackBar">';

				foreach ($segments as $segment) {
					if ($segment['count'] > 0) {
						$width = $segment['count'] / $bar_total * 100;
						print '<div class="apcupsdStackSeg apcupsdStackSeg--' . $segment['class'] . '" style="width:' . number_format($width, 2, '.', '') . '%" title="' . html_escape($segment['label'] . ': ' . $segment['count']) . '"></div>';
					}
				}

				print '</div>';
				print '<div class="apcupsdStackLegend">';

				foreach ($segments as $segment) {
					print '<span><span class="apcupsdStackChip apcupsdStackSeg--' . $segment['class'] . '"></span>' . html_escape($segment['label'] . ' (' . $segment['count'] . ')') . '</span>';
				}

				print '</div>';

				break;
			case 'readings':
				if ($enabled === 0) {
					print '<p class="apcupsdDashEmpty">' . __esc('No enabled UPSes are reporting readings yet.', 'apcupsd') . '</p>';

					break;
				}

				$readings = apcupsd_fleet_readings();
				$load     = $readings['load'];
				$charge   = $readings['charge'];
				$time     = $readings['time'];

				print apcupsd_dashboard_bullet(__('Load', 'apcupsd'), null, $load['min'], $load['avg'], $load['max'], 100.0, '%', apcupsd_dashboard_metric_severity('load_percent', $load['avg']));
				print apcupsd_dashboard_bullet(__('Battery Charge', 'apcupsd'), null, $charge['min'], $charge['avg'], $charge['max'], 100.0, '%', apcupsd_dashboard_metric_severity('battery_charge', $charge['avg']));

				$max_time   = $time['max'];
				$time_scale = max(30.0, $max_time !== null ? $max_time * 1.1 : 30.0);
				print apcupsd_dashboard_bullet(__('Time Left', 'apcupsd'), null, $time['min'], $time['avg'], $max_time, $time_scale, ' min', apcupsd_dashboard_metric_severity('timeleft', $time['avg']));

				break;
			case 'gauges':
				if ($enabled === 0) {
					print '<p class="apcupsdDashEmpty">' . __esc('No enabled UPSes are reporting readings yet.', 'apcupsd') . '</p>';

					break;
				}

				$readings = apcupsd_fleet_readings();

				$gauges = [
					['metric' => 'load_percent',   'label' => __('Load', 'apcupsd'),           'reading' => $readings['load'],   'unit' => '%',    'scale' => 100.0],
					['metric' => 'battery_charge', 'label' => __('Battery Charge', 'apcupsd'), 'reading' => $readings['charge'], 'unit' => '%',    'scale' => 100.0],
					['metric' => 'timeleft',       'label' => __('Time Left', 'apcupsd'),      'reading' => $readings['time'],   'unit' => ' min', 'scale' => max(30.0, $readings['time']['max'] !== null ? $readings['time']['max'] * 1.1 : 30.0)],
				];

				print '<div class="apcupsdGaugeGrid">';

				foreach ($gauges as $gauge) {
					$reading = $gauge['reading'];
					$center  = apcupsd_dashboard_format_value($reading['avg'], $gauge['unit']);

					$sub = '';

					if ($reading['min'] !== null || $reading['max'] !== null) {
						$sub = __('min %s · max %s', apcupsd_dashboard_format_value($reading['min'], $gauge['unit']), apcupsd_dashboard_format_value($reading['max'], $gauge['unit']), 'apcupsd');
					}

					$severity = apcupsd_dashboard_metric_severity($gauge['metric'], $reading['avg']);

					print '<div class="apcupsdGaugeCell">'
						. '<span class="apcupsdGaugeCellLabel">' . html_escape($gauge['label']) . '</span>'
						. apcupsd_dashboard_gauge($reading['avg'], $reading['min'], $reading['max'], $gauge['scale'], $severity, $center, $sub)
						. '</div>';
				}

				print '</div>';

				break;
			case 'upses':
				$upses = db_fetch_assoc("SELECT ups.id, ups.name, ups.host_id, ups.enabled, ups.last_updated,
					stats.ups_status, stats.ups_load_percent, stats.ups_battery_charge, stats.ups_timeleft
					FROM apcupsd_ups AS ups
					LEFT JOIN apcupsd_ups_stats AS stats ON stats.ups_id = ups.id
					ORDER BY ups.name");

				print '<table class="apcupsdDashTable"><thead><tr>'
					. '<th></th>'
					. '<th>' . __esc('UPS', 'apcupsd') . '</th>'
					. '<th>' . __esc('Status', 'apcupsd') . '</th>'
					. '<th class="apcupsdDashNum">' . __esc('Load', 'apcupsd') . '</th>'
					. '<th class="apcupsdDashNum">' . __esc('Charge', 'apcupsd') . '</th>'
					. '<th class="apcupsdDashNum">' . __esc('Time Left', 'apcupsd') . '</th>'
					. '<th>' . __esc('Last Updated', 'apcupsd') . '</th>'
					. '</tr></thead><tbody>';

				if (cacti_sizeof($upses)) {
					foreach ($upses as $ups) {
						$id   = (int) $ups['id'];
						$view = 'upses.php?action=view&id=' . $id;
						$load   = isset($ups['ups_load_percent']) && is_numeric($ups['ups_load_percent']) ? number_format_i18n((float) $ups['ups_load_percent'], 0) . '%' : __('N/A', 'apcupsd');
						$charge = isset($ups['ups_battery_charge']) && is_numeric($ups['ups_battery_charge']) ? number_format_i18n((float) $ups['ups_battery_charge'], 0) . '%' : __('N/A', 'apcupsd');
						$time   = isset($ups['ups_timeleft']) && is_numeric($ups['ups_timeleft']) ? number_format_i18n((float) $ups['ups_timeleft'], 0) . ' ' . __('min', 'apcupsd') : __('N/A', 'apcupsd');

						print '<tr>'
							. '<td><a class="apcupsdDashGlyph" href="' . html_escape($view) . '" title="' . __esc('Open UPS Dashboard', 'apcupsd') . '"><i class="fas fa-tachometer-alt" aria-hidden="true"></i></a></td>'
							. '<td><a href="' . html_escape($view) . '">' . html_escape($ups['name']) . '</a></td>'
							. '<td>' . apcupsd_dashboard_status_pill((string) ($ups['ups_status'] ?? '')) . '</td>'
							. '<td class="apcupsdDashNum">' . html_escape($load) . '</td>'
							. '<td class="apcupsdDashNum">' . html_escape($charge) . '</td>'
							. '<td class="apcupsdDashNum">' . html_escape($time) . '</td>'
							. '<td>' . html_escape((string) ($ups['last_updated'] ?? '')) . '</td>'
							. '</tr>';
					}
				} else {
					print '<tr class="apcupsdDashEmptyRow"><td colspan="7" class="apcupsdDashEmpty">' . __esc('No UPSes are configured yet.', 'apcupsd') . '</td></tr>';
				}

				print '</tbody></table>';

				break;
		}

		return (string) ob_get_clean();
	}
}

if (!function_exists('apcupsd_fleet_render_card')) {
	/**
	 * Render a complete Fleet Dashboard card.
	 *
	 * @param string $key      The card identifier.
	 * @param bool   $expanded Whether the card opens expanded.
	 *
	 * @return string The card HTML, or '' for an unknown key.
	 */
	function apcupsd_fleet_render_card(string $key, bool $expanded = false): string {
		$meta = apcupsd_fleet_card_meta();

		if (!isset($meta[$key])) {
			return '';
		}

		$def   = $meta[$key];
		$state = '';

		if ($key === 'status') {
			$stats = apcupsd_fleet_stats();

			if ((int) ($stats['onbatt'] ?? 0) > 0) {
				$state = 'down';
			}
		}

		return apcupsd_dashboard_card_chrome($key, (string) $def['title'], (int) $def['span'], (bool) $def['expandable'], $expanded, $state, apcupsd_fleet_card_body($key));
	}
}

if (!function_exists('apcupsd_fleet_layout')) {
	/**
	 * The current user's Fleet Dashboard layout (card order + expanded state).
	 * With no saved layout every card is present in the default order.
	 *
	 * @return array{order: string[], expanded: array<string, bool>}
	 */
	function apcupsd_fleet_layout(): array {
		$available = apcupsd_fleet_available_cards();
		$saved     = json_decode((string) read_user_setting('apcupsd_summary_layout', '', true), true);

		if (!is_array($saved) || !isset($saved['order']) || !is_array($saved['order'])) {
			return ['order' => $available, 'expanded' => []];
		}

		$order = [];

		foreach ($saved['order'] as $key) {
			if (is_string($key) && in_array($key, $available, true) && !in_array($key, $order, true)) {
				$order[] = $key;
			}
		}

		$expanded = [];

		if (isset($saved['expanded']) && is_array($saved['expanded'])) {
			foreach ($saved['expanded'] as $key => $on) {
				if ($on && in_array($key, $available, true)) {
					$expanded[(string) $key] = true;
				}
			}
		}

		return ['order' => $order, 'expanded' => $expanded];
	}
}

if (!function_exists('apcupsd_fleet_layout_save')) {
	/**
	 * Persist the posted Fleet Dashboard layout for the current user, validated
	 * to the known fleet cards.
	 *
	 * @return string A JSON status document.
	 */
	function apcupsd_fleet_layout_save(): string {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !function_exists('csrf_check') || !csrf_check(false)) {
			return (string) json_encode(['error' => __('Invalid request. Please try again.', 'apcupsd')]);
		}

		$available = apcupsd_fleet_available_cards();
		$posted    = json_decode(isset_request_var('layout') ? (string) get_nfilter_request_var('layout') : '', true);

		$order    = [];
		$expanded = [];

		if (is_array($posted)) {
			if (isset($posted['order']) && is_array($posted['order'])) {
				foreach ($posted['order'] as $key) {
					if (is_string($key) && in_array($key, $available, true) && !in_array($key, $order, true)) {
						$order[] = $key;
					}
				}
			}

			if (isset($posted['expanded']) && is_array($posted['expanded'])) {
				foreach ($posted['expanded'] as $key => $on) {
					if ($on && is_string($key) && in_array($key, $available, true)) {
						$expanded[$key] = true;
					}
				}
			}
		}

		set_user_setting('apcupsd_summary_layout', json_encode(['order' => $order, 'expanded' => $expanded]));

		return (string) json_encode(['ok' => true]);
	}
}

if (!function_exists('apcupsd_fleet_refresh_save')) {
	/**
	 * Persist the posted Fleet Dashboard auto-refresh interval for the current
	 * user. Accepts only a CSRF-validated POST carrying an offered value.
	 *
	 * @return string A JSON status document.
	 *
	 * @global array $page_refresh_interval Cacti's allowed refresh intervals.
	 */
	function apcupsd_fleet_refresh_save(): string {
		global $page_refresh_interval;

		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !function_exists('csrf_check') || !csrf_check(false)) {
			return (string) json_encode(['error' => __('Invalid request. Please try again.', 'apcupsd')]);
		}

		$posted    = isset_request_var('refresh') ? (string) get_nfilter_request_var('refresh') : '';
		$intervals = (is_array($page_refresh_interval) && cacti_sizeof($page_refresh_interval)) ? $page_refresh_interval : [30 => '', 60 => '', 300 => ''];
		$allowed   = array_map('intval', array_keys($intervals));
		$allowed[] = 0;

		if (!preg_match('/^[0-9]+$/', $posted) || !in_array((int) $posted, $allowed, true)) {
			return (string) json_encode(['error' => __('Invalid refresh interval.', 'apcupsd')]);
		}

		set_user_setting('apcupsd_summary_refresh', (int) $posted);

		return (string) json_encode(['ok' => true]);
	}
}

if (!function_exists('apcupsd_fleet_card_ajax')) {
	/**
	 * AJAX: render a single Fleet Dashboard card.
	 *
	 * @return string A JSON document with the card key and rendered HTML.
	 */
	function apcupsd_fleet_card_ajax(): string {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !function_exists('csrf_check') || !csrf_check(false)) {
			return (string) json_encode(['error' => __('Invalid request. Please try again.', 'apcupsd')]);
		}

		$key = isset_request_var('card') ? (string) get_nfilter_request_var('card') : '';

		if (!in_array($key, apcupsd_fleet_available_cards(), true)) {
			return (string) json_encode(['error' => __('Unknown card.', 'apcupsd')]);
		}

		$expanded = isset_request_var('expanded') && get_nfilter_request_var('expanded') === '1';

		return (string) json_encode(['card' => $key, 'html' => apcupsd_fleet_render_card($key, $expanded)]);
	}
}

if (!function_exists('apcupsd_fleet_dashboard')) {
	/**
	 * Render the high-level Fleet Dashboard: a draggable grid of overview, status
	 * distribution, fleet min/avg/max reading bullets and a UPSes table whose
	 * rows drill down into each UPS's per-UPS Dashboard. Card order, expanded
	 * state and the auto-refresh interval persist per user. Called from upses.php's
	 * dispatcher when action=dashboard.
	 *
	 * @return void
	 *
	 * @global array $config                Cacti global configuration array.
	 * @global array $page_refresh_interval Cacti's standard refresh-interval list.
	 */
	function apcupsd_fleet_dashboard(): void {
		global $config, $page_refresh_interval;

		apcupsd_dashboard_print_css();
		apcupsd_dashboard_tabs('dashboard');

		apcupsd_dashboard_init_timespan();

		$current_refresh = (int) read_user_setting('apcupsd_summary_refresh', 0);

		if ($current_refresh > 0 && function_exists('set_page_refresh')) {
			set_page_refresh([
				'seconds' => $current_refresh,
				'page'    => $config['url_path'] . 'plugins/apcupsd/upses.php?action=dashboard&header=false',
				'logout'  => 'false'
			]);
		}

		print '<script type="text/javascript" ' . plugin_apcupsd_csp_nonce() . ' src="' . $config['url_path'] . 'plugins/apcupsd/js/apcupsd_dashboard.js?v=' . filemtime($config['base_path'] . '/plugins/apcupsd/js/apcupsd_dashboard.js') . '"></script>';

		$meta   = apcupsd_fleet_card_meta();
		$layout = apcupsd_fleet_layout();
		$absent = array_values(array_diff(apcupsd_fleet_available_cards(), $layout['order']));

		if (empty($page_refresh_interval) || !is_array($page_refresh_interval)) {
			$page_refresh_interval = [0 => __('No Refresh', 'apcupsd'), 30 => __('30 Seconds', 'apcupsd'), 60 => __('1 Minute', 'apcupsd'), 300 => __('5 Minutes', 'apcupsd')];
		}

		print '<div class="apcupsdDashToolbar">';

		print '<label class="apcupsdDashToolbarLabel" for="apcupsd_dashboard_timespan">' . __esc('Timespan', 'apcupsd') . '</label>';
		print '<select id="apcupsd_dashboard_timespan" class="apcupsdDashTimespan">';

		global $graph_timespans;
		$timespans = is_array($graph_timespans) ? $graph_timespans : [];

		$current_span = isset($_SESSION['sess_current_timespan']) ? (int) $_SESSION['sess_current_timespan'] : 0;

		foreach ($timespans as $key => $display_text) {
			print '<option value="' . (int) $key . '"' . ($current_span === (int) $key ? ' selected' : '') . '>' . html_escape($display_text) . '</option>';
		}

		print '</select>';

		print '<label class="apcupsdDashToolbarLabel" for="apcupsd_dashboard_add">' . __esc('Add card', 'apcupsd') . '</label>';
		print '<select id="apcupsd_dashboard_add" class="apcupsdDashAdd"><option value="">' . __esc('Add a card…', 'apcupsd') . '</option>';

		foreach ($absent as $key) {
			print '<option value="' . html_escape($key) . '">' . html_escape($meta[$key]['title']) . '</option>';
		}

		print '</select>';

		print '<div class="apcupsdDashRefresh">';
		print '<label class="apcupsdDashToolbarLabel" for="apcupsd_dashboard_refresh">' . __esc('Refresh', 'apcupsd') . '</label>';
		print '<select id="apcupsd_dashboard_refresh" class="apcupsdDashRefreshInterval">';
		print '<option value="0"' . ($current_refresh == 0 ? ' selected' : '') . '>' . __esc('No Refresh', 'apcupsd') . '</option>';

		foreach ($page_refresh_interval as $seconds => $display_text) {
			if ((int) $seconds === 0) {
				continue;
			}

			print '<option value="' . (int) $seconds . '"' . ($current_refresh == $seconds ? ' selected' : '') . '>' . html_escape($display_text) . '</option>';
		}

		print '</select>';
		print '<button type="button" id="apcupsd_dashboard_refresh_now" class="apcupsdDashCardTool apcupsdDashRefreshNow" aria-label="' . __esc('Refresh', 'apcupsd') . '" title="' . __esc('Refresh', 'apcupsd') . '"><i class="fas fa-sync-alt" aria-hidden="true"></i></button>';
		print '</div>';
		print '<span class="apcupsdDashSaveStatus" role="status" aria-live="polite" hidden></span>';
		print '</div>';

		print '<div id="apcupsd_dashboard" class="apcupsdDashGrid"'
			. ' data-card-action="summary_card" data-layout-action="summary_layout" data-refresh-action="summary_refresh"'
			. ' data-reload="upses.php?action=dashboard">';

		foreach ($layout['order'] as $key) {
			print apcupsd_fleet_render_card($key, !empty($layout['expanded'][$key]));
		}

		print '</div>';

		print '<div id="apcupsd_dashboard_dialog" class="apcupsdDashDialog" style="display:none"></div>';

		$catalog = [];

		foreach (apcupsd_fleet_available_cards() as $key) {
			$catalog[$key] = $meta[$key]['title'];
		}

		// JSON_HEX_* prevents a card title from breaking out of the inline <script>.
		print '<script type="text/javascript" ' . plugin_apcupsd_csp_nonce() . '>var apcupsdDashCatalog = ' . json_encode($catalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '; initApcupsdDashboard();</script>';
	}
}

