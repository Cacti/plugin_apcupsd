<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../includes/nis.php';

describe('apcupsd poller request validation', function () {
	it('rejects a blank or hostile host before connecting', function () {
		foreach (['', 'rack ups', 'rack-ups;touch /tmp/pwned', '$(id)', "rack-ups\nstatus"] as $host) {
			expect(apcupsd_nis_query($host, 3551)['error'])->toContain('Invalid apcupsd hostname or port');
		}
	});

	it('rejects a port outside the TCP range before connecting', function () {
		expect(apcupsd_nis_query('rack-ups.example.com', 70000)['error'])->toContain('Invalid apcupsd hostname or port');
	});
});
