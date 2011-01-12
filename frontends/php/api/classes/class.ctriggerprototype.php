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
 * @package API
 */


class CTriggerPrototype extends CZBXAPI{

/**
 * Get TriggerPrototypes data
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
			'where' => array('t.flags='.ZBX_FLAG_DISCOVERY_CHILD),
			'group' => array(),
			'order' => array(),
			'limit' => null,
		);

		$def_options = array(
			'nodeids'				=> null,
			'groupids'				=> null,
			'templateids'			=> null,
			'hostids'				=> null,
			'triggerids'			=> null,
			'itemids'				=> null,
			'applicationids'		=> null,
			'discoveryids'			=> null,
			'functions'				=> null,
			'inherited'				=> null,
			'templated'				=> null,
			'monitored' 			=> null,
			'active' 				=> null,
			'maintenance'			=> null,

			'withUnacknowledgedEvents'		=>	null,
			'withAcknowledgedEvents'		=>	null,
			'withLastEventUnacknowledged'	=>	null,

			'skipDependent'			=> null,
			'nopermissions'			=> null,
			'editable'				=> null,
// timing
			'lastChangeSince'		=> null,
			'lastChangeTill'		=> null,
// filter
			'group'					=> null,
			'host'					=> null,
			'only_true'				=> null,
			'min_severity'			=> null,

			'filter'					=> null,
			'search'					=> null,
			'searchByAny'			=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,

// OutPut
			'expandData'			=> null,
			'expandDescription'		=> null,
			'output'				=> API_OUTPUT_REFER,
			'selectGroups'			=> null,
			'selectHosts'			=> null,
			'selectItems'			=> null,
			'select_functions'		=> null,
			'selectDiscoveryRule'	=> null,
			'countOutput'			=> null,
			'groupCount'			=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null,
			'limitSelects'			=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(is_array($options['output'])){
			unset($sql_parts['select']['triggers']);
			$sql_parts['select']['triggerid'] = ' t.triggerid';
			foreach($options['output'] as $key => $field){
				$sql_parts['select'][$field] = ' t.'.$field;
			}

			$options['output'] = API_OUTPUT_CUSTOM;
		}

// editable + PERMISSION CHECK

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;
/*/
			$sql_parts['where'][] = ' EXISTS(  '.
						' SELECT tt.triggerid  '.
						' FROM triggers tt,functions ff,items ii,hosts_groups hgg,rights rr,users_groups ugg '.
						' WHERE t.triggerid=tt.triggerid  '.
							' AND ff.triggerid=tt.triggerid  '.
							' AND ff.itemid=ii.itemid  '.
							' AND hgg.hostid=ii.hostid  '.
							' AND rr.id=hgg.groupid  '.
							' AND rr.groupid=ugg.usrgrpid  '.
							' AND ugg.userid='.$userid.
							' AND rr.permission>='.$permission.
							' AND NOT EXISTS(  '.
								' SELECT fff.triggerid  '.
								' FROM functions fff, items iii  '.
								' WHERE fff.triggerid=tt.triggerid '.
									' AND fff.itemid=iii.itemid '.		'    '.
									' AND EXISTS( '.
										' SELECT hggg.groupid '.
										' FROM hosts_groups hggg, rights rrr, users_groups uggg '.
										' WHERE hggg.hostid=iii.hostid '.
											' AND rrr.id=hggg.groupid '.
											' AND rrr.groupid=uggg.usrgrpid '.
											' AND uggg.userid='.$userid.
											' AND rrr.permission<'.$permission.
										' ) '.
								' ) '.
						' ) ';
//*/
//*/
			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
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
//*/
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}

			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
			$sql_parts['where']['groupid'] = DBcondition('hg.groupid', $options['groupids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['hg'] = 'hg.groupid';
			}
		}

// templateids
		if(!is_null($options['templateids'])){
			zbx_value2array($options['templateids']);

			if(!is_null($options['hostids'])){
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else{
				$options['hostids'] = $options['templateids'];
			}
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['hostid'] = 'i.hostid';
			}

			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['items'] = 'items i';
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

			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['where']['itemid'] = DBcondition('f.itemid', $options['itemids']);
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['f'] = 'f.itemid';
			}
		}

