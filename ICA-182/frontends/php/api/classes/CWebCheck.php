<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


class CWebCheck extends CZBXAPI {
	protected $tableName = 'httptest';
	protected $tableAlias = 'ht';
	private $history = 30;
	private $trends = 90;

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
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$userGroups = getUserGroupsByUserId($userid);

			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM applications a,hosts_groups hgg'.
						' JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE a.applicationid=ht.applicationid'.
						' AND a.hostid=hgg.hostid'.
					' GROUP BY a.applicationid'.
					' HAVING MIN(r.permission)>='.$permission.
					')';
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// httptestids
		if (!is_null($options['httptestids'])) {
			zbx_value2array($options['httptestids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['httptestid'] = 'ht.httptestid';
			}
			$sqlParts['where']['httptestid'] = dbConditionInt('ht.httptestid', $options['httptestids']);
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['hostid'] = 'a.hostid';
			}
			$sqlParts['from']['applications'] = 'applications a';
			$sqlParts['where'][] = 'a.applicationid=ht.applicationid';
			$sqlParts['where']['hostid'] = dbConditionInt('a.hostid', $options['hostids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hostid'] = 'a.hostid';
			}
		}

		// applicationids
		if (!is_null($options['applicationids'])) {
			zbx_value2array($options['applicationids']);

			if ($options['output'] != API_OUTPUT_EXTEND) {
				$sqlParts['select']['applicationid'] = 'a.applicationid';
			}
			$sqlParts['where'][] = dbConditionInt('ht.applicationid', $options['applicationids']);
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
			$this->dbFilter('httptest ht', $options, $sqlParts);
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
				' WHERE '.dbConditionInt('h.httptestid', $httpTestIds)
			);
			while ($step = DBfetch($dbSteps)) {
				$stepid = $step['httpstepid'];
				$step['webstepid'] = $stepid;
				unset($step['httpstepid']);
				$result[$step['httptestid']]['steps'][$step['webstepid']] = $step;
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

		$httpTestIds = DB::insert('httptest', $httpTests);

		foreach ($httpTests as $wnum => $httpTest) {
			$httpTest['httptestid'] = $httpTestIds[$wnum];

			$this->createCheckItems($httpTest);
			$this->createStepsReal($httpTest, $httpTest['steps']);
		}

		return array('httptestids' => $httpTestIds);
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

		$httpTestIds = zbx_objectValues($httpTests, 'httptestid');
		$dbHttpTest = $this->get(array(
			'output' => API_OUTPUT_EXTEND,
			'httptestids' => $httpTestIds,
			'selectSteps' => API_OUTPUT_EXTEND,
			'editable' => true,
			'preservekeys' => true
		));

		foreach ($httpTests as $httpTest) {
			DB::update('httptest', array(
				'values' => $httpTest,
				'where' => array('httptestid' => $httpTest['httptestid'])
			));

			$checkItemsUpdate = $updateFields = array();
			$dbCheckItems = DBselect(
				'SELECT i.itemid,hi.type'.
				' FROM items i,httptestitem hi'.
				' WHERE hi.httptestid='.zbx_dbstr($httpTest['httptestid']).
					' AND hi.itemid=i.itemid'
			);
			while ($checkitem = DBfetch($dbCheckItems)) {
				$itemids[] = $checkitem['itemid'];

				if (isset($httpTest['name'])) {
					switch ($checkitem['type']) {
						case HTTPSTEP_ITEM_TYPE_IN:
							$updateFields['key_'] = 'web.test.in['.$httpTest['name'].',,bps]';
							break;
						case HTTPSTEP_ITEM_TYPE_LASTSTEP:
							$updateFields['key_'] = 'web.test.fail['.$httpTest['name'].']';
							break;
						case HTTPSTEP_ITEM_TYPE_LASTERROR:
							$updateFields['key_'] = 'web.test.error['.$httpTest['name'].']';
							break;
					}
				}

				if (isset($httpTest['status'])) {
					$updateFields['status'] = (HTTPTEST_STATUS_ACTIVE == $httpTest['status']) ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;
				}
				if (isset($httpTest['delay'])) {
					$updateFields['delay'] = $httpTest['delay'];
				}
				if (!empty($updateFields)) {
					$checkItemsUpdate[] = array(
						'values' => $updateFields,
						'where' => array('itemid' => $checkitem['itemid'])
					);
				}
			}
			DB::update('items', $checkItemsUpdate);

			// update application
			if (isset($httpTest['applicationid'])) {
				DB::update('items_applications', array(
					'values' => array('applicationid' => $httpTest['applicationid']),
					'where' => array('itemid' => $itemids)
				));
			}

			// update steps
			$stepsCreate = $stepsUpdate = array();
			$dbSteps = zbx_toHash($dbHttpTest[$httpTest['httptestid']]['steps'], 'webstepid');

			foreach ($httpTest['steps'] as $webstep) {
				if (isset($webstep['webstepid']) && isset($dbSteps[$webstep['webstepid']])) {
					$stepsUpdate[] = $webstep;
					unset($dbSteps[$webstep['webstepid']]);
				}
				elseif (!isset($webstep['webstepid'])) {
					$stepsCreate[] = $webstep;
				}
			}
			$stepidsDelete = array_keys($dbSteps);

			if (!empty($stepidsDelete)) {
				$this->deleteStepsReal($stepidsDelete);
			}
			if (!empty($stepsUpdate)) {
				$this->updateStepsReal($httpTest, $stepsUpdate);
			}
			if (!empty($stepsCreate)) {
				$this->createStepsReal($httpTest, $stepsCreate);
			}
		}

		return array('httptestids' => $httpTestIds);
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

		if ($name = DBfetch(DBselect('SELECT ht.name FROM httptest ht WHERE '.dbConditionString('ht.name', $httpTestsNames), 1))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Scenario "%s" already exists.', $name['name']));
		}

		foreach ($httpTests as $httpTest) {
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

		$httpTestsNames = $this->checkNames($httpTests);

		$nameExists = DBfetch(DBselect('SELECT ht.name FROM httptest ht WHERE '.
				dbConditionString('ht.name', $httpTestsNames).
				' AND '.dbConditionInt('ht.httptestid', $httpTestIds, true), 1));
		if ($nameExists) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Scenario "%s" already exists.', $nameExists['name']));
		}

		foreach ($httpTests as $httpTest) {
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

		foreach ($httpTestsNames as $httpTestsName) {
			if (!preg_match('/^(['.ZBX_PREG_PRINT.'])+$/u', $httpTestsName)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Only characters are allowed.'));
			}
		}

		return $httpTestsNames;
	}

	/**
	 * Create items required for web scenario.
	 *
	 * @param $httpTest
	 */
	protected function createCheckItems($httpTest) {
		$checkitems = array(
			array(
				'name'				=> _s('Download speed for scenario "%s".', '$1'),
				'key_'				=> 'web.test.in['.$httpTest['name'].',,bps]',
				'value_type'		=> ITEM_VALUE_TYPE_FLOAT,
				'units'				=> 'Bps',
				'httptestitemtype'	=> HTTPSTEP_ITEM_TYPE_IN
			),
			array(
				'name'				=> _s('Failed step of scenario "%s".', '$1'),
				'key_'				=> 'web.test.fail['.$httpTest['name'].']',
				'value_type'		=> ITEM_VALUE_TYPE_UINT64,
				'units'				=> '',
				'httptestitemtype'	=> HTTPSTEP_ITEM_TYPE_LASTSTEP
			),
			array(
				'name'				=> _s('Last error message of scenario "%s".', '$1'),
				'key_'				=> 'web.test.error['.$httpTest['name'].']',
				'value_type'		=> ITEM_VALUE_TYPE_STR,
				'units'				=> '',
				'httptestitemtype'	=> HTTPSTEP_ITEM_TYPE_LASTERROR
			)
		);

		foreach ($checkitems as &$item) {
			$itemsExist = API::Item()->exists(array(
				'key_' => $item['key_'],
				'hostid' => $httpTest['hostid']
			));
			if ($itemsExist) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%s" already exists.', $item['key_']));
			}
			$item['data_type'] = ITEM_DATA_TYPE_DECIMAL;
			$item['hostid'] = $httpTest['hostid'];
			$item['delay'] = $httpTest['delay'];
			$item['type'] = ITEM_TYPE_HTTPTEST;
			$item['history'] = $this->history;
			$item['trends'] = $this->trends;
			$item['status'] = (HTTPTEST_STATUS_ACTIVE == $httpTest['status']) ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;
		}
		unset($item);

		$checkItemids = DB::insert('items', $checkitems);

		foreach ($checkItemids as $itemid) {
			$itemApplications[] = array(
				'applicationid' => $httpTest['applicationid'],
				'itemid' => $itemid
			);
		}
		DB::insert('items_applications', $itemApplications);

		$httpTestItems = array();
		foreach ($checkitems as $inum => $item) {
			$httpTestItems[] = array(
				'httptestid' => $httpTest['httptestid'],
				'itemid' => $checkItemids[$inum],
				'type' => $item['httptestitemtype']
			);
		}
		DB::insert('httptestitem', $httpTestItems);

		foreach ($checkitems as $stepitem) {
			info(_s('Web item "%s" created.', $stepitem['key_']));
		}
	}

