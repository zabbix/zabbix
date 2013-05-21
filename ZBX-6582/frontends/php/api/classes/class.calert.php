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
 * File containing CAlert class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Alerts
 *
 */
class CAlert extends CZBXAPI{
/**
 * Get Alerts data
 *
 * @param _array $options
 * @param array $options['itemids']
 * @param array $options['hostids']
 * @param array $options['groupids']
 * @param array $options['alertids']
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

		$sort_columns = array('alertid','clock','eventid','status'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('alerts' => 'a.alertid'),
			'from' => array('alerts' => 'alerts a'),
			'where' => array(),
			'order' => array(),
			'limit' => null,
		);

		$def_options = array(
			'nodeids'				=> null,
			'groupids'				=> null,
			'hostids'				=> null,
			'alertids'				=> null,
			'triggerids'			=> null,
			'eventids'				=> null,
			'actionids'				=> null,
			'mediatypeids'			=> null,
			'userids'				=> null,
			'nopermissions'			=> null,

// filter
			'filter'				=> null,
			'search'				=> null,
			'startSearch'			=> null,
			'excludeSearch'			=> null,
			'time_from'				=> null,
			'time_till'				=> null,
			'searchWildcardsEnabled'=> null,

// OutPut
			'extendoutput'			=> null,
			'output'				=> API_OUTPUT_REFER,
			'select_mediatypes'		=> null,
			'select_users'			=> null,
			'select_hosts'			=> null,
			'countOutput'			=> null,
			'preservekeys'			=> null,
			'editable'				=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_mediatypes'])){
				$options['select_mediatypes'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_users'])){
				$options['select_users'] = API_OUTPUT_EXTEND;
			}
		}


		if(is_array($options['output'])){
			unset($sql_parts['select']['alerts']);
			$sql_parts['select']['alertid'] = ' a.alertid';
			foreach($options['output'] as $key => $field){
				$sql_parts['select'][$field] = ' a.'.$field;
			}

			$options['output'] = API_OUTPUT_CUSTOM;
		}


// editable + PERMISSION CHECK

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

			$sql_parts['from']['events'] = 'events e';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['from']['functions'] = 'functions f';

			$sql_parts['where']['ae'] = 'a.eventid=e.eventid';
			$sql_parts['where']['e'] = 'e.object='.EVENT_OBJECT_TRIGGER;
			$sql_parts['where']['ef'] = 'e.objectid=f.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS( '.
											' SELECT ff.triggerid '.
											' FROM functions ff, items ii '.
											' WHERE ff.triggerid=e.objectid '.
												' AND ff.itemid=ii.itemid '.
												' AND EXISTS( '.
													' SELECT hgg.groupid '.
													' FROM hosts_groups hgg, rights rr, users_groups gg '.
													' WHERE hgg.hostid=ii.hostid '.
														' AND rr.id=hgg.groupid '.
														' AND rr.groupid=gg.usrgrpid '.
														' AND gg.userid='.$userid.
														' AND rr.permission<'.$permission.'))';
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}

			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';

			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where']['e'] = 'e.object='.EVENT_OBJECT_TRIGGER;
			$sql_parts['where']['ef'] = 'e.objectid=f.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
			$sql_parts['where']['hg'] = DBcondition('hg.groupid', $options['groupids']);
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['hostid'] = 'i.hostid';
			}

			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['items'] = 'items i';

			$sql_parts['where']['i'] = DBcondition('i.hostid', $options['hostids']);
			$sql_parts['where']['e'] = 'e.object='.EVENT_OBJECT_TRIGGER;
			$sql_parts['where']['ef'] = 'e.objectid=f.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
		}

// alertids
		if(!is_null($options['alertids'])){
			zbx_value2array($options['alertids']);

			$sql_parts['where'][] = DBcondition('a.alertid', $options['alertids']);
		}

// triggerids
		if(!is_null($options['triggerids'])){
			zbx_value2array($options['triggerids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['actionid'] = 'a.actionid';
			}

			$sql_parts['where']['ae'] = 'a.eventid=e.eventid';
			$sql_parts['where']['e'] = 'e.object='.EVENT_OBJECT_TRIGGER;
			$sql_parts['where'][] = DBcondition('e.objectid', $options['triggerids']);
		}

// eventids
		if(!is_null($options['eventids'])){
			zbx_value2array($options['eventids']);

			$sql_parts['where'][] = DBcondition('a.eventid', $options['eventids']);
		}


// actionids
		if(!is_null($options['actionids'])){
			zbx_value2array($options['actionids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['actionid'] = 'a.actionid';
			}

			$sql_parts['where'][] = DBcondition('a.actionid', $options['actionids']);
		}

// userids
		if(!is_null($options['userids'])){
			zbx_value2array($options['userids']);

			// if(is_null($options['extendoutput'])){
				// $sql_parts['select']['userid'] = 'a.userid';
			// }

			$field = 'a.userid';
			if(!is_null($options['time_from']) || !is_null($options['time_till'])){
				$field = '(a.userid+0)';
			}

			$sql_parts['where'][] = DBcondition($field, $options['userids']);
		}

// mediatypeids
		if(!is_null($options['mediatypeids'])){
			zbx_value2array($options['mediatypeids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['mediatypeid'] = 'a.mediatypeid';
			}

			$sql_parts['where'][] = DBcondition('a.mediatypeid', $options['mediatypeids']);
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('alerts a', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('alerts a', $options, $sql_parts);
		}

// time_from
		if(!is_null($options['time_from'])){
			$sql_parts['where'][] = 'a.clock>'.$options['time_from'];
		}

// time_till
		if(!is_null($options['time_till'])){
			$sql_parts['where'][] = 'a.clock<'.$options['time_till'];
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['alerts'] = 'a.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('COUNT(DISTINCT a.alertid) as rowscount');
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

		$alertids = array();
		$userids = array();
		$hostids = array();
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

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('a.alertid', $nodeids).
					$sql_where.
				$sql_order;
		$db_res = DBselect($sql, $sql_limit);
		while($alert = DBfetch($db_res)){
			if($options['countOutput']){
				$result = $alert['rowscount'];
			}
			else{
				$alertids[$alert['alertid']] = $alert['alertid'];

				if(isset($alert['userid']))
					$userids[$alert['userid']] = $alert['userid'];
				if(isset($alert['hostid']))
					$hostids[$alert['hostid']] = $alert['hostid'];
				if(isset($alert['mediatypeid']))
					$mediatypeids[$alert['mediatypeid']] = $alert['mediatypeid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$alert['alertid']] = array('alertid' => $alert['alertid']);
				}
				else{
					if(!isset($result[$alert['alertid']])) $result[$alert['alertid']]= array();

					if(!is_null($options['select_mediatypes']) && !isset($result[$alert['alertid']]['mediatypes'])){
						$result[$alert['alertid']]['mediatypes'] = array();
					}

					if(!is_null($options['select_users']) && !isset($result[$alert['alertid']]['users'])){
						$result[$alert['alertid']]['users'] = array();
					}

// hostids
					if(isset($alert['hostid']) && is_null($options['select_hosts'])){
						if(!isset($result[$alert['alertid']]['hosts']))
							$result[$alert['alertid']]['hosts'] = array();

						$result[$alert['alertid']]['hosts'][] = array('hostid' => $alert['hostid']);
//						unset($alert['hostid']);
					}
// userids
					if(isset($alert['userid']) && is_null($options['select_users'])){
						if(!isset($result[$alert['alertid']]['users']))
							$result[$alert['alertid']]['users'] = array();

						$result[$alert['alertid']]['users'][] = array('userid' => $alert['userid']);
					}
// mediatypeids
					if(isset($alert['mediatypeid']) && is_null($options['select_mediatypes'])){
						if(!isset($result[$alert['alertid']]['mediatypes']))
							$result[$alert['alertid']]['mediatypes'] = array();

						$result[$alert['alertid']]['mediatypes'][] = array('mediatypeid' => $alert['mediatypeid']);
					}

					$result[$alert['alertid']] += $alert;
				}
			}
		}

COpt::memoryPick();
		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
		$hosts = array();
		$users = array();
		$mediatypes = array();

// Adding hosts
		if(!is_null($options['select_hosts']) && str_in_array($options['select_hosts'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['select_hosts'],
				'hostids' => $hostids,
				'preservekeys' => 1
			);
			$hosts = CHost::get($obj_params);
		}

// Adding Users
		if(!is_null($options['select_users']) && str_in_array($options['select_users'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['select_users'],
				'userids' => $userids,
				'preservekeys' => 1
			);
			$users = CUser::get($obj_params);
		}

// Adding MediaTypes
		if(!is_null($options['select_mediatypes']) && str_in_array($options['select_mediatypes'], $subselects_allowed_outputs)){
			$sql = 'SELECT mt.* FROM media_type mt WHERE '.DBcondition('mt.mediatypeid', $mediatypeids);
			$res = DBselect($sql);
			while($media = DBfetch($res)){
				$mediatypes[$media['mediatypeid']] = $media;
			}
		}

		foreach($result as $alertid => $alert){
			if(isset($alert['hostid']) && isset($hosts[$alert['hostid']])){
				$result[$alertid]['hosts'][] = $hosts[$alert['hostid']];
			}

			if(isset($mediatypes[$alert['mediatypeid']])){
				$result[$alertid]['mediatypes'][] = $mediatypes[$alert['mediatypeid']];
			}

			if(isset($users[$alert['userid']])){
				$result[$alertid]['users'][] = $users[$alert['userid']];
			}
		}

COpt::memoryPick();
// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

/**
 * Add alerts
 *
 * @param _array $alerts multidimensional array with alerts data
 * @param array $alerts[0,...]['expression']
 * @param array $alerts[0,...]['description']
 * @param array $alerts[0,...]['type'] OPTIONAL
 * @param array $alerts[0,...]['priority'] OPTIONAL
 * @param array $alerts[0,...]['status'] OPTIONAL
 * @param array $alerts[0,...]['comments'] OPTIONAL
 * @param array $alerts[0,...]['url'] OPTIONAL
 * @return boolean
 */
	public static function create($alerts){
		$alerts = zbx_toArray($alerts);
		$alertids = array();
		$result = false;

		self::BeginTransaction(__METHOD__);
		foreach($alerts as $anum => $alert){
			$alert_db_fields = array(
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
				'alerttype'		=> ALERT_TYPE_MESSAGE
			);

			if(!check_db_fields($alert_db_fields, $alert)){
				$result = false;
				break;
			}

			$alertid = get_dbid('alerts', 'alertid');
			$sql = 'INSERT INTO alerts '.
					'(alertid, actionid, eventid, userid, mediatypeid, clock, sendto, subject, message, status, retries, error, nextcheck, esc_step, alerttype) '.
					' VALUES ('.$alertid.','.$alert['actionid'].','.$alert['eventid'].','.$alert['userid'].','.$alert['mediatypeid'].','.
								$alert['clock'].','.zbx_dbstr($alert['sendto']).','.zbx_dbstr($alert['subject']).','.zbx_dbstr($alert['message']).','.
								$alert['status'].','.$alert['retries'].','.zbx_dbstr($alert['error']).','.$alert['nextcheck'].','.
								$alert['esc_step'].','.$alert['alerttype'].' )';
			$result = DBexecute($sql);
			if(!$result) break;

			$alertids[] = $alertid;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return array('alertids'=>$alertids);
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal Zabbix error');
			return false;
		}
	}

/**
 * Delete alerts
 *
 * @param array $alertids
 * @return boolean
 */
	public static function delete($alertids){
		$alerts = zbx_toArray($alerts);
		$alertids = array();
		$result = false;
//------

		$options = array(
			'alertids' => zbx_objectValues($alerts, 'alertid'),
			'editable' => 1,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1
		);
		$del_alerts = self::get($options);
		foreach($alerts as $snum => $alert){
			if(!isset($del_alerts[$alert['alertid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}

			$alertids[] = $alert['alertid'];
		}

		if(!empty($alertids)){
			$sql = 'DELETE FROM alerts WHERE '.DBcondition('alertid', $alertids);
			$result = DBexecute($sql);
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, S_EMPTY_INPUT_PARAMETER.' [ alertids ]');
			$result = false;
		}

		if($result){
			return array('alertids'=>$alertids);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}
}
?>
