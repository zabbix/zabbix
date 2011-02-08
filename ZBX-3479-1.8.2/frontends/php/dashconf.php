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

require_once('include/config.inc.php');
require_once('include/hosts.inc.php');
require_once('include/triggers.inc.php');
require_once('include/html.inc.php');

$page['title'] = "S_DASHBOARD_CONFIGURATION";
$page['file'] = 'dashconf.php';
$page['hist_arg'] = array();
$page['scripts'] = array();

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

include_once('include/page_header.php');

//		VAR				TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields = array(
		'filterEnable'=>array(T_ZBX_INT, O_OPT, P_SYS,	NULL,				NULL),
		'del_groups'=>	array(T_ZBX_INT, O_OPT, P_SYS,	NULL,				NULL),
		'groupids'=>	array(T_ZBX_INT, O_OPT, P_SYS,	NULL,				NULL),
		'new_right'=>	array(T_ZBX_STR, O_OPT,	null,	null,				null),
		'trgSeverity'=>	array(T_ZBX_INT, O_OPT, P_SYS,	NULL,				NULL),
		'grpswitch'=>	array(T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(0,1),		NULL),

		'maintenance'=>	array(T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(0,1),		NULL),

		'form_refresh'=>array(T_ZBX_INT, O_OPT, P_SYS,	null,				NULL),
		'save'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,				NULL),
		'delete'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,				NULL)
	);

	check_fields($fields);
?>
<?php
// ACTION /////////////////////////////////////////////////////////////////////////////
	if(isset($_REQUEST['save'])){
// FILTER
		$filterEnable = get_request('filterEnable', 0);
		CProfile::update('web.dashconf.filter.enable', $filterEnable, PROFILE_TYPE_INT);

		if($filterEnable == 1){
// GROUPS
			$groupids = get_request('groupids', array());

			CProfile::update('web.dashconf.groups.grpswitch', $_REQUEST['grpswitch'], PROFILE_TYPE_INT);

			if($_REQUEST['grpswitch'] == 1){
				$result = rm4favorites('web.dashconf.groups.groupids');
				foreach($groupids as $gnum => $groupid){
					$result &= add2favorites('web.dashconf.groups.groupids', $groupid);
				}
			}

// HOSTS
			$_REQUEST['maintenance'] = get_request('maintenance', 0);
			CProfile::update('web.dashconf.hosts.maintenance', $_REQUEST['maintenance'], PROFILE_TYPE_INT);

// TRIGGERS
			$_REQUEST['trgSeverity'] = get_request('trgSeverity', array());
			$trgSeverity = implode(';', array_keys($_REQUEST['trgSeverity']));
			CProfile::update('web.dashconf.triggers.severity', $trgSeverity, PROFILE_TYPE_STR);
		}

		redirect('dashboard.php');
	}
	else if(isset($_REQUEST['new_right'])){
		$_REQUEST['groupids'] = get_request('groupids', array());

		foreach($_REQUEST['new_right'] as $id => $group){
			$_REQUEST['groupids'][$id] = $id;
		}
	}
	else if(isset($_REQUEST['delete'])){
		$del_groups = get_request('del_groups',array());

		foreach($del_groups as $gnum => $groupid){
			if(!isset($_REQUEST['groupids'][$groupid])) continue;

			unset($_REQUEST['groupids'][$groupid]);
		}
	}
?>
<?php
	$dashboard_wdgt = new CWidget();
	
// Header
	$dashboard_wdgt->setClass('header');
	$dashboard_wdgt->addPageHeader(S_DASHBOARD_CONFIGURATION_BIG, SPACE);

