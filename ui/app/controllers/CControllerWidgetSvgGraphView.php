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
			'edit_mode' => 'in 0,1',
			'content_width' => 'int32|ge '.self::GRAPH_WIDTH_MIN.'|le '.self::GRAPH_WIDTH_MAX,
			'content_height' => 'int32|ge '.self::GRAPH_HEIGHT_MIN.'|le '.self::GRAPH_HEIGHT_MAX,
			'preview' => 'in 1',
			'from' => 'string',
			'to' => 'string',
			'fields' => 'json'
		]);
	}

	protected function doAction(): void {
		$fields = $this->getForm()->getFieldsData();
		$edit_mode = $this->getInput('edit_mode', 0);
		$width = (int) $this->getInput('content_width', self::GRAPH_WIDTH_MIN);
		$height = (int) $this->getInput('content_height', self::GRAPH_HEIGHT_MIN);
		$preview = (bool) $this->getInput('preview', 0); // Configuration preview.

		$dashboard_time = !CWidgetFormSvgGraph::hasOverrideTime($fields);

		if ($dashboard_time && !$preview) {
			$from = $this->getInput('from');
			$to = $this->getInput('to');
		}
		else {
			$from = $fields['time_from'];
			$to = $fields['time_to'];
		}

		$range_time_parser = new CRangeTimeParser();

		$range_time_parser->parse($from);
		$time_from = $range_time_parser->getDateTime(true)->getTimestamp();

		$range_time_parser->parse($to);
		$time_to = $range_time_parser->getDateTime(false)->getTimestamp();

		$parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

		$percentile_left_value = $parser->parse($fields['percentile_left_value']) == CParser::PARSE_SUCCESS
			? $parser->calcValue()
			: null;

		$percentile_right_value = $parser->parse($fields['percentile_right_value']) == CParser::PARSE_SUCCESS
			? $parser->calcValue()
			: null;

		$lefty_min = $parser->parse($fields['lefty_min']) == CParser::PARSE_SUCCESS ? $parser->calcValue() : null;
		$lefty_max = $parser->parse($fields['lefty_max']) == CParser::PARSE_SUCCESS ? $parser->calcValue() : null;
		$righty_min = $parser->parse($fields['righty_min']) == CParser::PARSE_SUCCESS ? $parser->calcValue() : null;
		$righty_max = $parser->parse($fields['righty_max']) == CParser::PARSE_SUCCESS ? $parser->calcValue() : null;

		$graph_data = [
			'data_sets' => array_values($fields['ds']),
			'data_source' => $fields['source'],
			'dashboard_time' => $dashboard_time,
			'displaying' => [
				'show_simple_triggers' => $fields['simple_triggers'] == SVG_GRAPH_SIMPLE_TRIGGERS_ON,
				'show_working_time' => $fields['working_time'] == SVG_GRAPH_WORKING_TIME_ON,
				'show_percentile_left' => $fields['percentile_left'] == SVG_GRAPH_PERCENTILE_LEFT_ON,
				'percentile_left_value' => $percentile_left_value,
				'show_percentile_right' => $fields['percentile_right'] == SVG_GRAPH_PERCENTILE_RIGHT_ON,
				'percentile_right_value' => $percentile_right_value
			],
			'time_period' => [
				'time_from' => $time_from,
				'time_to' => $time_to
			],
			'axes' => [
				'show_left_y_axis' => $fields['lefty'] == SVG_GRAPH_AXIS_SHOW,
				'left_y_min' => $lefty_min,
				'left_y_max' => $lefty_max,
				'left_y_units' => $fields['lefty_units'] == SVG_GRAPH_AXIS_UNITS_STATIC
					? $fields['lefty_static_units']
					: null,
				'show_right_y_axis' => $fields['righty'] == SVG_GRAPH_AXIS_SHOW,
				'right_y_min' => $righty_min,
				'right_y_max' => $righty_max,
				'right_y_units' => $fields['righty_units'] == SVG_GRAPH_AXIS_UNITS_STATIC
					? $fields['righty_static_units']
					: null,
				'show_x_axis' => $fields['axisx'] == SVG_GRAPH_AXIS_SHOW
			],
			'legend' => [
				'show_legend' => $fields['legend'] == SVG_GRAPH_LEGEND_ON,
				'legend_columns' => $fields['legend_columns'],
				'legend_lines' => $fields['legend_lines'],
				'legend_statistic' => $fields['legend_statistic']
			],
			'problems' => [
				'show_problems' => $fields['show_problems'] == SVG_GRAPH_PROBLEMS_SHOW,
				'graph_item_problems' => $fields['graph_item_problems'] == SVG_GRAPH_SELECTED_ITEM_PROBLEMS,
				'problemhosts' => $fields['problemhosts'],
				'severities' => $fields['severities'],
				'problem_name' => $fields['problem_name'],
				'evaltype' => $fields['evaltype'],
				'tags' => $fields['tags']
			],
			'overrides' => array_values($fields['or'])
		];

		$svg_options = CSvgGraphHelper::get($graph_data, $width, $height);
		if ($svg_options['errors']) {
			error($svg_options['errors']);
		}

		if (!$preview) {
			$svg_options['data'] = zbx_array_merge($svg_options['data'], [
				'sbox' => $graph_data['dashboard_time'] && !$edit_mode,
				'show_problems' => $graph_data['problems']['show_problems'],
				'show_simple_triggers' => $graph_data['displaying']['show_simple_triggers'],
				'time_from' => $graph_data['time_period']['time_from'],
				'hint_max_rows' => ZBX_WIDGET_ROWS
			]);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultName()),
			'svg' => $svg_options['svg'].$svg_options['legend'],
			'svg_options' => $svg_options,
			'preview' => $preview,
			'info' => self::makeWidgetInfo($fields),
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
