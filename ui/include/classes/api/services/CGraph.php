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
 * Class containing methods for operations with graph.
 */
class CGraph extends CGraphGeneral {

	protected $tableName = 'graphs';
	protected $tableAlias = 'g';
	protected $sortColumns = ['graphid', 'name', 'graphtype'];

	public function __construct() {
		parent::__construct();

		$this->errorMessages = array_merge($this->errorMessages, [
			self::ERROR_TEMPLATE_HOST_MIX =>
				_('Graph "%1$s" with templated items cannot contain items from other hosts.'),
			self::ERROR_MISSING_GRAPH_NAME => _('Missing "name" field for graph.'),
			self::ERROR_MISSING_GRAPH_ITEMS => _('Missing items for graph "%1$s".'),
			self::ERROR_MISSING_REQUIRED_VALUE => _('No "%1$s" given for graph.'),
			self::ERROR_GRAPH_SUM => _('Cannot add more than one item with type "Graph sum" on graph "%1$s".')
		]);
	}

	/**
	 * Get graph data.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['graphs' => 'g.graphid'],
			'from'		=> 'graphs g',
			'where'		=> [],
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
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];

		$options = zbx_array_merge($defOptions, $options);

		self::validateGet($options);

		// permission check
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if (self::$userData['ugsetid'] == 0) {
				return $options['countOutput'] ? '0' : [];
			}

			$sqlParts['join']['gi'] = ['table' => 'graphs_items', 'using' => 'graphid'];
			$sqlParts['join']['i'] = ['left_table' => 'gi', 'table' => 'items', 'using' => 'itemid'];
			$sqlParts['join']['hh'] = ['left_table' => 'i', 'table' => 'host_hgset', 'using' => 'hostid'];
			$sqlParts['join']['p'] = ['left_table' => 'hh', 'table' => 'permission', 'using' => 'hgsetid'];
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
					' AND p1.hgsetid IS NULL'.
			')';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['join']['gi'] = ['table' => 'graphs_items', 'using' => 'graphid'];
			$sqlParts['join']['i'] = ['left_table' => 'gi', 'table' => 'items', 'using' => 'itemid'];
			$sqlParts['join']['hg'] = ['left_table' => 'i', 'table' => 'hosts_groups', 'using' => 'hostid'];
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);

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

			$sqlParts['join']['gi'] = ['table' => 'graphs_items', 'using' => 'graphid'];
			$sqlParts['join']['i'] = ['left_table' => 'gi', 'table' => 'items', 'using' => 'itemid'];
			$sqlParts['where'][] = dbConditionInt('i.hostid', $options['hostids']);

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

			$sqlParts['join']['gi'] = ['table' => 'graphs_items', 'using' => 'graphid'];
			$sqlParts['where'][] = dbConditionInt('gi.itemid', $options['itemids']);

			if ($options['groupCount']) {
				$sqlParts['group']['gi'] = 'gi.itemid';
			}
		}

		// templated
		if (!is_null($options['templated'])) {
			$sqlParts['join']['gi'] = ['table' => 'graphs_items', 'using' => 'graphid'];
			$sqlParts['join']['i'] = ['left_table' => 'gi', 'table' => 'items', 'using' => 'itemid'];
			$sqlParts['join']['h'] = ['left_table' => 'i', 'table' => 'hosts', 'using' => 'hostid'];

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
			$options['filter'] = [];
		}

		if (is_array($options['filter'])) {
			if (!array_key_exists('flags', $options['filter'])) {
				$options['filter']['flags'] = [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED];
			}

			$this->dbFilter('graphs g', $options, $sqlParts);

			if (isset($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['join']['gi'] = ['table' => 'graphs_items', 'using' => 'graphid'];
				$sqlParts['join']['i'] = ['left_table' => 'gi', 'table' => 'items', 'using' => 'itemid'];
				$sqlParts['join']['h'] = ['left_table' => 'i', 'table' => 'hosts', 'using' => 'hostid'];
				$sqlParts['where']['host'] = dbConditionString('h.host', $options['filter']['host']);
			}

			if (isset($options['filter']['hostid'])) {
				zbx_value2array($options['filter']['hostid']);

				$sqlParts['join']['gi'] = ['table' => 'graphs_items', 'using' => 'graphid'];
				$sqlParts['join']['i'] = ['left_table' => 'gi', 'table' => 'items', 'using' => 'itemid'];
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
				// Graphs share table with graph prototypes. Therefore remove graph unrelated fields.
				unset($graph['discover']);

				$result[$graph['graphid']] = $graph;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if (isset($options['expandName'])) {
			$result = CMacrosResolverHelper::resolveGraphNameByIds($result);
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);

			if (!$options['preservekeys']) {
				$result = array_values($result);
			}
		}

		return $result;
	}

	private static function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'selectDiscoveryRule' =>	['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', CDiscoveryRule::OUTPUT_FIELDS), 'default' => null],
			'selectGraphDiscovery' =>	['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE | API_DEPRECATED, 'in' => implode(',', self::DISCOVERY_DATA_OUTPUT_FIELDS), 'default' => null],
			'selectDiscoveryData' =>	['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', self::DISCOVERY_DATA_OUTPUT_FIELDS), 'default' => null]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Delete graphs.
	 *
	 * @param array $graphids
	 *
	 * @return array
	 */
	public function delete(array $graphids) {
		$this->validateDelete($graphids, $db_graphs);

		CGraphManager::delete($graphids);

		$this->addAuditBulk(CAudit::ACTION_DELETE, CAudit::RESOURCE_GRAPH, $db_graphs);

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
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete templated graph.'));
			}
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// adding Items
		if ($options['selectItems'] !== null && $options['selectItems'] !== API_OUTPUT_COUNT) {
			$relation_map = $this->createRelationMap($result, 'graphid', 'itemid', 'graphs_items');
			$items = API::Item()->get([
				'output' => $options['selectItems'],
				'itemids' => $relation_map->getRelatedIds(),
				'webitems' => true,
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relation_map->mapMany($result, $items, 'items');
		}

		self::addRelatedDiscoveryRules($options, $result);
		self::addRelatedGraphDiscovery($options, $result);
		self::addRelatedDiscoveryData($options, $result);

		return $result;
	}

	private static function addRelatedGraphDiscovery(array $options, array &$result): void {
		if ($options['selectGraphDiscovery'] === null) {
			return;
		}

		foreach ($result as &$graph) {
			$graph['graphDiscovery'] = [];
		}
		unset($graph);

		$_options = [
			'output' => array_merge(['graphid'], $options['selectGraphDiscovery']),
			'graphids' => array_keys($result)
		];
		$resource = DBselect(DB::makeSql('graph_discovery', $_options));

		while ($graph_discovery = DBfetch($resource)) {
			$result[$graph_discovery['graphid']]['graphDiscovery'] =
				array_diff_key($graph_discovery, array_flip(['graphid']));
		}
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

		$options = [
			'output' => ['itemid', 'hostid'],
			'itemids' => array_keys($_graph_indexes),
			'filter' => ['flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]]
		];
		$result = DBselect(DB::makeSql('items', $options));

		$graph_indexes = [];

		while ($row = DBfetch($result)) {
			foreach ($_graph_indexes[$row['itemid']] as $i) {
				if (array_key_exists($row['hostid'], $graph_indexes)
						&& array_key_exists($graphs[$i]['name'], $graph_indexes[$row['hostid']])
						&& $graph_indexes[$row['hostid']][$graphs[$i]['name']] != $i) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
						_s('value %1$s already exists', '(name)=('.$graphs[$i]['name'].')')
					));
				}

				$graph_indexes[$row['hostid']][$graphs[$i]['name']] = $i;
			}
		}

