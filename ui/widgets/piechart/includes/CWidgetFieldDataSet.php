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


namespace Widgets\PieChart\Includes;

use API,
	CApiInputValidator;

use Zabbix\Widgets\CWidgetField;

/**
 * Class for data set widget field used in Pie chart widget configuration Data set tab.
 */
class CWidgetFieldDataSet extends CWidgetField {

	public const DEFAULT_VALUE = [];

	public const DATASET_TYPE_SINGLE_ITEM = 0;
	public const DATASET_TYPE_PATTERN_ITEM = 1;

	public const ITEM_TYPE_NORMAL = 0;
	public const ITEM_TYPE_TOTAL = 1;

	// Predefined colors for data-sets in JSON format. Each next data set takes next sequential value from palette.
	public const DEFAULT_COLOR_PALETTE = [
		'FF465C', 'B0AF07', '0EC9AC', '524BBC', 'ED1248', 'D1E754', '2AB5FF', '385CC7', 'EC1594', 'BAE37D',
		'6AC8FF', 'EE2B29', '3CA20D', '6F4BBC', '00A1FF', 'F3601B', '1CAE59', '45CFDB', '894BBC', '6D6D6D'
	];

	// First color from the default color palette.
	private const DEFAULT_COLOR = 'FF465C';

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR)
			->setValidationRules(['type' => API_OBJECTS, 'fields' => [
				'dataset_type'			=> ['type' => API_INT32, 'in' => implode(',', [self::DATASET_TYPE_SINGLE_ITEM, self::DATASET_TYPE_PATTERN_ITEM])],
				'hosts'					=> ['type' => API_STRINGS_UTF8, 'flags' => null],
				'items'					=> ['type' => API_STRINGS_UTF8, 'flags' => null],
				'itemids'				=> ['type' => API_IDS, 'flags' => null],
				'color'					=> ['type' => API_COLOR, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'aggregate_function'	=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST])],
				'dataset_aggregation'	=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [AGGREGATE_NONE, AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM])],
				'type'					=> ['type' => API_INTS32, 'flags' => null, 'in' => implode(',', [self::ITEM_TYPE_NORMAL, self::ITEM_TYPE_TOTAL])],
				'data_set_label'		=> ['type' => API_STRING_UTF8, 'length' => 255]
			]]);
	}

	public function setValue($value): self {
		$data_sets = [];

		foreach ((array) $value as $data_set) {
			$data_sets[] = $data_set + self::getDefaults();
		}

		return parent::setValue($data_sets);
	}

	public function setFlags($flags): self {
		parent::setFlags($flags);

		if (($flags & self::FLAG_NOT_EMPTY) !== 0) {
			$strict_validation_rules = $this->getValidationRules();
			self::setValidationRuleFlag($strict_validation_rules, API_NOT_EMPTY);

			if (!$this->isTemplateDashboard()) {
				self::setValidationRuleFlag($strict_validation_rules['fields']['hosts'], API_NOT_EMPTY);
			}

			self::setValidationRuleFlag($strict_validation_rules['fields']['items'], API_NOT_EMPTY);
			self::setValidationRuleFlag($strict_validation_rules['fields']['itemids'], API_NOT_EMPTY);
			$this->setStrictValidationRules($strict_validation_rules);
		}
		else {
			$this->setStrictValidationRules();
		}

		return $this;
	}

	public function setTemplateId($templateid): self {
		parent::setTemplateId($templateid);

		return $this->setFlags($this->getFlags());
	}

	public static function getDefaults(): array {
		return [
			'dataset_type' => self::DATASET_TYPE_PATTERN_ITEM,
			'hosts' => [],
			'items' => [],
			'itemids' => [],
			'color' => self::DEFAULT_COLOR,
			'aggregate_function' => AGGREGATE_LAST,
			'dataset_aggregation' => AGGREGATE_NONE,
			'type' => [],
			'data_set_label' => ''
		];
	}

	public static function getItemNames(array $itemids): array {
		$names = [];

		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'name'],
			'selectHosts' => ['hostid', 'name'],
			'webitems' => true,
			'itemids' => $itemids,
			'preservekeys' => true
		]);

		if (!$items) {
			return $names;
		}

		foreach ($items as $item) {
			$hosts = array_column($item['hosts'], 'name', 'hostid');
			$names[$item['itemid']] = $hosts[$item['hostid']].NAME_DELIMITER.$item['name'];
		}

		return $names;
	}

	public function validate(bool $strict = false): array {
		$errors = [];
		$total_item_count = 0;

		$validation_rules = ($strict && $this->strict_validation_rules !== null)
			? $this->strict_validation_rules
			: $this->validation_rules;
		$validation_rules += $this->ex_validation_rules;

		$value = $this->value ?? $this->default;

		if ($this->full_name !== null) {
			$label = $this->full_name;
		}
		else {
			$label = $this->label ?? $this->name;
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
				if ($data['dataset_type'] == self::DATASET_TYPE_SINGLE_ITEM) {
					foreach($data['type'] as $item_type) {
						if ($item_type == self::ITEM_TYPE_TOTAL) {
							$total_item_count++;
						}
					}

					$validation_rules_by_type['fields']['itemids']['flags'] |= API_REQUIRED;
					$validation_rules_by_type['fields']['color']['type'] = API_COLORS;
					$validation_rules_by_type['fields']['type']['flags'] |= API_REQUIRED;

					unset($data['hosts'], $data['items']);
				}
				else {
					$validation_rules_by_type['fields']['hosts']['flags'] |= API_REQUIRED;
					$validation_rules_by_type['fields']['items']['flags'] |= API_REQUIRED;

					unset($data['itemids'], $data['type']);
				}

				if (!CApiInputValidator::validate($validation_rules_by_type, $data, $label.'/'.($i+1), $error)) {
					$errors[] = $error;
					break;
				}
			}

			if ($total_item_count > 1) {
				$errors[] = _('Cannot add more than one item with type "Total" to the chart.');
			}

			if ($total_item_count > 0) {
				foreach ($value as $data) {
					if ($data['dataset_aggregation'] !== AGGREGATE_NONE) {
						$errors[] = _('Cannot set "Data set aggregation" when item with type "Total" is added to the chart.');
						break;
					}
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

	public function toApi(array &$widget_fields = []): void {
		$value = $this->getValue();

		$dataset_fields = [
			'dataset_type' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'aggregate_function' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'dataset_aggregation' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'data_set_label' => ZBX_WIDGET_FIELD_TYPE_STR
		];
		$dataset_defaults = self::getDefaults();

		foreach ($value as $index => $val) {
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
			foreach ($val['type'] as $num => $type) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.type.'.$index.'.'.$num,
					'value' => $type
				];
			}
			// Field "color" stored as array for dataset type DATASET_TYPE_SINGLE_ITEM (0)
			if ($val['dataset_type'] == self::DATASET_TYPE_SINGLE_ITEM) {
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
