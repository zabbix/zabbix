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
 * Class containing methods for operations with maintenances.
 */
class CMaintenance extends CApiService {

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
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

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
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'filter'					=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectGroups'				=> null,
			'selectHosts'				=> null,
			'selectTimeperiods'			=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		$maintenanceids = [];
		if ($userType == USER_TYPE_SUPER_ADMIN || $options['nopermissions']) {
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

			$userGroups = getUserGroupsByUserId($userid);

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
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($maintenance = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
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

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * Add maintenances.
	 *
	 * @param array $maintenances
	 *
	 * @return boolean
	 */
	public function create(array $maintenances) {
		$maintenances = zbx_toArray($maintenances);
		if (self::$userData['type'] == USER_TYPE_ZABBIX_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$hostids = [];
		$groupids = [];
		foreach ($maintenances as $maintenance) {
			$hostids = array_merge($hostids, $maintenance['hostids']);
			$groupids = array_merge($groupids, $maintenance['groupids']);
		}

		// validate hosts & groups
		if (empty($hostids) && empty($groupids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one host or group should be selected.'));
		}

		// hosts permissions
		$options = [
			'hostids' => $hostids,
			'editable' => true,
			'output' => ['hostid'],
			'preservekeys' => true
		];
		$updHosts = API::Host()->get($options);
		foreach ($hostids as $hostid) {
			if (!isset($updHosts[$hostid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}
		// groups permissions
		$options = [
			'groupids' => $groupids,
			'editable' => true,
			'output' => ['groupid'],
			'preservekeys' => true
		];
		$updGroups = API::HostGroup()->get($options);
		foreach ($groupids as $groupid) {
			if (!isset($updGroups[$groupid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$this->removeSecondsFromTimes($maintenances);

		$tid = 0;
		$insert = [];
		$timeperiods = [];
		$insertTimeperiods = [];
		$now = time();
		$now -= $now % SEC_PER_MIN;

		// check fields
		foreach ($maintenances as $maintenance) {
			$dbFields = [
				'name' => null,
				'active_since' => $now,
				'active_till' => $now + SEC_PER_DAY
			];

			if (!check_db_fields($dbFields, $maintenance)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameters for maintenance.'));
			}
		}

		$collectionValidator = new CCollectionValidator([
			'uniqueField' => 'name',
			'messageDuplicate' => _('Maintenance "%1$s" already exists.')
		]);
		$this->checkValidator($maintenances, $collectionValidator);

		// validate if maintenance name already exists
		$dbMaintenances = $this->get([
			'output' => ['name'],
			'filter' => ['name' => zbx_objectValues($maintenances, 'name')],
			'nopermissions' => true,
			'limit' => 1
		]);

		if ($dbMaintenances) {
			$dbMaintenance = reset($dbMaintenances);
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Maintenance "%1$s" already exists.', $dbMaintenance['name']));
		}

		foreach ($maintenances as $mnum => $maintenance) {
			// validate maintenance active since
			if (!validateUnixTime($maintenance['active_since'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('"%s" must be between 1970.01.01 and 2038.01.18.', _('Active since')));
			}

			// validate maintenance active till
			if (!validateUnixTime($maintenance['active_till'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('"%s" must be between 1970.01.01 and 2038.01.18.', _('Active till')));
			}

			// validate maintenance active interval
			if ($maintenance['active_since'] > $maintenance['active_till']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Maintenance "Active since" value cannot be bigger than "Active till".'));
			}

			// validate timeperiods
			if (!array_key_exists('timeperiods', $maintenance) || !is_array($maintenance['timeperiods'])
					|| !$maintenance['timeperiods']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one maintenance period must be created.'));
			}

			foreach ($maintenance['timeperiods'] as $timeperiod) {
				if (!is_array($timeperiod)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one maintenance period must be created.'));
				}

				$dbFields = [
					'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
					'period' => SEC_PER_HOUR,
					'start_date' =>	$now
				];
				check_db_fields($dbFields, $timeperiod);

				$tid++;
				$insertTimeperiods[$tid] = $timeperiod;
				$timeperiods[$tid] = $mnum;
			}

			$insert[$mnum] = $maintenance;
		}
		$maintenanceids = DB::insert('maintenances', $insert);
		$timeperiodids = DB::insert('timeperiods', $insertTimeperiods);

		$insertWindows = [];
		foreach ($timeperiods as $tid => $mnum) {
			$insertWindows[] = [
				'timeperiodid' => $timeperiodids[$tid],
				'maintenanceid' => $maintenanceids[$mnum]
			];
		}
		DB::insert('maintenances_windows', $insertWindows);

		$insertHosts = [];
		$insertGroups = [];
		foreach ($maintenances as $mnum => $maintenance) {
			foreach ($maintenance['hostids'] as $hostid) {
				$insertHosts[] = [
					'hostid' => $hostid,
					'maintenanceid' => $maintenanceids[$mnum]
				];
			}
			foreach ($maintenance['groupids'] as $groupid) {
				$insertGroups[] = [
					'groupid' => $groupid,
					'maintenanceid' => $maintenanceids[$mnum]
				];
			}

			add_audit_details(AUDIT_ACTION_ADD, AUDIT_RESOURCE_MAINTENANCE, $maintenanceids[$mnum],
				$maintenance['name'], null
			);
		}
		DB::insert('maintenances_hosts', $insertHosts);
		DB::insert('maintenances_groups', $insertGroups);

		return ['maintenanceids' => $maintenanceids];
	}

	/**
	 * Update maintenances.
	 *
	 * @param array $maintenances
	 *
	 * @return boolean
	 */
	public function update(array $maintenances) {
		$maintenances = zbx_toArray($maintenances);
		$maintenanceids = zbx_objectValues($maintenances, 'maintenanceid');

		// validate maintenance permissions
		if (self::$userData['type'] == USER_TYPE_ZABBIX_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$updMaintenances = $this->get([
			'maintenanceids' => zbx_objectValues($maintenances, 'maintenanceid'),
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'selectGroups' => ['groupid'],
			'selectHosts' => ['hostid'],
			'selectTimeperiods' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		]);

		$maintenanceNamesChanged = [];
		foreach ($maintenances as $maintenance) {
			if (!isset($updMaintenances[$maintenance['maintenanceid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _(
					'No permissions to referred object or it does not exist!'
				));
			}

			if (isset($maintenance['name']) && !zbx_empty($maintenance['name'])
					&& $updMaintenances[$maintenance['maintenanceid']]['name'] !== $maintenance['name']) {
				if (isset($maintenanceNamesChanged[$maintenance['name']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Maintenance "%1$s" already exists.',
						$maintenance['name']
					));
				}
				else {
					$maintenanceNamesChanged[$maintenance['name']] = $maintenance['name'];
				}
			}
		}

		// check if maintenance already exists
		if ($maintenanceNamesChanged) {
			$dbMaintenances = $this->get([
				'output' => ['name'],
				'filter' => ['name' => $maintenanceNamesChanged],
				'nopermissions' => true,
				'limit' => 1
			]);

			if ($dbMaintenances) {
				$dbMaintenance = reset($dbMaintenances);
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Maintenance "%1$s" already exists.',
					$dbMaintenance['name']
				));
			}
		}

		$hostids = [];
		$groupids = [];

		foreach ($maintenances as $maintenance) {
			// validate maintenance active since
			if (!validateUnixTime($maintenance['active_since'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('"%s" must be between 1970.01.01 and 2038.01.18.', _('Active since')));
			}

			// validate maintenance active till
			if (!validateUnixTime($maintenance['active_till'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('"%s" must be between 1970.01.01 and 2038.01.18.', _('Active till')));
			}

			// validate maintenance active interval
			if ($maintenance['active_since'] > $maintenance['active_till']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Maintenance "Active since" value cannot be bigger than "Active till".'));
			}

			// validate timeperiods
			if (!array_key_exists('timeperiods', $maintenance) || !is_array($maintenance['timeperiods'])
					|| !$maintenance['timeperiods']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one maintenance period must be created.'));
			}

			foreach ($maintenance['timeperiods'] as $timeperiod) {
				if (!is_array($timeperiod)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one maintenance period must be created.'));
				}
			}

			$hostids = array_merge($hostids, $maintenance['hostids']);
			$groupids = array_merge($groupids, $maintenance['groupids']);
		}

		// validate hosts & groups
		if (empty($hostids) && empty($groupids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one host or group should be selected.'));
		}

		// validate hosts permissions
		$options = [
			'hostids' => $hostids,
			'editable' => true,
			'output' => ['hostid'],
			'preservekeys' => true
		];
		$updHosts = API::Host()->get($options);
		foreach ($hostids as $hostid) {
			if (!isset($updHosts[$hostid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}
		// validate groups permissions
		$options = [
			'groupids' => $groupids,
			'editable' => true,
			'output' => ['groupid'],
			'preservekeys' => true
		];
		$updGroups = API::HostGroup()->get($options);
		foreach ($groupids as $groupid) {
			if (!isset($updGroups[$groupid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$this->removeSecondsFromTimes($maintenances);

		$update = [];
		foreach ($maintenances as $mnum => $maintenance) {
			$dbFields = [
				'maintenanceid' => null
			];

			// validate fields
			if (!check_db_fields($dbFields, $maintenance)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameters for maintenance.'));
			}

			$update[$mnum] = [
				'values' => $maintenance,
				'where' => ['maintenanceid' => $maintenance['maintenanceid']]
			];

			// update time periods
			$this->replaceTimePeriods($updMaintenances[$maintenance['maintenanceid']], $maintenance);
		}
		DB::update('maintenances', $update);

		// some of the hosts and groups bound to maintenance must be deleted, other inserted and others left alone
		$insertHosts = [];
		$insertGroups = [];

		foreach ($maintenances as $maintenance) {
			// putting apart those host<->maintenance connections that should be inserted, deleted and not changed
			// $hostDiff['first'] - new hosts, that should be inserted
			// $hostDiff['second'] - hosts, that should be deleted
			// $hostDiff['both'] - hosts, that should not be touched
			$hostDiff = zbx_array_diff(
				zbx_toObject($maintenance['hostids'], 'hostid'),
				$updMaintenances[$maintenance['maintenanceid']]['hosts'],
				'hostid'
			);

			foreach ($hostDiff['first'] as $host) {
				$insertHosts[] = [
					'hostid' => $host['hostid'],
					'maintenanceid' => $maintenance['maintenanceid']
				];
			}
			foreach ($hostDiff['second'] as $host) {
				$deleteHosts = [
					'hostid' => $host['hostid'],
					'maintenanceid' => $maintenance['maintenanceid']
				];
				DB::delete('maintenances_hosts', $deleteHosts);
			}

			// now the same with the groups
			$groupDiff = zbx_array_diff(
				zbx_toObject($maintenance['groupids'], 'groupid'),
				$updMaintenances[$maintenance['maintenanceid']]['groups'],
				'groupid'
			);

			foreach ($groupDiff['first'] as $group) {
				$insertGroups[] = [
					'groupid' => $group['groupid'],
					'maintenanceid' => $maintenance['maintenanceid']
				];
			}
			foreach ($groupDiff['second'] as $group) {
				$deleteGroups = [
					'groupid' => $group['groupid'],
					'maintenanceid' => $maintenance['maintenanceid']
				];
				DB::delete('maintenances_groups', $deleteGroups);
			}

			add_audit_ext(
				AUDIT_ACTION_UPDATE,
				AUDIT_RESOURCE_MAINTENANCE,
				$maintenance['maintenanceid'],
				array_key_exists('name', $maintenance)
					? $maintenance['name']
					: $updMaintenances[$maintenance['maintenanceid']]['name'],
				'maintenances',
				$updMaintenances[$maintenance['maintenanceid']],
				$maintenance
			);
		}

		DB::insert('maintenances_hosts', $insertHosts);
		DB::insert('maintenances_groups', $insertGroups);

		return ['maintenanceids' => $maintenanceids];
	}

	/**
	 * Delete Maintenances.
	 *
	 * @param array $maintenanceids
	 *
	 * @return array
	 */
	public function delete(array $maintenanceids) {
		if (self::$userData['type'] == USER_TYPE_ZABBIX_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$options = [
			'output' => ['maintenanceid', 'name'],
			'maintenanceids' => $maintenanceids,
			'editable' => true,
			'preservekeys' => true
		];
		$maintenances = $this->get($options);
		foreach ($maintenanceids as $maintenanceid) {
			if (!isset($maintenances[$maintenanceid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}

		$timeperiodids = [];
		$dbTimeperiods = DBselect(
			'SELECT DISTINCT tp.timeperiodid'.
			' FROM timeperiods tp,maintenances_windows mw'.
			' WHERE '.dbConditionInt('mw.maintenanceid', $maintenanceids).
				' AND tp.timeperiodid=mw.timeperiodid'
		);
		while ($timeperiod = DBfetch($dbTimeperiods)) {
			$timeperiodids[] = $timeperiod['timeperiodid'];
		}

		$midCond = ['maintenanceid' => $maintenanceids];

		// remove maintenanceid from hosts table
		$options = [
			'real_hosts' => true,
			'output' => ['hostid'],
			'filter' => ['maintenanceid' => $maintenanceids]
		];
		$hosts = API::Host()->get($options);
		if (!empty($hosts)) {
			DB::update('hosts', [
				'values' => ['maintenanceid' => 0],
				'where' => ['hostid' => zbx_objectValues($hosts, 'hostid')]
			]);
		}

		DB::delete('timeperiods', ['timeperiodid' => $timeperiodids]);
		DB::delete('maintenances_windows', $midCond);
		DB::delete('maintenances_hosts', $midCond);
		DB::delete('maintenances_groups', $midCond);
		DB::delete('maintenances', $midCond);

		foreach ($maintenances as $maintenanceid => $maintenance) {
			add_audit_details(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_MAINTENANCE, $maintenanceid, $maintenance['name'],
				null
			);
		}

		return ['maintenanceids' => $maintenanceids];
	}

	/**
	 * Reset seconds to zero in maintenance time values.
	 *
	 * @param array $maintenances passed by reference
	 */
	protected function removeSecondsFromTimes(array &$maintenances) {
		foreach ($maintenances as &$maintenance) {
			if (isset($maintenance['active_since'])) {
				$maintenance['active_since'] -= $maintenance['active_since'] % SEC_PER_MIN;
			}

			if (isset($maintenance['active_till'])) {
				$maintenance['active_till'] -= $maintenance['active_till'] % SEC_PER_MIN;
			}


			if (isset($maintenance['timeperiods'])) {
				foreach ($maintenance['timeperiods'] as &$timeperiod) {
					if (isset($timeperiod['start_date'])) {
						$timeperiod['start_date'] -= $timeperiod['start_date'] % SEC_PER_MIN;
					}
				}
				unset($timeperiod);
			}
		}
		unset($maintenance);
	}

	/**
	 * Updates maintenance time periods.
	 *
	 * @param array $maintenance
	 * @param array $oldMaintenance
	 */
	protected function replaceTimePeriods(array $oldMaintenance, array $maintenance) {
		// replace time periods
		$timePeriods = DB::replace('timeperiods', $oldMaintenance['timeperiods'], $maintenance['timeperiods']);

		// link new time periods to maintenance
		$oldTimePeriods = zbx_toHash($oldMaintenance['timeperiods'], 'timeperiodid');
		$newMaintenanceWindows = [];
		foreach ($timePeriods as $tp) {
			if (!isset($oldTimePeriods[$tp['timeperiodid']])) {
				$newMaintenanceWindows[] = [
					'maintenanceid' => $maintenance['maintenanceid'],
					'timeperiodid' => $tp['timeperiodid']
				];
			}
		}
		DB::insert('maintenances_windows', $newMaintenanceWindows);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// selectGroups
		if ($options['selectGroups'] !== null && $options['selectGroups'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'maintenanceid', 'groupid', 'maintenances_groups');
			$groups = API::HostGroup()->get([
				'output' => $options['selectGroups'],
				'hostgroupids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $groups, 'groups');
		}

		// selectHosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'maintenanceid', 'hostid', 'maintenances_hosts');
			$groups = API::Host()->get([
				'output' => $options['selectHosts'],
				'hostids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $groups, 'hosts');
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
}
