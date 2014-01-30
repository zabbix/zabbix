<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once('include/config.inc.php');
require_once('include/hosts.inc.php');
require_once('include/maintenances.inc.php');
require_once('include/forms.inc.php');

$page['title'] = 'S_MAINTENANCE';
$page['file'] = 'maintenance.php';
$page['hist_arg'] = array('groupid','hostid');
$page['scripts'] = array('class.calendar.js');

include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
/* ARRAYS */
		'hosts'=>				array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groups'=>				array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'hostids'=>				array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groupids'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'hostid'=>				array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groupid'=>				array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),

// maintenance
		'maintenanceid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		'isset({form})&&({form}=="update")'),
		'maintenanceids'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, 		NULL),
		'mname'=>				array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	'isset({save})'),
		'maintenance_type'=>	array(T_ZBX_INT, O_OPT,	null,	null,		'isset({save})'),

		'description'=>			array(T_ZBX_STR, O_OPT,	NULL,	null,		'isset({save})'),

		'active_since'=>		array(T_ZBX_STR, O_OPT,  null, 	NOT_EMPTY,	'isset({save})'),
		'active_till'=>			array(T_ZBX_STR, O_OPT,  null, 	NOT_EMPTY,	'isset({save})'),

		'new_timeperiod'=>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({add_timeperiod})'),

		'timeperiods'=>			array(T_ZBX_STR, O_OPT, null,	null, null),
		'g_timeperiodid'=>		array(null,		 O_OPT, null,	null, null),

		'edit_timeperiodid'=>	array(null,		 O_OPT, P_ACT,	DB_ID, null),
		'twb_groupid' =>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),

// actions
		'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),

// form actions
		'add_timeperiod'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, 	null, null),
		'del_timeperiod'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel_new_timeperiod'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'clone'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),

