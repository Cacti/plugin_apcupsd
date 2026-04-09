<?php

declare(strict_types=1);

$ups_database = array(
	'ALARMDEL' => array(
		'db_column'   => 'ups_alarmdel',
		'snmp_ci'     => 'NA',
		'description' => __esc('Delay period before APCUPSD starts sounding alarm', 'apcupsd')
	),
	'AMBTEMP' => array(
		'db_column'   => 'ups_ambtemp',
		'snmp_ci'     => '1.3.6.1.4.1.318.1.1.2.1.1',
		'description' => __esc('Ambient temperature', 'apcupsd')
	),
	'APC' => array(
		'db_column'   => 'ups_key',
		'snmp_ci'     => '',
		'description' => __esc('version, number of records and number of bytes following', 'apcupsd')
	),
	'APCMODEL' => array(
		'db_column'   => 'ups_model',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.1.1.1.0',
		'description' => __esc('APC model information', 'apcupsd')
	),
	'BADBATTS' => array(
		'db_column'   => 'ups_badbatts',
		'snmp_ci'     => '',
		'description' => __esc('Number of bad external battery packs (for XL models)', 'apcupsd')
	),
	'BATTDATE' => array(
		'db_column'   => 'ups_battery_date',
		'snmp_ci'     => '1.3.6.1.4.1.318.1.1.1.2.1.3.0',
		'description' => __esc('Date battery last replaced (if set)', 'apcupsd')
	),
	'BATTSTAT' => array(
		'db_column'   => 'ups_battery_status',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.2.1.1.0',
		'description' => __esc('Battery status.', 'apcupsd'),
		'snmp_enum'   => []
	),
	'BATTV' => array(
		'db_column'   => 'ups_battery_voltage',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.2.2.8.0',
		'description' => __esc('Current battery voltage', 'apcupsd')
	),
	'BCHARGE' => array(
		'db_column'   => 'ups_battery_charge',
		'snmp_ci'     => '1.3.6.1.4.1.318.1.1.1.2.2.1.0',
		'description' => __esc('Current battery capacity charge percentage', 'apcupsd')
	),
	'CABLE' => array(
		'db_column'   => 'ups_cable',
		'snmp_ci'     => 'NA',
		'description' => __esc('Cable type specified in the configuration file', 'apcupsd')
	),
	'CUMONBATT' => array(
		'db_column'   => 'ups_cumonbatt',
		'snmp_ci'     => 'NA',
		'description' => __esc('Cumulative seconds on battery since apcupsd startup', 'apcupsd')
	),
	'DATE' => array(
		'db_column'   => 'ups_date',
		'snmp_ci'     => 'CURDATE',
		'description' => __esc('Date and time of last update from UPS', 'apcupsd')
	),
	'DIPSW' => array(
		'db_column'   => 'ups_dipsw',
		'snmp_ci'     => '',
		'description' => __esc('Current UPS DIP switch settings', 'apcupsd')
	),
	'DLOWBATT' => array(
		'db_column'   => 'ups_dlowbatt',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.5.2.8.0',
		'description' => __esc('Low battery signal sent when this much runtime remains', 'apcupsd')
	),
	'DRIVER' => array(
		'db_column'   => 'ups_driver',
		'snmp_ci'     => 'NA',
		'description' => __esc('The APCUPSD Driver when APCUPSD is the protocol', 'apcupsd')
	),
	'DSHUTD' => array(
		'db_column'   => 'ups_dshutd',
		'snmp_ci'     => '',
		'description' => __esc('Delay before UPS powers down after command received', 'apcupsd')
	),
	'DWAKE' => array(
		'db_column'   => 'ups_dwake',
		'snmp_ci'     => '',
		'description' => __esc('Time UPS waits after power off when the power is restored', 'apcupsd')
	),
	'ENDAPC' => array(
		'db_column'   => 'ups_end_rec',
		'snmp_ci'     => 'CURDATE',
		'description' => __esc('Date and time of status information was written', 'apcupsd')
	),
	'END APC' => array(
		'db_column'   => 'ups_end_rec',
		'snmp_ci'     => 'CURDATE',
		'description' => __esc('Date and time of status information was written', 'apcupsd')
	),
	'EXTBATTS' => array(
		'db_column'   => 'ups_extbatts',
		'snmp_ci'     => '',
		'description' => __esc('Number of external batteries (for XL models)', 'apcupsd')
	),
	'FIRMWARE' => array(
		'db_column'   => 'ups_firmware',
		'snmp_ci'     => '1.3.6.1.4.1.318.1.1.1.1.2.1.0',
		'description' => __esc('UPS firmware version', 'apcupsd')
	),
	'HITRANS' => array(
		'db_column'   => 'ups_hitrans',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.5.2.3.0',
		'description' => __esc('Input line voltage above which UPS will switch to battery', 'apcupsd')
	),
	'HOSTNAME' => array(
		'db_column'   => 'ups_hostname',
		'snmp_ci'     => 'NA',
		'description' => __esc('hostname of computer running apcupsd', 'apcupsd')
	),
	'HUMIDITY' => array(
		'db_column'   => 'ups_humidity',
		'snmp_ci'     => '',
		'description' => __esc('Ambient humidity', 'apcupsd')
	),
	'ITEMP' => array(
		'db_column'   => 'ups_internal_temp',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.2.2.2.0',
		'description' => __esc('UPS internal temperature in degrees Celsius', 'apcupsd')
	),
	'LASTXFER' => array(
		'db_column'   => 'ups_lastxfer',
		'snmp_ci'     => '',
		'description' => __esc('Reason for last transfer to battery since apcupsd startup', 'apcupsd')
	),
	'LINEFAIL' => array(
		'db_column'   => 'ups_line_fail',
		'snmp_ci'     => '',
		'description' => __esc('Input line voltage status.', 'apcupsd')
	),
	'LINEFREQ' => array(
		'db_column'   => 'ups_line_frequency',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.3.2.4.0',
		'description' => __esc('Current line frequency in Hertz', 'apcupsd')
	),
	'LINEV' => array(
		'db_column'   => 'ups_line_voltage',
		'snmp_ci'     => '1.3.6.1.4.1.318.1.1.1.3.2.1.0',
		'description' => __esc('Current input line voltage', 'apcupsd')
	),
	'LOADPCT' => array(
		'db_column'   => 'ups_load_percent',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.4.2.3.0',
		'description' => __esc('Percentage of UPS load capacity used as estimated by UPS', 'apcupsd')
	),
	'LOTRANS' => array(
		'db_column'   => 'ups_lowtrans',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.5.2.2.0',
		'description' => __esc('Input line voltage below which UPS will switch to battery', 'apcupsd')
	),
	'MANDATE' => array(
		'db_column'   => 'ups_mandate',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.1.2.2.0',
		'description' => __esc('UPS date of manufacture', 'apcupsd')
	),
	'MASTERUPD' => array(
		'db_column'   => 'ups_masterupd',
		'snmp_ci'     => 'NA',
		'description' => __esc('Last time the master sent an update to the slave', 'apcupsd')
	),
	'MAXLINEV' => array(
		'db_column'   => 'ups_max_line_voltage',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.3.2.2.0',
		'description' => __esc('Maximum input line voltage since apcupsd started', 'apcupsd')
	),
	'MAXTIME' => array(
		'db_column'   => 'ups_maxtime',
		'snmp_ci'     => '',
		'description' => __esc('Max battery runtime (TIMEOUT) after which system is shutdown', 'apcupsd')
	),
	'MBATTCHG' => array(
		'db_column'   => 'ups_mbattchg',
		'snmp_ci'     => '',
		'description' => __esc('Min battery charge %%% (BCHARGE) required for system shutdown', 'apcupsd')
	),
	'MINLINEV' => array(
		'db_column'   => 'ups_min_line_voltage',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.3.2.3.0',
		'description' => __esc('Min (observed) input line voltage since apcupsd started', 'apcupsd')
	),
	'MINTIMEL' => array(
		'db_column'   => 'ups_mintimel',
		'snmp_ci'     => '',
		'description' => __esc('Min battery runtime (MINUTES) required for system shutdown', 'apcupsd')
	),
	'MODEL' => array(
		'db_column'   => 'ups_model',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.1.1.1.0',
		'description' => __esc('UPS model derived from UPS information', 'apcupsd')
	),
	'NOMBATTV' => array(
		'db_column'   => 'ups_nominal_batt_voltage',
		'snmp_ci'     => '1.3.6.1.4.1.318.1.1.1.2.2.7',
		'description' => __esc('Nominal battery voltage', 'apcupsd')
	),
	'NOMINV' => array(
		'db_column'   => 'ups_nominal_voltage',
		'snmp_ci'     => '1.3.6.1.4.1.318.1.1.1.2.3.3',
		'description' => __esc('Nominal input voltage delivered to the UPS.', 'apcupsd')
	),
	'NOMOUTV' => array(
		'db_column'   => 'ups_nominal_output_voltage',
		'snmp_ci'     => '1.3.6.1.2.1.33.1.9.3.0',
		'description' => __esc('Nominal output voltage to supply when on battery power', 'apcupsd')
	),
	'NOMPOWER' => array(
		'db_column'   => 'ups_nominal_power',
		'snmp_ci'     => '.1.3.6.1.2.1.33.1.9.6.0',
		'description' => __esc('Nominal power output in watts', 'apcupsd')
	),
	'NUMXFERS' => array(
		'db_column'   => 'ups_numxfers',
		'snmp_ci'     => 'NA',
		'description' => __esc('Number of transfers to battery since apcupsd startup', 'apcupsd')
	),
	'OUTPUTV' => array(
		'db_column'   => 'ups_output_voltage',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.4.2.1.0',
		'description' => __esc('Current UPS output voltage', 'apcupsd')
	),
	'REG1' => array(
		'db_column'   => 'ups_reg1',
		'snmp_ci'     => '',
		'description' => __esc('Fault register 1 in hex', 'apcupsd')
	),
	'REG2' => array(
		'db_column'   => 'ups_reg2',
		'snmp_ci'     => '',
		'description' => __esc('Fault register 2 in hex', 'apcupsd')
	),
	'REG3' => array(
		'db_column'   => 'ups_reg3',
		'snmp_ci'     => '',
		'description' => __esc('Fault register 3 in hex', 'apcupsd')
	),
	'RETPCT' => array(
		'db_column'   => 'ups_battery_retpct',
		'snmp_ci'     => '',
		'description' => __esc('Battery charge %%% required after power off to restore power', 'apcupsd')
	),
	'SELFTEST' => array(
		'db_column'   => 'ups_selftest',
		'snmp_ci'     => '.1.3.6.1.2.1.33.1.7.3.0',
		'description' => __esc('Date and time of last self test since apcupsd startup', 'apcupsd'),
		'snmp_enum'   => []
	),
	'SENSE' => array(
		'db_column'   => 'ups_sense',
		'snmp_ci'     => '1.3.6.1.4.1.318.1.1.1.5.2.7.0',
		'description' => __esc('Current UPS sensitivity setting for voltage fluctuations', 'apcupsd'),
		'snmp_enum'   => []
	),
	'SERIALNO' => array(
		'db_column'   => 'ups_serialno',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.1.2.3.0',
		'description' => __esc('UPS serial number', 'apcupsd')
	),
	'STARTTIME' => array(
		'db_column'   => 'ups_starttime',
		'snmp_ci'     => 'NA',
		'description' => __esc('Date and time apcupsd was started', 'apcupsd')
	),
	'STATFLAG' => array(
		'db_column'   => 'ups_statflag',
		'snmp_ci'     => 'NA',
		'description' => __esc('UPS status flag in hex', 'apcupsd')
	),
	'STATUS' => array(
		'db_column'   => 'ups_status',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.4.1.1.0',
		'description' => __esc('UPS status. One or more of the following (space-separated): CAL TRIM BOOST ONLINE ONBATT OVERLOAD LOWBATT REPLACEBATT NOBATT SLAVE SLAVEDOWN or COMMLOST or SHUTTING DOWN', 'apcupsd'),
		'snmp_enum'   => []
	),
	'LASTSTEST' => array(
		'db_column'   => 'ups_laststest',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.7.2.4.0',
		'description' => __esc('Date of the last UPS self test', 'apcupsd')
	),
	'STESTI' => array(
		'db_column'   => 'ups_selftest_interval',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.7.2.1.0',
		'description' => __esc('Self-test interval', 'apcupsd'),
		'snmp_enum'   => []
	),
	'TIMELEFT' => array(
		'db_column'   => 'ups_timeleft',
		'snmp_ci'     => '1.3.6.1.4.1.318.1.1.1.2.2.3.0',
		'description' => __esc('Remaining runtime left on battery as estimated by the UPS', 'apcupsd')
	),
	'TONBATT' => array(
		'db_column'   => 'ups_tonbatt',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.2.1.2.0',
		'description' => __esc('Seconds currently on battery', 'apcupsd')
	),
	'UPSMODE' => array(
		'db_column'   => 'ups_mode',
		'snmp_ci'     => 'UNKNOWN',
		'description' => __esc('Mode in which UPS is operating', 'apcupsd')
	),
	'UPSNAME' => array(
		'db_column'   => 'ups_name',
		'snmp_ci'     => '.1.3.6.1.4.1.318.1.1.1.1.1.2.0',
		'description' => __esc('UPS name from configuration file (dumb) or EEPROM (smart)', 'apcupsd')
	),
	'MASTER' => array(
		'db_column'   => 'ups_master',
		'description' => __esc('UPS master name in the case where APCUPSD is pulling the data from another instance of APCUPSD', 'apcupsd')
	),
	'VERSION' => array(
		'db_column'   => 'ups_version',
		'snmp_ci'     => 'NA',
		'description' => __esc('apcupsd version number, date and operating system', 'apcupsd')
	),
	'XOFFBAT' => array(
		'db_column'   => 'ups_xoffbatt',
		'snmp_ci'     => 'NA',
		'description' => __esc('Date, time of last transfer off battery since apcupsd startup', 'apcupsd')
	),
	'XOFFBATT' => array(
		'db_column'   => 'ups_xoffbatt',
		'snmp_ci'     => 'NA',
		'description' => __esc('Date, time of last transfer off battery since apcupsd startup', 'apcupsd')
	),
	'XONBATT' => array(
		'db_column'   => 'ups_xonbatt',
		'snmp_ci'     => 'NA',
		'description' => __esc('Date, time of last transfer to battery since apcupsd startup', 'apcupsd')
	)
);

