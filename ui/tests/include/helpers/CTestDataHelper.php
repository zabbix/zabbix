<?php
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


/**
 * Class for generating DB objects or API data for tests.
 */
class CTestDataHelper {

	private static $objectids = [];

	/**
	 * Create objects using API.
	 * Usually only the object's "name/key" need to be provided. Some defaults (e.g. item's type, value_type) are mixed
	 * in and the "parent ID" property (e.g. host's groupid, item's hostid, etc.), if not specified, inferred by using
	 * the last known such object's ID. Previously defined objects can be linked by their reference ID, f.e.:
	 * `'master_itemid' => ':item:key.of.master.item'`.
	 *
	 * Call CTestDataHelper::cleanUp() to delete objects defined here along with child objects created during the test.
	 *
	 * @param array $objects
	 */
	public static function createObjects(array $objects): void {
		$objects += array_fill_keys(['template_groups', 'host_groups', 'templates', 'hosts'], []);

		try {
			self::createTemplateGroups($objects['template_groups']);
			self::createHostGroups($objects['host_groups']);
			self::createTemplates($objects['templates']);
			self::createHosts($objects['hosts']);
		}
		catch (Exception $e) {
			self::cleanUp();

			throw $e;
		}
	}

	/**
	 * @param array $template_groups
	 */
	private static function createTemplateGroups(array $template_groups): void {
		if (!$template_groups) {
			return;
		}

		$result = CDataHelper::call('templategroup.create', $template_groups);

		foreach ($template_groups as $template_group) {
			self::$objectids['template_groups'][$template_group['name']] = array_shift($result['groupids']);
		}
	}

	/**
	 * @param array $host_groups
	 */
	private static function createHostGroups(array $host_groups): void {
		if (!$host_groups) {
			return;
		}

		$result = CDataHelper::call('hostgroup.create', $host_groups);

		foreach ($host_groups as $host_group) {
			self::$objectids['host_groups'][$host_group['name']] = array_shift($result['groupids']);
		}
	}

	/**
	 * @param array $templates
	 */
	private static function createTemplates(array $templates): void {
		if (!$templates) {
			return;
		}

		$value_maps = [];
		$items = [];
		$lld_rules = [];

		foreach ($templates as &$template) {
			$template += [
				'groups' => [
					['groupid' => end(self::$objectids['template_groups'])]
				],
				'templates' => []
			];

			foreach ($template['groups'] as &$template_group) {
				self::processReference($template_group, 'groupid');
			}
			unset($template_group);

			foreach ($template['templates'] as &$_template) {
				self::processReference($_template, 'templateid');
			}
			unset($_template);

			if (array_key_exists('value_maps', $template)) {
				foreach ($template['value_maps'] as $value_map) {
					$value_maps[] = $value_map + ['hostid' => ':template:'.$template['host']];
				}

				unset($template['value_maps']);
			}

			if (array_key_exists('items', $template)) {
				foreach ($template['items'] as $item) {
					$items[] = $item + ['hostid' => ':template:'.$template['host']];
				}

				unset($template['items']);
			}

			if (array_key_exists('lld_rules', $template)) {
				foreach ($template['lld_rules'] as $lld_rule) {
					$lld_rules[] = $lld_rule + ['hostid' => ':template:'.$template['host']];
				}

				unset($template['lld_rules']);
			}
		}
		unset($template);

		$result = CDataHelper::call('template.create', $templates);

		foreach ($templates as $template) {
			self::$objectids['templates'][$template['host']] = array_shift($result['templateids']);
		}

		self::createValueMaps($value_maps);
		self::createItems($items);
		self::createLldRules($lld_rules);
	}

