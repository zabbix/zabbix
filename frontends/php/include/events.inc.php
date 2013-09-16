<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Returns the names of supported event sources.
 *
 * If the $source parameter is passed, returns the name of the specific source, otherwise - returns an array of all
 * supported sources.
 *
 * @param int $source
 *
 * @return array|string
 */
function eventSource($source = null) {
	$sources = array(
		EVENT_SOURCE_TRIGGERS => _('trigger'),
		EVENT_SOURCE_DISCOVERY => _('discovery'),
		EVENT_SOURCE_AUTO_REGISTRATION => _('auto registration'),
		EVENT_SOURCE_INTERNAL => _x('internal', 'event source')
	);

	if ($source === null) {
		return $sources;
	}
	elseif (isset($sources[$source])) {
		return $sources[$source];
	}
	else {
		return _('Unknown');
	}
}

/**
 * Returns the names of supported event objects.
 *
 * If the $source parameter is passed, returns the name of the specific object, otherwise - returns an array of all
 * supported objects.
 *
 * @param int $object
 *
 * @return array|string
 */
function eventObject($object = null) {
	$objects = array(
		EVENT_OBJECT_TRIGGER => _('trigger'),
		EVENT_OBJECT_DHOST => _('discovered host'),
		EVENT_OBJECT_DSERVICE => _('discovered service'),
		EVENT_OBJECT_AUTOREGHOST => _('auto-registered host'),
		EVENT_OBJECT_ITEM => _('item'),
		EVENT_OBJECT_LLDRULE => _('low-level discovery rule')
	);

	if ($object === null) {
		return $objects;
	}
	elseif (isset($objects[$object])) {
		return $objects[$object];
	}
	else {
		return _('Unknown');
	}
}

/**
 * Returns all supported event source-object pairs.
 *
 * @return array
 */
function eventSourceObjects() {
	return array(
		array('source' => EVENT_SOURCE_TRIGGERS, 'object' => EVENT_OBJECT_TRIGGER),
		array('source' => EVENT_SOURCE_DISCOVERY, 'object' => EVENT_OBJECT_DHOST),
		array('source' => EVENT_SOURCE_DISCOVERY, 'object' => EVENT_OBJECT_DSERVICE),
		array('source' => EVENT_SOURCE_AUTO_REGISTRATION, 'object' => EVENT_OBJECT_AUTOREGHOST),
		array('source' => EVENT_SOURCE_INTERNAL, 'object' => EVENT_OBJECT_TRIGGER),
		array('source' => EVENT_SOURCE_INTERNAL, 'object' => EVENT_OBJECT_ITEM),
		array('source' => EVENT_SOURCE_INTERNAL, 'object' => EVENT_OBJECT_LLDRULE)
	);
}

function get_tr_event_by_eventid($eventid) {
	$sql = 'SELECT e.*,t.triggerid,t.description,t.expression,t.priority,t.status,t.type'.
			' FROM events e,triggers t'.
			' WHERE e.eventid='.$eventid.
				' AND e.object='.EVENT_OBJECT_TRIGGER.
				' AND t.triggerid=e.objectid';
	return DBfetch(DBselect($sql));
}

function get_events_unacknowledged($db_element, $value_trigger = null, $value_event = null, $ack = false) {
	$elements = array('hosts' => array(), 'hosts_groups' => array(), 'triggers' => array());
	get_map_elements($db_element, $elements);

	if (empty($elements['hosts_groups']) && empty($elements['hosts']) && empty($elements['triggers'])) {
		return 0;
	}

	$config = select_config();
	$options = array(
		'nodeids' => get_current_nodeid(),
		'output' => array('triggerid'),
		'monitored' => 1,
		'skipDependent' => 1,
		'limit' => $config['search_limit'] + 1
	);
	if (!is_null($value_trigger)) {
		$options['filter'] = array('value' => $value_trigger);
	}
	if (!empty($elements['hosts_groups'])) {
		$options['groupids'] = array_unique($elements['hosts_groups']);
	}
	if (!empty($elements['hosts'])) {
		$options['hostids'] = array_unique($elements['hosts']);
	}
	if (!empty($elements['triggers'])) {
		$options['triggerids'] = array_unique($elements['triggers']);
	}
	$triggerids = API::Trigger()->get($options);

	return API::Event()->get(array(
		'countOutput' => 1,
		'objectids' => zbx_objectValues($triggerids, 'triggerid'),
		'filter' => array(
			'value' => is_null($value_event) ? array(TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE) : $value_event,
			'acknowledged' => $ack ? 1 : 0
		),
		'nopermissions' => true
	));
}