// applicationids
		if(!is_null($options['applicationids'])){
			zbx_value2array($options['applicationids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['applicationid'] = 'a.applicationid';
			}

			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['applications'] = 'applications a';
			$sql_parts['where']['a'] = DBcondition('a.applicationid', $options['applicationids']);
			$sql_parts['where']['ia'] = 'i.hostid=a.hostid';
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
		}

// discoveryids
		if(!is_null($options['discoveryids'])){
			zbx_value2array($options['discoveryids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['itemid'] = 'id.parent_itemid';
			}
			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['item_discovery'] = 'item_discovery id';
			$sql_parts['where']['fid'] = 'f.itemid=id.itemid';
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where'][] = DBcondition('id.parent_itemid', $options['discoveryids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['id'] = 'id.parent_itemid';
			}
		}

// functions
		if(!is_null($options['functions'])){
			zbx_value2array($options['functions']);

			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where'][] = DBcondition('f.function', $options['functions'], false, true);
		}

// monitored
		if(!is_null($options['monitored'])){
			$sql_parts['where']['monitored'] = ''.
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
			$sql_parts['where']['status'] = 't.status='.TRIGGER_STATUS_ENABLED;
		}

// active
		if(!is_null($options['active'])){
			$sql_parts['where']['active'] = ''.
				' NOT EXISTS ('.
					' SELECT ff.functionid'.
					' FROM functions ff'.
					' WHERE ff.triggerid=t.triggerid'.
						' AND EXISTS ('.
							' SELECT ii.itemid'.
							' FROM items ii, hosts hh'.
							' WHERE ff.itemid=ii.itemid'.
								' AND hh.hostid=ii.hostid'.
								' AND  hh.status<>'.HOST_STATUS_MONITORED.
						' )'.
				' )';
			$sql_parts['where']['status'] = 't.status='.TRIGGER_STATUS_ENABLED;
		}

// maintenance
		if(!is_null($options['maintenance'])){
			$sql_parts['where'][] = (($options['maintenance'] == 0) ? ' NOT ':'').
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

// lastChangeSince
		if(!is_null($options['lastChangeSince'])){
			$sql_parts['where']['lastchangesince'] = 't.lastchange>'.$options['lastChangeSince'];
		}

// lastChangeTill
		if(!is_null($options['lastChangeTill'])){
			$sql_parts['where']['lastchangetill'] = 't.lastchange<'.$options['lastChangeTill'];
		}

// withUnacknowledgedEvents
		if(!is_null($options['withUnacknowledgedEvents'])){
			$sql_parts['where']['unack'] = ' EXISTS('.
				' SELECT e.eventid'.
				' FROM events e'.
				' WHERE e.objectid=t.triggerid'.
					' AND e.object='.EVENT_OBJECT_TRIGGER.
					' AND e.value_changed='.TRIGGER_VALUE_CHANGED_YES.
					' AND e.value='.TRIGGER_VALUE_TRUE.
					' AND e.acknowledged=0)';
		}
// withAcknowledgedEvents
		if(!is_null($options['withAcknowledgedEvents'])){
			$sql_parts['where']['ack'] = 'NOT EXISTS('.
				' SELECT e.eventid'.
				' FROM events e'.
				' WHERE e.objectid=t.triggerid'.
					' AND e.object='.EVENT_OBJECT_TRIGGER.
					' AND e.value_changed='.TRIGGER_VALUE_CHANGED_YES.
					' AND e.value='.TRIGGER_VALUE_TRUE.
					' AND e.acknowledged=0)';
		}

// templated
		if(!is_null($options['templated'])){
			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts'] = 'hosts h';
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
				$sql_parts['where'][] = 't.templateid IS NOT NULL';
			}
			else{
				$sql_parts['where'][] = 't.templateid IS NULL';
			}
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('triggers t', $options, $sql_parts);
		}

// --- FILTER ---
		if(is_array($options['filter'])){
			zbx_db_filter('triggers t', $options, $sql_parts);

			if(isset($options['filter']['host']) && !is_null($options['filter']['host'])){
				zbx_value2array($options['filter']['host']);

				$sql_parts['from']['functions'] = 'functions f';
				$sql_parts['from']['items'] = 'items i';
				$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sql_parts['where']['fi'] = 'f.itemid=i.itemid';

				$sql_parts['from']['hosts'] = 'hosts h';
				$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
				$sql_parts['where']['host'] = DBcondition('h.host', $options['filter']['host'], false, true);
			}

			if(isset($options['filter']['hostid']) && !is_null($options['filter']['hostid'])){
				zbx_value2array($options['filter']['hostid']);

				$sql_parts['from']['functions'] = 'functions f';
				$sql_parts['from']['items'] = 'items i';
				$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
				$sql_parts['where']['fi'] = 'f.itemid=i.itemid';

				$sql_parts['where']['hostid'] = DBcondition('i.hostid', $options['filter']['hostid']);
			}
		}

// group
		if(!is_null($options['group'])){
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['name'] = 'g.name';
			}

			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['groups'] = 'groups g';
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

			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts'] = 'hosts h';
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

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['triggers'] = 't.*';
		}

