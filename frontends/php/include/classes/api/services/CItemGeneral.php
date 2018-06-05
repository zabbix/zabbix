<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
			'master_itemid'			=> ['template' => 1],
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

		foreach ($items as $item) {
			if (!check_db_fields($itemDbFields, $item)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}
		}

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
		$ip_range_parser = new CIPRangeParser(['v6' => ZBX_HAVE_IPV6, 'ranges' => false, 'usermacros' => true]);
		$update_interval_parser = new CUpdateIntervalParser([
			'usermacros' => true,
			'lldmacros' => (get_class($this) === 'CItemPrototype')
		]);

		foreach ($items as $inum => &$item) {
			$item = $this->clearValues($item);

			$fullItem = $items[$inum];

			if ($update) {
				$type = array_key_exists('type', $item) ? $item['type'] : $dbItems[$item['itemid']]['type'];

				if ($type == ITEM_TYPE_HTTPAGENT) {
					$this->validateHTTPCheck($fullItem, $dbItems[$item['itemid']]);
				}

				check_db_fields($dbItems[$item['itemid']], $fullItem);

				$this->checkNoParameters(
					$item,
					['templateid', 'state'],
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
		}
		unset($item);

		$this->checkExistingItems($items);

		// Validate inherited dependent items linkage.
		$templateids = [];

		foreach ($dbHosts as $db_host) {
			if ($db_host['status'] == HOST_STATUS_TEMPLATE) {
				$templateids[] = $db_host['hostid'];
			}
		}

		$templateids = array_keys(array_flip($templateids));
		$templates = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => $templateids,
			'selectHosts' => ['hostid']
		]);

		foreach ($templates as $template) {
			if (!$template['hosts']) {
				continue;
			}

			$hostids = zbx_objectValues($template['hosts'], 'hostid');
			$hostids = array_keys(array_flip($hostids));
			$all_hostids = array_merge($hostids, [$template['templateid']]);

			$options = [
				'output' => ['itemid', 'type', 'key_', 'master_itemid', 'hostid'],
				'hostids' => $all_hostids,
				'preservekeys' => true
			];

			if ($this instanceof CItem) {
				$options['webitems'] = true;
			}

			$host_items = $this->get($options);

			foreach ($items as $item) {
				if ($update) {
					$item += $dbItems[$item['itemid']];
				}

				if ($item['hostid'] == $template['templateid']
						|| ($item['type'] == ITEM_TYPE_DEPENDENT
							&& array_key_exists($item['master_itemid'], $host_items))) {
					$item_index = array_key_exists('itemid', $item) ? $item['itemid'] : $item['key_'];
					$host_items[$item_index] = $item;
				}
			}

			$this->validateDependentItemsIntersection($host_items, $hostids);
		}
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
	protected function checkSpecificFields(array $item, $method) {
		return true;
	}

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
	 * Checks whether the given items are referenced by any graphs and tries to
	 * unset these references, if they are no longer used.
	 *
	 * @throws APIException if at least one of the item can't be deleted
	 *
	 * @param array $itemids   An array of item IDs
	 */
	protected function checkGraphReference(array $itemids) {
		$this->checkUseInGraphAxis($itemids, true);
		$this->checkUseInGraphAxis($itemids);
	}

	/**
	 * Checks if any of the given items are used as min/max Y values in a graph.
	 *
	 * if there are graphs, that have an y*_itemid column set, but the
	 * y*_type column is not set to GRAPH_YAXIS_TYPE_ITEM_VALUE, the y*_itemid
	 * column will be set to NULL.
	 *
	 * If the $checkMax parameter is set to true, the items will be checked against
	 * max Y values, otherwise, they will be checked against min Y values.
	 *
	 * @throws APIException if any of the given items are used as min/max Y values in a graph.
	 *
	 * @param array $itemids   An array of items IDs
	 * @param bool  $check_max
	 */
	protected function checkUseInGraphAxis(array $itemids, $check_max = false) {
		$field_name_itemid = $check_max ? 'ymax_itemid' : 'ymin_itemid';
		$field_name_type = $check_max ? 'ymax_type' : 'ymin_type';
		$error = $check_max
			? 'Could not delete these items because some of them are used as MAX values for graphs.'
			: 'Could not delete these items because some of them are used as MIN values for graphs.';

		$result = DBselect(
			'SELECT g.graphid,g.'.$field_name_type.
			' FROM graphs g'.
			' WHERE '.dbConditionInt('g.'.$field_name_itemid, $itemids)
		);

		$update_graphs = [];

		while ($row = DBfetch($result)) {
			// check if Y type is actually set to GRAPH_YAXIS_TYPE_ITEM_VALUE
			if ($row[$field_name_type] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
			else {
				$update_graphs[] = [
					'values' => [$field_name_itemid => 0],
					'where' => ['graphid' => $row['graphid']]
				];
			}
		}
		unset($graph);

		// if there are graphs, that have an y*_itemid column set, but the
		// y*_type column is not set to GRAPH_YAXIS_TYPE_ITEM_VALUE, set y*_itemid to NULL.
		// Otherwise we won't be able to delete them.
		if ($update_graphs) {
			DB::update('graphs', $update_graphs);
		}
	}

	/**
	 * Updates the children of the item on the given hosts and propagates the inheritance to the child hosts.
	 *
	 * @abstract
	 *
	 * @param array $items          an array of items to inherit
	 * @param array|null $hostids   an array of hosts to inherit to; if set to null, the children will be updated on all
	 *                              child hosts
	 *
	 * @return bool
	 */
	abstract protected function inherit(array $items, array $hostids = null);

	/**
	 * Prepares and returns an array of child items, inherited from items $tpl_items on the given hosts.
	 *
	 * @param array      $tpl_items
	 * @param string     $tpl_items[]['itemid']
	 * @param string     $tpl_items[]['hostid']
	 * @param string     $tpl_items[]['key_']
	 * @param int        $tpl_items[]['type']
	 * @param array      $tpl_items[]['applicationPrototypes']            (optional) Suitable for item prototypes.
	 * @param string     $tpl_items[]['applicationPrototypes'][]['name']
	 * @param array      $tpl_items[]['applications']                     (optional) Array of applicationids.
	 * @param array      $tpl_items[]['preprocessing']                    (optional) Suitable for items and item
	 *                                                                               prototypes.
	 * @param int        $tpl_items[]['preprocessing'][]['type']
	 * @param string     $tpl_items[]['preprocessing'][]['params']
	 * @param int        $tpl_items[]['flags']
	 * @param mixed      $tpl_items[][<field_name>]                       (optional)
	 * @param array|null $hostids
	 *
	 * @return array an array of unsaved child items
	 */
	protected function prepareInheritedItems(array $tpl_items, array $hostids = null) {
		$class = get_class($this);

		$tpl_items = zbx_toHash($tpl_items, 'itemid');

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
		$sql = 'SELECT i.itemid,i.hostid,i.type,i.key_,i.flags,i.templateid,i.master_itemid'.
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

		foreach ($hostids_by_key as $key_ => $hostids) {
			$sql_select = ($class === 'CItemPrototype') ? ',id.parent_itemid AS ruleid' : '';
			$sql_join = ($class === 'CItemPrototype') ? ' JOIN item_discovery id ON i.itemid=id.itemid' : '';
			$db_items = DBselect(
				'SELECT i.itemid,i.hostid,i.type,i.key_,i.flags,i.templateid,i.master_itemid'.$sql_select.
					' FROM items i'.$sql_join.
					' WHERE '.dbConditionInt('i.hostid', $hostids).
						' AND '.dbConditionString('i.key_', [$key_])
			);

			while ($db_item = DBfetch($db_items)) {
				$hostid = $db_item['hostid'];
				unset($db_item['hostid']);

				$chd_items_key[$hostid][$db_item['key_']] = $db_item;
			}
		}

		// Prapering list of application prototypes.
		if ($class === 'CItemPrototype') {
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
		if ($class === 'CItemPrototype') {
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
		$upd_keys_by_hostid = [];

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
						$upd_keys_by_hostid[$chd_host['hostid']][] = $tpl_item['key_'];
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

					if ($class === 'CItemPrototype') {
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
					if ($class === 'CItemPrototype') {
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
				}

				// For items and item prototypes.
				if (($class === 'CItem' || $class === 'CItemPrototype')
						&& array_key_exists('preprocessing', $new_item)) {
					foreach ($new_item['preprocessing'] as $preprocessing) {
						if ($chd_item) {
							$preprocessing['itemid'] = $chd_item['itemid'];
						}
						else {
							unset($preprocessing['itemid']);
						}
					}
				}

				if ($class === 'CItemPrototype' && array_key_exists('applicationPrototypes', $new_item)) {
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
		if ($upd_keys_by_hostid) {
			$sql_where = [];
			foreach ($upd_keys_by_hostid as $hostid => $keys) {
				$sql_where[] = dbConditionInt('i.hostid', [$hostid]).' AND '.dbConditionString('i.key_', $keys);
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
		if ($class === 'CItem' || $class === 'CItemPrototype') {
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

		return $new_items;
	}

	/**
	 * Validate item pre-processing.
	 *
	 * @param array  $item									An array of single item data.
	 * @param array  $item['preprocessing']					An array of item pre-processing data.
	 * @param string $item['preprocessing'][]['type']		The preprocessing option type. Possible values:
	 *															1 - ZBX_PREPROC_MULTIPLIER;
	 *															2 - ZBX_PREPROC_RTRIM;
	 *															3 - ZBX_PREPROC_LTRIM;
	 *															4 - ZBX_PREPROC_TRIM;
	 *															5 - ZBX_PREPROC_REGSUB;
	 *															6 - ZBX_PREPROC_BOOL2DEC;
	 *															7 - ZBX_PREPROC_OCT2DEC;
	 *															8 - ZBX_PREPROC_HEX2DEC;
	 *															9 - ZBX_PREPROC_DELTA_VALUE;
	 *															10 - ZBX_PREPROC_DELTA_SPEED;
	 *															11 - ZBX_PREPROC_XPATH;
	 *															12 - ZBX_PREPROC_JSONPATH.
	 * @param string $item['preprocessing'][]['params']		Additional parameters used by preprocessing option. In case
	 *														of regular expression (ZBX_PREPROC_REGSUB), multiple
	 *														parameters are separated by LF (\n)character.
	 * @param string $method								A string of "create" or "update" method.
	 */
	protected function validateItemPreprocessing(array $item, $method) {
		if (array_key_exists('preprocessing', $item)) {
			if (!is_array($item['preprocessing'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			$type_validator = new CLimitedSetValidator(['values' => array_keys(get_preprocessing_types(null, false))]);

			$required_fields = ['type', 'params'];
			$delta = false;

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
									|| (new CLLDMacroParser())->parse($params) != CParser::PARSE_SUCCESS)) {
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

					case ZBX_PREPROC_BOOL2DEC:
					case ZBX_PREPROC_OCT2DEC:
					case ZBX_PREPROC_HEX2DEC:
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
				}
			}
		}
	}

	/**
	 * Insert item pre-processing data into DB.
	 *
	 * @param array $items							An array of items.
	 * @param array $items[]['preprocessing']		An array of item pre-processing data.
	 */
	protected function createItemPreprocessing(array $items) {
		$item_preproc = [];
		$step = 1;

		foreach ($items as $item) {
			if (array_key_exists('preprocessing', $item)) {
				foreach ($item['preprocessing'] as $preprocessing) {
					$item_preproc[] = [
						'itemid' => $item['itemid'],
						'step' => $step++,
						'type' => $preprocessing['type'],
						'params' => $preprocessing['params']
					];
				}
			}
		}

		if ($item_preproc) {
			DB::insert('item_preproc', $item_preproc);
		}
	}

	/**
	 * Update item pre-processing data in DB. Delete old records and create new ones.
	 *
	 * @param array $items							An array of items.
	 * @param array $items[]['preprocessing']		An array of item pre-processing data.
	 */
	protected function updateItemPreprocessing(array $items) {
		$item_preproc = [];
		$item_preprocids = [];
		$step = 1;

		foreach ($items as $item) {
			if (array_key_exists('preprocessing', $item)) {
				$item_preprocids[] = $item['itemid'];

				foreach ($item['preprocessing'] as $preprocessing) {
					$item_preproc[] = [
						'itemid' => $item['itemid'],
						'step' => $step++,
						'type' => $preprocessing['type'],
						'params' => $preprocessing['params']
					];
				}
			}
		}

		if ($item_preprocids) {
			DB::delete('item_preproc', ['itemid' => $item_preprocids]);
		}

		if ($item_preproc) {
			DB::insert('item_preproc', $item_preproc);
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

		return $result;
	}

	/**
	 * Validate items with type ITEM_TYPE_DEPENDENT for create or update operation.
	 *
	 * @param array   $items   Array of items.
	 * @param string  $method  Create or update method.
	 *
	 * @throws APIException for invalid data.
	 */
	protected function validateDependentItems(array $items, $method) {
		$items_cache = zbx_toHash($items, 'itemid');
		$root_items = [];
		$items_added = [];
		$items_moved = [];
		$items_created = [];
		$db_items = [];
		$processed_items = [];
		$unresolved_master_itemids = [];
		$has_unresolved_masters = false;

		if ($items_cache) {
			$options = [
				'output' => ['itemid', 'type', 'name', 'hostid', 'master_itemid'],
				'itemids' => array_keys($items_cache),
				'preservekeys' => true
			];

			$db_items = API::Item()->get($options);

			if ($this instanceof CItemPrototype) {
				if ($method === 'CItemPrototype::update') {
					$options += ['selectDiscoveryRule' => ['itemid']];
				}

				$db_item_prototypes = API::ItemPrototype()->get($options);

				// Populate 'ruleid' field.
				if ($method === 'CItemPrototype::update') {
					foreach ($db_item_prototypes as &$db_item_prototype) {
						$db_item_prototype['ruleid'] = $db_item_prototype['discoveryRule']['itemid'];
					}
					unset($db_item_prototype);
				}

				$db_items += $db_item_prototypes;
			}

			foreach ($db_items as $db_itemid => $db_item) {
				$items_cache[$db_itemid] += $db_item;
			}
		}

		do {
			if ($has_unresolved_masters) {
				$options = [
					'output' => ['type', 'name', 'hostid', 'master_itemid'],
					'itemids' => array_keys($unresolved_master_itemids),
					'preservekeys' => true
				];

				$db_masters = API::Item()->get($options + ['webitems' => true]);

				if ($this instanceof CItemPrototype) {
					$db_master_prototypes = API::ItemPrototype()->get($options + ['selectDiscoveryRule' => ['itemid']]);

					foreach ($db_master_prototypes as &$db_master_prototype) {
						$db_master_prototype['ruleid'] = $db_master_prototype['discoveryRule']['itemid'];
					}
					unset($db_master_prototype);

					$db_masters += $db_master_prototypes;
				}

				foreach ($db_masters as $db_master) {
					$items_cache[$db_master['itemid']] = $db_master;
					unset($unresolved_master_itemids[$db_master['itemid']]);
				}

				if ($unresolved_master_itemids) {
					reset($unresolved_master_itemids);
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect value for field "%1$s": %2$s.',
						'master_itemid', _s('Item "%1$s" does not exist or you have no access to this item',
							key($unresolved_master_itemids)
					)));
				}
				$has_unresolved_masters = false;
			}

			foreach ($items as $item_index => $item) {
				if (array_key_exists($item_index, $processed_items)) {
					// Do not validate already checked items.
					continue;
				}

				if (array_key_exists('itemid', $item) && array_key_exists($item['itemid'], $items_cache)) {
					$item += $items_cache[$item['itemid']];
				}

				if ($item['type'] != ITEM_TYPE_DEPENDENT) {
					continue;
				}

				$dependency_level = 0;
				$item_masters = [];
				$master_item = $item;
				$hostid = $master_item['hostid'];

				if ($this instanceof CItemPrototype) {
					$ruleid = $master_item['ruleid'];
				}

				if (array_key_exists('itemid', $item)) {
					$item_masters[$item['itemid']] = true;
				}

				// Traversing up to root item, if next parent should be requested from database store it itemid,
				// missing parents will be requested in bulk request on next $items scan.
				while ($master_item && $master_item['type'] == ITEM_TYPE_DEPENDENT) {
					$master_itemid = $master_item['master_itemid'];

					if (array_key_exists('itemid', $master_item) && $master_itemid == $master_item['itemid']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'master_itemid', _('circular item dependency is not allowed')
						));
					}

					if ($item_masters && array_key_exists($master_itemid, $item_masters)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'master_itemid', _('circular item dependency is not allowed')
						));
					}

					if (array_key_exists($master_itemid, $items_cache)) {
						$master_item = $items_cache[$master_itemid];
						$item_masters[$master_itemid] = true;
						$dependency_level++;
					}
					else {
						$unresolved_master_itemids[$master_itemid] = true;
						$has_unresolved_masters = true;
						break;
					}

					if ($hostid != $master_item['hostid']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'master_itemid', _('hostid of dependent item and master item should match')
						));
					}

					if ($this instanceof CItemPrototype && array_key_exists('discoveryRule', $master_item)
							&& $ruleid != $master_item['discoveryRule']['itemid']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'master_itemid', _('ruleid of dependent item and master item should match')
						));
					}

					if ($dependency_level > ZBX_DEPENDENT_ITEM_MAX_LEVELS) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'master_itemid', _('maximum number of dependency levels reached')
						));
					}
				}

				// Dependency tree root item is resolved successfully.
				if ($dependency_level > 0 && $master_item && $master_item['type'] != ITEM_TYPE_DEPENDENT) {
					$processed_items[$item_index] = true;
					$master_itemid = $master_item['itemid'];
					$root_items[$master_itemid] = true;

					if (array_key_exists('itemid', $item) &&
							$item['type'] == ITEM_TYPE_DEPENDENT &&
							$item['master_itemid'] != $db_items[$item['itemid']]['master_itemid']) {
						$itemid = $item['itemid'];
						$old_master_itemid = $db_items[$itemid]['master_itemid'];

						if (!array_key_exists($master_itemid, $items_added)) {
							$items_added[$master_itemid] = [$dependency_level => []];
						}
						elseif (!array_key_exists($dependency_level, $items_added[$master_itemid])) {
							$items_added[$master_itemid][$dependency_level] = [];
						}
						$items_added[$master_itemid][$dependency_level][$itemid] = $itemid;

						if (!array_key_exists($old_master_itemid, $items_moved)) {
							$items_moved[$old_master_itemid] = [];
						}
						$items_moved[$old_master_itemid][$itemid] = $itemid;
					}
					elseif (!array_key_exists('itemid', $item)) {
						$items_created[$master_itemid] = array_key_exists($master_itemid, $items_created)
							? $items_created[$master_itemid] + 1
							: 1;
					}
				}
			}
		} while ($has_unresolved_masters);

		// Validate every root master items childrens count.
		foreach (array_keys($root_items) as $root_itemid) {
			$dependency_level = 0;
			$find_itemids = [$root_itemid => $dependency_level];
			$items_count = array_key_exists($root_itemid, $items_created)
				? $items_created[$root_itemid]
				: 0;
			$counted_masters = [];

			while (($find_itemids || (array_key_exists($root_itemid, $items_added)
						&& array_key_exists($dependency_level, $items_added[$root_itemid])))
						&& $dependency_level <= ZBX_DEPENDENT_ITEM_MAX_LEVELS) {
				// If item was moved to another master item, do not count moved item (and its dependent items)
				// in old master dependent items count calculation.
				if (array_key_exists($root_itemid, $items_moved)) {
					$ignoreids = array_intersect_key($find_itemids, $items_moved[$root_itemid]);
					$find_itemids = array_diff_key($find_itemids, $ignoreids);
				}
				if (array_key_exists($root_itemid, $items_added)
						&& array_key_exists($dependency_level, $items_added[$root_itemid])) {
					$find_itemids += $items_added[$root_itemid][$dependency_level];
				}

				$options = [
					'output' => ['itemid'],
					'filter' => ['master_itemid' => array_keys($find_itemids)],
					'preservekeys' => true
				];
				$find_itemids = API::Item()->get($options);

				if ($this instanceof CItemPrototype) {
					$find_itemids += API::ItemPrototype()->get($options);
				}

				$find_itemids = array_diff_key($find_itemids, $counted_masters);
				$items_count = $items_count + count($find_itemids);
				$counted_masters += $find_itemids;
				++$dependency_level;

				if ($items_count > ZBX_DEPENDENT_ITEM_MAX_COUNT) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'master_itemid', _('maximum dependent items count reached')
					));
				}
			};

			if (($find_itemids || (array_key_exists($root_itemid, $items_added)
					&& array_key_exists($dependency_level, $items_added[$root_itemid])))
					&& $dependency_level > ZBX_DEPENDENT_ITEM_MAX_LEVELS) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'master_itemid', _('maximum number of dependency levels reached')
				));
			}
		}
	}

	/**
	 * Synchronize dependent item to master item relation for inherited items.
	 *
	 * @param array $items  Array of inherited items.
	 */
	protected function inheritDependentItems(array $items) {
		$master_itemids = [];

		foreach ($items as $item) {
			if ($item['type'] == ITEM_TYPE_DEPENDENT && array_key_exists('master_itemid', $item)) {
				$master_itemids[$item['master_itemid']] = true;
			}
		}

		if ($master_itemids) {
			$master_items = DB::select('items', [
				'output' => ['key_', 'hostid'],
				'filter' => ['itemid' => array_keys($master_itemids)],
				'preservekeys' => true
			]);

			$data = [];
			$host_master_items = [];

			foreach ($items as $item) {
				if ($item['type'] != ITEM_TYPE_DEPENDENT || !array_key_exists('master_itemid', $item)) {
					continue;
				}
				$master_item = $master_items[$item['master_itemid']];

				if (!array_key_exists($item['hostid'], $host_master_items)) {
					$host_master_items[$item['hostid']] = [];
				}

				if (bccomp($master_item['hostid'], $item['hostid']) != 0) {
					if (!array_key_exists($master_item['key_'], $host_master_items[$item['hostid']])) {
						$inherited_master_items = DB::select('items', [
							'output' => ['itemid'],
							'filter' => ['hostid' => $item['hostid'], 'key_' => $master_item['key_']]
						]);

						$host_master_items[$item['hostid']][$master_item['key_']] = reset($inherited_master_items);
					}

					$inherited_master_item = $host_master_items[$item['hostid']][$master_item['key_']];
					$data[] = [
						'values' => ['master_itemid' => $inherited_master_item['itemid']],
						'where' => ['itemid' => $item['itemid']]
					];
				}
			}

			if ($data) {
				DB::update('items', $data);
			}
		}
	}

	/**
	 * Validate merge of template dependent items and every host dependent items, host dependent item will be
	 * overwritten by template dependent items.
	 * Return false if intersection of host dependent items and template dependent items create dependent items
	 * with dependency level greater than ZBX_DEPENDENT_ITEM_MAX_LEVELS.
	 *
	 * @param array $db_items
	 * @param array $hostids
	 *
	 * @throws APIException if intersection of template items and host items creates dependent items tree with
	 *                      dependent item level more than ZBX_DEPENDENT_ITEM_MAX_LEVELS or master item recursion.
	 */
	protected function validateDependentItemsIntersection(array $db_items, array $hostids) {
		$hosts_items = [];
		$tmpl_items = [];

		foreach ($db_items as $db_item) {
			$master_key = ($db_item['type'] == ITEM_TYPE_DEPENDENT
					&& array_key_exists($db_item['master_itemid'], $db_items))
				? $db_items[$db_item['master_itemid']]['key_']
				: '';

			if (in_array($db_item['hostid'], $hostids)) {
				$hosts_items[$db_item['hostid']][$db_item['key_']] = $master_key;
			}
			elseif (!array_key_exists($db_item['key_'], $tmpl_items) || !$tmpl_items[$db_item['key_']]) {
				$tmpl_items[$db_item['key_']] = $master_key;
			}
		}

		foreach ($hosts_items as $hostid => $items) {
			$linked_items = $items;

			// Merge host items dependency tree with template items dependency tree.
			$linked_items = array_merge($linked_items, $tmpl_items);

			// Check dependency level for every dependent item.
			foreach ($linked_items as $linked_item => $linked_master_key) {
				$master_key = $linked_master_key;
				$dependency_level = 0;
				$traversing_path = [];

				while ($master_key && $dependency_level <= ZBX_DEPENDENT_ITEM_MAX_LEVELS) {
					$traversing_path[] = $master_key;
					$master_key = $linked_items[$master_key];
					++$dependency_level;

					if (in_array($master_key, $traversing_path)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'master_itemid', _('circular item dependency is not allowed')
						));
					}
				}

				if ($dependency_level > ZBX_DEPENDENT_ITEM_MAX_LEVELS) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'master_itemid', _('maximum number of dependency levels reached')
					));
				}
			}
		}
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
					? API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO
					: API_ALLOW_USER_MACRO,
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
				'in' => implode(',', [HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM])
			]
		];

		$data = $item + $db_item;

		if (array_key_exists('authtype', $data)
				&& ($data['authtype'] == HTTPTEST_AUTH_BASIC || $data['authtype'] == HTTPTEST_AUTH_NTLM)) {
			$rules += [
				'username' => [
					'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY,
					'length' => DB::getFieldLength('items', 'username')
				],
				'password' => [
					'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY,
					'length' => DB::getFieldLength('items', 'password')
				]
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
			$validator = new CStatusCodeRangesValidator([
				'usermacros' => true,
				'lldmacros' => ($this instanceof CItemPrototype)
			]);

			if (!$validator->validate($item['status_codes'])) {
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
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot read XML: %1$s.', _('XML is empty')));
				}
				else {
					$error = reset($errors);
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot read XML: %1$s.',
						_s('%1$s [Line: %2$s | Column: %3$s]', '('.$error->code.') '.trim($error->message),
						$error->line, $error->column
					)));
				}
			}

			if ($data['post_type'] == ZBX_POSTTYPE_JSON) {
				if (trim($posts, " \r\n") === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot read JSON.'));
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
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot read JSON.'));
				}
			}
		}
	}
}