	/**
	 * @param array $hosts
	 */
	private static function createHosts(array $hosts): void {
		if (!$hosts) {
			return;
		}

		$value_maps = [];
		$items = [];
		$lld_rules = [];

		foreach ($hosts as &$host) {
			$host += [
				'groups' => [
					['groupid' => end(self::$objectids['host_groups'])]
				],
				'templates' => []
			];

			foreach ($host['groups'] as &$host_group) {
				self::processReference($host_group, 'groupid');
			}
			unset($host_group);

			foreach ($host['templates'] as &$template) {
				self::processReference($template, 'templateid');
			}
			unset($template);

			self::processReference($host, 'proxy_hostid');

			if (array_key_exists('value_maps', $host)) {
				foreach ($host['value_maps'] as $value_map) {
					$value_maps[] = $value_map + ['hostid' => ':host:'.$host['host']];
				}

				unset($host['value_maps']);
			}

			if (array_key_exists('items', $host)) {
				foreach ($host['items'] as $item) {
					$items[] = $item + ['hostid' => ':host:'.$host['host']];
				}

				unset($host['items']);
			}

			if (array_key_exists('lld_rules', $host)) {
				foreach ($host['lld_rules'] as $lld_rule) {
					$lld_rules[] = $lld_rule + ['hostid' => ':host:'.$host['host']];
				}

				unset($host['lld_rules']);
			}
		}
		unset($host);

		$result = CDataHelper::call('host.create', $hosts);

		foreach ($hosts as $host) {
			self::$objectids['hosts'][$host['host']] = array_shift($result['hostids']);
		}

		self::createValueMaps($value_maps);
		self::createItems($items);
		self::createLldRules($lld_rules);
	}

	/**
	 * @param array $value_maps
	 */
	private static function createValueMaps(array $value_maps): void {
		if (!$value_maps) {
			return;
		}

		foreach ($value_maps as &$value_map) {
			self::processReference($value_map, 'hostid');
		}
		unset($value_map);

		$result = CDataHelper::call('valuemap.create', $value_maps);

		foreach ($value_maps as $value_map) {
			self::$objectids['value_maps'][$value_map['name']] = array_shift($result['valuemapids']);
		}
	}

	/**
	 * @param array $items
	 */
	private static function createItems(array $items): void {
		if (!$items) {
			return;
		}

		$host_refs = [];
		$item_indexes = [];

		foreach ($items as $i => &$item) {
			$host_refs[$i] = $item['hostid'];

			self::processReference($item, 'hostid');
			self::processReference($item, 'valuemapid');
			self::processReference($item, 'interfaceid');

			$item = self::prepareItem($item);

			$item_indexes[$item['hostid']][':item:'.$item['key_']] = $i;
		}
		unset($item);

		$dep_items = [];

		foreach ($items as $i => $item) {
			if ($item['type'] == ITEM_TYPE_DEPENDENT) {
				if (!array_key_exists($item['hostid'], $item_indexes)
						|| !array_key_exists($item['master_itemid'], $item_indexes[$item['hostid']])) {
					throw new Exception(sprintf('Wrong master item ID for item with key "%1$s" on "%2$s".',
						$item['key_'], $host_refs[$i]
					));
				}

				$dep_items[$item_indexes[$item['hostid']][$item['master_itemid']]][$i] = $item;

				unset($items[$i]);
			}
		}

		do {
			foreach ($items as &$item) {
				self::processReference($item, 'master_itemid');
			}
			unset($item);

			$result = CDataHelper::call('item.create', array_values($items));

			$_items = [];

			foreach ($items as $i => $item) {
				self::$objectids['items'][$item['key_']][$host_refs[$i]] = array_shift($result['itemids']);

				if (array_key_exists($i, $dep_items)) {
					$_items += $dep_items[$i];
				}
			}
		} while ($items = $_items);
	}

	/**
	 * @param array $item
	 *
	 * @return array
	 */
	public static function prepareItem(array $item): array {
		$item += [
			'name' => $item['key_'],
			'type' => array_key_exists('master_itemid', $item) ? ITEM_TYPE_DEPENDENT : ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_STR
		];

		return $item;
	}

