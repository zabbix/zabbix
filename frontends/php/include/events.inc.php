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
	function event_source2str($sourceid){
		switch($sourceid){
			case EVENT_SOURCE_TRIGGERS:	return S_TRIGGERS;
			case EVENT_SOURCE_DISCOVERY:	return S_DISCOVERY;
			default:			return S_UNKNOWN;
		}
	}

	function get_tr_event_by_eventid($eventid){
		$sql = 'SELECT e.*,t.triggerid, t.description,t.expression,t.priority,t.status,t.type '.
				' FROM events e,triggers t '.
				' WHERE e.eventid='.$eventid.
					' AND e.object='.EVENT_OBJECT_TRIGGER.
					' AND t.triggerid=e.objectid';
		$result = DBfetch(DBselect($sql));
	return $result;
	}

	function get_events_unacknowledged($db_element, $value_trigger=null, $value_event=null, $ack=false){
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
		if(!is_null($value_trigger)) $options['filter'] = array('value' => $value_trigger);

		if(!empty($elements['hosts_groups'])) $options['groupids'] = array_unique($elements['hosts_groups']);
		if(!empty($elements['hosts'])) $options['hostids'] = array_unique($elements['hosts']);
		if(!empty($elements['triggers'])) $options['triggerids'] = array_unique($elements['triggers']);
		$triggerids = CTrigger::get($options);

		$options = array(
			'countOutput' => 1,
			'triggerids' => zbx_objectValues($triggerids, 'triggerid'),
			'object' => EVENT_OBJECT_TRIGGER,
			'acknowledged' => $ack ? 1 : 0,
			'value' => is_null($value_event) ? array(TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE) : $value_event,
			'nopermissions' => 1
		);
		$event_count = CEvent::get($options);

	return $event_count;
	}

/* function:
 *     get_next_event
 *
 * description:
 *     return next event by value
 *
 * author: Aly
 */
function get_next_event($currentEvent, $eventList = null, $showUnknown=false){
	if(!is_null($eventList)){
		$nextEvent = reset($eventList);
		foreach($eventList as $enum => $event){
			if(bccomp($event['eventid'], $currentEvent['eventid']) == 0){
				if(bccomp($event['eventid'], $nextEvent['eventid']) == 0) $nextEvent = false;
				break;
			}

			if(($event['object'] == $currentEvent['object']) &&
				(bccomp($event['objectid'], $currentEvent['objectid']) == 0) &&
				($event['clock'] >= $currentEvent['clock']) &&
				(bccomp($event['eventid'], $currentEvent['eventid']) != 0)
			){
				$nextEvent = $event;
			}

		}
		if($nextEvent) return $nextEvent;
	}


	$sql = 'SELECT e.* '.
		' FROM events e '.
		' WHERE e.objectid='.$currentEvent['objectid'].
			' AND e.eventid > '.$currentEvent['eventid'].
			' AND e.object='.EVENT_OBJECT_TRIGGER.
			($showUnknown ? '' : ' AND e.value_changed='.TRIGGER_VALUE_CHANGED_YES).
		' ORDER BY e.object, e.objectid, e.eventid';

	return DBfetch(DBselect($sql,1));
}

