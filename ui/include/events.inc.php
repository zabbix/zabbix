<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Returns all supported event source-object pairs.
 */
function eventSourceObjects(): array {
	return [
		['source' => EVENT_SOURCE_TRIGGERS, 'object' => EVENT_OBJECT_TRIGGER],
		['source' => EVENT_SOURCE_DISCOVERY, 'object' => EVENT_OBJECT_DHOST],
		['source' => EVENT_SOURCE_DISCOVERY, 'object' => EVENT_OBJECT_DSERVICE],
		['source' => EVENT_SOURCE_AUTOREGISTRATION, 'object' => EVENT_OBJECT_AUTOREGHOST],
		['source' => EVENT_SOURCE_INTERNAL, 'object' => EVENT_OBJECT_TRIGGER],
		['source' => EVENT_SOURCE_INTERNAL, 'object' => EVENT_OBJECT_ITEM],
		['source' => EVENT_SOURCE_INTERNAL, 'object' => EVENT_OBJECT_LLDRULE],
		['source' => EVENT_SOURCE_SERVICE, 'object' => EVENT_OBJECT_SERVICE]
	];
}

function get_events_unacknowledged($db_element, $value_trigger = null, $value_event = null, $ack = false) {
	$elements = ['hosts' => [], 'hosts_groups' => [], 'triggers' => []];
	get_map_elements($db_element, $elements);

	if (empty($elements['hosts_groups']) && empty($elements['hosts']) && empty($elements['triggers'])) {
		return 0;
	}

	$options = [
		'output' => ['triggerid'],
		'monitored' => 1,
		'skipDependent' => 1,
		'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
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
 * @param array  $event                              An array of event data.
 * @param string $event['eventid']                   Event ID.
 * @param string $event['r_eventid']                 OK event ID.
 * @param string $event['cause_eventid']             Cause event ID.
 * @param string $event['correlationid']             OK Event correlation ID.
 * @param string $event['userid']                    User ID who generated the OK event.
 * @param string $event['name']                      Event name.
 * @param string $event['acknowledged']              State of acknowledgement.
 * @param CCOl   $event['opdata']                    Operational data with expanded macros.
 * @param string $event['comments']                  Trigger description with expanded macros.
 * @param array  $allowed                            An array of user role rules.
 * @param bool   $allowed['ui_correlation']          Whether user is allowed to visit event correlation page.
 *
 * @return CTableInfo
 */
function make_event_details(array $event, array $allowed) {
	$is_acknowledged = ($event['acknowledged'] == EVENT_ACKNOWLEDGED);

	$table = (new CTableInfo())
		->addRow([
			_('Event'),
			(new CCol($event['name']))->addClass(ZBX_STYLE_WORDBREAK)
		])
		->addRow([
			_('Operational data'),
			$event['opdata']->addClass(ZBX_STYLE_WORDBREAK)
		])
		->addRow([
			_('Severity'),
			CSeverityHelper::makeSeverityCell((int) $event['severity'])
		])
		->addRow([
			_('Time'),
			zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock'])
		])
		->addRow([
			_('Acknowledged'),
			(new CSpan($is_acknowledged ? _('Yes') : _('No')))->addClass(
				$is_acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED
			)
		]);

	if ($event['r_eventid'] != 0) {
		if ($event['correlationid'] != 0) {
			$correlations = API::Correlation()->get([
				'output' => ['correlationid', 'name'],
				'correlationids' => [$event['correlationid']]
			]);

			if ($correlations) {
				if ($allowed['ui_correlation']) {
					$correlation_name = (new CLink($correlations[0]['name'],
						(new CUrl('zabbix.php'))
							->setArgument('action', 'popup')
							->setArgument('popup', 'correlation.edit')
							->setArgument('correlationid', $correlations[0]['correlationid'])
							->getUrl()
					))->addClass(ZBX_STYLE_LINK_ALT);
				}
				else {
					$correlation_name = $correlations[0]['name'];
				}
			}
			else {
				$correlation_name = _('Event correlation rule');
			}

			$table->addRow([_('Resolved by'), $correlation_name]);
		}
		elseif ($event['userid'] != 0) {
			if ($event['userid'] == CWebUser::$data['userid']) {
				$table->addRow([_('Resolved by'), getUserFullname([
					'username' => CWebUser::$data['username'],
					'name' => CWebUser::$data['name'],
					'surname' => CWebUser::$data['surname']
				])]);
			}
			else {
				$user = API::User()->get([
					'output' => ['username', 'name', 'surname'],
					'userids' => [$event['userid']]
				]);

				if ($user) {
					$table->addRow([_('Resolved by'), getUserFullname($user[0])]);
				}
				else {
					$table->addRow([_('Resolved by'), _('Inaccessible user')]);
				}
			}
		}
		else {
			$table->addRow([_('Resolved by'), _('Trigger')]);
		}
	}

	$tags = CTagHelper::getTagsHtml([$event], ZBX_TAG_OBJECT_EVENT);

	$table
		->addRow([_('Tags'), (new CDiv($tags[$event['eventid']]))->addClass(ZBX_STYLE_TAGS_WRAPPER)])
		->addRow([_('Description'), (new CDiv(zbx_str2links($event['comments'])))->addClass(ZBX_STYLE_WORDBREAK)]);

	if ($event['cause_eventid'] == 0) {
		$table->addRow([_('Rank'), _('Cause')]);
	}
	else {
		$cause_event = API::Event()->get([
			'output' => ['name', 'objectid'],
			'eventids' => $event['cause_eventid']
		]);

		if ($cause_event) {
			$cause_event = reset($cause_event);

			$has_trigger = (bool) API::Trigger()->get([
				'output' => [],
				'triggerids' => [$cause_event['objectid']]
			]);

			$table->addRow([_('Rank'),
				[
					_('Symptom'),
					' (',
					$has_trigger
						? new CLink(
							$cause_event['name'],
							(new CUrl('tr_events.php'))
								->setArgument('triggerid', $cause_event['objectid'])
								->setArgument('eventid', $event['cause_eventid'])
						)
						: $cause_event['name'],
					')'
				]
			]);
		}
	}

	return $table;
}

/**
 * Check if event status is UPDATING depending on acknowledges task ID.
 *
 * @param bool   $in_closing                         True if problem is in CLOSING state.
 * @param array  $event                              Event data.
 * @param array  $event['acknowledges']              List of event acknowledges.
 * @param int    $event['acknowledges'][]['action']  Event action type.
 * @param string $event['acknowledges'][]['taskid']  Task ID.
 *
 * @return bool
 */
function isEventUpdating(bool $in_closing, array $event): bool {
	$in_updating = false;

	if (!$in_closing) {
		foreach ($event['acknowledges'] as $acknowledge) {
			if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE) ==
					ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE
					|| ($acknowledge['action'] & ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM) ==
					ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM) {

				// If currently is cause or symptom and there is an active task.
				if ($acknowledge['taskid'] != 0) {
					$in_updating = true;
					break;
				}
			}
		}
	}

	return $in_updating;
}

