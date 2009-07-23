<?php
/**
 * File containing CHostGroup class for API.
 * @package API
 */
/**
 * Class containing methods for operations with HostGroups
 *
 */
class CHostGroup {

	public static $error;

	/**
	 * Gets all HostGroup data from DB by HostGroup ID
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @static
	 * @param _array $group_data
	 * @param array $group_data['groupid']
	 * @return array|boolean HostGroup data as array or false if error
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
	 * Get HostGroup ID by HostGroup name
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @static
	 * @param array $group_data
	 * @param array $group_data['name']
	 * @return string|boolean HostGroup ID or false if error
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
	 * Get HostGroups
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @static
	 * @param array $params
	 * @return array
	 */
	public static function get($params){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('groupid', 'name'); // allowed columns for sorting


		$sql_parts = array(
			'select'	=> array('groups' => 'g.groupid'),
			'from' 		=> array('groups g'),
			'where' 	=> array(),
			'order' 	=> array(),
			'limit' 	=> null);

		$def_options = array(
			'nodeids'					=> 0,
			'groupids'					=> 0,
			'hostids'					=> 0,
			'monitored_hosts'			=> 0,
			'templated_hosts' 			=> 0,
			'real_hosts' 				=> 0,
			'not_proxy_hosts'			=> 0,
			'with_items'				=> 0,
			'with_monitored_items' 		=> 0,
			'with_historical_items'		=> 0,
			'with_triggers'				=> 0,
			'with_monitored_triggers' 	=> 0,
			'with_httptests' 			=> 0,
			'with_monitored_httptests'	=> 0,
			'with_graphs'				=> 0,
			'only_current_node'			=> 0,
			'editable'					=> 0,
			'nopermissions'				=> 0,
// output
			'extendoutput'				=> 0,
			'select_hosts'				=> 0,
			'count'						=> 0,
			'pattern' 					=> '',
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> 0);

		$options = array_merge($def_options, $params);


// *** ????? *** //
// nodes
// disabled by false
// TODO('check this ~106');
 		if(false && ZBX_DISTRIBUTED){
		 	$sql_parts['select'][] = 'n.name as node_name';
			$sql_parts['from'][] = 'nodes n';
			$sql_parts['where'][] = 'n.nodeid='.DBid2nodeid('g.groupid');
			$sql_parts['order'][] = 'node_name';
		}
// *** ????? *** //

// editable + PERMISSION CHECK
		if(defined('ZBX_API_REQUEST')){
			$options['nopermissions'] = false;
		}

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sql_parts['from']['r'] = 'rights r';
			$sql_parts['from']['ug'] = 'users_groups ug';
			$sql_parts['where'][] = 'r.id=g.groupid';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS( '.
									' SELECT gg.groupid '.
										' FROM groups gg, rights rr, users_groups ugg '.
										' WHERE rr.id=g.groupid '.
											' AND rr.groupid=ugg.usrgrpid '.
											' AND ugg.userid='.$userid.
											' AND rr.permission<'.$permission.')';
		}

// nodeids
		$nodeids = $options['nodeids'] ? $options['nodeids'] : get_current_nodeid(false);

// groupids
		if($options['groupids'] != 0){
			zbx_value2array($options['groupids']);
			$sql_parts['where'][] = DBcondition('g.groupid', $options['groupids']);
		}

// hostids
		if($options['hostids'] != 0){
			zbx_value2array($options['hostids']);
			if($options['extendoutput'] != 0){
				$sql_parts['select']['hostid'] = 'hg.hostid';
			}

			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.hostid', $options['hostids']);
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
		}

// monitored_hosts, real_hosts, templated_hosts, not_proxy_hosts
		if($options['monitored_hosts'] != 0){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'h.hostid=hg.hostid';
			$sql_parts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
		}
		else if($options['real_hosts'] != 0){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'h.hostid=hg.hostid';
			$sql_parts['where'][] = 'h.status IN('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		}
		else if($options['templated_hosts'] != 0){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'h.hostid=hg.hostid';
			$sql_parts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
		}
		else if($options['not_proxy_hosts'] != 0){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'h.hostid=hg.hostid';
			$sql_parts['where'][] = 'h.status<>'.HOST_STATUS_PROXY;
		}

// with_items, with_monitored_items, with_historical_items
		if($options['with_items'] != 0){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid )';
		}
		else if($options['with_monitored_items'] != 0){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid AND i.status='.ITEM_STATUS_ACTIVE.')';
		}
		else if($options['with_historical_items'] != 0){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid AND (i.status='.ITEM_STATUS_ACTIVE.' OR i.status='.ITEM_STATUS_NOTSUPPORTED.') AND i.lastvalue IS NOT NULL)';
		}

