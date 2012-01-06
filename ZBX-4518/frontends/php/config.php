<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once('include/config.inc.php');
require_once('include/images.inc.php');
require_once('include/regexp.inc.php');
require_once('include/forms.inc.php');


$page['title'] = _('Configuration of Zabbix');
$page['file'] = 'config.php';
$page['hist_arg'] = array('config');

require_once('include/page_header.php');
?>
<?php
	$fields=array(
		// VAR					        TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
		'config'=>					array(T_ZBX_INT, O_OPT,	null,	IN('0,3,5,6,7,8,9,10,11,12,13,14'),	null),
		// other form
		'alert_history'=>			array(T_ZBX_INT, O_NO,	null,	BETWEEN(0,65535),	'isset({config})&&({config}==0)&&isset({save})'),
		'event_history'=>			array(T_ZBX_INT, O_NO,	null,	BETWEEN(0,65535),	'isset({config})&&({config}==0)&&isset({save})'),
		'work_period'=>				array(T_ZBX_STR, O_NO,	null,	null,				'isset({config})&&({config}==7)&&isset({save})'),
		'refresh_unsupported'=>		array(T_ZBX_INT, O_NO,	null,	BETWEEN(0,65535),	'isset({config})&&({config}==5)&&isset({save})'),
		'alert_usrgrpid'=>			array(T_ZBX_INT, O_NO,	null,	DB_ID,				'isset({config})&&({config}==5)&&isset({save})'),
		'discovery_groupid'=>		array(T_ZBX_INT, O_NO,	null,	DB_ID,				'isset({config})&&({config}==5)&&isset({save})'),
		'snmptrap_logging'=>		array(T_ZBX_INT, O_OPT,	null,	IN('1'),			null),


		// image form
		'imageid'=>					array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,			'isset({config})&&({config}==3)&&(isset({form})&&({form}=="update"))'),
		'name'=>					array(T_ZBX_STR, O_NO,	null,	NOT_EMPTY,		'isset({config})&&({config}==3)&&isset({save})'),
		'imagetype'=>				array(T_ZBX_INT, O_OPT,	null,	IN('1,2'),		'isset({config})&&({config}==3)&&(isset({save}))'),

		// value mapping
		'valuemapid'=>				array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,			'isset({config})&&({config}==6)&&(isset({form})&&({form}=="update"))'),
		'mapname'=>					array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY, 		'isset({config})&&({config}==6)&&isset({save})'),
		'valuemap'=>				array(T_ZBX_STR, O_OPT,	null,	null,	null),
		'rem_value'=>				array(T_ZBX_INT, O_OPT,	null,	BETWEEN(0,65535), null),
		'add_value'=>				array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY, 'isset({add_map})'),
		'add_newvalue'=>			array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY, 'isset({add_map})'),

		// actions
		'add_map'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_map'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'save'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'go'=>						array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		// GUI
		'event_ack_enable'=>		array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	IN('1'),	null),
		'event_expire'=> 			array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	BETWEEN(1,99999),	'isset({config})&&({config}==8)&&isset({save})'),
		'event_show_max'=> 			array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	BETWEEN(1,99999),	'isset({config})&&({config}==8)&&isset({save})'),
		'dropdown_first_entry'=>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	IN('0,1,2'),		'isset({config})&&({config}==8)&&isset({save})'),
		'dropdown_first_remember'=>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	IN('1'),	null),
		'max_in_table' => 			array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	BETWEEN(1,99999),	'isset({config})&&({config}==8)&&isset({save})'),
		'search_limit' => 			array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	BETWEEN(1,999999),	'isset({config})&&({config}==8)&&isset({save})'),

		// Macros
		'macros_rem'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'macros'=>					array(T_ZBX_STR, O_OPT, P_SYS,			null,	null),
		'macro_new'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	'isset({macro_add})'),
		'value_new'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	'isset({macro_add})'),
		'macro_add' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'macros_del' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		// Themes
		'default_theme'=>			array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,			'isset({config})&&({config}==9)&&isset({save})'),

		// regexp
		'regexpids'=>				array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),
		'regexpid'=>				array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		'isset({config})&&({config}==10)&&(isset({form})&&({form}=="update"))'),
		'rename'=>					array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'isset({config})&&({config}==10)&&isset({save})', S_NAME),
		'test_string'=>				array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'isset({config})&&({config}==10)&&isset({save})', S_TEST_STRING),
		'delete_regexp'=>			array(T_ZBX_STR, O_OPT,	null,	null,		null),

		'g_expressionid'=>			array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'expressions'=>				array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==10)&&isset({save})'),
		'new_expression'=>			array(T_ZBX_STR, O_OPT,	null,	null,		null),
		'cancel_new_expression'=>	array(T_ZBX_STR, O_OPT,	null,	null,		null),

		'clone'=>					array(T_ZBX_STR, O_OPT,	null,	null,		null),
		'add_expression'=>			array(T_ZBX_STR, O_OPT,	null,	null,		null),
		'edit_expressionid'=>		array(T_ZBX_STR, O_OPT,	null,	null,		null),
		'delete_expression'=>		array(T_ZBX_STR, O_OPT,	null,	null,		null),

		// Trigger severities
		'severity_name_0' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==12)&&isset({save})'),
		'severity_color_0' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==12)&&isset({save})'),
		'severity_name_1' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==12)&&isset({save})'),
		'severity_color_1' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==12)&&isset({save})'),
		'severity_name_2' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==12)&&isset({save})'),
		'severity_color_2' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==12)&&isset({save})'),
		'severity_name_3' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==12)&&isset({save})'),
		'severity_color_3' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==12)&&isset({save})'),
		'severity_name_4' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==12)&&isset({save})'),
		'severity_color_4' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==12)&&isset({save})'),
		'severity_name_5' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==12)&&isset({save})'),
		'severity_color_5' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==12)&&isset({save})'),

		// Trigger displaying options
		'problem_unack_color' =>	array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==13)&&isset({save})'),
		'problem_ack_color' =>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==13)&&isset({save})'),
		'ok_unack_color' =>			array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==13)&&isset({save})'),
		'ok_ack_color' =>			array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==13)&&isset({save})'),
		'problem_unack_style' =>	array(T_ZBX_INT, O_OPT,	null,	IN('1'),	 null),
		'problem_ack_style' =>		array(T_ZBX_INT, O_OPT,	null,	IN('1'),	 null),
		'ok_unack_style' =>			array(T_ZBX_INT, O_OPT,	null,	IN('1'),	 null),
		'ok_ack_style' =>			array(T_ZBX_INT, O_OPT,	null,	IN('1'),	 null),
		'ok_period' =>				array(T_ZBX_INT, O_OPT,	null,	null,		'isset({config})&&({config}==13)&&isset({save})'),
		'blink_period' =>			array(T_ZBX_INT, O_OPT,	null,	null,		'isset({config})&&({config}==13)&&isset({save})'),

		// Icon Maps
		'iconmapid' => 				array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		'isset({config})&&({config}==14)&&(((isset({form})&&({form}=="update")))||isset({delete}))'),
		'iconmap' =>				array(T_ZBX_STR, O_OPT,	null,	null,		'isset({config})&&({config}==14)&&isset({save})'),

		'form' =>					array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh' =>			array(T_ZBX_INT, O_OPT,	null,	null,	null)
	);
