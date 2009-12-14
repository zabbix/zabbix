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
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @static
 * @param array $options
 * @return array|boolean Template data as array or false if error
 */
	public static function get($options = array()) {
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('hostid', 'host'); // allowed columns for sorting

		$sql_parts = array(
			'select' => array('templates' => 'h.hostid'),
			'from' => array('hosts h'),
			'where' => array('h.status='.HOST_STATUS_TEMPLATE),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'graphids'					=> null,
			'itemids'					=> null,
			'with_items'				=> null,
			'with_triggers'				=> null,
			'with_graphs'				=> null,
			'editable' 					=> null,
			'nopermissions'				=> null,
// filter
			'pattern'					=> '',

// OutPut
			'extendoutput'				=> null,
			'select_groups'				=> null,
			'select_hosts'				=> null,
			'select_templates'			=> null,
			'select_items'				=> null,
			'select_triggers'			=> null,
			'select_graphs'				=> null,
			'select_applications'		=> null,
			'select_macros'				=> null,
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
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['r'] = 'rights r';
			$sql_parts['from']['ug'] = 'users_groups ug';
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
		$nodeids = $options['nodeids'] ? $options['nodeids'] : get_current_nodeid(false);

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);

			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where']['hgh'] = 'hg.hostid=h.hostid';
		}

// templateids
		if(!is_null($options['templateids'])){
			zbx_value2array($options['templateids']);

			$sql_parts['where'][] = DBcondition('h.hostid', $options['templateids']);
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if($options['extendoutput']){
				$sql_parts['select']['linked_hostid'] = 'ht.hostid as linked_hostid';
			}

			$sql_parts['from']['ht'] = 'hosts_templates ht';
			$sql_parts['where'][] = DBcondition('ht.hostid', $options['hostids']);
			$sql_parts['where']['hht'] = 'h.hostid=ht.templateid';
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

// with_items
		if(!is_null($options['with_items'])){
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid )';
		}

// with_triggers
		if(!is_null($options['with_triggers'])){
			$sql_parts['where'][] = 'EXISTS(
					SELECT i.itemid
					FROM items i, functions f, triggers t
					WHERE i.hostid=h.hostid
						AND i.itemid=f.itemid
						AND f.triggerid=t.triggerid)';
		}

// with_graphs
		if(!is_null($options['with_graphs'])){
			$sql_parts['where'][] = 'EXISTS(
					SELECT DISTINCT i.itemid
					FROM items i, graphs_items gi
					WHERE i.hostid=h.hostid
						AND i.itemid=gi.itemid)';
		}

