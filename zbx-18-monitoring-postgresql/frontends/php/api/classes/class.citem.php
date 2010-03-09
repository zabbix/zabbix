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
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('items' => 'i.itemid'),
			'from' => array('items i'),
			'where' => array('webtype' => 'i.type<>9'),
			'group' => array(),
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
			'webitems'				=> null,
			'inherited'				=> null,
			'templated'				=> null,
			'editable'				=> null,
			'nopermissions'			=> null,
// filter
			'filter'				=> null,

			'group'					=> null,
			'host'					=> null,
			'application'			=> null,

			'belongs'				=> null,
			'with_triggers'			=> null,
//
			'pattern'				=> null,

// OutPut
			'output'				=> API_OUTPUT_REFER,
			'extendoutput'			=> null,
			'select_hosts'			=> null,
			'select_triggers'		=> null,
			'select_graphs'			=> null,
			'select_applications'	=> null,
			'countOutput'			=> null,
			'groupCount'			=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);


		$options = zbx_array_merge($def_options, $options);


		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_hosts'])){
				$options['select_hosts'] = API_OUTPUT_EXTEND;
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
		}


// editable + PERMISSION CHECK

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
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid(false);

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}

			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where'][] = 'hg.hostid=i.hostid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['hg'] = 'hg.groupid';
			}
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if($options['output'] != API_OUTPUT_EXTEND){
				$sql_parts['select']['hostid'] = 'i.hostid';
			}

			$sql_parts['where'][] = DBcondition('i.hostid', $options['hostids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['i'] = 'i.hostid';
			}
		}

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);

			$sql_parts['where']['itemid'] = DBcondition('i.itemid', $options['itemids']);
		}

// triggerids
		if(!is_null($options['triggerids'])){
			zbx_value2array($options['triggerids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['triggerid'] = 'f.triggerid';
			}

			$sql_parts['from'][] = 'functions f';
			$sql_parts['where'][] = DBcondition('f.triggerid', $options['triggerids']);
			$sql_parts['where']['if'] = 'i.itemid=f.itemid';
		}

// applicationids
		if(!is_null($options['applicationids'])){
			zbx_value2array($options['applicationids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['applicationid'] = 'ia.applicationid';
			}

			$sql_parts['from']['ia'] = 'items_applications ia';
			$sql_parts['where'][] = DBcondition('ia.applicationid', $options['applicationids']);
			$sql_parts['where']['ia'] = 'ia.itemid=i.itemid';
		}

// graphids
		if(!is_null($options['graphids'])){
			zbx_value2array($options['graphids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['graphid'] = 'gi.graphid';
			}

			$sql_parts['from']['gi'] = 'graphs_items gi';
			$sql_parts['where'][] = DBcondition('gi.graphid', $options['graphids']);
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
		}

// webitems
		if(!is_null($options['webitems'])){
			unset($sql_parts['where']['webtype']);
		}

// inherited
		if(!is_null($options['inherited'])){
			if($options['inherited'])
				$sql_parts['where'][] = 'i.templateid>0';
			else
				$sql_parts['where'][] = 'i.templateid=0';
		}

// templated
		if(!is_null($options['templated'])){
			$sql_parts['from'][] = 'hosts h';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';

			if($options['templated'])
				$sql_parts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			else
				$sql_parts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
		}

// API_OUTPUT_EXTEND
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['items'] = 'i.*';
		}

// pattern
		if(!is_null($options['pattern'])){
			$sql_parts['where']['description'] = ' UPPER(i.description) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['pattern']).'%');
		}

		if(isset($options['patternKey']))
			$sql_parts['where']['key_'] = ' UPPER(i.key_) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['patternKey']).'%');


// --- FILTER ---
		if(!is_null($options['filter'])){
			zbx_value2array($options['filter']);

			if(isset($options['filter']['itemid']))
				$sql_parts['where']['itemid'] = 'i.itemid='.zbx_dbstr($options['filter']['itemid']);

			if(isset($options['filter']['description']))
				$sql_parts['where']['description'] = 'i.description='.zbx_dbstr($options['filter']['description']);

			if(isset($options['filter']['key_']))
				$sql_parts['where']['key_'] = 'i.key_='.zbx_dbstr($options['filter']['key_']);

			if(isset($options['filter']['type']))
				$sql_parts['where'][] = 'i.type='.$options['filter']['type'];

			if(isset($options['filter']['snmp_community']))
				$sql_parts['where'][] = 'i.snmp_community='.zbx_dbstr($options['filter']['snmp_community']);

			if(isset($options['filter']['snmp_oid']))
				$sql_parts['where'][] = 'i.snmp_oid='.zbx_dbstr($options['filter']['snmp_oid']);

			if(isset($options['filter']['snmp_port']))
				$sql_parts['where'][] = 'i.snmp_port='.$options['filter']['snmp_port'];

			if(isset($options['filter']['value_type'])){
				zbx_value2array($options['filter']['value_type']);

				$sql_parts['where'][] = DBCondition('i.value_type', $options['filter']['value_type']);
			}

			if(isset($options['filter']['data_type']))
				$sql_parts['where'][] = 'i.data_type='.$options['filter']['data_type'];

			if(isset($options['filter']['delay']))
				$sql_parts['where'][] = 'i.delay='.$options['filter']['delay'];

			if(isset($options['filter']['trends']))
				$sql_parts['where'][] = 'i.trends='.$options['filter']['trends'];

			if(isset($options['filter']['history']))
				$sql_parts['where'][] = 'i.history='.$options['filter']['history'];

			if(isset($options['filter']['status']))
				$sql_parts['where'][] = 'i.status='.$options['filter']['status'];
		}

// group
		if(!is_null($options['group'])){
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['name'] = 'g.name';
			}

			$sql_parts['from']['g'] = 'groups g';
			$sql_parts['from']['hg'] = 'hosts_groups hg';

			$sql_parts['where']['ghg'] = 'g.groupid = hg.groupid';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where'][] = ' UPPER(g.name)='.zbx_dbstr(zbx_strtoupper($options['group']));
		}

// host
		if(!is_null($options['host'])){
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['host'] = 'h.host';
			}

			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
			$sql_parts['where'][] = ' UPPER(h.host)='.zbx_dbstr(zbx_strtoupper($options['host']));
		}

