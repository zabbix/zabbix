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
 * Class containing methods for operations with actions.
 */
class CAction extends CApiService {

	protected $tableName = 'actions';
	protected $tableAlias = 'a';
	protected $sortColumns = ['actionid', 'name', 'status'];

	/**
	 * Get actions data
	 *
	 * @param array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['actionids']
	 * @param array $options['applicationids']
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
			'selectAcknowledgeOperations'	=> null,
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
			$sqlParts['where'][] = dbConditionInt('om.mediatypeid', $options['mediatypeids']);
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
			$sqlParts['where'][] = '('.dbConditionInt('oc.scriptid', $options['scriptids']).
				' AND oc.type='.ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT.')';
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
		$dbRes = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
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
					' AND '.dbConditionInt('o.actionid', $actionIds).
					' AND oc.type='.ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT
			);
			while ($script = DBfetch($dbScripts)) {
				if (!isset($scripts[$script['scriptid']])) {
					$scripts[$script['scriptid']] = [];
				}
				$scripts[$script['scriptid']][$script['actionid']] = $script['actionid'];
				$scriptIds[$script['scriptid']] = $script['scriptid'];
			}

			$allowedScripts = API::Script()->get([
				'scriptids' => $scriptIds,
				'output' => ['scriptid'],
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
	 * Add actions.
	 *
	 * @param array $actions multidimensional array with actions data
	 * @param array $actions[0,...]['expression']
	 * @param array $actions[0,...]['description']
	 * @param array $actions[0,...]['type'] OPTIONAL
	 * @param array $actions[0,...]['priority'] OPTIONAL
	 * @param array $actions[0,...]['status'] OPTIONAL
	 * @param array $actions[0,...]['comments'] OPTIONAL
	 * @param array $actions[0,...]['url'] OPTIONAL
	 * @param array $actions[0,...]['filter'] OPTIONAL
	 * @param array $actions[0,...]['maintenance_mode'] OPTIONAL
	 *
	 * @return array
	 */
	public function create($actions) {
		$actions = zbx_toArray($actions);

		$this->validateCreate($actions);

		// Set "evaltype" if specified in "filter" section of action.
		foreach ($actions as &$action) {
			if (isset($action['filter'])) {
				$action['evaltype'] = $action['filter']['evaltype'];
			}
			$action += [
				'r_shortdata' => ACTION_DEFAULT_SUBJ_RECOVERY,
				'r_longdata' => ACTION_DEFAULT_MSG_RECOVERY,
				'ack_shortdata' => ACTION_DEFAULT_SUBJ_ACKNOWLEDGE,
				'ack_longdata' => ACTION_DEFAULT_MSG_ACKNOWLEDGE
			];

			// Set default values for recovery operations and their messages.
			if (array_key_exists('recovery_operations', $action)) {
				foreach ($action['recovery_operations'] as &$operation) {
					if (array_key_exists('operationid', $operation)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value "%1$s" for "%2$s" field.',
							$operation['operationid'], 'operationid'
						));
					}

					if ($operation['operationtype'] == OPERATION_TYPE_MESSAGE
							|| $operation['operationtype'] == OPERATION_TYPE_RECOVERY_MESSAGE) {
						$message = (array_key_exists('opmessage', $operation) && is_array($operation['opmessage']))
							? $operation['opmessage']
							: [];

						if (array_key_exists('default_msg', $message) && $message['default_msg'] == 1) {
							$message['subject'] = $action['r_shortdata'];
							$message['message'] = $action['r_longdata'];
						}

						$operation['opmessage'] = $message + [
							'default_msg' => 0,
							'mediatypeid' => 0,
							'subject' => ACTION_DEFAULT_SUBJ_RECOVERY,
							'message' => ACTION_DEFAULT_MSG_RECOVERY
						];
					}
				}
				unset($operation);
			}

			// Set default values for acknowledge operations and their messages.
			if (array_key_exists('acknowledge_operations', $action)) {
				foreach ($action['acknowledge_operations'] as &$operation) {
					if (array_key_exists('operationid', $operation)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value "%1$s" for "%2$s" field.',
							$operation['operationid'], 'operationid'
						));
					}

