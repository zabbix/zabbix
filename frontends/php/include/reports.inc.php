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

function show_report2_header($config){
	global $USER_DETAILS;
	
	$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY);
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY);
	
	$r_form = new CForm();
	$r_form->setMethod('get');
	
	$cmbConf = new CComboBox('config',$config,'submit()');
	$cmbConf->addItem(0,S_BY_HOST);
	$cmbConf->addItem(1,S_BY_TRIGGER_TEMPLATE);

	$r_form->addItem(array(S_MODE.SPACE,$cmbConf,SPACE));

	$cmbGroup = new CComboBox('groupid',$_REQUEST['groupid'],'submit()');
	$cmbGroup->addItem(0,S_ALL_SMALL);
	
	$status_filter=($config==1)?' AND h.status='.HOST_STATUS_TEMPLATE:' AND h.status='.HOST_STATUS_MONITORED;
	
	$sql = 'SELECT DISTINCT g.groupid,g.name '.
			' FROM groups g,hosts_groups hg,hosts h'.
			' WHERE '.DBcondition('h.hostid',$available_hosts).
				' AND '.DBcondition('g.groupid',$available_groups).
				' AND g.groupid=hg.groupid '.
				' AND h.hostid=hg.hostid'.
				$status_filter.
			' ORDER BY g.name';

	$result=DBselect($sql);
	while($row=DBfetch($result)){
		$cmbGroup->addItem($row['groupid'],	get_node_name_by_elid($row['groupid']).$row['name']);
	}
	$r_form->addItem(array(S_GROUP.SPACE,$cmbGroup));


	$sql_from = '';
	$sql_where = '';

	if(0 == $config){
		$cmbHosts = new CComboBox('hostid',$_REQUEST['hostid'],'submit()');
		$sql_where = ' AND h.status='.HOST_STATUS_MONITORED;
	}
	else{
		$cmbTpls = new CComboBox('hostid',$_REQUEST['hostid'],'submit()');
		$cmbTrigs = new CComboBox('tpl_triggerid',get_request('tpl_triggerid',0),'submit()');
		$cmbHGrps = new CComboBox('hostgroupid',get_request('hostgroupid',0),'submit()');
		
		$cmbTrigs->addItem(0,S_ALL_SMALL);
		$cmbHGrps->addItem(0,S_ALL_SMALL);
		
		$sql_where = ' AND h.status='.HOST_STATUS_TEMPLATE;		
	}
	
	
	if($_REQUEST['groupid'] > 0){
		$sql_from .= ',hosts_groups hg ';
		$sql_where.= ' AND hg.hostid=h.hostid AND hg.groupid='.$_REQUEST['groupid'];
	}
	else{
		if(0 == $config){
			$cmbHosts->addItem(0,S_ALL_SMALL);
		}
		else{
			$cmbTpls->addItem(0,S_ALL_SMALL);
		}		
	}
	
	$sql='SELECT DISTINCT h.hostid,h.host '.
		' FROM hosts h,items i '.$sql_from.
		' WHERE '.DBcondition('h.hostid',$available_hosts).
			$sql_where.
			' AND i.hostid=h.hostid '.
		' ORDER BY h.host';

	$result=DBselect($sql);
	while($row=DBfetch($result)){
		if(0 == $config){
			$cmbHosts->addItem($row['hostid'],get_node_name_by_elid($row['hostid']).$row['host']);
		}
		else{
			$cmbTpls->addItem($row['hostid'],get_node_name_by_elid($row['hostid']).$row['host']);
		}
	}

	
	if(0 == $config){
		$r_form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
		show_table_header(S_AVAILABILITY_REPORT_BIG, $r_form);
	}
	else{
		$r_form->addItem(array(SPACE.S_TEMPLATE.SPACE,$cmbTpls));

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

function bar_report_form(){
	global $USER_DETAILS;
	
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_ONLY);
	
	$config = get_request('config',1);
	$items = get_request('items',array());
	$function_type = get_request('function_type',CALC_FNC_AVG);
	$scaletype = get_request('scaletype',TIMEPERIOD_TYPE_WEEKLY);
	
	$title = get_request('title','Report 1');
	$xlabel = get_request('xlabel','');
	$ylabel = get_request('ylabel','');
	$showlegend = get_request('showlegend',0);
	
//	$showLegend = 
	
	$report_timesince = get_request('report_timesince',time()-86400);
	$report_timetill = get_request('report_timetill',time());
	
	$reportForm = new CFormTable(S_REPORTS,null,'get');//,'events.php?report_set=1','POST',null,'sform');
	$reportForm->addOption('name','zbx_report');
	$reportForm->addOption('id','zbx_report');

//	$reportForm->setMethod('post');		
	if(isset($_REQUEST['report_show']) && !empty($items))
		$reportForm->addVar('report_show','show');
	
	$reportForm->addVar('config',$config);	
	$reportForm->addVar('items',$items);
	$reportForm->addVar('report_timesince',($report_timesince>0)?$report_timesince:'');
	$reportForm->addVar('report_timetill',($report_timetill>0)?$report_timetill:'');

	$reportForm->addRow(S_TITLE, new CTextBox('title',$title,40));
	$reportForm->addRow(S_X.SPACE.S_LABEL, new CTextBox('xlabel',$xlabel,40));
	$reportForm->addRow(S_Y.SPACE.S_LABEL, new CTextBox('ylabel',$ylabel,40));
	$reportForm->addRow(S_LEGEND, new CCheckBox('showlegend',$showlegend,null,1));

	$scale = new CComboBox('scaletype', $scaletype);
		$scale->addItem(TIMEPERIOD_TYPE_HOURLY, S_HOURLY);
		$scale->addItem(TIMEPERIOD_TYPE_DAILY, 	S_DAILY);
		$scale->addItem(TIMEPERIOD_TYPE_WEEKLY,	S_WEEKLY);
		$scale->addItem(TIMEPERIOD_TYPE_MONTHLY,S_MONTHLY);
		$scale->addItem(TIMEPERIOD_TYPE_YEARLY,	S_YEARLY);
	$reportForm->addRow(S_SCALE, $scale);
	
//*	

	$clndr_icon = new CImg('images/general/bar/cal.gif','calendar', 16, 12, 'pointer');
	$clndr_icon->addAction('onclick','javascript: '.
										'var pos = getPosition(this); '.
										'pos.top+=10; '.
										'pos.left+=16; '.
										"CLNDR['avail_report_since'].clndr.clndrshow(pos.top,pos.left);");
	
	$reporttimetab = new CTable(null,'calendar');
	$reporttimetab->addOption('width','10%');
	
	$reporttimetab->setCellPadding(0);
	$reporttimetab->setCellSpacing(0);

	$reporttimetab->addRow(array(
							S_FROM, 
							new CNumericBox('report_since_day',(($report_timesince>0)?date('d',$report_timesince):''),2),
							'/',
							new CNumericBox('report_since_month',(($report_timesince>0)?date('m',$report_timesince):''),2),
							'/',
							new CNumericBox('report_since_year',(($report_timesince>0)?date('Y',$report_timesince):''),4),
							SPACE,
							new CNumericBox('report_since_hour',(($report_timesince>0)?date('H',$report_timesince):''),2),
							':',
							new CNumericBox('report_since_minute',(($report_timesince>0)?date('i',$report_timesince):''),2),
							$clndr_icon
					));
	zbx_add_post_js('create_calendar(null,'.
					'["report_since_day","report_since_month","report_since_year","report_since_hour","report_since_minute"],'.
					'"avail_report_since",'.
					'"report_timesince");');

	$clndr_icon->addAction('onclick','javascript: '.
										'var pos = getPosition(this); '.
										'pos.top+=10; '.
										'pos.left+=16; '.
										"CLNDR['avail_report_till'].clndr.clndrshow(pos.top,pos.left);");
										
	$reporttimetab->addRow(array(
							S_TILL, 
							new CNumericBox('report_till_day',(($report_timetill>0)?date('d',$report_timetill):''),2),
							'/',
							new CNumericBox('report_till_month',(($report_timetill>0)?date('m',$report_timetill):''),2),
							'/',
							new CNumericBox('report_till_year',(($report_timetill>0)?date('Y',$report_timetill):''),4),
							SPACE,
							new CNumericBox('report_till_hour',(($report_timetill>0)?date('H',$report_timetill):''),2),
							':',
							new CNumericBox('report_till_minute',(($report_timetill>0)?date('i',$report_timetill):''),2),
							$clndr_icon
					));
					
	zbx_add_post_js('create_calendar(null,'.
					'["report_till_day","report_till_month","report_till_year","report_till_hour","report_till_minute"],'.
					'"avail_report_till",'.
					'"report_timetill");'
					);
	
	zbx_add_post_js('addListener($("filter_icon"),'.
						'"click",'.
						'CLNDR[\'avail_report_since\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'avail_report_since\'].clndr));'.
					'addListener($("filter_icon"),'.
						'"click",'.
						'CLNDR[\'avail_report_till\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'avail_report_till\'].clndr));'
					);
	
	$reportForm->addRow(S_PERIOD, $reporttimetab);
//*/	
	
	if(count($items)){
		
		$items_table = new CTableInfo();
		foreach($items as $gid => $gitem){

			$host = get_host_by_itemid($gitem['itemid']);
			$item = get_item_by_itemid($gitem['itemid']);

			if($host['status'] == HOST_STATUS_TEMPLATE) $only_hostid = $host['hostid'];
			else $monitored_hosts = 1;

			$color = new CColorCell(null,$gitem['color']);

			$description = new CLink($host['host'].': '.item_description($item),'#','action');
			$description->onClick(
					'return PopUp("popup_bitem.php?config=1&list_name=items&dstfrm='.$reportForm->GetName().
					url_param($gitem, false).
					url_param($gid,false,'gid').
					'",550,400,"graph_item_form");');


			$items_table->addRow(array(
					new CCheckBox('group_gid['.$gid.']',isset($group_gid[$gid])),
					$gitem['caption'],
					$description,
					graph_item_calc_fnc2str($gitem['calc_fnc'],0),
					$color,
				));
		}
		$delete_button = new CButton('delete_item', S_DELETE_SELECTED);
	}
	else{
		$items_table = $delete_button = null;
	}
	
	$reportForm->addRow(S_ITEMS, 
				array(
					$items_table,
					new CButton('add_item',S_ADD,
						"return PopUp('popup_bitem.php?config=1&dstfrm=".$reportForm->getName().
						"',550,400,'graph_item_form');"),
					$delete_button
				));
	unset($items_table, $delete_button);
	
	$reportForm->addItemToBottomRow(new CButton('report_show',S_SHOW));
	
	$reset = new CButton('reset',S_RESET);
	$reset->setType('reset');	
	$reportForm->addItemToBottomRow($reset);
	
return $reportForm;
}