/**
 * Calculate and return a rank change icon depending on current event rank change. If event is currently a cause event
 * and it is undergoing a rank change, return cause event icon. If event is currently a symptom event and it is
 * undergoing a rank change, return symptom event icon. Icon can be displayed regardless if current status is in closing.
 *
 * @param array  $event                              Event data.
 * @param array  $event['acknowledges']              List of event acknowledges.
 * @param int    $event['acknowledges'][]['action']  Event action type.
 * @param string $event['acknowledges'][]['taskid']  Task ID.
 *
 * @return Ctag|null
 */
function getEventStatusUpdateIcon(array $event): ?Ctag {
	$icon = null;
	$icon_class = '';

	foreach ($event['acknowledges'] as $acknowledge) {
		// If currently is symptom and there is an active task to convert to cause, set icon style to cause.
		if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE) == ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE
				&& $acknowledge['taskid'] != 0) {
			$icon_class = ZBX_ICON_ARROW_RIGHT_TOP;
			break;
		}

		// If currently is cause and there is an active task to convert to symptom, set icon style to symptom.
		if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM) ==
				ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM && $acknowledge['taskid'] != 0) {
			$icon_class = ZBX_ICON_ARROW_TOP_RIGHT;
			break;
		}
	}

	if ($icon_class !== '') {
		$icon = (new CSpan())
			->addClass($icon_class)
			->addClass('js-blink')
			->setTitle(_('Updating'));
	}

	return $icon;
}

