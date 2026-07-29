<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Verify setup.php defines required plugin hooks and info function.
 */

describe('apcupsd setup.php structure', function () {
	$setupFile = realpath(__DIR__ . '/../../setup.php');

	if ($setupFile === false) {
		throw new RuntimeException('Unable to resolve setup.php path');
	}

	$source = file_get_contents($setupFile);

	if ($source === false) {
		throw new RuntimeException('Unable to read setup.php');
	}

	it('defines plugin_apcupsd_install function', function () use ($source) {
		expect($source)->toContain('function plugin_apcupsd_install');
	});

	it('defines plugin_apcupsd_version function', function () use ($source) {
		expect($source)->toContain('function plugin_apcupsd_version');
	});

	it('defines plugin_apcupsd_uninstall function', function () use ($source) {
		expect($source)->toContain('function plugin_apcupsd_uninstall');
	});

	// plugin_apcupsd_version() sources its array from the INFO ini file
	// rather than a literal PHP array, so the name/version keys live there.
	$infoFile = realpath(__DIR__ . '/../../INFO');

	if ($infoFile === false) {
		throw new RuntimeException('Unable to resolve INFO path');
	}

	$info = parse_ini_file($infoFile, true);

	if ($info === false) {
		throw new RuntimeException('Unable to parse INFO');
	}

	it('returns version array with name key', function () use ($info) {
		expect($info['info'])->toHaveKey('name');
	});

	it('returns version array with version key', function () use ($info) {
		expect($info['info'])->toHaveKey('version');
	});
});
