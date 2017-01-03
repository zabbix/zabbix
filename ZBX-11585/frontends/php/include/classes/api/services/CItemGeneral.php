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
			'delay'					=> [],
			'history'				=> [],
			'trends'				=> [],
			'status'				=> [],
			'value_type'			=> ['template' => 1],
			'trapper_hosts'			=> [],
			'units'					=> ['template' => 1],
			'multiplier'			=> ['template' => 1],
			'delta'					=> ['template' => 1],
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
			'delay_flex'			=> [],
			'params'				=> [],
			'ipmi_sensor'			=> ['template' => 1],
			'data_type'				=> ['template' => 1],
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
			'lifetime'				=> []
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
				'delay' => '0',
				'delay_flex' => ''
			];

			$dbHosts = API::Host()->get([
				'output' => ['hostid', 'status', 'name'],
				'hostids' => zbx_objectValues($items, 'hostid'),
				'templated_hosts' => true,
				'editable' => true,
				'selectApplications' => ['applicationid', 'flags'],
				'preservekeys' => true
			]);
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
			foreach ($items as $item) {
				// check permissions
				if (!isset($dbItems[$item['itemid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('No permissions to referred object or it does not exist!'));
				}

				$dbItem = $dbItems[$item['itemid']];

				$itemName = isset($item['name']) ? $item['name'] : $dbItem['name'];

				// discovered fields, except status, cannot be updated
				$updateDiscoveredValidator->setObjectName($itemName);
				$this->checkPartialValidator($item, $updateDiscoveredValidator, $dbItem);
			}

			$items = $this->extendObjects($this->tableName(), $items, ['name', 'flags']);
		}

		$item_key_parser = new CItemKey();

		foreach ($items as $inum => &$item) {
			$item = $this->clearValues($item);

			$fullItem = $items[$inum];

			if (!check_db_fields($itemDbFields, $item)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			if ($update) {
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
			}

			$host = $dbHosts[$fullItem['hostid']];

			if ($fullItem['type'] == ITEM_TYPE_ZABBIX_ACTIVE) {
				$item['delay_flex'] = '';
				$fullItem['delay_flex'] = '';
			}
			if ($fullItem['value_type'] == ITEM_VALUE_TYPE_STR) {
				$item['delta'] = 0;
			}
			if ($fullItem['value_type'] != ITEM_VALUE_TYPE_UINT64) {
				$item['data_type'] = 0;
			}

			// For non-numeric types, whichever value was entered in trends field, is overwritten to zero.
			if ($fullItem['value_type'] == ITEM_VALUE_TYPE_STR || $fullItem['value_type'] == ITEM_VALUE_TYPE_LOG
					|| $fullItem['value_type'] == ITEM_VALUE_TYPE_TEXT) {
				$item['trends'] = 0;
			}

			// check if the item requires an interface
			$itemInterfaceType = itemTypeInterface($fullItem['type']);
			if ($itemInterfaceType !== false && $host['status'] != HOST_STATUS_TEMPLATE) {
				if (!$fullItem['interfaceid']) {
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
					|| ($fullItem['type'] == ITEM_TYPE_TELNET && strcmp($fullItem['key_'], ZBX_DEFAULT_KEY_TELNET) == 0)
					|| ($fullItem['type'] == ITEM_TYPE_JMX && strcmp($fullItem['key_'], ZBX_DEFAULT_KEY_JMX) == 0)) {
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

			// update interval
			if ($fullItem['type'] != ITEM_TYPE_TRAPPER && $fullItem['type'] != ITEM_TYPE_SNMPTRAP) {
				// delay must be between 0 and 86400, if delay is 0, delay_flex interval must be set.
				if ($fullItem['delay'] < 0 || $fullItem['delay'] > SEC_PER_DAY
					|| ($fullItem['delay'] == 0 && $fullItem['delay_flex'] === '')) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('Item will not be refreshed. Please enter a correct update interval.')
					);
				}

				// Don't parse empty strings, they will not be valid.
				if ($fullItem['delay_flex'] !== '') {
					// Validate item delay_flex string. First check syntax with parser, then validate time ranges.
					$item_delay_flex_parser = new CItemDelayFlexParser($fullItem['delay_flex']);

					if ($item_delay_flex_parser->isValid()) {
						$delay_flex_validator = new CItemDelayFlexValidator();

						if ($delay_flex_validator->validate($item_delay_flex_parser->getIntervals())) {
							// Some valid intervals exist at this point.
							$flexible_intervals = $item_delay_flex_parser->getFlexibleIntervals();

							// If there are no flexible intervals, skip the next check calculation.
							if (!$flexible_intervals) {
								continue;
							}

							$nextCheck = calculateItemNextCheck(0, $fullItem['delay'],
								$item_delay_flex_parser->getFlexibleIntervals($flexible_intervals),
								time()
							);

							if ($nextCheck == ZBX_JAN_2038) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_('Item will not be refreshed. Please enter a correct update interval.')
								);
							}
						}
						else {
							self::exception(ZBX_API_ERROR_PARAMETERS, $delay_flex_validator->getError());
						}
					}
					else {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid interval "%1$s": %2$s.',
							$fullItem['delay_flex'],
							$item_delay_flex_parser->getError())
						);
					}
				}
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

			$this->checkSpecificFields($fullItem);
		}
		unset($item);

		$this->checkExistingItems($items);
	}

	protected function checkSpecificFields(array $item) {
		return true;
	}

	protected function clearValues(array $item) {
		if (isset($item['port']) && $item['port'] != '') {
			$item['port'] = ltrim($item['port'], '0');
			if ($item['port'] == '') {
				$item['port'] = 0;
			}
		}

		if (isset($item['lifetime']) && $item['lifetime'] != '') {
			$item['lifetime'] = ltrim($item['lifetime'], '0');
			if ($item['lifetime'] == '') {
				$item['lifetime'] = 0;
			}
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
	 * @param array $itemType   An item
	 * @param array $interfaces An array of interfaces to choose from
	 *
	 * @return array|boolean    The best matching interface;
	 *							an empty array of no matching interface was found;
	 *							false, if the item does not need an interface
	 */
	public static function findInterfaceForItem(array $item, array $interfaces) {
		$typeInterface = [];
		foreach ($interfaces as $interface) {
			if ($interface['main'] == 1) {
				$typeInterface[$interface['type']] = $interface;
			}
		}

		// find item interface type
		$type = itemTypeInterface($item['type']);

		$matchingInterface = [];

		// the item can use any interface
		if ($type == INTERFACE_TYPE_ANY) {
			$interfaceTypes = [
				INTERFACE_TYPE_AGENT,
				INTERFACE_TYPE_SNMP,
				INTERFACE_TYPE_JMX,
				INTERFACE_TYPE_IPMI
			];
			foreach ($interfaceTypes as $itype) {
				if (isset($typeInterface[$itype])) {
					$matchingInterface = $typeInterface[$itype];
					break;
				}
			}
		}
		// the item uses a specific type of interface
		elseif ($type !== false) {
			$matchingInterface = (isset($typeInterface[$type])) ? $typeInterface[$type] : [];
		}
		// the item does not need an interface
		else {
			$matchingInterface = false;
		}

		return $matchingInterface;
	}

	public function isReadable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get([
			'itemids' => $ids,
			'countOutput' => true
		]);

		return (count($ids) == $count);
	}

	public function isWritable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get([
			'itemids' => $ids,
			'editable' => true,
			'countOutput' => true
		]);

		return (count($ids) == $count);
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
	 * Prepares and returns an array of child items, inherited from items $itemsToInherit on the given hosts.
	 *
	 * @param array      $itemsToInherit
	 * @param array|null $hostIds
	 *
	 * @return array an array of unsaved child items
	 */
	protected function prepareInheritedItems(array $itemsToInherit, array $hostIds = null) {
		// fetch all child hosts
		$chdHosts = API::Host()->get([
			'output' => ['hostid', 'host', 'status'],
			'selectParentTemplates' => ['templateid'],
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'templateids' => zbx_objectValues($itemsToInherit, 'hostid'),
			'hostids' => $hostIds,
			'preservekeys' => true,
			'nopermissions' => true,
			'templated_hosts' => true
		]);
		if (empty($chdHosts)) {
			return [];
		}

		$newItems = [];
		foreach ($chdHosts as $hostId => $host) {
			$templateids = zbx_toHash($host['parentTemplates'], 'templateid');

			// skip items not from parent templates of current host
			$parentItems = [];
			foreach ($itemsToInherit as $inum => $parentItem) {
				if (isset($templateids[$parentItem['hostid']])) {
					$parentItems[$inum] = $parentItem;
				}
			}

			// check existing items to decide insert or update
			$exItems = API::Item()->get([
				'output' => ['itemid', 'type', 'key_', 'flags', 'templateid'],
				'hostids' => $hostId,
				'preservekeys' => true,
				'nopermissions' => true,
				'filter' => ['flags' => null]
			]);

			$exItemsKeys = zbx_toHash($exItems, 'key_');
			$exItemsTpl = zbx_toHash($exItems, 'templateid');

			$itemids_with_application_prototypes = [];

			foreach ($parentItems as $parentItem) {
				if (isset($parentItem['applicationPrototypes']) && is_array($parentItem['applicationPrototypes'])
						&& !array_key_exists('ruleid', $parentItem)) {
					$itemids_with_application_prototypes[$parentItem['itemid']] = true;
				}
			}

			if ($itemids_with_application_prototypes) {
				$discovery_rules = DBfetchArray(DBselect(
					'SELECT id.itemid,id.parent_itemid'.
					' FROM item_discovery id'.
					' WHERE '.dbConditionInt('id.itemid', array_keys($itemids_with_application_prototypes))
				));
				$discovery_rules = zbx_toHash($discovery_rules, 'itemid');
			}

			foreach ($parentItems as $parentItem) {
				$exItem = null;

				// check if an item of a different type with the same key exists
				if (isset($exItemsKeys[$parentItem['key_']])) {
					$exItem = $exItemsKeys[$parentItem['key_']];
					if ($exItem['flags'] != $parentItem['flags']) {
						$this->errorInheritFlags($exItem['flags'], $exItem['key_'], $host['host']);
					}
				}

				// update by templateid
				if (isset($exItemsTpl[$parentItem['itemid']])) {
					$exItem = $exItemsTpl[$parentItem['itemid']];

					if (isset($exItemsKeys[$parentItem['key_']])
						&& !idcmp($exItemsKeys[$parentItem['key_']]['templateid'], $parentItem['itemid'])) {
						self::exception(
							ZBX_API_ERROR_PARAMETERS,
							_params($this->getErrorMsg(self::ERROR_EXISTS), [$parentItem['key_'], $host['host']])
						);
					}
				}

				// update by key
				if (isset($exItemsKeys[$parentItem['key_']])) {
					$exItem = $exItemsKeys[$parentItem['key_']];

					if ($exItem['templateid'] > 0 && !idcmp($exItem['templateid'], $parentItem['itemid'])) {

						self::exception(
							ZBX_API_ERROR_PARAMETERS,
							_params($this->getErrorMsg(self::ERROR_EXISTS_TEMPLATE), [
								$parentItem['key_'],
								$host['host']
							])
						);
					}
				}

				if ($host['status'] == HOST_STATUS_TEMPLATE || !isset($parentItem['type'])) {
					unset($parentItem['interfaceid']);
				}
				elseif ((isset($parentItem['type']) && isset($exItem) && $parentItem['type'] != $exItem['type']) || !isset($exItem)) {
					$interface = self::findInterfaceForItem($parentItem, $host['interfaces']);

					if (!empty($interface)) {
						$parentItem['interfaceid'] = $interface['interfaceid'];
					}
					elseif ($interface !== false) {
						self::exception(
							ZBX_API_ERROR_PARAMETERS,
							_params($this->getErrorMsg(self::ERROR_NO_INTERFACE), [
								$host['host'],
								$parentItem['key_']
							])
						);
					}
				}
				else {
					unset($parentItem['interfaceid']);
				}

				// copying item
				$newItem = $parentItem;
				$newItem['hostid'] = $host['hostid'];
				$newItem['templateid'] = $parentItem['itemid'];

				// setting item application
				if (isset($parentItem['applications'])) {
					$newItem['applications'] = get_same_applications_for_host($parentItem['applications'], $host['hostid']);
				}

				if ($parentItem['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE
						&& array_key_exists('applicationPrototypes', $parentItem)) {

					// Get discovery rule ID for current item prototype, if it is not yet set.
					if (array_key_exists('ruleid', $parentItem)) {
						$discovery_ruleid = $parentItem['ruleid'];
					}
					else {
						$discovery_ruleid = $discovery_rules[$parentItem['itemid']]['parent_itemid'];
					}

					$newItem['applicationPrototypes'] = [];

					$db_application_prototypes = DBfetchArray(DBselect(
						'SELECT ap.application_prototypeid,ap.name'.
						' FROM application_prototype ap'.
						' WHERE ap.itemid='.zbx_dbstr($discovery_ruleid).
							' AND '.dbConditionString('ap.name',
								zbx_objectValues($parentItem['applicationPrototypes'], 'name')
							)
					));

					$db_application_prototypes = zbx_toHash($db_application_prototypes, 'name');

					foreach ($parentItem['applicationPrototypes'] as $application_prototype) {
						$db_application_prototype = $db_application_prototypes[$application_prototype['name']];

						$newItem['applicationPrototypes'][] = [
							'name' => $application_prototype['name'],
							'templateid' => $db_application_prototype['application_prototypeid']
						];
					}
				}

				if ($exItem) {
					$newItem['itemid'] = $exItem['itemid'];
				}
				else {
					unset($newItem['itemid']);
				}
				$newItems[] = $newItem;
			}
		}

		return $newItems;
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
}
