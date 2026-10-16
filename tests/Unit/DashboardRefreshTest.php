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

		expect($source)->toContain("\$refresh_page    = \$config['url_path'] . 'plugins/apcupsd/upses.php?action=dashboard&header=false';");
		expect($source)->toContain("\$refresh_page    = \$config['url_path'] . 'plugins/apcupsd/upses.php?action=view&id=' . \$ups_id . '&header=false';");
		expect(substr_count($source, 'apcupsd_dashboard_print_refresh($current_refresh, $refresh_page);'))->toBe(2);
	});

	it('exposes the refresh URL on both grids so the dropdown can arm the timer', function () use ($read) {
		$source = $read('includes/dashboard.php');

		expect(substr_count($source, "' data-refresh-page=\"' . html_escape(\$refresh_page) . '\"'"))->toBe(2);
		expect($read('js/apcupsd_dashboard.js'))->toContain("refreshPage:   grid.dataset.refreshPage   || ''");
	});

	it('applies a changed Refresh interval without reloading the page', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		$start = strpos($js, "document.getElementById('apcupsd_dashboard_refresh')");
		$end   = strpos($js, "document.getElementById('apcupsd_dashboard_refresh_now')");

		expect($start)->toBeGreaterThan(0);
		expect($end)->toBeGreaterThan($start);

		$handler = substr($js, $start, $end - $start);

		expect($handler)->toContain('apcupsdDashSaveRefresh(grid, this);');
		expect($handler)->not->toContain('window.location');

		$save = substr($js, (int) strpos($js, 'function apcupsdDashSaveRefresh('));

		expect($save)->not->toContain('window.location');
		expect($save)->not->toContain('location.reload');
		expect($save)->toContain('apcupsdDashSetRefresh(parseInt(value, 10) || 0, cfg.refreshPage);');
	});

	it('applies only the interval that was saved, using the value that was submitted', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->toContain('var value = select.value;');
		expect($js)->toContain('refresh: value}');
		expect($js)->toContain('select.dataset.previous = value;');
		expect($js)->not->toContain('select.dataset.previous = select.value;');
	});

	it('keeps one save in flight and sends a choice made meanwhile after it settles', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->toContain('var apcupsdDashRefreshSaving = false;');
		expect($js)->toContain('apcupsdDashRefreshPending = true;');
		expect($js)->toContain('if (select.value !== select.dataset.previous) {');
		expect($js)->toContain('apcupsdDashSaveRefresh(grid, select);');
	});

	it('rolls the picker back through its widget without running the save handler', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect($js)->toContain('function apcupsdDashSetSelect(select, value) {');
		expect($js)->toContain("\$select.trigger('change.select2');");
		expect($js)->toContain("\$select.selectmenu('refresh');");
		expect($js)->toContain('apcupsdDashSetSelect(select, select.dataset.previous);');

		// A namespaced trigger, so the 'change.apcupsddash' save handler does not run.
		expect($js)->not->toContain("trigger('change')");
	});

	it('puts Cacti\'s browser logout timer back when Refresh is turned off', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		// Remembered when the script loads, before the dashboard overrides it.
		expect($js)->toContain('var apcupsdDashCoreTimer = typeof apcupsdDashCoreTimer === \'undefined\' ? null : apcupsdDashCoreTimer;');
		expect($js)->toContain('refreshIsLogout === true');
		expect(strpos($js, 'apcupsdDashCoreTimer = {ms: refreshMSeconds, page: refreshPage};'))->toBeLessThan(strpos($js, 'function apcupsdDashCfg('));

		expect($js)->toContain('function apcupsdDashRestoreCoreTimer() {');
		expect($js)->toContain('refreshIsLogout = true;');
		expect($js)->toContain('refreshPage = apcupsdDashCoreTimer.page;');
		expect($js)->toContain('refreshMSeconds = apcupsdDashCoreTimer.ms;');

		// "Off" restores it, and never when there was no such timer to restore.
		expect(preg_match('/if \(seconds <= 0\) \{\s*apcupsdDashRestoreCoreTimer\(\);\s*return;\s*\}/', $js))->toBe(1);
		expect($js)->toContain('if (apcupsdDashCoreTimer === null || typeof setupPageTimeout !== \'function\') {');
	});

	it('loads the dashboard script before the script that starts the refresh', function () use ($read) {
		$source = $read('includes/dashboard.php');

		// Cacti runs ajax-loaded scripts in order, so the functions must be defined first.
		expect(substr_count($source, 'plugins/apcupsd/js/apcupsd_dashboard.js?v='))->toBe(2);

		$offset = 0;

		for ($i = 0; $i < 2; $i++) {
			$script  = strpos($source, 'plugins/apcupsd/js/apcupsd_dashboard.js?v=', $offset);
			$refresh = strpos($source, 'apcupsd_dashboard_print_refresh($current_refresh, $refresh_page);', $offset);

			expect($script)->toBeLessThan($refresh);

			$offset = $refresh + 1;
		}
	});

	it('swaps the running timer for the new interval, or stops it for No Refresh', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		expect(preg_match('/function apcupsdDashSetRefresh\(seconds, page\) \{\s*if \(typeof myRefresh !== \'undefined\'\) \{\s*clearTimeout\(myRefresh\);\s*\}/', $js))->toBe(1);
		expect($js)->toContain('if (seconds <= 0) {');
		expect($js)->toContain('refreshMSeconds = seconds * 1000;');
		expect($js)->toContain('refreshPage = page;');

		// Stopping must happen before the early return for "off", and starting after it.
		expect(strpos($js, 'clearTimeout(myRefresh);', strpos($js, 'function apcupsdDashSetRefresh(')))->toBeLessThan(strpos($js, 'if (seconds <= 0) {'));
		expect(strpos($js, 'if (seconds <= 0) {'))->toBeLessThan(strpos($js, 'refreshMSeconds = seconds * 1000;'));
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
