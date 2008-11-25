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
	$page['scripts'] = array('calendar.js');
	
	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	
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
		
		'triggerid'=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,			NULL),
		
// filter
		"filter_rst"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		"filter_set"=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),
		
		'filter_timesince'=>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),
		'filter_timetill'=>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),

//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})'),
	);

	check_fields($fields);

/* AJAX */	
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			update_profile('web.avail_report.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
	
//--------
/* FILTER */
	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['filter_timesince'] = 0;
		$_REQUEST['filter_timetill'] = 0;
	}
	
	$_REQUEST['filter_timesince'] = get_request('filter_timesince',get_profile('web.avail_report.filter.timesince',0));
	$_REQUEST['filter_timetill'] = get_request('filter_timetill',get_profile('web.avail_report.filter.timetill',0));
	
	if(($_REQUEST['filter_timetill'] > 0) && ($_REQUEST['filter_timesince'] > $_REQUEST['filter_timetill'])){
		$tmp = $_REQUEST['filter_timesince'];
		$_REQUEST['filter_timesince'] = $_REQUEST['filter_timetill'];
		$_REQUEST['filter_timetill'] = $tmp;
	}
	
	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		update_profile('web.avail_report.filter.timesince',$_REQUEST['filter_timesince'], PROFILE_TYPE_INT);
		update_profile('web.avail_report.filter.timetill',$_REQUEST['filter_timetill'], PROFILE_TYPE_INT);
	}
// --------------

	$config = get_request('config',get_profile('web.avail_report.config',0));
	update_profile('web.avail_report.config',$config, PROFILE_TYPE_INT);
	
	$options = array('allow_all_hosts','always_select_first_host','with_items');

	if(0 == $config){
		array_push($options,'monitored_hosts');
	}
	else{
		array_push($options,'templated_hosts');
	}
		
	if(!$ZBX_WITH_SUBNODES)	array_push($options,'only_current_node');
	
	validate_group_with_host(PERM_READ_LIST,$options);	