/* other */
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>array(T_ZBX_STR, O_OPT, NULL,	NULL,	NULL)
	);

	check_fields($fields);
	validate_sort_and_sortorder('name',ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go','none');

// PERMISSIONS
	if(get_request('groupid', 0) > 0){
		$groupids = available_groups($_REQUEST['groupid'], 1);
		if(empty($groupids)) access_deny();
	}

	if(get_request('hostid', 0) > 0){
		$hostids = available_hosts($_REQUEST['hostid'], 1);
		if(empty($hostids)) access_deny();
	}
?>
<?php
/************ MAINTENANCE ****************/

	if(inarr_isset(array('clone','maintenanceid'))){
		unset($_REQUEST['maintenanceid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['cancel_new_timeperiod'])){
		unset($_REQUEST['new_timeperiod']);
	}
	else if(isset($_REQUEST['save'])){
		if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
			access_deny();

		$maintenance = array(
			'name' => $_REQUEST['mname'],
			'maintenance_type' => $_REQUEST['maintenance_type'],
			'description'=>	$_REQUEST['description'],
			'active_since'=> zbxDateToTime(get_request('active_since', date('YmdHi'))),
			'active_till' => zbxDateToTime(get_request('active_till', 0)),
			'timeperiods' => get_request('timeperiods', array()),
			'hostids' => get_request('hostids', array()),
			'groupids' => get_request('groupids', array()),
		);

		if(isset($_REQUEST['maintenanceid'])){
			$maintenance['maintenanceid'] = $_REQUEST['maintenanceid'];

			$result = CMaintenance::update($maintenance);

			$msg1 = S_MAINTENANCE_UPDATED;
			$msg2 = S_CANNOT_UPDATE_MAINTENANCE;
		}
		else{
			$result = CMaintenance::create($maintenance);

			$msg1 = S_MAINTENANCE_ADDED;
			$msg2 = S_CANNOT_ADD_MAINTENANCE;
		}

		show_messages($result,$msg1,$msg2);

		if($result){ // result - OK
			add_audit(!isset($_REQUEST['maintenanceid'])?AUDIT_ACTION_ADD:AUDIT_ACTION_UPDATE,
				AUDIT_RESOURCE_MAINTENANCE,
				S_NAME.': '.$_REQUEST['mname']);

			unset($_REQUEST['form']);
		}
	}
	else if(isset($_REQUEST['delete']) || ($_REQUEST['go'] == 'delete')){
		if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY))) access_deny();

		$maintenanceids = get_request('maintenanceid', array());
		if(isset($_REQUEST['maintenanceids']))
			$maintenanceids = $_REQUEST['maintenanceids'];

		zbx_value2array($maintenanceids);

		$maintenances = array();
		foreach($maintenanceids as $id => $maintenanceid){
			$maintenances[$maintenanceid] = get_maintenance_by_maintenanceid($maintenanceid);
		}

		$go_result = CMaintenance::delete($maintenanceids);

		show_messages($go_result,S_MAINTENANCE_DELETED,S_CANNOT_DELETE_MAINTENANCE);
		if($go_result){
			foreach($maintenances as $maintenanceid => $maintenance){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_MAINTENANCE,'Id ['.$maintenanceid.'] '.S_NAME.' ['.$maintenance['name'].']');
			}

			unset($_REQUEST['form']);
			unset($_REQUEST['maintenanceid']);
		}
	}
	else if(inarr_isset(array('add_timeperiod','new_timeperiod'))){
		$new_timeperiod = $_REQUEST['new_timeperiod'];

		$new_timeperiod['start_date'] = zbxDateToTime($new_timeperiod['start_date']);

// START TIME
		$new_timeperiod['start_time'] = $new_timeperiod['hour'] * SEC_PER_HOUR + $new_timeperiod['minute'] * SEC_PER_MIN;

// PERIOD
		$new_timeperiod['period'] = $new_timeperiod['period_days'] * SEC_PER_DAY + $new_timeperiod['period_hours'] * SEC_PER_HOUR + $new_timeperiod['period_minutes'] * SEC_PER_MIN;

// DAYSOFWEEK
		if(!isset($new_timeperiod['dayofweek'])){
			$dayofweek = '';

			$dayofweek .= (!isset($new_timeperiod['dayofweek_su']))?'0':'1';
			$dayofweek .= (!isset($new_timeperiod['dayofweek_sa']))?'0':'1';
			$dayofweek .= (!isset($new_timeperiod['dayofweek_fr']))?'0':'1';
			$dayofweek .= (!isset($new_timeperiod['dayofweek_th']))?'0':'1';
			$dayofweek .= (!isset($new_timeperiod['dayofweek_we']))?'0':'1';
			$dayofweek .= (!isset($new_timeperiod['dayofweek_tu']))?'0':'1';
			$dayofweek .= (!isset($new_timeperiod['dayofweek_mo']))?'0':'1';

			$new_timeperiod['dayofweek'] = bindec($dayofweek);
		}
//--

// MONTHS
		if(!isset($new_timeperiod['month'])){
			$month = '';

			$month .= (!isset($new_timeperiod['month_dec']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_nov']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_oct']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_sep']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_aug']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_jul']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_jun']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_may']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_apr']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_mar']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_feb']))?'0':'1';
			$month .= (!isset($new_timeperiod['month_jan']))?'0':'1';

			$new_timeperiod['month'] = bindec($month);
		}
