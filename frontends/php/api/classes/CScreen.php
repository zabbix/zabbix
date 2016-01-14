<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 *
 * @package API
 */
class CScreen extends CZBXAPI {

	protected $tableName = 'screens';
	protected $tableAlias = 's';
	protected $sortColumns = array('screenid', 'name');

	/**
	 * Get screen data.
	 *
	 * @param array  $options
	 * @param array  $options['nodeids']		node IDs
	 * @param bool   $options['editable']		only with read-write permission. Ignored for SuperAdmins
	 * @param int    $options['count']			count Hosts, returned column name is rowscount
	 * @param string $options['pattern']		search hosts by pattern in host names
	 * @param int    $options['limit']			limit selection
	 * @param string $options['order']			deprecated parameter (for now)
	 *
	 * @return array
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];

		$sqlParts = array(
			'select'	=> array('screens' => 's.screenid'),
			'from'		=> array('screens' => 'screens s'),
			'where'		=> array('template' => 's.templateid IS NULL'),
			'order'		=> array(),
			'group'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'screenids'					=> null,
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
			'output'					=> API_OUTPUT_REFER,
			'selectScreenItems'			=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		if ($options['countOutput'] !== null) {
			$count_output = true;
			$options['output'] = array('screenid');
			$options['countOutput'] = null;
			$options['limit'] = null;
		}
		else {
			$count_output = false;
		}

		// screenids
		if (!is_null($options['screenids'])) {
			zbx_value2array($options['screenids']);
			$sqlParts['where'][] = dbConditionInt('s.screenid', $options['screenids']);
		}

		// screenitemids
		if (!is_null($options['screenitemids'])) {
			zbx_value2array($options['screenitemids']);
			if ($options['output'] != API_OUTPUT_EXTEND) {
				$sqlParts['select']['screenitemid'] = 'si.screenitemid';
			}
			$sqlParts['from']['screens_items'] = 'screens_items si';
			$sqlParts['where']['ssi'] = 'si.screenid=s.screenid';
			$sqlParts['where'][] = dbConditionInt('si.screenitemid', $options['screenitemids']);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('screens s', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('screens s', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$screenids = array();
		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($screen = DBfetch($res)) {
			$screenids[$screen['screenid']] = true;

			if (!isset($result[$screen['screenid']])) {
				$result[$screen['screenid']]= array();
			}

			if (isset($screen['screenitemid']) && $options['selectScreenItems'] === null) {
				if (!isset($result[$screen['screenid']]['screenitems'])) {
					$result[$screen['screenid']]['screenitems'] = array();
				}
				$result[$screen['screenid']]['screenitems'][] = array('screenitemid' => $screen['screenitemid']);
				unset($screen['screenitemid']);
			}

			$result[$screen['screenid']] += $screen;
		}

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions'] && $screenids) {
			$db_screen_items = DBselect(
				'SELECT si.screenid,si.resourcetype,si.resourceid'.
				' FROM screens_items si'.
				' WHERE '.dbConditionInt('si.screenid', array_keys($screenids)).
					' AND '.dbConditionInt('si.resourcetype', array(
						SCREEN_RESOURCE_HOSTS_INFO, SCREEN_RESOURCE_TRIGGERS_INFO, SCREEN_RESOURCE_TRIGGERS_OVERVIEW,
						SCREEN_RESOURCE_DATA_OVERVIEW, SCREEN_RESOURCE_HOSTGROUP_TRIGGERS,
						SCREEN_RESOURCE_HOST_TRIGGERS, SCREEN_RESOURCE_GRAPH, SCREEN_RESOURCE_SIMPLE_GRAPH,
						SCREEN_RESOURCE_PLAIN_TEXT, SCREEN_RESOURCE_MAP, SCREEN_RESOURCE_SCREEN
					)).
					' AND si.resourceid<>0'
			);

			$screens = array();

			while ($db_screen_item = DBfetch($db_screen_items)) {
				if (!array_key_exists($db_screen_item['screenid'], $screens)) {
					$screens[$db_screen_item['screenid']] = array(
						'groups' => array(),
						'hosts' => array(),
						'graphs' => array(),
						'items' => array(),
						'maps' => array(),
						'screens' => array()
					);
				}

				switch ($db_screen_item['resourcetype']) {
					case SCREEN_RESOURCE_HOSTS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
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

					case SCREEN_RESOURCE_MAP:
						$screens[$db_screen_item['screenid']]['maps'][$db_screen_item['resourceid']] = true;
						break;

					case SCREEN_RESOURCE_SCREEN:
						$screens[$db_screen_item['screenid']]['screens'][$db_screen_item['resourceid']] = true;
						break;
				}
			}

			// groups
			$groups = array();

			foreach ($screens as $screenid => $resources) {
				foreach ($resources['groups'] as $groupid => $foo) {
					$groups[$groupid][$screenid] = true;
				}
			}

			if ($groups) {
				$db_groups = API::HostGroup()->get(array(
					'output' => array(),
					'nodeids' => $options['nodeids'],
					'groupids' => array_keys($groups),
					'editable' => $options['editable'],
					'preservekeys' => true
				));

				foreach ($groups as $groupid => $resources) {
					if (!array_key_exists($groupid, $db_groups)) {
						foreach ($resources as $screenid => $foo) {
							unset($screens[$screenid], $result[$screenid]);
						}
					}
				}
			}

			// hosts
			$hosts = array();

			foreach ($screens as $screenid => $resources) {
				foreach ($resources['hosts'] as $hostid => $foo) {
					$hosts[$hostid][$screenid] = true;
				}
			}

			if ($hosts) {
				$db_hosts = API::Host()->get(array(
					'output' => array(),
					'nodeids' => $options['nodeids'],
					'hostids' => array_keys($hosts),
					'editable' => $options['editable'],
					'preservekeys' => true
				));

				foreach ($hosts as $hostid => $resources) {
					if (!array_key_exists($hostid, $db_hosts)) {
						foreach ($resources as $screenid => $foo) {
							unset($screens[$screenid], $result[$screenid]);
						}
					}
				}
			}

			// graphs
			$graphs = array();

			foreach ($screens as $screenid => $resources) {
				foreach ($resources['graphs'] as $graphid => $foo) {
					$graphs[$graphid][$screenid] = true;
				}
			}

			if ($graphs) {
				$db_graphs = API::Graph()->get(array(
					'output' => array(),
					'nodeids' => $options['nodeids'],
					'graphids' => array_keys($graphs),
					'editable' => $options['editable'],
					'preservekeys' => true
				));

				foreach ($graphs as $graphid => $resources) {
					if (!array_key_exists($graphid, $db_graphs)) {
						foreach ($resources as $screenid => $foo) {
							unset($screens[$screenid], $result[$screenid]);
						}
					}
				}
			}

			// items
			$items = array();

			foreach ($screens as $screenid => $resources) {
				foreach ($resources['items'] as $itemid => $foo) {
					$items[$itemid][$screenid] = true;
				}
			}

			if ($items) {
				$db_items = API::Item()->get(array(
					'output' => array(),
					'nodeids' => $options['nodeids'],
					'itemids' => array_keys($items),
					'editable' => $options['editable'],
					'webitems' => true,
					'preservekeys' => true
				));

				foreach ($items as $itemid => $resources) {
					if (!array_key_exists($itemid, $db_items)) {
						foreach ($resources as $screenid => $foo) {
							unset($screens[$screenid], $result[$screenid]);
						}
					}
				}
			}

			// maps
			$maps = array();

			foreach ($screens as $screenid => $resources) {
				foreach ($resources['maps'] as $sysmapid => $foo) {
					$maps[$sysmapid][$screenid] = true;
				}
			}

			if ($maps) {
				$db_maps = API::Map()->get(array(
					'output' => array(),
					'nodeids' => $options['nodeids'],
					'sysmapids' => array_keys($maps),
					'editable' => $options['editable'],
					'preservekeys' => true
				));

				foreach ($maps as $sysmapid => $resources) {
					if (!array_key_exists($sysmapid, $db_maps)) {
						foreach ($resources as $screenid => $foo) {
							unset($screens[$screenid], $result[$screenid]);
						}
					}
				}
			}

			// screens
			$_screens = array();

			foreach ($screens as $screenid => $resources) {
				foreach ($resources['screens'] as $_screenid => $foo) {
					$_screens[$_screenid][$screenid] = true;
				}
			}

			if ($_screens) {
				$db_screens = API::Screen()->get(array(
					'output' => array(),
					'nodeids' => $options['nodeids'],
					'screenids' => array_keys($_screens),
					'editable' => $options['editable'],
					'preservekeys' => true
				));

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
				return array(array('rowscount' => count($result)));
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

	public function exists($data) {
		$keyFields = array(array('screenid', 'name'));

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $data),
			'preservekeys' => true,
			'output' => array('screenid'),
			'nopermissions' => true,
			'limit' => 1
		);
		if (isset($data['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($data['node']);
		}
		elseif (isset($data['nodeids'])) {
			$options['nodeids'] = $data['nodeids'];
		}
		$screens = $this->get($options);

		return !empty($screens);
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $screens
	 */
	protected function validateCreate(array $screens) {
		$screenDbFields = array('name' => null);

		foreach ($screens as &$screen) {
			if (!check_db_fields($screenDbFields, $screen)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect input parameters.'));
			}

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

		$dbScreens = API::getApi()->select('screens', array(
			'filter' => array('name' => zbx_objectValues($screens, 'name')),
			'output' => array('name')
		));

		foreach ($dbScreens as $dbScreen) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Screen "%1$s" already exists.', $dbScreen['name']));
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

		$screenIds = DB::insert('screens', $screens);

		// create screen items
		$screenItems = array();
		foreach ($screens as $key => $screen) {
			if (isset($screen['screenitems'])) {
				foreach ($screen['screenitems'] as $screenItem) {
					$screenItem['screenid'] = $screenIds[$key];

					$screenItems[] = $screenItem;
				}
			}
		}

		if ($screenItems) {
			API::ScreenItem()->create($screenItems);
		}

		return array('screenids' => $screenIds);
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $screens
	 * @param array $dbScreens	array of existing screens with screen IDs as keys
	 */
	protected function validateUpdate(array $screens, array $dbScreens) {
		foreach ($screens as $screen) {
			if (!isset($dbScreens[$screen['screenid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		$screens = $this->extendObjects($this->tableName(), $screens, array('name'));

		foreach ($screens as $screen) {
			// "templateid" is not allowed
			if (array_key_exists('templateid', $screen)) {
				self::exception(
					ZBX_API_ERROR_PARAMETERS,
					_s('Cannot update "templateid" for screen "%1$s".', $screen['name'])
				);
			}

			if (isset($screen['name'])) {
				$dbScreenExist = API::getApi()->select('screens', array(
					'filter' => array('name' => $screen['name']),
					'output' => array('screenid')
				));
				$dbScreenExist = reset($dbScreenExist);

				if ($dbScreenExist && bccomp($dbScreenExist['screenid'], $screen['screenid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Screen "%1$s" already exists.', $screen['name']));
				}
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

		$dbScreens = $this->get(array(
			'output' => array('screenid', 'hsize', 'vsize'),
			'selectScreenItems' => array('screenitemid', 'x', 'y', 'colspan', 'rowspan'),
			'screenids' => zbx_objectValues($screens, 'screenid'),
			'editable' => true,
			'preservekeys' => true
		));

		$this->validateUpdate($screens, $dbScreens);
		$this->updateReal($screens);
		$this->truncateScreenItems($screens, $dbScreens);

		return array('screenids' => zbx_objectValues($screens, 'screenid'));
	}

	/**
	 * Saves screens and screen items.
	 *
	 * @param array $screens
	 */
	protected function updateReal(array $screens) {
		$update = array();

		foreach ($screens as $screen) {
			$screenId = $screen['screenid'];
			unset($screen['screenid'], $screen['screenitems']);

			if ($screen) {
				$update[] = array(
					'values' => $screen,
					'where' => array('screenid' => $screenId)
				);
			}
		}

		DB::update('screens', $update);

		// replace screen items
		foreach ($screens as $screen) {
			if (isset($screen['screenitems'])) {
				$this->replaceItems($screen['screenid'], $screen['screenitems']);
			}
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
		$deleteScreenItemIds = array();
		$updateScreenItems = array();
		foreach ($screens as $screen) {
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
			DB::delete('screens_items', array('screenitemid' => $deleteScreenItemIds));
		}

		foreach ($updateScreenItems as $screenItem) {
			DB::updateByPk('screens_items', $screenItem['screenitemid'], $screenItem);
		}
	}

	/**
	 * Validate input for delete method.
	 *
	 * @param array $screenIds
	 */
	protected function validateDelete(array $screenIds) {
		$dbScreens = $this->get(array(
			'screenids' => $screenIds,
			'editable' => true,
			'preservekeys' => true
		));

		foreach ($screenIds as $screenId) {
			if (!isset($dbScreens[$screenId])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}
	}

	/**
	 * Delete screen.
	 *
	 * @param array $screenIds
	 *
	 * @return array
	 */
	public function delete($screenIds) {
		$screenIds = zbx_toArray($screenIds);

		$this->validateDelete($screenIds);

		DB::delete('screens_items', array('screenid' => $screenIds));
		DB::delete('screens_items', array('resourceid' => $screenIds, 'resourcetype' => SCREEN_RESOURCE_SCREEN));
		DB::delete('slides', array('screenid' => $screenIds));
		DB::delete('screens', array('screenid' => $screenIds));
		DB::delete('profiles', array(
			'idx' => 'web.favorite.screenids',
			'source' => 'screenid',
			'value_id' => $screenIds
		));

		return array('screenids' => $screenIds);
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

		$createScreenItems = $deleteScreenItems = $updateScreenItems = array();
		$deleteScreenItemsIds = array();

		$dbScreenItems = API::ScreenItem()->get(array(
			'screenids' => $screenId,
			'preservekeys' => true
		));

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

	protected function applyQueryNodeOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		// only apply the node option if no specific ids are given
		if ($options['screenids'] === null && $options['screenitemids'] === null) {
			$sqlParts = parent::applyQueryNodeOptions($tableName, $tableAlias, $options, $sqlParts);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$screenIds = array_keys($result);

		// adding ScreenItems
		if ($options['selectScreenItems'] !== null && $options['selectScreenItems'] != API_OUTPUT_COUNT) {
			$screenItems = API::getApi()->select('screens_items', array(
				'output' => $this->outputExtend('screens_items', array('screenid', 'screenitemid'), $options['selectScreenItems']),
				'filter' => array('screenid' => $screenIds),
				'preservekeys' => true,
				'nodeids' => get_current_nodeid(true)
			));

			$relationMap = $this->createRelationMap($screenItems, 'screenid', 'screenitemid');

			$screenItems = $this->unsetExtraFields($screenItems, array('screenid', 'screenitemid'), $options['selectScreenItems']);
			$result = $relationMap->mapMany($result, $screenItems, 'screenitems');
		}

		return $result;
	}
}
