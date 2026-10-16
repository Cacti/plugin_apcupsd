<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The native NUT (Network UPS Tools) client in includes/nut.php. The protocol
 * logic runs over injected send/recv callables, so most of it is exercised
 * with a scripted server; the socket layer is covered with a connected stream
 * pair and a loopback listener.
 */

require_once __DIR__ . '/../../includes/nut.php';

/**
 * A scripted NUT server: recv() returns the queued lines then null; send()
 * records every command.
 *
 * @param array<int, string> $replies
 *
 * @return array{0: callable, 1: callable, 2: ArrayObject}
 */
function apcupsd_test_nut_transport(array $replies): array {
	$sent = new ArrayObject();

	return [
		function(string $command) use ($sent): void {
			$sent[] = $command;
		},
		function() use (&$replies): ?string {
			return $replies === [] ? null : array_shift($replies);
		},
		$sent,
	];
}

describe('NUT input validation', function () {
	it('accepts DNS names and IP addresses as hosts', function () {
		foreach (['ups.example.com', 'rack-ups', '10.0.0.5', '::1', '2001:db8::1'] as $host) {
			expect(apcupsd_nut_valid_host($host))->toBeTrue("host {$host}");
		}
	});

	it('rejects hosts that could be abused', function () {
		foreach (['', '   ', 'bad host', 'a;b', '$(id)', 'host/../x', "h\nLOGOUT"] as $host) {
			expect(apcupsd_nut_valid_host($host))->toBeFalse('host ' . json_encode($host));
		}
	});

	it('only accepts legal NUT UPS names', function () {
		expect(apcupsd_nut_valid_ups_name('ups'))->toBeTrue();
		expect(apcupsd_nut_valid_ups_name('rack_1.ups-a'))->toBeTrue();
		expect(apcupsd_nut_valid_ups_name(''))->toBeFalse();
		expect(apcupsd_nut_valid_ups_name('ups name'))->toBeFalse();
		expect(apcupsd_nut_valid_ups_name("ups\nLOGOUT"))->toBeFalse();
		expect(apcupsd_nut_valid_ups_name(str_repeat('a', 65)))->toBeFalse();
	});

	it('quotes protocol arguments and refuses control characters', function () {
		expect(apcupsd_nut_quote('secret'))->toBe('"secret"');
		expect(apcupsd_nut_quote('a"b\\c'))->toBe('"a\\"b\\\\c"');
		expect(apcupsd_nut_quote("pw\nLOGOUT"))->toBeNull();
		expect(apcupsd_nut_quote("pw\0"))->toBeNull();
	});
});

describe('NUT line tokenizer', function () {
	it('splits plain and quoted words', function () {
		expect(apcupsd_nut_tokenize('VAR ups battery.charge "100"'))->toBe(['VAR', 'ups', 'battery.charge', '100']);
		expect(apcupsd_nut_tokenize("BEGIN\tLIST  VAR ups"))->toBe(['BEGIN', 'LIST', 'VAR', 'ups']);
		expect(apcupsd_nut_tokenize(''))->toBe([]);
	});

	it('keeps spaces and unescapes quotes and backslashes inside quotes', function () {
		expect(apcupsd_nut_tokenize('VAR ups ups.model "Smart \\"X\\" 1500 \\\\ RM"'))
			->toBe(['VAR', 'ups', 'ups.model', 'Smart "X" 1500 \\ RM']);
	});

	it('returns null for an unterminated quote', function () {
		expect(apcupsd_nut_tokenize('VAR ups x "oops'))->toBeNull();
		expect(apcupsd_nut_tokenize('"abc\\'))->toBeNull();
	});
});

describe('NUT error messages', function () {
	it('maps known ERR codes', function () {
		expect(apcupsd_nut_error('ERR ACCESS-DENIED', 'ups'))->toContain('denied access');
		expect(apcupsd_nut_error('ERR INVALID-PASSWORD', 'ups'))->toContain('denied access');
		expect(apcupsd_nut_error('ERR UNKNOWN-UPS', 'rack1'))->toContain('"rack1"');
		expect(apcupsd_nut_error('ERR DATA-STALE', 'ups'))->toContain('stale data');
	});

	it('reports other codes, sanitised, and a bare ERR', function () {
		expect(apcupsd_nut_error('ERR FEATURE-NOT-CONFIGURED', 'ups'))->toContain('FEATURE-NOT-CONFIGURED');
		expect(apcupsd_nut_error('ERR BAD<script>', 'ups'))->toContain('BADscript');
		expect(apcupsd_nut_error('ERR', 'ups'))->toContain('ERR');
	});
});