/**
 * APC UPSD OID Cross Reference
 * Source: src/drivers/snmplite/apc-oids.h
 */
$apc_snmp_config = array(
	'CI_UPSMODEL' => [],
	'CI_STATUS' => [],
	'CI_WHY_BATT' => [],
	'CI_ST_STAT' => [],
	'CI_VLINE' => [],
	'CI_VMAX' => [],
	'CI_VMIN' => [],
	'CI_VOUT' => [],
	'CI_BATTLEV' => [],
	'CI_VBATT' => [],
	'CI_LOAD' => [],
	'CI_FREQ' => [],
	'CI_RUNTIM' => [],
	'CI_ITEMP' => [],
	'CI_DWAKE' => [],
	'CI_DSHUTD' => [],
	'CI_LTRANS' => [],
	'CI_HTRANS' => [],
	'CI_RETPCT' => [],
	'CI_AlarmTimer' => [],
	'CI_DALARM' => [],
	'CI_DLBATT' => [],
	'CI_IDEN' => [],
	'CI_STESTI' => [],
	'CI_SERNO' => [],
	'CI_NOMBATTV' => [],
	'CI_HUMID' => [],
	'CI_REVNO' => [],
	'CI_ATEMP' => [],
	'CI_NOMOUTV' => [],
	'CI_Boost' => [],
	'CI_Trim' => [],
	'CI_Overload' => [],
	'CI_NeedReplacement' => [],
	'CI_LowBattery' => []
);

/**
 * APC UPSD OID Cross Reference
 * Source: src/drivers/snmplite/apc-oids.h
 */
$apc_oids = [];

/**
 * APC UPSD OID Cross Reference
 * Source: src/drivers/snmplite/mge-oids.h
 */
$mge_oids = [];

/**
 * APC UPSD OID Cross Reference
 * Source: src/drivers/snmplite/rfc1628-oids.h
 */
$rfc_oids = [];

