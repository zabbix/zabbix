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


namespace Widgets\PieChart\Includes;

use API,
	CApiInputValidator;

use Zabbix\Widgets\CWidgetField;

/**
 * Class for data set widget field used in Pie chart widget configuration Data set tab.
 */
class CWidgetFieldDataSet extends CWidgetField {

	public const DEFAULT_VIEW = CWidgetFieldDataSetView::class;
	public const DEFAULT_VALUE = [];

	public const DATASET_TYPE_SINGLE_ITEM = 0;
	public const DATASET_TYPE_PATTERN_ITEM = 1;

	public const ITEM_TYPE_NORMAL = 0;
	public const ITEM_TYPE_TOTAL = 1;

	// Predefined colors for data-sets in JSON format. Each next data set takes next sequential value from palette.
	public const DEFAULT_COLOR_PALETTE = [
		'FF465C', 'FFD54F', '0EC9AC', '524BBC', 'ED1248', 'D1E754', '2AB5FF', '385CC7', 'EC1594', 'BAE37D',
		'6AC8FF', 'EE2B29', '3CA20D', '6F4BBC', '00A1FF', 'F3601B', '1CAE59', '45CFDB', '894BBC', '6D6D6D'
	];

	// First color from the default color palette.
	private const DEFAULT_COLOR = 'FF465C';

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setValidationRules(['type' => API_OBJECTS, 'fields' => [
				'dataset_type'			=> ['type' => API_INT32, 'in' => implode(',', [self::DATASET_TYPE_SINGLE_ITEM, self::DATASET_TYPE_PATTERN_ITEM])],
				'hosts'					=> ['type' => API_STRINGS_UTF8],
				'items'					=> ['type' => API_STRINGS_UTF8],
				'itemids'				=> ['type' => API_IDS],
				'references'			=> ['type' => API_STRINGS_UTF8],
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

	public static function getItemNames(array $itemids, bool $resolve_macros): array {
		$names = [];

		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', $resolve_macros ? 'name_resolved' : 'name'],
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
			$names[$item['itemid']] = $resolve_macros
				? $hosts[$item['hostid']].NAME_DELIMITER.$item['name_resolved']
				: $hosts[$item['hostid']].NAME_DELIMITER.$item['name'];
		}

		return $names;
	}

	public function validate(bool $strict = false): array {
		if (!$strict) {
			return [];
		}

		$errors = [];
		$total_item_count = 0;

		$validation_rules = $this->getValidationRules($strict);
		$value = $this->getValue();
		$label = $this->getErrorLabel();

		if (!count($value)) {
			if (!CApiInputValidator::validate($validation_rules, $value, $label, $error)) {
				$errors[] = $error;
			}
		}
		else {
			$validation_rules['type'] = API_OBJECT;
		}

		foreach ($value as $index => &$data) {
			$validation_rules_by_type = $validation_rules;

			if ($data['dataset_type'] == self::DATASET_TYPE_SINGLE_ITEM) {
				foreach($data['type'] as $item_type) {
					if ($item_type == self::ITEM_TYPE_TOTAL) {
						$total_item_count++;
					}
				}

				$validation_rules_by_type['fields']['itemids']['flags'] |= API_REQUIRED;
				$validation_rules_by_type['fields']['references']['flags'] |= API_REQUIRED;
				$validation_rules_by_type['fields']['color']['type'] = API_COLORS;
				$validation_rules_by_type['fields']['type']['flags'] |= API_REQUIRED;

				unset($data['hosts'], $data['items']);
			}
			else {
				if (!$this->isTemplateDashboard()) {
					$validation_rules_by_type['fields']['hosts']['flags'] |= API_REQUIRED;
				}

				$validation_rules_by_type['fields']['items']['flags'] |= API_REQUIRED;

				unset($data['itemids'], $data['type'], $data['references']);
			}

			if (!CApiInputValidator::validate($validation_rules_by_type, $data, $label.'/'.($index + 1), $error)) {
				$errors[] = $error;
				break;
			}

			if ($data['dataset_type'] == self::DATASET_TYPE_SINGLE_ITEM) {
				foreach ($data['itemids'] as $i => &$item_spec) {
					if ($item_spec == 0) {
						$item_spec = [CWidgetField::FOREIGN_REFERENCE_KEY => $data['references'][$i]];
					}
				}
				unset($item_spec);

				unset($data['references']);
			}
		}
		unset($data);

		if ($total_item_count > 1) {
			$errors[] = _('Cannot add more than one item with type "Total" to the chart.');
		}

		if ($total_item_count > 0) {
			foreach ($value as $data) {
				if ($data['dataset_aggregation'] !== AGGREGATE_NONE) {
					$errors[] =
						_('Cannot set "Data set aggregation" when item with type "Total" is added to the chart.');
					break;
				}
			}
		}

		if (!$errors) {
			$this->setValue($value);
		}

		return $errors;
	}

	public function toApi(array &$widget_fields = []): void {
		$dataset_fields = [
			'dataset_type' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'aggregate_function' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'dataset_aggregation' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'data_set_label' => ZBX_WIDGET_FIELD_TYPE_STR
		];
		$dataset_defaults = self::getDefaults();

		foreach ($this->getValue() as $index => $value) {
			foreach ($value['hosts'] as $host_index => $pattern_host) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.'.$index.'.hosts.'.$host_index,
					'value' => $pattern_host
				];
			}

			foreach ($value['items'] as $item_index => $pattern_item) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.'.$index.'.items.'.$item_index,
					'value' => $pattern_item
				];
			}