					if ($operation['operationtype'] == OPERATION_TYPE_MESSAGE
							|| $operation['operationtype'] == OPERATION_TYPE_ACK_MESSAGE) {
						$message = (array_key_exists('opmessage', $operation) && is_array($operation['opmessage']))
							? $operation['opmessage']
							: [];

						if (array_key_exists('default_msg', $message) && $message['default_msg'] == 1) {
							$message['subject'] = $action['ack_shortdata'];
							$message['message'] = $action['ack_longdata'];
						}

						$operation['opmessage'] = $message + [
							'default_msg'	=> 0,
							'mediatypeid'	=> 0,
							'subject'		=> ACTION_DEFAULT_SUBJ_ACKNOWLEDGE,
							'message'		=> ACTION_DEFAULT_MSG_ACKNOWLEDGE
						];
					}
				}
				unset($operation);
			}
		}
		unset($action);

		// Insert actions into db, get back array with new actionids.
		$actions = DB::save('actions', $actions);
		$actions = zbx_toHash($actions, 'actionid');

		$conditions_to_create = [];
		$operations_to_create = [];

		// Collect conditions and operations to be created and set appropriate action ID.
		foreach ($actions as $actionid => $action) {
			if (isset($action['filter'])) {
				foreach ($action['filter']['conditions'] as $condition) {
					$condition['actionid'] = $actionid;
					$conditions_to_create[] = $condition;
				}
			}

			if (array_key_exists('operations', $action) && $action['operations']) {
				foreach ($action['operations'] as $operation) {
					$operation['actionid'] = $actionid;
					$operation['recovery'] = ACTION_OPERATION;
					$operations_to_create[] = $operation;
				}
			}

			if (array_key_exists('recovery_operations', $action) && $action['recovery_operations']) {
				foreach ($action['recovery_operations'] as $recovery_operation) {
					$recovery_operation['actionid'] = $actionid;
					$recovery_operation['recovery'] = ACTION_RECOVERY_OPERATION;
					unset($recovery_operation['esc_period'], $recovery_operation['esc_step_from'],
						$recovery_operation['esc_step_to']
					);

					if ($recovery_operation['operationtype'] == OPERATION_TYPE_RECOVERY_MESSAGE) {
						unset($recovery_operation['opmessage']['mediatypeid']);
					}

					$operations_to_create[] = $recovery_operation;
				}
			}

			if (array_key_exists('acknowledge_operations', $action) && $action['acknowledge_operations']) {
				foreach ($action['acknowledge_operations'] as $ack_operation) {
					$ack_operation['actionid'] = $actionid;
					$ack_operation['recovery'] = ACTION_ACKNOWLEDGE_OPERATION;
					unset($ack_operation['esc_period'], $ack_operation['esc_step_from'], $ack_operation['esc_step_to']);
					$operations_to_create[] = $ack_operation;
				}
			}
		}

		$createdConditions = $this->addConditions($conditions_to_create);

		// Group back created action conditions by action ID to be used for updating action formula.
		$conditionsForActions = [];
		foreach ($createdConditions as $condition) {
			$conditionsForActions[$condition['actionid']][$condition['conditionid']] = $condition;
		}

		// Update "formula" field if evaltype is custom expression.
		foreach ($actions as $actionid => $action) {
			if (isset($action['filter'])) {
				$actionFilter = $action['filter'];
				if ($actionFilter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					$this->updateFormula($actionid, $actionFilter['formula'], $conditionsForActions[$actionid]);
				}
			}
		}

		// Add operations.
		$this->addOperations($operations_to_create);

		return ['actionids' => array_keys($actions)];
	}

	/**
	 * Update actions.
	 *
	 * @param array $actions multidimensional array with actions data
	 * @param array $actions[0,...]['actionid']
	 * @param array $actions[0,...]['expression']
	 * @param array $actions[0,...]['description']
	 * @param array $actions[0,...]['type'] OPTIONAL
	 * @param array $actions[0,...]['priority'] OPTIONAL
	 * @param array $actions[0,...]['status'] OPTIONAL
	 * @param array $actions[0,...]['comments'] OPTIONAL
	 * @param array $actions[0,...]['url'] OPTIONAL
	 * @param array $actions[0,...]['filter'] OPTIONAL
	 * @param array $actions[0,...]['maintenance_mode'] OPTIONAL
	 *
	 * @return array
	 */
	public function update($actions) {
		$actions = zbx_toArray($actions);
		$actions = zbx_toHash($actions, 'actionid');
		$actionIds = array_keys($actions);

		$db_actions = $this->get([
			'output' => API_OUTPUT_EXTEND,
			'selectFilter' => ['formula', 'conditions'],
			'selectOperations' => API_OUTPUT_EXTEND,
			'selectRecoveryOperations' => API_OUTPUT_EXTEND,
			'selectAcknowledgeOperations' => ['operationid', 'actionid', 'operationtype', 'opmessage', 'opmessage_grp',
				'opmessage_usr', 'opcommand', 'opcommand_hst', 'opcommand_grp'
			],
			'actionids' => $actionIds,
			'editable' => true,
			'preservekeys' => true
		]);

		$this->validateUpdate($actions, $db_actions);

		$operations_to_create = [];
		$operations_to_update = [];
		$operationids_to_delete = [];

		$actionsUpdateData = [];

		$newActionConditions = null;
		foreach ($actions as $actionId => $action) {
			$db_action = $db_actions[$actionId];

			$actionUpdateValues = $action;
			unset(
				$actionUpdateValues['actionid'],
				$actionUpdateValues['filter'],
				$actionUpdateValues['operations'],
				$actionUpdateValues['recovery_operations'],
				$actionUpdateValues['acknowledge_operations'],
				$actionUpdateValues['conditions'],
				$actionUpdateValues['formula'],
				$actionUpdateValues['evaltype']
			);

			if (isset($action['filter'])) {
				$actionFilter = $action['filter'];

				// set formula to empty string of not custom expression
				if ($actionFilter['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
					$actionUpdateValues['formula'] = '';
				}

				$actionUpdateValues['evaltype'] = $actionFilter['evaltype'];
			}

			if (array_key_exists('operations', $action)) {
				$db_operations = zbx_toHash($db_action['operations'], 'operationid');

				foreach ($action['operations'] as $operation) {
					if (!array_key_exists('operationid', $operation)) {
						$operation['actionid'] = $action['actionid'];
						$operation['recovery'] = ACTION_OPERATION;
						$operations_to_create[] = $operation;
					}
					else {
						$operationid = $operation['operationid'];

						if (array_key_exists($operationid, $db_operations)) {
							$operation['recovery'] = ACTION_OPERATION;
							$operations_to_update[] = $operation;
							unset($db_operations[$operationid]);
						}
						else {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value "%1$s" for "%2$s" field.',
								$operationid, 'operationid'
							));
						}
					}
				}
				$operationids_to_delete = array_merge($operationids_to_delete, array_keys($db_operations));
			}

			if (array_key_exists('recovery_operations', $action)) {
				$db_recovery_operations = zbx_toHash($db_action['recoveryOperations'], 'operationid');

				foreach ($action['recovery_operations'] as $recovery_operation) {
					unset($recovery_operation['esc_period'], $recovery_operation['esc_step_from'],
						$recovery_operation['esc_step_to']
					);
					$recovery_operation['actionid'] = $action['actionid'];

					if (!array_key_exists('operationid', $recovery_operation)) {
						if ($recovery_operation['operationtype'] == OPERATION_TYPE_RECOVERY_MESSAGE) {
							unset($recovery_operation['opmessage']['mediatypeid']);
						}

						$recovery_operation['recovery'] = ACTION_RECOVERY_OPERATION;
						$operations_to_create[] = $recovery_operation;
					}
					else {
						$recovery_operationid = $recovery_operation['operationid'];

						if (array_key_exists($recovery_operationid, $db_recovery_operations)) {
							$db_operation_type = $db_recovery_operations[$recovery_operationid]['operationtype'];
							if ((array_key_exists('operationtype', $recovery_operation)
									&& $recovery_operation['operationtype'] == OPERATION_TYPE_RECOVERY_MESSAGE)
									|| (!array_key_exists('operationtype', $recovery_operation)
										&& $db_operation_type == OPERATION_TYPE_RECOVERY_MESSAGE)) {
								unset($recovery_operation['opmessage']['mediatypeid']);
							}

							$recovery_operation['recovery'] = ACTION_RECOVERY_OPERATION;
							$operations_to_update[] = $recovery_operation;
							unset($db_recovery_operations[$recovery_operationid]);
						}
						else {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value "%1$s" for "%2$s" field.',
								$recovery_operationid, 'operationid'
							));
						}
					}
				}
				$operationids_to_delete = array_merge($operationids_to_delete, array_keys($db_recovery_operations));
			}

			if (array_key_exists('acknowledge_operations', $action)) {
				$db_ack_operations = zbx_toHash($db_action['acknowledgeOperations'], 'operationid');

				foreach ($action['acknowledge_operations'] as $ack_operation) {
					$ack_operation['recovery'] = ACTION_ACKNOWLEDGE_OPERATION;
					$opmessage = (array_key_exists('opmessage', $ack_operation) && is_array($ack_operation['opmessage']))
						? $ack_operation['opmessage']
						: [];
					unset($ack_operation['esc_period'], $ack_operation['esc_step_from'], $ack_operation['esc_step_to']);
					$ack_operation['actionid'] = $action['actionid'];

					if (!array_key_exists('operationid', $ack_operation)) {
						if ($ack_operation['operationtype'] == OPERATION_TYPE_MESSAGE
								|| $ack_operation['operationtype'] == OPERATION_TYPE_ACK_MESSAGE) {
							$opmessage += [
								'default_msg'	=> 0,
								'mediatypeid'	=> 0,
								'subject'		=> ACTION_DEFAULT_SUBJ_ACKNOWLEDGE,
								'message'		=> ACTION_DEFAULT_MSG_ACKNOWLEDGE
							];

							if ($opmessage['default_msg'] == 1) {
								$opmessage['subject'] = array_key_exists('ack_shortdata', $action)
									? $action['ack_shortdata']
									: $db_action['ack_shortdata'];
								$opmessage['message'] = array_key_exists('ack_longdata', $action)
									? $action['ack_longdata']
									: $db_action['ack_longdata'];
							}

							$ack_operation['opmessage'] = $opmessage;
						}

						$operations_to_create[] = $ack_operation;
					}
					elseif (array_key_exists($ack_operation['operationid'], $db_ack_operations)) {
						if ($ack_operation['operationtype'] == OPERATION_TYPE_MESSAGE
								|| $ack_operation['operationtype'] == OPERATION_TYPE_ACK_MESSAGE) {
							$db_opmessage = $db_ack_operations[$ack_operation['operationid']]['opmessage'];
							$default_msg = array_key_exists('default_msg', $opmessage)
								? $opmessage['default_msg']
								: $db_opmessage['default_msg'];

							if ($default_msg == 1) {
								$opmessage['subject'] = array_key_exists('ack_shortdata', $action)
									? $action['ack_shortdata']
									: $db_action['ack_shortdata'];
								$opmessage['message'] = array_key_exists('ack_longdata', $action)
									? $action['ack_longdata']
									: $db_action['ack_longdata'];
								$ack_operation['opmessage'] = $opmessage;
							}
						}

						$operations_to_update[] = $ack_operation;
						unset($db_ack_operations[$ack_operation['operationid']]);
					}
					else {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value "%1$s" for "%2$s" field.',
							$ack_operation['operationid'], 'operationid'
						));
					}
				}
				$operationids_to_delete = array_merge($operationids_to_delete, array_keys($db_ack_operations));
			}

			if ($actionUpdateValues) {
				$actionsUpdateData[] = ['values' => $actionUpdateValues, 'where' => ['actionid' => $actionId]];
			}
		}

		if ($actionsUpdateData) {
			DB::update('actions', $actionsUpdateData);
		}

		// add, update and delete operations
		$this->addOperations($operations_to_create);
		$this->updateOperations($operations_to_update, $db_actions);
		if (!empty($operationids_to_delete)) {
			$this->deleteOperations($operationids_to_delete);
		}

		// set actionid for all conditions and group by actionid into $newActionConditions
		$newActionConditions = null;
		foreach ($actions as $actionId => $action) {
			if (isset($action['filter'])) {
				if ($newActionConditions === null) {
					$newActionConditions = [];
				}

				$newActionConditions[$actionId] = [];
				foreach ($action['filter']['conditions'] as $condition) {
					$condition['actionid'] = $actionId;
					$newActionConditions[$actionId][] = $condition;
				}
			}
		}

		// if we have any conditions, fetch current conditions from db and do replace by position and group result
		// by actionid into $actionConditions
		$actionConditions = [];
		if ($newActionConditions !== null) {
			$existingConditions = DBfetchArray(DBselect(
				'SELECT conditionid,actionid,conditiontype,operator,value,value2'.
				' FROM conditions'.
				' WHERE '.dbConditionInt('actionid', $actionIds).
				' ORDER BY conditionid'
			));
			$existingActionConditions = [];
			foreach ($existingConditions as $condition) {
				$existingActionConditions[$condition['actionid']][] = $condition;
			}

			$conditions = DB::replaceByPosition('conditions', $existingActionConditions, $newActionConditions);
			foreach ($conditions as $condition) {
				$actionConditions[$condition['actionid']][] = $condition;
			}
		}

		// update formulas for user expressions using new conditions
		foreach ($actions as $actionId => $action) {
			if (isset($action['filter']) && $action['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$this->updateFormula($actionId, $action['filter']['formula'], $actionConditions[$actionId]);
			}
		}

		return ['actionids' => $actionIds];
	}

	/**
	 * @param array $conditions
	 *
	 * @return mixed
	 */
	protected function addConditions($conditions) {
		foreach ($conditions as $condition) {
			$connectionDbFields = [
				'actionid' => null,
				'conditiontype' => null
			];
			if (!check_db_fields($connectionDbFields, $condition)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameters for condition.'));
			}
		}

		return DB::save('conditions', $conditions);
	}

	protected function updateConditions($conditions) {
		$update = [];
		foreach ($conditions as $condition) {
			$conditionId = $condition['conditionid'];
			unset($condition['conditionid']);
			$update = [
				'values' => $condition,
				'where' => ['conditionid' => $conditionId]
			];
		}
		DB::update('conditions', $update);

		return $conditions;
	}

	protected function deleteConditions($conditionids) {
		DB::delete('conditions', ['conditionid' => $conditionids]);
	}

	/**
	 * @param array $operations
	 *
	 * @return bool
	 */
	protected function addOperations($operations) {
		foreach ($operations as $operation) {
			$operationDbFields = [
				'actionid' => null,
				'operationtype' => null
			];
			if (!check_db_fields($operationDbFields, $operation)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameter for operations.'));
			}
		}

		$operations = DB::save('operations', $operations);
		$operations = zbx_toHash($operations, 'operationid');

		$opMessagesToInsert = [];
		$opCommandsToInsert = [];
		$msggroups_to_insert = [];
		$msgusers_to_insert = [];
		$opCommandHstsToInsert = [];
		$opCommandGroupInserts = [];
		$opGroupsToInsert = [];
		$opTemplatesToInsert = [];
		$opConditionsToInsert = [];
		$opInventoryToInsert = [];

		foreach ($operations as $operationId => $operation) {
			switch ($operation['operationtype']) {
				case OPERATION_TYPE_MESSAGE:
					if (isset($operation['opmessage']) && !empty($operation['opmessage'])) {
						$operation['opmessage']['operationid'] = $operationId;
						$opMessagesToInsert[] = $operation['opmessage'];
					}
					if (isset($operation['opmessage_usr'])) {
						foreach ($operation['opmessage_usr'] as $user) {
							$msgusers_to_insert[] = [
								'operationid' => $operationId,
								'userid' => $user['userid']
							];
						}
					}
					if (isset($operation['opmessage_grp'])) {
						foreach ($operation['opmessage_grp'] as $userGroup) {
							$msggroups_to_insert[] = [
								'operationid' => $operationId,
								'usrgrpid' => $userGroup['usrgrpid']
							];
						}
					}
					break;

				case OPERATION_TYPE_COMMAND:
					if (isset($operation['opcommand']) && !empty($operation['opcommand'])) {
						$operation['opcommand']['operationid'] = $operationId;
						$opCommandsToInsert[] = $operation['opcommand'];
					}
					if (isset($operation['opcommand_hst'])) {
						foreach ($operation['opcommand_hst'] as $host) {
							$opCommandHstsToInsert[] = [
								'operationid' => $operationId,
								'hostid' => $host['hostid']
							];
						}
					}
					if (isset($operation['opcommand_grp'])) {
						foreach ($operation['opcommand_grp'] as $hostGroup) {
							$opCommandGroupInserts[] = [
								'operationid' => $operationId,
								'groupid' => $hostGroup['groupid']
							];
						}
					}
					break;

				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					foreach ($operation['opgroup'] as $hostGroup) {
						$opGroupsToInsert[] = [
							'operationid' => $operationId,
							'groupid' => $hostGroup['groupid']
						];
					}
					break;

				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					foreach ($operation['optemplate'] as $template) {
						$opTemplatesToInsert[] = [
							'operationid' => $operationId,
							'templateid' => $template['templateid']
						];
					}
					break;

				case OPERATION_TYPE_HOST_INVENTORY:
					$opInventoryToInsert[] = [
						'operationid' => $operationId,
						'inventory_mode' => $operation['opinventory']['inventory_mode']
					];
					break;

				case OPERATION_TYPE_ACK_MESSAGE:
					// falls through
				case OPERATION_TYPE_RECOVERY_MESSAGE:
					if (array_key_exists('opmessage', $operation) && $operation['opmessage']) {
						$operation['opmessage']['operationid'] = $operationId;
						$opMessagesToInsert[] = $operation['opmessage'];
					}
					break;
			}
			if (isset($operation['opconditions'])) {
				foreach ($operation['opconditions'] as $opCondition) {
					$opCondition['operationid'] = $operationId;
					$opConditionsToInsert[] = $opCondition;
				}
			}
		}

		DB::insert('opmessage_grp', $msggroups_to_insert);
		DB::insert('opmessage_usr', $msgusers_to_insert);
		DB::insert('opconditions', $opConditionsToInsert);
		DB::insert('opmessage', $opMessagesToInsert, false);
		DB::insert('opcommand', $opCommandsToInsert, false);
		DB::insert('opcommand_hst', $opCommandHstsToInsert);
		DB::insert('opcommand_grp', $opCommandGroupInserts);
		DB::insert('opgroup', $opGroupsToInsert);
		DB::insert('optemplate', $opTemplatesToInsert);
		DB::insert('opinventory', $opInventoryToInsert, false);

		return true;
	}

	/**
	 * @param array $operations
	 * @param array $db_actions
	 */
	protected function updateOperations($operations, $db_actions) {
		$operationsUpdate = [];

		// messages
		$opMessagesToInsert = [];
		$opMessagesToUpdate = [];
		$opMessagesToDeleteByOpId = [];

		$opMessageGrpsToInsert = [];
		$opMessageUsrsToInsert = [];
		$opMessageGrpsToDeleteByOpId = [];
		$opMessageUsrsToDeleteByOpId = [];

		// commands
		$opCommandsToInsert = [];
		$opCommandsToUpdate = [];
		$opCommandsToDeleteByOpId = [];

		$opCommandGrpsToInsert = [];
		$opCommandHstsToInsert = [];
		$opCommandGrpsToDeleteByOpId = [];
		$opCommandHstsToDeleteByOpId = [];

		// groups
		$opGroupsToInsert = [];
		$opGroupsToDeleteByOpId = [];

		// templates
		$opTemplateToInsert = [];
		$opTemplatesToDeleteByOpId = [];

		// operation conditions
		$opConditionsToInsert = [];

		// inventory
		$opInventoryToInsert = [];
		$opInventoryToUpdate = [];
		$opInventoryToDeleteByOpId = [];

		$operation_actions_hashkey = [
			ACTION_OPERATION				=> 'operations',
			ACTION_RECOVERY_OPERATION		=> 'recoveryOperations',
			ACTION_ACKNOWLEDGE_OPERATION	=> 'acknowledgeOperations'
		];

		foreach ($operations as $operation) {
			$actions_key = $operation_actions_hashkey[$operation['recovery']];

			$operationsDb = zbx_toHash($db_actions[$operation['actionid']][$actions_key], 'operationid');

			$operationDb = $operationsDb[$operation['operationid']];

			$type_changed = false;

			if (isset($operation['operationtype']) && ($operation['operationtype'] != $operationDb['operationtype'])) {
				$type_changed = true;

				switch ($operationDb['operationtype']) {
					case OPERATION_TYPE_MESSAGE:
						$opMessagesToDeleteByOpId[] = $operationDb['operationid'];
						$opMessageGrpsToDeleteByOpId[] = $operationDb['operationid'];
						$opMessageUsrsToDeleteByOpId[] = $operationDb['operationid'];
						break;

					case OPERATION_TYPE_COMMAND:
						$opCommandsToDeleteByOpId[] = $operationDb['operationid'];
						$opCommandHstsToDeleteByOpId[] = $operationDb['operationid'];
						$opCommandGrpsToDeleteByOpId[] = $operationDb['operationid'];
						break;

					case OPERATION_TYPE_GROUP_ADD:
						if ($operation['operationtype'] == OPERATION_TYPE_GROUP_REMOVE) {
							break;
						}
					case OPERATION_TYPE_GROUP_REMOVE:
						if ($operation['operationtype'] == OPERATION_TYPE_GROUP_ADD) {
							break;
						}
						$opGroupsToDeleteByOpId[] = $operationDb['operationid'];
						break;

					case OPERATION_TYPE_TEMPLATE_ADD:
						if ($operation['operationtype'] == OPERATION_TYPE_TEMPLATE_REMOVE) {
							break;
						}
					case OPERATION_TYPE_TEMPLATE_REMOVE:
						if ($operation['operationtype'] == OPERATION_TYPE_TEMPLATE_ADD) {
							break;
						}
						$opTemplatesToDeleteByOpId[] = $operationDb['operationid'];
						break;

					case OPERATION_TYPE_HOST_INVENTORY:
						$opInventoryToDeleteByOpId[] = $operationDb['operationid'];
						break;

					case OPERATION_TYPE_ACK_MESSAGE:
						// falls through
					case OPERATION_TYPE_RECOVERY_MESSAGE:
						$opMessagesToDeleteByOpId[] = $operationDb['operationid'];
						break;
				}
			}

			if (!isset($operation['operationtype'])) {
				$operation['operationtype'] = $operationDb['operationtype'];
			}

			switch ($operation['operationtype']) {
				case OPERATION_TYPE_MESSAGE:
					if (!isset($operation['opmessage_grp'])) {
						$operation['opmessage_grp'] = [];
					}
					else {
						zbx_array_push($operation['opmessage_grp'], ['operationid' => $operation['operationid']]);
					}

					if (!isset($operation['opmessage_usr'])) {
						$operation['opmessage_usr'] = [];
					}
					else {
						zbx_array_push($operation['opmessage_usr'], ['operationid' => $operation['operationid']]);
					}

					if (!isset($operationDb['opmessage_usr'])) {
						$operationDb['opmessage_usr'] = [];
					}
					if (!isset($operationDb['opmessage_grp'])) {
						$operationDb['opmessage_grp'] = [];
					}

					if ($type_changed) {
						$operation['opmessage']['operationid'] = $operation['operationid'];
						$opMessagesToInsert[] = $operation['opmessage'];

						$opMessageGrpsToInsert = array_merge($opMessageGrpsToInsert, $operation['opmessage_grp']);
						$opMessageUsrsToInsert = array_merge($opMessageUsrsToInsert, $operation['opmessage_usr']);
					}
					else {
						if (array_key_exists('opmessage', $operation)) {
							$opMessagesToUpdate[] = [
								'values' => $operation['opmessage'],
								'where' => ['operationid' => $operation['operationid']]
							];
						}

						$diff = zbx_array_diff($operation['opmessage_grp'], $operationDb['opmessage_grp'], 'usrgrpid');
						$opMessageGrpsToInsert = array_merge($opMessageGrpsToInsert, $diff['first']);

						foreach ($diff['second'] as $opMessageGrp) {
							DB::delete('opmessage_grp', [
								'usrgrpid' => $opMessageGrp['usrgrpid'],
								'operationid' => $operation['operationid']
							]);
						}

						$diff = zbx_array_diff($operation['opmessage_usr'], $operationDb['opmessage_usr'], 'userid');
						$opMessageUsrsToInsert = array_merge($opMessageUsrsToInsert, $diff['first']);
						foreach ($diff['second'] as $opMessageUsr) {
							DB::delete('opmessage_usr', [
								'userid' => $opMessageUsr['userid'],
								'operationid' => $operation['operationid']
							]);
						}
					}
					break;

				case OPERATION_TYPE_COMMAND:
					if (!isset($operation['opcommand_grp'])) {
						$operation['opcommand_grp'] = [];
					}
					else {
						zbx_array_push($operation['opcommand_grp'], ['operationid' => $operation['operationid']]);
					}

					if (!isset($operation['opcommand_hst'])) {
						$operation['opcommand_hst'] = [];
					}
					else {
						zbx_array_push($operation['opcommand_hst'], ['operationid' => $operation['operationid']]);
					}

					if (!isset($operationDb['opcommand_grp'])) {
						$operationDb['opcommand_grp'] = [];
					}
					if (!isset($operationDb['opcommand_hst'])) {
						$operationDb['opcommand_hst'] = [];
					}

					if ($type_changed) {
						$operation['opcommand']['operationid'] = $operation['operationid'];
						$opCommandsToInsert[] = $operation['opcommand'];

						$opCommandGrpsToInsert = array_merge($opCommandGrpsToInsert, $operation['opcommand_grp']);
						$opCommandHstsToInsert = array_merge($opCommandHstsToInsert, $operation['opcommand_hst']);
					}
					else {
						// clear and reset fields to default values on type change
						if ($operation['opcommand']['type'] == ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT) {
							$operation['opcommand']['command'] = '';
						}
						else {
							$operation['opcommand']['scriptid'] = null;
						}
						if ($operation['opcommand']['type'] != ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT) {
							$operation['opcommand']['execute_on'] = ZBX_SCRIPT_EXECUTE_ON_AGENT;
						}
						if ($operation['opcommand']['type'] != ZBX_SCRIPT_TYPE_SSH
								&& $operation['opcommand']['type'] != ZBX_SCRIPT_TYPE_TELNET) {
							$operation['opcommand']['port'] = '';
							$operation['opcommand']['username'] = '';
							$operation['opcommand']['password'] = '';
						}
						if (!isset($operation['opcommand']['authtype'])) {
							$operation['opcommand']['authtype'] = ITEM_AUTHTYPE_PASSWORD;
						}
						if ($operation['opcommand']['authtype'] == ITEM_AUTHTYPE_PASSWORD) {
							$operation['opcommand']['publickey'] = '';
							$operation['opcommand']['privatekey'] = '';
						}

						$opCommandsToUpdate[] = [
							'values' => $operation['opcommand'],
							'where' => ['operationid' => $operation['operationid']]
						];

						$diff = zbx_array_diff($operation['opcommand_grp'], $operationDb['opcommand_grp'], 'groupid');
						$opCommandGrpsToInsert = array_merge($opCommandGrpsToInsert, $diff['first']);

						foreach ($diff['second'] as $opMessageGrp) {
							DB::delete('opcommand_grp', [
								'groupid' => $opMessageGrp['groupid'],
								'operationid' => $operation['operationid']
							]);
						}

						$diff = zbx_array_diff($operation['opcommand_hst'], $operationDb['opcommand_hst'], 'hostid');
						$opCommandHstsToInsert = array_merge($opCommandHstsToInsert, $diff['first']);
						$opCommandHostIds = zbx_objectValues($diff['second'], 'opcommand_hstid');
						if ($opCommandHostIds) {
							DB::delete('opcommand_hst', [
								'opcommand_hstid' => $opCommandHostIds
							]);
						}
					}
					break;

				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					if (!isset($operation['opgroup'])) {
						$operation['opgroup'] = [];
					}
					else {
						zbx_array_push($operation['opgroup'], ['operationid' => $operation['operationid']]);
					}

					if (!isset($operationDb['opgroup'])) {
						$operationDb['opgroup'] = [];
					}

					$diff = zbx_array_diff($operation['opgroup'], $operationDb['opgroup'], 'groupid');
					$opGroupsToInsert = array_merge($opGroupsToInsert, $diff['first']);
					foreach ($diff['second'] as $opGroup) {
						DB::delete('opgroup', [
							'groupid' => $opGroup['groupid'],
							'operationid' => $operation['operationid']
						]);
					}
					break;

				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					if (!isset($operation['optemplate'])) {
						$operation['optemplate'] = [];
					}
					else {
						zbx_array_push($operation['optemplate'], ['operationid' => $operation['operationid']]);
					}

					if (!isset($operationDb['optemplate'])) {
						$operationDb['optemplate'] = [];
					}

					$diff = zbx_array_diff($operation['optemplate'], $operationDb['optemplate'], 'templateid');
					$opTemplateToInsert = array_merge($opTemplateToInsert, $diff['first']);

					foreach ($diff['second'] as $opTemplate) {
						DB::delete('optemplate', [
							'templateid' => $opTemplate['templateid'],
							'operationid' => $operation['operationid']
						]);
					}
					break;

				case OPERATION_TYPE_HOST_INVENTORY:
					if ($type_changed) {
						$operation['opinventory']['operationid'] = $operation['operationid'];
						$opInventoryToInsert[] = $operation['opinventory'];
					}
					else {
						$opInventoryToUpdate[] = [
							'values' => $operation['opinventory'],
							'where' => ['operationid' => $operation['operationid']]
						];
					}
					break;

				case OPERATION_TYPE_ACK_MESSAGE:
					// falls throught
				case OPERATION_TYPE_RECOVERY_MESSAGE:
					if ($type_changed) {
						$operation['opmessage']['operationid'] = $operation['operationid'];
						$opMessagesToInsert[] = $operation['opmessage'];
					}
					elseif (array_key_exists('opmessage', $operation)) {
						$opMessagesToUpdate[] = [
							'values' => $operation['opmessage'],
							'where' => ['operationid' => $operation['operationid']]
						];
					}
					break;
			}

			if (!isset($operation['opconditions'])) {
				$operation['opconditions'] = [];
			}
			else {
				zbx_array_push($operation['opconditions'], ['operationid' => $operation['operationid']]);
			}

			self::validateOperationConditions($operation['opconditions']);

			$db_opconditions = array_key_exists('opconditions', $operationDb) ? $operationDb['opconditions'] : [];
			$diff = zbx_array_diff($operation['opconditions'], $db_opconditions, 'opconditionid');
			$opConditionsToInsert = array_merge($opConditionsToInsert, $diff['first']);

			$opConditionsIdsToDelete = zbx_objectValues($diff['second'], 'opconditionid');
			if (!empty($opConditionsIdsToDelete)) {
				DB::delete('opconditions', ['opconditionid' => $opConditionsIdsToDelete]);
			}

			$operationId = $operation['operationid'];
			unset($operation['operationid']);
			if (!empty($operation)) {
				$operationsUpdate[] = [
					'values' => $operation,
					'where' => ['operationid' => $operationId]
				];
			}
		}

		DB::update('operations', $operationsUpdate);

		if (!empty($opMessagesToDeleteByOpId)) {
			DB::delete('opmessage', ['operationid' => $opMessagesToDeleteByOpId]);
		}
		if (!empty($opCommandsToDeleteByOpId)) {
			DB::delete('opcommand', ['operationid' => $opCommandsToDeleteByOpId]);
		}
		if (!empty($opMessageGrpsToDeleteByOpId)) {
			DB::delete('opmessage_grp', ['operationid' => $opMessageGrpsToDeleteByOpId]);
		}
		if (!empty($opMessageUsrsToDeleteByOpId)) {
			DB::delete('opmessage_usr', ['operationid' => $opMessageUsrsToDeleteByOpId]);
		}
		if (!empty($opCommandHstsToDeleteByOpId)) {
			DB::delete('opcommand_hst', ['operationid' => $opCommandHstsToDeleteByOpId]);
		}
		if (!empty($opCommandGrpsToDeleteByOpId)) {
			DB::delete('opcommand_grp', ['operationid' => $opCommandGrpsToDeleteByOpId]);
		}
		if (!empty($opCommandGrpsToDeleteByOpId)) {
			DB::delete('opcommand_grp', ['opcommand_grpid' => $opCommandGrpsToDeleteByOpId]);
		}
		if (!empty($opCommandHstsToDeleteByOpId)) {
			DB::delete('opcommand_hst', ['opcommand_hstid' => $opCommandHstsToDeleteByOpId]);
		}
		if (!empty($opGroupsToDeleteByOpId)) {
			DB::delete('opgroup', ['operationid' => $opGroupsToDeleteByOpId]);
		}
		if (!empty($opTemplatesToDeleteByOpId)) {
			DB::delete('optemplate', ['operationid' => $opTemplatesToDeleteByOpId]);
		}
		if (!empty($opInventoryToDeleteByOpId)) {
			DB::delete('opinventory', ['operationid' => $opInventoryToDeleteByOpId]);
		}

		DB::insert('opmessage', $opMessagesToInsert, false);
		DB::insert('opcommand', $opCommandsToInsert, false);
		DB::insert('opmessage_grp', $opMessageGrpsToInsert);
		DB::insert('opmessage_usr', $opMessageUsrsToInsert);
		DB::insert('opcommand_grp', $opCommandGrpsToInsert);
		DB::insert('opcommand_hst', $opCommandHstsToInsert);
		DB::insert('opgroup', $opGroupsToInsert);
		DB::insert('optemplate', $opTemplateToInsert);
		DB::update('opmessage', $opMessagesToUpdate);
		DB::update('opcommand', $opCommandsToUpdate);
		DB::insert('opconditions', $opConditionsToInsert);
		DB::insert('opinventory', $opInventoryToInsert, false);
		DB::update('opinventory', $opInventoryToUpdate);
	}

	protected function deleteOperations($operationIds) {
		DB::delete('operations', ['operationid' => $operationIds]);
	}

	/**
	 * Delete actions.
	 *
	 * @param array $actionids
	 *
	 * @return array
	 */
	public function delete(array $actionids) {
		if (empty($actionids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$delActions = $this->get([
			'actionids' => $actionids,
			'editable' => true,
			'output' => ['actionid'],
			'preservekeys' => true
		]);
		foreach ($actionids as $actionid) {
			if (isset($delActions[$actionid])) {
				continue;
			}
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		DB::delete('actions', ['actionid' => $actionids]);
		DB::delete('alerts', ['actionid' => $actionids]);

		return ['actionids' => $actionids];
	}

	/**
	 * Validate operation, recovery operation, acknowledge operations.
	 *
	 * @param array $operations		Operation data array.
	 *
	 * @return bool
	 */
	public function validateOperationsIntegrity($operations) {
		$operations = zbx_toArray($operations);

		$all_groupids = [];
		$all_hostids = [];
		$all_templateids = [];
		$all_userids = [];
		$all_usrgrpids = [];
		$all_mediatypeids = [];

		$valid_operationtypes = [
			ACTION_OPERATION => [
				EVENT_SOURCE_TRIGGERS => [OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND],
				EVENT_SOURCE_DISCOVERY => [
					OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND, OPERATION_TYPE_GROUP_ADD,
					OPERATION_TYPE_GROUP_REMOVE, OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_TEMPLATE_REMOVE,
					OPERATION_TYPE_HOST_ADD, OPERATION_TYPE_HOST_REMOVE, OPERATION_TYPE_HOST_ENABLE,
					OPERATION_TYPE_HOST_DISABLE, OPERATION_TYPE_HOST_INVENTORY
				],
				EVENT_SOURCE_AUTO_REGISTRATION => [
					OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND, OPERATION_TYPE_GROUP_ADD,
					OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_HOST_ADD, OPERATION_TYPE_HOST_DISABLE,
					OPERATION_TYPE_HOST_INVENTORY
				],
				EVENT_SOURCE_INTERNAL => [OPERATION_TYPE_MESSAGE]
			],
			ACTION_RECOVERY_OPERATION => [
				EVENT_SOURCE_TRIGGERS => [OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND,
					OPERATION_TYPE_RECOVERY_MESSAGE
				],
				EVENT_SOURCE_INTERNAL => [OPERATION_TYPE_MESSAGE, OPERATION_TYPE_RECOVERY_MESSAGE]
			],
			ACTION_ACKNOWLEDGE_OPERATION => [
				EVENT_SOURCE_TRIGGERS => [OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND, OPERATION_TYPE_ACK_MESSAGE]
			]
		];

		$required_fields = ['eventsource', 'recovery', 'operationtype'];

		$default_msg_validator = new CLimitedSetValidator([
			'values' => [0, 1]
		]);

		foreach ($operations as $operation) {
			foreach ($required_fields as $field) {
				if (!array_key_exists($field, $operation)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Field "%1$s" is mandatory.', $field));
				}
			}
			$eventsource = $operation['eventsource'];
			$recovery = $operation['recovery'];
			$operationtype = $operation['operationtype'];

			if ($recovery == ACTION_OPERATION) {
				if ((array_key_exists('esc_step_from', $operation) || array_key_exists('esc_step_to', $operation))
						&& (!array_key_exists('esc_step_from', $operation)
							|| !array_key_exists('esc_step_to', $operation))) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('esc_step_from and esc_step_to must be set together.'));
				}

				if (array_key_exists('esc_step_from', $operation) && array_key_exists('esc_step_to', $operation)) {
					if ($operation['esc_step_from'] < 1 || $operation['esc_step_to'] < 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Incorrect action operation escalation step values.')
						);
					}

					if ($operation['esc_step_from'] > $operation['esc_step_to'] && $operation['esc_step_to'] != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Incorrect action operation escalation step values.')
						);
					}
				}

				if (array_key_exists('esc_period', $operation)
						&& !validateTimeUnit($operation['esc_period'], SEC_PER_MIN, SEC_PER_WEEK, true, $error,
							['usermacros' => true])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'esc_period', $error)
					);
				}
			}

			if (!array_key_exists($eventsource, $valid_operationtypes[$recovery])
					|| !in_array($operationtype, $valid_operationtypes[$recovery][$eventsource])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect action operation type "%1$s" for event source "%2$s".', $operationtype, $eventsource)
				);
			}

			switch ($operationtype) {
				case OPERATION_TYPE_MESSAGE:
					$userids = array_key_exists('opmessage_usr', $operation)
						? zbx_objectValues($operation['opmessage_usr'], 'userid')
						: [];

					$usrgrpids = array_key_exists('opmessage_grp', $operation)
						? zbx_objectValues($operation['opmessage_grp'], 'usrgrpid')
						: [];

					if (!$userids && !$usrgrpids) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No recipients for action operation message.'));
					}

					$all_userids = array_merge($all_userids, $userids);
					$all_usrgrpids = array_merge($all_usrgrpids, $usrgrpids);
					// falls through
				case OPERATION_TYPE_ACK_MESSAGE:
					$message = array_key_exists('opmessage', $operation) ? $operation['opmessage'] : [];

					if (array_key_exists('mediatypeid', $message) && $message['mediatypeid']) {
						$all_mediatypeids[$message['mediatypeid']] = true;
					}

					if (array_key_exists('default_msg', $message)
							&& (!$default_msg_validator->validate($message['default_msg']))) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value "%1$s" for "%2$s" field: must be between %3$s and %4$s.',
							$message['default_msg'], 'default_msg', 0, 1
						));
					}
					break;
				case OPERATION_TYPE_COMMAND:
					if (!isset($operation['opcommand']['type'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No command type specified for action operation.'));
					}

					if ((!isset($operation['opcommand']['command'])
							|| zbx_empty(trim($operation['opcommand']['command'])))
							&& $operation['opcommand']['type'] != ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No command specified for action operation.'));
					}

					switch ($operation['opcommand']['type']) {
						case ZBX_SCRIPT_TYPE_IPMI:
							break;
						case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
							if (!isset($operation['opcommand']['execute_on'])) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('No execution target specified for action operation command "%s".',
										$operation['opcommand']['command']
									)
								);
							}
							break;
						case ZBX_SCRIPT_TYPE_SSH:
							if (!isset($operation['opcommand']['authtype'])
									|| zbx_empty($operation['opcommand']['authtype'])) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('No authentication type specified for action operation command "%s".',
										$operation['opcommand']['command']
									)
								);
							}

							if (!array_key_exists('username', $operation['opcommand'])
									|| !is_string($operation['opcommand']['username'])
									|| $operation['opcommand']['username'] == '') {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('No authentication user name specified for action operation command "%s".',
										$operation['opcommand']['command']
									)
								);
							}

							if ($operation['opcommand']['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
								if (!isset($operation['opcommand']['publickey'])
										|| zbx_empty($operation['opcommand']['publickey'])) {
									self::exception(ZBX_API_ERROR_PARAMETERS,
										_s('No public key file specified for action operation command "%s".',
											$operation['opcommand']['command']
										)
									);
								}
								if (!isset($operation['opcommand']['privatekey'])
										|| zbx_empty($operation['opcommand']['privatekey'])) {
									self::exception(ZBX_API_ERROR_PARAMETERS,
										_s('No private key file specified for action operation command "%s".',
											$operation['opcommand']['command']
										)
									);
								}
							}
							elseif ($operation['opcommand']['authtype'] != ITEM_AUTHTYPE_PASSWORD) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value "%1$s" for "%2$s" field.',
									$operation['opcommand']['authtype'], 'authtype'
								));
							}
							break;
						case ZBX_SCRIPT_TYPE_TELNET:
							if (!array_key_exists('username', $operation['opcommand'])
									|| !is_string($operation['opcommand']['username'])
									|| $operation['opcommand']['username'] == '') {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('No authentication user name specified for action operation command "%s".',
										$operation['opcommand']['command']
									)
								);
							}
							break;
						case ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT:
							if (!isset($operation['opcommand']['scriptid'])
									|| zbx_empty($operation['opcommand']['scriptid'])) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_('No script specified for action operation command.')
								);
							}
							$scripts = API::Script()->get([
								'output' => ['scriptid','name'],
								'scriptids' => $operation['opcommand']['scriptid'],
								'preservekeys' => true
							]);
							if (!isset($scripts[$operation['opcommand']['scriptid']])) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _(
									'Specified script does not exist or you do not have rights on it for action operation command.'
								));
							}
							break;
						default:
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation command type.'));
					}

					if (isset($operation['opcommand']['port']) && !zbx_empty($operation['opcommand']['port'])) {
						if (zbx_ctype_digit($operation['opcommand']['port'])) {
							if ($operation['opcommand']['port'] > 65535 || $operation['opcommand']['port'] < 1) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('Incorrect action operation port "%s".', $operation['opcommand']['port'])
								);
							}
						}
						else {
							$user_macro_parser = new CUserMacroParser();

							if ($user_macro_parser->parse($operation['opcommand']['port']) != CParser::PARSE_SUCCESS) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('Incorrect action operation port "%s".', $operation['opcommand']['port'])
								);
							}
						}
					}

					$groupids = [];
					if (array_key_exists('opcommand_grp', $operation)) {
						$groupids = zbx_objectValues($operation['opcommand_grp'], 'groupid');
					}

					$hostids = [];
					$withoutCurrent = true;
					if (array_key_exists('opcommand_hst', $operation)) {
						foreach ($operation['opcommand_hst'] as $hstCommand) {
							if (!is_array($hstCommand) || !array_key_exists('hostid', $hstCommand)) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('Incorrect value for field "%1$s": %2$s.', 'hostid', _('cannot be empty'))
								);
							}

							if ($hstCommand['hostid'] == 0) {
								$withoutCurrent = false;
							}
							else {
								$hostids[$hstCommand['hostid']] = $hstCommand['hostid'];
							}
						}
					}

					if (!$groupids && !$hostids && $withoutCurrent) {
						if ($operation['opcommand']['type'] == ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('You did not specify targets for action operation global script "%s".',
									$scripts[$operation['opcommand']['scriptid']]['name']
							));
						}
						else {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('You did not specify targets for action operation command "%s".',
									$operation['opcommand']['command']
							));
						}
					}

					$all_hostids = array_merge($all_hostids, $hostids);
					$all_groupids = array_merge($all_groupids, $groupids);
					break;
				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					$groupids = array_key_exists('opgroup', $operation)
						? zbx_objectValues($operation['opgroup'], 'groupid')
						: [];

					if (!$groupids) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Operation has no group to operate.'));
					}

					$all_groupids = array_merge($all_groupids, $groupids);
					break;
				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					$templateids = isset($operation['optemplate'])
						? zbx_objectValues($operation['optemplate'], 'templateid')
						: [];

					if (!$templateids) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Operation has no template to operate.'));
					}

					$all_templateids = array_merge($all_templateids, $templateids);
					break;
				case OPERATION_TYPE_HOST_ADD:
				case OPERATION_TYPE_HOST_REMOVE:
				case OPERATION_TYPE_HOST_ENABLE:
				case OPERATION_TYPE_HOST_DISABLE:
					break;

				case OPERATION_TYPE_HOST_INVENTORY:
					if (!array_key_exists('opinventory', $operation)
							|| !array_key_exists('inventory_mode', $operation['opinventory'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('No inventory mode specified for action operation.')
						);
					}
					if ($operation['opinventory']['inventory_mode'] != HOST_INVENTORY_MANUAL
							&& $operation['opinventory']['inventory_mode'] != HOST_INVENTORY_AUTOMATIC) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect inventory mode in action operation.'));
					}
					break;
			}
		}

		$this->checkMediatypesExists($all_mediatypeids, _s('Incorrect value for field "%1$s": %2$s.', 'mediatypeid',
			_('No permissions to referred object or it does not exist!')
		));
		$this->checkHostGroupsPermissions($all_groupids, _(
			'Incorrect action operation host group. Host group does not exist or you have no access to this host group.'
		));
		$this->checkHostsPermissions($all_hostids,
			_('Incorrect action operation host. Host does not exist or you have no access to this host.')
		);
		$this->checkTemplatesPermissions($all_templateids,
			_('Incorrect action operation template. Template does not exist or you have no access to this template.')
		);
		$this->checkUsersPermissions($all_userids,
			_('Incorrect action operation user. User does not exist or you have no access to this user.')
		);
		$this->checkUserGroupsPermissions($all_usrgrpids, _(
			'Incorrect action operation user group. User group does not exist or you have no access to this user group.'
		));

		return true;
	}

	/**
	 * Validate operation conditions.
	 *
	 * @static
	 * @param $conditions
	 * @return bool
	 */
	public static function validateOperationConditions($conditions) {
		$conditions = zbx_toArray($conditions);
		$ackStatuses = [
			EVENT_ACKNOWLEDGED => 1,
			EVENT_NOT_ACKNOWLEDGED => 1
		];

		foreach ($conditions as $condition) {
			switch ($condition['conditiontype']) {
				case CONDITION_TYPE_EVENT_ACKNOWLEDGED:
					if (!isset($ackStatuses[$condition['value']])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation condition acknowledge type.'));
					}
					break;

				default:
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation condition type.'));
					break;
			}
		}

		return true;
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

		// Acknowledge operations data.
		if ($options['selectAcknowledgeOperations'] !== null
				&& $options['selectAcknowledgeOperations'] != API_OUTPUT_COUNT) {
			$ack_operations = API::getApiService()->select('operations', [
				'output' => $this->outputExtend($options['selectAcknowledgeOperations'],
					['operationid', 'actionid', 'operationtype']
				),
				'filter' => ['actionid' => $actionIds, 'recovery' => ACTION_ACKNOWLEDGE_OPERATION],
				'preservekeys' => true
			]);

			foreach ($result as &$action) {
				$action['acknowledgeOperations'] = [];
			}

			$ack_operations = $this->getAcknowledgeOperations($ack_operations, $options['selectAcknowledgeOperations']);

			foreach ($ack_operations as $ack_operation) {
				$actionid = $ack_operation['actionid'];
				unset($ack_operation['actionid']);
				$result[$actionid]['acknowledgeOperations'][] = $ack_operation;
			}
		}

		// adding operations
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
							' WHERE '.dbConditionInt('operationid', $opmessage)
					);
					while ($db_opmessage = DBfetch($db_opmessages)) {
						$operations[$db_opmessage['operationid']]['opmessage'] = $db_opmessage;
					}
				}

				if ($this->outputIsRequested('opmessage_grp', $options['selectOperations'])) {
					foreach ($opmessage as $operationId) {
						$operations[$operationId]['opmessage_grp'] = [];
					}

					$db_opmessage_grp = DBselect(
						'SELECT og.operationid,og.usrgrpid'.
							' FROM opmessage_grp og'.
							' WHERE '.dbConditionInt('operationid', $opmessage)
					);
					while ($opmessage_grp = DBfetch($db_opmessage_grp)) {
						$operations[$opmessage_grp['operationid']]['opmessage_grp'][] = $opmessage_grp;
					}
				}

				if ($this->outputIsRequested('opmessage_usr', $options['selectOperations'])) {
					foreach ($opmessage as $operationId) {
						$operations[$operationId]['opmessage_usr'] = [];
					}

					$db_opmessage_usr = DBselect(
						'SELECT ou.operationid,ou.userid'.
							' FROM opmessage_usr ou'.
							' WHERE '.dbConditionInt('operationid', $opmessage)
					);
					while ($opmessage_usr = DBfetch($db_opmessage_usr)) {
						$operations[$opmessage_usr['operationid']]['opmessage_usr'][] = $opmessage_usr;
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
						'SELECT o.*'.
							' FROM opcommand o'.
							' WHERE '.dbConditionInt('operationid', $opcommand)
					);
					while ($db_opcommand = DBfetch($db_opcommands)) {
						$operations[$db_opcommand['operationid']]['opcommand'] = $db_opcommand;
					}
				}

				if ($this->outputIsRequested('opcommand_hst', $options['selectOperations'])) {
					foreach ($opcommand as $operationId) {
						$operations[$operationId]['opcommand_hst'] = [];
					}

					$db_opcommand_hst = DBselect(
						'SELECT oh.opcommand_hstid,oh.operationid,oh.hostid'.
							' FROM opcommand_hst oh'.
							' WHERE '.dbConditionInt('operationid', $opcommand)
					);
					while ($opcommand_hst = DBfetch($db_opcommand_hst)) {
						$operations[$opcommand_hst['operationid']]['opcommand_hst'][] = $opcommand_hst;
					}
				}

				if ($this->outputIsRequested('opcommand_grp', $options['selectOperations'])) {
					foreach ($opcommand as $operationId) {
						$operations[$operationId]['opcommand_grp'] = [];
					}

					$db_opcommand_grp = DBselect(
						'SELECT og.opcommand_grpid,og.operationid,og.groupid'.
							' FROM opcommand_grp og'.
							' WHERE '.dbConditionInt('operationid', $opcommand)
					);
					while ($opcommand_grp = DBfetch($db_opcommand_grp)) {
						$operations[$opcommand_grp['operationid']]['opcommand_grp'][] = $opcommand_grp;
					}
				}
			}

			// get OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE data
			if ($opgroup) {
				if ($this->outputIsRequested('opgroup', $options['selectOperations'])) {
					foreach ($opgroup as $operationId) {
						$operations[$operationId]['opgroup'] = [];
					}

					$db_opgroup = DBselect(
						'SELECT o.operationid,o.groupid'.
							' FROM opgroup o'.
							' WHERE '.dbConditionInt('operationid', $opgroup)
					);
					while ($opgroup = DBfetch($db_opgroup)) {
						$operations[$opgroup['operationid']]['opgroup'][] = $opgroup;
					}
				}
			}

			// get OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_TEMPLATE_REMOVE data
			if ($optemplate) {
				if ($this->outputIsRequested('optemplate', $options['selectOperations'])) {
					foreach ($optemplate as $operationId) {
						$operations[$operationId]['optemplate'] = [];
					}

					$db_optemplate = DBselect(
						'SELECT o.operationid,o.templateid'.
							' FROM optemplate o'.
							' WHERE '.dbConditionInt('operationid', $optemplate)
					);
					while ($optemplate = DBfetch($db_optemplate)) {
						$operations[$optemplate['operationid']]['optemplate'][] = $optemplate;
					}
				}
			}

			// get OPERATION_TYPE_HOST_INVENTORY data
			if ($opinventory) {
				if ($this->outputIsRequested('opinventory', $options['selectOperations'])) {
					foreach ($opinventory as $operationId) {
						$operations[$operationId]['opinventory'] = [];
					}

					$db_opinventory = DBselect(
						'SELECT o.operationid,o.inventory_mode'.
							' FROM opinventory o'.
							' WHERE '.dbConditionInt('operationid', $opinventory)
					);
					while ($opinventory = DBfetch($db_opinventory)) {
						$operations[$opinventory['operationid']]['opinventory'] = $opinventory;
					}
				}
			}

			$operations = $this->unsetExtraFields($operations, ['operationid', 'actionid' ,'operationtype'],
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
							' WHERE '.dbConditionInt('operationid', $opmessage)
					);
					while ($db_opmessage = DBfetch($db_opmessages)) {
						$recovery_operations[$db_opmessage['operationid']]['opmessage'] = $db_opmessage;
					}
				}

				if ($this->outputIsRequested('opmessage_grp', $options['selectRecoveryOperations'])) {
					foreach ($opmessage as $recovery_operationid) {
						$recovery_operations[$recovery_operationid]['opmessage_grp'] = [];
					}

					$db_opmessage_grp = DBselect(
						'SELECT og.operationid,og.usrgrpid'.
							' FROM opmessage_grp og'.
							' WHERE '.dbConditionInt('operationid', $opmessage)
					);
					while ($opmessage_grp = DBfetch($db_opmessage_grp)) {
						$recovery_operations[$opmessage_grp['operationid']]['opmessage_grp'][] = $opmessage_grp;
					}
				}

				if ($this->outputIsRequested('opmessage_usr', $options['selectRecoveryOperations'])) {
					foreach ($opmessage as $recovery_operationid) {
						$recovery_operations[$recovery_operationid]['opmessage_usr'] = [];
					}

					$db_opmessage_usr = DBselect(
						'SELECT ou.operationid,ou.userid'.
							' FROM opmessage_usr ou'.
							' WHERE '.dbConditionInt('operationid', $opmessage)
					);
					while ($opmessage_usr = DBfetch($db_opmessage_usr)) {
						$recovery_operations[$opmessage_usr['operationid']]['opmessage_usr'][] = $opmessage_usr;
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
						'SELECT o.*'.
							' FROM opcommand o'.
							' WHERE '.dbConditionInt('operationid', $opcommand)
					);
					while ($db_opcommand = DBfetch($db_opcommands)) {
						$recovery_operations[$db_opcommand['operationid']]['opcommand'] = $db_opcommand;
					}
				}

				if ($this->outputIsRequested('opcommand_hst', $options['selectRecoveryOperations'])) {
					foreach ($opcommand as $recovery_operationid) {
						$recovery_operations[$recovery_operationid]['opcommand_hst'] = [];
					}

					$db_opcommand_hst = DBselect(
						'SELECT oh.opcommand_hstid,oh.operationid,oh.hostid'.
							' FROM opcommand_hst oh'.
							' WHERE '.dbConditionInt('operationid', $opcommand)
					);
					while ($opcommand_hst = DBfetch($db_opcommand_hst)) {
						$recovery_operations[$opcommand_hst['operationid']]['opcommand_hst'][] = $opcommand_hst;
					}
				}

				if ($this->outputIsRequested('opcommand_grp', $options['selectRecoveryOperations'])) {
					foreach ($opcommand as $recovery_operationid) {
						$recovery_operations[$recovery_operationid]['opcommand_grp'] = [];
					}

					$db_opcommand_grp = DBselect(
						'SELECT og.opcommand_grpid,og.operationid,og.groupid'.
							' FROM opcommand_grp og'.
							' WHERE '.dbConditionInt('operationid', $opcommand)
					);
					while ($opcommand_grp = DBfetch($db_opcommand_grp)) {
						$recovery_operations[$opcommand_grp['operationid']]['opcommand_grp'][] = $opcommand_grp;
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
							' WHERE '.dbConditionInt('operationid', $op_recovery_message)
					);
					while ($db_opmessage = DBfetch($db_opmessages)) {
						$recovery_operations[$db_opmessage['operationid']]['opmessage'] = $db_opmessage;
					}
				}
			}

			$recovery_operations = $this->unsetExtraFields($recovery_operations,
				['operationid', 'actionid' ,'operationtype'], $options['selectRecoveryOperations']
			);
			$result = $relationMap->mapMany($result, $recovery_operations, 'recoveryOperations');
		}

		return $result;
	}

	/**
	 * Returns array of acknowledge operations according to requested options.
	 *
	 * @param array $ack_operations		Array of acknowledge operation with key set to operationid.
	 * @param array $ack_options		Array of acknowledge operation options from request.
	 *
	 * @return array
	 */
	protected function getAcknowledgeOperations($ack_operations, $ack_options) {
		$opmessages = [];
		$nonack_messages = [];
		$opcommands = [];

		foreach ($ack_operations as $ack_operationid => &$ack_operation) {
			unset($ack_operation['esc_period'], $ack_operation['esc_step_from'], $ack_operation['esc_step_to']);

			switch ($ack_operation['operationtype']) {
				case OPERATION_TYPE_ACK_MESSAGE:
					$opmessages[] = $ack_operationid;
					break;
				case OPERATION_TYPE_MESSAGE:
					$opmessages[] = $ack_operationid;
					$nonack_messages[] = $ack_operationid;
					break;
				case OPERATION_TYPE_COMMAND:
					$opcommands[] = $ack_operationid;
					break;
			}
		}
		unset($ack_operation);

		if ($opmessages) {
			if ($this->outputIsRequested('opmessage', $ack_options)) {
				foreach ($opmessages as $operationid) {
					$ack_operations[$operationid]['opmessage'] = [];
				}

				$messages = DB::select('opmessage', [
					'output' => ['operationid', 'default_msg', 'subject', 'message', 'mediatypeid'],
					'filter' => ['operationid' => $opmessages]
				]);

				foreach ($messages as $message) {
					$operationid = $message['operationid'];
					unset($message['operationid']);
					$ack_operations[$operationid]['opmessage'] = $message;
				}
			}

			if ($this->outputIsRequested('opmessage_grp', $ack_options) && $nonack_messages) {
				foreach ($nonack_messages as $operationid) {
					$ack_operations[$operationid]['opmessage_grp'] = [];
				}

				$messages_groups = DB::select('opmessage_grp', [
					'output' => ['operationid', 'usrgrpid'],
					'filter' => ['operationid' => $nonack_messages]
				]);

				foreach ($messages_groups as $messages_group) {
					$operationid = $messages_group['operationid'];
					unset($messages_group['operationid']);
					$ack_operations[$operationid]['opmessage_grp'][] = $messages_group;
				}
			}

			if ($this->outputIsRequested('opmessage_usr', $ack_options) && $nonack_messages) {
				foreach ($nonack_messages as $operationid) {
					$ack_operations[$operationid]['opmessage_usr'] = [];
				}

				$messages_users = DB::select('opmessage_usr', [
					'output' => ['operationid', 'userid'],
					'filter' => ['operationid' => $nonack_messages]
				]);

				foreach ($messages_users as $messages_user) {
					$operationid = $messages_user['operationid'];
					unset($messages_user['operationid']);
					$ack_operations[$operationid]['opmessage_usr'][] = $messages_user;
				}
			}
		}

		if ($opcommands) {
			if ($this->outputIsRequested('opcommand', $ack_options)) {
				foreach ($opcommands as $operationid) {
					$ack_operations[$operationid]['opcommand'] = [];
				}

				$commands = DB::select('opcommand', [
					'output' => ['operationid', 'type', 'scriptid', 'execute_on', 'port', 'authtype', 'username',
						'password', 'publickey', 'privatekey', 'command'
					],
					'filter' => ['operationid' => $opcommands]
				]);

				foreach ($commands as $command) {
					$operationid = $command['operationid'];
					unset($command['operationid']);
					$ack_operations[$operationid]['opcommand'] = $command;
				}
			}

			if ($this->outputIsRequested('opcommand_hst', $ack_options)) {
				foreach ($opcommands as $operationid) {
					$ack_operations[$operationid]['opcommand_hst'] = [];
				}

				$commands_history = DB::select('opcommand_hst', [
					'output' => ['opcommand_hstid', 'operationid', 'hostid'],
					'filter' => ['operationid' => $opcommands]
				]);

				foreach ($commands_history as $command_history) {
					$operationid = $command_history['operationid'];
					unset($command_history['operationid']);
					$ack_operations[$operationid]['opcommand_hst'][] = $command_history;
				}
			}

			if ($this->outputIsRequested('opcommand_grp', $ack_options)) {
				foreach ($opcommands as $operationid) {
					$ack_operations[$operationid]['opcommand_grp'] = [];
				}

				$commands_groups = DB::select('opcommand_grp', [
					'output' => ['opcommand_grpid', 'operationid', 'groupid'],
					'filter' => ['operationid' => $opcommands]
				]);

				foreach ($commands_groups as $command_group) {
					$operationid = $command_group['operationid'];
					unset($command_group['operationid']);
					$ack_operations[$operationid]['opcommand_grp'][] = $command_group;
				}
			}
		}

		$ack_operations = $this->unsetExtraFields($ack_operations, ['operationid', 'actionid' ,'operationtype'],
			$ack_options
		);
		return $ack_operations;
	}

	/**
	 * Returns the parameters for creating a discovery rule filter validator.
	 *
	 * @return array
	 */
	protected function getFilterSchema() {
		return [
			'validators' => [
				'evaltype' => new CLimitedSetValidator([
					'values' => [
						CONDITION_EVAL_TYPE_OR,
						CONDITION_EVAL_TYPE_AND,
						CONDITION_EVAL_TYPE_AND_OR,
						CONDITION_EVAL_TYPE_EXPRESSION
					],
					'messageInvalid' => _('Incorrect type of calculation for action "%1$s".')
				]),
				'formula' => new CStringValidator([
					'empty' => true
				]),
				'conditions' => new CCollectionValidator([
					'empty' => true,
					'messageInvalid' => _('Incorrect conditions for action "%1$s".')
				])
			],
			'postValidators' => [
				new CConditionValidator([
					'messageInvalidFormula' => _('Incorrect custom expression "%2$s" for action "%1$s": %3$s.'),
					'messageMissingCondition' => _('Condition "%2$s" used in formula "%3$s" for action "%1$s" is not defined.'),
					'messageUnusedCondition' => _('Condition "%2$s" is not used in formula "%3$s" for action "%1$s".'),
					'messageAndWithSeveralTriggers' => _('Comparing several triggers with "and" is not allowed.')
				])
			],
			'required' => ['evaltype', 'conditions'],
			'messageRequired' => _('No "%2$s" given for the filter of action "%1$s".'),
			'messageUnsupported' => _('Unsupported parameter "%2$s" for the filter of action "%1$s".')
		];
	}

	/**
	 * Returns the parameters for creating a action filter condition validator.
	 *
	 * @return array
	 */
	protected function getFilterConditionSchema() {
		$conditionTypes = [
			CONDITION_TYPE_HOST_GROUP, CONDITION_TYPE_HOST, CONDITION_TYPE_TRIGGER, CONDITION_TYPE_TRIGGER_NAME,
			CONDITION_TYPE_TRIGGER_SEVERITY, CONDITION_TYPE_TIME_PERIOD,
			CONDITION_TYPE_DHOST_IP, CONDITION_TYPE_DSERVICE_TYPE, CONDITION_TYPE_DSERVICE_PORT,
			CONDITION_TYPE_DSTATUS, CONDITION_TYPE_DUPTIME, CONDITION_TYPE_DVALUE, CONDITION_TYPE_TEMPLATE,
			CONDITION_TYPE_EVENT_ACKNOWLEDGED, CONDITION_TYPE_APPLICATION, CONDITION_TYPE_MAINTENANCE,
			CONDITION_TYPE_DRULE, CONDITION_TYPE_DCHECK, CONDITION_TYPE_PROXY, CONDITION_TYPE_DOBJECT,
			CONDITION_TYPE_HOST_NAME, CONDITION_TYPE_EVENT_TYPE, CONDITION_TYPE_HOST_METADATA, CONDITION_TYPE_EVENT_TAG,
			CONDITION_TYPE_EVENT_TAG_VALUE
		];

		$operators = [
			CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
			CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_IN, CONDITION_OPERATOR_MORE_EQUAL,
			CONDITION_OPERATOR_LESS_EQUAL, CONDITION_OPERATOR_NOT_IN
		];

		return [
			'validators' => [
				'conditiontype' => new CLimitedSetValidator([
					'values' => $conditionTypes,
					'messageInvalid' => _('Incorrect filter condition type for action "%1$s".')
				]) ,
				'value' => new CStringValidator([
					'empty' => true
				]),
				'value2' => new CStringValidator([
					'empty' => true
				]),
				'formulaid' => new CStringValidator([
					'regex' => '/[A-Z]+/',
					'messageEmpty' => _('Empty filter condition formula ID for action "%1$s".'),
					'messageRegex' => _('Incorrect filter condition formula ID for action "%1$s".')
				]),
				'operator' => new CLimitedSetValidator([
					'values' => $operators,
					'messageInvalid' => _('Incorrect filter condition operator for action "%1$s".')
				])
			],
			'required' => ['conditiontype', 'value'],
			'postValidators' => [
				new CActionCondValidator()
			],
			'messageRequired' => _('No "%2$s" given for a filter condition of action "%1$s".'),
			'messageUnsupported' => _('Unsupported parameter "%2$s" for a filter condition of action "%1$s".')
		];
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
	 * Converts a formula with letters to a formula with IDs and updates it.
	 *
	 * @param string 	$actionId
	 * @param string 	$formulaWithLetters		formula with letters
	 * @param array 	$conditions
	 */
	protected function updateFormula($actionId, $formulaWithLetters, array $conditions) {
		$formulaIdToConditionId = [];

		foreach ($conditions as $condition) {
			$formulaIdToConditionId[$condition['formulaid']] = $condition['conditionid'];
		}
		$formula = CConditionHelper::replaceLetterIds($formulaWithLetters, $formulaIdToConditionId);

		DB::updateByPk('actions', $actionId, ['formula' => $formula]);
	}

	/**
	 * Validate input given to action.create API call.
	 *
	 * @param $actions
	 */
	protected function validateCreate($actions) {
		$actionDbFields = [
			'name'        => null,
			'eventsource' => null
		];

		$duplicates = [];

		foreach ($actions as $action) {
			if (!check_db_fields($actionDbFields, $action)) {
				self::exception(
					ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect parameter for action "%1$s".', $action['name'])
				);
			}

			if (isset($duplicates[$action['name']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%1$s" already exists.', $action['name']));
			}
			else {
				$duplicates[$action['name']] = $action['name'];
			}

			if (array_key_exists('esc_period', $action) && $action['eventsource'] == EVENT_SOURCE_TRIGGERS) {
				self::validateStepDuration($action['esc_period']);
			}
		}

		$dbActionsWithSameName = $this->get([
			'filter' => ['name' => $duplicates],
			'output' => API_OUTPUT_EXTEND,
			'editable' => true,
			'nopermissions' => true
		]);
		if ($dbActionsWithSameName) {
			$dbActionWithSameName = reset($dbActionsWithSameName);
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%1$s" already exists.', $dbActionWithSameName['name']));
		}

		$filterValidator = new CSchemaValidator($this->getFilterSchema());
		$filterConditionValidator = new CSchemaValidator($this->getFilterConditionSchema());
		$maintenance_mode_validator = new CLimitedSetValidator([
			'values' => [ACTION_MAINTENANCE_MODE_NORMAL, ACTION_MAINTENANCE_MODE_PAUSE]
		]);

		$conditionsToValidate = [];
		$operations_to_validate = [];

		// Validate "filter" sections and "conditions" in them, ensure that "operations" section
		// is present and is not empty. Also collect conditions and operations for more validation.
		foreach ($actions as $action) {
			if ($action['eventsource'] != EVENT_SOURCE_TRIGGERS) {
				$this->checkNoParameters($action, ['maintenance_mode'], _('Cannot set "%1$s" for action "%2$s".'),
					$action['name']
				);
			}
			elseif (array_key_exists('maintenance_mode', $action)
					&& !$maintenance_mode_validator->validate($action['maintenance_mode'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value "%1$s" for "%2$s" field.', $action['maintenance_mode'], 'maintenance_mode')
				);
			}

			if (isset($action['filter'])) {
				$filterValidator->setObjectName($action['name']);
				$this->checkValidator($action['filter'], $filterValidator);
				$filterConditionValidator->setObjectName($action['name']);

				foreach ($action['filter']['conditions'] as $condition) {
					if ($condition['conditiontype'] == CONDITION_TYPE_EVENT_TAG_VALUE &&
							!array_key_exists('value2', $condition)) {
						self::exception(
							ZBX_API_ERROR_PARAMETERS,
							_s('No "%2$s" given for a filter condition of action "%1$s".', $action['name'], 'value2')
						);
					}

					$this->checkValidator($condition, $filterConditionValidator);
					$conditionsToValidate[] = $condition;
				}
			}

			if ((!array_key_exists('operations', $action) || !$action['operations'])
					&& (!array_key_exists('recovery_operations', $action) || !$action['recovery_operations'])
					&& (!array_key_exists('acknowledge_operations', $action) || !$action['acknowledge_operations'])) {
				self::exception(
					ZBX_API_ERROR_PARAMETERS,
					_s('Action "%1$s" no operations defined.', $action['name'])
				);
			}
			elseif (array_key_exists('operations', $action) && $action['operations']) {
				foreach ($action['operations'] as $operation) {
					$operation['recovery'] = ACTION_OPERATION;
					$operation['eventsource'] = $action['eventsource'];
					$operations_to_validate[] = $operation;
				}
			}

			if (array_key_exists('recovery_operations', $action) && $action['recovery_operations']) {
				foreach ($action['recovery_operations'] as $recovery_operation) {
					$recovery_operation['recovery'] = ACTION_RECOVERY_OPERATION;
					$recovery_operation['eventsource'] = $action['eventsource'];
					$operations_to_validate[] = $recovery_operation;
				}
			}

			if (array_key_exists('acknowledge_operations', $action) && $action['acknowledge_operations']) {
				foreach ($action['acknowledge_operations'] as $operation) {
					$operation['recovery'] = ACTION_ACKNOWLEDGE_OPERATION;
					$operation['eventsource'] = $action['eventsource'];
					$operations_to_validate[] = $operation;
				}
			}
		}

		// Validate conditions and operations in regard to whats in database now.
		if ($conditionsToValidate) {
			$this->validateConditionsPermissions($conditionsToValidate);
		}
		if ($operations_to_validate) {
			$this->validateOperationsIntegrity($operations_to_validate);
		}
	}

	/**
	 * Validate default step duration and operation step duration values.
	 *
	 * @param string $esc_period  Step duration.
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function validateStepDuration($esc_period) {
		if (!validateTimeUnit($esc_period, SEC_PER_MIN, SEC_PER_WEEK, false, $error, ['usermacros' => true])) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', 'esc_period', $error)
			);
		}
	}

	/**
	 * Validate input given to action.update API call.
	 *
	 * @param array $actions
	 * @param array $db_actions
	 */
	protected function validateUpdate($actions, $db_actions) {
		foreach ($actions as $action) {
			if (isset($action['actionid']) && !isset($db_actions[$action['actionid']])) {
				self::exception(
					ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
		$actions = zbx_toHash($actions, 'actionid');

		$maintenance_mode_validator = new CLimitedSetValidator([
			'values' => [ACTION_MAINTENANCE_MODE_NORMAL, ACTION_MAINTENANCE_MODE_PAUSE]
		]);

		// check fields
		$duplicates = [];

		foreach ($actions as $action) {
			$actionName = isset($action['name']) ? $action['name'] : $db_actions[$action['actionid']]['name'];

			if (!check_db_fields(['actionid' => null], $action)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Incorrect parameters for action update method "%1$s".', $actionName
				));
			}

			// check if user changed esc_period for trigger eventsource
			if (array_key_exists('esc_period', $action)
					&& $db_actions[$action['actionid']]['eventsource'] == EVENT_SOURCE_TRIGGERS) {
				self::validateStepDuration($action['esc_period']);
			}

			$this->checkNoParameters(
				$action,
				['eventsource'],
				_('Cannot update "%1$s" for action "%2$s".'),
				$actionName
			);

			if ($db_actions[$action['actionid']]['eventsource'] != EVENT_SOURCE_TRIGGERS) {
				$this->checkNoParameters($action, ['maintenance_mode'], _('Cannot update "%1$s" for action "%2$s".'),
					$actionName
				);
			}
			elseif (array_key_exists('maintenance_mode', $action)
					&& !$maintenance_mode_validator->validate($action['maintenance_mode'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value "%1$s" for "%2$s" field.', $action['maintenance_mode'], 'maintenance_mode')
				);
			}

			if (!isset($action['name'])) {
				continue;
			}

			if (isset($duplicates[$action['name']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%1$s" already exists.', $action['name']));
			}
			else {
				$duplicates[$action['name']] = $action['name'];
			}
		}

		// Unset accidentally passed in "evaltype" and "formula" fields.
		foreach ($actions as &$action) {
			unset($action['evaltype'], $action['formula']);
		}
		unset($action);

		$filterValidator = new CSchemaValidator($this->getFilterSchema());

		$filterConditionValidator = new CSchemaValidator($this->getFilterConditionSchema());

		$operations_to_validate = [];
		$conditionsToValidate = [];

		foreach ($actions as $actionId => $action) {
			$db_action = $db_actions[$actionId];

			if (isset($action['name'])) {
				$actionName = $action['name'];

				$actionExists = $this->get([
					'filter' => ['name' => $actionName],
					'output' => ['actionid'],
					'editable' => true,
					'nopermissions' => true,
					'preservekeys' => true
				]);
				if (($actionExists = reset($actionExists))
					&& (bccomp($actionExists['actionid'], $actionId) != 0)
				) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%1$s" already exists.', $actionName));
				}
			}
			else {
				$actionName = $db_action['name'];
			}

			if (isset($action['filter'])) {
				$actionFilter = $action['filter'];

				$filterValidator->setObjectName($actionName);
				$filterConditionValidator->setObjectName($actionName);

				$this->checkValidator($actionFilter, $filterValidator);

				foreach ($actionFilter['conditions'] as $condition) {
					if ($condition['conditiontype'] == CONDITION_TYPE_EVENT_TAG_VALUE
							&& !array_key_exists('value2', $condition)) {
						self::exception(
							ZBX_API_ERROR_PARAMETERS,
							_s('No "%2$s" given for a filter condition of action "%1$s".', $actionName, 'value2')
						);
					}

					$this->checkValidator($condition, $filterConditionValidator);
					$conditionsToValidate[] = $condition;
				}
			}

			$operations_defined = array_key_exists('operations', $action)
				? (bool) $action['operations']
				: (bool) $db_action['operations'];
			$rcv_operations_defined = array_key_exists('recovery_operations', $action)
				? (bool) $action['recovery_operations']
				: (bool) $db_action['recoveryOperations'];
			$ack_operations_defined = array_key_exists('acknowledge_operations', $action)
				? (bool) $action['acknowledge_operations']
				: (bool) $db_action['acknowledgeOperations'];

			if (!$operations_defined && !$rcv_operations_defined && !$ack_operations_defined) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%1$s" no operations defined.', $actionName));
			}

			if (array_key_exists('operations', $action) && $action['operations']) {
				$db_operations = zbx_toHash($db_actions[$action['actionid']]['operations'], 'operationid');
				foreach ($action['operations'] as $operation) {
					if (!array_key_exists('operationid', $operation)
							|| array_key_exists($operation['operationid'], $db_operations)) {
						$operation['recovery'] = ACTION_OPERATION;
						$operation['eventsource'] = $db_action['eventsource'];
						$operations_to_validate[] = $operation;
					}
					else {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operationid.'));
					}
				}
			}

			// Recovery operations.
			if (array_key_exists('recovery_operations', $action) && $action['recovery_operations']) {
				$db_recovery_operations = zbx_toHash($db_actions[$action['actionid']]['recoveryOperations'],
					'operationid'
				);
				foreach ($action['recovery_operations'] as $recovery_operation) {
					if (!array_key_exists('operationid', $recovery_operation)
							|| array_key_exists($recovery_operation['operationid'], $db_recovery_operations)) {
						$recovery_operation['recovery'] = ACTION_RECOVERY_OPERATION;
						$recovery_operation['eventsource'] = $db_action['eventsource'];
						$operations_to_validate[] = $recovery_operation;
					}
					else {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operationid.'));
					}
				}
			}

			if (array_key_exists('acknowledge_operations', $action) && $action['acknowledge_operations']) {
				$db_ack_operations = zbx_toHash($db_actions[$action['actionid']]['acknowledgeOperations'],
					'operationid'
				);
				foreach ($action['acknowledge_operations'] as $ack_operation) {
					if (!array_key_exists('operationid', $ack_operation)
							|| array_key_exists($ack_operation['operationid'], $db_ack_operations)) {
						$ack_operation['recovery'] = ACTION_ACKNOWLEDGE_OPERATION;
						$ack_operation['eventsource'] = $db_action['eventsource'];

						if (array_key_exists('operationid', $ack_operation)
								&& array_key_exists($ack_operation['operationid'], $db_ack_operations)) {
							$db_ack_operation = $db_ack_operations[$ack_operation['operationid']];
							$operation_type = array_key_exists('operationtype', $ack_operation)
								? $ack_operation['operationtype']
								: $db_ack_operation['operationtype'];

							// Field 'operationtype' is required.
							unset($db_ack_operation['operationtype']);

							if ($operation_type == OPERATION_TYPE_MESSAGE) {
								unset($db_ack_operation['opmessage_grp'], $db_ack_operation['opmessage_usr']);
							}
							elseif ($operation_type == OPERATION_TYPE_COMMAND) {
								unset($db_ack_operation['opcommand_grp'], $db_ack_operation['opcommand_hst']);
							}

							$ack_operation += $db_ack_operation;
						}

						$operations_to_validate[] = $ack_operation;
					}
					else {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect acknowledgement action operationid.'));
					}
				}
			}
		}

		if ($conditionsToValidate) {
			$this->validateConditionsPermissions($conditionsToValidate);
		}
		$this->validateOperationsIntegrity($operations_to_validate);
	}

	/**
	 * Check permissions to DB entities referenced by action conditions.
	 *
	 * @param array $conditions   conditions for which permissions to referenced DB entities will be checked
	 */
	protected function validateConditionsPermissions(array $conditions) {
		$hostGroupIdsAll = [];
		$templateIdsAll = [];
		$triggerIdsAll = [];
		$hostIdsAll = [];
		$discoveryRuleIdsAll = [];
		$discoveryCheckIdsAll = [];
		$proxyIdsAll = [];

		foreach ($conditions as $condition) {
			$conditionValue = $condition['value'];
			// validate condition values depending on condition type
			switch ($condition['conditiontype']) {
				case CONDITION_TYPE_HOST_GROUP:
					$hostGroupIdsAll[$conditionValue] = $conditionValue;
					break;

				case CONDITION_TYPE_TEMPLATE:
					$templateIdsAll[$conditionValue] = $conditionValue;
					break;

				case CONDITION_TYPE_TRIGGER:
					$triggerIdsAll[$conditionValue] = $conditionValue;
					break;

				case CONDITION_TYPE_HOST:
					$hostIdsAll[$conditionValue] = $conditionValue;
					break;

				case CONDITION_TYPE_DRULE:
					$discoveryRuleIdsAll[$conditionValue] = $conditionValue;
					break;

				case CONDITION_TYPE_DCHECK:
					$discoveryCheckIdsAll[$conditionValue] = $conditionValue;
					break;

				case CONDITION_TYPE_PROXY:
					$proxyIdsAll[$conditionValue] = $conditionValue;
					break;
			}
		}

		$this->checkHostGroupsPermissions($hostGroupIdsAll,
			_('Incorrect action condition host group. Host group does not exist or you have no access to it.')
		);
		$this->checkHostsPermissions($hostIdsAll,
			_('Incorrect action condition host. Host does not exist or you have no access to it.')
		);
		$this->checkTemplatesPermissions($templateIdsAll,
			_('Incorrect action condition template. Template does not exist or you have no access to it.')
		);
		$this->checkTriggersPermissions($triggerIdsAll,
			_('Incorrect action condition trigger. Trigger does not exist or you have no access to it.')
		);
		$this->checkDRulesPermissions($discoveryRuleIdsAll,
			_('Incorrect action condition discovery rule. Discovery rule does not exist or you have no access to it.')
		);
		$this->checkDChecksPermissions($discoveryCheckIdsAll,
			_('Incorrect action condition discovery check. Discovery check does not exist or you have no access to it.')
		);
		$this->checkProxiesPermissions($proxyIdsAll,
			_('Incorrect action condition proxy. Proxy does not exist or you have no access to it.')
		);
	}

	/**
	 * Checks if all given media types are valid.
	 *
	 * @param array  $mediatypeids  Array of media type ids where key is checked media type id.
	 * @param string $error         Error message to throw if invalid media type id was supplied.
	 *
	 * @throws APIException if invalid media types given.
	 */
	private function checkMediatypesExists(array $mediatypeids, $error) {
		if ($mediatypeids) {
			$count = API::MediaType()->get([
				'countOutput' => true,
				'mediatypeids' => array_keys($mediatypeids)
			]);

			if ($count != count($mediatypeids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}
		}
	}

	/**
	 * Checks if the current user has access to the given host groups.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given host groups
	 *
	 * @param array $groupids
	 * @param tring $error
	 */
	private function checkHostGroupsPermissions(array $groupids, $error) {
		if ($groupids) {
			$groupids = array_unique($groupids);

			$count = API::HostGroup()->get([
				'countOutput' => true,
				'groupids' => $groupids,
				'editable' => true
			]);

			if ($count != count($groupids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}
		}
	}

	/**
	 * Checks if the current user has access to the given hosts.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given hosts
	 *
	 * @param array $hostids
	 * @param tring $error
	 */
	private function checkHostsPermissions(array $hostids, $error) {
		if ($hostids) {
			$hostids = array_unique($hostids);

			$count = API::Host()->get([
				'countOutput' => true,
				'hostids' => $hostids,
				'editable' => true
			]);

			if ($count != count($hostids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}
		}
	}

	/**
	 * Checks if the current user has access to the given users.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given users
	 *
	 * @param array  $userids
	 * @param string $error
	 */
	protected function checkUsersPermissions(array $userids, $error) {
		if ($userids) {
			$userids = array_unique($userids);

			$count = API::User()->get([
				'countOutput' => true,
				'userids' => $userids
			]);

			if ($count != count($userids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}
		}
	}

	/**
	 * Checks if the current user has access to the given user groups.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given user groups
	 *
	 * @param array  $usrgrpids
	 * @param string $error
	 */
	protected function checkUserGroupsPermissions(array $usrgrpids, $error) {
		if ($usrgrpids) {
			$usrgrpids = array_unique($usrgrpids);

			$count = API::UserGroup()->get([
				'countOutput' => true,
				'usrgrpids' => $usrgrpids
			]);

			if ($count != count($usrgrpids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}
		}
	}

	/**
	 * Checks if the current user has access to the given templates.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given templates
	 *
	 * @param array  $templateids
	 * @param string $error
	 */
	protected function checkTemplatesPermissions(array $templateids, $error) {
		if ($templateids) {
			$templateids = array_unique($templateids);

			$count = API::Template()->get([
				'countOutput' => true,
				'templateids' => $templateids,
				'editable' => true
			]);

			if ($count != count($templateids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}
		}
	}

	/**
	 * Checks if the current user has access to the given triggers.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given triggers
	 *
	 * @param array  $triggerids
	 * @param string $error
	 */
	protected function checkTriggersPermissions(array $triggerids, $error) {
		if ($triggerids) {
			$triggerids = array_unique($triggerids);

			$count = API::Trigger()->get([
				'countOutput' => true,
				'triggerids' => $triggerids,
				'editable' => true
			]);

			if ($count != count($triggerids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}
		}
	}

	/**
	 * Checks if the current user has access to the given discovery rules.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given discovery rules
	 *
	 * @param array  $druleids
	 * @param string $error
	 */
	protected function checkDRulesPermissions(array $druleids, $error) {
		if ($druleids) {
			$druleids = array_unique($druleids);

			$count = API::DRule()->get([
				'countOutput' => true,
				'druleids' => $druleids,
				'editable' => true
			]);

			if ($count != count($druleids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}
		}
	}

	/**
	 * Checks if the current user has access to the given discovery checks.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given discovery checks
	 *
	 * @param array  $dcheckids
	 * @param string $error
	 */
	protected function checkDChecksPermissions(array $dcheckids, $error) {
		if ($dcheckids) {
			$dcheckids = array_unique($dcheckids);

			$count = API::DCheck()->get([
				'countOutput' => true,
				'dcheckids' => $dcheckids,
				'editable' => true
			]);

			if ($count != count($dcheckids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}
		}
	}

	/**
	 * Checks if the current user has access to the given proxies.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given proxies
	 *
	 * @param array  $proxyids
	 * @param string $error
	 */
	protected function checkProxiesPermissions(array $proxyids, $error) {
		if ($proxyids) {
			$proxyids = array_unique($proxyids);

			$count = API::Proxy()->get([
				'countOutput' => true,
				'proxyids' => $proxyids,
				'editable' => true
			]);

			if ($count != count($proxyids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}
		}
	}
}
