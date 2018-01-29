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
 * Class to perform low level http tests related actions.
 */
class CHttpTestManager {

	const ITEM_HISTORY = '30d';
	const ITEM_TRENDS = '90d';

	/**
	 * Changed steps names.
	 * array(
	 *   testid1 => array(nameold1 => namenew1, nameold2 => namenew2),
	 *   ...
	 * )
	 *
	 * @var array
	 */
	protected $changedSteps = [];

	/**
	 * Map of parent http test id to child http test id.
	 *
	 * @var array
	 */
	protected $httpTestParents = [];

	/**
	 * Save http test to db.
	 *
	 * @param array $httpTests
	 *
	 * @return array
	 */
	public function persist(array $httpTests) {
		$this->changedSteps = $this->findChangedStepNames($httpTests);

		$httpTests = $this->save($httpTests);
		$this->inherit($httpTests);

		return $httpTests;
	}

	/**
	 * Find steps where name was changed.
	 *
	 * @return array
	 */
	protected function findChangedStepNames(array $httpTests) {
		$httpSteps = [];
		$result = [];
		foreach ($httpTests as $httpTest) {
			if (isset($httpTest['httptestid']) && isset($httpTest['steps'])) {
				foreach ($httpTest['steps'] as $step) {
					if (isset($step['httpstepid']) && isset($step['name'])) {
						$httpSteps[$step['httpstepid']] = $step['name'];
					}
				}
			}
		}

		if (!empty($httpSteps)) {
			$dbCursor = DBselect(
				'SELECT hs.httpstepid,hs.httptestid,hs.name'.
				' FROM httpstep hs'.
				' WHERE '.dbConditionInt('hs.httpstepid', array_keys($httpSteps))
			);
			while ($dbStep = DBfetch($dbCursor)) {
				if ($httpSteps[$dbStep['httpstepid']] != $dbStep['name']) {
					$result[$dbStep['httptestid']][$httpSteps[$dbStep['httpstepid']]] = $dbStep['name'];
				}
			}
		}

		return $result;
	}

	/**
	 * Create new http tests.
	 *
	 * @param array $httpTests
	 *
	 * @return array
	 */
	public function create(array $httpTests) {
		$httpTestIds = DB::insert('httptest', $httpTests);

		foreach ($httpTests as $hnum => &$httpTest) {
			$httpTest['httptestid'] = $httpTestIds[$hnum];

			$this->createHttpTestItems($httpTest);
			$this->createStepsReal($httpTest, $httpTest['steps']);
		}
		unset($httpTest);

		$this->updateHttpTestFields($httpTests, 'create');

		return $httpTests;
	}

