<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2023 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function plugin_apcupsd_install() {
	api_plugin_register_hook('apcupsd', 'config_arrays',        'apcupsd_config_arrays',        'setup.php');
	api_plugin_register_hook('apcupsd', 'config_settings',      'apcupsd_config_settings',      'setup.php');
	api_plugin_register_hook('apcupsd', 'poller_bottom',        'apcupsd_poller_bottom',        'setup.php');
	api_plugin_register_hook('apcupsd', 'draw_navigation_text', 'apcupsd_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('apcupsd', 'replicate_out',        'apcupsd_replicate_out',        'setup.php');

	/* hook for table replication */
	api_plugin_register_hook('apcupsd', 'replicate_out',        'apcupsd_replicate_out',        'setup.php');

	api_plugin_register_realm('apcupsd', 'upses.php', __('Manage UPS\'s', 'apcupsd'), 1);

	apcupsd_setup_table();
}

function plugin_apcupsd_uninstall() {
	db_execute('DROP TABLE IF EXISTS apcupsd_ups');
	db_execute('DROP TABLE IF EXISTS apcupsd_ups_stats');

	return true;
}

function plugin_apcupsd_check_config() {
	return true;
}

function plugin_apcupsd_upgrade() {
	return true;
}

function apcupsd_check_upgrade() {
	global $config, $database_default;
	include_once($config['library_path'] . '/database.php');
	include_once($config['library_path'] . '/functions.php');

	$files = array('plugins.php', 'upses.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$info    = plugin_apcupsd_version();
	$current = $info['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='apcupsd'");
	if ($current != $old) {
		if (api_plugin_is_enabled('apcupsd')) {
			# may sound ridiculous, but enables new hooks
			api_plugin_enable_hooks('apcupsd');
		}

		db_execute("UPDATE plugin_config
			SET version='$current'
			WHERE directory='apcupsd'");

		db_execute("UPDATE plugin_config SET
			version='" . $info['version']  . "',
			name='"    . $info['longname'] . "',
			author='"  . $info['author']   . "',
			webpage='" . $info['homepage'] . "'
			WHERE directory='" . $info['name'] . "' ");
	}
}

function apcupsd_poller_bottom() {
	global $config;

	include_once($config['base_path'] . '/lib/poller.php');

	exec_background(read_config_option('path_php_binary'), ' -q ' . $config['base_path'] . '/plugins/apcupsd/poller_apcupsd.php');
}

function apcupsd_setup_table() {
	global $config, $database_default;
	include_once($config['library_path'] . '/database.php');

	db_execute("CREATE TABLE IF NOT EXISTS `apcupsd_ups` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`poller_id` int(10) unsigned NOT NULL default '1',
		`host_id` int(10) unsigned NOT NULL default '0',
		`site_id` int(10) unsigned NOT NULL default '0',
		`type_id` int(10) unsigned NOT NULL default '0',
		`name` varchar(40) NOT NULL DEFAULT '',
		`description` varchar(128) NOT NULL DEFAULT '',
		`status` int(10) unsigned NOT NULL DEFAULT '0',
		`hostname` varchar(64) NOT NULL DEFAULT '',
		`port` int(10) unsigned NOT NULL DEFAULT '3551',
		`enabled` char(2) DEFAULT 'on',
		`error_message` varchar(255) DEFAULT '',
		`last_updated` timestamp DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`))
		ENGINE=InnoDB
		COMMENT='Monitored UPS Table'");

	// APC      : 001,036,0854
	// DATE     : 2022-07-05 11:47:45 -0400
	// HOSTNAME : vmhost3
	// VERSION  : 3.14.14 (31 May 2016) redhat
	// UPSNAME  : APC1500
	// CABLE    : USB Cable
	// DRIVER   : USB UPS Driver
	// UPSMODE  : Stand Alone
	// STARTTIME: 2022-07-04 20:30:55 -0400
	// MODEL    : Back-UPS BX1500G
	// STATUS   : ONLINE
	// LINEV    : 121.0 Volts
	// LOADPCT  : 12.0 Percent
	// BCHARGE  : 100.0 Percent
	// TIMELEFT : 48.5 Minutes
	// MBATTCHG : 5 Percent
	// MINTIMEL : 3 Minutes
	// MAXTIME  : 0 Seconds
	// SENSE    : Low
	// LOTRANS  : 88.0 Volts
	// HITRANS  : 136.0 Volts
	// ALARMDEL : 30 Seconds
	// BATTV    : 27.2 Volts
	// LASTXFER : High line voltage
	// NUMXFERS : 0
	// TONBATT  : 0 Seconds
	// CUMONBATT: 0 Seconds
	// XOFFBATT : N/A
	// SELFTEST : NO
	// STATFLAG : 0x05000008
	// SERIALNO : 3B1050X33233
	// BATTDATE : 2021-04-01
	// NOMINV   : 120 Volts
	// NOMBATTV : 24.0 Volts
	// NOMPOWER : 865 Watts
	// FIRMWARE : 866.L5 .D USB FW:L5
	// END APC  : 2022-07-05 11:47:47 -0400

	db_execute("CREATE TABLE IF NOT exists `apcupsd_ups_stats` (
		`ups_id` int(10) unsigned NOT NULL,
		`ups_key` varchar(20) not null default '',
		`ups_date` timestamp not null default CURRENT_TIMESTAMP,
		`ups_hostname` varchar(64) not null default '',
		`ups_version` varchar(64) not null default '',
		`ups_name` varchar(20) not null default '',
		`ups_cable` varchar(20) not null default '',
		`ups_driver` varchar(20) not null default '',
		`ups_mode` varchar(20) not null default '',

		`ups_starttime` timestamp not null default CURRENT_TIMESTAMP,
		`ups_mandate` timestamp not null default CURRENT_TIMESTAMP,
		`ups_masterupd` timestamp not null default CURRENT_TIMESTAMP,
		`ups_xonbatt` timestamp not null default CURRENT_TIMESTAMP,

		`ups_model` varchar(40) not null default '',
		`ups_status` varchar(20) not null default '',

		`ups_dispsw` varchar(20) not null default '',
		`ups_extbatts` int(10) unsigned default null,
		`ups_badbatts` int(10) unsigned default null,
		`ups_reg1` varchar(20) not null default '',
		`ups_reg2` varchar(20) not null default '',
		`ups_reg3` varchar(20) not null default '',

		`ups_line_voltage` double default null,
		`ups_line_fail` varchar(20) not null default '0',
		`ups_load_percent` double default null,
		`ups_line_frequency` double default null,
		`ups_output_voltage` double default null,

		`ups_max_line_voltage` double default null,
		`ups_min_line_voltage` double default null,

		`ups_timeleft` double default null,
		`ups_mbattchg` double default null,
		`ups_mintimel` double default null,
		`ups_maxtime` double default null,
		`ups_sense` varchar(20) not null default '',
		`ups_lowtrans` double default null,
		`ups_hitrans` double default null,
		`ups_alarmdel` double default null,

		`ups_dlowbatt` varchar(20) not null default '',
		`ups_dshutd` varchar(20) not null default '',
		`ups_dwake` varchar(20) not null default '',

		`ups_battery_status` varchar(60) not null default '',
		`ups_battery_charge` double default null,
		`ups_battery_voltage` double default null,
		`ups_battery_date` varchar(20) not null default '',
		`ups_battery_retpct` double default null,

		`ups_lastxfer` varchar(40) not null default '',
		`ups_numxfers` int(10) unsigned default null,
		`ups_tonbatt` int(10) unsigned default null,
		`ups_cumonbatt` int(10) unsigned default null,
		`ups_xoffbatt` int(10) unsigned default null,
		`ups_selftest` varchar(10) not null default '',
		`ups_selftest_interval` varchar(10) not null default '',
		`ups_statflag` varchar(20) not null default '',
		`ups_serialno` varchar(20) not null default '',

		`ups_nominal_voltage` double default null,
		`ups_nominal_batt_voltage` double default null,
		`ups_nominal_power` double default null,
		`ups_nominal_output_voltage` double default null,

		`ups_abmtemp` double default null,
		`ups_humidity` double default null,
		`ups_internal_temp` double default null,

		`ups_firmware` varchar(40) not null default '',
		`ups_end_rec` timestamp not null default CURRENT_TIMESTAMP,
		PRIMARY KEY(ups_id))
		ENGINE=InnoDB
		COMMENT='Monitored UPS Status Table'");

	return true;
}

function plugin_apcupsd_version () {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/apcupsd/INFO', true);
	return $info['info'];
}

function apcupsd_log_valid_event() {
	global $action;

	$valid = false;

	if (read_config_option('apcupsd_enabled') == 'on') {
		if (strpos($_SERVER['SCRIPT_NAME'], 'graph_view.php') !== false) {
			$valid = false;
		} elseif (strpos($_SERVER['SCRIPT_NAME'], 'user_admin.php') !== false &&
			isset_request_var('action') && get_nfilter_request_var('action') == 'checkpass') {
			$valid = false;
		} elseif (strpos($_SERVER['SCRIPT_NAME'], 'plugins.php') !== false) {
			if (isset_request_var('mode')) {
				$valid  = true;
				$action = get_nfilter_request_var('mode');
			}
		} elseif (strpos($_SERVER['SCRIPT_NAME'], 'auth_profile.php') !== false) {
			$valid = false;
		} elseif (strpos($_SERVER['SCRIPT_NAME'], 'index.php') !== false) {
			$valid = false;
		} elseif (strpos($_SERVER['SCRIPT_NAME'], 'auth_changepassword.php') !== false) {
			$valid = false;
		} elseif (isset($_POST) && sizeof($_POST)) {
			$valid = true;
		} elseif (isset_request_var('purge_continue')) {
			$valid  = true;
			$action = 'purge';
		}
	}

	return $valid;
}

function apcupsd_config_arrays() {
	global $menu;

	$menu[__('Management')]['plugins/apcupsd/upses.php'] = __('UPSes', 'webseer');

	if (function_exists('auth_augment_roles')) {
		auth_augment_roles(__('System Administration'), array('upses.php'));
	}

	apcupsd_check_upgrade();
}

function apcupsd_config_settings () {
	global $tabs, $settings, $item_rows, $apcupsd_retentions;

}

function apcupsd_replicate_out($data) {
	include_once($config['base_path'] . '/lib/poller.php');

	$data = db_fetch_assoc('SELECT * FROM apcupsd_ups');

	replicate_out_table($data['rcnn_id'], $data, 'apcupsd_ups', $data['remote_poller_id']);

	$data = db_fetch_assoc('SELECT * FROM apcupsd_ups_stats');

	replicate_out_table($data['rcnn_id'], $data, 'apcupsd_ups_stats', $data['remote_poller_id']);

	return $data;
}

function apcupsd_draw_navigation_text($nav) {
	$nav['upses.php:'] = array(
		'title'   => __('Manage UPSes', 'apcupsd'),
		'mapping' => 'index.php:',
		'url'     => 'upses.php',
		'level'   => '1'
	);

	return $nav;
}

