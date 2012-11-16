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
			'nodeids'        => null,
			'httptestids'    => null,
			'applicationids' => null,
			'hostids'        => null,
			'groupids'       => null,
			'templateids'    => null,
			'editable'       => null,
			'inherited'      => null,
			'templated'      => null,
			'nopermissions'  => null,
			// filter
			'filter'         => null,
			'search'         => null,
			'searchByAny'    => null,
			'startSearch'    => null,
			'exludeSearch'   => null,
			// output
			'output'         => API_OUTPUT_REFER,
			'expandName'     => null,
			'expandStepName' => null,
			'selectHosts'    => null,
			'selectSteps'    => null,
			'countOutput'    => null,
			'groupCount'     => null,
			'preservekeys'   => null,
			'sortfield'      => '',
			'sortorder'      => '',
			'limit'          => null
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

			$sqlParts['select']['httptestid'] = 'ht.httptestid';
			$sqlParts['where']['httptestid'] = DBcondition('ht.httptestid', $options['httptestids']);
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

			$sqlParts['where']['hostid'] = DBcondition('ht.hostid', $options['hostids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hostid'] = 'ht.hostid';
			}
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['select']['groupid'] = 'hg.groupid';
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

		// inherited
		if (isset($options['inherited'])) {
			$sqlParts['where'][] = $options['inherited'] ? 'ht.templateid IS NOT NULL' : 'ht.templateid IS NULL';
		}

		// templated
		if (isset($options['templated'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['ha'] = 'h.hostid=ht.hostid';
			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
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

				if (!isset($result[$httpTest['httptestid']])) {
					$result[$httpTest['httptestid']] = array();
				}
				if (!is_null($options['selectHosts']) && !isset($result[$httpTest['httptestid']]['hosts'])) {
					$result[$httpTest['httptestid']]['hosts'] = array();
				}
				if (!is_null($options['selectSteps']) && !isset($result[$httpTest['httptestid']]['steps'])) {
					$result[$httpTest['httptestid']]['steps'] = array();
				}

				$result[$httpTest['httptestid']] += $httpTest;
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
				'templated_hosts' => true,
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

		// expandName
		if (!is_null($options['expandName']) && $result
			&& ((is_array($options['output']) && in_array('name', $options['output'])) || ($options['output'] = 'all'))
		) {
			$expandSteps = false;
			if (!is_null($options['expandStepName']) && $result && !is_null($options['selectSteps'])
					&& ((is_array($options['selectSteps']) && in_array('name', $options['selectSteps'])) || ($options['selectSteps'] = 'all'))
			) {
				$expandSteps = true;
			}

			$result = resolveHttpTestMacros($result, true, $expandSteps);
		}

		// expandStepName
		if (!is_null($options['expandStepName']) && is_null($options['expandName']) && $result && !is_null($options['selectSteps'])
			&& ((is_array($options['selectSteps']) && in_array('name', $options['selectSteps'])) || ($options['selectSteps'] = 'all'))
		) {
			$result = resolveHttpTestMacros($result, false, true);
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

		// find hostid by applicationid
		foreach ($httpTests as $hnum => $httpTest) {
			unset($httpTests[$hnum]['templateid']);

			if (empty($httpTest['hostid']) && !empty($httpTest['applicationid'])) {
				$dbHostId = DBfetch(DBselect('SELECT a.hostid'.
						' FROM applications a'.
						' WHERE a.applicationid='.zbx_dbstr($httpTest['applicationid'])));
				$httpTests[$hnum]['hostid'] = $dbHostId['hostid'];
			}
		}

		$this->validateCreate($httpTests);

		$httpTestManager = new CHttpTestManager();
		$httpTestManager->persist($httpTests);

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

		$httpTests = zbx_toHash($httpTests, 'httptestid');
		foreach ($httpTests as $hnum => $httpTest) {
			unset($httpTests[$hnum]['templateid']);
		}


		$dbHttpTests = array();
		$dbCursor = DBselect('SELECT ht.httptestid,ht.hostid,ht.templateid,ht.name'.
				' FROM httptest ht'.
				' WHERE '.DBcondition('ht.httptestid', array_keys($httpTests)));
		while ($dbHttpTest = DBfetch($dbCursor)) {
			$dbHttpTests[$dbHttpTest['httptestid']] = $dbHttpTest;
		}
		$dbCursor = DBselect('SELECT hs.httpstepid,hs.httptestid,hs.name'.
				' FROM httpstep hs'.
				' WHERE '.DBcondition('hs.httptestid', array_keys($dbHttpTests)));
		while ($dbHttpStep = DBfetch($dbCursor)) {
			$dbHttpTests[$dbHttpStep['httptestid']]['steps'][$dbHttpStep['httpstepid']] = $dbHttpStep;
		}

		// add hostid if missing
		// add test name and steps names if it's empty or test is templated
		// unset steps no for templated tests
		foreach($httpTests as $tnum => $httpTest) {
			$test =& $httpTests[$tnum];
			$dbTest = $dbHttpTests[$httpTest['httptestid']];
			$test['hostid'] = $dbTest['hostid'];

			if (!empty($dbTest['templateid']) || empty($test['name'])) {
				$test['name'] = $dbTest['name'];
			}
			if (!empty($test['steps'])) {
				foreach ($test['steps'] as $snum => $step) {
					if (isset($step['httpstepid']) && (!empty($dbTest['templateid']) || empty($step['name']))) {
						$test['steps'][$snum]['name'] = $dbTest['steps'][$step['httpstepid']]['name'];
					}
					if (!empty($dbTest['templateid'])) {
						unset($test['steps'][$snum]['no']);
					}
				}
			}
			unset($test);
		}

		$this->validateUpdate($httpTests);

		$httpTestManager = new CHttpTestManager();
		$httpTestManager->persist($httpTests);

		return array('httptestids' => zbx_objectValues($httpTests, 'httptestid'));
	}

	/**
	 * Delete web scenario.
	 *
	 * @param $httpTestIds
	 *
	 * @return array|bool
	 */
	public function delete($httpTestIds, $nopermissions = false) {
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
		if (!$nopermissions) {
			foreach ($httpTestIds as $httpTestId) {
				if (!empty($delHttpTests[$httpTestId]['templateid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot delete templated web scenario "%1$s".', $delHttpTests[$httpTestId]['name']));
				}
				if (!isset($delHttpTests[$httpTestId])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}
			}
		}

		$parentHttpTestIds = $httpTestIds;
		$childHttpTestIds = array();
		do {
			$dbTests = DBselect('SELECT ht.httptestid FROM httptest ht WHERE '.DBcondition('ht.templateid', $parentHttpTestIds));
			$parentHttpTestIds = array();
			while ($dbTest = DBfetch($dbTests)) {
				$parentHttpTestIds[] = $dbTest['httptestid'];
				$childHttpTestIds[$dbTest['httptestid']] = $dbTest['httptestid'];
			}
		} while (!empty($parentHttpTestIds));

		$options = array(
			'httptestids' => $childHttpTestIds,
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => true,
			'preservekeys' => true,
			'selectHosts' => API_OUTPUT_EXTEND
		);
		$delHttpTestChilds = $this->get($options);
		$delHttpTests = zbx_array_merge($delHttpTests, $delHttpTestChilds);
		$httpTestIds = array_merge($httpTestIds, $childHttpTestIds);

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

		// TODO: REMOVE
		foreach ($delHttpTests as $httpTest) {
			$host = reset($httpTest['hosts']);

			info(_s('Deleted: Web scenario "%1$s" on "%2$s".', $httpTest['name'], $host['host']));
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCENARIO,
				'Web scenario "'.$httpTest['name'].'" "'.$httpTest['httptestid'].'" host "'.$host['host'].'".');
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
		$this->checkNames($httpTests);

		foreach ($httpTests as $httpTest) {
			$missingKeys = checkRequiredKeys($httpTest, array('name', 'hostid', 'status', 'steps'));
			if (!empty($missingKeys)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Web scenario missing parameters: %1$s', implode(', ', $missingKeys)));
			}

			$nameExists = DBfetch(DBselect('SELECT ht.name FROM httptest ht'.
				' WHERE ht.name='.zbx_dbstr($httpTest['name']).
					' AND ht.hostid='.zbx_dbstr($httpTest['hostid']), 1));
			if ($nameExists) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Web scenario "%1$s" already exists.', $nameExists['name']));
			}

			if (empty($httpTest['steps'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Web scenario must have at least one step.'));
			}
			$this->checkSteps($httpTest);
		}

		$this->checkApplicationHost($httpTests);
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
			$missingKeys = checkRequiredKeys($httpTest, array('httptestid'));
			if (!empty($missingKeys)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Web scenario missing parameters: %1$s', implode(', ', $missingKeys)));
			}

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
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Web scenario "%1$s" already exists.', $nameExists['name']));
				}
			}

			if (!check_db_fields(array('httptestid' => null), $httpTest)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			if (isset($httpTest['steps'])) {
				$this->checkSteps($httpTest);
				$this->checkStepsOnUpdate($httpTest);
			}
		}

		$this->checkApplicationHost($httpTests);
	}

	/**
	 * Check that application belongs to http test host.
	 *
	 * @param array $httpTests
	 */
	protected function checkApplicationHost(array $httpTests) {
		$appIds = zbx_objectValues($httpTests, 'applicationid');
		$appIds = zbx_toHash($appIds);
		unset($appIds['0']);

		if (!empty($appIds)) {
			$appHostIds = array();

			$dbCursor = DBselect('SELECT a.hostid, a.applicationid FROM applications a'.
				' WHERE '.DBcondition('a.applicationid', $appIds));
			while ($dbApp = DBfetch($dbCursor)) {
				$appHostIds[$dbApp['applicationid']] = $dbApp['hostid'];
			}

			foreach ($httpTests as $httpTest) {
				if (isset($httpTest['applicationid'])) {
					if (!idcmp($appHostIds[$httpTest['applicationid']], $httpTest['hostid'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('The web scenario application belongs to a different host than the web scenario host.'));
					}
				}
			}
		}
	}

	/**
	 * Check web scenario steps.
	 *  - check status_codes field
	 *  - check name characters
	 *
	 * @param array $httpTest
	 */
	protected function checkSteps(array $httpTest) {
		$stepNames = zbx_objectValues($httpTest['steps'], 'name');
		if (!empty($stepNames) && !preg_grep('/'.ZBX_PREG_PARAMS.'/i', $stepNames)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Web scenario step name should contain only printable characters.'));
		}

		foreach ($httpTest['steps'] as $step) {
			if (isset($step['no']) && $step['no'] <= 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Web scenario step number cannot be less than 1.'));
			}
			if (isset($step['status_codes'])) {
				$this->checkStatusCode($step['status_codes']);
			}
		}
	}

	/**
	 * Check duplicate step names.
	 *
	 * @param array $httpTest
	 */
	protected function checkStepsOnUpdate(array $httpTest) {
		$stepNames = array();
		foreach ($httpTest['steps'] as $step) {
			if (!isset($step['httpstepid'])) {
				$stepNames[] = $step['name'];
			}
		}
		$sql = 'SELECT h.httpstepid,h.name'.
				' FROM httpstep h'.
				' WHERE h.httptestid='.$httpTest['httptestid'].
				' AND '.DBcondition('h.name', $stepNames);
		if ($dbStep = DBfetch(DBselect($sql))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Web scenario Step "%1$s" already exists.', $dbStep['name']));
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
		if (!empty($httpTestsNames)) {
			if (!preg_grep('/^['.ZBX_PREG_PRINT.']+$/u', $httpTestsNames)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Only characters are allowed.'));
			}
		}
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
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}
}
