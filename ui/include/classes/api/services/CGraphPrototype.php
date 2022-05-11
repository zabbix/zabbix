<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 */
class CGraphPrototype extends CGraphGeneral {

	protected $tableName = 'graphs';
	protected $tableAlias = 'g';
	protected $sortColumns = ['graphid', 'name', 'graphtype', 'discover'];

	public function __construct() {
		parent::__construct();

		$this->errorMessages = array_merge($this->errorMessages, [
			self::ERROR_TEMPLATE_HOST_MIX =>
				_('Graph prototype "%1$s" with templated items cannot contain items from other hosts.'),
			self::ERROR_MISSING_GRAPH_NAME => _('Missing "name" field for graph prototype.'),
			self::ERROR_MISSING_GRAPH_ITEMS => _('Missing items for graph prototype "%1$s".'),
			self::ERROR_MISSING_REQUIRED_VALUE => _('No "%1$s" given for graph prototype.'),
			self::ERROR_GRAPH_SUM => _('Cannot add more than one item with type "Graph sum" on graph prototype "%1$s".')
		]);
	}

	/**
	 * Get GraphPrototype data
	 *
	 * @param array $options
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['graphs' => 'g.graphid'],
			'from'		=> ['graphs' => 'graphs g'],
			'where'		=> ['g.flags='.ZBX_FLAG_DISCOVERY_PROTOTYPE],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'graphids'					=> null,
			'itemids'					=> null,
			'discoveryids'				=> null,
			'templated'					=> null,
			'inherited'					=> null,
			'editable'					=> false,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectGroups'				=> null,
			'selectTemplates'			=> null,
			'selectHosts'				=> null,
			'selectItems'				=> null,
			'selectGraphItems'			=> null,
			'selectDiscoveryRule'		=> null,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

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

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where'][] = 'hg.hostid=i.hostid';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';

			if ($options['groupCount']) {
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
			$sqlParts['where'][] = dbConditionInt('i.flags', (array) ZBX_FLAG_DISCOVERY_PROTOTYPE);
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';

			if ($options['groupCount']) {
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

			if ($options['groupCount']) {
				$sqlParts['group']['gi'] = 'gi.itemid';
			}
		}

		// discoveryids
		if (!is_null($options['discoveryids'])) {
			zbx_value2array($options['discoveryids']);

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['item_discovery'] = 'item_discovery id';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where']['giid'] = 'gi.itemid=id.itemid';
			$sqlParts['where'][] = dbConditionInt('id.parent_itemid', $options['discoveryids']);

			if ($options['groupCount']) {
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

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$dbRes = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($graph = DBfetch($dbRes)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
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

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Delete GraphPrototype.
	 *
	 * @param array $graphids
	 *
	 * @return array
	 */
	public function delete(array $graphids) {
		$this->validateDelete($graphids, $db_graphs);

		CGraphPrototypeManager::delete($graphids);

		$this->addAuditBulk(CAudit::ACTION_DELETE, CAudit::RESOURCE_GRAPH_PROTOTYPE, $db_graphs);

		return ['graphids' => $graphids];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array $graphids   [IN/OUT]
	 * @param array $db_graphs  [OUT]
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array &$graphids, array &$db_graphs = null) {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $graphids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_graphs = $this->get([
			'output' => ['graphid', 'name', 'templateid'],
			'graphids' => $graphids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($graphids as $graphid) {
			if (!array_key_exists($graphid, $db_graphs)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			if ($db_graphs[$graphid]['templateid'] != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete templated graph prototype.'));
			}
		}
	}

	protected function createReal(array &$graphs) {
		foreach ($graphs as &$graph) {
			// mark the graph as a graph prototype
			$graph['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;
		}
		unset($graph);

		return parent::createReal($graphs);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$graphids = array_keys($result);

		// adding Items
		if ($options['selectItems'] !== null && $options['selectItems'] !== API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'graphid', 'itemid', 'graphs_items');
			$items = API::Item()->get([
				'output' => $options['selectItems'],
				'itemids' => $relationMap->getRelatedIds(),
				'webitems' => true,
				'nopermissions' => true,
				'preservekeys' => true,
				'filter' => ['flags' => null]
			]);
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

			$discoveryRules = API::DiscoveryRule()->get([
				'output' => $options['selectDiscoveryRule'],
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
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
	protected function validateCreate(array &$graphs) {
		$itemIds = $this->validateItemsCreate($graphs);

		$allowedItems = API::Item()->get([
			'itemids' => $itemIds,
			'webitems' => true,
			'editable' => true,
			'output' => ['name', 'value_type', 'flags'],
			'selectItemDiscovery' => ['parent_itemid'],
			'preservekeys' => true,
			'filter' => [
				'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED]
			]
		]);

		foreach ($itemIds as $itemid) {
			if (!isset($allowedItems[$itemid])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$this->checkDiscoveryRuleCount($graphs, $allowedItems);

		parent::validateCreate($graphs);

		$allowedValueTypes = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64];

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

		$allowedItems = API::Item()->get([
			'itemids' => $itemIds,
			'webitems' => true,
			'editable' => true,
			'output' => ['itemid', 'name', 'value_type', 'flags'],
			'selectItemDiscovery' => ['parent_itemid'],
			'preservekeys' => true,
			'filter' => [
				'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED]
			]
		]);

		foreach ($itemIds as $itemId) {
			if (!isset($allowedItems[$itemId])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$this->checkDiscoveryRuleCount($graphs, $allowedItems);

		parent::validateUpdate($graphs, $dbGraphs);

		$allowedValueTypes = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64];

		foreach ($allowedItems as $item) {
			if (!in_array($item['value_type'], $allowedValueTypes)) {
				foreach ($dbGraphs as $dbGraph) {
					$itemIdsInGraphItems = zbx_objectValues($dbGraph['gitems'], 'itemid');
					if (in_array($item['itemid'], $itemIdsInGraphItems)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Cannot add a non-numeric item "%1$s" to graph prototype "%2$s".',
							$item['name'], $dbGraph['name']
						));
					}
				}
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
				$itemDiscoveryIds = [];

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
