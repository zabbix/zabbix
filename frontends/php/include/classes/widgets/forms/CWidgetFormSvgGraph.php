<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

	public function __construct($data) {
		parent::__construct($data, WIDGET_SVG_GRAPH);

		$this->data = self::convertDottedKeys($this->data, true);

		/**
		 * Data set tab.
		 *
		 * Contains single CWidgetFieldGraphDataSet field for data sets definition and configuration.
		 */
		$field_dataset = (new CWidgetFieldGraphDataSet('ds', ''));
		if (array_key_exists('ds', $this->data)) {
			$field_dataset->setValue($this->data['ds']);
		}
		$this->fields[$field_dataset->getName()] = $field_dataset;

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
		$field_time_mode = (new CWidgetFieldCheckBox('graph_time', _('Override relative time')))
			->setAction('jQuery("#time_from, #time_to, #time_from_dp, #time_to_dp")'.
							'.prop("disabled", !jQuery(this).is(":checked"));'
			);
		if (array_key_exists('graph_time', $this->data)) {
			$field_time_mode->setValue($this->data['graph_time']);
		}
		$this->fields[$field_time_mode->getName()] = $field_time_mode;

		// Date from.
		$field_time_from = new CWidgetFieldDatePicker('time_from', _('From'));
		if ($field_time_mode->getValue() != SVG_GRAPH_CUSTOM_TIME) {
			$field_time_from->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('time_from', $this->data)) {
			$field_time_from->setValue($this->data['time_from']);
		}
		$this->fields[$field_time_from->getName()] = $field_time_from;

		// Time to.
		$field_time_to = new CWidgetFieldDatePicker('time_to', _('To'));
		if ($field_time_mode->getValue() != SVG_GRAPH_CUSTOM_TIME) {
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
		$field_left_y = (new CWidgetFieldCheckBox('lefty', _('Left Y'), _('Show')))
			->setDefault(SVG_GRAPH_AXIS_Y_SHOW)
			->setAction(
				'var on = jQuery(this).is(":checked");'.
				'jQuery("#lefty_min, #lefty_max, #lefty_units").prop("disabled", !on);'.
				'jQuery("#lefty_static_units").prop("disabled",'.
					'(!on || jQuery("#lefty_units").val() != "'.SVG_GRAPH_AXIS_UNITS_STATIC.'"));'
			);
		if (array_key_exists('lefty', $this->data)) {
			$field_left_y->setValue($this->data['lefty']);
		}
		$this->fields[$field_left_y->getName()] = $field_left_y;

		// Min value on left Y axis.
		$field_left_y_min = (new CWidgetFieldTextBox('lefty_min', _('Min')))
			->setAttribute('placeholder', _('calculated'))
			->setAttribute('style', 'width: '.ZBX_TEXTAREA_SMALL_WIDTH.'px;');
		if ($field_left_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW) {
			$field_left_y_min->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('lefty_min', $this->data)) {
			$field_left_y_min->setValue($this->data['lefty_min']);
		}
		$this->fields[$field_left_y_min->getName()] = $field_left_y_min;

		// Max value on left Y axis.
		$field_left_y_max = (new CWidgetFieldTextBox('lefty_max', _('Max')))
			->setAttribute('placeholder', _('calculated'))
			->setAttribute('style', 'width: '.ZBX_TEXTAREA_SMALL_WIDTH.'px;');
		if ($field_left_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW) {
			$field_left_y_max->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('lefty_max', $this->data)) {
			$field_left_y_max->setValue($this->data['lefty_max']);
		}
		$this->fields[$field_left_y_max->getName()] = $field_left_y_max;

		// Specify the type of units on left Y axis.
		$field_left_y_units = (new CWidgetFieldComboBox('lefty_units', _('Units'), [
			SVG_GRAPH_AXIS_UNITS_AUTO => _x('Auto', 'history source selection method'),
			SVG_GRAPH_AXIS_UNITS_STATIC => _x('Static', 'history source selection method')
		]))
			->setDefault(SVG_GRAPH_AXIS_UNITS_AUTO)
			->setAttribute('style', 'width: '.ZBX_TEXTAREA_TINY_WIDTH.'px;')
			->setAction('jQuery("#lefty_static_units")'.
							'.prop("disabled", (jQuery(this).val() != "'.SVG_GRAPH_AXIS_UNITS_STATIC.'"))');
		if ($field_left_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW) {
			$field_left_y_units->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('lefty_units', $this->data)) {
			$field_left_y_units->setValue($this->data['lefty_units']);
		}
		$this->fields[$field_left_y_units->getName()] = $field_left_y_units;

		// Static units on left Y axis.
		$field_left_y_static_units = (new CWidgetFieldTextBox('lefty_static_units', null))
			->setAttribute('placeholder', _('value'))
			->setAttribute('style', 'width: '.ZBX_TEXTAREA_TINY_WIDTH.'px;');
		if ($field_left_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW
				|| $field_left_y_units->getValue() != SVG_GRAPH_AXIS_UNITS_STATIC) {
			$field_left_y_static_units->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('lefty_static_units', $this->data)) {
			$field_left_y_static_units->setValue($this->data['lefty_static_units']);
		}
		$this->fields[$field_left_y_static_units->getName()] = $field_left_y_static_units;

		// Show right Y axis.
		$field_right_y = (new CWidgetFieldCheckBox('righty', _('Right Y'), _('Show')))
			->setDefault(SVG_GRAPH_AXIS_Y_SHOW)
			->setAction(
				'var on = jQuery(this).is(":checked");'.
				'jQuery("#righty_min, #righty_max, #righty_units").prop("disabled", !on);'.
				'jQuery("#righty_static_units").prop("disabled",'.
					'(!on || jQuery("#righty_units").val() != "'.SVG_GRAPH_AXIS_UNITS_STATIC.'"));'
			);
		if (array_key_exists('righty', $this->data)) {
			$field_right_y->setValue($this->data['righty']);
		}
		$this->fields[$field_right_y->getName()] = $field_right_y;

		// Min value on right Y axis.
		$field_right_y_min = (new CWidgetFieldTextBox('righty_min', _('Min')))
			->setAttribute('placeholder', _('calculated'))
			->setAttribute('style', 'width: '.ZBX_TEXTAREA_SMALL_WIDTH.'px;');
		if ($field_right_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW) {
			$field_right_y_min->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('righty_min', $this->data)) {
			$field_right_y_min->setValue($this->data['righty_min']);
		}
		$this->fields[$field_right_y_min->getName()] = $field_right_y_min;

		// Max value on right Y axis.
		$field_right_y_max = (new CWidgetFieldTextBox('righty_max', _('Max')))
			->setAttribute('placeholder', _('calculated'))
			->setAttribute('style', 'width: '.ZBX_TEXTAREA_SMALL_WIDTH.'px;');
		if ($field_right_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW) {
			$field_right_y_max->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('righty_max', $this->data)) {
			$field_right_y_max->setValue($this->data['righty_max']);
		}
		$this->fields[$field_right_y_max->getName()] = $field_right_y_max;

		// Specify the type of units on right Y axis.
		$field_right_y_units = (new CWidgetFieldComboBox('righty_units', _('Units'), [
			SVG_GRAPH_AXIS_UNITS_AUTO => _x('Auto', 'history source selection method'),
			SVG_GRAPH_AXIS_UNITS_STATIC => _x('Static', 'history source selection method')
		]))
			->setDefault(SVG_GRAPH_AXIS_UNITS_AUTO)
			->setAttribute('style', 'width: '.ZBX_TEXTAREA_TINY_WIDTH.'px;')
			->setAction('jQuery("#righty_static_units")'.
							'.prop("disabled", (jQuery(this).val() != "'.SVG_GRAPH_AXIS_UNITS_STATIC.'"))');
		if ($field_right_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW) {
			$field_right_y_units->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('righty_units', $this->data)) {
			$field_right_y_units->setValue($this->data['righty_units']);
		}
		$this->fields[$field_right_y_units->getName()] = $field_right_y_units;

		// Static units on right Y axis.
		$field_right_y_static_units = (new CWidgetFieldTextBox('righty_static_units', null))
			->setAttribute('placeholder', _('value'))
			->setAttribute('style', 'width: '.ZBX_TEXTAREA_TINY_WIDTH.'px;');
		if ($field_right_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW
				|| $field_right_y_units->getValue() != SVG_GRAPH_AXIS_UNITS_STATIC) {
			$field_right_y_static_units->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('righty_static_units', $this->data)) {
			$field_right_y_static_units->setValue($this->data['righty_static_units']);
		}
		$this->fields[$field_right_y_static_units->getName()] = $field_right_y_static_units;

		// Show X axis.
		$field_axis_x = (new CWidgetFieldCheckBox('axisx', _('X-Axis'), _('Show')))->setDefault(SVG_GRAPH_AXIS_X_SHOW);
		if (array_key_exists('axisx', $this->data)) {
			$field_axis_x->setValue($this->data['axisx']);
		}
		$this->fields[$field_axis_x->getName()] = $field_axis_x;

		/**
		 * Legend tab.
		 *
		 * Contains single check-box field to show/hide legend.
		 */
		$field_legend = (new CWidgetFieldCheckBox('legend', _('Show legend')))
			->setAction('jQuery("[name=legend_lines]").rangeControl(jQuery(this).is(":checked")?"enable":"disable");')
			->setDefault(SVG_GRAPH_LEGEND_TYPE_SHORT);
		if (array_key_exists('legend', $this->data)) {
			$field_legend->setValue($this->data['legend']);
		}
		$this->fields[$field_legend->getName()] = $field_legend;

		$field_legend_lines = (new CWidgetFieldRangeControl('legend_lines', _('Number of lines'),
				SVG_GRAPH_LEGEND_LINES_MIN, SVG_GRAPH_LEGEND_LINES_MAX
			))
				->setDefault(SVG_GRAPH_LEGEND_LINES_DEFAULT);
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
			->setDefault(SVG_GRAPH_PROBLEMS_SHOW)
			->setAction(
				'var on = jQuery(this).is(":checked");'.
				'jQuery("#graph_item_problems, #problemhosts, #problem_name, #problemhosts_select")'.
					'.prop("disabled", !on);'.
				'jQuery("[name=\"severities[]\"]").prop("disabled", !on);'.
				'jQuery("[name=\"evaltype\"]").prop("disabled", !on);'.
				'jQuery("input, button", jQuery("#tags_table_tags")).prop("disabled", !on);'
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
		$field_problem_hosts = (new CWidgetFieldTextArea('problemhosts', _('Problem hosts')));
		if ($field_show_problems->getValue() != SVG_GRAPH_PROBLEMS_SHOW) {
			$field_problem_hosts->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('problemhosts', $this->data)) {
			$problem_hosts = is_array($this->data['problemhosts'])
				? implode(', ', $this->data['problemhosts'])
				: $this->data['problemhosts'];
			$field_problem_hosts->setValue($problem_hosts);
		}
		$this->fields[$field_problem_hosts->getName()] = $field_problem_hosts;

		// Severity checkboxes list.
		$field_severities = (new CWidgetFieldSeverities('severities', _('Severity')))
			->setStyle(ZBX_STYLE_LIST_HOR_CHECK_RADIO);
		if ($field_show_problems->getValue() != SVG_GRAPH_PROBLEMS_SHOW) {
			$field_severities->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('severities', $this->data)) {
			$field_severities->setValue($this->data['severities']);
		}
		$this->fields[$field_severities->getName()] = $field_severities;

		// Problem name input-text field.
		$field_problem_name = (new CWidgetFieldTextBox('problem_name', _('Problem')))
			->setAttribute('placeholder', _('problem pattern'));
		if ($field_show_problems->getValue() != SVG_GRAPH_PROBLEMS_SHOW) {
			$field_problem_name->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('problem_name', $this->data)) {
			$field_problem_name->setValue($this->data['problem_name']);
		}
		$this->fields[$field_problem_name->getName()] = $field_problem_name;

		// Problem tag evalype (And/Or).
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
		$overrides = (new CWidgetFieldGraphOverride('or', ''));
		if (array_key_exists('or', $this->data)) {
			$overrides->setValue($this->data['or']);
		}

		$this->fields[$overrides->getName()] = $overrides;
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
			$from = $this->fields['time_from']->getValue();
			$to = $this->fields['time_to']->getValue();
			$errors = zbx_array_merge($errors, CWidgetFieldDatePicker::validateTimeSelectorPeriod($from, $to));
		}

		// Validate Min/Max values in Axes tab.
		if ($this->fields['lefty']->getValue() == SVG_GRAPH_AXIS_Y_SHOW) {
			$lefty_min = $this->fields['lefty_min']->getValue();
			$lefty_max = $this->fields['lefty_max']->getValue();
			if ($lefty_min !== '' && !is_numeric($lefty_min)) {
				$errors[] = _s('Invalid parameter "%1$s" in field "%2$s": %3$s.', _('Min'), _('Left Y'), _('a number is expected'));
			}
			elseif ($lefty_max !== '' && !is_numeric($lefty_max)) {
				$errors[] = _s('Invalid parameter "%1$s" in field "%2$s": %3$s.', _('Max'), _('Left Y'), _('a number is expected'));
			}
			elseif ($lefty_min !== '' && $lefty_max !== '' && (int)$lefty_min >= (int)$lefty_max) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Min'), $lefty_min);
			}
		}

		if ($this->fields['righty']->getValue() == SVG_GRAPH_AXIS_Y_SHOW) {
			$righty_min = $this->fields['righty_min']->getValue();
			$righty_max = $this->fields['righty_max']->getValue();
			if ($righty_min !== '' && !is_numeric($righty_min)) {
				$errors[] = _s('Invalid parameter "%1$s" in field "%2$s": %3$s.', _('Min'), _('Right Y'), _('a number is expected'));
			}
			elseif ($righty_max !== '' && !is_numeric($righty_max)) {
				$errors[] = _s('Invalid parameter "%1$s" in field "%2$s": %3$s.', _('Max'), _('Right Y'), _('a number is expected'));
			}
			elseif ($righty_min !== '' && $righty_max !== '' && (int)$righty_min >= (int)$righty_max) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Min'), $righty_min);
			}
		}
		return $errors;
	}

	/**
	 * Check if widget configuration is set to use overridden time and calculates start and end time for graph.
	 *
	 * @param array $fields    Widget configuration fields.
	 * @param bool  $unixtime  Return start and end time as unix timestamps.
	 *
	 * @return array|bool    Returns start and end time of widget's custom time or false if relative time is not
	 *	                     overridden.
	 */
	public static function getOverriteTime($fields, $unixtime = true) {
		if (array_key_exists('graph_time', $fields) && $fields['graph_time'] == SVG_GRAPH_CUSTOM_TIME) {
			$range_time_parser = new CRangeTimeParser();
			if (!$unixtime) {
				$date = new DateTime();
			}
			$from = null;
			$to = null;

			if (array_key_exists('time_from', $fields) && $fields['time_from'] !== ''
					&& $range_time_parser->parse($fields['time_from']) === CParser::PARSE_SUCCESS) {
				$from = $unixtime
					? $range_time_parser->getDateTime(true)->getTimestamp()
					: $date
						->setTimestamp($range_time_parser->getDateTime(true)->getTimestamp())
						->format(ZBX_FULL_DATE_TIME);
			}

			if (array_key_exists('time_to', $fields) && $fields['time_to'] !== ''
					&& $range_time_parser->parse($fields['time_to']) === CParser::PARSE_SUCCESS) {
				$to = $unixtime
					? $range_time_parser->getDateTime(false)->getTimestamp()
					: $date
						->setTimestamp($range_time_parser->getDateTime(false)->getTimestamp())
						->format(ZBX_FULL_DATE_TIME);
			}

			if ($from && $to) {
				return [
					'from' => $from,
					'to' => $to
				];
			}
		}

		return false;
	}
}
