<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The UPSes page can run Cacti's graph automation for the device linked to
 * each selected UPS, so a device that never got its graphs (and so has no RRD
 * history for the dashboards) can be given them. upses.php is a web entry point
 * that cannot be loaded in the unit process, so its source is checked.
 */

describe('Run Graph Automation action', function () {
	$read = function (string $relative): string {
		$contents = file_get_contents(__DIR__ . '/../../' . $relative);

		expect($contents)->not->toBeFalse("Unable to read {$relative}");

		return (string) $contents;
	};

	it('loads the Cacti libraries graph automation needs', function () use ($read) {
		$page = $read('upses.php');

		foreach (['api_automation', 'api_device', 'api_tree', 'data_query', 'template'] as $library) {
			expect($page)->toContain("require_once(\$config['base_path'] . '/lib/{$library}.php');");
		}
	});

	it('only offers the action to users who may apply automation rules to devices', function () use ($read) {
		$page = $read('upses.php');

		// The same realm core's Devices page uses for "Apply Automation Rules".
		expect($page)->toContain('if (is_realm_allowed(3)) {');
		expect($page)->toContain("\$ups_actions[4] = __('Run Graph Automation', 'apcupsd');");
		expect(strpos($page, 'if (is_realm_allowed(3)) {'))->toBeLessThan(strpos($page, "\$ups_actions[4] = "));
	});

	it('refuses an action that was not offered, including this one without the realm', function () use ($read) {
		$page = $read('upses.php');

		expect($page)->toContain('array_key_exists((int) get_nfilter_request_var(\'drp_action\'), $ups_actions)');
		expect($page)->toContain('ctype_digit((string) get_nfilter_request_var(\'drp_action\'))');
		expect($page)->not->toContain("in_array(get_nfilter_request_var('drp_action'), ['1', '2', '3'], true)");
	});

	it('runs it from the confirmation form for the selected UPSes', function () use ($read) {
		$page = $read('upses.php');

		expect($page)->toContain("elseif (get_nfilter_request_var('drp_action') == '4') { // Run Graph Automation");
		expect($page)->toContain('run_graph_automation($selected_items);');
		expect($page)->toContain("elseif (get_nfilter_request_var('drp_action') == '4') { // run graph automation");
	});

	it('runs automation only for a UPS that has a linked Cacti device and reports the rest', function () use ($read) {
		$page = $read('upses.php');

		expect($page)->toContain('function run_graph_automation(array $ups_ids): void {');
		expect($page)->toContain('INNER JOIN host ON host.id = ups.host_id');
		expect($page)->toContain('automation_update_device($host_id);');
		expect($page)->toContain('$skipped++;');
		expect(substr_count($page, "raise_message('apcupsd_automation'"))->toBe(3);
		expect($page)->toContain('MESSAGE_LEVEL_INFO');
		expect($page)->toContain('MESSAGE_LEVEL_WARN');

		// The device id comes from a prepared query on the UPS id, never from the request.
		expect($page)->toContain("[(int) \$ups_id]);");
		expect(preg_match('/automation_update_device\(\$host_id\)/', $page))->toBe(1);
	});

	it('marks a UPS without a device in the confirmation list', function () use ($read) {
		$page = $read('upses.php');

		expect($page)->toContain("__esc('(no Cacti Device yet)', 'apcupsd')");
	});

	it('is documented', function () use ($read) {
		expect($read('README.md'))->toContain('Run Graph Automation');
		expect($read('CHANGELOG.md'))->toContain('"Run Graph Automation"');
	});
});
