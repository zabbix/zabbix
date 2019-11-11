<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


$form = (new CForm('GET', 'history.php'))
	->setName('items')
	->addItem(new CVar('action', HISTORY_BATCH_GRAPH));
// table
$table = (new CTableInfo())->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);
if (!$data['filterSet']) {
	$table->setNoDataMessage(_('Specify some filter condition to see the values.'));
}

$toggle_all = (new CColHeader(
	(new CSimpleButton())
		->addClass(ZBX_STYLE_TREEVIEW)
		->addClass('app-list-toggle-all')
		->addItem(new CSpan())
))->addStyle('width: 18px');

$check_all = (new CColHeader(
	(new CCheckBox('all_items'))->onClick("checkAll('".$form->getName()."', 'all_items', 'itemids');")
))->addStyle('width: 15px');

if ($data['filter']['showDetails']) {
	$table->setHeader([
		$toggle_all,
		$check_all,
		make_sorting_header(_('Host'), 'host', $data['sortField'], $data['sortOrder'], $data['view_url'])->addStyle('width: 13%'),
		make_sorting_header(_('Name'), 'name', $data['sortField'], $data['sortOrder'], $data['view_url'])->addStyle('width: 21%'),
		(new CColHeader(_('Interval')))->addStyle('width: 5%'),
		(new CColHeader(_('History')))->addStyle('width: 5%'),
		(new CColHeader(_('Trends')))->addStyle('width: 5%'),
		(new CColHeader(_('Type')))->addStyle('width: 8%'),
		make_sorting_header(_('Last check'), 'lastclock', $data['sortField'], $data['sortOrder'], $data['view_url'])->addStyle('width: 14%'),
		(new CColHeader(_('Last value')))->addStyle('width: 14%'),
		(new CColHeader(_x('Change', 'noun')))->addStyle('width: 10%'),
		(new CColHeader())->addStyle('width: 5%'),
		(new CColHeader(_('Info')))->addStyle('width: 35px')
	]);
}
else {
	$table->setHeader([
		$toggle_all,
		$check_all,
		make_sorting_header(_('Host'), 'host', $data['sortField'], $data['sortOrder'], $data['view_url'])->addStyle('width: 17%'),
		make_sorting_header(_('Name'), 'name', $data['sortField'], $data['sortOrder'], $data['view_url'])->addStyle('width: 40%'),
		make_sorting_header(_('Last check'), 'lastclock', $data['sortField'], $data['sortOrder'], $data['view_url'])->addStyle('width: 14%'),
		(new CColHeader(_('Last value')))->addStyle('width: 14%'),
		(new CColHeader(_x('Change', 'noun')))->addStyle('width: 10%'),
		(new CColHeader())->addStyle('width: 5%')
	]);
}

$tab_rows = [];

$config = select_config();

// Resolve delay, history and trend macros.
$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);
$simple_interval_parser = new CSimpleIntervalParser();

foreach ($data['items'] as &$item) {
	if ($item['type'] == ITEM_TYPE_SNMPTRAP || $item['type'] == ITEM_TYPE_TRAPPER
			|| $item['type'] == ITEM_TYPE_DEPENDENT) {
		$item['delay'] = '';
	}
	elseif ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
		$item['delay'] = $update_interval_parser->getDelay();

		if ($item['delay'][0] === '{') {
			$item['delay'] = (new CSpan($item['delay']))->addClass(ZBX_STYLE_RED);
		}
	}
	else {
		$item['delay'] = (new CSpan($item['delay']))->addClass(ZBX_STYLE_RED);
	}

	if ($config['hk_history_global']) {
		$keep_history = timeUnitToSeconds($config['hk_history']);
		$item['history'] = $config['hk_history'];
	}
	elseif ($simple_interval_parser->parse($item['history']) == CParser::PARSE_SUCCESS) {
		$keep_history = timeUnitToSeconds($item['history']);
	}
	else {
		$keep_history = 0;
		$item['history'] = (new CSpan($item['history']))->addClass(ZBX_STYLE_RED);
	}

	if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64) {
		if ($config['hk_trends_global']) {
			$keep_trends = timeUnitToSeconds($config['hk_trends']);
			$item['trends'] = $config['hk_trends'];
		}
		elseif ($simple_interval_parser->parse($item['trends']) == CParser::PARSE_SUCCESS) {
			$keep_trends = timeUnitToSeconds($item['trends']);
		}
		else {
			$keep_trends = 0;
			$item['trends'] = (new CSpan($item['trends']))->addClass(ZBX_STYLE_RED);
		}
	}
	else {
		$keep_trends = 0;
		$item['trends'] = '';
	}

	$item['show_link'] = ($keep_history != 0 || $keep_trends != 0);
}
unset($item);

