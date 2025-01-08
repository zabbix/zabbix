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


namespace Widgets\ItemHistory\Includes;

use API;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldColumnsList extends CWidgetField {

	public const DEFAULT_VIEW = CWidgetFieldColumnsListView::class;
	public const DEFAULT_VALUE = [];

	// Column value display type.
	public const DISPLAY_AS_IS = 1;
	public const DISPLAY_BAR = 2;
	public const DISPLAY_INDICATORS = 3;
	public const DISPLAY_HTML = 4;
	public const DISPLAY_SINGLE_LINE = 5;

	// Data source for numeric items.
	public const HISTORY_DATA_AUTO = 0;
	public const HISTORY_DATA_HISTORY = 1;
	public const HISTORY_DATA_TRENDS = 2;

	// Display single line values.
	public const SINGLE_LINE_LENGTH_MIN = 1;
	public const SINGLE_LINE_LENGTH_MAX = 500;
	public const SINGLE_LINE_LENGTH_DEFAULT = 100;

	public function __construct(string $name, ?string $label = null) {
		parent::__construct($name, $label);

		$this->setDefault(self::DEFAULT_VALUE);
	}

	public function getItemNames(array $itemids): array {
		$items = $itemids
			? API::Item()->get([
				'output' => $this->isTemplateDashboard() ? ['name'] : ['name_resolved'],
				'itemids' => $itemids,
				'selectHosts' => $this->isTemplateDashboard() ? null : ['name'],
				'webitems' => true,
				'preservekeys' => true
			])
			: [];

		$items_names = [];

		foreach ($items as $itemid => $item) {
			$items_names[$itemid] = $this->isTemplateDashboard()
				? $item['name']
				: $item['hosts'][0]['name'].NAME_DELIMITER.$item['name_resolved'];
		}

		return $items_names;
	}

	public function setValue($value): self {
		$columns = (array) $value;

		$columns = $columns
			? array_filter($columns, static function ($column) {
				return array_key_exists('itemid', $column) && $column['itemid'] !== '';
			})
			: [];

		$this->value = $columns;

		return $this;
	}

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		$columns_values = $this->getValue();

		$this->setValue($columns_values);

		return [];
	}

	public function toApi(array &$widget_fields = []): void {
		$fields = [
			'name' => ZBX_WIDGET_FIELD_TYPE_STR,
			'itemid' => ZBX_WIDGET_FIELD_TYPE_ITEM,
			'base_color' => ZBX_WIDGET_FIELD_TYPE_STR,
			'display' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'max_length' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'min' => ZBX_WIDGET_FIELD_TYPE_STR,
			'max' => ZBX_WIDGET_FIELD_TYPE_STR,
			'history' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'monospace_font' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'local_time' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'show_thumbnail' => ZBX_WIDGET_FIELD_TYPE_INT32
		];

		$column_defaults = [
			'base_color' => '',
			'display' => CWidgetFieldColumnsList::DISPLAY_AS_IS,
			'min' => '',
			'max' => '',
			'max_length' => CWidgetFieldColumnsList::SINGLE_LINE_LENGTH_DEFAULT,
			'history' => CWidgetFieldColumnsList::HISTORY_DATA_AUTO,
			'monospace_font' => 0,
			'local_time' => 0,
			'show_thumbnail' => 0
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

			if (array_key_exists('highlights', $value) && $value['highlights']) {
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

			if (array_key_exists('thresholds', $value) && $value['thresholds']) {
				foreach ($value['thresholds'] as $threshold_index => $threshold) {
					$widget_fields[] = [
						'type' => ZBX_WIDGET_FIELD_TYPE_STR,
						'name' => $this->name.'.'.$column_index.'.thresholds.'.$threshold_index.'.color',
						'value' => $threshold['color']
					];

					$widget_fields[] = [
						'type' => ZBX_WIDGET_FIELD_TYPE_STR,
						'name' =>  $this->name.'.'.$column_index.'.thresholds.'.$threshold_index.'.threshold',
						'value' => $threshold['threshold']
					];
				}
			}
		}
	}

	protected function getValidationRules(bool $strict = false): array {
		$validation_rules = ['type' => API_OBJECTS, 'fields' => [
			'name'				=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255],
			'itemid'			=> ['type' => API_ID, 'flags' => API_REQUIRED],
			'base_color'		=> ['type' => API_COLOR, 'default' => ''],
			'highlights'		=> ['type' =>  API_OBJECTS, 'uniq' => [['pattern']], 'fields' => [
				'color'				=> ['type' => API_COLOR],
				'pattern'			=> ['type' => API_REGEX, 'flags' => API_NOT_EMPTY, 'length' => 255]
			]],
			'display'			=> ['type' => API_INT32, 'default' => self::DISPLAY_AS_IS, 'in' => implode(',', [self::DISPLAY_AS_IS, self::DISPLAY_BAR, self::DISPLAY_INDICATORS, self::DISPLAY_HTML, self::DISPLAY_SINGLE_LINE])],
			'max_length'		=> ['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'display', 'in' => self::DISPLAY_SINGLE_LINE],
											'type' => API_INT32, 'flags' => API_REQUIRED, 'default' => self::SINGLE_LINE_LENGTH_DEFAULT, 'in' => self::SINGLE_LINE_LENGTH_MIN.':'.self::SINGLE_LINE_LENGTH_MAX],
										['else' => true,
											'type' => API_INT32, 'in' => self::SINGLE_LINE_LENGTH_MIN.':'.self::SINGLE_LINE_LENGTH_MAX]
			]],
			'min'				=> ['type' => API_NUMERIC],
			'max'				=> ['type' => API_NUMERIC],
			'thresholds'		=> ['type' =>  API_OBJECTS, 'uniq' => [['threshold']], 'fields' => [
				'color'				=> ['type' => API_COLOR],
				'threshold'			=> ['type' => API_NUMERIC]
			]],
			'history'			=> ['type' => API_INT32, 'default' => self::HISTORY_DATA_AUTO, 'in' => implode(',', [self::HISTORY_DATA_AUTO, self::HISTORY_DATA_HISTORY, self::HISTORY_DATA_TRENDS])],
			'monospace_font' 	=> ['type' => API_INT32, 'default' => 0, 'in' => '0,1'],
			'local_time' 		=> ['type' => API_INT32, 'default' => 0, 'in' => '0,1'],
			'show_thumbnail'		=> ['type' => API_INT32, 'default' => 0, 'in' => '0,1']
		]];

		if (($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0) {
			self::setValidationRuleFlag($validation_rules, API_NOT_EMPTY);
		}

		return $validation_rules;
	}
}