	/**
	 * Update http tests.
	 *
	 * @param array $httptests
	 *
	 * @return array
	 */
	public function update(array $httptests) {
		$httptestids = zbx_objectValues($httptests, 'httptestid');

		$db_httptests = API::HttpTest()->get([
			'output' => ['httptestid', 'name', 'applicationid', 'delay', 'status', 'agent', 'authentication',
				'http_user', 'http_password', 'hostid', 'templateid', 'http_proxy', 'retries', 'ssl_cert_file',
				'ssl_key_file', 'ssl_key_password', 'verify_peer', 'verify_host'
			],
			'httptestids' => $httptestids,
			'selectSteps' => ['httpstepid', 'name', 'no', 'url', 'timeout', 'posts', 'required', 'status_codes',
				'follow_redirects', 'retrieve_mode'
			],
			'editable' => true,
			'preservekeys' => true
		]);

		$deleteStepItemIds = [];
		$steps_create = [];
		$steps_update = [];

		foreach ($httptests as $key => $httptest) {
			$db_httptest = $db_httptests[$httptest['httptestid']];

			if (array_key_exists('delay', $httptest) && $db_httptest['delay'] != $httptest['delay']) {
				$httptest['nextcheck'] = 0;
			}

			DB::update('httptest', [
				'values' => $httptest,
				'where' => ['httptestid' => $httptest['httptestid']]
			]);

			$checkItemsUpdate = [];
			$updateFields = [];
			$itemids = [];
			$dbCheckItems = DBselect(
				'SELECT i.itemid,hi.type'.
				' FROM items i,httptestitem hi'.
				' WHERE hi.httptestid='.zbx_dbstr($httptest['httptestid']).
					' AND hi.itemid=i.itemid'
			);
			while ($checkitem = DBfetch($dbCheckItems)) {
				$itemids[] = $checkitem['itemid'];

				if (isset($httptest['name'])) {
					$updateFields['key_'] = $this->getTestKey($checkitem['type'], $httptest['name']);
				}

				if (isset($httptest['status'])) {
					$updateFields['status'] = (HTTPTEST_STATUS_ACTIVE == $httptest['status']) ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;
				}
				if (isset($httptest['delay'])) {
					$updateFields['delay'] = $httptest['delay'];
				}
				if (!empty($updateFields)) {
					$checkItemsUpdate[] = [
						'values' => $updateFields,
						'where' => ['itemid' => $checkitem['itemid']]
					];
				}
			}
			DB::update('items', $checkItemsUpdate);

			if (isset($httptest['applicationid'])) {
				$this->updateItemsApplications($itemids, $httptest['applicationid']);
			}

			if (array_key_exists('steps', $httptest)) {
				$dbSteps = zbx_toHash($db_httptest['steps'], 'httpstepid');

				foreach ($httptest['steps'] as $webstep) {
					if (isset($webstep['httpstepid']) && isset($dbSteps[$webstep['httpstepid']])) {
						$steps_update[$key][] = $webstep;
						unset($dbSteps[$webstep['httpstepid']]);
					}
					elseif (!isset($webstep['httpstepid'])) {
						$steps_create[$key][] = $webstep;
					}
				}

				$stepidsDelete = array_keys($dbSteps);

				if (!empty($stepidsDelete)) {
					$result = DBselect(
						'SELECT hi.itemid FROM httpstepitem hi WHERE '.dbConditionInt('hi.httpstepid', $stepidsDelete)
					);

					foreach (DBfetchColumn($result, 'itemid') as $itemId) {
						$deleteStepItemIds[] = $itemId;
					}

					DB::delete('httpstep', ['httpstepid' => $stepidsDelete]);
				}

				// IF application ID was not set, use the ID from DB so new items can be linked.
				if (!array_key_exists('applicationid', $httptest)) {
					$httptest['applicationid'] = $db_httptest['applicationid'];
				}
				elseif (bccomp($httptest['applicationid'], $db_httptest['applicationid'])) {
					unset($httptest['applicationid']);
				}
			}
		}

		// Old items must be deleted prior to createStepsReal() since identical items cannot be created in DB.
		if ($deleteStepItemIds) {
			API::Item()->delete($deleteStepItemIds, true);
		}

		foreach ($httptests as $key => $httptest) {
			if (array_key_exists('steps', $httptest)) {
				if (array_key_exists($key, $steps_update)) {
					$this->updateStepsReal($httptest, $steps_update[$key]);
				}

				if (array_key_exists($key, $steps_create)) {
					$this->createStepsReal($httptest, $steps_create[$key]);
				}
			}
			else {
				if (isset($httptest['applicationid'])) {
					$dbStepIds = DBfetchColumn(DBselect(
						'SELECT i.itemid'.
						' FROM items i'.
							' INNER JOIN httpstepitem hi ON hi.itemid=i.itemid'.
						' WHERE '.dbConditionInt('hi.httpstepid', zbx_objectValues($db_httptests[$httptest['httptestid']]['steps'], 'httpstepid')))
						, 'itemid'
					);
					$this->updateItemsApplications($dbStepIds, $httptest['applicationid']);
				}

				if (isset($httptest['status'])) {
					$status = ($httptest['status'] == HTTPTEST_STATUS_ACTIVE) ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;

					$itemIds = DBfetchColumn(DBselect(
						'SELECT hsi.itemid'.
							' FROM httpstep hs,httpstepitem hsi'.
							' WHERE hs.httpstepid=hsi.httpstepid'.
								' AND hs.httptestid='.zbx_dbstr($httptest['httptestid'])
					), 'itemid');

					DB::update('items', [
						'values' => ['status' => $status],
						'where' => ['itemid' => $itemIds]
					]);
				}
			}
		}

		/*
		 * Unset unchanged properties, otherwise, host web scenarios will inherit them and their custom applications
		 * will be overwritten.
		 */
		$properties_to_compare = ['applicationid', 'ssl_cert_file', 'ssl_key_password', 'ssl_key_file', 'verify_peer',
			'verify_host'
		];

		foreach ($httptests as &$http_test) {
			foreach ($properties_to_compare as $property) {
				// Do not compare unexisting properties.
				if (!array_key_exists($property, $http_test)) {
					continue;
				}

				// Unset unchanged properties.
				if (($property === 'applicationid'
						&& bccomp($http_test[$property], $db_httptests[$http_test['httptestid']][$property]) == 0)
						|| $http_test[$property] === $db_httptests[$http_test['httptestid']][$property]) {
					unset($http_test[$property]);
				}
			}
		}
		unset($http_test);

		$this->updateHttpTestFields($httptests, 'update');

		return $httptests;
	}

	/**
	 * Link http tests in template to hosts.
	 *
	 * @param $templateId
	 * @param $hostIds
	 */
	public function link($templateId, $hostIds) {
		$hostIds = zbx_toArray($hostIds);

		$httpTests = API::HttpTest()->get([
			'output' => ['httptestid', 'name', 'applicationid', 'delay', 'status', 'agent', 'authentication',
				'http_user', 'http_password', 'hostid', 'templateid', 'http_proxy', 'retries', 'ssl_cert_file',
				'ssl_key_file', 'ssl_key_password', 'verify_peer', 'verify_host', 'variables', 'headers'
			],
			'hostids' => $templateId,
			'selectSteps' => ['httpstepid', 'name', 'no', 'url', 'timeout', 'posts', 'required', 'status_codes',
				'follow_redirects', 'retrieve_mode', 'variables', 'headers', 'query_fields'
			],
			'preservekeys' => true
		]);

		$this->inherit($httpTests, $hostIds);
	}

	/**
	 * Inherit passed http tests to hosts.
	 * If $hostIds is empty that means that we need to inherit all $httpTests to hosts which are linked to templates
	 * where $httpTests belong.
	 *	 *
	 * @param array $httpTests
	 * @param array $hostIds
	 *
	 * @return bool
	 */
	public function inherit(array $httpTests, array $hostIds = []) {
		$hostsTemplatesMap = $this->getChildHostsFromHttpTests($httpTests, $hostIds);
		if (empty($hostsTemplatesMap)) {
			return true;
		}

		$preparedHttpTests = $this->prepareInheritedHttpTests($httpTests, $hostsTemplatesMap);
		$inheritedHttpTests = $this->save($preparedHttpTests);
		$this->inherit($inheritedHttpTests);

		return true;
	}

	/**
	 * Get array with hosts that are linked with templates which passed http tests belong to as key and templateid that host
	 * is linked to as value.
	 * If second parameter $hostIds is not empty, result should contain only passed host ids.
	 *
	 * @param array $httpTests
	 * @param array $hostIds
	 *
	 * @return array
	 */
	protected function getChildHostsFromHttpTests(array $httpTests, array $hostIds = []) {
		$hostsTemplatesMap = [];

		$sqlWhere = $hostIds ? ' AND '.dbConditionInt('ht.hostid', $hostIds) : '';
		$dbCursor = DBselect(
			'SELECT ht.templateid,ht.hostid'.
			' FROM hosts_templates ht'.
			' WHERE '.dbConditionInt('ht.templateid', zbx_objectValues($httpTests, 'hostid')).
				$sqlWhere
		);
		while ($dbHost = DBfetch($dbCursor)) {
			$hostsTemplatesMap[$dbHost['hostid']] = $dbHost['templateid'];
		}

		return $hostsTemplatesMap;
	}

