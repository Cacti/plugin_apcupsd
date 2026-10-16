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
		expect($source)->toContain('else { setupPageTimeout(); }');
		expect($source)->toContain('document.getElementById("apcupsd_dashboard")');
	});

	it('arms the timer from both the Fleet and the per-UPS dashboard', function () use ($read) {
		$source = $read('includes/dashboard.php');

		expect($source)->toContain("apcupsd_dashboard_print_refresh(\$current_refresh, \$config['url_path'] . 'plugins/apcupsd/upses.php?action=dashboard&header=false')");
		expect($source)->toContain("apcupsd_dashboard_print_refresh(\$current_refresh, \$config['url_path'] . 'plugins/apcupsd/upses.php?action=view&id=' . \$ups_id . '&header=false')");
	});

	it('hands the interval to the dashboard script and falls back to the page timer without it', function () use ($read) {
		$source = $read('includes/dashboard.php');

		expect($source)->toContain('if (typeof apcupsdDashStartRefresh === "function") { apcupsdDashStartRefresh(); } else { setupPageTimeout(); }');

		// The interval has to be in refreshMSeconds before the script reads it.
		expect(strpos($source, "'refreshMSeconds = '"))->toBeLessThan(strpos($source, 'apcupsdDashStartRefresh();'));
	});

	it('refreshes the cards in place through Cacti\'s refreshFunction hook where core has one', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->toContain('function apcupsdDashAutoRefresh()');
		expect($js)->toContain("apcupsdDashRefreshAll(grid, document.getElementById('apcupsd_dashboard_refresh_now'))");
		expect($js)->toContain("refreshFunction = 'apcupsdDashAutoRefresh()';");

		// Support is detected before refreshFunction is assigned, otherwise the assignment itself
		// would make Cacti 1.2.x (whose timer ignores it and reloads the page) look supported.
		expect(strpos($js, "typeof refreshFunction !== 'undefined'"))->toBeLessThan(strpos($js, "refreshFunction = 'apcupsdDashAutoRefresh()';"));
	});

	it('uses its own timer where core has no refreshFunction hook (Cacti 1.2.x)', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->toContain('function apcupsdDashStartRefresh()');
		expect($js)->toContain('clearTimeout(myRefresh);');
		expect($js)->toContain('apcupsdDashTimer = window.setTimeout(apcupsdDashOwnTick, apcupsdDashTimerMs);');
		expect($js)->toContain('apcupsdDashTimerMs = refreshMSeconds;');
	});

	it('ends its own timer once the dashboard has left the screen', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect(preg_match('/function apcupsdDashOwnTick\(\) \{\s*apcupsdDashTimer = null;\s*var grid = document\.getElementById\(\'apcupsd_dashboard\'\);\s*if \(!grid\) \{\s*return;\s*\}/', $js))->toBe(1);
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

	it('captures the live sort when the refreshed card replaces the old one, not when the request is sent', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect(strpos($js, 'var sorted = apcupsdDashSortSnapshot(card);'))->toBeGreaterThan(strpos($js, 'function apcupsdDashRefreshCard('));
		expect(strpos($js, 'var sorted = apcupsdDashSortSnapshot(card);'))->toBeGreaterThan(strpos($js, '.done(function(data) {'));
		expect(strpos($js, 'var sorted = apcupsdDashSortSnapshot(card);'))->toBeLessThan(strpos($js, 'card.parentNode.replaceChild(fresh, card);'));
	});

	it('has no recurring browser timer other than the 1.2.x fallback', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->not->toContain('setInterval');

		// Every setTimeout() is the fallback timer, which stops itself when the dashboard is gone.
		preg_match_all('/setTimeout\s*\(\s*([A-Za-z_]+)/', $js, $matches);

		expect($matches[1])->not->toBe([]);

		foreach ($matches[1] as $callback) {
			expect($callback)->toBe('apcupsdDashOwnTick');
		}
	});
});
