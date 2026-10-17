<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The apcupsd collector speaks the apcupsd Network Information Server (NIS)
 * protocol itself instead of running 'apcaccess'. A request is the text
 * 'status' with a 2-byte big-endian length in front of it; the reply is a run
 * of "KEY : value" records, each with its own 2-byte length, ended by an
 * empty record.
 */

require_once __DIR__ . '/../../includes/nis.php';

/**
 * Frame records the way an apcupsd server sends them.
 *
 * @param array<int, string> $records The text of each record.
 * @param bool               $end     Whether to end the reply with the empty record.
 */
function apcupsd_test_nis_reply(array $records, bool $end = true): string {
	$out = '';

	foreach ($records as $record) {
		$out .= pack('n', strlen($record)) . $record;
	}

	return $out . ($end ? pack('n', 0) : '');
}

describe('apcupsd unit stripping', function () {
	it('removes the unit from a number, as apcaccess -u does', function () {
		foreach (['121.0 Volts' => '121.0', '23.0 Percent' => '23.0', '30 Seconds' => '30', '30.5 Minutes' => '30.5', '60.0 Hz' => '60.0', '28.0 C' => '28.0', '900 Watts' => '900', '1.80 Amps' => '1.80', '-5.0 C' => '-5.0'] as $raw => $expected) {
			expect(apcupsd_nis_strip_units($raw))->toBe($expected);
		}
	});

	it('leaves text, dates and bare numbers alone', function () {
		foreach (['Back-UPS XS 1500M', '2026-10-16 21:09:13 -0400', '121.0', 'ONLINE', 'No alarm', '12 Volts of power', ''] as $value) {
			expect(apcupsd_nis_strip_units($value))->toBe($value);
		}
	});
});

describe('apcupsd record parsing', function () {
	it('splits a record on the first colon only', function () {
		expect(apcupsd_nis_parse_record("LINEV    : 121.0 Volts\n"))->toBe(['LINEV', '121.0 Volts']);
		expect(apcupsd_nis_parse_record('DATE     : 2026-10-16 21:09:13 -0400  '))->toBe(['DATE', '2026-10-16 21:09:13 -0400']);
		expect(apcupsd_nis_parse_record('END APC  : 2026-10-16 21:09:13 -0400'))->toBe(['END APC', '2026-10-16 21:09:13 -0400']);
		expect(apcupsd_nis_parse_record('MODEL    : Smart-UPS: 1500'))->toBe(['MODEL', 'Smart-UPS: 1500']);
	});

	it('ignores records that are not KEY : value lines', function () {
		expect(apcupsd_nis_parse_record('no separator here'))->toBeNull();
		expect(apcupsd_nis_parse_record(': value without a key'))->toBeNull();
		expect(apcupsd_nis_parse_record(str_repeat('K', 40) . ' : too long a key'))->toBeNull();
	});
});

describe('apcupsd NIS session', function () {
	$run = function (string $reply): array {
		$sent = '';
		$pos  = 0;

		$result = apcupsd_nis_session(
			function (string $data) use (&$sent): void {
				$sent .= $data;
			},
			function (int $bytes) use ($reply, &$pos): ?string {
				if ($pos + $bytes > strlen($reply)) {
					return null;
				}

				$out  = substr($reply, $pos, $bytes);
				$pos += $bytes;

				return $out;
			}
		);

		return [$result, $sent];
	};

	it('asks for the status and reads every record up to the empty one', function () use ($run) {
		[$result, $sent] = $run(apcupsd_test_nis_reply(["APC      : 001,036,0866\n", "STATUS   : ONLINE \n", "LINEV    : 121.0 Volts\n", "BCHARGE  : 100.0 Percent\n"]));

		expect($sent)->toBe("\x00\x06status");
		expect($result['error'])->toBe('');
		expect($result['vars'])->toBe(['APC' => '001,036,0866', 'STATUS' => 'ONLINE', 'LINEV' => '121.0', 'BCHARGE' => '100.0']);
	});

	it('skips records that are not KEY : value lines', function () use ($run) {
		[$result] = $run(apcupsd_test_nis_reply(["garbage\n", "STATUS   : ONLINE\n"]));

		expect($result['vars'])->toBe(['STATUS' => 'ONLINE']);
	});

	it('reports a reply with no records', function () use ($run) {
		[$result] = $run(apcupsd_test_nis_reply([]));

		expect($result['error'])->toBe('The apcupsd server returned no status data.');
	});

	it('reports a connection that closes before the reply is complete', function () use ($run) {
		[$result] = $run(apcupsd_test_nis_reply(["STATUS   : ONLINE\n"], false));

		expect($result['error'])->toBe('The apcupsd server closed the connection unexpectedly.');
		expect($result['vars'])->toBe([]);

		[$result] = $run(pack('n', 20) . 'STATUS');

		expect($result['error'])->toBe('The apcupsd server closed the connection unexpectedly.');
	});

	it('refuses an oversized record without reading it', function () use ($run) {
		[$result] = $run(pack('n', 60000) . str_repeat('A', 60000));

		expect($result['error'])->toBe('The apcupsd server sent an oversized record.');
		expect($result['vars'])->toBe([]);
	});

	it('gives up on a server that never ends the reply', function () use ($run) {
		[$result] = $run(apcupsd_test_nis_reply(array_fill(0, APCUPSD_NIS_MAX_RECORDS + 5, "A        : 1\n"), false));

		expect($result['error'])->toBe('The apcupsd server sent too much data.');
		expect($result['vars'])->toBe([]);
	});
});

