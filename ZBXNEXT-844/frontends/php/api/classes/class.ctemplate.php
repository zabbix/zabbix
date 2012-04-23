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
 * File containing CTemplate class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Templates
 *
 */
class CTemplate extends CZBXAPI{
/**
 * Get Template data
 *
 * @param array $options
 * @return array|boolean Template data as array or false if error
 */
	public static function get($options = array()) {
		global $USER_DETAILS;

		$result = array();
		$nodeCheck = false;
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('hostid', 'host'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('templates' => 'h.hostid'),
			'from' => array('hosts' => 'hosts h'),
			'where' => array('h.status='.HOST_STATUS_TEMPLATE),
			'group' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'templateids'				=> null,
			'parentTemplateids'			=> null,
			'hostids'					=> null,
			'graphids'					=> null,
			'itemids'					=> null,
			'triggerids'				=> null,
			'with_items'				=> null,
			'with_triggers'				=> null,
			'with_graphs'				=> null,
			'editable' 					=> null,
			'nopermissions'				=> null,

// filter
			'filter'					=> null,
			'search'					=> '',
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,

// OutPut
			'output'					=> API_OUTPUT_REFER,
			'extendoutput'				=> null,
			'select_groups'				=> null,
			'select_hosts'				=> null,
			'select_templates'			=> null,
			'selectParentTemplates'		=> null,
			'select_items'				=> null,
			'select_triggers'			=> null,
			'select_graphs'				=> null,
			'select_applications'		=> null,
			'select_macros'				=> null,
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
			if(!is_null($options['select_templates'])){
				$options['select_templates'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['selectParentTemplates'])){
				$options['selectParentTemplates'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_hosts'])){
				$options['select_hosts'] = API_OUTPUT_EXTEND;
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
			$sql_parts['select']['hosts'] = ' h.'.implode(',h.', $options['output']);
			$options['output'] = API_OUTPUT_REFER;
		}
// editable + PERMISSION CHECK

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where'][] = 'hg.hostid=h.hostid';
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
				$sql_parts['group']['hg'] = 'hg.groupid';
			}

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('hg.groupid', $nodeids);
			}
		}

// templateids
		if(!is_null($options['templateids'])){
			zbx_value2array($options['templateids']);

			$sql_parts['where']['templateid'] = DBcondition('h.hostid', $options['templateids']);

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('h.hostid', $nodeids);
			}
		}

// parentTemplateids
		if(!is_null($options['parentTemplateids'])){
			zbx_value2array($options['parentTemplateids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['parentTemplateid'] = 'ht.templateid as parentTemplateid';
			}

			$sql_parts['from']['hosts_templates'] = 'hosts_templates ht';
			$sql_parts['where'][] = DBcondition('ht.templateid', $options['parentTemplateids']);
			$sql_parts['where']['hht'] = 'h.hostid=ht.hostid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['templateid'] = 'ht.templateid';
			}

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('ht.templateid', $nodeids);
			}
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['linked_hostid'] = 'ht.hostid as linked_hostid';
			}

			$sql_parts['from']['hosts_templates'] = 'hosts_templates ht';
			$sql_parts['where'][] = DBcondition('ht.hostid', $options['hostids']);
			$sql_parts['where']['hht'] = 'h.hostid=ht.templateid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['ht'] = 'ht.hostid';
			}

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('ht.hostid', $nodeids);
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

// node check !!!!
// should last, after all ****IDS checks
		if(!$nodeCheck){
			$nodeCheck = true;
			$sql_parts['where'][] = DBin_node('h.hostid', $nodeids);
		}

// with_items
		if(!is_null($options['with_items'])){
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid )';
		}

// with_triggers
		if(!is_null($options['with_triggers'])){
			$sql_parts['where'][] = 'EXISTS( '.
						' SELECT i.itemid '.
						' FROM items i, functions f, triggers t '.
						' WHERE i.hostid=h.hostid '.
							' AND i.itemid=f.itemid '.
							' AND f.triggerid=t.triggerid)';
		}

