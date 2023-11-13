<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * Class containing methods for operations with discovery rules.
 */
class CDiscoveryRule extends CItemGeneral {

	public const ACCESS_RULES = parent::ACCESS_RULES + [
		'copy' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	protected $tableName = 'items';
	protected $tableAlias = 'i';
	protected $sortColumns = ['itemid', 'name', 'key_', 'delay', 'type', 'status'];

	public const OUTPUT_FIELDS = ['itemid', 'type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'status',
		'trapper_hosts', 'templateid', 'params', 'ipmi_sensor', 'authtype', 'username', 'password', 'publickey',
		'privatekey', 'interfaceid', 'description', 'lifetime', 'jmx_endpoint', 'master_itemid', 'timeout', 'url',
		'query_fields', 'posts', 'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers',
		'retrieve_mode', 'request_method', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer',
		'verify_host', 'allow_traps', 'state', 'error', 'parameters'
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
		ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
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
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM hosts_groups hgg'.
					' JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', $userGroups).
				' WHERE i.hostid=hgg.hostid'.
				' GROUP BY hgg.hostid'.
				' HAVING MIN(r.permission)>'.PERM_DENY.
					' AND MAX(r.permission)>='.zbx_dbstr($permission).
				')';
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
			if (self::dbDistinct($sqlParts)) {
				$result = $this->addNclobFieldValues($options, $result);
			}

			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['hostid'], $options['output']);
			$result = $this->unsetExtraFields($result, ['name_upper']);

