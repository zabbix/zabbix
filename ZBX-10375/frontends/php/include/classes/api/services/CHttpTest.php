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
 * Class containing methods for operations with http tests.
 *
 * @package API
 */
class CHttpTest extends CApiService {

	protected $tableName = 'httptest';
	protected $tableAlias = 'ht';
	protected $sortColumns = ['httptestid', 'name'];

	/**
	 * Get data about web scenarios.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sqlParts = [
			'select'	=> ['httptests' => 'ht.httptestid'],
			'from'		=> ['httptest' => 'httptest ht'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
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
		];
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
						' AND MAX(r.permission)>='.zbx_dbstr($permission).
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

			$result = $this->unsetExtraFields($result, ['hostid'], $options['output']);
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

		if (!$httpTests) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameters.'));
		}

		// Check and set default values, and find "hostid" by "applicationid".
		foreach ($httpTests as &$httpTest) {
			$defaultValues = [
				'verify_peer' => HTTPTEST_VERIFY_PEER_OFF,
				'verify_host' => HTTPTEST_VERIFY_HOST_OFF
			];

			check_db_fields($defaultValues, $httpTest);
		}
		unset($httpTest);

		$this->validateCreate($httpTests);

		$httpTests = Manager::HttpTest()->persist($httpTests);

		return ['httptestids' => zbx_objectValues($httpTests, 'httptestid')];
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

		if (!$httpTests) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameters.'));
		}

		$this->checkObjectIds($httpTests, 'httptestid',
			_('No "%1$s" given for web scenario.'),
			_('Empty web scenario ID.'),
			_('Incorrect web scenario ID.')
		);

		$httpTests = zbx_toHash($httpTests, 'httptestid');

		$dbHttpTests = [];
		$dbCursor = DBselect(
			'SELECT ht.httptestid,ht.hostid,ht.templateid,ht.name,'.
				'ht.ssl_cert_file,ht.ssl_key_file,ht.ssl_key_password,ht.verify_peer,ht.verify_host'.
			' FROM httptest ht'.
			' WHERE '.dbConditionInt('ht.httptestid', array_keys($httpTests))
		);
		while ($dbHttpTest = DBfetch($dbCursor)) {
			$dbHttpTests[$dbHttpTest['httptestid']] = $dbHttpTest;
		}

		$dbCursor = DBselect(
			'SELECT hs.httpstepid,hs.httptestid,hs.name'.
			' FROM httpstep hs'.
			' WHERE '.dbConditionInt('hs.httptestid', array_keys($dbHttpTests))
		);
		while ($dbHttpStep = DBfetch($dbCursor)) {
			$dbHttpTests[$dbHttpStep['httptestid']]['steps'][$dbHttpStep['httpstepid']] = $dbHttpStep;
		}

		$httpTests = $this->validateUpdate($httpTests, $dbHttpTests);

		Manager::HttpTest()->persist($httpTests);

		return ['httptestids' => array_keys($httpTests)];
	}

	/**
	 * Delete web scenario.
	 *
	 * @param array $httpTestIds
	 * @param bool  $nopermissions
	 *
	 * @return array
	 */
	public function delete(array $httpTestIds, $nopermissions = false) {
		if (empty($httpTestIds)) {
			return true;
		}

		$delHttpTests = $this->get([
			'httptestids' => $httpTestIds,
			'output' => API_OUTPUT_EXTEND,
			'editable' => true,
			'selectHosts' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		]);
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
		$childHttpTestIds = [];
		do {
			$dbTests = DBselect('SELECT ht.httptestid FROM httptest ht WHERE '.dbConditionInt('ht.templateid', $parentHttpTestIds));
			$parentHttpTestIds = [];
			while ($dbTest = DBfetch($dbTests)) {
				$parentHttpTestIds[] = $dbTest['httptestid'];
				$childHttpTestIds[$dbTest['httptestid']] = $dbTest['httptestid'];
			}
		} while (!empty($parentHttpTestIds));

		$options = [
			'httptestids' => $childHttpTestIds,
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => true,
			'preservekeys' => true,
			'selectHosts' => API_OUTPUT_EXTEND
		];
		$delHttpTestChildren = $this->get($options);
		$delHttpTests = zbx_array_merge($delHttpTests, $delHttpTestChildren);
		$httpTestIds = array_merge($httpTestIds, $childHttpTestIds);

		$itemidsDel = [];
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

		DB::delete('httptest', ['httptestid' => $httpTestIds]);

		// TODO: REMOVE
		foreach ($delHttpTests as $httpTest) {
			$host = reset($httpTest['hosts']);

			info(_s('Deleted: Web scenario "%1$s" on "%2$s".', $httpTest['name'], $host['host']));
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCENARIO,
				_('Web scenario').' ['.$httpTest['name'].'] ['.$httpTest['httptestid'].'] '.
				_('Host').' ['.$host['name'].']'
			);
		}

