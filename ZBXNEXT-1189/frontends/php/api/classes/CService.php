<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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

/**
 * Class containing methods for operations with IT Services
 * @package API
 */
class CService extends CZBXAPI {
	protected $tableName = 'services';
	protected $tableAlias = 's';
	protected $sortColumns = array('sortorder', 'name');

	public function __construct() {
		parent::__construct();

		// TODO: implement the selectTrigger option
		$this->getOptions = array_merge($this->getOptions, array(
			'parentids' => null,
			'childids' => null,
			'countOutput' => null,
			'selectParent' => null,
			'selectDependencies' => null,
			'selectDescendantDependencies' => null,
			'selectTimes' => null,
			'selectAlarms' => null,
			'selectTrigger' => null,
			'sortfield' => '',
			'sortorder' => '',
		));
	}

	/**
	 * Get services.
	 *
	 * Allowed options:
	 * - parentids          - fetch the services that are hardlinked to the given parent services;
	 * - childids        - fetch the services that are hardlinked to the given child services;
	 * - selectParent       - include the parent service in the result;
	 * - selectDependencies - include service dependencies in the result;
	 * - selectTimes        - include service times in the result.
	 *
	 * TODO: list all available options in the comments
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get(array $options) {
		$options = zbx_array_merge($this->getOptions, $options);

		// if the selectTrigger option is used, make sure we select the triggerid to be able to fetch the related triggers
		if ($options['selectTrigger'] !== null) {
			$options['output'] = $this->extendOutputOption($this->tableName(), 'triggerid', $options['output']);
		}

		// build and execute query
		$sql = $this->createSelectQuery($this->tableName(), $options);
		$res = DBselect($sql, $options['limit']);

		// fetch results
		$result = array();
		while ($row = DBfetch($res)) {
			// a count query, return a single result
			if ($options['countOutput'] !== null) {
				$result = $row['rowscount'];
			}
			// a normal select query
			else {
				$result[$row[$this->pk()]] = $this->unsetExtraFields($this->tableName(), $row, $options['output']);
			}
		}

		if ($options['countOutput'] !== null) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		if ($options['preservekeys'] === null) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $services
	 *
	 * @return void
	 */
	protected function validateCreate(array $services) {
		foreach ($services as $service) {
			$this->checkName($service);
			$this->checkAlgorithm($service);
			$this->checkShowSla($service);
			$this->checkGoodSla($service);
			$this->checkSortOrder($service);
			$this->checkTriggerId($service);
			$this->checkStatus($service);
			$this->checkParentId($service);

			$error = _s('Wrong fields for service "%1$s".', $service['name']);
			$this->checkUnsupportedFields($this->tableName(), $service, $error, array(
				'parentid', 'dependencies', 'times'
			));
		}

		$this->checkTriggerPermissions($services);
	}