?>
<?php
	$_REQUEST['config'] = get_request('config', CProfile::get('web.config.config', 0));

	check_fields($fields);

	CProfile::update('web.config.config' ,$_REQUEST['config'], PROFILE_TYPE_INT);

	$orig_config = select_config(false, get_current_nodeid(false));

	$result = 0;

	// Images
	if ($_REQUEST['config'] == 3) {
		if (isset($_REQUEST['save'])) {

			if (isset($_REQUEST['imageid'])) {
				$msg_ok = _('Image updated');
				$msg_fail = _('Cannot update image');
			}
			else {
				$msg_ok = _('Image added');
				$msg_fail = _('Cannot add image');
			}

			try {
				DBstart();
				$file = isset($_FILES['image']) && $_FILES['image']['name'] != '' ? $_FILES['image'] : null;
				if (!is_null($file)) {
					if ($file['error'] != 0 || $file['size'] == 0) {
						throw new Exception(_('Incorrect image'));
					}
					if ($file['size'] < ZBX_MAX_IMAGE_SIZE) {
						$image = fread(fopen($file['tmp_name'], 'r'), filesize($file['tmp_name']));
					}
					else {
						throw new Exception(_('Image size must be less than 1MB'));
					}

					$image = base64_encode($image);
				}

				if (isset($_REQUEST['imageid'])) {
					$val = array(
						'imageid' => $_REQUEST['imageid'],
						'name' => $_REQUEST['name'],
						'imagetype' => $_REQUEST['imagetype'],
						'image' => is_null($file) ? null : $image
					);
					$result = API::Image()->update($val);

					$audit_action = 'Image ['.$_REQUEST['name'].'] updated';
				}
				else {
					if (is_null($file)) {
						throw new Exception(_('Select image to download'));
					}
					if (!count(get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
						access_deny();
					}

					$val = array(
						'name' => $_REQUEST['name'],
						'imagetype' => $_REQUEST['imagetype'],
						'image' => $image
					);
					$result = API::Image()->create($val);

					$audit_action = 'Image ['.$_REQUEST['name'].'] added';
				}

				if ($result) {
					add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_IMAGE, $audit_action);
					unset($_REQUEST['form']);
				}

				DBend($result);
				show_messages($result, $msg_ok, $msg_fail);
			}
			catch (Exception $e) {
				DBend(false);
				error($e->getMessage());
				show_error_message($msg_fail);
			}
		}
		elseif (isset($_REQUEST['delete']) && isset($_REQUEST['imageid'])) {
			$image = get_image_by_imageid($_REQUEST['imageid']);

			$result = API::Image()->delete($_REQUEST['imageid']);

			show_messages($result, _('Image deleted'), _('Cannot delete image'));
			if ($result) {
				add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_IMAGE, 'Image ['.$image['name'].'] deleted');
				unset($_REQUEST['form']);
				unset($image, $_REQUEST['imageid']);
			}
		}
	}
	// GUI
	elseif (isset($_REQUEST['save']) && ($_REQUEST['config'] == 8)) {
		if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
			access_deny();

		$configs = array(
			'default_theme' => get_request('default_theme'),
			'event_ack_enable' => (is_null(get_request('event_ack_enable')) ? 0 : 1),
			'event_expire' => get_request('event_expire'),
			'event_show_max' => get_request('event_show_max'),
			'dropdown_first_entry' => get_request('dropdown_first_entry'),
			'dropdown_first_remember' => (is_null(get_request('dropdown_first_remember')) ? 0 : 1),
			'max_in_table' => get_request('max_in_table'),
			'search_limit' => get_request('search_limit'),
		);

		$result = update_config($configs);

		show_messages($result, S_CONFIGURATION_UPDATED, S_CONFIGURATION_WAS_NOT_UPDATED);

		if($result){
			$msg = array();
			if(!is_null($val = get_request('default_theme')))
				$msg[] = S_DEFAULT_THEME.' ['.$val.']';
			if(!is_null($val = get_request('event_ack_enable')))
				$msg[] = S_EVENT_ACKNOWLEDGES.' ['.($val?(S_DISABLED):(S_ENABLED)).']';
			if(!is_null($val = get_request('event_expire')))
				$msg[] = _('Show events not older than (in days)').' ['.$val.']';
			if(!is_null($val = get_request('event_show_max')))
				$msg[] = S_SHOW_EVENTS_MAX.' ['.$val.']';
			if(!is_null($val = get_request('dropdown_first_entry')))
				$msg[] = S_DROPDOWN_FIRST_ENTRY.' ['.$val.']';
			if(!is_null($val = get_request('dropdown_first_remember')))
				$msg[] = S_DROPDOWN_REMEMBER_SELECTED.' ['.$val.']';
			if(!is_null($val = get_request('max_in_table')))
				$msg[] = S_MAX_IN_TABLE.' ['.$val.']';

			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,implode('; ',$msg));
		}
	}
	else if(isset($_REQUEST['save'])&&uint_in_array($_REQUEST['config'],array(0,5,7))){

		if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
			access_deny();

/* OTHER ACTIONS */
		$configs = array(
				'event_history' => get_request('event_history'),
				'alert_history' => get_request('alert_history'),
				'refresh_unsupported' => get_request('refresh_unsupported'),
				'work_period' => get_request('work_period'),
				'alert_usrgrpid' => get_request('alert_usrgrpid'),
				'discovery_groupid' => get_request('discovery_groupid'),
				'snmptrap_logging' => (get_request('snmptrap_logging') ? 1 : 0),
			);
		$result=update_config($configs);

		show_messages($result, S_CONFIGURATION_UPDATED, S_CONFIGURATION_WAS_NOT_UPDATED);
		if($result){
			$msg = array();
			if(!is_null($val = get_request('event_history')))
				$msg[] = _('Do not keep events older than (in days)').' ['.$val.']';
			if(!is_null($val = get_request('alert_history')))
				$msg[] = _('Do not keep actions older than (in days)').' ['.$val.']';
			if(!is_null($val = get_request('refresh_unsupported')))
				$msg[] = _('Refresh unsupported items (in sec)').' ['.$val.']';
			if(!is_null($val = get_request('work_period')))
				$msg[] = _('Working time').' ['.$val.']';
			if(!is_null($val = get_request('discovery_groupid'))){
				$val = API::HostGroup()->get(array(
					'groupids' => $val,
					'editable' => 1,
					'output' => API_OUTPUT_EXTEND
				));

				if(!empty($val)){
					$val = array_pop($val);
					$msg[] = _('Group for discovered hosts').' ['.$val['name'].']';

					if(bccomp($val['groupid'],$orig_config['discovery_groupid']) !=0 ){
						setHostGroupInternal($orig_config['discovery_groupid'], ZBX_NOT_INTERNAL_GROUP);
						setHostGroupInternal($val['groupid'], ZBX_INTERNAL_GROUP);
					}
				}
			}
			if(!is_null($val = get_request('alert_usrgrpid'))){
				if(0 == $val) {
					$val = S_NONE;
				}
				else{
					$val = DBfetch(DBselect('SELECT name FROM usrgrp WHERE usrgrpid='.$val));
					$val = $val['name'];
				}

				$msg[] = _('User group for database down message').' ['.$val.']';
			}

			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,implode('; ',$msg));
		}
	}
