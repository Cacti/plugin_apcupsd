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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * The apcupsd_ups table definition (one row per monitored UPS device),
 * shared by the create path (api_plugin_db_table_create() in
 * apcupsd_setup_table()) and the upgrade path (db_update_table() in
 * apcupsd_upgrade_tables()) so both stay in sync from a single source.
 *
 * @return array<string,mixed> Table definition consumed by
 *                             api_plugin_db_table_create()/db_update_table().
 */
function apcupsd_ups_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id',                   'type' => 'int(10)',      'unsigned' => true, 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'poller_id',            'type' => 'int(10)',      'unsigned' => true, 'NULL' => true,  'default' => 1];
	$data['columns'][] = ['name' => 'host_id',              'type' => 'int(10)',      'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'site_id',              'type' => 'int(10)',      'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'type_id',              'type' => 'int(10)',      'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'name',                 'type' => 'varchar(40)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'description',          'type' => 'varchar(128)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'snmp_version',         'type' => 'tinyint(3)',   'unsigned' => true, 'NULL' => true, 'default' => 2];
	$data['columns'][] = ['name' => 'snmp_community',       'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'snmp_username',        'type' => 'varchar(50)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'snmp_password',        'type' => 'varchar(50)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'snmp_auth_protocol',   'type' => 'varchar(6)',   'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'snmp_priv_protocol',   'type' => 'varchar(6)',   'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'snmp_priv_passphrase', 'type' => 'varchar(200)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'snmp_context',         'type' => 'varchar(64)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'snmp_engine_id',       'type' => 'varchar(64)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'snmp_port',            'type' => 'tinyint(3)',   'unsigned' => true, 'NULL' => false, 'default' => 161];
	$data['columns'][] = ['name' => 'snmp_timeout',         'type' => 'int(10)',      'unsigned' => true, 'NULL' => false, 'default' => 2000];
	$data['columns'][] = ['name' => 'snmp_skipped',         'type' => 'varchar(255)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'status',               'type' => 'int(10)',      'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'hostname',             'type' => 'varchar(64)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'port',                 'type' => 'int(10)',      'unsigned' => true, 'NULL' => false, 'default' => 3551];
	$data['columns'][] = ['name' => 'enabled',              'type' => 'char(2)',      'NULL' => true, 'default' => 'on'];
	$data['columns'][] = ['name' => 'error_message',        'type' => 'varchar(255)', 'NULL' => true, 'default' => ''];
	$data['columns'][] = ['name' => 'last_updated',         'type' => 'timestamp',    'NULL' => false, 'default' => 'CURRENT_TIMESTAMP'];
	$data['primary']   = ['id'];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Monitored UPS Table';

	return $data;
}

/**
 * The apcupsd_ups_stats table definition (latest polled readings per UPS),
 * using the current column layout (post-rename: ups_ambtemp, ups_dipsw,
 * ups_master). Shared by the create and upgrade paths.
 *
 * @return array<string,mixed> Table definition consumed by
 *                             api_plugin_db_table_create()/db_update_table().
 */
function apcupsd_ups_stats_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'ups_id',                     'type' => 'int(10)',      'unsigned' => true, 'NULL' => false];
	$data['columns'][] = ['name' => 'ups_key',                    'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_date',                   'type' => 'timestamp',    'NULL' => false, 'default' => 'CURRENT_TIMESTAMP'];
	$data['columns'][] = ['name' => 'ups_hostname',               'type' => 'varchar(64)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_version',                'type' => 'varchar(64)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_name',                   'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_master',                 'type' => 'varchar(128)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_cable',                  'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_driver',                 'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_mode',                   'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_starttime',             'type' => 'timestamp',    'NULL' => false, 'default' => 'CURRENT_TIMESTAMP'];
	$data['columns'][] = ['name' => 'ups_mandate',               'type' => 'timestamp',    'NULL' => false, 'default' => 'CURRENT_TIMESTAMP'];
	$data['columns'][] = ['name' => 'ups_masterupd',             'type' => 'timestamp',    'NULL' => false, 'default' => 'CURRENT_TIMESTAMP'];
	$data['columns'][] = ['name' => 'ups_xonbatt',               'type' => 'timestamp',    'NULL' => false, 'default' => 'CURRENT_TIMESTAMP'];
	$data['columns'][] = ['name' => 'ups_laststest',             'type' => 'timestamp',    'NULL' => false, 'default' => 'CURRENT_TIMESTAMP'];
	$data['columns'][] = ['name' => 'ups_model',                 'type' => 'varchar(40)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_status',                'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_dipsw',                 'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_extbatts',             'type' => 'int(10)',      'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_badbatts',             'type' => 'int(10)',      'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_reg1',                  'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_reg2',                  'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_reg3',                  'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_line_voltage',         'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_line_fail',            'type' => 'varchar(20)',  'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ups_load_percent',         'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_line_frequency',       'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_output_voltage',       'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_max_line_voltage',     'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_min_line_voltage',     'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_timeleft',             'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_mbattchg',             'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_mintimel',             'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_maxtime',              'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_sense',                'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_lowtrans',             'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_hitrans',              'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_alarmdel',            'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_dlowbatt',            'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_dshutd',              'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_dwake',               'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_battery_status',      'type' => 'varchar(60)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_battery_charge',      'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_battery_voltage',     'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_battery_date',        'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_battery_retpct',      'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_lastxfer',           'type' => 'varchar(40)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_numxfers',           'type' => 'int(10)',      'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_tonbatt',            'type' => 'int(10)',      'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_cumonbatt',          'type' => 'int(10)',      'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_xoffbatt',           'type' => 'int(10)',      'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_selftest',           'type' => 'varchar(10)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_selftest_interval',  'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_statflag',           'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_serialno',           'type' => 'varchar(20)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_nominal_voltage',    'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_nominal_batt_voltage','type' => 'double',      'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_nominal_power',      'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_nominal_output_voltage','type' => 'double',    'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_ambtemp',           'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_humidity',          'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_internal_temp',     'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'ups_firmware',          'type' => 'varchar(40)',  'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ups_end_rec',           'type' => 'timestamp',    'NULL' => false, 'default' => 'CURRENT_TIMESTAMP'];
	$data['primary']   = ['ups_id'];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Monitored UPS Status Table';

	return $data;
}

