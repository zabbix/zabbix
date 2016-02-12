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

	/**
	 * Returns the parameters for creating a discovery rule validator.
	 *
	 * @return array
	 */
	protected function getDruleSchema() {
		return [
			'validators' => [
				'name' => new CStringValidator([
					'messageEmpty' => _('Empty discovery rule name.'),
					'messageInvalid' => _('Incorrect value "%1$s" for discovery rule name.')
				]),
				'iprange' => new CIPRangeValidator([
					'ipRangeLimit' => ZBX_DISCOVERER_IPRANGE_LIMIT
				]),
				'delay' => new CStringValidator([
					'regex' => '/^([1-9]|[0-9]{2,5}|[0-5][0-9]{5}|60[0-3][0-9]{3}|604([0-7][0-9]{2}|[0-8]00))$/',
					'messageEmpty' => _('Empty delay for discovery rule "%1$s".'),
					'messageRegex' => _('Incorrect delay for discovery rule "%1$s".'),
					'messageInvalid' => _('Incorrect delay for discovery rule "%1$s".')
				]),
				'proxy_hostid' => new CIdValidator([
					'empty' => true,
					'messageInvalid' => _('Incorrect proxy ID for discovery rule "%1$s".')
				]),
				'status' => new CLimitedSetValidator([
					'values' => [
						DRULE_STATUS_DISABLED,
						DRULE_STATUS_ACTIVE
					],
					'messageInvalid' => _('Incorrect status for discovery rule "%1$s".')
				]),
				'dchecks' => new CCollectionValidator([
					'messageEmpty' => _('Cannot save discovery rule without checks.'),
					'messageInvalid' => _('Incorrect checks for discovery rule "%1$s".')
				])
			],
			'required' => ['name', 'iprange', 'dchecks'],
			'messageRequired' => _('No "%2$s" given for discovery rule "%1$s".'),
			'messageUnsupported' => _('Unsupported parameter "%2$s" for discovery rule "%1$s".')
		];
	}

	/**
	 * Returns the parameters for creating a discovery check validator.
	 *
	 * @return array
	 */
	protected function getDCheckSchema() {
		$dcheck_types = [SVC_SSH, SVC_LDAP, SVC_SMTP, SVC_FTP, SVC_HTTP, SVC_POP, SVC_NNTP, SVC_IMAP, SVC_TCP,
			SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2c, SVC_ICMPPING, SVC_SNMPv3, SVC_HTTPS, SVC_TELNET
		];

		return [
			'validators' => [
				'dcheckid' => new CIdValidator([
					'empty' => true,
					'messageInvalid' => _('Incorrect discovery check ID for discovery rule "%1$s".')
				]),
				'key_' => new CStringValidator([
					'empty' => true,
					'messageInvalid' => _('Incorrect discovery check "key_" value for discovery rule "%1$s".')
				]),
				'ports' => new CStringValidator([
					'empty' => true,
					'messageInvalid' => _('Incorrect discovery check "ports" value for discovery rule "%1$s".')
				]),
				'snmp_community' => new CStringValidator([
					'empty' => true,
					'messageInvalid' => _('Incorrect discovery check "snmp_community" value for discovery rule "%1$s".')
				]),
				'snmpv3_authpassphrase' => new CStringValidator([
					'empty' => true,
					'messageInvalid' =>
						_('Incorrect discovery check "snmpv3_authpassphrase" value for discovery rule "%1$s".')
				]),
				'snmpv3_authprotocol' => new CStringValidator([
					'regex' => '/^(0|1)$/',
					'messageEmpty' => _('Empty discovery check "snmpv3_authprotocol" field for discovery rule "%1$s".'),
					'messageRegex' => _(
						'Incorrect discovery check "snmpv3_authprotocol" value for discovery rule "%1$s".'
					),
					'messageInvalid' => _(
						'Incorrect discovery check "snmpv3_authprotocol" value for discovery rule "%1$s".'
					)
				]),
				'snmpv3_contextname' => new CStringValidator([
					'empty' => true,
					'messageInvalid' => _(
						'Incorrect discovery check "snmpv3_contextname" value for discovery rule "%1$s".'
					)
				]),
				'snmpv3_privpassphrase' => new CStringValidator([
					'empty' => true,
					'messageInvalid' => _(
						'Incorrect discovery check "snmpv3_privpassphrase" value for discovery rule "%1$s".'
					)
				]),
				'snmpv3_privprotocol' => new CStringValidator([
					'regex' => '/^(0|1)$/',
					'messageEmpty' => _('Empty discovery check "snmpv3_privprotocol" field for discovery rule "%1$s".'),
					'messageRegex' => _(
						'Incorrect discovery check "snmpv3_privprotocol" value for discovery rule "%1$s".'
					),
					'messageInvalid' => _(
						'Incorrect discovery check "snmpv3_privprotocol" value for discovery rule "%1$s".'
					)
				]),
				'snmpv3_securitylevel' => new CStringValidator([
					'regex' => '/^([0-2])$/',
					'messageEmpty' => _(
						'Empty discovery check "snmpv3_securitylevel" field for discovery rule "%1$s".'
					),
					'messageRegex' => _(
						'Incorrect discovery check "snmpv3_securitylevel" value for discovery rule "%1$s".'
					),
					'messageInvalid' => _(
						'Incorrect discovery check "snmpv3_securitylevel" value for discovery rule "%1$s".'
					)
				]),
				'snmpv3_securityname' => new CStringValidator([
					'empty' => true,
					'messageInvalid' => _(
						'Incorrect discovery check "snmpv3_securityname" value for discovery rule "%1$s".'
					)
				]),
				'type' => new CLimitedSetValidator([
					'values' => $dcheck_types,
					'messageInvalid' => _('Incorrect discovery check type for discovery rule "%1$s".')
				]),
				'uniq' => new CStringValidator([
					'regex' => '/^(0|1)$/',
					'messageEmpty' => _('Incorrect discovery check "uniq" value for discovery rule "%1$s".'),
					'messageRegex' => _('Incorrect discovery check "uniq" value for discovery rule "%1$s".'),
					'messageInvalid' => _('Incorrect discovery check "uniq" value for discovery rule "%1$s".')
				])
			],
			'required' => ['type'],
			'messageRequired' => _('No  discovery check "%2$s" given for discovery rule "%1$s".'),
			'messageUnsupported' => _('Unsupported  discovery check parameter "%2$s" for discovery rule "%1$s".')
		];
	}

	/**
	 * Validate the input parameters for create() method.
	 *
	 * @param array $drules		Discovery rules data.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array $drules) {
		// Check permissions.
		if (self::$userData['type'] == USER_TYPE_ZABBIX_USER) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		if (!$drules) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$drule_validator = new CPartialSchemaValidator($this->getDruleSchema());

		foreach ($drules as $drule) {
			$drule_validator->setObjectName(
				array_key_exists('name', $drule) && $drule['name'] !== null
				? $drule['name']
				: ''
			);
			$this->checkValidator($drule, $drule_validator);
		}

		// Check drule name duplicates in input data.
		$dublicate_names = CArrayHelper::findDuplicate($drules, 'name');
		if ($dublicate_names) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Discovery rule "%1$s" already exists.', $dublicate_names['name'])
			);
		}

		// Check drule name duplicates in DB.
		$db_dublicate_names = API::getApiService()->select($this->tableName(), [
			'output' => ['name'],
			'filter' => ['name' => zbx_objectValues($drules, 'name')],
			'limit' => 1
		]);

		if ($db_dublicate_names) {
			$db_dublicate_name = reset($db_dublicate_names);
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Discovery rule "%1$s" already exists.', $db_dublicate_name['name'])
			);
		}

		// Check proxy IDs.
		$proxies = zbx_objectValues($drules, 'proxy_hostid');
		if ($proxies) {
			$db_proxies = API::proxy()->get([
				'output' => ['proxyid'],
				'proxyids' => $proxies,
				'preservekeys' => true
			]);
			foreach ($proxies as $proxy) {
				if ($proxy != 0 && !array_key_exists($proxy, $db_proxies)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect proxy ID "%1$s".', $proxy));
				}
			}
		}

		// Validate discovery checks.
		$this->validateDChecks($drules);
	}

	/**
	 * Validate the input parameters for update() method.
	 *
	 * @param array $drules			Discovery rules data.
	 * @param array $db_drules		DB discovery rules data.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array $drules, array $db_drules) {
		// Check permissions.
		if (self::$userData['type'] == USER_TYPE_ZABBIX_USER) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		if (!$drules) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$check_names = [];

		// Fetch missing data from DB.
		$drules = $this->extendFromObjects(zbx_toHash($drules, 'druleid'), $db_drules, ['name']);

		$drule_validator = new CPartialSchemaValidator($this->getDruleSchema());
		$drule_validator->setValidator('druleid', null);

		foreach ($drules as $drule) {
			if (!array_key_exists($drule['druleid'], $db_drules)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}

			$drule_validator->setObjectName(array_key_exists('name', $drule) ? $drule['name'] : '');
			$this->checkPartialValidator($drule, $drule_validator);

			if ($db_drules[$drule['druleid']]['name'] !== $drule['name']) {
				$check_names[] = $drule;
			}
		}

		if ($check_names) {
			// Check drule name duplicates in input data.
			$dublicate_names = CArrayHelper::findDuplicate($check_names, 'name');
			if ($dublicate_names) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Discovery rule "%1$s" already exists.', $dublicate_names['name'])
				);
			}

			// Check drule name duplicates in DB.
			$db_dublicate_names = API::getApiService()->select($this->tableName(), [
				'output' => ['name'],
				'filter' => ['name' => zbx_objectValues($check_names, 'name')],
				'limit' => 1
			]);

			if ($db_dublicate_names) {
				$db_dublicate_name = reset($db_dublicate_names);
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Discovery rule "%1$s" already exists.', $db_dublicate_name['name'])
				);
			}
		}

		// Check proxy IDs.
		$proxies = zbx_objectValues($drules, 'proxy_hostid');
		if ($proxies) {
			$db_proxies = API::proxy()->get([
				'output' => ['proxyid'],
				'proxyids' => $proxies,
				'preservekeys' => true
			]);
			foreach ($proxies as $proxy) {
				if ($proxy != 0 && !array_key_exists($proxy, $db_proxies)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect proxy ID "%1$s".', $proxy));
				}
			}
		}

		// Validate discovery checks.
		$this->validateDChecks($drules);
	}

	protected function validateDChecks(array $drules) {
		$item_key_parser = new CItemKey();
		$dcheck_validator = new CPartialSchemaValidator($this->getDCheckSchema());

		foreach ($drules as $drule) {
			if (!array_key_exists('dchecks', $drule)) {
				continue;
			}

			$uniq = 0;

			foreach ($drule['dchecks'] as $dcnum => $dcheck) {
				$drule_name = array_key_exists('name', $drule) ? $drule['name'] : '';
				$dcheck_validator->setObjectName($drule_name);
				$this->checkValidator($dcheck, $dcheck_validator);

				if (array_key_exists('uniq', $dcheck) && ($dcheck['uniq'] == 1)) {
					if (!in_array($dcheck['type'], [SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3])) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Only Zabbix agent, SNMPv1, SNMPv2 and SNMPv3 checks can be made unique.')
						);
					}

					$uniq++;
				}

				if (array_key_exists('ports', $dcheck) && !validate_port_list($dcheck['ports'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect port range.'));
				}

				switch ($dcheck['type']) {
					case SVC_AGENT:
						if (!array_key_exists('key_', $dcheck) || $dcheck['key_'] === null) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect key.'));
						}

						if ($item_key_parser->parse($dcheck['key_']) != CParser::PARSE_SUCCESS) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Invalid key "%1$s": %2$s.', $dcheck['key_'], $item_key_parser->getError())
							);
						}
						break;
					case SVC_SNMPv1:
					case SVC_SNMPv2c:
						if (!array_key_exists('snmp_community', $dcheck) || $dcheck['snmp_community'] === null
								|| $dcheck['snmp_community'] === false || $dcheck['snmp_community'] === '') {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect SNMP community.'));
						}
					case SVC_SNMPv3:
						if (!array_key_exists('key_', $dcheck) || $dcheck['key_'] === null || $dcheck['key_'] === false
								|| $dcheck['key_'] === '') {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect SNMP OID.'));
						}
						break;
				}

				// validate snmpv3 fields
				if (array_key_exists('snmpv3_securitylevel', $dcheck)
						&& $dcheck['snmpv3_securitylevel'] != ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV) {
					// snmpv3 authprotocol
					if ($dcheck['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV
							|| $dcheck['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
						if (!array_key_exists('snmpv3_authprotocol', $dcheck)
								|| $dcheck['snmpv3_authprotocol'] != ITEM_AUTHPROTOCOL_MD5
									&& $dcheck['snmpv3_authprotocol'] != ITEM_AUTHPROTOCOL_SHA) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect authentication protocol for discovery rule "%1$s".', $drule_name)
							);
						}
					}

					// snmpv3 privprotocol
					if ($dcheck['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
						if (!array_key_exists('snmpv3_privprotocol', $dcheck)
								|| $dcheck['snmpv3_privprotocol'] != ITEM_PRIVPROTOCOL_DES
									&& $dcheck['snmpv3_privprotocol'] != ITEM_PRIVPROTOCOL_AES) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect privacy protocol for discovery rule "%1$s".', $drule_name)
							);
						}
					}
				}
			}

			$this->validateDuplicateChecks($drule['dchecks']);

			if ($uniq > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Only one check can be unique.'));
			}
		}
	}

	protected function validateDuplicateChecks(array $dchecks) {
		$default_values = DB::getDefaults('dchecks');

		foreach ($dchecks as &$dcheck) {
			// set default values for snmpv3 fields
			if (!array_key_exists('snmpv3_securitylevel', $dcheck) || $dcheck['snmpv3_securitylevel'] === null) {
				$dcheck['snmpv3_securitylevel'] = ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV;
			}

			switch ($dcheck['snmpv3_securitylevel']) {
				case ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV:
					$dcheck['snmpv3_authprotocol'] = ITEM_AUTHPROTOCOL_MD5;
					$dcheck['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
					$dcheck['snmpv3_authpassphrase'] = '';
					$dcheck['snmpv3_privpassphrase'] = '';
					break;
				case ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV:
					$dcheck['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
					$dcheck['snmpv3_privpassphrase'] = '';
					break;
			}

			$dcheck += $default_values;
			unset($dcheck['uniq']);
		}
		unset($dcheck);

		while ($current = array_pop($dchecks)) {
			foreach ($dchecks as $dcheck) {
				$equal = true;
				foreach ($dcheck as $field => $value) {
					if (array_key_exists($field, $current) && (strcmp($value, $current[$field]) !== 0)) {
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
		$drules = zbx_toArray($drules);
		$this->validateCreate($drules);

		$druleids = DB::insert('drules', $drules);

		$create_dchecks = [];
		foreach ($drules as $dnum => $drule) {
			foreach ($drule['dchecks'] as $dcheck) {
				$dcheck['druleid'] = $druleids[$dnum];
				$create_dchecks[] = $dcheck;
			}
		}

		DB::insert('dchecks', $create_dchecks);

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
	 * ) $drules
	 *
	 * @return array
	 */
	public function update(array $drules) {
		$drules = zbx_toArray($drules);

		// Validate given IDs.
		$this->checkObjectIds($drules, 'druleid',
			_('No "%1$s" given for discovery rule.'),
			_('Empty discovery rule ID.'),
			_('Incorrect discovery rule ID.')
		);

		$druleids = zbx_objectValues($drules, 'druleid');

		$db_drules = API::DRule()->get([
			'output' => ['druleid', 'proxy_hostid', 'name', 'iprange', 'delay', 'nextcheck', 'status'],
			'druleids' => $druleids,
			'selectDChecks' => ['dcheckid', 'druleid', 'type', 'key_', 'snmp_community', 'ports', 'snmpv3_securityname',
				'snmpv3_securitylevel', 'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'uniq', 'snmpv3_authprotocol',
				'snmpv3_privprotocol', 'snmpv3_contextname'],
			'editable' => true,
			'preservekeys' => true
		]);

		$this->validateUpdate($drules, $db_drules);

		foreach ($drules as $drule) {
			// update drule if it's modified
			if (DB::recordModified($this->tableName(), $db_drules[$drule['druleid']], $drule)) {
				DB::updateByPk($this->tableName(), $drule['druleid'], $drule);
			}

			// update dchecks
			$db_dchecks = $db_drules[$drule['druleid']]['dchecks'];

			$new_dchecks = [];
			$old_dchecks = [];

			if (array_key_exists('dchecks', $drule)) {
				foreach ($drule['dchecks'] as $check) {
					$check['druleid'] = $drule['druleid'];

					if (!isset($check['dcheckid'])) {
						$new_dchecks[] = $check;
					}
					else {
						$old_dchecks[] = $check;
					}
				}
			}

			$del_dcheckids = array_diff(
				zbx_objectValues($db_dchecks, 'dcheckid'),
				zbx_objectValues($old_dchecks, 'dcheckid')
			);

			if ($del_dcheckids) {
				$this->deleteActionConditions($del_dcheckids);
			}

			DB::replace('dchecks', $db_dchecks, array_merge($old_dchecks, $new_dchecks));
		}

		return ['druleids' => $druleids];
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
