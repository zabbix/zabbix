<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Class containing methods for operations with graph prototypes.
 */
class CGraphPrototype extends CGraphGeneral {

	protected $tableName = 'graphs';
	protected $tableAlias = 'g';
	protected $sortColumns = ['graphid', 'name', 'graphtype', 'discover'];

	protected const FLAGS = ZBX_FLAG_DISCOVERY_PROTOTYPE;

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
			'selectHostGroups'			=> null,
			'selectTemplateGroups'		=> null,
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
			if (self::$userData['ugsetid'] == 0) {
				return $options['countOutput'] ? '0' : [];
			}

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from'][] = 'host_hgset hh';
			$sqlParts['from'][] = 'permission p';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where'][] = 'i.hostid=hh.hostid';
			$sqlParts['where'][] = 'hh.hgsetid=p.hgsetid';
			$sqlParts['where'][] = 'p.ugsetid='.self::$userData['ugsetid'];

			if ($options['editable']) {
				$sqlParts['where'][] = 'p.permission='.PERM_READ_WRITE;
			}

			$sqlParts['where'][] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM graphs_items gi1'.
				' JOIN items i1 ON gi1.itemid=i1.itemid'.
				' JOIN host_hgset hh1 ON i1.hostid=hh1.hostid'.
				' LEFT JOIN permission p1 ON hh1.hgsetid=p1.hgsetid'.
					' AND p1.ugsetid=p.ugsetid'.
				' WHERE g.graphid=gi1.graphid'.
					' AND p1.permission IS NULL'.
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
	private function validateDelete(array &$graphids, ?array &$db_graphs = null) {
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
	 * @inheritdoc
	 */
	protected static function checkDuplicates(array $graphs): void {
		$_graph_indexes = [];

		foreach ($graphs as $i => $graph) {
			foreach ($graph['gitems'] as $gitem) {
				$_graph_indexes[$gitem['itemid']][] = $i;
			}
		}

		$result = DBselect(
			'SELECT i.itemid,id.parent_itemid'.
			' FROM items i,item_discovery id'.
			' WHERE i.itemid=id.itemid'.
				' AND '.dbConditionId('i.itemid', array_keys($_graph_indexes)).
				' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE])
		);

		$graph_indexes = [];

		while ($row = DBfetch($result)) {
			foreach ($_graph_indexes[$row['itemid']] as $i) {
				if (array_key_exists($row['parent_itemid'], $graph_indexes)
						&& array_key_exists($graphs[$i]['name'], $graph_indexes[$row['parent_itemid']])
						&& $graph_indexes[$row['parent_itemid']][$graphs[$i]['name']] != $i) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
						_s('value %1$s already exists', '(name)=('.$graphs[$i]['name'].')')
					));
				}

				$graph_indexes[$row['parent_itemid']][$graphs[$i]['name']] = $i;
			}
		}

		$result = DBselect(
			'SELECT DISTINCT g.graphid,g.name,id.parent_itemid'.
			' FROM graphs g,graphs_items gi,items i,item_discovery id'.
			' WHERE g.graphid=gi.graphid'.
				' AND gi.itemid=i.itemid'.
				' AND i.itemid=id.itemid'.
				' AND '.dbConditionString('g.name', array_unique(array_column($graphs, 'name'))).
				' AND '.dbConditionInt('g.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE]).
				' AND '.dbConditionId('id.parent_itemid', array_keys($graph_indexes))
		);

		while ($row = DBfetch($result)) {
			if (array_key_exists($row['parent_itemid'], $graph_indexes)
					&& array_key_exists($row['name'], $graph_indexes[$row['parent_itemid']])) {
				$graph = $graphs[$graph_indexes[$row['parent_itemid']][$row['name']]];

				if (!array_key_exists('graphid', $graph) || bccomp($row['graphid'], $graph['graphid']) != 0) {
					$data = DBfetch(DBselect(
						'SELECT i.key_,h.host,h.status'.
						' FROM items i,hosts h'.
						' WHERE i.hostid=h.hostid'.
							' AND '.dbConditionId('i.itemid', [$row['parent_itemid']])
					));

					$error = in_array($data['status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])
						? _('Graph prototype "%1$s" already exists on the LLD rule with key "%2$s" of the host "%3$s".')
						: _('Graph prototype "%1$s" already exists on the LLD rule with key "%2$s" of the template "%3$s".');

					self::exception(ZBX_API_ERROR_PARAMETERS,
						sprintf($error, $graph['name'], $data['key_'], $data['host'])
					);
				}
			}
		}
	}
}
