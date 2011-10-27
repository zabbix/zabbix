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

function get_report2_filter($config,&$PAGE_GROUPS, &$PAGE_HOSTS){
	global $USER_DETAILS;

	$available_groups = $PAGE_GROUPS['groupids'];
	$available_hosts = $PAGE_HOSTS['hostids'];


/************************* FILTER *************************/
/***********************************************************/
	$filterForm = new CFormTable();//,'events.php?filter_set=1','POST',null,'sform');
	$filterForm->setAttribute('name','zbx_filter');
	$filterForm->setAttribute('id','zbx_filter');

	$filterForm->addVar('config',$config);
	$filterForm->addVar('filter_timesince',date('YmdHis', $_REQUEST['filter_timesince']));
	$filterForm->addVar('filter_timetill', date('YmdHis', $_REQUEST['filter_timetill']));

	$cmbGroups = new CComboBox('filter_groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
	$cmbHosts = new CComboBox('filter_hostid',$PAGE_HOSTS['selected'],'javascript: submit();');

	foreach($PAGE_GROUPS['groups'] as $groupid => $name){
		$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid, null, ': ').$name);
	}
	foreach($PAGE_HOSTS['hosts'] as $hostid => $name){
		$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid, null, ': ').$name);
	}

	$filterForm->addRow(S_GROUP,$cmbGroups);
	$filterForm->addRow(S_HOST,$cmbHosts);

	if(1 == $config){
		$cmbTrigs = new CComboBox('tpl_triggerid',get_request('tpl_triggerid',0),'submit()');
		$cmbHGrps = new CComboBox('hostgroupid',get_request('hostgroupid',0),'submit()');

		$cmbTrigs->addItem(0,S_ALL_SMALL);
		$cmbHGrps->addItem(0,S_ALL_SMALL);

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
				get_node_name_by_elid($row['groupid'], null, ': ').$row['name']
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
					get_node_name_by_elid($row['triggerid'], null, ': ').expand_trigger_description($row['triggerid'])
					);
		}

		$filterForm->addRow(S_TRIGGER,$cmbTrigs);
		$filterForm->addRow(S_FILTER.SPACE.S_HOST_GROUP,$cmbHGrps);
	}

