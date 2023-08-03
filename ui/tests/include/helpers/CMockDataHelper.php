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
class CMockDataHelper {

	private static $objectids = [];

	/**
	 * Converts an object definition to API create request parameters.
	 *
	 * @param array  $object          Object definition.
	 * @param string $object[][0]     The ID used for future object references and as default `key_`, `host`, etc..
	 * @param array  $object[][1]     (optional) Additional object parameters to pass to the create method.
	 * @param int    $object[][2]     (optional) Starting point for generating a range of objects, adds postfix to ID.
	 * @param int    $object[][3]     (optional) End of postfix range.
	 *
	 * @param array  $name_fields     Fields like `key_`, `host`, etc., to be replaced by the object ID.
	 * @param array  $default_params  (optional) Default object parameters to pass to the create method. Can be
	 *                                overridden by fields specified in $object[1].
	 *
	 * @return array
	 */
	private static function convertObjectDefinition(array $object, array $name_fields,
			array $default_params = []): array {
		[$id, $params, $from, $to] = $object + [
			1 => [],
			2 => 0,
			3 => 0
		];

		$params += $default_params;

		if ($from == 0 && $to == 0) {
			foreach ($name_fields as $name_field) {
				if (!array_key_exists($name_field, $params)) {
					$params = [$name_field => $id] + $params;
				}
			}

			return [$params];
		}

		$_params = [];

		for ($i = $from; $i <= $to; $i++) {
			$_id = $id.'.'.$i;

			foreach ($name_fields as $name_field) {
				$params = [$name_field => $_id] + $params;
			}

			$_params[] = $params;
		}

		return $_params;
	}

	/**
	 * Convert an object definition to API create method parameters.
	 *
	 * @param array  $object
	 * @param string $object_type
	 *
	 * @return array
	 */
	private static function mockObject(array $object, string $object_type): array {
		switch ($object_type) {
			case 'host_group':
				return self::mockHostGroup($object);

			case 'template_group':
				return self::mockTemplateGroup($object);

			case 'host':
				return self::mockHost($object);

			case 'discovered_host':
				unset($object[1]['parent_hostid']);

				return self::mockHost($object);

			case 'host_prototype':
				return self::mockHostPrototype($object);

			case 'template':
				return self::mockTemplate($object);

			case 'item':
				return self::mockItem($object);

			case 'discovered_item':
				unset($object[1]['parent_itemid']);

				return self::mockItem($object);

			case 'item_prototype':
				return self::mockItemPrototype($object);

			case 'lld_rule':
				return self::mockLldRule($object);

		}
	}

	/**
	 * @see CMockDataHelper::convertObjectDefinition()
	 *
	 * @param array $group
	 *
	 * @return array
	 */
	public static function mockHostGroup(array $group): array {
		return self::convertObjectDefinition($group, ['name']);
	}


	/**
	 * @see CMockDataHelper::convertObjectDefinition()
	 *
	 * @param array $group
	 *
	 * @return array
	 */
	public static function mockTemplateGroup(array $group): array {
		return self::convertObjectDefinition($group, ['name']);
	}

	/**
	 * @see CMockDataHelper::convertObjectDefinition()
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	public static function mockHost(array $host): array {
		return self::convertObjectDefinition($host, ['host']);
	}

	/**
	 * @see CMockDataHelper::convertObjectDefinition()
	 *
	 * @param array $template
	 *
	 * @return array
	 */
	public static function mockTemplate(array $template): array {
		return self::convertObjectDefinition($template, ['host']);
	}

	/**
	 * @see CMockDataHelper::convertObjectDefinition()
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	public static function mockHostPrototype(array $host): array {
		$hosts = self::convertObjectDefinition($host, ['host']);

		foreach ($hosts as &$host) {
			$host['host'] .= ' {#LLD}';
		}
		unset($host);

		return $hosts;
	}

	/**
	 * @see CMockDataHelper::convertObjectDefinition()
	 *
	 * @param array $item
	 *
	 * @return array
	 */
	public static function mockItem(array $item): array {
		$items = self::convertObjectDefinition($item, ['key_', 'name'], [
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_STR
		]);

		foreach ($items as &$item) {
			if (array_key_exists('master_itemid', $item)) {
				$item['type'] = ITEM_TYPE_DEPENDENT;
			}
		}
		unset($item);

		return $items;
	}

