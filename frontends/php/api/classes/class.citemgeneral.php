<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
?>
<?php
/**
 * @package API
 */

abstract class CItemGeneral extends CZBXAPI{

	protected $fieldRules;

	abstract public function get($options=array());

	public function __construct(){
// template - if templated item, value is taken from template item, cannot be changed on host
// system - values should not be updated
// host - value should be null for template items
		$this->fieldRules = array(
			'type'					=> array('template' => 1),
			'snmp_community'		=> array(),
			'snmp_oid'				=> array('template' => 1),
			'hostid'				=> array(),
			'name' 					=> array('template' => 1),
			'description' 			=> array(),
			'key_'					=> array('template' => 1),
			'delay'					=> array(),
			'history'				=> array(),
			'trends'				=> array(),
			'lastvalue'				=> array('system' => 1),
			'lastclock'				=> array('system' => 1),
			'prevvalue'				=> array('system' => 1),
			'status'				=> array(),
			'value_type'			=> array('template' => 1),
			'trapper_hosts'			=> array(),
			'units'					=> array('template' => 1),
			'multiplier'			=> array('template' => 1),
			'delta'					=> array(),
			'prevorgvalue'			=> array('system' => 1),
			'snmpv3_securityname'	=> array(),
			'snmpv3_securitylevel'	=> array(),
			'snmpv3_authpassphrase'	=> array(),
			'snmpv3_privpassphrase'	=> array(),
			'formula'				=> array('template' => 1),
			'error'					=> array('system' => 1),
			'lastlogsize'			=> array('system' => 1),
			'logtimefmt'			=> array(),
			'templateid'			=> array('system' => 1),
			'valuemapid'			=> array('template' => 1),
			'delay_flex'			=> array(),
			'params'				=> array(),
			'ipmi_sensor'			=> array('template' => 1),
			'data_type'				=> array('template' => 1),
			'authtype'				=> array(),
			'username'				=> array(),
			'password'				=> array(),
			'publickey'				=> array(),
			'privatekey'			=> array(),
			'mtime'					=> array('system' => 1),
			'lastns'				=> array('system' => 1),
			'flags'					=> array('system' => 1),
			'filter'				=> array(),
			'interfaceid'			=> array('host' => 1),
			'port'					=> array(),
			'inventory_link'		=> array(),
		);
	}

