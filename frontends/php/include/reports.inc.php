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

function show_report2_header($config,$available_hosts){
	
	$r_form = new CForm();
	$r_form->SetMethod('get');
	
	$cmbConf = new CComboBox('config',$config,'submit()');
	$cmbConf->AddItem(0,S_BY_HOST);
	$cmbConf->AddItem(1,S_BY_TRIGGER_TEMPLATE);

	$r_form->AddItem(array(S_MODE.SPACE,$cmbConf,SPACE));

	$cmbGroup = new CComboBox('groupid',$_REQUEST['groupid'],'submit()');
	$cmbGroup->AddItem(0,S_ALL_SMALL);
	
	$status_filter = ' AND h.status='.HOST_STATUS_MONITORED;
	if($config==1)
		$status_filter = ' AND h.status='.HOST_STATUS_TEMPLATE;

	$sql = 'SELECT DISTINCT g.groupid,g.name '.
					' FROM groups g,hosts_groups hg,hosts h'.
					' WHERE h.hostid in ('.$available_hosts.') '.
						' AND g.groupid=hg.groupid '.
						' AND h.hostid=hg.hostid'.$status_filter.
					' ORDER BY g.name';

	$result=DBselect($sql);
	while($row=DBfetch($result)){
		$cmbGroup->AddItem($row['groupid'],	get_node_name_by_elid($row['groupid']).$row['name']);
	}
	$r_form->AddItem(array(S_GROUP.SPACE,$cmbGroup));

	if(0 == $config){
		$cmbHosts = new CComboBox('hostid',$_REQUEST['hostid'],'submit()');
		$sql_cond = ' AND h.status='.HOST_STATUS_MONITORED;
	}
	else{
		$cmbTpls = new CComboBox('hostid',$_REQUEST['hostid'],'submit()');
		$cmbTrigs = new CComboBox('tpl_triggerid',get_request('tpl_triggerid',0),'submit()');

		$cmbTrigs->AddItem(0,S_ALL_SMALL);
		
		$sql_cond = ' AND h.status='.HOST_STATUS_TEMPLATE;		
	}
	
	
	if($_REQUEST['groupid'] > 0){
		$sql='SELECT h.hostid,h.host '.
			' FROM hosts h,items i,hosts_groups hg '.
			' WHERE h.hostid=i.hostid '.
				' AND hg.groupid='.$_REQUEST['groupid'].
				' AND hg.hostid=h.hostid'.
				' AND h.hostid in ('.$available_hosts.') '.
				$sql_cond.
			' GROUP BY h.hostid,h.host '.
			' ORDER BY h.host';
	}
	else{
		$sql='SELECT h.hostid,h.host '.
			' FROM hosts h,items i '.
			' WHERE h.hostid=i.hostid '.
				' AND h.hostid in ('.$available_hosts.') '.
				$sql_cond.
			' GROUP BY h.hostid,h.host '.
			' ORDER BY h.host';

		if(0 == $config){
			$cmbHosts->AddItem(0,S_ALL_SMALL);
		}
		else{
			$cmbTpls->AddItem(0,S_ALL_SMALL);
		}
		
	}

	$result=DBselect($sql);
	while($row=DBfetch($result)){
		if(0 == $config){
			$cmbHosts->AddItem($row['hostid'],get_node_name_by_elid($row['hostid']).$row['host']);
		}
		else{
			$cmbTpls->AddItem($row['hostid'],get_node_name_by_elid($row['hostid']).$row['host']);
		}
	}

	
	if(0 == $config){
		$r_form->AddItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
		show_table_header(S_AVAILABILITY_REPORT_BIG, $r_form);
	}
	else{
		$r_form->AddItem(array(SPACE.S_TEMPLATE.SPACE,$cmbTpls));
		if($_REQUEST['hostid'] > 0){
			$sql = 'SELECT DISTINCT t.triggerid,t.description '.
				' FROM triggers t,hosts h,items i,functions f '.
				' WHERE f.itemid=i.itemid '.
					' AND h.hostid=i.hostid '.
					' AND t.status='.TRIGGER_STATUS_ENABLED.
					' AND t.triggerid=f.triggerid '.
					' AND h.hostid='.$_REQUEST['hostid'].
					' AND h.status='.HOST_STATUS_TEMPLATE.
					' AND '.DBin_node('t.triggerid').
					' AND i.status='.ITEM_STATUS_ACTIVE.
				' ORDER BY t.description';
		}
		else{
			$sql = 'SELECT DISTINCT t.triggerid,t.description '.
				' FROM triggers t,hosts h,items i,functions f '.
				' WHERE f.itemid=i.itemid '.
					' AND h.hostid=i.hostid '.
					' AND t.status='.TRIGGER_STATUS_ENABLED.
					' AND t.triggerid=f.triggerid '.
					' AND h.status='.HOST_STATUS_TEMPLATE.
					' AND h.hostid in ('.$available_hosts.')'.
					' AND '.DBin_node('t.triggerid').
					' AND i.status='.ITEM_STATUS_ACTIVE.
				' ORDER BY t.description';
		}
		$result=DBselect($sql);

		while($row=DBfetch($result)){
			$cmbTrigs->AddItem(
					$row['triggerid'],
					get_node_name_by_elid($row['triggerid']).expand_trigger_description($row['triggerid'])
					);
		}
		$rr_form = new CForm();
		$rr_form->SetMethod('get');
		$rr_form->AddVar('config',$config);
		$rr_form->AddVar('groupid',$_REQUEST['groupid']);
		$rr_form->AddVar('hostid',$_REQUEST['hostid']);
		
		$rr_form->AddItem(array(S_TRIGGER.SPACE,$cmbTrigs));
		show_table_header(S_AVAILABILITY_REPORT_BIG, array($r_form,$rr_form));
	}

}
?>
