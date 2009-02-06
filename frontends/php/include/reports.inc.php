<?php
/* 
** ZABBIX
** Copyright (C) 2000-2008 SIA Zabbix
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

function show_report2_header($config,&$PAGE_GROUPS, &$PAGE_HOSTS){
	global $USER_DETAILS;
	$available_groups = $PAGE_GROUPS['groupids'];
	$available_hosts = $PAGE_HOSTS['hostids'];

//	$available_groups = $PAGE_GROUPS['groupids'];
//	$available_hosts = $PAGE_HOSTS['hostids'];
	
	$r_form = new CForm();
	$r_form->setMethod('get');
	
	$cmbConf = new CComboBox('config',$config,'submit()');
	$cmbConf->addItem(0,S_BY_HOST);
	$cmbConf->addItem(1,S_BY_TRIGGER_TEMPLATE);

	$r_form->addItem(array(S_MODE.SPACE,$cmbConf,SPACE));

	$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
	$cmbHosts = new CComboBox('hostid',$PAGE_HOSTS['selected'],'javascript: submit();');

	foreach($PAGE_GROUPS['groups'] as $groupid => $name){
		$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid).$name);
	}
	foreach($PAGE_HOSTS['hosts'] as $hostid => $name){
		$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid).$name);
	}
	
	$r_form->addItem(array(S_GROUP.SPACE,$cmbGroups));
	$r_form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));

	if(1 == $config){
		$cmbTrigs = new CComboBox('tpl_triggerid',get_request('tpl_triggerid',0),'submit()');
		$cmbHGrps = new CComboBox('hostgroupid',get_request('hostgroupid',0),'submit()');
		
		$cmbTrigs->addItem(0,S_ALL_SMALL);
		$cmbHGrps->addItem(0,S_ALL_SMALL);
	}
	
	if(0 == $config){
		show_table_header(S_AVAILABILITY_REPORT_BIG, $r_form);
	}
	else{
		$sql_cond = ' AND h.hostid=ht.hostid ';
		if($_REQUEST['hostid'] > 0)	$sql_cond.=' AND ht.templateid='.$_REQUEST['hostid'];
		
		if(isset($_REQUEST['tpl_triggerid']) && ($_REQUEST['tpl_triggerid'] > 0))
			$sql_cond.= ' AND t.templateid='.$_REQUEST['tpl_triggerid'];

		$result = DBselect('SELECT DISTINCT g.groupid,g.name '.
			' FROM triggers t,hosts h,items i,functions f, hosts_templates ht, groups g, hosts_groups hg '.
			' WHERE f.itemid=i.itemid '.
				' AND h.hostid=i.hostid '.
				' AND hg.hostid=h.hostid'.
				' AND g.groupid=hg.groupid '.
				' AND '.DBcondition('h.hostid',$available_hosts).
				' AND t.status='.TRIGGER_STATUS_ENABLED.
				' AND t.triggerid=f.triggerid '.
				' AND '.DBin_node('t.triggerid').
				' AND i.status='.ITEM_STATUS_ACTIVE.
				' AND h.status='.HOST_STATUS_MONITORED.
				$sql_cond.
			' ORDER BY g.name');

		while($row=DBfetch($result)){
			$cmbHGrps->addItem(
				$row['groupid'],
				get_node_name_by_elid($row['groupid']).$row['name']
				);
		}
		
		$sql_cond=($_REQUEST['hostid'] > 0)?' AND h.hostid='.$_REQUEST['hostid']:' AND '.DBcondition('h.hostid',$available_hosts);
		$sql = 'SELECT DISTINCT t.triggerid,t.description '.
			' FROM triggers t,hosts h,items i,functions f '.
			' WHERE f.itemid=i.itemid '.
				' AND h.hostid=i.hostid '.
				' AND t.status='.TRIGGER_STATUS_ENABLED.
				' AND t.triggerid=f.triggerid '.
				' AND h.status='.HOST_STATUS_TEMPLATE.
				' AND '.DBin_node('t.triggerid').
				' AND i.status='.ITEM_STATUS_ACTIVE.
				$sql_cond.
			' ORDER BY t.description';
		$result=DBselect($sql);

		while($row=DBfetch($result)){
			$cmbTrigs->addItem(
					$row['triggerid'],
					get_node_name_by_elid($row['triggerid']).expand_trigger_description($row['triggerid'])
					);
		}
		$rr_form = new CForm();
		$rr_form->setMethod('get');
		$rr_form->addVar('config',$config);
		$rr_form->addVar('groupid',$_REQUEST['groupid']);
		$rr_form->addVar('hostid',$_REQUEST['hostid']);
		
		$rr_form->addItem(array(S_TRIGGER.SPACE,$cmbTrigs,BR(),S_FILTER,SPACE,S_HOST_GROUP.SPACE,$cmbHGrps));
		show_table_header(S_AVAILABILITY_REPORT_BIG, array($r_form,$rr_form));
	}
}
?>