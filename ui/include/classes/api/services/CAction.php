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
 * Class containing methods for operations with actions.
 */
class CAction extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	protected $tableName = 'actions';
	protected $tableAlias = 'a';
	protected $sortColumns = ['actionid', 'name', 'status'];

	/**
	 * Valid condition types for each event source.
	 *
	 * @var array
	 */
	private const VALID_CONDITION_TYPES = [
		EVENT_SOURCE_TRIGGERS => [
			CONDITION_TYPE_HOST_GROUP, CONDITION_TYPE_HOST, CONDITION_TYPE_TRIGGER, CONDITION_TYPE_TRIGGER_NAME,
			CONDITION_TYPE_TRIGGER_SEVERITY, CONDITION_TYPE_TIME_PERIOD, CONDITION_TYPE_TEMPLATE,
			CONDITION_TYPE_SUPPRESSED, CONDITION_TYPE_EVENT_TAG, CONDITION_TYPE_EVENT_TAG_VALUE
		],
		EVENT_SOURCE_DISCOVERY => [
			CONDITION_TYPE_DHOST_IP, CONDITION_TYPE_DSERVICE_TYPE, CONDITION_TYPE_DSERVICE_PORT, CONDITION_TYPE_DSTATUS,
			CONDITION_TYPE_DUPTIME, CONDITION_TYPE_DVALUE, CONDITION_TYPE_DRULE, CONDITION_TYPE_DCHECK,
			CONDITION_TYPE_PROXY, CONDITION_TYPE_DOBJECT
		],
		EVENT_SOURCE_AUTOREGISTRATION => [
			CONDITION_TYPE_PROXY, CONDITION_TYPE_HOST_NAME, CONDITION_TYPE_HOST_METADATA
		],
		EVENT_SOURCE_INTERNAL => [
			CONDITION_TYPE_HOST_GROUP, CONDITION_TYPE_HOST, CONDITION_TYPE_TEMPLATE, CONDITION_TYPE_EVENT_TYPE,
			CONDITION_TYPE_EVENT_TAG, CONDITION_TYPE_EVENT_TAG_VALUE
		],
		EVENT_SOURCE_SERVICE => [
			CONDITION_TYPE_SERVICE, CONDITION_TYPE_SERVICE_NAME, CONDITION_TYPE_EVENT_TAG,
			CONDITION_TYPE_EVENT_TAG_VALUE
		]
	];

	/**
	 * Operation group names.
	 *
	 * @var array
	 */
	private const OPERATION_GROUPS = [
		ACTION_OPERATION => 'operations',
		ACTION_RECOVERY_OPERATION => 'recovery_operations',
		ACTION_UPDATE_OPERATION => 'update_operations'
	];

	/**
	 * Get actions data
	 *
	 * @param array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['actionids']
	 * @param array $options['status']
	 * @param bool  $options['editable']
	 * @param array $options['extendoutput']
	 * @param array $options['count']
	 * @param array $options['pattern']
	 * @param array $options['limit']
	 * @param array $options['order']
	 *
	 * @return array|int item data as array or false if error
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['actions' => 'a.actionid'],
			'from'		=> ['actions' => 'actions a'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'						=> null,
			'hostids'						=> null,
			'actionids'						=> null,
			'triggerids'					=> null,
			'mediatypeids'					=> null,
			'usrgrpids'						=> null,
			'userids'						=> null,
			'scriptids'						=> null,
			'nopermissions'					=> null,
			'editable'						=> false,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectFilter'				=> null,
			'selectOperations'			=> null,
			'selectRecoveryOperations'	=> null,
			'selectUpdateOperations'	=> null,
			'countOutput'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			// conditions are checked here by sql, operations after, by api queries
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

			// condition hostgroup
			$sqlParts['where'][] = 'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM conditions cc'.
						' LEFT JOIN rights r'.
							' ON r.id='.zbx_dbcast_2bigint('cc.value').
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE a.actionid=cc.actionid'.
						' AND cc.conditiontype='.CONDITION_TYPE_HOST_GROUP.
					' GROUP BY cc.value'.
					' HAVING MIN(r.permission) IS NULL'.
						' OR MIN(r.permission)='.PERM_DENY.
						' OR MAX(r.permission)<'.zbx_dbstr($permission).
					')';

			// condition host or template
			$sqlParts['where'][] = 'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM conditions cc,hosts_groups hgg'.
						' LEFT JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE a.actionid=cc.actionid'.
						' AND '.zbx_dbcast_2bigint('cc.value').'=hgg.hostid'.
						' AND cc.conditiontype IN ('.CONDITION_TYPE_HOST.','.CONDITION_TYPE_TEMPLATE.')'.
					' GROUP BY cc.value'.
					' HAVING MIN(r.permission) IS NULL'.
						' OR MIN(r.permission)='.PERM_DENY.
						' OR MAX(r.permission)<'.zbx_dbstr($permission).
					')';

			// condition trigger
			$sqlParts['where'][] = 'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM conditions cc,functions f,items i,hosts_groups hgg'.
						' LEFT JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE a.actionid=cc.actionid'.
						' AND '.zbx_dbcast_2bigint('cc.value').'=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND i.hostid=hgg.hostid'.
						' AND cc.conditiontype='.CONDITION_TYPE_TRIGGER.
					' GROUP BY cc.value'.
					' HAVING MIN(r.permission) IS NULL'.
						' OR MIN(r.permission)='.PERM_DENY.
						' OR MAX(r.permission)<'.zbx_dbstr($permission).
					')';
		}

		// actionids
		if (!is_null($options['actionids'])) {
			zbx_value2array($options['actionids']);

			$sqlParts['where'][] = dbConditionInt('a.actionid', $options['actionids']);
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['conditions_groups'] = 'conditions cg';
			$sqlParts['where'][] = dbConditionString('cg.value', $options['groupids']);
			$sqlParts['where']['ctg'] = 'cg.conditiontype='.CONDITION_TYPE_HOST_GROUP;
			$sqlParts['where']['acg'] = 'a.actionid=cg.actionid';
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['from']['conditions_hosts'] = 'conditions ch';
			$sqlParts['where'][] = dbConditionString('ch.value', $options['hostids']);
			$sqlParts['where']['cth'] = 'ch.conditiontype='.CONDITION_TYPE_HOST;
			$sqlParts['where']['ach'] = 'a.actionid=ch.actionid';
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['from']['conditions_triggers'] = 'conditions ct';
			$sqlParts['where'][] = dbConditionString('ct.value', $options['triggerids']);
			$sqlParts['where']['ctt'] = 'ct.conditiontype='.CONDITION_TYPE_TRIGGER;
			$sqlParts['where']['act'] = 'a.actionid=ct.actionid';
		}

		// mediatypeids
		if (!is_null($options['mediatypeids'])) {
			zbx_value2array($options['mediatypeids']);

			$sqlParts['from']['opmessage'] = 'opmessage om';
			$sqlParts['from']['operations_media'] = 'operations omed';
			$sqlParts['where'][] = dbConditionId('om.mediatypeid', $options['mediatypeids']);
			$sqlParts['where']['aomed'] = 'a.actionid=omed.actionid';
			$sqlParts['where']['oom'] = 'omed.operationid=om.operationid';
		}

		// operation messages
		// usrgrpids
		if (!is_null($options['usrgrpids'])) {
			zbx_value2array($options['usrgrpids']);

			$sqlParts['from']['opmessage_grp'] = 'opmessage_grp omg';
			$sqlParts['from']['operations_usergroups'] = 'operations oug';
			$sqlParts['where'][] = dbConditionInt('omg.usrgrpid', $options['usrgrpids']);
			$sqlParts['where']['aoug'] = 'a.actionid=oug.actionid';
			$sqlParts['where']['oomg'] = 'oug.operationid=omg.operationid';
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);

			$sqlParts['from']['opmessage_usr'] = 'opmessage_usr omu';
			$sqlParts['from']['operations_users'] = 'operations ou';
			$sqlParts['where'][] = dbConditionInt('omu.userid', $options['userids']);
			$sqlParts['where']['aou'] = 'a.actionid=ou.actionid';
			$sqlParts['where']['oomu'] = 'ou.operationid=omu.operationid';
		}

		// operation commands
		// scriptids
		if (!is_null($options['scriptids'])) {
			zbx_value2array($options['scriptids']);

			$sqlParts['from']['opcommand'] = 'opcommand oc';
			$sqlParts['from']['operations_scripts'] = 'operations os';
			$sqlParts['where'][] = dbConditionInt('oc.scriptid', $options['scriptids']);
			$sqlParts['where']['aos'] = 'a.actionid=os.actionid';
			$sqlParts['where']['ooc'] = 'os.operationid=oc.operationid';
		}

		// filter
		if (is_array($options['filter'])) {
			if (array_key_exists('esc_period', $options['filter']) && $options['filter']['esc_period'] !== null) {
				$options['filter']['esc_period'] = getTimeUnitFilters($options['filter']['esc_period']);
			}

			$this->dbFilter('actions a', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('actions a', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$actionIds = [];

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$dbRes = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($action = DBfetch($dbRes)) {
			if ($options['countOutput']) {
				$result = $action['rowscount'];
			}
			else {
				$actionIds[$action['actionid']] = $action['actionid'];

				$result[$action['actionid']] = $action;
			}
		}

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			// check hosts, templates
			$hosts = [];
			$hostIds = [];
			$sql = 'SELECT o.actionid,och.hostid'.
					' FROM operations o,opcommand_hst och'.
					' WHERE o.operationid=och.operationid'.
						' AND och.hostid<>0'.
						' AND '.dbConditionInt('o.actionid', $actionIds);
			$dbHosts = DBselect($sql);
			while ($host = DBfetch($dbHosts)) {
				if (!isset($hosts[$host['hostid']])) {
					$hosts[$host['hostid']] = [];
				}
				$hosts[$host['hostid']][$host['actionid']] = $host['actionid'];
				$hostIds[$host['hostid']] = $host['hostid'];
			}

			$dbTemplates = DBselect(
				'SELECT o.actionid,ot.templateid'.
				' FROM operations o,optemplate ot'.
				' WHERE o.operationid=ot.operationid'.
					' AND '.dbConditionInt('o.actionid', $actionIds)
			);
			while ($template = DBfetch($dbTemplates)) {
				if (!isset($hosts[$template['templateid']])) {
					$hosts[$template['templateid']] = [];
				}
				$hosts[$template['templateid']][$template['actionid']] = $template['actionid'];
				$hostIds[$template['templateid']] = $template['templateid'];
			}

			$allowedHosts = API::Host()->get([
				'hostids' => $hostIds,
				'output' => ['hostid'],
				'editable' => $options['editable'],
				'templated_hosts' => true,
				'preservekeys' => true
			]);
			foreach ($hostIds as $hostId) {
				if (isset($allowedHosts[$hostId])) {
					continue;
				}
				foreach ($hosts[$hostId] as $actionId) {
					unset($result[$actionId], $actionIds[$actionId]);
				}
			}
			unset($allowedHosts);

			// check hostgroups
			$groups = [];
			$groupIds = [];
			$dbGroups = DBselect(
				'SELECT o.actionid,ocg.groupid'.
				' FROM operations o,opcommand_grp ocg'.
				' WHERE o.operationid=ocg.operationid'.
					' AND '.dbConditionInt('o.actionid', $actionIds)
			);
			while ($group = DBfetch($dbGroups)) {
				if (!isset($groups[$group['groupid']])) {
					$groups[$group['groupid']] = [];
				}
				$groups[$group['groupid']][$group['actionid']] = $group['actionid'];
				$groupIds[$group['groupid']] = $group['groupid'];
			}

			$dbGroups = DBselect(
				'SELECT o.actionid,og.groupid'.
				' FROM operations o,opgroup og'.
				' WHERE o.operationid=og.operationid'.
					' AND '.dbConditionInt('o.actionid', $actionIds)
			);
			while ($group = DBfetch($dbGroups)) {
				if (!isset($groups[$group['groupid']])) {
					$groups[$group['groupid']] = [];
				}
				$groups[$group['groupid']][$group['actionid']] = $group['actionid'];
				$groupIds[$group['groupid']] = $group['groupid'];
			}

			$allowedGroups = API::HostGroup()->get([
				'groupids' => $groupIds,
				'output' => ['groupid'],
				'editable' => $options['editable'],
				'preservekeys' => true
			]);
			foreach ($groupIds as $groupId) {
				if (isset($allowedGroups[$groupId])) {
					continue;
				}
				foreach ($groups[$groupId] as $actionId) {
					unset($result[$actionId], $actionIds[$actionId]);
				}
			}
			unset($allowedGroups);

			// check scripts
			$scripts = [];
			$scriptIds = [];
			$dbScripts = DBselect(
				'SELECT o.actionid,oc.scriptid'.
				' FROM operations o,opcommand oc'.
				' WHERE o.operationid=oc.operationid'.
					' AND '.dbConditionInt('o.actionid', $actionIds)
			);
			while ($script = DBfetch($dbScripts)) {
				if (!isset($scripts[$script['scriptid']])) {
					$scripts[$script['scriptid']] = [];
				}
				$scripts[$script['scriptid']][$script['actionid']] = $script['actionid'];
				$scriptIds[$script['scriptid']] = $script['scriptid'];
			}

			$allowedScripts = API::Script()->get([
				'output' => ['scriptid'],
				'scriptids' => $scriptIds,
				'filter' => ['scope' => ZBX_SCRIPT_SCOPE_ACTION],
				'preservekeys' => true
			]);
			foreach ($scriptIds as $scriptId) {
				if (isset($allowedScripts[$scriptId])) {
					continue;
				}
				foreach ($scripts[$scriptId] as $actionId) {
					unset($result[$actionId], $actionIds[$actionId]);
				}
			}
			unset($allowedScripts);

			// check users
			$users = [];
			$userIds = [];
			$dbUsers = DBselect(
				'SELECT o.actionid,omu.userid'.
				' FROM operations o,opmessage_usr omu'.
				' WHERE o.operationid=omu.operationid'.
					' AND '.dbConditionInt('o.actionid', $actionIds)
			);
			while ($user = DBfetch($dbUsers)) {
				if (!isset($users[$user['userid']])) {
					$users[$user['userid']] = [];
				}
				$users[$user['userid']][$user['actionid']] = $user['actionid'];
				$userIds[$user['userid']] = $user['userid'];
			}

			$allowedUsers = API::User()->get([
				'userids' => $userIds,
				'output' => ['userid'],
				'preservekeys' => true
			]);
			foreach ($userIds as $userId) {
				if (isset($allowedUsers[$userId])) {
					continue;
				}
				foreach ($users[$userId] as $actionId) {
					unset($result[$actionId], $actionIds[$actionId]);
				}
			}

			// check usergroups
			$userGroups = [];
			$userGroupIds = [];
			$dbUserGroups = DBselect(
				'SELECT o.actionid,omg.usrgrpid'.
				' FROM operations o,opmessage_grp omg'.
				' WHERE o.operationid=omg.operationid'.
					' AND '.dbConditionInt('o.actionid', $actionIds)
			);
			while ($userGroup = DBfetch($dbUserGroups)) {
				if (!isset($userGroups[$userGroup['usrgrpid']])) {
					$userGroups[$userGroup['usrgrpid']] = [];
				}
				$userGroups[$userGroup['usrgrpid']][$userGroup['actionid']] = $userGroup['actionid'];
				$userGroupIds[$userGroup['usrgrpid']] = $userGroup['usrgrpid'];
			}

			$allowedUserGroups = API::UserGroup()->get([
				'usrgrpids' => $userGroupIds,
				'output' => ['usrgrpid'],
				'preservekeys' => true
			]);

			foreach ($userGroupIds as $userGroupId) {
				if (isset($allowedUserGroups[$userGroupId])) {
					continue;
				}
				foreach ($userGroups[$userGroupId] as $actionId) {
					unset($result[$actionId], $actionIds[$actionId]);
				}
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);

			foreach ($result as &$action) {
				// unset the fields that are returned in the filter
				unset($action['formula'], $action['evaltype']);

				if ($options['selectFilter'] !== null) {
					$filter = $this->unsetExtraFields(
						[$action['filter']],
						['conditions', 'formula', 'evaltype'],
						$options['selectFilter']
					);
					$filter = reset($filter);

					if (isset($filter['conditions'])) {
						foreach ($filter['conditions'] as &$condition) {
							unset($condition['actionid'], $condition['conditionid']);
						}
						unset($condition);
					}

					$action['filter'] = $filter;
				}
			}
			unset($action);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * @param array $actions
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function create(array $actions): array {
		$this->validateCreate($actions);

		$ins_actions = [];

		foreach ($actions as $action) {
			if (array_key_exists('filter', $action)) {
				$action['evaltype'] = $action['filter']['evaltype'];
			}

			$ins_actions[] = $action;
		}

		$actionids = DB::insert('actions', $ins_actions);

		foreach ($actions as $index => &$action) {
			$action['actionid'] = $actionids[$index];
		}
		unset($action);

		self::updateFilter($actions);
		self::updateOperations($actions);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_ACTION, $actions);

		return ['actionids' => $actionids];
	}

	/**
	 * @param array $actions
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function update(array $actions): array {
		$this->validateUpdate($actions, $db_actions);

		$upd_actions = [];

		foreach ($actions as $action) {
			$db_action = $db_actions[$action['actionid']];

			if (array_key_exists('filter', $action)) {
				$action['evaltype'] = $action['filter']['evaltype'];
				$db_action['evaltype'] = $db_action['filter']['evaltype'];
			}

			$upd_action = DB::getUpdatedValues('actions', $action, $db_action);

			if ($upd_action) {
				$upd_actions[] = [
					'values' => $upd_action,
					'where' => ['actionid' => $action['actionid']]
				];
			}
		}

		if ($upd_actions) {
			DB::update('actions', $upd_actions);
		}

		self::updateFilter($actions, $db_actions);
		self::updateOperations($actions, $db_actions);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_ACTION, $actions, $db_actions);

		return ['actionids' => array_column($actions, 'actionid')];
	}

	/**
	 * @param array      $actions
	 * @param array|null $db_actions
	 */
	private static function updateFilter(array &$actions, array $db_actions = null): void {
		$is_update = ($db_actions !== null);

		$ins_conditions = [];
		$upd_conditions = [];
		$del_conditionids = [];

		foreach ($actions as &$action) {
			if (!array_key_exists('filter', $action) || !array_key_exists('conditions', $action['filter'])) {
				continue;
			}

			$db_conditions = $is_update ? $db_actions[$action['actionid']]['filter']['conditions'] : [];

			foreach ($action['filter']['conditions'] as &$condition) {
				$db_condition = current(
					array_filter($db_conditions, static function(array $db_condition) use ($condition): bool {
						if ($condition['conditiontype'] == CONDITION_TYPE_SUPPRESSED) {
							return $condition['conditiontype'] == $db_condition['conditiontype'];
						}

						if ($condition['conditiontype'] == CONDITION_TYPE_EVENT_TAG_VALUE) {
							return $condition['conditiontype'] == $db_condition['conditiontype']
								&& $condition['value2'] === $db_condition['value2'];
						}

						return $condition['conditiontype'] == $db_condition['conditiontype']
							&& $condition['value'] == $db_condition['value'];
					})
				);

				if ($db_condition) {
					$condition['conditionid'] = $db_condition['conditionid'];
					unset($db_conditions[$db_condition['conditionid']]);

					$upd_condition = DB::getUpdatedValues('conditions', $condition, $db_condition);

					if ($upd_condition) {
						$upd_conditions[] = [
							'values' => $upd_condition,
							'where' => ['conditionid' => $db_condition['conditionid']]
						];
					}
				}
				else {
					$ins_conditions[] = ['actionid' => $action['actionid']] + $condition;
				}
			}
			unset($condition);

			$del_conditionids = array_merge($del_conditionids, array_keys($db_conditions));
		}
		unset($action);

		if ($del_conditionids) {
			DB::delete('conditions', ['conditionid' => $del_conditionids]);
		}

		if ($upd_conditions) {
			DB::update('conditions', $upd_conditions);
		}

		if ($ins_conditions) {
			$conditionids = DB::insert('conditions', $ins_conditions);
		}

		foreach ($actions as &$action) {
			if (!array_key_exists('filter', $action) || !array_key_exists('conditions', $action['filter'])) {
				continue;
			}

			foreach ($action['filter']['conditions'] as &$condition) {
				if (!array_key_exists('conditionid', $condition)) {
					$condition['conditionid'] = array_shift($conditionids);
				}
			}
			unset($condition);
		}
		unset($action);

		// Update formula.
		$upd_actions = [];

		foreach ($actions as &$action) {
			if (!array_key_exists('filter', $action)) {
				continue;
			}

			$action['filter']['formula'] = ($action['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION)
				? CConditionHelper::replaceLetterIds($action['filter']['formula'],
					array_column($action['filter']['conditions'], 'conditionid', 'formulaid')
				)
				: '';

			$db_formula = $is_update ? $db_actions[$action['actionid']]['filter']['formula'] : '';

			if ($action['filter']['formula'] !== $db_formula) {
				$upd_actions[] = [
					'values' => ['formula' => $action['filter']['formula']],
					'where' => ['actionid' => $action['actionid']]
				];
			}
		}
		unset($action);

		if ($upd_actions) {
			DB::update('actions', $upd_actions);
		}
	}

	/**
	 * @param array      $actions
	 * @param array|null $db_actions
	 */
	private static function updateOperations(array &$actions, array $db_actions = null): void {
		$is_update = ($db_actions !== null);

		$ins_operations = [];
		$upd_operations = [];
		$del_operationids = [];

		foreach ($actions as &$action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				$db_operations = $is_update ? $db_actions[$action['actionid']][$operation_group] : [];

				foreach ($action[$operation_group] as &$operation) {
					$db_operation = current(
						array_filter($db_operations, static function (array $db_operation) use ($operation): bool {
							return $operation['operationtype'] == $db_operation['operationtype']
								&& $operation['recovery'] == $db_operation['recovery'];
						})
					);

					if ($db_operation) {
						$operation['operationid'] = $db_operation['operationid'];
						unset($db_operations[$operation['operationid']]);

						$upd_operation = DB::getUpdatedValues('operations', $operation, $db_operation);

						if ($upd_operation) {
							$upd_operations[] = [
								'values' => $upd_operation,
								'where' => ['operationid' => $db_operation['operationid']]
							];
						}
					}
					else {
						$ins_operations[] = ['actionid' => $action['actionid']] + $operation;
					}
				}
				unset($operation);

				$del_operationids = array_merge($del_operationids, array_keys($db_operations));
			}
		}
		unset($action);

		if ($del_operationids) {
			DB::delete('operations', ['operationid' => $del_operationids]);
		}

		if ($upd_operations) {
			DB::update('operations', $upd_operations);
		}

		if ($ins_operations) {
			$operationids = DB::insert('operations', $ins_operations);
		}

		foreach ($actions as &$action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				foreach ($action[$operation_group] as &$operation) {
					if (!array_key_exists('operationid', $operation)) {
						$operation['operationid'] = array_shift($operationids);
					}
				}
				unset($operation);
			}
		}
		unset($action);

		self::updateOperationConditions($actions, $db_actions);
		self::updateOperationMessages($actions, $db_actions);
		self::updateOperationCommands($actions, $db_actions);
		self::updateOperationGroups($actions, $db_actions);
		self::updateOperationTemplates($actions, $db_actions);
		self::updateOperationInventories($actions, $db_actions);
	}

	/**
	 * @param array      $actions
	 * @param array|null $db_actions
	 */
	private static function updateOperationConditions(array &$actions, array $db_actions = null): void {
		$is_update = ($db_actions !== null);

		$ins_opconditions = [];
		$upd_opconditions = [];
		$del_opconditionids = [];

		foreach ($actions as &$action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				$db_operations = $is_update ? $db_actions[$action['actionid']][$operation_group] : [];

				foreach ($action[$operation_group] as &$operation) {
					if (!array_key_exists('opconditions', $operation)) {
						continue;
					}

					$db_operation = array_key_exists($operation['operationid'], $db_operations)
						? $db_operations[$operation['operationid']]
						: [];

					$db_opconditions = array_key_exists('opconditions', $db_operation)
						? array_column($db_operation['opconditions'], null, 'value')
						: [];

					foreach ($operation['opconditions'] as &$opcondition) {
						if (array_key_exists($opcondition['value'], $db_opconditions)) {
							$db_opcondition = $db_opconditions[$opcondition['value']];

							$opcondition['opconditionid'] = $db_opcondition['opconditionid'];
							unset($db_opconditions[$opcondition['value']]);

							$upd_opcondition = DB::getUpdatedValues('opconditions', $opcondition, $db_opcondition);

							if ($upd_opcondition) {
								$upd_opconditions[] = [
									'values' => $upd_opcondition,
									'where' => ['operationid' => $operation['operationid']]
								];
							}
						}
						else {
							$ins_opconditions[] = ['operationid' => $operation['operationid']] + $opcondition;
						}
					}
					unset($opcondition);

					$del_opconditionids = array_merge($del_opconditionids,
						array_column($db_opconditions, 'opconditionid')
					);
				}
				unset($operation);
			}
		}
		unset($action);

		if ($del_opconditionids) {
			DB::delete('opconditions', ['opconditionid' => $del_opconditionids]);
		}

		if ($upd_opconditions) {
			DB::update('opconditions', $upd_opconditions);
		}

		if ($ins_opconditions) {
			$opconditionids = DB::insert('opconditions', $ins_opconditions);
		}

		foreach ($actions as &$action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				foreach ($action[$operation_group] as &$operation) {
					if (!array_key_exists('opconditions', $operation)) {
						continue;
					}

					foreach ($operation['opconditions'] as &$opcondition) {
						if (!array_key_exists('opconditionid', $opcondition)) {
							$opcondition['opconditionid'] = array_shift($opconditionids);
						}
					}
					unset($opcondition);
				}
				unset($operation);
			}
		}
		unset($action);
	}

	/**
	 * @param array      $actions
	 * @param array|null $db_actions
	 */
	private static function updateOperationMessages(array &$actions, array $db_actions = null): void {
		$is_update = ($db_actions !== null);

		$ins_opmessages = [];
		$upd_opmessages = [];

		$ins_opmessage_grps = [];
		$del_opmessage_grpids = [];

		$ins_opmessage_usrs = [];
		$del_opmessage_usrids = [];

		foreach ($actions as &$action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				$db_operations = $is_update ? $db_actions[$action['actionid']][$operation_group] : [];

				foreach ($action[$operation_group] as &$operation) {
					$db_operation = array_key_exists($operation['operationid'], $db_operations)
						? $db_operations[$operation['operationid']]
						: [];

					switch ($operation['operationtype']) {
						case OPERATION_TYPE_MESSAGE:
							if (array_key_exists('opmessage_grp', $operation)) {
								$db_opmessage_grps = array_key_exists('opmessage_grp', $db_operation)
									? array_column($db_operation['opmessage_grp'], null, 'usrgrpid')
									: [];

								foreach ($operation['opmessage_grp'] as &$opmessage_grp) {
									if (array_key_exists($opmessage_grp['usrgrpid'], $db_opmessage_grps)) {
										$db_opmessage_grp = $db_opmessage_grps[$opmessage_grp['usrgrpid']];
										$opmessage_grp['opmessage_grpid'] = $db_opmessage_grp['opmessage_grpid'];
										unset($db_opmessage_grps[$opmessage_grp['usrgrpid']]);
									}
									else {
										$ins_opmessage_grps[] =
											['operationid' => $operation['operationid']] + $opmessage_grp;
									}
								}
								unset($opmessage_grp);

								$del_opmessage_grpids = array_merge($del_opmessage_grpids,
									array_column($db_opmessage_grps, 'opmessage_grpid')
								);
							}

							if (array_key_exists('opmessage_usr', $operation)) {
								$db_opmessage_usrs = array_key_exists('opmessage_usr', $db_operation)
									? array_column($db_operation['opmessage_usr'], null, 'userid')
									: [];

								foreach ($operation['opmessage_usr'] as &$opmessage_usr) {
									if (array_key_exists($opmessage_usr['userid'], $db_opmessage_usrs)) {
										$db_opmessage_usr = $db_opmessage_usrs[$opmessage_usr['userid']];
										$opmessage_usr['opmessage_usrid'] = $db_opmessage_usr['opmessage_usrid'];
										unset($db_opmessage_usrs[$opmessage_usr['userid']]);
									}
									else {
										$ins_opmessage_usrs[] =
											['operationid' => $operation['operationid']] + $opmessage_usr;
									}
								}
								unset($opmessage_usr);

								$del_opmessage_usrids = array_merge($del_opmessage_usrids,
									array_column($db_opmessage_usrs, 'opmessage_usrid')
								);
							}
							// break; is not missing here

						case OPERATION_TYPE_RECOVERY_MESSAGE:
						case OPERATION_TYPE_UPDATE_MESSAGE:
							if (array_key_exists('opmessage', $db_operation)) {
								$upd_opmessage = DB::getUpdatedValues('opmessage', $operation['opmessage'],
									$db_operation['opmessage']
								);

								if ($upd_opmessage) {
									$upd_opmessages[] = [
										'values' => $upd_opmessage,
										'where' => ['operationid' => $operation['operationid']]
									];
								}
							}
							else {
								$ins_opmessages[] =
									['operationid' => $operation['operationid']] + $operation['opmessage'];
							}
							break;
					}
				}
				unset($operation);
			}
		}
		unset($action);

		if ($upd_opmessages) {
			DB::update('opmessage', $upd_opmessages);
		}

		if ($ins_opmessages) {
			DB::insert('opmessage', $ins_opmessages, false);
		}

		if ($del_opmessage_grpids) {
			DB::delete('opmessage_grp', ['opmessage_grpid' => $del_opmessage_grpids]);
		}

		if ($ins_opmessage_grps) {
			$opmessage_grpids = DB::insert('opmessage_grp', $ins_opmessage_grps);
		}

		if ($del_opmessage_usrids) {
			DB::delete('opmessage_usr', ['opmessage_usrid' => $del_opmessage_usrids]);
		}

		if ($ins_opmessage_usrs) {
			$opmessage_usrids = DB::insert('opmessage_usr', $ins_opmessage_usrs);
		}

		foreach ($actions as &$action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				foreach ($action[$operation_group] as &$operation) {
					if (array_key_exists('opmessage_grp', $operation)) {
						foreach ($operation['opmessage_grp'] as &$opmessage_grp) {
							if (!array_key_exists('opmessage_grpid', $opmessage_grp)) {
								$opmessage_grp['opmessage_grpid'] = array_shift($opmessage_grpids);
							}
						}
						unset($opmessage_grp);
					}

					if (array_key_exists('opmessage_usr', $operation)) {
						foreach ($operation['opmessage_usr'] as &$opmessage_usr) {
							if (!array_key_exists('opmessage_usrid', $opmessage_usr)) {
								$opmessage_usr['opmessage_usrid'] = array_shift($opmessage_usrids);
							}
						}
						unset($opmessage_usr);
					}
				}
				unset($operation);
			}
		}
		unset($action);
	}

	/**
	 * @param array      $actions
	 * @param array|null $db_actions
	 */
	private static function updateOperationCommands(array &$actions, array $db_actions = null): void {
		$is_update = ($db_actions !== null);

		$ins_opcommands = [];
		$upd_opcommands = [];

		$ins_opcommand_grps = [];
		$del_opcommand_grpids = [];

		$ins_opcommand_hsts = [];
		$del_opcommand_hstids = [];

		foreach ($actions as &$action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				$db_operations = $is_update ? $db_actions[$action['actionid']][$operation_group] : [];

				foreach ($action[$operation_group] as &$operation) {
					if ($operation['operationtype'] != OPERATION_TYPE_COMMAND) {
						continue;
					}

					$db_operation = array_key_exists($operation['operationid'], $db_operations)
						? $db_operations[$operation['operationid']]
						: [];

					if (array_key_exists('opcommand', $db_operation)) {
						$upd_opcommand = DB::getUpdatedValues('opcommand', $operation['opcommand'],
							$db_operation['opcommand']
						);

						if ($upd_opcommand) {
							$upd_opcommands[] = [
								'values' => $upd_opcommand,
								'where' => ['operationid' => $operation['operationid']]
							];
						}
					}
					else {
						$ins_opcommands[] =
							['operationid' => $operation['operationid']] + $operation['opcommand'];
					}

					if (array_key_exists('opcommand_grp', $operation)) {
						$db_opcommand_grps = array_key_exists('opcommand_grp', $db_operation)
							? array_column($db_operation['opcommand_grp'], null, 'groupid')
							: [];

						foreach ($operation['opcommand_grp'] as &$opcommand_grp) {
							if (array_key_exists($opcommand_grp['groupid'], $db_opcommand_grps)) {
								$db_opcommand_grp = $db_opcommand_grps[$opcommand_grp['groupid']];
								$opcommand_grp['opcommand_grpid'] = $db_opcommand_grp['opcommand_grpid'];
								unset($db_opcommand_grps[$opcommand_grp['groupid']]);
							}
							else {
								$ins_opcommand_grps[] =
									['operationid' => $operation['operationid']] + $opcommand_grp;
							}
						}
						unset($opcommand_grp);

						$del_opcommand_grpids = array_merge($del_opcommand_grpids,
							array_column($db_opcommand_grps, 'opcommand_grpid')
						);
					}

					if (array_key_exists('opcommand_hst', $operation)) {
						$db_opcommand_hsts = array_key_exists('opcommand_hst', $db_operation)
							? array_column($db_operation['opcommand_hst'], null, 'hostid')
							: [];

						foreach ($operation['opcommand_hst'] as &$opcommand_hst) {
							if (array_key_exists($opcommand_hst['hostid'], $db_opcommand_hsts)) {
								$db_opcommand_hst = $db_opcommand_hsts[$opcommand_hst['hostid']];
								$opcommand_hst['opcommand_hstid'] = $db_opcommand_hst['opcommand_hstid'];
								unset($db_opcommand_hsts[$opcommand_hst['hostid']]);
							}
							else {
								$ins_opcommand_hsts[] =
									['operationid' => $operation['operationid']] + $opcommand_hst;
							}
						}
						unset($opcommand_hst);

						$del_opcommand_hstids = array_merge($del_opcommand_hstids,
							array_column($db_opcommand_hsts, 'opcommand_hstid')
						);
					}
				}
				unset($operation);
			}
		}
		unset($action);

		if ($del_opcommand_grpids) {
			DB::delete('opcommand_grp', ['opcommand_grpid' => $del_opcommand_grpids]);
		}

		if ($del_opcommand_hstids) {
			DB::delete('opcommand_hst', ['opcommand_hstid' => $del_opcommand_hstids]);
		}

		if ($upd_opcommands) {
			DB::update('opcommand', $upd_opcommands);
		}

		if ($ins_opcommands) {
			DB::insert('opcommand', $ins_opcommands, false);
		}

		if ($ins_opcommand_grps) {
			$opcommand_grpids = DB::insert('opcommand_grp', $ins_opcommand_grps);
		}

		if ($ins_opcommand_hsts) {
			$opcommand_hstids = DB::insert('opcommand_hst', $ins_opcommand_hsts);
		}

		foreach ($actions as &$action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				foreach ($action[$operation_group] as &$operation) {
					if (array_key_exists('opcommand_grp', $operation)) {
						foreach ($operation['opcommand_grp'] as &$opcommand_grp) {
							if (!array_key_exists('opcommand_grpid', $opcommand_grp)) {
								$opcommand_grp['opcommand_grpid'] = array_shift($opcommand_grpids);
							}
						}
						unset($opcommand_grp);
					}

					if (array_key_exists('opcommand_hst', $operation)) {
						foreach ($operation['opcommand_hst'] as &$opcommand_hst) {
							if (!array_key_exists('opcommand_hstid', $opcommand_hst)) {
								$opcommand_hst['opcommand_hstid'] = array_shift($opcommand_hstids);
							}
						}
						unset($opcommand_hst);
					}
				}
				unset($operation);
			}
		}
		unset($action);
	}

	/**
	 * @param array      $actions
	 * @param array|null $db_actions
	 */
	private static function updateOperationGroups(array &$actions, array $db_actions = null): void {
		$is_update = ($db_actions !== null);

		$ins_opgroups = [];
		$del_opgroupids = [];

		foreach ($actions as &$action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				$db_operations = $is_update ? $db_actions[$action['actionid']][$operation_group] : [];

				foreach ($action[$operation_group] as &$operation) {
					// Proceed only if operation type is OPERATION_TYPE_GROUP_ADD or OPERATION_TYPE_GROUP_REMOVE.
					if (!array_key_exists('opgroup', $operation)) {
						continue;
					}

					$db_operation = array_key_exists($operation['operationid'], $db_operations)
						? $db_operations[$operation['operationid']]
						: [];

					$db_opgroups = array_key_exists('opgroup', $db_operation)
						? array_column($db_operation['opgroup'], null, 'groupid')
						: [];

					foreach ($operation['opgroup'] as &$opgroup) {
						if (array_key_exists($opgroup['groupid'], $db_opgroups)) {
							$db_opgroup = $db_opgroups[$opgroup['groupid']];
							$opgroup['opgroupid'] = $db_opgroup['opgroupid'];
							unset($db_opgroups[$opgroup['groupid']]);
						}
						else {
							$ins_opgroups[] = ['operationid' => $operation['operationid']] + $opgroup;
						}
					}
					unset($opgroup);

					$del_opgroupids = array_merge($del_opgroupids, array_column($db_opgroups, 'opgroupid'));
				}
				unset($operation);
			}
		}
		unset($action);

		if ($del_opgroupids) {
			DB::delete('opgroup', ['opgroupid' => $del_opgroupids]);
		}

		if ($ins_opgroups) {
			$opgroupids = DB::insert('opgroup', $ins_opgroups);
		}

		foreach ($actions as &$action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				foreach ($action[$operation_group] as &$operation) {
					if (!array_key_exists('opgroup', $operation)) {
						continue;
					}

					foreach ($operation['opgroup'] as &$opgroup) {
						if (!array_key_exists('opgroupid', $opgroup)) {
							$opgroup['opgroupid'] = array_shift($opgroupids);
						}
					}
					unset($opgroup);
				}
				unset($operation);
			}
		}
		unset($action);
	}

	/**
	 * @param array      $actions
	 * @param array|null $db_actions
	 */
	private static function updateOperationTemplates(array &$actions, array $db_actions = null): void {
		$is_update = ($db_actions !== null);

		$ins_optemplates = [];
		$del_optemplateids = [];

		foreach ($actions as &$action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				$db_operations = $is_update ? $db_actions[$action['actionid']][$operation_group] : [];

				foreach ($action[$operation_group] as &$operation) {
					// Proceed only if operation type is OPERATION_TYPE_TEMPLATE_ADD or OPERATION_TYPE_TEMPLATE_REMOVE.
					if (!array_key_exists('optemplate', $operation)) {
						continue;
					}

					$db_operation = array_key_exists($operation['operationid'], $db_operations)
						? $db_operations[$operation['operationid']]
						: [];

					$db_optemplates = array_key_exists('optemplate', $db_operation)
						? array_column($db_operation['optemplate'], null, 'templateid')
						: [];

					foreach ($operation['optemplate'] as &$optemplate) {
						if (array_key_exists($optemplate['templateid'], $db_optemplates)) {
							$db_optemplate = $db_optemplates[$optemplate['templateid']];
							$optemplate['optemplateid'] = $db_optemplate['optemplateid'];
							unset($db_optemplates[$optemplate['templateid']]);
						}
						else {
							$ins_optemplates[] = ['operationid' => $operation['operationid']] + $optemplate;
						}
					}
					unset($optemplate);

					$del_optemplateids = array_merge($del_optemplateids, array_column($db_optemplates, 'optemplateid'));
				}
				unset($operation);
			}
		}
		unset($action);

		if ($del_optemplateids) {
			DB::delete('optemplate', ['optemplateid' => $del_optemplateids]);
		}

		if ($ins_optemplates) {
			$optemplateids = DB::insert('optemplate', $ins_optemplates);
		}

		foreach ($actions as &$action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				foreach ($action[$operation_group] as &$operation) {
					if (!array_key_exists('optemplate', $operation)) {
						continue;
					}

					foreach ($operation['optemplate'] as &$optemplate) {
						if (!array_key_exists('optemplateid', $optemplate)) {
							$optemplate['optemplateid'] = array_shift($optemplateids);
						}
					}
					unset($optemplate);
				}
				unset($operation);
			}
		}
		unset($action);
	}

	/**
	 * @param array      $actions
	 * @param array|null $db_actions
	 */
	private static function updateOperationInventories(array $actions, array $db_actions = null): void {
		$is_update = ($db_actions !== null);

		$ins_opinventories = [];
		$upd_opinventories = [];

		foreach ($actions as $action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				$db_operations = $is_update ? $db_actions[$action['actionid']][$operation_group] : [];

				foreach ($action[$operation_group] as $operation) {
					if ($operation['operationtype'] != OPERATION_TYPE_HOST_INVENTORY) {
						continue;
					}

					$db_operation = array_key_exists($operation['operationid'], $db_operations)
						? $db_operations[$operation['operationid']]
						: [];

					if (array_key_exists('opinventory', $db_operation)) {
						$upd_opinventory = DB::getUpdatedValues('opinventory', $operation['opinventory'],
							$db_operation['opinventory']
						);

						if ($upd_opinventory) {
							$upd_opinventories[] = [
								'values' => $upd_opinventory,
								'where' => ['operationid' => $operation['operationid']]
							];
						}
					}
					else {
						$ins_opinventories[] =
							['operationid' => $operation['operationid']] + $operation['opinventory'];
					}
				}
			}
		}

		if ($upd_opinventories) {
			DB::update('opinventory', $upd_opinventories);
		}

		if ($ins_opinventories) {
			DB::insert('opinventory', $ins_opinventories, false);
		}
	}

	/**
	 * @param array $actionids
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function delete(array $actionids): array {
		$this->validateDelete($actionids, $db_actions);

		$operationids = array_keys(DB::select('operations', [
			'output' => ['operationid'],
			'filter' => ['actionid' => $actionids],
			'preservekeys' => true
		]));

		DB::delete('opcommand', ['operationid' => $operationids]);
		DB::delete('opcommand_grp', ['operationid' => $operationids]);
		DB::delete('opcommand_hst', ['operationid' => $operationids]);
		DB::delete('opmessage', ['operationid' => $operationids]);
		DB::delete('opmessage_grp', ['operationid' => $operationids]);
		DB::delete('opmessage_usr', ['operationid' => $operationids]);
		DB::delete('opgroup', ['operationid' => $operationids]);
		DB::delete('optemplate', ['operationid' => $operationids]);
		DB::delete('opinventory', ['operationid' => $operationids]);
		DB::delete('opconditions', ['operationid' => $operationids]);

		DB::delete('operations', ['actionid' => $actionids]);
		DB::delete('conditions', ['actionid' => $actionids]);
		DB::delete('actions', ['actionid' => $actionids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_ACTION, $db_actions);

		return ['actionids' => $actionids];
	}

	/**
	 * @param array      $actionids
	 * @param array|null $db_actions
	 *
	 * @throws APIException
	 */
	private function validateDelete(array &$actionids, ?array &$db_actions): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $actionids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_actions = $this->get([
			'output' => ['actionid', 'name'],
			'actionids' => $actionids,
			'editable' => true
		]);

		if (count($db_actions) != count($actionids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$actionIds = array_keys($result);

		// adding formulas
		if ($options['selectFilter'] !== null) {
			$formulaRequested = $this->outputIsRequested('formula', $options['selectFilter']);
			$evalFormulaRequested = $this->outputIsRequested('eval_formula', $options['selectFilter']);
			$conditionsRequested = $this->outputIsRequested('conditions', $options['selectFilter']);

			$filters = [];
			foreach ($result as $action) {
				$filters[$action['actionid']] = [
					'evaltype' => $action['evaltype'],
					'formula' => isset($action['formula']) ? $action['formula'] : ''
				];
			}

			if ($formulaRequested || $evalFormulaRequested || $conditionsRequested) {
				$conditions = API::getApiService()->select('conditions', [
						'output' => ['actionid', 'conditionid', 'conditiontype', 'operator', 'value', 'value2'],
						'filter' => ['actionid' => $actionIds],
					'preservekeys' => true
				]);

				$relationMap = $this->createRelationMap($conditions, 'actionid', 'conditionid');
				$filters = $relationMap->mapMany($filters, $conditions, 'conditions');

				foreach ($filters as &$filter) {
					// in case of a custom expression - use the given formula
					if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						$formula = $filter['formula'];
					}
					// in other cases - generate the formula automatically
					else {
						$conditions = $filter['conditions'];

						// sort conditions
						$sortFields = [
							['field' => 'conditiontype', 'order' => ZBX_SORT_DOWN],
							['field' => 'operator', 'order' => ZBX_SORT_DOWN],
							['field' => 'value2', 'order' => ZBX_SORT_DOWN],
							['field' => 'value', 'order' => ZBX_SORT_DOWN]
						];
						CArrayHelper::sort($conditions, $sortFields);

						$conditionsForFormula = [];
						foreach ($conditions as $condition) {
							$conditionsForFormula[$condition['conditionid']] = $condition['conditiontype'];
						}
						$formula = CConditionHelper::getFormula($conditionsForFormula, $filter['evaltype']);
					}

					// generate formulaids from the effective formula
					$formulaIds = CConditionHelper::getFormulaIds($formula);
					foreach ($filter['conditions'] as &$condition) {
						$condition['formulaid'] = $formulaIds[$condition['conditionid']];
					}
					unset($condition);

					// generated a letter based formula only for actions with custom expressions
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
			foreach ($result as &$action) {
				$action['filter'] = $filters[$action['actionid']];
			}
			unset($action);
		}

		// Adding operations.
		if ($options['selectOperations'] !== null && $options['selectOperations'] != API_OUTPUT_COUNT) {
			$operations = API::getApiService()->select('operations', [
				'output' => $this->outputExtend($options['selectOperations'],
					['operationid', 'actionid', 'operationtype']
				),
				'filter' => ['actionid' => $actionIds, 'recovery' => ACTION_OPERATION],
				'preservekeys' => true
			]);
			$relationMap = $this->createRelationMap($operations, 'actionid', 'operationid');
			$operationIds = $relationMap->getRelatedIds();

			if ($this->outputIsRequested('opconditions', $options['selectOperations'])) {
				foreach ($operations as &$operation) {
					$operation['opconditions'] = [];
				}
				unset($operation);

				$res = DBselect('SELECT op.* FROM opconditions op WHERE '.dbConditionInt('op.operationid', $operationIds));
				while ($opcondition = DBfetch($res)) {
					$operations[$opcondition['operationid']]['opconditions'][] = $opcondition;
				}
			}

			$opmessage = [];
			$opcommand = [];
			$opgroup = [];
			$optemplate = [];
			$opinventory = [];

			foreach ($operations as $operationid => $operation) {
				unset($operations[$operationid]['recovery']);

				switch ($operation['operationtype']) {
					case OPERATION_TYPE_MESSAGE:
						$opmessage[] = $operationid;
						break;
					case OPERATION_TYPE_COMMAND:
						$opcommand[] = $operationid;
						break;
					case OPERATION_TYPE_GROUP_ADD:
					case OPERATION_TYPE_GROUP_REMOVE:
						$opgroup[] = $operationid;
						break;
					case OPERATION_TYPE_TEMPLATE_ADD:
					case OPERATION_TYPE_TEMPLATE_REMOVE:
						$optemplate[] = $operationid;
						break;
					case OPERATION_TYPE_HOST_ADD:
					case OPERATION_TYPE_HOST_REMOVE:
					case OPERATION_TYPE_HOST_ENABLE:
					case OPERATION_TYPE_HOST_DISABLE:
						break;
					case OPERATION_TYPE_HOST_INVENTORY:
						$opinventory[] = $operationid;
						break;
				}
			}

			// get OPERATION_TYPE_MESSAGE data
			if ($opmessage) {
				if ($this->outputIsRequested('opmessage', $options['selectOperations'])) {
					foreach ($opmessage as $operationId) {
						$operations[$operationId]['opmessage'] = [];
					}

					$db_opmessages = DBselect(
						'SELECT o.operationid,o.default_msg,o.subject,o.message,o.mediatypeid'.
						' FROM opmessage o'.
						' WHERE '.dbConditionInt('o.operationid', $opmessage)
					);
					while ($db_opmessage = DBfetch($db_opmessages)) {
						$operationid = $db_opmessage['operationid'];
						unset($db_opmessage['operationid']);
						$operations[$operationid]['opmessage'] = $db_opmessage;
					}
				}

				if ($this->outputIsRequested('opmessage_grp', $options['selectOperations'])) {
					foreach ($opmessage as $operationId) {
						$operations[$operationId]['opmessage_grp'] = [];
					}

					$db_opmessages_grp = DBselect(
						'SELECT og.operationid,og.usrgrpid'.
						' FROM opmessage_grp og'.
						' WHERE '.dbConditionInt('og.operationid', $opmessage)
					);
					while ($db_opmessage_grp = DBfetch($db_opmessages_grp)) {
						$operationid = $db_opmessage_grp['operationid'];
						unset($db_opmessage_grp['operationid']);
						$operations[$operationid]['opmessage_grp'][] = $db_opmessage_grp;
					}
				}

				if ($this->outputIsRequested('opmessage_usr', $options['selectOperations'])) {
					foreach ($opmessage as $operationId) {
						$operations[$operationId]['opmessage_usr'] = [];
					}

					$db_opmessages_usr = DBselect(
						'SELECT ou.operationid,ou.userid'.
						' FROM opmessage_usr ou'.
						' WHERE '.dbConditionInt('ou.operationid', $opmessage)
					);
					while ($db_opmessage_usr = DBfetch($db_opmessages_usr)) {
						$operationid = $db_opmessage_usr['operationid'];
						unset($db_opmessage_usr['operationid']);
						$operations[$operationid]['opmessage_usr'][] = $db_opmessage_usr;
					}
				}
			}

			// get OPERATION_TYPE_COMMAND data
			if ($opcommand) {
				if ($this->outputIsRequested('opcommand', $options['selectOperations'])) {
					foreach ($opcommand as $operationId) {
						$operations[$operationId]['opcommand'] = [];
					}

					$db_opcommands = DBselect(
						'SELECT o.operationid,o.scriptid'.
						' FROM opcommand o'.
						' WHERE '.dbConditionInt('o.operationid', $opcommand)
					);
					while ($db_opcommand = DBfetch($db_opcommands)) {
						$operationid = $db_opcommand['operationid'];
						unset($db_opcommand['operationid']);
						$operations[$operationid]['opcommand'] = $db_opcommand;
					}
				}

				if ($this->outputIsRequested('opcommand_hst', $options['selectOperations'])) {
					foreach ($opcommand as $operationId) {
						$operations[$operationId]['opcommand_hst'] = [];
					}

					$db_opcommands_hst = DBselect(
						'SELECT oh.opcommand_hstid,oh.operationid,oh.hostid'.
						' FROM opcommand_hst oh'.
						' WHERE '.dbConditionInt('oh.operationid', $opcommand)
					);
					while ($db_opcommand_hst = DBfetch($db_opcommands_hst)) {
						$operationid = $db_opcommand_hst['operationid'];
						unset($db_opcommand_hst['operationid']);
						$operations[$operationid]['opcommand_hst'][] = $db_opcommand_hst;
					}
				}

				if ($this->outputIsRequested('opcommand_grp', $options['selectOperations'])) {
					foreach ($opcommand as $operationId) {
						$operations[$operationId]['opcommand_grp'] = [];
					}

					$db_opcommands_grp = DBselect(
						'SELECT og.opcommand_grpid,og.operationid,og.groupid'.
						' FROM opcommand_grp og'.
						' WHERE '.dbConditionInt('og.operationid', $opcommand)
					);
					while ($db_opcommand_grp = DBfetch($db_opcommands_grp)) {
						$operationid = $db_opcommand_grp['operationid'];
						unset($db_opcommand_grp['operationid']);
						$operations[$operationid]['opcommand_grp'][] = $db_opcommand_grp;
					}
				}
			}

			// get OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE data
			if ($opgroup) {
				if ($this->outputIsRequested('opgroup', $options['selectOperations'])) {
					foreach ($opgroup as $operationId) {
						$operations[$operationId]['opgroup'] = [];
					}

					$db_opgroups = DBselect(
						'SELECT o.operationid,o.groupid'.
						' FROM opgroup o'.
						' WHERE '.dbConditionInt('o.operationid', $opgroup)
					);
					while ($db_opgroup = DBfetch($db_opgroups)) {
						$operationid = $db_opgroup['operationid'];
						unset($db_opgroup['operationid']);
						$operations[$operationid]['opgroup'][] = $db_opgroup;
					}
				}
			}

			// get OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_TEMPLATE_REMOVE data
			if ($optemplate) {
				if ($this->outputIsRequested('optemplate', $options['selectOperations'])) {
					foreach ($optemplate as $operationId) {
						$operations[$operationId]['optemplate'] = [];
					}

					$db_optemplates = DBselect(
						'SELECT o.operationid,o.templateid'.
						' FROM optemplate o'.
						' WHERE '.dbConditionInt('o.operationid', $optemplate)
					);
					while ($db_optemplate = DBfetch($db_optemplates)) {
						$operationid = $db_optemplate['operationid'];
						unset($db_optemplate['operationid']);
						$operations[$operationid]['optemplate'][] = $db_optemplate;
					}
				}
			}

			// get OPERATION_TYPE_HOST_INVENTORY data
			if ($opinventory) {
				if ($this->outputIsRequested('opinventory', $options['selectOperations'])) {
					foreach ($opinventory as $operationId) {
						$operations[$operationId]['opinventory'] = [];
					}

					$db_opinventories = DBselect(
						'SELECT o.operationid,o.inventory_mode'.
						' FROM opinventory o'.
						' WHERE '.dbConditionInt('o.operationid', $opinventory)
					);
					while ($db_opinventory = DBfetch($db_opinventories)) {
						$operationid = $db_opinventory['operationid'];
						unset($db_opinventory['operationid']);
						$operations[$operationid]['opinventory'] = $db_opinventory;
					}
				}
			}

			$operations = $this->unsetExtraFields($operations, ['operationid', 'actionid', 'operationtype'],
				$options['selectOperations']
			);
			$result = $relationMap->mapMany($result, $operations, 'operations');
		}

		// Adding recovery operations.
		if ($options['selectRecoveryOperations'] !== null && $options['selectRecoveryOperations'] != API_OUTPUT_COUNT) {
			$recovery_operations = API::getApiService()->select('operations', [
				'output' => $this->outputExtend($options['selectRecoveryOperations'],
					['operationid', 'actionid', 'operationtype']
				),
				'filter' => ['actionid' => $actionIds, 'recovery' => ACTION_RECOVERY_OPERATION],
				'preservekeys' => true
			]);

			$relationMap = $this->createRelationMap($recovery_operations, 'actionid', 'operationid');
			$recovery_operationids = $relationMap->getRelatedIds();

			if ($this->outputIsRequested('opconditions', $options['selectRecoveryOperations'])) {
				foreach ($recovery_operations as &$recovery_operation) {
					unset($recovery_operation['esc_period'], $recovery_operation['esc_step_from'],
						$recovery_operation['esc_step_to']
					);

					$recovery_operation['opconditions'] = [];
				}
				unset($recovery_operation);

				$res = DBselect('SELECT op.* FROM opconditions op WHERE '.
					dbConditionInt('op.operationid', $recovery_operationids)
				);
				while ($opcondition = DBfetch($res)) {
					$recovery_operations[$opcondition['operationid']]['opconditions'][] = $opcondition;
				}
			}

			$opmessage = [];
			$opcommand = [];
			$op_recovery_message = [];

			foreach ($recovery_operations as $recovery_operationid => $recovery_operation) {
				unset($recovery_operations[$recovery_operationid]['recovery']);

				switch ($recovery_operation['operationtype']) {
					case OPERATION_TYPE_MESSAGE:
						$opmessage[] = $recovery_operationid;
						break;
					case OPERATION_TYPE_COMMAND:
						$opcommand[] = $recovery_operationid;
						break;
					case OPERATION_TYPE_RECOVERY_MESSAGE:
						$op_recovery_message[] = $recovery_operationid;
						break;
				}
			}

			// Get OPERATION_TYPE_MESSAGE data.
			if ($opmessage) {
				if ($this->outputIsRequested('opmessage', $options['selectRecoveryOperations'])) {
					foreach ($opmessage as $recovery_operationid) {
						$recovery_operations[$recovery_operationid]['opmessage'] = [];
					}

					$db_opmessages = DBselect(
						'SELECT o.operationid,o.default_msg,o.subject,o.message,o.mediatypeid'.
						' FROM opmessage o'.
						' WHERE '.dbConditionInt('o.operationid', $opmessage)
					);
					while ($db_opmessage = DBfetch($db_opmessages)) {
						$operationid = $db_opmessage['operationid'];
						unset($db_opmessage['operationid']);
						$recovery_operations[$operationid]['opmessage'] = $db_opmessage;
					}
				}

				if ($this->outputIsRequested('opmessage_grp', $options['selectRecoveryOperations'])) {
					foreach ($opmessage as $recovery_operationid) {
						$recovery_operations[$recovery_operationid]['opmessage_grp'] = [];
					}

					$db_opmessages_grp = DBselect(
						'SELECT og.operationid,og.usrgrpid'.
						' FROM opmessage_grp og'.
						' WHERE '.dbConditionInt('og.operationid', $opmessage)
					);
					while ($db_opmessage_grp = DBfetch($db_opmessages_grp)) {
						$operationid = $db_opmessage_grp['operationid'];
						unset($db_opmessage_grp['operationid']);
						$recovery_operations[$operationid]['opmessage_grp'][] = $db_opmessage_grp;
					}
				}

				if ($this->outputIsRequested('opmessage_usr', $options['selectRecoveryOperations'])) {
					foreach ($opmessage as $recovery_operationid) {
						$recovery_operations[$recovery_operationid]['opmessage_usr'] = [];
					}

					$db_opmessages_usr = DBselect(
						'SELECT ou.operationid,ou.userid'.
						' FROM opmessage_usr ou'.
						' WHERE '.dbConditionInt('ou.operationid', $opmessage)
					);
					while ($db_opmessage_usr = DBfetch($db_opmessages_usr)) {
						$operationid = $db_opmessage_usr['operationid'];
						unset($db_opmessage_usr['operationid']);
						$recovery_operations[$operationid]['opmessage_usr'][] = $db_opmessage_usr;
					}
				}
			}

			// Get OPERATION_TYPE_COMMAND data.
			if ($opcommand) {
				if ($this->outputIsRequested('opcommand', $options['selectRecoveryOperations'])) {
					foreach ($opcommand as $recovery_operationid) {
						$recovery_operations[$recovery_operationid]['opcommand'] = [];
					}

					$db_opcommands = DBselect(
						'SELECT o.operationid,o.scriptid'.
						' FROM opcommand o'.
						' WHERE '.dbConditionInt('o.operationid', $opcommand)
					);
					while ($db_opcommand = DBfetch($db_opcommands)) {
						$operationid = $db_opcommand['operationid'];
						unset($db_opcommand['operationid']);
						$recovery_operations[$operationid]['opcommand'] = $db_opcommand;
					}
				}

				if ($this->outputIsRequested('opcommand_hst', $options['selectRecoveryOperations'])) {
					foreach ($opcommand as $recovery_operationid) {
						$recovery_operations[$recovery_operationid]['opcommand_hst'] = [];
					}

					$db_opcommands_hst = DBselect(
						'SELECT oh.opcommand_hstid,oh.operationid,oh.hostid'.
						' FROM opcommand_hst oh'.
						' WHERE '.dbConditionInt('oh.operationid', $opcommand)
					);
					while ($db_opcommand_hst = DBfetch($db_opcommands_hst)) {
						$operationid = $db_opcommand_hst['operationid'];
						unset($db_opcommand_hst['operationid']);
						$recovery_operations[$operationid]['opcommand_hst'][] = $db_opcommand_hst;
					}
				}

				if ($this->outputIsRequested('opcommand_grp', $options['selectRecoveryOperations'])) {
					foreach ($opcommand as $recovery_operationid) {
						$recovery_operations[$recovery_operationid]['opcommand_grp'] = [];
					}

					$db_opcommands_grp = DBselect(
						'SELECT og.opcommand_grpid,og.operationid,og.groupid'.
						' FROM opcommand_grp og'.
						' WHERE '.dbConditionInt('og.operationid', $opcommand)
					);
					while ($db_opcommand_grp = DBfetch($db_opcommands_grp)) {
						$operationid = $db_opcommand_grp['operationid'];
						unset($db_opcommand_grp['operationid']);
						$recovery_operations[$operationid]['opcommand_grp'][] = $db_opcommand_grp;
					}
				}
			}

			// get OPERATION_TYPE_RECOVERY_MESSAGE data
			if ($op_recovery_message) {
				if ($this->outputIsRequested('opmessage', $options['selectRecoveryOperations'])) {
					foreach ($op_recovery_message as $operationid) {
						$recovery_operations[$operationid]['opmessage'] = [];
					}

					$db_opmessages = DBselect(
						'SELECT o.operationid,o.default_msg,o.subject,o.message,o.mediatypeid'.
						' FROM opmessage o'.
						' WHERE '.dbConditionInt('o.operationid', $op_recovery_message)
					);
					while ($db_opmessage = DBfetch($db_opmessages)) {
						$operationid = $db_opmessage['operationid'];
						unset($db_opmessage['operationid']);
						$recovery_operations[$operationid]['opmessage'] = $db_opmessage;
					}
				}
			}

			$recovery_operations = $this->unsetExtraFields($recovery_operations,
				['operationid', 'actionid', 'operationtype'], $options['selectRecoveryOperations']
			);
			$result = $relationMap->mapMany($result, $recovery_operations, 'recovery_operations');
		}

		// Adding update operations.
		if ($options['selectUpdateOperations'] !== null && $options['selectUpdateOperations'] != API_OUTPUT_COUNT) {
			$update_operations = API::getApiService()->select('operations', [
				'output' => $this->outputExtend($options['selectUpdateOperations'],
					['operationid', 'actionid', 'operationtype']
				),
				'filter' => ['actionid' => $actionIds, 'recovery' => ACTION_UPDATE_OPERATION],
				'preservekeys' => true
			]);

			foreach ($result as &$action) {
				$action['update_operations'] = [];
			}
			unset($action);

			$update_operations = $this->getUpdateOperations($update_operations, $options['selectUpdateOperations']);

			foreach ($update_operations as $update_operation) {
				$actionid = $update_operation['actionid'];
				unset($update_operation['actionid'], $update_operation['recovery']);
				$result[$actionid]['update_operations'][] = $update_operation;
			}
		}

		return $result;
	}

	/**
	 * Returns an array of update operations according to requested options.
	 *
	 * @param array        $update_operations                 An array of update operations.
	 * @param string       $update_operations[<operationid>]  Operation ID.
	 * @param array|string $update_options                    An array of output options from request, or "extend".
	 *
	 * @return array
	 */
	protected function getUpdateOperations(array $update_operations, $update_options): array {
		$opmessages = [];
		$nonack_messages = [];
		$opcommands = [];

		foreach ($update_operations as $operationid => &$update_operation) {
			unset($update_operation['esc_period'], $update_operation['esc_step_from'],
				$update_operation['esc_step_to']
			);

			switch ($update_operation['operationtype']) {
				case OPERATION_TYPE_UPDATE_MESSAGE:
					$opmessages[] = $operationid;
					break;
				case OPERATION_TYPE_MESSAGE:
					$opmessages[] = $operationid;
					$nonack_messages[] = $operationid;
					break;
				case OPERATION_TYPE_COMMAND:
					$opcommands[] = $operationid;
					break;
			}
		}
		unset($update_operation);

		if ($opmessages) {
			if ($this->outputIsRequested('opmessage', $update_options)) {
				foreach ($opmessages as $operationid) {
					$update_operations[$operationid]['opmessage'] = [];
				}

				$db_opmessages = DBselect(
					'SELECT o.operationid,o.default_msg,o.subject,o.message,o.mediatypeid'.
					' FROM opmessage o'.
					' WHERE '.dbConditionInt('o.operationid', $opmessages)
				);
				while ($db_opmessage = DBfetch($db_opmessages)) {
					$operationid = $db_opmessage['operationid'];
					unset($db_opmessage['operationid']);
					$update_operations[$operationid]['opmessage'] = $db_opmessage;
				}
			}

			if ($nonack_messages && $this->outputIsRequested('opmessage_grp', $update_options)) {
				foreach ($nonack_messages as $operationid) {
					$update_operations[$operationid]['opmessage_grp'] = [];
				}

				$db_opmessage_grp = DBselect(
					'SELECT og.operationid,og.usrgrpid'.
					' FROM opmessage_grp og'.
					' WHERE '.dbConditionInt('og.operationid', $nonack_messages)
				);
				while ($opmessage_grp = DBfetch($db_opmessage_grp)) {
					$operationid = $opmessage_grp['operationid'];
					unset($opmessage_grp['operationid']);
					$update_operations[$operationid]['opmessage_grp'][] = $opmessage_grp;
				}
			}

			if ($nonack_messages && $this->outputIsRequested('opmessage_usr', $update_options)) {
				foreach ($nonack_messages as $operationid) {
					$update_operations[$operationid]['opmessage_usr'] = [];
				}

				$db_opmessage_usr = DBselect(
					'SELECT ou.operationid,ou.userid'.
					' FROM opmessage_usr ou'.
					' WHERE '.dbConditionInt('ou.operationid', $nonack_messages)
				);
				while ($opmessage_usr = DBfetch($db_opmessage_usr)) {
					$operationid = $opmessage_usr['operationid'];
					unset($opmessage_usr['operationid']);
					$update_operations[$operationid]['opmessage_usr'][] = $opmessage_usr;
				}
			}
		}

		if ($opcommands) {
			if ($this->outputIsRequested('opcommand', $update_options)) {
				foreach ($opcommands as $operationid) {
					$update_operations[$operationid]['opcommand'] = [];
				}

				$db_opcommands = DBselect(
					'SELECT o.operationid,o.scriptid'.
					' FROM opcommand o'.
					' WHERE '.dbConditionInt('o.operationid', $opcommands)
				);
				while ($db_opcommand = DBfetch($db_opcommands)) {
					$operationid = $db_opcommand['operationid'];
					unset($db_opcommand['operationid']);
					$update_operations[$operationid]['opcommand'] = $db_opcommand;
				}
			}

			if ($this->outputIsRequested('opcommand_hst', $update_options)) {
				foreach ($opcommands as $operationid) {
					$update_operations[$operationid]['opcommand_hst'] = [];
				}

				$db_opcommand_hst = DBselect(
					'SELECT oh.opcommand_hstid,oh.operationid,oh.hostid'.
					' FROM opcommand_hst oh'.
					' WHERE '.dbConditionInt('oh.operationid', $opcommands)
				);
				while ($opcommand_hst = DBfetch($db_opcommand_hst)) {
					$operationid = $opcommand_hst['operationid'];
					unset($opcommand_hst['operationid']);
					$update_operations[$operationid]['opcommand_hst'][] = $opcommand_hst;
				}
			}

			if ($this->outputIsRequested('opcommand_grp', $update_options)) {
				foreach ($opcommands as $operationid) {
					$update_operations[$operationid]['opcommand_grp'] = [];
				}

				$db_opcommand_grp = DBselect(
					'SELECT og.opcommand_grpid,og.operationid,og.groupid'.
					' FROM opcommand_grp og'.
					' WHERE '.dbConditionInt('og.operationid', $opcommands)
				);
				while ($opcommand_grp = DBfetch($db_opcommand_grp)) {
					$operationid = $opcommand_grp['operationid'];
					unset($opcommand_grp['operationid']);
					$update_operations[$operationid]['opcommand_grp'][] = $opcommand_grp;
				}
			}
		}

		return $this->unsetExtraFields($update_operations, ['operationid', 'operationtype'], $update_options);
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if (!$options['countOutput']) {
			// add filter fields
			if ($this->outputIsRequested('formula', $options['selectFilter'])
				|| $this->outputIsRequested('eval_formula', $options['selectFilter'])
				|| $this->outputIsRequested('conditions', $options['selectFilter'])) {

				$sqlParts = $this->addQuerySelect('a.formula', $sqlParts);
				$sqlParts = $this->addQuerySelect('a.evaltype', $sqlParts);
			}
			if ($this->outputIsRequested('evaltype', $options['selectFilter'])) {
				$sqlParts = $this->addQuerySelect('a.evaltype', $sqlParts);
			}
		}

		return $sqlParts;
	}

	/**
	 * Returns validation rules for the filter object.
	 *
	 * @param int $eventsource  Action event source. Possible values:
	 *                          EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
	 *                          EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
	 *
	 * @return array
	 */
	private static function getFilterValidationRules(int $eventsource): array {
		switch ($eventsource) {
			case EVENT_SOURCE_TRIGGERS:
				$value_rules = [
					['if' => ['field' => 'conditiontype', 'in' => implode(',', [CONDITION_TYPE_HOST_GROUP, CONDITION_TYPE_HOST, CONDITION_TYPE_TRIGGER, CONDITION_TYPE_TEMPLATE])], 'type' => API_ID, 'flags' => API_REQUIRED],
					['if' => ['field' => 'conditiontype', 'in' => implode(',', [CONDITION_TYPE_TRIGGER_NAME, CONDITION_TYPE_EVENT_TAG])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('conditions', 'value')],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_TRIGGER_SEVERITY], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_TIME_PERIOD], 'type' => API_TIME_PERIOD, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('conditions', 'value')],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_EVENT_TAG_VALUE], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('conditions', 'value')],
					['else' => true, 'type' => API_UNEXPECTED]
				];
				$operator_rules = [
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_HOST_GROUP], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_HOST_GROUP))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_HOST], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_HOST))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_TRIGGER], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_TRIGGER))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_TEMPLATE], 'type' => API_INT32,'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_TEMPLATE))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_TRIGGER_NAME], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_TRIGGER_NAME))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_TRIGGER_SEVERITY], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_TRIGGER_SEVERITY))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_TIME_PERIOD], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_TIME_PERIOD))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_SUPPRESSED], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_SUPPRESSED))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_EVENT_TAG], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_EVENT_TAG))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_EVENT_TAG_VALUE], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_EVENT_TAG_VALUE))]
				];
				break;

			case EVENT_SOURCE_DISCOVERY:
				$value_rules = [
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DHOST_IP], 'type' => API_IP_RANGES, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_RANGE, 'length' => DB::getFieldLength('conditions', 'value')],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DSERVICE_TYPE], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [SVC_SSH, SVC_LDAP, SVC_SMTP, SVC_FTP, SVC_HTTP, SVC_POP, SVC_NNTP, SVC_IMAP, SVC_TCP, SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2c, SVC_ICMPPING, SVC_SNMPv3, SVC_HTTPS, SVC_TELNET])],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DSERVICE_PORT], 'type' => API_INT32_RANGES, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('conditions', 'value'), 'in' => ZBX_MIN_PORT_NUMBER.':'.ZBX_MAX_PORT_NUMBER],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DSTATUS], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [DOBJECT_STATUS_UP, DOBJECT_STATUS_DOWN, DOBJECT_STATUS_DISCOVER, DOBJECT_STATUS_LOST])],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DUPTIME], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.SEC_PER_MONTH],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DVALUE], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('conditions', 'value')],
					['if' => ['field' => 'conditiontype', 'in' => implode(',', [CONDITION_TYPE_DRULE, CONDITION_TYPE_DCHECK, CONDITION_TYPE_PROXY])], 'type' => API_ID, 'flags' => API_REQUIRED],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DOBJECT], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [EVENT_OBJECT_DHOST, EVENT_OBJECT_DSERVICE])]
				];
				$operator_rules = [
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DHOST_IP], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_DHOST_IP))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DSERVICE_TYPE], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_DSERVICE_TYPE))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DSERVICE_PORT], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_DSERVICE_PORT))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DSTATUS], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_DSTATUS))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DUPTIME], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_DUPTIME))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DVALUE], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_DVALUE))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DRULE], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_DRULE))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DCHECK], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_DCHECK))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_PROXY], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_PROXY))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_DOBJECT], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_DOBJECT))]
				];
				break;

			case EVENT_SOURCE_AUTOREGISTRATION:
				$value_rules = [
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_PROXY], 'type' => API_ID, 'flags' => API_REQUIRED],
					['if' => ['field' => 'conditiontype', 'in' => implode(',', [CONDITION_TYPE_HOST_NAME, CONDITION_TYPE_HOST_METADATA])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('conditions', 'value')]
				];
				$operator_rules = [
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_PROXY], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_PROXY))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_HOST_NAME], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_HOST_NAME))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_HOST_METADATA], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_HOST_METADATA))]
				];
				break;

			case EVENT_SOURCE_INTERNAL:
				$value_rules = [
					['if' => ['field' => 'conditiontype', 'in' => implode(',', [CONDITION_TYPE_HOST_GROUP, CONDITION_TYPE_HOST, CONDITION_TYPE_TEMPLATE])], 'type' => API_ID, 'flags' => API_REQUIRED],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_EVENT_TYPE], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [EVENT_TYPE_ITEM_NOTSUPPORTED, EVENT_TYPE_LLDRULE_NOTSUPPORTED, EVENT_TYPE_TRIGGER_UNKNOWN])],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_EVENT_TAG], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('conditions', 'value')],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_EVENT_TAG_VALUE], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('conditions', 'value')]
				];
				$operator_rules = [
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_HOST_GROUP], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_HOST_GROUP))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_HOST], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_HOST))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_TEMPLATE], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_TEMPLATE))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_EVENT_TYPE], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_EVENT_TYPE))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_EVENT_TAG], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_EVENT_TAG))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_EVENT_TAG_VALUE], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_EVENT_TAG_VALUE))]
				];
				break;

			case EVENT_SOURCE_SERVICE:
				$value_rules = [
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_SERVICE], 'type' => API_ID, 'flags' => API_REQUIRED],
					['if' => ['field' => 'conditiontype', 'in' => implode(',', [CONDITION_TYPE_SERVICE_NAME, CONDITION_TYPE_EVENT_TAG])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('conditions', 'value')],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_EVENT_TAG_VALUE], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('conditions', 'value')]
				];
				$operator_rules = [
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_SERVICE], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_SERVICE))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_SERVICE_NAME], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_SERVICE_NAME))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_EVENT_TAG], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_EVENT_TAG))],
					['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_EVENT_TAG_VALUE], 'type' => API_INT32, 'in' => implode(',', get_operators_by_conditiontype(CONDITION_TYPE_EVENT_TAG_VALUE))]
				];
				break;
		}

		$condition_fields = [
			'conditiontype' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', self::VALID_CONDITION_TYPES[$eventsource])],
			'operator' =>		['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => $operator_rules],
			'value' =>			['type' => API_MULTIPLE, 'rules' => $value_rules],
			'value2' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'conditiontype', 'in' => CONDITION_TYPE_EVENT_TAG_VALUE], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('conditions', 'value2')],
									['else' => true, 'type' => API_UNEXPECTED]
			]]
		];

		return [
			'evaltype' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION])],
			'formula' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'evaltype', 'in' => CONDITION_EVAL_TYPE_EXPRESSION], 'type' => API_COND_FORMULA, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('actions', 'formula')],
								['else' => true, 'type' => API_UNEXPECTED]
			]],
			'conditions' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'evaltype', 'in' => CONDITION_EVAL_TYPE_EXPRESSION], 'type' => API_OBJECTS, 'flags' => API_REQUIRED, 'uniq' => [['formulaid']], 'fields' => [
									'formulaid' =>	['type' => API_COND_FORMULAID, 'flags' => API_REQUIRED]
								] + $condition_fields],
								['else' => true, 'type' => API_OBJECTS, 'fields' => $condition_fields]
			]]
		];
	}

	/**
	 * Returns validation rules for objects of normal, recovery and update operations.
	 *
	 * @param int $recovery     Action operation mode. Possible values:
	 *                          ACTION_OPERATION, ACTION_RECOVERY_OPERATION, ACTION_UPDATE_OPERATION
	 * @param int $eventsource  Action event source. Possible values:
	 *                          EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
	 *                          EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
	 *
	 * @return array
	 */
	private static function getOperationValidationRules(int $recovery, int $eventsource): array {
		$escalation_fields = [
			'esc_period' =>		['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '0,'.SEC_PER_MIN.':'.SEC_PER_WEEK, 'length' => DB::getFieldLength('operations', 'esc_period')],
			'esc_step_from' =>	['type' => API_INT32, 'in' => '1:99999'],
			'esc_step_to' =>	['type' => API_INT32, 'in' => '0:99999']
		];
		$opmessage_fields = [
			'default_msg' =>	['type' => API_INT32, 'in' => implode(',', [0, 1]), 'default' => DB::getDefault('opmessage', 'default_msg')],
			'subject' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'default_msg', 'in' => 0], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('opmessage', 'subject')],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'message' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'default_msg', 'in' => 0], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('opmessage', 'message')],
									['else' => true, 'type' => API_UNEXPECTED]
			]]
		];
		$all_opmessage_fields = [
			'opmessage' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'operationtype', 'in' => implode(',', [OPERATION_TYPE_MESSAGE, OPERATION_TYPE_UPDATE_MESSAGE])], 'type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => $opmessage_fields + [
										'mediatypeid' =>	['type' => API_ID]
									]],
									['if' => ['field' => 'operationtype', 'in' => OPERATION_TYPE_RECOVERY_MESSAGE], 'type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => $opmessage_fields],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'opmessage_grp' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'operationtype', 'in' => OPERATION_TYPE_MESSAGE], 'type' => API_OBJECTS, 'uniq' => [['usrgrpid']], 'fields' => [
										'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
									]],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'opmessage_usr' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'operationtype', 'in' => OPERATION_TYPE_MESSAGE], 'type' => API_OBJECTS, 'uniq' => [['userid']], 'fields' => [
										'userid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
									]],
									['else' => true, 'type' => API_UNEXPECTED]
			]]
		];
		$opcommand_fields = [
			'opcommand' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'operationtype', 'in' => OPERATION_TYPE_COMMAND], 'type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
										'scriptid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
									]],
									['else' => true, 'type' => API_UNEXPECTED]
			]]
		];
		$common_fields = $all_opmessage_fields + $opcommand_fields + [
			'opcommand_grp' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'operationtype', 'in' => OPERATION_TYPE_COMMAND], 'type' => API_OBJECTS, 'uniq' => [['groupid']], 'fields' => [
										'groupid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
									]],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'opcommand_hst' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'operationtype', 'in' => OPERATION_TYPE_COMMAND], 'type' => API_OBJECTS, 'uniq' => [['hostid']], 'fields' => [
										'hostid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
									]],
									['else' => true, 'type' => API_UNEXPECTED]
			]]
		];

		$operationtype_field = [
			'operationtype' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', getAllowedOperations($eventsource)[$recovery])]
		];

		switch ($recovery) {
			case ACTION_OPERATION:
				switch ($eventsource) {
					case EVENT_SOURCE_TRIGGERS:
						return $operationtype_field + $escalation_fields + [
							'evaltype' =>		['type' => API_INT32, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR])],
							'opconditions' =>	['type' => API_OBJECTS, 'uniq' => [['value']], 'fields' => [
								'conditiontype' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => CONDITION_TYPE_EVENT_ACKNOWLEDGED],
								'value' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => implode(',', [EVENT_NOT_ACKNOWLEDGED, EVENT_ACKNOWLEDGED]), 'length' => DB::getFieldLength('opconditions', 'value')],
								'operator' =>		['type' => API_INT32, 'in' => CONDITION_OPERATOR_EQUAL]
							]]
						] + $common_fields;

					case EVENT_SOURCE_DISCOVERY:
					case EVENT_SOURCE_AUTOREGISTRATION:
						return $operationtype_field + $common_fields + [
							'opgroup' =>		['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'operationtype', 'in' => implode(',', [OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE])], 'type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['groupid']], 'fields' => [
														'groupid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
													]],
													['else' => true, 'type' => API_UNEXPECTED]
							]],
							'optemplate' =>		['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'operationtype', 'in' => implode(',', [OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_TEMPLATE_REMOVE])], 'type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['templateid']], 'fields' => [
														'templateid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
													]],
													['else' => true, 'type' => API_UNEXPECTED]
							]],
							'opinventory' =>	['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'operationtype', 'in' => OPERATION_TYPE_HOST_INVENTORY], 'type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
														'inventory_mode' =>	['type' => API_INT32, 'in' => implode(',', [HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
													]],
													['else' => true, 'type' => API_UNEXPECTED]
							]]
						];

					case EVENT_SOURCE_INTERNAL:
						return $operationtype_field + $escalation_fields + $all_opmessage_fields;

					case EVENT_SOURCE_SERVICE:
						return $operationtype_field + $escalation_fields + $all_opmessage_fields + $opcommand_fields;
				}
				break;

			case ACTION_RECOVERY_OPERATION:
				switch ($eventsource) {
					case EVENT_SOURCE_TRIGGERS:
						return $operationtype_field + $common_fields;

					case EVENT_SOURCE_INTERNAL:
						return $operationtype_field + $all_opmessage_fields;

					case EVENT_SOURCE_SERVICE:
						return $operationtype_field + $all_opmessage_fields + $opcommand_fields;
				}
				break;

			case ACTION_UPDATE_OPERATION:
				switch ($eventsource) {
					case EVENT_SOURCE_TRIGGERS:
						return $operationtype_field + $common_fields;

					case EVENT_SOURCE_SERVICE:
						return $operationtype_field + $all_opmessage_fields + $opcommand_fields;
				}
				break;
		}
	}

	/**
	 * @param array $actions
	 *
	 * @throws APIException
	 */
	private function validateCreate(array &$actions): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('actions', 'name')],
			'eventsource' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [ACTION_STATUS_ENABLED, ACTION_STATUS_DISABLED])],
			'esc_period' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'eventsource', 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])], 'type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => SEC_PER_MIN.':'.SEC_PER_WEEK, 'length' => DB::getFieldLength('actions', 'esc_period')],
											['else' => true, 'type' => API_UNEXPECTED]
			]],
			'pause_suppressed' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_TRIGGERS], 'type' => API_INT32, 'in' => implode(',', [ACTION_PAUSE_SUPPRESSED_FALSE, ACTION_PAUSE_SUPPRESSED_TRUE])],
											['else' => true, 'type' => API_UNEXPECTED]
			]],
			'filter' =>					['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_TRIGGERS], 'type' => API_OBJECT, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_TRIGGERS)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_DISCOVERY], 'type' => API_OBJECT, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_DISCOVERY)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_AUTOREGISTRATION], 'type' => API_OBJECT, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_AUTOREGISTRATION)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_INTERNAL], 'type' => API_OBJECT, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_INTERNAL)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_SERVICE], 'type' => API_OBJECT, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_SERVICE)]
			]],
			'operations' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_TRIGGERS], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_TRIGGERS)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_DISCOVERY], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_DISCOVERY)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_AUTOREGISTRATION], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_AUTOREGISTRATION)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_INTERNAL], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_INTERNAL)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_SERVICE], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_SERVICE)]
			]],
			'recovery_operations' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_TRIGGERS], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_RECOVERY_OPERATION, EVENT_SOURCE_TRIGGERS)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_INTERNAL], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_RECOVERY_OPERATION, EVENT_SOURCE_INTERNAL)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_SERVICE], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_RECOVERY_OPERATION, EVENT_SOURCE_SERVICE)],
											['else' => true, 'type' => API_UNEXPECTED]
			]],
			'update_operations' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_TRIGGERS], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_UPDATE_OPERATION, EVENT_SOURCE_TRIGGERS)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_SERVICE], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_UPDATE_OPERATION, EVENT_SOURCE_SERVICE)],
											['else' => true, 'type' => API_UNEXPECTED]
			]],
			'notify_if_canceled' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_TRIGGERS], 'type' => API_INT32, 'in' => implode(',', [ACTION_NOTIFY_IF_CANCELED_FALSE, ACTION_NOTIFY_IF_CANCELED_TRUE])],
											['else' => true, 'type' => API_UNEXPECTED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $actions, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($actions);
		self::checkFilter($actions);
		self::checkOperations($actions);

		self::checkMediatypesPermissions($actions);
		self::checkScriptsPermissions($actions);
		self::checkHostGroupsPermissions($actions);
		self::checkHostsPermissions($actions);
		self::checkTemplatesPermissions($actions);
		self::checkUsersPermissions($actions);
		self::checkUserGroupsPermissions($actions);
		self::checkTriggersPermissions($actions);
		self::checkDRulesPermissions($actions);
		self::checkDChecksPermissions($actions);
		self::checkProxiesPermissions($actions);
		self::checkServicesPermissions($actions);
	}

	/**
	 * @param array      $actions
	 * @param array|null $db_actions
	 *
	 * @throws APIException
	 */
	private function validateUpdate(array &$actions, ?array &$db_actions): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['actionid']], 'fields' => [
			'actionid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $actions, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_actions = $this->get([
			'output' => ['actionid', 'name', 'eventsource', 'status', 'esc_period', 'pause_suppressed',
				'notify_if_canceled'
			],
			'actionids' => array_column($actions, 'actionid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($actions) != count($db_actions)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		// If not specified, copy original "name" and "eventsource" values for further validation and error reporting.
		$actions = $this->extendObjectsByKey($actions, $db_actions, 'actionid', ['name', 'eventsource']);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
			'actionid' =>				['type' => API_ID],
			'name' =>					['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('actions', 'name')],
			'eventsource' =>			['type' => API_INT32, 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [ACTION_STATUS_ENABLED, ACTION_STATUS_DISABLED])],
			'esc_period' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'eventsource', 'in' => implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])], 'type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => SEC_PER_MIN.':'.SEC_PER_WEEK, 'length' => DB::getFieldLength('actions', 'esc_period')],
											['else' => true, 'type' => API_UNEXPECTED]
			]],
			'pause_suppressed' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_TRIGGERS], 'type' => API_INT32, 'in' => implode(',', [ACTION_PAUSE_SUPPRESSED_FALSE, ACTION_PAUSE_SUPPRESSED_TRUE])],
											['else' => true, 'type' => API_UNEXPECTED]
			]],
			'filter' =>					['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_TRIGGERS], 'type' => API_OBJECT, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_TRIGGERS)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_DISCOVERY], 'type' => API_OBJECT, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_DISCOVERY)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_AUTOREGISTRATION], 'type' => API_OBJECT, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_AUTOREGISTRATION)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_INTERNAL], 'type' => API_OBJECT, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_INTERNAL)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_SERVICE], 'type' => API_OBJECT, 'fields' => self::getFilterValidationRules(EVENT_SOURCE_SERVICE)]
			]],
			'operations' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_TRIGGERS], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_TRIGGERS)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_DISCOVERY], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_DISCOVERY)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_AUTOREGISTRATION], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_AUTOREGISTRATION)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_INTERNAL], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_INTERNAL)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_SERVICE], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_OPERATION, EVENT_SOURCE_SERVICE)]
			]],
			'recovery_operations' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_TRIGGERS], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_RECOVERY_OPERATION, EVENT_SOURCE_TRIGGERS)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_INTERNAL], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_RECOVERY_OPERATION, EVENT_SOURCE_INTERNAL)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_SERVICE], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_RECOVERY_OPERATION, EVENT_SOURCE_SERVICE)],
											['else' => true, 'type' => API_UNEXPECTED]
			]],
			'update_operations' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_TRIGGERS], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_UPDATE_OPERATION, EVENT_SOURCE_TRIGGERS)],
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_SERVICE], 'type' => API_OBJECTS, 'fields' => self::getOperationValidationRules(ACTION_UPDATE_OPERATION, EVENT_SOURCE_SERVICE)],
											['else' => true, 'type' => API_UNEXPECTED]
			]],
			'notify_if_canceled' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'eventsource', 'in' => EVENT_SOURCE_TRIGGERS], 'type' => API_INT32, 'in' => implode(',', [ACTION_NOTIFY_IF_CANCELED_FALSE, ACTION_NOTIFY_IF_CANCELED_TRUE])],
											['else' => true, 'type' => API_UNEXPECTED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $actions, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($actions, $db_actions);

		foreach ($actions as $action) {
			if ($action['eventsource'] != $db_actions[$action['actionid']]['eventsource']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot update "%1$s" for action "%2$s".', 'eventsource', $action['name'])
				);
			}
		}

		self::addAffectedObjects($actions, $db_actions);

		self::checkFilter($actions);
		self::checkOperations($actions, $db_actions);

		self::checkMediatypesPermissions($actions);
		self::checkScriptsPermissions($actions);
		self::checkHostGroupsPermissions($actions);
		self::checkHostsPermissions($actions);
		self::checkTemplatesPermissions($actions);
		self::checkUsersPermissions($actions);
		self::checkUserGroupsPermissions($actions);
		self::checkTriggersPermissions($actions);
		self::checkDRulesPermissions($actions);
		self::checkDChecksPermissions($actions);
		self::checkProxiesPermissions($actions);
		self::checkServicesPermissions($actions);
	}

	/**
	 * Check for unique action names.
	 *
	 * @param array      $actions
	 * @param array|null $db_actions
	 *
	 * @throws APIException if action name is not unique.
	 */
	private static function checkDuplicates(array $actions, array $db_actions = null): void {
		$names = [];

		foreach ($actions as $action) {
			if ($db_actions === null || $action['name'] !== $db_actions[$action['actionid']]['name']) {
				$names[] = $action['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicates = DB::select('actions', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%1$s" already exists.', $duplicates[0]['name']));
		}
	}

	/**
	 * @param array $actions
	 *
	 * @throws APIException
	 */
	private static function checkFilter(array $actions): void {
		$condition_formula_parser = new CConditionFormula();
		$ip_range_parser = new CIPRangeParser(['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30]);

		foreach ($actions as $i => $action) {
			if (!array_key_exists('filter', $action)) {
				continue;
			}

			$path = '/'.($i + 1).'/filter';

			if ($action['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$condition_formula_parser->parse($action['filter']['formula']);

				$constants = array_column($condition_formula_parser->constants, 'value', 'value');

				if (count($action['filter']['conditions']) != count($constants)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', $path.'/conditions', _('incorrect number of conditions'))
					);
				}

				foreach ($action['filter']['conditions'] as $j => $condition) {
					if (!array_key_exists($condition['formulaid'], $constants)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							$path.'/conditions/'.($j + 1).'/formulaid', _('an identifier is not defined in the formula')
						));
					}
				}
			}

			if (array_key_exists('conditions', $action['filter'])) {
				foreach ($action['filter']['conditions'] as $j => $condition) {
					if ($condition['conditiontype'] == CONDITION_TYPE_DHOST_IP) {
						if (!$ip_range_parser->parse($condition['value'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								$path.'/conditions/'.($j + 1).'/value', $ip_range_parser->getError()
							));
						}
					}
					elseif ($condition['conditiontype'] == CONDITION_TYPE_DVALUE) {
						if ($condition['operator'] == CONDITION_OPERATOR_EQUAL
								|| $condition['operator'] == CONDITION_OPERATOR_NOT_EQUAL) {
							continue;
						}

						if ($condition['value'] === '') {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								$path.'/conditions/'.($j + 1).'/value', _('cannot be empty')
							));
						}
					}
				}
			}
		}
	}

	/**
	 * @param array      $actions
	 * @param array|null $db_actions
	 *
	 * @throws APIException
	 */
	private static function checkOperations(array &$actions, array $db_actions = null): void {
		$is_update = ($db_actions !== null);

		foreach ($actions as &$action) {
			if ($is_update) {
				if (!array_intersect_key(array_flip(self::OPERATION_GROUPS), $action)) {
					continue;
				}

				$db_action = $db_actions[$action['actionid']];
			}
			else {
				$db_action = [];
			}

			$operations = array_intersect_key($action + $db_action, array_flip(self::OPERATION_GROUPS));

			if (!array_filter($operations, 'boolval')) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('No operations defined for action "%1$s".', $action['name'])
				);
			}

			$unique_operations = [
				OPERATION_TYPE_HOST_ADD => 0,
				OPERATION_TYPE_HOST_REMOVE => 0,
				OPERATION_TYPE_HOST_ENABLE => 0,
				OPERATION_TYPE_HOST_DISABLE => 0,
				OPERATION_TYPE_HOST_INVENTORY => 0
			];

			foreach (self::OPERATION_GROUPS as $recovery => $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				foreach ($action[$operation_group] as &$operation) {
					$operation['recovery'] = $recovery;

					if ($recovery == ACTION_OPERATION) {
						if (array_key_exists($operation['operationtype'], $unique_operations)) {
							$unique_operations[$operation['operationtype']]++;

							if ($unique_operations[$operation['operationtype']] > 1) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('Operation "%1$s" already exists for action "%2$s".',
										operation_type2str($operation['operationtype']), $action['name']
									)
								);
							}
						}

						if (array_key_exists('esc_step_from', $operation)
								|| array_key_exists('esc_step_to', $operation)) {
							if (!array_key_exists('esc_step_from', $operation)
									|| !array_key_exists('esc_step_to', $operation)) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_('Parameters "esc_step_from" and "esc_step_to" must be set together.')
								);
							}

							if ($operation['esc_step_from'] > $operation['esc_step_to']
									&& $operation['esc_step_to'] != 0) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_('Incorrect action operation escalation step values.')
								);
							}
						}
					}

					if ($operation['operationtype'] == OPERATION_TYPE_MESSAGE) {
						$has_groups = array_key_exists('opmessage_grp', $operation) && $operation['opmessage_grp'];
						$has_users = array_key_exists('opmessage_usr', $operation) && $operation['opmessage_usr'];

						if (!$has_groups && !$has_users) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_('No recipients specified for action operation message.')
							);
						}
					}
					elseif ($operation['operationtype'] == OPERATION_TYPE_COMMAND
							&& $action['eventsource'] != EVENT_SOURCE_SERVICE) {
						$has_groups = array_key_exists('opcommand_grp', $operation) && $operation['opcommand_grp'];
						$has_hosts = array_key_exists('opcommand_hst', $operation) && $operation['opcommand_hst'];

						if (!$has_groups && !$has_hosts) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_('No targets specified for action operation global script.')
							);
						}
					}
				}
				unset($operation);
			}
		}
		unset($action);
	}

	/**
	 * Checks if all the given media types are valid.
	 *
	 * @param array $actions
	 *
	 * @throws APIException if invalid media types given.
	 */
	private static function checkMediatypesPermissions(array $actions): void {
		$mediatypeids = [];

		foreach ($actions as $action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				foreach ($action[$operation_group] as $operation) {
					if ($operation['operationtype'] == OPERATION_TYPE_MESSAGE
							|| $operation['operationtype'] == OPERATION_TYPE_UPDATE_MESSAGE) {
						if (array_key_exists('mediatypeid', $operation)
								&& $operation['opmessage']['mediatypeid'] != 0) {
							$mediatypeids[$operation['opmessage']['mediatypeid']] = true;
						}
					}
				}
			}
		}

		if (!$mediatypeids) {
			return;
		}

		$mediatypeids = array_keys($mediatypeids);

		$count = API::MediaType()->get([
			'countOutput' => true,
			'mediatypeids' => $mediatypeids
		]);

		if ($count != count($mediatypeids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect action operation media type. Media type does not exist or you have no access to it.')
			);
		}
	}

	/**
	 * Checks if all the given global scripts are valid.
	 *
	 * @param array $actions
	 *
	 * @throws APIException if invalid global scripts given.
	 */
	private static function checkScriptsPermissions(array $actions): void {
		$scriptids = [];

		foreach ($actions as $action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				foreach ($action[$operation_group] as $operation) {
					if ($operation['operationtype'] == OPERATION_TYPE_COMMAND) {
						$scriptids[$operation['opcommand']['scriptid']] = true;
					}
				}
			}
		}

		if (!$scriptids) {
			return;
		}

		$scriptids = array_keys($scriptids);

		$count = API::Script()->get([
			'countOutput' => true,
			'scriptids' => $scriptids,
			'filter' => ['scope' => ZBX_SCRIPT_SCOPE_ACTION]
		]);

		if ($count != count($scriptids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_('Specified script does not exist or you do not have rights on it for action operation command.')
			);
		}
	}

	/**
	 * Checks if the current user has access to the given host groups.
	 *
	 * @param array $actions
	 *
	 * @throws APIException if the user doesn't have write permissions for the given host groups.
	 */
	private static function checkHostGroupsPermissions(array $actions): void {
		$groupids = [];

		foreach ($actions as $action) {
			if (array_key_exists('filter', $action) && array_key_exists('conditions', $action['filter'])) {
				foreach ($action['filter']['conditions'] as $condition) {
					if ($condition['conditiontype'] == CONDITION_TYPE_HOST_GROUP) {
						$groupids[] = $condition['value'];
					}
				}
			}

			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				foreach ($action[$operation_group] as $operation) {
					if ($operation['operationtype'] == OPERATION_TYPE_COMMAND
							// Service actions do not support "opcommand_grp".
							&& array_key_exists('opcommand_grp', $operation)) {
						$groupids = array_merge($groupids, array_column($operation['opcommand_grp'], 'groupid'));
					}
					elseif ($operation['operationtype'] == OPERATION_TYPE_GROUP_ADD
							|| $operation['operationtype'] == OPERATION_TYPE_GROUP_REMOVE) {
						$groupids = array_merge($groupids, array_column($operation['opgroup'], 'groupid'));
					}
				}
			}
		}

		if (!$groupids) {
			return;
		}

		$groupids = array_keys(array_flip($groupids));

		$count = API::HostGroup()->get([
			'countOutput' => true,
			'groupids' => $groupids,
			'editable' => true
		]);

		if ($count != count($groupids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect action condition or operation host group. Host group does not exist or you have no access to it.')
			);
		}
	}

	/**
	 * Checks if the current user has access to the given hosts.
	 *
	 * @param array $actions
	 *
	 * @throws APIException if the user doesn't have write permissions for the given hosts.
	 */
	private static function checkHostsPermissions(array $actions): void {
		$hostids = [];

		foreach ($actions as $action) {
			if (array_key_exists('filter', $action) && array_key_exists('conditions', $action['filter'])) {
				foreach ($action['filter']['conditions'] as $condition) {
					if ($condition['conditiontype'] == CONDITION_TYPE_HOST) {
						$hostids[] = $condition['value'];
					}
				}
			}

			if ($action['eventsource'] == EVENT_SOURCE_SERVICE) {
				continue;
			}

			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				foreach ($action[$operation_group] as $operation) {
					if ($operation['operationtype'] == OPERATION_TYPE_COMMAND
							&& array_key_exists('opcommand_hst', $operation)) {
						foreach ($operation['opcommand_hst'] as $opcommand_hst) {
							if ($opcommand_hst['hostid'] != 0) {
								$hostids[] = $opcommand_hst['hostid'];
							}
						}
					}
				}
			}
		}

		if (!$hostids) {
			return;
		}

		$hostids = array_keys(array_flip($hostids));

		$count = API::Host()->get([
			'countOutput' => true,
			'hostids' => $hostids,
			'editable' => true
		]);

		if ($count != count($hostids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect action condition or operation host. Host does not exist or you have no access to it.')
			);
		}
	}

	/**
	 * Checks if the current user has access to the given users.
	 *
	 * @param array $actions
	 *
	 * @throws APIException if the user doesn't have write permissions for the given users.
	 */
	private static function checkUsersPermissions(array $actions): void {
		$userids = [];

		foreach ($actions as $action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				foreach ($action[$operation_group] as $operation) {
					if ($operation['operationtype'] == OPERATION_TYPE_MESSAGE
							&& array_key_exists('opmessage_usr', $operation)) {
						$userids = array_merge($userids, array_column($operation['opmessage_usr'], 'userid'));
					}
				}
			}
		}

		if (!$userids) {
			return;
		}

		$userids = array_keys(array_flip($userids));

		$count = API::User()->get([
			'countOutput' => true,
			'userids' => $userids
		]);

		if ($count != count($userids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect action operation user. User does not exist or you have no access to it.')
			);
		}
	}

	/**
	 * Checks if the current user has access to the given user groups.
	 *
	 * @param array $actions
	 *
	 * @throws APIException if the user doesn't have write permissions for the given user groups.
	 */
	private static function checkUserGroupsPermissions(array $actions): void {
		$usrgrpids = [];

		foreach ($actions as $action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				foreach ($action[$operation_group] as $operation) {
					if ($operation['operationtype'] == OPERATION_TYPE_MESSAGE
							&& array_key_exists('opmessage_grp', $operation)) {
						$usrgrpids = array_merge($usrgrpids, array_column($operation['opmessage_grp'], 'usrgrpid'));
					}
				}
			}
		}

		if (!$usrgrpids) {
			return;
		}

		$usrgrpids = array_keys(array_flip($usrgrpids));

		$count = API::UserGroup()->get([
			'countOutput' => true,
			'usrgrpids' => $usrgrpids
		]);

		if ($count != count($usrgrpids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect action operation user group. User group does not exist or you have no access to it.')
			);
		}
	}

	/**
	 * Checks if the current user has access to the given templates.
	 *
	 * @param array $actions
	 *
	 * @throws APIException if the user doesn't have write permissions for the given templates.
	 */
	private static function checkTemplatesPermissions(array $actions): void {
		$templateids = [];

		foreach ($actions as $action) {
			if (array_key_exists('filter', $action) && array_key_exists('conditions', $action['filter'])) {
				foreach ($action['filter']['conditions'] as $condition) {
					if ($condition['conditiontype'] == CONDITION_TYPE_TEMPLATE) {
						$templateids[] = $condition['value'];
					}
				}
			}

			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $action)) {
					continue;
				}

				foreach ($action[$operation_group] as $operation) {
					if ($operation['operationtype'] == OPERATION_TYPE_TEMPLATE_ADD
							|| $operation['operationtype'] == OPERATION_TYPE_TEMPLATE_REMOVE) {
						$templateids = array_merge($templateids, array_column($operation['optemplate'], 'templateid'));
					}
				}
			}
		}

		if (!$templateids) {
			return;
		}

		$templateids = array_keys(array_flip($templateids));

		$count = API::Template()->get([
			'countOutput' => true,
			'templateids' => $templateids,
			'editable' => true
		]);

		if ($count != count($templateids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect action condition or operation template. Template does not exist or you have no access to it.')
			);
		}
	}

	/**
	 * Checks if the current user has access to the given triggers.
	 *
	 * @param array $actions
	 *
	 * @throws APIException if the user doesn't have write permissions for the given triggers.
	 */
	private static function checkTriggersPermissions(array $actions): void {
		$triggerids = [];

		foreach ($actions as $action) {
			if (!array_key_exists('filter', $action) || !array_key_exists('conditions', $action['filter'])) {
				continue;
			}

			foreach ($action['filter']['conditions'] as $condition) {
				if ($condition['conditiontype'] == CONDITION_TYPE_TRIGGER) {
					$triggerids[$condition['value']] = true;
				}
			}
		}

		if (!$triggerids) {
			return;
		}

		$triggerids = array_keys($triggerids);

		$count = API::Trigger()->get([
			'countOutput' => true,
			'triggerids' => $triggerids,
			'editable' => true
		]);

		if ($count != count($triggerids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect action condition trigger. Trigger does not exist or you have no access to it.')
			);
		}
	}

	/**
	 * Checks if the current user has access to the given discovery rules.
	 *
	 * @param array $actions
	 *
	 * @throws APIException if the user doesn't have write permissions for the given discovery rules.
	 */
	private static function checkDRulesPermissions(array $actions): void {
		$druleids = [];

		foreach ($actions as $action) {
			if (!array_key_exists('filter', $action) || !array_key_exists('conditions', $action['filter'])) {
				continue;
			}

			foreach ($action['filter']['conditions'] as $condition) {
				if ($condition['conditiontype'] == CONDITION_TYPE_DRULE) {
					$druleids[$condition['value']] = true;
				}
			}
		}

		if (!$druleids) {
			return;
		}

		$druleids = array_keys($druleids);

		$count = API::DRule()->get([
			'countOutput' => true,
			'druleids' => $druleids,
			'editable' => true
		]);

		if ($count != count($druleids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect action condition discovery rule. Discovery rule does not exist or you have no access to it.')
			);
		}
	}

	/**
	 * Checks if the current user has access to the given discovery checks.
	 *
	 * @param array $actions
	 *
	 * @throws APIException if the user doesn't have write permissions for the given discovery checks.
	 */
	private static function checkDChecksPermissions(array $actions): void {
		$dcheckids = [];

		foreach ($actions as $action) {
			if (!array_key_exists('filter', $action) || !array_key_exists('conditions', $action['filter'])) {
				continue;
			}

			foreach ($action['filter']['conditions'] as $condition) {
				if ($condition['conditiontype'] == CONDITION_TYPE_DCHECK) {
					$druleids[$condition['value']] = true;
				}
			}
		}

		if (!$dcheckids) {
			return;
		}

		$dcheckids = array_keys($dcheckids);

		$count = API::DCheck()->get([
			'countOutput' => true,
			'dcheckids' => $dcheckids,
			'editable' => true
		]);

		if ($count != count($dcheckids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect action condition discovery check. Discovery check does not exist or you have no access to it.')
			);
		}
	}

	/**
	 * Checks if the current user has access to the given proxies.
	 *
	 * @param array $actions
	 *
	 * @throws APIException if the user doesn't have write permissions for the given proxies.
	 */
	private static function checkProxiesPermissions(array $actions): void {
		$proxyids = [];

		foreach ($actions as $action) {
			if (!array_key_exists('filter', $action) || !array_key_exists('conditions', $action['filter'])) {
				continue;
			}

			foreach ($action['filter']['conditions'] as $condition) {
				if ($condition['conditiontype'] == CONDITION_TYPE_PROXY) {
					$proxyids[$condition['value']] = true;
				}
			}
		}

		if (!$proxyids) {
			return;
		}

		$proxyids = array_keys($proxyids);

		$count = API::Proxy()->get([
			'countOutput' => true,
			'proxyids' => $proxyids,
			'editable' => true
		]);

		if ($count != count($proxyids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect action condition proxy. Proxy does not exist or you have no access to it.')
			);
		}
	}

	/**
	 * Checks if the current user has access to the given services.
	 *
	 * @param array $actions
	 *
	 * @throws APIException if the user doesn't have write permissions for the given services.
	 */
	private static function checkServicesPermissions(array $actions): void {
		$serviceids = [];

		foreach ($actions as $action) {
			if (!array_key_exists('filter', $action) || !array_key_exists('conditions', $action['filter'])) {
				continue;
			}

			foreach ($action['filter']['conditions'] as $condition) {
				if ($condition['conditiontype'] == CONDITION_TYPE_SERVICE) {
					$serviceids[$condition['value']] = true;
				}
			}
		}

		if ($serviceids) {
			return;
		}

		$serviceids = array_keys($serviceids);

		$count = API::Service()->get([
			'countOutput' => true,
			'serviceids' => $serviceids
		]);

		if ($count != count($serviceids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect action condition service. Service does not exist or you have no access to it.')
			);
		}
	}

	/**
	 * Add existing filter with conditions and operations to $db_actions if they are affected by the update.
	 *
	 * @param array      $actions
	 * @param array|null $db_actions
	 */
	private static function addAffectedObjects(array $actions, array &$db_actions = null): void {
		$actionids = ['filter' => [], 'operations' => []];

		foreach ($actions as $action) {
			if (array_key_exists('filter', $action)) {
				$actionids['filter'][] = $action['actionid'];
				$db_actions[$action['actionid']]['filter'] = [];
				$db_actions[$action['actionid']]['filter']['conditions'] = [];
			}

			if (!array_intersect_key(array_flip(self::OPERATION_GROUPS), $action)) {
				continue;
			}

			$actionids['operations'][] = $action['actionid'];

			foreach (self::OPERATION_GROUPS as $operation_group) {
				$db_actions[$action['actionid']][$operation_group] = [];
			}
		}

		if ($actionids['filter']) {
			$options = [
				'output' => ['actionid', 'evaltype', 'formula'],
				'filter' => ['actionid' => $actionids['filter']]
			];
			$db_filters = DBselect(DB::makeSql('actions', $options));

			while ($db_filter = DBfetch($db_filters)) {
				$db_actions[$db_filter['actionid']]['filter'] += array_diff_key($db_filter, array_flip(['actionid']));
			}

			$options = [
				'output' => ['conditionid', 'actionid', 'conditiontype', 'operator', 'value', 'value2'],
				'filter' => ['actionid' => $actionids['filter']]
			];
			$db_conditions = DBselect(DB::makeSql('conditions', $options));

			while ($db_condition = DBfetch($db_conditions)) {
				$db_actions[$db_condition['actionid']]['filter']['conditions'][$db_condition['conditionid']] =
					array_diff_key($db_condition, array_flip(['actionid']));
			}

			foreach ($db_actions as &$db_action) {
				if ($db_action['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					$formula = $db_action['filter']['formula'];

					$formulaids = CConditionHelper::getFormulaIds($formula);

					foreach ($db_action['filter']['conditions'] as &$db_condition) {
						$db_condition['formulaid'] = $formulaids[$db_condition['conditionid']];
					}
					unset($db_condition);
				}
			}
			unset($db_action);
		}

		if (!$actionids['operations']) {
			return;
		}

		$operationids = array_fill_keys([
			'opconditions', 'opmessage_grp', 'opmessage_usr', 'opcommand_grp', 'opcommand_hst', 'opgroup', 'optemplate'
		], []);

		$db_operations = DBselect(
			'SELECT o.operationid,o.actionid,o.operationtype,o.esc_period,o.esc_step_from,o.esc_step_to,o.evaltype,'.
				'o.recovery,m.default_msg,m.subject,m.message,m.mediatypeid,c.scriptid,i.inventory_mode'.
			' FROM operations o'.
				' LEFT JOIN opmessage m ON m.operationid=o.operationid'.
				' LEFT JOIN opcommand c ON c.operationid=o.operationid'.
				' LEFT JOIN opinventory i ON i.operationid=o.operationid'.
			' WHERE '.dbConditionId('o.actionid', $actionids['operations'])
		);

		while ($db_operation = DBfetch($db_operations)) {
			$operation = [
				'operationid' => $db_operation['operationid'],
				'operationtype' => $db_operation['operationtype'],
				'evaltype' => $db_operation['evaltype'],
				'recovery' => $db_operation['recovery']
			];

			$eventsource = $db_actions[$db_operation['actionid']]['eventsource'];

			if ($db_operation['recovery'] == ACTION_OPERATION
					&& in_array($eventsource, [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
				$operation['esc_period'] = $db_operation['esc_period'];
				$operation['esc_step_from'] = $db_operation['esc_step_from'];
				$operation['esc_step_to'] = $db_operation['esc_step_to'];

				if ($eventsource == EVENT_SOURCE_TRIGGERS) {
					$operation['opconditions'] = [];
					$operationids['opconditions'][$db_operation['operationid']] = true;
				}
			}

			switch ($db_operation['operationtype']) {
				case OPERATION_TYPE_MESSAGE:
				case OPERATION_TYPE_RECOVERY_MESSAGE:
				case OPERATION_TYPE_UPDATE_MESSAGE:
					$operation['opmessage'] = [
						'default_msg' => $db_operation['default_msg'],
						'subject' => $db_operation['subject'],
						'message' => $db_operation['message'],
						'mediatypeid' => $db_operation['mediatypeid']
					];

					if ($db_operation['operationtype'] == OPERATION_TYPE_MESSAGE) {
						$operation['opmessage_grp'] = [];
						$operation['opmessage_usr'] = [];
						$operationids['opmessage_grp'][$db_operation['operationid']] = true;
						$operationids['opmessage_usr'][$db_operation['operationid']] = true;
					}
					break;

				case OPERATION_TYPE_COMMAND:
					$operation['opcommand']['scriptid'] = $db_operation['scriptid'];

					if ($eventsource != EVENT_SOURCE_SERVICE) {
						$operation['opcommand_grp'] = [];
						$operation['opcommand_hst'] = [];
						$operationids['opcommand_grp'][$db_operation['operationid']] = true;
						$operationids['opcommand_hst'][$db_operation['operationid']] = true;
					}
					break;

				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					$operationids['opgroup'][$db_operation['operationid']] = true;
					break;

				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					$operationids['optemplate'][$db_operation['operationid']] = true;
					break;

				case OPERATION_TYPE_HOST_INVENTORY:
					$operation['opinventory']['inventory_mode'] = $db_operation['inventory_mode'];
					break;
			}

			$operation_group = self::OPERATION_GROUPS[$db_operation['recovery']];

			$db_actions[$db_operation['actionid']][$operation_group][$db_operation['operationid']] = $operation;
		}

		$db_opdata = [];

		if ($operationids['opconditions']) {
			$options = [
				'output' => ['opconditionid', 'operationid', 'conditiontype', 'operator', 'value'],
				'filter' => ['operationid' => array_keys($operationids['opconditions'])]
			];
			$db_opconditions = DBselect(DB::makeSql('opconditions', $options));

			while ($db_opcondition = DBfetch($db_opconditions)) {
				$db_opdata[$db_opcondition['operationid']]['opconditions'][$db_opcondition['opconditionid']] =
					array_diff_key($db_opcondition, array_flip(['operationid']));
			}
		}

		if ($operationids['opmessage_grp']) {
			$options = [
				'output' => ['opmessage_grpid', 'operationid', 'usrgrpid'],
				'filter' => ['operationid' => array_keys($operationids['opmessage_grp'])]
			];
			$db_opmessage_grps = DBselect(DB::makeSql('opmessage_grp', $options));

			while ($db_opmessage_grp = DBfetch($db_opmessage_grps)) {
				$db_opdata[$db_opmessage_grp['operationid']]['opmessage_grp'][$db_opmessage_grp['opmessage_grpid']] =
					array_diff_key($db_opmessage_grp, array_flip(['operationid']));
			}
		}

		if ($operationids['opmessage_usr']) {
			$options = [
				'output' => ['opmessage_usrid', 'operationid', 'userid'],
				'filter' => ['operationid' => array_keys($operationids['opmessage_usr'])]
			];
			$db_opmessage_usrs = DBselect(DB::makeSql('opmessage_usr', $options));

			while ($db_opmessage_usr = DBfetch($db_opmessage_usrs)) {
				$db_opdata[$db_opmessage_usr['operationid']]['opmessage_usr'][$db_opmessage_usr['opmessage_usrid']] =
					array_diff_key($db_opmessage_usr, array_flip(['operationid']));
			}
		}

		if ($operationids['opcommand_grp']) {
			$options = [
				'output' => ['opcommand_grpid', 'operationid', 'groupid'],
				'filter' => ['operationid' => array_keys($operationids['opcommand_grp'])]
			];
			$db_opcommand_grps = DBselect(DB::makeSql('opcommand_grp', $options));

			while ($db_opcommand_grp = DBfetch($db_opcommand_grps)) {
				$db_opdata[$db_opcommand_grp['operationid']]['opcommand_grp'][$db_opcommand_grp['opcommand_grpid']] =
					array_diff_key($db_opcommand_grp, array_flip(['operationid']));
			}
		}

		if ($operationids['opcommand_hst']) {
			$options = [
				'output' => ['opcommand_hstid', 'operationid', 'hostid'],
				'filter' => ['operationid' => array_keys($operationids['opcommand_hst'])]
			];
			$db_opcommand_hsts = DBselect(DB::makeSql('opcommand_hst', $options));

			while ($db_opcommand_hst = DBfetch($db_opcommand_hsts)) {
				$db_opdata[$db_opcommand_hst['operationid']]['opcommand_hst'][$db_opcommand_hst['opcommand_hstid']] =
					array_diff_key($db_opcommand_hst, array_flip(['operationid']));
			}
		}

		if ($operationids['opgroup']) {
			$options = [
				'output' => ['opgroupid', 'operationid', 'groupid'],
				'filter' => ['operationid' => array_keys($operationids['opgroup'])]
			];
			$db_opgroups = DBselect(DB::makeSql('opgroup', $options));

			while ($db_opgroup = DBfetch($db_opgroups)) {
				$db_opdata[$db_opgroup['operationid']]['opgroup'][$db_opgroup['opgroupid']] =
					array_diff_key($db_opgroup, array_flip(['operationid']));
			}
		}

		if ($operationids['optemplate']) {
			$options = [
				'output' => ['optemplateid', 'operationid', 'templateid'],
				'filter' => ['operationid' => array_keys($operationids['optemplate'])]
			];
			$db_optemplates = DBselect(DB::makeSql('optemplate', $options));

			while ($db_optemplate = DBfetch($db_optemplates)) {
				$db_opdata[$db_optemplate['operationid']]['optemplate'][$db_optemplate['optemplateid']] =
					array_diff_key($db_optemplate, array_flip(['operationid']));
			}
		}

		foreach ($db_actions as &$db_action) {
			foreach (self::OPERATION_GROUPS as $operation_group) {
				if (!array_key_exists($operation_group, $db_action)) {
					continue;
				}

				foreach ($db_action[$operation_group] as &$db_operation) {
					if (array_key_exists($db_operation['operationid'], $db_opdata)) {
						$db_operation = array_merge($db_operation, $db_opdata[$db_operation['operationid']]);
					}
				}
				unset($db_operation);
			}
		}
		unset($db_action);
	}
}
