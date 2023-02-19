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

include('../../include/auth.php');

$ups_actions = array(
	1 => __('Delete', 'apcupsd'),
	2 => __('Duplicate', 'apcupsd'),
	3 => __('Reset Detection', 'apcupsd')
);

$ups_types = array(
	1 => __('APC UPSD Based', 'apcupsd'),
	2 => __('SNMP Based', 'apcupsd')
);

global $fields_snmp_item;

/* file: upses.php, action: edit */
$fields_ups_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => __('UPS Information', 'apcupsd'),
		'collapsible' => 'true'
	),
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('UPS Name', 'apcupsd'),
		'description' => __('The Name you would like to give this UPS.  For APCUPSD Devices, a corresponding Cacti Device will be created automatically for the UPS using this name.  For SNMP based UPS\'s, you must create the Cacti Device first.', 'apcupsd'),
		'value' => '|arg1:name|',
		'size' => '50',
		'default' => __('New UPS', 'apcupsd'),
		'max_length' => '100'
	),
	'description' => array(
		'method' => 'textarea',
		'friendly_name' => __('UPS Description', 'apcupsd'),
		'description' => __('A more detailed Description of this UPS if required.', 'apcupsd'),
		'value' => '|arg1:description|',
		'textarea_rows' => '3',
		'textarea_cols' => '80'
	),
	'type_id' => array(
		'method' => 'drop_array',
		'friendly_name' => __('UPS Type', 'apcupsd'),
		'description' => __('The Type of UPS you are monitoring.', 'apcupsd'),
		'value' => '|arg1:type_id|',
		'array' => $ups_types
	),
	'poller_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Poller ID', 'apcupsd'),
		'description' => __('The Poller that this UPS exists in is monitored by', 'apcupsd'),
		'value' => '|arg1:poller_id|',
		'sql' => 'SELECT id, name FROM poller ORDER BY name',
		'default' => 1
	),
	'site_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Site Name', 'apcupsd'),
		'description' => __('The Site that this UPS exists in.', 'apcupsd'),
		'value' => '|arg1:site_id|',
		'sql' => 'SELECT id, name FROM sites ORDER BY name',
		'none_value' => __('None', 'apcupsd')
	),
	'host_id' => array(
		'method' => 'drop_callback',
		'friendly_name' => __('Cacti Device', 'apcupsd'),
		'description' => __('For SNMP Based UPS\', select the Cacti Device to use for SNMP credentials.  Otherwise, select None, and the UPS plugin will create the Device for you automatically.', 'apcupsd'),
		'none_value' => __('None'),
		'sql' => 'SELECT id, description AS name FROM host ORDER BY name',
		'action' => 'ajax_hosts_noany',
		'id' => '|arg1:host_id|',
		'value' => __('Autocreate on First Poll', 'apcupsd'),
		'none_value' => __('Autocreate on First Poll', 'apcupsd')
	),
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enabled', 'apcupsd'),
		'description' => __('Check to immediatly start polling for data.', 'apcupsd'),
		'value' => '|arg1:enabled|',
		'default' => 'on'
	),
	'spacer1' => array(
		'method' => 'spacer',
		'friendly_name' => __('APC UPSD Information', 'apcupsd'),
		'collapsible' => 'true'
	),
	'hostname' => array(
		'method' => 'textbox',
		'friendly_name' => __('Hostname', 'apcupsd'),
		'description' => __('The hostname of the host running apcupsd.', 'apcupsd'),
		'value' => '|arg1:hostname|',
		'size' => '70',
		'max_length' => '100'
	),
	'port' => array(
		'method' => 'textbox',
		'friendly_name' => __('TCP Port', 'apcupsd'),
		'description' => __('Enter the TCP Port for this UPS.', 'apcupsd'),
		'value' => '|arg1:port|',
		'size' => '10',
		'max_length' => '10'
	),
	'host_snmp_head' => array(
		'method' => 'spacer',
		'friendly_name' => __('SNMP Options'),
	),
	'snmp_hostname' => array(
		'method' => 'textbox',
		'friendly_name' => __('Hostname', 'apcupsd'),
		'description' => __('The hostname where the snmp agent is located.', 'apcupsd'),
		'value' => '|arg1:hostname|',
		'size' => '70',
		'max_length' => '100'
	),
	) + $fields_snmp_item + array(
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	),
	'save_component_ups' => array(
		'method' => 'hidden',
		'value' => '1'
	)
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
    case 'ajax_hosts':
        $sql_where = '';
        if (get_request_var('site_id') > 0) {
            $sql_where = 'site_id = ' . get_request_var('site_id');
        }

        get_allowed_ajax_hosts(false, false, $sql_where);

        break;
    case 'ajax_hosts_noany':
        $sql_where = '';
        if (get_request_var('site_id') > 0) {
            $sql_where = 'site_id = ' . get_request_var('site_id');
        }

        get_allowed_ajax_hosts(false, true, $sql_where);

        break;
	case 'ajax_tz':
		print json_encode(db_fetch_assoc_prepared('SELECT Name AS label, Name AS `value`
			FROM mysql.time_zone_name
			WHERE Name LIKE ?
			ORDER BY Name
			LIMIT ' . read_config_option('autocomplete_rows'),
			array('%' . get_nfilter_request_var('term') . '%')));

		break;
	case 'edit':
		top_header();

		ups_edit();

		bottom_footer();
		break;
	default:
		top_header();

		upses();

		bottom_footer();
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset_request_var('save_component_ups')) {
		$save['id']           = get_filter_request_var('id');
		$save['host_id']      = form_input_validate(get_nfilter_request_var('host_id'), 'host_id', '', true, 3);
		$save['type_id']      = form_input_validate(get_nfilter_request_var('type_id'), 'type_id', '', true, 3);
		$save['name']         = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['description']  = form_input_validate(get_nfilter_request_var('description'), 'description', '', true, 3);
		$save['site_id']      = form_input_validate(get_nfilter_request_var('site_id'), 'site_id', '', true, 3);

		if ($save['type_id'] == 1) {
			$save['hostname'] = form_input_validate(get_nfilter_request_var('hostname'), 'hostname', '', true, 3);
		} else {
			$save['hostname'] = form_input_validate(get_nfilter_request_var('snmp_hostname'), 'snmp_hostname', '', true, 3);
		}

		$save['port']         = form_input_validate(get_nfilter_request_var('port'), 'port', '', true, 3);
		$save['enabled']      = isset_request_var('enabled') ? 'on':'';

		$save['snmp_version']   = form_input_validate(get_nfilter_request_var('snmp_version'), 'snmp_version', '', true, 3);
		$save['snmp_community'] = form_input_validate(get_nfilter_request_var('snmp_community'), 'snmp_community', '', true, 3);

		$save['snmp_username']        = form_input_validate(get_nfilter_request_var('snmp_username'), 'snmp_username', '', true, 3);
		$save['snmp_password']        = form_input_validate(get_nfilter_request_var('snmp_password'), 'snmp_password', '', true, 3);
		$save['snmp_auth_protocol']   = form_input_validate(get_nfilter_request_var('snmp_auth_protocol'), 'snmp_auth_protocol', '', true, 3);
		$save['snmp_priv_protocol']   = form_input_validate(get_nfilter_request_var('snmp_priv_protocol'), 'snmp_priv_protocol', '', true, 3);
		$save['snmp_priv_passphrase'] = form_input_validate(get_nfilter_request_var('snmp_priv_passphrase'), 'snmp_priv_passphrase', '', true, 3);
		$save['snmp_context']         = form_input_validate(get_nfilter_request_var('snmp_context'), 'snmp_context', '', true, 3);
		$save['snmp_engine_id']       = form_input_validate(get_nfilter_request_var('snmp_engine_id'), 'snmp_engine_id', '', true, 3);

		$save['snmp_port']            = form_input_validate(get_nfilter_request_var('snmp_port'), 'snmp_port', '', true, 3);
		$save['snmp_timeout']         = form_input_validate(get_nfilter_request_var('snmp_timeout'), 'snmp_timeout', '', true, 3);

		if (!is_error_message()) {
			$ups_id = sql_save($save, 'apcupsd_ups');

			if ($ups_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: upses.php?header=false&action=edit&id=' . (empty($ups_id) ? get_nfilter_request_var('id') : $ups_id));
	}
}

function duplicate_ups($template_id, $name) {
	if (!is_array($template_id)) {
		$template_id = array($template_id);
	}

	foreach($template_id as $id) {
		$ups = db_fetch_row_prepared('SELECT *
			FROM apcupsd_ups
			WHERE id = ?',
			array($id));

		if (cacti_sizeof($ups)) {
			$save = array();

			$save['id'] = 0;

			foreach($ups as $column => $value) {
				if ($column == 'id') {
					continue;
				} elseif ($column == 'name') {
					$save['name'] = str_replace('<ups>', $value, $name);
				} else {
					$save[$column] = $value;
				}
			}

			$ups_id = sql_save($save, 'apcupsd_ups');

			if ($ups_id > 0) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		} else {
			raise_message('ups_error', __('Template UPS was not found! Unable to duplicate.'), MESSAGE_LEVEL_ERROR);
		}
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $ups_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				db_execute('DELETE FROM apcupsd_ups WHERE ' . array_to_sql_or($selected_items, 'id'));
			} elseif (get_nfilter_request_var('drp_action') == '2') { /* Duplicate */
				duplicate_ups($selected_items, get_nfilter_request_var('ups_name'));
			} elseif (get_nfilter_request_var('drp_action') == '3') { /* Reset Detection */
				db_execute('UPDATE apcupsd_ups SET snmp_skipped = "" WHERE ' . array_to_sql_or($selected_items, 'id'));
			}
		}

		header('Location: upses.php?header=false');
		exit;
	}

	/* setup some variables */
	$ups_list = ''; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$ups_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM apcupsd_ups WHERE id = ?', array($matches[1]))) . '</li>';
			$ups_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('upses.php');

	html_start_box($ups_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($ups_array) && cacti_sizeof($ups_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to Delete the following UPS.  Note, all Devices will be disassociated from this UPS.', 'Click \'Continue\' to Delete all following UPSes.  Note, all devices will be disassociated from this UPS.', cacti_sizeof($ups_array)) . "</p>
					<div class='itemlist'><ul>$ups_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Delete UPS', 'Delete UPSes', cacti_sizeof($ups_array)) . "'>";
		} elseif (get_nfilter_request_var('drp_action') == '2') { /* duplicate */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to Duplicate the following UPS.', 'Click \'Continue\' to Duplicate all following UPSes.', cacti_sizeof($ups_array)) . "</p>
					<div class='itemlist'><ul>$ups_list</ul></div>
					<p><strong>" . __('UPS Name:'). "</strong><br>"; form_text_box('ups_name', '<ups> (1)', '', '255', '30', 'text'); print "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Duplicate UPS', 'Duplicate UPSes', cacti_sizeof($ups_array)) . "'>";
		} elseif (get_nfilter_request_var('drp_action') == '3') { /* reset */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to Reset Discovery the following UPS.  Note, this only applies for SNMPD type UPSes.', 'Click \'Continue\' to Reset Discovery for the following UPSes.  Note, this only applies for SNMPD type UPSes.', cacti_sizeof($ups_array)) . "</p>
					<div class='itemlist'><ul>$ups_list</ul></div>
				</td>
			</tr>\n";


			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Reset UPS', 'Reset UPSes', cacti_sizeof($ups_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: upses.php?header=false');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($ups_array) ? serialize($ups_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

function ups_edit() {
	global $fields_ups_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$ups = db_fetch_row_prepared('SELECT * FROM apcupsd_ups WHERE id = ?', array(get_request_var('id')));
		$header_label = __esc('UPS [edit: %s]', $ups['name']);
	} else {
		$ups = array();
		$header_label = __('UPS [new]');
	}

//	if (isset($ups['host_id']) && $ups['host_id'] > 0) {
//		$fields_ups_edit['host_id']['value'] = db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($ups['host_id']));
//	}

	form_start('upses.php', 'ups');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_ups_edit, (isset($ups) ? $ups : array()))
		)
	);

	html_end_box(true, true);

	form_save_button('upses.php', 'return');

	?>
	<script type='text/javascript'>
	var showHost = false;

	// default snmp information
	var snmp_community       = $('#snmp_community').val();
	var snmp_username        = $('#snmp_username').val();
	var snmp_password        = $('#snmp_password').val();
	var snmp_auth_protocol   = $('#snmp_auth_protocol').val();
	var snmp_priv_passphrase = $('#snmp_priv_passphrase').val();
	var snmp_priv_protocol   = $('#snmp_priv_protocol').val();
	var snmp_context         = $('#snmp_context').val();
	var snmp_engine_id       = $('#snmp_engine_id').val();
	var snmp_port            = $('#snmp_port').val();
	var snmp_timeout         = $('#snmp_timeout').val();

	function changeType() {
		if ($('#type_id').val() == 1) {
			$('#row_spacer1').show();
			$('#row_hostname').show();
			$('#row_port').show();
			$('[id^="row_snmp"]').hide();
		} else if ($('#type_id').val() == 2) {
			$('#row_host_id').show();
			$('#row_spacer1').hide();
			$('#row_hostname').hide();
			$('#row_port').hide();
			$('[id^="row_snmp"]').show();
			setSNMP();
		} else {
			$('#row_spacer1').show();
			$('#row_hostname').show();
			$('#row_port').show();
		}
	}

	$(function() {
		$('#type_id').change(function() {
			changeType();
		});

		$('#snmp_version').change(function() {
			setSNMP();
		});

		setSNMP();
		changeType();
	});
	</script>
	<?php
}