	/**
	 * @param array $item
	 * @param int   $from
	 * @param int   $to
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public static function prepareItemSet(array $item, int $from, int $to): array {
		if ($from > $to) {
			throw new Exception('Incorrect range parameters.');
		}

		$bracket_pos = strpos($item['key_'], '[');
		$items = [];

		for ($i = $from; $i <= $to; $i++) {
			$key_ = $bracket_pos === false
				? $item['key_'].'.'.$i
				: substr_replace($item['key_'], '.'.$i, $bracket_pos, 0);

			$items[] = self::prepareItem(['key_' => $key_] + $item);
		}

		return $items;
	}

	/**
	 * @param array $lld_rules
	 */
	private static function createLldRules(array $lld_rules): void {
		if (!$lld_rules) {
			return;
		}

		$host_refs = [];
		$item_prototypes = [];

		foreach ($lld_rules as $i => &$lld_rule) {
			$host_refs[$i] = $lld_rule['hostid'];

			self::processReference($lld_rule, 'hostid');
			self::processReference($lld_rule, 'interfaceid');
			self::processReference($lld_rule, 'master_itemid');

			$lld_rule = self::prepareLldRule($lld_rule);

			if (array_key_exists('item_prototypes', $lld_rule)) {
				foreach ($lld_rule['item_prototypes'] as $item_prototype) {
					$item_prototypes[] = $item_prototype
						+ ['hostid' => $host_refs[$i], 'ruleid' => ':lld_rule:'.$lld_rule['key_']];
				}

				unset($lld_rule['item_prototypes']);
			}
		}
		unset($lld_rule);

		$result = CDataHelper::call('discoveryrule.create', $lld_rules);

		foreach ($lld_rules as $i => $lld_rule) {
			self::$objectids['lld_rules'][$lld_rule['key_']][$host_refs[$i]] = array_shift($result['itemids']);
		}

		self::createItemPrototypes($item_prototypes);
	}

	/**
	 * @param array $lld_rule
	 *
	 * @return array
	 */
	public static function prepareLldRule(array $lld_rule): array {
		$lld_rule += [
			'name' => $lld_rule['key_'],
			'type' => array_key_exists('master_itemid', $lld_rule) ? ITEM_TYPE_DEPENDENT : ITEM_TYPE_TRAPPER
		];

		return $lld_rule;
	}

	/**
	 * @param array $lld_rule
	 * @param int   $from
	 * @param int   $to
	 *
	 * @return array
	 */
	public static function prepareLldRuleSet(array $lld_rule, int $from, int $to): array {
		if ($from > $to) {
			throw new Exception('Incorrect range parameters.');
		}

		$bracket_pos = strpos($lld_rule['key_'], '[');
		$lld_rules = [];

		for ($i = $from; $i <= $to; $i++) {
			$key_ = $bracket_pos === false
				? $lld_rule['key_'].'.'.$i
				: substr_replace($lld_rule['key_'], '.'.$i, $bracket_pos, 0);

			$lld_rules[] = self::prepareLldRule(['key_' => $key_] + $lld_rule);
		}

		return $lld_rules;
	}

	/**
	 * @param array $items
	 */
	private static function createItemPrototypes(array $items): void {
		if (!$items) {
			return;
		}

		$host_refs = [];
		$discovered_items = [];
		$item_indexes = [];

		foreach ($items as $i => &$item) {
			$host_refs[$i] = $item['hostid'];

			self::processReference($item, 'hostid');
			self::processReference($item, 'ruleid');
			self::processReference($item, 'valuemapid');
			self::processReference($item, 'interfaceid');

			$item = self::prepareItemPrototype($item);

			if (array_key_exists('discovered_items', $item)) {
				foreach ($item['discovered_items'] as $discovered_item) {
					$discovered_items[] = $discovered_item + [
						'hostid' => $host_refs[$i],
						'item_prototypeid' => ':item_prototype:'.$item['key_']
					];
				}

				unset($item['discovered_items']);
			}

			$item_indexes[$item['ruleid']][':item_prototype:'.$item['key_']] = $i;
		}
		unset($item);

		$dep_items = [];

		foreach ($items as $i => $item) {
			if ($item['type'] == ITEM_TYPE_DEPENDENT
					&& strpos($item['master_itemid'], ':item_prototype:') === 0) {
				if (!array_key_exists($item['ruleid'], $item_indexes)
						|| !array_key_exists($item['master_itemid'], $item_indexes[$item['ruleid']])) {
					throw new Exception(sprintf('Wrong master item ID for item prototype with key "%1$s" on "%2$s".',
						$item['key_'], $host_refs[$i]
					));
				}

				$dep_items[$item_indexes[$item['ruleid']][$item['master_itemid']]][$i] = $item;

				unset($items[$i]);
			}
		}

		do {
			foreach ($items as &$item) {
				self::processReference($item, 'master_itemid');
			}
			unset($item);

			$result = CDataHelper::call('itemprototype.create', $items);

			$_items = [];

			foreach ($items as $i => $item) {
				self::$objectids['item_prototypes'][$item['key_']][$host_refs[$i]] = array_shift($result['itemids']);

				if (array_key_exists($i, $dep_items)) {
					$_items += $dep_items[$i];
				}
			}
		} while ($items = $_items);

		self::createDiscoveredItems($discovered_items);
	}

