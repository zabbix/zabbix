<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 */
class CDRule extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

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
			'editable'					=> false,
			'selectDHosts'				=> null,
			'selectDChecks'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
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

			if ($options['groupCount']) {
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

			if ($options['groupCount']) {
				$sqlParts['group']['dserviceid'] = 'ds.dserviceid';
			}
		}

// search
		if (!is_null($options['search'])) {
			zbx_db_search('drules dr', $options, $sqlParts);
		}

// filter
		if (is_array($options['filter'])) {
			if (array_key_exists('delay', $options['filter']) && $options['filter']['delay'] !== null) {
				$options['filter']['delay'] = getTimeUnitFilters($options['filter']['delay']);
			}

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
		$dbRes = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($drule = DBfetch($dbRes)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $drule;
				}
				else {
					$result = $drule['rowscount'];
				}
			}
			else {
				$result[$drule['druleid']] = $drule;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

	return $result;
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

		$proxy_hostids = [];

		$ip_range_parser = new CIPRangeParser(['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30]);

		foreach ($drules as $drule) {
			if (!array_key_exists('name', $drule)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Field "%1$s" is mandatory.', 'name'));
			}
			elseif (is_array($drule['name'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}
			elseif ($drule['name'] === '' || $drule['name'] === null || $drule['name'] === false) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'name', _('cannot be empty'))
				);
			}

			if (!array_key_exists('iprange', $drule) || $drule['iprange'] === '') {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'iprange', _('cannot be empty'))
				);
			}
			elseif (!$ip_range_parser->parse($drule['iprange'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'iprange', $ip_range_parser->getError())
				);
			}
			elseif (bccomp($ip_range_parser->getMaxIPCount(), ZBX_DISCOVERER_IPRANGE_LIMIT) > 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'iprange',
						_s('IP range "%1$s" exceeds "%2$s" address limit', $ip_range_parser->getMaxIPRange(),
							ZBX_DISCOVERER_IPRANGE_LIMIT
						)
					)
				);
			}

			if (array_key_exists('delay', $drule)
					&& !validateTimeUnit($drule['delay'], 1, SEC_PER_WEEK, false, $error, ['usermacros' => true])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'delay', $error)
				);
			}

			if (array_key_exists('status', $drule) && $drule['status'] != DRULE_STATUS_DISABLED
					&& $drule['status'] != DRULE_STATUS_ACTIVE) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value "%1$s" for "%2$s" field.', $drule['status'], 'status')
				);
			}

			if (array_key_exists('proxy_hostid', $drule)) {
				if (!zbx_is_int($drule['proxy_hostid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value "%1$s" for "%2$s" field.', $drule['proxy_hostid'], 'proxy_hostid')
					);
				}

				if ($drule['proxy_hostid'] > 0) {
					$proxy_hostids[] = $drule['proxy_hostid'];
				}
			}

			if (array_key_exists('dchecks', $drule) && $drule['dchecks']) {
				$this->validateDChecks($drule['dchecks']);
			}
			else {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot save discovery rule without checks.'));
			}
		}

		// Check drule name duplicates in input data.
		$duplicate = CArrayHelper::findDuplicate($drules, 'name');
		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Discovery rule "%1$s" already exists.', $duplicate['name'])
			);
		}

		// Check drule name duplicates in DB.
		$db_duplicate = $this->get([
			'output' => ['name'],
			'filter' => ['name' => zbx_objectValues($drules, 'name')],
			'limit' => 1
		]);

		if ($db_duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Discovery rule "%1$s" already exists.', $db_duplicate[0]['name'])
			);
		}

		// Check proxy IDs.
		if ($proxy_hostids) {
			$db_proxies = API::proxy()->get([
				'output' => ['proxyid'],
				'proxyids' => $proxy_hostids,
				'preservekeys' => true
			]);
			foreach ($proxy_hostids as $proxy_hostid) {
				if (!array_key_exists($proxy_hostid, $db_proxies)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value "%1$s" for "%2$s" field.', $proxy_hostid, 'proxy_hostid')
					);
				}
			}
		}
	}

	/**
	 * Validate the input parameters for update() method.
	 *
	 * @param array $drules			Discovery rules data.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array $drules) {
		// Check permissions.
		if (self::$userData['type'] == USER_TYPE_ZABBIX_USER) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		if (!$drules) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		// Validate given IDs.
		$this->checkObjectIds($drules, 'druleid',
			_('Field "%1$s" is mandatory.'),
			_s('Incorrect value for field "%1$s": %2$s.', 'druleid', _('cannot be empty')),
			_s('Incorrect value for field "%1$s": %2$s.', 'druleid', _('a numeric value is expected'))
		);

		$db_drules = $this->get([
			'output' => ['druleid', 'name'],
			'druleids' => zbx_objectValues($drules, 'druleid'),
			'preservekeys' => true
		]);

		$drule_names_changed = [];
		$proxy_hostids = [];

		$ip_range_parser = new CIPRangeParser(['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30]);

		foreach ($drules as $drule) {
			if (!array_key_exists($drule['druleid'], $db_drules)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}

			if (array_key_exists('name', $drule)) {
				if (is_array($drule['name'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}
				elseif ($drule['name'] === '' || $drule['name'] === null || $drule['name'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'name', _('cannot be empty'))
					);
				}

				if ($db_drules[$drule['druleid']]['name'] !== $drule['name']) {
					$drule_names_changed[] = $drule;
				}
			}

			if (array_key_exists('iprange', $drule)) {
				if ($drule['iprange'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'iprange', _('cannot be empty'))
					);
				}
				elseif (!$ip_range_parser->parse($drule['iprange'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'iprange', $ip_range_parser->getError())
					);
				}
				elseif (bccomp($ip_range_parser->getMaxIPCount(), ZBX_DISCOVERER_IPRANGE_LIMIT) > 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'iprange',
							_s('IP range "%1$s" exceeds "%2$s" address limit', $ip_range_parser->getMaxIPRange(),
								ZBX_DISCOVERER_IPRANGE_LIMIT
							)
						)
					);
				}
			}

			if (array_key_exists('delay', $drule)
					&& !validateTimeUnit($drule['delay'], 1, SEC_PER_WEEK, false, $error, ['usermacros' => true])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'delay', $error)
				);
			}

			if (array_key_exists('status', $drule) && $drule['status'] != DRULE_STATUS_DISABLED
					&& $drule['status'] != DRULE_STATUS_ACTIVE) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value "%1$s" for "%2$s" field.', $drule['status'], 'status')
				);
			}

			if (array_key_exists('proxy_hostid', $drule)) {
				if (!zbx_is_int($drule['proxy_hostid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value "%1$s" for "%2$s" field.', $drule['proxy_hostid'], 'proxy_hostid')
					);
				}

				if ($drule['proxy_hostid'] > 0) {
					$proxy_hostids[] = $drule['proxy_hostid'];
				}
			}

			if (array_key_exists('dchecks', $drule)) {
				if ($drule['dchecks']) {
					$this->validateDChecks($drule['dchecks']);
				}
				else {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot save discovery rule without checks.'));
				}
			}
		}

		if ($drule_names_changed) {
			// Check drule name duplicates in input data.
			$duplicate = CArrayHelper::findDuplicate($drule_names_changed, 'name');
			if ($duplicate) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Discovery rule "%1$s" already exists.', $duplicate['name'])
				);
			}

			// Check drule name duplicates in DB.
			$db_duplicate = $this->get([
				'output' => ['name'],
				'filter' => ['name' => zbx_objectValues($drule_names_changed, 'name')],
				'limit' => 1
			]);

			if ($db_duplicate) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Discovery rule "%1$s" already exists.', $db_duplicate[0]['name'])
				);
			}
		}

		// Check proxy IDs.
		if ($proxy_hostids) {
			$db_proxies = API::proxy()->get([
				'output' => ['proxyid'],
				'proxyids' => $proxy_hostids,
				'preservekeys' => true
			]);
			foreach ($proxy_hostids as $proxy_hostid) {
				if (!array_key_exists($proxy_hostid, $db_proxies)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value "%1$s" for "%2$s" field.', $proxy_hostid, 'proxy_hostid')
					);
				}
			}
		}
	}

	/**
	 * Validate discovery checks.
	 *
	 * @param array $dchecks
	 */
	protected function validateDChecks(array $dchecks) {
		$uniq = 0;
		$item_key_parser = new CItemKey();
		$source_values = [
			'name_source' => [ZBX_DISCOVERY_UNSPEC, ZBX_DISCOVERY_DNS, ZBX_DISCOVERY_IP, ZBX_DISCOVERY_VALUE],
			'host_source' => [ZBX_DISCOVERY_DNS, ZBX_DISCOVERY_IP, ZBX_DISCOVERY_VALUE]
		];

		if (!is_array($dchecks)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', 'dchecks', _('an array is expected'))
			);
		}

		foreach ($dchecks as $dcnum => $dcheck) {
			if (!is_array($dcheck)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'dchecks', _('an array is expected'))
				);
			}

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

			foreach ($source_values as $field => $values) {
				if (!array_key_exists($field, $dcheck)) {
					continue;
				}

				if (!in_array($dcheck['type'], [SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2c, SVC_SNMPv3])
						&& $dcheck[$field] == ZBX_DISCOVERY_VALUE) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', $field, $dcheck[$field])
					);
				}

				if (!in_array($dcheck[$field], $values)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', $field, $dcheck[$field])
					);
				}

				// Only one check can be equal ZBX_DISCOVERY_VALUE for 'host_source' and 'name_source' fields.
				if ($dcheck[$field] == ZBX_DISCOVERY_VALUE) {
					array_pop($source_values[$field]);
				}
			}

			$dcheck_types = [SVC_SSH, SVC_LDAP, SVC_SMTP, SVC_FTP, SVC_HTTP, SVC_POP, SVC_NNTP, SVC_IMAP, SVC_TCP,
				SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2c, SVC_ICMPPING, SVC_SNMPv3, SVC_HTTPS, SVC_TELNET
			];

			if (!array_key_exists('type', $dcheck)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Field "%1$s" is mandatory.', 'type'));
			}
			elseif (!is_numeric($dcheck['type']) || !in_array($dcheck['type'], $dcheck_types)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value "%1$s" for "%2$s" field.', $dcheck['type'], 'type')
				);
			}
			switch ($dcheck['type']) {
				case SVC_AGENT:
					if (!array_key_exists('key_', $dcheck)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Field "%1$s" is mandatory.', 'key_'));
					}

					if (is_array($dcheck['key_'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
					}

					if ($dcheck['key_'] === '' || $dcheck['key_'] === null || $dcheck['key_'] === false) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'key_', _('cannot be empty'))
						);
					}

					$length = mb_strlen($dcheck['key_']);
					$max_length = DB::getFieldLength('dchecks', 'key_');
					if ($length > $max_length) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'key_',
								_s('%1$d characters exceeds maximum length of %2$d characters', $length, $max_length)
							)
						);
					}

					if ($item_key_parser->parse($dcheck['key_']) != CParser::PARSE_SUCCESS) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Invalid key "%1$s": %2$s.', $dcheck['key_'], $item_key_parser->getError())
						);
					}
					break;

				case SVC_SNMPv1:
					// break; is not missing here
				case SVC_SNMPv2c:
					if (!array_key_exists('snmp_community', $dcheck) || $dcheck['snmp_community'] === null
							|| $dcheck['snmp_community'] === false || $dcheck['snmp_community'] === '') {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect SNMP community.'));
					}
					// break; is not missing here
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
							|| !array_key_exists($dcheck['snmpv3_authprotocol'], getSnmpV3AuthProtocols())) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value "%1$s" for "%2$s" field.',
								$dcheck['snmpv3_authprotocol'], 'snmpv3_authprotocol'
							)
						);
					}
				}

				// snmpv3 privprotocol
				if ($dcheck['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
					if (!array_key_exists('snmpv3_privprotocol', $dcheck)
							|| !array_key_exists($dcheck['snmpv3_privprotocol'], getSnmpV3PrivProtocols())) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value "%1$s" for "%2$s" field.',
								$dcheck['snmpv3_privprotocol'], 'snmpv3_privprotocol'
							)
						);
					}
				}
			}

			$this->validateDuplicateChecks($dchecks);
		}

		if ($uniq > 1) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Only one check can be unique.'));
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
					$dcheck['snmpv3_authprotocol'] = ITEM_SNMPV3_AUTHPROTOCOL_MD5;
					$dcheck['snmpv3_privprotocol'] = ITEM_SNMPV3_PRIVPROTOCOL_DES;
					$dcheck['snmpv3_authpassphrase'] = '';
					$dcheck['snmpv3_privpassphrase'] = '';
					break;
				case ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV:
					$dcheck['snmpv3_privprotocol'] = ITEM_SNMPV3_PRIVPROTOCOL_DES;
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

		array_walk($drules, function (&$drule, $index) use ($druleids) {
			$drule['druleid'] = $druleids[$index];
		});

		$this->addAuditBulk(CAudit::ACTION_ADD, CAudit::RESOURCE_DISCOVERY_RULE, $drules);

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
		$druleids = zbx_objectValues($drules, 'druleid');

		$this->validateUpdate($drules);

		$db_drules = API::DRule()->get([
			'output' => ['druleid', 'proxy_hostid', 'name', 'iprange', 'delay', 'status'],
			'selectDChecks' => ['dcheckid', 'druleid', 'type', 'key_', 'snmp_community', 'ports', 'snmpv3_securityname',
				'snmpv3_securitylevel', 'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'uniq', 'snmpv3_authprotocol',
				'snmpv3_privprotocol', 'snmpv3_contextname', 'host_source', 'name_source'
			],
			'druleids' => $druleids,
			'editable' => true,
			'preservekeys' => true
		]);

		$default_values = DB::getDefaults('dchecks');

		foreach ($drules as $drule) {
			$db_drule = $db_drules[$drule['druleid']];

			// Update drule if it's modified.
			if (DB::recordModified('drules', $db_drule, $drule)) {
				if (array_key_exists('delay', $drule) && $db_drule['delay'] != $drule['delay']) {
					$drule['nextcheck'] = 0;
				}

				DB::updateByPk('drules', $drule['druleid'], $drule);
			}

			if (array_key_exists('dchecks', $drule)) {
				// Update dchecks.
				$db_dchecks = $db_drule['dchecks'];

				$new_dchecks = [];
				$old_dchecks = [];

				foreach ($drule['dchecks'] as $check) {
					$check['druleid'] = $drule['druleid'];

					if (!isset($check['dcheckid'])) {
						$new_dchecks[] = array_merge($default_values, $check);
					}
					else {
						$old_dchecks[] = $check;
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
		}

		$this->addAuditBulk(CAudit::ACTION_UPDATE, CAudit::RESOURCE_DISCOVERY_RULE, $drules, $db_drules);

		return ['druleids' => $druleids];
	}

	/**
	 * @param array $druleids
	 *
	 * @return array
	 */
	public function delete(array $druleids) {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $druleids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_drules = $this->get([
			'output' => ['druleid', 'name'],
			'druleids' => $druleids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($druleids as $druleid) {
			if (!array_key_exists($druleid, $db_drules)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		// Check if discovery rules are used in actions.
		$db_actions = DBselect(
			'SELECT a.name,c.value'.
			' FROM actions a,conditions c'.
			' WHERE a.actionid=c.actionid'.
				' AND c.conditiontype='.CONDITION_TYPE_DRULE.
				' AND '.dbConditionString('c.value', $druleids),
			1
		);

		if ($db_action = DBfetch($db_actions)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Discovery rule "%1$s" is used in "%2$s" action.',
				$db_drules[$db_action['value']]['name'], $db_action['name']
			));
		}

		// Check if discovery checks are used in actions.
		$db_actions = DBselect(
			'SELECT a.name,dc.druleid'.
			' FROM actions a,conditions c,dchecks dc'.
			' WHERE a.actionid=c.actionid'.
				' AND '.zbx_dbcast_2bigint('c.value').'=dc.dcheckid'.
				' AND c.conditiontype='.CONDITION_TYPE_DCHECK.
				' AND '.dbConditionString('dc.druleid', $druleids),
			1
		);

		if ($db_action = DBfetch($db_actions)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Discovery rule "%1$s" is used in "%2$s" action.',
				$db_drules[$db_action['druleid']]['name'], $db_action['name']
			));
		}

		DB::delete('drules', ['druleid' => $druleids]);

		$this->addAuditBulk(CAudit::ACTION_DELETE, CAudit::RESOURCE_DISCOVERY_RULE, $db_drules);

		return ['druleids' => $druleids];
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
				'where' => ['actionid' => $actionIds]
			]);

			DB::delete('conditions', [
				'conditiontype' => CONDITION_TYPE_DCHECK,
				'value' => $dCheckIds
			]);
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$druleids = array_keys($result);

		// Adding Discovery Checks
		if (!is_null($options['selectDChecks'])) {
			if ($options['selectDChecks'] != API_OUTPUT_COUNT) {
				$dchecks = [];
				$relationMap = $this->createRelationMap($result, 'druleid', 'dcheckid', 'dchecks');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$dchecks = API::DCheck()->get([
						'output' => $options['selectDChecks'],
						'dcheckids' => $related_ids,
						'nopermissions' => true,
						'preservekeys' => true
					]);
					if (!is_null($options['limitSelects'])) {
						order_result($dchecks, 'dcheckid');
					}
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
					$result[$druleid]['dchecks'] = array_key_exists($druleid, $dchecks)
						? $dchecks[$druleid]['rowscount']
						: '0';
				}
			}
		}

		// Adding Discovery Hosts
		if (!is_null($options['selectDHosts'])) {
			if ($options['selectDHosts'] != API_OUTPUT_COUNT) {
				$dhosts = [];
				$relationMap = $this->createRelationMap($result, 'druleid', 'dhostid', 'dhosts');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$dhosts = API::DHost()->get([
						'output' => $options['selectDHosts'],
						'dhostids' => $related_ids,
						'preservekeys' => true
					]);
					if (!is_null($options['limitSelects'])) {
						order_result($dhosts, 'dhostid');
					}
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
					$result[$druleid]['dhosts'] = array_key_exists($druleid, $dhosts)
						? $dhosts[$druleid]['rowscount']
						: '0';
				}
			}
		}

		return $result;
	}
}
