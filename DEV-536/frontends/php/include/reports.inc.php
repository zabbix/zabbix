<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


/**
 * Create bar report form for "Distribution of values for multiple periods".
 *
 * @param array $items
 *
 * @return object $reportForm
 */
function valueDistributionFormForMultiplePeriods($items = array()) {
	$config = getRequest('config', BR_DISTRIBUTION_MULTIPLE_PERIODS);
	$scaletype = getRequest('scaletype', TIMEPERIOD_TYPE_WEEKLY);

	$title = getRequest('title', _('Report 1'));
	$xlabel = getRequest('xlabel', '');
	$ylabel = getRequest('ylabel', '');
	$showlegend = getRequest('showlegend', 0);

	$report_timesince = $_REQUEST['report_timesince'];
	$report_timetill = $_REQUEST['report_timetill'];

	$reportForm = new CFormTable(null, null, 'get');
	$reportForm->setTableClass('formtable old-filter');
	$reportForm->setAttribute('name', 'zbx_report');
	$reportForm->setAttribute('id', 'zbx_report');

	if (isset($_REQUEST['report_show']) && is_array($items) && $items) {
		$reportForm->addVar('report_show', 'show');
	}

	$reportForm->addVar('config', $config);

	$reportForm->addVar('report_timesince', date(TIMESTAMP_FORMAT_ZERO_TIME, $report_timesince));
	$reportForm->addVar('report_timetill', date(TIMESTAMP_FORMAT_ZERO_TIME, $report_timetill));

	$reportForm->addRow(_('Title'), new CTextBox('title', $title, 40));
	$reportForm->addRow(_('X label'), new CTextBox('xlabel', $xlabel, 40));
	$reportForm->addRow(_('Y label'), new CTextBox('ylabel', $ylabel, 40));
	$reportForm->addRow(_('Legend'), new CCheckBox('showlegend', $showlegend, null, 1));

	$scale = new CComboBox('scaletype', $scaletype);
	$scale->addItem(TIMEPERIOD_TYPE_HOURLY, _('Hourly'));
	$scale->addItem(TIMEPERIOD_TYPE_DAILY, 	_('Daily'));
	$scale->addItem(TIMEPERIOD_TYPE_WEEKLY,	_('Weekly'));
	$scale->addItem(TIMEPERIOD_TYPE_MONTHLY, _('Monthly'));
	$scale->addItem(TIMEPERIOD_TYPE_YEARLY,	_('Yearly'));
	$reportForm->addRow(_('Scale'), $scale);

	$reporttimetab = new CTable(null, 'calendar');

	$timeSinceRow = createDateSelector('report_timesince', $report_timesince, 'report_timetill');
	array_unshift($timeSinceRow, _('From'));
	$reporttimetab->addRow($timeSinceRow);

	$timeTillRow = createDateSelector('report_timetill', $report_timetill, 'report_timesince');
	array_unshift($timeTillRow, _('Till'));
	$reporttimetab->addRow($timeTillRow);

	$reportForm->addRow(_('Period'), $reporttimetab);

	if ($items) {
		$items = CMacrosResolverHelper::resolveItemNames($items);

		$items_table = new CTableInfo();
		foreach ($items as $id => &$item) {
			$color = new CColorCell(null, $item['color']);

			$caption = new CSpan($item['caption'], 'link');
			$caption->onClick('return PopUp("popup_bitem.php?config='.BR_DISTRIBUTION_MULTIPLE_PERIODS.
				'&list_name=items&dstfrm='.$reportForm->GetName().url_param($item, false).
				url_param($id, false, 'gid').'", 550, 400, "graph_item_form");'
			);

			$description = $item['host']['name'].NAME_DELIMITER.$item['name_expanded'];

			$items_table->addRow(array(
				new CCheckBox('group_gid['.$id.']', isset($group_gid[$id])),
				$caption,
				$description,
				graph_item_calc_fnc2str($item['calc_fnc'], 0),
				($item['axisside'] == GRAPH_YAXIS_SIDE_LEFT) ? _('Left') : _('Right'),
				$color,
			));

			// once used, unset unnecessary fields so they don't pass to URL
			unset($item['value_type'], $item['host'], $item['name'], $item['name_expanded']);
		}
		unset($item);

		$reportForm->addVar('items', $items);

		$delete_button = new CSubmit('delete_item', _('Delete selected'));
	}
	else {
		$items_table = $delete_button = null;
	}

	$reportForm->addRow(_('Items'), array(
		$items_table,
		new CButton('add_item', _('Add'), 'return PopUp("popup_bitem.php?config='.BR_DISTRIBUTION_MULTIPLE_PERIODS.
			'&dstfrm='.$reportForm->getName().'", 800, 400, "graph_item_form");'
		),
		$delete_button
	));
	unset($items_table, $delete_button);

	$reportForm->addItemToBottomRow(new CSubmit('report_show', _('Show')));
	$reportForm->addItemToBottomRow(new CSubmit('report_reset', _('Reset')));

	return $reportForm;
}

