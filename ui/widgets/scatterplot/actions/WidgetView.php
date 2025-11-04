<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


namespace Widgets\ScatterPlot\Actions;

use CControllerDashboardWidgetView,
	CControllerResponseData,
	CNumberParser,
	CParser;

use Widgets\ScatterPlot\Includes\{
	CScatterPlotHelper,
	CWidgetFieldDataSet,
	WidgetForm
};

class WidgetView extends CControllerDashboardWidgetView {

	private const GRAPH_WIDTH_MIN = 1;
	private const GRAPH_WIDTH_MAX = 65535;
	private const GRAPH_HEIGHT_MIN = 1;
	private const GRAPH_HEIGHT_MAX = 65535;

	private const MARKER_SIZES = [
		CWidgetFieldDataSet::DATASET_MARKER_SIZE_SMALL => 6,
		CWidgetFieldDataSet::DATASET_MARKER_SIZE_MEDIUM => 9,
		CWidgetFieldDataSet::DATASET_MARKER_SIZE_LARGE => 12
	];

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'contents_width' => 'int32|ge '.self::GRAPH_WIDTH_MIN.'|le '.self::GRAPH_WIDTH_MAX,
			'contents_height' => 'int32|ge '.self::GRAPH_HEIGHT_MIN.'|le '.self::GRAPH_HEIGHT_MAX,
			'has_custom_time_period' => 'in 1',
			'preview' => 'in 1'
		]);
	}

	protected function doAction(): void {
		$width = (int) $this->getInput('contents_width', self::GRAPH_WIDTH_MIN);
		$height = (int) $this->getInput('contents_height', self::GRAPH_HEIGHT_MIN);
		$has_custom_time_period = $this->hasInput('has_custom_time_period');
		$preview = $this->hasInput('preview'); // Configuration preview.

		$parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

		$x_axis_min = $parser->parse($this->fields_values['x_axis_min']) == CParser::PARSE_SUCCESS
			? $parser->calcValue()
			: null;

		$x_axis_max = $parser->parse($this->fields_values['x_axis_max']) == CParser::PARSE_SUCCESS
			? $parser->calcValue()
			: null;

		$y_axis_min = $parser->parse($this->fields_values['y_axis_min']) == CParser::PARSE_SUCCESS
			? $parser->calcValue()
			: null;

		$y_axis_max = $parser->parse($this->fields_values['y_axis_max']) == CParser::PARSE_SUCCESS
			? $parser->calcValue()
			: null;

		$data_sets = array_values($this->fields_values['ds']);

		foreach ($data_sets as &$data_set) {
			$data_set['marker_size'] = array_key_exists($data_set['marker_size'], self::MARKER_SIZES)
				? self::MARKER_SIZES[$data_set['marker_size']]
				: self::MARKER_SIZES[CWidgetFieldDataSet::DATASET_MARKER_SIZE_SMALL];

			foreach ($data_set['host_tags'] as $index => $tag) {
				if ($tag['tag'] === '' && $tag['value'] === '') {
					unset($data_set['host_tags'][$index]);
				}
			}
		}
		unset($data_set);

		$graph_data = [
			'data_sets' => $data_sets,
			'data_source' => $this->fields_values['source'],
			'fix_time_period' => ($this->isTemplateDashboard() && !$this->fields_values['override_hostid'])
				|| $has_custom_time_period,
			'time_period' => [
				'time_from' => $this->fields_values['time_period']['from_ts'],
				'time_to' => $this->fields_values['time_period']['to_ts']
			],
			'grouped_thresholds' => $this->prepareGroupedThresholds($this->fields_values['thresholds']),
			'interpolation' => (bool) $this->fields_values['interpolation'],
			'axes' => [
				'show_x_axis' => $this->fields_values['x_axis'] == SVG_GRAPH_AXIS_ON,
				'x_axis_min' => $x_axis_min,
				'x_axis_max' => $x_axis_max,
				'x_axis_units' => $this->fields_values['x_axis_units'] == SVG_GRAPH_AXIS_UNITS_STATIC
					? $this->fields_values['x_axis_static_units']
					: null,
				'show_y_axis' => $this->fields_values['y_axis'] == SVG_GRAPH_AXIS_ON,
				'y_axis_min' => $y_axis_min,
				'y_axis_max' => $y_axis_max,
				'y_axis_units' => $this->fields_values['y_axis_units'] == SVG_GRAPH_AXIS_UNITS_STATIC
					? $this->fields_values['y_axis_static_units']
					: null
			],
			'legend' => [
				'show_legend' => $this->fields_values['legend'] == WidgetForm::LEGEND_ON,
				'legend_columns' => $this->fields_values['legend_columns'],
				'legend_lines' => $this->fields_values['legend_lines'],
				'legend_lines_mode' => $this->fields_values['legend_lines_mode'],
				'show_aggregation' => $this->fields_values['legend_aggregation'] == WidgetForm::LEGEND_AGGREGATION_ON
			],
			'templateid' => $this->getInput('templateid', ''),
			'override_hostid' => $this->fields_values['override_hostid']
				? $this->fields_values['override_hostid'][0]
				: ''
		];

		$svg_options = CScatterPlotHelper::get($graph_data, $width, $height);
		if ($svg_options['errors']) {
			error($svg_options['errors']);
		}

		if (!$preview) {
			$svg_options['data'] = zbx_array_merge($svg_options['data'], [
				'time_period' => $this->fields_values['time_period'],
				'hint_max_rows' => ZBX_WIDGET_ROWS
			]);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'svg' => $svg_options['svg'].$svg_options['legend'],
			'svg_options' => $svg_options,
			'preview' => $preview,
			'info' => $this->makeWidgetInfo(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	private function prepareGroupedThresholds(array $thresholds): array {
		$grouped_thresholds = [];

		$parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);
		$binary_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true,
			'is_binary' => true
		]);

		foreach ($thresholds as $threshold) {
			$threshold_parsed_values = [];

			if ($parser->parse($threshold['x_axis_threshold']) === CParser::PARSE_SUCCESS) {
				$threshold_parsed_values['x'] = $parser->calcValue();

				if ($binary_parser->parse($threshold['x_axis_threshold']) === CParser::PARSE_SUCCESS) {
					$threshold_parsed_values['x_binary'] = $binary_parser->calcValue();
				}
			}

			if ($parser->parse($threshold['y_axis_threshold']) === CParser::PARSE_SUCCESS) {
				$threshold_parsed_values['y'] = $parser->calcValue();

				if ($binary_parser->parse($threshold['y_axis_threshold']) === CParser::PARSE_SUCCESS) {
					$threshold_parsed_values['y_binary'] = $binary_parser->calcValue();
				}
			}

			$threshold = $threshold_parsed_values + [
				'color' => '#'.$threshold['color']
			];

			if (array_key_exists('x', $threshold) && array_key_exists('y', $threshold)) {
				$grouped_thresholds['both'][] = $threshold;
			}
			elseif (array_key_exists('x', $threshold)) {
				$grouped_thresholds['only_x'][] = $threshold;
			}
			elseif (array_key_exists('y', $threshold)) {
				$grouped_thresholds['only_y'][] = $threshold;
			}
		}

		return $grouped_thresholds;
	}

	/**
	 * Make widget specific info to show in widget's header.
	 */
	private function makeWidgetInfo(): array {
		$info = [];

		if ($this->hasInput('has_custom_time_period')) {
			$info[] = [
				'icon' => ZBX_ICON_TIME_PERIOD,
				'hint' => relativeDateToText($this->fields_values['time_period']['from'],
					$this->fields_values['time_period']['to']
				)
			];
		}

		return $info;
	}
}
