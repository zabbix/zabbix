<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

class CDiscoveryRule extends CItemGeneral{

	public function __construct(){
		parent::__construct();
	}

/**
 * Get DiscoveryRule data
 */
	public function get($options=array()){

		$result = array();
		$user_type = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sort_columns = array('itemid','name','key_','delay','type','status'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('items' => 'i.itemid'),
			'from' => array('items' => 'items i'),
			'where' => array('i.flags='.ZBX_FLAG_DISCOVERY),
			'group' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'				=> null,
			'groupids'				=> null,
			'templateids'			=> null,
			'hostids'				=> null,
			'itemids'				=> null,
			'inherited'				=> null,
			'templated'				=> null,
			'monitored'				=> null,
			'editable'				=> null,
			'nopermissions'			=> null,

// filter
			'filter'				=> null,
			'search'				=> null,
			'searchByAny'			=> null,
			'startSearch'			=> null,
			'excludeSearch'			=> null,
			'searchWildcardsEnabled'=> null,

// OutPut
			'output'				=> API_OUTPUT_REFER,
			'selectHosts'			=> null,
			'selectTriggers'		=> null,
			'selectGraphs'			=> null,
			'selectPrototypes'		=> null,
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
			unset($sql_parts['select']['items']);

			$dbTable = DB::getSchema('items');
			$sql_parts['select']['itemid'] = 'i.itemid';
			foreach($options['output'] as $key => $field){
				if(isset($dbTable['fields'][$field]))
					$sql_parts['select'][$field] = 'i.'.$field;
			}

			$options['output'] = API_OUTPUT_CUSTOM;
		}

// editable + PERMISSION CHECK
		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
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
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

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

			if($options['output'] != API_OUTPUT_EXTEND){
				$sql_parts['select']['hostid'] = 'i.hostid';
			}

			$sql_parts['where']['hostid'] = DBcondition('i.hostid', $options['hostids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['i'] = 'i.hostid';
			}
		}

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);

			$sql_parts['where']['itemid'] = DBcondition('i.itemid', $options['itemids']);
		}

// inherited
		if(!is_null($options['inherited'])){
			if($options['inherited'])
				$sql_parts['where'][] = 'i.templateid IS NOT NULL';
			else
				$sql_parts['where'][] = 'i.templateid IS NULL';
		}

// templated
		if(!is_null($options['templated'])){
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';

			if($options['templated'])
				$sql_parts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			else
				$sql_parts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
		}

// monitored
		if(!is_null($options['monitored'])){
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';

			if($options['monitored']){
				$sql_parts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
				$sql_parts['where'][] = 'i.status='.ITEM_STATUS_ACTIVE;
			}
			else{
				$sql_parts['where'][] = '(h.status<>'.HOST_STATUS_MONITORED.' OR i.status<>'.ITEM_STATUS_ACTIVE.')';
			}
		}


// search
		if(is_array($options['search'])){
			zbx_db_search('items i', $options, $sql_parts);
		}

// --- FILTER ---
		if(is_array($options['filter'])){
			zbx_db_filter('items i', $options, $sql_parts);

			if(isset($options['filter']['host'])){
				zbx_value2array($options['filter']['host']);

				$sql_parts['from']['hosts'] = 'hosts h';
				$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
				$sql_parts['where']['h'] = DBcondition('h.host', $options['filter']['host']);
			}
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['items'] = 'i.*';
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

			if(!str_in_array('i.'.$options['sortfield'], $sql_parts['select'])
					&& !str_in_array('i.*', $sql_parts['select'])){
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

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('i.itemid', $nodeids).
					$sql_where.
				$sql_group.
				$sql_order;
//SDI($sql);
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

					if(!is_null($options['selectHosts']) && !isset($result[$item['itemid']]['hosts'])){
						$result[$item['itemid']]['hosts'] = array();
					}
					if(!is_null($options['selectTriggers']) && !isset($result[$item['itemid']]['triggers'])){
						$result[$item['itemid']]['triggers'] = array();
					}
					if(!is_null($options['selectGraphs']) && !isset($result[$item['itemid']]['graphs'])){
						$result[$item['itemid']]['graphs'] = array();
					}
					if(!is_null($options['selectPrototypes']) && !isset($result[$item['itemid']]['prototypes'])){
						$result[$item['itemid']]['prototypes'] = array();
					}

// hostids
					if(isset($item['hostid']) && is_null($options['selectHosts'])){
						if(!isset($result[$item['itemid']]['hosts'])) $result[$item['itemid']]['hosts'] = array();

						$result[$item['itemid']]['hosts'][] = array('hostid' => $item['hostid']);
					}

					$result[$item['itemid']] += $item;
				}
			}
		}