// VALUE MAPS
	elseif ($_REQUEST['config'] == 6) {
		$_REQUEST['valuemap'] = get_request('valuemap', array());
		if (isset($_REQUEST['add_map'])) {
			if (!zbx_is_int($_REQUEST['add_value'])) {
				info(_('Value maps are used to create a mapping between numeric values and string representations.'));
				show_messages(false, null, _('Cannot add value map'));
			}
			else {
				$added = false;
				foreach ($_REQUEST['valuemap'] as $num => $valueMap) {
					if ($valueMap['value'] == $_REQUEST['add_value']) {
						$_REQUEST['valuemap'][$num]['newvalue'] = $_REQUEST['add_newvalue'];
						$added = true;
						break;
					}
				}

				if (!$added) {
					$_REQUEST['valuemap'][] = array(
						'value' => $_REQUEST['add_value'],
						'newvalue' => $_REQUEST['add_newvalue']
					);
				}

				unset($_REQUEST['add_value'], $_REQUEST['add_newvalue']);
			}
		}
		elseif (isset($_REQUEST['del_map']) && isset($_REQUEST['rem_value'])) {
			$_REQUEST['valuemap'] = get_request('valuemap', array());
			foreach ($_REQUEST['rem_value'] as $val) {
				unset($_REQUEST['valuemap'][$val]);
			}
		}
		elseif (isset($_REQUEST['save'])) {
			$mapping = get_request('valuemap', array());
			$prevMap = getValuemapByName($_REQUEST['mapname']);
			if (!$prevMap || (isset($_REQUEST['valuemapid']) && bccomp($_REQUEST['valuemapid'], $prevMap['valuemapid']) == 0) ) {
				if (isset($_REQUEST['valuemapid'])) {
					$result = update_valuemap($_REQUEST['valuemapid'], $_REQUEST['mapname'], $mapping);
					$audit_action = AUDIT_ACTION_UPDATE;
					$msg_ok = _('Value map updated');
					$msg_fail = _('Cannot update value map');
					$valuemapid = $_REQUEST['valuemapid'];
				}
				else {
					if (!count(get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
						access_deny();
					}
					$result = add_valuemap($_REQUEST['mapname'], $mapping);
					$audit_action = AUDIT_ACTION_ADD;
					$msg_ok = _('Value map added');
					$msg_fail = _('Cannot add value map');
					$valuemapid = $result;
				}
			}
			else {
				$msg_ok =  _('Value map added');
				$msg_fail = _s('Cannot add or update value map. Map with name "%s" already exists', $_REQUEST['mapname']);
				$result = 0;
			}
			if ($result) {
				add_audit($audit_action, AUDIT_RESOURCE_VALUE_MAP, _('Value map').' ['.$_REQUEST['mapname'].'] ['.$valuemapid.']');
				unset($_REQUEST['form']);
			}
			show_messages($result, $msg_ok, $msg_fail);
		}
		elseif (isset($_REQUEST['delete']) && isset($_REQUEST['valuemapid'])) {
			$result = false;

			$sql = 'SELECT m.name, m.valuemapid'.
					' FROM valuemaps m WHERE '.DBin_node('m.valuemapid').
						' AND m.valuemapid='.$_REQUEST['valuemapid'];
			if ($map_data = DBfetch(DBselect($sql))) {
				$result = delete_valuemap($_REQUEST['valuemapid']);
			}

			if ($result) {
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_VALUE_MAP, _('Value map').' ['.$map_data['name'].'] ['.$map_data['valuemapid'].']');
				unset($_REQUEST['form']);
			}
			show_messages($result, _('Value map deleted'), _('Cannot delete value map'));
		}
	}
	else if(isset($_REQUEST['save']) && ($_REQUEST['config']==9)){
		if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
			access_deny();

/* OTHER ACTIONS */
		$configs = array(
				'default_theme' => get_request('default_theme')
			);
		$result=update_config($configs);

		show_messages($result, S_CONFIGURATION_UPDATED, S_CONFIGURATION_WAS_NOT_UPDATED);

		if($result){
			$msg = S_DEFAULT_THEME.' ['.get_request('default_theme').']';
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,$msg);
		}
	}
	else if($_REQUEST['config'] == 10){
		if (isset($_REQUEST['clone']) && isset($_REQUEST['regexpid'])) {
			unset($_REQUEST['regexpid']);
			$_REQUEST['form'] = 'clone';
		}
		else if(isset($_REQUEST['cancel_new_expression'])){
			unset($_REQUEST['new_expression']);
		}
		elseif (isset($_REQUEST['save'])) {
			if (!count(get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
				access_deny();
			}

			$regexp = array(
				'name' => $_REQUEST['rename'],
				'test_string' => $_REQUEST['test_string']
			);

			DBstart();
			if (isset($_REQUEST['regexpid'])) {
				$regexpid = $_REQUEST['regexpid'];
				if (!get_regexp_by_regexpid($regexpid)) {
					$result = false;
					error(_('Regular expression does not exist.'));
				}
				else {
					delete_expressions_by_regexpid($_REQUEST['regexpid']);
					$result = update_regexp($regexpid, $regexp);
				}

				$msg1 = S_REGULAR_EXPRESSION_UPDATED;
				$msg2 = S_CANNOT_UPDATE_REGULAR_EXPRESSION;
			}
			else {
				$result = $regexpid = add_regexp($regexp);

				$msg1 = S_REGULAR_EXPRESSION_ADDED;
				$msg2 = S_CANNOT_ADD_REGULAR_EXPRESSION;
			}

			if ($result) {
				$expressions = get_request('expressions', array());
				foreach ($expressions as $id => $expression) {
					$expressionid = add_expression($regexpid,$expression);
				}
			}

			$result = Dbend($result);

			show_messages($result,$msg1,$msg2);

			if ($result) { // result - OK
				add_audit(!isset($_REQUEST['regexpid']) ? AUDIT_ACTION_ADD : AUDIT_ACTION_UPDATE,
					AUDIT_RESOURCE_REGEXP,
					S_NAME.': '.$_REQUEST['rename']);

				unset($_REQUEST['form']);
			}
		}
		elseif (isset($_REQUEST['go'])) {
			if ($_REQUEST['go'] == 'delete') {
				if (!count(get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
					access_deny();
				}

				$regexpids = get_request('regexpid', array());
				if (isset($_REQUEST['regexpids'])) {
					$regexpids = $_REQUEST['regexpids'];
				}

				zbx_value2array($regexpids);

				$regexps = array();
				foreach($regexpids as $id => $regexpid){
					$regexps[$regexpid] = get_regexp_by_regexpid($regexpid);
				}

				DBstart();
				$result = delete_regexp($regexpids);
				$result = Dbend($result);

				show_messages($result, S_REGULAR_EXPRESSION_DELETED, S_CANNOT_DELETE_REGULAR_EXPRESSION);
				if ($result) {
					foreach ($regexps as $regexpid => $regexp) {
						add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_REGEXP, 'Id ['.$regexpid.'] '.S_NAME.' ['.$regexp['name'].']');
					}

					unset($_REQUEST['form']);
					unset($_REQUEST['regexpid']);

					$url = new CUrl();
					$path = $url->getPath();
					insert_js('cookie.eraseArray("'.$path.'")');
				}
			}
		}
		elseif (isset($_REQUEST['add_expression']) && isset($_REQUEST['new_expression'])) {
			$new_expression = $_REQUEST['new_expression'];

			if(!isset($new_expression['case_sensitive']))		$new_expression['case_sensitive'] = 0;

			$result = false;
			if(zbx_empty($new_expression['expression'])) {
				info(S_INCORRECT_EXPRESSION);
			}
			else{
				$result = true;
			}

			if($result){
				if(!isset($new_expression['id'])){
					if(!isset($_REQUEST['expressions'])) $_REQUEST['expressions'] = array();

					if(!str_in_array($new_expression,$_REQUEST['expressions']))
						array_push($_REQUEST['expressions'],$new_expression);
				}
				else{
					$id = $new_expression['id'];
					unset($new_expression['id']);
					$_REQUEST['expressions'][$id] = $new_expression;
				}

				unset($_REQUEST['new_expression']);
			}
		}
		elseif (isset($_REQUEST['delete_expression']) && isset($_REQUEST['g_expressionid'])) {
			$_REQUEST['expressions'] = get_request('expressions',array());
			foreach ($_REQUEST['g_expressionid'] as $val) {
				unset($_REQUEST['expressions'][$val]);
			}
		}
		elseif (isset($_REQUEST['edit_expressionid'])) {
			$_REQUEST['edit_expressionid'] = array_keys($_REQUEST['edit_expressionid']);
			$edit_expressionid = $_REQUEST['edit_expressionid'] = array_pop($_REQUEST['edit_expressionid']);
			$_REQUEST['expressions'] = get_request('expressions',array());

			if(isset($_REQUEST['expressions'][$edit_expressionid])){
				$_REQUEST['new_expression'] = $_REQUEST['expressions'][$edit_expressionid];
				$_REQUEST['new_expression']['id'] = $edit_expressionid;
			}
		}
	}
	// Macros
	elseif ($_REQUEST['config'] == 11) {
		if (isset($_REQUEST['save'])) {
			try {
				DBstart();

				$globalMacros = API::UserMacro()->get(array('globalmacro' => 1, 'output' => API_OUTPUT_EXTEND));
				$globalMacros = zbx_toHash($globalMacros, 'macro');

				$newMacros = get_request('macros', array());

				// remove item from new macros array if name and value is empty
				foreach ($newMacros as $number => $newMacro) {
					if (zbx_empty($newMacro['macro']) && zbx_empty($newMacro['value'])) {
						unset($newMacros[$number]);
					}
				}

				$duplicatedMacros = array();
				foreach ($newMacros as $number => $newMacro) {
					// transform macros to uppercase {$aaa} => {$AAA}
					$newMacros[$number]['macro'] = zbx_strtoupper($newMacro['macro']);

					// search for duplicates items in new macros array
					foreach ($newMacros as $duplicateNumber => $duplicateNewMacro) {
						if ($number != $duplicateNumber && $newMacro['macro'] == $duplicateNewMacro['macro']) {
							$duplicatedMacros[] = '"'.$duplicateNewMacro['macro'].'"';
						}
					}
				}

				// validate duplicates macros
				if (!empty($duplicatedMacros)) {
					throw new Exception(_('More than one macro with same name found:').SPACE.implode(', ', array_unique($duplicatedMacros)));
				}

				// save filtered macro array
				$_REQUEST['macros'] = $newMacros;

				// update
				$macrosToUpdate = array();
				foreach ($newMacros as $number => $newMacro) {
					if (isset($globalMacros[$newMacro['macro']])) {
						$macrosToUpdate[] = $newMacro;

						// remove item from new macros array
						unset($newMacros[$number]);
					}
				}
				if (!empty($macrosToUpdate)) {
					if (!API::UserMacro()->updateGlobal($macrosToUpdate)) {
						throw new Exception(_('Cannot update macro'));
					}
					foreach ($macrosToUpdate as $macro) {
						add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_MACRO, $globalMacros[$macro['macro']]['globalmacroid'], $macro['macro'].SPACE.RARR.SPACE.$macro['value'], null, null, null);
					}
				}

				$newMacroMacros = zbx_objectValues($newMacros, 'macro');
				$newMacroMacros = zbx_toHash($newMacroMacros, 'macro');

				// delete
				$macrosToDelete = array();
				$macrosToUpdate = zbx_toHash($macrosToUpdate, 'macro');
				foreach ($globalMacros as $globalMacro) {
					if (empty($newMacroMacros[$globalMacro['macro']]) && empty($macrosToUpdate[$globalMacro['macro']])) {
						$macrosToDelete[] = $globalMacro['macro'];

						// remove item from new macros array
						foreach ($newMacros as $number => $newMacro) {
							if ($newMacro['macro'] == $globalMacro['macro']) {
								unset($newMacros[$number]);
								break;
							}
						}
					}
				}
				if (!empty($macrosToDelete)) {
					if (!API::UserMacro()->deleteGlobal($macrosToDelete)) {
						throw new Exception(_('Cannot remove macro.'));
					}
					foreach ($macrosToDelete as $macro) {
						add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_MACRO, $globalMacros[$macro]['globalmacroid'], $macro.SPACE.RARR.SPACE.$globalMacros[$macro]['value'], null, null, null);
					}
				}

				// create
				if (!empty($newMacros)) {
					// mark marcos as new
					foreach ($newMacros as $number => $macro) {
						$_REQUEST['macros'][$number]['type'] = 'new';
					}

					$newMacrosIds = API::UserMacro()->createGlobal(array_values($newMacros));
					if (!$newMacrosIds) {
						throw new Exception(_('Cannot add macro'));
					}
					$newMacrosCreated = API::UserMacro()->get(array(
						'globalmacroids' => $newMacrosIds['globalmacroids'],
						'globalmacro' => 1,
						'output' => API_OUTPUT_EXTEND
					));
					foreach ($newMacrosCreated as $macro) {
						add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_MACRO, $macro['globalmacroid'], $macro['macro'].SPACE.RARR.SPACE.$macro['value'], null, null, null);
					}
				}

				DBend(true);
				show_message(_('Macros updated'));
			}
			catch (Exception $e) {
				DBend(false);
				error($e->getMessage());
				show_error_message(_('Cannot update macros'));
			}
		}

	}
	// Trigger severities
	else if(($_REQUEST['config'] == 12) && (isset($_REQUEST['save']))){
		$configs = array(
			'severity_name_0' => get_request('severity_name_0', _('Not classified')),
			'severity_color_0' => get_request('severity_color_0', ''),
			'severity_name_1' => get_request('severity_name_1', _('Information')),
			'severity_color_1' => get_request('severity_color_1', ''),
			'severity_name_2' => get_request('severity_name_2', _('Warning')),
			'severity_color_2' => get_request('severity_color_2', ''),
			'severity_name_3' => get_request('severity_name_3', _('Average')),
			'severity_color_3' => get_request('severity_color_3', ''),
			'severity_name_4' => get_request('severity_name_4', _('High')),
			'severity_color_4' => get_request('severity_color_4', ''),
			'severity_name_5' => get_request('severity_name_5', _('Disaster')),
			'severity_color_5' => get_request('severity_color_5', ''),
		);

		$result = update_config($configs);

		show_messages($result, S_CONFIGURATION_UPDATED, S_CONFIGURATION_WAS_NOT_UPDATED);
	}
	// Trigger displaying options
	else if(($_REQUEST['config'] == 13) && (isset($_REQUEST['save']))){
		$configs = array(
			'ok_period' => get_request('ok_period'),
			'blink_period' => get_request('blink_period'),
			'problem_unack_color' => get_request('problem_unack_color'),
			'problem_ack_color' => get_request('problem_ack_color'),
			'ok_unack_color' => get_request('ok_unack_color'),
			'ok_ack_color' => get_request('ok_ack_color'),
			'problem_unack_style' => get_request('problem_unack_style', 0),
			'problem_ack_style' => get_request('problem_ack_style', 0),
			'ok_unack_style' => get_request('ok_unack_style', 0),
			'ok_ack_style' => get_request('ok_ack_style', 0)
		);

		$result = update_config($configs);

		show_messages($result, S_CONFIGURATION_UPDATED, S_CONFIGURATION_WAS_NOT_UPDATED);
	}
	// Icon mapping
	elseif ($_REQUEST['config'] == 14) {
		if (isset($_REQUEST['save'])) {

			$_REQUEST['iconmap']['mappings'] = isset($_REQUEST['iconmap']['mappings'])
				? $_REQUEST['iconmap']['mappings']
				: array();

			$i = 0;
			foreach ($_REQUEST['iconmap']['mappings'] as $iconmappingid => &$mapping) {
				$mapping['iconmappingid'] = $iconmappingid;
				$mapping['sortorder'] = $i++;
			}
			unset($mapping);

			if (isset($_REQUEST['iconmapid'])) {
				$_REQUEST['iconmap']['iconmapid'] = $_REQUEST['iconmapid'];
				$result = API::IconMap()->update($_REQUEST['iconmap']);
				$msgOk = _('Icon map updated');
				$msgErr = _('Cannot update icon map');
			}
			else {
				$result = API::IconMap()->create($_REQUEST['iconmap']);
				$msgOk = _('Icon map created');
				$msgErr = _('Cannot create icon map');
			}

			show_messages($result, $msgOk, $msgErr);
			if ($result) {
				unset($_REQUEST['form']);
			}
		}
		elseif (isset($_REQUEST['delete'])) {
			$result = API::IconMap()->delete($_REQUEST['iconmapid']);
			if ($result) {
				unset($_REQUEST['form']);
			}
			show_messages($result, _('Icon map deleted'), _('Cannot delete icon map'));
		}
		elseif (isset($_REQUEST['clone'])) {
			unset($_REQUEST['iconmapid']);
			$_REQUEST['form'] = 'clone';
		}
	}
