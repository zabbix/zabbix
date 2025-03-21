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


namespace Widgets\TopHosts\Includes;

use CWidgetsData;

use Zabbix\Widgets\CWidgetField;
use Zabbix\Widgets\Fields\CWidgetFieldTimePeriod;
use Zabbix\Widgets\Fields\CWidgetFieldSparkline;

class CWidgetFieldColumnsList extends CWidgetField {

	public const DEFAULT_VIEW = CWidgetFieldColumnsListView::class;
	public const DEFAULT_VALUE = [];

	// Source of value to display in column.
	public const DATA_ITEM_VALUE = 1;
	public const DATA_HOST_NAME = 2;
	public const DATA_TEXT = 3;

	// Item value column display as type.
	public const DISPLAY_VALUE_AS_NUMERIC = 0;
	public const DISPLAY_VALUE_AS_TEXT = 1;
	public const DISPLAY_VALUE_AS_BINARY = 2;

	// Numeric item value display type.
	public const DISPLAY_AS_IS = 1;
	public const DISPLAY_BAR = 2;
	public const DISPLAY_INDICATORS = 3;
	public const DISPLAY_SPARKLINE = 6;

	// Where to select data for aggregation function.
	public const HISTORY_DATA_AUTO = 0;
	public const HISTORY_DATA_HISTORY = 1;
	public const HISTORY_DATA_TRENDS = 2;

	public const DEFAULT_DECIMAL_PLACES = 2;

	public const SPARKLINE_DEFAULT = [
		'width'		=> 1,
		'fill'		=> 3,
		'color'		=> '42A5F5',
		'time_period' => [
			'data_source' => CWidgetFieldTimePeriod::DATA_SOURCE_DEFAULT,
			'from' => 'now-1h',
			'to' => 'now'
		],
		'history'	=> CWidgetFieldSparkline::DATA_SOURCE_AUTO
	];

	private array $fields_objects = [];

	public function __construct(string $name, ?string $label = null) {
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

		$this->fields_objects = [];

		$columns_values = $this->getValue();

		foreach ($columns_values as $column_index => &$value) {
			if ($value['data'] != self::DATA_ITEM_VALUE) {
				continue;
			}

			$fields = [];

			if ($value['display'] == self::DISPLAY_SPARKLINE) {
				$sparkline = (new CWidgetFieldSparkline($this->name.'.'.$column_index.'.sparkline', null,
					['color' => ['use_default' => false]]
				))
					->setInType(CWidgetsData::DATA_TYPE_TIME_PERIOD)
					->acceptDashboard()
					->setDefault(CWidgetFieldColumnsList::SPARKLINE_DEFAULT)
					->acceptWidget();

				if (array_key_exists('sparkline', $value)) {
					$sparkline->setValue($value['sparkline']);
				}

				$fields['sparkline'] = $sparkline;
			}

			if ($value['aggregate_function'] != AGGREGATE_NONE) {
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

				$fields['time_period'] = $time_period_field;
			}

			foreach ($fields as $i => $field) {
				$errors = $field->validate($strict);

				if ($errors) {
					return $errors;
				}

				$value[$i] = $field->getValue();

				$this->fields_objects[] = $field;
			}
		}
		unset($value);

		$this->setValue($columns_values);

		return [];
	}

