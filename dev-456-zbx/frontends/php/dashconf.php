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

$page['title'] = "S_DASHBOARD_CONFIGURATION";
$page['file'] = 'dashconf.php';
$page['hist_arg'] = array();
$page['scripts'] = array();

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

include_once('include/page_header.php');

//		VAR				TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'config'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,2),		'isset({save})'),

		'del_groups'=>	array(T_ZBX_INT, O_OPT, P_SYS,	NULL,				NULL),
		'groupids'=>	array(T_ZBX_INT, O_OPT, P_SYS,	NULL,				NULL),
		'new_right'=>	array(T_ZBX_STR, O_OPT,	null,	null,				null),
		'trgSeverity'=>	array(T_ZBX_INT, O_OPT, P_SYS,	NULL,				NULL),
		'grpswitch'=>	array(T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(0,1),		NULL),

		'maintenance'=>	array(T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(0,1),		NULL),

		'save'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,				NULL),
		'delete'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,				NULL)
	);

	check_fields($fields);
?>
<?php
	$config = get_request('config', 0);

// ACTION /////////////////////////////////////////////////////////////////////////////
	if(isset($_REQUEST['save'])){
		if(0 == $config){
			$groupids = get_request('groupids', array());

			CProfile::update('web.dashconf.groups.grpswitch', $_REQUEST['grpswitch'], PROFILE_TYPE_INT);

			if($_REQUEST['grpswitch'] == 1){
				$result = rm4favorites('web.dashconf.groups.groupids');
				foreach($groupids as $gnum => $groupid){
					$result &= add2favorites('web.dashconf.groups.groupids', $groupid);
				}
			}
		}
		else if(1 == $config){
			CProfile::update('web.dashconf.hosts.maintenance', $_REQUEST['maintenance'], PROFILE_TYPE_INT);
		}
		else if(2 == $config){
			$trgSeverity = implode(';', array_keys($_REQUEST['trgSeverity']));
			CProfile::update('web.dashconf.triggers.severity', $trgSeverity, PROFILE_TYPE_STR);
		}

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
	$r_form = new CForm();

	$cmbConf = new CComboBox('config', $config, 'submit();');
	$cmbConf->addItem(0, S_GROUPS);
	$cmbConf->addItem(1, S_HOSTS);
	$cmbConf->addItem(2, S_TRIGGERS);

	$r_form->addItem($cmbConf);

	$dashboard_wdgt->setClass('header');
	$dashboard_wdgt->addPageHeader(S_DASHBOARD_CONFIGURATION_BIG, $r_form);

//-------------


// GROUPS
	$dashGroups = new CWidget('hat_dashgroups');

	if(0 == $config){
		$tblGrp = new CFormTable(S_GROUPS);
		$tblGrp->setName('dashconf');
		$tblGrp->addVar('config', 0);

		$groupids = get_request('groupids', get_favorites('web.dashconf.groups.groupids'));
		$groupids = zbx_objectValues($groupids, 'value');
		$groupids = zbx_toHash($groupids);

		$tblGrp->addVar('groupids', $groupids);

		$grpswitch = get_request('grpswitch', CProfile::get('web.dashconf.groups.grpswitch', 0));
		$cmbGroups = new CComboBox('grpswitch', $grpswitch, 'submit();');
		$cmbGroups->addItem(0, S_ALL_S);
		$cmbGroups->addItem(1, S_SELECTED);

		$tblGrp->addRow(S_VIEW, $cmbGroups);

		if($grpswitch == 1){
			$options = array(
				'groupids' => $groupids,
				'output' => API_OUTPUT_EXTEND
			);

			$groups = CHostGroup::get($options);
			order_result($groups, 'name');

			$lstGroups = new CListBox('del_groups[]',null,20);
			$lstGroups->setAttribute('style', 'width: 200px;');
			foreach($groups as $gnum => $group){
				$lstGroups->addItem($group['groupid'], get_node_name_by_elid($group['groupid'], true, ':').$group['name']);
			}

			$tblGrp->addRow(
					S_GROUPS,
					array(
						$lstGroups,
						BR(),
						new CButton('add',S_ADD, "return PopUp('popup_right.php?dstfrm=".$tblGrp->getName()."&permission=".PERM_READ_WRITE."',450,450);"),
						new CButton('delete',S_DELETE_SELECTED)
					)
				);
		}
	}
	else if(1 == $config){
		$tblGrp = new CFormTable(S_HOSTS);
		$tblGrp->addVar('config', 1);

		$maintenance = get_request('maintenance', CProfile::get('web.dashconf.hosts.maintenance', 0));

		$cmbMain = new CComboBox('maintenance', $maintenance);
		$cmbMain->addItem(0, S_SHOW_ALL);
		$cmbMain->addItem(1, S_HIDE);

		$tblGrp->addRow(S_HOSTS_IN_MAINTENANCE, $cmbMain);
	}
	else if(2 == $config){
		$severity = get_request('trgSeverity');
		if(is_null($severity)){
			$severity = CProfile::get('web.dashconf.triggers.severity', array());

			if(!empty($severity)) $severity = explode(';', $severity);
		}
		else{
			$severity = array_keys($severity);
		}

		$tblGrp = new CFormTable(S_TRIGGERS);
		$tblGrp->addVar('config', 2);

		$trgSeverities = array();

		$checked = in_array(TRIGGER_SEVERITY_NOT_CLASSIFIED, $severity);
		$trgSeverities[] = array(new CCheckBox('trgSeverity['.TRIGGER_SEVERITY_NOT_CLASSIFIED.']', $checked, '', 1), S_NOT_CLASSIFIED);
		$trgSeverities[] = BR();

		$checked = in_array(TRIGGER_SEVERITY_INFORMATION, $severity);
		$trgSeverities[] = array(new CCheckBox('trgSeverity['.TRIGGER_SEVERITY_INFORMATION.']', $checked, '', 1), S_INFORMATION);
		$trgSeverities[] = BR();

		$checked = in_array(TRIGGER_SEVERITY_WARNING, $severity);
		$trgSeverities[] = array(new CCheckBox('trgSeverity['.TRIGGER_SEVERITY_WARNING.']', $checked, '', 1), S_WARNING);
		$trgSeverities[] = BR();

		$checked = in_array(TRIGGER_SEVERITY_AVERAGE, $severity);
		$trgSeverities[] = array(new CCheckBox('trgSeverity['.TRIGGER_SEVERITY_AVERAGE.']', $checked, '', 1), S_AVERAGE);
		$trgSeverities[] = BR();

		$checked = in_array(TRIGGER_SEVERITY_HIGH, $severity);
		$trgSeverities[] = array(new CCheckBox('trgSeverity['.TRIGGER_SEVERITY_HIGH.']', $checked, '', 1), S_HIGH);
		$trgSeverities[] = BR();

		$checked = in_array(TRIGGER_SEVERITY_DISASTER, $severity);
		$trgSeverities[] = array(new CCheckBox('trgSeverity['.TRIGGER_SEVERITY_DISASTER.']', $checked, '', 1), S_DISASTER);

		$tblGrp->addRow(S_SEVERITY, $trgSeverities);
	}

	$tblGrp->addItemToBottomRow(new CButton('save',S_SAVE));

	$dashGroups->addItem($tblGrp);
	
	$dashboard_wdgt->addItem($dashGroups);
	$dashboard_wdgt->show();

?>
<?php

include_once("include/page_footer.php");

?>