<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


/**
 * Class to perform low level http tests related actions.
 */
class CHttpTestManager {

	private $history = 30;
	private $trends = 90;

	/**
	 * @var array
	 */
	protected $originalHttpTests = array();

	/**
	 * Array of changed steps names.
	 * array(
	 *   testid1 => array(nameold1 => namenew1, nameold2 => namenew2),
	 *   ...
	 * )
	 *
	 * @var array
	 */
	protected $changedSteps = array();

	/**
	 * Map of parent http test id to child http test id.
	 *
	 * @var array
	 */
	protected $httpTestParents = array();


	public function __construct(array $httpTests) {
		$this->originalHttpTests = $httpTests;
	}

	public function persist() {
		$this->findChangedStep();

		$HttpTests = $this->save($this->originalHttpTests);
		$this->inherit($HttpTests);
	}

	protected function findChangedStep() {
		$httpSteps = array();
		foreach ($this->originalHttpTests as $httpTest) {
			if (isset($httpTest['httptestid']) && isset($httpTest['steps'])) {
				foreach ($httpTest['steps'] as $step) {
					if (isset($step['httpstepid']) && isset($step['name'])) {
						$httpSteps[$step['httpstepid']] = $step['name'];
					}
				}
			}
		}

		if (!empty($httpSteps)) {
			$dbCursor = DBselect('SELECT hs.httpstepid,hs.httptestid,hs.name'.
					' FROM httpstep hs'.
					' WHERE '.DBcondition('hs.httpstepid', array_keys($httpSteps)));
			while ($dbStep = DBfetch($dbCursor)) {
				if ($httpSteps[$dbStep['httpstepid']] != $dbStep['name']) {
					$this->changedSteps[$dbStep['httptestid']][$httpSteps[$dbStep['httpstepid']]] = $dbStep['name'];
				}
			}
		}
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

		foreach ($httpTests as $hnum => $httpTest) {
			$httpTests[$hnum]['httptestid'] = $httpTestIds[$hnum];

			$httpTest['httptestid'] = $httpTestIds[$hnum];
			$this->createHttpTestItems($httpTest);
			$this->createStepsReal($httpTest, $httpTest['steps']);
		}

		return $httpTests;
	}

