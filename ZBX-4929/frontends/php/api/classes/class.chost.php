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
 * File containing CHost class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Hosts
 */
class CHost extends CZBXAPI{
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
 * @param boolean $options['with_monitored_triggers'] only with monitored triggers
 * @param boolean $options['with_httptests'] only with http tests
 * @param boolean $options['with_monitored_httptests'] only with monitored http tests
 * @param boolean $options['with_graphs'] only with graphs
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param int $options['extendoutput'] return all fields for Hosts
 * @param boolean $options['select_groups'] select HostGroups
 * @param boolean $options['select_templates'] select Templates
 * @param boolean $options['select_items'] select Items
 * @param boolean $options['select_triggers'] select Triggers
 * @param boolean $options['select_graphs'] select Graphs
 * @param boolean $options['select_applications'] select Applications
 * @param boolean $options['select_macros'] select Macros
 * @param boolean $options['select_profile'] select Profile
 * @param int $options['count'] count Hosts, returned column name is rowscount
 * @param string $options['pattern'] search hosts by pattern in Host name
 * @param string $options['extendPattern'] search hosts by pattern in Host name, ip and DNS
 * @param int $options['limit'] limit selection
 * @param string $options['sortfield'] field to sort by
 * @param string $options['sortorder'] sort order
 * @return array|boolean Host data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$nodeCheck = false;
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('hostid', 'host', 'status', 'dns', 'ip'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('hosts' => 'h.hostid'),
			'from' => array('hosts' => 'hosts h'),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null
		);

		$def_options = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'proxyids'					=> null,
			'templateids'				=> null,
			'itemids'					=> null,
			'triggerids'				=> null,
			'maintenanceids'			=> null,
			'graphids'					=> null,
			'dhostids'					=> null,
			'dserviceids'				=> null,
			'monitored_hosts'			=> null,
			'templated_hosts'			=> null,
			'proxy_hosts'				=> null,
			'with_items'				=> null,
			'with_monitored_items'		=> null,
			'with_historical_items'		=> null,
			'with_triggers'				=> null,
			'with_monitored_triggers'	=> null,
			'with_httptests'			=> null,
			'with_monitored_httptests'	=> null,
			'with_graphs'				=> null,
			'editable'					=> null,
			'nopermissions'				=> null,

// filter
			'filter'					=> null,
			'search'					=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,

// OutPut
			'output'					=> API_OUTPUT_REFER,
			'extendoutput'				=> null,
			'select_groups'				=> null,
			'selectParentTemplates'		=> null,
			'select_items'				=> null,
			'select_triggers'			=> null,
			'select_graphs'				=> null,
			'select_dhosts'				=> null,
			'select_dservices'			=> null,
			'select_applications'		=> null,
			'select_macros'				=> null,
			'select_profile'			=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);

		$options = zbx_array_merge($def_options, $options);

		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_groups'])){
				$options['select_groups'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['selectParentTemplates'])){
				$options['selectParentTemplates'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_items'])){
				$options['select_items'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_triggers'])){
				$options['select_triggers'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_graphs'])){
				$options['select_graphs'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_applications'])){
				$options['select_applications'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_macros'])){
				$options['select_macros'] = API_OUTPUT_EXTEND;
			}
		}

		if(is_array($options['output'])){
			unset($sql_parts['select']['hosts']);
			$sql_parts['select']['hostid'] = ' h.hostid';
			foreach($options['output'] as $key => $field){
				$sql_parts['select'][$field] = ' h.'.$field;
			}

			$options['output'] = API_OUTPUT_CUSTOM;
		}

// editable + PERMISSION CHECK
		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sql_parts['where'][] = 'EXISTS ('.
							' SELECT hh.hostid '.
							' FROM hosts hh, hosts_groups hgg, rights r, users_groups ug '.
							' WHERE hh.hostid=h.hostid '.
								' AND hh.hostid=hgg.hostid '.
								' AND r.id=hgg.groupid '.
								' AND r.groupid=ug.usrgrpid '.
								' AND ug.userid='.$userid.
								' AND r.permission>='.$permission.
								' AND NOT EXISTS( '.
									' SELECT hggg.groupid '.
									' FROM hosts_groups hggg, rights rr, users_groups gg '.
									' WHERE hggg.hostid=hgg.hostid '.
										' AND rr.id=hggg.groupid '.
										' AND rr.groupid=gg.usrgrpid '.
										' AND gg.userid='.$userid.
										' AND rr.permission<'.$permission.
								' )) ';
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);
			$sql_parts['where']['hostid'] = DBcondition('h.hostid', $options['hostids']);

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('h.hostid', $nodeids);
			}
		}

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}

			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where']['hgh'] = 'hg.hostid=h.hostid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['groupid'] = 'hg.groupid';
			}

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('hg.groupid', $nodeids);
			}
		}


// proxyids
		if(!is_null($options['proxyids'])){
			zbx_value2array($options['proxyids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['proxy_hostid'] = 'h.proxy_hostid';
			}
			$sql_parts['where'][] = DBcondition('h.proxy_hostid', $options['proxyids']);
		}

// templateids
		if(!is_null($options['templateids'])){
			zbx_value2array($options['templateids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['templateid'] = 'ht.templateid';
			}

			$sql_parts['from']['hosts_templates'] = 'hosts_templates ht';
			$sql_parts['where'][] = DBcondition('ht.templateid', $options['templateids']);
			$sql_parts['where']['hht'] = 'h.hostid=ht.hostid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['templateid'] = 'ht.templateid';
			}

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('ht.templateid', $nodeids);
			}
		}

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['itemid'] = 'i.itemid';
			}

			$sql_parts['from']['items'] = 'items i';
			$sql_parts['where'][] = DBcondition('i.itemid', $options['itemids']);
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('i.itemid', $nodeids);
			}
		}

// triggerids
		if(!is_null($options['triggerids'])){
			zbx_value2array($options['triggerids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['triggerid'] = 'f.triggerid';
			}

			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['where'][] = DBcondition('f.triggerid', $options['triggerids']);
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('f.triggerid', $nodeids);
			}
		}

// graphids
		if(!is_null($options['graphids'])){
			zbx_value2array($options['graphids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['graphid'] = 'gi.graphid';
			}

			$sql_parts['from']['graphs_items'] = 'graphs_items gi';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['where'][] = DBcondition('gi.graphid', $options['graphids']);
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('gi.graphid', $nodeids);
			}
		}

