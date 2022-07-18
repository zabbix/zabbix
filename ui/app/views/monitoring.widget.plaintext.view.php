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

$table = new CTableInfo();

if ($data['error'] != null) {
	$table->setNoDataMessage($data['error']);
}
else {
	$table_header = [(new CColHeader(_x('Timestamp', 'compact table header')))->addClass(ZBX_STYLE_CELL_WIDTH)];
	$names_at_top = ($data['style'] == STYLE_TOP && count($data['items']) > 1);

	if ($names_at_top) {
		$table->makeVerticalRotation();

		foreach ($data['items'] as $item) {
			$table_header[] = (new CColHeader(
				($data['same_host'] ? '' : $item['hosts'][0]['name'].NAME_DELIMITER).$item['name']
			))
				->addClass('vertical_rotation')
				->setTitle($item['name']);
		}
	}
	else {
		if ($data['style'] == STYLE_LEFT) {
			$table_header[] = _x('Name', 'compact table header');
		}
		$table_header[] = _x('Value', 'compact table header');
	}
	$table->setHeader($table_header);

	$clock = 0;
	$row_values = [];

	do {
		$history_item = array_shift($data['histories']);

		if ($history_item !== null && !$names_at_top) {
			$table_row = [
				(new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $history_item['clock'])))->addClass(ZBX_STYLE_NOWRAP)
			];
			if ($data['style'] == STYLE_LEFT) {
				$table->setHeadingColumn(1);
				$table_row[] = ($data['same_host']
					? ''
					: $data['items'][$history_item['itemid']]['hosts'][0]['name'].NAME_DELIMITER).
					$data['items'][$history_item['itemid']]['name'];
			}
			$table_row[] = $history_item['value'];
			$table->addRow($table_row);
		}
		else {
			if (($history_item === null && $row_values)
					|| $history_item !== null
					&& (($clock != 0 && $history_item['clock'] != $clock)
						|| array_key_exists($history_item['itemid'], $row_values))) {
				$table_row = [
					(new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $clock)))->addClass(ZBX_STYLE_NOWRAP)
				];
				foreach ($data['items'] as $item) {
					$table_row[] = array_key_exists($item['itemid'], $row_values)
						? $row_values[$item['itemid']]
						: '';
				}
				$table->addRow($table_row);
				$row_values = [];
			}

			if ($history_item !== null) {
				$clock = $history_item['clock'];
				$row_values[$history_item['itemid']] = $history_item['value'];
			}
		}
	} while ($history_item !== null && $table->getNumRows() < $data['show_lines']);
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
