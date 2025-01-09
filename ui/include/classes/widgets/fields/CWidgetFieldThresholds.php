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


namespace Zabbix\Widgets\Fields;

use CNumberParser,
	CParser;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldThresholds extends CWidgetField {

	public const DEFAULT_VIEW = \CWidgetFieldThresholdsView::class;
	public const DEFAULT_VALUE = [];

	private bool $is_binary_units;

	/**
	 * Create widget field for Thresholds selection.
	 */
	public function __construct(string $name, string $label = null, bool $is_binary_units = false) {
		parent::__construct($name, $label);

		$this->is_binary_units = $is_binary_units;

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setValidationRules(['type' =>  API_OBJECTS, 'uniq' => [['threshold']], 'fields' => [
				'color'		=> ['type' => API_COLOR, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'threshold'	=> ['type' => API_NUMERIC, 'flags' => API_REQUIRED]
			]]);
	}

	public function setValue($value): self {
		$thresholds = [];

		foreach ($value as $threshold) {
			$threshold['threshold'] = trim($threshold['threshold']);

			if ($threshold['threshold'] !== '') {
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

		$thresholds = [];

		foreach ($this->getValue() as $threshold) {
			if ($number_parser->parse($threshold['threshold']) === CParser::PARSE_SUCCESS) {
				$thresholds[] = $threshold + ['threshold_value' => $number_parser->calcValue()];
			}
		}

		uasort($thresholds,
			static function (array $threshold_1, array $threshold_2): int {
				return $threshold_1['threshold_value'] <=> $threshold_2['threshold_value'];
			}
		);

		foreach ($thresholds as &$threshold) {
			unset($threshold['threshold_value']);
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
				'name' => $this->name.'.'.$index.'.threshold',
				'value' => $value['threshold']
			];
		}
	}
}