// dhostids
		if(!is_null($options['dhostids'])){
			zbx_value2array($options['dhostids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['dhostid'] = 'ds.dhostid';
			}

			$sql_parts['from']['dservices'] = 'dservices ds';
			$sql_parts['where'][] = DBcondition('ds.dhostid', $options['dhostids']);
			$sql_parts['where']['dsh'] = 'ds.ip=h.ip';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['dhostid'] = 'ds.dhostid';
			}
		}

// dserviceids
		if(!is_null($options['dserviceids'])){
			zbx_value2array($options['dserviceids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['dserviceid'] = 'ds.dserviceid';
			}

			$sql_parts['from']['dservices'] = 'dservices ds';
			$sql_parts['where'][] = DBcondition('ds.dserviceid', $options['dserviceids']);
			$sql_parts['where']['dsh'] = 'ds.ip=h.ip';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['dserviceid'] = 'ds.dserviceid';
			}
		}
// maintenanceids
		if(!is_null($options['maintenanceids'])){
			zbx_value2array($options['maintenanceids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['maintenanceid'] = 'mh.maintenanceid';
			}

			$sql_parts['from']['maintenances_hosts'] = 'maintenances_hosts mh';
			$sql_parts['where'][] = DBcondition('mh.maintenanceid', $options['maintenanceids']);
			$sql_parts['where']['hmh'] = 'h.hostid=mh.hostid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['maintenanceid'] = 'mh.maintenanceid';
			}
		}

// node check !!!!!
// should last, after all ****IDS checks
		if(!$nodeCheck){
			$nodeCheck = true;
			$sql_parts['where'][] = DBin_node('h.hostid', $nodeids);
		}

// monitored_hosts, templated_hosts
		if(!is_null($options['monitored_hosts'])){
			$sql_parts['where']['status'] = 'h.status='.HOST_STATUS_MONITORED;
		}
		else if(!is_null($options['templated_hosts'])){
			$sql_parts['where']['status'] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')';
		}
		else if(!is_null($options['proxy_hosts'])){
			$sql_parts['where']['status'] = 'h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')';
		}
		else{
			$sql_parts['where']['status'] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		}

// with_items, with_monitored_items, with_historical_items
		if(!is_null($options['with_items'])){
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid )';
		}
		else if(!is_null($options['with_monitored_items'])){
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid AND i.status='.ITEM_STATUS_ACTIVE.')';
		}
		else if(!is_null($options['with_historical_items'])){
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid AND (i.status='.ITEM_STATUS_ACTIVE.' OR i.status='.ITEM_STATUS_NOTSUPPORTED.') AND i.lastvalue IS NOT NULL)';
		}

// with_triggers, with_monitored_triggers
		if(!is_null($options['with_triggers'])){
			$sql_parts['where'][] = 'EXISTS( '.
					' SELECT i.itemid '.
					' FROM items i, functions f, triggers t '.
					' WHERE i.hostid=h.hostid '.
						' AND i.itemid=f.itemid '.
						' AND f.triggerid=t.triggerid)';
		}
		else if(!is_null($options['with_monitored_triggers'])){
			$sql_parts['where'][] = 'EXISTS( '.
					' SELECT i.itemid '.
					' FROM items i, functions f, triggers t '.
					' WHERE i.hostid=h.hostid '.
						' AND i.status='.ITEM_STATUS_ACTIVE.
						' AND i.itemid=f.itemid '.
						' AND f.triggerid=t.triggerid '.
						' AND t.status='.TRIGGER_STATUS_ENABLED.')';
		}

// with_httptests, with_monitored_httptests
		if(!is_null($options['with_httptests'])){
			$sql_parts['where'][] = 'EXISTS( '.
					' SELECT a.applicationid '.
					' FROM applications a, httptest ht '.
					' WHERE a.hostid=h.hostid '.
						' AND ht.applicationid=a.applicationid)';
		}
		else if(!is_null($options['with_monitored_httptests'])){
			$sql_parts['where'][] = 'EXISTS( '.
					' SELECT a.applicationid '.
					' FROM applications a, httptest ht '.
					' WHERE a.hostid=h.hostid '.
						' AND ht.applicationid=a.applicationid '.
						' AND ht.status='.HTTPTEST_STATUS_ACTIVE.')';
		}

// with_graphs
		if(!is_null($options['with_graphs'])){
			$sql_parts['where'][] = 'EXISTS ('.
				'SELECT 1'.
				' FROM items i,graphs_items gi'.
				' WHERE i.hostid=h.hostid'.
					' AND i.itemid=gi.itemid '.zbx_limit(1).')';
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['hosts'] = 'h.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT h.hostid) as rowscount');

