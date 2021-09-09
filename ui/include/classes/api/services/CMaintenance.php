<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
	 * Get maintenances data.
	 *
	 * @param array  $options
	 * @param array  $options['itemids']
	 * @param array  $options['hostids']
	 * @param array  $options['groupids']
	 * @param array  $options['triggerids']
	 * @param array  $options['maintenanceids']
	 * @param bool   $options['status']
	 * @param bool   $options['editable']
	 * @param bool   $options['count']
	 * @param string $options['pattern']
	 * @param int    $options['limit']
	 * @param string $options['order']
	 *
	 * @return array
	 */
	public function get(array $options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['maintenance' => 'm.maintenanceid'],
			'from'		=> ['maintenances' => 'maintenances m'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'					=> null,
			'hostids'					=> null,
			'maintenanceids'			=> null,
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
			'selectGroups'				=> null,
			'selectHosts'				=> null,
			'selectTags'				=> null,
			'selectTimeperiods'			=> null,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		$maintenanceids = [];
		if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN || $options['nopermissions']) {
			if (!is_null($options['groupids']) || !is_null($options['hostids'])) {
				if (!is_null($options['groupids'])) {
					zbx_value2array($options['groupids']);
					$res = DBselect(
						'SELECT mmg.maintenanceid'.
						' FROM maintenances_groups mmg'.
						' WHERE '.dbConditionInt('mmg.groupid', $options['groupids'])
					);
					while ($maintenance = DBfetch($res)) {
						$maintenanceids[] = $maintenance['maintenanceid'];
					}
				}

				$sql = 'SELECT mmh.maintenanceid'.
						' FROM maintenances_hosts mmh,hosts_groups hg'.
						' WHERE hg.hostid=mmh.hostid';

				if (!is_null($options['groupids'])) {
					zbx_value2array($options['groupids']);
					$sql .= ' AND '.dbConditionInt('hg.groupid', $options['groupids']);
				}

				if (!is_null($options['hostids'])) {
					zbx_value2array($options['hostids']);
					$sql .= ' AND '.dbConditionInt('hg.hostid', $options['hostids']);
				}
				$res = DBselect($sql);
				while ($maintenance = DBfetch($res)) {
					$maintenanceids[] = $maintenance['maintenanceid'];
				}
				$sqlParts['where'][] = dbConditionInt('m.maintenanceid', $maintenanceids);
			}
		}
		else {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

			$sql = 'SELECT m.maintenanceid'.
					' FROM maintenances m'.
					' WHERE NOT EXISTS ('.
						'SELECT NULL'.
						' FROM maintenances_hosts mh,hosts_groups hg'.
							' LEFT JOIN rights r'.
								' ON r.id=hg.groupid'.
									' AND '.dbConditionInt('r.groupid', $userGroups).
						' WHERE m.maintenanceid=mh.maintenanceid'.
							' AND mh.hostid=hg.hostid'.
						' GROUP by mh.hostid'.
						' HAVING MIN(r.permission) IS NULL'.
							' OR MIN(r.permission)='.PERM_DENY.
							' OR MAX(r.permission)<'.zbx_dbstr($permission).
						')'.
					' AND NOT EXISTS ('.
						'SELECT NULL'.
						' FROM maintenances_groups mg'.
							' LEFT JOIN rights r'.
								' ON r.id=mg.groupid'.
									' AND '.dbConditionInt('r.groupid', $userGroups).
						' WHERE m.maintenanceid=mg.maintenanceid'.
						' GROUP by mg.groupid'.
						' HAVING MIN(r.permission) IS NULL'.
							' OR MIN(r.permission)='.PERM_DENY.
							' OR MAX(r.permission)<'.zbx_dbstr($permission).
						')';

			if (!is_null($options['groupids'])) {
				zbx_value2array($options['groupids']);
				$sql .= ' AND ('.
						'EXISTS ('.
							'SELECT NULL'.
								' FROM maintenances_groups mg'.
								' WHERE m.maintenanceid=mg.maintenanceid'.
								' AND '.dbConditionInt('mg.groupid', $options['groupids']).
							')'.
						' OR EXISTS ('.
							'SELECT NULL'.
								' FROM maintenances_hosts mh,hosts_groups hg'.
								' WHERE m.maintenanceid=mh.maintenanceid'.
									' AND mh.hostid=hg.hostid'.
									' AND '.dbConditionInt('hg.groupid', $options['groupids']).
							')'.
						')';
			}

			if (!is_null($options['hostids'])) {
				zbx_value2array($options['hostids']);
				$sql .= ' AND EXISTS ('.
						'SELECT NULL'.
							' FROM maintenances_hosts mh'.
							' WHERE m.maintenanceid=mh.maintenanceid'.
								' AND '.dbConditionInt('mh.hostid', $options['hostids']).
						')';
			}

			if (!is_null($options['maintenanceids'])) {
				zbx_value2array($options['maintenanceids']);
				$sql .= ' AND '.dbConditionInt('m.maintenanceid', $options['maintenanceids']);
			}

			$res = DBselect($sql);
			while ($maintenance = DBfetch($res)) {
				$maintenanceids[] = $maintenance['maintenanceid'];
			}
			$sqlParts['where'][] = dbConditionInt('m.maintenanceid', $maintenanceids);
		}

		// maintenanceids
		if (!is_null($options['maintenanceids'])) {
			zbx_value2array($options['maintenanceids']);

			$sqlParts['where'][] = dbConditionInt('m.maintenanceid', $options['maintenanceids']);
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
		}

		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * Add maintenances.
	 *
	 * @param array $maintenances
	 *
	 * @return array
	 */
	public function create(array $maintenances) {
		$this->validateCreate($maintenances);

		$ins_maintenances = [];

		foreach ($maintenances as &$maintenance) {
			$maintenance['active_since'] -= $maintenance['active_since'] % SEC_PER_MIN;
			$maintenance['active_till'] -= $maintenance['active_till'] % SEC_PER_MIN;

			foreach ($maintenance['timeperiods'] as &$timeperiod) {
				if ($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME) {
					$timeperiod['start_date'] -= $timeperiod['start_date'] % SEC_PER_MIN;
				}
			}
			unset($timeperiod);

			$ins_maintenances[] = $maintenance;
		}
		unset($maintenance);

		$maintenanceids = DB::insert('maintenances', $ins_maintenances);

		foreach ($maintenances as $index => &$maintenance) {
			$maintenance['maintenanceid'] = $maintenanceids[$index];
		}
		unset($maintenance);

		self::updateGroups($maintenances, __FUNCTION__);
		self::updateHosts($maintenances, __FUNCTION__);
		self::updateTags($maintenances, __FUNCTION__);
		self::updateTimeperiods($maintenances, __FUNCTION__);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_MAINTENANCE, $maintenances);

		return ['maintenanceids' => $maintenanceids];
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $maintenances
	 *
	 * @throws APIException if no permissions to object, it does no exists or the input is invalid.
	 */
	protected function validateCreate(array &$maintenances): void {
		$api_input_rules =		['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('maintenances', 'name')],
			'maintenance_type' =>	['type' => API_INT32, 'default' => DB::getDefault('maintenances', 'maintenance_type'), 'in' => implode(',', [MAINTENANCE_TYPE_NORMAL, MAINTENANCE_TYPE_NODATA])],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('maintenances', 'description')],
			'active_since' =>		['type' => API_INT32, 'in' => '0:2147464800'],
			'active_till' =>		['type' => API_INT32, 'in' => '0:2147464800'],
			'tags_evaltype' =>		['type' => API_INT32, 'in' => implode(',', [MAINTENANCE_TAG_EVAL_TYPE_AND_OR, MAINTENANCE_TAG_EVAL_TYPE_OR])],
			'groupids' =>			['type' => API_IDS, 'flags' => API_NORMALIZE | API_DEPRECATED, 'uniq' => true],
			'hostids' =>			['type' => API_IDS, 'flags' => API_NORMALIZE | API_DEPRECATED, 'uniq' => true],
			'groups' =>				['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'hosts' =>				['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['hostid']], 'fields' => [
				'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>				['type' => API_OBJECTS, 'uniq' => [['tag', 'operator', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('maintenance_tag', 'tag')],
				'operator' =>			['type' => API_INT32, 'default' => DB::getDefault('maintenance_tag', 'operator'), 'in' => implode(',', [MAINTENANCE_TAG_OPERATOR_EQUAL, MAINTENANCE_TAG_OPERATOR_LIKE])],
				'value' =>				['type' => API_STRING_UTF8, 'default' => DB::getDefault('maintenance_tag', 'value'), 'length' => DB::getFieldLength('maintenance_tag', 'value')]
			]],
			'timeperiods' => 		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
				'timeperiod_type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY])],
				'period' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:2147464800'],
				'every' =>				['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'default' => DB::getDefault('timeperiods', 'every'), 'in' => '0:'.ZBX_MAX_INT32],
					['if' => ['field' => 'timeperiod_type', 'in' => TIMEPERIOD_TYPE_MONTHLY], 'type' => API_INT32, 'in' => '1:5'],
					['if' => ['field' => 'timeperiod_type', 'in' => TIMEPERIOD_TYPE_ONETIME], 'type' => API_INT32, 'in' => '1']
				]],
				'month' =>				['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'timeperiod_type', 'in' => TIMEPERIOD_TYPE_MONTHLY], 'type' => API_INT32, 'default' => DB::getDefault('timeperiods', 'month'), 'in' => '0:4095'],
					['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY])], 'type' => API_INT32, 'in' => '0']
				]],
				'dayofweek' =>			['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'default' => DB::getDefault('timeperiods', 'dayofweek'), 'in' => '0:127'],
					['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY])], 'type' => API_INT32, 'in' => '0']
				]],
				'day' =>				['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'timeperiod_type', 'in' => TIMEPERIOD_TYPE_MONTHLY], 'type' => API_INT32, 'default' => DB::getDefault('timeperiods', 'day'), 'in' => '0:31'],
					['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY])], 'type' => API_INT32, 'in' => '0']
				]],
				'start_time' =>			['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'default' => DB::getDefault('timeperiods', 'start_time'), 'in' => '0:86340'],
					['if' => ['field' => 'timeperiod_type', 'in' => TIMEPERIOD_TYPE_ONETIME], 'type' => API_INT32, 'in' => '0']
				]],
				'start_date' =>			['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'timeperiod_type', 'in' => TIMEPERIOD_TYPE_ONETIME], 'type' => API_INT32, 'default' => DB::getDefault('timeperiods', 'start_date'), 'in' => '0:2147464800'],
					['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'in' => '0']
				]]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $maintenances, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Validate hosts & groups.
		foreach ($maintenances as $maintenance) {
			$has_groups = false;
			$has_hosts = false;

			if (array_key_exists('groups', $maintenance) && $maintenance['groups']) {
				$has_groups = true;
			}

			if (array_key_exists('hosts', $maintenance) && $maintenance['hosts']) {
				$has_hosts = true;
			}

			if (!$has_groups && !$has_hosts) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one host group or host must be selected.'));
			}
		}

		foreach ($maintenances as $i => $maintenance) {
			// Validate maintenance active interval.
			if ($maintenance['active_since'] > $maintenance['active_till']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Maintenance "%1$s" value cannot be bigger than "%2$s".', 'active_since', 'active_till')
				);
			}

			// Validate maintenance tags when maintenance_type is no data.
			if (array_key_exists('maintenance_type', $maintenance)
					&& $maintenance['maintenance_type'] == MAINTENANCE_TYPE_NODATA
					&& array_key_exists('tags', $maintenance) && $maintenance['tags']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i + 1).'/tags', _('should be empty')
				));
			}
		}

		self::checkDuplicates($maintenances);
		self::checkGroups($maintenances);
		self::checkHosts($maintenances);
	}

	/**
	 * Check for unique maintenance names.
	 *
	 * @static
	 *
	 * @param array      $maintenances
	 * @param array|null $db_maintenances
	 *
	 * @throws APIException if maintenance names are not unique.
	 */
	protected static function checkDuplicates(array $maintenances, array $db_maintenances = null): void {
		$names = [];

		foreach ($maintenances as $maintenance) {
			if ($db_maintenances === null
					|| $maintenance['name'] !== $db_maintenances[$maintenance['maintenanceid']]['name']) {
				$names[] = $maintenance['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicate = DBfetch(DBselect('SELECT m.name FROM maintenances m WHERE '.dbConditionString('m.name', $names), 1));

		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Maintenance "%1$s" already exists.', $duplicate['name'])
			);
		}
	}

	/**
	 * Update maintenances.
	 *
	 * @param array $maintenances
	 *
	 * @return array
	 */
	public function update(array $maintenances) {
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

		self::updateGroups($maintenances, __FUNCTION__, $db_maintenances);
		self::updateHosts($maintenances, __FUNCTION__, $db_maintenances);
		self::updateTags($maintenances, __FUNCTION__, $db_maintenances);
		self::updateTimeperiods($maintenances, __FUNCTION__, $db_maintenances);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_MAINTENANCE, $maintenances, $db_maintenances);

		return ['maintenanceids' => array_column($maintenances, 'maintenanceid')];
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array      $maintenances
	 * @param array|null $db_maintenances
	 *
	 * @throws APIException if no permissions to object, it does no exists or the input is invalid.
	 */
	protected function validateUpdate(array &$maintenances, array &$db_maintenances = null): void {
		$api_input_rules =		['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['maintenanceid'], ['name']], 'fields' => [
			'maintenanceid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('maintenances', 'name')],
			'maintenance_type' =>	['type' => API_INT32, 'in' => implode(',', [MAINTENANCE_TYPE_NORMAL, MAINTENANCE_TYPE_NODATA])],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('maintenances', 'description')],
			'active_since' =>		['type' => API_INT32, 'in' => '0:2147464800'],
			'active_till' =>		['type' => API_INT32, 'in' => '0:2147464800'],
			'tags_evaltype' =>		['type' => API_INT32, 'in' => implode(',', [MAINTENANCE_TAG_EVAL_TYPE_AND_OR, MAINTENANCE_TAG_EVAL_TYPE_OR])],
			'groupids' =>			['type' => API_IDS, 'flags' => API_NORMALIZE | API_DEPRECATED, 'uniq' => true],
			'hostids' =>			['type' => API_IDS, 'flags' => API_NORMALIZE | API_DEPRECATED, 'uniq' => true],
			'groups' =>				['type' => API_OBJECTS, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'hosts' =>				['type' => API_OBJECTS, 'uniq' => [['hostid']], 'fields' => [
				'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>				['type' => API_OBJECTS, 'uniq' => [['tag', 'operator', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('maintenance_tag', 'tag')],
				'operator' =>			['type' => API_INT32, 'default' => DB::getDefault('maintenance_tag', 'operator'), 'in' => implode(',', [MAINTENANCE_TAG_OPERATOR_EQUAL, MAINTENANCE_TAG_OPERATOR_LIKE])],
				'value' =>				['type' => API_STRING_UTF8, 'default' => DB::getDefault('maintenance_tag', 'value'), 'length' => DB::getFieldLength('maintenance_tag', 'value')]
			]],
			'timeperiods' => 		['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['timeperiodid']], 'fields' => [
				'timeperiodid' =>		['type' => API_ID],
				'timeperiod_type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY])],
				'period' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:2147464800'],
				'every' =>				['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'default' => DB::getDefault('timeperiods', 'every'), 'in' => '0:'.ZBX_MAX_INT32],
					['if' => ['field' => 'timeperiod_type', 'in' => TIMEPERIOD_TYPE_MONTHLY], 'type' => API_INT32, 'in' => '1:5'],
					['if' => ['field' => 'timeperiod_type', 'in' => TIMEPERIOD_TYPE_ONETIME], 'type' => API_INT32, 'in' => '1']
				]],
				'month' =>				['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'timeperiod_type', 'in' => TIMEPERIOD_TYPE_MONTHLY], 'type' => API_INT32, 'default' => DB::getDefault('timeperiods', 'month'), 'in' => '0:4095'],
					['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY])], 'type' => API_INT32, 'in' => '0']
				]],
				'dayofweek' =>			['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'default' => DB::getDefault('timeperiods', 'dayofweek'), 'in' => '0:127'],
					['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY])], 'type' => API_INT32, 'in' => '0']
				]],
				'day' =>				['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'timeperiod_type', 'in' => TIMEPERIOD_TYPE_MONTHLY], 'type' => API_INT32, 'default' => DB::getDefault('timeperiods', 'day'), 'in' => '0:31'],
					['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_ONETIME, TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY])], 'type' => API_INT32, 'in' => '0']
				]],
				'start_time' =>			['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'default' => DB::getDefault('timeperiods', 'start_time'), 'in' => '0:86340'],
					['if' => ['field' => 'timeperiod_type', 'in' => TIMEPERIOD_TYPE_ONETIME], 'type' => API_INT32, 'in' => '0']
				]],
				'start_date' =>			['type' => API_MULTIPLE, 'rules' => [
					['if' => ['field' => 'timeperiod_type', 'in' => TIMEPERIOD_TYPE_ONETIME], 'type' => API_INT32, 'default' => DB::getDefault('timeperiods', 'start_date'), 'in' => '0:2147464800'],
					['if' => ['field' => 'timeperiod_type', 'in' => implode(',', [TIMEPERIOD_TYPE_DAILY, TIMEPERIOD_TYPE_WEEKLY, TIMEPERIOD_TYPE_MONTHLY])], 'type' => API_INT32, 'in' => '0']
				]]
			]]
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

		self::addAffectedObjects($maintenances, $db_maintenances);

		// Validate hosts & groups.
		foreach ($maintenances as $maintenance) {
			$has_groups = false;
			$has_hosts = false;

			if (array_key_exists('groups', $maintenance)) {
				if ($maintenance['groups']) {
					$has_groups = true;
				}
			}
			else {
				$has_groups = (count($db_maintenances[$maintenance['maintenanceid']]['groups']) > 0);
			}

			if (array_key_exists('hosts', $maintenance)) {
				if ($maintenance['hosts']) {
					$has_hosts = true;
				}
			}
			else {
				$has_hosts = (count($db_maintenances[$maintenance['maintenanceid']]['hosts']) > 0);
			}

			if (!$has_groups && !$has_hosts) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one host group or host must be selected.'));
			}
		}

		foreach ($maintenances as $i => $maintenance) {
			// Validate maintenance active interval.
			$active_since = array_key_exists('active_since', $maintenance)
				? $maintenance['active_since']
				: $db_maintenances[$maintenance['maintenanceid']]['active_since'];

			$active_till = array_key_exists('active_till', $maintenance)
				? $maintenance['active_till']
				: $db_maintenances[$maintenance['maintenanceid']]['active_till'];

			if ($active_since > $active_till) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Maintenance "%1$s" value cannot be bigger than "%2$s".', 'active_since', 'active_till')
				);
			}

			// Validate maintenance tags when maintenance_type is no data.
			$maintenance_type = array_key_exists('maintenance_type', $maintenance)
				? $maintenance['maintenance_type']
				: $db_maintenances[$maintenance['maintenanceid']]['maintenance_type'];
			if ($maintenance_type == MAINTENANCE_TYPE_NODATA && array_key_exists('tags', $maintenance)
					&& $maintenance['tags']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i + 1).'/tags', _('should be empty')
				));
			}
		}

		self::checkDuplicates($maintenances, $db_maintenances);
		self::checkGroups($maintenances);
		self::checkHosts($maintenances);

		// FIXME: Delete this code after ZBXNEXT-6889 merged to master.
		foreach ($maintenances as $maintenance) {
			if (!array_key_exists('groups', $maintenance)) {
				unset($db_maintenances[$maintenance['maintenanceid']]['groups']);
			}

			if (!array_key_exists('hosts', $maintenance)) {
				unset($db_maintenances[$maintenance['maintenanceid']]['hosts']);
			}
		}
	}

	/**
	 * Delete Maintenances.
	 *
	 * @param array $maintenanceids
	 *
	 * @return array
	 */
	public function delete(array $maintenanceids) {
		$this->validateDelete($maintenanceids, $db_maintenances);

		$timeperiodids = [];
		$db_timeperiods = DBselect(
			'SELECT DISTINCT tp.timeperiodid'.
			' FROM timeperiods tp,maintenances_windows mw'.
			' WHERE '.dbConditionInt('mw.maintenanceid', $maintenanceids).
				' AND tp.timeperiodid=mw.timeperiodid'
		);
		while ($db_timeperiod = DBfetch($db_timeperiods)) {
			$timeperiodids[] = $db_timeperiod['timeperiodid'];
		}

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

		$maintenanceids_condition = ['maintenanceid' => $maintenanceids];

		DB::delete('timeperiods', ['timeperiodid' => $timeperiodids]);
		DB::delete('maintenances_windows', $maintenanceids_condition);
		DB::delete('maintenances_hosts', $maintenanceids_condition);
		DB::delete('maintenances_groups', $maintenanceids_condition);
		DB::delete('maintenances', $maintenanceids_condition);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_MAINTENANCE, $db_maintenances);

		return ['maintenanceids' => $maintenanceids];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array      $maintenanceids
	 * @param array|null $db_maintenances
	 *
	 * @throws APIException if no permissions to object, it does no exists or the input is invalid.
	 */
	private function validateDelete(array $maintenanceids, array &$db_maintenances = null): void {
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
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// selectGroups
		if ($options['selectGroups'] !== null && $options['selectGroups'] != API_OUTPUT_COUNT) {
			$groups = [];
			$relationMap = $this->createRelationMap($result, 'maintenanceid', 'groupid', 'maintenances_groups');
			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$groups = API::HostGroup()->get([
					'output' => $options['selectGroups'],
					'hostgroupids' => $related_ids,
					'preservekeys' => true
				]);
			}

			$result = $relationMap->mapMany($result, $groups, 'groups');
		}

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
			$tags = $this->unsetExtraFields($tags, ['maintenancetagid', 'maintenanceid'], []);
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
			$result = $relationMap->mapMany($result, $timeperiods, 'timeperiods');
		}

		return $result;
	}

	/**
	 * Check for valid groups.
	 *
	 * @static
	 *
	 * @param array $maintenances
	 *
	 * @throws APIException if groups are not valid.
	 */
	private static function checkGroups(array $maintenances): void {
		$groupids = [];
		foreach ($maintenances as $maintenance) {
			if (array_key_exists('groups', $maintenance) && $maintenance['groups']) {
				foreach ($maintenance['groups'] as $group) {
					$groupids[$group['groupid']] = true;
				}
			}
		}

		if ($groupids) {
			$groups_count = API::HostGroup()->get([
				'countOutput' => true,
				'groupids' => array_keys($groupids),
				'editable' => true
			]);

			if ($groups_count != count($groupids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Check for valid hosts.
	 *
	 * @static
	 *
	 * @param array $maintenances
	 *
	 * @throws APIException if hosts are not valid.
	 */
	private static function checkHosts(array $maintenances): void {
		$hostids = [];
		foreach ($maintenances as $maintenance) {
			if (array_key_exists('hosts', $maintenance) && $maintenance['hosts']) {
				foreach ($maintenance['hosts'] as $host) {
					$hostids[$host['hostid']] = true;
				}
			}
		}

		if ($hostids) {
			$hosts_count = API::Host()->get([
				'countOutput' => true,
				'hostids' => array_keys($hostids),
				'editable' => true
			]);

			if ($hosts_count != count($hostids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Update table "maintenances_groups".
	 *
	 * @static
	 *
	 * @param array      $maintenances
	 * @param string     $method
	 * @param array|null $db_maintenances
	 */
	private static function updateGroups(array &$maintenances, string $method, array $db_maintenances = null): void {
		$ins_groups = [];
		$del_groupids = [];

		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('groups', $maintenance)) {
				continue;
			}

			$maintenanceid = $maintenance['maintenanceid'];

			$db_groups = ($method === 'update')
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

		if ($ins_groups) {
			$maintenance_groupids = DB::insertBatch('maintenances_groups', $ins_groups);
		}

		if ($del_groupids) {
			DB::delete('maintenances_groups', ['maintenance_groupid' => $del_groupids]);
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
	 * @static
	 *
	 * @param array      $maintenances
	 * @param string     $method
	 * @param array|null $db_maintenances
	 */
	private static function updateHosts(array &$maintenances, string $method, array $db_maintenances = null): void {
		$ins_hosts = [];
		$del_hostids = [];

		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('hosts', $maintenance)) {
				continue;
			}

			$maintenanceid = $maintenance['maintenanceid'];

			$db_hosts = ($method === 'update')
				? array_column($db_maintenances[$maintenanceid]['hosts'], null, 'hostid')
				: [];

			foreach ($maintenance['hosts'] as &$host) {
				if (array_key_exists($host['hostid'], $db_hosts)) {
					$host['maintenance_hostid'] = $db_hosts[$host['hostid']]['maintenance_hostid'];
					unset($db_hosts[$host['hostid']]);
				}
				else {
					$ins_hosts[] = [
						'maintenanceid' => $maintenanceid,
						'hostid' => $host['hostid']
					];
				}
			}
			unset($host);

			$del_hostids = array_merge($del_hostids, array_column($db_hosts, 'maintenance_hostid'));
		}
		unset($maintenance);

		if ($ins_hosts) {
			$maintenance_hostids = DB::insertBatch('maintenances_hosts', $ins_hosts);
		}

		if ($del_hostids) {
			DB::delete('maintenances_hosts', ['maintenance_hostid' => $del_hostids]);
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
	 * Update table "maintenance_tag".
	 *
	 * @static
	 *
	 * @param array      $maintenances
	 * @param string     $method
	 * @param array|null $db_maintenances
	 */
	private static function updateTags(array &$maintenances, string $method, array $db_maintenances = null): void {
		$ins_tags = [];
		$del_maintenancetagids = [];

		foreach ($maintenances as &$maintenance) {
			$maintenanceid = $maintenance['maintenanceid'];

			$maintenance_type = array_key_exists('maintenance_type', $maintenance)
				? $maintenance['maintenance_type']
				: $db_maintenances[$maintenanceid]['maintenance_type'];

			if ($maintenance_type == MAINTENANCE_TYPE_NODATA && $method === 'update') {
				if (array_key_exists('tags', $db_maintenances[$maintenanceid])) {
					$del_maintenancetagids = array_merge($del_maintenancetagids,
						array_column($db_maintenances[$maintenanceid]['tags'], 'maintenancetagid')
					);
				}
			}

			if (!array_key_exists('tags', $maintenance)) {
				continue;
			}

			$db_maintenancetagid_by_tag_operator_value = [];
			$db_tags = ($method === 'update')
				? $db_maintenances[$maintenanceid]['tags']
				: [];

			foreach ($db_tags as $db_tag) {
				$db_maintenancetagid_by_tag_operator_value[$db_tag['tag']][$db_tag['operator']][$db_tag['value']] = $db_tag['maintenancetagid'];
			}

			foreach ($maintenance['tags'] as &$tag_row) {
				$tag = $tag_row['tag'];
				$operator = $tag_row['operator'];
				$value = $tag_row['value'];


				if (array_key_exists($tag, $db_maintenancetagid_by_tag_operator_value)
						&& array_key_exists($operator, $db_maintenancetagid_by_tag_operator_value[$tag])
						&& array_key_exists($value, $db_maintenancetagid_by_tag_operator_value[$tag][$operator])) {
					$maintenancetagid = $db_maintenancetagid_by_tag_operator_value[$tag][$operator][$value];
					unset($db_tags[$maintenancetagid]);

					$tag_row['maintenancetagid'] = $maintenancetagid;
				}
				else {
					$ins_tags[] = ['maintenanceid' => $maintenanceid] + $tag_row;
				}
			}
			unset($tag_row);

			$del_maintenancetagids = array_merge($del_maintenancetagids, array_column($db_tags, 'maintenancetagid'));
		}
		unset($maintenance);

		if ($ins_tags) {
			$maintenancetagids = DB::insert('maintenance_tag', $ins_tags);
		}

		if ($del_maintenancetagids) {
			DB::delete('maintenance_tag', ['maintenancetagid' => $del_maintenancetagids]);
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
	 * Update tables "periods" and "maintenances_windows".
	 *
	 * @static
	 *
	 * @param array      $maintenances
	 * @param string     $method
	 * @param array|null $db_maintenances
	 */
	private static function updateTimeperiods(array &$maintenances, string $method, array $db_maintenances = null
				): void {
		$timeperiods = [];
		$ins_windows = [];
		$ins_timeperiods = [];
		$upd_timeperiods = [];
		$del_timeperiodids = [];

		foreach ($maintenances as &$maintenance) {
			if (!array_key_exists('timeperiods', $maintenance)) {
				continue;
			}

			$maintenanceid = $maintenance['maintenanceid'];

			$db_timeperiods = ($method === 'update')
				? $db_maintenances[$maintenanceid]['timeperiods']
				: [];

			foreach ($maintenance['timeperiods'] as &$timeperiod) {
				if (array_key_exists('timeperiodid', $timeperiod)
						&& array_key_exists($timeperiod['timeperiodid'], $db_timeperiods)) {
					$db_timeperiod = $db_timeperiods[$timeperiod['timeperiodid']];
					$upd_timeperiod = DB::getUpdatedValues('timeperiods', $timeperiod, $db_timeperiod);

					if ($upd_timeperiod) {
						$upd_timeperiods[] = [
							'values' => $upd_timeperiod,
							'where' => ['timeperiodid' => $timeperiod['timeperiodid']]
						];
					}

					unset($db_timeperiods[$timeperiod['timeperiodid']]);
				}
				else {
					unset($timeperiod['timeperiodid']);

					$ins_timeperiods[] = $timeperiod;
					$timeperiods[] = $maintenanceid;
				}
			}
			unset($timeperiod);

			$del_timeperiodids = array_merge($del_timeperiodids, array_column($db_timeperiods, 'timeperiodid'));
		}
		unset($maintenance);

		$timeperiodids = [];
		if ($ins_timeperiods) {
			$timeperiodids = DB::insert('timeperiods', $ins_timeperiods);
		}

		foreach ($timeperiods as $timeperiod_index => $maintenanceid) {
			$ins_windows[] = [
				'timeperiodid' => $timeperiodids[$timeperiod_index],
				'maintenanceid' => $maintenanceid
			];
		}

		if ($ins_windows) {
			DB::insertBatch('maintenances_windows', $ins_windows);
		}

		if ($upd_timeperiods) {
			DB::update('timeperiods', $upd_timeperiods);
		}

		if ($del_timeperiodids) {
			DB::delete('maintenances_windows', ['timeperiodid' => $del_timeperiodids]);
			DB::delete('timeperiods', ['timeperiodid' => $del_timeperiodids]);
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
	 * Add the existing groups, hosts, tags and timeperiods to $db_maintenances whether these are affected by the
	 * update.
	 *
	 * @static
	 *
	 * @param array $maintenances
	 * @param array $db_maintenances
	 */
	private static function addAffectedObjects(array $maintenances, array &$db_maintenances): void {
		$maintenanceids = ['groups' => [] , 'hosts' => [], 'timeperiods' => [], 'tags' => []];

		foreach ($maintenances as $maintenance) {
			$maintenanceids['groups'][] = $maintenance['maintenanceid'];
			$db_maintenances[$maintenance['maintenanceid']]['groups'] = [];

			$maintenanceids['hosts'][] = $maintenance['maintenanceid'];
			$db_maintenances[$maintenance['maintenanceid']]['hosts'] = [];

			if (array_key_exists('tags', $maintenance)) {
				$maintenanceids['tags'][] = $maintenance['maintenanceid'];
				$db_maintenances[$maintenance['maintenanceid']]['tags'] = [];
			}

			if (array_key_exists('timeperiods', $maintenance)) {
				$maintenanceids['timeperiods'][] = $maintenance['maintenanceid'];
				$db_maintenances[$maintenance['maintenanceid']]['timeperiods'] = [];
			}
		}

		if ($maintenanceids['groups']) {
			$options = [
				'output' => ['maintenance_groupid', 'maintenanceid', 'groupid'],
				'filter' => ['maintenanceid' => $maintenanceids['groups']]
			];
			$db_groups = DBselect(DB::makeSql('maintenances_groups', $options));

			while ($db_group = DBfetch($db_groups)) {
				$db_maintenances[$db_group['maintenanceid']]['groups'][$db_group['maintenance_groupid']] = [
					'maintenance_groupid' => $db_group['maintenance_groupid'],
					'groupid' => $db_group['groupid']
				];
			}
		}

		if ($maintenanceids['hosts']) {
			$options = [
				'output' => ['maintenance_hostid', 'maintenanceid', 'hostid'],
				'filter' => ['maintenanceid' => $maintenanceids['hosts']]
			];
			$db_hosts = DBselect(DB::makeSql('maintenances_hosts', $options));

			while ($db_host = DBfetch($db_hosts)) {
				$db_maintenances[$db_host['maintenanceid']]['hosts'][$db_host['maintenance_hostid']] = [
					'maintenance_hostid' => $db_host['maintenance_hostid'],
					'hostid' => $db_host['hostid']
				];
			}
		}

		if ($maintenanceids['tags']) {
			$options = [
				'output' => ['maintenancetagid', 'maintenanceid', 'tag', 'operator', 'value'],
				'filter' => ['maintenanceid' => $maintenanceids['tags']]
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

		if ($maintenanceids['timeperiods']) {
			$db_timeperiods = DBselect('SELECT w.maintenanceid,t.timeperiodid,t.timeperiod_type,t.every,t.month,t.dayofweek,t.day,t.start_time,t.period,t.start_date FROM maintenances_windows w LEFT JOIN timeperiods t ON t.timeperiodid = w.timeperiodid WHERE '.dbConditionId('w.maintenanceid', $maintenanceids['timeperiods']));

			while ($db_timeperiod = DBfetch($db_timeperiods)) {
				$db_maintenances[$db_timeperiod['maintenanceid']]['timeperiods'][$db_timeperiod['timeperiodid']] = [
					'timeperiodid' => $db_timeperiod['timeperiodid'],
					'timeperiod_type' => $db_timeperiod['timeperiod_type'],
					'every' => $db_timeperiod['every'],
					'month' => $db_timeperiod['month'],
					'dayofweek' => $db_timeperiod['dayofweek'],
					'day' => $db_timeperiod['day'],
					'start_time' => $db_timeperiod['start_time'],
					'period' => $db_timeperiod['period'],
					'start_date' => $db_timeperiod['start_date']
				];
			}
		}
	}
}
