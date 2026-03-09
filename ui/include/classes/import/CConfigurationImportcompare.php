<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


/**
 * Class for preparing difference between import file and system.
 */
class CConfigurationImportcompare {

	/**
	 * @var array  Options for creating, updating or deleting entities.
	 */
	protected array $options;

	/**
	 * @var array  Structure and uniqueness rules of the importable entities.
	 */
	protected array $rules;

	/**
	 * CConfigurationImportcompare constructor.
	 *
	 * @param array $options
	 */
	public function __construct(array $options) {
		$this->options = $options;

		$this->rules = [
			'template_groups' => [
				'options' => $this->getOptions('template_groups'),
				'uuid' => true,
				'unique' => ['name']
			],
			'host_groups' => [
				'options' => $this->getOptions('host_groups'),
				'uuid' => true,
				'unique' => ['name']
			],
			'templates' => [
				'options' => $this->getOptions('templates'),
				'uuid' => true,
				'unique' => ['template'],
				'rules' => [
					'items' => [
						'options' => $this->getOptions('items'),
						'uuid' => true,
						'unique' => ['name', 'key'],
						'rules' => [
							'triggers' => [
								'options' => $this->getOptions('triggers'),
								'uuid' => true,
								'unique' => ['name', 'expression', 'recovery_expression']
							]
						]
					],
					'discovery_rules' => [
						'options' => $this->getOptions('discoveryRules'),
						'uuid' => true,
						'unique' => ['name', 'key'],
						'rules' => [
							'item_prototypes' => [
								'options' => $this->getOptions('discoveryRules'),
								'uuid' => true,
								'unique' => ['name', 'key'],
								'rules' => [
									'trigger_prototypes' => [
										'options' => $this->getOptions('discoveryRules'),
										'uuid' => true,
										'unique' => ['name', 'expression', 'recovery_expression']
									]
								]
							],
							'trigger_prototypes' => [
								'options' => $this->getOptions('discoveryRules'),
								'uuid' => true,
								'unique' => ['name', 'expression', 'recovery_expression']
							],
							'graph_prototypes' => [
								'options' => $this->getOptions('discoveryRules'),
								'uuid' => true,
								'unique' => ['name', 'graph_items' => [['item' => ['host']]]]
							],
							'host_prototypes' => [
								'options' => $this->getOptions('discoveryRules'),
								'uuid' => true,
								'unique' => ['host']
							]
						]
					],
					'dashboards' => [
						'options' => $this->getOptions('templateDashboards'),
						'uuid' => true,
						'unique' => ['name']
					],
					'httptests' => [
						'options' => $this->getOptions('httptests'),
						'uuid' => true,
						'unique' => ['name']
					],
					'valuemaps' => [
						'options' => $this->getOptions('valueMaps'),
						'uuid' => true,
						'unique' => ['name']
					]
				]
			],
			'triggers' => [
				'options' => $this->getOptions('triggers'),
				'uuid' => true,
				'unique' => ['name', 'expression', 'recovery_expression']
			],
			'graphs' => [
				'options' => $this->getOptions('graphs'),
				'uuid' => true,
				'unique' => ['name', 'graph_items' => [['item' => ['host']]]]
			],
			'dashboards' => [
				'options' => $this->getOptions('dashboards'),
				'uuid' => false,
				'unique' => ['name'],
				'rules' => [
					'pages' => [
						'options' => function (array $actions): array {
							return $this->getDashboardInnerEntityOptions($actions);
						},
						'unique_index' => true,
						'rules' => [
							'widgets' => [
								'options' => function (array $actions): array {
									return $this->getDashboardInnerEntityOptions($actions);
								},
								'uuid' => false,
								'unique' => [
									static function(array $widget): string {
										return ($widget['x'] ?? '0').'_'.($widget['y'] ?? '0');
									}
								]
							]
						]
					]
				]
			]
		];
	}

	/**
	 * Perform a comparison of the export and import data and return the difference.
	 *
	 * @param array $export  Current data.
	 * @param array $import  New data.
	 *
	 * @return array
	 */
	public function importcompare(array $export, array $import): array {
		// Leave only the supported entities.
		$export = array_intersect_key($export, $this->rules);
		$import = array_intersect_key($import, $this->rules);

		$result = $this->compareByRules($this->rules, $export, $import);

		unset($result['before'], $result['after']);

		return $result;
	}

