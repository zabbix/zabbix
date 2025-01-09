<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Item value widget view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\Item\Widget;

if (array_key_exists('error', $data)) {
	$body = (new CTableInfo())->setNoDataMessage($data['error']);
}
else {
	$classes_vertical = [
		Widget::POSITION_TOP => 'top',
		Widget::POSITION_MIDDLE => 'middle',
		Widget::POSITION_BOTTOM => 'bottom'
	];
	$classes_horizontal = [
		Widget::POSITION_LEFT => 'left',
		Widget::POSITION_CENTER => 'center',
		Widget::POSITION_RIGHT => 'right'
	];

	$rows = [];

	if ($data['sparkline']) {
		$rows[] = (new CSparkline())
			->setColor('#'.$data['sparkline']['color'])
			->setLineWidth($data['sparkline']['width'])
			->setFill($data['sparkline']['fill'])
			->setValue($data['sparkline']['value'])
			->setTimePeriodFrom($data['sparkline']['from'])
			->setTimePeriodTo($data['sparkline']['to']);
	}

	foreach ($classes_vertical as $row_key => $row_class) {
		$cols = [];

		foreach ($classes_horizontal as $column_key => $column_class) {
			if (!array_key_exists($row_key, $data['cells'])
					|| !array_key_exists($column_key, $data['cells'][$row_key])) {
				continue;
			}

			$div = new CDiv();

			$cell = $data['cells'][$row_key][$column_key];
			$cell_type = array_keys($cell)[0];
			$cell_data = array_values($cell)[0];

			$div->addClass($row_class);
			$div->addClass($column_class);

			switch ($cell_type) {
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
					$div
						->addClass('item-value')
						->addClass($cell_data['is_numeric'] ? 'type-number' : 'type-text')
						->addItem(drawValueCell($cell_data));
					break;
			}

			$cols[] = $div;
		}

		$rows[] = new CDiv($cols);
	}

	$body = new CLink($rows, $data['url']);

	if ($data['bg_color'] !== '') {
		$body->addStyle('background-color: #'.$data['bg_color'].';');
	}
}

(new CWidgetView($data))
	->addItem($body)
	->setVar('info', $data['info'])
	->show();

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
	if (array_key_exists('units', $cell_data['parts']) && $cell_data['units_pos'] == Widget::POSITION_ABOVE) {
		$item_cell[] = $units_div;
	}

	$item_content_div = (new CDiv())->addClass('item-value-content');

	// Units BEFORE value.
	if (array_key_exists('units', $cell_data['parts']) && $cell_data['units_pos'] == Widget::POSITION_BEFORE) {
		$item_content_div->addItem($units_div);
	}

	$item_value_div = (new CDiv())->addClass('value');

	if ($cell_data['parts']['value']['text'] === null) {
		$cell_data['parts']['value']['text'] = _('No data');
		$item_value_div->addClass('item-value-no-data');
	}

	$item_value_div = addTextFormatting($item_value_div, $cell_data['parts']['value']);
	$item_content_div->addItem($item_value_div);

	if (array_key_exists('decimals', $cell_data['parts'])) {
		$item_decimals_div = (new CDiv())->addClass('decimals');
		$item_decimals_div = addTextFormatting($item_decimals_div, $cell_data['parts']['decimals']);
		$item_content_div->addItem($item_decimals_div);
	}

	// Units AFTER value.
	if (array_key_exists('units', $cell_data['parts']) && $cell_data['units_pos'] == Widget::POSITION_AFTER) {
		$item_content_div->addItem($units_div);
	}

	$item_cell[] = $item_content_div;

	if (array_key_exists('change_indicator', $cell_data['parts'])) {
		$change_data = $cell_data['parts']['change_indicator'];
		$item_change_div = (new CDiv())->addClass('change-indicator');
		$item_change_div->addStyle(
			sprintf('--widget-item-font: %1$s;', number_format($change_data['font_size'] / 100, 2))
		);

		switch ($change_data['type']) {
			case Widget::CHANGE_INDICATOR_UP:
				$arrow_data = ['up' => true, 'fill_color' => $change_data['color']];
				break;
			case Widget::CHANGE_INDICATOR_DOWN:
				$arrow_data = ['down' => true, 'fill_color' => $change_data['color']];
				break;
			case Widget::CHANGE_INDICATOR_UP_DOWN:
				$arrow_data = ['up' => true, 'down' => true, 'fill_color' => $change_data['color']];
				break;
		}

		$item_change_div->addItem(new CSvgArrow($arrow_data));
		$item_content_div->addItem($item_change_div);
	}

	// Units BELOW value.
	if (array_key_exists('units', $cell_data['parts']) && $cell_data['units_pos'] == Widget::POSITION_BELOW) {
		$item_cell[] = $units_div;
	}

	return $item_cell;
}

/**
 * Adds formatting and content for text part on widget, based on provided data.
 *
 * @param CDiv    $div        Div where text element will be displayed.
 * @param array   $text_data  Text divs settings and content.
 *
 * @return CDiv
 */
function addTextFormatting(CDiv $div, array $text_data): CDiv {
	if ($text_data['bold']) {
		$div->addClass('bold');
	}

	$div->addStyle(sprintf('--widget-item-font: %1$s;', number_format($text_data['font_size'] / 100, 2)));

	if ($text_data['color'] !== '') {
		$div->addStyle(sprintf('color: #%1$s;', $text_data['color']));
	}

	$div->addItem($text_data['text']);

	return $div;
}
