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
 * Class containing methods for operations with template screens.
 */
class CTemplateScreen extends CScreen {

	protected $tableName = 'screens';
	protected $tableAlias = 's';

	/**
	 * Get Screen data
	 *
	 * @param array $options
	 * @param array $options['nodeids'] Node IDs
	 * @param boolean $options['with_items'] only with items
	 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
	 * @param int $options['count'] count Hosts, returned column name is rowscount
	 * @param string $options['pattern'] search hosts by pattern in host names
	 * @param int $options['limit'] limit selection
	 * @param string $options['order'] deprecated parameter (for now)
	 * @return array|boolean Host data as array or false if error
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('screenid', 'name');

		$sqlParts = array(
			'select'	=> array('screens' => 's.screenid, s.templateid'),
			'from'		=> array('screens' => 'screens s'),
			'where'		=> array('template' => 's.templateid IS NOT NULL'),
			'order'		=> array(),
			'group'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'screenids'					=> null,
			'screenitemids'				=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'editable'					=> null,
			'noInheritance'				=> null,
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

		if (!is_null($options['editable']) || (is_null($options['hostids']) && is_null($options['templateids']))) {
			$options['noInheritance'] = 1;
		}

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			// TODO: think how we could combine templateids && hostids options
			if (!is_null($options['templateids'])) {
				unset($options['hostids']);

				$options['templateids'] = API::Template()->get(array(
					'templateids' => $options['templateids'],
					'editable' => $options['editable'],
					'preservekeys' => true
				));
				$options['templateids'] = array_keys($options['templateids']);
			}
			elseif (!is_null($options['hostids'])) {
				$options['templateids'] = API::Host()->get(array(
					'hostids' => $options['hostids'],
					'editable' => $options['editable'],
					'preservekeys' => true
				));
				$options['templateids'] = array_keys($options['templateids']);
			}
			else {
				// TODO: get screen
				$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

				$userGroups = getUserGroupsByUserId($userid);

				$sqlParts['where'][] = 'EXISTS ('.
						'SELECT NULL'.
						' FROM hosts_groups hgg'.
							' JOIN rights r'.
								' ON r.id=hgg.groupid'.
									' AND '.dbConditionInt('r.groupid', $userGroups).
						' WHERE s.templateid=hgg.hostid'.
						' GROUP BY hgg.hostid'.
						' HAVING MIN(r.permission)>'.PERM_DENY.
							' AND MAX(r.permission)>='.$permission.
						')';
			}
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

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

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			if (isset($options['hostids']) && !is_null($options['hostids'])) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else {
				$options['hostids'] = $options['templateids'];
			}
		}

		// hostids
		$templatesChain = array();
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			// collecting template chain
			$linkedTemplateids = $options['hostids'];
			$childTemplateids = $options['hostids'];

			while (is_null($options['noInheritance']) && !empty($childTemplateids)) {
				$sql = 'SELECT ht.*'.
					' FROM hosts_templates ht'.
					' WHERE '.dbConditionInt('hostid', $childTemplateids);
				$dbTemplates = DBselect($sql);

				$childTemplateids = array();
				while ($link = DBfetch($dbTemplates)) {
					$childTemplateids[$link['templateid']] = $link['templateid'];
					$linkedTemplateids[$link['templateid']] = $link['templateid'];

					createParentToChildRelation($templatesChain, $link, 'templateid', 'hostid');
				}
			}
			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['templateid'] = 's.templateid';
			}
			$sqlParts['where']['templateid'] = dbConditionInt('s.templateid', $linkedTemplateids);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('screens s', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('screens s', $options, $sqlParts);
		}

		// sorting
		$this->dbSorting($sqlParts, $options, $sortColumns, 's');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($screen = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $screen;
				}
				else {
					$result = $screen['rowscount'];
				}
			}
			else {
				if (!isset($result[$screen['screenid']])) {
					$result[$screen['screenid']] = array();
				}

				if (isset($screen['screenitemid']) && is_null($options['selectScreenItems'])) {
					if (!isset($result[$screen['screenid']]['screenitems'])) {
						$result[$screen['screenid']]['screenitems'] = array();
					}
					$result[$screen['screenid']]['screenitems'][] = array('screenitemid' => $screen['screenitemid']);
					unset($screen['screenitemid']);
				}
				$result[$screen['screenid']] += $screen;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result, $templatesChain);
			$result = $this->unsetExtraFields($result, array('templateid'), $options['output']);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		elseif (!is_null($options['noInheritance'])) {
			$result = zbx_toHash($result, 'screenid');
		}

		return $result;
	}

	public function exists($data) {
		$keyFields = array(array('screenid', 'name'), 'templateid');

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
	 * Create Screen
	 *
	 * @param array $screens
	 * @param string $screens['name']
	 * @param array $screens['hsize']
	 * @param int $screens['vsize']
	 * @return array
	 */
	public function create(array $screens) {
		$screens = zbx_toArray($screens);
		$this->validateCreate($screens);

		$insertScreenItems = array();

		$screenids = DB::insert('screens', $screens);

		foreach ($screens as $snum => $screen) {
			if (isset($screen['screenitems'])) {
				foreach ($screen['screenitems'] as $screenitem) {
					$screenitem['screenid'] = $screenids[$snum];
					$insertScreenItems[] = $screenitem;
				}
			}
		}
		API::ScreenItem()->create($insertScreenItems);

		return array('screenids' => $screenids);
	}

