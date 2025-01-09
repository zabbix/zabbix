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


namespace Widgets\Gauge\Includes;

use API,
	CNumberParser;

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
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldRadioButtonList,
	CWidgetFieldSelect,
	CWidgetFieldTextArea,
	CWidgetFieldTextBox,
	CWidgetFieldNumericBox,
	CWidgetFieldThresholds
};

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

	// Scale defaults.
	private const DEFAULT_MIN = 0;
	private const DEFAULT_MAX = 100;
	private const DEFAULT_SCALE_DECIMAL_PLACES = 0;
	private const DEFAULT_SCALE_SHOW_UNITS = 1;
	private const DEFAULT_SCALE_SIZE_PERCENT = 15;

	// Description defaults.
	private const DEFAULT_DESCRIPTION_SIZE_PERCENT = 15;
	private const DEFAULT_DESCRIPTION_BOLD = 0;

	// Decimal places defaults.
	private const DECIMAL_PLACES_MIN = 0;
	private const DECIMAL_PLACES_MAX = 10;
	private const DEFAULT_DECIMAL_PLACES = 2;

	// Value defaults.
	private const DEFAULT_VALUE_SIZE_PERCENT = 25;
	private const DEFAULT_VALUE_BOLD = 0;

	// Value arc defaults.
	private const DEFAULT_VALUE_ARC_SIZE_PERCENT = 20;

	// Unit defaults.
	private const DEFAULT_UNITS_SHOW = 1;
	private const DEFAULT_UNITS_SIZE_PERCENT = 25;
	private const DEFAULT_UNITS_BOLD = 0;

	// Threshold defaults.
	private const DEFAULT_TH_SHOW_LABELS = 0;
	private const DEFAULT_TH_SHOW_ARC = 0;
	private const DEFAULT_TH_ARC_SIZE_PERCENT = 5;

	private bool $is_binary_units = false;

	public function __construct(array $values, ?string $templateid) {
		parent::__construct($values, $templateid);

		if (array_key_exists('units', $this->values) && $this->values['units'] !== '') {
			$this->is_binary_units = isBinaryUnits($this->values['units']);
		}
		elseif (array_key_exists('itemid', $this->values) && is_array($this->values['itemid'])
				&& !array_key_exists(CWidgetField::FOREIGN_REFERENCE_KEY, $this->values['itemid'])) {
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

		if ($errors) {
			return $errors;
		}

		$number_parser = new CNumberParser([
			'with_size_suffix' => true,
			'with_time_suffix' => true,
			'is_binary_size' => $this->is_binary_units
		]);

		$number_parser->parse($this->getFieldValue('min'));
		$min = $number_parser->calcValue();

		$number_parser->parse($this->getFieldValue('max'));
		$max = $number_parser->calcValue();

		if ($min >= $max) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Max'), _s('value must be greater than "%1$s"', $min));
		}

		if (!$this->getFieldValue('show')) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Show'), _('at least one option must be selected'));
		}

		$min_threshold = null;
		$max_threshold = null;

		foreach ($this->getFieldValue('thresholds') as $threshold) {
			$number_parser->parse($threshold['threshold']);

			$threshold_value = $number_parser->calcValue();

			$min_threshold = $min_threshold !== null ? min($min_threshold, $threshold_value) : $threshold_value;
			$max_threshold = $max_threshold !== null ? max($max_threshold, $threshold_value) : $threshold_value;
		}

		if ($min_threshold !== null && $min_threshold < $min) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Thresholds'),
				_s('value must be no less than "%1$s"', $min)
			);
		}
		if ($max_threshold !== null && $max_threshold > $max) {
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
				(new CWidgetFieldCheckBoxList('show', _('Show'), [
					Widget::SHOW_DESCRIPTION => _('Description'),
					Widget::SHOW_VALUE => _('Value'),
					Widget::SHOW_VALUE_ARC => _('Value arc'),
					Widget::SHOW_NEEDLE => _('Needle'),
					Widget::SHOW_SCALE => _('Scale')
				]))
					->setDefault([Widget::SHOW_DESCRIPTION, Widget::SHOW_VALUE, Widget::SHOW_SCALE,
						Widget::SHOW_VALUE_ARC
					])
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			)
			->addField(
				(new CWidgetFieldTextArea('description', _('Description')))
					->setDefault('{ITEM.NAME}')
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldIntegerBox('desc_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->prefixLabel(_('Description'))
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
				(new CWidgetFieldIntegerBox('value_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->prefixLabel(_('Value'))
					->setDefault(self::DEFAULT_VALUE_SIZE_PERCENT)
			)
			->addField(
				new CWidgetFieldColor('value_color', _('Color'))
			)
			->addField(
				(new CWidgetFieldIntegerBox('value_arc_size', _('Size'),
					self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX
				))
					->prefixLabel(_('Value arc'))
					->setDefault(self::DEFAULT_VALUE_ARC_SIZE_PERCENT)
			)
			->addField(
				new CWidgetFieldColor('value_arc_color', _('Value arc'))
			)
			->addField(
				new CWidgetFieldColor('empty_color', _('Arc background'))
			)
			->addField(
				new CWidgetFieldColor('bg_color', _('Background'))
			)
			->addField(
				(new CWidgetFieldCheckBox('units_show', _('Units')))->setDefault(self::DEFAULT_UNITS_SHOW)
			)
			->addField(
				(new CWidgetFieldTextBox('units', _('Units')))->setMaxLength(255)
			)
			->addField(
				(new CWidgetFieldIntegerBox('units_size', _('Size'),self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->prefixLabel(_('Units'))
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
				new CWidgetFieldColor('needle_color', _('Color'))
			)
			->addField(
				(new CWidgetFieldIntegerBox('scale_decimal_places', _('Decimal places'),
					self::DECIMAL_PLACES_MIN, self::DECIMAL_PLACES_MAX
				))
					->setDefault(self::DEFAULT_SCALE_DECIMAL_PLACES)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY)
			)
			->addField(
				(new CWidgetFieldIntegerBox('scale_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->prefixLabel(_('Scale'))
					->setDefault(self::DEFAULT_SCALE_SIZE_PERCENT)
			)
			->addField(
				(new CWidgetFieldCheckBox('scale_show_units', _('Show units')))
					->setDefault(self::DEFAULT_SCALE_SHOW_UNITS)
			)
			->addField(
				new CWidgetFieldThresholds('thresholds', _('Thresholds'), $this->is_binary_units)
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
			);
	}
}