foreach ($data['items'] as $key => $item) {
	if (!$item['applications']) {
		continue;
	}

	$lastHistory = isset($data['history'][$item['itemid']][0]) ? $data['history'][$item['itemid']][0] : null;
	$prevHistory = isset($data['history'][$item['itemid']][1]) ? $data['history'][$item['itemid']][1] : null;

	if (strpos($item['units'], ',') !== false) {
		list($item['units'], $item['unitsLong']) = explode(',', $item['units']);
	}
	else {
		$item['unitsLong'] = '';
	}

	// last check time and last value
	if ($lastHistory) {
		$lastClock = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $lastHistory['clock']);
		$lastValue = formatHistoryValue($lastHistory['value'], $item, false);
	}
	else {
		$lastClock = UNKNOWN_VALUE;
		$lastValue = UNKNOWN_VALUE;
	}

	// change
	$digits = ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT) ? ZBX_UNITS_ROUNDOFF_MIDDLE_LIMIT : 0;
	if ($lastHistory && $prevHistory
			&& ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64)
			&& (bcsub($lastHistory['value'], $prevHistory['value'], $digits) != 0)) {

		$change = '';
		if (($lastHistory['value'] - $prevHistory['value']) > 0) {
			$change = '+';
		}

		// for 'unixtime' change should be calculated as uptime
		$change .= convert_units([
			'value' => bcsub($lastHistory['value'], $prevHistory['value'], $digits),
			'units' => $item['units'] == 'unixtime' ? 'uptime' : $item['units']
		]);
	}
	else {
		$change = UNKNOWN_VALUE;
	}


	$checkbox = (new CCheckBox('itemids['.$item['itemid'].']', $item['itemid']));

	if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64) {
		$actions = $item['show_link']
			? new CLink(_('Graph'), 'history.php?action='.HISTORY_GRAPH.'&itemids[]='.$item['itemid'])
			: UNKNOWN_VALUE;
	}
	else {
		$actions = $item['show_link']
			? new CLink(_('History'), 'history.php?action='.HISTORY_VALUES.'&itemids[]='.$item['itemid'])
			: UNKNOWN_VALUE;
		$checkbox->setEnabled(false);
	}

	$item_name = (new CDiv([
		(new CSpan($item['name_expanded']))->addClass('label'),
		($item['description'] !== '') ? makeDescriptionIcon($item['description']) : null
	]))->addClass('action-container');

	$state_css = ($item['state'] == ITEM_STATE_NOTSUPPORTED) ? ZBX_STYLE_GREY : null;

	if ($data['filter']['showDetails']) {
		// item key
		$itemKey = ($item['type'] == ITEM_TYPE_HTTPTEST)
			? (new CSpan($item['key_expanded']))->addClass(ZBX_STYLE_GREEN)
			: (new CLink($item['key_expanded'], 'items.php?form=update&itemid='.$item['itemid']))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREEN);

		$info_icons = [];
		if ($item['status'] == ITEM_STATUS_ACTIVE && $item['error'] !== '') {
			$info_icons[] = makeErrorIcon($item['error']);
		}

		$row = new CRow([
			'',
			$checkbox,
			'',
			(new CCol([$item_name, $itemKey]))->addClass($state_css),
			(new CCol($item['delay']))->addClass($state_css),
			(new CCol($item['history']))->addClass($state_css),
			(new CCol($item['trends']))->addClass($state_css),
			(new CCol(item_type2str($item['type'])))->addClass($state_css),
			(new CCol($lastClock))->addClass($state_css),
			(new CCol($lastValue))->addClass($state_css),
			(new CCol($change))->addClass($state_css),
			$actions,
			makeInformationList($info_icons)
		]);
	}
	else {
		$row = new CRow([
			'',
			$checkbox,
			'',
			(new CCol($item_name))->addClass($state_css),
			(new CCol($lastClock))->addClass($state_css),
			(new CCol($lastValue))->addClass($state_css),
			(new CCol($change))->addClass($state_css),
			$actions
		]);
	}

	// add the item row to each application tab
	foreach ($item['applications'] as $itemApplication) {
		$applicationId = $itemApplication['applicationid'];

		if (isset($data['applications'][$applicationId])) {
			$data['applications'][$applicationId]['item_cnt']++;
			// objects may have different properties, so it's better to use a copy of it
			$tab_rows[$applicationId][] = clone $row;
		}
	}

	// remove items with applications from the collection
	unset($data['items'][$key]);
}

