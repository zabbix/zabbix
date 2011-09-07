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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
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

	protected function checkInput(&$items, $update=false){
		// interfaces
		$interfaceids = zbx_objectValues($items, 'interfaceid');
		$interfaces = API::HostInterface()->get(array(
			'output' => array('interfaceid', 'hostid'),
			'interfaceids' => $interfaceids,
			'nopermissions' => true,
			'preservekeys' => true
		));

		// permissions
		if($update){
			$item_db_fields = array('itemid' => null);

			$dbItems = $this->get(array(
				'output' => API_OUTPUT_EXTEND,
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
		else{
			$item_db_fields = array('name' => null, 'key_' => null, 'hostid' => null, 'type' => null);

			$dbHosts = API::Host()->get(array(
				'output' => array('hostid', 'host', 'status'),
				'hostids' => zbx_objectValues($items, 'hostid'),
				'templated_hosts' => true,
				'editable' => true,
				'preservekeys' => true
			));
		}

		foreach($items as $inum => &$item){
			$fullItem = $items[$inum];

			if(!check_db_fields($item_db_fields, $item)){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_ARGUMENTS_PASSED_TO_FUNCTION);
			}

			if($update){
				check_db_fields($dbItems[$item['itemid']], $fullItem);

				if(!isset($dbItems[$item['itemid']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);


				if($dbHosts[$dbItems[$item['itemid']]['hostid']]['status'] == HOST_STATUS_TEMPLATE)
					unset($item['interfaceid']);

// apply rules
				foreach($this->fieldRules as $field => $rules){
					if(((0 != $fullItem['templateid']) && isset($rules['template'])) || isset($rules['system'])){
						unset($item[$field]);
					}
				}

				if(!isset($item['key_'])) $item['key_'] = $dbItems[$item['itemid']]['key_'];
				if(!isset($item['hostid'])) $item['hostid'] = $dbItems[$item['itemid']]['hostid'];
			}
			else{
				if(!isset($dbHosts[$item['hostid']]))
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);

				if(!isset($item['interfaceid']) && $this->itemTypeInterface($item['type'])
					&& ($dbHosts[$item['hostid']]['status'] != HOST_STATUS_TEMPLATE)
					&& ($fullItem['flags'] != ZBX_FLAG_DISCOVERY_CHILD)
				){
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No interface for item.'));
				}
			}

			if($this->itemTypeInterface($fullItem['type']) && ($dbHosts[$item['hostid']]['status'] != HOST_STATUS_TEMPLATE)
				&& ($fullItem['flags'] != ZBX_FLAG_DISCOVERY_CHILD)
			){
				if(isset($item['interfaceid'])){
					if(!isset($interfaces[$item['interfaceid']]) || (bccomp($interfaces[$item['interfaceid']]['hostid'],$item['hostid'])!=0))
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item uses Host interface from non parent host'));
				}
			}
			else{
				$item['interfaceid'] = 0;
			}

			if((isset($item['port']) && !zbx_empty($item['port']))
				&& !((zbx_ctype_digit($item['port']) && ($item['port']>0) && ($item['port']<65535))
				|| preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/u', $item['port']))
			){
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Item "%1$s:%2$s" has invalid port: "%3$s".', $fullItem['name'], $fullItem['key_'], $item['port']));
			}

			if(isset($item['value_type'])){
				if($item['value_type'] == ITEM_VALUE_TYPE_STR) $item['delta'] = 0;
				if($item['value_type'] != ITEM_VALUE_TYPE_UINT64) $item['data_type'] = 0;
			}

			if(isset($item['key_'])){
				$itemKey = new CItemKey($item['key_']);
				if(!$itemKey->isValid()){
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Error in item key: %s', $itemKey->getError()));
				}

				if(isset($item['type'])){
					if(($item['type'] == ITEM_TYPE_DB_MONITOR && $item['key_'] == 'db.odbc.select[<unique short description>]') ||
						($item['type'] == ITEM_TYPE_SSH && $item['key_'] == 'ssh.run[<unique short description>,<ip>,<port>,<encoding>]') ||
						($item['type'] == ITEM_TYPE_TELNET && $item['key_'] == 'telnet.run[<unique short description>,<ip>,<port>,<encoding>]') ||
						($item['type'] == ITEM_TYPE_JMX && $item['key_'] == 'jmx[<object name>,<attribute name>]'))
					{
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Check the key, please. Default example was passed.'));
					}

					if(isset($item['delay']) && isset($item['delay_flex'])){
						$res = calculate_item_nextcheck(0, 0, $item['type'], $item['delay'], $item['delay_flex'], time());
						if($res['delay'] == SEC_PER_YEAR && $item['type'] != ITEM_TYPE_ZABBIX_ACTIVE && $item['type'] != ITEM_TYPE_TRAPPER){
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Item will not be refreshed. Please enter a correct update interval.'));
						}
					}
				}

				if($fullItem['type'] == ITEM_TYPE_AGGREGATE){
					// grpfunc['group','key','itemfunc','numeric param']
					if(!preg_match('/^(grpmax|grpmin|grpsum|grpavg)(\[\"(.*)\"\,\"(.*)\"\,\"(last|min|max|avg|sum|count)\"\,\"[0-9]+\"\])$/ui', $item['key_'], $arr))
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Key does not match <grpmax|grpmin|grpsum|grpavg>["group","key","<last|min|max|avg|sum|count>","numeric param"].'));
				}

				if(isset($item['value_type'])){
					if(preg_match('/^(log|logrt|eventlog)\[/', $item['key_']) && ($item['value_type'] != ITEM_VALUE_TYPE_LOG)){
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Type of information must be Log for log key.'));
					}

					if(isset($item['type']) && ($item['type'] == ITEM_TYPE_AGGREGATE) && ($item['value_type'] != ITEM_VALUE_TYPE_FLOAT)){
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Value type must be Float for aggregate items.'));
					}
				}
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

	protected function itemTypeInterface($itemType){
		switch($itemType){
			case ITEM_TYPE_SNMPV1:
			case ITEM_TYPE_SNMPV2C:
			case ITEM_TYPE_SNMPV3:
				return INTERFACE_TYPE_SNMP;
			case ITEM_TYPE_IPMI:
				return INTERFACE_TYPE_IPMI;
			case ITEM_TYPE_ZABBIX:
			case ITEM_TYPE_SIMPLE:
			case ITEM_TYPE_EXTERNAL:
			case ITEM_TYPE_DB_MONITOR:
			case ITEM_TYPE_SSH:
			case ITEM_TYPE_TELNET:
				return INTERFACE_TYPE_AGENT;
			case ITEM_TYPE_JMX:
				return INTERFACE_TYPE_JMX;
			default:
				return false;
		}
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