	/**
	 * @see CMockDataHelper::convertObjectDefinition()
	 *
	 * @param array $item
	 *
	 * @return array
	 */
	public static function mockItemPrototype(array $item): array {
		$items = self::mockItem($item);

		foreach ($items as &$item) {
			$item['key_'] .= '[{#LLD}]';
			$item['name'] .= '[{#LLD}]';
		}
		unset($item);

		return $items;
	}

	/**
	 * @see CMockDataHelper::convertObjectDefinition()
	 *
	 * @param array $lld_rule
	 *
	 * @return array
	 */
	public static function mockLldRule(array $lld_rule): array {
		$lld_rules = self::convertObjectDefinition($lld_rule, ['key_', 'name'], [
			'type' => ITEM_TYPE_TRAPPER
		]);

		foreach ($lld_rules as &$lld_rule) {
			if (array_key_exists('master_itemid', $lld_rule)) {
				$lld_rule['type'] = ITEM_TYPE_DEPENDENT;
			}
		}
		unset($lld_rule);

		return $lld_rules;
	}

	/**
	 * Create objects through API.
	 *
	 * Binding between objects, properties happens using reference IDs. These are strings in `:{OBJECT_TYPE}:{ID}` form.
	 * F.e., ':host:some.host' - here the record ID of a host object with the ID 'some.host' will be placed.
	 *
	 * Note that the referenced object must be known (placed first), to be used further, e.g. a host definition must
	 * come first, to bind an item to it, a dependent item's master_itemid needs to be defined after master item was
	 * created, et cetera.
	 *
	 * Some relation properties are added from linked objects by default, e.g. last host group assigned to host.
	 * @see CMockDataHelper::addObjectDefaults()
	 *
	 * @param array   $object_sets  List of sets of object type => array of objects definitions.
	 *                              It is advised to specify one template or host per set, so its ID gets assigned to
	 *                              other objects in the set (e.g. items) by default.
	 */
	public static function createObjects(array $object_sets): void {
		DBconnect($error);

		static $host_object_types = ['host', 'template', 'host_prototype', 'discovered_host'];

		foreach ($object_sets as $object_set) {
			$last_hostid = null;
			$host_definitions = array_intersect_key($object_set, array_flip(['template', 'host', 'discovered_host']));

			if (count($host_definitions) == 1) {
				$object_type = key($host_definitions);
				$id = reset($host_definitions)[0][0];

				$last_hostid = ':'.$object_type.':'.$id;
			}

			foreach ($object_set as $object_type => $objects) {
				foreach ($objects as $object) {
					$object += [1 => []];
					$object[1] += self::getObjectDefaults($object_type, $last_hostid);
					self::processReferences($object[1]);

					$api_objects = self::mockObject($object, $object_type);

					$api_method = $object_type === 'lld_rule' ? 'discoveryrule' : str_replace('_', '', $object_type);
					$api_method .= '.create';

					switch ($object_type) {
						case 'discovered_host':
							$api_method = 'host.create';
							break;

						case 'discovered_item':
							$api_method = 'item.create';
							break;
					}

					$result = CDataHelper::call($api_method, $api_objects);
					$objectids = reset($result);
					$id_field = in_array($object_type, $host_object_types) ? 'host' : 'name';

					self::$objectids[$object_type][$object[0]] = reset($objectids);

					foreach ($api_objects as $i => $api_object) {
						self::$objectids[$object_type][$api_object[$id_field]] = $objectids[$i];
					}

					$discoveries = [];

					switch ($object_type) {
						case 'discovered_host':
							foreach ($api_objects as $i => $api_object) {
								$discoveries[] = [
									'hostid' => $objectids[$i],
									'parent_hostid' => $object[1]['parent_hostid'],
									'host' => $api_object[$id_field]
								];
							}

							DB::insert('host_discovery', $discoveries, false);

							DB::update('hosts', [
								'values' => ['flags' => ZBX_FLAG_DISCOVERY_CREATED],
								'where' => ['hostid' => $objectids]
							]);
							break;

						case 'discovered_item':
							foreach ($api_objects as $api_object) {
								$discoveries[] = [
									'itemid' => $objectids[$i],
									'parent_itemid' => $object[1]['parent_itemid'],
									'key_' => $api_object[$id_field]
								];
							}

							DB::insert('item_discovery', $discoveries);

							DB::update('items', [
								'values' => ['flags' => ZBX_FLAG_DISCOVERY_CREATED],
								'where' => ['itemid' => $objectids]
							]);
							break;
					}
				}
			}
		}
	}