describe('NUT session', function () {
	it('reads every variable anonymously and logs out', function () {
		[$send, $recv, $sent] = apcupsd_test_nut_transport([
			'Network UPS Tools upsd 2.8.0 - https://www.networkupstools.org/',
			'BEGIN LIST VAR ups',
			'VAR ups battery.charge "100"',
			'VAR ups ups.model "Smart \\"X\\" 1500"',
			'VAR ups ups.status "OL CHRG"',
			'not a var line',
			'VAR ups too-few',
			'VAR ups unterminated "oops',
			'END LIST VAR ups',
		]);

		$result = apcupsd_nut_session($send, $recv, 'ups');

		expect($result['error'])->toBe('');
		expect($result['version'])->toBe('Network UPS Tools upsd 2.8.0 - https://www.networkupstools.org/');
		expect($result['vars'])->toBe(['battery.charge' => '100', 'ups.model' => 'Smart "X" 1500', 'ups.status' => 'OL CHRG']);
		expect($sent->getArrayCopy())->toBe(['VER', 'LIST VAR ups', 'LOGOUT']);
	});

	it('authenticates with quoted credentials when a username is set', function () {
		[$send, $recv, $sent] = apcupsd_test_nut_transport([
			'upsd 2.8.0', 'OK', 'OK', 'BEGIN LIST VAR ups', 'VAR ups ups.load "12"', 'END LIST VAR ups',
		]);

		$result = apcupsd_nut_session($send, $recv, 'ups', 'monitor', 'p"w');

		expect($result['error'])->toBe('');
		expect($sent->getArrayCopy())->toBe(['VER', 'USERNAME "monitor"', 'PASSWORD "p\\"w"', 'LIST VAR ups', 'LOGOUT']);
	});

	it('reports a refused login', function () {
		[$send, $recv] = apcupsd_test_nut_transport(['upsd 2.8.0', 'OK', 'ERR INVALID-PASSWORD']);

		$result = apcupsd_nut_session($send, $recv, 'ups', 'monitor', 'bad');

		expect($result['error'])->toContain('denied access');
		expect($result['vars'])->toBe([]);
	});

	it('does not send credentials that could inject a command', function () {
		[$send, $recv, $sent] = apcupsd_test_nut_transport(['upsd 2.8.0']);

		$result = apcupsd_nut_session($send, $recv, 'ups', "monitor\nLOGOUT", 'pw');

		expect($result['error'])->toContain('cannot be sent');
		expect($sent->getArrayCopy())->toBe([]);

		[$send, $recv, $sent] = apcupsd_test_nut_transport(['upsd 2.8.0']);

		expect(apcupsd_nut_session($send, $recv, 'ups', 'monitor', "pw\r")['error'])->toContain('cannot be sent');
		expect($sent->getArrayCopy())->toBe([]);
	});

	it('refuses an invalid UPS name before sending anything', function () {
		[$send, $recv, $sent] = apcupsd_test_nut_transport([]);

		expect(apcupsd_nut_session($send, $recv, "ups\nLOGOUT")['error'])->toContain('Invalid NUT');
		expect($sent->getArrayCopy())->toBe([]);
	});

	it('reports an unknown UPS', function () {
		[$send, $recv] = apcupsd_test_nut_transport(['upsd 2.8.0', 'ERR UNKNOWN-UPS']);

		expect(apcupsd_nut_session($send, $recv, 'nope')['error'])->toContain('"nope"');
	});

	it('reports a connection that closes early', function () {
		[$send, $recv] = apcupsd_test_nut_transport([]);

		expect(apcupsd_nut_session($send, $recv, 'ups')['error'])->toContain('closed the connection');

		[$send, $recv] = apcupsd_test_nut_transport(['upsd 2.8.0', 'BEGIN LIST VAR ups', 'VAR ups ups.load "5"']);

		$result = apcupsd_nut_session($send, $recv, 'ups');

		expect($result['error'])->toContain('closed the connection');
	});

	it('gives up on a server that never ends the list', function () {
		$replies = ['upsd 2.8.0', 'BEGIN LIST VAR ups'];

		for ($i = 0; $i <= APCUPSD_NUT_MAX_LINES; $i++) {
			$replies[] = 'VAR ups x "1"';
		}

		[$send, $recv] = apcupsd_test_nut_transport($replies);

		$result = apcupsd_nut_session($send, $recv, 'ups');

		expect($result['error'])->toContain('too much data');
		expect($result['vars'])->toBe([]);
	});

	it('upgrades to TLS before anything else when requested', function () {
		[$send, $recv, $sent] = apcupsd_test_nut_transport([
			'OK STARTTLS', 'upsd 2.8.0', 'OK', 'OK', 'BEGIN LIST VAR ups', 'END LIST VAR ups',
		]);

		$upgraded = 0;
		$result   = apcupsd_nut_session($send, $recv, 'ups', 'monitor', 'pw', function() use (&$upgraded): bool {
			$upgraded++;

			return true;
		});

		expect($result['error'])->toBe('');
		expect($upgraded)->toBe(1);
		expect($sent->getArrayCopy()[0])->toBe('STARTTLS');
		expect($sent->getArrayCopy()[1])->toBe('VER');
	});

	it('stops when TLS is refused or fails', function () {
		[$send, $recv] = apcupsd_test_nut_transport(['ERR FEATURE-NOT-CONFIGURED']);

		expect(apcupsd_nut_session($send, $recv, 'ups', '', '', function(): bool {
			return true;
		})['error'])->toContain('FEATURE-NOT-CONFIGURED');

		[$send, $recv, $sent] = apcupsd_test_nut_transport(['OK STARTTLS', 'upsd 2.8.0']);

		expect(apcupsd_nut_session($send, $recv, 'ups', 'monitor', 'pw', function(): bool {
			return false;
		})['error'])->toContain('TLS negotiation');
		expect($sent->getArrayCopy())->toBe(['STARTTLS']);
	});
});

