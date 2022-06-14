<?php declare(strict_types = 0);
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
 * Single item widget form.
 */
class CWidgetFormItem extends CWidgetForm {

	/**
	 * Minimum value of percentage.
	 *
	 * @var int
	 */
	private const WIDGET_ITEM_PERCENT_MIN = 1;

	/**
	 * Maximum value of percentage.
	 *
	 * @var int
	 */
	private const WIDGET_ITEM_PERCENT_MAX = 100;

	public function __construct($data, $templateid) {
		parent::__construct($data, $templateid, WIDGET_ITEM);

		// item field
		$field_item = (new CWidgetFieldMsItem('itemid', _('Item'), $templateid))
			->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			->setMultiple(false);

		if (array_key_exists('itemid', $this->data)) {
			$field_item->setValue($this->data['itemid']);
		}

		$this->fields[$field_item->getName()] = $field_item;

		// show checkboxes
		$field_show = (new CWidgetFieldCheckBoxList('show', _('Show')))
			->setDefault([WIDGET_ITEM_SHOW_DESCRIPTION, WIDGET_ITEM_SHOW_VALUE, WIDGET_ITEM_SHOW_TIME,
				WIDGET_ITEM_SHOW_CHANGE_INDICATOR
			])
			->setFlags(CWidgetField::FLAG_LABEL_ASTERISK);

		if (array_key_exists('show', $this->data)) {
			$field_show->setValue($this->data['show']);
		}

		$this->fields[$field_show->getName()] = $field_show;

		// advanced configuration
		$field_adv_conf = (new CWidgetFieldCheckBox('adv_conf', _('Advanced configuration')))->setDefault(0);

		if (array_key_exists('adv_conf', $this->data)) {
			$field_adv_conf->setValue($this->data['adv_conf']);
		}

		$this->fields[$field_adv_conf->getName()] = $field_adv_conf;

		// description textarea field
		$field_desc = (new CWidgetFieldTextArea('description', _('Description')))
			->setDefault('{ITEM.NAME}')
			->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH - 38);

		if (array_key_exists('description', $this->data)) {
			$field_desc->setValue($this->data['description']);
		}

		$this->fields[$field_desc->getName()] = $field_desc;

		// description horizontal position
		$field_desc_h_pos = (new CWidgetFieldRadioButtonList('desc_h_pos', _('Horizontal position'), [
			WIDGET_ITEM_POS_LEFT => _('Left'),
			WIDGET_ITEM_POS_CENTER => _('Center'),
			WIDGET_ITEM_POS_RIGHT => _('Right')
		]))
			->setDefault(WIDGET_ITEM_POS_CENTER)
			->setModern(true);

		if (array_key_exists('desc_h_pos', $this->data)) {
			$field_desc_h_pos->setValue($this->data['desc_h_pos']);
		}

		$this->fields[$field_desc_h_pos->getName()] = $field_desc_h_pos;

		// description vertical position
		$field_desc_v_pos = (new CWidgetFieldRadioButtonList('desc_v_pos', _('Vertical position'), [
			WIDGET_ITEM_POS_TOP => _('Top'),
			WIDGET_ITEM_POS_MIDDLE => _('Middle'),
			WIDGET_ITEM_POS_BOTTOM => _('Bottom')
		]))
			->setDefault(WIDGET_ITEM_POS_BOTTOM)
			->setModern(true);

		if (array_key_exists('desc_v_pos', $this->data)) {
			$field_desc_v_pos->setValue($this->data['desc_v_pos']);
		}

		$this->fields[$field_desc_v_pos->getName()] = $field_desc_v_pos;

		// description size
		$field_desc_size = (new CWidgetFieldIntegerBox('desc_size', _('Size'), self::WIDGET_ITEM_PERCENT_MIN,
			self::WIDGET_ITEM_PERCENT_MAX
		))->setDefault(15);

		if (array_key_exists('desc_size', $this->data)) {
			$field_desc_size->setValue($this->data['desc_size']);
		}

		$this->fields[$field_desc_size->getName()] = $field_desc_size;

