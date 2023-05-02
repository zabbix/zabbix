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


namespace Widgets\Gauge\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldColor,
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectItem,
	CWidgetFieldRadioButtonList,
	CWidgetFieldSelect,
	CWidgetFieldTextArea,
	CWidgetFieldTextBox,
	CWidgetFieldNumericBox,
	CWidgetFieldThresholds
};

use CNumberParser,
	CParser;

use Widgets\Gauge\Widget;

/**
 * Gauge widget form.
 */
class WidgetForm extends CWidgetForm {

	private const SIZE_PERCENT_MIN = 1;
	private const SIZE_PERCENT_MAX = 100;

	// Angle values and defaults.
	private const ANGLES = [180, 270];
	private const DEFAULT_ANGLE = 180;

	// Min/Max defaults.
	private const DEFAULT_MIN = 0;
	private const DEFAULT_MAX = 100;
	private const DEFAULT_MINMAX_SHOW = 1;
	private const DEFAULT_MINMAX_SHOW_UNITS = 0;
	private const DEFAULT_MINMAX_SIZE_PERCENT = 5;

	// Description defaults.
	private const DEFAULT_DESCRIPTION_SIZE_PERCENT = 15;
	private const DEFAULT_DESCRIPTION_BOLD = 0;

	// Decimal places defaults.
	private const DECIMAL_PLACES_MIN = 0;
	private const DECIMAL_PLACES_MAX = 10;
	private const DEFAULT_DECIMAL_PLACES = 2;

	// Value defaults.
	private const DEFAULT_VALUE_SIZE_PERCENT = 30;
	private const DEFAULT_VALUE_ARC_SHOW = 1;
	private const DEFAULT_VALUE_BOLD = 0;

	// Value arc defaults.
	private const DEFAULT_VALUE_ARC_SIZE_PERCENT = 5;

	// Unit defaults.
	private const DEFAULT_UNITS_SHOW = 1;
	private const DEFAULT_UNITS_SIZE_PERCENT = 30;
	private const DEFAULT_UNITS_BOLD = 1;

	// Needle defaults.
	private const DEFAULT_NEEDLE_SHOW = 0;

	// Threshold defaults.
	private const DEFAULT_TH_SHOW_LABELS = 0;
	private const DEFAULT_TH_SHOW_ARC = 0;
	private const DEFAULT_TH_ARC_SIZE_PERCENT = 10;

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

		$min = $number_parser->parse($this->getFieldValue('min')) == CParser::PARSE_SUCCESS
			? $number_parser->calcValue()
			: null;

		$max = $number_parser->parse($this->getFieldValue('max')) == CParser::PARSE_SUCCESS
			? $number_parser->calcValue()
			: null;

