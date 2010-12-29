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
 * File containing CEvent class for API.
 * @package API
 */
/**
 * Class containing methods for operations with events
 *
 */
class CEvent extends CZBXAPI{
/**
 * Get events data
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
 * @param array $options['eventids']
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
		$nodeCheck = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('eventid', 'object', 'clock'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('events' => array('e.eventid')),
			'from' => array('events' => 'events e'),
			'where' => array(),
			'order' => array(),
			'group' => array(),
			'limit' => null
		);

		$def_options = array(
			'nodeids'				=> null,
			'groupids'				=> null,
			'hostids'				=> null,
			'triggerids'			=> null,
			'eventids'				=> null,
			'editable'				=> null,
			'object'				=> null,
			'source'				=> null,
			'acknowledged'			=> null,
			'nopermissions'			=> null,
// filter
			'showUnknown'			=> null,
			'value'					=> null,
			'time_from'				=> null,
			'time_till'				=> null,
			'eventid_from'			=> null,
			'eventid_till'			=> null,
// filter
			'filter'					=> null,
			'search'					=> null,
						'filterOperator'	=> API_FILTER_OPERATOR,
			'startSearch'				=> null,
			'excludeSearch'				=> null,

// OutPut
			'output'				=> API_OUTPUT_REFER,
			'extendoutput'			=> null,
			'selectHosts'			=> null,
			'selectItems'			=> null,
			'select_triggers'		=> null,
			'select_alerts'			=> null,
			'select_acknowledges'	=> null,
			'countOutput'			=> null,
			'groupCount'			=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['selectHosts'])){
				$options['selectHosts'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_triggers'])){
				$options['select_triggers'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['selectItems'])){
				$options['selectItems'] = API_OUTPUT_EXTEND;
			}
		}

// editable + PERMISSION CHECK
		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			if(is_null($options['source']) && is_null($options['object'])){
				$options['object'] = EVENT_OBJECT_TRIGGER;
			}

			if(($options['object'] == EVENT_OBJECT_TRIGGER) || ($options['source'] == EVENT_SOURCE_TRIGGER)){
				if(!is_null($options['triggerids'])){
					$triggerOptions = array(
						'triggerids' => $options['triggerids'],
						'editable' => $options['editable']
					);

					$triggers = CTrigger::get($triggerOptions);
					$options['triggerids'] = zbx_objectValues($triggers, 'triggerid');
				}
				else{
					$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

					$sql_parts['from']['functions'] = 'functions f';
					$sql_parts['from']['items'] = 'items i';
					$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
					$sql_parts['from']['rights'] = 'rights r';
					$sql_parts['from']['users_groups'] = 'users_groups ug';
					$sql_parts['where']['e'] = 'e.object='.EVENT_OBJECT_TRIGGER;
					$sql_parts['where']['fe'] = 'f.triggerid=e.objectid';
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
			}
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// eventids
		if(!is_null($options['eventids'])){
			zbx_value2array($options['eventids']);

			$sql_parts['where'][] = DBcondition('e.eventid', $options['eventids']);

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('e.objectid', $nodeids);
			}
		}

// triggerids
		if(!is_null($options['triggerids']) && ($options['object'] == EVENT_OBJECT_TRIGGER)){
			zbx_value2array($options['triggerids']);
			$sql_parts['where'][] = DBcondition('e.objectid', $options['triggerids']);

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('e.objectid', $nodeids);
			}
		}

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}

			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where']['hg'] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where']['fe'] = 'f.triggerid=e.objectid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
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
			$sql_parts['where']['ft'] = 'f.triggerid=e.objectid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
		}

// node check !!!!!
// should last, after all ****IDS checks
		if(!$nodeCheck){
			$nodeCheck = true;
			$sql_parts['where'][] = DBin_node('e.eventid', $nodeids);
		}

// object
		if(!is_null($options['object'])){
			$sql_parts['where']['o'] = 'e.object='.$options['object'];
		}

// source
		if(!is_null($options['source'])){
			$sql_parts['where'][] = 'e.source='.$options['source'];
		}
// acknowledged
		if(!is_null($options['acknowledged'])){
			$sql_parts['where'][] = 'e.acknowledged='.($options['acknowledged']?1:0);
		}
// showUnknown
		if(!is_null($options['showUnknown'])){
			if(is_null($options['filter'])) $options['filter'] = array();
			$options['filter']['value_changed'] = null;
		}
// time_from
		if(!is_null($options['time_from'])){
			$sql_parts['where'][] = 'e.clock>='.$options['time_from'];
		}
// time_till
		if(!is_null($options['time_till'])){
			$sql_parts['where'][] = 'e.clock<='.$options['time_till'];
		}
// eventid_from
		if(!is_null($options['eventid_from'])){
			$sql_parts['where'][] = 'e.eventid>='.$options['eventid_from'];
		}
// eventid_till
		if(!is_null($options['eventid_till'])){
			$sql_parts['where'][] = 'e.eventid<='.$options['eventid_till'];
		}
// value
		if(!is_null($options['value'])){
			zbx_value2array($options['value']);

			$sql_parts['where'][] = DBcondition('e.value', $options['value']);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('events e', $options, $sql_parts);
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('events e', $options, $sql_parts);
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['events'] = array('e.*');
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array(
				'events' => array('COUNT(DISTINCT e.eventid) as rowscount')
			);
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			if($options['sortfield'] == 'clock'){
				$sql_parts['order'][] = 'e.clock '.$sortorder;
				$sql_parts['order'][] = 'e.ns '.$sortorder;

				if(!str_in_array('e.*', $sql_parts['select']['events'])){
					$sql_parts['select']['events'][] = 'e.clock';
					$sql_parts['select']['events'][] = 'e.ns';
				}

				if(!is_null($options['triggerids'])){
					$sql_parts['where']['o'] = '(e.object-0)='.EVENT_OBJECT_TRIGGER;
				}
			}

			if($options['sortfield'] == 'object'){
				$sql_parts['order'][] = 'e.object '.$sortorder;
				$sql_parts['order'][] = 'e.objectid '.$sortorder;
				$sql_parts['order'][] = 'e.clock '.$sortorder;
				$sql_parts['order'][] = 'e.ns '.$sortorder;

				if(!str_in_array('e.*', $sql_parts['select']['events'])){
					$sql_parts['select']['events'][] = 'e.object';
					$sql_parts['select']['events'][] = 'e.objectid';
					$sql_parts['select']['events'][] = 'e.clock';
					$sql_parts['select']['events'][] = 'e.ns';
				}
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}

// select_********
		if(($options['output'] != API_OUTPUT_EXTEND) && (!is_null($options['selectHosts']) || !is_null($options['select_triggers']) || !is_null($options['selectItems'])))
		{
			$sql_parts['select']['events'][] = 'e.object';
			$sql_parts['select']['events'][] = 'e.objectid';
		}
//---------------

		$eventids = array();
		$triggerids = array();

// Event fields
		$sql_parts['select']['events'] = implode(',', array_unique($sql_parts['select']['events']));

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
		if(!empty($sql_parts['where']))		$sql_where.= implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.$sql_where.
				$sql_order;
		$db_res = DBselect($sql, $sql_limit);
//SDI($sql);
		while($event = DBfetch($db_res)){
			if($options['countOutput']){
				$result = $event['rowscount'];
			}
			else{
				$eventids[$event['eventid']] = $event['eventid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$event['eventid']] = array('eventid' => $event['eventid']);
				}
				else{
					if(isset($event['object']) && ($event['object'] == EVENT_OBJECT_TRIGGER)){
						$triggerids[$event['objectid']] = $event['objectid'];
					}

					if(!isset($result[$event['eventid']])) $result[$event['eventid']]= array();

					if(!is_null($options['selectHosts']) && !isset($result[$event['eventid']]['hosts'])){
						$result[$event['eventid']]['hosts'] = array();
					}

					if(!is_null($options['select_triggers']) && !isset($result[$event['eventid']]['triggers'])){
						$result[$event['eventid']]['triggers'] = array();
					}

					if(!is_null($options['selectItems']) && !isset($result[$event['eventid']]['items'])){
						$result[$event['eventid']]['items'] = array();
					}

					if(!is_null($options['select_alerts']) && !isset($result[$event['eventid']]['alerts'])){
						$result[$event['eventid']]['alerts'] = array();
					}

					if(!is_null($options['select_acknowledges']) && !isset($result[$event['eventid']]['acknowledges'])){
						$result[$event['eventid']]['acknowledges'] = array();
					}

// hostids
					if(isset($event['hostid']) && is_null($options['selectHosts'])){
						if(!isset($result[$event['eventid']]['hosts'])) $result[$event['eventid']]['hosts'] = array();

						$result[$event['eventid']]['hosts'][] = array('hostid' => $event['hostid']);
						unset($event['hostid']);
					}

// triggerids
					if(isset($event['triggerid']) && is_null($options['select_triggers'])){
						if(!isset($result[$event['eventid']]['triggers'])) $result[$event['eventid']]['triggers'] = array();

						$result[$event['eventid']]['triggers'][] = array('triggerid' => $event['triggerid']);
						unset($event['triggerid']);
					}

// itemids
					if(isset($event['itemid']) && is_null($options['selectItems'])){
						if(!isset($result[$event['eventid']]['items'])) $result[$event['eventid']]['items'] = array();

						$result[$event['eventid']]['items'][] = array('itemid' => $event['itemid']);
						unset($event['itemid']);
					}

					$result[$event['eventid']] += $event;
				}
			}
		}


Copt::memoryPick();
		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
// Adding hosts
		if(!is_null($options['selectHosts']) && str_in_array($options['selectHosts'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['selectHosts'],
				'triggerids' => $triggerids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$hosts = CHost::get($obj_params);

			$triggers = array();
			foreach($hosts as $hostid => $host){
				$htriggers = $host['triggers'];
				unset($host['triggers']);
				foreach($htriggers as $tnum => $trigger){
					$triggerid = $trigger['triggerid'];
					if(!isset($triggers[$triggerid])) $triggers[$triggerid] = array('hosts' => array());

					$triggers[$triggerid]['hosts'][] = $host;
				}
			}

			foreach($result as $eventid => $event){
				if(isset($triggers[$event['objectid']])){
					$result[$eventid]['hosts'] = $triggers[$event['objectid']]['hosts'];
				}
				else{
					$result[$eventid]['hosts'] = array();
				}
			}
		}

// Adding triggers
		if(!is_null($options['select_triggers']) && str_in_array($options['select_triggers'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_triggers'],
				'triggerids' => $triggerids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$triggers = CTrigger::get($obj_params);
			foreach($result as $eventid => $event){
				if(isset($triggers[$event['objectid']])){
					$result[$eventid]['triggers'][] = $triggers[$event['objectid']];
				}
				else{
					$result[$eventid]['triggers'] = array();
				}
			}
		}

// Adding items
		if(!is_null($options['selectItems']) && str_in_array($options['selectItems'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['selectItems'],
				'triggerids' => $triggerids,
				'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)),
				'webitems' => 1,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$db_items = CItem::get($obj_params);
			$items = array();

			foreach($db_items as $itemid => $item){
				$itriggers = $item['triggers'];
				unset($item['triggers']);
				foreach($itriggers as $trigger){
					if(!isset($items[$trigger['triggerid']])) $items[$trigger['triggerid']] = array();

					$items[$trigger['triggerid']][] = $item;
				}
			}

			foreach($result as $eventid => $event){
				if(isset($items[$event['objectid']])){
					$result[$eventid]['items'] = $items[$event['objectid']];
				}
				else{
					$result[$eventid]['items'] = array();
				}
			}
		}

// Adding alerts
		if(!is_null($options['select_alerts']) && str_in_array($options['select_alerts'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['select_alerts'],
				'select_mediatypes' => API_OUTPUT_EXTEND,
				'nodeids' => $nodeids,
				'eventids' => $eventids,
				'nopermissions' => 1,
				'preservekeys' => 1,
				'sortfield' => 'clock',
				'sortorder' => ZBX_SORT_DOWN
			);
			$dbAlerts = CAlert::get($obj_params);
			foreach($dbAlerts as $anum => $alert){
				$result[$alert['eventid']]['alerts'][] = $alert;
			}
		}

// adding acknowledges
		if(!is_null($options['select_acknowledges'])){
			if(is_array($options['select_acknowledges']) || str_in_array($options['select_acknowledges'], $subselects_allowed_outputs)){
				$sql = 'SELECT a.*, u.alias '.
					' FROM acknowledges a '.
						' LEFT JOIN users u ON u.userid=a.userid '.
					' WHERE '.DBcondition('a.eventid', $eventids).
					' ORDER BY a.clock DESC';
				$res = DBselect($sql);
				while($ack = DBfetch($res)){
					$result[$ack['eventid']]['acknowledges'][] = $ack;
				}
			}
			else if(API_OUTPUT_COUNT == $options['select_acknowledges']){
				$sql = 'SELECT COUNT(a.acknowledgeid) as rowscount, a.eventid '.
					' FROM acknowledges a '.
					' WHERE '.DBcondition('a.eventid', $eventids).
					' GROUP BY a.eventid';
				$res = DBselect($sql);
				while($ack = DBfetch($res)){
					$result[$ack['eventid']]['acknowledges'] = $ack['rowscount'];
				}
			}
		}

// removing keys (hash -> array)

		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}


	return $result;
	}

/**
 * Add events ( without alerts )
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $events multidimensional array with events data
 * @param array $events[0,...]['source']
 * @param array $events[0,...]['object']
 * @param array $events[0,...]['objectid']
 * @param array $events[0,...]['clock'] OPTIONAL
 * @param array $events[0,...]['value'] OPTIONAL
 * @param array $events[0,...]['acknowledged'] OPTIONAL
 * @return boolean
 */
	public static function create($events){
		$events = zbx_toArray($events);
		$eventids = array();

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'triggerids' => zbx_objectValues($events, 'objectid'),
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1
			);
			$triggers = CTrigger::get($options);

