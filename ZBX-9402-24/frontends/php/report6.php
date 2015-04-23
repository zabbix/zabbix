<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/reports.inc.php';

$page['title']	= _('Bar reports');
$page['file']	= 'report6.php';
$page['hist_arg'] = array('period');
$page['scripts'] = array('class.calendar.js');

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'config' =>				array(T_ZBX_INT, O_OPT,	P_SYS,			IN('0,1,2,3'),	null),
	'groupid' =>			array(T_ZBX_INT, O_OPT,	P_SYS,			DB_ID,			null),
	'hostids' =>			array(T_ZBX_INT, O_OPT,	null,			DB_ID,
		'isset({config}) && {config} == 3 && isset({report_show}) && !isset({groupids})'),
	'groupids' =>			array(T_ZBX_INT, O_OPT,	null,			DB_ID,
		'isset({config}) && {config} == 3 && isset({report_show}) && !isset({hostids})'),
	'itemid' =>				array(T_ZBX_INT, O_OPT, null,			DB_ID.NOT_ZERO,
		'isset({config}) && {config} == 3 && isset({report_show})'),
	'items' =>				array(T_ZBX_STR, O_OPT,	null,			null,
		'isset({report_show}) && !isset({delete_period}) && (isset({config}) && {config} != 3 || !isset({config}))',
		_('Items')),
	'new_graph_item' =>		array(T_ZBX_STR, O_OPT,	null,			null,			null),
	'group_gid' =>			array(T_ZBX_STR, O_OPT,	null,			null,			null),
	'title' =>				array(T_ZBX_STR, O_OPT,	null,			null,			null),
	'xlabel' =>				array(T_ZBX_STR, O_OPT,	null,			null,			null),
	'ylabel' =>				array(T_ZBX_STR, O_OPT,	null,			null,			null),
	'showlegend' =>			array(T_ZBX_STR, O_OPT,	null,			null,			null),
	'sorttype' =>			array(T_ZBX_INT, O_OPT,	null,			null,			null),
	'scaletype' =>			array(T_ZBX_INT, O_OPT,	null,			null,			null),
	'avgperiod' =>			array(T_ZBX_INT, O_OPT,	null,			null,			null),
	'periods' =>			array(T_ZBX_STR, O_OPT,	null,			null,
		'isset({report_show}) && !isset({delete_item}) && isset({config}) && {config} == 2',
		_('Period')),
	'new_period' =>			array(T_ZBX_STR, O_OPT,	null,			null,			null),
	'group_pid' =>			array(T_ZBX_STR, O_OPT,	null,			null,			null),
	'palette' =>			array(T_ZBX_INT, O_OPT,	null,			null,			null),
	'palettetype' =>		array(T_ZBX_INT, O_OPT,	null,			null,			null),
	// actions
	'delete_item' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,			null),
	'delete_period' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,			null),
	// filter
	'report_show' =>		array(T_ZBX_STR, O_OPT,	P_SYS,			null,			null),
	'report_reset' =>		array(T_ZBX_STR, O_OPT,	P_SYS,			null,			null),
	'report_timesince' =>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,			null),
	'report_timetill' =>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,			null),
	// ajax
	'filterState' =>		array(T_ZBX_INT, O_OPT, P_ACT,			null,			null)
);
$isValid = check_fields($fields);

// filter reset
if (hasRequest('report_reset')) {
	// get requests keys
	if (getRequest('config') == BR_DISTRIBUTION_MULTIPLE_PERIODS) {
		$unsetRequests = array('title', 'xlabel', 'ylabel', 'showlegend', 'scaletype', 'items', 'report_timesince',
			'report_timetill', 'report_show'
		);
	}
	elseif (getRequest('config') == BR_DISTRIBUTION_MULTIPLE_ITEMS) {
		$unsetRequests = array('periods', 'items', 'title', 'xlabel', 'ylabel', 'showlegend', 'sorttype',
			'report_show'
		);
	}
	else {
		$unsetRequests = array('report_timesince', 'report_timetill', 'sortorder', 'groupids', 'hostids', 'itemid',
			'title', 'xlabel', 'ylabel', 'showlegend', 'groupid', 'scaletype', 'avgperiod', 'palette', 'palettetype',
			'report_show'
		);
	}

	// requests unseting
	foreach ($unsetRequests as $unsetRequests) {
		unset($_REQUEST[$unsetRequests]);
	}
}

