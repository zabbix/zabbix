<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * @var array    $data
 */

$form = (new CForm('GET', 'history.php'))
	->cleanItems()
	->setName('items')
	->addItem(new CVar('action', HISTORY_BATCH_GRAPH));

$table = (new CTableInfo())->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);

// Latest data header.
$col_check_all = new CColHeader(
	(new CCheckBox('all_items'))->onClick("checkAll('".$form->getName()."', 'all_items', 'itemids');")
);

$view_url = $data['view_curl']->getUrl();

$col_host = make_sorting_header(_('Host'), 'host', $data['sort_field'], $data['sort_order'], $view_url);
$col_name = make_sorting_header(_('Name'), 'name', $data['sort_field'], $data['sort_order'], $view_url);

$simple_interval_parser = new CSimpleIntervalParser();
$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);

if ($data['filter']['show_tags'] == SHOW_TAGS_NONE) {
	$tags_header = null;
}
else {
	$tags_header = new CColHeader(_('Tags'));

	switch ($data['filter']['show_tags']) {
		case SHOW_TAGS_1:
			$tags_header->addClass(ZBX_STYLE_COLUMN_TAGS_1);
			break;

		case SHOW_TAGS_2:
			$tags_header->addClass(ZBX_STYLE_COLUMN_TAGS_2);
			break;

		case SHOW_TAGS_3:
			$tags_header->addClass(ZBX_STYLE_COLUMN_TAGS_3);
			break;
	}
}

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
		$tags_header,
		(new CColHeader())->addStyle('width: 6%'),
		(new CColHeader(_('Info')))->addStyle('width: 35px')
	]);
}
else {
	$table->setHeader([
		$col_check_all->addStyle('width: 15px'),
		$col_host->addStyle('width: 17%'),
		$col_name->addStyle('width: 40%'),
		(new CColHeader(_('Last check')))->addStyle('width: 14%'),
		(new CColHeader(_('Last value')))->addStyle('width: 14%'),
		(new CColHeader(_x('Change', 'noun')))->addStyle('width: 10%'),
		$tags_header,
		(new CColHeader())->addStyle('width: 6%'),
		(new CColHeader(_('Info')))->addStyle('width: 35px')
	]);
}

// Latest data rows.
foreach ($data['items'] as $itemid => $item) {
	$is_graph = ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64);
	$checkbox = (new CCheckBox('itemids['.$itemid.']', $itemid))->setEnabled($is_graph);
	$state_css = ($item['state'] == ITEM_STATE_NOTSUPPORTED) ? ZBX_STYLE_GREY : null;

	$item_name = (new CDiv([
		(new CLinkAction($item['name']))
			->setMenuPopup(CMenuPopupHelper::getItem(['itemid' => $itemid])),
		($item['description_expanded'] !== '') ? makeDescriptionIcon($item['description_expanded']) : null
	]))->addClass(ZBX_STYLE_ACTION_CONTAINER);

	// Row history data preparation.
	$last_history = array_key_exists($itemid, $data['history'])
		? ((count($data['history'][$itemid]) > 0) ? $data['history'][$itemid][0] : null)
		: null;

	if ($last_history) {
		$prev_history = (count($data['history'][$itemid]) > 1) ? $data['history'][$itemid][1] : null;

		$last_check = (new CSpan(zbx_date2age($last_history['clock'])))
			->addClass(ZBX_STYLE_CURSOR_POINTER)
			->setHint(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $last_history['clock']), '', true, '', 0);

		$last_value = (new CSpan(formatHistoryValue($last_history['value'], $item, false)))
			->addClass(ZBX_STYLE_CURSOR_POINTER)
			->setHint(
				(new CDiv(mb_substr($last_history['value'], 0, ZBX_HINTBOX_CONTENT_LIMIT)))->addClass(ZBX_STYLE_HINTBOX_WRAP),
				'', true, '', 0
			);

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
	if ($data['config']['hk_history_global']) {
		$keep_history = timeUnitToSeconds($data['config']['hk_history']);
		$item_history = $data['config']['hk_history'];
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
		if ($data['config']['hk_trends_global']) {
			$keep_trends = timeUnitToSeconds($data['config']['hk_trends']);
			$item_trends = $data['config']['hk_trends'];
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

	if ($keep_history != 0 || $keep_trends != 0) {
		$actions = new CLink($is_graph ? _('Graph') : _('History'), (new CUrl('history.php'))
			->setArgument('action', $is_graph ? HISTORY_GRAPH : HISTORY_VALUES)
			->setArgument('itemids[]', $item['itemid'])
		);
	}
	else {
		$actions = '';
	}

	$host = $data['hosts'][$item['hostid']];

	$maintenance_icon = '';

	if ($host['status'] == HOST_STATUS_MONITORED && $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
		if (array_key_exists($host['maintenanceid'], $data['maintenances'])) {
			$maintenance = $data['maintenances'][$host['maintenanceid']];
			$maintenance_icon = makeMaintenanceIcon($host['maintenance_type'], $maintenance['name'],
				$maintenance['description']
			);
		}
		else {
			$maintenance_icon = makeMaintenanceIcon($host['maintenance_type'],
				_('Inaccessible maintenance'), ''
			);
		}
	}

	$host_name_container = (new CDiv([
		(new CLinkAction($host['name']))
			->addClass($host['status'] == HOST_STATUS_NOT_MONITORED ? ZBX_STYLE_RED : null)
			->setMenuPopup(CMenuPopupHelper::getHost($item['hostid'])),
		$maintenance_icon
	]))->addClass(ZBX_STYLE_ACTION_CONTAINER);

	$item_icons = [];
	if ($item['status'] == ITEM_STATUS_ACTIVE && $item['error'] !== '') {
		$item_icons[] = makeErrorIcon($item['error']);
	}

	if ($data['filter']['show_details']) {
		$item_key = (new CSpan($item['key_expanded']))->addClass(ZBX_STYLE_GREEN);

		if (in_array($item['type'], [ITEM_TYPE_SNMPTRAP, ITEM_TYPE_TRAPPER, ITEM_TYPE_DEPENDENT])
				|| ($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($item['key_expanded'], 'mqtt.get', 8) === 0)) {
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

		$table_row = new CRow([
			$checkbox,
			$host_name_container,
			(new CCol([$item_name, $item_key]))->addClass($state_css),
			(new CCol($item_delay))->addClass($state_css),
			(new CCol($item_history))->addClass($state_css),
			(new CCol($item_trends))->addClass($state_css),
			(new CCol(item_type2str($item['type'])))->addClass($state_css),
			(new CCol($last_check))->addClass($state_css),
			(new CCol($last_value))->addClass($state_css),
			(new CCol($change))->addClass($state_css),
			($data['filter']['show_tags'] != SHOW_TAGS_NONE) ? $data['tags'][$itemid] : null,
			$actions,
			makeInformationList($item_icons)
		]);
	}
	else {
		$table_row = new CRow([
			$checkbox,
			$host_name_container,
			(new CCol($item_name))->addClass($state_css),
			(new CCol($last_check))->addClass($state_css),
			(new CCol($last_value))->addClass($state_css),
			(new CCol($change))->addClass($state_css),
			($data['filter']['show_tags'] != SHOW_TAGS_NONE) ? $data['tags'][$itemid] : null,
			$actions,
			makeInformationList($item_icons)
		]);
	}

	$table->addRow($table_row);
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('graphtype', 'itemids', [
		GRAPH_TYPE_STACKED => ['name' => _('Display stacked graph')],
		GRAPH_TYPE_NORMAL => ['name' => _('Display graph')]
	], 'latest')
]);

echo $form;
