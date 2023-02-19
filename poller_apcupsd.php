#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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
require_once($config['base_path'] . '/lib/api_automation_tools.php');
require_once($config['base_path'] . '/lib/api_device.php');
require_once($config['base_path'] . '/lib/api_data_source.php');
require_once($config['base_path'] . '/lib/api_graph.php');
require_once($config['base_path'] . '/lib/api_tree.php');
require_once($config['base_path'] . '/lib/data_query.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/snmp.php');
require_once($config['base_path'] . '/lib/template.php');
require_once($config['base_path'] . '/lib/utility.php');
include('./plugins/apcupsd/database.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug, $start, $force;

$debug = false;
$force = false;
$start = microtime(true);
$hash  = '2107af603fd8dc27ea3f2cc2234eb7b9';

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

$host_template_id = db_fetch_cell_prepared('SELECT id FROM host_template WHERE hash = ?', array($hash));
$add_devices = true;

if (empty($host_template_id)) {
	cacti_log('WARNING: UPSD Device Package Not Imported.  Device automation will not happen until it is imported!', false, 'APCUPSD');
	$add_devices = false;
}

/* apcupsd upses first UPS */
$upses = db_fetch_assoc_prepared('SELECT *
	FROM apcupsd_ups
	WHERE type_id = 1
	AND enabled = "on"
	AND poller_id = ?',
	array($config['poller_id']));

$apcupsd = cacti_sizeof($upses);

if ($apcupsd > 0) {
	foreach($upses as $ups) {
		debug(sprintf('Collecting UPS Information for %s', $ups['name']));

		collect_ups_data($ups);

		if ($ups['host_id'] == 0 && $add_devices) {
			add_ups_device($ups, $host_template_id);
		}
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

		if ($ups['host_id'] == 0 && $add_devices) {
//			add_ups_device($ups, $host_template_id);
		}
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

function add_ups_device($ups, $host_template_id) {
	$save = array();

	if ($ups['type_id'] == 1) {
		$host_id = api_device_save(0, $host_template_id, $ups['name'], 'localhost', // id, template_id, description, hostname
			'', 0, '', '',                     // snmp community, snmp_version, snmp_username, snmp_password
			161, 500, '', 0,                   // snmp_port, snmp_timeout, disabled, availability_method
			0, 0, 500, 1, $ups['description'], // ping_method, ping_port, ping_timeout, ping_retries, notes
			'', '', '', '', '',                // snmp_auth_protocol, snmp_prive_passphrase, snmp_priv_protocol, snmp_context, snmp_engine_id
			10, 1, $ups['poller_id'],          // max_oids, device_threads, poller_id
			$ups['site_id'], '', '', 0);       // site_id, external_id, location, bulk_walk_size
	} else {
		$host_id = api_device_save(0, $host_template_id, $ups['name'], $ups['hostname'], // id, template_id, description, hostname
			$ups['snmp_community'], $ups['snmp_version'], $ups['snmp_username'], $ups['snmp_password'], // snmp community, snmp_version, snmp_username, snmp_password
			$ups['snmp_port'], $ups['snmp_timeout'], '', 2,                   // snmp_port, snmp_timeout, disabled, availability_method
			0, 0, $ups['snmp_timeout'], 1, $ups['description'], // ping_method, ping_port, ping_timeout, print_retries, notes
			$ups['snmp_auth_protocol'], $ups['snmp_priv_passphrase'], $ups['snmp_priv_protocol'], $ups['snmp_context'], $ups['snmp_engine_id'], // snmp_auth_protocol, snmp_prive_passphrase, snmp_priv_protocol, snmp_context, snmp_engine_id
			10, 1, $ups['poller_id'],          // max_oids, device_threads, poller_id
			$ups['site_id'], '', '', 0);       // site_id, external_id, location, bulk_walk_size
	}

	if ($host_id > 0) {
		db_execute_prepared('UPDATE apcupsd_ups
			SET host_id = ?
			WHERE id = ?',
			array($host_id, $ups['id']));
	}
}

function collect_snmp_ups_data($ups) {
	global $ups_database, $snmp_error;

	$start = time();

	$save = array();

	$save['ups_id']   = $ups['id'];
	$save['ups_date'] = date('Y-m-d H:i:s');
	$save['ups_hostname'] = $ups['hostname'];
	$save['ups_version']  = '1.0 (Cacti Plugin)';
	$save['ups_cable']    = 'Ethernet Link';
	$save['ups_driver']   = 'Cacti';
	$save['ups_mode']     = 'Stand Alone';

	$value = cacti_snmp_get($ups['hostname'], $ups['snmp_community'], '.1.3.6.1.2.1.1.3.0', $ups['snmp_version'],
		$ups['snmp_username'], $ups['snmp_password'], $ups['snmp_auth_protocol'], $ups['snmp_priv_passphrase'],
		$ups['snmp_priv_protocol'], $ups['snmp_context'], $ups['snmp_port'], $ups['snmp_timeout'], 1, 'SNMP',
		$ups['snmp_engine_id']);

	if ($value > 0) {
		db_execute_prepared('UPDATE apcupsd_ups SET status = 3 WHERE id = ?', array($ups['id']));

		foreach($ups_database AS $key => $data) {
			if (isset($data['snmp_ci']) && $data['snmp_ci'] != '' && $data['snmp_ci'] != 'NA' && $data['snmp_ci'] != 'UNKNOWN') {
				if ($data['snmp_ci'] == 'CURDATE' || $data['db_column'] == 'ups_date') {
					$stats[$data['db_column']] = date('Y-m-d H:i:s');
				} else {
					$value = cacti_snmp_get($ups['hostname'], $ups['snmp_community'], $data['snmp_ci'], $ups['snmp_version'],
						$ups['snmp_username'], $ups['snmp_password'], $ups['snmp_auth_protocol'], $ups['snmp_priv_passphrase'],
						$ups['snmp_priv_protocol'], $ups['snmp_context'], $ups['snmp_port'], $ups['snmp_timeout'], 1, 'SNMP',
						$ups['snmp_engine_id']);

					if ($value != 'U') {
						debug("SNMP Check for {$data['snmp_ci']}, Key $key, DB Column: {$data['db_column']}, Rendered: $value");

						if (isset($data['snmp_enum'])) {
							$prevalue = $value;
							debug("------------------ UPS ENUM $key");
							$value = $data['snmp_enum'][$value];
							debug("------------ $prevalue ---- $value");
						}

						switch($key) {
							case 'LASTSTEST':
								$parts = explode('/', $value);
								$save[$data['db_column']] = $parts[2] . '-' . $parts[0] . '-' . $parts[1] . ' 00:00:00';
								break;
							case 'TIMELEFT':
							case 'DLOWBATT':
								$value /= 100;
								$value /= 60;
								$save[$data['db_column']] = $value;
								break;
							case 'NOMPOWER':
							case 'NOMOUTV':
								$parts = explode(' ', $value);
								$save[$data['db_column']] = $parts[0];
								break;
							default:
								$save[$data['db_column']] = $value;
								break;
						}
					} else {
						debug("SNMP Check for {$data['snmp_ci']}, Key $key, DB Column: {$data['db_column']}, Rendered: No Data");
					}
				}
			}
		}
	} else {
		db_execute_prepared('UPDATE apcupsd_ups SET status = 1 WHERE id = ?', array($ups['id']));
	}

	$save['ups_end_rec'] = date('Y-m-d H:i:s');

	sql_save($save, 'apcupsd_ups_stats');
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

				if (isset($o[1])) {
					$value = trim($o[1]);
				} else {
					$value = '';
				}

				if (array_key_exists($keyword, $ups_database)) {
					if ($keyword == 'STATUS') {
						$status = db_fetch_cell_prepared('SELECT status
							FROM host
							WHERE id = ?',
							array($ups['host_id']));

						if ($value != 'ONLINE') {
							if ($status != 4) {
								db_execute_prepared('UPDATE host SET status = 4, status_fail_date=NOW() WHERE id = ?', array($ups['host_id']));
							}
						} elseif ($status != 3) {
							db_execute_prepared('UPDATE host SET status = 3, status_rec_date=NOW() WHERE id = ?', array($ups['host_id']));
						}
					}

					$sql_params[] = $value;
					$sql_insert .= ', `' . $ups_database[$keyword]['db_column'] . '`';
					$sql_data   .= ', ?';
				} else {
					debug('WARNING: Column ' . $keyword . ' is unknown with value ' . $value);
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

