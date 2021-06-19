<?php



/**
 * Validates the input parameters for the update() method.
 *
 * @throws APIException if the input is invalid
 *
 * @param array $services
 */
public function validateUpdate(array $services) {
	foreach ($services as $service) {
		if (empty($service['serviceid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
		}
	}

	$this->checkServicePermissions(zbx_objectValues($services, 'serviceid'));

	$services = $this->extendObjects($this->tableName(), $services, ['name']);
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
		$this->checkUnsupportedFields($this->tableName(), $service, $error, [
			'parentid', 'dependencies', 'times'
		]);
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
	$dependencies = [];
	$parentDependencies = [];
	$serviceTimes = [];
	$deleteParentsForServiceIds = [];
	$deleteDependenciesForServiceIds = [];
	$deleteTimesForServiceIds = [];
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
				$parentDependencies[] = [
					'serviceid' => $service['parentid'],
					'dependsOnServiceid' => $service['serviceid'],
//						'soft' => 0
				];
			}
		}

		// save service times
		if (isset($service['times'])) {
			$deleteTimesForServiceIds[] = $service['serviceid'];

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
	if ($deleteTimesForServiceIds) {
		$this->deleteTimes($deleteTimesForServiceIds);
	}
	if ($serviceTimes) {
		$this->addTimes($serviceTimes);
	}

	updateItServices();

	return ['serviceids' => zbx_objectValues($services, 'serviceid')];
}

/**
 * Validates the input parameters for the delete() method.
 *
 * @throws APIException if the input is invalid
 *
 * @param array $serviceIds
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
 * @param array $serviceIds
 *
 * @return array
 */
public function delete(array $serviceIds) {
	$this->validateDelete($serviceIds);

	DB::delete($this->tableName(), ['serviceid' => $serviceIds]);

	updateItServices();

	return ['serviceids' => $serviceIds];
}

/**
 * Validates the input parameters for the addDependencies() method.
 *
 * @throws APIException if the input is invalid
 *
 * @param array $dependencies
 */
protected function XXvalidateAddDependencies(array $dependencies) {
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
			['dependsOnServiceid', 'serviceid']
		);
	}

	$this->checkForHardlinkedDependencies($dependencies);
	$this->checkThatParentsDontHaveTriggers($dependencies);
	$this->checkForCircularityInDependencies($dependencies);
}

/**
 * Add the given service dependencies.
 *
 * @param array $dependencies   an array of service dependencies, each pair in the form of
 *                              array('serviceid' => 1, 'dependsOnServiceid' => 2, 'soft' => 0)
 *
 * @return array
 */
public function XXaddDependencies(array $dependencies) {
	$dependencies = zbx_toArray($dependencies);
	$this->validateAddDependencies($dependencies);

	$data = [];
	foreach ($dependencies as $dependency) {
		$data[] = [
			'serviceupid' => $dependency['serviceid'],
			'servicedownid' => $dependency['dependsOnServiceid'],
//				'soft' => $dependency['soft']
		];
	}
	DB::insert('services_links', $data);

	return ['serviceids' => zbx_objectValues($dependencies, 'serviceid')];
}

/**
 * Validates the input for the deleteDependencies() method.
 *
 * @throws APIException if the given input is invalid
 *
 * @param array $serviceIds
 */
