<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * Class containing methods for operations with host prototypes.
 */
class CHostPrototype extends CHostBase {

	protected $sortColumns = ['hostid', 'host', 'name', 'status'];

	public function __construct() {
		parent::__construct();

		$this->getOptions = array_merge($this->getOptions, [
			'discoveryids'  		=> null,
			'inherited'				=> null,
			'selectDiscoveryRule' 	=> null,
			'selectGroupLinks'		=> null,
			'selectGroupPrototypes' => null,
			'selectParentHost'		=> null,
			'selectTemplates' 		=> null,
			'selectInventory' 		=> null,
			'editable'				=> false,
			'nopermissions'			=> null,
			'sortfield'    			=> '',
			'sortorder'     		=> ''
		]);
	}

	/**
	 * Get host prototypes.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get(array $options) {
		$options = zbx_array_merge($this->getOptions, $options);
		$options['filter']['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;

		// build and execute query
		$sql = $this->createSelectQuery($this->tableName(), $options);
		$res = DBselect($sql, $options['limit']);

		// fetch results
		$result = [];
		while ($row = DBfetch($res)) {
			// a count query, return a single result
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $row;
				}
				else {
					$result = $row['rowscount'];
				}
			}
			// a normal select query
			else {
				$result[$row[$this->pk()]] = $row;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['triggerid'], $options['output']);
		}

		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Check for duplicated names.
	 *
	 * @param string $field_name
	 * @param array  $names_by_ruleid
	 *
	 * @throws APIException  if host prototype with same name already exists.
	 */
	private function checkDuplicates($field_name, array $names_by_ruleid) {
		$sql_where = [];
		foreach ($names_by_ruleid as $ruleid => $names) {
			$sql_where[] = '(i.itemid='.$ruleid.' AND '.dbConditionString('h.'.$field_name, $names).')';
		}

		$db_host_prototypes = DBfetchArray(DBselect(
				'SELECT i.name AS rule,h.'.$field_name.
				' FROM items i,host_discovery hd,hosts h'.
				' WHERE i.itemid=hd.parent_itemid'.
					' AND hd.hostid=h.hostid'.
					' AND '.implode(' OR ', $sql_where),
				1
		));

		if ($db_host_prototypes) {
			$error = ($field_name === 'host')
				? _('Host prototype with host name "%1$s" already exists in discovery rule "%2$s".')
				: _('Host prototype with visible name "%1$s" already exists in discovery rule "%2$s".');

			self::exception(ZBX_API_ERROR_PARAMETERS,
				sprintf($error, $db_host_prototypes[0][$field_name], $db_host_prototypes[0]['rule'])
			);
		}
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @param array $host_prototypes
	 */
	protected function validateCreate(array &$host_prototypes) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['ruleid', 'host'], ['ruleid', 'name']], 'fields' => [
			'ruleid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'host' =>				['type' => API_H_NAME, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name'), 'default_source' => 'host'],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])],
			'groupLinks' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'groupPrototypes' =>	['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
				'name' =>				['type' => API_HG_NAME, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hstgrp', 'name')]
			]],
			'inventory' =>			['type' => API_OBJECT, 'fields' => [
				'inventory_mode' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
			]],
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $host_prototypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$hosts_by_ruleid = [];
		$names_by_ruleid = [];
		$groupids = [];

		foreach ($host_prototypes as $host_prototype) {
			// Collect host group ID links for latter validation.
			foreach ($host_prototype['groupLinks'] as $group_prototype) {
				$groupids[$group_prototype['groupid']] = true;
			}

			$hosts_by_ruleid[$host_prototype['ruleid']][] = $host_prototype['host'];
			$names_by_ruleid[$host_prototype['ruleid']][] = $host_prototype['name'];
		}

		$ruleids = array_unique(zbx_objectValues($host_prototypes, 'ruleid'));
		$groupids = array_keys($groupids);

		$this->checkDiscoveryRulePermissions($ruleids);
		$this->checkHostGroupsPermissions($groupids);

		// Check if the host is discovered.
		$db_discovered_hosts = DBfetchArray(DBselect(
			'SELECT h.host'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionInt('i.itemid', $ruleids).
				' AND h.flags='.ZBX_FLAG_DISCOVERY_CREATED,
			1
		));

		if ($db_discovered_hosts) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Cannot create a host prototype on a discovered host "%1$s".', $db_discovered_hosts[0]['host'])
			);
		}

		$this->checkDuplicates('host', $hosts_by_ruleid);
		$this->checkDuplicates('name', $names_by_ruleid);
	}

	/**
	 * Creates the given host prototypes.
	 *
	 * @param array $host_prototypes
	 *
	 * @return array
	 */
	public function create(array $host_prototypes) {
		// 'templateid' validation happens during linkage.
		$this->validateCreate($host_prototypes);

		// Merge groups into group prototypes.
		foreach ($host_prototypes as &$host_prototype) {
			$host_prototype['groupPrototypes'] = array_merge(
				array_key_exists('groupPrototypes', $host_prototype) ? $host_prototype['groupPrototypes'] : [],
				$host_prototype['groupLinks']
			);
			unset($host_prototype['groupLinks']);
		}
		unset($host_prototype);

		$host_prototypes = $this->createReal($host_prototypes);
		$this->inherit($host_prototypes);

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_HOST_PROTOTYPE, $host_prototypes);

		return ['hostids' => zbx_objectValues($host_prototypes, 'hostid')];
	}

	/**
	 * Creates the host prototypes and inherits them to linked hosts and templates.
	 *
	 * @param array $hostPrototypes
	 *
	 * @return array	an array of host prototypes with host IDs
	 */
	protected function createReal(array $hostPrototypes) {
		foreach ($hostPrototypes as &$hostPrototype) {
			$hostPrototype['flags'] = ZBX_FLAG_DISCOVERY_PROTOTYPE;
		}
		unset($hostPrototype);

		// save the host prototypes
		$hostPrototypeIds = DB::insert($this->tableName(), $hostPrototypes);

		$groupPrototypes = [];
		$hostPrototypeDiscoveryRules = [];
		$hostPrototypeInventory = [];
		foreach ($hostPrototypes as $key => $hostPrototype) {
			$hostPrototypes[$key]['hostid'] = $hostPrototype['hostid'] = $hostPrototypeIds[$key];

			// save group prototypes
			foreach ($hostPrototype['groupPrototypes'] as $groupPrototype) {
				$groupPrototype['hostid'] = $hostPrototype['hostid'];
				$groupPrototypes[] = $groupPrototype;
			}

			// discovery rules
			$hostPrototypeDiscoveryRules[] = [
				'hostid' => $hostPrototype['hostid'],
				'parent_itemid' => $hostPrototype['ruleid']
			];

			// inventory
			if (isset($hostPrototype['inventory']['inventory_mode'])
					&& ($hostPrototype['inventory']['inventory_mode'] == HOST_INVENTORY_MANUAL
						|| $hostPrototype['inventory']['inventory_mode'] == HOST_INVENTORY_AUTOMATIC)) {
				$hostPrototypeInventory[] = [
					'hostid' => $hostPrototype['hostid'],
					'inventory_mode' => $hostPrototype['inventory']['inventory_mode']
				];
			}
		}

		// save group prototypes
		$groupPrototypes = DB::save('group_prototype', $groupPrototypes);
		$i = 0;
		foreach ($hostPrototypes as &$hostPrototype) {
			foreach ($hostPrototype['groupPrototypes'] as &$groupPrototype) {
				$groupPrototype['group_prototypeid'] = $groupPrototypes[$i]['group_prototypeid'];
				$i++;
			}
			unset($groupPrototype);
		}
		unset($hostPrototype);

		// link host prototypes to discovery rules
		DB::insert('host_discovery', $hostPrototypeDiscoveryRules, false);

		// save inventory
		DB::insert('host_inventory', $hostPrototypeInventory, false);

		// link templates
		foreach ($hostPrototypes as $hostPrototype) {
			if (isset($hostPrototype['templates']) && $hostPrototype['templates']) {
				$this->link(zbx_objectValues($hostPrototype['templates'], 'templateid'), [$hostPrototype['hostid']]);
			}
		}

		return $hostPrototypes;
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @param array $host_prototypes
	 * @param array $db_host_prototypes
	 */
	protected function validateUpdate(array &$host_prototypes, array &$db_host_prototypes = null) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostid']], 'fields' => [
			'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
			'host' =>				['type' => API_H_NAME, 'flags' => API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hosts', 'host')],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'name')],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])],
			'groupLinks' =>			['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['group_prototypeid'], ['groupid']], 'fields' => [
				'group_prototypeid' =>	['type' => API_ID],
				'groupid' =>			['type' => API_ID]
			]],
			'groupPrototypes' =>	['type' => API_OBJECTS, 'uniq' => [['group_prototypeid'], ['name']], 'fields' => [
				'group_prototypeid' =>	['type' => API_ID],
				'name' =>				['type' => API_HG_NAME, 'flags' => API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('hstgrp', 'name')]
			]],
			'inventory' =>			['type' => API_OBJECT, 'fields' => [
				'inventory_mode' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
			]],
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $host_prototypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_host_prototypes = $this->get([
			'output' => ['hostid', 'host', 'name', 'status'],
			'selectDiscoveryRule' => ['itemid'],
			'selectGroupLinks' => ['group_prototypeid', 'groupid'],
			'selectGroupPrototypes' => ['group_prototypeid', 'name'],
			'hostids' => zbx_objectValues($host_prototypes, 'hostid'),
			'editable' => true,
			'preservekeys' => true
		]);

		$hosts_by_ruleid = [];
		$names_by_ruleid = [];

		foreach ($host_prototypes as &$host_prototype) {
			// Check if this host prototype exists.
			if (!array_key_exists($host_prototype['hostid'], $db_host_prototypes)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_host_prototype = $db_host_prototypes[$host_prototype['hostid']];
			$host_prototype['ruleid'] = $db_host_prototype['discoveryRule']['itemid'];

			if (array_key_exists('host', $host_prototype) && $host_prototype['host'] !== $db_host_prototype['host']) {
				$hosts_by_ruleid[$host_prototype['ruleid']][] = $host_prototype['host'];
			}

			if (array_key_exists('name', $host_prototype) && $host_prototype['name'] !== $db_host_prototype['name']) {
				$names_by_ruleid[$host_prototype['ruleid']][] = $host_prototype['name'];
			}
		}
		unset($host_prototype);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['ruleid', 'host'], ['ruleid', 'name']]];
		if (!CApiInputValidator::validateUniqueness($api_input_rules, $host_prototypes, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$groupids = [];
		$db_groupids = [];

		foreach ($host_prototypes as $host_prototype) {
			$db_host_prototype = $db_host_prototypes[$host_prototype['hostid']];

			foreach ($db_host_prototype['groupLinks'] as $db_group_link) {
				$db_groupids[$db_group_link['groupid']] = true;
			}

			$db_group_links = zbx_toHash($db_host_prototype['groupLinks'], 'group_prototypeid');
			$db_group_prototypes = zbx_toHash($db_host_prototype['groupPrototypes'], 'group_prototypeid');

			// Validate 'group_prototypeid' in 'groupLinks' property.
			if (array_key_exists('groupLinks', $host_prototype)) {
				foreach ($host_prototype['groupLinks'] as $group_link) {
					if (!$group_link) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
					}

					// Don't allow invalid 'group_prototypeid' parameters which do not belong to this 'hostid'.
					if (array_key_exists('group_prototypeid', $group_link)
							&& !array_key_exists($group_link['group_prototypeid'], $db_group_links)) {
						self::exception(ZBX_API_ERROR_PERMISSIONS,
							_('No permissions to referred object or it does not exist!')
						);
					}

					if (array_key_exists('groupid', $group_link)) {
						$groupids[$group_link['groupid']] = true;
					}
				}
			}

			// Validate 'group_prototypeid' in 'groupPrototypes' property.
			if (array_key_exists('groupPrototypes', $host_prototype)) {
				foreach ($host_prototype['groupPrototypes'] as $group_prototype) {
					if (!$group_prototype) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
					}

					// Don't allow invalid 'group_prototypeid' parameters which do not belong to this 'hostid'.
					if (array_key_exists('group_prototypeid', $group_prototype)
							&& !array_key_exists($group_prototype['group_prototypeid'], $db_group_prototypes)) {
						self::exception(ZBX_API_ERROR_PERMISSIONS,
							_('No permissions to referred object or it does not exist!')
						);
					}
				}
			}
		}

		// Collect only new given groupids for validation.
		$groupids = array_diff_key($groupids, $db_groupids);

		if ($groupids) {
			$this->checkHostGroupsPermissions(array_keys($groupids));
		}

		$host_prototypes = $this->extendObjectsByKey($host_prototypes, $db_host_prototypes, 'hostid',
			['host', 'name', 'groupLinks', 'groupPrototypes']
		);

		if ($hosts_by_ruleid) {
			$this->checkDuplicates('host', $hosts_by_ruleid);
		}
		if ($names_by_ruleid) {
			$this->checkDuplicates('name', $names_by_ruleid);
		}
	}

	/**
	 * Updates the given host prototypes.
	 *
	 * @param array $host_prototypes
	 *
	 * @return array
	 */
	public function update(array $host_prototypes) {
		$this->validateUpdate($host_prototypes, $db_host_prototypes);

		// merge group links into group prototypes
		foreach ($host_prototypes as &$host_prototype) {
			$host_prototype['groupPrototypes'] =
				array_merge($host_prototype['groupPrototypes'], $host_prototype['groupLinks']);
			unset($host_prototype['groupLinks']);
		}
		unset($host_prototype);

		$host_prototypes = $this->updateReal($host_prototypes);
		$this->inherit($host_prototypes);

		foreach ($db_host_prototypes as &$db_host_prototype) {
			unset($db_host_prototype['discoveryRule'], $db_host_prototype['groupLinks'],
				$db_host_prototype['groupPrototypes']
			);
		}
		unset($db_host_prototype);

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST_PROTOTYPE, $host_prototypes, $db_host_prototypes);

		return ['hostids' => zbx_objectValues($host_prototypes, 'hostid')];
	}

	/**
	 * Updates the host prototypes and propagates the changes to linked hosts and templates.
	 *
	 * @param array $hostPrototypes
	 *
	 * @return array
	 */
	protected function updateReal(array $hostPrototypes) {
		// save the host prototypes
		foreach ($hostPrototypes as $hostPrototype) {
			DB::updateByPk($this->tableName(), $hostPrototype['hostid'], $hostPrototype);
		}

		$exHostPrototypes = $this->get([
			'output' => ['hostid'],
			'selectGroupLinks' => API_OUTPUT_EXTEND,
			'selectGroupPrototypes' => API_OUTPUT_EXTEND,
			'selectTemplates' => ['templateid'],
			'selectInventory' => API_OUTPUT_EXTEND,
			'hostids' => zbx_objectValues($hostPrototypes, 'hostid'),
			'preservekeys' => true
		]);

		// update related objects
		$inventoryCreate = [];
		$inventoryDeleteIds = [];
		foreach ($hostPrototypes as $key => $hostPrototype) {
			$exHostPrototype = $exHostPrototypes[$hostPrototype['hostid']];

			// group prototypes
			if (isset($hostPrototype['groupPrototypes'])) {
				foreach ($hostPrototype['groupPrototypes'] as &$groupPrototype) {
					$groupPrototype['hostid'] = $hostPrototype['hostid'];
				}
				unset($groupPrototype);

				// save group prototypes
				$exGroupPrototypes = zbx_toHash(
					array_merge($exHostPrototype['groupLinks'], $exHostPrototype['groupPrototypes']),
					'group_prototypeid'
				);
				$modifiedGroupPrototypes = [];
				foreach ($hostPrototype['groupPrototypes'] as $groupPrototype) {
					if (isset($groupPrototype['group_prototypeid'])) {
						unset($exGroupPrototypes[$groupPrototype['group_prototypeid']]);
					}

					$modifiedGroupPrototypes[] = $groupPrototype;
				}
				if ($exGroupPrototypes) {
					$this->deleteGroupPrototypes(array_keys($exGroupPrototypes));
				}
				$hostPrototypes[$key]['groupPrototypes'] = DB::save('group_prototype', $modifiedGroupPrototypes);
			}

			// templates
			if (isset($hostPrototype['templates'])) {
				$existingTemplateIds = zbx_objectValues($exHostPrototype['templates'], 'templateid');
				$newTemplateIds = zbx_objectValues($hostPrototype['templates'], 'templateid');
				$this->unlink(array_diff($existingTemplateIds, $newTemplateIds), [$hostPrototype['hostid']]);
				$this->link(array_diff($newTemplateIds, $existingTemplateIds), [$hostPrototype['hostid']]);
			}

			// inventory
			if (isset($hostPrototype['inventory']) ) {
				$inventory = zbx_array_mintersect(['inventory_mode'], $hostPrototype['inventory']);

				if (array_key_exists('inventory_mode', $inventory)
					&& ($inventory['inventory_mode'] == HOST_INVENTORY_MANUAL
						|| $inventory['inventory_mode'] == HOST_INVENTORY_AUTOMATIC)) {

					if ($exHostPrototype['inventory']['inventory_mode'] != HOST_INVENTORY_DISABLED) {
						DB::update('host_inventory', [
							'values' => $inventory,
							'where' => ['hostid' => $hostPrototype['hostid']]
						]);
					}
					else {
						$inventoryCreate[] = $inventory + ['hostid' => $hostPrototype['hostid']];
					}
				}
				else {
					$inventoryDeleteIds[] = $hostPrototype['hostid'];
				}
			}
		}

		// save inventory
		DB::insert('host_inventory', $inventoryCreate, false);
		DB::delete('host_inventory', ['hostid' => $inventoryDeleteIds]);

		return $hostPrototypes;
	}

	/**
	 * Updates the children of the host prototypes on the given hosts and propagates the inheritance to the child hosts.
	 *
	 * @param array $hostPrototypes		array of host prototypes to inherit
	 * @param array $hostids   			array of hosts to inherit to; if set to null, the children will be updated on all
	 *                              	child hosts
	 *
	 * @return bool
	 */
	protected function inherit(array $hostPrototypes, array $hostids = null) {
		if (empty($hostPrototypes)) {
			return true;
		}

		// prepare the child host prototypes
		$newHostPrototypes = $this->prepareInheritedObjects($hostPrototypes, $hostids);
		if (!$newHostPrototypes) {
			return true;
		}

		$insertHostPrototypes = [];
		$updateHostPrototypes = [];
		foreach ($newHostPrototypes as $newHostPrototype) {
			if (isset($newHostPrototype['hostid'])) {
				$updateHostPrototypes[] = $newHostPrototype;
			}
			else {
				$insertHostPrototypes[] = $newHostPrototype;
			}
		}

		// save the new host prototypes
		if (!zbx_empty($insertHostPrototypes)) {
			$insertHostPrototypes = $this->createReal($insertHostPrototypes);
		}

		if (!zbx_empty($updateHostPrototypes)) {
			$updateHostPrototypes = $this->updateReal($updateHostPrototypes);
		}

		$host_prototypes = array_merge($updateHostPrototypes, $insertHostPrototypes);

		if ($host_prototypes) {
			$sql = 'SELECT hd.hostid'.
					' FROM host_discovery hd,items i,hosts h'.
					' WHERE hd.parent_itemid=i.itemid'.
						' AND i.hostid=h.hostid'.
						' AND h.status='.HOST_STATUS_TEMPLATE.
						' AND '.dbConditionInt('hd.hostid', zbx_objectValues($host_prototypes, 'hostid'));
			$valid_prototypes = DBfetchArrayAssoc(DBselect($sql), 'hostid');

			foreach ($host_prototypes as $key => $host_prototype) {
				if (!array_key_exists($host_prototype['hostid'], $valid_prototypes)) {
					unset($host_prototypes[$key]);
				}
			}
		}

		// propagate the inheritance to the children
		return $this->inherit($host_prototypes);
	}


	/**
	 * Prepares and returns an array of child host prototypes, inherited from host prototypes $hostPrototypes
	 * on the given hosts.
	 *
	 * Each host prototype must have the "ruleid" parameter set.
	 *
	 * @param array     $hostPrototypes
	 * @param array		$hostIds
	 *
	 * @return array 	an array of unsaved child host prototypes
	 */
	protected function prepareInheritedObjects(array $hostPrototypes, array $hostIds = null) {
		// fetch the related discovery rules with their hosts
		$discoveryRules = API::DiscoveryRule()->get([
			'output' => ['itemid', 'hostid'],
			'selectHosts' => ['hostid'],
			'itemids' => zbx_objectValues($hostPrototypes, 'ruleid'),
			'templated' => true,
			'nopermissions' => true,
			'preservekeys' => true
		]);

		// fetch all child hosts to inherit to
		// do not inherit host prototypes on discovered hosts
		$chdHosts = API::Host()->get([
			'output' => ['hostid', 'host', 'status'],
			'selectParentTemplates' => ['templateid'],
			'templateids' => zbx_objectValues($discoveryRules, 'hostid'),
			'hostids' => $hostIds,
			'nopermissions' => true,
			'templated_hosts' => true,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
		]);
		if (empty($chdHosts)) {
			return [];
		}

		// fetch the child discovery rules
		$childDiscoveryRules = API::DiscoveryRule()->get([
			'output' => ['itemid', 'templateid', 'hostid'],
			'preservekeys' => true,
			'filter' => [
				'templateid' => array_keys($discoveryRules)
			]
		]);

		// fetch child host prototypes and group them by discovery rule
		$childHostPrototypes = API::HostPrototype()->get([
			'output' => ['hostid', 'host', 'templateid'],
			'selectGroupLinks' => API_OUTPUT_EXTEND,
			'selectGroupPrototypes' => API_OUTPUT_EXTEND,
			'selectDiscoveryRule' => ['itemid'],
			'discoveryids' => zbx_objectValues($childDiscoveryRules, 'itemid'),
		]);
		foreach ($childDiscoveryRules as &$childDiscoveryRule) {
			$childDiscoveryRule['hostPrototypes'] = [];
		}
		unset($childDiscoveryRule);
		foreach ($childHostPrototypes as $childHostPrototype) {
			$discoveryRuleId = $childHostPrototype['discoveryRule']['itemid'];
			unset($childHostPrototype['discoveryRule']);

			$childDiscoveryRules[$discoveryRuleId]['hostPrototypes'][] = $childHostPrototype;
		}

		// match each discovery that the parent host prototypes belong to to the child discovery rule for each host
		$discoveryRuleChildren = [];
		foreach ($childDiscoveryRules as $childRule) {
			$discoveryRuleChildren[$childRule['templateid']][$childRule['hostid']] = $childRule['itemid'];
		}

		$newHostPrototypes = [];
		foreach ($chdHosts as $host) {
			$hostId = $host['hostid'];

			// skip items not from parent templates of current host
			$templateIds = zbx_toHash($host['parentTemplates'], 'templateid');
			$parentHostPrototypes = [];
			foreach ($hostPrototypes as $inum => $parentHostPrototype) {
				$parentTemplateId = $discoveryRules[$parentHostPrototype['ruleid']]['hostid'];

				if (isset($templateIds[$parentTemplateId])) {
					$parentHostPrototypes[$inum] = $parentHostPrototype;
				}
			}

			foreach ($parentHostPrototypes as $parentHostPrototype) {
				$childDiscoveryRuleId = $discoveryRuleChildren[$parentHostPrototype['ruleid']][$hostId];
				$exHostPrototype = null;

				// check if the child discovery rule already has host prototypes
				$exHostPrototypes = $childDiscoveryRules[$childDiscoveryRuleId]['hostPrototypes'];
				if ($exHostPrototypes) {
					$exHostPrototypesHosts = zbx_toHash($exHostPrototypes, 'host');
					$exHostPrototypesTemplateIds = zbx_toHash($exHostPrototypes, 'templateid');

					// look for an already created inherited host prototype
					// if one exists - update it
					if (isset($exHostPrototypesTemplateIds[$parentHostPrototype['hostid']])) {
						$exHostPrototype = $exHostPrototypesTemplateIds[$parentHostPrototype['hostid']];

						// check if there's a host prototype on the target host with the same host name but from a different template
						// or no template
						if (isset($exHostPrototypesHosts[$parentHostPrototype['host']])
							&& !idcmp($exHostPrototypesHosts[$parentHostPrototype['host']]['templateid'], $parentHostPrototype['hostid'])) {

							$discoveryRule = DBfetch(DBselect('SELECT i.name FROM items i WHERE i.itemid='.zbx_dbstr($exHostPrototype['discoveryRule']['itemid'])));
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host prototype "%1$s" already exists on "%2$s".', $parentHostPrototype['host'], $discoveryRule['name']));
						}
					}

					// look for a host prototype with the same host name
					// if one exists - convert it to an inherited host prototype
					if (isset($exHostPrototypesHosts[$parentHostPrototype['host']])) {
						$exHostPrototype = $exHostPrototypesHosts[$parentHostPrototype['host']];

						// check that this host prototype is not inherited from a different template
						if ($exHostPrototype['templateid'] > 0 && !idcmp($exHostPrototype['templateid'], $parentHostPrototype['hostid'])) {
							$discoveryRule = DBfetch(DBselect('SELECT i.name FROM items i WHERE i.itemid='.zbx_dbstr($exHostPrototype['discoveryRule']['itemid'])));
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host prototype "%1$s" already exists on "%2$s", inherited from another template.', $parentHostPrototype['host'], $discoveryRule['name']));
						}
					}
				}

				// copy host prototype
				$newHostPrototype = $parentHostPrototype;
				$newHostPrototype['ruleid'] = $discoveryRuleChildren[$parentHostPrototype['ruleid']][$hostId];
				$newHostPrototype['templateid'] = $parentHostPrototype['hostid'];

				// update an existing inherited host prototype
				if ($exHostPrototype) {
					// look for existing group prototypes to update
					$exGroupPrototypesByTemplateId = zbx_toHash($exHostPrototype['groupPrototypes'], 'templateid');
					$exGroupPrototypesByName = zbx_toHash($exHostPrototype['groupPrototypes'], 'name');
					$exGroupPrototypesByGroupId = zbx_toHash($exHostPrototype['groupLinks'], 'groupid');

					// look for a group prototype that can be updated
					foreach ($newHostPrototype['groupPrototypes'] as &$groupPrototype) {
						// updated an inherited item prototype by templateid
						if (isset($exGroupPrototypesByTemplateId[$groupPrototype['group_prototypeid']])) {
							$groupPrototype['group_prototypeid'] = $exGroupPrototypesByTemplateId[$groupPrototype['group_prototypeid']]['group_prototypeid'];
						}
						// updated an inherited item prototype by name
						elseif (isset($groupPrototype['name']) && !zbx_empty($groupPrototype['name'])
								&& isset($exGroupPrototypesByName[$groupPrototype['name']])) {

							$groupPrototype['templateid'] = $groupPrototype['group_prototypeid'];
							$groupPrototype['group_prototypeid'] = $exGroupPrototypesByName[$groupPrototype['name']]['group_prototypeid'];
						}
						// updated an inherited item prototype by group ID
						elseif (isset($groupPrototype['groupid']) && $groupPrototype['groupid']
								&& isset($exGroupPrototypesByGroupId[$groupPrototype['groupid']])) {

							$groupPrototype['templateid'] = $groupPrototype['group_prototypeid'];
							$groupPrototype['group_prototypeid'] = $exGroupPrototypesByGroupId[$groupPrototype['groupid']]['group_prototypeid'];
						}
						// create a new child group prototype
						else {
							$groupPrototype['templateid'] = $groupPrototype['group_prototypeid'];
							unset($groupPrototype['group_prototypeid']);
						}

						unset($groupPrototype['hostid']);
					}
					unset($groupPrototype);

					$newHostPrototype['hostid'] = $exHostPrototype['hostid'];
				}
				// create a new inherited host prototype
				else {
					foreach ($newHostPrototype['groupPrototypes'] as &$groupPrototype) {
						$groupPrototype['templateid'] = $groupPrototype['group_prototypeid'];
						unset($groupPrototype['group_prototypeid'], $groupPrototype['hostid']);
					}
					unset($groupPrototype);

					unset($newHostPrototype['hostid']);
				}
				$newHostPrototypes[] = $newHostPrototype;
			}
		}

		return $newHostPrototypes;
	}

	/**
	 * Inherits all host prototypes from the templates given in "templateids" to hosts or templates given in "hostids".
	 *
	 * @param array $data
	 *
	 * @return bool
	 */
	public function syncTemplates(array $data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$discoveryRules = API::DiscoveryRule()->get([
			'output' => ['itemid'],
			'hostids' => $data['templateids']
		]);
		$hostPrototypes = $this->get([
			'discoveryids' => zbx_objectValues($discoveryRules, 'itemid'),
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND,
			'selectGroupLinks' => API_OUTPUT_EXTEND,
			'selectGroupPrototypes' => API_OUTPUT_EXTEND,
			'selectTemplates' => ['templateid'],
			'selectDiscoveryRule' => ['itemid'],
			'selectInventory' => ['inventory_mode']
		]);

		foreach ($hostPrototypes as &$hostPrototype) {
			// merge group links into group prototypes
			foreach ($hostPrototype['groupLinks'] as $group) {
				$hostPrototype['groupPrototypes'][] = $group;
			}
			unset($hostPrototype['groupLinks']);

			// the ID of the discovery rule must be passed in the "ruleid" parameter
			$hostPrototype['ruleid'] = $hostPrototype['discoveryRule']['itemid'];
			unset($hostPrototype['discoveryRule']);
		}
		unset($hostPrototype);

		$this->inherit($hostPrototypes, $data['hostids']);

		return true;
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $hostPrototypeIds
	 * @param bool 	$nopermissions
	 */
	protected function validateDelete($hostPrototypeIds, $nopermissions) {
		if (!$hostPrototypeIds) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		if (!$nopermissions) {
			$this->checkHostPrototypePermissions($hostPrototypeIds);
			$this->checkNotInherited($hostPrototypeIds);
		}
	}

	/**
	 * Delete host prototypes.
	 *
	 * @param array 	$hostPrototypeIds
	 * @param bool 		$nopermissions		if set to true, permission and template checks will be skipped
	 *
	 * @return array
	 */
	public function delete(array $hostPrototypeIds, $nopermissions = false) {
		$this->validateDelete($hostPrototypeIds, $nopermissions);

		// include child IDs
		$parentHostPrototypeIds = $hostPrototypeIds;
		$childHostPrototypeIds = [];
		do {
			$query = DBselect('SELECT h.hostid FROM hosts h WHERE '.dbConditionInt('h.templateid', $parentHostPrototypeIds));
			$parentHostPrototypeIds = [];
			while ($hostPrototype = DBfetch($query)) {
				$parentHostPrototypeIds[] = $hostPrototype['hostid'];
				$childHostPrototypeIds[] = $hostPrototype['hostid'];
			}
		} while (!empty($parentHostPrototypeIds));

		$hostPrototypeIds = array_merge($hostPrototypeIds, $childHostPrototypeIds);

		$deleteHostPrototypes = $this->get([
			'hostids' => $hostPrototypeIds,
			'output' => ['host'],
			'selectGroupPrototypes' => ['group_prototypeid'],
			'selectParentHost' => ['host'],
			'nopermissions' => true
		]);

		// delete discovered hosts
		$discoveredHosts = DBfetchArray(DBselect(
			'SELECT hostid FROM host_discovery WHERE '.dbConditionInt('parent_hostid', $hostPrototypeIds)
		));
		if ($discoveredHosts) {
			API::Host()->delete(zbx_objectValues($discoveredHosts, 'hostid'), true);
		}

		// delete group prototypes and discovered groups
		$groupPrototypeIds = [];
		foreach ($deleteHostPrototypes as $groupPrototype) {
			foreach ($groupPrototype['groupPrototypes'] as $groupPrototype) {
				$groupPrototypeIds[] = $groupPrototype['group_prototypeid'];
			}
		}
		$this->deleteGroupPrototypes($groupPrototypeIds);

		// delete host prototypes
		DB::delete($this->tableName(), ['hostid' => $hostPrototypeIds]);

		// TODO: REMOVE info
		foreach ($deleteHostPrototypes as $hostProtototype) {
			info(_s('Deleted: Host prototype "%1$s" on "%2$s".', $hostProtototype['host'], $hostProtototype['parentHost']['host']));
		}

		return ['hostids' => $hostPrototypeIds];
	}

	protected function link(array $templateids, array $targetids) {
		$this->checkHostPrototypePermissions($targetids);

		$links = parent::link($templateids, $targetids);

		foreach ($targetids as $targetid) {
			$linked_templates = API::Template()->get([
				'output' => [],
				'hostids' => [$targetid],
				'nopermissions' => true
			]);

			$result = DBselect(
				'SELECT i.key_,count(*)'.
				' FROM items i'.
				' WHERE '.dbConditionInt('i.hostid', array_merge($templateids, array_keys($linked_templates))).
				' GROUP BY i.key_'.
				' HAVING count(*)>1',
				1
			);
			if ($row = DBfetch($result)) {
				$target_templates = API::HostPrototype()->get([
					'output' => ['name'],
					'hostids' => [$targetid],
					'nopermissions' => true
				]);

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Item "%1$s" already exists on "%2$s", inherited from another template.', $row['key_'],
						$target_templates[0]['name']
					)
				);
			}
		}

		return $links;
	}

	/**
	 * Checks if the current user has access to the given LLD rules.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given LLD rules
	 *
	 * @param array $ruleids
	 */
	protected function checkDiscoveryRulePermissions(array $ruleids) {
		$count = API::DiscoveryRule()->get([
			'countOutput' => true,
			'itemids' => $ruleids,
			'editable' => true
		]);

		if ($count != count($ruleids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Checks if the current user has access to the given host groups.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given host groups
	 *
	 * @param array $groupids
	 */
	protected function checkHostGroupsPermissions(array $groupids) {
		$db_groups = API::HostGroup()->get([
			'output' => ['name', 'flags'],
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($groupids as $groupid) {
			if (!array_key_exists($groupid, $db_groups)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_group = $db_groups[$groupid];

			// Check if group prototypes use discovered host groups.
			if ($db_group['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Group prototype cannot be based on a discovered host group "%1$s".', $db_group['name'])
				);
			}
		}
	}

	/**
	 * Checks if the current user has access to the given host prototypes.
	 *
	 * @throws APIException if the user doesn't have write permissions for the host prototypes.
	 *
	 * @param array $hostPrototypeIds
	 */
	protected function checkHostPrototypePermissions(array $hostPrototypeIds) {
		if ($hostPrototypeIds) {
			$hostPrototypeIds = array_unique($hostPrototypeIds);

			$count = $this->get([
				'countOutput' => true,
				'hostids' => $hostPrototypeIds,
				'editable' => true
			]);

			if ($count != count($hostPrototypeIds)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Checks if the given host prototypes are not inherited from a template.
	 *
	 * @throws APIException 	if at least one host prototype is inherited
	 *
	 * @param array $hostPrototypeIds
	 */
	protected function checkNotInherited(array $hostPrototypeIds) {
		$query = DBSelect('SELECT hostid FROM hosts h WHERE h.templateid>0 AND '.dbConditionInt('h.hostid', $hostPrototypeIds), 1);

		if ($hostPrototype = DBfetch($query)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Cannot delete templated host prototype.'));
		}
	}

	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

		// do not return host prototypes from discovered hosts
		$sqlParts['from'][] = 'host_discovery hd';
		$sqlParts['from'][] = 'items i';
		$sqlParts['from'][] = 'hosts ph';
		$sqlParts['where'][] = $this->fieldId('hostid').'=hd.hostid';
		$sqlParts['where'][] = 'hd.parent_itemid=i.itemid';
		$sqlParts['where'][] = 'i.hostid=ph.hostid';
		$sqlParts['where'][] = 'ph.flags='.ZBX_FLAG_DISCOVERY_NORMAL;

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM '.
					'host_discovery hd,items i,hosts_groups hgg'.
					' JOIN rights r'.
						' ON r.id=hgg.groupid'.
						' AND '.dbConditionInt('r.groupid', getUserGroupsByUserId(self::$userData['userid'])).
				' WHERE h.hostid=hd.hostid'.
					' AND hd.parent_itemid=i.itemid'.
					' AND i.hostid=hgg.hostid'.
				' GROUP BY hgg.hostid'.
				' HAVING MIN(r.permission)>'.PERM_DENY.
				' AND MAX(r.permission)>='.zbx_dbstr($permission).
				')';
		}

		// discoveryids
		if ($options['discoveryids'] !== null) {
			$sqlParts['where'][] = dbConditionInt('hd.parent_itemid', (array) $options['discoveryids']);

			if ($options['groupCount']) {
				$sqlParts['group']['hd'] = 'hd.parent_itemid';
			}
		}

		// inherited
		if ($options['inherited'] !== null) {
			$sqlParts['where'][] = ($options['inherited']) ? 'h.templateid IS NOT NULL' : 'h.templateid IS NULL';
		}

		return $sqlParts;
	}

	/**
	 * Retrieves and adds additional requested data to the result set.
	 *
	 * @param array  $options
	 * @param array  $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$hostPrototypeIds = array_keys($result);

		// adding discovery rule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'hostid', 'parent_itemid', 'host_discovery');
			$discoveryRules = API::DiscoveryRule()->get([
				'output' => $options['selectDiscoveryRule'],
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		// adding group links
		if ($options['selectGroupLinks'] !== null && $options['selectGroupLinks'] != API_OUTPUT_COUNT) {
			$groupPrototypes = DBFetchArray(DBselect(
				'SELECT hg.group_prototypeid,hg.hostid'.
					' FROM group_prototype hg'.
					' WHERE '.dbConditionInt('hg.hostid', $hostPrototypeIds).
					' AND hg.groupid IS NOT NULL'
			));
			$relationMap = $this->createRelationMap($groupPrototypes, 'hostid', 'group_prototypeid');
			$groupPrototypes = API::getApiService()->select('group_prototype', [
				'output' => $options['selectGroupLinks'],
				'group_prototypeids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			foreach ($groupPrototypes as &$groupPrototype) {
				unset($groupPrototype['name']);
			}
			unset($groupPrototype);
			$result = $relationMap->mapMany($result, $groupPrototypes, 'groupLinks');
		}

		// adding group prototypes
		if ($options['selectGroupPrototypes'] !== null && $options['selectGroupPrototypes'] != API_OUTPUT_COUNT) {
			$groupPrototypes = DBFetchArray(DBselect(
				'SELECT hg.group_prototypeid,hg.hostid'.
				' FROM group_prototype hg'.
				' WHERE '.dbConditionInt('hg.hostid', $hostPrototypeIds).
					' AND hg.groupid IS NULL'
			));
			$relationMap = $this->createRelationMap($groupPrototypes, 'hostid', 'group_prototypeid');
			$groupPrototypes = API::getApiService()->select('group_prototype', [
				'output' => $options['selectGroupPrototypes'],
				'group_prototypeids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			foreach ($groupPrototypes as &$groupPrototype) {
				unset($groupPrototype['groupid']);
			}
			unset($groupPrototype);
			$result = $relationMap->mapMany($result, $groupPrototypes, 'groupPrototypes');
		}

		// adding host
		if ($options['selectParentHost'] !== null && $options['selectParentHost'] != API_OUTPUT_COUNT) {
			$relationMap = new CRelationMap();
			$dbRules = DBselect(
				'SELECT hd.hostid,i.hostid AS parent_hostid'.
					' FROM host_discovery hd,items i'.
					' WHERE '.dbConditionInt('hd.hostid', $hostPrototypeIds).
					' AND hd.parent_itemid=i.itemid'
			);
			while ($relation = DBfetch($dbRules)) {
				$relationMap->addRelation($relation['hostid'], $relation['parent_hostid']);
			}

			$hosts = API::Host()->get([
				'output' => $options['selectParentHost'],
				'hostids' => $relationMap->getRelatedIds(),
				'templated_hosts' => true,
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $hosts, 'parentHost');
		}

		// adding templates
		if ($options['selectTemplates'] !== null) {
			if ($options['selectTemplates'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'hostid', 'templateid', 'hosts_templates');
				$templates = API::Template()->get([
					'output' => $options['selectTemplates'],
					'templateids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				$result = $relationMap->mapMany($result, $templates, 'templates');
			}
			else {
				$templates = API::Template()->get([
					'hostids' => $hostPrototypeIds,
					'countOutput' => true,
					'groupCount' => true
				]);
				$templates = zbx_toHash($templates, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['templates'] = array_key_exists($hostid, $templates)
						? $templates[$hostid]['rowscount']
						: '0';
				}
			}
		}

		// adding inventory
		if ($options['selectInventory'] !== null) {
			$inventory = API::getApiService()->select('host_inventory', [
				'output' => ['hostid', 'inventory_mode'],
				'filter' => ['hostid' => $hostPrototypeIds],
				'preservekeys' => true
			]);

			foreach ($hostPrototypeIds as $host_prototypeid) {
				// There is no DB record if inventory mode is HOST_INVENTORY_DISABLED.
				if (!array_key_exists($host_prototypeid, $inventory)) {
					$inventory[$host_prototypeid] = [
						'hostid' => (string) $host_prototypeid,
						'inventory_mode' => (string) HOST_INVENTORY_DISABLED
					];
				}
			}

			$relation_map = $this->createRelationMap($result, 'hostid', 'hostid');
			$inventory = $this->unsetExtraFields($inventory, ['hostid', 'inventory_mode'], $options['selectInventory']);
			$result = $relation_map->mapOne($result, $inventory, 'inventory');
		}

		return $result;
	}

	/**
	 * Deletes the given group prototype and all discovered groups.
	 * Deletes also group prototype children.
	 *
	 * @param array $groupPrototypeIds
	 */
	protected function deleteGroupPrototypes(array $groupPrototypeIds) {
		// delete child group prototypes
		$groupPrototypeChildren = DBfetchArray(DBselect(
			'SELECT gp.group_prototypeid FROM group_prototype gp WHERE '.dbConditionInt('templateid', $groupPrototypeIds)
		));
		if ($groupPrototypeChildren) {
			$this->deleteGroupPrototypes(zbx_objectValues($groupPrototypeChildren, 'group_prototypeid'));
		}

		// delete discovered groups
		$hostGroups = DBfetchArray(DBselect(
			'SELECT groupid FROM group_discovery WHERE '.dbConditionInt('parent_group_prototypeid', $groupPrototypeIds)
		));
		if ($hostGroups) {
			API::HostGroup()->delete(zbx_objectValues($hostGroups, 'groupid'), true);
		}

		// delete group prototypes
		DB::delete('group_prototype', ['group_prototypeid' => $groupPrototypeIds]);
	}
}