	/**
	 * @param array $item
	 *
	 * @return array
	 */
	public static function prepareItemPrototype(array $item): array {
		$item += [
			'name' => $item['key_'],
			'type' => array_key_exists('master_itemid', $item) ? ITEM_TYPE_DEPENDENT : ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_STR
		];

		return $item;
	}

	/**
	 * @param array $item
	 * @param int   $from
	 * @param int   $to
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public static function prepareItemPrototypeSet(array $item, int $from, int $to): array {
		if ($from > $to) {
			throw new Exception('Incorrect range parameters.');
		}

		$bracket_pos = strpos($item['key_'], '[');
		$items = [];

		for ($i = $from; $i <= $to; $i++) {
			$key_ = $bracket_pos === false
				? $item['key_'].'.'.$i
				: substr_replace($item['key_'], '.'.$i, $bracket_pos, 0);

			$items[] = self::prepareItemPrototype(['key_' => $key_] + $item);
		}

		return $items;
	}

	/**
	 * @param array $discovered_items
	 */
	private static function createDiscoveredItems(array $discovered_items): void {
		if (!$discovered_items) {
			return;
		}

		$host_refs = [];
		$item_indexes = [];
		$item_prototypeids = [];

		foreach ($discovered_items as $i => &$item) {
			$host_refs[$i] = $item['hostid'];

			self::processReference($item, 'hostid');
			self::processReference($item, 'item_prototypeid');
			self::processReference($item, 'valuemapid');
			self::processReference($item, 'interfaceid');

			$item = self::prepareItem($item);

			$item_indexes[$item['item_prototypeid']][':discovered_item:'.$item['key_']] = $i;

			$item_prototypeids[$i] = $item['item_prototypeid'];
			unset($item['item_prototypeid']);
		}
		unset($item);

		$dep_items = [];

		foreach ($discovered_items as $i => &$item) {
			if ($item['type'] == ITEM_TYPE_DEPENDENT && strpos($item['master_itemid'], ':discovered_item:') === 0) {
				if (!array_key_exists($item_prototypeids[$i], $item_indexes)
						|| !array_key_exists($item['master_itemid'], $item_indexes[$item_prototypeids[$i]])) {
					throw new Exception(sprintf('Wrong master item ID for discovered item with key "%1$s" on "%2$s".',
						$item['key_'], $host_refs[$i]
					));
				}

				$dep_items[$item_indexes[$item_prototypeids[$i]][$item['master_itemid']]][$i] = $item;

				unset($discovered_items[$i]);
			}
		}
		unset($item);

		do {
			foreach ($discovered_items as &$item) {
				self::processReference($item, 'master_itemid');
			}
			unset($item);

			$result = CDataHelper::call('item.create', $discovered_items);

			$item_discoveries = [];
			$_discovered_items = [];

			foreach ($discovered_items as $i => $item) {
				$itemid = $result['itemids'][$i];

				self::$objectids['discovered_items'][$item['key_']][$host_refs[$i]] = $itemid;
				$item_discoveries[] = [
					'itemid' => $itemid,
					'parent_itemid' => $item_prototypeids[$i],
					'key_' => $item['key_']
				];

				if (array_key_exists($i, $dep_items)) {
					$_discovered_items += $dep_items[$i];
				}
			}

			DB::insert('item_discovery', $item_discoveries);

			DB::update('items', [
				'values' => ['flags' => ZBX_FLAG_DISCOVERY_CREATED],
				'where' => ['itemid' => $result['itemids']]
			]);
		} while ($discovered_items = $_discovered_items);
	}

