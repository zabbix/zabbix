<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
 * @param string $options['extend_pattern'] search hosts by pattern in Host name, ip and DNS
 * @param int $options['limit'] limit selection
 * @param string $options['sortfield'] field to sort by
 * @param string $options['sortorder'] sort order
 * @return array|boolean Host data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('hostid', 'host', 'status', 'dns', 'ip'); // allowed columns for sorting


		$sql_parts = array(
			'select' => array('hosts' => 'h.hostid'),
			'from' => array('hosts h'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'templateids'				=> null,
			'itemids'					=> null,
			'triggerids'				=> null,
			'graphids'					=> null,
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
			'pattern'					=> '',
			'extend_pattern'			=> null,

// OutPut
			'extendoutput'				=> null,
			'select_groups'				=> null,
			'select_templates'			=> null,
			'select_items'				=> null,
			'select_triggers'			=> null,
			'select_graphs'				=> null,
			'select_applications'		=> null,
			'select_macros'				=> null,
			'select_profile'			=> null,
			'count'						=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);

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
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);
			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where']['hgh'] = 'hg.hostid=h.hostid';
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);
			$sql_parts['where'][] = DBcondition('h.hostid', $options['hostids']);
		}

// templateids
		if(!is_null($options['templateids'])){
			zbx_value2array($options['templateids']);
			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['templateid'] = 'ht.templateid';
			}

			$sql_parts['from']['ht'] = 'hosts_templates ht';
			$sql_parts['where'][] = DBcondition('ht.templateid', $options['templateids']);
			$sql_parts['where']['hht'] = 'h.hostid=ht.hostid';
		}

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);
			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['itemid'] = 'i.itemid';
			}

			$sql_parts['from']['i'] = 'items i';
			$sql_parts['where'][] = DBcondition('i.itemid', $options['itemids']);
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
		}

// triggerids
		if(!is_null($options['triggerids'])){
			zbx_value2array($options['triggerids']);
			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['triggerid'] = 'f.triggerid';
			}

			$sql_parts['from']['f'] = 'functions f';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['where'][] = DBcondition('f.triggerid', $options['triggerids']);
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
		}

// graphids
		if(!is_null($options['graphids'])){
			zbx_value2array($options['graphids']);
			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['graphid'] = 'gi.graphid';
			}

			$sql_parts['from']['gi'] = 'graphs_items gi';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['where'][] = DBcondition('gi.graphid', $options['graphids']);
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';

		}

// monitored_hosts, templated_hosts
		if(!is_null($options['monitored_hosts'])){
			$sql_parts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
		}
		else if(!is_null($options['templated_hosts'])){
			$sql_parts['where'][] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')';
		}
		else if(!is_null($options['proxy_hosts'])){
			$sql_parts['where'][] = 'h.status IN ('.HOST_STATUS_PROXY.')';
		}
		else{
			$sql_parts['where'][] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
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
			$sql_parts['where'][] = 'EXISTS( '.
					' SELECT DISTINCT i.itemid '.
					' FROM items i, graphs_items gi '.
					' WHERE i.hostid=h.hostid '.
						' AND i.itemid=gi.itemid)';
		}