	/**
	 * Update Screen
	 *
	 * @param array $screens multidimensional array with Hosts data
	 * @param string $screens['screenid']
	 * @param int $screens['name']
	 * @param int $screens['hsize']
	 * @param int $screens['vsize']
	 * @return boolean
	 */
	public function update(array $screens) {
		$screens = zbx_toArray($screens);
		$update = array();
		$this->validateUpdate($screens);

		foreach ($screens as $screen) {
			$screenid = $screen['screenid'];
			unset($screen['screenid']);
			if (!empty($screen)) {
				$update[] = array(
					'values' => $screen,
					'where' => array('screenid' => $screenid)
				);
			}

			if (isset($screen['screenitems'])) {
				$this->replaceItems($screenid, $screen['screenitems']);
			}
		}
		DB::update('screens', $update);

		return  array('screenids' => zbx_objectValues($screens, 'screenid'));
	}

	/**
	 * Delete Screen
	 *
	 * @param array $screenids
	 * @return boolean
	 */
	public function delete($screenIds) {
		$screenIds = zbx_toArray($screenIds);

		$this->validateDelete($screenIds);

		DB::delete('screens_items', array('screenid' => $screenIds));
		DB::delete('screens_items', array('resourceid' => $screenIds, 'resourcetype' => SCREEN_RESOURCE_SCREEN));
		DB::delete('slides', array('screenid' => $screenIds));
		DB::delete('screens', array('screenid' => $screenIds));

		return array('screenids' => $screenIds);
	}

