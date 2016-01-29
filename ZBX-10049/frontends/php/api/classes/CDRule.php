<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * Class containing methods for operations with discovery rules.
 *
 * @package API
 */
class CDRule extends CZBXAPI {

	protected $tableName = 'drules';
	protected $tableAlias = 'dr';
	protected $sortColumns = array('druleid', 'name');

	/**
	 * Get drule data.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get(array $options = array()) {
		$result = array();
		$nodeCheck = false;

		$sqlParts = array(
			'select'	=> array('drules' => 'dr.druleid'),
			'from'		=> array('drules' => 'drules dr'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'druleids'					=> null,
			'dhostids'					=> null,
			'dserviceids'				=> null,
			'editable'					=> null,
			'selectDHosts'				=> null,
			'selectDChecks'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		if (CWebUser::getType() < USER_TYPE_ZABBIX_ADMIN) {
			return array();
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// druleids
		if (!is_null($options['druleids'])) {
			zbx_value2array($options['druleids']);
			$sqlParts['where']['druleid'] = dbConditionInt('dr.druleid', $options['druleids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'dr.druleid', $nodeids);
			}
		}

// dhostids
		if (!is_null($options['dhostids'])) {
			zbx_value2array($options['dhostids']);

			$sqlParts['select']['dhostid'] = 'dh.dhostid';
			$sqlParts['from']['dhosts'] = 'dhosts dh';
			$sqlParts['where']['dhostid'] = dbConditionInt('dh.dhostid', $options['dhostids']);
			$sqlParts['where']['dhdr'] = 'dh.druleid=dr.druleid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dhostid'] = 'dh.dhostid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'dh.dhostid', $nodeids);
			}
		}

// dserviceids
		if (!is_null($options['dserviceids'])) {
			zbx_value2array($options['dserviceids']);

			$sqlParts['select']['dserviceid'] = 'ds.dserviceid';
			$sqlParts['from']['dhosts'] = 'dhosts dh';
			$sqlParts['from']['dservices'] = 'dservices ds';

			$sqlParts['where']['dserviceid'] = dbConditionInt('ds.dserviceid', $options['dserviceids']);
			$sqlParts['where']['dhdr'] = 'dh.druleid=dr.druleid';
			$sqlParts['where']['dhds'] = 'dh.dhostid=ds.dhostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dserviceid'] = 'ds.dserviceid';
			}

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'ds.dserviceid', $nodeids);
			}
		}

		// node check !!!!!
		// should be last, after all ****IDS checks
		if (!$nodeCheck) {
			$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'dr.druleid', $nodeids);
		}

// search
		if (!is_null($options['search'])) {
			zbx_db_search('drules dr', $options, $sqlParts);
		}

// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('drules dr', $options, $sqlParts);
		}

// search
		if (is_array($options['search'])) {
			zbx_db_search('drules dr', $options, $sqlParts);
		}

// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}
//------------

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$dbRes = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($drule = DBfetch($dbRes)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount']))
					$result[] = $drule;
				else
					$result = $drule['rowscount'];
			}
			else{
				// dhostids
				if (isset($drule['dhostid']) && is_null($options['selectDHosts'])) {
					if (!isset($result[$drule['druleid']]['dhosts']))
						$result[$drule['druleid']]['dhosts'] = array();

					$result[$drule['druleid']]['dhosts'][] = array('dhostid' => $drule['dhostid']);
					unset($drule['dhostid']);
				}

				// dchecks
				if (isset($drule['dcheckid']) && is_null($options['selectDChecks'])) {
					if (!isset($result[$drule['druleid']]['dchecks']))
						$result[$drule['druleid']]['dchecks'] = array();

					$result[$drule['druleid']]['dchecks'][] = array('dcheckid' => $drule['dcheckid']);
					unset($drule['dcheckid']);
				}

				if (!isset($result[$drule['druleid']]))
					$result[$drule['druleid']]= array();

				$result[$drule['druleid']] += $drule;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}


	public function exists(array $object) {
		$options = array(
			'filter' => array(),
			'output' => array('druleid'),
			'nopermissions' => 1,
			'limit' => 1
		);
		if (isset($object['name'])) $options['filter']['name'] = $object['name'];
		if (isset($object['druleids'])) $options['druleids'] = zbx_toArray($object['druleids']);

		if (isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		elseif (isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = $this->get($options);

	return !empty($objs);
	}

	/**
	 * Check drules data
	 *
	 * @throw APIException
	 *
	 * @param array  $drules	Drules data array
	 * @param string $method	Caller function name
	 *
	 * @return void
	 */
	public function checkInput(array &$drules, $method) {
		$drules = zbx_toArray($drules);

		if (!$drules) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input.'));
		}

