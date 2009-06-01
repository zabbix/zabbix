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

	public static $error;

	/**
	 * Gets all item data from DB by itemid
	 *
	 * @static
	 * @param int $item_data 
	 * @return array|boolean item data || false if error
	 */
	public static function getById($item_data){
		$item = get_item_by_itemid($item_data['itemid']);
		$result = $item ? true : false;
		if($result)
			return $item;
		else{
			self::$error = array('error' => ZBX_API_ERROR_NO_HOST, 'data' => 'Item with id: '.$itemid.' doesn\'t exists.');
			return false;
		}
	}
	
	/**
	 * Get itemid by host.name and item.key
	 *
	 * <code>
	 * $item_data = array(
	 * 	*string 'key_' => 'item key',
	 * 	*string 'host' => 'host name',
	 * 	*string 'hostid' => 'hostid'
	 * );
	 * </code>
	 * 
	 *
	 * @static
	 * @param array $item_data
	 * @return int|boolean 
	 */
	public static function getId($item_data){
		
		if(isset($item_data['host'])) {
			$host = $item_data['host'];
		}
		else {
			$host = CHost::getById(array($item_data['hostid']));
			$host = $host['host'];
		}
		
		$item = get_item_by_key($item_data['key_'], $host);
				
		$result = $item ? true : false;
		if($result)
			return $item['itemid'];
		else{
			self::$error = array('error' => ZBX_API_ERROR_NO_HOST, 'data' => 'Item doesn\'t exists.');
			return false;		
		}
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
	 * <code>
	 * array( array(
	 * *'description'			=> *,
	 * *'key_'					=> *,
	 * *'hostid'				=> *,
	 * 'delay'					=> 60,
	 * 'history'				=> 7,
	 * 'status'					=> ITEM_STATUS_ACTIVE,
	 * 'type'					=> ITEM_TYPE_ZABBIX,
	 * 'snmp_community'			=> '',
	 * 'snmp_oid'				=> '',
	 * 'value_type'				=> ITEM_VALUE_TYPE_STR,
	 * 'data_type'				=> ITEM_DATA_TYPE_DECIMAL,
	 * 'trapper_hosts'			=> 'localhost',
	 * 'snmp_port'				=> 161,
	 * 'units'					=> '',
	 * 'multiplier'				=> 0,
	 * 'delta'					=> 0,
	 * 'snmpv3_securityname'	=> '',
	 * 'snmpv3_securitylevel'	=> 0,
	 * 'snmpv3_authpassphrase'	=> '',
	 * 'snmpv3_privpassphrase'	=> '',
	 * 'formula'					=> 0,
	 * 'trends'					=> 365,
	 * 'logtimefmt'				=> '',
	 * 'valuemapid'				=> 0,
	 * 'delay_flex'				=> '',
	 * 'params'					=> '',
	 * 'ipmi_sensor'			=> '',
	 * 'applications'			=> array(),
	 * 'templateid'				=> 0
	 * ), ...);
	 * </code>
	 *
	 * @static
	 * @param array $items multidimensional array with items data
	 * @return array|boolean 
	 */
	public static function add($items){
		$itemids = array();
		DBstart(false);
		
		$result = false;
		foreach($items as $item){
			$result = add_item($item);
			if(!$result) break;
			$itemids['result'] = $result;
		}
		
		$result = DBend($result);

		if($result)
			return $itemids;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
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
		$itemids = array();
		DBstart(false);
		foreach($items as $item){
			$result = update_item($item['itemid'], $item);
			if(!$result) break;
			$itemids[$result] = $result;
		}	
		$result = DBend($result);
		
		if($result)
			return $itemids;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Delete items
	 *
	 * @static
	 * @param array $itemids 
	 * @return array|boolean
	 */	
	public static function delete($itemids){
		$result = delete_item($itemids);	
		if($result)
			return $itemids;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
}
?>