//--

		if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY){
			if($new_timeperiod['month_date_type'] > 0){
				$new_timeperiod['day'] = 0;
			}
			else{
				$new_timeperiod['every'] = 0;
				$new_timeperiod['dayofweek'] = 0;
			}
		}

		$_REQUEST['timeperiods'] = get_request('timeperiods',array());

		$result = false;
		if($new_timeperiod['period'] < 300) {	/* 5 min */
			info(S_INCORRECT_MAINTENANCE_PERIOD.' ('.S_MIN_SMALL.SPACE.S_5_MINUTES.')');
		}
		else if(($new_timeperiod['hour'] > 23) || ($new_timeperiod['minute'] > 59)){
			info(S_INCORRECT_MAINTENANCE_PERIOD);
		}
		else if(($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME) && ($new_timeperiod['start_date'] < 1)){
			info(S_INCORRECT_MAINTENANCE_DATE);
		}
		else if(($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_DAILY) && ($new_timeperiod['every'] < 1)){
			info(S_INCORRECT_MAINTENANCE_DAY_PERIOD);
		}
		else if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY){
			if($new_timeperiod['every'] < 1){
				info(S_INCORRECT_MAINTENANCE_WEEK_PERIOD);
			}
			else if($new_timeperiod['dayofweek'] < 1){
				info(S_INCORRECT_MAINTENANCE_DAYS_OF_WEEK);
			}
			else{
				$result = true;
			}
		}
		else if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY){
			if($new_timeperiod['month'] < 1){
				info(S_INCORRECT_MAINTENANCE_MONTH_PERIOD);
			}
			else if(($new_timeperiod['day'] == 0) && ($new_timeperiod['dayofweek'] < 1)){
				info(S_INCORRECT_MAINTENANCE_DAYS_OF_WEEK);
			}
			else if((($new_timeperiod['day'] < 1) || ($new_timeperiod['day'] > 31)) && ($new_timeperiod['dayofweek'] == 0)){
				info(S_INCORRECT_MAINTENANCE_DATE);
			}
			else{
				$result = true;
			}
		}
		else{
			$result = true;
		}

		if($result){
			if(!isset($new_timeperiod['id'])){
				if(!str_in_array($new_timeperiod,$_REQUEST['timeperiods']))
					array_push($_REQUEST['timeperiods'],$new_timeperiod);
			}
			else{
				$id = $new_timeperiod['id'];
				unset($new_timeperiod['id']);
				$_REQUEST['timeperiods'][$id] = $new_timeperiod;
			}

			unset($_REQUEST['new_timeperiod']);
		}
	}
	else if(inarr_isset(array('del_timeperiod','g_timeperiodid'))){
		$_REQUEST['timeperiods'] = get_request('timeperiods',array());
		foreach($_REQUEST['g_timeperiodid'] as $val){
			unset($_REQUEST['timeperiods'][$val]);
		}
	}
	else if(inarr_isset(array('edit_timeperiodid'))){
		$_REQUEST['edit_timeperiodid'] = array_keys($_REQUEST['edit_timeperiodid']);
		$edit_timeperiodid = $_REQUEST['edit_timeperiodid'] = array_pop($_REQUEST['edit_timeperiodid']);
		$_REQUEST['timeperiods'] = get_request('timeperiods',array());

		if(isset($_REQUEST['timeperiods'][$edit_timeperiodid])){
			$_REQUEST['new_timeperiod'] = $_REQUEST['timeperiods'][$edit_timeperiodid];
			$_REQUEST['new_timeperiod']['id'] = $edit_timeperiodid;
			$_REQUEST['new_timeperiod']['start_date'] = date('YmdHi', $_REQUEST['timeperiods'][$edit_timeperiodid]['start_date']);
		}
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}


	$options = array(
		'groups' => array(
			'editable' => 1,
		),
		'groupid' => get_request('groupid', null),
	);
	$pageFilter = new CPageFilter($options);
	$_REQUEST['groupid'] = $pageFilter->groupid;


	$frmForm = new CForm(null, 'get');

	if(!isset($_REQUEST['form'])){
		$frmForm->addItem(new CButton('form',S_CREATE_MAINTENANCE_PERIOD));
	}

	$maintenance_wdgt = new CWidget();
	$maintenance_wdgt->addPageHeader(S_CONFIGURATION_OF_MAINTENANCE_PERIODS, $frmForm);
