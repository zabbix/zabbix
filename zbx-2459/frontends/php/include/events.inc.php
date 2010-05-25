<?php
/*
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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
	function event_source2str($sourceid){
		switch($sourceid){
			case EVENT_SOURCE_TRIGGERS:	return S_TRIGGERS;
			case EVENT_SOURCE_DISCOVERY:	return S_DISCOVERY;
			default:			return S_UNKNOWN;
		}
	}

	function get_event_by_eventid($eventid){
		$db_events = DBselect('select * from events where eventid='.$eventid);
		return DBfetch($db_events);
	}

	function get_tr_event_by_eventid($eventid){
		$sql = 'SELECT e.*,t.triggerid, t.description,t.priority,t.status,t.type '.
				' FROM events e,triggers t '.
				' WHERE e.eventid='.$eventid.
					' AND e.object='.EVENT_OBJECT_TRIGGER.
					' AND t.triggerid=e.objectid';
		$result = DBfetch(DBselect($sql));
	return $result;
	}

	function get_events_unacknowledged($db_element, $value=null){
		$elements = array('hosts' => array(), 'hosts_groups' => array(), 'triggers' => array());

		get_map_elements($db_element, $elements);
		if(empty($elements['hosts_groups']) && empty($elements['hosts']) && empty($elements['triggers'])){
			return 0;
		}

		$config = select_config();
		$options = array(
			'nodeids' => get_current_nodeid(),
			'output' => API_OUTPUT_SHORTEN,
			'monitored' => 1,
			'skipDependent' => 1,
			'limit' => ($config['search_limit']+1)
		);
		if(!empty($elements['hosts_groups'])) $options['groupids'] = array_unique($elements['hosts_groups']);
		if(!empty($elements['hosts'])) $options['hostids'] = array_unique($elements['hosts']);
		if(!empty($elements['triggers'])) $options['triggerids'] = array_unique($elements['triggers']);
		$triggerids = CTrigger::get($options);

		$options = array(
			'countOutput' => 1,
			'triggerids' => zbx_objectValues($triggerids, 'triggerid'),
			'object' => EVENT_OBJECT_TRIGGER,
			'acknowledged' => 0,
			'value' => is_null($value) ? array(TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE) : $value,
			'nopermissions' => 1
		);
		$event_count = CEvent::get($options);

	return $event_count['rowscount'];
	}

/* function:
 *     event_initial_time
 *
 * description:
 *     returns 'true' if event is initial, otherwise false;
 *
 * author: Aly
 */
function event_initial_time($row,$hide_unknown=0){
	$events = get_latest_events($row,$hide_unknown);

	if(!empty($events) &&
		($events[0]['value'] == $row['value']) &&
		($row['type'] == TRIGGER_MULT_EVENT_ENABLED) &&
		($row['value'] == TRIGGER_VALUE_TRUE))
	{
		return true;
	}
	if(!empty($events) && ($events[0]['value'] == $row['value'])){
		return false;
	}

	return true;
}

/* function:
 *     first_initial_eventid
 *
 * description:
 *     return first initial eventid
 *
 * author: Aly
 */

function first_initial_eventid($row,$hide_unknown=0){

	$events = get_latest_events($row,$hide_unknown);

	$sql_cond=($hide_unknown == 1)?' AND e.value<>2 ':'';

	if(empty($events)){
		$sql = 'SELECT e.eventid '.
				' FROM events e '.
				' WHERE e.objectid='.$row['triggerid'].$sql_cond.
					' AND e.object='.EVENT_OBJECT_TRIGGER.
				' ORDER BY e.object, e.objectid, e.eventid';
		$res = DBselect($sql,1);

		if($rows = DBfetch($res)) return $rows['eventid'];
	}
	else if(!empty($events) && ($events[0]['value'] != $row['value'])){
		$eventid = $events[0]['eventid'];
		$sql = 'SELECT e.eventid '.
				' FROM events e '.
				' WHERE e.eventid > '.$eventid.
					' AND e.objectid='.$row['triggerid'].$sql_cond.
					' AND e.object='.EVENT_OBJECT_TRIGGER.
				' ORDER BY e.object, e.objectid, e.eventid';
		$res = DBselect($sql,1);

		if($rows = DBfetch($res)){
			return $rows['eventid'];
		}

		$row['eventid'] = $eventid;
		$row['value'] = $events[0]['value'];

		return first_initial_eventid($row,$hide_unknown);			// recursion!!!
	}
	else if(!empty($events) && ($events[0]['value'] == $row['value'])){
		$eventid = (count($events) > 1)?($events[1]['eventid']):(0);

		$sql = 'SELECT e.eventid,e.clock '.
				' FROM events e '.
				' WHERE e.eventid > '.$eventid.
					' AND e.objectid='.$row['triggerid'].
					' AND e.object='.EVENT_OBJECT_TRIGGER.
					' AND e.value='.$row['value'].
				' ORDER BY e.object, e.objectid, e.eventid';
		$res = DBselect($sql,1);
		$rows = DBfetch($res);

		return $rows['eventid'];
	}
return false;
}