?>

<?php
	$form = new CForm();
	$form->cleanItems();

	$cmbConfig = new CCombobox('configDropDown', $_REQUEST['config'], 'javascript: redirect("config.php?config="+this.options[this.selectedIndex].value);');
	$cmbConfig->addItems(array(
		8 => _('GUI'),
		0 => _('Housekeeper'),
		3 => _('Images'),
		14 => _('Icon mapping'),
		10 => _('Regular expressions'),
		11 => _('Macros'),
		6 => _('Value mapping'),
		7 => _('Working time'),
		12 => _('Trigger severities'),
		13 => _('Trigger displaying options'),
		5 => _('Other')
	));
	$form->addItem($cmbConfig);

	if(!isset($_REQUEST['form'])){
		switch($_REQUEST['config']){
			case 3:
				$form->addItem(new CSubmit('form', _('Create image')));
				break;
			case 6:
				$form->addItem(new CSubmit('form', _('Create value map')));
				break;
			case 10:
				$form->addItem(new CSubmit('form', _('New regular expression')));
				break;
			case 14:
				$form->addItem(new CSubmit('form', _('Create icon map')));
				break;
		}
	}

	$cnf_wdgt = new CWidget();
	$cnf_wdgt->addPageHeader(_('CONFIGURATION OF ZABBIX'), $form);


	if(isset($_REQUEST['config'])){
		$config = select_config(false, get_current_nodeid(false));
	}

