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
	require_once 'include/config.inc.php';
	require_once 'include/hosts.inc.php';
	require_once 'include/reports.inc.php';

	$page['title']	= 'S_AVAILABILITY_REPORT';
	$page['file']	= 'report2.php';
	$page['hist_arg'] = array('config','groupid','hostid','tpl_triggerid');

include_once 'include/page_header.php';

?>
<?php
//		VAR					TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'config'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
		'groupid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			NULL),
		'hostgroupid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			NULL),
		'hostid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			NULL),
		'tpl_triggerid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			NULL),
		
		'triggerid'=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,			NULL)
	);

	check_fields($fields);

	$config = get_request('config',get_profile('web.avail_report.config',0));
	update_profile('web.avail_report.config',$config);

	if(0 == $config){
		$options = array('allow_all_hosts','always_select_first_host','monitored_hosts','with_items');
	}
	else{
		$options = array('allow_all_hosts','always_select_first_host','templated_hosts','with_items');
	}
		
	if(!$ZBX_WITH_SUBNODES)	array_push($options,'only_current_node');
	
	validate_group_with_host(PERM_READ_LIST,$options);	
?>
<?php
$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY);
show_report2_header($config,$available_hosts);
?>
<?php
	if( isset($_REQUEST['triggerid']) &&
		!($trigger_data = DBfetch(DBselect('SELECT DISTINCT t.*, h.host, h.hostid '.
					' FROM triggers t, functions f, items i, hosts h '.
					' WHERE t.triggerid='.$_REQUEST['triggerid'].
						' AND t.triggerid=f.triggerid '.
						' AND f.itemid=i.itemid '.
						' AND i.hostid=h.hostid '.
						' AND h.hostid in ('.$available_hosts.') '
					))) )
	{
		unset($_REQUEST['triggerid']);
	}
	

	if(isset($_REQUEST['triggerid'])){
		if(!check_right_on_trigger_by_triggerid(PERM_READ_ONLY, $_REQUEST['triggerid']))
			access_deny();
		
		show_table_header(array(new CLink($trigger_data['host'],'?hostid='.$trigger_data['hostid']),' : "',expand_trigger_description_by_data($trigger_data),'"'));

		$table = new CTableInfo(null,'graph');
		$table->AddRow(new CImg('chart4.php?triggerid='.$_REQUEST['triggerid']));
		$table->Show();
	}
	else if(isset($_REQUEST['hostid'])){
		if(0 == $config){
			if($_REQUEST['hostid'] > 0)	
				$sql_cond = ' AND h.hostid='.$_REQUEST['hostid'];
			else
				$sql_cond = '';
		}
		else{
			$sql_cond = ' AND h.hostid=ht.hostid ';
			$sql_cond.=(isset($_REQUEST['hostgroupid']) && ($_REQUEST['hostgroupid']>0))?' AND g.groupid ='.$_REQUEST['hostgroupid']:'';
			
			if($_REQUEST['hostid'] > 0)	$sql_cond.=' AND ht.templateid='.$_REQUEST['hostid'];
			
			if(isset($_REQUEST['tpl_triggerid']) && ($_REQUEST['tpl_triggerid'] > 0))
				$sql_cond.= ' AND t.templateid='.$_REQUEST['tpl_triggerid'];
		}
		
		if($_REQUEST['hostid'] > 0){
			$row	= DBfetch(DBselect('SELECT host FROM hosts WHERE hostid='.$_REQUEST['hostid']));
			show_table_header($row['host']);
		}
		else{
			if(isset($_REQUEST['tpl_triggerid']) && ($_REQUEST['tpl_triggerid'] > 0))
				show_table_header(expand_trigger_description($_REQUEST['tpl_triggerid']));
			else
				show_table_header(S_ALL_HOSTS_BIG);				
		}
		$result = DBselect('SELECT DISTINCT h.hostid,h.host,t.triggerid,t.expression,t.description,t.value '.
			' FROM triggers t,hosts h,items i,functions f, hosts_templates ht, groups g, hosts_groups hg '.
			' WHERE f.itemid=i.itemid '.
				' AND hg.hostid=h.hostid'.
				' AND g.groupid=hg.groupid '.
				' AND h.hostid=i.hostid '.
				' AND h.hostid in ('.$available_hosts.')'.
				' AND t.status='.TRIGGER_STATUS_ENABLED.
				' AND t.triggerid=f.triggerid '.
				' AND '.DBin_node('t.triggerid').
				' AND i.status='.ITEM_STATUS_ACTIVE.
				' AND h.status='.HOST_STATUS_MONITORED.
				$sql_cond.
			' ORDER BY h.host, t.description');

		
		$accessible_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY);

		$table = new CTableInfo();
		$table->setHeader(array(is_show_subnodes() ? S_NODE : null,(($_REQUEST['hostid'] == 0) || (1 == $config))?S_HOST:NULL, S_NAME,S_TRUE,S_FALSE,S_UNKNOWN,S_GRAPH));
		while($row=DBfetch($result)){
			if(!check_right_on_trigger_by_triggerid(null, $row['triggerid'], $accessible_hosts)) continue;

			$availability = calculate_availability($row['triggerid'],0,0);

			$true	= new CSpan(sprintf("%.4f%%",$availability['true']), 'on');
			$false	= new CSpan(sprintf("%.4f%%",$availability['false']), 'off');
			$unknown= new CSpan(sprintf("%.4f%%",$availability['unknown']), 'unknown');
			$actions= new CLink(S_SHOW,'report2.php?hostid='.$_REQUEST['hostid'].'&triggerid='.$row['triggerid'],'action');

			$table->addRow(array(
				get_node_name_by_elid($row['hostid']),
				(($_REQUEST['hostid'] == 0) || (1 == $config))?$row['host']:NULL,
				new CLink(
					expand_trigger_description_by_data($row),
					'events.php?triggerid='.$row['triggerid'],'action'),
				$true,
				$false,
				$unknown,
				$actions
				));
		}
		$table->show();
	}
?>
<?php
	
	include_once 'include/page_footer.php';

?>