/* function:
 *     get_latest_events
 *
 * description:
 *     return latest events by VALUE
 *
 * author: Aly
 */
function get_latest_events($row,$hide_unknown=0){

	$eventz = array();
	$events = array();

// SQL's are optimized that's why it's splited that way

/*******************************************/
// Check for optimization after changing!  */
/*******************************************/

	$sql = 'SELECT e.eventid, e.value '.
			' FROM events e '.
			' WHERE e.objectid='.$row['triggerid'].
				' AND e.eventid < '.$row['eventid'].
				' AND e.object='.EVENT_OBJECT_TRIGGER.
				' AND e.value='.TRIGGER_VALUE_FALSE.
			' ORDER BY e.object DESC, e.objectid DESC, e.eventid DESC';
	if($rez = DBfetch(DBselect($sql,1))) $eventz[$rez['value']] = $rez['eventid'];

	$sql = 'SELECT e.eventid, e.value '.
			' FROM events e'.
			' WHERE e.objectid='.$row['triggerid'].
				' AND e.eventid < '.$row['eventid'].
				' AND e.object='.EVENT_OBJECT_TRIGGER.
				' AND e.value='.TRIGGER_VALUE_TRUE.
			' ORDER BY e.object DESC, e.objectid DESC, e.eventid DESC';

	if($rez = DBfetch(DBselect($sql,1))) $eventz[$rez['value']] = $rez['eventid'];

	if($hide_unknown == 0){
		$sql = 'SELECT e.eventid, e.value '.
				' FROM events e'.
				' WHERE e.objectid='.$row['triggerid'].
					' AND e.eventid < '.$row['eventid'].
					' AND e.object='.EVENT_OBJECT_TRIGGER.
					' AND e.value='.TRIGGER_VALUE_UNKNOWN.
				' ORDER BY e.object DESC, e.objectid DESC, e.eventid DESC';
		if($rez = DBfetch(DBselect($sql,1))) $eventz[$rez['value']] = $rez['eventid'];
	}

/*******************************************/

	arsort($eventz);
	foreach($eventz as $key => $value){
		$events[] = array('eventid'=>$value,'value'=>$key);
	}
return $events;
}

/* function:
 *     get_next_event
 *
 * description:
 *     return next event by value
 *
 * author: Aly
 */
function get_next_event($event, $event_list=array()){

	if(!empty($event_list)){
		$next_event = false;
		if((TRIGGER_VALUE_TRUE == $event['value']) && (TRIGGER_MULT_EVENT_ENABLED == $event['type'])){
			foreach($event_list as $e){
				if(($e['objectid'] == $event['objectid']) && ($e['eventid'] > $event['eventid'])
						&& ($e['value'] != TRIGGER_VALUE_UNKNOWN)){
					$next_event = $e;
				}
			}
		}
		else{
			foreach($event_list as $e){
				if(($e['objectid'] == $event['objectid']) && ($e['eventid'] > $event['eventid'])
						&& ($e['value'] != TRIGGER_VALUE_UNKNOWN) && ($e['value'] != $event['value'])){
					$next_event = $e;
				}
			}
		}

		if($next_event) return $next_event;
	}


	if((TRIGGER_VALUE_TRUE == $event['value']) && (TRIGGER_MULT_EVENT_ENABLED == $event['type'])){
		$sql = 'SELECT e.eventid, e.value, e.clock '.
			' FROM events e'.
			' WHERE e.objectid='.$event['objectid'].
				' AND e.eventid > '.$event['eventid'].
				' AND e.object='.EVENT_OBJECT_TRIGGER.
				' AND e.value<>'.TRIGGER_VALUE_UNKNOWN.
			' ORDER BY e.object, e.objectid, e.eventid';
	}
	else{
		$sql = 'SELECT e.eventid, e.value, e.clock '.
			' FROM events e'.
			' WHERE e.objectid='.$event['objectid'].
				' AND e.eventid > '.$event['eventid'].
				' AND e.object='.EVENT_OBJECT_TRIGGER.
				' AND e.value<>'.$event['value'].
				' AND e.value<>'.TRIGGER_VALUE_UNKNOWN.
			' ORDER BY e.object, e.objectid, e.eventid';
	}

	return DBfetch(DBselect($sql,1));
}