describe('NUT status translation', function () {
	it('maps NUT flags to the apcupsd vocabulary', function () {
		expect(apcupsd_nut_status('OL'))->toBe('ONLINE');
		expect(apcupsd_nut_status('OB DISCHRG'))->toBe('ONBATT');
		expect(apcupsd_nut_status('OL CHRG'))->toBe('ONLINE');
		expect(apcupsd_nut_status('ob lb'))->toBe('ONBATT LOWBATT');
		expect(apcupsd_nut_status('OL RB OVER FSD'))->toBe('ONLINE REPLACEBATT OVERLOAD SHUTTING DOWN');
	});

	it('keeps unknown flags, drops duplicates and tolerates empty input', function () {
		expect(apcupsd_nut_status('OL BYPASS OL'))->toBe('ONLINE BYPASS');
		expect(apcupsd_nut_status(''))->toBe('');
		expect(strlen(apcupsd_nut_status(str_repeat('FLAG ', 30) . 'END1 END2 END3 END4 END5')))->toBeLessThanOrEqual(64);
	});

	it('detects line power', function () {
		expect(apcupsd_nut_status_online('ONLINE'))->toBeTrue();
		expect(apcupsd_nut_status_online('ONLINE LOWBATT'))->toBeTrue();
		expect(apcupsd_nut_status_online('ONBATT'))->toBeFalse();
		expect(apcupsd_nut_status_online(''))->toBeFalse();
	});
});

