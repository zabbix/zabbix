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

class CItemGeneral extends CZBXAPI{

	protected $fieldsToUpdateFromTemplate;

	public function __construct(){
		$this->fieldsToUpdateFromTemplate = array(
			'itemid' => 1,
			'type' => 1,
			'snmp_oid' => 1,
			'hostid' => 1,
			'description' => 1,
			'key_' => 1,
			'value_type' => 1,
			'units' => 1,
			'multiplier' => 1,
			'formula' => 1,
			'logtimefmt' => 1,
			'templateid' => 1,
			'ipmi_sensor' => 1,
			'data_type' => 1,
			'flags' => 1,
			'filter' => 1,
		);
	}

	public function errorInheritFlags($flag, $key, $host){
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

	public function itemTypeInterface($itemType){
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
			default:
				return false;
		}
	}
}
?>