function get_next_event($currentEvent, array $eventList = array()) {
	$nextEvent = false;

	foreach ($eventList as $event) {
		// check only the events belonging to the same object
		// find the event with the smallest eventid but greater than the current event id
		if ($event['object'] == $currentEvent['object'] && bccomp($event['objectid'], $currentEvent['objectid']) == 0
				&& (bccomp($event['eventid'], $currentEvent['eventid']) === 1
				&& (!$nextEvent || bccomp($event['eventid'], $nextEvent['eventid']) === -1))) {
			$nextEvent = $event;
		}
	}
	if ($nextEvent) {
		return $nextEvent;
	}

	$sql = 'SELECT e.*'.
			' FROM events e'.
			' WHERE e.objectid='.$currentEvent['objectid'].
				' AND e.eventid>'.$currentEvent['eventid'].
				' AND e.object='.$currentEvent['object'].
				' AND e.source='.$currentEvent['source'].
			' ORDER BY e.object,e.objectid,e.eventid';
	return DBfetch(DBselect($sql, 1));
}

function make_event_details($event, $trigger) {
	$config = select_config();
	$table = new CTableInfo();

	$table->addRow(array(_('Event'), CMacrosResolverHelper::resolveEventDescription(array_merge($trigger, $event))));
	$table->addRow(array(_('Time'), zbx_date2str(_('d M Y H:i:s'), $event['clock'])));

	if ($config['event_ack_enable']) {
		// to make resulting link not have hint with acknowledges
		$event['acknowledges'] = count($event['acknowledges']);
		$ack = getEventAckState($event, true);
		$table->addRow(array(_('Acknowledged'), $ack));
	}

	return $table;
}

function make_small_eventlist($startEvent) {
	$config = select_config();

	$table = new CTableInfo();
	$table->setHeader(array(
		_('Time'),
		_('Status'),
		_('Duration'),
		_('Age'),
		$config['event_ack_enable'] ? _('Ack') : null, // if we need to chow acks
		_('Actions')
	));

	$clock = $startEvent['clock'];

	$options = array(
		'objectids' => $startEvent['objectid'],
		'eventid_till' => $startEvent['eventid'],
		'output' => API_OUTPUT_EXTEND,
		'select_acknowledges' => API_OUTPUT_COUNT,
		'sortfield' => 'eventid',
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => 20
	);
	$events = API::Event()->get($options);
	$sortFields = array(
		array('field' => 'clock', 'order' => ZBX_SORT_DOWN),
		array('field' => 'eventid', 'order' => ZBX_SORT_DOWN)
	);
	CArrayHelper::sort($events, $sortFields);

	$actions = getEventActionsStatHints(zbx_objectValues($events, 'eventid'));

	foreach ($events as $event) {
		$lclock = $clock;
		$duration = zbx_date2age($lclock, $event['clock']);
		$clock = $event['clock'];

		if (bccomp($startEvent['eventid'],$event['eventid']) == 0 && $nextevent = get_next_event($event, $events)) {
			$duration = zbx_date2age($nextevent['clock'], $clock);
		}
		elseif (bccomp($startEvent['eventid'], $event['eventid']) == 0) {
			$duration = zbx_date2age($clock);
		}

		$eventStatusSpan = new CSpan(trigger_value2str($event['value']));

		// add colors and blinking to span depending on configuration and trigger parameters
		addTriggerValueStyle(
			$eventStatusSpan,
			$event['value'],
			$event['clock'],
			$event['acknowledged']
		);

		$ack = getEventAckState($event, true);

		$table->addRow(array(
			new CLink(
				zbx_date2str(_('d M Y H:i:s'), $event['clock']),
				'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid'],
				'action'
			),
			$eventStatusSpan,
			$duration,
			zbx_date2age($event['clock']),
			$config['event_ack_enable'] ? $ack : null,
			isset($actions[$event['eventid']]) ? $actions[$event['eventid']] : SPACE
		));
	}

	return $table;
}

function make_popup_eventlist($triggerId, $eventId) {
	$config = select_config();

	$table = new CTableInfo();
	$table->setAttribute('style', 'width: 400px;');

	// if acknowledges are turned on, we show 'ack' column
	if ($config['event_ack_enable']) {
		$table->setHeader(array(_('Time'), _('Status'), _('Duration'), _('Age'), _('Ack')));
	}
	else {
		$table->setHeader(array(_('Time'), _('Status'), _('Duration'), _('Age')));
	}

	$events = API::Event()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'objectids' => $triggerId,
		'eventid_till' => $eventId,
		'nopermissions' => true,
		'select_acknowledges' => API_OUTPUT_COUNT,
		'sortfield' => 'eventid',
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => ZBX_WIDGET_ROWS
	));

	$lclock = time();

	foreach ($events as $event) {
		$duration = zbx_date2age($lclock, $event['clock']);
		$lclock = $event['clock'];

		$eventStatusSpan = new CSpan(trigger_value2str($event['value']));

		// add colors and blinking to span depending on configuration and trigger parameters
		addTriggerValueStyle($eventStatusSpan, $event['value'], $event['clock'], $event['acknowledged']);

		$table->addRow(array(
			zbx_date2str(_('d M Y H:i:s'), $event['clock']),
			$eventStatusSpan,
			$duration,
			zbx_date2age($event['clock']),
			getEventAckState($event, false, false)
		));
	}

	return $table;
}