// expandData
		if(!is_null($options['expandData'])){
			$sql_parts['select']['host'] = 'h.host';
			$sql_parts['select']['hostid'] = 'h.hostid';
			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['ft'] = 'f.triggerid=t.triggerid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('COUNT(DISTINCT t.triggerid) as rowscount');

// groupCount
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

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
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

					if(!is_null($options['selectHosts']) && !isset($result[$trigger['triggerid']]['hosts'])){
						$result[$trigger['triggerid']]['hosts'] = array();
					}
					if(!is_null($options['selectItems']) && !isset($result[$trigger['triggerid']]['items'])){
						$result[$trigger['triggerid']]['items'] = array();
					}
					if(!is_null($options['select_functions']) && !isset($result[$trigger['triggerid']]['functions'])){
						$result[$trigger['triggerid']]['functions'] = array();
					}
					if(!is_null($options['selectDiscoveryRule']) && !isset($result[$trigger['triggerid']]['discoveryRule'])){
						$result[$trigger['triggerid']]['discoveryRule'] = array();
					}

// groups
					if(isset($trigger['groupid']) && is_null($options['selectGroups'])){
						if(!isset($result[$trigger['triggerid']]['groups'])) $result[$trigger['triggerid']]['groups'] = array();

						$result[$trigger['triggerid']]['groups'][] = array('groupid' => $trigger['groupid']);
						unset($trigger['groupid']);
					}

// hostids
					if(isset($trigger['hostid']) && is_null($options['selectHosts'])){
						if(!isset($result[$trigger['triggerid']]['hosts'])) $result[$trigger['triggerid']]['hosts'] = array();

						$result[$trigger['triggerid']]['hosts'][] = array('hostid' => $trigger['hostid']);

						if(is_null($options['expandData'])) unset($trigger['hostid']);
					}
// itemids
					if(isset($trigger['itemid']) && is_null($options['selectItems'])){
						if(!isset($result[$trigger['triggerid']]['items']))
							$result[$trigger['triggerid']]['items'] = array();

						$result[$trigger['triggerid']]['items'][] = array('itemid' => $trigger['itemid']);
						unset($trigger['itemid']);
					}

					$result[$trigger['triggerid']] += $trigger;
				}
			}
		}

Copt::memoryPick();
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

// withLastEventUnacknowledged
		if(!is_null($options['withLastEventUnacknowledged'])){
			$eventids = array();
			$sql = 'SELECT max(e.eventid) as eventid, e.objectid'.
					' FROM events e '.
					' WHERE e.object='.EVENT_OBJECT_TRIGGER.
						' AND '.DBcondition('e.objectid', $triggerids).
						' AND '.DBcondition('e.value', array(TRIGGER_VALUE_TRUE)).
						' AND e.value_changed='.TRIGGER_VALUE_CHANGED_YES.
					' GROUP BY e.objectid';
			$events_db = DBselect($sql);
			while($event = DBfetch($events_db)){
				$eventids[] = $event['eventid'];
			}

			$correct_triggerids = array();
			$sql = 'SELECT e.objectid'.
					' FROM events e '.
					' WHERE '.DBcondition('e.eventid', $eventids).
						' AND e.acknowledged=0';
			$triggers_db = DBselect($sql);
			while($trigger = DBfetch($triggers_db)){
				$correct_triggerids[$trigger['objectid']] = $trigger['objectid'];
			}
			foreach($result as $triggerid => $trigger){
				if(!isset($correct_triggerids[$triggerid])){
					unset($result[$triggerid]);
					unset($triggerids[$triggerid]);
				}

			}
		}

// Adding Objects

// Adding groups
		if(!is_null($options['selectGroups']) && str_in_array($options['selectGroups'], $subselects_allowed_outputs)){
			$obj_params = array(
					'nodeids' => $nodeids,
					'output' => $options['selectGroups'],
					'triggerids' => $triggerids,
					'preservekeys' => 1
				);
			$groups = CHostgroup::get($obj_params);
			foreach($groups as $groupid => $group){
				$gtriggers = $group['triggers'];
				unset($group['triggers']);

				foreach($gtriggers as $num => $trigger){
					$result[$trigger['triggerid']]['groups'][] = $group;
				}
			}
		}
