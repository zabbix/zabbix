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
 * Class containing methods for operations with host prototypes.
 *
 * @package API
 */
class CHostPrototype extends CZBXAPI {

	protected $tableName = 'hosts';
	protected $tableAlias = 'h';
	protected $sortColumns = array('hostid', 'host', 'name', 'status');

	public function __construct() {
		parent::__construct();

		$this->getOptions = array_merge($this->getOptions, array(
			'discoveryids'  => null,
			'sortfield'     => '',
			'sortorder'     => ''
		));
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
		$options['filter']['flags'] = ZBX_FLAG_DISCOVERY_CHILD;

		// build and execute query
		$sql = $this->createSelectQuery($this->tableName(), $options);
		$res = DBselect($sql, $options['limit']);

		// fetch results
		$result = array();
		while ($row = DBfetch($res)) {
			// a count query, return a single result
			if ($options['countOutput'] !== null) {
			if ($options['groupCount'] !== null) {
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

		if ($options['countOutput'] !== null) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, array('triggerid'), $options['output']);
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
	 * @param array $hostPrototypes
	 *
	 * @return void
	 */
	protected function validateCreate(array $hostPrototypes) {
		$parameters = array(
			'host' => null,
			'name' => null,
			'ruleid' => null,
			'status' => HOST_STATUS_MONITORED
		);

		foreach ($hostPrototypes as $hostPrototype) {
			if (!check_db_fields($parameters, $hostPrototype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for host prototype "%1$s".', $hostPrototype['host']));
			}

			$this->checkUnsupportedFields($this->tableName(), $hostPrototype,
				_s('Wrong fields for host prototype "%1$s".', $hostPrototype['host']),
				array('ruleid', 'templates')
			);

			$this->checkHost($hostPrototype);
			$this->checkName($hostPrototype);
			$this->checkStatus($hostPrototype);
			$this->checkId($hostPrototype['ruleid'],
				_s('Incorrect discovery rule ID for host prototype "%1$s".', $hostPrototype['host'])
			);
		}

		$this->checkDiscoveryRulePermissions(zbx_objectValues($hostPrototypes, 'ruleid'));

		// template permissions
		$templates = array();
		foreach ($hostPrototypes as $hostPrototype) {
			if (isset($hostPrototype['templates'])) {
				$templates = array_merge($templates, $hostPrototype['templates']);
			}
		}
		$this->checkTemplatePermissions(zbx_objectValues($templates, 'templateid'));

		$this->checkDuplicates($hostPrototypes, 'host', _('Host prototype "%1$s" already exists.'));
		$this->checkExistingHostPrototypes($hostPrototypes);
	}

	/**
	 * Creates the given host prototypes.
	 *
	 * @param array $hostPrototypes
	 *
	 * @return array
	 */
	public function create(array $hostPrototypes) {
		$hostPrototypes = zbx_toArray($hostPrototypes);

		foreach ($hostPrototypes as $hostPrototype) {
			if (isset($hostPrototype['templates'])) {
				$hostPrototype['templates'] = zbx_toArray($hostPrototype['templates']);
			}
		}

		$this->validateCreate($hostPrototypes);

		foreach ($hostPrototypes as &$hostPrototype) {
			$hostPrototype['flags'] = ZBX_FLAG_DISCOVERY_CHILD;
		}

		// save the host prototypes
		$hostPrototypeIds = DB::insert($this->tableName(), $hostPrototypes);

		$hostPrototypeDiscoveryRules = array();
		foreach ($hostPrototypes as $key => $hostPrototype) {
			$hostPrototype['hostid'] = $hostPrototypeIds[$key];

			$hostPrototypeDiscoveryRules[] = array(
				'hostid' => $hostPrototype['hostid'],
				'parent_itemid' => $hostPrototype['ruleid']
			);
		}

		// link host prototypes to discovery rules
		DB::insert('host_discovery', $hostPrototypeDiscoveryRules);

		// TODO: inheritance

		return array('hostids' => $hostPrototypeIds);
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $hostPrototypes
	 *
	 * @return void
	 */
	public function validateUpdate(array $hostPrototypes) {
		foreach ($hostPrototypes as $host) {
			if (empty($host['hostid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
		}

		$hostPrototypes = zbx_toHash($hostPrototypes, 'hostid');

		$this->checkHostPrototypePermissions(zbx_objectValues($hostPrototypes, 'hostid'));

		$hostPrototypes = $this->extendObjects($this->tableName(), $hostPrototypes, array('host'));

		foreach ($hostPrototypes as $hostPrototype) {
			$this->checkUnsupportedFields($this->tableName(), $hostPrototype,
				_s('Wrong fields for host prototype "%1$s".', $hostPrototype['host']),
				array('ruleid', 'templates')
			);

			if (isset($hostPrototype['host'])) {
				$this->checkHost($hostPrototype);
			}
			if (isset($hostPrototype['name'])) {
				$this->checkName($hostPrototype);
			}
			if (isset($hostPrototype['status'])) {
				$this->checkStatus($hostPrototype);
			}
			if (isset($hostPrototype['ruleid'])) {
				$this->checkId($hostPrototype['ruleid'],
					_s('Incorrect discovery rule ID for host prototype "%1$s".', $hostPrototype['host'])
				);
			}
		}

		$this->checkDiscoveryRulePermissions(zbx_objectValues($hostPrototypes, 'ruleid'));

		// template permissions
		$templates = array();
		foreach ($hostPrototypes as $hostPrototype) {
			if (isset($hostPrototype['templates'])) {
				$templates = array_merge($templates, $hostPrototype['templates']);
			}
		}
		$this->checkTemplatePermissions(zbx_objectValues($templates, 'templateid'));

		// check for duplicates
		$relationMap = $this->createRelationMap($hostPrototypes, 'hostid', 'parent_itemid', 'host_discovery');
		$hostPrototypes = $relationMap->mapIdOne($hostPrototypes, 'ruleid');
		$this->checkDuplicates($hostPrototypes, 'host', _('Host prototype "%1$s" already exists.'));
		$this->checkExistingHostPrototypes($hostPrototypes);
	}

	/**
	 * Updates the given host prototypes.
	 *
	 * @param array $hostPrototypes
	 *
	 * @return array
	 */
	public function update(array $hostPrototypes) {
		$hostPrototypes = zbx_toArray($hostPrototypes);

		foreach ($hostPrototypes as $hostPrototype) {
			if (isset($hostPrototype['templates'])) {
				$hostPrototype['templates'] = zbx_toArray($hostPrototype['templates']);
			}
		}

		$this->validateUpdate($hostPrototypes);

		// save the host prototypes
		foreach ($hostPrototypes as $hostPrototype) {
			DB::updateByPk($this->tableName(), $hostPrototype['hostid'], $hostPrototype);
		}

		// TODO: inheritance

		return array('hostids' => zbx_objectValues($hostPrototypes, 'hostid'));
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $hostPrototypeIds
	 *
	 * @return void
	 */
	public function validateDelete($hostPrototypeIds) {
		if (!$hostPrototypeIds) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$this->checkHostPrototypePermissions($hostPrototypeIds);
	}

	/**
	 * Delete host prototypes.
	 *
	 * @param $hostPrototypeIds
	 *
	 * @return array
	 */
	public function delete($hostPrototypeIds) {
		$hostPrototypeIds = zbx_toArray($hostPrototypeIds);
		$this->validateDelete($hostPrototypeIds);

		DB::delete($this->tableName(), array('hostid' => $hostPrototypeIds));

		return array('hostids' => $hostPrototypeIds);
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
			'hostids' => $ids,
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
	 * Validates the "host" field.
	 *
	 * @throws APIException if the name is missing
	 *
	 * @param array $host
	 *
	 * @return void
	 */
	protected function checkHost(array $host) {
		if (zbx_empty($host['host'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty host.'));
		}

		// Check if host name isn't longer than 64 chars
		if (zbx_strlen($host['host']) > 64) {
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_n(
					'Maximum host name length is %2$d characters, "%3$s" is %1$d character.',
					'Maximum host name length is %2$d characters, "%3$s" is %1$d characters.',
					zbx_strlen($host['host']),
					64,
					$host['host']
				)
			);
		}

		if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $host['host'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect characters used for host "%s".', $host['host']));
		}
	}

	/**
	 * Validates the "name" field. Assumes the "host" field is valid.
	 *
	 * @throws APIException if the name is missing
	 *
	 * @param array $host
	 *
	 * @return void
	 */
	protected function checkName(array $host) {
		if (zbx_empty($host['name'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Empty name for host prototype "%1$s".', $host['host']));
		}
	}

	/**
	 * Validates the "host" field. Assumes the "host" field is valid.
	 *
	 * @throws APIException if the status is incorrect
	 *
	 * @param array $host
	 *
	 * @return void
	 */
	protected function checkStatus(array $host) {
		$statuses = array(
			HOST_STATUS_MONITORED => true,
			HOST_STATUS_NOT_MONITORED => true
		);

		if (!isset($statuses[$host['status']])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect status for host prototype "%1$s".', $host['host']));
		}
	}

	/**
	 * Checks if the current user has access to the given LLD rules.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given LLD rules
	 *
	 * @param array $discoveryRuleIds
	 */
	protected function checkDiscoveryRulePermissions(array $discoveryRuleIds) {
		if (!API::DiscoveryRule()->isWritable($discoveryRuleIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Checks if the current user has access to the given templates.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given templates.
	 *
	 * @param array $templateIds
	 */
	protected function checkTemplatePermissions(array $templateIds) {
		if (!API::Template()->isWritable($templateIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
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
		if (!$this->isWritable($hostPrototypeIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Check if any item from list already exists.
	 * If items have item ids it will check for existing item with different itemid.
	 *
	 * @throw APIException
	 *
	 * @param array $hostPrototypes
	 */
	protected function checkExistingHostPrototypes(array $hostPrototypes) {
		$hostsByDiscoveryRuleId = array();
		$hostIds = array();
		foreach ($hostPrototypes as $hostPrototype) {
			$hostsByDiscoveryRuleId[$hostPrototype['ruleid']][] = $hostPrototype['host'];

			if (isset($hostPrototype['hostid'])) {
				$hostIds[] = $hostPrototype['hostid'];
			}
		}

		$sqlWhere = array();
		foreach ($hostsByDiscoveryRuleId as $discoveryRuleId => $hosts) {
			$sqlWhere[] = '(hd.parent_itemid='.$discoveryRuleId.' AND '.dbConditionString('h.host', $hosts).')';
		}

		if ($sqlWhere) {
			$sql = 'SELECT i.name,h.host'.
				' FROM hosts h,host_discovery hd,items i'.
				' WHERE h.hostid=hd.hostid AND hd.parent_itemid=i.itemid AND ('.implode(' OR ', $sqlWhere).')';

			// if we update existing items we need to exclude them from result.
			if ($hostIds) {
				$sql .= ' AND '.dbConditionInt('h.hostid', $hostIds, true);
			}
			$query = DBselect($sql, 1);
			while ($row = DBfetch($query)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Host prototype "%1$s" already exists in discovery rule "%2$s".', $row['host'], $row['name']));
			}
		}
	}

	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

		if (CWebUser::getType() != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
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
				' AND MAX(r.permission)>='.$permission.
				')';
		}

		// discoveryids
		if ($options['discoveryids'] !== null) {
			$sqlParts['from'][] = 'host_discovery hd';
			$sqlParts['where'][] = $this->fieldId('hostid').'=hd.hostid';
			$sqlParts['where'][] = dbConditionInt('hd.parent_itemid', (array) $options['discoveryids']);

			if ($options['groupCount'] !== null) {
				$sqlParts['group']['hd'] = 'hd.parent_itemid';
			}
		}

		return $sqlParts;
	}
}