?>
<?php
	if(isset($_REQUEST['form'])){
		$frmMaintenance = new CForm('maintenance.php', 'post');
		$frmMaintenance->setName(S_MAINTENANCE);
		$frmMaintenance->addVar('form', get_request('form', 1));
		$frmMaintenance->addVar('form_refresh', get_request('form_refresh',0)+1);

		if(isset($_REQUEST['maintenanceid']))
			$frmMaintenance->addVar('maintenanceid', $_REQUEST['maintenanceid']);

		$left_tab = new CTable();
		$left_tab->setCellPadding(3);
		$left_tab->setCellSpacing(3);

// MAINTENANCE FORM {{{
		if(isset($_REQUEST['maintenanceid']) && !isset($_REQUEST['form_refresh'])){
			$options = array(
				'editable' => 1,
				'maintenanceids' => $_REQUEST['maintenanceid'],
				'output' => API_OUTPUT_EXTEND,
			);
			$maintenance = CMaintenance::get($options);
			$maintenance = reset($maintenance);

			$mname				= $maintenance['name'];
			$maintenance_type	= $maintenance['maintenance_type'];
			$active_since		= $maintenance['active_since'];
			$active_till		= $maintenance['active_till'];
			$description		= $maintenance['description'];
		}
		else{
			$mname				= get_request('mname', '');
			$maintenance_type	= get_request('maintenance_type', 0);
			$active_since		= zbxDateToTime(get_request('active_since', date('YmdHi')));
			$active_till		= zbxDateToTime(get_request('active_till', date('YmdHi', time() + SEC_PER_DAY)));
			$description		= get_request('description', '');
		}
		$tblMntc = new CTable(null, 'formElementTable');

		$tblMntc->addRow(array(S_NAME, new CTextBox('mname', $mname, 50)));

		$cmbType =  new CComboBox('maintenance_type', $maintenance_type);
		$cmbType->addItem(MAINTENANCE_TYPE_NORMAL, S_WITH_DATA_COLLECTION);
		$cmbType->addItem(MAINTENANCE_TYPE_NODATA, S_NO_DATA_COLLECTION);
		$tblMntc->addRow(array(S_MAINTENANCE_TYPE, $cmbType));

		$tblMntc->addItem(new Cvar('active_since', date('YmdHi', $active_since)));
		$tblMntc->addItem(new Cvar('active_till', date('YmdHi', $active_till)));

		$clndr_icon = new CImg('images/general/bar/cal.gif','calendar', 16, 12, 'pointer');

		$clndr_icon->addAction('onclick', 'javascript: var pos = getPosition(this); '.
			'pos.top+=10; pos.left+=16; CLNDR["mntc_active_since"].clndr.clndrshow(pos.top,pos.left);');
		zbx_add_post_js('create_calendar(null, ["mntc_since_day","mntc_since_month","mntc_since_year",'.
			'"mntc_since_hour","mntc_since_minute"],"mntc_active_since","active_since");');
		$tblMntc->addRow(array(S_ACTIVE_SINCE,array(
			new CNumericBox('mntc_since_day',(($active_since>0)?date('d',$active_since):''),2),	'/',
			new CNumericBox('mntc_since_month',(($active_since>0)?date('m',$active_since):''),2), '/',
			new CNumericBox('mntc_since_year',(($active_since>0)?date('Y',$active_since):''),4), SPACE,
			new CNumericBox('mntc_since_hour',(($active_since>0)?date('H',$active_since):''),2), ':',
			new CNumericBox('mntc_since_minute',(($active_since>0)?date('i',$active_since):''),2),
			$clndr_icon
		)));


		$clndr_icon->addAction('onclick','javascript: var pos = getPosition(this); '.
			'pos.top+=10; pos.left+=16; CLNDR["mntc_active_till"].clndr.clndrshow(pos.top,pos.left);');
		zbx_add_post_js('create_calendar(null,["mntc_till_day","mntc_till_month","mntc_till_year",'.
			'"mntc_till_hour","mntc_till_minute"],"mntc_active_till","active_till");');
		$tblMntc->addRow(array(S_ACTIVE_TILL, array(
			new CNumericBox('mntc_till_day',(($active_till>0)?date('d',$active_till):''),2), '/',
			new CNumericBox('mntc_till_month',(($active_till>0)?date('m',$active_till):''),2), '/',
			new CNumericBox('mntc_till_year',(($active_till>0)?date('Y',$active_till):''),4), SPACE,
			new CNumericBox('mntc_till_hour',(($active_till>0)?date('H',$active_till):''),2), ':',
			new CNumericBox('mntc_till_minute',(($active_till>0)?date('i',$active_till):''),2),
			$clndr_icon
		)));

		$tblMntc->addRow(array(S_DESCRIPTION, new CTextArea('description', $description,66,5)));


		$footer = array(new CButton('save', S_SAVE));
		if(isset($_REQUEST['maintenanceid'])){
			$footer[] = new CButton('clone',S_CLONE);
			$footer[] = new CButtonDelete(S_DELETE_MAINTENANCE_PERIOD_Q, url_param('form').url_param('maintenanceid'));
		}
		$footer[] = new CButtonCancel();

		$left_tab->addRow(new CFormElement(S_MAINTENANCE, $tblMntc, $footer));
// }}} MAINTENANCE FORM


// MAINTENANCE PERIODS {{{
		$tblPeriod = new CTableInfo();

		if(isset($_REQUEST['maintenanceid']) && !isset($_REQUEST['form_refresh'])){
			$timeperiods = array();
			$sql = 'SELECT DISTINCT mw.maintenanceid, tp.* '.
					' FROM timeperiods tp, maintenances_windows mw '.
					' WHERE mw.maintenanceid='.$_REQUEST['maintenanceid'].
						' AND tp.timeperiodid=mw.timeperiodid '.
					' ORDER BY tp.timeperiod_type ASC';
			$db_timeperiods = DBselect($sql);
			while($timeperiod = DBfetch($db_timeperiods)){
				$timeperiods[] = $timeperiod;
			}
		}
		else{
			$timeperiods = get_request('timeperiods', array());
		}

		$tblPeriod->setHeader(array(
			new CCheckBox('all_periods',null,'checkAll("'.S_PERIOD.'","all_periods","g_timeperiodid");'),
			S_PERIOD_TYPE,
			S_SCHEDULE,
			S_PERIOD,
			S_ACTION
		));

		foreach($timeperiods as $id => $timeperiod){
			$tblPeriod->addRow(array(
				new CCheckBox('g_timeperiodid[]', 'no', null, $id),
				timeperiod_type2str($timeperiod['timeperiod_type']),
				new CCol(shedule2str($timeperiod), 'wraptext'),
				zbx_date2age(0,$timeperiod['period']),
				new CButton('edit_timeperiodid['.$id.']', S_EDIT)
			));

			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][timeperiod_type]', $timeperiod['timeperiod_type']));
			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][every]', $timeperiod['every']));
			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][month]', $timeperiod['month']));
			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][dayofweek]', $timeperiod['dayofweek']));
			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][day]', $timeperiod['day']));
			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][start_time]', $timeperiod['start_time']));
			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][start_date]', $timeperiod['start_date']));
			$tblPeriod->addItem(new Cvar('timeperiods['.$id.'][period]', $timeperiod['period']));
		}

		$footer = array();
		if(!isset($_REQUEST['new_timeperiod'])){
			$footer[] = new CButton('new_timeperiod', S_NEW);
		}
		if($tblPeriod->ItemsCount() > 0 ){
			$footer[] = new CButton('del_timeperiod', S_DELETE_SELECTED);
		}

		$left_tab->addRow(new CFormElement(S_MAINTENANCE, $tblPeriod, $footer));
