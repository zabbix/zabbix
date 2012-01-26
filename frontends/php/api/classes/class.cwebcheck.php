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
 * @package API
 */
class CWebCheck extends CZBXAPI {
	protected $tableName = 'httptest';
	protected $tableAlias = 'ht';
	private $history = 30;
	private $trends = 90;

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

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['hostid'] = 'a.hostid';
			}
			$sqlParts['from']['applications'] = 'applications a';
			$sqlParts['where'][] = 'a.applicationid=ht.applicationid';
			$sqlParts['where']['hostid'] = DBcondition('a.hostid', $options['hostids']);

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

		$webcheckids = array();

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
		while ($webcheck = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $webcheck;
				}
				else {
					$result = $webcheck['rowscount'];
				}
			}
			else {
				$webcheck['webcheckid'] = $webcheck['httptestid'];
				unset($webcheck['httptestid']);
				$webcheckids[$webcheck['webcheckid']] = $webcheck['webcheckid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$webcheck['webcheckid']] = array('webcheckid' => $webcheck['webcheckid']);
				}
				else {
					if (!isset($result[$webcheck['webcheckid']])) {
						$result[$webcheck['webcheckid']] = array();
					}
					if (!is_null($options['selectHosts']) && !isset($result[$webcheck['webcheckid']]['hosts'])) {
						$result[$webcheck['webcheckid']]['hosts'] = array();
					}
					if (!is_null($options['selectSteps']) && !isset($result[$webcheck['webcheckid']]['steps'])) {
						$result[$webcheck['webcheckid']]['steps'] = array();
					}

					// hostids
					if (isset($webcheck['hostid']) && is_null($options['selectHosts'])) {
						if (!isset($result[$webcheck['webcheckid']]['hosts'])) {
							$result[$webcheck['webcheckid']]['hosts'] = array();
						}
						$result[$webcheck['webcheckid']]['hosts'][] = array('hostid' => $webcheck['hostid']);
						unset($webcheck['hostid']);
					}
					$result[$webcheck['webcheckid']] += $webcheck;
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
				'webcheckids' => $webcheckids,
				'nopermissions' => true,
				'preservekeys' => true
			);
			$hosts = API::Host()->get($objParams);
			foreach ($hosts as $hostid => $host) {
				$hwebchecks = $host['webchecks'];
				unset($host['webchecks']);
				foreach ($hwebchecks as $hwebcheck) {
					$result[$hwebcheck['webcheckid']]['hosts'][] = $host;
				}
			}
		}

		// adding steps
		if (!is_null($options['selectSteps']) && str_in_array($options['selectSteps'], $subselectsAllowedOutputs)) {
			$dbSteps = DBselect(
				'SELECT h.*'.
				' FROM httpstep h'.
				' WHERE '.DBcondition('h.httptestid', $webcheckids)
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

	public function create($webchecks) {
		$webchecks = zbx_toArray($webchecks);
		$webcheckNames = zbx_objectValues($webchecks, 'name');

		if (!preg_grep('/^(['.ZBX_PREG_PRINT.'])+$/u', $webcheckNames)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Only characters are allowed.'));
		}

		$dbWebchecks = $this->get(array(
			'filter' => array('name' => $webcheckNames),
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => true,
			'preservekeys' => true
		));
		foreach ($dbWebchecks as $webcheck) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Scenario "%s" already exists.', $webcheck['name']));
		}

		$webcheckids = DB::insert('httptest', $webchecks);

		foreach ($webchecks as $wnum => $webcheck) {
			if (empty($webcheck['steps'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Webcheck must have at least one step.'));
			}
			$webcheck['webcheckid'] = $webcheckids[$wnum];

			$this->createCheckItems($webcheck);
			$this->createStepsReal($webcheck, $webcheck['steps']);
		}
		return array('webcheckids' => $webcheckids);
	}

	public function update($webchecks) {
		$webchecks = zbx_toArray($webchecks);
		$webcheckids = zbx_objectValues($webchecks, 'webcheckid');

		$dbWebchecks = $this->get(array(
			'output' => API_OUTPUT_EXTEND,
			'httptestids' => $webcheckids,
			'selectSteps' => API_OUTPUT_EXTEND,
			'editable' => true,
			'preservekeys' => true
		));

		foreach ($webchecks as $webcheck) {
			if (!isset($dbWebchecks[$webcheck['webcheckid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('You do not have permission to perform this operation.'));
			}

			if (!check_db_fields(array('webcheckid' => null), $webcheck)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			if (isset($webcheck['name'])) {
				if (!preg_match('/^(['.ZBX_PREG_PRINT.'])+$/u', $webcheck['name'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Only characters are allowed.'));
				}

				$webcheckExist = $this->get(array(
					'filter' => array('name' => $webcheck['name']),
					'preservekeys' => true,
					'nopermissions' => true,
					'output' => API_OUTPUT_SHORTEN
				));
				$webcheckExist = reset($webcheckExist);

				if ($webcheckExist && (bccomp($webcheckExist['webcheckid'], $webcheckExist['webcheckid']) != 0)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Scenario "%s" already exists.', $webcheck['name']));
				}
			}

			$webcheck['curstate'] = HTTPTEST_STATE_UNKNOWN;
			$webcheck['error'] = '';

			DB::update('httptest', array(
				'values' => $webcheck,
				'where' => array('httptestid' => $webcheck['webcheckid'])
			));

			$checkItemsUpdate = $updateFields = array();
			$dbCheckItems = DBselect(
				'SELECT i.itemid,hi.type'.
				' FROM items i,httptestitem hi'.
				' WHERE hi.httptestid='.$webcheck['webcheckid'].
					' AND hi.itemid=i.itemid'
			);
			while ($checkitem = DBfetch($dbCheckItems)) {
				$itemids[] = $checkitem['itemid'];

				if (isset($webcheck['name'])) {
					switch ($checkitem['type']) {
						case HTTPSTEP_ITEM_TYPE_IN:
							$updateFields['key_'] = 'web.test.in['.$webcheck['name'].',,bps]';
							break;
						case HTTPSTEP_ITEM_TYPE_LASTSTEP:
							$updateFields['key_'] = 'web.test.fail['.$webcheck['name'].']';
							break;
					}
				}

				if (isset($webcheck['status'])) {
					$updateFields['status'] = $webcheck['status'];
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
			if (isset($webcheck['applicationid'])) {
				DB::update('items_applications', array(
					'values' => array('applicationid' => $webcheck['applicationid']),
					'where' => array('itemid' => $itemids)
				));
			}

			// update steps
			$stepsCreate = $stepsUpdate = array();
			$dbSteps = zbx_toHash($dbWebchecks[$webcheck['webcheckid']]['steps'], 'webstepid');

			foreach ($webcheck['steps'] as $webstep) {
				if (isset($webstep['webstepid']) && isset($dbSteps[$webstep['webstepid']])) {
					$stepsUpdate[] = $webstep;
					unset($dbSteps[$webstep['webstepid']]);
				}
				elseif (!isset($webstep['webstepid'])) {
					$stepsCreate[] = $webstep;
				}
			}
			$stepidsDelete = array_keys($dbSteps);

			if (!empty($stepsCreate)) {
				$this->createStepsReal($webcheck, $stepsCreate);
			}
			if (!empty($stepsUpdate)) {
				$this->updateStepsReal($webcheck, $stepsUpdate);
			}
			if (!empty($stepidsDelete)) {
				$this->deleteStepsReal($stepidsDelete);
			}
		}
		return array('webchekids' => $webcheckids);
	}

	public function delete($webcheckids) {
		if (empty($webcheckids)) {
			return true;
		}
		$webcheckids = zbx_toArray($webcheckids);

		$delWebchecks = $this->get(array(
			'httptestids' => $webcheckids,
			'output' => API_OUTPUT_EXTEND,
			'editable' => true,
			'selectHosts' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
		foreach ($webcheckids as $webcheckid) {
			if (!isset($delWebchecks[$webcheckid])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$itemidsDel = array();
		$dbTestItems = DBselect(
			'SELECT hsi.itemid'.
			' FROM httptestitem hsi'.
			' WHERE '.DBcondition('hsi.httptestid', $webcheckids)
		);
		while ($testitem = DBfetch($dbTestItems)) {
			$itemidsDel[] = $testitem['itemid'];
		}

		$dbStepItems = DBselect(
			'SELECT DISTINCT hsi.itemid'.
			' FROM httpstepitem hsi,httpstep hs'.
			' WHERE '.DBcondition('hs.httptestid', $webcheckids).
				' AND hs.httpstepid=hsi.httpstepid'
		);
		while ($stepitem = DBfetch($dbStepItems)) {
			$itemidsDel[] = $stepitem['itemid'];
		}

		if (!empty($itemidsDel)) {
			API::Item()->delete($itemidsDel, true);
		}

		DB::delete('httptest', array('httptestid' => $webcheckids));

		// TODO: REMOVE info
		foreach ($delWebchecks as $webcheck) {
			info(_s('Scenario "%s" deleted.', $webcheck['name']));
		}

		// TODO: REMOVE audit
		foreach ($delWebchecks as $webcheck) {
			$host = reset($webcheck['hosts']);
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCENARIO,
				_s('Scenario "%1$s" "%2$s" host "%3$s".', $webcheck['name'], $webcheck['webcheckid'], $host['host']));
		}

		return array('webcheckids' => $webcheckids);
	}

	protected function createCheckItems($webcheck) {
		$checkitems = array(
			array(
				'name'				=> _s('Download speed for scenario "%s".', '$1'),
				'key_'				=> 'web.test.in['.$webcheck['name'].',,bps]',
				'value_type'		=> ITEM_VALUE_TYPE_FLOAT,
				'units'				=> 'Bps',
				'httptestitemtype'	=> HTTPSTEP_ITEM_TYPE_IN
			),
			array(
				'name'				=> _s('Failed step of scenario "%s".', '$1'),
				'key_'				=> 'web.test.fail['.$webcheck['name'].']',
				'value_type'		=> ITEM_VALUE_TYPE_UINT64,
				'units'				=> '',
				'httptestitemtype'	=> HTTPSTEP_ITEM_TYPE_LASTSTEP
			)
		);

		foreach ($checkitems as &$item) {
			$itemsExist = API::Item()->exists(array(
				'key_' => $item['key_'],
				'hostid' => $webcheck['hostid']
			));
			if ($itemsExist) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%s" already exists.', $item['key_']));
			}
			$item['data_type'] = ITEM_DATA_TYPE_DECIMAL;
			$item['hostid'] = $webcheck['hostid'];
			$item['delay'] = $webcheck['delay'];
			$item['type'] = ITEM_TYPE_HTTPTEST;
			$item['trapper_hosts'] = 'localhost';
			$item['history'] = $this->history;
			$item['trends'] = $this->trends;
			$item['status'] = $webcheck['status'];
		}
		unset($item);

		$checkItemids = DB::insert('items', $checkitems);

		foreach ($checkItemids as $itemid) {
			$itemApplications[] = array(
				'applicationid' => $webcheck['applicationid'],
				'itemid' => $itemid
			);
		}
		DB::insert('items_applications', $itemApplications);

		$webcheckitems = array();
		foreach ($checkitems as $inum => $item) {
			$webcheckitems[] = array(
				'httptestid' => $webcheck['webcheckid'],
				'itemid' => $checkItemids[$inum],
				'type' => $item['httptestitemtype']
			);
		}
		DB::insert('httptestitem', $webcheckitems);

		foreach ($checkitems as $stepitem) {
			info(_s('Web item "%s" created.', $stepitem['key_']));
		}
	}

	protected function createStepsReal($webcheck, $websteps) {
		$webstepsNames = zbx_objectValues($websteps, 'name');

		if (!preg_grep('/'.ZBX_PREG_PARAMS.'/i', $webstepsNames)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Scenario step name should contain only printable characters.'));
		}

		$sql = 'SELECT h.httpstepid,h.name'.
				' FROM httpstep h'.
				' WHERE h.httptestid='.$webcheck['webcheckid'].
					' AND '.DBcondition('h.name', $webstepsNames);
		if ($httpstepData = DBfetch(DBselect($sql))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Step "%s" already exists.', $httpstepData['name']));
		}

		foreach ($websteps as $snum => $webstep) {
			$websteps[$snum]['httptestid'] = $webcheck['webcheckid'];
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
					'key_'				=> 'web.test.in['.$webcheck['name'].','.$webstep['name'].',bps]',
					'value_type'		=> ITEM_VALUE_TYPE_FLOAT,
					'units'				=> 'Bps',
					'httpstepitemtype'	=> HTTPSTEP_ITEM_TYPE_IN
				),
				array(
					'name'				=> _s('Response time for step "%1$s" of scenario "%2$s".', '$2', '$1'),
					'key_'				=> 'web.test.time['.$webcheck['name'].','.$webstep['name'].',resp]',
					'value_type'		=> ITEM_VALUE_TYPE_FLOAT,
					'units'				=> 's',
					'httpstepitemtype'	=> HTTPSTEP_ITEM_TYPE_TIME
				),
				array(
					'name'				=> _s('Response code for step "%1$s" of scenario "%2$s".', '$2', '$1'),
					'key_'				=> 'web.test.rspcode['.$webcheck['name'].','.$webstep['name'].']',
					'value_type'		=> ITEM_VALUE_TYPE_UINT64,
					'units'				=> '',
					'httpstepitemtype'	=> HTTPSTEP_ITEM_TYPE_RSPCODE
				)
			);
			foreach ($stepitems as &$item) {
				$itemsExist = API::Item()->exists(array(
					'key_' => $item['key_'],
					'hostid' => $webcheck['hostid']
				));
				if ($itemsExist) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Web item with key "%s" already exists.', $item['key_']));
				}
				$item['hostid'] = $webcheck['hostid'];
				$item['delay'] = $webcheck['delay'];
				$item['type'] = ITEM_TYPE_HTTPTEST;
				$item['data_type'] = ITEM_DATA_TYPE_DECIMAL;
				$item['trapper_hosts'] = 'localhost';
				$item['history'] = $this->history;
				$item['trends'] = $this->trends;
				$item['status'] = $webcheck['status'];
			}
			unset($item);

			$stepItemids = DB::insert('items', $stepitems);

			$itemApplications = array();
			foreach ($stepItemids as $itemid) {
				$itemApplications[] = array(
					'applicationid' => $webcheck['applicationid'],
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

	protected function updateStepsReal($webcheck, $websteps) {
		$webstepsNames = zbx_objectValues($websteps, 'name');

		if (!preg_grep('/'.ZBX_PREG_PARAMS.'/i', $webstepsNames)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Scenario step name should contain only printable characters.'));
		}

		// get all used keys
		$webstepids = zbx_objectValues($websteps, 'webstepid');
		$dbKeys = DBfetchArray(DBselect(
			'SELECT i.key_'.
			' FROM items i,httpstepitem hi'.
			' WHERE '.DBcondition('hi.httpstepid', $webstepids).
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
				' WHERE hi.httpstepid='.$webstep['webstepid'].
					' AND hi.itemid=i.itemid'
			);
			while ($stepitem = DBfetch($dbStepItems)) {
				$itemids[] = $stepitem['itemid'];

				if (isset($webcheck['name']) || $webstep['name']) {
					switch ($stepitem['type']) {
						case HTTPSTEP_ITEM_TYPE_IN:
							$updateFields['key_'] = 'web.test.in['.$webcheck['name'].','.$webstep['name'].',bps]';
							break;
						case HTTPSTEP_ITEM_TYPE_TIME:
							$updateFields['key_'] = 'web.test.time['.$webcheck['name'].','.$webstep['name'].',resp]';
							break;
						case HTTPSTEP_ITEM_TYPE_RSPCODE:
							$updateFields['key_'] = 'web.test.rspcode['.$webcheck['name'].','.$webstep['name'].']';
							break;
					}
				}
				if (isset($dbKeys[$updateFields['key_']])) {
					unset($updateFields['key_']);
				}
				if (isset($webcheck['status'])) {
					$updateFields['status'] = $webcheck['status'];
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
			if (isset($webcheck['applicationid'])) {
				DB::update('items_applications', array(
					'values' => array('applicationid' => $webcheck['applicationid']),
					'where' => array('itemid' => $itemids)
				));
			}
		}
	}

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
?>
