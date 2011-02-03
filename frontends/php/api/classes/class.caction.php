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
			'searchByAny'			=> null,
			'startSearch'				=> null,
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
// conditions are checked here by sql, operations after, by api queries
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

// condition hostgroup
			$sql_parts['where'][] =
				' NOT EXISTS('.
					' SELECT cc.conditionid'.
					' FROM conditions cc'.
					' WHERE cc.conditiontype='.CONDITION_TYPE_HOST_GROUP.
						' AND cc.actionid=a.actionid'.
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
						' AND cc.actionid=a.actionid'.
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
						' AND cc.actionid=a.actionid'.
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

			$sql_parts['where'][] = DBcondition('c.value', $options['groupids']);
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

			$sql_parts['where'][] = DBcondition('c.value', $options['hostids']);
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

			$sql_parts['where'][] = DBcondition('c.value', $options['triggerids']);
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
			$sql_parts['from']['operations'] = 'operations o';

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

		if((USER_TYPE_SUPER_ADMIN == $user_type) || !is_null($options['nopermissions'])){
		}
		else{
// check hosts, templates
			$hosts = $hostids = array();
			$sql = 'SELECT o.actionid, och.hostid'.
					' FROM operations o, opcommand_hst och'.
					' WHERE o.operationid=och.operationid'.
						' AND och.hostid<>0'.
						' AND '.DBcondition('o.actionid', $actionids);
			$db_hosts = DBselect($sql);
			while($host = DBfetch($db_hosts)){
				if(!isset($hosts[$host['hostid']])) $hosts[$host['hostid']] = array();
				$hosts[$host['hostid']][$host['actionid']] = $host['actionid'];
				$hostids[$host['hostid']] = $host['hostid'];
			}

			$sql = 'SELECT o.actionid, ot.templateid'.
					' FROM operations o, optemplate ot'.
					' WHERE o.operationid=ot.operationid'.
						' AND '.DBcondition('o.actionid', $actionids);
			$db_templates = DBselect($sql);
			while($template = DBfetch($db_templates)){
				if(!isset($hosts[$template['templateid']])) $hosts[$template['templateid']] = array();
				$hosts[$template['templateid']][$template['actionid']] = $template['actionid'];
				$hostids[$template['templateid']] = $template['templateid'];
			}

			$allowed_hosts = CHost::get(array(
				'hostids' => $hostids,
				'output' => API_OUTPUT_SHORTEN,
				'editable' => $options['editable'],
				'templated_hosts' => true,
				'preservekeys' => true,
			));
			foreach($hostids as $hostid){
				if(!isset($allowed_hosts[$hostid])){
					foreach($hosts[$hostid] as $actionid){
						unset($result[$actionid], $actionids[$actionid]);
					}
				}
			}


// check hostgroups
			$groups = $groupids = array();
			$sql = 'SELECT o.actionid, ocg.groupid'.
					' FROM operations o, opcommand_grp ocg'.
					' WHERE o.operationid=ocg.operationid'.
						' AND '.DBcondition('o.actionid', $actionids);
			$db_groups = DBselect($sql);
			while($group = DBfetch($db_groups)){
				if(!isset($groups[$group['groupid']])) $groups[$group['groupid']] = array();
				$groups[$group['groupid']][$group['actionid']] = $group['actionid'];
				$groupids[$group['groupid']] = $group['groupid'];
			}

			$sql = 'SELECT o.actionid, og.groupid'.
					' FROM operations o, opgroup og'.
					' WHERE o.operationid=og.operationid'.
						' AND '.DBcondition('o.actionid', $actionids);
			$db_groups = DBselect($sql);
			while($group = DBfetch($db_groups)){
				if(!isset($groups[$group['groupid']])) $groups[$group['groupid']] = array();
				$groups[$group['groupid']][$group['actionid']] = $group['actionid'];
				$groupids[$group['groupid']] = $group['groupid'];
			}

			$allowed_groups = CHostGroup::get(array(
				'groupids' => $groupids,
				'output' => API_OUTPUT_SHORTEN,
				'editable' => $options['editable'],
				'preservekeys' => true,
			));
			foreach($groupids as $groupid){
				if(!isset($allowed_groups[$groupid])){
					foreach($groups[$groupid] as $actionid){
						unset($result[$actionid], $actionids[$actionid]);
					}
				}
			}

// check users
			$users = $userids = array();
			$sql = 'SELECT o.actionid, omu.userid'.
					' FROM operations o, opmessage_usr omu'.
					' WHERE o.operationid=omu.operationid'.
						' AND '.DBcondition('o.actionid', $actionids);
			$db_users = DBselect($sql);
			while($user = DBfetch($db_users)){
				if(!isset($users[$user['userid']])) $users[$user['userid']] = array();
				$users[$user['userid']][$user['actionid']] = $user['actionid'];
				$userids[$user['userid']] = $user['userid'];
			}

			$allowed_users = CUser::get(array(
				'userids' => $userids,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true,
			));
			foreach($userids as $userid){
				if(!isset($allowed_users[$userid])){
					foreach($users[$userid] as $actionid){
						unset($result[$actionid], $actionids[$actionid]);
					}
				}
			}

// check usergroups
			$usrgrps = $usrgrpids = array();
			$sql = 'SELECT o.actionid, omg.usrgrpid'.
					' FROM operations o, opmessage_grp omg'.
					' WHERE o.operationid=omg.operationid'.
						' AND '.DBcondition('o.actionid', $actionids);
			$db_usergroups = DBselect($sql);
			while($usrgrp = DBfetch($db_usergroups)){
				if(!isset($usrgrps[$usrgrp['usrgrpid']])) $usrgrps[$usrgrp['usrgrpid']] = array();
				$usrgrps[$usrgrp['usrgrpid']][$usrgrp['actionid']] = $usrgrp['actionid'];
				$usrgrpids[$usrgrp['usrgrpid']] = $usrgrp['usrgrpid'];
			}

			$allowed_usrgrps = CUserGroup::get(array(
				'usrgrpids' => $usrgrpids,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true,
			));

			foreach($usrgrpids as $usrgrpid){
				if(!isset($allowed_usrgrps[$usrgrpid])){
					foreach($usrgrps[$usrgrpid] as $actionid){
						unset($result[$actionid], $actionids[$actionid]);
					}
				}
			}
		}

