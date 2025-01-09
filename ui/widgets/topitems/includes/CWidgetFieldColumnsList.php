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


namespace Widgets\TopItems\Includes;

use CWidgetsData;
use DB;
use Zabbix\Widgets\CWidgetField;
use Zabbix\Widgets\Fields\CWidgetFieldTimePeriod;
use Zabbix\Widgets\Fields\CWidgetFieldSparkline;

class CWidgetFieldColumnsList extends CWidgetField {

	public const DEFAULT_VIEW = CWidgetFieldColumnsListView::class;
	public const DEFAULT_VALUE = [];

	// Column value display value as type.
	public const DISPLAY_VALUE_AS_NUMERIC = 1;
	public const DISPLAY_VALUE_AS_TEXT = 2;

	// Column value display type.
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


	// Predefined colors for thresholds. Each next threshold takes next sequential value from palette.
	public const THRESHOLDS_DEFAULT_COLOR_PALETTE = [
		'FF465C', 'FFD54F', '0EC9AC', '524BBC', 'ED1248', 'D1E754', '2AB5FF', '385CC7', 'EC1594', 'BAE37D',
		'6AC8FF', 'EE2B29', '3CA20D', '6F4BBC', '00A1FF', 'F3601B', '1CAE59', '45CFDB', '894BBC', '6D6D6D'
	];

	private array $fields_objects = [];

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this->setDefault(self::DEFAULT_VALUE);
	}

	public function getValue() {
		$field_value = parent::getValue();

		foreach ($field_value as $value_index => $value) {
			if (array_key_exists('item_tags', $value)) {
				foreach ($value['item_tags'] as $tag_index => $tag) {
					if ($tag['tag'] === '' && $tag['value'] === '') {
						unset($field_value[$value_index]['item_tags'][$tag_index]);
					}
				}
			}
		}

		return $field_value;
	}

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		$this->fields_objects = [];

		$columns_values = $this->getValue();

		foreach ($columns_values as $column_index => &$value) {
			$fields = [];

			if ($value['display_value_as'] == self::DISPLAY_VALUE_AS_NUMERIC
					&& $value['display'] == self::DISPLAY_SPARKLINE) {
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
			'item_tags_evaltype' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'base_color' => ZBX_WIDGET_FIELD_TYPE_STR,
			'display_value_as' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'display' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'min' => ZBX_WIDGET_FIELD_TYPE_STR,
			'max' => ZBX_WIDGET_FIELD_TYPE_STR,
			'decimal_places' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'aggregate_function' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'history' => ZBX_WIDGET_FIELD_TYPE_INT32
		];

		$column_defaults = [
			'items' => [],
			'item_tags_evaltype' => TAG_EVAL_TYPE_AND_OR,
			'item_tags' => [],
			'base_color' => '',
			'display_value_as' => CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC,
			'display' => CWidgetFieldColumnsList::DISPLAY_AS_IS,
			'min' => '',
			'max' => '',
			'highlights' => [],
			'thresholds' => [],
			'decimal_places' => CWidgetFieldColumnsList::DEFAULT_DECIMAL_PLACES,
			'aggregate_function' => AGGREGATE_NONE,
			'time_period' => [
				CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
					CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
				)
			],
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

			if (!array_key_exists('items', $value)) {
				$value['items'] = [];
			}

			if (!array_key_exists('thresholds', $value)) {
				$value['thresholds'] = [];
			}

			if (!array_key_exists('highlights', $value)) {
				$value['highlights'] = [];
			}

			if (!array_key_exists('item_tags', $value)) {
				$value['item_tags'] = [];
			}

			foreach ($value['items'] as $items_index => $pattern) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.'.$column_index.'.items.'.$items_index,
					'value' => $pattern
				];
			}

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

			foreach ($value['thresholds'] as $threshold_index => $threshold) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.'.$column_index.'.thresholds.'.$threshold_index.'.color',
					'value' => $threshold['color']
				];
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.'.$column_index.'.thresholds.'.$threshold_index.'.threshold',
					'value' => $threshold['threshold']
				];
			}

			foreach ($value['item_tags'] as $tag_index => $tag) {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$column_index.'.item_tags.'.$tag_index.'.tag',
					'value' => $tag['tag']
				];
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.'.$column_index.'.item_tags.'.$tag_index.'.operator',
					'value' => $tag['operator']
				];
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$column_index.'.item_tags.'.$tag_index.'.value',
					'value' => $tag['value']
				];
			}
		}

		foreach ($this->fields_objects as $field) {
			$field->toApi($widget_fields);
		}
	}

	protected function getValidationRules(bool $strict = false): array {
		$validation_rules = ['type' => API_OBJECTS, 'fields' => [
			'items'					=> ['type' => API_STRINGS_UTF8, 'default' => [], 'flags' => API_NOT_EMPTY],
			'item_tags_evaltype'	=> ['type' => API_INT32, 'in' => implode(',', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]), 'default' => TAG_EVAL_TYPE_AND_OR],
			'item_tags'				=> ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag'					=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('item_tag', 'tag')],
				'operator'				=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS])],
				'value'					=> ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('item_tag', 'value')]
			]],
			'aggregate_function'	=> ['type' => API_INT32, 'in' => implode(',', [AGGREGATE_NONE, AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST]), 'default' => AGGREGATE_NONE],
			'time_period'			=> ['type' => API_ANY],
			'display_value_as'		=> ['type' => API_INT32, 'in' => implode(',', [self::DISPLAY_VALUE_AS_NUMERIC, self::DISPLAY_VALUE_AS_TEXT]), 'default' => self::DISPLAY_VALUE_AS_NUMERIC],
			'display'				=> ['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'display_value_as', 'in' => self::DISPLAY_VALUE_AS_NUMERIC],
												'type' => API_INT32, 'default' => self::DISPLAY_AS_IS, 'in' => implode(',', [self::DISPLAY_AS_IS, self::DISPLAY_BAR, self::DISPLAY_INDICATORS, self::DISPLAY_SPARKLINE])],
											['else' => true,
												'type' => API_INT32]
			]],
			'history'				=> ['type' => API_INT32, 'default' => self::HISTORY_DATA_AUTO, 'in' => implode(',', [self::HISTORY_DATA_AUTO, self::HISTORY_DATA_HISTORY, self::HISTORY_DATA_TRENDS])],
			'sparkline'				=> ['type' => API_ANY],
			'base_color'			=> ['type' => API_COLOR, 'default' => ''],
			'min'					=> ['type' => API_NUMERIC, 'default' => ''],
			'max'					=> ['type' => API_NUMERIC, 'default' => ''],
			'decimal_places'		=> ['type' => API_INT32, 'in' => '0:10', 'default' => self::DEFAULT_DECIMAL_PLACES],
			'highlights'			=> ['type' =>  API_OBJECTS, 'uniq' => [['pattern']], 'fields' => [
				'color'					=> ['type' => API_COLOR, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'pattern'				=> ['type' => API_REGEX, 'flags' => API_REQUIRED | API_NOT_EMPTY]
			]],
			'thresholds'			=> ['type' =>  API_OBJECTS, 'uniq' => [['threshold']], 'fields' => [
				'color'					=> ['type' => API_COLOR, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'threshold'				=> ['type' => API_NUMERIC]
			]]
		]];

		if (($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0) {
			self::setValidationRuleFlag($validation_rules, API_NOT_EMPTY);
		}

		return $validation_rules;
	}
}
