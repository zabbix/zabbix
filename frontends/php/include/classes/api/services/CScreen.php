<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
 * Class containing methods for operations with screens.
 */
class CScreen extends CApiService {

	protected $tableName = 'screens';
	protected $tableAlias = 's';
	protected $sortColumns = ['screenid', 'name'];

	/**
	 * Get screen data.
	 *
	 * @param array  $options
	 * @param bool   $options['editable']		only with read-write permission. Ignored for SuperAdmins
	 * @param int    $options['count']			count Hosts, returned column name is rowscount
	 * @param string $options['pattern']		search hosts by pattern in host names
	 * @param int    $options['limit']			limit selection
	 * @param string $options['order']			deprecated parameter (for now)
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];
		$user_data = self::$userData;

		$sql_parts = [
			'select'	=> ['screens' => 's.screenid'],
			'from'		=> ['screens' => 'screens s'],
			'where'		=> ['template' => 's.templateid IS NULL'],
			'order'		=> [],
			'group'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'screenids'					=> null,
			'userids'					=> null,
			'screenitemids'				=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectScreenItems'			=> null,
			'selectUsers'				=> null,
			'selectUserGroups'			=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		if ($options['countOutput'] !== null) {
			$count_output = true;
			$options['output'] = ['screenid'];
			$options['countOutput'] = null;
			$options['limit'] = null;
		}
		else {
			$count_output = false;
		}

		// Editable + permission check.
		if ($user_data['type'] != USER_TYPE_SUPER_ADMIN && $user_data['type'] != USER_TYPE_ZABBIX_ADMIN
				&& !$options['nopermissions']) {
			$public_screens = '';

			if ($options['editable']) {
				$permission = PERM_READ_WRITE;
			}
			else {
				$permission = PERM_READ;
				$public_screens = ' OR s.private='.PUBLIC_SHARING;
			}

			$user_groups = getUserGroupsByUserId($user_data['userid']);

			$sql_parts['where'][] = '(EXISTS ('.
					'SELECT NULL'.
					' FROM screen_user su'.
					' WHERE s.screenid=su.screenid'.
						' AND su.userid='.$user_data['userid'].
						' AND su.permission>='.$permission.
				')'.
				' OR EXISTS ('.
					'SELECT NULL'.
					' FROM screen_usrgrp sg'.
					' WHERE s.screenid=sg.screenid'.
						' AND '.dbConditionInt('sg.usrgrpid', $user_groups).
						' AND sg.permission>='.$permission.
				')'.
				' OR s.userid='.$user_data['userid'].
				$public_screens.
			')';
		}

		// screenids
		if (!is_null($options['screenids'])) {
			zbx_value2array($options['screenids']);
			$sql_parts['where'][] = dbConditionInt('s.screenid', $options['screenids']);
		}

		// userids
		if ($options['userids'] !== null) {
			zbx_value2array($options['userids']);

			$sql_parts['where'][] = dbConditionInt('s.userid', $options['userids']);
		}

		// screenitemids
		if (!is_null($options['screenitemids'])) {
			zbx_value2array($options['screenitemids']);

			$sql_parts['from']['screens_items'] = 'screens_items si';
			$sql_parts['where']['ssi'] = 'si.screenid=s.screenid';
			$sql_parts['where'][] = dbConditionInt('si.screenitemid', $options['screenitemids']);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('screens s', $options, $sql_parts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('screens s', $options, $sql_parts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sql_parts['limit'] = $options['limit'];
		}

		$screenids = [];
		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$res = DBselect($this->createSelectQueryFromParts($sql_parts), $sql_parts['limit']);
		while ($screen = DBfetch($res)) {
			$screenids[$screen['screenid']] = true;
			$result[$screen['screenid']] = $screen;
		}

		// editable + PERMISSION CHECK
		if ($user_data['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions'] && $screenids) {
			$db_screen_items = DBselect(
				'SELECT si.screenid,si.resourcetype,si.resourceid,si.style'.
				' FROM screens_items si'.
				' WHERE '.dbConditionInt('si.screenid', array_keys($screenids)).
					' AND '.dbConditionInt('si.resourcetype', [
						SCREEN_RESOURCE_HOST_INFO, SCREEN_RESOURCE_TRIGGER_INFO, SCREEN_RESOURCE_TRIGGER_OVERVIEW,
						SCREEN_RESOURCE_DATA_OVERVIEW, SCREEN_RESOURCE_HOSTGROUP_TRIGGERS,
						SCREEN_RESOURCE_HOST_TRIGGERS, SCREEN_RESOURCE_GRAPH, SCREEN_RESOURCE_SIMPLE_GRAPH,
						SCREEN_RESOURCE_PLAIN_TEXT, SCREEN_RESOURCE_CLOCK, SCREEN_RESOURCE_MAP, SCREEN_RESOURCE_SCREEN
					]).
					' AND si.resourceid<>0'
			);

			$screens = [];

			while ($db_screen_item = DBfetch($db_screen_items)) {
				if (!array_key_exists($db_screen_item['screenid'], $screens)) {
					$screens[$db_screen_item['screenid']] = [
						'groups' => [], 'hosts' => [], 'graphs' => [], 'items' => [], 'maps' => [], 'screens' => []
					];
				}

				switch ($db_screen_item['resourcetype']) {
					case SCREEN_RESOURCE_HOST_INFO:
					case SCREEN_RESOURCE_TRIGGER_INFO:
					case SCREEN_RESOURCE_TRIGGER_OVERVIEW:
					case SCREEN_RESOURCE_DATA_OVERVIEW:
					case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
						$screens[$db_screen_item['screenid']]['groups'][$db_screen_item['resourceid']] = true;
						break;

					case SCREEN_RESOURCE_HOST_TRIGGERS:
						$screens[$db_screen_item['screenid']]['hosts'][$db_screen_item['resourceid']] = true;
						break;

					case SCREEN_RESOURCE_GRAPH:
						$screens[$db_screen_item['screenid']]['graphs'][$db_screen_item['resourceid']] = true;
						break;

					case SCREEN_RESOURCE_SIMPLE_GRAPH:
					case SCREEN_RESOURCE_PLAIN_TEXT:
						$screens[$db_screen_item['screenid']]['items'][$db_screen_item['resourceid']] = true;
						break;

					case SCREEN_RESOURCE_CLOCK:
						if ($db_screen_item['style'] == TIME_TYPE_HOST) {
							$screens[$db_screen_item['screenid']]['items'][$db_screen_item['resourceid']] = true;
						}
						break;

					case SCREEN_RESOURCE_MAP:
						$screens[$db_screen_item['screenid']]['maps'][$db_screen_item['resourceid']] = true;
						break;

					case SCREEN_RESOURCE_SCREEN:
						$screens[$db_screen_item['screenid']]['screens'][$db_screen_item['resourceid']] = true;
						break;
				}
			}

			// groups
			$groups = [];

			foreach ($screens as $screenid => $resources) {
				foreach ($resources['groups'] as $groupid => $foo) {
					$groups[$groupid][$screenid] = true;
				}
			}

			if ($groups) {
				$db_groups = API::HostGroup()->get([
					'output' => [],
					'groupids' => array_keys($groups),
					'preservekeys' => true
				]);

				foreach ($groups as $groupid => $resources) {
					if (!array_key_exists($groupid, $db_groups)) {
						foreach ($resources as $screenid => $foo) {
							unset($screens[$screenid], $result[$screenid]);
						}
					}
				}
			}

			// hosts
			$hosts = [];

			foreach ($screens as $screenid => $resources) {
				foreach ($resources['hosts'] as $hostid => $foo) {
					$hosts[$hostid][$screenid] = true;
				}
			}

			if ($hosts) {
				$db_hosts = API::Host()->get([
					'output' => [],
					'hostids' => array_keys($hosts),
					'preservekeys' => true
				]);

				foreach ($hosts as $hostid => $resources) {
					if (!array_key_exists($hostid, $db_hosts)) {
						foreach ($resources as $screenid => $foo) {
							unset($screens[$screenid], $result[$screenid]);
						}
					}
				}
			}

			// graphs
			$graphs = [];

			foreach ($screens as $screenid => $resources) {
				foreach ($resources['graphs'] as $graphid => $foo) {
					$graphs[$graphid][$screenid] = true;
				}
			}

			if ($graphs) {
				$db_graphs = API::Graph()->get([
					'output' => [],
					'graphids' => array_keys($graphs),
					'preservekeys' => true
				]);

				foreach ($graphs as $graphid => $resources) {
					if (!array_key_exists($graphid, $db_graphs)) {
						foreach ($resources as $screenid => $foo) {
							unset($screens[$screenid], $result[$screenid]);
						}
					}
				}
			}

			// items
			$items = [];

			foreach ($screens as $screenid => $resources) {
				foreach ($resources['items'] as $itemid => $foo) {
					$items[$itemid][$screenid] = true;
				}
			}

			if ($items) {
				$db_items = API::Item()->get([
					'output' => [],
					'itemids' => array_keys($items),
					'webitems' => true,
					'preservekeys' => true
				]);

				foreach ($items as $itemid => $resources) {
					if (!array_key_exists($itemid, $db_items)) {
						foreach ($resources as $screenid => $foo) {
							unset($screens[$screenid], $result[$screenid]);
						}
					}
				}
			}

			// maps
			$maps = [];

			foreach ($screens as $screenid => $resources) {
				foreach ($resources['maps'] as $sysmapid => $foo) {
					$maps[$sysmapid][$screenid] = true;
				}
			}

			if ($maps) {
				$db_maps = API::Map()->get([
					'output' => [],
					'sysmapids' => array_keys($maps),
					'preservekeys' => true
				]);

				foreach ($maps as $sysmapid => $resources) {
					if (!array_key_exists($sysmapid, $db_maps)) {
						foreach ($resources as $screenid => $foo) {
							unset($screens[$screenid], $result[$screenid]);
						}
					}
				}
			}

			// screens
			$_screens = [];

			foreach ($screens as $screenid => $resources) {
				foreach ($resources['screens'] as $_screenid => $foo) {
					$_screens[$_screenid][$screenid] = true;
				}
			}

			if ($_screens) {
				$db_screens = API::Screen()->get([
					'output' => [],
					'screenids' => array_keys($_screens),
					'preservekeys' => true
				]);

				foreach ($_screens as $_screenid => $resources) {
					if (!array_key_exists($_screenid, $db_screens)) {
						foreach ($resources as $screenid => $foo) {
							unset($screens[$screenid], $result[$screenid]);
						}
					}
				}
			}
		}

		if ($count_output) {
			if ($options['groupCount'] !== null) {
				return [['rowscount' => count($result)]];
			}
			else {
				return count($result);
			}
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if ($options['preservekeys'] === null) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Validate vsize and hsize parameters.
	 *
	 * @param array $screen
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateScreenSize(array $screen) {
		foreach (['vsize', 'hsize'] as $field_name) {
			if (!array_key_exists($field_name, $screen)) {
				continue;
			}

			if (!zbx_is_int($screen[$field_name])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_s('Incorrect value for field "%1$s": %2$s.', $field_name, _('a numeric value is expected'))
				);
			}

			if ($screen[$field_name] < SCREEN_MIN_SIZE || $screen[$field_name] > SCREEN_MAX_SIZE) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_s('Incorrect value for field "%1$s": %2$s.', $field_name,
						_s('must be between "%1$s" and "%2$s"', SCREEN_MIN_SIZE, SCREEN_MAX_SIZE)
					)
				);
			}
		}
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $screens
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array $screens) {
		if (!$screens) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$user_data = self::$userData;

		$screen_db_fields = ['name' => null];

		foreach ($screens as &$screen) {
			if (!check_db_fields($screen_db_fields, $screen)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect input parameters.'));
			}

			$this->validateScreenSize($screen);

			// "templateid", is not allowed
			if (array_key_exists('templateid', $screen)) {
				self::exception(
					ZBX_API_ERROR_PARAMETERS,
					_s('Cannot set "templateid" for screen "%1$s".', $screen['name'])
				);
			}

			unset($screen['screenid']);
		}
		unset($screen);

		// Check for duplicate names.
		$duplicate = CArrayHelper::findDuplicate($screens, 'name');
		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate "name" value "%1$s" for screen.', $duplicate['name'])
			);
		}

		// Check if screen already exists.
		$db_screens = $this->get([
			'output' => ['name'],
			'filter' => ['name' => zbx_objectValues($screens, 'name')],
			'nopermissions' => true,
			'limit' => 1
		]);

		if ($db_screens) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Screen "%1$s" already exists.', $db_screens[0]['name']));
		}

		$private_validator = new CLimitedSetValidator([
			'values' => [PUBLIC_SHARING, PRIVATE_SHARING]
		]);

		$permission_validator = new CLimitedSetValidator([
			'values' => [PERM_READ, PERM_READ_WRITE]
		]);

		foreach ($screens as $screen) {
			// Check if owner can be set.
			if (array_key_exists('userid', $screen)) {
				if ($screen['userid'] === '' || $screen['userid'] === null || $screen['userid'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Screen owner cannot be empty.'));
				}
				elseif ($screen['userid'] != $user_data['userid'] && $user_data['type'] != USER_TYPE_SUPER_ADMIN
						&& $user_data['type'] != USER_TYPE_ZABBIX_ADMIN) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Only administrators can set screen owner.'));
				}
			}

			// Check for invalid "private" values.
			if (array_key_exists('private', $screen)) {
				if (!$private_validator->validate($screen['private'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect "private" value "%1$s" for screen "%2$s".', $screen['private'], $screen['name'])
					);
				}
			}

			$userids = [];

			// Screen user shares.
			if (array_key_exists('users', $screen)) {
				if (!is_array($screen['users'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				$required_fields = ['userid', 'permission'];

				foreach ($screen['users'] as $share) {
					// Check required parameters.
					$missing_keys = array_diff($required_fields, array_keys($share));

					if ($missing_keys) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'User sharing is missing parameters: %1$s for screen "%2$s".',
							implode(', ', $missing_keys),
							$screen['name']
						));
					}
					else {
						foreach ($required_fields as $field) {
							if ($share[$field] === '' || $share[$field] === null) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Sharing option "%1$s" is missing a value for screen "%2$s".',
									$field,
									$screen['name']
								));
							}
						}
					}

					if (!$permission_validator->validate($share['permission'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect "permission" value "%1$s" in users for screen "%2$s".',
							$share['permission'],
							$screen['name']
						));
					}

					if (array_key_exists('private', $screen) && $screen['private'] == PUBLIC_SHARING
							&& $share['permission'] == PERM_READ) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Screen "%1$s" is public and read-only sharing is disallowed.', $screen['name'])
						);
					}

					if (array_key_exists($share['userid'], $userids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Duplicate userid "%1$s" in users for screen "%2$s".', $share['userid'], $screen['name'])
						);
					}

					$userids[$share['userid']] = $share['userid'];
				}
			}

			if (array_key_exists('userid', $screen) && $screen['userid']) {
				$userids[$screen['userid']] = $screen['userid'];
			}

			// Users validation.
			if ($userids) {
				$db_users = API::User()->get([
					'userids' => $userids,
					'countOutput' => true
				]);

				if (count($userids) != $db_users) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect user ID specified for screen "%1$s".', $screen['name'])
					);
				}
			}

			// Screen user group shares.
			if (array_key_exists('userGroups', $screen)) {
				if (!is_array($screen['userGroups'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				$shared_user_groupids = [];
				$required_fields = ['usrgrpid', 'permission'];

				foreach ($screen['userGroups'] as $share) {
					// Check required parameters.
					$missing_keys = array_diff($required_fields, array_keys($share));

					if ($missing_keys) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'User group sharing is missing parameters: %1$s for screen "%2$s".',
							implode(', ', $missing_keys),
							$screen['name']
						));
					}
					else {
						foreach ($required_fields as $field) {
							if ($share[$field] === '' || $share[$field] === null) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Field "%1$s" is missing a value for screen "%2$s".',
									$field,
									$screen['name']
								));
							}
						}
					}

					if (!$permission_validator->validate($share['permission'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect "permission" value "%1$s" in user groups for screen "%2$s".',
							$share['permission'],
							$screen['name']
						));
					}

					if (array_key_exists('private', $screen) && $screen['private'] == PUBLIC_SHARING
							&& $share['permission'] == PERM_READ) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Screen "%1$s" is public and read-only sharing is disallowed.', $screen['name'])
						);
					}

					if (array_key_exists($share['usrgrpid'], $shared_user_groupids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Duplicate usrgrpid "%1$s" in user groups for screen "%2$s".',
							$share['usrgrpid'],
							$screen['name']
						));
					}

					$shared_user_groupids[$share['usrgrpid']] = $share['usrgrpid'];
				}

				if ($shared_user_groupids) {
					$db_user_groups = API::UserGroup()->get([
						'usrgrpids' => $shared_user_groupids,
						'countOutput' => true
					]);

					if (count($shared_user_groupids) != $db_user_groups) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect user group ID specified for screen "%1$s".', $screen['name'])
						);
					}
				}

				unset($shared_user_groupids);
			}
		}
	}

	/**
	 * Create screen.
	 *
	 * @param array $screens
	 *
	 * @return array
	 */
	public function create(array $screens) {
		$screens = zbx_toArray($screens);

		$this->validateCreate($screens);

		foreach ($screens as &$screen) {
			if (!array_key_exists('templateid', $screen)) {
				$screen['userid'] = array_key_exists('userid', $screen) ? $screen['userid'] : self::$userData['userid'];
			}
		}
		unset($screen);

		$screenids = DB::insert('screens', $screens);

		$shared_users = [];
		$shared_user_groups = [];
		$screenItems = [];

		foreach ($screens as $key => $screen) {
			// Screen user shares.
			if (array_key_exists('users', $screen)) {
				foreach ($screen['users'] as $user) {
					$shared_users[] = [
						'screenid' => $screenids[$key],
						'userid' => $user['userid'],
						'permission' => $user['permission']
					];
				}
			}

			// Screen user group shares.
			if (array_key_exists('userGroups', $screen)) {
				foreach ($screen['userGroups'] as $user_group) {
					$shared_user_groups[] = [
						'screenid' => $screenids[$key],
						'usrgrpid' => $user_group['usrgrpid'],
						'permission' => $user_group['permission']
					];
				}
			}

			// Create screen items.
			if (array_key_exists('screenitems', $screen)) {
				foreach ($screen['screenitems'] as $screenItem) {
					$screenItem['screenid'] = $screenids[$key];

					$screenItems[] = $screenItem;
				}
			}
		}

		DB::insert('screen_user', $shared_users);
		DB::insert('screen_usrgrp', $shared_user_groups);

		if ($screenItems) {
			API::ScreenItem()->create($screenItems);
		}


		return ['screenids' => $screenids];
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $screens
	 * @param array $db_screens		array of existing screens with screen IDs as keys.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array $screens, array $db_screens) {
		if (!$screens) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$user_data = self::$userData;

		// Validate given IDs.
		$this->checkObjectIds($screens, 'screenid',
			_('No "%1$s" given for screen.'),
			_('Empty screen ID.'),
			_('Incorrect screen ID.')
		);

		$check_names = [];

		foreach ($screens as $screen) {
			$this->validateScreenSize($screen);

			if (!array_key_exists($screen['screenid'], $db_screens)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		$screens = $this->extendFromObjects(zbx_toHash($screens, 'screenid'), $db_screens, ['name']);

		foreach ($screens as $screen) {
			// "templateid" is not allowed
			if (array_key_exists('templateid', $screen)) {
				self::exception(
					ZBX_API_ERROR_PARAMETERS,
					_s('Cannot update "templateid" for screen "%1$s".', $screen['name'])
				);
			}

			if (array_key_exists('name', $screen)) {
				// Validate "name" field.
				if (array_key_exists('name', $screen)) {
					if (is_array($screen['name'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
					}
					elseif ($screen['name'] === '' || $screen['name'] === null || $screen['name'] === false) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Screen name cannot be empty.'));
					}

					if ($db_screens[$screen['screenid']]['name'] !== $screen['name']) {
						$check_names[] = $screen;
					}
				}
			}
		}

		if ($check_names) {
			// Check for duplicate names.
			$duplicate = CArrayHelper::findDuplicate($check_names, 'name');
			if ($duplicate) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Duplicate "name" value "%1$s" for screen.', $duplicate['name'])
				);
			}

			$db_screen_names = $this->get([
				'output' => ['screenid', 'name'],
				'filter' => ['name' => zbx_objectValues($check_names, 'name')],
				'nopermissions' => true
			]);
			$db_screen_names = zbx_toHash($db_screen_names, 'name');

			// Check for existing names.
			foreach ($check_names as $screen) {
				if (array_key_exists($screen['name'], $db_screen_names)
						&& bccomp($db_screen_names[$screen['name']]['screenid'], $screen['screenid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Screen "%1$s" already exists.', $screen['name'])
					);
				}
			}
		}

		$private_validator = new CLimitedSetValidator([
			'values' => [PUBLIC_SHARING, PRIVATE_SHARING]
		]);

		$permission_validator = new CLimitedSetValidator([
			'values' => [PERM_READ, PERM_READ_WRITE]
		]);

		foreach ($screens as $screen) {
			// Check if owner can be set.
			if (array_key_exists('userid', $screen)) {
				if ($screen['userid'] === '' || $screen['userid'] === null || $screen['userid'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Screen owner cannot be empty.'));
				}
				elseif ($screen['userid'] != $user_data['userid'] && $user_data['type'] != USER_TYPE_SUPER_ADMIN
						&& $user_data['type'] != USER_TYPE_ZABBIX_ADMIN) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Only administrators can set screen owner.'));
				}
			}

			// Unset extra field.
			unset($db_screens[$screen['screenid']]['userid']);

			$screen = array_merge($db_screens[$screen['screenid']], $screen);

			if (!$private_validator->validate($screen['private'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect "private" value "%1$s" for screen "%2$s".', $screen['private'], $screen['name'])
				);
			}

			$userids = [];

			// Screen user shares.
			if (array_key_exists('users', $screen)) {
				if (!is_array($screen['users'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				$required_fields = ['userid', 'permission'];

				foreach ($screen['users'] as $share) {
					// Check required parameters.
					$missing_keys = array_diff($required_fields, array_keys($share));

					if ($missing_keys) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'User sharing is missing parameters: %1$s for screen "%2$s".',
							implode(', ', $missing_keys),
							$screen['name']
						));
					}
					else {
						foreach ($required_fields as $field) {
							if ($share[$field] === '' || $share[$field] === null) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Sharing option "%1$s" is missing a value for screen "%2$s".',
									$field,
									$screen['name']
								));
							}
						}
					}

					if (!$permission_validator->validate($share['permission'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect "permission" value "%1$s" in users for screen "%2$s".',
							$share['permission'],
							$screen['name']
						));
					}

					if ($screen['private'] == PUBLIC_SHARING && $share['permission'] == PERM_READ) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Screen "%1$s" is public and read-only sharing is disallowed.', $screen['name'])
						);
					}

					if (array_key_exists($share['userid'], $userids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Duplicate userid "%1$s" in users for screen "%2$s".', $share['userid'], $screen['name'])
						);
					}

					$userids[$share['userid']] = $share['userid'];
				}
			}

			if (array_key_exists('userid', $screen) && $screen['userid']) {
				$userids[$screen['userid']] = $screen['userid'];
			}

			// Users validation.
			if ($userids) {
				$db_users = API::User()->get([
					'userids' => $userids,
					'countOutput' => true
				]);

				if (count($userids) != $db_users) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect user ID specified for screen "%1$s".', $screen['name'])
					);
				}
			}

			// Screen user group shares.
			if (array_key_exists('userGroups', $screen)) {
				if (!is_array($screen['userGroups'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				$shared_user_groupids = [];
				$required_fields = ['usrgrpid', 'permission'];

				foreach ($screen['userGroups'] as $share) {
					// Check required parameters.
					$missing_keys = array_diff($required_fields, array_keys($share));

					if ($missing_keys) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'User group sharing is missing parameters: %1$s for screen "%2$s".',
							implode(', ', $missing_keys),
							$screen['name'])
						);
					}
					else {
						foreach ($required_fields as $field) {
							if ($share[$field] === '' || $share[$field] === null) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Sharing option "%1$s" is missing a value for screen "%2$s".',
									$field,
									$screen['name']
								));
							}
						}
					}

					if (!$permission_validator->validate($share['permission'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect "permission" value "%1$s" in user groups for screen "%2$s".',
							$share['permission'],
							$screen['name']
						));
					}

					if ($screen['private'] == PUBLIC_SHARING && $share['permission'] == PERM_READ) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Screen "%1$s" is public and read-only sharing is disallowed.', $screen['name'])
						);
					}

					if (array_key_exists($share['usrgrpid'], $shared_user_groupids)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Duplicate usrgrpid "%1$s" in user groups for screen "%2$s".',
							$share['usrgrpid'],
							$screen['name']
						));
					}

					$shared_user_groupids[$share['usrgrpid']] = $share['usrgrpid'];
				}

				if ($shared_user_groupids) {
					$db_user_groups = API::UserGroup()->get([
						'usrgrpids' => $shared_user_groupids,
						'countOutput' => true
					]);

					if (count($shared_user_groupids) != $db_user_groups) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect user group ID specified for screen "%1$s".', $screen['name'])
						);
					}
				}

				unset($shared_user_groupids);
			}
		}
	}

	/**
	 * Update screen.
	 *
	 * @param array $screens
	 *
	 * @return array
	 */
	public function update(array $screens) {
		$screens = zbx_toArray($screens);

		// check screen IDs before doing anything
		$this->checkObjectIds($screens, 'screenid',
			_('No "%1$s" given for screen.'),
			_('Empty screen ID for screen.'),
			_('Incorrect screen ID.')
		);

		$db_screens = $this->get([
			'output' => ['name', 'screenid', 'hsize', 'vsize', 'private'],
			'selectScreenItems' => ['screenitemid', 'x', 'y', 'colspan', 'rowspan'],
			'selectUsers' => ['screenuserid', 'screenid', 'userid', 'permission'],
			'selectUserGroups' => ['screenusrgrpid', 'screenid', 'usrgrpid', 'permission'],
			'screenids' => zbx_objectValues($screens, 'screenid'),
			'editable' => true,
			'preservekeys' => true
		]);

		$this->validateUpdate($screens, $db_screens);
		$this->updateReal($screens, $db_screens);
		$this->truncateScreenItems($screens, $db_screens);

		return ['screenids' => zbx_objectValues($screens, 'screenid')];
	}

	/**
	 * Saves screens and screen data.
	 *
	 * @param array $screens
	 * @param array $db_screens	array of existing screens with screen IDs as keys
	 */
	protected function updateReal(array $screens, array $db_screens) {
		$update_screens = [];

		foreach ($screens as $screen) {
			$screenid = $screen['screenid'];
			unset($screen['screenid'], $screen['screenitems'], $screen['users'], $screen['userGroups']);

			if ($screen) {
				$update_screens[] = [
					'values' => $screen,
					'where' => ['screenid' => $screenid]
				];
			}
		}

		DB::update('screens', $update_screens);

		$shared_userids_to_delete = [];
		$shared_users_to_update = [];
		$shared_users_to_add = [];
		$shared_user_groupids_to_delete = [];
		$shared_user_groups_to_update = [];
		$shared_user_groups_to_add = [];

		foreach ($screens as $screen) {
			$db_screen = $db_screens[$screen['screenid']];

			// Screen user shares.
			if (array_key_exists('users', $screen)) {
				$user_shares_diff = zbx_array_diff($screen['users'], $db_screen['users'], 'userid');

				foreach ($user_shares_diff['both'] as $update_user_share) {
					$shared_users_to_update[] = [
						'values' => $update_user_share,
						'where' => ['userid' => $update_user_share['userid'], 'screenid' => $screen['screenid']]
					];
				}

				foreach ($user_shares_diff['first'] as $new_shared_user) {
					$new_shared_user['screenid'] = $screen['screenid'];
					$shared_users_to_add[] = $new_shared_user;
				}

				$shared_userids_to_delete = array_merge($shared_userids_to_delete,
					zbx_objectValues($user_shares_diff['second'], 'screenuserid')
				);
			}

			// Screen user group shares.
			if (array_key_exists('userGroups', $screen)) {
				$user_group_shares_diff = zbx_array_diff($screen['userGroups'], $db_screen['userGroups'], 'usrgrpid');

				foreach ($user_group_shares_diff['both'] as $update_user_share) {
					$shared_user_groups_to_update[] = [
						'values' => $update_user_share,
						'where' => ['usrgrpid' => $update_user_share['usrgrpid'], 'screenid' => $screen['screenid']]
					];
				}

				foreach ($user_group_shares_diff['first'] as $new_shared_user_group) {
					$new_shared_user_group['screenid'] = $screen['screenid'];
					$shared_user_groups_to_add[] = $new_shared_user_group;
				}

				$shared_user_groupids_to_delete = array_merge($shared_user_groupids_to_delete,
					zbx_objectValues($user_group_shares_diff['second'], 'screenusrgrpid')
				);
			}

			// Replace screen items.
			if (array_key_exists('screenitems', $screen)) {
				$this->replaceItems($screen['screenid'], $screen['screenitems']);
			}
		}

		// User shares.
		DB::insert('screen_user', $shared_users_to_add);
		DB::update('screen_user', $shared_users_to_update);

		if ($shared_userids_to_delete) {
			DB::delete('screen_user', ['screenuserid' => $shared_userids_to_delete]);
		}

		// User group shares.
		DB::insert('screen_usrgrp', $shared_user_groups_to_add);
		DB::update('screen_usrgrp', $shared_user_groups_to_update);

		if ($shared_user_groupids_to_delete) {
			DB::delete('screen_usrgrp', ['screenusrgrpid' => $shared_user_groupids_to_delete]);
		}
	}

	/**
	 * Delete or reduce the size of screens items when reducing the size of the screens.
	 *
	 * Each array in the $screens array must have the following values:
	 * - screenid
	 * - hsize
	 * - vsize
	 *
	 * Each array in the $dbScreens array must have the following values:
	 * - screenid
	 * - hsize
	 * - vsize
	 * - screenitems
	 *
	 * @param array $screens
	 * @param array $dbScreens	array of existing screens with screen IDs as keys
	 */
	protected function truncateScreenItems(array $screens, array $dbScreens) {
		$deleteScreenItemIds = [];
		$updateScreenItems = [];
		foreach ($screens as $screen) {
			if (array_key_exists('screenitems', $screen)) {
				continue;
			}

			$dbScreen = $dbScreens[$screen['screenid']];
			$dbScreenItems = $dbScreen['screenitems'];

			if (isset($screen['hsize'])) {
				foreach ($dbScreenItems as $dbScreenItem) {
					// delete screen items that are located on the deleted columns
					if ($dbScreenItem['x'] > $screen['hsize'] - 1) {
						$deleteScreenItemIds[$dbScreenItem['screenitemid']] = $dbScreenItem['screenitemid'];
					}
					// reduce the colspan of screenitems that are displayed on the deleted columns
					elseif (($dbScreenItem['x'] + $dbScreenItem['colspan']) > $screen['hsize']) {
						$colspan = $screen['hsize'] - $dbScreenItem['x'];

						$screenItemId = $dbScreenItem['screenitemid'];
						$updateScreenItems[$screenItemId]['screenitemid'] = $dbScreenItem['screenitemid'];
						$updateScreenItems[$screenItemId]['colspan'] = $colspan;
					}
				}
			}

			if (isset($screen['vsize'])) {
				foreach ($dbScreenItems as $dbScreenItem) {
					// delete screen items that are located on the deleted rows
					if ($dbScreenItem['y'] > $screen['vsize'] - 1) {
						$deleteScreenItemIds[$dbScreenItem['screenitemid']] = $dbScreenItem['screenitemid'];
					}
					// reduce the rowspan of screenitems that are displayed on the deleted rows
					elseif (($dbScreenItem['y'] + $dbScreenItem['rowspan']) > $screen['vsize']) {
						$rowspan = $screen['vsize'] - $dbScreenItem['y'];

						$screenItemId = $dbScreenItem['screenitemid'];
						$updateScreenItems[$screenItemId]['screenitemid'] = $dbScreenItem['screenitemid'];
						$updateScreenItems[$screenItemId]['rowspan'] = $rowspan;
					}
				}
			}
		}

		if ($deleteScreenItemIds) {
			DB::delete('screens_items', ['screenitemid' => $deleteScreenItemIds]);
		}

		foreach ($updateScreenItems as $screenItem) {
			DB::updateByPk('screens_items', $screenItem['screenitemid'], $screenItem);
		}
	}

	/**
	 * Validate input for delete method.
	 *
	 * @param array $screenids
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateDelete(array $screenids) {
		if (!$screenids) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$db_screens = $this->get([
			'output' => ['screenid'],
			'screenids' => $screenids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($screenids as $screenid) {
			if (!array_key_exists($screenid, $db_screens)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Delete screen.
	 *
	 * @param array $screenids
	 *
	 * @return array
	 */
	public function delete(array $screenids) {
		$this->validateDelete($screenids);

		DB::delete('screens_items', ['screenid' => $screenids]);
		DB::delete('screens_items', ['resourceid' => $screenids, 'resourcetype' => SCREEN_RESOURCE_SCREEN]);
		DB::delete('slides', ['screenid' => $screenids]);
		DB::delete('screens', ['screenid' => $screenids]);
		DB::delete('profiles', [
			'idx' => 'web.favorite.screenids',
			'source' => 'screenid',
			'value_id' => $screenids
		]);

		return ['screenids' => $screenids];
	}

	/**
	 * Replaces all of the screen items of the given screen.
	 *
	 * @param int   $screenId		The ID of the target screen
	 * @param array $screenItems	An array of screen items
	 */
	protected function replaceItems($screenId, $screenItems) {
		foreach ($screenItems as &$screenItem) {
			$screenItem['screenid'] = $screenId;
		}
		unset($screenItem);

		$createScreenItems = $deleteScreenItems = $updateScreenItems = [];
		$deleteScreenItemsIds = [];

		$dbScreenItems = API::ScreenItem()->get([
			'output' => ['screenitemid'],
			'screenids' => $screenId,
			'preservekeys' => true
		]);

		foreach ($screenItems as $screenItem) {
			if (isset($screenItem['screenitemid']) && isset($dbScreenItems[$screenItem['screenitemid']])) {
				$updateScreenItems[$screenItem['screenitemid']] = $screenItem;
			}
			else {
				$createScreenItems[] = $screenItem;
			}
		}

		foreach ($dbScreenItems as $dbScreenItem) {
			if (!isset($updateScreenItems[$dbScreenItem['screenitemid']])) {
				$deleteScreenItemsIds[$dbScreenItem['screenitemid']] = $dbScreenItem['screenitemid'];
			}
		}

		if ($deleteScreenItemsIds) {
			API::ScreenItem()->delete($deleteScreenItemsIds);
		}
		if ($updateScreenItems) {
			API::ScreenItem()->update($updateScreenItems);
		}
		if ($createScreenItems) {
			API::ScreenItem()->create($createScreenItems);
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$screenIds = array_keys($result);

		// adding ScreenItems
		if ($options['selectScreenItems'] !== null && $options['selectScreenItems'] != API_OUTPUT_COUNT) {
			$screenItems = API::getApiService()->select('screens_items', [
				'output' => $this->outputExtend($options['selectScreenItems'], ['screenid', 'screenitemid']),
				'filter' => ['screenid' => $screenIds],
				'preservekeys' => true
			]);

			$relation_map = $this->createRelationMap($screenItems, 'screenid', 'screenitemid');

			$screenItems = $this->unsetExtraFields($screenItems, ['screenid', 'screenitemid'], $options['selectScreenItems']);
			$result = $relation_map->mapMany($result, $screenItems, 'screenitems');
		}

		// Adding user shares.
		if ($options['selectUsers'] !== null && $options['selectUsers'] != API_OUTPUT_COUNT) {
			$relation_map = $this->createRelationMap($result, 'screenid', 'userid', 'screen_user');
			// Get all allowed users.
			$related_users = API::User()->get([
				'output' => ['userid'],
				'userids' => $relation_map->getRelatedIds(),
				'preservekeys' => true
			]);

			$related_userids = zbx_objectValues($related_users, 'userid');

			if ($related_userids) {
				$users = API::getApiService()->select('screen_user', [
					'output' => $this->outputExtend($options['selectUsers'], ['screenid', 'userid']),
					'filter' => ['screenid' => $screenIds, 'userid' => $related_userids],
					'preservekeys' => true
				]);

				$relation_map = $this->createRelationMap($users, 'screenid', 'screenuserid');

				$users = $this->unsetExtraFields($users, ['screenuserid', 'userid', 'permission'],
					$options['selectUsers']
				);

				foreach ($users as &$user) {
					unset($user['screenid']);
				}
				unset($user);

				$result = $relation_map->mapMany($result, $users, 'users');
			}
			else {
				foreach ($result as &$row) {
					$row['users'] = [];
				}
				unset($row);
			}
		}

		// Adding user group shares.
		if ($options['selectUserGroups'] !== null && $options['selectUserGroups'] != API_OUTPUT_COUNT) {
			$relation_map = $this->createRelationMap($result, 'screenid', 'usrgrpid', 'screen_usrgrp');
			// Get all allowed groups.
			$related_groups = API::UserGroup()->get([
				'output' => ['usrgrpid'],
				'usrgrpids' => $relation_map->getRelatedIds(),
				'preservekeys' => true
			]);

			$related_groupids = zbx_objectValues($related_groups, 'usrgrpid');

			if ($related_groupids) {
				$user_groups = API::getApiService()->select('screen_usrgrp', [
					'output' => $this->outputExtend($options['selectUserGroups'], ['screenid', 'usrgrpid']),
					'filter' => ['screenid' => $screenIds, 'usrgrpid' => $related_groupids],
					'preservekeys' => true
				]);

				$relation_map = $this->createRelationMap($user_groups, 'screenid', 'screenusrgrpid');

				$user_groups = $this->unsetExtraFields($user_groups, ['screenusrgrpid', 'usrgrpid', 'permission'],
					$options['selectUserGroups']
				);

				foreach ($user_groups as &$user_group) {
					unset($user_group['screenid']);
				}
				unset($user_group);

				$result = $relation_map->mapMany($result, $user_groups, 'userGroups');
			}
			else {
				foreach ($result as &$row) {
					$row['userGroups'] = [];
				}
				unset($row);
			}
		}

		return $result;
	}
}
