<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
		EVENT_SOURCE_AUTOREGISTRATION => _('autoregistration'),
		EVENT_SOURCE_INTERNAL => _x('internal', 'event source'),
		EVENT_SOURCE_SERVICE => _('service')
	];

	if ($source === null) {
		return $sources;
	}

	return array_key_exists($source, $sources) ?  $sources[$source] : _('Unknown');
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
		EVENT_OBJECT_AUTOREGHOST => _('autoregistered host'),
		EVENT_OBJECT_ITEM => _('item'),
		EVENT_OBJECT_LLDRULE => _('low-level discovery rule'),
		EVENT_OBJECT_SERVICE => _('service')
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
 * @param string $event['objectid']                  Object ID.
 * @param string $event['correlationid']             OK Event correlation ID.
 * @param string $event['userid']                    User ID who generated the OK event.
 * @param string $event['name']                      Event name.
 * @param string $event['acknowledged']              State of acknowledgement.
 * @param array  $event['acknowledges']              List of problem updates.
 * @param string $event['acknowledges'][]['action']  Action performed in update.
 * @param CCOl   $event['opdata']                    Operational data with expanded macros.
 * @param string $event['comments']                  Trigger description with expanded macros.
 * @param array  $allowed                            An array of user role rules.
 * @param bool   $allowed['ui_correlation']          Whether user is allowed to visit event correlation page.
 * @param bool   $allowed['add_comments']            Whether user is allowed to add problems comments.
 * @param bool   $allowed['change_severity']         Whether user is allowed to change problems severity.
 * @param bool   $allowed['acknowledge']             Whether user is allowed to acknowledge problems.
 * @param bool   $allowed['close']                   Whether user is allowed to close problems.
 *
 * @return CTableInfo
 */
