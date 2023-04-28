<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace Widgets\Item\Includes;

use API;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldCheckBoxList,
	CWidgetFieldColor,
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectItem,
	CWidgetFieldRadioButtonList,
	CWidgetFieldSelect,
	CWidgetFieldTextArea,
	CWidgetFieldTextBox,
	CWidgetFieldThresholds
};

use Widgets\Item\Widget;

/**
 * Single item widget form.
 */
class WidgetForm extends CWidgetForm {

	private const SIZE_PERCENT_MIN = 1;
	private const SIZE_PERCENT_MAX = 100;

	private const DEFAULT_DESCRIPTION_SIZE = 15;
	private const DEFAULT_DECIMAL_SIZE = 35;
	private const DEFAULT_VALUE_SIZE = 45;
	private const DEFAULT_UNITS_SIZE = 35;
	private const DEFAULT_TIME_SIZE = 15;

	private bool $is_binary_units = false;

	public function __construct(array $values, ?string $templateid) {
		parent::__construct($values, $templateid);

		if (array_key_exists('units', $this->values) && $this->values['units'] !== '') {
			$this->is_binary_units = isBinaryUnits($this->values['units']);
		}
		elseif (array_key_exists('itemid', $this->values)) {
			$items = API::Item()->get([
				'output' => ['units'],
				'itemids' => $this->values['itemid'],
				'webitems' => true
			]);

			$this->is_binary_units = $items && isBinaryUnits($items[0]['units']);
		}
	}

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		// Check if one of the objects (description, value or time) occupies same space.
		$fields = [
			['show' => Widget::SHOW_DESCRIPTION, 'h_pos' => 'desc_h_pos', 'v_pos' => 'desc_v_pos'],
			['show' => Widget::SHOW_VALUE, 'h_pos' => 'value_h_pos', 'v_pos' => 'value_v_pos'],
			['show' => Widget::SHOW_TIME, 'h_pos' => 'time_h_pos', 'v_pos' => 'time_v_pos']
		];

		$fields_count = count($fields);
		$show = $this->getFieldValue('show');

		for ($i = 0; $i < $fields_count - 1; $i++) {
			if (!in_array($fields[$i]['show'], $show)) {
				continue;
			}

			$i_h_pos = $this->getFieldValue($fields[$i]['h_pos']);
			$i_v_pos = $this->getFieldValue($fields[$i]['v_pos']);

			for ($j = $i + 1; $j < $fields_count; $j++) {
				if (!in_array($fields[$j]['show'], $show)) {
					continue;
				}

				$j_h_pos = $this->getFieldValue($fields[$j]['h_pos']);
				$j_v_pos = $this->getFieldValue($fields[$j]['v_pos']);

				if ($i_h_pos == $j_h_pos && $i_v_pos == $j_v_pos) {
					$errors[] = _('Two or more fields cannot occupy same space.');
					break 2;
				}
			}
		}

