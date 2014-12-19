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
 * Class containing methods for operations with screen items.
 *
 * @package API
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
	 * Get screem item data.
	 *
	 * @param array $options
	 * @param array $options['nodeids']			Node IDs
	 * @param array $options['screenitemids']	Search by screen item IDs
	 * @param array $options['screenids']		Search by screen IDs
	 * @param array $options['filter']			Result filter
	 * @param array $options['limit']			The size of the result set
	 *
	 * @return array
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
					$result[$row['screenitemid']] = $row;
				}
				else {
					$result[] = $row;
				}
			}
		}

		return $result;
	}

	/**
	 * Create screen items.
	 *
	 * @param array $screenItems	An array of screen items
	 *
	 * @return array
	 */
	public function create(array $screenItems) {
		$screenItems = zbx_toArray($screenItems);

		$this->validateCreate($screenItems);

		$screenItemIds = DB::insert($this->tableName(), $screenItems);

		return array('screenitemids' => $screenItemIds);
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $screenItems
	 */
	protected function validateCreate(array $screenItems) {
		$screenItemDBfields = array(
			'screenid' => null,
			'resourcetype' => null
		);

		foreach ($screenItems as &$screenItem) {
			if (!check_db_fields($screenItemDBfields, $screenItem)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}

			unset($screenItem['screenitemid']);
		}
		unset($screenItem);

		$screenIds = array_keys(array_flip(zbx_objectValues($screenItems, 'screenid')));

		$dbScreens = API::Screen()->get(array(
			'screenids' => $screenIds,
			'output' => array('screenid', 'hsize', 'vsize', 'name'),
			'editable' => true,
			'preservekeys' => true
		));

		if (count($dbScreens) < count($screenIds)) {
			$dbTemplateScreens = API::TemplateScreen()->get(array(
				'screenids' => $screenIds,
				'output' => array('screenid', 'hsize', 'vsize', 'name'),
				'editable' => true,
				'preservekeys' => true
			));

			if ($dbTemplateScreens) {
				$dbScreens = zbx_array_merge($dbScreens, $dbTemplateScreens);
			}
		}

		$dbScreenItems = $this->get(array(
			'screenids' => $screenIds,
			'output' => array('screenitemid', 'screenid', 'x', 'y', 'rowspan', 'colspan'),
			'editable' => true,
			'preservekeys' => true
		));

		$this->checkInput($screenItems, $dbScreenItems);
		$this->checkDuplicateResourceInCell($screenItems, $dbScreenItems, $dbScreens);

		foreach ($screenItems as $screenItem) {
			$this->checkSpans($screenItem, $dbScreens[$screenItem['screenid']]);
			$this->checkSpansInBounds($screenItem, $dbScreenItems, $dbScreens[$screenItem['screenid']]);
			$this->checkGridCoordinates($screenItem, $dbScreens[$screenItem['screenid']]);
		}
	}

	/**
	 * Updates screen items.
	 *
	 * @param array $screenItems	An array of screen items
	 *
	 * @return array
	 */
	public function update(array $screenItems) {
		$screenItems = zbx_toArray($screenItems);

		$this->validateUpdate($screenItems);

		$screenItems = zbx_toHash($screenItems, 'screenitemid');

		$update = $screenItemIds = array();

		foreach ($screenItems as $screenItem) {
			$screenItemId = $screenItem['screenitemid'];
			unset($screenItem['screenitemid']);

			$update[] = array(
				'values' => $screenItem,
				'where' => array('screenitemid' => $screenItemId)
			);

			$screenItemIds[] = $screenItemId;
		}

		DB::update($this->tableName(), $update);

		return array('screenitemids' => $screenItemIds);
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @throws APIException
	 *
	 * @param array $screenItems
	 */
	protected function validateUpdate(array $screenItems) {
		$screenItemDBfields = array(
			'screenitemid' => null
		);

		foreach ($screenItems as $screenItem) {
			if (!check_db_fields($screenItemDBfields, $screenItem)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
		}

		$screenItems = zbx_toHash($screenItems, 'screenitemid');
		$screenItemIds = array_keys($screenItems);

		$dbScreens = API::Screen()->get(array(
			'screenitemids' => $screenItemIds,
			'output' => array('screenid', 'hsize', 'vsize', 'name'),
			'editable' => true,
			'preservekeys' => true
		));

		$dbTemplateScreens = API::TemplateScreen()->get(array(
			'screenitemids' => $screenItemIds,
			'output' => array('screenid', 'hsize', 'vsize', 'name'),
			'editable' => true,
			'preservekeys' => true
		));

		if ($dbTemplateScreens) {
			$dbScreens = zbx_array_merge($dbScreens, $dbTemplateScreens);
		}

		$dbScreenItems = $this->get(array(
			'screenitemids' => $screenItemIds,
			'output' => array('screenitemid', 'screenid', 'x', 'y', 'rowspan', 'colspan', 'resourcetype', 'resourceid'),
			'editable' => true,
			'preservekeys' => true
		));

		$screenItems = $this->extendObjects($this->tableName(), $screenItems, array('screenid', 'x', 'y', 'rowspan', 'colspan'));

		$this->checkInput($screenItems, $dbScreenItems);
		$this->checkDuplicateResourceInCell($screenItems, $dbScreenItems, $dbScreens);

		foreach ($screenItems as $screenItem) {
			$this->checkSpans($screenItem, $dbScreens[$screenItem['screenid']]);
			$this->checkSpansInBounds($screenItem, $dbScreenItems, $dbScreens[$screenItem['screenid']]);
			$this->checkGridCoordinates($screenItem, $dbScreens[$screenItem['screenid']]);
		}
	}

	/**
	 * Update screen items using the given 'x' and 'y' parameters.
	 * If the given cell is free, a new screen item will be created.
	 *
	 * @param array $screenItems	An array of screen items with the given X and Y coordinates
	 *
	 * @return array
	 */
	public function updateByPosition(array $screenItems) {
		$screenItemDBfields = array(
			'screenid' => null,
			'x' => null,
			'y' => null
		);

		foreach ($screenItems as $screenItem) {
			if (!check_db_fields($screenItemDBfields, $screenItem)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
		}

		$dbScreenItems = $this->get(array(
			'screenids' => zbx_objectValues($screenItems, 'screenid'),
			'output' => array('screenitemid', 'screenid', 'x', 'y'),
			'editable' => true,
			'preservekeys' => true
		));

		$create = $update = $affectedIds = array();

		foreach ($screenItems as $screenItem) {
			foreach ($dbScreenItems as $dbScreenItem) {
				if ($screenItem['screenid'] == $dbScreenItem['screenid']
						&& $screenItem['x'] == $dbScreenItem['x'] && $screenItem['y'] == $dbScreenItem['y']) {
					$screenItem['screenitemid'] = $dbScreenItem['screenitemid'];
					$update[$dbScreenItem['screenitemid']] = $screenItem;

					continue 2;
				}
			}

			$create[] = $screenItem;
		}

		if ($update) {
			$screenItems = API::ScreenItem()->update($update);

			$affectedIds = $screenItems['screenitemids'];
		}

		if ($create) {
			$screenItems = API::ScreenItem()->create($create);

			$affectedIds = array_merge($affectedIds, $screenItems['screenitemids']);
		}

		return array('screenitemids' => $affectedIds);
	}

	/**
	 * Deletes screen items.
	 *
	 * @param array $screenItemIds
	 *
	 * @return array
	 */
	public function delete($screenItemIds) {
		$screenItemIds = zbx_toArray($screenItemIds);

		// check permissions
		$dbScreenItems = $this->get(array(
			'screenitemids' => $screenItemIds,
			'preservekeys' => true
		));

		foreach ($screenItemIds as $screenItemId) {
			if (!isset($dbScreenItems[$screenItemId])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		// delete screen items
		DB::delete($this->tableName(), array(
			'screenitemid' => $screenItemIds
		));

		return array('screenitemids' => $screenItemIds);
	}

	/**
	 * Returns true if the given screen items exist and are available for reading.
	 *
	 * @param array $screenItemIds
	 *
	 * @return bool
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
			'countOutput' => true
		));

		return (count($screenItemIds) == $count);
	}

	/**
	 * Returns true if the given screen items exist and are available for writing.
	 *
	 * @param array $screenItemIds	An array if screen item IDs
	 *
	 * @return bool
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
			'editable' => true,
			'countOutput' => true
		));

		return (count($screenItemIds) == $count);
	}

	/**
	 * Validates screen items.
	 *
	 * If the $dbScreenItems parameter is given, the screen items will be matched
	 * against the ones given in $dbScreenItems. If a screen item is not present in
	 * $dbScreenItems, a ZBX_API_ERROR_PERMISSIONS exception will be thrown.
	 *
	 * @throws APIException if a validation error occurred.
	 *
	 * @param array $screenItems
	 * @param array $dbScreenItems
	 */
	protected function checkInput(array $screenItems, array $dbScreenItems = array()) {
		$hostGroupsIds = $hostIds = $graphIds = $itemIds = $mapIds = $screenIds = array();

		$screenItems = $this->extendFromObjects($screenItems, $dbScreenItems, array('resourcetype', 'resourceid'));

		foreach ($screenItems as $screenItem) {
			// check permissions
			if (isset($screenItem['screenitemid']) && !isset($dbScreenItems[$screenItem['screenitemid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}

			if (!$this->isValidResourceType($screenItem['resourcetype'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect resource type provided for screen item.'));
			}

			if (!isset($screenItem['resourceid'])) {
				$screenItem['resourceid'] = null;
			}

			// check resource id
			switch ($screenItem['resourcetype']) {
				case SCREEN_RESOURCE_HOSTS_INFO:
				case SCREEN_RESOURCE_TRIGGERS_INFO:
				case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
				case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
				case SCREEN_RESOURCE_DATA_OVERVIEW:
					if (in_array($screenItem['resourcetype'], array(SCREEN_RESOURCE_TRIGGERS_OVERVIEW, SCREEN_RESOURCE_DATA_OVERVIEW))) {
						if (!$screenItem['resourceid']) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('No host group ID provided for screen element.'));
						}
					}

					if ($screenItem['resourceid']) {
						$hostGroupsIds[$screenItem['resourceid']] = $screenItem['resourceid'];
					}
					break;

				case SCREEN_RESOURCE_HOST_TRIGGERS:
					if ($screenItem['resourceid']) {
						$hostIds[$screenItem['resourceid']] = $screenItem['resourceid'];
					}
					break;

				case SCREEN_RESOURCE_GRAPH:
					if (!$screenItem['resourceid']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No graph ID provided for screen element.'));
					}

					$graphIds[$screenItem['resourceid']] = $screenItem['resourceid'];
					break;

				case SCREEN_RESOURCE_SIMPLE_GRAPH:
				case SCREEN_RESOURCE_PLAIN_TEXT:
					if (!$screenItem['resourceid']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No item ID provided for screen element.'));
					}

					$itemIds[$screenItem['resourceid']] = $screenItem['resourceid'];
					break;

				case SCREEN_RESOURCE_CLOCK:
					if (isset($screenItem['style']) && $screenItem['style'] == TIME_TYPE_HOST) {
						if (!$screenItem['resourceid']) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('No item ID provided for screen element.'));
						}

						$itemIds[$screenItem['resourceid']] = $screenItem['resourceid'];
					}
					break;

				case SCREEN_RESOURCE_MAP:
					if (!$screenItem['resourceid']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No map ID provided for screen element.'));
					}

					$mapIds[$screenItem['resourceid']] = $screenItem['resourceid'];
					break;

				case SCREEN_RESOURCE_SCREEN:
					if (!$screenItem['resourceid']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No screen ID provided for screen element.'));
					}

					$screenIds[$screenItem['resourceid']] = $screenItem['resourceid'];
					break;
			}

			// check url
			if ($screenItem['resourcetype'] == SCREEN_RESOURCE_URL) {
				if (!isset($screenItem['url']) || zbx_empty($screenItem['url'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No URL provided for screen element.'));
				}
			}

			// check "Show lines"
			if (isset($screenItem['elements'])) {
				switch ($screenItem['resourcetype']) {
					case SCREEN_RESOURCE_ACTIONS:
					case SCREEN_RESOURCE_EVENTS:
					case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
					case SCREEN_RESOURCE_HOST_TRIGGERS:
					case SCREEN_RESOURCE_PLAIN_TEXT:
						if ($screenItem['elements'] < 1 || $screenItem['elements'] > 100) {
							self::exception(
								ZBX_API_ERROR_PARAMETERS,
								_s(
									'Incorrect value "%1$s" for "%2$s" field: must be between %3$s and %4$s.',
									$screenItem['elements'],
									'elements',
									1,
									100
								)
							);
						}
						break;
				}
			}
		}

		// check host groups
		if ($hostGroupsIds) {
			$dbHostGroups = API::HostGroup()->get(array(
				'groupids' => $hostGroupsIds,
				'output' => array('groupid'),
				'editable' => true,
				'preservekeys' => true
			));

			foreach ($hostGroupsIds as $hostGroupsId) {
				if (!isset($dbHostGroups[$hostGroupsId])) {
					self::exception(
						ZBX_API_ERROR_PERMISSIONS,
						_s('Incorrect host group ID "%1$s" provided for screen element.', $hostGroupsId)
					);
				}
			}
		}

		// check hosts
		if ($hostIds) {
			$dbHosts = API::Host()->get(array(
				'hostids' => $hostIds,
				'output' => array('hostid'),
				'editable' => true,
				'preservekeys' => true
			));

			foreach ($hostIds as $hostId) {
				if (!isset($dbHosts[$hostId])) {
					self::exception(
						ZBX_API_ERROR_PERMISSIONS,
						_s('Incorrect host ID "%1$s" provided for screen element.', $hostId)
					);
				}
			}
		}

		// check graphs
		if ($graphIds) {
			$dbGraphs = API::Graph()->get(array(
				'graphids' => $graphIds,
				'output' => array('graphid'),
				'editable' => true,
				'preservekeys' => true
			));

			foreach ($graphIds as $graphId) {
				if (!isset($dbGraphs[$graphId])) {
					self::exception(
						ZBX_API_ERROR_PERMISSIONS,
						_s('Incorrect graph ID "%1$s" provided for screen element.', $graphId)
					);
				}
			}
		}

		// check items
		if ($itemIds) {
			$dbItems = API::Item()->get(array(
				'itemids' => $itemIds,
				'output' => array('itemid'),
				'editable' => true,
				'preservekeys' => true,
				'webitems' => true
			));

			foreach ($itemIds as $itemId) {
				if (!isset($dbItems[$itemId])) {
					self::exception(
						ZBX_API_ERROR_PERMISSIONS,
						_s('Incorrect item ID "%1$s" provided for screen element.', $itemId)
					);
				}
			}
		}

		// check maps
		if ($mapIds) {
			$dbMaps = API::Map()->get(array(
				'sysmapids' => $mapIds,
				'output' => array('sysmapid'),
				'editable' => true,
				'preservekeys' => true
			));

			foreach ($mapIds as $mapId) {
				if (!isset($dbMaps[$mapId])) {
					self::exception(
						ZBX_API_ERROR_PERMISSIONS,
						_s('Incorrect map ID "%1$s" provided for screen element.', $mapId)
					);
				}
			}
		}

		// check screens
		if ($screenIds) {
			$dbScreens = API::Screen()->get(array(
				'screenids' => $screenIds,
				'output' => array('screenid'),
				'editable' => true,
				'preservekeys' => true
			));

			if (count($dbScreens) < count($screenIds)) {
				$dbTemplateScreens = API::TemplateScreen()->get(array(
					'screenids' => $screenIds,
					'output' => array('screenid'),
					'editable' => true,
					'preservekeys' => true
				));

				if ($dbTemplateScreens) {
					$dbScreens = zbx_array_merge($dbScreens, $dbTemplateScreens);
				}
			}

			foreach ($screenIds as $screenId) {
				if (!isset($dbScreens[$screenId])) {
					self::exception(
						ZBX_API_ERROR_PERMISSIONS,
						_s('Incorrect screen ID "%1$s" provided for screen element.', $screenId)
					);
				}
			}
		}
	}

	/**
	 * Returns true if the given resource type is supported.
	 *
	 * @param int $resourceType
	 *
	 * @return bool
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
	 * @param array $screen
	 */
	protected function checkSpans(array $screenItem, array $screen) {
		if (isset($screenItem['rowspan'])) {
			if (!zbx_is_int($screenItem['rowspan']) || $screenItem['rowspan'] < 0) {
				self::exception(
					ZBX_API_ERROR_PARAMETERS,
					_s(
						'Screen "%1$s" row span in cell X - %2$s Y - %3$s is incorrect.',
						$screen['name'],
						$screenItem['x'],
						$screenItem['y']
					)
				);
			}
		}

		if (isset($screenItem['colspan'])) {
			if (!zbx_is_int($screenItem['colspan']) || $screenItem['colspan'] < 0) {
				self::exception(
					ZBX_API_ERROR_PARAMETERS,
					_s(
						'Screen "%1$s" column span in cell X - %2$s Y - %3$s is incorrect.',
						$screen['name'],
						$screenItem['x'],
						$screenItem['y']
					)
				);
			}
		}
	}

	/**
	 * Checks that the row and column spans fit into the size of the screen.
	 *
	 * @throws APIException if the any of the spans is bigger then the free space on the screen
	 *
	 * @param array $screenItem
	 * @param array $dbScreenItems
	 * @param array $screen
	 */
	protected function checkSpansInBounds(array $screenItem, array $dbScreenItems, array $screen) {
		if (!isset($screenItem['x'])) {
			$screenItem['x'] = isset($screenItem['screenitemid'])
				? $dbScreenItems[$screenItem['screenitemid']]['x']
				: 0;
		}
		if (!isset($screenItem['y'])) {
			$screenItem['y'] = isset($screenItem['screenitemid'])
				? $dbScreenItems[$screenItem['screenitemid']]['y']
				: 0;
		}

		if (isset($screenItem['rowspan']) && isset($screen['vsize'])
				&& $screenItem['rowspan'] > $screen['vsize'] - $screenItem['y']) {
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s(
					'Screen "%1$s" row span in cell X - %2$s Y - %3$s is too big.',
					$screen['name'],
					$screenItem['x'],
					$screenItem['y']
				)
			);
		}

		if (isset($screenItem['colspan']) && isset($screen['hsize'])
				&& $screenItem['colspan'] > $screen['hsize'] - $screenItem['x']) {
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s(
					'Screen "%1$s" column span in cell X - %2$s Y - %3$s is too big.',
					$screen['name'],
					$screenItem['x'],
					$screenItem['y']
				)
			);
		}
	}

	/**
	 * Check duplicates screen items in one cell.
	 *
	 * @throws APIException
	 *
	 * @param array $screenItems
	 * @param array $dbScreenItems
	 * @param array $dbScreens
	 */
	protected function checkDuplicateResourceInCell(array $screenItems, array $dbScreenItems, array $dbScreens) {
		foreach ($screenItems as &$screenItem) {
			if (!isset($screenItem['x'])) {
				$screenItem['x'] = isset($screenItem['screenitemid'])
					? $dbScreenItems[$screenItem['screenitemid']]['x']
					: 0;
			}
			if (!isset($screenItem['y'])) {
				$screenItem['y'] = isset($screenItem['screenitemid'])
					? $dbScreenItems[$screenItem['screenitemid']]['y']
					: 0;
			}
		}
		unset($screenItem);

		foreach ($screenItems as $key => $screenItem) {
			// check between input and input
			foreach ($screenItems as $key2 => $screenItem2) {
				if ($key == $key2) {
					continue;
				}

				if ($screenItem['x'] == $screenItem2['x'] &&
					$screenItem['y'] == $screenItem2['y'] &&
					$screenItem['screenid'] == $screenItem2['screenid']
				) {
					$screenId = isset($screenItem['screenitemid'])
						? $dbScreenItems[$screenItem['screenitemid']]['screenid']
						: $screenItem['screenid'];

					self::exception(
						ZBX_API_ERROR_PARAMETERS,
						_s(
							'Screen "%1$s" cell X - %2$s Y - %3$s is already taken.',
							$dbScreens[$screenId]['name'],
							$screenItem['x'],
							$screenItem['y']
						)
					);
				}
			}

			// check between input and db
			foreach ($dbScreenItems as $dbScreenItem) {
				if (isset($screenItem['screenitemid'])
						&& bccomp($screenItem['screenitemid'], $dbScreenItem['screenitemid']) == 0) {
					continue;
				}

				if ($screenItem['x'] == $dbScreenItem['x'] && $screenItem['y'] == $dbScreenItem['y']) {
					$screenId = isset($screenItem['screenitemid'])
						? $dbScreenItems[$screenItem['screenitemid']]['screenid']
						: $screenItem['screenid'];

					self::exception(
						ZBX_API_ERROR_PARAMETERS,
						_s(
							'Screen "%1$s" cell X - %2$s Y - %3$s is already taken.',
							$dbScreens[$screenId]['name'],
							$screenItem['x'],
							$screenItem['y']
						)
					);
				}
			}
		}
	}

	/**
	 * Checks that the row and column fit into the size of the screen.
	 *
	 * @throws APIException if the any of the coordinates is bigger then the free space on the screen
	 *
	 * @param array $screenItem
	 * @param array $screen
	 */
	protected function checkGridCoordinates(array $screenItem, array $screen) {
		if (isset($screenItem['x']) && $screenItem['x'] > $screen['hsize'] - 1) {
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s(
					'The X coordinate of screen element located at X - %1$s and Y - %2$s of screen "%3$s" is too big.',
					$screenItem['x'],
					$screenItem['y'],
					$screen['name']
				)
			);
		}

		if (isset($screenItem['y']) && $screenItem['y'] > $screen['vsize'] - 1) {
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s(
					'The Y coordinate of screen element located at X - %1$s and Y - %2$s of screen "%3$s" is too big.',
					$screenItem['x'],
					$screenItem['y'],
					$screen['name']
				)
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

		// screens
		if ($options['screenids'] !== null) {
			zbx_value2array($options['screenids']);
			$sqlParts = $this->addQuerySelect($this->fieldId('screenid'), $sqlParts);
			$sqlParts['where'][] = dbConditionInt($this->fieldId('screenid'), $options['screenids']);
		}

		return $sqlParts;
	}
}
