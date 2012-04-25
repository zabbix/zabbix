<?php
/*
** Zabbix
** Copyright (C) 2001-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php

/*
 * Function: get_service_status
 *
 * Description:
 *     Retrieve true status
 *
 * Author:
 *     Aly
 *
 * Comments:
 *		Don't forget to sync code with C!!!!
 */
function get_service_status($serviceid, $algorithm, $triggerid = null, $status = 0) {
	if (is_numeric($triggerid)) {
		$status = ($serv_status = get_service_status_of_trigger($triggerid)) ? $serv_status : $status;
	}

	if ($algorithm == SERVICE_ALGORITHM_MAX || $algorithm == SERVICE_ALGORITHM_MIN) {
		$sort_order = ($algorithm == SERVICE_ALGORITHM_MAX) ? ' DESC' : '';

		$result = DBselect(
			'SELECT s.status'.
			' FROM services s,services_links l'.
			' WHERE l.serviceupid='.$serviceid.
				' AND s.serviceid=l.servicedownid'.
			' ORDER BY s.status'.$sort_order
		);
		if ($row = DBfetch($result)) {
			$status = $row['status'];
		}
	}
	return $status;
}

// TODO: move to CService
function expandPeriodicalServiceTimes(&$data, $period_start, $period_end, $ts_from, $ts_to, $type) {
	$week = getdate($period_start);
	$week = $period_start - $week['wday'] * SEC_PER_DAY - $week['hours'] * SEC_PER_HOUR - $week['minutes'] * SEC_PER_MIN - $week['seconds'];

	for (; $week < $period_end; $week += SEC_PER_WEEK) {
		$_s = $week + $ts_from;
		$_e = $week + $ts_to;

		if ($period_end < $_s || $period_start >= $_e) {
			continue;
		}

		if ($_s < $period_start) {
			$_s = $period_start;
		}
		if ($_e > $period_end) {
			$_e = $period_end;
		}

		if (isset($data[$_s][$type.'_s'])) {
			$data[$_s][$type.'_s']++;
		}
		else {
			$data[$_s][$type.'_s'] = 1;
		}

		if (isset($data[$_e][$type.'_e'])) {
			$data[$_e][$type.'_e']++;
		}
		else {
			$data[$_e][$type.'_e'] = 1;
		}
	}
}

function serviceAlgorythm($algorythm = null) {
	$algorythms = array(
		SERVICE_ALGORITHM_MAX => _('Problem, if at least one child has a problem'),
		SERVICE_ALGORITHM_MIN => _('Problem, if all children have problems'),
		SERVICE_ALGORITHM_NONE => _('Do not calculate')
	);

	if ($algorythm === null) {
		return $algorythms;
	}
	elseif (isset($algorythms[$algorythm])) {
		return $algorythms[$algorythm];
	}
	else {
		return false;
	}
}

function get_service_childs($serviceid, $soft = 0) {
	$childs = array();

	$result = DBselect(
		'SELECT sl.servicedownid'.
		' FROM services_links sl'.
		' WHERE sl.serviceupid='.$serviceid.
			($soft ? '' : ' AND sl.soft=0')
	);
	while ($row = DBfetch($result)) {
		$childs[] = $row['servicedownid'];
		$childs = array_merge($childs, get_service_childs($row['servicedownid']));
	}
	return $childs;
}

function createServiceTree(&$services, &$temp, $id = 0, $serviceupid = 0, $parentid = 0, $soft = 0, $linkid = '') {
	$rows = $services[$id];
	if ($rows['serviceid'] > 0 && $rows['caption'] != 'root') {
		$rows['algorithm'] = serviceAlgorythm($rows['algorithm']);
	}

	$rows['parentid'] = $parentid;
	if ($soft == 0) {
		$caption_tmp = $rows['caption'];
		$rows['caption'] = new CSpan($rows['caption'], 'link');
		$rows['caption']->setAttribute('onclick', 'javascript: call_menu(event, '.zbx_jsvalue($rows['serviceid']).','.zbx_jsvalue($caption_tmp).');');
		$temp[$rows['serviceid']] = $rows;

		if (isset($rows['childs'])) {
			foreach ($rows['childs'] as $nodeid) {
				if (!isset($services[$nodeid['id']])) {
					continue;
				}
				if (isset($services[$nodeid['id']]['serviceupid'])) {
					createServiceTree($services, $temp, $nodeid['id'], $services[$nodeid['id']]['serviceupid'], $rows['serviceid'], $nodeid['soft'], $nodeid['linkid']);
				}
			}
		}
	}
	else {
		if ($rows['serviceid'] != 0 && $linkid != 0) {
			$rows['caption'] = new CSpan($rows['caption'], 'unknown');
			$temp[$rows['serviceid'].'.'.$linkid] = $rows;
		}
	}
	return null;
}