/////////////////////////////////
//  config = 0 // Housekeeper  //
/////////////////////////////////
	if($_REQUEST['config'] == 0){
		$data = array();
		$data['form'] = get_request('form', 1);
		$data['form_refresh'] = get_request('form_refresh', 0);

		if($data['form_refresh']){
			$data['config']['alert_history'] = get_request('alert_history');
			$data['config']['event_history'] = get_request('event_history');
		}
		else{
			$data['config'] = select_config(false);
		}

		$houseKeeperForm = new CView('administration.general.housekeeper.edit', $data);
		$cnf_wdgt->addItem($houseKeeperForm->render());
	}
////////////////////////////
//  config = 3 // Images  //
////////////////////////////
	elseif ($_REQUEST['config'] == 3) {
		$data = array();
		$data['form'] = get_request('form', null);
		$data['widget'] = &$cnf_wdgt;

		if (!empty($data['form'])) {
			if (!empty($_REQUEST['imageid'])) {
				$image = DBfetch(DBselect('SELECT i.imagetype, i.name FROM images i WHERE i.imageid = '.$_REQUEST['imageid']));

				$data['imageid'] = $_REQUEST['imageid'];
				$data['imagename'] = $image['name'];
				$data['imagetype'] = $image['imagetype'];
			}
			else {
				$data['imageid'] = null;
				$data['imagename'] = get_request('name', '');
				$data['imagetype'] = get_request('imagetype', 1);
			}

			$imageForm = new CView('administration.general.image.edit', $data);
			$cnf_wdgt->addItem($imageForm->render());
		}
		else {
			$data['imagetype'] = get_request('imagetype', IMAGE_TYPE_ICON);
			$options = array(
				'filter'=> array('imagetype'=> $data['imagetype']),
				'output'=> API_OUTPUT_EXTEND,
				'sortfield'=> 'name'
			);
			$data['images'] = API::Image()->get($options);

			$imageForm = new CView('administration.general.image.list', $data);
			$cnf_wdgt->addItem($imageForm->render());
		}
	}