// with_triggers, with_monitored_triggers
		if($options['with_triggers'] != 0){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS( SELECT t.triggerid '.
										' FROM items i, functions f, triggers t'.
										' WHERE i.hostid=hg.hostid '.
											' AND f.itemid=i.itemid '.
											' AND t.triggerid=f.triggerid)';
		}
		else if($options['with_monitored_triggers'] != 0){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS( SELECT t.triggerid '.
										' FROM items i, functions f, triggers t'.
										' WHERE i.hostid=hg.hostid '.
											' AND i.status='.ITEM_STATUS_ACTIVE.
											' AND i.itemid=f.itemid '.
											' AND f.triggerid=t.triggerid '.
											' AND t.status='.TRIGGER_STATUS_ENABLED.')';
		}

// with_httptests, with_monitored_httptests
		if($options['with_httptests'] != 0){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS( SELECT a.applicationid '.
									' FROM applications a, httptest ht '.
									' WHERE a.hostid=hg.hostid '.
										' AND ht.applicationid=a.applicationid)';
		}
		else if($options['with_monitored_httptests'] != 0){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS( SELECT a.applicationid '.
									' FROM applications a, httptest ht '.
									' WHERE a.hostid=hg.hostid '.
										' AND ht.applicationid=a.applicationid '.
										' AND ht.status='.HTTPTEST_STATUS_ACTIVE.')';
		}

// with_graphs
		if($options['with_graphs'] != 0){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS( SELECT DISTINCT i.itemid '.
										' FROM items i, graphs_items gi '.
										' WHERE i.hostid=hg.hostid '.
											' AND i.itemid=gi.itemid)';
		}

// extendoutput
		if($options['extendoutput'] != 0){
			$sql_parts['select']['groups'] = 'g.*';
		}

// count
		if($options['count'] != 0){
			$options['select_hosts'] = 0;
			$options['sortfield'] = '';

			$sql_parts['select']['groups'] = 'COUNT(g.groupid) as rowscount';
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(g.name) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'g.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('g.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('g.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'g.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
		else if(!defined('ZBX_API_REQUEST')){
			$sql_parts['limit'] = 1001;
		}
//-----------

		$groupids = array();

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
				' WHERE '.DBin_node('g.groupid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($group = DBfetch($res)){
			if($options['count'])
				$result = $group;
			else{
				if(!$options['extendoutput']){
					$result[$group['groupid']] = $group['groupid'];
				}
				else{
					$groupids[$group['groupid']] = $group['groupid'];

					if(!isset($result[$group['groupid']])) $result[$group['groupid']]= array();

					if($options['select_hosts'] && !isset($result[$group['groupid']]['hostids'])){
						$result[$group['groupid']]['hostids'] = array();
						$result[$group['groupid']]['hosts'] = array();
					}

					// hostids
					if(isset($group['hostid'])){
						if(!isset($result[$group['groupid']]['hostids']))
							$result[$group['groupid']]['hostids'] = array();

						$result[$group['groupid']]['hostids'][$group['hostid']] = $group['hostid'];
						unset($group['hostid']);
					}

					$result[$group['groupid']] += $group;
				}
			}
		}

// Adding hosts
		if($options['select_hosts']){
			$obj_params = array('extendoutput' => 1, 'groupids' => $groupids, 'templated_hosts' => 1);
			$hosts = CHost::get($obj_params);
			foreach($hosts as $hostid => $host){
				foreach($host['groupids'] as $num => $groupid){
					$result[$groupid]['hostids'][$hostid] = $hostid;
					$result[$groupid]['hosts'][$hostid] = $host;
				}
			}
		}

	return $result;
	}

	/**
	 * Add HostGroup
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @static
	 * @param array $groups array with HostGroup names
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
	 * Update HostGruop
	 *
	 * Updates existing HostGroups, changing names. Input parameter is array with following structure :
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @static
	 * @param array $groups
	 * @param array $groups[0]['name'], ...
	 * @param array $groups[0]['groupid'], ...
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
	 * Delete HostGroups
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
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
	 * Add Hosts to HostGroup
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
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
	 * Add Hosts to HostGroup
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
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
	 * Add Groups to existing Host
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
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
	 * Update Host's HostGroups with new HostGroups (rewrite)
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
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