/**
 * Calculate and return event status string: PROBLEM, RESOLVED, CLOSING or UPDATING depending on acknowledges task ID.
 *
 * @param bool   $in_closing                         True if problem is in CLOSING state.
 * @param array  $event                              Event data.
 *
 * @return string
 */
function getEventStatusString(bool $in_closing, array $event): string {
	if ($event['r_eventid'] != 0) {
		$value_str = isEventUpdating($in_closing, $event) ? _('UPDATING') : _('RESOLVED');
	}
	else {
		if ($in_closing) {
			$value_str = _('CLOSING');
		}
		else {
			$value_str = isEventUpdating($in_closing, $event) ? _('UPDATING') : _('PROBLEM');
		}
	}

	return $value_str;
}

/**
 *
 * @param array  $startEvent                    An array of event data.
 * @param string $startEvent['eventid']         Event ID.
 * @param string $startEvent['objectid']        Object ID.
 * @param array  $allowed                       An array of user role rules.
 * @param bool   $allowed['add_comments']       Whether user is allowed to add problems comments.
 * @param bool   $allowed['change_severity']    Whether user is allowed to change problems severity.
 * @param bool   $allowed['acknowledge']        Whether user is allowed to acknowledge problems.
 * @param bool   $allowed['close']              Whether user is allowed to close problems.
 * @param bool   $allowed['suppress_problems']  Whether user is allowed to suppress/unsuppress problems.
 * @param bool   $allowed['rank_change']        Whether user is allowed to change problem ranking.
 *
 * @return CTableInfo
 */
