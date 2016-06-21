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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


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
			' WHERE l.serviceupid='.zbx_dbstr($serviceid).
				' AND s.serviceid=l.servicedownid'.
			' ORDER BY s.status'.$sort_order
		);
		if ($row = DBfetch($result)) {
			$status = $row['status'];
		}
	}
	return $status;
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
		' WHERE sl.serviceupid='.zbx_dbstr($serviceid).
			($soft ? '' : ' AND sl.soft=0')
	);
	while ($row = DBfetch($result)) {
		$childs[] = $row['servicedownid'];
		$childs = array_merge($childs, get_service_childs($row['servicedownid']));
	}
	return $childs;
}

/**
 * Creates nodes that can be used to display the service configuration tree using the CTree class.
 *
 * @see CTree
 *
 * @param array $services
 * @param array $parentService
 * @param array $service
 * @param array $dependency
 * @param array $tree
 */
function createServiceConfigurationTree(array $services, &$tree, array $parentService = array(), array $service = array(), array $dependency = array()) {
	if (!$service) {
		$caption = new CLink(_('root'), '#', 'service-conf-menu');
		$caption->setAttribute('data-menu', array(
			'serviceid' => 0,
			'name' => _('root'),
			'hasDependencies' => true
		));

		$serviceNode = array(
			'id' => 0,
			'parentid' => 0,
			'caption' => $caption,
			'trigger' => array(),
			'algorithm' => SPACE,
			'description' => SPACE
		);

		$service = $serviceNode;
		$service['serviceid'] = 0;
		$service['dependencies'] = array();
		$service['trigger'] = array();

		// add all top level services as children of "root"
		foreach ($services as $topService) {
			if (!$topService['parent']) {
				$service['dependencies'][] = array(
					'servicedownid' => $topService['serviceid'],
					'soft' => 0,
					'linkid' => 0
				);
			}
		}

		$tree = array($serviceNode);
	}
	else {
		// caption
		$caption = new CLink($service['name'], '#', 'service-conf-menu');

		// service is deletable only if it has no hard dependency
		$deletable = true;
		foreach ($service['dependencies'] as $dep) {
			if ($dep['soft'] == 0) {
				$deletable = false;
				break;
			}
		}

		$caption->setAttribute('data-menu', array(
			'serviceid' => $service['serviceid'],
			'name' => $service['name'],
			'deletable' => $deletable
		));

		$serviceNode = array(
			'id' => $service['serviceid'],
			'caption' => $caption,
			'description' => ($service['trigger']) ? $service['trigger']['description'] : '-',
			'parentid' => ($parentService) ? $parentService['serviceid'] : 0,
			'algorithm' => serviceAlgorythm($service['algorithm'])
		);
	}

	if (!$dependency || !$dependency['soft']) {
		$tree[$serviceNode['id']] = $serviceNode;

		foreach ($service['dependencies'] as $dependency) {
			$childService = $services[$dependency['servicedownid']];
			createServiceConfigurationTree($services, $tree, $service, $childService, $dependency);
		}
	}
	else {
		$serviceNode['caption'] = new CSpan($serviceNode['caption'], 'service-caption-soft');

		$tree[$serviceNode['id'].'.'.$dependency['linkid']] = $serviceNode;
	}
}

/**
 * Creates nodes that can be used to display the SLA report tree using the CTree class.
 *
 * @see CTree
 *
 * @param array $services       an array of services to display in the tree
 * @param array $slaData        sla report data, see CService::getSla()
 * @param $period
 * @param array $parentService
 * @param array $service
 * @param array $dependency
 * @param array $tree
 */