function bar_report_form2(){
	global $USER_DETAILS;
	
	$config = get_request('config',1);
	
	$title = get_request('title','Report 2');	
	$xlabel = get_request('xlabel','');
	$ylabel = get_request('ylabel','');
	
	$sorttype = get_request('sorttype',0);

	$captions = get_request('captions',array());	
	$items = get_request('items',array());
	$periods = get_request('periods',array());

	$showlegend = get_request('showlegend',0);
	
	$reportForm = new CFormTable(S_REPORTS,null,'get');//,'events.php?report_set=1','POST',null,'sform');
	$reportForm->addOption('name','zbx_report');
	$reportForm->addOption('id','zbx_report');

//	$reportForm->setMethod('post');		
	if(isset($_REQUEST['report_show']) && !empty($items))
		$reportForm->addVar('report_show','show');
	
	$reportForm->addVar('config',$config);	
	$reportForm->addVar('items',$items);
// periods add later
	
	$reportForm->addRow(S_TITLE, new CTextBox('title',$title,40));
	$reportForm->addRow(S_X.SPACE.S_LABEL, new CTextBox('xlabel',$xlabel,40));
	$reportForm->addRow(S_Y.SPACE.S_LABEL, new CTextBox('ylabel',$ylabel,40));

	$reportForm->addRow(S_LEGEND, new CCheckBox('showlegend',$showlegend,null,1));
	
	if(count($periods) < 2){
		$sortCmb = new CComboBox('sorttype', $sorttype);
			$sortCmb->addItem(0, S_NAME);
			$sortCmb->addItem(1, S_VALUE);
		
		$reportForm->addRow(S_SORT_BY,$sortCmb);
	}
	else{
		$reportForm->addVar('sortorder',0);
	}
		
//*/	
// PERIODS
	if(count($periods)){
		$periods_table = new CTableInfo();
		foreach($periods as $pid => $period){			
			$color = new CColorCell(null,$period['color']);
			
			$edit_link = 'popup_period.php?period_id='.$pid.
							'&config=2'.
							'&dstfrm='.$reportForm->getName().
							'&caption='.$period['caption'].
							'&report_timesince='.$period['report_timesince'].
							'&report_timetill='.$period['report_timetill'].
							'&color='.$period['color'];
				
			$caption = new CSpan($period['caption'], 'link');
			$caption->addAction('onclick', "return PopUp('".$edit_link."',840,340,'period_form');");
			
			$periods_table->addRow(array(
					new CCheckBox('group_pid['.$pid.']'),
					$caption,
					date(S_DATE_FORMAT_YMDHMS, $period['report_timesince']),
					date(S_DATE_FORMAT_YMDHMS, $period['report_timetill']),
					$color,
				));
		}
		$delete_button = new CButton('delete_period', S_DELETE_SELECTED);
	}
	else{
		$periods_table = $delete_button = null;
	}
	
	$reportForm->addVar('periods',$periods);

	$reportForm->addRow(S_PERIOD, 
				array(
					$periods_table,
					new CButton('add_period',S_ADD,
						"return PopUp('popup_period.php?config=2&dstfrm=".$reportForm->getName()."',840,340,'period_form');"),
					$delete_button
				));
	unset($periods_table, $delete_button);
//-----------

// ITEMS
	if(count($items)){
		$items_table = new CTableInfo();
		foreach($items as $gid => $gitem){

			$host = get_host_by_itemid($gitem['itemid']);
			$item = get_item_by_itemid($gitem['itemid']);

			if($host['status'] == HOST_STATUS_TEMPLATE) $only_hostid = $host['hostid'];
			else $monitored_hosts = 1;

			$description = new CLink($host['host'].': '.item_description($item),'#','action');
			$description->onClick(
					'return PopUp("popup_bitem.php?config=2&list_name=items&dstfrm='.$reportForm->GetName().
					url_param($gitem, false).
					url_param($gid,false,'gid').
					'",550,400,"graph_item_form");');


			$items_table->addRow(array(
					new CCheckBox('group_gid['.$gid.']',isset($group_gid[$gid])),
					$gitem['caption'],
					$description,
					graph_item_calc_fnc2str($gitem['calc_fnc'],0),
				));
		}
		$delete_button = new CButton('delete_item', S_DELETE_SELECTED);
	}
	else{
		$items_table = $delete_button = null;
	}
	
	$reportForm->addRow(S_ITEMS, 
				array(
					$items_table,
					new CButton('add_item',S_ADD,
						"return PopUp('popup_bitem.php?config=2&dstfrm=".$reportForm->getName().
						"',550,400,'graph_item_form');"),
					$delete_button
				));
	unset($items_table, $delete_button);
//--------------
	
	
	$reportForm->addItemToBottomRow(new CButton('report_show',S_SHOW));
	
	$reset = new CButton('reset',S_RESET);
	$reset->setType('reset');	
	$reportForm->addItemToBottomRow($reset);
	
return $reportForm;
}

