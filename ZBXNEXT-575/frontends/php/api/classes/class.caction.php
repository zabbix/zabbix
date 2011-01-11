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

// OutPut
			'output'				=> API_OUTPUT_REFER,
			'selectConditions'		=> null,
			'selectOperations'		=> null,
			'countOutput'			=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = zbx_array_merge($def_options, $options);

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
// operation users
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
				$sql_parts['select']['mediatypeid'] = 'om.mediatypeid';
			}

			$sql_parts['from']['opmessage'] = 'opmessage om';

			$sql_parts['where'][] = DBcondition('om.mediatypeid', $options['mediatypeids']);
			$sql_parts['where']['ao'] = 'a.actionid=o.actionid';
			$sql_parts['where']['oom'] = 'o.operationid=om.operationid';
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

					if(!is_null($options['selectConditions']) && !isset($result[$action['actionid']]['conditions'])){
						$result[$action['actionid']]['conditions'] = array();
					}

					if(!is_null($options['selectOperations']) && !isset($result[$action['actionid']]['operations'])){
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
		if(!is_null($options['selectConditions']) && str_in_array($options['selectConditions'], $subselects_allowed_outputs)){
			$sql = 'SELECT c.* FROM conditions c WHERE '.DBcondition('c.actionid', $actionids);
			$res = DBselect($sql);
			while($condition = DBfetch($res)){
				$result[$condition['actionid']]['conditions'][] = $condition;
			}
		}

// Adding Operations
		if(!is_null($options['selectOperations']) && str_in_array($options['selectOperations'], $subselects_allowed_outputs)){
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
				if(!isset($operations[$opcondition['operationid']]['opconditions']))
					$operations[$opcondition['operationid']]['opconditions'] = array();
				$operations[$opcondition['operationid']]['opconditions'][] = $opcondition;
			}


			$opmessage = $opcommand = $opgroup = $optemplate = array();
			foreach($operations as $operationid => $operation){
				switch($operation['operationtype']){
					case OPERATION_TYPE_MESSAGE:
						$opmessage[] = $operationid;
						break;
					case OPERATION_TYPE_COMMAND:
						$opcommand[] = $operationid;
						break;
					case OPERATION_TYPE_GROUP_ADD:
					case OPERATION_TYPE_GROUP_REMOVE:
						$opgroup[] = $operationid;
						break;
					case OPERATION_TYPE_TEMPLATE_ADD:
					case OPERATION_TYPE_TEMPLATE_REMOVE:
						$optemplate[] = $operationid;
						break;
					case OPERATION_TYPE_HOST_ADD:
					case OPERATION_TYPE_HOST_REMOVE:
					case OPERATION_TYPE_HOST_ENABLE:
					case OPERATION_TYPE_HOST_DISABLE:
				}
			}

// get OPERATION_TYPE_MESSAGE data
			if(!empty($opmessage)){
				$sql = 'SELECT operationid, default_msg, subject, message, mediatypeid '.
						' FROM opmessage '.
						' WHERE '.DBcondition('operationid', $opmessage);
				$db_opmessages = DBselect($sql);
				while($db_opmessage = DBfetch($db_opmessages)){
					$operations[$db_opmessage['operationid']]['opmessage_grp'] = array();
					$operations[$db_opmessage['operationid']]['opmessage_usr'] = array();
					$operations[$db_opmessage['operationid']]['opmessage'] = $db_opmessage;
				}

				$sql = 'SELECT operationid, usrgrpid '.
						' FROM opmessage_grp '.
						' WHERE '.DBcondition('operationid', $opmessage);
				$db_opmessage_grp = DBselect($sql);
				while($opmessage_grp = DBfetch($db_opmessage_grp)){
					$operations[$opmessage_grp['operationid']]['opmessage_grp'][] = $opmessage_grp;
				}

				$sql = 'SELECT operationid, userid '.
						' FROM opmessage_usr '.
						' WHERE '.DBcondition('operationid', $opmessage);
				$db_opmessage_usr = DBselect($sql);
				while($opmessage_usr = DBfetch($db_opmessage_usr)){
					$operations[$opmessage_usr['operationid']]['opmessage_usr'][] = $opmessage_usr;
				}
			}

// get OPERATION_TYPE_COMMAND data
			if(!empty($opcommand)){
				$sql = 'SELECT operationid, hostid, command '.
						' FROM opcommand_hst '.
						' WHERE '.DBcondition('operationid', $opcommand);
				$db_opcommand_hst = DBselect($sql);
				while($opcommand_hst = DBfetch($db_opcommand_hst)){
					if(!isset($operations[$opcommand_hst['operationid']]['opcommand_hst']))
						$operations[$opcommand_hst['operationid']]['opcommand_hst'] = array();
					$operations[$opcommand_hst['operationid']]['opcommand_hst'][] = $opcommand_hst;
				}

				$sql = 'SELECT operationid, groupid, command'.
						' FROM opcommand_grp'.
						' WHERE '.DBcondition('operationid', $opcommand);
				$db_opcommand_grp = DBselect($sql);
				while($opcommand_grp = DBfetch($db_opcommand_grp)){
					if(!isset($operations[$opcommand_grp['operationid']]['opcommand_grp']))
						$operations[$opcommand_grp['operationid']]['opcommand_grp'] = array();
					$operations[$opcommand_grp['operationid']]['opcommand_grp'][] = $opcommand_grp;
				}
			}

// get OPERATION_TYPE_GROUP_ADD, OPERATION_TYPE_GROUP_REMOVE data
			if(!empty($opgroup)){
				$sql = 'SELECT operationid, groupid'.
						' FROM opgroup'.
						' WHERE '.DBcondition('operationid', $opmessage);
				$db_opgroup = DBselect($sql);
				while($opgroup = DBfetch($db_opgroup)){
					if(!isset($operations[$opgroup['operationid']]['opgroup']))
						$operations[$opgroup['operationid']]['opgroup'] = array();
					$operations[$opgroup['operationid']]['opgroup'][] = $opgroup;
				}
			}

// get OPERATION_TYPE_TEMPLATE_ADD, OPERATION_TYPE_TEMPLATE_REMOVE data
			if(!empty($optemplate)){
				$sql = 'SELECT operationid, templateid'.
						' FROM optemplate'.
						' WHERE '.DBcondition('operationid', $opmessage);
				$db_optemplate = DBselect($sql);
				while($optemplate = DBfetch($db_optemplate)){
					if(!isset($operations[$optemplate['operationid']]['optemplate']))
						$operations[$optemplate['operationid']]['optemplate'] = array();
					$operations[$optemplate['operationid']]['optemplate'][] = $optemplate;
				}
			}


			foreach($operations as $operation){
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
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action [%s] already exists.', $action['name']));
				else
					$duplicates[$action['name']] = $action['name'];
			}

			$options = array(
				'filter' => array('name' => $duplicates),
				'output' => API_OUTPUT_SHORTEN,
				'editable' => 1,
				'nopermissions' => 1
			);
			$dbActions = self::get($options);
			foreach($dbActions as $anum => $dbAction){
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action [%s] already exists.', $dbAction['name']));
			}
//------

			$actionids = DB::insert('actions', $actions);

			$conditions = array();
			$operations = array();
			foreach($actions as $anum => $action){
				if(isset($action['conditions']) && !empty($action['conditions'])){
					foreach($action['conditions'] as $condition){
						$condition['actionid'] = $actionids[$anum];
						$conditions[] = $condition;
					}
				}

				if(!isset($action['operations']) || empty($action['operations'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_PARAMETER_USED_FOR_ACTION.' [ '.$action['name'].' ]');
				}
				else{
					foreach($action['operations'] as $operation){
						$operation['actionid'] = $actionids[$anum];
						$operations[] = $operation;
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

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'actionids' => $actionids,
				'editable' => true,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
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
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action [%s] already exists.', $action['name']));
				else
					$duplicates[$action['name']] = $action['name'];
			}
//------

			$conditions = array();
			$operations = array();
			foreach($actions as $anum => $action){
// Existance
				$options = array(
					'filter' => array( 'name' => $action['name'] ),
					'output' => API_OUTPUT_SHORTEN,
					'editable' => 1,
					'nopermissions' => 1
				);
				$action_exists = self::get($options);
				if(($action_exist = reset($action_exists)) && ($action_exist['actionid'] != $action['actionid'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action [%s] already exists.', $action['name']));
				}
//----

				if(isset($action['conditions']) && !empty($action['conditions'])){
					foreach($action['conditions'] as $condition){
						$condition['actionid'] = $action['actionid'];
						$conditions[] = $condition;
					}
				}

				if(!isset($action['operations']) || empty($action['operations'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_PARAMETER_USED_FOR_ACTION.' [ '.$action['name'].' ]');
				}
				else{
					foreach($action['operations'] as $operation){
						$operation['actionid'] = $action['actionid'];
						$operations[] = $operation;
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

			DB::delete('conditions', array('actionid' => $actionids));
			DB::delete('operations', array('actionid' => $actionids));

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

	protected static function addConditions($conditions){
		$conditions = zbx_toArray($conditions);

		if(!check_permission_for_action_conditions($conditions)){
			self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
		}

		foreach($conditions as $cnum => $condition){
			if(!validate_condition($condition['conditiontype'], $condition['value'])){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_PARAMETER_USED_FOR_CONDITIONS);
			}
		}

		DB::insert('conditions', $conditions);

		return true;
	}

	protected static function addOperations($operations){
		$operations = zbx_toArray($operations);

		self::validateOperations($operations);

		foreach($operations as $operation){
			$operation_db_fields = array(
				'actionid' => null,
				'operationtype' => null,
			);
			if(!check_db_fields($operation_db_fields, $operation)){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_PARAMETER_USED_FOR_OPERATIONS);
			}
		}

		$operationids = DB::insert('operations', $operations);

		$opmessage = $opmessage_grp = $opmessage_usr = $opcommand_hst = $opcommand_grp = $opgroup = $optemplate = array();
		foreach($operations as $onum => $operation){
			$operationid = $operationids[$onum];

			switch($operation['operationtype']){
				case OPERATION_TYPE_MESSAGE:
					if(isset($operation['opmessage']) && !empty($operation['opmessage'])){
						$operation['opmessage']['operationid'] = $operationid;
						$opmessage[] = $operation['opmessage'];
					}

					if(isset($operation['userids'])){
						foreach($operation['userids'] as $userid){
							$opmessage_usr[] = array(
								'operationid' => $operationid,
								'userid' => $userid
							);
						}
					}

					if(isset($operation['usrgrpid'])){
						foreach($operation['usrgrpid'] as $usrgrpid){
							$opmessage_grp[] = array(
								'operationid' => $operationid,
								'usrgrpid' => $usrgrpid
							);
						}
					}

					break;
				case OPERATION_TYPE_COMMAND:
					if(isset($operation['opcommand_hst'])){
						foreach($operation['opcommand_hst'] as $hst){
							$opcommand_hst[] = array(
								'operationid' => $operationid,
								'hostid' => $hst['hostid'],
								'command' => $hst['command'],
							);
						}
					}

					if(isset($operation['opcommand_grp'])){
						foreach($operation['opcommand_grp'] as $grp){
							$opcommand_grp[] = array(
								'operationid' => $operationid,
								'groupid' => $grp['groupid'],
								'command' => $grp['command'],
							);
						}
					}

					break;
				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					foreach($operation['opgroup'] as $grp){
						$opgroup[] = array(
							'operationid' => $operationid,
							'groupid' => $grp['groupid'],
						);
					}
					break;
				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					foreach($operation['optemplate'] as $tpl){
						$optemplate[] = array(
							'operationid' => $operationid,
							'templateid' => $tpl['templateid'],
						);
					}
					break;
				case OPERATION_TYPE_HOST_ADD:
				case OPERATION_TYPE_HOST_REMOVE:
				case OPERATION_TYPE_HOST_ENABLE:
				case OPERATION_TYPE_HOST_DISABLE:
			}

			$opcondition_inserts = array();
			if(isset($operation['opconditions'])){
				foreach($operation['opconditions'] as $opcondition){
					$opcondition['operationid'] = $operationid;
					$opcondition_inserts[] = $opcondition;
				}
			}
		}

		DB::insert('opconditions', $opcondition_inserts);

		DB::insert('opmessage', $opmessage);
		DB::insert('opmessage_grp', $opmessage_grp);
		DB::insert('opmessage_usr', $opmessage_usr);
		DB::insert('opcommand_hst', $opcommand_hst);
		DB::insert('opcommand_grp', $opcommand_grp);
		DB::insert('opgroup', $opgroup);
		DB::insert('optemplate', $optemplate);

		return true;
	}

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

			DB::delete('actions', array('actionid'=>$actionids));

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

	public static function validateOperations($operations){
		$operations = zbx_toArray($operations);

		foreach($operations as $operation){

			if(($operation['esc_step_from'] >= $operation['esc_step_to']) && ($operation['esc_step_to'] != 0)){
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect operation escalation step values.'));
			}

			if(isset($operation['esc_period']) && (($operation['esc_period'] > 0) && ($operation['esc_period'] < 60))){
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect operation escalation period.'));
			}

			switch($operation['operationtype']){
				case OPERATION_TYPE_MESSAGE:
					if((!isset($operation['userids']) || empty($operation['userids']))
							&& (!isset($operation['usrgrpid']) || empty($operation['usrgrpid']))){
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No recipients for operation message.'));
					}

					if(isset($operation['userids'])){
						$count = count(array_unique($operation['userids']));

						$users = CUser::get(array(
							'userids' => $operation['userids'],
							'output' => API_OUTPUT_SHORTEN
						));

						if($count != count($users)){
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect operation user.'));
						}
					}

					if(isset($operation['usrgrpid'])){
						$count = count(array_unique($operation['usrgrpid']));

						$usrgrps = CUserGroup::get(array(
							'usrgrpids' => $operation['objectid'],
							'output' => API_OUTPUT_SHORTEN
						));

						if($count != count($usrgrps)){
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect operation group.'));
						}
					}

					break;
				case OPERATION_TYPE_COMMAND:
					if((!isset($operation['opcommand_hst']) || empty($operation['opcommand_hst']))
							&& (!isset($operation['opcommand_grp']) || empty($operation['opcommand_grp']))){
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No targets for operation command.'));
					}

					// self::validateCommands($operation['longdata']);
					break;
				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					if(!isset($operation['opgroup']) || empty($operation['opgroup'])){
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Operation has no group to operate.'));
					}

					$group = CHostGroup::get(array(
						'groupids' => $operation['opgroup'],
						'output' => API_OUTPUT_SHORTEN,
						'editable' => 1,
					));
					if(empty($group)){
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect operation group.'));
					}
					break;
				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					if(!isset($operation['optemplate']) || empty($operation['optemplate'])){
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Operation has no template to operate.'));
					}

					$tpl = CTemplate::get(array(
						'templateids' => $operation['optemplate'],
						'output' => API_OUTPUT_SHORTEN,
						'editable' => 1,
					));
					if(empty($tpl)){
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect operation template.'));
					}
					break;
				case OPERATION_TYPE_HOST_ADD:
				case OPERATION_TYPE_HOST_REMOVE:
				case OPERATION_TYPE_HOST_ENABLE:
				case OPERATION_TYPE_HOST_DISABLE:
					break;
				default:
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect operation type.'));
			}
		}

		return true;
	}

	public static function validateCommands($commands){

		return true;

		$cmd_list = explode("\n",$commands);
		foreach($cmd_list as $cmd){
			$cmd = trim($cmd, "\x00..\x1F");
	//		if(!ereg("^(({HOSTNAME})|".ZBX_EREG_INTERNAL_NAMES.")(:|#)[[:print:]]*$",$cmd,$cmd_items)){
			if(!preg_match("/^(({HOSTNAME})|".ZBX_PREG_INTERNAL_NAMES.")(:|#)[".ZBX_PREG_PRINT."]*$/", $cmd, $cmd_items)){
				error(S_INCORRECT_COMMAND.": '$cmd'");
				return FALSE;
			}

			if($cmd_items[4] == '#'){ // group
				if(!DBfetch(DBselect('select groupid from groups where name='.zbx_dbstr($cmd_items[1])))){
					error(S_UNKNOWN_GROUP_NAME.": '".$cmd_items[1]."' ".S_IN_COMMAND_SMALL." '".$cmd."'");
					return FALSE;
				}
			}
			else if($cmd_items[4] == ':'){ // host
				if(($cmd_items[1] != '{HOSTNAME}') && !DBfetch(DBselect('select hostid from hosts where host='.zbx_dbstr($cmd_items[1])))){
					error(S_UNKNOWN_HOST_NAME.": '".$cmd_items[1]."' ".S_IN_COMMAND_SMALL." '".$cmd."'");
					return FALSE;
				}
			}
		}
	}

}
?>
