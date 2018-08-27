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
			'edit_mode' => 'in 0,1',
			'content_width' => 'int32',
			'content_height' => 'int32',
			'preview' => 'in 1',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();
		$uniqueid = $this->getInput('uniqueid');
		$edit_mode = $this->getInput('edit_mode', 0);
		$width = (int) $this->getInput('content_width', 100);
		$height = (int) $this->getInput('content_height', 100);
		$preview = (bool) $this->getInput('preview', 0); // Configuration preview.
		$initial_load = $this->getInput('initial_load', 1);
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
			'dashboard_time' => !CWidgetFormSvgGraph::hasOverrideTime($fields),
			'overrides' => []
		];

		// Set data source options.
		if (array_key_exists('source', $fields)
				&& in_array($fields['source'], [SVG_GRAPH_DATA_SOURCE_HISTORY, SVG_GRAPH_DATA_SOURCE_TRENDS])) {
			$graph_data['data_source'] = $fields['source'];
		}

		// Use dashboard time from user profile.
		if ($graph_data['dashboard_time'] && !$preview) {
			$timeline = getTimeSelectorPeriod([
				'profileIdx' => 'web.dashbrd.filter',
				'profileIdx2' => $this->getInput('dashboardid', 0)
			]);

			$graph_data['time_period'] = [
				'time_from' => $timeline['from_ts'],
				'time_to' => $timeline['to_ts']
			];

			// Init script that refreshes widget once timeselector changes.
			if ($initial_load) {
				$script_inline .=
					'jQuery.subscribe("timeselector.rangeupdate", function(e, data) {'.
						'jQuery(".dashbrd-grid-widget-container").dashboardGrid(\'refreshWidget\', "'.$uniqueid.'");'.
					'});';
			}
		}
		// Otherwise, set graph time period options.
		else {
			$range_time_parser = new CRangeTimeParser();

			$range_time_parser->parse($graph_data['dashboard_time'] ? 'now-1h' : $fields['time_from']);
			$graph_data['time_period']['time_from'] = $range_time_parser->getDateTime(true)->getTimestamp();

			$range_time_parser->parse($graph_data['dashboard_time'] ? 'now' : $fields['time_to']);
			$graph_data['time_period']['time_to'] = $range_time_parser->getDateTime(false)->getTimestamp();
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
			if (array_key_exists('min', $graph_data['left_y_axis'])
					&& array_key_exists('max', $graph_data['left_y_axis'])
					&& $graph_data['left_y_axis']['min'] >= $graph_data['left_y_axis']['max']) {
				unset($graph_data['left_y_axis']['min'], $graph_data['left_y_axis']['max']); // Unset invalid.
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
			if (array_key_exists('min', $graph_data['right_y_axis'])
					&& array_key_exists('max', $graph_data['right_y_axis'])
					&& $graph_data['right_y_axis']['min'] >= $graph_data['right_y_axis']['max']) {
				unset($graph_data['right_y_axis']['min'], $graph_data['right_y_axis']['max']); // Unset invalid.
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

		// Legend type and space to reserve (in terms of number of lines).
		$graph_data['legend'] = array_key_exists('legend', $fields) ? $fields['legend'] : SVG_GRAPH_LEGEND_TYPE_NONE;
		if ($graph_data['legend'] != SVG_GRAPH_LEGEND_TYPE_NONE) {
			if (array_key_exists('legend_lines', $fields) && $fields['legend_lines'] >= SVG_GRAPH_LEGEND_LINES_MIN
					&& $fields['legend_lines'] >= SVG_GRAPH_LEGEND_LINES_MIN
					&& $fields['legend_lines'] <= SVG_GRAPH_LEGEND_LINES_MAX) {
				$graph_data['legend_lines'] = $fields['legend_lines'];
			}
			else {
				$graph_data['legend_lines'] = SVG_GRAPH_LEGEND_LINES_DEFAULT;
			}
		}

		// Show problems.
		if (array_key_exists('show_problems', $fields) && $fields['show_problems'] == SVG_GRAPH_PROBLEMS_SHOW) {
			// Show graph item problems only.
			if (array_key_exists('graph_item_problems', $fields)
					&& $fields['graph_item_problems'] == SVG_GRAPH_SELECTED_ITEM_PROBLEMS) {
				$graph_data['problems']['graph_item_problems_only'] = SVG_GRAPH_SELECTED_ITEM_PROBLEMS;
			}

			// Problem hosts.
			if (array_key_exists('problemhosts', $fields) && $fields['problemhosts'] !== '') {
				$graph_data['problems']['problemhosts'] = $fields['problemhosts'];
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
			'sbox' => $graph_data['dashboard_time'], // SBox available only for graphs without overwriten relative time.
			'show_problems' => array_key_exists('problems', $graph_data),
			'hint_max_rows' => ZBX_WIDGET_ROWS
		]);

		if (!$preview) {
			$script_inline .=
				'var widget = jQuery(".dashbrd-grid-widget-container")'.
						'.dashboardGrid(\'getWidgetsBy\', \'uniqueid\', "'.$uniqueid.'");'.
				'jQuery(\'svg\', widget[0]["content_body"]).svggraph('.CJs::encodeJson($graph_options).');';
		}

		if ($initial_load) {
			// Register widget auto-refresh when resizing widget.
			$script_inline .=
				'if (typeof(zbx_svggraph_widget_resize_end) !== typeof(Function)) {'.
					'function zbx_svggraph_widget_resize_end() {'.
						'jQuery(".dashbrd-grid-widget-container").dashboardGrid("refreshWidget", "'.$uniqueid.'");'.
					'}'.
				'}'.
				'jQuery(".dashbrd-grid-widget-container").dashboardGrid("addAction", "onResizeEnd",'.
					'"zbx_svggraph_widget_resize_end", "'.$uniqueid.'", {'.
						'trigger_name: "svggraph_widget_resize_end_'.$uniqueid.'"'.
					'});';

			// Disable SBox when switch to edit mode.
			$script_inline .=
				'if (typeof(zbx_svggraph_widget_edit_start) !== typeof(Function)) {'.
					'function zbx_svggraph_widget_edit_start() {'.
						'var widget = jQuery(".dashbrd-grid-widget-container")'.
								'.dashboardGrid(\'getWidgetsBy\', \'uniqueid\', "'.$uniqueid.'");'.
						'jQuery(\'svg\', widget[0]["content_body"]).svggraph("disableSBox");'.
					'}'.
				'}'.
				'jQuery(".dashbrd-grid-widget-container").dashboardGrid("addAction", "onEditStart",'.
					'"zbx_svggraph_widget_edit_start", "'.$uniqueid.'", {'.
						'trigger_name: "svggraph_widget_edit_start_'.$uniqueid.'"'.
					'});';
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'svg' => $svg_data['svg'].$svg_data['legend'],
			'script_inline' => $script_inline,
			'initial_load' => $initial_load,
			'preview' => $preview,
			'info' => $edit_mode ? null : self::makeWidgetInfo($fields),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	/**
	 * Make widget specific info to show in widget's header.
	 *
	 * @param array  $fields
	 *
	 * @return array
	 */
	private static function makeWidgetInfo(array $fields) {
		$info = [];

		if (CWidgetFormSvgGraph::hasOverrideTime($fields)) {
			$info[] = [
				'icon' => 'btn-info-clock',
				'hint' => relativeDateToText($fields['time_from'], $fields['time_to'])
			];
		}

		return $info;
	}
}