/**
 * Create bar report form for "Distribution of values for multiple items".
 *
 * @param array $items
 * @param array $periods
 *
 * @return object $reportForm
 */
function valueDistributionFormForMultipleItems($items = array(), $periods = array()){
	$config = getRequest('config', BR_DISTRIBUTION_MULTIPLE_PERIODS);

	$title = getRequest('title', _('Report 2'));
	$xlabel = getRequest('xlabel', '');
	$ylabel = getRequest('ylabel', '');

	$sorttype = getRequest('sorttype', 0);
	$showlegend = getRequest('showlegend', 0);

	$reportForm = new CFormTable(null, null, 'get');
	$reportForm->setTableClass('formtable old-filter');
	$reportForm->setAttribute('name', 'zbx_report');
	$reportForm->setAttribute('id', 'zbx_report');

	if (isset($_REQUEST['report_show']) && is_array($items) && $items && is_array($periods) && $periods) {
		$reportForm->addVar('report_show', 'show');
	}

	$reportForm->addVar('config', $config);

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

	if (is_array($periods) && $periods) {
		$periods_table = new CTableInfo();
		foreach ($periods as $pid => $period) {
			$color = new CColorCell(null, $period['color']);

			$edit_link = 'popup_period.php?period_id='.$pid.'&config='.BR_DISTRIBUTION_MULTIPLE_ITEMS.
				'&dstfrm='.$reportForm->getName().url_param($period['caption'], false, 'caption').'&report_timesince='.
				$period['report_timesince'].'&report_timetill='.$period['report_timetill'].'&color='.$period['color'];

			$caption = new CSpan($period['caption'], 'link');
			$caption->addAction('onclick', "return PopUp('".$edit_link."',840,340,'period_form');");

			$periods_table->addRow(array(
				new CCheckBox('group_pid['.$pid.']'),
				$caption,
				zbx_date2str(DATE_TIME_FORMAT, $period['report_timesince']),
				zbx_date2str(DATE_TIME_FORMAT, $period['report_timetill']),
				$color
			));
		}

		$reportForm->addVar('periods', $periods);

		$delete_button = new CSubmit('delete_period', _('Delete selected'));

	}
	else {
		$periods_table = $delete_button = null;
	}

	$reportForm->addRow(_('Period'), array(
		$periods_table,
		new CButton('add_period', _('Add'), 'return PopUp("popup_period.php?config='.BR_DISTRIBUTION_MULTIPLE_ITEMS.
			'&dstfrm='.$reportForm->getName().'", 840, 340, "period_form");'
		),
		$delete_button
	));
	unset($periods_table, $delete_button);

	if ($items) {
		$items = CMacrosResolverHelper::resolveItemNames($items);

		$items_table = new CTableInfo();
		foreach ($items as $id => &$item) {
			$caption = new CSpan($item['caption'], 'link');
			$caption->onClick('return PopUp("popup_bitem.php?config='.BR_DISTRIBUTION_MULTIPLE_ITEMS.'&list_name=items'.
				'&dstfrm='.$reportForm->GetName().url_param($item, false).url_param($id, false, 'gid').'", 550, 400, "'.
				'graph_item_form");'
			);

			$description = $item['host']['name'].NAME_DELIMITER.$item['name_expanded'];

			$items_table->addRow(array(
				new CCheckBox('group_gid['.$id.']', isset($group_gid[$id])),
				$caption,
				$description,
				graph_item_calc_fnc2str($item['calc_fnc'], 0)
			));

			// once used, unset unnecessary fields so they don't pass to URL. "color" goes in "periods" parameter.
			unset($item['value_type'], $item['host'], $item['name'], $item['name_expanded'], $item['color']);
		}
		unset($item);

		$reportForm->addVar('items', $items);

		$delete_button = new CSubmit('delete_item', _('Delete selected'));
	}
	else {
		$items_table = $delete_button = null;
	}

	$reportForm->addRow(_('Items'), array(
		$items_table,
		new CButton('add_item',_('Add'), "return PopUp('popup_bitem.php?config=".BR_DISTRIBUTION_MULTIPLE_ITEMS.
			"&dstfrm=".$reportForm->getName()."', 550, 400, 'graph_item_form');"
		),
		$delete_button
	));
	unset($items_table, $delete_button);

	$reportForm->addItemToBottomRow(new CSubmit('report_show', _('Show')));
	$reportForm->addItemToBottomRow(new CSubmit('report_reset', _('Reset')));

	return $reportForm;
}

