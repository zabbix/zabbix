<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
	$csvRows = array();

	$page['type'] = detect_page_type(PAGE_TYPE_CSV);
	$page['file'] = 'zbx_events_export.csv';

	require_once dirname(__FILE__).'/include/func.inc.php';
}
else {
	$csvExport = false;

	$page['title'] = _('Latest events');
	$page['file'] = 'events.php';
	$page['hist_arg'] = array('groupid', 'hostid');
	$page['scripts'] = array('class.calendar.js', 'gtlc.js');
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
$fields = array(
	'source'=>			array(T_ZBX_INT, O_OPT, P_SYS,	IN($allowed_sources), null),
	'groupid'=>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'hostid'=>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'triggerid'=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'period'=>			array(T_ZBX_INT, O_OPT, null,	null,		null),
	'stime'=>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'load'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,		null),
	'fullscreen'=>		array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	'csv_export'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'filter_rst'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'filter_set'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	// ajax
	'filterState' =>	array(T_ZBX_INT, O_OPT, P_ACT,	null,		null),
	'favobj'=>			array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favid'=>			array(T_ZBX_INT, O_OPT, P_ACT,	null,		null)
);
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('groupid') && !API::HostGroup()->isReadable(array(getRequest('groupid')))) {
	access_deny();
}
if (getRequest('hostid') && !API::Host()->isReadable(array(getRequest('hostid')))) {
	access_deny();
}
if (getRequest('triggerid') && !API::Trigger()->isReadable(array(getRequest('triggerid')))) {
	access_deny();
}

/*
 * Ajax
 */
if (hasRequest('filterState')) {
	CProfile::update('web.events.filter.state', getRequest('filterState'), PROFILE_TYPE_INT);
}
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

$source = getRequest('source', CProfile::get('web.events.source', EVENT_SOURCE_TRIGGERS));

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

CProfile::update('web.events.source', $source, PROFILE_TYPE_INT);

