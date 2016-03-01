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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/incidentdetails.inc.php';

$page['title'] = _('Tests');
$page['file'] = 'rsm.tests.php';
$page['hist_arg'] = array('groupid', 'hostid');
$page['scripts'] = array('class.calendar.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'host' =>					array(T_ZBX_STR, O_OPT,		P_SYS,	null,			null),
	'type' =>					array(T_ZBX_INT, O_OPT,		null,	IN('0,1,2,3'),	null),
	'slvItemId' =>				array(T_ZBX_INT, O_OPT,		P_SYS,	DB_ID,			null),
	'original_from' =>			array(T_ZBX_INT, O_OPT,		null,	null,			null),
	'original_to' =>			array(T_ZBX_INT, O_OPT,		null,	null,			null),
	// filter
	'filter_set' =>				array(T_ZBX_STR, O_OPT,		P_ACT,	null,			null),
	'filter_from' =>			array(T_ZBX_INT, O_OPT,		null,	null,			null),
	'filter_to' =>				array(T_ZBX_INT, O_OPT,		null,	null,			null),
	'filter_rolling_week' =>	array(T_ZBX_INT, O_OPT,		null,	null,			null),
	// ajax
	'favobj'=>					array(T_ZBX_STR, O_OPT,		P_ACT,	null,			null),
	'favref'=>					array(T_ZBX_STR, O_OPT,		P_ACT,  NOT_EMPTY,		'isset({favobj})'),
	'favstate'=>				array(T_ZBX_INT, O_OPT,		P_ACT,  NOT_EMPTY,		'isset({favobj})&&("filter"=={favobj})')
);
check_fields($fields);

