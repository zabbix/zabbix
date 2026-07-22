<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * Class containing methods for operations with maintenances.
 */
class CMaintenance extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN, 'action' => CRoleHelper::ACTIONS_EDIT_MAINTENANCE],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN, 'action' => CRoleHelper::ACTIONS_EDIT_MAINTENANCE],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN, 'action' => CRoleHelper::ACTIONS_EDIT_MAINTENANCE]
	];

	protected $tableName = 'maintenances';
	protected $tableAlias = 'm';
	protected $sortColumns = ['maintenanceid', 'name', 'maintenance_type', 'active_till', 'active_since'];

	/**
	 * Get maintenance data.
	 *
	 * @param array        $options
	 * @param array        $options['groupids']                Select maintenances by host group IDs.
	 * @param array        $options['hostids']                 Select maintenances by host IDs.
	 * @param array        $options['maintenanceids']          Select maintenances by maintenance IDs.
	 * @param array        $options['triggerids']              Select maintenances by trigger IDs.
	 * @param bool         $options['editable']                Select only maintenances with read-write permission.
	 * @param bool         $options['nopermissions']           Select maintenances without applying permission checks.
	 * @param array        $options['filter']                  Filter maintenances by exact field values.
	 * @param array        $options['search']                  Search maintenance fields by text patterns.
	 * @param bool         $options['searchByAny']             Match any search criterion instead of all criteria.
	 * @param bool         $options['startSearch']             Match search patterns only at the beginning of fields.
	 * @param bool         $options['excludeSearch']           Return maintenances that do not match search criteria.
	 * @param bool         $options['searchWildcardsEnabled'] Enable wildcards in search criteria.
	 * @param array|string $options['output']                  Maintenance fields to return.
	 * @param array|string $options['selectHostGroups']        Return a "hostgroups" property with host group data.
	 * @param array|string $options['selectHosts']             Return a "hosts" property with host data.
	 * @param array|string $options['selectTags']              Return a "tags" property with problem tag data.
	 * @param array|string $options['selectTimeperiods']       Return a "timeperiods" property with time period data.
	 * @param array|string $options['selectTriggers']          Return a "triggers" property with trigger data.
	 * @param array|string $options['selectEventNames']        Return an "event_names" property with event name conditions.
	 * @param bool         $options['countOutput']             Return the maintenance count instead of objects.
	 * @param bool         $options['groupCount']              Group the maintenance count.
	 * @param bool         $options['preservekeys']            Return maintenance IDs as array keys.
	 * @param array|string $options['sortfield']               Fields to sort by.
	 * @param array|string $options['sortorder']               Sort directions.
	 * @param int          $options['limit']                   Limit the number of returned maintenances.
	 *
	 * @return array|string
	 */
	public function get(array $options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['maintenance' => 'm.maintenanceid'],
			'from'		=> 'maintenances m',
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'					=> null,
			'hostids'					=> null,
			'maintenanceids'			=> null,
			'triggerids'				=> null,
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
			'selectHosts'				=> null,
			'selectTags'				=> null,
			'selectTimeperiods'			=> null,
			'selectTriggers'			=> null,
			'selectEventNames'			=> null,
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

			$permission_condition = $options['editable']
				? ' AND (p.hgsetid IS NULL OR p.permission < '.PERM_READ_WRITE.')'
				: ' AND p.hgsetid IS NULL';

			$sqlParts['where'][] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM maintenances_hosts mh'.
				' JOIN host_hgset hh ON mh.hostid=hh.hostid'.
				' LEFT JOIN permission p ON hh.hgsetid=p.hgsetid'.
					' AND p.ugsetid='.self::$userData['ugsetid'].
				' WHERE m.maintenanceid=mh.maintenanceid'.
					$permission_condition.
			')';

			$sqlParts['where'][] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM maintenance_triggers mt'.
				' JOIN functions f ON mt.triggerid=f.triggerid'.
				' JOIN items i ON f.itemid=i.itemid'.
				' JOIN host_hgset hh ON i.hostid=hh.hostid'.
				' LEFT JOIN permission p ON hh.hgsetid=p.hgsetid'.
					' AND p.ugsetid='.self::$userData['ugsetid'].
				' WHERE m.maintenanceid=mt.maintenanceid'.
					$permission_condition.
			')';

			$userGroups = getUserGroupsByUserId(self::$userData['userid']);
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$sqlParts['where'][] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM maintenances_groups mg'.
				' LEFT JOIN rights r ON mg.groupid=r.id'.
					' AND '.dbConditionId('r.groupid', $userGroups).
				' WHERE m.maintenanceid=mg.maintenanceid'.
				' GROUP by mg.groupid'.
				' HAVING MIN(r.permission) IS NULL'.
					' OR MIN(r.permission)='.PERM_DENY.
					' OR MAX(r.permission)<'.zbx_dbstr($permission).
			')';
		}

		if ($options['groupids'] !== null) {
			zbx_value2array($options['groupids']);

			$sqlParts['where'][] = '('.
				'EXISTS ('.
					'SELECT NULL'.
					' FROM maintenances_groups mg'.
					' WHERE m.maintenanceid=mg.maintenanceid'.
						' AND '.dbConditionId('mg.groupid', $options['groupids']).
				')'.
				' OR EXISTS ('.
					'SELECT NULL'.
					' FROM maintenances_hosts mh,hosts_groups hg'.
					' WHERE m.maintenanceid=mh.maintenanceid'.
						' AND mh.hostid=hg.hostid'.
						' AND '.dbConditionId('hg.groupid', $options['groupids']).
				')'.
			')';
		}

		if ($options['hostids'] !== null) {
			zbx_value2array($options['hostids']);

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM maintenances_hosts mh'.
				' WHERE m.maintenanceid=mh.maintenanceid'.
					' AND '.dbConditionId('mh.hostid', $options['hostids']).
			')';
		}

		// maintenanceids
		if ($options['maintenanceids'] !== null) {
			zbx_value2array($options['maintenanceids']);

			$sqlParts['where'][] = dbConditionInt('m.maintenanceid', $options['maintenanceids']);
		}

		if ($options['triggerids'] !== null) {
			zbx_value2array($options['triggerids']);

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM maintenance_triggers mt'.
				' WHERE m.maintenanceid=mt.maintenanceid'.
				' AND '.dbConditionId('mt.triggerid', $options['triggerids']).
			')';
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('maintenances m', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('maintenances m', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($maintenance = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $maintenance;
				}
				else {
					$result = $maintenance['rowscount'];
				}
			}
			else {
				$result[$maintenance['maintenanceid']] = $maintenance;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);

			if (!$options['preservekeys']) {
				$result = array_values($result);
			}
		}

		return $result;
	}

	/**
	 * @param array $maintenances
	 *
	 * @return array
	 */
	public function create(array $maintenances) {
		if (self::$userData['type'] == USER_TYPE_ZABBIX_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$this->validateCreate($maintenances);

		$maintenanceids = DB::insert('maintenances', $maintenances);

		foreach ($maintenances as $index => &$maintenance) {
			$maintenance['maintenanceid'] = $maintenanceids[$index];
		}
		unset($maintenance);

		self::updateTags($maintenances);
		self::updateGroups($maintenances);
		self::updateHosts($maintenances);
		self::updateTriggers($maintenances);
		self::updateEventNames($maintenances);
		self::updateTimeperiods($maintenances);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_MAINTENANCE, $maintenances);

		return ['maintenanceids' => $maintenanceids];
	}

	/**
	 * @param array $maintenances
	 *
	 * @throws APIException if no permissions to object, it does not exist or the input is invalid.
	 */
	protected function validateCreate(array &$maintenances): void {
		$api_input_rules =		['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('maintenances', 'name')],
			'maintenance_type' =>	['type' => API_INT32, 'in' => implode(',', [MAINTENANCE_TYPE_NORMAL, MAINTENANCE_TYPE_NODATA]), 'default' => DB::getDefault('maintenances', 'maintenance_type')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('maintenances', 'description')],
			'active_since' =>		['type' => API_TIMESTAMP, 'flags' => API_REQUIRED],
			'active_till' =>		['type' => API_TIMESTAMP, 'flags' => API_REQUIRED, 'compare' => ['operator' => '>', 'field' => 'active_since']],
			'tags_evaltype' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'maintenance_type', 'in' => implode(',', [MAINTENANCE_TYPE_NORMAL])], 'type' => API_INT32, 'in' => implode(',', [MAINTENANCE_TAG_EVAL_TYPE_AND_OR, MAINTENANCE_TAG_EVAL_TYPE_OR])],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'tags' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'maintenance_type', 'in' => implode(',', [MAINTENANCE_TYPE_NORMAL])], 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'operator', 'value']], 'fields' => [
				'tag' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('maintenance_tag', 'tag')],
				'operator' =>				['type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE]), 'default' => DB::getDefault('maintenance_tag', 'operator')],
				'value' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('maintenance_tag', 'value'), 'default' => DB::getDefault('maintenance_tag', 'value')]
										]],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'groups' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'hosts' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['hostid']], 'fields' => [
				'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'triggers' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'maintenance_type', 'in' => MAINTENANCE_TYPE_NORMAL], 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['triggerid']], 'fields' => [
				'triggerid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
										]],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'event_names' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'maintenance_type', 'in' => MAINTENANCE_TYPE_NORMAL], 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['operator', 'value']], 'fields' => [
				'operator' =>				['type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE]), 'default' => DB::getDefault('maintenance_eventnames', 'operator')],
				'value' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('maintenance_eventnames', 'value')]
										]],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'timeperiods' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
				'period' =>				['type' => API_TIME_UNIT, 'in' => implode(':', [5 * SEC_PER_MIN, CMaintenanceHelper::MAX_TIMEPERIOD]), 'default' => SEC_PER_HOUR],
				'timeperiod_type' =>	['type' => API_INT32, 'in' => implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]), 'default' => DB::getDefault('timeperiods', 'timeperiod_type')],
				'start_date' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_ONETIME])], 'type' => API_TIMESTAMP, 'default' => time()],
											['else' => true, 'type' => API_UNEXPECTED]
				]],
				'start_time' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_TIMESTAMP, 'format' => 'H:i', 'timezone' => 'UTC', 'in' => implode(':', [0, SEC_PER_DAY - SEC_PER_MIN]), 'default' => DB::getDefault('timeperiods', 'start_time')],
											['else' => true, 'type' => API_UNEXPECTED]
				]],
				'every' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_DAILY])], 'type' => API_INT32, 'in' => implode(':', [1, 999]), 'default' => DB::getDefault('timeperiods', 'every')],
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_WEEKLY])], 'type' => API_INT32, 'in' => implode(':', [1, 99]), 'default' => DB::getDefault('timeperiods', 'every')],
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'in' => implode(',', [MONTH_WEEK_FIRST, MONTH_WEEK_SECOND, MONTH_WEEK_THIRD, MONTH_WEEK_FOURTH, MONTH_WEEK_LAST]), 'default' => DB::getDefault('timeperiods', 'every')],
											['else' => true, 'type' => API_UNEXPECTED]
				]],
				'day' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'in' => implode(':', [0, MONTH_MAX_DAY])],
											['else' => true, 'type' => API_UNEXPECTED]
				]],
				'dayofweek' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_WEEKLY])], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(':', [0b0000001, 0b1111111])],
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'in' => implode(':', [0, 0b1111111])],
											['else' => true, 'type' => API_UNEXPECTED]
				]],
				'month' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(':', [0b000000000001, 0b111111111111])],
											['else' => true, 'type' => API_UNEXPECTED]
				]]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $maintenances, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		foreach ($maintenances as &$maintenance) {
			$maintenance['active_since'] -= $maintenance['active_since'] % SEC_PER_MIN;
			$maintenance['active_till'] -= $maintenance['active_till'] % SEC_PER_MIN;

			if ((!array_key_exists('groups', $maintenance) || !$maintenance['groups'])
					&& (!array_key_exists('hosts', $maintenance) || !$maintenance['hosts'])
					&& (!array_key_exists('triggers', $maintenance) || !$maintenance['triggers'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_('At least one host group, host or trigger must be selected.')
				);
			}
		}
		unset($maintenance);

		$maintenances = self::validateTimePeriods($maintenances);

		self::checkDuplicates($maintenances);
		self::checkGroups($maintenances);
		self::checkHosts($maintenances);
		self::checkTriggers($maintenances);
	}

	/**
	 * @param array $maintenances
	 *
	 * @return array
	 */
	public function update(array $maintenances): array {
		if (self::$userData['type'] == USER_TYPE_ZABBIX_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$this->validateUpdate($maintenances, $db_maintenances);

		$upd_maintenances = [];

		foreach ($maintenances as $maintenance) {
			$upd_maintenance = DB::getUpdatedValues('maintenances', $maintenance,
				$db_maintenances[$maintenance['maintenanceid']]
			);

			if ($upd_maintenance) {
				$upd_maintenances[] = [
					'values' => $upd_maintenance,
					'where' => ['maintenanceid' => $maintenance['maintenanceid']]
				];
			}
		}

		if ($upd_maintenances) {
			DB::update('maintenances', $upd_maintenances);
		}

		self::updateTags($maintenances, $db_maintenances);
		self::updateGroups($maintenances, $db_maintenances);
		self::updateHosts($maintenances, $db_maintenances);
		self::updateTriggers($maintenances, $db_maintenances);
		self::updateEventNames($maintenances, $db_maintenances);
		self::updateTimeperiods($maintenances, $db_maintenances);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_MAINTENANCE, $maintenances, $db_maintenances);

		return ['maintenanceids' => array_column($maintenances, 'maintenanceid')];
	}

	/**
	 * @param array      $maintenances
	 * @param array|null $db_maintenances
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$maintenances, ?array &$db_maintenances = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['maintenanceid']], 'fields' => [
			'maintenanceid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $maintenances, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_maintenances = $this->get([
			'output' => ['maintenanceid', 'name', 'maintenance_type', 'description', 'active_since', 'active_till',
				'tags_evaltype'
			],
			'maintenanceids' => array_column($maintenances, 'maintenanceid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_maintenances) != count($maintenances)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$maintenances = $this->extendObjectsByKey($maintenances, $db_maintenances, 'maintenanceid',
			['maintenance_type', 'active_since', 'active_till']
		);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['maintenanceid'], ['name']], 'fields' => [
			'maintenanceid' =>		['type' => API_ID],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('maintenances', 'name')],
			'maintenance_type' =>	['type' => API_INT32, 'in' => implode(',', [MAINTENANCE_TYPE_NORMAL, MAINTENANCE_TYPE_NODATA])],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('maintenances', 'description')],
			'active_since' =>		['type' => API_TIMESTAMP],
			'active_till' =>		['type' => API_TIMESTAMP, 'compare' => ['operator' => '>', 'field' => 'active_since']],
			'tags_evaltype' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'maintenance_type', 'in' => implode(',', [MAINTENANCE_TYPE_NORMAL])], 'type' => API_INT32, 'in' => implode(',', [MAINTENANCE_TAG_EVAL_TYPE_AND_OR, MAINTENANCE_TAG_EVAL_TYPE_OR])],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'tags' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'maintenance_type', 'in' => implode(',', [MAINTENANCE_TYPE_NORMAL])], 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'operator', 'value']], 'fields' => [
				'tag' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('maintenance_tag', 'tag')],
				'operator' =>				['type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE]), 'default' => DB::getDefault('maintenance_tag', 'operator')],
				'value' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('maintenance_tag', 'value'), 'default' => DB::getDefault('maintenance_tag', 'value')]
										]],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'groups' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'hosts' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['hostid']], 'fields' => [
				'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'triggers' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'maintenance_type', 'in' => MAINTENANCE_TYPE_NORMAL], 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['triggerid']], 'fields' => [
				'triggerid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
										]],
										['else' => true, 'type' => API_OBJECTS, 'length' => 0]
			]],
			'event_names' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'maintenance_type', 'in' => MAINTENANCE_TYPE_NORMAL], 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['operator', 'value']], 'fields' => [
				'operator' =>				['type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE]), 'default' => DB::getDefault('maintenance_eventnames', 'operator')],
				'value' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('maintenance_eventnames', 'value')]
										]],
										['else' => true, 'type' => API_OBJECTS, 'length' => 0]
			]],
			'timeperiods' =>		['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
				'period' =>				['type' => API_TIME_UNIT, 'in' => implode(':', [5 * SEC_PER_MIN, CMaintenanceHelper::MAX_TIMEPERIOD]), 'default' => SEC_PER_HOUR],
				'timeperiod_type' =>	['type' => API_INT32, 'in' => implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY]), 'default' => DB::getDefault('timeperiods', 'timeperiod_type')],
				'start_date' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_ONETIME])], 'type' => API_TIMESTAMP, 'default' => time()],
											['else' => true, 'type' => API_UNEXPECTED]
				]],
				'start_time' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_TIMESTAMP, 'format' => 'H:i', 'timezone' => 'UTC', 'in' => implode(':', [0, SEC_PER_DAY - SEC_PER_MIN]), 'default' => DB::getDefault('timeperiods', 'start_time')],
											['else' => true, 'type' => API_UNEXPECTED]
				]],
				'every' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_DAILY])], 'type' => API_INT32, 'in' => implode(':', [1, 999]), 'default' => DB::getDefault('timeperiods', 'every')],
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_WEEKLY])], 'type' => API_INT32, 'in' => implode(':', [1, 99]), 'default' => DB::getDefault('timeperiods', 'every')],
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'in' => implode(',', [MONTH_WEEK_FIRST, MONTH_WEEK_SECOND, MONTH_WEEK_THIRD, MONTH_WEEK_FOURTH, MONTH_WEEK_LAST]), 'default' => DB::getDefault('timeperiods', 'every')],
											['else' => true, 'type' => API_UNEXPECTED]
				]],
				'day' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'in' => implode(':', [0, MONTH_MAX_DAY])],
											['else' => true, 'type' => API_UNEXPECTED]
				]],
				'dayofweek' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_WEEKLY])], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(':', [0b0000001, 0b1111111])],
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'in' => implode(':', [0, 0b1111111])],
											['else' => true, 'type' => API_UNEXPECTED]
				]],
				'month' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(':', [0b000000000001, 0b111111111111])],
											['else' => true, 'type' => API_UNEXPECTED]
				]]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $maintenances, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$maintenances = self::validateTimePeriods($maintenances);

		self::addAffectedObjects($maintenances, $db_maintenances);

		foreach ($maintenances as &$maintenance) {
			$maintenance['active_since'] -= $maintenance['active_since'] % SEC_PER_MIN;
			$maintenance['active_till'] -= $maintenance['active_till'] % SEC_PER_MIN;

			$db_maintenance = $db_maintenances[$maintenance['maintenanceid']];

			if ($maintenance['maintenance_type'] == MAINTENANCE_TYPE_NODATA
					&& $db_maintenance['maintenance_type'] == MAINTENANCE_TYPE_NORMAL) {
				$maintenance['tags_evaltype'] = DB::getDefault('maintenances', 'tags_evaltype');

				$has_triggers = false;
				$has_event_names = false;

				if (!array_key_exists('triggers', $maintenance)) {
					$has_triggers = (bool) DB::select('maintenance_triggers', [
						'output' => ['maintenance_triggerid'],
						'filter' => ['maintenanceid' => $maintenance['maintenanceid']],
						'limit' => 1
					]);
				}

				if (!array_key_exists('event_names', $maintenance)) {
					$has_event_names = (bool) DB::select('maintenance_eventnames', [
						'output' => ['maintenance_eventnameid'],
						'filter' => ['maintenanceid' => $maintenance['maintenanceid']],
						'limit' => 1
					]);
				}

				if ($has_triggers || $has_event_names) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('Triggers and event name conditions cannot be used with maintenance type "Without data collection".')
					);
				}
			}

			if (array_key_exists('groups', $maintenance)
					|| array_key_exists('hosts', $maintenance)
					|| array_key_exists('triggers', $maintenance)) {
				$groups = array_key_exists('groups', $maintenance) ? $maintenance['groups'] : $db_maintenance['groups'];
				$hosts = array_key_exists('hosts', $maintenance) ? $maintenance['hosts'] : $db_maintenance['hosts'];
				$triggers = array_key_exists('triggers', $maintenance)
					? $maintenance['triggers']
					: $db_maintenance['triggers'];

				if (!$groups && !$hosts && !$triggers) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('At least one host group, host or trigger must be selected.')
					);
				}
			}
		}
		unset($maintenance);

		self::checkDuplicates($maintenances, $db_maintenances);
		self::checkGroups($maintenances, $db_maintenances);
		self::checkHosts($maintenances, $db_maintenances);
		self::checkTriggers($maintenances, $db_maintenances);
	}

	/**
	 * @param array $maintenanceids
	 *
	 * @return array
	 */
	public function delete(array $maintenanceids): array {
		if (self::$userData['type'] == USER_TYPE_ZABBIX_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$this->validateDelete($maintenanceids, $db_maintenances);

		$maintenances_windows = DB::select('maintenances_windows', [
			'output' => ['timeperiodid'],
			'filter' => ['maintenanceid' => $maintenanceids]
		]);

		// Lock maintenances table before maintenance delete to prevent server from adding host to maintenance.
		DBselect(
			'SELECT NULL'.
			' FROM maintenances'.
			' WHERE '.dbConditionId('maintenanceid', $maintenanceids).
			' FOR UPDATE'
		);

		// Remove maintenanceid from hosts table.
		DB::update('hosts', [
			'values' => ['maintenanceid' => 0],
			'where' => ['maintenanceid' => $maintenanceids]
		]);

		DB::delete('timeperiods', ['timeperiodid' => array_column($maintenances_windows, 'timeperiodid')]);
		DB::delete('maintenances', ['maintenanceid' => $maintenanceids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_MAINTENANCE, $db_maintenances);

		return ['maintenanceids' => $maintenanceids];
	}

	/**
	 * @param array      $maintenanceids
	 * @param array|null $db_maintenances
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array $maintenanceids, ?array &$db_maintenances = null): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $maintenanceids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_maintenances = $this->get([
			'output' => ['maintenanceid', 'name'],
			'maintenanceids' => $maintenanceids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_maintenances) != count($maintenanceids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Validate time periods of given maintenances.
	 *
	 * @param array $maintenances
	 *
	 * @return array Array of validated maintenances.
	 *
	 * @throws APIException if time periods are not valid.
	 */
	private static function validateTimePeriods(array $maintenances): array {
		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('timeperiods', $maintenance)) {
				continue;
			}

			foreach ($maintenance['timeperiods'] as &$timeperiod) {
				$timeperiod['period'] = CMaintenanceHelper::normalizeTimePeriod($timeperiod['period']);

				if ($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME) {
					$timeperiod['start_date'] -= $timeperiod['start_date'] % SEC_PER_MIN;
				}
				else {
					$timeperiod['start_time'] -= $timeperiod['start_time'] % SEC_PER_MIN;
				}

				if ($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY) {
					if ((!array_key_exists('day', $timeperiod) || $timeperiod['day'] == 0)
							&& (!array_key_exists('dayofweek', $timeperiod) || $timeperiod['dayofweek'] == 0)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('At least one day of the week or day of the month must be specified.')
						);
					}
					elseif (array_key_exists('day', $timeperiod) && $timeperiod['day'] != 0
							&& array_key_exists('dayofweek', $timeperiod) && $timeperiod['dayofweek'] != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Day of the week and day of the month cannot be specified simultaneously.')
						);
					}
				}
			}
			unset($timeperiod);
		}
		unset($maintenance);

		return $maintenances;
	}

	/**
	 * Check for unique maintenance names.
	 *
	 * @param array      $maintenances
	 * @param array|null $db_maintenances
	 *
	 * @throws APIException if maintenance names are not unique.
	 */
	protected static function checkDuplicates(array $maintenances, ?array $db_maintenances = null): void {
		$names = [];

		foreach ($maintenances as $maintenance) {
			if (!array_key_exists('name', $maintenance)) {
				continue;
			}

			if ($db_maintenances === null
					|| $maintenance['name'] !== $db_maintenances[$maintenance['maintenanceid']]['name']) {
				$names[] = $maintenance['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicates = DB::select('maintenances', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Maintenance "%1$s" already exists.', $duplicates[0]['name']));
		}
	}

	/**
	 * Check for valid host groups.
	 *
	 * @param array      $maintenances
	 * @param array|null $db_maintenances
	 *
	 * @throws APIException if groups are not valid.
	 */
	private static function checkGroups(array $maintenances, ?array $db_maintenances = null): void {
		$edit_groupids = [];

		foreach ($maintenances as $maintenance) {
			if (!array_key_exists('groups', $maintenance)) {
				continue;
			}

			$groupids = array_column($maintenance['groups'], 'groupid');

			if ($db_maintenances === null) {
				$edit_groupids += array_flip($groupids);
			}
			else {
				$db_groupids = array_column($db_maintenances[$maintenance['maintenanceid']]['groups'], 'groupid');

				$ins_groupids = array_flip(array_diff($groupids, $db_groupids));
				$del_groupids = array_flip(array_diff($db_groupids, $groupids));

				$edit_groupids += $ins_groupids + $del_groupids;
			}
		}

		if (!$edit_groupids) {
			return;
		}

		$count = API::HostGroup()->get([
			'countOutput' => true,
			'groupids' => array_keys($edit_groupids),
			'editable' => true
		]);

		if ($count != count($edit_groupids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Check for valid hosts.
	 *
	 * @param array      $maintenances
	 * @param array|null $db_maintenances
	 *
	 * @throws APIException if hosts are not valid.
	 */
	private static function checkHosts(array $maintenances, ?array $db_maintenances = null): void {
		$edit_hostids = [];

		foreach ($maintenances as $maintenance) {
			if (!array_key_exists('hosts', $maintenance)) {
				continue;
			}

			$hostids = array_column($maintenance['hosts'], 'hostid');

			if ($db_maintenances === null) {
				$edit_hostids += array_flip($hostids);
			}
			else {
				$db_hostids = array_column($db_maintenances[$maintenance['maintenanceid']]['hosts'], 'hostid');

				$ins_hostids = array_flip(array_diff($hostids, $db_hostids));
				$del_hostids = array_flip(array_diff($db_hostids, $hostids));

				$edit_hostids += $ins_hostids + $del_hostids;
			}
		}

		if (!$edit_hostids) {
			return;
		}

		$count = API::Host()->get([
			'countOutput' => true,
			'hostids' => array_keys($edit_hostids),
			'editable' => true
		]);

		if ($count != count($edit_hostids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Check for valid triggers.
	 *
	 * @param array      $maintenances
	 * @param array|null $db_maintenances
	 *
	 * @throws APIException if triggers are not valid.
	 */
	private static function checkTriggers(array $maintenances, ?array $db_maintenances = null): void {
		$edit_triggerids = [];

		foreach ($maintenances as $maintenance) {
			if (!array_key_exists('triggers', $maintenance)) {
				continue;
			}

			$triggerids = array_column($maintenance['triggers'], 'triggerid');

			if ($db_maintenances === null) {
				$edit_triggerids += array_flip($triggerids);
			}
			else {
				$db_triggerids = array_column($db_maintenances[$maintenance['maintenanceid']]['triggers'], 'triggerid');

				$ins_triggerids = array_flip(array_diff($triggerids, $db_triggerids));
				$del_triggerids = array_flip(array_diff($db_triggerids, $triggerids));

				$edit_triggerids += $ins_triggerids + $del_triggerids;
			}
		}

		if (!$edit_triggerids) {
			return;
		}

		$count = API::Trigger()->get([
			'countOutput' => true,
			'triggerids' => array_keys($edit_triggerids),
			'templated' => false,
			'editable' => true
		]);

		if ($count != count($edit_triggerids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}


	/**
	 * Update table "maintenance_tag".
	 *
	 * @param array      $maintenances
	 * @param array|null $db_maintenances
	 */
	private static function updateTags(array &$maintenances, ?array $db_maintenances = null): void {
		$ins_maintenance_tags = [];
		$del_maintenancetagids = [];

		foreach ($maintenances as &$maintenance) {
			if (($db_maintenances === null && !array_key_exists('tags', $maintenance))
					|| ($db_maintenances !== null
						&& !array_key_exists('tags', $db_maintenances[$maintenance['maintenanceid']]))) {
				continue;
			}

			if ($db_maintenances !== null && !array_key_exists('tags', $maintenance)) {
				$maintenance['tags'] = [];
			}

			$db_tags = ($db_maintenances !== null) ? $db_maintenances[$maintenance['maintenanceid']]['tags'] : [];

			foreach ($maintenance['tags'] as &$tag) {
				$db_maintenancetagid = key(
					array_filter($db_tags, static function (array $db_tag) use ($tag): bool {
						return $tag['tag'] == $db_tag['tag'] && $tag['operator'] == $db_tag['operator']
							&& $tag['value'] == $db_tag['value'];
					})
				);

				if ($db_maintenancetagid !== null) {
					$tag['maintenancetagid'] = $db_maintenancetagid;
					unset($db_tags[$db_maintenancetagid]);
				}
				else {
					$ins_maintenance_tags[] = ['maintenanceid' => $maintenance['maintenanceid']] + $tag;
				}
			}
			unset($tag);

			$del_maintenancetagids = array_merge($del_maintenancetagids, array_keys($db_tags));
		}
		unset($maintenance);

		if ($del_maintenancetagids) {
			DB::delete('maintenance_tag', ['maintenancetagid' => $del_maintenancetagids]);
		}

		if ($ins_maintenance_tags) {
			$maintenancetagids = DB::insert('maintenance_tag', $ins_maintenance_tags);
		}

		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('tags', $maintenance)) {
				continue;
			}

			foreach ($maintenance['tags'] as &$tag) {
				if (!array_key_exists('maintenancetagid', $tag)) {
					$tag['maintenancetagid'] = array_shift($maintenancetagids);
				}
			}
			unset($tag);
		}
		unset($maintenance);
	}

	/**
	 * Update table "maintenances_groups".
	 *
	 * @param array      $maintenances
	 * @param array|null $db_maintenances
	 */
	private static function updateGroups(array &$maintenances, ?array $db_maintenances = null): void {
		$ins_groups = [];
		$del_groupids = [];

		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('groups', $maintenance)) {
				continue;
			}

			$maintenanceid = $maintenance['maintenanceid'];

			$db_groups = ($db_maintenances !== null)
				? array_column($db_maintenances[$maintenanceid]['groups'], null, 'groupid')
				: [];

			foreach ($maintenance['groups'] as &$group) {
				if (array_key_exists($group['groupid'], $db_groups)) {
					$group['maintenance_groupid'] = $db_groups[$group['groupid']]['maintenance_groupid'];
					unset($db_groups[$group['groupid']]);
				}
				else {
					$ins_groups[] = [
						'maintenanceid' => $maintenanceid,
						'groupid' => $group['groupid']
					];
				}
			}
			unset($group);

			$del_groupids = array_merge($del_groupids, array_column($db_groups, 'maintenance_groupid'));
		}
		unset($maintenance);

		if ($del_groupids) {
			DB::delete('maintenances_groups', ['maintenance_groupid' => $del_groupids]);
		}

		if ($ins_groups) {
			$maintenance_groupids = DB::insertBatch('maintenances_groups', $ins_groups);
		}

		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('groups', $maintenance)) {
				continue;
			}

			foreach ($maintenance['groups'] as &$group) {
				if (!array_key_exists('maintenance_groupid', $group)) {
					$group['maintenance_groupid'] = array_shift($maintenance_groupids);
				}
			}
			unset($group);
		}
		unset($maintenance);
	}

	/**
	 * Update table "maintenances_hosts".
	 *
	 * @param array      $maintenances
	 * @param array|null $db_maintenances
	 */
	private static function updateHosts(array &$maintenances, ?array $db_maintenances = null): void {
		$ins_maintenances_hosts = [];
		$del_maintenance_hostids = [];

		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('hosts', $maintenance)) {
				continue;
			}

			$maintenanceid = $maintenance['maintenanceid'];

			$db_hosts = ($db_maintenances !== null)
				? array_column($db_maintenances[$maintenanceid]['hosts'], null, 'hostid')
				: [];

			foreach ($maintenance['hosts'] as &$host) {
				if (array_key_exists($host['hostid'], $db_hosts)) {
					$host['maintenance_hostid'] = $db_hosts[$host['hostid']]['maintenance_hostid'];
					unset($db_hosts[$host['hostid']]);
				}
				else {
					$ins_maintenances_hosts[] = [
						'maintenanceid' => $maintenanceid,
						'hostid' => $host['hostid']
					];
				}
			}
			unset($host);

			$del_maintenance_hostids = array_merge($del_maintenance_hostids,
				array_column($db_hosts, 'maintenance_hostid')
			);
		}
		unset($maintenance);

		if ($del_maintenance_hostids) {
			DB::delete('maintenances_hosts', ['maintenance_hostid' => $del_maintenance_hostids]);
		}

		if ($ins_maintenances_hosts) {
			$maintenance_hostids = DB::insertBatch('maintenances_hosts', $ins_maintenances_hosts);
		}

		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('hosts', $maintenance)) {
				continue;
			}

			foreach ($maintenance['hosts'] as &$host) {
				if (!array_key_exists('maintenance_hostid', $host)) {
					$host['maintenance_hostid'] = array_shift($maintenance_hostids);
				}
			}
			unset($host);
		}
		unset($maintenance);
	}

	/**
	 * Update table "maintenance_triggers".
	 *
	 * @param array      $maintenances
	 * @param array|null $db_maintenances
	 */
	private static function updateTriggers(array &$maintenances, ?array $db_maintenances = null): void {
		$ins_maintenance_triggers = [];
		$del_maintenance_triggerids = [];

		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('triggers', $maintenance)) {
				continue;
			}

			$maintenanceid = $maintenance['maintenanceid'];

			$db_triggers = ($db_maintenances !== null)
				? array_column($db_maintenances[$maintenanceid]['triggers'], null, 'triggerid')
				: [];

			foreach ($maintenance['triggers'] as &$trigger) {
				if (array_key_exists($trigger['triggerid'], $db_triggers)) {
					$trigger['maintenance_triggerid'] = $db_triggers[$trigger['triggerid']]['maintenance_triggerid'];
					unset($db_triggers[$trigger['triggerid']]);
				}
				else {
					$ins_maintenance_triggers[] = [
						'maintenanceid' => $maintenanceid,
						'triggerid' => $trigger['triggerid']
					];
				}
			}
			unset($trigger);

			$del_maintenance_triggerids = array_merge($del_maintenance_triggerids,
				array_column($db_triggers, 'maintenance_triggerid')
			);
		}
		unset($maintenance);

		if ($del_maintenance_triggerids) {
			DB::delete('maintenance_triggers', ['maintenance_triggerid' => $del_maintenance_triggerids]);
		}

		if ($ins_maintenance_triggers) {
			$maintenance_triggerids = DB::insertBatch('maintenance_triggers', $ins_maintenance_triggers);
		}

		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('triggers', $maintenance)) {
				continue;
			}

			foreach ($maintenance['triggers'] as &$trigger) {
				if (!array_key_exists('maintenance_triggerid', $trigger)) {
					$trigger['maintenance_triggerid'] = array_shift($maintenance_triggerids);
				}
			}
			unset($trigger);
		}
		unset($maintenance);
	}

	/**
	 * Update table "maintenance_eventnames".
	 *
	 * @param array      $maintenances
	 * @param array|null $db_maintenances
	 */
	private static function updateEventNames(array &$maintenances, ?array $db_maintenances = null): void {
		$ins_event_names = [];
		$del_maintenance_eventnameids = [];

		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('event_names', $maintenance)) {
				continue;
			}

			$db_event_names = ($db_maintenances !== null)
				? $db_maintenances[$maintenance['maintenanceid']]['event_names']
				: [];

			foreach ($maintenance['event_names'] as &$event_name) {
				$db_maintenance_eventnameid = key(
					array_filter($db_event_names, static function (array $db_event_name) use ($event_name): bool {
						return $event_name['operator'] == $db_event_name['operator']
							&& $event_name['value'] === $db_event_name['value'];
					})
				);

				if ($db_maintenance_eventnameid !== null) {
					$event_name['maintenance_eventnameid'] = $db_maintenance_eventnameid;
					unset($db_event_names[$db_maintenance_eventnameid]);
				}
				else {
					$ins_event_names[] = ['maintenanceid' => $maintenance['maintenanceid']] + $event_name;
				}
			}
			unset($event_name);

			$del_maintenance_eventnameids = array_merge($del_maintenance_eventnameids, array_keys($db_event_names));
		}
		unset($maintenance);

		if ($del_maintenance_eventnameids) {
			DB::delete('maintenance_eventnames', ['maintenance_eventnameid' => $del_maintenance_eventnameids]);
		}

		if ($ins_event_names) {
			$maintenance_eventnameids = DB::insert('maintenance_eventnames', $ins_event_names);
		}

		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('event_names', $maintenance)) {
				continue;
			}

			foreach ($maintenance['event_names'] as &$event_name) {
				if (!array_key_exists('maintenance_eventnameid', $event_name)) {
					$event_name['maintenance_eventnameid'] = array_shift($maintenance_eventnameids);
				}
			}
			unset($event_name);
		}
		unset($maintenance);
	}

	/**
	 * Update tables "periods" and "maintenances_windows".
	 *
	 * @param array      $maintenances
	 * @param array|null $db_maintenances
	 */
	private static function updateTimeperiods(array &$maintenances, ?array $db_maintenances = null): void {
		$ins_timeperiods = [];
		$ins_maintenances_windows = [];
		$del_timeperiodids = [];

		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('timeperiods', $maintenance)) {
				continue;
			}

			$db_timeperiods = ($db_maintenances !== null)
				? $db_maintenances[$maintenance['maintenanceid']]['timeperiods']
				: [];

			foreach ($maintenance['timeperiods'] as &$timeperiod) {
				$db_timeperiodid = key(
					array_filter($db_timeperiods, static function (array $db_timeperiod) use ($timeperiod): bool {
						return $timeperiod['period'] == $db_timeperiod['period']
							&& $timeperiod['timeperiod_type'] == $db_timeperiod['timeperiod_type']
							&& (!array_key_exists('start_date', $timeperiod)
									|| $timeperiod['start_date'] == $db_timeperiod['start_date'])
							&& (!array_key_exists('start_time', $timeperiod)
									|| $timeperiod['start_time'] == $db_timeperiod['start_time'])
							&& (!array_key_exists('every', $timeperiod)
									|| $timeperiod['every'] == $db_timeperiod['every'])
							&& (!array_key_exists('day', $timeperiod) || $timeperiod['day'] == $db_timeperiod['day'])
							&& (!array_key_exists('dayofweek', $timeperiod)
									|| $timeperiod['dayofweek'] == $db_timeperiod['dayofweek'])
							&& (!array_key_exists('month', $timeperiod)
									|| $timeperiod['month'] == $db_timeperiod['month']);
					})
				);

				if ($db_timeperiodid !== null) {
					$timeperiod['timeperiodid'] = $db_timeperiodid;
					unset($db_timeperiods[$db_timeperiodid]);
				}
				else {
					$ins_timeperiods[] = $timeperiod;
					$ins_maintenances_windows[] = ['maintenanceid' => $maintenance['maintenanceid']];
				}
			}
			unset($timeperiod);

			$del_timeperiodids = array_merge($del_timeperiodids, array_keys($db_timeperiods));
		}
		unset($maintenance);

		if ($del_timeperiodids) {
			DB::delete('maintenances_windows', ['timeperiodid' => $del_timeperiodids]);
			DB::delete('timeperiods', ['timeperiodid' => $del_timeperiodids]);
		}

		if ($ins_timeperiods) {
			$timeperiodids = DB::insert('timeperiods', $ins_timeperiods);

			foreach ($ins_maintenances_windows as $i => &$maintenance_window) {
				$maintenance_window += ['timeperiodid' => $timeperiodids[$i]];
			}
			unset($maintenance_window);

			DB::insertBatch('maintenances_windows', $ins_maintenances_windows);
		}

		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('timeperiods', $maintenance)) {
				continue;
			}

			foreach ($maintenance['timeperiods'] as &$timeperiod) {
				if (!array_key_exists('timeperiodid', $timeperiod)) {
					$timeperiod['timeperiodid'] = array_shift($timeperiodids);
				}
			}
			unset($timeperiod);
		}
		unset($maintenance);
	}

	/**
	 * @param array $maintenances
	 * @param array $db_maintenances
	 */
	private static function addAffectedObjects(array $maintenances, array &$db_maintenances): void {
		self::addAffectedTags($maintenances, $db_maintenances);
		self::addAffectedTargets($maintenances, $db_maintenances);
		self::addAffectedEventNames($maintenances, $db_maintenances);
		self::addAffectedTimeperiods($maintenances, $db_maintenances);
	}

	/**
	 * @param array $maintenances
	 * @param array $db_maintenances
	 */
	private static function addAffectedTags(array $maintenances, array &$db_maintenances): void {
		$maintenanceids = [];

		foreach ($maintenances as $maintenance) {
			$db_maintenance_type = $db_maintenances[$maintenance['maintenanceid']]['maintenance_type'];

			if (array_key_exists('tags', $maintenance)
					|| ($maintenance['maintenance_type'] != $db_maintenance_type
						&& $maintenance['maintenance_type'] == MAINTENANCE_TYPE_NODATA)) {
				$maintenanceids[] = $maintenance['maintenanceid'];
				$db_maintenances[$maintenance['maintenanceid']]['tags'] = [];
			}
		}

		if (!$maintenanceids) {
			return;
		}

		$options = [
			'output' => ['maintenancetagid', 'maintenanceid', 'tag', 'operator', 'value'],
			'filter' => ['maintenanceid' => $maintenanceids]
		];
		$db_tags = DBselect(DB::makeSql('maintenance_tag', $options));

		while ($db_tag = DBfetch($db_tags)) {
			$db_maintenances[$db_tag['maintenanceid']]['tags'][$db_tag['maintenancetagid']] = [
				'maintenancetagid' => $db_tag['maintenancetagid'],
				'tag' => $db_tag['tag'],
				'operator' => $db_tag['operator'],
				'value' => $db_tag['value']
			];
		}
	}

	/**
	 * @param array $maintenances
	 * @param array $db_maintenances
	 */
	private static function addAffectedTargets(array $maintenances, array &$db_maintenances): void {
		$maintenanceids = [];

		foreach ($maintenances as $maintenance) {
			if (array_key_exists('groups', $maintenance) || array_key_exists('hosts', $maintenance)
					|| array_key_exists('triggers', $maintenance)) {
				$maintenanceids[] = $maintenance['maintenanceid'];
				$db_maintenances[$maintenance['maintenanceid']]['groups'] = [];
				$db_maintenances[$maintenance['maintenanceid']]['hosts'] = [];
				$db_maintenances[$maintenance['maintenanceid']]['triggers'] = [];
			}
		}

		if (!$maintenanceids) {
			return;
		}

		$options = [
			'output' => ['maintenance_groupid', 'maintenanceid', 'groupid'],
			'filter' => ['maintenanceid' => $maintenanceids]
		];
		$db_groups = DBselect(DB::makeSql('maintenances_groups', $options));

		while ($db_group = DBfetch($db_groups)) {
			$db_maintenances[$db_group['maintenanceid']]['groups'][$db_group['maintenance_groupid']] = [
				'maintenance_groupid' => $db_group['maintenance_groupid'],
				'groupid' => $db_group['groupid']
			];
		}

		$options = [
			'output' => ['maintenance_hostid', 'maintenanceid', 'hostid'],
			'filter' => ['maintenanceid' => $maintenanceids]
		];
		$db_hosts = DBselect(DB::makeSql('maintenances_hosts', $options));

		while ($db_host = DBfetch($db_hosts)) {
			$db_maintenances[$db_host['maintenanceid']]['hosts'][$db_host['maintenance_hostid']] = [
				'maintenance_hostid' => $db_host['maintenance_hostid'],
				'hostid' => $db_host['hostid']
			];
		}

		$options = [
			'output' => ['maintenance_triggerid', 'maintenanceid', 'triggerid'],
			'filter' => ['maintenanceid' => $maintenanceids]
		];
		$db_triggers = DBselect(DB::makeSql('maintenance_triggers', $options));

		while ($db_trigger = DBfetch($db_triggers)) {
			$db_maintenances[$db_trigger['maintenanceid']]['triggers'][$db_trigger['maintenance_triggerid']] = [
				'maintenance_triggerid' => $db_trigger['maintenance_triggerid'],
				'triggerid' => $db_trigger['triggerid']
			];
		}
	}

	/**
	 * @param array $maintenances
	 * @param array $db_maintenances
	 */
	private static function addAffectedEventNames(array $maintenances, array &$db_maintenances): void {
		$maintenanceids = [];

		foreach ($maintenances as $maintenance) {
			if (array_key_exists('event_names', $maintenance)) {
				$maintenanceids[] = $maintenance['maintenanceid'];
				$db_maintenances[$maintenance['maintenanceid']]['event_names'] = [];
			}
		}

		if (!$maintenanceids) {
			return;
		}

		$options = [
			'output' => ['maintenance_eventnameid', 'maintenanceid', 'value', 'operator'],
			'filter' => ['maintenanceid' => $maintenanceids]
		];
		$db_event_names = DBselect(DB::makeSql('maintenance_eventnames', $options));

		while ($db_event_name = DBfetch($db_event_names)) {
			$db_maintenances[$db_event_name['maintenanceid']]['event_names']
					[$db_event_name['maintenance_eventnameid']] = [
				'maintenance_eventnameid' => $db_event_name['maintenance_eventnameid'],
				'operator' => $db_event_name['operator'],
				'value' => $db_event_name['value']
			];
		}
	}

	/**
	 * @param array $maintenances
	 * @param array $db_maintenances
	 */
	private static function addAffectedTimeperiods(array $maintenances, array &$db_maintenances): void {
		$maintenanceids = [];

		foreach ($maintenances as $maintenance) {
			if (array_key_exists('timeperiods', $maintenance)) {
				$maintenanceids[] = $maintenance['maintenanceid'];
				$db_maintenances[$maintenance['maintenanceid']]['timeperiods'] = [];
			}
		}

		if (!$maintenanceids) {
			return;
		}

		$db_timeperiods = DBselect(
			'SELECT mw.maintenanceid,mw.timeperiodid,t.timeperiod_type,t.every,t.month,t.dayofweek,t.day,t.start_time,'.
				't.period,t.start_date'.
			' FROM maintenances_windows mw,timeperiods t'.
			' WHERE mw.timeperiodid=t.timeperiodid'.
				' AND '.dbConditionInt('mw.maintenanceid', $maintenanceids)
		);

		while ($db_timeperiod = DBfetch($db_timeperiods)) {
			$db_maintenances[$db_timeperiod['maintenanceid']]['timeperiods'][$db_timeperiod['timeperiodid']] =
				array_diff_key($db_timeperiod, array_flip(['maintenanceid']));
		}
	}

	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		$this->addRelatedHostGroups($options, $result);
		$this->addRelatedTriggers($options, $result);
		$this->addRelatedEventNames($options, $result);

		// selectHosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$hosts = [];
			$relationMap = $this->createRelationMap($result, 'maintenanceid', 'hostid', 'maintenances_hosts');
			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$hosts = API::Host()->get([
					'output' => $options['selectHosts'],
					'hostids' => $related_ids,
					'preservekeys' => true
				]);
			}

			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// Adding problem tags.
		if ($options['selectTags'] !== null && $options['selectTags'] != API_OUTPUT_COUNT) {
			$tags = API::getApiService()->select('maintenance_tag', [
				'output' => $this->outputExtend($options['selectTags'], ['maintenanceid']),
				'filter' => ['maintenanceids' => array_keys($result)],
				'preservekeys' => true
			]);
			$relation_map = $this->createRelationMap($tags, 'maintenanceid', 'maintenancetagid');
			$tags = $this->unsetExtraFields($tags, ['maintenancetagid', 'maintenanceid']);
			$result = $relation_map->mapMany($result, $tags, 'tags');
		}

		// selectTimeperiods
		if ($options['selectTimeperiods'] !== null && $options['selectTimeperiods'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'maintenanceid', 'timeperiodid', 'maintenances_windows');
			$timeperiods = API::getApiService()->select('timeperiods', [
				'output' => $options['selectTimeperiods'],
				'filter' => ['timeperiodid' => $relationMap->getRelatedIds()],
				'preservekeys' => true
			]);
			$timeperiods = $this->unsetExtraFields($timeperiods, ['timeperiodid']);
			$result = $relationMap->mapMany($result, $timeperiods, 'timeperiods');
		}

		return $result;
	}

	private function addRelatedHostGroups(array $options, array &$result): void {
		if ($options['selectHostGroups'] === null || $options['selectHostGroups'] === API_OUTPUT_COUNT) {
			return;
		}

		$relation_map = $this->createRelationMap($result, 'maintenanceid', 'groupid', 'maintenances_groups');
		$related_ids = $relation_map->getRelatedIds();
		$groups = $related_ids
			? API::HostGroup()->get([
				'output' => $options['selectHostGroups'],
				'groupids' => $related_ids,
				'preservekeys' => true
			])
			: [];

		$result = $relation_map->mapMany($result, $groups, 'hostgroups');
	}

	private function addRelatedTriggers(array $options, array &$result): void {
		if ($options['selectTriggers'] === null || $options['selectTriggers'] === API_OUTPUT_COUNT) {
			return;
		}

		$maintenance_triggers = API::getApiService()->select('maintenance_triggers', [
			'output' => ['maintenanceid', 'triggerid'],
			'filter' => ['maintenanceid' => array_keys($result)]
		]);

		foreach ($result as &$maintenance) {
			$maintenance['triggers'] = [];
		}
		unset($maintenance);

		$triggerids = [];

		foreach ($maintenance_triggers as $maintenance_trigger) {
			$triggerids[$maintenance_trigger['triggerid']] = true;
		}

		$triggers = $triggerids
			? API::Trigger()->get([
				'output' => $options['selectTriggers'],
				'triggerids' => array_keys($triggerids),
				'preservekeys' => true
			])
			: [];

		foreach ($maintenance_triggers as $maintenance_trigger) {
			$triggerid = $maintenance_trigger['triggerid'];

			if (array_key_exists($triggerid, $triggers)) {
				$result[$maintenance_trigger['maintenanceid']]['triggers'][] = $triggers[$triggerid];
			}
		}
	}

	private function addRelatedEventNames(array $options, array &$result): void {
		if ($options['selectEventNames'] === null || $options['selectEventNames'] === API_OUTPUT_COUNT) {
			return;
		}

		$event_names = API::getApiService()->select('maintenance_eventnames', [
			'output' => $this->outputExtend($options['selectEventNames'], ['maintenanceid']),
			'filter' => ['maintenanceid' => array_keys($result)]
		]);

		foreach ($result as &$maintenance) {
			$maintenance['event_names'] = [];
		}
		unset($maintenance);

		foreach ($event_names as $event_name) {
			$maintenanceid = $event_name['maintenanceid'];

			unset($event_name['maintenance_eventnameid'], $event_name['maintenanceid']);

			$result[$maintenanceid]['event_names'][] = $event_name;
		}
	}
}