// extendoutput
		if(!is_null($options['extendoutput'])){
			$sql_parts['select']['hosts'] = 'h.*';
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT h.hostid) as rowscount');
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
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'h.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('h.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('h.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'h.'.$options['sortfield'];
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
				$hostids[$host['hostid']] = $host['hostid'];

				if(is_null($options['extendoutput'])){
					$result[$host['hostid']] = $host['hostid'];
				}
				else{
					if(!isset($result[$host['hostid']])) $result[$host['hostid']]= array();

					if($options['select_groups'] && !isset($result[$host['hostid']]['groupids'])){
						$result[$host['hostid']]['groupids'] = array();
						$result[$host['hostid']]['groups'] = array();
					}

					if($options['select_templates'] && !isset($result[$host['hostid']]['templateids'])){
						$result[$host['hostid']]['templateids'] = array();
						$result[$host['hostid']]['templates'] = array();
					}

					if($options['select_items'] && !isset($result[$host['hostid']]['itemids'])){
						$result[$host['hostid']]['itemids'] = array();
						$result[$host['hostid']]['items'] = array();
					}
					if($options['select_profile'] && !isset($result[$host['hostid']]['profile'])){
						$result[$host['hostid']]['profile'] = array();
						$result[$host['hostid']]['profile_ext'] = array();
					}

					if($options['select_triggers'] && !isset($result[$host['hostid']]['triggerids'])){
						$result[$host['hostid']]['triggerids'] = array();
						$result[$host['hostid']]['triggers'] = array();
					}

					if($options['select_graphs'] && !isset($result[$host['hostid']]['graphids'])){
						$result[$host['hostid']]['graphids'] = array();
						$result[$host['hostid']]['graphs'] = array();
					}
					if($options['select_applications'] && !isset($result[$host['hostid']]['applications'])){
						$result[$host['hostid']]['applications'] = array();
						$result[$host['hostid']]['applicationids'] = array();
					}
					if($options['select_macros'] && !isset($result[$host['hostid']]['macroids'])){
						$result[$host['hostid']]['macros'] = array();
						$result[$host['hostid']]['macroids'] = array();
					}

// groupids
					if(isset($host['groupid'])){
						if(!isset($result[$host['hostid']]['groupids']))
							$result[$host['hostid']]['groupids'] = array();

						$result[$host['hostid']]['groupids'][$host['groupid']] = $host['groupid'];
						unset($host['groupid']);
					}

// templateids
					if(isset($host['templateid'])){
						if(!isset($result[$host['hostid']]['templateids']))
							$result[$host['hostid']]['templateids'] = array();

						$result[$host['hostid']]['templateids'][$host['templateid']] = $host['templateid'];
						unset($host['templateid']);
					}

// triggerids
					if(isset($host['triggerid'])){
						if(!isset($result[$host['hostid']]['triggerids']))
							$result[$host['hostid']]['triggerids'] = array();

						$result[$host['hostid']]['triggerids'][$host['triggerid']] = $host['triggerid'];
						unset($host['triggerid']);
					}

// itemids
					if(isset($host['itemid'])){
						if(!isset($result[$host['hostid']]['itemids']))
							$result[$host['hostid']]['itemids'] = array();

						$result[$host['hostid']]['itemids'][$host['itemid']] = $host['itemid'];
						unset($host['itemid']);
					}

// graphids
					if(isset($host['graphid'])){
						if(!isset($result[$host['hostid']]['graphids']))
							$result[$host['hostid']]['graphids'] = array();

						$result[$host['hostid']]['graphids'][$host['graphid']] = $host['graphid'];
						unset($host['graphid']);
					}

					$result[$host['hostid']] += $host;
				}
			}
		}

		if(is_null($options['extendoutput']) || !is_null($options['count'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
// Adding Groups
		if($options['select_groups']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'preservekeys' => 1);
			$groups = CHostgroup::get($obj_params);
			foreach($groups as $groupid => $group){
				foreach($group['hostids'] as $num => $hostid){
					$result[$hostid]['groupids'][$groupid] = $groupid;
					$result[$hostid]['groups'][$groupid] = $group;
				}
			}
		}

// Adding Profiles
		if($options['select_profile']){
			$sql = 'SELECT hp.*
				FROM hosts_profiles hp
				WHERE '.DBcondition('hp.hostid', $hostids);
			$db_profile = DBselect($sql);
			while($profile = DBfetch($db_profile))
				$result[$profile['hostid']]['profile'] = $profile;


			$sql = 'SELECT hpe.*
				FROM hosts_profiles_ext hpe
				WHERE '.DBcondition('hpe.hostid', $hostids);
			$db_profile_ext = DBselect($sql);
			while($profile_ext = DBfetch($db_profile_ext))
				$result[$profile_ext['hostid']]['profile_ext'] = $profile_ext;
		}

// Adding Templates
		if($options['select_templates']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'preservekeys' => 1);
			$templates = CTemplate::get($obj_params);
			foreach($templates as $templateid => $template){
				foreach($template['hostids'] as $num => $hostid){
					$result[$hostid]['templateids'][$templateid] = $templateid;
					$result[$hostid]['templates'][$templateid] = $template;
				}
			}
		}

// Adding Items
		if($options['select_items']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'nopermissions' => 1, 'preservekeys' => 1);
			$items = CItem::get($obj_params);
			foreach($items as $itemid => $item){
				foreach($item['hostids'] as $num => $hostid){
					$result[$hostid]['itemids'][$itemid] = $itemid;
					$result[$hostid]['items'][$itemid] = $item;
				}
			}
		}

// Adding triggers
		if($options['select_triggers']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'preservekeys' => 1);
			$triggers = CTrigger::get($obj_params);
			foreach($triggers as $triggerid => $trigger){
				foreach($trigger['hostids'] as $num => $hostid){
					$result[$hostid]['triggerids'][$triggerid] = $triggerid;
					$result[$hostid]['triggers'][$triggerid] = $trigger;
				}
			}
		}

