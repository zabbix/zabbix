<?php
/**
 * File containing host class for API.
 * @package API
 */
/**
 * Class containing methods for operations with hosts
 */
class CHost {

	public static $error;

	/**
	 * Get host data 
	 *
	 * <code>
	 * $def_options = array(
	 * 	string 'nodeid' 					=> 'node ID,
	 * 	array 'groupids' 					=> array(groupid1, groupid2, ...),
	 * 	array 'hostids' 					=> array(hostid1, hostid2, ...),
	 * 	boolean 'monitored_hosts'			=> 'only monitored hosts',
	 *	boolean 'templated_hosts'			=> 'include templates in result',
	 * 	boolean 'with_items' 				=> 'only with items',
	 * 	boolean 'with_monitored_items' 		=> 'only with monitored items',
	 * 	boolean 'with_historical_items'		=> 'only with historical items',
	 * 	boolean 'with_triggers' 			=> 'only with triggers',
	 * 	boolean 'with_monitored_triggers'	=> 'only with monitores triggers',
	 * 	boolean 'with_httptests' 			=> 'only with http tests',
	 * 	boolean 'with_monitored_httptests'	=> 'only with monitores http tests',
	 * 	boolean 'with_graphs'				=> 'only with graphs'
	 *  string  'pattern'					=> 'search hosts by pattern in host names'
	 *  integer 'limit'						=> 'limit selection'
	 *  string  'order'						=> 'depricated parametr (for now)'
	 * );
	 * </code>
	 *
	 * @static
	 * @param array $options 
	 * @return array|boolean host data as array or false if error
	 */
	public static function get($options=array()){

		$def_sql = array(
			'select' => array(),
			'from' => array('hosts h'),
			'where' => array(),
			'order' => array(),
			'limit' => null,
			);

		$def_options = array(
			'nodeid'					=>		0,
			'groupids'					=>		0,
			'hostids'					=>		0,
			'monitored_hosts'			=>		0,
			'templated_hosts'			=>		0,
			'with_items'				=>		0,
			'with_monitored_items'		=>		0,
			'with_historical_items'		=>		0,
			'with_triggers'				=>		0,
			'with_monitored_triggers'	=>		0,
			'with_httptests'			=>		0,
			'with_monitored_httptests'	=>		0,
			'with_graphs'				=>		0,
			'count'						=>		0,
			'pattern'					=>		'',
			'order' 					=>		0,
			'limit'						=>		0,
			);

		$def_options = array_merge($def_options, $options);

		$result = array();
//-----
// nodes
		if($def_options['nodeid']){
			$nodeid = $def_options['nodeid'];
		}
		else{
			$nodeid = get_current_nodeid(false);
		}

// groups
		$in_groups = count($def_sql['where']);
		
		if($def_options['groupids'] != 0){
			zbx_value2array($def_options['groupids']);
			$def_sql['where'][] = DBcondition('hg.groupid',$def_options['groupids']);			
		}

		if($in_groups != count($def_sql['where'])){
			$def_sql['from'][] = 'hosts_groups hg';
			$def_sql['where'][] = 'hg.hostid=h.hostid';
		}
		

// hosts 
		if($def_options['hostids'] != 0){
			zbx_value2array($def_options['hostids']);

			$def_sql['where'][] = DBcondition('h.hostid',$def_options['hostids']);
		}

		if(!zbx_empty($def_options['pattern'])){
			$def_sql['where'][] = ' UPPER(h.host) LIKE '.zbx_dbstr('%'.strtoupper($def_options['pattern']).'%');
		}

		if($def_options['monitored_hosts'])
			$def_sql['where'][] = 'h.status='.HOST_STATUS_MONITORED;
		else if($def_options['templated_hosts'])
			$def_sql['where'][] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')';
		else
			$def_sql['where'][] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';

// items
		if($def_options['with_items']){
			$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid )';
		}  
		else if($def_options['with_monitored_items']){
			$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid AND i.status='.ITEM_STATUS_ACTIVE.')';
		}
		else if($def_options['with_historical_items']){
			$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid AND (i.status='.ITEM_STATUS_ACTIVE.' OR i.status='.ITEM_STATUS_NOTSUPPORTED.') AND i.lastvalue IS NOT NULL)';
		}


// triggers
		if($def_options['with_triggers']){
			$def_sql['where'][] = 'EXISTS( SELECT i.itemid '.
				 ' FROM items i, functions f, triggers t'.
				 ' WHERE i.hostid=h.hostid '.
				 	' AND i.itemid=f.itemid '.
				 	' AND f.triggerid=t.triggerid)';
		} 
		else if($def_options['with_monitored_triggers']){
			$def_sql['where'][] = 'EXISTS( SELECT i.itemid '.
				 ' FROM items i, functions f, triggers t'.
				 ' WHERE i.hostid=h.hostid '.
				 	' AND i.status='.ITEM_STATUS_ACTIVE.
				 	' AND i.itemid=f.itemid '.
				 	' AND f.triggerid=t.triggerid '.
				 	' AND t.status='.TRIGGER_STATUS_ENABLED.')';
		}