	/**
	 * Generate http tests data for inheritance.
	 * Using passed parameters decide if new http tests must be created on host or existing ones must be updated.
	 *
	 * @param array $httpTests which we need to inherit
	 * @param array $hostsTemplatesMap
	 *
	 * @throws Exception
	 * @return array with http tests, existing apps have 'httptestid' key.
	 */
	protected function prepareInheritedHttpTests(array $httpTests, array $hostsTemplatesMap) {
		$hostHttpTests = $this->getHttpTestsMapsByHostIds(array_keys($hostsTemplatesMap));

		$result = [];
		foreach ($httpTests as $httpTest) {
			$httpTestId = $httpTest['httptestid'];
			foreach ($hostHttpTests as $hostId => $hostHttpTest) {
				// if http test template is not linked to host we skip it
				if ($hostsTemplatesMap[$hostId] != $httpTest['hostid']) {
					continue;
				}

				$exHttpTest = null;
				// update by templateid
				if (isset($hostHttpTest['byTemplateId'][$httpTestId])) {
					$exHttpTest = $hostHttpTest['byTemplateId'][$httpTestId];

					/*
					 * 'templateid' needs to be checked here too in case we update linked httptest to name
					 * that already exists on a linked host.
					 */
					if (isset($httpTest['name']) && isset($hostHttpTest['byName'][$httpTest['name']])
							&& !idcmp($exHttpTest['templateid'], $hostHttpTest['byName'][$httpTest['name']]['templateid'])) {
						$host = DBfetch(DBselect('SELECT h.name FROM hosts h WHERE h.hostid='.zbx_dbstr($hostId)));
						throw new Exception(
							_s('Web scenario "%1$s" already exists on host "%2$s".', $exHttpTest['name'], $host['name'])
						);
					}
				}
				// update by name
				elseif (isset($hostHttpTest['byName'][$httpTest['name']])) {
					$exHttpTest = $hostHttpTest['byName'][$httpTest['name']];

					if (bccomp($exHttpTest['templateid'], $httpTestId) == 0 || !$this->compareHttpSteps($httpTest, $exHttpTest)) {
						$host = DBfetch(DBselect('SELECT h.name FROM hosts h WHERE h.hostid='.zbx_dbstr($hostId)));
						throw new Exception(
							_s('Web scenario "%1$s" already exists on host "%2$s".', $exHttpTest['name'], $host['name'])
						);
					}
					elseif ($this->compareHttpProperties($httpTest, $exHttpTest)) {
						$this->createLinkageBetweenHttpTests($httpTestId, $exHttpTest['httptestid']);
						continue;
					}
				}

				$newHttpTest = $httpTest;
				$newHttpTest['hostid'] = $hostId;
				$newHttpTest['templateid'] = $httpTestId;
				if ($exHttpTest) {
					$newHttpTest['httptestid'] = $exHttpTest['httptestid'];

					$this->setHttpTestParent($exHttpTest['httptestid'], $httpTestId);

					if (isset($newHttpTest['steps'])) {
						$newHttpTest['steps'] = $this->prepareHttpSteps($httpTest['steps'], $exHttpTest['httptestid']);
					}
				}
				else {
					unset($newHttpTest['httptestid']);
				}

				if (!empty($newHttpTest['applicationid'])) {
					$newHttpTest['applicationid'] = $this->findChildApplication($newHttpTest['applicationid'], $hostId);
				}

				$result[] = $newHttpTest;
			}
		}

		return $result;
	}

	/**
	 * Compare properties for http tests.
	 *
	 * @param array $http_test			Current http test properties.
	 * @param array $ex_http_test		Existing http test properties to compare with.
	 *
	 * @return bool
	 */
	protected function compareHttpProperties(array $http_test, array $ex_http_test) {
		return ($http_test['http_proxy'] === $ex_http_test['http_proxy']
				&& $http_test['agent'] === $ex_http_test['agent']
				&& $http_test['retries'] == $ex_http_test['retries']
				&& $http_test['delay'] === $ex_http_test['delay']
				&& bccomp($http_test['applicationid'], $ex_http_test['applicationid']) == 0);
	}

	/**
	 * Create linkage between two http tests.
	 * If we found existing http test by name and steps, we only add linkage, i.e. change templateid
	 *
	 * @param $parentId
	 * @param $childId
	 */
	protected function createLinkageBetweenHttpTests($parentId, $childId) {
		DB::update('httptest', [
			'values' => ['templateid' => $parentId],
			'where' => ['httptestid' => $childId]
		]);

		$dbCursor = DBselect(
			'SELECT i1.itemid AS parentid,i2.itemid AS childid'.
			' FROM httptestitem hti1,httptestitem hti2,items i1,items i2'.
			' WHERE hti1.httptestid='.zbx_dbstr($parentId).
				' AND hti2.httptestid='.zbx_dbstr($childId).
				' AND hti1.itemid=i1.itemid'.
				' AND hti2.itemid=i2.itemid'.
				' AND i1.key_=i2.key_'
		);
		while ($dbItems = DBfetch($dbCursor)) {
			DB::update('items', [
				'values' => ['templateid' => $dbItems['parentid']],
				'where' => ['itemid' => $dbItems['childid']]
			]);
		}

		$dbCursor = DBselect(
			'SELECT i1.itemid AS parentid,i2.itemid AS childid'.
			' FROM httpstepitem hsi1,httpstepitem hsi2,httpstep hs1,httpstep hs2,items i1,items i2'.
			' WHERE hs1.httptestid='.zbx_dbstr($parentId).
				' AND hs2.httptestid='.zbx_dbstr($childId).
				' AND hsi1.itemid=i1.itemid'.
				' AND hsi2.itemid=i2.itemid'.
				' AND hs1.httpstepid=hsi1.httpstepid'.
				' AND hs2.httpstepid=hsi2.httpstepid'.
				' AND i1.key_=i2.key_'
		);
		while ($dbItems = DBfetch($dbCursor)) {
			DB::update('items', [
				'values' => ['templateid' => $dbItems['parentid']],
				'where' => ['itemid' => $dbItems['childid']]
			]);
		}
	}

