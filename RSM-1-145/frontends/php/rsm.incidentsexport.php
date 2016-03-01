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

$page['type'] = detect_page_type(PAGE_TYPE_CSV);
$page['file'] = 'incidents_export.csv';

$csvRows[] = array(
	_('IncidentID'),
	_('TLD'),
	_('Service'),
	_('Status'),
	_('StartTime'),
	_('EndTime'),
	_('FailedTestsWithinIncident')
);

require_once dirname(__FILE__).'/include/page_header.php';

$data = array();

// get TLDs
$tlds = API::Host()->get(array(
	'output' => array('hostid', 'host', 'name'),
	'tlds' => true
));

foreach ($tlds as $data['tld']) {
	// get items
	$items = API::Item()->get(array(
		'hostids' => $data['tld']['hostid'],
		'filter' => array(
			'key_' => array(
				RSM_SLV_DNS_ROLLWEEK, RSM_SLV_DNSSEC_ROLLWEEK, RSM_SLV_RDDS_ROLLWEEK,
				RSM_SLV_EPP_ROLLWEEK, RSM_SLV_DNS_AVAIL, RSM_SLV_DNSSEC_AVAIL, RSM_SLV_RDDS_AVAIL,
				RSM_SLV_EPP_AVAIL
			)
		),
		'output' => array('itemid', 'hostid', 'key_', 'lastvalue', 'lastclock'),
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
				case RSM_SLV_DNS_ROLLWEEK:
					$data['dns']['itemid'] = $item['itemid'];
					$data['dns']['slv'] = sprintf('%.3f', $item['lastvalue']);
					$data['dns']['slvTestTime'] = sprintf('%.3f', $item['lastclock']);
					$data['dns']['events'] = array();
					break;
				case RSM_SLV_DNSSEC_ROLLWEEK:
					$data['dnssec']['itemid'] = $item['itemid'];
					$data['dnssec']['slv'] = sprintf('%.3f', $item['lastvalue']);
					$data['dnssec']['slvTestTime'] = sprintf('%.3f', $item['lastclock']);
					$data['dnssec']['events'] = array();
					break;
				case RSM_SLV_RDDS_ROLLWEEK:
					$data['rdds']['itemid'] = $item['itemid'];
					$data['rdds']['slv'] = sprintf('%.3f', $item['lastvalue']);
					$data['rdds']['slvTestTime'] = sprintf('%.3f', $item['lastclock']);
					$data['rdds']['events'] = array();
					break;
				case RSM_SLV_EPP_ROLLWEEK:
					$data['epp']['itemid'] = $item['itemid'];
					$data['epp']['slv'] = sprintf('%.3f', $item['lastvalue']);
					$data['epp']['slvTestTime'] = sprintf('%.3f', $item['lastclock']);
					$data['epp']['events'] = array();
					break;
				case RSM_SLV_DNS_AVAIL:
					$data['dns']['availItemId'] = $item['itemid'];
					$dnsAvailItem = $item['itemid'];
					$dnsItems[] = $item['itemid'];
					$itemIds[] = $item['itemid'];
					break;
				case RSM_SLV_DNSSEC_AVAIL:
					$data['dnssec']['availItemId'] = $item['itemid'];
					$dnssecAvailItem = $item['itemid'];
					$dnssecItems[] = $item['itemid'];
					$itemIds[] = $item['itemid'];
					break;
				case RSM_SLV_RDDS_AVAIL:
					$data['rdds']['availItemId'] = $item['itemid'];
					$rddsAvailItem = $item['itemid'];
					$rddsItems[] = $item['itemid'];
					$itemIds[] = $item['itemid'];
					break;
				case RSM_SLV_EPP_AVAIL:
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

		// get events
		$events = API::Event()->get(array(
			'output' => API_OUTPUT_EXTEND,
			'triggerids' => $triggerIds,
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'selectTriggers' => API_OUTPUT_REFER
		));

		CArrayHelper::sort($events, array('objectid', 'clock'));

		$i = 0;
		$incidents = array();
		$lastEventValue = array();

		// data generation
		foreach ($events as $event) {
			$eventTriggerId = null;
			$getHistory = false;

			// ignore event duplicates
			$currentValue = ($event['value'] == TRIGGER_VALUE_UNKNOWN) ? TRIGGER_VALUE_FALSE : $event['value'];
			if (isset($lastEventValue[$event['objectid']])
					&& $lastEventValue[$event['objectid']] == $currentValue) {
				continue;
			}
			else {
				$lastEventValue[$event['objectid']] = $currentValue;
			}

			if ($event['value'] == TRIGGER_VALUE_TRUE) {
				if (isset($incidents[$i]) && $incidents[$i]['status'] == TRIGGER_VALUE_TRUE) {
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

				$eventTrigger = reset($event['triggers']);
				$eventTriggerId = $eventTrigger['triggerid'];

				$i++;
				$incidents[$i] = array(
					'eventid' => $event['eventid'],
					'objectid' => $event['objectid'],
					'status' => TRIGGER_VALUE_TRUE,
					'startTime' => $event['clock'],
					'false_positive' => $event['false_positive']
				);
			}
			else {
				if (isset($incidents[$i])) {
					if ($incidents[$i]['objectid'] == $event['objectid']) {
						$eventTriggerId = $incidents[$i]['objectid'];
						$incidents[$i]['status'] = $event['value'];
						$incidents[$i]['endTime'] = $event['clock'];
					}
				}
				else {
					$i++;
					// get event start time
					$addEvent = API::Event()->get(array(
						'output' => API_OUTPUT_EXTEND,
						'triggerids' => array($event['objectid']),
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
						$eventTrigger = reset($addEvent['triggers']);
						$eventTriggerId = $eventTrigger['triggerid'];

						if (in_array($eventTriggerId, $dnsTriggers)) {
							$infoItemId = $dnsAvailItem;
						}
						elseif (in_array($eventTriggerId, $dnssecTriggers)) {
							$infoItemId = $dnssecAvailItem;
						}
						elseif (in_array($eventTriggerId, $rddsTriggers)) {
							$infoItemId = $rddsAvailItem;
						}
						elseif (in_array($eventTriggerId, $eppTriggers)) {
							$infoItemId = $eppAvailItem;
						}

						$incidents[$i] = array(
							'objectid' => $event['objectid'],
							'eventid' => $addEvent['eventid'],
							'status' => $event['value'],
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
							)
						);
					}
				}
			}

			if (in_array($eventTriggerId, $dnsTriggers)) {
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
			elseif (in_array($eventTriggerId, $dnssecTriggers)) {
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
			elseif (in_array($eventTriggerId, $rddsTriggers)) {
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
			elseif (in_array($eventTriggerId, $eppTriggers)) {
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

		// input into rolling week calculation block
		$services = array();

		// get deleay items
		$itemKeys = array();
		if (isset($data['dns']['events']) || isset($data['dnssec']['events'])) {
			array_push($itemKeys, CALCULATED_ITEM_DNS_DELAY, CALCULATED_DNS_ROLLWEEK_SLA);
			if (isset($data['dns']['events'])) {
				$services['dns'] = array();
			}
			if (isset($data['dnssec']['events'])) {
				$services['dnssec'] = array();
			}
		}
		if (isset($data['rdds']['events'])) {
			array_push($itemKeys, CALCULATED_ITEM_RDDS_DELAY, CALCULATED_RDDS_ROLLWEEK_SLA);
		}
		if (isset($data['epp']['events'])) {
			array_push($itemKeys, CALCULATED_ITEM_EPP_DELAY, CALCULATED_EPP_ROLLWEEK_SLA);
		}

		if ($itemKeys) {
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

			$items = API::Item()->get(array(
				'hostids' => $rsm['hostid'],
				'output' => array('itemid', 'value_type', 'key_'),
				'filter' => array(
					'key_' => $itemKeys
				)
			));

			if (count($itemKeys) != count($items)) {
				show_error_message(_s('Missing service configuration items at host "%1$s".', RSM_HOST));
				require_once dirname(__FILE__).'/include/page_footer.php';
				exit;
			}

			// set rolling week time
			$weekTimeFrom = $serverTime - $rollWeekSeconds['value'];
			$weekTimeTill = $serverTime;

			// get SLA items
			foreach ($items as $item) {
				if ($item['key_'] === CALCULATED_DNS_ROLLWEEK_SLA || $item['key_'] === CALCULATED_RDDS_ROLLWEEK_SLA
						|| $item['key_'] === CALCULATED_EPP_ROLLWEEK_SLA) {
					// get last value
					$itemValue = API::History()->get(array(
						'itemids' => $item['itemid'],
						'time_from' => $weekTimeFrom,
						'history' => $item['value_type'],
						'output' => API_OUTPUT_EXTEND,
						'limit' => 1
					));

					$itemValue = reset($itemValue);

					if ($item['key_'] === CALCULATED_DNS_ROLLWEEK_SLA) {
						if (isset($services['dns'])) {
							$services['dns']['slaValue'] = $itemValue['value'];
						}
						if (isset($services['dnssec'])) {
							$services['dnssec']['slaValue'] = $itemValue['value'];
						}
					}
					elseif ($item['key_'] === CALCULATED_RDDS_ROLLWEEK_SLA) {
						$services['rdds']['slaValue'] = $itemValue['value'];
					}
					else {
						$services['epp']['slaValue'] = $itemValue['value'];
					}
				}
				else {
					// get last value
					$itemValue = API::History()->get(array(
						'itemids' => $item['itemid'],
						'time_till' => $weekTimeTill,
						'sortorder' => ZBX_SORT_DOWN,
						'sortfield' => array('clock'),
						'history' => $item['value_type'],
						'output' => API_OUTPUT_EXTEND,
						'limit' => 1
					));
					$itemValue = reset($itemValue);

					if ($item['key_'] == CALCULATED_ITEM_DNS_DELAY) {
						if (isset($services['dns'])) {
							$services['dns']['delay'] = $itemValue['value'];
							$services['dns']['itemId'] = $dnsAvailItem;
						}
						if (isset($services['dnssec'])) {
							$services['dnssec']['delay'] = $itemValue['value'];
							$services['dnssec']['itemId'] = $dnssecAvailItem;
						}
					}
					elseif ($item['key_'] == CALCULATED_ITEM_RDDS_DELAY) {
						$services['rdds']['delay'] = $itemValue['value'];
						$services['rdds']['itemId'] = $rddsAvailItem;
					}
					elseif ($item['key_'] == CALCULATED_ITEM_EPP_DELAY) {
						$services['epp']['delay'] = $itemValue['value'];
						$services['epp']['itemId'] = $eppAvailItem;
					}
				}
			}

			foreach ($services as $key => $service) {
				$data[$key]['slaValue'] = $service['slaValue'] * 60;
				$data[$key]['delay'] = $service['delay'];
			}
		}
	}

	// data sorting
	if (isset($data['dns']['events'])) {
		$data['dns']['events'] = array_reverse($data['dns']['events']);
		foreach ($data['dns']['events'] as $event) {
			if (!isset($event['startTime']) || !$event['startTime']) {
				continue;
			}
			$csvRows[] = array(
				$event['eventid'],
				$data['tld']['name'],
				_('DNS'),
				getIncidentStatus($event['false_positive'], $event['status']),
				date('d.m.Y H:i:s', $event['startTime']),
				isset($event['endTime']) ? date('d.m.Y H:i:s', $event['endTime']) : '',
				$event['incidentFailedTests']
			);
		}
	}
	if (isset($data['dnssec']['events'])) {
		$data['dnssec']['events'] = array_reverse($data['dnssec']['events']);
		foreach ($data['dnssec']['events'] as $event) {
			if (!isset($event['startTime']) || !$event['startTime']) {
				continue;
			}
			$csvRows[] = array(
				$event['eventid'],
				$data['tld']['name'],
				_('DNSSEC'),
				getIncidentStatus($event['false_positive'], $event['status']),
				date('d.m.Y H:i:s', $event['startTime']),
				isset($event['endTime']) ? date('d.m.Y H:i:s', $event['endTime']) : '',
				$event['incidentFailedTests']
			);
		}
	}
	if (isset($data['rdds']['events'])) {
		$data['rdds']['events'] = array_reverse($data['rdds']['events']);
		foreach ($data['rdds']['events'] as $event) {
			if (!isset($event['startTime']) || !$event['startTime']) {
				continue;
			}
			$csvRows[] = array(
				$event['eventid'],
				$data['tld']['name'],
				_('RDDS'),
				getIncidentStatus($event['false_positive'], $event['status']),
				date('d.m.Y H:i:s', $event['startTime']),
				isset($event['endTime']) ? date('d.m.Y H:i:s', $event['endTime']) : '',
				$event['incidentFailedTests']
			);
		}
	}
	if (isset($data['epp']['events'])) {
		$data['epp']['events'] = array_reverse($data['epp']['events']);
		foreach ($data['epp']['events'] as $event) {
			if (!isset($event['startTime']) || !$event['startTime']) {
				continue;
			}
			$csvRows[] = array(
				$event['eventid'],
				$data['tld']['name'],
				_('EPP'),
				getIncidentStatus($event['false_positive'], $event['status']),
				date('d.m.Y H:i:s', $event['startTime']),
				isset($event['endTime']) ? date('d.m.Y H:i:s', $event['endTime']) : '',
				$event['incidentFailedTests']
			);
		}
	}
}

print(zbx_toCSV($csvRows));
