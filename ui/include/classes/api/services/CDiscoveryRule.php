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
 * Class containing methods for operations with discovery rules.
 */
class CDiscoveryRule extends CItemGeneral {

	protected $tableName = 'items';
	protected $tableAlias = 'i';
	protected $sortColumns = ['itemid', 'name', 'key_', 'delay', 'type', 'status'];

	public const OUTPUT_FIELDS = ['itemid', 'type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'status',
		'trapper_hosts', 'templateid', 'params', 'ipmi_sensor', 'authtype', 'username', 'password', 'publickey',
		'privatekey', 'interfaceid', 'description', 'lifetime_type', 'lifetime', 'enabled_lifetime_type',
		'enabled_lifetime', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'posts', 'status_codes',
		'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode', 'request_method', 'ssl_cert_file',
		'ssl_key_file', 'ssl_key_password', 'verify_peer', 'verify_host', 'allow_traps', 'state', 'error', 'parameters'
	];

	/**
	 * @inheritDoc
	 */
	const SUPPORTED_PREPROCESSING_TYPES = [ZBX_PREPROC_REGSUB, ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH,
		ZBX_PREPROC_VALIDATE_REGEX, ZBX_PREPROC_VALIDATE_NOT_REGEX, ZBX_PREPROC_ERROR_FIELD_JSON,
		ZBX_PREPROC_ERROR_FIELD_XML, ZBX_PREPROC_THROTTLE_TIMED_VALUE, ZBX_PREPROC_SCRIPT,
		ZBX_PREPROC_PROMETHEUS_TO_JSON, ZBX_PREPROC_CSV_TO_JSON, ZBX_PREPROC_STR_REPLACE, ZBX_PREPROC_XML_TO_JSON,
		ZBX_PREPROC_SNMP_WALK_VALUE, ZBX_PREPROC_SNMP_WALK_TO_JSON, ZBX_PREPROC_SNMP_GET_VALUE
	];

	/**
	 * Define a set of supported item types.
	 *
	 * @var array
	 */
	const SUPPORTED_ITEM_TYPES = [ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL,
		ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH,
		ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT,
		ITEM_TYPE_BROWSER
	];

	/**
	 * A list of supported operation fields indexed by table name.
	 */
	const OPERATION_FIELDS = [
		'lld_override_opdiscover' => 'opdiscover',
		'lld_override_opstatus' => 'opstatus',
		'lld_override_opperiod' => 'opperiod',
		'lld_override_ophistory' => 'ophistory',
		'lld_override_optrends' => 'optrends',
		'lld_override_opseverity' => 'opseverity',
		'lld_override_optag' => 'optag',
		'lld_override_optemplate' => 'optemplate',
		'lld_override_opinventory' => 'opinventory'
	];

