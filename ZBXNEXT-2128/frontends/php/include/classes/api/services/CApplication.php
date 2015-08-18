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


/**
 * Class containing methods for operations with applications.
 *
 * @package API
 */
class CApplication extends CApiService {

	protected $tableName = 'applications';
	protected $tableAlias = 'a';
	protected $sortColumns = ['applicationid', 'name'];

	/**
	 * Get applications data.
	 *
	 * @param array  $options
	 * @param array  $options['itemids']
	 * @param array  $options['hostids']
	 * @param array  $options['groupids']
	 * @param array  $options['triggerids']
	 * @param array  $options['applicationids']
	 * @param bool   $options['status']
	 * @param bool   $options['editable']
	 * @param bool   $options['count']
	 * @param string $options['pattern']
	 * @param int    $options['limit']
	 * @param string $options['order']
	 *
	 * @return array	item data as array or false if error
	 */
	public function get($options = []) {
		$result = [];
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sqlParts = [
			'select'	=> ['apps' => 'a.applicationid'],
			'from'		=> ['applications' => 'applications a'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'						=> null,
			'templateids'					=> null,
			'hostids'						=> null,
			'itemids'						=> null,
			'applicationids'				=> null,
			'templated'						=> null,
			'editable'						=> null,
			'inherited'						=> null,
			'nopermissions'					=> null,
			// filter
			'filter'						=> null,
			'search'						=> null,
			'searchByAny'					=> null,
			'startSearch'					=> null,
			'excludeSearch'					=> null,
			'searchWildcardsEnabled'		=> null,
			// output
			'output'						=> API_OUTPUT_EXTEND,
			'selectHost'					=> null,
			'selectItems'					=> null,
			'selectDiscoveryRule'			=> null,
			'selectApplicationDiscovery'	=> null,
			'countOutput'					=> null,
			'groupCount'					=> null,
			'preservekeys'					=> null,
			'sortfield'						=> '',
			'sortorder'						=> '',
			'limit'							=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$userGroups = getUserGroupsByUserId($userid);

			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM hosts_groups hgg'.
						' JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE a.hostid=hgg.hostid'.
					' GROUP BY hgg.hostid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
						' AND MAX(r.permission)>='.zbx_dbstr($permission).
					')';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['ahg'] = 'a.hostid=hg.hostid';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			if (!is_null($options['hostids'])) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else {
				$options['hostids'] = $options['templateids'];
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['where']['hostid'] = dbConditionInt('a.hostid', $options['hostids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hostid'] = 'a.hostid';
			}
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['from']['items_applications'] = 'items_applications ia';
			$sqlParts['where'][] = dbConditionInt('ia.itemid', $options['itemids']);
			$sqlParts['where']['aia'] = 'a.applicationid=ia.applicationid';
		}

		// applicationids
		if (!is_null($options['applicationids'])) {
			zbx_value2array($options['applicationids']);

			$sqlParts['where'][] = dbConditionInt('a.applicationid', $options['applicationids']);
		}

		// templated
		if (!is_null($options['templated'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['ah'] = 'a.hostid=h.hostid';

			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// inherited
		if (!is_null($options['inherited'])) {
			$sqlParts['where'][] = ($options['inherited'] ? '' : 'NOT').' EXISTS ('.
				'SELECT NULL'.
				' FROM application_template at'.
				' WHERE a.applicationid=at.applicationid'.
			')';
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('applications a', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('applications a', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		// output
		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($application = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $application;
				}
				else {
					$result = $application['rowscount'];
				}
			}
			else {
				$result[$application['applicationid']] = $application;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['hostid'], $options['output']);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	public function checkInput(&$applications, $method) {
		if (empty($applications)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$create = ($method == 'create');
		$update = ($method == 'update');
		$delete = ($method == 'delete');

		// permissions
		if ($update || $delete) {
			$itemDbFields = ['applicationid' => null];
			$dbApplications = $this->get([
				'output' => ['applicationid', 'hostid', 'name', 'flags', 'templateids'],
				'applicationids' => zbx_objectValues($applications, 'applicationid'),
				'editable' => true,
				'preservekeys' => true
			]);
		}
		else {
			$itemDbFields = ['name' => null, 'hostid' => null];
			$dbHosts = API::Host()->get([
				'output' => ['hostid', 'host', 'status'],
				'hostids' => zbx_objectValues($applications, 'hostid'),
				'templated_hosts' => true,
				'editable' => true,
				'preservekeys' => true
			]);
		}

		if ($update){
			$applications = $this->extendObjects($this->tableName(), $applications, ['name']);
		}

		foreach ($applications as &$application) {
			if (!check_db_fields($itemDbFields, $application)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			// check permissions by hostid
			if ($create) {
				if (!isset($dbHosts[$application['hostid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}
			}

			// check permissions by applicationid
			if ($delete || $update) {
				if (!isset($dbApplications[$application['applicationid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}
			}

			if ($update) {
				$this->checkNoParameters(
					$application, ['templateid', 'flags'], _('Cannot update "%1$s" for application "%2$s".'),
					$application['name']
				);
			}
			elseif ($create) {
				$this->checkNoParameters(
					$application, ['templateid', 'flags'], _('Cannot set "%1$s" for application "%2$s".'),
					$application['name']
				);
			}

			// Check on operating with templated and discovered applications.
			if ($delete || $update) {
				if ($dbApplications[$application['applicationid']]['templateids']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot update templated applications.'));
				}

				if ($dbApplications[$application['applicationid']]['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot update discovered application "%1$s".',
						$dbApplications[$application['applicationid']]['name']
					));
				}
			}

			if ($update) {
				if (!isset($application['hostid'])) {
					$application['hostid'] = $dbApplications[$application['applicationid']]['hostid'];
				}
			}

			// check existence
			if ($update || $create) {
				$applicationsExists = $this->get([
					'output' => API_OUTPUT_EXTEND,
					'filter' => [
						'hostid' => $application['hostid'],
						'name' => $application['name']
					],
					'nopermissions' => 1
				]);
				foreach ($applicationsExists as $applicationExists) {
					if (!$update || (bccomp($applicationExists['applicationid'], $application['applicationid']) != 0)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Application "%1$s" already exists.', $application['name']));
					}
				}
			}
		}
		unset($application);
	}

	/**
	 * Create new applications.
	 *
	 * @param array $applications

	 * @return array
	 */
	public function create(array $applications) {
		$applications = zbx_toArray($applications);
		$this->checkInput($applications, __FUNCTION__);

		foreach ($applications as &$application) {
			$application['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
		}
		unset($application);

		$appManager = new CApplicationManager();
		$applications = $appManager->create($applications);
		$appManager->inherit($applications);

		return ['applicationids' => zbx_objectValues($applications, 'applicationid')];
	}

	/**
	 * Update applications.
	 *
	 * @param array $applications
	 *
	 * @return array
	 */
	public function update(array $applications) {
		$applications = zbx_toArray($applications);
		$this->checkInput($applications, __FUNCTION__);

		$appManager = new CApplicationManager();
		$appManager->update($applications);
		$appManager->inherit($applications);

		return ['applicationids' => zbx_objectValues($applications, 'applicationid')];
	}

	/**
	 * Delete Applications.
	 *
	 * @param array $applicationids
	 * @param bool  $nopermissions
	 *
	 * @return array
	 */
	public function delete(array $applicationids, $nopermissions = false) {
		// TODO: remove $nopermissions hack
		$delApplications = $this->get([
			'output' => ['applicationid', 'hostid', 'name', 'flags', 'templateids'],
			'selectHost' => ['name', 'hostid'],
			'applicationids' => $applicationids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (!$nopermissions) {
			foreach ($applicationids as $applicationid) {
				if (!isset($delApplications[$applicationid])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
				}
				if ($delApplications[$applicationid]['templateids']) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('Cannot delete templated application.'));
				}

				if ($delApplications[$applicationid]['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Cannot delete discovered application "%1$s".',
						$delApplications[$applicationid]['name']
					));
				}
			}
		}

		$appManager = new CApplicationManager();

		// fetch application children
		$childApplicationIds = [];
		$parentApplicationIds = $applicationids;
		while ($parentApplicationIds) {
			$parentApplicationIds = $appManager->fetchExclusiveChildIds($parentApplicationIds);
			foreach ($parentApplicationIds as $appId) {
				$childApplicationIds[$appId] = $appId;
			}
		}

		// filter children that can be deleted
		if ($childApplicationIds) {
			$childApplicationIds = $appManager->fetchEmptyIds($childApplicationIds);
		}

		$childApplications = $this->get([
			'applicationids' => $childApplicationIds,
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => true,
			'preservekeys' => true,
			'selectHost' => ['name', 'hostid']
		]);

		$appManager->delete(array_merge($applicationids, $childApplicationIds));

		// TODO: remove info from API
		foreach (zbx_array_merge($delApplications, $childApplications) as $delApplication) {
			info(_s('Deleted: Application "%1$s" on "%2$s".', $delApplication['name'],
				$delApplication['host']['name']
			));
		}

		return ['applicationids' => $applicationids];
	}

	/**
	 * Add Items to applications.
	 *
	 * @param array $data
	 * @param array $data['applications']
	 * @param array $data['items']
	 *
	 * @return array
	 */
	public function massAdd($data) {
		if (empty($data['applications']) || empty($data['items'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameters.'));
		}

		$applications = zbx_toArray($data['applications']);
		$applicationIds = zbx_objectValues($applications, 'applicationid');
		$items = zbx_toArray($data['items']);
		$itemIds = zbx_objectValues($items, 'itemid');

		// validate permissions
		$allowedApplications = $this->get([
			'applicationids' => $applicationIds,
			'output' => ['applicationid', 'hostid', 'name'],
			'selectHost' => ['hostid', 'name'],
			'editable' => true,
			'preservekeys' => true
		]);
		foreach ($applications as $application) {
			if (!isset($allowedApplications[$application['applicationid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$allowedItems = API::Item()->get([
			'itemids' => $itemIds,
			'selectHosts' => ['name'],
			'output' => ['itemid', 'hostid', 'name'],
			'editable' => true,
			'preservekeys' => true
		]);
		foreach ($items as $item) {
			if (!isset($allowedItems[$item['itemid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		// validate hosts
		$dbApplication = reset($allowedApplications);
		$dbApplicationHost = $dbApplication['host'];
		foreach ($applications as $application) {
			if ($dbApplicationHost['hostid'] != $allowedApplications[$application['applicationid']]['hostid']) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('Cannot process applications from different hosts or templates.'));
			}
		}

		foreach ($items as $item) {
			$dbItem = $allowedItems[$item['itemid']];

			if ($dbItem['hostid'] != $dbApplicationHost['hostid']) {
				$dbItem['host'] = reset($dbItem['hosts']);

				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_s('Cannot add item "%1$s" from "%2$s" to application "%3$s" from "%4$s".',
						$dbItem['name'], $dbItem['host']['name'], $dbApplication['name'], $dbApplicationHost['name']));
			}
		}

		// link application with item
		$linkedDb = DBselect(
			'SELECT ia.itemid,ia.applicationid'.
			' FROM items_applications ia'.
			' WHERE '.dbConditionInt('ia.itemid', $itemIds).
				' AND '.dbConditionInt('ia.applicationid', $applicationIds)
		);
		while ($pair = DBfetch($linkedDb)) {
			$linked[$pair['applicationid']][$pair['itemid']] = true;
		}

		$createApplications = [];

		foreach ($applicationIds as $applicationId) {
			foreach ($itemIds as $itemId) {
				if (isset($linked[$applicationId]) && isset($linked[$applicationId][$itemId])) {
					continue;
				}

				$createApplications[] = [
					'itemid' => $itemId,
					'applicationid' => $applicationId
				];
			}
		}

		DB::insert('items_applications', $createApplications);

		// mass add applications for children
		foreach ($itemIds as $itemId) {
			$dbChilds = DBselect('SELECT i.itemid,i.hostid FROM items i WHERE i.templateid='.zbx_dbstr($itemId));

			while ($child = DBfetch($dbChilds)) {
				$dbApplications = DBselect(
					'SELECT a1.applicationid'.
					' FROM applications a1,applications a2'.
					' WHERE a1.name=a2.name'.
						' AND a1.hostid='.zbx_dbstr($child['hostid']).
						' AND '.dbConditionInt('a2.applicationid', $applicationIds)
				);

				$childApplications = [];

				while ($dbApplication = DBfetch($dbApplications)) {
					$childApplications[] = $dbApplication;
				}

				if (!$result = $this->massAdd(['items' => $child, 'applications' => $childApplications])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot add items.'));
				}
			}
		}

		return ['applicationids' => $applicationIds];
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// add application templates
		if ($this->outputIsRequested('templateids', $options['output'])) {
			$query = DBselect(
				'SELECT at.application_templateid,at.applicationid,at.templateid'.
					' FROM application_template at'.
					' WHERE '.dbConditionInt('at.applicationid', array_keys($result))
			);
			$relationMap = new CRelationMap();
			$templateApplications = [];
			while ($templateApplication = DBfetch($query)) {
				$relationMap->addRelation($templateApplication['applicationid'], $templateApplication['application_templateid']);
				$templateApplications[$templateApplication['application_templateid']] = $templateApplication['templateid'];
			}
			$result = $relationMap->mapMany($result, $templateApplications, 'templateids');
		}

		// adding one host
		if ($options['selectHost'] !== null) {
			$relationMap = $this->createRelationMap($result, 'applicationid', 'hostid');
			$hosts = API::Host()->get([
				'output' => $options['selectHost'],
				'hostids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'templated_hosts' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $hosts, 'host');
		}

		// adding items
		if ($options['selectItems'] !== null && $options['selectItems'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'applicationid', 'itemid', 'items_applications');
			$items = API::Item()->get([
				'output' => $options['selectItems'],
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $items, 'items');
		}

		// adding discovery rule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			$relationMap = new CRelationMap();

			$dbRules = DBselect(
				'SELECT ad.applicationid,ap.itemid'.
				' FROM application_discovery ad,application_prototype ap,applications a'.
				' WHERE '.dbConditionInt('ad.applicationid', array_keys($result)).
					' AND ad.application_prototypeid=ap.application_prototypeid'.
					' AND a.applicationid=ad.applicationid'.
					' AND a.flags='.ZBX_FLAG_DISCOVERY_CREATED
			);

			while ($rule = DBfetch($dbRules)) {
				$relationMap->addRelation($rule['applicationid'], $rule['itemid']);
			}

			$discoveryRules = API::DiscoveryRule()->get([
				'output' => $options['selectDiscoveryRule'],
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);

			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		// adding application discovery
		if ($options['selectApplicationDiscovery'] !== null) {
			$applicationDiscoveries = API::getApiService()->select('application_discovery', [
				'output' => $this->outputExtend($options['selectApplicationDiscovery'],
					['application_discoveryid', 'applicationid']
				),
				'filter' => ['applicationid' => array_keys($result)],
				'preservekeys' => true
			]);

			$relationMap = $this->createRelationMap($applicationDiscoveries, 'applicationid',
				'application_discoveryid'
			);

			$applicationDiscoveries = $this->unsetExtraFields($applicationDiscoveries,
				['applicationid', 'application_discoveryid'], $options['selectApplicationDiscovery']
			);

			$result = $relationMap->mapMany($result, $applicationDiscoveries, 'applicationDiscovery');
		}

		return $result;
	}
}