// author: Aly
function make_event_details($event, $trigger){
	$table = new CTableInfo();

	$table->addRow(array(S_EVENT, expand_trigger_description_by_data(array_merge($trigger, $event), ZBX_FLAG_EVENT)));
	$table->addRow(array(S_TIME, zbx_date2str(S_EVENTS_EVENT_DETAILS_DATE_FORMAT,$event['clock'])));

	$duration = zbx_date2age($event['clock']);

	if($next_event = get_next_event($event, null, true)){
		$duration = zbx_date2age($event['clock'],$next_event['clock']);
	}

	if($event['value'] == TRIGGER_VALUE_FALSE){
		$value = new CCol(S_OK_BIG,'off');
	}
	elseif($event['value'] == TRIGGER_VALUE_TRUE){
		$value = new CCol(S_PROBLEM_BIG,'on');
	}
	else{
		$value = new CCol(S_UNKNOWN_BIG,'unknown');
	}

	$ack = '-';
	if(($event['value'] == TRIGGER_VALUE_TRUE) && ($event['acknowledged'] == 1)){
		$db_acks = get_acknowledges_by_eventid($event['eventid']);
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

function make_small_eventlist($startEvent){
	$config = select_config();

	$table = new CTableInfo();
	$table->setHeader(array(
		S_TIME,
		S_STATUS,
		S_DURATION,
		S_AGE,
		($config['event_ack_enable'] ? S_ACK : null), //if we need to chow acks
		S_ACTIONS
	));

	$clock = $startEvent['clock'];

	$options = array(
		'triggerids' => $startEvent['objectid'],
		'eventid_till' => $startEvent['eventid'],
		'filter' => array('value_changed' => null),
		'output' => API_OUTPUT_EXTEND,
		'select_acknowledges' => API_OUTPUT_COUNT,
		'sortfield' => 'eventid',
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => 20
	);
	$events = CEvent::get($options);
	order_result($events, array('clock', 'eventid'), ZBX_SORT_DOWN);
	foreach($events as $enum => $event){
		$lclock = $clock;
		$duration = zbx_date2age($lclock, $event['clock']);
		$clock = $event['clock'];

		if($startEvent['eventid'] == $event['eventid'] && ($nextevent = get_next_event($event,null,true))) {
			$duration = zbx_date2age($nextevent['clock'], $clock);
		}
		else if($startEvent['eventid'] == $event['eventid']) {
			$duration = zbx_date2age($clock);
		}

		$value = new CCol(trigger_value2str($event['value']), get_trigger_value_style($event['value']));

		$ack = getEventAckState($event);

//actions
		$actions = get_event_actions_stat_hints($event['eventid']);
//--------

		$table->addRow(array(
			new CLink(
				zbx_date2str(S_EVENTS_SMALL_EVENT_LIST_DATE_FORMAT,$event['clock']),
				'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid'],
				'action'
			),
			$value,
			$duration,
			zbx_date2age($event['clock']),
			($config['event_ack_enable'] ? $ack : null),
			$actions
		));
	}

return $table;
}

function make_popup_eventlist($eventid, $trigger_type, $triggerid) {

	$config = select_config();

	$table = new CTableInfo();
	
	//if acknowledges are turned on, we show 'ack' column
	if ($config['event_ack_enable']) {
		$table->setHeader(array(S_TIME,S_STATUS,S_DURATION, S_AGE, S_ACK));
	}
	else {
		$table->setHeader(array(S_TIME,S_STATUS,S_DURATION, S_AGE));
	}

	$table->setAttribute('style', 'width: 400px;');

	$options = array(
		'output' => API_OUTPUT_EXTEND,
		'triggerids' => $triggerid,
		'eventid_till' => $eventid,
		'filter' => array(
			'object' => EVENT_OBJECT_TRIGGER
		),
		'nopermissions' => 1,
		'sortfield' => 'clock',
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => ZBX_WIDGET_ROWS
	);
	$db_events = CEvent::get($options);

	$lclock = time();
	foreach($db_events as $id => $event) {
		$duration = zbx_date2age($lclock, $event['clock']);
		$lclock = $event['clock'];

		$value = new CCol(trigger_value2str($event['value']), get_trigger_value_style($event['value']));

// ack +++
		$ack = getEventAckState($event);

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

function getEventAckState($event){
	$config = select_config();

	if(!$config['event_ack_enable']) return null;
//	if(($event['value'] != TRIGGER_VALUE_TRUE) || ($event['value_changed'] == TRIGGER_VALUE_CHANGED_NO)){
	if($event['value_changed'] == TRIGGER_VALUE_CHANGED_NO){
		return SPACE;
	}

	if($event['acknowledged'] == 0){
		$ack = new CLink(S_NO,'acknow.php?eventid='.$event['eventid'],'on');
	}
	else{
		$rows = is_array($event['acknowledges']) ? count($event['acknowledges']) : $event['acknowledges'];
		$ack = array(
			new CLink(new CSpan(S_YES,'off'),'acknow.php?eventid='.$event['eventid']),
			SPACE.'('.$rows.')'
		);
	}

return $ack;
}

function getLastEvents($options){
	if(!isset($options['limit'])) $options['limit'] = 15;

	$triggerOptions = array(
		'filter' => array(),
		'skipDependent'	=> 1,
		'selectHosts' => array('hostid', 'host'),
		'output' => API_OUTPUT_EXTEND,
//		'expandDescription' => 1,
		'sortfield' => 'lastchange',
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => $options['limit']
	);

	$eventOptions = array(
		'object' => EVENT_OBJECT_TRIGGER,
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => 'clock',
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => $options['limit']
	);

	if(isset($options['nodeids'])) $triggerOptions['nodeids'] = $options['nodeids'];
	if(isset($options['priority'])) $triggerOptions['filter']['priority'] = $options['priority'];
	if(isset($options['monitored'])) $triggerOptions['monitored'] = $options['monitored'];

	if(isset($options['lastChangeSince'])){
		$triggerOptions['lastChangeSince'] = $options['lastChangeSince'];
		$eventOptions['time_from'] = $options['lastChangeSince'];
	}

	if(isset($options['value'])){
		$triggerOptions['filter']['value'] = $options['value'];
		$eventOptions['value'] = $options['value'];
	}

// triggers
	$triggers = CTrigger::get($triggerOptions);
	$triggers = zbx_toHash($triggers, 'triggerid');

// events
	$eventOptions['triggerids'] = zbx_objectValues($triggers, 'triggerid');
	$events = CEvent::get($eventOptions);

	$sortClock = array();
	$sortEvent = array();
	foreach($events as $enum => $event){
		if(!isset($triggers[$event['objectid']])) continue;

		$events[$enum]['trigger'] = $triggers[$event['objectid']];
		$events[$enum]['host'] = reset($events[$enum]['trigger']['hosts']);

		$sortClock[$enum] = $event['clock'];
		$sortEvent[$enum] = $event['eventid'];

		//expanding description for the state where event was
		$merged_event = array_merge($event, $triggers[$event['objectid']]);
		$events[$enum]['trigger']['description'] = expand_trigger_description_by_data($merged_event, ZBX_FLAG_EVENT);
	}

	array_multisort($sortClock, SORT_DESC, $sortEvent, SORT_DESC, $events);

return $events;
}
?>
