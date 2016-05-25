<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/events.inc.php';
require_once dirname(__FILE__).'/include/actions.inc.php';
require_once dirname(__FILE__).'/include/discovery.inc.php';
require_once dirname(__FILE__).'/include/html.inc.php';

if (hasRequest('csv_export')) {
	$csvExport = true;
	$csvRows = [];

	$page['type'] = detect_page_type(PAGE_TYPE_CSV);
	$page['file'] = 'zbx_events_export.csv';

	require_once dirname(__FILE__).'/include/func.inc.php';
}
else {
	$csvExport = false;

	$page['title'] = _('Latest events');
	$page['file'] = 'events.php';
	$page['scripts'] = ['class.calendar.js', 'gtlc.js'];
	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

	if (PAGE_TYPE_HTML == $page['type']) {
		define('ZBX_PAGE_DO_REFRESH', 1);
	}
}

require_once dirname(__FILE__).'/include/page_header.php';

$allow_discovery = check_right_on_discovery();
$allowed_sources[] = EVENT_SOURCE_TRIGGERS;
if ($allow_discovery) {
	$allowed_sources[] = EVENT_SOURCE_DISCOVERY;
}

//		VAR			TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'source'=>			[T_ZBX_INT, O_OPT, P_SYS,	IN($allowed_sources), null],
	'groupid'=>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'hostid'=>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'triggerid'=>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'period'=>			[T_ZBX_INT, O_OPT, null,	null,		null],
	'stime'=>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'load'=>			[T_ZBX_STR, O_OPT, P_SYS,	NULL,		null],
	'fullscreen'=>		[T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null],
	'csv_export'=>		[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst'=>		[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_set'=>		[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	// ajax
	'favobj'=>			[T_ZBX_STR, O_OPT, P_ACT,	null,		null],
	'favid'=>			[T_ZBX_INT, O_OPT, P_ACT,	null,		null]
];
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('groupid') && !API::HostGroup()->isReadable([getRequest('groupid')])) {
	access_deny();
}
if (getRequest('hostid') && !API::Host()->isReadable([getRequest('hostid')])) {
	access_deny();
}
if (getRequest('triggerid') && !API::Trigger()->isReadable([getRequest('triggerid')])) {
	access_deny();
}

/*
 * Ajax
 */
