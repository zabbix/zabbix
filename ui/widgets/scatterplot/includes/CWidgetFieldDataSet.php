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


namespace Widgets\ScatterPlot\Includes;

use API,
	CApiInputValidator;

use Zabbix\Widgets\CWidgetField;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectOverrideHost;

/**
 * Class for data set widget field used in Scatter plot widget configuration Data set tab.
 */
class CWidgetFieldDataSet extends CWidgetField {

	public const DEFAULT_VIEW = CWidgetFieldDataSetView::class;
	public const DEFAULT_VALUE = [];

	public const DATASET_TYPE_SINGLE_ITEM = 0;
	public const DATASET_TYPE_PATTERN_ITEM = 1;

	public const DATASET_MARKER_SIZE_SMALL = 0;
	public const DATASET_MARKER_SIZE_MEDIUM = 1;
	public const DATASET_MARKER_SIZE_LARGE = 2;

	// Predefined colors for data-sets in JSON format. Each next data set takes next sequential value from palette.
	public const DEFAULT_COLOR_PALETTE = [
		'FFD54F', '0EC9AC', '524BBC', 'ED1248', 'D1E754', '2AB5FF', '385CC7', 'EC1594', 'BAE37D', '6AC8FF', 'EE2B29',
		'3CA20D', '6F4BBC', '00A1FF', 'F3601B', '1CAE59', '45CFDB', '894BBC', '6D6D6D'
	];

	// First palette from predefined palettes.
	private const DEFAULT_PALETTE = 0;