		return ['httptestids' => $httpTestIds];
	}

	/**
	 * Validate web scenario parameters for create method.
	 *  - check if web scenario with same name already exists
	 *  - check if web scenario has at least one step
	 *
	 * @param array $httpTests
	 */
	protected function validateCreate(array $httpTests) {
		foreach ($httpTests as $httpTest) {
			$missingKeys = checkRequiredKeys($httpTest, ['name', 'hostid', 'steps']);
			if (!empty($missingKeys)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Web scenario missing parameters: %1$s', implode(', ', $missingKeys)));
			}
		}

		$hostIds = zbx_objectValues($httpTests, 'hostid');
		if (!API::Host()->isWritable($hostIds)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($httpTests as $httpTest) {
			if (zbx_empty($httpTest['name'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Web scenario name cannot be empty.'));
			}

			$this->checkSslParameters($httpTest);

			if (empty($httpTest['steps'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Web scenario must have at least one step.'));
			}

			$this->checkSteps($httpTest);
			$this->checkDuplicateSteps($httpTest);
		}

		// check input array for duplicate names
		$collectionValidator = new CCollectionValidator([
			'uniqueField' => 'name',
			'uniqueField2' => 'hostid',
			'messageDuplicate' => _('Web scenario "%1$s" already exists.')
		]);
		$this->checkValidator($httpTests, $collectionValidator);

		// check database for duplicate names
		$this->checkDuplicates($httpTests);

		$this->checkApplicationHost($httpTests);
	}

	/**
	 * Validate web scenario parameters for update method.
	 *  - check permissions
	 *  - check if web scenario with same name already exists
	 *  - check that each web scenario object has httptestid defined
	 *  - return array of web scenarios, if validation was successful
	 *
	 * @param array $httpTests
	 * @param array $dbHttpTests
	 *
	 * @return array $httpTests
	 */
	protected function validateUpdate(array $httpTests, array $dbHttpTests) {
		if (!$this->isWritable(array_keys($httpTests))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		$httpTests = $this->extendFromObjects($httpTests, $dbHttpTests, [
			'ssl_key_file', 'ssl_cert_file', 'ssl_key_password', 'verify_host', 'verify_peer'
		]);

		foreach ($httpTests as &$httpTest) {
			$dbHttpTest = $dbHttpTests[$httpTest['httptestid']];

			$httpTest['hostid'] = $dbHttpTest['hostid'];

			if (!isset($httpTest['name']) || $dbHttpTest['templateid']) {
				$httpTest['name'] = $dbHttpTest['name'];
			}

			$this->checkSslParameters($httpTest);

			if (array_key_exists('steps', $httpTest) && is_array($httpTest['steps'])) {
				foreach ($httpTest['steps'] as &$httpTestStep) {
					if (isset($httpTestStep['httpstepid'])
							&& ($dbHttpTest['templateid'] || !isset($httpTestStep['name']))) {
						$httpTestStep['name'] = $dbHttpTest['steps'][$httpTestStep['httpstepid']]['name'];
					}

					if ($dbHttpTest['templateid']) {
						unset($httpTestStep['no']);
					}

					// unset required text and POST variables if retrieving only headers
					if (isset($httpTestStep['retrieve_mode'])
							&& ($httpTestStep['retrieve_mode'] == HTTPTEST_STEP_RETRIEVE_MODE_HEADERS)) {
						$httpTestStep['required'] = '';
						$httpTestStep['posts'] = '';
					}
				}
				unset($httpTestStep);

				$this->checkSteps($httpTest, $dbHttpTest);
				$this->checkDuplicateSteps($httpTest, $dbHttpTest);
			}

			unset($httpTest['templateid']);
		}
		unset($httpTest);

		// check input array for duplicate names
		$collectionValidator = new CCollectionValidator([
			'uniqueField' => 'name',
			'uniqueField2' => 'hostid',
			'messageDuplicate' => _('Web scenario "%1$s" already exists.')
		]);
		$this->checkValidator($httpTests, $collectionValidator);

		// check database for duplicate names
		$this->checkDuplicates($httpTests, $dbHttpTests);

		$this->checkApplicationHost($httpTests);

		return $httpTests;
	}

	/**
	 * Check DB for duplicate names on hosts
	 *
	 * @throws APIException if same name on some host is found.
	 *
	 * @param array $httpTests		array of web screnarios
	 * @param array $dbHttpTests	array of DB web screnarios
	 */
	protected function checkDuplicates(array $httpTests, array $dbHttpTests = []) {
		$httpTestNames = [];

		foreach ($httpTests as $httpTest) {
			if (isset($httpTest['name'])) {
				if (zbx_empty($httpTest['name'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Web scenario name cannot be empty.'));
				}
				elseif (($dbHttpTests && $dbHttpTests[$httpTest['httptestid']]['name'] !== $httpTest['name'])
						|| !$dbHttpTests) {
					$httpTestNames[$httpTest['hostid']][] = $httpTest['name'];
				}
			}
		}

		if ($httpTestNames) {
			foreach ($httpTestNames as $hostId => $httpTestName) {
				$nameExists = API::getApiService()->select($this->tableName(), [
					'output' => ['name'],
					'filter' => ['name' => $httpTestName, 'hostid' => $hostId],
					'limit' => 1
				]);

				if ($nameExists) {
					$nameExists = reset($nameExists);
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Web scenario "%1$s" already exists.', $nameExists['name'])
					);
				}
			}
		}
	}

	/**
	 * Check that application belongs to http test host.
	 *
	 * @param array $httpTests
	 */
	protected function checkApplicationHost(array $httpTests) {
		// applications containing 0 in ID, will be removed from web scenario
		foreach ($httpTests as $httpTestId => $httpTest) {
			if (array_key_exists('applicationid', $httpTest) && $httpTest['applicationid'] == 0) {
				unset($httpTests[$httpTestId]);
			}
		}

		$applicationids = zbx_objectValues($httpTests, 'applicationid');

		if ($applicationids) {
			$applications = API::getApiService()->select('applications', [
				'output' => ['applicationid', 'hostid', 'name', 'flags'],
				'applicationids' => $applicationids,
				'preservekeys' => true
			]);

			// check if applications exist and are normal applications
			foreach ($applicationids as $applicationid) {
				if (!array_key_exists($applicationid, $applications)) {
					self::exception(ZBX_API_ERROR_PERMISSIONS,
						_('No permissions to referred object or it does not exist!')
					);
				}
				elseif ($applications[$applicationid]['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Cannot add a discovered application "%1$s" to a web scenario.',
						$applications[$applicationid]['name']
					));
				}
			}

			foreach ($httpTests as $httpTest) {
				if (!idcmp($applications[$httpTest['applicationid']]['hostid'], $httpTest['hostid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('The web scenario application belongs to a different host than the web scenario host.')
					);
				}
			}
		}
	}

	/**
	 * Check web scenario steps.
	 *  - check status_codes field
	 *  - check name characters
	 *
	 * @throws APIException if incorrect characters are passed, incorrect step numbers step is missing.
	 *
	 * @param array $httpTest
	 * @param array $dbHttpTest
	 */
	protected function checkSteps(array $httpTest, array $dbHttpTest = []) {
		if (array_key_exists('steps', $httpTest)
				&& (!is_array($httpTest['steps']) || (count($httpTest['steps']) == 0))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Web scenario must have at least one step.'));
		}

		$followRedirectsValidator = new CLimitedSetValidator([
				'values' => [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON]
			]
		);

		$retrieveModeValidator = new CLimitedSetValidator([
				'values' => [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS]
			]
		);

		foreach ($httpTest['steps'] as $step) {
			if ((isset($step['httpstepid']) && array_key_exists('name', $step) && zbx_empty($step['name']))
					|| (!isset($step['httpstepid']) && (!array_key_exists('name', $step) || zbx_empty($step['name'])))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Web scenario step name cannot be empty.'));
			}

			if ((isset($step['httpstepid']) && array_key_exists('url', $step) && zbx_empty($step['url']))
					|| (!isset($step['httpstepid']) && (!array_key_exists('url', $step) || zbx_empty($step['url'])))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Web scenario step URL cannot be empty.'));
			}

			if (isset($step['no']) && $step['no'] <= 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Web scenario step number cannot be less than 1.'));
			}
			if (isset($step['status_codes'])) {
				$this->checkStatusCode($step['status_codes']);
			}

			if (isset($step['follow_redirects'])) {
				$followRedirectsValidator->messageInvalid = _s(
					'Incorrect follow redirects value for step "%1$s" of web scenario "%2$s".',
					$step['name'],
					$httpTest['name']
				);

				$this->checkValidator($step['follow_redirects'], $followRedirectsValidator);
			}

			if (isset($step['retrieve_mode'])) {
				$retrieveModeValidator->messageInvalid = _s(
					'Incorrect retrieve mode value for step "%1$s" of web scenario "%2$s".',
					$step['name'],
					$httpTest['name']
				);

				$this->checkValidator($step['retrieve_mode'], $retrieveModeValidator);
			}
		}

		// check if step still exists on update
		if ($dbHttpTest) {
			foreach ($httpTest['steps'] as $step) {
				if (isset($step['httpstepid']) && !isset($dbHttpTest['steps'][$step['httpstepid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('No permissions to referred object or it does not exist!')
					);
				}
			}
		}
	}

	/**
	 * Check duplicate step names.
	 *
	 * @throws APIException if duplicate step name is found.
	 *
	 * @param array $httpTest
	 * @param array $dbHttpTest
	 */
	protected function checkDuplicateSteps(array $httpTest, array $dbHttpTest = []) {
		if ($duplicate = CArrayHelper::findDuplicate($httpTest['steps'], 'name')) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Web scenario step "%1$s" already exists.', $duplicate['name']));
		}
	}

	/**
	 * Validate http response code range.
	 * Range can be empty string, can be set as user macro or be numeric and contain ',' and '-'.
	 *
	 * Examples: '100-199, 301, 404, 500-550' or '{$USER_MACRO123}'
	 *
	 * @throws APIException if the status code range is invalid.
	 *
	 * @param string $statusCodeRange
	 *
	 * @return bool
	 */
	protected  function checkStatusCode($statusCodeRange) {
		$user_macro_parser = new CUserMacroParser();

		if ($statusCodeRange === '' || $user_macro_parser->parse($statusCodeRange) == CParser::PARSE_SUCCESS) {
			return true;
		}
		else {
			$ranges = explode(',', $statusCodeRange);
			foreach ($ranges as $range) {
				$range = explode('-', $range);
				if (count($range) > 2) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid response code "%1$s".', $statusCodeRange));
				}

				foreach ($range as $value) {
					if (!is_numeric($value)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Invalid response code "%1$s".', $statusCodeRange)
						);
					}
				}
			}
		}

		return true;
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

		$count = $this->get([
			'httptestids' => $ids,
			'countOutput' => true
		]);

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

		$count = $this->get([
			'httptestids' => $ids,
			'editable' => true,
			'countOutput' => true
		]);

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
			$hosts = API::Host()->get([
				'output' => $options['selectHosts'],
				'hostid' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'templated_hosts' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding steps
		if ($options['selectSteps'] !== null) {
			if ($options['selectSteps'] != API_OUTPUT_COUNT) {
				$httpSteps = API::getApiService()->select('httpstep', [
					'output' => $this->outputExtend($options['selectSteps'], ['httptestid', 'httpstepid']),
					'filters' => ['httptestid' => $httpTestIds],
					'preservekeys' => true
				]);
				$relationMap = $this->createRelationMap($httpSteps, 'httptestid', 'httpstepid');

				$httpSteps = $this->unsetExtraFields($httpSteps, ['httptestid', 'httpstepid'], $options['selectSteps']);

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

	/**
	 * @param $httpTest
	 *
	 * @throws APIException if bad value for "verify_peer" parameter.
	 * @throws APIException if bad value for "verify_host" parameter.
	 * @throws APIException if SSL cert is present but SSL key is not.
	 */
	protected function checkSslParameters($httpTest) {

		$verifyPeerValidator = new CLimitedSetValidator(
			[
				'values' => [HTTPTEST_VERIFY_PEER_ON, HTTPTEST_VERIFY_PEER_OFF],
				'messageInvalid' => _('Incorrect SSL verify peer value for web scenario "%1$s".')
			]
		);
		$verifyPeerValidator->setObjectName($httpTest['name']);
		$this->checkValidator($httpTest['verify_peer'], $verifyPeerValidator);

		$verifyHostValidator = new CLimitedSetValidator(
			[
				'values' => [HTTPTEST_VERIFY_HOST_ON, HTTPTEST_VERIFY_HOST_OFF],
				'messageInvalid' => _('Incorrect SSL verify host value for web scenario "%1$s".')
			]
		);

		$verifyHostValidator->setObjectName($httpTest['name']);
		$this->checkValidator($httpTest['verify_host'], $verifyHostValidator);

			if (($httpTest['ssl_key_password'] != '') && ($httpTest['ssl_key_file'] == '')) {
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s('Empty SSL key file for web scenario "%1$s".', $httpTest['name'])
			);
		}

		if (($httpTest['ssl_key_file'] != '') && ($httpTest['ssl_cert_file'] == '')) {
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s('Empty SSL certificate file for web scenario "%1$s".', $httpTest['name'])
			);
		}
	}
}
