<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * File containing CScreenItem class for API.
 * @package API
 */
/**
 * Class containing methods for operations with ScreenItems
 */
class CScreenItem extends CZBXAPI {

	protected $tableName = 'screens_items';
	protected $tableAlias = 'si';

	/**
	 * Supported values for the resourcetype column.
	 *
	 * @var array
	 */
	protected static $resourceTypes = array(
		SCREEN_RESOURCE_GRAPH,
		SCREEN_RESOURCE_SIMPLE_GRAPH,
		SCREEN_RESOURCE_MAP,
		SCREEN_RESOURCE_PLAIN_TEXT,
		SCREEN_RESOURCE_HOSTS_INFO,
		SCREEN_RESOURCE_TRIGGERS_INFO,
		SCREEN_RESOURCE_SERVER_INFO,
		SCREEN_RESOURCE_CLOCK,
		SCREEN_RESOURCE_SCREEN,
		SCREEN_RESOURCE_TRIGGERS_OVERVIEW,
		SCREEN_RESOURCE_DATA_OVERVIEW,
		SCREEN_RESOURCE_URL,
		SCREEN_RESOURCE_ACTIONS,
		SCREEN_RESOURCE_EVENTS,
		SCREEN_RESOURCE_HOSTGROUP_TRIGGERS,
		SCREEN_RESOURCE_SYSTEM_STATUS,
		SCREEN_RESOURCE_HOST_TRIGGERS
	);

	protected $sortColumns = array(
		'screenitemid',
		'screenid'
	);

	public function __construct() {
		parent::__construct();

		$this->getOptions = zbx_array_merge($this->getOptions, array(
			'screenitemids'	=> null,
			'screenids'		=> null,
			'editable'		=> null,
			'sortfield'		=> '',
			'sortorder'		=> '',
			'preservekeys'	=> null,
			'countOutput'	=> null
		));
	}

	/**
	 * Get ScreemItem data
	 *
	 * @param array $options
	 * @param array $options['nodeids']			Node IDs
	 * @param array $options['screenitemids']	Search by screen item IDs
	 * @param array $options['screenids']		Search by screen IDs
	 * @param array $options['filter']			Result filter
	 * @param array $options['limit']			The size of the result set
	 *
	 * @return array|boolean Host data as array or false if error
	 */
	public function get(array $options = array()) {
		$options = zbx_array_merge($this->getOptions, $options);

		// build and execute query
		$sql = $this->createSelectQuery($this->tableName(), $options);
		$res = DBselect($sql, $options['limit']);

		// fetch results
		$result = array();
		while ($row = DBfetch($res)) {
			// count query, return a single result
			if ($options['countOutput'] !== null) {
				$result = $row['rowscount'];
			}
			// normal select query
			else {
				if ($options['preservekeys'] !== null) {
					$result[$row['screenitemid']] = $this->unsetExtraFields($this->tableName(), $row, $options['output']);
				}
				else {
					$result[] = $this->unsetExtraFields($this->tableName(), $row, $options['output']);
				}
			}
		}

		return $result;
	}

