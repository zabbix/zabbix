<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

/**
 *
 * @param array  $event								An array of event data.
 * @param string $event['eventid']					Event ID.
 * @param string $event['correlationid']			OK Event correlation ID.
 * @param string $event['userid]					User ID who generated the OK event.
 * @param string $event['name']						Event name.
 * @param string $event['acknowledged']				State of acknowledgement.
 * @param string $backurl							A link back after acknowledgement has been clicked.
 *
 * @return CTableInfo
 */
function make_event_details($event, $backurl) {
	$event_update_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'acknowledge.edit')
		->setArgument('eventids', [$event['eventid']])
		->setArgument('backurl', $backurl)
		->getUrl();

	$table = (new CTableInfo())
		->addRow([
			_('Event'),
			$event['name']
		])
		->addRow([
			_('Time'),
			zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock'])
		])
		->addRow([
			_('Acknowledged'),
			(new CLink($event['acknowledged'] == EVENT_ACKNOWLEDGED ? _('Yes') : _('No'), $event_update_url))
				->addClass($event['acknowledged'] == EVENT_ACKNOWLEDGED ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
				->addClass(ZBX_STYLE_LINK_ALT)
		]);

	if ($event['r_eventid'] != 0) {
		if ($event['correlationid'] != 0) {
			$correlations = API::Correlation()->get([
				'output' => ['correlationid', 'name'],
				'correlationids' => [$event['correlationid']]
			]);

			if ($correlations) {
				if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
					$correlation_name = (new CLink($correlations[0]['name'],
						(new CUrl('correlation.php'))
							->setArgument('correlationid', $correlations[0]['correlationid'])
							->getUrl()
					))->addClass(ZBX_STYLE_LINK_ALT);
				}
				else {
					$correlation_name = $correlations[0]['name'];
				}
			}
			else {
				$correlation_name = _('Correlation rule');
			}

			$table->addRow([_('Resolved by'), $correlation_name]);
		}
		elseif ($event['userid'] != 0) {
			if ($event['userid'] == CWebUser::$data['userid']) {
				$table->addRow([_('Resolved by'), getUserFullname([
					'alias' => CWebUser::$data['alias'],
					'name' => CWebUser::$data['name'],
					'surname' => CWebUser::$data['surname']
				])]);
			}
			else {
				$user = API::User()->get([
					'output' => ['alias', 'name', 'surname'],
					'userids' => [$event['userid']]
				]);

				if ($user) {
					$table->addRow([_('Resolved by'), getUserFullname($user[0])]);
				}
				else {
					$table->addRow([_('Resolved by'), _('User')]);
				}
			}
		}
		else {
			$table->addRow([_('Resolved by'), _('Trigger')]);
		}
	}

	$tags = makeEventsTags([$event]);

	$table->addRow([_('Tags'), $tags[$event['eventid']]]);

	return $table;
}