if (isset($_REQUEST['favobj'])) {
	if('filter' == $_REQUEST['favobj']){
		CProfile::update('web.rsm.tests.filter.state', get_request('favstate'), PROFILE_TYPE_INT);
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

$data['tests'] = array();

$macro = API::UserMacro()->get(array(
	'globalmacro' => true,
	'output' => API_OUTPUT_EXTEND,
	'filter' => array(
		'macro' => RSM_ROLLWEEK_SECONDS
	)
));

if (!$macro) {
	show_error_message(_s('Macro "%1$s" doesn\'t not exist.', RSM_ROLLWEEK_SECONDS));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$rollWeekSeconds = reset($macro);
$serverTime = time() - RSM_ROLLWEEK_SHIFT_BACK;

/*
 * Filter
 */
if (get_request('filter_set')) {
	$data['host'] = get_request('host');
	$data['type'] = get_request('type');
	$data['slvItemId'] = get_request('slvItemId');

	$data['filter_from'] = (get_request('filter_from') == get_request('original_from'))
		? date('YmdHis', get_request('filter_from', $serverTime - $rollWeekSeconds['value']))
		: get_request('filter_from', date('YmdHis', $serverTime - $rollWeekSeconds['value']));

	$data['filter_to'] = (get_request('filter_to') == get_request('original_to'))
		? date('YmdHis', get_request('filter_to', $serverTime))
		: get_request('filter_to', date('YmdHis', $serverTime));

	CProfile::update('web.rsm.tests.host', $data['host'], PROFILE_TYPE_STR);
	CProfile::update('web.rsm.tests.type', $data['type'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.tests.slvItemId', $data['slvItemId'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.tests.filter_from', $data['filter_from'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.tests.filter_to', $data['filter_to'], PROFILE_TYPE_ID);
}
elseif (get_request('filter_rolling_week')) {
	$data['host'] = CProfile::get('web.rsm.tests.host');
	$data['type'] = CProfile::get('web.rsm.tests.type');
	$data['slvItemId'] = CProfile::get('web.rsm.tests.slvItemId');

	// set new filter from and filter to
	$data['filter_from'] = date('YmdHis', $serverTime - $rollWeekSeconds['value']);
	$data['filter_to'] = date('YmdHis', $serverTime);

	CProfile::update('web.rsm.tests.filter_from', $data['filter_from'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.tests.filter_to', $data['filter_to'], PROFILE_TYPE_ID);
}
else {
	$data['host'] = CProfile::get('web.rsm.tests.host');
	$data['type'] = CProfile::get('web.rsm.tests.type');
	$data['slvItemId'] = CProfile::get('web.rsm.tests.slvItemId');
	$data['filter_from'] = CProfile::get('web.rsm.tests.filter_from',
		date('YmdHis', $serverTime - $rollWeekSeconds['value'])
	);
	$data['filter_to'] = CProfile::get('web.rsm.tests.filter_to', date('YmdHis', $serverTime));
}

// check
if (!$data['host'] || !$data['slvItemId'] || $data['type'] === null) {
	access_deny();
}

// get TLD
$tld = API::Host()->get(array(
	'tlds' => true,
	'output' => array('hostid', 'host', 'name'),
	'filter' => array(
		'host' => $data['host']
	)
));

if ($tld) {
	$data['tld'] = reset($tld);
}
else {
	show_error_message(_('No permissions to referred TLD or it does not exist!'));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

// used items
if ($data['type'] == RSM_DNS) {
	$key = RSM_SLV_DNS_AVAIL;
}
elseif ($data['type'] == RSM_DNSSEC) {
	$key = RSM_SLV_DNSSEC_AVAIL;
}
elseif ($data['type'] == RSM_RDDS) {
	$key = RSM_SLV_RDDS_AVAIL;
}
else {
	$key = RSM_SLV_EPP_AVAIL;
}

// get items
$items = API::Item()->get(array(
	'hostids' => $data['tld']['hostid'],
	'filter' => array(
		'key_' => $key
	),
	'output' => array('itemid', 'hostid', 'key_'),
	'preservekeys' => true
));

if ($items) {
	$item = reset($items);
	$availItem = $item['itemid'];

	// get triggers
	$triggers = API::Trigger()->get(array(
		'itemids' => $availItem,
		'output' => array('triggerids'),
		'preservekeys' => true
	));

	$triggerIds = array_keys($triggers);

	$events = API::Event()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'triggerids' => $triggerIds,
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'selectTriggers' => API_OUTPUT_REFER,
		'time_from' => zbxDateToTime($data['filter_from']),
		'time_till' => zbxDateToTime($data['filter_to'])
	));

	CArrayHelper::sort($events, array('objectid', 'clock'));

	$i = 0;
	$incidents = array();

	// data generation
	$incidentsData = array();

	foreach ($events as $event) {
		if ($event['value'] == TRIGGER_VALUE_TRUE) {
			if (isset($incidents[$i]) && $incidents[$i]['status'] == TRIGGER_VALUE_TRUE) {
				// get event end time
				$addEvent = DBfetch(DBselect(
					'SELECT e.clock'.
					' FROM events e'.
					' WHERE e.objectid='.$incidents[$i]['objectid'].
						' AND e.clock>='.zbxDateToTime($data['filter_to']).
						' AND e.object='.EVENT_OBJECT_TRIGGER.
						' AND e.source='.EVENT_SOURCE_TRIGGERS.
						' AND '.dbConditionInt('e.value', array(TRIGGER_VALUE_FALSE, TRIGGER_VALUE_UNKNOWN)).
					' ORDER BY e.clock,e.ns',
					1
				));

				if ($addEvent) {
					$incidentsData[$i]['endTime'] = $addEvent['clock'];
					$incidentsData[$i]['status'] = TRIGGER_VALUE_FALSE;
				}
			}

			$i++;
			$incidents[$i] = array(
				'objectid' => $event['objectid'],
				'status' => TRIGGER_VALUE_TRUE,
				'startTime' => $event['clock'],
				'false_positive' => $event['false_positive']
			);
		}
		else {
			if (isset($incidents[$i])) {
				$incidents[$i] = array(
					'status' => TRIGGER_VALUE_FALSE,
					'endTime' => $event['clock']
				);
			}
			else {
				$i++;
				// get event start time
				$addEvent = API::Event()->get(array(
					'output' => API_OUTPUT_EXTEND,
					'triggerids' => $event['objectid'],
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'selectTriggers' => API_OUTPUT_REFER,
					'time_till' => $event['clock'] - 1,
					'filter' => array(
						'value' => TRIGGER_VALUE_TRUE
					),
					'limit' => 1,
					'sortorder' => ZBX_SORT_DOWN
				));

				if ($addEvent) {
					$addEvent = reset($addEvent);

					$incidents[$i] = array(
						'objectid' => $event['objectid'],
						'status' => TRIGGER_VALUE_FALSE,
						'startTime' => $addEvent['clock'],
						'endTime' => $event['clock'],
						'false_positive' => $addEvent['false_positive']
					);
				}
			}
		}

		if (isset($incidentsData[$i]) && $incidentsData[$i]) {
			$incidentsData[$i] = array_merge($incidentsData[$i], $incidents[$i]);
		}
		else {
			if (isset($incidents[$i])) {
				$incidentsData[$i] = $incidents[$i];
			}
		}
	}

	if (isset($incidents[$i]) && $incidents[$i]['status'] == TRIGGER_VALUE_TRUE) {
		// get event end time
		$addEvent = DBfetch(DBselect(
			'SELECT e.clock'.
			' FROM events e'.
			' WHERE e.objectid='.$incidents[$i]['objectid'].
				' AND e.clock>='.zbxDateToTime($data['filter_to']).
				' AND e.object='.EVENT_OBJECT_TRIGGER.
				' AND e.source='.EVENT_SOURCE_TRIGGERS.
				' AND '.dbConditionString('e.value', array(TRIGGER_VALUE_FALSE, TRIGGER_VALUE_UNKNOWN)).
			' ORDER BY e.clock,e.ns',
			1
		));

		if ($addEvent) {
			$newData[$i] = array(
				'status' => TRIGGER_VALUE_FALSE,
				'endTime' => $addEvent['clock']
			);

			unset($incidentsData[$i]['status']);
			$incidentsData[$i] = array_merge($incidentsData[$i], $newData[$i]);
		}
	}

	$tests = DBselect(
		'SELECT h.clock, h.value'.
		' FROM history_uint h'.
		' WHERE h.itemid='.$availItem.
			' AND h.clock>='.zbxDateToTime($data['filter_from']).
			' AND h.clock<='.zbxDateToTime($data['filter_to'])
	);

	// result generation
	$data['downTests'] = 0;
	$data['statusChanges'] = 0;
	while ($test = DBfetch($tests)) {
		if ($test['value'] == 0) {
			$data['tests'][] = array(
				'value' => $test['value'],
				'clock' => $test['clock'],
				'incident' => 0,
				'updated' => false
			);

			if (!$test['value']) {
				$data['downTests']++;
			}
		}

		// state changes
		if (!isset($statusChanged)) {
			$statusChanged = $test['value'];
		}
		else {
			if ($statusChanged != $test['value']) {
				$statusChanged = $test['value'];
				$data['statusChanges']++;
			}
		}
	}

	$data['downPeriod'] = zbxDateToTime($data['filter_to']) - zbxDateToTime($data['filter_from']);

	if ($data['type'] == RSM_DNS || $data['type'] == RSM_DNSSEC) {
		$itemKey = CALCULATED_ITEM_DNS_DELAY;
	}
	elseif ($data['type'] == RSM_RDDS) {
		$itemKey = CALCULATED_ITEM_RDDS_DELAY;
	}
	else {
		$itemKey = CALCULATED_ITEM_EPP_DELAY;
	}

	// get host with calculated items
	$rsm = API::Host()->get(array(
		'output' => array('hostid'),
		'filter' => array(
			'host' => RSM_HOST
		)
	));

	if ($rsm) {
		$rsm = reset($rsm);
	}
	else {
		show_error_message(_s('No permissions to referred host "%1$s" or it does not exist!', RSM_HOST));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	$item = API::Item()->get(array(
		'hostids' => $rsm['hostid'],
		'output' => array('itemid', 'value_type'),
		'filter' => array(
			'key_' => $itemKey
		)
	));

	if ($item) {
		$item = reset($item);
	}
	else {
		show_error_message(_s('Missing items at host "%1$s"!', RSM_HOST));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	$itemValue = API::History()->get(array(
		'itemids' => $item['itemid'],
		'time_from' => zbxDateToTime($data['filter_from']),
		'history' => $item['value_type'],
		'output' => API_OUTPUT_EXTEND,
		'limit' => 1
	));
	$itemValue = reset($itemValue);

	$timeStep = $itemValue['value'] ?  $itemValue['value'] / SEC_PER_MIN : 1;

	$data['downTimeMinutes'] = $data['downTests'] * $timeStep;

	foreach ($incidentsData as $incident) {
		foreach ($data['tests'] as $key => $test) {
			if (!$test['updated'] && $incident['startTime'] < $test['clock'] && (!isset($incident['endTime'])
					|| (isset($incident['endTime']) && $incident['endTime'] > $test['clock']))) {
				$data['tests'][$key]['incident'] = $incident['false_positive'] ? INCIDENT_FALSE_POSITIVE : INCIDENT_RESOLVED;
				$data['tests'][$key]['updated'] = true;
			}
		}
	}
}
else {
	access_deny();
}

$rsmView = new CView('rsm.tests.list', $data);

$rsmView->render();
$rsmView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