	/**
	 * Check items data.
	 *
	 * @throw APIException
	 *
	 * @param array $items passed by reference
	 * @param bool  $update
	 *
	 * @return void
	 */
	protected function checkInput(array &$items, $update = false) {
		// permissions
		if ($update) {
			$item_db_fields = array('itemid' => null);

			$dbItemsFields = array('itemid', 'templateid');
			foreach ($this->fieldRules as $field => $rule) {
				if (!isset($rule['system'])) {
					$dbItemsFields[] = $field;
				}
			}

			$dbItems = $this->get(array(
				'output' => $dbItemsFields,
				'itemids' => zbx_objectValues($items, 'itemid'),
				'editable' => true,
				'preservekeys' => true
			));

			$dbHosts = API::Host()->get(array(
				'output' => array('hostid', 'host', 'status'),
				'hostids' => zbx_objectValues($dbItems, 'hostid'),
				'templated_hosts' => true,
				'editable' => true,
				'preservekeys' => true
			));
		}
		else {
			$item_db_fields = array('name' => null, 'key_' => null, 'hostid' => null, 'type' => null,
				'value_type' => null, 'delay' => '0', 'delay_flex' => ''
			);

			$dbHosts = API::Host()->get(array(
				'output' => array('hostid', 'host', 'status'),
				'hostids' => zbx_objectValues($items, 'hostid'),
				'templated_hosts' => true,
				'editable' => true,
				'preservekeys' => true
			));
		}

		// interfaces
		$interfaces = API::HostInterface()->get(array(
			'output' => array('interfaceid', 'hostid', 'type'),
			'hostids' => zbx_objectValues($dbHosts, 'hostid'),
			'nopermissions' => true,
			'preservekeys' => true
		));

		foreach ($items as $inum => &$item) {
			$fullItem = $items[$inum];

			if (!check_db_fields($item_db_fields, $item)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_ARGUMENTS_PASSED_TO_FUNCTION);
			}

			if ($update) {
				if (!isset($dbItems[$item['itemid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
				}

				check_db_fields($dbItems[$item['itemid']], $fullItem);

				if ($dbHosts[$fullItem['hostid']]['status'] == HOST_STATUS_TEMPLATE) {
					unset($item['interfaceid']);
				}

				// apply rules
				foreach ($this->fieldRules as $field => $rules) {
					if ((0 != $fullItem['templateid'] && isset($rules['template'])) || isset($rules['system'])) {
						unset($item[$field]);
					}
				}

				if (!isset($item['key_'])) {
					$item['key_'] = $fullItem['key_'];
				}

				if (!isset($item['hostid'])) {
					$item['hostid'] = $fullItem['hostid'];
				}
			}
			else {
				if (!isset($dbHosts[$item['hostid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
				}

				if (!isset($item['interfaceid'])
						&& self::itemTypeInterface($item['type'])
						&& $dbHosts[$item['hostid']]['status'] != HOST_STATUS_TEMPLATE
						&& $fullItem['flags'] != ZBX_FLAG_DISCOVERY_CHILD) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No interface for item.'));
				}
			}

			if ($fullItem['type'] == ITEM_TYPE_ZABBIX_ACTIVE) {
				$item['delay_flex'] = '';
			}

			if ($fullItem['value_type'] == ITEM_VALUE_TYPE_STR) {
				$item['delta'] = 0;
			}

			if ($fullItem['value_type'] != ITEM_VALUE_TYPE_UINT64) {
				$item['data_type'] = 0;
			}

			// interface
			$itemInterfaceType = self::itemTypeInterface($fullItem['type']);
			if ($itemInterfaceType !== false
					&& $dbHosts[$fullItem['hostid']]['status'] != HOST_STATUS_TEMPLATE
					&& $fullItem['flags'] != ZBX_FLAG_DISCOVERY_CHILD
					&& isset($item['interfaceid']) && $item['interfaceid']) {
				if (!isset($interfaces[$fullItem['interfaceid']])
						|| bccomp($interfaces[$fullItem['interfaceid']]['hostid'], $fullItem['hostid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Item uses host interface from non-parent host.'));
				}
				if ($itemInterfaceType !== INTERFACE_TYPE_ANY
						&& $interfaces[$fullItem['interfaceid']]['type'] != $itemInterfaceType) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Item uses incorrect interface type.'));
				}
			}
			else {
				$item['interfaceid'] = 0;
			}

			// item key
			if (($fullItem['type'] == ITEM_TYPE_DB_MONITOR && strcmp($fullItem['key_'], ZBX_DEFAULT_KEY_DB_MONITOR) == 0)
					|| ($fullItem['type'] == ITEM_TYPE_SSH && strcmp($fullItem['key_'], ZBX_DEFAULT_KEY_SSH) == 0)
					|| ($fullItem['type'] == ITEM_TYPE_TELNET && strcmp($fullItem['key_'], ZBX_DEFAULT_KEY_TELNET) == 0)
					|| ($fullItem['type'] == ITEM_TYPE_JMX && strcmp($fullItem['key_'], ZBX_DEFAULT_KEY_JMX) == 0)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Check the key, please. Default example was passed.'));
			}

			$itemKey = new CItemKey($fullItem['key_']);
			if (!$itemKey->isValid()) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Error in item key: "%s".', $itemKey->getError()));
			}

			if ($fullItem['type'] == ITEM_TYPE_AGGREGATE) {
				$params = $itemKey->getParameters();

				if (!str_in_array($itemKey->getKeyId(), array('grpmax', 'grpmin', 'grpsum', 'grpavg'))
						|| count($params) != 4
						|| !str_in_array($params[2], array('last', 'min', 'max', 'avg', 'sum', 'count'))) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Key "%1$s" does not match <grpmax|grpmin|grpsum|grpavg>["Host group(s)", "Item key",'.
						' "<last|min|max|avg|sum|count>", "parameter"].', $itemKey->getKeyId()));
				}
			}

			if ($fullItem['type'] == ITEM_TYPE_SNMPTRAP
					&& strcmp($fullItem['key_'], 'snmptrap.fallback') != 0
					&& strcmp($itemKey->getKeyId(), 'snmptrap') != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('SNMP trap key is invalid'));
			}

			// type of information
			if ($fullItem['type'] == ITEM_TYPE_AGGREGATE && $fullItem['value_type'] != ITEM_VALUE_TYPE_FLOAT) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Value type must be Float for aggregate items.'));
			}

