<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	require_once ('include/config.inc.php');
	require_once ('include/triggers.inc.php');

	$page['title']	= "S_TRIGGERS_TOP_100";
	$page['file']	= 'report5.php';
	$page['hist_arg'] = array('period');
	$page['scripts'] = array();
	
include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'period'=>		array(T_ZBX_STR, O_OPT,	P_SYS|P_NZERO,	IN('"day","week","month","year"'),		NULL)
	);

	check_fields($fields);
?>
<?php
	$_REQUEST['period'] = get_request('period', 'day');
	$admin_links = (($USER_DETAILS['type'] == USER_TYPE_ZABBIX_ADMIN) || ($USER_DETAILS['type'] == USER_TYPE_SUPER_ADMIN));
	
	$form = new CForm();
	$form->SetMethod('get');
	
	$cmbPeriod = new CComboBox('period',$_REQUEST['period'],'submit()');
	$cmbPeriod->AddItem('day',S_DAY);
	$cmbPeriod->AddItem('week',S_WEEK);
	$cmbPeriod->AddItem('month',S_MONTH);
	$cmbPeriod->AddItem('year',S_YEAR);

	$form->AddItem($cmbPeriod);

	show_table_header(S_TRIGGERS_TOP_100_BIG, $form);
?>
<?php
	$table = new CTableInfo();
	$table->setHeader(array(
			is_show_subnodes() ? S_NODE : null,
			S_HOST,
			S_TRIGGER,
			S_SEVERITY,
			S_NUMBER_OF_STATUS_CHANGES
			));

	switch($_REQUEST['period']){
		case 'week':	$time_dif=7*24*3600;	break;
		case 'month':	$time_dif=10*24*3600;	break;
		case 'year':	$time_dif=365*24*3600;	break;
		case 'day':
		default:	$time_dif=24*3600;	break;
	}

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY);
	$available_triggers = get_accessible_triggers(PERM_READ_ONLY,PERM_RES_IDS_ARRAY);
	$scripts_by_hosts = get_accessible_scripts_by_hosts($available_hosts);


	$triggers = array();
	$triggerids = array();
	$sql = 'SELECT h.host, h.hostid, t.triggerid, t.description, t.expression, t.lastchange, t.priority, count(distinct e.eventid) as cnt_event '.
			' FROM hosts h, triggers t, functions f, items i, events e'.
			' WHERE h.hostid = i.hostid '.
				' and i.itemid = f.itemid '.
				' and t.triggerid=f.triggerid '.
				' and t.triggerid=e.objectid '.
				' and e.object='.EVENT_OBJECT_TRIGGER.
				' and e.clock>'.(time()-$time_dif).
				' and '.DBcondition('t.triggerid',$available_triggers).
				' and '.DBin_node('t.triggerid').
			' GROUP BY h.host,t.triggerid,t.description,t.expression,t.priority '.
			' ORDER BY cnt_event desc, h.host, t.description, t.triggerid';

	$result=DBselect($sql, 100);
	while($row=DBfetch($result)){
		$row['items'] = array();
		$triggers[$row['triggerid']] = $row;
		$triggerids[$row['triggerid']] = $row['triggerid'];
	}
	
	$sql = 'SELECT f.triggerid, i.* '.
			' FROM functions f, items i '.
			' WHERE '.DBcondition('f.triggerid',$triggerids).
				' AND i.itemid=f.itemid';
	$result = DBselect($sql);
	while($row = DBfetch($result)){
		$item['itemid'] = $row['itemid'];
		$item['action'] = str_in_array($row['value_type'],array(ITEM_VALUE_TYPE_FLOAT,ITEM_VALUE_TYPE_UINT64))?'showgraph':'showvalues';
		$item['description'] = item_description($row);
		
		$triggers[$row['triggerid']]['items'][$row['itemid']] = $item;
	}
	
	foreach($triggers as $triggerid => $row){
		$description = expand_trigger_description_by_data($row);
		
		$menus = '';
		$host_nodeid = id2nodeid($row['hostid']);
		foreach($scripts_by_hosts[$row['hostid']] as $id => $script){
			$script_nodeid = id2nodeid($script['scriptid']);
			if( (bccomp($host_nodeid ,$script_nodeid ) == 0))
				$menus.= "['".$script['name']."',\"javascript: openWinCentered('scripts_exec.php?execute=1&hostid=".$row['hostid']."&scriptid=".$script['scriptid']."','".S_TOOLS."',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
		}

		$menus.= "[".zbx_jsvalue(S_LINKS).",null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";
		$menus.= "['".S_LATEST_DATA."',\"javascript: redirect('latest.php?hostid=".$row['hostid']."')\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";

		$menus = rtrim($menus,',');
		$menus="show_popup_menu(event,[[".zbx_jsvalue(S_TOOLS).",null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],".$menus."],180);";
		
		$host = new CSpan($row['host']);
		$host->addOption('onclick','javascript: '.$menus);
		$host->addOption('onmouseover',"javascript: this.style.cursor = 'pointer';");
		
		$tr_desc = new CSpan($description,'pointer');
		$tr_desc->addAction('onclick',"create_mon_trigger_menu(event, ".
										" new Array({'triggerid': '".$row['triggerid']."', 'lastchange': '".$row['lastchange']."'}),".
										zbx_jsvalue($row['items']).");");

		$table->addRow(array(
			get_node_name_by_elid($row['triggerid']),
			$host,
			$tr_desc,
			new CCol(get_severity_description($row['priority']),get_severity_style($row['priority'])),
			$row['cnt_event'],
		));
	}
	$table->show();
	
	$jsmenu = new CPUMenu(null,170);
	$jsmenu->InsertJavaScript();
?>
<?php

include_once 'include/page_footer.php';

?>