function make_small_eventlist($startEvent, $backurl) {
	$table = (new CTableInfo())
		->setHeader([
			_('Time'),
			_('Recovery time'),
			_('Status'),
			_('Age'),
			_('Duration'),
			_('Ack'),
			_('Actions')
		]);

	$clock = $startEvent['clock'];

	$events = API::Event()->get([
		'output' => ['eventid', 'r_eventid', 'source', 'object', 'objectid', 'clock', 'ns', 'acknowledged', 'severity'],
		'select_acknowledges' => API_OUTPUT_EXTEND,
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'value' => TRIGGER_VALUE_TRUE,
		'objectids' => $startEvent['objectid'],
		'eventid_till' => $startEvent['eventid'],
		'sortfield' => ['clock', 'eventid'],
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => 20,
		'preservekeys' => true
	]);

	$r_eventids = [];

	foreach ($events as $event) {
		$r_eventids[$event['r_eventid']] = true;
	}
	unset($r_eventids[0]);

	$r_events = $r_eventids
		? API::Event()->get([
			'output' => ['clock', 'correlationid', 'userid'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'eventids' => array_keys($r_eventids),
			'preservekeys' => true
		])
		: [];

	$triggerids = [];
	foreach ($events as &$event) {
		if (array_key_exists($event['r_eventid'], $r_events)) {
			$event['r_clock'] = $r_events[$event['r_eventid']]['clock'];
			$event['correlationid'] = $r_events[$event['r_eventid']]['correlationid'];
			$event['userid'] = $r_events[$event['r_eventid']]['userid'];
		}
		else {
			$triggerids[] = $event['objectid'];
			$event['r_clock'] = 0;
			$event['correlationid'] = 0;
			$event['userid'] = 0;
		}
	}
	unset($event);

	$actions = makeEventsActionsTable($events, getDefaultActionOptions());

	// Get trigger severities.
	$db_triggers = $triggerids
		? API::Trigger()->get([
			'output' => ['priority'],
			'triggerids' => $triggerids,
			'preservekeys' => true
		])
		: [];

	foreach ($events as $index => $event) {
		$duration = ($event['r_eventid'] != 0)
			? zbx_date2age($event['clock'], $event['r_clock'])
			: zbx_date2age($event['clock']);

		if ($event['r_eventid'] == 0) {
			$in_closing = false;

			foreach ($event['acknowledges'] as $acknowledge) {
				if ($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) {
					$in_closing = true;
					break;
				}
			}

			$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
			$value_str = $in_closing ? _('CLOSING') : _('PROBLEM');
			$value_clock = $in_closing ? time() : $event['clock'];
		}
		else {
			$value = TRIGGER_VALUE_FALSE;
			$value_str = _('RESOLVED');
			$value_clock = $event['r_clock'];
		}

		$cell_status = new CSpan($value_str);

		/*
		 * Add colors to span depending on configuration and trigger parameters. No blinking added to status,
		 * since the page is not on autorefresh.
		 */
		addTriggerValueStyle($cell_status, $value, $value_clock, $event['acknowledged'] == EVENT_ACKNOWLEDGED);

		// Make icons for actions column.
		$action_icons = [];

		// Add messages icon.
		$messages_action = $actions[$event['eventid']]['messages'];
		if ($messages_action['count']) {
			$action_icons[] = makeActionIcon(ZBX_STYLE_ACTION_ICON_MSGS, $messages_action['table'],
				$messages_action['count']
			);
		}

		// Add severity change icon.
		$severity_action = $actions[$event['eventid']]['severity_changes'];
		if ($severity_action['count']) {
			if ($db_triggers[$event['objectid']]['priority'] > $event['severity']) {
				$icon_style = ZBX_STYLE_ACTION_ICON_SEV_UP;
			}
			elseif ($db_triggers[$event['objectid']]['priority'] < $event['severity']) {
				$icon_style = ZBX_STYLE_ACTION_ICON_SEV_DOWN;
			}
			else {
				$icon_style = ZBX_STYLE_ACTION_ICON_SEV_CHANGED;
			}

			$action_icons[] = makeActionIcon($icon_style, $severity_action['table']);
		}

		// Add actions list icon.
		$action_list = $actions[$event['eventid']]['action_list'];
		if ($action_list['count']) {
			if ($action_list['has_fail_action']) {
				$icon_style = ZBX_STYLE_ACTIONS_NUM_RED;
			}
			elseif ($action_list['has_uncomplete_action']) {
				$icon_style = ZBX_STYLE_ACTIONS_NUM_YELLOW;
			}
			else {
				$icon_style = ZBX_STYLE_ACTIONS_NUM_GRAY;
			}

			$action_icons[] = makeActionIcon($icon_style, $action_list['table'], $action_list['count']);
		}

		// Create link to Problem update page.
		$problem_update_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'acknowledge.edit')
			->setArgument('eventids', [$event['eventid']])
			->setArgument('backurl', $backurl)
			->getUrl();

		$table->addRow([
			(new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']),
				'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid']
			))->addClass('action'),
			($event['r_eventid'] == 0)
				? ''
				: (new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['r_clock']),
						'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid']
				))->addClass('action'),
			$cell_status,
			zbx_date2age($event['clock']),
			$duration,
			(new CLink($event['acknowledged'] == EVENT_ACKNOWLEDGED ? _('Yes') : _('No'), $problem_update_url))
				->addClass($event['acknowledged'] == EVENT_ACKNOWLEDGED ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
				->addClass(ZBX_STYLE_LINK_ALT),
			$action_icons ? (new CCol($action_icons))->addClass(ZBX_STYLE_NOWRAP) : ''
		]);
	}

	return $table;
}

/**
 * Create table with trigger description and events.
 *
 * @param array  $trigger							An array of trigger data.
 * @param string $trigger['triggerid']				Trigger ID to select events.
 * @param string $trigger['description']			Trigger description.
 * @param string $trigger['url']					Trigger URL.
 * @param string $eventid_till
 * @param string $backurl							URL to return to.
 * @param bool   $fullscreen
 *
 * @return CDiv
 */
