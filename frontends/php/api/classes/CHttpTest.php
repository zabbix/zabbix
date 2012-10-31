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


class CHttpTest extends CZBXAPI {
	protected $tableName = 'httptest';
	protected $tableAlias = 'ht';

	/**
	 * Get data about web scenarios.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('httptestid', 'name');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sqlParts = array(
			'select'	=> array('httptests' => 'ht.httptestid'),
			'from'		=> array('httptest' => 'httptest ht'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'			=> null,
			'httptestids'		=> null,
			'applicationids'	=> null,
			'hostids'			=> null,
			'groupids'			=> null,
			'editable'			=> null,
			'nopermissions'	 	=> null,
			// filter
			'filter'			=> null,
			'search'			=> null,
			'searchByAny'		=> null,
			'startSearch'		=> null,
			'exludeSearch'		=> null,
			// output
			'output'			=> API_OUTPUT_REFER,
			'selectHosts'		=> null,
			'selectSteps'		=> null,
			'countOutput'		=> null,
			'groupCount'		=> null,
			'preservekeys'		=> null,
			'sortfield'			=> '',
			'sortorder'			=> '',
			'limit'				=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == $userType || $options['nopermissions']) {
		}
		else {
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['rights'] = 'rights r';
			$sqlParts['from']['applications'] = 'applications a';
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where'][] = 'a.applicationid=ht.applicationid';
			$sqlParts['where'][] = 'hg.hostid=a.hostid';
			$sqlParts['where'][] = 'r.id=hg.groupid ';
			$sqlParts['where'][] = 'r.groupid=ug.usrgrpid';
			$sqlParts['where'][] = 'ug.userid='.$userid;
			$sqlParts['where'][] = 'r.permission>='.$permission;
			$sqlParts['where'][] = 'NOT EXISTS ('.
									' SELECT hgg.groupid'.
									' FROM hosts_groups hgg,rights rr,users_groups gg'.
									' WHERE hgg.hostid=hg.hostid'.
										' AND rr.id=hgg.groupid'.
										' AND rr.groupid=gg.usrgrpid'.
										' AND gg.userid='.$userid.
										' AND rr.permission<'.$permission.')';
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// httptestids
		if (!is_null($options['httptestids'])) {
			zbx_value2array($options['httptestids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['httptestid'] = 'ht.httptestid';
			}
			$sqlParts['where']['httptestid'] = DBcondition('ht.httptestid', $options['httptestids']);
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['where']['hostid'] = DBcondition('ht.hostid', $options['hostids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hostid'] = 'ht.hostid';
			}
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['groupid'] = 'hg.groupid';
			}

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sqlParts['where'][] = 'hg.hostid=ht.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// applicationids
		if (!is_null($options['applicationids'])) {
			zbx_value2array($options['applicationids']);

			if ($options['output'] != API_OUTPUT_EXTEND) {
				$sqlParts['select']['applicationid'] = 'a.applicationid';
			}
			$sqlParts['where'][] = DBcondition('ht.applicationid', $options['applicationids']);
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['httptests'] = 'ht.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('count(ht.httptestid) as rowscount');

			// groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sqlParts['group'] as $key => $fields) {
					$sqlParts['select'][$key] = $fields;
				}
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('httptest ht', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('httptest ht', $options, $sqlParts);
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'ht');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$httpTestIds = array();

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
				' WHERE '.DBin_node('ht.httptestid', $nodeids).
					$sqlWhere.
					$sqlGroup.
					$sqlOrder;
		$res = DBselect($sql, $sqlLimit);
		while ($httpTest = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $httpTest;
				}
				else {
					$result = $httpTest['rowscount'];
				}
			}
			else {
				$httpTestIds[$httpTest['httptestid']] = $httpTest['httptestid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$httpTest['httptestid']] = array('httptestid' => $httpTest['httptestid']);
				}
				else {
					if (!isset($result[$httpTest['httptestid']])) {
						$result[$httpTest['httptestid']] = array();
					}
					if (!is_null($options['selectHosts']) && !isset($result[$httpTest['httptestid']]['hosts'])) {
						$result[$httpTest['httptestid']]['hosts'] = array();
					}
					if (!is_null($options['selectSteps']) && !isset($result[$httpTest['httptestid']]['steps'])) {
						$result[$httpTest['httptestid']]['steps'] = array();
					}

					// hostids
					if (isset($httpTest['hostid']) && is_null($options['selectHosts'])) {
						if (!isset($result[$httpTest['httptestid']]['hosts'])) {
							$result[$httpTest['httptestid']]['hosts'] = array();
						}
						$result[$httpTest['httptestid']]['hosts'][] = array('hostid' => $httpTest['hostid']);
						unset($httpTest['hostid']);
					}
					$result[$httpTest['httptestid']] += $httpTest;
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		// adding hosts
		if (!is_null($options['selectHosts']) && str_in_array($options['selectHosts'], $subselectsAllowedOutputs)) {
			$objParams = array(
				'output' => $options['selectHosts'],
				'httptestids' => $httpTestIds,
				'nopermissions' => true,
				'preservekeys' => true
			);
			$hosts = API::Host()->get($objParams);
			foreach ($hosts as $host) {
				$hwebchecks = $host['httptests'];
				unset($host['httptests']);
				foreach ($hwebchecks as $hwebcheck) {
					$result[$hwebcheck['httptestid']]['hosts'][] = $host;
				}
			}
		}

		// adding steps
		if (!is_null($options['selectSteps']) && str_in_array($options['selectSteps'], $subselectsAllowedOutputs)) {
			$dbSteps = DBselect(
				'SELECT h.*'.
				' FROM httpstep h'.
				' WHERE '.DBcondition('h.httptestid', $httpTestIds)
			);
			while ($step = DBfetch($dbSteps)) {
				$step['webstepid'] = $step['httpstepid'];
				$result[$step['httptestid']]['steps'][$step['httpstepid']] = $step;
			}
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * Create web scenario.
	 *
	 * @param $httpTests
	 *
	 * @return array
	 */
	public function create($httpTests) {
		$httpTests = zbx_toArray($httpTests);

		$this->validateCreate($httpTests);

		$httpTestManager = new CHttpTestManager();
		$httpTests = $httpTestManager->create($httpTests);

		$httpTestManager->inherit($httpTests);

		return array('httptestids' => zbx_objectValues($httpTests, 'httptestid'));
	}

