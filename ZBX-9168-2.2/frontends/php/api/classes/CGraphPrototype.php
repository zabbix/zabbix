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
 * Class containing methods for operations with graph prototypes.
 *
 * @package API
 */
class CGraphPrototype extends CGraphGeneral {

	protected $tableName = 'graphs';
	protected $tableAlias = 'g';
	protected $sortColumns = array('graphid', 'name', 'graphtype');

	public function __construct() {
		parent::__construct();

		$this->errorMessages = array_merge($this->errorMessages, array(
			self::ERROR_TEMPLATE_HOST_MIX =>
				_('Graph prototype "%1$s" with templated items cannot contain items from other hosts.'),
			self::ERROR_MISSING_GRAPH_NAME => _('Missing "name" field for graph prototype.'),
			self::ERROR_MISSING_GRAPH_ITEMS => _('Missing items for graph prototype "%1$s".'),
			self::ERROR_MISSING_REQUIRED_VALUE => _('No "%1$s" given for graph prototype.'),
			self::ERROR_TEMPLATED_ID => _('Cannot update "templateid" for graph prototype "%1$s".'),
			self::ERROR_GRAPH_SUM => _('Cannot add more than one item with type "Graph sum" on graph prototype "%1$s".')
		));
	}

	/**
	 * Get GraphPrototype data
	 *
	 * @param array $options
	 * @return array
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sqlParts = array(
			'select'	=> array('graphs' => 'g.graphid'),
			'from'		=> array('graphs' => 'graphs g'),
			'where'		=> array('g.flags='.ZBX_FLAG_DISCOVERY_PROTOTYPE),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'graphids'					=> null,
			'itemids'					=> null,
			'discoveryids'				=> null,
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
			'output'					=> API_OUTPUT_REFER,
			'selectGroups'				=> null,
			'selectTemplates'			=> null,
			'selectHosts'				=> null,
			'selectItems'				=> null,
			'selectGraphItems'			=> null,
			'selectDiscoveryRule'		=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
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
				' HAVING MAX(permission)<'.$permission.
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
				' HAVING MAX(permission)<'.$permission.
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
				' HAVING MAX(permission)<'.$permission.
					' OR MIN(permission) IS NULL'.
					' OR MIN(permission)='.PERM_DENY.
				')';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['select']['groupid'] = 'hg.groupid';
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

			$sqlParts['select']['hostid'] = 'i.hostid';
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

			$sqlParts['select']['itemid'] = 'gi.itemid';
			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where'][] = dbConditionInt('gi.itemid', $options['itemids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['gi'] = 'gi.itemid';
			}
		}

		// discoveryids
		if (!is_null($options['discoveryids'])) {
			zbx_value2array($options['discoveryids']);

			$sqlParts['select']['itemid'] = 'id.parent_itemid';
			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['item_discovery'] = 'item_discovery id';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where']['giid'] = 'gi.itemid=id.itemid';
			$sqlParts['where'][] = dbConditionInt('id.parent_itemid', $options['discoveryids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['id'] = 'id.parent_itemid';
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
		if (is_array($options['filter'])) {
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

		$graphids = array();

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
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
				$graphids[$graph['graphid']] = $graph['graphid'];

				if (!isset($result[$graph['graphid']])) {
					$result[$graph['graphid']]= array();
				}

				// hostids
				if (isset($graph['hostid']) && is_null($options['selectHosts'])) {
					if (!isset($result[$graph['graphid']]['hosts'])) {
						$result[$graph['graphid']]['hosts'] = array();
					}
					$result[$graph['graphid']]['hosts'][] = array('hostid' => $graph['hostid']);
					unset($graph['hostid']);
				}

				// itemids
				if (isset($graph['itemid']) && is_null($options['selectItems'])) {
					if (!isset($result[$graph['graphid']]['items'])) {
						$result[$graph['graphid']]['items'] = array();
					}
					$result[$graph['graphid']]['items'][] = array('itemid' => $graph['itemid']);
					unset($graph['itemid']);
				}

				$result[$graph['graphid']] += $graph;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
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
			'filter' => array('flags' => null),
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		));
		$graph = reset($graph);

		foreach ($chdHosts as $chdHost) {
			$tmpGraph = $graph;
			$tmpGraph['templateid'] = $graph['graphid'];

			if (!$tmpGraph['gitems'] = getSameGraphItemsForHost($tmpGraph['gitems'], $chdHost['hostid'])) {
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
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Graph "%1$s" cannot inherit. No required items on "%2$s" (Ymin value item).',
							$tmpGraph['name'],
							$chdHost['host']
					));
				}
				$yminItemid = reset($yminItemid);
				$tmpGraph['ymin_itemid'] = $yminItemid['itemid'];
			}

			// check if templated graph exists
			$chdGraphs = $this->get(array(
				'filter' => array(
					'templateid' => $tmpGraph['graphid'],
					'flags' => array(ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_NORMAL)
				),
				'output' => API_OUTPUT_EXTEND,
				'selectGraphItems' => API_OUTPUT_EXTEND,
				'preservekeys' => true,
				'hostids' => $chdHost['hostid']
			));

			if ($chdGraph = reset($chdGraphs)) {
				if (zbx_strtolower($tmpGraph['name']) != zbx_strtolower($chdGraph['name'])
						&& $this->exists(array('name' => $tmpGraph['name'], 'hostids' => $chdHost['hostid']))) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Graph "%1$s" already exists on "%2$s".', $tmpGraph['name'], $chdHost['host'])
					);
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

					$chdGraphItems = API::GraphItem()->get(array(
						'graphids' => $chdGraph['graphid'],
						'output' => API_OUTPUT_EXTEND,
						'preservekeys' => true,
						'expandData' => true,
						'nopermissions' => true
					));

					if (count($chdGraphItems) == count($tmpGraph['gitems'])) {
						foreach ($tmpGraph['gitems'] as $gitem) {
							foreach ($chdGraphItems as $chdItem) {
								if ($gitem['key_'] == $chdItem['key_'] && bccomp($chdHost['hostid'], $chdItem['hostid']) == 0) {
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
			'filter' => array('flags' => null)
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
	 * Delete GraphPrototype.
	 *
	 * @param int|string|array $graphids
	 * @param bool             $nopermissions
	 *
	 * @return array
	 */
	public function delete($graphids, $nopermissions = false) {
		if (empty($graphids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$graphids = zbx_toArray($graphids);
		$delGraphPrototypeIds = $graphids;

		$delGraphs = $this->get(array(
			'graphids' => $graphids,
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		if (!$nopermissions) {
			foreach ($graphids as $graphid) {
				if (!isset($delGraphs[$graphid])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
				}
				if ($delGraphs[$graphid]['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('Cannot delete templated graphs.'));
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
		$createdGraphs = array();

		$dbGraphs = DBselect('SELECT gd.graphid FROM graph_discovery gd WHERE '.dbConditionInt('gd.parent_graphid', $graphids));
		while ($graph = DBfetch($dbGraphs)) {
			$createdGraphs[$graph['graphid']] = $graph['graphid'];
		}
		if (!empty($createdGraphs)) {
			$result = API::Graph()->delete($createdGraphs, true);
			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete graphs created by low level discovery.'));
			}
		}

		DB::delete('graphs', array('graphid' => $graphids));

		foreach ($delGraphs as $graph) {
			info(_s('Graph prototype "%s" deleted.', $graph['name']));
		}

		return array('graphids' => $delGraphPrototypeIds);
	}

	protected function createReal($graph) {
		// mark the graph as a graph prototype
		$graph['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;

		return parent::createReal($graph);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$graphids = array_keys($result);

		// adding Items
		if ($options['selectItems'] !== null && $options['selectItems'] !== API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'graphid', 'itemid', 'graphs_items');
			$items = API::Item()->get(array(
				'nodeids' => $options['nodeids'],
				'output' => $options['selectItems'],
				'itemids' => $relationMap->getRelatedIds(),
				'webitems' => true,
				'nopermissions' => true,
				'preservekeys' => true,
				'filter' => array('flags' => null)
			));
			$result = $relationMap->mapMany($result, $items, 'items');
		}

		// adding discoveryRule
		if (!is_null($options['selectDiscoveryRule'])) {
			$dbRules = DBselect(
				'SELECT id.parent_itemid,gi.graphid'.
					' FROM item_discovery id,graphs_items gi'.
					' WHERE '.dbConditionInt('gi.graphid', $graphids).
						' AND gi.itemid=id.itemid'
			);
			$relationMap = new CRelationMap();
			while ($relation = DBfetch($dbRules)) {
				$relationMap->addRelation($relation['graphid'], $relation['parent_itemid']);
			}

			$discoveryRules = API::DiscoveryRule()->get(array(
				'output' => $options['selectDiscoveryRule'],
				'nodeids' => $options['nodeids'],
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			));
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		return $result;
	}

	/**
	 * Validate graph prototype specific data on Create method.
	 * Get allowed item ID's, check permissions, check if items have at least one prototype, do all general validation,
	 * and check for numeric item types.
	 *
	 * @param array $graphs
	 */
	protected function validateCreate(array $graphs) {
		$itemIds = $this->validateItemsCreate($graphs);

		$allowedItems = API::Item()->get(array(
			'nodeids' => get_current_nodeid(true),
			'itemids' => $itemIds,
			'webitems' => true,
			'editable' => true,
			'output' => array('name', 'value_type', 'flags'),
			'selectItemDiscovery' => array('parent_itemid'),
			'preservekeys' => true,
			'filter' => array(
				'flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED)
			)
		));

		foreach ($itemIds as $itemid) {
			if (!isset($allowedItems[$itemid])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$this->checkDiscoveryRuleCount($graphs, $allowedItems);

		parent::validateCreate($graphs);

		$allowedValueTypes = array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64);

		foreach ($graphs as $graph) {
			foreach ($graph['gitems'] as $gitem) {
				if (!in_array($allowedItems[$gitem['itemid']]['value_type'], $allowedValueTypes)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Cannot add a non-numeric item "%1$s" to graph prototype "%2$s".',
						$allowedItems[$gitem['itemid']]['name'],
						$graph['name']
					));
				}
			}
		}
	}

	/**
	 * Validate graph prototype specific data on Update method.
	 * Get allowed item ID's, check permissions, check if items have at least one prototype, do all general validation,
	 * and check for numeric item types.
	 *
	 * @param array $graphs
	 * @param array $dbGraphs
	 */
	protected function validateUpdate(array $graphs, array $dbGraphs) {
		// check for "itemid" when updating graph prototype with only "gitemid" passed
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

		$allowedItems = API::Item()->get(array(
			'nodeids' => get_current_nodeid(true),
			'itemids' => $itemIds,
			'webitems' => true,
			'editable' => true,
			'output' => array('name', 'value_type', 'flags'),
			'selectItemDiscovery' => array('parent_itemid'),
			'preservekeys' => true,
			'filter' => array(
				'flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED)
			)
		));

		foreach ($itemIds as $itemId) {
			if (!isset($allowedItems[$itemId])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$this->checkDiscoveryRuleCount($graphs, $allowedItems);

		parent::validateUpdate($graphs, $dbGraphs);

		$allowedValueTypes = array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64);

		foreach ($allowedItems as $item) {
			if (!in_array($item['value_type'], $allowedValueTypes)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot add a non-numeric item "%1$s" to graph prototype "%2$s".', $item['name'], $graph['name'])
				);
			}
		}
	}

	/**
	 * Check if graph prototype has at least one item prototype and belongs to one discovery rule.
	 *
	 * @throws APIException if graph prototype has no item prototype or items belong to multiple discovery rules.
	 *
	 * @param array  $graphs				array of graphs
	 * @param array  $graphs['gitems']		array of graphs items
	 * @param string $graphs['name']		graph name
	 * @param array  $items					array of existing graph items and ones that user has permission to access
	 */
	protected function checkDiscoveryRuleCount(array $graphs, array $items) {
		foreach ($graphs as $graph) {
			// for update method we will skip this step, if no items are set
			if (isset($graph['gitems'])) {
				$itemDiscoveryIds = array();

				foreach ($graph['gitems'] as $gitem) {
					if ($items[$gitem['itemid']]['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
						$itemDiscoveryIds[$items[$gitem['itemid']]['itemDiscovery']['parent_itemid']] = true;
					}
				}

				if (count($itemDiscoveryIds) > 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Graph prototype "%1$s" contains item prototypes from multiple discovery rules.',
						$graph['name']
					));
				}
				elseif (!$itemDiscoveryIds) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Graph prototype "%1$s" must have at least one item prototype.',
						$graph['name']
					));
				}
			}
		}
	}
}