			foreach ($value['itemids'] as $item_index => $item_spec) {
				if (is_array($item_spec)) {
					$widget_fields[] = [
						'type' => ZBX_WIDGET_FIELD_TYPE_STR,
						'name' => $this->name.'.'.$index.'.itemids.'.$item_index.'.'.
							CWidgetField::FOREIGN_REFERENCE_KEY,
						'value' => $item_spec[CWidgetField::FOREIGN_REFERENCE_KEY]
					];
				}
				else {
					$widget_fields[] = [
						'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
						'name' => $this->name.'.'.$index.'.itemids.'.$item_index,
						'value' => $item_spec
					];
				}
			}

			foreach ($value['type'] as $type_index => $type) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.'.$index.'.type.'.$type_index,
					'value' => $type
				];
			}

			// Field "color" stored as array for dataset type DATASET_TYPE_SINGLE_ITEM (0)
			if ($value['dataset_type'] == self::DATASET_TYPE_SINGLE_ITEM) {
				foreach ($value['color'] as $color_index => $color) {
					$widget_fields[] = [
						'type' => ZBX_WIDGET_FIELD_TYPE_STR,
						'name' => $this->name.'.'.$index.'.color.'.$color_index,
						'value' => $color
					];
				}
			}
			else {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.'.$index.'.color',
					'value' => $value['color']
				];
			}

			// Other dataset fields are stored if different from the defaults.
			foreach ($dataset_fields as $name => $type) {
				if ($value[$name] !== null && $value[$name] != $dataset_defaults[$name]) {
					$widget_fields[] = [
						'type' => $type,
						'name' => $this->name.'.'.$index.'.'.$name,
						'value' => $value[$name]
					];
				}
			}
		}
	}

	protected function getValidationRules(bool $strict = false): array {
		$validation_rules = parent::getValidationRules($strict);

		if (($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0) {
			self::setValidationRuleFlag($validation_rules, API_NOT_EMPTY);

			if (!$this->isTemplateDashboard()) {
				self::setValidationRuleFlag($validation_rules['fields']['hosts'], API_NOT_EMPTY);
			}

			self::setValidationRuleFlag($validation_rules['fields']['items'], API_NOT_EMPTY);
			self::setValidationRuleFlag($validation_rules['fields']['itemids'], API_NOT_EMPTY);
			self::setValidationRuleFlag($validation_rules['fields']['references'], API_NOT_EMPTY);
		}

		return $validation_rules;
	}
}
