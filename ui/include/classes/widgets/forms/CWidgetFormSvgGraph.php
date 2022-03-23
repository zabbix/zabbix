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

	public function __construct($data, $templateid) {
		parent::__construct($data, $templateid, WIDGET_SVG_GRAPH);

		$this->data = self::convertDottedKeys($this->data);

		/**
		 * Data set tab.
		 *
		 * Contains single CWidgetFieldGraphDataSet field for data sets definition and configuration.
		 */
		$field_ds = (new CWidgetFieldGraphDataSet('ds', _('Data set')))->setFlags(CWidgetField::FLAG_NOT_EMPTY);

		if (array_key_exists('ds', $this->data)) {
			$field_ds->setValue($this->data['ds']);
		}

		$this->fields[$field_ds->getName()] = $field_ds;

		/**
		 * Display options tab.
		 *
		 * Used to select either data are loaded from History or Trends or turning automatic mode on.
		 */
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

		/**
		 * Time period tab.
		 *
		 * Contains fields for specifying widget time options.
		 */
		// Checkbox to specify either relative dashboard time or widget's own time.
		$field_graph_time = (new CWidgetFieldCheckBox('graph_time', _('Set custom time period')))
			->setAction('jQuery("#time_from, #time_to, #time_from_calendar, #time_to_calendar")'.
				'.prop("disabled", !jQuery(this).is(":checked"));'
			);

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

		/**
		 * Axes tab.
		 *
		 * Contains fields to specify options for graph axes.
		 */
		// Show left Y axis.
		$field_lefty = (new CWidgetFieldCheckBox('lefty', _('Left Y'), _('Show')))
			->setDefault(SVG_GRAPH_AXIS_SHOW)
			->setAction('onLeftYChange()');

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
		]))
			->setDefault(SVG_GRAPH_AXIS_UNITS_AUTO);

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
		$field_righty = (new CWidgetFieldCheckBox('righty', _('Right Y'), _('Show')))
			->setDefault(SVG_GRAPH_AXIS_SHOW)
			->setAction('onRightYChange()');

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
		]))
			->setDefault(SVG_GRAPH_AXIS_UNITS_AUTO);

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

		/**
		 * Legend tab.
		 *
		 * Contains check-box field to show/hide legend and field to specify number of lines in which legend is shown.
		 */
		// Show legend.
		$field_legend = (new CWidgetFieldCheckBox('legend', _('Show legend')))
			->setAction('jQuery("[name=legend_lines]").rangeControl('.
				'jQuery(this).is(":checked") ? "enable" : "disable"'.
			');')
			->setDefault(SVG_GRAPH_LEGEND_TYPE_SHORT);

		if (array_key_exists('legend', $this->data)) {
			$field_legend->setValue($this->data['legend']);
		}

		$this->fields[$field_legend->getName()] = $field_legend;

		// Number of lines.
		$field_legend_lines = (new CWidgetFieldRangeControl('legend_lines', _('Number of rows'),
			SVG_GRAPH_LEGEND_LINES_MIN, SVG_GRAPH_LEGEND_LINES_MAX
		))
			->setDefault(SVG_GRAPH_LEGEND_LINES_MIN);

		if ($field_legend->getValue() == SVG_GRAPH_LEGEND_TYPE_NONE) {
			$field_legend_lines->setFlags(CWidgetField::FLAG_DISABLED);
		}
		if (array_key_exists('legend_lines', $this->data)) {
			$field_legend_lines->setValue($this->data['legend_lines']);
		}

		$this->fields[$field_legend_lines->getName()] = $field_legend_lines;

		/**
		 * Problems tab.
		 *
		 * Contains fields to configure highlighted problem areas in graph.
		 */
		// Checkbox: Selected items only.
		$field_show_problems = (new CWidgetFieldCheckBox('show_problems', _('Show problems')))
			->setAction(
				'var on = jQuery(this).is(":checked"),'.
					'widget = jQuery(this).closest(".ui-widget");'.
				'jQuery("#graph_item_problems, #problem_name, #problemhosts_select")'.
					'.prop("disabled", !on);'.
				'jQuery("#problemhosts_").multiSelect(on ? "enable" : "disable");'.
				'jQuery("[name^=\"severities[\"]", widget).prop("disabled", !on);'.
				'jQuery("[name=\"evaltype\"]", widget).prop("disabled", !on);'.
				'jQuery("input, button, z-select", jQuery("#tags_table_tags", widget)).prop("disabled", !on);'
			);

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

		/**
		 * Overrides tab.
		 *
		 * Contains single field for override configuration.
		 */
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
	private static function validateTimeSelectorPeriod($from, $to) {
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

	/**
	 * Validate form fields.
	 *
	 * @param bool $strict  Enables more strict validation of the form fields.
	 *                      Must be enabled for validation of input parameters in the widget configuration form.
	 *
	 * @return bool
	 */
	public function validate($strict = false) {
		$errors = parent::validate($strict);

		// Test graph custom time period.
		if ($this->fields['graph_time']->getValue() == SVG_GRAPH_CUSTOM_TIME) {
			$errors = array_merge($errors, self::validateTimeSelectorPeriod($this->fields['time_from']->getValue(),
				$this->fields['time_to']->getValue()
			));
		}

		$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

		// Validate Min/Max values in Axes tab.
		if ($this->fields['lefty']->getValue() == SVG_GRAPH_AXIS_SHOW) {
			$lefty_min = $number_parser->parse($this->fields['lefty_min']->getValue()) == CParser::PARSE_SUCCESS
				? $number_parser->calcValue()
				: '';

			$lefty_max = $number_parser->parse($this->fields['lefty_max']->getValue()) == CParser::PARSE_SUCCESS
				? $number_parser->calcValue()
				: '';

			if ($lefty_min !== '' && $lefty_max !== '' && $lefty_min >= $lefty_max) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Left Y').'/'._('Max'),
					_('Y axis MAX value must be greater than Y axis MIN value')
				);
			}
		}

		if ($this->fields['righty']->getValue() == SVG_GRAPH_AXIS_SHOW) {
			$righty_min = $number_parser->parse($this->fields['righty_min']->getValue()) == CParser::PARSE_SUCCESS
				? $number_parser->calcValue()
				: '';

			$righty_max = $number_parser->parse($this->fields['righty_max']->getValue()) == CParser::PARSE_SUCCESS
				? $number_parser->calcValue()
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
	 * @param array $fields                Widget configuration fields.
	 * @param int   $fields['graph_time']  (optional)
	 *
	 * @return bool
	 */
	public static function hasOverrideTime($fields) {
		return (array_key_exists('graph_time', $fields) && $fields['graph_time'] == SVG_GRAPH_CUSTOM_TIME);
	}
}