// Adding graphs
		if($options['select_graphs']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'preservekeys' => 1);
			$graphs = CGraph::get($obj_params);
			foreach($graphs as $graphid => $graph){
				foreach($graph['hostids'] as $num => $hostid){
					$result[$hostid]['graphids'][$graphid] = $graphid;
					$result[$hostid]['graphs'][$graphid] = $graph;
				}
			}
		}

// Adding applications
		if($options['select_applications']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'preservekeys' => 1);
			$applications = CApplication::get($obj_params);
			foreach($applications as $applicationid => $application){
				foreach($application['hostids'] as $num => $hostid){
					$result[$hostid]['applicationids'][$applicationid] = $applicationid;
					$result[$hostid]['applications'][$applicationid] = $application;
				}
			}
		}

// Adding macros
		if($options['select_macros']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'preservekeys' => 1);
			$macros = CUserMacro::get($obj_params);
			foreach($macros as $macroid => $macro){
				foreach($macro['hostids'] as $num => $hostid){
					$result[$hostid]['macroids'][$macroid] = $macroid;
					$result[$hostid]['macros'][$macroid] = $macro;
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
	public static function getObjects($host_data){
		$result = array();
		$hostids = array();
		
		$sql = 'SELECT hostid '.
				' FROM hosts '.
				' WHERE host='.zbx_dbstr($host_data['host']).
					' AND '.DBin_node('hostid', false);

		$res = DBselect($sql);
		while($host = DBfetch($res)){
			$hostids[$host['hostid']] = $host['hostid'];
		}

		if(!empty($hostids))
			$result = self::get(array('hostids'=>$hostids, 'extendoutput'=>1));
		
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
	public static function add($hosts){
		$hosts = zbx_toArray($hosts);
		$hostids = array();

		$groupids = array();
		
		$templates = null;
		$newgroup = '';

		$result = false;

		foreach($hosts as $hnum => $host){
			if(empty($host['groups'])){
				self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'No groups for host [ '.$host['host'].' ]');
				return false;
			}
						
			$hosts[$hnum]['groups'] = zbx_toArray($hosts[$hnum]['groups']);
			
			foreach($hosts[$hnum]['groups'] as $gnum => $group){
				$groupids[$group['groupid']] = $group['groupid'];
			}
		}

		$upd_groups = CHostGroup::get(array('groupids'=>$groupids,
										'editable'=>1, 
										'preservekeys'=>1));
		foreach($groupids as $gnum => $groupid){
			if(!isset($upd_groups[$groupid])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'You do not have enough rights for operation');
				return false;
			}
		}

		self::BeginTransaction(__METHOD__);
		foreach($hosts as $num => $host){
			$host['groupids'] = zbx_objectValues($host['groups'], 'groupid');

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
				'groupids' => array()
			);

			if(!check_db_fields($host_db_fields, $host)){
				$result = false;
				self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Wrong fields for host [ '.$host['host'].' ]');
				break;
			}

			$result = add_host($host['host'], $host['port'], $host['status'], $host['useip'], $host['dns'], $host['ip'],
				$host['proxy_hostid'], $templates, $host['useipmi'], $host['ipmi_ip'], $host['ipmi_port'], $host['ipmi_authtype'],
				$host['ipmi_privilege'], $host['ipmi_username'], $host['ipmi_password'], $newgroup, $host['groupids']);

			if(!$result) break;
			$hostids[] = $result;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$new_hosts = self::get(array('hostids'=>$hostids, 'editable'=>1, 'extendoutput'=>1, 'nopermissions'=>1));
			return $new_hosts;
		}
		else{
			self::setError(__METHOD__);
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
		$hosts = zbx_toArray($hosts);
		$hostids = array();
		
		$upd_hosts = self::get(array('hostids'=> zbx_objectValues($hosts, 'hostid'), 'editable'=>1, 'extendoutput'=>1, 'preservekeys'=>1));
		foreach($hosts as $gnum => $host){
			if(!isset($upd_hosts[$host['hostid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'You do not have enough rights for operation');
				return false;
			}
			$hostids[] = $host['hostid'];
		}
		
		$templates = null;
		$newgroup = '';

		$result = false;
		self::BeginTransaction(__METHOD__);

		foreach($hosts as $num => $host){
			$host_db_fields = $upd_hosts[$host['hostid']];

			if(!check_db_fields($host_db_fields, $host)){
				$result = false;
				break;
			}

			$groups = get_groupids_by_host($host['hostid']);

			$result = update_host($host['hostid'], $host['host'], $host['port'], $host['status'], $host['useip'], $host['dns'], $host['ip'],
				$host['proxy_hostid'], $templates, $host['useipmi'], $host['ipmi_ip'], $host['ipmi_port'], $host['ipmi_authtype'],
				$host['ipmi_privilege'], $host['ipmi_username'], $host['ipmi_password'], $newgroup, $groups);

			if(!$result) break;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$upd_hosts = self::get(array('hostids'=>$hostids, 'extendoutput'=>1, 'nopermissions'=>1));			
			return $upd_hosts;
		}
		else{
			self::setError(__METHOD__);
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
 * @param array $hosts['hosts'] Array of Host objects to update
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
	public static function massUpdate($hosts){
		$params = $hosts;
		$hosts = $hosts['hosts'];
		
		$hosts = zbx_toArray($hosts);
		$hostids = array();
		
		$upd_hosts = self::get(array('hostids'=> zbx_objectValues($hosts, 'hostid'), 'editable'=>1, 'extendoutput'=>1, 'preservekeys'=>1));
		foreach($hosts as $gnum => $host){
			if(!isset($upd_hosts[$host['hostid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'You have not enough rights for operation');
				return false;
			}
			$hostids[] = $host['hostid'];
		}

		self::BeginTransaction(__METHOD__);
		if(empty($hostids)){
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ hostids ]');
			return false;
		}
		else{
			$sql = 'UPDATE hosts SET '.
				(isset($params['proxy_hostid']) ? ',proxy_hostid='.$params['proxy_hostid'] : '').
				(isset($params['host']) ? ',host='.zbx_dbstr($params['host']) : '').
				(isset($params['port']) ? ',port='.$params['port'] : '').
				(isset($params['status']) ? ',status='.$params['status'] : '').
				(isset($params['useip']) ? ',useip='.$params['useip'] : '').
				(isset($params['dns']) ? ',dns='.zbx_dbstr($params['dns']) : '').
				(isset($params['ip']) ? ',ip='.zbx_dbstr($params['ip']) : '').
				(isset($params['useipmi']) ? ',useipmi='.$params['useipmi'] : '').
				(isset($params['ipmi_port']) ? ',ipmi_port='.$params['ipmi_port'] : '').
				(isset($params['ipmi_authtype']) ? ',ipmi_authtype='.$params['ipmi_authtype'] : '').
				(isset($params['ipmi_privilege']) ? ',ipmi_privilege='.$params['ipmi_privilege'] : '').
				(isset($params['ipmi_username']) ? ',ipmi_username='.zbx_dbstr($params['ipmi_username']) : '').
				(isset($params['ipmi_password']) ? ',ipmi_password='.zbx_dbstr($params['ipmi_password']) : '').
				(isset($params['ipmi_ip']) ? ',ipmi_ip='.zbx_dbstr($params['ipmi_ip']) : '').
				' WHERE '.DBcondition('hostid', $hostids);

			$sql = substr_replace($sql, '', strpos($sql, ','), 1);

			$result = DBexecute($sql);
		}
		
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$upd_hosts = self::get(array('hostids'=>$hostids, 'extendoutput'=>1, 'nopermissions'=>1));			
			return $upd_hosts;
		}
		else{
			self::setError(__METHOD__);
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
 * @param array $hosts
 * @param array $hosts[0, ...]['hostid'] Host ID to delete
 * @return array|boolean
 */
	public static function delete($hosts){
		$hosts = zbx_toArray($hosts);
		$hostids = array();
		
		$del_hosts = self::get(array('hostids'=> zbx_objectValues($hosts, 'hostid'), 
									'editable'=>1, 
									'extendoutput'=>1, 
									'preservekeys'=>1));
		if(empty($del_hosts)){
			self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'Host does not exist');
			return false;
		}

		foreach($hosts as $num => $host){
			if(!isset($del_hosts[$host['hostid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'You have not enough rights for operation');
				return false;
			}
			
			$hostids[] = $host['hostid'];
			//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_HOST, 'Host ['.$host['host'].']');
		}
		
		self::BeginTransaction(__METHOD__);
		if(!empty($hostids)){
			$result = delete_host($hostids, false);
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter');
			$result = false;
		}
		
		$result = self::EndTransaction($result, __METHOD__);
		
		if($result){
			return zbx_cleanHashes($del_hosts);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}

}
?>