function make_popup_eventlist($trigger, $eventid_till, $backurl, $fullscreen = false) {
	// Show trigger description and URL.
	$div = new CDiv();

	if ($trigger['comments'] !== '') {
		$div->addItem(
			(new CDiv())
				->addItem(zbx_str2links($trigger['comments']))
				->addClass(ZBX_STYLE_OVERLAY_DESCR)
				->addStyle('max-width: 500px')
		);
	}

	if ($trigger['url'] !== '') {
		$trigger_url = CHtmlUrlValidator::validate($trigger['url'])
			? $trigger['url']
			: 'javascript: alert(\''._s('Provided URL "%1$s" is invalid.', zbx_jsvalue($trigger['url'], false, false)).
				'\');';

		$div->addItem(
			(new CDiv())
				->addItem(new CLink($trigger['url'], $trigger_url))
				->addClass(ZBX_STYLE_OVERLAY_DESCR_URL)
				->addStyle('max-width: 500px')
		);
	}

	$show_timeline = true;

	// indicator of sort field
	$sort_div = (new CSpan())->addClass(ZBX_STYLE_ARROW_DOWN);

	if ($show_timeline) {
		$header = [
			(new CColHeader([_('Time'), $sort_div]))->addClass(ZBX_STYLE_RIGHT),
			(new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH),
			(new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH)
		];
	}
	else {
		$header = [[_('Time'), $sort_div]];
	}

	// Select and show events.
	$table = (new CTableInfo())
		->setHeader(array_merge($header, [
			_('Recovery time'),
			_('Status'),
			_('Duration'),
			_('Ack'),
			_('Tags')
		]));

	if ($eventid_till != 0) {
		$problems = API::Event()->get([
			'output' => ['eventid', 'r_eventid', 'clock', 'ns', 'objectid', 'acknowledged'],
			'selectTags' => ['tag', 'value'],
			'select_acknowledges' => ['action'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'eventid_till' => $eventid_till,
			'objectids' => $trigger['triggerid'],
			'value' => TRIGGER_VALUE_TRUE,
			'sortfield' => ['eventid'],
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => ZBX_WIDGET_ROWS
		]);

		CArrayHelper::sort($problems, [
			['field' => 'clock', 'order' => ZBX_SORT_DOWN],
			['field' => 'ns', 'order' => ZBX_SORT_DOWN]
		]);

		$r_eventids = [];

		foreach ($problems as $problem) {
			$r_eventids[$problem['r_eventid']] = true;
		}
		unset($r_eventids[0]);

		$r_events = $r_eventids
			? API::Event()->get([
				'output' => ['clock', 'correlationid', 'userid'],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'eventids' => array_keys($r_eventids),
				'preservekeys' => true
			])
			: [];

		$today = strtotime('today');
		$last_clock = 0;

		if ($problems) {
			$tags = makeEventsTags($problems);
		}

		$url_details = (new CUrl('tr_events.php'))
			->setArgument('triggerid', '')
			->setArgument('eventid', '')
			->setArgument('fullscreen', $fullscreen ? '1' : null);

		foreach ($problems as $problem) {
			if (array_key_exists($problem['r_eventid'], $r_events)) {
				$problem['r_clock'] = $r_events[$problem['r_eventid']]['clock'];
				$problem['correlationid'] = $r_events[$problem['r_eventid']]['correlationid'];
				$problem['userid'] = $r_events[$problem['r_eventid']]['userid'];
			}
			else {
				$problem['r_clock'] = 0;
				$problem['correlationid'] = 0;
				$problem['userid'] = 0;
			}

			if ($problem['r_eventid'] != 0) {
				$value = TRIGGER_VALUE_FALSE;
				$value_str = _('RESOLVED');
				$value_clock = $problem['r_clock'];
			}
			else {
				$in_closing = false;

				foreach ($problem['acknowledges'] as $acknowledge) {
					if ($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) {
						$in_closing = true;
						break;
					}
				}

				$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
				$value_str = $in_closing ? _('CLOSING') : _('PROBLEM');
				$value_clock = $in_closing ? time() : $problem['clock'];
			}

			$url_details
				->setArgument('triggerid', $problem['objectid'])
				->setArgument('eventid', $problem['eventid']);

			$cell_clock = ($problem['clock'] >= $today)
				? zbx_date2str(TIME_FORMAT_SECONDS, $problem['clock'])
				: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']);
			$cell_clock = new CCol(new CLink($cell_clock, $url_details));
			if ($problem['r_eventid'] != 0) {
				$cell_r_clock = ($problem['r_clock'] >= $today)
					? zbx_date2str(TIME_FORMAT_SECONDS, $problem['r_clock'])
					: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock']);
				$cell_r_clock = (new CCol(new CLink($cell_r_clock, $url_details)))
					->addClass(ZBX_STYLE_NOWRAP)
					->addClass(ZBX_STYLE_RIGHT);
			}
			else {
				$cell_r_clock = '';
			}

			$cell_status = new CSpan($value_str);

			// add colors and blinking to span depending on configuration and trigger parameters
			addTriggerValueStyle($cell_status, $value, $value_clock, $problem['acknowledged'] == EVENT_ACKNOWLEDGED);

			if ($show_timeline) {
				if ($last_clock != 0) {
					CScreenProblem::addTimelineBreakpoint($table, $last_clock, $problem['clock'], ZBX_SORT_DOWN);
				}
				$last_clock = $problem['clock'];

				$row = [
					$cell_clock->addClass(ZBX_STYLE_TIMELINE_DATE),
					(new CCol())
						->addClass(ZBX_STYLE_TIMELINE_AXIS)
						->addClass(ZBX_STYLE_TIMELINE_DOT),
					(new CCol())->addClass(ZBX_STYLE_TIMELINE_TD)
				];
			}
			else {
				$row = [
					$cell_clock
						->addClass(ZBX_STYLE_NOWRAP)
						->addClass(ZBX_STYLE_RIGHT)
				];
			}

			// Create acknowledge link.
			$problem_update_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'acknowledge.edit')
				->setArgument('eventids', [$problem['eventid']])
				->setArgument('backurl', $backurl)
				->getUrl();

			$acknowledged = $problem['acknowledged'] == EVENT_ACKNOWLEDGED;
			$problem_update_link = (new CLink($acknowledged ? _('Yes') : _('No'), $problem_update_url))
				->addClass($acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
				->addClass(ZBX_STYLE_LINK_ALT);

			$table->addRow(array_merge($row, [
				$cell_r_clock,
				$cell_status,
				(new CCol(
					($problem['r_eventid'] != 0)
						? zbx_date2age($problem['clock'], $problem['r_clock'])
						: zbx_date2age($problem['clock'])
				))
					->addClass(ZBX_STYLE_NOWRAP),
				$problem_update_link,
				$tags[$problem['eventid']]
			]));
		}
	}

	$div->addItem($table);

	return $div;
}

/**
 * Create element with event acknowledges info.
 *
 * @param array  $events
 * @param int    $events[]['eventid']
 * @param int    $events[]['objectid']
 * @param array  $events[]['acknowledges']
 * @param string $events[]['acknowledges'][]['userid']
 * @param int    $events[]['acknowledges'][]['clock']
 * @param string $events[]['acknowledges'][]['message']
 * @param string $events[]['acknowledges'][]['action']
 * @param string $backurl  add url param to link with current page file name
 *
 * @return array
 */
function makeEventsAcknowledges($events, $backurl) {
	$db_users = [];
	$userids = [];

	foreach ($events as &$event) {
		foreach ($event['acknowledges'] as $acknowledge) {
			$userids[$acknowledge['userid']] = true;
		}
		CArrayHelper::sort($event['acknowledges'], [['field' => 'clock', 'order' => ZBX_SORT_DOWN]]);
	}
	unset($event);

	if ($userids) {
		$db_users = API::User()->get([
			'output' => ['alias', 'name', 'surname'],
			'userids' => array_keys($userids),
			'preservekeys' => true
		]);
	}

	$acknowledges = [];

	foreach ($events as $event) {
		$link = 'zabbix.php?action=acknowledge.edit&eventids[]='.$event['eventid'].'&backurl='.urlencode($backurl);

		if ($event['acknowledges']) {
			$hint = makeAcknowledgesTable(array_slice($event['acknowledges'], 0, ZBX_WIDGET_ROWS), $db_users);
			$ack = (new CLink(_('Yes'), $link))
				->setHint($hint, '', false)
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREEN);
			$acknowledges[$event['eventid']] = [$ack, CViewHelper::showNum(count($event['acknowledges']))];
		}
		else {
			$acknowledges[$event['eventid']] = (new CLink(_('No'), $link))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_RED);
		}
	}

	return $acknowledges;
}