foreach ($data['applications'] as $appid => $dbApp) {
	$host = $data['hosts'][$dbApp['hostid']];

	if(!isset($tab_rows[$appid])) continue;

	$appRows = $tab_rows[$appid];

	$open_state = CProfile::get('web.latest.toggle', null, $dbApp['applicationid']);

	$hostName = (new CLinkAction($host['name']))->setMenuPopup(CMenuPopupHelper::getHost($dbApp['hostid']));
	if ($host['status'] == HOST_STATUS_NOT_MONITORED) {
		$hostName->addClass(ZBX_STYLE_RED);
	}

	// add toggle row
	$table->addRow([
		(new CSimpleButton())
			->addClass(ZBX_STYLE_TREEVIEW)
			->addClass('app-list-toggle')
			->setAttribute('data-app-id', $dbApp['applicationid'])
			->setAttribute('data-open-state', $open_state)
			->addItem(new CSpan()),
		'',
		$hostName,
		(new CCol([bold($dbApp['name']), ' ('._n('%1$s Item', '%1$s Items', $dbApp['item_cnt']).')']))
			->setColSpan($data['filter']['showDetails'] ? 10 : 5)
	]);

	// add toggle sub rows
	foreach ($appRows as $row) {
		$row->setAttribute('parent_app_id', $dbApp['applicationid']);
		$table->addRow($row);
	}
}

/**
 * Display OTHER ITEMS (which are not linked to application)
 */
