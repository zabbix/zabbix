<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * Class containing methods for operations with triggers.
 *
 * @package API
 */
class CTrigger extends CTriggerGeneral {

	protected $tableName = 'triggers';
	protected $tableAlias = 't';
	protected $sortColumns = ['triggerid', 'description', 'status', 'priority', 'lastchange', 'hostname'];

	/**
	 * Get Triggers data.
	 *
	 * @param array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['triggerids']
	 * @param array $options['applicationids']
	 * @param array $options['status']
	 * @param array $options['editable']
	 * @param array $options['count']
	 * @param array $options['pattern']
	 * @param array $options['limit']
	 * @param array $options['order']
	 *
	 * @return array|int item data as array or false if error
	 */
	public function get(array $options = []) {
		$result = [];
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sqlParts = [
			'select'	=> ['triggers' => 't.triggerid'],
			'from'		=> ['t' => 'triggers t'],
			'where'		=> [],
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
			'applicationids'				=> null,
			'functions'						=> null,
			'inherited'						=> null,
			'templated'						=> null,
			'monitored'						=> null,
			'active'						=> null,
			'maintenance'					=> null,
			'withUnacknowledgedEvents'		=> null,
			'withAcknowledgedEvents'		=> null,
			'withLastEventUnacknowledged'	=> null,
			'skipDependent'					=> null,
			'nopermissions'					=> null,
			'editable'						=> null,
			// timing
			'lastChangeSince'				=> null,
			'lastChangeTill'				=> null,
			// filter
			'group'							=> null,
			'host'							=> null,
			'only_true'						=> null,
			'min_severity'					=> null,
			'filter'						=> null,
			'search'						=> null,
			'searchByAny'					=> null,
			'startSearch'					=> null,
			'excludeSearch'					=> null,
			'searchWildcardsEnabled'		=> null,
			// output
			'expandDescription'				=> null,
			'expandComment'					=> null,
			'expandExpression'				=> null,
			'output'						=> API_OUTPUT_EXTEND,
			'selectGroups'					=> null,
			'selectHosts'					=> null,
			'selectItems'					=> null,
			'selectFunctions'				=> null,
			'selectDependencies'			=> null,
			'selectDiscoveryRule'			=> null,
			'selectLastEvent'				=> null,
			'countOutput'					=> null,
			'groupCount'					=> null,
			'preservekeys'					=> null,
			'sortfield'						=> '',
			'sortorder'						=> '',
			'limit'							=> null,
			'limitSelects'					=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$userGroups = getUserGroupsByUserId($userid);

			$sqlParts['where'][] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM functions f,items i,hosts_groups hgg'.
					' LEFT JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', $userGroups).
				' WHERE t.triggerid=f.triggerid '.
					' AND f.itemid=i.itemid'.
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

			sort($options['groupids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['groupid'] = dbConditionInt('hg.groupid', $options['groupids']);

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

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['hostids']);
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['i'] = 'i.hostid';
			}
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['where']['triggerid'] = dbConditionInt('t.triggerid', $options['triggerids']);
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where']['itemid'] = dbConditionInt('f.itemid', $options['itemids']);
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['f'] = 'f.itemid';
			}
		}

		// applicationids
		if (!is_null($options['applicationids'])) {
			zbx_value2array($options['applicationids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items_applications'] = 'items_applications ia';
			$sqlParts['where']['a'] = dbConditionInt('ia.applicationid', $options['applicationids']);
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fia'] = 'f.itemid=ia.itemid';
		}

		// functions
		if (!is_null($options['functions'])) {
			zbx_value2array($options['functions']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where'][] = dbConditionString('f.function', $options['functions']);
		}

		// monitored
		if (!is_null($options['monitored'])) {
			$sqlParts['where']['monitored'] = 'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM functions f,items i,hosts h'.
					' WHERE t.triggerid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND i.hostid=h.hostid'.
						' AND ('.
							'i.status<>'.ITEM_STATUS_ACTIVE.
							' OR h.status<>'.HOST_STATUS_MONITORED.
						')'.
					')';
			$sqlParts['where']['status'] = 't.status='.TRIGGER_STATUS_ENABLED;
		}

		// active
		if (!is_null($options['active'])) {
			$sqlParts['where']['active'] = 'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM functions f,items i,hosts h'.
					' WHERE t.triggerid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND i.hostid=h.hostid'.
						' AND h.status<>'.HOST_STATUS_MONITORED.
					')';
			$sqlParts['where']['status'] = 't.status='.TRIGGER_STATUS_ENABLED;
		}

		// maintenance
		if (!is_null($options['maintenance'])) {
			$sqlParts['where'][] = ($options['maintenance'] == 0 ? 'NOT ' : '').
					'EXISTS ('.
						'SELECT NULL'.
						' FROM functions f,items i,hosts h'.
						' WHERE t.triggerid=f.triggerid'.
							' AND f.itemid=i.itemid'.
							' AND i.hostid=h.hostid'.
							' AND h.maintenance_status='.HOST_MAINTENANCE_STATUS_ON.
					')';
			$sqlParts['where'][] = 't.status='.TRIGGER_STATUS_ENABLED;
		}

		// lastChangeSince
		if (!is_null($options['lastChangeSince'])) {
			$sqlParts['where']['lastchangesince'] = 't.lastchange>'.zbx_dbstr($options['lastChangeSince']);
		}

		// lastChangeTill
		if (!is_null($options['lastChangeTill'])) {
			$sqlParts['where']['lastchangetill'] = 't.lastchange<'.zbx_dbstr($options['lastChangeTill']);
		}

		// withUnacknowledgedEvents
		if (!is_null($options['withUnacknowledgedEvents'])) {
			$sqlParts['where']['unack'] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM events e'.
					' WHERE t.triggerid=e.objectid'.
						' AND e.source='.EVENT_SOURCE_TRIGGERS.
						' AND e.object='.EVENT_OBJECT_TRIGGER.
						' AND e.value='.TRIGGER_VALUE_TRUE.
						' AND e.acknowledged='.EVENT_NOT_ACKNOWLEDGED.
					')';
		}

		// withAcknowledgedEvents
		if (!is_null($options['withAcknowledgedEvents'])) {
			$sqlParts['where']['ack'] = 'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM events e'.
					' WHERE e.objectid=t.triggerid'.
						' AND e.source='.EVENT_SOURCE_TRIGGERS.
						' AND e.object='.EVENT_OBJECT_TRIGGER.
						' AND e.value='.TRIGGER_VALUE_TRUE.
						' AND e.acknowledged='.EVENT_NOT_ACKNOWLEDGED.
					')';
		}

		// templated
		if (!is_null($options['templated'])) {
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
		if (!is_null($options['inherited'])) {
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
		if (is_null($options['filter'])) {
			$options['filter'] = [];
		}

		if (is_array($options['filter'])) {
			if (!array_key_exists('flags', $options['filter'])) {
				$options['filter']['flags'] = [
					ZBX_FLAG_DISCOVERY_NORMAL,
					ZBX_FLAG_DISCOVERY_CREATED
				];
			}

			$this->dbFilter('triggers t', $options, $sqlParts);

			if (isset($options['filter']['host']) && !is_null($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['functions'] = 'functions f';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['host'] = dbConditionString('h.host', $options['filter']['host']);
			}

			if (isset($options['filter']['hostid']) && !is_null($options['filter']['hostid'])) {
				zbx_value2array($options['filter']['hostid']);

				$sqlParts['from']['functions'] = 'functions f';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
				$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['filter']['hostid']);
			}
		}

		// group
		if (!is_null($options['group'])) {
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['groups'] = 'groups g';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where']['ghg'] = 'g.groupid = hg.groupid';
			$sqlParts['where']['group'] = ' g.name='.zbx_dbstr($options['group']);
		}

		// host
		if (!is_null($options['host'])) {
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
			$sqlParts['where']['host'] = ' h.host='.zbx_dbstr($options['host']);
		}

		// only_true
		if (!is_null($options['only_true'])) {
			$config = select_config();
			$sqlParts['where']['ot'] = '((t.value='.TRIGGER_VALUE_TRUE.')'.
					' OR '.
					'((t.value='.TRIGGER_VALUE_FALSE.') AND (t.lastchange>'.(time() - $config['ok_period']).')))';
		}

		// min_severity
		if (!is_null($options['min_severity'])) {
			$sqlParts['where'][] = 't.priority>='.zbx_dbstr($options['min_severity']);
		}

		// limit
		if (!zbx_ctype_digit($options['limit']) || !$options['limit']) {
			$options['limit'] = null;
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);

		// return count or grouped counts via direct SQL count
		if (!is_null($options['countOutput']) && !$this->requiresPostSqlFiltering($options)) {
			$dbRes = DBselect($this->createSelectQueryFromParts($sqlParts), $options['limit']);
			while ($trigger = DBfetch($dbRes)) {
				if (!is_null($options['groupCount'])) {
					$result[] = $trigger;
				}
				else {
					$result = $trigger['rowscount'];
				}
			}
			return $result;
		}

		$result = zbx_toHash($this->customFetch($this->createSelectQueryFromParts($sqlParts), $options), 'triggerid');

		// return count for post SQL filtered result sets
		if (!is_null($options['countOutput'])) {
			return count($result);
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// expandDescription
		if (!is_null($options['expandDescription']) && $result && array_key_exists('description', reset($result))) {
			$result = CMacrosResolverHelper::resolveTriggerNames($result);
		}

		// expandComment
		if (!is_null($options['expandComment']) && $result && array_key_exists('comments', reset($result))) {
			$result = CMacrosResolverHelper::resolveTriggerDescriptions($result);
		}

		// expand expression
		if ($options['expandExpression'] !== null && $result && array_key_exists('expression', reset($result))) {
			$result = CMacrosResolverHelper::resolveTriggerExpressions($result,
				['resolve_usermacros' => true, 'resolve_macros' => true]
			);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		$result = $this->unsetExtraFields($result, ['state', 'expression'], $options['output']);

		return $result;
	}

	/**
	 * Check input.
	 *
	 * @param array  $triggers
	 * @param string $method
	 */
	public function checkInput(array &$triggers, $method) {
		$update = ($method == 'update');

		// permissions
		if ($update) {
			$triggerDbFields = ['triggerid' => null];

			$dbTriggers = $this->get([
				'output' => API_OUTPUT_EXTEND,
				'triggerids' => zbx_objectValues($triggers, 'triggerid'),
				'editable' => true,
				'preservekeys' => true
			]);

			$dbTriggers = CMacrosResolverHelper::resolveTriggerExpressions($dbTriggers);

			$updateDiscoveredValidator = new CUpdateDiscoveredValidator([
				'allowed' => ['triggerid', 'status'],
				'messageAllowedField' => _('Cannot update "%2$s" for a discovered trigger "%1$s".')
			]);
			foreach ($triggers as $trigger) {
				$triggerId = $trigger['triggerid'];
				// check permissions
				if (!isset($dbTriggers[$triggerId])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}
				$dbTrigger = $dbTriggers[$triggerId];

				$triggerName = isset($trigger['description']) ? $trigger['description'] : $dbTrigger['description'];
				$updateDiscoveredValidator->setObjectName($triggerName);

				// discovered fields, except status, cannot be updated
				$this->checkPartialValidator($trigger, $updateDiscoveredValidator, $dbTrigger);
			}

			$triggers = $this->extendObjects($this->tableName(), $triggers, ['description']);
		}
		else {
			$triggerDbFields = [
				'description' => null,
				'expression' => null,
				'value' => TRIGGER_VALUE_FALSE
			];
		}

		foreach ($triggers as $tnum => &$trigger) {
			$currentTrigger = $triggers[$tnum];

			$this->checkNoParameters(
				$trigger,
				['templateid', 'state', 'value'],
				($update ? _('Cannot update "%1$s" for trigger "%2$s".') : _('Cannot set "%1$s" for trigger "%2$s".')),
				$trigger['description']
			);

			if (!check_db_fields($triggerDbFields, $trigger)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect fields for trigger.'));
			}

			$expressionChanged = true;
			if ($update) {
				$dbTrigger = $dbTriggers[$trigger['triggerid']];

				if (array_key_exists('expression', $trigger) && $trigger['expression'] === $dbTrigger['expression']) {
					$expressionChanged = false;
				}
				if (array_key_exists('description', $trigger)
						&& strcmp($trigger['description'], $dbTrigger['description']) == 0) {
					unset($trigger['description']);
				}
			}

			// if some of the properties are unchanged, no need to update them in DB
			// validating trigger expression
			if (isset($trigger['expression']) && $expressionChanged) {
				// expression permissions
				$expressionData = new CTriggerExpression(['lldmacros' => false]);
				if (!$expressionData->parse($trigger['expression'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $expressionData->error);
				}

				if (!isset($expressionData->expressions[0])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Trigger expression must contain at least one host:key reference.'));
				}

				$expressionHosts = $expressionData->getHosts();

				$hosts = API::Host()->get([
					'output' => ['hostid', 'host', 'status'],
					'filter' => ['host' => $expressionHosts],
					'editable' => true,
					'templated_hosts' => true,
					'preservekeys' => true
				]);
				$hosts = zbx_toHash($hosts, 'host');
				$hostsStatusFlags = 0x0;
				foreach ($expressionHosts as $host) {
					if (!isset($hosts[$host])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect trigger expression. Host "%s" does not exist or you have no access to this host.', $host));
					}

					// find out if both templates and hosts are referenced in expression
					$hostsStatusFlags |= ($hosts[$host]['status'] == HOST_STATUS_TEMPLATE) ? 0x1 : 0x2;
					if ($hostsStatusFlags == 0x3) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect trigger expression. Trigger expression elements should not belong to a template and a host simultaneously.'));
					}
				}

				foreach ($expressionData->expressions as $exprPart) {
					$sql = 'SELECT i.itemid,i.value_type'.
							' FROM items i,hosts h'.
							' WHERE i.key_='.zbx_dbstr($exprPart['item']).
								' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]).
								' AND h.host='.zbx_dbstr($exprPart['host']).
								' AND h.hostid=i.hostid';

					if (!DBfetch(DBselect($sql))) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect item key "%1$s" provided for trigger expression on "%2$s".', $exprPart['item'], $exprPart['host']));
					}
				}
			}

			// check existing
			$this->checkIfExistsOnHost($currentTrigger);
		}
		unset($trigger);
	}

	/**
	 * Add triggers.
	 *
	 * Trigger params: expression, description, type, priority, status, comments, url, templateid
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	public function create(array $triggers) {
		$triggers = zbx_toArray($triggers);

		$this->checkInput($triggers, __FUNCTION__);
		$this->createReal($triggers);

		foreach ($triggers as $trigger) {
			$this->inherit($trigger);
		}

		// clear all dependencies on inherited triggers
		$this->deleteDependencies($triggers);

		// add new dependencies
		foreach ($triggers as $trigger) {
			if (!empty($trigger['dependencies'])) {
				$newDeps = [];
				foreach ($trigger['dependencies'] as $depTrigger) {
					$newDeps[] = [
						'triggerid' => $trigger['triggerid'],
						'dependsOnTriggerid' => $depTrigger['triggerid']
					];
				}
				$this->addDependencies($newDeps);
			}
		}

		return ['triggerids' => zbx_objectValues($triggers, 'triggerid')];
	}

	/**
	 * Update triggers.
	 *
	 * If a trigger expression is passed in any of the triggers, it must be in it's exploded form.
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	public function update(array $triggers) {
		$triggers = zbx_toArray($triggers);
		$triggerids = zbx_objectValues($triggers, 'triggerid');

		$this->checkInput($triggers, __FUNCTION__);
		$this->updateReal($triggers);

		foreach ($triggers as $trigger) {
			$this->inherit($trigger);

			// replace dependencies
			if (isset($trigger['dependencies'])) {
				$this->deleteDependencies($trigger);

				if ($trigger['dependencies']) {
					$newDeps = [];
					foreach ($trigger['dependencies'] as $depTrigger) {
						$newDeps[] = [
							'triggerid' => $trigger['triggerid'],
							'dependsOnTriggerid' => $depTrigger['triggerid']
						];
					}
					$this->addDependencies($newDeps);
				}
			}
		}

		return ['triggerids' => $triggerids];
	}

	/**
	 * Delete triggers.
	 *
	 * @param array $triggerIds
	 * @param bool  $nopermissions
	 *
	 * @return array
	 */
	public function delete(array $triggerIds, $nopermissions = false) {
		$this->validateDelete($triggerIds, $nopermissions);

		// get child triggers
		$parentTriggerIds = $triggerIds;

		do {
			$dbItems = DBselect('SELECT triggerid FROM triggers WHERE '.dbConditionInt('templateid', $parentTriggerIds));
			$parentTriggerIds = [];

			while ($dbTrigger = DBfetch($dbItems)) {
				$parentTriggerIds[] = $dbTrigger['triggerid'];
				$triggerIds[] = $dbTrigger['triggerid'];
			}
		} while ($parentTriggerIds);

		// select all triggers which are deleted (including children)
		$delTriggers = $this->get([
			'output' => ['triggerid', 'description', 'expression'],
			'triggerids' => $triggerIds,
			'nopermissions' => true,
			'selectHosts' => ['name']
		]);

		// TODO: REMOVE info
		foreach ($delTriggers as $trigger) {
			info(_s('Deleted: Trigger "%1$s" on "%2$s".', $trigger['description'],
					implode(', ', zbx_objectValues($trigger['hosts'], 'name'))));

			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TRIGGER, $trigger['triggerid'],
					$trigger['description'], null, null, null);
		}

		// execute delete
		$this->deleteByIds($triggerIds);

		return ['triggerids' => $triggerIds];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array     $triggerIds
	 * @param boolean   $nopermissions  if set to true permissions will not be checked
	 *
	 * @return void
	 */
	protected function validateDelete(array $triggerIds, $nopermissions) {
		if (!$triggerIds) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		if (!$nopermissions) {
			$dbTriggers = $this->get([
				'output' => ['triggerid', 'flags', 'description'],
				'triggerids' => $triggerIds,
				'editable' => true,
				'filter' => ['flags' => null]
			]);

			foreach ($dbTriggers as $dbTrigger) {
				if ($dbTrigger['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Cannot delete discovered trigger "%1$s".', $dbTrigger['description'])
					);
				}
			}

			$this->checkPermissions($triggerIds);
			$this->checkNotInherited($triggerIds);
		}
	}

	/**
	 * Delete trigger by ids.
	 *
	 * @param array $triggerIds
	 */
	protected function deleteByIds(array $triggerIds) {
		// others idx should be deleted as well if they arise at some point
		DB::delete('profiles', [
			'idx' => 'web.events.filter.triggerid',
			'value_id' => $triggerIds
		]);

		DB::delete('sysmaps_elements', [
			'elementid' => $triggerIds,
			'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER
		]);

		// disable actions
		$actionIds = [];

		$dbActions = DBselect(
			'SELECT DISTINCT actionid'.
			' FROM conditions'.
			' WHERE conditiontype='.CONDITION_TYPE_TRIGGER.
				' AND '.dbConditionString('value', $triggerIds)
		);
		while ($dbAction = DBfetch($dbActions)) {
			$actionIds[$dbAction['actionid']] = $dbAction['actionid'];
		}

		DBexecute('UPDATE actions SET status='.ACTION_STATUS_DISABLED.' WHERE '.dbConditionInt('actionid', $actionIds));

		// delete action conditions
		DB::delete('conditions', [
			'conditiontype' => CONDITION_TYPE_TRIGGER,
			'value' => $triggerIds
		]);

		if ($this->usedInItServices($triggerIds)) {
			DB::update('services', [
				'values' => [
					'triggerid' => null,
					'showsla' => SERVICE_SHOW_SLA_OFF
				],
				'where' => [
					'triggerid' => $triggerIds
				]
			]);

			updateItServices();
		}

		parent::deleteByIds($triggerIds);
	}

	/**
	 * Validates the input for the addDependencies() method.
	 *
	 * @throws APIException if the given dependencies are invalid
	 *
	 * @param array $triggersData
	 */
	protected function validateAddDependencies(array $triggersData) {
		if (!$triggersData) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$depTtriggerIds = [];
		$triggers = [];
		foreach ($triggersData as $dep) {
			$triggerId = $dep['triggerid'];

			if (!isset($triggers[$dep['triggerid']])) {
				$triggers[$triggerId] = [
					'triggerid' => $triggerId,
					'dependencies' => [],
				];
			}
			$triggers[$triggerId]['dependencies'][] = $dep['dependsOnTriggerid'];
			$depTtriggerIds[$dep['dependsOnTriggerid']] = $dep['dependsOnTriggerid'];
		}

		if (!$this->isReadable($depTtriggerIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$this->checkDependencies($triggers);
		$this->checkDependencyParents($triggers);
		$this->checkDependencyDuplicates($triggers);
	}

	/**
	 * Add the given dependencies and inherit them on all child triggers.
	 *
	 * @param array $triggersData   an array of trigger dependency pairs, each pair in the form of
	 *                              array('triggerid' => 1, 'dependsOnTriggerid' => 2)
	 *
	 * @return array
	 */
	public function addDependencies(array $triggersData) {
		$triggersData = zbx_toArray($triggersData);

		$triggerIds = array_unique(zbx_objectValues($triggersData, 'triggerid'));
		if (!$this->isWritable($triggerIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$this->validateAddDependencies($triggersData);


		foreach ($triggersData as $dep) {
			$triggerId = $dep['triggerid'];
			$depTriggerId = $dep['dependsOnTriggerid'];

			DB::insert('trigger_depends', [[
				'triggerid_down' => $triggerId,
				'triggerid_up' => $depTriggerId
			]]);

			// propagate the dependencies to the child triggers
			$childTriggers = API::getApiService()->select($this->tableName(), [
				'output' => ['triggerid'],
				'filter' => [
					'templateid' => $triggerId
				]
			]);
			if ($childTriggers) {
				foreach ($childTriggers as $childTrigger) {
					$childHostsQuery = get_hosts_by_triggerid($childTrigger['triggerid']);
					while ($childHost = DBfetch($childHostsQuery)) {
						$newDep = [$childTrigger['triggerid'] => $depTriggerId];
						$newDep = replace_template_dependencies($newDep, $childHost['hostid']);

						$this->addDependencies([[
							'triggerid' => $childTrigger['triggerid'],
							'dependsOnTriggerid' => $newDep[$childTrigger['triggerid']]
						]]);
					}
				}
			}
		}

		return ['triggerids' => $triggerIds];
	}

	/**
	 * Validates the input for the deleteDependencies() method.
	 *
	 * @throws APIException if the given input is invalid
	 *
	 * @param array $triggers
	 */
	protected function validateDeleteDependencies(array $triggers) {
		if (!$triggers) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}
	}

	/**
	 * Deletes all trigger dependencies from the given triggers and their children.
	 *
	 * @param array $triggers   an array of triggers with the 'triggerid' field defined
	 *
	 * @return array
	 */
	public function deleteDependencies(array $triggers) {
		$triggers = zbx_toArray($triggers);

		$this->validateDeleteDependencies($triggers);

		$triggerids = zbx_objectValues($triggers, 'triggerid');

		try {
			// delete the dependencies from the child triggers
			$childTriggers = API::getApiService()->select($this->tableName(), [
				'output' => ['triggerid'],
				'filter' => [
					'templateid' => $triggerids
				]
			]);
			if ($childTriggers) {
				$this->deleteDependencies($childTriggers);
			}

			DB::delete('trigger_depends', [
				'triggerid_down' => $triggerids
			]);
		}
		catch (APIException $e) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete dependency'));
		}

		return ['triggerids' => $triggerids];
	}

	/**
	 * @param $triggers
	 */
	protected function createReal(array &$triggers) {
		$triggers = zbx_toArray($triggers);

		// insert triggers without expression
		$triggersCopy = $triggers;
		for ($i = 0, $size = count($triggersCopy); $i < $size; $i++) {
			unset($triggersCopy[$i]['expression']);
		}
		$triggerIds = DB::insert('triggers', $triggersCopy);
		unset($triggersCopy);

		$allHosts = [];
		$allowedHosts = [];
		$triggersAndHosts = [];
		$triggerExpression = [];
		foreach ($triggers as $tnum => $trigger) {
			$triggerId = $triggers[$tnum]['triggerid'] = $triggerIds[$tnum];
			$hosts = [];

			try {
				$expression = implode_exp($trigger['expression'], $triggerId, $hosts);
			}
			catch (Exception $e) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Cannot implode expression "%s".', $trigger['expression']).' '.$e->getMessage());
			}

			$this->validateItems($trigger);

			foreach ($hosts as $host) {
				$allHosts[] = $host;
			}

			$triggersAndHosts[$triggerId] = $hosts;
			$triggerExpression[$triggerId] = $expression;
		}

		$allHosts = array_unique($allHosts);
		$dbHostsStatuses = DBselect(
			'SELECT h.host,h.status'.
			' FROM hosts h'.
			' WHERE '.dbConditionString('h.host', $allHosts)
		);
		while ($allowedHostsData = DBfetch($dbHostsStatuses)) {
			if ($allowedHostsData['status'] != HOST_STATUS_TEMPLATE) {
				$allowedHosts[] = $allowedHostsData['host'];
			}
		}

		// update triggers expression
		foreach ($triggers as $tnum => $trigger) {
			$triggerId = $triggers[$tnum]['triggerid'] = $triggerIds[$tnum];

			DB::update('triggers', [
				'values' => ['expression' => $triggerExpression[$triggerId]],
				'where' => ['triggerid' => $triggerId]
			]);

			info(_s('Created: Trigger "%1$s" on "%2$s".', $trigger['description'], implode(', ', $triggersAndHosts[$triggerId])));
			add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_TRIGGER, $triggerId,
					$trigger['description'], null, null, null);
		}
	}

	/**
	 * Updates trigger records in database.
	 *
	 * @param array $triggers
	 */
	protected function updateReal(array $triggers) {
		$triggers = zbx_toArray($triggers);
		$infos = [];

		$triggerIds = zbx_objectValues($triggers, 'triggerid');

		$dbTriggers = $this->get([
			'output' => API_OUTPUT_EXTEND,
			'triggerids' => $triggerIds,
			'selectHosts' => ['name'],
			'selectDependencies' => ['triggerid'],
			'preservekeys' => true,
			'nopermissions' => true
		]);

		$dbTriggers = CMacrosResolverHelper::resolveTriggerExpressions($dbTriggers);

		$changedPriorityTriggerIds = [];

		foreach ($triggers as &$trigger) {
			$descriptionChanged = false;
			$expressionChanged = false;

			$dbTrigger = $dbTriggers[$trigger['triggerid']];
			$hosts = zbx_objectValues($dbTrigger['hosts'], 'name');

			if (isset($trigger['description']) && strcmp($dbTrigger['description'], $trigger['description']) != 0) {
				$descriptionChanged = true;
			}
			else {
				$trigger['description'] = $dbTrigger['description'];
			}

			if (isset($trigger['expression']) && $dbTrigger['expression'] !== $trigger['expression']) {
				$this->validateItems($trigger);

				$expressionChanged = true;
				$expressionFull = $trigger['expression'];
			}

			if ($expressionChanged) {
				// check the expression
				$expressionData = new CTriggerExpression();
				if (!$expressionData->parse($expressionFull)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $expressionData->error);
				}

				// remove triggers if expression is changed in a way that trigger will not appear in current host
				$oldExpressionData = new CTriggerExpression();
				$oldExpressionData->parse($dbTrigger['expression']);
				// check if at least one template has stayed in expression, this means that child trigger will stay in host
				$oldTemplates = $oldExpressionData->getHosts();
				$newTemplates = zbx_toHash($expressionData->getHosts());
				$proceed = true;
				foreach ($oldTemplates as $oldTemplate) {
					if (isset($newTemplates[$oldTemplate])) {
						$proceed = false;
						break;
					}
				}
				// proceed if there is possibility that child triggers should be deleted
				if ($proceed) {
					$sql = 'SELECT t.triggerid'.
							' FROM triggers t'.
							' WHERE t.templateid='.zbx_dbstr($trigger['triggerid']);
					$cTrigCursor = DBselect($sql);
					$cTrigIds = [];
					while ($cTrig = DBfetch($cTrigCursor)) {
						// get templates linked to templated trigger host
						$templateNames = DBfetchArrayAssoc(DBselect('SELECT h.name'.
							' FROM hosts h, hosts_templates ht, items i, functions f'.
							' WHERE h.hostid = ht.templateid AND ht.hostid = i.hostid AND i.itemid = f.itemid AND'.
							' f.triggerid='.zbx_dbstr($cTrig['triggerid'])), 'name');

						// if we have at least one template linked to trigger host inside trigger expression,
						// then we don't delete this trigger
						$expressionHosts = $expressionData->getHosts();
						foreach ($expressionHosts as $templateName) {
							if (isset($templateNames[$templateName])) {
								continue 2;
							}
						}
						$cTrigIds[] = $cTrig['triggerid'];
					}
					$this->deleteByIds($cTrigIds);
				}

				DB::delete('functions', ['triggerid' => $trigger['triggerid']]);

				try {
					$trigger['expression'] = implode_exp($expressionFull, $trigger['triggerid'], $hosts);
				}
				catch (Exception $e) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Cannot implode expression "%s".', $expressionFull).' '.$e->getMessage());
				}

				// if the expression has changed, we must revalidate the existing dependencies
				if (!isset($trigger['dependencies'])) {
					$trigger['dependencies'] = zbx_objectValues($dbTrigger['dependencies'], 'triggerid');
				}
			}

			$triggerUpdate = $trigger;
			if (!$descriptionChanged) {
				unset($triggerUpdate['description']);
			}
			if (!$expressionChanged) {
				unset($triggerUpdate['expression']);
			}

			// skip updating read only values
			unset(
				$triggerUpdate['state'],
				$triggerUpdate['value'],
				$triggerUpdate['lastchange'],
				$triggerUpdate['error']
			);

			DB::update('triggers', [
				'values' => $triggerUpdate,
				'where' => ['triggerid' => $trigger['triggerid']]
			]);

			// update service status
			if (isset($trigger['priority']) && $trigger['priority'] != $dbTrigger['priority']) {
				$changedPriorityTriggerIds[] = $trigger['triggerid'];
			}

			// restore the full expression to properly validate dependencies
			$trigger['expression'] = $expressionChanged ? $expressionFull : $dbTrigger['expression'];

			$infos[] = _s('Updated: Trigger "%1$s" on "%2$s".', $trigger['description'], implode(', ', $hosts));
			add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER, $dbTrigger['triggerid'],
					$dbTrigger['description'], null, $dbTrigger, $triggerUpdate);
		}
		unset($trigger);

		if ($changedPriorityTriggerIds && $this->usedInItServices($changedPriorityTriggerIds)) {
			updateItServices();
		}

		foreach ($infos as $info) {
			info($info);
		}
	}

	/**
	 * @param $data
	 *
	 * @return bool
	 */
	public function syncTemplates(array $data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$triggers = $this->get([
			'output' => [
				'triggerid', 'expression', 'description', 'url', 'status', 'priority', 'comments', 'type'
			],
			'hostids' => $data['templateids'],
			'preservekeys' => true
		]);

		$triggers = CMacrosResolverHelper::resolveTriggerExpressions($triggers);

		foreach ($triggers as $trigger) {
			$this->inherit($trigger, $data['hostids']);
		}

		return true;
	}

	/**
	 * Synchronizes the templated trigger dependencies on the given hosts inherited from the given
	 * templates.
	 * Update dependencies, do it after all triggers that can be dependent were created/updated on
	 * all child hosts/templates. Starting from highest level template triggers select triggers from
	 * one level lower, then for each lower trigger look if it's parent has dependencies, if so
	 * find this dependency trigger child on dependent trigger host and add new dependency.
	 *
	 * @param array $data
	 *
	 * @return void
	 */
	public function syncTemplateDependencies(array $data) {
		$templateIds = zbx_toArray($data['templateids']);
		$hostIds = zbx_toArray($data['hostids']);

		$parentTriggers = $this->get([
			'output' => ['triggerid'],
			'hostids' => $templateIds,
			'preservekeys' => true,
			'selectDependencies' => ['triggerid']
		]);

		if ($parentTriggers) {
			$childTriggers = $this->get([
				'output' => ['triggerid', 'templateid'],
				'hostids' => ($hostIds) ? $hostIds : null,
				'filter' => ['templateid' => array_keys($parentTriggers)],
				'nopermissions' => true,
				'preservekeys' => true,
				'selectHosts' => ['hostid']
			]);

			if ($childTriggers) {
				$newDependencies = [];
				foreach ($childTriggers as $childTrigger) {
					$parentDependencies = $parentTriggers[$childTrigger['templateid']]['dependencies'];
					if ($parentDependencies) {
						$dependencies = [];
						foreach ($parentDependencies as $depTrigger) {
							$dependencies[] = $depTrigger['triggerid'];
						}
						$host = reset($childTrigger['hosts']);
						$dependencies = replace_template_dependencies($dependencies, $host['hostid']);
						foreach ($dependencies as $depTriggerId) {
							$newDependencies[] = [
								'triggerid' => $childTrigger['triggerid'],
								'dependsOnTriggerid' => $depTriggerId
							];
						}
					}
				}
				$this->deleteDependencies($childTriggers);

				if ($newDependencies) {
					$this->addDependencies($newDependencies);
				}
			}
		}
	}

	/**
	 * Validates the dependencies of the given triggers.
	 *
	 * @param array $triggers list of triggers and corresponding dependencies
	 * @param int $triggers[]['triggerid'] trigger id
	 * @param array $triggers[]['dependencies'] list of trigger ids on which depends given trigger
	 *
	 * @trows APIException if any of the dependencies is invalid
	 */
	protected function checkDependencies(array $triggers) {
		foreach ($triggers as $trigger) {
			if (empty($trigger['dependencies'])) {
				continue;
			}

			// trigger templates
			$triggerTemplates = API::Template()->get([
				'output' => ['status', 'hostid'],
				'triggerids' => $trigger['triggerid'],
				'nopermissions' => true
			]);

			// forbid dependencies from hosts to templates
			if (!$triggerTemplates) {
				$triggerDependencyTemplates = API::Template()->get([
					'output' => ['templateid'],
					'triggerids' => $trigger['dependencies'],
					'nopermissions' => true,
					'limit' => 1
				]);
				if ($triggerDependencyTemplates) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot add dependency from a host to a template.'));
				}
			}

			// the trigger can't depend on itself
			if (in_array($trigger['triggerid'], $trigger['dependencies'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot create dependency on trigger itself.'));
			}

			// check circular dependency
			$downTriggerIds = [$trigger['triggerid']];
			do {
				// triggerid_down depends on triggerid_up
				$res = DBselect(
					'SELECT td.triggerid_up'.
					' FROM trigger_depends td'.
					' WHERE '.dbConditionInt('td.triggerid_down', $downTriggerIds)
				);

				// combine db dependencies with those to be added
				$upTriggersIds = [];
				while ($row = DBfetch($res)) {
					$upTriggersIds[] = $row['triggerid_up'];
				}
				foreach ($downTriggerIds as $id) {
					if (isset($triggers[$id]) && isset($triggers[$id]['dependencies'])) {
						$upTriggersIds = array_merge($upTriggersIds, $triggers[$id]['dependencies']);
					}
				}

				// if found trigger id is in dependent triggerids, there is a dependency loop
				$downTriggerIds = [];
				foreach ($upTriggersIds as $id) {
					if (bccomp($id, $trigger['triggerid']) == 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot create circular dependencies.'));
					}
					$downTriggerIds[] = $id;
				}
			} while (!empty($downTriggerIds));

			// fetch all templates that are used in dependencies
			$triggerDependencyTemplates = API::Template()->get([
				'output' => ['templateid'],
				'triggerids' => $trigger['dependencies'],
				'nopermissions' => true,
			]);
			$depTemplateIds = zbx_toHash(zbx_objectValues($triggerDependencyTemplates, 'templateid'));

			// run the check only if a templated trigger has dependencies on other templates
			$triggerTemplateIds = zbx_toHash(zbx_objectValues($triggerTemplates, 'hostid'));
			$tdiff = array_diff($depTemplateIds, $triggerTemplateIds);
			if (!empty($triggerTemplateIds) && !empty($depTemplateIds) && !empty($tdiff)) {
				$affectedTemplateIds = zbx_array_merge($triggerTemplateIds, $depTemplateIds);

				// create a list of all hosts, that are children of the affected templates
				$dbLowlvltpl = DBselect(
					'SELECT DISTINCT ht.templateid,ht.hostid,h.host'.
					' FROM hosts_templates ht,hosts h'.
					' WHERE h.hostid=ht.hostid'.
						' AND '.dbConditionInt('ht.templateid', $affectedTemplateIds)
				);
				$map = [];
				while ($lowlvltpl = DBfetch($dbLowlvltpl)) {
					if (!isset($map[$lowlvltpl['hostid']])) {
						$map[$lowlvltpl['hostid']] = [];
					}
					$map[$lowlvltpl['hostid']][$lowlvltpl['templateid']] = $lowlvltpl['host'];
				}

				// check that if some host is linked to the template, that the trigger belongs to,
				// the host must also be linked to all of the templates, that trigger dependencies point to
				foreach ($map as $templates) {
					foreach ($triggerTemplateIds as $triggerTemplateId) {
						// is the host linked to one of the trigger templates?
						if (isset($templates[$triggerTemplateId])) {
							// then make sure all of the dependency templates are also linked
							foreach ($depTemplateIds as $depTemplateId) {
								if (!isset($templates[$depTemplateId])) {
									self::exception(ZBX_API_ERROR_PARAMETERS,
										_s('Not all templates are linked to "%s".', reset($templates))
									);
								}
							}
							break;
						}
					}
				}
			}
		}
	}

	/**
	 * Check that none of the triggers have dependencies on their children. Checks only one level of inheritance, but
	 * since it is called on each inheritance step, also works for multiple inheritance levels.
	 *
	 * @throws APIException     if at least one trigger is dependent on its child
	 *
	 * @param array $triggers
	 */
	protected function checkDependencyParents(array $triggers) {
		// fetch all templated dependency trigger parents
		$depTriggerIds = [];
		foreach ($triggers as $trigger) {
			foreach ($trigger['dependencies'] as $depTriggerId) {
				$depTriggerIds[$depTriggerId] = $depTriggerId;
			}
		}
		$parentDepTriggers = DBfetchArray(DBSelect(
			'SELECT templateid,triggerid'.
			' FROM triggers'.
			' WHERE templateid>0'.
				' AND '.dbConditionInt('triggerid', $depTriggerIds)
		));
		if ($parentDepTriggers) {
			$parentDepTriggers = zbx_toHash($parentDepTriggers, 'triggerid');
			foreach ($triggers as $trigger) {
				foreach ($trigger['dependencies'] as $depTriggerId) {
					// check if the current trigger is the parent of the dependency trigger
					if (isset($parentDepTriggers[$depTriggerId])
							&& $parentDepTriggers[$depTriggerId]['templateid'] == $trigger['triggerid']) {

						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Trigger cannot be dependent on a trigger that is inherited from it.')
						);
					}
				}
			}
		}
	}

	/**
	 * Checks if the given dependencies contain duplicates.
	 *
	 * @throws APIException if the given dependencies contain duplicates
	 *
	 * @param array $triggers
	 */
	protected function checkDependencyDuplicates(array $triggers) {
		// check duplicates in array
		$uniqueTriggers = [];
		$duplicateTriggerId = null;
		foreach ($triggers as $trigger) {
			foreach ($trigger['dependencies'] as $dep) {
				if (isset($uniqueTriggers[$trigger['triggerid']][$dep])) {
					$duplicateTriggerId = $trigger['triggerid'];
					break 2;
				}
				else {
					$uniqueTriggers[$trigger['triggerid']][$dep] = 1;
				}
			}
		}

		if ($duplicateTriggerId === null) {
			// check if dependency already exists in DB
			foreach ($triggers as $trigger) {
				$dbUpTriggers = DBselect(
					'SELECT td.triggerid_up'.
					' FROM trigger_depends td'.
					' WHERE '.dbConditionInt('td.triggerid_up', $trigger['dependencies']).
					' AND td.triggerid_down='.zbx_dbstr($trigger['triggerid'])
				, 1);
				if (DBfetch($dbUpTriggers)) {
					$duplicateTriggerId = $trigger['triggerid'];
					break;
				}
			}
		}

		if ($duplicateTriggerId) {
			$dplTrigger = DBfetch(DBselect(
				'SELECT t.description'.
				' FROM triggers t'.
				' WHERE t.triggerid='.zbx_dbstr($duplicateTriggerId)
			));
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate dependencies in trigger "%1$s".', $dplTrigger['description'])
			);
		}
	}

	/**
	 * Check if all templates trigger belongs to are linked to same hosts.
	 *
	 * @throws APIException
	 *
	 * @param $trigger
	 *
	 * @return bool
	 */
	protected function validateItems(array $trigger) {
		$expressionData = new CTriggerExpression();
		$expressionData->parse($trigger['expression']);

		$templatesData = API::Template()->get([
			'output' => ['templateid'],
			'selectHosts' => ['hostid'],
			'selectTemplates' => ['templateid'],
			'filter' => ['host' => $expressionData->getHosts()],
			'nopermissions' => true,
			'preservekeys' => true
		]);
		$firstTemplate = array_pop($templatesData);
		if ($firstTemplate) {
			$compareLinks = array_merge(
				zbx_objectValues($firstTemplate['hosts'], 'hostid'),
				zbx_objectValues($firstTemplate['templates'], 'templateid')
			);

			foreach ($templatesData as $data) {
				$linkedTo = array_merge(
					zbx_objectValues($data['hosts'], 'hostid'),
					zbx_objectValues($data['templates'], 'templateid')
				);

				if (array_diff($compareLinks, $linkedTo) || array_diff($linkedTo, $compareLinks)) {
					self::exception(
						ZBX_API_ERROR_PARAMETERS,
						_s('Trigger "%s" belongs to templates with different linkages.', $trigger['description'])
					);
				}
			}
		}

		return true;
	}

	/**
	 * Check if user has read permissions for triggers.
	 *
	 * @param $ids
	 *
	 * @return bool
	 */
	public function isReadable(array $ids) {
		if (empty($ids)) {
			return true;
		}
		$ids = array_unique($ids);

		$count = $this->get([
			'triggerids' => $ids,
			'countOutput' => true
		]);

		return count($ids) == $count;
	}

	/**
	 *  Check if user has write permissions for triggers.
	 *
	 * @param $ids
	 *
	 * @return bool
	 */
	public function isWritable(array $ids) {
		if (empty($ids)) {
			return true;
		}
		$ids = array_unique($ids);

		$count = $this->get([
			'triggerids' => $ids,
			'editable' => true,
			'countOutput' => true
		]);

		return count($ids) == $count;
	}

	/**
	 * Returns true if the given expression contains templates.
	 *
	 * @param CTriggerExpression $exp
	 *
	 * @return bool
	 */
	protected function expressionHasTemplates(CTriggerExpression $expressionData) {
		$hosts = API::Host()->get([
			'output' => ['status'],
			'filter' => ['name' => $expressionData->getHosts()],
			'templated_hosts' => true
		]);

		foreach ($hosts as $host) {
			if ($host['status'] == HOST_STATUS_TEMPLATE) {
				return true;
			}
		}

		return false;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null && $options['expandDescription'] !== null) {
			$sqlParts = $this->addQuerySelect($this->fieldId('expression'), $sqlParts);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$triggerids = array_keys($result);

		// adding trigger dependencies
		if ($options['selectDependencies'] !== null && $options['selectDependencies'] != API_OUTPUT_COUNT) {
			$res = DBselect(
				'SELECT td.triggerid_up,td.triggerid_down'.
				' FROM trigger_depends td'.
				' WHERE '.dbConditionInt('td.triggerid_down', $triggerids)
			);
			$relationMap = new CRelationMap();
			while ($relation = DBfetch($res)) {
				$relationMap->addRelation($relation['triggerid_down'], $relation['triggerid_up']);
			}

			$dependencies = $this->get([
				'output' => $options['selectDependencies'],
				'triggerids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
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
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $items, 'items');
		}

		// adding discoveryrule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			$dbRules = DBselect(
				'SELECT id.parent_itemid,td.triggerid'.
				' FROM trigger_discovery td,item_discovery id,functions f'.
				' WHERE '.dbConditionInt('td.triggerid', $triggerids).
					' AND td.parent_triggerid=f.triggerid'.
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
				'preservekeys' => true,
			]);
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		// adding last event
		if ($options['selectLastEvent'] !== null) {
			foreach ($result as $triggerId => $trigger) {
				$result[$triggerId]['lastEvent'] = [];
			}

			if (is_array($options['selectLastEvent'])) {
				$pkFieldId = $this->pk('events');
				$outputFields = [
					'objectid' => $this->fieldId('objectid', 'e'),
					'ns' => $this->fieldId('ns', 'e'),
					$pkFieldId => $this->fieldId($pkFieldId, 'e')
				];

				foreach ($options['selectLastEvent'] as $field) {
					if ($this->hasField($field, 'events')) {
						$outputFields[$field] = $this->fieldId($field, 'e');
					}
				}

				$outputFields = implode(',', $outputFields);
			}
			else {
				$outputFields = 'e.*';
			}

			// due to performance issues, avoid using 'ORDER BY' for outter SELECT
			$dbEvents = DBselect(
				'SELECT '.$outputFields.
				' FROM events e'.
					' JOIN ('.
						'SELECT e2.source,e2.object,e2.objectid,MAX(clock) AS clock'.
						' FROM events e2'.
						' WHERE e2.source='.EVENT_SOURCE_TRIGGERS.
							' AND e2.object='.EVENT_OBJECT_TRIGGER.
							' AND '.dbConditionInt('e2.objectid', $triggerids).
						' GROUP BY e2.source,e2.object,e2.objectid'.
					') e3 ON e3.source=e.source'.
						' AND e3.object=e.object'.
						' AND e3.objectid=e.objectid'.
						' AND e3.clock=e.clock'
			);

			// in case there are multiple records with same 'clock' for one trigger, we'll get different 'ns'
			$lastEvents = [];

			while ($dbEvent = DBfetch($dbEvents)) {
				$triggerId = $dbEvent['objectid'];
				$ns = $dbEvent['ns'];

				// unset fields, that were not requested
				if (is_array($options['selectLastEvent'])) {
					if (!in_array('objectid', $options['selectLastEvent'])) {
						unset($dbEvent['objectid']);
					}
					if (!in_array('ns', $options['selectLastEvent'])) {
						unset($dbEvent['ns']);
					}
				}

				$lastEvents[$triggerId][$ns] = $dbEvent;
			}

			foreach ($lastEvents as $triggerId => $events) {
				// find max 'ns' for each trigger and that will be the 'lastEvent'
				$maxNs = max(array_keys($events));
				$result[$triggerId]['lastEvent'] = $events[$maxNs];
			}

			foreach ($lastEvents as $triggerId => $events) {
				// find max 'ns' for each trigger and that will be the 'lastEvent'
				$maxNs = max(array_keys($events));
				$result[$triggerId]['lastEvent'] = $events[$maxNs];
			}
		}

		return $result;
	}

	protected function applyQuerySortField($sortfield, $sortorder, $alias, array $sqlParts) {
		if ($sortfield === 'hostname') {
			$sqlParts['select']['hostname'] = 'h.name AS hostname';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where'][] = 't.triggerid = f.triggerid';
			$sqlParts['where'][] = 'f.itemid = i.itemid';
			$sqlParts['where'][] = 'i.hostid = h.hostid';
			$sqlParts['order'][] = 'h.name '.$sortorder;
		}
		else {
			$sqlParts = parent::applyQuerySortField($sortfield, $sortorder, $alias, $sqlParts);
		}

		return $sqlParts;
	}

	/**
	 * Checks if all of the given triggers are available for writing.
	 *
	 * @throws APIException     if a trigger is not writable or does not exist or is not normal
	 *
	 * @param array $triggerIds
	 */
	protected function checkPermissions(array $triggerIds) {
		if (!$this->isWritable($triggerIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Checks that none of the given triggers is inherited from a template.
	 *
	 * @throws APIException     if one of the triggers is inherited
	 *
	 * @param array $triggerIds
	 */
	protected function checkNotInherited(array $triggerIds) {
		$trigger = DBfetch(DBselect(
			'SELECT t.triggerid,t.description,t.expression'.
				' FROM triggers t'.
				' WHERE '.dbConditionInt('t.triggerid', $triggerIds).
				' AND t.templateid IS NOT NULL',
			1
		));
		if ($trigger) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Cannot delete templated trigger "%1$s:%2$s".', $trigger['description'],
					CMacrosResolverHelper::resolveTriggerExpression($trigger['expression'])
				)
			);
		}
	}

	protected function requiresPostSqlFiltering(array $options) {
		return $options['skipDependent'] !== null || $options['withLastEventUnacknowledged'] !== null;
	}

	protected function applyPostSqlFiltering(array $triggers, array $options) {
		$triggers = zbx_toHash($triggers, 'triggerid');

		// unset triggers which depend on at least one problem trigger upstream into dependency tree
		if ($options['skipDependent'] !== null) {
			// Result trigger IDs of all triggers in results.
			$resultTriggerIds = zbx_objectValues($triggers, 'triggerid');

			// Will contain IDs of all triggers on which some other trigger depends.
			$allUpTriggerIds = [];

			// Trigger dependency map.
			$downToUpTriggerIds = [];

			// Values (state) of each "up" trigger ID is stored in here.
			$upTriggerValues = [];

			// Will contain IDs of all triggers either disabled directly, or by having disabled item or disabled host.
			$disabledTriggerIds = [];

			// First loop uses result trigger IDs.
			$triggerIds = $resultTriggerIds;
			do {
				// Fetch all dependency records where "down" trigger IDs are in current iteration trigger IDs.
				$dbResult = DBselect(
					'SELECT d.triggerid_down,d.triggerid_up,t.value'.
					' FROM trigger_depends d,triggers t'.
					' WHERE d.triggerid_up=t.triggerid'.
					' AND '.dbConditionInt('d.triggerid_down', $triggerIds)
				);

				// Add trigger IDs as keys and empty arrays as values.
				$downToUpTriggerIds = $downToUpTriggerIds + array_fill_keys($triggerIds, []);

				$triggerIds = [];
				while ($dependency = DBfetch($dbResult)) {
					// Trigger ID for "down" trigger, which has dependencies.
					$downTriggerId = $dependency['triggerid_down'];

					// Trigger ID for "up" trigger, on which the other ("up") trigger depends.
					$upTriggerId = $dependency['triggerid_up'];

					// Add "up" trigger ID to mapping. We also index by $upTrigger because later these arrays
					// are combined with + and this way indexes and values do not break.
					$downToUpTriggerIds[$downTriggerId][$upTriggerId] = $upTriggerId;

					// Add ID of this "up" trigger to all known "up" triggers.
					$allUpTriggerIds[] = $upTriggerId;

					// Remember value of this "up" trigger.
					$upTriggerValues[$upTriggerId] = $dependency['value'];

					// Add ID of this "up" trigger to the list of trigger IDs which should be mapped.
					$triggerIds[] = $upTriggerId;
				}
			} while ($triggerIds);

			// Fetch trigger IDs for triggers that are disabled, have disabled items or disabled item hosts.
			$dbResult = DBSelect(
				'SELECT t.triggerid'.
				' FROM triggers t,functions f,items i,hosts h'.
				' WHERE t.triggerid=f.triggerid'.
				' AND f.itemid=i.itemid'.
				' AND i.hostid=h.hostid'.
				' AND ('.
				'i.status='.ITEM_STATUS_DISABLED.
				' OR h.status='.HOST_STATUS_NOT_MONITORED.
				' OR t.status='.TRIGGER_STATUS_DISABLED.
				')'.
				' AND '.dbConditionInt('t.triggerid', $allUpTriggerIds)
			);
			while ($row = DBfetch($dbResult)) {
				$resultTriggerId = $row['triggerid'];
				$disabledTriggerIds[$resultTriggerId] = $resultTriggerId;
			}

			// Now process all mapped dependencies and unset any disabled "up" triggers so they do not participate in
			// decisions regarding nesting resolution in next step.
			foreach ($downToUpTriggerIds as $downTriggerId => $upTriggerIds) {
				$upTriggerIdsToUnset = [];
				foreach ($upTriggerIds as $upTriggerId) {
					if (isset($disabledTriggerIds[$upTriggerId])) {
						unset($downToUpTriggerIds[$downTriggerId][$upTriggerId]);
					}
				}
			}

			// Resolve dependencies for all result set triggers.
			foreach ($resultTriggerIds as $resultTriggerId) {
				// We start with result trigger.
				$triggerIds = [$resultTriggerId];

				// This also is unrolled recursive function and is repeated until there are no more trigger IDs to
				// check, add and resolve.
				do {
					$nextTriggerIds = [];
					foreach ($triggerIds as $triggerId) {
						// Loop through all "up" triggers.
						foreach ($downToUpTriggerIds[$triggerId] as $upTriggerId) {
							if ($downToUpTriggerIds[$upTriggerId]) {
								// If there this "up" trigger has "up" triggers of it's own, merge them and proceed with recursion.
								$downToUpTriggerIds[$resultTriggerId] += $downToUpTriggerIds[$upTriggerId];

								// Add trigger ID to be processed in next loop iteration.
								$nextTriggerIds[] = $upTriggerId;
							}
						}
					}
					$triggerIds = $nextTriggerIds;
				} while ($triggerIds);
			}

			// Clean result set.
			foreach ($resultTriggerIds as $resultTriggerId) {
				foreach ($downToUpTriggerIds[$resultTriggerId] as $upTriggerId) {
					// If "up" trigger is in problem state, dependent trigger should not be returned and is removed
					// from results.
					if ($upTriggerValues[$upTriggerId] == TRIGGER_VALUE_TRUE) {
						unset($triggers[$resultTriggerId]);
					}
				}

				// Check if result trigger is disabled and if so, remove from results.
				if (isset($disabledTriggerIds[$resultTriggerId])) {
					unset($triggers[$resultTriggerId]);
				}
			}
		}

		// withLastEventUnacknowledged
		if (!is_null($options['withLastEventUnacknowledged'])) {
			$triggerIds = zbx_objectValues($triggers, 'triggerid');
			$eventIds = [];
			$eventsDb = DBselect(
				'SELECT MAX(e.eventid) AS eventid,e.objectid'.
					' FROM events e'.
					' WHERE e.object='.EVENT_OBJECT_TRIGGER.
					' AND e.source='.EVENT_SOURCE_TRIGGERS.
					' AND '.dbConditionInt('e.objectid', $triggerIds).
					' AND '.dbConditionInt('e.value', [TRIGGER_VALUE_TRUE]).
					' GROUP BY e.objectid'
			);
			while ($event = DBfetch($eventsDb)) {
				$eventIds[] = $event['eventid'];
			}

			$correctTriggerIds = DBfetchArrayAssoc(DBselect(
				'SELECT e.objectid'.
					' FROM events e '.
					' WHERE '.dbConditionInt('e.eventid', $eventIds).
					' AND e.acknowledged=0'
			), 'objectid');

			foreach ($triggers as $triggerId => $trigger) {
				if (!isset($correctTriggerIds[$triggerId])) {
					unset($triggers[$triggerId]);
				}
			}
		}

		return $triggers;
	}

	/**
	 * Returns true if at least one of the given triggers is used in IT services.
	 *
	 * @param array $triggerIds
	 *
	 * @return bool
	 */
	protected function usedInItServices(array $triggerIds) {
		$query = DBselect('SELECT serviceid FROM services WHERE '.dbConditionInt('triggerid', $triggerIds), 1);

		return (DBfetch($query) != false);
	}
}