if (hasRequest('new_graph_item')) {
	$_REQUEST['items'] = getRequest('items', array());
	$newItem = getRequest('new_graph_item', array());

	foreach ($_REQUEST['items'] as $item) {
		if ((bccomp($newItem['itemid'], $item['itemid']) == 0)
				&& $newItem['calc_fnc'] == $item['calc_fnc']
				&& $newItem['caption'] == $item['caption']) {
			$itemExists = true;
			break;
		}
	}

	if (!isset($itemExists)) {
		array_push($_REQUEST['items'], $newItem);
	}
}

// validate permissions
if (getRequest('config') == BR_COMPARE_VALUE_MULTIPLE_PERIODS) {
	if (getRequest('groupid') && !API::HostGroup()->isReadable(array($_REQUEST['groupid']))) {
		access_deny();
	}
	if (getRequest('groupids') && !API::HostGroup()->isReadable($_REQUEST['groupids'])) {
		access_deny();
	}
	if (getRequest('hostids') && !API::Host()->isReadable($_REQUEST['hostids'])) {
		access_deny();
	}
	if (getRequest('itemid')) {
		$items = API::Item()->get(array(
			'itemids' => $_REQUEST['itemid'],
			'webitems' => true,
			'output' => array('itemid')
		));
		if (!$items) {
			access_deny();
		}
	}
}
else {
	if (getRequest('items') && count($_REQUEST['items']) > 0) {
		$itemIds = zbx_objectValues($_REQUEST['items'], 'itemid');
		$itemsCount = API::Item()->get(array(
			'itemids' => $itemIds,
			'webitems' => true,
			'countOutput' => true
		));

		if (count($itemIds) != $itemsCount) {
			access_deny();
		}
	}
}

if (hasRequest('filterState')) {
	CProfile::update('web.report6.filter.state', getRequest('filterState'), PROFILE_TYPE_INT);
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}


if (isset($_REQUEST['delete_item']) && isset($_REQUEST['group_gid'])) {
	foreach ($_REQUEST['items'] as $gid => $item) {
		if (!isset($_REQUEST['group_gid'][$gid])) {
			continue;
		}
		unset($_REQUEST['items'][$gid]);
	}
	unset($_REQUEST['delete_item'], $_REQUEST['group_gid']);
}
elseif (isset($_REQUEST['new_period'])) {
	$_REQUEST['periods'] = getRequest('periods', array());
	$newPeriod = getRequest('new_period', array());

	foreach ($_REQUEST['periods'] as $period) {
		$period['report_timesince'] = zbxDateToTime($period['report_timesince']);
		$period['report_timetill'] = zbxDateToTime($period['report_timetill']);

		if ($newPeriod['report_timesince'] == $period['report_timesince']
				&& $newPeriod['report_timetill'] == $period['report_timetill']) {
			$periodExists = true;
			break;
		}
	}

	if (!isset($periodExists)) {
		array_push($_REQUEST['periods'], $newPeriod);
	}
}
elseif (isset($_REQUEST['delete_period']) && isset($_REQUEST['group_pid'])) {
	foreach ($_REQUEST['periods'] as $pid => $period) {
		if (!isset($_REQUEST['group_pid'][$pid])) {
			continue;
		}
		unset($_REQUEST['periods'][$pid]);
	}
	unset($_REQUEST['delete_period'], $_REQUEST['group_pid']);
}

