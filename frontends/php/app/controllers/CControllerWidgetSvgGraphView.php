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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerWidgetSvgGraphView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_SVG_GRAPH);
		$this->setValidationRules([
			'name' => 'string',
			'uniqueid' => 'required|string',
			'dashboardid' => 'db dashboard.dashboardid',
			'initial_load' => 'in 0,1',
			'content_width' => 'int32',
			'content_height' => 'int32',
			'edit_mode' => 'in 0,1',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();
		$uniqueid = $this->getInput('uniqueid');
		$width = (int) $this->getInput('content_width', 100);
		$height = (int) $this->getInput('content_height', 100);
		$edit_mode = (bool) $this->getInput('edit_mode', 0); // Configuration preview is loaded in edit mode.
		$initial_load = !$edit_mode && $this->getInput('initial_load', 1);
		$script_inline = '';

		// Sort fields by its natural order.
		CArrayHelper::sort($fields['ds'], ['order']);

		$graph_data = [
			'data_sets' => array_values($fields['ds']),
			'data_source' => SVG_GRAPH_DATA_SOURCE_AUTO,
			'time_period' => [
				'time_from' => null,
				'time_to' => null
			],
			'dashboard_time' => false,
			'overrides' => []
		];

		// Set data source options.
		if (array_key_exists('source', $fields)
				&& in_array($fields['source'], [SVG_GRAPH_DATA_SOURCE_HISTORY, SVG_GRAPH_DATA_SOURCE_TRENDS])) {
			$graph_data['data_source'] = $fields['source'];
		}

		/**
		 * Set graph time period options.
		 *
		 * First, try get widget's custom time.
		 */
		if (array_key_exists('graph_time', $fields) && $fields['graph_time'] == SVG_GRAPH_CUSTOM_TIME) {
			$range_time_parser = new CRangeTimeParser();

			foreach (['time_from', 'time_to'] as $field) {
				if (array_key_exists($field, $fields) && $fields[$field] !== ''
						&& $range_time_parser->parse($fields[$field]) === CParser::PARSE_SUCCESS) {
					$graph_data['time_period'][$field]
						= $range_time_parser->getDateTime($field === 'time_from')->getTimestamp();
				}
			}
		}

		// If no valid date range specified, use dashboard time from user profile.
		if (!$graph_data['time_period']['time_from'] || !$graph_data['time_period']['time_to']) {
			$timeline = getTimeSelectorPeriod([
				'profileIdx' => 'web.dashbrd.filter',
				'profileIdx2' => $this->getInput('dashboardid', 0)
			]);

			$graph_data['time_period'] = [
				'time_from' => $timeline['from_ts'],
				'time_to' => $timeline['to_ts']
			];

			$graph_data['dashboard_time'] = true;

			// Init script that refreshes widget once timeselector changes.
			if ($initial_load) {
				$script_inline .=
					'jQuery.subscribe("timeselector.rangeupdate", function(e, data) {'.
						'jQuery(".dashbrd-grid-widget-container").dashboardGrid(\'refreshWidget\', "'.$uniqueid.'");'.
					'});';
			}
		}

		// Set left Y axis options.
		if (array_key_exists('lefty', $fields) && $fields['lefty'] == SVG_GRAPH_AXIS_Y_SHOW) {
			$graph_data['left_y_axis'] = [];

			if (array_key_exists('lefty_min', $fields) && is_numeric($fields['lefty_min'])) {
				$graph_data['left_y_axis']['min'] = $fields['lefty_min'];
			}
			if (array_key_exists('lefty_max', $fields) && is_numeric($fields['lefty_max'])) {
				$graph_data['left_y_axis']['max'] = $fields['lefty_max'];
			}
			if (array_key_exists('lefty_units', $fields) && $fields['lefty_units'] == SVG_GRAPH_AXIS_UNITS_STATIC) {
				$graph_data['left_y_axis']['units'] = array_key_exists('lefty_static_units', $fields)
					? $fields['lefty_static_units']
					: '';
			}
		}

		// Set right Y axis options.
		if (array_key_exists('righty', $fields) && $fields['righty'] == SVG_GRAPH_AXIS_Y_SHOW) {
			$graph_data['right_y_axis'] = [];

			if (array_key_exists('righty_min', $fields) && is_numeric($fields['righty_min'])) {
				$graph_data['right_y_axis']['min'] = $fields['righty_min'];
			}
			if (array_key_exists('righty_max', $fields) && is_numeric($fields['righty_max'])) {
				$graph_data['right_y_axis']['max'] = $fields['righty_max'];
			}
			if (array_key_exists('righty_units', $fields) && $fields['righty_units'] == SVG_GRAPH_AXIS_UNITS_STATIC) {
				$graph_data['right_y_axis']['units'] = array_key_exists('righty_static_units', $fields)
					? $fields['righty_static_units']
					: '';
			}
		}

		// Set X axis options.
		if (array_key_exists('axisx', $fields) && $fields['axisx'] == SVG_GRAPH_AXIS_X_SHOW) {
			$graph_data['x_axis'] = SVG_GRAPH_AXIS_X_SHOW;
		}

		// Legend type.
		$graph_data['legend'] = array_key_exists('legend', $fields) ? $fields['legend'] : SVG_GRAPH_LEGEND_TYPE_NONE;

		// Show problems.
		if (array_key_exists('show_problems', $fields) && $fields['show_problems'] == SVG_GRAPH_PROBLEMS_SHOW) {
			// Show graph item problems only.
			if (array_key_exists('graph_item_problems', $fields)
					&& $fields['graph_item_problems'] == SVG_GRAPH_SELECTED_ITEM_PROBLEMS) {
				$graph_data['problems']['graph_item_problems_only'] = SVG_GRAPH_SELECTED_ITEM_PROBLEMS;
			}

			// Problem hosts.
			if (array_key_exists('problem_hosts', $fields) && $fields['problem_hosts'] !== '') {
				$graph_data['problems']['problem_hosts'] = $fields['problem_hosts'];
			}

			// Problem severities.
			$graph_data['problems']['severities'] = (array_key_exists('severities', $fields) && $fields['severities'])
				? $fields['severities']
				: range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1);

			// Problem name.
			if (array_key_exists('problem_name', $fields) && $fields['problem_name'] !== '') {
				$graph_data['problems']['problem_name'] = $fields['problem_name'];
			}

			// Problem tag evaltype.
			$graph_data['problems']['evaltype'] = (array_key_exists('evaltype', $fields)
					&& $fields['evaltype'] == TAG_EVAL_TYPE_OR)
				? $fields['evaltype']
				: TAG_EVAL_TYPE_AND_OR;

			// Problem tags.
			if (array_key_exists('tags', $fields) && $fields['tags']) {
				$graph_data['problems']['tags'] = $fields['tags'];
			}
		}

		// Set overrides.
		if (array_key_exists('or', $fields)) {
			$graph_data['overrides'] = $fields['or'];
		}

		$svg_data = CSvgGraphHelper::get($graph_data, $width, $height);
		if ($svg_data['errors']) {
			error($svg_data['errors']);
		}

		$graph_options = zbx_array_merge($svg_data['data'], [
			'sbox' => $graph_data['dashboard_time'], // SBox available only for graphs without overriten relative time.
			'values' => true
		]);

		if (!$edit_mode) {
			$script_inline .=
				'var widget = jQuery(".dashbrd-grid-widget-container")'.
						'.dashboardGrid(\'getWidgetsBy\', \'uniqueid\', "'.$uniqueid.'");'.
				'jQuery(\'svg\', widget[0]["content_body"]).svggraph('.CJs::encodeJson($graph_options).');';
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'svg' => $svg_data['svg'],
			'script_inline' => $script_inline,
			'edit_mode' => $edit_mode,
			'initial_load' => $initial_load,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