/**
 * Create element with event acknowledges info.
 * If $event has subarray 'acknowledges', returned link will have hint with acknowledges.
 *
 * @param array			$event   event data
 * @param int			$event['acknowledged']
 * @param int			$event['eventid']
 * @param int			$event['objectid']
 * @param array			$event['acknowledges']
 * @param bool|string	$backUrl if true, add backurl param to link with current page file name
 * @param bool			$isLink  if true, return link otherwise span
 * @param array			$params  additional params for link
 *
 * @return array|CLink|CSpan|null|string
 */
function getEventAckState($event, $backUrl = false, $isLink = true, $params = array()) {
	$config = select_config();

	if (!$config['event_ack_enable']) {
		return null;
	}

	if ($isLink) {
		if (!empty($backUrl)) {
			if (is_bool($backUrl)) {
				global $page;
				$backurl = '&backurl='.$page['file'];
			}
			else {
				$backurl = '&backurl='.$backUrl;
			}
		}
		else {
			$backurl = '';
		}

		$additionalParams = '';
		foreach ($params as $key => $value) {
			$additionalParams .= '&'.$key.'='.$value;
		}

		if ($event['acknowledged'] == 0) {
			$ack = new CLink(_('No'), 'acknow.php?eventid='.$event['eventid'].'&triggerid='.$event['objectid'].$backurl.$additionalParams, 'disabled');
		}
		else {
			$ackLink = new CLink(_('Yes'), 'acknow.php?eventid='.$event['eventid'].'&triggerid='.$event['objectid'].$backurl.$additionalParams, 'enabled');
			if (is_array($event['acknowledges'])) {
				$ackLinkHints = makeAckTab($event);
				if (!empty($ackLinkHints)) {
					$ackLink->setHint($ackLinkHints, '', '', false);
				}
				$ack = array($ackLink, ' ('.count($event['acknowledges']).')');
			}
			else {
				$ack = array($ackLink, ' ('.$event['acknowledges'].')');
			}
		}
	}
	else {
		if ($event['acknowledged'] == 0) {
			$ack = new CSpan(_('No'), 'on');
		}
		else {
			$ack = array(new CSpan(_('Yes'), 'off'), ' ('.(is_array($event['acknowledges']) ? count($event['acknowledges']) : $event['acknowledges']).')');
		}
	}

	return $ack;
}

function getLastEvents($options) {
	if (!isset($options['limit'])) {
		$options['limit'] = 15;
	}

	$triggerOptions = array(
		'filter' => array(),
		'skipDependent' => 1,
		'selectHosts' => array('hostid', 'host'),
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => 'lastchange',
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => $options['triggerLimit']
	);

	$eventOptions = array(
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => 'eventid',
		'sortorder' => ZBX_SORT_DOWN
	);

	if (isset($options['eventLimit'])) {
		$eventOptions['limit'] = $options['eventLimit'];
	}

	if (isset($options['nodeids'])) {
		$triggerOptions['nodeids'] = $options['nodeids'];
	}
	if (isset($options['priority'])) {
		$triggerOptions['filter']['priority'] = $options['priority'];
	}
	if (isset($options['monitored'])) {
		$triggerOptions['monitored'] = $options['monitored'];
	}
	if (isset($options['lastChangeSince'])) {
		$triggerOptions['lastChangeSince'] = $options['lastChangeSince'];
		$eventOptions['time_from'] = $options['lastChangeSince'];
	}
	if (isset($options['value'])) {
		$triggerOptions['filter']['value'] = $options['value'];
		$eventOptions['value'] = $options['value'];
	}

	// triggers
	$triggers = API::Trigger()->get($triggerOptions);
	$triggers = zbx_toHash($triggers, 'triggerid');

	// events
	$eventOptions['objectids'] = zbx_objectValues($triggers, 'triggerid');
	$events = API::Event()->get($eventOptions);

	$sortClock = array();
	$sortEvent = array();
	foreach ($events as $enum => $event) {
		if (!isset($triggers[$event['objectid']])) {
			continue;
		}

		$events[$enum]['trigger'] = $triggers[$event['objectid']];
		$events[$enum]['host'] = reset($events[$enum]['trigger']['hosts']);
		$sortClock[$enum] = $event['clock'];
		$sortEvent[$enum] = $event['eventid'];

		//expanding description for the state where event was
		$merged_event = array_merge($event, $triggers[$event['objectid']]);
		$events[$enum]['trigger']['description'] = CMacrosResolverHelper::resolveEventDescription($merged_event);
	}
	array_multisort($sortClock, SORT_DESC, $sortEvent, SORT_DESC, $events);

	return $events;
}
