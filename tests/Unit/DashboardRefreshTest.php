<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The dashboards arm Cacti's page timer through the refreshMSeconds /
 * refreshPage / refreshIsLogout globals (as graph_view.php does) rather than
 * set_page_refresh(), which parks the interval in the session where it can be
 * applied to whichever page renders next instead of clearing when the user
 * leaves the dashboard.
 */

describe('dashboard auto-refresh', function () {
	$read = function (string $relative): string {
		$contents = file_get_contents(__DIR__ . '/../../' . $relative);

		expect($contents)->not->toBeFalse("Unable to read {$relative}");

		return (string) $contents;
	};

	it('does not store the refresh interval in the session', function () use ($read) {
		foreach (['includes/dashboard.php', 'upses.php', 'includes/helpers.php', 'js/apcupsd_dashboard.js'] as $file) {
			expect($read($file))->not->toContain('set_page_refresh');
		}
	});

	it('sets the Cacti refresh globals from a ready callback and re-arms the page timer', function () use ($read) {
		$source = $read('includes/dashboard.php');

		expect($source)->toContain('function apcupsd_dashboard_print_refresh(');
		expect($source)->toContain("'refreshMSeconds = '");
		expect($source)->toContain("'refreshPage = '");
		expect($source)->toContain("'refreshIsLogout = false;'");
		expect($source)->toContain("'setupPageTimeout();'");
		expect($source)->toContain('document.getElementById("apcupsd_dashboard")');
	});

	it('arms the timer from both the Fleet and the per-UPS dashboard', function () use ($read) {
		$source = $read('includes/dashboard.php');

		expect($source)->toContain("apcupsd_dashboard_print_refresh(\$current_refresh, \$config['url_path'] . 'plugins/apcupsd/upses.php?action=dashboard&header=false')");
		expect($source)->toContain("apcupsd_dashboard_print_refresh(\$current_refresh, \$config['url_path'] . 'plugins/apcupsd/upses.php?action=view&id=' . \$ups_id . '&header=false')");
	});

	it('refreshes the cards in place through Cacti\'s refreshFunction hook, not a page reload', function () use ($read) {
		$source = $read('includes/dashboard.php');

		expect($source)->toContain("'refreshFunction = typeof apcupsdDashAutoRefresh === \"function\" ? \"apcupsdDashAutoRefresh()\" : \"\";'");

		// refreshFunction must be set before the timer is armed, and refreshPage kept as the fallback.
		expect(strpos($source, "'refreshFunction = "))->toBeLessThan(strpos($source, "'setupPageTimeout();'"));

		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->toContain('function apcupsdDashAutoRefresh()');
		expect($js)->toContain("apcupsdDashRefreshAll(grid, document.getElementById('apcupsd_dashboard_refresh_now'))");
	});

	it('hands another page back to Cacti\'s own refresh when the dashboard has gone', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->toContain("refreshFunction = '';");
		expect($js)->toContain('loadUrl({url: refreshPage});');
	});

	it('does not stack refresh rounds and keeps the chosen sort across an in-place refresh', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->toContain('if (!pending || apcupsdDashRefreshing) {');
		expect($js)->toContain('var sorted = apcupsdDashSortSnapshot(card);');
		expect($js)->toContain('apcupsdDashSortRestore(fresh, sorted);');
	});

	it('does not run its own refresh timer in the browser', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->not->toContain('setInterval');
		expect(preg_match('/setTimeout\s*\(/', $js))->toBe(0);
	});
});