		if (CWebUser::getType() < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		$proxies = array();

		foreach ($drules as $drule) {
			if (array_key_exists('iprange', $drule)) {
				if (!validate_ip_range($drule['iprange'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect IP range "%s".', $drule['iprange']));
				}
			}
			else if ($method === 'create') {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('IP range cannot be empty.'));
			}

			if (array_key_exists('delay', $drule) && $drule['delay'] < 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect delay.'));
			}

			if (array_key_exists('status', $drule) && (($drule['status'] != DRULE_STATUS_DISABLED)
					&& ($drule['status'] != DRULE_STATUS_ACTIVE))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect status.'));
			}

			if (array_key_exists('dchecks', $drule) && $drule['dchecks']) {
				$this->validateDChecks($drule['dchecks']);
			}
			else if (array_key_exists('dchecks', $drule) || $method === 'create') {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot save discovery rule without checks.'));
			}

			if (array_key_exists('proxy_hostid', $drule) && $drule['proxy_hostid']) {
				$proxies[] = $drule['proxy_hostid'];
			}
		}

		if ($proxies) {
			$db_proxies = API::proxy()->get(array(
				'proxyids' => $proxies,
				'output' => array('proxyid'),
				'preservekeys' => true,
			));
			foreach ($proxies as $proxy) {
				if (!array_key_exists($proxy, $db_proxies)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect proxyid.'));
				}
			}
		}
	}

	protected function validateDChecks(array &$dChecks) {
		$uniq = 0;

		foreach ($dChecks as $dCheck) {
			if (!is_array($dCheck)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s".', 'dchecks'));
			}
		}

		foreach ($dChecks as $dcnum => $dCheck) {
			if (isset($dCheck['uniq']) && ($dCheck['uniq'] == 1)) $uniq++;

			if (isset($dCheck['ports']) && !validate_port_list($dCheck['ports'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect port range.'));
			}

			switch ($dCheck['type']) {
				case SVC_AGENT:
					if (!isset($dCheck['key_'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect key.'));
					}

					$itemKey = new CItemKey($dCheck['key_']);
					if (!$itemKey->isValid()) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid key "%1$s": %2$s.',
							$dCheck['key_'],
							$itemKey->getError()
						));
					}
					break;
				case SVC_SNMPv1:
				case SVC_SNMPv2c:
					if (!isset($dCheck['snmp_community']) || zbx_empty($dCheck['snmp_community'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect SNMP community.'));
					}
				case SVC_SNMPv3:
					if (!isset($dCheck['key_']) || zbx_empty($dCheck['key_'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect SNMP OID.'));
					}
					break;
			}

			// set default values for snmpv3 fields
			if (!isset($dCheck['snmpv3_securitylevel'])) {
				$dCheck['snmpv3_securitylevel'] = ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV;
			}

			switch ($dCheck['snmpv3_securitylevel']) {
				case ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV:
					$dChecks[$dcnum]['snmpv3_authprotocol'] = ITEM_AUTHPROTOCOL_MD5;
					$dChecks[$dcnum]['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
					$dChecks[$dcnum]['snmpv3_authpassphrase'] = $dChecks[$dcnum]['snmpv3_privpassphrase'] = '';
					break;
				case ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV:
					$dChecks[$dcnum]['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
					$dChecks[$dcnum]['snmpv3_privpassphrase'] = '';
					break;
			}

			// validate snmpv3 fields
			if (isset($dCheck['snmpv3_securitylevel']) && $dCheck['snmpv3_securitylevel'] != ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV) {
				// snmpv3 authprotocol
				if (str_in_array($dCheck['snmpv3_securitylevel'], array(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV))) {
					if (zbx_empty($dCheck['snmpv3_authprotocol'])
							|| (isset($dCheck['snmpv3_authprotocol'])
									&& !str_in_array($dCheck['snmpv3_authprotocol'], array(ITEM_AUTHPROTOCOL_MD5, ITEM_AUTHPROTOCOL_SHA)))) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect authentication protocol for discovery rule "%1$s".', $dCheck['name']));
					}
				}

				// snmpv3 privprotocol
				if ($dCheck['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
					if (zbx_empty($dCheck['snmpv3_privprotocol'])
							|| (isset($dCheck['snmpv3_privprotocol'])
									&& !str_in_array($dCheck['snmpv3_privprotocol'], array(ITEM_PRIVPROTOCOL_DES, ITEM_PRIVPROTOCOL_AES)))) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect privacy protocol for discovery rule "%1$s".', $dCheck['name']));
					}
				}
			}

			$this->validateDuplicateChecks($dChecks);
		}

		if ($uniq > 1) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Only one check can be unique.'));
		}
	}

	protected function validateRequiredFields($dRules, $on) {
		if ($on == 'update') {
			foreach ($dRules as $dRule) {
				if (!isset($dRule['druleid']) || zbx_empty($dRule['druleid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Field "druleid" is required.'));
				}
			}
		}
		else {
			foreach ($dRules as $dRule) {
				if (!isset($dRule['name']) || zbx_empty($dRule['name'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Field "name" is required.'));
				}
			}
		}
	}

	protected function validateDuplicateChecks(array $dChecks) {
		$defaultValues = DB::getDefaults('dchecks');
		foreach ($dChecks as &$dCheck) {
			$dCheck += $defaultValues;
			unset($dCheck['uniq']);
		}
		unset($dCheck);

		while ($current = array_pop($dChecks)) {
			foreach ($dChecks as $dCheck) {
				$equal = true;
				foreach ($dCheck as $fieldName => $dCheckField) {
					if (isset($current[$fieldName]) && (strcmp($dCheckField, $current[$fieldName]) !== 0)) {
						$equal = false;
						break;
					}
				}
				if ($equal) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Checks should be unique.'));
				}
			}
		}
	}

	/**
	 * Create new discovery rules.
	 *
	 * @param array(
	 *  name => string,
	 *  proxy_hostid => int,
	 *  iprange => string,
	 *  delay => string,
	 *  status => int,
	 *  dchecks => array(
	 *  	array(
	 *  		type => int,
	 *  		ports => string,
	 *  		key_ => string,
	 *  		snmp_community => string,
	 *  		snmpv3_securityname => string,
	 *  		snmpv3_securitylevel => int,
	 *  		snmpv3_authpassphrase => string,
	 *  		snmpv3_privpassphrase => string,
	 *  		uniq => int,
	 *  	), ...
	 *  )
	 * ) $drules
	 *
	 * @return array
	 */
	public function create(array $drules) {
		$this->checkInput($drules, __FUNCTION__);
		$this->validateRequiredFields($drules, __FUNCTION__);

		// checking to the duplicate names
		foreach ($drules as $drule) {
			if ($this->exists($drule)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Discovery rule "%s" already exists.', $drule['name']));
			}
		}

		$druleids = DB::insert('drules', $drules);

		$dchecks_create = array();

		foreach ($drules as $dnum => $drule) {
			foreach ($drule['dchecks'] as $dcheck) {
				$dcheck['druleid'] = $druleids[$dnum];
				$dchecks_create[] = $dcheck;
			}
		}

		DB::insert('dchecks', $dchecks_create);

		return array('druleids' => $druleids);
	}

	/**
	 * Update existing drules.
	 *
	 * @param array(
	 * 	druleid => int,
	 *  name => string,
	 *  proxy_hostid => int,
	 *  iprange => string,
	 *  delay => string,
	 *  status => int,
	 *  dchecks => array(
	 *  	array(
	 * 			dcheckid => int,
	 *  		type => int,
	 *  		ports => string,
	 *  		key_ => string,
	 *  		snmp_community => string,
	 *  		snmpv3_securityname => string,
	 *  		snmpv3_securitylevel => int,
	 *  		snmpv3_authpassphrase => string,
	 *  		snmpv3_privpassphrase => string,
	 *  		uniq => int,
	 *  	), ...
	 *  )
	 * ) $drules
	 *
	 * @return array
	 */
	public function update(array $drules) {
		$druleids = zbx_objectValues($drules, 'druleid');

		$db_drules = API::DRule()->get(array(
			'druleids' => $druleids,
			'output' => array('druleid', 'name'),
			'selectDChecks' => array('dcheckid'),
			'editable' => true,
			'preservekeys' => true
		));

		foreach ($druleids as $druleid) {
			if (!array_key_exists($druleid, $db_drules)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$this->checkInput($drules, __FUNCTION__);
		$this->validateRequiredFields($drules, __FUNCTION__);

		$default_values = DB::getDefaults('dchecks');

		$drules_update = array();

		foreach ($drules as $drule) {
			// validate drule duplicate names
			if ($drule['name'] !== NULL && strcmp($db_drules[$drule['druleid']]['name'], $drule['name']) != 0
					&& $this->exists($drule)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Discovery rule "%s" already exists.', $drule['name']));
			}

			$drules_update[] = array(
				'values' => $drule,
				'where' => array('druleid' => $drule['druleid'])
			);

			// update dchecks
			if ($drule['dchecks'] !== NULL) {
				$db_checks = $db_drules[$drule['druleid']]['dchecks'];

				$new_checks = array();

				foreach ($drule['dchecks'] as $cnum => $check) {
					if (!array_key_exists('druleid', $check)) {
						$check['druleid'] = $drule['druleid'];
						unset($check['dcheckid']);

						$new_checks[] = array_merge($default_values, $check);

						unset($drule['dchecks'][$cnum]);
					}
				}

				$dcheckids_delete = array_diff(
					zbx_objectValues($db_checks, 'dcheckid'),
					zbx_objectValues($drule['dchecks'], 'dcheckid')
				);

				if ($dcheckids_delete) {
					$this->deleteActionConditions($dcheckids_delete);
				}

				DB::replace('dchecks', $db_checks, array_merge($drule['dchecks'], $new_checks));
			}
		}

		DB::update('drules', $drules_update);

		return array('druleids' => $druleids);
	}

	/**
	 * Delete drules.
	 *
	 * @param array $druleIds
	 *
	 * @return boolean
	 */
	public function delete(array $druleIds) {
		$this->validateDelete($druleIds);

		$actionIds = array();

		$dbActions = DBselect(
			'SELECT DISTINCT actionid'.
			' FROM conditions'.
			' WHERE conditiontype='.CONDITION_TYPE_DRULE.
				' AND '.dbConditionString('value', $druleIds).
			' ORDER BY actionid'
		);
		while ($dbAction = DBfetch($dbActions)) {
			$actionIds[] = $dbAction['actionid'];
		}

		if ($actionIds) {
			DB::update('actions', array(
				'values' => array('status' => ACTION_STATUS_DISABLED),
				'where' => array('actionid' => $actionIds),
			));

			DB::delete('conditions', array(
				'conditiontype' => CONDITION_TYPE_DRULE,
				'value' => $druleIds
			));
		}

		$result = DB::delete('drules', array('druleid' => $druleIds));
		if ($result) {
			foreach ($druleIds as $druleId) {
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_DISCOVERY_RULE, '['.$druleId.']');
			}
		}

		return array('druleids' => $druleIds);
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $druleIds
	 *
	 * @return void
	 */
	protected function validateDelete(array $druleIds) {
		if (!$druleIds) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$this->checkDrulePermissions($druleIds);
	}

	/**
	 * Delete related action conditions.
	 *
	 * @param array $dCheckIds
	 */
	protected function deleteActionConditions(array $dCheckIds) {
		$actionIds = array();

		// conditions
		$dbActions = DBselect(
			'SELECT DISTINCT c.actionid'.
			' FROM conditions c'.
			' WHERE c.conditiontype='.CONDITION_TYPE_DCHECK.
				' AND '.dbConditionString('c.value', $dCheckIds).
			' ORDER BY c.actionid'
		);
		while ($dbAction = DBfetch($dbActions)) {
			$actionIds[] = $dbAction['actionid'];
		}

		// disabling actions with deleted conditions
		if ($actionIds) {
			DB::update('actions', array(
				'values' => array('status' => ACTION_STATUS_DISABLED),
				'where' => array('actionid' => $actionIds),
			));

			DB::delete('conditions', array(
				'conditiontype' => CONDITION_TYPE_DCHECK,
				'value' => $dCheckIds
			));
		}
	}

	/**
	 * Check if user has read permissions for discovery rule.
	 *
	 * @param array $ids
	 *
	 * @return bool
	 */
	public function isReadable(array $ids) {
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'druleids' => $ids,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	/**
	 * Check if user has write permissions for discovery rule.
	 *
	 * @param array $ids
	 *
	 * @return bool
	 */
	public function isWritable(array $ids) {
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'druleids' => $ids,
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$druleids = array_keys($result);

		// Adding Discovery Checks
		if (!is_null($options['selectDChecks'])) {
			if ($options['selectDChecks'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'druleid', 'dcheckid', 'dchecks');
				$dchecks = API::DCheck()->get(array(
					'output' => $options['selectDChecks'],
					'nodeids' => $options['nodeids'],
					'dcheckids' => $relationMap->getRelatedIds(),
					'nopermissions' => true,
					'preservekeys' => true
				));
				if (!is_null($options['limitSelects'])) {
					order_result($dchecks, 'dcheckid');
				}
				$result = $relationMap->mapMany($result, $dchecks, 'dchecks', $options['limitSelects']);
			}
			else {
				$dchecks = API::DCheck()->get(array(
					'nodeids' => $options['nodeids'],
					'druleids' => $druleids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				));
				$dchecks = zbx_toHash($dchecks, 'druleid');
				foreach ($result as $druleid => $drule) {
					if (isset($dchecks[$druleid]))
						$result[$druleid]['dchecks'] = $dchecks[$druleid]['rowscount'];
					else
						$result[$druleid]['dchecks'] = 0;
				}
			}
		}

		// Adding Discovery Hosts
		if (!is_null($options['selectDHosts'])) {
			if ($options['selectDHosts'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'druleid', 'dhostid', 'dhosts');
				$dhosts = API::DHost()->get(array(
					'output' => $options['selectDHosts'],
					'nodeids' => $options['nodeids'],
					'dhostids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				));
				if (!is_null($options['limitSelects'])) {
					order_result($dhosts, 'dhostid');
				}
				$result = $relationMap->mapMany($result, $dhosts, 'dhosts', $options['limitSelects']);
			}
			else {
				$dhosts = API::DHost()->get(array(
					'nodeids' => $options['nodeids'],
					'druleids' => $druleids,
					'countOutput' => true,
					'groupCount' => true
				));
				$dhosts = zbx_toHash($dhosts, 'druleid');
				foreach ($result as $druleid => $drule) {
					if (isset($dhosts[$druleid]))
						$result[$druleid]['dhosts'] = $dhosts[$druleid]['rowscount'];
					else
						$result[$druleid]['dhosts'] = 0;
				}
			}
		}

		return $result;
	}

	/**
	 * Checks if the current user has access to given discovery rules.
	 *
	 * @throws APIException if the user doesn't have write permissions for discovery rules.
	 *
	 * @param array $druleIds
	 *
	 * @return void
	 */
	protected function checkDrulePermissions(array $druleIds) {
		if (!$this->isWritable($druleIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}
}
