<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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

use CWidgetsData;
use Zabbix\Widgets\CWidgetField;

class CWidgetFieldColumnsList extends CWidgetField {

	public const DEFAULT_VIEW = \CWidgetFieldColumnsListView::class;
	public const DEFAULT_VALUE = [];

	// Source of value to display in column.
	public const DATA_ITEM_VALUE = 1;
	public const DATA_HOST_NAME = 2;
	public const DATA_TEXT = 3;

	// Column value display type.
	public const DISPLAY_AS_IS = 1;
	public const DISPLAY_BAR = 2;
	public const DISPLAY_INDICATORS = 3;

	// Where to select data for aggregation function.
	public const HISTORY_DATA_AUTO = 1;
	public const HISTORY_DATA_HISTORY = 2;
	public const HISTORY_DATA_TRENDS = 3;

	public const DEFAULT_DECIMAL_PLACES = 2;

	// Predefined colors for thresholds. Each next threshold takes next sequential value from palette.
	public const THRESHOLDS_DEFAULT_COLOR_PALETTE = [
		'FF465C', 'FFD54F', '0EC9AC', '524BBC', 'ED1248', 'D1E754', '2AB5FF', '385CC7', 'EC1594', 'BAE37D',
		'6AC8FF', 'EE2B29', '3CA20D', '6F4BBC', '00A1FF', 'F3601B', '1CAE59', '45CFDB', '894BBC', '6D6D6D'
	];

	private array $time_period_fields = [];

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this->setDefault(self::DEFAULT_VALUE);
	}

	public function setValue($value): self {
		$this->value = (array) $value;

		return $this;
	}

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		$this->time_period_fields = [];

		$columns_values = $this->getValue();

		foreach ($columns_values as $column_index => &$value) {
			if ($value['data'] != self::DATA_ITEM_VALUE || $value['aggregate_function'] == AGGREGATE_NONE) {
				continue;
			}

			$time_period_field = (new CWidgetFieldTimePeriod($this->name.'.'.$column_index.'.time_period',
				'/'.($column_index + 1)
			))
				->setDefault([
					CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
						CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
					)
				])
				->setInType(CWidgetsData::DATA_TYPE_TIME_PERIOD)
				->acceptDashboard()
				->acceptWidget()
				->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK);

			if (array_key_exists('time_period', $value)) {
				$time_period_field->setValue($value['time_period']);
			}

			$errors = $time_period_field->validate($strict);

			if ($errors) {
				return $errors;
			}

			$value['time_period'] = $time_period_field->getValue();

			$this->time_period_fields[] = $time_period_field;
		}
		unset($value);

		$this->setValue($columns_values);

		return [];
	}

	public function toApi(array &$widget_fields = []): void {
		$fields = [
			'name' => ZBX_WIDGET_FIELD_TYPE_STR,
			'data' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'item' => ZBX_WIDGET_FIELD_TYPE_STR,
			'aggregate_function' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'min' => ZBX_WIDGET_FIELD_TYPE_STR,
			'max' => ZBX_WIDGET_FIELD_TYPE_STR,
			'decimal_places' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'display' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'history' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'base_color' => ZBX_WIDGET_FIELD_TYPE_STR,
			'text' => ZBX_WIDGET_FIELD_TYPE_STR
		];

		foreach ($this->getValue() as $column_index => $value) {
			foreach (array_intersect_key($fields, $value) as $field => $field_type) {
				$widget_fields[] = [
					'type' => $field_type,
					'name' => $this->name.'.'.$column_index.'.'.$field,
					'value' => $value[$field]
				];
			}

			if (!array_key_exists('thresholds', $value) || !$value['thresholds']) {
				continue;
			}

			foreach ($value['thresholds'] as $threshold_index => $threshold) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'thresholds.'.$column_index.'.color.'.$threshold_index,
					'value' => $threshold['color']
				];
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'thresholds.'.$column_index.'.threshold.'.$threshold_index,
					'value' => $threshold['threshold']
				];
			}
		}

		foreach ($this->time_period_fields as $time_period_field) {
			$time_period_field->toApi($widget_fields);
		}
	}

	protected function getValidationRules(bool $strict = false): array {
		$validation_rules = ['type' => API_OBJECTS, 'fields' => [
			'name'					=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255],
			'data'					=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [self::DATA_ITEM_VALUE, self::DATA_HOST_NAME, self::DATA_TEXT])],
			'item'					=> ['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'data', 'in' => self::DATA_ITEM_VALUE],
												'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
											['else' => true,
												'type' => API_STRING_UTF8]
			]],
			'aggregate_function'	=> ['type' => API_INT32, 'in' => implode(',', [AGGREGATE_NONE, AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST]), 'default' => AGGREGATE_NONE],
			'time_period'			=> ['type' => API_ANY],
			'display'				=> ['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'data', 'in' => self::DATA_ITEM_VALUE],
												'type' => API_INT32, 'default' => self::DISPLAY_AS_IS, 'in' => implode(',', [self::DISPLAY_AS_IS, self::DISPLAY_BAR, self::DISPLAY_INDICATORS])],
											['else' => true,
												'type' => API_INT32]
			]],
			'history'				=> ['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'data', 'in' => self::DATA_ITEM_VALUE],
												'type' => API_INT32, 'default' => self::HISTORY_DATA_AUTO, 'in' => implode(',', [self::HISTORY_DATA_AUTO, self::HISTORY_DATA_HISTORY, self::HISTORY_DATA_TRENDS])],
											['else' => true,
												'type' => API_INT32]
			]],
			'base_color'			=> ['type' => API_COLOR],
			'min'					=> ['type' => API_NUMERIC],
			'max'					=> ['type' => API_NUMERIC],
			'decimal_places'		=> ['type' => API_INT32, 'in' => '0:10', 'default' => self::DEFAULT_DECIMAL_PLACES],
			'thresholds'			=> ['type' =>  API_OBJECTS, 'uniq' => [['threshold']], 'fields' => [
				'color'					=> ['type' => API_COLOR],
				'threshold'				=> ['type' => API_NUMERIC]
			]],
			'text'					=> ['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'data', 'in' => self::DATA_TEXT],
												'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255],
											['else' => true,
												'type' => API_STRING_UTF8]
			]]
		]];

		if (($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0) {
			self::setValidationRuleFlag($validation_rules, API_NOT_EMPTY);
		}

		return $validation_rules;
	}
}