// TODO: comment
// TODO: check that it works with permissions
// TODO: check sort order
// TODO: optimize trigger description expansion
function createShowServiceTree(array $services, array $slaData, array $parentService = array(), array $service = array(), array $dependency = array(), $tree = array()) {
	// if no parent service is given, start from the root
	if (!$service) {
		$serviceNode = array(
			'id' => 0,
			'serviceid' => 0,
			'parent' => array(),
			'parentid' => 0,
			'caption' => _('root'),
			'status' => SPACE,
			'sla' => SPACE,
			'sla2' => SPACE,
			'trigger' => array(),
			'dependencies' => array(),
			'reason' => SPACE,
			'graph' => SPACE,
		);

		// add all top level services as children of "root"
		foreach ($services as $topService) {
			if (!$topService['parent']) {
				$serviceNode['childs'][] = array(
					'servicedownid' => $topService['serviceid'],
					'soft' => 0,
					'linkid' => 0
				);
			}
		}

		$tree = array($serviceNode);
		$service = $serviceNode;
	}
	// create a not from the given service
	else {
		$serviceSla = $slaData[$service['serviceid']];

		// caption
		$trigger = $service['trigger'];
		$caption = array(get_node_name_by_elid($service['serviceid'], null, ': '), $service['name']);
		if ($trigger) {
			$url = new CLink(
				expand_trigger_description($trigger['triggerid']),
				'events.php?source='.EVENT_SOURCE_TRIGGERS.'&triggerid='.$trigger['triggerid']
			);
			$caption[] = ' [';
			$caption[] = $url;
			$caption[] = ']';
		}

		// reason
		$problemList = '-';
		if ($serviceSla['problems']) {
			$problemList = new CList(null, 'itservices');
			foreach ($serviceSla['problems'] as $problemTrigger) {
				$problemList->addItem(new CLink(expand_trigger_description($problemTrigger['triggerid']), 'events.php?source='.EVENT_SOURCE_TRIGGERS.'&triggerid='.$problemTrigger['triggerid']));
			}
		}

		// sla
		if ($service['showsla']) {
			$slaValues = reset($serviceSla['sla']);
			$slaGood = $slaValues['sla'];
			$slaBad = 100 - $slaValues['sla'];

			$p = min($slaBad, 20);

			$width = 160;
			$widthRed = $width * $p / 20;
			$widthGreen = $width - $widthRed;

			$chart1 = null;
			if ($widthGreen > 0) {
				$chart1 = new CDiv(null, 'sla-bar sla-green');
				$chart1->setAttribute('style', 'width: '.$widthGreen.'px;');
			}
			$chart2 = null;
			if ($widthRed > 0) {
				$chart2 = new CDiv(null, 'sla-bar sla-red');
				$chart2->setAttribute('style', 'width: '.$widthRed.'px;');
			}

			$slaBar = new CLink(array($chart1, $chart2, SPACE, new CSpan(sprintf('%.2f', $slaBad), 'underline')),
				'report3.php?serviceid='.$service['serviceid'].'&year='.date('Y'),
				'image '.(($service['goodsla'] > $slaGood) ? 'on' : 'off')
			);

			$sla = new CDiv($slaBar, 'invisible');
			$sla2 = array(
				new CSpan(sprintf('%.2f', $service['goodsla']), 'green'),
				' / ',
				new CSpan(sprintf('%.2f', $slaGood), ($service['goodsla'] > $slaGood) ? 'red' : 'green')
			);
		}
		else {
			$sla = '-';
			$sla2 = '-';
		}

		// TODO: proper child format
		// TODO: graph links
		// TODO: get rid of either "id" or "serviceid"
		$serviceNode = array(
			'id' => $service['serviceid'],
			'serviceid' => $service['serviceid'],
			'caption' => $caption,
			'graph' => !empty($service['triggerid']) ? new CLink(_('Show'), 'srv_status.php?serviceid='.$service['serviceid'].'&showgraph=1'.url_param('path')) : '-',
			'serviceup' => ($service['parent']) ? $service['parent']['serviceid'] : 0,
			'description' => ($service['trigger']) ? $service['trigger']['description'] : _('None'),
			'reason' => $problemList,
			'sla' => $sla,
			'sla2' => $sla2,
			'childs' => $service['dependencies'],
			'parentid' => ($parentService) ? $parentService['serviceid'] : 0,
			'status' => $serviceSla['status']
		);
	}

	// hard dependencies and dependencies for the "root" node
	if (!$dependency || $dependency['soft'] == 0) {
		$tree[$serviceNode['id']] = $serviceNode;

		foreach ($serviceNode['childs'] as $dependency) {
			$childService = $services[$dependency['servicedownid']];
			$tree = createShowServiceTree($services, $slaData, $service, $childService, $dependency, $tree);
		}
	}
	// soft dependencies
	else {
		$serviceNode['caption'] = new CSpan($serviceNode['caption']);
		$serviceNode['caption']->setAttribute('style', 'color: #888888;');

		$tree[$serviceNode['id'].'.'.$dependency['linkid']] = $serviceNode;
	}

	return $tree;
}