describe('NUT stats row', function () {
	$ups = ['id' => '7', 'hostname' => 'nut.example.com', 'nut_ups_name' => 'rack1'];

	it('maps the variables onto apcupsd_ups_stats columns', function () use ($ups) {
		$stats = apcupsd_nut_build_stats([
			'ups.status'            => 'OL CHRG',
			'ups.model'             => ' Smart-UPS 1500 ',
			'device.model'          => 'ignored: ups.model wins',
			'ups.serial'            => 'AS1234',
			'ups.firmware'          => '652.18',
			'driver.name'           => 'usbhid-ups',
			'ups.load'              => '37',
			'ups.realpower.nominal' => '980',
			'input.voltage'         => '121.5',
			'output.voltage'        => '120',
			'battery.charge'        => '100',
			'battery.runtime'       => '1830',
			'battery.runtime.low'   => '120',
			'battery.date'          => '2025/01/02',
			'ambient.temperature'   => '24.5',
		], $ups, 'Network UPS Tools upsd 2.8.0');

		expect($stats['ups_id'])->toBe(7);
		expect($stats['ups_hostname'])->toBe('nut.example.com');
		expect($stats['ups_name'])->toBe('rack1');
		expect($stats['ups_version'])->toBe('Network UPS Tools upsd 2.8.0');
		expect($stats['ups_cable'])->toBe('Ethernet Link');
		expect($stats['ups_driver'])->toBe('usbhid-ups');
		expect($stats['ups_status'])->toBe('ONLINE');
		expect($stats['ups_battery_status'])->toBe('Normal');
		expect($stats['ups_model'])->toBe('Smart-UPS 1500');
		expect($stats['ups_serialno'])->toBe('AS1234');
		expect($stats['ups_firmware'])->toBe('652.18');
		expect($stats['ups_load_percent'])->toBe(37.0);
		expect($stats['ups_nominal_power'])->toBe(980.0);
		expect($stats['ups_line_voltage'])->toBe(121.5);
		expect($stats['ups_output_voltage'])->toBe(120.0);
		expect($stats['ups_battery_charge'])->toBe(100.0);
		expect($stats['ups_timeleft'])->toBe(30.5);
		expect($stats['ups_mintimel'])->toBe(2.0);
		expect($stats['ups_battery_date'])->toBe('2025/01/02');
		expect($stats['ups_ambtemp'])->toBe(24.5);
		expect($stats['ups_date'])->toBe($stats['ups_end_rec']);
	});

	it('falls back to device.* names and flags a low battery', function () use ($ups) {
		$stats = apcupsd_nut_build_stats(['ups.status' => 'OB LB', 'device.model' => 'Generic', 'device.serial' => 'X1'], $ups);

		expect($stats['ups_model'])->toBe('Generic');
		expect($stats['ups_serialno'])->toBe('X1');
		expect($stats['ups_status'])->toBe('ONBATT LOWBATT');
		expect($stats['ups_battery_status'])->toBe('Low');
		expect($stats['ups_version'])->toBe('NUT');
		expect($stats['ups_driver'])->toBe('NUT');
	});

	it('leaves out missing, empty and non-numeric values and truncates long text', function () use ($ups) {
		$stats = apcupsd_nut_build_stats([
			'ups.load'     => 'n/a',
			'ups.model'    => '   ',
			'ups.serial'   => str_repeat('S', 100),
			'input.voltage' => '',
		], $ups);

		expect($stats)->not->toHaveKey('ups_load_percent');
		expect($stats)->not->toHaveKey('ups_model');
		expect($stats)->not->toHaveKey('ups_line_voltage');
		expect($stats)->not->toHaveKey('ups_battery_charge');
		expect(strlen($stats['ups_serialno']))->toBe(64);
		expect($stats['ups_status'])->toBe('');
	});

	it('only maps columns that exist in the stats table', function () {
		require_once __DIR__ . '/../../includes/database.php';

		$columns = array_column(apcupsd_ups_stats_table_data()['columns'], 'name');

		foreach (array_keys(apcupsd_nut_variable_map()) as $column) {
			expect($columns)->toContain($column);
		}
	});
});

describe('timeout normalisation', function () {
	it('keeps sane values and defaults the rest', function () {
		expect(apcupsd_normalize_timeout(10))->toBe(10);
		expect(apcupsd_normalize_timeout('7'))->toBe(7);
		expect(apcupsd_normalize_timeout(null))->toBe(5);
		expect(apcupsd_normalize_timeout(''))->toBe(5);
		expect(apcupsd_normalize_timeout('abc'))->toBe(5);
		expect(apcupsd_normalize_timeout(0))->toBe(5);
		expect(apcupsd_normalize_timeout('-3'))->toBe(5);
		expect(apcupsd_normalize_timeout(null, 9))->toBe(9);
	});

	it('never allows more than two minutes', function () {
		expect(apcupsd_normalize_timeout(120))->toBe(120);
		expect(apcupsd_normalize_timeout(121))->toBe(120);
		expect(apcupsd_normalize_timeout('99999'))->toBe(120);
	});
});