		$result = DBselect(
			'SELECT DISTINCT g.graphid,g.name,i.hostid'.
			' FROM graphs g,graphs_items gi,items i'.
			' WHERE g.graphid=gi.graphid'.
				' AND gi.itemid=i.itemid'.
				' AND '.dbConditionString('g.name', array_unique(array_column($graphs, 'name'))).
				' AND '.dbConditionInt('g.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]).
				' AND '.dbConditionId('i.hostid', array_keys($graph_indexes))
		);

		while ($row = DBfetch($result)) {
			if (array_key_exists($row['hostid'], $graph_indexes)
					&& array_key_exists($row['name'], $graph_indexes[$row['hostid']])) {
				$graph = $graphs[$graph_indexes[$row['hostid']][$row['name']]];

				if (!array_key_exists('graphid', $graph) || bccomp($row['graphid'], $graph['graphid']) != 0) {
					$hosts = DB::select('hosts', [
						'output' => ['host', 'status'],
						'hostids' => $row['hostid']
					]);

					$error = in_array($hosts[0]['status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])
						? _('Graph "%1$s" already exists on the host "%2$s".')
						: _('Graph "%1$s" already exists on the template "%2$s".');

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $graph['name'], $hosts[0]['host']));
				}
			}
		}
	}

	/**
	 * Inherit template graphs from to hosts.
	 *
	 * @param array $templateids
	 * @param array $hostids
	 */
	public function linkTemplateObjects(array $templateids, array $hostids): void {
		$output = ['graphid', 'name', 'width', 'height', 'yaxismin', 'yaxismax', 'templateid', 'show_work_period',
			'show_triggers', 'graphtype', 'show_legend', 'show_3d', 'percent_left', 'percent_right', 'ymin_type',
			'ymax_type', 'ymin_itemid', 'ymax_itemid'
		];

		$graphs = $this->get([
			'output' => $output,
			'selectGraphItems' => ['itemid', 'drawtype', 'sortorder', 'color', 'yaxisside', 'calc_fnc', 'type'],
			'hostids' => $templateids,
			'preservekeys' => true,
			'nopermissions' => true
		]);

		if ($graphs) {
			$this->inherit($graphs, $hostids);
		}
	}
}