COpt::memoryPick();
		if(!is_null($options['countOutput'])){
			return $result;
		}

// Adding Objects
// Adding Conditions
		if(!is_null($options['selectConditions']) && str_in_array($options['selectConditions'], $subselects_allowed_outputs)){
			$sql = 'SELECT c.* FROM conditions c WHERE '.DBcondition('c.actionid', $actionids);
			$res = DBselect($sql);
			while($condition = DBfetch($res)){
				$result[$condition['actionid']]['conditions'][$condition['conditionid']] = $condition;
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
				$sql = 'SELECT opcommand_hstid, operationid, hostid, command '.
						' FROM opcommand_hst '.
						' WHERE '.DBcondition('operationid', $opcommand);
				$db_opcommand_hst = DBselect($sql);
				while($opcommand_hst = DBfetch($db_opcommand_hst)){
					if(!isset($operations[$opcommand_hst['operationid']]['opcommand_hst']))
						$operations[$opcommand_hst['operationid']]['opcommand_hst'] = array();
					$operations[$opcommand_hst['operationid']]['opcommand_hst'][] = $opcommand_hst;
				}

				$sql = 'SELECT opcommand_grpid, operationid, groupid, command'.
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
						' WHERE '.DBcondition('operationid', $opgroup);
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
						' WHERE '.DBcondition('operationid', $optemplate);
				$db_optemplate = DBselect($sql);
				while($optemplate = DBfetch($db_optemplate)){
					if(!isset($operations[$optemplate['operationid']]['optemplate']))
						$operations[$optemplate['operationid']]['optemplate'] = array();
					$operations[$optemplate['operationid']]['optemplate'][] = $optemplate;
				}
			}


			foreach($operations as $operation){
				$result[$operation['actionid']]['operations'][$operation['operationid']] = $operation;
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
				if(!check_db_fields($action_db_fields, $action))
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect parameter is used for action "%s"', $action['name']));

				if(isset($action['esc_period']) && ($action['esc_period'] < 60) && (EVENT_SOURCE_TRIGGERS == $action['eventsource']))
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%s" has incorrect value for "esc_period" (minimal 60 seconds).', $action['name']));

				if(isset($duplicates[$action['name']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%s" already exists.', $action['name']));
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
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%s" already exists.', $dbAction['name']));
			}
//------

			$actionids = DB::insert('actions', $actions);

			$conditions = $operations = array();
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

			self::validateConditions($conditions);
			self::addConditions($conditions);

			self::validateOperations($operations);
			self::addOperations($operations);


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
//sdii($actions);
		$actions = zbx_toArray($actions);
		$actionids = zbx_objectValues($actions, 'actionid');
		$update = array();

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'actionids' => $actionids,
				'editable' => true,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => true,
				'selectOperations' => API_OUTPUT_EXTEND,
				'selectConditions' => API_OUTPUT_EXTEND,
			);
			$updActions = self::get($options);
			foreach($actions as $anum => $action){
				if(isset($action['actionid']) && !isset($updActions[$action['actionid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
				}
			}

// Check fields
			$duplicates = array();
			foreach($actions as $anum => $action){
				if(!check_db_fields(array('actionid' => null), $action)){
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect parameters are used for action update method "%s"',$action['name']));
				}

// check if user change esc_period or eventsource
				if(isset($action['esc_period']) || isset($action['eventsource'])){
					$eventsource = isset($action['eventsource']) ? $action['eventsource']: $updActions[$action['actionid']]['eventsource'];
					$esc_period = isset($action['esc_period']) ? $action['esc_period']: $updActions[$action['actionid']]['esc_period'];

					if(($esc_period < 60) && (EVENT_SOURCE_TRIGGERS == $eventsource))
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%s" has incorrect value for "esc_period" (minimal 60 seconds).', $action['name']));
				}
//--
				if(!isset($action['name'])) continue;

				if(isset($duplicates[$action['name']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%s" already exists.', $action['name']));
				else
					$duplicates[$action['name']] = $action['name'];
			}
//------

			$operationsCreate = $operationsUpdate = $operationidsDelete = array();
			$conditionsCreate = $conditionsUpdate = $conditionidsDelete = array();
			foreach($actions as $anum => $action){
// Existance
				$options = array(
					'filter' => array( 'name' => $action['name'] ),
					'output' => API_OUTPUT_SHORTEN,
					'editable' => 1,
					'nopermissions' => true,
					'preservekeys' => true,
				);
				$action_exists = self::get($options);
				if(($action_exist = reset($action_exists)) && ($action_exist['actionid'] != $action['actionid'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%s" already exists.', $action['name']));
				}
//----

				if(isset($action['conditions'])){
					$conditionsDb = isset($updActions[$action['actionid']]['conditions'])
							? $updActions[$action['actionid']]['conditions']
							: array();

					self::validateConditions($action['conditions']);

					foreach($action['conditions'] as $condition){
						$condition['actionid'] = $action['actionid'];

						if(!isset($condition['conditionid'])){
							$conditionsCreate[] = $condition;
						}
						else if(isset($conditionsDb[$condition['conditionid']])){
							$conditionsUpdate[] = $condition;
							unset($conditionsDb[$condition['conditionid']]);
						}
						else{
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action conditionid'));
						}
					}

					$conditionidsDelete = array_merge($conditionidsDelete, array_keys($conditionsDb));
				}

				if(isset($action['operations']) && empty($action['operations'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Action "%s" no operations defined.', $action['name']));
				}
				else if(isset($action['operations'])){
					self::validateOperations($action['operations']);

					$operations_db = $updActions[$action['actionid']]['operations'];
					foreach($action['operations'] as $operation){
						$operation['actionid'] = $action['actionid'];

						if(!isset($operation['operationid'])){
							$operationsCreate[] = $operation;
						}
						else if(isset($operations_db[$operation['operationid']])){
							$operationsUpdate[] = $operation;
							unset($operations_db[$operation['operationid']]);
						}
						else{
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operationid'));
						}
					}
					$operationidsDelete = array_merge($operationidsDelete, array_keys($operations_db));
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

			self::addConditions($conditionsCreate);
			self::updateConditions($conditionsUpdate);
			if(!empty($conditionidsDelete))
				self::deleteConditions($conditionidsDelete);

			self::addOperations($operationsCreate);
			self::updateOperations($operationsUpdate, $updActions);
			if(!empty($operationidsDelete))
				self::deleteOperations($operationidsDelete);

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
		foreach($conditions as $condition){
			$condition_db_fields = array(
				'actionid' => null,
				'conditiontype' => null
			);
			if(!check_db_fields($condition_db_fields, $condition)){
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parameters for condition.'));
			}
		}

		DB::insert('conditions', $conditions);
	}

	protected static function updateConditions($conditions){
		$update = array();
		foreach($conditions as $condition){
			$conditionid = $condition['conditionid'];
			unset($condition['conditionid']);
			$update = array(
				'values' => $condition,
				'where' => array('conditionid='.$conditionid)
			);
		}
		DB::update('conditions', $update);
	}

	protected static function deleteConditions($conditionids){
		DB::delete('conditions', array('conditionid' => $conditionids));
	}


	protected static function addOperations($operations){
		foreach($operations as $operation){
			$operationDbFields = array(
				'actionid' => null,
				'operationtype' => null,
			);
			if(!check_db_fields($operationDbFields, $operation)){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_PARAMETER_USED_FOR_OPERATIONS);
			}
		}

		$operationids = DB::insert('operations', $operations);

		$opmessage = $opmessage_grp = $opmessage_usr = $opcommand_hst = $opcommand_grp = $opgroup = $optemplate = array();
		$opcondition_inserts = array();
		foreach($operations as $onum => $operation){
			$operationid = $operationids[$onum];

			switch($operation['operationtype']){
				case OPERATION_TYPE_MESSAGE:
					if(isset($operation['opmessage']) && !empty($operation['opmessage'])){
						$operation['opmessage']['operationid'] = $operationid;
						$opmessage[] = $operation['opmessage'];
					}

					if(isset($operation['opmessage_usr'])){
						foreach($operation['opmessage_usr'] as $user){
							$opmessage_usr[] = array(
								'operationid' => $operationid,
								'userid' => $user['userid']
							);
						}
					}

					if(isset($operation['opmessage_grp'])){
						foreach($operation['opmessage_grp'] as $usrgrp){
							$opmessage_grp[] = array(
								'operationid' => $operationid,
								'usrgrpid' => $usrgrp['usrgrpid']
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

			if(isset($operation['opconditions'])){
				foreach($operation['opconditions'] as $opcondition){
					$opcondition['operationid'] = $operationid;
					$opcondition_inserts[] = $opcondition;
				}
			}
		}

		DB::insert('opconditions', $opcondition_inserts);

		DB::insert('opmessage', $opmessage, false);
		DB::insert('opmessage_grp', $opmessage_grp);
		DB::insert('opmessage_usr', $opmessage_usr);
		DB::insert('opcommand_hst', $opcommand_hst);
		DB::insert('opcommand_grp', $opcommand_grp);
		DB::insert('opgroup', $opgroup);
		DB::insert('optemplate', $optemplate);

		return true;
	}

	protected static function updateOperations($operations, $actionsDb){
		$operationsUpdate = array();
//sdii($operations);
		$opmessageCreate = array();
		$opmessageUpdate = array();
		$opmessageDeleteByOpId = array();
		$opmessage_grpDeleteByOpId = array();
		$opmessage_usrDeleteByOpId = array();
		$opmessage_grpCreate = array();
		$opmessage_usrCreate = array();

		$opcommand_grpCreate = array();
		$opcommand_grpDelete = array();
		$opcommand_grpUpdate = array();
		$opcommand_hstCreate = array();
		$opcommand_hstDelete = array();
		$opcommand_hstUpdate = array();
		$opcommand_hstDeleteByOpId = array();
		$opcommand_grpDeleteByOpId = array();

		$opgroupCreate = array();
		$opgroupDeleteByOpId = array();

		$optemplateCreate = array();
		$optemplateDeleteByOpId = array();

		$opconditionsCreate = array();

		foreach($operations as $operation){
			$operationDb = $actionsDb[$operation['actionid']]['operations'][$operation['operationid']];

			$type_changed = false;
			if(isset($operation['operationtype']) && ($operation['operationtype'] != $operationDb['operationtype'])){
				$type_changed = true;

				switch($operationDb['operationtype']){
					case OPERATION_TYPE_MESSAGE:
						$opmessageDeleteByOpId[] = $operationDb['operationid'];
						$opmessage_grpDeleteByOpId[] = $operationDb['operationid'];
						$opmessage_usrDeleteByOpId[] = $operationDb['operationid'];
						break;
					case OPERATION_TYPE_COMMAND:
						$opcommand_hstDeleteByOpId[] = $operationDb['operationid'];
						$opcommand_grpDeleteByOpId[] = $operationDb['operationid'];
						break;
					case OPERATION_TYPE_GROUP_ADD:
						if($operation['operationtype'] == OPERATION_TYPE_GROUP_REMOVE) break;
					case OPERATION_TYPE_GROUP_REMOVE:
						if($operation['operationtype'] == OPERATION_TYPE_GROUP_ADD) break;
						$opgroupDeleteByOpId[] = $operationDb['operationid'];
						break;
					case OPERATION_TYPE_TEMPLATE_ADD:
						if($operation['operationtype'] == OPERATION_TYPE_TEMPLATE_REMOVE) break;
					case OPERATION_TYPE_TEMPLATE_REMOVE:
						if($operation['operationtype'] == OPERATION_TYPE_TEMPLATE_ADD) break;
						$optemplateDeleteByOpId[] = $operationDb['operationid'];
						break;
				}
			}


			if(!isset($operation['operationtype']))
				$operation['operationtype'] = $operationDb['operationtype'];

			switch($operation['operationtype']){
				case OPERATION_TYPE_MESSAGE:
					if(!isset($operation['opmessage_grp']))
						$operation['opmessage_grp'] = array();
					else
						zbx_array_push($operation['opmessage_grp'], array('operationid' => $operation['operationid']));

					if(!isset($operation['opmessage_usr']))
						$operation['opmessage_usr'] = array();
					else
						zbx_array_push($operation['opmessage_usr'], array('operationid' => $operation['operationid']));

					if(!isset($operationDb['opmessage_usr']))
						$operationDb['opmessage_usr'] = array();
					if(!isset($operationDb['opmessage_grp']))
						$operationDb['opmessage_grp'] = array();


					if($type_changed){
						$operation['opmessage']['operationid'] = $operation['operationid'];
						$opmessageCreate[] = $operation['opmessage'];

						$opmessage_grpCreate = array_merge($opmessage_grpCreate, $operation['opmessage_grp']);
						$opmessage_usrCreate = array_merge($opmessage_usrCreate, $operation['opmessage_usr']);
					}
					else{
						$opmessageUpdate[] = array(
							'values' => $operation['opmessage'],
							'where' => array('operationid='.$operation['operationid']),
						);

						$diff = zbx_array_diff($operationDb['opmessage_grp'], $operation['opmessage_grp'], 'usrgrpid');
						$opmessage_grpCreate = array_merge($opmessage_grpCreate, $diff['second']);

						foreach($diff['first'] as $omgrp){
							DB::delete('opmessage_grp', array(
								'usrgrpid' => $omgrp['usrgrpid'],
								'operationid' => $operation['operationid'],
							));
						}


						$diff = zbx_array_diff($operationDb['opmessage_usr'], $operation['opmessage_usr'], 'userid');
						$opmessage_usrCreate = array_merge($opmessage_usrCreate, $diff['second']);
						foreach($diff['first'] as $omusr){
							DB::delete('opmessage_usr', array(
								'userid' => $omusr['userid'],
								'operationid' => $operation['operationid'],
							));
						}
					}
					break;
				case OPERATION_TYPE_COMMAND:
					if(!isset($operation['opcommand_grp']))
						$operation['opcommand_grp'] = array();
					else
						zbx_array_push($operation['opcommand_grp'], array('operationid' => $operation['operationid']));

					if(!isset($operation['opcommand_hst']))
						$operation['opcommand_hst'] = array();
					else
						zbx_array_push($operation['opcommand_hst'], array('operationid' => $operation['operationid']));

					if(!isset($operationDb['opcommand_grp']))
						$operationDb['opcommand_grp'] = array();
					if(!isset($operationDb['opcommand_hst']))
						$operationDb['opcommand_hst'] = array();

					if($type_changed){
						$opcommand_grpCreate = array_merge($opcommand_grpCreate, $operation['opcommand_grp']);
						$opcommand_hstCreate = array_merge($opcommand_hstCreate, $operation['opcommand_hst']);
					}
					else{
						$diff = zbx_array_diff($operationDb['opcommand_grp'], $operation['opcommand_grp'], 'opcommand_grpid');
						$opcommand_grpCreate = array_merge($opcommand_grpCreate, $diff['second']);
						$opcommand_grpDelete = array_merge($opcommand_grpDelete, zbx_objectValues($diff['first'], 'opcommand_grpid'));
						foreach($diff['both'] as $opcommand_grp){
							$opcommand_grpid = $opcommand_grp['opcommand_grpid'];
							unset($opcommand_grp['opcommand_grpid']);
							$opcommand_grpUpdate[] = array(
								'values' => $opcommand_grp,
								'where' => array('opcommand_grpid='.$opcommand_grpid),
							);
						}

						$diff = zbx_array_diff($operationDb['opcommand_hst'], $operation['opcommand_hst'], 'opcommand_hstid');
						$opcommand_hstCreate = array_merge($opcommand_hstCreate, $diff['second']);
						$opcommand_hstDelete = array_merge($opcommand_hstDelete, zbx_objectValues($diff['first'], 'opcommand_hstid'));
						foreach($diff['both'] as $opcommand_hst){
							$opcommand_hstid = $opcommand_hst['opcommand_hstid'];
							unset($opcommand_hst['opcommand_hstid']);
							$opcommand_hstUpdate[] = array(
								'values' => $opcommand_hst,
								'where' => array('opcommand_hstid='.$opcommand_hstid),
							);
						}

					}
					break;
				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					if(!isset($operation['opgroup'])) $operation['opgroup'] = array();
					else zbx_array_push($operation['opgroup'], array('operationid' => $operation['operationid']));

					if(!isset($operationDb['opgroup'])) $operationDb['opgroup'] = array();

					$diff = zbx_array_diff($operationDb['opgroup'], $operation['opgroup'], 'groupid');
					$opgroupCreate = array_merge($opgroupCreate, $diff['second']);
					foreach($diff['first'] as $ogrp){
						DB::delete('opgroup', array(
							'groupid' => $ogrp['groupid'],
							'operationid' => $operation['operationid'],
						));
					}
					break;
				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					if(!isset($operation['optemplate'])) $operation['optemplate'] = array();
					else zbx_array_push($operation['optemplate'], array('operationid' => $operation['operationid']));

					if(!isset($operationDb['optemplate'])) $operationDb['optemplate'] = array();

					$diff = zbx_array_diff($operationDb['optemplate'], $operation['optemplate'], 'templateid');
					$optemplateCreate = array_merge($optemplateCreate, $diff['second']);
					foreach($diff['first'] as $otpl){
						DB::delete('optemplate', array(
							'templateid' => $otpl['templateid'],
							'operationid' => $operation['operationid'],
						));
					}
					break;
			}


			if(!isset($operation['opconditions']))
				$operation['opconditions'] = array();
			else
				zbx_array_push($operation['opconditions'], array('operationid' => $operation['operationid']));

			self::validateOperationConditions($operation['opconditions']);

			$diff = zbx_array_diff($operationDb['opconditions'], $operation['opconditions'], 'opconditionid');
			$opconditionsCreate = array_merge($opconditionsCreate, $diff['second']);

			$opconditionsidDelete = zbx_objectValues($diff['first'], 'opconditionid');
			if(!empty($opconditionsidDelete))
				DB::delete('opconditions', array('opconditionid' => $opconditionsidDelete));


			$operationid = $operation['operationid'];
			unset($operation['operationid']);
			if(!empty($operation)){
				$operationsUpdate[] = array(
					'values' => $operation,
					'where' => array('operationid='.$operationid),
				);
			}
		}

		DB::update('operations', $operationsUpdate);

		if(!empty($opmessageDeleteByOpId))
			DB::delete('opmessage', array('operationid' => $opmessageDeleteByOpId));
		if(!empty($opmessage_grpDeleteByOpId))
			DB::delete('opmessage_grp', array('operationid' => $opmessage_grpDeleteByOpId));
		if(!empty($opmessage_usrDeleteByOpId))
			DB::delete('opmessage_usr', array('operationid' => $opmessage_usrDeleteByOpId));
		if(!empty($opcommand_hstDeleteByOpId))
			DB::delete('opcommand_hst', array('operationid' => $opcommand_hstDeleteByOpId));
		if(!empty($opcommand_grpDeleteByOpId))
			DB::delete('opcommand_grp', array('operationid' => $opcommand_grpDeleteByOpId));
		if(!empty($opcommand_grpDelete))
			DB::delete('opcommand_grp', array('opcommand_grpid' => $opcommand_grpDelete));
		if(!empty($opcommand_hstDelete))
			DB::delete('opcommand_hst', array('opcommand_hstid' => $opcommand_hstDelete));
		if(!empty($opgroupDeleteByOpId))
			DB::delete('opgroup', array('operationid' => $opgroupDeleteByOpId));
		if(!empty($optemplateDeleteByOpId))
			DB::delete('optemplate', array('operationid' => $optemplateDeleteByOpId));

		DB::insert('opmessage', $opmessageCreate, false);

		DB::insert('opmessage_grp', $opmessage_grpCreate);
		DB::insert('opmessage_usr', $opmessage_usrCreate);
		DB::insert('opcommand_grp', $opcommand_grpCreate);
		DB::insert('opcommand_hst', $opcommand_hstCreate);

		DB::insert('opgroup', $opgroupCreate);
		DB::insert('optemplate', $optemplateCreate);

		DB::update('opmessage', $opmessageUpdate);
		DB::update('opcommand_grp', $opcommand_grpUpdate);
		DB::update('opcommand_hst', $opcommand_hstUpdate);


		DB::insert('opconditions', $opconditionsCreate);
	}

	protected static function deleteOperations($operationids){
		DB::delete('operations', array('operationid' => $operationids));
	}


	public static function delete($actionids){
		$actionids = zbx_toArray($actionids);
		try{
			self::BeginTransaction(__METHOD__);

			if(empty($actionids)) self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter'));

			$options = array(
				'actionids' => $actionids,
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
			);
			$delActions = self::get($options);
			foreach($actionids as $actionid){
				if(!isset($delActions[$actionid])){
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

			if((isset($operation['esc_step_from']) || isset($operation['esc_step_to']))
					&& !isset($operation['esc_step_from'], $operation['esc_step_to'])
			){
				self::exception(ZBX_API_ERROR_PARAMETERS, _('esc_step_from and esc_step_to must be set together.'));
			}

			if(isset($operation['esc_step_from'], $operation['esc_step_to'])){
				if(($operation['esc_step_from'] < 1) || ($operation['esc_step_to'] < 0)){
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation escalation step values.'));
				}

				if(($operation['esc_step_from'] > $operation['esc_step_to']) && ($operation['esc_step_to'] != 0)){
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation escalation step values.'));
				}
			}

			if(isset($operation['esc_period'])){
				if(isset($operation['esc_period']) && (($operation['esc_period'] != 0) && ($operation['esc_period'] < 60))){
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation escalation period.'));
				}
			}

			$hostIdsAll = $hostGroupIdsAll = $userIdsAll = $userGroupIdsAll = array();
			switch($operation['operationtype']){
				case OPERATION_TYPE_MESSAGE:
					$userids = isset($operation['opmessage_usr'])
							? zbx_objectValues($operation['opmessage_usr'], 'userid')
							: array();
					$usergroupids = isset($operation['opmessage_grp'])
							? zbx_objectValues($operation['opmessage_grp'], 'usrgrpid')
							: array();

					if(empty($userids) && empty($usergroupids))
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No recipients for operation message.'));

					$userIdsAll = array_merge($userIdsAll, $userids);
					$userGroupIdsAll = array_merge($userGroupIdsAll, $usergroupids);
					break;
				case OPERATION_TYPE_COMMAND:
					$groupids = isset($operation['opcommand_grp'])
							? zbx_objectValues($operation['opcommand_grp'], 'groupid')
							: array();

					$hostids = array();
					$without_current = true;
					if(isset($operation['opcommand_hst'])){
						foreach($operation['opcommand_hst'] as $hid){
							if($hid['hostid'] == 0){
								$without_current = false;
							}
							else{
								$hostids[$hid['hostid']] = $hid['hostid'];
							}
						}
					}

					if(empty($groupids) && empty($hostids) && $without_current)
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No targets for operation command.'));

					$hostIdsAll = array_merge($hostIdsAll, $hostids);
					$hostGroupIdsAll = array_merge($hostGroupIdsAll, $groupids);
					break;
				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					$groupids = isset($operation['opgroup'])
							? zbx_objectValues($operation['opgroup'], 'groupid')
							: array();

					if(empty($groupids))
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Operation has no group to operate.'));

					$hostGroupIdsAll = array_merge($hostGroupIdsAll, $groupids);
					break;
				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					$templateids = isset($operation['optemplate'])
							? zbx_objectValues($operation['optemplate'], 'templateid')
							: array();

					if(empty($templateids))
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Operation has no template to operate.'));

					$hostIdsAll = array_merge($hostIdsAll, $templateids);
					break;
				case OPERATION_TYPE_HOST_ADD:
				case OPERATION_TYPE_HOST_REMOVE:
				case OPERATION_TYPE_HOST_ENABLE:
				case OPERATION_TYPE_HOST_DISABLE:
					break;
				default:
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation type.'));
			}
		}

		if(!CHostGroup::isWritable($hostGroupIdsAll))
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation group. Host group does not exist or you have no access to this host group.'));
		if(!CHost::isWritable($hostIdsAll))
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation host. Host does not exist or you have no access to this host.'));
		if(!CUser::isReadable($userIdsAll))
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation user. User does not exist or you have no access to this user.'));
		if(!CUserGroup::isReadable($userGroupIdsAll))
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation user group. User group does not exist or you have no access to this user group.'));

		return true;
	}

	public static function validateConditions($conditions){
		$conditions = zbx_toArray($conditions);

		$hostGroupIdsAll = array();
		$templateIdsAll = array();
		$triggerIdsAll = array();
		$hostIdsAll = array();

		$discoveryCheckTypes = discovery_check_type2str();
		$discoveryObjectStatuses = discovery_object_status2str();

		foreach($conditions as $condition){
			switch($condition['conditiontype']){
				case CONDITION_TYPE_HOST_GROUP:
					$hostGroupIdsAll[$condition['value']] = $condition['value'];
					break;
				case CONDITION_TYPE_HOST_TEMPLATE:
					$templateIdsAll[$condition['value']] = $condition['value'];
					break;
				case CONDITION_TYPE_TRIGGER:
					$triggerIdsAll[$condition['value']] = $condition['value'];
					break;
				case CONDITION_TYPE_HOST:
					$hostIdsAll[$condition['value']] = $condition['value'];
					break;
				case CONDITION_TYPE_TIME_PERIOD:
					if(!validate_period($condition['value'])){
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect action condition period "%s".', $condition['value']));
					}
					break;
				case CONDITION_TYPE_DHOST_IP:
					if(!validate_ip_range($condition['value']) ){
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect action condition ip "%s".', $condition['value']));
					}
					break;
				case CONDITION_TYPE_DSERVICE_TYPE:
					if(!isset($discoveryCheckTypes[$condition['value']])){
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition discovery check.'));
					}
					break;
				case CONDITION_TYPE_DSERVICE_PORT:
					if(!validate_port_list($condition['value'])){
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect action condition port "%s".', $condition['value']));
					}
					break;
				case CONDITION_TYPE_DSTATUS:
					if(!isset($discoveryObjectStatuses[$condition['value']])){
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition discovery status.'));
					}
					break;
				case CONDITION_TYPE_TRIGGER_NAME:
				case CONDITION_TYPE_TRIGGER_VALUE:
				case CONDITION_TYPE_TRIGGER_SEVERITY:
				case CONDITION_TYPE_MAINTENANCE:
				case CONDITION_TYPE_NODE:
				case CONDITION_TYPE_DRULE:
				case CONDITION_TYPE_DCHECK:
				case CONDITION_TYPE_DOBJECT:
				case CONDITION_TYPE_PROXY:
				case CONDITION_TYPE_DUPTIME:
				case CONDITION_TYPE_DVALUE:
				case CONDITION_TYPE_APPLICATION:
				case CONDITION_TYPE_HOST_NAME:
					break;
				default:
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition type'));
			}
		}

		if(!CHostGroup::isWritable($hostGroupIdsAll))
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition host group. Host group does not exist or you have no access to this host group.'));
		if(!CHost::isWritable($hostIdsAll))
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition host. Host does not exist or you have no access to this host.'));
		if(!CTemplate::isWritable($templateIdsAll))
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition template. Template does not exist or you have no access to this template.'));
		if(!CTrigger::isWritable($triggerIdsAll))
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action condition trigger. Trigger does not exist or you have no access to this trigger.'));

		return true;
	}

	public static function validateOperationConditions($conditions){
		$conditions = zbx_toArray($conditions);

		$ackStatuses = array(
			EVENT_ACKNOWLEDGED => 1,
			EVENT_NOT_ACKNOWLEDGED => 1
		);

		foreach($conditions as $condition){
			switch($condition['conditiontype']){
				case CONDITION_TYPE_EVENT_ACKNOWLEDGED:
					if(!isset($ackStatuses[$condition['value']])){
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation condition acknowledge type.'));
					}
					break;
				default:
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect action operation condition type'));
			}
		}

		return true;
	}

}
?>
