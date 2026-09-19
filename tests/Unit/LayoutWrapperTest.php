<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

if (!function_exists('top_header')) {
	function top_header() {
		$GLOBALS['__layout_events'][] = 'top_header';
	}
}

if (!function_exists('bottom_footer')) {
	function bottom_footer() {
		$GLOBALS['__layout_events'][] = 'bottom_footer';
	}
}

require_once __DIR__ . '/../../ui_helpers.php';

beforeEach(function () {
	$GLOBALS['__layout_events'] = [];
});

describe('shared layout wrapper routing in upses.php', function () {
	it('executes the layout callback exactly once, in top->content->bottom order', function () {
		$layoutInvocations = 0;

		apcupsd_render_with_layout(function () use (&$layoutInvocations) {
			$layoutInvocations++;
			$GLOBALS['__layout_events'][] = 'content';
		});

		expect($layoutInvocations)->toBe(1);
		expect($GLOBALS['__layout_events'])->toBe(['top_header', 'content', 'bottom_footer']);
	});

	it('routes the edit action through the shared layout helper', function () {
		$upsesSource = file_get_contents(realpath(__DIR__ . '/../../upses.php'));

		expect($upsesSource)->toContain("apcupsd_render_with_layout('ups_edit');");
	});

	it('routes the default action through the shared layout helper', function () {
		$upsesSource = file_get_contents(realpath(__DIR__ . '/../../upses.php'));

		expect($upsesSource)->toContain("apcupsd_render_with_layout('upses');");
	});
});
