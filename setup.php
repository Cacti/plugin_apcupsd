<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

/**
 * Installs the APCUPSD plugin: registers its Cacti hooks (config_arrays,
 * config_settings, poller_bottom, draw_navigation_text, replicate_out),
 * registers its upses.php realm, and creates its database tables. Invoked
 * by Cacti's plugin architecture when an administrator installs this
 * plugin from Console > Plugin Management.
 *
 * @return void
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

/**
 * Uninstalls the APCUPSD plugin, dropping its apcupsd_ups and
 * apcupsd_ups_stats tables. Invoked by Cacti's plugin architecture when
 * an administrator uninstalls this plugin from Console > Plugin
 * Management.
 *
 * @return bool Always returns true.
 */
function plugin_apcupsd_uninstall() {
	db_execute('DROP TABLE IF EXISTS apcupsd_ups');
	db_execute('DROP TABLE IF EXISTS apcupsd_ups_stats');

	return true;
}

/**
 * Verifies the plugin's configuration; currently a no-op placeholder.
 * Invoked by Cacti's plugin architecture on relevant page loads.
 *
 * @return bool Always returns true.
 */
function plugin_apcupsd_check_config() {
	return true;
}

/**
 * Performs any schema/data migrations needed when upgrading to a newer
 * version of this plugin; currently a no-op placeholder. Invoked by
 * Cacti's plugin architecture when an installed plugin's version
 * increases.
 *
 * @return bool Always returns true.
 */
function plugin_apcupsd_upgrade() {
	return true;
}

/**
 * Detects whether the installed plugin_config version differs from this
 * plugin's INFO file version and, if so, re-enables its hooks (to pick
 * up any newly added ones), updates the stored plugin_config record, and
 * applies a handful of one-off apcupsd_ups_stats column migrations
 * (renaming/adding columns from older releases). Only runs on
 * plugins.php/upses.php. Called from apcupsd_config_arrays() on every
 * relevant page load.
 *
 * @return void
 *
 * @global array  $config           Cacti global configuration array; used
 *                                   to load database.php/functions.php.
 * @global object $database_default Cacti's default database connection
 *                                   handle (unused directly here;
 *                                   declared for parity with other
 *                                   database-touching functions in this
 *                                   file).
 */
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
	$old     = db_fetch_cell_prepared('SELECT version
		FROM plugin_config
		WHERE directory = ?',
		array('apcupsd'));
	if ($current != $old) {
		if (api_plugin_is_enabled('apcupsd')) {
			# may sound ridiculous, but enables new hooks
			api_plugin_enable_hooks('apcupsd');
		}

		db_execute_prepared("UPDATE plugin_config SET
			version = ?, name = ?, author = ?, webpage = ?
			WHERE directory = ?",
			array(
				$info['version'],
				$info['longname'],
				$info['author'],
				$info['homepage'],
				$info['name']
			)
		);

		if (db_column_exists('apcupsd_ups_stats', 'ups_abmtemp')) {
			db_execute('ALTER TABLE apcupsd_ups_stats CHANGE COLUMN ups_abmtemp ups_ambtemp DOUBLE default NULL');
		}

		if (!db_column_exists('apcupsd_ups_stats', 'ups_master')) {
			db_execute('ALTER TABLE apcupsd_ups_stats ADD COLUMN ups_master varchar(128) NOT NULL default "" AFTER ups_name');
		}

		if (db_column_exists('apcupsd_ups_stats', 'ups_dispsw')) {
			db_execute('ALTER TABLE apcuspd_ups_stats CHANGE COLUMN ups_dispsw ups_dipsw VARCHAR(20) NOT NULL default ""');
		}
	}
}

/**
 * Hook implementation for Cacti's 'poller_bottom' filter. Launches
 * poller_apcupsd.php as a background process to poll all configured
 * UPS devices. Called by Cacti's poller via
 * api_plugin_hook('poller_bottom', ...) at the end of each polling cycle.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to locate
 *                        the PHP binary and this plugin's poller script.
 */
function apcupsd_poller_bottom() {
	global $config;

	include_once($config['base_path'] . '/lib/poller.php');

	exec_background(read_config_option('path_php_binary'), ' -q ' . $config['base_path'] . '/plugins/apcupsd/poller_apcupsd.php');
}