	/**
	 * Returns default values for API object relation fields, e.g. `hostid` of last known host or template for an item.
	 *
	 * @param string $object_type
	 * @param string $last_hostid
	 *
	 * @return array
	 */
	private static function getObjectDefaults(string $object_type, string $last_hostid = null): array {
		switch ($object_type) {
			case 'template':
				return [
					'groups' => [
						['groupid' => end(self::$objectids['template_group'])]
					]
				];

			case 'host':
				return [
					'groups' => [
						['groupid' => end(self::$objectids['host_group'])]
					]
				];

			case 'host_prototype':
				return [
					'groupLinks' => [
						['groupid' => end(self::$objectids['host_group'])]
					],
					'ruleid' => end(self::$objectids['lld_rule'])
				];

			case 'item_prototype':
				return [
					'hostid' => $last_hostid,
					'ruleid' => end(self::$objectids['lld_rule'])
				];

			case 'lld_rule':
				return ['hostid' => $last_hostid];

			case 'item':
				return ['hostid' => $last_hostid];

			case 'discovered_host':
				return [
					'groups' => [
						['groupid' => end(self::$objectids['host_group'])]
					],
					'parent_hostid' => end(self::$objectids['host_prototype'])
				];

			case 'discovered_item':
				return [
					'hostid' => $last_hostid,
					'parent_itemid' => end(self::$objectids['item_prototype'])
				];
		}

		return [];
	}

	/**
	 * Parses a reference ID into its parts.
	 *
	 * @return array|null Array containing object_type and id, if reference matched.
	 */
	private static function parseReference(string $referenceid): ?array {
		$object_type = strtok($referenceid, ':');

		return (!is_string($object_type) || !array_key_exists($object_type, self::$objectids))
			? null
			: ['object_type' => $object_type, 'id' => strtok(':')];
	}

	/**
	 * Check for, and replace a reference ID with the corresponding object's record ID.
	 *
	 * @param array  $object    Array containing the referenced property.
	 * @param string $property  The reference key.
	 */
	private static function processReference(array &$object, string $property): void {
		if (!is_string($object[$property]) || $object[$property][0] !== ':') {
			return;
		}

		$matched_object = self::parseReference($object[$property]);

		if ($matched_object === null) {
			return;
		}

		$object[$property] = self::$objectids[$matched_object['object_type']][$matched_object['id']];
	}

	/**
	 * Replace all references with record IDs in an array recursively.
	 *
	 * @param array $array
	 */
	public static function processReferences(array &$array): void {
		foreach ($array as $key => &$value) {
			is_array($value) ? self::processReferences($value) : self::processReference($array, $key);
		}
		unset($value);
	}

	/**
	 * Delete inserted objects from the database and reset internal data.
	 */
	public static function cleanUp(): void {
		if (array_key_exists('template', self::$objectids)) {
			CDataHelper::call('template.delete', array_values(self::$objectids['template']));
		}

		if (array_key_exists('host', self::$objectids)) {
			CDataHelper::call('host.delete', array_values(self::$objectids['host']));
		}

		if (array_key_exists('template_group', self::$objectids)) {
			CDataHelper::call('templategroup.delete', array_values(self::$objectids['template_group']));
		}

		if (array_key_exists('host_group', self::$objectids)) {
			CDataHelper::call('hostgroup.delete', array_values(self::$objectids['host_group']));
		}

		self::$objectids = [];
	}
}