function bar_report_form3(){
	global $USER_DETAILS;
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY);
	
	$config = get_request('config',1);
	
	$title = get_request('title','Report 2');	
	$xlabel = get_request('xlabel','');
	$ylabel = get_request('ylabel','');
	
	$sorttype = get_request('sorttype',0);
	$scaletype = get_request('scaletype', TIMEPERIOD_TYPE_WEEKLY);
	$avgperiod = get_request('avgperiod', TIMEPERIOD_TYPE_DAILY);

	$report_timesince = get_request('report_timesince',time()-86400);
	$report_timetill = get_request('report_timetill',time());

	$captions = get_request('captions',array());	
	$items = get_request('items',array());

	$hostids = get_request('hostids', array());
	$showlegend = get_request('showlegend',0);

	$reportForm = new CFormTable(S_REPORTS,null,'get');//,'events.php?report_set=1','POST',null,'sform');
	$reportForm->addOption('name','zbx_report');
	$reportForm->addOption('id','zbx_report');

//	$reportForm->setMethod('post');		
	if(isset($_REQUEST['report_show']) && !empty($items))
		$reportForm->addVar('report_show','show');
	
	$reportForm->addVar('config',$config);	
	$reportForm->addVar('report_timesince',($report_timesince>0)?$report_timesince:'');
	$reportForm->addVar('report_timetill',($report_timetill>0)?$report_timetill:'');

