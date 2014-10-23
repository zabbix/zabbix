<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
class CScreen extends CApiService {

	protected $tableName = 'screens';
	protected $tableAlias = 's';
	protected $sortColumns = array('screenid', 'name');

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
			'output'					=> API_OUTPUT_EXTEND,
			'selectScreenItems'			=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// screenids
		if (!is_null($options['screenids'])) {
			zbx_value2array($options['screenids']);
			$sqlParts['where'][] = dbConditionInt('s.screenid', $options['screenids']);
		}

		// screenitemids
		if (!is_null($options['screenitemids'])) {
			zbx_value2array($options['screenitemids']);

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

		$screenIds = array();
		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($screen = DBfetch($res)) {
			if ($options['countOutput'] !== null) {
				if ($options['groupCount'] !== null) {
					$result[] = $screen;
				}
				else {
					$result = $screen['rowscount'];
				}
			}
			else {
				$screenIds[$screen['screenid']] = $screen['screenid'];

				$result[$screen['screenid']] = $screen;
			}
		}

		// editable + PERMISSION CHECK
		if ($userType == USER_TYPE_SUPER_ADMIN || $options['nopermissions']) {
		}
		elseif ($result) {
			$groupsToCheck = array();
			$hostsToCheck = array();
			$graphsToCheck = array();
			$itemsToCheck = array();
			$mapsToCheck = array();
			$screensToCheck = array();
			$screensItems = array();

			$dbScreenItems = DBselect('SELECT si.* FROM screens_items si WHERE '.dbConditionInt('si.screenid', $screenIds));

			while ($screenItem = DBfetch($dbScreenItems)) {
				$screensItems[$screenItem['screenitemid']] = $screenItem;

				if ($screenItem['resourceid']) {
					switch ($screenItem['resourcetype']) {
						case SCREEN_RESOURCE_HOSTS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
						case SCREEN_RESOURCE_DATA_OVERVIEW:
						case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
							$groupsToCheck[] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_HOST_TRIGGERS:
							$hostsToCheck[] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_GRAPH:
							$graphsToCheck[] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_SIMPLE_GRAPH:
						case SCREEN_RESOURCE_PLAIN_TEXT:
							$itemsToCheck[] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_CLOCK:
							if ($screenItem['style'] == TIME_TYPE_HOST) {
								$itemsToCheck[] = $screenItem['resourceid'];
							}
							break;

						case SCREEN_RESOURCE_MAP:
							$mapsToCheck[] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_SCREEN:
							$screensToCheck[] = $screenItem['resourceid'];
							break;
					}
				}
			}

			$groupsToCheck = array_unique($groupsToCheck);
			$hostsToCheck = array_unique($hostsToCheck);
			$graphsToCheck = array_unique($graphsToCheck);
			$itemsToCheck = array_unique($itemsToCheck);
			$mapsToCheck = array_unique($mapsToCheck);
			$screensToCheck = array_unique($screensToCheck);

			// group
			$allowedGroups = API::HostGroup()->get(array(
				'output' => array('groupid'),
				'groupids' => $groupsToCheck,
				'editable' => $options['editable']
			));
			$allowedGroups = zbx_objectValues($allowedGroups, 'groupid');

			// host
			$allowedHosts = API::Host()->get(array(
				'output' => array('hostid'),
				'hostids' => $hostsToCheck,
				'editable' => $options['editable']
			));
			$allowedHosts = zbx_objectValues($allowedHosts, 'hostid');

			// graph
			$allowedGraphs = API::Graph()->get(array(
				'output' => array('graphid'),
				'graphids' => $graphsToCheck,
				'editable' => $options['editable']
			));
			$allowedGraphs = zbx_objectValues($allowedGraphs, 'graphid');

			// item
			$allowedItems = API::Item()->get(array(
				'output' => array('itemid'),
				'itemids' => $itemsToCheck,
				'webitems' => true,
				'editable' => $options['editable']
			));
			$allowedItems = zbx_objectValues($allowedItems, 'itemid');

			// map
			$allowedMaps = API::Map()->get(array(
				'output' => array('sysmapid'),
				'sysmapids' => $mapsToCheck,
				'editable' => $options['editable']
			));
			$allowedMaps = zbx_objectValues($allowedMaps, 'sysmapid');

			// screen
			$allowedScreens = API::Screen()->get(array(
				'output' => array('screenid'),
				'screenids' => $screensToCheck,
				'editable' => $options['editable']
			));
			$allowedScreens = zbx_objectValues($allowedScreens, 'screenid');

			$restrGroups = array_diff($groupsToCheck, $allowedGroups);
			$restrHosts = array_diff($hostsToCheck, $allowedHosts);
			$restrGraphs = array_diff($graphsToCheck, $allowedGraphs);
			$restrItems = array_diff($itemsToCheck, $allowedItems);
			$restrMaps = array_diff($mapsToCheck, $allowedMaps);
			$restrScreens = array_diff($screensToCheck, $allowedScreens);

			// group
			foreach ($restrGroups as $resourceId) {
				foreach ($screensItems as $screenItemId => $screenItem) {
					if (bccomp($screenItem['resourceid'], $resourceId) == 0
							&& uint_in_array($screenItem['resourcetype'], array(
								SCREEN_RESOURCE_HOSTS_INFO, SCREEN_RESOURCE_TRIGGERS_INFO, SCREEN_RESOURCE_TRIGGERS_OVERVIEW,
								SCREEN_RESOURCE_DATA_OVERVIEW, SCREEN_RESOURCE_HOSTGROUP_TRIGGERS))) {
						unset($result[$screenItem['screenid']], $screensItems[$screenItemId]);
					}
				}
			}

			// host
			foreach ($restrHosts as $resourceId) {
				foreach ($screensItems as $screenItemId => $screenItem) {
					if (bccomp($screenItem['resourceid'], $resourceId) == 0
							&& uint_in_array($screenItem['resourcetype'], array(SCREEN_RESOURCE_HOST_TRIGGERS))) {
						unset($result[$screenItem['screenid']], $screensItems[$screenItemId]);
					}
				}
			}

			// graph
			foreach ($restrGraphs as $resourceId) {
				foreach ($screensItems as $screenItemId => $screenItem) {
					if (bccomp($screenItem['resourceid'], $resourceId) == 0 && $screenItem['resourcetype'] == SCREEN_RESOURCE_GRAPH) {
						unset($result[$screenItem['screenid']], $screensItems[$screenItemId]);
					}
				}
			}

			// item
			foreach ($restrItems as $resourceId) {
				foreach ($screensItems as $screenItemId => $screenItem) {
					if (bccomp($screenItem['resourceid'], $resourceId) == 0
							&& uint_in_array($screenItem['resourcetype'], array(SCREEN_RESOURCE_CLOCK,
								SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_PLAIN_TEXT))) {
						unset($result[$screenItem['screenid']], $screensItems[$screenItemId]);
					}
				}
			}

			// map
			foreach ($restrMaps as $resourceId) {
				foreach ($screensItems as $screenItemId => $screenItem) {
					if (bccomp($screenItem['resourceid'], $resourceId) == 0
							&& $screenItem['resourcetype'] == SCREEN_RESOURCE_MAP) {
						unset($result[$screenItem['screenid']], $screensItems[$screenItemId]);
					}
				}
			}

			// screen
			foreach ($restrScreens as $resourceId) {
				foreach ($screensItems as $screenItemId => $screenItem) {
					if (bccomp($screenItem['resourceid'], $resourceId) == 0
							&& $screenItem['resourcetype'] == SCREEN_RESOURCE_SCREEN) {
						unset($result[$screenItem['screenid']], $screensItems[$screenItemId]);
					}
				}
			}
		}

		if ($options['countOutput'] !== null) {
			return $result;
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
	 * Check if screen exists.
	 *
	 * @deprecated	As of version 2.4, use get method instead.
	 *
	 * @param array	$object
	 *
	 * @return bool
	 */
	public function exists(array $data) {
		$this->deprecated('screen.exists method is deprecated.');

		$screen = $this->get(array(
			'output' => array('screenid'),
			'filter' => zbx_array_mintersect(array(array('screenid', 'name')), $data),
			'preservekeys' => true,
			'limit' => 1
		));

		return (bool) $screen;
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

		$dbScreens = API::getApiService()->select('screens', array(
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
				$dbScreenExist = API::getApiService()->select('screens', array(
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
			'output' => array('screenid'),
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
	public function delete(array $screenIds) {
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
			'output' => array('screenitemid'),
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

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$screenIds = array_keys($result);

		// adding ScreenItems
		if ($options['selectScreenItems'] !== null && $options['selectScreenItems'] != API_OUTPUT_COUNT) {
			$screenItems = API::getApiService()->select('screens_items', array(
				'output' => $this->outputExtend($options['selectScreenItems'], array('screenid', 'screenitemid')),
				'filter' => array('screenid' => $screenIds),
				'preservekeys' => true
			));

			$relationMap = $this->createRelationMap($screenItems, 'screenid', 'screenitemid');

			$screenItems = $this->unsetExtraFields($screenItems, array('screenid', 'screenitemid'), $options['selectScreenItems']);
			$result = $relationMap->mapMany($result, $screenItems, 'screenitems');
		}

		return $result;
	}
}