	/**
	 * Saves the given screen items.
	 *
	 * @param array $screenItems	An array of screen items
	 * @return array				An array, that contains the IDs of the new items
	 *								under the 'screenitemids' key
	 */
	public function create(array $screenItems) {
		$screenItems = zbx_toArray($screenItems);

		$this->validateCreate($screenItems);

		// insert items
		$screenItemids = DB::insert($this->tableName(), $screenItems);

		return array('screenitemids' => $screenItemids);
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $screenItems
	 *
	 * @return void
	 */
	protected function validateCreate(array $screenItems) {
		foreach ($screenItems as $screenItem) {
			if (empty($screenItem['screenid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
		}

		$screenIds = zbx_objectValues($screenItems, 'screenid');

		// fetch the items we're updating
		$dbScreenItems = API::getApi()->select('screens_items', array(
			'screenids' => $screenIds,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		$screens = API::getApi()->select('screens', array(
			'output' => array('screenid', 'hsize', 'vsize'),
			'screenids' => $screenIds,
			'preservekeys' => true
		));

		foreach($screenItems as $screenItem) {
			$screen = $screens[$screenItem['screenid']];

			// check duplicate resource in cell
			if (isset($screenItem['x']) && isset($screenItem['y'])) {
				foreach ($dbScreenItems as $dbScreenItem) {
					if ($dbScreenItem['screenid'] == $screenItem['screenid']
						&& strcmp($dbScreenItem['x'], $screenItem['x']) == 0
						&& strcmp($dbScreenItem['y'], $screenItem['y']) == 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Screen item in same cell already exists.'));
					}
				}
			}

			$this->checkSpans($screenItem);
			$this->checkSpansInBounds($screenItem, $screen);
		}

		// validate input
		$this->checkInput($screenItems, $dbScreenItems);
	}

	/**
	 * Updates the given screen items.
	 *
	 * @param array $screenItems	An array of screen items
	 * @return array				An array, that contains the IDs of the updated items
	 *								under the 'screenitemids' key
	 */
	public function update(array $screenItems) {
		$screenItems = zbx_toArray($screenItems);

		$this->validateUpdate($screenItems);

		// update items
		$update = array();
		foreach ($screenItems as $screenItem) {
			$screenItemId = $screenItem['screenitemid'];
			unset($screenItem['screenitemid']);
			$update[] = array(
				'values' => $screenItem,
				'where' => array('screenitemid' => $screenItemId)
			);
		}
		DB::update($this->tableName(), $update);

		return array('screenitemids' => zbx_objectValues($screenItems, 'screenitemid'));
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $screenItems
	 *
	 * @return void
	 */
	protected function validateUpdate(array $screenItems) {
		foreach ($screenItems as $screenItem) {
			if (empty($screenItem['screenitemid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
		}
		$screenItemIds = zbx_objectValues($screenItems, 'screenitemid');

		$screens = API::getApi()->select('screens', array(
			'output' => array('screenid', 'hsize', 'vsize'),
			'screenitemids' => $screenItemIds,
			'preservekeys' => true
		));

		$screenItems = $this->extendObjects($this->tableName(), $screenItems, array('screenid', 'x', 'y', 'rowspan', 'colspan'));

		foreach ($screenItems as $screenItem) {
			$screen = $screens[$screenItem['screenid']];

			$this->checkSpans($screenItem);
			$this->checkSpansInBounds($screenItem, $screen);
		}

		// old validation
		// fetch the items we're updating
		$dbScreenItems = API::getApi()->select('screens_items', array(
			'screenitemids' => $screenItemIds,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		// validate input
		$this->checkInput($screenItems, $dbScreenItems);
	}

	/**
	 * Update screen items using the given 'x' and 'y' parameters.
	 * If the given cell is free, a new screen item will be created.
	 *
	 * @param array $screenItems	An array of screen items with the given X and Y coordinates
	 * @return array				An array, that contains the IDs of the updated items
	 *								under the 'screenitemids' key
	 */
	public function updateByPosition(array $screenItems) {
		// create a screen-position map
		$dbScreenItems = $this->get(array(
			'output' => array('screenitemid', 'x', 'y', 'screenid'),
			'screenids' => zbx_objectValues($screenItems, 'screenid')
		));
		$screenItemMap = array();
		foreach ($dbScreenItems as $dbScreenItem) {
			$key = $dbScreenItem['screenid'].'_'.$dbScreenItem['x'].'_'.$dbScreenItem['y'];
			$screenItemMap[$key] = $dbScreenItem['screenitemid'];
		}

		// substitute the items in the given positions with the ones in the database
		$updateItems = array();
		$createItems = array();
		foreach ($screenItems as $screenItem) {
			$key = $screenItem['screenid'].'_'.$screenItem['x'].'_'.$screenItem['y'];

			// an item in the given position exists, update it
			if (isset($screenItemMap[$key])) {
				$screenItem['screenitemid'] = $screenItemMap[$key];
				$updateItems[] = $screenItem;
			}
			// the given cell is free, create a new screen item
			else {
				$createItems[] = $screenItem;
			}
		}

		// save items
		$updateItemids = array();
		$createItemids = array();
		if ($updateItems) {
			$updateItemids = $this->update($updateItems);
			$updateItemids = $updateItemids['screenitemids'];
		}
		if ($createItems) {
			$createItemids = $this->create($createItems);
			$createItemids = $createItemids['screenitemids'];
		}

		// return the ids of the affected items
		return array('screenitemids' => array_merge($updateItemids, $createItemids));
	}

	/**
	 * Deletes the given screen items.
	 *
	 * @param array|int $screenItemids	The IDs of the screen items to delete
	 * @return array					An array, that contains the IDs of the deleted items
	 *									under the 'screenitemids' key
	 */
	public function delete($screenItemids) {
		$screenItemids = zbx_toArray($screenItemids);

		// check permissions
		$dbScreenItems = $this->get(array(
			'screenitemids' => $screenItemids,
			'preservekeys' => true
		));
		foreach ($screenItemids as $screenItemId) {
			if (!isset($dbScreenItems[$screenItemId])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		// delete screen items
		DB::delete($this->tableName(), array(
			'screenitemid' => $screenItemids
		));

		return array('screenitemids' => $screenItemids);
	}

	/**
	 * Returns true if the given screen items exist and are available for reading.
	 *
	 * @param array $screenItemids	An array if screen item IDs
	 * @return boolean
	 */
	public function isReadable(array $screenItemids) {
		if (!is_array($screenItemids)) {
			return false;
		}
		elseif (empty($screenItemids)) {
			return true;
		}

		$screenItemids = array_unique($screenItemids);

		$count = $this->get(array(
			'screenitemids' => $screenItemids,
			'countOutput' => true
		));

		return (count($screenItemids) == $count);
	}

	/**
	 * Returns true if the given screen items exist and are available for writing.
	 *
	 * @param array $screenItemids	An array if screen item IDs
	 * @return boolean
	 */
	public function isWritable(array $screenItemids) {
		if (!is_array($screenItemids)) {
			return false;
		}
		elseif (empty($screenItemids)) {
			return true;
		}

		$screenItemids = array_unique($screenItemids);

		$count = $this->get(array(
			'screenitemids' => $screenItemids,
			'editable' => true,
			'countOutput' => true
		));

		return (count($screenItemids) == $count);
	}

	/**
	 * Validates the given screen items.
	 *
	 * If the $dbScreenItems parameter is given, the screen items will be matched
	 * against the ones given in $dbScreenItems. If a screen item is not present in
	 * $dbScreenItems, a ZBX_API_ERROR_PERMISSIONS exception will be thrown.
	 *
	 * @throws APIException if a validation error occurred.
	 *
	 * @param array $screenItems	An array of screen items to validate
	 * @param array $dbScreenItems	An array of screen items $screenItems should be matched against
	 */
	protected function checkInput(array $screenItems, array $dbScreenItems = array()) {
		$hostgroups = array();
		$hosts = array();
		$graphs = array();
		$items = array();
		$maps = array();
		$screens = array();

		foreach ($screenItems as $screenItem) {
			// check if the item is editable
			if (!empty($screenItem['screenitemid']) && !isset($dbScreenItems[$screenItem['screenitemid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}

			// check resource type
			if (!$this->isValidResourceType($screenItem['resourcetype'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect resource type provided for screen item.'));
			}

			// perform resource type specific validation
			// save the affected object ids to validate them later
			$hostGroupResourceTypes = array(
				SCREEN_RESOURCE_HOSTS_INFO,
				SCREEN_RESOURCE_TRIGGERS_INFO,
				SCREEN_RESOURCE_TRIGGERS_OVERVIEW,
				SCREEN_RESOURCE_HOSTGROUP_TRIGGERS,
				SCREEN_RESOURCE_DATA_OVERVIEW
			);
			if (in_array($screenItem['resourcetype'], $hostGroupResourceTypes)) {
				$resourceIdRequired = !in_array($screenItem['resourcetype'], array(
					SCREEN_RESOURCE_HOSTS_INFO,
					SCREEN_RESOURCE_HOSTGROUP_TRIGGERS,
					SCREEN_RESOURCE_TRIGGERS_INFO
				));
				if (!$screenItem['resourceid'] && $resourceIdRequired) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No host group ID provided for screen element.'));
				}
				elseif ($screenItem['resourceid']) {
					$hostgroups[] = $screenItem['resourceid'];
				}
			}
			elseif ($screenItem['resourcetype'] == SCREEN_RESOURCE_HOST_TRIGGERS && $screenItem['resourceid']) {
				$hosts[] = $screenItem['resourceid'];
			}
			elseif ($screenItem['resourcetype'] == SCREEN_RESOURCE_GRAPH) {
				if (!$screenItem['resourceid']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No graph ID provided for screen element.'));
				}
				$graphs[] = $screenItem['resourceid'];
			}
			elseif (in_array($screenItem['resourcetype'], array(SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_PLAIN_TEXT))
				|| $screenItem['resourcetype'] == SCREEN_RESOURCE_CLOCK && $screenItem['style'] == TIME_TYPE_HOST) {
				if (!$screenItem['resourceid']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No item ID provided for screen element.'));
				}
				$items[] = $screenItem['resourceid'];
			}
			elseif ($screenItem['resourcetype'] == SCREEN_RESOURCE_MAP) {
				if (!$screenItem['resourceid']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No map ID provided for screen element.'));
				}
				$maps[] = $screenItem['resourceid'];
			}
			elseif ($screenItem['resourcetype'] == SCREEN_RESOURCE_SCREEN) {
				if (!$screenItem['resourceid']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No screen ID provided for screen element.'));
				}
				$screens[] = $screenItem['resourceid'];
			}
			elseif ($screenItem['resourcetype'] == SCREEN_RESOURCE_URL) {
				if (!$screenItem['url']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No URL provided for screen element.'));
				}
			}
		}

		// check host groups
		if (!empty($hostgroups)) {
			$result = API::HostGroup()->get(array(
				'groupids' => $hostgroups,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
			));
			foreach ($hostgroups as $id) {
				if (!isset($result[$id])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect host group ID "%s" provided for screen element.', $id));
				}
			}
		}

		// check hosts
		if ($hosts) {
			$result = API::Host()->get(array(
				'hostids' => $hosts,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
			));
			foreach ($hosts as $id) {
				if (!isset($result[$id])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect host ID "%s" provided for screen element.', $id));
				}
			}
		}

		// check graphs
		if ($graphs) {
			$result = API::Graph()->get(array(
				'graphids' => $graphs,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
			));
			foreach ($graphs as $id) {
				if (!isset($result[$id])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect graph ID "%s" provided for screen element.', $id));
				}
			}
		}

		// check items
		if ($items) {
			$result = API::Item()->get(array(
				'itemids' => $items,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true,
				'webitems' => true
			));
			foreach ($items as $id) {
				if (!isset($result[$id])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect item ID "%s" provided for screen element.', $id));
				}
			}
		}

		// check maps
		if ($maps) {
			$result = API::Map()->get(array(
				'sysmapids' => $maps,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
			));
			foreach ($maps as $id) {
				if (!isset($result[$id])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect map ID "%s" provided for screen element.', $id));
				}
			}
		}

		// check screens
		if ($screens) {
			$result = API::Screen()->get(array(
				'screenids' => $screens,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
			));
			if (empty($result)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect screen ID "%s" provided for screen element.', $id));
			}
		}
	}

	/**
	 * Returns true if the given resource type is supported.
	 *
	 * @param int $resourceType
	 * @return boolean
	 */
	protected function isValidResourceType($resourceType) {
		return in_array($resourceType, self::$resourceTypes);
	}

	/**
	 * Checks that the row and column spans are valid.
	 *
	 * @throws APIException if the any of the spans is not an integer or missing
	 *
	 * @param array $screenItem
	 *
	 * @return void
	 */
	protected function checkSpans(array $screenItem) {
		if (zbx_empty($screenItem['rowspan']) || !zbx_is_int($screenItem['rowspan'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('Incorrect row span provided for screen element located at X - %1$s and Y - %1$s.', $screenItem['x'], $screenItem['y'])
			);
		}
		if (zbx_empty($screenItem['colspan']) || !zbx_is_int($screenItem['colspan'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('Incorrect column span provided for screen element located at X - %1$s and Y - %1$s.', $screenItem['x'], $screenItem['y'])
			);
		}
	}

	/**
	 * Checks that the row and column spans fit into the size of the screen.
	 *
	 * @throws APIException if the any of the spans is bigger then the free space on the screen
	 *
	 * @param array $screenItem
	 * @param array $screen
	 *
	 * @return void
	 */
	protected function checkSpansInBounds(array $screenItem, array $screen) {
		if ($screenItem['rowspan'] > $screen['vsize'] - $screenItem['y']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('Row span of screen element located at X - %1$s and Y - %1$s is too big.', $screenItem['x'], $screenItem['y'])
			);
		}
		if ($screenItem['colspan'] > $screen['hsize'] - $screenItem['x']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('Column span of screen element located at X - %1$s and Y - %1$s is too big.', $screenItem['x'], $screenItem['y'])
			);
		}
	}

	protected function applyQueryNodeOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		// only apply the node option if no specific screen ids are given
		if ($options['screenids'] === null) {
			$sqlParts = parent::applyQueryNodeOptions($tableName, $tableAlias, $options, $sqlParts);
		}

		return $sqlParts;
	}

	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

		// screen ids
		if ($options['screenids'] !== null) {
			zbx_value2array($options['screenids']);
			$sqlParts = $this->addQuerySelect($this->fieldId('screenid'), $sqlParts);
			$sqlParts['where'][] = DBcondition($this->fieldId('screenid'), $options['screenids']);
		}

		return $sqlParts;
	}
}