// Adding hosts
		if(!is_null($options['selectHosts'])){

			$obj_params = array(
				'nodeids' => $nodeids,
				'triggerids' => $triggerids,
				'templated_hosts' => 1,
				'nopermissions' => 1,
				'preservekeys' => 1
			);

			if(is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectHosts'];
				$hosts = CHost::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($hosts, 'host');
				foreach($hosts as $hostid => $host){
					unset($hosts[$hostid]['triggers']);

					$count = array();
					foreach($host['triggers'] as $tnum => $trigger){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$trigger['triggerid']])) $count[$trigger['triggerid']] = 0;
							$count[$trigger['triggerid']]++;

							if($count[$trigger['triggerid']] > $options['limitSelects']) continue;
						}

						$result[$trigger['triggerid']]['hosts'][] = &$hosts[$hostid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectHosts']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$hosts = CHost::get($obj_params);
				$hosts = zbx_toHash($hosts, 'hostid');
				foreach($result as $triggerid => $trigger){
					if(isset($hosts[$triggerid]))
						$result[$triggerid]['hosts'] = $hosts[$triggerid]['rowscount'];
					else
						$result[$triggerid]['hosts'] = 0;
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
		if(!is_null($options['selectItems']) && str_in_array($options['selectItems'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['selectItems'],
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

// Adding discoveryRule
		if(!is_null($options['selectDiscoveryRule'])){
			$ruleids = $rule_map = array();

			$sql = 'SELECT id.parent_itemid, td.triggerid'.
					' FROM trigger_discovery td, item_discovery id, functions f'.
					' WHERE '.DBcondition('td.triggerid', $triggerids).
						' AND td.parent_triggerid=f.triggerid'.
						' AND f.itemid=id.itemid';
			$db_rules = DBselect($sql);
			while($rule = DBfetch($db_rules)){
				$ruleids[$rule['parent_itemid']] = $rule['parent_itemid'];
				$rule_map[$rule['triggerid']] = $rule['parent_itemid'];
			}

			$obj_params = array(
				'nodeids' => $nodeids,
				'itemids' => $ruleids,
				'nopermissions' => 1,
				'preservekeys' => 1,
			);

			if(is_array($options['selectDiscoveryRule']) || str_in_array($options['selectDiscoveryRule'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectDiscoveryRule'];
				$discoveryRules = CItem::get($obj_params);

				foreach($result as $triggerid => $trigger){
					if(isset($rule_map[$triggerid]) && isset($discoveryRules[$rule_map[$triggerid]])){
						$result[$triggerid]['discoveryRule'] = $discoveryRules[$rule_map[$triggerid]];
					}
				}
			}
		}

// expandDescription
		if(!is_null($options['expandDescription'])){
// Function compare values {{{
			foreach($result as $tnum => $trigger){
				preg_match_all('/\$([1-9])/u', $trigger['description'], $numbers);
				preg_match_all('~{[0-9]+}[+\-\*/<>=#]?[\(]*(?P<val>[+\-0-9]+)[\)]*~u', $trigger['expression'], $matches);

				foreach($numbers[1] as $i){
					$rep = isset($matches['val'][$i-1]) ? $matches['val'][$i-1] : '';
					$result[$tnum]['description'] = str_replace('$'.($i), $rep, $result[$tnum]['description']);
				}
			}
// }}}

			$functionids = array();
			$triggers_to_expand_hosts = array();
			$triggers_to_expand_items = array();
			$triggers_to_expand_items2 = array();
			foreach($result as $tnum => $trigger){

				preg_match_all('/{HOSTNAME([1-9]?)}/u', $trigger['description'], $hnums);
				if(!empty($hnums[1])){
					preg_match_all('/{([0-9]+)}/u', $trigger['expression'], $funcs);
					$funcs = $funcs[1];

					foreach($hnums[1] as $fnum){
						$fnum = $fnum ? $fnum : 1;
						if(isset($funcs[$fnum-1])){
							$functionid = $funcs[$fnum-1];
							$functionids[$functionid] = $functionid;
							$triggers_to_expand_hosts[$trigger['triggerid']][$functionid] = $fnum;
						}
					}
				}

				preg_match_all('/{ITEM.LASTVALUE([1-9]?)}/u', $trigger['description'], $inums);
				if(!empty($inums[1])){
					preg_match_all('/{([0-9]+)}/u', $trigger['expression'], $funcs);
					$funcs = $funcs[1];

					foreach($inums[1] as $fnum){
						$fnum = $fnum ? $fnum : 1;
						if(isset($funcs[$fnum-1])){
							$functionid = $funcs[$fnum-1];
							$functionids[$functionid] = $functionid;
							$triggers_to_expand_items[$trigger['triggerid']][$functionid] = $fnum;
						}
					}
				}

				preg_match_all('/{ITEM.VALUE([1-9]?)}/u', $trigger['description'], $inums);
				if(!empty($inums[1])){
					preg_match_all('/{([0-9]+)}/u', $trigger['expression'], $funcs);
					$funcs = $funcs[1];

					foreach($inums[1] as $fnum){
						$fnum = $fnum ? $fnum : 1;
						if(isset($funcs[$fnum-1])){
							$functionid = $funcs[$fnum-1];
							$functionids[$functionid] = $functionid;
							$triggers_to_expand_items2[$trigger['triggerid']][$functionid] = $fnum;
						}
					}
				}
			}

			if(!empty($functionids)){
				$sql = 'SELECT DISTINCT f.triggerid, f.functionid, h.host, i.lastvalue'.
						' FROM functions f,items i,hosts h'.
						' WHERE f.itemid=i.itemid'.
							' AND i.hostid=h.hostid'.
							' AND h.status<>'.HOST_STATUS_TEMPLATE.
							' AND '.DBcondition('f.functionid', $functionids);
				$db_funcs = DBselect($sql);
				while($func = DBfetch($db_funcs)){
					if(isset($triggers_to_expand_hosts[$func['triggerid']][$func['functionid']])){

						$fnum = $triggers_to_expand_hosts[$func['triggerid']][$func['functionid']];
						if($fnum == 1)
							$result[$func['triggerid']]['description'] = str_replace('{HOSTNAME}', $func['host'], $result[$func['triggerid']]['description']);

						$result[$func['triggerid']]['description'] = str_replace('{HOSTNAME'.$fnum.'}', $func['host'], $result[$func['triggerid']]['description']);
					}

					if(isset($triggers_to_expand_items[$func['triggerid']][$func['functionid']])){
						$fnum = $triggers_to_expand_items[$func['triggerid']][$func['functionid']];
						if($fnum == 1)
							$result[$func['triggerid']]['description'] = str_replace('{ITEM.LASTVALUE}', $func['lastvalue'], $result[$func['triggerid']]['description']);

						$result[$func['triggerid']]['description'] = str_replace('{ITEM.LASTVALUE'.$fnum.'}', $func['lastvalue'], $result[$func['triggerid']]['description']);
					}

					if(isset($triggers_to_expand_items2[$func['triggerid']][$func['functionid']])){
						$fnum = $triggers_to_expand_items2[$func['triggerid']][$func['functionid']];
						if($fnum == 1)
							$result[$func['triggerid']]['description'] = str_replace('{ITEM.VALUE}', $func['lastvalue'], $result[$func['triggerid']]['description']);

						$result[$func['triggerid']]['description'] = str_replace('{ITEM.VALUE'.$fnum.'}', $func['lastvalue'], $result[$func['triggerid']]['description']);
					}
				}
			}

			foreach($result as $tnum => $trigger){
				if($res = preg_match_all('/'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $trigger['description'], $arr)){
					$macros = CUserMacro::getMacros($arr[1], array('triggerid' => $trigger['triggerid']));

					$search = array_keys($macros);
					$values = array_values($macros);

					$result[$tnum]['description'] = str_replace($search, $values, $trigger['description']);
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

	public static function exists($object){
		$keyFields = array(array('hostid', 'host'), 'description');

		$result = false;

		if(!isset($object['hostid']) && !isset($object['host'])){
			$expression = $object['expression'];
			$expressionData = parseTriggerExpressions($expression, true);

			if( isset($expressionData[$expression]['errors']) ) {
				//showExpressionErrors($expression, $expressionData[$expression]['errors']);
				return false;
			}

			if(!isset($expressionData[$expression]['hosts']) || !is_array($expressionData[$expression]['hosts']) || !count($expressionData[$expression]['hosts'])) {
				//error(S_TRIGGER_EXPRESSION_HOST_DOES_NOT_EXISTS_ERROR);
				return false;
			}

			reset($expressionData[$expression]['hosts']);
			$hData =& $expressionData[$expression]['hosts'][key($expressionData[$expression]['hosts'])];
			$object['host'] = zbx_substr($expression, $hData['openSymbolNum']+1, $hData['closeSymbolNum']-($hData['openSymbolNum']+1));
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
					'description' => null,
					'expression' => null,
					'error' => _('Trigger just added. No status update so far.'),
					'value'	=> 2,
				);
				if(!check_db_fields($trigger_db_fields, $trigger)){
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for trigger'));
				}

				$expressionData = parseTriggerExpressions($trigger['expression'], true);

				if(isset($expressionData[$trigger['expression']]['errors'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, $expressionData[$trigger['expression']]['errors']);
				}

				if(CTrigger::exists(array(
					'description' => $trigger['description'],
					'expression' => $trigger['expression'])
				)){
					self::exception(ZBX_API_ERROR_PARAMETERS,
						sprintf(_('Trigger [%1$s:%2$s] already exists.'), $trigger['description'], $trigger['expression']));
				}
			}

			self::createReal($triggers);

			$createdTriggers = self::get(array(
				'triggerids' => array_keys($triggers),
				'output' => API_OUTPUT_REFER,
				'selectItems' => API_OUTPUT_EXTEND
			));
			foreach($createdTriggers as $createdTrigger){
				$has_prototype = false;

				foreach($createdTrigger['items'] as $titem){
					if($titem['flags'] == ZBX_FLAG_DISCOVERY_CHILD){
						$has_prototype = true;
						break;
					}
				}
				if(!$has_prototype){
					self::exception(ZBX_API_ERROR_PARAMETERS,
						sprintf(_('Trigger [%1$s:%2$s] does not have item prototype.'), $trigger['description'], $trigger['expression']));
				}
			}

			foreach($triggers as $trigger)
				self::inherit($trigger);

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
 * @param array $triggers
 * @return boolean
 */
	public static function update($triggers){
		$triggers = zbx_toArray($triggers);
		$triggerids = zbx_objectValues($triggers, 'triggerid');

		try{
			self::BeginTransaction(__METHOD__);

			$options = array(
				'triggerids' => $triggerids,
				'editable' => true,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => true,
			);
			$dbTriggers = self::get($options);
			foreach($triggers as $tnum => $trigger){
				if(!isset($dbTriggers[$trigger['triggerid']])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
				}

				if(!isset($trigger['triggerid']))
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for trigger'));


				$dbTrigger = $dbTriggers[$trigger['triggerid']];

				if(isset($trigger['expression'])){
					$expression_full = explode_exp($dbTrigger['expression']);
					if(strcmp($trigger['expression'], $expression_full) == 0){
						unset($triggers[$tnum]['expression']);
					}
				}

				if(isset($trigger['description']) && strcmp($trigger['description'], $dbTrigger['description']) == 0)
					unset($triggers[$tnum]['description']);
				if(isset($trigger['priority']) && ($trigger['priority'] == $dbTrigger['priority']))
					unset($triggers[$tnum]['priority']);
				if(isset($trigger['priority']) && ($trigger['priority'] == $dbTriggers[$trigger['triggerid']]['priority']))
					unset($triggers[$tnum]['priority']);
				if(isset($trigger['type']) && ($trigger['type'] == $dbTriggers[$trigger['triggerid']]['type']))
					unset($triggers[$tnum]['type']);
				if(isset($trigger['url']) && ($trigger['url'] == $dbTriggers[$trigger['triggerid']]['url']))
					unset($triggers[$tnum]['url']);
				if(isset($trigger['status']) && ($trigger['status'] == $dbTriggers[$trigger['triggerid']]['status']))
					unset($triggers[$tnum]['status']);
			}

			self::updateReal($triggers);

			$updatedTriggers = self::get(array(
				'triggerids' => array_keys($triggers),
				'output' => API_OUTPUT_REFER,
				'selectItems' => API_OUTPUT_EXTEND
			));
			foreach($updatedTriggers as $updatedTrigger){
				$has_prototype = false;

				foreach($updatedTrigger['items'] as $titem){
					if($titem['flags'] == ZBX_FLAG_DISCOVERY_CHILD){
						$has_prototype = true;
						break;
					}
				}
				if(!$has_prototype){
					self::exception(ZBX_API_ERROR_PARAMETERS,
						sprintf(_('Trigger [%1$s] does not have item prototype.'), $trigger['description']));
				}
			}

			foreach($triggers as $trigger)
				self::inherit($trigger);


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
 * @param array $triggerids array with trigger ids
 * @return array
 */
	public static function delete($triggerids, $nopermissions=false){
		$triggerids = zbx_toArray($triggerids);

		try{
			self::BeginTransaction(__METHOD__);

			if(empty($triggerids)) self::exception(ZBX_API_ERROR_PARAMETERS, 'Empty input parameter');

			$options = array(
				'triggerids' => $triggerids,
				'output' => API_OUTPUT_EXTEND,
				'editable' => true,
				'preservekeys' => true
			);
			$del_triggers = self::get($options);

// TODO: remove $nopermissions hack
			if(!$nopermissions){
				foreach($triggerids as $gnum => $triggerid){
					if(!isset($del_triggers[$triggerid])){
						self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
					}

					if($del_triggers[$triggerid]['templateid'] != 0){
						self::exception(ZBX_API_ERROR_PARAMETERS,
							sprintf(_('Cannot delete templated trigger [%1$s:%2$s]'),
								$del_triggers[$triggerid]['description'],
								explode_exp($del_triggers[$triggerid]['expression'], false))
						);
					}
				}
			}

//			if(!empty($trigger_prototypes)){
//				$sql = 'SELECT triggerid FROM trigger_discovery WHERE '.
//						DBcondition('parent_triggerid', $trigger_prototypes);
//				$db_triggers = DBselect($sql);
//				while($trigger = DBfetch($db_triggers)){
//					$triggerids[] = $trigger['triggerid'];
//				}
//			}

// get child triggers
			$parent_triggerids = $triggerids;
			do{
				$db_items = DBselect('SELECT triggerid FROM triggers WHERE ' . DBcondition('templateid', $parent_triggerids));
				$parent_triggerids = array();
				while($db_trigger = DBfetch($db_items)){
					$parent_triggerids[] = $db_trigger['triggerid'];
					$triggerids[$db_trigger['triggerid']] = $db_trigger['triggerid'];
				}
			} while(!empty($parent_triggerids));


// select all triggers which are deleted (include childs)
			$options = array(
				'triggerids' => $triggerids,
				'output' => API_OUTPUT_EXTEND,
				'nopermissions' => true,
				'preservekeys' => true,
			);
			$del_triggers = self::get($options);

			DB::delete('events', array(
				'objectid' => $triggerids,
				'object' => EVENT_OBJECT_TRIGGER,
			));

			DB::delete('sysmaps_elements', array(
				'elementid' => $triggerids,
				'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER,
			));

// disable actions
			$actionids = array();
			$sql = 'SELECT DISTINCT actionid '.
					' FROM conditions '.
					' WHERE conditiontype='.CONDITION_TYPE_TRIGGER.
						' AND '.DBcondition('value', $triggerids, false, true);   // FIXED[POSIBLE value type violation]!!!
			$db_actions = DBselect($sql);
			while($db_action = DBfetch($db_actions)){
				$actionids[$db_action['actionid']] = $db_action['actionid'];
			}

			DBexecute('UPDATE actions '.
					' SET status='.ACTION_STATUS_DISABLED.
					' WHERE '.DBcondition('actionid', $actionids));

// delete action conditions
			DB::delete('conditions', array(
				'conditiontype' => CONDITION_TYPE_TRIGGER,
				'value' => $triggerids,
			));


// TODO: REMOVE info
			foreach($del_triggers as $triggerid => $trigger){
				info(_s('Trigger prototype [%1$s:%2$s] deleted.', $trigger['description'], explode_exp($trigger['expression'], false)));
				add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TRIGGER, $trigger['triggerid'], $trigger['description'].':'.$trigger['expression'], NULL, NULL, NULL);
			}


			DB::delete('triggers', array('triggerid' => $triggerids));

			update_services_status_all();


			self::EndTransaction(true, __METHOD__);
			return array('triggerids' => $triggerids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

	protected static function createReal(&$triggers){
		$triggers = zbx_toArray($triggers);

		$triggerids = DB::insert('triggers', $triggers);

		foreach($triggers as $tnum => $trigger){
			$triggerid = $triggers[$tnum]['triggerid'] = $triggerids[$tnum];

			addEvent($triggerid, TRIGGER_VALUE_UNKNOWN);

			$expression = implode_exp($trigger['expression'], $triggerid);
			if(is_null($expression)){
				self::exception(_('Cannot implode expression'));
			}
			DB::update('triggers', array(
				'values' => array('expression' => $expression),
				'where' => array('triggerid='.$triggerid)
			));

			info(sprintf(_('Trigger [%1$s:%2$s] created.'), $trigger['description'], $trigger['expression']));
		}

	}

	protected static function updateReal($triggers){
		$triggers = zbx_toArray($triggers);

		$options = array(
			'triggerids' => zbx_objectValues($triggers, 'triggerid'),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1,
			'nopermissions' => 1,
		);
		$dbTriggers = self::get($options);

		$description_changed = $expression_changed = false;
		foreach($triggers as $tnum => &$trigger){
			$dbTrigger = $dbTriggers[$trigger['triggerid']];

			if(isset($trigger['description']) && (strcmp($dbTrigger['description'], $trigger['description']) != 0)){
				$description_changed = true;
			}


			$expression_full = explode_exp($dbTrigger['expression'], 0);
			if(isset($trigger['expression']) && (strcmp($expression_full, $trigger['expression']) != 0)){
				$expression_changed = true;
				$expression_full = $trigger['expression'];
				$trigger['error'] = 'Trigger expression updated. No status update so far.';
			}


			if($description_changed || $expression_changed){
				$expressionData = parseTriggerExpressions($expression_full, true);
				if(isset($expressionData[$expression_full]['errors'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, $expressionData[$expression_full]['errors']);
				}

				reset($expressionData[$expression_full]['hosts']);
				$hData =& $expressionData[$expression_full]['hosts'][key($expressionData[$expression_full]['hosts'])];
				$host = zbx_substr($expression_full, $hData['openSymbolNum']+1, $hData['closeSymbolNum']-($hData['openSymbolNum']+1));

				$options = array(
					'filter' => array('description' => $trigger['description'], 'host' => $host),
					'output' => API_OUTPUT_EXTEND,
					'editable' => 1,
					'nopermissions' => 1,
				);
				$triggers_exist = CTrigger::get($options);

				$trigger_exist = false;
				foreach($triggers_exist as $tnum => $tr){
					$tmp_exp = explode_exp($tr['expression'], false);
					if(strcmp($tmp_exp, $expression_full) == 0){
						$trigger_exist = $tr;
						break;
					}
				}
				if($trigger_exist && ($trigger_exist['triggerid'] != $trigger['triggerid'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_TRIGGER.' ['.$trigger['description'].'] '.S_ALREADY_EXISTS_SMALL);
				}
			}


			if($expression_changed){
				delete_function_by_triggerid($trigger['triggerid']);

				$trigger['expression'] = implode_exp($expression_full, $trigger['triggerid']);

				if(isset($trigger['status']) && ($trigger['status'] != TRIGGER_STATUS_ENABLED)){
					if($trigger['value_flags'] == TRIGGER_VALUE_FLAG_NORMAL){
						addEvent($trigger['triggerid'], TRIGGER_VALUE_UNKNOWN);

						$trigger['value_flags'] = TRIGGER_VALUE_FLAG_UNKNOWN;
					}
				}
			}

			$trigger_update = $trigger;
			if(!$description_changed)
				unset($trigger_update['description']);
			if(!$expression_changed)
				unset($trigger_update['expression']);

			DB::update('triggers', array(
				'values' => $trigger_update,
				'where' => array('triggerid='.$trigger['triggerid'])
			));

			$description = isset($trigger['description']) ? $trigger['description'] : $dbTrigger['description'];
			$expression = isset($trigger['expression']) ? $trigger['expression'] : explode_exp($dbTrigger['expression'], false);
			info(_s('Trigger prototype [%1$s:%2$s] updated.', $description, $expression));
		}
		unset($trigger);
	}

	protected static function inherit($trigger, $hostids=null){
		$triggerTemplate = CTemplate::get(array(
			'triggerids' => $trigger['triggerid'],
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => 1,
		));
		$triggerTemplate = reset($triggerTemplate);
		if(!$triggerTemplate) return true;

		if(!isset($trigger['expression']) || !isset($trigger['description'])){
			$options = array(
				'triggerids' => $trigger['triggerid'],
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => true,
				'nopermissions' => true,
			);
			$dbTrigger = self::get($options);
			$dbTrigger = reset($dbTrigger);

			if(!isset($trigger['description']))
				$trigger['description'] = $dbTrigger['description'];
			if(!isset($trigger['expression']))
				$trigger['expression'] = explode_exp($dbTrigger['expression'], 0);
		}

		$options = array(
			'templateids' => $triggerTemplate['templateid'],
			'output' => array('hostid', 'host'),
			'preservekeys' => 1,
			'hostids' => $hostids,
			'nopermissions' => 1,
			'templated_hosts' => 1,
		);
		$chd_hosts = CHost::get($options);

		foreach($chd_hosts as $chd_host){
			$newTrigger = $trigger;

			$newTrigger['templateid'] = $trigger['triggerid'];

			$newTrigger['expression'] = str_replace('{'.$triggerTemplate['host'].':', '{'.$chd_host['host'].':', $trigger['expression']);

// check if templated trigger exists
			$childTriggers = self::get(array(
				'filter' => array('templateid' => $newTrigger['triggerid']),
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1,
				'hostids' => $chd_host['hostid']
			));

			if($childTrigger = reset($childTriggers)){
				$childTrigger['expression'] = explode_exp($childTrigger['expression'], 0);

				if((strcmp($childTrigger['expression'], $newTrigger['expression']) != 0) ||
						(strcmp($childTrigger['description'], $newTrigger['description']) != 0))
				{
					$exists = self::exists(array(
						'description' => $newTrigger['description'],
						'expression' => $newTrigger['expression'],
						'hostids' => $chd_host['hostid']
					));
					if($exists){
						self::exception(ZBX_API_ERROR_PARAMETERS,
							sprintf(_('Trigger [%1$s] already exists on [%2$s]'), $newTrigger['description'], $chd_host['host']));
					}
				}
				else if($childTrigger['flags'] != ZBX_FLAG_DISCOVERY_CHILD){
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Trigger with same name but other type exists'));
				}

				$newTrigger['triggerid'] = $childTrigger['triggerid'];
				self::updateReal($newTrigger);
			}
			else{
				$options = array(
					'filter' => array(
						'description' => $newTrigger['description'],
						'flags' => null
					),
					'output' => API_OUTPUT_EXTEND,
					'preservekeys' => 1,
					'nopermissions' => 1,
					'hostids' => $chd_host['hostid']
				);
				$childTriggers = self::get($options);

				$childTrigger = false;
				foreach($childTriggers as $tnum => $tr){
					$tmp_exp = explode_exp($tr['expression'], false);
					if(strcmp($tmp_exp, $newTrigger['expression']) == 0){
						$childTrigger = $tr;
						break;
					}
				}

				if($childTrigger){
					if($childTrigger['templateid'] != 0){
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Trigger [%1$s] already exists on [%2$s]', $childTrigger['description'], $chd_host['host']));
					}
					else if($childTrigger['flags'] != $newTrigger['flags']){
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Trigger with same name but other type exists'));
					}

					$newTrigger['triggerid'] = $childTrigger['triggerid'];
					self::updateReal($newTrigger);
				}
				else{
					self::createReal($newTrigger);
					$newTrigger = reset($newTrigger);
				}
			}
			self::inherit($newTrigger);
		}
	}

	public static function syncTemplates($data){
		try{
			self::BeginTransaction(__METHOD__);

			$data['templateids'] = zbx_toArray($data['templateids']);
			$data['hostids'] = zbx_toArray($data['hostids']);

			$options = array(
				'hostids' => $data['hostids'],
				'editable' => true,
				'preservekeys' => true,
				'templated_hosts' => true,
				'output' => API_OUTPUT_SHORTEN
			);
			$allowedHosts = CHost::get($options);
			foreach($data['hostids'] as $hostid){
				if(!isset($allowedHosts[$hostid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}
			$options = array(
				'templateids' => $data['templateids'],
				'preservekeys' => true,
				'editable' => true,
				'output' => API_OUTPUT_SHORTEN
			);
			$allowedTemplates = CTemplate::get($options);
			foreach($data['templateids'] as $templateid){
				if(!isset($allowedTemplates[$templateid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			$options = array(
				'hostids' => $data['templateids'],
				'preservekeys' => 1,
				'output' => API_OUTPUT_EXTEND,
				'select_dependencies' => true,
			);
			$triggers = self::get($options);

			foreach($triggers as $trigger){
				$trigger['expression'] = explode_exp($trigger['expression'], false);
				self::inherit($trigger, $data['hostids']);
			}

			self::EndTransaction(true, __METHOD__);
			return true;
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}
}

?>
