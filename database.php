<?php

$ups_database = array(
	'ALARMDEL' => array(
		'db_column'   => 'ups_alarmdel',
		'oid'         => '',
		'description' => __esc('Delay period before UPS starts sounding alarm', 'apcupsd')
	),
	'AMBTEMP' => array(
		'db_column'   => 'ups_ambtemp',
		'oid'         => '',
		'description' => __esc('Ambient temperature', 'apcupsd')
	),
	'APC' => array(
		'db_column'   => 'ups_key',
		'oid'         => '',
		'description' => __esc('version, number of records and number of bytes following', 'apcupsd')
	),
	'APCMODEL' => array(
		'db_column'   => 'ups_model',
		'oid'         => '1.3.6.1.4.1.318.1.1.1.1.1.1.0',
		'description' => __esc('APC model information', 'apcupsd')
	),
	'BADBATTS' => array(
		'db_column'   => 'ups_badbatts',
		'oid'         => '',
		'description' => __esc('Number of bad external battery packs (for XL models)', 'apcupsd')
	),
	'BATTDATE' => array(
		'db_column'   => 'ups_battery_date',
		'oid'         => '1.3.6.1.4.1.318.1.1.1.2.1.3.0',
		'description' => __esc('Date battery last replaced (if set)', 'apcupsd')
	),
	'BATTSTAT' => array(
		'db_column'   => 'ups_battery_status',
		'oid'         => '',
		'description' => __esc('Battery status.', 'apcupsd')
	),
	'BATTV' => array(
		'db_column'   => 'ups_battery_voltage',
		'oid'         => '',
		'description' => __esc('Current battery voltage', 'apcupsd')
	),
	'BCHARGE' => array(
		'db_column'   => 'ups_battery_charge',
		'oid'         => '1.3.6.1.4.1.318.1.1.1.2.2.1.0',
		'description' => __esc('Current battery capacity charge percentage', 'apcupsd')
	),
	'CABLE' => array(
		'db_column'   => 'ups_cable',
		'oid'         => '',
		'description' => __esc('Cable type specified in the configuration file', 'apcupsd')
	),
	'CUMONBATT' => array(
		'db_column'   => 'ups_cumonbatt',
		'oid'         => '',
		'description' => __esc('Cumulative seconds on battery since apcupsd startup', 'apcupsd')
	),
	'DATE' => array(
		'db_column'   => 'ups_date',
		'oid'         => '',
		'description' => __esc('Date and time of last update from UPS', 'apcupsd')
	),
	'DIPSW' => array(
		'db_column'   => 'ups_dipsw',
		'oid'         => '',
		'description' => __esc('Current UPS DIP switch settings', 'apcupsd')
	),
	'DLOWBATT' => array(
		'db_column'   => 'ups_dlowbatt',
		'oid'         => '',
		'description' => __esc('Low battery signal sent when this much runtime remains', 'apcupsd')
	),
	'DRIVER' => array(
		'db_column'   => 'ups_driver',
		'oid'         => '',
		'description' => __esc('The APCUPSD Driver when APCUPSD is the protocol', 'apcupsd')
	),
	'DSHUTD' => array(
		'db_column'   => 'ups_dshutd',
		'oid'         => '',
		'description' => __esc('Delay before UPS powers down after command received', 'apcupsd')
	),
	'DWAKE' => array(
		'db_column'   => 'ups_dwake',
		'oid'         => '',
		'description' => __esc('Time UPS waits after power off when the power is restored', 'apcupsd')
	),
	'ENDAPC' => array(
		'db_column'   => 'ups_end_rec',
		'oid'         => '',
		'description' => __esc('Date and time of status information was written', 'apcupsd')
	),
	'END APC' => array(
		'db_column'   => 'ups_end_rec',
		'oid'         => '',
		'description' => __esc('Date and time of status information was written', 'apcupsd')
	),
	'EXTBATTS' => array(
		'db_column'   => 'ups_extbatts',
		'oid'         => '',
		'description' => __esc('Number of external batteries (for XL models)', 'apcupsd')
	),
	'FIRMWARE' => array(
		'db_column'   => 'ups_firmware',
		'oid'         => '1.3.6.1.4.1.318.1.1.1.1.2.1.0',
		'description' => __esc('UPS firmware version', 'apcupsd')
	),
	'HITRANS' => array(
		'db_column'   => 'ups_hitrans',
		'oid'         => '1.3.6.1.4.1.318.1.1.1.5.2.4.0',
		'description' => __esc('Input line voltage above which UPS will switch to battery', 'apcupsd')
	),
	'HOSTNAME' => array(
		'db_column'   => 'ups_hostname',
		'oid'         => '',
		'description' => __esc('hostname of computer running apcupsd', 'apcupsd')
	),
	'HUMIDITY' => array(
		'db_column'   => 'ups_humidity',
		'oid'         => '',
		'description' => __esc('Ambient humidity', 'apcupsd')
	),
	'ITEMP' => array(
		'db_column'   => 'ups_internal_temp',
		'oid'         => '',
		'description' => __esc('UPS internal temperature in degrees Celcius', 'apcupsd')
	),
	'LASTXFER' => array(
		'db_column'   => 'ups_lastxfer',
		'oid'         => '',
		'description' => __esc('Reason for last transfer to battery since apcupsd startup', 'apcupsd')
	),
	'LINEFAIL' => array(
		'db_column'   => 'ups_line_fail',
		'oid'         => '',
		'description' => __esc('Input line voltage status.', 'apcupsd')
	),
	'LINEFREQ' => array(
		'db_column'   => 'ups_line_frequency',
		'oid'         => '',
		'description' => __esc('Current line frequency in Hertz', 'apcupsd')
	),
	'LINEV' => array(
		'db_column'   => 'ups_line_voltage',
		'oid'         => '1.3.6.1.4.1.318.1.1.1.3.2.1.0',
		'description' => __esc('Current input line voltage', 'apcupsd')
	),
	'LOADPCT' => array(
		'db_column'   => 'ups_load_percent',
		'oid'         => '',
		'description' => __esc('Percentage of UPS load capacity used as estimated by UPS', 'apcupsd')
	),
	'LOTRANS' => array(
		'db_column'   => 'ups_lowtrans',
		'oid'         => '1.3.6.1.4.1.318.1.1.1.5.2.3.0',
		'description' => __esc('Input line voltage below which UPS will switch to battery', 'apcupsd')
	),
	'MANDATE' => array(
		'db_column'   => 'ups_mandate',
		'oid'         => '',
		'description' => __esc('UPS date of manufacture', 'apcupsd')
	),
	'MASTERUPD' => array(
		'db_column'   => 'ups_masterupd',
		'oid'         => '',
		'description' => __esc('Last time the master sent an update to the slave', 'apcupsd')
	),
	'MAXLINEV' => array(
		'db_column'   => 'ups_max_line_voltage',
		'oid'         => '',
		'description' => __esc('Maximum input line voltage since apcupsd started', 'apcupsd')
	),
	'MAXTIME' => array(
		'db_column'   => 'ups_maxtime',
		'oid'         => '',
		'description' => __esc('Max battery runtime (TIMEOUT) after which system is shutdown', 'apcupsd')
	),
	'MBATTCHG' => array(
		'db_column'   => 'ups_mbattchg',
		'oid'         => '',
		'description' => __esc('Min battery charge %%% (BCHARGE) required for system shutdown', 'apcupsd')
	),
	'MINLINEV' => array(
		'db_column'   => 'ups_min_line_voltage',
		'oid'         => '',
		'description' => __esc('Min (observed) input line voltage since apcupsd started', 'apcupsd')
	),
	'MINTIMEL' => array(
		'db_column'   => 'ups_mintimel',
		'oid'         => '',
		'description' => __esc('Min battery runtime (MINUTES) required for system shutdown', 'apcupsd')
	),
	'MODEL' => array(
		'db_column'   => 'ups_model',
		'oid'         => '',
		'description' => __esc('UPS model derived from UPS information', 'apcupsd')
	),
	'NOMBATTV' => array(
		'db_column'   => 'ups_nominal_batt_voltage',
		'oid'         => '',
		'description' => __esc('Nominal battery voltage', 'apcupsd')
	),
	'NOMINV' => array(
		'db_column'   => 'ups_nominal_voltage',
		'oid'         => '',
		'description' => __esc('Nominal input voltage delivered to the UPS.', 'apcupsd')
	),
	'NOMOUTV' => array(
		'db_column'   => 'ups_nominal_output_voltage',
		'oid'         => '',
		'description' => __esc('Nominal output voltage to supply when on battery power', 'apcupsd')
	),
	'NOMPOWER' => array(
		'db_column'   => 'ups_nominal_power',
		'oid'         => '',
		'description' => __esc('Nominal power output in watts', 'apcupsd')
	),
	'NUMXFERS' => array(
		'db_column'   => 'ups_numxfers',
		'oid'         => '',
		'description' => __esc('Number of transfers to battery since apcupsd startup', 'apcupsd')
	),
	'OUTPUTV' => array(
		'db_column'   => 'ups_output_voltage',
		'oid'         => '',
		'description' => __esc('Current UPS output voltage', 'apcupsd')
	),
	'REG1' => array(
		'db_column'   => 'ups_reg1',
		'oid'         => '',
		'description' => __esc('Fault register 1 in hex', 'apcupsd')
	),
	'REG2' => array(
		'db_column'   => 'ups_reg2',
		'oid'         => '',
		'description' => __esc('Fault register 2 in hex', 'apcupsd')
	),
	'REG3' => array(
		'db_column'   => 'ups_reg3',
		'oid'         => '',
		'description' => __esc('Fault register 3 in hex', 'apcupsd')
	),
	'RETPCT' => array(
		'db_column'   => 'ups_battery_retpct',
		'oid'         => '',
		'description' => __esc('Battery charge %%% required after power off to restore power', 'apcupsd')
	),
	'SELFTEST' => array(
		'db_column'   => 'ups_selftest',
		'oid'         => '',
		'description' => __esc('Date and time of last self test since apcupsd startup', 'apcupsd')
	),
	'SENSE' => array(
		'db_column'   => 'ups_sense',
		'oid'         => '1.3.6.1.4.1.318.1.1.1.5.2.7.0',
		'description' => __esc('Current UPS sensitivity setting for voltage fluctuations', 'apcupsd')
	),
	'SERIALNO' => array(
		'db_column'   => 'ups_serialno',
		'oid'         => '1.3.6.1.4.1.318.1.1.1.1.2.3.0',
		'description' => __esc('UPS serial number', 'apcupsd')
	),
	'STARTTIME' => array(
		'db_column'   => 'ups_starttime',
		'oid'         => '',
		'description' => __esc('Date and time apcupsd was started', 'apcupsd')
	),
	'STATFLAG' => array(
		'db_column'   => 'ups_statflag',
		'oid'         => '',
		'description' => __esc('UPS status flag in hex', 'apcupsd')
	),
	'STATUS' => array(
		'db_column'   => 'ups_status',
		'oid'         => '',
		'description' => __esc('UPS status.  One or more of the following (space-separated): CAL TRIM BOOST ONLINE ONBATT OVERLOAD LOWBATT REPLACEBATT NOBATT SLAVE SLAVEDOWN or COMMLOST or SHUTTING DOWN', 'apcupsd')
	),
	'STESTI' => array(
		'db_column'   => 'ups_selftest_interval',
		'oid'         => '',
		'description' => __esc('Self-test interval', 'apcupsd')
	),
	'TIMELEFT' => array(
		'db_column'   => 'ups_timeleft',
		'oid'         => '1.3.6.1.4.1.318.1.1.1.2.2.3.0',
		'description' => __esc('Remaining runtime left on battery as estimated by the UPS', 'apcupsd')
	),
	'TONBATT' => array(
		'db_column'   => 'ups_tonbatt',
		'oid'         => '',
		'description' => __esc('Seconds currently on battery', 'apcupsd')
	),
	'UPSMODE' => array(
		'db_column'   => 'ups_mode',
		'oid'         => '',
		'description' => __esc('Mode in which UPS is operating', 'apcupsd')
	),
	'UPSNAME' => array(
		'db_column'   => 'ups_name',
		'oid'         => '',
		'description' => __esc('UPS name from configuration file (dumb) or EEPROM (smart)', 'apcupsd')
	),
	'VERSION' => array(
		'db_column'   => 'ups_version',
		'oid'         => '',
		'description' => __esc('apcupsd version number, date and operating system', 'apcupsd')
	),
	'XOFFBAT' => array(
		'db_column'   => 'ups_xoffbatt',
		'oid'         => '',
		'description' => __esc('Date, time of last transfer off battery since apcupsd startup', 'apcupsd')
	),
	'XOFFBATT' => array(
		'db_column'   => 'ups_xoffbatt',
		'oid'         => '',
		'description' => __esc('Date, time of last transfer off battery since apcupsd startup', 'apcupsd')
	),
	'XONBATT' => array(
		'db_column'   => 'ups_xonbatt',
		'oid'         => '',
		'description' => __esc('Date, time of last transfer to battery since apcupsd startup', 'apcupsd')
	)
);


