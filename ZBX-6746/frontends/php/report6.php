<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once ('include/config.inc.php');
require_once ('include/reports.inc.php');

$page['title']	= _('Bar reports');
$page['file']	= 'report6.php';
$page['hist_arg'] = array('period');
$page['scripts'] = array('class.calendar.js');

require_once dirname(__FILE__).'/include/page_header.php';
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'config'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1,2,3'),	NULL),

		'groupid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, 		NULL),
		'hostids'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, 		'isset({config})&&({config}==3)&&isset({report_show})&&!isset({groupids})'),
		'groupids'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, 		'isset({config})&&({config}==3)&&isset({report_show})&&!isset({hostids})'),

		'items'=>		array(T_ZBX_STR, O_OPT,	NULL,	null,		'isset({report_show})'),
		'new_graph_item'=>	array(T_ZBX_STR, O_OPT,	NULL,	null,		null),
		'group_gid'=>		array(T_ZBX_STR, O_OPT,	null,	null,		null),

		'title'=>		array(T_ZBX_STR, O_OPT,	NULL,	null,		null),
		'xlabel'=>		array(T_ZBX_STR, O_OPT,	NULL,	null,		null),
		'ylabel'=>		array(T_ZBX_STR, O_OPT,	NULL,	null,		null),

		'showlegend'=>		array(T_ZBX_STR, O_OPT,	NULL,	null,		null),
		'sorttype'=>		array(T_ZBX_INT, O_OPT,	null,	null,		null),

		'scaletype'=>		array(T_ZBX_INT, O_OPT,	NULL,	null,		NULL),
		'avgperiod'=>		array(T_ZBX_INT, O_OPT,	NULL,	null,		NULL),

		'periods'=>		array(T_ZBX_STR, O_OPT,	null,	null,		null),
		'new_period'=>		array(T_ZBX_STR, O_OPT,	NULL,	null,		null),
		'group_pid'=>		array(T_ZBX_STR, O_OPT,	null,	null,		null),

		'palette'=>		array(T_ZBX_INT, O_OPT,	NULL,	null,		NULL),
		'palettetype'=>		array(T_ZBX_INT, O_OPT,	NULL,	null,		NULL),
// actions
		'delete_item'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete_period'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'report_show'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
// filter
		'report_show'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,		NULL),

		'report_timesince'=>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),
		'report_timetill'=>		array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'favstate'=>	array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})&&("filter"=={favobj})'),
	);

	check_fields($fields);

/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.report6.filter.state',$_REQUEST['favstate'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit();
	}
