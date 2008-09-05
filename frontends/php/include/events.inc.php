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
	function	event_source2str($sourceid){
		switch($sourceid){
			case EVENT_SOURCE_TRIGGERS:	return S_TRIGGERS;
			case EVENT_SOURCE_DISCOVERY:	return S_DISCOVERY;
			default:			return S_UNKNOWN;
		}
	}
	
	function get_event_by_eventid($eventid){
		$db_events = DBselect("select * from events where eventid=$eventid");
		return DBfetch($db_events);
	}

	function get_tr_event_by_eventid($eventid){
		$result = DBfetch(DBselect('SELECT e.*,t.triggerid, t.description,t.priority,t.status,t.type '.
									' FROM events e,triggers t '.
									' WHERE e.eventid='.$eventid.
										' AND e.object='.EVENT_OBJECT_TRIGGER.
										' AND t.triggerid=e.objectid '
									));
	return $result;
	}

	
/* function:
 *     event_initial_time
 *
 * description:
 *     returns 'true' if event is initial, otherwise false; 
 *
 * author: Aly
 */
function event_initial_time($row,$show_unknown=0){
	$events = get_latest_events($row,$show_unknown);
	
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

function first_initial_eventid($row,$show_unknown=0){
	
	$events = get_latest_events($row,$show_unknown);

	$sql_cond=($show_unknown == 0)?' AND e.value<>2 ':'';
	
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
		return first_initial_eventid($row,$show_unknown=0);			// recursion!!!
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
function get_latest_events($row,$show_unknown=0){

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

	if($show_unknown != 0){
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
function get_next_event($row,$show_unknown=0){
	$sql_cond=($show_unknown == 0)?' AND e.value<>'.TRIGGER_VALUE_UNKNOWN:'';
	
	if((TRIGGER_MULT_EVENT_ENABLED == $row['type']) && (TRIGGER_VALUE_TRUE == $row['value'])){
		$sql = 'SELECT e.eventid, e.value '.
			' FROM events e'.
			' WHERE e.objectid='.$row['triggerid'].
				' AND e.eventid > '.$row['eventid'].
				' AND e.object='.EVENT_OBJECT_TRIGGER.
				' AND e.value='.$row['value'].
			' ORDER BY e.object, e.objectid, e.eventid';
	}
	else{
		$sql = 'SELECT e.eventid, e.value, e.clock '.
			' FROM events e'.
			' WHERE e.objectid='.$row['triggerid'].
				' AND e.eventid > '.$row['eventid'].
				' AND e.object='.EVENT_OBJECT_TRIGGER.
				' AND e.value<>'.$row['value'].
				$sql_cond.
			' ORDER BY e.object, e.objectid, e.eventid';
	}
	$rez = DBfetch(DBselect($sql,1));
return $rez;
}

// author: Aly	
function make_event_details($eventid){
	$event = get_tr_event_by_eventid($eventid);
	
	$table = new CTableInfo();
	
	$table->AddRow(array(S_EVENT, expand_trigger_description($event['triggerid'])));
	$table->AddRow(array(S_TIME, date('Y.M.d H:i:s',$event['clock'])));
	
	$duration = zbx_date2age($event['clock']);
	if($next_event = get_next_event($event,1)){
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
			new CLink(new CSpan(S_YES,'off'),'acknow.php?eventid='.$event['eventid'],'action'),
			SPACE.'('.$rows.')'
			);
	}

	$table->AddRow(array(S_STATUS, $value));	
	$table->AddRow(array(S_DURATION, $duration));
	$table->AddRow(array(S_ACKNOWLEDGED, $ack));
	
return $table;
}

function make_small_eventlist($triggerid,&$trigger_data){
	
	$table = new CTableInfo();
	$table->SetHeader(array(S_TIME,S_STATUS,S_DURATION, S_AGE, S_ACK, S_ACTIONS));

	$sql = 'SELECT * '.
			' FROM events '.
			' WHERE objectid='.$triggerid.
				' AND object='.EVENT_OBJECT_TRIGGER.
			' ORDER BY clock DESC';

	$result = DBselect($sql,20);

	$rows = array();
	$count = 0;
	
	while($row=DBfetch($result)){	
		
		if(!empty($rows) && ($rows[$count]['value'] != $row['value'])){
			$count++;
		}
		else if(!empty($rows) && 
				($rows[$count]['value'] == $row['value']) && 
				($trigger_data['type'] == TRIGGER_MULT_EVENT_ENABLED) && 
				($row['value'] == TRIGGER_VALUE_TRUE)
				){
			$count++;
		}
		$rows[$count] = $row;
	}

	$clock=time();
	
	foreach($rows as $id => $row){
		$lclock=$clock;
		$clock=$row["clock"];
		
		$duration = zbx_date2age($lclock,$clock);

		$value = new CCol(trigger_value2str($row['value']), get_trigger_value_style($row["value"]));
	
		$ack = '-';
		if(1 == $row['acknowledged']){
			$db_acks = get_acknowledges_by_eventid($row['eventid']);
			$rows=0;
			while($a=DBfetch($db_acks))	$rows++;
			
			$ack=array(
				new CLink(new CSpan(S_YES,'off'),'acknow.php?eventid='.$row['eventid'],'action'),
				SPACE.'('.$rows.')'
				);
		}
		
//actions
		$actions= get_event_actions_stat_hints($row['eventid']);					
//--------		

		$table->AddRow(array(
			new CLink(
					date('Y.M.d H:i:s',$row['clock']),
					"tr_events.php?triggerid=".$trigger_data['triggerid'].'&eventid='.$row['eventid'],
					"action"
					),
			$value,
			$duration,
			zbx_date2age($row['clock']),
			$ack,
			$actions
			));
	}
return $table;
}

function get_history_of_triggers_events($start,$num, $groupid=0, $hostid=0){
	global $USER_DETAILS;
	$config = select_config();
	
	$show_unknown = get_profile('web.events.filter.show_unknown',0);
	
	$sql_from = $sql_cond = '';

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_LIST);
	$available_triggers = get_accessible_triggers(PERM_READ_ONLY, PERM_RES_DATA_ARRAY, get_current_nodeid());
	
	if($hostid > 0){
		$sql_cond = ' AND h.hostid='.$hostid;
	}
	else if($groupid > 0){
		$sql_from = ', hosts_groups hg ';
		$sql_cond = ' AND h.hostid=hg.hostid AND hg.groupid='.$groupid;
	}
	else{
		$sql_from = ', hosts_groups hg ';
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

	$sql_cond=($show_unknown == 0)?(' AND e.value<>'.TRIGGER_VALUE_UNKNOWN.' '):('');

	$table = new CTableInfo(S_NO_EVENTS_FOUND); 
	$table->SetHeader(array(
			make_sorting_link(S_TIME,'e.eventid'),
			is_show_subnodes() ? S_NODE : null,
			$hostid == 0 ? S_HOST : null,
			S_DESCRIPTION,
			S_VALUE,
			S_SEVERITY
			));

	if(!empty($triggers)){
		$sql = 'SELECT e.eventid, e.objectid as triggerid, e.clock, e.value, e.acknowledged '.
				' FROM events e '.
				' WHERE (e.object+0)='.EVENT_OBJECT_TRIGGER.
					' AND '.DBcondition('e.objectid', $trigger_list).
					$sql_cond.
				order_by('e.clock');

		$result = DBselect($sql,10*($start+$num));
	}
		
	$col=0;
	$skip = $start;

	while(!empty($triggers) && ($col<$num) && ($row=DBfetch($result))){
		
		if($skip > 0){
			if(($show_unknown == 0) && ($row['value'] == TRIGGER_VALUE_UNKNOWN)) continue;
			$skip--;
			continue;
		}
	
		$value = new CCol(trigger_value2str($row['value']), get_trigger_value_style($row['value']));

		$row = array_merge($triggers[$row['triggerid']],$row);
		if((0 == $show_unknown) && (!event_initial_time($row,$show_unknown))) continue;
		
		$table->AddRow(array(
			date("Y.M.d H:i:s",$row["clock"]),
			get_node_name_by_elid($row['triggerid']),
			($hostid == 0)?$row['host']:null,
			new CLink(
				expand_trigger_description_by_data($row, ZBX_FLAG_EVENT),
				"tr_events.php?triggerid=".$row["triggerid"],"action"
				),
			$value,
			new CCol(get_severity_description($row["priority"]), get_severity_style($row["priority"])),
		));
			
		$col++;
	}
return $table;
}

function get_history_of_discovery_events($start,$end){

	$sql_cond=' AND e.clock>'.$start;
	$sql_cond.=' AND e.clock<'.$end;

	$sql = 'SELECT DISTINCT e.source,e.object,e.objectid,e.clock,e.value '.
			' FROM events e'.
			' WHERE e.source='.EVENT_SOURCE_DISCOVERY.
			$sql_cond.
			order_by('e.clock');
	$db_events = DBselect($sql);
   
	$table = new CTableInfo(S_NO_EVENTS_FOUND); 
	$table->SetHeader(array(S_TIME, S_IP, S_DESCRIPTION, S_STATUS));
	$col=0;
	
	while($event_data = DBfetch($db_events)){
		$value = new CCol(trigger_value2str($event_data['value']), get_trigger_value_style($event_data['value']));

		switch($event_data['object']){
			case EVENT_OBJECT_DHOST:
				$object_data = DBfetch(DBselect('SELECT ip FROM dhosts WHERE dhostid='.$event_data['objectid']));
				$description = SPACE;
				break;
			case EVENT_OBJECT_DSERVICE:
				$object_data = DBfetch(DBselect('SELECT h.ip,s.type,s.port '.
											' FROM dhosts h,dservices s '.
											' WHERE h.dhostid=s.dhostid '.
												' AND s.dserviceid='.$event_data['objectid']));
												
				$description = S_SERVICE.': '.discovery_check_type2str($object_data['type']).'; '.S_PORT.': '.$object_data['port'];
				break;
			default:
				continue;
		}

		if(!$object_data) continue;

		$table->AddRow(array(
			date('Y.M.d H:i:s',$event_data['clock']),
			$object_data['ip'],
			$description,
			$value));

		$col++;
	}
return $table;
}
?>