/**
 * Creates this plugin's apcupsd_ups (configured UPS devices) and
 * apcupsd_ups_stats (polled UPS readings) database tables, if they don't
 * already exist. Called from plugin_apcupsd_install() during plugin
 * installation.
 *
 * @return bool Always returns true.
 *
 * @global array  $config           Cacti global configuration array;
 *                                   used to load database.php.
 * @global object $database_default Cacti's default database connection
 *                                   handle (unused directly here;
 *                                   declared for parity with other
 *                                   database-touching functions in this
 *                                   file).
 */
function apcupsd_setup_table() {
	global $config, $database_default;
	include_once($config['library_path'] . '/database.php');

	db_execute("CREATE TABLE IF NOT EXISTS `apcupsd_ups` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`poller_id` int(10) unsigned DEFAULT 1,
		`host_id` int(10) unsigned NOT NULL DEFAULT 0,
		`site_id` int(10) unsigned NOT NULL DEFAULT 0,
		`type_id` int(10) unsigned NOT NULL DEFAULT 0,
		`name` varchar(40) NOT NULL DEFAULT '',
		`description` varchar(128) NOT NULL DEFAULT '',
		`snmp_version` tinyint(3) unsigned DEFAULT 2,
		`snmp_community` varchar(100) NOT NULL DEFAULT '',
		`snmp_username` varchar(50) NOT NULL DEFAULT '',
		`snmp_password` varchar(50) NOT NULL DEFAULT '',
		`snmp_auth_protocol` varchar(6) NOT NULL DEFAULT '',
		`snmp_priv_protocol` varchar(6) NOT NULL DEFAULT '',
		`snmp_priv_passphrase` varchar(200) NOT NULL DEFAULT '',
		`snmp_context` varchar(64) NOT NULL DEFAULT '',
		`snmp_engine_id` varchar(64) NOT NULL DEFAULT '',
		`snmp_port` tinyint(3) unsigned NOT NULL DEFAULT 161,
		`snmp_timeout` int(10) unsigned NOT NULL DEFAULT 2000,
		`snmp_skipped` varchar(255) NOT NULL DEFAULT '',
		`status` int(10) unsigned NOT NULL DEFAULT 0,
		`hostname` varchar(64) NOT NULL DEFAULT '',
		`port` int(10) unsigned NOT NULL DEFAULT 3551,
		`enabled` char(2) DEFAULT 'on',
		`error_message` varchar(255) DEFAULT '',
		`last_updated` timestamp NOT NULL DEFAULT current_timestamp(),
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
		`ups_master` varchar(128) not null default '',
		`ups_cable` varchar(20) not null default '',
		`ups_driver` varchar(20) not null default '',
		`ups_mode` varchar(20) not null default '',

		`ups_starttime` timestamp not null default CURRENT_TIMESTAMP,
		`ups_mandate` timestamp not null default CURRENT_TIMESTAMP,
		`ups_masterupd` timestamp not null default CURRENT_TIMESTAMP,
		`ups_xonbatt` timestamp not null default CURRENT_TIMESTAMP,
		`ups_laststest` timestamp not null default CURRENT_TIMESTAMP,

		`ups_model` varchar(40) not null default '',
		`ups_status` varchar(20) not null default '',

		`ups_dipsw` varchar(20) not null default '',
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
		`ups_selftest_interval` varchar(20) not null default '',
		`ups_statflag` varchar(20) not null default '',
		`ups_serialno` varchar(20) not null default '',

		`ups_nominal_voltage` double default null,
		`ups_nominal_batt_voltage` double default null,
		`ups_nominal_power` double default null,
		`ups_nominal_output_voltage` double default null,

		`ups_ambtemp` double default null,
		`ups_humidity` double default null,
		`ups_internal_temp` double default null,

		`ups_firmware` varchar(40) not null default '',
		`ups_end_rec` timestamp not null default CURRENT_TIMESTAMP,
		PRIMARY KEY(ups_id))
		ENGINE=InnoDB
		COMMENT='Monitored UPS Status Table'");

	return true;
}

/**
 * Reads this plugin's INFO file and returns its [info] section. Used by
 * Cacti's plugin architecture via the api_plugin_version hook, and
 * internally by apcupsd_check_upgrade() and poller_apcupsd.php's
 * display_version() to detect/report the plugin's version.
 *
 * @return array The parsed [info] section of the plugin's INFO file (keys
 *               such as name, version, author, homepage, longname).
 *
 * @global array $config Cacti global configuration array; used to locate
 *                        the plugin's base path.
 */
