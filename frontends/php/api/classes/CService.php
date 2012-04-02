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

	public function __construct() {
		parent::__construct();

		$this->getOptions = array_merge($this->getOptions, array(
			'parentids' => null
		));
	}

	/**
	 * Get services.
	 *
	 * Allowed options:
	 * - parentids - fetch the services that are hardlinked to the given parent services.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get(array $options) {
		$options = zbx_array_merge($this->getOptions, $options);

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
				if ($options['preservekeys'] !== null) {
					$result[$row['scriptid']] = $this->unsetExtraFields($this->tableName(), $row, $options['output']);
				}
				else {
					$result[] = $this->unsetExtraFields($this->tableName(), $row, $options['output']);
				}
			}
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
			if (isset($service['parentid'])) {
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
		$serviceTimes = array();
		$deleteParentsFromServiceIds = array();
		foreach ($services as $service) {
			if (!empty($service['dependencies'])) {
				foreach ($service['dependencies'] as $dependency) {
					$dependency['serviceid'] = $service['serviceid'];
					$dependencies[] = $dependency;
				}
			}

			// update parent
			if (isset($service['parentid'])) {
				// unset a parent
				if ($service['parentid'] === 0) {
					$deleteParentsFromServiceIds[] = $service['serviceid'];
				}
				// set a new parent
				else {
					$dependencies[] = array(
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

		// unset parents
		if ($deleteParentsFromServiceIds) {
			$this->deleteParentDependencies($deleteParentsFromServiceIds);
		}

		// replace dependencies
		if ($dependencies) {
			$this->deleteDependencies(array_unique(zbx_objectValues($dependencies, 'serviceid')));
			$this->addDependencies($dependencies);
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
			$this->checkDependency($dependency);

			$this->checkUnsupportedFields('services_links', $dependency,
				_s('Wrong fields for dependency for service "%1$s".', $dependency['serviceid']),
				array('dependsOnServiceid', 'serviceid')
			);
		}

		$serviceIds = array_merge(
			zbx_objectValues($dependencies, 'serviceid'),
			zbx_objectValues($dependencies, 'dependsOnServiceid')
		);
		$serviceIds = array_unique($serviceIds);
		$this->checkServicePermissions($serviceIds);

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

	/**
	 * Deletes all service times for the given services.
	 *
	 * @param array $serviceIds
	 *
	 * @return boolean
	 */
	public function deleteTimes($serviceIds) {
		$serviceIds = zbx_toArray($serviceIds);
		$this->validatedeleteTimes($serviceIds);

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
		$algorithms = array(
			SERVICE_ALGORITHM_MAX,
			SERVICE_ALGORITHM_MIN,
			SERVICE_ALGORITHM_NONE
		);
		if (!isset($service['algorithm']) || !in_array($service['algorithm'], $algorithms, true)) {
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
		if (!isset($service['showsla']) || !in_array($service['showsla'], array(0, 1), true)) {
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
		if (empty($service['sortorder']) || !zbx_is_int($service['sortorder'])
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
		$affectedTriggerIds = zbx_objectValues($services, 'triggerid');
		if ($affectedTriggerIds) {
			if (!API::Trigger()->isReadable($affectedTriggerIds)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
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
		$child = $this->select('services_links', array(
			'output' => array('serviceupid'),
			'filter' => array(
				'serviceupid' => $serviceIds,
				'soft' => 0
			),
			'limit' => 1
		));
		$child = reset($child);
		if ($child) {
			$service = $this->select($this->tableName(), array(
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
		if (empty($dependency['serviceid']) || empty($dependency['dependsOnServiceid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
		}

		if (idcmp($dependency['serviceid'], $dependency['dependsOnServiceid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Service with ID "%1$s" cannot be dependent on itself.', $dependency['serviceid']));
		}

		// check 'soft' field value
		if (!isset($dependency['soft']) || !in_array($dependency['soft'], array(0, 1), true)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect "soft" field value for dependency for service with ID "%1$s".', $dependency['serviceid'])
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
				'filter' => array(
					'soft' => 0,
					'servicedownid' => $softDepServiceIds
				),
				'limit' => 1
			));

			if ($dep) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Service with ID "%1$s" is already hardlinked to a different service.', $dependency['dependsOnServiceid'])
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
		$sqlParts = parent::applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

		// parentids
		if ($options['parentids'] !== null) {
			$sqlParts['from'][] = 'services_links sl';
			$sqlParts['where'][] = 's.serviceid=sl.servicedownid';
			$sqlParts['where'][] = DBcondition('sl.serviceupid', (array) $options['parentids']);
			$sqlParts['where'][] = 'sl.soft=0';
		}

		return $sqlParts;
	}


}
