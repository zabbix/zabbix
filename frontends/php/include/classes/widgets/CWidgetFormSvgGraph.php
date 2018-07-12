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

		$this->data = self::convertDottedKeys($this->data);

		$this->tabs = [
			'data_set' => [
				'name' => _('Data set'),
				'fields' => []
			],
			'display_options' => [
				'name' => _('Display options'),
				'fields' => []
			],
			'time_perios' => [
				'name' => _('Time period'),
				'fields' => []
			],
			'axes' => [
				'name' => _('Axes'),
				'fields' => []
			],
			'legend' => [
				'name' => _('Legend'),
				'fields' => []
			],
			'problems' => [
				'name' => _('Problems'),
				'fields' => []
			],
			'overrides' => [
				'name' => _('Overrides'),
				'fields' => []
			]
		];

		// Hack to add preview field. This will be removed when better solution will be implemneted.
		$this->fields[] = new CWidgetFieldEmbed(
			(new CDiv(
				(new CDiv())
					->addItem('Wannabe SVG graph')
					->addStyle(
						'background: radial-gradient(circle, #f2f2f2, #f6f6ed, #ced7ce); '.
						'width: 100%; '.
						'vertical-align: middle; '.
						'text-align: center; '.
						'border-color: #dfe4e7; '.
						'border-width: 1px 0px; '.
						'border-style: solid; '.
						'padding: 80px 0 75px; '.
						'position: absolute; '.
						'color: #979797; '.
						'left: 0px; '.
						'right: 0px;'
					)
			))->addStyle('width: 100%; height: 185px;')
		);

		/**
		 * Data set tab.
		 *
		 * Contains single CWidgetFieldGraphDataSet field for data sets definition and configuration.
		 */
		$field_dataset = (new CWidgetFieldGraphDataSet('ds', ''));
		if (array_key_exists('ds', $this->data)) {
			$field_dataset->setValue($this->data['ds']);
		}
		$this->tabs['data_set']['fields'][] = $field_dataset;

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

		$this->tabs['display_options']['fields'][] = $field_data_source;

		/**
		 * Time period tab.
		 *
		 * Contains fields for specifying widget time options.
		 */
		// Checkbox to specify either relative dashboard time or widget's own time.
		$field_time_mode = (new CWidgetFieldCheckBox('graph_time', _('Override relative time')))
			->setDefault(SVG_GRAPH_CUSTOM_TIME)
			->setAction('jQuery("#time_from, #time_to, #time_from_dp, #time_to_dp")'.
							'.prop("disabled", !jQuery(this).is(":checked"));'
			);
		if (array_key_exists('graph_time', $this->data)) {
			$field_time_mode->setValue($this->data['graph_time']);
		}
		$this->tabs['time_perios']['fields'][] = $field_time_mode;

		// Date from.
		$field_time_from = new CWidgetFieldDatePicker('time_from', 'From');
		if ($field_time_mode->getValue() == SVG_GRAPH_CUSTOM_TIME) {
			$field_time_from->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('time_from', $this->data)) {
			$field_time_from->setValue($this->data['time_from']);
		}
		$this->tabs['time_perios']['fields'][] = $field_time_from;

		// Time to.
		$field_time_to = new CWidgetFieldDatePicker('time_to', 'To');
		if ($field_time_mode->getValue() == SVG_GRAPH_CUSTOM_TIME) {
			$field_time_to->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('time_to', $this->data)) {
			$field_time_to->setValue($this->data['time_to']);
		}
		$this->tabs['time_perios']['fields'][] = $field_time_to;

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
		$this->tabs['axes']['fields'][] = $field_left_y;

		// Min value on left Y axis.
		$field_left_y_min = new CWidgetFieldTextBox('lefty_min', _('Min'));
		if ($field_left_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW) {
			$field_left_y_min->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('lefty_min', $this->data)) {
			$field_left_y_min->setValue($this->data['lefty_min']);
		}
		$this->tabs['axes']['fields'][] = $field_left_y_min;

		// Max value on left Y axis.
		$field_left_y_max = new CWidgetFieldTextBox('lefty_max', _('Max'));
		if ($field_left_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW) {
			$field_left_y_max->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('lefty_max', $this->data)) {
			$field_left_y_max->setValue($this->data['lefty_max']);
		}
		$this->tabs['axes']['fields'][] = $field_left_y_max;

		// Specify the type of units on left Y axis.
		$field_left_y_units = (new CWidgetFieldComboBox('lefty_units', _('Units'), [
			SVG_GRAPH_AXIS_UNITS_AUTO => _x('Auto', 'history source selection method'),
			SVG_GRAPH_AXIS_UNITS_STATIC => _x('Static', 'history source selection method')
		]))
			->setDefault(SVG_GRAPH_AXIS_UNITS_AUTO)
			->setAction('jQuery("#lefty_static_units")'.
							'.prop("disabled", (jQuery(this).val() != "'.SVG_GRAPH_AXIS_UNITS_STATIC.'"))');
		if ($field_left_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW) {
			$field_left_y_units->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('lefty_units', $this->data)) {
			$field_left_y_units->setValue($this->data['lefty_units']);
		}
		$this->tabs['axes']['fields'][] = $field_left_y_units;

		// Static units on left Y axis.
		$field_left_y_static_units = new CWidgetFieldTextBox('lefty_static_units', null);
		if ($field_left_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW
				|| $field_left_y_units->getValue() != SVG_GRAPH_AXIS_UNITS_STATIC) {
			$field_left_y_static_units->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('lefty_static_units', $this->data)) {
			$field_left_y_static_units->setValue($this->data['lefty_static_units']);
		}
		$this->tabs['axes']['fields'][] = $field_left_y_static_units;

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
		$this->tabs['axes']['fields'][] = $field_right_y;

		// Min value on right Y axis.
		$field_right_y_min = new CWidgetFieldTextBox('righty_min', _('Min'));
		if ($field_right_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW) {
			$field_right_y_min->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('righty_min', $this->data)) {
			$field_right_y_min->setValue($this->data['righty_min']);
		}
		$this->tabs['axes']['fields'][] = $field_right_y_min;

		// Max value on right Y axis.
		$field_right_y_max = new CWidgetFieldTextBox('righty_max', _('Max'));
		if ($field_right_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW) {
			$field_right_y_max->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('righty_max', $this->data)) {
			$field_right_y_max->setValue($this->data['righty_max']);
		}
		$this->tabs['axes']['fields'][] = $field_right_y_max;

		// Specify the type of units on right Y axis.
		$field_right_y_units = (new CWidgetFieldComboBox('righty_units', _('Units'), [
			SVG_GRAPH_AXIS_UNITS_AUTO => _x('Auto', 'history source selection method'),
			SVG_GRAPH_AXIS_UNITS_STATIC => _x('Static', 'history source selection method')
		]))
			->setDefault(SVG_GRAPH_AXIS_UNITS_AUTO)
			->setAction('jQuery("#righty_static_units")'.
							'.prop("disabled", (jQuery(this).val() != "'.SVG_GRAPH_AXIS_UNITS_STATIC.'"))');
		if ($field_right_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW) {
			$field_right_y_units->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('righty_units', $this->data)) {
			$field_right_y_units->setValue($this->data['righty_units']);
		}
		$this->tabs['axes']['fields'][] = $field_right_y_units;

		// Static units on right Y axis.
		$field_right_y_static_units = new CWidgetFieldTextBox('righty_static_units', null);
		if ($field_right_y->getValue() != SVG_GRAPH_AXIS_Y_SHOW
				|| $field_right_y_units->getValue() != SVG_GRAPH_AXIS_UNITS_STATIC) {
			$field_right_y_static_units->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('righty_static_units', $this->data)) {
			$field_right_y_static_units->setValue($this->data['righty_static_units']);
		}
		$this->tabs['axes']['fields'][] = $field_right_y_static_units;

		// Show X axis.
		$field_axis_x = (new CWidgetFieldCheckBox('axisx', _('X-Axis'), _('Show')))->setDefault(SVG_GRAPH_AXIS_X_SHOW);
		if (array_key_exists('axisx', $this->data)) {
			$field_axis_x->setValue($this->data['axisx']);
		}
		$this->tabs['axes']['fields'][] = $field_axis_x;

		/**
		 * Legend tab.
		 *
		 * Contains single check-box field to show/hide legend.
		 */
		$field_legend = (new CWidgetFieldCheckBox('legend', _('Show legend')))->setDefault(SVG_GRAPH_LEGEND_SHOW);
		if (array_key_exists('legend', $this->data)) {
			$field_legend->setValue($this->data['legend']);
		}
		$this->tabs['legend']['fields'][] = $field_legend;

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
				'jQuery("#graph_item_problems, #problem_hosts, #problem_name").prop("disabled", !on);'.
				'jQuery("[name=\"severities[]\"]").prop("disabled", !on);'.
				'jQuery("[name=\"evaltype\"]").prop("disabled", !on);'.
				'jQuery("input, button", jQuery("#tags_table")).prop("disabled", !on);'
			);
		if (array_key_exists('show_problems', $this->data)) {
			$field_show_problems->setValue($this->data['show_problems']);
		}
		$this->tabs['problems']['fields'][] = $field_show_problems;

		// Checkbox: Selected items only.
		$field_problems = (new CWidgetFieldCheckBox('graph_item_problems', _('Selected items only')))
			->setDefault(SVG_GRAPH_SELECTED_ITEM_PROBLEMS);
		if ($field_show_problems->getValue() != SVG_GRAPH_PROBLEMS_SHOW) {
			$field_problems->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('graph_item_problems', $this->data)) {
			$field_problems->setValue($this->data['graph_item_problems']);
		}
		$this->tabs['problems']['fields'][] = $field_problems;

		// Problem hosts.
		$field_problem_hosts = new CWidgetFieldTextBox('problem_hosts', _('Hosts'));
		if ($field_show_problems->getValue() != SVG_GRAPH_PROBLEMS_SHOW) {
			$field_problem_hosts->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('problem_hosts', $this->data)) {
			$field_problem_hosts->setValue($this->data['problem_hosts']);
		}
		$this->tabs['problems']['fields'][] = $field_problem_hosts;

		// Severity checkboxes list.
		$field_severities = (new CWidgetFieldSeverities('severities', _('Severity')))
			->setStyle(ZBX_STYLE_LIST_HOR_CHECK_RADIO);
		if ($field_show_problems->getValue() != SVG_GRAPH_PROBLEMS_SHOW) {
			$field_severities->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('severities', $this->data)) {
			$field_severities->setValue($this->data['severities']);
		}
		$this->tabs['problems']['fields'][] = $field_severities;

		// Problem name input-text field.
		$field_problem_name = new CWidgetFieldTextBox('problem_name', _('Problem'));
		if ($field_show_problems->getValue() != SVG_GRAPH_PROBLEMS_SHOW) {
			$field_problem_name->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('problem_name', $this->data)) {
			$field_problem_name->setValue($this->data['problem_name']);
		}
		$this->tabs['problems']['fields'][] = $field_problem_name;

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
		$this->tabs['problems']['fields'][] = $field_evaltype;

		// Problem tags field.
		$field_tags = new CWidgetFieldTags('tags', '');
		if ($field_show_problems->getValue() != SVG_GRAPH_PROBLEMS_SHOW) {
			$field_tags->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('tags', $this->data)) {
			$field_tags->setValue($this->data['tags']);
		}
		$this->tabs['problems']['fields'][] = $field_tags;

		/**
		 * Overrides tab.
		 *
		 * Contains single field for override configuration.
		 */
		$overrides = array_key_exists('or', $this->data) ? $this->data['or'] : [];
		$this->tabs['overrides']['fields'][] = (new CWidgetFieldGraphOverride('or', ''))->setValue($overrides);
	}
}
