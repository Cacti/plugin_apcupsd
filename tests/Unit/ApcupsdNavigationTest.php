<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for apcupsd_draw_navigation_text() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

it('adds the upses breadcrumb entry without disturbing existing ones', function () {
	$nav = apcupsd_draw_navigation_text(array('other.php:' => array('title' => 'Other')));

	expect($nav)->toHaveKey('other.php:');
	expect($nav)->toHaveKey('upses.php:');
	expect($nav['upses.php:'])->toBe(array(
		'title'   => 'Manage UPSes',
		'mapping' => 'index.php:',
		'url'     => 'upses.php',
		'level'   => '1',
	));
});