//	$reportForm->addVar('items',$items); 				//params are set later!!
//	$reportForm->addVar('periods',$periods);
	
	$reportForm->addRow(S_TITLE, new CTextBox('title',$title,40));
	$reportForm->addRow(S_X.SPACE.S_LABEL, new CTextBox('xlabel',$xlabel,40));
	$reportForm->addRow(S_Y.SPACE.S_LABEL, new CTextBox('ylabel',$ylabel,40));

	$reportForm->addRow(S_LEGEND, new CCheckBox('showlegend',$showlegend,null,1));
	$reportForm->addVar('sortorder',0);

// GROUPS
	$groupids = get_request('groupids', array());
	$group_tb = new CTweenBox($reportForm,'groupids',null,10);	

	$sql_from = '';
	$sql_where =  'AND '.DBcondition('g.groupid',$groupids);
	
	$sql = 'SELECT DISTINCT g.groupid, g.name '.
			' FROM hosts h, hosts_groups hg, groups g '.$sql_from.
			' WHERE hg.groupid=g.groupid'.
				' AND h.hostid=hg.hostid '.
				' AND '.DBcondition('h.hostid',$available_hosts).
				' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
				$sql_where.
			' ORDER BY g.name';
//SDI($sql);
	$db_groups = DBselect($sql);
	while($group = DBfetch($db_groups)){
		$groupids[$group['groupid']] = $group['groupid'];			
		$group_tb->addItem($group['groupid'],$group['name'], true);			
	}

	$sql = 'SELECT DISTINCT g.* '.
			' FROM hosts h, hosts_groups hg, groups g '.
			' WHERE hg.groupid=g.groupid'.
				' AND h.hostid=hg.hostid '.
				' AND '.DBcondition('h.hostid',$available_hosts).
				' AND '.DBcondition('g.groupid',$groupids,true).
				' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
			' ORDER BY g.name';
	$db_groups = DBselect($sql);
	while($group = DBfetch($db_groups)){
		$group_tb->addItem($group['groupid'],$group['name'], false);			
	}

	$reportForm->addRow(S_GROUPS, $group_tb->Get(S_SELECTED_GROUPS,S_OTHER.SPACE.S_GROUPS));