	/**
	 * Get import options for the specified entity group (like templates, dashboards, etc.)
	 *
	 * @param string $entity_group
	 *
	 * @return array
	 */
	protected function getOptions(string $entity_group): array {
		return [
			'updateExisting' => array_key_exists($entity_group, $this->options)
				&& array_key_exists('updateExisting', $this->options[$entity_group])
				&& $this->options[$entity_group]['updateExisting'],
			'createMissing' => array_key_exists($entity_group, $this->options)
				&& array_key_exists('createMissing', $this->options[$entity_group])
				&& $this->options[$entity_group]['createMissing'],
			'deleteMissing' => array_key_exists($entity_group, $this->options)
				&& array_key_exists('deleteMissing', $this->options[$entity_group])
				&& $this->options[$entity_group]['deleteMissing']
		];
	}

	/**
	 * Get import options for pages and widgets of the global dashboards.
	 *
	 * @param array  $actions
	 *
	 * @return array
	 */
	protected function getDashboardInnerEntityOptions(array $actions): array {
		$dashboards_options = $this->getOptions('dashboards');

		$rule = $actions[0] === ['dashboards', 'updated'] ? 'updateExisting' : 'createMissing';

		return 	[
			'updateExisting' => $dashboards_options[$rule],
			'createMissing' => $dashboards_options[$rule],
			'deleteMissing' => $dashboards_options[$rule]
		];
	}

	/**
	 * Recursively compare entities by rules.
	 *
	 * @param array $rules
	 * @param array $before
	 * @param array $after
	 * @param array $actions
	 *
	 * @return array
	 */
	protected function compareByRules(array $rules, array $before, array $after, array $actions = []): array {
		$result = [];

		foreach ($rules as $entity_group => $rule) {
			$before_entity_group = array_key_exists($entity_group, $before) ? $before[$entity_group] : [];
			$after_entity_group = array_key_exists($entity_group, $after) ? $after[$entity_group] : [];

			unset($before[$entity_group], $after[$entity_group]);

			if (!$before_entity_group && !$after_entity_group) {
				continue;
			}

			if (array_key_exists('unique_index', $rule) && $rule['unique_index']) {
				$before_entity_group = self::addIndex($before_entity_group);
				$after_entity_group = self::addIndex($after_entity_group);

				$diff = $this->compareByIndex($before_entity_group, $after_entity_group);
			}
			else {
				$diff = $this->compareByUniqueness($before_entity_group, $after_entity_group, $rule['uuid'],
					$rule['unique']
				);
			}

			if (array_key_exists('added', $diff)) {
				$_actions = [...$actions, [$entity_group, 'added']];

				foreach ($diff['added'] as &$entity) {
					$entity = $this->compareByRules($rule['rules'] ?? [], [], $entity, $_actions);
				}
				unset($entity);
			}

			if (array_key_exists('removed', $diff)) {
				$_actions = [...$actions, [$entity_group, 'removed']];

				foreach ($diff['removed'] as &$entity) {
					$entity = $this->compareByRules($rule['rules'] ?? [], $entity, [], $_actions);
				}
				unset($entity);
			}

			if (array_key_exists('updated', $diff) && array_key_exists('rules', $rule)) {
				$_actions = [...$actions, [$entity_group, 'updated']];

				foreach ($diff['updated'] as &$entity) {
					$entity = $this->compareByRules($rule['rules'], $entity['before'], $entity['after'], $_actions);
				}
				unset($entity);
			}

			$options = $rule['options'] instanceof Closure ? $rule['options']($actions) : $rule['options'];

			$diff = $this->applyOptions($entity_group, $options, $diff);

			if ($diff) {
				$result[$entity_group] = $diff;
			}
		}

		$object = [];

		$parent_action = $actions ? end($actions)[1] : null;

		if ($parent_action === 'removed' || $parent_action === 'updated') {
			$object['before'] = $before;
		}

		if ($parent_action === 'added' || $parent_action === 'updated') {
			$object['after'] = $after;
		}

		return $object + $result;
	}