	/**
	 * Update web scenario.
	 *
	 * @param $httpTests
	 *
	 * @return array
	 */
	public function update($httpTests) {
		$httpTests = zbx_toArray($httpTests);
		$this->validateUpdate($httpTests);

		$httpTestManager = new CHttpTestManager();
		$httpTestManager->update($httpTests);
		$httpTestManager->inherit($httpTests);

		return array('httptestids' => zbx_objectValues($httpTests, 'httptestid'));
	}

	/**
	 * Delete web scenario.
	 *
	 * @param $httpTestIds
	 *
	 * @return array|bool
	 */
	public function delete($httpTestIds) {
		if (empty($httpTestIds)) {
			return true;
		}
		$httpTestIds = zbx_toArray($httpTestIds);

		$delHttpTests = $this->get(array(
			'httptestids' => $httpTestIds,
			'output' => API_OUTPUT_EXTEND,
			'editable' => true,
			'selectHosts' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
		foreach ($httpTestIds as $httpTestId) {
			if (!isset($delHttpTests[$httpTestId])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$itemidsDel = array();
		$dbTestItems = DBselect(
			'SELECT hsi.itemid'.
			' FROM httptestitem hsi'.
			' WHERE '.DBcondition('hsi.httptestid', $httpTestIds)
		);
		while ($testitem = DBfetch($dbTestItems)) {
			$itemidsDel[] = $testitem['itemid'];
		}

		$dbStepItems = DBselect(
			'SELECT DISTINCT hsi.itemid'.
			' FROM httpstepitem hsi,httpstep hs'.
			' WHERE '.DBcondition('hs.httptestid', $httpTestIds).
				' AND hs.httpstepid=hsi.httpstepid'
		);
		while ($stepitem = DBfetch($dbStepItems)) {
			$itemidsDel[] = $stepitem['itemid'];
		}

		if (!empty($itemidsDel)) {
			API::Item()->delete($itemidsDel, true);
		}

		DB::delete('httptest', array('httptestid' => $httpTestIds));

		// TODO: REMOVE info
		foreach ($delHttpTests as $httpTest) {
			info(_s('Scenario "%s" deleted.', $httpTest['name']));
		}

		// TODO: REMOVE audit
		foreach ($delHttpTests as $httpTest) {
			$host = reset($httpTest['hosts']);
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCENARIO,
				_s('Scenario "%1$s" "%2$s" host "%3$s".', $httpTest['name'], $httpTest['httptestid'], $host['host']));
		}

		return array('httptestids' => $httpTestIds);
	}

	/**
	 * Validate web scenario parameters for create method.
	 *  - check if web scenario with same name already exists
	 *  - check if web scenario has at least one step
	 *
	 * @param array $httpTests
	 */
	protected function validateCreate(array $httpTests) {
		$httpTestsNames = $this->checkNames($httpTests);

		if ($name = DBfetch(DBselect('SELECT ht.name FROM httptest ht WHERE '.DBcondition('ht.name', $httpTestsNames), 1))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Scenario "%s" already exists.', $name['name']));
		}

		foreach ($httpTests as $httpTest) {
			$nameExists = DBfetch(DBselect('SELECT ht.name FROM httptest ht'.
				' WHERE ht.name='.zbx_dbstr($httpTest['name']).
					' AND ht.hostid='.zbx_dbstr($httpTest['hostid']), 1));
			if ($nameExists) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Scenario "%1$s" already exists.', $nameExists['name']));
			}

			if (empty($httpTest['steps'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Webcheck must have at least one step.'));
			}
			$this->checkSteps($httpTest['steps']);
		}
	}

	/**
	 * Validate web scenario parameters for update method.
	 *  - check permissions
	 *  - check if web scenario with same name already exists
	 *  - check that each web scenario object has httptestid defined
	 *
	 * @param array $httpTests
	 */
	protected function validateUpdate(array $httpTests) {
		$httpTestIds = zbx_objectValues($httpTests, 'httptestid');

		if (!$this->isWritable($httpTestIds)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('You do not have permission to perform this operation.'));
		}

		$this->checkNames($httpTests);

		foreach ($httpTests as $httpTest) {
			if (isset($httpTest['name'])) {
				// get hostid from db if it's not provided
				if (isset($httpTest['hostid'])) {
					$hostId = $httpTest['hostid'];
				}
				else {
					$hostId = DBfetch(DBselect('SELECT ht.hostid FROM httptest ht'.
							' WHERE ht.httptestid='.zbx_dbstr($httpTest['httptestid'])));
					$hostId = $hostId['hostid'];
				}

				$nameExists = DBfetch(DBselect('SELECT ht.name FROM httptest ht'.
					' WHERE ht.name='.zbx_dbstr($httpTest['name']).
						' AND ht.hostid='.zbx_dbstr($hostId).
						' AND ht.httptestid<>'.zbx_dbstr($httpTest['httptestid']), 1));
				if ($nameExists) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Scenario "%1$s" already exists.', $nameExists['name']));
				}
			}

			if (!check_db_fields(array('httptestid' => null), $httpTest)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			if (isset($httpTest['steps'])) {
				$this->checkSteps($httpTest['steps']);
			}
		}
	}

	/**
	 * Check web scenario steps.
	 *  - check status_codes field
	 *
	 * @param array $steps
	 */
	protected function checkSteps(array $steps) {
		foreach ($steps as $step) {
			if (isset($step['status_codes'])) {
				$this->checkStatusCode($step['status_codes']);
			}
		}
	}

	/**
	 * Validate http response code reange.
	 * Range can contain ',' and '-'
	 * Range can be empty string.
	 *
	 * Examples: '100-199, 301, 404, 500-550'
	 *
	 * @param string $statusCodeRange
	 *
	 * @return bool
	 */
	protected  function checkStatusCode($statusCodeRange) {
		if ($statusCodeRange == '') {
			return true;
		}

		foreach (explode(',', $statusCodeRange) as $range) {
			$range = explode('-', $range);
			if (count($range) > 2) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid response code range "%1$s".', $statusCodeRange));
			}

			foreach ($range as $value) {
				if (!is_numeric($value)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid response code "%1$s".', $value));
				}
			}
		}

		return true;
	}

	/**
	 * Check web scenario names.
	 *
	 * @param array $httpTests
	 *
	 * @return array|null
	 */
	protected function checkNames(array $httpTests) {
		$httpTestsNames = zbx_objectValues($httpTests, 'name');
		if (!preg_grep('/^['.ZBX_PREG_PRINT.']+$/u', $httpTestsNames)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Only characters are allowed.'));
		}

		return $httpTestsNames;
	}

	/**
	 * Check if user has read permissions on http test with given ids.
	 *
	 * @param array $ids
	 *
	 * @return bool
	 */
	public function isReadable(array $ids) {
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'httptestids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	/**
	 * Check if user has write permissions on http test with given ids.
	 *
	 * @param array $ids
	 *
	 * @return bool
	 */
	public function isWritable(array $ids) {
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'httptestids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}
}
