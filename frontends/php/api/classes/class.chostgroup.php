<?php
/**
 * File containing hostgroup class for API.
 * @package API
 */
/**
 * Class containing methods for operations with host groups
 *
 */
class CHostGroup {
	
	public static $error;

	/**
	 * Gets all host group data from DB by groupid
	 *
	 * @static
	 * @param array $group_data 
	 * @return array|boolean host group data as array or false if error
	 */
	public static function getById($group_data){
		$sql = 'SELECT * FROM groups WHERE groupid='.$group_data['groupid'];
		$group = DBfetch(DBselect($sql));
		
		$result = $group ? true : false;
		if($result)
			return $group;
		else{
			self::$error = array('error' => ZBX_API_ERROR_NO_HOST, 'data' => 'Host group with id: '.$group_data['groupid'].' doesn\'t exists.');
			return $result;
		}
	}
	
	/**
	 * Get host group id by group name
	 *
	 * <code>
	 * $group_data = array(
	 * 	string 'name' => 'group name'
	 * );
	 * </code>
	 *
	 * @static
	 * @param array $group_data
	 * @return int|boolean host group data as array or false if error
	 */
	public static function getId($group_data){
		$sql = 'SELECT groupid FROM groups WHERE name='.zbx_dbstr($group_data['name']).' AND '.DBin_node('groupid', get_current_nodeid(false));
		$groupid = DBfetch(DBselect($sql));
		
		$result = $groupid ? true : false;
		if($result)
			return $groupid['groupid'];
		else{
			self::$error = array('error' => ZBX_API_ERROR_NO_HOST, 'data' => 'Host group with name: '.$group_data['name'].' doesn\'t exists.');
			return $result;
		}
	}
	
	/**
	 * Get host groups
	 *
	 * @static
	 * @param array $params
	 * @return array
	 */
	public static function get($params){
		
		$def_sql = array(
					'select' =>	array('g.groupid','g.name'),
					'from' =>	array('groups g'),
					'where' =>	array(),
					'order' =>	array(),
					'limit' =>	null,
				);
				
		$def_options = array(
					'nodeid' =>      				0,
					'groupids' =>					0,
					'hostids' =>					0,
					'monitored_hosts' =>			0,
					'templated_hosts' =>			0,
					'real_hosts' =>					0,
					'not_proxy_hosts' =>			0,
					'with_items' =>					0,
					'with_monitored_items' =>		0,
					'with_historical_items'=>		0,
					'with_triggers' =>				0,
					'with_monitored_triggers'=>		0,
					'with_httptests' =>				0,
					'with_monitored_httptests'=>	0,
					'with_graphs'=>					0,
					'only_current_node' =>			0,
					'pattern' =>					'',
					'order' =>						0,
					'limit' =>						0,
				);	
		$def_options = array_merge($def_options, $params);
		$result = array();
		
		if($def_options['nodeid']){
			$nodeid = $def_options['nodeid'];
		}
		else{
			$nodeid = get_current_nodeid(false);
		}
		
//		$available_groups = get_accessible_groups_by_user($USER_DETAILS,$perm,PERM_RES_IDS_ARRAY,$nodeid,AVAILABLE_NOCACHE);

// nodes
// disabled by false
// TODO('check this ~106');
 		if(false && ZBX_DISTRIBUTED){
		 	$def_sql['select'][] = 'n.name as node_name';
			$def_sql['from'][] = 'nodes n';
			$def_sql['where'][] = 'n.nodeid='.DBid2nodeid('g.groupid');
			$def_sql['order'][] = 'node_name';
		}
		
// groups
		if($def_options['groupids'] != 0){
			zbx_value2array($def_options['groupids']);
			$def_sql['where'][] = DBcondition('g.groupid',$def_options['groupids']);			
		}
		
		if(!zbx_empty($def_options['pattern'])){
			$def_sql['where'][] = ' g.name LIKE '.zbx_dbstr('%'.$def_options['pattern'].'%');
		}

// hosts
		$in_hosts = count($def_sql['where']);
		
		if($def_options['monitored_hosts'])
			$def_sql['where'][] = 'h.status='.HOST_STATUS_MONITORED;
		else if($def_options['real_hosts'])
			$def_sql['where'][] = 'h.status IN('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		else if($def_options['templated_hosts'])
			$def_sql['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
		else if($def_options['not_proxy_hosts'])
			$def_sql['where'][] = 'h.status<>'.HOST_STATUS_PROXY;

		if($def_options['hostids'] != 0){
			zbx_value2array($def_options['hostids']);
			$def_sql['where'][] = DBcondition('h.hostid',$def_options['hostids']);
		}

		if($in_hosts != count($def_sql['where'])){
			$def_sql['from'][] = 'hosts_groups hg';
			$def_sql['from'][] = 'hosts h';
			$def_sql['where'][] = 'hg.groupid=g.groupid';
			$def_sql['where'][] = 'h.hostid=hg.hostid';
		}
		
// items
		if($def_options['with_items']){
			$def_sql['from'][] = 'hosts_groups hg';

			$def_sql['where'][] = 'hg.groupid=g.groupid';
			$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid )';
		} 
		else if($def_options['with_monitored_items']){
			$def_sql['from'][] = 'hosts_groups hg';

			$def_sql['where'][] = 'hg.groupid=g.groupid';
			$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid AND i.status='.ITEM_STATUS_ACTIVE.')';
		}
		else if($def_options['with_historical_items']){
			$def_sql['from'][] = 'hosts_groups hg';

			$def_sql['where'][] = 'hg.groupid=g.groupid';
			$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid AND (i.status='.ITEM_STATUS_ACTIVE.' OR i.status='.ITEM_STATUS_NOTSUPPORTED.') AND i.lastvalue IS NOT NULL)';
		}

