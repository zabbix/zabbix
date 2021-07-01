<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


function serviceAlgorithm($algorithm = null) {
	$algorithms = [
		SERVICE_ALGORITHM_MAX => _('Problem, if at least one child has a problem'),
		SERVICE_ALGORITHM_MIN => _('Problem, if all children have problems'),
		SERVICE_ALGORITHM_NONE => _('Do not calculate')
	];

	if ($algorithm === null) {
		return $algorithms;
	}
	elseif (isset($algorithms[$algorithm])) {
		return $algorithms[$algorithm];
	}
	else {
		return false;
	}
}

/**
 * Calculates the current service status based on it's child services.
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
 * Updates the status of all services
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

/**
 * Method to sort list of Services by 'sortorder' field and then by 'name' field if more entries has same 'sortorder'
 * value. Separate method is needed because entries make multilevel hierarchy and branches also must be sorted according
 * fields 'sortorder' and 'name'.
 *
 * @param array $services
 *
 * @return void
 */
function sortServices(array &$services) {
	$sort_options = [
		['field' => 'sortorder', 'order' => ZBX_SORT_UP],
		['field' => 'name', 'order' => ZBX_SORT_UP]
	];

	// Sort first level entries.
	CArrayHelper::sort($services, $sort_options);

	// Sort dependencies.
	foreach ($services as &$service) {
		if ($service['dependencies']) {
			foreach ($service['dependencies'] as &$dependent_item) {
				$dependent_item['name'] = $services[$dependent_item['serviceid']]['name'];
				$dependent_item['sortorder'] = $services[$dependent_item['serviceid']]['sortorder'];
			}
			unset($dependent_item);

			CArrayHelper::sort($service['dependencies'], $sort_options);
		}
	}
	unset($service);
}
