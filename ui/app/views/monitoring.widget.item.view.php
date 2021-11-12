<?php
declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

if ($data['error'] !== '') {
	$body = (new CTableInfo())->setNoDataMessage($data['error']);
}
else {
	$items = [];

	foreach ([WIDGET_ITEM_POS_TOP, WIDGET_ITEM_POS_MIDDLE, WIDGET_ITEM_POS_BOTTOM] as $key) {
		$items[$key] = new CDiv();

		if (!array_key_exists($key, $data['data'])) {
			continue;
		}

		$row = $data['data'][$key];

		foreach ($row as $column) {
			if (is_array($column['data'])) {
				$value_block = false;

				// Check if this is simply array of descriptions or value block.
				foreach ($column['data'] as $cell) {
					if (is_array($cell)) {
						$value_block = true;
						break;
					}
				}
			}

			if (is_array($column['data']) && $value_block) {
				// Value block that consists of other blocks.
				$main = new CDiv();

				if ($column['classes']) {
					foreach ($column['classes'] as $class) {
						$main->addClass($class);
					}
				}

				foreach ($column['data'] as $cell) {
					if ($cell['classes'] && in_array('change-indicator', $cell['classes'])) {
						$item = new CDiv(new CSvgArrow($cell['data']));
					}
					else {
						// Other blocks: value, decimals and units.
						$item = new CDiv($cell['data']);
					}

					if ($cell['classes']) {
						foreach ($cell['classes'] as $class) {
							$item->addClass($class);
						}
					}

					if ($cell['styles']) {
						$cnt = count($cell['styles']);
						$i = 0;

						foreach ($cell['styles'] as $style => $value) {
							$item->addStyle($style.': '.$value.(($i + 1) != $cnt ? '; ' : ''));
							$i++;
						}
					}

					// To do: use $cell['value_type'] variable to truncate string blocks. Either CSS class or style.
					$main->addItem($item);
				}

				$items[$key]->addItem($main);
			}
			else {
				// Individual blocks like description and time.
				$item = new CDiv($column['data']);

				if ($column['classes']) {
					foreach ($column['classes'] as $class) {
						$item->addClass($class);
					}
				}

				if ($column['styles']) {
					$cnt = count($column['styles']);
					$i = 0;

					foreach ($column['styles'] as $style => $value) {
						$item->addStyle($style.': '.$value.(($i + 1) != $cnt ? '; ' : ''));
						$i++;
					}
				}

				$items[$key]->addItem($item);
			}
		}
	}

	$body = (new CDiv($items))->addClass('dashboard-grid-widget-item');

	if ($data['data']['bg_color'] !== '') {
		$body->addStyle('background-color: #'.$data['data']['bg_color']);
	}
}

$output = [
	'name' => $data['name'],
	'body' => $body->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