// httptests
		if($def_options['with_httptests']){
			$def_sql['where'][] = 'EXISTS( SELECT a.applicationid '.
				' FROM applications a, httptest ht '.
				' WHERE a.hostid=h.hostid '.
					' AND ht.applicationid=a.applicationid)';
		}
		else if($def_options['with_monitored_httptests']){
			$def_sql['where'][] = 'EXISTS( SELECT a.applicationid '.
				' FROM applications a, httptest ht '.
				' WHERE a.hostid=h.hostid '.
					' AND ht.applicationid=a.applicationid '.
					' AND ht.status='.HTTPTEST_STATUS_ACTIVE.')';
		}

// graphs
		if($def_options['with_graphs']){
			$def_sql['where'][] = 'EXISTS( SELECT DISTINCT i.itemid '.
				 ' FROM items i, graphs_items gi '.
				 ' WHERE i.hostid=h.hostid '.
				 	' AND i.itemid=gi.itemid)';
		}

// count
		if($def_options['count']){
			$def_sql['select'][] = 'COUNT(h.hostid) as rowscount';
		}
		else{
			$def_sql['select'][] = 'h.hostid';
			$def_sql['select'][] = 'h.host';
		}

// order
		if(str_in_array($def_options['order'], array('host','hostid'))){
			$def_sql['order'][] = 'h.'.$def_options['order'];
		}

// limit
		if(zbx_ctype_digit($def_options['limit'])){
			$def_sql['limit'] = $def_options['limit'];
		}