	/**
	 * Find application with same name on given host.
	 *
	 * @param $parentAppId
	 * @param $childHostId
	 *
	 * @return string
	 */
	protected function findChildApplication($parentAppId, $childHostId) {
		$childAppId = DBfetch(DBselect(
			'SELECT a2.applicationid'.
			' FROM applications a1'.
				' INNER JOIN applications a2 ON a1.name=a2.name'.
			' WHERE a1.applicationid='.zbx_dbstr($parentAppId).
				' AND a2.hostid='.zbx_dbstr($childHostId))
		);

		return $childAppId['applicationid'];
	}

	/**
	 * Find and set first parent id for http test.
	 *
	 * @param $id
	 * @param $parentId
	 */
	protected function setHttpTestParent($id, $parentId) {
		while (isset($this->httpTestParents[$parentId])) {
			$parentId = $this->httpTestParents[$parentId];
		}
		$this->httpTestParents[$id] = $parentId;
	}

	/**
	 * Get hosts http tests for each passed hosts.
	 * Each host has two hashes with http tests, one with name keys other with templateid keys.
	 *
	 * Resulting structure is:
	 * array(
	 *     'hostid1' => array(
	 *         'byName' => array(ht1data, ht2data, ...),
	 *         'nyTemplateId' => array(ht1data, ht2data, ...)
	 *     ), ...
	 * );
	 *
	 * @param array $hostIds
	 *
	 * @return array
	 */
	protected function getHttpTestsMapsByHostIds(array $hostIds) {
		$hostHttpTests = [];
		foreach ($hostIds as $hostid) {
			$hostHttpTests[$hostid] = ['byName' => [], 'byTemplateId' => []];
		}

		$dbCursor = DBselect(
			'SELECT ht.httptestid,ht.name,ht.applicationid,ht.delay,ht.agent,ht.hostid,ht.templateid,ht.http_proxy,'.
				'ht.retries'.
			' FROM httptest ht'.
			' WHERE '.dbConditionInt('ht.hostid', $hostIds)
		);
		while ($dbHttpTest = DBfetch($dbCursor)) {
			$hostHttpTests[$dbHttpTest['hostid']]['byName'][$dbHttpTest['name']] = $dbHttpTest;
			if ($dbHttpTest['templateid']) {
				$hostHttpTests[$dbHttpTest['hostid']]['byTemplateId'][$dbHttpTest['templateid']] = $dbHttpTest;
			}
		}

		return $hostHttpTests;
	}

	/**
	 * Compare steps for http tests.
	 *
	 * @param array $httpTest steps must be included under 'steps'
	 * @param array $exHttpTest
	 *
	 * @return bool
	 */
	protected function compareHttpSteps(array $httpTest, array $exHttpTest) {
		$firstHash = '';
		$secondHash = '';

		CArrayHelper::sort($httpTest['steps'], ['no']);
		foreach ($httpTest['steps'] as $step) {
			$firstHash .= $step['no'].$step['name'];
		}

		$dbHttpTestSteps = DBfetchArray(DBselect(
			'SELECT hs.name,hs.no'.
			' FROM httpstep hs'.
			' WHERE hs.httptestid='.zbx_dbstr($exHttpTest['httptestid'])
		));

		CArrayHelper::sort($dbHttpTestSteps, ['no']);
		foreach ($dbHttpTestSteps as $dbHttpStep) {
			$secondHash .= $dbHttpStep['no'].$dbHttpStep['name'];
		}

		return ($firstHash === $secondHash);
	}

	/**
	 * Save http tests. If http test has httptestid it gets updated otherwise a new one is created.
	 *
	 * @param array $http_tests
	 *
	 * @return array
	 */
	protected function save(array $http_tests) {
		$http_tests_to_create = [];
		$http_tests_to_update = [];

		foreach ($http_tests as $num => $http_test) {
			if (array_key_exists('httptestid', $http_test)) {
				$http_tests_to_update[] = $http_test;
			}
			else {
				$http_tests_to_create[] = $http_test;
			}

			/*
			 * Unset $http_tests and (later) put it back with actual httptestid as a key right after creating/updateing
			 * it. This is done in such a way because $http_tests array holds items with incremental keys which are not
			 * a real httptestids.
			 */
			unset($http_tests[$num]);
		}

		if ($http_tests_to_create) {
			$new_http_tests = $this->create($http_tests_to_create);

			foreach ($new_http_tests as $new_http_test) {
				$http_tests[$new_http_test['httptestid']] = $new_http_test;
			}
		}

		if ($http_tests_to_update) {
			$updated_http_tests = $this->update($http_tests_to_update);

			foreach ($updated_http_tests as $updated_http_test) {
				$http_tests[$updated_http_test['httptestid']] = $updated_http_test;
			}
		}

		return $http_tests;
	}