// triggers
		if($def_options['with_triggers']){
			$def_sql['from'][] = 'hosts_groups hg';
			
			$def_sql['where'][] = 'hg.groupid=g.groupid';
			$def_sql['where'][] = 'EXISTS( SELECT t.triggerid '.
										' FROM items i, functions f, triggers t'.
										' WHERE i.hostid=hg.hostid '.
											' AND f.itemid=i.itemid '.
											' AND t.triggerid=f.triggerid)';
		}	
		else if($def_options['with_monitored_triggers']){
			$def_sql['from'][] = 'hosts_groups hg';
			
			$def_sql['where'][] = 'hg.groupid=g.groupid';
			$def_sql['where'][] = 'EXISTS( SELECT t.triggerid '.
										' FROM items i, functions f, triggers t'.
										' WHERE i.hostid=hg.hostid '.
											' AND i.status='.ITEM_STATUS_ACTIVE.
											' AND i.itemid=f.itemid '.
											' AND f.triggerid=t.triggerid '.
											' AND t.status='.TRIGGER_STATUS_ENABLED.')';
		}
		
// htptests	
		if($def_options['with_httptests']){
			$def_sql['from'][] = 'hosts_groups hg';
			
			$def_sql['where'][] = 'hg.groupid=g.groupid';
			$def_sql['where'][] = 'EXISTS( SELECT a.applicationid '.
									' FROM applications a, httptest ht '.
									' WHERE a.hostid=hg.hostid '.
										' AND ht.applicationid=a.applicationid)';
		}
		else if($def_options['with_monitored_httptests']){
			$def_sql['from'][] = 'hosts_groups hg';
			
			$def_sql['where'][] = 'hg.groupid=g.groupid';
			$def_sql['where'][] = 'EXISTS( SELECT a.applicationid '.
									' FROM applications a, httptest ht '.
									' WHERE a.hostid=hg.hostid '.
										' AND ht.applicationid=a.applicationid '.
										' AND ht.status='.HTTPTEST_STATUS_ACTIVE.')';
		}
		
