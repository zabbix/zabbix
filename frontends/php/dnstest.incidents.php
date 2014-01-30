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
require_once dirname(__FILE__).'/include/incidents.inc.php';
require_once dirname(__FILE__).'/include/incidentdetails.inc.php';

$page['title'] = _('TLD Rolling week status');
$page['file'] = 'dnstest.incidents.php';
$page['hist_arg'] = array('groupid', 'hostid');
$page['scripts'] = array('class.calendar.js');

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'host' =>					array(T_ZBX_STR, O_OPT,	null,	null,		NULL),
	'eventid' =>				array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),
	'incident_type' =>			array(T_ZBX_INT, O_OPT,	null,	null,		NULL),
	'mark_incident' =>			array(T_ZBX_INT, O_OPT,	null,	null,		NULL),
	'original_from' =>			array(T_ZBX_INT, O_OPT, null,	null,		null),
	'original_to' =>			array(T_ZBX_INT, O_OPT, null,	null,		null),
	// filter
	'filter_set' =>				array(T_ZBX_STR, O_OPT,	P_ACT,	null,		null),
	'filter_search' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'filter_from' =>			array(T_ZBX_INT, O_OPT, null,	null,		null),
	'filter_to' =>				array(T_ZBX_INT, O_OPT, null,	null,		null),
	'filter_rolling_week' =>	array(T_ZBX_INT, O_OPT, null,	null,		null),
	// ajax
	'favobj'=>					array(T_ZBX_STR, O_OPT, P_ACT,	NULL,		NULL),
	'favref'=>					array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'),
	'favstate'=>				array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})&&("filter"=={favobj})')
);
check_fields($fields);