		// description bold
		$field_desc_bold = (new CWidgetFieldCheckBox('desc_bold', _('Bold')))->setDefault(0);

		if (array_key_exists('desc_bold', $this->data)) {
			$field_desc_bold->setValue($this->data['desc_bold']);
		}

		$this->fields[$field_desc_bold->getName()] = $field_desc_bold;

		// description color
		$field_desc_color = (new CWidgetFieldColor('desc_color', _('Color')))
			->setDefault('');

		if (array_key_exists('desc_color', $this->data)) {
			$field_desc_color->setValue($this->data['desc_color']);
		}

		$this->fields[$field_desc_color->getName()] = $field_desc_color;

		// value decimal places
		$field_decimal_places = (new CWidgetFieldIntegerBox('decimal_places', _('Decimal places'), 0, 10))
			->setDefault(2);

		if (array_key_exists('decimal_places', $this->data)) {
			$field_decimal_places->setValue($this->data['decimal_places']);
		}

		$this->fields[$field_decimal_places->getName()] = $field_decimal_places;

		// value decimal size
		$field_decimal_size = (new CWidgetFieldIntegerBox('decimal_size', _('Size'), self::WIDGET_ITEM_PERCENT_MIN,
			self::WIDGET_ITEM_PERCENT_MAX
		))->setDefault(35);

		if (array_key_exists('decimal_size', $this->data)) {
			$field_decimal_size->setValue($this->data['decimal_size']);
		}

		$this->fields[$field_decimal_size->getName()] = $field_decimal_size;

		// value horizontal position
		$field_value_h_pos = (new CWidgetFieldRadioButtonList('value_h_pos', _('Horizontal position'), [
			WIDGET_ITEM_POS_LEFT => _('Left'),
			WIDGET_ITEM_POS_CENTER => _('Center'),
			WIDGET_ITEM_POS_RIGHT => _('Right')
		]))
			->setDefault(WIDGET_ITEM_POS_CENTER)
			->setModern(true);

		if (array_key_exists('value_h_pos', $this->data)) {
			$field_value_h_pos->setValue($this->data['value_h_pos']);
		}

		$this->fields[$field_value_h_pos->getName()] = $field_value_h_pos;

		// value vertical position
		$field_value_v_pos = (new CWidgetFieldRadioButtonList('value_v_pos', _('Vertical position'), [
			WIDGET_ITEM_POS_TOP => _('Top'),
			WIDGET_ITEM_POS_MIDDLE => _('Middle'),
			WIDGET_ITEM_POS_BOTTOM => _('Bottom')
		]))
			->setDefault(WIDGET_ITEM_POS_MIDDLE)
			->setModern(true);

		if (array_key_exists('value_v_pos', $this->data)) {
			$field_value_v_pos->setValue($this->data['value_v_pos']);
		}

		$this->fields[$field_value_v_pos->getName()] = $field_value_v_pos;

		// value size
		$field_value_size = (new CWidgetFieldIntegerBox('value_size', _('Size'), self::WIDGET_ITEM_PERCENT_MIN,
			self::WIDGET_ITEM_PERCENT_MAX
		))->setDefault(45);

		if (array_key_exists('value_size', $this->data)) {
			$field_value_size->setValue($this->data['value_size']);
		}

		$this->fields[$field_value_size->getName()] = $field_value_size;

		// value bold
		$field_value_bold = (new CWidgetFieldCheckBox('value_bold', _('Bold')))->setDefault(1);

		if (array_key_exists('value_bold', $this->data)) {
			$field_value_bold->setValue($this->data['value_bold']);
		}

		$this->fields[$field_value_bold->getName()] = $field_value_bold;

		// value color
		$field_value_color = (new CWidgetFieldColor('value_color', _('Color')))
			->setDefault('');

		if (array_key_exists('value_color', $this->data)) {
			$field_value_color->setValue($this->data['value_color']);
		}

		$this->fields[$field_value_color->getName()] = $field_value_color;

		// units show
		$field_units_show = (new CWidgetFieldCheckBox('units_show', _('Units')))->setDefault(1);