// application
		if(!is_null($options['application'])){
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['application'] = 'a.name as application';
			}

			$sql_parts['from']['a'] = 'applications a';
			$sql_parts['from']['ia'] = 'items_applications ia';

			$sql_parts['where']['aia'] = 'a.applicationid = ia.applicationid';
			$sql_parts['where']['iai'] = 'ia.itemid=i.itemid';
			$sql_parts['where'][] = ' UPPER(a.name)='.zbx_dbstr(zbx_strtoupper($options['application']));
		}


// with_triggers
		if(!is_null($options['with_triggers'])){
			if($options['with_triggers'] == 1)
				$sql_parts['where'][] = ' EXISTS ( SELECT functionid FROM functions ff WHERE ff.itemid=i.itemid )';
			else
				$sql_parts['where'][] = 'NOT EXISTS ( SELECT functionid FROM functions ff WHERE ff.itemid=i.itemid )';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT i.itemid) as rowscount');

//groupCount
			if(!is_null($options['groupCount'])){
				foreach($sql_parts['group'] as $key => $fields){
					$sql_parts['select'][$key] = $fields;
				}
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
//----------

		$itemids = array();

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
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['group']))		$sql_where.= ' GROUP BY '.implode(',',$sql_parts['group']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT DISTINCT '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('i.itemid', $nodeids).
					$sql_where.
				$sql_group.
				$sql_order;
//sdi($sql);
		$res = DBselect($sql, $sql_limit);
		while($item = DBfetch($res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $item;
				else
					$result = $item['rowscount'];
			}
			else{
				$itemids[$item['itemid']] = $item['itemid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$item['itemid']] = array('itemid' => $item['itemid']);
				}
				else{
					if(!isset($result[$item['itemid']]))
						$result[$item['itemid']]= array();

					if(!is_null($options['select_hosts']) && !isset($result[$item['itemid']]['hosts'])){
						$result[$item['itemid']]['hosts'] = array();
					}
					if(!is_null($options['select_triggers']) && !isset($result[$item['itemid']]['triggers'])){
						$result[$item['itemid']]['triggers'] = array();
					}
					if(!is_null($options['select_graphs']) && !isset($result[$item['itemid']]['graphs'])){
						$result[$item['itemid']]['graphs'] = array();
					}
					if(!is_null($options['select_applications']) && !isset($result[$item['itemid']]['applications'])){
						$result[$item['itemid']]['applications'] = array();
					}

// hostids
					if(isset($item['hostid']) && is_null($options['select_hosts'])){
						if(!isset($result[$item['itemid']]['hosts'])) $result[$item['itemid']]['hosts'] = array();

						$result[$item['itemid']]['hosts'][] = array('hostid' => $item['hostid']);
//						unset($item['hostid']);
					}
// triggerids
					if(isset($item['triggerid']) && is_null($options['select_triggers'])){
						if(!isset($result[$item['itemid']]['triggers']))
							$result[$item['itemid']]['triggers'] = array();

						$result[$item['itemid']]['triggers'][] = array('triggerid' => $item['triggerid']);
						unset($item['triggerid']);
					}
// graphids
					if(isset($item['graphid']) && is_null($options['select_graphs'])){
						if(!isset($result[$item['itemid']]['graphs']))
							$result[$item['itemid']]['graphs'] = array();

						$result[$item['itemid']]['graphs'][] = array('graphid' => $item['graphid']);
						unset($item['graphid']);
					}
// applicationids
					if(isset($item['applicationid']) && is_null($options['select_applications'])){
						if(!isset($result[$item['itemid']]['applications']))
							$result[$item['itemid']]['applications'] = array();

						$result[$item['itemid']]['applications'][] = array('applicationid' => $item['applicationid']);
						unset($item['applicationid']);
					}

					$result[$item['itemid']] += $item;
				}
			}
		}

COpt::memoryPick();
		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
// Adding hosts
		if(!is_null($options['select_hosts']) && str_in_array($options['select_hosts'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'templated_hosts' => 1,
				'output' => $options['select_hosts'],
				'itemids' => $itemids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$hosts = CHost::get($obj_params);

			foreach($hosts as $hostid => $host){
				$hitems = $host['items'];
				unset($host['items']);
				foreach($hitems as $inum => $item){
					$result[$item['itemid']]['hosts'][] = $host;
				}
			}

			$templates = CTemplate::get($obj_params);
			foreach($templates as $templateid => $template){
				$titems = $template['items'];
				unset($template['items']);
				foreach($titems as $inum => $item){
					$result[$item['itemid']]['hosts'][] = $template;
				}
			}
		}

// Adding triggers
		if(!is_null($options['select_triggers']) && str_in_array($options['select_triggers'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_triggers'],
				'itemids' => $itemids,
				'preservekeys' => 1
			);
			$triggers = CTrigger::get($obj_params);
			foreach($triggers as $triggerid => $trigger){
				$titems = $trigger['items'];
				unset($trigger['items']);
				foreach($titems as $inum => $item){
					$result[$item['itemid']]['triggers'][] = $trigger;
				}
			}
		}

// Adding graphs
		if(!is_null($options['select_graphs']) && str_in_array($options['select_graphs'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_graphs'],
				'itemids' => $itemids,
				'preservekeys' => 1
			);
			$graphs = CGraph::get($obj_params);
			foreach($graphs as $graphid => $graph){
				$gitems = $graph['items'];
				unset($graph['items']);
				foreach($gitems as $inum => $item){
					$result[$item['itemid']]['graphs'][] = $graph;
				}
			}
		}

// Adding applications
		if(!is_null($options['select_applications']) && str_in_array($options['select_applications'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_applications'],
				'itemids' => $itemids,
				'preservekeys' => 1
			);
			$applications = CApplication::get($obj_params);
			foreach($applications as $applicationid => $application){
				$aitems = $application['items'];
				unset($application['items']);
				foreach($aitems as $inum => $item){
					$result[$item['itemid']]['applications'][] = $application;
				}
			}
		}

COpt::memoryPick();
// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
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
 * @param array $item_data['hostid']
 * @return int|boolean
 */

	public static function getObjects($itemData){
		$options = array(
			'filter' => $itemData,
			'output'=>API_OUTPUT_EXTEND
		);

		if(isset($itemData['node']))
			$options['nodeids'] = getNodeIdByNodeName($itemData['node']);
		else if(isset($itemData['nodeids']))
			$options['nodeids'] = $itemData['nodeids'];

		$result = self::get($options);

	return $result;
	}

	public static function checkObjects($itemsData){

		$itemsData = zbx_toArray($itemsData);
		$result = array();
		foreach($itemsData as $inum => $itemData){
			$options = array(
				'filter' => $itemData,
				'output' => API_OUTPUT_SHORTEN,
				'nopermissions' => 1
			);

			if(isset($itemData['node']))
				$options['nodeids'] = getNodeIdByNodeName($itemData['node']);
			else if(isset($itemData['nodeids']))
				$options['nodeids'] = $itemData['nodeids'];

			$items = self::get($options);

			$result+= $items;
		}

	return $result;
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
	public static function create($items){
		$items = zbx_toArray($items);
		$itemids = array();

		self::BeginTransaction(__METHOD__);

		$result = true;
		foreach($items as $inum => $item){

/*//////////////
			$item_db_fields = array(
				'description'		=> null,
				'key_'			=> null,
				'hostid'		=> null,
				'delay'			=> 60,
				'history'		=> 7,
				'status'		=> ITEM_STATUS_ACTIVE,
				'type'			=> ITEM_TYPE_ZABBIX,
				'snmp_community'	=> '',
				'snmp_oid'		=> '',
				'value_type'		=> ITEM_VALUE_TYPE_STR,
				'data_type'		=> ITEM_DATA_TYPE_DECIMAL,
				'trapper_hosts'		=> 'localhost',
				'snmp_port'		=> 161,
				'units'			=> '',
				'multiplier'		=> 0,
				'delta'			=> 0,
				'snmpv3_securityname'	=> '',
				'snmpv3_securitylevel'	=> 0,
				'snmpv3_authpassphrase'	=> '',
				'snmpv3_privpassphrase'	=> '',
				'formula'		=> 0,
				'trends'		=> 365,
				'logtimefmt'		=> '',
				'valuemapid'		=> 0,
				'delay_flex'		=> '',
				'authtype'		=> 0,
				'username'		=> '',
				'password'		=> '',
				'publickey'		=> '',
				'privatekey'		=> '',
				'params'		=> '',
				'ipmi_sensor'		=> '',
				'applications'		=> array(),
				'templateid'		=> 0
			);

			if(!check_db_fields($item_db_fields, $item)){
				self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Wrong field for item');
				$result = false;
				break;
			}

			$host = CHost::get(array('hostids' => $item['hostid'], 'noprermissions' => 1, 'templated_hosts' => 1));
			if(empty($host)){
				self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Host with HostID ['.$item['hostid'].'] does not exist');
				$result = false;
				break;
			}

			// if(($i = array_search(0, $item['applications'])) !== FALSE)
				// unset($item['applications'][$i]);

			if(!preg_match('/^'.ZBX_PREG_ITEM_KEY_FORMAT.'$/u', $item['key_']) ){
				self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Incorrect key format [ example: "key_name[param1,param2,...]" ]');
				$result = false;
				break;
			}

			if($item['delay'] < 1){
				self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Delay cannot be less than 1 second');
				$result = false;
				break;
			}

			if($item['delay_flex'] != ''){
				$arr_of_delay = explode(';', $item['delay_flex']);

				foreach($arr_of_delay as $one_delay_flex){
					$arr = explode('/', $one_delay_flex);
					if($arr[0] < 1){
						self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Delay cannot be less than 1 second');
						$result = false;
						break 2;
					}
				}
			}

			if(($item['snmp_port'] < 1) || ($item['snmp_port'] > 65535)){
				self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Invalid SNMP port');
				$result = false;
				break;
			}

			if(preg_match('/^log|eventlog\[/', $item['key_']) && ($item['value_type'] != ITEM_VALUE_TYPE_LOG)){
				self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Type of information must be Log for log key');
				$result = false;
				break;
			}

			if($item['value_type'] == ITEM_VALUE_TYPE_STR){
				$item['delta'] = 0;
			}

			if($item['value_type'] != ITEM_VALUE_TYPE_UINT64){
				$item['data_type'] = 0;
			}

			if(($item['type'] == ITEM_TYPE_AGGREGATE) && ($item['value_type'] != ITEM_VALUE_TYPE_FLOAT)){
				self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Value type must be Float for aggregate items');
				$result = false;
				break;
			}

			if($item['type'] == ITEM_TYPE_AGGREGATE){
				if(preg_match('/^((.)*)(\[\"((.)*)\"\,\"((.)*)\"\,\"((.)*)\"\,\"([0-9]+)\"\])$/i', $item['key_'], $arr)){
					$g = $arr[1];
					if(!str_in_array($g, array('grpmax', 'grpmin', 'grpsum', 'grpavg'))){
						self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, "Group function [ $g ] is not one of [ grpmax, grpmin, grpsum, grpavg ]");
						$result = false;
						break;
					}
					// Group
					$g = $arr[4];
					// Key
					$g = $arr[6];
					// Item function
					$g = $arr[8];
					if(!str_in_array($g, array('last', 'min', 'max', 'avg', 'sum', 'count'))){
						self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, "Item function [ $g ] is not one of [last, min, max, avg, sum, count]");
						$result = false;
						break;
					}
					// Parameter
					$g = $arr[10];
				}
				else{
					self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Key does not match grpfunc["group", "key", "itemfunc", "numeric param"]');
					$result = false;
					break;
				}
			}

			$sql = 'SELECT itemid,hostid '.
					' FROM items '.
					' WHERE hostid='.$item['hostid'].
						' AND key_='.zbx_dbstr($item['key_']);
			$db_item = DBfetch(DBselect($sql));
			if($db_item && $item['templateid'] == 0){
				error('An item with the Key ['.$item['key_'].'] already exists for host ['.$host['host'].']. The key must be unique.');
				return FALSE;
			}
			else if ($db_item && $item['templateid'] != 0){
				$item['hostid'] = $db_item['hostid'];
				$item['applications'] = get_same_applications_for_host($item['applications'], $db_item['hostid']);

				$result = update_item($db_item['itemid'], $item);

			return $result;
			}

			// first add mother item
			$itemid=get_dbid('items','itemid');
			$result=DBexecute('INSERT INTO items '.
					' (itemid,description,key_,hostid,delay,history,status,type,'.
						'snmp_community,snmp_oid,value_type,data_type,trapper_hosts,'.
						'snmp_port,units,multiplier,'.
						'delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,'.
						'snmpv3_privpassphrase,formula,trends,logtimefmt,valuemapid,'.
						'delay_flex,params,ipmi_sensor,templateid,authtype,username,password,publickey,privatekey)'.
				' VALUES ('.$itemid.','.zbx_dbstr($item['description']).','.zbx_dbstr($item['key_']).','.$item['hostid'].','.
							$item['delay'].','.$item['history'].','.$item['status'].','.$item['type'].','.
							zbx_dbstr($item['snmp_community']).','.zbx_dbstr($item['snmp_oid']).','.$item['value_type'].','.$item['data_type'].','.
							zbx_dbstr($item['trapper_hosts']).','.$item['snmp_port'].','.zbx_dbstr($item['units']).','.$item['multiplier'].','.
							$item['delta'].','.zbx_dbstr($item['snmpv3_securityname']).','.$item['snmpv3_securitylevel'].','.
							zbx_dbstr($item['snmpv3_authpassphrase']).','.zbx_dbstr($item['snmpv3_privpassphrase']).','.
							zbx_dbstr($item['formula']).','.$item['trends'].','.zbx_dbstr($item['logtimefmt']).','.$item['valuemapid'].','.
							zbx_dbstr($item['delay_flex']).','.zbx_dbstr($item['params']).','.
							zbx_dbstr($item['ipmi_sensor']).','.$item['templateid'].','.$item['authtype'].','.
							zbx_dbstr($item['username']).','.zbx_dbstr($item['password']).','.
							zbx_dbstr($item['publickey']).','.zbx_dbstr($item['privatekey']).')'
				);

			if ($result)
				//add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_ITEM, $itemid, $item['description'], NULL, NULL, NULL);
			else
				return $result;

			foreach($item['applications'] as $key => $appid){
				$itemappid=get_dbid('items_applications','itemappid');
				DBexecute('INSERT INTO items_applications (itemappid,itemid,applicationid) VALUES('.$itemappid.','.$itemid.','.$appid.')');
			}

			info('Added new item '.$host['host'].':'.$item['key_']);

	// add items to child hosts

			$db_hosts = get_hosts_by_templateid($host['hostid']);
			while($db_host = DBfetch($db_hosts)){
	// recursion
				$item['hostid'] = $db_host['hostid'];
				$item['applications'] = get_same_applications_for_host($item['applications'], $db_host['hostid']);
				$item['templateid'] = $itemid;

				$result = add_item($item);
				if(!$result) break;

			}

			if($result)
				return $itemid;

			if($item['templateid'] == 0){
				delete_item($itemid);
			}

*///////////////

			$result = add_item($item);

			if(!$result) break;
			$itemids[] = $result;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$new_items = self::get(array('itemids'=>$itemids, 'extendoutput'=>1, 'nopermissions'=>1));
			return $new_items;
		}
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
		$items = zbx_toArray($items);
		$itemids = array();

		$options = array(
				'itemids'=> zbx_objectValues($items, 'itemid'),
				'editable'=>1,
				'extendoutput'=>1,
				'preservekeys'=>1
			);
		$upd_items = self::get($options);
		foreach($items as $gnum => $item){
			if(!isset($upd_items[$item['itemid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}
			$itemids[] = $item['itemid'];
		}

		$result = true;

		self::BeginTransaction(__METHOD__);
		foreach($items as $inum => $item){
			$item_db_fields = $upd_items[$item['itemid']];

			unset($item_db_fields['lastvalue']);
			unset($item_db_fields['prevvalue']);
			unset($item_db_fields['lastclock']);
			unset($item_db_fields['prevorgvalue']);
			if(!check_db_fields($item_db_fields, $item)){
				error('Incorrect arguments pasted to function [CItem::update]');
				$result = false;
				break;
			}

			$result = update_item($item['itemid'], $item);
			if(!$result) break;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$upd_items = self::get(array('itemids'=>$itemids, 'extendoutput'=>1, 'nopermissions'=>1));
			return $upd_items;
		}
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
 * @param _array $items multidimensional array with item objects
 * @param array $items[0,...]['itemid']
 * @return deleted items
 */
	public static function delete($items){
		$items = zbx_toArray($items);
		$itemids = array();

		$del_items = self::get(array('itemids'=> zbx_objectValues($items, 'itemid'), 'editable'=>1, 'extendoutput'=>1, 'preservekeys'=>1));
		foreach($items as $num => $item){
			if(!isset($del_items[$item['itemid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}

			$itemids[] = $item['itemid'];
			//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_ITEM, 'Item ['.$Item['description'].']');
		}

		if(!empty($itemids)){
			$result = delete_item($itemids);
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Incorrect input parameter [ items ]');
			$result = false;
		}

		if($result){
			return zbx_cleanHashes($del_items);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}
}
?>