// graphs
		if($def_options['with_graphs']){
			$def_sql['from'][] = 'hosts_groups hg';
			
			$def_sql['where'][] = 'hg.groupid=g.groupid';
			$def_sql['where'][] = 'EXISTS( SELECT DISTINCT i.itemid '.
										' FROM items i, graphs_items gi '.
										' WHERE i.hostid=hg.hostid '.
											' AND i.itemid=gi.itemid)';
		}

// order
		if(str_in_array($def_options['order'], array('group','groupid'))){
			$def_sql['order'][] = 'g.'.$def_options['order'];
		}
		
// limit
		if(zbx_ctype_digit($def_options['limit'])){
			$def_sql['limit'] = $def_options['limit'];
		}
//-----

		$def_sql['order'][] = 'g.name';
				
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
				' WHERE '.DBin_node('g.groupid', $nodeid).
					$sql_where.
				$sql_order;
		$res = DBselect($sql,$sql_limit);
		while($group = DBfetch($res)){
			$result[$group['groupid']] = $group;			
		}
	return $result;
	}
	
	/**
	 * Add host group 
	 *
	 * Create Host group. Input parameter is array with following structure :
	 * Array('Name1', 'Name2', ...);
	 *
	 * @static
	 * @param array $groups multidimensional array with host groups data
	 * @return boolean 
	 */
	public static function add($groups){
		DBstart(false);
		$groupids = array();
		
		$result = false;
		foreach($groups as $group){
			$result = db_save_group($group);
			if(!$result) break;
			$groupids[$result] = $result;
		}
		
		$result = DBend($result);
		if($result)
			return $groupids;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Update host group
	 *
	 * Updates existing host groups, changing names. Input parameter is array with following structure :
	 * <code>
	 * Array( Array('groupid' => groupid1, 'name' => name1), Array( 'groupid => groupid2, 'name' => name2), ...);
	 * </code>
	 *
	 * @static
	 * @param array $groups multidimensional array with host groups data
	 * @return boolean
	 */
	public static function update($groups){		
		DBstart(false);
		$groupids = array();
		
		$result = false;
		foreach($groups as $group){
			$result = db_save_group($group['name'], $group['groupid']);
			if(!$result) break;
			$groupids[$result] = $result;
		}
			
		$result = DBend($result);
		if($result)
			return $groupids;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Delete host groups
	 *
	 * @static
	 * @param array $groupids 
	 * @return boolean
	 */	
	public static function delete($groupids){
		$result = delete_host_group($groupids);
		if($result)
			return $groupids;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Add hosts to host group
	 *
	 * @static
	 * @param array $data 
	 * @return boolean 
	 */
	public static function addHosts($data){
		
		$result =  add_host_to_group($data['hostids'], $data['groupid']);
		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Add hosts to host group
	 *
	 * @static
	 * @param array $data 
	 * @return boolean 
	 */
	public static function removeHosts($data){
		$groupid = $data['groupid'];
		$hostids = $data['hostids'];
		
		DBstart(false);
		foreach($hostids as $key => $hostid){
			$result = delete_host_from_group($hostid, $groupid);
			if(!$result) break;
		}
		
		$result = DBend($result);
		
		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

	/**
	 * Add groups to existing host groups
	 *
	 * @static
	 * @param string $hostid
	 * @param array $groupids
	 * @return boolean
	 */	
	public static function addGroupsToHost($data){
		$hostid = $data['hostid'];
		$groupids = $data['groupids'];
		$result = false;
		
		DBstart(false);
		foreach($groupids as $key => $groupid) {
			$hostgroupid = get_dbid("hosts_groups","hostgroupid");
			$result = DBexecute("insert into hosts_groups (hostgroupid,hostid,groupid) values ($hostgroupid, $hostid, $groupid)");
			if(!$result)
				return $result;
		}
		$result = DBend($result);
		
		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

	/**
	 * Update existing host groups with new one (rewrite) //work
	 *
	 * @static
	 * @param string $hostid 
	 * @param array $groupids 
	 * @return boolean
	 */	
	public static function updateGroupsToHost($data){
		$hostid = $data['hostid'];
		$groupids = $data['groupids'];
		$result = update_host_groups($hostid, $groupids);
		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

}
?>