		if (array_key_exists('units_show', $this->data)) {
			$field_units_show->setValue($this->data['units_show']);
		}

		$this->fields[$field_units_show->getName()] = $field_units_show;

		// units input field
		$field_units = new CWidgetFieldTextBox('units', _('Units'));

		if (array_key_exists('units', $this->data)) {
			$field_units->setValue($this->data['units']);
		}

		$this->fields[$field_units->getName()] = $field_units;

		// units position
		$field_units_pos = (new CWidgetFieldSelect('units_pos', _('Position'), [
			WIDGET_ITEM_POS_BEFORE => _('Before value'),
			WIDGET_ITEM_POS_ABOVE => _('Above value'),
			WIDGET_ITEM_POS_AFTER => _('After value'),
			WIDGET_ITEM_POS_BELOW => _('Below value')
		]))
			->setDefault(WIDGET_ITEM_POS_AFTER);

		if (array_key_exists('units_pos', $this->data)) {
			$field_units_pos->setValue($this->data['units_pos']);
		}

		$this->fields[$field_units_pos->getName()] = $field_units_pos;

		// units size
		$field_units_size = (new CWidgetFieldIntegerBox('units_size', _('Size'), self::WIDGET_ITEM_PERCENT_MIN,
			self::WIDGET_ITEM_PERCENT_MAX
		))->setDefault(35);

		if (array_key_exists('units_size', $this->data)) {
			$field_units_size->setValue($this->data['units_size']);
		}

		$this->fields[$field_units_size->getName()] = $field_units_size;

		// units bold
		$field_units_bold = (new CWidgetFieldCheckBox('units_bold', _('Bold')))->setDefault(1);

		if (array_key_exists('units_bold', $this->data)) {
			$field_units_bold->setValue($this->data['units_bold']);
		}

		$this->fields[$field_units_bold->getName()] = $field_units_bold;

		// units color
		$field_units_color = (new CWidgetFieldColor('units_color', _('Color')))
			->setDefault('');

		if (array_key_exists('units_color', $this->data)) {
			$field_units_color->setValue($this->data['units_color']);
		}

		$this->fields[$field_units_color->getName()] = $field_units_color;

		// time horizontal position
		$field_time_h_pos = (new CWidgetFieldRadioButtonList('time_h_pos', _('Horizontal position'), [
			WIDGET_ITEM_POS_LEFT => _('Left'),
			WIDGET_ITEM_POS_CENTER => _('Center'),
			WIDGET_ITEM_POS_RIGHT => _('Right')
		]))
			->setDefault(WIDGET_ITEM_POS_CENTER)
			->setModern(true);

		if (array_key_exists('time_h_pos', $this->data)) {
			$field_time_h_pos->setValue($this->data['time_h_pos']);
		}

		$this->fields[$field_time_h_pos->getName()] = $field_time_h_pos;

		// time vertical position
		$field_time_v_pos = (new CWidgetFieldRadioButtonList('time_v_pos', _('Vertical position'), [
			WIDGET_ITEM_POS_TOP => _('Top'),
			WIDGET_ITEM_POS_MIDDLE => _('Middle'),
			WIDGET_ITEM_POS_BOTTOM => _('Bottom')
		]))
			->setDefault(WIDGET_ITEM_POS_TOP)
			->setModern(true);

		if (array_key_exists('time_v_pos', $this->data)) {
			$field_time_v_pos->setValue($this->data['time_v_pos']);
		}

		$this->fields[$field_time_v_pos->getName()] = $field_time_v_pos;

		// time size
		$field_time_size = (new CWidgetFieldIntegerBox('time_size', _('Size'), self::WIDGET_ITEM_PERCENT_MIN,
			self::WIDGET_ITEM_PERCENT_MAX
		))->setDefault(15);

		if (array_key_exists('time_size', $this->data)) {
			$field_time_size->setValue($this->data['time_size']);
		}

		$this->fields[$field_time_size->getName()] = $field_time_size;

		// time bold
		$field_time_bold = (new CWidgetFieldCheckBox('time_bold', _('Bold')))->setDefault(0);

