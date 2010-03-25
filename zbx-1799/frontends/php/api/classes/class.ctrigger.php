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
 * File containing CTrigger class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Triggers
 *
 */
class CTrigger extends CZBXAPI{

	public static $error = array();

/**
 * Get Triggers data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $options
 * @param array $options['itemids']
 * @param array $options['hostids']
 * @param array $options['groupids']
 * @param array $options['triggerids']
 * @param array $options['applicationids']
 * @param array $options['status']
 * @param array $options['editable']
 * @param array $options['extendoutput']
 * @param array $options['count']
 * @param array $options['pattern']
 * @param array $options['limit']
 * @param array $options['order']
 * @return array|int item data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('triggerid', 'description', 'status', 'priority', 'lastchange'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('triggers' => 't.triggerid'),
			'from' => array('t' => 'triggers t'),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null,
			);

		$def_options = array(
			'nodeids'				=> null,
			'groupids'				=> null,
			'hostids'				=> null,
			'triggerids'			=> null,
			'itemids'				=> null,
			'applicationids'		=> null,
			'status'				=> null,
			'monitored' 			=> null,
			'templated'				=> null,
			'maintenance'			=> null,
			'inherited'				=> null,
			'editable'				=> null,
			'nopermissions'			=> null,
			'only_problems'			=> null,
			'skipDependent'			=> null,
			'with_unacknowledged_events' => null,
// filter
			'filter'				=> null,
			'group'					=> null,
			'host'					=> null,
			'only_true'				=> null,
			'min_severity'			=> null,
//
			'pattern'				=> '',
// OutPut
			'expand_data'			=> null,
			'output'				=> API_OUTPUT_REFER,
			'extendoutput'			=> null,
			'select_groups'			=> null,
			'select_hosts'			=> null,
			'select_items'			=> null,
			'select_functions'		=> null,
			'select_dependencies'	=> null,
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
			if(!is_null($options['select_items'])){
				$options['select_items'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_dependencies'])){
				$options['select_dependencies'] = API_OUTPUT_EXTEND;
			}
		}


// editable + PERMISSION CHECK

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

			$sql_parts['from']['f'] = 'functions f';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['r'] = 'rights r';
			$sql_parts['from']['ug'] = 'users_groups ug';
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS( '.
											' SELECT ff.triggerid '.
											' FROM functions ff, items ii '.
											' WHERE ff.triggerid=t.triggerid '.
												' AND ff.itemid=ii.itemid '.
												' AND EXISTS( '.
													' SELECT hgg.groupid '.
													' FROM hosts_groups hgg, rights rr, users_groups gg '.
													' WHERE hgg.hostid=ii.hostid '.
														' AND rr.id=hgg.groupid '.
														' AND rr.groupid=gg.usrgrpid '.
														' AND gg.userid='.$userid.
														' AND rr.permission<'.$permission.'))';
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}

			$sql_parts['from']['f'] = 'functions f';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
			$sql_parts['where']['groupid'] = DBcondition('hg.groupid', $options['groupids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['hg'] = 'hg.groupid';
			}
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['hostid'] = 'i.hostid';
			}

			$sql_parts['from']['f'] = 'functions f';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['where']['hostid'] = DBcondition('i.hostid', $options['hostids']);
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['i'] = 'i.hostid';
			}
		}

// triggerids
		if(!is_null($options['triggerids'])){
			zbx_value2array($options['triggerids']);

			$sql_parts['where']['triggerid'] = DBcondition('t.triggerid', $options['triggerids']);
		}

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['itemid'] = 'f.itemid';
			}

			$sql_parts['from']['f'] = 'functions f';
			$sql_parts['where']['itemid'] = DBcondition('f.itemid', $options['itemids']);
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
		}

// applicationids
		if(!is_null($options['applicationids'])){
			zbx_value2array($options['applicationids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['applicationid'] = 'a.applicationid';
			}

			$sql_parts['from']['f'] = 'functions f';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['from']['a'] = 'applications a';
			$sql_parts['where']['a'] = DBcondition('a.applicationid', $options['applicationids']);
			$sql_parts['where']['ia'] = 'i.hostid=a.hostid';
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
		}

// status
		if(!is_null($options['status'])){
			$sql_parts['where'][] = 't.status='.$options['status'];
		}

// monitored
		if(!is_null($options['monitored'])){
			$sql_parts['where'][] = ''.
				' NOT EXISTS ('.
					' SELECT ff.functionid'.
					' FROM functions ff'.
					' WHERE ff.triggerid=t.triggerid'.
						' AND EXISTS ('.
								' SELECT ii.itemid'.
								' FROM items ii, hosts hh'.
								' WHERE ff.itemid=ii.itemid'.
									' AND hh.hostid=ii.hostid'.
									' AND ('.
										' ii.status<>'.ITEM_STATUS_ACTIVE.
										' OR hh.status<>'.HOST_STATUS_MONITORED.
									' )'.
						' )'.
				' )';
			$sql_parts['where'][] = 't.status='.TRIGGER_STATUS_ENABLED;
		}

// maintenance
		if(!is_null($options['maintenance'])){
			$sql_parts['where'][] = (($options['maintenance'] == 0)?' NOT ':'').
				' EXISTS ('.
					' SELECT ff.functionid'.
					' FROM functions ff'.
					' WHERE ff.triggerid=t.triggerid'.
						' AND EXISTS ('.
								' SELECT ii.itemid'.
								' FROM items ii, hosts hh'.
								' WHERE ff.itemid=ii.itemid'.
									' AND hh.hostid=ii.hostid'.
									' AND hh.maintenance_status=1'.
						' )'.
				' )';
			$sql_parts['where'][] = 't.status='.TRIGGER_STATUS_ENABLED;
		}

// only_problems
		if(!is_null($options['only_problems'])){
			$sql_parts['where']['ot'] = 't.value='.TRIGGER_VALUE_TRUE;
		}
// with_unacknowledged_events
		if(!is_null($options['with_unacknowledged_events'])){
			$sql_parts['where']['unack'] = ' EXISTS('.
				' SELECT e.eventid'.
				' FROM events e'.
				' WHERE e.objectid=t.triggerid'.
					' AND e.object=0'.
					' AND e.value='.TRIGGER_VALUE_TRUE.
					' AND e.acknowledged=0)';
		}
// templated
		if(!is_null($options['templated'])){
			$sql_parts['from']['f'] = 'functions f';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';

			if($options['templated']){
				$sql_parts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else{
				$sql_parts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

// inherited
		if(!is_null($options['inherited'])){
			if($options['inherited']){
				$sql_parts['where'][] = 't.templateid<>0';
			}
			else{
				$sql_parts['where'][] = 't.templateid=0';
			}
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['triggers'] = 't.*';
		}

// expand_data
		if(!is_null($options['expand_data'])){
			$sql_parts['select']['host'] = 'h.host';
			$sql_parts['select']['hostid'] = 'h.hostid';
			$sql_parts['from']['f'] = 'functions f';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where']['description'] = ' UPPER(t.description) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['pattern']).'%');
		}


// --- FILTER ---
		if(!is_null($options['filter'])){
			zbx_value2array($options['filter']);
			
			if(isset($options['filter']['description']) && !is_null($options['filter']['description'])){
				$sql_parts['where']['description'] = 't.description='.zbx_dbstr($options['filter']['description']);
			}
			
			if(isset($options['filter']['host']) || isset($options['filter']['hostid'])){
				$sql_parts['from']['f'] = 'functions f';
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sql_parts['where']['fi'] = 'f.itemid=i.itemid';

				if(isset($options['filter']['host']) && !is_null($options['filter']['host'])){
					$sql_parts['from']['h'] = 'hosts h';
					$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
					$sql_parts['where']['host'] = 'h.host='.zbx_dbstr($options['filter']['host']);
				}

				if(isset($options['filter']['hostid']) && !is_null($options['filter']['hostid']))
					$sql_parts['where']['hostid'] = 'i.hostid='.zbx_dbstr($options['filter']['hostid']);
			}

// severity
			if(isset($options['filter']['priority']) && !is_null($options['filter']['priority'])){
				zbx_value2array($options['filter']['priority']);

				$sql_parts['where']['priority'] = DBcondition('t.priority', $options['filter']['priority']);
			}
		}
// group
		if(!is_null($options['group'])){
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['name'] = 'g.name';
			}

			$sql_parts['from']['f'] = 'functions f';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['g'] = 'groups g';
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where']['ghg'] = 'g.groupid = hg.groupid';
			$sql_parts['where']['group'] = ' UPPER(g.name)='.zbx_dbstr(zbx_strtoupper($options['group']));
		}

// host
		if(!is_null($options['host'])){
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['host'] = 'h.host';
			}

			$sql_parts['from']['f'] = 'functions f';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['i'] = DBcondition('i.hostid', $options['hostids']);
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
			$sql_parts['where']['host'] = ' UPPER(h.host)='.zbx_dbstr(zbx_strtoupper($options['host']));
		}

// only_true
		if(!is_null($options['only_true'])){

			$sql_parts['where']['ot'] = '((t.value='.TRIGGER_VALUE_TRUE.')'.
									' OR '.
									'((t.value='.TRIGGER_VALUE_FALSE.') AND (t.lastchange>'.(time() - TRIGGER_FALSE_PERIOD).')))';
		}

// min_severity
		if(!is_null($options['min_severity'])){
			$sql_parts['where'][] = 't.priority>='.$options['min_severity'];
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('COUNT(DISTINCT t.triggerid) as rowscount');

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

			$sql_parts['order'][] = 't.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('t.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('t.*', $sql_parts['select'])){
				$sql_parts['select'][] = 't.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//---------------

		$triggerids = array();

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
				' WHERE '.DBin_node('t.triggerid', $nodeids).
					$sql_where.
				$sql_group.
				$sql_order;
//SDI($sql);
		$db_res = DBselect($sql, $sql_limit);
		while($trigger = DBfetch($db_res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $trigger;
				else
					$result = $trigger['rowscount'];
			}
			else{
				$triggerids[$trigger['triggerid']] = $trigger['triggerid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$trigger['triggerid']] = array('triggerid' => $trigger['triggerid']);
				}
				else{
					if(!isset($result[$trigger['triggerid']])) $result[$trigger['triggerid']]= array();

					if(!is_null($options['select_hosts']) && !isset($result[$trigger['triggerid']]['hosts'])){
						$result[$trigger['triggerid']]['hosts'] = array();
					}
					if(!is_null($options['select_items']) && !isset($result[$trigger['triggerid']]['items'])){
						$result[$trigger['triggerid']]['items'] = array();
					}
					if(!is_null($options['select_functions']) && !isset($result[$trigger['triggerid']]['functions'])){
						$result[$trigger['triggerid']]['functions'] = array();
					}
					if(!is_null($options['select_dependencies']) && !isset($result[$trigger['triggerid']]['dependencies'])){
						$result[$trigger['triggerid']]['dependencies'] = array();
					}

// groups
					if(isset($trigger['groupid']) && is_null($options['select_groups'])){
						if(!isset($result[$trigger['triggerid']]['groups'])) $result[$trigger['triggerid']]['groups'] = array();

						$result[$trigger['triggerid']]['groups'][] = array('groupid' => $trigger['groupid']);
						unset($trigger['groupid']);
					}

// hostids
					if(isset($trigger['hostid']) && is_null($options['select_hosts'])){
						if(!isset($result[$trigger['triggerid']]['hosts'])) $result[$trigger['triggerid']]['hosts'] = array();

						$result[$trigger['triggerid']]['hosts'][] = array('hostid' => $trigger['hostid']);

						if(is_null($options['expand_data'])) unset($trigger['hostid']);
					}
// itemids
					if(isset($trigger['itemid']) && is_null($options['select_items'])){
						if(!isset($result[$trigger['triggerid']]['items']))
							$result[$trigger['triggerid']]['items'] = array();

						$result[$trigger['triggerid']]['items'][] = array('itemid' => $trigger['itemid']);
						unset($trigger['itemid']);
					}

					$result[$trigger['triggerid']] += $trigger;
				}
			}
		}

COpt::memoryPick();
		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// skipDependent		
		if(!is_null($options['skipDependent'])){
			$tids = $triggerids;
			$map = array();
			
			do{
				$sql = 'SELECT d.triggerid_down, d.triggerid_up, t.value '.
						' FROM trigger_depends d, triggers t '.
						' WHERE '.DBcondition('d.triggerid_down', $tids).
							' AND d.triggerid_up=t.triggerid';
				$db_result = DBselect($sql);
				
				$tids = array();
				while($row = DBfetch($db_result)){
					if(TRIGGER_VALUE_TRUE == $row['value']){
						if(isset($map[$row['triggerid_down']])){
							foreach($map[$row['triggerid_down']] as $triggerid => $state){
								unset($result[$triggerid]);
								unset($triggerids[$triggerid]);
							}
						}
						else{
							unset($result[$row['triggerid_down']]);
							unset($triggerids[$row['triggerid_down']]);
						}
					}
					else{
						if(isset($map[$row['triggerid_down']])){
							if(!isset($map[$row['triggerid_up']]))
								$map[$row['triggerid_up']] = array();

							$map[$row['triggerid_up']] += $map[$row['triggerid_down']];
						}
						else{
							if(!isset($map[$row['triggerid_up']]))
								$map[$row['triggerid_up']] = array();

							$map[$row['triggerid_up']][$row['triggerid_down']] = 1;
						}
						$tids[] = $row['triggerid_up'];
					}
				}
			}while(!empty($tids));
		}

// Adding Objects
// Adding trigger dependencies
		if(!is_null($options['select_dependencies']) && str_in_array($options['select_dependencies'], $subselects_allowed_outputs)){
			$deps = array();
			$depids = array();

			$sql = 'SELECT triggerid_up, triggerid_down FROM trigger_depends WHERE '.DBcondition('triggerid_down', $triggerids);
			$db_deps = DBselect($sql);
			while($db_dep = DBfetch($db_deps)){
				if(!isset($deps[$db_dep['triggerid_down']])) $deps[$db_dep['triggerid_down']] = array();
				$deps[$db_dep['triggerid_down']][$db_dep['triggerid_up']] = $db_dep['triggerid_up'];
				$depids[] = $db_dep['triggerid_up'];
			}

			$obj_params = array(
				'triggerids' => $depids,
				'output' => API_OUTPUT_EXTEND,
				'expand_data' => 1,
				'preservekeys' => 1
			);
			$allowed = self::get($obj_params); //allowed triggerids

			foreach($deps as $triggerid => $deptriggers){
				foreach($deptriggers as $num => $deptriggerid){
					if(isset($allowed[$deptriggerid])){
						$result[$triggerid]['dependencies'][] = $allowed[$deptriggerid];
					}
				}
			}
		}

// Adding groups
		if(!is_null($options['select_groups']) && str_in_array($options['select_groups'], $subselects_allowed_outputs)){
		}

// Adding hosts
		if(!is_null($options['select_hosts']) && str_in_array($options['select_hosts'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'templated_hosts' => 1,
				'output' => $options['select_hosts'],
				'triggerids' => $triggerids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$hosts = CHost::get($obj_params);
			foreach($hosts as $hostid => $host){
				$htriggers = $host['triggers'];
				unset($host['triggers']);
				foreach($htriggers as $num => $trigger){
					$result[$trigger['triggerid']]['hosts'][] = $host;
				}
			}
		}

// Adding Functions
		if(!is_null($options['select_functions']) && str_in_array($options['select_functions'], $subselects_allowed_outputs)){

			if($options['select_functions'] == API_OUTPUT_EXTEND)
				$sql_select = 'f.*';
			else 
				$sql_select = 'f.functionid, f.triggerid';

			$sql = 'SELECT '.$sql_select.
					' FROM functions f '.
					' WHERE '.DBcondition('f.triggerid',$triggerids);
			$res = DBselect($sql);
			while($function = DBfetch($res)){
				$triggerid = $function['triggerid'];
				unset($function['triggerid']);

				$result[$triggerid]['functions'][] = $function;
			}
		}

// Adding Items
		if(!is_null($options['select_items']) && str_in_array($options['select_items'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_items'],
				'triggerids' => $triggerids,
				'webitems' => 1,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$items = CItem::get($obj_params);
			foreach($items as $itemid => $item){
				$itriggers = $item['triggers'];
				unset($item['triggers']);
				foreach($itriggers as $num => $trigger){
					$result[$trigger['triggerid']]['items'][] = $item;
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
 * Get triggerid by host.host and trigger.expression
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $triggers multidimensional array with trigger objects
 * @param array $triggers[0,...]['expression']
 * @param array $triggers[0,...]['host']
 * @param array $triggers[0,...]['hostid'] OPTIONAL
 * @param array $triggers[0,...]['description'] OPTIONAL
 */

