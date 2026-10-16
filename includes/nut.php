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
 | Native PHP client for the Network UPS Tools (NUT) upsd protocol.        |
 | No CLI tools are needed: the protocol is a line-based text protocol on  |
 | TCP 3493, spoken here over a plain PHP stream.                          |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once(__DIR__ . '/functions.php');

if (!defined('APCUPSD_NUT_DEFAULT_PORT')) {
	define('APCUPSD_NUT_DEFAULT_PORT', 3493);
}

// Upper bound on the lines read for one LIST VAR so a hostile or broken server cannot exhaust memory.
if (!defined('APCUPSD_NUT_MAX_LINES')) {
	define('APCUPSD_NUT_MAX_LINES', 4000);
}

if (!function_exists('apcupsd_nut_valid_host')) {
	/**
	 * Whether a value is acceptable as the NUT server's hostname: a DNS name or
	 * an IPv4/IPv6 address. Validated before any socket is opened.
	 *
	 * @param mixed $hostname The configured hostname.
	 *
	 * @return bool True when the hostname is safe to connect to.
	 */
	function apcupsd_nut_valid_host($hostname): bool {
		$hostname = trim((string) $hostname);

		return $hostname !== ''
			&& (preg_match('/^[a-zA-Z0-9.\-]{1,253}$/', $hostname) === 1 || filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false);
	}
}

if (!function_exists('apcupsd_nut_valid_ups_name')) {
	/**
	 * Whether a value is a legal NUT UPS name (the name from `LIST UPS`).
	 *
	 * @param mixed $name The configured NUT UPS name.
	 *
	 * @return bool True when the name only holds letters, digits, '_', '.' and '-'.
	 */
	function apcupsd_nut_valid_ups_name($name): bool {
		return preg_match('/^[A-Za-z0-9_.\-]{1,64}$/', (string) $name) === 1;
	}
}

if (!function_exists('apcupsd_nut_quote')) {
	/**
	 * Quote a value for use as a NUT protocol argument. Backslashes and double
	 * quotes are escaped; values holding control characters (which could inject
	 * a second protocol line) are rejected.
	 *
	 * @param mixed $value The raw argument.
	 *
	 * @return string|null The double-quoted argument, or null when it cannot be sent safely.
	 */
	function apcupsd_nut_quote($value): ?string {
		$value = (string) $value;

		if (preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
			return null;
		}

		return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
	}
}

if (!function_exists('apcupsd_nut_tokenize')) {
	/**
	 * Split one line of the NUT protocol into words. Words are separated by
	 * whitespace; a double-quoted word may hold spaces and uses backslash to
	 * escape '"' and '\'.
	 *
	 * @param string $line The line, without its line ending.
	 *
	 * @return array<int, string>|null The words, or null when a quote is never closed.
	 */
	function apcupsd_nut_tokenize(string $line): ?array {
		$tokens = [];
		$length = strlen($line);
		$i      = 0;

		while ($i < $length) {
			$char = $line[$i];

			if ($char === ' ' || $char === "\t") {
				$i++;

				continue;
			}

			$token = '';

			if ($char === '"') {
				$closed = false;
				$i++;

				while ($i < $length) {
					$char = $line[$i];

					if ($char === '\\' && $i + 1 < $length) {
						$token .= $line[$i + 1];
						$i += 2;

						continue;
					}

					$i++;

					if ($char === '"') {
						$closed = true;

						break;
					}

					$token .= $char;
				}

				if (!$closed) {
					return null;
				}
			} else {
				while ($i < $length && $line[$i] !== ' ' && $line[$i] !== "\t") {
					$token .= $line[$i];
					$i++;
				}
			}

			$tokens[] = $token;
		}

		return $tokens;
	}
}

if (!function_exists('apcupsd_nut_error')) {
	/**
	 * Turn an "ERR <code>" reply into a message suitable for the UPS error column.
	 *
	 * @param string $line The ERR line sent by upsd.
	 * @param string $ups  The NUT UPS name that was queried.
	 *
	 * @return string The translated message.
	 */
	function apcupsd_nut_error(string $line, string $ups): string {
		$parts = explode(' ', trim($line));
		$code  = preg_replace('/[^A-Za-z0-9\-]/', '', $parts[1] ?? '');

		switch ($code) {
			case 'ACCESS-DENIED':
			case 'PASSWORD-REQUIRED':
			case 'USERNAME-REQUIRED':
			case 'INVALID-PASSWORD':
			case 'INVALID-USERNAME':
				return __('The NUT server denied access. Check the NUT username and password.', 'apcupsd');
			case 'UNKNOWN-UPS':
				return sprintf(__('The NUT server does not know a UPS named "%s".', 'apcupsd'), $ups);
			case 'DATA-STALE':
				return __('The NUT server has stale data for this UPS: its driver is not responding.', 'apcupsd');
			default:
				return sprintf(__('The NUT server reported an error: %s', 'apcupsd'), $code === '' ? 'ERR' : $code);
		}
	}
}

