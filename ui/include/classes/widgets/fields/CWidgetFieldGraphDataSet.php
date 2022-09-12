<?php
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


/**
 * Class for data set widget field used in Graph widget configuration Data set tab.
 */
class CWidgetFieldGraphDataSet extends CWidgetField {

	// Predefined colors for data-sets in JSON format. Each next data set takes next sequential value from palette.
	const DEFAULT_COLOR_PALETTE = ["FF465C","B0AF07","0EC9AC","524BBC","ED1248","D1E754","2AB5FF","385CC7","EC1594","BAE37D","6AC8FF","EE2B29","3CA20D","6F4BBC","00A1FF","F3601B","1CAE59","45CFDB","894BBC","6D6D6D"];

	// First color from the default color palette.
	const DEFAULT_COLOR = 'FF465C';

	/**
	 * Create widget field for Data set selection.
	 *
	 * @param string $name   Field name in form.
	 * @param string $label  Label for the field in form.
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setValidationRules(['type' => API_OBJECTS, 'fields' => [
			'dataset_type'			=> ['type' => API_INT32, 'in' => implode(',', [CWidgetHelper::DATASET_TYPE_SINGLE_ITEM, CWidgetHelper::DATASET_TYPE_PATTERN_ITEM])],
			'hosts'					=> ['type' => API_STRINGS_UTF8, 'flags' => null],
			'items'					=> ['type' => API_STRINGS_UTF8, 'flags' => null],
			'itemids'				=> ['type' => API_IDS, 'flags' => null],
			'color'					=> ['type' => API_COLOR, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'type'					=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SVG_GRAPH_TYPE_LINE, SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_STAIRCASE, SVG_GRAPH_TYPE_BAR])],
			'stacked'				=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SVG_GRAPH_STACKED_OFF, SVG_GRAPH_STACKED_ON])],
			'width'					=> ['type' => API_INT32, 'in' => implode(',', range(0, 10))],
			'pointsize'				=> ['type' => API_INT32, 'in' => implode(',', range(1, 10))],
			'transparency'			=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', range(0, 10))],
			'fill'					=> ['type' => API_INT32, 'in' => implode(',', range(0, 10))],
			'missingdatafunc'		=> ['type' => API_INT32, 'in' => implode(',', [SVG_GRAPH_MISSING_DATA_NONE, SVG_GRAPH_MISSING_DATA_CONNECTED, SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERO, SVG_GRAPH_MISSING_DATA_LAST_KNOWN])],
			'axisy'					=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [GRAPH_YAXIS_SIDE_LEFT, GRAPH_YAXIS_SIDE_RIGHT])],
			'timeshift'				=> ['type' => API_TIME_UNIT, 'flags' => API_REQUIRED, 'in' => implode(':', [ZBX_MIN_TIMESHIFT, ZBX_MAX_TIMESHIFT])],
			'aggregate_function'	=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [AGGREGATE_NONE, AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST])],
			'aggregate_interval'	=> ['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'aggregate_function', 'in' => implode(',', [AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST])],
					'type' => API_TIME_UNIT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_TIME_UNIT_WITH_YEAR, 'in' => implode(':', [1, ZBX_MAX_TIMESHIFT])],
				['else' => true, 'type' => API_STRING_UTF8, 'in' => GRAPH_AGGREGATE_DEFAULT_INTERVAL]
			]],
			'aggregate_grouping'	=> ['type' => API_INT32, 'in' => implode(',', [GRAPH_AGGREGATE_BY_ITEM, GRAPH_AGGREGATE_BY_DATASET])],
			'approximation'			=> ['type' => API_INT32, 'in' => implode(',', [APPROXIMATION_MIN, APPROXIMATION_AVG, APPROXIMATION_MAX, APPROXIMATION_ALL])]
		]]);

		$this->setDefault([]);
	}

	/**
	 * Set field values for the datasets.
	 *
	 * @return $this
	 */
	public function setValue($value) {
		$data_sets = [];

		foreach ((array) $value as $data_set) {
			$data_sets[] = $data_set + self::getDefaults();
		}

		return parent::setValue($data_sets);
	}

	/**
	 * Set additional flags, which can be used in configuration form.
	 *
	 * @param int $flags
	 *
	 * @return $this
	 */
	public function setFlags($flags) {
		parent::setFlags($flags);

		if ($flags & self::FLAG_NOT_EMPTY) {
			$strict_validation_rules = $this->getValidationRules();
			self::setValidationRuleFlag($strict_validation_rules, API_NOT_EMPTY);
			self::setValidationRuleFlag($strict_validation_rules['fields']['hosts'], API_NOT_EMPTY);
			self::setValidationRuleFlag($strict_validation_rules['fields']['items'], API_NOT_EMPTY);
			self::setValidationRuleFlag($strict_validation_rules['fields']['itemids'], API_NOT_EMPTY);
			$this->setStrictValidationRules($strict_validation_rules);
		}
		else {
			$this->setStrictValidationRules(null);
		}

		return $this;
	}

