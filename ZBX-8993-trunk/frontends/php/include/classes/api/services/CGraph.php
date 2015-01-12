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
 * Class containing methods for operations with graph.
 *
 * @package API
 */
class CGraph extends CGraphGeneral {

	protected $tableName = 'graphs';
	protected $tableAlias = 'g';
	protected $sortColumns = array('graphid', 'name', 'graphtype');

	public function __construct() {
		parent::__construct();

		$this->errorMessages = array_merge($this->errorMessages, array(
			self::ERROR_TEMPLATE_HOST_MIX =>
				_('Graph "%1$s" with templated items cannot contain items from other hosts.'),
			self::ERROR_MISSING_GRAPH_NAME => _('Missing "name" field for graph.'),
			self::ERROR_MISSING_GRAPH_ITEMS => _('Missing items for graph "%1$s".'),
			self::ERROR_MISSING_REQUIRED_VALUE => _('No "%1$s" given for graph.'),
			self::ERROR_TEMPLATED_ID => _('Cannot update "templateid" for graph "%1$s".'),
			self::ERROR_GRAPH_SUM => _('Cannot add more than one item with type "Graph sum" on graph "%1$s".')
		));
	}

	/**
	 * Get graph data.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sqlParts = array(
			'select'	=> array('graphs' => 'g.graphid'),
			'from'		=> array('graphs' => 'graphs g'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'graphids'					=> null,
			'itemids'					=> null,
			'templated'					=> null,
			'inherited'					=> null,
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
			'selectGroups'				=> null,
			'selectTemplates'			=> null,
			'selectHosts'				=> null,
			'selectItems'				=> null,
			'selectGraphItems'			=> null,
			'selectDiscoveryRule'		=> null,
			'selectGraphDiscovery'		=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// permission check
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$userGroups = getUserGroupsByUserId($userid);

			// check permissions by graph items
			$sqlParts['where'][] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM graphs_items gi,items i,hosts_groups hgg'.
					' LEFT JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', $userGroups).
				' WHERE g.graphid=gi.graphid'.
					' AND gi.itemid=i.itemid'.
					' AND i.hostid=hgg.hostid'.
				' GROUP BY i.hostid'.
				' HAVING MAX(permission)<'.zbx_dbstr($permission).
					' OR MIN(permission) IS NULL'.
					' OR MIN(permission)='.PERM_DENY.
				')';
			// check permissions by Y min item
			$sqlParts['where'][] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM items i,hosts_groups hgg'.
					' LEFT JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', $userGroups).
				' WHERE g.ymin_type='.GRAPH_YAXIS_TYPE_ITEM_VALUE.
					' AND g.ymin_itemid=i.itemid'.
					' AND i.hostid=hgg.hostid'.
				' GROUP BY i.hostid'.
				' HAVING MAX(permission)<'.zbx_dbstr($permission).
					' OR MIN(permission) IS NULL'.
					' OR MIN(permission)='.PERM_DENY.
				')';
			// check permissions by Y max item
			$sqlParts['where'][] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM items i,hosts_groups hgg'.
					' LEFT JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', $userGroups).
				' WHERE g.ymax_type='.GRAPH_YAXIS_TYPE_ITEM_VALUE.
					' AND g.ymax_itemid=i.itemid'.
					' AND i.hostid=hgg.hostid'.
				' GROUP BY i.hostid'.
				' HAVING MAX(permission)<'.zbx_dbstr($permission).
					' OR MIN(permission) IS NULL'.
					' OR MIN(permission)='.PERM_DENY.
				')';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where'][] = 'hg.hostid=i.hostid';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			if (!is_null($options['hostids'])) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else {
				$options['hostids'] = $options['templateids'];
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('i.hostid', $options['hostids']);
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['i'] = 'i.hostid';
			}
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['where'][] = dbConditionInt('g.graphid', $options['graphids']);
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where'][] = dbConditionInt('gi.itemid', $options['itemids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['gi'] = 'gi.itemid';
			}
		}

		// templated
		if (!is_null($options['templated'])) {
			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['ggi'] = 'g.graphid=gi.graphid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// inherited
		if (!is_null($options['inherited'])) {
			if ($options['inherited']) {
				$sqlParts['where'][] = 'g.templateid IS NOT NULL';
			}
			else {
				$sqlParts['where'][] = 'g.templateid IS NULL';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('graphs g', $options, $sqlParts);
		}

		// filter
		if (is_null($options['filter'])) {
			$options['filter'] = array();
		}

		if (is_array($options['filter'])) {
			if (!array_key_exists('flags', $options['filter'])) {
				$options['filter']['flags'] = array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED);
			}

			$this->dbFilter('graphs g', $options, $sqlParts);

			if (isset($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['graphs_items'] = 'graphs_items gi';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
				$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['host'] = dbConditionString('h.host', $options['filter']['host']);
			}

			if (isset($options['filter']['hostid'])) {
				zbx_value2array($options['filter']['hostid']);

				$sqlParts['from']['graphs_items'] = 'graphs_items gi';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
				$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
				$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['filter']['hostid']);
			}
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$dbRes = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($graph = DBfetch($dbRes)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $graph;
				}
				else {
					$result = $graph['rowscount'];
				}
			}
			else {
				$result[$graph['graphid']] = $graph;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if (isset($options['expandName'])) {
			$result = CMacrosResolverHelper::resolveGraphNameByIds($result);
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	protected function inherit($graph, $hostids = null) {
		$graphTemplates = API::Template()->get(array(
			'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
			'output' => array('templateid'),
			'nopermissions' => true
		));

		if (empty($graphTemplates)) {
			return true;
		}

		$graphTemplate = reset($graphTemplates);

		$chdHosts = API::Host()->get(array(
			'templateids' => $graphTemplate['templateid'],
			'output' => array('hostid', 'host'),
			'preservekeys' => true,
			'hostids' => $hostids,
			'nopermissions' => true,
			'templated_hosts' => true
		));

		$graph = $this->get(array(
			'graphids' => $graph['graphid'],
			'nopermissions' => true,
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		));
		$graph = reset($graph);

		foreach ($chdHosts as $chdHost) {
			$tmpGraph = $graph;
			$tmpGraph['templateid'] = $graph['graphid'];

			$tmpGraph['gitems'] = getSameGraphItemsForHost($tmpGraph['gitems'], $chdHost['hostid']);
			if (!$tmpGraph['gitems']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph "%1$s" cannot inherit. No required items on "%2$s".', $tmpGraph['name'], $chdHost['host']));
			}

			if ($tmpGraph['ymax_itemid'] > 0) {
				$ymaxItemid = getSameGraphItemsForHost(array(array('itemid' => $tmpGraph['ymax_itemid'])), $chdHost['hostid']);
				if (!$ymaxItemid) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph "%1$s" cannot inherit. No required items on "%2$s" (Ymax value item).', $tmpGraph['name'], $chdHost['host']));
				}
				$ymaxItemid = reset($ymaxItemid);
				$tmpGraph['ymax_itemid'] = $ymaxItemid['itemid'];
			}
			if ($tmpGraph['ymin_itemid'] > 0) {
				$yminItemid = getSameGraphItemsForHost(array(array('itemid' => $tmpGraph['ymin_itemid'])), $chdHost['hostid']);
				if (!$yminItemid) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph "%1$s" cannot inherit. No required items on "%2$s" (Ymin value item).', $tmpGraph['name'], $chdHost['host']));
				}
				$yminItemid = reset($yminItemid);
				$tmpGraph['ymin_itemid'] = $yminItemid['itemid'];
			}

			// check if templated graph exists
			$chdGraphs = $this->get(array(
				'filter' => array('templateid' => $tmpGraph['graphid'], 'flags' => array(ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_NORMAL)),
				'output' => API_OUTPUT_EXTEND,
				'selectGraphItems' => API_OUTPUT_EXTEND,
				'preservekeys' => true,
				'hostids' => $chdHost['hostid']
			));

			if ($chdGraph = reset($chdGraphs)) {
				if ($tmpGraph['name'] !== $chdGraph['name']) {
					$graphExists = $this->get(array(
						'output' => array('graphid'),
						'hostids' => $chdHost['hostid'],
						'filter' => array(
							'name' => $tmpGraph['name'],
							'flags' => null
						),
						'nopermissions' => true,
						'limit' => 1
					));
					if ($graphExists) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Graph "%1$s" already exists on "%2$s".',
							$tmpGraph['name'],
							$chdHost['host']
						));
					}
				}
				elseif ($chdGraph['flags'] != $tmpGraph['flags']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Graph with same name but other type exist.'));
				}

				$tmpGraph['graphid'] = $chdGraph['graphid'];
				$this->updateReal($tmpGraph, $chdGraph);
			}
			// check if graph with same name and items exists
			else {
				$chdGraph = $this->get(array(
					'filter' => array('name' => $tmpGraph['name'], 'flags' => null),
					'output' => API_OUTPUT_EXTEND,
					'selectGraphItems' => API_OUTPUT_EXTEND,
					'preservekeys' => true,
					'nopermissions' => true,
					'hostids' => $chdHost['hostid']
				));
				if ($chdGraph = reset($chdGraph)) {
					if ($chdGraph['templateid'] != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph "%1$s" already exists on "%2$s" (inherited from another template).', $tmpGraph['name'], $chdHost['host']));
					}
					elseif ($chdGraph['flags'] != $tmpGraph['flags']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Graph with same name but other type exist.'));
					}

					$chdGraphItemItems = API::Item()->get(array(
						'output' => array('itemid', 'key_', 'hostid'),
						'itemids' => zbx_objectValues($chdGraph['gitems'], 'itemid'),
						'preservekeys' => true
					));

					if (count($chdGraph['gitems']) == count($tmpGraph['gitems'])) {
						foreach ($tmpGraph['gitems'] as $gitem) {
							foreach ($chdGraph['gitems'] as $chdGraphItem) {
								$chdGraphItemItem = $chdGraphItemItems[$chdGraphItem['itemid']];
								if ($gitem['key_'] == $chdGraphItemItem['key_']
										&& bccomp($chdHost['hostid'], $chdGraphItemItem['hostid']) == 0) {
									continue 2;
								}
							}
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph "%1$s" already exists on "%2$s" (items are not identical).', $tmpGraph['name'], $chdHost['host']));
						}

						$tmpGraph['graphid'] = $chdGraph['graphid'];
						$this->updateReal($tmpGraph, $chdGraph);
					}
					else {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph "%1$s" already exists on "%2$s" (items are not identical).', $tmpGraph['name'], $chdHost['host']));
					}
				}
				else {
					$graphid = $this->createReal($tmpGraph);
					$tmpGraph['graphid'] = $graphid;
				}
			}
			$this->inherit($tmpGraph);
		}
	}

	/**
	 * Inherit template graphs from template to host.
	 *
	 * @param array $data
	 *
	 * @return bool
	 */
	public function syncTemplates($data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$dbLinks = DBSelect(
			'SELECT ht.hostid,ht.templateid'.
			' FROM hosts_templates ht'.
			' WHERE '.dbConditionInt('ht.hostid', $data['hostids']).
				' AND '.dbConditionInt('ht.templateid', $data['templateids'])
		);
		$linkage = array();
		while ($link = DBfetch($dbLinks)) {
			if (!isset($linkage[$link['templateid']])) {
				$linkage[$link['templateid']] = array();
			}
			$linkage[$link['templateid']][$link['hostid']] = 1;
		}

		$graphs = $this->get(array(
			'hostids' => $data['templateids'],
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND,
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'selectHosts' => array('hostid')
		));

		foreach ($graphs as $graph) {
			foreach ($data['hostids'] as $hostid) {
				if (isset($linkage[$graph['hosts'][0]['hostid']][$hostid])) {
					$this->inherit($graph, $hostid);
				}
			}
		}

		return true;
	}

