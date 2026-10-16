<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * NUT (Network UPS Tools) is a third collector type (type_id 3). These tests
 * pin the schema and the places that have to know about the new type.
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/nut.php';

describe('NUT schema', function () {
	$columns = function (array $data): array {
		$out = [];

		foreach ($data['columns'] as $column) {
			$out[$column['name']] = $column;
		}

		return $out;
	};

	it('adds the NUT connection settings to apcupsd_ups', function () use ($columns) {
		$ups = $columns(apcupsd_ups_table_data());

		foreach (['nut_ups_name', 'nut_username', 'nut_password', 'nut_tls'] as $name) {
			expect($ups)->toHaveKey($name);
		}

		expect($ups['nut_ups_name']['type'])->toBe('varchar(64)');
		expect($ups['nut_password']['type'])->toBe('varchar(128)');
		expect($ups['nut_tls']['type'])->toBe('char(2)');
		expect($ups['nut_ups_name']['default'])->toBe('');
	});

	it('adds the CA file and self-signed TLS options to apcupsd_ups', function () use ($columns) {
		$ups = $columns(apcupsd_ups_table_data());

		expect($ups['nut_cafile']['type'])->toBe('varchar(255)');
		expect($ups['nut_cafile']['default'])->toBe('');
		expect($ups['nut_tls_selfsigned']['type'])->toBe('char(2)');
		expect($ups['nut_tls_selfsigned']['default'])->toBe('');
	});

	it('adds a per-UPS timeout that defaults to five seconds', function () use ($columns) {
		$ups = $columns(apcupsd_ups_table_data());

		expect($ups)->toHaveKey('timeout');
		expect($ups['timeout']['type'])->toBe('int(10)');
		expect($ups['timeout']['default'])->toBe(5);
		expect($ups['timeout']['NULL'])->toBeFalse();
	});

	it('stores unreported timestamps as unknown instead of the poll time', function () use ($columns) {
		$stats = $columns(apcupsd_ups_stats_table_data());

		foreach (['ups_starttime', 'ups_mandate', 'ups_masterupd', 'ups_xonbatt', 'ups_laststest'] as $name) {
			expect($stats[$name]['type'])->toBe('timestamp');
			expect($stats[$name]['NULL'])->toBeTrue("{$name} must allow NULL");
			expect($stats[$name]['default'])->toBeNull("{$name} must not default to the current time");
		}

		// The poll and record times are real readings and keep their defaults.
		expect($stats['ups_date']['default'])->toBe('CURRENT_TIMESTAMP');
		expect($stats['ups_end_rec']['default'])->toBe('CURRENT_TIMESTAMP');
	});

	it('does not invent the timestamps NUT does not report', function () {
		$stats = apcupsd_nut_build_stats(['ups.status' => 'OL'], ['id' => 1, 'hostname' => 'h', 'nut_ups_name' => 'ups']);

		foreach (['ups_starttime', 'ups_mandate', 'ups_masterupd', 'ups_xonbatt', 'ups_laststest'] as $name) {
			expect($stats)->not->toHaveKey($name);
		}
	});

	it('keeps the NUT settings out of the primary key and indexes', function () {
		$data = apcupsd_ups_table_data();

		expect($data['primary'])->toBe(['id']);
		expect($data)->not->toHaveKey('keys');
	});

	it('widens the stats columns multi-flag NUT values need', function () use ($columns) {
		$stats = $columns(apcupsd_ups_stats_table_data());

		expect($stats['ups_status']['type'])->toBe('varchar(64)');
		expect($stats['ups_model']['type'])->toBe('varchar(64)');
		expect($stats['ups_serialno']['type'])->toBe('varchar(64)');
		expect($stats['ups_firmware']['type'])->toBe('varchar(64)');
		expect($stats['ups_driver']['type'])->toBe('varchar(40)');
		expect($stats['ups_name']['type'])->toBe('varchar(64)');
	});
});

