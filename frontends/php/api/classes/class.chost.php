<?php
/**
 * File containing host class for API.
 * @package API
 */
/**
 * Class containing methods for operations with hosts
 *
 */
class CHost {

	/**
	 * Get host data 
	 *
	 * $def_options = array(
	 * + string 'nodeid' 					=> 'node ID,
	 * + array 'groupids' 					=> array(groupid1, groupid2, ...),
	 * + array 'hostids' 					=> array(hostid1, hostid2, ...),
	 * + boolean 'monitored_hosts'			=> 'only monitored hosts',
	 * + boolean 'with_items' 				=> 'only with items',
	 * + boolean 'with_monitored_items' 	=> 'only with monitored items',
	 * + boolean 'with_historical_items'	=> 'only with historical items',
	 * + boolean 'with_triggers' 			=> 'only with triggers',
	 * + boolean 'with_monitored_triggers'	=> 'only with monitores triggers',
	 * + boolean 'with_httptests' 			=> 'only with http tests',
	 * + boolean 'with_monitored_httptests'	=> 'only with monitores http tests',
	 * + boolean 'with_graphs'				=> 'only with graphs'
	 * );
	 *
	 * @static
	 * @param array $options 
	 * @return array|boolean host data as array or false if error
	 */
	public static function get($options=array()){

		$def_sql = array(
			'select' => array('h.hostid','h.host'),
			'from' => array('hosts h'),
			'where' => array(),
			'order' => array(),
			);

		$def_options = array(
			'nodeid' =>      0,
			'groupids' =>     0,
			'hostids' =>     0,
			'monitored_hosts' =>   0,
			'with_items' =>     0,
			'with_monitored_items' =>  0,
			'with_historical_items'=>  0,
			'with_triggers' =>    0,
			'with_monitored_triggers'=>  0,
			'with_httptests' =>    0,
			'with_monitored_httptests'=> 0,
			'with_graphs'=>     0,
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
		if($def_options['groupids']){
			zbx_value2array($def_options['groupids']);

		$def_sql['from'][] = 'hosts_groups hg';
		$def_sql['where'][] = DBcondition('hg.groupid',$def_options['groupids']);
		$def_sql['where'][] = 'hg.hostid=h.hostid';
		}

// hosts 
		if($def_options['hostids']){
			zbx_value2array($def_options['hostids']);

			$def_sql['where'][] = DBcondition('h.hostid',$def_options['hostids']);
		}

		if($def_options['monitored_hosts'])
			$def_sql['where'][] = 'h.status='.HOST_STATUS_MONITORED;

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
//------
		$def_sql['order'][] = 'h.host';

		$def_sql['select'] = array_unique($def_sql['select']);
		$def_sql['from'] = array_unique($def_sql['from']);
		$def_sql['where'] = array_unique($def_sql['where']);
		$def_sql['order'] = array_unique($def_sql['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if(!empty($def_sql['select'])) $sql_select.= implode(',',$def_sql['select']);
		if(!empty($def_sql['from'])) $sql_from.= implode(',',$def_sql['from']);
		if(!empty($def_sql['where'])) $sql_where.= ' AND '.implode(' AND ',$def_sql['where']);
		if(!empty($def_sql['order'])) $sql_order.= implode(',',$def_sql['order']);

		$sql = 'SELECT DISTINCT '.$sql_select.
			' FROM '.$sql_from.
			' WHERE '.DBin_node('h.hostid', $nodeid).
		$sql_where.
			' ORDER BY '.$sql_order; 
		$res = DBselect($sql);
		while($host = DBfetch($res)){
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
	public static function getById($hostid){
		$sql = 'SELECT * FROM hosts WHERE hostid='.$hostid;
		$host = DBfetch(DBselect($sql));
		
		return $host ? $host : false;
	}
	
	/**
	 * Get hostid by host name
	 *
	 * $host_data = array(
	 * + string 'host' => 'hostname'
	 * );
	 *
	 * @static
	 * @param array $host_data
	 * @return int|boolean hostid
	 */
	public static function getId($host_data){
		$sql = 'SELECT hostid FROM hosts WHERE host='.zbx_dbstr($host_data['host']).' AND '.DBin_node('hostid', get_current_nodeid(false));
		$hostid = DBfetch(DBselect($sql));
		
		return $hostid ? $hostid['hostid'] : false;	
	}
	strcmp(
	/**
	 * Add host
	 *
	 * $host_db_fields = array(
	 * + string 'host' => 'host name [0]',
	 * + 'port' => 'port [0]',
	 * + 'status' => 0,
	 * + 'useip' => 0,
	 * + 'dns' => '',
	 * + 'ip' => '0.0.0.0',
	 * + 'proxy_hostid' => 0,
	 * + 'useipmi' => 0,
	 * + 'ipmi_ip' => '',
	 * + 'ipmi_port' => 623,
	 * + 'ipmi_authtype' => 0,
	 * + 'ipmi_privilege' => 0,
	 * + 'ipmi_username' => '',
	 * + 'ipmi_password' => '',
	 * );

	 * @static
	 * @param array $hosts multidimensional array with hosts data
	 * @return boolean 
	 */
	public static function add($hosts){
		$templates = null;
		$newgroup = ''; 
		$groups = null;
			
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
		}
		
		$result = DBend($result);
		return $result ? true : false;
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
				error('Incorrect arguments pasted to function [CHost::update]');
				$result = false;
				break;
			}			
			
			$result = update_host($host['hostid'], $host['host'], $host['port'], $host['status'], $host['useip'], $host['dns'], $host['ip'], 
				$host['proxy_hostid'], $templates, $host['useipmi'], $host['ipmi_ip'], $host['ipmi_port'], $host['ipmi_authtype'], 
				$host['ipmi_privilege'], $host['ipmi_username'], $host['ipmi_password'], $newgroup, $groups);
			if(!$result) break;
		}	
		$result = DBend($result);
		
		return $result;
	}
	
	/**
	 * Mass update hosts
	 *
	 * @static
	 * @param array $hosts multidimensional array with host ids
	 * @param array $host_data array with host data to update
	 * @return boolean
	 */
	public static function massUpdate($hostids, $host_data) {
	
		$sql = 'UPDATE hosts SET '.
			(isset($host_data['proxy_hostid']) ? ',proxy_hostid='.$host_data['proxy_hostid'] : '').
			(isset($host_data['host']) ? ',host='.zbx_dbstr($host_data['host']) : '').
			(isset($host_data['port']) ? ',port='.$host_data['port'] : '').
			(isset($host_data['status']) ? ',status='.$host_data['status'] : '').
			(isset($host_data['useip']) ? ',useip='.$host_data['useip'] : '').
			(isset($host_data['dns']) ? ',dns='.zbx_dbstr($host_data['dns']) : '').
			(isset($host_data['ip']) ? ',ip='.zbx_dbstr($host_data['ip']) : '').
			(isset($host_data['useipmi']) ? ',useipmi='$host_data['useipmi'] : '').
			(isset($host_data['ipmi_port']) ? ',ipmi_port='.$host_data['ipmi_port'] : '').
			(isset($host_data['ipmi_authtype']) ? ',ipmi_authtype='.$host_data['ipmi_authtype'] : '').
			(isset($host_data['ipmi_privilege']) ? ',ipmi_privilege='.$host_data['ipmi_privilege'] : '').
			(isset($host_data['ipmi_username']) ? ',ipmi_username='.zbx_dbstr($host_data['ipmi_username']) : '').
			(isset($host_data['ipmi_password']) ? ',ipmi_password='.zbx_dbstr($host_data['ipmi_password']) : '').
			(isset($host_data['ipmi_ip']) ? ',ipmi_ip='.zbx_dbstr($host_data['ipmi_ip']) : '').
			' WHERE '.DBcondition('hostid', $hostids);
			
		substr_replace($sql, '', strpos(',', $sql), 1);
		
		$result = DBexecute($sql);
		
		return $result;
	}
	
	/**
	 * Delete host
	 *
	 * @static
	 * @param array $hostids 
	 * @return boolean
	 */	
	public static function delete($hostids){
		return delete_host($hostids, false);	
	}
		
}
?>