// ----------

// HOSTS
	validate_group(PERM_READ_ONLY,array('real_hosts'),'web.last.conf.groupid');
	
	$cmbGroups = new CComboBox('groupid',get_request('groupid',0),'submit()');
	$cmbGroups->addItem(0,S_ALL_S);
	$sql = 'SELECT DISTINCT g.groupid,g.name '.
			' FROM groups g,hosts_groups hg,hosts h '.
			' WHERE '.DBcondition('h.hostid',$available_hosts).
				' AND g.groupid=hg.groupid '.
				' AND h.hostid=hg.hostid'.
				' AND h.status IN ('.HOST_STATUS_MONITORED.') '.
			' ORDER BY g.name';

	$result=DBselect($sql);
	while($row=DBfetch($result)){
		$cmbGroups->addItem($row['groupid'],$row['name']);
	}
	
	$td_groups = new CCol(array(S_GROUP,SPACE,$cmbGroups));
	$td_groups->addOption('style','text-align: right;');

	$host_tb = new CTweenBox($reportForm,'hostids',null,10);	

	$sql_from = '';
	$sql_where =  'AND '.DBcondition('h.hostid',$hostids);
	
	$sql = 'SELECT DISTINCT h.hostid, h.host '.
			' FROM hosts h '.$sql_from.
			' WHERE '.DBcondition('h.hostid',$available_hosts).
				$sql_where.
				' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
			' ORDER BY h.host';
	$db_hosts = DBselect($sql);
	while($host = DBfetch($db_hosts)){
		$hostids[$host['hostid']] = $host['hostid'];			
		$host_tb->addItem($host['hostid'],$host['host'], true);			
	}


	$sql_from = '';
	$sql_where = '';
	if(isset($_REQUEST['groupid']) && ($_REQUEST['groupid']>0)){
		$sql_from .= ', hosts_groups hg ';
		$sql_where .= ' AND hg.groupid='.$_REQUEST['groupid'].
						' AND h.hostid=hg.hostid ';
	}
	
	$sql = 'SELECT DISTINCT h.* '.
			' FROM hosts h '.$sql_from.
			' WHERE '.DBcondition('h.hostid',$available_hosts).
				' AND '.DBcondition('h.hostid',$hostids,true).
				' AND h.status IN ('.HOST_STATUS_MONITORED.') '.
				$sql_where.
			' ORDER BY h.host';
	$db_hosts = DBselect($sql);
	while($host = DBfetch($db_hosts)){
		$host_tb->addItem($host['hostid'],$host['host'], false);			
	}

	$reportForm->addRow(S_HOSTS, $host_tb->Get(S_SELECTED_HOSTS,array(S_OTHER.SPACE.S_HOSTS.SPACE.'|'.SPACE.S_GROUP.SPACE,$cmbGroups)));