	/**
	 * Create web scenario steps with items.
	 *
	 * @param $httpTest
	 * @param $websteps
	 */
	protected function createStepsReal($httpTest, $websteps) {
		$webstepsNames = zbx_objectValues($websteps, 'name');

		if (!preg_grep('/'.ZBX_PREG_PARAMS.'/i', $webstepsNames)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Scenario step name should contain only printable characters.'));
		}

		$sql = 'SELECT h.httpstepid,h.name'.
				' FROM httpstep h'.
				' WHERE h.httptestid='.zbx_dbstr($httpTest['httptestid']).
					' AND '.dbConditionString('h.name', $webstepsNames);
		if ($httpstepData = DBfetch(DBselect($sql))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Step "%s" already exists.', $httpstepData['name']));
		}

		foreach ($websteps as $snum => $webstep) {
			$websteps[$snum]['httptestid'] = $httpTest['httptestid'];
			if ($webstep['no'] <= 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Scenario step number cannot be less than 1.'));
			}
		}
		$webstepids = DB::insert('httpstep', $websteps);

		foreach ($websteps as $snum => $webstep) {
			$webstepid = $webstepids[$snum];

			$stepitems = array(
				array(
					'name'				=> _s('Download speed for step "%1$s" of scenario "%2$s".', '$2', '$1'),
					'key_'				=> 'web.test.in['.$httpTest['name'].','.$webstep['name'].',bps]',
					'value_type'		=> ITEM_VALUE_TYPE_FLOAT,
					'units'				=> 'Bps',
					'httpstepitemtype'	=> HTTPSTEP_ITEM_TYPE_IN
				),
				array(
					'name'				=> _s('Response time for step "%1$s" of scenario "%2$s".', '$2', '$1'),
					'key_'				=> 'web.test.time['.$httpTest['name'].','.$webstep['name'].',resp]',
					'value_type'		=> ITEM_VALUE_TYPE_FLOAT,
					'units'				=> 's',
					'httpstepitemtype'	=> HTTPSTEP_ITEM_TYPE_TIME
				),
				array(
					'name'				=> _s('Response code for step "%1$s" of scenario "%2$s".', '$2', '$1'),
					'key_'				=> 'web.test.rspcode['.$httpTest['name'].','.$webstep['name'].']',
					'value_type'		=> ITEM_VALUE_TYPE_UINT64,
					'units'				=> '',
					'httpstepitemtype'	=> HTTPSTEP_ITEM_TYPE_RSPCODE
				)
			);
			foreach ($stepitems as &$item) {
				$itemsExist = API::Item()->exists(array(
					'key_' => $item['key_'],
					'hostid' => $httpTest['hostid']
				));
				if ($itemsExist) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Web item with key "%s" already exists.', $item['key_']));
				}
				$item['hostid'] = $httpTest['hostid'];
				$item['delay'] = $httpTest['delay'];
				$item['type'] = ITEM_TYPE_HTTPTEST;
				$item['data_type'] = ITEM_DATA_TYPE_DECIMAL;
				$item['history'] = $this->history;
				$item['trends'] = $this->trends;
				$item['status'] = (HTTPTEST_STATUS_ACTIVE == $httpTest['status']) ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;
			}
			unset($item);

			$stepItemids = DB::insert('items', $stepitems);

			$itemApplications = array();
			foreach ($stepItemids as $itemid) {
				$itemApplications[] = array(
					'applicationid' => $httpTest['applicationid'],
					'itemid' => $itemid
				);
			}
			DB::insert('items_applications', $itemApplications);

			$webstepitems = array();
			foreach ($stepitems as $inum => $item) {
				$webstepitems[] = array(
					'httpstepid' => $webstepid,
					'itemid' => $stepItemids[$inum],
					'type' => $item['httpstepitemtype']
				);
			}
			DB::insert('httpstepitem', $webstepitems);

			foreach ($stepitems as $stepitem) {
				info(_s('Web item "%s" created.', $stepitem['key_']));
			}
		}
	}

	/**
	 * Update web scenario steps.
	 *
	 * @param $httpTest
	 * @param $websteps
	 */
	protected function updateStepsReal($httpTest, $websteps) {
		$webstepsNames = zbx_objectValues($websteps, 'name');

		if (!preg_grep('/'.ZBX_PREG_PARAMS.'/i', $webstepsNames)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Scenario step name should contain only printable characters.'));
		}

		// get all used keys
		$webstepids = zbx_objectValues($websteps, 'webstepid');
		$dbKeys = DBfetchArray(DBselect(
			'SELECT i.key_'.
			' FROM items i,httpstepitem hi'.
			' WHERE '.dbConditionInt('hi.httpstepid', $webstepids).
				' AND hi.itemid=i.itemid'
		));
		$dbKeys = zbx_toHash($dbKeys, 'key_');

		foreach ($websteps as $webstep) {
			if ($webstep['no'] <= 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Scenario step number cannot be less than 1.'));
			}

			DB::update('httpstep', array(
				'values' => $webstep,
				'where' => array('httpstepid' => $webstep['webstepid'])
			));

			// update item keys
			$itemids = array();
			$stepitemsUpdate = $updateFields = array();
			$dbStepItems = DBselect(
				'SELECT i.itemid,hi.type'.
				' FROM items i,httpstepitem hi'.
				' WHERE hi.httpstepid='.zbx_dbstr($webstep['webstepid']).
					' AND hi.itemid=i.itemid'
			);
			while ($stepitem = DBfetch($dbStepItems)) {
				$itemids[] = $stepitem['itemid'];

				if (isset($httpTest['name']) || $webstep['name']) {
					switch ($stepitem['type']) {
						case HTTPSTEP_ITEM_TYPE_IN:
							$updateFields['key_'] = 'web.test.in['.$httpTest['name'].','.$webstep['name'].',bps]';
							break;
						case HTTPSTEP_ITEM_TYPE_TIME:
							$updateFields['key_'] = 'web.test.time['.$httpTest['name'].','.$webstep['name'].',resp]';
							break;
						case HTTPSTEP_ITEM_TYPE_RSPCODE:
							$updateFields['key_'] = 'web.test.rspcode['.$httpTest['name'].','.$webstep['name'].']';
							break;
					}
				}
				if (isset($dbKeys[$updateFields['key_']])) {
					unset($updateFields['key_']);
				}
				if (isset($httpTest['status'])) {
					$updateFields['status'] = (HTTPTEST_STATUS_ACTIVE == $httpTest['status']) ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;
				}
				if (isset($httpTest['delay'])) {
					$updateFields['delay'] = $httpTest['delay'];
				}
				if (!empty($updateFields)) {
					$stepitemsUpdate[] = array(
						'values' => $updateFields,
						'where' => array('itemid' => $stepitem['itemid'])
					);
				}
			}
			DB::update('items', $stepitemsUpdate);

			// update application
			if (isset($httpTest['applicationid'])) {
				DB::update('items_applications', array(
					'values' => array('applicationid' => $httpTest['applicationid']),
					'where' => array('itemid' => $itemids)
				));
			}
		}
	}

	/**
	 * Delete web scenario steps.
	 *
	 * @param $webstepids
	 */
	protected function deleteStepsReal($webstepids) {
		$itemids = array();
		$dbStepItems = DBselect(
			'SELECT i.itemid'.
			' FROM items i,httpstepitem hi'.
			' WHERE '.dbConditionInt('hi.httpstepid', $webstepids).
				' AND hi.itemid=i.itemid'
		);
		while ($stepitem = DBfetch($dbStepItems)) {
			$itemids[] = $stepitem['itemid'];
		}

		DB::delete('httpstep', array('httpstepid' => $webstepids));

		if (!empty($itemids)) {
			API::Item()->delete($itemids, true);
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