			foreach ($result as &$rule) {
				// unset the fields that are returned in the filter
				unset($rule['formula'], $rule['evaltype']);

				if ($options['selectFilter'] !== null) {
					$filter = $this->unsetExtraFields([$rule['filter']],
						['conditions', 'formula', 'evaltype'],
						$options['selectFilter']
					);
					$filter = reset($filter);
					if (isset($filter['conditions'])) {
						foreach ($filter['conditions'] as &$condition) {
							unset($condition['item_conditionid'], $condition['itemid']);
						}
						unset($condition);
					}

					$rule['filter'] = $filter;
				}
			}
			unset($rule);
		}

		// Decode ITEM_TYPE_HTTPAGENT encoded fields.
		foreach ($result as &$item) {
			if (array_key_exists('query_fields', $item)) {
				$query_fields = ($item['query_fields'] !== '') ? json_decode($item['query_fields'], true) : [];
				$item['query_fields'] = json_last_error() ? [] : $query_fields;
			}

			if (array_key_exists('headers', $item)) {
				$item['headers'] = $this->headersStringToArray($item['headers']);
			}

			// Option 'Convert to JSON' is not supported for discovery rule.
			unset($item['output_format']);
		}
		unset($item);

		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		$upcased_index = array_search($tableAlias.'.name_upper', $sqlParts['select']);

		if ($upcased_index !== false) {
			unset($sqlParts['select'][$upcased_index]);
		}

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
			$formulaRequested = $this->outputIsRequested('formula', $options['selectFilter']);
			$evalFormulaRequested = $this->outputIsRequested('eval_formula', $options['selectFilter']);
			$conditionsRequested = $this->outputIsRequested('conditions', $options['selectFilter']);

			$filters = [];
			foreach ($result as $rule) {
				$filters[$rule['itemid']] = [
					'evaltype' => $rule['evaltype'],
					'formula' => isset($rule['formula']) ? $rule['formula'] : ''
				];
			}

			// adding conditions
			if ($formulaRequested || $evalFormulaRequested || $conditionsRequested) {
				$conditions = DB::select('item_condition', [
					'output' => ['item_conditionid', 'macro', 'value', 'itemid', 'operator'],
					'filter' => ['itemid' => $itemIds],
					'preservekeys' => true,
					'sortfield' => ['item_conditionid']
				]);
				$relationMap = $this->createRelationMap($conditions, 'itemid', 'item_conditionid');

				$filters = $relationMap->mapMany($filters, $conditions, 'conditions');

				foreach ($filters as &$filter) {
					// in case of a custom expression - use the given formula
					if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						$formula = $filter['formula'];
					}
					// in other cases - generate the formula automatically
					else {
						// sort the conditions by macro before generating the formula
						$conditions = zbx_toHash($filter['conditions'], 'item_conditionid');
						$conditions = order_macros($conditions, 'macro');

						$formulaConditions = [];
						foreach ($conditions as $condition) {
							$formulaConditions[$condition['item_conditionid']] = $condition['macro'];
						}
						$formula = CConditionHelper::getFormula($formulaConditions, $filter['evaltype']);
					}

					// generate formulaids from the effective formula
					$formulaIds = CConditionHelper::getFormulaIds($formula);
					foreach ($filter['conditions'] as &$condition) {
						$condition['formulaid'] = $formulaIds[$condition['item_conditionid']];
					}
					unset($condition);

					// generated a letter based formula only for rules with custom expressions
					if ($formulaRequested && $filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						$filter['formula'] = CConditionHelper::replaceNumericIds($formula, $formulaIds);
					}

					if ($evalFormulaRequested) {
						$filter['eval_formula'] = CConditionHelper::replaceNumericIds($formula, $formulaIds);
					}
				}
				unset($filter);
			}

			// add filters to the result
			foreach ($result as &$rule) {
				$rule['filter'] = $filters[$rule['itemid']];
			}
			unset($rule);
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
				$conditions = DB::select('lld_override_condition', [
					'output' => ['lld_override_conditionid', 'macro', 'value', 'lld_overrideid', 'operator'],
					'filter' => ['lld_overrideid' => array_keys($overrides)],
					'sortfield' => ['lld_override_conditionid'],
					'preservekeys' => true
				]);

				$relation_map = $this->createRelationMap($conditions, 'lld_overrideid', 'lld_override_conditionid');

				foreach ($overrides as &$override) {
					$override['filter'] = [
						'evaltype' => $override['evaltype'],
						'formula' => $override['formula']
					];
					unset($override['evaltype'], $override['formula']);
				}
				unset($override);

				$overrides = $relation_map->mapMany($overrides, $conditions, 'conditions');

				foreach ($overrides as &$override) {
					$override['filter'] += ['conditions' => $override['conditions']];
					unset($override['conditions']);

					if ($override['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						$formula = $override['filter']['formula'];
					}
					else {
						$conditions = zbx_toHash($override['filter']['conditions'], 'lld_override_conditionid');
						$conditions = order_macros($conditions, 'macro');
						$formula_conditions = [];

						foreach ($conditions as $condition) {
							$formula_conditions[$condition['lld_override_conditionid']] = $condition['macro'];
						}

						$formula = CConditionHelper::getFormula($formula_conditions, $override['filter']['evaltype']);
					}

					$formulaids = CConditionHelper::getFormulaIds($formula);

					foreach ($override['filter']['conditions'] as &$condition) {
						$condition['formulaid'] = $formulaids[$condition['lld_override_conditionid']];
						unset($condition['lld_override_conditionid'], $condition['lld_overrideid']);
					}
					unset($condition);

					if ($override['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						$override['filter']['formula'] = CConditionHelper::replaceNumericIds($formula, $formulaids);
						$override['filter']['eval_formula'] = $override['filter']['formula'];
					}
					else {
						$override['filter']['eval_formula'] = CConditionHelper::replaceNumericIds($formula,
							$formulaids
						);
					}
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
			'host_status' =>		['type' => API_ANY],
			'flags' =>				['type' => API_ANY],
			'uuid' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'host_status', 'in' => implode(',', [HOST_STATUS_TEMPLATE])], 'type' => API_UUID],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'uuid'), 'unset' => true]
			]],
			'hostid' =>				['type' => API_ANY],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
			'type' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
			'key_' =>				['type' => API_ITEM_KEY, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('items', 'key_')],
			'lifetime' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'lifetime')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'preprocessing' =>		self::getPreprocessingValidationRules(),
			'lld_macro_paths' =>	self::getLldMacroPathsValidationRules(),
			'filter' =>				self::getFilterValidationRules('items', 'item_condition'),
			'overrides' =>			self::getOverridesValidationRules()
		]];

		if (!CApiInputValidator::validate($api_input_rules, $items, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::validateByType(array_keys($api_input_rules['fields']), $items);

		self::addUuid($items);

		self::checkUuidDuplicates($items);
		self::checkDuplicates($items);
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

		$itemids = DB::insert('items', $items);

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
			'itemid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
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

		/*
		 * The fields "headers" and "query_fields" in API are arrays, but it is necessary to get the values of these
		 * fields as stored in database.
		 */
		$db_items = DB::select('items', [
			'output' => array_merge(['uuid', 'itemid', 'name', 'type', 'key_', 'lifetime', 'description', 'status'],
				array_diff(CItemType::FIELD_NAMES, ['parameters'])
			),
			'itemids' => array_column($items, 'itemid'),
			'preservekeys' => true
		]);

		self::addInternalFields($db_items);

		foreach ($items as $i => &$item) {
			$db_item = $db_items[$item['itemid']];
			$item['host_status'] = $db_item['host_status'];

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

		$items = $this->extendObjectsByKey($items, $db_items, 'itemid', ['hostid', 'flags']);

		self::validateUniqueness($items);

		self::addAffectedObjects($items, $db_items);

		self::checkUuidDuplicates($items, $db_items);
		self::checkDuplicates($items, $db_items);
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
			'host_status' =>		['type' => API_ANY],
			'uuid' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'host_status', 'in' => HOST_STATUS_TEMPLATE], 'type' => API_UUID],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'uuid'), 'unset' => true]
			]],
			'itemid' =>				['type' => API_ANY],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
			'type' =>				['type' => API_INT32, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
			'key_' =>				['type' => API_ITEM_KEY, 'length' => DB::getFieldLength('items', 'key_')],
			'lifetime' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'lifetime')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'preprocessing' =>		self::getPreprocessingValidationRules(),
			'lld_macro_paths' =>	self::getLldMacroPathsValidationRules(),
			'filter' =>				self::getFilterValidationRules('items', 'item_condition'),
			'overrides' =>			self::getOverridesValidationRules()
		]];
	}

	/**
	 * @return array
	 */
	private static function getInheritedValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'host_status' =>		['type' => API_ANY],
			'uuid' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'itemid' =>				['type' => API_ANY],
			'name' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'type' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'key_' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'lifetime' =>			['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'lifetime')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'preprocessing' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'lld_macro_paths' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'filter' =>				self::getFilterValidationRules('items', 'item_condition'),
			'overrides' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED]
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

		$object_formulaids = [];

		while ($db_filter = DBfetch($db_filters)) {
			if ($db_filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$object_formulaids[$db_filter[$base_pk]] = CConditionHelper::getFormulaIds($db_filter['formula']);

				$db_filter['formula'] = CConditionHelper::replaceNumericIds($db_filter['formula'],
					$object_formulaids[$db_filter[$base_pk]]
				);
			}

			$db_objects[$db_filter[$base_pk]]['filter'] =
				array_diff_key($db_filter, array_flip([$base_pk])) + ['conditions' => []];
		}

		$options = [
			'output' => [$condition_pk, $base_pk, 'operator', 'macro', 'value'],
			'filter' => [$base_pk => array_keys($db_objects)]
		];
		$db_conditions = DBselect(DB::makeSql($condition_table, $options));

		while ($db_condition = DBfetch($db_conditions)) {
			if ($db_objects[$db_condition[$base_pk]]['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$db_condition['formulaid'] = $object_formulaids[$db_condition[$base_pk]][$db_condition[$condition_pk]];
			}
			else {
				$db_condition['formulaid'] = '';
			}

			$db_objects[$db_condition[$base_pk]]['filter']['conditions'][$db_condition[$condition_pk]] =
				array_diff_key($db_condition, array_flip([$base_pk]));
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

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_LLD_RULE, $items, $db_items);
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
					$upd_object['formula'] = CConditionHelper::replaceLetterIds($object['filter']['formula'],
						array_column($object['filter']['conditions'], $condition_pk, 'formulaid')
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
				$condition_indexes = [];

				foreach ($object['filter']['conditions'] as $j => $condition) {
					$condition_indexes[$condition['formulaid']] = $j;
				}

				$formula = CConditionHelper::replaceLetterIds($object['filter']['formula'], $condition_indexes);
				$formulaids = CConditionHelper::getFormulaIds($formula);

				$object['filter']['formula'] = CConditionHelper::replaceNumericIds($formula, $formulaids);
			}

			$changed = false;
			$db_conditions = $db_objects !== null ? $db_objects[$object[$base_pk]]['filter']['conditions'] : [];

			foreach ($object['filter']['conditions'] as $j => &$condition) {
				if ($object['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					$condition['formulaid'] = $formulaids[$j];
				}
				elseif ($db_objects !== null
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
			'output' => array_merge(['itemid', 'name', 'type', 'key_', 'lifetime', 'description', 'status'],
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

		self::addInternalFields($db_items);

		$items = [];

		foreach ($db_items as $db_item) {
			$item = array_intersect_key($db_item, array_flip(['itemid', 'type']));

			if ($db_item['type'] == ITEM_TYPE_SCRIPT) {
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

			$item['overrides'] = $item['overrides'];
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
			'output' => array_merge(['uuid', 'itemid', 'name', 'type', 'key_', 'lifetime', 'description', 'status'],
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
			'output' => array_merge(['itemid', 'name', 'type', 'key_', 'lifetime', 'description', 'status'],
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

		if (array_key_exists('filter', $item)) {
			foreach ($item['filter']['conditions'] as &$condition) {
				unset($condition['item_conditionid']);
			}
			unset($condition);
		}

		if (array_key_exists('overrides', $item)) {
			foreach ($item['overrides'] as &$override) {
				unset($override['lld_overrideid']);

				if (array_key_exists('filter', $override)) {
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

	/**
	 * @deprecated
	 *
	 * Copies the given discovery rules to the specified hosts.
	 *
	 * @param array $data
	 * @param array $data['discoveryids']  An array of item ids to be cloned.
	 * @param array $data['hostids']       An array of host ids were the items should be cloned to.
	 *
	 * @return bool
	 *
	 * @throws APIException if no discovery rule IDs or host IDs are given or
	 * the user doesn't have the necessary permissions.
	 */
	public function copy(array $data) {
		// validate data
		if (!isset($data['discoveryids']) || !$data['discoveryids']) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No discovery rule IDs given.'));
		}
		if (!isset($data['hostids']) || !$data['hostids']) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No host IDs given.'));
		}

		$this->checkHostPermissions($data['hostids']);

		// check if the given discovery rules exist
		$count = $this->get([
			'countOutput' => true,
			'itemids' => $data['discoveryids']
		]);

		if ($count != count($data['discoveryids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// copy
		foreach ($data['discoveryids'] as $discoveryid) {
			foreach ($data['hostids'] as $hostid) {
				$this->copyDiscoveryRule($discoveryid, $hostid);
			}
		}

		return true;
	}

	/**
	 * Checks if the current user has access to the given hosts and templates. Assumes the "hostid" field is valid.
	 *
	 * @param array $hostids    an array of host or template IDs
	 *
	 * @throws APIException if the user doesn't have write permissions for the given hosts.
	 */
	protected function checkHostPermissions(array $hostids) {
		if ($hostids) {
			$hostids = array_unique($hostids);

			$count = API::Host()->get([
				'countOutput' => true,
				'hostids' => $hostids,
				'editable' => true
			]);

			if ($count == count($hostids)) {
				return;
			}

			$count += API::Template()->get([
				'countOutput' => true,
				'templateids' => $hostids,
				'editable' => true
			]);

			if ($count != count($hostids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Copies the given discovery rule to the specified host.
	 *
	 * @throws APIException if the discovery rule interfaces could not be mapped
	 * to the new host interfaces.
	 *
	 * @param string $discoveryid  The ID of the discovery rule to be copied
	 * @param string $hostid       Destination host id
	 *
	 * @return bool
	 */
	protected function copyDiscoveryRule($discoveryid, $hostid) {
		// fetch discovery to clone
		$srcDiscovery = $this->get([
			'output' => array_merge(['itemid', 'hostid', 'name', 'type', 'key_', 'lifetime', 'description', 'status'],
				CItemType::FIELD_NAMES
			),
			'selectFilter' => ['evaltype', 'formula', 'conditions'],
			'selectLLDMacroPaths' => ['lld_macro', 'path'],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectOverrides' => ['name', 'step', 'stop', 'filter', 'operations'],
			'itemids' => $discoveryid,
			'preservekeys' => true
		]);
		$srcDiscovery = reset($srcDiscovery);

		// fetch source and destination hosts
		$hosts = API::Host()->get([
			'output' => ['hostid', 'host', 'name', 'status'],
			'selectInterfaces' => ['interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'details'],
			'hostids' => [$srcDiscovery['hostid'], $hostid],
			'templated_hosts' => true,
			'preservekeys' => true
		]);
		$src_host = $hosts[$srcDiscovery['hostid']];
		$dst_host = $hosts[$hostid];

		$dstDiscovery = $srcDiscovery;
		$dstDiscovery['hostid'] = $hostid;
		unset($dstDiscovery['itemid']);
		if ($dstDiscovery['filter']) {
			foreach ($dstDiscovery['filter']['conditions'] as &$condition) {
				unset($condition['itemid'], $condition['item_conditionid']);
			}
			unset($condition);
		}

		if (!$dstDiscovery['lld_macro_paths']) {
			unset($dstDiscovery['lld_macro_paths']);
		}

		if ($dstDiscovery['overrides']) {
			foreach ($dstDiscovery['overrides'] as &$override) {
				if (array_key_exists('filter', $override)) {
					if (!$override['filter']['conditions']) {
						unset($override['filter']);
					}
					unset($override['filter']['eval_formula']);
				}
			}
			unset($override);
		}
		else {
			unset($dstDiscovery['overrides']);
		}

		// if this is a plain host, map discovery interfaces
		if ($src_host['status'] != HOST_STATUS_TEMPLATE) {
			// find a matching interface
			$interface = self::findInterfaceForItem($dstDiscovery['type'], $dst_host['interfaces']);
			if ($interface) {
				$dstDiscovery['interfaceid'] = $interface['interfaceid'];
			}
			// no matching interface found, throw an error
			elseif ($interface !== false) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Cannot find host interface on "%1$s" for item key "%2$s".',
					$dst_host['name'],
					$dstDiscovery['key_']
				));
			}
		}

		// Master item should exists for LLD rule with type dependent item.
		if ($srcDiscovery['type'] == ITEM_TYPE_DEPENDENT) {
			$master_items = DBfetchArray(DBselect(
				'SELECT i1.itemid'.
				' FROM items i1,items i2'.
				' WHERE i1.key_=i2.key_'.
					' AND i1.hostid='.zbx_dbstr($dstDiscovery['hostid']).
					' AND i2.itemid='.zbx_dbstr($srcDiscovery['master_itemid'])
			));

			if (!$master_items) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_s('Discovery rule "%1$s" cannot be copied without its master item.', $srcDiscovery['name'])
				);
			}

			$dstDiscovery['master_itemid'] = $master_items[0]['itemid'];
		}

		// save new discovery
		$newDiscovery = $this->create([$dstDiscovery]);
		$dstDiscovery['itemid'] = $newDiscovery['itemids'][0];

		// copy prototypes
		$this->copyItemPrototypes($srcDiscovery['itemid'], $src_host, $dstDiscovery['itemid'], $dst_host);

		// fetch new prototypes
		$dstDiscovery['items'] = API::ItemPrototype()->get([
			'output' => ['itemid', 'key_'],
			'discoveryids' => $dstDiscovery['itemid'],
			'preservekeys' => true
		]);

		if ($dstDiscovery['items']) {
			// copy graphs
			$this->copyGraphPrototypes($srcDiscovery, $dstDiscovery);

			// copy triggers
			$this->copyTriggerPrototypes($srcDiscovery, $src_host, $dst_host);
		}

		// copy host prototypes
		$this->copyHostPrototypes($discoveryid, $dstDiscovery['itemid']);

		return true;
	}

	/**
	 * Returns the interface that best matches the given item.
	 *
	 * @param array $item_type  An item type
	 * @param array $interfaces An array of interfaces to choose from
	 *
	 * @return array|boolean    The best matching interface;
	 *							an empty array of no matching interface was found;
	 *							false, if the item does not need an interface
	 */
	private static function findInterfaceForItem($item_type, array $interfaces) {
		$type = itemTypeInterface($item_type);

		if ($type == INTERFACE_TYPE_OPT) {
			return false;
		}
		elseif ($type == INTERFACE_TYPE_ANY) {
			return self::findInterfaceByPriority($interfaces);
		}
		// the item uses a specific type of interface
		elseif ($type !== false) {
			$interface_by_type = [];

			foreach ($interfaces as $interface) {
				if ($interface['main'] == INTERFACE_PRIMARY) {
					$interface_by_type[$interface['type']] = $interface;
				}
			}

			return array_key_exists($type, $interface_by_type) ? $interface_by_type[$type] : [];
		}
		// the item does not need an interface
		else {
			return false;
		}
	}

	/**
	 * Return first main interface matched from list of preferred types, or NULL.
	 *
	 * @param array $interfaces  An array of interfaces to choose from.
	 *
	 * @return ?array
	 */
	private static function findInterfaceByPriority(array $interfaces): ?array {
		$interface_by_type = [];

		foreach ($interfaces as $interface) {
			if ($interface['main'] == INTERFACE_PRIMARY) {
				$interface_by_type[$interface['type']] = $interface;
			}
		}

		foreach (self::INTERFACE_TYPES_BY_PRIORITY as $interface_type) {
			if (array_key_exists($interface_type, $interface_by_type)) {
				return $interface_by_type[$interface_type];
			}
		}

		return null;
	}

	/**
	 * Create copies of items prototypes from the given source LLD rule to the given destination host or template.
	 *
	 * @param string $src_ruleid
	 * @param array  $src_host
	 * @param array  $src_host['interfaces']
	 * @param string $dst_ruleid
	 * @param array  $dst_host
	 * @param string $dst_host['hostid']
	 * @param string $dst_host['host']
	 * @param array  $dst_host['interfaces']
	 *
	 * @throws APIException
	 */
	private static function copyItemPrototypes(string $src_ruleid, array $src_host, string $dst_ruleid,
			array $dst_host): void {
		$src_items = API::ItemPrototype()->get([
			'output' => ['itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history', 'trends',
				'valuemapid', 'logtimefmt', 'description', 'status', 'discover',

				// Type fields.
				// The fields used for multiple item types.
				'interfaceid', 'authtype', 'username', 'password', 'params', 'timeout', 'delay', 'trapper_hosts',

				// Dependent item type specific fields.
				'master_itemid',

				// HTTP Agent item type specific fields.
				'url', 'query_fields', 'request_method', 'post_type', 'posts',
				'headers', 'status_codes', 'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy',
				'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'allow_traps',

				// IPMI item type specific fields.
				'ipmi_sensor',

				// JMX item type specific fields.
				'jmx_endpoint',

				// Script item type specific fields.
				'parameters',

				// SNMP item type specific fields.
				'snmp_oid',

				// SSH item type specific fields.
				'publickey', 'privatekey'
			],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectTags' => ['tag', 'value'],
			'discoveryids' => $src_ruleid,
			'preservekeys' => true
		]);

		if (!$src_items) {
			return;
		}

		$src_itemids = array_fill_keys(array_keys($src_items), true);
		$src_valuemapids = [];
		$src_interfaceids = [];
		$src_dep_items = [];
		$dep_itemids = [];

		foreach ($src_items as $itemid => $item) {
			if ($item['valuemapid'] != 0) {
				$src_valuemapids[$item['valuemapid']] = true;
			}

			if ($item['interfaceid'] != 0) {
				$src_interfaceids[$item['interfaceid']] = true;
			}

			if ($item['type'] == ITEM_TYPE_DEPENDENT) {
				if (array_key_exists($item['master_itemid'], $src_itemids)) {
					$src_dep_items[$item['master_itemid']][] = $item;

					unset($src_items[$itemid]);
				}
				else {
					$dep_itemids[$item['master_itemid']][] = $item['itemid'];
				}
			}
		}

		$valuemap_links = [];

		if ($src_valuemapids) {
			$src_valuemaps = API::ValueMap()->get([
				'output' => ['valuemapid', 'name'],
				'valuemapids' => array_keys($src_valuemapids)
			]);

			$dst_valuemaps = API::ValueMap()->get([
				'output' => ['valuemapid', 'hostid', 'name'],
				'hostids' => $dst_host['hostid'],
				'filter' => ['name' => array_unique(array_column($src_valuemaps, 'name'))]
			]);

			$dst_valuemapids = [];

			foreach ($dst_valuemaps as $dst_valuemap) {
				$dst_valuemapids[$dst_valuemap['name']][$dst_valuemap['hostid']] = $dst_valuemap['valuemapid'];
			}

			foreach ($src_valuemaps as $src_valuemap) {
				if (array_key_exists($src_valuemap['name'], $dst_valuemapids)) {
					foreach ($dst_valuemapids[$src_valuemap['name']] as $dst_hostid => $dst_valuemapid) {
						$valuemap_links[$src_valuemap['valuemapid']][$dst_hostid] = $dst_valuemapid;
					}
				}
			}
		}

		$interface_links = [];
		$dst_interfaceids = [];

		if ($src_interfaceids) {
			$src_interfaces = [];

			foreach ($src_host['interfaces'] as $src_interface) {
				if (array_key_exists($src_interface['interfaceid'], $src_interfaceids)) {
					$src_interfaces[$src_interface['interfaceid']] =
						array_diff_key($src_interface, array_flip(['interfaceid']));
				}
			}

			foreach ($dst_host['interfaces'] as $dst_interface) {
				$dst_interfaceid = $dst_interface['interfaceid'];
				unset($dst_interface['interfaceid']);

				foreach ($src_interfaces as $src_interfaceid => $src_interface) {
					if ($src_interface == $dst_interface) {
						$interface_links[$src_interfaceid][$dst_host['hostid']] = $dst_interfaceid;
					}
				}

				if ($dst_interface['main'] == INTERFACE_PRIMARY) {
					$dst_interfaceids[$dst_host['hostid']][$dst_interface['type']] = $dst_interfaceid;
				}
			}
		}

		$master_item_links = [];

		if ($dep_itemids) {
			$master_items = API::Item()->get([
				'output' => ['itemid', 'key_'],
				'itemids' => array_keys($dep_itemids),
				'webitems' => true
			]);

			$options = $dst_host['status'] == HOST_STATUS_TEMPLATE
				? ['templateids' => $dst_host['hostid']]
				: ['hostids' => $dst_host['hostid']];

			$dst_master_items = API::Item()->get([
				'output' => ['itemid', 'hostid', 'key_'],
				'filter' => ['key_' => array_unique(array_column($master_items, 'key_'))],
				'webitems' => true
			] + $options);

			$dst_master_itemids = [];

			foreach ($dst_master_items as $item) {
				$dst_master_itemids[$item['hostid']][$item['key_']] = $item['itemid'];
			}

			foreach ($master_items as $item) {
				if (array_key_exists($dst_host['hostid'], $dst_master_itemids)
						&& array_key_exists($item['key_'], $dst_master_itemids[$dst_host['hostid']])) {
					$master_item_links[$item['itemid']][$dst_host['hostid']] =
						$dst_master_itemids[$dst_host['hostid']][$item['key_']];
				}
				else {
					$src_itemid = reset($dep_itemids[$item['itemid']]);

					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Cannot copy item prototype with key "%1$s" without its master item with key "%2$s".',
						$src_items[$src_itemid]['key_'], $item['key_']
					));
				}
			}
		}

		do {
			$dst_items = [];

			foreach ($src_items as $src_item) {
				$dst_item = array_diff_key($src_item, array_flip(['itemid']));

				if ($src_item['valuemapid'] != 0) {
					if (array_key_exists($src_item['valuemapid'], $valuemap_links)
							&& array_key_exists($dst_host['hostid'], $valuemap_links[$src_item['valuemapid']])) {
						$dst_item['valuemapid'] = $valuemap_links[$src_item['valuemapid']][$dst_host['hostid']];
					}
					else {
						$dst_item['valuemapid'] = 0;
					}
				}

				$dst_item['interfaceid'] = 0;

				if ($src_item['interfaceid'] != 0) {
					if (array_key_exists($src_item['interfaceid'], $interface_links)
							&& array_key_exists($dst_host['hostid'], $interface_links[$src_item['interfaceid']])) {
						$dst_item['interfaceid'] = $interface_links[$src_item['interfaceid']][$dst_host['hostid']];
					}
					else {
						$type = itemTypeInterface($src_item['type']);

						if (in_array($type,
							[INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI]
						)) {
							if (array_key_exists($dst_host['hostid'], $dst_interfaceids)
									&& array_key_exists($type, $dst_interfaceids[$dst_host['hostid']])) {
								$dst_item['interfaceid'] = $dst_interfaceids[$dst_host['hostid']][$type];
							}
							else {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Cannot find host interface on "%1$s" for item prototype with key "%2$s".',
									$dst_host['host'], $src_item['key_']
								));
							}
						}
					}
				}

				if ($src_item['type'] == ITEM_TYPE_DEPENDENT) {
					$dst_item['master_itemid'] = $master_item_links[$src_item['master_itemid']][$dst_host['hostid']];
				}

				$dst_items[] = ['hostid' => $dst_host['hostid'], 'ruleid' => $dst_ruleid] + $dst_item;
			}

			$response = API::ItemPrototype()->create($dst_items);

			$_src_items = [];

			if ($src_dep_items) {
				foreach ($src_items as $src_item) {
					$dst_itemid = array_shift($response['itemids']);

					if (array_key_exists($src_item['itemid'], $src_dep_items)) {
						$master_item_links[$src_item['itemid']][$dst_host['hostid']] = $dst_itemid;

						$_src_items = array_merge($_src_items, $src_dep_items[$src_item['itemid']]);
						unset($src_dep_items[$src_item['itemid']]);
					}
				}
			}

			$src_items = $_src_items;
		} while ($src_items);
	}

	/**
	 * Copies all of the graphs from the source discovery to the target discovery rule.
	 *
	 * @throws APIException if graph saving fails
	 *
	 * @param array $srcDiscovery    The source discovery rule to copy from
	 * @param array $dstDiscovery    The target discovery rule to copy to
	 *
	 * @return array
	 */
	protected function copyGraphPrototypes(array $srcDiscovery, array $dstDiscovery) {
		// fetch source graphs
		$srcGraphs = API::GraphPrototype()->get([
			'output' => ['graphid', 'name', 'width', 'height', 'yaxismin', 'yaxismax', 'show_work_period',
				'show_triggers', 'graphtype', 'show_legend', 'show_3d', 'percent_left', 'percent_right',
				'ymin_type', 'ymax_type', 'ymin_itemid', 'ymax_itemid', 'discover'
			],
			'selectGraphItems' => ['itemid', 'drawtype', 'sortorder', 'color', 'yaxisside', 'calc_fnc', 'type'],
			'selectHosts' => ['hostid'],
			'discoveryids' => $srcDiscovery['itemid'],
			'preservekeys' => true
		]);

		if (!$srcGraphs) {
			return [];
		}

		$srcItemIds = [];
		foreach ($srcGraphs as $key => $graph) {
			// skip graphs with items from multiple hosts
			if (count($graph['hosts']) > 1) {
				unset($srcGraphs[$key]);
				continue;
			}

			// skip graphs with http items
			if (httpItemExists($graph['gitems'])) {
				unset($srcGraphs[$key]);
				continue;
			}

			// save all used item ids to map them to the new items
			foreach ($graph['gitems'] as $item) {
				$srcItemIds[$item['itemid']] = $item['itemid'];
			}
			if ($graph['ymin_itemid']) {
				$srcItemIds[$graph['ymin_itemid']] = $graph['ymin_itemid'];
			}
			if ($graph['ymax_itemid']) {
				$srcItemIds[$graph['ymax_itemid']] = $graph['ymax_itemid'];
			}
		}

		// fetch source items
		$items = API::Item()->get([
			'output' => ['itemid', 'key_'],
			'webitems' => true,
			'itemids' => $srcItemIds,
			'filter' => ['flags' => null],
			'preservekeys' => true
		]);

		$srcItems = [];
		$itemKeys = [];
		foreach ($items as $item) {
			$srcItems[$item['itemid']] = $item;
			$itemKeys[$item['key_']] = $item['key_'];
		}

		// fetch newly cloned items
		$newItems = API::Item()->get([
			'output' => ['itemid', 'key_'],
			'webitems' => true,
			'hostids' => $dstDiscovery['hostid'],
			'filter' => [
				'key_' => $itemKeys,
				'flags' => null
			],
			'preservekeys' => true
		]);

		$items = array_merge($dstDiscovery['items'], $newItems);
		$dstItems = [];
		foreach ($items as $item) {
			$dstItems[$item['key_']] = $item;
		}

		$dstGraphs = $srcGraphs;
		foreach ($dstGraphs as &$graph) {
			unset($graph['graphid']);

			foreach ($graph['gitems'] as &$gitem) {
				// replace the old item with the new one with the same key
				$item = $srcItems[$gitem['itemid']];
				$gitem['itemid'] = $dstItems[$item['key_']]['itemid'];
			}
			unset($gitem);

			// replace the old axis items with the new one with the same key
			if ($graph['ymin_itemid']) {
				$yMinSrcItem = $srcItems[$graph['ymin_itemid']];
				$graph['ymin_itemid'] = $dstItems[$yMinSrcItem['key_']]['itemid'];
			}
			if ($graph['ymax_itemid']) {
				$yMaxSrcItem = $srcItems[$graph['ymax_itemid']];
				$graph['ymax_itemid'] = $dstItems[$yMaxSrcItem['key_']]['itemid'];
			}
		}
		unset($graph);

		// save graphs
		$rs = API::GraphPrototype()->create($dstGraphs);
		if (!$rs) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot clone graph prototypes.'));
		}

		return $rs;
	}

	/**
	 * Copies all of the triggers from the source discovery to the target discovery rule.
	 *
	 * @param array  $src_discovery       The source discovery rule to copy from.
	 * @param array  $src_host            The host the source discovery belongs to.
	 * @param string $src_host['hostid']
	 * @param string $src_host['host']
	 * @param array  $dst_host            The host the target discovery belongs to.
	 * @param string $dst_host['hostid']
	 * @param string $dst_host['host']
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	protected function copyTriggerPrototypes(array $src_discovery, array $src_host, array $dst_host): array {
		$src_triggers = API::TriggerPrototype()->get([
			'output' => ['triggerid', 'expression', 'description', 'url_name', 'url', 'status', 'priority', 'comments',
				'type', 'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag', 'opdata',
				'discover', 'event_name'
			],
			'selectItems' => ['itemid', 'type'],
			'selectTags' => ['tag', 'value'],
			'selectDependencies' => ['triggerid'],
			'discoveryids' => $src_discovery['itemid']
		]);

		$dst_triggers = [];

		foreach ($src_triggers as $i => $src_trigger) {
			// Skip trigger prototypes with web items and remove them from source.
			if (httpItemExists($src_trigger['items'])) {
				unset($src_triggers[$i]);
			}
			else {
				$dst_triggers[] = array_intersect_key($src_trigger, array_flip(['expression', 'description', 'url_name',
					'url', 'status', 'priority', 'comments','type', 'recovery_mode', 'recovery_expression',
					'correlation_mode', 'correlation_tag', 'opdata', 'discover', 'event_name', 'tags'
				]));
			}
		}

		if (!$dst_triggers) {
			return [];
		}

		$src_triggers = array_values($src_triggers);

		$dst_triggers = CMacrosResolverHelper::resolveTriggerExpressions($dst_triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		foreach ($dst_triggers as &$trigger) {
			$trigger['expression'] = CTriggerGeneralHelper::getExpressionWithReplacedHost(
				$trigger['expression'], $src_host['host'], $dst_host['host']
			);

			if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
				$trigger['recovery_expression'] = CTriggerGeneralHelper::getExpressionWithReplacedHost(
					$trigger['recovery_expression'], $src_host['host'], $dst_host['host']
				);
			}
		}
		unset($trigger);

		$result = API::TriggerPrototype()->create($dst_triggers);

		if (!$result) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot clone trigger prototypes.'));
		}

		$dst_triggerids = $result['triggerids'];
		$src_trigger_indexes = array_flip(array_column($src_triggers, 'triggerid'));

		$dst_triggers = [];

		/*
		 * A check that the trigger-up belongs to the source host needs to be performed on copying the dependencies
		 * on triggers.
		 * If it does, we need to check that the triggers with the same description and expression exist on the
		 * destination host.
		 * If not, we need to check if the dependencies from destination triggers to these triggers are valid.
		 */
		$src_triggerids_up = [];

		foreach ($dst_triggerids as $i => $dst_triggerid) {
			if (!$src_triggers[$i]['dependencies']) {
				unset($dst_triggerids[$i]);
				continue;
			}

			$dst_triggers[$dst_triggerid] = ['triggerid' => $dst_triggerid];

			foreach ($src_triggers[$i]['dependencies'] as $i2 => $src_trigger_up) {
				if (array_key_exists($src_trigger_up['triggerid'], $src_trigger_indexes)) {
					// Add dependency on the trigger prototype of the same LLD rule.
					$dst_triggers[$dst_triggerid]['dependencies'][] =
						['triggerid' => $result['triggerids'][$src_trigger_indexes[$src_trigger_up['triggerid']]]];

					unset($src_triggers[$i]['dependencies'][$i2]);
				}
				else {
					$src_triggerids_up[$src_trigger_up['triggerid']] = true;
				}
			}

			if (!$src_triggers[$i]['dependencies']) {
				unset($dst_triggerids[$i]);
			}
		}

		if ($src_triggerids_up) {
			$src_host_triggers_up = DBfetchArrayAssoc(DBselect(
				'SELECT DISTINCT t.triggerid,t.description,t.expression,t.recovery_expression'.
				' FROM triggers t,functions f,items i'.
				' WHERE t.triggerid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND '.dbConditionId('t.triggerid', array_keys($src_triggerids_up)).
					' AND '.dbConditionId('i.hostid', [$src_host['hostid']])
			), 'triggerid');

			$src_host_triggers_up = CMacrosResolverHelper::resolveTriggerExpressions($src_host_triggers_up,
				['sources' => ['expression', 'recovery_expression']]
			);

			$src_host_dependencies = [];
			$other_host_dependencies = [];

			foreach ($dst_triggerids as $i => $dst_triggerid) {
				$src_trigger = $src_triggers[$i];

				foreach ($src_trigger['dependencies'] as $src_trigger_up) {
					if (array_key_exists($src_trigger_up['triggerid'], $src_host_triggers_up)) {
						$src_host_dependencies[$src_trigger_up['triggerid']][$src_trigger['triggerid']] = true;
					}
					else {
						// Add dependency on the trigger of the other templates or hosts.
						$dst_triggers[$dst_triggerid]['dependencies'][] = ['triggerid' => $src_trigger_up['triggerid']];
						$other_host_dependencies[$src_trigger_up['triggerid']][$dst_triggerid] = true;
					}
				}
			}

			if ($src_host_dependencies) {
				$dst_host_triggers = DBfetchArrayAssoc(DBselect(
					'SELECT DISTINCT t.triggerid,t.description,t.expression,t.recovery_expression'.
					' FROM items i,functions f,triggers t'.
					' WHERE i.itemid=f.itemid'.
						' AND f.triggerid=t.triggerid'.
						' AND '.dbConditionId('i.hostid', [$dst_host['hostid']]).
						' AND '.dbConditionString('t.description',
							array_unique(array_column($src_host_triggers_up, 'description'))
						)
				), 'triggerid');

				$dst_host_triggers = CMacrosResolverHelper::resolveTriggerExpressions($dst_host_triggers);

				$dst_host_triggerids = [];

				foreach ($dst_host_triggers as $i => $trigger) {
					$expression = CTriggerGeneralHelper::getExpressionWithReplacedHost(
						$trigger['expression'], $dst_host['host'], $src_host['host']
					);
					$recovery_expression = $trigger['recovery_expression'];

					if ($recovery_expression !== '') {
						$recovery_expression = CTriggerGeneralHelper::getExpressionWithReplacedHost(
							$trigger['recovery_expression'], $dst_host['host'], $src_host['host']
						);
					}

					$dst_host_triggerids[$trigger['description']][$expression][$recovery_expression] =
						$trigger['triggerid'];
				}

				foreach ($src_host_triggers_up as $src_trigger_up) {
					$description = $src_trigger_up['description'];
					$expression = $src_trigger_up['expression'];
					$recovery_expression = $src_trigger_up['recovery_expression'];

					if (array_key_exists($description, $dst_host_triggerids)
							&& array_key_exists($expression, $dst_host_triggerids[$description])
							&& array_key_exists($recovery_expression, $dst_host_triggerids[$description][$expression])) {
						$dst_triggerid_up = $dst_host_triggerids[$description][$expression][$recovery_expression];

						foreach ($src_host_dependencies[$src_trigger_up['triggerid']] as $src_triggerid => $foo) {
							$dst_triggerid = $dst_triggerids[$src_trigger_indexes[$src_triggerid]];

							$dst_triggers[$dst_triggerid]['dependencies'][] = ['triggerid' => $dst_triggerid_up];
						}
					}
					else {
						$src_triggerid = key($src_host_dependencies[$src_trigger_up['triggerid']]);
						$src_trigger = $src_triggers[$src_trigger_indexes[$src_triggerid]];

						$hosts = DB::select('hosts', [
							'output' => ['status'],
							'hostids' => $dst_host['hostid']
						]);

						$error = ($hosts[0]['status'] == HOST_STATUS_TEMPLATE)
							? _('Trigger prototype "%1$s" cannot depend on the non-existent trigger "%2$s" on the template "%3$s".')
							: _('Trigger prototype "%1$s" cannot depend on the non-existent trigger "%2$s" on the host "%3$s".');

						self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $src_trigger['description'],
							$src_trigger_up['description'], $dst_host['host']
						));
					}
				}
			}

			if ($other_host_dependencies) {
				$trigger_hosts = CTriggerGeneral::getTriggerHosts($other_host_dependencies);

				CTriggerGeneral::checkDependenciesOfHostTriggers($other_host_dependencies, $trigger_hosts);
				CTriggerGeneral::checkDependenciesOfTemplateTriggers($other_host_dependencies, $trigger_hosts);
			}
		}

		if ($dst_triggers) {
			$dst_triggers = array_values($dst_triggers);
			CTriggerGeneral::updateDependencies($dst_triggers);
		}

		return $result;
	}

	/**
	 * Copy all of the host prototypes from the source discovery rule to the target discovery rule.
	 *
	 * @param string $src_discoveryid
	 * @param string $dst_discoveryid
	 *
	 * @throws APIException
	 */
	protected function copyHostPrototypes(string $src_discoveryid, string $dst_discoveryid): void {
		$src_host_prototypes = API::HostPrototype()->get([
			'output' => ['host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode'],
			'selectInterfaces' => ['type', 'useip', 'ip', 'dns', 'port', 'main', 'details'],
			'selectGroupLinks' => ['groupid'],
			'selectGroupPrototypes' => ['name'],
			'selectTemplates' => ['templateid'],
			'selectTags' => ['tag', 'value'],
			'selectMacros' => ['macro', 'type', 'value', 'description'],
			'discoveryids' => $src_discoveryid
		]);

		if (!$src_host_prototypes) {
			return;
		}

		$dst_host_prototypes = [];

		foreach ($src_host_prototypes as $i => $src_host_prototype) {
			unset($src_host_prototypes[$i]);

			$dst_host_prototype = ['ruleid' => $dst_discoveryid] + array_intersect_key($src_host_prototype, array_flip([
				'host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode', 'groupLinks',
				'groupPrototypes', 'templates', 'tags'
			]));

			if ($src_host_prototype['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
				foreach ($src_host_prototype['interfaces'] as $src_interface) {
					$dst_interface =
						array_intersect_key($src_interface, array_flip(['type', 'useip', 'ip', 'dns', 'port', 'main']));

					if ($src_interface['type'] == INTERFACE_TYPE_SNMP) {
						switch ($src_interface['details']['version']) {
							case SNMP_V1:
							case SNMP_V2C:
								$dst_interface['details'] = array_intersect_key($src_interface['details'],
									array_flip(['version', 'bulk', 'community'])
								);
								break;

							case SNMP_V3:
								$field_names = array_flip(['version', 'bulk', 'contextname', 'securityname',
									'securitylevel'
								]);

								if ($src_interface['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
									$field_names += array_flip(['authprotocol', 'authpassphrase']);
								}
								elseif ($src_interface['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
									$field_names +=
										array_flip(['authprotocol', 'authpassphrase', 'privprotocol', 'privpassphrase']);
								}

								$dst_interface['details'] = array_intersect_key($src_interface['details'], $field_names);
								break;
						}
					}

					$dst_host_prototype['interfaces'][] = $dst_interface;
				}
			}

			foreach ($src_host_prototype['macros'] as $src_macro) {
				if ($src_macro['type'] == ZBX_MACRO_TYPE_SECRET) {
					$dst_host_prototype['macros'][] = ['type' => ZBX_MACRO_TYPE_TEXT, 'value' => ''] + $src_macro;
				}
				else {
					$dst_host_prototype['macros'][] = $src_macro;
				}
			}

			$dst_host_prototypes[] = $dst_host_prototype;
		}

		API::HostPrototype()->create($dst_host_prototypes);
	}
}