		if (array_key_exists('time_bold', $this->data)) {
			$field_time_bold->setValue($this->data['time_bold']);
		}

		$this->fields[$field_time_bold->getName()] = $field_time_bold;

		// time color
		$field_time_color = (new CWidgetFieldColor('time_color', _('Color')))
			->setDefault('');

		if (array_key_exists('time_color', $this->data)) {
			$field_time_color->setValue($this->data['time_color']);
		}

		$this->fields[$field_time_color->getName()] = $field_time_color;

		// change indicator up arrow color
		$field_up_color = (new CWidgetFieldColor('up_color', _('Change indicator')))
			->setDefault('');

		if (array_key_exists('up_color', $this->data)) {
			$field_up_color->setValue($this->data['up_color']);
		}

		$this->fields[$field_up_color->getName()] = $field_up_color;

		// change indicator down arrow color
		$field_down_color = (new CWidgetFieldColor('down_color', _('Change indicator')))
			->setDefault('');

		if (array_key_exists('down_color', $this->data)) {
			$field_down_color->setValue($this->data['down_color']);
		}

		$this->fields[$field_down_color->getName()] = $field_down_color;

		// change indicator up/down arrow color
		$field_updown_color = (new CWidgetFieldColor('updown_color', _('Change indicator')))
			->setDefault('');

		if (array_key_exists('updown_color', $this->data)) {
			$field_updown_color->setValue($this->data['updown_color']);
		}

		$this->fields[$field_updown_color->getName()] = $field_updown_color;

		// background color
		$field_bg_color = (new CWidgetFieldColor('bg_color', _('Background color')))
			->setDefault('');

		if (array_key_exists('bg_color', $this->data)) {
			$field_bg_color->setValue($this->data['bg_color']);
		}

		$this->fields[$field_bg_color->getName()] = $field_bg_color;

		// Dynamic item.
		if ($templateid === null) {
			$dynamic_item = (new CWidgetFieldCheckBox('dynamic', _('Dynamic item')))->setDefault(WIDGET_SIMPLE_ITEM);

			if (array_key_exists('dynamic', $this->data)) {
				$dynamic_item->setValue($this->data['dynamic']);
			}

			$this->fields[$dynamic_item->getName()] = $dynamic_item;
		}
	}

	/**
	 * Validate form fields.
	 *
	 * @param bool $strict  Enables more strict validation of the form fields.
	 *                      Must be enabled for validation of input parameters in the widget configuration form.
	 *
	 * @return array
	 */
	public function validate($strict = false) {
		$errors = parent::validate($strict);

		// Check if one of the objects (description, value or time) occupies same space.
		$fields = [
			['show' => WIDGET_ITEM_SHOW_DESCRIPTION, 'h_pos' => 'desc_h_pos', 'v_pos' => 'desc_v_pos'],
			['show' => WIDGET_ITEM_SHOW_VALUE, 'h_pos' => 'value_h_pos', 'v_pos' => 'value_v_pos'],
			['show' => WIDGET_ITEM_SHOW_TIME, 'h_pos' => 'time_h_pos', 'v_pos' => 'time_v_pos']
		];
		$fields_count = count($fields);
		$show = $this->fields['show']->getValue();

		for ($i = 0; $i < $fields_count - 1; $i++) {
			if (!in_array($fields[$i]['show'], $show)) {
				continue;
			}

			$i_h_pos = $this->fields[$fields[$i]['h_pos']]->getValue();
			$i_v_pos = $this->fields[$fields[$i]['v_pos']]->getValue();

			for ($j = $i + 1; $j < $fields_count; $j++) {
				if (!in_array($fields[$j]['show'], $show)) {
					continue;
				}

				$j_h_pos = $this->fields[$fields[$j]['h_pos']]->getValue();
				$j_v_pos = $this->fields[$fields[$j]['v_pos']]->getValue();

				if ($i_h_pos == $j_h_pos && $i_v_pos == $j_v_pos) {
					$errors[] = _('Two or more fields cannot occupy same space.');
					break 2;
				}
			}
		}

		return $errors;
	}
}