// author: Aly
function make_event_details($eventid){
	$event = get_tr_event_by_eventid($eventid);

	$table = new CTableInfo();

	$table->AddRow(array(S_EVENT, expand_trigger_description($event['triggerid'])));
	$table->AddRow(array(S_TIME, zbx_date2str(S_EVENTS_EVENT_DETAILS_DATE_FORMAT,$event['clock'])));

	$duration = zbx_date2age($event['clock']);

	if($next_event = get_next_event($event)){
		$duration = zbx_date2age($event['clock'],$next_event['clock']);
	}

	if($event["value"] == TRIGGER_VALUE_FALSE){
		$value=new CCol(S_OK_BIG,"off");
	}
	elseif($event["value"] == TRIGGER_VALUE_TRUE){
		$value=new CCol(S_PROBLEM_BIG,"on");
	}
	else{
		$value=new CCol(S_UNKNOWN_BIG,"unknown");
	}


	$ack = '-';
	if($event["value"] == 1 && $event["acknowledged"] == 1){
		$db_acks = get_acknowledges_by_eventid($event["eventid"]);
		$rows=0;
		while($a=DBfetch($db_acks))	$rows++;

		$ack=array(
			new CLink(S_YES,'acknow.php?eventid='.$event['eventid'],'off'),
			SPACE.'('.$rows.')'
			);
	}

	$table->addRow(array(S_STATUS, $value));
	$table->addRow(array(S_DURATION, $duration));
	$table->addRow(array(S_ACKNOWLEDGED, $ack));

return $table;
}

function make_small_eventlist($eventid, $trigger_data){

	$table = new CTableInfo();
	$table->setHeader(array(S_TIME, S_STATUS, S_DURATION, S_AGE, S_ACK, S_ACTIONS));

	$options = array(
		'eventids' => $eventid, 
		'output' => API_OUTPUT_EXTEND, 
//		'select_triggers' => API_OUTPUT_EXTEND
	);
	$curevent = CEvent::get($options);
	$curevent = reset($curevent);
	
	$clock = $curevent['clock'];

	$options = array(
		'triggerids' => $trigger_data['triggerid'],
		'eventid_till' => $curevent['eventid'],
		'select_triggers' => API_OUTPUT_EXTEND,
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => 'eventid',
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => 20
	);
	$events = CEvent::get($options);

	foreach($events as $enum => $event){
		$trigger = reset($event['triggers']);

		$event['type'] = $trigger['type'];
		
		$lclock = $clock;
		$clock = $event['clock'];
		$duration = zbx_date2age($lclock, $clock);

		if($curevent['eventid'] == $event['eventid'] && ($nextevent = get_next_event($event))) {
			$duration = zbx_date2age($nextevent['clock'], $clock);
		}
		else if($curevent['eventid'] == $event['eventid']) {
			$duration = zbx_date2age($clock);
		}

		$value = new CCol(trigger_value2str($event['value']), get_trigger_value_style($event['value']));

		$ack = new CSpan(S_NO, 'on');
		if(1 == $event['acknowledged']){
			$db_acks = get_acknowledges_by_eventid($event['eventid']);
			$rows=0;
			while($a=DBfetch($db_acks))	$rows++;

			$ack=array(
				new CLink(new CSpan(S_YES,'off'),'acknow.php?eventid='.$event['eventid']),
				SPACE.'('.$rows.')'
			);
		}

//actions
		$actions = get_event_actions_stat_hints($event['eventid']);
//--------

		$table->addRow(array(
			new CLink(
				zbx_date2str(S_EVENTS_SMALL_EVENT_LIST_DATE_FORMAT,$event['clock']),
				'tr_events.php?triggerid='.$trigger_data['triggerid'].'&eventid='.$event['eventid'],
				'action'
			),
			$value,
			$duration,
			zbx_date2age($event['clock']),
			$ack,
			$actions
		));
	}

return $table;
}