	/**
	 * Update http tests.
	 *
	 * @param array $httpTests
	 *
	 * @return array
	 */
	public function update(array $httpTests) {
		$httpTestIds = zbx_objectValues($httpTests, 'httptestid');
		$dbHttpTest = API::HttpTest()->get(array(
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
						' WHERE hi.httptestid='.$httpTest['httptestid'].
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
				if ($httpTest['applicationid'] == 0) {
					DB::delete('items_applications', array('itemid' => $itemids));
				}
				else {
					DB::update('items_applications', array(
						'values' => array('applicationid' => $httpTest['applicationid']),
						'where' => array('itemid' => $itemids)
					));
				}
			}


			// update steps
			if (isset($httpTest['steps'])) {
				$stepsCreate = $stepsUpdate = array();
				$dbSteps = zbx_toHash($dbHttpTest[$httpTest['httptestid']]['steps'], 'httpstepid');
				foreach ($httpTest['steps'] as $webstep) {
					if (isset($webstep['httpstepid']) && isset($dbSteps[$webstep['httpstepid']])) {

						$stepsUpdate[] = $webstep;
						unset($dbSteps[$webstep['httpstepid']]);
					}
					elseif (!isset($webstep['httpstepid'])) {
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
		}

		return $httpTests;
	}

	/**
	 * Link http tests in template to hosts.
	 *
	 * @param $templateId
	 * @param $hostIds
	 *
	 * @return bool
	 */
	public function link($templateId, $hostIds) {
		$hostIds = zbx_toArray($hostIds);

		$httpTests = array();
		$dbCursor = DBselect('SELECT ht.httptestid,ht.name,ht.applicationid,ht.delay,ht.status,ht.macros,ht.agent,'.
			'ht.authentication,ht.http_user,ht.http_password,ht.hostid,ht.templateid'.
			' FROM httptests ht'.
			' WHERE ht.hostid='.zbx_dbstr($templateId));
		while ($dbHttpTest = DBfetch($dbCursor)) {
			$httpTests[$dbHttpTest['httptestid']] = $dbHttpTest;
		}

		$dbCursor = DBselect('SELECT hs.httpstepid,hs.httptestid,hs.name,hs.no,hs.url,hs.timeout,hs.posts,hs.required,'.
				'hs.status_code'.
				' FROM httpstep hs'.
				' WHERE '.DBcondition('hs.httptestid', array_keys($httpTests)));
		while ($dbHttpStep = DBfetch($dbCursor)) {
			$httpTests[$dbHttpStep['httptestid']]['steps'][] = $dbHttpStep;
		}

		$this->inherit($httpTests, $hostIds);

		return true;
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
	public function inherit(array $httpTests, array $hostIds = array()) {
		$hostsTemaplatesMap = $this->getChildHostsFromHttpTests($httpTests, $hostIds);
		if (empty($hostsTemaplatesMap)) {
			return true;
		}

		$preparedHttpTests = $this->prepareInheritedHttpTests($httpTests, $hostsTemaplatesMap);
		$inheritedHttpTests = $this->save($preparedHttpTests);
		$this->inherit($inheritedHttpTests);

		return true;
	}

	/**
	 * Get array with hosts that are linked with templates which passed http tests belongs to as key and templateid that host
	 * is linked to as value.
	 * If second parameter $hostIds is not empty, result should contain only passed host ids.
	 *
	 * @param array $httpTests
	 * @param array $hostIds
	 *
	 * @return array
	 */
	protected function getChildHostsFromHttpTests(array $httpTests, array $hostIds = array()) {
		$hostsTemaplatesMap = array();

		$sqlWhere = empty($hostIds) ? '' : ' AND '.DBcondition('ht.hostid', $hostIds);
		$dbCursor = DBselect('SELECT ht.templateid, ht.hostid'.
				' FROM hosts_templates ht'.
				' WHERE '.DBcondition('ht.templateid', array_unique(zbx_objectValues($httpTests, 'hostid'))).
				$sqlWhere);
		while ($dbHost = DBfetch($dbCursor)) {
			$hostsTemaplatesMap[$dbHost['hostid']] = $dbHost['templateid'];
		}

		return $hostsTemaplatesMap;
	}

	/**
	 * Generate http tests data for inheritance.
	 * Using passed parameters decide if new http tests must be created on host or existing one must be updated.
	 *
	 * @param array $httpTests which we need to inherit
	 * @param array $hostsTemaplatesMap
	 *
	 * @throws Exception
	 * @return array with http tests, existing apps have 'httptestid' key.
	 */
	protected function prepareInheritedHttpTests(array $httpTests, array $hostsTemaplatesMap) {
		$hostHttpTests = $this->getHttpTestsMapsByHostIds(array_keys($hostsTemaplatesMap));

		$result = array();
		foreach ($httpTests as $httpTest) {
			$httpTestId = $httpTest['httptestid'];
			foreach ($hostHttpTests as $hostId => $hostHttpTest) {
				// if http test template is not linked to host we skip it
				if ($hostsTemaplatesMap[$hostId] != $httpTest['hostid']) {
					continue;
				}

				$exHttpTest = null;
				// update by templateid
				if (isset($hostHttpTest['byTemplateId'][$httpTestId])) {
					$exHttpTest = $hostHttpTest['byTemplateId'][$httpTestId];

					// need to check templateid here too in case we update linked http test to name that already exists on linked host
					if (isset($hostHttpTest['byName'][$httpTest['name']])
							&& !idcmp($exHttpTest['templateid'], $hostHttpTest['byName'][$httpTest['name']]['templateid'])) {
						$host = DBfetch(DBselect('SELECT h.name FROM hosts h WHERE h.hostid='.zbx_dbstr($hostId)));
						throw new Exception(_s('Http test "%1$s" already exists on host "%2$s".', $exHttpTest['name'], $host['name']));
					}
				}
				// update by name
				else if (isset($hostHttpTest['byName'][$httpTest['name']])) {
					$exHttpTest = $hostHttpTest['byName'][$httpTest['name']];
					if ($exHttpTest['templateid'] > 0 || !$this->compareHttpSteps($httpTest, $exHttpTest)) {
						$host = DBfetch(DBselect('SELECT h.name FROM hosts h WHERE h.hostid='.zbx_dbstr($hostId)));
						throw new Exception(_s('Http test "%1$s" already exists on host "%2$s".', $exHttpTest['name'], $host['name']));
					}

					// if we found existing http test by name and steps, we only add linkage, i.e. change templateid
					// so we unset all data for http test we link, templateid is assigned later
					$httpTest = array();
				}

//				if ($this->checkIfItemsRequireChenged($httpTest)) {
//					$this->checkHttpTestItems($httpTest);
//				}

				$newHttpTest = $httpTest;
				$newHttpTest['hostid'] = $hostId;
				$newHttpTest['templateid'] = $httpTestId;
				if ($exHttpTest) {
					$newHttpTest['httptestid'] = $exHttpTest['httptestid'];

					$parentHttpTest = $httpTestId;
					while (isset($this->httpTestParents[$parentHttpTest])) {
						$parentHttpTest = $this->httpTestParents[$parentHttpTest];
					}

					$this->httpTestParents[$exHttpTest['httptestid']] = $parentHttpTest;

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
		$hostHttpTests = array();
		foreach ($hostIds as $hostid) {
			$hostHttpTests[$hostid] = array('byName' => array(), 'byTemplateId' => array());
		}

		$dbCursor = DBselect('SELECT ht.httptestid,ht.name,ht.hostid,ht.templateid'.
				' FROM httptest ht'.
				' WHERE '.DBcondition('ht.hostid', $hostIds));
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
		$firstHash = $secondHash = '';

		foreach ($httpTest['steps'] as $step) {
			$firstHash .= $step['no'].$step['name'];
		}

		$dbCursor = DBselect('SELECT hs.name,hs.no'.
			' FROM httpstep hs'.
			' WHERE hs.httptestid='.zbx_dbstr($exHttpTest['httptestid']).
			' ORDER BY hs.no');
		while ($dbHttpStep = DBfetch($dbCursor)) {
			$secondHash .= $dbHttpStep['no'].$dbHttpStep['name'];
		}

		return $firstHash == $secondHash;
	}

	/**
	 * Check if http test data requires http items change.
	 * Changes are required if:
	 *  - http test name changes
	 *  - step name changes
	 *
	 * @param array $httpTest
	 *
	 * @return bool
	 */
	protected function checkIfItemsRequireChenged(array $httpTest) {

		return true;
	}

	/**
	 * Save http tests. If http test has httptestid it gets updated otherwise a new one is created.
	 *
	 * @param array $httpTests
	 *
	 * @return array
	 */
	protected function save(array $httpTests) {
		$httpTestsCreate = array();
		$httpTestsUpdate = array();

		foreach ($httpTests as $httpTest) {
			if (isset($httpTest['httptestid'])) {
				$httpTestsUpdate[] = $httpTest;
			}
			else {
				$httpTestsCreate[] = $httpTest;
			}
		}

		if (!empty($httpTestsCreate)) {
			$newHttpTests = $this->create($httpTestsCreate, true);
			foreach ($newHttpTests as $num => $newHttpTest) {
				$httpTests[$num]['httptestid'] = $newHttpTest['httptestid'];
			}
		}
		if (!empty($httpTestsUpdate)) {
			$this->update($httpTestsUpdate);
		}

		return $httpTests;
	}

	/**
	 * @param array $steps
	 * @param $exHttpTestId
	 *
	 * @return array
	 */
	protected function prepareHttpSteps(array $steps, $exHttpTestId) {
		$exSteps = array();
		$dbCursor = DBselect('SELECT hs.httpstepid,hs.name'.
			' FROM httpstep hs'.
			' WHERE hs.httptestid='.zbx_dbstr($exHttpTestId));
		while ($dbHttpStep = DBfetch($dbCursor)) {
			$exSteps[$dbHttpStep['name']] = $dbHttpStep['httpstepid'];
		}

		$result = array();
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
				throw new Exception(_s('Item with key "%s" already exists.', $item['key_']));
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

		$itemApplications = array();
		foreach ($checkItemids as $itemid) {
			if (!empty($httpTest['applicationid'])) {
				$itemApplications[] = array(
					'applicationid' => $httpTest['applicationid'],
					'itemid' => $itemid
				);
			}
		}
		if (!empty($itemApplications)) {
			DB::insert('items_applications', $itemApplications);
		}


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
	 *
	 * @throws Exception
	 */
	protected function createStepsReal($httpTest, $websteps) {
		$webstepsNames = zbx_objectValues($websteps, 'name');

		if (!preg_grep('/'.ZBX_PREG_PARAMS.'/i', $webstepsNames)) {
			throw new Exception(_('Scenario step name should contain only printable characters.'));
		}

		$sql = 'SELECT h.httpstepid,h.name'.
				' FROM httpstep h'.
				' WHERE h.httptestid='.$httpTest['httptestid'].
				' AND '.DBcondition('h.name', $webstepsNames);
		if ($httpstepData = DBfetch(DBselect($sql))) {
			throw new Exception(_s('Step "%s" already exists.', $httpstepData['name']));
		}

		foreach ($websteps as $snum => $webstep) {
			$websteps[$snum]['httptestid'] = $httpTest['httptestid'];
			if ($webstep['no'] <= 0) {
				throw new Exception(_('Scenario step number cannot be less than 1.'));
			}
		}
		$webstepids = DB::insert('httpstep', $websteps);

		foreach ($websteps as $snum => $webstep) {
			$webstepid = $webstepids[$snum];

			$stepitems = array(
				array(
					'name' => _s('Download speed for step "%1$s" of scenario "%2$s".', '$2', '$1'),
					'key_' => 'web.test.in['.$httpTest['name'].','.$webstep['name'].',bps]',
					'value_type' => ITEM_VALUE_TYPE_FLOAT,
					'units' => 'Bps',
					'httpstepitemtype' => HTTPSTEP_ITEM_TYPE_IN
				),
				array(
					'name' => _s('Response time for step "%1$s" of scenario "%2$s".', '$2', '$1'),
					'key_' => 'web.test.time['.$httpTest['name'].','.$webstep['name'].',resp]',
					'value_type' => ITEM_VALUE_TYPE_FLOAT,
					'units' => 's',
					'httpstepitemtype' => HTTPSTEP_ITEM_TYPE_TIME
				),
				array(
					'name' => _s('Response code for step "%1$s" of scenario "%2$s".', '$2', '$1'),
					'key_' => 'web.test.rspcode['.$httpTest['name'].','.$webstep['name'].']',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'units' => '',
					'httpstepitemtype' => HTTPSTEP_ITEM_TYPE_RSPCODE
				)
			);
			foreach ($stepitems as &$item) {
				$itemsExist = API::Item()->exists(array(
					'key_' => $item['key_'],
					'hostid' => $httpTest['hostid']
				));
				if ($itemsExist) {
					throw new Exception(_s('Web item with key "%s" already exists.', $item['key_']));
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
				if (!empty($httpTest['applicationid'])) {
					$itemApplications[] = array(
						'applicationid' => $httpTest['applicationid'],
						'itemid' => $itemid
					);
				}
			}
			if (!empty($itemApplications)) {
				DB::insert('items_applications', $itemApplications);
			}


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
	 *
	 * @throws Exception
	 */
	protected function updateStepsReal($httpTest, $websteps) {
		$webstepsNames = zbx_objectValues($websteps, 'name');

		if (!preg_grep('/'.ZBX_PREG_PARAMS.'/i', $webstepsNames)) {
			throw new Exception(_('Scenario step name should contain only printable characters.'));
		}

		// get all used keys
		$webstepids = zbx_objectValues($websteps, 'httpstepid');
		$dbKeys = DBfetchArray(DBselect(
			'SELECT i.key_'.
					' FROM items i,httpstepitem hi'.
					' WHERE '.DBcondition('hi.httpstepid', $webstepids).
					' AND hi.itemid=i.itemid'
		));
		$dbKeys = zbx_toHash($dbKeys, 'key_');

		foreach ($websteps as $webstep) {
			if ($webstep['no'] <= 0) {
				throw new Exception(_('Scenario step number cannot be less than 1.'));
			}

			DB::update('httpstep', array(
				'values' => $webstep,
				'where' => array('httpstepid' => $webstep['httpstepid'])
			));

			// update item keys
			$itemids = array();
			$stepitemsUpdate = $updateFields = array();
			$dbStepItems = DBselect(
				'SELECT i.itemid,hi.type'.
						' FROM items i,httpstepitem hi'.
						' WHERE hi.httpstepid='.$webstep['httpstepid'].
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
				if ($httpTest['applicationid'] == 0) {
					DB::delete('items_applications', array('itemid' => $itemids));
				}
				else {
					DB::update('items_applications', array(
						'values' => array('applicationid' => $httpTest['applicationid']),
						'where' => array('itemid' => $itemids)
					));
				}
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
					' WHERE '.DBcondition('hi.httpstepid', $webstepids).
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
}