//------
		
		$def_sql['select'] = array_unique($def_sql['select']);
		$def_sql['from'] = array_unique($def_sql['from']);
		$def_sql['where'] = array_unique($def_sql['where']);
		$def_sql['order'] = array_unique($def_sql['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		$sql_limit = null;
		if(!empty($def_sql['select'])) $sql_select.= implode(',',$def_sql['select']);
		if(!empty($def_sql['from'])) $sql_from.= implode(',',$def_sql['from']);
		if(!empty($def_sql['where'])) $sql_where.= ' AND '.implode(' AND ',$def_sql['where']);
		if(!empty($def_sql['order'])) $sql_order.= ' ORDER BY '.implode(',',$def_sql['order']);
		if(!empty($def_sql['limit'])) $sql_limit = $def_sql['limit'];

		$sql = 'SELECT DISTINCT '.$sql_select.
			' FROM '.$sql_from.
			' WHERE '.DBin_node('h.hostid', $nodeid).
				$sql_where.
			$sql_order; 
		$res = DBselect($sql, $sql_limit);
		while($host = DBfetch($res)){
			if($def_options['count']) 
				$result = $host;
			else 
				$result[$host['hostid']] = $host;
		}
		
	return $result;
	}
	
	/**
	 * Gets all host data from DB by hostid
	 *
	 * @static
	 * @param string $hostid
	 * @return array|boolean host data as array or false if error
	 */
	public static function getById($host_data){
		$sql = 'SELECT * FROM hosts WHERE hostid='.$host_data['hostid'];
		$host = DBfetch(DBselect($sql));
		
		$result = $host ? true : false;
		if($result)
			return $host;
		else{
			self::$error = array('error' => ZBX_API_ERROR_NO_HOST, 'data' => 'Host with id: '.$host_data['hostid'].' doesn\'t exists.');
			return false;
		}
	}
	
	/**
	 * Get hostid by host name
	 *
	 * <code>
	 * $host_data = array(
	 * 	string 'host' => 'hostname'
	 * );
	 * </code>
	 *
	 * @static
	 * @param array $host_data
	 * @return int|boolean hostid
	 */
	public static function getId($host_data){
		$result = false;
		
		$sql = 'SELECT hostid '.
				' FROM hosts '.
				' WHERE host='.zbx_dbstr($host_data['host']).
					' AND '.DBin_node('hostid', get_current_nodeid(false));
		$res = DBselect($sql);
		if($hostid = DBfetch($res))
			$result = $hostid['hostid'];
		else{
			self::$error = array('error' => ZBX_API_ERROR_NO_HOST, 'data' => 'Host with name: "'.$host_data['host'].'" doesn\'t exists.');
		}
		
	return $result;
	}
	
	/**
	 * Add host
	 *
	 * <code>
	 * $host_db_fields = array(
	 * 	string 'host' => 'host name [0]',
	 * 	'port' => 'port [0]',
	 * 	'status' => 0,
	 * 	'useip' => 0,
	 * 	'dns' => '',
	 * 	'ip' => '0.0.0.0',
	 * 	'proxy_hostid' => 0,
	 * 	'useipmi' => 0,
	 * 	'ipmi_ip' => '',
	 * 	'ipmi_port' => 623,
	 * 	'ipmi_authtype' => 0,
	 * 	'ipmi_privilege' => 0,
	 * 	'ipmi_username' => '',
	 * 	'ipmi_password' => '',
	 * );
	 * </code>
	 *
	 * @static
	 * @param array $hosts multidimensional array with hosts data
	 * @return boolean 
	 */
	public static function add($hosts){
		$templates = null;
		$newgroup = ''; 
		$groups = null;
			
		$hostids = array();
		DBstart(false);
		
		$result = false;
		foreach($hosts as $host){
		
			$host_db_fields = array(
				'host' => null,
				'port' => 0,
				'status' => 0,
				'useip' => 0,
				'dns' => '',
				'ip' => '0.0.0.0',
				'proxy_hostid' => 0,
				'useipmi' => 0,
				'ipmi_ip' => '',
				'ipmi_port' => 623,
				'ipmi_authtype' => 0,
				'ipmi_privilege' => 0,
				'ipmi_username' => '',
				'ipmi_password' => '',
			);

			if(!check_db_fields($host_db_fields, $host)){
				$result = false;
				break;
			}
			
			$result = add_host($host['host'], $host['port'], $host['status'], $host['useip'], $host['dns'], $host['ip'], 
				$host['proxy_hostid'], $templates, $host['useipmi'], $host['ipmi_ip'], $host['ipmi_port'], $host['ipmi_authtype'], 
				$host['ipmi_privilege'], $host['ipmi_username'], $host['ipmi_password'], $newgroup, $groups);
			if(!$result) break;
			$hostids[$result] = $result;
		}
		$result = DBend($result);
		if($result){
			return $hostids;
		}
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $hostids);//'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Update host
	 *
	 * @static
	 * @param array $hosts multidimensional array with host data
	 * @return boolean
	 */
	public static function update($hosts){
		$templates = null;
		$newgroup = ''; 
		$groups = null;
		
		$hostids = array();
		
		$result = false;
		
		DBstart(false);
		foreach($hosts as $host){
		
			$sql = 'SELECT DISTINCT * '.
			' FROM hosts '.
			' WHERE hostid='.$host['hostid'];

			$host_db_fields = DBfetch(DBselect($sql));
			
			if(!isset($host_db_fields)) {
				$result = false;
				break;
			}
			
			if(!check_db_fields($host_db_fields, $host)){
				$result = false;
				break;
			}			
			
			$result = update_host($host['hostid'], $host['host'], $host['port'], $host['status'], $host['useip'], $host['dns'], $host['ip'], 
				$host['proxy_hostid'], $templates, $host['useipmi'], $host['ipmi_ip'], $host['ipmi_port'], $host['ipmi_authtype'], 
				$host['ipmi_privilege'], $host['ipmi_username'], $host['ipmi_password'], $newgroup, $groups);
			if(!$result) break;
			$hostids[$result] = $result;
		}	
		$result = DBend($result);
		
		if($result){
			return $hostids;
		}
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Mass update hosts
	 *
	 * @static
	 * @param array $hosts multidimensional array with host ids
	 * @return boolean
	 */
	public static function massUpdate($hosts) {
	
		$hostids = $hosts['hostids'];
		$host_data = $hosts['host_data'];
		$sql = 'UPDATE hosts SET '.
			(isset($host_data['proxy_hostid']) ? ',proxy_hostid='.$host_data['proxy_hostid'] : '').
			(isset($host_data['host']) ? ',host='.zbx_dbstr($host_data['host']) : '').
			(isset($host_data['port']) ? ',port='.$host_data['port'] : '').
			(isset($host_data['status']) ? ',status='.$host_data['status'] : '').
			(isset($host_data['useip']) ? ',useip='.$host_data['useip'] : '').
			(isset($host_data['dns']) ? ',dns='.zbx_dbstr($host_data['dns']) : '').
			(isset($host_data['ip']) ? ',ip='.zbx_dbstr($host_data['ip']) : '').
			(isset($host_data['useipmi']) ? ',useipmi='.$host_data['useipmi'] : '').
			(isset($host_data['ipmi_port']) ? ',ipmi_port='.$host_data['ipmi_port'] : '').
			(isset($host_data['ipmi_authtype']) ? ',ipmi_authtype='.$host_data['ipmi_authtype'] : '').
			(isset($host_data['ipmi_privilege']) ? ',ipmi_privilege='.$host_data['ipmi_privilege'] : '').
			(isset($host_data['ipmi_username']) ? ',ipmi_username='.zbx_dbstr($host_data['ipmi_username']) : '').
			(isset($host_data['ipmi_password']) ? ',ipmi_password='.zbx_dbstr($host_data['ipmi_password']) : '').
			(isset($host_data['ipmi_ip']) ? ',ipmi_ip='.zbx_dbstr($host_data['ipmi_ip']) : '').
			' WHERE '.DBcondition('hostid', $hostids);
			
		substr_replace($sql, '', strpos(',', $sql), 1);
		
		$result = DBexecute($sql);
		if($result){
			return $hostids;
		}
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Delete host
	 *
	 * @static
	 * @param array $hostids 
	 * @return boolean
	 */	
	public static function delete($hostids){
		$result = delete_host($hostids, false);	
		if($result)
			return $hostids;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
		
}
?>