		if ($min !== null && $max !== null && $min >= $max) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Max'), _s('value must be greater than "%1$s"', $min));
		}

		$prev_treshold = null;
		$min_treshold = null;
		$max_treshold = null;

		foreach ($this->getFieldValue('thresholds') as $threshold) {
			$threshold_value = $number_parser->parse($threshold['threshold_value']) == CParser::PARSE_SUCCESS
				? $number_parser->calcValue()
				: null;

			if ($threshold_value !== null) {
				if ($prev_treshold === null) {
					$min_treshold = $threshold_value;
					$max_treshold = $threshold_value;
				}
				elseif ($threshold_value > $prev_treshold) {
					$max_treshold = $threshold_value;
				}
				elseif ($threshold_value < $prev_treshold) {
					$min_treshold = $threshold_value;
				}
			}
		}

		if ($min_treshold !== null && $min_treshold < $min) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Thresholds'),
				_s('value must be no less than "%1$s"', $min)
			);
		}
		if ($max_treshold !== null && $max_treshold > $max) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Thresholds'),
				_s('value must be no greater than "%1$s"', $max)
			);
		}

		return $errors;
	}

	public function addFields(): self {
		return $this
			->addField(
				(new CWidgetFieldMultiSelectItem('itemid', _('Item')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('angle', _('Angle'),
					array_combine(self::ANGLES, array_map(static fn(int $angle): string => $angle.'°', self::ANGLES))
				))
					->setDefault(self::DEFAULT_ANGLE)
			)
			->addField(
				(new CWidgetFieldNumericBox('min', _('Min')))
					->setDefault(self::DEFAULT_MIN)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldNumericBox('max', _('Max')))
				->setDefault(self::DEFAULT_MAX)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
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
				(new CWidgetFieldIntegerBox('desc_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->setDefault(self::DEFAULT_DESCRIPTION_SIZE_PERCENT)
			)
			->addField(
				(new CWidgetFieldCheckBox('desc_bold', _('Bold')))->setDefault(self::DEFAULT_DESCRIPTION_BOLD)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('desc_v_pos', _('Vertical position'), [
					Widget::DESC_V_POSITION_TOP => _('Top'),
					Widget::DESC_V_POSITION_BOTTOM => _('Bottom')
				]))->setDefault(Widget::DESC_V_POSITION_BOTTOM)
			)
			->addField(
				new CWidgetFieldColor('desc_color', _('Color'))
			)
			->addField(
				(new CWidgetFieldIntegerBox('decimal_places', _('Decimal places'),
					self::DECIMAL_PLACES_MIN, self::DECIMAL_PLACES_MAX
				))
					->setDefault(self::DEFAULT_DECIMAL_PLACES)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY)
			)
			->addField(
				(new CWidgetFieldCheckBox('value_bold', _('Bold')))->setDefault(self::DEFAULT_VALUE_BOLD)
			)
			->addField(
				(new CWidgetFieldCheckBox('value_arc', _('Arc')))->setDefault(self::DEFAULT_VALUE_ARC_SHOW)
			)
			->addField(
				(new CWidgetFieldIntegerBox('value_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->setDefault(self::DEFAULT_VALUE_SIZE_PERCENT)
			)
			->addField(
				new CWidgetFieldColor('value_color', _('Color'))
			)
			->addField(
				(new CWidgetFieldIntegerBox('value_arc_size', _('Arc size'),
					self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX
				))->setDefault(self::DEFAULT_VALUE_ARC_SIZE_PERCENT)
			)
			->addField(
				(new CWidgetFieldCheckBox('units_show', _('Units')))->setDefault(self::DEFAULT_UNITS_SHOW)
			)
			->addField(
				new CWidgetFieldTextBox('units', _('Units'))
			)
			->addField(
				(new CWidgetFieldIntegerBox('units_size', _('Size'),self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->setDefault(self::DEFAULT_UNITS_SIZE_PERCENT)
			)
			->addField(
				(new CWidgetFieldSelect('units_pos', _('Position'), [
					Widget::UNITS_POSITION_BEFORE => _('Before value'),
					Widget::UNITS_POSITION_ABOVE => _('Above value'),
					Widget::UNITS_POSITION_AFTER => _('After value'),
					Widget::UNITS_POSITION_BELOW => _('Below value')
				]))->setDefault(Widget::UNITS_POSITION_AFTER)
			)
			->addField(
				(new CWidgetFieldCheckBox('units_bold', _('Bold')))->setDefault(self::DEFAULT_UNITS_BOLD)
			)
			->addField(
				new CWidgetFieldColor('units_color', _('Color'))
			)
			->addField(
				(new CWidgetFieldCheckBox('needle_show', _('Needle')))->setDefault(self::DEFAULT_NEEDLE_SHOW)
			)
			->addField(
				new CWidgetFieldColor('needle_color', _('Color'))
			)
			->addField(
				(new CWidgetFieldCheckBox('minmax_show', _('Min/Max')))->setDefault(self::DEFAULT_MINMAX_SHOW)
			)
			->addField(
				(new CWidgetFieldIntegerBox('minmax_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->setDefault(self::DEFAULT_MINMAX_SIZE_PERCENT)
			)
			->addField(
				(new CWidgetFieldCheckBox('minmax_show_units', _('Show units')))
					->setDefault(self::DEFAULT_MINMAX_SHOW_UNITS)
			)
			->addField(
				new CWidgetFieldColor('empty_color', _('Empty color'))
			)
			->addField(
				new CWidgetFieldColor('bg_color', _('Background color'))
			)
			->addField(
				new CWidgetFieldThresholds('thresholds', _('Thresholds'))
			)
			->addField(
				(new CWidgetFieldCheckBox('th_show_labels', _('Show labels')))->setDefault(self::DEFAULT_TH_SHOW_LABELS)
			)
			->addField(
				(new CWidgetFieldCheckBox('th_show_arc', _('Show arc')))->setDefault(self::DEFAULT_TH_SHOW_ARC)
			)
			->addField(
				(new CWidgetFieldIntegerBox('th_arc_size', _('Arc size'), self::SIZE_PERCENT_MIN,
					self::SIZE_PERCENT_MAX
				))->setDefault(self::DEFAULT_TH_ARC_SIZE_PERCENT)
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldCheckBox('dynamic', _('Enable host selection'))
			);
	}
}
