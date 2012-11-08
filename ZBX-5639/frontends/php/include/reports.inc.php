<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

/**
 * Creates the availability report page filter.
 *
 * Possible $config values are AVAILABILITY_REPORT_BY_HOST or AVAILABILITY_REPORT_BY_TEMPLATE.
 *
 * @param int $config                   report mode
 * @param array $PAGE_GROUPS            the data for the host/template group filter select
 * @param array $PAGE_HOSTS             the data for the host/template filter select
 * @param string|string[] $usedHostIds  the hosts the displayed triggers belong to
 *
 * @return CFormTable
 */
function get_report2_filter($config, array $PAGE_GROUPS, array $PAGE_HOSTS, $usedHostIds = null){
	global $options;
	$filterForm = new CFormTable();
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

	if ($config == AVAILABILITY_REPORT_BY_TEMPLATE) {
		$filterForm->addRow(_('Template group'),$cmbGroups);
		$filterForm->addRow(_('Template'),$cmbHosts);

		$cmbTrigs = new CComboBox('tpl_triggerid',get_request('tpl_triggerid',0),'submit()');
		$cmbHGrps = new CComboBox('hostgroupid',get_request('hostgroupid',0),'submit()');

		$cmbTrigs->addItem(0, _('all'));
		$cmbHGrps->addItem(0, _('all'));

		// fetch the groups, that the used hosts belong to
		$hostGroups = API::HostGroup()->get(array(
			'output' => array('name', 'groupid'),
			'hostids' => $usedHostIds,
			'monitored_hosts' => true
		));
		$groupExist = false;
		foreach ($hostGroups as $hostGroup) {
			if (isset($_REQUEST['hostgroupid']) && $hostGroup['groupid'] == $_REQUEST['hostgroupid']) {
				$groupExist = true;
			}
			$cmbHGrps->addItem(
				$hostGroup['groupid'],
				get_node_name_by_elid($hostGroup['groupid'], null, ': ').$hostGroup['name']
			);
		}
		if (isset($_REQUEST['hostgroupid']) && !$groupExist) {
			unset($options['groupids']);
		}

		if ($PAGE_HOSTS['selected']) {
			$sql_cond = ' AND h.hostid='.$PAGE_HOSTS['selected'];
		}
		else {
			$sql_cond = ' AND '.DBcondition('h.hostid',$PAGE_HOSTS['hostids']);
		}
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
		$triggerExist = false;
		while ($row = DBfetch($result)) {
			if (isset($_REQUEST['tpl_triggerid']) && $row['triggerid'] == $_REQUEST['tpl_triggerid']) {
				$triggerExist = true;
			}
			$cmbTrigs->addItem(
				$row['triggerid'],
				get_node_name_by_elid($row['triggerid'], null, ': ').$row['description']
			);
		}
		if (isset($_REQUEST['tpl_triggerid']) && !$triggerExist) {
			unset($options['filter']['templateid']);
		}

		$filterForm->addRow(_('Template trigger'),$cmbTrigs);
		$filterForm->addRow(_('Filter by host group'),$cmbHGrps);
	}
	elseif ($config == AVAILABILITY_REPORT_BY_HOST) {
		$filterForm->addRow(_('Host group'), $cmbGroups);
		$filterForm->addRow(_('Host'), $cmbHosts);
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
		_('From'),
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
		_('Till'),
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

	$filterForm->addRow(_('Period'), $filtertimetab);

//*/
	$filterForm->addItemToBottomRow(new CSubmit('filter_set',_('Filter')));

	$reset = new CButton("filter_rst", _('Reset'), 'javascript: var url = new Curl(location.href); url.setArgument("filter_rst",1); location.href = url.getUrl();');

	$filterForm->addItemToBottomRow($reset);

	return $filterForm;
}

function bar_report_form(){
	$config = get_request('config',1);
	$items = get_request('items',array());
	$scaletype = get_request('scaletype',TIMEPERIOD_TYPE_WEEKLY);

	$title = get_request('title',_('Report 1'));
	$xlabel = get_request('xlabel','');
	$ylabel = get_request('ylabel','');
	$showlegend = get_request('showlegend',0);

	$report_timesince = $_REQUEST['report_timesince'];
	$report_timetill = $_REQUEST['report_timetill'];

	$reportForm = new CFormTable(null,null,'get');//,'events.php?report_set=1','POST',null,'sform');
	$reportForm->setAttribute('name','zbx_report');
	$reportForm->setAttribute('id','zbx_report');

	if(isset($_REQUEST['report_show']) && !empty($items))
		$reportForm->addVar('report_show','show');

	$reportForm->addVar('config',$config);
	$reportForm->addVar('items',$items);
	$reportForm->addVar('report_timesince', date('YmdHis', $report_timesince));
	$reportForm->addVar('report_timetill',  date('YmdHis', $report_timetill));

	$reportForm->addRow(_('Title'), new CTextBox('title',$title,40));
	$reportForm->addRow(_('X label'), new CTextBox('xlabel',$xlabel,40));
	$reportForm->addRow(_('Y label'), new CTextBox('ylabel',$ylabel,40));
	$reportForm->addRow(_('Legend'), new CCheckBox('showlegend',$showlegend,null,1));

	$scale = new CComboBox('scaletype', $scaletype);
		$scale->addItem(TIMEPERIOD_TYPE_HOURLY, _('Hourly'));
		$scale->addItem(TIMEPERIOD_TYPE_DAILY, 	_('Daily'));
		$scale->addItem(TIMEPERIOD_TYPE_WEEKLY,	_('Weekly'));
		$scale->addItem(TIMEPERIOD_TYPE_MONTHLY, _('Monthly'));
		$scale->addItem(TIMEPERIOD_TYPE_YEARLY,	_('Yearly'));
	$reportForm->addRow(_('Scale'), $scale);

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
		_('From'),
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
		_('Till'),
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

	$reportForm->addRow(_('Period'), $reporttimetab);
//*/

	if(count($items)){

		$items_table = new CTableInfo();
		foreach($items as $gid => $gitem){

			$host = get_host_by_itemid($gitem['itemid']);
			$item = get_item_by_itemid($gitem['itemid']);

			$color = new CColorCell(null,$gitem['color']);

			$caption = new CSpan($gitem['caption'], 'link');
			$caption->onClick(
					'return PopUp("popup_bitem.php?config=1&list_name=items&dstfrm='.$reportForm->GetName().
					url_param($gitem, false).
					url_param($gid,false,'gid').
					'",550,400,"graph_item_form");');

			$description = $host['name'].': '.itemName($item);

			$items_table->addRow(array(
					new CCheckBox('group_gid['.$gid.']',isset($group_gid[$gid])),
					$caption,
					$description,
					graph_item_calc_fnc2str($gitem['calc_fnc'],0),
					($gitem['axisside']==GRAPH_YAXIS_SIDE_LEFT)?_('Left'):_('Right'),
					$color,
				));
		}
		$delete_button = new CSubmit('delete_item', _('Delete selected'));
	}
	else{
		$items_table = $delete_button = null;
	}

	$reportForm->addRow(_('Items'),
				array(
					$items_table,
					new CButton('add_item',_('Add'),
						"return PopUp('popup_bitem.php?config=1&dstfrm=".$reportForm->getName().
						"',800,400,'graph_item_form');"),
					$delete_button
				));
	unset($items_table, $delete_button);

	$reportForm->addItemToBottomRow(new CSubmit('report_show',_('Show')));

	$reset = new CButton('reset',_('Reset'));
	$reset->setType('reset');
	$reportForm->addItemToBottomRow($reset);

return $reportForm;
}

function bar_report_form2(){
	$config = get_request('config',1);

	$title = get_request('title',_('Report 2'));
	$xlabel = get_request('xlabel','');
	$ylabel = get_request('ylabel','');

	$sorttype = get_request('sorttype',0);

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

	$reportForm->addRow(_('Title'), new CTextBox('title', $title, 40));
	$reportForm->addRow(_('X label'), new CTextBox('xlabel', $xlabel, 40));
	$reportForm->addRow(_('Y label'), new CTextBox('ylabel', $ylabel, 40));

	$reportForm->addRow(_('Legend'), new CCheckBox('showlegend', $showlegend, null, 1));

	if (count($periods) < 2) {
		$sortCmb = new CComboBox('sorttype', $sorttype);
			$sortCmb->addItem(0, _('Name'));
			$sortCmb->addItem(1, _('Value'));

		$reportForm->addRow(_('Sort by'), $sortCmb);
	}
	else {
		$reportForm->addVar('sortorder', 0);
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
					zbx_date2str(REPORTS_BAR_REPORT_DATE_FORMAT, $period['report_timesince']),
					zbx_date2str(REPORTS_BAR_REPORT_DATE_FORMAT, $period['report_timetill']),
					$color,
				));
		}
		$delete_button = new CSubmit('delete_period', _('Delete selected'));
	}
	else{
		$periods_table = $delete_button = null;
	}

	$reportForm->addVar('periods',$periods);

	$reportForm->addRow(_('Period'),
				array(
					$periods_table,
					new CButton('add_period',_('Add'),
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

			$caption = new CSpan($gitem['caption'], 'link');
			$caption->onClick(
					'return PopUp("popup_bitem.php?config=2&list_name=items&dstfrm='.$reportForm->GetName().
					url_param($gitem, false).
					url_param($gid,false,'gid').
					'",550,400,"graph_item_form");');

			$description = $host['name'].': '.itemName($item);

			$items_table->addRow(array(
					new CCheckBox('group_gid['.$gid.']',isset($group_gid[$gid])),
					$caption,
					$description,
					graph_item_calc_fnc2str($gitem['calc_fnc'],0)
				));
		}
		$delete_button = new CSubmit('delete_item', _('Delete selected'));
	}
	else{
		$items_table = $delete_button = null;
	}

	$reportForm->addRow(_('Items'),
				array(
					$items_table,
					new CButton('add_item',_('Add'),
						"return PopUp('popup_bitem.php?config=2&dstfrm=".$reportForm->getName().
						"',550,400,'graph_item_form');"),
					$delete_button
				));
	unset($items_table, $delete_button);
//--------------


	$reportForm->addItemToBottomRow(new CSubmit('report_show',_('Show')));

	$reset = new CButton('reset',_('Reset'));
	$reset->setType('reset');
	$reportForm->addItemToBottomRow($reset);

return $reportForm;
}

