<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('end-to-end wiring checks for the apcupsd security fixes', function () {
	$pollerSource = file_get_contents(realpath(__DIR__ . '/../../poller_apcupsd.php'));
	$uiSource     = file_get_contents(realpath(__DIR__ . '/../../upses.php'));

	it('poller reads apcupsd UPSes over the native protocol and never shells out', function () use ($pollerSource) {
		expect($pollerSource)->toContain('apcupsd_nis_query($ups[\'hostname\'], $ups[\'port\'], $ups[\'timeout\'] ?? null)');
		expect($pollerSource)->toContain("require_once('./plugins/apcupsd/includes/nis.php');");
		expect($pollerSource)->not->toContain('apcaccess');
		expect(preg_match('/\b(exec_with_timeout|exec|shell_exec|system|passthru|proc_open|popen)\s*\(/', $pollerSource))->toBe(0);
	});

	it('the apcupsd client rejects invalid host or port before connecting', function () {
		$client = file_get_contents(realpath(__DIR__ . '/../../includes/nis.php'));

		expect($client)->toContain('Invalid apcupsd hostname or port configuration');
		expect(strpos($client, 'apcupsd_nut_valid_host($hostname)'))->toBeLessThan(strpos($client, 'stream_socket_client('));
	});

	it('ajax host filters use integer-normalized site clauses', function () use ($uiSource) {
		expect(substr_count($uiSource, "apcupsd_get_site_sql_where(get_request_var('site_id'))"))->toBe(2);
	});

	it('UI enforces numeric validation for APCUPSD and SNMP ports', function () use ($uiSource) {
		expect($uiSource)->toContain("form_input_validate(get_nfilter_request_var('port'), 'port', '^[0-9]+$', true, 3)");
		expect($uiSource)->toContain("form_input_validate(get_nfilter_request_var('snmp_port'), 'snmp_port', '^[0-9]+$', true, 3)");
	});

	it('autocomplete limit is normalized before entering SQL', function () use ($uiSource) {
		expect($uiSource)->toContain("apcupsd_get_autocomplete_rows_limit(read_config_option('autocomplete_rows'))");
	});
});