//////////////////////////////////////
//  config = 5 // Other Parameters  //
//////////////////////////////////////
	elseif($_REQUEST['config'] == 5){
		$data = array();
		$data['form'] = get_request('form', 1);
		$data['form_refresh'] = get_request('form_refresh', 0);

		if($data['form_refresh']){
			$data['config']['discovery_groupid'] = get_request('discovery_groupid');
			$data['config']['alert_usrgrpid'] = get_request('alert_usrgrpid');
			$data['config']['refresh_unsupported'] = get_request('refresh_unsupported');
			$data['config']['snmptrap_logging'] = get_request('snmptrap_logging');
		}
		else{
			$data['config'] = select_config(false);
		}

		$data['discovery_groups'] = API::HostGroup()->get(array(
										'sortfield'=>'name',
										'editable' => 1,
										'output' => API_OUTPUT_EXTEND
									));
		$data['alert_usrgrps'] = DBfetchArray(DBselect('SELECT usrgrpid, name FROM usrgrp WHERE '.DBin_node('usrgrpid').' order by name'));

		$otherForm = new CView('administration.general.other.edit', $data);
		$cnf_wdgt->addItem($otherForm->render());
	}
///////////////////////////////////
//  config = 6 // Value Mapping  //
///////////////////////////////////
	elseif ($_REQUEST['config'] == 6) {
		$data = array();
		if (isset($_REQUEST['form'])) {
			$data['form'] = get_request('form', 1);
			$data['form_refresh'] = get_request('form_refresh', 0);
			$data['valuemapid'] = get_request('valuemapid');
			$data['valuemap'] = array();
			$data['mapname'] = '';
			$data['title'] = '';
			$data['confirmMessage'] = null;
			$data['add_value'] = get_request('add_value');
			$data['add_newvalue'] = get_request('add_newvalue');;

			if (!empty($data['valuemapid'])) {
				$db_valuemap = DBfetch(DBselect('SELECT v.name FROM valuemaps v WHERE v.valuemapid = '.$data['valuemapid']));
				$data['mapname'] = $db_valuemap['name'];
				$data['title'] = ' "'.$data['mapname'].'"';

				if (empty($data['form_refresh'])) {
					$db_mappings = DBselect('SELECT m.value, m.newvalue FROM mappings m WHERE m.valuemapid = '.$data['valuemapid']);
					while ($mapping = DBfetch($db_mappings)) {
						$data['valuemap'][] = array('value' => $mapping['value'], 'newvalue' => $mapping['newvalue']);
					}
				}
				else {
					$data['mapname'] = get_request('mapname', '');
					$data['valuemap'] = get_request('valuemap', array());
				}

				$valuemap_count = DBfetch(DBselect('SELECT COUNT(i.itemid) as cnt FROM items i WHERE i.valuemapid='.$data['valuemapid']));
				if ($valuemap_count['cnt']) {
					$data['confirmMessage'] = _n('Delete selected value mapping? It is used for %d item!', 'Delete selected value mapping? It is used for %d items!', $valuemap_count['cnt']);
				}
				else {
					$data['confirmMessage'] = _('Delete selected value mapping?');
				}
			}

			if (empty($data['valuemapid']) && !empty($data['form_refresh'])) {
				$data['mapname'] = get_request('mapname', '');
				$data['valuemap'] = get_request('valuemap', array());
			}

			order_result($data['valuemap'], 'value');

			$valueMappingForm = new CView('administration.general.valuemapping.edit', $data);
			$cnf_wdgt->addItem($valueMappingForm->render());
		}
		else{
			$cnf_wdgt->addHeader(_('Value mapping'));
			$data['valuemaps'] = array();

			$db_valuemaps = DBselect('SELECT v.valuemapid, v.name FROM valuemaps v WHERE '.DBin_node('valuemapid'));
			while ($db_valuemap = DBfetch($db_valuemaps)) {
				$data['valuemaps'][$db_valuemap['valuemapid']] = $db_valuemap;
				$data['valuemaps'][$db_valuemap['valuemapid']]['maps'] = array();
			}

			$db_maps = DBselect('SELECT m.valuemapid, m.value, m.newvalue FROM mappings m WHERE '.DBin_node('mappingid'));
			while ($db_map = DBfetch($db_maps)) {
				$data['valuemaps'][$db_map['valuemapid']]['maps'][] = array('value' => $db_map['value'], 'newvalue' => $db_map['newvalue']);
			}
			order_result($data['valuemaps'], 'name');

			$valueMappingForm = new CView('administration.general.valuemapping.list', $data);
			$cnf_wdgt->addItem($valueMappingForm->render());
		}
	}