/**
 * Get acknowledgement table.
 *
 * @param array  $acknowledges
 * @param string $acknowledges[]['userid']
 * @param int    $acknowledges[]['clock']
 * @param string $acknowledges[]['message']
 * @param string $acknowledges[]['action']
 *
 * @return CTableInfo
 */
function makeAcknowledgesTable($acknowledges, $users) {
	$table = (new CTableInfo())->setHeader([_('Time'), _('User'), _('Message'), _('User action')]);

	foreach ($acknowledges as $acknowledge) {
		$table->addRow([
			zbx_date2str(DATE_TIME_FORMAT_SECONDS, $acknowledge['clock']),
			array_key_exists($acknowledge['userid'], $users)
				? getUserFullname($users[$acknowledge['userid']])
				: _('Inaccessible user'),
			zbx_nl2br($acknowledge['message']),
			($acknowledge['action'] == ZBX_PROBLEM_UPDATE_CLOSE) ? _('Close problem') : ''
		]);
	}

	return $table;
}

/**
 * Place filter tags at the beginning of tags array.
 *
 * @param array  $event_tags
 * @param string $event_tags[]['tag']
 * @param string $event_tags[]['value']
 * @param array  $f_tags
 * @param int    $f_tags[<tag>][]['operator']
 * @param string $f_tags[<tag>][]['value']
 *
 * @return array
 */
