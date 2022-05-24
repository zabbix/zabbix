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
 * Class containing methods for operations with triggers.
 */
class CTrigger extends CTriggerGeneral {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'adddependencies' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'deletedependencies' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

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
			'functions'						=> null,
			'inherited'						=> null,
			'dependent'						=> null,
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
			'evaltype'						=> TAG_EVAL_TYPE_AND_OR,
			'tags'							=> null,
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
		if ($options['groupids'] !== null) {
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

		// functions
		if ($options['functions'] !== null) {
			zbx_value2array($options['functions']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sqlParts['where'][] = dbConditionString('f.name', $options['functions']);
		}

		// monitored
		if ($options['monitored'] !== null) {
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
		if ($options['active'] !== null) {
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
		if ($options['maintenance'] !== null) {
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
		if ($options['lastChangeSince'] !== null) {
			$sqlParts['where']['lastchangesince'] = 't.lastchange>'.zbx_dbstr($options['lastChangeSince']);
		}

		// lastChangeTill
		if ($options['lastChangeTill'] !== null) {
			$sqlParts['where']['lastchangetill'] = 't.lastchange<'.zbx_dbstr($options['lastChangeTill']);
		}

		// withUnacknowledgedEvents
		if ($options['withUnacknowledgedEvents'] !== null) {
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
		if ($options['withAcknowledgedEvents'] !== null) {
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

		// dependent
		if ($options['dependent'] !== null) {
			if ($options['dependent']) {
				$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM trigger_depends td'.
					' WHERE td.triggerid_down=t.triggerid'.
				')';
			}
			else {
				$sqlParts['where'][] = 'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM trigger_depends td'.
					' WHERE td.triggerid_down=t.triggerid'.
				')';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('triggers t', $options, $sqlParts);
		}

		// filter
		if ($options['filter'] === null) {
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

			if (array_key_exists('host', $options['filter']) && $options['filter']['host'] !== null) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['functions'] = 'functions f';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['host'] = dbConditionString('h.host', $options['filter']['host']);
			}

			if (array_key_exists('hostid', $options['filter']) && $options['filter']['hostid'] !== null) {
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
			$sqlParts['where']['ghg'] = 'g.groupid = hg.groupid';
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

		// only_true
		if ($options['only_true'] !== null) {
			$sqlParts['where']['ot'] = '((t.value='.TRIGGER_VALUE_TRUE.')'.
				' OR ((t.value='.TRIGGER_VALUE_FALSE.')'.
					' AND (t.lastchange>'.
					(time() - timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::OK_PERIOD))).
				'))'.
			')';
		}

		// min_severity
		if ($options['min_severity'] !== null) {
			$sqlParts['where'][] = 't.priority>='.zbx_dbstr($options['min_severity']);
		}

		// tags
		if ($options['tags'] !== null && $options['tags']) {
			$sqlParts['where'][] = CApiTagHelper::addWhereCondition($options['tags'], $options['evaltype'], 't',
				'trigger_tag', 'triggerid'
			);
		}

		// limit
		if (!zbx_ctype_digit($options['limit']) || !$options['limit']) {
			$options['limit'] = null;
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);

		// return count or grouped counts via direct SQL count
		if ($options['countOutput'] && !$this->requiresPostSqlFiltering($options)) {
			$dbRes = DBselect(self::createSelectQueryFromParts($sqlParts), $options['limit']);
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

		$result = zbx_toHash($this->customFetch(self::createSelectQueryFromParts($sqlParts), $options), 'triggerid');

		// return count for post SQL filtered result sets
		if ($options['countOutput']) {
			return (string) count($result);
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// expandDescription
		if ($options['expandDescription'] !== null && $result && array_key_exists('description', reset($result))) {
			$result = CMacrosResolverHelper::resolveTriggerNames($result);
		}

		// expandComment
		if ($options['expandComment'] !== null && $result && array_key_exists('comments', reset($result))) {
			$result = CMacrosResolverHelper::resolveTriggerDescriptions($result, ['sources' => ['comments']]);
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

		// Triggers share table with trigger prototypes. Therefore remove trigger unrelated fields.
		if ($this->outputIsRequested('discover', $options['output'])) {
			foreach ($result as &$row) {
				unset($row['discover']);
			}
			unset($row);
		}

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
		$this->validateCreate($triggers);

		$this->createReal($triggers);
		$this->checkDependenciesLinks($triggers);

		$this->inherit($triggers);

		$this->updateDependencies($triggers);

		return ['triggerids' => array_column($triggers, 'triggerid')];
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
	public function update(array $triggers): array {
		$this->validateUpdate($triggers, $db_triggers);

		$this->updateReal($triggers, $db_triggers);
		self::checkExistingDependencies($triggers, $db_triggers);

		$this->inherit($triggers);

		$this->updateDependencies($triggers, $db_triggers);

		return ['triggerids' => array_column($triggers, 'triggerid')];
	}

	/**
	 * Delete triggers.
	 *
	 * @param array $triggerids
	 *
	 * @return array
	 */
	public function delete(array $triggerids) {
		$this->validateDelete($triggerids, $db_triggers);

		CTriggerManager::delete($triggerids);

		$this->addAuditBulk(CAudit::ACTION_DELETE, CAudit::RESOURCE_TRIGGER, $db_triggers);

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
					_s('Cannot delete templated trigger "%1$s:%2$s".', $db_trigger['description'],
						CMacrosResolverHelper::resolveTriggerExpression($db_trigger['expression'])
					)
				);
			}
		}
	}

	/**
	 * Check the existing dependencies if the trigger expressions were changed.
	 *
	 * @param array $triggers
	 * @param array $db_triggers
	 */
	private static function checkExistingDependencies(array $triggers, array $db_triggers): void {
		$triggerids = [];
		$hostids = [];

		foreach ($triggers as $trigger) {
			if (array_key_exists('hosts', $db_triggers[$trigger['triggerid']])) {
				$triggerids[$trigger['triggerid']] = true;
				$hostids += $db_triggers[$trigger['triggerid']]['hosts'];
			}
		}

		if (!$triggerids) {
			return;
		}

		/*
		 * It's necessary to perform the check of existing dependencies only if, as the result of the expression change,
		 * the trigger no longer belongs to any of the previous hosts.
		 */
		$result = DBselect(
			'SELECT DISTINCT f.triggerid,i.hostid'.
			' FROM functions f,items i'.
			' WHERE f.itemid=i.itemid'.
				' AND '.dbConditionId('f.triggerid', array_keys($triggerids)).
				' AND '.dbConditionId('i.hostid', array_keys($hostids))
		);

		while ($row = DBfetch($result)) {
			if (array_key_exists($row['hostid'], $db_triggers[$row['triggerid']]['hosts'])) {
				unset($db_triggers[$row['triggerid']]['hosts'][$row['hostid']]);
			}
		}

		$trigger_dependencies = [];

		foreach ($triggers as $trigger) {
			if (!array_key_exists($trigger['triggerid'], $triggerids)
					|| !$db_triggers[$trigger['triggerid']]['hosts']) {
				continue;
			}

			$dependencies = array_key_exists('dependencies', $trigger)
				? $trigger['dependencies']
				: $db_triggers[$trigger['triggerid']]['dependencies'];

			foreach ($dependencies as $trigger_up) {
				$trigger_dependencies[$trigger_up['triggerid']][$trigger['triggerid']] = true;
			}
		}

		if (!$trigger_dependencies) {
			return;
		}

		/*
		 * There is no need to perform a check for dependency duplicates, because we are checking for existing
		 * dependencies that cannot have them. Also there is no need to perform the check on circular
		 * dependencies, because the dependencies are based on trigger IDs. Even if the expressions have changed, the
		 * trigger IDs remain the same.
		 */

		$trigger_hosts = self::getTriggerHosts($trigger_dependencies);

		/*
		 * The template trigger can become a host trigger. Therefore the template trigger dependencies may remain
		 * after the update.
		 */
		self::checkDependenciesOfHostTriggers($trigger_dependencies, $trigger_hosts);

		/*
		 * The template (or host) trigger can become a trigger of another template. If after the update
		 * the trigger became owned by another template and it also has a dependency on a trigger from another
		 * template remaining, then we should check that:
		 *  - Such dependency did not become a dependency on a trigger from the parent template.
		 *  - Such dependency did not become a dependency on a trigger from the child template or host.
		 *  - The template of the trigger-up is linked to all child templates of the new trigger's template.
		 */
		self::checkDependenciesOfTemplateTriggers($trigger_dependencies, $trigger_hosts);
	}

	/**
	 * Validates the input for the addDependencies() method.
	 *
	 * @param array      $triggers_data
	 * @param array|null $triggers
	 * @param array|null $db_triggers
	 *
	 * @throws APIException if the given dependencies are invalid.
	 */
	protected function validateAddDependencies(array $triggers_data, ?array &$triggers, ?array &$db_triggers): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['triggerid', 'dependsOnTriggerid']], 'fields' => [
			'triggerid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'dependsOnTriggerid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $triggers_data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$triggers = [];
		$trigger_dependencies = [];

		foreach ($triggers_data as $dependency) {
			$triggers[$dependency['triggerid']]['triggerid'] = $dependency['triggerid'];
			$triggers[$dependency['triggerid']]['dependencies'][] = ['triggerid' => $dependency['dependsOnTriggerid']];

			$trigger_dependencies[$dependency['dependsOnTriggerid']][$dependency['triggerid']] = true;
		}

		$triggers = array_values($triggers);

		$db_triggers = $this->get([
			'output' => ['triggerid', 'description', 'flags'],
			'triggerids' => array_column($triggers, 'triggerid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_triggers) != count($triggers)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($db_triggers as $db_trigger) {
			if ($db_trigger['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Cannot update "%2$s" for a discovered trigger "%1$s".',
					$db_trigger['description'], 'dependencies'
				));
			}
		}

		$count = $this->get([
			'countOutput' => true,
			'triggerids' => array_keys($trigger_dependencies)
		]);

		if ($count != count($trigger_dependencies)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkDependencyDuplicates($trigger_dependencies);
		self::checkCircularDependencies($trigger_dependencies);

		$trigger_hosts = self::getTriggerHosts($trigger_dependencies);

		self::checkDependenciesOfHostTriggers($trigger_dependencies, $trigger_hosts);
		self::checkDependenciesOfTemplateTriggers($trigger_dependencies, $trigger_hosts);

		foreach ($db_triggers as &$db_trigger) {
			$db_trigger['dependencies'] = [];
		}
		unset($db_trigger);
	}

	/**
	 * Add the given dependencies and inherit them on all child triggers.
	 *
	 * @param array $triggers_data  An array of trigger dependency pairs, each pair in the form of
	 *                              ['triggerid' => 1, 'dependsOnTriggerid' => 2].
	 *
	 * @return array
	 */
	public function addDependencies(array $triggers_data) {
		$this->validateAddDependencies($triggers_data, $triggers, $db_triggers);

		self::updateDependencies($triggers, $db_triggers);

		return ['triggerids' => array_column($triggers, 'triggerid')];
	}

	/**
	 * @param array      $triggers
	 * @param array|null $db_triggers
	 *
	 * @throws APIException if the given input is invalid
	 */
	private function validateDeleteDependencies(array &$triggers, ?array &$db_triggers): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['triggerid']], 'fields' => [
			'triggerid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $triggers, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_triggers = $this->get([
			'output' => ['description', 'flags'],
			'triggerids' => array_column($triggers, 'triggerid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_triggers) != count($triggers)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($db_triggers as $db_trigger) {
			if ($db_trigger['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Cannot update "%2$s" for a discovered trigger "%1$s".',
					$db_trigger['description'], 'dependencies'
				));
			}
		}

		foreach ($triggers as &$trigger) {
			$trigger['dependencies'] = [];
		}
		unset($trigger);

		self::addAffectedDependencies($triggers, $db_triggers);
	}

	/**
	 * Deletes all trigger dependencies from the given triggers and their children.
	 *
	 * @param array $triggers An array of triggers with the 'triggerid' field defined.
	 *
	 * @return array
	 */
	public function deleteDependencies(array $triggers) {
		$this->validateDeleteDependencies($triggers, $db_triggers);

		self::updateDependencies($triggers, $db_triggers);

		return ['triggerids' => array_column($triggers, 'triggerid')];
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
			$dependencies = [];
			$relationMap = new CRelationMap();
			$res = DBselect(
				'SELECT td.triggerid_up,td.triggerid_down'.
				' FROM trigger_depends td'.
				' WHERE '.dbConditionInt('td.triggerid_down', $triggerids)
			);
			while ($relation = DBfetch($res)) {
				$relationMap->addRelation($relation['triggerid_down'], $relation['triggerid_up']);
			}

			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$dependencies = $this->get([
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
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $items, 'items');
		}

		// adding discoveryrule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			$discoveryRules = [];
			$relationMap = new CRelationMap();
			$dbRules = DBselect(
				'SELECT id.parent_itemid,td.triggerid'.
				' FROM trigger_discovery td,item_discovery id,functions f'.
				' WHERE '.dbConditionInt('td.triggerid', $triggerids).
					' AND td.parent_triggerid=f.triggerid'.
					' AND f.itemid=id.itemid'
			);
			while ($rule = DBfetch($dbRules)) {
				$relationMap->addRelation($rule['triggerid'], $rule['parent_itemid']);
			}

			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$discoveryRules = API::DiscoveryRule()->get([
					'output' => $options['selectDiscoveryRule'],
					'itemids' => $related_ids,
					'nopermissions' => true,
					'preservekeys' => true
				]);
			}

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

			// Due to performance issues, avoid using 'ORDER BY' for outer SELECT.
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
			foreach (['parent_triggerid', 'ts_delete'] as $field) {
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
		if ($options['withLastEventUnacknowledged'] !== null) {
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
}
