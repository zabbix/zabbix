<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace Widgets\PieChart\Actions;

use CControllerDashboardWidgetView,
	CControllerResponseData,
	CRangeTimeParser;

use Widgets\PieChart\Includes\WidgetForm;

class WidgetView extends CControllerDashboardWidgetView {

	private const PIE_CHART_WIDTH_MIN = 1;
	private const PIE_CHART_WIDTH_MAX = 65535;
	private const PIE_CHART_HEIGHT_MIN = 1;
	private const PIE_CHART_HEIGHT_MAX = 65535;

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'edit_mode' => 'in 0,1',
			//'from' => 'string',
			//'to' => 'string'
		]);
	}

	protected function doAction(): void {
		$edit_mode = $this->getInput('edit_mode', 0);
		$width = self::PIE_CHART_WIDTH_MAX;
		$height = self::PIE_CHART_HEIGHT_MAX;

		$dashboard_time = !WidgetForm::hasOverrideTime($this->fields_values);

		/*if ($dashboard_time) {
			$from = $this->getInput('from') ?: 'now-1h';
			$to = $this->getInput('to') ?: 'now';
		}
		else {
			$from = $this->fields_values['time_from'];
			$to = $this->fields_values['time_to'];
		}*/
		$from = $this->fields_values['time_from'];
		$to = $this->fields_values['time_to'];

		$range_time_parser = new CRangeTimeParser();

		$range_time_parser->parse($from);
		$time_from = $range_time_parser->getDateTime(true)->getTimestamp();

		$range_time_parser->parse($to);
		$time_to = $range_time_parser->getDateTime(false)->getTimestamp();

		$pie_chart_data = [
			'data_sets' => array_values($this->fields_values['ds']),
			'data_source' => $this->fields_values['source'],
			'dashboard_time' => $dashboard_time,
			'displaying' => [
				'draw_type' => $this->fields_values['draw_type'] == PIE_CHART_DRAW_DOUGHNUT,
				'width' => $this->fields_values['draw_type'] == PIE_CHART_DRAW_DOUGHNUT
					? $this->fields_values['width']
					: null,
				'stroke' => $this->fields_values['stroke'],
				'space' => $this->fields_values['space'],
				'merge' => $this->fields_values['merge'] == PIE_CHART_MERGE_ON,
				'merge_percent' => $this->fields_values['merge'] == PIE_CHART_MERGE_ON
					? $this->fields_values['merge_percent']
					: null,
				'merge_color' => $this->fields_values['merge'] == PIE_CHART_MERGE_ON
					? $this->fields_values['merge_color']
					: null,
				'total_show' => $this->fields_values['draw_type'] == PIE_CHART_DRAW_DOUGHNUT
					? $this->fields_values['total_show'] == PIE_CHART_SHOW_TOTAL_ON
					: null,
				'value_size' => $this->fields_values['total_show'] == PIE_CHART_SHOW_TOTAL_ON
					? $this->fields_values['value_size']
					: null,
				'decimal_places' => $this->fields_values['total_show'] == PIE_CHART_SHOW_TOTAL_ON
					? $this->fields_values['decimal_places']
					: null,
				'units_show' => $this->fields_values['total_show'] == PIE_CHART_SHOW_TOTAL_ON
					? $this->fields_values['units_show'] == PIE_CHART_SHOW_UNITS_ON
					: null,
				'units' => $this->fields_values['units_show'] == PIE_CHART_SHOW_UNITS_ON
					? $this->fields_values['units']
					: null,
				'value_bold' => $this->fields_values['total_show'] == PIE_CHART_SHOW_TOTAL_ON
					? $this->fields_values['value_bold'] == PIE_CHART_VALUE_BOLD_ON
					: null,
				'value_color' => $this->fields_values['total_show'] == PIE_CHART_SHOW_TOTAL_ON
					? $this->fields_values['value_color']
					: null,
			],
			'time_period' => [
				'time_from' => $time_from,
				'time_to' => $time_to
			],
			'legend' => [
				'show_legend' => $this->fields_values['legend'] == PIE_CHART_LEGEND_ON,
				'legend_columns' => $this->fields_values['legend_columns'],
				'legend_lines' => $this->fields_values['legend_lines']
			],
			'templateid' => $this->getInput('templateid', ''),
		];

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'info' => $this->makeWidgetInfo(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	private function makeWidgetInfo(): array {
		$info = [];

		if (WidgetForm::hasOverrideTime($this->fields_values)) {
			$info[] = [
				'icon' => 'btn-info-clock',
				'hint' => relativeDateToText($this->fields_values['time_from'], $this->fields_values['time_to'])
			];
		}

		return $info;
	}
}
