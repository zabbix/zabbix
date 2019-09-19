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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class containing methods for operations with item general.
 */
abstract class CItemGeneral extends CApiService {

	const ERROR_EXISTS_TEMPLATE = 'existsTemplate';
	const ERROR_EXISTS = 'exists';
	const ERROR_NO_INTERFACE = 'noInterface';
	const ERROR_INVALID_KEY = 'invalidKey';

	protected $fieldRules;

	/**
	 * @abstract
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	abstract public function get($options = []);

	public function __construct() {
		parent::__construct();

		// template - if templated item, value is taken from template item, cannot be changed on host
		// system - values should not be updated
		// host - value should be null for template items
		$this->fieldRules = [
			'type'					=> ['template' => 1],
			'snmp_community'		=> [],
			'snmp_oid'				=> ['template' => 1],
			'hostid'				=> [],
			'name'					=> ['template' => 1],
			'description'			=> [],
			'key_'					=> ['template' => 1],
			'master_itemid'			=> ['template' => 1],
			'delay'					=> [],
			'history'				=> [],
			'trends'				=> [],
			'status'				=> [],
			'value_type'			=> ['template' => 1],
			'trapper_hosts'			=> [],
			'units'					=> ['template' => 1],
			'snmpv3_contextname'	=> [],
			'snmpv3_securityname'	=> [],
			'snmpv3_securitylevel'	=> [],
			'snmpv3_authprotocol'	=> [],
			'snmpv3_authpassphrase'	=> [],
			'snmpv3_privprotocol'	=> [],
			'snmpv3_privpassphrase'	=> [],
			'formula'				=> ['template' => 1],
			'error'					=> ['system' => 1],
			'lastlogsize'			=> ['system' => 1],
			'logtimefmt'			=> [],
			'templateid'			=> ['system' => 1],
			'valuemapid'			=> ['template' => 1],
			'params'				=> [],
			'ipmi_sensor'			=> ['template' => 1],
			'authtype'				=> [],
			'username'				=> [],
			'password'				=> [],
			'publickey'				=> [],
			'privatekey'			=> [],
			'mtime'					=> ['system' => 1],
			'flags'					=> [],
			'filter'				=> [],
			'interfaceid'			=> ['host' => 1],
			'port'					=> [],
			'inventory_link'		=> [],
			'lifetime'				=> [],
			'preprocessing'			=> ['template' => 1],
			'jmx_endpoint'			=> [],
			'url'					=> ['template' => 1],
			'timeout'				=> ['template' => 1],
			'query_fields'			=> ['template' => 1],
			'posts'					=> ['template' => 1],
			'status_codes'			=> ['template' => 1],
			'follow_redirects'		=> ['template' => 1],
			'post_type'				=> ['template' => 1],
			'http_proxy'			=> ['template' => 1],
			'headers'				=> ['template' => 1],
			'retrieve_mode'			=> ['template' => 1],
			'request_method'		=> ['template' => 1],
			'output_format'			=> ['template' => 1],
			'allow_traps'			=> [],
			'ssl_cert_file'			=> ['template' => 1],
			'ssl_key_file'			=> ['template' => 1],
			'ssl_key_password'		=> ['template' => 1],
			'verify_peer'			=> ['template' => 1],
			'verify_host'			=> ['template' => 1]
		];

		$this->errorMessages = array_merge($this->errorMessages, [
			self::ERROR_NO_INTERFACE => _('Cannot find host interface on "%1$s" for item key "%2$s".')
		]);
	}

	/**
	 * Check items data.
	 *
	 * Any system field passed to the function will be unset.
	 *
	 * @throw APIException
	 *
	 * @param array $items passed by reference
	 * @param bool  $update
	 */
	protected function checkInput(array &$items, $update = false) {
		if ($update) {
			$itemDbFields = ['itemid' => null];

			$dbItemsFields = ['itemid', 'templateid'];
			foreach ($this->fieldRules as $field => $rule) {
				if (!isset($rule['system'])) {
					$dbItemsFields[] = $field;
				}
			}

			$dbItems = $this->get([
				'output' => $dbItemsFields,
				'itemids' => zbx_objectValues($items, 'itemid'),
				'editable' => true,
				'preservekeys' => true
			]);

			$dbHosts = API::Host()->get([
				'output' => ['hostid', 'status', 'name'],
				'hostids' => zbx_objectValues($dbItems, 'hostid'),
				'templated_hosts' => true,
				'editable' => true,
				'selectApplications' => ['applicationid', 'flags'],
				'preservekeys' => true
			]);
		}
		else {
			$itemDbFields = [
				'name' => null,
				'key_' => null,
				'hostid' => null,
				'type' => null,
				'value_type' => null,
				'delay' => null
			];

			$dbHosts = API::Host()->get([
				'output' => ['hostid', 'status', 'name'],
				'hostids' => zbx_objectValues($items, 'hostid'),
				'templated_hosts' => true,
				'editable' => true,
				'selectApplications' => ['applicationid', 'flags'],
				'preservekeys' => true
			]);

			$discovery_rules = [];

			if ($this instanceof CItemPrototype) {
				$itemDbFields['ruleid'] = null;
				$druleids = zbx_objectValues($items, 'ruleid');

				if ($druleids) {
					$discovery_rules = API::DiscoveryRule()->get([
						'output' => ['hostid'],
						'itemids' => $druleids,
						'preservekeys' => true
					]);
				}
			}
		}

		// interfaces
		$interfaces = API::HostInterface()->get([
			'output' => ['interfaceid', 'hostid', 'type'],
			'hostids' => zbx_objectValues($dbHosts, 'hostid'),
			'nopermissions' => true,
			'preservekeys' => true
		]);

		if ($update) {
			$updateDiscoveredValidator = new CUpdateDiscoveredValidator([
				'allowed' => ['itemid', 'status'],
				'messageAllowedField' => _('Cannot update "%2$s" for a discovered item "%1$s".')
			]);
			foreach ($items as &$item) {
				// check permissions
				if (!array_key_exists($item['itemid'], $dbItems)) {
					self::exception(ZBX_API_ERROR_PERMISSIONS,
						_('No permissions to referred object or it does not exist!')
					);
				}

				$dbItem = $dbItems[$item['itemid']];

				if (array_key_exists('hostid', $item) && bccomp($dbItem['hostid'], $item['hostid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'hostid', _('cannot be changed'))
					);
				}

				$itemName = array_key_exists('name', $item) ? $item['name'] : $dbItem['name'];

				// discovered fields, except status, cannot be updated
				$updateDiscoveredValidator->setObjectName($itemName);
				$this->checkPartialValidator($item, $updateDiscoveredValidator, $dbItem);

				$item += [
					'hostid' => $dbItem['hostid'],
					'type' => $dbItem['type'],
					'name' => $dbItem['name'],
					'key_' => $dbItem['key_'],
					'flags' => $dbItem['flags']
				];
			}
			unset($item);
		}

		$item_key_parser = new CItemKey();
		$ip_range_parser = new CIPRangeParser([
			'v6' => ZBX_HAVE_IPV6,
			'ranges' => false,
			'usermacros' => true,
			'macros' => [
				'{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}'
			]
		]);
		$update_interval_parser = new CUpdateIntervalParser([
			'usermacros' => true,
			'lldmacros' => (get_class($this) === 'CItemPrototype')
		]);

		foreach ($items as $inum => &$item) {
			$item = $this->clearValues($item);

			$fullItem = $items[$inum];

			if (!check_db_fields($itemDbFields, $item)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			if ($update) {
				$type = array_key_exists('type', $item) ? $item['type'] : $dbItems[$item['itemid']]['type'];

				if ($type == ITEM_TYPE_HTTPAGENT) {
					$this->validateHTTPCheck($fullItem, $dbItems[$item['itemid']]);
				}

				check_db_fields($dbItems[$item['itemid']], $fullItem);

				$this->checkNoParameters(
					$item,
					['templateid', 'state', 'lastlogsize', 'mtime', 'error'],
					_('Cannot update "%1$s" for item "%2$s".'),
					$item['name']
				);

				// apply rules
				foreach ($this->fieldRules as $field => $rules) {
					if ((0 != $fullItem['templateid'] && isset($rules['template'])) || isset($rules['system'])) {
						unset($item[$field]);

						// For templated item and fields that should not be modified, use the value from DB.
						if (array_key_exists($field, $dbItems[$item['itemid']])
								&& array_key_exists($field, $fullItem)) {
							$fullItem[$field] = $dbItems[$item['itemid']][$field];
						}
					}
				}

				if (!isset($item['key_'])) {
					$item['key_'] = $fullItem['key_'];
				}
				if (!isset($item['hostid'])) {
					$item['hostid'] = $fullItem['hostid'];
				}

				// if a templated item is being assigned to an interface with a different type, ignore it
				$itemInterfaceType = itemTypeInterface($dbItems[$item['itemid']]['type']);
				if ($fullItem['templateid'] && isset($item['interfaceid']) && isset($interfaces[$item['interfaceid']])
						&& $itemInterfaceType !== INTERFACE_TYPE_ANY && $interfaces[$item['interfaceid']]['type'] != $itemInterfaceType) {

					unset($item['interfaceid']);
				}
			}
			else {
				if ($fullItem['type'] == ITEM_TYPE_HTTPAGENT) {
					$this->validateHTTPCheck($fullItem, []);
				}

				if (!isset($dbHosts[$item['hostid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}

				check_db_fields($itemDbFields, $fullItem);

				$this->checkNoParameters(
					$item,
					['templateid', 'state'],
					_('Cannot set "%1$s" for item "%2$s".'),
					$item['name']
				);

				if ($this instanceof CItemPrototype && (!array_key_exists($fullItem['ruleid'], $discovery_rules)
						|| $discovery_rules[$fullItem['ruleid']]['hostid'] != $fullItem['hostid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('No permissions to referred object or it does not exist!')
					);
				}
			}

			$host = $dbHosts[$fullItem['hostid']];

			// Validate update interval.
			if (!in_array($fullItem['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT])) {
				if ($update_interval_parser->parse($fullItem['delay']) != CParser::PARSE_SUCCESS) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'delay', _('invalid delay'))
					);
				}

				$delay = $update_interval_parser->getDelay();

				// Check if not macros. If delay is a macro, skip this step, otherwise check if delay is valid.
				if ($delay[0] !== '{') {
					$delay_sec = timeUnitToSeconds($delay);
					$intervals = $update_interval_parser->getIntervals();
					$flexible_intervals = $update_interval_parser->getIntervals(ITEM_DELAY_FLEXIBLE);
					$has_scheduling_intervals = (bool) $update_interval_parser->getIntervals(ITEM_DELAY_SCHEDULING);
					$has_macros = false;

					foreach ($intervals as $interval) {
						if (strpos($interval['interval'], '{') !== false) {
							$has_macros = true;
							break;
						}
					}

					// If delay is 0, there must be at least one either flexible or scheduling interval.
					if ($delay_sec == 0 && !$intervals) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Item will not be refreshed. Specified update interval requires having at least one either flexible or scheduling interval.')
						);
					}
					elseif ($delay_sec < 0 || $delay_sec > SEC_PER_DAY) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Item will not be refreshed. Update interval should be between 1s and 1d. Also Scheduled/Flexible intervals can be used.')
						);
					}

					if ($fullItem['type'] == ITEM_TYPE_ZABBIX_ACTIVE) {
						// Remove flexible and scheduling intervals and leave only the delay part.
						$item['delay'] = $delay;
					}
					// If there are scheduling intervals or intervals with macros, skip the next check calculation.
					elseif (!$has_macros && !$has_scheduling_intervals && $flexible_intervals
							&& calculateItemNextCheck(0, $delay_sec, $flexible_intervals, time()) == ZBX_JAN_2038) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Item will not be refreshed. Please enter a correct update interval.')
						);
					}
				}
				elseif ($fullItem['type'] == ITEM_TYPE_ZABBIX_ACTIVE) {
					// Remove flexible and scheduling intervals and leave only the delay part.
					$item['delay'] = $delay;
				}
			}

			// For non-numeric types, whichever value was entered in trends field, is overwritten to zero.
			if ($fullItem['value_type'] == ITEM_VALUE_TYPE_STR || $fullItem['value_type'] == ITEM_VALUE_TYPE_LOG
					|| $fullItem['value_type'] == ITEM_VALUE_TYPE_TEXT) {
				$item['trends'] = '0';
			}

			// check if the item requires an interface
			if ($host['status'] == HOST_STATUS_TEMPLATE) {
				unset($item['interfaceid']);
			}
			else {
				$itemInterfaceType = itemTypeInterface($fullItem['type']);

				if ($itemInterfaceType !== false) {
					if (!array_key_exists('interfaceid', $fullItem) || !$fullItem['interfaceid']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No interface found.'));
					}
					elseif (!isset($interfaces[$fullItem['interfaceid']]) || bccomp($interfaces[$fullItem['interfaceid']]['hostid'], $fullItem['hostid']) != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Item uses host interface from non-parent host.'));
					}
					elseif ($itemInterfaceType !== INTERFACE_TYPE_ANY && $interfaces[$fullItem['interfaceid']]['type'] != $itemInterfaceType) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Item uses incorrect interface type.'));
					}
				}
				// no interface required, just set it to null
				else {
					$item['interfaceid'] = 0;
				}
			}

			// item key
			if ($fullItem['type'] == ITEM_TYPE_DB_MONITOR) {
				if (!isset($fullItem['flags']) || $fullItem['flags'] != ZBX_FLAG_DISCOVERY_RULE) {
					if (strcmp($fullItem['key_'], ZBX_DEFAULT_KEY_DB_MONITOR) == 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Check the key, please. Default example was passed.')
						);
					}
				}
				elseif ($fullItem['flags'] == ZBX_FLAG_DISCOVERY_RULE) {
					if (strcmp($fullItem['key_'], ZBX_DEFAULT_KEY_DB_MONITOR_DISCOVERY) == 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('Check the key, please. Default example was passed.')
						);
					}
				}
			}
			elseif (($fullItem['type'] == ITEM_TYPE_SSH && strcmp($fullItem['key_'], ZBX_DEFAULT_KEY_SSH) == 0)
					|| ($fullItem['type'] == ITEM_TYPE_TELNET && strcmp($fullItem['key_'], ZBX_DEFAULT_KEY_TELNET) == 0)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Check the key, please. Default example was passed.'));
			}

			// key
			if ($item_key_parser->parse($fullItem['key_']) != CParser::PARSE_SUCCESS) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_params($this->getErrorMsg(self::ERROR_INVALID_KEY), [
						$fullItem['key_'], $fullItem['name'], $host['name'], $item_key_parser->getError()
					])
				);
			}

			// parameters
			if ($fullItem['type'] == ITEM_TYPE_AGGREGATE) {
				$params_num = $item_key_parser->getParamsNum();

				if (!str_in_array($item_key_parser->getKey(), ['grpmax', 'grpmin', 'grpsum', 'grpavg'])
						|| $params_num > 4 || $params_num < 3
						|| ($params_num == 3 && $item_key_parser->getParam(2) !== 'last')
						|| !str_in_array($item_key_parser->getParam(2), ['last', 'min', 'max', 'avg', 'sum', 'count'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Key "%1$s" does not match <grpmax|grpmin|grpsum|grpavg>["Host group(s)", "Item key",'.
							' "<last|min|max|avg|sum|count>", "parameter"].', $item_key_parser->getKey()));
				}
			}

			// type of information
			if ($fullItem['type'] == ITEM_TYPE_AGGREGATE && $fullItem['value_type'] != ITEM_VALUE_TYPE_UINT64
					&& $fullItem['value_type'] != ITEM_VALUE_TYPE_FLOAT) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('Type of information must be "Numeric (unsigned)" or "Numeric (float)" for aggregate items.'));
			}

			if (($fullItem['type'] == ITEM_TYPE_TRAPPER || $fullItem['type'] == ITEM_TYPE_HTTPAGENT)
					&& array_key_exists('trapper_hosts', $fullItem) && $fullItem['trapper_hosts'] !== ''
					&& !$ip_range_parser->parse($fullItem['trapper_hosts'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'trapper_hosts', $ip_range_parser->getError())
				);
			}

			// jmx
			if ($fullItem['type'] == ITEM_TYPE_JMX) {
				if (!array_key_exists('jmx_endpoint', $fullItem) && !$update) {
					$item['jmx_endpoint'] = ZBX_DEFAULT_JMX_ENDPOINT;
				}
				if (array_key_exists('jmx_endpoint', $fullItem) && $fullItem['jmx_endpoint'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'jmx_endpoint', _('cannot be empty'))
					);
				}

				if (($fullItem['username'] === '') !== ($fullItem['password'] === '')) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'username',
								_('both username and password should be either present or empty'))
					);
				}
			}
			else {
				if (array_key_exists('jmx_endpoint', $item) && $item['jmx_endpoint'] !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'jmx_endpoint', _('should be empty'))
					);
				}
				elseif (array_key_exists('jmx_endpoint', $fullItem) && $fullItem['jmx_endpoint'] !== '') {
					$item['jmx_endpoint'] = '';
				}
			}

			// Dependent item.
			if ($fullItem['type'] == ITEM_TYPE_DEPENDENT) {
				if ($update) {
					if (array_key_exists('master_itemid', $item) && !$item['master_itemid']) {
						self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect value for field "%1$s": %2$s.',
							'master_itemid', _('cannot be empty')
						));
					}
					if ($dbItems[$fullItem['itemid']]['type'] != ITEM_TYPE_DEPENDENT
							&& !array_key_exists('master_itemid', $item)) {
						self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect value for field "%1$s": %2$s.',
							'master_itemid', _('cannot be empty')
						));
					}
				}
				elseif (!array_key_exists('master_itemid', $item) || !$item['master_itemid']) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect value for field "%1$s": %2$s.',
						'master_itemid', _('cannot be empty')
					));
				}
				if (array_key_exists('master_itemid', $item) && !is_int($item['master_itemid'])
						&& !(is_string($item['master_itemid']) && ctype_digit($item['master_itemid']))) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value "%1$s" for "%2$s" field.',
						$item['master_itemid'], 'master_itemid'
					));
				}
			}
			else {
				if (array_key_exists('master_itemid', $item) && $item['master_itemid']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'master_itemid', _('should be empty')
					));
				}
				$item['master_itemid'] = 0;
			}

			// ssh, telnet
			if ($fullItem['type'] == ITEM_TYPE_SSH || $fullItem['type'] == ITEM_TYPE_TELNET) {
				if (zbx_empty($fullItem['username'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No authentication user name specified.'));
				}

				if ($fullItem['type'] == ITEM_TYPE_SSH && $fullItem['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
					if (zbx_empty($fullItem['publickey'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No public key file specified.'));
					}
					if (zbx_empty($fullItem['privatekey'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('No private key file specified.'));
					}
				}
			}

			// snmp trap
			if ($fullItem['type'] == ITEM_TYPE_SNMPTRAP
					&& $fullItem['key_'] !== 'snmptrap.fallback' && $item_key_parser->getKey() !== 'snmptrap') {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('SNMP trap key is invalid.'));
			}

			// snmp oid
			if ((in_array($fullItem['type'], [ITEM_TYPE_SNMPV1, ITEM_TYPE_SNMPV2C, ITEM_TYPE_SNMPV3]))
					&& zbx_empty($fullItem['snmp_oid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No SNMP OID specified.'));
			}

			// snmp community
			if (in_array($fullItem['type'], [ITEM_TYPE_SNMPV1, ITEM_TYPE_SNMPV2C])
					&& zbx_empty($fullItem['snmp_community'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No SNMP community specified.'));
			}

			// snmp port
			if (isset($fullItem['port']) && !zbx_empty($fullItem['port']) && !validatePortNumberOrMacro($fullItem['port'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Item "%1$s:%2$s" has invalid port: "%3$s".', $fullItem['name'], $fullItem['key_'], $fullItem['port']));
			}

			if (isset($fullItem['snmpv3_securitylevel']) && $fullItem['snmpv3_securitylevel'] != ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV) {
				// snmpv3 authprotocol
				if (str_in_array($fullItem['snmpv3_securitylevel'], [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV])) {
					if (isset($fullItem['snmpv3_authprotocol']) && (zbx_empty($fullItem['snmpv3_authprotocol'])
							|| !str_in_array($fullItem['snmpv3_authprotocol'],
								[ITEM_AUTHPROTOCOL_MD5, ITEM_AUTHPROTOCOL_SHA]))) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect authentication protocol for item "%1$s".', $fullItem['name']));
					}
				}

				// snmpv3 privprotocol
				if ($fullItem['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
					if (isset($fullItem['snmpv3_privprotocol']) && (zbx_empty($fullItem['snmpv3_privprotocol'])
							|| !str_in_array($fullItem['snmpv3_privprotocol'],
								[ITEM_PRIVPROTOCOL_DES, ITEM_PRIVPROTOCOL_AES]))) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect privacy protocol for item "%1$s".', $fullItem['name']));
					}
				}
			}

			if (isset($item['applications']) && $item['applications']) {
				/*
				 * 'flags' is available for update and item prototypes.
				 * Don't allow discovered or any other application types for item prototypes in 'applications' option.
				 */
				if (array_key_exists('flags', $fullItem) && $fullItem['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
					foreach ($host['applications'] as $num => $application) {
						if ($application['flags'] != ZBX_FLAG_DISCOVERY_NORMAL) {
							unset($host['applications'][$num]);
						}
					}
				}

				// check that the given applications belong to the item's host
				$dbApplicationIds = zbx_objectValues($host['applications'], 'applicationid');
				foreach ($item['applications'] as $appId) {
					if (!in_array($appId, $dbApplicationIds)) {
						$error = _s('Application with ID "%1$s" is not available on "%2$s".', $appId, $host['name']);
						self::exception(ZBX_API_ERROR_PARAMETERS, $error);
					}
				}
			}

			$this->checkSpecificFields($fullItem, $update ? 'update' : 'create');

			$this->validateItemPreprocessing($fullItem);
		}
		unset($item);

		$this->checkExistingItems($items);
	}

	/**
	 * Check item specific fields. Each API like Item, Itemprototype and Discovery rule may inherit different fields
	 * to validate.
	 *
	 * @param array  $item    An array of single item data.
	 * @param string $method  A string of "create" or "update" method.
	 *
	 * @return bool
	 */
	abstract protected function checkSpecificFields(array $item, $method);

	protected function clearValues(array $item) {
		if (isset($item['port']) && $item['port'] != '') {
			$item['port'] = ltrim($item['port'], '0');
			if ($item['port'] == '') {
				$item['port'] = 0;
			}
		}

		if (array_key_exists('type', $item) &&
				($item['type'] == ITEM_TYPE_DEPENDENT || $item['type'] == ITEM_TYPE_TRAPPER)) {
			$item['delay'] = 0;
		}

		return $item;
	}

	protected function errorInheritFlags($flag, $key, $host) {
		switch ($flag) {
			case ZBX_FLAG_DISCOVERY_NORMAL:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as an item.', $key, $host));
				break;
			case ZBX_FLAG_DISCOVERY_RULE:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as a discovery rule.', $key, $host));
				break;
			case ZBX_FLAG_DISCOVERY_PROTOTYPE:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as an item prototype.', $key, $host));
				break;
			case ZBX_FLAG_DISCOVERY_CREATED:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as an item created from item prototype.', $key, $host));
				break;
			default:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on "%2$s" as unknown item element.', $key, $host));
		}
	}

	/**
	 * Returns the interface that best matches the given item.
	 *
	 * @param array $item_type  An item type
	 * @param array $interfaces An array of interfaces to choose from
	 *
	 * @return array|boolean    The best matching interface;
	 *							an empty array of no matching interface was found;
	 *							false, if the item does not need an interface
	 */
	public static function findInterfaceForItem($item_type, array $interfaces) {
		$interface_by_type = [];
		foreach ($interfaces as $interface) {
			if ($interface['main'] == 1) {
				$interface_by_type[$interface['type']] = $interface;
			}
		}

		// find item interface type
		$type = itemTypeInterface($item_type);

		// the item can use any interface
		if ($type == INTERFACE_TYPE_ANY) {
			$interface_types = [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI];
			foreach ($interface_types as $interface_type) {
				if (array_key_exists($interface_type, $interface_by_type)) {
					return $interface_by_type[$interface_type];
				}
			}
		}
		// the item uses a specific type of interface
		elseif ($type !== false) {
			return array_key_exists($type, $interface_by_type) ? $interface_by_type[$type] : [];
		}
		// the item does not need an interface
		else {
			return false;
		}
	}

	/**
	 * Updates the children of the item on the given hosts and propagates the inheritance to the child hosts.
	 *
	 * @param array      $tpl_items  An array of items to inherit.
	 * @param array|null $hostids    An array of hosts to inherit to; if set to null, the items will be inherited to all
	 *                               linked hosts or templates.
	 */
	protected function inherit(array $tpl_items, array $hostids = null) {
		$tpl_items = zbx_toHash($tpl_items, 'itemid');

		// Inherit starting from common items and finishing up dependent.
		while ($tpl_items) {
			$_tpl_items = [];

			foreach ($tpl_items as $tpl_item) {
				if ($tpl_item['type'] != ITEM_TYPE_DEPENDENT
						|| !array_key_exists($tpl_item['master_itemid'], $tpl_items)) {
					$_tpl_items[$tpl_item['itemid']] = $tpl_item;
				}
			}

			foreach ($_tpl_items as $itemid => $_tpl_item) {
				unset($tpl_items[$itemid]);
			}

			$this->_inherit($_tpl_items, $hostids);
		}
	}

	/**
	 * Auxiliary method for item inheritance. See full description in inherit() method.
	 */
	private function _inherit(array $tpl_items, array $hostids = null) {
		// Prepare the child items.
		$new_items = $this->prepareInheritedItems($tpl_items, $hostids);
		if (!$new_items) {
			return;
		}

		$ins_items = [];
		$upd_items = [];

		foreach ($new_items as $new_item) {
			if (array_key_exists('itemid', $new_item)) {
				if ($this instanceof CItemPrototype) {
					unset($new_item['ruleid']);
				}
				$upd_items[] = $new_item;
			}
			else {
				$ins_items[] = $new_item;
			}
		}

		$this->validateDependentItems($new_items);

		// Save the new items.
		if ($ins_items) {
			if ($this instanceof CItem) {
				static::validateInventoryLinks($ins_items, false);
			}

			$this->createReal($ins_items);
		}

		if ($upd_items) {
			if ($this instanceof CItem) {
				static::validateInventoryLinks($upd_items, true);
			}

			$this->updateReal($upd_items);
		}

		$new_items = array_merge($upd_items, $ins_items);

		// Inheriting items from the templates.
		$db_items = DBselect(
			'SELECT i.itemid'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionInt('i.itemid', zbx_objectValues($new_items, 'itemid')).
				' AND '.dbConditionInt('h.status', [HOST_STATUS_TEMPLATE])
		);

		$tpl_itemids = [];
		while ($db_item = DBfetch($db_items)) {
			$tpl_itemids[$db_item['itemid']] = true;
		}

		foreach ($new_items as $index => $new_item) {
			if (!array_key_exists($new_item['itemid'], $tpl_itemids)) {
				unset($new_items[$index]);
			}
		}

		$this->inherit($new_items);
	}

	/**
	 * Prepares and returns an array of child items, inherited from items $tpl_items on the given hosts.
	 *
	 * @param array      $tpl_items
	 * @param string     $tpl_items[<itemid>]['itemid']
	 * @param string     $tpl_items[<itemid>]['hostid']
	 * @param string     $tpl_items[<itemid>]['key_']
	 * @param int        $tpl_items[<itemid>]['type']
	 * @param array      $tpl_items[<itemid>]['applicationPrototypes']            (optional) Suitable for item
	 *                                                                            prototypes.
	 * @param string     $tpl_items[<itemid>]['applicationPrototypes'][]['name']
	 * @param array      $tpl_items[<itemid>]['applications']                     (optional) Array of applicationids.
	 * @param array      $tpl_items[<itemid>]['preprocessing']                    (optional)
	 * @param int        $tpl_items[<itemid>]['preprocessing'][]['type']
	 * @param string     $tpl_items[<itemid>]['preprocessing'][]['params']
	 * @param int        $tpl_items[<itemid>]['flags']
	 * @param string     $tpl_items[<itemid>]['master_itemid']                    (optional)
	 * @param mixed      $tpl_items[<itemid>][<field_name>]                       (optional)
	 * @param array|null $hostids
	 *
	 * @return array an array of unsaved child items
	 */
	private function prepareInheritedItems(array $tpl_items, array $hostids = null) {
		$itemids_by_templateid = [];
		foreach ($tpl_items as $tpl_item) {
			$itemids_by_templateid[$tpl_item['hostid']][] = $tpl_item['itemid'];
		}

		// Fetch all child hosts.
		$chd_hosts = API::Host()->get([
			'output' => ['hostid', 'host', 'status'],
			'selectParentTemplates' => ['templateid'],
			'selectInterfaces' => ['interfaceid', 'main', 'type'],
			'templateids' => array_keys($itemids_by_templateid),
			'hostids' => $hostids,
			'preservekeys' => true,
			'nopermissions' => true,
			'templated_hosts' => true
		]);
		if (!$chd_hosts) {
			return [];
		}

		$chd_items_tpl = [];
		$chd_items_key = [];

		// Preparing list of items by item templateid.
		$sql = 'SELECT i.itemid,i.hostid,i.type,i.key_,i.flags,i.templateid'.
			' FROM items i'.
			' WHERE '.dbConditionInt('i.templateid', zbx_objectValues($tpl_items, 'itemid'));
		if ($hostids !== null) {
			$sql .= ' AND '.dbConditionInt('i.hostid', $hostids);
		}
		$db_items = DBselect($sql);

		while ($db_item = DBfetch($db_items)) {
			$hostid = $db_item['hostid'];
			unset($db_item['hostid']);

			$chd_items_tpl[$hostid][$db_item['templateid']] = $db_item;
		}

		$hostids_by_key = [];

		// Preparing list of items by item key.
		foreach ($chd_hosts as $chd_host) {
			$tpl_itemids = [];

			foreach ($chd_host['parentTemplates'] as $parent_template) {
				if (array_key_exists($parent_template['templateid'], $itemids_by_templateid)) {
					$tpl_itemids = array_merge($tpl_itemids, $itemids_by_templateid[$parent_template['templateid']]);
				}
			}

			foreach ($tpl_itemids as $tpl_itemid) {
				if (!array_key_exists($chd_host['hostid'], $chd_items_tpl)
						|| !array_key_exists($tpl_itemid, $chd_items_tpl[$chd_host['hostid']])) {
					$hostids_by_key[$tpl_items[$tpl_itemid]['key_']][] = $chd_host['hostid'];
				}
			}
		}

		foreach ($hostids_by_key as $key_ => $key_hostids) {
			$sql_select = ($this instanceof CItemPrototype) ? ',id.parent_itemid AS ruleid' : '';
			// "LEFT JOIN" is needed to check flags on inherited and existing item, item prototype or lld rule.
			// For example, when linking an item prototype with same key as in an item on target host or template.
			$sql_join = ($this instanceof CItemPrototype) ? ' LEFT JOIN item_discovery id ON i.itemid=id.itemid' : '';
			$db_items = DBselect(
				'SELECT i.itemid,i.hostid,i.type,i.key_,i.flags,i.templateid'.$sql_select.
					' FROM items i'.$sql_join.
					' WHERE '.dbConditionInt('i.hostid', $key_hostids).
						' AND '.dbConditionString('i.key_', [$key_])
			);

			while ($db_item = DBfetch($db_items)) {
				$hostid = $db_item['hostid'];
				unset($db_item['hostid']);

				$chd_items_key[$hostid][$db_item['key_']] = $db_item;
			}
		}

		// Preparing list of application prototypes.
		if ($this instanceof CItemPrototype) {
			$tpl_app_prototypes = [];
			$item_prototypeids = [];

			foreach ($tpl_items as $tpl_item) {
				if (array_key_exists('applicationPrototypes', $tpl_item) && $tpl_item['applicationPrototypes']) {
					$item_prototypeids[] = $tpl_item['itemid'];
				}
			}

			if ($item_prototypeids) {
				$db_tpl_app_prototypes = DBselect(
					'SELECT iap.itemid,iap.application_prototypeid,ap.name'.
					' FROM item_application_prototype iap,application_prototype ap'.
					' WHERE iap.application_prototypeid=ap.application_prototypeid'.
						' AND '.dbConditionInt('iap.itemid', $item_prototypeids)
				);

				while ($db_tpl_app_prototype = DBfetch($db_tpl_app_prototypes)) {
					$tpl_app_prototypes[$db_tpl_app_prototype['itemid']][$db_tpl_app_prototype['name']] =
						$db_tpl_app_prototype['application_prototypeid'];
				}
			}
		}

		// List of the discovery rules.
		if ($this instanceof CItemPrototype) {
			// List of itemids without 'ruleid' property.
			$tpl_itemids = [];
			$tpl_ruleids = [];
			foreach ($tpl_items as $tpl_item) {
				if (!array_key_exists('ruleid', $tpl_item)) {
					$tpl_itemids[] = $tpl_item['itemid'];
				}
				else {
					$tpl_ruleids[$tpl_item['ruleid']] = true;
				}
			}

			if ($tpl_itemids) {
				$db_rules = DBselect(
					'SELECT id.parent_itemid,id.itemid'.
						' FROM item_discovery id'.
						' WHERE '.dbConditionInt('id.itemid', $tpl_itemids)
				);

				while ($db_rule = DBfetch($db_rules)) {
					$tpl_items[$db_rule['itemid']]['ruleid'] = $db_rule['parent_itemid'];
					$tpl_ruleids[$db_rule['parent_itemid']] = true;
				}
			}

			$sql = 'SELECT i.hostid,i.templateid,i.itemid'.
					' FROM items i'.
					' WHERE '.dbConditionInt('i.templateid', array_keys($tpl_ruleids));
			if ($hostids !== null) {
				$sql .= ' AND '.dbConditionInt('i.hostid', $hostids);
			}
			$db_rules = DBselect($sql);

			// List of child lld ruleids by child hostid and parent lld ruleid.
			$chd_ruleids = [];
			while ($db_rule = DBfetch($db_rules)) {
				$chd_ruleids[$db_rule['hostid']][$db_rule['templateid']] = $db_rule['itemid'];
			}
		}

		$new_items = [];
		// List of the updated item keys by hostid.
		$upd_hostids_by_key = [];

		foreach ($chd_hosts as $chd_host) {
			$tpl_itemids = [];

			foreach ($chd_host['parentTemplates'] as $parent_template) {
				if (array_key_exists($parent_template['templateid'], $itemids_by_templateid)) {
					$tpl_itemids = array_merge($tpl_itemids, $itemids_by_templateid[$parent_template['templateid']]);
				}
			}

			foreach ($tpl_itemids as $tpl_itemid) {
				$tpl_item = $tpl_items[$tpl_itemid];

				$chd_item = null;

				// Update by templateid.
				if (array_key_exists($chd_host['hostid'], $chd_items_tpl)
						&& array_key_exists($tpl_item['itemid'], $chd_items_tpl[$chd_host['hostid']])) {
					$chd_item = $chd_items_tpl[$chd_host['hostid']][$tpl_item['itemid']];

					if ($tpl_item['key_'] !== $chd_item['key_']) {
						$upd_hostids_by_key[$tpl_item['key_']][] = $chd_host['hostid'];
					}
				}
				// Update by key.
				elseif (array_key_exists($chd_host['hostid'], $chd_items_key)
						&& array_key_exists($tpl_item['key_'], $chd_items_key[$chd_host['hostid']])) {
					$chd_item = $chd_items_key[$chd_host['hostid']][$tpl_item['key_']];

					// Check if an item of a different type with the same key exists.
					if ($tpl_item['flags'] != $chd_item['flags']) {
						$this->errorInheritFlags($chd_item['flags'], $chd_item['key_'], $chd_host['host']);
					}

					// Check if item already linked to another template.
					if ($chd_item['templateid'] != 0 && bccomp($chd_item['templateid'], $tpl_item['itemid']) != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _params(
							$this->getErrorMsg(self::ERROR_EXISTS_TEMPLATE), [$tpl_item['key_'], $chd_host['host']]
						));
					}

					if ($this instanceof CItemPrototype) {
						$chd_ruleid = $chd_ruleids[$chd_host['hostid']][$tpl_item['ruleid']];
						if (bccomp($chd_item['ruleid'], $chd_ruleid) != 0) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Item prototype "%1$s" already exists on "%2$s", linked to another rule.',
									$chd_item['key_'], $chd_host['host']
								)
							);
						}
					}
				}

				// copying item
				$new_item = $tpl_item;
				if ($chd_item !== null) {
					$new_item['itemid'] = $chd_item['itemid'];
				}
				else {
					unset($new_item['itemid']);
					if ($this instanceof CItemPrototype) {
						$new_item['ruleid'] = $chd_ruleids[$chd_host['hostid']][$tpl_item['ruleid']];
					}
				}
				$new_item['hostid'] = $chd_host['hostid'];
				$new_item['templateid'] = $tpl_item['itemid'];

				if ($chd_host['status'] != HOST_STATUS_TEMPLATE) {
					if ($chd_item === null || $new_item['type'] != $chd_item['type']) {
						$interface = self::findInterfaceForItem($new_item['type'], $chd_host['interfaces']);

						if ($interface) {
							$new_item['interfaceid'] = $interface['interfaceid'];
						}
						elseif ($interface !== false) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _params(
								$this->getErrorMsg(self::ERROR_NO_INTERFACE), [$chd_host['host'], $new_item['key_']]
							));
						}
					}

					if ($this instanceof CItem || $this instanceof CDiscoveryRule) {
						if (!array_key_exists('itemid', $new_item)) {
							$new_item['rtdata'] = true;
						}
					}
				}

				if (array_key_exists('preprocessing', $new_item)) {
					foreach ($new_item['preprocessing'] as $preprocessing) {
						if ($chd_item) {
							$preprocessing['itemid'] = $chd_item['itemid'];
						}
						else {
							unset($preprocessing['itemid']);
						}
					}
				}

				if ($this instanceof CItemPrototype && array_key_exists('applicationPrototypes', $new_item)) {
					foreach ($new_item['applicationPrototypes'] as &$application_prototype) {
						$application_prototype['templateid'] =
							$tpl_app_prototypes[$tpl_item['itemid']][$application_prototype['name']];
					}
					unset($application_prototype);
				}

				$new_items[] = $new_item;
			}
		}

		// Check if item with a new key already exists on the child host.
		if ($upd_hostids_by_key) {
			$sql_where = [];
			foreach ($upd_hostids_by_key as $key => $hostids) {
				$sql_where[] = dbConditionInt('i.hostid', $hostids).' AND i.key_='.zbx_dbstr($key);
			}

			$sql = 'SELECT i.hostid,i.key_'.
				' FROM items i'.
				' WHERE ('.implode(') OR (', $sql_where).')';
			$db_items = DBselect($sql, 1);

			if ($db_item = DBfetch($db_items)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _params($this->getErrorMsg(self::ERROR_EXISTS),
					[$db_item['key_'], $chd_hosts[$db_item['hostid']]['host']]
				));
			}
		}

		// Setting item applications.
		if ($this instanceof CItem || $this instanceof CItemPrototype) {
			$tpl_applicationids = [];
			foreach ($tpl_items as $tpl_item) {
				if (array_key_exists('applications', $tpl_item)) {
					foreach ($tpl_item['applications'] as $applicationid) {
						$tpl_applicationids[$applicationid] = true;
					}
				}
			}

			if ($tpl_applicationids) {
				$db_applications = DBselect('SELECT a.hostid,at.templateid,a.applicationid'.
					' FROM application_template at,applications a'.
					' WHERE at.applicationid=a.applicationid'.
						' AND '.dbConditionInt('at.templateid', array_keys($tpl_applicationids)).
						' AND '.dbConditionInt('a.hostid', array_keys($chd_hosts))
				);

				$app_links = [];
				while ($db_application = DBfetch($db_applications)) {
					$app_links[$db_application['hostid']][$db_application['templateid']] =
						$db_application['applicationid'];
				}

				foreach ($new_items as &$new_item) {
					if (array_key_exists('applications', $new_item)) {
						$applicationids = [];
						foreach ($new_item['applications'] as $applicationid) {
							if (array_key_exists($applicationid, $app_links[$new_item['hostid']])) {
								$applicationids[] =  $app_links[$new_item['hostid']][$applicationid];
							}
						}
						$new_item['applications'] = $applicationids;
					}
				}
				unset($new_item);
			}
		}

		return $this->prepareDependentItems($tpl_items, $new_items, $hostids);
	}

	/**
	 * Update relations for inherited dependent items to master items.
	 *
	 * @param array      $tpl_items
	 * @param int        $tpl_items[<itemid>]['type']
	 * @param string     $tpl_items[<itemid>]['master_itemid']
	 * @param array      $new_items
	 * @param string     $new_items[<itemid>]['hostid']
	 * @param int        $new_items[<itemid>]['type']
	 * @param string     $new_items[<itemid>]['templateid']
	 * @param array|null $hostids
	 *
	 * @return array an array of synchronized inherited items.
	 */
	private function prepareDependentItems(array $tpl_items, array $new_items, array $hostids = null) {
		$tpl_master_itemids = [];

		foreach ($tpl_items as $tpl_item) {
			if ($tpl_item['type'] == ITEM_TYPE_DEPENDENT) {
				$tpl_master_itemids[$tpl_item['master_itemid']] = true;
			}
		}

		if ($tpl_master_itemids) {
			$sql = 'SELECT i.itemid,i.hostid,i.templateid'.
				' FROM items i'.
				' WHERE '.dbConditionId('i.templateid', array_keys($tpl_master_itemids));
			if ($hostids !== null) {
				$sql .= ' AND '.dbConditionId('i.hostid', $hostids);
			}
			$db_items = DBselect($sql);

			$master_links = [];

			while ($db_item = DBfetch($db_items)) {
				$master_links[$db_item['templateid']][$db_item['hostid']] = $db_item['itemid'];
			}

			foreach ($new_items as &$new_item) {
				if ($new_item['type'] == ITEM_TYPE_DEPENDENT) {
					$tpl_item = $tpl_items[$new_item['templateid']];

					if (array_key_exists('master_itemid', $tpl_item)) {
						$new_item['master_itemid'] = $master_links[$tpl_item['master_itemid']][$new_item['hostid']];
					}
				}
			}
			unset($new_item);
		}

		return $new_items;
	}

	/**
	 * Validate item pre-processing.
	 *
	 * @param array  $item                                             An array of single item data.
	 * @param array  $item['preprocessing']                            An array of item pre-processing data.
	 * @param string $item['preprocessing'][]['type']                  The preprocessing option type. Possible values:
	 *                                                                  1 - ZBX_PREPROC_MULTIPLIER;
	 *                                                                  2 - ZBX_PREPROC_RTRIM;
	 *                                                                  3 - ZBX_PREPROC_LTRIM;
	 *                                                                  4 - ZBX_PREPROC_TRIM;
	 *                                                                  5 - ZBX_PREPROC_REGSUB;
	 *                                                                  6 - ZBX_PREPROC_BOOL2DEC;
	 *                                                                  7 - ZBX_PREPROC_OCT2DEC;
	 *                                                                  8 - ZBX_PREPROC_HEX2DEC;
	 *                                                                  9 - ZBX_PREPROC_DELTA_VALUE;
	 *                                                                  10 - ZBX_PREPROC_DELTA_SPEED;
	 *                                                                  11 - ZBX_PREPROC_XPATH;
	 *                                                                  12 - ZBX_PREPROC_JSONPATH;
	 *                                                                  13 - ZBX_PREPROC_VALIDATE_RANGE;
	 *                                                                  14 - ZBX_PREPROC_VALIDATE_REGEX;
	 *                                                                  15 - ZBX_PREPROC_VALIDATE_NOT_REGEX;
	 *                                                                  16 - ZBX_PREPROC_ERROR_FIELD_JSON;
	 *                                                                  17 - ZBX_PREPROC_ERROR_FIELD_XML;
	 *                                                                  18 - ZBX_PREPROC_ERROR_FIELD_REGEX;
	 *                                                                  19 - ZBX_PREPROC_THROTTLE_VALUE;
	 *                                                                  20 - ZBX_PREPROC_THROTTLE_TIMED_VALUE;
	 *                                                                  21 - ZBX_PREPROC_SCRIPT;
	 *                                                                  22 - ZBX_PREPROC_PROMETHEUS_PATTERN;
	 *                                                                  23 - ZBX_PREPROC_PROMETHEUS_TO_JSON.
	 * @param string $item['preprocessing'][]['params']                Additional parameters used by preprocessing
	 *                                                                 option. Multiple parameters are separated by LF
	 *                                                                 (\n) character.
	 * @param string $item['preprocessing'][]['error_handler']         Action type used in case of preprocessing step
	 *                                                                 failure. Possible values:
	 *                                                                  0 - ZBX_PREPROC_FAIL_DEFAULT;
	 *                                                                  1 - ZBX_PREPROC_FAIL_DISCARD_VALUE;
	 *                                                                  2 - ZBX_PREPROC_FAIL_SET_VALUE;
	 *                                                                  3 - ZBX_PREPROC_FAIL_SET_ERROR.
	 * @param string $item['preprocessing'][]['error_handler_params']  Error handler parameters.
	 */
	protected function validateItemPreprocessing(array $item) {
		if (array_key_exists('preprocessing', $item)) {
			if (!is_array($item['preprocessing'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			$type_validator = new CLimitedSetValidator(['values' => $this::$supported_preprocessing_types]);

			$error_handler_validator = new CLimitedSetValidator([
				'values' => [ZBX_PREPROC_FAIL_DEFAULT, ZBX_PREPROC_FAIL_DISCARD_VALUE, ZBX_PREPROC_FAIL_SET_VALUE,
					ZBX_PREPROC_FAIL_SET_ERROR
				]
			]);

			$prometheus_pattern_parser = new CPrometheusPatternParser(['usermacros' => true,
				'lldmacros' => ($this instanceof CItemPrototype)
			]);
			$prometheus_output_parser = new CPrometheusOutputParser(['usermacros' => true,
				'lldmacros' => ($this instanceof CItemPrototype)
			]);

			$required_fields = ['type', 'params', 'error_handler', 'error_handler_params'];
			$delta = false;
			$throttling = false;
			$prometheus = false;

			foreach ($item['preprocessing'] as $preprocessing) {
				$missing_keys = array_diff($required_fields, array_keys($preprocessing));

				if ($missing_keys) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Item pre-processing is missing parameters: %1$s', implode(', ', $missing_keys))
					);
				}

				if (is_array($preprocessing['type'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}
				elseif ($preprocessing['type'] === '' || $preprocessing['type'] === null
						|| $preprocessing['type'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'type', _('cannot be empty'))
					);
				}

				if (!$type_validator->validate($preprocessing['type'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'type',
							_s('unexpected value "%1$s"', $preprocessing['type'])
						)
					);
				}

				$preprocessing['params'] = str_replace("\r\n", "\n", $preprocessing['params']);

				switch ($preprocessing['type']) {
					case ZBX_PREPROC_MULTIPLIER:
						// Check if custom multiplier is a valid number.
						$params = $preprocessing['params'];

						if (is_array($params)) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
						}
						elseif ($params === '' || $params === null || $params === false) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect value for field "%1$s": %2$s.', 'params', _('cannot be empty'))
							);
						}

						if (!is_numeric($params)
								&& (new CUserMacroParser())->parse($params) != CParser::PARSE_SUCCESS
								&& (!($this instanceof CItemPrototype)
									|| ((new CLLDMacroFunctionParser())->parse($params) != CParser::PARSE_SUCCESS
										&& (new CLLDMacroParser())->parse($params) != CParser::PARSE_SUCCESS))) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
								'params', _('a numeric value is expected')
							));
						}
						break;

					case ZBX_PREPROC_RTRIM:
					case ZBX_PREPROC_LTRIM:
					case ZBX_PREPROC_TRIM:
					case ZBX_PREPROC_XPATH:
					case ZBX_PREPROC_JSONPATH:
					case ZBX_PREPROC_VALIDATE_REGEX:
					case ZBX_PREPROC_VALIDATE_NOT_REGEX:
					case ZBX_PREPROC_ERROR_FIELD_JSON:
					case ZBX_PREPROC_ERROR_FIELD_XML:
					case ZBX_PREPROC_SCRIPT:
						// Check 'params' if not empty.
						if (is_array($preprocessing['params'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
						}
						elseif ($preprocessing['params'] === '' || $preprocessing['params'] === null
								|| $preprocessing['params'] === false) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect value for field "%1$s": %2$s.', 'params', _('cannot be empty'))
							);
						}
						break;

					case ZBX_PREPROC_REGSUB:
					case ZBX_PREPROC_ERROR_FIELD_REGEX:
						// Check if 'params' are not empty and if second parameter contains (after \n) is not empty.
						if (is_array($preprocessing['params'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
						}
						elseif ($preprocessing['params'] === '' || $preprocessing['params'] === null
								|| $preprocessing['params'] === false) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect value for field "%1$s": %2$s.', 'params', _('cannot be empty'))
							);
						}

						$params = explode("\n", $preprocessing['params']);

						if ($params[0] === '') {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
								'params', _('first parameter is expected')
							));
						}

						if (!array_key_exists(1, $params) || $params[1] === '') {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
								'params', _('second parameter is expected')
							));
						}
						break;

					case ZBX_PREPROC_VALIDATE_RANGE:
						if (is_array($preprocessing['params'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
						}
						elseif (trim($preprocessing['params']) === '' || $preprocessing['params'] === null
								|| $preprocessing['params'] === false) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect value for field "%1$s": %2$s.', 'params', _('cannot be empty'))
							);
						}

						$params = explode("\n", $preprocessing['params']);

						if ($params[0] !== '' && !is_numeric($params[0])
								&& (new CUserMacroParser())->parse($params[0]) != CParser::PARSE_SUCCESS
								&& (!($this instanceof CItemPrototype)
									|| ((new CLLDMacroFunctionParser())->parse($params[0]) != CParser::PARSE_SUCCESS
										&& (new CLLDMacroParser())->parse($params[0]) != CParser::PARSE_SUCCESS))) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
								'params', _('a numeric value is expected')
							));
						}

						if ($params[1] !== '' && !is_numeric($params[1])
								&& (new CUserMacroParser())->parse($params[1]) != CParser::PARSE_SUCCESS
								&& (!($this instanceof CItemPrototype)
									|| ((new CLLDMacroFunctionParser())->parse($params[1]) != CParser::PARSE_SUCCESS
										&& (new CLLDMacroParser())->parse($params[1]) != CParser::PARSE_SUCCESS))) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
								'params', _('a numeric value is expected')
							));
						}

						if (is_numeric($params[0]) && is_numeric($params[1]) && $params[0] > $params[1]) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value for field "%1$s": %2$s.',
								'params',
								_s('"%1$s" value must be less than or equal to "%2$s" value', _('min'), _('max'))
							));
						}
						break;

					case ZBX_PREPROC_BOOL2DEC:
					case ZBX_PREPROC_OCT2DEC:
					case ZBX_PREPROC_HEX2DEC:
					case ZBX_PREPROC_THROTTLE_VALUE:
						// Check if 'params' is empty, because it must be empty.
						if (is_array($preprocessing['params'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
						}
						elseif ($preprocessing['params'] !== '' && $preprocessing['params'] !== null
								&& $preprocessing['params'] !== false) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect value for field "%1$s": %2$s.', 'params', _('should be empty'))
							);
						}

						if ($preprocessing['type'] == ZBX_PREPROC_THROTTLE_VALUE) {
							if ($throttling) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _('Only one throttling step is allowed.'));
							}
							else {
								$throttling = true;
							}
						}
						break;

					case ZBX_PREPROC_DELTA_VALUE:
					case ZBX_PREPROC_DELTA_SPEED:
						// Check if 'params' is empty, because it must be empty.
						if (is_array($preprocessing['params'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
						}
						elseif ($preprocessing['params'] !== '' && $preprocessing['params'] !== null
								&& $preprocessing['params'] !== false) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect value for field "%1$s": %2$s.', 'params', _('should be empty'))
							);
						}

						// Check if one of the deltas (Delta per second or Delta value) already exists.
						if ($delta) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Only one change step is allowed.'));
						}
						else {
							$delta = true;
						}
						break;

					case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
						$api_input_rules = [
							'type' => API_TIME_UNIT,
							'flags' => ($this instanceof CItem)
								? API_NOT_EMPTY | API_ALLOW_USER_MACRO
								: API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO,
							'in' => '1:'.ZBX_MAX_TIMESHIFT
						];

						if (!CApiInputValidator::validate($api_input_rules, $preprocessing['params'], 'params',
								$error)) {
							self::exception(ZBX_API_ERROR_PARAMETERS, $error);
						}

						if ($throttling) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Only one throttling step is allowed.'));
						}
						else {
							$throttling = true;
						}
						break;

					case ZBX_PREPROC_PROMETHEUS_PATTERN:
					case ZBX_PREPROC_PROMETHEUS_TO_JSON:
						if ($prometheus) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Only one Prometheus step is allowed.'));
						}

						$prometheus = true;

						if (is_array($preprocessing['params'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
						}

						if ($preprocessing['type'] == ZBX_PREPROC_PROMETHEUS_PATTERN) {
							if ($preprocessing['params'] === '' || $preprocessing['params'] === null
									|| $preprocessing['params'] === false) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('Incorrect value for field "%1$s": %2$s.', 'params', _('cannot be empty'))
								);
							}

							$params = explode("\n", $preprocessing['params']);

							if ($params[0] === '') {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
									'params', _('first parameter is expected')
								));
							}

							if ($prometheus_pattern_parser->parse($params[0]) != CParser::PARSE_SUCCESS) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
									'params', _('invalid Prometheus pattern')
								));
							}

							if ($params[1] !== ''
									&& $prometheus_output_parser->parse($params[1]) != CParser::PARSE_SUCCESS) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
									'params', _('invalid Prometheus output')
								));
							}
						}
						// Prometheus to JSON can be empty and has only one parameter.
						elseif ($preprocessing['params'] !== '') {
							if ($prometheus_pattern_parser->parse($preprocessing['params']) != CParser::PARSE_SUCCESS) {
								self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
									'params', _('invalid Prometheus pattern')
								));
							}
						}
						break;
				}

				switch ($preprocessing['type']) {
					case ZBX_PREPROC_RTRIM:
					case ZBX_PREPROC_LTRIM:
					case ZBX_PREPROC_TRIM:
					case ZBX_PREPROC_THROTTLE_VALUE:
					case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
					case ZBX_PREPROC_SCRIPT:
						if (is_array($preprocessing['error_handler'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
						}
						elseif ($preprocessing['error_handler'] != ZBX_PREPROC_FAIL_DEFAULT) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect value for field "%1$s": %2$s.', 'error_handler',
									_s('unexpected value "%1$s"', $preprocessing['error_handler'])
								)
							);
						}

						if (is_array($preprocessing['error_handler_params'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
						}
						elseif ($preprocessing['error_handler_params'] !== ''
								&& $preprocessing['error_handler_params'] !== null
								&& $preprocessing['error_handler_params'] !== false) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect value for field "%1$s": %2$s.', 'error_handler_params',
									_('should be empty')
								)
							);
						}
						break;

					default:
						if (is_array($preprocessing['error_handler'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
						}
						elseif (!$error_handler_validator->validate($preprocessing['error_handler'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect value for field "%1$s": %2$s.', 'error_handler',
									_s('unexpected value "%1$s"', $preprocessing['error_handler'])
								)
							);
						}

						if (is_array($preprocessing['error_handler_params'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
						}
						elseif (($preprocessing['error_handler'] == ZBX_PREPROC_FAIL_DEFAULT
									|| $preprocessing['error_handler'] == ZBX_PREPROC_FAIL_DISCARD_VALUE)
								&& $preprocessing['error_handler_params'] !== ''
								&& $preprocessing['error_handler_params'] !== null
								&& $preprocessing['error_handler_params'] !== false) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect value for field "%1$s": %2$s.', 'error_handler_params',
									_('should be empty')
								)
							);
						}
						elseif ($preprocessing['error_handler'] == ZBX_PREPROC_FAIL_SET_ERROR
								&& ($preprocessing['error_handler_params'] === ''
									|| $preprocessing['error_handler_params'] === null
									|| $preprocessing['error_handler_params'] === false)) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Incorrect value for field "%1$s": %2$s.', 'error_handler_params',
									_('cannot be empty')
								)
							);
						}
				}
			}
		}
	}

	/**
	 * Method validates preprocessing steps independently from other item properties.
	 *
	 * @param array  $preprocessing_steps    An array of item pre-processing step details.
	 *                                       See self::validateItemPreprocessing for details.
	 *
	 * @return bool|string
	 */
	public function validateItemPreprocessingSteps(array $preprocessing_steps) {
		try {
			$this->validateItemPreprocessing(['preprocessing' => $preprocessing_steps]);

			return true;
		}
		catch (APIException $error) {
			return $error->getMessage();
		}
	}

	/**
	 * Insert item pre-processing data into DB.
	 *
	 * @param array $items                     An array of items.
	 * @param array $items[]['preprocessing']  An array of item pre-processing data.
	 */
	protected function createItemPreprocessing(array $items) {
		$item_preproc = [];

		foreach ($items as $item) {
			if (array_key_exists('preprocessing', $item)) {
				$step = 1;

				foreach ($item['preprocessing'] as $preprocessing) {
					$item_preproc[] = [
						'itemid' => $item['itemid'],
						'step' => $step++,
						'type' => $preprocessing['type'],
						'params' => $preprocessing['params'],
						'error_handler' => $preprocessing['error_handler'],
						'error_handler_params' => $preprocessing['error_handler_params']
					];
				}
			}
		}

		if ($item_preproc) {
			DB::insertBatch('item_preproc', $item_preproc);
		}
	}

	/**
	 * Update item pre-processing data in DB. Delete old records and create new ones.
	 *
	 * @param array $items                     An array of items.
	 * @param array $items[]['preprocessing']  An array of item pre-processing data.
	 */
	protected function updateItemPreprocessing(array $items) {
		$item_preproc = [];
		$item_preprocids = [];

		foreach ($items as $item) {
			if (array_key_exists('preprocessing', $item)) {
				$item_preprocids[] = $item['itemid'];
				$step = 1;

				foreach ($item['preprocessing'] as $preprocessing) {
					$item_preproc[] = [
						'itemid' => $item['itemid'],
						'step' => $step++,
						'type' => $preprocessing['type'],
						'params' => $preprocessing['params'],
						'error_handler' => $preprocessing['error_handler'],
						'error_handler_params' => $preprocessing['error_handler_params']
					];
				}
			}
		}

		if ($item_preprocids) {
			DB::delete('item_preproc', ['itemid' => $item_preprocids]);
		}

		if ($item_preproc) {
			DB::insertBatch('item_preproc', $item_preproc);
		}
	}

	/**
	 * Check if any item from list already exists.
	 * If items have item ids it will check for existing item with different itemid.
	 *
	 * @throw APIException
	 *
	 * @param array $items
	 */
	protected function checkExistingItems(array $items) {
		$itemKeysByHostId = [];
		$itemIds = [];
		foreach ($items as $item) {
			if (!isset($itemKeysByHostId[$item['hostid']])) {
				$itemKeysByHostId[$item['hostid']] = [];
			}
			$itemKeysByHostId[$item['hostid']][] = $item['key_'];

			if (isset($item['itemid'])) {
				$itemIds[] = $item['itemid'];
			}
		}

		$sqlWhere = [];
		foreach ($itemKeysByHostId as $hostId => $keys) {
			$sqlWhere[] = '(i.hostid='.zbx_dbstr($hostId).' AND '.dbConditionString('i.key_', $keys).')';
		}

		if ($sqlWhere) {
			$sql = 'SELECT i.key_,h.host'.
					' FROM items i,hosts h'.
					' WHERE i.hostid=h.hostid AND ('.implode(' OR ', $sqlWhere).')';

			// if we update existing items we need to exclude them from result.
			if ($itemIds) {
				$sql .= ' AND '.dbConditionInt('i.itemid', $itemIds, true);
			}
			$dbItems = DBselect($sql, 1);
			while ($dbItem = DBfetch($dbItems)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Item with key "%1$s" already exists on "%2$s".', $dbItem['key_'], $dbItem['host']));
			}
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// adding hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'itemid', 'hostid');
			$hosts = API::Host()->get([
				'hostids' => $relationMap->getRelatedIds(),
				'templated_hosts' => true,
				'output' => $options['selectHosts'],
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding preprocessing
		if ($options['selectPreprocessing'] !== null && $options['selectPreprocessing'] != API_OUTPUT_COUNT) {
			$db_item_preproc = API::getApiService()->select('item_preproc', [
				'output' => $this->outputExtend($options['selectPreprocessing'], ['itemid', 'step']),
				'filter' => ['itemid' => array_keys($result)],
			]);

			CArrayHelper::sort($db_item_preproc, ['step']);

			foreach ($result as &$item) {
				$item['preprocessing'] = [];
			}
			unset($item);

			foreach ($db_item_preproc as $step) {
				$itemid = $step['itemid'];
				unset($step['item_preprocid'], $step['itemid'], $step['step']);

				if (array_key_exists($itemid, $result)) {
					$result[$itemid]['preprocessing'][] = $step;
				}
			}
		}

		return $result;
	}

	/**
	 * Validate items with type ITEM_TYPE_DEPENDENT for create or update operation.
	 *
	 * @param array  $items
	 * @param string $items[]['itemid']         (mandatory for updated items and item prototypes)
	 * @param string $items[]['hostid']
	 * @param int    $items[]['type']
	 * @param string $items[]['master_itemid']  (mandatory for ITEM_TYPE_DEPENDENT)
	 * @param int    $items[]['flags']          (mandatory for items)
	 *
	 * @throws APIException for invalid data.
	 */
	protected function validateDependentItems(array $items) {
		$dep_items = [];
		$upd_itemids = [];

		foreach ($items as $item) {
			if ($item['type'] == ITEM_TYPE_DEPENDENT) {
				if ($this instanceof CDiscoveryRule || $this instanceof CItemPrototype
						|| $item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
					$dep_items[] = $item;
				}

				if (array_key_exists('itemid', $item)) {
					$upd_itemids[] = $item['itemid'];
				}
			}
		}

		if (!$dep_items) {
			return;
		}

		if ($this instanceof CItemPrototype && $upd_itemids) {
			$db_links = DBselect(
				'SELECT id.itemid,id.parent_itemid AS ruleid'.
				' FROM item_discovery id'.
				' WHERE '.dbConditionId('id.itemid', $upd_itemids)
			);

			$links = [];

			while ($db_link = DBfetch($db_links)) {
				$links[$db_link['itemid']] = $db_link['ruleid'];
			}

			foreach ($dep_items as &$dep_item) {
				if (array_key_exists('itemid', $dep_item)) {
					$dep_item['ruleid'] = $links[$dep_item['itemid']];
				}
			}
			unset($dep_item);
		}

		$master_itemids = [];

		foreach ($dep_items as $dep_item) {
			$master_itemids[$dep_item['master_itemid']] = true;
		}

		$master_items = [];

		// Fill relations array by master items (item prototypes). Discovery rule should not be master item.
		do {
			if ($this instanceof CItemPrototype) {
				$db_master_items = DBselect(
					'SELECT i.itemid,i.hostid,i.master_itemid,i.flags,id.parent_itemid AS ruleid'.
					' FROM items i'.
						' LEFT JOIN item_discovery id'.
							' ON i.itemid=id.itemid'.
					' WHERE '.dbConditionId('i.itemid', array_keys($master_itemids)).
						' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_PROTOTYPE])
				);
			}
			// CDiscoveryRule, CItem
			else {
				$db_master_items = DBselect(
					'SELECT i.itemid,i.hostid,i.master_itemid'.
					' FROM items i'.
					' WHERE '.dbConditionId('i.itemid', array_keys($master_itemids)).
						' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_NORMAL])
				);
			}

			while ($db_master_item = DBfetch($db_master_items)) {
				$master_items[$db_master_item['itemid']] = $db_master_item;

				unset($master_itemids[$db_master_item['itemid']]);
			}

			if ($master_itemids) {
				reset($master_itemids);

				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_s('Incorrect value for field "%1$s": %2$s.', 'master_itemid',
						_s('Item "%1$s" does not exist or you have no access to this item', key($master_itemids))
					)
				);
			}

			$master_itemids = [];

			foreach ($master_items as $master_item) {
				if ($master_item['master_itemid'] != 0
						&& !array_key_exists($master_item['master_itemid'], $master_items)) {
					$master_itemids[$master_item['master_itemid']] = true;
				}
			}
		} while ($master_itemids);

		foreach ($dep_items as $dep_item) {
			$master_item = $master_items[$dep_item['master_itemid']];

			if ($dep_item['hostid'] != $master_item['hostid']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'master_itemid', _('hostid of dependent item and master item should match')
				));
			}

			if ($this instanceof CItemPrototype && $master_item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE
					&& $dep_item['ruleid'] != $master_item['ruleid']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'master_itemid', _('ruleid of dependent item and master item should match')
				));
			}

			if (array_key_exists('itemid', $dep_item)) {
				$master_itemid = $dep_item['master_itemid'];

				while ($master_itemid != 0) {
					if ($master_itemid == $dep_item['itemid']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'master_itemid', _('circular item dependency is not allowed')
						));
					}

					$master_itemid = $master_items[$master_itemid]['master_itemid'];
				}
			}
		}

		// Fill relations array by dependent items (item prototypes).
		$root_itemids = [];

		foreach ($master_items as $master_item) {
			if ($master_item['master_itemid'] == 0) {
				$root_itemids[] = $master_item['itemid'];
			}
		}

		$dependent_items = [];

		foreach ($dep_items as $dep_item) {
			if (array_key_exists('itemid', $dep_item)) {
				$dependent_items[$dep_item['master_itemid']][] = $dep_item['itemid'];
			}
		}

		$master_itemids = $root_itemids;

		do {
			$sql = 'SELECT i.master_itemid,i.itemid'.
				' FROM items i'.
				' WHERE '.dbConditionId('i.master_itemid', $master_itemids);
			if ($upd_itemids) {
				$sql .= ' AND '.dbConditionId('i.itemid', $upd_itemids, true); // Exclude updated items.
			}

			$db_items = DBselect($sql);

			while ($db_item = DBfetch($db_items)) {
				$dependent_items[$db_item['master_itemid']][] = $db_item['itemid'];
			}

			$_master_itemids = $master_itemids;
			$master_itemids = [];

			foreach ($_master_itemids as $master_itemid) {
				if (array_key_exists($master_itemid, $dependent_items)) {
					$master_itemids = array_merge($master_itemids, $dependent_items[$master_itemid]);
				}
			}
		} while ($master_itemids);

		foreach ($dep_items as $dep_item) {
			if (!array_key_exists('itemid', $dep_item)) {
				$dependent_items[$dep_item['master_itemid']][] = false;
			}
		}

		foreach ($root_itemids as $root_itemid) {
			self::checkDependencyDepth($dependent_items, $root_itemid);
		}
	}

	/**
	 * Validate depth and ammount of elements in the tree of the dependent items.
	 *
	 * @param array  $dependent_items
	 * @param string $dependent_items[<master_itemid>][]  List if the dependent item IDs ("false" for new items)
	 *                                                    by master_itemid.
	 * @param string $root_itemid                         ID of the item being checked.
	 * @param int    $level                               Current dependency level.
	 *
	 * @throws APIException for invalid data.
	 */
	private static function checkDependencyDepth(array $dependent_items, $root_itemid, $level = 0) {
		$count = 0;

		if (array_key_exists($root_itemid, $dependent_items)) {
			if (++$level > ZBX_DEPENDENT_ITEM_MAX_LEVELS) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'master_itemid', _('maximum number of dependency levels reached')
				));
			}

			foreach ($dependent_items[$root_itemid] as $master_itemid) {
				$count++;

				if ($master_itemid !== false) {
					$count += self::checkDependencyDepth($dependent_items, $master_itemid, $level);
				}
			}

			if ($count > ZBX_DEPENDENT_ITEM_MAX_COUNT) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'master_itemid', _('maximum dependent items count reached')
				));
			}
		}

		return $count;
	}

	/**
	 * Converts headers field text to hash with header name as key.
	 *
	 * @param string $headers  Headers string, one header per line, line delimiter "\r\n".
	 *
	 * @return array
	 */
	protected function headersStringToArray($headers) {
		$result = [];

		foreach (explode("\r\n", $headers) as $header) {
			$header = explode(': ', $header, 2);

			if (count($header) == 2) {
				$result[$header[0]] = $header[1];
			}
		}

		return $result;
	}

	/**
	 * Converts headers fields hash to string.
	 *
	 * @param array $headers  Array of headers where key is header name.
	 *
	 * @return string
	 */
	protected function headersArrayToString(array $headers) {
		$result = [];

		foreach ($headers as $k => $v) {
			$result[] = $k.': '.$v;
		}

		return implode("\r\n", $result);
	}

	/**
	 * Validate item with type ITEM_TYPE_HTTPAGENT.
	 *
	 * @param array $item     Array of item fields.
	 * @param array $db_item  Array of item database fields for update action or empty array for create action.
	 *
	 * @throws APIException for invalid data.
	 */
	protected function validateHTTPCheck(array $item, array $db_item) {
		$rules = [
			'timeout' => [
				'type' => API_TIME_UNIT, 'flags' => ($this instanceof CItemPrototype)
					? API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO
					: API_NOT_EMPTY | API_ALLOW_USER_MACRO,
				'in' => '1:'.SEC_PER_MIN
			],
			'url' => [
				'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY,
				'length' => DB::getFieldLength('items', 'url'),
			],
			'status_codes' => [
				'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'status_codes')
			],
			'follow_redirects' => [
				'type' => API_INT32,
				'in' => implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON]),
			],
			'post_type' => [
				'type' => API_INT32,
				'in' => implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML])
			],
			'http_proxy' => [
				'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'http_proxy')
			],
			'headers' => [
				'type' => API_STRINGS_UTF8
			],
			'retrieve_mode' => [
				'type' => API_INT32,
				'in' => implode(',', [
					HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS,
					HTTPTEST_STEP_RETRIEVE_MODE_BOTH
				])
			],
			'request_method' => [
				'type' => API_INT32,
				'in' => implode(',', [
					HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD
				])
			],
			'output_format' => [
				'type' => API_INT32,
				'in' => implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON])
			],
			'allow_traps' => [
				'type' => API_INT32,
				'in' => implode(',', [HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON])
			],
			'ssl_cert_file' => [
				'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'ssl_cert_file'),
			],
			'ssl_key_file' => [
				'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'ssl_key_file'),
			],
			'ssl_key_password' => [
				'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'ssl_key_password'),
			],
			'verify_peer' => [
				'type' => API_INT32,
				'in' => implode(',', [HTTPTEST_VERIFY_PEER_OFF, HTTPTEST_VERIFY_PEER_ON])
			],
			'verify_host' => [
				'type' => API_INT32,
				'in' => implode(',', [HTTPTEST_VERIFY_HOST_OFF, HTTPTEST_VERIFY_HOST_ON])
			],
			'authtype' => [
				'type' => API_INT32,
				'in' => implode(',', [
					HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS
				])
			]
		];

		$data = $item + $db_item;

		if (array_key_exists('authtype', $data)
				&& ($data['authtype'] == HTTPTEST_AUTH_BASIC || $data['authtype'] == HTTPTEST_AUTH_NTLM
					|| $data['authtype'] == HTTPTEST_AUTH_KERBEROS)) {
			$rules += [
				'username' => [ 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'username')],
				'password' => [ 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'password')]
			];
		}

		// Strict validation for 'retrieve_mode' only for create action.
		if (array_key_exists('request_method', $data) && $data['request_method'] == HTTPCHECK_REQUEST_HEAD
				&& array_key_exists('retrieve_mode', $item)) {
			$rules['retrieve_mode']['in'] = (string) HTTPTEST_STEP_RETRIEVE_MODE_HEADERS;
		}

		if (array_key_exists('post_type', $data)
				&& ($data['post_type'] == ZBX_POSTTYPE_JSON || $data['post_type'] == ZBX_POSTTYPE_XML)) {
			$rules['posts'] = [
				'type' => API_STRING_UTF8,
				'length' => DB::getFieldLength('items', 'posts')
			];
		}

		if (array_key_exists('templateid', $data) && $data['templateid']) {
			$rules['interfaceid'] = [
				'type' => API_INT32, 'flags' => API_REQUIRED | API_NOT_EMPTY
			];
		}

		if (array_key_exists('trapper_hosts', $item) && $item['trapper_hosts'] !== ''
				&& (!array_key_exists('allow_traps', $data) || $data['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_OFF)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', 'trapper_hosts', _('should be empty'))
			);
		}

		// Keep values only for fields with defined validation rules.
		$data = array_intersect_key($data, $rules);

		if (!CApiInputValidator::validate(['type' => API_OBJECT, 'fields' => $rules], $data, '', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$json = new CJson();

		if (array_key_exists('query_fields', $item)) {
			if (!is_array($item['query_fields'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', 'query_fields', _('an array is expected'))
				);
			}

			foreach ($item['query_fields'] as $v) {
				if (!is_array($v) || count($v) > 1 || key($v) === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', 'query_fields', _('nonempty key and value pair expected'))
					);
				}
			}

			$json_string = $json->encode($item['query_fields']);

			if (strlen($json_string) > DB::getFieldLength('items', 'query_fields')) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', 'query_fields',
					_('cannot convert to JSON, result value too long')
				));
			}
		}

		if (array_key_exists('headers', $item)) {
			if (!is_array($item['headers'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', 'headers', _('an array is expected'))
				);
			}

			foreach ($item['headers'] as $k => $v) {
				if (trim($k) === '' || !is_string($v) || $v === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', 'headers', _('nonempty key and value pair expected'))
					);
				}
			}
		}

		if (array_key_exists('status_codes', $item) && $item['status_codes']) {
			$ranges_parser = new CRangesParser([
				'usermacros' => true,
				'lldmacros' => ($this instanceof CItemPrototype)
			]);

			if ($ranges_parser->parse($item['status_codes']) != CParser::PARSE_SUCCESS) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value "%1$s" for "%2$s" field.', $item['status_codes'], 'status_codes')
				);
			}
		}

		if ((array_key_exists('post_type', $item) || array_key_exists('posts', $item))
				&& ($data['post_type'] == ZBX_POSTTYPE_JSON || $data['post_type'] == ZBX_POSTTYPE_XML)) {
			$posts = array_key_exists('posts', $data) ? $data['posts'] : '';
			libxml_use_internal_errors(true);

			if ($data['post_type'] == ZBX_POSTTYPE_XML
					&& simplexml_load_string($posts, null, LIBXML_IMPORT_FLAGS) === false) {
				$errors = libxml_get_errors();
				libxml_clear_errors();

				if (!$errors) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', 'posts', _('XML is expected'))
					);
				}
				else {
					$error = reset($errors);
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', 'posts',
						_s('%1$s [Line: %2$s | Column: %3$s]', '('.$error->code.') '.trim($error->message),
						$error->line, $error->column
					)));
				}
			}

			if ($data['post_type'] == ZBX_POSTTYPE_JSON) {
				if (trim($posts, " \r\n") === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', 'posts', _('JSON is expected'))
					);
				}

				$types = [
					'usermacros' => true,
					'macros_n' => [
						'{HOST.IP}', '{HOST.CONN}', '{HOST.DNS}', '{HOST.HOST}', '{HOST.NAME}', '{ITEM.ID}',
						'{ITEM.KEY}'
					]
				];

				if ($this instanceof CItemPrototype) {
					$types['lldmacros'] = true;
				}

				$matches = (new CMacrosResolverGeneral)->getMacroPositions($posts, $types);

				$shift = 0;

				foreach ($matches as $pos => $substr) {
					$posts = substr_replace($posts, '1', $pos + $shift, strlen($substr));
					$shift = $shift + 1 - strlen($substr);
				}

				$json->decode($posts);

				if ($json->hasError()) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', 'posts', _('JSON is expected'))
					);
				}
			}
		}
	}
}