/**
 * Create report bar for for "Compare values for multiple periods"
 *
 * @return object $reportForm
 */
function valueComparisonFormForMultiplePeriods() {
	$config = getRequest('config', BR_DISTRIBUTION_MULTIPLE_PERIODS);

	$title = getRequest('title', _('Report 3'));
	$xlabel = getRequest('xlabel', '');
	$ylabel = getRequest('ylabel', '');

	$scaletype = getRequest('scaletype', TIMEPERIOD_TYPE_WEEKLY);
	$avgperiod = getRequest('avgperiod', TIMEPERIOD_TYPE_DAILY);

	$report_timesince = getRequest('report_timesince', date(TIMESTAMP_FORMAT_ZERO_TIME, time() - SEC_PER_DAY));
	$report_timetill = getRequest('report_timetill', date(TIMESTAMP_FORMAT_ZERO_TIME));

	$itemId = getRequest('itemid', 0);

	$hostids = getRequest('hostids', array());
	$hostids = zbx_toHash($hostids);
	$showlegend = getRequest('showlegend', 0);

	$palette = getRequest('palette', 0);
	$palettetype = getRequest('palettetype', 0);

	$reportForm = new CFormTable(null,null,'get');
	$reportForm->setTableClass('formtable old-filter');
	$reportForm->setAttribute('name','zbx_report');
	$reportForm->setAttribute('id','zbx_report');

	if (isset($_REQUEST['report_show']) && $itemId) {
		$reportForm->addVar('report_show','show');
	}

	$reportForm->addVar('config', $config);
	$reportForm->addVar('report_timesince', date(TIMESTAMP_FORMAT, $report_timesince));
	$reportForm->addVar('report_timetill', date(TIMESTAMP_FORMAT, $report_timetill));

	$reportForm->addRow(_('Title'), new CTextBox('title', $title, 40));
	$reportForm->addRow(_('X label'), new CTextBox('xlabel', $xlabel, 40));
	$reportForm->addRow(_('Y label'), new CTextBox('ylabel', $ylabel, 40));

	$reportForm->addRow(_('Legend'), new CCheckBox('showlegend', $showlegend, null, 1));
	$reportForm->addVar('sortorder', 0);

	$groupids = getRequest('groupids', array());
	$group_tb = new CTweenBox($reportForm, 'groupids', $groupids, 10);

	$db_groups = API::HostGroup()->get(array(
		'real_hosts' => true,
		'output' => array('groupid', 'name')
	));
	order_result($db_groups, 'name');
	foreach ($db_groups as $group) {
		$groupids[$group['groupid']] = $group['groupid'];
		$group_tb->addItem($group['groupid'],$group['name']);
	}

	$reportForm->addRow(_('Groups'), $group_tb->Get(_('Selected groups'), _('Other groups')));

	$groupid = getRequest('groupid', 0);
	$cmbGroups = new CComboBox('groupid', $groupid, 'submit()');
	$cmbGroups->addItem(0, _('All'));
	foreach ($db_groups as $group) {
		$cmbGroups->addItem($group['groupid'], $group['name']);
	}

	$td_groups = new CCol(array(_('Group'), SPACE, $cmbGroups));
	$td_groups->setAttribute('style', 'text-align: right;');

	$host_tb = new CTweenBox($reportForm, 'hostids', $hostids, 10);

	$options = array(
		'real_hosts' => true,
		'output' => array('hostid', 'name')
	);
	if ($groupid > 0) {
		$options['groupids'] = $groupid;
	}
	$db_hosts = API::Host()->get($options);
	$db_hosts = zbx_toHash($db_hosts, 'hostid');
	order_result($db_hosts, 'name');

	foreach ($db_hosts as $hnum => $host) {
		$host_tb->addItem($host['hostid'],$host['name']);
	}

	$options = array(
		'real_hosts' => true,
		'output' => array('hostid', 'name'),
		'hostids' => $hostids,
	);
	$db_hosts2 = API::Host()->get($options);
	order_result($db_hosts2, 'name');
	foreach ($db_hosts2 as $hnum => $host) {
		if (!isset($db_hosts[$host['hostid']])) {
			$host_tb->addItem($host['hostid'], $host['name']);
		}
	}

	$reportForm->addRow(_('Hosts'),
		$host_tb->Get(_('Selected hosts'),
		array(_('Other hosts | Group').SPACE, $cmbGroups)
	));

	$reporttimetab = new CTable(null,'calendar');

	$timeSinceRow = createDateSelector('report_timesince', $report_timesince, 'report_timetill');
	array_unshift($timeSinceRow, _('From'));
	$reporttimetab->addRow($timeSinceRow);

	$timeTillRow = createDateSelector('report_timetill', $report_timetill, 'report_timesince');
	array_unshift($timeTillRow, _('Till'));
	$reporttimetab->addRow($timeTillRow);

	$reportForm->addRow(_('Period'), $reporttimetab);

	$scale = new CComboBox('scaletype', $scaletype);
	$scale->addItem(TIMEPERIOD_TYPE_HOURLY, _('Hourly'));
	$scale->addItem(TIMEPERIOD_TYPE_DAILY, _('Daily'));
	$scale->addItem(TIMEPERIOD_TYPE_WEEKLY, _('Weekly'));
	$scale->addItem(TIMEPERIOD_TYPE_MONTHLY, _('Monthly'));
	$scale->addItem(TIMEPERIOD_TYPE_YEARLY, _('Yearly'));
	$reportForm->addRow(_('Scale'), $scale);

	$avgcmb = new CComboBox('avgperiod', $avgperiod);
	$avgcmb->addItem(TIMEPERIOD_TYPE_HOURLY, _('Hourly'));
	$avgcmb->addItem(TIMEPERIOD_TYPE_DAILY, _('Daily'));
	$avgcmb->addItem(TIMEPERIOD_TYPE_WEEKLY, _('Weekly'));
	$avgcmb->addItem(TIMEPERIOD_TYPE_MONTHLY, _('Monthly'));
	$avgcmb->addItem(TIMEPERIOD_TYPE_YEARLY, _('Yearly'));
	$reportForm->addRow(_('Average by'), $avgcmb);

	$itemName = '';
	if ($itemId) {
		$items = CMacrosResolverHelper::resolveItemNames(array(get_item_by_itemid($itemId)));
		$item = reset($items);

		$itemName = $item['name_expanded'];
	}

	$itemidVar = new CVar('itemid', $itemId, 'itemid');
	$reportForm->addItem($itemidVar);

	$txtCondVal = new CTextBox('item_name', $itemName, 50, 'yes');
	$txtCondVal->setAttribute('id', 'item_name');

	$btnSelect = new CButton('btn1', _('Select'),
		'return PopUp("popup.php?dstfrm='.$reportForm->GetName().
			'&dstfld1=itemid'.
			'&dstfld2=item_name'.
			'&srctbl=items'.
			'&srcfld1=itemid'.
			'&srcfld2=name'.
			'&monitored_hosts=1");',
		'T'
	);

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

	$reportForm->addRow(_('Palette'), array($paletteCmb, $paletteTypeCmb));

	$reportForm->addItemToBottomRow(new CSubmit('report_show', _('Show')));
	$reportForm->addItemToBottomRow(new CSubmit('report_reset', _('Reset')));

	return $reportForm;
}

