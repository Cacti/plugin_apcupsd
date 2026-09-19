<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | Shared helpers for request normalization and apcaccess execution.       |
 +-------------------------------------------------------------------------+
 */

if (!function_exists('apcupsd_normalize_positive_int')) {
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
	function apcupsd_get_site_sql_where($site_id) {
		$site_id = apcupsd_normalize_positive_int($site_id, 0);

		if ($site_id > 0) {
			return 'site_id = ' . $site_id;
		}

		return '';
	}
}

if (!function_exists('apcupsd_get_autocomplete_rows_limit')) {
	function apcupsd_get_autocomplete_rows_limit($rows) {
		return apcupsd_normalize_positive_int($rows, 1);
	}
}

if (!function_exists('apcupsd_escape_shellcmd')) {
	function apcupsd_escape_shellcmd($value) {
		if (function_exists('cacti_escapeshellcmd')) {
			return cacti_escapeshellcmd($value);
		}

		return escapeshellcmd($value);
	}
}

if (!function_exists('apcupsd_escape_shellarg')) {
	function apcupsd_escape_shellarg($value) {
		if (function_exists('cacti_escapeshellarg')) {
			return cacti_escapeshellarg($value);
		}

		return escapeshellarg($value);
	}
}

if (!function_exists('apcupsd_build_apcaccess_command')) {
	function apcupsd_build_apcaccess_command($binary_path, $hostname, $port) {
		$hostname = trim((string)$hostname);
		$port     = apcupsd_normalize_positive_int($port, 0);

		if ($binary_path === '' || $hostname === '' || $port < 1 || $port > 65535) {
			return false;
		}

		return apcupsd_escape_shellcmd($binary_path) . ' -u -h ' . apcupsd_escape_shellarg($hostname . ':' . $port);
	}
}