	/**
	 * Check for, and replace a reference ID with the corresponding object's record ID.
	 *
	 * @param array  $object    Array containing the referenced property.
	 * @param string $property  The reference key. A "." symbol is used as a separator for nested property references,
	 *                          f.e., `templates.templateid`. In case of matching object names (e.g. item inherited from
	 *                          template to host), the contained reference should include further specific parent object
	 *                          references, e.g.: `:item:item.key:host:my.name` vs `:items:item.key:template:my.name`.
	 */
	private static function processReference(array &$object, string $property): void {
		if (strpos($property, '.') !== false) {
			[$property, $sub_property] = explode('.', $property, 2);

			if (is_string(key($object))) {
				if (!array_key_exists($property, $object)) {
					return;
				}

				self::processReference($object[$property], $sub_property);
			}
			else {
				foreach ($object as &$_object) {
					if (!array_key_exists($property, $_object)) {
						continue;
					}

					self::processReference($_object[$property], $sub_property);
				}
				unset($_object);
			}

			return;
		}

		if (!array_key_exists($property, $object) || !is_string($object[$property]) || $object[$property][0] !== ':') {
			return;
		}

		$colon_positions = [0];
		$p = 0;

		while ($p = strpos($object[$property], ':', $p + 1)) {
			if ($object[$property][$p - 1] !== '\\') {
				$colon_positions[] = $p;
			}
		}

		if (count($colon_positions) % 2 != 0 || !isset($object[$property][end($colon_positions) + 1])) {
			return;
		}

		$object_type = substr($object[$property], $colon_positions[0] + 1, $colon_positions[1] - 1).'s';
		$name = substr($object[$property], $colon_positions[1] + 1,
			array_key_exists(2, $colon_positions)
				? $colon_positions[2] - $colon_positions[1] - 1
				: strlen($object[$property]) - $colon_positions[1] - 1
		);

		unset($colon_positions[0], $colon_positions[1]);

		if (!array_key_exists($object_type, self::$objectids)
				|| !array_key_exists($name, self::$objectids[$object_type])
				|| ($colon_positions && !is_array(self::$objectids[$object_type][$name]))) {
			return;
		}

		if (!$colon_positions) {
			$value = self::$objectids[$object_type][$name];

			while (is_array($value)) {
				$value = end($value);
			}

			$object[$property] = $value;
			return;
		}

		$value = self::$objectids[$object_type][$name];

		while ($colon_positions) {
			if (!is_array($value))  {
				return;
			}

			$colon_start = array_shift($colon_positions);
			array_shift($colon_positions);

			$ref = $colon_positions
				? substr($object[$property], $colon_start, reset($colon_positions) - $colon_start - 1)
				: substr($object[$property], $colon_start);

			if (!array_key_exists($ref, $value)) {
				return;
			}

			$value = $value[$ref];
		}

		$object[$property] = $value;
	}

	/**
	 * Replace all references with record IDs in an array recursively.
	 *
	 * @param string $method
	 * @param array $params
	 */
	public static function processReferences(string $method, array &$params): void {
		$object_type = substr($method, 0, strpos($method, '.'));

		$ref_fields = [];

		switch ($object_type) {
			case 'host':
				$ref_fields = ['hostid', 'groups.groupid', 'templates.templateid'];
				break;

			case 'item':
				$ref_fields = ['itemid', 'hostid', 'valuemapid', 'interfaceid', 'master_itemid'];
				break;

			case 'discoveryrule':
				$ref_fields = ['itemid', 'hostid', 'interfaceid', 'master_itemid'];
				break;

			case 'itemprototype':
				$ref_fields = ['itemid', 'hostid', 'ruleid', 'valuemapid', 'interfaceid', 'master_itemid'];
				break;
		}

		if (is_string(key($params))) {
			foreach ($ref_fields as $ref_field) {
				self::processReference($params, $ref_field);
			}
		}
		else {
			foreach ($params as &$object) {
				foreach ($ref_fields as $ref_field) {
					self::processReference($object, $ref_field);
				}
			}
			unset($object);
		}
	}

	/**
	 * Delete inserted objects from the database and reset internal data.
	 */
	public static function cleanUp(): void {
		if (array_key_exists('templates', self::$objectids)) {
			CDataHelper::call('template.delete', array_values(self::$objectids['templates']));
		}

		if (array_key_exists('hosts', self::$objectids)) {
			CDataHelper::call('host.delete', array_values(self::$objectids['hosts']));
		}

		if (array_key_exists('template_groups', self::$objectids)) {
			CDataHelper::call('templategroup.delete', array_values(self::$objectids['template_groups']));
		}

		if (array_key_exists('host_groups', self::$objectids)) {
			CDataHelper::call('hostgroup.delete', array_values(self::$objectids['host_groups']));
		}

		self::$objectids = [];
	}
}