	/**
	 * @param array $steps
	 * @param $exHttpTestId
	 *
	 * @return array
	 */
	protected function prepareHttpSteps(array $steps, $exHttpTestId) {
		$exSteps = [];
		$dbCursor = DBselect(
			'SELECT hs.httpstepid,hs.name'.
			' FROM httpstep hs'.
			' WHERE hs.httptestid='.zbx_dbstr($exHttpTestId)
		);
		while ($dbHttpStep = DBfetch($dbCursor)) {
			$exSteps[$dbHttpStep['name']] = $dbHttpStep['httpstepid'];
		}

		$result = [];
		foreach ($steps as $step) {
			$parentTestId = $this->httpTestParents[$exHttpTestId];
			if (isset($this->changedSteps[$parentTestId][$step['name']])) {
				$stepName = $this->changedSteps[$parentTestId][$step['name']];
			}
			else {
				$stepName = $step['name'];
			}

			if (isset($exSteps[$stepName])) {
				$step['httpstepid'] = $exSteps[$stepName];
				$step['httptestid'] = $exHttpTestId;
			}

			$result[] = $step;
		}

		return $result;
	}

	/**
	 * Create items required for web scenario.
	 *
	 * @param array $httpTest
	 *
	 * @throws Exception
	 */
	protected function createHttpTestItems(array $httpTest) {
		$checkitems = [
			[
				'name'				=> 'Download speed for scenario "$1".',
				'key_'				=> $this->getTestKey(HTTPSTEP_ITEM_TYPE_IN, $httpTest['name']),
				'value_type'		=> ITEM_VALUE_TYPE_FLOAT,
				'units'				=> 'Bps',
				'httptestitemtype'	=> HTTPSTEP_ITEM_TYPE_IN
			],
			[
				'name'				=> 'Failed step of scenario "$1".',
				'key_'				=> $this->getTestKey(HTTPSTEP_ITEM_TYPE_LASTSTEP, $httpTest['name']),
				'value_type'		=> ITEM_VALUE_TYPE_UINT64,
				'units'				=> '',
				'httptestitemtype'	=> HTTPSTEP_ITEM_TYPE_LASTSTEP
			],
			[
				'name'				=> 'Last error message of scenario "$1".',
				'key_'				=> $this->getTestKey(HTTPSTEP_ITEM_TYPE_LASTERROR, $httpTest['name']),
				'value_type'		=> ITEM_VALUE_TYPE_STR,
				'units'				=> '',
				'httptestitemtype'	=> HTTPSTEP_ITEM_TYPE_LASTERROR
			]
		];

		// if this is a template scenario, fetch the parent http items to link inherited items to them
		$parentItems = [];
		if (isset($httpTest['templateid']) && $httpTest['templateid']) {
			$parentItems = DBfetchArrayAssoc(DBselect(
				'SELECT i.itemid,i.key_'.
					' FROM items i,httptestitem hti'.
					' WHERE i.itemid=hti.itemid'.
					' AND hti.httptestid='.zbx_dbstr($httpTest['templateid'])
			), 'key_');
		}

		$insertItems = [];

		$delay = array_key_exists('delay', $httpTest) ? $httpTest['delay'] : DB::getDefault('httptest', 'delay');
		$status = array_key_exists('status', $httpTest) ? $httpTest['status'] : DB::getDefault('httptest', 'status');

		foreach ($checkitems as $item) {
			$item['hostid'] = $httpTest['hostid'];
			$item['delay'] = $delay;
			$item['type'] = ITEM_TYPE_HTTPTEST;
			$item['history'] = self::ITEM_HISTORY;
			$item['trends'] = self::ITEM_TRENDS;
			$item['status'] = ($status == HTTPTEST_STATUS_ACTIVE)
				? ITEM_STATUS_ACTIVE
				: ITEM_STATUS_DISABLED;

			if (isset($parentItems[$item['key_']])) {
				$item['templateid'] = $parentItems[$item['key_']]['itemid'];
			}

			$insertItems[] = $item;
		}

		$newTestItemIds = DB::insert('items', $insertItems);

		if (array_key_exists('applicationid', $httpTest)) {
			$this->createItemsApplications($newTestItemIds, $httpTest['applicationid']);
		}

		$httpTestItems = [];
		foreach ($checkitems as $inum => $item) {
			$httpTestItems[] = [
				'httptestid' => $httpTest['httptestid'],
				'itemid' => $newTestItemIds[$inum],
				'type' => $item['httptestitemtype']
			];
		}
		DB::insert('httptestitem', $httpTestItems);
	}