//-------------
// GROUPS
	$dashForm = new CFormTable(S_FILTER);
	$dashForm->addVar('form_refresh', 1);
	$dashForm->setName('dashconf');
	$dashForm->setAttribute('id', 'dashform');

	if(isset($_REQUEST['form_refresh'])){
		$filterEnable = get_request('filterEnable', 0);

		$groupids = get_request('groupids', array());
		$groupids = zbx_toHash($groupids);
		
		$grpswitch = get_request('grpswitch', 0);

		$maintenance = get_request('maintenance', 0);

		$severity = get_request('trgSeverity', array());
		$severity = array_keys($severity);
	}
	else{
		$filterEnable = CProfile::get('web.dashconf.filter.enable', 0);

		$groupids = get_favorites('web.dashconf.groups.groupids');
		$groupids = zbx_objectValues($groupids, 'value');
		$groupids = zbx_toHash($groupids);

		$grpswitch = CProfile::get('web.dashconf.groups.grpswitch', 0);
		$maintenance = CProfile::get('web.dashconf.hosts.maintenance', 1);

		$severity = CProfile::get('web.dashconf.triggers.severity', '0;1;2;3;4;5');
		$severity = zbx_empty($severity)?array():explode(';', $severity);
	}

	$dashForm->addVar('filterEnable', $filterEnable);

	if($filterEnable){
		$cbFilter = new CSpan(S_ENABLED, 'green underline pointer');
		$cbFilter->setAttribute('onclick', "create_var('".$dashForm->getName()."', 'filterEnable', 0, true);");
	}
	else{
		$cbFilter = new CSpan(S_DISABLED, 'red underline pointer');
		$cbFilter->setAttribute('onclick', "$('dashform').enable(); create_var('".$dashForm->getName()."', 'filterEnable', 1, true);");
	}
	
	$dashForm->addRow(S_DASHBOARD_FILTER, $cbFilter);

	$dashForm->addVar('groupids', $groupids);

	$cmbGroups = new CComboBox('grpswitch', $grpswitch, 'submit();');
	$cmbGroups->addItem(0, S_ALL_S);
	$cmbGroups->addItem(1, S_SELECTED);
	
	if(!$filterEnable) $cmbGroups->setAttribute('disabled', 'disabled');

	$dashForm->addRow(S_HOST_GROUPS, $cmbGroups);

	if($grpswitch == 1){
		$options = array(
			'groupids' => $groupids,
			'output' => API_OUTPUT_EXTEND
		);

		$groups = CHostGroup::get($options);
		order_result($groups, 'name');

		$lstGroups = new CListBox('del_groups[]',null,15);
		$lstGroups->setAttribute('style', 'width: 200px;');
		foreach($groups as $gnum => $group){
			$lstGroups->addItem($group['groupid'], get_node_name_by_elid($group['groupid'], null, ':').$group['name']);
		}

		if(!$filterEnable) $lstGroups->setAttribute('disabled', 'disabled');

		$addButton = new CButton('add',S_ADD, "return PopUp('popup_right.php?dstfrm=".$dashForm->getName()."&permission=".PERM_READ_WRITE."',450,450);");
		if(!$filterEnable) $addButton->setAttribute('disabled', 'disabled');

		$delButton = new CButton('delete',S_DELETE_SELECTED);
		if(!$filterEnable) $delButton->setAttribute('disabled', 'disabled');

		$dashForm->addRow(
				S_GROUPS,
				array($lstGroups,BR(),$addButton, $delButton)
			);
	}

//HOSTS
// SPACE added to extend CB width in Chrome
	$cbMain = new CCheckBox('maintenance', $maintenance, null, '1');
	if(!$filterEnable) $cbMain->setAttribute('disabled', 'disabled');

	$dashForm->addRow(S_HOSTS, array($cbMain, S_SHOW_HOSTS_IN_MAINTENANCE));

// Trigger
	$severity = zbx_toHash($severity);
	$trgSeverities = array();

	$cb = new CCheckBox('trgSeverity['.TRIGGER_SEVERITY_NOT_CLASSIFIED.']', isset($severity[TRIGGER_SEVERITY_NOT_CLASSIFIED]), '', 1);
	$cb->setEnabled($filterEnable);
	$trgSeverities[] = array($cb, S_NOT_CLASSIFIED);
	$trgSeverities[] = BR();

	$cb = new CCheckBox('trgSeverity['.TRIGGER_SEVERITY_INFORMATION.']', isset($severity[TRIGGER_SEVERITY_INFORMATION]), '', 1);
	$cb->setEnabled($filterEnable);
	$trgSeverities[] = array($cb, S_INFORMATION);
	$trgSeverities[] = BR();

	$cb = new CCheckBox('trgSeverity['.TRIGGER_SEVERITY_WARNING.']', isset($severity[TRIGGER_SEVERITY_WARNING]), '', 1);
	$cb->setEnabled($filterEnable);
	$trgSeverities[] = array($cb, S_WARNING);
	$trgSeverities[] = BR();

	$cb = new CCheckBox('trgSeverity['.TRIGGER_SEVERITY_AVERAGE.']', isset($severity[TRIGGER_SEVERITY_AVERAGE]), '', 1);
	$cb->setEnabled($filterEnable);
	$trgSeverities[] = array($cb, S_AVERAGE);
	$trgSeverities[] = BR();

	$cb = new CCheckBox('trgSeverity['.TRIGGER_SEVERITY_HIGH.']', isset($severity[TRIGGER_SEVERITY_HIGH]), '', 1);
	$cb->setEnabled($filterEnable);
	$trgSeverities[] = array($cb, S_HIGH);
	$trgSeverities[] = BR();

	$cb = new CCheckBox('trgSeverity['.TRIGGER_SEVERITY_DISASTER.']', isset($severity[TRIGGER_SEVERITY_DISASTER]), '', 1);
	$cb->setEnabled($filterEnable);
	$trgSeverities[] = array($cb, S_DISASTER);

	$dashForm->addRow(S_TRIGGERS_WITH_SEVERITY, $trgSeverities);
//-----

	$dashForm->addItemToBottomRow(new CButton('save',S_SAVE));
	
	$dashboard_wdgt->addItem($dashForm);
	$dashboard_wdgt->show();

?>
<?php

include_once("include/page_footer.php");

?>