//*
	$clndr_icon = new CImg('images/general/bar/cal.gif','calendar', 16, 12, 'pointer');
	$clndr_icon->addAction('onclick','javascript: '.
										'var pos = getPosition(this); '.
										'pos.top+=10; '.
										'pos.left+=16; '.
										"CLNDR['avail_report_since'].clndr.clndrshow(pos.top,pos.left);");

	$filtertimetab = new CTable(null,'calendar');
	$filtertimetab->setAttribute('width','10%');

	$filtertimetab->setCellPadding(0);
	$filtertimetab->setCellSpacing(0);

	$filtertimetab->addRow(array(
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

	$filterForm->addRow(S_PERIOD, $filtertimetab);

//*/
	$filterForm->addItemToBottomRow(new CButton('filter_set',S_FILTER));

	$reset = new CButton("filter_rst",S_RESET);
	$reset->setType('button');
	$reset->setAction('javascript: var url = new Curl(location.href); url.setArgument("filter_rst",1); location.href = url.getUrl();');

	$filterForm->addItemToBottomRow($reset);

return $filterForm;
}

function bar_report_form(){
	global $USER_DETAILS;

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_ONLY);

	$config = get_request('config',1);
	$items = get_request('items',array());
	$function_type = get_request('function_type',CALC_FNC_AVG);
	$scaletype = get_request('scaletype',TIMEPERIOD_TYPE_WEEKLY);

	$title = get_request('title',S_REPORT.' 1');
	$xlabel = get_request('xlabel','');
	$ylabel = get_request('ylabel','');
	$showlegend = get_request('showlegend',0);

//	$showLegend =

	$report_timesince = $_REQUEST['report_timesince'];
	$report_timetill = $_REQUEST['report_timetill'];

	$reportForm = new CFormTable(null,null,'get');//,'events.php?report_set=1','POST',null,'sform');
	$reportForm->setAttribute('name','zbx_report');
	$reportForm->setAttribute('id','zbx_report');

//	$reportForm->setMethod('post');
	if(isset($_REQUEST['report_show']) && !empty($items))
		$reportForm->addVar('report_show','show');

	$reportForm->addVar('config',$config);
	$reportForm->addVar('items',$items);
	$reportForm->addVar('report_timesince', date('YmdHis', $report_timesince));
	$reportForm->addVar('report_timetill',  date('YmdHis', $report_timetill));

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
	$reporttimetab->setAttribute('width','10%');

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

			$caption = new CSpan($gitem['caption'], 'link');
			$caption->onClick(
					'return PopUp("popup_bitem.php?config=1&list_name=items&dstfrm='.$reportForm->GetName().
					url_param($gitem, false).
					url_param($gid,false,'gid').
					'",550,400,"graph_item_form");');

			$description = $host['host'].': '.item_description($item);

			$items_table->addRow(array(
					new CCheckBox('group_gid['.$gid.']',isset($group_gid[$gid])),
					$caption,
					$description,
					graph_item_calc_fnc2str($gitem['calc_fnc'],0),
					($gitem['axisside']==GRAPH_YAXIS_SIDE_LEFT)?S_LEFT:S_RIGHT,
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
						"',800,400,'graph_item_form');"),
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

	$title = get_request('title',S_REPORT.' 2');
	$xlabel = get_request('xlabel','');
	$ylabel = get_request('ylabel','');

	$sorttype = get_request('sorttype',0);

	$captions = get_request('captions',array());
	$items = get_request('items',array());
	$periods = get_request('periods',array());

	$showlegend = get_request('showlegend',0);

	$reportForm = new CFormTable(null,null,'get');//,'events.php?report_set=1','POST',null,'sform');
	$reportForm->setAttribute('name','zbx_report');
	$reportForm->setAttribute('id','zbx_report');

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
					zbx_date2str(S_REPORTS_BAR_REPORT_DATE_FORMAT, $period['report_timesince']),
					zbx_date2str(S_REPORTS_BAR_REPORT_DATE_FORMAT, $period['report_timetill']),
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

			$caption = new CSpan($gitem['caption'], 'link');
			$caption->onClick(
					'return PopUp("popup_bitem.php?config=2&list_name=items&dstfrm='.$reportForm->GetName().
					url_param($gitem, false).
					url_param($gid,false,'gid').
					'",550,400,"graph_item_form");');

			$description = $host['host'].': '.item_description($item);

			$items_table->addRow(array(
					new CCheckBox('group_gid['.$gid.']',isset($group_gid[$gid])),
					$caption,
					$description,
					graph_item_calc_fnc2str($gitem['calc_fnc'],0)
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

	$title = get_request('title',S_REPORT.' 3');
	$xlabel = get_request('xlabel','');
	$ylabel = get_request('ylabel','');

	$sorttype = get_request('sorttype',0);
	$scaletype = get_request('scaletype', TIMEPERIOD_TYPE_WEEKLY);
	$avgperiod = get_request('avgperiod', TIMEPERIOD_TYPE_DAILY);

	$report_timesince = get_request('report_timesince',date('YmdHis', time()-86400));
	$report_timetill = get_request('report_timetill',date('YmdHis'));

	$captions = get_request('captions',array());
	$items = get_request('items',array());

	$hostids = get_request('hostids', array());
	$hostids = zbx_toHash($hostids);
	$showlegend = get_request('showlegend',0);

	$palette = get_request('palette',0);
	$palettetype = get_request('palettetype',0);

	$reportForm = new CFormTable(null,null,'get');//,'events.php?report_set=1','POST',null,'sform');
	$reportForm->setAttribute('name','zbx_report');
	$reportForm->setAttribute('id','zbx_report');

//	$reportForm->setMethod('post');
	if(isset($_REQUEST['report_show']) && !empty($items))
		$reportForm->addVar('report_show','show');

	$reportForm->addVar('config',$config);
	$reportForm->addVar('report_timesince',date('YmdHis', $report_timesince));
	$reportForm->addVar('report_timetill',date('YmdHis', $report_timetill));

//	$reportForm->addVar('items',$items); 				//params are set later!!
//	$reportForm->addVar('periods',$periods);

	$reportForm->addRow(S_TITLE, new CTextBox('title',$title,40));
	$reportForm->addRow(S_X.SPACE.S_LABEL, new CTextBox('xlabel',$xlabel,40));
	$reportForm->addRow(S_Y.SPACE.S_LABEL, new CTextBox('ylabel',$ylabel,40));

	$reportForm->addRow(S_LEGEND, new CCheckBox('showlegend',$showlegend,null,1));
	$reportForm->addVar('sortorder',0);

// GROUPS
	$groupids = get_request('groupids', array());
	$group_tb = new CTweenBox($reportForm,'groupids',$groupids,10);

	$options = array(
		'real_hosts' => 1,
		'output' => 'extend'
	);

	$db_groups = CHostGroup::get($options);
	order_result($db_groups, 'name');
	foreach($db_groups as $gnum => $group){
		$groupids[$group['groupid']] = $group['groupid'];
		$group_tb->addItem($group['groupid'],$group['name']);
	}

	$reportForm->addRow(S_GROUPS, $group_tb->Get(S_SELECTED_GROUPS,S_OTHER.SPACE.S_GROUPS));
// ----------

// HOSTS
//	validate_group(PERM_READ_ONLY,array('real_hosts'),'web.last.conf.groupid');

	$groupid = get_request('groupid',0);
	$cmbGroups = new CComboBox('groupid',$groupid,'submit()');
	$cmbGroups->addItem(0,S_ALL_S);
	foreach($db_groups as $gnum => $group){
		$cmbGroups->addItem($group['groupid'],$group['name']);
	}

	$td_groups = new CCol(array(S_GROUP,SPACE,$cmbGroups));
	$td_groups->setAttribute('style','text-align: right;');

	$host_tb = new CTweenBox($reportForm,'hostids',$hostids,10);

	$options = array(
		'real_hosts' => 1,
		'output' => array('hostid', 'host')
	);
	if($groupid > 0){
		$options['groupids'] = $groupid;
	}
	$db_hosts = CHost::get($options);
	$db_hosts = zbx_toHash($db_hosts, 'hostid');
	order_result($db_hosts, 'host');

	foreach($db_hosts as $hnum => $host){
		$host_tb->addItem($host['hostid'],$host['host']);
	}

	$options = array(
		'real_hosts' => 1,
		'output' => array('hostid', 'host'),
		'hostids' => $hostids,
	);
	$db_hosts2 = CHost::get($options);
	order_result($db_hosts2, 'host');
	foreach($db_hosts2 as $hnum => $host){
		if(!isset($db_hosts[$host['hostid']]))
			$host_tb->addItem($host['hostid'],$host['host']);
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
	$reporttimetab->setAttribute('width','10%');

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

	$itemidVar = new CVarTag('items[0][itemid]', $itemid);
	$itemidVar->setAttribute('id', 'items_0_itemid');
	$reportForm->addItem($itemidVar);

	$txtCondVal = new CTextBox('items[0][description]',$description,50,'yes');
	$txtCondVal->setAttribute('id', 'items_0_description');

	$btnSelect = new CButton('btn1',S_SELECT,
			"return PopUp('popup.php?dstfrm=".$reportForm->GetName().
			"&dstfld1=items_0_itemid&dstfld2=items_0_description&".
			"srctbl=items&srcfld1=itemid&srcfld2=description&monitored_hosts=1');",
			'T');

	$reportForm->addRow(S_ITEM , array($txtCondVal,$btnSelect));


	$paletteCmb = new CComboBox('palette', $palette);
		$paletteCmb->addItem(0, S_PALETTE.' #1');
		$paletteCmb->addItem(1, S_PALETTE.' #2');
		$paletteCmb->addItem(2, S_PALETTE.' #3');
		$paletteCmb->addItem(3, S_PALETTE.' #4');

	$paletteTypeCmb = new CComboBox('palettetype', $palettetype);
		$paletteTypeCmb->addItem(0, S_MIDDLE);
		$paletteTypeCmb->addItem(1, S_DARKEN);
		$paletteTypeCmb->addItem(2, S_BRIGHTEN);

	$reportForm->addRow(S_PALETTE , array($paletteCmb,$paletteTypeCmb));
//--------------


	$reportForm->addItemToBottomRow(new CButton('report_show',S_SHOW));

	$reset = new CButton('reset',S_RESET);
	$reset->setType('reset');
	$reportForm->addItemToBottomRow($reset);

return $reportForm;
}
?>
