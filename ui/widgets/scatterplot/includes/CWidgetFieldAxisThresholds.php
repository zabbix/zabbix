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


namespace Widgets\ScatterPlot\Includes;

use CNumberParser,
	CParser;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldAxisThresholds extends CWidgetField {

	public const DEFAULT_VIEW = CWidgetFieldAxisThresholdsView::class;
	public const DEFAULT_VALUE = [];

	private bool $is_binary_units;

	/**
	 * Create widget field for Axis thresholds selection.
	 */
	public function __construct(string $name, ?string $label = null, bool $is_binary_units = false) {
		parent::__construct($name, $label);

		$this->is_binary_units = $is_binary_units;

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setValidationRules(['type' =>  API_OBJECTS, 'uniq' => [['threshold']], 'fields' => [
				'color'		=> ['type' => API_COLOR, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'x_axis_threshold'	=> ['type' => API_NUMERIC, 'flags' => API_REQUIRED],
				'y_axis_threshold'	=> ['type' => API_NUMERIC, 'flags' => API_REQUIRED]
			]]);
	}

	public function setValue($value): self {
		$thresholds = [];

		foreach ($value as $threshold) {
			$threshold['x_axis_threshold'] = trim($threshold['x_axis_threshold']);
			$threshold['y_axis_threshold'] = trim($threshold['y_axis_threshold']);

			if ($threshold['x_axis_threshold'] !== '' || $threshold['y_axis_threshold'] !== '') {
				$thresholds[] = $threshold;
			}
		}

		return parent::setValue($thresholds);
	}

	public function validate($strict = false): array {
		if ($errors = parent::validate($strict)) {
			return $errors;
		}

		$number_parser = new CNumberParser([
			'with_size_suffix' => true,
			'with_time_suffix' => true,
			'is_binary_size' => $this->is_binary_units
		]);

		$both_axes = [];
		$x_axis = [];
		$y_axis = [];

		foreach ($this->getValue() as $threshold) {
			$parsed_values = [];

			if ($number_parser->parse($threshold['x_axis_threshold']) === CParser::PARSE_SUCCESS) {
				$parsed_values['x_axis'] = $number_parser->calcValue();
			}

			if ($number_parser->parse($threshold['y_axis_threshold']) === CParser::PARSE_SUCCESS) {
				$parsed_values['y_axis'] = $number_parser->calcValue();
			}

			if (array_key_exists('x_axis', $parsed_values) && array_key_exists('y_axis', $parsed_values)) {
				$both_axes[] = $threshold + [
					'x_threshold_order' => $parsed_values['x_axis'],
					'y_threshold_order' => $parsed_values['y_axis']
				];
			}
			elseif (array_key_exists('x_axis', $parsed_values)) {
				$x_axis[] = ['x_threshold_order' => $parsed_values['x_axis']] + $threshold;
			}
			elseif (array_key_exists('y_axis', $parsed_values)) {
				$y_axis[] = ['y_threshold_order' => $parsed_values['y_axis']] + $threshold;
			}
		}

		usort($both_axes, static function(array $t1, array $t2): int {
			// First sort by X (ascending)
			if ($t1['x_threshold_order'] === $t2['x_threshold_order']) {
				// If X is the same, sort by Y (ascending)
				return $t1['y_threshold_order'] <=> $t2['y_threshold_order'];
			}

			return $t1['x_threshold_order'] <=> $t2['x_threshold_order'];
		});

		uasort($x_axis, static fn (array $t1, array $t2) => $t1['x_threshold_order'] <=> $t2['x_threshold_order']);
		uasort($y_axis,	static fn (array $t1, array $t2) => $t1['y_threshold_order'] <=> $t2['y_threshold_order']);

		$thresholds = array_merge($both_axes, $x_axis, $y_axis);

		foreach ($thresholds as &$threshold) {
			unset($threshold['x_threshold_order'], $threshold['y_threshold_order']);
		}
		unset($threshold);

		$thresholds = array_values($thresholds);

		$this->setValue($thresholds);

		return [];
	}

	public function toApi(array &$widget_fields = []): void {
		foreach ($this->getValue() as $index => $value) {
			$widget_fields[] = [
				'type' => $this->save_type,
				'name' => $this->name.'.'.$index.'.color',
				'value' => $value['color']
			];
			$widget_fields[] = [
				'type' => $this->save_type,
				'name' => $this->name.'.'.$index.'.x_axis_threshold',
				'value' => $value['x_axis_threshold']
			];
			$widget_fields[] = [
				'type' => $this->save_type,
				'name' => $this->name.'.'.$index.'.y_axis_threshold',
				'value' => $value['y_axis_threshold']
			];
		}
	}
}
