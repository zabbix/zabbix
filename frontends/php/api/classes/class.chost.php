<?php
/**
 * File containing CHost class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Hosts
 */
class CHost {

	public static $error;

	/**
	 * Get Host data
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $options
	 * @param array $options['nodeids'] Node IDs
	 * @param array $options['groupids'] HostGroup IDs
	 * @param array $options['hostids'] Host IDs
	 * @param boolean $options['monitored_hosts'] only monitored Hosts
	 * @param boolean $options['templated_hosts'] include templates in result
	 * @param boolean $options['with_items'] only with items
	 * @param boolean $options['with_monitored_items'] only with monitored items
	 * @param boolean $options['with_historical_items'] only with historical items
	 * @param boolean $options['with_triggers'] only with triggers
	 * @param boolean $options['with_monitored_triggers'] only with monitores triggers
	 * @param boolean $options['with_httptests'] only with http tests
	 * @param boolean $options['with_monitored_httptests'] only with monitores http tests
	 * @param boolean $options['with_graphs'] only with graphs
	 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
	 * @param int $options['extendoutput'] return all fields for Hosts
	 * @param int $options['count'] count Hosts, returned column name is rowscount
	 * @param string $options['pattern'] search hosts by pattern in host names
	 * @param int $options['limit'] limit selection
	 * @param string $options['order'] depricated parametr (for now)
	 * @return array|boolean Host data as array or false if error
	 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];
		
		$sort_columns = array('hostid, host'); // allowed columns for sorting
	
	
		$sql_parts = array(
			'select' => array('hosts' => 'h.hostid, h.host'),
			'from' => array('hosts h'),
			'where' => array(),
			'order' => array(),
			'limit' => null);
		
		$def_options = array(
			'nodeids'					=> array(),
			'groupids'					=> array(),
			'hostids'					=> array(),
			'monitored_hosts'			=> false,
			'templated_hosts'			=> false,
			'with_items'				=> false,
			'with_monitored_items'		=> false,
			'with_historical_items'		=> false,
			'with_triggers'				=> false,
			'with_monitored_triggers'	=> false,
			'with_httptests'			=> false,
			'with_monitored_httptests'	=> false,
			'with_graphs'				=> false,
			'editable'					=> false,
			'nopermissions'				=> false,
			'extendoutput'				=> false,
			'count'						=> false,
			'pattern'					=> '',
			'extend_pattern'			=> false,
			'order' 					=> '',
			'limit'						=> null);

		$options = array_merge($def_options, $options);
	
// editable + PERMISSION CHECK
		if(defined('ZBX_API_REQUEST')){
			$options['nopermissions'] = false;
		}
		
		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;
			
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['r'] = 'rights r';
			$sql_parts['from']['ug'] = 'users_groups ug';
			$sql_parts['where']['hgh'] = 'hg.hostid=h.hostid';
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
		if($options['groupids']){
			zbx_value2array($options['groupids']);
			if($options['extenduotput']){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where']['hgh'] = 'hg.hostid=h.hostid';
		}
// hostids
		if($options['hostids']){
			zbx_value2array($options['hostids']);
			$sql_parts['where'][] = DBcondition('h.hostid', $options['hostids']);
		}

// monitored_hosts, templated_hosts
		if($options['monitored_hosts']){
			$sql_parts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
		}
		else if($options['templated_hosts']){
			$sql_parts['where'][] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')';
		}
		else{
			$sql_parts['where'][] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		}

// with_items, with_monitored_items, with_historical_items
		if($options['with_items']){
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid )';
		}
		else if($options['with_monitored_items']){
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid AND i.status='.ITEM_STATUS_ACTIVE.')';
		}
		else if($options['with_historical_items']){
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid AND (i.status='.ITEM_STATUS_ACTIVE.' OR i.status='.ITEM_STATUS_NOTSUPPORTED.') AND i.lastvalue IS NOT NULL)';
		}

// with_triggers, with_monitored_triggers
		if($options['with_triggers']){
			$sql_parts['where'][] = 'EXISTS( 
					SELECT i.itemid
					FROM items i, functions f, triggers t
					WHERE i.hostid=h.hostid 
						AND i.itemid=f.itemid 
						AND f.triggerid=t.triggerid)';
		}
		else if($options['with_monitored_triggers']){
			$sql_parts['where'][] = 'EXISTS( 
					SELECT i.itemid 
					FROM items i, functions f, triggers t
					WHERE i.hostid=h.hostid 
						AND i.status='.ITEM_STATUS_ACTIVE.'
						AND i.itemid=f.itemid 
						AND f.triggerid=t.triggerid 
						AND t.status='.TRIGGER_STATUS_ENABLED.')';
		}

// with_httptests, with_monitored_httptests
		if($options['with_httptests']){
			$sql_parts['where'][] = 'EXISTS( 
					SELECT a.applicationid 
					FROM applications a, httptest ht 
					WHERE a.hostid=h.hostid 
						AND ht.applicationid=a.applicationid)';
		}
		else if($options['with_monitored_httptests']){
			$sql_parts['where'][] = 'EXISTS( 
					SELECT a.applicationid 	
					FROM applications a, httptest ht 	
					WHERE a.hostid=h.hostid	
						AND ht.applicationid=a.applicationid 	
						AND ht.status='.HTTPTEST_STATUS_ACTIVE.')';
		}

// with_graphs
		if($options['with_graphs']){
			$sql_parts['where'][] = 'EXISTS( 
					SELECT DISTINCT i.itemid 
					FROM items i, graphs_items gi 
					WHERE i.hostid=h.hostid 
						AND i.itemid=gi.itemid)';
		}

// extendoutput
		if($options['extendoutput']){
			$sql_parts['select']['hosts'] = 'h.*';
		}
		
// count
		if($options['count']){
			$sql_parts['select'] = array('count(h.hostid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			if($options['extend_pattern']){
				$sql_parts['where'][] = ' ( '.
											'UPPER(h.host) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%').' OR '.
											'h.ip LIKE '.zbx_dbstr('%'.$options['pattern'].'%').' OR '.
											'UPPER(h.dns) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%').
										' ) ';
			}
			else{
				$sql_parts['where'][] = ' UPPER(h.host) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
			}
		}

// order
		// restrict not allowed columns for sorting
		$options['order'] = str_in_array($options['order'], $sort_columns) ? $options['order'] : '';
		if(!zbx_empty($options['order'])){
			$sql_parts['order'][] = 'h.'.$options['order'];
			if(!str_in_array('h.'.$options['order'], $sql_parts['select'])) $sql_parts['select'][] = 'h.'.$options['order'];
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//-------
		
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
				WHERE '.DBin_node('h.hostid', $nodeids).
				$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($host = DBfetch($res)){
			if($options['count'])
				$result = $host;
			else{
				if(!isset($result[$host['hostid']])) 
					$result[$host['hostid']]= array();
				
				if(isset($host['groupid'])){
					if(!isset($result[$host['hostid']]['groups'])) 
						$result[$host['hostid']]['groups'] = array();
						
					$result[$host['hostid']]['groups'][$host['groupid']] = $host['groupid'];
					unset($host['groupid']);
				}
				
				$result[$host['hostid']] += $host;
			}
		}

	return $result;
	}

	/**
	 * Gets all Host data from DB by Host ID
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $host_data
	 * @param string $host_data['hostid']
	 * @return array|boolean Host data as array or false if error
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
	 * Get Host ID by Host name
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $host_data
	 * @param string $host_data['host']
	 * @return int|boolean 
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
	 * Add Host
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $hosts multidimensional array with Hosts data
	 * @param string $hosts['host'] Host name.
	 * @param array $hosts['groupids'] HostGroup IDs add Host to.
	 * @param int $hosts['port'] Port. OPTIONAL
	 * @param int $hosts['status'] Host Status. OPTIONAL
	 * @param int $hosts['useip'] Use IP. OPTIONAL
	 * @param string $hosts['dns'] DNS. OPTIONAL
	 * @param string $hosts['ip'] IP. OPTIONAL
	 * @param int $hosts['proxy_hostid'] Proxy Host ID. OPTIONAL
	 * @param int $hosts['useipmi'] Use IPMI. OPTIONAL
	 * @param string $hosts['ipmi_ip'] IPMAI IP. OPTIONAL
	 * @param int $hosts['ipmi_port'] IPMI port. OPTIONAL
	 * @param int $hosts['ipmi_authtype'] IPMI authentication type. OPTIONAL
	 * @param int $hosts['ipmi_privilege'] IPMI privilege. OPTIONAL
	 * @param string $hosts['ipmi_username'] IPMI username. OPTIONAL
	 * @param string $hosts['ipmi_password'] IPMI password. OPTIONAL
	 * @return boolean
	 */
	public static function add($hosts){
		$templates = null;
		$newgroup = '';

		$error = 'Unknown ZABBIX internal error';
		$result_ids = array();
		$result = false;

		DBstart(false);

		foreach($hosts as $host){

			if(empty($host['groupids'])){
				$result = false;
				$error = 'No groups for host [ '.$host['host'].' ]';
				break;
			}

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
				$error = 'Wrong fields for host [ '.$host['host'].' ]';
				break;
			}

			$result = add_host($host['host'], $host['port'], $host['status'], $host['useip'], $host['dns'], $host['ip'],
				$host['proxy_hostid'], $templates, $host['useipmi'], $host['ipmi_ip'], $host['ipmi_port'], $host['ipmi_authtype'],
				$host['ipmi_privilege'], $host['ipmi_username'], $host['ipmi_password'], $newgroup, $host['groupids']);
			if(!$result) break;
			$result_ids[$result] = $result;
		}
		$result = DBend($result);