function make_popup_eventlist($eventid, $trigger_type, $triggerid) {

	$table = new CTableInfo();
	$table->setHeader(array(S_TIME,S_STATUS,S_DURATION, S_AGE, S_ACK));
	$table->setAttribute('style', 'width: 400px;');

	$event_list = array();
	$sql = 'SELECT * '.
			' FROM events '.
			' WHERE eventid<='.$eventid.
				' AND object='.EVENT_OBJECT_TRIGGER.
				' AND objectid='.$triggerid.
			' ORDER BY eventid DESC';
	$db_events = DBselect($sql, ZBX_WIDGET_ROWS);

	$count = 0;
	while($event = DBfetch($db_events)){
		if(!empty($event_list) && ($event_list[$count]['value'] != $event['value'])) {
			$count++;
		}
		else if(!empty($event_list) &&
			($event_list[$count]['value'] == $event['value']) &&
			($trigger_type == TRIGGER_MULT_EVENT_ENABLED) &&
			($event['value'] == TRIGGER_VALUE_TRUE))
		{
			$count++;
		}

		$event_list[$count] = $event;
	}

	$lclock = time();
	foreach($event_list as $id => $event) {
		$duration = zbx_date2age($lclock, $event['clock']);
		$lclock = $event['clock'];

		$value = new CCol(trigger_value2str($event['value']), get_trigger_value_style($event['value']));

// ack +++
		$ack = new CSpan(S_NO,'on');
		if($event['acknowledged']) {
			$ack=new CSpan(S_YES,'off');
		}
// ---
		$table->addRow(array(
			zbx_date2str(S_EVENTS_POPUP_EVENT_LIST_DATE_FORMAT,$event['clock']),
			$value,
			$duration,
			zbx_date2age($event['clock']),
			$ack
		));
	}

return $table;
}

function get_history_of_triggers_events($start,$num, $groupid=0, $hostid=0){
	global $USER_DETAILS;
	$config = select_config();

	$hide_unknown = CProfile::get('web.events.filter.hide_unknown',0);

	$sql_from = $sql_cond = '';

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_LIST);
	$available_triggers = get_accessible_triggers(PERM_READ_ONLY, array(), PERM_RES_DATA_ARRAY, get_current_nodeid());

	if($hostid > 0){
		$sql_cond = ' AND h.hostid='.$hostid;
	}
	else if($groupid > 0){
		$sql_from = ', hosts_groups hg ';
		$sql_cond = ' AND h.hostid=hg.hostid AND hg.groupid='.$groupid;
	}
	else{
		$sql_from = '';
		$sql_cond = ' AND '.DBcondition('h.hostid',$available_hosts);
	}

//---
	$triggers = array();
	$trigger_list = array();

	$sql = 'SELECT DISTINCT t.triggerid,t.priority,t.description,t.expression,h.host,t.type '.
			' FROM triggers t, functions f, items i, hosts h '.$sql_from.
			' WHERE '.DBcondition('t.triggerid', $available_triggers).
				' AND t.triggerid=f.triggerid '.
				' AND f.itemid=i.itemid '.
				' AND i.hostid=h.hostid '.
				' AND h.status='.HOST_STATUS_MONITORED.
				$sql_cond;

	$rez = DBselect($sql);
	while($rowz = DBfetch($rez)){
		$triggers[$rowz['triggerid']] = $rowz;
		array_push($trigger_list, $rowz['triggerid']);
	}

	$sql_cond=($hide_unknown == 1)?(' AND e.value<>'.TRIGGER_VALUE_UNKNOWN.' '):('');

	$table = new CTableInfo(S_NO_EVENTS_FOUND);
	$table->SetHeader(array(
			S_TIME,
			is_show_all_nodes() ? S_NODE : null,
			$hostid == 0 ? S_HOST : null,
			S_DESCRIPTION,
			S_VALUE,
			S_SEVERITY
			));

	if(!empty($triggers)){
		$sql = 'SELECT e.eventid, e.objectid as triggerid, e.clock, e.value, e.acknowledged '.
				' FROM events e '.
				' WHERE e.object='.EVENT_OBJECT_TRIGGER.
					' AND '.DBcondition('e.objectid', $trigger_list).
					$sql_cond.
				' ORDER BY e.eventid DESC';

		$result = DBselect($sql,10*($start+$num));
	}

	$col=0;
	$skip = $start;

	while(!empty($triggers) && ($col<$num) && ($row=DBfetch($result))){

		if($skip > 0){
			if(($hide_unknown == 1) && ($row['value'] == TRIGGER_VALUE_UNKNOWN)) continue;
			$skip--;
			continue;
		}

		$value = new CCol(trigger_value2str($row['value']), get_trigger_value_style($row['value']));

		$row = zbx_array_merge($triggers[$row['triggerid']],$row);
		if((1 == $hide_unknown) && (!event_initial_time($row,$hide_unknown))) continue;

		$table->addRow(array(
			zbx_date2str(S_EVENTS_TRIGGERS_EVENTS_HISTORY_LIST_DATE_FORMAT,$row["clock"]),
			get_node_name_by_elid($row['triggerid']),
			($hostid == 0)?$row['host']:null,
			new CLink(
				expand_trigger_description_by_data($row, ZBX_FLAG_EVENT),
				'tr_events.php?triggerid='.$row['triggerid'].'&eventid='.$row['eventid']
				),
			$value,
			new CCol(get_severity_description($row["priority"]), get_severity_style($row["priority"])),
		));

		$col++;
	}
return $table;
}

?>
