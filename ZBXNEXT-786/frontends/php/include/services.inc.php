<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


function serviceAlgorythm($algorythm = null) {
	$algorythms = [
		SERVICE_ALGORITHM_MAX => _('Problem, if at least one child has a problem'),
		SERVICE_ALGORITHM_MIN => _('Problem, if all children have problems'),
		SERVICE_ALGORITHM_NONE => _('Do not calculate')
	];

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
	$childs = [];

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
function createServiceConfigurationTree(array $services, &$tree, array $parentService = [], array $service = [], array $dependency = []) {
	if (!$service) {
		$caption = new CLink(_('root'), '#');
		$caption->setMenuPopup(CMenuPopupHelper::getServiceConfiguration(null, _('root'), false));

		$serviceNode = [
			'id' => 0,
			'parentid' => 0,
			'caption' => $caption,
			'trigger' => [],
			'algorithm' => SPACE,
			'description' => SPACE
		];

		$service = $serviceNode;
		$service['serviceid'] = 0;
		$service['dependencies'] = [];
		$service['trigger'] = [];

		// add all top level services as children of "root"
		foreach ($services as $topService) {
			if (!$topService['parent']) {
				$service['dependencies'][] = [
					'servicedownid' => $topService['serviceid'],
					'soft' => 0,
					'linkid' => 0
				];
			}
		}

		$tree = [$serviceNode];
	}
	else {
		// caption
		$caption = new CLink($service['name'], '#');

		// service is deletable only if it has no hard dependency
		$deletable = true;
		foreach ($service['dependencies'] as $dep) {
			if ($dep['soft'] == 0) {
				$deletable = false;
				break;
			}
		}

		$caption->setMenuPopup(CMenuPopupHelper::getServiceConfiguration($service['serviceid'], $service['name'], $deletable));

		$serviceNode = [
			'id' => $service['serviceid'],
			'caption' => $caption,
			'description' => $service['trigger'] ? $service['trigger']['description'] : '',
			'parentid' => $parentService ? $parentService['serviceid'] : 0,
			'algorithm' => serviceAlgorythm($service['algorithm'])
		];
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
function createServiceMonitoringTree(array $services, array $slaData, $period, &$tree, array $parentService = [], array $service = [], array $dependency = []) {
	// if no parent service is given, start from the root
	if (!$service) {
		$serviceNode = [
			'id' => 0,
			'parentid' => 0,
			'caption' => _('root'),
			'status' => SPACE,
			'sla' => SPACE,
			'sla2' => SPACE,
			'trigger' => [],
			'reason' => SPACE,
			'graph' => SPACE,
		];

		$service = $serviceNode;
		$service['serviceid'] = 0;
		$service['dependencies'] = [];
		$service['trigger'] = [];

		// add all top level services as children of "root"
		foreach ($services as $topService) {
			if (!$topService['parent']) {
				$service['dependencies'][] = [
					'servicedownid' => $topService['serviceid'],
					'soft' => 0,
					'linkid' => 0
				];
			}
		}

		$tree = [$serviceNode];
	}
	// create a not from the given service
	else {
		$serviceSla = $slaData[$service['serviceid']];
		$slaValues = reset($serviceSla['sla']);

		// caption
		// remember the selected time period when following the bar link
		$periods = [
			'today' => 'daily',
			'week' => 'weekly',
			'month' => 'monthly',
			'year' => 'yearly',
			24 => 'daily',
			24 * 7 => 'weekly',
			24 * 30 => 'monthly',
			24 * DAY_IN_YEAR => 'yearly'
		];

		$caption = [new CLink(
			$service['name'],
			'report3.php?serviceid='.$service['serviceid'].'&year='.date('Y').'&period='.$periods[$period]
		)];
		$trigger = $service['trigger'];
		if ($trigger) {
			$url = new CLink($trigger['description'],
				'events.php?filter_set=1&source='.EVENT_SOURCE_TRIGGERS.'&triggerid='.$trigger['triggerid']
			);
			$caption[] = ' - ';
			$caption[] = $url;
		}

		// reason
		$problemList = '';
		if ($serviceSla['problems']) {
			$problemList = new CList([], 'service-problems');
			foreach ($serviceSla['problems'] as $problemTrigger) {
				$problemList->addItem(new CLink($problemTrigger['description'],
					'events.php?filter_set=1&source='.EVENT_SOURCE_TRIGGERS.'&triggerid='.$problemTrigger['triggerid']
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
			$bar = new CLink([
				$chart1,
				$chart2,
				new CDiv('80%', 'sla-bar-legend sla-bar-legend-start'),
				new CDiv('100%', 'sla-bar-legend sla-bar-legend-end')
			], 'srv_status.php?serviceid='.$service['serviceid'].'&showgraph=1'.url_param('path'));
			$bar = new CDiv($bar, 'sla-bar');
			$bar->setAttribute('title', _s('Only the last 20%% of the indicator is displayed.'));

			$slaBar = [
				$bar,
				new CSpan(sprintf('%.4f', $slaBad), 'sla-value '.(($service['goodsla'] > $slaGood) ? ZBX_STYLE_RED : ZBX_STYLE_GREEN))
			];

			$sla = new CDiv($slaBar, 'invisible');
			$sla2 = [
				new CSpan(sprintf('%.4f', $slaGood), 'sla-value '.(($service['goodsla'] > $slaGood) ? ZBX_STYLE_RED : ZBX_STYLE_GREEN)),
				'/',
				new CSpan(sprintf('%.4f', $service['goodsla']), 'sla-value')
			];
		}

		$serviceNode = [
			'id' => $service['serviceid'],
			'caption' => $caption,
			'description' => ($service['trigger']) ? $service['trigger']['description'] : _('None'),
			'reason' => $problemList,
			'sla' => $sla,
			'sla2' => $sla2,
			'parentid' => ($parentService) ? $parentService['serviceid'] : 0,
			'status' => ($serviceSla['status'] !== null) ? $serviceSla['status'] : ''
		];
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
 * Calculates the current IT service status based on it's child services.
 *
 * The new statuses are written to the $services array in the "newStatus" property.
 *
 * @param string $rootServiceId     id of the service to start calculation from
 * @param array $servicesLinks      array with service IDs as keys and arrays of child service IDs as values
 * @param array $services           array of services with IDs as keys
 * @param array $triggers           array of triggers with trigger IDs as keys
 */
function calculateItServiceStatus($rootServiceId, array $servicesLinks, array &$services, array $triggers) {
	$service = &$services[$rootServiceId];

	// don't calculate a thread if it is already calculated
	// it can be with soft links
	if (isset($service['newStatus'])) {
		return;
	}

	$newStatus = SERVICE_STATUS_OK;

	// leaf service with a trigger
	if ($service['triggerid'] != 0) {
		if ($service['algorithm'] != SERVICE_ALGORITHM_NONE) {
			$trigger = $triggers[$service['triggerid']];
			$newStatus = calculateItServiceStatusByTrigger($trigger['status'], $trigger['value'], $trigger['priority']);
		}
	}
	elseif (isset($servicesLinks[$rootServiceId])) {
		// calculate status depending on children status
		$statuses = [];

		foreach ($servicesLinks[$rootServiceId] as $rootServiceId) {
			calculateItServiceStatus($rootServiceId, $servicesLinks, $services, $triggers);
			$statuses[] = $services[$rootServiceId]['newStatus'];
		}

		if ($statuses && $service['algorithm'] != SERVICE_ALGORITHM_NONE) {
			$maxSeverity = max($statuses);

			// always return the maximum status of child services
			if ($service['algorithm'] == SERVICE_ALGORITHM_MAX && $maxSeverity != SERVICE_STATUS_OK) {
				$newStatus = $maxSeverity;
			}
			elseif (min($statuses) != SERVICE_STATUS_OK) {
				$newStatus = $maxSeverity;
			}
		}
	}

	$service['newStatus'] = $newStatus;
}

/**
 * Checks the status of the trigger and returns the corresponding service status.
 *
 * @param int $triggerStatus
 * @param int $triggerValue
 * @param int $triggerPriority
 *
 * @return int
 */
function calculateItServiceStatusByTrigger($triggerStatus, $triggerValue, $triggerPriority) {
	if ($triggerStatus == TRIGGER_STATUS_DISABLED || $triggerValue == TRIGGER_VALUE_FALSE) {
		return SERVICE_STATUS_OK;
	}

	return $triggerPriority;
}

/**
 * Updates the status of all IT services
 */
function updateItServices() {
	$servicesLinks = [];
	$services = [];
	$rootServiceIds = [];
	$triggers = [];

	// auxiliary arrays
	$triggerIds = [];
	$servicesLinksDown = [];

	$result = DBselect('SELECT sl.serviceupid,sl.servicedownid FROM services_links sl');

	while ($row = DBfetch($result)) {
		$servicesLinks[$row['serviceupid']][] = $row['servicedownid'];
		$servicesLinksDown[$row['servicedownid']] = true;
	}

	$result = DBselect('SELECT s.serviceid,s.algorithm,s.triggerid,s.status FROM services s ORDER BY s.serviceid');

	while ($row = DBfetch($result)) {
		$services[$row['serviceid']] = [
			'serviceid' => $row['serviceid'],
			'algorithm' => $row['algorithm'],
			'triggerid' => $row['triggerid'],
			'status' => $row['status']
		];

		if (!isset($servicesLinksDown[$row['serviceid']])) {
			$rootServiceIds[] = $row['serviceid'];
		}

		if ($row['triggerid'] != 0) {
			$triggerIds[$row['triggerid']] = true;
		}
	}

	if ($triggerIds) {
		$result = DBselect(
			'SELECT t.triggerid,t.priority,t.status,t.value'.
			' FROM triggers t'.
			' WHERE '.dbConditionInt('t.triggerid', array_keys($triggerIds))
		);

		while ($row = DBfetch($result)) {
			$triggers[$row['triggerid']] = [
				'priority' => $row['priority'],
				'status' => $row['status'],
				'value' => $row['value']
			];
		}
	}

	// clearing auxiliary variables
	unset($triggerIds, $servicesLinksDown);

	// calculating data
	foreach ($rootServiceIds as $rootServiceId) {
		calculateItServiceStatus($rootServiceId, $servicesLinks, $services, $triggers);
	}

	// updating changed data
	$updates = [];
	$inserts = [];
	$clock = time();

	foreach ($services as $service) {
		if ($service['newStatus'] != $service['status']) {
			$updates[] = [
				'values' => ['status' => $service['newStatus']],
				'where' =>  ['serviceid' => $service['serviceid']]
			];
			$inserts[] = [
				'serviceid' => $service['serviceid'],
				'clock' => $clock,
				'value' => $service['newStatus']
			];
		}
	}

	if ($updates) {
		DB::update('services', $updates);
		DB::insert('service_alarms', $inserts);
	}
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
	$serviceTypes = [
		SERVICE_TIME_TYPE_DOWNTIME,
		SERVICE_TIME_TYPE_ONETIME_DOWNTIME,
		SERVICE_TIME_TYPE_UPTIME
	];
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