function createServiceMonitoringTree(array $services, array $slaData, $period, &$tree, array $parentService = array(), array $service = array(), array $dependency = array()) {
	// if no parent service is given, start from the root
	if (!$service) {
		$serviceNode = array(
			'id' => 0,
			'parentid' => 0,
			'caption' => _('root'),
			'status' => SPACE,
			'sla' => SPACE,
			'sla2' => SPACE,
			'trigger' => array(),
			'reason' => SPACE,
			'graph' => SPACE,
		);

		$service = $serviceNode;
		$service['serviceid'] = 0;
		$service['dependencies'] = array();
		$service['trigger'] = array();

		// add all top level services as children of "root"
		foreach ($services as $topService) {
			if (!$topService['parent']) {
				$service['dependencies'][] = array(
					'servicedownid' => $topService['serviceid'],
					'soft' => 0,
					'linkid' => 0
				);
			}
		}

		$tree = array($serviceNode);
	}
	// create a not from the given service
	else {
		$serviceSla = $slaData[$service['serviceid']];
		$slaValues = reset($serviceSla['sla']);

		// caption
		// remember the selected time period when following the bar link
		$periods = array(
			'today' => 'daily',
			'week' => 'weekly',
			'month' => 'monthly',
			'year' => 'yearly',
			24 => 'daily',
			24 * 7 => 'weekly',
			24 * 30 => 'monthly',
			24 * DAY_IN_YEAR => 'yearly'
		);

		$caption = array(new CLink(
			array(get_node_name_by_elid($service['serviceid'], null, ': '), $service['name']),
			'report3.php?serviceid='.$service['serviceid'].'&year='.date('Y').'&period='.$periods[$period]
		));
		$trigger = $service['trigger'];
		if ($trigger) {
			$url = new CLink($trigger['description'],
				'events.php?source='.EVENT_SOURCE_TRIGGERS.'&triggerid='.$trigger['triggerid']
			);
			$caption[] = ' - ';
			$caption[] = $url;
		}

		// reason
		$problemList = '-';
		if ($serviceSla['problems']) {
			$problemList = new CList(null, 'service-problems');
			foreach ($serviceSla['problems'] as $problemTrigger) {
				$problemList->addItem(new CLink($problemTrigger['description'],
					'events.php?source='.EVENT_SOURCE_TRIGGERS.'&triggerid='.$problemTrigger['triggerid']
				));
			}
		}

		// sla
		$sla = '-';
		$sla2 = '-';
		if ($service['showsla'] && $slaValues['sla'] !== null) {
			$slaGood = $slaValues['sla'];
			$slaBad = 100 - $slaValues['sla'];

			$p = min($slaBad, 20);

			$width = 160;
			$widthRed = $width * $p / 20;
			$widthGreen = $width - $widthRed;

			$chart1 = null;
			if ($widthGreen > 0) {
				$chart1 = new CDiv(null, 'sla-bar-part sla-green');
				$chart1->setAttribute('style', 'width: '.$widthGreen.'px;');
			}
			$chart2 = null;
			if ($widthRed > 0) {
				$chart2 = new CDiv(null, 'sla-bar-part sla-red');
				$chart2->setAttribute('style', 'width: '.$widthRed.'px;');
			}
			$bar = new CLink(array(
				$chart1,
				$chart2,
				new CDiv('80%', 'sla-bar-legend sla-bar-legend-start'),
				new CDiv('100%', 'sla-bar-legend sla-bar-legend-end')
			), 'srv_status.php?serviceid='.$service['serviceid'].'&showgraph=1'.url_param('path'));
			$bar = new CDiv($bar, 'sla-bar');
			$bar->setAttribute('title', _s('Only the last 20%% of the indicator is displayed.'));

			$slaBar = array(
				$bar,
				new CSpan(sprintf('%.4f', $slaBad), 'sla-value '.(($service['goodsla'] > $slaGood) ? 'red' : 'green'))
			);

			$sla = new CDiv($slaBar, 'invisible');
			$sla2 = array(
				new CSpan(sprintf('%.4f', $slaGood), 'sla-value '.(($service['goodsla'] > $slaGood) ? 'red' : 'green')),
				'/',
				new CSpan(sprintf('%.4f', $service['goodsla']), 'sla-value')
			);
		}

		$serviceNode = array(
			'id' => $service['serviceid'],
			'caption' => $caption,
			'description' => ($service['trigger']) ? $service['trigger']['description'] : _('None'),
			'reason' => $problemList,
			'sla' => $sla,
			'sla2' => $sla2,
			'parentid' => ($parentService) ? $parentService['serviceid'] : 0,
			'status' => ($serviceSla['status'] !== null) ? $serviceSla['status'] : '-'
		);
	}

	// hard dependencies and dependencies for the "root" node
	if (!$dependency || $dependency['soft'] == 0) {
		$tree[$serviceNode['id']] = $serviceNode;

		foreach ($service['dependencies'] as $dependency) {
			$childService = $services[$dependency['servicedownid']];
			createServiceMonitoringTree($services, $slaData, $period, $tree, $service, $childService, $dependency);
		}
	}
	// soft dependencies
	else {
		$serviceNode['caption'] = new CSpan($serviceNode['caption'], 'service-caption-soft');

		$tree[$serviceNode['id'].'.'.$dependency['linkid']] = $serviceNode;
	}
}

