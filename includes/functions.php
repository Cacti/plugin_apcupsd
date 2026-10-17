<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | Shared helpers for request normalization and request validation.        |
 +-------------------------------------------------------------------------+
 */

if (!defined('APCUPSD_HOST_TEMPLATE_HASH')) {
	define('APCUPSD_HOST_TEMPLATE_HASH', '2107af603fd8dc27ea3f2cc2234eb7b9');
}

if (!function_exists('apcupsd_host_template_imported')) {
	/**
	 * Determines whether this plugin's APC UPS Host Template has been
	 * imported into Cacti, by looking up its well-known template hash.
	 * Called from poller_apcupsd.php and upses.php to decide whether the
	 * host template still needs importing before proceeding.
	 *
	 * @return bool True when a host_template row with this plugin's
	 *              known hash exists.
	 */
	function apcupsd_host_template_imported(): bool {
		return (bool) db_fetch_cell_prepared('SELECT id FROM host_template WHERE hash = ?', [APCUPSD_HOST_TEMPLATE_HASH]);
	}
}

if (!function_exists('apcupsd_is_slave_status')) {
	/**
	 * Whether a UPS status string reports the UPS as a slave (e.g. "ONLINE SLAVE"
	 * or "SLAVEDOWN"). A slave draws power already attributed to the master UPS it
	 * is attached to, so its estimated power must not be counted again in fleet
	 * totals.
	 *
	 * @param mixed $status The apcupsd STATUS value.
	 *
	 * @return bool True when the status contains the SLAVE flag.
	 */
	function apcupsd_is_slave_status($status): bool {
		return is_string($status) && stripos($status, 'SLAVE') !== false;
	}
}

if (!function_exists('apcupsd_estimate_watts')) {
	/**
	 * Estimates a UPS's real output power in watts as
	 * nominal power x load percent / 100, for UPSes that do not report a
	 * measured wattage. Called from the Fleet Dashboard aggregates and the UPS
	 * tables so every view derives the figure the same way.
	 *
	 * @param mixed $nominal_power The UPS nominal power rating in watts.
	 * @param mixed $load_percent  The UPS load as a percentage of that rating.
	 *
	 * @return float|null The estimated watts, or null when either input is
	 *                    missing, non-numeric or negative, or when the
	 *                    nominal power is not a positive rating.
	 */
	function apcupsd_estimate_watts($nominal_power, $load_percent): ?float {
		if (!is_numeric($nominal_power) || !is_numeric($load_percent)) {
			return null;
		}

		$nominal = (float) $nominal_power;
		$load    = (float) $load_percent;

		if ($nominal <= 0 || $load < 0) {
			return null;
		}

		return $nominal * $load / 100;
	}
}

if (!function_exists('apcupsd_normalize_positive_int')) {
	/**
	 * Coerces a value (int, numeric string, or otherwise) to a positive
	 * integer, falling back to a caller-supplied default when the value
	 * isn't a positive whole number. Called throughout this file and
	 * upses.php/poller_apcupsd.php to sanitize request/config values such
	 * as site ids, row limits, and ports before use in SQL or network
	 * requests.
	 *
	 * @param mixed $value   The value to normalize.
	 * @param int   $default The value to return when $value does not
	 *                       normalize to a positive integer; defaults to 0.
	 *
	 * @return int The normalized positive integer, or (int)$default.
	 */
	function apcupsd_normalize_positive_int($value, $default = 0): int {
		if (is_int($value)) {
			$normalized = $value;
		} elseif (is_string($value) && preg_match('/^[0-9]+$/', $value)) {
			$normalized = (int)$value;
		} else {
			$normalized = 0;
		}

		if ($normalized > 0) {
			return $normalized;
		}

		return (int)$default;
	}
}

if (!function_exists('apcupsd_normalize_timeout')) {
	/**
	 * Coerces a configured timeout to whole seconds between 1 and 120, falling
	 * back to a default when the value is not a positive number. Used for the
	 * per-UPS timeout of the apcupsd and NUT collectors so a blank, zero or
	 * absurd value can neither disable the timeout nor stall the poller.
	 *
	 * @param mixed $value   The configured timeout in seconds.
	 * @param int   $default The timeout to use when $value is not a positive number.
	 *
	 * @return int The timeout in seconds, from 1 to 120.
	 */
	function apcupsd_normalize_timeout($value, $default = 5): int {
		$seconds = apcupsd_normalize_positive_int($value, (int) $default);

		return max(1, min(120, $seconds));
	}
}

if (!function_exists('apcupsd_get_site_sql_where')) {
	/**
	 * Builds a SQL condition restricting results to a single site id, when
	 * one is given. Called from upses.php's list/autocomplete queries to
	 * optionally scope UPS devices to a specific site.
	 *
	 * @param mixed $site_id The candidate site id to filter by.
	 *
	 * @return string The 'site_id = N' condition, or '' when $site_id
	 *                doesn't normalize to a positive integer.
	 */
	function apcupsd_get_site_sql_where($site_id): string {
		$site_id = apcupsd_normalize_positive_int($site_id, 0);

		if ($site_id > 0) {
			return 'site_id = ' . $site_id;
		}

		return '';
	}
}

if (!function_exists('apcupsd_get_autocomplete_rows_limit')) {
	/**
	 * Normalizes a requested autocomplete result-row limit to a positive
	 * integer, defaulting to 1 when the given value is invalid. Called
	 * from upses.php's AJAX autocomplete handler to bound the number of
	 * rows returned.
	 *
	 * @param mixed $rows The requested row limit.
	 *
	 * @return int The normalized row limit, defaulting to 1.
	 */
	function apcupsd_get_autocomplete_rows_limit($rows): int {
		return apcupsd_normalize_positive_int($rows, 1);
	}
}