	/**
	 * Create web scenario fields.
	 *
	 * @param array  $httptests
	 * @param string $httptests['httptestid']
	 * @param array  $httptests['variables']           (optional)
	 * @param string $httptests['variables']['name']
	 * @param string $httptests['variables']['value']
	 * @param array  $httptests['headers']             (optional)
	 * @param string $httptests['headers']['name']
	 * @param string $httptests['headers']['value']
	 * @param string $method
	 */
	private function updateHttpTestFields(array $httptests, $method) {
		$fields = [
			ZBX_HTTPFIELD_VARIABLE => 'variables',
			ZBX_HTTPFIELD_HEADER => 'headers'
		];
		$httptest_fields = [];

		foreach ($httptests as $httptest) {
			foreach ($fields as $type => $field) {
				if (array_key_exists($field, $httptest)) {
					$httptest_fields[$httptest['httptestid']][$type] = $httptest[$field];
				}
			}
		}

		if (!$httptest_fields) {
			return;
		}

		$db_httptest_fields = ($method === 'update')
			? DB::select('httptest_field', [
				'output' => ['httptest_fieldid', 'httptestid', 'type', 'name', 'value'],
				'filter' => ['httptestid' => array_keys($httptest_fields)],
				'sortfield' => ['httptest_fieldid']
			])
			: [];

		$ins_httptest_fields = [];
		$upd_httptest_fields = [];
		$del_httptest_fieldids = [];

		foreach ($db_httptest_fields as $index =>  $db_httptest_field) {
			if (array_key_exists($db_httptest_field['type'], $httptest_fields[$db_httptest_field['httptestid']])) {
				$httptest_field =
					array_shift($httptest_fields[$db_httptest_field['httptestid']][$db_httptest_field['type']]);

				if ($httptest_field !== null) {
					$upd_httptest_field = [];

					foreach (['name', 'value'] as $field_name) {
						if ($httptest_field[$field_name] !== $db_httptest_field[$field_name]) {
							$upd_httptest_field[$field_name] = $httptest_field[$field_name];
						}
					}

					if ($upd_httptest_field) {
						$upd_httptest_fields[] = [
							'values' => $upd_httptest_field,
							'where' => ['httptest_fieldid' => $db_httptest_field['httptest_fieldid']]
						];
					}
				}
				else {
					$del_httptest_fieldids[] = $db_httptest_field['httptest_fieldid'];
				}
			}
		}

		foreach ($httptest_fields as $httptestid => $httptest_fields_by_httptest) {
			foreach ($httptest_fields_by_httptest as $type => $httptest_fields_by_type) {
				foreach ($httptest_fields_by_type as $httptest_field) {
					$ins_httptest_fields[] = [
						'httptestid' => $httptestid,
						'type' => $type
					] + $httptest_field;
				}
			}
		}

		if ($ins_httptest_fields) {
			DB::insertBatch('httptest_field', $ins_httptest_fields);
		}

		if ($upd_httptest_fields) {
			DB::update('httptest_field', $upd_httptest_fields);
		}

		if ($del_httptest_fieldids) {
			DB::delete('httptest_field', ['httptest_fieldid' => $del_httptest_fieldids]);
		}
	}

	/**
	 * Create web scenario step fields.
	 *
	 * @param array  $httpsteps
	 * @param string $httpsteps['httpstepid']
	 * @param array  $httpsteps['variables']           (optional)
	 * @param string $httpsteps['variables']['name']
	 * @param string $httpsteps['variables']['value']
	 * @param array  $httpsteps['headers']             (optional)
	 * @param string $httpsteps['headers']['name']
	 * @param string $httpsteps['headers']['value']
	 * @param string $method
	 */
	private function updateHttpStepFields(array $httpsteps, $method) {
		$fields = [
			ZBX_HTTPFIELD_VARIABLE => 'variables',
			ZBX_HTTPFIELD_HEADER => 'headers',
			ZBX_HTTPFIELD_POST_FIELD => 'post_fields',
			ZBX_HTTPFIELD_QUERY_FIELD => 'query_fields'
		];
		$httpstep_fields = [];

		foreach ($httpsteps as $httpstep) {
			foreach ($fields as $type => $field) {
				if (array_key_exists($field, $httpstep)) {
					$httpstep_fields[$httpstep['httpstepid']][$type] = $httpstep[$field];
				}
			}
		}

		if (!$httpstep_fields) {
			return;
		}

		$db_httpstep_fields = ($method === 'update')
			? DB::select('httpstep_field', [
				'output' => ['httpstep_fieldid', 'httpstepid', 'type', 'name', 'value'],
				'filter' => ['httpstepid' => array_keys($httpstep_fields)],
				'sortfield' => ['httpstep_fieldid']
			])
			: [];

		$ins_httpstep_fields = [];
		$upd_httpstep_fields = [];
		$del_httpstep_fieldids = [];

		foreach ($db_httpstep_fields as $index =>  $db_httpstep_field) {
			if (array_key_exists($db_httpstep_field['type'], $httpstep_fields[$db_httpstep_field['httpstepid']])) {
				$httpstep_field =
					array_shift($httpstep_fields[$db_httpstep_field['httpstepid']][$db_httpstep_field['type']]);

				if ($httpstep_field !== null) {
					$upd_httpstep_field = [];

					foreach (['name', 'value'] as $field_name) {
						if ($httpstep_field[$field_name] !== $db_httpstep_field[$field_name]) {
							$upd_httpstep_field[$field_name] = $httpstep_field[$field_name];
						}
					}

					if ($upd_httpstep_field) {
						$upd_httpstep_fields[] = [
							'values' => $upd_httpstep_field,
							'where' => ['httpstep_fieldid' => $db_httpstep_field['httpstep_fieldid']]
						];
					}
				}
				else {
					$del_httpstep_fieldids[] = $db_httpstep_field['httpstep_fieldid'];
				}
			}
		}

		foreach ($httpstep_fields as $httpstepid => $httpstep_fields_by_httpstep) {
			foreach ($httpstep_fields_by_httpstep as $type => $httpstep_fields_by_type) {
				foreach ($httpstep_fields_by_type as $httpstep_field) {
					$ins_httpstep_fields[] = [
						'httpstepid' => $httpstepid,
						'type' => $type
					] + $httpstep_field;
				}
			}
		}

		if ($ins_httpstep_fields) {
			DB::insertBatch('httpstep_field', $ins_httpstep_fields);
		}

		if ($upd_httpstep_fields) {
			DB::update('httpstep_field', $upd_httpstep_fields);
		}

		if ($del_httpstep_fieldids) {
			DB::delete('httpstep_field', ['httpstep_fieldid' => $del_httpstep_fieldids]);
		}
	}