// extendoutput
		if(!is_null($options['extendoutput'])){
			$sql_parts['select']['templates'] = 'h.*';
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select']['templates'] = 'count(h.hostid) as rowscount';
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(h.host) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
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
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',', $sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',', $sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ', $sql_parts['where']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',', $sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('h.hostid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);

		while($template = DBfetch($res)){
			if($options['count'])
				$result = $template;
			else{
				$template['templateid'] = $template['hostid'];
				$templateids[$template['templateid']] = $template['templateid'];

				if(is_null($options['extendoutput'])){
					$result[$template['templateid']] = array('templateid' => $template['templateid']);
				}
				else{
					if(!isset($result[$template['templateid']])) $result[$template['templateid']]= array();

					if($options['select_groups'] && !isset($result[$template['templateid']]['groups'])){
						$template['groups'] = array();
					}

					if($options['select_templates'] && !isset($result[$template['templateid']]['templates'])){
						$template['templates'] = array();
					}

					if($options['select_hosts'] && !isset($result[$template['templateid']]['hosts'])){
						$template['hosts'] = array();
					}

					if($options['select_items'] && !isset($result[$template['templateid']]['items'])){
						$template['items'] = array();
					}

					if($options['select_triggers'] && !isset($result[$template['templateid']]['triggers'])){
						$template['triggers'] = array();
					}

					if($options['select_graphs'] && !isset($result[$template['templateid']]['graphs'])){
						$template['graphs'] = array();
					}
					if($options['select_applications'] && !isset($result[$template['templateid']]['applications'])){
						$template['applications'] = array();
					}
					if($options['select_macros'] && !isset($result[$host['hostid']]['macros'])){
						$template['macros'] = array();
					}

// groupids
					if(isset($template['groupid'])){
						if(!isset($result[$template['templateid']]['groups']))
							$result[$template['templateid']]['groups'] = array();

						$result[$template['templateid']]['groups'][$template['groupid']] = array('groupid' => $template['groupid']);
						unset($template['groupid']);
					}

// hostids
					if(isset($template['linked_hostid'])){
						if(!isset($result[$template['templateid']]['hosts']))
							$result[$template['templateid']]['hosts'] = array();

						$result[$template['templateid']]['hosts'][$template['linked_hostid']] = array('hostid' => $template['linked_hostid']);
						unset($template['linked_hostid']);
					}

// itemids
					if(isset($template['itemid'])){
						if(!isset($result[$template['templateid']]['items']))
							$result[$template['templateid']]['items'] = array();

						$result[$template['templateid']]['items'][$template['itemid']] = array('itemid' => $template['itemid']);
						unset($template['itemid']);
					}

// graphids
					if(isset($template['graphid'])){
						if(!isset($result[$template['templateid']]['graphs'])) $result[$template['templateid']]['graphs'] = array();

						$result[$template['templateid']]['graphs'][$template['graphid']] = array('graphid' => $template['graphid']);
						unset($template['graphid']);
					}

					$result[$template['templateid']] += $template;
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
			$obj_params = array('extendoutput' => 1, 'hostids' => $templateids, 'preservekeys' => 1);
			$groups = CHostgroup::get($obj_params);
			foreach($groups as $groupid => $group){
				foreach($group['hosts'] as $hnum => $templateid){
					$result[$templateid]['groups'][$groupid] = $group;
				}
			}
		}

// Adding Templates
		if($options['select_templates']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $templateids, 'preservekeys' => 1);
			$templates = self::get($obj_params);
			foreach($templates as $templateid => $template){
				foreach($template['hosts'] as $hnum => $host){
					$result[$host['hostid']]['templates'][$templateid] = $template;
				}
			}
		}

// Adding Hosts
		if($options['select_hosts']){
			$obj_params = array('extendoutput' => 1, 'templateids' => $templateids, 'templated_hosts' => 1, 'preservekeys' => 1);
			$hosts = CHost::get($obj_params);
			foreach($hosts as $hostid => $host){
				foreach($host['templates'] as $tnum => $template){
					$result[$template['templateid']]['hosts'][$hostid] = $host;
				}
			}
		}

// Adding Items
		if($options['select_items']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $templateids, 'nopermissions' => 1, 'preservekeys' => 1);
			$items = CItem::get($obj_params);
			foreach($items as $itemid => $item){
				foreach($item['hosts'] as $hnum => $host){
					$result[$host['hostid']]['items'][$itemid] = $item;
				}
			}
		}

// Adding triggers
		if($options['select_triggers']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $templateids, 'preservekeys' => 1);
			$triggers = CTrigger::get($obj_params);
			foreach($triggers as $triggerid => $trigger){
				foreach($trigger['hosts'] as $hnum => $host){
					$result[$host['hostid']]['triggers'][$triggerid] = $trigger;
				}
			}
		}

// Adding graphs
		if($options['select_graphs']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $templateids, 'preservekeys' => 1);
			$graphs = CGraph::get($obj_params);
			foreach($graphs as $graphid => $graph){
				foreach($graph['hosts'] as $hnum => $host){
					$result[$host['hostid']]['graphs'][$graphid] = $graph;
				}
			}
		}

// Adding applications
		if($options['select_applications']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $templateids, 'preservekeys' => 1);
			$applications = Capplication::get($obj_params);
			foreach($applications as $applicationid => $application){
				foreach($application['hosts'] as $hnum => $host){
					$result[$host['hostid']]['applications'][$applicationid] = $application;
				}
			}
		}

// Adding macros
		if($options['select_macros']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'preservekeys' => 1);
			$macros = CUserMacro::get($obj_params);
			foreach($macros as $macroid => $macro){
				foreach($macro['hosts'] as $hnum => $host){
					$result[$host['hostid']]['macros'][$macroid] = $macro;
				}
			}
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
//			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

/**
 * Get Template ID by Template name
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $template_data
 * @param array $template_data['host']
 * @return string templateid
 */
	public static function getObjects($template_data){
		$result = array();
		$templateid = array();

		$sql = 'SELECT hostid '.
				' FROM hosts '.
				' WHERE host='.zbx_dbstr($template_data['template']).
					' AND status='.HOST_STATUS_TEMPLATE.
					' AND '.DBin_node('hostid', false);
		$res = DBselect($sql);
		while($template = DBfetch($res)){
			$templateids[$template['hostid']] = $template['hostid'];
		}

		if(!empty($templateids))
			$result = self::get(array('templateids'=>$templateids, 'extendoutput'=>1));

	return $result;
	}

