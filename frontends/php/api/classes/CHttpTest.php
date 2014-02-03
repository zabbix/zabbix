<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * Class containing methods for operations with http tests.
 *
 * @package API
 */
class CHttpTest extends CZBXAPI {

	protected $tableName = 'httptest';
	protected $tableAlias = 'ht';
	protected $sortColumns = array('httptestid', 'name');

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
			'monitored'      => null,
			'nopermissions'  => null,
			// filter
			'filter'         => null,
			'search'         => null,
			'searchByAny'    => null,
			'startSearch'    => null,
			'excludeSearch'  => null,
			// output
			'output'         => API_OUTPUT_EXTEND,
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
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$userGroups = getUserGroupsByUserId($userid);

			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM hosts_groups hgg'.
						' JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE ht.hostid=hgg.hostid'.
					' GROUP BY hgg.hostid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
						' AND MAX(r.permission)>='.$permission.
					')';
		}

		// httptestids
		if (!is_null($options['httptestids'])) {
			zbx_value2array($options['httptestids']);

			$sqlParts['where']['httptestid'] = dbConditionInt('ht.httptestid', $options['httptestids']);
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

			$sqlParts['where']['hostid'] = dbConditionInt('ht.hostid', $options['hostids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hostid'] = 'ht.hostid';
			}
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where'][] = 'hg.hostid=ht.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// applicationids
		if (!is_null($options['applicationids'])) {
			zbx_value2array($options['applicationids']);

			$sqlParts['where'][] = dbConditionInt('ht.applicationid', $options['applicationids']);
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

		// monitored
		if (!is_null($options['monitored'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hht'] = 'h.hostid=ht.hostid';

			if ($options['monitored']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
				$sqlParts['where'][] = 'ht.status='.ITEM_STATUS_ACTIVE;
			}
			else {
				$sqlParts['where'][] = '(h.status<>'.HOST_STATUS_MONITORED.' OR ht.status<>'.ITEM_STATUS_ACTIVE.')';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('httptest ht', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('httptest ht', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
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
				$result[$httpTest['httptestid']] = $httpTest;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);

			// expandName
			$nameRequested = (is_array($options['output']) && in_array('name', $options['output']))
				|| $options['output'] == API_OUTPUT_EXTEND;
			$expandName = $options['expandName'] !== null && $nameRequested;

			// expandStepName
			$stepNameRequested = $options['selectSteps'] == API_OUTPUT_EXTEND
				|| (is_array($options['selectSteps']) && in_array('name', $options['selectSteps']));
			$expandStepName = $options['expandStepName'] !== null && $stepNameRequested;

			if ($expandName || $expandStepName) {
				$result = resolveHttpTestMacros($result, $expandName, $expandStepName);
			}

			$result = $this->unsetExtraFields($result, array('hostid'), $options['output']);
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

		$httpTests = Manager::HttpTest()->persist($httpTests);

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
				' WHERE '.dbConditionInt('ht.httptestid', array_keys($httpTests)));
		while ($dbHttpTest = DBfetch($dbCursor)) {
			$dbHttpTests[$dbHttpTest['httptestid']] = $dbHttpTest;
		}
		$dbCursor = DBselect('SELECT hs.httpstepid,hs.httptestid,hs.name'.
				' FROM httpstep hs'.
				' WHERE '.dbConditionInt('hs.httptestid', array_keys($dbHttpTests)));
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

			if (!isset($test['name']) || zbx_empty($test['name']) || !empty($dbTest['templateid'])) {
				$test['name'] = $dbTest['name'];
			}
			if (!empty($test['steps'])) {
				foreach ($test['steps'] as $snum => $step) {
					if (isset($step['httpstepid'])
							&& (!empty($dbTest['templateid']) || !isset($step['name']) || zbx_empty($step['name']))) {
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

		Manager::HttpTest()->persist($httpTests);

		return array('httptestids' => zbx_objectValues($httpTests, 'httptestid'));
	}

	/**
	 * Delete web scenario.
	 *
	 * @param $httpTestIds
	 *
	 * @return array
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
			$dbTests = DBselect('SELECT ht.httptestid FROM httptest ht WHERE '.dbConditionInt('ht.templateid', $parentHttpTestIds));
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
			' WHERE '.dbConditionInt('hsi.httptestid', $httpTestIds)
		);
		while ($testitem = DBfetch($dbTestItems)) {
			$itemidsDel[] = $testitem['itemid'];
		}

		$dbStepItems = DBselect(
			'SELECT DISTINCT hsi.itemid'.
			' FROM httpstepitem hsi,httpstep hs'.
			' WHERE '.dbConditionInt('hs.httptestid', $httpTestIds).
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
			$missingKeys = checkRequiredKeys($httpTest, array('name', 'hostid', 'steps'));
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
			$this->checkDuplicateSteps($httpTest);
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
				$this->checkDuplicateSteps($httpTest);
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

			$dbCursor = DBselect(
				'SELECT a.hostid,a.applicationid'.
				' FROM applications a'.
				' WHERE '.dbConditionInt('a.applicationid', $appIds)
			);
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
	protected function checkDuplicateSteps(array $httpTest) {
		if ($duplicate = CArrayHelper::findDuplicate($httpTest['steps'], 'name')) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Web scenario step "%1$s" already exists.', $duplicate['name']));
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


	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null) {
			// make sure we request the hostid to be able to expand macros
			if ($options['expandName'] !== null || $options['expandStepName'] !== null || $options['selectHosts'] !== null) {
				$sqlParts = $this->addQuerySelect($this->fieldId('hostid'), $sqlParts);
			}
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$httpTestIds = array_keys($result);

		// adding hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'httptestid', 'hostid');
			$hosts = API::Host()->get(array(
				'output' => $options['selectHosts'],
				'hostid' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'templated_hosts' => true,
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding steps
		if ($options['selectSteps'] !== null) {
			if ($options['selectSteps'] != API_OUTPUT_COUNT) {
				$httpSteps = API::getApi()->select('httpstep', array(
					'output' => $this->outputExtend($options['selectSteps'], array('httptestid', 'httpstepid')),
					'filters' => array('httptestid' => $httpTestIds),
					'preservekeys' => true,
					'nodeids' => get_current_nodeid(true)
				));
				$relationMap = $this->createRelationMap($httpSteps, 'httptestid', 'httpstepid');

				$httpSteps = $this->unsetExtraFields($httpSteps, array('httptestid', 'httpstepid'), $options['selectSteps']);

				$result = $relationMap->mapMany($result, $httpSteps, 'steps');
			}
			else {
				$dbHttpSteps = DBselect(
					'SELECT hs.httptestid,COUNT(hs.httpstepid) AS stepscnt'.
						' FROM httpstep hs'.
						' WHERE '.dbConditionInt('hs.httptestid', $httpTestIds).
						' GROUP BY hs.httptestid'
				);
				while ($dbHttpStep = DBfetch($dbHttpSteps)) {
					$result[$dbHttpStep['httptestid']]['steps'] = $dbHttpStep['stepscnt'];
				}
			}
		}

		return $result;
	}
}
