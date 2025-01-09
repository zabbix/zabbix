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


namespace Widgets\SvgGraph\Includes;

use CNumberParser,
	CParser,
	CWidgetsData;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldPatternSelectHost,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldNumericBox,
	CWidgetFieldRadioButtonList,
	CWidgetFieldRangeControl,
	CWidgetFieldSelect,
	CWidgetFieldSeverities,
	CWidgetFieldTags,
	CWidgetFieldTextBox,
	CWidgetFieldTimePeriod
};

/**
 * Graph widget form view.
 */
class WidgetForm extends CWidgetForm {

	public const LEGEND_ON = 1;
	public const LEGEND_STATISTIC_ON = 1;
	public const LEGEND_AGGREGATION_ON = 1;

	public const LEGEND_LINES_MODE_FIXED = 0;
	public const LEGEND_LINES_MODE_VARIABLE = 1;

	public const LEGEND_LINES_MIN = 1;
	public const LEGEND_LINES_MAX = 10;

	public const LEGEND_COLUMNS_MIN = 1;
	public const LEGEND_COLUMNS_MAX = 4;

	public const PERCENTILE_MIN = 1;
	public const PERCENTILE_MAX = 100;

	private bool $percentile_left_on = false;
	private bool $percentile_right_on = false;

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

		// Validate Min/Max values in Axes tab.
		if ($this->getFieldValue('lefty') == SVG_GRAPH_AXIS_ON) {
			$lefty_min = $number_parser_w_suffix->parse($this->getFieldValue('lefty_min')) == CParser::PARSE_SUCCESS
				? $number_parser_w_suffix->calcValue()
				: null;

			$lefty_max = $number_parser_w_suffix->parse($this->getFieldValue('lefty_max')) == CParser::PARSE_SUCCESS
				? $number_parser_w_suffix->calcValue()
				: null;

			if ($lefty_min !== null && $lefty_max !== null && $lefty_min >= $lefty_max) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Left Y').': '._('Max'),
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
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Right Y').': '._('Max'),
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
			$this->legend_on = $values['legend'] == self::LEGEND_ON;
		}

		if (array_key_exists('legend_statistic', $values)) {
			$this->legend_statistic_on = $values['legend_statistic'] == self::LEGEND_STATISTIC_ON;
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
			->initOverridesFields()
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			);
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
				(new CWidgetFieldTimePeriod('time_period', _('Time period')))
					->setDefault([
						CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
							CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
						)
					])
					->setDefaultPeriod(['from' => 'now-1h', 'to' => 'now'])
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			);
	}

	private function initAxesFields(): self {
		return $this
			->addField(
				(new CWidgetFieldCheckBox('lefty', _('Left Y'), _('Show')))->setDefault(SVG_GRAPH_AXIS_ON)
			)
			->addField(
				(new CWidgetFieldSelect('lefty_scale', _('Scale'), [
					SVG_GRAPH_AXIS_SCALE_LINEAR => _('Linear'),
					SVG_GRAPH_AXIS_SCALE_LOGARITHMIC => _('Logarithmic')
				]))->setDefault(SVG_GRAPH_AXIS_SCALE_LINEAR)
			)
			->addField(
				(new CWidgetFieldNumericBox('lefty_min', _('Min')))
					->prefixLabel(_('Left Y'))
					->setFlags(!$this->lefty_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldNumericBox('lefty_max', _('Max')))
					->prefixLabel(_('Left Y'))
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
					->setMaxLength(255)
			)
			->addField(
				(new CWidgetFieldCheckBox('righty', _('Right Y'), _('Show')))->setDefault(SVG_GRAPH_AXIS_ON)
			)
			->addField(
				(new CWidgetFieldSelect('righty_scale', _('Scale'), [
					SVG_GRAPH_AXIS_SCALE_LINEAR => _('Linear'),
					SVG_GRAPH_AXIS_SCALE_LOGARITHMIC => _('Logarithmic')
				]))->setDefault(SVG_GRAPH_AXIS_SCALE_LINEAR)
			)
			->addField(
				(new CWidgetFieldNumericBox('righty_min', _('Min')))
					->prefixLabel(_('Right Y'))
					->setFlags(!$this->righty_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldNumericBox('righty_max', _('Max')))
					->prefixLabel(_('Right Y'))
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
					->setMaxLength(255)
			)
			->addField(
				(new CWidgetFieldCheckBox('axisx', _('X-Axis'), _('Show')))->setDefault(SVG_GRAPH_AXIS_ON)
			);
	}

	private function initLegendFields(): self {
		return $this
			->addField(
				(new CWidgetFieldCheckBox('legend', _('Show legend')))->setDefault(self::LEGEND_ON)
			)
			->addField(
				(new CWidgetFieldCheckBox('legend_statistic', _('Display min/avg/max')))
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldCheckBox('legend_aggregation', _('Show aggregation function')))
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('legend_lines_mode', _('Rows'), [
					self::LEGEND_LINES_MODE_FIXED => _('Fixed'),
					self::LEGEND_LINES_MODE_VARIABLE => _('Variable')
				]))
					->setDefault(self::LEGEND_LINES_MODE_FIXED)
					->setFlags(!$this->legend_on ? CWidgetField::FLAG_DISABLED : 0x00)
			)
			->addField(
				(new CWidgetFieldRangeControl('legend_lines',  _('Number of rows'),
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
			->addField($this->isTemplateDashboard()
				? null
				: (new CWidgetFieldPatternSelectHost('problemhosts', _('Problem hosts')))
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
				(new CWidgetFieldRadioButtonList('evaltype', _('Problem tags'), [
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
			(new CWidgetFieldOverride('or', _('Overrides')))->setFlags(CWidgetField::FLAG_NOT_EMPTY)
		);
	}
}