function bar_report_form3(){
	$config = get_request('config',1);

	$title = get_request('title',_('Report 3'));
	$xlabel = get_request('xlabel','');
	$ylabel = get_request('ylabel','');

	$scaletype = get_request('scaletype', TIMEPERIOD_TYPE_WEEKLY);
	$avgperiod = get_request('avgperiod', TIMEPERIOD_TYPE_DAILY);

	$report_timesince = get_request('report_timesince', date('YmdHis', time() - SEC_PER_DAY));
	$report_timetill = get_request('report_timetill', date('YmdHis'));

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

	$reportForm->addRow(_('Title'), new CTextBox('title', $title, 40));
	$reportForm->addRow(_('X label'), new CTextBox('xlabel', $xlabel, 40));
	$reportForm->addRow(_('Y label'), new CTextBox('ylabel', $ylabel, 40));

	$reportForm->addRow(_('Legend'), new CCheckBox('showlegend', $showlegend, null, 1));
	$reportForm->addVar('sortorder', 0);

// GROUPS
	$groupids = get_request('groupids', array());
	$group_tb = new CTweenBox($reportForm,'groupids',$groupids,10);

	$options = array(
		'real_hosts' => 1,
		'output' => 'extend'
	);

	$db_groups = API::HostGroup()->get($options);
	order_result($db_groups, 'name');
	foreach($db_groups as $gnum => $group){
		$groupids[$group['groupid']] = $group['groupid'];
		$group_tb->addItem($group['groupid'],$group['name']);
	}

	$reportForm->addRow(_('Groups'), $group_tb->Get(_('Selected groups'), _('Other groups')));
// ----------

// HOSTS
//	validate_group(PERM_READ_ONLY,array('real_hosts'),'web.last.conf.groupid');

	$groupid = get_request('groupid',0);
	$cmbGroups = new CComboBox('groupid',$groupid,'submit()');
	$cmbGroups->addItem(0, _('All'));
	foreach($db_groups as $gnum => $group){
		$cmbGroups->addItem($group['groupid'],$group['name']);
	}

	$td_groups = new CCol(array(_('Group'),SPACE,$cmbGroups));
	$td_groups->setAttribute('style','text-align: right;');

	$host_tb = new CTweenBox($reportForm,'hostids',$hostids,10);

	$options = array(
		'real_hosts' => 1,
		'output' => array('hostid', 'name')
	);
	if($groupid > 0){
		$options['groupids'] = $groupid;
	}
	$db_hosts = API::Host()->get($options);
	$db_hosts = zbx_toHash($db_hosts, 'hostid');
	order_result($db_hosts, 'name');

	foreach($db_hosts as $hnum => $host){
		$host_tb->addItem($host['hostid'],$host['name']);
	}

	$options = array(
		'real_hosts' => 1,
		'output' => array('hostid', 'name'),
		'hostids' => $hostids,
	);
	$db_hosts2 = API::Host()->get($options);
	order_result($db_hosts2, 'name');
	foreach($db_hosts2 as $hnum => $host){
		if(!isset($db_hosts[$host['hostid']]))
			$host_tb->addItem($host['hostid'],$host['name']);
	}

	$reportForm->addRow(_('Hosts'), $host_tb->Get(_('Selected hosts'), array(_('Other hosts | Group').SPACE, $cmbGroups)));
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
		_('From'),
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
		_('Till'),
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

	$reportForm->addRow(_('Period'), $reporttimetab);

	$scale = new CComboBox('scaletype', $scaletype);
		$scale->addItem(TIMEPERIOD_TYPE_HOURLY, _('Hourly'));
		$scale->addItem(TIMEPERIOD_TYPE_DAILY, 	_('Daily'));
		$scale->addItem(TIMEPERIOD_TYPE_WEEKLY,	_('Weekly'));
		$scale->addItem(TIMEPERIOD_TYPE_MONTHLY,_('Monthly'));
		$scale->addItem(TIMEPERIOD_TYPE_YEARLY,	_('Yearly'));
	$reportForm->addRow(_('Scale'), $scale);

	$avgcmb = new CComboBox('avgperiod', $avgperiod);
		$avgcmb->addItem(TIMEPERIOD_TYPE_HOURLY,	_('Hourly'));
		$avgcmb->addItem(TIMEPERIOD_TYPE_DAILY, 	_('Daily'));
		$avgcmb->addItem(TIMEPERIOD_TYPE_WEEKLY,	_('Weekly'));
		$avgcmb->addItem(TIMEPERIOD_TYPE_MONTHLY, 	_('Monthly'));
		$avgcmb->addItem(TIMEPERIOD_TYPE_YEARLY,	_('Yearly'));
	$reportForm->addRow(_('Average by'), $avgcmb);

	// items
	$itemid = 0;
	$description = '';
	if(count($items) && ($items[0]['itemid'] > 0)){
		$itemid = $items[0]['itemid'];
		$description = get_item_by_itemid($itemid);
		$description = itemName($description);
	}

	$itemidVar = new CVar('items[0][itemid]', $itemid, 'items_0_itemid');
	$reportForm->addItem($itemidVar);

	$txtCondVal = new CTextBox('items[0][description]',$description,50,'yes');
	$txtCondVal->setAttribute('id', 'items_0_description');

	$btnSelect = new CButton('btn1', _('Select'),
			"return PopUp('popup.php?dstfrm=".$reportForm->GetName().
			"&dstfld1=items_0_itemid&dstfld2=items_0_description&".
			"srctbl=items&srcfld1=itemid&srcfld2=name&monitored_hosts=1');",
			'T');

	$reportForm->addRow(_('Item'), array($txtCondVal, $btnSelect));


	$paletteCmb = new CComboBox('palette', $palette);
		$paletteCmb->addItem(0, _s('Palette #%1$s', 1));
		$paletteCmb->addItem(1, _s('Palette #%1$s', 2));
		$paletteCmb->addItem(2, _s('Palette #%1$s', 3));
		$paletteCmb->addItem(3, _s('Palette #%1$s', 4));

	$paletteTypeCmb = new CComboBox('palettetype', $palettetype);
		$paletteTypeCmb->addItem(0, _('Middle'));
		$paletteTypeCmb->addItem(1, _('Darken'));
		$paletteTypeCmb->addItem(2, _('Brighten'));

	$reportForm->addRow(_('Palette') , array($paletteCmb,$paletteTypeCmb));
	$reportForm->addItemToBottomRow(new CSubmit('report_show',_('Show')));

	$reset = new CButton('reset', _('Reset'));
	$reset->setType('reset');
	$reportForm->addItemToBottomRow($reset);

	return $reportForm;
}
