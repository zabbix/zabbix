<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	 * @param bool  $options['editable']
	 * @param array $options['count']
	 * @param array $options['pattern']
	 * @param array $options['limit']
	 * @param array $options['order']
	 *
	 * @return array|int item data as array or false if error
	 */
	public function get(array $options = []) {
		$result = [];

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
			'editable'						=> false,
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
			'startSearch'					=> false,
			'excludeSearch'					=> false,
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
			'selectTags'					=> null,
			'selectTriggerDiscovery'		=> null,
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

			if ($options['groupCount']) {
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
			$sqlParts['where'][] = dbConditionString('f.name', $options['functions']);
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
			$sqlParts['from']['hstgrp'] = 'hstgrp g';
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
				' OR ((t.value='.TRIGGER_VALUE_FALSE.')'.
					' AND (t.lastchange>'.(time() - timeUnitToSeconds($config['ok_period'])).
				'))'.
			')';
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
		if ($options['countOutput'] && !$this->requiresPostSqlFiltering($options)) {
			$dbRes = DBselect($this->createSelectQueryFromParts($sqlParts), $options['limit']);
			while ($trigger = DBfetch($dbRes)) {
				if ($options['groupCount']) {
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
		if ($options['countOutput']) {
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

		$result = $this->unsetExtraFields($result, ['state', 'expression'], $options['output']);

		return $result;
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

		$this->validateCreate($triggers);
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
		$db_triggers = [];

		$this->validateUpdate($triggers, $db_triggers);

		$validate_dependencies = [];
		foreach ($triggers as $tnum => $trigger) {
			$db_trigger = $db_triggers[$tnum];

			$expressions_changed = ($trigger['expression'] !== $db_trigger['expression']
				|| $trigger['recovery_expression'] !== $db_trigger['recovery_expression']);

			if ($expressions_changed && $db_trigger['dependencies'] && !array_key_exists('dependencies', $trigger)) {
				$validate_dependencies[] = [
					'triggerid' => $trigger['triggerid'],
					'dependencies' => zbx_objectValues($db_trigger['dependencies'], 'triggerid')
				];
			}
		}

		if ($validate_dependencies) {
			$this->checkDependencies($validate_dependencies);
			$this->checkDependencyParents($validate_dependencies);
		}

		$this->updateReal($triggers, $db_triggers);

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

		return ['triggerids' => zbx_objectValues($triggers, 'triggerid')];
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
			'output' => ['triggerid', 'description'],
			'triggerids' => $triggerIds,
			'nopermissions' => true,
			'selectHosts' => ['name']
		]);

		// TODO: REMOVE info
		foreach ($delTriggers as $trigger) {
			info(_s('Deleted: Trigger "%1$s" on "%2$s".', $trigger['description'],
				implode(', ', zbx_objectValues($trigger['hosts'], 'name'))
			));

			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TRIGGER, $trigger['triggerid'], $trigger['description'],
				null, null, null
			);
		}

		// execute delete
		$this->deleteByIds($triggerIds);

		return ['triggerids' => $triggerIds];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array		$triggerids			Trigger IDs.
	 * @param bool		$nopermissions		If set to true permissions will not be checked.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateDelete(array $triggerids, $nopermissions) {
		if (!$triggerids) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		if (!$nopermissions) {
			$this->checkPermissions($triggerids);
			$this->checkNotInherited($triggerids);
		}
	}

	/**
	 * Delete trigger by ids.
	 *
	 * @param array $triggerIds
	 */
	protected function deleteByIds(array $triggerIds) {
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

		// Remove trigger sysmap elements.
		$selementids = [];

		$db_trigger_elements = DBselect(
			'SELECT st.selementid'.
			' FROM sysmap_element_trigger st'.
			' WHERE '.dbConditionInt('st.triggerid', $triggerIds)
		);

		while ($db_trigger_element = DBfetch($db_trigger_elements)) {
			$selementids[$db_trigger_element['selementid']] = true;
		}

		if ($selementids) {
			DB::delete('sysmap_element_trigger', ['triggerid' => $triggerIds]);

			$db_not_empty_elements = DBselect(
				'SELECT st.selementid'.
				' FROM sysmap_element_trigger st'.
				' WHERE '.dbConditionInt('st.selementid', array_keys($selementids))
			);
			while ($db_not_empty_element = DBfetch($db_not_empty_elements)) {
				unset($selementids[$db_not_empty_element['selementid']]);
			}

			DB::delete('sysmaps_elements', ['selementid' => array_keys($selementids)]);
		}

		$insert = [];
		foreach ($triggerIds as $triggerId) {
			$insert[] = [
				'tablename' => 'events',
				'field' => 'triggerid',
				'value' => $triggerId
			];
		}
		DB::insertBatch('housekeeper', $insert);

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

		$triggerids = array_unique(zbx_objectValues($triggersData, 'triggerid'));

		$triggers = $this->get([
			'output' => ['triggerid', 'description', 'flags'],
			'triggerids' => $triggerids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($triggerids as $triggerid) {
			if (!array_key_exists($triggerid, $triggers)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		foreach ($triggers as $trigger) {
			if ($trigger['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Cannot update "%2$s" for a discovered trigger "%1$s".',
					$trigger['description'], 'dependencies'
				));
			}
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

		if ($depTtriggerIds) {
			$count = $this->get([
				'countOutput' => true,
				'triggerids' => $depTtriggerIds
			]);

			if ($count != count($depTtriggerIds)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
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

		return ['triggerids' => array_unique(zbx_objectValues($triggersData, 'triggerid'))];
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

		$triggerids = array_unique(zbx_objectValues($triggers, 'triggerid'));

		$triggers = $this->get([
			'output' => ['triggerid', 'description', 'flags'],
			'triggerids' => $triggerids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($triggerids as $triggerid) {
			if (!array_key_exists($triggerid, $triggers)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		foreach ($triggers as $trigger) {
			if ($trigger['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Cannot update "%2$s" for a discovered trigger "%1$s".',
					$trigger['description'], 'dependencies'
				));
			}
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
	 * Synchronizes the templated trigger dependencies on the given hosts inherited from the given
	 * templates.
	 * Update dependencies, do it after all triggers that can be dependent were created/updated on
	 * all child hosts/templates. Starting from highest level template triggers select triggers from
	 * one level lower, then for each lower trigger look if it's parent has dependencies, if so
	 * find this dependency trigger child on dependent trigger host and add new dependency.
	 *
	 * @param array $data
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

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if (!$options['countOutput'] && $options['expandDescription'] !== null) {
			$sqlParts = $this->addQuerySelect($this->fieldId('expression'), $sqlParts);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		if (!$result) {
			return $result;
		}

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
		}

		// adding trigger discovery
		if ($options['selectTriggerDiscovery'] !== null && $options['selectTriggerDiscovery'] !== API_OUTPUT_COUNT) {
			foreach ($result as &$trigger) {
				$trigger['triggerDiscovery'] = [];
			}
			unset($trigger);

			$sql_select = ['triggerid'];
			foreach (['parent_triggerid'] as $field) {
				if ($this->outputIsRequested($field, $options['selectTriggerDiscovery'])) {
					$sql_select[] = $field;
				}
			}

			$trigger_discoveries = DBselect(
				'SELECT '.implode(',', $sql_select).
				' FROM trigger_discovery'.
				' WHERE '.dbConditionInt('triggerid', $triggerids)
			);

			while ($trigger_discovery = DBfetch($trigger_discoveries)) {
				$triggerid = $trigger_discovery['triggerid'];
				unset($trigger_discovery['triggerid']);

				$result[$triggerid]['triggerDiscovery'] = $trigger_discovery;
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
	 * @param array $triggerids
	 */
	protected function checkPermissions(array $triggerids) {
		if ($triggerids) {
			$triggerids = array_unique($triggerids);

			$count = $this->get([
				'countOutput' => true,
				'triggerids' => $triggerids,
				'editable' => true
			]);

			if ($count != count($triggerids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
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
	 * Returns true if at least one of the given triggers is used in services.
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
