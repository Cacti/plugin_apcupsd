<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | Shared helpers for request normalization and apcaccess execution.       |
 +-------------------------------------------------------------------------+
 */

if (!defined('APCUPSD_HOST_TEMPLATE_HASH')) {
	define('APCUPSD_HOST_TEMPLATE_HASH', '2107af603fd8dc27ea3f2cc2234eb7b9');
}

if (!function_exists('apcupsd_host_template_imported')) {
	/**
	 * Determines whether this plugin's APC UPS Host Template has been
	 * imported into Cacti, by looking up its well-known template hash.
	 * Called from setup.php's install/upgrade flow to decide whether the
	 * host template still needs importing.
	 *
	 * @return bool True when a host_template row with this plugin's
	 *               known hash exists.
	 */
	function apcupsd_host_template_imported() {
		return (bool) db_fetch_cell_prepared('SELECT id FROM host_template WHERE hash = ?', array(APCUPSD_HOST_TEMPLATE_HASH));
	}
}

if (!function_exists('apcupsd_normalize_positive_int')) {
	/**
	 * Coerces a value (int, numeric string, or otherwise) to a positive
	 * integer, falling back to a caller-supplied default when the value
	 * isn't a positive whole number. Called throughout this file and
	 * upses.php/poller_apcupsd.php to sanitize request/config values such
	 * as site ids, row limits, and ports before use in SQL or shell
	 * commands.
	 *
	 * @param mixed $value   The value to normalize.
	 * @param int   $default The value to return when $value does not
	 *                        normalize to a positive integer; defaults to 0.
	 *
	 * @return int The normalized positive integer, or (int)$default.
	 */
	function apcupsd_normalize_positive_int($value, $default = 0) {
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
	function apcupsd_get_site_sql_where($site_id) {
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
	function apcupsd_get_autocomplete_rows_limit($rows) {
		return apcupsd_normalize_positive_int($rows, 1);
	}
}

if (!function_exists('apcupsd_escape_shellcmd')) {
	/**
	 * Escapes a string for safe use as a shell command name, preferring
	 * Cacti core's cacti_escapeshellcmd() when available and falling back
	 * to PHP's native escapeshellcmd() otherwise. Called from
	 * apcupsd_build_apcaccess_command() before building the apcaccess
	 * shell command.
	 *
	 * @param string $value The value to escape.
	 *
	 * @return string The escaped value.
	 */
	function apcupsd_escape_shellcmd($value) {
		if (function_exists('cacti_escapeshellcmd')) {
			return cacti_escapeshellcmd($value);
		}

		return escapeshellcmd($value);
	}
}

if (!function_exists('apcupsd_escape_shellarg')) {
	/**
	 * Escapes a string for safe use as a single shell command argument,
	 * preferring Cacti core's cacti_escapeshellarg() when available and
	 * falling back to PHP's native escapeshellarg() otherwise. Called from
	 * apcupsd_build_apcaccess_command() before building the apcaccess
	 * shell command.
	 *
	 * @param string $value The value to escape.
	 *
	 * @return string The escaped value.
	 */
	function apcupsd_escape_shellarg($value) {
		if (function_exists('cacti_escapeshellarg')) {
			return cacti_escapeshellarg($value);
		}

		return escapeshellarg($value);
	}
}

if (!function_exists('apcupsd_build_apcaccess_command')) {
	/**
	 * Builds a safely-escaped 'apcaccess -u -h host:port' shell command for
	 * querying a remote apcupsd daemon, validating the hostname and port
	 * first. Called from poller_apcupsd.php while polling each configured
	 * UPS device.
	 *
	 * @param string $binary_path The path to the apcaccess binary.
	 * @param string $hostname    The apcupsd daemon's hostname/IP.
	 * @param mixed  $port        The apcupsd daemon's TCP port.
	 *
	 * @return string|false The fully-escaped shell command, or false when
	 *                       the binary path, hostname, or port is invalid.
	 */
	function apcupsd_build_apcaccess_command($binary_path, $hostname, $port) {
		$hostname = trim((string)$hostname);
		$port     = apcupsd_normalize_positive_int($port, 0);

		if ($binary_path === '' || $hostname === '' || $port < 1 || $port > 65535) {
			return false;
		}

		return apcupsd_escape_shellcmd($binary_path) . ' -u -h ' . apcupsd_escape_shellarg($hostname . ':' . $port);
	}
}
