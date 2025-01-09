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
		$objects += array_fill_keys(['host_groups', 'hosts', 'triggers', 'roles', 'user_groups', 'users', 'actions',
			'events', 'alerts'
		], []);

		try {
			self::createHostGroups($objects['host_groups']);
			self::createHosts($objects['hosts']);
			self::createTriggers($objects['triggers']);
			self::createRoles($objects['roles']);
			self::createUserGroups($objects['user_groups']);
			self::createUsers($objects['users']);
			self::createActions($objects['actions']);
			self::createEvents($objects['events']);
			self::createAlerts($objects['alerts']);
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

		$items = [];

		foreach ($hosts as &$host) {
			$host += [
				'groups' => [
					['groupid' => end(self::$objectids['host_group'])]
				]
			];

			if (array_key_exists('items', $host)) {
				foreach ($host['items'] as $item) {
					$items[] = $item + ['hostid' => ':host:'.$host['host']];
				}

				unset($host['items']);
			}
		}
		unset($host);

		self::convertHostReferences($hosts);

		$result = CDataHelper::call('host.create', $hosts);

		foreach ($hosts as $host) {
			self::$objectids['host'][$host['host']] = array_shift($result['hostids']);
		}

		self::createItems($items);
	}

	public static function convertHostReferences(array &$hosts): void {
		self::convertPropertyReference($hosts, 'hostid');
		self::convertPropertyReference($hosts, 'groups.groupid');
		self::convertPropertyReference($hosts, 'templates.templateid');
		self::convertPropertyReference($hosts, 'proxyid');
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

	private static function createRoles(array $roles): void {
		if (!$roles) {
			return;
		}

		self::convertRoleReferences($roles);

		$result = CDataHelper::call('role.create', $roles);

		foreach ($roles as $role) {
			self::$objectids['role'][$role['name']] = array_shift($result['roleids']);
		}
	}

	public static function convertRoleReferences(array &$roles): void {
		self::convertPropertyReference($roles, 'roleid');
		self::convertPropertyReference($roles, 'users.userid');
		self::convertPropertyReference($roles, 'users.roleid');
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

		$medias = [];

		foreach ($users as &$user) {
			$user += [
				'roleid' => end(self::$objectids['role']),
				'usrgrps' => [
					['usrgrpid' => end(self::$objectids['user_group'])]
				]
			];

			if (array_key_exists('medias', $user)) {
				$medias[$user['username']] = true;
			}
		}
		unset($user);

		self::convertUserReferences($users);

		$result = CDataHelper::call('user.create', $users);

		foreach ($users as $user) {
			self::$objectids['user'][$user['username']] = array_shift($result['userids']);
		}

		if ($medias) {
			$result = CDataHelper::call('user.get', [
				'userids' => array_intersect_key(self::$objectids['user'], $medias),
				'output' => ['username'],
				'selectMedias' => ['mediaid', 'sendto']
			]);

			foreach ($result as $user) {
				foreach ($user['medias'] as $media) {
					foreach ($media['sendto'] as $email) {
						self::$objectids['media'][$email][$user['username']] = $media['mediaid'];
					}
				}
			}
		}
	}

	public static function convertUserReferences(array &$users): void {
		self::convertPropertyReference($users, 'userid');
		self::convertPropertyReference($users, 'roleid');
		self::convertPropertyReference($users, 'usrgrps.usrgrpid');
		self::convertPropertyReference($users, 'medias.mediaid');
		self::convertPropertyReference($users, 'role.roleid');
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

	private static function createEvents(array $events): void {
		if (!$events) {
			return;
		}

		foreach ($events as &$event) {
			$event = self::prepareEvent($event);
		}
		unset($event);

		self::convertEventReferences($events);

		$result = DB::insert('events', array_values($events));

		foreach ($events as $alias => $event) {
			self::$objectids['event'][$alias] = array_shift($result);
		}
	}

	/**
	 * @param array $event
	 *
	 * @return array
	 */
	public static function prepareEvent(array $event): array {
		$time = time();

		return $event + ($event['source'] == EVENT_SOURCE_TRIGGERS
			? [
				'object' => EVENT_OBJECT_TRIGGER,
				'objectid' => end(self::$objectids['trigger']),
				'clock' => $time,
				'value' => $time
			]
			: [
				'clock' => $time,
				'value' => $time
			]
		);
	}

	public static function convertEventReferences(array &$events): void {
		self::convertPropertyReferenceForObjects($events, 'objectid');
	}

	private static function createAlerts(array $alerts): void {
		if (!$alerts) {
			return;
		}

		foreach ($alerts as &$alert) {
			$alert = self::prepareAlert($alert);
		}
		unset($alert);

		self::convertAlertReferences($alerts);

		$result = DB::insert('alerts', array_values($alerts));

		foreach ($alerts as $alias => $alert) {
			self::$objectids['alert'][$alias] = array_shift($result);
		}
	}

	/**
	 * @param array $alert
	 *
	 * @return array
	 */
	public static function prepareAlert(array $alert): array {
		$defaults = [
			'clock' => time(),
			'message' => '',
			'parameters' => ''
		];

		if (array_key_exists('action', self::$objectids)) {
			$defaults['actionid'] = end(self::$objectids['action']);
		}

		if (array_key_exists('event', self::$objectids)) {
			$defaults['eventid'] = end(self::$objectids['event']);
		}

		if (array_key_exists('user', self::$objectids)) {
			$defaults['userid'] = end(self::$objectids['user']);
		}

		return $alert + $defaults;
	}

	public static function convertAlertReferences(array &$alerts): void {
		self::convertPropertyReferenceForObjects($alerts, 'alertid');
		self::convertPropertyReferenceForObjects($alerts, 'actionid');
		self::convertPropertyReferenceForObjects($alerts, 'eventid');
		self::convertPropertyReferenceForObjects($alerts, 'userid');
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
			elseif (array_key_exists($sub_property, $object[$property])) {
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
	 * @param mixed $value  The value possibly containing the reference. In case of matching object names (e.g. item
	 *                       inherited from template to host), the contained reference should include further specific
	 *                       parent object references, e.g.: `:item:item.key:host:my.name` vs
	 *                       `:items:item.key:template:my.name`.
	 * @param bool  $unset   Whether to unset the value from the $objectids array, if it is convertible.
	 */
	private static function convertValueReference(&$value, bool $unset = false): void {
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
		if (array_key_exists('alert', self::$objectids)) {
			DB::delete('alerts', ['alertid' => array_values(self::$objectids['alert'])]);
		}

		if (array_key_exists('event', self::$objectids)) {
			DB::delete('events', ['eventid' => array_values(self::$objectids['event'])]);
		}

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

	public static function getObjectFields(array $object, array $fields, ?array $except_fields = []) {
		$object = array_intersect_key($object, array_flip($fields));

		foreach ($except_fields as $path) {
			$nested_object = &$object;

			while (true) {
				[$field, $path] = explode('.', $path, 2);

				if (!array_key_exists($field, $nested_object)) {
					break;
				}

				$nested_object = &$nested_object[$field];

				if (strpos($path, '.') === false) {
					if (array_key_exists($path, $nested_object)) {
						unset($nested_object[$path]);
					}
					else {
						foreach ($nested_object as &$_object) {
							unset($_object[$path]);
						}
						unset($_object);
					}

					break;
				}
			}
			unset($nested_object);
		}

		return $object;
	}

	/**
	 * Replace any references in the given array. Note that convert{Object}References methods are preferred for results.
	 *
	 * @param array $array
	 */
	public static function resolveRequestReferences(array &$array) {
		foreach ($array as &$value) {
			is_array($value)
				? self::resolveRequestReferences($value)
				: self::convertValueReference($value);
		}
		unset($value);
	}
}
