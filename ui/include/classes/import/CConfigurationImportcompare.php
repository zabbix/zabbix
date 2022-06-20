<?php declare(strict_types = 0);
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
	 * CConfigurationImportcompare constructor.
	 *
	 * @param array $options  import options "createMissing", "updateExisting" and "deleteMissing"
	 */
	public function __construct(array $options) {
		$this->uuid_structure = [
			'template_groups' => [],
			'host_groups' => [],
			'templates' => [
				'groups' => [],
				'items' => [
					'triggers' => []
				],
				'discovery_rules' => [
					'item_prototypes' => [
						'trigger_prototypes' => []
					],
					'trigger_prototypes' => [],
					'graph_prototypes' => [],
					'host_prototypes' => [
						'group_links' => []
					]
				],
				'dashboards' => [],
				'httptests' => [],
				'valuemaps' => []
			],
			'triggers' => [],
			'graphs' => []
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

			$diff = $this->compareArrayByUuid($before[$key], $after[$key]);

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

		if($object) {
			// Insert 'before' and/or 'after' at the beginning of array.
			$result = array_merge($object, $result);
		}

		return $result;
	}

	/**
	 * Compare two entities and separate all their keys into added/removed/updated.
	 *
	 * @param array $before
	 * @param array $after
	 *
	 * @return array
	 */
	protected function compareArrayByUuid(array $before, array $after): array {
		$diff = [
			'added' => [],
			'removed' => [],
			'updated' => []
		];

		$before = zbx_toHash($before, 'uuid');
		$after = zbx_toHash($after, 'uuid');

		$before_keys = array_keys($before);
		$after_keys = array_keys($after);

		$same_keys = array_intersect($before_keys, $after_keys);
		$added_keys = array_diff($after_keys, $before_keys);
		$removed_keys = array_diff($before_keys, $after_keys);

		foreach ($added_keys as $key) {
			$diff['added'][] = $after[$key];
		}

		foreach ($removed_keys as $key) {
			$diff['removed'][] = $before[$key];
		}

		foreach ($same_keys as $key) {
			if ($before[$key] != $after[$key]) {
				$diff['updated'][] = [
					'before' => $before[$key],
					'after' => $after[$key]
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
				elseif ($has_before_templates && !$has_after_templates) {
					$entity['after']['templates'] = [];

					// Make sure, precessed entry is last in both arrays. Otherwise it will break the comparison.
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
