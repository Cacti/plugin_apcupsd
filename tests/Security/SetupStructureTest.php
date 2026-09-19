<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('apcupsd setup.php structure', function () {
	$source = file_get_contents(realpath(__DIR__ . '/../../setup.php'));

	// plugin_apcupsd_version() reads its metadata from the INFO ini file
	// rather than returning an inline PHP array, unlike some other plugins.
	$info = file_get_contents(realpath(__DIR__ . '/../../INFO'));

	it('defines plugin_apcupsd_install function', function () use ($source) {
		expect($source)->toContain('function plugin_apcupsd_install');
	});

	it('defines plugin_apcupsd_version function', function () use ($source) {
		expect($source)->toContain('function plugin_apcupsd_version');
	});

	it('defines plugin_apcupsd_uninstall function', function () use ($source) {
		expect($source)->toContain('function plugin_apcupsd_uninstall');
	});

	it('returns version array with name key', function () use ($info) {
		expect($info)->toMatch('/^name\s*=/m');
	});

	it('returns version array with version key', function () use ($info) {
		expect($info)->toMatch('/^version\s*=/m');
	});

	it('registers hooks in install function', function () use ($source) {
		expect($source)->toContain('api_plugin_register_hook');
	});
});