function orderEventTags(array $event_tags, array $f_tags) {
	$first_tags = [];

	foreach ($event_tags as $i => $tag) {
		if (array_key_exists($tag['tag'], $f_tags)) {
			foreach ($f_tags[$tag['tag']] as $f_tag) {
				if (($f_tag['operator'] == TAG_OPERATOR_EQUAL && $tag['value'] === $f_tag['value'])
						|| ($f_tag['operator'] == TAG_OPERATOR_LIKE
							&& ($f_tag['value'] === '' || stripos($tag['value'], $f_tag['value']) !== false))) {
					$first_tags[] = $tag;
					unset($event_tags[$i]);
					break;
				}
			}
		}
	}

	return array_merge($first_tags, $event_tags);
}

/**
 * Create element with event tags.
 *
 * @param array  $events
 * @param string $events[]['eventid']
 * @param array  $events[]['tags']
 * @param string $events[]['tags']['tag']
 * @param string $events[]['tags']['value']
 * @param bool   $html
 * @param int    $list_tags_count
 * @param array  $filter_tags
 * @param string $filter_tags[]['tag']
 * @param int    $filter_tags[]['operator']
 * @param string $filter_tags[]['value']
 *
 * @return array
 */
function makeEventsTags(array $events, $html = true, $list_tags_count = EVENTS_LIST_TAGS_COUNT,
		array $filter_tags = []) {
	$tags = [];

	// Convert $filter_tags to a more usable format.
	$f_tags = [];
	foreach ($filter_tags as $filter_tag) {
		$f_tags[$filter_tag['tag']][] = [
			'operator' => $filter_tag['operator'],
			'value' => $filter_tag['value']
		];
	}

	foreach ($events as $event) {
		CArrayHelper::sort($event['tags'], ['tag', 'value']);

		$tags[$event['eventid']] = [];

		if ($html) {
			// Show first n tags and "..." with hint box if there are more.

			$event_tags = $f_tags ? orderEventTags($event['tags'], $f_tags) : $event['tags'];
			$tags_shown = array_slice($event_tags, 0, $list_tags_count);

			foreach ($tags_shown as $tag) {
				$value = $tag['tag'].(($tag['value'] === '') ? '' : ': '.$tag['value']);
				$tags[$event['eventid']][] = (new CSpan($value))
					->addClass(ZBX_STYLE_TAG)
					->setHint($value);
			}

			if (count($event['tags']) > count($tags_shown)) {
				// Display all tags in hint box.

				$hint_content = [];

				foreach ($event['tags'] as $tag) {
					$value = $tag['tag'].($tag['value'] === '' ? '' : ': '.$tag['value']);
					$hint_content[$event['eventid']][] = (new CSpan($value))
						->addClass(ZBX_STYLE_TAG)
						->setHint($value);
				}

				$tags[$event['eventid']][] = (new CSpan(
					(new CButton(null))
						->addClass(ZBX_STYLE_ICON_WZRD_ACTION)
						->setHint(new CDiv($hint_content), '', true, 'max-width: 500px')
					))->addClass(ZBX_STYLE_REL_CONTAINER);
			}
		}
		else {
			// Show all and uncut for CSV.

			foreach ($event['tags'] as $tag) {
				$value = $tag['tag'].(($tag['value'] === '') ? '' : ': '.$tag['value']);
				$tags[$event['eventid']][] = $value;
			}
		}
	}

	return $tags;
}

function getLastEvents($options) {
	if (!isset($options['limit'])) {
		$options['limit'] = 15;
	}

	$triggerOptions = [
		'output' => ['triggerid', 'priority'],
		'filter' => [],
		'skipDependent' => 1,
		'selectHosts' => ['hostid', 'name'],
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
		$events[$enum]['trigger']['description'] = $event['name'];
	}
	array_multisort($sortClock, SORT_DESC, $sortEvent, SORT_DESC, $events);

	return $events;
}