//groupCount
			if(!is_null($options['groupCount'])){
				foreach($sql_parts['group'] as $key => $fields){
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('hosts h', $options, $sql_parts);
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('hosts h', $options, $sql_parts);
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][$options['sortfield']] = 'h.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('h.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('h.*', $sql_parts['select'])){
				$sql_parts['select'][$options['sortfield']] = 'h.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//-------


		$hostids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['group'] = array_unique($sql_parts['group']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_group = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['group']))		$sql_where.= ' GROUP BY '.implode(',',$sql_parts['group']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.$sql_where.
				$sql_group.
				$sql_order;
 //SDI($sql);
		$res = DBselect($sql, $sql_limit);
		while($host = DBfetch($res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $host;
				else
					$result = $host['rowscount'];
			}
			else{
				$hostids[$host['hostid']] = $host['hostid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$host['hostid']] = array('hostid' => $host['hostid']);
				}
				else{
					if(!isset($result[$host['hostid']])) $result[$host['hostid']] = array();

					if(!is_null($options['select_groups']) && !isset($result[$host['hostid']]['groups'])){
						$result[$host['hostid']]['groups'] = array();
					}

					if(!is_null($options['selectParentTemplates']) && !isset($result[$host['hostid']]['parentTemplates'])){
						$result[$host['hostid']]['parentTemplates'] = array();
					}

					if(!is_null($options['select_items']) && !isset($result[$host['hostid']]['items'])){
						$result[$host['hostid']]['items'] = array();
					}
					if(!is_null($options['select_profile']) && !isset($result[$host['hostid']]['profile'])){
						$result[$host['hostid']]['profile'] = array();
						$result[$host['hostid']]['profile_ext'] = array();
					}

					if(!is_null($options['select_triggers']) && !isset($result[$host['hostid']]['triggers'])){
						$result[$host['hostid']]['triggers'] = array();
					}

					if(!is_null($options['select_graphs']) && !isset($result[$host['hostid']]['graphs'])){
						$result[$host['hostid']]['graphs'] = array();
					}

					if(!is_null($options['select_dhosts']) && !isset($result[$host['hostid']]['dhosts'])){
						$result[$host['hostid']]['dhosts'] = array();
					}

					if(!is_null($options['select_dservices']) && !isset($result[$host['hostid']]['dservices'])){
						$result[$host['hostid']]['dservices'] = array();
					}

					if(!is_null($options['select_applications']) && !isset($result[$host['hostid']]['applications'])){
						$result[$host['hostid']]['applications'] = array();
					}

					if(!is_null($options['select_macros']) && !isset($result[$host['hostid']]['macros'])){
						$result[$host['hostid']]['macros'] = array();
					}

//					if(!is_null($options['select_maintenances']) && !isset($result[$host['hostid']]['maintenances'])){
//						$result[$host['hostid']]['maintenances'] = array();
//					}

// groupids
					if(isset($host['groupid']) && is_null($options['select_groups'])){
						if(!isset($result[$host['hostid']]['groups']))
							$result[$host['hostid']]['groups'] = array();

						$result[$host['hostid']]['groups'][] = array('groupid' => $host['groupid']);
						unset($host['groupid']);
					}

// templateids
					if(isset($host['templateid'])){
						if(!isset($result[$host['hostid']]['templates']))
							$result[$host['hostid']]['templates'] = array();

						$result[$host['hostid']]['templates'][] = array('templateid' => $host['templateid']);
						unset($host['templateid']);
					}

// triggerids
					if(isset($host['triggerid']) && is_null($options['select_triggers'])){
						if(!isset($result[$host['hostid']]['triggers']))
							$result[$host['hostid']]['triggers'] = array();

						$result[$host['hostid']]['triggers'][] = array('triggerid' => $host['triggerid']);
						unset($host['triggerid']);
					}

// itemids
					if(isset($host['itemid']) && is_null($options['select_items'])){
						if(!isset($result[$host['hostid']]['items']))
							$result[$host['hostid']]['items'] = array();

						$result[$host['hostid']]['items'][] = array('itemid' => $host['itemid']);
						unset($host['itemid']);
					}

// graphids
					if(isset($host['graphid']) && is_null($options['select_graphs'])){
						if(!isset($result[$host['hostid']]['graphs']))
							$result[$host['hostid']]['graphs'] = array();

						$result[$host['hostid']]['graphs'][] = array('graphid' => $host['graphid']);
						unset($host['graphid']);
					}

// dhostids
					if(isset($host['dhostid']) && is_null($options['select_dhosts'])){
						if(!isset($result[$host['hostid']]['dhosts']))
							$result[$host['hostid']]['dhosts'] = array();

						$result[$host['hostid']]['dhosts'][] = array('dhostid' => $host['dhostid']);
						unset($host['dhostid']);
					}

// dserviceids
					if(isset($host['dserviceid']) && is_null($options['select_dservices'])){
						if(!isset($result[$host['hostid']]['dservices']))
							$result[$host['hostid']]['dservices'] = array();

						$result[$host['hostid']]['dservices'][] = array('dserviceid' => $host['dserviceid']);
						unset($host['dserviceid']);
					}
// maintenanceids
					if(isset($host['maintenanceid'])){
						if(!isset($result[$host['hostid']]['maintenanceid']))
							$result[$host['hostid']]['maintenances'] = array();

						$result[$host['hostid']]['maintenances'][] = array('maintenanceid' => $host['maintenanceid']);
//						unset($host['maintenanceid']);
					}
//---

					$result[$host['hostid']] += $host;
				}
			}
		}

Copt::memoryPick();
		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
// Adding Groups
		if(!is_null($options['select_groups']) && str_in_array($options['select_groups'], $subselects_allowed_outputs)){
			$obj_params = array(
					'nodeids' => $nodeids,
					'output' => $options['select_groups'],
					'hostids' => $hostids,
					'preservekeys' => 1
				);
			$groups = CHostgroup::get($obj_params);

			foreach($groups as $groupid => $group){
				$ghosts = $group['hosts'];
				unset($group['hosts']);
				foreach($ghosts as $num => $host){
					$result[$host['hostid']]['groups'][] = $group;
				}
			}
		}

// Adding Profiles
		if(!is_null($options['select_profile'])){
			$sql = 'SELECT hp.* '.
				' FROM hosts_profiles hp '.
				' WHERE '.DBcondition('hp.hostid', $hostids);
			$db_profile = DBselect($sql);
			while($profile = DBfetch($db_profile))
				$result[$profile['hostid']]['profile'] = $profile;


			$sql = 'SELECT hpe.* '.
				' FROM hosts_profiles_ext hpe '.
				' WHERE '.DBcondition('hpe.hostid', $hostids);
			$db_profile_ext = DBselect($sql);
			while($profile_ext = DBfetch($db_profile_ext))
				$result[$profile_ext['hostid']]['profile_ext'] = $profile_ext;
		}

// Adding Templates
		if(!is_null($options['selectParentTemplates'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'hostids' => $hostids,
				'preservekeys' => 1
			);

			if(is_array($options['selectParentTemplates']) || str_in_array($options['selectParentTemplates'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectParentTemplates'];
				$templates = CTemplate::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($templates, 'host');
				foreach($templates as $templateid => $template){
					unset($templates[$templateid]['hosts']);
					$count = array();
					foreach($template['hosts'] as $hnum => $host){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$host['hostid']])) $count[$host['hostid']] = 0;
							$count[$host['hostid']]++;

							if($count[$host['hostid']] > $options['limitSelects']) continue;
						}

						$result[$host['hostid']]['parentTemplates'][] = &$templates[$templateid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectParentTemplates']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$templates = CTemplate::get($obj_params);
				$templates = zbx_toHash($templates, 'hostid');
				foreach($result as $hostid => $host){
					if(isset($templates[$hostid]))
						$result[$hostid]['templates'] = $templates[$hostid]['rowscount'];
					else
						$result[$hostid]['templates'] = 0;
				}
			}
		}

// Adding Items
		if(!is_null($options['select_items'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'hostids' => $hostids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);

			if(is_array($options['select_items']) || str_in_array($options['select_items'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['select_items'];
				$items = CItem::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($items, 'description');
				foreach($items as $itemid => $item){
					unset($items[$itemid]['hosts']);
					foreach($item['hosts'] as $hnum => $host){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$host['hostid']])) $count[$host['hostid']] = 0;
							$count[$host['hostid']]++;

							if($count[$host['hostid']] > $options['limitSelects']) continue;
						}

						$result[$host['hostid']]['items'][] = &$items[$itemid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['select_items']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$items = CItem::get($obj_params);
				$items = zbx_toHash($items, 'hostid');
				foreach($result as $hostid => $host){
					if(isset($items[$hostid]))
						$result[$hostid]['items'] = $items[$hostid]['rowscount'];
					else
						$result[$hostid]['items'] = 0;
				}
			}
		}

// Adding triggers
		if(!is_null($options['select_triggers'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'hostids' => $hostids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);

			if(is_array($options['select_triggers']) || str_in_array($options['select_triggers'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['select_triggers'];
				$triggers = CTrigger::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($triggers, 'description');
				foreach($triggers as $triggerid => $trigger){
					unset($triggers[$triggerid]['hosts']);

					foreach($trigger['hosts'] as $hnum => $host){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$host['hostid']])) $count[$host['hostid']] = 0;
							$count[$host['hostid']]++;

							if($count[$host['hostid']] > $options['limitSelects']) continue;
						}

						$result[$host['hostid']]['triggers'][] = &$triggers[$triggerid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['select_triggers']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$triggers = CTrigger::get($obj_params);
				$triggers = zbx_toHash($triggers, 'hostid');
				foreach($result as $hostid => $host){
					if(isset($triggers[$hostid]))
						$result[$hostid]['triggers'] = $triggers[$hostid]['rowscount'];
					else
						$result[$hostid]['triggers'] = 0;
				}
			}
		}

// Adding graphs
		if(!is_null($options['select_graphs'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'hostids' => $hostids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);

			if(is_array($options['select_graphs']) || str_in_array($options['select_graphs'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['select_graphs'];
				$graphs = CGraph::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($graphs, 'name');
				foreach($graphs as $graphid => $graph){
					unset($graphs[$graphid]['hosts']);

					foreach($graph['hosts'] as $hnum => $host){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$host['hostid']])) $count[$host['hostid']] = 0;
							$count[$host['hostid']]++;

							if($count[$host['hostid']] > $options['limitSelects']) continue;
						}

						$result[$host['hostid']]['graphs'][] = &$graphs[$graphid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['select_graphs']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$graphs = CGraph::get($obj_params);
				$graphs = zbx_toHash($graphs, 'hostid');
				foreach($result as $hostid => $host){
					if(isset($graphs[$hostid]))
						$result[$hostid]['graphs'] = $graphs[$hostid]['rowscount'];
					else
						$result[$hostid]['graphs'] = 0;
				}
			}
		}

// Adding discovery hosts
		if(!is_null($options['select_dhosts'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'hostids' => $hostids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);

			if(is_array($options['select_dhosts']) || str_in_array($options['select_dhosts'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['select_dhosts'];
				$dhosts = CDHost::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($dhosts, 'dhostid');
				foreach($dhosts as $dhostid => $dhost){
					unset($dhosts[$dhostid]['hosts']);

					foreach($dhost['hosts'] as $hnum => $host){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$host['hostid']])) $count[$host['hostid']] = 0;
							$count[$host['hostid']]++;

							if($count[$host['hostid']] > $options['limitSelects']) continue;
						}

						$result[$host['hostid']]['dhosts'][] = &$dhosts[$dhostid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['select_dhosts']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$dhosts = CDHost::get($obj_params);
				$dhosts = zbx_toHash($dhosts, 'hostid');
				foreach($result as $hostid => $host){
					if(isset($dhosts[$hostid]))
						$result[$hostid]['dhosts'] = $dhosts[$hostid]['rowscount'];
					else
						$result[$hostid]['dhosts'] = 0;
				}
			}
		}

// Adding applications
		if(!is_null($options['select_applications'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'hostids' => $hostids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);

			if(is_array($options['select_applications']) || str_in_array($options['select_applications'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['select_applications'];
				$applications = CApplication::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($applications, 'name');
				foreach($applications as $applicationid => $application){
					unset($applications[$applicationid]['hosts']);

					foreach($application['hosts'] as $hnum => $host){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$host['hostid']])) $count[$host['hostid']] = 0;
							$count[$host['hostid']]++;

							if($count[$host['hostid']] > $options['limitSelects']) continue;
						}

						$result[$host['hostid']]['applications'][] = &$applications[$applicationid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['select_applications']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$applications = CApplication::get($obj_params);
				$applications = zbx_toHash($applications, 'hostid');
				foreach($result as $hostid => $host){
					if(isset($applications[$hostid]))
						$result[$hostid]['applications'] = $applications[$hostid]['rowscount'];
					else
						$result[$hostid]['applications'] = 0;
				}
			}
		}

// Adding macros
		if(!is_null($options['select_macros']) && str_in_array($options['select_macros'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_macros'],
				'hostids' => $hostids,
				'preservekeys' => 1
			);

			$macros = CUserMacro::get($obj_params);
			foreach($macros as $macroid => $macro){
				$mhosts = $macro['hosts'];
				unset($macro['hosts']);
				foreach($mhosts as $num => $host){
					$result[$host['hostid']]['macros'][] = $macro;
				}
			}
		}

Copt::memoryPick();
// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

/**
 * Get Host ID by Host name
 *
 * @param _array $host_data
 * @param string $host_data['host']
 * @return int|boolean
 */
	public static function getObjects($hostData){
		$options = array(
			'filter' => $hostData,
			'output'=>API_OUTPUT_EXTEND
		);

		if(isset($hostData['node']))
			$options['nodeids'] = getNodeIdByNodeName($hostData['node']);
		else if(isset($hostData['nodeids']))
			$options['nodeids'] = $hostData['nodeids'];

		$result = self::get($options);

	return $result;
	}

	public static function exists($object){
		$keyFields = array(array('hostid', 'host'));

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);

		if(isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if(isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = self::get($options);

	return !empty($objs);
	}

/**
 * Add Host
 *
 * @param _array $hosts multidimensional array with Hosts data
 * @param string $hosts['host'] Host name.
 * @param array $hosts['groups'] array of HostGroup objects with IDs add Host to.
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
	public static function create($hosts){
		$hosts = zbx_toArray($hosts);
		$hostids = array();
		$groupids = array();

		try{
			self::BeginTransaction(__METHOD__);
// BASIC VALIDATION {{{
			foreach($hosts as $hnum => $host){
				$hosts[$hnum]['groups'] = zbx_toArray($hosts[$hnum]['groups']);

				foreach($hosts[$hnum]['groups'] as $gnum => $group){
					$groupids[$group['groupid']] = $group['groupid'];
				}
			}
// }}}

// PERMISSIONS {{{
			$upd_groups = CHostGroup::get(array(
				'groupids' => $groupids,
				'editable' => 1,
				'preservekeys' => 1));
			foreach($groupids as $gnum => $groupid){
				if(!isset($upd_groups[$groupid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
				}
			}
// }}} PERMISSIONS

			foreach($hosts as $host){
				self::validate($host);

				$hostid = get_dbid('hosts', 'hostid');
				$hostids[] = $hostid;
				$result = DBexecute('INSERT INTO hosts (hostid, proxy_hostid, host, port, status, useip, dns, ip, disable_until, available,'.
					'useipmi,ipmi_port,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,ipmi_ip) VALUES ('.
					$hostid.','.
					$host['proxy_hostid'].','.
					zbx_dbstr($host['host']).','.
					$host['port'].','.
					$host['status'].','.
					$host['useip'].','.
					zbx_dbstr($host['dns']).','.
					zbx_dbstr($host['ip']).
					',0,'.
					HOST_AVAILABLE_UNKNOWN.','.
					$host['useipmi'].','.
					$host['ipmi_port'].','.
					$host['ipmi_authtype'].','.
					$host['ipmi_privilege'].','.
					zbx_dbstr($host['ipmi_username']).','.
					zbx_dbstr($host['ipmi_password']).','.
					zbx_dbstr($host['ipmi_ip']).')'
				);
				if(!$result){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
				}

				foreach($host['groups'] as $group){
					$hostgroupid = get_dbid('hosts_groups', 'hostgroupid');
					$result = DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES ($hostgroupid, $hostid, {$group['groupid']})");
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
					}
				}

				$host['hostid'] = $hostid;
				$options = array();
				$options['hosts'] = $host;
				if(isset($host['templates']) && !is_null($host['templates']))
					$options['templates'] = $host['templates'];
				if(isset($host['macros']) && !is_null($host['macros']))
					$options['macros'] = $host['macros'];

				$result = CHost::massAdd($options);
				if(!$result){
					self::exception();
				}

				if(isset($host['profile']) && !empty($host['profile'])){
					$fields = array_keys($host['profile']);
					$fields = implode(', ', $fields);

					$values = array_map('zbx_dbstr', $host['profile']);
					$values = implode(', ', $values);

					DBexecute('INSERT INTO hosts_profiles (hostid, '.$fields.') VALUES ('.$hostid.', '.$values.')');
				}

				if(isset($host['extendedProfile']) && !empty($host['extendedProfile'])){
					$fields = array_keys($host['extendedProfile']);
					$fields = implode(', ', $fields);

					$values = array_map('zbx_dbstr', $host['extendedProfile']);
					$values = implode(', ', $values);

					DBexecute('INSERT INTO hosts_profiles_ext (hostid, '.$fields.') VALUES ('.$hostid.', '.$values.')');
				}
			}

			self::EndTransaction(true, __METHOD__);
			return array('hostids' => $hostids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Update Host
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
 * @param string $hosts['groups'] groups
 * @return boolean
 */
	public static function update($hosts){
		$hosts = zbx_toArray($hosts);
		$hostids = zbx_objectValues($hosts, 'hostid');

		try{
			self::BeginTransaction(__METHOD__);
			$options = array(
				'hostids' => $hostids,
				'editable' => 1,
				'preservekeys' => 1
			);
			$upd_hosts = self::get($options);
			foreach($hosts as $host){
				if(!isset($upd_hosts[$host['hostid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_YOU_DO_NOT_HAVE_ENOUGH_RIGHTS);
				}
			}

			foreach($hosts as $host){
				$tmp = $host;
				$host['hosts'] = $tmp;

				$result = self::massUpdate($host);
				if(!$result) self::exception(ZBX_API_ERROR_INTERNAL, S_HOST_UPDATE_FAILED);
			}

			self::EndTransaction(true, __METHOD__);
			return array('hostids' => $hostids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Add Hosts to HostGroups. All Hosts are added to all HostGroups.
 *
 * @param array $data
 * @param array $data['groups']
 * @param array $data['templates']
 * @param array $data['macros']
 * @return array
 */
	public static function massAdd($data){
		$data['hosts'] = zbx_toArray($data['hosts']);

		try{
			self::BeginTransaction(__METHOD__);
			$options = array(
				'hostids' => zbx_objectValues($data['hosts'], 'hostid'),
				'editable' => 1,
				'preservekeys' => 1
			);
			$upd_hosts = self::get($options);

			foreach($data['hosts'] as $hnum => $host){
				if(!isset($upd_hosts[$host['hostid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, 'You do not have enough rights for operation');
				}
			}

			if(isset($data['groups']) && !empty($data['groups'])){
				$options = array(
					'groups' => zbx_toArray($data['groups']),
					'hosts' => $data['hosts']
				);
				$result = CHostGroup::massAdd($options);
				if(!$result) self::exception();
			}

			if(isset($data['templates']) && !empty($data['templates'])){
				$options = array(
					'hosts' => $data['hosts'],
					'templates' => zbx_toArray($data['templates'])
				);
				$result = CTemplate::massAdd($options);
				if(!$result) self::exception();
			}

			if(isset($data['macros']) && !empty($data['macros'])){
				$options = array(
					'hosts' => $data['hosts'],
					'macros' => $data['macros']
				);

				$result = CUserMacro::massAdd($options);
				if(!$result) self::exception();
			}

			self::EndTransaction(true, __METHOD__);
			return array('hostids' => zbx_objectValues($data['hosts'], 'hostid'));
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			// Ignore exception with no error messages
			if (count($error) == 0) {
				return false;
			}
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Mass update hosts
 *
 * @param _array $hosts multidimensional array with Hosts data
 * @param array $hosts['hosts'] Array of Host objects to update
 * @param string $hosts['fields']['host'] Host name.
 * @param array $hosts['fields']['groupids'] HostGroup IDs add Host to.
 * @param int $hosts['fields']['port'] Port. OPTIONAL
 * @param int $hosts['fields']['status'] Host Status. OPTIONAL
 * @param int $hosts['fields']['useip'] Use IP. OPTIONAL
 * @param string $hosts['fields']['dns'] DNS. OPTIONAL
 * @param string $hosts['fields']['ip'] IP. OPTIONAL
 * @param int $hosts['fields']['proxy_hostid'] Proxy Host ID. OPTIONAL
 * @param int $hosts['fields']['useipmi'] Use IPMI. OPTIONAL
 * @param string $hosts['fields']['ipmi_ip'] IPMAI IP. OPTIONAL
 * @param int $hosts['fields']['ipmi_port'] IPMI port. OPTIONAL
 * @param int $hosts['fields']['ipmi_authtype'] IPMI authentication type. OPTIONAL
 * @param int $hosts['fields']['ipmi_privilege'] IPMI privilege. OPTIONAL
 * @param string $hosts['fields']['ipmi_username'] IPMI username. OPTIONAL
 * @param string $hosts['fields']['ipmi_password'] IPMI password. OPTIONAL
 *
 * @return boolean
 */
	public static function massUpdate($data){
		$hosts = zbx_toArray($data['hosts']);
		$hostids = zbx_objectValues($hosts, 'hostid');

		unset($data['hosts']);
		if (empty($data)) {
			return true;
		}

		try{
			self::BeginTransaction(__METHOD__);

			// validate data
			$fields = array_keys($data);
			self::validate($data, $fields);

			$options = array(
				'hostids' => $hostids,
				'editable' => true,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => true
			);
			$upd_hosts = self::get($options);
			foreach($hosts as $hnum => $host){
				if(!isset($upd_hosts[$host['hostid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}


// UPDATE HOSTS PROPERTIES {{{
			if(isset($data['host'])){
				if(count($hosts) > 1){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot mass update host name');
				}

				$cur_host = reset($hosts);
			}

			$sql_set = array();
			if(isset($data['proxy_hostid'])) $sql_set[] = 'proxy_hostid='.$data['proxy_hostid'];
			if(isset($data['host'])) $sql_set[] = 'host='.zbx_dbstr($data['host']);
			if(isset($data['port'])) $sql_set[] = 'port='.$data['port'];
			if(isset($data['status'])) $sql_set[] = 'status='.$data['status'];
			if(isset($data['useip'])) $sql_set[] = 'useip='.$data['useip'];
			if(isset($data['dns'])) $sql_set[] = 'dns='.zbx_dbstr($data['dns']);
			if(isset($data['ip'])) $sql_set[] = 'ip='.zbx_dbstr($data['ip']);
			if(isset($data['useipmi'])) $sql_set[] = 'useipmi='.$data['useipmi'];
			if(isset($data['ipmi_port'])) $sql_set[] = 'ipmi_port='.$data['ipmi_port'];
			if(isset($data['ipmi_authtype'])) $sql_set[] = 'ipmi_authtype='.$data['ipmi_authtype'];
			if(isset($data['ipmi_privilege'])) $sql_set[] = 'ipmi_privilege='.$data['ipmi_privilege'];
			if(isset($data['ipmi_username'])) $sql_set[] = 'ipmi_username='.zbx_dbstr($data['ipmi_username']);
			if(isset($data['ipmi_password'])) $sql_set[] = 'ipmi_password='.zbx_dbstr($data['ipmi_password']);
			if(isset($data['ipmi_ip'])) $sql_set[] = 'ipmi_ip='.zbx_dbstr($data['ipmi_ip']);

			if(!empty($sql_set)){
				$sql = 'UPDATE hosts SET ' . implode(', ', $sql_set) . ' WHERE '.DBcondition('hostid', $hostids);
				$result = DBexecute($sql);
				if(isset($data['status']))
					update_host_status($hostids, $data['status']);
			}
// }}} UPDATE HOSTS PROPERTIES


// UPDATE HOSTGROUPS LINKAGE {{{
			if(isset($data['groups']) && !is_null($data['groups'])){
				$data['groups'] = zbx_toArray($data['groups']);

				$host_groups = CHostGroup::get(array('hostids' => $hostids));
				$host_groupids = zbx_objectValues($host_groups, 'groupid');
				$new_groupids = zbx_objectValues($data['groups'], 'groupid');

				$result = self::massAdd(array(
					'hosts' => $hosts,
					'groups' => $data['groups']
				));
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, S_CANNOT_ADD_GROUP);
				}

				$groupids_to_del = array_diff($host_groupids, $new_groupids);

				if(!empty($groupids_to_del)){
					$result = self::massRemove(array('hostids' => $hostids, 'groupids' => $groupids_to_del));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, S_CANNOT_DELETE_GROUP);
					}
				}
			}
// }}} UPDATE HOSTGROUPS LINKAGE


			$data['templates_clear'] = isset($data['templates_clear']) ? zbx_toArray($data['templates_clear']) : array();
			$cleared_templateids = array();
			foreach($hostids as $hostid){
				foreach($data['templates_clear'] as $tpl){
					$result = unlink_template($hostid, $tpl['templateid'], false);
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot unlink template [ '.$tpl['templateid'].' ]');
					}
					$cleared_templateids[] = $tpl['templateid'];
				}
			}


// UPDATE TEMPLATE LINKAGE {{{
			if(isset($data['templates']) && !is_null($data['templates'])){
				$opt = array(
					'hostids' => $hostids,
					'output' => API_OUTPUT_SHORTEN,
					'preservekeys' => true,
				);
				$host_templates = CTemplate::get($opt);

				$host_templateids = array_keys($host_templates);
				$new_templateids = zbx_objectValues($data['templates'], 'templateid');

				$templates_to_del = array_diff($host_templateids, $new_templateids);
				$templates_to_del = array_diff($templates_to_del, $cleared_templateids);

				if(!empty($templates_to_del)){
					$result = self::massRemove(array('hostids' => $hostids, 'templateids' => $templates_to_del));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, S_CANNOT_UNLINK_TEMPLATE);
					}
				}

				$result = self::massAdd(array('hosts' => $hosts, 'templates' => $data['templates']));
				if(!$result){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_CANNOT_LINK_TEMPLATE);
				}
			}
// }}} UPDATE TEMPLATE LINKAGE


// UPDATE MACROS {{{
			if(isset($data['macros']) && !is_null($data['macros'])){
				$macrosToAdd = zbx_toHash($data['macros'], 'macro');

				$hostMacros = CUserMacro::get(array(
					'hostids' => $hostids,
					'output' => API_OUTPUT_EXTEND,
				));
				$hostMacros = zbx_toHash($hostMacros, 'macro');

// Delete
				$macrosToDelete = array();
				foreach($hostMacros as $hmnum => $hmacro){
					if(!isset($macrosToAdd[$hmacro['macro']])){
						$macrosToDelete[] = $hmacro['macro'];
					}
				}
// Update
				$macrosToUpdate = array();
				foreach($macrosToAdd as $nhmnum => $nhmacro){
					if(isset($hostMacros[$nhmacro['macro']])){
						$macrosToUpdate[] = $nhmacro;
						unset($macrosToAdd[$nhmnum]);
					}
				}
//----

				if(!empty($macrosToDelete)){
					$result = self::massRemove(array('hostids' => $hostids, 'macros' => $macrosToDelete));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Can\'t remove macro');
					}
				}

				if(!empty($macrosToUpdate)){
					$result = CUsermacro::massUpdate(array('hosts' => $hosts, 'macros' => $macrosToUpdate));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot update macro');
					}
				}

				if(!empty($macrosToAdd)){
					$result = self::massAdd(array('hosts' => $hosts, 'macros' => $macrosToAdd));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot add macro');
					}
				}
			}
// }}} UPDATE MACROS


// PROFILE {{{
			if(isset($data['profile']) && !is_null($data['profile'])){
				if(empty($data['profile'])){
					$sql = 'DELETE FROM hosts_profiles WHERE '.DBcondition('hostid', $hostids);
					if(!DBexecute($sql))
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot delete profile');
				}
				else{
					$existing_profiles = array();
					$existing_profiles_db = DBselect('SELECT hostid FROM hosts_profiles WHERE '.DBcondition('hostid', $hostids));
					while($existing_profile = DBfetch($existing_profiles_db)){
						$existing_profiles[] = $existing_profile['hostid'];
					}

					$hostids_without_profile = array_diff($hostids, $existing_profiles);

					$fields = array_keys($data['profile']);
					$fields = implode(', ', $fields);

					$values = array_map('zbx_dbstr', $data['profile']);
					$values = implode(', ', $values);

					foreach($hostids_without_profile as $hostid){
						$sql = 'INSERT INTO hosts_profiles (hostid, '.$fields.') VALUES ('.$hostid.', '.$values.')';
						if(!DBexecute($sql))
							self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot create profile');
					}

					if(!empty($existing_profiles)){
						$host_profile_fields = array('devicetype', 'name', 'os', 'serialno', 'tag','macaddress', 'hardware', 'software',
							'contact', 'location', 'notes');
						$sql_set = array();
						foreach($host_profile_fields as $field){
							if(isset($data['profile'][$field])) $sql_set[] = $field.'='.zbx_dbstr($data['profile'][$field]);
						}

						$sql = 'UPDATE hosts_profiles SET ' . implode(', ', $sql_set) . ' WHERE '.DBcondition('hostid', $existing_profiles);
						if(!DBexecute($sql))
							self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot update profile');
					}
				}
			}
// }}} PROFILE


// EXTENDED PROFILE {{{
			if(isset($data['extendedProfile']) && !is_null($data['extendedProfile'])){
				if(empty($data['extendedProfile'])){
					$sql = 'DELETE FROM hosts_profiles_ext WHERE '.DBcondition('hostid', $hostids);
					if(!DBexecute($sql))
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot delete extended profile');
				}
				else{
					$existing_profiles = array();
					$existing_profiles_db = DBselect('SELECT hostid FROM hosts_profiles_ext WHERE '.DBcondition('hostid', $hostids));
					while($existing_profile = DBfetch($existing_profiles_db)){
						$existing_profiles[] = $existing_profile['hostid'];
					}

					$hostids_without_profile = array_diff($hostids, $existing_profiles);

					$fields = array_keys($data['extendedProfile']);
					$fields = implode(', ', $fields);

					$values = array_map('zbx_dbstr', $data['extendedProfile']);
					$values = implode(', ', $values);

					foreach($hostids_without_profile as $hostid){
						$sql = 'INSERT INTO hosts_profiles_ext (hostid, '.$fields.') VALUES ('.$hostid.', '.$values.')';
						if(!DBexecute($sql))
							self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot create extended profile');
					}

					if(!empty($existing_profiles)){

						$host_profile_ext_fields = array('device_alias','device_type','device_chassis','device_os','device_os_short',
							'device_hw_arch','device_serial','device_model','device_tag','device_vendor','device_contract',
							'device_who','device_status','device_app_01','device_app_02','device_app_03','device_app_04',
							'device_app_05','device_url_1','device_url_2','device_url_3','device_networks','device_notes',
							'device_hardware','device_software','ip_subnet_mask','ip_router','ip_macaddress','oob_ip',
							'oob_subnet_mask','oob_router','date_hw_buy','date_hw_install','date_hw_expiry','date_hw_decomm','site_street_1',
							'site_street_2','site_street_3','site_city','site_state','site_country','site_zip','site_rack','site_notes',
							'poc_1_name','poc_1_email','poc_1_phone_1','poc_1_phone_2','poc_1_cell','poc_1_screen','poc_1_notes','poc_2_name',
							'poc_2_email','poc_2_phone_1','poc_2_phone_2','poc_2_cell','poc_2_screen','poc_2_notes');

						$sql_set = array();
						foreach($host_profile_ext_fields as $field){
							if(isset($data['extendedProfile'][$field])) $sql_set[] = $field.'='.zbx_dbstr($data['extendedProfile'][$field]);
						}

						$sql = 'UPDATE hosts_profiles_ext SET ' . implode(', ', $sql_set) . ' WHERE '.DBcondition('hostid', $existing_profiles);
						if(!DBexecute($sql))
							self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot update extended profile');
					}
				}
			}
// }}} EXTENDED PROFILE

			self::EndTransaction(true, __METHOD__);
			return array('hostids' => $hostids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * remove Hosts from HostGroups. All Hosts are removed from all HostGroups.
 *
 * @param array $data
 * @param array $data['hostids']
 * @param array $data['groupids']
 * @param array $data['templateids']
 * @param array $data['macroids']
 * @return array
 */
	public static function massRemove($data){
		$hostids = zbx_toArray($data['hostids']);

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'hostids' => $hostids,
				'editable' => 1,
				'preservekeys' => 1,
				'output' => API_OUTPUT_SHORTEN,
			);
			$upd_hosts = self::get($options);
			foreach($hostids as $hostid){
				if(!isset($upd_hosts[$hostid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			if(isset($data['groupids'])){
				$options = array(
					'hostids' => $hostids,
					'groupids' => zbx_toArray($data['groupids'])
				);
				$result = CHostGroup::massRemove($options);
				if(!$result) self::exception();
			}

			if(isset($data['templateids'])){
				$options = array(
					'hostids' => $hostids,
					'templateids' => zbx_toArray($data['templateids'])
				);
				$result = CTemplate::massRemove($options);
				if(!$result) self::exception();
			}

			if(isset($data['macros'])){
				$options = array(
					'hostids' => $hostids,
					'macros' => zbx_toArray($data['macros'])
				);
				$result = CUserMacro::massRemove($options);
				if(!$result) self::exception();
			}

			self::EndTransaction(true, __METHOD__);
			return array('hostids' => $hostids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Delete Host
 *
 * @param array $hosts
 * @param array $hosts[0, ...]['hostid'] Host ID to delete
 * @return array|boolean
 */
	public static function delete($hosts){
		$hosts = zbx_toArray($hosts);
		$hostids = zbx_objectValues($hosts, 'hostid');
		if(empty($hostids)) return true;

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'hostids' => $hostids,
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1
			);

			$del_hosts = self::get($options);
			foreach($hostids as $hnum => $hostid){
				if(!isset($del_hosts[$hostid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			$result = delete_host($hostids, false);
			if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot delete host');

			self::EndTransaction(true, __METHOD__);
			return array('hostids' => $hostids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}


	/**
	 * Validates a host expression.
	 *
	 * if the $fields parameters is passed, it will only validate the given fields.
	 *
	 * {@source}
	 * @access private
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @throws APIException if validation fails
	 *
	 * @param array $host The host expression to validate
	 * @param array $fields An array of field names to be validated
	 *
	 * @return boolean
	 */
	private static function validate(array &$host, array $fields = array()) {
		// don't perform field checks if we're udpating only selected fields
		if (!$fields) {
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
				'ipmi_password' => ''
			);

			if (!check_db_fields($host_db_fields, $host)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for host [ '.$host['host'].' ]');
			}

			// validate all fields
			$fields = array_keys($host_db_fields);
			$fields[] = 'groups';
		}

		// dns
		if (in_array('dns', $fields)) {
			if (!empty($host['dns']) && !preg_match('/^'.ZBX_PREG_DNS_FORMAT.'$/i', $host['dns'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect characters used for DNS [ '.$host['dns'].' ]');
			}
			// check if the DNS name is set
			if (empty($host['dns']) && !$host['useip']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, S_HOST_NO_DNS);
			}
		}
		// CHECK IF HOSTS HAVE AT LEAST 1 GROUP
		if (in_array('groups', $fields) && empty($host['groups'])) {
			$error = (isset($host['host'])) ? sprintf(S_NO_GROUPS_FOR_HOST, $host['host']) : S_NO_GROUPS_FOR_HOSTS;
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// host
		if (in_array('host', $fields)) {
			if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $host['host'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect characters used for Hostname [ '.$host['host'].' ]');
			}
			// Check if host name isn't longer then 64 chars
			if (zbx_strlen($host['host']) > 64) {
				self::exception(ZBX_API_ERROR_PARAMETERS, sprintf(S_HOST_NAME_MUST_BE_LONGER, 64, $host['host'], zbx_strlen($host['host'])));
			}
			if (CTemplate::exists(array('host' => $host['host']))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, S_TEMPLATE.' [ '.$host['host'].' ] '.S_ALREADY_EXISTS_SMALL);
			}
			// check if a different host with the same name exists
			$options = array(
				'filter' => array(
					'host' => $host['host']),
				'output' => API_OUTPUT_SHORTEN,
				'nopermissions' => true
			);
			$host_exists = self::get($options);
			$host_exist = reset($host_exists);
			if ($host_exist && (!isset($host['hostid']) || $host_exist['hostid'] != $host['hostid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, S_HOST.' [ '.$host['host'].' ] '.S_ALREADY_EXISTS_SMALL);
			}
		}

		if(isset($host['profile']) && !empty($host['profile'])){
			$profile_fields = array('devicetype', 'name', 'os', 'serialno', 'tag', 'macaddress', 'hardware',
				'software', 'contact', 'location', 'notes'
			);

			$fields = array_keys($host['profile']);
			foreach ($fields as $field) {
				if (!in_array($field, $profile_fields)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_PROFILE_FIELD.' "'.$field.'"');
				}
			}
		}

		if(isset($host['extendedProfile']) && !empty($host['extendedProfile'])){
			$ext_profiles_fields = array('device_alias', 'device_type', 'device_chassis', 'device_os', 'device_os_short',
				'device_hw_arch', 'device_serial', 'device_model', 'device_tag', 'device_vendor', 'device_contract',
				'device_who', 'device_status', 'device_app_01', 'device_app_02', 'device_app_03', 'device_app_04',
				'device_app_05', 'device_url_1', 'device_url_2', 'device_url_3', 'device_networks', 'device_notes',
				'device_hardware', 'device_software', 'ip_subnet_mask', 'ip_router', 'ip_macaddress', 'oob_ip',
				'oob_subnet_mask', 'oob_router', 'date_hw_buy', 'date_hw_install', 'date_hw_expiry', 'date_hw_decomm',
				'site_street_1', 'site_street_2', 'site_street_3', 'site_city', 'site_state', 'site_country',
				'site_zip', 'site_rack', 'site_notes', 'poc_1_name', 'poc_1_email', 'poc_1_phone_1',
				'poc_1_phone_2', 'poc_1_cell', 'poc_1_screen', 'poc_1_notes', 'poc_2_name',
				'poc_2_email', 'poc_2_phone_1', 'poc_2_phone_2', 'poc_2_cell', 'poc_2_screen', 'poc_2_notes'
			);
			$fields = array_keys($host['extendedProfile']);
			foreach ($fields as $field) {
				if (!in_array($field, $ext_profiles_fields)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, S_INCORRECT_EXTENDED_PROFILE_FIELD.' "'.$field.'"');
				}
			}
		}

		return true;
	}

}
?>