/**
 * Validate items array for bar reports - IDs, color, permissions, etc.
 * Color validation for graph items is only for "Distribution of values for multiple periods" section ($config == 1).
 * Automatically set caption like item name if none is set. If no axis side is set, set LEFT side as default.
 *
 * @param array $items
 *
 * @return mixed	valid items array on success or false on failure
 */
function validateBarReportItems($items = array()) {
	$config = getRequest('config', BR_DISTRIBUTION_MULTIPLE_PERIODS);

	if (!isset($items) || !$items) {
		return false;
	}

	$fields = array('itemid', 'calc_fnc');
	if ($config == BR_DISTRIBUTION_MULTIPLE_PERIODS) {
		array_push($fields, 'color');
	}

	foreach ($items as $item) {
		foreach ($fields as $field) {
			if (!isset($item[$field])) {
				show_error_message(_s('Missing "%1$s" field for item.', $field));
				return false;
			}
		}

		$itemIds[$item['itemid']] = $item['itemid'];
	}

	$validItems = API::Item()->get(array(
		'output' => array('itemid', 'hostid', 'name', 'key_', 'value_type'),
		'selectHosts' => array('name'),
		'webitems' => true,
		'itemids' => $itemIds,
		'preservekeys' => true
	));

	$items = zbx_toHash($items, 'itemid');

	$allowedValueTypes = array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64);

	foreach ($validItems as &$item) {
		if (!in_array($item['value_type'], $allowedValueTypes)) {
			show_error_message(_s('Cannot add a non-numeric item "%1$s".', $item['name']));
			return false;
		}

		// add host name and set caption like item name for valid items
		$item['host'] = reset($item['hosts']);
		unset($item['hosts']);

		// if no caption set, set default caption like item name
		if (!isset($items[$item['itemid']]['caption']) || zbx_empty($items[$item['itemid']]['caption'])) {
			$item['caption'] = $item['name'];
		}
		else {
			$validItems[$item['itemid']]['caption'] = $items[$item['itemid']]['caption'];
		}

		if (!isset($items[$item['itemid']]['axisside']) || zbx_empty($items[$item['itemid']]['axisside'])) {
			$items[$item['itemid']]['axisside'] = GRAPH_YAXIS_SIDE_LEFT;
		}
	}
	unset($item);

	// check axis value. 0 = count
	$calcFncValidator = new CLimitedSetValidator(array(
		'values' => array(0, CALC_FNC_MIN, CALC_FNC_AVG, CALC_FNC_MAX)
	));
	$axisValidator = new CLimitedSetValidator(array(
		'values' => array(GRAPH_YAXIS_SIDE_LEFT, GRAPH_YAXIS_SIDE_RIGHT)
	));

	if ($config == BR_DISTRIBUTION_MULTIPLE_PERIODS) {
		$colorValidator = new CColorValidator();
	}
	foreach ($items as $item) {
		if (!$calcFncValidator->validate($item['calc_fnc'])) {
			show_error_message(_s('Incorrect value for field "%1$s".', 'calc_fnc'));
			return false;
		}
		if (!$axisValidator->validate($item['axisside'])) {
			show_error_message(_s('Incorrect value for field "%1$s".', 'axisside'));
			return false;
		}
		if ($config == BR_DISTRIBUTION_MULTIPLE_PERIODS) {
			if (!$colorValidator->validate($item['color'])) {
				show_error_message($colorValidator->getError());
				return false;
			}
			$validItems[$item['itemid']]['color'] = $item['color'];
		}

		$validItems[$item['itemid']]['calc_fnc'] = $item['calc_fnc'];
		$validItems[$item['itemid']]['axisside'] = $item['axisside'];
	}

	return $validItems;
}