			foreach($events as $num => $event){
				if($event['object'] != EVENT_OBJECT_TRIGGER) continue;

				if(isset($triggers[$event['objectid']])){
					$trigger = $triggers[$event['objectid']];

					if(($event['value'] != $trigger['value']) || (($event['value'] == TRIGGER_VALUE_TRUE) && ($trigger['type'] == TRIGGER_MULT_EVENT_ENABLED))){
						continue;
					}
				}

				unset($events[$num]);
			}

			foreach($events as $num => $event){
				$event_db_fields = array(
					'source'		=> null,
					'object'		=> null,
					'objectid'		=> null,
					'clock'			=> time(),
					'value'			=> 0,
					'acknowledged'	=> 0
				);

				if(!check_db_fields($event_db_fields, $event)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for Event');
				}

				$eventid = get_dbid('events','eventid');
				$sql = 'INSERT INTO events (eventid, source, object, objectid, clock, value, acknowledged) '.
						' VALUES ('.$eventid.','.
									$event['source'].','.
									$event['object'].','.
									$event['objectid'].','.
									$event['clock'].','.
									$event['value'].','.
									$event['acknowledged'].
								')';
				if(!DBexecute($sql))
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

//			$triggers[] = array('triggerid' => $event['objectid'], 'value'=> $event['value'], 'lastchange'=> $event['clock']);

				$eventids[$eventid] = $eventid;
			}

// This will create looping (Trigger->Event->Trigger->Event)
//			$result = CTrigger::update($triggers);