	public static function getObjects($triggerData){
		$options = array(
			'filter' => $triggerData,
			'output'=>API_OUTPUT_EXTEND
		);

		if(isset($triggerData['node']))
			$options['nodeids'] = getNodeIdByNodeName($triggerData['node']);
		else if(isset($triggerData['nodeids']))
			$options['nodeids'] = $triggerData['nodeids'];

		$result = self::get($options);
		if(isset($triggerData['expression'])){
			foreach($result as $tnum => $trigger){
				$tmp_exp = explode_exp($trigger['expression'], false);

				if(strcmp(trim($tmp_exp,' '), trim($triggerData['expression'],' ')) != 0) {
					unset($result[$tnum]);
				}
			}
		}

	return $result;
	}

	public static function exists($object){
		$keyFields = array(array('hostid', 'host'), 'description');

		$result = false;
		
		if(!isset($object['hostid']) && !isset($object['host'])){
			preg_match('/^.*?{(.+?):/u', $object['expression'], $host);
			$object['host'] = $host[1];
		}

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => 1,
		);
		if(isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if(isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$triggers = self::get($options);
		foreach($triggers as $tnum => $trigger){
			$tmp_exp = explode_exp($trigger['expression'], false);
			if(strcmp($tmp_exp, $object['expression']) == 0){
				$result = true;
				break;
			}
		}

	return $result;
	}

/**
 * Add triggers
 *
 * Trigger params: expression, description, type, priority, status, comments, url, templateid
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $triggers
 * @return boolean
 */
	public static function create($triggers){
		$triggers = zbx_toArray($triggers);
		$triggerids = array();

		try{
			self::BeginTransaction(__METHOD__);
			
			foreach($triggers as $num => $trigger){
				$trigger_db_fields = array(
					'description'	=> null,
					'expression'	=> null,
					'type'		=> 0,
					'priority'	=> 0,
					'status'	=> TRIGGER_STATUS_DISABLED,
					'comments'	=> '',
					'url'		=> '',
					'templateid'=> 0
				);

				if(!check_db_fields($trigger_db_fields, $trigger)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for trigger');
				}

				$result = add_trigger(
					$trigger['expression'],
					$trigger['description'],
					$trigger['type'],
					$trigger['priority'],
					$trigger['status'],
					$trigger['comments'],
					$trigger['url'],
					array(),
					$trigger['templateid']
				);
				if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'Trigger ['.$trigger['description'].' ]: cannot create');

				$triggerids[] = $result;
			}

			self::EndTransaction(true, __METHOD__);
			
			return array('triggerids' => $triggerids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, $error);
			return false;
		}
	}

/**
 * Update triggers
 *
 * Trigger params: expression, description, type, priority, status, comments, url, templateid
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $triggers
 * @return boolean
 */
	public static function update($triggers){
		$triggers = zbx_toArray($triggers);
		$triggerids = array();
		
		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'triggerids' => zbx_objectValues($triggers, 'triggerid'),
				'editable' => 1,
				'extendoutput' => 1,
				'preservekeys' => 1
			);
			$upd_triggers = self::get($options);
			foreach($triggers as $gnum => $trigger){
				if(!isset($upd_triggers[$trigger['triggerid']])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
				}
				$triggerids[] = $trigger['triggerid'];
			}

			
			foreach($triggers as $tnum => $trigger){
			
				$trigger_db_fields = $upd_triggers[$trigger['triggerid']];
				if(!check_db_fields($trigger_db_fields, $trigger)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for trigger');
				}
				
				$result = update_trigger(
					$trigger['triggerid'],
					$trigger['expression'],
					$trigger['description'],
					$trigger['type'],
					$trigger['priority'],
					$trigger['status'],
					$trigger['comments'],
					$trigger['url'],
					array(),
					$trigger['templateid']
				);
				if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'Trigger ['.$trigger['description'].' ]: cannot update');
			}
			
			self::EndTransaction(true, __METHOD__);
			
			return array('triggerids' => $triggerids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, $error);
			return false;
		}
	}