?>
<?php

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY);
	$available_triggers = get_accessible_triggers(PERM_READ_ONLY);
	
	show_report2_header($config);
	
	if(isset($_REQUEST['triggerid'])){
		if(uint_in_array($_REQUEST['triggerid'], $available_triggers)){
			$sql = 'SELECT DISTINCT t.*, h.host, h.hostid '.
					' FROM triggers t, functions f, items i, hosts h '.
					' WHERE t.triggerid='.$_REQUEST['triggerid'].
						' AND t.triggerid=f.triggerid '.
						' AND f.itemid=i.itemid '.
						' AND i.hostid=h.hostid ';
			$trigger_data = DBfetch(DBselect($sql));
		}
		else{
			unset($_REQUEST['triggerid']);
		}
	}
	

	if(isset($_REQUEST['triggerid'])){	
		show_table_header(array(new CLink($trigger_data['host'],'?hostid='.$trigger_data['hostid']),' : "',expand_trigger_description_by_data($trigger_data),'"'));

		$table = new CTableInfo(null,'graph');
		$table->AddRow(new CImg('chart4.php?triggerid='.$_REQUEST['triggerid']));
		$table->Show();
	}
	else if(isset($_REQUEST['hostid'])){
		
/*
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
//*/
/************************* FILTER *************************/
/***********************************************************/	
		$filterForm = new CFormTable(S_FILTER);//,'events.php?filter_set=1','POST',null,'sform');
		$filterForm->AddOption('name','zbx_filter');
		$filterForm->AddOption('id','zbx_filter');
//		$filterForm->SetMethod('post');
			
		$filterForm->AddVar('filter_timesince',($_REQUEST['filter_timesince']>0)?$_REQUEST['filter_timesince']:'');
		$filterForm->AddVar('filter_timetill',($_REQUEST['filter_timetill']>0)?$_REQUEST['filter_timetill']:'');
//*	
		$clndr_icon = new CImg('images/general/bar/cal.gif','calendar', 16, 12, 'pointer');
		$clndr_icon->AddAction('onclick','javascript: '.
											'var pos = getPosition(this); '.
											'pos.top+=10; '.
											'pos.left+=16; '.
											"CLNDR['avail_report_since'].clndr.clndrshow(pos.top,pos.left);");
		
		$filtertimetab = new CTable(null,'calendar');
		$filtertimetab->AddOption('width','10%');
		
		$filtertimetab->SetCellPadding(0);
		$filtertimetab->SetCellSpacing(0);
	
		$filtertimetab->AddRow(array(
								S_FROM, 
								new CNumericBox('filter_since_day',(($_REQUEST['filter_timesince']>0)?date('d',$_REQUEST['filter_timesince']):''),2),
								'/',
								new CNumericBox('filter_since_month',(($_REQUEST['filter_timesince']>0)?date('m',$_REQUEST['filter_timesince']):''),2),
								'/',
								new CNumericBox('filter_since_year',(($_REQUEST['filter_timesince']>0)?date('Y',$_REQUEST['filter_timesince']):''),4),
								SPACE,
								new CNumericBox('filter_since_hour',(($_REQUEST['filter_timesince']>0)?date('H',$_REQUEST['filter_timesince']):''),2),
								':',
								new CNumericBox('filter_since_minute',(($_REQUEST['filter_timesince']>0)?date('i',$_REQUEST['filter_timesince']):''),2),
								$clndr_icon
						));
		zbx_add_post_js('create_calendar(null,'.
						'["filter_since_day","filter_since_month","filter_since_year","filter_since_hour","filter_since_minute"],'.
						'"avail_report_since",'.
						'"filter_timesince");');

		$clndr_icon->AddAction('onclick','javascript: '.
											'var pos = getPosition(this); '.
											'pos.top+=10; '.
											'pos.left+=16; '.
											"CLNDR['avail_report_till'].clndr.clndrshow(pos.top,pos.left);");
											
		$filtertimetab->AddRow(array(
								S_TILL, 
								new CNumericBox('filter_till_day',(($_REQUEST['filter_timetill']>0)?date('d',$_REQUEST['filter_timetill']):''),2),
								'/',
								new CNumericBox('filter_till_month',(($_REQUEST['filter_timetill']>0)?date('m',$_REQUEST['filter_timetill']):''),2),
								'/',
								new CNumericBox('filter_till_year',(($_REQUEST['filter_timetill']>0)?date('Y',$_REQUEST['filter_timetill']):''),4),
								SPACE,
								new CNumericBox('filter_till_hour',(($_REQUEST['filter_timetill']>0)?date('H',$_REQUEST['filter_timetill']):''),2),
								':',
								new CNumericBox('filter_till_minute',(($_REQUEST['filter_timetill']>0)?date('i',$_REQUEST['filter_timetill']):''),2),
								$clndr_icon
						));
		zbx_add_post_js('create_calendar(null,'.
				'["filter_till_day","filter_till_month","filter_till_year","filter_till_hour","filter_till_minute"],'.
				'"avail_report_till",'.
				'"filter_timetill");');
		
		zbx_add_post_js('addListener($("filter_icon"),"click",CLNDR[\'avail_report_since\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'avail_report_since\'].clndr));'.
						'addListener($("filter_icon"),"click",CLNDR[\'avail_report_till\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'avail_report_till\'].clndr));'
						);
		
		$filterForm->AddRow(S_PERIOD, $filtertimetab);
	//*/	
		$filterForm->AddItemToBottomRow(new CButton("filter_set",S_FILTER));
		
		$reset = new CButton("filter_rst",S_RESET);
		$reset->SetType('button');
		$reset->SetAction('javascript: var url = new Curl(location.href); url.setArgument("filter_rst",1); location.href = url.getUrl();');
	
		$filterForm->AddItemToBottomRow($reset);
								
		$filter = create_filter(S_FILTER,NULL,$filterForm,'tr_filter',get_profile('web.avail_report.filter.state',0));
		$filter->Show();
//-------

		$sql_from = '';
		$sql_where = '';

		if(0 == $config){
			if($_REQUEST['hostid'] > 0)	
				$sql_where.= ' AND h.hostid='.$_REQUEST['hostid'];
		}
		else{
			if($_REQUEST['groupid'] > 0){
				$sql_from .= ',hosts_groups hg ';
				$sql_where.= ' AND hg.hostid=h.hostid AND hg.groupid='.$_REQUEST['groupid'];
			}
			
			if($_REQUEST['hostid'] > 0){
			
				$sql_from.=',hosts_templates ht ';
				$sql_where.=' AND ht.hostid=h.hostid AND ht.templateid='.$_REQUEST['hostid'];
			}
			
			if(isset($_REQUEST['tpl_triggerid']) && ($_REQUEST['tpl_triggerid'] > 0))
				$sql_where.= ' AND t.templateid='.$_REQUEST['tpl_triggerid'];
		}
		
		$result = DBselect('SELECT DISTINCT h.hostid,h.host,t.triggerid,t.expression,t.description,t.value '.
			' FROM triggers t,hosts h,items i,functions f '.$sql_from.
			' WHERE h.status='.HOST_STATUS_MONITORED.
				' AND '.DBcondition('h.hostid',$available_hosts).
				' AND i.hostid=h.hostid '.
				' AND i.status='.ITEM_STATUS_ACTIVE.
				' AND f.itemid=i.itemid '.
				' AND t.triggerid=f.triggerid '.
				' AND t.status='.TRIGGER_STATUS_ENABLED.
				$sql_where.
			' ORDER BY h.host, t.description');

		
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY);

		$table = new CTableInfo();
		$table->setHeader(
				array(is_show_subnodes()?S_NODE : null,
				(($_REQUEST['hostid'] == 0) || (1 == $config))?S_HOST:NULL, 
				S_NAME,
				S_TRUE,
				S_FALSE,
				S_UNKNOWN,
				S_GRAPH));
				
		while($row=DBfetch($result)){
			if(!check_right_on_trigger_by_triggerid(null, $row['triggerid'])) continue;

			$availability = calculate_availability($row['triggerid'],$_REQUEST['filter_timesince'],$_REQUEST['filter_timetill']);

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

include_once 'include/page_footer.php';
?>