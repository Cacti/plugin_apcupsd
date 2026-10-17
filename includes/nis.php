<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | Native PHP client for the apcupsd Network Information Server (NIS).     |
 | The 'apcaccess' command is not needed: the protocol is a request and a  |
 | stream of length-prefixed text records on TCP 3551, spoken here over a  |
 | plain PHP stream. It is what 'apcaccess -u -h host:port' does.          |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once(__DIR__ . '/functions.php');

// The host validation is not specific to NUT, so it is shared rather than copied.
require_once(__DIR__ . '/nut.php');

if (!defined('APCUPSD_NIS_DEFAULT_PORT')) {
	define('APCUPSD_NIS_DEFAULT_PORT', 3551);
}

// A status reply is about seventy records; the cap stops a broken or hostile server from exhausting memory.
if (!defined('APCUPSD_NIS_MAX_RECORDS')) {
	define('APCUPSD_NIS_MAX_RECORDS', 1000);
}

// Records are single "KEY : value" lines, so anything this long is not a status record.
if (!defined('APCUPSD_NIS_MAX_RECORD_BYTES')) {
	define('APCUPSD_NIS_MAX_RECORD_BYTES', 4096);
}

if (!function_exists('apcupsd_nis_strip_units')) {
	/**
	 * Remove the unit that apcupsd appends to a numeric value ("121.0 Volts"
	 * becomes "121.0"), as 'apcaccess -u' does. Only a number followed by a
	 * known unit word is changed, so text such as a model name or a date is
	 * returned as it is.
	 *
	 * @param string $value The value as sent by apcupsd.
	 *
	 * @return string The value without its unit.
	 */
	function apcupsd_nis_strip_units(string $value): string {
		if (preg_match('/^(-?[0-9]+(?:\.[0-9]+)?)\s+(?:Volts|Percent|Seconds|Minutes|Hours|Days|Watts|Hz|C|F|Amps|VA)$/', $value, $matches) === 1) {
			return $matches[1];
		}

		return $value;
	}
}

if (!function_exists('apcupsd_nis_parse_record')) {
	/**
	 * Split one status record ("LINEV    : 121.0 Volts") into its key and value.
	 * Only the first colon separates them, so a value such as a time stamp keeps
	 * its own colons.
	 *
	 * @param string $record The record as sent, with or without its line ending.
	 *
	 * @return array{0: string, 1: string}|null The key and value, or null when the record is not a "KEY : value" line.
	 */
	function apcupsd_nis_parse_record(string $record): ?array {
		$colon = strpos($record, ':');

		if ($colon === false) {
			return null;
		}

		$key = trim(substr($record, 0, $colon));

		if ($key === '' || strlen($key) > 32) {
			return null;
		}

		return [$key, trim(substr($record, $colon + 1))];
	}
}

if (!function_exists('apcupsd_nis_session')) {
	/**
	 * Run one NIS conversation over a transport: send the 'status' request and
	 * read records until the server ends the reply with an empty one. The
	 * transport is injected as callables so the protocol logic does not depend
	 * on a live socket.
	 *
	 * @param callable $send Sends the given bytes.
	 * @param callable $read Returns exactly the requested number of bytes, or null at EOF or when time runs out.
	 *
	 * @return array{vars: array<string, string>, error: string} The values by key, without units. An empty 'error' means success.
	 */
	function apcupsd_nis_session(callable $send, callable $read): array {
		$result = ['vars' => [], 'error' => ''];

		$send(pack('n', 6) . 'status');

		for ($count = 0; $count < APCUPSD_NIS_MAX_RECORDS; $count++) {
			$header = $read(2);

			if ($header === null) {
				$result['vars']  = [];
				$result['error'] = __('The apcupsd server closed the connection unexpectedly.', 'apcupsd');

				return $result;
			}

			$length = unpack('n', $header)[1];

			if ($length === 0) {
				if (!count($result['vars'])) {
					$result['error'] = __('The apcupsd server returned no status data.', 'apcupsd');
				}

				return $result;
			}

			$record = $length > APCUPSD_NIS_MAX_RECORD_BYTES ? null : $read($length);

			if ($record === null) {
				$result['vars']  = [];
				$result['error'] = $length > APCUPSD_NIS_MAX_RECORD_BYTES
					? __('The apcupsd server sent an oversized record.', 'apcupsd')
					: __('The apcupsd server closed the connection unexpectedly.', 'apcupsd');

				return $result;
			}

			$parsed = apcupsd_nis_parse_record($record);

			if ($parsed !== null) {
				$result['vars'][$parsed[0]] = apcupsd_nis_strip_units($parsed[1]);
			}
		}

		$result['vars']  = [];
		$result['error'] = __('The apcupsd server sent too much data.', 'apcupsd');

		return $result;
	}
}