	/**
	 * Create web scenario steps with items.
	 *
	 * @param $httpTest
	 * @param $websteps
	 *
	 * @throws Exception
	 */
	protected function createStepsReal($httpTest, $websteps) {
		foreach ($websteps as &$webstep) {
			$webstep['httptestid'] = $httpTest['httptestid'];

			if (array_key_exists('posts', $webstep)) {
				if (is_array($webstep['posts'])) {
					$webstep['post_fields'] = $webstep['posts'];
					$webstep['posts'] = '';
					$webstep['post_type'] = ZBX_POSTTYPE_FORM;
				}
				else {
					$webstep['post_fields'] = [];
					$webstep['post_type'] = ZBX_POSTTYPE_RAW;
				}
			}
		}
		unset($webstep);

		$webstepids = DB::insert('httpstep', $websteps);

		// if this is a template scenario, fetch the parent http items to link inherited items to them
		$parentStepItems = [];
		if (isset($httpTest['templateid']) && $httpTest['templateid']) {
			$parentStepItems = DBfetchArrayAssoc(DBselect(
				'SELECT i.itemid,i.key_,hsi.httpstepid'.
				' FROM items i,httpstepitem hsi,httpstep hs'.
				' WHERE i.itemid=hsi.itemid'.
					' AND hsi.httpstepid=hs.httpstepid'.
					' AND hs.httptestid='.zbx_dbstr($httpTest['templateid'])
			), 'key_');
		}

		foreach ($websteps as $snum => &$webstep) {
			$webstep['httpstepid'] = $webstepids[$snum];

			$stepitems = [
				[
					'name' => 'Download speed for step "$2" of scenario "$1".',
					'key_' => $this->getStepKey(HTTPSTEP_ITEM_TYPE_IN, $httpTest['name'], $webstep['name']),
					'value_type' => ITEM_VALUE_TYPE_FLOAT,
					'units' => 'Bps',
					'httpstepitemtype' => HTTPSTEP_ITEM_TYPE_IN
				],
				[
					'name' => 'Response time for step "$2" of scenario "$1".',
					'key_' => $this->getStepKey(HTTPSTEP_ITEM_TYPE_TIME, $httpTest['name'], $webstep['name']),
					'value_type' => ITEM_VALUE_TYPE_FLOAT,
					'units' => 's',
					'httpstepitemtype' => HTTPSTEP_ITEM_TYPE_TIME
				],
				[
					'name' => 'Response code for step "$2" of scenario "$1".',
					'key_' => $this->getStepKey(HTTPSTEP_ITEM_TYPE_RSPCODE, $httpTest['name'], $webstep['name']),
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'units' => '',
					'httpstepitemtype' => HTTPSTEP_ITEM_TYPE_RSPCODE
				]
			];

			if (!isset($httpTest['delay']) || !isset($httpTest['status'])) {
				$dbTest = DBfetch(DBselect('SELECT ht.delay,ht.status FROM httptest ht WHERE ht.httptestid='.zbx_dbstr($httpTest['httptestid'])));
				$delay = $dbTest['delay'];
				$status = $dbTest['status'];
			}
			else {
				$delay = $httpTest['delay'];
				$status = $httpTest['status'];
			}

			$insertItems = [];
			$stepItemids = [];

			foreach ($stepitems as $item) {
				$item['hostid'] = $httpTest['hostid'];
				$item['delay'] = $delay;
				$item['type'] = ITEM_TYPE_HTTPTEST;
				$item['history'] = self::ITEM_HISTORY;
				$item['trends'] = self::ITEM_TRENDS;
				$item['status'] = (HTTPTEST_STATUS_ACTIVE == $status) ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;

				if (isset($parentStepItems[$item['key_']])) {
					$item['templateid'] = $parentStepItems[$item['key_']]['itemid'];
				}

				$insertItems[] = $item;
			}

			if ($insertItems) {
				$stepItemids = DB::insert('items', $insertItems);

				if (array_key_exists('applicationid', $httpTest)) {
					$this->createItemsApplications($stepItemids, $httpTest['applicationid']);
				}
			}

			$webstepitems = [];
			foreach ($stepitems as $inum => $item) {
				$webstepitems[] = [
					'httpstepid' => $webstep['httpstepid'],
					'itemid' => $stepItemids[$inum],
					'type' => $item['httpstepitemtype']
				];
			}
			DB::insert('httpstepitem', $webstepitems);
		}
		unset($webstep);

		$this->updateHttpStepFields($websteps, 'create');
	}

	/**
	 * Update web scenario steps.
	 *
	 * @param $httpTest
	 * @param $websteps
	 *
	 * @throws Exception
	 */
	protected function updateStepsReal($httpTest, $websteps) {
		$item_key_parser = new CItemKey();

		// get all used keys
		$webstepids = zbx_objectValues($websteps, 'httpstepid');
		$dbKeys = DBfetchArrayAssoc(DBselect(
			'SELECT i.key_'.
			' FROM items i,httpstepitem hi'.
			' WHERE '.dbConditionInt('hi.httpstepid', $webstepids).
				' AND hi.itemid=i.itemid')
			, 'key_'
		);

		foreach ($websteps as &$webstep) {
			if (array_key_exists('posts', $webstep)) {
				if (is_array($webstep['posts'])) {
					$webstep['post_fields'] = $webstep['posts'];
					$webstep['posts'] = '';
					$webstep['post_type'] = ZBX_POSTTYPE_FORM;
				}
				else {
					$webstep['post_fields'] = [];
					$webstep['post_type'] = ZBX_POSTTYPE_RAW;
				}
			}
		}
		unset($webstep);

		foreach ($websteps as $webstep) {
			DB::update('httpstep', [
				'values' => $webstep,
				'where' => ['httpstepid' => $webstep['httpstepid']]
			]);

			// update item keys
			$itemids = [];
			$stepitemsUpdate = $updateFields = [];
			$dbStepItems = DBselect(
				'SELECT i.itemid,i.key_,hi.type'.
				' FROM items i,httpstepitem hi'.
				' WHERE hi.httpstepid='.zbx_dbstr($webstep['httpstepid']).
					' AND hi.itemid=i.itemid'
			);
			while ($stepitem = DBfetch($dbStepItems)) {
				$itemids[] = $stepitem['itemid'];

				if (isset($httpTest['name']) || isset($webstep['name'])) {
					if (!isset($httpTest['name']) || !isset($webstep['name'])) {
						$item_key_parser->parse($stepitem['key_']);
						if (!isset($httpTest['name'])) {
							$httpTest['name'] = $item_key_parser->getParam(0);
						}
						if (!isset($webstep['name'])) {
							$webstep['name'] = $item_key_parser->getParam(1);
						}
					}

					$updateFields['key_'] = $this->getStepKey($stepitem['type'], $httpTest['name'], $webstep['name']);
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
					$stepitemsUpdate[] = [
						'values' => $updateFields,
						'where' => ['itemid' => $stepitem['itemid']]
					];
				}
			}

			if ($stepitemsUpdate) {
				DB::update('items', $stepitemsUpdate);
			}

			if (array_key_exists('applicationid', $httpTest)) {
				$this->updateItemsApplications($itemids, $httpTest['applicationid']);
			}
		}

		$this->updateHttpStepFields($websteps, 'update');
	}

