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
		
		$sort_columns = array('itemid','description','key_','delay','history','trends','type','status'); // allowed columns for sorting

		$sql_parts = array(
			'select' => array('items' => 'i.itemid'),
			'from' => array('items i'),
			'where' => array('i.type<>9'),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'				=> 0,
			'groupids'				=> 0,
			'hostids'				=> 0,
			'itemids'				=> 0,
			'graphids'				=> 0,
			'triggerids'			=> 0,
			'applicationids'		=> 0,
			'templated_items'		=> 0,
			'editable'				=> 0,
			'nopermissions'			=> 0,
// OutPut
			'extendoutput'			=> 0,
			'select_hosts'			=> 0,
			'select_triggers'		=> 0,
			'select_graphs'			=> 0,
			'select_applications'	=> 0,
			'count'					=> 0,
// filter
			'filter'				=> 0,

			'group'					=> null,
			'host'					=> null,
			'application'			=> null,
			'key'					=> null,
			'type'					=> null,
			'snmp_community'		=> null,
			'snmp_oid'				=> null,
			'snmp_port'				=> null,
			'valuetype'				=> null,
			'delay'					=> null,
			'history'				=> null,
			'trends'				=> null,
			'status'				=> null,

//
			'pattern'				=> null,
			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> 0,
			'order'					=> '');

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
			zbx_value2array($options['groupids']);
			
			if($options['extendoutput'] != 0){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}
			
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where'][] = 'hg.hostid=i.hostid';
		}

// hostids
		if($options['hostids'] != 0){
			zbx_value2array($options['hostids']);
			
			if($options['extendoutput'] != 0){
				$sql_parts['select']['hostid'] = 'i.hostid';
			}

			$sql_parts['where'][] = DBcondition('i.hostid', $options['hostids']);
		}

// itemids
		if($options['itemids'] != 0){
			zbx_value2array($options['itemids']);
			
			if($options['extendoutput'] != 0){
				$sql_parts['select']['itemid'] = 'i.itemid';
			}
			
			$sql_parts['where'][] = DBcondition('i.itemid', $options['itemids']);
		}

// triggerids
		if($options['triggerids'] != 0){
			zbx_value2array($options['triggerids']);

			if($options['extendoutput'] != 0){
				$sql_parts['select']['triggerid'] = 'f.triggerid';
			}

			$sql_parts['from'][] = 'functions f';
			$sql_parts['where'][] = DBcondition('f.triggerid', $options['triggerids']);
			$sql_parts['where']['if'] = 'i.itemid=f.itemid';
		}
		
// applicationids
		if($options['applicationids'] != 0){
			zbx_value2array($options['applicationids']);
			
			if($options['extendoutput'] != 0){
				$sql_parts['select']['applicationid'] = 'a.applicationid';
			}
			
			$sql_parts['from'][] = 'applications a';
			$sql_parts['where'][] = DBcondition('a.applicationid', $options['applicationids']);
			$sql_parts['where']['ia'] = 'i.hostid=a.hostid';
		}

// graphids
		if($options['graphids'] != 0){
			zbx_value2array($options['graphids']);

			if($options['extendoutput'] != 0){
				$sql_parts['select']['graphid'] = 'gi.graphid';
			}

			$sql_parts['from']['gi'] = 'graphs_items gi';
			$sql_parts['where'][] = DBcondition('gi.graphid', $options['graphids']);
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
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
			$options['select_hosts'] = 0;
			$options['select_triggers'] = 0;
			$options['select_graphs'] = 0;
			$options['sortfield'] = '';
			
			$sql_parts['select'] = array('count(i.itemid) as rowscount');
		}

