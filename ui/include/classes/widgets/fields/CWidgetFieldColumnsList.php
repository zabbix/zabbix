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


class CWidgetFieldColumnsList extends CWidgetField {

	// Source of value to display in column.
	const DATA_ITEM_VALUE = 1;
	const DATA_HOST_NAME = 2;
	const DATA_TEXT = 3;

	// Value aggregation functions when source of value is set to DATA_ITEM_VALUE.
	const FUNC_NONE = 0;
	const FUNC_MIN = 1;
	const FUNC_MAX = 2;
	const FUNC_AVG = 3;
	const FUNC_LAST = 4;
	const FUNC_FIRST = 5;
	const FUNC_COUNT = 6;

	// Column value display type.
	const DISPLAY_AS_IS = 1;
	const DISPLAY_BAR = 2;
	const DISPLAY_INDICATORS = 3;

	// Where to select data for aggregation function.
	const HISTORY_DATA_AUTO = 1;
	const HISTORY_DATA_HISTORY = 2;
	const HISTORY_DATA_TRENDS = 3;

	// Predefined colors for thresholds. Each next threshold takes next sequential value from palette.
	const THRESHOLDS_DEFAULT_COLOR_PALETTE = [
		'FF465C','B0AF07','0EC9AC','524BBC','ED1248','D1E754','2AB5FF','385CC7','EC1594','BAE37D',
		'6AC8FF','EE2B29','3CA20D','6F4BBC','00A1FF','F3601B','1CAE59','45CFDB','894BBC','6D6D6D'
	];

	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setValidationRules(['type' => API_OBJECTS, 'fields' => [
			'name'			=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
			'data'			=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => self::DATA_ITEM_VALUE.':'.self::DATA_TEXT],
			'item'			=> ['type' => API_STRING_UTF8, 'default' => '', 'length' => 255],
			'function'		=> ['type' => API_INT32, 'in' => self::FUNC_NONE.':'.self::FUNC_COUNT],
			'from'			=> ['type' => API_RANGE_TIME],
			'to'			=> ['type' => API_RANGE_TIME],
			'display'		=> ['type' => API_INT32, 'in' => self::DISPLAY_AS_IS.':'.self::DISPLAY_INDICATORS],
			'history'		=> ['type' => API_INT32, 'in' => self::HISTORY_DATA_AUTO.':'.self::HISTORY_DATA_TRENDS],
			'base_color'	=> ['type' => API_COLOR],
			'min'			=> ['type' => API_NUMERIC],
			'max'			=> ['type' => API_NUMERIC],
			'thresholds'	=> ['type' =>  API_OBJECTS, 'fields' => [
				'color'			=> ['type' => API_COLOR],
				'threshold'		=> ['type' => API_NUMERIC]
			]],
			'text'			=> ['type' => API_STRING_UTF8, 'length' => 255]
		]]);
		$this->setDefault([[
			'name' => '',
			'data' => self::DATA_HOST_NAME,
			'item' => '',
			'function'	=> self::FUNC_NONE,
			'display' => self::DISPLAY_AS_IS,
			'history' => self::HISTORY_DATA_AUTO,
			'from' => 'now-1h',
			'to'	=> 'now',
			'min'	=> '',
			'max'	=> '',
			'base_color' => '',
			'text' => '',
			'thresholds' => []
		]]);
	}

	public function setValue($value) {
		$this->value = (array) $value;

		return $this;
	}

	/**
	 * Prepares array entry for widget field, ready to be passed to CDashboard API functions.
	 * Reference is needed here to avoid array merging in CWidgetForm::fieldsToApi method. With large number of widget
	 * fields it causes significant performance decrease.
	 *
	 * @param array $widget_fields   reference to Array of widget fields.
	 */
	public function toApi(array &$widget_fields = []) {
		$fields = [
			'name' => ZBX_WIDGET_FIELD_TYPE_STR,
			'data' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'item' => ZBX_WIDGET_FIELD_TYPE_STR,
			'function' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'from' => ZBX_WIDGET_FIELD_TYPE_STR,
			'to' => ZBX_WIDGET_FIELD_TYPE_STR,
			'display' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'history' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'base_color' => ZBX_WIDGET_FIELD_TYPE_STR,
			'text' => ZBX_WIDGET_FIELD_TYPE_STR
		];

		foreach ($this->getValue() as $index => $val) {
			foreach (array_intersect_key($fields, $val) as $field => $field_type) {
				$widget_fields[] = [
					'type' => $field_type,
					'name' => implode('.', [$this->name, $field, $index]),
					'value' => $val[$field]
				];
			}

			if (!array_key_exists('thresholds', $val) || !$val['thresholds']) {
				continue;
			}

			foreach ($val['thresholds'] as $i => $threshold) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => implode('.', [$this->name, 'thresholds', $index, $i, 'color']),
					'value' => $threshold['color']
				];
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => implode('.', [$this->name, 'thresholds', $index, $i, 'threshold']),
					'value' => $threshold['threshold']
				];
			}
		}
	}
}