function del_empty_nodes($services) {
	do {
		unset($retry);
		foreach ($services as $id => $data) {
			if (isset($data['serviceupid']) && !isset($services[$data['serviceupid']])) {
				unset($services[$id]);
				$retry = true;
			}
		}
	} while (isset($retry));

	return $services;
}

/******************************************************************************
 *                                                                            *
 * Function: update_services_rec                                              *
 *                                                                            *
 * Purpose: re-calculate and updates status of the service and its childs     *
 *                                                                            *
 * Parameters: serviceid - item to update services for                        *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev   (PHP ver. by Aly)                               *
 *                                                                            *
 * Comments: recursive function   !!! Don't forget sync code with C !!!       *
 *                                                                            *
 ******************************************************************************/
function update_services_rec($serviceid) {
	$result = DBselect(
		'SELECT l.serviceupid,s.algorithm'.
		' FROM services_links l,services s'.
		' WHERE s.serviceid=l.serviceupid'.
			' AND l.servicedownid='.$serviceid
	);
	while ($row = DBfetch($result)) {
		$serviceupid = $row['serviceupid'];
		$algorithm = $row['algorithm'];

		if ($algorithm == SERVICE_ALGORITHM_MAX || $algorithm == SERVICE_ALGORITHM_MIN) {
			$status = get_service_status($serviceupid, $algorithm);
			add_service_alarm($serviceupid, $status, time());
			DBexecute('UPDATE services SET status='.$status.' WHERE serviceid='.$serviceupid);
		}
		elseif ($algorithm != SERVICE_ALGORITHM_NONE) {
			error(_('Unknown calculation algorithm of service status').SPACE.'['.$algorithm.']');
			return false;
		}
	}

	$result = DBselect('SELECT sl.serviceupid FROM services_links sl WHERE sl.servicedownid='.$serviceid);
	while ($row = DBfetch($result)) {
		$serviceupid = $row['serviceupid'];
		update_services_rec($serviceupid); // ATTENTION: recursion!!!
	}
}

/******************************************************************************
 *                                                                            *
 * Function: update_services                                                  *
 *                                                                            *
 * Purpose: re-calculate and updates status of the service and its childs     *
 * on trigger priority change                                                 *
 *                                                                            *
 * Parameters: serviceid - item to update services for                        *
 *             status - new status of the service                             *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev   (PHP ver. by Aly)                               *
 *                                                                            *
 * Comments: !!! Don't forget sync code with C !!!                            *
 *                                                                            *
 ******************************************************************************/
function update_services($triggerid, $status) {
	DBexecute('UPDATE services SET status='.$status.' WHERE triggerid='.$triggerid);

	$result = DBselect('SELECT s.serviceid FROM services s WHERE s.triggerid='.$triggerid);
	while ($row = DBfetch($result)) {
		add_service_alarm($row['serviceid'], $status, time());
		update_services_rec($row['serviceid']);
	}
}

/*
 * Function: update_services_status_all
 *
 * Description:
 * Cleaning parent nodes from triggers, updating ALL services status.
 *
 * Comments: !!! Don't forget sync code with C !!!
 *
 */
