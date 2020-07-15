<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * @var CPartial $this
 */

$form = (new CForm('GET', 'history.php'))
	->cleanItems()
	->setName('items')
	->addItem(new CVar('action', HISTORY_BATCH_GRAPH));

$table = (new CTableInfo())->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);

// Latest data header.

$col_check_all = (new CColHeader(
	(new CCheckBox('all_items'))->onClick("checkAll('".$form->getName()."', 'all_items', 'itemids');")
));

$view_url = $data['view_curl']->getUrl();

$col_host = make_sorting_header(_('Host'), 'host', $data['sort_field'], $data['sort_order'], $view_url);
$col_name = make_sorting_header(_('Name'), 'name', $data['sort_field'], $data['sort_order'], $view_url);

if ($data['filter']['show_details']) {
	$table->setHeader([
		$col_check_all->addStyle('width: 15px;'),
		$col_host->addStyle('width: 13%'),
		$col_name->addStyle('width: 21%'),
		(new CColHeader(_('Interval')))->addStyle('width: 5%'),
		(new CColHeader(_('History')))->addStyle('width: 5%'),
		(new CColHeader(_('Trends')))->addStyle('width: 5%'),
		(new CColHeader(_('Type')))->addStyle('width: 8%'),
		(new CColHeader(_('Last check')))->addStyle('width: 14%'),
		(new CColHeader(_('Last value')))->addStyle('width: 14%'),
		(new CColHeader(_x('Change', 'noun')))->addStyle('width: 10%'),
		(new CColHeader())->addStyle('width: 5%'),
		(new CColHeader(_('Info')))->addStyle('width: 35px')
	]);

	$table_columns = 12;
}
else {
	$table->setHeader([
		$col_check_all->addStyle('width: 15px'),
		$col_host->addStyle('width: 17%'),
		$col_name->addStyle('width: 40%'),
		(new CColHeader(_('Last check')))->addStyle('width: 14%'),
		(new CColHeader(_('Last value')))->addStyle('width: 14%'),
		(new CColHeader(_x('Change', 'noun')))->addStyle('width: 10%'),
		(new CColHeader())->addStyle('width: 5%')
	]);

	$table_columns = 7;
}

// Latest data rows.

$config = select_config();

$simple_interval_parser = new CSimpleIntervalParser();
$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);

$last_hostid = null;
$last_applicationid = null;