function make_small_eventlist(array $startEvent, array $allowed) {
	$table = (new CTableInfo())
		->setHeader([
			_('Time'),
			_('Recovery time'),
			_('Status'),
			_('Age'),
			_('Duration'),
			_('Update'),
			_('Actions')
		]);

	$events = API::Event()->get([
		'output' => ['eventid', 'source', 'object', 'objectid', 'acknowledged', 'clock', 'ns', 'severity', 'r_eventid',
			'cause_eventid'
		],
		'selectAcknowledges' => ['userid', 'clock', 'message', 'action', 'old_severity', 'new_severity',
			'suppress_until', 'taskid'
		],
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
			'output' => ['clock'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'eventids' => array_keys($r_eventids),
			'preservekeys' => true
		])
		: [];

	$triggerids = [];
	foreach ($events as &$event) {
		$triggerids[] = $event['objectid'];

		$event['r_clock'] = array_key_exists($event['r_eventid'], $r_events)
			? $r_events[$event['r_eventid']]['clock']
			: 0;
	}
	unset($event);

	// Get trigger severities.
	$triggers = $triggerids
		? API::Trigger()->get([
			'output' => ['priority', 'manual_close'],
			'triggerids' => $triggerids,
			'preservekeys' => true
		])
		: [];

	$actions = getEventsActionsIconsData($events, $triggers);
	$users = API::User()->get([
		'output' => ['username', 'name', 'surname'],
		'userids' => array_keys($actions['userids']),
		'preservekeys' => true
	]);

	foreach ($events as $event) {
		$duration = ($event['r_eventid'] != 0)
			? zbx_date2age($event['clock'], $event['r_clock'])
			: zbx_date2age($event['clock']);

		$can_be_closed = $allowed['close'];
		$in_closing = false;

		if ($event['r_eventid'] != 0) {
			$value = TRIGGER_VALUE_FALSE;
			$value_clock = $event['r_clock'];
			$can_be_closed = false;
		}
		else {
			if (hasEventCloseAction($event['acknowledges'])) {
				$in_closing = true;
				$can_be_closed = false;
			}

			$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
			$value_clock = $in_closing ? time() : $event['clock'];
		}

		$value_str = getEventStatusString($in_closing, $event);
		$is_acknowledged = ($event['acknowledged'] == EVENT_ACKNOWLEDGED);
		$cell_status = new CSpan($value_str);

		if (isEventUpdating($in_closing, $event)) {
			$cell_status->addClass('js-blink');
		}

		/*
		 * Add colors to span depending on configuration and trigger parameters. No blinking added to status,
		 * since the page is not on autorefresh.
		 */
		addTriggerValueStyle($cell_status, $value, $value_clock, $is_acknowledged);

		$problem_update_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'popup')
			->setArgument('popup', 'acknowledge.edit')
			->setArgument('eventids[]', $event['eventid'])
			->getUrl();

		// Create acknowledge link.
		$problem_update_link = ($allowed['add_comments'] || $allowed['change_severity'] || $allowed['acknowledge']
				|| $can_be_closed || $allowed['suppress_problems'] || $allowed['rank_change'])
			? (new CLink(_('Update'), $problem_update_url))->addClass(ZBX_STYLE_LINK_ALT)
			: new CSpan(_('Update'));

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
			$problem_update_link,
			makeEventActionsIcons($event['eventid'], $actions['data'], $users, $is_acknowledged)
		]);
	}

	return $table;
}

/**
 * Checks if event is closed.
 *
 * @param array $event                              Event object.
 * @param array $event['r_eventid']                 OK event id. 0 if not resolved.
 * @param array $event['acknowledges']              List of problem updates.
 * @param int   $event['acknowledges'][]['action']  Action performed in update.
 *
 * @return bool
 */
function isEventClosed(array $event): bool {
	if ($event['r_eventid'] != 0) {
		return true;
	}
	else {
		return hasEventCloseAction($event['acknowledges']);
	}
}

/**
 * Checks if event has manual close action.
 *
 * @param array $event                              Event object.
 * @param array $event['acknowledges']              List of problem updates.
 * @param int   $event['acknowledges'][]['action']  Action performed in update.
 *
 * @return bool
 */
function hasEventCloseAction(array $acknowledges): bool {
	foreach ($acknowledges as $acknowledge) {
		if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
			// If at least one manual close update was found, event is closing.
			return true;
		}
	}

	return false;
}

/**
 * Returns true if event is suppressed and not unsuppressed after that.
 *
 * @param array $acknowledges
 * @param int   $acknowledges['action']
 * @param ?array $unsuppression_action   [OUT] Variable to store suppression action data.
 *
 * @return bool
 */
function isEventRecentlySuppressed(array $acknowledges, &$suppression_action = null): bool {
	CArrayHelper::sort($acknowledges, [['field' => 'clock', 'order' => ZBX_SORT_DOWN]]);

	foreach ($acknowledges as $ack) {
		if (!array_key_exists('suppress_until', $ack)) {
			continue;
		}

		if (($ack['action'] & ZBX_PROBLEM_UPDATE_UNSUPPRESS) == ZBX_PROBLEM_UPDATE_UNSUPPRESS) {
			return false;
		}
		elseif (($ack['action'] & ZBX_PROBLEM_UPDATE_SUPPRESS) == ZBX_PROBLEM_UPDATE_SUPPRESS) {
			if ($ack['suppress_until'] == ZBX_PROBLEM_SUPPRESS_TIME_INDEFINITE || $ack['suppress_until'] > time()) {
				$suppression_action = $ack;

				return true;
			}
			break;
		}
	}

	return false;
}