			self::EndTransaction(true, __METHOD__);
			return $eventids;
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
 * Delete events by eventids
 *
 * @param array $eventids
 * @param array $eventids['eventids']
 * @return boolean
 */
	public static function delete($eventids){
		$eventids = zbx_toArray($eventids);

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'eventids' => $eventids,
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1
			);
			$del_events = self::get($options);
			foreach($eventids as $enum => $eventid){
				if(!isset($del_events[$eventid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			$result = DBexecute('DELETE FROM events WHERE '.DBcondition('eventid', $eventids));
			$result &= DBexecute('DELETE FROM alerts WHERE '.DBcondition('eventid', $eventids));

			if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'Can not delete event');

			self::EndTransaction(true, __METHOD__);
			return array('eventids' => $eventids);
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
	 * Delete events by triggerids
	 *
	 * @param array $triggerids
	 * @return boolean
	 */
	public static function deleteByTriggerIDs($triggerids){
		zbx_value2array($triggerids);

		try{
			$sql = 'DELETE FROM events e WHERE e.object='.EVENT_OBJECT_TRIGGER.' AND '.DBcondition('e.objectid', $triggerids);
			if(!DBexecute($sql))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

	public static function acknowledge($data){
		global $USER_DETAILS;

		$eventids = isset($data['eventids']) ? zbx_toArray($data['eventids']) : array();
		$eventids = zbx_toHash($eventids);

		try{
			self::BeginTransaction(__METHOD__);

// PERMISSIONS {{{
			$options = array(
				'eventids' => $eventids,
				'output' => API_OUTPUT_EXTEND,
				'select_triggers' => API_OUTPUT_EXTEND,
				'preservekeys' => 1
			);
			$allowedEvents = self::get($options);
			foreach($eventids as $num => $eventid){
				if(!isset($allowedEvents[$eventid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
				}
			}
// }}} PERMISSIONS

			foreach($allowedEvents as $aenum => $event){
				$trig = reset($event['triggers']);
				if(!(($trig['type'] == TRIGGER_MULT_EVENT_ENABLED) && ($event['value'] == TRIGGER_VALUE_TRUE))){

					$val = ($event['value'] == TRIGGER_VALUE_TRUE ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE);

					$sql = ' SELECT eventid, object, objectid '.
						' FROM events '.
						' WHERE eventid < '.$event['eventid'].
						' AND objectid = '.$event['objectid'].
						' AND value = '.$val.
						' AND object = '.EVENT_OBJECT_TRIGGER.
						' ORDER BY object desc, objectid desc, eventid DESC';
					$first = DBfetch(DBselect($sql, 1));
					$first_sql = $first ? ' AND e.eventid > '.$first['eventid'] : '';


					$sql = ' SELECT eventid, object, objectid'.
						' FROM events'.
						' WHERE eventid > '.$event['eventid'].
						' AND objectid = '.$event['objectid'].
						' AND value = '.$val.
						' AND object = '.EVENT_OBJECT_TRIGGER.
						' ORDER BY object ASC, objectid ASC, eventid ASC';
					$last = DBfetch(DBselect($sql, 1));
					$last_sql = $last ? ' AND e.eventid < '.$last['eventid'] : '';


					$sql = 'SELECT e.eventid'.
						' FROM events e'.
						' WHERE e.objectid = '.$event['objectid'].
							' AND e.value = '. ($val ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE).
							$first_sql.
							$last_sql;

					$db_events = DBselect($sql);
					while($eventid = DBfetch($db_events)){
						$eventids[$eventid['eventid']] = $eventid['eventid'];
					}
				}
			}

			$sql = 'UPDATE events SET acknowledged=1 WHERE '.DBcondition('eventid', $eventids);
			if(!DBexecute($sql)) self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

			$time = time();
			$dataInsert = array();
			foreach($eventids as $enum => $eventid){
				$dataInsert[] = array(
					'userid' => $USER_DETAILS['userid'],
					'eventid' => $eventid,
					'clock' => $time,
					'message'=> $data['message']
				);
			}

			DB::insert('acknowledges', $dataInsert);

			self::EndTransaction(true, __METHOD__);

			return array('eventids' => $eventids);
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