			if ($fullItem['value_type'] != ITEM_VALUE_TYPE_LOG
					&& str_in_array($itemKey->getKeyId(), array('log', 'logrt', 'eventlog'))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Type of information must be Log for log key.'));
			}

			// update interval
			if ($fullItem['type'] != ITEM_TYPE_TRAPPER && $fullItem['type'] != ITEM_TYPE_SNMPTRAP) {
				$res = calculate_item_nextcheck(0, 0, $fullItem['type'], $fullItem['delay'], $fullItem['delay_flex'], time());
				if ($res['delay'] == SEC_PER_YEAR) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('Item will not be refreshed. Please enter a correct update interval.'));
				}
			}

			// SNMP port
			if (isset($fullItem['port']) && !validatePortNumber($fullItem['port'], true, true)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Item "%1$s:%2$s" has invalid port: "%3$s".', $fullItem['name'], $fullItem['key_'], $fullItem['port']));
			}

		}
		unset($item);
	}

	protected function errorInheritFlags($flag, $key, $host){
		switch($flag){
			case ZBX_FLAG_DISCOVERY_NORMAL:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on host "%2$s" as an item.', $key, $host));
				break;
			case ZBX_FLAG_DISCOVERY:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on host "%2$s" as a discovery rule.', $key, $host));
				break;
			case ZBX_FLAG_DISCOVERY_CHILD:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on host "%2$s" as an item prototype.', $key, $host));
				break;
			case ZBX_FLAG_DISCOVERY_CREATED:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on host "%2$s" as an item created from item prototype.', $key, $host));
				break;
			default:
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%1$s" already exists on host "%2$s" as unknown item element.', $key, $host));
		}
	}

	public static function itemTypeInterface($itemType){
		switch($itemType){
			case ITEM_TYPE_SNMPV1:
			case ITEM_TYPE_SNMPV2C:
			case ITEM_TYPE_SNMPV3:
			case ITEM_TYPE_SNMPTRAP:
				return INTERFACE_TYPE_SNMP;
			case ITEM_TYPE_IPMI:
				return INTERFACE_TYPE_IPMI;
			case ITEM_TYPE_ZABBIX:
				return INTERFACE_TYPE_AGENT;
			case ITEM_TYPE_SIMPLE:
			case ITEM_TYPE_EXTERNAL:
			case ITEM_TYPE_SSH:
			case ITEM_TYPE_TELNET:
				return INTERFACE_TYPE_ANY;
			case ITEM_TYPE_JMX:
				return INTERFACE_TYPE_JMX;
			default:
				return false;
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
		$typeInterface = array();
		foreach ($interfaces as $interface) {
			if ($interface['main'] == 1) {
				$typeInterface[$interface['type']] = $interface;
			}
		}

		// find item interface type
		$type = self::itemTypeInterface($item['type']);

		$matchingInterface = array();

		// the item can use any interface
		if ($type == INTERFACE_TYPE_ANY) {
			$interfaceTypes = array(
				INTERFACE_TYPE_AGENT,
				INTERFACE_TYPE_SNMP,
				INTERFACE_TYPE_JMX,
				INTERFACE_TYPE_IPMI
			);
			foreach ($interfaceTypes as $itype) {
				if (isset($typeInterface[$itype])) {
					$matchingInterface = $typeInterface[$itype];
					break;
				}
			}
		}
		// the item uses a specific type of interface
		elseif ($type !== false) {
			$matchingInterface = (isset($typeInterface[$type])) ? $typeInterface[$type] : array();
		}
		// the item does not need an interface
		else {
			$matchingInterface = false;
		}

		return $matchingInterface;
	}


	public function isReadable($ids){
		if(!is_array($ids)) return false;
		if(empty($ids)) return true;

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'itemids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	public function isWritable($ids){
		if(!is_array($ids)) return false;
		if(empty($ids)) return true;

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'itemids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

}
?>
