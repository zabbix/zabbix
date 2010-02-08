<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
/**
 * File containing CHostGroup class for API.
 * @package API
 */
/**
 * Class containing methods for operations with HostGroups
 */
class CHostGroup extends CZBXAPI{
/**
 * Get HostGroups
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $params
 * @return array
 */
	public static function get($params){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('groupid', 'name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select'	=> array('groups' => 'g.groupid'),
			'from' 		=> array('groups g'),
			'where' 	=> array(),
			'order' 	=> array(),
			'limit' 	=> null);

		$def_options = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'monitored_hosts'			=> null,
			'templated_hosts' 			=> null,
			'real_hosts' 				=> null,
			'not_proxy_hosts'			=> null,
			'with_items'				=> null,
			'with_monitored_items' 		=> null,
			'with_historical_items'		=> null,
			'with_triggers'				=> null,
			'with_monitored_triggers' 	=> null,
			'with_httptests' 			=> null,
			'with_monitored_httptests'	=> null,
			'with_graphs'				=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
// filter
			'pattern' 					=> '',
// output
			'output'					=> API_OUTPUT_REFER,
			'extendoutput'				=> null,
			'select_hosts'				=> null,
			'count'						=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $params);


		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_hosts'])){
				$options['select_hosts'] = API_OUTPUT_EXTEND;
			}
		}


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
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);
			$sql_parts['where'][] = DBcondition('g.groupid', $options['groupids']);
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['hostid'] = 'hg.hostid';
			}

			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.hostid', $options['hostids']);
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
		}

// monitored_hosts, real_hosts, templated_hosts, not_proxy_hosts
		if(!is_null($options['monitored_hosts'])){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'h.hostid=hg.hostid';
			$sql_parts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
		}
		else if(!is_null($options['real_hosts'])){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'h.hostid=hg.hostid';
			$sql_parts['where'][] = 'h.status IN('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		}
		else if(!is_null($options['templated_hosts'])){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'h.hostid=hg.hostid';
			$sql_parts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
		}
		else if(!is_null($options['not_proxy_hosts'])){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'h.hostid=hg.hostid';
			$sql_parts['where'][] = 'h.status<>'.HOST_STATUS_PROXY;
		}

// with_items, with_monitored_items, with_historical_items
		if(!is_null($options['with_items'])){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid )';
		}
		else if(!is_null($options['with_monitored_items'])){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid AND i.status='.ITEM_STATUS_ACTIVE.')';
		}
		else if(!is_null($options['with_historical_items'])){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid AND (i.status='.ITEM_STATUS_ACTIVE.' OR i.status='.ITEM_STATUS_NOTSUPPORTED.') AND i.lastvalue IS NOT NULL)';
		}