// calculate stime and period
if ($csvExport) {
	$period = getRequest('period', ZBX_PERIOD_DEFAULT);

	if (hasRequest('stime')) {
		$stime = getRequest('stime');

		if ($stime + $period > time()) {
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
		$pageFilter = new CPageFilter(array(
			'groups' => array(
				'monitored_hosts' => true,
				'with_monitored_triggers' => true
			),
			'hosts' => array(
				'monitored_hosts' => true,
				'with_monitored_triggers' => true
			),
			'hostid' => getRequest('hostid'),
			'groupid' => getRequest('groupid')
		));

		// try to find matching trigger when host is changed
		// use the host ID from the page filter since it may not be present in the request
		// if all hosts are selected, preserve the selected trigger
		if ($triggerId != 0 && $pageFilter->hostid != 0) {
			$hostId = $pageFilter->hostid;

			$oldTriggers = API::Trigger()->get(array(
				'output' => array('triggerid', 'description', 'expression'),
				'selectHosts' => array('hostid', 'host'),
				'selectItems' => array('itemid', 'hostid', 'key_', 'type', 'flags', 'status'),
				'selectFunctions' => API_OUTPUT_EXTEND,
				'triggerids' => $triggerId
			));
			$oldTrigger = reset($oldTriggers);

			$oldTrigger['hosts'] = zbx_toHash($oldTrigger['hosts'], 'hostid');

			// if the trigger doesn't belong to the selected host - find a new one on that host
			if (!isset($oldTrigger['hosts'][$hostId])) {
				$triggerId = 0;

				$oldTrigger['items'] = zbx_toHash($oldTrigger['items'], 'itemid');
				$oldTrigger['functions'] = zbx_toHash($oldTrigger['functions'], 'functionid');
				$oldExpression = triggerExpression($oldTrigger);

				$newTriggers = API::Trigger()->get(array(
					'output' => array('triggerid', 'description', 'expression'),
					'selectHosts' => array('hostid', 'host'),
					'selectItems' => array('itemid', 'key_'),
					'selectFunctions' => API_OUTPUT_EXTEND,
					'filter' => array('description' => $oldTrigger['description']),
					'hostids' => $hostId
				));

				foreach ($newTriggers as $newTrigger) {
					if (count($oldTrigger['items']) != count($newTrigger['items'])) {
						continue;
					}

					$newTrigger['items'] = zbx_toHash($newTrigger['items'], 'itemid');
					$newTrigger['hosts'] = zbx_toHash($newTrigger['hosts'], 'hostid');
					$newTrigger['functions'] = zbx_toHash($newTrigger['functions'], 'functionid');

					$found = false;
					foreach ($newTrigger['functions'] as $fnum => $function) {
						foreach ($oldTrigger['functions'] as $ofnum => $oldFunction) {
							// compare functions
							if (($function['function'] != $oldFunction['function']) || ($function['parameter'] != $oldFunction['parameter'])) {
								continue;
							}
							// compare that functions uses same item keys
							if ($newTrigger['items'][$function['itemid']]['key_'] != $oldTrigger['items'][$oldFunction['itemid']]['key_']) {
								continue;
							}
							// rewrite itemid so we could compare expressions
							// of two triggers form different hosts
							$newTrigger['functions'][$fnum]['itemid'] = $oldFunction['itemid'];
							$found = true;

							unset($oldTrigger['functions'][$ofnum]);
							break;
						}
						if (!$found) {
							break;
						}
					}
					if (!$found) {
						continue;
					}

					// if we found same trigger we overwriting it's hosts and items for expression compare
					$newTrigger['hosts'] = $oldTrigger['hosts'];
					$newTrigger['items'] = $oldTrigger['items'];

					$newExpression = triggerExpression($newTrigger);

					if (strcmp($oldExpression, $newExpression) == 0) {
						CProfile::update('web.events.filter.triggerid', $newTrigger['triggerid'], PROFILE_TYPE_ID);
						$triggerId = $newTrigger['triggerid'];
						break;
					}
				}
			}
		}
	}

	$eventsWidget = new CWidget();

	$csvDisabled = true;

	// header
	$frmForm = new CForm();
	if (hasRequest('source')) {
		$frmForm->addVar('source', getRequest('source'), 'source_csv');
	}
	$frmForm->addVar('stime', $stime, 'stime_csv');
	$frmForm->addVar('period', $period, 'period_csv');
	$frmForm->addVar('page', getPageNumber(), 'page_csv');

	if ($source == EVENT_SOURCE_TRIGGERS) {
		if ($triggerId) {
			$frmForm->addVar('triggerid', $triggerId, 'triggerid_csv');
		}
		else {
			$frmForm->addVar('groupid', getRequest('groupid'), 'groupid_csv');
			$frmForm->addVar('hostid', getRequest('hostid'), 'hostid_csv');
		}
	}
	$frmForm->addItem(new CSubmit('csv_export', _('Export to CSV')));

	$eventsWidget->addPageHeader(
		_('HISTORY OF EVENTS').SPACE.'['.zbx_date2str(DATE_TIME_FORMAT_SECONDS).']',
		array(
			$frmForm,
			SPACE,
			get_icon('fullscreen', array('fullscreen' => getRequest('fullscreen')))
		)
	);

	$headerForm = new CForm('get');
	$headerForm->addVar('fullscreen', getRequest('fullscreen'));
	$headerForm->addVar('stime', $stime);
	$headerForm->addVar('period', $period);

	// add host and group filters to the form
	if ($source == EVENT_SOURCE_TRIGGERS) {
		if (getRequest('triggerid') != 0) {
			$headerForm->addVar('triggerid', getRequest('triggerid'), 'triggerid_filter');
		}

		$headerForm->addItem(array(
			_('Group').SPACE,
			$pageFilter->getGroupsCB()
		));
		$headerForm->addItem(array(
			SPACE._('Host').SPACE,
			$pageFilter->getHostsCB()
		));
	}

	if ($allow_discovery) {
		$cmbSource = new CComboBox('source', $source, 'submit()');
		$cmbSource->addItem(EVENT_SOURCE_TRIGGERS, _('Trigger'));
		$cmbSource->addItem(EVENT_SOURCE_DISCOVERY, _('Discovery'));
		$headerForm->addItem(array(SPACE._('Source').SPACE, $cmbSource));
	}

	$eventsWidget->addHeader(_('Events'), $headerForm);
	$eventsWidget->addHeaderRowNumber();

	$filterForm = null;

	if ($source == EVENT_SOURCE_TRIGGERS) {
		$filterForm = new CFormTable(null, null, 'get');
		$filterForm->setTableClass('formtable old-filter');
		$filterForm->setAttribute('name', 'zbx_filter');
		$filterForm->setAttribute('id', 'zbx_filter');
		$filterForm->addVar('triggerid', $triggerId);
		$filterForm->addVar('stime', $stime);
		$filterForm->addVar('period', $period);

		if ($triggerId > 0) {
			$dbTrigger = API::Trigger()->get(array(
				'triggerids' => $triggerId,
				'output' => array('description', 'expression'),
				'selectHosts' => array('name'),
				'preservekeys' => true,
				'expandDescription' => true
			));
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

		$filterForm->addRow(new CRow(array(
			new CCol(_('Trigger'), 'form_row_l'),
			new CCol(array(
				new CTextBox('trigger', $trigger, 96, true),
				new CButton('btn1', _('Select'),
					'return PopUp("popup.php?'.
						'dstfrm='.$filterForm->getName().
						'&dstfld1=triggerid'.
						'&dstfld2=trigger'.
						'&srctbl=triggers'.
						'&srcfld1=triggerid'.
						'&srcfld2=description'.
						'&real_hosts=1'.
						'&monitored_hosts=1'.
						'&with_monitored_triggers=1'.
						($pageFilter->hostid ? '&only_hostid='.$pageFilter->hostid : '').
						'");',
					'T'
				)
			), 'form_row_r')
		)));

		$filterForm->addItemToBottomRow(new CSubmit('filter_set', _('Filter')));
		$filterForm->addItemToBottomRow(new CSubmit('filter_rst', _('Reset')));
	}

	$eventsWidget->addFlicker($filterForm, CProfile::get('web.events.filter.state', 0));

	$scroll = new CDiv();
	$scroll->setAttribute('id', 'scrollbar_cntr');
	$eventsWidget->addFlicker($scroll, CProfile::get('web.events.filter.state', 0));

	$table = new CTableInfo(_('No events found.'));
}

// trigger events
if ($source == EVENT_OBJECT_TRIGGER) {
	$firstEvent = API::Event()->get(array(
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'output' => API_OUTPUT_EXTEND,
		'objectids' => $triggerId ? $triggerId : null,
		'sortfield' => array('clock'),
		'sortorder' => ZBX_SORT_UP,
		'limit' => 1
	));
	$firstEvent = reset($firstEvent);
}

// discovery events
else {
	$firstEvent = API::Event()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'source' => EVENT_SOURCE_DISCOVERY,
		'object' => EVENT_OBJECT_DHOST,
		'sortfield' => array('clock'),
		'sortorder' => ZBX_SORT_UP,
		'limit' => 1
	));
	$firstEvent = reset($firstEvent);

	$firstDServiceEvent = API::Event()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'source' => EVENT_SOURCE_DISCOVERY,
		'object' => EVENT_OBJECT_DSERVICE,
		'sortfield' => array('clock'),
		'sortorder' => ZBX_SORT_UP,
		'limit' => 1
	));
	$firstDServiceEvent = reset($firstDServiceEvent);

	if ($firstDServiceEvent && (!$firstEvent || $firstDServiceEvent['eventid'] < $firstEvent['eventid'])) {
		$firstEvent = $firstDServiceEvent;
	}
}