// --- FILTER ---
		if($options['filter'] != 0){
// group
			if(!is_null($options['group'])){
				if($options['extendoutput'] != 0){
					$sql_parts['select']['name'] = 'g.name';
				}
				
				$sql_parts['from']['g'] = 'groups g';
				$sql_parts['from']['hg'] = 'hosts_groups hg';
				
				$sql_parts['where']['ghg'] = 'g.groupid = hg.groupid';
				$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
				$sql_parts['where'][] = ' UPPER(g.name)='.zbx_dbstr(strtoupper($options['group']));
			}

// host
			if(!is_null($options['host'])){
				if($options['extendoutput'] != 0){
					$sql_parts['select']['host'] = 'h.host';
				}
				
				$sql_parts['from']['h'] = 'hosts h';
				$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
				$sql_parts['where'][] = ' UPPER(h.host)='.zbx_dbstr(strtoupper($options['host']));
			}

// application
			if(!is_null($options['application'])){
				if($options['extendoutput'] != 0){
					$sql_parts['select']['application'] = 'a.name as application';
				}
				
				$sql_parts['from']['a'] = 'applications a';
				$sql_parts['from']['ia'] = 'items_applications ia';
				
				$sql_parts['where']['aia'] = 'a.applicationid = ia.applicationid';
				$sql_parts['where']['iai'] = 'ia.itemid=i.itemid';
				$sql_parts['where'][] = ' UPPER(a.name)='.zbx_dbstr(strtoupper($options['application']));
			}

// key
			if(!is_null($options['key'])){
				$sql_parts['where'][] = ' UPPER(i.key_) LIKE '.zbx_dbstr('%'.strtoupper($options['key']).'%');
			}

// type
			if(!is_null($options['type'])){
				$sql_parts['where'][] = 'i.type='.$options['type'];
			}

// snmp community
			if(!is_null($options['snmp_community'])){
				$sql_parts['where'][] = 'i.snmp_community='.zbx_dbstr($options['snmp_community']);
			}

// snmp oid
			if(isset($_REQUEST['snmp_oid'])){
				$sql_parts['where'][] = 'i.snmp_oid='.zbx_dbstr($options['snmp_oid']);
			}

// snmp port
			if(isset($_REQUEST['snmp_port'])){
				$sql_parts['where'][] = 'i.snmp_port='.$options['snmp_port'];
			}

// valuetype
			if(!is_null($options['valuetype'])){
				$sql_parts['where'][] = 'i.value_type='.$options['valuetype'];
			}

// delay
			if(!is_null($options['delay'])){
				$sql_parts['where'][] = 'i.delay='.$options['delay'];
			}

// trends
			if(!is_null($options['trends'])){
				$sql_parts['where'][] = 'i.trends='.$options['trends'];
			}
			
// history
			if(!is_null($options['history'])){
				$sql_parts['where'][] = 'i.history='.$options['history'];
			}

// pattern
			if(!is_null($options['pattern'])){
				$sql_parts['where'][] = ' UPPER(i.description) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
			}

// status
			if(!is_null($options['status'])){
				$sql_parts['where'][] = 'i.status='.$options['status'];
			}			
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;
			
			$sql_parts['order'][] = 'i.'.$options['sortfield'].' '.$sortorder;
			
			if(!str_in_array('i.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('i.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'i.'.$options['sortfield'];
			}
		}
		
// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
		else if(!defined('ZBX_API_REQUEST')){
			$sql_parts['limit'] = 1001;
		}
//----------

		$itemids = array(); 
		
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

		$sql = 'SELECT '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('i.itemid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($item = DBfetch($res)){
			if($options['count'])
				$result = $item;
			else{
				$itemids[$item['itemid']] = $item['itemid'];
				
				if($options['extendoutput'] == 0){
					$result[$item['itemid']] = $item['itemid'];
				}
				else{
					if(!isset($result[$item['itemid']])) 
						$result[$item['itemid']]= array();
						
					if($options['select_hosts'] && !isset($result[$item['itemid']]['hostids'])){
						$result[$item['itemid']]['hostids'] = array();
						$result[$item['itemid']]['hosts'] = array();
					}
					if($options['select_triggers'] && !isset($result[$item['itemid']]['triggerids'])){
						$result[$item['itemid']]['triggerids'] = array();
						$result[$item['itemid']]['triggers'] = array();
					}
					if($options['select_graphs'] && !isset($result[$item['itemid']]['graphids'])){
						$result[$item['itemid']]['graphids'] = array();
						$result[$item['itemid']]['graphs'] = array();
					}
					if($options['select_applications'] && !isset($result[$item['itemid']]['applicationids'])){
						$result[$item['itemid']]['applicationids'] = array();
						$result[$item['itemid']]['applications'] = array();
					}

					
					// hostids
					if(isset($item['hostid'])){
						if(!isset($result[$item['itemid']]['hostids'])) $result[$item['itemid']]['hostids'] = array();

						$result[$item['itemid']]['hostids'][$item['hostid']] = $item['hostid'];
						unset($item['hostid']);
					}
					// triggerids
					if(isset($item['triggerid'])){
						if(!isset($result[$item['itemid']]['triggerids'])) 
							$result[$item['itemid']]['triggerids'] = array();
							
						$result[$item['itemid']]['triggerids'][$item['triggerid']] = $item['triggerid'];
						unset($item['triggerid']);
					}
					// graphids
					if(isset($item['graphid'])){
						if(!isset($result[$item['itemid']]['graphids'])) 
							$result[$item['itemid']]['graphids'] = array();
							
						$result[$item['itemid']]['graphids'][$item['graphid']] = $item['graphid'];
						unset($item['graphid']);
					}
					// applicationids
					if(isset($item['applicationid'])){
						if(!isset($result[$item['itemid']]['applicationids'])) 
							$result[$item['itemid']]['applicationids'] = array();
							
						$result[$item['itemid']]['applicationids'][$item['applicationid']] = $item['applicationid'];
						unset($item['applicationid']);
					}

					$result[$item['itemid']] += $item;
				}
			}
		}
// Adding Objects

// Adding hosts
		if($options['select_hosts']){
			$obj_params = array('extendoutput' => 1, 'itemids' => $itemids, 'nopermissions' => 1);
			$hosts = CHost::get($obj_params);
			foreach($hosts as $hostid => $host){
				foreach($host['itemids'] as $num => $itemid){
					$result[$itemid]['hostids'][$hostid] = $hostid;
					$result[$itemid]['hosts'][$hostid] = $host;
				}
			}
			
			$templates = CTemplate::get($obj_params);
			foreach($templates as $templateid => $template){
				foreach($template['itemids'] as $num => $itemid){
					$result[$itemid]['hostids'][$templateid] = $templateid;
					$result[$itemid]['hosts'][$templateid] = $template;
				}
			}
		}
	
// Adding triggers	
		if($options['select_triggers']){
			$obj_params = array('extendoutput' => 1, 'itemids' => $itemids);
			$triggers = CTrigger::get($obj_params);
			foreach($triggers as $triggerid => $trigger){
				foreach($trigger['itemids'] as $num => $itemid){
					$result[$itemid]['triggerids'][$triggerid] = $triggerid;
					$result[$itemid]['triggers'][$triggerid] = $trigger;
				}
			}
		}
		
// Adding graphs
		if($options['select_graphs']){
			$obj_params = array('extendoutput' => 1, 'itemids' => $itemids);
			$graphs = CGraph::get($obj_params);
			foreach($graphs as $graphid => $graph){
				foreach($graph['itemids'] as $num => $itemid){
					$result[$itemid]['graphids'][$graphid] = $graphid;
					$result[$itemid]['graphs'][$graphid] = $graph;
				}
			}
		}
		
// Adding applications
		if($options['select_applications']){
			$sql = 'SELECT ia.itemid, app.* '.
					' FROM applications app, items_applications ia '.
					' WHERE app.applicationid=ia.applicationid '.
						' AND '.DBcondition('ia.itemid',$itemids);
			$res = DBselect($sql);
			while($app = DBfetch($res)){
				$result[$app['itemid']]['applicationids'][$app['applicationid']] = $app['applicationid'];
				$result[$app['itemid']]['applications'][$app['applicationid']] = $app;
			}
						
/*
			$obj_params = array('extendoutput' => 1, 'itemids' => $itemids);
			$applications = CApplication::get($obj_params);			
			foreach($applications as $applicationid => $application){
				foreach($application['itemids'] as $num => $itemid){
					$result[$itemid]['applicationids'][$applicationid] = $applicationid;
					$result[$itemid]['applications'][$applicationid] = $application;
				}
			}
//*/
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