//--------
?>
<?php

	if(isset($_REQUEST['new_graph_item'])){
		$_REQUEST['items'] = get_request('items', array());
		$new_gitem = get_request('new_graph_item', array());

		foreach($_REQUEST['items'] as $gid => $data){
			if(	(bccomp($new_gitem['itemid'] , $data['itemid'])==0) &&
				$new_gitem['calc_fnc'] == $data['calc_fnc'] &&
				$new_gitem['caption'] == $data['caption'])
			{
				$already_exist = true;
				break;
			}
		}

		if(!isset($already_exist)){
			array_push($_REQUEST['items'], $new_gitem);
		}
	}
	else if(isset($_REQUEST['delete_item']) && isset($_REQUEST['group_gid'])){

		foreach($_REQUEST['items'] as $gid => $data){
			if(!isset($_REQUEST['group_gid'][$gid])) continue;
			unset($_REQUEST['items'][$gid]);
		}
		unset($_REQUEST['delete_item'], $_REQUEST['group_gid']);
	}
	else if(isset($_REQUEST['new_period'])){
		$_REQUEST['periods'] = get_request('periods', array());
		$new_period = get_request('new_period', array());

		foreach($_REQUEST['periods'] as $pid => $data){
			$data['report_timesince'] = zbxDateToTime($data['report_timesince']);
			$data['report_timetill'] = zbxDateToTime($data['report_timetill']);

			if(	$new_period['report_timesince'] == $data['report_timesince'] &&
				$new_period['report_timetill'] == $data['report_timetill'])
			{
				$already_exist = true;
				break;
			}
		}

		if(!isset($already_exist)){
			array_push($_REQUEST['periods'], $new_period);
		}
	}
	else if(isset($_REQUEST['delete_period']) && isset($_REQUEST['group_pid'])){

		foreach($_REQUEST['periods'] as $pid => $data){
			if(!isset($_REQUEST['group_pid'][$pid])) continue;
			unset($_REQUEST['periods'][$pid]);
		}
		unset($_REQUEST['delete_period'], $_REQUEST['group_pid']);
	}

	// item validation
	if (isset($_REQUEST['items']) && $_REQUEST['items']) {
		$itemIds = zbx_objectValues($_REQUEST['items'], 'itemid');

		$itemCount = API::Item()->get(array(
			'itemids' => $itemIds,
			'webitems' => true,
			'countOutput' => true
		));

		if ($itemCount != count($itemIds)) {
			show_error_message(_('No permissions to referred object or it does not exist!'));
			require_once dirname(__FILE__).'/include/page_footer.php';
			exit;
		}
	}

	$config = $_REQUEST['config'] = get_request('config',1);

	$_REQUEST['report_timesince'] = zbxDateToTime(get_request('report_timesince', date('YmdHis', time() - SEC_PER_DAY)));
	$_REQUEST['report_timetill'] = zbxDateToTime(get_request('report_timetill', date('YmdHis')));

	$rep6_wdgt = new CWidget();
// Header
	$r_form = new CForm();
	$cnfCmb = new CComboBox('config', $config, 'submit();');
		$cnfCmb->addItem(1, _('Distribution of values for multiple periods'));
		$cnfCmb->addItem(2, _('Distribution of values for multiple items'));
		$cnfCmb->addItem(3, _('Compare values for multiple periods'));

	$r_form->addItem(array(_('Reports').SPACE,$cnfCmb));

	$rep6_wdgt->addPageHeader(_('Bar reports'));
	$rep6_wdgt->addHeader(_('Report'), $r_form);
	$rep6_wdgt->addItem(BR());
//-------------

	$rep_tab = new CTable();
	$rep_tab->setCellPadding(3);
	$rep_tab->setCellSpacing(3);

	$rep_tab->setAttribute('border',0);

// --------------
	switch($config){
		case 1: $rep_form = bar_report_form(); break;
		case 2: $rep_form = bar_report_form2(); break;
		case 3: $rep_form = bar_report_form3(); break;
		default: $rep_form = bar_report_form();
	}

	$rep6_wdgt->addFlicker($rep_form, CProfile::get('web.report6.filter.state', 1));

	if(isset($_REQUEST['report_show'])){
		$src = 'chart_bar.php?config='.$_REQUEST['config'].
					url_param('title').
					url_param('xlabel').
					url_param('ylabel').
					url_param('scaletype').
					url_param('avgperiod').
					url_param('showlegend').
					url_param('sorttype').
					url_param('report_timesince').
					url_param('report_timetill').
					url_param('periods').
					url_param('items').
					url_param('hostids').
					url_param('groupids').
					url_param('palette').
					url_param('palettetype');

		$rep_tab->addRow(new CImg($src, 'report'));
	}

	$outer_table = new CTable();
	$outer_table->setAttribute('border',0);
	$outer_table->setAttribute('width','100%');

	$outer_table->setCellPadding(1);
	$outer_table->setCellSpacing(1);
	$tmp_row = new CRow($rep_tab);
	$tmp_row->setAttribute('align','center');
	$outer_table->addRow($tmp_row);

	$rep6_wdgt->addItem($outer_table);
	$rep6_wdgt->show();
?>
<?php

require_once dirname(__FILE__).'/include/page_footer.php';

?>
