<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
		$objects += array_fill_keys(['host_groups', 'hosts', 'triggers', 'roles', 'user_groups', 'users', 'actions'],
			[]
		);

		try {
			self::createHostGroups($objects['host_groups']);
			self::createHosts($objects['hosts']);
			self::createTriggers($objects['triggers']);
			self::createRoles($objects['roles']);
			self::createUserGroups($objects['user_groups']);
			self::createUsers($objects['users']);
			self::createActions($objects['actions']);
		}
		catch (Exception $e) {
			self::cleanUp();

			throw $e;
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
			self::$objectids['host_group'][$host_group['name']] = array_shift($result['groupids']);
		}
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
		$httptests = [];

		foreach ($hosts as &$host) {
			$host += [
				'groups' => [
					['groupid' => end(self::$objectids['host_group'])]
				]
			];

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

			if (array_key_exists('httptests', $host)) {
				foreach ($host['httptests'] as $httptest) {
					$httptests[] = $httptest + ['hostid' => ':host:'.$host['host']];
				}

				unset($host['httptests']);
			}
		}
		unset($host);

		self::convertHostReferences($hosts);

		$result = CDataHelper::call('host.create', $hosts);

		foreach ($hosts as $host) {
			self::$objectids['host'][$host['host']] = array_shift($result['hostids']);
		}

		self::createValueMaps($value_maps);
		self::createItems($items);
		self::createLldRules($lld_rules);
		self::createHttptests($httptests);
	}

	public static function convertHostReferences(array &$hosts): void {
		self::convertPropertyReference($hosts, 'hostid');
		self::convertPropertyReference($hosts, 'groups.groupid');
		self::convertPropertyReference($hosts, 'templates.templateid');
		self::convertPropertyReference($hosts, 'proxyid');
	}

	/**
	 * @param array $value_maps
	 */
	private static function createValueMaps(array $value_maps): void {
		if (!$value_maps) {
			return;
		}

		self::convertValueMapReferences($value_maps);

		$result = CDataHelper::call('valuemap.create', $value_maps);

		foreach ($value_maps as $value_map) {
			self::$objectids['value_map'][$value_map['name']] = array_shift($result['valuemapids']);
		}
	}

	public static function convertValueMapReferences(array &$value_maps): void {
		self::convertPropertyReference($value_maps, 'valuemapid');
		self::convertPropertyReference($value_maps, 'hostid');

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
			self::convertItemReferences($items);

			$result = CDataHelper::call('item.create', array_values($items));

			$_items = [];

			foreach ($items as $i => $item) {
				self::$objectids['item'][$item['key_']][$host_refs[$i]] = array_shift($result['itemids']);

				if (array_key_exists($i, $dep_items)) {
					$_items += $dep_items[$i];
				}
			}
		} while ($items = $_items);
	}

	public static function convertItemReferences(array &$items): void {
		self::convertPropertyReference($items, 'itemid');
		self::convertPropertyReference($items, 'hostid');
		self::convertPropertyReference($items, 'valuemapid');
		self::convertPropertyReference($items, 'interfaceid');
		self::convertPropertyReference($items, 'master_itemid');
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
	 * @param array $lld_rules
	 */
	private static function createLldRules(array $lld_rules): void {
		if (!$lld_rules) {
			return;
		}

		$host_refs = [];
		$item_prototypes = [];
		$host_prototypes = [];
		$trigger_prototypes = [];
		$graph_prototypes = [];

		foreach ($lld_rules as $i => &$lld_rule) {
			$host_refs[$i] = $lld_rule['hostid'];

			$lld_rule = self::prepareLldRule($lld_rule);
			$rule_reference = ['ruleid' => ':lld_rule:'.$lld_rule['key_'].$host_refs[$i]];

			if (array_key_exists('item_prototypes', $lld_rule)) {
				foreach ($lld_rule['item_prototypes'] as $item_prototype) {
					$item_prototypes[] = $item_prototype + ['hostid' => $host_refs[$i]] + $rule_reference;
				}

				unset($lld_rule['item_prototypes']);
			}

			if (array_key_exists('trigger_prototypes', $lld_rule)) {
				foreach ($lld_rule['trigger_prototypes'] as $alias => $trigger_prototype) {
					$trigger_prototypes[$alias] = $trigger_prototype;
				}

				unset($lld_rule['trigger_prototypes']);
			}

			if (array_key_exists('host_prototypes', $lld_rule)) {
				foreach ($lld_rule['host_prototypes'] as $host_prototype) {
					$host_prototypes[] = $host_prototype + $rule_reference;
				}

				unset($lld_rule['host_prototypes']);
			}

			if (array_key_exists('graph_prototypes', $lld_rule)) {
				foreach ($lld_rule['graph_prototypes'] as $alias => $graph_prototype) {
					$graph_prototypes[$alias] = $graph_prototype + $rule_reference;
				}

				unset($lld_rule['graph_prototypes']);
			}
		}
		unset($lld_rule);

		self::convertLldRuleReferences($lld_rules);

		$result = CDataHelper::call('discoveryrule.create', $lld_rules);

		foreach ($lld_rules as $i => $lld_rule) {
			self::$objectids['lld_rule'][$lld_rule['key_']][$host_refs[$i]] = array_shift($result['itemids']);
		}

		self::createItemPrototypes($item_prototypes);
		self::createTriggerPrototypes($trigger_prototypes);
		self::createGraphPrototypes($graph_prototypes);
		self::createHostPrototypes($host_prototypes);
	}

	public static function convertLldRuleReferences(array &$lld_rules): void {
		self::convertPropertyReference($lld_rules, 'itemid');
		self::convertPropertyReference($lld_rules, 'hostid');
		self::convertPropertyReference($lld_rules, 'interfaceid');
		self::convertPropertyReference($lld_rules, 'master_itemid');
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

	private static function createHttptests(array $httptests): void {
		if (!$httptests) {
			return;
		}

		$host_refs = [];

		foreach ($httptests as $i => &$httptest) {
			$host_refs[$i] = $httptest['hostid'];
		}
		unset($httptest);

		self::convertHttptestReferences($httptests);

		$result = CDataHelper::call('httptest.create', $httptests);

		foreach ($httptests as $i => $httptest) {
			self::$objectids['httptest'][$httptest['name']][$host_refs[$i]] = array_shift($result['httptestids']);
		}
	}

	/**
	 * @param array $httptest
	 */
	public static function convertHttptestReferences(array &$httptest): void {
		self::convertPropertyReference($httptest, 'httptestid');
		self::convertPropertyReference($httptest, 'hostid');
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
			self::convertItemPrototypeReferences($items);

			$result = CDataHelper::call('itemprototype.create', $items);

			$_items = [];

			foreach ($items as $i => $item) {
				self::$objectids['item_prototype'][$item['key_']][$host_refs[$i]] = array_shift($result['itemids']);

				if (array_key_exists($i, $dep_items)) {
					$_items += $dep_items[$i];
				}
			}
		} while ($items = $_items);

		self::createDiscoveredItems($discovered_items);
	}

	public static function convertItemPrototypeReferences(array &$items): void {
		self::convertPropertyReference($items, 'itemid');
		self::convertPropertyReference($items, 'hostid');
		self::convertPropertyReference($items, 'ruleid');
		self::convertPropertyReference($items, 'valuemapid');
		self::convertPropertyReference($items, 'interfaceid');
		self::convertPropertyReference($items, 'master_itemid');
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
			self::convertDiscoveredItemReferences($discovered_items);

			$result = CDataHelper::call('item.create', $discovered_items);

			$item_discoveries = [];
			$_discovered_items = [];

			foreach ($discovered_items as $i => $item) {
				$itemid = $result['itemids'][$i];

				self::$objectids['discovered_item'][$item['key_']][$host_refs[$i]] = $itemid;
				$item_discoveries[] = [
					'itemid' => $itemid,
					'parent_itemid' => $item_prototypeids[$i],
					'key_' => $item['key_']
				];

				if (array_key_exists($i, $dep_items)) {
					$_discovered_items += $dep_items[$i];
				}
			}

			self::convertPropertyReference($item_discoveries, 'parent_itemid');

			DB::insert('item_discovery', $item_discoveries);

			DB::update('items', [
				'values' => ['flags' => ZBX_FLAG_DISCOVERY_CREATED],
				'where' => ['itemid' => $result['itemids']]
			]);
		} while ($discovered_items = $_discovered_items);
	}

	public static function convertDiscoveredItemReferences(array &$discovered_items): void {
		self::convertPropertyReference($discovered_items, 'itemid');
		self::convertPropertyReference($discovered_items, 'hostid');
		self::convertPropertyReference($discovered_items, 'item_prototypeid');
		self::convertPropertyReference($discovered_items, 'valuemapid');
		self::convertPropertyReference($discovered_items, 'interfaceid');
		self::convertPropertyReference($discovered_items, 'master_itemid');
	}

	private static function createTriggers(array $triggers): void {
		if (!$triggers) {
			return;
		}

		$trigger_aliases = array_keys($triggers);

		self::convertTriggerReferences($triggers);

		$result = CDataHelper::call('trigger.create', array_values($triggers));

		foreach ($trigger_aliases as $trigger_alias) {
			self::$objectids['trigger'][$trigger_alias] = array_shift($result['triggerids']);
		}
	}

	public static function convertTriggerReferences(array &$triggers): void {
		self::convertPropertyReferenceForObjects($triggers, 'triggerid');
		self::convertPropertyReferenceForObjects($triggers, 'dependencies.triggerid');
	}

	private static function createTriggerPrototypes(array $triggers): void {
		if (!$triggers) {
			return;
		}

		$trigger_aliases = array_keys($triggers);

		self::convertTriggerPrototypeReferences($triggers);

		$result = CDataHelper::call('triggerprototype.create', array_values($triggers));

		foreach ($trigger_aliases as $trigger_alias) {
			self::$objectids['trigger_prototype'][$trigger_alias] = array_shift($result['triggerids']);
		}
	}

	public static function convertTriggerPrototypeReferences(array &$triggers): void {
		self::convertPropertyReferenceForObjects($triggers, 'triggerid');
		self::convertPropertyReferenceForObjects($triggers, 'dependencies.triggerid');
	}


	/**
	 * @param array $graph_prototypes
	 */
	private static function createGraphPrototypes(array &$graph_prototypes): void {
		if (!$graph_prototypes) {
			return;
		}

		self::convertGraphPrototypeReferences($graph_prototypes);

		$result = CDataHelper::call('graphprototype.create', array_values($graph_prototypes));

		foreach ($graph_prototypes as $alias => &$graph_prototype) {
			$graph_prototype['graphid'] = array_shift($result['graphids']);

			self::$objectids['graph_prototype'][$alias] = $graph_prototype['graphid'];
		}
		unset($graph_prototype);
	}

	public static function convertGraphPrototypeReferences(array &$graph_prototypes): void {
		self::convertPropertyReferenceForObjects($graph_prototypes, 'graphid');
		self::convertPropertyReferenceForObjects($graph_prototypes, 'gitems.itemid');
	}

	/**
	 * @param array $host_prototypes
	 */
	private static function createHostPrototypes(array &$host_prototypes): void {
		if (!$host_prototypes) {
			return;
		}

		self::convertHostPrototypeReferences($host_prototypes);

		$result = CDataHelper::call('hostprototype.create', array_values($host_prototypes));

		foreach ($host_prototypes as &$host_prototype) {
			$host_prototype['hostid'] = array_shift($result['hostids']);

			self::$objectids['host_prototype'][$host_prototype['host']] = $host_prototype['hostid'];
		}
		unset($host_prototype);
	}

	public static function convertHostPrototypeReferences(array &$host_prototypes): void {
		self::convertPropertyReference($host_prototypes, 'hostid');
		self::convertPropertyReference($host_prototypes, 'ruleid');
		self::convertPropertyReference($host_prototypes, 'groupLinks.groupid');
		self::convertPropertyReference($host_prototypes, 'templates.templateid');
	}

	private static function createRoles(array $roles): void {
		if (!$roles) {
			return;
		}

		$result = CDataHelper::call('role.create', $roles);

		foreach ($roles as $role) {
			self::$objectids['role'][$role['name']] = array_shift($result['roleids']);
		}
	}

	private static function createUserGroups(array $user_groups): void {
		if (!$user_groups) {
			return;
		}

		self::convertUserGroupReferences($user_groups);

		$result = CDataHelper::call('usergroup.create', $user_groups);

		foreach ($user_groups as $user_group) {
			self::$objectids['user_group'][$user_group['name']] = array_shift($result['usrgrpids']);
		}
	}

	public static function convertUserGroupReferences(array &$user_groups): void {
		self::convertPropertyReference($user_groups, 'usrgrpid');
		self::convertPropertyReference($user_groups, 'rights.id');
		self::convertPropertyReference($user_groups, 'users.usrgrpid');
	}

	private static function createUsers(array $users): void {
		if (!$users) {
			return;
		}

		foreach ($users as &$user) {
			$user += [
				'roleid' => end(self::$objectids['role']),
				'usrgrps' => [
					['usrgrpid' => end(self::$objectids['user_group'])]
				]
			];
		}
		unset($user);

		self::convertUserReferences($users);

		$result = CDataHelper::call('user.create', $users);

		foreach ($users as $user) {
			self::$objectids['user'][$user['username']] = array_shift($result['userids']);
		}
	}

	public static function convertUserReferences(array &$users): void {
		self::convertPropertyReference($users, 'userid');
		self::convertPropertyReference($users, 'roleid');
		self::convertPropertyReference($users, 'usrgrps.usrgrpid');
	}

	private static function createActions(array $actions): void {
		if (!$actions) {
			return;
		}

		foreach ($actions as &$action) {
			if (array_key_exists('filter', $action) && array_key_exists('conditions', $action['filter'])) {
				$referenced_condition_types = [CONDITION_TYPE_HOST_GROUP, CONDITION_TYPE_HOST,
					CONDITION_TYPE_TRIGGER, CONDITION_TYPE_TEMPLATE, CONDITION_TYPE_DRULE,
					CONDITION_TYPE_PROXY
				];

				foreach ($action['filter']['conditions'] as &$condition) {
					if (in_array($condition['conditiontype'], $referenced_condition_types)) {
						self::convertPropertyReference($condition, 'value');
					}
				}
				unset($condition);
			}
		}
		unset($action);

		self::convertActionReferences($actions);

		$result = CDataHelper::call('action.create', $actions);

		foreach ($actions as $action) {
			self::$objectids['action'][$action['name']] = array_shift($result['actionids']);
		}
	}

	public static function convertActionReferences(array &$actions): void {
		self::convertPropertyReference($actions, 'actionid');
		self::convertPropertyReference($actions, 'operations.opmessage_grp.usrgrpid');
		self::convertPropertyReference($actions, 'operations.opmessage_usr.userid');
		self::convertPropertyReference($actions, 'operations.opcommand.scriptid');
		self::convertPropertyReference($actions, 'operations.opcommand_grp.groupid');
		self::convertPropertyReference($actions, 'operations.opcommand_hst.hostid');
		self::convertPropertyReference($actions, 'operations.opgroup.groupid');
		self::convertPropertyReference($actions, 'operations.optemplate.templateid');
	}

	/**
	 * Check for, and replace a reference ID in the given objects' property with the corresponding object's record ID.
	 *
	 * @param array  $objects   Array of objects containing the referenced property.
	 * @param string $property  The reference key. A "." symbol is used as a separator for nested property references,
	 *                          f.e., `templates.templateid`. In case of matching object names (e.g. item inherited from
	 *                          template to host), the contained reference should include further specific parent object
	 *                          references, e.g.: `:item:item.key:host:my.name` vs `:items:item.key:template:my.name`.
	 */
	private static function convertPropertyReference(array &$objects, string $property): void {
		is_numeric(key($objects))
			? self::convertPropertyReferenceForObjects($objects, $property)
			: self::convertPropertyReferenceForObject($objects, $property);
	}

	private static function convertPropertyReferenceForObjects(array &$objects, string $property): void {
		$nested = strpos($property, '.') !== false;

		if ($nested) {
			[$property, $sub_property] = explode('.', $property, 2);
		}

		foreach ($objects as &$object) {
			if (!array_key_exists($property, $object)) {
				continue;
			}
			elseif (!$nested) {
				self::convertValueReference($object[$property]);
				continue;
			}

			if (strpos($sub_property, '.') !== false) {
				self::convertPropertyReference($object[$property], $sub_property);
				continue;
			}

			if (is_numeric(key($object[$property]))) {
				foreach ($object[$property] as &$_object) {
					if (array_key_exists($sub_property, $_object)) {
						self::convertValueReference($_object[$sub_property]);
					}
				}
				unset($_object);
			}
			else {
				self::convertValueReference($object[$property][$sub_property]);
			}
		}
		unset($object);
	}

	private static function convertPropertyReferenceForObject(array &$object, string $property): void {
		$nested = strpos($property, '.') !== false;

		if ($nested) {
			[$property, $sub_property] = explode('.', $property, 2);
		}

		if (array_key_exists($property, $object)) {
			if ($nested) {
				is_numeric(key($object[$property]))
					? self::convertPropertyReferenceForObjects($object[$property], $sub_property)
					: self::convertPropertyReferenceForObject($object[$property], $sub_property);
			}
			else {
				self::convertValueReference($object[$property]);
			}
		}
	}

	public static function unsetDeletedObjectIds(array $objectids): void {
		foreach ($objectids as $objectid) {
			self::convertValueReference($objectid, true);
		}
	}

	public static function getConvertedValueReferences(array $values): array {
		self::convertValueReferences($values);

		return $values;
	}

	public static function convertValueReferences(array &$values): void {
		foreach ($values as &$value) {
			self::convertValueReference($value);
		}
		unset($value);
	}

	public static function getConvertedValueReference(string $value): string {
		self::convertValueReference($value);

		return $value;
	}

	/**
	 * Check for, and replace a reference ID in the given value with the corresponding object's record ID.
	 *
	 * @param string $value  The value possibly containing the reference. In case of matching object names (e.g. item
	 *                       inherited from template to host), the contained reference should include further specific
	 *                       parent object references, e.g.: `:item:item.key:host:my.name` vs
	 *                       `:items:item.key:template:my.name`.
	 * @param bool  $unset   Whether to unset the value from the $objectids array, if it is convertible.
	 */
	private static function convertValueReference(string &$value, bool $unset = false): void {
		if (!is_string($value) || $value === '' || $value[0] !== ':') {
			return;
		}

		$colon_positions = [0];
		$p = 0;

		while ($p = strpos($value, ':', $p + 1)) {
			if ($value[$p - 1] !== '\\') {
				$colon_positions[] = $p;
			}
		}

		if (count($colon_positions) % 2 != 0 || !isset($value[end($colon_positions) + 1])) {
			return;
		}

		$object_type = substr($value, $colon_positions[0] + 1, $colon_positions[1] - 1);
		$name = substr($value, $colon_positions[1] + 1,
			array_key_exists(2, $colon_positions)
				? $colon_positions[2] - $colon_positions[1] - 1
				: strlen($value) - $colon_positions[1] - 1
		);

		unset($colon_positions[0], $colon_positions[1]);

		if (!array_key_exists($object_type, self::$objectids)
				|| !array_key_exists($name, self::$objectids[$object_type])
				|| ($colon_positions && !is_array(self::$objectids[$object_type][$name]))) {
			return;
		}

		if (!$colon_positions) {
			$objectid = self::$objectids[$object_type][$name];

			if ($unset) {
				unset(self::$objectids[$object_type][$name]);

				if (!self::$objectids[$object_type]) {
					unset(self::$objectids[$object_type]);
				}
			}

			while (is_array($objectid)) {
				$objectid = end($objectid);
			}

			$value = $objectid;

			return;
		}

		$objectid = self::$objectids[$object_type][$name];

		while ($colon_positions) {
			if (!is_array($objectid)) {
				return;
			}

			$colon_start = array_shift($colon_positions);
			array_shift($colon_positions);

			$ref = $colon_positions
				? substr($value, $colon_start, reset($colon_positions) - $colon_start - 1)
				: substr($value, $colon_start);

			if (!array_key_exists($ref, $objectid)) {
				return;
			}

			$objectid = $objectid[$ref];
		}

		$value = $objectid;
	}

	/**
	 * Delete inserted objects from the database and reset internal data.
	 */
	public static function cleanUp(): void {
		if (array_key_exists('action', self::$objectids)) {
			CDataHelper::call('action.delete', array_values(self::$objectids['action']));
		}

		if (array_key_exists('user', self::$objectids)) {
			CDataHelper::call('user.delete', array_values(self::$objectids['user']));
		}

		if (array_key_exists('user_group', self::$objectids)) {
			CDataHelper::call('usergroup.delete', array_values(self::$objectids['user_group']));
		}

		if (array_key_exists('role', self::$objectids)) {
			CDataHelper::call('role.delete', array_values(self::$objectids['role']));
		}

		if (array_key_exists('host', self::$objectids)) {
			CDataHelper::call('host.delete', array_values(self::$objectids['host']));
		}

		if (array_key_exists('host_group', self::$objectids)) {
			CDataHelper::call('hostgroup.delete', array_values(self::$objectids['host_group']));
		}

		self::$objectids = [];
	}
}
