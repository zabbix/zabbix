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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 * @var array $data
 */

$type_field_names = [
	INTERFACE_TYPE_AGENT => _('Zabbix agent'),
	INTERFACE_TYPE_SNMP => _('SNMP'),
	INTERFACE_TYPE_JMX => _('JMX'),
	INTERFACE_TYPE_IPMI => _('IPMI')
];

$header = [
	STYLE_HORIZONTAL => [
		'',
		_x('Available', 'compact table header'),
		_x('Not available', 'compact table header'),
		_x('Unknown', 'compact table header'),
		_x('Total', 'compact table header')
	],
	STYLE_VERTICAL => ['']
];

foreach ($type_field_names as $key => $value) {
	if (!in_array($key, $data['hosts_types'])) {
		continue;
	}

	$header[STYLE_VERTICAL][] = $value;
}

if (count($data['hosts_types']) == 1) {
	$counts = $data['hosts_count'][$data['hosts_types'][0]];

	$table = (new CDiv())
		->addClass(ZBX_STYLE_HOST_AVAIL_WIDGET)
		->addClass(ZBX_STYLE_TOTALS_LIST)
		->addClass(($data['layout'] == STYLE_HORIZONTAL)
			? ZBX_STYLE_TOTALS_LIST_HORIZONTAL
			: ZBX_STYLE_TOTALS_LIST_VERTICAL
		);

	$table->addItem((new CDiv([
		(new CSpan($counts[INTERFACE_AVAILABLE_TRUE]))->addClass(ZBX_STYLE_TOTALS_LIST_COUNT), _('Available')
	]))->addClass(ZBX_STYLE_HOST_AVAIL_TRUE));

	$table->addItem((new CDiv([
		(new CSpan($counts[INTERFACE_AVAILABLE_FALSE]))->addClass(ZBX_STYLE_TOTALS_LIST_COUNT), _('Not available')
	]))->addClass(ZBX_STYLE_HOST_AVAIL_FALSE));

	$table->addItem((new CDiv([
		(new CSpan($counts[INTERFACE_AVAILABLE_UNKNOWN]))->addClass(ZBX_STYLE_TOTALS_LIST_COUNT), _('Unknown')
	]))->addClass(ZBX_STYLE_HOST_AVAIL_UNKNOWN));

	$table->addItem((new CDiv([
		(new CSpan($data['hosts_total'][$data['hosts_types'][0]]))->addClass(ZBX_STYLE_TOTALS_LIST_COUNT), _('Total')
	]))->addClass(ZBX_STYLE_HOST_AVAIL_TOTAL));
}
else {
	$table = (new CTableInfo)
		->setHeader($header[$data['layout']])
		->setHeadingColumn(0)
		->addClass(ZBX_STYLE_HOST_AVAIL_WIDGET);

	foreach ($type_field_names as $key => $value) {
		if (in_array($key, $data['hosts_types'])) {
			$counts = $data['hosts_count'][$key];

			$available_row = (new CCol($counts[INTERFACE_AVAILABLE_TRUE]))->addClass(ZBX_STYLE_HOST_AVAIL_TRUE);
			$not_available_row = (new CCol($counts[INTERFACE_AVAILABLE_FALSE]))->addClass(ZBX_STYLE_HOST_AVAIL_FALSE);
			$unknown_row = (new CCol($counts[INTERFACE_AVAILABLE_UNKNOWN]))->addClass(ZBX_STYLE_HOST_AVAIL_UNKNOWN);
			$total_row = (new CCol($data['hosts_total'][$key]))->addClass(ZBX_STYLE_HOST_AVAIL_TOTAL);

			if ($data['layout'] == STYLE_HORIZONTAL) {
				$table->addRow([$value, $available_row, $not_available_row, $unknown_row, $total_row]);
			}
			else {
				$rows[INTERFACE_AVAILABLE_TRUE][] = $available_row;
				$rows[INTERFACE_AVAILABLE_FALSE][] = $not_available_row;
				$rows[INTERFACE_AVAILABLE_UNKNOWN][] = $unknown_row;
				$rows['hosts_total'][] = $total_row;
			}
		}
	}

	if ($data['layout'] == STYLE_VERTICAL) {
		$table
			->addRow(array_merge([_('Available')], $rows[INTERFACE_AVAILABLE_TRUE]))
			->addRow(array_merge([_('Not available')], $rows[INTERFACE_AVAILABLE_FALSE]))
			->addRow(array_merge([_('Unknown')], $rows[INTERFACE_AVAILABLE_UNKNOWN]))
			->addRow(array_merge([_('Total')], $rows['hosts_total']));
	}
}

$output = [
	'name' => $data['name'],
	'body' => $table->toString()
];

if ($messages = get_and_clear_messages()) {
	$output['messages'] = array_column($messages, 'message');
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
