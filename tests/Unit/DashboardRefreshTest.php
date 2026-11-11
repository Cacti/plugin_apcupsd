<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The UPS and Fleet dashboards arm auto-refresh through Cacti's standard
 * set_page_refresh(), the same way the shared Card API dashboards do, and the
 * adapter reloads the page to apply a changed interval after persisting it
 * through the CSRF-protected refresh endpoint.
 */

describe('dashboard auto-refresh', function () {
	$read = function (string $relative): string {
		$contents = file_get_contents(__DIR__ . '/../../' . $relative);

		expect($contents)->not->toBeFalse("Unable to read {$relative}");

		return (string) $contents;
	};

	it('arms Cacti\'s page refresh through set_page_refresh from both dashboards', function () use ($read) {
		$source = $read('includes/dashboard.php');

		expect($source)->toContain('function apcupsd_dashboard_print_refresh(');
		expect($source)->toContain('set_page_refresh([');
		expect($source)->toContain("'seconds' => \$seconds,");
		expect($source)->toContain("'page'    => \$page,");
		expect(substr_count($source, 'apcupsd_dashboard_print_refresh($current_refresh, $refresh_page);'))->toBe(2);
	});

	it('does nothing when Refresh is off or the core helper is missing', function () use ($read) {
		$source = $read('includes/dashboard.php');

		expect($source)->toContain("if (\$seconds <= 0 || !function_exists('set_page_refresh')) {");
	});

	it('arms the timer from both the Fleet and the per-UPS dashboard', function () use ($read) {
		$source = $read('includes/dashboard.php');

		expect($source)->toContain("\$refresh_page    = \$config['url_path'] . 'plugins/apcupsd/upses.php?action=dashboard&header=false';");
		expect($source)->toContain("\$refresh_page    = \$config['url_path'] . 'plugins/apcupsd/upses.php?action=view&id=' . \$ups_id . '&header=false';");
	});

	it('reloads to apply a changed Refresh interval after persisting it', function () use ($read) {
		$js = $read('js/apcupsd_dashboard.js');

		$start = strpos($js, "document.getElementById('apcupsd_dashboard_refresh')");
		$end   = strpos($js, "document.getElementById('apcupsd_dashboard_refresh_now')");

		expect($start)->toBeGreaterThan(0);
		expect($end)->toBeGreaterThan($start);

		$handler = substr($js, (int) $start, (int) $end - (int) $start);

		expect($handler)->toContain('action: cfg.refreshAction, refresh: this.value');
		expect($handler)->toContain('window.location = cfg.reload;');
	});

	it('persists only an interval the refresh picker offers, through a CSRF-checked POST', function () use ($read) {
		$source = $read('includes/dashboard.php');

		expect($source)->toContain('function apcupsd_dashboard_refresh_save(): string {');
		expect($source)->toContain("set_user_setting('apcupsd_dashboard_refresh', (int) \$posted);");
		expect($source)->toContain("!csrf_check(false)");
	});

	it('loads the shared controller and the dashboard script before the refresh is armed', function () use ($read) {
		$source = $read('includes/dashboard.php');

		expect(substr_count($source, 'plugins/apcupsd/js/apcupsd_dashboard.js?v='))->toBe(2);
		expect(substr_count($source, 'plugins/apcupsd/js/cards.js?v='))->toBe(2);

		$offset = 0;

		for ($i = 0; $i < 2; $i++) {
			$script  = strpos($source, 'plugins/apcupsd/js/apcupsd_dashboard.js?v=', $offset);
			$refresh = strpos($source, 'apcupsd_dashboard_print_refresh($current_refresh, $refresh_page);', $offset);

			expect($script)->toBeLessThan($refresh);

			$offset = (int) $refresh + 1;
		}
	});
});