if (hasRequest('favobj')) {
	// saving fixed/dynamic setting to profile
	if ('timelinefixedperiod' == getRequest('favobj')) {
		if (hasRequest('favid')) {
			CProfile::update('web.events.timelinefixed', getRequest('favid'), PROFILE_TYPE_INT);
		}
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Filter
 */
if (hasRequest('filter_set')) {
	CProfile::update('web.events.filter.triggerid', getRequest('triggerid', 0), PROFILE_TYPE_ID);
}
elseif (hasRequest('filter_rst')) {
	DBStart();
	CProfile::delete('web.events.filter.triggerid');
	DBend();
}

$triggerId = CProfile::get('web.events.filter.triggerid', 0);

$source = ($triggerId != 0 && hasRequest('filter_set'))
	? EVENT_SOURCE_TRIGGERS
	: getRequest('source', CProfile::get('web.events.source', EVENT_SOURCE_TRIGGERS));

CProfile::update('web.events.source', $source, PROFILE_TYPE_INT);

// calculate stime and period
if ($csvExport) {
	$period = getRequest('period', ZBX_PERIOD_DEFAULT);

	if (hasRequest('stime')) {
		$stime = getRequest('stime');

		if (bccomp($stime + $period, date(TIMESTAMP_FORMAT, time())) == 1) {
			$stime = date(TIMESTAMP_FORMAT, time() - $period);
		}
	}
	else {
		$stime = date(TIMESTAMP_FORMAT, time() - $period);
	}
}
else {
	$sourceName = ($source == EVENT_OBJECT_TRIGGER) ? 'trigger' : 'discovery';

	if (hasRequest('period')) {
		$_REQUEST['period'] = getRequest('period', ZBX_PERIOD_DEFAULT);
		CProfile::update('web.events.'.$sourceName.'.period', getRequest('period'), PROFILE_TYPE_INT);
	}
	else {
		$_REQUEST['period'] = CProfile::get('web.events.'.$sourceName.'.period');
	}

	$period = navigation_bar_calc();
	$stime = getRequest('stime');
}

$from = zbxDateToTime($stime);
$till = $from + $period;

/*
 * Display
 */
if ($source == EVENT_SOURCE_TRIGGERS) {
	if ($triggerId != 0 && hasRequest('filter_set')) {
		$host = API::Host()->get([
			'output' => ['hostid'],
			'selectGroups' => ['groupid'],
			'triggerids' => [$triggerId],
			'limit' => 1
		]);

		$host = reset($host);
		$hostid = $host['hostid'];
		$group = reset($host['groups']);
		$groupid = $group['groupid'];
	}
	else {
		$groupid = getRequest('groupid');
		$hostid = getRequest('hostid');
	}

	$pageFilter = new CPageFilter([
		'groups' => [
			'monitored_hosts' => true,
			'with_monitored_triggers' => true
		],
		'hosts' => [
			'monitored_hosts' => true,
			'with_monitored_triggers' => true
		],
		'hostid' => $hostid,
		'groupid' => $groupid
	]);
}

if ($csvExport) {
	if (!hasRequest('hostid')) {
		$_REQUEST['hostid'] = 0;
	}
	if (!hasRequest('groupid')) {
		$_REQUEST['groupid'] = 0;
	}
}
else {
	if ($source == EVENT_SOURCE_TRIGGERS) {
		// try to find matching trigger when host is changed
		// use the host ID from the page filter since it may not be present in the request
		// if all hosts are selected, preserve the selected trigger
		if ($triggerId != 0 && $pageFilter->hostid != 0) {
			$old_triggers = API::Trigger()->get([
				'output' => ['description', 'expression'],
				'selectHosts' => ['hostid', 'host'],
				'triggerids' => [$triggerId]
			]);
			$old_trigger = reset($old_triggers);

			$old_trigger['hosts'] = zbx_toHash($old_trigger['hosts'], 'hostid');

			// if the trigger doesn't belong to the selected host - find a new one on that host
			if (!array_key_exists($pageFilter->hostid, $old_trigger['hosts'])) {
				$triggerId = 0;

				$old_expression = CMacrosResolverHelper::resolveTriggerExpression($old_trigger['expression']);

				$new_triggers = API::Trigger()->get([
					'output' => ['triggerid', 'description', 'expression'],
					'selectHosts' => ['hostid', 'host'],
					'filter' => ['description' => $old_trigger['description']],
					'hostids' => [$pageFilter->hostid]
				]);

				$new_triggers = CMacrosResolverHelper::resolveTriggerExpressions($new_triggers);

				foreach ($new_triggers as $new_trigger) {
					$new_trigger['hosts'] = zbx_toHash($new_trigger['hosts'], 'hostid');

					foreach ($old_trigger['hosts'] as $old_host) {
						$new_expression = triggerExpressionReplaceHost($new_trigger['expression'],
							$new_trigger['hosts'][$pageFilter->hostid]['host'], $old_host['host']
						);

						if ($old_expression === $new_expression) {
							CProfile::update('web.events.filter.triggerid', $new_trigger['triggerid'], PROFILE_TYPE_ID);
							$triggerId = $new_trigger['triggerid'];
							break 2;
						}
					}
				}
			}
		}
	}

	$eventsWidget = (new CWidget())->setTitle(_('Events'));

	// header
	$frmForm = (new CForm('get'))
		->addVar('stime', $stime, 'stime_csv')
		->addVar('period', $period, 'period_csv')
		->addVar('page', getPageNumber(), 'page_csv');
	if (hasRequest('source')) {
		$frmForm->addVar('source', getRequest('source'), 'source_csv');
	}

	if ($source == EVENT_SOURCE_TRIGGERS) {
		$frmForm->addVar('groupid', $pageFilter->groupid, 'groupid_csv');
		$frmForm->addVar('hostid', $pageFilter->hostid, 'hostid_csv');

		if ($triggerId) {
			$frmForm->addVar('triggerid', $triggerId, 'triggerid_csv');
		}
	}

	$frmForm->addVar('fullscreen', getRequest('fullscreen'));
	$frmForm->addVar('stime', $stime);
	$frmForm->addVar('period', $period);

	$controls = new CList();

	// add host and group filters to the form
	if ($source == EVENT_SOURCE_TRIGGERS) {
		if (getRequest('triggerid') != 0) {
			$frmForm->addVar('triggerid', getRequest('triggerid'), 'triggerid_filter');
		}

		$controls->addItem([_('Group'), SPACE, $pageFilter->getGroupsCB()]);
		$controls->addItem([_('Host'), SPACE, $pageFilter->getHostsCB()]);
	}

	if ($allow_discovery) {
		$controls->addItem([_('Source'), SPACE, new CComboBox('source', $source, 'submit()', [
			EVENT_SOURCE_TRIGGERS => _('Trigger'),
			EVENT_SOURCE_DISCOVERY => _('Discovery')
		])]);
	}

	$controls->addItem(new CSubmit('csv_export', _('Export to CSV')));
	$controls->addItem(get_icon('fullscreen', ['fullscreen' => getRequest('fullscreen')]));

	$frmForm->addItem($controls);
	$eventsWidget->setControls($frmForm);

	$filterForm = (new CFilter('web.events.filter.state'))
		->addVar('fullscreen', getRequest('fullscreen'));

	if ($source == EVENT_SOURCE_TRIGGERS) {
		$filterForm->addVar('triggerid', $triggerId)
			->addVar('stime', $stime)
			->addVar('period', $period);
		$filterForm->addVar('groupid', $pageFilter->groupid);
		$filterForm->addVar('hostid', $pageFilter->hostid);

		if ($triggerId > 0) {
			$dbTrigger = API::Trigger()->get([
				'triggerids' => $triggerId,
				'output' => ['description', 'expression'],
				'selectHosts' => ['name'],
				'preservekeys' => true,
				'expandDescription' => true
			]);
			if ($dbTrigger) {
				$dbTrigger = reset($dbTrigger);
				$host = reset($dbTrigger['hosts']);

				$trigger = $host['name'].NAME_DELIMITER.$dbTrigger['description'];
			}
			else {
				$triggerId = 0;
			}
		}
		if (!isset($trigger)) {
			$trigger = '';
		}

		$filterColumn = new CFormList();

		$filterColumn->addRow(
			_('Trigger'),
			[
				(new CTextBox('trigger', $trigger, true))->setWidth(ZBX_TEXTAREA_FILTER_BIG_WIDTH),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('btn1', _('Select')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick('return PopUp("popup.php?'.
						'dstfrm=zbx_filter'.
						'&dstfld1=triggerid'.
						'&dstfld2=trigger'.
						'&srctbl=triggers'.
						'&srcfld1=triggerid'.
						'&srcfld2=description'.
						'&real_hosts=1'.
						'&monitored_hosts=1'.
						'&with_monitored_triggers=1'.
						($pageFilter->hostid ? '&only_hostid='.$pageFilter->hostid : '').
						'");'
					)
			]
		);

		$filterForm->addColumn($filterColumn);
	}

	$filterForm->addNavigator();

	$eventsWidget->addItem($filterForm);

	$table = new CTableInfo();
}

// trigger events
if ($source == EVENT_OBJECT_TRIGGER) {
	$firstEvent = API::Event()->get([
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'output' => API_OUTPUT_EXTEND,
		'objectids' => $triggerId ? $triggerId : null,
		'sortfield' => ['clock'],
		'sortorder' => ZBX_SORT_UP,
		'limit' => 1
	]);
	$firstEvent = reset($firstEvent);
}

// discovery events
else {
	$firstEvent = API::Event()->get([
		'output' => API_OUTPUT_EXTEND,
		'source' => EVENT_SOURCE_DISCOVERY,
		'object' => EVENT_OBJECT_DHOST,
		'sortfield' => ['clock'],
		'sortorder' => ZBX_SORT_UP,
		'limit' => 1
	]);
	$firstEvent = reset($firstEvent);

	$firstDServiceEvent = API::Event()->get([
		'output' => API_OUTPUT_EXTEND,
		'source' => EVENT_SOURCE_DISCOVERY,
		'object' => EVENT_OBJECT_DSERVICE,
		'sortfield' => ['clock'],
		'sortorder' => ZBX_SORT_UP,
		'limit' => 1
	]);
	$firstDServiceEvent = reset($firstDServiceEvent);

	if ($firstDServiceEvent && (!$firstEvent || $firstDServiceEvent['eventid'] < $firstEvent['eventid'])) {
		$firstEvent = $firstDServiceEvent;
	}
}

$config = select_config();

// headers
if ($source == EVENT_SOURCE_DISCOVERY) {
	$header = [
		_('Time'),
		_('IP'),
		_('DNS'),
		_('Description'),
		_('Status')
	];

	if ($csvExport) {
		$csvRows[] = $header;
	}
	else {
		$table->setHeader($header);
	}
}
else {
	$header = [
		_('Time'),
		($pageFilter->hostid == 0) ? _('Host') : null,
		_('Description'),
		_('Status'),
		_('Severity'),
		_('Duration'),
		$config['event_ack_enable'] ? _('Ack') : null,
		_('Actions')
	];

	if ($csvExport) {
		$csvRows[] = $header;
	}
	else {
		$table->setHeader($header);
	}
}

if (!$firstEvent) {
	$starttime = null;

	$url = (new CUrl('events.php'))
		->setArgument('fullscreen', getRequest('fullscreen'));

	if (!$csvExport) {
		$events = [];
		$paging = getPagingLine($events, ZBX_SORT_UP, $url);
	}
}
else {
	$starttime = $firstEvent['clock'];

	if ($source == EVENT_SOURCE_DISCOVERY) {
		// fetch discovered service and discovered host events separately
		$dHostEvents = API::Event()->get([
			'source' => EVENT_SOURCE_DISCOVERY,
			'object' => EVENT_OBJECT_DHOST,
			'time_from' => $from,
			'time_till' => $till,
			'output' => ['eventid', 'object', 'objectid', 'clock', 'value'],
			'sortfield' => ['clock', 'eventid'],
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => $config['search_limit'] + 1
		]);
		$dServiceEvents = API::Event()->get([
			'source' => EVENT_SOURCE_DISCOVERY,
			'object' => EVENT_OBJECT_DSERVICE,
			'time_from' => $from,
			'time_till' => $till,
			'output' => ['eventid', 'object', 'objectid', 'clock', 'value'],
			'sortfield' => ['clock', 'eventid'],
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => $config['search_limit'] + 1
		]);
		$dsc_events = array_merge($dHostEvents, $dServiceEvents);
		CArrayHelper::sort($dsc_events, [
			['field' => 'clock', 'order' => ZBX_SORT_DOWN],
			['field' => 'eventid', 'order' => ZBX_SORT_DOWN]
		]);
		$dsc_events = array_slice($dsc_events, 0, $config['search_limit'] + 1);

		$url = (new CUrl('events.php'))
			->setArgument('fullscreen', getRequest('fullscreen'));

		$paging = getPagingLine($dsc_events, ZBX_SORT_DOWN, $url);

		$objectids = [];
		foreach ($dsc_events as $event_data) {
			$objectids[$event_data['objectid']] = $event_data['objectid'];
		}

		// object dhost
		$dhosts = [];
		$res = DBselect(
			'SELECT s.dserviceid,s.dhostid,s.ip,s.dns'.
			' FROM dservices s'.
			' WHERE '.dbConditionInt('s.dhostid', $objectids)
		);
		while ($dservices = DBfetch($res)) {
			$dhosts[$dservices['dhostid']] = $dservices;
		}

		// object dservice
		$dservices = [];
		$res = DBselect(
			'SELECT s.dserviceid,s.ip,s.dns,s.type,s.port'.
			' FROM dservices s'.
			' WHERE '.dbConditionInt('s.dserviceid', $objectids)
		);
		while ($dservice = DBfetch($res)) {
			$dservices[$dservice['dserviceid']] = $dservice;
		}

		foreach ($dsc_events as $event_data) {
			switch ($event_data['object']) {
				case EVENT_OBJECT_DHOST:
					if (isset($dhosts[$event_data['objectid']])) {
						$event_data['object_data'] = $dhosts[$event_data['objectid']];
					}
					else {
						$event_data['object_data']['ip'] = _('Unknown');
						$event_data['object_data']['dns'] = _('Unknown');
					}
					$event_data['description'] = _('Host');
					break;

				case EVENT_OBJECT_DSERVICE:
					if (isset($dservices[$event_data['objectid']])) {
						$event_data['object_data'] = $dservices[$event_data['objectid']];
					}
					else {
						$event_data['object_data']['ip'] = _('Unknown');
						$event_data['object_data']['dns'] = _('Unknown');
						$event_data['object_data']['type'] = _('Unknown');
						$event_data['object_data']['port'] = _('Unknown');
					}

					$event_data['description'] = _('Service').NAME_DELIMITER.
							discovery_check_type2str($event_data['object_data']['type']).
							discovery_port2str($event_data['object_data']['type'], $event_data['object_data']['port']);
					break;

				default:
					continue;
			}

			if (!isset($event_data['object_data'])) {
				continue;
			}

			if ($csvExport) {
				$csvRows[] = [
					zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event_data['clock']),
					$event_data['object_data']['ip'],
					$event_data['object_data']['dns'],
					$event_data['description'],
					discovery_value($event_data['value'])
				];
			}
			else {
				$table->addRow([
					zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event_data['clock']),
					$event_data['object_data']['ip'],
					zbx_empty($event_data['object_data']['dns']) ? SPACE : $event_data['object_data']['dns'],
					$event_data['description'],
					(new CCol(discovery_value($event_data['value'])))->addClass(discovery_value_style($event_data['value']))
				]);
			}
		}
	}

	// source not discovery i.e. trigger
	else {
		if ($csvExport || $pageFilter->hostsSelected || $triggerId != 0) {
			$knownTriggerIds = [];
			$validTriggerIds = [];

			$triggerOptions = [
				'output' => ['triggerid'],
				'preservekeys' => true,
				'monitored' => true
			];

			$allEventsSliceLimit = $config['search_limit'];

			$eventOptions = [
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'time_from' => $from,
				'time_till' => $till,
				'output' => ['eventid', 'objectid'],
				'sortfield' => ['clock', 'eventid'],
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => $allEventsSliceLimit + 1
			];

			if ($triggerId) {
				$knownTriggerIds = [$triggerId => $triggerId];
				$validTriggerIds = $knownTriggerIds;

				$eventOptions['objectids'] = [$triggerId];
			}
			elseif ($pageFilter->hostid > 0) {
				$hostTriggers = API::Trigger()->get([
					'output' => ['triggerid'],
					'hostids' => $pageFilter->hostid,
					'monitored' => true,
					'preservekeys' => true
				]);
				$filterTriggerIds = array_map('strval', array_keys($hostTriggers));
				$knownTriggerIds = array_combine($filterTriggerIds, $filterTriggerIds);
				$validTriggerIds = $knownTriggerIds;

				$eventOptions['hostids'] = $pageFilter->hostid;
				$eventOptions['objectids'] = $validTriggerIds;
			}
			elseif ($pageFilter->groupid > 0) {
				$eventOptions['groupids'] = $pageFilter->groupid;

				$triggerOptions['groupids'] = $pageFilter->groupid;
			}

			$events = [];

			while (true) {
				$allEventsSlice = API::Event()->get($eventOptions);

				$triggerIdsFromSlice = array_keys(array_flip(zbx_objectValues($allEventsSlice, 'objectid')));

				$unknownTriggerIds = array_diff($triggerIdsFromSlice, $knownTriggerIds);

				if ($unknownTriggerIds) {
					$triggerOptions['triggerids'] = $unknownTriggerIds;
					$validTriggersFromSlice = API::Trigger()->get($triggerOptions);

					foreach ($validTriggersFromSlice as $trigger) {
						$validTriggerIds[$trigger['triggerid']] = $trigger['triggerid'];
					}

					foreach ($unknownTriggerIds as $id) {
						$id = strval($id);
						$knownTriggerIds[$id] = $id;
					}
				}

				foreach ($allEventsSlice as $event) {
					if (isset($validTriggerIds[$event['objectid']])) {
						$events[] = ['eventid' => $event['eventid']];
					}
				}

				// break loop when either enough events have been retrieved, or last slice was not full
				if (count($events) >= $config['search_limit'] || count($allEventsSlice) <= $allEventsSliceLimit) {
					break;
				}

				/*
				 * Because events in slices are sorted descending by eventid (i.e. bigger eventid),
				 * first event in next slice must have eventid that is previous to last eventid in current slice.
				 */
				$lastEvent = end($allEventsSlice);
				$eventOptions['eventid_till'] = $lastEvent['eventid'] - 1;
			}

			/*
			 * At this point it is possible that more than $config['search_limit'] events are selected,
			 * therefore at most only first $config['search_limit'] + 1 events will be used for pagination.
			 */
			$events = array_slice($events, 0, $config['search_limit'] + 1);

			// get paging
			$url = (new CUrl('events.php'))
				->setArgument('fullscreen', getRequest('fullscreen'))
				->setArgument('groupid', $pageFilter->groupid)
				->setArgument('hostid', $pageFilter->hostid);

			$paging = getPagingLine($events, ZBX_SORT_DOWN, $url);

			// query event with extend data
			$events = API::Event()->get([
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'eventids' => zbx_objectValues($events, 'eventid'),
				'output' => API_OUTPUT_EXTEND,
				'select_acknowledges' => API_OUTPUT_COUNT,
				'sortfield' => ['clock', 'eventid'],
				'sortorder' => ZBX_SORT_DOWN,
				'nopermissions' => true
			]);

			$triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description', 'expression', 'priority', 'flags', 'url'],
				'selectHosts' => ['hostid', 'name', 'status'],
				'selectItems' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
				'triggerids' => zbx_objectValues($events, 'objectid'),
				'preservekeys' => true
			]);

			$triggers = CMacrosResolverHelper::resolveTriggerUrls($triggers);

			// fetch hosts
			$hosts = [];
			foreach ($triggers as &$trigger) {
				$hosts[] = reset($trigger['hosts']);

				// Add already filtered read and read-write 'groupid' and 'hostid' to pass to menu pop-up "Events" link.
				$trigger['groupid'] = $pageFilter->groupid;
				$trigger['hostid'] = $pageFilter->hostid;
			}
			unset($trigger);

			$hostids = zbx_objectValues($hosts, 'hostid');

			$hosts = API::Host()->get([
				'output' => ['name', 'hostid', 'status'],
				'hostids' => $hostids,
				'selectGraphs' => API_OUTPUT_COUNT,
				'selectScreens' => API_OUTPUT_COUNT,
				'preservekeys' => true
			]);

			// fetch scripts for the host JS menu
			if (!$csvExport && $pageFilter->hostid == 0) {
				$scripts = API::Script()->getScriptsByHosts($hostids);
			}

			// actions
			$actions = makeEventsActions(zbx_objectValues($events, 'eventid'));

			// events
			foreach ($events as $event) {
				$trigger = $triggers[$event['objectid']];

				$host = reset($trigger['hosts']);
				$host = $hosts[$host['hostid']];

				$description = CMacrosResolverHelper::resolveEventDescription(zbx_array_merge($trigger, [
					'clock' => $event['clock'],
					'ns' => $event['ns']
				]));

				// duration
				$event['duration'] = ($nextEvent = get_next_event($event, $events))
					? zbx_date2age($event['clock'], $nextEvent['clock'])
					: zbx_date2age($event['clock']);

				// action
				$action = isset($actions[$event['eventid']]) ? $actions[$event['eventid']] : '';

				if ($csvExport) {
					$csvRows[] = [
						zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']),
						($pageFilter->hostid == 0) ? $host['name'] : null,
						$description,
						trigger_value2str($event['value']),
						getSeverityName($trigger['priority'], $config),
						$event['duration'],
						$config['event_ack_enable'] ? ($event['acknowledges'] ? _('Yes') : _('No')) : null,
						strip_tags((string) $action)
					];
				}
				else {
					$triggerDescription = (new CSpan($description))
						->addClass(ZBX_STYLE_LINK_ACTION)
						->setMenuPopup(
							CMenuPopupHelper::getTrigger($trigger, null, $event['clock'])
						);

					// add colors and blinking to span depending on configuration and trigger parameters
					$statusSpan = new CSpan(trigger_value2str($event['value']));

					addTriggerValueStyle(
						$statusSpan,
						$event['value'],
						$event['clock'],
						$event['acknowledged']
					);

					// host JS menu link
					$hostName = null;

					if ($pageFilter->hostid == 0) {
						$hostName = (new CSpan($host['name']))
							->addClass(ZBX_STYLE_LINK_ACTION)
							->setMenuPopup(CMenuPopupHelper::getHost($host, $scripts[$host['hostid']]));
					}

					$table->addRow([
						(new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']),
								'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid']))
							->addClass('action'),
						$hostName,
						$triggerDescription,
						$statusSpan,
						getSeverityCell($trigger['priority'], $config, null, !$event['value']),
						$event['duration'],
						$config['event_ack_enable'] ? getEventAckState($event, $page['file']) : null,
						(new CCol($action))->addClass(ZBX_STYLE_NOWRAP)
					]);
				}
			}
		}
		else {
			if (!$csvExport) {
				$events = [];

				$url = (new CUrl('events.php'))
					->setArgument('fullscreen', getRequest('fullscreen'))
					->setArgument('groupid', $pageFilter->groupid)
					->setArgument('hostid', $pageFilter->hostid);

				$paging = getPagingLine($events, ZBX_SORT_UP, $url);
			}
		}
	}

	if (!$csvExport) {
		$table = [$table, $paging];
	}
}

if ($csvExport) {
	echo zbx_toCSV($csvRows);
}
else {
	$eventsWidget->addItem($table);

	$timeline = [
		'period' => $period,
		'starttime' => date(TIMESTAMP_FORMAT, $starttime),
		'usertime' => date(TIMESTAMP_FORMAT, $till)
	];

	$objData = [
		'id' => 'timeline_1',
		'loadSBox' => 0,
		'loadImage' => 0,
		'loadScroll' => 1,
		'dynamic' => 0,
		'mainObject' => 1,
		'periodFixed' => CProfile::get('web.events.timelinefixed', 1),
		'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
	];

	zbx_add_post_js('jqBlink.blink();');
	zbx_add_post_js('timeControl.addObject("scroll_events_id", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($objData).');');
	zbx_add_post_js('timeControl.processObjects();');

	$eventsWidget->show();

	require_once dirname(__FILE__).'/include/page_footer.php';
}