/////////////////////////////////
//  config = 7 // Working time //
/////////////////////////////////
	elseif($_REQUEST['config'] == 7){
		$data = array();
		$data['form'] = get_request('form', 1);
		$data['form_refresh'] = get_request('form_refresh', 0);

		if($data['form_refresh']){
			$data['config']['work_period'] = get_request('work_period');
		}
		else{
			$data['config'] = select_config(false);
		}

		$workingTimeForm = new CView('administration.general.workingtime.edit', $data);
		$cnf_wdgt->addItem($workingTimeForm->render());
	}
/////////////////////////
//  config = 8 // GUI  //
/////////////////////////
	elseif($_REQUEST['config'] == 8){
		$data = array();
		$data['form'] = get_request('form', 1);
		$data['form_refresh'] = get_request('form_refresh', 0);

		if($data['form_refresh']){
			$data['config']['default_theme'] = get_request('default_theme');
			$data['config']['event_ack_enable'] = get_request('event_ack_enable');
			$data['config']['dropdown_first_entry'] = get_request('dropdown_first_entry');
			$data['config']['dropdown_first_remember'] = get_request('dropdown_first_remember');
			$data['config']['search_limit'] = get_request('search_limit');
			$data['config']['max_in_table'] = get_request('max_in_table');
			$data['config']['event_expire'] = get_request('event_expire');
			$data['config']['event_show_max'] = get_request('event_show_max');
		}
		else{
			$data['config'] = select_config(false);
		}

		$guiForm = new CView('administration.general.gui.edit', $data);
		$cnf_wdgt->addItem($guiForm->render());
	}
//////////////////////////////////////////
//  config = 10 // Regular Expressions  //
//////////////////////////////////////////
	elseif($_REQUEST['config'] == 10){
		$data = array();

		if(isset($_REQUEST['form'])){
			$data['form'] = get_request('form', 1);
			$data['form_refresh'] = get_request('form_refresh', 0) + 1;

			$regExpForm = new CView('administration.general.regularexpressions.edit', $data);
			$cnf_wdgt->addItem($regExpForm->render());
		}
		else{
			$data['cnf_wdgt'] = &$cnf_wdgt;
			$data['regexps'] = array();
			$data['regexpids'] = array();

			$db_regexps = DBselect('SELECT re.* FROM regexps re WHERE '.DBin_node('re.regexpid').' ORDER BY re.name');
			while($regexp = DBfetch($db_regexps)){
				$regexp['expressions'] = array();
				$data['regexps'][$regexp['regexpid']] = $regexp;
				$data['regexpids'][$regexp['regexpid']] = $regexp['regexpid'];
			}

			$data['db_exps'] = DBfetchArray(DBselect('SELECT e.* FROM expressions e WHERE '.DBin_node('e.expressionid').' AND '.DBcondition('e.regexpid', $data['regexpids']).' ORDER BY e.expression_type'));

			$regExpForm = new CView('administration.general.regularexpressions.list', $data);
			$cnf_wdgt->addItem($regExpForm->render());
		}
	}
