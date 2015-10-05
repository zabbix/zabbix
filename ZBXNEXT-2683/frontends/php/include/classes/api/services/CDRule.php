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
 * Class containing methods for operations with discovery rules.
 *
 * @package API
 */
class CDRule extends CApiService {

	protected $tableName = 'drules';
	protected $tableAlias = 'dr';
	protected $sortColumns = ['druleid', 'name'];

	/**
	 * Get drule data.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get(array $options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['drules' => 'dr.druleid'],
			'from'		=> ['drules' => 'drules dr'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
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
			'output'					=> API_OUTPUT_EXTEND,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		if (CWebUser::getType() < USER_TYPE_ZABBIX_ADMIN) {
			return [];
		}

// druleids
		if (!is_null($options['druleids'])) {
			zbx_value2array($options['druleids']);
			$sqlParts['where']['druleid'] = dbConditionInt('dr.druleid', $options['druleids']);
		}

// dhostids
		if (!is_null($options['dhostids'])) {
			zbx_value2array($options['dhostids']);

			$sqlParts['from']['dhosts'] = 'dhosts dh';
			$sqlParts['where']['dhostid'] = dbConditionInt('dh.dhostid', $options['dhostids']);
			$sqlParts['where']['dhdr'] = 'dh.druleid=dr.druleid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dhostid'] = 'dh.dhostid';
			}
		}

// dserviceids
		if (!is_null($options['dserviceids'])) {
			zbx_value2array($options['dserviceids']);

			$sqlParts['from']['dhosts'] = 'dhosts dh';
			$sqlParts['from']['dservices'] = 'dservices ds';

			$sqlParts['where']['dserviceid'] = dbConditionInt('ds.dserviceid', $options['dserviceids']);
			$sqlParts['where']['dhdr'] = 'dh.druleid=dr.druleid';
			$sqlParts['where']['dhds'] = 'dh.dhostid=ds.dhostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dserviceid'] = 'ds.dserviceid';
			}
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
		$dbRes = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($drule = DBfetch($dbRes)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount']))
					$result[] = $drule;
				else
					$result = $drule['rowscount'];
			}
			else {
				$result[$drule['druleid']] = $drule;
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

	public function checkInput(array &$dRules) {
		$dRules = zbx_toArray($dRules);

		if (empty($dRules)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input.'));
		}

		if (CWebUser::getType() < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		$proxies = [];
		$ipRangeValidator = new CIPRangeValidator(['ipRangeLimit' => ZBX_DISCOVERER_IPRANGE_LIMIT]);
		foreach ($dRules as $dRule) {
			if (!isset($dRule['iprange'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('IP range cannot be empty.'));
			}

			if (!$ipRangeValidator->validate($dRule['iprange'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $ipRangeValidator->getError());
			}

			if (isset($dRule['delay']) && $dRule['delay'] < 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect delay.'));
			}

			if (isset($dRule['status']) && (($dRule['status'] != DRULE_STATUS_DISABLED) && ($dRule['status'] != DRULE_STATUS_ACTIVE))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect status.'));
			}

			if (empty($dRule['dchecks'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot save discovery rule without checks.'));
			}

			$this->validateDChecks($dRule['dchecks']);

			if (isset($dRule['proxy_hostid']) && $dRule['proxy_hostid']) {
				$proxies[] = $dRule['proxy_hostid'];
			}
		}

		if (!empty($proxies)) {
			$proxiesDB = API::proxy()->get([
				'proxyids' => $proxies,
				'output' => ['proxyid'],
				'preservekeys' => true,
			]);
			foreach ($proxies as $proxy) {
				if (!isset($proxiesDB[$proxy])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect proxyid.'));
				}
			}
		}
	}

	protected function validateDChecks(array &$dChecks) {
		$uniq = 0;
		$item_key_parser = new CItemKey();

		foreach ($dChecks as $dcnum => $dCheck) {
			if (isset($dCheck['uniq']) && ($dCheck['uniq'] == 1)) {
				if (!in_array($dCheck['type'], [SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('Only Zabbix agent, SNMPv1, SNMPv2 and SNMPv3 checks can be made unique.')
					);
				}

				$uniq++;
			}

			if (isset($dCheck['ports']) && !validate_port_list($dCheck['ports'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect port range.'));
			}

			switch ($dCheck['type']) {
				case SVC_AGENT:
					if (!isset($dCheck['key_'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect key.'));
					}

					if ($item_key_parser->parse($dCheck['key_']) != CParser::PARSE_SUCCESS) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Invalid key "%1$s": %2$s.', $dCheck['key_'], $item_key_parser->getError())
						);
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
				if (str_in_array($dCheck['snmpv3_securitylevel'], [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])) {
					if (zbx_empty($dCheck['snmpv3_authprotocol'])
							|| (isset($dCheck['snmpv3_authprotocol'])
									&& !str_in_array($dCheck['snmpv3_authprotocol'], [ITEM_AUTHPROTOCOL_MD5, ITEM_AUTHPROTOCOL_SHA]))) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect authentication protocol for discovery rule "%1$s".', $dCheck['name']));
					}
				}

				// snmpv3 privprotocol
				if ($dCheck['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
					if (zbx_empty($dCheck['snmpv3_privprotocol'])
							|| (isset($dCheck['snmpv3_privprotocol'])
									&& !str_in_array($dCheck['snmpv3_privprotocol'], [ITEM_PRIVPROTOCOL_DES, ITEM_PRIVPROTOCOL_AES]))) {
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
	public function create(array $dRules) {
		$this->checkInput($dRules);
		$this->validateRequiredFields($dRules, __FUNCTION__);

		// check host name duplicates
		$collectionValidator = new CCollectionValidator([
			'uniqueField' => 'name',
			'messageDuplicate' => _('Discovery rule "%1$s" already exists.')
		]);
		$this->checkValidator($dRules, $collectionValidator);

		// checking to the duplicate names
		$dbDRules = API::getApiService()->select($this->tableName(), [
			'output' => ['name'],
			'filter' => ['name' => zbx_objectValues($dRules, 'name')],
			'limit' => 1
		]);

		if ($dbDRules) {
			$dbDRule = reset($dbDRules);
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Discovery rule "%1$s" already exists.', $dbDRule['name']));
		}

		$druleids = DB::insert('drules', $dRules);

		$dChecksCreate = [];
		foreach ($dRules as $dNum => $dRule) {
			foreach ($dRule['dchecks'] as $dCheck) {
				$dCheck['druleid'] = $druleids[$dNum];
				$dChecksCreate[] = $dCheck;
			}
		}

		DB::insert('dchecks', $dChecksCreate);

		return ['druleids' => $druleids];
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
	 * ) $dRules
	 *
	 * @return array
	 */
	public function update(array $dRules) {
		$this->checkInput($dRules);
		$this->validateRequiredFields($dRules, __FUNCTION__);

		$dRuleIds = zbx_objectValues($dRules, 'druleid');

		$dRulesDb = API::DRule()->get([
			'druleids' => $dRuleIds,
			'output' => API_OUTPUT_EXTEND,
			'selectDChecks' => API_OUTPUT_EXTEND,
			'editable' => true,
			'preservekeys' => true
		]);

		$defaultValues = DB::getDefaults('dchecks');

		$dRulesUpdate = [];
		$dCheckIdsDelete = [];
		$dChecksCreate = [];
		$dRuleNamesChanged = [];

		// validate drule duplicate names
		foreach ($dRules as $dRule) {
			if (!isset($dRulesDb[$dRule['druleid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}

			if ($dRulesDb[$dRule['druleid']]['name'] !== $dRule['name']) {
				if (isset($dRuleNamesChanged[$dRule['name']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Discovery rule "%1$s" already exists.',
						$dRule['name']
					));
				}
				else {
					$dRuleNamesChanged[$dRule['name']] = $dRule['name'];
				}
			}
		}

		if ($dRuleNamesChanged) {
			$dbDRules = API::getApiService()->select($this->tableName(), [
				'output' => ['name'],
				'filter' => ['name' => $dRuleNamesChanged],
				'limit' => 1
			]);

			if ($dbDRules) {
				$dbDRule = reset($dbDRules);
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Discovery rule "%1$s" already exists.',
					$dbDRule['name']
				));
			}
		}

		foreach ($dRules as $dRule) {
			$dRulesUpdate[] = [
				'values' => $dRule,
				'where' => ['druleid' => $dRule['druleid']]
			];

			// update dchecks
			$dbChecks = $dRulesDb[$dRule['druleid']]['dchecks'];

			$newChecks = [];
			$oldChecks = [];

			foreach ($dRule['dchecks'] as $check) {
				$check['druleid'] = $dRule['druleid'];

				if (!isset($check['dcheckid'])) {
					$newChecks[] = array_merge($defaultValues, $check);
				}
				else {
					$oldChecks[] = $check;
				}
			}

			$delDCheckIds = array_diff(
				zbx_objectValues($dbChecks, 'dcheckid'),
				zbx_objectValues($oldChecks, 'dcheckid')
			);

			if ($delDCheckIds) {
				$this->deleteActionConditions($delDCheckIds);
			}

			DB::replace('dchecks', $dbChecks, array_merge($oldChecks, $newChecks));
		}

		DB::update('drules', $dRulesUpdate);

		return ['druleids' => $dRuleIds];
	}

	/**
	 * Delete drules.
	 *
	 * @param array $dRuleIds
	 *
	 * @return array
	 */
	public function delete(array $dRuleIds) {
		$this->validateDelete($dRuleIds);

		$actionIds = [];
		$conditionIds = [];

		$dCheckIds = [];

		$dbChecks = DBselect('SELECT dc.dcheckid FROM dchecks dc WHERE '.dbConditionInt('dc.druleid', $dRuleIds));

		while ($dbCheck = DBfetch($dbChecks)) {
			$dCheckIds[] = $dbCheck['dcheckid'];
		}

		$dbConditions = DBselect(
			'SELECT c.conditionid,c.actionid'.
			' FROM conditions c'.
			' WHERE (c.conditiontype='.CONDITION_TYPE_DRULE.' AND '.dbConditionString('c.value', $dRuleIds).')'.
				' OR (c.conditiontype='.CONDITION_TYPE_DCHECK.' AND '.dbConditionString('c.value', $dCheckIds).')'
		);

		while ($dbCondition = DBfetch($dbConditions)) {
			$conditionIds[] = $dbCondition['conditionid'];
			$actionIds[] = $dbCondition['actionid'];
		}

		if ($actionIds) {
			DB::update('actions', [
				'values' => ['status' => ACTION_STATUS_DISABLED],
				'where' => ['actionid' => array_unique($actionIds)]
			]);
		}

		if ($conditionIds) {
			DB::delete('conditions', ['conditionid' => $conditionIds]);
		}

		$result = DB::delete('drules', ['druleid' => $dRuleIds]);
		if ($result) {
			foreach ($dRuleIds as $dRuleId) {
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_DISCOVERY_RULE, '['.$dRuleId.']');
			}
		}

		return ['druleids' => $dRuleIds];
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
		$actionIds = [];

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
			DB::update('actions', [
				'values' => ['status' => ACTION_STATUS_DISABLED],
				'where' => ['actionid' => $actionIds],
			]);

			DB::delete('conditions', [
				'conditiontype' => CONDITION_TYPE_DCHECK,
				'value' => $dCheckIds
			]);
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

		$count = $this->get([
			'druleids' => $ids,
			'countOutput' => true
		]);

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

		$count = $this->get([
			'druleids' => $ids,
			'editable' => true,
			'countOutput' => true
		]);

		return (count($ids) == $count);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$druleids = array_keys($result);

		// Adding Discovery Checks
		if (!is_null($options['selectDChecks'])) {
			if ($options['selectDChecks'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'druleid', 'dcheckid', 'dchecks');
				$dchecks = API::DCheck()->get([
					'output' => $options['selectDChecks'],
					'dcheckids' => $relationMap->getRelatedIds(),
					'nopermissions' => true,
					'preservekeys' => true
				]);
				if (!is_null($options['limitSelects'])) {
					order_result($dchecks, 'dcheckid');
				}
				$result = $relationMap->mapMany($result, $dchecks, 'dchecks', $options['limitSelects']);
			}
			else {
				$dchecks = API::DCheck()->get([
					'druleids' => $druleids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);
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
				$dhosts = API::DHost()->get([
					'output' => $options['selectDHosts'],
					'dhostids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				if (!is_null($options['limitSelects'])) {
					order_result($dhosts, 'dhostid');
				}
				$result = $relationMap->mapMany($result, $dhosts, 'dhosts', $options['limitSelects']);
			}
			else {
				$dhosts = API::DHost()->get([
					'druleids' => $druleids,
					'countOutput' => true,
					'groupCount' => true
				]);
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