	public function copy(array $data) {
		$screenIds = $data['screenIds'] = zbx_toArray($data['screenIds']);
		$templateIds = $data['templateIds'] = zbx_toArray($data['templateIds']);

		$this->validateCopy($data);

		$screens = $this->get(array(
			'screenids' => $screenIds,
			'output' => API_OUTPUT_EXTEND,
			'selectScreenItems' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		foreach ($templateIds as $templateId) {
			$resourceGraphIds = array();
			$resourceItemIds = array();
			foreach ($screens as &$screen) {
				$screen['templateid'] = $templateId;

				foreach ($screen['screenitems'] as $screenItem) {
					if ($screenItem['resourceid']) {
						switch ($screenItem['resourcetype']) {
							case SCREEN_RESOURCE_GRAPH:
								$resourceGraphIds[] = $screenItem['resourceid'];
								break;
							default:
								$resourceItemIds[] = $screenItem['resourceid'];
						}
					}
				}
			}
			unset($screen);


			// get same items on destination template
			$resourceItemsMap = array();
			$dbItems = DBselect(
				'SELECT src.itemid as srcid,dest.itemid as destid'.
						' FROM items dest,items src'.
						' WHERE dest.key_=src.key_'.
						' AND dest.hostid='.$templateId.
						' AND '.dbConditionInt('src.itemid', $resourceItemIds)
			);
			while ($dbItem = DBfetch($dbItems)) {
				$resourceItemsMap[$dbItem['srcid']] = $dbItem['destid'];
			}

			// get same graphs on destination template
			$resourceGraphsMap = array();
			$dbItems = DBselect(
				'SELECT src.graphid as srcid,dest.graphid as destid'.
						' FROM graphs dest,graphs src,graphs_items destgi,items desti'.
						' WHERE dest.name=src.name'.
						' AND destgi.graphid=dest.graphid'.
						' AND destgi.itemid=desti.itemid'.
						' AND desti.hostid='.$templateId.
						' AND '.dbConditionInt('src.graphid', $resourceGraphIds)
			);
			while ($dbItem = DBfetch($dbItems)) {
				$resourceGraphsMap[$dbItem['srcid']] = $dbItem['destid'];
			}


			$newScreenIds = DB::insert('screens', $screens);

			$insertScreenItems = array();
			foreach ($screens as $snum => $screen) {
				foreach ($screen['screenitems'] as $screenItem) {
					$screenItem['screenid'] = $newScreenIds[$snum];

					$rid = $screenItem['resourceid'];

					switch ($screenItem['resourcetype']) {
						case SCREEN_RESOURCE_GRAPH:
							if ($rid && !isset($resourceGraphsMap[$rid])) {
								$graph = DBfetch(DBselect('SELECT g.name FROM graphs g WHERE g.graphid='.zbx_dbstr($rid)));
								$template = DBfetch(DBselect('SELECT h.name FROM hosts h WHERE h.hostid='.zbx_dbstr($templateId)));
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph "%1$s" does not exist on template "%2$s".',
									$graph['name'], $template['name']));
							}

							$screenItem['resourceid'] = $resourceGraphsMap[$rid];
							break;
						default:
							if ($rid && !isset($resourceItemsMap[$rid])) {
								$item = DBfetch(DBselect('SELECT i.name FROM items i WHERE i.itemid='.zbx_dbstr($rid)));
								$template = DBfetch(DBselect('SELECT h.name FROM hosts h WHERE h.hostid='.zbx_dbstr($templateId)));
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item "%1$s" does not exist on template "%2$s".',
									$item['name'], $template['name']));
							}

							$screenItem['resourceid'] = $resourceItemsMap[$rid];
					}


					$insertScreenItems[] = $screenItem;
				}
			}