/////////////////////////////
//  config = 11 // Macros  //
/////////////////////////////
	elseif ($_REQUEST['config'] == 11) {
		$data = array();
		$data['form'] = get_request('form', 1);
		$data['form_refresh'] = get_request('form_refresh', 0);
		$data['macros'] = array();

		if ($data['form_refresh']) {
			$data['macros'] = get_request('macros', array());
		}
		else {
			$data['macros'] = API::UserMacro()->get(array('output' => API_OUTPUT_EXTEND, 'globalmacro' => 1));
			order_result($data['macros'], 'macro');
		}
		if (empty($data['macros'])) {
			$data['macros'] = array(0 => array('macro' => '', 'value' => ''));
		}

		$macrosForm = new CView('administration.general.macros.edit', $data);
		$cnf_wdgt->addItem($macrosForm->render());
	}
/////////////////////////////////////////
//  config = 12 // Trigger severities  //
/////////////////////////////////////////
	elseif($_REQUEST['config'] == 12){
		$data = array();
		$data['form'] = get_request('form', 1);
		$data['form_refresh'] = get_request('form_refresh', 0);

		if($data['form_refresh']){
			$data['config']['severity_name_0'] = get_request('severity_name_0');
			$data['config']['severity_color_0'] = get_request('severity_color_0', '');
			$data['config']['severity_name_1'] = get_request('severity_name_1');
			$data['config']['severity_color_1'] = get_request('severity_color_1', '');
			$data['config']['severity_name_2'] = get_request('severity_name_2');
			$data['config']['severity_color_2'] = get_request('severity_color_2', '');
			$data['config']['severity_name_3'] = get_request('severity_name_3');
			$data['config']['severity_color_3'] = get_request('severity_color_3', '');
			$data['config']['severity_name_4'] = get_request('severity_name_4');
			$data['config']['severity_color_4'] = get_request('severity_color_4', '');
			$data['config']['severity_name_5'] = get_request('severity_name_5');
			$data['config']['severity_color_5'] = get_request('severity_color_5', '');
		}
		else{
			$data['config'] = select_config(false);
		}

		$triggerSeverityForm = new CView('administration.general.triggerSeverity.edit', $data);
		$cnf_wdgt->addItem($triggerSeverityForm->render());
	}
////////////////////////////////////////////////
//  config = 13 // Trigger displaying options //
////////////////////////////////////////////////
	elseif($_REQUEST['config'] == 13){
		$data = array();
		$data['form'] = get_request('form', 1);
		$data['form_refresh'] = get_request('form_refresh', 0);

		// form has been submitted
		if($data['form_refresh']){
			$data['ok_period'] = get_request('ok_period');
			$data['blink_period'] = get_request('blink_period');
			$data['problem_unack_color'] = get_request('problem_unack_color');
			$data['problem_ack_color'] = get_request('problem_ack_color');
			$data['ok_unack_color'] = get_request('ok_unack_color');
			$data['ok_ack_color'] = get_request('ok_ack_color');
			$data['problem_unack_style'] = get_request('problem_unack_style');
			$data['problem_ack_style'] = get_request('problem_ack_style');
			$data['ok_unack_style'] = get_request('ok_unack_style');
			$data['ok_ack_style'] = get_request('ok_ack_style');
		}
		else{
			$config = select_config(false);
			$data['ok_period'] = $config['ok_period'];
			$data['blink_period'] = $config['blink_period'];
			$data['problem_unack_color'] = $config['problem_unack_color'];
			$data['problem_ack_color'] = $config['problem_ack_color'];
			$data['ok_unack_color'] = $config['ok_unack_color'];
			$data['ok_ack_color'] = $config['ok_ack_color'];
			$data['problem_unack_style'] = $config['problem_unack_style'];
			$data['problem_ack_style'] = $config['problem_ack_style'];
			$data['ok_unack_style'] = $config['ok_unack_style'];
			$data['ok_ack_style'] = $config['ok_ack_style'];
		}

		$triggerDisplayingForm = new CView('administration.general.triggerDisplayingOptions.edit', $data);
		$cnf_wdgt->addItem($triggerDisplayingForm->render());
	}
	//////////////////////////////////
	//  config = 14 // Icon mapping //
	//////////////////////////////////
	elseif ($_REQUEST['config'] == 14) {
		$data = array();
		$data['form_refresh'] = get_request('form_refresh', 0);
		$data['iconmapid'] = get_request('iconmapid', null);

		$data['iconList'] = array();
		$iconList = API::Image()->get(array(
			'filter'=> array('imagetype'=> IMAGE_TYPE_ICON),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
		foreach ($iconList as $icon) {
			$data['iconList'][$icon['imageid']] = $icon['name'];
		}

		$data['inventoryList'] = array();
		$inventoryFields = getHostInventories();
		foreach ($inventoryFields as $field) {
			$data['inventoryList'][$field['nr']] = $field['title'];
		}

		if (isset($_REQUEST['form'])) {
			if ($data['form_refresh'] || ($_REQUEST['form'] === 'clone')) {
				$data['iconmap'] = get_request('iconmap');
			}
			elseif (isset($_REQUEST['iconmapid'])) {
				$iconMap = API::IconMap()->get(array(
					'output' => API_OUTPUT_EXTEND,
					'iconmapids' => $_REQUEST['iconmapid'],
					'editable' => true,
					'preservekeys' => true,
					'selectMappings' => API_OUTPUT_EXTEND,
				));
				$data['iconmap'] = reset($iconMap);
			}
			else {
				$firstIcon = reset($iconList);
				$data['iconmap'] = array(
					'name' => '',
					'default_iconid' => $firstIcon['imageid'],
					'mappings' => array(),
				);
			}

			$iconMapView = new CView('administration.general.iconmap.edit', $data);
		}
		else {
			$cnf_wdgt->addHeader(_('Icon mapping'));
			$data['iconmaps'] = API::IconMap()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'editable' => true,
				'preservekeys' => true,
				'selectMappings' => API_OUTPUT_EXTEND,
			));
			order_result($data['iconmaps'], 'name');
			$iconMapView = new CView('administration.general.iconmap.list', $data);
		}

		$cnf_wdgt->addItem($iconMapView->render());
	}

$cnf_wdgt->show();

require_once('include/page_footer.php');
?>