// ----------

		
//*/	
// PERIOD

	$clndr_icon = new CImg('images/general/bar/cal.gif','calendar', 16, 12, 'pointer');
	$clndr_icon->addAction('onclick','javascript: '.
										'var pos = getPosition(this); '.
										'pos.top+=10; '.
										'pos.left+=16; '.
										"CLNDR['avail_report_since'].clndr.clndrshow(pos.top,pos.left);");
	
	$reporttimetab = new CTable(null,'calendar');
	$reporttimetab->addOption('width','10%');
	
	$reporttimetab->setCellPadding(0);
	$reporttimetab->setCellSpacing(0);

	$reporttimetab->addRow(array(
							S_FROM, 
							new CNumericBox('report_since_day',(($report_timesince>0)?date('d',$report_timesince):''),2),
							'/',
							new CNumericBox('report_since_month',(($report_timesince>0)?date('m',$report_timesince):''),2),
							'/',
							new CNumericBox('report_since_year',(($report_timesince>0)?date('Y',$report_timesince):''),4),
							SPACE,
							new CNumericBox('report_since_hour',(($report_timesince>0)?date('H',$report_timesince):''),2),
							':',
							new CNumericBox('report_since_minute',(($report_timesince>0)?date('i',$report_timesince):''),2),
							$clndr_icon
					));
	zbx_add_post_js('create_calendar(null,'.
					'["report_since_day","report_since_month","report_since_year","report_since_hour","report_since_minute"],'.
					'"avail_report_since",'.
					'"report_timesince");');

	$clndr_icon->addAction('onclick','javascript: '.
										'var pos = getPosition(this); '.
										'pos.top+=10; '.
										'pos.left+=16; '.
										"CLNDR['avail_report_till'].clndr.clndrshow(pos.top,pos.left);");
										
	$reporttimetab->addRow(array(
							S_TILL, 
							new CNumericBox('report_till_day',(($report_timetill>0)?date('d',$report_timetill):''),2),
							'/',
							new CNumericBox('report_till_month',(($report_timetill>0)?date('m',$report_timetill):''),2),
							'/',
							new CNumericBox('report_till_year',(($report_timetill>0)?date('Y',$report_timetill):''),4),
							SPACE,
							new CNumericBox('report_till_hour',(($report_timetill>0)?date('H',$report_timetill):''),2),
							':',
							new CNumericBox('report_till_minute',(($report_timetill>0)?date('i',$report_timetill):''),2),
							$clndr_icon
					));
					
	zbx_add_post_js('create_calendar(null,'.
					'["report_till_day","report_till_month","report_till_year","report_till_hour","report_till_minute"],'.
					'"avail_report_till",'.
					'"report_timetill");'
					);
	
	zbx_add_post_js('addListener($("filter_icon"),'.
						'"click",'.
						'CLNDR[\'avail_report_since\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'avail_report_since\'].clndr));'.
					'addListener($("filter_icon"),'.
						'"click",'.
						'CLNDR[\'avail_report_till\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'avail_report_till\'].clndr));'
					);
	
	$reportForm->addRow(S_PERIOD, $reporttimetab);
