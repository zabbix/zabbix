<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

	const GRAPH_WIDTH_MIN = 1;
	const GRAPH_WIDTH_MAX = 65535;
	const GRAPH_HEIGHT_MIN = 1;
	const GRAPH_HEIGHT_MAX = 65535;

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_SVG_GRAPH);
		$this->setValidationRules([
			'name' => 'string',
			'uniqueid' => 'required|string',
			'dashboardid' => 'db dashboard.dashboardid',
			'initial_load' => 'in 0,1',
			'edit_mode' => 'in 0,1',
			'content_width' => 'int32|ge '.self::GRAPH_WIDTH_MIN.'|le '.self::GRAPH_WIDTH_MAX,
			'content_height' => 'int32|ge '.self::GRAPH_HEIGHT_MIN.'|le '.self::GRAPH_HEIGHT_MAX,
			'preview' => 'in 1',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();
		$uniqueid = $this->getInput('uniqueid');
		$edit_mode = $this->getInput('edit_mode', 0);
		$width = (int) $this->getInput('content_width', self::GRAPH_WIDTH_MIN);
		$height = (int) $this->getInput('content_height', self::GRAPH_HEIGHT_MIN);
		$preview = (bool) $this->getInput('preview', 0); // Configuration preview.
		$initial_load = $this->getInput('initial_load', 1);
		$script_inline = '';

		$graph_data = [
			'data_sets' => array_values($fields['ds']),
			'data_source' => $fields['source'],
			'dashboard_time' => !CWidgetFormSvgGraph::hasOverrideTime($fields),
			'time_period' => [
				'time_from' => null,
				'time_to' => null
			],
			'left_y_axis' => [
				'show' => $fields['lefty'],
				'min' => ($fields['lefty_min'] !== '')
					? convertFunctionValue($fields['lefty_min'], ZBX_UNITS_ROUNDOFF_LOWER_LIMIT)
					: '',
				'max' => ($fields['lefty_max'] !== '')
					? convertFunctionValue($fields['lefty_max'], ZBX_UNITS_ROUNDOFF_LOWER_LIMIT)
					: '',
				'units' => ($fields['lefty_units'] == SVG_GRAPH_AXIS_UNITS_STATIC)
					? $fields['lefty_static_units']
					: null
			],
			'right_y_axis' => [
				'show' => $fields['righty'],
				'min' => ($fields['righty_min'] !== '')
					? convertFunctionValue($fields['righty_min'], ZBX_UNITS_ROUNDOFF_LOWER_LIMIT)
					: '',
				'max' => ($fields['righty_max'] !== '')
					? convertFunctionValue($fields['righty_max'], ZBX_UNITS_ROUNDOFF_LOWER_LIMIT)
					: '',
				'units' => ($fields['righty_units'] == SVG_GRAPH_AXIS_UNITS_STATIC)
					? $fields['righty_static_units']
					: null
			],
			'x_axis' => [
				'show' => $fields['axisx']
			],
			'legend' => $fields['legend'],
			'legend_lines' => $fields['legend_lines'],
			'problems' => [
				'show_problems' => ($fields['show_problems'] == SVG_GRAPH_PROBLEMS_SHOW),
				'graph_item_problems' => $fields['graph_item_problems'],
				'problemhosts' => $fields['problemhosts'],
				'severities' => $fields['severities'],
				'problem_name' => $fields['problem_name'],
				'evaltype' => $fields['evaltype'],
				'tags' => $fields['tags']
			],
			'overrides' => array_values($fields['or'])
		];

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
						'jQuery(".dashbrd-grid-container").dashboardGrid(\'refreshWidget\', "'.$uniqueid.'");'.
					'});';
			}
		}
		// Otherwise, set graph time period options.
		else {
			$range_time_parser = new CRangeTimeParser();

			$range_time_parser->parse($fields['time_from']);
			$graph_data['time_period']['time_from'] = $range_time_parser->getDateTime(true)->getTimestamp();

			$range_time_parser->parse($fields['time_to']);
			$graph_data['time_period']['time_to'] = $range_time_parser->getDateTime(false)->getTimestamp();
		}

		$svg_data = CSvgGraphHelper::get($graph_data, $width, $height);
		if ($svg_data['errors']) {
			error($svg_data['errors']);
		}

		if (!$preview) {
			$graph_options = zbx_array_merge($svg_data['data'], [
				'sbox' => ($graph_data['dashboard_time'] && !$edit_mode),
				'show_problems' => ($fields['show_problems'] == SVG_GRAPH_PROBLEMS_SHOW),
				'time_from' => $graph_data['time_period']['time_from'],
				'hint_max_rows' => ZBX_WIDGET_ROWS
			]);

			$script_inline .=
				'var widget = jQuery(".dashbrd-grid-container")'.
						'.dashboardGrid(\'getWidgetsBy\', \'uniqueid\', "'.$uniqueid.'");'.
				'jQuery(\'svg\', widget[0]["content_body"]).svggraph('.CJs::encodeJson($graph_options).', widget[0]);';
		}

		if ($initial_load) {
			// Register widget auto-refresh when resizing widget.
			$script_inline .=
				'jQuery(".dashbrd-grid-container").dashboardGrid("addAction", "onResizeEnd",'.
					'"zbx_svggraph_widget_trigger", "'.$uniqueid.'", {'.
						'parameters: ["onResizeEnd"],'.
						'grid: {widget: 1},'.
						'trigger_name: "svggraph_widget_resize_end_'.$uniqueid.'"'.
					'});';

			// Disable SBox when switch to edit mode.
			$script_inline .=
				'jQuery(".dashbrd-grid-container").dashboardGrid("addAction", "onEditStart",'.
					'"zbx_svggraph_widget_trigger", "'.$uniqueid.'", {'.
						'parameters: ["onEditStart"],'.
						'grid: {widget: 1},'.
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