	/**
	 * Get DiscoveryRule data
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['items' => 'i.itemid'],
			'from'		=> ['items' => 'items i'],
			'where'		=> ['i.flags='.ZBX_FLAG_DISCOVERY_RULE],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'						=> null,
			'templateids'					=> null,
			'hostids'						=> null,
			'itemids'						=> null,
			'interfaceids'					=> null,
			'inherited'						=> null,
			'templated'						=> null,
			'monitored'						=> null,
			'editable'						=> false,
			'nopermissions'					=> null,
			// filter
			'filter'						=> null,
			'search'						=> null,
			'searchByAny'					=> null,
			'startSearch'					=> false,
			'excludeSearch'					=> false,
			'searchWildcardsEnabled'		=> null,
			// output
			'output'						=> API_OUTPUT_EXTEND,
			'selectHosts'					=> null,
			'selectItems'					=> null,
			'selectTriggers'				=> null,
			'selectGraphs'					=> null,
			'selectHostPrototypes'			=> null,
			'selectFilter'					=> null,
			'selectLLDMacroPaths'			=> null,
			'selectPreprocessing'			=> null,
			'selectOverrides'				=> null,
			'countOutput'					=> false,
			'groupCount'					=> false,
			'preservekeys'					=> false,
			'sortfield'						=> '',
			'sortorder'						=> '',
			'limit'							=> null,
			'limitSelects'					=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if (self::$userData['ugsetid'] == 0) {
				return $options['countOutput'] ? '0' : [];
			}

			$sqlParts['from'][] = 'host_hgset hh';
			$sqlParts['from'][] = 'permission p';
			$sqlParts['where'][] = 'i.hostid=hh.hostid';
			$sqlParts['where'][] = 'hh.hgsetid=p.hgsetid';
			$sqlParts['where'][] = 'p.ugsetid='.self::$userData['ugsetid'];

			if ($options['editable']) {
				$sqlParts['where'][] = 'p.permission='.PERM_READ_WRITE;
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

			$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['hostids']);

			if ($options['groupCount']) {
				$sqlParts['group']['i'] = 'i.hostid';
			}
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['where']['itemid'] = dbConditionInt('i.itemid', $options['itemids']);
		}

		// interfaceids
		if (!is_null($options['interfaceids'])) {
			zbx_value2array($options['interfaceids']);

			$sqlParts['where']['interfaceid'] = dbConditionId('i.interfaceid', $options['interfaceids']);

			if ($options['groupCount']) {
				$sqlParts['group']['i'] = 'i.interfaceid';
			}
		}

		// groupids
		if ($options['groupids'] !== null) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where'][] = 'hg.hostid=i.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// inherited
		if (!is_null($options['inherited'])) {
			if ($options['inherited']) {
				$sqlParts['where'][] = 'i.templateid IS NOT NULL';
			}
			else {
				$sqlParts['where'][] = 'i.templateid IS NULL';
			}
		}

		// templated
		if (!is_null($options['templated'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// monitored
		if (!is_null($options['monitored'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['monitored']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
				$sqlParts['where'][] = 'i.status='.ITEM_STATUS_ACTIVE;
			}
			else {
				$sqlParts['where'][] = '(h.status<>'.HOST_STATUS_MONITORED.' OR i.status<>'.ITEM_STATUS_ACTIVE.')';
			}
		}

		// search
		if (is_array($options['search'])) {
			if (array_key_exists('error', $options['search']) && $options['search']['error'] !== null) {
				zbx_db_search('item_rtdata ir', ['search' => ['error' => $options['search']['error']]] + $options,
					$sqlParts
				);
			}

			zbx_db_search('items i', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			if (array_key_exists('delay', $options['filter']) && $options['filter']['delay'] !== null) {
				$sqlParts['where'][] = makeUpdateIntervalFilter('i.delay', $options['filter']['delay']);
				unset($options['filter']['delay']);
			}

			if (array_key_exists('lifetime', $options['filter']) && $options['filter']['lifetime'] !== null) {
				$options['filter']['lifetime'] = getTimeUnitFilters($options['filter']['lifetime']);
			}

			if (array_key_exists('enabled_lifetime', $options['filter'])
					&& $options['filter']['enabled_lifetime'] !== null) {
				$options['filter']['enabled_lifetime'] = getTimeUnitFilters($options['filter']['enabled_lifetime']);
			}

			if (array_key_exists('state', $options['filter']) && $options['filter']['state'] !== null) {
				$this->dbFilter('item_rtdata ir', ['filter' => ['state' => $options['filter']['state']]] + $options,
					$sqlParts
				);
			}

			$this->dbFilter('items i', $options, $sqlParts);

			if (isset($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['h'] = dbConditionString('h.host', $options['filter']['host']);
			}
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($item = DBfetch($res)) {
			if (!$options['countOutput']) {
				$result[$item['itemid']] = $item;
				continue;
			}

			if ($options['groupCount']) {
				$result[] = $item;
			}
			else {
				$result = $item['rowscount'];
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			self::prepareItemsForApi($result, false);

			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['formula', 'evaltype']);
			$result = $this->unsetExtraFields($result, ['hostid'], $options['output']);
		}

		foreach ($result as &$item) {
			// Option 'Convert to JSON' is not supported for discovery rule.
			unset($item['output_format']);
		}
		unset($item);

		if (!$options['preservekeys']) {
			$result = array_values($result);
		}

		return $result;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ((!$options['countOutput'] && ($this->outputIsRequested('state', $options['output'])
				|| $this->outputIsRequested('error', $options['output'])))
				|| (is_array($options['search']) && array_key_exists('error', $options['search']))
				|| (is_array($options['filter']) && array_key_exists('state', $options['filter']))) {
			$sqlParts['left_join'][] = ['alias' => 'ir', 'table' => 'item_rtdata', 'using' => 'itemid'];
			$sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
		}

		if (!$options['countOutput']) {
			if ($this->outputIsRequested('state', $options['output'])) {
				$sqlParts = $this->addQuerySelect('ir.state', $sqlParts);
			}
			if ($this->outputIsRequested('error', $options['output'])) {
				/*
				 * SQL func COALESCE use for template items because they don't have record
				 * in item_rtdata table and DBFetch convert null to '0'
				 */
				$sqlParts = $this->addQuerySelect(dbConditionCoalesce('ir.error', '', 'error'), $sqlParts);
			}

			// add filter fields
			if ($this->outputIsRequested('formula', $options['selectFilter'])
					|| $this->outputIsRequested('eval_formula', $options['selectFilter'])
					|| $this->outputIsRequested('conditions', $options['selectFilter'])) {

				$sqlParts = $this->addQuerySelect('i.formula', $sqlParts);
				$sqlParts = $this->addQuerySelect('i.evaltype', $sqlParts);
			}
			if ($this->outputIsRequested('evaltype', $options['selectFilter'])) {
				$sqlParts = $this->addQuerySelect('i.evaltype', $sqlParts);
			}

			if ($options['selectHosts'] !== null) {
				$sqlParts = $this->addQuerySelect('i.hostid', $sqlParts);
			}
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$itemIds = array_keys($result);

		// adding items
		if (!is_null($options['selectItems'])) {
			if ($options['selectItems'] != API_OUTPUT_COUNT) {
				$items = [];
				$relationMap = $this->createRelationMap($result, 'parent_itemid', 'itemid', 'item_discovery');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$items = API::ItemPrototype()->get([
						'output' => $options['selectItems'],
						'itemids' => $related_ids,
						'nopermissions' => true,
						'preservekeys' => true
					]);
				}

				$result = $relationMap->mapMany($result, $items, 'items', $options['limitSelects']);
			}
			else {
				$items = API::ItemPrototype()->get([
					'discoveryids' => $itemIds,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);

				$items = zbx_toHash($items, 'parent_itemid');
				foreach ($result as $itemid => $item) {
					$result[$itemid]['items'] = array_key_exists($itemid, $items) ? $items[$itemid]['rowscount'] : '0';
				}
			}
		}

		// adding triggers
		if (!is_null($options['selectTriggers'])) {
			if ($options['selectTriggers'] != API_OUTPUT_COUNT) {
				$triggers = [];
				$relationMap = new CRelationMap();
				$res = DBselect(
					'SELECT id.parent_itemid,f.triggerid'.
					' FROM item_discovery id,items i,functions f'.
					' WHERE '.dbConditionInt('id.parent_itemid', $itemIds).
						' AND id.itemid=i.itemid'.
						' AND i.itemid=f.itemid'
				);
				while ($relation = DBfetch($res)) {
					$relationMap->addRelation($relation['parent_itemid'], $relation['triggerid']);
				}

				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$triggers = API::TriggerPrototype()->get([
						'output' => $options['selectTriggers'],
						'triggerids' => $related_ids,
						'preservekeys' => true
					]);
				}

				$result = $relationMap->mapMany($result, $triggers, 'triggers', $options['limitSelects']);
			}
			else {
				$triggers = API::TriggerPrototype()->get([
					'discoveryids' => $itemIds,
					'countOutput' => true,
					'groupCount' => true
				]);

				$triggers = zbx_toHash($triggers, 'parent_itemid');
				foreach ($result as $itemid => $item) {
					$result[$itemid]['triggers'] = array_key_exists($itemid, $triggers)
						? $triggers[$itemid]['rowscount']
						: '0';
				}
			}
		}

		// adding graphs
		if (!is_null($options['selectGraphs'])) {
			if ($options['selectGraphs'] != API_OUTPUT_COUNT) {
				$graphs = [];
				$relationMap = new CRelationMap();
				$res = DBselect(
					'SELECT id.parent_itemid,gi.graphid'.
					' FROM item_discovery id,items i,graphs_items gi'.
					' WHERE '.dbConditionInt('id.parent_itemid', $itemIds).
						' AND id.itemid=i.itemid'.
						' AND i.itemid=gi.itemid'
				);
				while ($relation = DBfetch($res)) {
					$relationMap->addRelation($relation['parent_itemid'], $relation['graphid']);
				}

				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$graphs = API::GraphPrototype()->get([
						'output' => $options['selectGraphs'],
						'graphids' => $related_ids,
						'preservekeys' => true
					]);
				}

				$result = $relationMap->mapMany($result, $graphs, 'graphs', $options['limitSelects']);
			}
			else {
				$graphs = API::GraphPrototype()->get([
					'discoveryids' => $itemIds,
					'countOutput' => true,
					'groupCount' => true
				]);

				$graphs = zbx_toHash($graphs, 'parent_itemid');
				foreach ($result as $itemid => $item) {
					$result[$itemid]['graphs'] = array_key_exists($itemid, $graphs)
						? $graphs[$itemid]['rowscount']
						: '0';
				}
			}
		}

		// adding hosts
		if ($options['selectHostPrototypes'] !== null) {
			if ($options['selectHostPrototypes'] != API_OUTPUT_COUNT) {
				$hostPrototypes = [];
				$relationMap = $this->createRelationMap($result, 'parent_itemid', 'hostid', 'host_discovery');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$hostPrototypes = API::HostPrototype()->get([
						'output' => $options['selectHostPrototypes'],
						'hostids' => $related_ids,
						'nopermissions' => true,
						'preservekeys' => true
					]);
				}

				$result = $relationMap->mapMany($result, $hostPrototypes, 'hostPrototypes', $options['limitSelects']);
			}
			else {
				$hostPrototypes = API::HostPrototype()->get([
					'discoveryids' => $itemIds,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);
				$hostPrototypes = zbx_toHash($hostPrototypes, 'parent_itemid');

				foreach ($result as $itemid => $item) {
					$result[$itemid]['hostPrototypes'] = array_key_exists($itemid, $hostPrototypes)
						? $hostPrototypes[$itemid]['rowscount']
						: '0';
				}
			}
		}

		if ($options['selectFilter'] !== null) {
			$has_evaltype = $this->outputIsRequested('evaltype', $options['selectFilter']);
			$has_formula = $this->outputIsRequested('formula', $options['selectFilter']);
			$has_eval_formula = $this->outputIsRequested('eval_formula', $options['selectFilter']);
			$has_conditions = $this->outputIsRequested('conditions', $options['selectFilter']);

			foreach ($result as &$item) {
				$item['filter'] = [];

				if ($has_evaltype) {
					$item['filter']['evaltype'] = $item['evaltype'];
				}
			}
			unset($item);

			if ($has_formula || $has_eval_formula || $has_conditions) {
				$db_conditions = DBselect(
					'SELECT c.itemid,c.item_conditionid,c.macro,c.operator,c.value'.
					' FROM item_condition c'.
					' WHERE '.dbConditionInt('c.itemid', $itemIds)
				);

				$item_conditions = [];

				while ($db_condition = DBfetch($db_conditions)) {
					$item_conditions[$db_condition['itemid']][$db_condition['item_conditionid']] =
						array_diff_key($db_condition, array_flip(['item_conditionid', 'itemid']));
				}

				foreach ($result as &$item) {
					$eval_formula = '';
					$conditions = array_key_exists($item['itemid'], $item_conditions)
						? $item_conditions[$item['itemid']]
						: [];

					if ($item['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						CConditionHelper::sortConditionsByFormula($conditions, $item['formula']);

						$eval_formula = $item['formula'];
					}
					else {
						CConditionHelper::sortLldRuleConditions($conditions);

						$eval_formula = CConditionHelper::getEvalFormula($conditions, 'macro', (int) $item['evaltype']);
					}

					CConditionHelper::addFormulaIds($conditions, $eval_formula);
					CConditionHelper::replaceConditionIds($eval_formula, $conditions);

					if ($has_formula) {
						$item['filter']['formula'] = $item['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION
							? $eval_formula
							: '';
					}

					if ($has_eval_formula) {
						$item['filter']['eval_formula'] = $eval_formula;
					}

					if ($has_conditions) {
						$item['filter']['conditions'] = array_values($conditions);
					}
				}
				unset($item);
			}
		}

		// Add LLD macro paths.
		if ($options['selectLLDMacroPaths'] !== null && $options['selectLLDMacroPaths'] != API_OUTPUT_COUNT) {
			$lld_macro_paths = API::getApiService()->select('lld_macro_path', [
				'output' => $this->outputExtend($options['selectLLDMacroPaths'], ['itemid']),
				'filter' => ['itemid' => $itemIds]
			]);

			foreach ($result as &$lld_rule) {
				$lld_rule['lld_macro_paths'] = [];
			}
			unset($lld_rule);

			foreach ($lld_macro_paths as $lld_macro_path) {
				$itemid = $lld_macro_path['itemid'];

				unset($lld_macro_path['lld_macro_pathid'], $lld_macro_path['itemid']);

				$result[$itemid]['lld_macro_paths'][] = $lld_macro_path;
			}
		}

		// add overrides
		if ($options['selectOverrides'] !== null && $options['selectOverrides'] != API_OUTPUT_COUNT) {
			$ovrd_fields = ['itemid', 'lld_overrideid'];
			$filter_requested = $this->outputIsRequested('filter', $options['selectOverrides']);
			$operations_requested = $this->outputIsRequested('operations', $options['selectOverrides']);

			if ($filter_requested) {
				$ovrd_fields = array_merge($ovrd_fields, ['formula', 'evaltype']);
			}

			$overrides = API::getApiService()->select('lld_override', [
				'output' => $this->outputExtend($options['selectOverrides'], $ovrd_fields),
				'filter' => ['itemid' => $itemIds],
				'preservekeys' => true
			]);

			if ($filter_requested && $overrides) {
				$db_conditions = DBselect(
					'SELECT c.lld_overrideid,c.lld_override_conditionid,c.macro,c.operator,c.value'.
					' FROM lld_override_condition c'.
					' WHERE '.dbConditionInt('c.lld_overrideid', array_keys($overrides))
				);

				$override_conditions = [];

				while ($db_condition = DBfetch($db_conditions)) {
					$override_conditions[$db_condition['lld_overrideid']][$db_condition['lld_override_conditionid']] =
						array_diff_key($db_condition, array_flip(['lld_override_conditionid', 'lld_overrideid']));
				}

				foreach ($overrides as &$override) {
					$eval_formula = '';
					$conditions = array_key_exists($override['lld_overrideid'], $override_conditions)
						? $override_conditions[$override['lld_overrideid']]
						: [];

					if ($override['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						CConditionHelper::sortConditionsByFormula($conditions, $override['formula']);

						$eval_formula = $override['formula'];
					}
					else {
						CConditionHelper::sortLldRuleConditions($conditions);

						$eval_formula =
							CConditionHelper::getEvalFormula($conditions, 'macro', (int) $override['evaltype']);
					}

					CConditionHelper::addFormulaIds($conditions, $eval_formula);
					CConditionHelper::replaceConditionIds($eval_formula, $conditions);

					$override['filter'] = [
						'evaltype' => $override['evaltype'],
						'formula' => $override['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION ? $eval_formula : '',
						'eval_formula' => $eval_formula,
						'conditions' => array_values($conditions)
					];

					unset($override['evaltype'], $override['formula']);
				}
				unset($override);
			}

			if ($operations_requested && $overrides) {
				$operations = DB::select('lld_override_operation', [
					'output' => ['lld_override_operationid', 'lld_overrideid', 'operationobject', 'operator', 'value'],
					'filter' => ['lld_overrideid' => array_keys($overrides)],
					'sortfield' => ['lld_override_operationid'],
					'preservekeys' => true
				]);

				if ($operations) {
					$opdiscover = DB::select('lld_override_opdiscover', [
						'output' => ['lld_override_operationid', 'discover'],
						'filter' => ['lld_override_operationid' => array_keys($operations)]
					]);

					$item_prototype_objectids = [];
					$trigger_prototype_objectids = [];
					$host_prototype_objectids = [];

					foreach ($operations as $operation) {
						switch ($operation['operationobject']) {
							case OPERATION_OBJECT_ITEM_PROTOTYPE:
								$item_prototype_objectids[$operation['lld_override_operationid']] = true;
								break;

							case OPERATION_OBJECT_TRIGGER_PROTOTYPE:
								$trigger_prototype_objectids[$operation['lld_override_operationid']] = true;
								break;

							case OPERATION_OBJECT_HOST_PROTOTYPE:
								$host_prototype_objectids[$operation['lld_override_operationid']] = true;
								break;
						}
					}

					if ($item_prototype_objectids || $trigger_prototype_objectids || $host_prototype_objectids) {
						$opstatus = DB::select('lld_override_opstatus', [
							'output' => ['lld_override_operationid', 'status'],
							'filter' => ['lld_override_operationid' => array_keys(
								$item_prototype_objectids + $trigger_prototype_objectids + $host_prototype_objectids
							)]
						]);
					}

					if ($item_prototype_objectids) {
						$ophistory = DB::select('lld_override_ophistory', [
							'output' => ['lld_override_operationid', 'history'],
							'filter' => ['lld_override_operationid' => array_keys($item_prototype_objectids)]
						]);
						$optrends = DB::select('lld_override_optrends', [
							'output' => ['lld_override_operationid', 'trends'],
							'filter' => ['lld_override_operationid' => array_keys($item_prototype_objectids)]
						]);
						$opperiod = DB::select('lld_override_opperiod', [
							'output' => ['lld_override_operationid', 'delay'],
							'filter' => ['lld_override_operationid' => array_keys($item_prototype_objectids)]
						]);
					}

					if ($trigger_prototype_objectids) {
						$opseverity = DB::select('lld_override_opseverity', [
							'output' => ['lld_override_operationid', 'severity'],
							'filter' => ['lld_override_operationid' => array_keys($trigger_prototype_objectids)]
						]);
					}

					if ($trigger_prototype_objectids || $host_prototype_objectids || $item_prototype_objectids) {
						$optag = DB::select('lld_override_optag', [
							'output' => ['lld_override_operationid', 'tag', 'value'],
							'filter' => ['lld_override_operationid' => array_keys(
								$trigger_prototype_objectids + $host_prototype_objectids + $item_prototype_objectids
							)]
						]);
					}

					if ($host_prototype_objectids) {
						$optemplate = DB::select('lld_override_optemplate', [
							'output' => ['lld_override_operationid', 'templateid'],
							'filter' => ['lld_override_operationid' => array_keys($host_prototype_objectids)]
						]);
						$opinventory = DB::select('lld_override_opinventory', [
							'output' => ['lld_override_operationid', 'inventory_mode'],
							'filter' => ['lld_override_operationid' => array_keys($host_prototype_objectids)]
						]);
					}

					foreach ($operations as &$operation) {
						$lld_override_operationid = $operation['lld_override_operationid'];

						if ($item_prototype_objectids || $trigger_prototype_objectids || $host_prototype_objectids) {
							foreach ($opstatus as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['opstatus']['status'] = $row['status'];
								}
							}
						}

						foreach ($opdiscover as $row) {
							if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
								$operation['opdiscover']['discover'] = $row['discover'];
							}
						}

						if ($item_prototype_objectids) {
							foreach ($ophistory as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['ophistory']['history'] = $row['history'];
								}
							}

							foreach ($optrends as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['optrends']['trends'] = $row['trends'];
								}
							}

							foreach ($opperiod as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['opperiod']['delay'] = $row['delay'];
								}
							}
						}

						if ($trigger_prototype_objectids) {
							foreach ($opseverity as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['opseverity']['severity'] = $row['severity'];
								}
							}
						}

						if ($trigger_prototype_objectids || $host_prototype_objectids || $item_prototype_objectids) {
							foreach ($optag as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['optag'][] = ['tag' => $row['tag'], 'value' => $row['value']];
								}
							}
						}

						if ($host_prototype_objectids) {
							foreach ($optemplate as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['optemplate'][] = ['templateid' => $row['templateid']];
								}
							}

							foreach ($opinventory as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['opinventory']['inventory_mode'] = $row['inventory_mode'];
								}
							}
						}
					}
					unset($operation);
				}

				$relation_map = $this->createRelationMap($operations, 'lld_overrideid', 'lld_override_operationid');

				$overrides = $relation_map->mapMany($overrides, $operations, 'operations');
			}

			foreach ($result as &$row) {
				$row['overrides'] = [];

				foreach ($overrides as $override) {
					if (bccomp($override['itemid'], $row['itemid']) == 0) {
						unset($override['itemid'], $override['lld_overrideid']);

						if ($operations_requested) {
							foreach ($override['operations'] as &$operation) {
								unset($operation['lld_override_operationid'], $operation['lld_overrideid']);
							}
							unset($operation);
						}

						$row['overrides'][] = $override;
					}
				}
			}
			unset($row);
		}

		return $result;
	}

	/**
	 * @param array $items
	 *
	 * @return array
	 */
	public function create(array $items): array {
		self::validateCreate($items);

		self::createForce($items);
		self::inherit($items);

		return ['itemids' => array_column($items, 'itemid')];
	}

	/**
	 * @param array $items
	 *
	 * @throws APIException
	 */
	private static function validateCreate(array &$items): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => [
			'hostid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $items, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkHostsAndTemplates($items, $db_hosts, $db_templates);
		self::addHostStatus($items, $db_hosts, $db_templates);
		self::addFlags($items, ZBX_FLAG_DISCOVERY_RULE);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['uuid'], ['hostid', 'key_']], 'fields' => [
			'host_status' =>			['type' => API_ANY],
			'flags' =>					['type' => API_ANY],
			'uuid' =>					['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'host_status', 'in' => implode(',', [HOST_STATUS_TEMPLATE])], 'type' => API_UUID],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'uuid'), 'unset' => true]
			]],
			'hostid' =>					['type' => API_ANY],
			'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
			'type' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
			'key_' =>					['type' => API_ITEM_KEY, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('items', 'key_')],
			'lifetime_type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER, ZBX_LLD_DELETE_IMMEDIATELY]), 'default' => DB::getDefault('items', 'lifetime_type')],
			'lifetime' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'lifetime'), 'default' => DB::getDefault('items', 'lifetime')],
											['else' => true, 'type' => API_TIME_UNIT, 'in' => '0', 'default' => 0]
			]],
			'enabled_lifetime_type' =>	['type' => API_MULTIPLE, 'default' => DB::getDefault('items', 'enabled_lifetime_type'), 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER, ZBX_LLD_DISABLE_NEVER, ZBX_LLD_DISABLE_IMMEDIATELY])],
											['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('items', 'enabled_lifetime_type')]
			]],
			'enabled_lifetime' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'enabled_lifetime_type', 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'enabled_lifetime')],
												['else' => true, 'type' => API_TIME_UNIT, 'in' => DB::getDefault('items', 'enabled_lifetime')]
											]],
											['else' => true, 'type' => API_TIME_UNIT, 'in' => DB::getDefault('items', 'enabled_lifetime')]
			]],
			'description' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'preprocessing' =>			self::getPreprocessingValidationRules(),
			'lld_macro_paths' =>		self::getLldMacroPathsValidationRules(),
			'filter' =>					self::getFilterValidationRules('items', 'item_condition'),
			'overrides' =>				self::getOverridesValidationRules()
		]];

		if (!CApiInputValidator::validate($api_input_rules, $items, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::validateByType(array_keys($api_input_rules['fields']), $items);

		self::addUuid($items);

		self::checkUuidDuplicates($items);
		self::checkDuplicates($items);
		self::checkLifetimeFields($items);
		self::checkHostInterfaces($items);
		self::checkDependentItems($items);
		self::checkFilterFormula($items);
		self::checkOverridesFilterFormula($items);
		self::checkOverridesOperationTemplates($items);
	}

	/**
	 * @param array $items
	 */
	private static function createForce(array &$items): void {
		self::addValueType($items);

		self::prepareItemsForDb($items);
		$itemids = DB::insert('items', $items);
		self::prepareItemsForApi($items);

		$ins_items_rtdata = [];
		$host_statuses = [];

		foreach ($items as &$item) {
			$item['itemid'] = array_shift($itemids);

			if (in_array($item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])) {
				$ins_items_rtdata[] = ['itemid' => $item['itemid']];
			}

			$host_statuses[] = $item['host_status'];
			unset($item['host_status'], $item['flags'], $item['value_type']);
		}
		unset($item);

		if ($ins_items_rtdata) {
			DB::insertBatch('item_rtdata', $ins_items_rtdata, false);
		}

		self::updateParameters($items);
		self::updatePreprocessing($items);
		self::updateLldMacroPaths($items);
		self::updateItemFilters($items);
		self::updateOverrides($items);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_LLD_RULE, $items);

		foreach ($items as &$item) {
			$item['host_status'] = array_shift($host_statuses);
			$item['flags'] = ZBX_FLAG_DISCOVERY_RULE;
		}
		unset($item);
	}

	/**
	 * Add value_type property to given items.
	 *
	 * @param array $items
	 */
	private static function addValueType(array &$items): void {
		foreach ($items as &$item) {
			$item['value_type'] = ITEM_VALUE_TYPE_TEXT;
		}
		unset($item);
	}

	/**
	 * @param array $items
	 *
	 * @return array
	 */
	public function update(array $items): array {
		$this->validateUpdate($items, $db_items);

		$itemids = array_column($items, 'itemid');

		self::updateForce($items, $db_items);
		self::inherit($items, $db_items);

		return ['itemids' => $itemids];
	}

	/**
	 * @param array      $items
	 * @param array|null $db_items
	 *
	 * @throws APIException
	 */
	protected function validateUpdate(array &$items, ?array &$db_items): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['itemid']], 'fields' => [
			'itemid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'lifetime_type' => ['type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER, ZBX_LLD_DELETE_IMMEDIATELY])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $items, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$count = $this->get([
			'countOutput' => true,
			'itemids' => array_column($items, 'itemid'),
			'editable' => true
		]);

		if ($count != count($items)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_items = DB::select('items', [
			'output' => array_merge(['uuid', 'itemid', 'name', 'type', 'key_', 'lifetime_type', 'lifetime',
				'enabled_lifetime_type', 'enabled_lifetime', 'description', 'status'],
				array_diff(CItemType::FIELD_NAMES, ['parameters'])
			),
			'itemids' => array_column($items, 'itemid'),
			'preservekeys' => true
		]);

		self::addInternalFields($db_items);

		foreach ($items as $i => &$item) {
			$db_item = $db_items[$item['itemid']];
			$item['host_status'] = $db_item['host_status'];

			$item += ['lifetime_type' => $db_item['lifetime_type']];

			$item += $item['lifetime_type'] == ZBX_LLD_DELETE_IMMEDIATELY
				? ['enabled_lifetime_type' => DB::getDefault('items', 'enabled_lifetime_type')]
				: ['enabled_lifetime_type' => $db_item['enabled_lifetime_type']];

			$api_input_rules = $db_item['templateid'] == 0
				? self::getValidationRules()
				: self::getInheritedValidationRules();

			if (!CApiInputValidator::validate($api_input_rules, $item, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
		unset($item);

		$items = $this->extendObjectsByKey($items, $db_items, 'itemid', ['type', 'key_']);

		self::validateByType(array_keys($api_input_rules['fields']), $items, $db_items);

		$items = $this->extendObjectsByKey($items, $db_items, 'itemid',
			['hostid', 'flags', 'lifetime', 'enabled_lifetime']
		);

		self::validateUniqueness($items);

		self::addAffectedObjects($items, $db_items);

		self::checkUuidDuplicates($items, $db_items);
		self::checkDuplicates($items, $db_items);
		self::checkLifetimeFields($items);
		self::checkHostInterfaces($items, $db_items);
		self::checkDependentItems($items, $db_items);
		self::checkFilterFormula($items);
		self::checkOverridesFilterFormula($items);
		self::checkOverridesOperationTemplates($items);
	}

	/**
	 * @return array
	 */
	private static function getValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'host_status' =>			['type' => API_ANY],
			'uuid' =>					['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'host_status', 'in' => HOST_STATUS_TEMPLATE], 'type' => API_UUID],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'uuid'), 'unset' => true]
			]],
			'itemid' =>					['type' => API_ANY],
			'name' =>					['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
			'type' =>					['type' => API_INT32, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
			'key_' =>					['type' => API_ITEM_KEY, 'length' => DB::getFieldLength('items', 'key_')],
			'lifetime_type' =>			['type' => API_ANY],
			'lifetime' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'lifetime')],
											['else' => true, 'type' => API_TIME_UNIT, 'in' => '0']
			]],
			'enabled_lifetime_type' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER, ZBX_LLD_DISABLE_NEVER, ZBX_LLD_DISABLE_IMMEDIATELY])],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'enabled_lifetime_type')]
			]],
			'enabled_lifetime' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'enabled_lifetime_type', 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'enabled_lifetime')],
												['else' => true, 'type' => API_TIME_UNIT, 'in' => DB::getDefault('items', 'enabled_lifetime')]
											]],
											['else' => true, 'type' => API_TIME_UNIT, 'in' => DB::getDefault('items', 'enabled_lifetime')]
			]],
			'description' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'preprocessing' =>			self::getPreprocessingValidationRules(),
			'lld_macro_paths' =>		self::getLldMacroPathsValidationRules(),
			'filter' =>					self::getFilterValidationRules('items', 'item_condition'),
			'overrides' =>				self::getOverridesValidationRules()
		]];
	}

	/**
	 * @return array
	 */
	private static function getInheritedValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'host_status' =>			['type' => API_ANY],
			'uuid' =>					['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'itemid' =>					['type' => API_ANY],
			'name' =>					['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'type' =>					['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'key_' =>					['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'lifetime_type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER, ZBX_LLD_DELETE_IMMEDIATELY])],
			'lifetime' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'lifetime')],
											['else' => true, 'type' => API_TIME_UNIT, 'in' => '0']
			]],
			'enabled_lifetime_type' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER, ZBX_LLD_DISABLE_NEVER, ZBX_LLD_DISABLE_IMMEDIATELY])],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'enabled_lifetime_type')]
			]],
			'enabled_lifetime' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'enabled_lifetime_type', 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'enabled_lifetime')],
												['else' => true, 'type' => API_TIME_UNIT, 'in' => DB::getDefault('items', 'enabled_lifetime')]
											]],
											['else' => true, 'type' => API_TIME_UNIT, 'in' => DB::getDefault('items', 'enabled_lifetime')]
			]],
			'description' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'preprocessing' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'lld_macro_paths' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'filter' =>					self::getFilterValidationRules('items', 'item_condition'),
			'overrides' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED]
		]];
	}

	/**
	 * @return array
	 */
	private static function getLldMacroPathsValidationRules(): array {
		return ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['lld_macro']], 'fields' => [
			'lld_macro' =>	['type' => API_LLD_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('lld_macro_path', 'lld_macro')],
			'path' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_macro_path', 'path')]
		]];
	}

	/**
	 * @param string $base_table
	 * @param string $condition_table
	 *
	 * @return array
	 */
	private static function getFilterValidationRules(string $base_table, string $condition_table): array {
		$condition_fields = [
			'macro' =>		['type' => API_LLD_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength($condition_table, 'macro')],
			'operator' =>	['type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP, CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS]), 'default' => CONDITION_OPERATOR_REGEXP],
			'value' =>		['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'operator', 'in' => implode(',', [CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength($condition_table, 'value')],
								['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault($condition_table, 'value')]
			]]
		];

		return ['type' => API_OBJECT, 'fields' => [
			'evaltype' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION])],
			'formula' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'evaltype', 'in' => CONDITION_EVAL_TYPE_EXPRESSION], 'type' => API_COND_FORMULA, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength($base_table, 'formula')],
								['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault($base_table, 'formula')]
			]],
			'conditions' =>	['type' => API_MULTIPLE, 'flags' => API_REQUIRED | API_NORMALIZE, 'rules' => [
								['if' => ['field' => 'evaltype', 'in' => CONDITION_EVAL_TYPE_EXPRESSION], 'type' => API_OBJECTS, 'uniq' => [['formulaid']], 'fields' => [
									'formulaid' =>	['type' => API_COND_FORMULAID, 'flags' => API_REQUIRED]
								] + $condition_fields],
								['else' => true, 'type' => API_OBJECTS, 'fields' => [
									'formulaid' => ['type' => API_STRING_UTF8, 'in' => '', 'unset' => true]
								] + $condition_fields]
			]]
		]];
	}

	/**
	 * @return array
	 */
	private static function getOverridesValidationRules(): array {
		return ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['name'], ['step']], 'fields' => [
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_override', 'name')],
			'step' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(':', [1, ZBX_MAX_INT32])],
			'stop' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_LLD_OVERRIDE_STOP_NO, ZBX_LLD_OVERRIDE_STOP_YES])],
			'filter' =>		self::getFilterValidationRules('lld_override', 'lld_override_condition'),
			'operations' =>	self::getOperationsValidationRules()
		]];
	}

	/**
	 * @return array
	 */
	private static function getOperationsValidationRules(): array {
		return ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
			'operationobject' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [OPERATION_OBJECT_ITEM_PROTOTYPE, OPERATION_OBJECT_TRIGGER_PROTOTYPE, OPERATION_OBJECT_GRAPH_PROTOTYPE, OPERATION_OBJECT_HOST_PROTOTYPE])],
			'operator' =>			['type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP])],
			'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('lld_override_operation', 'value')],
			'opdiscover' =>			['type' => API_OBJECT, 'fields' => [
				'discover' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER])]
			]],
			'opstatus' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'operationobject', 'in' => implode(',', [OPERATION_OBJECT_ITEM_PROTOTYPE, OPERATION_OBJECT_TRIGGER_PROTOTYPE, OPERATION_OBJECT_HOST_PROTOTYPE])], 'type' => API_OBJECT, 'fields' => [
											'status' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_PROTOTYPE_STATUS_ENABLED, ZBX_PROTOTYPE_STATUS_DISABLED])]
										]],
										['else' => true, 'type' => API_OBJECT, 'fields' => []]
			]],
			'opperiod' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'operationobject', 'in' => implode(',', [OPERATION_OBJECT_ITEM_PROTOTYPE])], 'type' => API_OBJECT, 'fields' => [
											'delay' =>			['type' => API_ITEM_DELAY, 'flags' => API_REQUIRED | API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('lld_override_opperiod', 'delay')]
										]],
										['else' => true, 'type' => API_OBJECT, 'fields' => []]
			]],
			'ophistory' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'operationobject', 'in' => implode(',', [OPERATION_OBJECT_ITEM_PROTOTYPE])], 'type' => API_OBJECT, 'fields' => [
											'history' =>		['type' => API_TIME_UNIT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('lld_override_ophistory', 'history')]
										]],
										['else' => true, 'type' => API_OBJECT, 'fields' => []]
			]],
			'optrends' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'operationobject', 'in' => implode(',', [OPERATION_OBJECT_ITEM_PROTOTYPE])], 'type' => API_OBJECT, 'fields' => [
											'trends' =>			['type' => API_TIME_UNIT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('lld_override_optrends', 'trends')]
										]],
										['else' => true, 'type' => API_OBJECT, 'fields' => []]
			]],
			'opseverity' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'operationobject', 'in' => implode(',', [OPERATION_OBJECT_TRIGGER_PROTOTYPE])], 'type' => API_OBJECT, 'fields' => [
											'severity' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_WARNING, TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH, TRIGGER_SEVERITY_DISASTER])]
										]],
										['else' => true, 'type' => API_OBJECT, 'fields' => []]
			]],
			'optag' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'operationobject', 'in' => implode(',', [OPERATION_OBJECT_ITEM_PROTOTYPE, OPERATION_OBJECT_TRIGGER_PROTOTYPE, OPERATION_OBJECT_HOST_PROTOTYPE])], 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
											'tag' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_override_optag', 'tag')],
											'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('lld_override_optag', 'value'), 'default' => DB::getDefault('lld_override_optag', 'value')]
										]],
										['else' => true, 'type' => API_OBJECTS, 'length' => 0]
			]],
			'optemplate' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'operationobject', 'in' => implode(',', [OPERATION_OBJECT_HOST_PROTOTYPE])], 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
											'templateid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
										]],
										['else' => true, 'type' => API_OBJECTS, 'length' => 0]
			]],
			'opinventory' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'operationobject', 'in' => implode(',', [OPERATION_OBJECT_HOST_PROTOTYPE])], 'type' => API_OBJECT, 'fields' => [
											'inventory_mode' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
										]],
										['else' => true, 'type' => API_OBJECT, 'fields' => []]
			]]
		]];
	}

	/**
	 * @inheritDoc
	 */
	protected static function addAffectedObjects(array $items, array &$db_items): void {
		self::addAffectedPreprocessing($items, $db_items);
		self::addAffectedLldMacroPaths($items, $db_items);
		self::addAffectedItemFilters($items, $db_items);
		self::addAffectedOverrides($items, $db_items);
		self::addAffectedParameters($items, $db_items);
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 */
	private static function addAffectedLldMacroPaths(array $items, array &$db_items): void {
		$itemids = [];

		foreach ($items as $item) {
			if (array_key_exists('lld_macro_paths', $item)) {
				$itemids[] = $item['itemid'];
				$db_items[$item['itemid']]['lld_macro_paths'] = [];
			}
		}

		if (!$itemids) {
			return;
		}

		$options = [
			'output' => ['lld_macro_pathid', 'itemid', 'lld_macro', 'path'],
			'filter' => ['itemid' => $itemids]
		];
		$db_lld_macro_paths = DBselect(DB::makeSql('lld_macro_path', $options));

		while ($db_lld_macro_path = DBfetch($db_lld_macro_paths)) {
			$db_items[$db_lld_macro_path['itemid']]['lld_macro_paths'][$db_lld_macro_path['lld_macro_pathid']] =
				array_diff_key($db_lld_macro_path, array_flip(['itemid']));
		}
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 */
	private static function addAffectedItemFilters(array $items, array &$db_items): void {
		$_db_items = [];

		foreach ($items as $item) {
			if (array_key_exists('filter', $item)) {
				$_db_items[$item['itemid']] = &$db_items[$item['itemid']];
			}
		}

		if (!$_db_items) {
			return;
		}

		self::addAffectedFilters($_db_items, 'items', 'item_condition');
	}

	/**
	 * @param array  $db_objects
	 * @param string $base_table
	 * @param string $condition_table
	 */
	private static function addAffectedFilters(array &$db_objects, string $base_table, string $condition_table): void {
		$base_pk = DB::getPk($base_table);
		$condition_pk = DB::getPk($condition_table);

		foreach ($db_objects as &$db_object) {
			$db_object['filter'] = [];
		}
		unset($db_object);

		$options = [
			'output' => [$base_pk, 'evaltype', 'formula'],
			'filter' => [$base_pk => array_keys($db_objects)]
		];
		$db_filters = DBselect(DB::makeSql($base_table, $options));

		while ($db_filter = DBfetch($db_filters)) {
			$conditions = [];

			if ($db_filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				CConditionHelper::addFormulaIds($conditions, $db_filter['formula']);
				CConditionHelper::replaceConditionIds($db_filter['formula'], $conditions);
			}

			$db_objects[$db_filter[$base_pk]]['filter'] =
				array_diff_key($db_filter, array_flip([$base_pk])) + ['conditions' => $conditions];
		}

		$options = [
			'output' => [$condition_pk, $base_pk, 'operator', 'macro', 'value'],
			'filter' => [$base_pk => array_keys($db_objects)]
		];
		$db_conditions = DBselect(DB::makeSql($condition_table, $options));

		while ($db_condition = DBfetch($db_conditions)) {
			if ($db_objects[$db_condition[$base_pk]]['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$db_objects[$db_condition[$base_pk]]['filter']['conditions'][$db_condition[$condition_pk]] +=
					array_diff_key($db_condition, array_flip([$base_pk]));
			}
			else {
				$db_condition['formulaid'] = '';

				$db_objects[$db_condition[$base_pk]]['filter']['conditions'][$db_condition[$condition_pk]] =
					array_diff_key($db_condition, array_flip([$base_pk]));
			}
		}
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 */
	private static function addAffectedOverrides(array $items, array &$db_items): void {
		$itemids = [];

		foreach ($items as $item) {
			if (array_key_exists('overrides', $item)) {
				$itemids[] = $item['itemid'];
				$db_items[$item['itemid']]['overrides'] = [];
			}
		}

		if (!$itemids) {
			return;
		}

		$options = [
			'output' => ['lld_overrideid', 'itemid', 'name', 'step', 'stop'],
			'filter' => ['itemid' => $itemids]
		];
		$result = DBselect(DB::makeSql('lld_override', $options));

		$db_overrides = [];

		while ($db_override = DBfetch($result)) {
			$db_items[$db_override['itemid']]['overrides'][$db_override['lld_overrideid']] =
				array_diff_key($db_override, array_flip(['itemid']));

			$db_overrides[$db_override['lld_overrideid']] = &$db_items[$db_override['itemid']]['overrides'][$db_override['lld_overrideid']];
		}

		if (!$db_overrides) {
			return;
		}

		self::addAffectedOverrideFilters($db_overrides);
		self::addAffectedOverrideOperations($db_overrides);
	}

	/**
	 * @param array $db_overrides
	 */
	private static function addAffectedOverrideFilters(array &$db_overrides): void {
		self::addAffectedFilters($db_overrides, 'lld_override', 'lld_override_condition');
	}

	/**
	 * @param array $db_overrides
	 */
	private static function addAffectedOverrideOperations(array &$db_overrides): void {
		foreach ($db_overrides as &$db_override) {
			$db_override['operations'] = [];
		}
		unset($db_override);

		$options = [
			'output' => ['lld_override_operationid', 'lld_overrideid', 'operationobject', 'operator', 'value'],
			'filter' => ['lld_overrideid' => array_keys($db_overrides)]
		];
		$result = DBselect(DB::makeSql('lld_override_operation', $options));

		$db_operations = [];

		while ($db_operation = DBfetch($result)) {
			$db_overrides[$db_operation['lld_overrideid']]['operations'][$db_operation['lld_override_operationid']] =
				array_diff_key($db_operation, array_flip(['lld_overrideid']));

			$db_operations[$db_operation['lld_override_operationid']] =
				&$db_overrides[$db_operation['lld_overrideid']]['operations'][$db_operation['lld_override_operationid']];
		}

		if (!$db_operations) {
			return;
		}

		self::addAffectedOverrideOperationSingleObjectFields($db_operations);
		self::addAffectedOverrideOperationTags($db_operations);
		self::addAffectedOverrideOperationTemplates($db_operations);
	}

	/**
	 * @param array $db_operations
	 */
	private static function addAffectedOverrideOperationSingleObjectFields(array &$db_operations): void {
		$db_op_fields = DBselect(
			'SELECT op.lld_override_operationid,d.discover AS d_discover,s.status AS s_status,p.delay AS p_delay,'.
				'h.history AS h_history,t.trends AS t_trends,ss.severity AS ss_severity,'.
				'i.inventory_mode AS i_inventory_mode'.
			' FROM lld_override_operation op'.
			' LEFT JOIN lld_override_opdiscover d ON op.lld_override_operationid=d.lld_override_operationid'.
			' LEFT JOIN lld_override_opstatus s ON op.lld_override_operationid=s.lld_override_operationid'.
			' LEFT JOIN lld_override_opperiod p ON op.lld_override_operationid=p.lld_override_operationid'.
			' LEFT JOIN lld_override_ophistory h ON op.lld_override_operationid=h.lld_override_operationid'.
			' LEFT JOIN lld_override_optrends t ON op.lld_override_operationid=t.lld_override_operationid'.
			' LEFT JOIN lld_override_opseverity ss ON op.lld_override_operationid=ss.lld_override_operationid'.
			' LEFT JOIN lld_override_opinventory i ON op.lld_override_operationid=i.lld_override_operationid'.
			' WHERE '.dbConditionId('op.lld_override_operationid', array_keys($db_operations))
		);

		$single_op_fields = [
			'd' => 'opdiscover',
			's' => 'opstatus',
			'p' => 'opperiod',
			'h' => 'ophistory',
			't' => 'optrends',
			'ss' => 'opseverity',
			'i' => 'opinventory'
		];

		while ($db_op_field = DBfetch($db_op_fields, false)) {
			foreach ($single_op_fields as $alias => $opfield) {
				$fields = [];
				$has_filled_fields = false;

				foreach ($db_op_field as $aliased_name => $value) {
					if (strncmp($aliased_name, $alias.'_', strlen($alias) + 1) != 0) {
						continue;
					}

					$fields[substr($aliased_name, strlen($alias) + 1)] = $value;

					if ($value !== null) {
						$has_filled_fields = true;
					}
				}

				$db_operations[$db_op_field['lld_override_operationid']][$opfield] = $has_filled_fields ? $fields : [];
			}
		}
	}

	/**
	 * @param array $db_operations
	 */
	private static function addAffectedOverrideOperationTags(array &$db_operations): void {
		$tag_operationobjects = [
			OPERATION_OBJECT_ITEM_PROTOTYPE, OPERATION_OBJECT_TRIGGER_PROTOTYPE, OPERATION_OBJECT_HOST_PROTOTYPE
		];
		$operationids = [];

		foreach ($db_operations as &$db_operation) {
			$db_operation['optag'] = [];

			if (in_array($db_operation['operationobject'], $tag_operationobjects)) {
				$operationids[] = $db_operation['lld_override_operationid'];
			}
		}
		unset($db_operation);

		if (!$operationids) {
			return;
		}

		$options = [
			'output' => ['lld_override_optagid', 'lld_override_operationid', 'tag', 'value'],
			'filter' => ['lld_override_operationid' => $operationids]
		];
		$db_tags = DBselect(DB::makeSql('lld_override_optag', $options));

		while ($db_tag = DBfetch($db_tags)) {
			$db_operations[$db_tag['lld_override_operationid']]['optag'][$db_tag['lld_override_optagid']] =
				array_diff_key($db_tag, array_flip(['lld_override_operationid']));
		}
	}

	/**
	 * @param array $db_operations
	 */
	private static function addAffectedOverrideOperationTemplates(array &$db_operations): void {
		$operationids = [];

		foreach ($db_operations as &$db_operation) {
			$db_operation['optemplate'] = [];

			if ($db_operation['operationobject'] == OPERATION_OBJECT_HOST_PROTOTYPE) {
				$operationids[] = $db_operation['lld_override_operationid'];
			}
		}
		unset($db_operation);

		if (!$operationids) {
			return;
		}

		$options = [
			'output' => ['lld_override_optemplateid', 'lld_override_operationid', 'templateid'],
			'filter' => ['lld_override_operationid' => $operationids]
		];
		$db_templates = DBselect(DB::makeSql('lld_override_optemplate', $options));

		while ($db_template = DBfetch($db_templates)) {
			$db_operations[$db_template['lld_override_operationid']]['optemplate'][$db_template['lld_override_optemplateid']] =
				array_diff_key($db_template, array_flip(['lld_override_operationid']));
		}
	}

	/**
	 * Check if lifetime value is greater than enabled_lifetime value.
	 *
	 * @param array $items
	 */
	private static function checkLifetimeFields(array $items): void {
		foreach ($items as $i => $item) {
			if ($item['lifetime_type'] != ZBX_LLD_DELETE_AFTER
					|| $item['enabled_lifetime_type'] != ZBX_LLD_DISABLE_AFTER || $item['lifetime'][0] === '{'
					|| $item['enabled_lifetime'][0] === '{') {
				continue;
			}

			$item['lifetime'] = timeUnitToSeconds($item['lifetime']);
			$item['enabled_lifetime'] = timeUnitToSeconds($item['enabled_lifetime']);

			if ($item['lifetime'] == 0 && $item['enabled_lifetime'] == 0) {
				continue;
			}

			if ($item['enabled_lifetime'] >= $item['lifetime']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1).'/enabled_lifetime',
						_s('cannot be greater than or equal to the value of parameter "%1$s"', '/'.($i + 1).'/lifetime')
					)
				);
			}
		}
	}

	/**
	 * Check that all constants of formula are specified in the filter conditions of the given LLD rules or overrides.
	 *
	 * @param array  $objects
	 * @param string $path
	 *
	 * @throws APIException
	 */
	private static function checkFilterFormula(array $objects, string $path = '/'): void {
		$condition_formula_parser = new CConditionFormula();

		foreach ($objects as $i => $object) {
			if (!array_key_exists('filter', $object)
					|| $object['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
				continue;
			}

			$condition_formula_parser->parse($object['filter']['formula']);

			$constants = array_unique(array_column($condition_formula_parser->constants, 'value'));
			$subpath = ($path === '/' ? $path : $path.'/').($i + 1).'/filter';

			$condition_formulaids = array_column($object['filter']['conditions'], 'formulaid');

			foreach ($constants as $constant) {
				if (!in_array($constant, $condition_formulaids)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $subpath.'/formula',
						_s('missing filter condition "%1$s"', $constant)
					));
				}
			}

			foreach ($object['filter']['conditions'] as $j => $condition) {
				if (!in_array($condition['formulaid'], $constants)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$subpath.'/conditions/'.($j + 1).'/formulaid', _('an identifier is not defined in the formula')
					));
				}
			}
		}
	}

	/**
	 * Check that specific checks for overrides of given LLD rules are valid.
	 *
	 * @param array $items
	 */
	private static function checkOverridesFilterFormula(array $items): void {
		foreach ($items as $i => $item) {
			if (!array_key_exists('overrides', $item)) {
				continue;
			}

			$path = '/'.($i + 1).'/overrides';

			self::checkFilterFormula($item['overrides'], $path);
		}
	}

	/**
	 * Check that templates specified in override operations of the given LLD rules are valid.
	 *
	 * @param array  $items
	 *
	 * @throws APIException
	 */
	private static function checkOverridesOperationTemplates(array $items): void {
		$template_indexes = [];

		foreach ($items as $i1 => $item) {
			if (!array_key_exists('overrides', $item)) {
				continue;
			}

			foreach ($item['overrides'] as $i2 => $override) {
				if (!array_key_exists('operations', $override)) {
					continue;
				}

				foreach ($override['operations'] as $i3 => $operation) {
					if (!array_key_exists('optemplate', $operation)) {
						continue;
					}

					foreach ($operation['optemplate'] as $i4 => $template) {
						$template_indexes[$template['templateid']][$i1][$i2][$i3] = $i4;
					}
				}
			}
		}

		if (!$template_indexes) {
			return;
		}

		$db_templates = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => array_keys($template_indexes),
			'preservekeys' => true
		]);

		$template_indexes = array_diff_key($template_indexes, $db_templates);
		$index = reset($template_indexes);

		if ($index === false) {
			return;
		}

		$i1 = key($index);
		$i2 = key($index[$i1]);
		$i3 = key($index[$i1][$i2]);
		$i4 = $index[$i1][$i2][$i3];

		self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
			'/'.($i1 + 1).'/overrides/'.($i2 + 1).'/operations/'.($i3 + 1).'/optemplate/'.($i4 + 1).'/templateid',
			_('a template ID is expected')
		));
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 */
	private static function updateForce(array &$items, array &$db_items): void {
		// Helps to avoid deadlocks.
		CArrayHelper::sort($items, ['itemid']);

		self::addFieldDefaultsByType($items, $db_items);

		$upd_items = [];
		$upd_itemids = [];

		$internal_fields = array_flip(['itemid', 'type', 'key_', 'hostid', 'flags', 'host_status']);
		$nested_object_fields = array_flip(['preprocessing', 'lld_macro_paths', 'filter', 'overrides', 'parameters']);

		self::prepareItemsForDb($items);

		foreach ($items as $i => &$item) {
			$upd_item = DB::getUpdatedValues('items', $item, $db_items[$item['itemid']]);

			if ($upd_item) {
				$upd_items[] = [
					'values' => $upd_item,
					'where' => ['itemid' => $item['itemid']]
				];

				if (array_key_exists('type', $item) && $item['type'] == ITEM_TYPE_HTTPAGENT) {
					$item = array_intersect_key($item,
						array_flip(['authtype']) + $internal_fields + $upd_item + $nested_object_fields
					);
				}
				else {
					$item = array_intersect_key($item, $internal_fields + $upd_item + $nested_object_fields);
				}

				$upd_itemids[$i] = $item['itemid'];
			}
			else {
				$item = array_intersect_key($item, $internal_fields + $nested_object_fields);
			}
		}
		unset($item);

		if ($upd_items) {
			DB::update('items', $upd_items);
		}

		self::updateParameters($items, $db_items, $upd_itemids);
		self::updatePreprocessing($items, $db_items, $upd_itemids);
		self::updateLldMacroPaths($items, $db_items, $upd_itemids);
		self::updateItemFilters($items, $db_items, $upd_itemids);
		self::updateOverrides($items, $db_items, $upd_itemids);

		$items = array_intersect_key($items, $upd_itemids);
		$db_items = array_intersect_key($db_items, array_flip($upd_itemids));

		self::prepareItemsForApi($items);
		self::prepareItemsForApi($db_items);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_LLD_RULE, $items, $db_items);
	}

	protected static function addFieldDefaultsByType(array &$items, array $db_items): void {
		parent::addFieldDefaultsByType($items, $db_items);

		self::addFieldDefaultsByLifetimeType($items, $db_items);
		self::addFieldDefaultsByEnabledLifetimeType($items, $db_items);
	}

	private static function addFieldDefaultsByLifetimeType(array &$items, array $db_items): void {
		$field_defaults = [
			'enabled_lifetime' => DB::getDefault('items', 'enabled_lifetime')
		];

		foreach ($items as &$item) {
			if (array_key_exists('lifetime_type', $item)
					&& $item['lifetime_type'] != $db_items[$item['itemid']]['lifetime_type']) {
				if ($item['lifetime_type'] != ZBX_LLD_DELETE_AFTER) {
					$item['lifetime'] = 0;
				}

				if ($item['lifetime_type'] == ZBX_LLD_DELETE_IMMEDIATELY) {
					$item += $field_defaults;
				}
			}
		}
		unset($item);
	}

	private static function addFieldDefaultsByEnabledLifetimeType(array &$items, array $db_items): void {
		$field_defaults = [
			'enabled_lifetime' => DB::getDefault('items', 'enabled_lifetime')
		];

		foreach ($items as &$item) {
			if (array_key_exists('enabled_lifetime_type', $item)
					&& $item['enabled_lifetime_type'] != $db_items[$item['itemid']]['enabled_lifetime_type']
					&& $item['enabled_lifetime_type'] != ZBX_LLD_DISABLE_AFTER) {
				$item += $field_defaults;
			}
		}
		unset($item);
	}

	/**
	 * @param array      $items
	 * @param array|null $db_items
	 * @param array|null $upd_itemids
	 */
	private static function updateLldMacroPaths(array &$items, array &$db_items = null,
			array &$upd_itemids = null): void {
		$ins_lld_macro_paths = [];
		$upd_lld_macro_paths = [];
		$del_lld_macro_pathids = [];

		foreach ($items as $i => &$item) {
			if (!array_key_exists('lld_macro_paths', $item)) {
				continue;
			}

			$changed = false;
			$db_lld_macro_paths = $db_items !== null
				? array_column($db_items[$item['itemid']]['lld_macro_paths'], null, 'lld_macro')
				: [];

			foreach ($item['lld_macro_paths'] as &$lld_macro_path) {
				if (array_key_exists($lld_macro_path['lld_macro'], $db_lld_macro_paths)) {
					$db_lld_macro_path = $db_lld_macro_paths[$lld_macro_path['lld_macro']];
					$lld_macro_path['lld_macro_pathid'] = $db_lld_macro_path['lld_macro_pathid'];
					unset($db_lld_macro_paths[$lld_macro_path['lld_macro']]);

					$upd_lld_macro_path = DB::getUpdatedValues('lld_macro_path', $lld_macro_path, $db_lld_macro_path);

					if ($upd_lld_macro_path) {
						$upd_lld_macro_paths[] = [
							'values' => $upd_lld_macro_path,
							'where' => ['lld_macro_pathid' => $db_lld_macro_path['lld_macro_pathid']]
						];
						$changed = true;
					}
				}
				else {
					$ins_lld_macro_paths[] = ['itemid' => $item['itemid']] + $lld_macro_path;
					$changed = true;
				}
			}
			unset($lld_macro_path);

			if ($db_lld_macro_paths) {
				$del_lld_macro_pathids =
					array_merge($del_lld_macro_pathids, array_column($db_lld_macro_paths, 'lld_macro_pathid'));
				$changed = true;
			}

			if ($db_items !== null) {
				if ($changed) {
					$upd_itemids[$i] = $item['itemid'];
				}
				else {
					unset($item['lld_macro_paths'], $db_items[$item['itemid']]['lld_macro_paths']);
				}
			}
		}
		unset($item);

		if ($del_lld_macro_pathids) {
			DB::delete('lld_macro_path', ['lld_macro_pathid' => $del_lld_macro_pathids]);
		}

		if ($upd_lld_macro_paths) {
			DB::update('lld_macro_path', $upd_lld_macro_paths);
		}

		if ($ins_lld_macro_paths) {
			$lld_macro_pathids = DB::insert('lld_macro_path', $ins_lld_macro_paths);
		}

		foreach ($items as &$item) {
			if (!array_key_exists('lld_macro_paths', $item)) {
				continue;
			}

			foreach ($item['lld_macro_paths'] as &$lld_macro_path) {
				if (!array_key_exists('lld_macro_pathid', $lld_macro_path)) {
					$lld_macro_path['lld_macro_pathid'] = array_shift($lld_macro_pathids);
				}
			}
			unset($lld_macro_path);
		}
		unset($item);
	}

	/**
	 * @param array      $items
	 * @param array|null $db_items
	 * @param array|null $upd_itemids
	 */
	private static function updateItemFilters(array &$items, array &$db_items = null, array &$upd_itemids = null): void {
		self::updateFilters($items, $db_items, $upd_itemids, 'items', 'item_condition');
	}

	/**
	 * @param array      $objects
	 * @param array|null $db_objects
	 * @param array|null $upd_objectids
	 * @param string     $base_table
	 * @param string     $condition_table
	 */
	private static function updateFilters(array &$objects, ?array &$db_objects, ?array &$upd_objectids,
			string $base_table, string $condition_table): void {
		$base_pk = DB::getPk($base_table);
		$condition_pk = DB::getPk($condition_table);

		$_upd_objectids = $db_objects !== null ? [] : null;

		self::updateFilterConditions($objects, $db_objects, $_upd_objectids, $base_table, $condition_table);

		$upd_objects = [];

		foreach ($objects as $i => &$object) {
			if (!array_key_exists('filter', $object)) {
				continue;
			}

			$upd_object = [];
			$changed = false;

			if ($db_objects === null
					|| $object['filter']['evaltype'] != $db_objects[$object[$base_pk]]['filter']['evaltype']) {
				$upd_object['evaltype'] = $object['filter']['evaltype'];
			}

			if ($object['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				if ($db_objects === null
						|| $object['filter']['formula'] != $db_objects[$object[$base_pk]]['filter']['formula']
						|| array_key_exists($i, $_upd_objectids)) {
					$upd_object['formula'] = $object['filter']['formula'];
					CConditionHelper::replaceFormulaIds($upd_object['formula'],
						array_column($object['filter']['conditions'], null, $condition_pk)
					);
				}
			}
			elseif ($db_objects !== null
					&& $db_objects[$object[$base_pk]]['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$upd_object['formula'] = DB::getDefault($base_table, 'formula');
				$object['filter']['formula'] = DB::getDefault($base_table, 'formula');
			}

			if ($upd_object) {
				$upd_objects[] = [
					'values' => $upd_object,
					'where' => [$base_pk => $object[$base_pk]]
				];
				$changed = true;
			}

			if ($db_objects !== null) {
				if ($changed || array_key_exists($i, $_upd_objectids)) {
					$upd_objectids[$i] = $object[$base_pk];
				}
				else {
					unset($object['filter'], $db_objects[$object[$base_pk]]['filter']);
				}
			}
		}
		unset($object);

		if ($upd_objects) {
			DB::update($base_table, $upd_objects);
		}
	}

	/**
	 * @param array      $objects
	 * @param array|null $db_objects
	 * @param array|null $upd_objectids
	 * @param string     $base_table
	 * @param string     $condition_table
	 */
	private static function updateFilterConditions(array &$objects, array &$db_objects = null,
			array &$upd_objectids = null, string $base_table, string $condition_table): void {
		$base_pk = DB::getPk($base_table);
		$condition_pk = DB::getPk($condition_table);

		$ins_conditions = [];
		$del_conditionids = [];

		foreach ($objects as $i => &$object) {
			if (!array_key_exists('filter', $object) || !array_key_exists('conditions', $object['filter'])) {
				continue;
			}

			if ($object['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				CConditionHelper::resetFormulaIds($object['filter']['formula'], $object['filter']['conditions']);
			}

			$changed = false;
			$db_conditions = $db_objects !== null ? $db_objects[$object[$base_pk]]['filter']['conditions'] : [];

			foreach ($object['filter']['conditions'] as &$condition) {
				if ($db_objects !== null
						&& $object['filter']['evaltype'] != $db_objects[$object[$base_pk]]['filter']['evaltype']
						&& $db_objects[$object[$base_pk]]['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					$condition['formulaid'] = '';
				}

				$db_conditionid = self::getConditionId($condition_table, $condition, $db_conditions);

				if ($db_conditionid !== null) {
					$condition[$condition_pk] = $db_conditionid;

					if (array_key_exists('formulaid', $condition)
							&& $condition['formulaid'] != $db_conditions[$db_conditionid]['formulaid']) {
						$changed = true;
					}

					unset($db_conditions[$db_conditionid]);

				} else {
					$ins_conditions[] = [$base_pk => $object[$base_pk]] + $condition;
					$changed = true;
				}
			}
			unset($condition);

			if ($db_conditions) {
				$del_conditionids =
					array_merge($del_conditionids, array_column($db_conditions, $condition_pk));
				$changed = true;
			}

			if ($db_objects !== null) {
				if ($changed) {
					$upd_objectids[$i] = $object[$base_pk];
				}
				elseif ($object['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
					unset($object['filter']['conditions'], $db_objects[$object[$base_pk]]['filter']['conditions']);
				}
			}
		}
		unset($object);

		if ($del_conditionids) {
			DB::delete($condition_table, [$condition_pk => $del_conditionids]);
		}

		if ($ins_conditions) {
			$conditionids = DB::insert($condition_table, $ins_conditions);
		}

		foreach ($objects as &$object) {
			if (!array_key_exists('filter', $object) || !array_key_exists('conditions', $object['filter'])) {
				continue;
			}

			foreach ($object['filter']['conditions'] as &$condition) {
				if (!array_key_exists($condition_pk, $condition)) {
					$condition[$condition_pk] = array_shift($conditionids);
				}
			}
			unset($condition);
		}
		unset($object);
	}

	/**
	 * @param string $condition_table
	 * @param array  $condition
	 * @param array  $db_conditions
	 *
	 * @return array|null
	 */
	private static function getConditionId(string $condition_table, array $condition, array $db_conditions): ?string {
		$condition += [
			'operator' => DB::getDefault($condition_table, 'operator'),
			'value' => DB::getDefault($condition_table, 'value')
		];

		$condition_pk = DB::getPk($condition_table);

		foreach ($db_conditions as $db_condition) {
			if (!DB::getUpdatedValues($condition_table, $condition, $db_condition)) {
				return $db_condition[$condition_pk];
			}
		}

		return null;
	}

	/**
	 * @param array      $items
	 * @param array|null $db_items
	 * @param array|null $upd_itemids
	 */
	private static function updateOverrides(array &$items, array &$db_items = null, array &$upd_itemids = null): void {
		$ins_overrides = [];
		$upd_overrides = [];
		$del_overrideids = [];

		$_upd_itemids = $db_items !== null ? [] : null;
		$_upd_overrides = [];

		foreach ($items as $i => &$item) {
			if (!array_key_exists('overrides', $item)) {
				continue;
			}

			$changed = false;
			$db_overrides = $db_items !== null
				? array_column($db_items[$item['itemid']]['overrides'], null, 'step')
				: [];
			$db_override_steps = $db_items !== null
				? array_column($db_items[$item['itemid']]['overrides'], 'step', 'name')
				: [];

			foreach ($item['overrides'] as &$override) {
				if (array_key_exists($override['step'], $db_overrides)) {
					$db_override = $db_overrides[$override['step']];
					$override['lld_overrideid'] = $db_override['lld_overrideid'];
					unset($db_overrides[$override['step']]);

					$upd_override = DB::getUpdatedValues('lld_override', $override, $db_override);

					if (array_key_exists($override['name'], $db_override_steps)
							&& $override['step'] != $db_override_steps[$override['name']]) {
						$_upd_overrides[] = [
							'values' => $upd_override,
							'where' => ['lld_overrideid' => $db_override['lld_overrideid']]
						];

						$upd_override = ['name' => '#'.$db_override['lld_overrideid']];
					}

					if ($upd_override) {
						$upd_overrides[] = [
							'values' => $upd_override,
							'where' => ['lld_overrideid' => $db_override['lld_overrideid']]
						];
						$changed = true;
					}
				}
				else {
					$ins_overrides[] = ['itemid' => $item['itemid']] + $override;
					$changed = true;
				}
			}
			unset($override);

			if ($db_overrides) {
				$del_overrideids = array_merge($del_overrideids, array_column($db_overrides, 'lld_overrideid'));
				$changed = true;
			}

			if ($db_items !== null && $changed) {
				$_upd_itemids[$i] = $item['itemid'];
			}
		}
		unset($item);

		if ($del_overrideids) {
			self::deleteOverrides($del_overrideids);
		}

		if ($upd_overrides) {
			DB::update('lld_override', array_merge($upd_overrides, $_upd_overrides));
		}

		if ($ins_overrides) {
			$overrideids = DB::insert('lld_override', $ins_overrides);
		}

		$overrides = [];
		$db_overrides = null;
		$upd_overrideids = null;

		if ($db_items !== null) {
			$db_overrides = [];
			$upd_overrideids = [];
			$item_indexes = [];
		}

		foreach ($items as $i => &$item) {
			if (!array_key_exists('overrides', $item)) {
				continue;
			}

			foreach ($item['overrides'] as &$override) {
				if (!array_key_exists('lld_overrideid', $override)) {
					$override['lld_overrideid'] = array_shift($overrideids);

					if ($db_items !== null) {
						$db_overrides[$override['lld_overrideid']] = [
							'lld_overrideid' => $override['lld_overrideid']
						];

						if (array_key_exists('filter', $override)) {
							$db_overrides[$override['lld_overrideid']]['filter'] = [
								'evaltype' => DB::getDefault('lld_override', 'evaltype'),
								'formula' => DB::getDefault('lld_override', 'formula'),
								'conditions' => []
							];
						}

						if (array_key_exists('operations', $override)) {
							$db_overrides[$override['lld_overrideid']]['operations'] = [];
						}
					}
				}
				else {
					$db_overrides[$override['lld_overrideid']] =
						$db_items[$item['itemid']]['overrides'][$override['lld_overrideid']];
				}

				$overrides[] = &$override;

				if ($db_items !== null) {
					$item_indexes[] = $i;
				}
			}
			unset($override);
		}
		unset($item);

		if ($overrides) {
			self::updateOverrideFilters($overrides, $db_overrides, $upd_overrideids);
			self::updateOverrideOperations($overrides, $db_overrides, $upd_overrideids);
		}

		if ($db_items !== null) {
			foreach (array_unique(array_intersect_key($item_indexes, $upd_overrideids)) as $i) {
				$_upd_itemids[$i] = $items[$i]['itemid'];
			}

			foreach ($items as $i => &$item) {
				if (!array_key_exists($i, $_upd_itemids)) {
					unset($item['overrides'], $db_items[$item['itemid']]['overrides']);
				}
			}
			unset($item);

			$upd_itemids += $_upd_itemids;
		}
	}

	/**
	 * @param array $del_overrideids
	 */
	private static function deleteOverrides(array $del_overrideids): void {
		DB::delete('lld_override_condition', ['lld_overrideid' => $del_overrideids]);

		$options = [
			'output' => ['lld_override_operationid'],
			'filter' => ['lld_overrideid' => $del_overrideids]
		];
		$del_operationids =
			DBfetchColumn(DBselect(DB::makeSql('lld_override_operation', $options)), 'lld_override_operationid');

		self::deleteOverrideOperations($del_operationids);

		DB::delete('lld_override', ['lld_overrideid' => $del_overrideids]);
	}

	/**
	 * @param array      $overrides
	 * @param array|null $db_overrides
	 * @param array|null $upd_overrideids
	 */
	private static function updateOverrideFilters(array &$overrides, ?array &$db_overrides,
			?array &$upd_overrideids): void {
		self::updateFilters($overrides, $db_overrides, $upd_overrideids, 'lld_override', 'lld_override_condition');
	}

	/**
	 * @param array      $overrides
	 * @param array|null $db_overrides
	 * @param array|null $upd_overrideids
	 */
	private static function updateOverrideOperations(array &$overrides, ?array &$db_overrides,
			?array &$upd_overrideids): void {
		$ins_operations = [];
		$del_operationids = [];

		$_upd_overrideids = $db_overrides !== null ? [] : null;

		foreach ($overrides as $i => &$override) {
			if (!array_key_exists('operations', $override)) {
				continue;
			}

			$changed = false;
			$db_operations = $db_overrides !== null ? $db_overrides[$override['lld_overrideid']]['operations'] : [];

			foreach ($override['operations'] as &$operation) {
				self::setOperationId($operation, $db_operations);

				if (array_key_exists('lld_override_operationid', $operation)) {
					unset($db_operations[$operation['lld_override_operationid']]);
				}
				else {
					$ins_operations[] = ['lld_overrideid' => $override['lld_overrideid']] + $operation;
					$changed = true;
				}
			}
			unset($operation);

			if ($db_operations) {
				$del_operationids = array_merge($del_operationids, array_keys($db_operations));
				$changed = true;
			}

			if ($db_overrides !== null && $changed) {
				$_upd_overrideids[$i] = $override['lld_overrideid'];
			}
		}
		unset($override);

		if ($del_operationids) {
			self::deleteOverrideOperations($del_operationids);
		}

		if ($ins_operations) {
			$operationids = DB::insert('lld_override_operation', $ins_operations);
		}

		$operations = [];
		$upd_operationids = null;

		if ($db_overrides !== null) {
			$upd_operationids = [];
			$override_indexes = [];
		}

		foreach ($overrides as $i => &$override) {
			if (!array_key_exists('operations', $override)) {
				continue;
			}

			foreach ($override['operations'] as &$operation) {
				if (!array_key_exists('lld_override_operationid', $operation)) {
					$operation['lld_override_operationid'] = array_shift($operationids);

					$operations[] = &$operation;

					if ($db_overrides !== null) {
						$override_indexes[] = $i;
					}
				}
			}
			unset($operation);
		}
		unset($override);

		if ($operations) {
			self::createOverrideOperationFields($operations, $upd_operationids);
		}

		if ($db_overrides !== null) {
			foreach (array_unique(array_intersect_key($override_indexes, $upd_operationids)) as $i) {
				$_upd_overrideids[$i] = $overrides[$i]['lld_overrideid'];
			}

			foreach ($overrides as $i => &$override) {
				if (!array_key_exists($i, $_upd_overrideids)) {
					unset($override['operations'], $db_overrides[$override['lld_overrideid']]['operations']);
				}
			}
			unset($override);

			$upd_overrideids += $_upd_overrideids;
		}
	}

	/**
	 * Set the ID of override operation if all fields of the given operation are equal to all fields of one of existing
	 * override operations.
	 *
	 * @param array $operation
	 * @param array $db_operations
	 */
	private static function setOperationId(array &$operation, array $db_operations): void {
		$_operation = $operation
			+ array_intersect_key(DB::getDefaults('lld_override_operation'), array_flip(['operator', 'value']))
			+ array_fill_keys(self::OPERATION_FIELDS, []);

		foreach ($db_operations as $db_operation) {
			if (self::operationMatches($_operation, $db_operation)) {
				$operation = $_operation;
				return;
			}
		}
	}

	/**
	 * Check whether the existing override operation in database matches the given override operation.
	 *
	 * @param array $operation
	 * @param array $db_operation
	 *
	 * @return bool
	 */
	private static function operationMatches(array &$operation, array $db_operation): bool {
		if (DB::getUpdatedValues('lld_override_operation', $operation, $db_operation)) {
			return false;
		}

		foreach (self::OPERATION_FIELDS as $optable => $opfield) {
			$pk = DB::getPk($optable);

			if ($operation[$opfield]) {
				if (in_array($opfield, ['optag', 'optemplate'])) {
					if (!$db_operation[$opfield]) {
						return false;
					}

					foreach ($operation[$opfield] as &$op) {
						foreach ($db_operation[$opfield] as $i => $db_op) {
							if (DB::getUpdatedValues($optable, $op, $db_op)) {
								continue;
							}

							unset($db_operation[$opfield][$i]);
							$op[$pk] = $db_op[$pk];
							continue 2;
						}

						return false;
					}
					unset($op);

					if ($db_operation[$opfield]) {
						return false;
					}
				}
				elseif (DB::getUpdatedValues($optable, $operation[$opfield], $db_operation[$opfield])) {
					return false;
				}
			}
			elseif ($db_operation[$opfield]) {
				return false;
			}
		}

		$operation['lld_override_operationid'] = $db_operation['lld_override_operationid'];

		return true;
	}

	/**
	 * @param array $del_operationids
	 */
	private static function deleteOverrideOperations(array $del_operationids): void {
		foreach (self::OPERATION_FIELDS as $optable => $foo) {
			DB::delete($optable, ['lld_override_operationid' => $del_operationids]);
		}

		DB::delete('lld_override_operation', ['lld_override_operationid' => $del_operationids]);
	}

	/**
	 * @param array      $operations
	 * @param array|null $upd_operationids
	 */
	private static function createOverrideOperationFields(array &$operations, ?array &$upd_operationids): void {
		foreach (self::OPERATION_FIELDS as $optable => $opfield) {
			$pk = DB::getPk($optable);

			if (in_array($opfield, ['optag', 'optemplate'])) {
				$ins_opfields = [];

				foreach ($operations as $i => $operation) {
					if (!array_key_exists($opfield, $operation) || !$operation[$opfield]) {
						continue;
					}

					foreach ($operation[$opfield] as $_opfield) {
						$ins_opfields[] =
							['lld_override_operationid' => $operation['lld_override_operationid']] + $_opfield;
					}

					if ($upd_operationids !== null) {
						$upd_operationids[$i] = $operation['lld_override_operationid'];
					}
				}

				if ($ins_opfields) {
					$opfieldids = DB::insert($optable, $ins_opfields);
				}

				foreach ($operations as &$operation) {
					if (!array_key_exists($opfield, $operation)) {
						continue;
					}

					foreach ($operation[$opfield] as &$_opfield) {
						$_opfield[$pk] = array_shift($opfieldids);
					}
					unset($_opfield);
				}
				unset($operation);
			}
			else {
				$ins_opfields = [];

				foreach ($operations as $i => &$operation) {
					if (!array_key_exists($opfield, $operation) || !$operation[$opfield]) {
						continue;
					}

					$ins_opfields[] = [$pk => $operation['lld_override_operationid']] + $operation[$opfield];

					if ($upd_operationids !== null) {
						$upd_operationids[$i] = $operation['lld_override_operationid'];
					}
				}
				unset($operation);

				if ($ins_opfields) {
					DB::insert($optable, $ins_opfields, false);
				}
			}
		}
	}

	/**
	 * @param array $templateids
	 * @param array $hostids
	 */
	public static function linkTemplateObjects(array $templateids, array $hostids): void {
		$db_items = DB::select('items', [
			'output' => array_merge(
				['itemid', 'name', 'type', 'key_', 'lifetime_type', 'lifetime', 'enabled_lifetime_type',
					'enabled_lifetime', 'description', 'status'
				],
				array_diff(CItemType::FIELD_NAMES, ['interfaceid', 'parameters'])
			),
			'filter' => [
				'hostid' => $templateids,
				'flags' => ZBX_FLAG_DISCOVERY_RULE
			],
			'preservekeys' => true
		]);

		if (!$db_items) {
			return;
		}

		self::prepareItemsForApi($db_items);
		self::addInternalFields($db_items);

		$items = [];

		foreach ($db_items as $db_item) {
			$item = array_intersect_key($db_item, array_flip(['itemid', 'type']));

			if (in_array($db_item['type'], [ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER])) {
				$item += ['parameters' => []];
			}

			$items[] = $item + [
				'preprocessing' => [],
				'lld_macro_paths' => [],
				'filter' => [],
				'overrides' => []
			];
		}

		self::addAffectedObjects($items, $db_items);

		$ruleids = array_keys($db_items);

		$items = array_values($db_items);

		foreach ($items as &$item) {
			if (array_key_exists('parameters', $item)) {
				$item['parameters'] = array_values($item['parameters']);
			}

			$item['preprocessing'] = array_values($item['preprocessing']);
			$item['lld_macro_paths'] = array_values($item['lld_macro_paths']);
			$item['filter']['conditions'] = array_values($item['filter']['conditions']);

			foreach ($item['filter']['conditions'] as &$condition) {
				if ($item['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
					unset($condition['formulaid']);
				}
			}
			unset($condition);

			foreach ($item['overrides'] as &$override) {
				foreach ($override['filter']['conditions'] as &$condition) {
					if ($override['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
						unset($condition['formulaid']);
					}
				}
				unset($condition);

				$override['filter']['conditions'] = array_values($override['filter']['conditions']);

				foreach ($override['operations'] as &$operation) {
					$operation['optag'] = array_values($operation['optag']);
					$operation['optemplate'] = array_values($operation['optemplate']);
				}
				unset($operation);

				$override['operations'] = array_values($override['operations']);
			}
			unset($override);

			$item['overrides'] = array_values($item['overrides']);
		}
		unset($item);

		self::inherit($items, [], $hostids);

		CItemPrototype::linkTemplateObjects($templateids, $hostids);
		API::TriggerPrototype()->syncTemplates(['templateids' => $templateids, 'hostids' => $hostids]);
		API::GraphPrototype()->syncTemplates(['templateids' => $templateids, 'hostids' => $hostids]);
		API::HostPrototype()->linkTemplateObjects($ruleids, $hostids);
	}

	/**
	 * @inheritDoc
	 */
	protected static function inherit(array $items, array $db_items = [], ?array $hostids = null,
			bool $is_dep_items = false): void {
		$tpl_links = self::getTemplateLinks($items, $hostids);

		if ($hostids === null) {
			self::filterObjectsToInherit($items, $db_items, $tpl_links);

			if (!$items) {
				return;
			}
		}

		self::checkDoubleInheritedNames($items, $db_items, $tpl_links);

		$chunks = self::getInheritChunks($items, $tpl_links);

		foreach ($chunks as $chunk) {
			$_items = array_intersect_key($items, array_flip($chunk['item_indexes']));
			$_db_items = array_intersect_key($db_items, array_flip(array_column($_items, 'itemid')));
			$_hostids = array_keys($chunk['hosts']);

			self::inheritChunk($_items, $_db_items, $tpl_links, $_hostids);
		}
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 * @param array $tpl_links
	 * @param array $hostids
	 */
	private static function inheritChunk(array $items, array $db_items, array $tpl_links, array $hostids): void {
		$items_to_link = [];
		$items_to_update = [];

		foreach ($items as $i => $item) {
			if (!array_key_exists($item['itemid'], $db_items)) {
				$items_to_link[] = $item;
			}
			else {
				$items_to_update[] = $item;
			}

			unset($items[$i]);
		}

		$ins_items = [];
		$upd_items = [];
		$upd_db_items = [];

		if ($items_to_link) {
			$upd_db_items = self::getChildObjectsUsingName($items_to_link, $hostids);

			if ($upd_db_items) {
				$upd_items = self::getUpdChildObjectsUsingName($items_to_link, $upd_db_items);
			}

			$ins_items = self::getInsChildObjects($items_to_link, $upd_db_items, $tpl_links, $hostids);
		}

		if ($items_to_update) {
			$_upd_db_items = self::getChildObjectsUsingTemplateid($items_to_update, $db_items, $hostids);
			$_upd_items = self::getUpdChildObjectsUsingTemplateid($items_to_update, $_upd_db_items);

			self::checkDuplicates($_upd_items, $_upd_db_items);

			$upd_items = array_merge($upd_items, $_upd_items);
			$upd_db_items += $_upd_db_items;
		}

		self::setChildMasterItemIds($upd_items, $ins_items, $hostids);

		self::checkDependentItems(array_merge($upd_items, $ins_items), $upd_db_items, true);

		self::addInterfaceIds($upd_items, $upd_db_items, $ins_items);

		if ($upd_items) {
			self::updateForce($upd_items, $upd_db_items);
		}

		if ($ins_items) {
			self::createForce($ins_items);
		}

		self::inherit(array_merge($upd_items, $ins_items), $upd_db_items);
	}

	/**
	 * @param array $items
	 * @param array $hostids
	 *
	 * @return array
	 */
	private static function getChildObjectsUsingName(array $items, array $hostids): array {
		$result = DBselect(
			'SELECT i.itemid,ht.hostid,i.key_,i.templateid,i.flags,h.status AS host_status,'.
				'ht.templateid AS parent_hostid'.
			' FROM hosts_templates ht,items i,hosts h'.
			' WHERE ht.hostid=i.hostid'.
				' AND ht.hostid=h.hostid'.
				' AND '.dbConditionId('ht.templateid', array_unique(array_column($items, 'hostid'))).
				' AND '.dbConditionString('i.key_', array_unique(array_column($items, 'key_'))).
				' AND '.dbConditionId('ht.hostid', $hostids)
		);

		$upd_db_items = [];
		$parent_indexes = [];

		while ($row = DBfetch($result)) {
			foreach ($items as $i => $item) {
				if (bccomp($row['parent_hostid'], $item['hostid']) == 0 && $row['key_'] === $item['key_']) {
					if ($row['flags'] == $item['flags'] && $row['templateid'] == 0) {
						$upd_db_items[$row['itemid']] = $row;
						$parent_indexes[$row['itemid']] = $i;
					}
					else {
						self::showObjectMismatchError($item, $row);
					}
				}
			}
		}

		if (!$upd_db_items) {
			return [];
		}

		$options = [
			'output' => array_merge(
				['uuid', 'itemid', 'name', 'type', 'key_', 'lifetime_type', 'lifetime', 'enabled_lifetime_type',
					'enabled_lifetime', 'description', 'status'
				],
				array_diff(CItemType::FIELD_NAMES, ['parameters'])
			),
			'itemids' => array_keys($upd_db_items)
		];
		$result = DBselect(DB::makeSql('items', $options));

		while ($row = DBfetch($result)) {
			$upd_db_items[$row['itemid']] = $row + $upd_db_items[$row['itemid']];
		}

		$upd_items = [];

		foreach ($upd_db_items as $upd_db_item) {
			$item = $items[$parent_indexes[$upd_db_item['itemid']]];

			$upd_items[] = [
				'itemid' => $upd_db_item['itemid'],
				'type' => $item['type'],
				'preprocessing' => [],
				'lld_macro_paths' => [],
				'filter' => [],
				'overrides' => [],
				'parameters' => []
			];
		}

		self::addAffectedObjects($upd_items, $upd_db_items);

		return $upd_db_items;
	}

	/**
	 * @param array $items
	 * @param array $upd_db_items
	 *
	 * @return array
	 */
	private static function getUpdChildObjectsUsingName(array $items, array $upd_db_items): array {
		$parent_indexes = [];

		foreach ($items as $i => &$item) {
			$item['uuid'] = '';
			$item = self::unsetNestedObjectIds($item);

			$parent_indexes[$item['hostid']][$item['key_']] = $i;
		}
		unset($item);

		$upd_items = [];

		foreach ($upd_db_items as $upd_db_item) {
			$item = $items[$parent_indexes[$upd_db_item['parent_hostid']][$upd_db_item['key_']]];

			$upd_item = [
				'itemid' => $upd_db_item['itemid'],
				'hostid' => $upd_db_item['hostid'],
				'templateid' => $item['itemid'],
				'host_status' => $upd_db_item['host_status']
			] + $item;

			$upd_item += [
				'preprocessing' => [],
				'lld_macro_paths' => [],
				'filter' => [],
				'overrides' => [],
				'parameters' => []
			];

			$upd_items[] = $upd_item;
		}

		return $upd_items;
	}

	/**
	 * @param array $items
	 * @param array $upd_db_items
	 * @param array $tpl_links
	 * @param array $hostids
	 *
	 * @return array
	 */
	private static function getInsChildObjects(array $items, array $upd_db_items, array $tpl_links,
			array $hostids): array {
		$ins_items = [];

		$upd_item_keys = [];

		foreach ($upd_db_items as $upd_db_item) {
			$upd_item_keys[$upd_db_item['hostid']][] = $upd_db_item['key_'];
		}

		foreach ($items as $item) {
			$item['uuid'] = '';
			$item = self::unsetNestedObjectIds($item);

			foreach ($tpl_links[$item['hostid']] as $host) {
				if (!in_array($host['hostid'], $hostids)
						|| (array_key_exists($host['hostid'], $upd_item_keys)
							&& in_array($item['key_'], $upd_item_keys[$host['hostid']]))) {
					continue;
				}

				$ins_items[] = [
					'hostid' => $host['hostid'],
					'templateid' => $item['itemid'],
					'host_status' => $host['status']
				] + array_diff_key($item, array_flip(['itemid']));
			}
		}

		return $ins_items;
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 * @param array $hostids
	 *
	 * @return array
	 */
	private static function getChildObjectsUsingTemplateid(array $items, array $db_items, array $hostids): array {
		$upd_db_items = DB::select('items', [
			'output' => array_merge(
				['itemid', 'name', 'type', 'key_', 'lifetime_type', 'lifetime', 'enabled_lifetime_type',
					'enabled_lifetime', 'description', 'status'
				],
				array_diff(CItemType::FIELD_NAMES, ['parameters'])
			),
			'filter' => [
				'templateid' => array_keys($db_items),
				'hostid' => $hostids
			],
			'preservekeys' => true
		]);

		self::addInternalFields($upd_db_items);

		if ($upd_db_items) {
			$parent_indexes = array_flip(array_column($items, 'itemid'));
			$upd_items = [];

			foreach ($upd_db_items as $upd_db_item) {
				$item = $items[$parent_indexes[$upd_db_item['templateid']]];
				$db_item = $db_items[$upd_db_item['templateid']];

				$upd_item = [
					'itemid' => $upd_db_item['itemid'],
					'type' => $item['type']
				];

				$upd_item += array_intersect_key([
					'preprocessing' => [],
					'lld_macro_paths' => [],
					'filter' => [],
					'overrides' => [],
					'parameters' => []
				], $db_item);

				$upd_items[] = $upd_item;
			}

			self::addAffectedObjects($upd_items, $upd_db_items);
		}

		return $upd_db_items;
	}

	/**
	 * @param array $items
	 * @param array $upd_db_items
	 *
	 * @return array
	 */
	private static function getUpdChildObjectsUsingTemplateid(array $items, array $upd_db_items): array {
		$parent_indexes = array_flip(array_column($items, 'itemid'));

		foreach ($items as &$item) {
			unset($item['uuid']);
			$item = self::unsetNestedObjectIds($item);
		}
		unset($item);

		$upd_items = [];

		foreach ($upd_db_items as $upd_db_item) {
			$item = $items[$parent_indexes[$upd_db_item['templateid']]];

			$upd_items[] = array_intersect_key($upd_db_item,
				array_flip(['itemid', 'hostid', 'templateid', 'host_status'])
			) + $item;
		}

		return $upd_items;
	}

	/**
	 * @param array $item
	 *
	 * @return array
	 */
	protected static function unsetNestedObjectIds(array $item): array {
		$item = parent::unsetNestedObjectIds($item);

		if (array_key_exists('lld_macro_paths', $item)) {
			foreach ($item['lld_macro_paths'] as &$lld_macro_path) {
				unset($lld_macro_path['lld_macro_pathid']);
			}
			unset($lld_macro_path);
		}

		if (array_key_exists('filter', $item) && array_key_exists('conditions', $item['filter'])) {
			foreach ($item['filter']['conditions'] as &$condition) {
				unset($condition['item_conditionid']);
			}
			unset($condition);
		}

		if (array_key_exists('overrides', $item)) {
			foreach ($item['overrides'] as &$override) {
				unset($override['lld_overrideid']);

				if (array_key_exists('filter', $override) && array_key_exists('conditions', $override['filter'])) {
					foreach ($override['filter']['conditions'] as &$condition) {
						unset($condition['lld_override_conditionid']);
					}
					unset($condition);
				}

				if (array_key_exists('operations', $override)) {
					foreach ($override['operations'] as &$operation) {
						unset($operation['lld_override_operationid']);

						if (array_key_exists('optag', $operation)) {
							foreach ($operation['optag'] as &$optag) {
								unset($optag['lld_override_optagid']);
							}
							unset($optag);
						}

						if (array_key_exists('optemplate', $operation)) {
							foreach ($operation['optemplate'] as &$optemplate) {
								unset($optemplate['lld_override_optemplateid']);
							}
							unset($optemplate);
						}
					}
					unset($operation);
				}
			}
			unset($override);
		}

		return $item;
	}

	/**
	 * @param array $itemids
	 *
	 * @return array
	 */
	public function delete(array $itemids): array {
		$this->validateDelete($itemids, $db_items);

		self::deleteForce($db_items);

		return ['ruleids' => $itemids];
	}

	/**
	 * @param array      $itemids
	 * @param array|null $db_items
	 *
	 * @throws APIException
	 */
	private function validateDelete(array $itemids, array &$db_items = null): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $itemids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_items = $this->get([
			'output' => ['itemid', 'name', 'templateid'],
			'itemids' => $itemids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_items) != count($itemids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($itemids as $i => $itemid) {
			if ($db_items[$itemid]['templateid'] != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
					_('cannot delete inherited LLD rule')
				));
			}
		}
	}

	/**
	 * @param array $db_items
	 */
	public static function deleteForce(array $db_items): void {
		self::addInheritedItems($db_items);

		$del_itemids = array_keys($db_items);

		self::deleteAffectedItemPrototypes($del_itemids);
		self::deleteAffectedHostPrototypes($del_itemids);
		self::deleteAffectedOverrides($del_itemids);

		DB::delete('item_parameter', ['itemid' => $del_itemids]);
		DB::delete('item_preproc', ['itemid' => $del_itemids]);
		DB::delete('lld_macro_path', ['itemid' => $del_itemids]);
		DB::delete('item_condition', ['itemid' => $del_itemids]);
		DB::update('items', [
			'values' => ['templateid' => 0],
			'where' => ['itemid' => $del_itemids]
		]);
		DB::delete('items', ['itemid' => $del_itemids]);

		$ins_housekeeper = [];

		foreach ($del_itemids as $itemid) {
			$ins_housekeeper[] = [
				'tablename' => 'events',
				'field' => 'lldruleid',
				'value' => $itemid
			];
		}

		DB::insertBatch('housekeeper', $ins_housekeeper);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_LLD_RULE, $db_items);
	}

	/**
	 * Delete item prototypes which belong to the given LLD rules.
	 *
	 * @param array $del_itemids
	 */
	private static function deleteAffectedItemPrototypes(array $del_itemids): void {
		$db_items = DBfetchArrayAssoc(DBselect(
			'SELECT id.itemid,i.name'.
			' FROM item_discovery id,items i'.
			' WHERE id.itemid=i.itemid'.
				' AND '.dbConditionId('parent_itemid', $del_itemids)
		), 'itemid');

		if ($db_items) {
			CItemPrototype::deleteForce($db_items);
		}
	}

	/**
	 * Delete host prototypes which belong to the given LLD rules.
	 *
	 * @param array $del_itemids
	 */
	private static function deleteAffectedHostPrototypes(array $del_itemids): void {
		$db_host_prototypes = DBfetchArrayAssoc(DBselect(
			'SELECT hd.hostid,h.host'.
			' FROM host_discovery hd,hosts h'.
			' WHERE hd.hostid=h.hostid'.
				' AND '.dbConditionId('hd.parent_itemid', $del_itemids)
		), 'hostid');

		if ($db_host_prototypes) {
			CHostPrototype::deleteForce($db_host_prototypes);
		}
	}

	/**
	 * Delete overrides which belong to the given LLD rules.
	 *
	 * @param array $del_itemids
	 */
	private static function deleteAffectedOverrides(array $del_itemids): void {
		$del_overrideids = array_keys(DB::select('lld_override', [
			'filter' => ['itemid' => $del_itemids],
			'preservekeys' => true
		]));

		if ($del_overrideids) {
			self::deleteOverrides($del_overrideids);
		}
	}

	/**
	 * @param array      $templateids
	 * @param array|null $hostids
	 */
	public static function unlinkTemplateObjects(array $templateids, array $hostids = null): void {
		$hostids_condition = $hostids ? ' AND '.dbConditionId('ii.hostid', $hostids) : '';

		$result = DBselect(
			'SELECT ii.itemid,ii.name,ii.templateid,ii.uuid,h.status AS host_status'.
			' FROM items i,items ii,hosts h'.
			' WHERE i.itemid=ii.templateid'.
				' AND ii.hostid=h.hostid'.
				' AND '.dbConditionId('i.hostid', $templateids).
				' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_RULE]).
				$hostids_condition
		);

		$items = [];
		$db_items = [];

		while ($row = DBfetch($result)) {
			$item = [
				'itemid' => $row['itemid'],
				'templateid' => 0
			];

			if ($row['host_status'] == HOST_STATUS_TEMPLATE) {
				$item += ['uuid' => generateUuidV4()];
			}

			$items[] = $item;
			$db_items[$row['itemid']] = $row;
		}

		if ($items) {
			self::updateForce($items, $db_items);

			$itemids = array_keys($db_items);

			CItemPrototype::unlinkTemplateObjects($itemids);
			API::HostPrototype()->unlinkTemplateObjects($itemids);
		}
	}

	/**
	 * @param array      $templateids
	 * @param array|null $hostids
	 */
	public static function clearTemplateObjects(array $templateids, array $hostids = null): void {
		$hostids_condition = $hostids ? ' AND '.dbConditionId('ii.hostid', $hostids) : '';

		$db_items = DBfetchArrayAssoc(DBselect(
			'SELECT ii.itemid,ii.name'.
			' FROM items i,items ii'.
			' WHERE i.itemid=ii.templateid'.
				' AND '.dbConditionId('i.hostid', $templateids).
				' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_RULE]).
				$hostids_condition
		), 'itemid');

		if ($db_items) {
			self::deleteForce($db_items);
		}
	}
}