/**
 * Validate periods array for bar reports - time since, time till and color.
 * Automatically set caption if none is set.
 *
 * @param array $periods
 *
 * @return mixed	valid periods array on success or false on failure
 */
function validateBarReportPeriods($periods = array()) {
	if (!isset($periods) || !$periods) {
		return false;
	}
	$fields = array('report_timesince', 'report_timetill', 'color');
	$colorValidator = new CColorValidator();

	foreach ($periods as &$period) {
		foreach ($fields as $field) {
			if (!isset($period[$field]) || !$period[$field]) {
				show_error_message(_s('Missing "%1$s" field for period.', $field));
				return false;
			}
		}

		if (!$colorValidator->validate($period['color'])) {
			show_error_message($colorValidator->getError());
			return false;
		}
		if (!validateUnixTime($period['report_timesince'])) {
			show_error_message(_s('Invalid period for field "%1$s".', 'report_timesince'));
			return false;
		}
		if (!validateUnixTime($period['report_timetill'])) {
			show_error_message(_s('Invalid period for field "%1$s".', 'report_timetill'));
			return false;
		}
		if (!isset($period['caption']) || zbx_empty($period['caption'])) {
			$period['caption'] = zbx_date2str(DATE_TIME_FORMAT, $period['report_timesince']).' - '.
				zbx_date2str(DATE_TIME_FORMAT, $period['report_timetill']);
		}

	}
	unset($period);

	return $periods;
}