	/**
	 * Delete graphs.
	 *
	 * @param array $graphids
	 * @param bool  $nopermissions
	 *
	 * @return array
	 */
	public function delete(array $graphids, $nopermissions = false) {
		if (empty($graphids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$delGraphs = $this->get(array(
			'output' => API_OUTPUT_EXTEND,
			'graphids' => $graphids,
			'editable' => true,
			'preservekeys' => true,
			'selectHosts' => array('name')
		));

		if (!$nopermissions) {
			foreach ($graphids as $graphid) {
				if (!isset($delGraphs[$graphid])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
				}

				$delGraph = $delGraphs[$graphid];

				if ($delGraph['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('Cannot delete templated graphs.'));
				}

				if ($delGraph['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Cannot delete discovered graph "%1$s".', $delGraph['name']
					));
				}
			}
		}

		$parentGraphids = $graphids;
		do {
			$dbGraphs = DBselect('SELECT g.graphid FROM graphs g WHERE '.dbConditionInt('g.templateid', $parentGraphids));
			$parentGraphids = array();
			while ($dbGraph = DBfetch($dbGraphs)) {
				$parentGraphids[] = $dbGraph['graphid'];
				$graphids[] = $dbGraph['graphid'];
			}
		} while (!empty($parentGraphids));

		$graphids = array_unique($graphids);

		DB::delete('screens_items', array(
			'resourceid' => $graphids,
			'resourcetype' => SCREEN_RESOURCE_GRAPH
		));

		DB::delete('profiles', array(
			'idx' => 'web.favorite.graphids',
			'source' => 'graphid',
			'value_id' => $graphids
		));

		DB::delete('graphs', array(
			'graphid' => $graphids
		));

		foreach ($delGraphs as $graph) {
			$host = reset($graph['hosts']);
			info(_s('Deleted: Graph "%1$s" on "%2$s".', $graph['name'], $host['name']));
		}

		return array('graphids' => $graphids);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$graphids = array_keys($result);

		// adding Items
		if ($options['selectItems'] !== null && $options['selectItems'] !== API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'graphid', 'itemid', 'graphs_items');
			$items = API::Item()->get(array(
				'output' => $options['selectItems'],
				'itemids' => $relationMap->getRelatedIds(),
				'webitems' => true,
				'nopermissions' => true,
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $items, 'items');
		}

		// adding discoveryRule
		if ($options['selectDiscoveryRule'] !== null) {
			$dbRules = DBselect(
				'SELECT id.parent_itemid,gd.graphid'.
					' FROM graph_discovery gd,item_discovery id,graphs_items gi'.
					' WHERE '.dbConditionInt('gd.graphid', $graphids).
					' AND gd.parent_graphid=gi.graphid'.
					' AND gi.itemid=id.itemid'
			);
			$relationMap = new CRelationMap();
			while ($relation = DBfetch($dbRules)) {
				$relationMap->addRelation($relation['graphid'], $relation['parent_itemid']);
			}

			$discoveryRules = API::DiscoveryRule()->get(array(
				'output' => $options['selectDiscoveryRule'],
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			));
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		// adding graph discovery
		if ($options['selectGraphDiscovery'] !== null) {
			$graphDiscoveries = API::getApiService()->select('graph_discovery', array(
				'output' => $this->outputExtend($options['selectGraphDiscovery'], array('graphid')),
				'filter' => array('graphid' => array_keys($result)),
				'preservekeys' => true
			));
			$relationMap = $this->createRelationMap($graphDiscoveries, 'graphid', 'graphid');

			$graphDiscoveries = $this->unsetExtraFields($graphDiscoveries, array('graphid'),
				$options['selectGraphDiscovery']
			);
			$result = $relationMap->mapOne($result, $graphDiscoveries, 'graphDiscovery');
		}

		return $result;
	}

	/**
	 * Validate create.
	 *
	 * @param array $graphs
	 */
	protected function validateCreate(array $graphs) {
		$itemIds = $this->validateItemsCreate($graphs);
		$this->validateItems($itemIds, $graphs);

		parent::validateCreate($graphs);
	}

	/**
	 * Validate update.
	 *
	 * @param array $graphs
	 * @param array $dbGraphs
	 */
	protected function validateUpdate(array $graphs, array $dbGraphs) {
		// check for "itemid" when updating graph with only "gitemid" passed
		foreach ($graphs as &$graph) {
			if (isset($graph['gitems'])) {
				foreach ($graph['gitems'] as &$gitem) {
					if (isset($gitem['gitemid']) && !isset($gitem['itemid'])) {
						$dbGitems = zbx_toHash($dbGraphs[$graph['graphid']]['gitems'], 'gitemid');
						$gitem['itemid'] = $dbGitems[$gitem['gitemid']]['itemid'];
					}
				}
				unset($gitem);
			}
		}
		unset($graph);

		$itemIds = $this->validateItemsUpdate($graphs);
		$this->validateItems($itemIds, $graphs);

		parent::validateUpdate($graphs, $dbGraphs);
	}

	/**
	 * Validates items.
	 *
	 * @param array $itemIds
	 * @param array $graphs
	 */
	protected function validateItems(array $itemIds, array $graphs) {
		$dbItems = API::Item()->get(array(
			'output' => array('name', 'value_type'),
			'itemids' => $itemIds,
			'webitems' => true,
			'editable' => true,
			'preservekeys' => true
		));

		// check if items exist and user has permission to access those items
		foreach ($itemIds as $itemId) {
			if (!isset($dbItems[$itemId])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$allowedValueTypes = array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64);

		// get value type and name for these items
		foreach ($graphs as $graph) {
			// graph items
			foreach ($graph['gitems'] as $graphItem) {
				$item = $dbItems[$graphItem['itemid']];

				if (!in_array($item['value_type'], $allowedValueTypes)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Cannot add a non-numeric item "%1$s" to graph "%2$s".',
						$item['name'],
						$graph['name']
					));
				}
			}

			// Y axis min
			if (isset($graph['ymin_itemid']) && $graph['ymin_itemid']
					&& isset($graph['ymin_type']) && $graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				$item = $dbItems[$graph['ymin_itemid']];

				if (!in_array($item['value_type'], $allowedValueTypes)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Cannot add a non-numeric item "%1$s" to graph "%2$s".',
						$item['name'],
						$graph['name']
					));
				}
			}

			// Y axis max
			if (isset($graph['ymax_itemid']) && $graph['ymax_itemid']
					&& isset($graph['ymax_type']) && $graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				$item = $dbItems[$graph['ymax_itemid']];

				if (!in_array($item['value_type'], $allowedValueTypes)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Cannot add a non-numeric item "%1$s" to graph "%2$s".',
						$item['name'],
						$graph['name']
					));
				}
			}
		}
	}
}
