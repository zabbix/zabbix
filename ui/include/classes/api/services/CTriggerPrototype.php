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
 * Class containing methods for operations with trigger prototypes.
 */
class CTriggerPrototype extends CTriggerGeneral {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	protected const FLAGS = ZBX_FLAG_DISCOVERY_PROTOTYPE;

	protected $tableName = 'triggers';
	protected $tableAlias = 't';
	protected $sortColumns = ['triggerid', 'description', 'status', 'priority', 'discover'];

	/**
	 * Get trigger prototypes from database.
	 *
	 * @param array $options
	 *
	 * @return array|int
	 */
	public function get(array $options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['triggers' => 't.triggerid'],
			'from'		=> ['t' => 'triggers t'],
			'where'		=> ['t.flags='.ZBX_FLAG_DISCOVERY_PROTOTYPE],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'						=> null,
			'templateids'					=> null,
			'hostids'						=> null,
			'triggerids'					=> null,
			'itemids'						=> null,
			'discoveryids'					=> null,
			'functions'						=> null,
			'inherited'						=> null,
			'templated'						=> null,
			'monitored' 					=> null,
			'active' 						=> null,
			'maintenance'					=> null,
			'nopermissions'					=> null,
			'editable'						=> false,
			// filter
			'group'							=> null,
			'host'							=> null,
			'min_severity'					=> null,
			'filter'						=> null,
			'search'						=> null,
			'searchByAny'					=> null,
			'startSearch'					=> false,
			'excludeSearch'					=> false,
			'searchWildcardsEnabled'		=> null,
			// output
			'expandExpression'				=> null,
			'output'						=> API_OUTPUT_EXTEND,
			'selectHostGroups'				=> null,
			'selectTemplateGroups'			=> null,
			'selectHosts'					=> null,
			'selectItems'					=> null,
			'selectFunctions'				=> null,
			'selectDependencies'			=> null,
			'selectDiscoveryRule'			=> null,
			'selectTags'					=> null,
			'countOutput'					=> false,
			'groupCount'					=> false,
			'preservekeys'					=> false,
			'sortfield'						=> '',
			'sortorder'						=> '',
			'limit'							=> null,
			'limitSelects'					=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + permission check
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if (self::$userData['ugsetid'] == 0) {
				return $options['countOutput'] ? '0' : [];
			}

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from'][] = 'host_hgset hh';
			$sqlParts['from'][] = 'permission p';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where'][] = 'i.hostid=hh.hostid';
			$sqlParts['where'][] = 'hh.hgsetid=p.hgsetid';
			$sqlParts['where'][] = 'p.ugsetid='.self::$userData['ugsetid'];

			if ($options['editable']) {
				$sqlParts['where'][] = 'p.permission='.PERM_READ_WRITE;
			}

			$sqlParts['where'][] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM functions f1'.
				' JOIN items i1 ON f1.itemid=i1.itemid'.
				' JOIN host_hgset hh1 ON i1.hostid=hh1.hostid'.
				' LEFT JOIN permission p1 ON hh1.hgsetid=p1.hgsetid'.
					' AND p1.ugsetid=p.ugsetid'.
				' WHERE t.triggerid=f1.triggerid'.
					' AND p1.permission IS NULL'.
			')';
		}

		// groupids
		if ($options['groupids'] !== null) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['groupid'] = dbConditionInt('hg.groupid', $options['groupids']);

			if ($options['groupCount']) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// templateids
		if ($options['templateids'] !== null) {
			zbx_value2array($options['templateids']);

			if ($options['hostids'] !== null) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else {
				$options['hostids'] = $options['templateids'];
			}
		}

