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
 * File containing CAction class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Actions
 *
 */
class CAction extends CZBXAPI{
/**
 * Get Actions data
 *
 * @param _array $options
 * @param array $options['itemids']
 * @param array $options['hostids']
 * @param array $options['groupids']
 * @param array $options['actionids']
 * @param array $options['applicationids']
 * @param array $options['status']
 * @param array $options['editable']
 * @param array $options['extendoutput']
 * @param array $options['count']
 * @param array $options['pattern']
 * @param array $options['limit']
 * @param array $options['order']
 * @return array|int item data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('actionid','name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('actions' => 'a.actionid'),
			'from' => array('actions' => 'actions a'),
			'where' => array(),
			'order' => array(),
			'limit' => null,
		);

		$def_options = array(
			'nodeids'				=> null,
			'groupids'				=> null,
			'hostids'				=> null,
			'actionids'				=> null,
			'triggerids'			=> null,
			'mediatypeids'			=> null,
			'userids'				=> null,
			'nopermissions'			=> null,
			'editable'				=> null,
// filter
			'filter'				=> null,
			'search'				=> null,
			'startSearch'			=> null,
			'excludeSearch'			=> null,
			'searchWildcardsEnabled'=> null,

// OutPut
			'extendoutput'			=> null,
			'output'				=> API_OUTPUT_REFER,
			'select_conditions'		=> null,
			'select_operations'		=> null,
			'countOutput'			=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_conditions'])){
				$options['select_conditions'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_operations'])){
				$options['select_operations'] = API_OUTPUT_EXTEND;
			}
		}


// editable + PERMISSION CHECK
		if((USER_TYPE_SUPER_ADMIN == $user_type) || !is_null($options['nopermissions'])){
		}
		else{
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

			$sql_parts['from']['conditions'] = 'conditions c';
			$sql_parts['where']['ac'] = 'a.actionid=c.actionid';

// condition hostgroup
			$sql_parts['where'][] =
				' NOT EXISTS('.
					' SELECT cc.conditionid'.
					' FROM conditions cc'.
					' WHERE cc.conditiontype='.CONDITION_TYPE_HOST_GROUP.
						' AND cc.actionid=c.actionid'.
						' AND ('.
							' NOT EXISTS('.
								' SELECT rr.id'.
								' FROM rights rr, users_groups ug'.
								' WHERE rr.id='.zbx_dbcast_2bigint('cc.value').
									' AND rr.groupid=ug.usrgrpid'.
									' AND ug.userid='.$userid.
									' AND rr.permission>='.$permission.
							' )'.
							' OR EXISTS('.
								' SELECT rr.id'.
								' FROM rights rr, users_groups ugg'.
								' WHERE rr.id='.zbx_dbcast_2bigint('cc.value').
									' AND rr.groupid=ugg.usrgrpid'.
									' AND ugg.userid='.$userid.
									' AND rr.permission<'.$permission.
							' )'.
						' )'.
				' )';

// condition host or template
			$sql_parts['where'][] =
				' NOT EXISTS('.
					' SELECT cc.conditionid'.
					' FROM conditions cc'.
					' WHERE (cc.conditiontype='.CONDITION_TYPE_HOST.' OR cc.conditiontype='.CONDITION_TYPE_HOST_TEMPLATE.')'.
						' AND cc.actionid=c.actionid'.
						' AND ('.
							' NOT EXISTS('.
								' SELECT hgg.hostid'.
								' FROM hosts_groups hgg, rights r,users_groups ug'.
								' WHERE hgg.hostid='.zbx_dbcast_2bigint('cc.value').
									' AND r.id=hgg.groupid'.
									' AND ug.userid='.$userid.
									' AND r.permission>='.$permission.
									' AND r.groupid=ug.usrgrpid)'.
							' OR EXISTS('.
								' SELECT hgg.hostid'.
									' FROM hosts_groups hgg, rights rr, users_groups gg'.
									' WHERE hgg.hostid='.zbx_dbcast_2bigint('cc.value').
										' AND rr.id=hgg.groupid'.
										' AND rr.groupid=gg.usrgrpid'.
										' AND gg.userid='.$userid.
										' AND rr.permission<'.$permission.')'.
							' )'.
				' )';

// condition trigger
			$sql_parts['where'][] =
				' NOT EXISTS('.
					' SELECT cc.conditionid '.
					' FROM conditions cc '.
					' WHERE cc.conditiontype='.CONDITION_TYPE_TRIGGER.
						' AND cc.actionid=c.actionid'.
						' AND ('.
							' NOT EXISTS('.
								' SELECT f.triggerid'.
								' FROM functions f, items i,hosts_groups hg, rights r, users_groups ug'.
								' WHERE ug.userid='.$userid.
									' AND r.groupid=ug.usrgrpid'.
									' AND r.permission>='.$permission.
									' AND hg.groupid=r.id'.
									' AND i.hostid=hg.hostid'.
									' AND f.itemid=i.itemid'.
									' AND f.triggerid='.zbx_dbcast_2bigint('cc.value').')'.
							' OR EXISTS('.
								' SELECT ff.functionid'.
								' FROM functions ff, items ii'.
								' WHERE ff.triggerid='.zbx_dbcast_2bigint('cc.value').
									' AND ii.itemid=ff.itemid'.
									' AND EXISTS('.
										' SELECT hgg.groupid'.
										' FROM hosts_groups hgg, rights rr, users_groups ugg'.
										' WHERE hgg.hostid=ii.hostid'.
											' AND rr.id=hgg.groupid'.
											' AND rr.groupid=ugg.usrgrpid'.
											' AND ugg.userid='.$userid.
											' AND rr.permission<'.$permission.'))'.
					  ' )'.
				' )';
// condition users
			$sql_parts['where'][] =
				' NOT EXISTS('.
					' SELECT o.operationid '.
					' FROM operations o '.
					' WHERE o.operationtype='.OPERATION_TYPE_MESSAGE.
						' AND o.actionid=a.actionid'.
						' AND (('.
								' o.object='.OPERATION_OBJECT_USER.
								' AND o.objectid NOT IN ('.
									' SELECT DISTINCT ug.userid'.
									' FROM users_groups ug'.
									' WHERE ug.usrgrpid IN ('.
										' SELECT uug.usrgrpid'.
										' FROM users_groups uug'.
										' WHERE uug.userid='.$USER_DETAILS['userid'].
										' )'.
									' )'.
							' ) OR ('.
								' o.object='.OPERATION_OBJECT_GROUP.
								' AND o.objectid NOT IN ('.
									' SELECT ug.usrgrpid'.
									' FROM users_groups ug'.
									' WHERE ug.userid='.$USER_DETAILS['userid'].
									' )'.
								' )'.
						' )'.
				' )';


		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// actionids
		if(!is_null($options['actionids'])){
			zbx_value2array($options['actionids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['actionid'] = 'a.actionid';
			}

			$sql_parts['where'][] = DBcondition('a.actionid', $options['actionids']);
		}

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['groupids'] = 'c.value';
			}

			$sql_parts['from']['conditions'] = 'conditions c';

			$sql_parts['where'][] = DBcondition('c.value', $options['groupids'], false, true);
			$sql_parts['where']['c'] = 'c.conditiontype='.CONDITION_TYPE_HOST_GROUP;
			$sql_parts['where']['ac'] = 'a.actionid=c.actionid';
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['hostids'] = 'c.value';
			}

			$sql_parts['from']['conditions'] = 'conditions c';

			$sql_parts['where'][] = DBcondition('c.value', $options['hostids'], false, true);
			$sql_parts['where']['c'] = 'c.conditiontype='.CONDITION_TYPE_HOST;
			$sql_parts['where']['ac'] = 'a.actionid=c.actionid';
		}

// triggerids
		if(!is_null($options['triggerids'])){
			zbx_value2array($options['triggerids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['triggerids'] = 'c.value';
			}

			$sql_parts['from']['conditions'] = 'conditions c';

			$sql_parts['where'][] = DBcondition('c.value', $options['triggerids'], false, true);
			$sql_parts['where']['c'] = 'c.conditiontype='.CONDITION_TYPE_TRIGGER;
			$sql_parts['where']['ac'] = 'a.actionid=c.actionid';
		}

// mediatypeids
		if(!is_null($options['mediatypeids'])){
			zbx_value2array($options['mediatypeids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['mediatypeid'] = 'opm.mediatypeid';
			}

			$sql_parts['from']['opmediatypes'] = 'opmediatypes opm';
			$sql_parts['from']['operations'] = 'operations o';

			$sql_parts['where'][] = DBcondition('opm.mediatypeid', $options['mediatypeids']);
			$sql_parts['where']['oopm'] = 'opm.operationid=o.operationid';
			$sql_parts['where']['ao'] = 'a.actionid=o.actionid';
		}

// userids
		if(!is_null($options['userids'])){
			zbx_value2array($options['userids']);
// TODO:
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('actions a', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('actions a', $options, $sql_parts);
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['actions'] = 'a.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('COUNT(DISTINCT a.actionid) as rowscount');
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
//---------------

		$actionids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('a.actionid', $nodeids).
					$sql_where.
				$sql_order;
//SDI($sql);
		$db_res = DBselect($sql, $sql_limit);
		while($action = DBfetch($db_res)){

			if($options['countOutput']){
				$result = $action['rowscount'];
			}
			else{
				$actionids[$action['actionid']] = $action['actionid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$action['actionid']] = array('actionid' => $action['actionid']);
				}
				else{
					if(!isset($result[$action['actionid']])) $result[$action['actionid']]= array();

					if(!is_null($options['select_conditions']) && !isset($result[$action['actionid']]['conditions'])){
						$result[$action['actionid']]['conditions'] = array();
					}

					if(!is_null($options['select_operations']) && !isset($result[$action['actionid']]['operations'])){
						$result[$action['actionid']]['operations'] = array();
					}

					$result[$action['actionid']] += $action;
				}
			}
		}

COpt::memoryPick();
		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
// Adding Conditions
		if(!is_null($options['select_conditions']) && str_in_array($options['select_conditions'], $subselects_allowed_outputs)){
			$sql = 'SELECT c.* FROM conditions c WHERE '.DBcondition('c.actionid', $actionids);
			$res = DBselect($sql);
			while($condition = DBfetch($res)){
				$result[$condition['actionid']]['conditions'][] = $condition;
			}
		}

// Adding Operations
		if(!is_null($options['select_operations']) && str_in_array($options['select_operations'], $subselects_allowed_outputs)){
			$operations = array();
			$operationids = array();
			$sql = 'SELECT o.* '.
					' FROM operations o '.
					' WHERE '.DBcondition('o.actionid', $actionids);
			$res = DBselect($sql);
			while($operation = DBfetch($res)){
				$operation['opconditions'] = array();
				$operation['opmediatypes'] = array();

				$operations[$operation['operationid']] = $operation;
				$operationids[$operation['operationid']] = $operation['operationid'];
			}

			$sql = 'SELECT op.* FROM opconditions op WHERE '.DBcondition('op.operationid', $operationids);
			$res = DBselect($sql);
			while($opcondition = DBfetch($res)){
				$operations[$opcondition['operationid']]['opconditions'][] = $opcondition;
			}

			$sql = 'SELECT op.* FROM opmediatypes op WHERE '.DBcondition('op.operationid', $operationids);
			$res = DBselect($sql);
			while($opmediatype = DBfetch($res)){
				$operations[$opmediatype['operationid']]['opmediatypes'][] = $opmediatype;
			}

			foreach($operations as $num => $operation){
				$result[$operation['actionid']]['operations'][] = $operation;
			}
		}

COpt::memoryPick();
// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	public static function exists($object){
		$keyFields = array(array('actionid', 'name'));

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
/**
 * Add actions
 *
 * @param _array $actions multidimensional array with actions data
 * @param array $actions[0,...]['expression']
 * @param array $actions[0,...]['description']
 * @param array $actions[0,...]['type'] OPTIONAL
 * @param array $actions[0,...]['priority'] OPTIONAL
 * @param array $actions[0,...]['status'] OPTIONAL
 * @param array $actions[0,...]['comments'] OPTIONAL
 * @param array $actions[0,...]['url'] OPTIONAL
 * @return boolean
 */
	public static function create($actions){
		$actions = zbx_toArray($actions);
		$conditions = array();
		$operations = array();

		try{
			self::BeginTransaction(__METHOD__);

// Check fields
			$action_db_fields = array(
				'name' => null,
				'eventsource' => null,
				'evaltype' => null,
			);
			$duplicates = array();
			foreach($actions as $anum => $action){
				if(!check_db_fields($action_db_fields, $action)){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_PARAMETER_USED_FOR_ACTION.' [ '.$action['name'].' ]');
				}

				if(isset($duplicates[$action['name']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, S_ACTION.' [ '.$action['name'].' ] '.S_ALREADY_EXISTS_SMALL);
				else
					$duplicates[$action['name']] = $action['name'];
			}

			$options = array(
				'filter' => array('name' => $duplicates),
				'output' => API_OUTPUT_EXTEND,
				'editable' => 1,
				'nopermissions' => 1
			);
			$dbActions = self::get($options);
			foreach($dbActions as $anum => $dbAction){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_ACTION.' [ '.$dbAction['name'].' ] '.S_ALREADY_EXISTS_SMALL);
			}
//------

			$actionids = DB::insert('actions', $actions);

			foreach($actions as $anum => $action){
				if(isset($action['conditions']) && !empty($action['conditions'])){
					foreach($action['conditions'] as $condition){
						$conditions[] = array_merge(array('actionid' => $actionids[$anum]), $condition);
					}
				}

				if(!isset($action['operations']) || empty($action['operations'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_PARAMETER_USED_FOR_ACTION.' [ '.$action['name'].' ]');
				}
				else{
					foreach($action['operations'] as $operation){
						$operations[] = array_merge(array('actionid' => $actionids[$anum]), $operation);
					}
				}
			}

			self::addOperations($operations);
			self::addConditions($conditions);

			self::EndTransaction(true, __METHOD__);
			return array('actionids' => $actionids);
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
 * Update actions
 *
 * @param _array $actions multidimensional array with actions data
 * @param array $actions[0,...]['actionid']
 * @param array $actions[0,...]['expression']
 * @param array $actions[0,...]['description']
 * @param array $actions[0,...]['type'] OPTIONAL
 * @param array $actions[0,...]['priority'] OPTIONAL
 * @param array $actions[0,...]['status'] OPTIONAL
 * @param array $actions[0,...]['comments'] OPTIONAL
 * @param array $actions[0,...]['url'] OPTIONAL
 * @return boolean
 */
	public static function update($actions){
		$actions = zbx_toArray($actions);
		$actionids = zbx_objectValues($actions, 'actionid');

		$update = array();
		$operations = array();
		$conditions = array();
		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'actionids' => $actionids,
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1
			);
			$upd_actions = self::get($options);
			foreach($actions as $anum => $action){
				if(!isset($upd_actions[$action['actionid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
				}
			}

// Check fields
			$action_db_fields = array(
				'actionid' => null
			);

			$duplicates = array();
			foreach($actions as $anum => $action){
				if(!check_db_fields($action_db_fields, $action)){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_PARAMETER_USED_FOR_ACTION.' [ '.$action['name'].' ]');
				}

				if(!isset($action['name'])) continue;

				if(isset($duplicates[$action['name']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, S_ACTION.' [ '.$action['name'].' ] '.S_ALREADY_EXISTS_SMALL);
				else
					$duplicates[$action['name']] = $action['name'];
			}
//------

			foreach($actions as $anum => $action){
// existence
				if (isset($action['name'])) {
					$options = array(
						'filter' => array( 'name' => $action['name'] ),
						'output' => API_OUTPUT_SHORTEN,
						'editable' => 1,
						'nopermissions' => 1
					);
					$action_exists = self::get($options);
					if(($action_exist = reset($action_exists)) && ($action_exist['actionid'] != $action['actionid'])){
						self::exception(ZBX_API_ERROR_PARAMETERS, S_ACTION.' [ '.$action['name'].' ] '.S_ALREADY_EXISTS_SMALL);
					}
				}
//----

				if(isset($action['conditions']) && !empty($action['conditions'])){
					foreach($action['conditions'] as $condition){
						$conditions[] = array_merge(array('actionid' => $action['actionid']), $condition);
					}
				}

				if (isset($action['operations']) && !empty($action['operations'])) {
					foreach ($action['operations'] as $operation) {
						$operations[] = array_merge(array('actionid' => $action['actionid']), $operation);
					}
				}

				$actionid = $action['actionid'];
				unset($action['actionid']);
				if(!empty($action)){
					$update[] = array(
						'values' => $action,
						'where' => array('actionid='.$actionid),
					);
				}
			}

			DB::update('actions', $update);

			if (!empty($operations)) {
				$operationids = array();
				$sql = 'SELECT operationid FROM operations WHERE '.DBcondition('actionid', $actionids);
				$operations_db = DBselect($sql);
				while($operationid = DBfetch($operations_db)){
					$operationids[] = $operationid['operationid'];
				}

				DB::delete('opconditions', DBcondition('operationid', $operationids));
				DB::delete('opmediatypes', DBcondition('operationid', $operationids));
				DB::delete('operations', DBcondition('actionid', $actionids));

				self::addOperations($operations);
			}

			if (!empty($conditions)) {
				DB::delete('conditions', DBcondition('actionid', $actionids));
				self::addConditions($conditions);
			}

			self::EndTransaction(true, __METHOD__);
			return array('actionids' => $actionids);
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
 * add conditions
 *
 * @param array $conditions multidimensional array with conditions data
 * @param array $conditions[0,...]['actionid']
 * @param array $conditions[0,...]['conditiontype']
 * @param array $conditions[0,...]['value']
 * @param array $conditions[0,...]['operator']
 * @return boolean
 */
	protected static function addConditions($conditions){
		$conditions = zbx_toArray($conditions);
		$conditions_insert = array();

		if(!check_permission_for_action_conditions($conditions)){
			self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
		}

		foreach($conditions as $cnum => $condition){
			if(!validate_condition($condition['conditiontype'], $condition['value'])){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_PARAMETER_USED_FOR_CONDITIONS);
			}
			$conditions_insert[] = $condition;
		}

		DB::insert('conditions', $conditions_insert);

		return true;
	}

/**
 * add operations
 *
 * @param array $operations multidimensional array with operations data
 * @param array $operations[0,...]['actionid']
 * @param array $operations[0,...]['operationtype']
 * @param array $operations[0,...]['object']
 * @param array $operations[0,...]['objectid']
 * @param array $operations[0,...]['shortdata']
 * @param array $operations[0,...]['longdata']
 * @param array $operations[0,...]['esc_period']
 * @param array $operations[0,...]['esc_step_from']
 * @param array $operations[0,...]['esc_step_to']
 * @param array $operations[0,...]['default_msg']
 * @param array $operations[0,...]['evaltype']
 * @param array $operations[0,...]['mediatypeid']
 * @param array $operations[0,...]['opconditions']
 * @param array $operations[0,...]['opconditions']['conditiontype']
 * @param array $operations[0,...]['opconditions']['operator']
 * @param array $operations[0,...]['opconditions']['value']
 * @return boolean
 */
	protected static function addOperations($operations){
		$operations = zbx_toArray($operations);
		$operation_inserts = array();
		$opcondition_inserts = array();
		$opmediatype_inserts = array();

		foreach($operations as $onum => $operation){
			if(!validate_operation($operation)){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}
		}

		foreach($operations as $onum => $operation){
			$operation_db_fields = array(
				'actionid' => null,
				'operationtype' => null,
			);
			if(!check_db_fields($operation_db_fields, $operation)){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_PARAMETER_USED_FOR_OPERATIONS);
			}

			$operation_inserts[$onum] = $operation;
		}

		$operationids = DB::insert('operations', $operation_inserts);

		foreach($operations as $onum => $operation){
			if(isset($operation['opconditions'])){
				foreach($operation['opconditions'] as $opcondition){
					$opcondition['operationid'] = $operationids[$onum];
					$opcondition_inserts[] = $opcondition;
				}
			}

			if($operation['mediatypeid'] > 0){
				$opmediatype_inserts[] = array(
					'operationid' => $operationids[$onum],
					'mediatypeid' => $operation['mediatypeid'],
				);
			}
		}

		DB::insert('opconditions', $opcondition_inserts);
		DB::insert('opmediatypes', $opmediatype_inserts);

		return true;
	}

/**
 * Delete actions
 *
 * @param array $actionids
 * @param array $actionids['actionids']
 * @return boolean
 */
	public static function delete($actionids){
		if(empty($actionids)) return true;
		$actionids = zbx_toArray($actionids);

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'actionids' => $actionids,
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1
			);
			$del_actions = self::get($options);
			foreach($actionids as $actionid){
				if(!isset($del_actions[$actionid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			$operationids = array();
			$sql = 'SELECT operationid FROM operations WHERE '.DBcondition('actionid', $actionids);
			$operations_db = DBselect($sql);
			while($operationid = DBfetch($operations_db)){
				$operationids[] = $operationid['operationid'];
			}

			DB::delete('conditions', DBcondition('actionid', $actionids));
			DB::delete('opconditions', DBcondition('operationid', $operationids));
			DB::delete('opmediatypes', DBcondition('operationid', $operationids));
			DB::delete('alerts', DBcondition('actionid', $actionids));
			DB::delete('operations', DBcondition('actionid', $actionids));
			DB::delete('actions', DBcondition('actionid', $actionids));

			self::EndTransaction(true, __METHOD__);

			return array('actionids' => $actionids);
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