describe('apcupsd socket layer', function () {
	$pair = function (string $reply, bool $hangup = true): array {
		$domain = DIRECTORY_SEPARATOR === '\\' ? STREAM_PF_INET : STREAM_PF_UNIX;
		[$client, $server] = stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

		fwrite($server, $reply);

		if ($hangup) {
			stream_socket_shutdown($server, STREAM_SHUT_WR);
		}

		return [$client, $server];
	};

	it('speaks the protocol over a connected stream', function () use ($pair) {
		[$client, $server] = $pair(apcupsd_test_nis_reply(["STATUS   : ONLINE\n", "TIMELEFT : 30.5 Minutes\n"]));

		$result = apcupsd_nis_query_stream($client, 2);

		stream_set_blocking($server, false);

		expect($result['error'])->toBe('');
		expect($result['vars'])->toBe(['STATUS' => 'ONLINE', 'TIMELEFT' => '30.5']);
		expect((string) stream_get_contents($server))->toBe("\x00\x06status");

		fclose($client);
		fclose($server);
	});

	it('reads a reply that arrives in pieces, splitting a length and a record', function () use ($pair) {
		$reply  = apcupsd_test_nis_reply(["LINEV    : 121.0 Volts\n", "STATUS   : ONLINE\n"]);
		$domain = DIRECTORY_SEPARATOR === '\\' ? STREAM_PF_INET : STREAM_PF_UNIX;
		[$client, $server] = stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

		fwrite($server, substr($reply, 0, 1));
		fwrite($server, substr($reply, 1, 9));
		fwrite($server, substr($reply, 10));
		stream_socket_shutdown($server, STREAM_SHUT_WR);

		$result = apcupsd_nis_query_stream($client, 2);

		expect($result['error'])->toBe('');
		expect($result['vars'])->toBe(['LINEV' => '121.0', 'STATUS' => 'ONLINE']);

		fclose($client);
		fclose($server);
	});

	it('reports a peer that hangs up without answering', function () use ($pair) {
		[$client, $server] = $pair('');

		expect(apcupsd_nis_query_stream($client, 2)['error'])->toContain('closed the connection');

		fclose($client);
		fclose($server);
	});

	it('stops at the overall deadline even when the server has more to say', function () use ($pair) {
		[$client, $server] = $pair(apcupsd_test_nis_reply(["STATUS   : ONLINE\n"]));

		$started = microtime(true);
		$result  = apcupsd_nis_query_stream($client, 5, microtime(true) - 1);

		expect($result['error'])->toBe('The apcupsd server did not answer within 5 seconds.');
		expect(microtime(true) - $started)->toBeLessThan(2);

		fclose($client);
		fclose($server);
	});

	it('stops at the deadline when a server stops in the middle of a record', function () use ($pair) {
		// A length that promises more than is ever sent, with the connection held open: a blocking
		// read would sit here for the whole stream timeout instead of the deadline.
		[$client, $server] = $pair(pack('n', 40) . 'STATUS', false);

		$started = microtime(true);
		$result  = apcupsd_nis_query_stream($client, 30, microtime(true) + 1);

		expect($result['error'])->toBe('The apcupsd server did not answer within 30 seconds.');
		expect(microtime(true) - $started)->toBeLessThan(3);

		fclose($client);
		fclose($server);
	});

	it('connects over TCP and reports a server that never answers, within twice the timeout', function () {
		$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		$port     = (int) substr((string) strrchr((string) stream_socket_get_name($listener, false), ':'), 1);

		$started = microtime(true);
		$result  = apcupsd_nis_query('127.0.0.1', (string) $port, 1);

		expect($result['error'])->toBe('The apcupsd server did not answer within 1 seconds.');
		expect(microtime(true) - $started)->toBeLessThan(3);

		fclose($listener);
	});

	it('reports a refused connection', function () {
		$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		$port     = (int) substr((string) strrchr((string) stream_socket_get_name($listener, false), ':'), 1);

		fclose($listener);

		$result = apcupsd_nis_query('127.0.0.1', $port, 1);

		expect($result['error'])->toStartWith('Unable to connect to apcupsd at 127.0.0.1:' . $port . ': ');
	});

	it('brackets IPv6 literals in the connection target', function () {
		expect(apcupsd_nis_query('::1', 1, 1)['error'])->toContain('[::1]:1');
	});

	it('uses the standard port and the default timeout for blank settings', function () {
		expect(APCUPSD_NIS_DEFAULT_PORT)->toBe(3551);
		expect(apcupsd_normalize_timeout(null))->toBe(5);
		expect(apcupsd_normalize_timeout(500))->toBe(120);
	});
});