		if($result){
			return $result_ids;
		}
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);//'Internal zabbix error');
			return false;
		}
	}

	/**
	 * Update Host
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $hosts multidimensional array with Hosts data
	 * @param string $hosts['host'] Host name.
	 * @param int $hosts['port'] Port. OPTIONAL
	 * @param int $hosts['status'] Host Status. OPTIONAL
	 * @param int $hosts['useip'] Use IP. OPTIONAL
	 * @param string $hosts['dns'] DNS. OPTIONAL
	 * @param string $hosts['ip'] IP. OPTIONAL
	 * @param int $hosts['proxy_hostid'] Proxy Host ID. OPTIONAL
	 * @param int $hosts['useipmi'] Use IPMI. OPTIONAL
	 * @param string $hosts['ipmi_ip'] IPMAI IP. OPTIONAL
	 * @param int $hosts['ipmi_port'] IPMI port. OPTIONAL
	 * @param int $hosts['ipmi_authtype'] IPMI authentication type. OPTIONAL
	 * @param int $hosts['ipmi_privilege'] IPMI privilege. OPTIONAL
	 * @param string $hosts['ipmi_username'] IPMI username. OPTIONAL
	 * @param string $hosts['ipmi_password'] IPMI password. OPTIONAL
	 * @return boolean
	 */
	public static function update($hosts){
		$templates = null;
		$newgroup = '';

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
			
			$groups = get_groupids_by_host($host['hostid']);

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
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $hosts multidimensional array with Hosts data
	 * @param array $hosts['hostids'] Host IDs to update
	 * @param string $hosts['host'] Host name.
	 * @param array $hosts['groupids'] HostGroup IDs add Host to.
	 * @param int $hosts['port'] Port. OPTIONAL
	 * @param int $hosts['status'] Host Status. OPTIONAL
	 * @param int $hosts['useip'] Use IP. OPTIONAL
	 * @param string $hosts['dns'] DNS. OPTIONAL
	 * @param string $hosts['ip'] IP. OPTIONAL
	 * @param int $hosts['proxy_hostid'] Proxy Host ID. OPTIONAL
	 * @param int $hosts['useipmi'] Use IPMI. OPTIONAL
	 * @param string $hosts['ipmi_ip'] IPMAI IP. OPTIONAL
	 * @param int $hosts['ipmi_port'] IPMI port. OPTIONAL
	 * @param int $hosts['ipmi_authtype'] IPMI authentication type. OPTIONAL
	 * @param int $hosts['ipmi_privilege'] IPMI privilege. OPTIONAL
	 * @param string $hosts['ipmi_username'] IPMI username. OPTIONAL
	 * @param string $hosts['ipmi_password'] IPMI password. OPTIONAL
	 * @return boolean
	 */
	public static function massUpdate($hosts) {

		$hostids = $hosts['hostids'];
		$sql = 'UPDATE hosts SET '.
			(isset($hosts['proxy_hostid']) ? ',proxy_hostid='.$hosts['proxy_hostid'] : '').
			(isset($hosts['host']) ? ',host='.zbx_dbstr($hosts['host']) : '').
			(isset($hosts['port']) ? ',port='.$hosts['port'] : '').
			(isset($hosts['status']) ? ',status='.$hosts['status'] : '').
			(isset($hosts['useip']) ? ',useip='.$hosts['useip'] : '').
			(isset($hosts['dns']) ? ',dns='.zbx_dbstr($hosts['dns']) : '').
			(isset($hosts['ip']) ? ',ip='.zbx_dbstr($hosts['ip']) : '').
			(isset($hosts['useipmi']) ? ',useipmi='.$hosts['useipmi'] : '').
			(isset($hosts['ipmi_port']) ? ',ipmi_port='.$hosts['ipmi_port'] : '').
			(isset($hosts['ipmi_authtype']) ? ',ipmi_authtype='.$hosts['ipmi_authtype'] : '').
			(isset($hosts['ipmi_privilege']) ? ',ipmi_privilege='.$hosts['ipmi_privilege'] : '').
			(isset($hosts['ipmi_username']) ? ',ipmi_username='.zbx_dbstr($hosts['ipmi_username']) : '').
			(isset($hosts['ipmi_password']) ? ',ipmi_password='.zbx_dbstr($hosts['ipmi_password']) : '').
			(isset($hosts['ipmi_ip']) ? ',ipmi_ip='.zbx_dbstr($hosts['ipmi_ip']) : '').
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
	 * Delete Host
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
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