// }}} MAINTENANCE PERIODS

		if(isset($_REQUEST['new_timeperiod'])){
			$new_timeperiod = $_REQUEST['new_timeperiod'];

			$left_tab->addRow(create_hat(
					(is_array($new_timeperiod) && isset($new_timeperiod['id']))?S_EDIT_MAINTENANCE_PERIOD:S_NEW_MAINTENANCE_PERIOD,
					get_timeperiod_form(),//nulls
					null,
					'hat_new_timeperiod'
				));
		}

		$right_tab = new CTable();
		$right_tab->setCellPadding(3);
		$right_tab->setCellSpacing(3);

// MAINTENANCE HOSTS {{{
		$options = array(
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'real_hosts' => true,
			'preservekeys' => true
		);
		$all_groups = CHostGroup::get($options);
		order_result($all_groups, 'name');

		$twb_groupid = get_request('twb_groupid', 0);
		if(!isset($all_groups[$twb_groupid])){
			$twb_group = reset($all_groups);
			$twb_groupid = $twb_group['groupid'];
		}

		$cmbGroups = new CComboBox('twb_groupid', $twb_groupid, 'submit()');
		foreach($all_groups as $group){
			$cmbGroups->addItem($group['groupid'], $group['name']);
		}

		if(isset($_REQUEST['maintenanceid']) && !isset($_REQUEST['form_refresh'])){
			$hostids = CHost::get(array(
				'maintenanceids' => $_REQUEST['maintenanceid'],
				'real_hosts' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'editable' => 1,
			));
			$hostids = zbx_objectValues($hostids, 'hostid');
		}
		else{
			$hostids = get_request('hostids', array());
		}

		$host_tb = new CTweenBox($frmMaintenance, 'hostids', $hostids, 10);

		// hosts from selected twb group
		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'real_hosts' => 1,
			'editable' => 1,
			'groupids' => $twb_groupid,
		);
		$hosts = CHost::get($options);

		// selected hosts
		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'real_hosts' => 1,
			'editable' => 1,
			'hostids' => $hostids,
		);
		$hosts_selected = CHost::get($options);

		$hosts = array_merge($hosts, $hosts_selected);
		$hosts = zbx_toHash($hosts, 'hostid');
		order_result($hosts, 'host');

		foreach($hosts as $host){
			$host_tb->addItem($host['hostid'], $host['host']);
		}

		$tblHlink = new CTable(null, 'formElementTable');
		$tblHlink->addRow($host_tb->Get(S_IN.SPACE.S_MAINTENANCE, array(S_OTHER.SPACE.S_HOSTS.SPACE.'|'.SPACE.S_GROUP.SPACE, $cmbGroups)));

		$right_tab->addRow(new CFormElement(S_HOSTS_IN_MAINTENANCE, $tblHlink));
// }}} MAINTENANCE HOSTS