function plugin_apcupsd_version () {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/apcupsd/INFO', true);
	return $info['info'];
}

/**
 * Determines whether the current request represents a "valid" auditable
 * event for this plugin's logging purposes (e.g. a POST submission or a
 * plugins.php mode change), excluding known noisy/irrelevant pages such
 * as graph_view.php and the login/password pages. Used by Cacti core's
 * auditing hook to decide whether to record an event for the current
 * page load, when the 'apcupsd_enabled' setting is on.
 *
 * @return bool True when the current request should be logged as a
 *              valid event.
 *
 * @global string $action Set to the detected action ('purge', or the
 *                         plugins.php 'mode' value) for the caller to
 *                         include in its log entry.
 */
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

/**
 * Hook implementation for Cacti's 'config_arrays' filter. Adds the
 * "UPSes" entry under the Management section of Cacti's menu, augments
 * the System Administration role with this plugin's page where
 * supported, and triggers this plugin's upgrade check. Called by Cacti
 * core via api_plugin_hook('config_arrays', ...) while building the
 * navigation menu.
 *
 * @return void
 *
 * @global array $menu Cacti's main navigation menu array, extended here
 *                      with this plugin's entry.
 */
function apcupsd_config_arrays() {
	global $menu;

	$menu[__('Management')]['plugins/apcupsd/upses.php'] = __('UPSes', 'webseer');

	if (function_exists('auth_augment_roles')) {
		auth_augment_roles(__('System Administration'), array('upses.php'));
	}

	apcupsd_check_upgrade();
}

/**
 * Hook implementation for Cacti's 'config_settings' filter. Intended to
 * register this plugin's Settings page tab/fields; currently a no-op
 * (the function body is empty). Called by Cacti core via
 * api_plugin_hook('config_settings', ...) while building the Settings
 * page.
 *
 * @return void
 *
 * @global array $tabs                Cacti's registered Settings page
 *                                     tabs (unused directly here).
 * @global array $settings            Cacti's registered Settings page
 *                                     fields (unused directly here).
 * @global array $item_rows           Rows-per-page options offered by
 *                                     Cacti core (unused directly here).
 * @global array $apcupsd_retentions  Reserved for this plugin's data
 *                                     retention options (unused directly
 *                                     here).
 */
function apcupsd_config_settings () {
	global $tabs, $settings, $item_rows, $apcupsd_retentions;

}

/**
 * Hook implementation for Cacti's 'replicate_out' filter. Replicates this
 * plugin's apcupsd_ups and apcupsd_ups_stats table contents out to a
 * remote poller in a distributed Cacti setup. Called by Cacti core via
 * api_plugin_hook('replicate_out', ...) during remote poller data
 * replication.
 *
 * @param array $data The replication context, including 'rcnn_id' (the
 *                     remote connection id) and 'remote_poller_id'.
 *
 * @return array The unmodified $data array (this hook does not modify
 *               its payload).
 *
 * @global array $config Cacti global configuration array; used to load
 *                        lib/poller.php.
 */
function apcupsd_replicate_out($data) {
	global $config;

	include_once($config['base_path'] . '/lib/poller.php');

	$upsdata = db_fetch_assoc_prepared('SELECT *
		FROM apcupsd_ups',
		array());

	replicate_out_table($data['rcnn_id'], $upsdata, 'apcupsd_ups', $data['remote_poller_id']);

	$upsdata = db_fetch_assoc_prepared('SELECT *
		FROM apcupsd_ups_stats',
		array());

	replicate_out_table($data['rcnn_id'], $upsdata, 'apcupsd_ups_stats', $data['remote_poller_id']);

	return $data;
}

/**
 * Hook implementation for Cacti's 'draw_navigation_text' filter. Adds a
 * breadcrumb entry for upses.php's default view. Called by Cacti core
 * via api_plugin_hook('draw_navigation_text', ...) while rendering the
 * page breadcrumb trail.
 *
 * @param array $nav The existing breadcrumb map contributed by Cacti
 *                    core and other plugins.
 *
 * @return array The $nav array with this plugin's breadcrumb entry
 *               added.
 */
function apcupsd_draw_navigation_text($nav) {
	$nav['upses.php:'] = array(
		'title'   => __('Manage UPSes', 'apcupsd'),
		'mapping' => 'index.php:',
		'url'     => 'upses.php',
		'level'   => '1'
	);

	return $nav;
}