function upses() {
	global $ups_actions, $item_rows, $config;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_ups');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box( __('UPSes'), '100%', '', '3', 'center', 'upses.php?action=edit');

	?>
	<tr class='even'>
		<td>
			<form id='form_ups' action='upses.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('UPSes');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'upses.php?header=false';
				strURL += '&filter='+$('#filter').val();
				strURL += '&rows='+$('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'upses.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#form_ups').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});

			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE name LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
	} else {
		$sql_where = '';
	}

	if (get_request_var('site_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' site_id = ' . get_request_var('site_id');
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM apcupsd_ups $sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$ups_list = db_fetch_assoc("SELECT *
		FROM apcupsd_ups AS ups
		LEFT JOIN apcupsd_ups_stats AS stats
		ON ups.id = stats.ups_id
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('upses.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('UPSes', 'apcupsd'), 'page', 'main');

	form_start('upses.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name' => array(
			'display' => __('UPS Name'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The Name of this UPS.')
		),
		'id' => array(
			'display' => __('ID'),
			'align'   => 'center',
			'sort'    => 'ASC',
			'tip'     => __('The unique id associated with this UPS.')
		),
		'status' => array(
			'display' => __('Status'),
			'align'   => 'center',
			'sort'    => 'ASC',
			'tip'     => __('The Status of the apcupsd daemon on the target Host.')
		),
		'type_id' => array(
			'display' => __('Collector'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Type of Collector.  Currently APCUPSD and SNMP are supported.')
		),
		'ups_status' => array(
			'display' => __('UPS Status'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Status of the monitored UPS on the target Host.')
		),
		'ups_model' => array(
			'display' => __('UPS Model'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Model of the monitored UPS on the target Host.')
		),
		'ups_line_voltage' => array(
			'display' => __('Line Voltage'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The Line Voltage of the monitored UPS on the target Host.')
		),
		'ups_load_percent' => array(
			'display' => __('Load Percent'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The Load Percent of the monitored UPS on the target Host.')
		),
		'ups_timeleft' => array(
			'display' => __('Time Left'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The minutes of available UPS charge of the monitored UPS on the target Host.')
		),
		'nosort' => array(
			'display' => __('Hostname:Port'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The Hostname:Port that is running the apcupsd daemon.')
		),
		'enabled' => array(
			'display' => __('Enabled'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('If this UPS is being monitored or not.')
		),
		'last_updated' => array(
			'display' => __('Last Updated'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The last time this UPS was samples or found up.')
		)
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (cacti_sizeof($ups_list)) {
		foreach ($ups_list as $ups) {
			form_alternate_row('line' . $ups['id'], true);

			form_selectable_cell(filter_value($ups['name'], get_request_var('filter'), 'upses.php?action=edit&id=' . $ups['id']), $ups['id']);
			form_selectable_cell($ups['id'], $ups['id'], '', 'center');
			form_selectable_cell(get_colored_device_status(($ups['enabled'] == '' ? true : false), $ups['status']), $ups['id'], '', 'center');
			form_selectable_ecell($ups['type_id'] == 1 ? 'APCUPSD':'SNMPD', $ups['id'], '', 'left');
			form_selectable_ecell($ups['ups_status'], $ups['id'], '', 'left');
			form_selectable_ecell($ups['ups_model'], $ups['id'], '', 'left');
			form_selectable_ecell(checkNullandReturn($ups['ups_line_voltage']), $ups['id'], '', 'right');
			form_selectable_ecell(checkNullandReturn($ups['ups_load_percent']), $ups['id'], '', 'right');
			form_selectable_ecell($ups['ups_timeleft'], $ups['id'], '', 'right');

			if ($ups['type_id'] == 1) {
				form_selectable_ecell($ups['hostname'] . ':' . $ups['port'], $ups['id'], '', 'right');
			} else {
				form_selectable_ecell($ups['hostname'] . ':' . $ups['snmp_port'], $ups['id'], '', 'right');
			}

			form_selectable_ecell($ups['enabled'] == 'on' ? __('Yes'):__('No'), $ups['id'], '', 'right');
			form_selectable_ecell($ups['last_updated'], $ups['id'], '', 'right');

			form_checkbox_cell($ups['name'], $ups['id']);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><em>" . __('No UPSes Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($ups_list)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($ups_actions);

	form_end();
}

function checkNullandReturn($value) {
	if ($value === null) {
		return __('Not Avail', 'apcupsd');
	} else {
		return $value;
	}
}