//-----------

	$scale = new CComboBox('scaletype', $scaletype);
		$scale->addItem(TIMEPERIOD_TYPE_HOURLY, S_HOURLY);
		$scale->addItem(TIMEPERIOD_TYPE_DAILY, 	S_DAILY);
		$scale->addItem(TIMEPERIOD_TYPE_WEEKLY,	S_WEEKLY);
		$scale->addItem(TIMEPERIOD_TYPE_MONTHLY,S_MONTHLY);
		$scale->addItem(TIMEPERIOD_TYPE_YEARLY,	S_YEARLY);
	$reportForm->addRow(S_SCALE, $scale);
	
	$avgcmb = new CComboBox('avgperiod', $avgperiod);
		$avgcmb->addItem(TIMEPERIOD_TYPE_HOURLY,	S_HOURLY);
		$avgcmb->addItem(TIMEPERIOD_TYPE_DAILY, 	S_DAILY);
		$avgcmb->addItem(TIMEPERIOD_TYPE_WEEKLY,	S_WEEKLY);
		$avgcmb->addItem(TIMEPERIOD_TYPE_MONTHLY, 	S_MONTHLY);
		$avgcmb->addItem(TIMEPERIOD_TYPE_YEARLY,	S_YEARLY);
	$reportForm->addRow(S_AVERAGE_BY, $avgcmb);

// ITEMS
	$itemid = 0;
	$description = '';
	if(count($items) && ($items[0]['itemid'] > 0)){
		$itemid = $items[0]['itemid'];
		$description = get_item_by_itemid($itemid);
		$description = item_description($description);
	}
	$reportForm->addVar('items[0][itemid]',$itemid);
	
	$txtCondVal = new CTextBox('items[0][description]',$description,50,'yes');
	$btnSelect = new CButton('btn1',S_SELECT,
			"return PopUp('popup.php?dstfrm=".$reportForm->GetName().
			"&dstfld1=items[0][itemid]&dstfld2=items[0][description]&".
			"srctbl=items&srcfld1=itemid&srcfld2=description&monitored_hosts=1');",
			'T');
			
	$reportForm->addRow(S_ITEM , array($txtCondVal,$btnSelect));
			
//--------------
	
	
	$reportForm->addItemToBottomRow(new CButton('report_show',S_SHOW));
	
	$reset = new CButton('reset',S_RESET);
	$reset->setType('reset');	
	$reportForm->addItemToBottomRow($reset);
	
return $reportForm;
}
?>