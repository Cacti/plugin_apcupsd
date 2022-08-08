#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

chdir(dirname(__FILE__));
chdir('../..');

include('./include/cli_check.php');
include('./plugins/apcupsd/database.php');
include_once('./lib/poller.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug, $start, $force;

$debug = false;
$force = false;
$start = microtime(true);

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-d':
			case '--debug':
				$debug = true;
				break;
			case '-f':
			case '--force':
				$force = true;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}

/* apcupsd upses first UPS */
$upses = db_fetch_assoc('SELECT *
	FROM apcupsd_ups
	WHERE type_id = 1
	AND enabled = "on"');

$apcupsd = cacti_sizeof($upses);

if ($apcupsd > 0) {
	foreach($upses as $ups) {
		debug(sprintf('Collecting UPS Information for %s', $ups['name']));

		collect_ups_data($ups);
	}
}

/* apcupsd upses first UPS */
$upses = db_fetch_assoc('SELECT *
	FROM apcupsd_ups
	WHERE type_id = 2
	AND enabled = "on"');

$snmpupses = cacti_sizeof($upses);

if ($snmpupses > 0) {
	foreach($upses as $ups) {
		debug(sprintf('Collecting UPS Information for %s', $ups['name']));

		collect_snmp_ups_data($ups);
	}
}

$end = microtime(true);

$cacti_stats = sprintf(
	'Time:%01.2f UPSDUPSes:%s SNMPUPSes:%s',
	$end - $start,
	$apcupsd, $snmpupses
);

cacti_log("APCUPSD STATS: $cacti_stats", false, 'SYSTEM');

/* log to the database */
set_config_option('stats_apcupsd', $cacti_stats);

function collect_snmp_ups_data($ups) {
	global $ups_database;
}

function collect_ups_data($ups) {
	global $ups_database;

	$command = 'apcaccess -u -h ' . $ups['hostname'] . ':' . $ups['port'];

	$output = array();
	$return = 0;

	$results = exec($command, $output, $return);

	if ($return > 0) {
		$message = implode(', ', $output);

		db_execute_prepared('UPDATE apcupsd_ups
			SET status = 1, error_message = ?
			WHERE id = ?',
			array($message, $ups['id']));
	} else {
		if (cacti_sizeof($output)) {
			$sql_insert   = 'REPLACE INTO apcupsd_ups_stats (ups_id';
			$sql_data     = 'VALUES (?';
			$sql_params[] = $ups['id'];

			foreach($output as $o) {
				$o = explode(': ', $o);

				$keyword = trim($o[0]);
				$value   = trim($o[1]);

				if (array_key_exists($keyword, $ups_database)) {
					$sql_params[] = $value;
					$sql_insert .= ', `' . $ups_database[$keyword]['db_column'] . '`';
					$sql_data   .= ', ?';
				} else {
					debug('WARNING: Column ' . $keyword . ' is unknown');
				}
			}

			db_execute_prepared("$sql_insert) $sql_data)", $sql_params);
		}

		db_execute_prepared('UPDATE apcupsd_ups
			SET status = 3, last_updated=NOW()
			WHERE id = ?',
			array($ups['id']));
	}
}

function debug($string) {
	global $debug;

	if ($debug) {
		print date('H:i:s') . ' DEBUG: ' . trim($string) . PHP_EOL;
	}
}

function display_version() {
	global $config;

	if (!function_exists('plugin_apcupsd_version')) {
		include_once($config['base_path'] . '/plugins/hmib/setup.php');
	}

	$info = plugin_hmib_version();
	print "UPS Poller Process, Version " . $info['version'] . ", " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	print "\nThe UPS poller process script for Cacti.\n\n";
	print "usage: \n";
	print "poller_apcups.php [--force] [--debug]\n";
}

