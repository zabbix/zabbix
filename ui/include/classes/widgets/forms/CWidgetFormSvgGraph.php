<?php
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


class CWidgetFormSvgGraph extends CWidgetForm {

	private const WIDGET_ITEM_PERCENTILE_MIN = 1;
	private const WIDGET_ITEM_PERCENTILE_MAX = 100;

	public function __construct($data, $templateid) {
		parent::__construct($data, $templateid, WIDGET_SVG_GRAPH);

		$this->data = self::convertDottedKeys($this->data);

		$this->initDataSetFields();
		$this->initDisplayingOptionsFields();
		$this->initTimePeriodFields();
		$this->initAxesFields();
		$this->initLegendFields();
		$this->initProblemsFields();
		$this->initOverridesFields();
	}

	/**
	 * Validate form fields.
	 *
	 * @param bool $strict  Enables more strict validation of the form fields.
	 *                      Must be enabled for validation of input parameters in the widget configuration form.
	 *
	 * @throws Exception
	 *
	 * @return array
	 */
	public function validate($strict = false): array {
		$errors = parent::validate($strict);

		$number_parser_w_suffix = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);
		$number_parser_wo_suffix = new CNumberParser();

		// Percentiles
		if ($this->fields['percentile_left']->getValue() == SVG_GRAPH_PERCENTILE_LEFT_ON) {
			$percentile_left_value = $this->fields['percentile_left_value']->getValue();

			if ($percentile_left_value !== '') {
				$percentile_left_value_calculated =
						$number_parser_wo_suffix->parse($percentile_left_value) == CParser::PARSE_SUCCESS
					? $number_parser_wo_suffix->calcValue()
					: null;

				if ($percentile_left_value_calculated === null
						|| $percentile_left_value_calculated < self::WIDGET_ITEM_PERCENTILE_MIN
						|| $percentile_left_value_calculated > self::WIDGET_ITEM_PERCENTILE_MAX) {
					$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Percentile line (left)'),
						_s('value must be between "%1$s" and "%2$s"', self::WIDGET_ITEM_PERCENTILE_MIN,
							self::WIDGET_ITEM_PERCENTILE_MAX
						)
					);
				}
			}
		}

		if ($this->fields['percentile_right']->getValue() == SVG_GRAPH_PERCENTILE_RIGHT_ON) {
			$percentile_right_value = $this->fields['percentile_right_value']->getValue();

			if ($percentile_right_value !== '') {
				$percentile_right_value_calculated =
						$number_parser_wo_suffix->parse($percentile_right_value) == CParser::PARSE_SUCCESS
					? $number_parser_wo_suffix->calcValue()
					: null;

				if ($percentile_right_value_calculated === null
						|| $percentile_right_value_calculated < self::WIDGET_ITEM_PERCENTILE_MIN
						|| $percentile_right_value_calculated > self::WIDGET_ITEM_PERCENTILE_MAX) {
					$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Percentile line (right)'),
						_s('value must be between "%1$s" and "%2$s"', self::WIDGET_ITEM_PERCENTILE_MIN,
							self::WIDGET_ITEM_PERCENTILE_MAX
						)
					);
				}
			}
		}

		// Test graph custom time period.
		if ($this->fields['graph_time']->getValue() == SVG_GRAPH_CUSTOM_TIME) {
			$errors = array_merge($errors, self::validateTimeSelectorPeriod($this->fields['time_from']->getValue(),
				$this->fields['time_to']->getValue()
			));
		}

		// Validate Min/Max values in Axes tab.
		if ($this->fields['lefty']->getValue() == SVG_GRAPH_AXIS_SHOW) {
			$lefty_min =
					$number_parser_w_suffix->parse($this->fields['lefty_min']->getValue()) == CParser::PARSE_SUCCESS
				? $number_parser_w_suffix->calcValue()
				: '';

			$lefty_max =
					$number_parser_w_suffix->parse($this->fields['lefty_max']->getValue()) == CParser::PARSE_SUCCESS
				? $number_parser_w_suffix->calcValue()
				: '';

			if ($lefty_min !== '' && $lefty_max !== '' && $lefty_min >= $lefty_max) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Left Y').'/'._('Max'),
					_('Y axis MAX value must be greater than Y axis MIN value')
				);
			}
		}

		if ($this->fields['righty']->getValue() == SVG_GRAPH_AXIS_SHOW) {
			$righty_min =
					$number_parser_w_suffix->parse($this->fields['righty_min']->getValue()) == CParser::PARSE_SUCCESS
				? $number_parser_w_suffix->calcValue()
				: '';

			$righty_max =
					$number_parser_w_suffix->parse($this->fields['righty_max']->getValue()) == CParser::PARSE_SUCCESS
				? $number_parser_w_suffix->calcValue()
				: '';

			if ($righty_min !== '' && $righty_max !== '' && $righty_min >= $righty_max) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Right Y').'/'._('Max'),
					_('Y axis MAX value must be greater than Y axis MIN value')
				);
			}
		}

		return $errors;
	}

	/**
	 * Check if widget configuration is set to use overridden time.
	 *
	 * @param array $fields  Widget configuration fields.
	 *
	 * @return bool
	 */
	public static function hasOverrideTime(array $fields): bool {
		return array_key_exists('graph_time', $fields) && $fields['graph_time'] == SVG_GRAPH_CUSTOM_TIME;
	}

	private function initDataSetFields(): void {
		$field_ds = (new CWidgetFieldGraphDataSet('ds', _('Data set')))->setFlags(CWidgetField::FLAG_NOT_EMPTY);

		if (array_key_exists('ds', $this->data)) {
			$field_ds->setValue($this->data['ds']);
		}

		$this->fields[$field_ds->getName()] = $field_ds;
	}

	private function initDisplayingOptionsFields(): void {
		// History data selection.
		$field_data_source = (new CWidgetFieldRadioButtonList('source', _('History data selection'), [
			SVG_GRAPH_DATA_SOURCE_AUTO => _x('Auto', 'history source selection method'),
			SVG_GRAPH_DATA_SOURCE_HISTORY => _('History'),
			SVG_GRAPH_DATA_SOURCE_TRENDS => _('Trends')
		]))
			->setDefault(SVG_GRAPH_DATA_SOURCE_AUTO)
			->setModern(true);

		if (array_key_exists('source', $this->data)) {
			$field_data_source->setValue($this->data['source']);
		}

		$this->fields[$field_data_source->getName()] = $field_data_source;

		// Simple triggers.
		$field_simple_triggers = new CWidgetFieldCheckBox('simple_triggers', _('Simple triggers'));

		if (array_key_exists('simple_triggers', $this->data)) {
			$field_simple_triggers->setValue($this->data['simple_triggers']);
		}

		$this->fields[$field_simple_triggers->getName()] = $field_simple_triggers;

		// Working time.
		$field_working_time = new CWidgetFieldCheckBox('working_time', _('Working time'));

		if (array_key_exists('working_time', $this->data)) {
			$field_working_time->setValue($this->data['working_time']);
		}

		$this->fields[$field_working_time->getName()] = $field_working_time;

		// Percentile line left.
		$field_percentile_left = new CWidgetFieldCheckBox('percentile_left', _('Percentile line (left)'));

		if (array_key_exists('percentile_left', $this->data)) {
			$field_percentile_left->setValue($this->data['percentile_left']);
		}

		$this->fields[$field_percentile_left->getName()] = $field_percentile_left;

		// Percentile line left value.
		$field_percentile_left_value = (new CWidgetFieldTextBox('percentile_left_value', null))
			->setPlaceholder(_('value'))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH);

		if ($field_percentile_left->getValue() != SVG_GRAPH_PERCENTILE_LEFT_ON) {
			$field_percentile_left_value->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('percentile_left_value', $this->data)) {
			$field_percentile_left_value->setValue($this->data['percentile_left_value']);
		}

		$this->fields[$field_percentile_left_value->getName()] = $field_percentile_left_value;

		// Percentile line right.
		$field_percentile_right = new CWidgetFieldCheckBox('percentile_right', _('Percentile line (right)'));

		if (array_key_exists('percentile_right', $this->data)) {
			$field_percentile_right->setValue($this->data['percentile_right']);
		}

		$this->fields[$field_percentile_right->getName()] = $field_percentile_right;

		// Percentile line right value.
		$field_percentile_right_value = (new CWidgetFieldTextBox('percentile_right_value', null))
			->setPlaceholder(_('value'))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH);

		if ($field_percentile_right->getValue() != SVG_GRAPH_PERCENTILE_RIGHT_ON) {
			$field_percentile_right_value->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('percentile_right_value', $this->data)) {
			$field_percentile_right_value->setValue($this->data['percentile_right_value']);
		}

		$this->fields[$field_percentile_right_value->getName()] = $field_percentile_right_value;
	}

	private function initTimePeriodFields(): void {
		// Checkbox to specify either relative dashboard time or widget's own time.
		$field_graph_time = new CWidgetFieldCheckBox('graph_time', _('Set custom time period'));

		if (array_key_exists('graph_time', $this->data)) {
			$field_graph_time->setValue($this->data['graph_time']);
		}

		$this->fields[$field_graph_time->getName()] = $field_graph_time;

		// Date from.
		$field_time_from = (new CWidgetFieldDatePicker('time_from', _('From'), false))
			->setDefault('now-1h')
			->setFlags(CWidgetField::FLAG_NOT_EMPTY);

		if ($field_graph_time->getValue() != SVG_GRAPH_CUSTOM_TIME) {
			$field_time_from->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('time_from', $this->data)) {
			$field_time_from->setValue($this->data['time_from']);
		}

		$this->fields[$field_time_from->getName()] = $field_time_from;

		// Time to.
		$field_time_to = (new CWidgetFieldDatePicker('time_to', _('To'), false))
			->setDefault('now')
			->setFlags(CWidgetField::FLAG_NOT_EMPTY);

		if ($field_graph_time->getValue() != SVG_GRAPH_CUSTOM_TIME) {
			$field_time_to->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('time_to', $this->data)) {
			$field_time_to->setValue($this->data['time_to']);
		}

		$this->fields[$field_time_to->getName()] = $field_time_to;
	}

	private function initAxesFields(): void {
		// Show left Y axis.
		$field_lefty = (new CWidgetFieldCheckBox('lefty', _('Left Y'), _('Show')))->setDefault(SVG_GRAPH_AXIS_SHOW);

		if (array_key_exists('lefty', $this->data)) {
			$field_lefty->setValue($this->data['lefty']);
		}

		$this->fields[$field_lefty->getName()] = $field_lefty;

		// Min value on left Y axis.
		$field_lefty_min = (new CWidgetFieldNumericBox('lefty_min', _('Min')))
			->setPlaceholder(_('calculated'))
			->setFullName(_('Left Y').'/'._('Min'))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);

		if ($field_lefty->getValue() != SVG_GRAPH_AXIS_SHOW) {
			$field_lefty_min->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('lefty_min', $this->data)) {
			$field_lefty_min->setValue($this->data['lefty_min']);
		}

		$this->fields[$field_lefty_min->getName()] = $field_lefty_min;

		// Max value on left Y axis.
		$field_lefty_max = (new CWidgetFieldNumericBox('lefty_max', _('Max')))
			->setPlaceholder(_('calculated'))
			->setFullName(_('Left Y').'/'._('Max'))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);

		if ($field_lefty->getValue() != SVG_GRAPH_AXIS_SHOW) {
			$field_lefty_max->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('lefty_max', $this->data)) {
			$field_lefty_max->setValue($this->data['lefty_max']);
		}

		$this->fields[$field_lefty_max->getName()] = $field_lefty_max;

		// Specify the type of units on left Y axis.
		$field_lefty_units = (new CWidgetFieldSelect('lefty_units', _('Units'), [
			SVG_GRAPH_AXIS_UNITS_AUTO => _x('Auto', 'history source selection method'),
			SVG_GRAPH_AXIS_UNITS_STATIC => _x('Static', 'history source selection method')
		]))->setDefault(SVG_GRAPH_AXIS_UNITS_AUTO);

		if ($field_lefty->getValue() != SVG_GRAPH_AXIS_SHOW) {
			$field_lefty_units->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('lefty_units', $this->data)) {
			$field_lefty_units->setValue($this->data['lefty_units']);
		}

		$this->fields[$field_lefty_units->getName()] = $field_lefty_units;

		// Static units on left Y axis.
		$field_lefty_static_units = (new CWidgetFieldTextBox('lefty_static_units', null))
			->setPlaceholder(_('value'))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH);

		if ($field_lefty->getValue() != SVG_GRAPH_AXIS_SHOW
				|| $field_lefty_units->getValue() != SVG_GRAPH_AXIS_UNITS_STATIC) {
			$field_lefty_static_units->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('lefty_static_units', $this->data)) {
			$field_lefty_static_units->setValue($this->data['lefty_static_units']);
		}

		$this->fields[$field_lefty_static_units->getName()] = $field_lefty_static_units;

		// Show right Y axis.
		$field_righty = (new CWidgetFieldCheckBox('righty', _('Right Y'), _('Show')))->setDefault(SVG_GRAPH_AXIS_SHOW);

		if (array_key_exists('righty', $this->data)) {
			$field_righty->setValue($this->data['righty']);
		}

		$this->fields[$field_righty->getName()] = $field_righty;

		// Min value on right Y axis.
		$field_righty_min = (new CWidgetFieldNumericBox('righty_min', _('Min')))
			->setPlaceholder(_('calculated'))
			->setFullName(_('Right Y').'/'._('Min'))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);

		if ($field_righty->getValue() != SVG_GRAPH_AXIS_SHOW) {
			$field_righty_min->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('righty_min', $this->data)) {
			$field_righty_min->setValue($this->data['righty_min']);
		}

		$this->fields[$field_righty_min->getName()] = $field_righty_min;

		// Max value on right Y axis.
		$field_righty_max = (new CWidgetFieldNumericBox('righty_max', _('Max')))
			->setPlaceholder(_('calculated'))
			->setFullName(_('Right Y').'/'._('Max'))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);

		if ($field_righty->getValue() != SVG_GRAPH_AXIS_SHOW) {
			$field_righty_max->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('righty_max', $this->data)) {
			$field_righty_max->setValue($this->data['righty_max']);
		}

		$this->fields[$field_righty_max->getName()] = $field_righty_max;

		// Specify the type of units on right Y axis.
		$field_righty_units = (new CWidgetFieldSelect('righty_units', _('Units'), [
			SVG_GRAPH_AXIS_UNITS_AUTO => _x('Auto', 'history source selection method'),
			SVG_GRAPH_AXIS_UNITS_STATIC => _x('Static', 'history source selection method')
		]))->setDefault(SVG_GRAPH_AXIS_UNITS_AUTO);

		if ($field_righty->getValue() != SVG_GRAPH_AXIS_SHOW) {
			$field_righty_units->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('righty_units', $this->data)) {
			$field_righty_units->setValue($this->data['righty_units']);
		}

		$this->fields[$field_righty_units->getName()] = $field_righty_units;

		// Static units on right Y axis.
		$field_righty_static_units = (new CWidgetFieldTextBox('righty_static_units', null))
			->setPlaceholder(_('value'))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH);

		if ($field_righty->getValue() != SVG_GRAPH_AXIS_SHOW
				|| $field_righty_units->getValue() != SVG_GRAPH_AXIS_UNITS_STATIC) {
			$field_righty_static_units->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('righty_static_units', $this->data)) {
			$field_righty_static_units->setValue($this->data['righty_static_units']);
		}

		$this->fields[$field_righty_static_units->getName()] = $field_righty_static_units;

		// Show X axis.
		$field_axisx = (new CWidgetFieldCheckBox('axisx', _('X-Axis'), _('Show')))->setDefault(SVG_GRAPH_AXIS_SHOW);

		if (array_key_exists('axisx', $this->data)) {
			$field_axisx->setValue($this->data['axisx']);
		}

		$this->fields[$field_axisx->getName()] = $field_axisx;
	}

	private function initLegendFields(): void {
		/**
		 * Legend tab.
		 *
		 * Contains check-box field to show/hide legend and field to specify number of lines in which legend is shown.
		 */

		$field_legend = (new CWidgetFieldCheckBox('legend', _('Show legend')))->setDefault(SVG_GRAPH_LEGEND_ON);

		if (array_key_exists('legend', $this->data)) {
			$field_legend->setValue($this->data['legend']);
		}

		$this->fields[$field_legend->getName()] = $field_legend;

		// Show legend statistic.
		$field_legend_statistic = (new CWidgetFieldCheckBox('legend_statistic', _('Display min/max/avg')))
			->setDefault(SVG_GRAPH_LEGEND_STATISTIC_OFF);

		if ($field_legend->getValue() == SVG_GRAPH_LEGEND_OFF) {
			$field_legend_statistic->setFlags(CWidgetField::FLAG_DISABLED);
		}
		if (array_key_exists('legend_statistic', $this->data)) {
			$field_legend_statistic->setValue($this->data['legend_statistic']);
		}

		$this->fields[$field_legend_statistic->getName()] = $field_legend_statistic;

		// Number of lines.
		$field_legend_lines = (new CWidgetFieldRangeControl('legend_lines', _('Number of rows'),
			SVG_GRAPH_LEGEND_LINES_MIN, SVG_GRAPH_LEGEND_LINES_MAX
		))->setDefault(SVG_GRAPH_LEGEND_LINES_MIN);

		if ($field_legend->getValue() == SVG_GRAPH_LEGEND_OFF) {
			$field_legend_lines->setFlags(CWidgetField::FLAG_DISABLED);
		}
		if (array_key_exists('legend_lines', $this->data)) {
			$field_legend_lines->setValue($this->data['legend_lines']);
		}

		$this->fields[$field_legend_lines->getName()] = $field_legend_lines;

		// Number of columns.
		$field_legend_columns = (new CWidgetFieldRangeControl('legend_columns', _('Number of columns'),
			SVG_GRAPH_LEGEND_COLUMNS_MIN, SVG_GRAPH_LEGEND_COLUMNS_MAX
		))->setDefault(SVG_GRAPH_LEGEND_COLUMNS_MAX);

		if ($field_legend_statistic->getValue() == SVG_GRAPH_LEGEND_STATISTIC_ON) {
			$field_legend_columns->setFlags(CWidgetField::FLAG_DISABLED);
		}
		if (array_key_exists('legend_columns', $this->data)) {
			$field_legend_columns->setValue($this->data['legend_columns']);
		}

		$this->fields[$field_legend_columns->getName()] = $field_legend_columns;
	}

	private function initProblemsFields(): void {
		// Checkbox: Selected items only.
		$field_show_problems = new CWidgetFieldCheckBox('show_problems', _('Show problems'));

		if (array_key_exists('show_problems', $this->data)) {
			$field_show_problems->setValue($this->data['show_problems']);
		}

		$this->fields[$field_show_problems->getName()] = $field_show_problems;

		// Checkbox: Selected items only.
		$field_problems = (new CWidgetFieldCheckBox('graph_item_problems', _('Selected items only')))
			->setDefault(SVG_GRAPH_SELECTED_ITEM_PROBLEMS);

		if ($field_show_problems->getValue() != SVG_GRAPH_PROBLEMS_SHOW) {
			$field_problems->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('graph_item_problems', $this->data)) {
			$field_problems->setValue($this->data['graph_item_problems']);
		}

		$this->fields[$field_problems->getName()] = $field_problems;

		// Problem hosts.
		$field_problemhosts = (new CWidgetFieldHostPatternSelect('problemhosts', _('Problem hosts')))
			->setPlaceholder(_('host pattern'));

		if ($field_show_problems->getValue() != SVG_GRAPH_PROBLEMS_SHOW) {
			$field_problemhosts->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('problemhosts', $this->data)) {
			$field_problemhosts->setValue($this->data['problemhosts']);
		}

		$this->fields[$field_problemhosts->getName()] = $field_problemhosts;

		// Severity checkboxes list.
		$field_severities = new CWidgetFieldSeverities('severities', _('Severity'));

		if ($field_show_problems->getValue() != SVG_GRAPH_PROBLEMS_SHOW) {
			$field_severities->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('severities', $this->data)) {
			$field_severities->setValue($this->data['severities']);
		}

		$this->fields[$field_severities->getName()] = $field_severities;

		// Problem name input-text field.
		$field_problem_name = (new CWidgetFieldTextBox('problem_name', _('Problem')))
			->setPlaceholder(_('problem pattern'));

		if ($field_show_problems->getValue() != SVG_GRAPH_PROBLEMS_SHOW) {
			$field_problem_name->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('problem_name', $this->data)) {
			$field_problem_name->setValue($this->data['problem_name']);
		}

		$this->fields[$field_problem_name->getName()] = $field_problem_name;

		// Problem tag evaltype (And/Or).
		$field_evaltype = (new CWidgetFieldRadioButtonList('evaltype', _('Tags'), [
			TAG_EVAL_TYPE_AND_OR => _('And/Or'),
			TAG_EVAL_TYPE_OR => _('Or')
		]))
			->setDefault(TAG_EVAL_TYPE_AND_OR)
			->setModern(true);

		if ($field_show_problems->getValue() != SVG_GRAPH_PROBLEMS_SHOW) {
			$field_evaltype->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('evaltype', $this->data)) {
			$field_evaltype->setValue($this->data['evaltype']);
		}

		$this->fields[$field_evaltype->getName()] = $field_evaltype;

		// Problem tags field.
		$field_tags = new CWidgetFieldTags('tags', '');

		if ($field_show_problems->getValue() != SVG_GRAPH_PROBLEMS_SHOW) {
			$field_tags->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('tags', $this->data)) {
			$field_tags->setValue($this->data['tags']);
		}

		$this->fields[$field_tags->getName()] = $field_tags;
	}

	private function initOverridesFields(): void {
		$field_or = (new CWidgetFieldGraphOverride('or', _('Overrides')))->setFlags(CWidgetField::FLAG_NOT_EMPTY);

		if (array_key_exists('or', $this->data)) {
			$field_or->setValue($this->data['or']);
		}

		$this->fields[$field_or->getName()] = $field_or;
	}

	/**
	 * Validate "from" and "to" parameters for allowed period.
	 *
	 * @param string $from
	 * @param string $to
	 *
	 * @return array
	 */
	private static function validateTimeSelectorPeriod(string $from, string $to): array {
		$errors = [];
		$ts = [];
		$ts['now'] = time();
		$range_time_parser = new CRangeTimeParser();

		foreach (['from' => $from, 'to' => $to] as $field => $value) {
			$range_time_parser->parse($value);
			$ts[$field] = $range_time_parser
				->getDateTime($field === 'from')
				->getTimestamp();
		}

		$period = $ts['to'] - $ts['from'] + 1;
		$range_time_parser->parse('now-'.CSettingsHelper::get(CSettingsHelper::MAX_PERIOD));
		$max_period = 1 + $ts['now'] - $range_time_parser
			->getDateTime(true)
			->getTimestamp();

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
