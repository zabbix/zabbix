<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
/**
 * @package API
 */

class CWebCheck extends CZBXAPI{

	private static $history = 30;
	private static $trends = 90;

	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('httptestid', 'name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('httptests' => 'ht.httptestid'),
			'from' => array('httptest' => 'httptest ht'),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null
		);

		$def_options = array(
			'nodeids'				=> null,
			'applicationids'				=> null,
			'hostids'				=> null,
			'editable'				=> null,
			'nopermissions'			=> null,
// filter
			'filter'				=> null,
			'search'				=> null,
			'searchByAny'			=> null,
			'startSearch'				=> null,
			'exludeSearch'			=> null,

// OutPut
			'output'				=> API_OUTPUT_REFER,
			'select_hosts'			=> null,
			'selectSteps'			=> null,
			'countOutput'			=> null,
			'groupCount'			=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = zbx_array_merge($def_options, $options);

// editable + PERMISSION CHECK

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['applications'] = 'applications a';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where'][] = 'a.applicationid=ht.applicationid';
			$sql_parts['where'][] = 'hg.hostid=a.hostid';
			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS( '.
								' SELECT hgg.groupid '.
								' FROM hosts_groups hgg, rights rr, users_groups gg '.
								' WHERE hgg.hostid=hg.hostid '.
									' AND rr.id=hgg.groupid '.
									' AND rr.groupid=gg.usrgrpid '.
									' AND gg.userid='.$userid.
									' AND rr.permission<'.$permission.')';
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['hostid'] = 'a.hostid';
			}

			$sql_parts['from']['applications'] = 'applications a';
			$sql_parts['where'][] = 'a.applicationid=ht.applicationid';
			$sql_parts['where']['hostid'] = DBcondition('a.hostid', $options['hostids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['hostid'] = 'a.hostid';
			}
		}

// applicationids
		if(!is_null($options['applicationids'])){
			zbx_value2array($options['applicationids']);

			if($options['output'] != API_OUTPUT_EXTEND){
				$sql_parts['select']['applicationid'] = 'a.applicationid';
			}
			$sql_parts['where'][] = DBcondition('ht.applicationid', $options['applicationids']);

		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['httptests'] = 'ht.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(ht.httptestid) as rowscount');

//groupCount
			if(!is_null($options['groupCount'])){
				foreach($sql_parts['group'] as $key => $fields){
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('httptest ht', $options, $sql_parts);
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('httptest ht', $options, $sql_parts);
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'ht.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('ht.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('ht.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'ht.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//----------

		$webcheckids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['group'] = array_unique($sql_parts['group']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_group = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['group']))		$sql_where.= ' GROUP BY '.implode(',',$sql_parts['group']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('ht.httptestid', $nodeids).
					$sql_where.
				$sql_group.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($webcheck = DBfetch($res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $webcheck;
				else
					$result = $webcheck['rowscount'];
			}
			else{
				$webcheck['webcheckid'] = $webcheck['httptestid'];
				unset($webcheck['httptestid']);

				$webcheckids[$webcheck['webcheckid']] = $webcheck['webcheckid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$webcheck['webcheckid']] = array('webcheckid' => $webcheck['webcheckid']);
				}
				else{
					if(!isset($result[$webcheck['webcheckid']])) $result[$webcheck['webcheckid']] = array();


					if(!is_null($options['select_hosts']) && !isset($result[$webcheck['webcheckid']]['hosts'])){
						$result[$webcheck['webcheckid']]['hosts'] = array();
					}
					if(!is_null($options['selectSteps']) && !isset($result[$webcheck['webcheckid']]['steps'])){
						$result[$webcheck['webcheckid']]['steps'] = array();
					}

// hostids
					if(isset($webcheck['hostid']) && is_null($options['select_hosts'])){
						if(!isset($result[$webcheck['webcheckid']]['hosts']))
							$result[$webcheck['webcheckid']]['hosts'] = array();

						$result[$webcheck['webcheckid']]['hosts'][] = array('hostid' => $webcheck['hostid']);
						unset($webcheck['hostid']);
					}

					$result[$webcheck['webcheckid']] += $webcheck;
				}
			}
		}

COpt::memoryPick();
		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Hosts
		if(!is_null($options['select_hosts']) && str_in_array($options['select_hosts'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['select_hosts'],
				'webcheckids' => $webcheckids,
				'nopermissions' => true,
				'preservekeys' => true
			);
			$hosts = CHost::get($obj_params);

			foreach($hosts as $hostid => $host){
				$hwebchecks = $host['webchecks'];
				unset($host['webchecks']);
				foreach($hwebchecks as $hwebcheck){
					$result[$hwebcheck['webcheckid']]['hosts'][] = $host;
				}
			}
		}

// Adding Steps
		if(!is_null($options['selectSteps']) && str_in_array($options['selectSteps'], $subselects_allowed_outputs)){
			$sql = 'SELECT * '.
					' FROM httpstep'.
					' WHERE '.DBcondition('httptestid', $webcheckids);
			$db_steps = DBselect($sql);
			while($step = DBfetch($db_steps)){
				$stepid = $step['httpstepid'];
				$step['webstepid'] = $stepid;
				unset($step['httpstepid']);

				$result[$step['httptestid']]['steps'][$step['webstepid']] = $step;
			}
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	public static function create($webchecks){
		$webchecks = zbx_toArray($webchecks);

		try{
			self::BeginTransaction(__METHOD__);

			$webcheck_names = zbx_objectValues($webchecks, 'name');

			if(!preg_grep('/^(['.ZBX_PREG_PRINT.'])+$/u', $webcheck_names)){
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Only characters are allowed'));
			}

			$options = array(
				'filter' => array('name' => $webcheck_names),
				'output' => API_OUTPUT_EXTEND,
				'nopermissions' => true,
				'preservekeys' => true,
			);
			$db_webchecks = self::get($options);
			foreach($db_webchecks as $webcheck){
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Scenarion [%s] already exists.', $webcheck['name']));
			}


			$webcheckids = DB::insert('httptest', $webchecks);

			foreach($webchecks as $wnum => $webcheck){
				if(empty($webcheck['steps']))
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Webcheck must have at least one step.'));

				$webcheck['webcheckid'] = $webcheckids[$wnum];

				self::createCheckItems($webcheck);
				self::createStepsReal($webcheck, $webcheck['steps']);
			}

			self::EndTransaction(true, __METHOD__);
			return array('webcheckids' => $webcheckids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, $error);
			return false;
		}
	}

	public static function update($webchecks){
		$webchecks = zbx_toArray($webchecks);
		$webcheckids = zbx_objectValues($webchecks, 'webcheckid');

		try{
			self::BeginTransaction(__METHOD__);

			$dbWebchecks = self::get(array(
				'output' => API_OUTPUT_EXTEND,
				'webcheckids' => $webcheckids,
				'selectSteps' => API_OUTPUT_EXTEND,
				'editable' => true,
				'preservekeys' => true
			));


			foreach($webchecks as $webcheck){
				if(!isset($dbWebchecks[$webcheck['webcheckid']])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSION);
				}

				if(!check_db_fields(array('webcheckid' => null), $webcheck)){
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function'));
				}

				if(isset($webcheck['name'])){
					if(!preg_match('/^(['.ZBX_PREG_PRINT.'])+$/u', $webcheck['name'])){
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Only characters are allowed'));
					}

					$options = array(
						'filter' => array('name' => $webcheck['name']),
						'preservekeys' => true,
						'nopermissions' => true,
						'output' => API_OUTPUT_SHORTEN,
					);
					$webcheck_exist = self::get($options);
					$webcheck_exist = reset($webcheck_exist);

					if($webcheck_exist && ($webcheck_exist['webcheckid'] != $webcheck_exist['webcheckid']))
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Scenario [%s] already exists.', $webcheck['name']));
				}

				$webcheck['curstate'] = HTTPTEST_STATE_UNKNOWN;
				$webcheck['error'] = '';
				$update = array(
					'values' => $webcheck,
					'where' => array('httptestid='.$webcheck['webcheckid'])
				);
				DB::update('httptest', $update);


				$checkitems_update = $update_fields = array();
				$sql = 'SELECT i.itemid, hi.type'.
					' FROM items i, httptestitem hi '.
					' WHERE hi.httptestid='.$webcheck['webcheckid'].
						' AND hi.itemid=i.itemid';
				$db_checkitems = DBselect($sql);
				while($checkitem = DBfetch($db_checkitems)){
					$itemids[] = $checkitem['itemid'];

					if(isset($webcheck['name'])){
						switch($checkitem['type']){
							case HTTPSTEP_ITEM_TYPE_IN:
								$update_fields['key_'] = 'web.test.in['.$webcheck['name'].',,bps]';
								break;
							case HTTPSTEP_ITEM_TYPE_LASTSTEP:
								$update_fields['key_'] = 'web.test.fail['.$webcheck['name'].']';
								break;
						}
					}

					if(isset($webcheck['status'])){
						$update_fields['status'] = $webcheck['status'];
					}


					if(!empty($update_fields)){
						$checkitems_update[] = array(
							'values' => $update_fields,
							'where' => array('itemid='.$checkitem['itemid'])
						);
					}
				}
				DB::update('items', $checkitems_update);

// update application
				if(isset($webcheck['applicationid'])){
					DB::update('items_applications', array(
						'values' => array('applicationid' => $webcheck['applicationid']),
						'where' => array(DBcondition('itemid', $itemids))
					));
				}


// UPDATE STEPS
				$steps_create = $steps_update = array();
				$dbSteps = zbx_toHash($dbWebchecks[$webcheck['webcheckid']]['steps'], 'webstepid');

				foreach($webcheck['steps'] as $webstep){
					if(isset($webstep['webstepid']) && isset($dbSteps[$webstep['webstepid']])){
						$steps_update[] = $webstep;
						unset($dbSteps[$webstep['webstepid']]);
					}
					else if(!isset($webstep['webstepid'])){
						$steps_create[] = $webstep;
					}
				}
				$stepids_delete = array_keys($dbSteps);

				if(!empty($steps_create))
					self::createStepsReal($webcheck, $steps_create);
				if(!empty($steps_update))
					self::updateStepsReal($webcheck, $steps_update);
				if(!empty($stepids_delete))
					self::deleteStepsReal($stepids_delete);
			}

			return array('webchekids' => $webcheckids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, $error);
			return false;
		}
	}

	public static function delete($webcheckids){
		if(empty($webcheckids)) return true;

		$webcheckids = zbx_toArray($webcheckids);

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'webcheckids' => $webcheckids,
				'output' => API_OUTPUT_EXTEND,
				'editable' => true,
				'select_hosts' => API_OUTPUT_EXTEND,
				'preservekeys' => true
			);
			$del_webchecks = self::get($options);

			foreach($webcheckids as $webcheckid){
				if(!isset($del_webchecks[$webcheckid])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
				}
			}

			$itemids_del = array();
			$sql = 'SELECT itemid '.
					' FROM httptestitem '.
					' WHERE '.DBcondition('httptestid', $webcheckids);
			$db_testitems = DBselect($sql);
			while($testitem = DBfetch($db_testitems)){
				$itemids_del[] = $testitem['itemid'];
			}

			$sql = 'SELECT DISTINCT hsi.itemid '.
					' FROM httpstepitem hsi, httpstep hs'.
					' WHERE '.DBcondition('hs.httptestid', $webcheckids).
						' AND hs.httpstepid=hsi.httpstepid';
			$db_stepitems = DBselect($sql);
			while($stepitem = DBfetch($db_stepitems)){
				$itemids_del[] = $stepitem['itemid'];
			}

			CItem::delete($itemids_del, true);

			DB::delete('httptest', array('httptestid' => $webcheckids));

// TODO: REMOVE info
			foreach($del_webchecks as $webcheck){
				info(_s('Scenario [%s] deleted.', $webcheck['name']));
			}

// TODO: REMOVE audit
			foreach($del_webchecks as $webcheck){
				$host = reset($webcheck['hosts']);
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCENARIO,
					_s('Scenario [%1$s] [%2$s] host [%3$s]', $webcheck['name'], $webcheck['webcheckid'], $host['host']));
			}

			self::EndTransaction(true, __METHOD__);
			return array('webcheckids' => $webcheckids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}


	protected static function createCheckItems($webcheck){
		$checkitems = array(
			array(
				// GETTEXT: Legend below graph
				'description'		=> _s('Download speed for scenario \'%s\'', '$1'),
				'key_'				=> 'web.test.in['.$webcheck['name'].',,bps]',
				'value_type'		=> ITEM_VALUE_TYPE_FLOAT,
				'units'				=> 'Bps',
				'httptestitemtype'	=> HTTPSTEP_ITEM_TYPE_IN
			),
			array(
				// GETTEXT: Legend below graph
				'description'		=> _s('Failed step of scenario \'%s\'', '$1'),
				'key_'				=> 'web.test.fail['.$webcheck['name'].']',
				'value_type'		=> ITEM_VALUE_TYPE_UINT64,
				'units'				=> '',
				'httptestitemtype'	=> HTTPSTEP_ITEM_TYPE_LASTSTEP
			)
		);

		foreach($checkitems as &$item){
			$items_exist = CItem::exists(array(
				'key_' => $item['key_'],
				'hostid' => $webcheck['hostid']
			));
			if($items_exist)
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key [%s] already exists', $item['key_']));

			$item['data_type'] = ITEM_DATA_TYPE_DECIMAL;
			$item['hostid'] = $webcheck['hostid'];
			$item['delay'] = $webcheck['delay'];
			$item['type'] = ITEM_TYPE_HTTPTEST;
			$item['trapper_hosts'] = 'localhost';
			$item['history'] = self::$history;
			$item['trends'] = self::$trends;
			$item['status'] = $webcheck['status'];
		}
		unset($item);

		$check_itemids = DB::insert('items', $checkitems);

		foreach($check_itemids as $itemid){
			$itemApplications[] = array(
				'applicationid' => $webcheck['applicationid'],
				'itemid' => $itemid
			);
		}
		DB::insert('items_applications', $itemApplications);

		$webcheckitems = array();
		foreach($checkitems as $inum => $item){
			$webcheckitems[] = array(
				'httptestid' => $webcheck['webcheckid'],
				'itemid' => $check_itemids[$inum],
				'type' => $item['httptestitemtype'],
			);

		}
		DB::insert('httptestitem', $webcheckitems);

		foreach($checkitems as $stepitem)
			info(_s('Web item [%s] created.', $stepitem['key_']));
	}

	protected static function createStepsReal($webcheck, $websteps){

		$websteps_names = zbx_objectValues($websteps, 'name');

		if(!preg_grep('/'.ZBX_PREG_PARAMS.'/i', $websteps_names))
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Scenario step name should contain only printable characters.'));

		$sql = 'SELECT httpstepid, name'.
				' FROM httpstep '.
				' WHERE httptestid='.$webcheck['webcheckid'].
					' AND '.DBcondition('name', $websteps_names);
		if($httpstep_data = DBfetch(DBselect($sql))){
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Step [%s] already exists.', $httpstep_data['name']));
		}

		foreach($websteps as $snum=>$webstep){
			$websteps[$snum]['httptestid'] = $webcheck['webcheckid'];
			if($webstep['no'] <= 0)
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Scenario step number cannot be less than 1'));
		}

		$webstepids = DB::insert('httpstep', $websteps);


		foreach($websteps as $snum => $webstep){
			$webstepid = $webstepids[$snum];

			$stepitems = array(
				array(
					'description'		=> _s('Download speed for step \'%1$s\' of scenario \'%2$s\'', '$2', '$1'),
					'key_'				=> 'web.test.in['.$webcheck['name'].','.$webstep['name'].',bps]',
					'value_type'		=> ITEM_VALUE_TYPE_FLOAT,
					'units'				=> 'Bps',
					'httpstepitemtype'	 => HTTPSTEP_ITEM_TYPE_IN
				),
				array(
					'description'		=> _s('Response time for step \'%1$s\' of scenario \'%2$s\'', '$2', '$1'),
					'key_'				=> 'web.test.time['.$webcheck['name'].','.$webstep['name'].',resp]',
					'value_type'		=> ITEM_VALUE_TYPE_FLOAT,
					'units'				=> 's',
					'httpstepitemtype' 	=> HTTPSTEP_ITEM_TYPE_TIME
				),
				array(
					'description'		=> _s('Response code for step \'%1$s\' of scenario \'%2$s\'', '$2', '$1'),
					'key_'				=> 'web.test.rspcode['.$webcheck['name'].','.$webstep['name'].']',
					'value_type'		=> ITEM_VALUE_TYPE_UINT64,
					'units'				=> '',
					'httpstepitemtype' 	=> HTTPSTEP_ITEM_TYPE_RSPCODE
				)
			);
			foreach($stepitems as &$item){
				$items_exist = CItem::exists(array(
					'key_' => $item['key_'],
					'hostid' => $webcheck['hostid']
				));
				if($items_exist)
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Web item with key [%s] already exists.', $item['key_']));

				$item['hostid'] = $webcheck['hostid'];
				$item['delay'] = $webcheck['delay'];
				$item['type'] = ITEM_TYPE_HTTPTEST;
				$item['data_type'] = ITEM_DATA_TYPE_DECIMAL;
				$item['trapper_hosts'] = 'localhost';
				$item['history'] = self::$history;
				$item['trends'] = self::$trends;
				$item['status'] = $webcheck['status'];
			}
			unset($item);

			$step_itemids = DB::insert('items', $stepitems);


			$itemApplications = array();
			foreach($step_itemids as $itemid){
				$itemApplications[] = array(
					'applicationid' => $webcheck['applicationid'],
					'itemid' => $itemid
				);
			}
			DB::insert('items_applications', $itemApplications);


			$webstepitems = array();
			foreach($stepitems as $inum => $item){
				$webstepitems[] = array(
					'httpstepid' => $webstepid,
					'itemid' => $step_itemids[$inum],
					'type' => $item['httpstepitemtype'],
				);
			}
			DB::insert('httpstepitem', $webstepitems);

			foreach($stepitems as $stepitem)
				info(_s('Web item [%s] created.', $stepitem['key_']));
		}
	}

	protected static function updateStepsReal($webcheck, $websteps){

		$websteps_names = zbx_objectValues($websteps, 'name');

		if(!preg_grep('/'.ZBX_PREG_PARAMS.'/i', $websteps_names))
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Scenario step name should contain only printable characters.'));


		foreach($websteps as $snum => $webstep){
			if($webstep['no'] <= 0)
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Scenario step number cannot be less than 1'));

			$update = array(
				'values' => $webstep,
				'where' => array('httpstepid='.$webstep['webstepid'])
			);
			DB::update('httpstep', $update);


// update item keys
			$stepitems_update = $update_fields = array();
			$sql = 'SELECT i.itemid, hi.type'.
				' FROM items i, httpstepitem hi '.
				' WHERE hi.httpstepid='.$webstep['webstepid'].
					' AND hi.itemid=i.itemid';
			$db_stepitems = DBselect($sql);
			while($stepitem = DBfetch($db_stepitems)){
				$itemids[] = $stepitem['itemid'];

				if(isset($webcheck['name']) || $webstep['name']){
					switch($stepitem['type']){
						case HTTPSTEP_ITEM_TYPE_IN:
							$update_fields['key_'] = 'web.test.in['.$webcheck['name'].','.$webstep['name'].',bps]';
							break;
						case HTTPSTEP_ITEM_TYPE_TIME:
							$update_fields['key_'] = 'web.test.time['.$webcheck['name'].','.$webstep['name'].',resp]';
							break;
						case HTTPSTEP_ITEM_TYPE_RSPCODE:
							$update_fields['key_'] = 'web.test.rspcode['.$webcheck['name'].','.$webstep['name'].']';
							break;
					}
				}

				if(isset($webcheck['status'])){
					$update_fields['status'] = $webcheck['status'];
				}

				if(!empty($update_fields)){
					$stepitems_update[] = array(
						'values' => $update_fields,
						'where' => array('itemid='.$stepitem['itemid'])
					);
				}
			}
			DB::update('items', $stepitems_update);


// update application
			if(isset($webcheck['applicationid'])){
				DB::update('items_applications', array(
					'values' => array('applicationid' => $webcheck['applicationid']),
					'where' => array(DBcondition('itemid', $itemids))
				));
			}
		}
	}

	protected static function deleteStepsReal($webstepids){

		$itemids = array();
		$sql = 'SELECT i.itemid'.
			' FROM items i, httpstepitem hi '.
			' WHERE '.DBcondition('hi.httpstepid', $webstepids).
				' AND hi.itemid=i.itemid';
		$db_stepitems = DBselect($sql);
		while($stepitem = DBfetch($db_stepitems)){
			$itemids[] = $stepitem['itemid'];
		}

		DB::delete('httpstep', array('httpstepid' => $webstepids));

		CItem::delete($itemids, true);
	}
}
?>