	/**
	 * Update web item application linkage.
	 *
	 * @param array  $itemids
	 * @param string $applicationid
	 */
	protected function updateItemsApplications(array $itemids, $applicationid) {
		if ($applicationid == 0) {
			DB::delete('items_applications', ['itemid' => $itemids]);
		}
		else {
			$linked_itemids = DBfetchColumn(
				DBselect('SELECT ia.itemid FROM items_applications ia WHERE '.dbConditionInt('ia.itemid', $itemids)),
				'itemid'
			);

			if ($linked_itemids) {
				DB::update('items_applications', [
					'values' => ['applicationid' => $applicationid],
					'where' => ['itemid' => $linked_itemids]
				]);
			}

			$this->createItemsApplications(array_diff($itemids, $linked_itemids), $applicationid);
		}
	}

	/**
	 * Create web item application linkage.
	 *
	 * @param array  $itemids
	 * @param string $applicationid
	 */
	protected function createItemsApplications(array $itemids, $applicationid) {
		if ($applicationid != 0 && $itemids) {
			$insert = [];

			foreach ($itemids as $itemid) {
				$insert[] = ['itemid' => $itemid, 'applicationid' => $applicationid];
			}

			DB::insertBatch('items_applications', $insert);
		}
	}

	/**
	 * Get item key for test item.
	 *
	 * @param int    $type
	 * @param string $testName
	 *
	 * @return bool|string
	 */
	protected function getTestKey($type, $testName) {
		switch ($type) {
			case HTTPSTEP_ITEM_TYPE_IN:
				return 'web.test.in['.quoteItemKeyParam($testName).',,bps]';
			case HTTPSTEP_ITEM_TYPE_LASTSTEP:
				return 'web.test.fail['.quoteItemKeyParam($testName).']';
			case HTTPSTEP_ITEM_TYPE_LASTERROR:
				return 'web.test.error['.quoteItemKeyParam($testName).']';
		}

		return false;
	}

	/**
	 * Get item key for step item.
	 *
	 * @param int    $type
	 * @param string $testName
	 * @param string $stepName
	 *
	 * @return bool|string
	 */
	protected function getStepKey($type, $testName, $stepName) {
		switch ($type) {
			case HTTPSTEP_ITEM_TYPE_IN:
				return 'web.test.in['.quoteItemKeyParam($testName).','.quoteItemKeyParam($stepName).',bps]';
			case HTTPSTEP_ITEM_TYPE_TIME:
				return 'web.test.time['.quoteItemKeyParam($testName).','.quoteItemKeyParam($stepName).',resp]';
			case HTTPSTEP_ITEM_TYPE_RSPCODE:
				return 'web.test.rspcode['.quoteItemKeyParam($testName).','.quoteItemKeyParam($stepName).']';
		}

		return false;
	}

	/**
	 * Returns the data about the last execution of the given HTTP tests.
	 *
	 * The following values will be returned for each executed HTTP test:
	 * - lastcheck      - time when the test has been executed last
	 * - lastfailedstep - number of the last failed step
	 * - error          - error message
	 *
	 * If a HTTP test has not been executed in last ZBX_HISTORY_PERIOD, no value will be returned.
	 *
	 * @param array $httpTestIds
	 *
	 * @return array    an array with HTTP test IDs as keys and arrays of data as values
	 */
	public function getLastData(array $httpTestIds) {
		$httpItems = DBfetchArray(DBselect(
			'SELECT hti.httptestid,hti.type,i.itemid,i.value_type'.
			' FROM httptestitem hti,items i'.
			' WHERE hti.itemid=i.itemid'.
				' AND hti.type IN ('.HTTPSTEP_ITEM_TYPE_LASTSTEP.','.HTTPSTEP_ITEM_TYPE_LASTERROR.')'.
				' AND '.dbConditionInt('hti.httptestid', $httpTestIds)
		));

		$history = Manager::History()->getLastValues($httpItems, 1, ZBX_HISTORY_PERIOD);

		$data = [];

		foreach ($httpItems as $httpItem) {
			if (isset($history[$httpItem['itemid']])) {
				if (!isset($data[$httpItem['httptestid']])) {
					$data[$httpItem['httptestid']] = [
						'lastcheck' => null,
						'lastfailedstep' => null,
						'error' => null
					];
				}

				$itemHistory = $history[$httpItem['itemid']][0];

				if ($httpItem['type'] == HTTPSTEP_ITEM_TYPE_LASTSTEP) {
					$data[$httpItem['httptestid']]['lastcheck'] = $itemHistory['clock'];
					$data[$httpItem['httptestid']]['lastfailedstep'] = $itemHistory['value'];
				}
				else {
					$data[$httpItem['httptestid']]['error'] = $itemHistory['value'];
				}
			}
		}

		return $data;
	}
}