	/**
	 * Default values filled in newly created data set or used as unspecified values.
	 *
	 * @return array
	 */
	public static function getDefaults() {
		return [
			'dataset_type' => CWidgetHelper::DATASET_TYPE_PATTERN_ITEM,
			'hosts' => [],
			'items' => [],
			'itemids' => [],
			'color' => self::DEFAULT_COLOR,
			'type' => SVG_GRAPH_TYPE_LINE,
			'stacked' => SVG_GRAPH_STACKED_OFF,
			'width' => SVG_GRAPH_DEFAULT_WIDTH,
			'pointsize' => SVG_GRAPH_DEFAULT_POINTSIZE,
			'transparency' => SVG_GRAPH_DEFAULT_TRANSPARENCY,
			'fill' => SVG_GRAPH_DEFAULT_FILL,
			'axisy' => GRAPH_YAXIS_SIDE_LEFT,
			'timeshift' => '',
			'missingdatafunc' => SVG_GRAPH_MISSING_DATA_NONE,
			'aggregate_function' => AGGREGATE_NONE,
			'aggregate_interval' => GRAPH_AGGREGATE_DEFAULT_INTERVAL,
			'aggregate_grouping'=> GRAPH_AGGREGATE_BY_ITEM,
			'approximation' => APPROXIMATION_AVG
		];
	}

	/**
	 * @param bool $strict  Widget form submit validation?
	 *
	 * @return array  Errors.
	 */
	public function validate(bool $strict = false): array {
		$errors = [];

		$validation_rules = ($strict && $this->strict_validation_rules !== null)
			? $this->strict_validation_rules
			: $this->validation_rules;
		$validation_rules += $this->ex_validation_rules;
		$value = ($this->value === null) ? $this->default : $this->value;

		if ($this->full_name !== null) {
			$label = $this->full_name;
		}
		else {
			$label = ($this->label === null) ? $this->name : $this->label;
		}

		if ($strict) {
			if (!count($value)) {
				if (!CApiInputValidator::validate($validation_rules, $value, $label, $error)) {
					$errors[] = $error;
				}
			}
			else {
				$validation_rules['type'] = API_OBJECT;
			}

			foreach ($value as $i => $data) {
				$validation_rules_by_type = $validation_rules;

				if ($data['dataset_type'] == CWidgetHelper::DATASET_TYPE_SINGLE_ITEM) {
					$validation_rules_by_type['fields']['itemids']['flags'] |= API_REQUIRED;
					$validation_rules_by_type['fields']['color']['type'] = API_COLORS;

					unset($data['hosts'], $data['items']);
				}
				else {
					$validation_rules_by_type['fields']['hosts']['flags'] |= API_REQUIRED;
					$validation_rules_by_type['fields']['items']['flags'] |= API_REQUIRED;

					unset($data['itemids']);
				}

				if (!CApiInputValidator::validate($validation_rules_by_type, $data, $label.'/'.($i+1), $error)) {
					$errors[] = $error;
					break;
				}
			}
		}

		if (!$errors) {
			$this->setValue($value);
		}
		else {
			$this->setValue($this->default);
		}

		return $errors;
	}

	/**
	 * Prepares array entry for widget field, ready to be passed to CDashboard API functions.
	 * Reference is needed here to avoid array merging in CWidgetForm::fieldsToApi method. With large number of widget
	 * fields it causes significant performance decrease.
	 *
	 * @param array $widget_fields  Reference to array of widget fields.
	 */
	public function toApi(array &$widget_fields = []) {
		$value = $this->getValue();

		$dataset_fields = [
			'dataset_type' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'stacked' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'width' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'pointsize' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'transparency' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'fill' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'axisy' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'timeshift' => ZBX_WIDGET_FIELD_TYPE_STR,
			'missingdatafunc' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'aggregate_function' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'aggregate_interval' => ZBX_WIDGET_FIELD_TYPE_STR,
			'aggregate_grouping' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'approximation' => ZBX_WIDGET_FIELD_TYPE_INT32
		];
		$dataset_defaults = self::getDefaults();

		foreach ($value as $index => $val) {
			// Hosts, items and itemids fields are stored as arrays to bypass length limit.
			foreach ($val['hosts'] as $num => $pattern_host) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.hosts.'.$index.'.'.$num,
					'value' => $pattern_host
				];
			}
			foreach ($val['items'] as $num => $pattern_item) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.items.'.$index.'.'.$num,
					'value' => $pattern_item
				];
			}
			foreach ($val['itemids'] as $num => $itemid) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
					'name' => $this->name.'.itemids.'.$index.'.'.$num,
					'value' => $itemid
				];
			}
			// Field "color" stored as array for dataset type CWidgetHelper::DATASET_TYPE_SINGLE_ITEM (0)
			if ($val['dataset_type'] == CWidgetHelper::DATASET_TYPE_SINGLE_ITEM) {
				foreach ($val['color'] as $num => $color) {
					$widget_fields[] = [
						'type' => ZBX_WIDGET_FIELD_TYPE_STR,
						'name' => $this->name.'.color.'.$index.'.'.$num,
						'value' => $color
					];
				}
			}
			else {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.color.'.$index,
					'value' => $val['color']
				];
			}

			// Other dataset fields are stored if different from the defaults.
			foreach ($dataset_fields as $name => $type) {
				if ($val[$name] !== null && $val[$name] != $dataset_defaults[$name]) {
					$widget_fields[] = [
						'type' => $type,
						'name' => $this->name.'.'.$name.'.'.$index,
						'value' => $val[$name]
					];
				}
			}
		}
	}
}
