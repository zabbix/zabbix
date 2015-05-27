<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	$sources = [
		EVENT_SOURCE_TRIGGERS => _('trigger'),
		EVENT_SOURCE_DISCOVERY => _('discovery'),
		EVENT_SOURCE_AUTO_REGISTRATION => _('auto registration'),
		EVENT_SOURCE_INTERNAL => _x('internal', 'event source')
	];

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
	$objects = [
		EVENT_OBJECT_TRIGGER => _('trigger'),
		EVENT_OBJECT_DHOST => _('discovered host'),
		EVENT_OBJECT_DSERVICE => _('discovered service'),
		EVENT_OBJECT_AUTOREGHOST => _('auto-registered host'),
		EVENT_OBJECT_ITEM => _('item'),
		EVENT_OBJECT_LLDRULE => _('low-level discovery rule')
	];

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
	return [
		['source' => EVENT_SOURCE_TRIGGERS, 'object' => EVENT_OBJECT_TRIGGER],
		['source' => EVENT_SOURCE_DISCOVERY, 'object' => EVENT_OBJECT_DHOST],
		['source' => EVENT_SOURCE_DISCOVERY, 'object' => EVENT_OBJECT_DSERVICE],
		['source' => EVENT_SOURCE_AUTO_REGISTRATION, 'object' => EVENT_OBJECT_AUTOREGHOST],
		['source' => EVENT_SOURCE_INTERNAL, 'object' => EVENT_OBJECT_TRIGGER],
		['source' => EVENT_SOURCE_INTERNAL, 'object' => EVENT_OBJECT_ITEM],
		['source' => EVENT_SOURCE_INTERNAL, 'object' => EVENT_OBJECT_LLDRULE]
	];
}

function get_events_unacknowledged($db_element, $value_trigger = null, $value_event = null, $ack = false) {
	$elements = ['hosts' => [], 'hosts_groups' => [], 'triggers' => []];
	get_map_elements($db_element, $elements);

	if (empty($elements['hosts_groups']) && empty($elements['hosts']) && empty($elements['triggers'])) {
		return 0;
	}

	$config = select_config();
	$options = [
		'output' => ['triggerid'],
		'monitored' => 1,
		'skipDependent' => 1,
		'limit' => $config['search_limit'] + 1
	];
	if (!is_null($value_trigger)) {
		$options['filter'] = ['value' => $value_trigger];
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

	return API::Event()->get([
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'countOutput' => true,
		'objectids' => zbx_objectValues($triggerids, 'triggerid'),
		'filter' => [
			'value' => $value_event,
			'acknowledged' => $ack ? 1 : 0
		]
	]);
}

function get_next_event($currentEvent, array $eventList = []) {
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
			' WHERE e.source='.zbx_dbstr($currentEvent['source']).
				' AND e.object='.zbx_dbstr($currentEvent['object']).
				' AND e.objectid='.zbx_dbstr($currentEvent['objectid']).
				' AND e.clock>='.zbx_dbstr($currentEvent['clock']).
				' AND ((e.clock='.zbx_dbstr($currentEvent['clock']).' AND e.ns>'.$currentEvent['ns'].')'.
					' OR e.clock>'.zbx_dbstr($currentEvent['clock']).')'.
			' ORDER BY e.clock,e.eventid';
	return DBfetch(DBselect($sql, 1));
}

function make_event_details($event, $trigger) {
	$config = select_config();
	$table = new CTableInfo();

	$table->addRow([
		_('Event'),
		CMacrosResolverHelper::resolveEventDescription(array_merge($trigger, $event))
	]);
	$table->addRow([
		_('Time'),
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock'])
	]);

	if ($config['event_ack_enable']) {
		// to make resulting link not have hint with acknowledges
		$event['acknowledges'] = count($event['acknowledges']);
		$table->addRow([
			_('Acknowledged'),
			getEventAckState($event, $page['file'])
		]);
	}

	return $table;
}

function make_small_eventlist($startEvent) {
	$config = select_config();

	$table = new CTableInfo();
	$table->setHeader([
		_('Time'),
		_('Status'),
		_('Duration'),
		_('Age'),
		$config['event_ack_enable'] ? _('Ack') : null, // if we need to chow acks
		_('Actions')
	]);

	$clock = $startEvent['clock'];

	$events = API::Event()->get([
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'objectids' => $startEvent['objectid'],
		'eventid_till' => $startEvent['eventid'],
		'output' => API_OUTPUT_EXTEND,
		'select_acknowledges' => API_OUTPUT_COUNT,
		'sortfield' => ['clock', 'eventid'],
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => 20
	]);

	$sortFields = [
		['field' => 'clock', 'order' => ZBX_SORT_DOWN],
		['field' => 'eventid', 'order' => ZBX_SORT_DOWN]
	];
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

		$ack = getEventAckState($event, $page['file']);

		$table->addRow([
			new CLink(
				zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']),
				'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid'],
				'action'
			),
			$eventStatusSpan,
			$duration,
			zbx_date2age($event['clock']),
			$config['event_ack_enable'] ? $ack : null,
			isset($actions[$event['eventid']]) ? $actions[$event['eventid']] : SPACE
		]);
	}

	return $table;
}