foreach ($data['rows'] as $row) {
	$item = $data['items'][$row['itemid']];

	// Secondary header for the next host or application.

	$is_next_host = $item['hostid'] !== $last_hostid;
	$is_next_application = $row['applicationid'] !== $last_applicationid;

	if ($is_next_host || $is_next_application) {
		$host = $data['hosts'][$item['hostid']];

		$col_host = (new CLinkAction($host['name']))->setMenuPopup(CMenuPopupHelper::getHost($item['hostid']));

		if ($host['status'] == HOST_STATUS_NOT_MONITORED) {
			$col_host->addClass(ZBX_STYLE_RED);
		}

		$application_name = $row['applicationid']
			? $data['applications'][$row['applicationid']]['name']
			: '- '.('other').' -';

		$application_size = $data['applications_size'][$item['hostid']][$row['applicationid']];

		$col_name = (new CCol([bold($application_name), ' ('._n('%1$s Item', '%1$s Items', $application_size).')']))
			->setColSpan($table_columns - 2);

		$table->addRow(['', $col_host, $col_name]);

		$last_hostid = $item['hostid'];
		$last_applicationid = $row['applicationid'];
	}

	// Row history data preparation.

	if (array_key_exists($item['itemid'], $data['history'])) {
		$last_history = (count($data['history'][$item['itemid']]) > 0) ? $data['history'][$item['itemid']][0] : null;
		$prev_history = (count($data['history'][$item['itemid']]) > 1) ? $data['history'][$item['itemid']][1] : null;
	}
	else {
		$last_history = null;
		$prev_history = null;
	}

	if ($last_history) {
		$last_check = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $last_history['clock']);
		$last_value = formatHistoryValue($last_history['value'], $item, false);
		$change = '';

		if ($prev_history && in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
			$history_diff = $last_history['value'] - $prev_history['value'];

			if ($history_diff != 0) {
				if ($history_diff > 0) {
					$change = '+';
				}

				// The change must be calculated as uptime for the 'unixtime'.
				$change .= convertUnits([
					'value' => $history_diff,
					'units' => ($item['units'] === 'unixtime') ? 'uptime' : $item['units']
				]);
			}
		}
	}
	else {
		$last_check = '';
		$last_value = '';
		$change = '';
	}

	// Other row data preparation.

	if ($config['hk_history_global']) {
		$keep_history = timeUnitToSeconds($config['hk_history']);
		$item_history = $config['hk_history'];
	}
	elseif ($simple_interval_parser->parse($item['history']) == CParser::PARSE_SUCCESS) {
		$keep_history = timeUnitToSeconds($item['history']);
		$item_history = $item['history'];
	}
	else {
		$keep_history = 0;
		$item_history = (new CSpan($item['history']))->addClass(ZBX_STYLE_RED);
	}

	if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64) {
		if ($config['hk_trends_global']) {
			$keep_trends = timeUnitToSeconds($config['hk_trends']);
			$item_trends = $config['hk_trends'];
		}
		elseif ($simple_interval_parser->parse($item['trends']) == CParser::PARSE_SUCCESS) {
			$keep_trends = timeUnitToSeconds($item['trends']);
			$item_trends = $item['trends'];
		}
		else {
			$keep_trends = 0;
			$item_trends = (new CSpan($item['trends']))->addClass(ZBX_STYLE_RED);
		}
	}
	else {
		$keep_trends = 0;
		$item_trends = '';
	}

	$is_graph = ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64);

	$checkbox = (new CCheckBox('itemids['.$item['itemid'].']', $item['itemid']))->setEnabled($is_graph);

	$item_name = (new CDiv([
		(new CSpan($item['name_expanded']))->addClass('label'),
		($item['description'] !== '') ? makeDescriptionIcon($item['description']) : null
	]))->addClass('action-container');

	if ($keep_history != 0 || $keep_trends != 0) {
		$actions = new CLink($is_graph ? _('Graph') : _('History'), (new CUrl('history.php'))
			->setArgument('action', $is_graph ? HISTORY_GRAPH : HISTORY_VALUES)
			->setArgument('itemids[]', $item['itemid'])
		);
	}
	else {
		$actions = '';
	}

	$state_css = ($item['state'] == ITEM_STATE_NOTSUPPORTED) ? ZBX_STYLE_GREY : null;

	if ($data['filter']['show_details']) {
		if ($item['type'] == ITEM_TYPE_HTTPTEST) {
			$item_key = (new CSpan($item['key_expanded']))->addClass(ZBX_STYLE_GREEN);
		}
		else {
			$item_key = (new CLink($item['key_expanded'], (new CUrl('items.php'))
				->setArgument('form', 'update')
				->setArgument('itemid', $item['itemid'])
			))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREEN);
		}

		if (in_array($item['type'], [ITEM_TYPE_SNMPTRAP, ITEM_TYPE_TRAPPER, ITEM_TYPE_DEPENDENT])) {
			$item_delay = '';
		}
		elseif ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
			$item_delay = $update_interval_parser->getDelay();

			if ($item_delay[0] === '{') {
				$item_delay = (new CSpan($item_delay))->addClass(ZBX_STYLE_RED);
			}
		}
		else {
			$item_delay = (new CSpan($item['delay']))->addClass(ZBX_STYLE_RED);
		}

		$item_icons = [];
		if ($item['status'] == ITEM_STATUS_ACTIVE && $item['error'] !== '') {
			$item_icons[] = makeErrorIcon($item['error']);
		}

		$row = new CRow([
			$checkbox,
			'',
			(new CCol([$item_name, $item_key]))->addClass($state_css),
			(new CCol($item_delay))->addClass($state_css),
			(new CCol($item_history))->addClass($state_css),
			(new CCol($item_trends))->addClass($state_css),
			(new CCol(item_type2str($item['type'])))->addClass($state_css),
			(new CCol($last_check))->addClass($state_css),
			(new CCol($last_value))->addClass($state_css),
			(new CCol($change))->addClass($state_css),
			$actions,
			makeInformationList($item_icons)
		]);
	}
	else {
		$row = new CRow([
			$checkbox,
			'',
			(new CCol($item_name))->addClass($state_css),
			(new CCol($last_check))->addClass($state_css),
			(new CCol($last_value))->addClass($state_css),
			(new CCol($change))->addClass($state_css),
			$actions
		]);
	}

	$table->addRow($row);
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('graphtype', 'itemids', [
		GRAPH_TYPE_STACKED => ['name' => _('Display stacked graph')],
		GRAPH_TYPE_NORMAL => ['name' => _('Display graph')]
	])
]);

echo $form;