describe('NUT wiring', function () {
	$read = function (string $relative): string {
		$contents = file_get_contents(__DIR__ . '/../../' . $relative);

		expect($contents)->not->toBeFalse("Unable to read {$relative}");

		return (string) $contents;
	};

	it('polls NUT UPSes from the poller and creates their devices like apcupsd ones', function () use ($read) {
		$poller = $read('poller_apcupsd.php');

		expect($poller)->toContain("require_once('./plugins/apcupsd/includes/nut.php');");
		expect($poller)->toContain('WHERE type_id = 3');
		expect($poller)->toContain('function collect_nut_ups_data(');
		expect($poller)->toContain('apcupsd_nut_query(');
		expect($poller)->toContain('apcupsd_nut_build_stats(');
		expect($poller)->toContain('NUTUPSes:%s');
		expect($poller)->toContain("if (\$ups['type_id'] != 2) {");
	});

	it('writes the NUT reading with prepared statements and clears the old error', function () use ($read) {
		$poller = $read('poller_apcupsd.php');

		expect($poller)->toContain("db_execute_prepared('REPLACE INTO apcupsd_ups_stats");
		expect($poller)->toContain('error_message = ""');
		expect(preg_match('/\bdb_execute\s*\(/', $poller))->toBe(0);
	});

	it('offers the NUT type and its settings on the UPS form', function () use ($read) {
		$page = $read('upses.php');

		expect($page)->toContain("3 => __('NUT Based', 'apcupsd')");
		expect($page)->toContain("require_once(__DIR__ . '/includes/nut.php');");

		foreach (['nut_ups_name', 'nut_username', 'nut_password', 'nut_tls'] as $field) {
			expect($page)->toContain("'{$field}' => [");
		}

		foreach (['nut_ups_name', 'nut_username', 'nut_password'] as $field) {
			expect($page)->toContain("get_nfilter_request_var('{$field}')");
		}

		expect($page)->toContain("isset_request_var('nut_tls')");

		expect($page)->toContain("'method'        => 'textbox_password'");
		expect($page)->toContain('apcupsd_nut_valid_host(');
		expect($page)->toContain('APCUPSD_NUT_DEFAULT_PORT');
		expect($page)->toContain("\$('[id^=\"row_nut\"]')");
	});

	it('labels the NUT collector on the dashboard and shows its port', function () use ($read) {
		$dashboard = $read('includes/dashboard.php');

		expect($dashboard)->toContain("3 => __('NUT', 'apcupsd')");
		expect($dashboard)->toContain("(int) (\$ups['type_id'] ?? 0) !== 2 ? (\$ups['port']");
	});

	it('marks the linked host down when a NUT query fails, and up or down from the status otherwise', function () use ($read) {
		$poller = $read('poller_apcupsd.php');

		expect($poller)->toContain('function set_ups_host_state(');
		expect($poller)->toContain("set_ups_host_state(\$ups['host_id'], false);");
		expect($poller)->toContain("set_ups_host_state(\$ups['host_id'], \$online);");
		expect($poller)->toContain("db_execute_prepared('UPDATE host SET status = 4, status_fail_date=NOW() WHERE id = ?'");

		// The failure branch must mark the host before it returns false.
		expect(strpos($poller, "set_ups_host_state(\$ups['host_id'], false);"))->toBeLessThan(strpos($poller, "substr(\$result['error'], 0, 255)"));
	});

	it('does not rewrite an existing UPS\'s port when the edit form opens', function () use ($read) {
		$page = $read('upses.php');

		expect($page)->toContain('function changeType(userChanged) {');
		expect($page)->toContain("if (userChanged && \$('#port').val() == '3493') {");
		expect($page)->toContain("if (userChanged && (\$('#port').val() == '' || \$('#port').val() == '3551')) {");
		expect($page)->toContain('changeType(false);');
		expect($page)->toContain('changeType(true);');
		expect($page)->not->toContain('changeType();');
	});

	it('enforces the per-UPS timeout for both the apcaccess and NUT collectors', function () use ($read) {
		$poller = $read('poller_apcupsd.php');

		expect($poller)->toContain('exec_with_timeout($command, $output, $return, $timeout);');
		expect($poller)->not->toContain('exec($command');
		expect($poller)->toContain("\$timeout = apcupsd_normalize_timeout(\$ups['timeout'] ?? null);");
		expect(preg_match("/\\\$ups\\['nut_tls'\\] == 'on',\\s+apcupsd_normalize_timeout\\(\\\$ups\\['timeout'\\] \\?\\? null\\),/", $poller))->toBe(1);
		expect($poller)->toContain('if ($return != 0 || !cacti_sizeof($output)) {');

		$page = $read('upses.php');

		expect($page)->toContain("\$save['timeout']      = apcupsd_normalize_timeout(get_nfilter_request_var('timeout'));");
		expect($page)->toContain("\$('#row_timeout').show();");
		expect($page)->toContain("\$('#row_timeout').hide();");
	});

	it('keeps the timeout field narrow on the form', function () use ($read) {
		$page = $read('upses.php');

		expect(preg_match("/'timeout' => \\[.*?'size'\\s+=> '5',.*?'max_length'\\s+=> '3'/s", $page))->toBe(1);
	});

	it('uses no external program or shell to talk to NUT', function () use ($read) {
		$nut = $read('includes/nut.php');

		foreach (['exec(', 'shell_exec', 'proc_open', 'popen(', 'passthru', 'system(', 'eval(', 'curl'] as $forbidden) {
			expect(strpos($nut, $forbidden))->toBeFalse("includes/nut.php must not use {$forbidden}");
		}

		expect($nut)->toContain('stream_socket_client');
		expect($nut)->toContain("'verify_peer'       => !\$allow_self_signed");
	});

	it('offers a CA file and a self-signed option for NUT TLS and passes them to the poller', function () use ($read) {
		$page   = $read('upses.php');
		$poller = $read('poller_apcupsd.php');

		foreach (['nut_cafile', 'nut_tls_selfsigned'] as $field) {
			expect($page)->toContain("'{$field}' => [");
			expect($poller)->toContain("\$ups['{$field}']");
		}

		expect($page)->toContain("get_nfilter_request_var('nut_cafile')");
		expect($page)->toContain("isset_request_var('nut_tls_selfsigned')");
		expect($page)->toContain('apcupsd_nut_valid_cafile(');
		expect($page)->toContain('function syncNutTls()');
		expect($read('README.md'))->toContain('Allow Self-Signed Certificate');
	});

	it('is measured by the coverage run', function () use ($read) {
		expect($read('phpunit.xml'))->toContain('<file>includes/nut.php</file>');
	});

	it('is documented and ships a new plugin version', function () use ($read) {
		$readme = $read('README.md');

		expect($readme)->toContain('Network UPS Tools (NUT)');
		expect($readme)->toContain('NUT Based');
		expect($readme)->toContain('3493');

		$info = parse_ini_string($read('INFO'), true);

		expect(version_compare($info['info']['version'], '1.4.0', '>='))->toBeTrue();
		expect($read('CHANGELOG.md'))->toContain('--- ' . $info['info']['version'] . ' ---');
	});
});
