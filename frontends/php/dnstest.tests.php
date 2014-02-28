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
$page['file'] = 'dnstest.tests.php';
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
		CProfile::update('web.dnstest.tests.filter.state', get_request('favstate'), PROFILE_TYPE_INT);
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

$data = array();
$data['host'] = get_request('host');
$data['type'] = get_request('type');
$data['slvItemId'] = get_request('slvItemId');
$data['tests'] = array();

// check
if (!$data['host'] || !$data['slvItemId'] || $data['type'] === null) {
	access_deny();
}

/*
 * Filter
 */
if (get_request('filter_rolling_week')) {
	$data['filter_from'] = date('YmdHis', time() - SEC_PER_WEEK);
	$data['filter_to'] = date('YmdHis', time());
}
else {
	if (get_request('filter_from') == get_request('original_from')) {
		$data['filter_from'] = date('YmdHis', get_request('filter_from', time() - SEC_PER_WEEK));
	}
	else {
		$data['filter_from'] = get_request('filter_from', date('YmdHis', time() - SEC_PER_WEEK));
	}
	if (get_request('filter_to') == get_request('original_to')) {
		$data['filter_to'] = date('YmdHis', get_request('filter_to', time()));
	}
	else {
		$data['filter_to'] = get_request('filter_to', date('YmdHis', time()));
	}
}

$data['sid'] = get_request('sid');

// get TLD
$tld = API::Host()->get(array(
	'tlds' => true,
	'output' => array('hostid', 'host', 'name'),
	'filter' => array(
		'host' => $data['host']
	)
));

$data['tld'] = reset($tld);

// used items
if ($data['type'] == DNSTEST_DNS) {
	$key = DNSTEST_SLV_DNS_AVAIL;
}
elseif ($data['type'] == DNSTEST_DNSSEC) {
	$key = DNSTEST_SLV_DNSSEC_AVAIL;
}
elseif ($data['type'] == DNSTEST_RDDS) {
	$key = DNSTEST_SLV_RDDS_AVAIL;
}
else {
	$key = DNSTEST_SLV_EPP_AVAIL;
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
		'time_till' => zbxDateToTime($data['filter_to']),
		'filter' => array(
			'value_changed' => TRIGGER_VALUE_CHANGED_YES
		)
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
				$addEvent = API::Event()->get(array(
					'output' => API_OUTPUT_EXTEND,
					'triggerids' => array($incidents[$i]['objectid']),
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'selectTriggers' => API_OUTPUT_REFER,
					'time_from' => zbxDateToTime($data['filter_to']),
					'filter' => array(
						'value' => TRIGGER_VALUE_FALSE,
						'value_changed' => TRIGGER_VALUE_CHANGED_YES
					),
					'limit' => 1,
					'sortorder' => ZBX_SORT_UP
				));

				if ($addEvent) {
					$addEvent = reset($addEvent);
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
						'value' => TRIGGER_VALUE_TRUE,
						'value_changed' => TRIGGER_VALUE_CHANGED_YES
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
		$addEvent = API::Event()->get(array(
			'output' => API_OUTPUT_EXTEND,
			'triggerids' => $incidents[$i]['objectid'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'selectTriggers' => API_OUTPUT_REFER,
			'time_from' => zbxDateToTime($data['filter_to']),
			'filter' => array(
				'value' => TRIGGER_VALUE_FALSE,
				'value_changed' => TRIGGER_VALUE_CHANGED_YES
			),
			'limit' => 1,
			'sortorder' => ZBX_SORT_UP
		));

		if ($addEvent) {
			$addEvent = reset($addEvent);
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

	if ($data['tld'] && $data['slvItemId']) {
		$data['slv'] = sprintf('%.3f', getSLV($data['slvItemId']));

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

		if ($data['type'] == DNSTEST_DNS || $data['type'] == DNSTEST_DNSSEC) {
			$itemKey = CALCULATED_ITEM_DNS_DELAY;
		}
		elseif ($data['type'] == DNSTEST_RDDS) {
			$itemKey = CALCULATED_ITEM_RDDS_DELAY;
		}
		else {
			$itemKey = CALCULATED_ITEM_EPP_DELAY;
		}

		// get host with calculated items
		$dnstest = API::Host()->get(array(
			'output' => array('hostid'),
			'filter' => array(
				'host' => DNSTEST_HOST
			)
		));

		if ($dnstest) {
			$dnstest = reset($dnstest);
		}
		else {
			show_error_message(_s('No permissions to referred host "%1$s" or it does not exist!', DNSTEST_HOST));
			require_once dirname(__FILE__).'/include/page_footer.php';
			exit;
		}

		$item = API::Item()->get(array(
			'hostids' => $dnstest['hostid'],
			'output' => array('itemid'),
			'filter' => array(
				'key_' => $itemKey
			)
		));

		if ($item) {
			$item = reset($item);
		}
		else {
			show_error_message(_s('Missed items for host "%1$s"!', DNSTEST_HOST));
			require_once dirname(__FILE__).'/include/page_footer.php';
			exit;
		}

		$timeStep = getFirstUintValue($item['itemid'], zbxDateToTime($data['filter_from'])) / SEC_PER_MIN;
		$timeStep = $timeStep ? $timeStep : 1;

		$data['downTimeMinutes'] = $data['downTests'] * $timeStep;

		foreach ($incidentsData as $incident) {
			foreach ($data['tests'] as $key => $test) {
				if (!$test['updated'] && $incident['startTime'] < $test['clock'] && (!isset($incident['endTime'])
						|| (isset($incident['endTime']) && $incident['endTime'] > $test['clock']))) {
					$data['tests'][$key]['incident'] = $incident['false_positive'] ? 2 : 1;
					$data['tests'][$key]['updated'] = true;
				}
			}
		}
	}
}
else {
	access_deny();
}

$dnsTestView = new CView('dnstest.tests.list', $data);

$dnsTestView->render();
$dnsTestView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
