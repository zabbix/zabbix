<?php declare(strict_types = 1);
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
		if (!array_key_exists($key, $data['data'])) {
			continue;
		}

		$items[$key] = new CDiv();

		$row = $data['data'][$key];

		foreach ($row as $column) {
			// The $column_key can be "item_value", "item_time" or "item_description".
			foreach ($column as $column_key => $block) {
				// Possible DIV blocks: "div_item_value", "div_item_time" or "div_item_description".
				${"div_$column_key"} = new CDiv();

				// The "item_value" block is special and contain more blocks inside.
				if ($column_key === 'item_value') {
					/*
					 * The $block_key can be "data", "item_value_content" or "classes". We need to loop this because
					 * units can be inside (before or after value) or outside (above or below) the wrapper. So which
					 * ever block comes first. Except for classes. That is added for "dv_item_value_content" DIV.
					 */
					foreach ($block as $block_key => $content) {
						if ($block_key === 'item_value_content') {
							// Make "div_item_value_content" DIV block.
							${"div_$block_key"} = new CDiv();

							foreach ($content['data'] as $content_inner) {
								foreach ($content_inner as $item_key => $item) {
									if ($item_key === 'change_indicator') {
										${"div_$item_key"} = new CDiv(new CSvgArrow($item['data']));
									}
									else {
										${"div_$item_key"} = new CDiv($item['data']);
									}

									foreach ($item['classes'] as $class) {
										${"div_$item_key"}->addClass($class);
									}

									$cnt = count($item['styles']);
									$i = 0;

									foreach ($item['styles'] as $style => $value) {
										${"div_$item_key"}->addStyle($style.': '.$value.(($i + 1) != $cnt ? '; ' : ''));
										$i++;
									}

									${"div_$block_key"}->addItem(${"div_$item_key"});
								}
							}

							foreach ($block[$block_key]['classes'] as $class) {
								${"div_$block_key"}->addClass($class);
							}

							${"div_$column_key"}->addItem(${"div_$block_key"});
						}
						elseif ($block_key === 'data') {
							foreach ($content as $content_inner) {
								foreach ($content_inner as $item_key => $item) {
									// Make "div_units" DIV.
									${"$item_key"} = new CDiv($item['data']);

									foreach ($item['classes'] as $class) {
										${"$item_key"}->addClass($class);
									}

									$cnt = count($item['styles']);
									$i = 0;

									foreach ($item['styles'] as $style => $value) {
										${"$item_key"}->addStyle($style.': '.$value.(($i + 1) != $cnt ? '; ' : ''));
										$i++;
									}

									${"div_$column_key"}->addItem(${"$item_key"});
								}
							}
						}
					}
				}
				else {
					/*
					 * This block is either description or time. Description can be array as well if it contains
					 * multiple lines.
					 */
					${"div_$column_key"}->addItem($block['data']);
				}

				// Regardless of whether it is description, value or time block, they have classes.
				foreach ($block['classes'] as $class) {
					${"div_$column_key"}->addClass($class);
				}

				// Value block may not have styles.
				if (array_key_exists('styles', $block)) {
					$cnt = count($block['styles']);
					$i = 0;

					foreach ($block['styles'] as $style => $value) {
						${"div_$column_key"}->addStyle($style.': '.$value.(($i + 1) != $cnt ? '; ' : ''));
						$i++;
					}
				}

				$items[$key]->addItem(${"div_$column_key"});
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
