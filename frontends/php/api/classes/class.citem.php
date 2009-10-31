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
 * File containing CItem class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Items
 *
 */
class CItem extends CZBXAPI{
/**
 * Get items data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
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
			'nodeids'				=> null,
			'groupids'				=> null,
			'hostids'				=> null,
			'itemids'				=> null,
			'graphids'				=> null,
			'triggerids'			=> null,
			'applicationids'		=> null,
			'templated_items'		=> null,
			'editable'				=> null,
			'nopermissions'			=> null,
// OutPut
			'extendoutput'			=> null,
			'select_hosts'			=> null,
			'select_triggers'		=> null,
			'select_graphs'			=> null,
			'select_applications'	=> null,
			'count'					=> null,
// filter
			'filter'				=> null,

			'group'					=> null,
			'host'					=> null,
			'application'			=> null,
			'key'					=> null,
			'type'					=> null,
			'snmp_community'		=> null,
			'snmp_oid'				=> null,
			'snmp_port'				=> null,
			'valuetype'				=> null,
			'data_type'				=> null,
			'delay'					=> null,
			'history'				=> null,
			'trends'				=> null,
			'status'				=> null,
			'belongs'				=> null,
			'with_triggers'			=> null,
//
			'pattern'				=> null,
			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = zbx_array_merge($def_options, $options);

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
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}

			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where'][] = 'hg.hostid=i.hostid';
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			$sql_parts['where'][] = DBcondition('i.hostid', $options['hostids']);
		}

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);

			$sql_parts['where'][] = DBcondition('i.itemid', $options['itemids']);
		}

// triggerids
		if(!is_null($options['triggerids'])){
			zbx_value2array($options['triggerids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['triggerid'] = 'f.triggerid';
			}

			$sql_parts['from'][] = 'functions f';
			$sql_parts['where'][] = DBcondition('f.triggerid', $options['triggerids']);
			$sql_parts['where']['if'] = 'i.itemid=f.itemid';
		}

// applicationids
		if(!is_null($options['applicationids'])){
			zbx_value2array($options['applicationids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['applicationid'] = 'ia.applicationid';
			}

			$sql_parts['from']['ia'] = 'items_applications ia';
			$sql_parts['where'][] = DBcondition('ia.applicationid', $options['applicationids']);
			$sql_parts['where']['ia'] = 'ia.itemid=i.itemid';
		}

// graphids
		if(!is_null($options['graphids'])){
			zbx_value2array($options['graphids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['graphid'] = 'gi.graphid';
			}

			$sql_parts['from']['gi'] = 'graphs_items gi';
			$sql_parts['where'][] = DBcondition('gi.graphid', $options['graphids']);
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
		}

// templated_items
		if(!is_null($options['templated_items'])){
			if($options['templated_items'] == 1)
				$sql_parts['where'][] = 'i.templateid>0';
			else
				$sql_parts['where'][] = 'i.templateid=0';
		}

// extendoutput
		if(!is_null($options['extendoutput'])){
			$sql_parts['select']['items'] = 'i.*';
		}

// pattern
		if(!is_null($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(i.description) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
		}

// --- FILTER ---
		if(!is_null($options['filter'])){
// group
			if(!is_null($options['group'])){
				if(!is_null($options['extendoutput'])){
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
				if(!is_null($options['extendoutput'])){
					$sql_parts['select']['host'] = 'h.host';
				}

				$sql_parts['from']['h'] = 'hosts h';
				$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
				$sql_parts['where'][] = ' UPPER(h.host)='.zbx_dbstr(strtoupper($options['host']));
			}

// application
			if(!is_null($options['application'])){
				if(!is_null($options['extendoutput'])){
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
			if(!is_null($options['snmp_oid'])){
				$sql_parts['where'][] = 'i.snmp_oid='.zbx_dbstr($options['snmp_oid']);
			}

// snmp port
			if(!is_null($options['snmp_port'])){
				$sql_parts['where'][] = 'i.snmp_port='.$options['snmp_port'];
			}

// valuetype
			if(!is_null($options['valuetype'])){
				$sql_parts['where'][] = 'i.value_type='.$options['valuetype'];
			}

// datatype
			if(!is_null($options['data_type'])){
				$sql_parts['where'][] = 'i.data_type='.$options['data_type'];
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

// status
			if(!is_null($options['status'])){
				$sql_parts['where'][] = 'i.status='.$options['status'];
			}

// with_triggers
			if(!is_null($options['with_triggers'])){
				if($options['with_triggers'] == 1)
					$sql_parts['where'][] = ' EXISTS ( SELECT functionid FROM functions ff WHERE ff.itemid=i.itemid )';
				else
					$sql_parts['where'][] = 'NOT EXISTS ( SELECT functionid FROM functions ff WHERE ff.itemid=i.itemid )';
			}
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT i.itemid) as rowscount');
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

				if(is_null($options['extendoutput'])){
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
//						unset($item['hostid']);
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

		if(is_null($options['extendoutput']) || !is_null($options['count'])) return $result;

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
			$obj_params = array('extendoutput' => 1, 'itemids' => $itemids);
			$applications = CApplication::get($obj_params);
			foreach($applications as $applicationid => $application){
				foreach($application['itemids'] as $num => $itemid){
					$result[$itemid]['applicationids'][$applicationid] = $applicationid;
					$result[$itemid]['applications'][$applicationid] = $application;
				}
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
			self::$error[] = array('error' => ZBX_API_ERROR_NO_HOST, 'data' => 'Item with id: '.$itemid.' doesn\'t exists.');
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
	 * @param array $item_data
	 * @param array $item_data['key_']
	 * @param array $item_data['host'] ALTERNATIVE
	 * @param array $item_data['hostid'] ALTERNATIVE
	 * @return int|boolean
	 */
	public static function getId($item_data){
		if(isset($item_data['hostid'])){
			$hostid = $item_data['hostid'];
		}
		else{
			if(!isset($item_data['host'])){
				self::$error[] = array('error' => ZBX_API_ERROR_NO_HOST, 'data' => 'Item doesn\'t exists.');
				return false;
			}
			$hostid = CHost::getId(array('host' => $item_data['host']));
		}

		$sql = 'SELECT DISTINCT i.itemid'.
			' FROM items i'.
			' WHERE i.key_='.zbx_dbstr($item_data['key_']).
				' AND i.hostid='.$hostid;

		$itemid = DBfetch(DBselect($sql));

		$result = $itemid ? $itemid['itemid'] : false;
		if($result)
			return $result;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_NO_HOST, 'data' => 'Item doesn\'t exists.');
			return false;
		}
	}

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
	 * @param array $items multidimensional array with items data
	 * @return array|boolean
	 */
	public static function add($items){
		$itemids = array();
		self::BeginTransaction(__METHOD__);

		$result = true;
		foreach($items as $item){
			$result = add_item($item);
			if(!$result) break;
			$itemids['result'] = $result;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result)
			return $itemids;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
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
	 * @param array $items multidimensional array with items data
	 * @return boolean
	 */
	public static function update($items){

		$result = true;
		$itemids = array();
		self::BeginTransaction(__METHOD__);
		foreach($items as $item){
			$result = update_item($item['itemid'], $item);
			if(!$result) break;
			$itemids[$result] = $result;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result)
			return $itemids;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
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
	 * @param array $itemids
	 * @return array|boolean
	 */
	public static function delete($itemids){
		$itemids = isset($itemids['itemids']) ? $itemids['itemids'] : array();
		zbx_value2array($itemids);

		if(!empty($itemids)){
			$result = delete_item($itemids);
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ itemids ]');
			$result = false;
		}
		if($result)
			return $itemids;
		else{
			self::setError(__METHOD__);
			return false;
		}
	}

}
?>