	/**
	 * Creates the given services.
	 *
	 * @param array $services
	 *
	 * @return array
	 */
	public function create(array $services) {
		$services = zbx_toArray($services);
		$this->validateCreate($services);

		// save the services
		$serviceIds = DB::insert($this->tableName(), $services);

		$dependencies = array();
		$serviceTimes = array();
		foreach ($services as $key => $service) {
			$serviceId = $serviceIds[$key];

			// save dependencies
			if (!empty($service['dependencies'])) {
				foreach ($service['dependencies'] as $dependency) {
					$dependency['serviceid'] = $serviceId;
					$dependencies[] = $dependency;
				}
			}

			// save parent service
			if (!empty($service['parentid'])) {
				$dependencies[] = array(
					'serviceid' => $service['parentid'],
					'dependsOnServiceid' => $serviceId,
					'soft' => 0
				);
			}

			// save service times
			if (isset($service['times'])) {
				foreach ($service['times'] as $serviceTime) {
					$serviceTime['serviceid'] = $serviceId;
					$serviceTimes[] = $serviceTime;
				}
			}
		}

		if ($dependencies) {
			$this->addDependencies($dependencies);
		}

		if ($serviceTimes) {
			$this->addTimes($serviceTimes);
		}

		update_services_status_all();

		return array('serviceids' => $serviceIds);
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $services
	 *
	 * @return void
	 */
	public function validateUpdate(array $services) {
		foreach ($services as $service) {
			if (empty($service['serviceid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
		}

		$this->checkServicePermissions(zbx_objectValues($services, 'serviceid'));

		$services = $this->extendObjects($this->tableName(), $services, array('name'));
		foreach ($services as $service) {
			$this->checkName($service);

			if (isset($service['algorithm'])) {
				$this->checkAlgorithm($service);
			}
			if (isset($service['showsla'])) {
				$this->checkShowSla($service);
			}
			if (isset($service['goodsla'])) {
				$this->checkGoodSla($service);
			}
			if (isset($service['sortorder'])) {
				$this->checkSortOrder($service);
			}
			if (isset($service['triggerid'])) {
				$this->checkTriggerId($service);
			}
			if (isset($service['status'])) {
				$this->checkStatus($service);
			}
			if (isset($service['parentid'])) {
				$this->checkParentId($service);
			}

			$error = _s('Wrong fields for service "%1$s".', $service['name']);
			$this->checkUnsupportedFields($this->tableName(), $service, $error, array(
				'parentid', 'dependencies', 'times'
			));
		}

		$this->checkTriggerPermissions($services);
	}

	/**
	 * Updates the given services.
	 *
	 * @param array $services
	 *
	 * @return array
	 */
	public function update(array $services) {
		$services = zbx_toArray($services);
		$this->validateUpdate($services);

		// save the services
		foreach ($services as $service) {
			DB::updateByPk($this->tableName(), $service['serviceid'], $service);
		}

		// update dependencies
		$dependencies = array();
		$parentDependencies = array();
		$serviceTimes = array();
		$deleteParentsForServiceIds = array();
		$deleteDependenciesForServiceIds = array();
		foreach ($services as $service) {
			if (isset($service['dependencies'])) {
				$deleteDependenciesForServiceIds[] = $service['serviceid'];

				if ($service['dependencies']) {
					foreach ($service['dependencies'] as $dependency) {
						$dependency['serviceid'] = $service['serviceid'];
						$dependencies[] = $dependency;
					}
				}
			}

			// update parent
			if (isset($service['parentid'])) {
				$deleteParentsForServiceIds[] = $service['serviceid'];

				if ($service['parentid']) {
					$parentDependencies[] = array(
						'serviceid' => $service['parentid'],
						'dependsOnServiceid' => $service['serviceid'],
						'soft' => 0
					);
				}
			}

			// save service times
			if (isset($service['times'])) {
				foreach ($service['times'] as $serviceTime) {
					$serviceTime['serviceid'] = $service['serviceid'];
					$serviceTimes[] = $serviceTime;
				}
			}
		}

		// replace dependencies
		if ($deleteParentsForServiceIds) {
			$this->deleteParentDependencies(zbx_objectValues($services, 'serviceid'));
		}
		if ($deleteDependenciesForServiceIds) {
			$this->deleteDependencies(array_unique($deleteDependenciesForServiceIds));
		}
		if ($parentDependencies || $dependencies) {
			$this->addDependencies(array_merge($parentDependencies, $dependencies));
		}

		// replace service times
		if ($serviceTimes) {
			$this->deleteTimes(array_unique(zbx_objectValues($serviceTimes, 'serviceid')));
			$this->addTimes($serviceTimes);
		}

		update_services_status_all();

		return array('serviceids' => zbx_objectValues($services, 'serviceid'));
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $serviceIds
	 *
	 * @return void
	 */
	public function validateDelete($serviceIds) {
		if (!$serviceIds) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$this->checkServicePermissions($serviceIds);
		$this->checkThatServicesDontHaveChildren($serviceIds);
	}

	/**
	 * Delete services.
	 *
	 * @param $serviceIds
	 *
	 * @return array
	 */
	public function delete($serviceIds) {
		$serviceIds = zbx_toArray($serviceIds);
		$this->validateDelete($serviceIds);

		DB::delete($this->tableName(), array('serviceid' => $serviceIds));

		update_services_status_all();

		return array('serviceids' => $serviceIds);
	}

	/**
	 * Validates the input parameters for the addDependencies() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $dependencies
	 *
	 * @return void
	 */
	protected function validateAddDependencies(array $dependencies) {
		if (!$dependencies) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		foreach ($dependencies as $dependency) {
			if (empty($dependency['serviceid']) || empty($dependency['dependsOnServiceid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
		}

		$serviceIds = array_merge(
			zbx_objectValues($dependencies, 'serviceid'),
			zbx_objectValues($dependencies, 'dependsOnServiceid')
		);
		$serviceIds = array_unique($serviceIds);
		$this->checkServicePermissions($serviceIds);

		foreach ($dependencies as $dependency) {
			$this->checkDependency($dependency);

			$this->checkUnsupportedFields('services_links', $dependency,
				_s('Wrong fields for dependency for service "%1$s".', $dependency['serviceid']),
				array('dependsOnServiceid', 'serviceid')
			);
		}

		$this->checkForHardlinkedDependencies($dependencies);
		$this->checkThatParentsDontHaveTriggers($dependencies);
	}

	/**
	 * Add the given service dependencies.
	 *
	 * @param array $dependencies   an array of service dependencies, each pair in the form of
	 *                              array('serviceid' => 1, 'dependsOnServiceid' => 2, 'soft' => 0)
	 *
	 * @return array
	 */
	public function addDependencies(array $dependencies) {
		$dependencies = zbx_toArray($dependencies);
		$this->validateAddDependencies($dependencies);

		$data = array();
		foreach ($dependencies as $dependency) {
			$data[] = array(
				'serviceupid' => $dependency['serviceid'],
				'servicedownid' => $dependency['dependsOnServiceid'],
				'soft' => $dependency['soft']
			);
		}
		DB::insert('services_links', $data);

		return array('serviceids' => zbx_objectValues($dependencies, 'serviceid'));
	}

	/**
	 * Validates the input for the deleteDependencies() method.
	 *
	 * @throws APIException if the given input is invalid
	 *
	 * @param array $serviceIds
	 *
	 * @return void
	 */
	protected function validateDeleteDependencies(array $serviceIds) {
		if (!$serviceIds) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$this->checkServicePermissions($serviceIds);
	}

	/**
	 * Deletes all dependencies for the given services.
	 *
	 * @param array $serviceIds
	 *
	 * @return boolean
	 */
	public function deleteDependencies($serviceIds) {
		$serviceIds = zbx_toArray($serviceIds);
		$this->validateDeleteDependencies($serviceIds);

		DB::delete('services_links', array(
			'serviceupid' =>  $serviceIds
		));

		return array('serviceids' => $serviceIds);
	}

	/**
	 * Validates the input for the addTimes() method.
	 *
	 * @throws APIException if the given input is invalid
	 *
	 * @param array $serviceTimes
	 *
	 * @return void
	 */
	public function validateAddTimes(array $serviceTimes) {
		foreach ($serviceTimes as $serviceTime) {
			$this->checkTime($serviceTime);

			$this->checkUnsupportedFields('services_times', $serviceTime,
				_s('Wrong fields for time for service "%1$s".', $serviceTime['serviceid'])
			);
		}

		$this->checkServicePermissions(array_unique(zbx_objectValues($serviceTimes, 'serviceid')));
	}

	/**
	 * Adds the given service times.
	 *
	 * @param array $serviceTimes an array of service times
	 *
	 * @return array
	 */
	public function addTimes(array $serviceTimes) {
		$serviceTimes = zbx_toArray($serviceTimes);
		$this->validateAddTimes($serviceTimes);

		DB::insert('services_times', $serviceTimes);

		return array('serviceids' => zbx_objectValues($serviceTimes, 'serviceid'));
	}

	/**
	 * Validates the input for the deleteTimes() method.
	 *
	 * @throws APIException if the given input is invalid
	 *
	 * @param array $serviceIds
	 *
	 * @return void
	 */
	protected function validateDeleteTimes(array $serviceIds) {
		if (!$serviceIds) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$this->checkServicePermissions($serviceIds);
	}

	// TODO: comment
	// TODO: add interval support
	public function getSla(array $options) {
		$serviceIds = (isset($options['serviceids'])) ? zbx_toArray($options['serviceids']) : null;
		$intervals = (isset($options['intervals'])) ? zbx_toArray($options['intervals']) : array();

		// fetch services
		$services = $this->get(array(
			'output' => array('serviceid', 'name', 'status'),
			'selectTimes' => API_OUTPUT_EXTEND,
			'selectDescendantDependencies' => array('servicedownid'),
			'selectTrigger' => API_OUTPUT_EXTEND,
			'serviceids' => $serviceIds,
			'preservekeys' => true
		));
		$problemServiceIds = array();
		foreach ($services as &$service) {
			$service['alarms'] = array();

			if ($service['status'] > 0) {
				$problemServiceIds[] = $service['serviceid'];
			}
		}
		unset($service);

		// add service alarms
		$intervalConditions = array();
		foreach ($intervals as $interval) {
			$intervalConditions[] = 'sa.clock BETWEEN '.zbx_dbstr($interval['from']).' AND '.zbx_dbstr($interval['to']);
		}
		$query = DBselect(
			'SELECT *'.
				' FROM service_alarms sa'.
				' WHERE '.(($serviceIds) ? DBcondition('sa.serviceid', $serviceIds) : '').
				' AND ('.implode(' OR ', $intervalConditions).')'.
				' ORDER BY sa.clock,sa.servicealarmid'
		);
		while ($data = DBfetch($query)) {
			$services[$data['serviceid']]['alarms'][] = $data;
		}

		// fetch problem triggers
		if ($problemServiceIds) {
			$problemTriggers = $this->fetchProblemTriggers($problemServiceIds);
		}

		// calculate SLAs
		$rs = array();
		foreach ($services as $service) {
			// sla
			$sla = array();
			foreach ($intervals as $interval) {
				$intervalSla = $this->calculateSla($service, $interval['from'], $interval['to']);

				$sla[] = array(
					'from' => $interval['from'],
					'to' => $interval['to'],
					'sla' => $intervalSla['ok'],
					'okTime' => $intervalSla['okTime'],
					'problemTime' => $intervalSla['problemTime'],
					'downtimeTime' => $intervalSla['downtimeTime'],
				);
			}

			// add problem triggers
			$problems = array();
			$problemServices = zbx_objectValues($service['descendantDependencies'], 'servicedownid');
			$problemServices[] = $service['serviceid'];
			foreach ($problemServices as $serviceId) {
				if (isset($problemTriggers[$serviceId])) {
					$trigger = $problemTriggers[$serviceId];

					$problems[$trigger['triggerid']] = $trigger;
				}
			}

			$rs[$service['serviceid']] = array(
				'status' => $service['status'],
				'sla' => $sla,
				'problems' => $problems
			);
		}

		return $rs;
	}

	/**
	 * Deletes all service times for the given services.
	 *
	 * @param array $serviceIds
	 *
	 * @return boolean
	 */
	public function deleteTimes($serviceIds) {
		$serviceIds = zbx_toArray($serviceIds);
		$this->validateDeleteTimes($serviceIds);

		DB::delete('services_times', array(
			'serviceid' =>  $serviceIds
		));

		return array('serviceids' => $serviceIds);
	}

	/**
	 * Returns true if all of the given objects are available for reading.
	 *
	 * @param $ids
	 *
	 * @return bool
	 */
	public function isReadable(array $ids) {
		if (empty($ids)) {
			return true;
		}
		$ids = array_unique($ids);

		$count = $this->get(array(
			'serviceids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'countOutput' => true
		));
		return count($ids) == $count;
	}

	/**
	 * Returns true if all of the given objects are available for writing.
	 *
	 * @param $ids
	 *
	 * @return bool
	 */
	public function isWritable(array $ids) {
		return $this->isReadable($ids);
	}

	/**
	 * Deletes the the dependencies of the parent services on the given services.
	 *
	 * @param $serviceIds
	 *
	 * @return void
	 */
	protected function deleteParentDependencies($serviceIds) {
		DB::delete('services_links', array(
			'servicedownid' => $serviceIds,
			'soft' => 0
		));
	}

	// TODO: comment
	protected function calculateSla(array $service, $period_start, $period_end) {
		/* structure of "$data"
			 *	key	- time stamp
			 *	alarm	- on/off status (0,1 - off; >1 - on)
			 *	dt_s	- count of downtime starts
			 *	dt_e	- count of downtime ends
			 *	ut_s	- count of uptime starts
			 *	ut_e	- count of uptime ends
			 */

		// TODO: get the last alarm from $serviceAlarms
		$data[$period_start]['alarm'] = get_last_service_value($service['serviceid'], $period_start);

		foreach ($service['alarms'] as $alarm) {
			if ($alarm['clock'] >= $period_start && $alarm['clock'] <= $period_end) {
				$data[$alarm['clock']]['alarm'] = $alarm['value'];
			}
		}

		$unmarked_period_type = 'ut';

		foreach ($service['times'] as $time) {
			if ($time['type'] == SERVICE_TIME_TYPE_UPTIME) {
				expandPeriodicalServiceTimes($data, $period_start, $period_end, $time['ts_from'], $time['ts_to'], 'ut');
			}
			elseif ($time['type'] == SERVICE_TIME_TYPE_DOWNTIME) {
				expandPeriodicalServiceTimes($data, $period_start, $period_end, $time['ts_from'], $time['ts_to'], 'dt');

				// if exist any uptime - unmarked time is downtime
				$unmarked_period_type = 'dt';
			}
			elseif($time['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME && $time['ts_to'] >= $period_start && $time['ts_from'] <= $period_end) {
				if ($time['ts_from'] < $period_start) {
					$time['ts_from'] = $period_start;
				}
				if ($time['ts_to'] > $period_end) {
					$time['ts_to'] = $period_end;
				}

				if (isset($data[$time['ts_from']]['dt_s'])) {
					$data[$time['ts_from']]['dt_s']++;
				}
				else {
					$data[$time['ts_from']]['dt_s'] = 1;
				}

				if (isset($data[$time['ts_to']]['dt_e'])) {
					$data[$time['ts_to']]['dt_e']++;
				}
				else {
					$data[$time['ts_to']]['dt_e'] = 1;
				}
			}
		}

		if (!isset($data[$period_end])) {
			$data[$period_end] = array();
		}

		// sort by time stamp
		ksort($data);

		// calculate times
		$dt_cnt = 0;
		$ut_cnt = 0;
		$sla_time = array(
			'dt' => array('problemTime' => 0, 'okTime' => 0),
			'ut' => array('problemTime' => 0, 'okTime' => 0)
		);
		$prev_alarm = $data[$period_start]['alarm'];
		$prev_time = $period_start;

		if (isset($data[$period_start]['ut_s'])) {
			$ut_cnt += $data[$period_start]['ut_s'];
		}
		if (isset($data[$period_start]['ut_e'])) {
			$ut_cnt -= $data[$period_start]['ut_e'];
		}
		if (isset($data[$period_start]['dt_s'])) {
			$dt_cnt += $data[$period_start]['dt_s'];
		}
		if (isset($data[$period_start]['dt_e'])) {
			$dt_cnt -= $data[$period_start]['dt_e'];
		}
		foreach ($data as $ts => $val) {
			// skip first data [already readed]
			if ($ts == $period_start) {
				continue;
			}

			if ($dt_cnt > 0) {
				$period_type = 'dt';
			}
			elseif ($ut_cnt > 0) {
				$period_type = 'ut';
			}
			else {
				$period_type = $unmarked_period_type;
			}

			// state=0,1 [OK] (1 - information severity of trigger), >1 [PROBLEMS] (trigger severity)
			if ($prev_alarm > 1) {
				$sla_time[$period_type]['problemTime']	+= $ts - $prev_time;
			}
			else {
				$sla_time[$period_type]['okTime'] += $ts - $prev_time;
			}

			if (isset($val['ut_s'])) {
				$ut_cnt += $val['ut_s'];
			}
			if (isset($val['ut_e'])) {
				$ut_cnt -= $val['ut_e'];
			}
			if (isset($val['dt_s'])) {
				$dt_cnt += $val['dt_s'];
			}
			if (isset($val['dt_e'])) {
				$dt_cnt -= $val['dt_e'];
			}
			if (isset($val['alarm'])) {
				$prev_alarm = $val['alarm'];
			}

			$prev_time = $ts;
		}

		$sla_time['problemTime'] = &$sla_time['ut']['problemTime'];
		$sla_time['okTime'] = &$sla_time['ut']['okTime'];
		$sla_time['downtimeTime'] = $sla_time['dt']['okTime'] + $sla_time['dt']['problemTime'];

		$full_time = $sla_time['problemTime'] + $sla_time['okTime'];
		if ($full_time > 0) {
			$sla_time['problem'] = 100 * $sla_time['problemTime'] / $full_time;
			$sla_time['ok'] = 100 * $sla_time['okTime'] / $full_time;
		}
		else {
			$sla_time['problem'] = 100;
			$sla_time['ok'] = 100;
		}

		return $sla_time;
	}

	/**
	 * Returns an array of triggers which are in a problem state and are linked to the given services.
	 *
	 * @param array $serviceIds
	 *
	 * @return array    in the form of array(serviceId1 => trigger1, serviceId2 => trigger2)
	 */
	protected function fetchProblemTriggers(array $serviceIds) {
		// get service reason
		$triggers = DBfetchArray(DBSelect(
			'SELECT s.serviceid,t.*'.
				' FROM services s,triggers t'.
				' WHERE s.status>0'.
				' AND s.triggerid IS NOT NULL'.
				' AND t.triggerid=s.triggerid'.
				' AND '.DBcondition('t.triggerid', get_accessible_triggers(PERM_READ_ONLY)).
				' AND '.DBcondition('s.serviceid', $serviceIds).
				' ORDER BY s.status DESC,t.description'
		));

		$rs = array();
		foreach ($triggers as $trigger) {
			$serviceId = $trigger['serviceid'];
			unset($trigger['serviceid']);

			$rs[$serviceId] = $trigger;
		}

		return $rs;
	}

	/**
	 * Returns an array of descendant service dependencies for each service.
	 *
	 * @param array $parentServiceIds
	 * @param $output
	 *
	 * @return array    in the form of array(serviceId1 => array(...), serviceId2 => array(...))
	 */
	protected function getDescendantDependencies(array $parentServiceIds, $output) {
		$extendedOutput = $this->extendOutputOption('services_links', array('linkid', 'serviceupid', 'servicedownid'), $output);

		// fetch all descendant dependencies
		$dependencies = $this->fetchDescendantDependencies($parentServiceIds, $extendedOutput);
		$dependencies = array_reverse($dependencies);

		// build up the descendant dependency list starting from the bottom
		$rs = array();
		foreach ($dependencies as $dependency) {
			if (!isset($rs[$dependency['serviceupid']])) {
				$rs[$dependency['serviceupid']] = array();
			}

			// if the child host has dependencies of it's own, add them too
			if (isset($rs[$dependency['servicedownid']])) {
				$rs[$dependency['serviceupid']] = zbx_array_merge($rs[$dependency['serviceupid']], $rs[$dependency['servicedownid']]);
			}

			$rs[$dependency['serviceupid']][$dependency['linkid']] = $this->unsetExtraFields('services_links', $dependency, $output);
		}

		// filter the service that have been requested
		$finalResult = array();
		foreach ($parentServiceIds as $serviceId) {
			$finalResult[$serviceId] = (isset($rs[$serviceId])) ? $rs[$serviceId] : array();
		}

		return $finalResult;
	}

	/**
	 * Returns an array of dependencies that are descendants of the given services.
	 *
	 * @param array $parentServiceIds
	 * @param $output
	 *
	 * @return array
	 *
	 * TODO: permission check
	 */
	protected function fetchDescendantDependencies(array $parentServiceIds, $output) {
		$rs = API::getApi()->select('services_links', array(
			'output' => $output,
			'filter' => array(
				'serviceupid' => $parentServiceIds
			)
		));

		if ($rs) {
			$rs = array_merge($rs, $this->fetchDescendantDependencies(zbx_objectValues($rs, 'servicedownid'), $output));
		}

		return $rs;
	}

	/**
	 * Validates the "name" field.
	 *
	 * @throws APIException if the name is missing
	 *
	 * @param array $service
	 *
	 * @return void
	 */
	protected function checkName(array $service) {
		if (!isset($service['name']) || zbx_empty($service['name'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty name.'));
		}
	}

	/**
	 * Validates the "algorithm" field. Assumes the "name" field is valid.
	 *
	 * @throws APIException if the name is missing or invalid
	 *
	 * @param array $service
	 *
	 * @return void
	 */
	protected function checkAlgorithm(array $service) {
		if (!isset($service['algorithm']) || !serviceAlgorythm($service['algorithm'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect algorithm for service "%1$s".', $service['name']));
		}
	}

	/**
	 * Validates the "showsla" field. Assumes the "name" field is valid.
	 *
	 * @throws APIException if the name is missing or is not a boolean value
	 *
	 * @param array $service
	 *
	 * @return void
	 */
	protected function checkShowSla(array $service) {
		$showSlaValues = array(
			SERVICE_SHOW_SLA_OFF => true,
			SERVICE_SHOW_SLA_ON => true,
		);
		if (!isset($service['showsla']) || !isset($showSlaValues[$service['showsla']])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect calculate SLA value for service "%1$s".', $service['name']));
		}
	}

	/**
	 * Validates the "showsla" field. Assumes the "name" field is valid.
	 *
	 * @throws APIException if the value is missing, or is out of bounds
	 *
	 * @param array $service
	 *
	 * @return void
	 */
	protected function checkGoodSla(array $service) {
		if ((!empty($service['showsla']) && empty($service['goodsla']))
				|| (isset($service['goodsla'])
					&& (!is_numeric($service['goodsla']) || $service['goodsla'] < 0 || $service['goodsla'] > 100))) {

			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect acceptable SLA for service "%1$s".', $service['name']));
		}
	}

	/**
	 * Validates the "sortorder" field. Assumes the "name" field is valid.
	 *
	 * @throws APIException if the value is missing, or is out of bounds
	 *
	 * @param array $service
	 *
	 * @return void
	 */
	protected function checkSortOrder(array $service) {
		if (!isset($service['sortorder']) || !zbx_is_int($service['sortorder'])
			|| $service['sortorder'] < 0 || $service['sortorder'] > 999) {

			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect sorder order for service "%1$s".', $service['name']));
		}
	}

	/**
	 * Validates the "triggerid" field. Assumes the "name" field is valid.
	 *
	 * @throws APIException if the value is incorrect
	 *
	 * @param array $service
	 *
	 * @return void
	 */
	protected function checkTriggerId(array $service) {
		if (!empty($service['triggerid']) && !zbx_is_int($service['triggerid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect trigger ID for service "%1$s".', $service['name']));
		}
	}

	/**
	 * Validates the "parentid" field. Assumes the "name" field is valid.
	 *
	 * @throws APIException if the value is incorrect
	 *
	 * @param array $service
	 *
	 * @return void
	 */
	protected function checkParentId(array $service) {
		if (!empty($service['parentid']) && !zbx_is_int($service['parentid'])) {
			if (isset($service['name'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect parent for service "%1$s".', $service['name']));
			}
			else {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect parent service.'));
			}
		}

		if (isset($service['serviceid']) && idcmp($service['serviceid'], $service['parentid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Service cannot be parent and child at the same time.'));
		}
	}

	/**
	 * Validates the "status" field. Assumes the "name" field is valid.
	 *
	 * @throws APIException if the value is incorrect
	 *
	 * @param array $service
	 *
	 * @return void
	 */
	protected function checkStatus(array $service) {
		if (!empty($service['status']) && !zbx_is_int($service['status'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect status for service "%1$s".', $service['name']));
		}
	}

	/**
	 * Checks that the user has read access to the given triggers.
	 *
	 * @throws APIException if the user doesn't have permission to access any of the triggers
	 *
	 * @param array $services
	 *
	 * @return void
	 */
	protected function checkTriggerPermissions(array $services) {
		$triggerIds = array();
		foreach ($services as $service) {
			if (!empty($service['triggerid'])) {
				$triggerIds[] = $service['triggerid'];
			}
		}
		if (!API::Trigger()->isReadable($triggerIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Checks that all of the given services are readable.
	 *
	 * @throws APIException if at least one of the services doesn't exist
	 *
	 * @param array $serviceIds
	 *
	 * @return void
	 */
	protected function checkServicePermissions(array $serviceIds) {
		if (!$this->isReadable($serviceIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Checks that none of the given services have any children.
	 *
	 * @throws APIException if at least one of the services has a child service
	 *
	 * @param array $serviceIds
	 *
	 * @return void
	 */
	protected function checkThatServicesDontHaveChildren(array $serviceIds) {
		$child = API::getApi()->select('services_links', array(
			'output' => array('serviceupid'),
			'filter' => array(
				'serviceupid' => $serviceIds,
				'soft' => 0
			),
			'limit' => 1
		));
		$child = reset($child);
		if ($child) {
			$service = API::getApi()->select($this->tableName(), array(
				'output' => array('name'),
				'serviceids' => $child['serviceupid'],
				'limit' => 1
			));
			$service = reset($service);
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('Service "%1$s" cannot be deleted, because it is dependent on another service.', $service['name'])
			);
		}
	}

	/**
	 * Checks that the given dependency is valid.
	 *
	 * @throws APIException if the dependency is invalid
	 *
	 * @param array $dependency
	 *
	 * @return void
	 */
	protected function checkDependency(array $dependency) {
		if (idcmp($dependency['serviceid'], $dependency['dependsOnServiceid'])) {
			$service = API::getApi()->select($this->tableName(), array(
				'output' => array('name'),
				'serviceids' => $dependency['serviceid']
			));
			$service = reset($service);
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Service "%1$s" cannot be dependent on itself.', $service['name']));
		}

		// check 'soft' field value
		if (!isset($dependency['soft']) || !in_array((int) $dependency['soft'], array(0, 1), true)) {
			$service = API::getApi()->select($this->tableName(), array(
				'output' => array('name'),
				'serviceids' => $dependency['serviceid']
			));
			$service = reset($service);
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect "soft" field value for dependency for service "%1$s".', $service['name'])
			);
		}
	}

	/**
	 * Checks that that none of the given services are hard linked to a different service.
	 * Assumes the dependencies are valid.
	 *
	 * @throws APIException if at a least one service is hard linked to another service
	 *
	 * @param array $dependencies
	 *
	 * @return void
	 */
	protected function checkForHardlinkedDependencies(array $dependencies) {
		// only check hard dependencies
		$softDepServiceIds = array();
		foreach ($dependencies as $dependency) {
			if (!$dependency['soft']) {
				$softDepServiceIds[] = $dependency['dependsOnServiceid'];
			}
		}

		if ($softDepServiceIds) {
			// look for at least one hardlinked service among the given
			$softDepServiceIds = array_unique($softDepServiceIds);
			$dep = API::getApi()->select('services_links', array(
				'output' => array('servicedownid'),
				'filter' => array(
					'soft' => 0,
					'servicedownid' => $softDepServiceIds
				),
				'limit' => 1
			));
			if ($dep) {
				$dep = reset($dep);
				$service = API::getApi()->select($this->tableName(), array(
					'output' => array('name'),
					'serviceids' => $dep['servicedownid']
				));
				$service = reset($service);
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Service "%1$s" is already hardlinked to a different service.', $service['name'])
				);
			}
		}
	}

	/**
	 * Checks that none of the parent services are linked to a trigger. Assumes the dependencies are valid.
	 *
	 * @throws APIException if at least one of the parent services is linked to a trigger
	 *
	 * @param array $dependencies
	 *
	 * @return void
	 */
	protected function checkThatParentsDontHaveTriggers(array $dependencies) {
		$parentServiceIds = array_unique(zbx_objectValues($dependencies, 'serviceid'));
		if ($parentServiceIds) {
			$query = DBselect(
				'SELECT s.triggerid,s.name'.
					' FROM services s '.
					' WHERE '.DBcondition('s.serviceid', $parentServiceIds).
					' AND s.triggerid IS NOT NULL', 1);
			if ($parentService = DBfetch($query)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Service "%1$s" cannot be linked to a trigger and have children at the same time.', $parentService['name']));
			}
		}
	}

	/**
	 * Checks that the given service time is valid.
	 *
	 * @throws APIException if the service time is invalid
	 *
	 * @param array $serviceTime
	 *
	 * @return void
	 */
	protected function checkTime(array $serviceTime) {
		if (empty($serviceTime['serviceid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
		}

		checkServiceTime($serviceTime);
	}

	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		if (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) {
			$accessibleTriggers = get_accessible_triggers(PERM_READ_ONLY);
			// if services with specific trigger IDs were requested, return only the ones accessible to the current user.
			if ($options['filter']['triggerid']) {
				$options['filter']['triggerid'] = array_intersect($accessibleTriggers, $options['filter']['triggerid']);
			}
			// otherwise return services with either no triggers, or any trigger accessible to the current user
			else {
				$sqlParts['where'][] = '('.$this->fieldId('triggerid').' IS NULL OR '.DBcondition($this->fieldId('triggerid'), $accessibleTriggers).')';
			}
		}

		$sqlParts = parent::applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

		// parentids
		if ($options['parentids'] !== null) {
			$sqlParts['from'][] = 'services_links slp';
			$sqlParts['where'][] = $this->fieldId('serviceid').'=slp.servicedownid AND slp.soft=0';
			$sqlParts['where'][] = DBcondition('slp.serviceupid', (array) $options['parentids']);
		}
		// childids
		if ($options['childids'] !== null) {
			$sqlParts['from'][] = 'services_links slc';
			$sqlParts['where'][] = $this->fieldId('serviceid').'=slc.serviceupid AND slc.soft=0';
			$sqlParts['where'][] = DBcondition('slc.servicedownid', (array) $options['childids']);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$serviceIds = array_keys($result);

		// selectDependencies
		if ($options['selectDependencies'] !== null) {
			$dependencyOutput = $this->extendOutputOption('services_links', 'serviceupid', $options['selectDependencies']);
			$dependencies = API::getApi()->select('services_links', array(
				'output' => $dependencyOutput,
				'filter' => array('serviceupid' => $serviceIds)
			));
			foreach ($result as &$service) {
				$service['dependencies'] = array();
			}
			unset($service);
			foreach ($dependencies as $dependency) {
				$refId = $dependency['serviceupid'];
				$dependency = $this->unsetExtraFields('services_links', $dependency, $options['selectDependencies']);
				$result[$refId]['dependencies'][] = $dependency;
			}
		}

		// selectDescendantDependencies
		if ($options['selectDescendantDependencies']) {
			$dependencies = $this->getDescendantDependencies($serviceIds, $options['selectDescendantDependencies']);
			foreach ($result as &$service) {
				$service['descendantDependencies'] = $dependencies[$service['serviceid']];
			}
			unset($service);
		}

		// selectParent
		if ($options['selectParent'] !== null) {
			$parents = $this->get(array(
				'output' => $options['selectParent'],
				'childids' => $serviceIds,
				'selectDependencies' => array('servicedownid', 'soft')
			));
			foreach ($result as &$service) {
				$service['parent'] = array();
			}
			unset($service);

			// map the parents to their children, look for the first hard linked dependency
			foreach ($parents as $parent) {
				foreach ($parent['dependencies'] as $dependency) {
					if (!$dependency['soft']) {
						unset($parent['dependencies']);
						if (isset($result[$dependency['servicedownid']])) {
							$result[$dependency['servicedownid']]['parent'] = $parent;
						}
					}
				}
			}
		}

		// selectTimes
		if ($options['selectTimes'] !== null) {
			$timesOutput = $this->extendOutputOption('services_times', 'serviceid', $options['selectTimes']);
			$serviceTimes = API::getApi()->select('services_times', array(
				'output' => $timesOutput,
				'filter' => array('serviceid' => $serviceIds)
			));
			foreach ($result as &$service) {
				$service['times'] = array();
			}
			unset($service);
			foreach ($serviceTimes as $serviceTime) {
				$refId = $serviceTime['serviceid'];
				$serviceTime = $this->unsetExtraFields('services_times', $serviceTime, $options['selectTimes']);
				$result[$refId]['times'][] = $serviceTime;
			}
		}

		// selectAlarms
		if ($options['selectAlarms'] !== null) {
			$alarmsOutput = $this->extendOutputOption('service_alarms', 'serviceid', $options['selectAlarms']);
			$alarmsTimes = API::getApi()->select('service_alarms', array(
				'output' => $alarmsOutput,
				'filter' => array('serviceid' => $serviceIds)
			));
			foreach ($result as &$service) {
				$service['times'] = array();
			}
			unset($service);
			foreach ($alarmsTimes as $serviceAlarm) {
				$refId = $serviceAlarm['serviceid'];
				$serviceAlarm = $this->unsetExtraFields('service_alarms', $serviceAlarm, $options['selectAlarms']);
				$result[$refId]['times'][] = $serviceAlarm;
			}
		}

		// selectTrigger
		if ($options['selectTrigger'] !== null) {
			$triggers = API::getApi()->select('triggers', array(
				'output' => $options['selectTrigger'],
				'triggerids' => zbx_objectValues($result, 'triggerid'),
				'preservekeys' => true,
			));
			foreach ($result as &$service) {
				$service['trigger'] = ($service['triggerid']) ? $triggers[$service['triggerid']] : array();
			}
			unset($service);
		}

		return $result;
	}
}
