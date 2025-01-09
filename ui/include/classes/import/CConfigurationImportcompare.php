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


/**
 * Class for preparing difference between import file and system.
 */
class CConfigurationImportcompare {
	/**
	 * @var array
	 */
	protected $options;

	/**
	 * @var array  shows which elements in import array tree contains uuid
	 */
	protected $uuid_structure;

	/**
	 * @var array  contains unique fields path for import entities
	 */
	protected $unique_fields_keys_by_type;

	/**
	 * CConfigurationImportcompare constructor.
	 *
	 * @param array $options  import options "createMissing", "updateExisting" and "deleteMissing"
	 */
	public function __construct(array $options) {
		$this->uuid_structure = [
			'template_groups' => [],
			'host_groups' => [],
			'templates' => [
				'items' => [
					'triggers' => []
				],
				'discovery_rules' => [
					'item_prototypes' => [
						'trigger_prototypes' => []
					],
					'trigger_prototypes' => [],
					'graph_prototypes' => [],
					'host_prototypes' => []
				],
				'dashboards' => [],
				'httptests' => [],
				'valuemaps' => []
			],
			'triggers' => [],
			'graphs' => []
		];

		$this->unique_fields_keys_by_type = [
			'template_groups' => ['name'],
			'host_groups' => ['name'],
			'templates' => ['template'],
			'items' => ['name', 'key'],
			'triggers' => ['name', 'expression', 'recovery_expression'],
			'dashboards' => ['name'],
			'httptests' => ['name'],
			'valuemaps' => ['name'],
			'discovery_rules' => ['name', 'key'],
			'item_prototypes' => ['name', 'key'],
			'trigger_prototypes' => ['name', 'expression', 'recovery_expression'],
			'graph_prototypes' => ['name', ['graph_items' => ['numeric_keys' => ['item' => 'host']]]],
			'host_prototypes' => ['host'],
			'graphs' => ['name', ['graph_items' => ['numeric_keys' => ['item' => 'host']]]]
		];

		$this->options = $options;
	}

	/**
	 * Performs comparison of import and export arrays and returns combined array that shows what was changed.
	 *
	 * @param array $export  data exported from current system
	 * @param array $import  data from import file
	 *
	 * @return array
	 */
	public function importcompare(array $export, array $import): array {
		// Leave only template related keys.
		$export = array_intersect_key($export, $this->uuid_structure);
		$import = array_intersect_key($import, $this->uuid_structure);

		return $this->compareByStructure($this->uuid_structure, $export, $import, $this->options);
	}

	/**
	 * Create separate comparison for each structured object.
	 * Warning: Recursion.
	 *
	 * @param array $structure
	 * @param array $before
	 * @param array $after
	 * @param array $options
	 *
	 * @return array
	 */
	protected function compareByStructure(array $structure, array $before, array $after, array $options): array {
		$result = [];

		foreach ($structure as $key => $sub_structure) {
			if ((!array_key_exists($key, $before) || !$before[$key])
				&& (!array_key_exists($key, $after) || !$after[$key])) {
				continue;
			}

			// Make sure, $key exists in both arrays.
			$before += [$key => []];
			$after += [$key => []];

			$diff = $this->compareArrayByUniqueness($before[$key], $after[$key], $key);


			if (array_key_exists('added', $diff)) {
				foreach ($diff['added'] as &$entity) {
					$entity = $this->compareByStructure($sub_structure, [], $entity, $options);
				}
				unset($entity);
			}

			if (array_key_exists('removed', $diff)) {
				foreach ($diff['removed'] as &$entity) {
					$entity = $this->compareByStructure($sub_structure, $entity, [], $options);
				}
				unset($entity);
			}

			if ($sub_structure && array_key_exists('updated', $diff)) {
				foreach ($diff['updated'] as &$entity) {
					$entity = $this->compareByStructure($sub_structure, $entity['before'], $entity['after'], $options);
				}
				unset($entity);
			}

			$diff = $this->applyOptions($options, $key, $diff);

			unset($before[$key], $after[$key]);

			if ($diff) {
				$result[$key] = $diff;
			}
		}

		$object = [];

		if ($before) {
			$object['before'] = $before;
		}

		if ($after) {
			$object['after'] = $after;
		}

		if ($object) {
			// Insert 'before' and/or 'after' at the beginning of array.
			$result = array_merge($object, $result);
		}

		return $result;
	}

	/**
	 * Compare two entities and separate all their keys into added/removed/updated.
	 * First entities gets compared by uuid then by its unique field values.
	 *
	 * @param array  $before
	 * @param array  $after
	 * @param string $type
	 *
	 * @return array
	 */
	protected function compareArrayByUniqueness(array $before, array $after, string $type): array {
		if (!$before && !$after) {
			return [];
		}

		$diff = [
			'added' => [],
			'removed' => [],
			'updated' => []
		];

		$before = $this->addUniquenessParameterByEntityType($before, $type);
		$after = $this->addUniquenessParameterByEntityType($after, $type);

		$same_entities = [];
		foreach ($after as $a_key => $after_entity) {
			if (!array_key_exists('uuid', $after_entity)) {
				unset($after[$a_key]);
				continue;
			}

			foreach ($before as $b_key => $before_entity) {
				if (array_key_exists('uuid', $before_entity) && $before_entity['uuid'] === $after_entity['uuid']) {
					unset($before_entity['uniqueness'], $after_entity['uniqueness']);

					$same_entities[$b_key]['before'] = $before_entity;
					$same_entities[$b_key]['after'] = $after_entity;

					unset($before[$b_key], $after[$a_key]);
					continue 2;
				}
			}

			foreach ($before as $b_key => $before_entity) {
				if ($before_entity['uniqueness'] === $after_entity['uniqueness']) {
					unset($before_entity['uniqueness'], $after_entity['uniqueness']);
					$before_entity['uuid'] = $after_entity['uuid'];

					$same_entities[$b_key]['before'] = $before_entity;
					$same_entities[$b_key]['after'] = $after_entity;

					unset($before[$b_key], $after[$a_key]);
					break;
				}
			}
		}

		$removed_entities = $before;
		$added_entities = $after;

		foreach ($added_entities as $entity) {
			unset($entity['uniqueness']);

			$diff['added'][] = $entity;
		}

		foreach ($removed_entities as $entity) {
			unset($entity['uniqueness']);

			$diff['removed'][] = $entity;
		}

		foreach ($same_entities as $entity) {
			$uuid = ['uuid' => null];

			if (array_diff_key($entity['before'], $uuid) != array_diff_key($entity['after'], $uuid)) {
				$diff['updated'][] = [
					'before' => $entity['before'],
					'after' => $entity['after']
				];
			}
		}

		foreach (['added', 'removed', 'updated'] as $key) {
			if (!$diff[$key]) {
				unset($diff[$key]);
			}
		}

		return $diff;
	}