		// hostids
		if ($options['hostids'] !== null) {
			zbx_value2array($options['hostids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['hostids']);
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';

			if ($options['groupCount']) {
				$sqlParts['group']['i'] = 'i.hostid';
			}
		}

		// triggerids
		if ($options['triggerids'] !== null) {
			zbx_value2array($options['triggerids']);

			$sqlParts['where']['triggerid'] = dbConditionInt('t.triggerid', $options['triggerids']);
		}

		// itemids
		if ($options['itemids'] !== null) {
			zbx_value2array($options['itemids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where']['itemid'] = dbConditionInt('f.itemid', $options['itemids']);
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';

			if ($options['groupCount']) {
				$sqlParts['group']['f'] = 'f.itemid';
			}
		}

		// discoveryids
		if ($options['discoveryids'] !== null) {
			zbx_value2array($options['discoveryids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['item_discovery'] = 'item_discovery id';
			$sqlParts['where']['fid'] = 'f.itemid=id.itemid';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where'][] = dbConditionInt('id.parent_itemid', $options['discoveryids']);

			if ($options['groupCount']) {
				$sqlParts['group']['id'] = 'id.parent_itemid';
			}
		}

		// functions
		if ($options['functions'] !== null) {
			zbx_value2array($options['functions']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where'][] = dbConditionString('f.name', $options['functions']);
		}

		// monitored
		if ($options['monitored'] !== null) {
			$sqlParts['where']['monitored'] =
				' NOT EXISTS ('.
					' SELECT NULL'.
					' FROM functions ff'.
					' WHERE ff.triggerid=t.triggerid'.
						' AND EXISTS ('.
								' SELECT NULL'.
								' FROM items ii,hosts hh'.
								' WHERE ff.itemid=ii.itemid'.
									' AND hh.hostid=ii.hostid'.
									' AND ('.
										' ii.status<>'.ITEM_STATUS_ACTIVE.
										' OR hh.status<>'.HOST_STATUS_MONITORED.
									' )'.
						' )'.
				' )';
			$sqlParts['where']['status'] = 't.status='.TRIGGER_STATUS_ENABLED;
		}

		// active
		if ($options['active'] !== null) {
			$sqlParts['where']['active'] =
				' NOT EXISTS ('.
					' SELECT NULL'.
					' FROM functions ff'.
					' WHERE ff.triggerid=t.triggerid'.
						' AND EXISTS ('.
							' SELECT NULL'.
							' FROM items ii,hosts hh'.
							' WHERE ff.itemid=ii.itemid'.
								' AND hh.hostid=ii.hostid'.
								' AND  hh.status<>'.HOST_STATUS_MONITORED.
						' )'.
				' )';
			$sqlParts['where']['status'] = 't.status='.TRIGGER_STATUS_ENABLED;
		}

		// maintenance
		if ($options['maintenance'] !== null) {
			$sqlParts['where'][] = (($options['maintenance'] == 0) ? ' NOT ' : '').
				' EXISTS ('.
					' SELECT NULL'.
					' FROM functions ff'.
					' WHERE ff.triggerid=t.triggerid'.
						' AND EXISTS ('.
								' SELECT NULL'.
								' FROM items ii,hosts hh'.
								' WHERE ff.itemid=ii.itemid'.
									' AND hh.hostid=ii.hostid'.
									' AND hh.maintenance_status=1'.
						' )'.
				' )';
			$sqlParts['where'][] = 't.status='.TRIGGER_STATUS_ENABLED;
		}

		// templated
		if ($options['templated'] !== null) {
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// inherited
		if ($options['inherited'] !== null) {
			if ($options['inherited']) {
				$sqlParts['where'][] = 't.templateid IS NOT NULL';
			}
			else {
				$sqlParts['where'][] = 't.templateid IS NULL';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('triggers t', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('triggers t', $options, $sqlParts);

			if (isset($options['filter']['host']) && $options['filter']['host'] !== null) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['functions'] = 'functions f';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sqlParts['where']['fi'] = 'f.itemid=i.itemid';

				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['host'] = dbConditionString('h.host', $options['filter']['host']);
			}

			if (isset($options['filter']['hostid']) && $options['filter']['hostid'] !== null) {
				zbx_value2array($options['filter']['hostid']);

				$sqlParts['from']['functions'] = 'functions f';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sqlParts['where']['fi'] = 'f.itemid=i.itemid';

				$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['filter']['hostid']);
			}
		}

		// group
		if ($options['group'] !== null) {
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['hstgrp'] = 'hstgrp g';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where']['ghg'] = 'g.groupid=hg.groupid';
			$sqlParts['where']['group'] = ' g.name='.zbx_dbstr($options['group']);
		}

		// host
		if ($options['host'] !== null) {
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
			$sqlParts['where']['host'] = ' h.host='.zbx_dbstr($options['host']);
		}

		// min_severity
		if ($options['min_severity'] !== null) {
			$sqlParts['where'][] = 't.priority>='.zbx_dbstr($options['min_severity']);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$dbRes = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($triggerPrototype = DBfetch($dbRes)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $triggerPrototype;
				}
				else {
					$result = $triggerPrototype['rowscount'];
				}
			}
			else {
				$result[$triggerPrototype['triggerid']] = $triggerPrototype;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// expand expressions
		if ($options['expandExpression'] !== null && $result) {
			$sources = [];
			if (array_key_exists('expression', reset($result))) {
				$sources[] = 'expression';
			}
			if (array_key_exists('recovery_expression', reset($result))) {
				$sources[] = 'recovery_expression';
			}

			if ($sources) {
				$result = CMacrosResolverHelper::resolveTriggerExpressions($result,
					['resolve_usermacros' => true, 'resolve_macros' => true, 'sources' => $sources]
				);
			}
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Create new trigger prototypes.
	 *
	 * @param array $trigger_prototypes
	 *
	 * @return array
	 */
	public function create(array $trigger_prototypes) {
		$this->validateCreate($trigger_prototypes);

		$this->createReal($trigger_prototypes);
		$this->checkDependenciesLinks($trigger_prototypes);

		$this->inherit($trigger_prototypes);

		$this->updateDependencies($trigger_prototypes);

		return ['triggerids' => zbx_objectValues($trigger_prototypes, 'triggerid')];
	}

	/**
	 * Update existing trigger prototypes.
	 *
	 * @param array $trigger_prototypes
	 *
	 * @return array
	 */
	public function update(array $trigger_prototypes): array {
		$this->validateUpdate($trigger_prototypes, $db_triggers);

		$this->updateReal($trigger_prototypes, $db_triggers);
		$this->inherit($trigger_prototypes);

		$this->updateDependencies($trigger_prototypes, $db_triggers);

		return ['triggerids' => array_column($trigger_prototypes, 'triggerid')];
	}

	/**
	 * Delete existing trigger prototypes.
	 *
	 * @param array $triggerids
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function delete(array $triggerids) {
		$this->validateDelete($triggerids, $db_triggers);

		CTriggerPrototypeManager::delete($triggerids);

		$this->addAuditBulk(CAudit::ACTION_DELETE, CAudit::RESOURCE_TRIGGER_PROTOTYPE, $db_triggers);

		return ['triggerids' => $triggerids];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array $triggerids   [IN/OUT]
	 * @param array $db_triggers  [OUT]
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateDelete(array &$triggerids, array &$db_triggers = null) {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $triggerids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_triggers = $this->get([
			'output' => ['triggerid', 'description', 'expression', 'templateid'],
			'triggerids' => $triggerids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($triggerids as $triggerid) {
			if (!array_key_exists($triggerid, $db_triggers)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_trigger = $db_triggers[$triggerid];

			if ($db_trigger['templateid'] != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot delete templated trigger prototype "%1$s:%2$s".', $db_trigger['description'],
						CMacrosResolverHelper::resolveTriggerExpression($db_trigger['expression'])
					)
				);
			}
		}
	}

	/**
	 * Retrieves and adds additional requested data (options 'selectHosts', etc.) to result set.
	 *
	 * @param array		$options
	 * @param array		$result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$triggerPrototypeIds = array_keys($result);

		// Add trigger prototype dependencies.
		if ($options['selectDependencies'] !== null && $options['selectDependencies'] != API_OUTPUT_COUNT) {
			$dependencies = [];
			$relationMap = new CRelationMap();
			$res = DBselect(
				'SELECT td.triggerid_up,td.triggerid_down'.
				' FROM trigger_depends td'.
				' WHERE '.dbConditionInt('td.triggerid_down', $triggerPrototypeIds)
			);

			while ($relation = DBfetch($res)) {
				$relationMap->addRelation($relation['triggerid_down'], $relation['triggerid_up']);
			}

			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$dependencies = API::getApiService()->select($this->tableName(), [
					'output' => $options['selectDependencies'],
					'triggerids' => $related_ids,
					'preservekeys' => true
				]);
			}

			$result = $relationMap->mapMany($result, $dependencies, 'dependencies');
		}

		// adding items
		if ($options['selectItems'] !== null && $options['selectItems'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'triggerid', 'itemid', 'functions');
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

		// adding discovery rule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			$dbRules = DBselect(
				'SELECT id.parent_itemid,f.triggerid'.
					' FROM item_discovery id,functions f'.
					' WHERE '.dbConditionInt('f.triggerid', $triggerPrototypeIds).
					' AND f.itemid=id.itemid'
			);
			$relationMap = new CRelationMap();
			while ($rule = DBfetch($dbRules)) {
				$relationMap->addRelation($rule['triggerid'], $rule['parent_itemid']);
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

}