$tab_rows = [];
foreach ($data['items'] as $item) {
	$lastHistory = isset($data['history'][$item['itemid']][0]) ? $data['history'][$item['itemid']][0] : null;
	$prevHistory = isset($data['history'][$item['itemid']][1]) ? $data['history'][$item['itemid']][1] : null;

	if (strpos($item['units'], ',') !== false)
		list($item['units'], $item['unitsLong']) = explode(',', $item['units']);
	else
		$item['unitsLong'] = '';

	// last check time and last value
	if ($lastHistory) {
		$lastClock = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $lastHistory['clock']);
		$lastValue = formatHistoryValue($lastHistory['value'], $item, false);
	}
	else {
		$lastClock = UNKNOWN_VALUE;
		$lastValue = UNKNOWN_VALUE;
	}

	// column "change"
	$digits = ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT) ? ZBX_UNITS_ROUNDOFF_MIDDLE_LIMIT : 0;
	if (isset($lastHistory['value']) && isset($prevHistory['value'])
			&& ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64)
			&& (bcsub($lastHistory['value'], $prevHistory['value'], $digits) != 0)) {

		$change = '';
		if (($lastHistory['value'] - $prevHistory['value']) > 0) {
			$change = '+';
		}

		// for 'unixtime' change should be calculated as uptime
		$change .= convert_units([
			'value' => bcsub($lastHistory['value'], $prevHistory['value'], $digits),
			'units' => $item['units'] == 'unixtime' ? 'uptime' : $item['units']
		]);
	}
	else {
		$change = UNKNOWN_VALUE;
	}

	$checkbox = (new CCheckBox('itemids['.$item['itemid'].']', $item['itemid']));

	if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64) {
		$actions = $item['show_link']
			? new CLink(_('Graph'), 'history.php?action='.HISTORY_GRAPH.'&itemids[]='.$item['itemid'])
			: UNKNOWN_VALUE;
	}
	else {
		$actions = $item['show_link']
			? new CLink(_('History'), 'history.php?action='.HISTORY_VALUES.'&itemids[]='.$item['itemid'])
			: UNKNOWN_VALUE;
		$checkbox->setEnabled(false);
	}

	$item_name = (new CDiv([
		(new CSpan($item['name_expanded']))->addClass('label'),
		($item['description'] !== '') ? makeDescriptionIcon($item['description']) : null
	]))->addClass('action-container');

	$state_css = ($item['state'] == ITEM_STATE_NOTSUPPORTED) ? ZBX_STYLE_GREY : null;

	$host = $data['hosts'][$item['hostid']];
	if ($data['filter']['showDetails']) {
		// item key
		$itemKey = ($item['type'] == ITEM_TYPE_HTTPTEST)
			? (new CSpan($item['key_expanded']))->addClass(ZBX_STYLE_GREEN)
			: (new CLink($item['key_expanded'], 'items.php?form=update&itemid='.$item['itemid']))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREEN);

		$info_icons = [];
		if ($item['status'] == ITEM_STATUS_ACTIVE && $item['error'] !== '') {
			$info_icons[] = makeErrorIcon($item['error']);
		}

		$row = new CRow([
			'',
			$checkbox,
			'',
			(new CCol([$item_name, $itemKey]))->addClass($state_css),
			(new CCol($item['delay']))->addClass($state_css),
			(new CCol($item['history']))->addClass($state_css),
			(new CCol($item['trends']))->addClass($state_css),
			(new CCol(item_type2str($item['type'])))->addClass($state_css),
			(new CCol($lastClock))->addClass($state_css),
			(new CCol($lastValue))->addClass($state_css),
			(new CCol($change))->addClass($state_css),
			$actions,
			makeInformationList($info_icons)
		]);
	}
	else {
		$row = new CRow([
			'',
			$checkbox,
			'',
			(new CCol($item_name))->addClass($state_css),
			(new CCol($lastClock))->addClass($state_css),
			(new CCol($lastValue))->addClass($state_css),
			(new CCol($change))->addClass($state_css),
			$actions
		]);
	}

	$data['hosts'][$item['hostid']]['item_cnt']++;
	$tab_rows[$item['hostid']][] = $row;
}

foreach ($data['hosts'] as $hostId => $dbHost) {
	$host = $data['hosts'][$dbHost['hostid']];

	if(!isset($tab_rows[$hostId])) {
		continue;
	}
	$appRows = $tab_rows[$hostId];

	$open_state = CProfile::get('web.latest.toggle_other', null, $host['hostid']);

	$hostName = (new CLinkAction($host['name']))->setMenuPopup(CMenuPopupHelper::getHost($hostId));
	if ($host['status'] == HOST_STATUS_NOT_MONITORED) {
		$hostName->addClass(ZBX_STYLE_RED);
	}

	// add toggle row
	$table->addRow([
		(new CSimpleButton())
			->addClass(ZBX_STYLE_TREEVIEW)
			->addClass('app-list-toggle')
			->setAttribute('data-host-id', $host['hostid'])
			->setAttribute('data-open-state', $open_state)
			->addItem(new CSpan()),
		'',
		$hostName,
		(new CCol([bold('- '.('other').' -'), ' ('._n('%1$s Item', '%1$s Items', $dbHost['item_cnt']).')']))
			->setColSpan($data['filter']['showDetails'] ? 10 : 5)
	]);

	// add toggle sub rows
	foreach($appRows as $row) {
		$row->setAttribute('parent_host_id', $host['hostid']);
		$table->addRow($row);
	}
}

$form->addItem([
	$table,
	new CActionButtonList('graphtype', 'itemids', [
		GRAPH_TYPE_STACKED => ['name' => _('Display stacked graph')],
		GRAPH_TYPE_NORMAL => ['name' => _('Display graph')]
	])
]);

return $form->toString();