/**
 * Delete triggers
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $triggers multidimensional array with trigger objects
 * @param array $triggers[0,...]['triggerid']
 * @return deleted triggers
 */
	public static function delete($triggers){
		$triggers = zbx_toArray($triggers);
		$triggerids = array();

		$options = array(
			'triggerids'=>zbx_objectValues($triggers, 'triggerid'),
			'editable'=>1,
			'extendoutput'=>1,
			'preservekeys'=>1
		);
		$del_triggers = self::get($options);
		foreach($triggers as $gnum => $trigger){
			if(!isset($del_triggers[$trigger['triggerid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}

			$triggerids[] = $trigger['triggerid'];
			//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TRIGGER, 'Trigger ['.$trigger['description'].']');
		}

		self::BeginTransaction(__METHOD__);
		if(!empty($triggerids)){
			$result = delete_trigger($triggerids);
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ triggerids ]');
			$result = false;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return array('triggerids' => $triggerids);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}

/**
 * Add dependency for trigger
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $triggers_data
 * @param array $triggers_data['triggerid]
 * @param array $triggers_data['depends_on_triggerid']
 * @return boolean
 */
	public static function addDependencies($triggers_data){
		$triggers_data = zbx_toArray($triggers_data);

		$result = true;

		self::BeginTransaction(__METHOD__);

		foreach($triggers_data as $num => $dep){
			$result &= (bool) insert_dependency($dep['triggerid'], $dep['depends_on_triggerid']);
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result)
			return true;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

/**
 * Delete trigger dependencis
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $triggers multidimensional array with trigger objects
 * @param array $triggers[0,...]['triggerid']
 * @return boolean
 */
	public static function deleteDependencies($triggers){
		$triggers = zbx_toArray($triggers);

		$triggerids = array();
		foreach($triggers as $num => $trigger){
			$triggerids[] = $trigger['triggerid'];
		}

		self::BeginTransaction(__METHOD__);

		$result = delete_dependencies_by_triggerid($triggerids);

		$result = self::EndTransaction($result, __METHOD__);
		if($result)
			return true;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
}

?>