	/**
	 * Compare two arrays by UUID or uniqueness criteria.
	 *
	 * @param array $before
	 * @param array $after
	 * @param bool  $has_uuid
	 * @param array $unique    Specification of the entity unique data.
	 *
	 * @return array
	 */
	protected function compareByUniqueness(array $before, array $after, bool $has_uuid, array $unique): array {
		$before = $this->addUniqueIds($before, $unique);
		$after = $this->addUniqueIds($after, $unique);

		$same_entities = [];

		foreach ($after as $a_key => $after_entity) {
			if ($has_uuid) {
				foreach ($before as $b_key => $before_entity) {
					if ($before_entity['uuid'] === $after_entity['uuid']) {
						$same_entities[$b_key]['before'] = $before_entity;
						$same_entities[$b_key]['after'] = $after_entity;

						unset($before[$b_key], $after[$a_key]);

						continue 2;
					}
				}
			}

			foreach ($before as $b_key => $before_entity) {
				if ($before_entity['_unique_id'] === $after_entity['_unique_id']) {
					if ($has_uuid) {
						$before_entity['uuid'] = $after_entity['uuid'];
					}

					$same_entities[$b_key]['before'] = $before_entity;
					$same_entities[$b_key]['after'] = $after_entity;

					unset($before[$b_key], $after[$a_key]);

					break;
				}
			}
		}

		$diff = [];

		$removed_entities = $before;
		$added_entities = $after;

		foreach ($added_entities as $entity) {
			unset($entity['_unique_id']);

			$diff['added'][] = $entity;
		}

		foreach ($removed_entities as $entity) {
			unset($entity['_unique_id']);

			$diff['removed'][] = $entity;
		}

		foreach ($same_entities as $entity) {
			unset($entity['before']['_unique_id'], $entity['after']['_unique_id']);

			if ($entity['before'] != $entity['after']) {
				$diff['updated'][] = [
					'before' => $entity['before'],
					'after' => $entity['after']
				];
			}
		}

		return $diff;
	}

	/**
	 * Compare two arrays by index.
	 *
	 * @param array $before
	 * @param array $after
	 *
	 * @return array
	 */
	protected function compareByIndex(array $before, array $after): array {
		$diff = [];

		$intersection = array_intersect_key($before, $after);

		if ($added = array_diff_key($after, $intersection)) {
			$diff['added'] = array_values($added);
		}

		if ($removed = array_diff_key($before, $intersection)) {
			$diff['removed'] = array_values($removed);
		}

		foreach (array_keys($intersection) as $key) {
			if ($before[$key] != $after[$key]) {
				$diff['updated'][] = [
					'before' => $before[$key],
					'after' => $after[$key]
				];
			}
		}

		return $diff;
	}

	/**
	 * Add index to the entities as the only uniqueness criteria.
	 *
	 * @param array $entities
	 *
	 * @return array
	 */
	private static function addIndex(array $entities): array {
		foreach ($entities as $index => &$entity) {
			$entity['_index'] = $index;
		}
		unset($entity);

		return $entities;
	}

	/**
	 * Calculate and add unique ID strings to the entities.
	 *
	 * @param array $entities
	 * @param array $unique    Specification of the entity unique data.
	 *
	 * @return array
	 */
	private static function addUniqueIds(array $entities, array $unique): array {
		foreach ($entities as $entity_key => &$entity) {
			$entity['_unique_id'] = self::getUniqueData($entity, $unique, $entity_key);

			// To make a unique entity a string, get result values, get rid of value duplicates and sort them.
			$entity['_unique_id'] = array_unique(self::flatten($entity['_unique_id']));
			sort($entity['_unique_id']);

			$entity['_unique_id'] = implode('/', $entity['_unique_id']);
		}
		unset($entity);

		return $entities;
	}

	/**
	 * Get unique data of the entity.
	 *
	 * @param array $entity
	 * @param array $unique      Specification of the entity unique data.
	 * @param mixed $entity_key
	 *
	 * @return array
	 */
	private static function getUniqueData(array $entity, array $unique, mixed $entity_key): array {
		$result = [];

		foreach ($unique as $unique_key => $unique_value) {
			if (is_array($unique_value)) {
				if (is_int($unique_key)) {
					foreach ($entity as $sub_entity_key => $sub_entity) {
						$result[] = self::getUniqueData($sub_entity, $unique_value, $sub_entity_key);
					}
				}
				else {
					$result[] = self::getUniqueData($entity[$unique_key], $unique_value, $unique_key);
				}
			}
			elseif ($unique_value instanceof Closure) {
				$result[] = call_user_func($unique_value, $entity, $entity_key);
			}
			else {
				$result[] = $entity[$unique_value];
			}
		}

		return $result;
	}

	/**
	 * Return multidimensional array as one dimensional array.
	 *
	 * @param array $array
	 *
	 * @return array
	 */
	private static function flatten(array $array): array {
		$result = [];

		foreach ($array as $value) {
			if (is_array($value)) {
				$result = array_merge($result, self::flatten($value));
			}
			else {
				$result[] = $value;
			}
		}

		return $result;
	}