// MAINTENANCE GROUPS {{{
		$tblGlink = new CTable(null, 'formElementTable');

		if(isset($_REQUEST['maintenanceid']) && !isset($_REQUEST['form_refresh'])){
			$groupids = CHostGroup::get(array(
				'maintenanceids' => $_REQUEST['maintenanceid'],
				'real_hosts' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'editable' => 1,
			));
			$groupids = zbx_objectValues($groupids, 'groupid');
		}
		else{
			$groupids = get_request('groupids', array());
		}

		$group_tb = new CTweenBox($frmMaintenance, 'groupids', $groupids, 10);

		foreach($all_groups as $group){
			$group_tb->addItem($group['groupid'], $group['name']);
		}

		$tblGlink->addRow($group_tb->Get(S_IN.SPACE.S_MAINTENANCE, S_OTHER.SPACE.S_GROUPS));

		$right_tab->addRow(new CFormElement(S_GROUPS_IN_MAINTENANCE, $tblGlink));
// }}} MAINTENANCE GROUPS

		$td_l = new CCol($left_tab);
		$td_l->setAttribute('valign','top');

		$td_r = new CCol($right_tab);
		$td_r->setAttribute('valign','top');

		$outer_table = new CTable();
		$outer_table->addRow(array($td_l, $td_r));

		$frmMaintenance->additem($outer_table);

		show_messages();
		$maintenance_wdgt->addItem($frmMaintenance);
	}
	else{
// Table HEADER
		$form = new CForm(null,'get');
		$form->addItem(array(S_GROUP.SPACE, $pageFilter->getGroupsCB()));

		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');

		$maintenance_wdgt->addHeader(S_MAINTENANCE_PERIODS_BIG, $form);
		$maintenance_wdgt->addHeader($numrows);
// ----

		$sortfield = getPageSortField('name');
		$sortorder = getPageSortOrder();
		$options = array(
			'extendoutput' => 1,
			'editable' => 1,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);
		if($pageFilter->groupsSelected){
			if($pageFilter->groupid > 0)
				$options['groupids'] = $pageFilter->groupid;
			else
				$options['groupids'] = array_keys($pageFilter->groups);
		}
		else{
			$options['groupids'] = array();
		}

		$maintenances = CMaintenance::get($options);

		$form = new CForm();
		$form->setName('maintenances');

		$table = new CTableInfo();
		$table->setHeader(array(
			new CCheckBox('all_maintenances',NULL,"checkAll('".$form->GetName()."','all_maintenances','maintenanceids');"),
			make_sorting_header(S_NAME,'name'),
			make_sorting_header(S_TYPE,'maintenance_type'),
			make_sorting_header(S_STATUS,'status'),
			S_DESCRIPTION
		));

		foreach($maintenances as $mnum => $maintenance){
			if($maintenance['active_till'] < time())
				$maintenances[$mnum]['status'] = MAINTENANCE_STATUS_EXPIRED;
			else if($maintenance['active_since'] > time())
				$maintenances[$mnum]['status'] = MAINTENANCE_STATUS_APPROACH;
			else
				$maintenances[$mnum]['status'] = MAINTENANCE_STATUS_ACTIVE;
		}
		order_result($maintenances, $sortfield, $sortorder);
		$paging = getPagingLine($maintenances);

		foreach($maintenances as $mnum => $maintenance){
			$maintenanceid = $maintenance['maintenanceid'];

			switch($maintenance['status']){
				case MAINTENANCE_STATUS_EXPIRED:
					$mnt_status = new CSpan(S_EXPIRED,'red');
					break;
				case MAINTENANCE_STATUS_APPROACH:
					$mnt_status = new CSpan(S_APPROACHING,'blue');
					break;
				case MAINTENANCE_STATUS_ACTIVE:
					$mnt_status = new CSpan(S_ACTIVE,'green');
					break;
			}

			$table->addRow(array(
				new CCheckBox('maintenanceids['.$maintenanceid.']',NULL,NULL,$maintenanceid),
				new CLink($maintenance['name'],'maintenance.php?form=update&maintenanceid='.$maintenanceid.'#form'),
				$maintenance['maintenance_type']?S_NO_DATA_COLLECTION:S_WITH_DATA_COLLECTION,
				$mnt_status,
				$maintenance['description']
			));
		}

// goBox
		$goBox = new CComboBox('go');
		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_MAINTENANCE_PERIODS_Q);
		$goBox->addItem($goOption);

		// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO.' (0)');
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "maintenanceids";');

		$footer = get_table_header(array($goBox, $goButton));

		$form->addItem(array($paging,$table,$paging,$footer));

		$maintenance_wdgt->addItem($form);
	}

	$maintenance_wdgt->show();


include_once('include/page_footer.php');

?>