/**
 * Returns true if event is unsuppressed and not suppressed after that.
 *
 * @param array  $acknowledges
 * @param int    $acknowledges['action']
 * @param ?array $unsuppression_action   [OUT] Variable to store unsuppression action data.
 *
 * @return bool
 */
function isEventRecentlyUnsuppressed(array $acknowledges, &$unsuppression_action = null): bool {
	CArrayHelper::sort($acknowledges, [['field' => 'clock', 'order' => ZBX_SORT_DOWN]]);

	foreach ($acknowledges as $ack) {
		if (($ack['action'] & ZBX_PROBLEM_UPDATE_SUPPRESS) == ZBX_PROBLEM_UPDATE_SUPPRESS) {
			return false;
		}
		elseif (($ack['action'] & ZBX_PROBLEM_UPDATE_UNSUPPRESS) == ZBX_PROBLEM_UPDATE_UNSUPPRESS) {
			$unsuppression_action = $ack;
			return true;
		}
	}

	return false;
}

/**
 * Validate if the given events can change the rank by moving to a new cause. Linking a cause event with its symptoms
 * (or only cause or only symptoms) to another different cause or symptom is allowed and will switch to the new cause as
 * a result. Linking a cause to one of its own symptoms is also allowed and will simply switch the cause and symptom as
 * a result. Linking a symptom to same cause is not allowed and that event ID is skipped. Linking a symptom to symptom
 * of same cause is also not allowed and is skipped.
 *
 * @param array  $eventids        Array of event IDs that should be converted to symptom events.
 * @param string $cause_eventid   Event ID that will be the new cause ID for given $eventids.
 *
 * @return array                  Returns event IDs that are allowed to change rank.
 */
function validateEventRankChangeToSymptom(array $eventids, string $cause_eventid): array {
	$eventids = array_fill_keys($eventids, true);
	$all_eventids = $eventids;
	$all_eventids[$cause_eventid] = true;

	// Get all the events that were given in the request to check permissions.
	$events = API::Event()->get([
		'output' => ['eventid', 'cause_eventid'],
		'eventids' => array_keys($all_eventids),
		'preservekeys' => true
	]);

	// Early return. In case one of the events are missing, no rank change can occur.
	if (count($events) != count($all_eventids)) {
		return [];
	}

	// Early return. No matter if cause or symptom, source and destination cannot be the same.
	if (count($eventids) == 1 && bccomp(key($eventids), $cause_eventid) == 0) {
		return [];
	}

	$dst_event = $events[$cause_eventid];

	foreach (array_keys($eventids) as $eventid) {
		$event = $events[$eventid];

		// Given cause is being moved.
		if ($event['cause_eventid'] == 0) {
			// Destination is cause. Cause is moved to same cause. Skip this event ID.
			if ($dst_event['cause_eventid'] == 0 && bccomp($eventid, $dst_event['eventid']) == 0) {
				unset($eventids[$eventid]);
			}
		}
		// Given symptom is moved.
		else {
			// Destination is cause.
			if ($dst_event['cause_eventid'] == 0) {
				// Symptom current cause is the same as new cause. Skip this event ID.
				if (bccomp($event['cause_eventid'], $dst_event['eventid']) == 0) {
					unset($eventids[$eventid]);
				}
			}
			// Destination is symptom.
			else {
				// Symptom destination is self. Skip this Event ID.
				if (bccomp($eventid, $dst_event['eventid']) == 0) {
					unset($eventids[$eventid]);
				}

				// If given symptom cause is not also in the list, skip this Event ID.
				if (!array_key_exists($event['cause_eventid'], $eventids)) {
					unset($eventids[$eventid]);
				}
			}
		}
	}

	return array_keys($eventids);
}