if (!function_exists('apcupsd_nis_query_stream')) {
	/**
	 * Run a NIS conversation over an already connected stream.
	 *
	 * @param resource   $stream   The connected stream.
	 * @param int        $timeout  The UPS's timeout in seconds; used for the default deadline and the timeout message.
	 * @param float|null $deadline Unix time (microtime) the whole exchange must end by; defaults to twice the timeout from now.
	 *                             Reads are polled against it without blocking, so it holds even when a server trickles bytes.
	 *
	 * @return array{vars: array<string, string>, error: string}
	 */
	function apcupsd_nis_query_stream($stream, int $timeout, ?float $deadline = null): array {
		$deadline  = $deadline ?? microtime(true) + ($timeout * 2);
		$buffer    = '';
		$timed_out = false;

		$send = function(string $data) use ($stream): void {
			fwrite($stream, $data);
		};

		// Reads are non-blocking and polled against the deadline, so a server that
		// trickles bytes without finishing a record cannot hold the poller open.
		stream_set_blocking($stream, false);

		$read = function(int $bytes) use ($stream, $deadline, &$buffer, &$timed_out): ?string {
			while (strlen($buffer) < $bytes) {
				if (microtime(true) >= $deadline) {
					$timed_out = true;

					return null;
				}

				$chunk = fread($stream, 8192);

				if ($chunk === false || ($chunk === '' && feof($stream))) {
					return null;
				}

				if ($chunk === '') {
					usleep(10000);
				}

				$buffer .= $chunk;
			}

			$data   = substr($buffer, 0, $bytes);
			$buffer = (string) substr($buffer, $bytes);

			return $data;
		};

		$result = apcupsd_nis_session($send, $read);

		if ($timed_out) {
			$result['error'] = __('The apcupsd server did not answer within %s seconds.', $timeout, 'apcupsd');
		}

		return $result;
	}
}

if (!function_exists('apcupsd_nis_query')) {
	/**
	 * Connect to an apcupsd NIS server over TCP and read the UPS's status. The
	 * connect is bounded by the timeout and the whole exchange by twice the
	 * timeout, the same bounds the NUT collector uses.
	 *
	 * @param string $hostname The apcupsd server's hostname or IP address.
	 * @param mixed  $port     The apcupsd server's TCP port; 3551 when blank.
	 * @param mixed  $timeout  The UPS's timeout in seconds, from 1 to 120; 5 when blank.
	 *
	 * @return array{vars: array<string, string>, error: string}
	 */
	function apcupsd_nis_query(string $hostname, $port, $timeout = 5): array {
		$started  = microtime(true);
		$hostname = trim($hostname);
		$port     = apcupsd_normalize_positive_int($port, APCUPSD_NIS_DEFAULT_PORT);
		$timeout  = apcupsd_normalize_timeout($timeout);

		if (!apcupsd_nut_valid_host($hostname) || $port > 65535) {
			return ['vars' => [], 'error' => __('Invalid apcupsd hostname or port configuration', 'apcupsd')];
		}

		$target = (strpos($hostname, ':') !== false ? '[' . $hostname . ']' : $hostname) . ':' . $port;
		$stream = @stream_socket_client('tcp://' . $target, $errno, $errstr, $timeout);

		if ($stream === false) {
			return ['vars' => [], 'error' => __('Unable to connect to apcupsd at %s: %s', $target, $errstr, 'apcupsd')];
		}

		$result = apcupsd_nis_query_stream($stream, $timeout, $started + ($timeout * 2));

		fclose($stream);

		return $result;
	}
}
