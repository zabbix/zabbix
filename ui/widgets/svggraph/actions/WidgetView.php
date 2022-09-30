<?php declare(strict_types = 0);
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


namespace Widgets\SvgGraph\Actions;

use CControllerDashboardWidgetView,
	CControllerResponseData,
	CNumberParser,
	CParser,
	CRangeTimeParser,
	CSvgGraphHelper;

use Widgets\SvgGraph\Includes\WidgetForm;

class WidgetView extends CControllerDashboardWidgetView {

	private const GRAPH_WIDTH_MIN = 1;
	private const GRAPH_WIDTH_MAX = 65535;
	private const GRAPH_HEIGHT_MIN = 1;
	private const GRAPH_HEIGHT_MAX = 65535;

	public function __construct() {
		parent::__construct();

		$this->setValidationRules([
			'name' => 'string',
			'edit_mode' => 'in 0,1',
			'content_width' => 'int32|ge '.self::GRAPH_WIDTH_MIN.'|le '.self::GRAPH_WIDTH_MAX,
			'content_height' => 'int32|ge '.self::GRAPH_HEIGHT_MIN.'|le '.self::GRAPH_HEIGHT_MAX,
			'preview' => 'in 1',
			'from' => 'string',
			'to' => 'string',
			'fields' => 'required|array'
		]);
	}

	protected function doAction(): void {
		$values = $this->getForm()->getFieldsValues();

		$edit_mode = $this->getInput('edit_mode', 0);
		$width = (int) $this->getInput('content_width', self::GRAPH_WIDTH_MIN);
		$height = (int) $this->getInput('content_height', self::GRAPH_HEIGHT_MIN);
		$preview = (bool) $this->getInput('preview', 0); // Configuration preview.

		$dashboard_time = !WidgetForm::hasOverrideTime($values);

		if ($dashboard_time && !$preview) {
			$from = $this->getInput('from');
			$to = $this->getInput('to');
		}
		else {
			$from = $values['time_from'];
			$to = $values['time_to'];
		}

		$range_time_parser = new CRangeTimeParser();

		$range_time_parser->parse($from);
		$time_from = $range_time_parser->getDateTime(true)->getTimestamp();

		$range_time_parser->parse($to);
		$time_to = $range_time_parser->getDateTime(false)->getTimestamp();

		$parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

		$percentile_left_value = $parser->parse($values['percentile_left_value']) == CParser::PARSE_SUCCESS
			? $parser->calcValue()
			: null;

		$percentile_right_value = $parser->parse($values['percentile_right_value']) == CParser::PARSE_SUCCESS
			? $parser->calcValue()
			: null;

		$lefty_min = $parser->parse($values['lefty_min']) == CParser::PARSE_SUCCESS ? $parser->calcValue() : null;
		$lefty_max = $parser->parse($values['lefty_max']) == CParser::PARSE_SUCCESS ? $parser->calcValue() : null;
		$righty_min = $parser->parse($values['righty_min']) == CParser::PARSE_SUCCESS ? $parser->calcValue() : null;
		$righty_max = $parser->parse($values['righty_max']) == CParser::PARSE_SUCCESS ? $parser->calcValue() : null;

		$graph_data = [
			'data_sets' => array_values($values['ds']),
			'data_source' => $values['source'],
			'dashboard_time' => $dashboard_time,
			'displaying' => [
				'show_simple_triggers' => $values['simple_triggers'] == SVG_GRAPH_SIMPLE_TRIGGERS_ON,
				'show_working_time' => $values['working_time'] == SVG_GRAPH_WORKING_TIME_ON,
				'show_percentile_left' => $values['percentile_left'] == SVG_GRAPH_PERCENTILE_LEFT_ON,
				'percentile_left_value' => $percentile_left_value,
				'show_percentile_right' => $values['percentile_right'] == SVG_GRAPH_PERCENTILE_RIGHT_ON,
				'percentile_right_value' => $percentile_right_value
			],
			'time_period' => [
				'time_from' => $time_from,
				'time_to' => $time_to
			],
			'axes' => [
				'show_left_y_axis' => $values['lefty'] == SVG_GRAPH_AXIS_ON,
				'left_y_min' => $lefty_min,
				'left_y_max' => $lefty_max,
				'left_y_units' => $values['lefty_units'] == SVG_GRAPH_AXIS_UNITS_STATIC
					? $values['lefty_static_units']
					: null,
				'show_right_y_axis' => $values['righty'] == SVG_GRAPH_AXIS_ON,
				'right_y_min' => $righty_min,
				'right_y_max' => $righty_max,
				'right_y_units' => $values['righty_units'] == SVG_GRAPH_AXIS_UNITS_STATIC
					? $values['righty_static_units']
					: null,
				'show_x_axis' => $values['axisx'] == SVG_GRAPH_AXIS_ON
			],
			'legend' => [
				'show_legend' => $values['legend'] == SVG_GRAPH_LEGEND_ON,
				'legend_columns' => $values['legend_columns'],
				'legend_lines' => $values['legend_lines'],
				'legend_statistic' => $values['legend_statistic']
			],
			'problems' => [
				'show_problems' => $values['show_problems'] == SVG_GRAPH_PROBLEMS_ON,
				'graph_item_problems' => $values['graph_item_problems'] == SVG_GRAPH_SELECTED_ITEM_PROBLEMS,
				'problemhosts' => $values['problemhosts'],
				'severities' => $values['severities'],
				'problem_name' => $values['problem_name'],
				'evaltype' => $values['evaltype'],
				'tags' => $values['tags']
			],
			'overrides' => array_values($values['or'])
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
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'svg' => $svg_options['svg'].$svg_options['legend'],
			'svg_options' => $svg_options,
			'preview' => $preview,
			'info' => self::makeWidgetInfo($values),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	/**
	 * Make widget specific info to show in widget's header.
	 */
	private static function makeWidgetInfo(array $values): array {
		$info = [];

		if (WidgetForm::hasOverrideTime($values)) {
			$info[] = [
				'icon' => 'btn-info-clock',
				'hint' => relativeDateToText($values['time_from'], $values['time_to'])
			];
		}

		return $info;
	}
}