// with_graphs
		if(!is_null($options['with_graphs'])){
			$sql_parts['where'][] = 'EXISTS('.
					'SELECT DISTINCT i.itemid '.
					' FROM items i, graphs_items gi '.
					' WHERE i.hostid=h.hostid '.
						' AND i.itemid=gi.itemid)';
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['templates'] = 'h.*';
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

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('hosts h', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('hosts h', $options, $sql_parts);
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
//-------------

		$templateids = array();

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
				' WHERE '.DBin_node('h.hostid', $nodeids).
					$sql_where.
				$sql_group.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($template = DBfetch($res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $template;
				else
					$result = $template['rowscount'];
			}
			else{
				$template['templateid'] = $template['hostid'];
				$templateids[$template['templateid']] = $template['templateid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$template['templateid']] = array('templateid' => $template['templateid']);
				}
				else{
					if(!isset($result[$template['templateid']])) $result[$template['templateid']]= array();

					if(!is_null($options['select_groups']) && !isset($result[$template['templateid']]['groups'])){
						$template['groups'] = array();
					}

					if(!is_null($options['select_templates']) && !isset($result[$template['templateid']]['templates'])){
						$template['templates'] = array();
					}

					if(!is_null($options['select_hosts']) && !isset($result[$template['templateid']]['hosts'])){
						$template['hosts'] = array();
					}
					if(!is_null($options['selectParentTemplates']) && !isset($result[$template['templateid']]['parentTemplates'])){
						$template['parentTemplates'] = array();
					}

					if(!is_null($options['select_items']) && !isset($result[$template['templateid']]['items'])){
						$template['items'] = array();
					}

					if(!is_null($options['select_triggers']) && !isset($result[$template['templateid']]['triggers'])){
						$template['triggers'] = array();
					}

					if(!is_null($options['select_graphs']) && !isset($result[$template['templateid']]['graphs'])){
						$template['graphs'] = array();
					}
					if(!is_null($options['select_applications']) && !isset($result[$template['templateid']]['applications'])){
						$template['applications'] = array();
					}
					if(!is_null($options['select_macros']) && !isset($result[$template['hostid']]['macros'])){
						$template['macros'] = array();
					}

// groupids
					if(isset($template['groupid']) && is_null($options['select_groups'])){
						if(!isset($result[$template['templateid']]['groups']))
							$result[$template['templateid']]['groups'] = array();

						$result[$template['templateid']]['groups'][] = array('groupid' => $template['groupid']);
						unset($template['groupid']);
					}

// hostids
					if(isset($template['linked_hostid']) && is_null($options['select_hosts'])){
						if(!isset($result[$template['templateid']]['hosts']))
							$result[$template['templateid']]['hosts'] = array();

						$result[$template['templateid']]['hosts'][] = array('hostid' => $template['linked_hostid']);
						unset($template['linked_hostid']);
					}
// parentTemplateids
					if(isset($template['parentTemplateid']) && is_null($options['selectParentTemplates'])){
						if(!isset($result[$template['templateid']]['parentTemplates']))
							$result[$template['templateid']]['parentTemplates'] = array();

						$result[$template['templateid']]['parentTemplates'][] = array('templateid' => $template['parentTemplateid']);
						unset($template['parentTemplateid']);
					}

// itemids
					if(isset($template['itemid']) && is_null($options['select_items'])){
						if(!isset($result[$template['templateid']]['items']))
							$result[$template['templateid']]['items'] = array();

						$result[$template['templateid']]['items'][] = array('itemid' => $template['itemid']);
						unset($template['itemid']);
					}

// triggerids
					if(isset($template['triggerid']) && is_null($options['select_triggers'])){
						if(!isset($result[$template['hostid']]['triggers']))
							$result[$template['hostid']]['triggers'] = array();

						$result[$template['hostid']]['triggers'][] = array('triggerid' => $template['triggerid']);
						unset($template['triggerid']);
					}

// graphids
					if(isset($template['graphid']) && is_null($options['select_graphs'])){
						if(!isset($result[$template['templateid']]['graphs'])) $result[$template['templateid']]['graphs'] = array();

						$result[$template['templateid']]['graphs'][] = array('graphid' => $template['graphid']);
						unset($template['graphid']);
					}

					$result[$template['templateid']] += $template;
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
				'hostids' => $templateids,
				'preservekeys' => 1
			);
			$groups = CHostgroup::get($obj_params);
			foreach($groups as $groupid => $group){
				$ghosts = $group['hosts'];
				unset($group['hosts']);
				foreach($ghosts as $hnum => $template){
					$result[$template['hostid']]['groups'][] = $group;
				}
			}
		}

// Adding Templates
		if(!is_null($options['select_templates'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'parentTemplateids' => $templateids,
				'preservekeys' => 1
			);

			if(is_array($options['select_templates']) || str_in_array($options['select_templates'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['select_templates'];
				$templates = CTemplate::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($templates, 'host');
				foreach($templates as $templateid => $template){
					unset($templates[$templateid]['parentTemplates']);

					if(isset($template['parentTemplates']) && is_array($template['parentTemplates'])) {
						$count = array();
						foreach($template['parentTemplates'] as $hnum => $parentTemplate){
							if(!is_null($options['limitSelects'])){
								if(!isset($count[$parentTemplate['templateid']])) $count[$parentTemplate['templateid']] = 0;
								$count[$parentTemplate['hostid']]++;

								if($count[$parentTemplate['templateid']] > $options['limitSelects']) continue;
							}

							$result[$parentTemplate['templateid']]['templates'][] = &$templates[$templateid];
						}
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['select_templates']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$templates = CTemplate::get($obj_params);
				$templates = zbx_toHash($templates, 'hostid');
				foreach($result as $templateid => $template){
					if(isset($templates[$groupid]))
						$result[$templateid]['templates'] = $templates[$templateid]['rowscount'];
					else
						$result[$templateid]['templates'] = 0;
				}
			}
		}

// Adding Hosts
		if(!is_null($options['select_hosts'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'templateids' => $templateids,
				'preservekeys' => 1
			);

			if(is_array($options['select_hosts']) || str_in_array($options['select_hosts'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['select_hosts'];
				$hosts = CHost::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($hosts, 'host');
				foreach($hosts as $hostid => $host){
					unset($hosts[$hostid]['templates']);

					foreach($host['templates'] as $tnum => $template){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$template['templateid']])) $count[$template['templateid']] = 0;
							$count[$template['templateid']]++;

							if($count[$template['templateid']] > $options['limitSelects']) continue;
						}

						$result[$template['templateid']]['hosts'][] = &$hosts[$hostid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['select_hosts']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$hosts = CHost::get($obj_params);
				$hosts = zbx_toHash($hosts, 'hostid');
				foreach($result as $templateid => $template){
					if(isset($hosts[$templateid]))
						$result[$templateid]['hosts'] = $hosts[$templateid]['rowscount'];
					else
						$result[$templateid]['hosts'] = 0;
				}
			}
		}

// Adding parentTemplates
		if(!is_null($options['selectParentTemplates'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'hostids' => $templateids,
				'preservekeys' => 1
			);

			if(is_array($options['selectParentTemplates']) || str_in_array($options['selectParentTemplates'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectParentTemplates'];
				$templates = CTemplate::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($templates, 'host');
				foreach($templates as $templateid => $template){
					unset($templates[$templateid]['hosts']);

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
			else if(API_OUTPUT_COUNT == $options['select_templates']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$templates = CTemplate::get($obj_params);
				$templates = zbx_toHash($templates, 'hostid');
				foreach($result as $templateid => $template){
					if(isset($templates[$groupid]))
						$result[$templateid]['parentTemplates'] = $templates[$templateid]['rowscount'];
					else
						$result[$templateid]['parentTemplates'] = 0;
				}
			}
		}

// Adding Items
		if(!is_null($options['select_items'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'hostids' => $templateids,
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
				foreach($result as $templateid => $template){
					if(isset($items[$templateid]))
						$result[$templateid]['items'] = $items[$templateid]['rowscount'];
					else
						$result[$templateid]['items'] = 0;
				}
			}
		}

// Adding triggers
		if(!is_null($options['select_triggers'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'hostids' => $templateids,
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
				foreach($result as $templateid => $template){
					if(isset($triggers[$templateid]))
						$result[$templateid]['triggers'] = $triggers[$templateid]['rowscount'];
					else
						$result[$templateid]['triggers'] = 0;
				}
			}
		}

// Adding graphs
		if(!is_null($options['select_graphs'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'hostids' => $templateids,
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
				foreach($result as $templateid => $template){
					if(isset($graphs[$templateid]))
						$result[$templateid]['graphs'] = $graphs[$templateid]['rowscount'];
					else
						$result[$templateid]['graphs'] = 0;
				}
			}
		}

// Adding applications
		if(!is_null($options['select_applications'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'hostids' => $templateids,
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
				foreach($result as $templateid => $template){
					if(isset($applications[$templateid]))
						$result[$templateid]['applications'] = $applications[$templateid]['rowscount'];
					else
						$result[$templateid]['applications'] = 0;
				}
			}
		}

// Adding macros
		if(!is_null($options['select_macros']) && str_in_array($options['select_macros'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_macros'],
				'hostids' => $templateids,
				'preservekeys' => 1
			);
			$macros = CUserMacro::get($obj_params);
			foreach($macros as $macroid => $macro){
				$mhosts = $macro['hosts'];
				unset($macro['hosts']);
				foreach($mhosts as $hnum => $host){
					$result[$host['hostid']]['macros'][] = $macro;
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
 * Get Template ID by Template name
 *
 * @param array $template_data
 * @param array $template_data['host']
 * @param array $template_data['templateid']
 * @return string templateid
 */
	public static function getObjects($templateData){
		$options = array(
			'filter' => $templateData,
			'output'=>API_OUTPUT_EXTEND
		);

		if(isset($templateData['node']))
			$options['nodeids'] = getNodeIdByNodeName($templateData['node']);
		else if(isset($templateData['nodeids']))
			$options['nodeids'] = $templateData['nodeids'];

		$result = self::get($options);

	return $result;
	}

	public static function exists($object){
		$keyFields = array(array('templateid', 'host'));

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
 * Add Template
 *
 * @param _array $templates multidimensional array with templates data
 * @param string $templates['host']
 * @param string $templates['port']
 * @param string $templates['status']
 * @param string $templates['useip']
 * @param string $templates['dns']
 * @param string $templates['ip']
 * @param string $templates['proxy_hostid']
 * @param string $templates['useipmi']
 * @param string $templates['ipmi_ip']
 * @param string $templates['ipmi_port']
 * @param string $templates['ipmi_authtype']
 * @param string $templates['ipmi_privilege']
 * @param string $templates['ipmi_username']
 * @param string $templates['ipmi_password']
 * @return boolean
 */
	public static function create($templates){
		$templates = zbx_toArray($templates);
		$templateids = array();

		try{
			self::BeginTransaction(__METHOD__);
// CHECK IF HOSTS HAVE AT LEAST 1 GROUP {{{
			foreach($templates as $tnum => $template){
				if(empty($template['groups'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'No groups for template [ '.$template['host'].' ]');
				}
				$templates[$tnum]['groups'] = zbx_toArray($templates[$tnum]['groups']);

				foreach($templates[$tnum]['groups'] as $gnum => $group){
					$groupids[$group['groupid']] = $group['groupid'];
				}
			}
// }}} CHECK IF HOSTS HAVE AT LEAST 1 GROUP


// PERMISSIONS {{{
			$options = array(
				'groupids' => $groupids,
				'editable' => 1,
				'preservekeys' => 1
			);
			$upd_groups = CHostGroup::get($options);
			foreach($groupids as $gnum => $groupid){
				if(!isset($upd_groups[$groupid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}
// }}} PERMISSIONS

			foreach($templates as $tnum => $template){
	 			$template_db_fields = array(
					'host' => null
				);

				if(!check_db_fields($template_db_fields, $template)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Field "host" is mandatory');
				}

				if(!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/i', $template['host'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect characters used for Template name [ '.$template['host'].' ]');
				}

				if(self::exists(array('host' => $template['host']))){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_TEMPLATE.' [ '.$template['host'].' ] '.S_ALREADY_EXISTS_SMALL);
				}
				if(CHost::exists(array('host' => $template['host']))){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_HOST.' [ '.$template['host'].' ] '.S_ALREADY_EXISTS_SMALL);
				}

				$templateid = get_dbid('hosts', 'hostid');
				$templateids[] = $templateid;

				$sql = 'INSERT INTO hosts (hostid, host, status) VALUES ('.$templateid.','.zbx_dbstr($template['host']).','.HOST_STATUS_TEMPLATE.')';
				if(!DBexecute($sql))
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBError');

				foreach($template['groups'] as $group){
					$hostgroupid = get_dbid('hosts_groups', 'hostgroupid');
					$result = DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES ($hostgroupid, $templateid, {$group['groupid']})");
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
					}
				}

				$template['templateid'] = $templateid;
				$options = array();
				$options['templates'] = $template;
				if(isset($template['templates']) && !is_null($template['templates']))
					$options['templates_link'] = $template['templates'];
				if(isset($template['macros']) && !is_null($template['macros']))
					$options['macros'] = $template['macros'];
				if(isset($template['hosts']) && !is_null($template['hosts']))
					$options['hosts'] = $template['hosts'];

				$result = self::massAdd($options);
				if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS);
			}

			self::EndTransaction(true, __METHOD__);
			return array('templateids' => $templateids);
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
 * Update Template
 *
 * @param array $templates multidimensional array with templates data
 * @return boolean
 */
	public static function update($templates){
		$templates = zbx_toArray($templates);
		$templateids = zbx_objectValues($templates, 'templateid');

		try{
			self::BeginTransaction(__METHOD__);

			$upd_templates = self::get(array(
				'templateids' => $templateids,
				'editable' => 1,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1
			));
			foreach($templates as $tnum => $template){
				if(!isset($upd_templates[$template['templateid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			foreach($templates as $tnum => $template){
				$tpl_tmp = $template;

				$template['templates_link'] = isset($template['templates']) ? $template['templates'] : null;

				unset($template['templates']);
				unset($template['templateid']);
				unset($tpl_tmp['templates']);

				$template['templates'] = array($tpl_tmp);

				$result = self::massUpdate($template);
				if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'Failed to update template');
			}

			self::EndTransaction(true, __METHOD__);
			return array('templateids' => $templateids);
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
 * Delete Template
 *
 * @param array $templateids
 * @param array $templateids['templateids']
 * @return boolean
 */
	public static function delete($templates){
		$templates = zbx_toArray($templates);
		$templateids = array();

		$options = array(
			'templateids' => zbx_objectValues($templates, 'templateid'),
			'editable' => 1,
			'extendoutput' => 1,
			'preservekeys' => 1
		);
		$del_templates = self::get($options);
		foreach($templates as $gnum => $template){
			if(!isset($del_templates[$template['templateid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}
			$templateids[] = $template['templateid'];
		}

		if(!empty($templateids)){
			$result = delete_host($templateids, false);
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ templateids ]');
			$result = false;
		}

		if($result){
			return array('templateids' => $templateids);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}


/**
 * Link Template to Hosts
 *
 * @param array $data
 * @param string $data['templates']
 * @param string $data['hosts']
 * @param string $data['groups']
 * @param string $data['templates_link']
 * @return boolean
 */
	public static function massAdd($data){
		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : null;
		$templateids = is_null($templates) ? array() : zbx_objectValues($templates, 'templateid');

		try{
			self::BeginTransaction(__METHOD__);

			$upd_templates = self::get(array(
				'templateids' => $templateids,
				'editable' => 1,
				'preservekeys' => 1
			));

			foreach($templates as $tnum => $template){
				if(!isset($upd_templates[$template['templateid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			if(isset($data['groups']) && !empty($data['groups'])){
				$options = array('groups' => $data['groups'], 'templates' => $templates);
				$result = CHostGroup::massAdd($options);
				if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'Can\'t link groups');
			}

			if(isset($data['hosts']) && !empty($data['hosts'])){
				$hostids = zbx_objectValues($data['hosts'], 'hostid');
				self::link($templateids, $hostids);
			}

			if(isset($data['templates_link']) && !empty($data['templates_link'])){
				$templates_linkids = zbx_objectValues($data['templates_link'], 'templateid');
				self::link($templates_linkids, $templateids);
			}

			if(isset($data['macros']) && !empty($data['macros'])){
				$options = array('templates' => zbx_toArray($data['templates']), 'macros' => $data['macros']);
				$result = CUserMacro::massAdd($options);
				if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'Can\'t link macros');
			}

			$result = self::EndTransaction(true, __METHOD__);
			return array('templateids' => zbx_objectValues($data['templates'], 'templateid'));
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
 * @return boolean
 */
	public static function massUpdate($data){
		$transaction = false;

		$templates = zbx_toArray($data['templates']);
		$templateids = zbx_objectValues($templates, 'templateid');

		try{
			$options = array(
				'templateids' => $templateids,
				'editable' => 1,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1,
			);
			$upd_templates = self::get($options);

			foreach($templates as $tnum => $template){
				if(!isset($upd_templates[$template['templateid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

// CHECK IF TEMPLATES HAVE AT LEAST 1 GROUP {{{
			if(isset($data['groups']) && empty($data['groups'])){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'No groups for template');
			}
			$data['groups'] = zbx_toArray($data['groups']);
// }}} CHECK IF TEMPLATES HAVE AT LEAST 1 GROUP

			$transaction = self::BeginTransaction(__METHOD__);


// UPDATE TEMPLATES PROPERTIES {{{
			if(isset($data['host'])){
				if(count($templates) > 1){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot mass update template name');
				}

				$cur_template = reset($templates);

				$options = array(
					'filter' => array(
						'host' => $cur_template['host']),
					'output' => API_OUTPUT_SHORTEN,
					'editable' => 1,
					'nopermissions' => 1
				);
				$template_exists = self::get($options);
				$template_exist = reset($template_exists);

				if($template_exist && ($template_exist['templateid'] != $cur_template['templateid'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_TEMPLATE . ' [ ' . $data['host'] . ' ] ' . S_ALREADY_EXISTS_SMALL);
				}

//can't set the same name as existing host
				if(CHost::exists(array('host' => $cur_template['host']))){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_HOST.' [ '.$template['host'].' ] '.S_ALREADY_EXISTS_SMALL);
				}
			}

			if(isset($data['host']) && !preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/i', $data['host'])){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect characters used for Hostname [ ' . $data['host'] . ' ]');
			}

			$sql_set = array();
			if(isset($data['host'])) $sql_set[] = 'host=' . zbx_dbstr($data['host']);

			if(!empty($sql_set)){
				$sql = 'UPDATE hosts SET ' . implode(', ', $sql_set) . ' WHERE ' . DBcondition('hostid', $templateids);
				$result = DBexecute($sql);
			}
// }}} UPDATE TEMPLATES PROPERTIES


// UPDATE HOSTGROUPS LINKAGE {{{
			if(isset($data['groups']) && !is_null($data['groups'])){
				$template_groups = CHostGroup::get(array('hostids' => $templateids));
				$template_groupids = zbx_objectValues($template_groups, 'groupid');
				$new_groupids = zbx_objectValues($data['groups'], 'groupid');

				$groups_to_add = array_diff($new_groupids, $template_groupids);

				if(!empty($groups_to_add)){
					$result = self::massAdd(array(
						'templates' => $templates,
						'groups' => zbx_toObject($groups_to_add, 'groupid')
					));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Can\'t add group');
					}
				}

				$groupids_to_del = array_diff($template_groupids, $new_groupids);
				if(!empty($groupids_to_del)){
					$result = self::massRemove(array(
						'templateids' => $templateids,
						'groupids' => $groupids_to_del
					));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Can\'t remove group');
					}
				}
			}
// }}} UPDATE HOSTGROUPS LINKAGE


			$data['templates_clear'] = isset($data['templates_clear']) ? zbx_toArray($data['templates_clear']) : array();
			$cleared_templateids = array();
			foreach($templateids as $templateid){
				foreach($data['templates_clear'] as $tpl){
					$result = unlink_template($templateid, $tpl['templateid'], false);
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot unlink template [ ' . $tpl['templateid'] . ' ]');
					}
					$cleared_templateids[] = $tpl['templateid'];
				}
			}


// UPDATE TEMPLATE LINKAGE {{{
// firstly need to unlink all things, to correctly check circulars

			if(isset($data['hosts']) && !is_null($data['hosts'])){
				$template_hosts = CHost::get(array('templateids' => $templateids, 'templated_hosts' => 1));
				$template_hostids = zbx_objectValues($template_hosts, 'hostid');
				$new_hostids = zbx_objectValues($data['hosts'], 'hostid');

				$hosts_to_del = array_diff($template_hostids, $new_hostids);
				$hostids_to_del = array_diff($hosts_to_del, $cleared_templateids);

				if(!empty($hostids_to_del)){
					$result = self::massRemove(array(
						'hostids' => $hostids_to_del,
						'templateids' => $templateids
					));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Can\'t unlink template');
					}
				}
			}

			if(isset($data['templates_link']) && !is_null($data['templates_link'])){
				$template_templates = CTemplate::get(array('hostids' => $templateids));
				$template_templateids = zbx_objectValues($template_templates, 'templateid');
				$new_templateids = zbx_objectValues($data['templates_link'], 'templateid');

				$templates_to_del = array_diff($template_templateids, $new_templateids);
				$templateids_to_del = array_diff($templates_to_del, $cleared_templateids);
				if(!empty($templateids_to_del)){
					$result = self::massRemove(array(
						'templateids' => $templateids,
						'templateids_link' => $templateids_to_del
					));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Can\'t unlink template');
					}
				}
			}

			if(isset($data['hosts']) && !is_null($data['hosts'])){

				$hosts_to_add = array_diff($new_hostids, $template_hostids);
				if(!empty($hosts_to_add)){
					$result = self::massAdd(array('templates' => $templates, 'hosts' => $hosts_to_add));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Can\'t link template');
					}
				}
			}

			if(isset($data['templates_link']) && !is_null($data['templates_link'])){
				$templates_to_add = array_diff($new_templateids, $template_templateids);
				if(!empty($templates_to_add)){
					$result = self::massAdd(array('templates' => $templates, 'templates_link' => $templates_to_add));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Can\'t link template');
					}
				}
			}
// }}} UPDATE TEMPLATE LINKAGE


// UPDATE MACROS {{{
			if(isset($data['macros']) && !is_null($data['macros'])){
				$macrosToAdd = zbx_toHash($data['macros'], 'macro');

				$templateMacros = CUserMacro::get(array(
					'hostids' => $templateids,
					'output' => API_OUTPUT_EXTEND
				));
				$templateMacros = zbx_toHash($templateMacros, 'macro');

// Delete
				$macrosToDelete = array();
				foreach($templateMacros as $hmnum => $hmacro){
					if(!isset($macrosToAdd[$hmacro['macro']])){
						$macrosToDelete[] = $hmacro['macro'];
					}
				}

// Update
				$macrosToUpdate = array();
				foreach($macrosToAdd as $nhmnum => $nhmacro){
					if(isset($templateMacros[$nhmacro['macro']])){
						$macrosToUpdate[] = $nhmacro;
						unset($macrosToAdd[$nhmnum]);
					}
				}
//----

				if(!empty($macrosToDelete)){
					$result = self::massRemove(array(
						'templateids' => $templateids,
						'macros' => $macrosToDelete
					));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Can\'t remove macro');
					}
				}

				if(!empty($macrosToUpdate)){
					$result = CUsermacro::massUpdate(array('templates' => $templates, 'macros' => $macrosToUpdate));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot update macro');
					}
				}

				if(!empty($macrosToAdd)){
					$result = self::massAdd(array('templates' => $templates, 'macros' => $macrosToAdd));
					if(!$result){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot add macro');
					}
				}
			}
// }}} UPDATE MACROS

			self::EndTransaction(true, __METHOD__);
			return array('templateids' => $templateids);
		}
		catch(APIException $e){
			if($transaction) self::EndTransaction(false, __METHOD__);

			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * remove Hosts to HostGroups. All Hosts are added to all HostGroups.
 *
 * @param array $data
 * @param array $data['templateids']
 * @param array $data['groupids']
 * @param array $data['hostids']
 * @param array $data['macroids']
 * @return boolean
 */
	public static function massRemove($data){
		$templateids = zbx_toArray($data['templateids']);

		try{
			self::BeginTransaction(__METHOD__);

			$upd_templates = self::get(array(
				'templateids' => $templateids,
				'editable' => 1,
				'preservekeys' => 1
			));

			foreach($templateids as $templateid){
				if(!isset($upd_templates[$templateid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			if(isset($data['groupids'])){
				$options = array(
					'groupids' => zbx_toArray($data['groupids']),
					'templateids' => $templateids
				);
				$result = CHostGroup::massRemove($options);
				if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'Can\'t unlink groups');
			}

			if(isset($data['hostids'])){
				$hostids = zbx_toArray($data['hostids']);
				foreach($hostids as $hostid){
					foreach($templateids as $templateid){
						$result = unlink_template($hostid, $templateid, true);
						if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'Can\'t unlink hosts');
					}
				}
			}

			if(isset($data['templateids_link'])){
				$templateids_link = zbx_toArray($data['templateids_link']);
				foreach($templateids_link as $templateid_link){
					foreach($templateids as $templateid){
						$result = unlink_template($templateid, $templateid_link, true);
						if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'Can\'t unlink templates');
					}
				}
			}

			if(isset($data['macros'])){
				$options = array(
					'templateids' => $templateids,
					'macros' => zbx_toArray($data['macros'])
				);
				$result = CUserMacro::massRemove($options);
				if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'Can\'t remove macros');
			}

			self::EndTransaction(true, __METHOD__);
			return array('templateids' => $templateids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}


	private static function link($templateids, $targetids){
		if(empty($templateids)) return true;

		// check if someone passed duplicate templates in the same query
		$templateIdDuplicates = zbx_arrayFindDuplicates($templateids);
		if(!zbx_empty($templateIdDuplicates)){
			$duplicatesFound = array();
			foreach($templateIdDuplicates as $value => $count){
				$duplicatesFound[] = sprintf(S_TEMPLATE_ID_X_IS_PASSED_N_TIMES, $value, $count);
			}
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				sprintf(S_CANNOT_PASS_DUPLICATE_TEMPLATE_IDS, implode(", ", $duplicatesFound))
			);
		}

		// check if any templates linked to targets have more than one unique item key/application
		foreach($targetids as $targetid){
			$linkedTpls = self::get(array(
				'nopermissions' => 1,
				'output' => API_OUTPUT_SHORTEN,
						'hostids' => $targetid
			));
			$allids = array_merge($templateids, zbx_objectValues($linkedTpls, 'templateid'));

			$sql = 'SELECT key_, count(*) as cnt '.
				' FROM items '.
				' WHERE '.DBcondition('hostid',$allids).
				' GROUP BY key_ '.
				' HAVING count(*) > 1';
			$res = DBselect($sql);
			if($db_cnt = DBfetch($res)){
				self::exception(ZBX_API_ERROR_PARAMETERS,
					S_TEMPLATE_WITH_ITEM_KEY.' ['.htmlspecialchars($db_cnt['key_']).'] '.S_ALREADY_LINKED_TO_HOST_SMALL);
			}

			$sql = 'SELECT name, count(*) as cnt '.
				' FROM applications '.
				' WHERE '.DBcondition('hostid',$allids).
				' GROUP BY name '.
				' HAVING count(*) > 1';
			$res = DBselect($sql);
			if($db_cnt = DBfetch($res)){
				self::exception(ZBX_API_ERROR_PARAMETERS,
					S_TEMPLATE_WITH_APPLICATION.' ['.htmlspecialchars($db_cnt['name']).'] '.S_ALREADY_LINKED_TO_HOST_SMALL);
			}
		}

		// CHECK TEMPLATE TRIGGERS DEPENDENCIES
		foreach($templateids as $tnum => $templateid){
			$triggerids = array();
			$db_triggers = get_triggers_by_hostid($templateid);
			while($trigger = DBfetch($db_triggers)){
				$triggerids[$trigger['triggerid']] = $trigger['triggerid'];
			}

			$sql = 'SELECT DISTINCT h.host '.
					' FROM trigger_depends td, functions f, items i, hosts h '.
					' WHERE ('.DBcondition('td.triggerid_down', $triggerids).' AND f.triggerid=td.triggerid_up) '.
						' AND i.itemid=f.itemid '.
						' AND h.hostid=i.hostid '.
						' AND '.DBcondition('h.hostid', $templateids, true).
						' AND h.status='.HOST_STATUS_TEMPLATE;

			if($db_dephost = DBfetch(DBselect($sql))){
				$options = array(
					'templateids' => $templateid,
					'output'=> API_OUTPUT_EXTEND
				);

				$tmp_tpls = self::get($options);
				$tmp_tpl = reset($tmp_tpls);

				self::exception(ZBX_API_ERROR_PARAMETERS,
					'Trigger in template [ '.$tmp_tpl['host'].' ] has dependency with trigger in template [ '.$db_dephost['host'].' ]');
			}
		}

		$linked = array();
		$sql = 'SELECT hostid, templateid '.
				' FROM hosts_templates '.
				' WHERE '.DBcondition('hostid', $targetids).
					' AND '.DBcondition('templateid', $templateids);
		$linked_db = DBselect($sql);
		while($pair = DBfetch($linked_db)){
			$linked[] = array($pair['hostid'] => $pair['templateid']);
		}

		// add template linkages, if problems rollback later
		foreach($targetids as $targetid){
			foreach($templateids as $tnum => $templateid){
				foreach($linked as $lnum => $link){
					if(isset($link[$targetid]) && ($link[$targetid] == $templateid)) continue 2;
				}

				$values = array(get_dbid('hosts_templates', 'hosttemplateid'), $targetid, $templateid);
				$sql = 'INSERT INTO hosts_templates VALUES ('. implode(', ', $values) .')';
				$result = DBexecute($sql);

				if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'DBError');
			}
		}

		// CHECK CIRCULAR LINKAGE
		// get template linkage graph
		$graph = array();
		$sql = 'SELECT ht.hostid, ht.templateid'.
			' FROM hosts_templates ht, hosts h'.
			' WHERE ht.hostid=h.hostid'.
				' AND h.status='.HOST_STATUS_TEMPLATE;
		$db_graph = DBselect($sql);
		while($branch = DBfetch($db_graph)){
			if(!isset($graph[$branch['hostid']])) $graph[$branch['hostid']] = array();
			$graph[$branch['hostid']][$branch['templateid']] = $branch['templateid'];
		}

		// get points that have more than one parent templates
		$start_points = array();
		$sql = 'SELECT max(ht.hostid) as hostid, ht.templateid'.
			' FROM('.
				' SELECT count(htt.templateid) as ccc, htt.hostid'.
				' FROM hosts_templates htt'.
				' WHERE htt.hostid NOT IN ( SELECT httt.templateid FROM hosts_templates httt )'.
				' GROUP BY htt.hostid'.
				' ) ggg, hosts_templates ht'.
			' WHERE ggg.ccc>1'.
				' AND ht.hostid=ggg.hostid'.
			' GROUP BY ht.templateid';
		$db_start_points = DBselect($sql);
		while($start_point = DBfetch($db_start_points)){
			$start_points[] = $start_point['hostid'];
			$graph[$start_point['hostid']][$start_point['templateid']] = $start_point['templateid'];
		}

		// add to the start points also points which we add current templates
		$start_points = array_merge($start_points, $targetids);
		$start_points = array_unique($start_points);

		foreach($start_points as $spnum => $start){
			$path = array();
			if(!self::checkCircularLink($graph, $start, $path)){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_CIRCULAR_LINK_CANNOT_BE_CREATED);
			}
		}

		foreach($targetids as $targetid){
			$syncTemplateIds = array();
			foreach ($templateids as $templateid) {
				foreach($linked as $lnum => $link){
					if(isset($link[$targetid]) && ($link[$targetid] == $templateid)){
						unset($linked[$lnum]);
						continue 2;
					}
				}

				$syncTemplateIds[] = $templateid;
			}

			// sync templates
			if ($syncTemplateIds) {
				if (!syncHostWithTemplates($targetid, $syncTemplateIds)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, S_CANNOT_SYNC_TEMPLATE);
				}
			}
		}

		return true;
	}

	private static function checkCircularLink(&$graph, $current, &$path){

		if(isset($path[$current])) return false;
		$path[$current] = $current;
		if(!isset($graph[$current])) return true;

		foreach($graph[$current] as $step){
			if(!self::checkCircularLink($graph, $step, $path)) return false;
		}

		return true;
	}
}
?>