// with_triggers, with_monitored_triggers
		if(!is_null($options['with_triggers'])){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS( SELECT t.triggerid '.
										' FROM items i, functions f, triggers t'.
										' WHERE i.hostid=hg.hostid '.
											' AND f.itemid=i.itemid '.
											' AND t.triggerid=f.triggerid)';
		}
		else if(!is_null($options['with_monitored_triggers'])){
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
		if(!is_null($options['with_httptests'])){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS( SELECT a.applicationid '.
									' FROM applications a, httptest ht '.
									' WHERE a.hostid=hg.hostid '.
										' AND ht.applicationid=a.applicationid)';
		}
		else if(!is_null($options['with_monitored_httptests'])){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS( SELECT a.applicationid '.
									' FROM applications a, httptest ht '.
									' WHERE a.hostid=hg.hostid '.
										' AND ht.applicationid=a.applicationid '.
										' AND ht.status='.HTTPTEST_STATUS_ACTIVE.')';
		}

// with_graphs
		if(!is_null($options['with_graphs'])){
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sql_parts['where'][] = 'EXISTS( SELECT DISTINCT i.itemid '.
										' FROM items i, graphs_items gi '.
										' WHERE i.hostid=hg.hostid '.
											' AND i.itemid=gi.itemid)';
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['groups'] = 'g.*';
		}

// count
		if(!is_null($options['count'])){
			$options['select_hosts'] = 0;
			$options['sortfield'] = '';

			$sql_parts['select'] = array('COUNT(DISTINCT g.groupid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(g.name) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['pattern']).'%');
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

		$sql = 'SELECT DISTINCT '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('g.groupid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($group = DBfetch($res)){
			if($options['count'])
				$result = $group;
			else{
				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$group['groupid']] = array('groupid' => $group['groupid']);
				}
				else{
					$groupids[$group['groupid']] = $group['groupid'];

					if(!isset($result[$group['groupid']])) $result[$group['groupid']]= array();

					if(!is_null($options['select_hosts']) && !isset($result[$group['groupid']]['hosts'])){
						$result[$group['groupid']]['hosts'] = array();
					}

// hostids
					if(isset($group['hostid']) && is_null($options['select_hosts'])){
						if(!isset($result[$group['groupid']]['hosts']))
							$result[$group['groupid']]['hosts'] = array();

						$result[$group['groupid']]['hosts'][] = array('hostid' => $group['hostid']);
						unset($group['hostid']);
					}

					$result[$group['groupid']] += $group;
				}
			}
		}

		if(($options['output'] != API_OUTPUT_EXTEND) || !is_null($options['count'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding hosts
		if(!is_null($options['select_hosts']) && str_in_array($options['select_hosts'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_hosts'],
				'groupids' => $groupids,
				'templated_hosts' => 1,
				'preservekeys' => 1
			);
			$hosts = API::Host()->get($obj_params);

			foreach($hosts as $hostid => $host){
				$hgroups = $host['groups'];
				unset($host['groups']);
				foreach($hgroups as $num => $group){
					$result[$group['groupid']]['hosts'][] = $host;
				}
			}
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}
/**
 * Get HostGroup ID by name
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $data
 * @param array $data['name']
 * @return string|boolean HostGroup ID or false if error
 */
	public static function getObjects($data){
		$result = array();
		$groupids = array();

		$sql = 'SELECT groupid '.
				' FROM groups '.
				' WHERE name='.zbx_dbstr($data['name']).
					' AND '.DBin_node('groupid', false);
		$res = DBselect($sql);
		while($group=DBfetch($res)){
			$groupids[$group['groupid']] = $group['groupid'];
		}

		if(!empty($groupids))
			$result = self::get(array('groupids'=>$groupids, 'extendoutput'=>1));

	return $result;
	}

/**
 * Add HostGroups
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $groups array with HostGroup names
 * @param array $groups['name']
 * @return array
 */
	public static function create($groups){
		global $USER_DETAILS;

		if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			self::exception(ZBX_API_ERROR_PERMISSIONS, 'Only Super Admins can create HostGroups');
		}

		$groups = zbx_toArray($groups);
		$groupids = array();

		foreach($groups as $num => $group){
			if(!is_array($group) || !isset($group['name']) || empty($group['name'])){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ name ]');
			}

			$group_exist = self::getObjects(array('name' => $group['name']));
			if(!empty($group_exist)){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'HostGroup [ '.$group['name'].' ] already exists');
			}

			$groupids[] = $groupid = get_dbid('groups', 'groupid');
			$values = array(
				'groupid' => $groupid,
				'name' => zbx_dbstr($group['name']),
				'internal' => ZBX_NOT_INTERNAL_GROUP
			);
			$sql = 'INSERT INTO groups ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')';
			if(!DBexecute($sql)) 
				self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
		}

		$options = array(
			'groupids' => $groupids, 
			'extendoutput' => 1, 
			'nopermissions' => 1
		);
		return self::get($options);
	}

/**
 * Update HostGroup
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $groups
 * @param array $groups[0]['name'], ...
 * @param array $groups[0]['groupid'], ...
 * @return boolean
 */
	public static function update($groups){
		$groups = zbx_toArray($groups);
		$groupids = zbx_objectValues($groups, 'groupid');

		if(empty($groups)){
			self::exception(ZBX_API_ERROR_PARAMETERS, 'Empty input parameter');
		}

		CValidate::hostGroups($groups);

		foreach($groups as $num => $group){
			$group_exist = self::getObjects(array('name' => $group['name']));
			$group_exist = reset($group_exist);

			if($group_exist && ($group_exist['groupid'] != $group['groupid'])){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'HostGroup [ '.$group['name'].' ] already exists');
			}

			$sql = 'UPDATE groups SET name='.zbx_dbstr($group['name']).' WHERE groupid='.$group['groupid'];
			if(!DBexecute($sql)){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
			}
		}

		$options = array(
			'groupids' => $groupids, 
			'extendoutput' => 1, 
			'nopermissions' => 1
		);
		return self::get($options);		
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
 * @param array $groups
 * @param array $groups[0,..]['groupid']
 * @return boolean
 */
	public static function delete($groups){
		$groups = zbx_toArray($groups);
		$groupids = array();
		
		if(empty($groups)){
			self::exception(ZBX_API_ERROR_PARAMETERS, 'Empty input parameter');
		}
		
		$del_groups = CValidate::hostGroups($groups, true);

		$result = delete_host_group($groupids);
		if(!$result)
			self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot delete group');


		return zbx_cleanHashes($del_groups);
	}

/**
 * Add Hosts to HostGroups. All Hosts are added to all HostGroups.
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $data
 * @param array $data['groups']
 * @param array $data['hosts']
 * @param array $data['templates']
 * @return boolean
 */
	public static function massAdd($data){
		$groups = zbx_toArray($data['groups']);
		$groupids = zbx_objectValues($groups, 'groupid');
		
		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : null;
		$hostids = is_null($hosts) ? array() : zbx_objectValues($hosts, 'hostid');
		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : null;
		$templateids = is_null($templates) ? array() : zbx_objectValues($templates, 'templateid');

		CValidate::hosts($hosts);
		CValidate::hostGroups($groups);
		CValidate::templates($templates);
		
		$objectids = array_merge($hostids, $templateids);

		$linked = array();
		$sql = 'SELECT hostid, groupid FROM hosts_groups WHERE '.DBcondition('hostid', $objectids).' AND '.DBcondition('groupid', $groupids);
		$linked_db = DBselect($sql);
		while($pair = DBfetch($linked_db)){
			$linked[$pair['groupid']] = array($pair['hostid'] => $pair['hostid']);
		}

		foreach($groupids as $gnum => $groupid){
			foreach($objectids as $hostid){
				if(isset($linked[$groupid]) && isset($linked[$groupid][$hostid])) continue;
				
				$values = array(
					'hostgroupid' => get_dbid('hosts_groups', 'hostgroupid'),
					'hostid' => $hostid,
					'groupid' => $groupid
				);
				$sql = 'INSERT INTO hosts_groups ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')';
				if(!DBexecute($sql))
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
			}
		}

		$options = array(
			'groupids' => $groupids,
			'extendoutput' => 1,
			'select_hosts' => 1,
			'nopermission' => 1
		);
		return self::get($options);
	}

/**
 * Remove Hosts from HostGroups
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $data
 * @param array $data['groups']
 * @param array $data['hosts']
 * @param array $data['templates']
 * @return boolean
 */
	public static function massRemove($data){
		$groups = zbx_toArray($data['groups']);
		$groupids = zbx_objectValues($groups, 'groupid');

		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : null;
		$hostids = is_null($hosts) ? array() : zbx_objectValues($hosts, 'hostid');
		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : null;
		$templateids = is_null($templates) ? array() : zbx_objectValues($templates, 'templateid');

		CValidate::hosts($hosts);
		CValidate::hostGroups($groups);
		CValidate::templates($templates);
		

		$objectids_to_unlink = array_merge($hostids, $templateids);
		$unlinkable = getUnlinkableHosts($groupids, $objectids_to_unlink);
		if(count($objectids_to_unlink) != count($unlinkable)){
			self::exception(ZBX_API_ERROR_PARAMETERS, 'One of the Objects is left without Hostgroup');
		}

		$sql = 'DELETE FROM hosts_groups WHERE '.DBcondition('hostid', $objectids_to_unlink).' AND '.DBcondition('groupid', $groupids);
		if(!DBexecute($sql))
			self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

		return self::get(array(
			'groupids' => $groupids,
			'extendoutput' => 1,
			'select_hosts' => 1,
			'nopermission' => 1)
		);
	}

/**
 * Update HostGroups with new Hosts (rewrite)
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $data
 * @param array $data['groups']
 * @param array $data['hosts']
 * @param array $data['templates']
 * @return boolean
 */
	public static function massUpdate($data){
		$groups = zbx_toArray($data['groups']);
		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : null;
		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : null;
		$groupids = zbx_objectValues($groups, 'groupid');
		$hostids = zbx_objectValues($hosts, 'hostid');
		$templateids = zbx_objectValues($templates, 'templateid');


		$hosts_to_unlink = $hosts_to_link = array();
		$options = array(
			'groupids' => $groupids,
			'preservekeys' => 1,
			// 'editable' => 1
		);
		if(!is_null($hosts)){
			$groups_hosts = CHost::get($options);
			$hosts_to_unlink = array_diff(array_keys($groups_hosts), $hostids);
			$hosts_to_link = array_diff($hostids, array_keys($groups_hosts));
		}

		$templates_to_unlink = $templates_to_link = array();
		if(!is_null($templates)){
			$groups_templates = CTemplate::get($options);
			$templates_to_unlink = array_diff(array_keys($groups_templates), $templateids);
			$templates_to_link = array_diff($templateids, array_keys($groups_templates));
		}

		$objectids_to_link = array_merge($hosts_to_link, $templates_to_link);
		$objectids_to_unlink = array_merge($hosts_to_unlink, $templates_to_unlink);

// PERMISSION {{{

		$hosts_to_check = array_merge($hosts_to_link, $hosts_to_unlink);
		CValidate::hosts(zbx_toObject($hosts_to_check, 'hostid'));
		
		CValidate::hostGroups($groups);
		
		$templates_to_check = array_merge($templates_to_link, $templates_to_unlink);
		CValidate::templates(zbx_toObject($templates_to_check, 'hostid'));
		

		$unlinkable = getUnlinkableHosts($groupids, $objectids_to_unlink);
		if(count($objectids_to_unlink) != count($unlinkable)){
			self::exception(ZBX_API_ERROR_PARAMETERS, 'One of the Objects is left without Hostgroup');
		}

		$sql = 'DELETE FROM hosts_groups WHERE '.DBcondition('groupid', $groupids).' AND '.DBcondition('hostid', $objectids_to_unlink);
		if(!DBexecute($sql))
			self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

		foreach($groupids as $gnum => $groupid){
			foreach($objectids_to_link as $objectid){
				$hostgroupid = get_dbid('hosts_groups', 'hostgroupid');
				$sql = "INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES ($hostgroupid, $objectid, $groupid)";
				if(!DBexecute($sql))
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
			}
		}

		
		return self::get(array(
			'groupids' => $groupids,
			'extendoutput' => 1,
			'select_hosts' => 1,
			'nopermission' => 1)
		);
	}

}

?>