if (isset($_REQUEST['favobj'])) {
	if('filter' == $_REQUEST['favobj']) {
		CProfile::update('web.dnstest.incidents.filter.state', get_request('favstate'), PROFILE_TYPE_INT);
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

if (isset($_REQUEST['mark_incident']) && CWebUser::getType() >= USER_TYPE_ZABBIX_ADMIN) {
	$event = API::Event()->get(array(
		'eventids' => get_request('eventid'),
		'output' => API_OUTPUT_EXTEND,
		'filter' => array(
			'value' => TRIGGER_VALUE_TRUE,
			'value_changed' => TRIGGER_VALUE_CHANGED_YES
		)
	));

	if ($_REQUEST['mark_incident'] == INCIDENT_ACTIVE) {
		$incidentType = _('Active');
		$changeIncidentType = 0;
	}
	elseif ($_REQUEST['mark_incident'] == INCIDENT_RESOLVED) {
		$incidentType = _('Resolved');
		$changeIncidentType = 0;
	}
	else {
		$incidentType = _('False positive');
		$changeIncidentType = 1;
	}

	if ($event) {
		$event = reset($event);
		if ($event['false_positive'] != $changeIncidentType) {
			DBstart();
			$res = DBexecute(
				'UPDATE events SET false_positive='.zbx_dbstr($changeIncidentType).' WHERE eventid='.$event['eventid']
			);
			show_messages(DBend($res), _('Status updated'), _('Cannot update status'));

			if ($res) {
				add_audit(
					AUDIT_ACTION_UPDATE,
					AUDIT_RESOURCE_INCIDENT,
					' Incident ['.$event['eventid'].'] '.$incidentType
				);
			}
		}
	}
	else {
		access_deny();
	}
}

$host = get_request('host');
$data = array();

/*
 * Filter
 */
if (isset($_REQUEST['filter_set'])) {
	$data['filter_search'] = get_request('filter_search');
	CProfile::update('web.dnstest.incidents.filter_search', $data['filter_search'], PROFILE_TYPE_STR);
}
else {
	$data['filter_search'] = CProfile::get('web.dnstest.incidents.filter_search');
}

if (get_request('filter_rolling_week')) {
	$data['filter_from'] = date('YmdHis', time() - SEC_PER_WEEK);
	$data['filter_to'] = date('YmdHis', time());
	CProfile::update('web.dnstest.incidents.filter_from', $data['filter_from'], PROFILE_TYPE_ID);
	CProfile::update('web.dnstest.incidents.filter_to', $data['filter_to'], PROFILE_TYPE_ID);
}
else {
	if (get_request('filter_set')) {
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
		CProfile::update('web.dnstest.incidents.filter_from', $data['filter_from'], PROFILE_TYPE_ID);
		CProfile::update('web.dnstest.incidents.filter_to', $data['filter_to'], PROFILE_TYPE_ID);
	}
	else {
		$data['filter_from'] = CProfile::get(
			'web.dnstest.incidents.filter_from',
			date('YmdHis', time() - SEC_PER_WEEK)
		);
		$data['filter_to'] = CProfile::get('web.dnstest.incidents.filter_to', date('YmdHis', time()));
	}
}

$filterTimeFrom = zbxDateToTime($data['filter_from']);
$filterTimeTill = zbxDateToTime($data['filter_to']);

$data['sid'] = get_request('sid');

// get TLD
if ($host || $data['filter_search']) {
	$options = array(
		'tlds' => true,
		'output' => array('hostid', 'host', 'name')
	);

	if ($host) {
		$options['filter'] = array('host' => $host);
	}
	else {
		$options['filter'] = array('name' => $data['filter_search']);
	}

	$tld = API::Host()->get($options);
	$data['tld'] = reset($tld);

	if ($data['tld'] && $host && $data['filter_search'] != $data['tld']['name']) {
		$data['filter_search'] = $data['tld']['name'];
		CProfile::update('web.dnstest.incidents.filter_search', $data['tld']['name'], PROFILE_TYPE_STR);
	}

	if (!$data['tld']) {
		unset($data['tld']);
	}
	else {
		// get items
		$items = API::Item()->get(array(
			'hostids' => $data['tld']['hostid'],
			'filter' => array(
				'key_' => array(
					DNSTEST_SLV_DNS_ROLLWEEK, DNSTEST_SLV_DNSSEC_ROLLWEEK, DNSTEST_SLV_RDDS_ROLLWEEK,
					DNSTEST_SLV_EPP_ROLLWEEK, DNSTEST_SLV_DNS_AVAIL, DNSTEST_SLV_DNSSEC_AVAIL, DNSTEST_SLV_RDDS_AVAIL,
					DNSTEST_SLV_EPP_AVAIL
				)
			),
			'output' => array('itemid', 'hostid', 'key_', 'lastvalue'),
			'preservekeys' => true
		));

		if ($items) {
			$dnsItems = array();
			$dnssecItems = array();
			$rddsItems = array();
			$eppItems = array();
			$dnsAvailItem = array();
			$dnssecAvailItem = array();
			$rddsAvailItem = array();
			$eppAvailItem = array();

			foreach ($items as $item) {
				switch ($item['key_']) {
					case DNSTEST_SLV_DNS_ROLLWEEK:
						$data['dns']['itemid'] = $item['itemid'];
						$data['dns']['slv'] = sprintf('%.3f', $item['lastvalue']);
						$data['dns']['events'] = array();
						break;
					case DNSTEST_SLV_DNSSEC_ROLLWEEK:
						$data['dnssec']['itemid'] = $item['itemid'];
						$data['dnssec']['slv'] = sprintf('%.3f', $item['lastvalue']);
						$data['dnssec']['events'] = array();
						break;
					case DNSTEST_SLV_RDDS_ROLLWEEK:
						$data['rdds']['itemid'] = $item['itemid'];
						$data['rdds']['slv'] = sprintf('%.3f', $item['lastvalue']);
						$data['rdds']['events'] = array();
						break;
					case DNSTEST_SLV_EPP_ROLLWEEK:
						$data['epp']['itemid'] = $item['itemid'];
						$data['epp']['slv'] = sprintf('%.3f', $item['lastvalue']);
						$data['epp']['events'] = array();
						break;
					case DNSTEST_SLV_DNS_AVAIL:
						$data['dns']['availItemId'] = $item['itemid'];
						$dnsAvailItem = $item['itemid'];
						$dnsItems[] = $item['itemid'];
						$itemIds[] = $item['itemid'];
						break;
					case DNSTEST_SLV_DNSSEC_AVAIL:
						$data['dnssec']['availItemId'] = $item['itemid'];
						$dnssecAvailItem = $item['itemid'];
						$dnssecItems[] = $item['itemid'];
						$itemIds[] = $item['itemid'];
						break;
					case DNSTEST_SLV_RDDS_AVAIL:
						$data['rdds']['availItemId'] = $item['itemid'];
						$rddsAvailItem = $item['itemid'];
						$rddsItems[] = $item['itemid'];
						$itemIds[] = $item['itemid'];
						break;
					case DNSTEST_SLV_EPP_AVAIL:
						$data['epp']['availItemId'] = $item['itemid'];
						$eppAvailItem = $item['itemid'];
						$eppItems[] = $item['itemid'];
						$itemIds[] = $item['itemid'];
						break;
				}
			}

			// get triggers
			$triggers = API::Trigger()->get(array(
				'itemids' => $itemIds,
				'output' => array('triggerids'),
				'filter' => array(
					'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED
				),
				'preservekeys' => true
			));

			$triggerIds = array_keys($triggers);

			$dnsTriggers = array();
			$dnssecTriggers = array();
			$rddsTriggers = array();
			$eppTriggers = array();
			foreach ($triggers as $trigger) {
				$triggerItem = reset($trigger['items']);

				if (in_array($triggerItem['itemid'], $dnsItems)) {
					$dnsTriggers[] = $trigger['triggerid'];
				}
				elseif (in_array($triggerItem['itemid'], $dnssecItems)) {
					$dnssecTriggers[] = $trigger['triggerid'];
				}
				elseif (in_array($triggerItem['itemid'], $rddsItems)) {
					$rddsTriggers[] = $trigger['triggerid'];
				}
				elseif (in_array($triggerItem['itemid'], $eppItems)) {
					$eppTriggers[] = $trigger['triggerid'];
				}
			}

			// select events, where time_from < filter_from and value TRIGGER_VALUE_TRUE
			$newEventIds = array();
			foreach ($triggerIds as $triggerId) {
				$beginEvent = DBfetch(DBselect(
					'SELECT e.eventid,e.value'.
					' FROM events e'.
					' WHERE e.objectid='.$triggerId.
					'	AND e.clock<'.$filterTimeFrom.
					'	AND e.value_changed='.TRIGGER_VALUE_CHANGED_YES.
					' ORDER BY e.clock DESC',
					1
				));

				if ($beginEvent && $beginEvent['value'] == TRIGGER_VALUE_TRUE) {
					$newEventIds[] = $beginEvent['eventid'];
				}
			}

			// get events
			$events = API::Event()->get(array(
				'triggerids' => $triggerIds,
				'selectTriggers' => API_OUTPUT_REFER,
				'select_acknowledges' => API_OUTPUT_EXTEND,
				'time_from' => $filterTimeFrom,
				'time_till' => $filterTimeTill,
				'output' => API_OUTPUT_EXTEND,
				'filter' => array(
					'value_changed' => TRIGGER_VALUE_CHANGED_YES
				)
			));

			if ($newEventIds) {
				$newEvents = API::Event()->get(array(
					'eventids' => $newEventIds,
					'selectTriggers' => API_OUTPUT_REFER,
					'select_acknowledges' => API_OUTPUT_EXTEND,
					'output' => API_OUTPUT_EXTEND
				));

				$events = array_merge($events, $newEvents);
			}

			CArrayHelper::sort($events, array('objectid', 'clock'));

			$i = 0;
			$incidents = array();

			// data generation
			foreach ($events as $event) {
				$getHistory = false;
				$event['acknowledges'] = reset($event['acknowledges']);

				if ($event['value'] == TRIGGER_VALUE_TRUE) {
					if (isset($incidents[$i]) && $incidents[$i]['status'] == TRIGGER_VALUE_TRUE) {
						// get event end time
						$addEvent = API::Event()->get(array(
							'triggerids' => array($incidents[$i]['objectid']),
							'selectTriggers' => API_OUTPUT_REFER,
							'output' => API_OUTPUT_EXTEND,
							'time_from' => $filterTimeTill,
							'filter' => array(
								'value' => TRIGGER_VALUE_FALSE,
								'value_changed' => TRIGGER_VALUE_CHANGED_YES
							),
							'limit' => 1,
							'sortorder' => ZBX_SORT_UP
						));

						$addEvent = reset($addEvent);

						if ($addEvent) {
							$newData[$i] = array(
								'status' => TRIGGER_VALUE_FALSE,
								'endTime' => $addEvent['clock']
							);

							$eventTrigger = reset($addEvent['triggers']);

							if (in_array($eventTrigger['triggerid'], $dnsTriggers)) {
								unset($data['dns']['events'][$i]['status']);
								$itemType = 'dns';
								$itemId = $dnsAvailItem;
								$data['dns']['events'][$i] = array_merge($data['dns']['events'][$i], $newData[$i]);
							}
							elseif (in_array($eventTrigger['triggerid'], $dnssecTriggers)) {
								unset($data['dnssec']['events'][$i]['status']);
								$itemType = 'dnssec';
								$itemId = $dnssecAvailItem;
								$data['dnssec']['events'][$i] = array_merge(
									$data['dnssec']['events'][$i],
									$newData[$i]
								);
							}
							elseif (in_array($eventTrigger['triggerid'], $rddsTriggers)) {
								unset($data['rdds']['events'][$i]['status']);
								$itemType = 'rdds';
								$itemId = $rddsAvailItem;
								$data['rdds']['events'][$i] = array_merge($data['rdds']['events'][$i], $newData[$i]);
							}
							elseif (in_array($eventTrigger['triggerid'], $eppTriggers)) {
								unset($data['epp']['events'][$i]['status']);
								$itemType = 'epp';
								$itemId = $eppAvailItem;
								$data['epp']['events'][$i] = array_merge($data['epp']['events'][$i], $newData[$i]);
							}

							$data[$itemType]['events'][$i]['incidentTotalTests'] = getTotalTestsCount(
								$itemId,
								$filterTimeFrom,
								$filterTimeTill,
								$data[$itemType]['events'][$i]['startTime'],
								$data[$itemType]['events'][$i]['endTime']
							);

							$data[$itemType]['events'][$i]['incidentFailedTests'] = getFailedTestsCount(
								$itemId,
								$filterTimeTill,
								$data[$itemType]['events'][$i]['startTime'],
								$data[$itemType]['events'][$i]['endTime']
							);
						}
						else {
							if (isset($data['dns']['events'][$i])) {
								$itemInfo = array(
									'itemType' => 'dns',
									'itemId' => $dnsAvailItem
								);
							}
							elseif (isset($data['dnssec']['events'][$i])) {
								$itemInfo = array(
									'itemType' => 'dnssec',
									'itemId' => $dnssecAvailItem
								);
							}
							elseif (isset($data['rdds']['events'][$i])) {
								$itemInfo = array(
									'itemType' => 'rdds',
									'itemId' => $rddsAvailItem
								);
							}
							elseif (isset($data['epp']['events'][$i])) {
								$itemInfo = array(
									'itemType' => 'epp',
									'itemId' => $eppAvailItem
								);
							}

							$data[$itemInfo['itemType']]['events'][$i]['incidentTotalTests'] = getTotalTestsCount(
								$itemInfo['itemId'],
								$filterTimeFrom,
								$filterTimeTill,
								$data[$itemInfo['itemType']]['events'][$i]['startTime']
							);

							$data[$itemInfo['itemType']]['events'][$i]['incidentFailedTests'] = getFailedTestsCount(
								$itemInfo['itemId'],
								$filterTimeTill,
								$data[$itemInfo['itemType']]['events'][$i]['startTime']
							);
						}
					}

					$eventTrigger = reset($event['triggers']);

					$i++;
					$incidents[$i] = array(
						'eventid' => $event['eventid'],
						'objectid' => $event['objectid'],
						'status' => TRIGGER_VALUE_TRUE,
						'startTime' => $event['clock'],
						'false_positive' => $event['false_positive'],
						'ticket' => $event['acknowledges']['message']
					);
				}
				else {
					if (isset($incidents[$i])) {
						$incidents[$i] = array(
							'status' => TRIGGER_VALUE_FALSE,
							'endTime' => $event['clock'],
							'ticket' => $event['acknowledges']['message']
						);
					}
					else {
						$i++;
						// get event start time
						$addEvent = API::Event()->get(array(
							'triggerids' => array($event['objectid']),
							'selectTriggers' => API_OUTPUT_REFER,
							'output' => API_OUTPUT_EXTEND,
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
							$eventTrigger = reset($addEvent['triggers']);

							if (in_array($eventTrigger['triggerid'], $dnsTriggers)) {
								$infoItemId = $dnsAvailItem;
							}
							elseif (in_array($eventTrigger['triggerid'], $dnssecTriggers)) {
								$infoItemId = $dnssecAvailItem;
							}
							elseif (in_array($eventTrigger['triggerid'], $rddsTriggers)) {
								$infoItemId = $rddsAvailItem;
							}
							elseif (in_array($eventTrigger['triggerid'], $eppTriggers)) {
								$infoItemId = $eppAvailItem;
							}

							$incidents[$i] = array(
								'objectid' => $event['objectid'],
								'eventid' => $addEvent['eventid'],
								'status' => TRIGGER_VALUE_FALSE,
								'startTime' => $addEvent['clock'],
								'endTime' => $event['clock'],
								'false_positive' => $event['false_positive'],
								'incidentTotalTests' => getTotalTestsCount(
									$infoItemId,
									$filterTimeFrom,
									$filterTimeTill,
									$addEvent['clock'],
									$event['clock']
								),
								'incidentFailedTests' => getFailedTestsCount(
									$infoItemId,
									$filterTimeTill,
									$addEvent['clock'],
									$event['clock']
								),
								'ticket' => $event['acknowledges']['message']
							);
						}
					}
				}

				if (in_array($eventTrigger['triggerid'], $dnsTriggers)) {
					if (isset($data['dns']['events'][$i])) {
						unset($data['dns']['events'][$i]['status']);

						$itemType = 'dns';
						$itemId = $dnsAvailItem;
						$getHistory = true;

						$data['dns']['events'][$i] = array_merge($data['dns']['events'][$i], $incidents[$i]);
					}
					else {
						if (isset($incidents[$i])) {
							$data['dns']['events'][$i] = $incidents[$i];
						}
					}
				}
				elseif (in_array($eventTrigger['triggerid'], $dnssecTriggers)) {
					if (isset($data['dnssec']['events'][$i])) {
						unset($data['dnssec']['events'][$i]['status']);

						$itemType = 'dnssec';
						$itemId = $dnssecAvailItem;
						$getHistory = true;

						$data['dnssec']['events'][$i] = array_merge($data['dnssec']['events'][$i], $incidents[$i]);
					}
					else {
						if (isset($incidents[$i])) {
							$data['dnssec']['events'][$i] = $incidents[$i];
						}
					}
				}
				elseif (in_array($eventTrigger['triggerid'], $rddsTriggers)) {
					if (isset($data['rdds']['events'][$i]) && $data['rdds']['events'][$i]) {
						unset($data['rdds']['events'][$i]['status']);

						$itemType = 'rdds';
						$itemId = $rddsAvailItem;
						$getHistory = true;

						$data['rdds']['events'][$i] = array_merge($data['rdds']['events'][$i], $incidents[$i]);
					}
					else {
						if (isset($incidents[$i])) {
							$data['rdds']['events'][$i] = $incidents[$i];
						}
					}
				}
				elseif (in_array($eventTrigger['triggerid'], $eppTriggers)) {
					if (isset($data['epp']['events'][$i]) && $data['epp']['events'][$i]) {
						unset($data['epp']['events'][$i]['status']);

						$itemType = 'epp';
						$itemId = $eppAvailItem;
						$getHistory = true;

						$data['epp']['events'][$i] = array_merge($data['epp']['events'][$i], $incidents[$i]);
					}
					else {
						if (isset($incidents[$i])) {
							$data['epp']['events'][$i] = $incidents[$i];
						}
					}
				}

				if ($getHistory) {
					$data[$itemType]['events'][$i]['incidentTotalTests'] = getTotalTestsCount(
						$itemId,
						$filterTimeFrom,
						$filterTimeTill,
						$data[$itemType]['events'][$i]['startTime'],
						$data[$itemType]['events'][$i]['endTime']
					);

					$data[$itemType]['events'][$i]['incidentFailedTests'] = getFailedTestsCount(
						$itemId,
						$filterTimeTill,
						$data[$itemType]['events'][$i]['startTime'],
						$data[$itemType]['events'][$i]['endTime']
					);

					unset($getHistory);
				}
			}

			if (isset($incidents[$i]) && $incidents[$i]['status'] == TRIGGER_VALUE_TRUE) {
				// get event end time
				$addEvent = API::Event()->get(array(
					'triggerids' => array($incidents[$i]['objectid']),
					'selectTriggers' => API_OUTPUT_REFER,
					'output' => API_OUTPUT_EXTEND,
					'time_from' => $filterTimeTill,
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

					$eventTrigger = reset($addEvent['triggers']);

					if (in_array($eventTrigger['triggerid'], $dnsTriggers)) {
						unset($data['dns']['events'][$i]['status']);
						$itemType = 'dns';
						$itemId = $dnsAvailItem;
						$data['dns']['events'][$i] = array_merge($data['dns']['events'][$i], $newData[$i]);
					}
					elseif (in_array($eventTrigger['triggerid'], $dnssecTriggers)) {
						unset($data['dnssec']['events'][$i]['status']);
						$itemType = 'dnssec';
						$itemId = $dnssecAvailItem;
						$data['dnssec']['events'][$i] = array_merge(
							$data['dnssec']['events'][$i],
							$newData[$i]
						);
					}
					elseif (in_array($eventTrigger['triggerid'], $rddsTriggers)) {
						unset($data['rdds']['events'][$i]['status']);
						$itemType = 'rdds';
						$itemId = $rddsAvailItem;
						$data['rdds']['events'][$i] = array_merge($data['rdds']['events'][$i], $newData[$i]);
					}
					elseif (in_array($eventTrigger['triggerid'], $eppTriggers)) {
						unset($data['epp']['events'][$i]['status']);
						$itemType = 'epp';
						$itemId = $eppAvailItem;
						$data['epp']['events'][$i] = array_merge($data['epp']['events'][$i], $newData[$i]);
					}

					$data[$itemType]['events'][$i]['incidentTotalTests'] = getTotalTestsCount(
						$itemId,
						$filterTimeFrom,
						$filterTimeTill,
						$data[$itemType]['events'][$i]['startTime'],
						$data[$itemType]['events'][$i]['endTime']
					);

					$data[$itemType]['events'][$i]['incidentFailedTests'] = getFailedTestsCount(
						$itemId,
						$filterTimeTill,
						$data[$itemType]['events'][$i]['startTime'],
						$data[$itemType]['events'][$i]['endTime']
					);
				}
				else {
					if (isset($data['dns']['events'][$i])) {
						$itemInfo = array(
							'itemType' => 'dns',
							'itemId' => $dnsAvailItem
						);
					}
					elseif (isset($data['dnssec']['events'][$i])) {
						$itemInfo = array(
							'itemType' => 'dnssec',
							'itemId' => $dnssecAvailItem
						);
					}
					elseif (isset($data['rdds']['events'][$i])) {
						$itemInfo = array(
							'itemType' => 'rdds',
							'itemId' => $rddsAvailItem
						);
					}
					elseif (isset($data['epp']['events'][$i])) {
						$itemInfo = array(
							'itemType' => 'epp',
							'itemId' => $eppAvailItem
						);
					}

					$data[$itemInfo['itemType']]['events'][$i]['incidentTotalTests'] = getTotalTestsCount(
						$itemInfo['itemId'],
						$filterTimeFrom,
						$filterTimeTill,
						$data[$itemInfo['itemType']]['events'][$i]['startTime']
					);

					$data[$itemInfo['itemType']]['events'][$i]['incidentFailedTests'] = getFailedTestsCount(
						$itemInfo['itemId'],
						$filterTimeTill,
						$data[$itemInfo['itemType']]['events'][$i]['startTime']
					);
				}
			}

			$data['dns']['totalTests'] = 0;
			$data['dnssec']['totalTests'] = 0;
			$data['rdds']['totalTests'] = 0;
			$data['epp']['totalTests'] = 0;
			$data['dns']['inIncident'] = 0;
			$data['dnssec']['inIncident'] = 0;
			$data['rdds']['inIncident'] = 0;
			$data['epp']['inIncident'] = 0;

			$availItems = array();
			if ($dnsAvailItem) {
				$availItems[] = $dnsAvailItem;
			}
			if ($dnssecAvailItem) {
				$availItems[] = $dnssecAvailItem;
			}
			if ($rddsAvailItem) {
				$availItems[] = $rddsAvailItem;
			}
			if ($eppAvailItem) {
				$availItems[] = $eppAvailItem;
			}

			$itemsHistories = DBselect(
				'SELECT h.clock, h.value, h.itemid'.
				' FROM history_uint h'.
				' WHERE '.dbConditionInt('h.itemid', $availItems).
					' AND h.clock>='.$filterTimeFrom.
					' AND h.clock<='.$filterTimeTill.
					' AND h.value=0'
			);

			while ($itemsHistory = DBfetch($itemsHistories)) {
				if ($itemsHistory['itemid'] == $dnsAvailItem) {
					$type = 'dns';
				}
				elseif ($itemsHistory['itemid'] == $dnssecAvailItem) {
					$type = 'dnssec';
				}
				elseif ($itemsHistory['itemid'] == $rddsAvailItem) {
					$type = 'rdds';
				}
				elseif ($itemsHistory['itemid'] == $eppAvailItem) {
					$type = 'epp';
				}

				$data[$type]['totalTests']++;

				foreach ($data[$type]['events'] as $incident) {
					if ($itemsHistory['clock'] >= $incident['startTime'] && (!isset($incident['endTime'])
							|| (isset($incident['endTime']) && $itemsHistory['clock'] <= $incident['endTime']))) {
						$data[$type]['inIncident']++;
					}
				}
			}
		}
	}
}

// data sorting
if (isset($data['dns']['events'])) {
	$data['dns']['events'] = array_reverse($data['dns']['events']);
}
if (isset($data['dnssec']['events'])) {
	$data['dnssec']['events'] = array_reverse($data['dnssec']['events']);
}
if (isset($data['rdds']['events'])) {
	$data['rdds']['events'] = array_reverse($data['rdds']['events']);
}
if (isset($data['epp']['events'])) {
	$data['epp']['events'] = array_reverse($data['epp']['events']);
}

$data['incident_type'] = get_request('incident_type', get_cookie('ui-tabs-1', 0));

$dnsTestView = new CView('dnstest.incidents.list', $data);

$dnsTestView->render();
$dnsTestView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