/**
 * Add Template
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
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
		$errors = array();
		$templates = zbx_toArray($templates);
		$templateids = array();
		$tpls = null;
		$newgroup = '';
		$status = HOST_STATUS_TEMPLATE;

		$result = false;

		foreach($templates as $tnum => $template){
			if(empty($template['groups'])){
				self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'No groups for template [ '.$template['host'].' ]');
				return false;
			}

			$templates[$tnum]['groups'] = zbx_toArray($templates[$tnum]['groups']);

			foreach($templates[$tnum]['groups'] as $gnum => $group){
				$groupids[$group['groupid']] = $group['groupid'];
			}
		}

		$upd_groups = CHostGroup::get(array(
			'groupids' => $groupids,
			'editable' => 1,
			'preservekeys' => 1));
		foreach($groupids as $gnum => $groupid){
			if(!isset($upd_groups[$groupid])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'You do not have enough rights for operation');
				return false;
			}
		}

		self::BeginTransaction(__METHOD__);
		foreach($templates as $tnum => $template){
			$template['groupids'] = zbx_objectValues($template['groups'], 'groupid');

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
				'groupids' => array(),
				'templates' => array(),
			);

			if(!check_db_fields($host_db_fields, $template)){
				$result = false;
				break;
			}
 
			$result = add_host($template['host'], $template['port'], $status, $template['useip'], $template['dns'], $template['ip'],
				$template['proxy_hostid'], null, $template['useipmi'], $template['ipmi_ip'], $template['ipmi_port'], $template['ipmi_authtype'],
				$template['ipmi_privilege'], $template['ipmi_username'], $template['ipmi_password'], $newgroup, $template['groupids']);

			if(!$result) break;

			$templateids[] = $result;
			
// TODO			
			$template['hostid'] = $result;
			$options['hosts'] = $template;
			if(isset($template['templates']) && !is_null($template['templates']))
				$options['templates'] = $template['templates'];

			self::massAdd($options);
			
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$new_templates = self::get(array('templateids'=>$templateids, 'extendoutput'=>1, 'nopermissions'=>1));
			return $new_templates;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

/**
 * Update Template
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $templates multidimensional array with templates data
 * @return boolean
 */
	public static function update($templates){

		$errors = array();
		$result = true;
		$templates = zbx_toArray($templates);
		$templateids = zbx_objectValues($templates, 'templateid');

		$upd_templates = self::get(array(
			'templateids' => $templateids, 
			'editable' => 1, 
			'extendoutput' => 1, 
			'preservekeys' => 1
		));
		foreach($templates as $tnum => $template){
// PERMISSIONS {{{
			if(!isset($upd_templates[$template['templateid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}
// }}} PERMISSIONS

// CHECK IF HOSTS HAVE AT LEAST 1 GROUP {{{
			if(isset($template['groups']) && empty($template['groups'])){
				self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'No groups for template [ '.$template['host'].' ]');
				return false;
			}
			$templates[$tnum]['groups'] = zbx_toArray($templates[$tnum]['groups']);			
// }}} CHECK IF HOSTS HAVE AT LEAST 1 GROUP

// CHECK CIRCULAR LINKS {{{
			if(!self::checkCircularLink($template['templateid'], zbx_objectValues($template['templates'], 'templateid'))){
				self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Circular link can not be created');
				return false;
			}
// }}} CHECK CIRCULAR LINKS
		}

		
		// if(!check_templates_trigger_dependencies($templateids)){
			// self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Wrong template trigger dependencies');
			// return false;
		// }
		

		self::BeginTransaction(__METHOD__);
		foreach($templates as $tnum => $template){

			if(!check_db_fields($upd_templates[$template['templateid']], $template)){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Wrong fields for host [ '.$template['host'].' ]');
				break;
			}

			$template_exists = self::getObjects(array('template' => $template['host']));
			$template_exists = reset($template_exists);
			if(!empty($template_exists) && ($template_exists['hostid'] != $template_exists['hostid'])){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Template [ '.$template['host'].' ] already exists');
				break;
			}
			if(!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/i', $template['host'])){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Incorrect characters used for Hostname [ '.$template['host'].' ]');
				break;
			}

			$sql = 'UPDATE hosts SET host='.zbx_dbstr($template['host']).
				' WHERE hostid='.$template['hostid'];
			$result = DBexecute($sql);

		
			if(isset($template['groups']) && !is_null($template['groups'])){
				$host_groups = CHostGroup::get(array('hostids' => $template['hostid']));
				$host_groupids = zbx_objectValues($host_groups, 'groupid');
				$new_groupids = zbx_objectValues($template['groups'], 'groupid');
				
				$groups_to_add = array_diff($new_groupids, $host_groupids);
				if(!empty($groups_to_add)){
					$result &= self::massAdd(array('templates' => $template, 'groups' => $groups_to_add));
				}
				
				$groups_to_del = array_diff($host_groupids, $new_groupids);
				if(!empty($groups_to_del)){
					$result &= self::massRemove(array('templates' => $template, 'groups' => $groups_to_del));
				}
			}
			
			$template['templates_clear'] = isset($template['templates_clear']) ? $template['templates_clear'] : array();
			if(isset($template['templates_clear']) && !is_null($template['templates_clear'])){
				foreach($template['templates_clear'] as $tpl){
					$result = unlink_template($template['hostid'], $tpl['templateid'], false);
				}
			}
			
			if(isset($template['templates']) && !is_null($template['templates'])){
			
				$host_templates = CTemplate::get(array('hostids' => $template['hostid'], 'nopermissions' => 1));
				$host_templateids = zbx_objectValues($host_templates, 'templateid');
				$new_templateids = zbx_objectValues($template['templates'], 'templateid');
				
				$templates_to_add = array_diff($new_templateids, $host_templateids);
				if(!empty($templates_to_add)){
					$result &= CTemplate::massAdd(array('hosts' => $template, 'templates' => $templates_to_add));
				}
				
				$templates_to_del = array_diff($host_templateids, $new_templateids);
				$templates_to_del = array_diff($templates_to_del, $template['templates_clear']);
				
				if(!empty($templates_to_del)){
					$result &= CTemplate::massRemove(array('hosts' => $template, 'templates' => $templates_to_del));
				}	
			}
			
			if(!$result) break;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$upd_templates = self::get(array('templateids'=>$templateids, 'extendoutput'=>1, 'nopermissions'=>1));
			return $upd_templates;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

	private static function checkCircularLink($id, $templateids){
		if(empty($templateids)) return true;

		foreach($templateids as $tpid){
			if(bccomp($tpid, $id) == 0) return false;
		}
		
		$sql = 'SELECT templateid FROM hosts_templates WHERE hostid='.$id;
		$tpls_db = DBselect($sql);
		while($tpl = DBfetch($tpls_db)){
			$templateids[] = $tpl['templateid'];		
		}
		
		$first_lvl_templateids = array_unique($templateids);
		$next_templateids = $first_lvl_templateids;
		$templateids = array();

		do{
			$sql = 'SELECT templateid FROM hosts_templates WHERE '.DBcondition('hostid', $next_templateids);
			$tpls_db = DBselect($sql);

			$next_templateids = array();
			while($tpl = DBfetch($tpls_db)){
				$next_templateids[] = $tpl['templateid'];		
			}
			$templateids = array_merge($templateids, $next_templateids);
		}while(!empty($next_templateids));
	
		$first_lvl_templateids[] = $id;
		if(array_intersect($first_lvl_templateids, $templateids)){
			return false;
		}
		// $sql = 'SELECT hostid FROM hosts_templates WHERE '.DBcondition('hostid', $templateids).' AND '.DBcondition('templateid', $templateids);
		// if(DBfetch(DBselect($sql))){
			// return false;
		// }
		
		return true;
	}
	
	
/**
 * Delete Template
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $templateids
 * @param array $templateids['templateids']
 * @return boolean
 */
	public static function delete($templates){
		$templates = zbx_toArray($templates);
		$templateids = array();

		$del_templates = self::get(array('templateids'=>zbx_objectValues($templates, 'templateid'),
											'editable'=>1,
											'extendoutput'=>1,
											'preservekeys'=>1));
		foreach($templates as $gnum => $template){
			if(!isset($del_templates[$template['templateid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}
			$templateids[] = $template['templateid'];
			//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_HOST, 'Template ['.$template['host'].']');
		}

		if(!empty($templateids)){
			$result = delete_host($templateids, false);
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ templateids ]');
			$result = false;
		}

		if($result){
			return zbx_cleanHashes($del_templates);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}

/**
 * Link Template to Hosts
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $data
 * @param string $data['templates']
 * @param string $data['hosts']
 * @param string $data['groups']
 * @param string $data['templates_link']
 * @return boolean
 */
	public static function massAdd($data){
		$result = true;
		$errors = array();
	
		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : null;
		$templateids = is_null($templates) ? array() : zbx_objectValues($templates, 'templateid');
		
		self::BeginTransaction(__METHOD__);
		
		if(isset($data['groups'])){
			$options = array('groups' => $data['groups'], 'templates' => $templates);
			$result = CHostGroup::massAdd($options);
		}
		
		if(isset($data['hosts'])){
			$hostids = zbx_objectValues($data['hosts'], 'hostid');
			
			$linked = array();
			$sql = 'SELECT hostid, templateid FROM hosts_templates WHERE '.DBcondition('hostid', $hostids).
				' AND '.DBcondition('templateid', $templateids);
			$linked_db = DBselect($sql);
			while($pair = DBfetch($linked_db)){
				$linked[$pair['hostid']] = array($pair['templateid'] => $pair['templateid']);
			}
		
		
			foreach($hostids as $hostid){
				if(!self::checkCircularLink($hostid, $templateids)){
					$result = false;
					$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Circular link can not be created');					
					break;
				}
			}
			
			
			if($result){
				foreach($hostids as $hostid){
				foreach($templateids as $tnum => $templateid){
					if(isset($linked[$hostid]) && isset($linked[$hostid][$templateid])) continue;
					$hosttemplateid = get_dbid('hosts_templates', 'hosttemplateid');
					if(!$result = DBexecute('INSERT INTO hosts_templates VALUES ('.$hosttemplateid.','.$hostid.','.$templateid.')')){
						$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Insert error');
						break;
					}
				}
			}
			}

			if($result){
				foreach($hostids as $hostid){
					foreach($templateids as $tnum => $templateid){
						if(isset($linked[$hostid]) && isset($linked[$hostid][$templateid])) continue;
						
						sync_host_with_templates($hostid, $templateid);
					}
				}
			}
		}
		
		if(isset($data['templates_link'])){
			$templates_linkids = zbx_objectValues($data['templates_link'], 'templateid');
			
			$linked = array();
			$sql = 'SELECT hostid, templateid FROM hosts_templates WHERE '.DBcondition('hostid', $templates_linkids).
				' AND '.DBcondition('templateid', $templateids);
			$linked_db = DBselect($sql);
			while($pair = DBfetch($linked_db)){
				$linked[$pair['hostid']] = array($pair['templateid'] => $pair['templateid']);
			}
		
		
			foreach($templates_linkids as $templates_linkid){
				foreach($templateids as $tnum => $templateid){
					if(isset($linked[$templates_linkid]) && isset($linked[$templates_linkid][$templateid])) continue;
					$hosttemplateid = get_dbid('hosts_templates', 'hosttemplateid');
					if(!$result = DBexecute('INSERT INTO hosts_templates VALUES ('.$hosttemplateid.','.$templates_linkid.','.$templateid.')')){
						$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Insert error');
						break;
					}
				}
			}

			if($result){
				foreach($templates_linkids as $templates_linkid){
					foreach($templateids as $tnum => $templateid){
						if(isset($linked[$templates_linkid]) && isset($linked[$templates_linkid][$templateid])) continue;
						
						sync_host_with_templates($templateid, $templates_linkid);
					}
				}
			}
			
		}
		
		$result = self::EndTransaction($result, __METHOD__);
		
		if($result)
			return true;
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}
	
	/**
 * remove Hosts to HostGroups. All Hosts are added to all HostGroups.
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $data
 * @param array $data['templates']
 * @param array $data['groups']
 * @param array $data['hosts']
 * @return boolean
 */
	public static function massRemove($data){
		$errors = array();
		$result = true;
		
		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : null;
		$templateids = is_null($templates) ? array() : zbx_objectValues($templates, 'templateid');
		
		if(isset($data['groups'])){
			$options = array('groups' => $data['groups'], 'templates' => $templates);
			$result = CHostGroup::massRemove($options);
		}
		
		if(isset($data['hosts'])){
			$hostids = zbx_objectValues($data['hosts'], 'hostid');
			foreach($hostids as $hostid){	
				foreach($templateids as $templateid){
					unlink_template($hostid, $templateid, true);
				}
			}
		}

		
		if($result){
			return $result;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

	
/**
 * Link Template to Hosts
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $data
 * @param string $data['templateid']
 * @param array $data['hostids']
 * @return boolean
 */
	public static function linkHosts($data){
		$result = false;
		$error = '';

		$templateid = $data['templateid'];
		$hostids = $data['hostids'];
		self::BeginTransaction(__METHOD__);

		foreach($hostids as $hostid){
			$hosttemplateid = get_dbid('hosts_templates', 'hosttemplateid');
			if(!$result = DBexecute('INSERT INTO hosts_templates VALUES ('.$hosttemplateid.','.$hostid.','.$templateid.')')){
				$error = 'DBexecute';
				break;
			}
		}

		if($result) {
			foreach($hostids as $hostid){
				$result = sync_host_with_templates($hostid, $templateid);
				if(!$result) {
					$error = 'sync_host_with_templates';
					break;
				}
			}
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result)
			return true;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);
			return false;
		}
	}

/**
 * Unlink Hosts from Templates
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $data
 * @param string $data['templateid']
 * @param array $data['hostids']
 * @param boolean $data['clean']
 * @return boolean
 */
	public static function unlinkHosts($data){
		$templateid = $data['templateid'];
		$hostids = $data['hostids'];
		$clean = isset($data['clean']);

		foreach($hostids as $hostid) {
			$result = delete_template_elements($hostid, array($templateid), $clean);
		}
		$result&= DBexecute('DELETE FROM hosts_templates WHERE templateid='.$templateid.' AND '.DBcondition('hostid',$hostids));

		if($result)
			return true;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);
			return false;
		}
	}

/**
 * Link Host to Templates
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $data
 * @param string $data['hosts']
 * @param array $data['templats']
 * @return boolean
 */
	public static function linkTemplates($data){
		$result = true;
		$errors = array();

		$hosts = zbx_toArray($data['hosts']);
		$hostids = zbx_objectValues($hosts, 'hostid');

		$templates = zbx_toArray($data['templates']);
		$templateids = zbx_objectValues($templates, 'templateid');

		self::BeginTransaction(__METHOD__);

		$sql = 'SELECT hostid, templateid FROM hosts_templates WHERE '.DBcondition('hostid', $hostids).' AND '.DBcondition('templateid', $templateids);
		$linked_db = DBselect($sql);
		$linked = array();
		while($pair = DBfetch($linked_db)){
			$linked[$pair['templateid']] = array($pair['hostid'] => $pair['hostid']);
		}

		foreach($templates as $tnum => $template){
			$templateid = $template['templateid'];

			$hosttemplateid = get_dbid('hosts_templates', 'hosttemplateid');
			foreach($hosts as $hnum => $host){

				if(isset($linked[$templateid]) && isset($linked[$templateid][$host['hostid']])) continue;

				if(!$result = DBexecute('INSERT INTO hosts_templates VALUES ('.$hosttemplateid.','.$host['hostid'].','.$templateid.')'))
				$result = false;
				break;
			}
			if(!$result) break;
		}

		if($result){
			foreach($templates as $tnum => $template){
				foreach($hosts as $hnum => $host){
//					$result = sync_host_with_templates($hostid, $templateid);
					sync_host_with_templates($host['hostid'], $template['templateid']);
				}
//				if(!$result) break;
			}
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return true;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

/**
 * Unlink Templates from Host
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param string $data
 * @param string $data['templates']
 * @param array $data['hosts']
 * @param boolean $data['clean'] whether to wipe all info from template elements.
 * @return boolean
 */
	public static function unlinkTemplates($data){
		$errors = array();

		$templateids = zbx_objectValues($data['templates'], 'templateid');
		$hostids = zbx_objectValues($data['hosts'], 'hostid');
		$clean = isset($data['clean']) ? $data['clean'] : false;;

		self::BeginTransaction(__METHOD__);

		$sql = 'DELETE FROM hosts_templates WHERE '.DBcondition('hostid', $hostids).' AND '.DBcondition('templateid', $templateids);
		$result = DBexecute($sql);

		if($result){
			foreach($hostids as $hostid){
				$result = delete_template_elements($hostid, $templateids, $clean);
				if(!$result) break;
			}
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result)
			return true;
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

}
?>
