<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
?>
<?php
/**
 * File containing CScreenItem class for API.
 * @package API
 */
/**
 * Class containing methods for operations with ScreenItems
 */
class CScreenItem extends CZBXAPI {

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


	/**
	 * Sortable columns
	 *
	 * @var array
	 */
	protected static $sortColumns = array(
		'screenitemid',
		'screenid'
	);


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
		$defOptions = array(
			'nodeids'					=> null,
			'screenitemids'				=> null,
			'screenids'					=> null,
			'editable'					=> null,

			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,

			// output
			'output'					=> API_OUTPUT_REFER,
			'selectScreen'				=> null,				// not implemented
			'countOutput'				=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		// options
		$options = zbx_array_merge($defOptions, $options);

		// build and execute query
		$sql = $this->buildSql($options);
		$res = DBselect($sql, $options['limit']);

		// fetch results
		$result = array();
		while ($row = DBfetch($res)) {
			// a count query, return a single result
			if ($options['countOutput'] !== null) {
				$result = $row['rowscount'];
			}
			// a normal select query
			else {
				$result[$row['screenitemid']] = $row;
			}
		}

		// remove keys
		if ($options['preservekeys'] === null) {
			$result = zbx_cleanHashes($result);
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

		// validate input
		$this->checkInput($screenItems);

		// insert items
		$screenItemIds = DB::insert('screens_items', $screenItems);

		return array(
			'screenitemids' => $screenItemIds
		);
	}


	/**
	 * Updates the given screen items.
	 *
	 * @param array $screenItems	An array of screen items
	 * @return array				An array, that contains the IDs of the updated items
	 *								under the 'screenitemids' key
	 */
	public function update(array $screenItems) {

		// fetch the items we're updating
		$screenItemIds = zbx_objectValues($screenItems, 'screenitemid');
		$dbScreenItems = $this->get(array(
			'screenitemids' => $screenItemIds,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
		));

		// validate input
		$this->checkInput($screenItems, $dbScreenItems);

		// update items
		$update = array();
		foreach ($screenItems as $screenItem) {
			$screenItemId = $screenItem['screenitemid'];
			unset($screenItem['screenitemid']);
			$update[] = array(
				'values' => $screenItem,
				'where' => array(
					'screenitemid' => $screenItemId
				)
			);
		}
		DB::update('screens_items', $update);

		return array(
			'screenitemids' => zbx_objectValues($screenItems, 'screenitemid')
		);
	}


	/**
	 * Update screen items using the given 'x' and 'y' parameters.
	 * If the given cell is free, a new screen item will be created.
	 *
	 * @param array $screenItems    An array of screen items with the given X and Y coordinates
	 * @return array				An array, that contains the IDs of the updated items
	 *								under the 'screenitemids' key
	 */
	public function updateByPosition(array $screenItems) {

		// create a screen-position map
		$dbScreenItems = $this->get(array(
			'output' => array('screenitemid', 'x', 'y', 'screenid'),
			'screenids' => zbx_objectValues($screenItems, 'screenid'),
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
		$updateItemIds = array();
		$createItemIds = array();
		if ($updateItems) {
			$updateItemIds = $this->update($updateItems);
			$updateItemIds = $updateItemIds['screenitemids'];
		}
		if ($createItems) {
			$createItemIds = $this->create($createItems);
			$createItemIds = $createItemIds['screenitemids'];
		}

		// return the ids of the affected items
		return array(
			'screenitemids' => array_merge($updateItemIds, $createItemIds)
		);
	}


	/**
	 * Deletes the given screen items.
	 *
	 * @param array|int $screenItemIds	The IDs of the screen items to delete
	 * @return array					An array, that contains the IDs of the deleted items
	 *									under the 'screenitemids' key
	 */
	public function delete($screenItemIds) {
		$screenItemIds = zbx_toArray($screenItemIds);

		// check permissions
		$dbScreenItems = $this->get(array(
			'screenitemids' => $screenItemIds,
			'preservekeys' => true
		));
		foreach ($screenItemIds as $screenItemId) {
			if(!isset($dbScreenItems[$screenItemId])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		// delete screen items
		DB::delete('screens_items', array(
			'screenitemid' => $screenItemIds
		));

		return array('screenitemids' => $screenItemIds);
	}


	/**
	 * Returns true if the given screen items exist and are available for reading.
	 *
	 * @param array $screenItemIds  An array if screen item IDs
	 * @return boolean
	 */
	public function isReadable(array $screenItemIds) {
		if (!is_array($screenItemIds)) {
			return false;
		}
		elseif (empty($screenItemIds)) {
			return true;
		}

		$screenItemIds = array_unique($screenItemIds);

		$count = $this->get(array(
			'screenitemids' => $screenItemIds,
			'output' => API_OUTPUT_SHORTEN,
			'countOutput' => true
		));

		return (count($screenItemIds) == $count);
	}


	/**
	 * Returns true if the given screen items exist and are available for writing.
	 *
	 * @param array $screenItemIds  An array if screen item IDs
	 * @return boolean
	 */
	public function isWritable(array $screenItemIds) {
		if (!is_array($screenItemIds)) {
			return false;
		}
		elseif (empty($screenItemIds)) {
			return true;
		}

		$screenItemIds = array_unique($screenItemIds);

		$count = $this->get(array(
			'screenitemids' => $screenItemIds,
			'output' => API_OUTPUT_SHORTEN,
			'editable' => true,
			'countOutput' => true
		));

		return (count($screenItemIds) == $count);
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
	 * @param array $dbScreenItems	An array of screen items $screenItems should
	 *								be matched against
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
			if ($dbScreenItems && !isset($dbScreenItems[$screenItem['screenitemid']])) {
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
				if (!$screenItem['resourceid']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No host group ID provided for screen element.'));
				}
				$hostgroups[] = $screenItem['resourceid'];
			}
			elseif ($screenItem['resourcetype'] == SCREEN_RESOURCE_HOST_TRIGGERS) {
				if (!$screenItem['resourceid']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No host ID provided for screen element.'));
				}
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
		if(!empty($hostgroups)){
			$result = API::HostGroup()->get(array(
				'groupids' => $hostgroups,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1
			));
			foreach($hostgroups as $id){
				if(!isset($result[$id]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect host group ID "%s" provided for screen element.', $id));
			}
		}

		// check hosts
		if ($hosts) {
			$result = API::Host()->get(array(
				'hostids' => $hosts,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1
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
				'preservekeys' => 1
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
				'preservekeys' => 1,
				'webitems' => 1
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
				'preservekeys' => 1
			));
			foreach ($maps as $id) {
				if (!isset($result[$id])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect map ID "%s" provided for screen element.', $id));
				}
			}
		}

		// check screens
		if ($screens) {
			$result = $this->get(array(
				'screenids' => $screens,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1
			));
			foreach ($screens as $id) {
				if (!isset($result[$id])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect screen ID "%s" provided for screen element.', $id));
				}
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
	 * Builds a SELECT SQL query from the given options.
	 *
	 * @param array $options
	 * @return string         The resulting SQL query
	 */
	protected function buildSql(array $options) {
		$sqlParts = array(
			'select' => array('screenitems' => 'si.screenitemid'),
			'from' => array('screenitems' => 'screens_items si'),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null
		);

		// schema
		$schema = DB::getSchema('screens_items');

		// handle output
		$sqlParts = $this->buildSqlOutput($options, $sqlParts, $schema);

		// handle filters
		$sqlParts = $this->buildSqlFilters($options, $sqlParts);

		// check nodes
		$nodeids = ($options['nodeids'] !== null) ? $options['nodeids'] : get_current_nodeid();

		// build query
		$sqlSelect = implode(',', $sqlParts['select']);
		$sqlFrom = implode(',', $sqlParts['from']);
		$sqlWhere = ($sqlParts['where']) ? ' AND '.implode(' AND ', $sqlParts['where']) : '';
		$sqlGroup = ($sqlParts['group']) ? ' GROUP BY '.implode(',', $sqlParts['group']) : '';
		$sqlOrder = ($sqlParts['order']) ? ' ORDER BY '.implode(',', $sqlParts['order']) : '';
		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				' WHERE '.DBin_node('si.screenitemid', $nodeids).
					$sqlWhere.
				$sqlGroup.
				$sqlOrder;

		return $sql;
	}


	/**
	 * Modifies the SQL parts to implement all of the ouput related options.
	 *
	 * @param array $options
	 * @param array $sqlParts
	 * @param array $schema
	 * @return array
	 */
	protected function buildSqlOutput(array $options, array $sqlParts, array $schema) {
		// custom output
		if (is_array($options['output'])) {
			foreach ($options['output'] as $field) {
				if (isset($schema['fields'][$field])) {
					$sqlParts['select'][$field] = 'si.'.$field;
				}
			}
		}
		// extendex output
		elseif ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['screens_items'] = 'si.*';
		}

		// count
		if ($options['countOutput'] !== null) {
			$sqlParts['select'] = array('COUNT(DISTINCT si.screenitemid) AS rowscount');
		}

		// sort
		$sortfield = $options['sortfield'];
		if (in_array($sortfield, self::$sortColumns)) {
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN) ? ZBX_SORT_DOWN : ZBX_SORT_UP;
			$sqlParts['order'][] = 'si.'.$sortfield.' '.$sortorder;

			if (!array_intersect(array('si.'.$sortfield, 'si.*'), array($sqlParts['select']))) {
				$sqlParts['select'][] = 'si.'.$sortfield;
			}
		}

		return $sqlParts;
	}


	/**
	 * Modifies the SQL parts to implement all of the filter related options.
	 *
	 * @param array $options
	 * @param array $sqlParts
	 * @return type
	 */
	protected function buildSqlFilters(array $options, array $sqlParts) {

		// screen item ids
		if ($options['screenitemids'] !== null) {
			zbx_value2array($options['screenitemids']);
			$sqlParts['where'][] = DBcondition('si.screenitemid', $options['screenitemids']);
		}

		// screen ids
		if ($options['screenids'] !== null) {
			zbx_value2array($options['screenids']);
			$sqlParts['where'][] = DBcondition('si.screenid', $options['screenids']);
		}

		// filters
		if (is_array($options['filter'])) {
			zbx_db_filter('screens_items si', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('screens_items si', $options, $sqlParts);
		}

		return $sqlParts;
	}

}
