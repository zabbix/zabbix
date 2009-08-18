<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
class CAction {

	public static $error = array();

	/**
	 * Get Actions data
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['actionids']
	 * @param array $options['applicationids']
	 * @param array $options['status']
	 * @param array $options['templated_items']
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


		$sql_parts = array(
			'select' => array('actions' => 'a.actionid'),
			'from' => array('a' => 'actions a'),
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

// filter
			'eventsource'			=> null,
			'evaltype'				=> null,
			'status'				=> null,
			'esc_period'			=> null,
			'recovery_msg'			=> null,
			'pattern'				=> '',

// OutPut
			'extendoutput'			=> null,
			'select_conditions'		=> null,
			'select_operations'		=> null,
			'count'					=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = array_merge($def_options, $options);


// editable + PERMISSION CHECK
		if(defined('ZBX_API_REQUEST')){
			$options['nopermissions'] = null;
		}

		if((USER_TYPE_SUPER_ADMIN == $user_type) || !is_null($options['nopermissions'])){
		}
		else{
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

			$sql_parts['from']['c'] = 'conditions c';
			$sql_parts['from']['f'] = 'functions f';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['r'] = 'rights r';
			$sql_parts['from']['ug'] = 'users_groups ug';

			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;


// condition hostgroup
			$sql_parts['where'][] = '((c.conditiontype='.CONDITION_TYPE_HOST_GROUP.
									' AND hg.groupid=c.value '.
									' AND NOT EXISTS( '.
										' SELECT hgg.groupid '.
											' FROM hosts_groups hgg, rights rr, users_groups ugg '.
											' WHERE hgg.groupid=hg.groupid '.
												' AND rr.id=hgg.groupid '.
												' AND rr.groupid=ugg.usrgrpid '.
												' AND ugg.userid='.$userid.
												' AND rr.permission<'.$permission.'))'.
// condition host or template
								' OR '.
								'((c.conditiontype='.CONDITION_TYPE_HOST.' OR c.conditiontype='.CONDITION_TYPE_HOST_TEMPLATE.') '.
									' AND hg.hostid=c.value '.
									' AND NOT EXISTS( '.
										' SELECT hgg.groupid '.
										' FROM hosts_groups hgg, rights rr, users_groups gg '.
										' WHERE hgg.hostid=hg.hostid '.
											' AND rr.id=hgg.groupid '.
											' AND rr.groupid=gg.usrgrpid '.
											' AND gg.userid='.$userid.
											' AND rr.permission<'.$permission.'))'.

// condition trigger
								' OR '.
								'( c.conditiontype='.CONDITION_TYPE_TRIGGER.
									' AND f.triggerid=c.value '.
									' AND NOT EXISTS( '.
										' SELECT ff.triggerid '.
										' FROM functions ff, items ii '.
										' WHERE ff.triggerid=f.triggerid '.
											' AND ff.itemid=ii.itemid '.
											' AND EXISTS( '.
												' SELECT hgg.groupid '.
												' FROM hosts_groups hgg, rights rr, users_groups gg '.
												' WHERE hgg.hostid=ii.hostid '.
													' AND rr.id=hgg.groupid '.
													' AND rr.groupid=gg.usrgrpid '.
													' AND gg.userid='.$userid.
													' AND rr.permission<'.$permission.'))))';
		}

// nodeids
		$nodeids = $options['nodeids'] ? $options['nodeids'] : get_current_nodeid(false);

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}

			$sql_parts['from']['f'] = 'functions f';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['from']['hg'] = 'hosts_groups hg';

			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where']['e'] = 'e.object='.EVENT_OBJECT_TRIGGER;
			$sql_parts['where']['ef'] = 'e.objectid=f.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
			$sql_parts['where']['hg'] = DBcondition('hg.groupid', $options['groupids']);
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['hostid'] = 'i.hostid';
			}

			$sql_parts['from']['f'] = 'functions f';
			$sql_parts['from']['i'] = 'items i';

			$sql_parts['where']['i'] = DBcondition('i.hostid', $options['hostids']);
			$sql_parts['where']['e'] = 'e.object='.EVENT_OBJECT_TRIGGER;
			$sql_parts['where']['ef'] = 'e.objectid=f.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
		}

// actionids
		if(!is_null($options['actionids'])){
			zbx_value2array($options['actionids']);

			$sql_parts['where'][] = DBcondition('a.actionid', $options['actionids']);
		}

// triggerids
		if(!is_null($options['triggerids'])){
			zbx_value2array($options['triggerids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['actionid'] = 'a.actionid';
			}

			$sql_parts['where']['ae'] = 'a.eventid=e.eventid';
			$sql_parts['where']['e'] = 'e.object='.EVENT_OBJECT_TRIGGER;
			$sql_parts['where'][] = DBcondition('e.objectid', $options['triggerids']);
		}

// actionids
		if(!is_null($options['actionids'])){
			zbx_value2array($options['actionids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['actionid'] = 'a.actionid';
			}

			$sql_parts['where'][] = DBcondition('a.actionid', $options['actionids']);
		}

// userids
		if(!is_null($options['userids'])){
			zbx_value2array($options['userids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['userid'] = 'a.userid';
			}

			$sql_parts['where'][] = DBcondition('a.userid', $options['userids']);
		}

// mediatypeids
		if(!is_null($options['mediatypeids'])){
			zbx_value2array($options['mediatypeids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['mediatypeid'] = 'a.mediatypeid';
			}

			$sql_parts['where'][] = DBcondition('a.mediatypeid', $options['mediatypeids']);
		}

// eventsource
		if(!is_null($options['eventsource'])){
			$sql_parts['where'][] = 'a.eventsource='.$options['eventsource'];
		}

// evaltype
		if(!is_null($options['evaltype'])){
			$sql_parts['where'][] = 'a.evaltype='.$options['evaltype'];
		}

// status
		if(!is_null($options['status'])){
			$sql_parts['where'][] = 'a.status='.$options['status'];
		}

// esc_period
		if(!is_null($options['esc_period'])){
			$sql_parts['where'][] = 'a.esc_period>'.$options['esc_period'];
		}

// recovery_msg
		if(!is_null($options['recovery_msg'])){
			$sql_parts['where'][] = 'a.recovery_msg<'.$options['recovery_msg'];
		}

// extendoutput
		if(!is_null($options['extendoutput'])){
			$sql_parts['select']['actions'] = 'a.*';
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('COUNT(DISTINCT a.actionid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(a.name) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
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
		$userids = array();
		$mediatypeids = array();

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
		$db_res = DBselect($sql, $sql_limit);
		while($action = DBfetch($db_res)){
			if($options['count']){
				$result = $action;
			}
			else{
				$actionids[$action['actionid']] = $action['actionid'];

				if(is_null($options['extendoutput'])){
					$result[$action['actionid']] = $action['actionid'];
				}
				else{
					if(!isset($result[$action['actionid']])) $result[$action['actionid']]= array();

					$result[$action['actionid']] += $action;
				}
			}
		}

		if(is_null($options['extendoutput']) || !is_null($options['count'])) return $result;

// Adding Objects
// Adding Conditions
		if($options['select_conditions']){
			$sql = 'SELECT c.* FROM conditions c WHERE '.DBcondition('c.actionid', $actionids);
			$res = DBselect($sql);
			while($condition = DBfetch($res)){
				$result[$condition['actionid']]['conditionids'][$condition['conditionid']] = $condition['conditionid'];
				$result[$condition['actionid']]['conditions'][$condition['conditionid']] = $condition;
			}
		}

// Adding Operations
		if($options['select_operations']){
			$operations = array();
			$operationids = array();
			$sql = 'SELECT o.* '.
					' FROM operations o '.
					' WHERE '.DBcondition('o.actionid', $actionids);
			$res = DBselect($sql);
			while($operation = DBfetch($res)){
				$operation['opconditions'] = array();
				$operation['opconditionids'] = array();

				$operations[$operation['operationid']] = $operation;
				$operationids[$operation['operationid']] = $operation['operationid'];
			}

			$sql = 'SELECT op.* FROM opconditions op WHERE '.DBcondition('op.operationid', $operationids);
			$res = DBselect($sql);
			while($opcondition = DBfetch($res)){
				$operations[$opcondition['operationid']]['opconditions'][$opcondition['opconditionid']] = $opcondition;
				$operationids[$opcondition['operationid']]['opconditionids'][$opcondition['opconditionid']] = $opcondition['opconditionid'];
			}

			foreach($operations as $operationd => $operation){
				$result[$operation['actionid']]['operationids'][$operation['operationid']] = $operation['operationid'];
				$result[$operation['actionid']]['operations'][$operation['operationid']] = $operation;
			}
		}

	return $result;
	}

/**
 * Add actions
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
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
	public static function add($actions){

		$actionids = array();
		DBstart(false);

		$result = false;
		foreach($actions as $num => $action){
			$action_db_fields = array(
				'actionid'		=> null,
				'eventid'		=> null,
				'userid'		=> null,
				'clock'			=> time(),
				'mediatypeid'	=> 0,
				'sendto'		=> null,
				'subject'		=> '',
				'message'		=> '',
				'status'		=> ALERT_STATUS_NOT_SENT,
				'retries'		=> 0,
				'error'			=> '',
				'nextcheck'		=> null,
				'esc_step'		=> 0,
				'actiontype'		=> ALERT_TYPE_MESSAGE
			);

			if(!check_db_fields($action_db_fields, $action)){
				$result = false;
				break;
			}

			$actionid = get_dbid('actions', 'actionid');
			$sql = 'INSERT INTO actions '.
					'(actionid, actionid, eventid, userid, mediatypeid, clock, sendto, subject, message, status, retries, error, nextcheck, esc_step, actiontype) '.
					' VALUES ('.$actionid.','.$action['actionid'].','.$action['eventid'].','.$action['userid'].','.$action['mediatypeid'].','.
								$action['clock'].','.zbx_dbstr($action['sentto']).','.zbx_dbstr($action['subject']).','.zbx_dbstr($action['message']).','.
								$action['status'].','.$action['retries'].','.zbx_dbstr($action['error']).','.$action['nextcheck'].','.
								$action['esc_step'].','.$action['actiontype'].' )';
			$result = DBexecute($sql);
			if(!$result) break;
			$actionids[$actionid] = $actionid;
		}

		$result = DBend($result);
		if($result)
			return $actionids;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

/**
 * Delete actions
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $actionids
 * @return boolean
 */
	public static function delete($actionids){
		zbx_value2array($actionids);

		$sql = 'DELETE FROM actions WHERE '.DBcondition('actionid', $actionids);
		$result = DBexecute($sql);
		if($result)
			return $result;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
}
?>