function make_popup_eventlist($triggerId, $eventId) {
	$config = select_config();

	$table = new CTableInfo();

	// if acknowledges are turned on, we show 'ack' column
	if ($config['event_ack_enable']) {
		$table->setHeader([_('Time'), _('Status'), _('Duration'), _('Age'), _('Ack')]);
	}
	else {
		$table->setHeader([_('Time'), _('Status'), _('Duration'), _('Age')]);
	}

	$events = API::Event()->get([
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'output' => API_OUTPUT_EXTEND,
		'objectids' => $triggerId,
		'eventid_till' => $eventId,
		'select_acknowledges' => API_OUTPUT_COUNT,
		'sortfield' => ['clock', 'eventid'],
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => ZBX_WIDGET_ROWS
	]);

	$lclock = time();

	foreach ($events as $event) {
		$duration = zbx_date2age($lclock, $event['clock']);
		$lclock = $event['clock'];

		$eventStatusSpan = new CSpan(trigger_value2str($event['value']));

		// add colors and blinking to span depending on configuration and trigger parameters
		addTriggerValueStyle($eventStatusSpan, $event['value'], $event['clock'], $event['acknowledged']);

		$table->addRow([
			zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']),
			$eventStatusSpan,
			$duration,
			zbx_date2age($event['clock']),
			getEventAckState($event, null, false)
		]);
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
 * @param string		$url if not null, add url param to link with current page file name
 * @param bool			$isLink  if true, return link otherwise span
 * @param array			$params  additional params for link
 *
 * @return array|CLink|CSpan|null|string
 */
function getEventAckState($event, $url = null, $isLink = true, $params = []) {
	$config = select_config();

	if (!$config['event_ack_enable']) {
		return null;
	}

	if ($event['acknowledged'] != 0) {
		$acknowledges_num = is_array($event['acknowledges']) ? count($event['acknowledges']) : $event['acknowledges'];
	}

	if ($isLink) {
		if ($url === null) {
			$backurl = '';
		}
		else {
			$backurl = '&backurl='.urlencode($url);
		}

		$additionalParams = '';
		foreach ($params as $key => $value) {
			$additionalParams .= '&'.$key.'='.$value;
		}

		if ($event['acknowledged'] == 0) {
			$ack = new CLink(_('No'), 'acknow.php?eventid='.$event['eventid'].'&triggerid='.$event['objectid'].$backurl.$additionalParams, ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_RED);
		}
		else {
			$ackLink = new CLink(_('Yes'), 'acknow.php?eventid='.$event['eventid'].'&triggerid='.$event['objectid'].$backurl.$additionalParams, ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_GREEN);
			if (is_array($event['acknowledges'])) {
				$ackLinkHints = makeAckTab($event);
				if (!empty($ackLinkHints)) {
					$ackLink->setHint($ackLinkHints, '', false);
				}
			}
			$ack = [$ackLink, CViewHelper::showNum($acknowledges_num)];
		}
	}
	else {
		if ($event['acknowledged'] == 0) {
			$ack = new CSpan(_('No'), ZBX_STYLE_RED);
		}
		else {
			$ack = [new CSpan(_('Yes'), ZBX_STYLE_GREEN), CViewHelper::showNum($acknowledges_num)];
		}
	}

	return $ack;
}

function getLastEvents($options) {
	if (!isset($options['limit'])) {
		$options['limit'] = 15;
	}

	$triggerOptions = [
		'filter' => [],
		'skipDependent' => 1,
		'selectHosts' => ['hostid', 'name'],
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => 'lastchange',
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => $options['triggerLimit']
	];

	$eventOptions = [
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => ['clock', 'eventid'],
		'sortorder' => ZBX_SORT_DOWN
	];

	if (isset($options['eventLimit'])) {
		$eventOptions['limit'] = $options['eventLimit'];
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

	$sortClock = [];
	$sortEvent = [];
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