/**
 * Recalculates the status of the given service and it's parents.
 *
 * Note: this function does not update the status based on the status of the linked trigger,
 * the status is calculated only based on the status of the child services.
 *
 * @param $serviceid
 *
 * @return bool
 */
function update_services_rec($serviceid) {
	$result = DBselect(
		'SELECT l.serviceupid,s.algorithm'.
		' FROM services_links l,services s'.
		' WHERE s.serviceid=l.serviceupid'.
			' AND l.servicedownid='.zbx_dbstr($serviceid)
	);
	while ($row = DBfetch($result)) {
		$serviceupid = $row['serviceupid'];
		$algorithm = $row['algorithm'];

		if ($algorithm == SERVICE_ALGORITHM_MAX || $algorithm == SERVICE_ALGORITHM_MIN) {
			$status = get_service_status($serviceupid, $algorithm);
			add_service_alarm($serviceupid, $status, time());
			DBexecute('UPDATE services SET status='.zbx_dbstr($status).' WHERE serviceid='.zbx_dbstr($serviceupid));
		}
		elseif ($algorithm != SERVICE_ALGORITHM_NONE) {
			error(_('Unknown calculation algorithm of service status').SPACE.'['.$algorithm.']');
			return false;
		}
	}

	$result = DBselect('SELECT sl.serviceupid FROM services_links sl WHERE sl.servicedownid='.zbx_dbstr($serviceid));
	while ($row = DBfetch($result)) {
		$serviceupid = $row['serviceupid'];
		update_services_rec($serviceupid); // ATTENTION: recursion!!!
	}
}

/**
 * Retrieves the service linked to given trigger, sets it's status to $status and propagates the status change
 * to the parent services.
 *
 * @param $triggerid
 * @param $status
 */
function update_services($triggerid, $status) {
	DBexecute('UPDATE services SET status='.zbx_dbstr($status).' WHERE triggerid='.zbx_dbstr($triggerid));

	$result = DBselect('SELECT s.serviceid FROM services s WHERE s.triggerid='.zbx_dbstr($triggerid));
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
		DBexecute('UPDATE services SET status='.zbx_dbstr($status).' WHERE serviceid='.$row['serviceid']);
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
		' WHERE sa.serviceid='.zbx_dbstr($serviceid).
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
	return DBexecute('INSERT INTO service_alarms (servicealarmid,serviceid,clock,value) VALUES ('.get_dbid('service_alarms', 'servicealarmid').','.zbx_dbstr($serviceid).','.zbx_dbstr($clock).','.zbx_dbstr($status).')');
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
		if (!isset($serviceTime['ts_from']) || !validateUnixTime($serviceTime['ts_from'])) {
			throw new APIException(ZBX_API_ERROR_PARAMETERS, _('Incorrect service start time.'));
		}
		if (!isset($serviceTime['ts_to']) || !validateUnixTime($serviceTime['ts_to'])) {
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

/**
 * Convert 1.8 service time format (unixtime) to 2.0 format (seconds starting from Sunday).
 *
 * @param int $time
 *
 * @return int
 */
function prepareServiceTime($time) {
	return ($time > SEC_PER_WEEK * 2)
		? date('w', $time) * SEC_PER_DAY + ($time - mktime(null, null, null, date('n', $time), date('j', $time), date('Y', $time)))
		: $time;
}
