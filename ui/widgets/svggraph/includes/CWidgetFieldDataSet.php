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


namespace Widgets\SvgGraph\Includes;

use API,
	CApiInputValidator;

use Zabbix\Widgets\CWidgetField;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectOverrideHost;

/**
 * Class for data set widget field used in Graph widget configuration Data set tab.
 */
class CWidgetFieldDataSet extends CWidgetField {

	public const DEFAULT_VIEW = CWidgetFieldDataSetView::class;
	public const DEFAULT_VALUE = [];

	public const DATASET_TYPE_SINGLE_ITEM = 0;
	public const DATASET_TYPE_PATTERN_ITEM = 1;

	// Predefined colors for data-sets in JSON format. Each next data set takes next sequential value from palette.
	public const DEFAULT_COLOR_PALETTE = [
		'FF465C', 'FFD54F', '0EC9AC', '524BBC', 'ED1248', 'D1E754', '2AB5FF', '385CC7', 'EC1594', 'BAE37D',
		'6AC8FF', 'EE2B29', '3CA20D', '6F4BBC', '00A1FF', 'F3601B', '1CAE59', '45CFDB', '894BBC', '6D6D6D'
	];

	// First color from the default color palette.
	private const DEFAULT_COLOR = 'FF465C';

	public function __construct(string $name, ?string $label = null) {
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
				'type'					=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SVG_GRAPH_TYPE_LINE, SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_STAIRCASE, SVG_GRAPH_TYPE_BAR])],
				'stacked'				=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SVG_GRAPH_STACKED_OFF, SVG_GRAPH_STACKED_ON])],
				'width'					=> ['type' => API_INT32, 'in' => '0:10'],
				'pointsize'				=> ['type' => API_INT32, 'in' => '1:10'],
				'transparency'			=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:10'],
				'fill'					=> ['type' => API_INT32, 'in' => '0:10'],
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
				'approximation'			=> ['type' => API_INT32, 'in' => implode(',', [APPROXIMATION_MIN, APPROXIMATION_AVG, APPROXIMATION_MAX, APPROXIMATION_ALL])],
				'data_set_label'		=> ['type' => API_STRING_UTF8, 'length' => 255],
				'override_hostid'		=> ['type' => API_ANY]
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
			'references' => [],
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
			'approximation' => APPROXIMATION_AVG,
			'data_set_label' => '',
			'override_hostid' => []
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
				$validation_rules_by_type['fields']['itemids']['flags'] |= API_REQUIRED;
				$validation_rules_by_type['fields']['references']['flags'] |= API_REQUIRED;
				$validation_rules_by_type['fields']['color']['type'] = API_COLORS;

				unset($data['hosts'], $data['items']);
			}
			else {
				if (!$this->isTemplateDashboard()) {
					$validation_rules_by_type['fields']['hosts']['flags'] |= API_REQUIRED;
				}

				$validation_rules_by_type['fields']['items']['flags'] |= API_REQUIRED;

				unset($data['itemids'], $data['references']);
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

			if (!$this->isTemplateDashboard()) {
				$override_host_field = new CWidgetFieldMultiSelectOverrideHost('override_hostid',
					$label.'/'.($index + 1).'/'._('Override host')
				);

				$override_host_field->setValue($data['override_hostid']);

				$errors = $override_host_field->validate($strict);

				if ($errors) {
					break;
				}

				$data['override_hostid'] = $override_host_field->getValue();
			}
		}
		unset($data);

		if (!$errors) {
			$this->setValue($value);
		}

		return $errors;
	}

	public function toApi(array &$widget_fields = []): void {
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
			'approximation' => ZBX_WIDGET_FIELD_TYPE_INT32,
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

			if (array_key_exists(CWidgetField::FOREIGN_REFERENCE_KEY, $value['override_hostid'])) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.'.$index.'.override_hostid.'.CWidgetField::FOREIGN_REFERENCE_KEY,
					'value' => $value['override_hostid'][CWidgetField::FOREIGN_REFERENCE_KEY]
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
