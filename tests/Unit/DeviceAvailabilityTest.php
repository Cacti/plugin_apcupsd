<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../includes/functions.php';

describe('apcupsd_device_availability_fields', function () {
	it('builds a TCP ping check against the service host and port', function () {
		expect(apcupsd_device_availability_fields('ups.example.net', 3551, 5))->toBe([
			'hostname'            => 'ups.example.net',
			'availability_method' => 3,
			'ping_method'         => 3,
			'ping_port'           => 3551,
			'ping_timeout'        => 5000,
		]);
	});

	it('falls back to localhost when the hostname is blank', function () {
		$fields = apcupsd_device_availability_fields('', 3493, 5);

		expect($fields['hostname'])->toBe('localhost');
	});

	it('uses the configured hostname when one is given', function () {
		$fields = apcupsd_device_availability_fields('nut.local', 3493, 5);

		expect($fields['hostname'])->toBe('nut.local');
	});

	it('casts the port to an integer for the TCP ping', function () {
		$fields = apcupsd_device_availability_fields('h', '3551', '5');

		expect($fields['ping_port'])->toBe(3551);
	});

	it('derives the ping timeout in milliseconds from the UPS timeout', function () {
		expect(apcupsd_device_availability_fields('h', 1, 10)['ping_timeout'])->toBe(10000);
		expect(apcupsd_device_availability_fields('h', 1, 120)['ping_timeout'])->toBe(120000);
	});

	it('never produces a zero ping timeout for a zero or negative timeout', function () {
		expect(apcupsd_device_availability_fields('h', 1, 0)['ping_timeout'])->toBe(1000);
		expect(apcupsd_device_availability_fields('h', 1, -5)['ping_timeout'])->toBe(1000);
	});
});
