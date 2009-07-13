<?php
/**
 * File containing CItem class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Items
 *
 */
class CItem {

	public static $error;

	/**
	 * Get items data
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @static
	 * @param array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['triggerids']
	 * @param array $options['applicationids']
	 * @param boolean $options['status']
	 * @param boolean $options['templated_items']
	 * @param boolean $options['editable']
	 * @param boolean $options['count']
	 * @param string $options['pattern']
	 * @param int $options['limit']
	 * @param string $options['order']
	 * @return array|int item data as array or false if error
	 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];
		
		$sort_columns = array('itemid'); // allowed columns for sorting

		$sql_parts = array(
			'select' => array('items' => 'i.itemid'),
			'from' => array('items i'),
			'where' => array('i.type<>9'),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'			=> 0,
			'groupids'			=> 0,
			'hostids'			=> 0,
			'itemids'			=> 0,
			'triggerids'		=> 0,
			'applicationids'	=> 0,
			'status'			=> 0,
			'templated_items'	=> 0,
			'editable'			=> 0,
			'nopermissions'		=> 0,
			'extendoutput'		=> 0,
			'count'				=> 0,
			'pattern'			=> '',
			'limit'				=> 0,
			'order'				=> '');

		$options = array_merge($def_options, $options);

// editable + PERMISSION CHECK
		if(defined('ZBX_API_REQUEST')){
			$options['nopermissions'] = false;
		}
		
		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;
			
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['r'] = 'rights r';
			$sql_parts['from']['ug'] = 'users_groups ug';
			$sql_parts['where'][] = 'hg.hostid=i.hostid';
			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS( '.
								' SELECT hgg.groupid '.
								' FROM hosts_groups hgg, rights rr, users_groups gg '.
								' WHERE hgg.hostid=hg.hostid '.
									' AND rr.id=hgg.groupid '.
									' AND rr.groupid=gg.usrgrpid '.
									' AND gg.userid='.$userid.
									' AND rr.permission<'.$permission.')';									
		}

// nodeids
		$nodeids = $options['nodeids'] ? $options['nodeids'] : get_current_nodeid(false);
		
// groupids
		if($options['groupids'] != 0){
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where'][] = 'hg.hostid=i.hostid';
			$sql_parts['from']['hg'] = 'hosts_groups hg';
		}

// hostids
		if($options['hostids'] != 0){
			zbx_value2array($options['hostids']);
			$sql_parts['where'][] = DBcondition('i.hostid', $options['hostids']);
		}

// itemids
		if($options['itemids'] != 0){
			$sql_parts['where'][] = DBcondition('i.itemid', $options['itemids']);
		}

// triggerids
		if($options['triggerids'] != 0){
			$sql_parts['where'][] = DBcondition('f.triggerid', $options['triggerids']);
			$sql_parts['where'][] = 'i.itemid=f.itemid';
			$sql_parts['from'][] = 'functions f';
		}

// applicationids
		if($options['applicationids'] != 0){
			$sql_parts['where'][] = DBcondition('a.applicationid', $options['applicationids']);
			$sql_parts['where'][] = 'i.hostid=a.hostid';
			$sql_parts['from'][] = 'applications a';
		}

// status
		if($options['status'] != 0){
			$sql_parts['where'][] = 'i.status='.$options['status'];
		}

// templated_items
		if($options['templated_items'] != 0){
			$sql_parts['where'][] = 'i.templateid<>0';
		}

// extendoutput
		if($options['extendoutput'] != 0){
			$sql_parts['select']['items'] = 'i.*';
		}

// count
		if($options['count'] != 0){
			$sql_parts['select']['items'] = 'count(i.itemid) as count';
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(i.description) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
		}

// order
		// restrict not allowed columns for sorting
		$options['order'] = in_array($options['order'], $sort_columns) ? $options['order'] : '';
		
		if(!zbx_empty($options['order'])){
			$sql_parts['order'][] = 'i.'.$options['order'];
			if(!str_in_array('i.'.$options['order'], $sql_parts['select'])) $sql_parts['select'][] = 'i.'.$options['order'];
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//----------

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);
	
		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);			
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('i.itemid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($item = DBfetch($res)){
			if($options['count'])
				$result = $item;
			else
				if(!isset($options['extendoutput'])){
					$result[$item['itemid']] = $item['itemid'];
				}
				else{
					if(!isset($result[$item['itemid']])) 
						$result[$item['itemid']] = array();
					
					$result[$item['itemid']] += $item;
				}
				
				
		}

	return $result;
	}

	/**
	 * Gets all item data from DB by itemid
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @static
	 * @param int $item_data
	 * @param int $item_data['itemid']
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
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @static
	 * @param array $item_data
	 * @param array $item_data['key_']
	 * @param array $item_data['host']
	 * @param array $item_data['hostid'] OPTIONAL
	 * @return int|boolean
	 */
	public static function getId($item_data){

		if(isset($item_data['host'])) {
			$host = $item_data['host'];
		}
		else {
			$host = CHost::getById(array('hostid' => $item_data['hostid']));
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
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * Input array $items has following structure and default values :
	 * <code>
	 * array( array(
	 * *'description'			=> *,
	 * *'key_'				=> *,
	 * *'hostid'				=> *,
	 * 'delay'				=> 60,
	 * 'history'				=> 7,
	 * 'status'				=> ITEM_STATUS_ACTIVE,
	 * 'type'				=> ITEM_TYPE_ZABBIX,
	 * 'snmp_community'			=> '',
	 * 'snmp_oid'				=> '',
	 * 'value_type'				=> ITEM_VALUE_TYPE_STR,
	 * 'data_type'				=> ITEM_DATA_TYPE_DECIMAL,
	 * 'trapper_hosts'			=> 'localhost',
	 * 'snmp_port'				=> 161,
	 * 'units'				=> '',
	 * 'multiplier'				=> 0,
	 * 'delta'				=> 0,
	 * 'snmpv3_securityname'		=> '',
	 * 'snmpv3_securitylevel'		=> 0,
	 * 'snmpv3_authpassphrase'		=> '',
	 * 'snmpv3_privpassphrase'		=> '',
	 * 'formula'				=> 0,
	 * 'trends'				=> 365,
	 * 'logtimefmt'				=> '',
	 * 'valuemapid'				=> 0,
	 * 'delay_flex'				=> '',
	 * 'params'				=> '',
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
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
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
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
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