if (!function_exists('apcupsd_nut_session')) {
	/**
	 * Run one NUT conversation over a transport: optionally upgrade to TLS,
	 * identify the server, authenticate when credentials are given, and read
	 * every variable of one UPS. The transport is injected as callables so the
	 * protocol logic does not depend on a live socket.
	 *
	 * @param callable      $send       Sends one command line (no line ending).
	 * @param callable      $recv       Returns the next line without its ending, or null at EOF/timeout.
	 * @param string        $ups        The NUT UPS name to read.
	 * @param string        $username   The upsd.users user; empty for anonymous read-only access.
	 * @param string        $password   The upsd.users password.
	 * @param callable|null $enable_tls Upgrades the connection after STARTTLS; null to stay plain text.
	 *
	 * @return array{vars: array<string, string>, version: string, error: string} An empty 'error' means success.
	 */
	function apcupsd_nut_session(callable $send, callable $recv, string $ups, string $username = '', string $password = '', ?callable $enable_tls = null): array {
		$result = ['vars' => [], 'version' => '', 'error' => ''];

		if (!apcupsd_nut_valid_ups_name($ups)) {
			$result['error'] = __('Invalid NUT hostname, port or UPS name configuration', 'apcupsd');

			return $result;
		}

		// Sends a command and returns [success, first reply line or error message].
		$reply = function(string $command) use ($send, $recv, $ups): array {
			$send($command);

			$line = $recv();

			if ($line === null) {
				return [false, __('The NUT server closed the connection unexpectedly.', 'apcupsd')];
			}

			return strncmp($line, 'ERR', 3) === 0 ? [false, apcupsd_nut_error($line, $ups)] : [true, $line];
		};

		$steps = [];

		if ($enable_tls !== null) {
			$steps[] = ['STARTTLS', 'tls'];
		}

		$steps[] = ['VER', 'version'];

		if ($username !== '') {
			$quoted_user = apcupsd_nut_quote($username);
			$quoted_pass = apcupsd_nut_quote($password);

			if ($quoted_user === null || $quoted_pass === null) {
				$result['error'] = __('The NUT username or password contains characters that cannot be sent.', 'apcupsd');

				return $result;
			}

			$steps[] = ['USERNAME ' . $quoted_user, 'auth'];
			$steps[] = ['PASSWORD ' . $quoted_pass, 'auth'];
		}

		foreach ($steps as [$command, $kind]) {
			[$ok, $text] = $reply($command);

			if (!$ok) {
				$result['error'] = $text;

				return $result;
			}

			if ($kind === 'version') {
				$result['version'] = substr(trim($text), 0, 64);
			}

			if ($kind === 'tls' && !$enable_tls()) {
				$result['error'] = __('TLS negotiation with the NUT server failed.', 'apcupsd');

				return $result;
			}
		}

		[$ok, $text] = $reply('LIST VAR ' . $ups);

		if (!$ok) {
			$result['error'] = $text;

			return $result;
		}

		$done = false;

		for ($count = 0; $count < APCUPSD_NUT_MAX_LINES && !$done; $count++) {
			$line = $recv();

			if ($line === null) {
				$result['error'] = __('The NUT server closed the connection unexpectedly.', 'apcupsd');

				return $result;
			}

			$tokens = apcupsd_nut_tokenize($line) ?? [];
			$done   = ($tokens[0] ?? '') === 'END';

			if (count($tokens) === 4 && $tokens[0] === 'VAR') {
				$result['vars'][$tokens[2]] = $tokens[3];
			}
		}

		if (!$done) {
			$result['vars']  = [];
			$result['error'] = __('The NUT server sent too much data.', 'apcupsd');

			return $result;
		}

		$send('LOGOUT');

		return $result;
	}
}