protected function XXvalidateDeleteDependencies(array $serviceIds) {
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
public function XXdeleteDependencies($serviceIds) {
	$serviceIds = zbx_toArray($serviceIds);
	$this->validateDeleteDependencies($serviceIds);

	DB::delete('services_links', [
		'serviceupid' =>  $serviceIds
	]);

	return ['serviceids' => $serviceIds];
}

/**
 * Validates the input for the addTimes() method.
 *
 * @throws APIException if the given input is invalid
 *
 * @param array $serviceTimes
 */
public function XXvalidateAddTimes(array $serviceTimes) {
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
public function XXaddTimes(array $serviceTimes) {
	$serviceTimes = zbx_toArray($serviceTimes);
	$this->validateAddTimes($serviceTimes);

	DB::insert('services_times', $serviceTimes);

	return ['serviceids' => zbx_objectValues($serviceTimes, 'serviceid')];
}

/**
 * Validates the input for the deleteTimes() method.
 *
 * @throws APIException if the given input is invalid
 *
 * @param array $serviceIds
 */
protected function XXvalidateDeleteTimes(array $serviceIds) {
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
public function XXdeleteTimes($serviceIds) {
	$serviceIds = zbx_toArray($serviceIds);
	$this->validateDeleteTimes($serviceIds);

	DB::delete('services_times', [
		'serviceid' =>  $serviceIds
	]);

	return ['serviceids' => $serviceIds];
}

/**
 * Deletes the dependencies of the parent services on the given services.
 *
 * @param $serviceIds
 */
protected function XXdeleteParentDependencies($serviceIds) {
	DB::delete('services_links', [
		'servicedownid' => $serviceIds,
//			'soft' => 0
	]);
}




/**
 * Returns an array of dependencies that are children of the given services. Performs permission checks.
 *
 * @param array $parentServiceIds
 * @param $output
 *
 * @return array    an array of service links sorted by "sortorder" in ascending order
 */
protected function XXfetchChildDependencies(array $parentServiceIds, $output) {
	$sqlParts = API::getApiService()->createSelectQueryParts('services_links', 'sl', [
		'output' => $output,
		'filter' => ['serviceupid' => $parentServiceIds]
	]);

	// sort by sortorder
	$sqlParts['from'][] = $this->tableName().' '.$this->tableAlias();
	$sqlParts['where'][] = 'sl.servicedownid='.$this->fieldId('serviceid');
	$sqlParts = $this->addQueryOrder($this->fieldId('sortorder'), $sqlParts);
	$sqlParts = $this->addQueryOrder($this->fieldId('serviceid'), $sqlParts);

	// add permission filter
	if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
		$sqlParts = $this->addPermissionFilter($sqlParts);
	}

	$sql = self::createSelectQueryFromParts($sqlParts);

	return DBfetchArray(DBselect($sql));
}

/**
 * Returns an array of dependencies from the parent services to the given services.
 * Performs permission checks.
 *
 * @param array $childServiceIds
 * @param $output
 * @param boolean $soft             if set to true, will return only soft-linked dependencies
 *
 * @return array    an array of service links sorted by "sortorder" in ascending order
 */
protected function XXfetchParentDependencies(array $childServiceIds, $output, $soft = null) {
	$sqlParts = API::getApiService()->createSelectQueryParts('services_links', 'sl', [
		'output' => $output,
		'filter' => ['servicedownid' => $childServiceIds]
	]);

	$sqlParts['from'][] = $this->tableName().' '.$this->tableAlias();
	$sqlParts['where'][] = 'sl.serviceupid='.$this->fieldId('serviceid');
	if ($soft !== null) {
//			$sqlParts['where'][] = 'sl.soft='.($soft ? 1 : 0);
	}
	$sqlParts = $this->addQueryOrder($this->fieldId('sortorder'), $sqlParts);
	$sqlParts = $this->addQueryOrder($this->fieldId('serviceid'), $sqlParts);

	// add permission filter
	if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
		$sqlParts = $this->addPermissionFilter($sqlParts);
	}

	$sql = self::createSelectQueryFromParts($sqlParts);

	return DBfetchArray(DBselect($sql));
}


/**
 * Validates the "name" field.
 *
 * @throws APIException if the name is missing
 *
 * @param array $service
 */
protected function XXcheckName(array $service) {
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
 */
protected function XXcheckAlgorithm(array $service) {
	if (!isset($service['algorithm']) || !serviceAlgorithm($service['algorithm'])) {
		self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect algorithm for service "%1$s".', $service['name']));
	}
}


/**
 * Validates the "showsla" field. Assumes the "name" field is valid.
 *
 * @throws APIException if the value is missing, or is out of bounds
 *
 * @param array $service
 */
protected function XXcheckGoodSla(array $service) {
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
 */
protected function XXcheckSortOrder(array $service) {
	if (!isset($service['sortorder']) || !zbx_is_int($service['sortorder'])
		|| $service['sortorder'] < 0 || $service['sortorder'] > 999) {

		self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect sort order for service "%1$s".', $service['name']));
	}
}


/**
 * Validates the "parentid" field. Assumes the "name" field is valid.
 *
 * @throws APIException if the value is incorrect
 *
 * @param array $service
 */
protected function XXcheckParentId(array $service) {
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
 * Checks that all of the given services are readable.
 *
 * @throws APIException if at least one of the services doesn't exist
 *
 * @param array $serviceids
 */
protected function XXcheckServicePermissions(array $serviceids) {
	if ($serviceids) {
		$serviceids = array_unique($serviceids);

		$count = $this->get([
			'countOutput' => true,
			'serviceids' => $serviceids
		]);

		if ($count != count($serviceids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('No permissions to referred object or it does not exist!')
			);
		}
	}
}

/**
 * Checks that none of the given services have any children.
 *
 * @throws APIException if at least one of the services has a child service
 *
 * @param array $serviceIds
 */
protected function XXcheckThatServicesDontHaveChildren(array $serviceIds) {
	$child = API::getApiService()->select('services_links', [
		'output' => ['serviceupid'],
		'filter' => [
			'serviceupid' => $serviceIds,
//				'soft' => 0
		],
		'limit' => 1
	]);
	$child = reset($child);
	if ($child) {
		$service = API::getApiService()->select($this->tableName(), [
			'output' => ['name'],
			'serviceids' => $child['serviceupid'],
			'limit' => 1
		]);
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
 */
protected function XXcheckDependency(array $dependency) {
	if (idcmp($dependency['serviceid'], $dependency['dependsOnServiceid'])) {
		$service = API::getApiService()->select($this->tableName(), [
			'output' => ['name'],
			'serviceids' => $dependency['serviceid']
		]);
		$service = reset($service);
		self::exception(ZBX_API_ERROR_PARAMETERS, _s('Service "%1$s" cannot be dependent on itself.', $service['name']));
	}

	// check 'soft' field value
//		if (!isset($dependency['soft']) || !in_array((int) $dependency['soft'], [0, 1], true)) {
//			$service = API::getApiService()->select($this->tableName(), [
//				'output' => ['name'],
//				'serviceids' => $dependency['serviceid']
//			]);
//			$service = reset($service);
//			self::exception(ZBX_API_ERROR_PARAMETERS,
//				_s('Incorrect "soft" field value for dependency for service "%1$s".', $service['name'])
//			);
//		}
}

/**
 * Checks that that none of the given services are hard linked to a different service.
 * Assumes the dependencies are valid.
 *
 * @throws APIException if at a least one service is hard linked to another service
 *
 * @param array $dependencies
 */
protected function XXcheckForHardlinkedDependencies(array $dependencies) {
	// only check hard dependencies
	$hardDepServiceIds = [];
//		foreach ($dependencies as $dependency) {
//			if (!$dependency['soft']) {
//				$hardDepServiceIds[] = $dependency['dependsOnServiceid'];
//			}
//		}

	if ($hardDepServiceIds) {
		// look for at least one hardlinked service among the given
		$hardDepServiceIds = array_unique($hardDepServiceIds);
		$dep = API::getApiService()->select('services_links', [
			'output' => ['servicedownid'],
			'filter' => [
//					'soft' => 0,
				'servicedownid' => $hardDepServiceIds
			],
			'limit' => 1
		]);
		if ($dep) {
			$dep = reset($dep);
			$service = API::getApiService()->select($this->tableName(), [
				'output' => ['name'],
				'serviceids' => $dep['servicedownid']
			]);
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
 */
protected function XXcheckThatParentsDontHaveTriggers(array $dependencies) {
	$parentServiceIds = array_unique(zbx_objectValues($dependencies, 'serviceid'));
	if ($parentServiceIds) {
		$query = DBselect(
			'SELECT s.triggerid,s.name'.
			' FROM services s '.
			' WHERE '.dbConditionInt('s.serviceid', $parentServiceIds).
			' AND s.triggerid IS NOT NULL', 1);
		if ($parentService = DBfetch($query)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Service "%1$s" cannot be linked to a trigger and have children at the same time.', $parentService['name']));
		}
	}
}
