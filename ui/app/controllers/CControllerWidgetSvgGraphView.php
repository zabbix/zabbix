<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

		$parser = new CNumberParser(['with_suffix' => true]);
		$lefty_min = $parser->parse($fields['lefty_min']) == CParser::PARSE_SUCCESS ? $parser->calcValue() : '';
		$lefty_max = $parser->parse($fields['lefty_max']) == CParser::PARSE_SUCCESS ? $parser->calcValue() : '';
		$righty_min = $parser->parse($fields['righty_min']) == CParser::PARSE_SUCCESS ? $parser->calcValue() : '';
		$righty_max = $parser->parse($fields['righty_max']) == CParser::PARSE_SUCCESS ? $parser->calcValue() : '';

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
				'min' => $lefty_min,
				'max' => $lefty_max,
				'units' => ($fields['lefty_units'] == SVG_GRAPH_AXIS_UNITS_STATIC)
					? $fields['lefty_static_units']
					: null
			],
			'right_y_axis' => [
				'show' => $fields['righty'],
				'min' => $righty_min,
				'max' => $righty_max,
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
		}
		// Otherwise, set graph time period options.
		else {
			$range_time_parser = new CRangeTimeParser();

			$range_time_parser->parse($fields['time_from']);
			$graph_data['time_period']['time_from'] = $range_time_parser->getDateTime(true)->getTimestamp();

			$range_time_parser->parse($fields['time_to']);
			$graph_data['time_period']['time_to'] = $range_time_parser->getDateTime(false)->getTimestamp();
		}

		$svg_options = CSvgGraphHelper::get($graph_data, $width, $height);
		if ($svg_options['errors']) {
			error($svg_options['errors']);
		}

		if (!$preview) {
			$svg_options['data'] = zbx_array_merge($svg_options['data'], [
				'sbox' => ($graph_data['dashboard_time'] && !$edit_mode),
				'show_problems' => ($fields['show_problems'] == SVG_GRAPH_PROBLEMS_SHOW),
				'time_from' => $graph_data['time_period']['time_from'],
				'hint_max_rows' => ZBX_WIDGET_ROWS
			]);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'svg' => $svg_options['svg'].$svg_options['legend'],
			'svg_options' => $svg_options,
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
