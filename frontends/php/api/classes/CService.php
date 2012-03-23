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


	protected function validateCreate(array $services) {
		$this->checkServicePermissions($services);

		foreach ($services as $service) {
			$this->checkName($service);
			$this->checkAlgorithm($service);
			$this->checkShowSla($service);
			$this->checkGoodSla($service);
			$this->checkSortOrder($service);
			// TODO: check triggerid

			$error = _s('Wrong fields for service "%1$s".', $service['name']);
			$this->checkUnsupportedFields($this->tableName(), $service, $error, array(
				'parentid'
			));
		}

		$this->checkTriggerPermissions($services);

		// TODO: check that parent services and linked services exist
		$this->checkIfParentsHaveTriggers($services);
	}

	public function create(array $services) {
		$services = zbx_toArray($services);
		$this->validateCreate($services);
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
		if (empty($service['sortorder']) || !is_numeric($service['sortorder'])
				|| $service['sortorder'] < 0 || $service['sortorder'] > 999) {

			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect sorder order for service "%1$s".', $service['name']));
		}
	}

	/**
	 * Checks if the user has read access to the given triggers.
	 *
	 * @throws APIException if the user doesn't have permission to access any of the triggers
	 *
	 * @param array $services
	 *
	 * @return void
	 */
	protected function checkTriggerPermissions(array $services) {
		$affectedTriggerIds = array_merge(zbx_objectValues($services, 'triggerid'), array());
		if ($affectedTriggerIds) {
			if (!API::Trigger()->isReadable($affectedTriggerIds)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}
	}

	/**
	 * Checks that none of the parent services are linked to a trigger.
	 *
	 * @throws APIException if at least one of the parent services is linked to a trigger
	 *
	 * @param array $services
	 *
	 * @return void
	 */
	protected function checkIfParentsHaveTriggers(array $services) {
		// TODO: check linked parent services

		$parentServiceIds = zbx_objectValues($services, 'parentid');
		$parentServiceIds = array_unique($parentServiceIds);
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
	 * Checks if all affected services (updated, used as parents or linked) actually exist.
	 *
	 * @throws APIException if at least one of the services doesn't exist
	 *
	 * @param array $services
	 *
	 * @return void
	 */
	protected function checkServicePermissions(array $services) {
		// check user permissions
		if (CWebUser::$data['type'] == USER_TYPE_ZABBIX_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// check if the affected services exist
		$serviceIds = zbx_objectValues($services, 'parentid');
		if ($serviceIds) {
			if (!$this->isReadable($serviceIds)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		// TODO: check serviceid
		// TODO: check parentid
		// TODO: check links
	}

}