	private function addUniquenessParameterByEntityType(array $entities, string $type): array {
		foreach ($entities as &$entity) {
			foreach ($this->unique_fields_keys_by_type[$type] as $unique_field_key) {
				$unique_values = $this->getUniqueValuesByFieldPath($entity, $unique_field_key);

				$entity['uniqueness'][] = $unique_values;
			}
			// To make unique entity string, get result values, get rid of value duplicates and sort them.
			$entity['uniqueness'] = array_unique($this->flatten($entity['uniqueness']));
			sort($entity['uniqueness']);

			$entity['uniqueness'] = implode('/', $entity['uniqueness']);
		}
		unset($entity);

		return $entities;
	}

	/**
	 * Get entity field values by giving field key path constructed.
	 *
	 * @param array        $entity    Entity.
	 * @param string|array $field_key Field key or field key path given.
	 */
	private function getUniqueValuesByFieldPath(array $entity, $field_key_path) {
		if (is_array($field_key_path)) {
			foreach ($field_key_path as $sub_key => $sub_field) {
				if ($sub_key !== 'numeric_keys') {
					$sub_entities = $entity[$sub_key];
				}
				else {
					if (is_array($sub_field)) {
						foreach ($sub_field as $key => $field) {
							foreach ($entity as $sub_entity) {
								$sub_entities[] = $sub_entity[$key];
							}

							$sub_field = $field;
							}
					}
					else {
						$sub_entities = $entity;
					}
				}

				$result = $this->getUniqueValuesByFieldPath($sub_entities, $sub_field);
			}
		}
		else {
			if (array_key_exists($field_key_path, $entity)){
				$result = $entity[$field_key_path];
			}
			else {
				$result = array_column($entity, $field_key_path);
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
	private function flatten(array $array): array {
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
	 * @param array  $options     import options
	 * @param string $entity_key  key of entity being processed
	 * @param array  $diff        diff for this entity
	 *
	 * @return array
	 */
	protected function applyOptions(array $options, string $entity_key, array $diff): array {
		$option_key_map = [
			'template_groups' => 'template_groups',
			'host_groups' => 'host_groups',
			'group_links' => 'host_groups',
			'groups' => 'template_groups',
			'templates' => 'templates',
			'items' => 'items',
			'triggers' => 'triggers',
			'discovery_rules' => 'discoveryRules',
			'item_prototypes' => 'discoveryRules',
			'trigger_prototypes' => 'discoveryRules',
			'graph_prototypes' => 'discoveryRules',
			'host_prototypes' => 'discoveryRules',
			'dashboards' => 'templateDashboards',
			'httptests' => 'httptests',
			'valuemaps' => 'valueMaps',
			'graphs' => 'graphs'
		];

		if (!array_key_exists($option_key_map[$entity_key], $options)) {
			return [];
		}

		$entity_options = $options[$option_key_map[$entity_key]];
		$stored_changes = [];

		if ($entity_key === 'templates' && array_key_exists('updated', $diff)) {
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

				if (!$options['templateLinkage']['createMissing'] && !$options['templateLinkage']['deleteMissing']) {
					$entity['after']['templates'] = $entity['before']['templates'];
				}
				elseif ($options['templateLinkage']['createMissing'] && !$options['templateLinkage']['deleteMissing']) {
					$entity['after']['templates'] = $this->afterForInnerCreateMissing($entity['before']['templates'],
						$entity['after']['templates']);
				}
				elseif ($options['templateLinkage']['deleteMissing'] && !$options['templateLinkage']['createMissing']) {
					$entity['after']['templates'] = $this->afterForInnerDeleteMissing($entity['before']['templates'],
						$entity['after']['templates']);
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

		if (!array_key_exists('createMissing', $entity_options) || !$entity_options['createMissing']) {
			unset($diff['added']);
		}

		if (!array_key_exists('deleteMissing', $entity_options) || !$entity_options['deleteMissing']) {
			unset($diff['removed']);
		}

		if (array_key_exists('updated', $diff)) {
			$new_updated = [];

			foreach ($diff['updated'] as $key => $entity) {
				$has_inner_entities = array_flip(array_keys($entity));
				unset($has_inner_entities['before'], $has_inner_entities['after']);
				$has_inner_entities = count($has_inner_entities) > 0;

				if ($has_inner_entities || array_key_exists($key, $stored_changes)
					|| $entity['after'] !== $entity['before']) {
					if (!array_key_exists('updateExisting', $entity_options) || !$entity_options['updateExisting']) {
						$entity['after'] = $entity['before'];
					}
					$new_updated[] = $entity;
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
