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


namespace Widgets\PieChart\Includes;

use CRangeTimeParser,
	CSettingsHelper;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldColor,
	CWidgetFieldDatePicker,
	CWidgetFieldIntegerBox,
	CWidgetFieldRadioButtonList,
	CWidgetFieldRangeControl,
	CWidgetFieldTextBox
};

/**
 * Pie chart widget form view.
 */
class WidgetForm extends CWidgetForm {

	public const DATA_SOURCE_AUTO = 0;
	public const DATA_SOURCE_HISTORY = 1;
	public const DATA_SOURCE_TRENDS = 2;

	public const DRAW_TYPE_DOUGHNUT = 1;
	private const DRAW_TYPE_PIE = 0;

	private const CUSTOM_TIME_ON = 1;

	public const LEGEND_ON = 1;
	private const LEGEND_COLUMNS_MAX = 4;
	private const LEGEND_COLUMNS_MIN = 1;
	private const LEGEND_LINES_MAX = 10;
	private const LEGEND_LINES_MIN = 1;

	private const MERGE_PERCENT_MAX = 10;
	private const MERGE_PERCENT_MIN = 1;

	private const SPACE_DEFAULT = 1;
	private const SPACE_MAX = 10;
	private const SPACE_MIN = 0;

	private const VALUE_DECIMALS_DEFAULT = 2;
	private const VALUE_DECIMALS_MAX = 6;
	private const VALUE_DECIMALS_MIN = 0;

	public const VALUE_SIZE_CUSTOM = 1;
	private const VALUE_SIZE_AUTO = 0;
	private const VALUE_SIZE_DEFAULT = 20;
	private const VALUE_SIZE_MAX = 100;
	private const VALUE_SIZE_MIN = 1;

	private const WIDTH_DEFAULT = 50;
	private const WIDTH_MIN = 20;
	private const WIDTH_STEP = 10;

	private bool $graph_time_on = false;
	private bool $legend_on = true;

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($this->getFieldValue('graph_time') == self::CUSTOM_TIME_ON) {
			$errors = array_merge($errors, self::validateTimeSelectorPeriod($this->getFieldValue('time_from'),
				$this->getFieldValue('time_to')
			));
		}

