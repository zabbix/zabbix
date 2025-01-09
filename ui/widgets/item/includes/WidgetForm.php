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


namespace Widgets\Item\Includes;

use API,
	CItemHelper,
	CWidgetsData;

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
	CWidgetFieldSparkline,
	CWidgetFieldThresholds,
	CWidgetFieldTimePeriod
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

	public const ITEM_VALUE_DATA_SOURCE_AUTO = 0;
	public const ITEM_VALUE_DATA_SOURCE_HISTORY = 1;
	public const ITEM_VALUE_DATA_SOURCE_TRENDS = 2;

	public const SPARKLINE_DEFAULT = [
		'width'		=> 1,
		'fill'		=> 3,
		'color'		=> '42A5F5',
		'time_period' => [
			'data_source' => CWidgetFieldTimePeriod::DATA_SOURCE_DEFAULT,
			'from' => 'now-1h',
			'to' => 'now'
		],
		'history'	=> CWidgetFieldSparkline::DATA_SOURCE_AUTO
	];

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

	public function getFieldsValues(): array {
		$fields_values = parent::getFieldsValues();

		if ($fields_values['aggregate_function'] == AGGREGATE_NONE) {
			unset($fields_values['time_period']);
		}

		return $fields_values;
	}

	public function validate(bool $strict = false): array {
		$show = $this->getFieldValue('show');
		$sparkline_enabled = in_array(Widget::SHOW_SPARKLINE, $show);
		$errors = [];

		foreach ($this->fields as $name => $field) {
			if (!$sparkline_enabled && $name === 'sparkline') {
				$field->setValue(CWidgetFieldSparkline::DEFAULT_VALUE);
			}
			else {
				$errors = array_merge($errors, $field->validate($strict));
			}
		}

		if ($errors) {
			return $errors;
		}

		if (!$show) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Show'), _('at least one option must be selected'));

			return $errors;
		}

		// Check if one of the objects (description, value or time) occupies same space.
		$fields = [
			['show' => Widget::SHOW_DESCRIPTION, 'h_pos' => 'desc_h_pos', 'v_pos' => 'desc_v_pos'],
			['show' => Widget::SHOW_VALUE, 'h_pos' => 'value_h_pos', 'v_pos' => 'value_v_pos'],
			['show' => Widget::SHOW_TIME, 'h_pos' => 'time_h_pos', 'v_pos' => 'time_v_pos']
		];

		$fields_count = count($fields);

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

	protected function normalizeValues(array $values): array {
		$values = parent::normalizeValues($values);

		if (array_key_exists('show', $values) && is_array($values['show'])
				&& in_array(Widget::SHOW_SPARKLINE, $values['show'])) {
			$values['sparkline'] = array_key_exists('sparkline', $values)
				? array_replace(WidgetForm::SPARKLINE_DEFAULT, $values['sparkline'])
				: WidgetForm::SPARKLINE_DEFAULT;
		}

		return $values;
	}

	public function addFields(): self {
		return $this
			->addField(
				(new CWidgetFieldMultiSelectItem('itemid', _('Item')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
			)
			->addField(
				(new CWidgetFieldCheckBoxList('show', _('Show'), [
					Widget::SHOW_DESCRIPTION => _('Description'),
					Widget::SHOW_VALUE => _('Value'),
					Widget::SHOW_TIME => _('Time'),
					Widget::SHOW_CHANGE_INDICATOR => _('Change indicator'),
					Widget::SHOW_SPARKLINE => _('Sparkline')
				]))
					->setDefault([Widget::SHOW_DESCRIPTION, Widget::SHOW_VALUE, Widget::SHOW_TIME,
						Widget::SHOW_CHANGE_INDICATOR
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
				(new CWidgetFieldTextBox('units', _('Units')))->setMaxLength(255)
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
				(new CWidgetFieldSparkline('sparkline', _('Sparkline')))->setDefault(self::SPARKLINE_DEFAULT)
			)
			->addField(
				new CWidgetFieldColor('bg_color', _('Background color'))
			)
			->addField(
				(new CWidgetFieldSelect('aggregate_function', _('Aggregation function'), [
					AGGREGATE_NONE => CItemHelper::getAggregateFunctionName(AGGREGATE_NONE),
					AGGREGATE_MIN => CItemHelper::getAggregateFunctionName(AGGREGATE_MIN),
					AGGREGATE_MAX => CItemHelper::getAggregateFunctionName(AGGREGATE_MAX),
					AGGREGATE_AVG => CItemHelper::getAggregateFunctionName(AGGREGATE_AVG),
					AGGREGATE_COUNT => CItemHelper::getAggregateFunctionName(AGGREGATE_COUNT),
					AGGREGATE_SUM => CItemHelper::getAggregateFunctionName(AGGREGATE_SUM),
					AGGREGATE_FIRST => CItemHelper::getAggregateFunctionName(AGGREGATE_FIRST),
					AGGREGATE_LAST => CItemHelper::getAggregateFunctionName(AGGREGATE_LAST)
				]))->setDefault(AGGREGATE_NONE)
			)
			->addField(
				(new CWidgetFieldTimePeriod('time_period', _('Time period')))
					->setDefault([
						CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
							CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
						)
					])
					->setDefaultPeriod(['from' => 'now-1h', 'to' => 'now'])
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('history', _('History data'), [
					self::ITEM_VALUE_DATA_SOURCE_AUTO => _('Auto'),
					self::ITEM_VALUE_DATA_SOURCE_HISTORY => _('History'),
					self::ITEM_VALUE_DATA_SOURCE_TRENDS => _('Trends')
				]))->setDefault(self::ITEM_VALUE_DATA_SOURCE_AUTO)
			)
			->addField(
				new CWidgetFieldThresholds('thresholds', _('Thresholds'), $this->is_binary_units)
			);
	}
}