if (!function_exists('apcupsd_nut_query_stream')) {
	/**
	 * Run a NUT conversation over an already connected stream.
	 *
	 * @param resource   $stream   The connected stream.
	 * @param string     $hostname The server name (kept for TLS peer verification, set on the stream context).
	 * @param string     $ups      The NUT UPS name to read.
	 * @param string     $username The upsd.users user; empty for anonymous access.
	 * @param string     $password The upsd.users password.
	 * @param bool       $tls      Whether to require STARTTLS before sending credentials.
	 * @param int        $timeout  Seconds the exchange may take per step; used for the default deadline and the timeout message.
	 * @param float|null $deadline Unix time (microtime) the whole exchange must end by; defaults to twice the timeout from now.
	 *                             Reads are polled against it without blocking, so it holds even when a server trickles bytes.
	 *
	 * @return array{vars: array<string, string>, version: string, error: string}
	 */
	function apcupsd_nut_query_stream($stream, string $hostname, string $ups, string $username, string $password, bool $tls, int $timeout, ?float $deadline = null): array {
		$deadline = $deadline ?? microtime(true) + ($timeout * 2);
		$buffer   = '';

		// A command is a few hundred bytes written into an empty socket buffer, so it completes in one call.
		$send = function(string $command) use ($stream): void {
			fwrite($stream, $command . "\n");
		};

		// Reads are non-blocking and polled against the deadline. fgets() would block for the
		// stream timeout on every read, so a server dripping bytes without ever finishing a
		// line could hold it open well past the deadline.
		stream_set_blocking($stream, false);

		$recv = function() use ($stream, $deadline, &$buffer): ?string {
			while (($end = strpos($buffer, "\n")) === false) {
				if (microtime(true) >= $deadline || strlen($buffer) > 8192) {
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

			$line   = substr($buffer, 0, $end);
			$buffer = (string) substr($buffer, $end + 1);

			return rtrim($line, "\r");
		};

		// The handshake needs a blocking stream, bounded by what is left of the deadline.
		$enable_tls = $tls ? function() use ($stream, $deadline): bool {
			stream_set_blocking($stream, true);
			stream_set_timeout($stream, max(1, (int) ceil($deadline - microtime(true))));

			$enabled = (bool) @stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

			stream_set_blocking($stream, false);

			return $enabled;
		} : null;

		$result = apcupsd_nut_session($send, $recv, $ups, $username, $password, $enable_tls);

		// A read that gave up because time ran out looks like a closed connection to the session.
		if ($result['error'] === __('The NUT server closed the connection unexpectedly.', 'apcupsd') && microtime(true) >= $deadline) {
			$result['error'] = sprintf(__('The NUT server did not answer within %s seconds.', 'apcupsd'), $timeout);
		}

		return $result;
	}
}

if (!function_exists('apcupsd_nut_valid_cafile')) {
	/**
	 * Whether a value is acceptable as the path of the CA / certificate file used
	 * to verify the NUT server: an absolute local path with no stream wrapper
	 * (such as phar:// or http://) and no control characters.
	 *
	 * @param mixed $path The configured path.
	 *
	 * @return bool True when the path is a plain absolute file path.
	 */
	function apcupsd_nut_valid_cafile($path): bool {
		$path = (string) $path;

		return strlen($path) <= 255
			&& strpos($path, '://') === false
			&& preg_match('#^(/|[A-Za-z]:[\\\\/])[^\x00-\x1f\x7f]+$#', $path) === 1;
	}
}

if (!function_exists('apcupsd_nut_ssl_options')) {
	/**
	 * The SSL stream-context options used for the STARTTLS upgrade. By default
	 * the certificate chain and host name are verified against the system trust
	 * store. A CA / certificate file makes that file the trust anchor instead,
	 * which is how a self-signed server certificate is trusted without weakening
	 * verification. Allowing self-signed certificates turns verification off, so
	 * the traffic is encrypted but the server's identity is not checked.
	 *
	 * @param string $hostname          The NUT server's hostname, used for SNI and name verification.
	 * @param string $cafile            Optional PEM file to verify the server against.
	 * @param bool   $allow_self_signed Accept any certificate without verifying it.
	 *
	 * @return array<string, mixed> The 'ssl' context options.
	 */
	function apcupsd_nut_ssl_options(string $hostname, string $cafile = '', bool $allow_self_signed = false): array {
		$options = [
			'verify_peer'       => !$allow_self_signed,
			'verify_peer_name'  => !$allow_self_signed,
			'allow_self_signed' => $allow_self_signed,
			'peer_name'         => $hostname,
			'SNI_enabled'       => true,
		];

		if ($cafile !== '' && !$allow_self_signed) {
			$options['cafile'] = $cafile;
		}

		return $options;
	}
}

if (!function_exists('apcupsd_nut_query')) {
	/**
	 * Connect to a NUT server over TCP and read every variable of one UPS. The
	 * connect and the whole conversation together are bounded by twice the
	 * timeout. When TLS is requested the certificate and host name are verified
	 * (see apcupsd_nut_ssl_options()), so install the server's CA, point the UPS
	 * at a CA / certificate file, or allow self-signed certificates for STARTTLS
	 * to succeed.
	 *
	 * @param string $hostname          The NUT server's hostname or IP address.
	 * @param mixed  $port              The NUT server's TCP port.
	 * @param string $ups               The NUT UPS name to read.
	 * @param string $username          The upsd.users user; empty for anonymous access.
	 * @param string $password          The upsd.users password.
	 * @param bool   $tls               Whether to require STARTTLS.
	 * @param int    $timeout           Seconds allowed to connect and per read, clamped to 1-120.
	 * @param string $cafile            Optional PEM file to verify the server certificate against.
	 * @param bool   $allow_self_signed Accept any certificate without verifying it (encrypts only).
	 *
	 * @return array{vars: array<string, string>, version: string, error: string}
	 */
	function apcupsd_nut_query(string $hostname, $port, string $ups, string $username = '', string $password = '', bool $tls = false, int $timeout = 5, string $cafile = '', bool $allow_self_signed = false): array {
		$started  = microtime(true);
		$hostname = trim($hostname);
		$port     = apcupsd_normalize_positive_int($port, APCUPSD_NUT_DEFAULT_PORT);
		$timeout  = apcupsd_normalize_timeout($timeout);
		$cafile   = trim($cafile);

		if (!apcupsd_nut_valid_host($hostname) || $port > 65535 || !apcupsd_nut_valid_ups_name($ups)) {
			return ['vars' => [], 'version' => '', 'error' => __('Invalid NUT hostname, port or UPS name configuration', 'apcupsd')];
		}

		if ($tls && !$allow_self_signed && $cafile !== '' && (!apcupsd_nut_valid_cafile($cafile) || !is_readable($cafile))) {
			return ['vars' => [], 'version' => '', 'error' => __('The NUT CA / certificate file cannot be read.', 'apcupsd')];
		}

		$target  = (strpos($hostname, ':') !== false ? '[' . $hostname . ']' : $hostname) . ':' . $port;
		$context = stream_context_create(['ssl' => apcupsd_nut_ssl_options($hostname, $cafile, $allow_self_signed)]);

		$stream = @stream_socket_client('tcp://' . $target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

		if ($stream === false) {
			return ['vars' => [], 'version' => '', 'error' => sprintf(__('Unable to connect to the NUT server %s: %s', 'apcupsd'), $target, $errstr)];
		}

		$result = apcupsd_nut_query_stream($stream, $hostname, $ups, $username, $password, $tls, $timeout, $started + ($timeout * 2));

		fclose($stream);

		return $result;
	}
}

if (!function_exists('apcupsd_nut_status')) {
	/**
	 * Translate NUT's ups.status flags (OL, OB, LB, RB, ...) into the apcupsd
	 * vocabulary the rest of the plugin matches on (ONLINE, ONBATT, LOWBATT, ...).
	 * Purely informational charge flags (CHRG, DISCHRG) are dropped; unknown
	 * flags are kept as is.
	 *
	 * @param string $nut_status The raw ups.status value, for example "OL CHRG".
	 *
	 * @return string The apcupsd-style status, at most 64 characters.
	 */
	function apcupsd_nut_status(string $nut_status): string {
		$map = [
			'OL'      => 'ONLINE',
			'OB'      => 'ONBATT',
			'LB'      => 'LOWBATT',
			'RB'      => 'REPLACEBATT',
			'OVER'    => 'OVERLOAD',
			'FSD'     => 'SHUTTING DOWN',
			'CHRG'    => '',
			'DISCHRG' => '',
		];

		$out = [];

		foreach (preg_split('/\s+/', trim($nut_status), -1, PREG_SPLIT_NO_EMPTY) as $flag) {
			$flag = strtoupper($flag);
			$flag = $map[$flag] ?? $flag;

			if ($flag !== '' && !in_array($flag, $out, true)) {
				$out[] = $flag;
			}
		}

		return substr(implode(' ', $out), 0, 64);
	}
}

if (!function_exists('apcupsd_nut_status_online')) {
	/**
	 * Whether an apcupsd-style status reports the UPS as running on line power.
	 *
	 * @param string $status A status from apcupsd_nut_status().
	 *
	 * @return bool True when the ONLINE flag is present.
	 */
	function apcupsd_nut_status_online(string $status): bool {
		return in_array('ONLINE', explode(' ', $status), true);
	}
}

if (!function_exists('apcupsd_nut_variable_map')) {
	/**
	 * The NUT variables stored in apcupsd_ups_stats: each column maps to the NUT
	 * variables that can fill it (first one present wins) and how the value is
	 * stored: 'float', 'minutes' (seconds converted to minutes) or 'text:N'
	 * (trimmed to N characters).
	 *
	 * @return array<string, array{0: string[], 1: string}>
	 */
	function apcupsd_nut_variable_map(): array {
		return [
			'ups_model'                  => [['ups.model', 'device.model'], 'text:64'],
			'ups_serialno'               => [['ups.serial', 'device.serial'], 'text:64'],
			'ups_firmware'               => [['ups.firmware'], 'text:64'],
			'ups_driver'                 => [['driver.name'], 'text:40'],
			'ups_load_percent'           => [['ups.load'], 'float'],
			'ups_internal_temp'          => [['ups.temperature'], 'float'],
			'ups_ambtemp'                => [['ambient.temperature'], 'float'],
			'ups_humidity'               => [['ambient.humidity'], 'float'],
			'ups_line_voltage'           => [['input.voltage'], 'float'],
			'ups_line_frequency'         => [['input.frequency'], 'float'],
			'ups_max_line_voltage'       => [['input.voltage.maximum'], 'float'],
			'ups_min_line_voltage'       => [['input.voltage.minimum'], 'float'],
			'ups_lowtrans'               => [['input.transfer.low'], 'float'],
			'ups_hitrans'                => [['input.transfer.high'], 'float'],
			'ups_nominal_voltage'        => [['input.voltage.nominal'], 'float'],
			'ups_output_voltage'         => [['output.voltage'], 'float'],
			'ups_nominal_output_voltage' => [['output.voltage.nominal'], 'float'],
			'ups_battery_charge'         => [['battery.charge'], 'float'],
			'ups_mbattchg'               => [['battery.charge.low'], 'float'],
			'ups_timeleft'               => [['battery.runtime'], 'minutes'],
			'ups_mintimel'               => [['battery.runtime.low'], 'minutes'],
			'ups_battery_voltage'        => [['battery.voltage'], 'float'],
			'ups_nominal_batt_voltage'   => [['battery.voltage.nominal'], 'float'],
			'ups_battery_date'           => [['battery.date'], 'text:20'],
			'ups_nominal_power'          => [['ups.realpower.nominal'], 'float'],
			'ups_dshutd'                 => [['ups.delay.shutdown'], 'text:20'],
			'ups_dwake'                  => [['ups.delay.start'], 'text:20'],
		];
	}
}

if (!function_exists('apcupsd_nut_build_stats')) {
	/**
	 * Build the apcupsd_ups_stats row for a NUT UPS from its variables. Columns
	 * the server did not report are left out so they take their NULL/empty
	 * defaults (the poller replaces the whole row each poll).
	 *
	 * @param array<string, string> $vars    The variables read from upsd.
	 * @param array                 $ups     The apcupsd_ups row (id, hostname, nut_ups_name).
	 * @param string                $version The upsd version string, when known.
	 *
	 * @return array<string, mixed> Column => value.
	 */
	function apcupsd_nut_build_stats(array $vars, array $ups, string $version = ''): array {
		$now = date('Y-m-d H:i:s');

		$stats = [
			'ups_id'       => (int) $ups['id'],
			'ups_key'      => 'NUT',
			'ups_date'     => $now,
			'ups_hostname' => substr((string) $ups['hostname'], 0, 64),
			'ups_version'  => $version !== '' ? $version : 'NUT',
			'ups_name'     => substr((string) $ups['nut_ups_name'], 0, 64),
			'ups_cable'    => 'Ethernet Link',
			'ups_driver'   => 'NUT',
			'ups_mode'     => 'Stand Alone',
			'ups_status'   => apcupsd_nut_status((string) ($vars['ups.status'] ?? '')),
			'ups_end_rec'  => $now,
		];

		$stats['ups_battery_status'] = in_array('LOWBATT', explode(' ', $stats['ups_status']), true) ? 'Low' : 'Normal';

		foreach (apcupsd_nut_variable_map() as $column => [$names, $kind]) {
			foreach ($names as $name) {
				if (!isset($vars[$name])) {
					continue;
				}

				$value = trim($vars[$name]);

				if (strncmp($kind, 'text:', 5) === 0) {
					if ($value !== '') {
						$stats[$column] = substr($value, 0, (int) substr($kind, 5));
					}
				} elseif (is_numeric($value)) {
					$stats[$column] = $kind === 'minutes' ? round((float) $value / 60, 2) : (float) $value;
				}

				break;
			}
		}

		return $stats;
	}
}