describe('NUT TLS trust options', function () {
	it('accepts only plain absolute CA file paths', function () {
		foreach (['/etc/nut/upsd.pem', '/etc/ssl/certs/nut ca.pem', 'C:\\certs\\ca.pem', 'C:/certs/ca.pem'] as $path) {
			expect(apcupsd_nut_valid_cafile($path))->toBeTrue("path {$path}");
		}

		foreach (['', 'ca.pem', 'relative/ca.pem', 'phar:///tmp/x.phar/ca.pem', 'http://example.com/ca.pem', "/etc/nut/ca.pem\nx", "/etc/\0ca.pem", '/' . str_repeat('a', 255)] as $path) {
			expect(apcupsd_nut_valid_cafile($path))->toBeFalse('path ' . json_encode($path));
		}
	});

	it('verifies the certificate chain and host name by default', function () {
		$options = apcupsd_nut_ssl_options('nut.example.com');

		expect($options['verify_peer'])->toBeTrue();
		expect($options['verify_peer_name'])->toBeTrue();
		expect($options['allow_self_signed'])->toBeFalse();
		expect($options['peer_name'])->toBe('nut.example.com');
		expect($options)->not->toHaveKey('cafile');
	});

	it('trusts a CA / certificate file without weakening verification', function () {
		$options = apcupsd_nut_ssl_options('nut.example.com', '/etc/nut/upsd.pem');

		expect($options['cafile'])->toBe('/etc/nut/upsd.pem');
		expect($options['verify_peer'])->toBeTrue();
		expect($options['verify_peer_name'])->toBeTrue();
	});

	it('only turns verification off when self-signed certificates are explicitly allowed', function () {
		$options = apcupsd_nut_ssl_options('nut.example.com', '/etc/nut/upsd.pem', true);

		expect($options['verify_peer'])->toBeFalse();
		expect($options['verify_peer_name'])->toBeFalse();
		expect($options['allow_self_signed'])->toBeTrue();
		expect($options)->not->toHaveKey('cafile');
	});

	it('refuses an unreadable or unsafe CA file before connecting', function () {
		foreach (['/nonexistent/ca.pem', 'phar:///tmp/x.phar', 'relative.pem'] as $path) {
			expect(apcupsd_nut_query('127.0.0.1', 1, 'ups', '', '', true, 1, $path)['error'])->toContain('CA / certificate file cannot be read');
		}
	});

	it('ignores the CA file when TLS is off or self-signed certificates are allowed', function () {
		expect(apcupsd_nut_query('127.0.0.1', 1, 'ups', '', '', false, 1, '/nonexistent/ca.pem')['error'])->toContain('Unable to connect');
		expect(apcupsd_nut_query('127.0.0.1', 1, 'ups', '', '', true, 1, '/nonexistent/ca.pem', true)['error'])->toContain('Unable to connect');
	});
});