$config = select_config();

// headers
if ($source == EVENT_SOURCE_DISCOVERY) {
	$header = array(
		_('Time'),
		_('IP'),
		_('DNS'),
		_('Description'),
		_('Status')
	);

	if ($csvExport) {
		$csvRows[] = $header;
	}
	else {
		$table->setHeader($header);
	}
}
else {
	$header = array(
		_('Time'),
		(getRequest('hostid', 0) == 0) ? _('Host') : null,
		_('Description'),
		_('Status'),
		_('Severity'),
		_('Duration'),
		$config['event_ack_enable'] ? _('Ack') : null,
		_('Actions')
	);

	if ($csvExport) {
		$csvRows[] = $header;
	}
	else {
		$table->setHeader($header);
	}
}

if (!$firstEvent) {
	$starttime = null;

	if (!$csvExport) {
		$events = array();
		$paging = getPagingLine($events);
	}
}
else {
	$starttime = $firstEvent['clock'];

	if ($source == EVENT_SOURCE_DISCOVERY) {
		// fetch discovered service and discovered host events separately
		$dHostEvents = API::Event()->get(array(
			'source' => EVENT_SOURCE_DISCOVERY,
			'object' => EVENT_OBJECT_DHOST,
			'time_from' => $from,
			'time_till' => $till,
			'output' => array('eventid', 'object', 'objectid', 'clock', 'value'),
			'sortfield' => array('clock', 'eventid'),
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => $config['search_limit'] + 1
		));
		$dServiceEvents = API::Event()->get(array(
			'source' => EVENT_SOURCE_DISCOVERY,
			'object' => EVENT_OBJECT_DSERVICE,
			'time_from' => $from,
			'time_till' => $till,
			'output' => array('eventid', 'object', 'objectid', 'clock', 'value'),
			'sortfield' => array('clock', 'eventid'),
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => $config['search_limit'] + 1
		));
		$dsc_events = array_merge($dHostEvents, $dServiceEvents);
		CArrayHelper::sort($dsc_events, array(
			array('field' => 'clock', 'order' => ZBX_SORT_DOWN),
			array('field' => 'eventid', 'order' => ZBX_SORT_DOWN)
		));
		$dsc_events = array_slice($dsc_events, 0, $config['search_limit'] + 1);

		$paging = getPagingLine($dsc_events);

		if (!$csvExport) {
			$csvDisabled = zbx_empty($dsc_events);
		}

		$objectids = array();
		foreach ($dsc_events as $event_data) {
			$objectids[$event_data['objectid']] = $event_data['objectid'];
		}

		// object dhost
		$dhosts = array();
		$res = DBselect(
			'SELECT s.dserviceid,s.dhostid,s.ip,s.dns'.
			' FROM dservices s'.
			' WHERE '.dbConditionInt('s.dhostid', $objectids)
		);
		while ($dservices = DBfetch($res)) {
			$dhosts[$dservices['dhostid']] = $dservices;
		}

		// object dservice
		$dservices = array();
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
				$csvRows[] = array(
					zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event_data['clock']),
					$event_data['object_data']['ip'],
					$event_data['object_data']['dns'],
					$event_data['description'],
					discovery_value($event_data['value'])
				);
			}
			else {
				$table->addRow(array(
					zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event_data['clock']),
					$event_data['object_data']['ip'],
					zbx_empty($event_data['object_data']['dns']) ? SPACE : $event_data['object_data']['dns'],
					$event_data['description'],
					new CCol(discovery_value($event_data['value']), discovery_value_style($event_data['value']))
				));
			}
		}
	}

	// source not discovery i.e. trigger
	else {
		if ($csvExport || $pageFilter->hostsSelected) {
			$knownTriggerIds = array();
			$validTriggerIds = array();

			$triggerOptions = array(
				'output' => array('triggerid'),
				'preservekeys' => true,
				'monitored' => true
			);

			$allEventsSliceLimit = $config['search_limit'];

			$eventOptions = array(
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'time_from' => $from,
				'time_till' => $till,
				'output' => array('eventid', 'objectid'),
				'sortfield' => array('clock', 'eventid'),
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => $allEventsSliceLimit + 1
			);

			if ($triggerId) {
				$knownTriggerIds = array($triggerId => $triggerId);
				$validTriggerIds = $knownTriggerIds;

				$eventOptions['objectids'] = array($triggerId);;
			}
			elseif ($pageFilter->hostid > 0) {
				$hostTriggers = API::Trigger()->get(array(
					'output' => array('triggerid'),
					'hostids' => $pageFilter->hostid,
					'monitored' => true,
					'preservekeys' => true
				));
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

			$events = array();

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
						$events[] = array('eventid' => $event['eventid']);
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
			$paging = getPagingLine($events);

			// query event with extend data
			$events = API::Event()->get(array(
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'eventids' => zbx_objectValues($events, 'eventid'),
				'output' => API_OUTPUT_EXTEND,
				'select_acknowledges' => API_OUTPUT_COUNT,
				'sortfield' => array('clock', 'eventid'),
				'sortorder' => ZBX_SORT_DOWN,
				'nopermissions' => true
			));

			if (!$csvExport) {
				$csvDisabled = zbx_empty($events);
			}

			$triggers = API::Trigger()->get(array(
				'triggerids' => zbx_objectValues($events, 'objectid'),
				'selectHosts' => array('hostid', 'status'),
				'selectItems' => array('itemid', 'hostid', 'name', 'key_', 'value_type'),
				'output' => array('description', 'expression', 'priority', 'flags', 'url')
			));
			$triggers = zbx_toHash($triggers, 'triggerid');

			// fetch hosts
			$hosts = array();
			foreach ($triggers as $trigger) {
				$hosts[] = reset($trigger['hosts']);
			}
			$hostids = zbx_objectValues($hosts, 'hostid');

			$hosts = API::Host()->get(array(
				'output' => array('name', 'hostid', 'status'),
				'hostids' => $hostids,
				'selectGraphs' => API_OUTPUT_COUNT,
				'selectScreens' => API_OUTPUT_COUNT,
				'preservekeys' => true
			));

			// fetch scripts for the host JS menu
			if (!$csvExport && getRequest('hostid', 0) == 0) {
				$scripts = API::Script()->getScriptsByHosts($hostids);
			}

			// actions
			$actions = getEventActionsStatus(zbx_objectValues($events, 'eventid'));

			// events
			foreach ($events as $event) {
				$trigger = $triggers[$event['objectid']];

				$host = reset($trigger['hosts']);
				$host = $hosts[$host['hostid']];

				$triggerItems = array();

				$trigger['items'] = CMacrosResolverHelper::resolveItemNames($trigger['items']);

				foreach ($trigger['items'] as $item) {
					$triggerItems[] = array(
						'name' => $item['name_expanded'],
						'params' => array(
							'itemid' => $item['itemid'],
							'action' => in_array($item['value_type'], array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64))
								? HISTORY_GRAPH : HISTORY_VALUES
						)
					);
				}

				$description = CMacrosResolverHelper::resolveEventDescription(zbx_array_merge($trigger, array(
					'clock' => $event['clock'],
					'ns' => $event['ns']
				)));

				// duration
				$event['duration'] = ($nextEvent = get_next_event($event, $events))
					? zbx_date2age($event['clock'], $nextEvent['clock'])
					: zbx_date2age($event['clock']);

				// action
				$action = isset($actions[$event['eventid']]) ? $actions[$event['eventid']] : ' - ';

				if ($csvExport) {
					$csvRows[] = array(
						zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']),
						(getRequest('hostid', 0) == 0) ? $host['name'] : null,
						$description,
						trigger_value2str($event['value']),
						getSeverityCaption($trigger['priority']),
						$event['duration'],
						$config['event_ack_enable'] ? ($event['acknowledges'] ? _('Yes') : _('No')) : null,
						strip_tags((string) $action)
					);
				}
				else {
					$triggerDescription = new CSpan($description, 'pointer link_menu');
					$triggerDescription->setMenuPopup(
						CMenuPopupHelper::getTrigger($trigger, $triggerItems, null, $event['clock'])
					);

					// acknowledge
					$ack = getEventAckState($event, true);

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

					if (getRequest('hostid', 0) == 0) {
						$hostName = new CSpan($host['name'], 'link_menu');
						$hostName->setMenuPopup(CMenuPopupHelper::getHost($host, $scripts[$host['hostid']]));
					}

					$table->addRow(array(
						new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']),
								'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid'],
							'action'
						),
						$hostName,
						$triggerDescription,
						$statusSpan,
						getSeverityCell($trigger['priority'], null, !$event['value']),
						$event['duration'],
						$config['event_ack_enable'] ? $ack : null,
						$action
					));
				}
			}
		}
		else {
			if (!$csvExport) {
				$events = array();
				$paging = getPagingLine($events);
			}
		}
	}

	if (!$csvExport) {
		$table = array($paging, $table, $paging);
	}
}

if ($csvExport) {
	echo zbx_toCSV($csvRows);
}
else {
	$eventsWidget->addItem($table);

	$timeline = array(
		'period' => $period,
		'starttime' => date(TIMESTAMP_FORMAT, $starttime),
		'usertime' => date(TIMESTAMP_FORMAT, $till)
	);

	$objData = array(
		'id' => 'timeline_1',
		'loadSBox' => 0,
		'loadImage' => 0,
		'loadScroll' => 1,
		'dynamic' => 0,
		'mainObject' => 1,
		'periodFixed' => CProfile::get('web.events.timelinefixed', 1),
		'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
	);

	zbx_add_post_js('jqBlink.blink();');
	zbx_add_post_js('timeControl.addObject("scroll_events_id", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($objData).');');
	zbx_add_post_js('timeControl.processObjects();');

	$eventsWidget->show();

	if ($csvDisabled) {
		zbx_add_post_js('document.getElementById("csv_export").disabled = true;');
	}

	require_once dirname(__FILE__).'/include/page_footer.php';
}