	/**
	 * Compare two entities and separate all their keys into added/removed/updated.
	 *
	 * @param string $entity_group
	 * @param array  $options
	 * @param array  $diff
	 *
	 * @return array
	 */
	protected function applyOptions(string $entity_group, array $options, array $diff): array {
		$template_linkage = $this->getOptions('templateLinkage');

		$stored_changes = [];

		if ($entity_group === 'templates' && array_key_exists('updated', $diff)) {
			$updated_count = count($diff['updated']);
			for ($key = 0; $key < $updated_count; $key++) {
				$entity = $diff['updated'][$key];
				$has_before_templates = array_key_exists('templates', $entity['before']);
				$has_after_templates = array_key_exists('templates', $entity['after']);

				if (!$has_before_templates && !$has_after_templates) {
					continue;
				}

				if ($has_before_templates && !$has_after_templates) {
					$entity['after']['templates'] = [];

					// Make sure that processed entry is last in both arrays. Otherwise, it will break the comparison.
					$before_templates = $entity['before']['templates'];
					unset($entity['before']['templates']);
					$entity['before']['templates'] = $before_templates;
				}
				elseif ($has_after_templates && !$has_before_templates) {
					$entity['before']['templates'] = [];
				}

				if ($entity['before']['templates'] === $entity['after']['templates']) {
					continue;
				}

				if (!$template_linkage['createMissing'] && !$template_linkage['deleteMissing']) {
					$entity['after']['templates'] = $entity['before']['templates'];
				}
				elseif ($template_linkage['createMissing'] && !$template_linkage['deleteMissing']) {
					$entity['after']['templates'] = $this->afterForInnerCreateMissing($entity['before']['templates'],
						$entity['after']['templates']
					);
				}
				elseif ($template_linkage['deleteMissing'] && !$template_linkage['createMissing']) {
					$entity['after']['templates'] = $this->afterForInnerDeleteMissing($entity['before']['templates'],
						$entity['after']['templates']
					);
				}

				if ($entity['before'] === $entity['after'] && count($entity) === 2) {
					unset($diff['updated'][$key]);
				}
				else {
					$stored_changes[$key]['templates'] = $entity['after']['templates'];
				}
			}
			unset($entity);

			if (!$diff['updated']) {
				unset($diff['updated']);
			}
		}

		if (!$options['createMissing']) {
			unset($diff['added']);
		}

		if (!$options['deleteMissing']) {
			unset($diff['removed']);
		}

		if (array_key_exists('updated', $diff)) {
			$new_updated = [];

			foreach ($diff['updated'] as $key => $entity) {
				$has_inner_entities = array_flip(array_keys($entity));
				unset($has_inner_entities['before'], $has_inner_entities['after']);
				$has_inner_entities = count($has_inner_entities) > 0;

				if ($has_inner_entities || array_key_exists($key, $stored_changes)) {
					if (!$options['updateExisting']) {
						$entity['after'] = $entity['before'];
					}

					$new_updated[] = $entity;
				}
				else {
					if ($options['updateExisting'] && $entity['after'] !== $entity['before']) {
						$new_updated[] = $entity;
					}
				}
			}

			if ($new_updated) {
				$diff['updated'] = $new_updated;
			}
			else {
				unset($diff['updated']);
			}
		}

		if ($stored_changes) {
			foreach ($stored_changes as $key => $stored_entry) {
				$entry = $diff['updated'][$key]['after'];

				foreach ($stored_entry as $entry_key => $entry_after_value) {
					if ($entry_after_value !== []) {
						$entry[$entry_key] = $entry_after_value;
					}
				}

				$diff['updated'][$key]['after'] = $entry;
			}
		}

		if (array_key_exists('updated', $diff)) {
			// Reset keys.
			$diff['updated'] = array_values($diff['updated']);

			// Make sure, key order is same in 'before' and 'after' arrays.
			foreach ($diff['updated'] as &$entity) {
				$order = array_flip(array_keys($entity['before']));
				$order = array_intersect_key($order, $entity['after']);
				$entity['after'] = array_merge($order, $entity['after']);
			}
			unset($entity);
		}

		return $diff;
	}

	/**
	 * Create "after" that contains all entries from "before" and "after" combined.
	 *
	 * @param array $before
	 * @param array $after
	 *
	 * @return array
	 */
	protected function afterForInnerCreateMissing(array $before, array $after): array {
		$missing = [];

		foreach ($after as $after_entity) {
			$found = false;

			foreach ($before as $before_entity) {
				if ($before_entity === $after_entity) {
					$found = true;
					break;
				}
			}

			if (!$found) {
				$missing[] = $after_entity;
			}
		}

		return array_merge($before, $missing);
	}

	/**
	 * Create "after" that contains only entries from "after" that were also present in "before".
	 *
	 * @param array $before
	 * @param array $after
	 *
	 * @return array
	 */
	protected function afterForInnerDeleteMissing(array $before, array $after): array {
		$new_after = [];

		foreach ($after as $after_entity) {
			$found = false;

			foreach ($before as $before_entity) {
				if ($before_entity === $after_entity) {
					$found = true;
					break;
				}
			}

			if ($found) {
				$new_after[] = $after_entity;
			}
		}

		return $new_after;
	}
}