function update_services_status_all() {
	$result = DBselect(
		'SELECT s.serviceid,s.algorithm,s.triggerid'.
		' FROM services s'.
		' WHERE s.serviceid NOT IN ('.
			'SELECT DISTINCT sl.serviceupid'.
			' FROM services_links sl'.
		')'
	);
	while ($row = DBfetch($result)) {
		$status = get_service_status($row['serviceid'], $row['algorithm'], $row['triggerid']);
		DBexecute('UPDATE services SET status='.$status.' WHERE serviceid='.$row['serviceid']);
		add_service_alarm($row['serviceid'], $status, time());
	}

	$result = DBselect(
		'SELECT MAX(sl.servicedownid) AS serviceid,sl.serviceupid'.
		' FROM services_links sl'.
		' WHERE sl.servicedownid NOT IN ('.
			'SELECT DISTINCT sl.serviceupid FROM services_links sl'.
		')'.
		' GROUP BY sl.serviceupid'
	);
	while ($row = DBfetch($result)) {
		update_services_rec($row['serviceid']);
	}
}

/******************************************************************************
 *                                                                            *
 * Comments: !!! Don't forget sync code with C !!!                            *
 *                                                                            *
 ******************************************************************************/
function latest_service_alarm($serviceid, $status) {
	$result = DBselect(
		'SELECT sa.servicealarmid,sa.value'.
		' FROM service_alarms sa'.
		' WHERE sa.serviceid='.$serviceid.
		' ORDER BY sa.servicealarmid DESC', 1
	);
	$row = DBfetch($result);
	return ($row && $row['value'] == $status);
}

/******************************************************************************
 *                                                                            *
 * Comments: !!! Don't forget sync code with C !!!                            *
 *                                                                            *
 ******************************************************************************/
function add_service_alarm($serviceid, $status, $clock) {
	if (latest_service_alarm($serviceid, $status)) {
		return true;
	}
	return DBexecute('INSERT INTO service_alarms (servicealarmid,serviceid,clock,value) VALUES ('.get_dbid('service_alarms', 'servicealarmid').','.$serviceid.','.$clock.','.$status.')');
}

/**
 * Validate the new service time. Validation is implemented as a separate function to be available directly from the
 * frontend.
 *
 * @throws APIException if the given service time is invalid
 *
 * @param array $serviceTime
 *
 * @return void
 */
function checkServiceTime(array $serviceTime) {
	// type validation
	$serviceTypes = array(
		SERVICE_TIME_TYPE_DOWNTIME,
		SERVICE_TIME_TYPE_ONETIME_DOWNTIME,
		SERVICE_TIME_TYPE_UPTIME
	);
	if (!isset($serviceTime['type']) || !in_array($serviceTime['type'], $serviceTypes)) {
		throw new APIException(ZBX_API_ERROR_PARAMETERS, _('Incorrect service time type.'));
	}

	// one-time downtime validation
	if ($serviceTime['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME) {
		if (!isset($serviceTime['ts_from']) || !validateMaxTime($serviceTime['ts_from'])) {
			throw new APIException(ZBX_API_ERROR_PARAMETERS, _('Incorrect service start time.'));
		}
		if (!isset($serviceTime['ts_to']) || !validateMaxTime($serviceTime['ts_to'])) {
			throw new APIException(ZBX_API_ERROR_PARAMETERS, _('Incorrect service end time.'));
		}
	}
	// recurring downtime validation
	else {
		if (!isset($serviceTime['ts_from']) || !zbx_is_int($serviceTime['ts_from']) || $serviceTime['ts_from'] < 0 || $serviceTime['ts_from'] > SEC_PER_WEEK) {
			throw new APIException(ZBX_API_ERROR_PARAMETERS, _('Incorrect service start time.'));
		}
		if (!isset($serviceTime['ts_to']) || !zbx_is_int($serviceTime['ts_to']) || $serviceTime['ts_to'] < 0 || $serviceTime['ts_to'] > SEC_PER_WEEK) {
			throw new APIException(ZBX_API_ERROR_PARAMETERS, _('Incorrect service end time.'));
		}
	}

	if ($serviceTime['ts_from'] >= $serviceTime['ts_to']) {
		throw new APIException(ZBX_API_ERROR_PARAMETERS, _('Service start time must be less than end time.'));
	}
}
