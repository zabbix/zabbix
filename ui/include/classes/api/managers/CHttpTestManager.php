<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	 * Create new HTTP tests.
	 *
	 * @param array $httptests
	 *
	 * @return array
	 */
	public function create(array $httptests) {
		$httptestids = DB::insert('httptest', $httptests);

		foreach ($httptests as $i => &$httptest) {
			$httptest['httptestid'] = $httptestids[$i];
			$itemids = [];

			$this->createHttpTestItems($httptest, $itemids);
			$this->createStepsReal($httptest, $httptest['steps'], $itemids);

			if (array_key_exists('tags', $httptest) && $httptest['tags']) {
				self::createItemsTags($httptest['tags'], $itemids);
			}
		}
		unset($httptest);

		$this->updateHttpTestFields($httptests, 'create');
		self::createHttpTestTags($httptests);

		return $httptests;
	}

	/**
	 * Update http tests.
	 *
	 * @param array $httptests
	 *
	 * @return array
	 */
	public function update(array $httptests) {
		$db_httptests = API::HttpTest()->get([
			'output' => ['httptestid', 'name', 'delay', 'status', 'agent', 'authentication',
				'http_user', 'http_password', 'hostid', 'templateid', 'http_proxy', 'retries', 'ssl_cert_file',
				'ssl_key_file', 'ssl_key_password', 'verify_peer', 'verify_host'
			],
			'selectSteps' => ['httpstepid', 'name', 'no', 'url', 'timeout', 'posts', 'required', 'status_codes',
				'follow_redirects', 'retrieve_mode'
			],
			'httptestids' => array_column($httptests, 'httptestid'),
			'nopermissions' => true,
			'preservekeys' => true
		]);

		$steps_create = [];
		$steps_update = [];
		$del_step_items = [];
		$httptest_itemids = [];

		$upd_test_items = [];
		$db_upd_test_items = [];

		foreach ($httptests as $key => $httptest) {
			$db_httptest = $db_httptests[$httptest['httptestid']];

			if (array_key_exists('delay', $httptest) && $db_httptest['delay'] != $httptest['delay']) {
				$httptest['nextcheck'] = 0;
			}

			DB::update('httptest', [
				'values' => $httptest,
				'where' => ['httptestid' => $httptest['httptestid']]
			]);

			if (!array_key_exists($httptest['httptestid'], $httptest_itemids)) {
				$httptest_itemids[$httptest['httptestid']] = [];
			}

			$status_update = false;
			$optional_delay = array_key_exists('delay', $httptest) ? 'i.delay,' : '';
			$optional_status = '';

			if (array_key_exists('status', $httptest)) {
				$status_update = (HTTPTEST_STATUS_ACTIVE == $httptest['status'])
					? ITEM_STATUS_ACTIVE
					: ITEM_STATUS_DISABLED;
				$optional_status = 'i.status,';
			}

			$db_test_items = DBselect(
				'SELECT i.itemid,i.type,i.name,i.key_,i.delay,'.$optional_status.$optional_delay.
					',i.flags,h.status AS host_status,hi.type AS test_type'.
				' FROM items i,hosts h,httptestitem hi'.
				' WHERE hi.itemid=i.itemid'.
					' AND h.hostid=i.hostid'.
					' AND '.dbConditionId('hi.httptestid', [$httptest['httptestid']])
			);

			while ($db_test_item = DBfetch($db_test_items)) {
				$httptest_itemids[$httptest['httptestid']][] = $db_test_item['itemid'];

				$upd_item = [
					'name' => $this->getTestName($db_test_item['test_type'], $httptest['name']),
					'key_' => $this->getTestKey($db_test_item['test_type'], $httptest['name']),
				];

				if ($status_update !== false) {
					$upd_item['status'] = $status_update;
				}

				if (array_key_exists('delay', $httptest)) {
					$upd_item['delay'] = $httptest['delay'];
				}

				unset($db_test_item['test_type']);

				$upd_test_items[] = $upd_item + $db_test_item;
				$db_upd_test_items[$upd_item['itemid']] = $db_test_item;
			}

			if (array_key_exists('steps', $httptest)) {
				$db_steps = zbx_toHash($db_httptest['steps'], 'httpstepid');

				foreach ($httptest['steps'] as $webstep) {
					if (!array_key_exists('httpstepid', $webstep)) {
						$steps_create[$key][] = $webstep;
					}
					elseif (array_key_exists($webstep['httpstepid'], $db_steps)) {
						$steps_update[$key][] = $webstep;
						unset($db_steps[$webstep['httpstepid']]);
					}
				}

				$del_stepids = array_keys($db_steps);

				if ($del_stepids) {
					$del_step_items += DBfetchArrayAssoc(DBselect(
						'SELECT i.itemid,i.name,i.flags'.
						' FROM httpstepitem hi'.
						' WHERE i.itemid=hi.itemid'.
							' AND '.dbConditionId('hi.httpstepid', $del_stepids)
					), 'itemid');

					DB::delete('httpstep', ['httpstepid' => $del_stepids]);
				}
			}
		}

		if ($upd_test_items) {
			API::getApiService('item')->updateForce($upd_test_items, $db_upd_test_items);
		}

		// Old items must be deleted prior to createStepsReal() since identical items cannot be created in DB.
		if ($del_step_items) {
			API::getApiService('item')->deleteForce($del_step_items);
		}

		foreach ($httptests as $key => $httptest) {
			if (array_key_exists('steps', $httptest)) {
				if (array_key_exists($key, $steps_update)) {
					$this->updateStepsReal($httptest, $steps_update[$key], $httptest_itemids[$httptest['httptestid']]);
				}

				if (array_key_exists($key, $steps_create)) {
					$this->createStepsReal($httptest, $steps_create[$key], $httptest_itemids[$httptest['httptestid']]);
				}
			}
			else {
				$this->updateStepItems($httptest, $db_httptests[$httptest['httptestid']]);
			}
		}

		$this->updateHttpTestFields($httptests, 'update');
		self::updateHttpTestTags($httptests);

		foreach ($httptests as $httptest) {
			$tags = array_key_exists('tags', $httptest) ? $httptest['tags'] : [];
			self::updateItemsTags($tags, $httptest_itemids[$httptest['httptestid']]);
		}

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
			'output' => ['httptestid', 'name', 'delay', 'status', 'agent', 'authentication',
				'http_user', 'http_password', 'hostid', 'templateid', 'http_proxy', 'retries', 'ssl_cert_file',
				'ssl_key_file', 'ssl_key_password', 'verify_peer', 'verify_host', 'variables', 'headers'
			],
			'hostids' => $templateId,
			'selectSteps' => ['httpstepid', 'name', 'no', 'url', 'timeout', 'posts', 'required', 'status_codes',
				'follow_redirects', 'retrieve_mode', 'variables', 'headers', 'query_fields'
			],
			'selectTags' => ['tag', 'value'],
			'preservekeys' => true,
			'nopermissions' => true
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

					if (bccomp($exHttpTest['templateid'], $httpTestId) == 0
							|| $exHttpTest['templateid'] != 0
							|| !$this->compareHttpSteps($httpTest, $exHttpTest)) {
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
				$newHttpTest['uuid'] = '';
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
				&& $http_test['delay'] === $ex_http_test['delay']);
	}

	/**
	 * Create linkage between two http tests.
	 * If we found existing http test by name and steps, we only add linkage, i.e. change templateid
	 *
	 * @param $parentid
	 * @param $childid
	 */
	protected function createLinkageBetweenHttpTests($parentid, $childid) {
		DB::update('httptest', [
			'values' => [
				'templateid' => $parentid,
				'uuid' => ''
			],
			'where' => ['httptestid' => $childid]
		]);

		$upd_items = [];
		$db_upd_items = [];

		$db_test_items = DBselect(
			'SELECT i1.itemid AS parentid,i2.itemid,i2.type,i2.name,i2.templateid,i2.flags,h.status AS host_status'.
			' FROM httptestitem hti1,httptestitem hti2,hosts h,items i1,items i2'.
			' WHERE hti1.itemid=i1.itemid'.
				' AND hti2.itemid=i2.itemid'.
				' AND i1.key_=i2.key_'.
				' AND h.hostid=i2.hostid'.
				' AND '.dbConditionId('hti1.httptestid', [$parentid]).
				' AND '.dbConditionId('hti2.httptestid', [$childid])
		);

		while ($db_test_item = DBfetch($db_test_items)) {
			$upd_item = ['templateid' => $db_test_item['parentid']];

			unset($db_test_item['parentid']);

			$upd_items[] = $upd_item + $db_test_item;
			$db_upd_items[$db_test_item['itemid']] = $db_test_item;
		}

		$db_step_items = DBselect(
			'SELECT i1.itemid AS parentid,i2.itemid,i2.type,i2.name,i2.templateid,i2.flags,h.status AS host_status'.
			' FROM httpstepitem hsi1,httpstepitem hsi2,hosts h,httpstep hs1,httpstep hs2,items i1,items i2'.
			' WHERE hsi1.itemid=i1.itemid'.
				' AND hsi2.itemid=i2.itemid'.
				' AND hs1.httpstepid=hsi1.httpstepid'.
				' AND hs2.httpstepid=hsi2.httpstepid'.
				' AND i1.key_=i2.key_'.
				' AND h.hostid=i2.itemid'.
				' AND '.dbConditionId('hs1.httptestid', [$parentid]).
				' AND '.dbConditionId('hs2.httptestid', [$childid])
		);

		while ($db_test_item = DBfetch($db_step_items)) {
			$upd_item = ['templateid' => $db_test_item['parentid']];

			unset($db_test_item['parentid']);

			$upd_items[] = $upd_item + $db_test_item;
			$db_upd_items[$db_test_item['itemid']] = $db_test_item;
		}

		if ($upd_items) {
			API::getApiService('item')->updateForce($upd_items, $db_upd_items);
		}
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
			'SELECT ht.httptestid,ht.name,ht.delay,ht.agent,ht.hostid,ht.templateid,ht.http_proxy,ht.retries'.
			' FROM httptest ht'.
			' WHERE '.dbConditionId('ht.hostid', $hostIds)
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
			 * Unset $http_tests and (later) put it back with actual httptestid as a key right after creating/updating
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
	 * @param array $http_test
	 * @param array  $_itemids
	 *
	 * @throws Exception
	 */
	protected function createHttpTestItems(array $http_test, array &$_itemids): void {
		$check_items = [
			[
				'name'				=> $this->getTestName(HTTPSTEP_ITEM_TYPE_IN, $http_test['name']),
				'key_'				=> $this->getTestKey(HTTPSTEP_ITEM_TYPE_IN, $http_test['name']),
				'value_type'		=> ITEM_VALUE_TYPE_FLOAT,
				'units'				=> 'Bps',
				'httptestitemtype'	=> HTTPSTEP_ITEM_TYPE_IN
			],
			[
				'name'				=> $this->getTestName(HTTPSTEP_ITEM_TYPE_LASTSTEP, $http_test['name']),
				'key_'				=> $this->getTestKey(HTTPSTEP_ITEM_TYPE_LASTSTEP, $http_test['name']),
				'value_type'		=> ITEM_VALUE_TYPE_UINT64,
				'units'				=> '',
				'httptestitemtype'	=> HTTPSTEP_ITEM_TYPE_LASTSTEP
			],
			[
				'name'				=> $this->getTestName(HTTPSTEP_ITEM_TYPE_LASTERROR, $http_test['name']),
				'key_'				=> $this->getTestKey(HTTPSTEP_ITEM_TYPE_LASTERROR, $http_test['name']),
				'value_type'		=> ITEM_VALUE_TYPE_STR,
				'units'				=> '',
				'httptestitemtype'	=> HTTPSTEP_ITEM_TYPE_LASTERROR
			]
		];

		// If this is a template scenario, fetch the parent HTTP items to link inherited items to them.
		$parent_items = [];

		if (array_key_exists('templateid', $http_test) && $http_test['templateid']) {
			$parent_items = DBfetchArrayAssoc(DBselect(
				'SELECT i.itemid,i.key_'.
					' FROM items i,httptestitem hti'.
					' WHERE i.itemid=hti.itemid'.
						' AND '.dbConditionId('hti.httptestid', [$http_test['templateid']])
			), 'key_');
		}

		if (!array_key_exists('host_status', $http_test)) {
			$http_test = DBfetch(DBselect(
				'SELECT h.status AS host_status'.
					' FROM hosts h'.
					' WHERE '.dbConditionId('h.hostid', [$http_test['hostid']])
			)) + $http_test;
		}

		$delay = array_key_exists('delay', $http_test) ? $http_test['delay'] : DB::getDefault('httptest', 'delay');
		$status = array_key_exists('status', $http_test) ? $http_test['status'] : DB::getDefault('httptest', 'status');

		$ins_items = [];

		foreach ($check_items as $item) {
			$item['hostid'] = $http_test['hostid'];
			$item['delay'] = $delay;
			$item['type'] = ITEM_TYPE_HTTPTEST;
			$item['history'] = self::ITEM_HISTORY;
			$item['trends'] = self::ITEM_TRENDS;
			$item['status'] = ($status == HTTPTEST_STATUS_ACTIVE)
				? ITEM_STATUS_ACTIVE
				: ITEM_STATUS_DISABLED;
			$item['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
			$item['host_status'] = $http_test['host_status'];

			if (array_key_exists($item['key_'], $parent_items)) {
				$item['templateid'] = $parent_items[$item['key_']]['itemid'];
			}

			$ins_items[] = $item;
		}

		API::getApiService('item')->createForce($ins_items);
		$itemids = array_column($ins_items, 'itemid');

		$ins_httptestitems = [];

		foreach ($check_items as $i => $item) {
			$ins_httptestitems[] = [
				'httptestid' => $http_test['httptestid'],
				'itemid' => $itemids[$i],
				'type' => $item['httptestitemtype']
			];
		}

		DB::insertBatch('httptestitem', $ins_httptestitems);
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

		foreach ($db_httptest_fields as $db_httptest_field) {
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

		foreach ($db_httpstep_fields as $db_httpstep_field) {
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
	 * @param array  $httptest
	 * @param array  $websteps
	 * @param array  $_itemids
	 *
	 * @throws Exception
	 */
	protected function createStepsReal(array $httptest, array $websteps, array &$_itemids): void {
		foreach ($websteps as &$webstep) {
			$webstep['httptestid'] = $httptest['httptestid'];

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
		// If this is a template scenario, fetch the parent HTTP items to link inherited items to them.
		$parent_step_items = [];

		if (array_key_exists('templateid', $httptest) && $httptest['templateid']) {
			$parent_step_items = DBfetchArrayAssoc(DBselect(
				'SELECT i.itemid,i.key_'.
				' FROM items i,httpstepitem hsi,httpstep hs'.
				' WHERE i.itemid=hsi.itemid'.
					' AND hsi.httpstepid=hs.httpstepid'.
					' AND '.dbConditionId('hs.httptestid', [$httptest['templateid']])
			), 'key_');
		}

		if (!array_key_exists('delay', $httptest) || !array_key_exists('status', $httptest)) {
			$httptest += DBfetch(DBselect(
				'SELECT ht.delay,ht.status'.
				' FROM httptest ht'.
				' WHERE '.dbConditionId('ht.httptestid', [$httptest['httptestid']])
			));
		}

		if (!array_key_exists('host_status', $httptest)) {
			$httptest = DBfetch(DBselect(
				'SELECT h.status AS host_status'.
				' FROM hosts h'.
				' WHERE '.dbConditionId('h.hostid', [$httptest['hostid']])
			)) + $httptest;
		}

		$ins_httpstepitems = [];

		foreach ($websteps as $i => &$webstep) {
			$webstep['httpstepid'] = $webstepids[$i];

			$step_items = [
				[
					'name' => $this->getStepName(HTTPSTEP_ITEM_TYPE_IN, $httptest['name'], $webstep['name']),
					'key_' => $this->getStepKey(HTTPSTEP_ITEM_TYPE_IN, $httptest['name'], $webstep['name']),
					'value_type' => ITEM_VALUE_TYPE_FLOAT,
					'units' => 'Bps',
					'httpstepitemtype' => HTTPSTEP_ITEM_TYPE_IN
				],
				[
					'name' => $this->getStepName(HTTPSTEP_ITEM_TYPE_TIME, $httptest['name'], $webstep['name']),
					'key_' => $this->getStepKey(HTTPSTEP_ITEM_TYPE_TIME, $httptest['name'], $webstep['name']),
					'value_type' => ITEM_VALUE_TYPE_FLOAT,
					'units' => 's',
					'httpstepitemtype' => HTTPSTEP_ITEM_TYPE_TIME
				],
				[
					'name' => $this->getStepName(HTTPSTEP_ITEM_TYPE_RSPCODE, $httptest['name'], $webstep['name']),
					'key_' => $this->getStepKey(HTTPSTEP_ITEM_TYPE_RSPCODE, $httptest['name'], $webstep['name']),
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'units' => '',
					'httpstepitemtype' => HTTPSTEP_ITEM_TYPE_RSPCODE
				]
			];

			$ins_items = [];

			foreach ($step_items as $item) {
				$item['hostid'] = $httptest['hostid'];
				$item['delay'] = $httptest['delay'];
				$item['type'] = ITEM_TYPE_HTTPTEST;
				$item['history'] = self::ITEM_HISTORY;
				$item['trends'] = self::ITEM_TRENDS;
				$item['status'] = ($httptest['status'] == HTTPTEST_STATUS_ACTIVE)
					? ITEM_STATUS_ACTIVE
					: ITEM_STATUS_DISABLED;
				$item['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
				$item['host_status'] = $httptest['host_status'];

				if (array_key_exists($item['key_'], $parent_step_items)) {
					$item['templateid'] = $parent_step_items[$item['key_']]['itemid'];
				}

				$ins_items[] = $item;
			}

			API::getApiService('item')->createForce($ins_items);
			$itemids = array_column($ins_items, 'itemid');

			foreach ($step_items as $i => $item) {
				$_itemids[] = $itemids[$i];

				$ins_httpstepitems[] = [
					'httpstepid' => $webstep['httpstepid'],
					'itemid' => $itemids[$i],
					'type' => $item['httpstepitemtype']
				];
			}
		}
		unset($webstep);

		DB::insertBatch('httpstepitem', $ins_httpstepitems);

		$this->updateHttpStepFields($websteps, 'create');
	}

	/**
	 * Update web scenario steps.
	 *
	 * @param array  $httptest
	 * @param array  $websteps
	 * @param array  $itemids_per_http_tests
	 *
	 * @throws Exception
	 */
	protected function updateStepsReal(array $httptest, array $websteps, array &$_itemids): void {
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

		$status_update = false;
		$optional_delay = array_key_exists('delay', $httptest) ? 'i.delay,' : '';
		$optional_status = '';

		if (array_key_exists('status', $httptest)) {
			$status_update = ($httptest['status'] == HTTPTEST_STATUS_ACTIVE)
				? ITEM_STATUS_ACTIVE
				: ITEM_STATUS_DISABLED;

			$optional_status .= 'i.status,';
		}

		$item_key_parser = new CItemKey();
		$upd_items= [];
		$db_upd_items = [];

		foreach ($websteps as $webstep) {
			DB::update('httpstep', [
				'values' => $webstep,
				'where' => ['httpstepid' => $webstep['httpstepid']]
			]);

			$db_step_items = DBselect(
				'SELECT i.itemid,i.type,i.name,i.key_,'.$optional_status.$optional_delay.
					'i.flags,h.status AS host_status,hi.type AS test_type'.
				' FROM items i,hosts h,httpstepitem hi'.
				' WHERE hi.itemid=i.itemid'.
					' AND h.hostid=i.hostid'.
					' AND '.dbConditionId('hi.httpstepid', [$webstep['httpstepid']])
			);

			while ($db_step_item = DBfetch($db_step_items)) {
				$_itemids[] = $db_step_item['itemid'];

				$upd_item = [];

				if (array_key_exists('delay', $httptest)) {
					$upd_item['delay'] = $httptest['delay'];
				}

				if ($status_update !== false) {
					$upd_item['status'] = $status_update;
				}

				$name_exists = [
					'http_test' => array_key_exists('name', $httptest) ,
					'webstep' => array_key_exists('name', $webstep)
				];

				if ($name_exists['http_test'] || $name_exists['webstep']) {
					if (!$name_exists['http_test'] || !$name_exists['webstep']) {
						$item_key_parser->parse($db_step_item['key_']);

						if (!$name_exists['http_test']) {
							$httptest['name'] = $item_key_parser->getParam(0);
						}

						if (!$name_exists['webstep']) {
							$webstep['name'] = $item_key_parser->getParam(1);
						}
					}

					$upd_item += [
						'name' => $this->getStepName($db_step_item['test_type'], $httptest['name'], $webstep['name']),
						'key_' => $this->getStepKey($db_step_item['test_type'], $httptest['name'], $webstep['name'])
					];
				}

				unset($db_step_item['test_type']);

				$upd_items[] = $upd_item + $db_step_item;
				$db_upd_items[$db_step_item['itemid']] = $db_step_item;
			}
		}

		if ($upd_items) {
			API::getApiService('item')->updateForce($upd_items, $db_upd_items);
		}

		$this->updateHttpStepFields($websteps, 'update');
	}

	/**
	 * Update web items after changes in web scenario.
	 * This should be used, when individual steps are not being updated.
	 *
	 * @param array $httptest
	 * @param array $db_httptest
	 */
	protected function updateStepItems(array $httptest, array $db_httptest): void {
		$db_websteps = zbx_toHash($db_httptest['steps'], 'httpstepid');
		$status_update = false;
		$optional_status = '';

		if (array_key_exists('status', $httptest)) {
			$status_update = ($httptest['status'] == HTTPTEST_STATUS_ACTIVE)
				? ITEM_STATUS_ACTIVE
				: ITEM_STATUS_DISABLED;
			$optional_status = 'i.status,';
		}

		$upd_items = [];
		$db_upd_items = [];

		$db_step_items = DBselect(
			'SELECT i.itemid,i.type,i.name,'.$optional_status.'i.flags,h.status AS host_status,'.
				'hsi.httpstepid,hsi.type AS test_type'.
			' FROM items i,hosts h,httpstepitem hsi'.
			' WHERE i.itemid=hsi.itemid'.
				' AND h.hostid=i.hostid'.
				' AND '.dbConditionId('hsi.httpstepid', array_column($db_httptest['steps'], 'httpstepid'))
		);

		while ($db_step_item = DBfetch($db_step_items)) {
			$upd_item = [];

			if ($status_update !== false) {
				$upd_item['status'] = $status_update;
			}

			if ($httptest['name'] !== $db_httptest['name']) {
				$db_webstep = $db_websteps[$db_step_item['httpstepid']];

				$upd_item += [
					'name' => $this->getStepName($db_step_item['test_type'], $httptest['name'], $db_webstep['name']),
					'key_' => $this->getStepKey($db_step_item['test_type'], $httptest['name'], $db_webstep['name'])
				];
			}

			if ($upd_item) {
				unset($db_step_item['httpstepid']);
				unset($db_step_item['test_type']);

				$upd_items[] = $upd_item + $db_step_item;
				$db_upd_items[$db_step_item['itemid']] = $db_step_item;
			}
		}

		if ($upd_items) {
			API::getApiService('item')->updateForce($upd_items, $db_upd_items);
		}
	}

	/**
	 * Create tags for http test and http test step items.
	 * All items should belong to the same http test and should have same set of tags.
	 *
	 * @static
	 *
	 * @param array $tags     New tags to save.
	 * @param array $itemids  List of itemids.
	 */
	protected static function createItemsTags(array $tags, array $itemids): void {
		$new_tags = [];
		foreach ($tags as $tag) {
			foreach ($itemids as $itemid) {
				$new_tags[] = $tag + ['itemid' => $itemid];
			}
		}

		DB::insert('item_tag', $new_tags);
	}

	/**
	 * Update step item tags.
	 * Function assumes that all step items has same set of tags. Not suitable for steps from different web scenarios.
	 *
	 * @static
	 *
	 * @param array $tags         New tags to save.
	 * @param array $stepitemids  List of step itemids to update.
	 */
	protected static function updateItemsTags(array $tags, array $stepitemids): void {
		// Select tags from database.
		$db_tags_raw = DB::select('item_tag', [
			'output' => ['itemtagid', 'tag', 'value', 'itemid'],
			'filter' => ['itemid' => $stepitemids]
		]);
		$db_tags = [];
		foreach ($db_tags_raw as $tag) {
			$db_tags[$tag['itemid']][$tag['tag']][$tag['value']] = $tag['itemtagid'];
		}

		// Make array with new tags.
		$item_tags_add = array_fill_keys($stepitemids, $tags);

		// Unset tags which don't need to add/delete.
		foreach ($db_tags as $stepitemid => $item_tags_del) {
			foreach ($item_tags_add[$stepitemid] as $new_tag_key => $tag_add) {
				if (array_key_exists($tag_add['tag'], $item_tags_del)
						&& array_key_exists($tag_add['value'], $item_tags_del[$tag_add['tag']])) {
					unset($item_tags_add[$stepitemid][$new_tag_key],
						$db_tags[$stepitemid][$tag_add['tag']][$tag_add['value']]
					);
				}
			}
		}

		// Delete tags.
		$del_tagids = [];
		foreach ($db_tags as $db_tag) {
			foreach ($db_tag as $db_tagids) {
				if ($db_tagids) {
					$del_tagids = array_merge($del_tagids, array_values($db_tagids));
				}
			}
		}
		if ($del_tagids) {
			DB::delete('item_tag', ['itemtagid' => $del_tagids]);
		}

		// Add new tags.
		$new_tags = [];
		foreach ($item_tags_add as $stepitemid => $tags) {
			foreach ($tags as $tag) {
				$tag['itemid'] = $stepitemid;
				$new_tags[] = $tag;
			}
		}
		if ($new_tags) {
			DB::insert('item_tag', $new_tags);
		}
	}

	/**
	 * Get item key for test item.
	 *
	 * @param int    $type
	 * @param string $test_name
	 *
	 * @return string
	 */
	protected function getTestKey(int $type, string $test_name): string {
		switch ($type) {
			case HTTPSTEP_ITEM_TYPE_IN:
				return 'web.test.in['.quoteItemKeyParam($test_name).',,bps]';
			case HTTPSTEP_ITEM_TYPE_LASTSTEP:
				return 'web.test.fail['.quoteItemKeyParam($test_name).']';
			case HTTPSTEP_ITEM_TYPE_LASTERROR:
				return 'web.test.error['.quoteItemKeyParam($test_name).']';
		}

		return 'unknown';
	}

	/**
	 * Get item name for test item.
	 *
	 * @param int    $type
	 * @param string $test_name
	 *
	 * @return string
	 */
	protected function getTestName(int $type, string $test_name): string {
		switch ($type) {
			case HTTPSTEP_ITEM_TYPE_IN:
				return 'Download speed for scenario "'.$test_name.'".';
			case HTTPSTEP_ITEM_TYPE_LASTSTEP:
				return 'Failed step of scenario "'.$test_name.'".';
			case HTTPSTEP_ITEM_TYPE_LASTERROR:
				return 'Last error message of scenario "'.$test_name.'".';
		}

		return 'unknown';
	}

	/**
	 * Get item key for step item.
	 *
	 * @param int    $type
	 * @param string $test_name
	 * @param string $step_name
	 *
	 * @return string
	 */
	protected function getStepKey(int $type, string $test_name, string $step_name): string {
		switch ($type) {
			case HTTPSTEP_ITEM_TYPE_IN:
				return 'web.test.in['.quoteItemKeyParam($test_name).','.quoteItemKeyParam($step_name).',bps]';
			case HTTPSTEP_ITEM_TYPE_TIME:
				return 'web.test.time['.quoteItemKeyParam($test_name).','.quoteItemKeyParam($step_name).',resp]';
			case HTTPSTEP_ITEM_TYPE_RSPCODE:
				return 'web.test.rspcode['.quoteItemKeyParam($test_name).','.quoteItemKeyParam($step_name).']';
		}

		return 'unknown';
	}

	/**
	 * Get item name for step item.
	 *
	 * @param int    $type
	 * @param string $test_name
	 * @param string $step_name
	 *
	 * @return string
	 */
	protected function getStepName(int $type, string $test_name, string $step_name): string {
		switch ($type) {
			case HTTPSTEP_ITEM_TYPE_IN:
				return 'Download speed for step "'.$step_name.'" of scenario "'.$test_name.'".';
			case HTTPSTEP_ITEM_TYPE_TIME:
				return 'Response time for step "'.$step_name.'" of scenario "'.$test_name.'".';
			case HTTPSTEP_ITEM_TYPE_RSPCODE:
				return 'Response code for step "'.$step_name.'" of scenario "'.$test_name.'".';
		}

		return 'unknown';
	}

	/**
	 * Returns the data about the last execution of the given HTTP tests.
	 *
	 * The following values will be returned for each executed HTTP test:
	 * - lastcheck      - time when the test has been executed last
	 * - lastfailedstep - number of the last failed step
	 * - error          - error message
	 *
	 * If a HTTP test has not been executed in last CSettingsHelper::HISTORY_PERIOD, no value will be returned.
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

		$history = Manager::History()->getLastValues($httpItems, 1, timeUnitToSeconds(CSettingsHelper::get(
			CSettingsHelper::HISTORY_PERIOD
		)));

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

	/**
	 * Record web scenario tags into database.
	 *
	 * @static
	 *
	 * @param array  $http_tests
	 * @param array  $http_tests[]['tags']
	 * @param string $http_tests[]['tags'][]['tag']
	 * @param string $http_tests[]['tags'][]['value']
	 * @param string $http_tests[]['httptestid']
	 */
	protected static function createHttpTestTags(array $http_tests): void {
		$new_tags = [];
		foreach ($http_tests as $http_test) {
			if (!array_key_exists('tags', $http_test)) {
				continue;
			}

			foreach ($http_test['tags'] as $tag) {
				$new_tags[] = $tag + ['httptestid' => $http_test['httptestid']];
			}
		}

		if ($new_tags) {
			DB::insert('httptest_tag', $new_tags);
		}
	}

	/**
	 * Update web scenario tags.
	 *
	 * @static
	 *
	 * @param array  $http_tests
	 * @param string $http_tests[]['httptestid']
	 * @param array  $http_tests[]['tags']
	 * @param string $http_tests[]['tags'][]['tag']
	 * @param string $http_tests[]['tags'][]['value']
	 */
	protected static function updateHttpTestTags(array $http_tests): void {
		$http_tests = zbx_toHash($http_tests, 'httptestid');

		// Select tags from database.
		$db_httptest_tags_raw = DB::select('httptest_tag', [
			'output' => ['httptesttagid', 'httptestid', 'tag', 'value'],
			'filter' => ['httptestid' => array_column($http_tests, 'httptestid')]
		]);

		$db_httptest_tags = [];
		foreach ($db_httptest_tags_raw as $tag) {
			$db_httptest_tags[$tag['httptestid']][] = $tag;
		}

		// Find which tags must be added/deleted.
		$del_tagids = [];
		foreach ($db_httptest_tags as $httptestid => $db_tags) {
			if (!array_key_exists('tags', $http_tests[$httptestid])) {
				continue;
			}

			foreach ($db_tags as $del_tag_key => $tag_delete) {
				foreach ($http_tests[$httptestid]['tags'] as $new_tag_key => $tag_add) {
					if ($tag_delete['tag'] === $tag_add['tag'] && $tag_delete['value'] === $tag_add['value']) {
						unset($db_tags[$del_tag_key], $http_tests[$httptestid]['tags'][$new_tag_key]);
						continue 2;
					}
				}
			}

			if ($db_tags) {
				$del_tagids = array_merge($del_tagids, array_column($db_tags, 'httptesttagid'));
			}
		}

		$new_tags = [];
		foreach ($http_tests as $httptestid => $http_test) {
			if (!array_key_exists('tags', $http_test)) {
				continue;
			}

			foreach ($http_test['tags'] as $tag) {
				$tag['httptestid'] = $httptestid;
				$new_tags[] = $tag;
			}
		}

		if ($del_tagids) {
			DB::delete('httptest_tag', ['httptesttagid' => $del_tagids]);
		}
		if ($new_tags) {
			DB::insert('httptest_tag', $new_tags);
		}
	}
}