describe('NUT socket layer', function () {
	$pair = function (array $lines): array {
		$domain = DIRECTORY_SEPARATOR === '\\' ? STREAM_PF_INET : STREAM_PF_UNIX;
		[$client, $server] = stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

		fwrite($server, implode("\n", $lines) . ($lines === [] ? '' : "\n"));
		stream_socket_shutdown($server, STREAM_SHUT_WR);

		return [$client, $server];
	};

	it('speaks the protocol over a connected stream', function () use ($pair) {
		[$client, $server] = $pair(['upsd 2.8.0', 'BEGIN LIST VAR ups', 'VAR ups battery.charge "99"', 'END LIST VAR ups']);

		$result = apcupsd_nut_query_stream($client, 'nut.example.com', 'ups', '', '', false, 2);

		stream_set_blocking($server, false);
		$sent = (string) stream_get_contents($server);

		expect($result['error'])->toBe('');
		expect($result['vars'])->toBe(['battery.charge' => '99']);
		expect($sent)->toBe("VER\nLIST VAR ups\nLOGOUT\n");

		fclose($client);
		fclose($server);
	});

	it('reports a peer that hangs up before answering', function () use ($pair) {
		[$client, $server] = $pair([]);

		$result = apcupsd_nut_query_stream($client, 'nut.example.com', 'ups', '', '', false, 2);

		expect($result['error'])->toContain('closed the connection');

		fclose($client);
		fclose($server);
	});

	it('fails cleanly when the TLS handshake cannot complete', function () use ($pair) {
		[$client, $server] = $pair(['OK STARTTLS']);

		$result = apcupsd_nut_query_stream($client, 'nut.example.com', 'ups', 'monitor', 'pw', true, 2);

		expect($result['error'])->toContain('TLS negotiation');

		fclose($client);
		fclose($server);
	});

	it('rejects an unusable hostname, port or UPS name without connecting', function () {
		foreach ([['bad host', 3493, 'ups'], ['nut.example.com', 70000, 'ups'], ['nut.example.com', 3493, 'bad name']] as [$host, $port, $name]) {
			expect(apcupsd_nut_query($host, $port, $name)['error'])->toContain('Invalid NUT');
		}
	});

	it('reports a refused connection', function () {
		$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		$port     = (int) substr((string) strrchr((string) stream_socket_get_name($listener, false), ':'), 1);

		fclose($listener);

		$result = apcupsd_nut_query('127.0.0.1', $port, 'ups', '', '', false, 1);

		expect($result['error'])->toContain('Unable to connect to the NUT server');
	});

	it('stops at the overall deadline even when the server has more to say', function () use ($pair) {
		[$client, $server] = $pair(['upsd 2.8.0', 'BEGIN LIST VAR ups', 'VAR ups battery.charge "99"', 'END LIST VAR ups']);

		$started = microtime(true);
		$result  = apcupsd_nut_query_stream($client, 'nut.example.com', 'ups', '', '', false, 5, microtime(true) - 1);

		expect($result['error'])->toContain('did not answer within 5 seconds');
		expect(microtime(true) - $started)->toBeLessThan(2);

		fclose($client);
		fclose($server);
	});

	it('stops at the deadline when a server never finishes a line', function () {
		$domain = DIRECTORY_SEPARATOR === '\\' ? STREAM_PF_INET : STREAM_PF_UNIX;
		[$client, $server] = stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

		// A partial reply and then silence, with the connection held open: a blocking
		// fgets() would sit here for the whole read timeout instead of the deadline.
		fwrite($server, 'upsd 2.8');

		$started = microtime(true);
		$result  = apcupsd_nut_query_stream($client, 'nut.example.com', 'ups', '', '', false, 30, microtime(true) + 1);

		expect($result['error'])->toContain('did not answer within 30 seconds');
		expect(microtime(true) - $started)->toBeLessThan(3);

		fclose($client);
		fclose($server);
	});

	it('gives up on a line that never ends', function () use ($pair) {
		[$client, $server] = $pair([str_repeat('a', 9000)]);

		$result = apcupsd_nut_query_stream($client, 'nut.example.com', 'ups', '', '', false, 2);

		expect($result['error'])->toContain('closed the connection');

		fclose($client);
		fclose($server);
	});

	it('reads replies that arrive in pieces and with CRLF endings', function () {
		$domain = DIRECTORY_SEPARATOR === '\\' ? STREAM_PF_INET : STREAM_PF_UNIX;
		[$client, $server] = stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

		fwrite($server, "upsd 2.8.0\r\nBEGIN LIST VAR ups\r\nVAR ups battery.ch");
		fwrite($server, "arge \"88\"\r\nEND LIST VAR ups\r\n");
		stream_socket_shutdown($server, STREAM_SHUT_WR);

		$result = apcupsd_nut_query_stream($client, 'nut.example.com', 'ups', '', '', false, 2);

		expect($result['error'])->toBe('');
		expect($result['vars'])->toBe(['battery.charge' => '88']);

		fclose($client);
		fclose($server);
	});

	it('connects over TCP and reports a server that never answers', function () {
		$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		$port     = (int) substr((string) strrchr((string) stream_socket_get_name($listener, false), ':'), 1);

		$started = microtime(true);
		$result  = apcupsd_nut_query('127.0.0.1', (string) $port, 'ups', '', '', false, 1);

		expect($result['error'])->toContain('did not answer within 1 seconds');

		// One second to wait for the reply, never more than twice the timeout in total.
		expect(microtime(true) - $started)->toBeLessThan(3);

		fclose($listener);
	});

	it('brackets IPv6 literals in the connection target', function () {
		$result = apcupsd_nut_query('::1', 1, 'ups', '', '', false, 1);

		expect($result['error'])->toContain('[::1]:1');
	});
});