			DB::insert('screens_items', $insertScreenItems);
		}

		return true;
	}

	/**
	 * Validate input for create method.
	 *
	 * @param array $screens
	 */
	protected function validateCreate(array $screens) {
		$screenNames = zbx_objectValues($screens, 'name');
		$templateids = zbx_objectValues($screens, 'templateid');

		$dbScreens = $this->get(array(
			'filter' => array(
				'name' => $screenNames,
				'templateid' => $templateids
			),
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => true
		));
		foreach ($screens as $screen) {
			$screenDbFields = array('name' => null, 'templateid' => null);
			if (!check_db_fields($screenDbFields, $screen)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for screen "%1$s".', $screen['name']));
			}

			foreach ($dbScreens as $dbScreen) {
				if ($dbScreen['name'] == $screen['name'] && bccomp($dbScreen['templateid'], $screen['templateid']) == 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Screen "%1$s" already exists.', $screen['name']));
				}
			}
		}
	}

	/**
	 * Validate input for update method.
	 *
	 * @param array $screens
	 */
	protected function validateUpdate(array $screens) {
		$updScreens = $this->get(array(
			'screenids' => zbx_objectValues($screens, 'screenid'),
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		$screens = $this->extendObjects($this->tableName(), $screens, array('name'));

		foreach ($screens as $screen) {
			if (!isset($screen['screenid'], $updScreens[$screen['screenid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}

			// check for "templateid", because it is not allowed
			if (array_key_exists('templateid', $screen)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot update "templateid" for screen "%1$s".', $screen['name']));
			}

			$screenDbFields = array('screenid' => null);
			if (!check_db_fields($screenDbFields, $screen)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for screen "%1$s".', $screen['name']));
			}

			$dbScreen = $updScreens[$screen['screenid']];

			if (isset($screen['name'])) {
				$existScreens = $this->get(array(
					'filter' => array(
						'name' => $screen['name'],
						'templateid' => $dbScreen['templateid']
					),
					'preservekeys' => true,
					'nopermissions' => true,
					'output' => array('screenid')
				));
				$existScreen = reset($existScreens);

				if ($existScreen && bccomp($existScreen['screenid'], $screen['screenid']) != 0) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Screen "%1$s" already exists.', $screen['name']));
				}
			}
		}
	}

	/**
	 * Validate input for delete method.
	 *
	 * @param array $screenIds
	 */
	protected function validateDelete(array $screenIds) {
		if (!$this->isWritable($screenIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Validate input for copy method.
	 *
	 * @param array $data
	 */
	protected function validateCopy(array $data) {
		$screenIds = $data['screenIds'];
		$templateIds = $data['templateIds'];

		$screens = $this->get(array(
			'screenids' => $screenIds,
			'output' => array('screenid', 'name', 'templateid'),
			'editable' => true,
			'preservekeys' => true
		));
		foreach ($screenIds as $screenId) {
			if (!isset($screens[$screenId])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		// check permissions on templates
		if (!API::Template()->isWritable($templateIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// check if screen with same name exists
		$existingScreens = $this->get(array(
			'filter' => array(
				'name' => zbx_objectValues($screens, 'name'),
				'templateid' => $templateIds
			),
			'output' => array('screenid', 'name', 'templateid'),
			'preservekeys' => true
		));
		foreach ($existingScreens as $existingScreen) {
			$template = DBfetch(DBselect('SELECT h.name FROM hosts h WHERE h.hostid='.$existingScreen['templateid']));
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Screen "%1$s" already exists on template "%2$2".',
				$existingScreen['name'], $template['name']));
		}
	}

	/**
	 * Check if user has read access to screens.
	 *
	 * @param array $ids
	 *
	 * @return bool
	 */
	public function isReadable(array $ids) {
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'screenids' => $ids,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	/**
	 * Check if user has write access to screens.
	 *
	 * @param array $ids
	 *
	 * @return bool
	 */
	public function isWritable(array $ids) {
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'screenids' => $ids,
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null) {
			// request the templateid field for inheritance to work
			$sqlParts = $this->addQuerySelect($this->fieldId('templateid'), $sqlParts);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result, array $templatesChain = array()) {
		$result = parent::addRelatedObjects($options, $result);

		$screenIds = array_keys($result);

		// hashing
		$options['hostids'] = zbx_toHash($options['hostids']);

		// adding screenitems
		if ($options['selectScreenItems'] !== null && $options['selectScreenItems'] != API_OUTPUT_COUNT) {
			$screenItems = API::getApi()->select('screens_items', array(
				'output' => $this->outputExtend('screens_items',
					array('screenid', 'screenitemid', 'resourcetype', 'resourceid'), $options['selectScreenItems']
				),
				'filter' => array('screenid' => $screenIds),
				'preservekeys' => true
			));
			$relationMap = $this->createRelationMap($screenItems, 'screenid', 'screenitemid');

			foreach ($screenItems as $screenItem) {
				switch ($screenItem['resourcetype']) {
					case SCREEN_RESOURCE_GRAPH:
						$graphids[$screenItem['resourceid']] = $screenItem['resourceid'];
						break;
					case SCREEN_RESOURCE_SIMPLE_GRAPH:
					case SCREEN_RESOURCE_PLAIN_TEXT:
						$itemids[$screenItem['resourceid']] = $screenItem['resourceid'];
						break;
				}
			}

			$screenItems = $this->unsetExtraFields($screenItems, array('screenid', 'screenitemid', 'resourceid', 'resourcetype'),
				$options['selectScreenItems']
			);
			$result = $relationMap->mapMany($result, $screenItems, 'screenitems');
		}

		// creating linkage of template -> real objects
		if (!is_null($options['selectScreenItems']) && !is_null($options['hostids'])) {
			// prepare graphs
			if (!empty($graphids)) {
				$tplGraphs = API::Graph()->get(array(
					'output' => array('graphid', 'name'),
					'graphids' => $graphids,
					'nopermissions' => true,
					'preservekeys' => true
				));

				$dbGraphs = API::Graph()->get(array(
					'output' => array('graphid', 'name'),
					'selectHosts' => array('hostid'),
					'hostids' => $options['hostids'],
					'filter' => array('name' => zbx_objectValues($tplGraphs, 'name')),
					'nopermissions' => true,
					'preservekeys' => true
				));
				$realGraphs = array();
				foreach ($dbGraphs as $graph) {
					$host = reset($graph['hosts']);
					unset($graph['hosts']);

					if (!isset($realGraphs[$host['hostid']])) {
						$realGraphs[$host['hostid']] = array();
					}
					$realGraphs[$host['hostid']][$graph['name']] = $graph;
				}
			}

			// prepare items
			if (!empty($itemids)) {
				$tplItems = API::Item()->get(array(
					'output' => array('itemid', 'key_', 'hostid'),
					'itemids' => $itemids,
					'nopermissions' => true,
					'preservekeys' => true
				));

				$dbItems = API::Item()->get(array(
					'output' => array('itemid', 'key_', 'hostid'),
					'hostids' => $options['hostids'],
					'filter' => array('key_' => zbx_objectValues($tplItems, 'key_')),
					'nopermissions' => true,
					'preservekeys' => true
				));

				$realItems = array();
				foreach ($dbItems as $item) {
					unset($item['hosts']);

					if (!isset($realItems[$item['hostid']])) {
						$realItems[$item['hostid']] = array();
					}
					$realItems[$item['hostid']][$item['key_']] = $item;
				}
			}
		}

		if (is_null($options['countOutput']) || (!is_null($options['countOutput']) && !is_null($options['groupCount']))) {
			// creating copies of templated screens (inheritance)
			// screenNum is needed due to we can't refer to screenid/hostid/templateid as they will repeat
			$screenNum = 0;
			$vrtResult = array();

			foreach ($result as $screen) {
				if (is_null($options['hostids']) || isset($options['hostids'][$screen['templateid']])) {
					$screenNum++;
					$vrtResult[$screenNum] = $screen;
					$vrtResult[$screenNum]['hostid'] = $screen['templateid'];
				}
				if (!isset($templatesChain[$screen['templateid']])) {
					continue;
				}

				foreach ($templatesChain[$screen['templateid']] as $hostid) {
					if (!isset($options['hostids'][$hostid])) {
						continue;
					}

					$screenNum++;
					$vrtResult[$screenNum] = $screen;
					$vrtResult[$screenNum]['hostid'] = $hostid;

					if (!isset($vrtResult[$screenNum]['screenitems'])) {
						continue;
					}

					foreach ($vrtResult[$screenNum]['screenitems'] as &$screenitem) {
						switch ($screenitem['resourcetype']) {
							case SCREEN_RESOURCE_GRAPH:
								$graphName = $tplGraphs[$screenitem['resourceid']]['name'];
								$screenitem['real_resourceid'] = $realGraphs[$hostid][$graphName]['graphid'];
								break;
							case SCREEN_RESOURCE_SIMPLE_GRAPH:
							case SCREEN_RESOURCE_PLAIN_TEXT:
								$itemKey = $tplItems[$screenitem['resourceid']]['key_'];
								$screenitem['real_resourceid'] = $realItems[$hostid][$itemKey]['itemid'];
								break;
						}
					}
					unset($screenitem);
				}
			}
			$result = array_values($vrtResult);
		}

		return $result;
	}
}