COpt::memoryPick();
		if(!is_null($options['countOutput'])){
			return $result;
		}

// Adding Objects
// Adding hosts
		if(!is_null($options['selectHosts'])){
			if(is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselects_allowed_outputs)){
				$obj_params = array(
					'nodeids' => $nodeids,
					'itemids' => $itemids,
					'templated_hosts' => 1,
					'output' => $options['selectHosts'],
					'nopermissions' => 1,
					'preservekeys' => 1
				);
				$hosts = API::Host()->get($obj_params);

				foreach($hosts as $hostid => $host){
					$hitems = $host['items'];
					unset($host['items']);
					foreach($hitems as $inum => $item){
						$result[$item['itemid']]['hosts'][] = $host;
					}
				}

				$templates = API::Template()->get($obj_params);
				foreach($templates as $templateid => $template){
					$titems = $template['items'];
					unset($template['items']);
					foreach($titems as $inum => $item){
						$result[$item['itemid']]['hosts'][] = $template;
					}
				}
			}
		}

// Adding triggers
		if(!is_null($options['selectTriggers'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'discoveryids' => $itemids,
				'preservekeys' => 1,
				'filter' => array('flags' => ZBX_FLAG_DISCOVERY_CHILD),
			);

			if(in_array($options['selectTriggers'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectTriggers'];
				$triggers = API::Trigger()->get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($triggers, 'name');
				foreach($triggers as $triggerid => $trigger){
					unset($triggers[$triggerid]['items']);
					$count = array();
					foreach($trigger['items'] as $item){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$item['itemid']])) $count[$item['itemid']] = 0;
							$count[$item['itemid']]++;

							if($count[$item['itemid']] > $options['limitSelects']) continue;
						}

						$result[$item['itemid']]['triggers'][] = &$triggers[$triggerid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectTriggers']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$triggers = API::Trigger()->get($obj_params);

				$triggers = zbx_toHash($triggers, 'parent_itemid');
				foreach($result as $itemid => $item){
					if(isset($triggers[$itemid]))
						$result[$itemid]['triggers'] = $triggers[$itemid]['rowscount'];
					else
						$result[$itemid]['triggers'] = 0;
				}
			}
		}

// Adding graphs
		if(!is_null($options['selectGraphs'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'discoveryids' => $itemids,
				'preservekeys' => 1,
				'filter' => array('flags' => ZBX_FLAG_DISCOVERY_CHILD),
			);

			if(in_array($options['selectGraphs'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectGraphs'];
				$graphs = API::Graph()->get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($graphs, 'name');
				foreach($graphs as $graphid => $graph){
					unset($graphs[$graphid]['discoveries']);
					$count = array();
					foreach($graph['discoveries'] as $item){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$item['itemid']])) $count[$item['itemid']] = 0;
							$count[$item['itemid']]++;

							if($count[$item['itemid']] > $options['limitSelects']) continue;
						}

						$result[$item['itemid']]['graphs'][] = &$graphs[$graphid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectGraphs']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$graphs = API::Graph()->get($obj_params);

				$graphs = zbx_toHash($graphs, 'parent_itemid');
				foreach($result as $itemid => $item){
					if(isset($graphs[$itemid]))
						$result[$itemid]['graphs'] = $graphs[$itemid]['rowscount'];
					else
						$result[$itemid]['graphs'] = 0;
				}
			}
		}

// Adding prototypes
		if(!is_null($options['selectPrototypes'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'discoveryids' => $itemids,
				'nopermissions' => 1,
				'preservekeys' => 1,
			);

			if(is_array($options['selectPrototypes']) || str_in_array($options['selectPrototypes'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectPrototypes'];
				$prototypes = API::Item()->get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($prototypes, 'name');
				foreach($prototypes as $itemid => $subrule){
					unset($prototypes[$itemid]['discoveries']);
					$count = array();
					foreach($subrule['discoveries'] as $discovery){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$discovery['itemid']])) $count[$discovery['itemid']] = 0;
							$count[$discovery['itemid']]++;

							if($count[$discovery['itemid']] > $options['limitSelects']) continue;
						}

						$result[$discovery['itemid']]['prototypes'][] = &$prototypes[$itemid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectPrototypes']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$prototypes = API::Item()->get($obj_params);

				$prototypes = zbx_toHash($prototypes, 'parent_itemid');
				foreach($result as $itemid => $item){
					if(isset($prototypes[$itemid]))
						$result[$itemid]['prototypes'] = $prototypes[$itemid]['rowscount'];
					else
						$result[$itemid]['prototypes'] = 0;
				}
			}
		}

COpt::memoryPick();
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	public function exists($object){
		$options = array(
			'filter' => array('key_' => $object['key_']),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);

		if(isset($object['hostid'])) $options['hostids'] = $object['hostid'];
		if(isset($object['host'])) $options['filter']['host'] = $object['host'];

		if(isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if(isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = $this->get($options);

		return !empty($objs);
	}

	protected function checkInput(&$items, $update=false){
		foreach($items as $inum => $item){
			$items[$inum]['flags'] = ZBX_FLAG_DISCOVERY;
		}

		parent::checkInput($items, $update);
	}

/**
 * Add DIscoveryRule
 *
 * @param array $items
 * @return array|boolean
 */
	public function create($items){
		$items = zbx_toArray($items);

			$this->checkInput($items);

			$this->createReal($items);

			$this->inherit($items);

			return array('itemids' => zbx_objectValues($items, 'itemid'));
	}

	protected function createReal(&$items){
		foreach($items as $key => $item){
			$itemsExists = API::Item()->get(array(
				'output' => API_OUTPUT_SHORTEN,
				'filter' => array(
					'hostid' => $item['hostid'],
					'key_' => $item['key_']
				),
				'nopermissions' => 1
			));
			foreach($itemsExists as $inum => $itemExists){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Host with item ['.$item['key_'].'] already exists');
			}
		}

		$itemids = DB::insert('items', $items);

		$itemApplications = array();
		foreach($items as $key => $item){
			$items[$key]['itemid'] = $itemids[$key];

			if(!isset($item['applications'])) continue;

			foreach($item['applications'] as $anum => $appid){
				if($appid == 0) continue;

				$itemApplications[] = array(
					'applicationid' => $appid,
					'itemid' => $items[$key]['itemid']
				);
			}
		}

		if(!empty($itemApplications)){
			DB::insert('items_applications', $itemApplications);
		}

// TODO: REMOVE info
		$itemHosts = $this->get(array(
			'itemids' => $itemids,
			'output' => array('key_'),
			'selectHosts' => array('host'),
			'nopermissions' => 1
		));
		foreach($itemHosts as $item){
			$host = reset($item['hosts']);
			info(S_DISCOVERY_RULE.' ['.$host['host'].':'.$item['key_'].'] '.S_CREATED_SMALL);
		}
	}

	protected function updateReal($items){
		$items = zbx_toArray($items);

		$data = array();
		foreach($items as $inum => $item){
			$itemsExists = API::Item()->get(array(
				'output' => API_OUTPUT_SHORTEN,
				'filter' => array(
					'hostid' => $item['hostid'],
					'key_' => $item['key_']
				),
				'nopermissions' => 1
			));
			foreach($itemsExists as $inum => $itemExists){
				if(bccomp($itemExists['itemid'], $item['itemid']) != 0){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Host with item [ '.$item['key_'].' ] already exists');
				}
			}

			$data[] = array('values' => $item, 'where'=> array('itemid'=>$item['itemid']));
		}
		$result = DB::update('items', $data);
		if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

		$itemids = array();
		$itemApplications = array();
		foreach($items as $key => $item){
			$itemids[] = $item['itemid'];

			if(!isset($item['applications'])) continue;
			foreach($item['applications'] as $anum => $appid){
				$itemApplications[] = array(
					'applicationid' => $appid,
					'itemid' => $item['itemid']
				);
			}
		}

		if(!empty($itemids)){
			DB::delete('items_applications', array('itemid'=>$itemids));
			DB::insert('items_applications', $itemApplications);
		}

// TODO: REMOVE info
		$itemHosts = $this->get(array(
			'itemids' => $itemids,
			'output' => array('key_'),
			'selectHosts' => array('host'),
			'nopermissions' => 1,
		));
		foreach($itemHosts as $item){
			$host = reset($item['hosts']);
			info(S_DISCOVERY_RULE.' ['.$host['host'].':'.$item['key_'].'] '.S_UPDATED_SMALL);
		}

	}

/**
 * Update DIscoveryRule
 *
 * @param array $items
 * @return boolean
 */
	public function update($items){
		$items = zbx_toArray($items);

			$this->checkInput($items, true);

			$this->updateReal($items);

			$this->inherit($items);

			return array('itemids' => zbx_objectValues($items, 'itemid'));
	}

/**
 * Delete DIscoveryRules
 *
 * @param array $ruleids
 * @return
 */
	public function delete($ruleids, $nopermissions=false){

			if(empty($ruleids)) self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter'));

			$ruleids = zbx_toHash($ruleids);

			$options = array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $ruleids,
				'editable' => true,
				'preservekeys' => true,
			);
			$del_rules = $this->get($options);

// TODO: remove $nopermissions hack
			if(!$nopermissions){
				foreach($ruleids as $ruleid){
					if(!isset($del_rules[$ruleid])){
						self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
					}
					if($del_rules[$ruleid]['templateid'] != 0){
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot delete templated items'));
					}
				}
			}

// get child discovery rules
			$parent_itemids = $ruleids;
			$child_ruleids = array();
			do{
				$db_items = DBselect('SELECT itemid FROM items WHERE ' . DBcondition('templateid', $parent_itemids));
				$parent_itemids = array();
				while($db_item = DBfetch($db_items)){
					$parent_itemids[$db_item['itemid']] = $db_item['itemid'];
					$child_ruleids[$db_item['itemid']] = $db_item['itemid'];
				}
			}while(!empty($parent_itemids));

			$options = array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $child_ruleids,
				'nopermissions' => true,
				'preservekeys' => true,
			);
			$del_rules_childs = $this->get($options);

			$del_rules = array_merge($del_rules, $del_rules_childs);
			$ruleids = array_merge($ruleids, $child_ruleids);


			$iprototypeids = array();
			$sql = 'SELECT i.itemid'.
			 		' FROM item_discovery id, items i'.
			 		' WHERE i.itemid=id.itemid'.
			 			' AND '.DBcondition('parent_itemid', $ruleids);
			$db_items = DBselect($sql);
			while($item = DBfetch($db_items)){
				$iprototypeids[$item['itemid']] = $item['itemid'];
			}
			if(!empty($iprototypeids)){
				if(!API::Itemprototype()->delete($iprototypeids, true))
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete discovery rule'));
			}

			DB::delete('items', array('itemid'=>$ruleids));


// HOUSEKEEPER {{{
			$item_data_tables = array(
				'trends',
				'trends_uint',
				'history_text',
				'history_log',
				'history_uint',
				'history_str',
				'history',
			);
			$insert = array();
			foreach($ruleids as $id => $ruleid){
				foreach($item_data_tables as $table){
					$insert[] = array(
						'tablename' => $table,
						'field' => 'itemid',
						'value' => $ruleid,
					);
				}
			}
			DB::insert('housekeeper', $insert);
// }}} HOUSEKEEPER

// TODO: remove info from API
			foreach($del_rules as $item){
				info(_s('Discovery rule [%1$s:%2$s] deleted.', $item['name'], $item['key_']));
			}

			return array('ruleids' => $ruleids);
	}

	public function syncTemplates($data){
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		if(!API::Host()->isWritable($data['hostids'])){
			self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
		}
		if(!API::Template()->isReadable($data['templateids'])){
			self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
		}

		$selectFields = array();
		foreach($this->fieldRules as $key => $rules){
			if(!isset($rules['system']) && !isset($rules['host'])){
				$selectFields[] = $key;
			}
		}

		$options = array(
			'hostids' => $data['templateids'],
			'preservekeys' => true,
			'output' => $selectFields,
		);
		$items = $this->get($options);

		$this->inherit($items, $data['hostids']);

		return true;
	}

	/**
	 * Inherit discovery rules to child hosts/templates.
	 * @param array $items
	 * @param null|array $hostids array of hostids which discovery rules should be inherited to
	 * @return bool
	 */
	protected function inherit(array $items, $hostids=null) {
		if (empty($items)) {
			return true;
		}

		$chdHosts = API::Host()->get(array(
			'templateids' => zbx_objectValues($items, 'hostid'),
			'hostids' => $hostids,
			'output' => array('hostid', 'host', 'status'),
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'nopermissions' => true,
			'templated_hosts' => true
		));
		if (empty($chdHosts)) {
			return true;
		}

		$insertItems = array();
		$updateItems = array();
		$inheritedItems = array();
		foreach ($chdHosts as $hostid => $host) {
			$interfaceids = array();
			foreach ($host['interfaces'] as $interface) {
				if ($interface['main'] == 1) {
					$interfaceids[$interface['type']] = $interface['interfaceid'];
				}
			}

			$templateids = zbx_toHash($host['templates'], 'templateid');

// skip items not from parent templates of current host
			$parentItems = array();
			foreach ($items as $itemid => $item) {
				if (isset($templateids[$item['hostid']])) {
					$parentItems[$itemid] = $item;
				}
			}

// check existing items to decide insert or update
			$exItems = $this->get(array(
				'output' => array('itemid', 'key_', 'type', 'flags', 'templateid'),
				'hostids' => $hostid,
				'filter' => array('flags' => null),
				'preservekeys' => true,
				'nopermissions' => true
			));
			$exItemsKeys = zbx_toHash($exItems, 'key_');
			$exItemsTpl = zbx_toHash($exItems, 'templateid');

			foreach ($parentItems as $item) {
				$exItem = null;

// update by tempalteid
				if (isset($exItemsTpl[$item['itemid']])) {
					$exItem = $exItemsTpl[$item['itemid']];
				}

// update by key
				if (isset($exItemsKeys[$item['key_']])) {
					$exItem = $exItemsKeys[$item['key_']];

					if ($exItem['flags'] != ZBX_FLAG_DISCOVERY) {
						$this->errorInheritFlags($exItem['flags'], $exItem['key_'], $host['host']);
					}
					elseif ($exItem['templateid'] > 0 && bccomp($exItem['templateid'], $item['itemid']) != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Discovery rule "%1$s:%2$s" already exists, inherited from another template.', $host['host'], $item['key_']));
					}
				}

				if ($host['status'] == HOST_STATUS_TEMPLATE || !isset($item['type'])) {
					unset($item['interfaceid']);
				}
				elseif (isset($item['type']) && $item['type'] != $exItem['type']) {
					if ($type = $this->itemTypeInterface($item['type'])) {
						if (!isset($interfaceids[$type])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot find host interface on host "%1$s" for discovery rule key "%2$s".', $host['host'], $item['key_']));
						}

						$item['interfaceid'] = $interfaceids[$type];
					}
					else {
						$item['interfaceid'] = 0;
					}
				}

// coping item
				$newItem = $item;
				$newItem['hostid'] = $host['hostid'];
				$newItem['templateid'] = $item['itemid'];

// setting item application
				if (isset($item['applications'])) {
					$newItem['applications'] = get_same_applications_for_host($item['applications'], $host['hostid']);
				}
//--

				if ($exItem) {
					$newItem['itemid'] = $exItem['itemid'];
					$inheritedItems[] = $newItem;

					$updateItems[] = $newItem;
				}
				else {
					$inheritedItems[] = $newItem;
					$newItem['flags'] = ZBX_FLAG_DISCOVERY;
					$insertItems[] = $newItem;
				}
			}
		}

		$this->createReal($insertItems);
		$this->updateReal($updateItems);

		$this->inherit($inheritedItems);
	}
}
?>
