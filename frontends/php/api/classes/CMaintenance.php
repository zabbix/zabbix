<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
/**
 * File containing CMaintenance class for API.
 * @package API
 */
/**
 * Class containing methods for operations with maintenances
 *
 */
class CMaintenance extends CZBXAPI {

	protected $tableName = 'maintenances';

	protected $tableAlias = 'm';

	/**
	 * Get maintenances data
	 *
	 * @param array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['triggerids']
	 * @param array $options['maintenanceids']
	 * @param boolean $options['status']
	 * @param boolean $options['editable']
	 * @param boolean $options['count']
	 * @param string $options['pattern']
	 * @param int $options['limit']
	 * @param string $options['order']
	 * @return array|int item data as array or false if error
	 */
	public function get(array $options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('maintenanceid', 'name', 'maintenance_type');

		$sqlParts = array(
			'select'	=> array('maintenance' => 'm.maintenanceid'),
			'from'		=> array('maintenances' => 'maintenances m'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
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
			'output'					=> API_OUTPUT_REFER,
			'selectGroups'				=> null,
			'selectHosts'				=> null,
			'selectTimeperiods'         => null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		$maintenanceids = array();
		if ($userType == USER_TYPE_SUPER_ADMIN || $options['nopermissions']) {
			if (!is_null($options['groupids']) || !is_null($options['hostids'])) {
				if (!is_null($options['groupids'])) {
					zbx_value2array($options['groupids']);
					$res = DBselect(
						'SELECT mmg.maintenanceid'.
						' FROM maintenances_groups mmg'.
						' WHERE '.DBcondition('mmg.groupid', $options['groupids'])
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
					$sql .= ' AND '.DBcondition('hg.groupid', $options['groupids']);
				}

				if (!is_null($options['hostids'])) {
					zbx_value2array($options['hostids']);
					$sql .= ' AND '.DBcondition('hg.hostid', $options['hostids']);
				}
				$res = DBselect($sql);
				while ($maintenance = DBfetch($res)) {
					$maintenanceids[] = $maintenance['maintenanceid'];
				}
				$sqlParts['where'][] = DBcondition('m.maintenanceid', $maintenanceids);
			}
		}
		else {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sql = 'SELECT DISTINCT m.maintenanceid'.
					' FROM maintenances m'.
					' WHERE NOT EXISTS ('.
						' SELECT mh3.maintenanceid'.
						' FROM maintenances_hosts mh3,rights r3,users_groups ug3,hosts_groups hg3'.
						' WHERE mh3.maintenanceid=m.maintenanceid'.
							' AND r3.groupid=ug3.usrgrpid'.
							' AND hg3.hostid=mh3.hostid'.
							' AND r3.id=hg3.groupid'.
							' AND ug3.userid='.$userid.
							' AND r3.permission<'.$permission.
					')'.
					' AND NOT EXISTS ('.
						'SELECT mh4.maintenanceid'.
						' FROM maintenances_hosts mh4'.
						' WHERE mh4.maintenanceid=m.maintenanceid'.
							' AND NOT EXISTS('.
								'SELECT r5.id'.
								' FROM rights r5,users_groups ug5,hosts_groups hg5'.
								' WHERE r5.groupid=ug5.usrgrpid'.
									' AND hg5.hostid=mh4.hostid'.
									' AND r5.id=hg5.groupid'.
									' AND ug5.userid='.$userid.
							')'.
					')'.
					' AND NOT EXISTS ('.
						'SELECT mg2.maintenanceid'.
						' FROM maintenances_groups mg2,rights r3,users_groups ug3'.
						' WHERE mg2.maintenanceid=m.maintenanceid'.
							' AND r3.groupid=ug3.usrgrpid'.
							' AND r3.id=mg2.groupid'.
							' AND ug3.userid='.$userid.
							' AND r3.permission<'.$permission.
					')'.
					' AND NOT EXISTS ('.
						'SELECT mg3.maintenanceid'.
						' FROM maintenances_groups mg3'.
						' WHERE mg3.maintenanceid=m.maintenanceid'.
							' AND NOT EXISTS ('.
								'SELECT r5.id'.
								' FROM rights r5,users_groups ug5,hosts_groups hg5'.
								' WHERE r5.groupid=ug5.usrgrpid'.
									' AND r5.id=mg3.groupid'.
									' AND ug5.userid='.$userid.
							')'.
					')';

			if (!is_null($options['groupids'])) {
				zbx_value2array($options['groupids']);

				$sql .= ' AND ('.
							// filtering using groups attached to maintenence
							'EXISTS ('.
								'SELECT mgf.maintenanceid'.
								' FROM maintenances_groups mgf'.
								' WHERE mgf.maintenanceid=m.maintenanceid'.
									' AND '.DBcondition('mgf.groupid', $options['groupids']).
							')'.
							// filtering by hostgroups of hosts attached to maintenance
							' OR EXISTS ('.
								' SELECT mh.maintenanceid'.
								' FROM maintenances_hosts mh,hosts_groups hg'.
								' WHERE mh.maintenanceid=m.maintenanceid'.
									' AND hg.hostid=mh.hostid'.
									' AND '.DBcondition('hg.groupid', $options['groupids']).
							')'.
						')';
			}

			if (!is_null($options['hostids'])) {
				zbx_value2array($options['hostids']);
				$sql .= ' AND EXISTS ('.
							' SELECT mh.maintenanceid'.
							' FROM maintenances_hosts mh'.
							' WHERE mh.maintenanceid=m.maintenanceid'.
								' AND '.DBcondition('mh.hostid', $options['hostids']).
							')';
			}
			$res = DBselect($sql);
			while ($maintenance = DBfetch($res)) {
				$maintenanceids[] = $maintenance['maintenanceid'];
			}
			$sqlParts['where'][] = DBcondition('m.maintenanceid', $maintenanceids);
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// groupids
		if (!is_null($options['groupids'])) {
			$options['selectGroups'] = 1;
		}

		// hostids
		if (!is_null($options['hostids'])) {
			$options['selectHosts'] = 1;
		}

		// maintenanceids
		if (!is_null($options['maintenanceids'])) {
			zbx_value2array($options['maintenanceids']);

			$sqlParts['where'][] = DBcondition('m.maintenanceid', $options['maintenanceids']);
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['maintenance'] = 'm.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('COUNT(DISTINCT m.maintenanceid) AS rowscount');

			// groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sqlParts['group'] as $key => $fields) {
					$sqlParts['select'][$key] = $fields;
				}
			}
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('maintenances m', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('maintenances m', $options, $sqlParts);
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'm');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$maintenanceids = array();

		$sqlParts['select'] = array_unique($sqlParts['select']);
		$sqlParts['from'] = array_unique($sqlParts['from']);
		$sqlParts['where'] = array_unique($sqlParts['where']);
		$sqlParts['group'] = array_unique($sqlParts['group']);
		$sqlParts['order'] = array_unique($sqlParts['order']);

		$sqlSelect = '';
		$sqlFrom = '';
		$sqlWhere = '';
		$sqlGroup = '';
		$sqlOrder = '';
		if (!empty($sqlParts['select'])) {
			$sqlSelect .= implode(',', $sqlParts['select']);
		}
		if (!empty($sqlParts['from'])) {
			$sqlFrom .= implode(',', $sqlParts['from']);
		}
		if (!empty($sqlParts['where'])) {
			$sqlWhere .= ' AND '.implode(' AND ', $sqlParts['where']);
		}
		if (!empty($sqlParts['group'])) {
			$sqlWhere .= ' GROUP BY '.implode(',', $sqlParts['group']);
		}
		if (!empty($sqlParts['order'])) {
			$sqlOrder .= ' ORDER BY '.implode(',', $sqlParts['order']);
		}
		$sqlLimit = $sqlParts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				' WHERE '.DBin_node('m.maintenanceid', $nodeids).
					$sqlWhere.
				$sqlOrder;
		$res = DBselect($sql, $sqlLimit);
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
				$maintenanceids[$maintenance['maintenanceid']] = $maintenance['maintenanceid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$maintenance['maintenanceid']] = array('maintenanceid' => $maintenance['maintenanceid']);
				}
				else {
					if (!isset($result[$maintenance['maintenanceid']])) {
						$result[$maintenance['maintenanceid']] = array();
					}

					if (!is_null($options['selectGroups']) && !isset($result[$maintenance['maintenanceid']]['groups'])) {
						$result[$maintenance['maintenanceid']]['groups'] = array();
					}
					if (!is_null($options['selectHosts']) && !isset($result[$maintenance['maintenanceid']]['hosts'])) {
						$result[$maintenance['maintenanceid']]['hosts'] = array();
					}

					// groupids
					if (isset($maintenance['groupid']) && is_null($options['selectGroups'])) {
						if (!isset($result[$maintenance['maintenanceid']]['groups'])) {
							$result[$maintenance['maintenanceid']]['groups'] = array();
						}
						$result[$maintenance['maintenanceid']]['groups'][] = array('groupid' => $maintenance['groupid']);
						unset($maintenance['groupid']);
					}

					// hostids
					if (isset($maintenance['hostid']) && is_null($options['selectHosts'])) {
						if (!isset($result[$maintenance['maintenanceid']]['hosts'])) {
							$result[$maintenance['maintenanceid']]['hosts'] = array();
						}
						$result[$maintenance['maintenanceid']]['hosts'][] = array('hostid' => $maintenance['hostid']);
						unset($maintenance['hostid']);
					}
					$result[$maintenance['maintenanceid']] += $maintenance;
				}
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
	 * Determine, whether an object already exists
	 *
	 * @param array $object
	 * @return bool
	 */
	public function exists(array $object) {
		$keyFields = array(array('maintenanceid', 'name'));

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => true,
			'limit' => 1
		);
		$objs = $this->get($options);
		return !empty($objs);
	}

	/**
	 * Add maintenances
	 *
	 * @param array $maintenances
	 * @return boolean
	 */
	public function create(array $maintenances) {
		$maintenances = zbx_toArray($maintenances);
		if (self::$userData['type'] == USER_TYPE_ZABBIX_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$hostids = array();
		$groupids = array();
		foreach ($maintenances as $maintenance) {
			$hostids = array_merge($hostids, $maintenance['hostids']);
			$groupids = array_merge($groupids, $maintenance['groupids']);
		}

		// validate hosts & groups
		if (empty($hostids) && empty($groupids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one host or group should be selected.'));
		}

		// hosts permissions
		$options = array(
			'hostids' => $hostids,
			'editable' => true,
			'output' => API_OUTPUT_SHORTEN,
			'preservekeys' => true
		);
		$updHosts = API::Host()->get($options);
		foreach ($hostids as $hostid) {
			if (!isset($updHosts[$hostid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}
		// groups permissions
		$options = array(
			'groupids' => $groupids,
			'editable' => true,
			'output' => API_OUTPUT_SHORTEN,
			'preservekeys' => true
		);
		$updGroups = API::HostGroup()->get($options);
		foreach ($groupids as $groupid) {
			if (!isset($updGroups[$groupid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$this->removeSecondsFromTimes($maintenances);

		$tid = 0;
		$insert = array();
		$timeperiods = array();
		$insertTimeperiods = array();
		$now = time();
		$now -= $now % SEC_PER_MIN;
		foreach ($maintenances as $mnum => $maintenance) {
			$dbFields = array(
				'name' => null,
				'active_since' => $now,
				'active_till' => $now + SEC_PER_DAY
			);
			if (!check_db_fields($dbFields, $maintenance)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameters for maintenance.'));
			}

			// validate if maintenance name already exists
			if ($this->exists(array('name' => $maintenance['name']))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Maintenance "%s" already exists.', $maintenance['name']));
			}

			// validate maintenance active since
			if (!validateMaxTime($maintenance['active_since'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('"%s" must be between 1970.01.01 and 2038.01.18.', _('Active since')));
			}

			// validate maintenance active till
			if (!validateMaxTime($maintenance['active_till'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('"%s" must be between 1970.01.01 and 2038.01.18.', _('Active till')));
			}

			// validate maintenance active interval
			if ($maintenance['active_since'] > $maintenance['active_till']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Maintenance "Active since" value cannot be bigger than "Active till".'));
			}

			// validate timeperiods
			if (empty($maintenance['timeperiods'][0])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one maintenance period must be created.'));
			}

			$insert[$mnum] = $maintenance;

			foreach ($maintenance['timeperiods'] as $timeperiod) {
				$dbFields = array(
					'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
					'period' => SEC_PER_HOUR,
					'start_date' =>	$now
				);
				check_db_fields($dbFields, $timeperiod);

				$tid++;
				$insertTimeperiods[$tid] = $timeperiod;
				$timeperiods[$tid] = $mnum;
			}
		}
		$maintenanceids = DB::insert('maintenances', $insert);
		$timeperiodids = DB::insert('timeperiods', $insertTimeperiods);

		$insertWindows = array();
		foreach ($timeperiods as $tid => $mnum) {
			$insertWindows[] = array(
				'timeperiodid' => $timeperiodids[$tid],
				'maintenanceid' => $maintenanceids[$mnum]
			);
		}
		DB::insert('maintenances_windows', $insertWindows);

		$insertHosts = array();
		$insertGroups = array();
		foreach ($maintenances as $mnum => $maintenance) {
			foreach ($maintenance['hostids'] as $hostid) {
				$insertHosts[] = array(
					'hostid' => $hostid,
					'maintenanceid' => $maintenanceids[$mnum]
				);
			}
			foreach ($maintenance['groupids'] as $groupid) {
				$insertGroups[] = array(
					'groupid' => $groupid,
					'maintenanceid' => $maintenanceids[$mnum]
				);
			}
		}
		DB::insert('maintenances_hosts', $insertHosts);
		DB::insert('maintenances_groups', $insertGroups);

		return array('maintenanceids' => $maintenanceids);
	}

	/**
	 * Update maintenances
	 *
	 * @param array $maintenances
	 * @return boolean
	 */
	public function update(array $maintenances) {
		$maintenances = zbx_toArray($maintenances);
		$maintenanceids = zbx_objectValues($maintenances, 'maintenanceid');

		// validate maintenance permissions
		if (self::$userData['type'] == USER_TYPE_ZABBIX_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$hostids = array();
		$groupids = array();
		$updMaintenances = $this->get(array(
			'maintenanceids' => zbx_objectValues($maintenances, 'maintenanceid'),
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'selectGroups' => API_OUTPUT_REFER,
			'selectHosts' => API_OUTPUT_REFER,
			'selectTimeperiods' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		foreach ($maintenances as $maintenance) {
			if (!isset($updMaintenances[$maintenance['maintenanceid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}

			// Checking whether a maintenance with this name already exists. First, getting all maintenances with the same name as this
			$receivedMaintenances = API::Maintenance()->get(array(
				'filter' => array('name' => $maintenance['name'])
			));

			// validate if maintenance name already exists
			foreach ($receivedMaintenances as $rMaintenance) {
				if (bccomp($rMaintenance['maintenanceid'], $maintenance['maintenanceid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Maintenance "%s" already exists.', $maintenance['name']));
				}
			}

			// validate maintenance active since
			if (!validateMaxTime($maintenance['active_since'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('"%s" must be between 1970.01.01 and 2038.01.18.', _('Active since')));
			}

			// validate maintenance active till
			if (!validateMaxTime($maintenance['active_till'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('"%s" must be between 1970.01.01 and 2038.01.18.', _('Active till')));
			}

			// validate maintenance active interval
			if ($maintenance['active_since'] > $maintenance['active_till']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Maintenance "Active since" value cannot be bigger than "Active till".'));
			}

			// validate timeperiods
			if (empty($maintenance['timeperiods'][0])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one maintenance period must be created.'));
			}

			$hostids = array_merge($hostids, $maintenance['hostids']);
			$groupids = array_merge($groupids, $maintenance['groupids']);
		}

		// validate hosts & groups
		if (empty($hostids) && empty($groupids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one host or group should be selected.'));
		}

		// validate hosts permissions
		$options = array(
			'hostids' => $hostids,
			'editable' => true,
			'output' => API_OUTPUT_SHORTEN,
			'preservekeys' => true
		);
		$updHosts = API::Host()->get($options);
		foreach ($hostids as $hostid) {
			if (!isset($updHosts[$hostid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}
		// validate groups permissions
		$options = array(
			'groupids' => $groupids,
			'editable' => true,
			'output' => API_OUTPUT_SHORTEN,
			'preservekeys' => true
		);
		$updGroups = API::HostGroup()->get($options);
		foreach ($groupids as $groupid) {
			if (!isset($updGroups[$groupid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$this->removeSecondsFromTimes($maintenances);

		$update = array();
		foreach ($maintenances as $mnum => $maintenance) {
			$dbFields = array(
				'maintenanceid' => null
			);

			// validate fields
			if (!check_db_fields($dbFields, $maintenance)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameters for maintenance.'));
			}

			$update[$mnum] = array(
				'values' => $maintenance,
				'where' => array('maintenanceid' => $maintenance['maintenanceid'])
			);

			// update time periods
			$this->replaceTimePeriods($updMaintenances[$maintenance['maintenanceid']], $maintenance);
		}
		DB::update('maintenances', $update);

		// some of the hosts and groups bound to maintenance must be deleted, other inserted and others left alone
		$insertHosts = array();
		$insertGroups = array();

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
				$insertHosts[] = array(
					'hostid' => $host['hostid'],
					'maintenanceid' => $maintenance['maintenanceid']
				);
			}
			foreach ($hostDiff['second'] as $host) {
				$deleteHosts = array(
					'hostid' => $host['hostid'],
					'maintenanceid' => $maintenance['maintenanceid']
				);
				DB::delete('maintenances_hosts', $deleteHosts);
			}

			// now the same with the groups
			$groupDiff = zbx_array_diff(
				zbx_toObject($maintenance['groupids'], 'groupid'),
				$updMaintenances[$maintenance['maintenanceid']]['groups'],
				'groupid'
			);

			foreach ($groupDiff['first'] as $group) {
				$insertGroups[] = array(
					'groupid' => $group['groupid'],
					'maintenanceid' => $maintenance['maintenanceid']
				);
			}
			foreach ($groupDiff['second'] as $group) {
				$deleteGroups = array(
					'groupid' => $group['groupid'],
					'maintenanceid' => $maintenance['maintenanceid']
				);
				DB::delete('maintenances_groups', $deleteGroups);
			}
		}

		DB::insert('maintenances_hosts', $insertHosts);
		DB::insert('maintenances_groups', $insertGroups);

		return array('maintenanceids' => $maintenanceids);
	}

	/**
	 * Delete maintenances by maintenanceids.
	 *
	 * @param array $maintenanceids
	 *
	 * @return array
	 */
	public function delete(array $maintenanceids) {
		$maintenanceids = zbx_toArray($maintenanceids);
			if (self::$userData['type'] == USER_TYPE_ZABBIX_USER) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}

			$options = array(
				'maintenanceids' => $maintenanceids,
				'editable' => true,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
			);
			$maintenances = $this->get($options);
			foreach ($maintenanceids as $maintenanceid) {
				if (!isset($maintenances[$maintenanceid])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
				}
			}

			$timeperiodids = array();
			$dbTimeperiods = DBselect(
				'SELECT DISTINCT tp.timeperiodid'.
				' FROM timeperiods tp,maintenances_windows mw'.
				' WHERE '.DBcondition('mw.maintenanceid', $maintenanceids).
					' AND tp.timeperiodid=mw.timeperiodid'
			);
			while ($timeperiod = DBfetch($dbTimeperiods)) {
				$timeperiodids[] = $timeperiod['timeperiodid'];
			}

			$midCond = array('maintenanceid' => $maintenanceids);

			// remove maintenanceid from hosts table
			$options = array(
				'real_hosts' => true,
				'output' => API_OUTPUT_SHORTEN,
				'filter' => array('maintenanceid' => $maintenanceids)
			);
			$hosts = API::Host()->get($options);
			if (!empty($hosts)) {
				DB::update('hosts', array(
					'values' => array('maintenanceid' => 0),
					'where' => array('hostid' => zbx_objectValues($hosts, 'hostid'))
				));
			}

			DB::delete('timeperiods', array('timeperiodid' => $timeperiodids));
			DB::delete('maintenances_windows', $midCond);
			DB::delete('maintenances_hosts', $midCond);
			DB::delete('maintenances_groups', $midCond);
			DB::delete('maintenances', $midCond);
			return array('maintenanceids' => $maintenanceids);
	}

	/**
	 * Reset seconds to zero in maintenace time values.
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
		$newMaintenanceWindows = array();
		foreach ($timePeriods as $tp) {
			if (!isset($oldTimePeriods[$tp['timeperiodid']])) {
				$newMaintenanceWindows[] = array(
					'maintenanceid' => $maintenance['maintenanceid'],
					'timeperiodid' => $tp['timeperiodid']
				);
			}
		}
		DB::insert('maintenances_windows', $newMaintenanceWindows);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$maintenanceIds = array_keys($result);

		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		// selectGroups
		if (is_array($options['selectGroups']) || str_in_array($options['selectGroups'], $subselectsAllowedOutputs)) {
			$objParams = array(
				'output' => $options['selectGroups'],
				'maintenanceids' => $maintenanceIds,
				'preservekeys' => true
			);
			$groups = API::HostGroup()->get($objParams);
			foreach ($groups as $group) {
				$gmaintenances = $group['maintenances'];
				unset($group['maintenances']);
				foreach ($gmaintenances as $maintenance) {
					$result[$maintenance['maintenanceid']]['groups'][] = $group;
				}
			}
		}

		// selectHosts
		if (is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselectsAllowedOutputs)) {
			$objParams = array(
				'output' => $options['selectHosts'],
				'maintenanceids' => $maintenanceIds,
				'preservekeys' => true
			);
			$hosts = API::Host()->get($objParams);
			foreach ($hosts as $host) {
				$hmaintenances = $host['maintenances'];
				unset($host['maintenances']);
				foreach ($hmaintenances as $maintenance) {
					$result[$maintenance['maintenanceid']]['hosts'][] = $host;
				}
			}
		}

		// selectTimeperiods
		if ($options['selectTimeperiods'] !== null) {
			foreach ($result as &$maintenance) {
				$maintenance['timeperiods'] = array();
			}
			unset($maintenance);

			// create the SELECT part of the query
			$sqlParts = $this->applyQueryOutputOptions('timeperiods', 'tp', array(
				'output' => $options['selectTimeperiods']
			), array('select' => array('tp.timeperiodid')));
			$query = DBSelect(
				'SELECT '.implode($sqlParts['select'], ',').',mw.maintenanceid'.
				' FROM timeperiods tp,maintenances_windows mw'.
				' WHERE '.DBcondition('mw.maintenanceid', $maintenanceIds).
					' AND tp.timeperiodid=mw.timeperiodid'
			);
			while ($tp = DBfetch($query)) {
				$refId = $tp['maintenanceid'];
				$tp = $this->unsetExtraFields('timeperiods', $tp, $options['selectTimeperiods']);
				$result[$refId]['timeperiods'][] = $tp;
			}
		}

		return $result;
	}
}
