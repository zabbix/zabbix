<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


$table = new CTableInfo();

if ($data['error'] != null) {
	$table->setNoDataMessage($data['error']);
}
else {
	$table_header = [(new CColHeader(_('Timestamp')))->addClass(ZBX_STYLE_CELL_WIDTH)];
	$names_at_top = ($data['name_location'] == STYLE_TOP && count($data['items']) > 1);

	if ($names_at_top) {
		$table->makeVerticalRotation();

		foreach ($data['items'] as $item) {
			$column_name = ($data['same_host'] === false)
				? $item['hosts'][0]['name'].NAME_DELIMITER.$item['name_expanded']
				: $item['name_expanded'];
			$table_header[] = (new CColHeader($column_name))
				->addClass('vertical_rotation')
				->setTitle($item['name_expanded']);
		}
	}
	else {
		if ($data['name_location'] == STYLE_LEFT) {
			$table_header[] = _('Name');
		}
		$table_header[] = _('Value');
	}
	$table->setHeader($table_header);

	$clock = null;
	$row_values = [];
	foreach ($data['history_data'] as $history_item) {
		if ($names_at_top) {
			if ($history_item['clock'] != $clock || array_key_exists($history_item['itemid'], $row_values)) {
				if ($clock !== null && count($row_values)) {
					$table_row = [(new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $clock)))
						->addClass(ZBX_STYLE_NOWRAP)];
					foreach ($data['items'] as $item) {
						$table_row[] = array_key_exists($item['itemid'], $row_values)
							? $row_values[$item['itemid']]
							: '';
					}
					$table->addRow($table_row);
					$row_values = [];
				}
				$clock = $history_item['clock'];
			}
			$row_values[$history_item['itemid']] = $history_item['value'];
		}
		else {
			$table_row = [(new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $history_item['clock'])))
				->addClass(ZBX_STYLE_NOWRAP)];
			if ($data['name_location'] == STYLE_LEFT) {
				$table_row[] = ($data['same_host'] === false)
					? $history_item['host_name'].NAME_DELIMITER.$history_item['item_name']
					: $history_item['item_name'];
			}
			$table_row[] = $history_item['value'];
			$table->addRow($table_row);
		}

		if ($table->getNumRows() >= $data['show_lines']) {
			break;
		}
	}
}

$output = [
	'header' => $data['name'],
	'body' => $table->toString(),
	'footer' => (new CList())
		->addItem(_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)))
		->addClass(ZBX_STYLE_DASHBRD_WIDGET_FOOT)
		->toString()
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