	public function __construct(string $name, ?string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setValidationRules(['type' => API_OBJECTS, 'fields' => [
				'dataset_type'			=> ['type' => API_INT32, 'in' => implode(',', [self::DATASET_TYPE_SINGLE_ITEM, self::DATASET_TYPE_PATTERN_ITEM])],
				'hostgroupids'			=> ['type' => API_IDS],
				'hosts'					=> ['type' => API_STRINGS_UTF8],
				'x_axis_items'			=> ['type' => API_STRINGS_UTF8],
				'y_axis_items' 			=> ['type' => API_STRINGS_UTF8],
				'x_axis_itemids'		=> ['type' => API_IDS],
				'y_axis_itemids'		=> ['type' => API_IDS],
				'host_tags_evaltype'	=> ['type' => API_INT32, 'in' => implode(',', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR])],
				'host_tags'				=> ['type' => API_OBJECTS, 'fields' => [
					'tag'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
					'operator'	=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS])],
					'value'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255]
				]],
				'x_axis_references'		=> ['type' => API_STRINGS_UTF8],
				'y_axis_references'		=> ['type' => API_STRINGS_UTF8],
				'color'					=> ['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
				'color_palette'			=> ['type' => API_INT32, 'flags' => API_NOT_EMPTY],
				'timeshift'				=> ['type' => API_TIME_UNIT, 'flags' => API_REQUIRED, 'in' => implode(':', [ZBX_MIN_TIMESHIFT, ZBX_MAX_TIMESHIFT])],
				'aggregate_function'	=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST])],
				'aggregate_interval'	=> ['type' => API_TIME_UNIT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_TIME_UNIT_WITH_YEAR, 'in' => implode(':', [1, ZBX_MAX_TIMESHIFT])],
				'marker'				=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [CScatterPlotMetricPoint::MARKER_TYPE_ELLIPSIS, CScatterPlotMetricPoint::MARKER_TYPE_SQUARE, CScatterPlotMetricPoint::MARKER_TYPE_TRIANGLE, CScatterPlotMetricPoint::MARKER_TYPE_DIAMOND, CScatterPlotMetricPoint::MARKER_TYPE_STAR, CScatterPlotMetricPoint::MARKER_TYPE_CROSS])],
				'marker_size'			=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [self::DATASET_MARKER_SIZE_SMALL, self::DATASET_MARKER_SIZE_MEDIUM, self::DATASET_MARKER_SIZE_LARGE])],
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
			'hostgroupids' => [],
			'host_tags_evaltype' => TAG_EVAL_TYPE_AND_OR,
			'host_tags' => [],
			'hosts' => [],
			'x_axis_items' => [],
			'y_axis_items' => [],
			'x_axis_itemids' => [],
			'y_axis_itemids' => [],
			'x_axis_references' => [],
			'y_axis_references' => [],
			'color_palette' => self::DEFAULT_PALETTE,
			'timeshift' => '',
			'aggregate_function' => AGGREGATE_AVG,
			'aggregate_interval' => '15m',
			'override_hostid' => [],
			'marker' => CScatterPlotMetricPoint::MARKER_TYPE_ELLIPSIS,
			'marker_size' => self::DATASET_MARKER_SIZE_SMALL
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

	public static function getHostGroupCaptions(array $hostgroupids): array {
		$captions = [];

		$hostgroups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $hostgroupids,
			'preservekeys' => true
		]);

		if (!$hostgroups) {
			return $captions;
		}

		foreach ($hostgroupids as $hostgroupid) {
			$hostgroup = array_key_exists($hostgroupid, $hostgroups) ? $hostgroups[$hostgroupid] : [
				'groupid' => $hostgroupid,
				'name' => _('Inaccessible group')
			];

			$captions[ZBX_WIDGET_FIELD_TYPE_GROUP][$hostgroup['groupid']] = [
				'id' => $hostgroup['groupid'],
				'name' => $hostgroup['name']
			];
		}

		return $captions;
	}

	public function validate(bool $strict = false): array {
		if (!$strict) {
			return [];
		}

		$validation_rules = $this->getValidationRules($strict);
		$value = $this->getValue();
		$label = $this->getErrorLabel();

		if (!$value) {
			if (!CApiInputValidator::validate($validation_rules, $value, $label, $error)) {
				return [$error];
			}
		}
		else {
			$validation_rules['type'] = API_OBJECT;
		}

		foreach ($value as $index => &$data) {
			$validation_rules_by_type = $validation_rules;

			if ($data['dataset_type'] == self::DATASET_TYPE_SINGLE_ITEM) {
				foreach (['x_axis_itemids', 'y_axis_itemids', 'x_axis_references', 'y_axis_references'] as $key) {
					$validation_rules_by_type['fields'][$key]['flags'] |= API_REQUIRED;
				}

				unset($data['hostgroupids'], $data['hosts'], $data['x_axis_items'], $data['y_axis_items']);
			}
			else {
				if (!$this->isTemplateDashboard()) {
					$validation_rules_by_type['fields']['hosts']['flags'] |= API_REQUIRED;
				}

				$validation_rules_by_type['fields']['x_axis_items']['flags'] |= API_REQUIRED;
				$validation_rules_by_type['fields']['y_axis_items']['flags'] |= API_REQUIRED;

				unset($data['x_axis_itemids'], $data['y_axis_itemids'], $data['x_axis_references'],
					$data['y_axis_references']
				);
			}

			if (!CApiInputValidator::validate($validation_rules_by_type, $data, $label.'/'.($index + 1), $error)) {
				return [$error];
			}

			if ($data['dataset_type'] == self::DATASET_TYPE_SINGLE_ITEM) {
				foreach (['x_axis', 'y_axis'] as $key) {
					foreach ($data[$key.'_itemids'] as $i => &$item_spec) {
						if ($item_spec == 0) {
							if ($data[$key.'_references'][$i] === '') {
								return [_s('Invalid parameter "%1$s": %2$s.', $label.'/'.($index + 1),
									_('referred widget is unavailable')
								)];
							}

							$item_spec = [CWidgetField::FOREIGN_REFERENCE_KEY => $data[$key.'_references'][$i]];
						}
					}
					unset($item_spec);
				}

				unset($data['x_axis_references'], $data['y_axis_references']);
			}

			if (!$this->isTemplateDashboard()) {
				$override_host_field = new CWidgetFieldMultiSelectOverrideHost('override_hostid',
					$label.'/'.($index + 1).'/'._('Override host')
				);

				$override_host_field->setValue($data['override_hostid']);

				if ($errors = $override_host_field->validate($strict)) {
					return $errors;
				}

				$data['override_hostid'] = $override_host_field->getValue();
			}
		}
		unset($data);

		$this->setValue($value);

		return [];
	}

	public function toApi(array &$widget_fields = []): void {
		$dataset_fields = [
			'dataset_type' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'timeshift' => ZBX_WIDGET_FIELD_TYPE_STR,
			'host_tags_evaltype' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'aggregate_function' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'aggregate_interval' => ZBX_WIDGET_FIELD_TYPE_STR,
			'marker' => ZBX_WIDGET_FIELD_TYPE_INT32,
			'marker_size' => ZBX_WIDGET_FIELD_TYPE_INT32
		];

		$dataset_defaults = self::getDefaults();

		foreach ($this->getValue() as $index => $value) {
			foreach ($value['hostgroupids'] as $group_index => $group_spec) {
				if (is_array($group_spec)) {
					$widget_fields[] = [
						'type' => ZBX_WIDGET_FIELD_TYPE_STR,
						'name' => $this->name.'.'.$index.'.hostgroupids.'.$group_index.'.'.
							CWidgetField::FOREIGN_REFERENCE_KEY,
						'value' => $group_spec[CWidgetField::FOREIGN_REFERENCE_KEY]
					];
				}
				else {
					$widget_fields[] = [
						'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
						'name' => $this->name.'.'.$index.'.hostgroupids.'.$group_index,
						'value' => $group_spec
					];
				}
			}

			foreach ($value['hosts'] as $host_index => $pattern_host) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.'.$index.'.hosts.'.$host_index,
					'value' => $pattern_host
				];
			}

			foreach (['x_axis_items', 'y_axis_items'] as $key) {
				if (is_array($value[$key])) {
					foreach ($value[$key] as $item_index => $pattern_item) {
						$widget_fields[] = [
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => $this->name.'.'.$index.'.'.$key.'.'.$item_index,
							'value' => $pattern_item
						];
					}
				}
			}

			foreach (['x_axis_itemids', 'y_axis_itemids'] as $key) {
				foreach ($value[$key] as $item_index => $item_spec) {
					if (is_array($item_spec)) {
						$widget_fields[] = [
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => $this->name.'.'.$index.'.'.$key.'.'.$item_index.'.'.
								CWidgetField::FOREIGN_REFERENCE_KEY,
							'value' => $item_spec[CWidgetField::FOREIGN_REFERENCE_KEY]
						];
					}
					else {
						$widget_fields[] = [
							'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
							'name' => $this->name.'.'.$index.'.'.$key.'.'.$item_index,
							'value' => $item_spec
						];
					}
				}
			}

			if (array_key_exists('color', $value)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.'.$index.'.color',
					'value' => $value['color']
				];
			}
			elseif (array_key_exists('color_palette', $value)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.'.$index.'.color_palette',
					'value' => $value['color_palette']
				];
			}

			if (array_key_exists('host_tags', $value)) {
				foreach ($value['host_tags'] as $key => $tag) {
					if ($tag['tag'] !== '' && $tag['value'] !== '') {
						$widget_fields[] = [
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => $this->name.'.'.$index.'.host_tags.'.$key.'.'.'tag',
							'value' => $tag['tag']
						];
						$widget_fields[] = [
							'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
							'name' => $this->name.'.'.$index.'.host_tags.'.$key.'.'.'operator',
							'value' => $tag['operator']
						];
						$widget_fields[] = [
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => $this->name.'.'.$index.'.host_tags.'.$key.'.'.'value',
							'value' => $tag['value']
						];
					}
				}
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

			foreach (['x_axis_items', 'y_axis_items', 'x_axis_itemids', 'y_axis_itemids', 'x_axis_references',
					'y_axis_references'] as $key) {
				self::setValidationRuleFlag($validation_rules['fields'][$key], API_NOT_EMPTY);
			}
		}

		return $validation_rules;
	}
}