// item validation
$config = $_REQUEST['config'] = getRequest('config', BR_DISTRIBUTION_MULTIPLE_PERIODS);

// items array validation
if ($config != BR_COMPARE_VALUE_MULTIPLE_PERIODS) {
	$items = getRequest('items');
	$validItems = validateBarReportItems($items);
	if ($validItems) {
		$validItems = CMacrosResolverHelper::resolveItemNames($validItems);

		foreach ($validItems as &$item) {
			if ($item['caption'] === $item['name']) {
				$item['caption'] = $item['name_expanded'];
			}
		}

		unset($item);
	}

	if ($config == BR_DISTRIBUTION_MULTIPLE_ITEMS) {
		$validPeriods = validateBarReportPeriods(getRequest('periods'));
	}
}

$_REQUEST['report_timesince'] = zbxDateToTime(getRequest('report_timesince',
	date(TIMESTAMP_FORMAT_ZERO_TIME, time() - SEC_PER_DAY)));
$_REQUEST['report_timetill'] = zbxDateToTime(getRequest('report_timetill',
	date(TIMESTAMP_FORMAT_ZERO_TIME, time())));

$rep6_wdgt = new CWidget();

$r_form = new CForm();
$cnfCmb = new CComboBox('config', $config, 'submit();');
$cnfCmb->addItem(BR_DISTRIBUTION_MULTIPLE_PERIODS, _('Distribution of values for multiple periods'));
$cnfCmb->addItem(BR_DISTRIBUTION_MULTIPLE_ITEMS, _('Distribution of values for multiple items'));
$cnfCmb->addItem(BR_COMPARE_VALUE_MULTIPLE_PERIODS, _('Compare values for multiple periods'));

$r_form->addItem(array(_('Reports').SPACE, $cnfCmb));

$rep6_wdgt->addPageHeader(_('Bar reports'));
$rep6_wdgt->addHeader(_('Report'), $r_form);
$rep6_wdgt->addItem(BR());

$rep_tab = new CTable();
$rep_tab->setCellPadding(3);
$rep_tab->setCellSpacing(3);

$rep_tab->setAttribute('border', 0);

switch ($config) {
	default:
	case BR_DISTRIBUTION_MULTIPLE_PERIODS:
		$rep_form = valueDistributionFormForMultiplePeriods($validItems);
		break;
	case BR_DISTRIBUTION_MULTIPLE_ITEMS:
		$rep_form = valueDistributionFormForMultipleItems($validItems, $validPeriods);
		break;
	case BR_COMPARE_VALUE_MULTIPLE_PERIODS:
		$rep_form = valueComparisonFormForMultiplePeriods();
		break;
}

$rep6_wdgt->addFlicker($rep_form, CProfile::get('web.report6.filter.state', BR_DISTRIBUTION_MULTIPLE_PERIODS));

if (hasRequest('report_show')) {
	$items = ($config == BR_COMPARE_VALUE_MULTIPLE_PERIODS)
		? array(array('itemid' => getRequest('itemid')))
		: $validItems;

	if ($isValid && (($config != BR_COMPARE_VALUE_MULTIPLE_PERIODS) ? $validItems : true)
			&& (($config == BR_DISTRIBUTION_MULTIPLE_ITEMS) ? $validPeriods : true)) {
		$src = 'chart_bar.php?'.
			'config='.$config.
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
			url_param($items, false, 'items').
			url_param('hostids').
			url_param('groupids').
			url_param('palette').
			url_param('palettetype');

		$rep_tab->addRow(new CImg($src, 'report'));
	}
}

$outer_table = new CTable();

$outer_table->setAttribute('border', 0);
$outer_table->setAttribute('width', '100%');

$outer_table->setCellPadding(1);
$outer_table->setCellSpacing(1);

$tmp_row = new CRow($rep_tab);
$tmp_row->setAttribute('align', 'center');

$outer_table->addRow($tmp_row);

$rep6_wdgt->addItem($outer_table);
$rep6_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