		return $errors;
	}

	protected function normalizeValues(array $values): array {
		$values = parent::normalizeValues($values);

		if (array_key_exists('graph_time', $values)) {
			$this->graph_time_on = $values['graph_time'] == self::CUSTOM_TIME_ON;
		}

		if (!$this->graph_time_on) {
			unset($values['time_from'], $values['time_to']);
		}

		if (array_key_exists('legend', $values)) {
			$this->legend_on = $values['legend'] == self::LEGEND_ON;
		}

		return $values;
	}

	public function addFields(): self {
		return $this
			->initDataSetFields()
			->initDisplayingOptionsFields()
			->initTimePeriodFields()
			->initLegendFields();
	}

	private function initDataSetFields(): self {
		return $this->addField(
			(new CWidgetFieldDataSet('ds', _('Data set')))->setFlags(CWidgetField::FLAG_NOT_EMPTY)
		);
	}

	private function initDisplayingOptionsFields(): self {
		return $this
			->addField(
				(new CWidgetFieldRadioButtonList('source', _('History data selection'), [
					self::DATA_SOURCE_AUTO => _x('Auto', 'history source selection method'),
					self::DATA_SOURCE_HISTORY => _('History'),
					self::DATA_SOURCE_TRENDS => _('Trends')
				]))->setDefault(self::DATA_SOURCE_AUTO)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('draw_type', _('Draw'), [
					self::DRAW_TYPE_PIE => _('Pie'),
					self::DRAW_TYPE_DOUGHNUT => _('Doughnut')
				]))->setDefault(self::DRAW_TYPE_PIE)
			)
			->addField(
				(new CWidgetFieldRangeControl('width', _('Width'),
					self::WIDTH_MIN, self::WIDTH_DEFAULT, self::WIDTH_STEP
				))
					->setDefault(self::WIDTH_DEFAULT)
			)
			->addField(
				(new CWidgetFieldRangeControl('space', _('Space between sectors'),
					self::SPACE_MIN, self::SPACE_MAX
				))
					->setDefault(self::SPACE_DEFAULT)
			)
			->addField(
				new CWidgetFieldCheckBox('merge', null, _('Merge sectors smaller than '))
			)
			->addField(
				(new CWidgetFieldIntegerBox('merge_percent', null,
					self::MERGE_PERCENT_MIN, self::MERGE_PERCENT_MAX
				))
					->setDefault(self::MERGE_PERCENT_MIN)
			)
			->addField(
				(new CWidgetFieldColor('merge_color'))
			)
			->addField(
				(new CWidgetFieldCheckBox('total_show', _('Show total value')))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('value_size_type', _('Size'), [
					self::VALUE_SIZE_AUTO => _('Auto'),
					self::VALUE_SIZE_CUSTOM => _('Custom')
				]))->setDefault(self::VALUE_SIZE_AUTO)
			)
			->addField(
				(new CWidgetFieldIntegerBox('value_size', null,
					self::VALUE_SIZE_MIN, self::VALUE_SIZE_MAX
				))
					->setDefault(self::VALUE_SIZE_DEFAULT)
			)
			->addField(
				(new CWidgetFieldIntegerBox('decimal_places', _('Decimal places'),
					self::VALUE_DECIMALS_MIN, self::VALUE_DECIMALS_MAX
				))
					->setDefault(self::VALUE_DECIMALS_DEFAULT)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY)
			)
			->addField(
				(new CWidgetFieldCheckBox('units_show', null, _('Units')))
			)
			->addField(
				(new CWidgetFieldTextBox('units'))
			)
			->addField(
				(new CWidgetFieldCheckBox('value_bold', _('Bold')))
			)
			->addField(
				new CWidgetFieldColor('value_color', _('Color'))
			);
	}

	private function initTimePeriodFields(): self {
		return $this
			->addField(
				new CWidgetFieldCheckBox('graph_time', _('Set custom time period'))
			)
			->addField(
				(new CWidgetFieldDatePicker('time_from', _('From')))
					->setDefault('now-1h')
					->setFlags($this->graph_time_on
						? CWidgetField::FLAG_NOT_EMPTY
						: CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_DISABLED
					)
			)
			->addField(
				(new CWidgetFieldDatePicker('time_to', _('To')))
					->setDefault('now')
					->setFlags($this->graph_time_on
						? CWidgetField::FLAG_NOT_EMPTY
						: CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_DISABLED
					)
			);
	}

	private function initLegendFields(): self {
		return $this
			->addField(
				(new CWidgetFieldCheckBox('legend', _('Show legend')))->setDefault(self::LEGEND_ON)
			)
			->addField(
				(new CWidgetFieldCheckBox('legend_aggregation', _('Show aggregation function')))
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldRangeControl('legend_lines', _('Number of rows'),
					self::LEGEND_LINES_MIN, self::LEGEND_LINES_MAX
				))
					->setDefault(self::LEGEND_LINES_MIN)
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldRangeControl('legend_columns', _('Number of columns'),
					self::LEGEND_COLUMNS_MIN, self::LEGEND_COLUMNS_MAX
				))
					->setDefault(self::LEGEND_COLUMNS_MAX)
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
			);
	}


	/**
	 * Check if widget configuration is set to use custom time.
	 */
	public static function hasOverrideTime(array $fields_values): bool {
		return array_key_exists('graph_time', $fields_values)
			&& $fields_values['graph_time'] == self::CUSTOM_TIME_ON;
	}

	private static function validateTimeSelectorPeriod(string $from, string $to): array {
		$errors = [];
		$ts = [];
		$ts['now'] = time();
		$range_time_parser = new CRangeTimeParser();

		foreach (['from' => $from, 'to' => $to] as $field => $value) {
			$range_time_parser->parse($value);
			$ts[$field] = $range_time_parser->getDateTime($field === 'from')->getTimestamp();
		}

		$period = $ts['to'] - $ts['from'] + 1;
		$range_time_parser->parse('now-'.CSettingsHelper::get(CSettingsHelper::MAX_PERIOD));
		$max_period = 1 + $ts['now'] - $range_time_parser->getDateTime(true)->getTimestamp();

		if ($period < ZBX_MIN_PERIOD) {
			$errors[] = _n('Minimum time period to display is %1$s minute.',
				'Minimum time period to display is %1$s minutes.', (int) (ZBX_MIN_PERIOD / SEC_PER_MIN)
			);
		}
		elseif ($period > $max_period) {
			$errors[] = _n('Maximum time period to display is %1$s day.',
				'Maximum time period to display is %1$s days.', (int) round($max_period / SEC_PER_DAY)
			);
		}

		return $errors;
	}
}
