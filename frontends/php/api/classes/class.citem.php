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
	 * Get items data 
	 *
	 * <code>
	 * $options = array(
	 *	array 'itemids' 			=> array(),
	 *	array 'hostids' 			=> array(),
	 *	array 'groupids' 			=> array(),
	 *	array 'triggerids' 			=> array(),
	 *	array 'applicationids' 		=> array(),
	 *	boolean 'status' 			=> false,
	 *	boolean 'templated_items' 	=> false,
	 *	boolean 'count'				=> false,
	 *	string 'pattern'			=> '',
	 *	int 'limit' 				=> null,
	 *	string 'order' 				=> ''
	 * );
	 * </code>
	 *
	 * @static
	 * @param array $options 
	 * @return array|int item data as array or false if error
	 */
	public static function get($options=array()){

		$result = array();
		
		$sort_columns = array('itemid'); // allowed columns for sorting

		$sql_parts = array(
			'select' => array('i.itemid, i.type, i.description, i.key_, i.status'),
			'from' => array('items i'),
			'where' => array('i.type<>9'),
			'order' => array(),
			'limit' => null,
			);
		
		$def_options = array(
			'itemids' 			=> array(),
			'hostids' 			=> array(),
			'groupids' 			=> array(),
			'triggerids' 		=> array(),
			'applicationids' 	=> array(),
			'status' 			=> false,
			'templated_items' 	=> false,
			'count'				=> false,
			'pattern'			=> '',
			'limit' 			=> null,
			'order' 			=> ''
		);
		
		$options = array_merge($def_options, $options);

		// restrict not allowed columns for sorting 
		$options['order'] = in_array($options['order'], $sort_columns) ? $options['order'] : '';
		
// count
		if($options['count']){
			$sql_parts['select'] = array('count(i.itemid) as count');
		}
// itemids
		if($options['itemids']){
			$sql_parts['where'][] = DBcondition('i.itemid', $options['itemids']);
		}
// hostids
		if($options['hostids']){
			$sql_parts['where'][] = DBcondition('i.hostid', $options['hostids']);
		}
// groupids
		if($options['groupids']){
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where'][] = 'hg.hostid=i.hostid';			
			$sql_parts['from'][] = 'hosts_groups hg';	
		}
// triggerids
		if($options['triggerids']){
			$sql_parts['where'][] = DBcondition('f.triggerid', $options['triggerids']);
			$sql_parts['where'][] = 'i.itemid=f.itemid';			
			$sql_parts['from'][] = 'functions f';	
		}
// applicationids
		if($options['applicationids']){
			$sql_parts['where'][] = DBcondition('a.applicationid', $options['applicationids']);
			$sql_parts['where'][] = 'i.hostid=a.hostid';			
			$sql_parts['from'][] = 'applications a';	
		}
// status
		if($options['status'] !== false){
			$sql_parts['where'][] = 'i.status='.$options['status'];
		}	
// templated_items
		if($options['templated_items']){
			$sql_parts['where'][] = 'i.templateid=0';				
		}
// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' i.description LIKE '.zbx_dbstr('%'.$options['pattern'].'%');
		}
// order
		if(!zbx_empty($options['order'])){
			$sql_parts['order'][] = 'i.'.$options['order'];
		}		
// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
	
	
		$sql_select = implode(',', $sql_parts['select']);
		$sql_from = implode(',', $sql_parts['from']);
		$sql_where = implode(' AND ', $sql_parts['where']);
		$sql_order = zbx_empty($options['order']) ? '' : ' ORDER BY '.implode(',', $sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		
		$sql = 'SELECT DISTINCT '.$sql_select.
			' FROM '.$sql_from.
			' WHERE '.$sql_where.
			$sql_order; 
		$db_res = DBselect($sql, $sql_limit);
		
		while($item = DBfetch($db_res)){
			if($options['count']) 
				$result = $item;
			else 
				$result[$item['itemid']] = $item;
		}
		
	return $result;
	}
	
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