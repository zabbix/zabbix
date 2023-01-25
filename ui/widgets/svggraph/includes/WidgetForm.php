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


namespace Widgets\SvgGraph\Includes;

use CNumberParser,
	CParser,
	CRangeTimeParser,
	CSettingsHelper;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldDatePicker,
	CWidgetFieldGraphDataSet,
	CWidgetFieldGraphOverride,
	CWidgetFieldHostPatternSelect,
	CWidgetFieldNumericBox,
	CWidgetFieldRadioButtonList,
	CWidgetFieldRangeControl,
	CWidgetFieldSelect,
	CWidgetFieldSeverities,
	CWidgetFieldTags,
	CWidgetFieldTextBox
};

/**
 * Graph widget form view.
 */
class WidgetForm extends CWidgetForm {

	private const PERCENTILE_MIN = 1;
	private const PERCENTILE_MAX = 100;

	private bool $percentile_left_on = false;
	private bool $percentile_right_on = false;

	private bool $graph_time_on = false;

	private bool $lefty_on = true;
	private bool $lefty_units_static = false;
	private bool $righty_on = true;
	private bool $righty_units_static = false;

	private bool $legend_on = true;
	private bool $legend_statistic_on = false;

	private bool $problems_on = false;

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		$number_parser_w_suffix = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);
		$number_parser_wo_suffix = new CNumberParser();

		// Percentiles
		if ($this->getFieldValue('percentile_left') == SVG_GRAPH_PERCENTILE_LEFT_ON) {
			$percentile_left_value = $this->getFieldValue('percentile_left_value');

			if ($percentile_left_value !== '') {
				$percentile_left_value_calculated =
						$number_parser_wo_suffix->parse($percentile_left_value) == CParser::PARSE_SUCCESS
					? $number_parser_wo_suffix->calcValue()
					: null;

				if ($percentile_left_value_calculated === null
						|| $percentile_left_value_calculated < self::PERCENTILE_MIN
						|| $percentile_left_value_calculated > self::PERCENTILE_MAX) {
					$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Percentile line (left)'),
						_s('value must be between "%1$s" and "%2$s"', self::PERCENTILE_MIN, self::PERCENTILE_MAX)
					);
				}
			}
		}

		if ($this->getFieldValue('percentile_right') == SVG_GRAPH_PERCENTILE_RIGHT_ON) {
			$percentile_right_value = $this->getFieldValue('percentile_right_value');

			if ($percentile_right_value !== '') {
				$percentile_right_value_calculated =
						$number_parser_wo_suffix->parse($percentile_right_value) == CParser::PARSE_SUCCESS
					? $number_parser_wo_suffix->calcValue()
					: null;

				if ($percentile_right_value_calculated === null
						|| $percentile_right_value_calculated < self::PERCENTILE_MIN
						|| $percentile_right_value_calculated > self::PERCENTILE_MAX) {
					$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Percentile line (right)'),
						_s('value must be between "%1$s" and "%2$s"', self::PERCENTILE_MIN, self::PERCENTILE_MAX)
					);
				}
			}
		}

		// Test graph custom time period.
		if ($this->getFieldValue('graph_time') == SVG_GRAPH_CUSTOM_TIME_ON) {
			$errors = array_merge($errors, self::validateTimeSelectorPeriod($this->getFieldValue('time_from'),
				$this->getFieldValue('time_to')
			));
		}

		// Validate Min/Max values in Axes tab.
		if ($this->getFieldValue('lefty') == SVG_GRAPH_AXIS_ON) {
			$lefty_min = $number_parser_w_suffix->parse($this->getFieldValue('lefty_min')) == CParser::PARSE_SUCCESS
				? $number_parser_w_suffix->calcValue()
				: null;

			$lefty_max = $number_parser_w_suffix->parse($this->getFieldValue('lefty_max')) == CParser::PARSE_SUCCESS
				? $number_parser_w_suffix->calcValue()
				: null;

			if ($lefty_min !== null && $lefty_max !== null && $lefty_min >= $lefty_max) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Left Y').'/'._('Max'),
					_('Y axis MAX value must be greater than Y axis MIN value')
				);
			}
		}

		if ($this->getFieldValue('righty') == SVG_GRAPH_AXIS_ON) {
			$righty_min = $number_parser_w_suffix->parse($this->getFieldValue('righty_min')) == CParser::PARSE_SUCCESS
				? $number_parser_w_suffix->calcValue()
				: null;

			$righty_max = $number_parser_w_suffix->parse($this->getFieldValue('righty_max')) == CParser::PARSE_SUCCESS
				? $number_parser_w_suffix->calcValue()
				: null;

			if ($righty_min !== null && $righty_max !== null && $righty_min >= $righty_max) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Right Y').'/'._('Max'),
					_('Y axis MAX value must be greater than Y axis MIN value')
				);
			}
		}

		return $errors;
	}

	protected function normalizeValues(array $values): array {
		$values = parent::normalizeValues($values);

		if (array_key_exists('percentile_left', $values)) {
			$this->percentile_left_on = $values['percentile_left'] == SVG_GRAPH_PERCENTILE_LEFT_ON;
		}

		if (!$this->percentile_left_on) {
			unset($values['percentile_left_value']);
		}

		if (array_key_exists('percentile_right', $values)) {
			$this->percentile_right_on = $values['percentile_right'] == SVG_GRAPH_PERCENTILE_RIGHT_ON;
		}

		if (!$this->percentile_right_on) {
			unset($values['percentile_right_value']);
		}

		if (array_key_exists('graph_time', $values)) {
			$this->graph_time_on = $values['graph_time'] == SVG_GRAPH_CUSTOM_TIME_ON;
		}

		if (!$this->graph_time_on) {
			unset($values['time_from'], $values['time_to']);
		}

		if (array_key_exists('lefty', $values)) {
			$this->lefty_on = $values['lefty'] == SVG_GRAPH_AXIS_ON;
		}

		if (!$this->lefty_on) {
			unset($values['lefty_min'], $values['lefty_max'], $values['lefty_units']);
		}

		if (array_key_exists('lefty_units', $values)) {
			$this->lefty_units_static = $values['lefty_units'] == SVG_GRAPH_AXIS_UNITS_STATIC;
		}

		if (!$this->lefty_on || !$this->lefty_units_static) {
			unset($values['lefty_static_units']);
		}

		if (array_key_exists('righty', $values)) {
			$this->righty_on = $values['righty'] == SVG_GRAPH_AXIS_ON;
		}

		if (!$this->righty_on) {
			unset($values['righty_min'], $values['righty_max'], $values['righty_units']);
		}

		if (array_key_exists('righty_units', $values)) {
			$this->righty_units_static = $values['righty_units'] == SVG_GRAPH_AXIS_UNITS_STATIC;
		}

		if (!$this->righty_on || !$this->righty_units_static) {
			unset($values['righty_static_units']);
		}

		if (array_key_exists('legend', $values)) {
			$this->legend_on = $values['legend'] == SVG_GRAPH_LEGEND_ON;
		}

		if (array_key_exists('legend_statistic', $values)) {
			$this->legend_statistic_on = $values['legend_statistic'] == SVG_GRAPH_LEGEND_STATISTIC_ON;
		}

		if (array_key_exists('show_problems', $values)) {
			$this->problems_on = $values['show_problems'] == SVG_GRAPH_PROBLEMS_ON;
		}

		if (!$this->problems_on) {
			unset($values['graph_item_problems'], $values['problemhosts'], $values['severities'],
				$values['problem_name'], $values['evaltype'], $values['tags']
			);
		}

		return $values;
	}

	public function addFields(): self {
		return $this
			->initDataSetFields()
			->initDisplayingOptionsFields()
			->initTimePeriodFields()
			->initAxesFields()
			->initLegendFields()
			->initProblemsFields()
			->initOverridesFields();
	}

	private function initDataSetFields(): self {
		return $this->addField(
			(new CWidgetFieldGraphDataSet('ds', _('Data set')))->setFlags(CWidgetField::FLAG_NOT_EMPTY)
		);
	}

	private function initDisplayingOptionsFields(): self {
		return $this
			->addField(
				(new CWidgetFieldRadioButtonList('source', _('History data selection'), [
					SVG_GRAPH_DATA_SOURCE_AUTO => _x('Auto', 'history source selection method'),
					SVG_GRAPH_DATA_SOURCE_HISTORY => _('History'),
					SVG_GRAPH_DATA_SOURCE_TRENDS => _('Trends')
				]))->setDefault(SVG_GRAPH_DATA_SOURCE_AUTO)
			)
			->addField(
				new CWidgetFieldCheckBox('simple_triggers', _('Simple triggers'))
			)
			->addField(
				new CWidgetFieldCheckBox('working_time', _('Working time'))
			)
			->addField(
				new CWidgetFieldCheckBox('percentile_left', _('Percentile line (left)'))
			)
			->addField(
				(new CWidgetFieldTextBox('percentile_left_value', null))
					->setFlags(!$this->percentile_left_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				new CWidgetFieldCheckBox('percentile_right', _('Percentile line (right)'))
			)
			->addField(
				(new CWidgetFieldTextBox('percentile_right_value', null))
					->setFlags(!$this->percentile_right_on ? CWidgetField::FLAG_DISABLED : 0x00)
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

	private function initAxesFields(): self {
		return $this
			->addField(
				(new CWidgetFieldCheckBox('lefty', _('Left Y'), _('Show')))->setDefault(SVG_GRAPH_AXIS_ON)
			)
			->addField(
				(new CWidgetFieldNumericBox('lefty_min', _('Min')))
					->setFullName(_('Left Y').'/'._('Min'))
					->setFlags(!$this->lefty_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldNumericBox('lefty_max', _('Max')))
					->setFullName(_('Left Y').'/'._('Max'))
					->setFlags(!$this->lefty_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldSelect('lefty_units', _('Units'), [
					SVG_GRAPH_AXIS_UNITS_AUTO => _x('Auto', 'history source selection method'),
					SVG_GRAPH_AXIS_UNITS_STATIC => _x('Static', 'history source selection method')
				]))
					->setDefault(SVG_GRAPH_AXIS_UNITS_AUTO)
					->setFlags(!$this->lefty_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldTextBox('lefty_static_units'))
					->setFlags(!$this->lefty_on || !$this->lefty_units_static ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldCheckBox('righty', _('Right Y'), _('Show')))->setDefault(SVG_GRAPH_AXIS_ON)
			)
			->addField(
				(new CWidgetFieldNumericBox('righty_min', _('Min')))
					->setFullName(_('Right Y').'/'._('Min'))
					->setFlags(!$this->righty_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldNumericBox('righty_max', _('Max')))
					->setFullName(_('Right Y').'/'._('Max'))
					->setFlags(!$this->righty_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldSelect('righty_units', _('Units'), [
					SVG_GRAPH_AXIS_UNITS_AUTO => _x('Auto', 'history source selection method'),
					SVG_GRAPH_AXIS_UNITS_STATIC => _x('Static', 'history source selection method')
				]))
					->setDefault(SVG_GRAPH_AXIS_UNITS_AUTO)
					->setFlags(!$this->righty_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldTextBox('righty_static_units', null))
					->setFlags(!$this->righty_on || !$this->righty_units_static ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldCheckBox('axisx', _('X-Axis'), _('Show')))->setDefault(SVG_GRAPH_AXIS_ON)
			);
	}

	private function initLegendFields(): self {
		return $this
			->addField(
				(new CWidgetFieldCheckBox('legend', _('Show legend')))->setDefault(SVG_GRAPH_LEGEND_ON)
			)
			->addField(
				(new CWidgetFieldCheckBox('legend_statistic', _('Display min/max/avg')))
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldRangeControl('legend_lines', _('Number of rows'),
					SVG_GRAPH_LEGEND_LINES_MIN, SVG_GRAPH_LEGEND_LINES_MAX
				))
					->setDefault(SVG_GRAPH_LEGEND_LINES_MIN)
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldRangeControl('legend_columns', _('Number of columns'),
					SVG_GRAPH_LEGEND_COLUMNS_MIN, SVG_GRAPH_LEGEND_COLUMNS_MAX
				))
					->setDefault(SVG_GRAPH_LEGEND_COLUMNS_MAX)
					->setFlags(!$this->legend_on || $this->legend_statistic_on ? CWidgetField::FLAG_DISABLED : 0x00)
			);
	}

	private function initProblemsFields(): self {
		return $this
			->addField(
				new CWidgetFieldCheckBox('show_problems', _('Show problems'))
			)
			->addField(
				(new CWidgetFieldCheckBox('graph_item_problems', _('Selected items only')))
					->setDefault(SVG_GRAPH_SELECTED_ITEM_PROBLEMS)
					->setFlags(!$this->problems_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldHostPatternSelect('problemhosts', _('Problem hosts')))
					->setFlags(!$this->problems_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldSeverities('severities', _('Severity')))
					->setFlags(!$this->problems_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldTextBox('problem_name', _('Problem')))
					->setFlags(!$this->problems_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('evaltype', _('Tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))
					->setDefault(TAG_EVAL_TYPE_AND_OR)
					->setFlags(!$this->problems_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldTags('tags'))->setFlags(!$this->problems_on ? CWidgetField::FLAG_DISABLED : 0x00)
			);
	}

	private function initOverridesFields(): self {
		return $this->addField(
			(new CWidgetFieldGraphOverride('or', _('Overrides')))->setFlags(CWidgetField::FLAG_NOT_EMPTY)
		);
	}

	/**
	 * Check if widget configuration is set to use overridden time.
	 */
	public static function hasOverrideTime(array $fields_values): bool {
		return array_key_exists('graph_time', $fields_values)
			&& $fields_values['graph_time'] == SVG_GRAPH_CUSTOM_TIME_ON;
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
