<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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

class CApplication extends CZBXAPI{
/**
 * Get Applications data
 *
 * @param array $options
 * @param array $options['itemids']
 * @param array $options['hostids']
 * @param array $options['groupids']
 * @param array $options['triggerids']
 * @param array $options['applicationids']
 * @param boolean $options['status']
 * @param boolean $options['editable']
 * @param boolean $options['count']
 * @param string $options['pattern']
 * @param int $options['limit']
 * @param string $options['order']
 * @return array|int item data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('applicationid', 'name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('apps' => 'a.applicationid'),
			'from' => array('applications' => 'applications a'),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null
		);

		$def_options = array(
			'nodeids'				=> null,
			'groupids'				=> null,
			'templateids'			=> null,
			'hostids'				=> null,
			'itemids'				=> null,
			'applicationids'		=> null,
			'templated'				=> null,
			'editable'				=> null,
			'inherited' 			=> null,
			'nopermissions'			=> null,

// filter
			'filter'				=> null,
			'search'				=> null,
			'searchByAny'			=> null,
			'startSearch'				=> null,
			'exludeSearch'			=> null,

// OutPut
			'output'				=> API_OUTPUT_REFER,
			'expandData'			=> null,
			'selectHosts'			=> null,
			'selectItems'			=> null,
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
			$sql_parts['from']['users_groups'] = 'users_groups ug';
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
// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where']['ahg'] = 'a.hostid=hg.hostid';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['hg'] = 'hg.groupid';
			}
		}

// templateids
		if(!is_null($options['templateids'])){
			zbx_value2array($options['templateids']);

			if(!is_null($options['hostids'])){
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else{
				$options['hostids'] = $options['templateids'];
			}
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if($options['output'] != API_OUTPUT_EXTEND){
				$sql_parts['select']['hostid'] = 'a.hostid';
			}

			$sql_parts['where']['hostid'] = DBcondition('a.hostid', $options['hostids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['hostid'] = 'a.hostid';
			}
		}

// expandData
		if(!is_null($options['expandData'])){
			$sql_parts['select']['host'] = 'h.host';
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['ah'] = 'a.hostid=h.hostid';
		}

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['itemid'] = 'ia.itemid';
			}
			$sql_parts['from']['items_applications'] = 'items_applications ia';
			$sql_parts['where'][] = DBcondition('ia.itemid', $options['itemids']);
			$sql_parts['where']['aia'] = 'a.applicationid=ia.applicationid';

		}

// applicationids
		if(!is_null($options['applicationids'])){
			zbx_value2array($options['applicationids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['applicationid'] = 'a.applicationid';
			}
			$sql_parts['where'][] = DBcondition('a.applicationid', $options['applicationids']);

		}

// templated
		if(!is_null($options['templated'])){
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['ah'] = 'a.hostid=h.hostid';

			if($options['templated'])
				$sql_parts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			else
				$sql_parts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
		}

// inherited
		if(!is_null($options['inherited'])){
			if($options['inherited']){
				$sql_parts['where'][] = 'a.templateid IS NOT NULL';
			}
			else{
				$sql_parts['where'][] = 'a.templateid IS NULL';
			}
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['apps'] = 'a.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT a.applicationid) as rowscount');

//groupCount
			if(!is_null($options['groupCount'])){
				foreach($sql_parts['group'] as $key => $fields){
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('applications a', $options, $sql_parts);
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('applications a', $options, $sql_parts);
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'a.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('a.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('a.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'a.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//----------

		$applicationids = array();

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
				' WHERE '.DBin_node('a.applicationid', $nodeids).
					$sql_where.
				$sql_group.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($application = DBfetch($res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $application;
				else
					$result = $application['rowscount'];
			}
			else{
				$applicationids[$application['applicationid']] = $application['applicationid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$application['applicationid']] = array('applicationid' => $application['applicationid']);
				}
				else{
					if(!isset($result[$application['applicationid']]))
						$result[$application['applicationid']]= array();


					if(!is_null($options['selectHosts']) && !isset($result[$application['applicationid']]['hosts'])){
						$result[$application['applicationid']]['hosts'] = array();
					}

					if(!is_null($options['selectItems']) && !isset($result[$application['applicationid']]['items'])){
						$result[$application['applicationid']]['items'] = array();
					}

// hostids
					if(isset($application['hostid']) && is_null($options['selectHosts'])){
						if(!isset($result[$application['applicationid']]['hosts']))
							$result[$application['applicationid']]['hosts'] = array();

						$result[$application['applicationid']]['hosts'][] = array('hostid' => $application['hostid']);
//						unset($application['hostid']);
					}

// itemids
					if(isset($application['itemid']) && is_null($options['selectItems'])){
						if(!isset($result[$application['applicationid']]['items']))
							$result[$application['applicationid']]['items'] = array();

						$result[$application['applicationid']]['items'][] = array('itemid' => $application['itemid']);
						unset($application['itemid']);
					}

					$result[$application['applicationid']] += $application;
				}
			}
		}

COpt::memoryPick();
		if(!is_null($options['countOutput'])){
			return $result;
		}

// Adding Objects
// Adding Hosts
		if(!is_null($options['selectHosts']) && str_in_array($options['selectHosts'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['selectHosts'],
				'applicationids' => $applicationids,
				'nopermissions' => 1,
				'templated_hosts' => true,
				'preservekeys' => 1
			);
			$hosts = CHost::get($obj_params);
			foreach($hosts as $hostid => $host){
				$iapplications = $host['applications'];
				unset($host['applications']);
				foreach($iapplications as $num => $application){
					$result[$application['applicationid']]['hosts'][] = $host;
				}
			}
		}

// Adding Objects
// Adding items
		if(!is_null($options['selectItems']) && str_in_array($options['selectItems'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['selectItems'],
				'applicationids' => $applicationids,
				'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)),
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$items = CItem::get($obj_params);
			foreach($items as $itemid => $item){
				$iapplications = $item['applications'];
				unset($item['applications']);
				foreach($iapplications as $num => $application){
					$result[$application['applicationid']]['items'][] = $item;
				}
			}
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	public static function exists($object){
		$keyFields = array(array('hostid', 'host'), 'name');

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);
		if(isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if(isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = self::get($options);

	return !empty($objs);
	}

	public static function checkInput(&$applications, $method){
		$create = ($method == 'create');
		$update = ($method == 'update');
		$delete = ($method == 'delete');

// permissions
		if($update || $delete){
			$item_db_fields = array('applicationid' => null);
			$dbApplications = self::get(array(
				'output' => API_OUTPUT_EXTEND,
				'applicationids' => zbx_objectValues($applications, 'applicationid'),
				'editable' => 1,
				'preservekeys' => 1
			));
		}
		else{
			$item_db_fields = array('name' => null, 'hostid' => null);
			$dbHosts = CHost::get(array(
				'output' => array('hostid', 'host', 'status'),
				'hostids' => zbx_objectValues($applications, 'hostid'),
				'templated_hosts' => 1,
				'editable' => 1,
				'preservekeys' => 1
			));
		}

		foreach($applications as &$application){
			if(!check_db_fields($item_db_fields, $application)){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_ARGUMENTS_PASSED_TO_FUNCTION);
			}

			unset($application['templateid']);

// check permissions by hostid
			if($create){
				if(!isset($dbHosts[$application['hostid']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
			}

// check permissions by applicationid
			if($delete || $update){
				if(!isset($dbApplications[$application['applicationid']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
			}

// check on operating with templated applications
			if($delete || $update){
				if($dbApplications[$application['applicationid']]['templateid'] != 0){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot interact templated applications');
				}
			}

			if($update){
				if(!isset($application['hostid'])){
					$application['hostid'] = $dbApplications[$application['applicationid']]['hostid'];
				}
			}

// check existance
			if($update || $create){
				$applicationsExists = self::get(array(
					'output' => API_OUTPUT_EXTEND,
					'filter' => array(
						'hostid' => $application['hostid'],
						'name' => $application['name'],
					),
					'nopermissions' => 1
				));
				foreach($applicationsExists as $applicationExists){
					if(!$update || ($applicationExists['applicationid'] != $application['applicationid'])){
						self::exception(ZBX_API_ERROR_PARAMETERS, S_APPLICATION.' ['.$application['name'].'] '.S_ALREADY_EXISTS_SMALL);
					}
				}
			}
		}
		unset($application);
	}

/**
 * Add Applications
 *
 * @param array $applications
 * @param array $app_data['name']
 * @param array $app_data['hostid']
 * @return array
 */
	public static function create($applications){
		$applications = zbx_toArray($applications);

		try{
			self::BeginTransaction(__METHOD__);

			self::checkInput($applications, __FUNCTION__);

			self::createReal($applications);

			self::inherit($applications);

			self::EndTransaction(true, __METHOD__);
			return array('applicationids' => zbx_objectValues($applications, 'applicationid'));
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Update Applications
 *
 * @param array $applications
 * @param array $app_data['name']
 * @param array $app_data['hostid']
 * @return array
 */
	public static function update($applications){
		$applications = zbx_toArray($applications);

		try{
			self::BeginTransaction(__METHOD__);

			self::checkInput($applications, __FUNCTION__);

			self::updateReal($applications);

			self::inherit($applications);

			self::EndTransaction(true, __METHOD__);
			return array('applicationids' => zbx_objectValues($applications, 'applicationids'));
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

	protected static function createReal(&$applications){
		if(empty($applications)) return true;

		$applicationids = DB::insert('applications', $applications);

		foreach($applications as $anum => $application){
			$applications[$anum]['applicationid'] = $applicationids[$anum];
		}

// TODO: REMOVE info
		$applications_created = self::get(array(
			'applicationids' => $applicationids,
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => API_OUTPUT_EXTEND,
			'nopermissions' => 1
		));
		foreach($applications_created as $application_created){
			$host = reset($application_created['hosts']);
			info(_s('Application [%1$s:%2$s] updated.', $host['host'], $application_created['name']));
		}
	}

	protected static function updateReal($applications){
		$update = array();
		foreach($applications as $application){
			$update[] = array(
				'values' => $application,
				'where'=> array('applicationid='.$application['applicationid'])
			);
		}
		DB::update('applications', $update);


// TODO: REMOVE info
		$applications_upd = self::get(array(
			'applicationids' => zbx_objectValues($applications, 'applicationid'),
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => API_OUTPUT_EXTEND,
			'nopermissions' => 1,
		));
		foreach($applications_upd as $application_upd){
			$host = reset($application_upd['hosts']);
			info(_s('Application [%1$s:%2$s] updated.', $host['host'], $application_upd['name']));
		}
	}

/**
 * Delete Applications
 *
 * @param array $applicationids
 * @return array
 */
	public static function delete($applicationids, $nopermissions=false){
		$applicationids = zbx_toArray($applicationids);
		try{
			self::BeginTransaction(__METHOD__);

// TODO: remove $nopermissions hack
			$options = array(
				'applicationids' => $applicationids,
				'editable' => true,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => true,
			);
			$del_applications = self::get($options);

			if(!$nopermissions){
				foreach($applicationids as $applicationid){
					if(!isset($del_applications[$applicationid])){
						self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
					}
					if($del_applications[$applicationid]['templateid'] != 0){
						self::exception(ZBX_API_ERROR_PERMISSIONS, 'Cannot delete templated application');
					}
				}
			}


			$parent_applicationids = $applicationids;
			$child_applicationids = array();
			do{
				$db_applications = DBselect('SELECT applicationid FROM applications WHERE ' . DBcondition('templateid', $parent_applicationids));
				$parent_applicationids = array();
				while($db_application = DBfetch($db_applications)){
					$parent_applicationids[] = $db_application['applicationid'];
					$child_applicationids[$db_application['applicationid']] = $db_application['applicationid'];
				}
			} while(!empty($parent_applicationids));

			$options = array(
				'applicationids' => $child_applicationids,
				'output' => API_OUTPUT_EXTEND,
				'nopermissions' => true,
				'preservekeys' => true,
			);
			$del_application_childs = self::get($options);

			$del_applications = array_merge($del_applications, $del_application_childs);
			$applicationids = array_merge($applicationids, $child_applicationids);

//check if app is used by web scenario
			$sql = 'SELECT ht.name, ht.applicationid '.
					' FROM httptest ht '.
					' WHERE '.DBcondition('ht.applicationid',$applicationids);
			$res = DBselect($sql);
			if($info = DBfetch($res)){
				self::exception(ZBX_API_ERROR_PARAMETERS,
						S_APPLICATION.' ['.$del_applications[$info['applicationid']]['name'].'] '.
						S_USED_BY_SCENARIO_SMALL.' ['.$info['name'].'] '.S_AND_CANT_BE_DELETED);
			}

			DB::delete('applications', array('applicationid'=>$applicationids));

// TODO: remove info from API
			foreach($del_applications as $del_application){
				info(_s('Application [%1$s] deleted', $del_application['name']));
			}

			self::EndTransaction(true, __METHOD__);
			return array('applicationids' => $applicationids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Add Items to applications
 *
 * @param array $data
 * @param array $data['applications']
 * @param array $data['items']
 * @return boolean
 */
	public static function massAdd($data){
		try{
			self::BeginTransaction(__METHOD__);

			if(empty($data['applications'])) self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter'));

			$applications = zbx_toArray($data['applications']);
			$items = zbx_toArray($data['items']);
			$applicationids = zbx_objectValues($applications, 'applicationid');
			$itemids = zbx_objectValues($items, 'itemid');

// PERMISSIONS {{{
			$app_options = array(
				'applicationids' => $applicationids,
				'editable' => 1,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1,
			);
			$allowed_applications = self::get($app_options);
			foreach($applications as $anum => $application){
				if(!isset($allowed_applications[$application['applicationid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
				}
			}

			$item_options = array(
				'itemids' => $itemids,
				'editable' => 1,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1
			);
			$allowed_items = CItem::get($item_options);
			foreach($items as $num => $item){
				if(!isset($allowed_items[$item['itemid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
				}
			}
// }}} PERMISSIONS

			$sql = 'SELECT itemid, applicationid ' .
					' FROM items_applications ' .
					' WHERE ' . DBcondition('itemid', $itemids) .
						' AND ' . DBcondition('applicationid', $applicationids);
			$linked_db = DBselect($sql);
			while($pair = DBfetch($linked_db)){
				$linked[$pair['applicationid']] = array($pair['itemid'] => $pair['itemid']);
			}



			$apps_insert = array();
			foreach($applicationids as $anum => $applicationid){
				foreach($itemids as $inum => $itemid){
					if(isset($linked[$applicationid]) && isset($linked[$applicationid][$itemid])) {
						continue;
					}

					$apps_insert[] = array(
						'itemid' => $itemid,
						'applicationid' => $applicationid,
					);
				}
			}

			DB::insert('items_applications', $apps_insert);

			foreach($itemids as $inum => $itemid){
				$db_childs = DBselect('SELECT itemid, hostid FROM items WHERE templateid=' . $itemid);

				while($child = DBfetch($db_childs)){
					$sql = 'SELECT a1.applicationid ' .
							' FROM applications a1, applications a2 ' .
							' WHERE a1.name=a2.name ' .
								' AND a1.hostid=' . $child['hostid'] .
								' AND ' . DBcondition('a2.applicationid', $applicationids);
					$db_apps = DBselect($sql);

					$child_applications = array();

					while($app = DBfetch($db_apps)){
						$child_applications[] = $app;
					}

					$result = self::massAdd(array('items' => $child, 'applications' => $child_applications));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot add items');
					}
				}
			}

			self::EndTransaction(true, __METHOD__);
			return array('applicationids'=> $applicationids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

	protected static function inherit($applications, $hostids=null){
		if(empty($applications)) return $applications;

		$applications = zbx_toHash($applications, 'applicationid');

		$chdHosts = CHost::get(array(
			'output' => array('hostid', 'host'),
			'templateids' => zbx_objectValues($applications, 'hostid'),
			'hostids' => $hostids,
			'preservekeys' => 1,
			'nopermissions' => 1,
			'templated_hosts' => 1
		));
		if(empty($chdHosts)) return true;

		$insertApplications = array();
		$updateApplications = array();

		foreach($chdHosts as $hostid => $host){
			$templateids = zbx_toHash($host['templates'], 'templateid');

// skip applications not from parent templates of current host
			$parentApplications = array();
			foreach($applications as $applicationid => $application){
				if(isset($templateids[$application['hostid']]))
					$parentApplications[$applicationid] = $application;
			}

// check existing items to decide insert or update
			$exApplications = self::get(array(
				'output' => API_OUTPUT_EXTEND,
				'hostids' => $hostid,
				'preservekeys' => true,
				'nopermissions' => true,
			));

			$exApplicationsNames = zbx_toHash($exApplications, 'name');
			$exApplicationsTpl = zbx_toHash($exApplications, 'templateid');

			foreach($parentApplications as $applicationid => $application){
				$exApplication = null;

// update by tempalteid
				if(isset($exApplicationsTpl[$applicationid])){
					$exApplication = $exApplicationsTpl[$applicationid];
				}

// update by name

				if(isset($application['name']) && isset($exApplicationsNames[$application['name']])){
					$exApplication = $exApplicationsNames[$application['name']];

					if(($exApplication['templateid'] > 0) && ($exApplication['templateid'] != $application['applicationid'])){
						self::exception(ZBX_API_ERROR_PARAMETERS, S_APPLICATION.' ['.$exApplication['name'].'] '.S_ALREADY_EXISTS_FOR_HOST_SMALL.' ['.$host['host'].']');
					}
				}

				$newApplication = $application;
				$newApplication['hostid'] = $host['hostid'];
				$newApplication['templateid'] = $application['applicationid'];


				if($exApplication){
					$newApplication['applicationid'] = $exApplication['applicationid'];
					$updateApplications[] = $newApplication;
				}
				else{
					$insertApplications[] = $newApplication;
				}
			}
		}

		self::createReal($insertApplications);
		self::updateReal($updateApplications);

		$inheritedApplications = array_merge($insertApplications, $updateApplications);

		self::inherit($inheritedApplications);

		return true;
	}

	public static function syncTemplates($data){
		try{
			self::BeginTransaction(__METHOD__);

			$data['templateids'] = zbx_toArray($data['templateids']);
			$data['hostids'] = zbx_toArray($data['hostids']);

			$options = array(
				'hostids' => $data['hostids'],
				'editable' => 1,
				'preservekeys' => 1,
				'templated_hosts' => 1,
				'output' => API_OUTPUT_SHORTEN
			);
			$allowedHosts = CHost::get($options);
			foreach($data['hostids'] as $hostid){
				if(!isset($allowedHosts[$hostid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}
			$options = array(
				'templateids' => $data['templateids'],
				'preservekeys' => 1,
				'output' => API_OUTPUT_SHORTEN
			);
			$allowedTemplates = CTemplate::get($options);
			foreach($data['templateids'] as $templateid){
				if(!isset($allowedTemplates[$templateid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			$options = array(
				'hostids' => $data['templateids'],
				'preservekeys' => 1,
				'output' => API_OUTPUT_EXTEND,
			);
			$applications = self::get($options);

			self::inherit($applications, $data['hostids']);

			self::EndTransaction(true, __METHOD__);
			return true;
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}
}

?>
