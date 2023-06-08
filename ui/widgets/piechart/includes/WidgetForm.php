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

use Zabbix\Widgets\Fields\{CWidgetFieldCheckBox,
	CWidgetFieldColor,
	CWidgetFieldDatePicker,
	CWidgetFieldPieChartDataSet,
	CWidgetFieldIntegerBox,
	CWidgetFieldRadioButtonList,
	CWidgetFieldRangeControl,
	CWidgetFieldTextBox};

/**
 * Pie chart widget form view.
 */
class WidgetForm extends CWidgetForm {

	private bool $chart_time_on = false;
	private bool $legend_on = true;

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		// Test chart custom time period.
		if ($this->getFieldValue('chart_time') == PIE_CHART_CUSTOM_TIME_ON) {
			$errors = array_merge($errors, self::validateTimeSelectorPeriod($this->getFieldValue('time_from'),
				$this->getFieldValue('time_to')
			));
		}

		return $errors;
	}

	protected function normalizeValues(array $values): array {
		$values = parent::normalizeValues($values);

		if (array_key_exists('chart_time', $values)) {
			$this->chart_time_on = $values['chart_time'] == PIE_CHART_CUSTOM_TIME_ON;
		}

		if (!$this->chart_time_on) {
			unset($values['time_from'], $values['time_to']);
		}

		if (array_key_exists('legend', $values)) {
			$this->legend_on = $values['legend'] == PIE_CHART_LEGEND_ON;
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
			(new CWidgetFieldPieChartDataSet('ds', _('Data set')))->setFlags(CWidgetField::FLAG_NOT_EMPTY)
		);
	}

	private function initDisplayingOptionsFields(): self {
		return $this
			->addField(
				(new CWidgetFieldRadioButtonList('source', _('History data selection'), [
					PIE_CHART_DATA_SOURCE_AUTO => _x('Auto', 'history source selection method'),
					PIE_CHART_DATA_SOURCE_HISTORY => _('History'),
					PIE_CHART_DATA_SOURCE_TRENDS => _('Trends')
				]))->setDefault(PIE_CHART_DATA_SOURCE_AUTO)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('draw', _('Draw'), [
					PIE_CHART_DRAW_PIE => _('Pie'),
					PIE_CHART_DRAW_DOUGHNUT => _('Doughnut')
				]))->setDefault(PIE_CHART_DRAW_PIE)
			)
			->addField(
				(new CWidgetFieldRangeControl('width', _('Width'),
					PIE_CHART_WIDTH_MIN, PIE_CHART_WIDTH_DEFAULT, PIE_CHART_WIDTH_STEP
				))
					->setDefault(PIE_CHART_WIDTH_DEFAULT)
			)
			->addField(
				(new CWidgetFieldRangeControl('stroke', _('Stroke width'),
					PIE_CHART_STROKE_MIN, PIE_CHART_STROKE_MAX
				))
					->setDefault(PIE_CHART_STROKE_DEFAULT)
			)
			->addField(
				(new CWidgetFieldRangeControl('sector_space', _('Space between sectors'),
					PIE_CHART_SECTORS_SPACE_MIN, PIE_CHART_SECTORS_SPACE_MAX
				))
					->setDefault(PIE_CHART_SECTORS_SPACE_DEFAULT)
			)
			->addField(
				new CWidgetFieldCheckBox('merge_sectors', null, _('Merge sectors smaller than '))
			)
			->addField(
				(new CWidgetFieldIntegerBox('merge_percentage', null, 1, 100))
					->setDefault(PIE_CHART_MERGE_PERCENT_DEFAULT)
			)
			->addField(
				(new CWidgetFieldColor('merge_color'))
			)
			->addField(
				(new CWidgetFieldCheckBox('show_total', _('Show total value')))
			)
			->addField(
				(new CWidgetFieldIntegerBox('value_size', _('Size'), 1, 100))
					->setDefault(PIE_CHART_VALUE_SIZE_DEFAULT)
			)
			->addField(
				(new CWidgetFieldIntegerBox('decimal_places', _('Decimal places'), 1, 6))
					->setDefault(PIE_CHART_VALUE_DECIMALS_DEFAULT)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY)
			)
			->addField(
				(new CWidgetFieldCheckBox('units_show', null, _('Units')))
			)
			->addField(
				(new CWidgetFieldTextBox('units_value'))
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
				new CWidgetFieldCheckBox('chart_time', _('Set custom time period'))
			)
			->addField(
				(new CWidgetFieldDatePicker('time_from', _('From')))
					->setDefault('now-1h')
					->setFlags($this->chart_time_on
						? CWidgetField::FLAG_NOT_EMPTY
						: CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_DISABLED
					)
			)
			->addField(
				(new CWidgetFieldDatePicker('time_to', _('To')))
					->setDefault('now')
					->setFlags($this->chart_time_on
						? CWidgetField::FLAG_NOT_EMPTY
						: CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_DISABLED
					)
			);
	}

	private function initLegendFields(): self {
		return $this
			->addField(
				(new CWidgetFieldCheckBox('legend', _('Show legend')))->setDefault(PIE_CHART_LEGEND_ON)
			)
			->addField(
				(new CWidgetFieldRangeControl('legend_lines', _('Number of rows'),
					PIE_CHART_LEGEND_LINES_MIN, PIE_CHART_LEGEND_LINES_MAX
				))
					->setDefault(PIE_CHART_LEGEND_LINES_MIN)
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldRangeControl('legend_columns', _('Number of columns'),
					PIE_CHART_LEGEND_COLUMNS_MIN, PIE_CHART_LEGEND_COLUMNS_MAX
				))
					->setDefault(PIE_CHART_LEGEND_COLUMNS_MAX)
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
			);
	}


	/**
	 * Check if widget configuration is set to use custom time.
	 */
	public static function hasOverrideTime(array $fields_values): bool {
		return array_key_exists('chart_time', $fields_values)
			&& $fields_values['chart_time'] == PIE_CHART_CUSTOM_TIME_ON;
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
