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
	$body = (new CDiv(
		new CLink(drawCells($data['cells']), $data['url'])
	))->addClass('dashboard-grid-widget-item');

	if ($data['bg_color'] !== '') {
		$body->addStyle('background-color: #'.$data['bg_color']);
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


/**
 * Prepare array of DIVs one for each row with all their content.
 *
 * @param array $cells  Widget data arranged by cells.
 *
 * @return array
 */
function drawCells(array $cells): array {
	$rows = [];

	foreach ([WIDGET_ITEM_POS_TOP, WIDGET_ITEM_POS_MIDDLE, WIDGET_ITEM_POS_BOTTOM] as $row_key) {
		if (array_key_exists($row_key, $cells)) {
			$cols = [];

			foreach ($cells[$row_key] as $column_key => $cell) {
				$cols[] = drawCell($cell, $row_key, $column_key);
			}

			$rows[] = new CDiv($cols);
		}
		else {
			$rows[] = new CDiv();
		}
	}

	return $rows;
}

/**
 * Prepare DIV for single cell in row with all it's content.
 *
 * @param array $cell        Data for single cell.
 * @param int   $row_key     Cell's vertical alignment.
 * @param int   $column_key  Cell's horizontal alignment.
 *
 * @return CDiv
 */
function drawCell(array $cell, int $row_key, int $column_key): CDiv {
	$div = new CDiv();

	$cell_type = array_keys($cell)[0];
	$cell_data = array_values($cell)[0];

	$classes_vertical = [
		WIDGET_ITEM_POS_TOP => 'top',
		WIDGET_ITEM_POS_MIDDLE => 'middle',
		WIDGET_ITEM_POS_BOTTOM => 'bottom'
	];
	$classes_horizontal = [
		WIDGET_ITEM_POS_LEFT => 'left',
		WIDGET_ITEM_POS_CENTER => 'center',
		WIDGET_ITEM_POS_RIGHT => 'right'
	];

	$div->addClass($classes_vertical[$row_key]);
	$div->addClass($classes_horizontal[$column_key]);

	switch($cell_type) {
		case 'item_description':
			$div->addClass('item-description');

			if (strpos($cell_data['text'], "\n") !== false) {
				$cell_data['text'] = zbx_nl2br($cell_data['text']);
				$div->addClass('multiline');
			}

			$div = addTextFormatting($div, $cell_data);
			break;

		case 'item_time':
			$div->addClass('item-time');
			$div = addTextFormatting($div, $cell_data);
			break;

		case 'item_value':
			$div->addClass('item-value');

			if (array_key_exists('value_type', $cell_data)) {
				$div->addClass(($cell_data['value_type'] == ITEM_VALUE_TYPE_FLOAT
					|| $cell_data['value_type'] == ITEM_VALUE_TYPE_UINT64)
					? 'type-number'
					: 'type-text'
				);
			}

			$div->addItem(drawValueCell($cell_data));
			break;
	}

	return $div;
}

/**
 * Prepare content for value cell.
 *
 * @param array $cell_data  Data with all value cell parts.
 *
 * @return array
 */
function drawValueCell(array $cell_data): array {
	$item_cell = [];

	if (array_key_exists('units', $cell_data['parts'])) {
		$units_div = (new CDiv())->addClass('units');
		$units_div = addTextFormatting($units_div, $cell_data['parts']['units']);
	}

	// Units ABOVE value.
	if (array_key_exists('units', $cell_data['parts']) && $cell_data['units_pos'] == WIDGET_ITEM_POS_ABOVE) {
		$item_cell[] = $units_div;
	}

	$item_content_div = (new CDiv())->addClass('item-value-content');

	// Units BEFORE value.
	if (array_key_exists('units', $cell_data['parts']) && $cell_data['units_pos'] == WIDGET_ITEM_POS_BEFORE) {
		$item_content_div->addItem($units_div);
	}

	if (array_key_exists('value', $cell_data['parts'])) {
		$item_value_div = (new CDiv())->addClass('value');

		if ($cell_data['parts']['value']['text'] === null) {
			$cell_data['parts']['value']['text'] = _('No data');
			$item_value_div->addClass('item-value-no-data');
		}

		$item_value_div = addTextFormatting($item_value_div, $cell_data['parts']['value']);
		$item_content_div->addItem($item_value_div);
	}

	if (array_key_exists('decimals', $cell_data['parts'])) {
		$item_decimals_div = (new CDiv())->addClass('decimals');
		$item_decimals_div = addTextFormatting($item_decimals_div, $cell_data['parts']['decimals']);
		$item_content_div->addItem($item_decimals_div);
	}

	if (array_key_exists('change_indicator', $cell_data['parts'])) {
		$change_data = $cell_data['parts']['change_indicator'];
		$item_change_div = (new CDiv())->addClass('change-indicator');
		$item_change_div->addStyle(
			sprintf('--widget-item-font: %1$s;', number_format($change_data['font_size'] / 100, 2))
		);

		switch ($change_data['type']) {
			case CControllerWidgetItemView::CHANGE_INDICATOR_UP:
				$arrow_data = ['up' => true, 'fill_color' => $change_data['color']];
				break;
			case CControllerWidgetItemView::CHANGE_INDICATOR_DOWN:
				$arrow_data = ['down' => true, 'fill_color' => $change_data['color']];
				break;
			case CControllerWidgetItemView::CHANGE_INDICATOR_UP_DOWN:
				$arrow_data = ['up' => true, 'down' => true, 'fill_color' => $change_data['color']];
				break;
		}

		$item_change_div->addItem(new CSvgArrow($arrow_data));
		$item_content_div->addItem($item_change_div);
	}

	// Units AFTER value.
	if (array_key_exists('units', $cell_data['parts']) && $cell_data['units_pos'] == WIDGET_ITEM_POS_AFTER) {
		$item_content_div->addItem($units_div);
	}

	$item_cell[] = $item_content_div;

	// Units BELOW value.
	if (array_key_exists('units', $cell_data['parts']) && $cell_data['units_pos'] == WIDGET_ITEM_POS_BELOW) {
		$item_cell[] = $units_div;
	}

	return $item_cell;
}

/**
 * Adds formatting and content for text part on widget, based on provided data.
 *
 * @param CDiv    $div                     Div where text element will be displayed.
 * @param array   $text_data               Text divs settings and content.
 * @param boolean $text_data['bold']       Flag if text should be in bold.
 * @param int     $text_data['font_size']  Font size of text in percents from widget height.
 * @param string  $text_data['color']      Hex string with text color or emtpy for default color.
 * @param string  $text_data['text']       Text string to display.
 *
 * @return CDiv
 */
function addTextFormatting(CDiv $div, array $text_data): CDiv {
	if ($text_data['bold']) {
		$div->addClass('bold');
	}

	$style = sprintf('--widget-item-font: %1$s;', number_format($text_data['font_size'] / 100, 2));
	if ($text_data['color'] !== '') {
		$style .= sprintf(' color: #%1$s;', $text_data['color']);
	}
	$div->addStyle($style);

	$div->addItem($text_data['text']);

	return $div;
}