		return $errors;
	}

	public function addFields(): self {
		return $this
			->addField(
				(new CWidgetFieldMultiSelectItem('itemid', _('Item'), $this->templateid))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
			)
			->addField(
				(new CWidgetFieldCheckBoxList('show', _('Show'), [
					Widget::SHOW_DESCRIPTION => _('Description'),
					Widget::SHOW_VALUE => _('Value'),
					Widget::SHOW_TIME => _('Time'),
					Widget::SHOW_CHANGE_INDICATOR => _('Change indicator')
				]))
					->setDefault([Widget::SHOW_DESCRIPTION, Widget::SHOW_VALUE, Widget::SHOW_TIME,
						Widget::SHOW_CHANGE_INDICATOR
					])
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				new CWidgetFieldCheckBox('adv_conf', _('Advanced configuration'))
			)
			->addField(
				(new CWidgetFieldTextArea('description', _('Description')))
					->setDefault('{ITEM.NAME}')
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('desc_h_pos', _('Horizontal position'), [
					Widget::POSITION_LEFT => _('Left'),
					Widget::POSITION_CENTER => _('Center'),
					Widget::POSITION_RIGHT => _('Right')
				]))->setDefault(Widget::POSITION_CENTER)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('desc_v_pos', _('Vertical position'), [
					Widget::POSITION_TOP => _('Top'),
					Widget::POSITION_MIDDLE => _('Middle'),
					Widget::POSITION_BOTTOM => _('Bottom')
				]))->setDefault(Widget::POSITION_BOTTOM)
			)
			->addField(
				(new CWidgetFieldIntegerBox('desc_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->setDefault(self::DEFAULT_DESCRIPTION_SIZE)
			)
			->addField(
				new CWidgetFieldCheckBox('desc_bold', _('Bold'))
			)
			->addField(
				new CWidgetFieldColor('desc_color', _('Color'))
			)
			->addField(
				(new CWidgetFieldIntegerBox('decimal_places', _('Decimal places'), 0, 10))
					->setDefault(2)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY)
			)
			->addField(
				(new CWidgetFieldIntegerBox('decimal_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->setDefault(self::DEFAULT_DECIMAL_SIZE)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('value_h_pos', _('Horizontal position'), [
					Widget::POSITION_LEFT => _('Left'),
					Widget::POSITION_CENTER => _('Center'),
					Widget::POSITION_RIGHT => _('Right')
				]))->setDefault(Widget::POSITION_CENTER)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('value_v_pos', _('Vertical position'), [
					Widget::POSITION_TOP => _('Top'),
					Widget::POSITION_MIDDLE => _('Middle'),
					Widget::POSITION_BOTTOM => _('Bottom')
				]))->setDefault(Widget::POSITION_MIDDLE)
			)
			->addField(
				(new CWidgetFieldIntegerBox('value_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->setDefault(self::DEFAULT_VALUE_SIZE)
			)
			->addField(
				(new CWidgetFieldCheckBox('value_bold', _('Bold')))->setDefault(1)
			)
			->addField(
				new CWidgetFieldColor('value_color', _('Color'))
			)
			->addField(
				(new CWidgetFieldCheckBox('units_show', _('Units')))->setDefault(1)
			)
			->addField(
				new CWidgetFieldTextBox('units', _('Units'))
			)
			->addField(
				(new CWidgetFieldSelect('units_pos', _('Position'), [
					Widget::POSITION_BEFORE => _('Before value'),
					Widget::POSITION_ABOVE => _('Above value'),
					Widget::POSITION_AFTER => _('After value'),
					Widget::POSITION_BELOW => _('Below value')
				]))->setDefault(Widget::POSITION_AFTER)
			)
			->addField(
				(new CWidgetFieldIntegerBox('units_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->setDefault(self::DEFAULT_UNITS_SIZE)
			)
			->addField(
				(new CWidgetFieldCheckBox('units_bold', _('Bold')))->setDefault(1)
			)
			->addField(
				new CWidgetFieldColor('units_color', _('Color'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('time_h_pos', _('Horizontal position'), [
					Widget::POSITION_LEFT => _('Left'),
					Widget::POSITION_CENTER => _('Center'),
					Widget::POSITION_RIGHT => _('Right')
				]))->setDefault(Widget::POSITION_CENTER)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('time_v_pos', _('Vertical position'), [
					Widget::POSITION_TOP => _('Top'),
					Widget::POSITION_MIDDLE => _('Middle'),
					Widget::POSITION_BOTTOM => _('Bottom')
				]))->setDefault(Widget::POSITION_TOP)
			)
			->addField(
				(new CWidgetFieldIntegerBox('time_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->setDefault(self::DEFAULT_TIME_SIZE)
			)
			->addField(
				new CWidgetFieldCheckBox('time_bold', _('Bold'))
			)
			->addField(
				new CWidgetFieldColor('time_color', _('Color'))
			)
			->addField(
				new CWidgetFieldColor('up_color', _('Change indicator'))
			)
			->addField(
				new CWidgetFieldColor('down_color', _('Change indicator'))
			)
			->addField(
				new CWidgetFieldColor('updown_color', _('Change indicator'))
			)
			->addField(
				new CWidgetFieldColor('bg_color', _('Background color'))
			)
			->addField(
				new CWidgetFieldThresholds('thresholds', _('Thresholds'), $this->is_binary_units)
			)
			->addField($this->templateid === null
				? new CWidgetFieldCheckBox('dynamic', _('Enable host selection'))
				: null
			);
	}
}
