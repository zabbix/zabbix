<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
 * Class containing methods for operations with template screens.
 *
 * @package API
 */
class CTemplateScreen extends CScreen {

	protected $tableName = 'screens';
	protected $tableAlias = 's';
	protected $sortColumns = array('screenid', 'name');

	/**
	 * Get screen data.
	 *
	 * @param array  $options
	 * @param array  $options['nodeids']		Node IDs
	 * @param bool   $options['with_items']		only with items
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
		$userid = self::$userData['userid'];

		$sqlParts = array(
			'select'	=> array('screens' => 's.screenid,s.templateid'),
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

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
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

		if ($options['countOutput'] !== null && $options['groupCount'] === null) {
			return $result;
		}

		$screenIds = array_keys($result);

		// adding screenitems
		if ($options['selectScreenItems'] !== null && $options['selectScreenItems'] != API_OUTPUT_COUNT) {
			$screenItems = API::getApi()->select('screens_items', array(
				'output' => $this->outputExtend('screens_items',
					array('screenid', 'screenitemid', 'resourcetype', 'resourceid'), $options['selectScreenItems']
				),
				'filter' => array('screenid' => $screenIds),
				'preservekeys' => true,
				'nodeids' => get_current_nodeid(true)
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

			$screenItems = $this->unsetExtraFields($screenItems,
				array('screenid', 'screenitemid', 'resourceid', 'resourcetype'),
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

		// hashing
		$options['hostids'] = zbx_toHash($options['hostids']);
		if (is_null($options['countOutput'])
				|| (!is_null($options['countOutput']) && !is_null($options['groupCount']))) {
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

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
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
				'SELECT src.itemid AS srcid,dest.itemid as destid'.
				' FROM items dest,items src'.
				' WHERE dest.key_=src.key_'.
					' AND dest.hostid='.zbx_dbstr($templateId).
					' AND '.dbConditionInt('src.itemid', $resourceItemIds)
			);
			while ($dbItem = DBfetch($dbItems)) {
				$resourceItemsMap[$dbItem['srcid']] = $dbItem['destid'];
			}

			// get same graphs on destination template
			$resourceGraphsMap = array();
			$dbItems = DBselect(
				'SELECT src.graphid AS srcid,dest.graphid as destid'.
				' FROM graphs dest,graphs src,graphs_items destgi,items desti'.
				' WHERE dest.name=src.name'.
					' AND destgi.graphid=dest.graphid'.
					' AND destgi.itemid=desti.itemid'.
					' AND desti.hostid='.zbx_dbstr($templateId).
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

								self::exception(
									ZBX_API_ERROR_PARAMETERS,
									_s('Graph "%1$s" does not exist on template "%2$s".', $graph['name'], $template['name'])
								);
							}

							$screenItem['resourceid'] = $resourceGraphsMap[$rid];
							break;

						default:
							if ($rid && !isset($resourceItemsMap[$rid])) {
								$item = DBfetch(DBselect('SELECT i.name FROM items i WHERE i.itemid='.zbx_dbstr($rid)));
								$template = DBfetch(DBselect('SELECT h.name FROM hosts h WHERE h.hostid='.zbx_dbstr($templateId)));

								self::exception(
									ZBX_API_ERROR_PARAMETERS,
									_s('Item "%1$s" does not exist on template "%2$s".', $item['name'], $template['name'])
								);
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
	 * Validates the input parameters for the create() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $screens
	 */
	protected function validateCreate(array $screens) {
		$screenDbFields = array('name' => null, 'templateid' => null);

		foreach ($screens as $screen) {
			if (!check_db_fields($screenDbFields, $screen)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect input parameters.'));
			}
		}

		$templateIds = zbx_objectValues($screens, 'templateid');

		$dbScreens = $this->get(array(
			'filter' => array(
				'name' => zbx_objectValues($screens, 'name'),
				'templateid' => $templateIds
			),
			'output' => array('name', 'templateid'),
			'nopermissions' => true
		));

		$dbTemplates = API::Template()->get(array(
			'templateids' => $templateIds,
			'output' => array('name', 'templateid'),
			'editable' => true,
			'preservekeys' => true
		));

		foreach ($templateIds as $templateId) {
			if (!isset($dbTemplates[$templateId])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		foreach ($screens as $screen) {
			foreach ($dbScreens as $dbScreen) {
				if ($dbScreen['name'] == $screen['name'] && bccomp($dbScreen['templateid'], $screen['templateid']) == 0) {
					self::exception(
						ZBX_API_ERROR_PARAMETERS,
						_s('Template screen "%1$s" already exists.', $screen['name'])
					);
				}
			}
		}
	}

	/**
	 * Update template screens.
	 *
	 * @param array $screens
	 *
	 * @return array
	 */
	public function update(array $screens) {
		$screens = zbx_toArray($screens);

		// check hostids before doing anything
		$this->checkObjectIds($screens, 'screenid',
			_('No "%1$s" given for template screen.'),
			_('Empty screen ID for template screen.'),
			_('Incorrect template screen ID.')
		);

		$dbScreens = $this->get(array(
			'output' => array('screenid', 'hsize', 'vsize', 'templateid'),
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

	protected function validateUpdate(array $screens, array $dbScreens) {
		foreach ($screens as $screen) {
			if (!isset($dbScreens[$screen['screenid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$screens = $this->extendObjects($this->tableName(), $screens, array('name'));

		foreach ($screens as $screen) {
			// "templateid" is not allowed
			if (array_key_exists('templateid', $screen)) {
				self::exception(
					ZBX_API_ERROR_PARAMETERS,
					_s('Cannot update "templateid" for template screen "%1$s".', $screen['name'])
				);
			}

			if (isset($screen['name'])) {
				$dbScreenExist = $this->get(array(
					'filter' => array(
						'name' => $screen['name'],
						'templateid' => $dbScreens[$screen['screenid']]['templateid']
					),
					'nopermissions' => true,
					'output' => array('screenid')
				));
				$dbScreenExist = reset($dbScreenExist);

				if ($dbScreenExist && bccomp($dbScreenExist['screenid'], $screen['screenid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Template screen "%1$s" already exists.', $screen['name']));
				}
			}
		}
	}

	/**
	 * Validate input for copy method.
	 *
	 * @param array $data['screenIds']
	 * @param array $data['templateIds']
	 */
	protected function validateCopy(array $data) {
		$screenIds = $data['screenIds'];
		$templateIds = $data['templateIds'];

		$dbScreens = $this->get(array(
			'screenids' => $screenIds,
			'output' => array('screenid', 'name', 'templateid'),
			'editable' => true,
			'preservekeys' => true
		));

		foreach ($screenIds as $screenId) {
			if (!isset($dbScreens[$screenId])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		// check permissions on templates
		if (!API::Template()->isWritable($templateIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// check if screen with same name exists
		$dbExistingScreens = $this->get(array(
			'filter' => array(
				'name' => zbx_objectValues($dbScreens, 'name'),
				'templateid' => $templateIds
			),
			'output' => array('name', 'templateid'),
			'preservekeys' => true
		));

		foreach ($dbExistingScreens as $dbExistingScreen) {
			$dbTemplate = DBfetch(DBselect('SELECT h.name FROM hosts h WHERE h.hostid='.$dbExistingScreen['templateid']));

			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s('Screen "%1$s" already exists on template "%2$2".', $dbExistingScreen['name'], $dbTemplate['name'])
			);
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null) {
			// request the templateid field for inheritance to work
			$sqlParts = $this->addQuerySelect($this->fieldId('templateid'), $sqlParts);
		}

		return $sqlParts;
	}
}
