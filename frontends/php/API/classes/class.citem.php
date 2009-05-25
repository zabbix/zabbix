<?php
/**
 * File containing Item class for API.
 * @package API
 */
/**
 * Class containing methods for operations with items
 *
 */
class CItem {

	/**
	 * Gets all item data from DB by itemid
	 *
	 * @static
	 * @param int $itemid 
	 * @return array|boolean item data || false if error
	 */
	public static function getById($itemid){
		return get_item_by_itemid($itemid);
	}
	
	/**
	 * Get itemid by host.name and item.key
	 *
	 * $item_data = array(
	 * + string 'key_' => 'item key',
	 * + string 'host' => 'host name',
	 * + string 'hostid' => 'hostid'
	 * );
	 *
	 * @static
	 * @param array $item_data
	 * @return int|boolean 
	 */
	public static function getId($item_data){
	
		if(!isset($item_data['host']) && !isset($item_data['hostid'])) {
			return false;
		}
		
		if(isset($item_data['host']) {
			$host = $item_data['host'];
		}
		else {
			$host = CHost::getById($item_data['hostid']);
			$host = $host['host'];
		}
		
		$item = get_item_by_key($item_data['key_'], $host);
		
		return $item ? $item['itemid'] : false;	
	}
	
 	// /**
	 // * Get itemid by host.hostid and item.key
	 // *
	 // * @static
	 // * @param string $hostid
	 // * @param string $itemkey
	 // * @return int|boolean 
	 // */
	// public static function getIdByHostId($item_data$hostid, $itemkey){
	
		// $sql = 'SELECT DISTINCT i.itemid '.
				// ' FROM items i '.
				// ' WHERE i.hostid='.$hostid.' AND i.key_='.zbx_dbstr($itemkey);
		// $item = DBfetch(DBselect($sql));
			
		// return $item ? $item['itemid'] : false;	
	// }
	
	/** 
	 * Add item
	 *
	 * 
	 * Input array $items has following structure and default values :
	 * ( fields with value * are mandatory )
	 * array( array(
	 * + *'description'				=> *,
	 * + *'key_'					=> *,
	 * + *'hostid'					=> *,
	 * + 'delay'					=> 60,
	 * + 'history'					=> 7,
	 * + 'status'					=> ITEM_STATUS_ACTIVE,
	 * + 'type'						=> ITEM_TYPE_ZABBIX,
	 * + 'snmp_community'			=> '',
	 * + 'snmp_oid'					=> '',
	 * + 'value_type'				=> ITEM_VALUE_TYPE_STR,
	 * + 'data_type'				=> ITEM_DATA_TYPE_DECIMAL,
	 * + 'trapper_hosts'			=> 'localhost',
	 * + 'snmp_port'				=> 161,
	 * + 'units'					=> '',
	 * + 'multiplier'				=> 0,
	 * + 'delta'					=> 0,
	 * + 'snmpv3_securityname'		=> '',
	 * + 'snmpv3_securitylevel'		=> 0,
	 * + 'snmpv3_authpassphrase'	=> '',
	 * + 'snmpv3_privpassphrase'	=> '',
	 * + 'formula'					=> 0,
	 * + 'trends'					=> 365,
	 * + 'logtimefmt'				=> '',
	 * + 'valuemapid'				=> 0,
	 * + 'delay_flex'				=> '',
	 * + 'params'					=> '',
	 * + 'ipmi_sensor'				=> '',
	 * + 'applications'				=> array(),
	 * + 'templateid'				=> 0
	 * ), ...);
	 *
	 * @static
	 * @param array $items multidimensional array with items data
	 * @return boolean 
	 */
	public static function add($items){
		
		DBstart(false);
		
		$result = false;
		foreach($items as $item){
			$result = add_item($item);
			if(!$result) break;
		}
		
		$result = DBend($result);
		return $result;
	}
	
	/**
	 * Update item
	 *
	 * @static
	 * @param array $items multidimensional array with items data
	 * @return boolean
	 */
	public static function update($items){
		
		$result = false;
		
		DBstart(false);
		foreach($items as $item){
			$result = update_item($item['itemid'], $item);
			if(!$result) break;
		}	
		$result = DBend($result);
		
		return $result;
	}
	
	/**
	 * Delete items
	 *
	 * @static
	 * @param array $itemids 
	 * @return boolean
	 */	
	public static function delete($itemids){
		return delete_item($itemids);	
	}
	
}
?>