	public function toApi(array &$widget_fields = []): void {
		$fields = [
			'name' => ZBX_WIDGET_FIELD_TYPE_STR,
			'data' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'text' => ZBX_WIDGET_FIELD_TYPE_STR,
			'item' => ZBX_WIDGET_FIELD_TYPE_STR,
			'base_color' => ZBX_WIDGET_FIELD_TYPE_STR,
			'display_value_as' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'display' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'min' => ZBX_WIDGET_FIELD_TYPE_STR,
			'max' => ZBX_WIDGET_FIELD_TYPE_STR,
			'decimal_places' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'show_thumbnail' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'aggregate_function' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'history' => ZBX_WIDGET_FIELD_TYPE_INT32
		];

		$column_defaults = [
			'base_color' => '',
			'display_value_as' => CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC,
			'display' => CWidgetFieldColumnsList::DISPLAY_AS_IS,
			'min' => '',
			'max' => '',
			'decimal_places' => CWidgetFieldColumnsList::DEFAULT_DECIMAL_PLACES,
			'show_thumbnail' => 0,
			'aggregate_function' => AGGREGATE_NONE,
			'history' => CWidgetFieldColumnsList::HISTORY_DATA_AUTO
		];

		foreach ($this->getValue() as $column_index => $value) {
			foreach (array_intersect_key($fields, $value) as $field => $field_type) {
				if (!array_key_exists($field, $column_defaults) || $column_defaults[$field] !== $value[$field]) {
					$widget_fields[] = [
						'type' => $field_type,
						'name' => $this->name.'.'.$column_index.'.'.$field,
						'value' => $value[$field]
					];
				}
			}

			if (array_key_exists('thresholds', $value)) {
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

			if (array_key_exists('highlights', $value)) {
				foreach ($value['highlights'] as $highlight_index => $highlight) {
					$widget_fields[] = [
						'type' => ZBX_WIDGET_FIELD_TYPE_STR,
						'name' => $this->name.'.'.$column_index.'.highlights.'.$highlight_index.'.color',
						'value' => $highlight['color']
					];

					$widget_fields[] = [
						'type' => ZBX_WIDGET_FIELD_TYPE_STR,
						'name' => $this->name.'.'.$column_index.'.highlights.'.$highlight_index.'.pattern',
						'value' => $highlight['pattern']
					];
				}
			}
		}

		foreach ($this->fields_objects as $field) {
			$field->toApi($widget_fields);
		}
	}

	protected function getValidationRules(bool $strict = false): array {
		$validation_rules = ['type' => API_OBJECTS, 'fields' => [
			'name'					=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255],
			'data'					=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [self::DATA_ITEM_VALUE, self::DATA_HOST_NAME, self::DATA_TEXT])],
			'text'					=> ['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'data', 'in' => self::DATA_TEXT],
											'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255],
										['else' => true,
											'type' => API_STRING_UTF8]
			]],
			'item'					=> ['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'data', 'in' => self::DATA_ITEM_VALUE],
												'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255],
											['else' => true,
												'type' => API_STRING_UTF8]
			]],
			'base_color'			=> ['type' => API_COLOR, 'default' => ''],
			'display_value_as'		=> ['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'data', 'in' => self::DATA_ITEM_VALUE],
											'type' => API_INT32, 'default' => self::DISPLAY_VALUE_AS_NUMERIC, 'in' => implode(',', [self::DISPLAY_VALUE_AS_NUMERIC, self::DISPLAY_VALUE_AS_TEXT, self::DISPLAY_VALUE_AS_BINARY])],
										['else' => true,
											'type' => API_INT32]
			]],
			'display'				=> ['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'data', 'in' => self::DATA_ITEM_VALUE],
												'type' => API_INT32, 'default' => self::DISPLAY_AS_IS, 'in' => implode(',', [self::DISPLAY_AS_IS, self::DISPLAY_BAR, self::DISPLAY_INDICATORS, self::DISPLAY_SPARKLINE])],
											['else' => true,
												'type' => API_INT32]
			]],
			'sparkline'				=> ['type' => API_ANY],
			'min'					=> ['type' => API_NUMERIC],
			'max'					=> ['type' => API_NUMERIC],
			'decimal_places'		=> ['type' => API_INT32, 'in' => '0:10', 'default' => self::DEFAULT_DECIMAL_PLACES],
			'thresholds'			=> ['type' =>  API_OBJECTS, 'uniq' => [['threshold']], 'fields' => [
				'color'					=> ['type' => API_COLOR, 'flags' => API_REQUIRED],
				'threshold'				=> ['type' => API_NUMERIC, 'flags' => API_REQUIRED]
			]],
			'highlights'			=> ['type' =>  API_OBJECTS, 'uniq' => [['pattern']], 'fields' => [
				'color'					=> ['type' => API_COLOR, 'flags' => API_REQUIRED],
				'pattern'				=> ['type' => API_REGEX, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255]
			]],
			'show_thumbnail'		=> ['type' => API_INT32, 'default' => 0, 'in' => '0,1'],
			'aggregate_function'	=> ['type' => API_INT32, 'in' => implode(',', [AGGREGATE_NONE, AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST]), 'default' => AGGREGATE_NONE],
			'time_period'			=> ['type' => API_ANY],
			'history'				=> ['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'data', 'in' => self::DATA_ITEM_VALUE],
											'type' => API_INT32, 'default' => self::HISTORY_DATA_AUTO, 'in' => implode(',', [self::HISTORY_DATA_AUTO, self::HISTORY_DATA_HISTORY, self::HISTORY_DATA_TRENDS])],
										['else' => true,
											'type' => API_INT32]
			]]
		]];

		if (($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0) {
			self::setValidationRuleFlag($validation_rules, API_NOT_EMPTY);
		}

		return $validation_rules;
	}
}