function make_event_details(array $event, array $allowed) {
	$can_be_closed = $allowed['close'];

	if ($event['r_eventid'] != 0) {
		$can_be_closed = false;
	}
	else {
		foreach ($event['acknowledges'] as $acknowledge) {
			if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
				$can_be_closed = false;
				break;
			}
		}
	}

	$is_acknowledged = ($event['acknowledged'] == EVENT_ACKNOWLEDGED);

	$table = (new CTableInfo())
		->addRow([
			_('Event'),
			(new CCol($event['name']))->addClass(ZBX_STYLE_WORDWRAP)
		])
		->addRow([
			_('Operational data'),
			$event['opdata']
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
			($allowed['add_comments'] || $allowed['change_severity'] || $allowed['acknowledge'] || $can_be_closed)
				? (new CLink($is_acknowledged ? _('Yes') : _('No')))
					->addClass($is_acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
					->addClass(ZBX_STYLE_LINK_ALT)
					->onClick('acknowledgePopUp('.json_encode(['eventids' => [$event['eventid']]]).', this);')
				: (new CSpan($is_acknowledged ? _('Yes') : _('No')))->addClass(
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
							->setArgument('action', 'correlation.edit')
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

	$tags = makeTags([$event]);

	$table
		->addRow([_('Tags'), $tags[$event['eventid']]])
		->addRow([_('Description'), (new CDiv(zbx_str2links($event['comments'])))]);

	return $table;
}

/**
 *
 * @param array  $startEvent                  An array of event data.
 * @param string $startEvent['eventid']       Event ID.
 * @param string $startEvent['objectid']      Object ID.
 * @param array  $allowed                     An array of user role rules.
 * @param bool   $allowed['add_comments']     Whether user is allowed to add problems comments.
 * @param bool   $allowed['change_severity']  Whether user is allowed to change problems severity.
 * @param bool   $allowed['acknowledge']      Whether user is allowed to acknowledge problems.
 * @param bool   $allowed['close']            Whether user is allowed to close problems.
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
			_('Ack'),
			_('Actions')
		]);

	$events = API::Event()->get([
		'output' => ['eventid', 'source', 'object', 'objectid', 'acknowledged', 'clock', 'ns', 'severity', 'r_eventid'],
		'select_acknowledges' => ['userid', 'clock', 'message', 'action', 'old_severity', 'new_severity'],
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
			'output' => ['priority'],
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

		if ($event['r_eventid'] != 0) {
			$value = TRIGGER_VALUE_FALSE;
			$value_str = _('RESOLVED');
			$value_clock = $event['r_clock'];
			$can_be_closed = false;
		}
		else {
			$in_closing = false;

			foreach ($event['acknowledges'] as $acknowledge) {
				if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
					$in_closing = true;
					$can_be_closed = false;
					break;
				}
			}

			$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
			$value_str = $in_closing ? _('CLOSING') : _('PROBLEM');
			$value_clock = $in_closing ? time() : $event['clock'];
		}

		$is_acknowledged = ($event['acknowledged'] == EVENT_ACKNOWLEDGED);
		$cell_status = new CSpan($value_str);

		/*
		 * Add colors to span depending on configuration and trigger parameters. No blinking added to status,
		 * since the page is not on autorefresh.
		 */
		addTriggerValueStyle($cell_status, $value, $value_clock, $is_acknowledged);

		// Create acknowledge link.
		$problem_update_link = ($allowed['add_comments'] || $allowed['change_severity'] || $allowed['acknowledge']
				|| $can_be_closed)
			? (new CLink($is_acknowledged ? _('Yes') : _('No')))
				->addClass($is_acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
				->addClass(ZBX_STYLE_LINK_ALT)
				->onClick('acknowledgePopUp('.json_encode(['eventids' => [$event['eventid']]]).', this);')
			: (new CSpan($is_acknowledged ? _('Yes') : _('No')))->addClass(
				$is_acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED
			);

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
			makeEventActionsIcons($event['eventid'], $actions['data'], $users)
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
 * Place priority tags at the beginning of tags array.
 *
 * @param array  $event_tags             An array of event tags.
 * @param string $event_tags[]['tag']    Tag name.
 * @param string $event_tags[]['value']  Tag value.
 * @param array  $priorities             An array of priority tag names.
 *
 * @return array
 */
function orderEventTagsByPriority(array $event_tags, array $priorities) {
	$first_tags = [];

	foreach ($priorities as $priority) {
		foreach ($event_tags as $i => $tag) {
			if ($tag['tag'] === $priority) {
				$first_tags[] = $tag;
				unset($event_tags[$i]);
			}
		}
	}

	return array_merge($first_tags, $event_tags);
}

/**
 * Create element with tags.
 *
 * @param array  $list
 * @param string $list[][$key]
 * @param array  $list[]['tags']
 * @param string $list[]['tags'][]['tag']
 * @param string $list[]['tags'][]['value']
 * @param bool   $html
 * @param string $key                        Name of tag source ID. Possible values:
 *                                            - 'eventid' - for events and problems (default);
 *                                            - 'hostid' - for hosts and host prototypes;
 *                                            - 'templateid' - for templates;
 *                                            - 'triggerid' - for triggers.
 * @param int    $list_tag_count             Maximum number of tags to display.
 * @param array  $filter_tags                An array of tag filtering data.
 * @param ?array $subfilter_tags             Array of selected sub-filter tags. Null when tags are not clickable.
 * @param array  $subfilter_tags[<tag>]
 * @param array  $subfilter_tags[<tag>][<value1>]
 * @param array  $subfilter_tags[<tag>][<value2>]
 * @param array  $subfilter_tags[<tag>][<value...>]
 * @param string $filter_tags[]['tag']
 * @param int    $filter_tags[]['operator']
 * @param string $filter_tags[]['value']
 * @param int    $tag_name_format            Tag name format. Possible values:
 *                                            - TAG_NAME_FULL (default);
 *                                            - TAG_NAME_SHORTENED;
 *                                            - TAG_NAME_NONE.
 * @param string $tag_priority               A list of comma-separated tag names.
 *
 * @return array
 */
function makeTags(array $list, bool $html = true, string $key = 'eventid', int $list_tag_count = ZBX_TAG_COUNT_DEFAULT,
		array $filter_tags = [], ?array $subfilter_tags = null, int $tag_name_format = TAG_NAME_FULL,
		string $tag_priority = ''): array {
	$tags = [];

	if ($html) {
		// Convert $filter_tags to a more usable format.

		$f_tags = [];

		foreach ($filter_tags as $tag) {
			$f_tags[$tag['tag']][] = [
				'operator' => $tag['operator'],
				'value' => $tag['value']
			];
		}
	}

	if ($tag_priority !== '') {
		$p_tags = explode(',', $tag_priority);
		$p_tags = array_map('trim', $p_tags);
	}

	foreach ($list as $element) {
		$tags[$element[$key]] = [];

		if (!$element['tags']) {
			continue;
		}

		CArrayHelper::sort($element['tags'], ['tag', 'value']);

		if ($html) {
			// Show first n tags and "..." with hint box if there are more.

			$e_tags = $f_tags ? orderEventTags($element['tags'], $f_tags) : $element['tags'];

			if ($tag_priority !== '') {
				$e_tags = orderEventTagsByPriority($e_tags, $p_tags);
			}

			$tags_shown = 0;

			foreach ($e_tags as $tag) {
				$value = getTagString($tag, $tag_name_format);

				if ($value !== '') {
					if ($subfilter_tags !== null
							&& !(array_key_exists($tag['tag'], $subfilter_tags)
								&& array_key_exists($tag['value'], $subfilter_tags[$tag['tag']]))) {
						$value = (new CLinkAction($value))->onClick(CHtml::encode(
							'view.setSubfilter('.json_encode(['subfilter_tags['.$tag['tag'].'][]', $tag['value']]).')'
						));
					}

					$tags[$element[$key]][] = (new CSpan($value))
						->addClass(ZBX_STYLE_TAG)
						->setHint(getTagString($tag));

					$tags_shown++;

					if ($tags_shown >= $list_tag_count) {
						break;
					}
				}
			}

			if (count($element['tags']) > $tags_shown) {
				// Display all tags in hint box.

				$hint_content = [];

				foreach ($element['tags'] as $tag) {
					$value = getTagString($tag);

					if ($subfilter_tags !== null
							&& !(array_key_exists($tag['tag'], $subfilter_tags)
								&& array_key_exists($tag['value'], $subfilter_tags[$tag['tag']]))) {
						$value = (new CLinkAction($value))->onClick(CHtml::encode(
							'view.setSubfilter('.json_encode(['subfilter_tags['.$tag['tag'].'][]', $tag['value']]).')'
						));
					}

					$hint_content[$element[$key]][] = (new CSpan($value))
						->addClass(ZBX_STYLE_TAG)
						->setHint($value);
				}

				$tags[$element[$key]][] = (new CButton(null))
					->addClass(ZBX_STYLE_ICON_WZRD_ACTION)
					->setHint($hint_content, ZBX_STYLE_HINTBOX_WRAP, true);
			}
		}
		else {
			// Show all and uncut for CSV.

			foreach ($element['tags'] as $tag) {
				$tags[$element[$key]][] = getTagString($tag);
			}
		}
	}

	return $tags;
}

/**
 * Returns tag name in selected format.
 *
 * @param array  $tag
 * @param string $tag['tag']
 * @param string $tag['value']
 * @param int    $tag_name_format  TAG_NAME_*
 *
 * @return string
 */
function getTagString(array $tag, $tag_name_format = TAG_NAME_FULL) {
	switch ($tag_name_format) {
		case TAG_NAME_NONE:
			return $tag['value'];

		case TAG_NAME_SHORTENED:
			return substr($tag['tag'], 0, 3).(($tag['value'] === '') ? '' : ': '.$tag['value']);

		default:
			return $tag['tag'].(($tag['value'] === '') ? '' : ': '.$tag['value']);
	}
}