/**
 * Creates this plugin's tables (apcupsd_ups, apcupsd_ups_stats) through
 * Cacti's tracked plugin table API. Called from plugin_apcupsd_install()
 * during installation, and re-run (safely) from apcupsd_upgrade_tables().
 *
 * @return bool Always returns true.
 */
function apcupsd_setup_table(): bool {
	api_plugin_db_table_create('apcupsd', 'apcupsd_ups', apcupsd_ups_table_data());
	api_plugin_db_table_create('apcupsd', 'apcupsd_ups_stats', apcupsd_ups_stats_table_data());

	return true;
}

/**
 * Refreshes this plugin's tables on upgrade. The apcupsd_ups_stats column
 * renames/adds below cannot be expressed by db_update_table(), so they run
 * first as guarded pre-steps to bring older installations onto the current
 * column layout; db_update_table() then reconciles the rest (or creates the
 * table outright when missing). Called from apcupsd_check_upgrade().
 *
 * @return void
 */
function apcupsd_upgrade_tables(): void {
	if (db_column_exists('apcupsd_ups_stats', 'ups_abmtemp')) {
		db_execute('ALTER TABLE apcupsd_ups_stats CHANGE COLUMN ups_abmtemp ups_ambtemp DOUBLE default NULL');
	}

	if (!db_column_exists('apcupsd_ups_stats', 'ups_master')) {
		db_execute('ALTER TABLE apcupsd_ups_stats ADD COLUMN ups_master varchar(128) NOT NULL default "" AFTER ups_name');
	}

	if (db_column_exists('apcupsd_ups_stats', 'ups_dispsw')) {
		db_execute('ALTER TABLE apcupsd_ups_stats CHANGE COLUMN ups_dispsw ups_dipsw VARCHAR(20) NOT NULL default ""');
	}

	$tables = [
		'apcupsd_ups'       => apcupsd_ups_table_data(),
		'apcupsd_ups_stats' => apcupsd_ups_stats_table_data(),
	];

	foreach ($tables as $table => $table_data) {
		if (db_table_exists($table)) {
			db_update_table($table, $table_data);
		} else {
			api_plugin_db_table_create('apcupsd', $table, $table_data);
		}
	}
}

/**
 * Drops every table this plugin owns. Called from
 * plugin_apcupsd_uninstall() when the plugin is removed.
 *
 * @return void
 */
function apcupsd_drop_tables(): void {
	db_execute('DROP TABLE IF EXISTS apcupsd_ups');
	db_execute('DROP TABLE IF EXISTS apcupsd_ups_stats');
}
