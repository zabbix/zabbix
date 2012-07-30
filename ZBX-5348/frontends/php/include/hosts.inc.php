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
	function setHostGroupInternal($groupids, $internal=ZBX_NOT_INTERNAL_GROUP){
		zbx_value2array($groupids);

		$sql = 'UPDATE groups SET internal='.$internal.' WHERE '.DBcondition('groupid', $groupids);
		$result = DBexecute($sql);
	return $result;
	}

/*
 * Function: check_circle_host_link
 *
 * Description:
 *     Check for circular templates linkage
 *
 * Author:
 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 * Comments:
 *
 *     NOTE: templates = array(id => name, id2 => name2, ...)
 *
 */
	function check_circle_host_link($hostid, $templates){
		if(count($templates) == 0)	return false;
		if(isset($templates[$hostid]))	return true;
		foreach($templates as $id => $name)
			if(check_circle_host_link($hostid, get_templates_by_hostid($id)))
				return true;

		return false;
	}

/*
 * Function: unlink_template
 *
 * Description:
 *     Unlink elements from host by template
 *
 * Author:
 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 * Comments: !!! Don't forget sync code with C !!!
 *
 */
	function unlink_template($hostid, $templateids, $unlink_mode = true){
		zbx_value2array($templateids);

		$result = delete_template_elements($hostid, $templateids, $unlink_mode);
		$result&= DBexecute('DELETE FROM hosts_templates WHERE hostid='.$hostid.' AND '.DBcondition('templateid',$templateids));
	return $result;
	}

/*
 * Function: delete_template_elements
 *
 * Description:
 *     Delete all elements from host by template
 *
 * Author:
 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 * Comments: !!! Don't forget sync code with C !!!
 *
 */
	function delete_template_elements($hostid, $templateids = null, $unlink_mode = false){
		zbx_value2array($templateids);

		delete_template_graphs($hostid, $templateids, $unlink_mode);
		delete_template_triggers($hostid, $templateids, $unlink_mode);
		delete_template_items($hostid, $templateids, $unlink_mode);
		delete_template_applications($hostid, $templateids, $unlink_mode);
	return true;
	}


	/**
	 * Copies all of the objects from the templates to the host.
	 *
	 * @param $targetHostId
	 * @param array $templatedIds
	 * @param bool $copyMode
	 *
	 * @return bool
	 */
	function copyTemplateElements($targetHostId, array $templatedIds, $copyMode = false) {

		$newTriggerIds = array();
		foreach ($templatedIds as $templateId) {
			copy_template_applications($targetHostId, $templateId, $copyMode);
			copy_template_items($targetHostId, $templateId, $copyMode);

			// copy triggers
			$newTemplateTriggerIds = copy_template_triggers($targetHostId, $templateId, $copyMode);
			$newTriggerIds = zbx_array_merge($newTriggerIds, $newTemplateTriggerIds);

			if ($copyMode) {
				copy_template_graphs($targetHostId, $templateId, $copyMode);
			}
			else {
				$result = CGraph::syncTemplates(array('hostids' => $targetHostId, 'templateids' => $templateId));
			}

			if (!$result) {
				return false;
			}
		}

		// update trigger dependencies
		foreach ($newTriggerIds as $templateTriggerId => $hostTriggerId) {
			$templateDependencies = get_trigger_dependencies_by_triggerid($templateTriggerId);
			$hostDependencies = replace_template_dependencies($templateDependencies, $targetHostId);
			foreach ($hostDependencies as $depTriggerId) {
				add_trigger_dependency($hostTriggerId, $depTriggerId);
			}
		}

		return true;
	}

	/**
	 * Synchronizes the host with the given templates.
	 *
	 * @param $hostid
	 * @param array $templateIds
	 *
	 * @return bool
	 */
	function syncHostWithTemplates($hostid, array $templateIds) {
		delete_template_elements($hostid, $templateIds);
		return copyTemplateElements($hostid, $templateIds);
	}

/*
 * Function: delete_host
 *
 * Description:
 *     Delete host with all elements and relations
 *
 * Author:
 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 * Comments: !!! Don't forget sync code with C !!!
 *
 */
	function delete_host($hostids, $unlink_mode = false){
		zbx_value2array($hostids);
		if(empty($hostids)) return true;

		$ret = false;
// unlink child hosts
		$db_childs = get_hosts_by_templateid($hostids);
		while($db_child = DBfetch($db_childs)){
			unlink_template($db_child['hostid'], $hostids, $unlink_mode);
		}

// delete web tests
		$del_httptests = array();
		$db_httptests = get_httptests_by_hostid($hostids);
		while($db_httptest = DBfetch($db_httptests)){
			$del_httptests[$db_httptest['httptestid']] = $db_httptest['httptestid'];
		}
		if(!empty($del_httptests)){
			delete_httptest($del_httptests);
		}

// delete items -> triggers -> graphs
		$del_items = array();
		$db_items = get_items_by_hostid($hostids);
		while($db_item = DBfetch($db_items)){
			$del_items[$db_item['itemid']] = $db_item['itemid'];
		}

		delete_item($del_items);

// delete screen items
		DBexecute('DELETE FROM screens_items WHERE '.DBcondition('resourceid',$hostids).' AND resourcetype='.SCREEN_RESOURCE_HOST_TRIGGERS);

// delete host from maps
		delete_sysmaps_elements_with_hostid($hostids);

// delete host from maintenances
		DBexecute('DELETE FROM maintenances_hosts WHERE '.DBcondition('hostid',$hostids));

// delete host from group
		DBexecute('DELETE FROM hosts_groups WHERE '.DBcondition('hostid',$hostids));

// delete host from template linkages
		DBexecute('DELETE FROM hosts_templates WHERE '.DBcondition('hostid',$hostids));

		// delete host macros
		DBexecute('DELETE FROM hostmacro WHERE '.DBcondition('hostid', $hostids));

// disable actions
		$actionids = array();

// conditions
		$sql = 'SELECT DISTINCT actionid '.
				' FROM conditions '.
				' WHERE conditiontype='.CONDITION_TYPE_HOST.
					' AND '.DBcondition('value',$hostids, false, true);		// FIXED[POSIBLE value type violation]!!!
		$db_actions = DBselect($sql);
		while($db_action = DBfetch($db_actions)){
			$actionids[$db_action['actionid']] = $db_action['actionid'];
		}

		DBexecute('UPDATE actions '.
					' SET status='.ACTION_STATUS_DISABLED.
					' WHERE '.DBcondition('actionid',$actionids));
// operations
		$sql = 'SELECT DISTINCT o.actionid '.
				' FROM operations o '.
				' WHERE o.operationtype IN ('.OPERATION_TYPE_GROUP_ADD.','.OPERATION_TYPE_GROUP_REMOVE.') '.
					' AND '.DBcondition('o.objectid',$hostids);
		$db_actions = DBselect($sql);
		while($db_action = DBfetch($db_actions)){
			$actionids[$db_action['actionid']] = $db_action['actionid'];
		}

		if(!empty($actionids)){
			DBexecute('UPDATE actions '.
					' SET status='.ACTION_STATUS_DISABLED.
					' WHERE '.DBcondition('actionid',$actionids));
		}


// delete action conditions
		DBexecute('DELETE FROM conditions '.
					' WHERE conditiontype='.CONDITION_TYPE_HOST.
						' AND '.DBcondition('value',$hostids, false, true));	// FIXED[POSIBLE value type violation]!!!


// delete action operations
		DBexecute('DELETE FROM operations '.
					' WHERE operationtype IN ('.OPERATION_TYPE_TEMPLATE_ADD.','.OPERATION_TYPE_TEMPLATE_REMOVE.') '.
						' AND '.DBcondition('objectid',$hostids));


// delete host profile
		delete_host_profile($hostids);
		delete_host_profile_ext($hostids);
		$applicationids = array();
		$query = 'SELECT a.applicationid'.
				' FROM applications a'.
				' WHERE	'.DBcondition('a.hostid', $hostids);
		$db_applications = DBselect($query);
		while($app = DBfetch($db_applications)){
			$applicationids[] = $app['applicationid'];
		}
		$result = delete_application($applicationids);
		if(!$result) return false;


// delete host
		foreach($hostids as $id){	/* The section should be improved */
			$host_old = get_host_by_hostid($id);
			$result = DBexecute('DELETE FROM hosts WHERE hostid='.$id);
			if($result){
				if($host_old['status'] == HOST_STATUS_TEMPLATE){
					info(S_TEMPLATE.SPACE.$host_old['host'].SPACE.S_HOST_HAS_BEEN_DELETED_MSG_PART2);
					add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TEMPLATE, $id, $host_old['host'], 'hosts', NULL, NULL);
				}
				else{
					info(S_HOST_HAS_BEEN_DELETED_MSG_PART1.SPACE.$host_old['host'].SPACE.S_HOST_HAS_BEEN_DELETED_MSG_PART2);
					add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_HOST, $id, $host_old['host'], 'hosts', NULL, NULL);
				}
			}
			else
				break;
		}

		return $result;
	}

	function delete_host_group($groupids){
		zbx_value2array($groupids);
		if(empty($groupids)) return true;

		$dlt_groupids = getDeletableHostGroups($groupids);
		if(count($groupids) != count($dlt_groupids)){
			foreach($groupids as $num => $groupid){
				if(!isset($dlt_groupids[$groupid])){
					$group = get_hostgroup_by_groupid($groupid);
					if($group['internal'] == ZBX_INTERNAL_GROUP)
						error(S_GROUP.SPACE.'"'.$group['name'].'"'.SPACE.S_INTERNAL_AND_CANNOT_DELETED_SMALL);
					else
						error(S_GROUP.SPACE.'"'.$group['name'].'"'.SPACE.S_CANNOT_DELETED_INNER_HOSTS_CANNOT_UNLINKED_SMALL);
				}
			}
			return false;
		}

// check if hostgroup used in scripts
		$error = false;
		$sql = 'SELECT s.name AS script_name, g.name AS group_name '.
				' FROM scripts s, groups g'.
				' WHERE '.
					' g.groupid = s.groupid '.
					' AND '.DBcondition('s.groupid', $groupids);
		$res = DBselect($sql);
		while($group = DBfetch($res)){
			$error = true;
			error(sprintf(S_HOSTGROUP_CANNOT_BE_DELETED_USED_IN_SCRIPT, $group['group_name'], $group['script_name']));
		}
		if($error) return false;

// delete screens items
		$resources = array(
			SCREEN_RESOURCE_HOSTGROUP_TRIGGERS,
			SCREEN_RESOURCE_HOSTS_INFO,
			SCREEN_RESOURCE_TRIGGERS_INFO,
			SCREEN_RESOURCE_TRIGGERS_OVERVIEW,
			SCREEN_RESOURCE_DATA_OVERVIEW
		);
		$sql = 'DELETE FROM screens_items '.
				' WHERE '.DBcondition('resourceid',$groupids).
					' AND '.DBcondition('resourcetype',$resources);
		DBexecute($sql);

// delete sysmap element
		if(!delete_sysmaps_elements_with_groupid($groupids))
			return false;

// delete host from maintenances
		DBexecute('DELETE FROM maintenances_groups WHERE '.DBcondition('groupid',$groupids));

// disable actions
		$actionids = array();

// conditions
		$sql = 'SELECT DISTINCT c.actionid '.
				' FROM conditions c '.
				' WHERE c.conditiontype='.CONDITION_TYPE_HOST_GROUP.
					' AND '.DBcondition('c.value',$groupids, false, true);
		$db_actions = DBselect($sql);
		while($db_action = DBfetch($db_actions)){
			$actionids[$db_action['actionid']] = $db_action['actionid'];
		}

// operations
		$sql = 'SELECT DISTINCT o.actionid '.
				' FROM operations o '.
				' WHERE o.operationtype IN ('.OPERATION_TYPE_GROUP_ADD.','.OPERATION_TYPE_GROUP_REMOVE.') '.
					' AND '.DBcondition('o.objectid',$groupids);
		$db_actions = DBselect($sql);
		while($db_action = DBfetch($db_actions)){
			$actionids[$db_action['actionid']] = $db_action['actionid'];
		}

		if(!empty($actionids)){
			DBexecute('UPDATE actions '.
					' SET status='.ACTION_STATUS_DISABLED.
					' WHERE '.DBcondition('actionid',$actionids));
		}


// delete action conditions
		DBexecute('DELETE FROM conditions'.
					' WHERE conditiontype='.CONDITION_TYPE_HOST_GROUP.
						' AND '.DBcondition('value',$groupids, false, true));

// delete action operations
		DBexecute('DELETE FROM operations '.
					' WHERE operationtype IN ('.OPERATION_TYPE_GROUP_ADD.','.OPERATION_TYPE_GROUP_REMOVE.') '.
						' AND '.DBcondition('objectid',$groupids));


		DBexecute('DELETE FROM hosts_groups WHERE '.DBcondition('groupid',$groupids));

		foreach ($groupids as $id) {	/* The section should be improved */
			$hostgroup_old = get_hostgroup_by_groupid($id);
			$result = DBexecute('DELETE FROM groups WHERE groupid='.$id);
			if ($result)
				add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_HOST_GROUP, $id, $hostgroup_old['name'], 'groups', NULL, NULL);
			else
				break;
		}

		return $result;
	}

	function get_hostgroup_by_groupid($groupid){
		$result=DBselect("select * from groups where groupid=".$groupid);
		$row=DBfetch($result);
		if($row){
			return $row;
		}
		error(S_NO_HOST_GROUPS_WITH." groupid=[$groupid]");
		return  false;
	}

	function db_save_proxy($name,$status,$useip,$dns,$ip,$port,$proxyid=null){
		if(!is_string($name)){
			error(S_INCORRECT_PARAMETERS_FOR_SMALL." 'db_save_proxy'");
			return false;
		}

		if(is_null($proxyid))
			$result = DBselect('SELECT * FROM hosts WHERE status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'.
					' and '.DBin_node('hostid').' AND host='.zbx_dbstr($name));
		else
			$result = DBselect('SELECT * FROM hosts WHERE status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'.
					' and '.DBin_node('hostid').' AND host='.zbx_dbstr($name).
					' and hostid<>'.$proxyid);

		if(DBfetch($result)){
			error(S_PROXY.SPACE."'$name'".SPACE.S_ALREADY_EXISTS_SMALL);
			return false;
		}

		if(is_null($proxyid)){
			$proxyid=get_dbid('hosts','hostid');
			if(!DBexecute('INSERT INTO hosts (hostid,host,status,useip,dns,ip,port)'.
				' values ('.$proxyid.','.zbx_dbstr($name).','.$status.','.$useip.','.zbx_dbstr($dns).','.zbx_dbstr($ip).','.$port.')'))
			{
				return false;
			}

			return $proxyid;
		}
		else
			return DBexecute('update hosts set host='.zbx_dbstr($name).',status='.$status.',useip='.$useip.',dns='.zbx_dbstr($dns).',ip='.zbx_dbstr($ip).',port='.$port.' where hostid='.$proxyid);
	}

	function delete_proxy($proxyids){
		zbx_value2array($proxyids);

		$actionids = array();
// conditions
		$sql = 'SELECT DISTINCT actionid FROM conditions '.
				' WHERE conditiontype='.CONDITION_TYPE_PROXY.
					' AND '.DBcondition('value', $proxyids, false, true);	// FIXED[POSIBLE value type violation]!!!

		$db_actions = DBselect($sql);
		while($db_action = DBfetch($db_actions))
			$actionids[] = $db_action['actionid'];

		if (!empty($actionids))
		{
			DBexecute('UPDATE actions '.
					' SET status='.ACTION_STATUS_DISABLED.
					' WHERE '.DBcondition('actionid', $actionids));

// delete action conditions
			DBexecute('DELETE FROM conditions '.
					' WHERE conditiontype='.CONDITION_TYPE_PROXY.
					' AND '.DBcondition('value',$proxyids, false, true));	// FIXED[POSIBLE value type violation]!!!
		}

		if(!DBexecute('UPDATE hosts SET proxy_hostid=0 WHERE '.DBcondition('proxy_hostid',$proxyids)))
			return false;

	return DBexecute('DELETE FROM hosts WHERE '.DBcondition('hostid',$proxyids));
	}

	function update_hosts_by_proxyid($proxyid,$hosts=array()){
		DBexecute('update hosts set proxy_hostid=0 where proxy_hostid='.$proxyid);

		foreach($hosts as $hostid){
			DBexecute('update hosts set proxy_hostid='.$proxyid.' where hostid='.$hostid);
		}
	}

	function add_proxy($name,$status,$useip,$dns,$ip,$port,$hosts=array()){
		$proxyid = db_save_proxy($name,$status,$useip,$dns,$ip,$port);
		if(!$proxyid)
			return	$proxyid;

		update_hosts_by_proxyid($proxyid,$hosts);

		return $proxyid;
	}

	function update_proxy($proxyid,$name,$status,$useip,$dns,$ip,$port,$hosts){
		$result = db_save_proxy($name,$status,$useip,$dns,$ip,$port,$proxyid);
		if(!$result)
			return	$result;

		update_hosts_by_proxyid($proxyid,$hosts);

		return $result;
	}

	function get_host_by_itemid($itemids){
		$res_array = is_array($itemids);
		zbx_value2array($itemids);

		$result = false;
		$hosts = array();

		$sql = 'SELECT i.itemid, h.* '.
				' FROM hosts h, items i '.
				' WHERE i.hostid=h.hostid '.
					' AND '.DBcondition('i.itemid',$itemids);
		$res=DBselect($sql);
		while($row=DBfetch($res)){
			$result = true;
			$hosts[$row['itemid']] = $row;
		}

		if(!$res_array){
			foreach($hosts as $itemid => $host){
				$result = $host;
			}
		}
		else if($result){
			$result = $hosts;
			unset($hosts);
		}

	return $result;
	}

	function get_host_by_hostid($hostid,$no_error_message=0){
		$sql='SELECT * FROM hosts WHERE hostid='.$hostid;
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row){
			return $row;
		}
		if($no_error_message == 0)
			error(S_NO_HOST_WITH.' with hostid=['.$hostid.']');

	return	false;
	}

	function get_hosts_by_templateid($templateids){
		zbx_value2array($templateids);
		$sql = 'SELECT h.* '.
				' FROM hosts h, hosts_templates ht '.
				' WHERE h.hostid=ht.hostid '.
					' AND '.DBcondition('ht.templateid',$templateids);

	return DBselect($sql);
	}

// Update Host status

	function update_host_status($hostids,$status){
		$res = true;
		zbx_value2array($hostids);

//		$hosts = array();
		$sql = 'SELECT * '.
			' FROM hosts '.
			' WHERE '.DBcondition('hostid', $hostids).
				' AND status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		$result = DBselect($sql);
		while($host=DBfetch($result)){
			if($status != $host['status']){
//				$hosts[$host['hostid']] = $host['hostid'];
				update_trigger_value_to_unknown_by_hostid($host['hostid']);
				$res = DBexecute('UPDATE hosts SET status='.$status.' WHERE hostid='.$host['hostid']);
				if($res){
					$host_new = $host;//get_host_by_hostid($host['hostid']);
					$host_new['status'] = $status;
					add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST, $host['hostid'], $host['host'], 'hosts', $host, $host_new);
				}
				info(S_UPDATED_STATUS_OF_HOST.' "'.$host['host'].'"');
			}
		}

/*
		if(!empty($hosts)){
			update_trigger_value_to_unknown_by_hostid($hosts);

			return	DBexecute('UPDATE hosts SET status='.$status.
							' WHERE '.DBcondition('hostid',$hosts).
								' AND status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'
						);
		}
		else{z
			return 1;
		}
//*/
	return $res;
	}

/*
 * Function: get_templates_by_hostid
 *
 * Description:
 *     Retrieve templates for specified host
 *
 * Author:
 *		Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 * Comments:
 *		mod by Aly
 */
	function get_templates_by_hostid($hostid){
		$result = array();
		$db_templates = DBselect('SELECT DISTINCT h.hostid,h.host '.
				' FROM hosts_templates ht '.
					' LEFT JOIN hosts h ON h.hostid=ht.templateid '.
				' WHERE ht.hostid='.$hostid.
				' ORDER BY h.host');

		while($template_data = DBfetch($db_templates)){
			$result[$template_data['hostid']] = $template_data['host'];
		}

	return $result;
	}

/*
 * Function: get_viewed_groups
 *
 * Description:
 *     Retrieve groups for dropdown
 *
 * Author:
 *		Artem "Aly" Suharev
 *
 * Comments:
 *
 */
function get_viewed_groups($perm, $options=array(), $nodeid=null, $sql=array()){
	global $USER_DETAILS;
	global $page;

	$def_sql = array(
				'select' =>	array('g.groupid','g.name'),
				'from' =>	array('groups g'),
				'where' =>	array(),
				'order' =>	array(),
			);

	$def_options = array(
				'deny_all' =>						0,
				'allow_all' =>						0,
				'select_first_group'=>				0,
				'select_first_group_if_empty'=>		0,
				'do_not_select' =>					0,
				'do_not_select_if_empty' =>			0,
				'monitored_hosts' =>				0,
				'templated_hosts' =>				0,
				'real_hosts' =>						0,
				'not_proxy_hosts' =>				0,
				'with_items' =>						0,
				'with_monitored_items' =>			0,
				'with_historical_items'=>			0,
				'with_triggers' =>					0,
				'with_monitored_triggers'=>			0,
				'with_httptests' =>					0,
				'with_monitored_httptests'=>		0,
				'with_graphs'=>						0,
				'only_current_node' =>				0
			);
	$def_options = zbx_array_merge($def_options, $options);

	$config = select_config();

	$dd_first_entry = $config['dropdown_first_entry'];
//	if($page['menu'] == 'config') $dd_first_entry = ZBX_DROPDOWN_FIRST_NONE;
	if($def_options['allow_all']) $dd_first_entry = ZBX_DROPDOWN_FIRST_ALL;
	if($def_options['deny_all']) $dd_first_entry = ZBX_DROPDOWN_FIRST_NONE;

	$result = array('original'=> -1, 'selected'=>0, 'groups'=> array(), 'groupids'=> array());
	$groups = &$result['groups'];
	$groupids = &$result['groupids'];

	$first_entry = ($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE)?S_NOT_SELECTED_SMALL:S_ALL_SMALL;
	$groups['0'] = $first_entry;

	$_REQUEST['groupid'] = $result['original'] = get_request('groupid', -1);
	$_REQUEST['hostid'] = get_request('hostid', -1);
//-----
	if(is_null($nodeid)){
		if(!$def_options['only_current_node']) $nodeid = get_current_nodeid();
		else $nodeid = get_current_nodeid(false);
	}
//	$nodeid = is_null($nodeid)?get_current_nodeid(!$def_options['only_current_node']):$nodeid;
	$available_groups = get_accessible_groups_by_user($USER_DETAILS,$perm,PERM_RES_IDS_ARRAY,$nodeid,AVAILABLE_NOCACHE);

// nodes
	if(ZBX_DISTRIBUTED){
		$def_sql['select'][] = 'n.name as node_name';
		$def_sql['from'][] = 'nodes n';
		$def_sql['where'][] = 'n.nodeid='.DBid2nodeid('g.groupid');
		$def_sql['order'][] = 'node_name';
	}

// hosts
	if($def_options['monitored_hosts'])
		$def_sql['where'][] = 'h.status='.HOST_STATUS_MONITORED;
	else if($def_options['real_hosts'])
		$def_sql['where'][] = 'h.status IN('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
	else if($def_options['templated_hosts'])
		$def_sql['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
	else if($def_options['not_proxy_hosts'])
		$def_sql['where'][] = 'h.status<>'.HOST_STATUS_PROXY;
	else
		$in_hosts = false;

	if(!isset($in_hosts)){
		$def_sql['from'][] = 'hosts_groups hg';
		$def_sql['from'][] = 'hosts h';
		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'h.hostid=hg.hostid';
	}

// items
	if($def_options['with_items']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid )';
	}
	else if($def_options['with_monitored_items']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid AND i.status='.ITEM_STATUS_ACTIVE.')';
	}
	else if($def_options['with_historical_items']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid AND (i.status='.ITEM_STATUS_ACTIVE.' OR i.status='.ITEM_STATUS_NOTSUPPORTED.') AND i.lastvalue IS NOT NULL)';
	}

// triggers
	if($def_options['with_triggers']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS( SELECT t.triggerid '.
									' FROM items i, functions f, triggers t'.
									' WHERE i.hostid=hg.hostid '.
										' AND f.itemid=i.itemid '.
										' AND t.triggerid=f.triggerid)';
	}
	else if($def_options['with_monitored_triggers']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS( SELECT t.triggerid '.
									' FROM items i, functions f, triggers t'.
									' WHERE i.hostid=hg.hostid '.
										' AND i.status='.ITEM_STATUS_ACTIVE.
										' AND i.itemid=f.itemid '.
										' AND f.triggerid=t.triggerid '.
										' AND t.status='.TRIGGER_STATUS_ENABLED.')';
	}

// httptests
	if($def_options['with_httptests']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS( SELECT a.applicationid '.
								' FROM applications a, httptest ht '.
								' WHERE a.hostid=hg.hostid '.
									' AND ht.applicationid=a.applicationid)';
	}
	else if($def_options['with_monitored_httptests']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS( '.
								' SELECT a.applicationid '.
								' FROM applications a, httptest ht '.
								' WHERE a.hostid=hg.hostid '.
									' AND ht.applicationid=a.applicationid '.
									' AND ht.status='.HTTPTEST_STATUS_ACTIVE.')';
	}

// graphs
	if($def_options['with_graphs']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS( SELECT DISTINCT i.itemid '.
									' FROM items i, graphs_items gi '.
									' WHERE i.hostid=hg.hostid '.
										' AND i.itemid=gi.itemid)';
	}

//-----
	$def_sql['order'][] = 'g.name';

	foreach($sql as $key => $value){
		zbx_value2array($value);

		if(isset($def_sql[$key])) $def_sql[$key] = zbx_array_merge($def_sql[$key], $value);
		else $def_sql[$key] = $value;
	}

	$def_sql['select'] = array_unique($def_sql['select']);
	$def_sql['from'] = array_unique($def_sql['from']);
	$def_sql['where'] = array_unique($def_sql['where']);
	$def_sql['order'] = array_unique($def_sql['order']);

	$sql_select = '';
	$sql_from = '';
	$sql_where = '';
	$sql_order = '';
	if(!empty($def_sql['select'])) $sql_select.= implode(',',$def_sql['select']);
	if(!empty($def_sql['from'])) $sql_from.= implode(',',$def_sql['from']);
	if(!empty($def_sql['where'])) $sql_where.= ' AND '.implode(' AND ',$def_sql['where']);
	if(!empty($def_sql['order'])) $sql_order.= implode(',',$def_sql['order']);

	$sql = 'SELECT DISTINCT '.$sql_select.
			' FROM '.$sql_from.
			' WHERE '.DBcondition('g.groupid',$available_groups).
				$sql_where.
			' ORDER BY '.$sql_order;
//SDI($sql);
	$res = DBselect($sql);
	while($group = DBfetch($res)){
		$groups[$group['groupid']] = $group['name'];
		$groupids[$group['groupid']] = $group['groupid'];

		if(bccomp($_REQUEST['groupid'],$group['groupid']) == 0) $result['selected'] = $group['groupid'];
	}

	$profile_groupid = CProfile::get('web.'.$page['menu'].'.groupid');
//-----
	if($def_options['do_not_select']){
		$result['selected'] = $_REQUEST['groupid'] = 0;
	}
	else if($def_options['do_not_select_if_empty'] && ($_REQUEST['groupid'] == -1)){
		$result['selected'] = $_REQUEST['groupid'] = 0;
	}
	else if(($def_options['select_first_group']) ||
			($def_options['select_first_group_if_empty'] && ($_REQUEST['groupid'] == -1) && is_null($profile_groupid)))
	{
		$first_groupid = next($groupids);
		reset($groupids);

		if($first_groupid !== FALSE)
			$_REQUEST['groupid'] = $result['selected'] = $first_groupid;
		else
			$_REQUEST['groupid'] = $result['selected'] = 0;
	}
	else{
		if($config['dropdown_first_remember']){
			if($_REQUEST['groupid'] == -1) $_REQUEST['groupid'] = is_null($profile_groupid)? '0':$profile_groupid;
			if(isset($groupids[$_REQUEST['groupid']])){
				$result['selected'] = $_REQUEST['groupid'];
			}
			else{
				$_REQUEST['groupid'] = $result['selected'];
			}
		}
		else{
			$_REQUEST['groupid'] = $result['selected'];
		}
	}

return $result;
}

/*
 * Function: get_viewed_hosts
 *
 * Description:
 *     Retrieve groups for dropdown
 *
 * Author:
 *		Artem "Aly" Suharev
 *
 * Comments:
 *
 */
function get_viewed_hosts($perm, $groupid=0, $options=array(), $nodeid=null, $sql=array()){
	global $USER_DETAILS;
	global $page;

	$userid = $USER_DETAILS['userid'];

	$def_sql = array(
				'select' =>	array('h.hostid','h.host'),
				'from' =>	array('hosts h'),
				'where' =>	array(),
				'order' =>	array(),
			);

	$def_options = array(
				'deny_all' =>				0,
				'allow_all' =>				0,
				'select_first_host' =>			0,
				'select_first_host_if_empty' =>		0,
				'select_host_on_group_switch' =>	0,
				'do_not_select' =>			0,
				'do_not_select_if_empty' =>		0,
				'monitored_hosts' =>			0,
				'templated_hosts' =>			0,
				'real_hosts' =>				0,
				'not_proxy_hosts' =>			0,
				'with_items' =>				0,
				'with_monitored_items' =>		0,
				'with_historical_items' =>		0,
				'with_triggers' =>			0,
				'with_monitored_triggers' =>		0,
				'with_httptests' =>			0,
				'with_monitored_httptests' =>		0,
				'with_graphs' =>			0,
				'only_current_node' =>			0
			);

	$def_options = zbx_array_merge($def_options, $options);

	$config = select_config();

	$dd_first_entry = $config['dropdown_first_entry'];
	if($def_options['allow_all']) $dd_first_entry = ZBX_DROPDOWN_FIRST_ALL;
	if($def_options['deny_all']) $dd_first_entry = ZBX_DROPDOWN_FIRST_NONE;
	if($dd_first_entry == ZBX_DROPDOWN_FIRST_ALL) $def_options['select_host_on_group_switch'] = 1;

	$result = array('original'=> -1, 'selected'=>0, 'hosts'=> array(), 'hostids'=> array());
	$hosts = &$result['hosts'];
	$hostids = &$result['hostids'];

	$first_entry = ($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE)?S_NOT_SELECTED_SMALL:S_ALL_SMALL;
	$hosts['0'] = $first_entry;

	if(!is_array($groupid) && ($groupid == 0)){
		if($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE){
			return $result;
		}
	}
	else{
		zbx_value2array($groupid);

		$def_sql['from'][] = 'hosts_groups hg';
		$def_sql['where'][] = DBcondition('hg.groupid',$groupid);
		$def_sql['where'][] = 'hg.hostid=h.hostid';
	}

	$_REQUEST['hostid'] = $result['original'] = get_request('hostid', -1);
//-----
	if(is_null($nodeid)){
		if(!$def_options['only_current_node']) $nodeid = get_current_nodeid();
		else $nodeid = get_current_nodeid(false);
	}

//$nodeid = is_null($nodeid)?get_current_nodeid($opt):$nodeid;
//$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,$perm,PERM_RES_IDS_ARRAY,$nodeid,AVAILABLE_NOCACHE);

	if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			$def_sql['from']['hg'] = 'hosts_groups hg';
			$def_sql['from']['r'] = 'rights r';
			$def_sql['from']['ug'] = 'users_groups ug';
			$def_sql['where']['hgh'] = 'hg.hostid=h.hostid';
			$def_sql['where'][] = 'r.id=hg.groupid ';
			$def_sql['where'][] = 'r.groupid=ug.usrgrpid';
			$def_sql['where'][] = 'ug.userid='.$userid;
			$def_sql['where'][] = 'r.permission>='.$perm;
			$def_sql['where'][] = 'NOT EXISTS( '.
									' SELECT hgg.groupid '.
									' FROM hosts_groups hgg, rights rr, users_groups gg '.
									' WHERE hgg.hostid=hg.hostid '.
										' AND rr.id=hgg.groupid '.
										' AND rr.groupid=gg.usrgrpid '.
										' AND gg.userid='.$userid.
										' AND rr.permission<'.$perm.')';
	}

// nodes
	if(ZBX_DISTRIBUTED){
		$def_sql['select'][] = 'n.name';
		$def_sql['from'][] = 'nodes n';
		$def_sql['where'][] = 'n.nodeid='.DBid2nodeid('h.hostid');
		$def_sql['order'][] = 'n.name';
	}

// hosts
	if($def_options['monitored_hosts'])
		$def_sql['where'][] = 'h.status='.HOST_STATUS_MONITORED;
	else if($def_options['real_hosts'])
		$def_sql['where'][] = 'h.status IN('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
	else if($def_options['templated_hosts'])
		$def_sql['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
	else if($def_options['not_proxy_hosts'])
		$def_sql['where'][] = 'h.status<>'.HOST_STATUS_PROXY;


// items
	if($def_options['with_items']){
		$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid )';
	}
	else if($def_options['with_monitored_items']){
		$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid AND i.status='.ITEM_STATUS_ACTIVE.')';
	}
	else if($def_options['with_historical_items']){
		$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid AND (i.status='.ITEM_STATUS_ACTIVE.' OR i.status='.ITEM_STATUS_NOTSUPPORTED.') AND i.lastvalue IS NOT NULL)';
	}


// triggers
	if($def_options['with_triggers']){
		$def_sql['where'][] = 'EXISTS( SELECT i.itemid '.
									' FROM items i, functions f, triggers t'.
									' WHERE i.hostid=h.hostid '.
										' AND i.itemid=f.itemid '.
										' AND f.triggerid=t.triggerid)';
	}
	else if($def_options['with_monitored_triggers']){
		$def_sql['where'][] = 'EXISTS( SELECT i.itemid '.
									' FROM items i, functions f, triggers t'.
									' WHERE i.hostid=h.hostid '.
										' AND i.status='.ITEM_STATUS_ACTIVE.
										' AND i.itemid=f.itemid '.
										' AND f.triggerid=t.triggerid '.
										' AND t.status='.TRIGGER_STATUS_ENABLED.')';
	}

// httptests
	if($def_options['with_httptests']){
		$def_sql['where'][] = 'EXISTS( SELECT a.applicationid '.
								' FROM applications a, httptest ht '.
								' WHERE a.hostid=h.hostid '.
									' AND ht.applicationid=a.applicationid)';
	}
	else if($def_options['with_monitored_httptests']){
		$def_sql['where'][] = 'EXISTS( SELECT a.applicationid '.
								' FROM applications a, httptest ht '.
								' WHERE a.hostid=h.hostid '.
									' AND ht.applicationid=a.applicationid '.
									' AND ht.status='.HTTPTEST_STATUS_ACTIVE.')';
	}

// graphs
	if($def_options['with_graphs']){
		$def_sql['where'][] = 'EXISTS( SELECT DISTINCT i.itemid '.
									' FROM items i, graphs_items gi '.
									' WHERE i.hostid=h.hostid '.
										' AND i.itemid=gi.itemid)';
	}
//------
	$def_sql['order'][] = 'h.host';

	foreach($sql as $key => $value){
		zbx_value2array($value);

		if(isset($def_sql[$key])) $def_sql[$key] = zbx_array_merge($def_sql[$key], $value);
		else $def_sql[$key] = $value;
	}

	$def_sql['select'] = array_unique($def_sql['select']);
	$def_sql['from'] = array_unique($def_sql['from']);
	$def_sql['where'] = array_unique($def_sql['where']);
	$def_sql['order'] = array_unique($def_sql['order']);

	$sql_select = '';
	$sql_from = '';
	$sql_where = '';
	$sql_order = '';
	if(!empty($def_sql['select'])) $sql_select.= implode(',',$def_sql['select']);
	if(!empty($def_sql['from'])) $sql_from.= implode(',',$def_sql['from']);
	if(!empty($def_sql['where'])) $sql_where.= ' AND '.implode(' AND ',$def_sql['where']);
	if(!empty($def_sql['order'])) $sql_order.= implode(',',$def_sql['order']);

	$sql = 'SELECT DISTINCT '.$sql_select.
			' FROM '.$sql_from.
			' WHERE '.DBin_node('h.hostid', $nodeid).
				$sql_where.
			' ORDER BY '.$sql_order;
	$res = DBselect($sql);
	while($host = DBfetch($res)){
		$hosts[$host['hostid']] = $host['host'];
		$hostids[$host['hostid']] = $host['hostid'];

		if(bccomp($_REQUEST['hostid'],$host['hostid']) == 0) $result['selected'] = $host['hostid'];
	}

	$profile_hostid = CProfile::get('web.'.$page['menu'].'.hostid');

//-----
	if($def_options['do_not_select']){
		$_REQUEST['hostid'] = $result['selected'] = 0;
	}
	else if($def_options['do_not_select_if_empty'] && ($_REQUEST['hostid'] == -1)){
		$_REQUEST['hostid'] = $result['selected'] = 0;
	}
	else if(($def_options['select_first_host']) ||
			($def_options['select_first_host_if_empty'] && ($_REQUEST['hostid'] == -1) && is_null($profile_hostid)) ||
			($def_options['select_host_on_group_switch'] && ($_REQUEST['hostid'] != -1) && (bccomp($_REQUEST['hostid'],$result['selected']) != 0)))
	{
		$first_hostid = next($hostids);
		reset($hostids);

		if($first_hostid !== FALSE)
			$_REQUEST['hostid'] = $result['selected'] = $first_hostid;
		else
			$_REQUEST['hostid'] = $result['selected'] = 0;
	}
	else{
		if($config['dropdown_first_remember']){
			if($_REQUEST['hostid'] == -1) $_REQUEST['hostid'] = is_null($profile_hostid)? '0':$profile_hostid;

			if(isset($hostids[$_REQUEST['hostid']])){
				$result['selected'] = $_REQUEST['hostid'];
			}
			else{
				$_REQUEST['hostid'] = $result['selected'];
			}
		}
		else{
			$_REQUEST['hostid'] = $result['selected'];
		}
	}
return $result;
}

/*
 * Function: validate_group_with_host
 *
 * Description:
 *     Check available groups and host by user permission
 *     and check current group an host relations
 *
 * Author:
 *		Aly
 *
 * Comments:
 *
 */
	function validate_group_with_host(&$PAGE_GROUPS, &$PAGE_HOSTS, $reset_host=true){
		global $page;

		$config = select_config();

		$dd_first_entry = $config['dropdown_first_entry'];

		$group_var = 'web.latest.groupid';
		$host_var = 'web.latest.hostid';

		$_REQUEST['groupid']    = get_request('groupid', CProfile::get($group_var, -1));
		$_REQUEST['hostid']     = get_request('hostid', CProfile::get($host_var, -1));

		if($_REQUEST['groupid'] > 0){
			if($_REQUEST['hostid'] > 0){
				$sql = 'SELECT groupid FROM hosts_groups WHERE hostid='.$_REQUEST['hostid'].' AND groupid='.$_REQUEST['groupid'];
				if(!DBfetch(DBselect($sql))){
					$_REQUEST['hostid'] = 0;
				}
			}
			else if($reset_host){
				$_REQUEST['hostid'] = 0;
			}
		}
		else{
			$_REQUEST['groupid'] = 0;

			if($reset_host && ($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE)){
				$_REQUEST['hostid'] = 0;
			}
		}

		$PAGE_GROUPS['selected'] = $_REQUEST['groupid'];
		$PAGE_HOSTS['selected'] = $_REQUEST['hostid'];

		if(($PAGE_GROUPS['selected'] == 0) && ($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE) && $reset_host){
			$PAGE_GROUPS['groupids'] = array();
		}

		if(($PAGE_HOSTS['selected'] == 0) && ($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE) && $reset_host){
			$PAGE_HOSTS['hostids'] = array();
		}

		if($PAGE_GROUPS['original'] > -1)
			CProfile::update('web.'.$page['menu'].'.groupid', $_REQUEST['groupid'], PROFILE_TYPE_ID);

		if($PAGE_HOSTS['original'] > -1)
			CProfile::update('web.'.$page['menu'].'.hostid', $_REQUEST['hostid'], PROFILE_TYPE_ID);

		CProfile::update($group_var, $_REQUEST['groupid'], PROFILE_TYPE_ID);
		CProfile::update($host_var, $_REQUEST['hostid'], PROFILE_TYPE_ID);
	}

/*
 * Function: validate_group
 *
 * Description:
 *     Check available groups by user permisions
 *
 * Author:
 *     Artem "Aly" Suharev
 *
 * Comments:
 *
 */
 	function validate_group(&$PAGE_GROUPS, &$PAGE_HOSTS, $reset_host=true){
		global $page;

		$config = select_config();

		$dd_first_entry = $config['dropdown_first_entry'];

		$group_var = 'web.latest.groupid';
		$host_var = 'web.latest.hostid';

		$_REQUEST['groupid']    = get_request('groupid', CProfile::get($group_var, -1));

		if($_REQUEST['groupid'] < 0){
			$PAGE_GROUPS['selected'] = $_REQUEST['groupid'] = 0;
			$PAGE_HOSTS['selected'] = $_REQUEST['hostid'] = 0;
		}

		if(!isset($_REQUEST['hostid']) || $reset_host){
			$PAGE_HOSTS['selected'] = $_REQUEST['hostid'] = 0;
		}

		if(($PAGE_GROUPS['selected'] == 0) && ($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE)){
			$PAGE_GROUPS['groupids'] = array();
		}

		$PAGE_GROUPS['selected'] = $_REQUEST['groupid'];

		if($PAGE_GROUPS['original'] > -1)
			CProfile::update('web.'.$page['menu'].'.groupid', $_REQUEST['groupid'], PROFILE_TYPE_ID);

		if($PAGE_HOSTS['original'] > -1)
			CProfile::update('web.'.$page['menu'].'.hostid', $_REQUEST['hostid'], PROFILE_TYPE_ID);

		CProfile::update($group_var, $_REQUEST['groupid'], PROFILE_TYPE_ID);
		CProfile::update($host_var, $_REQUEST['hostid'], PROFILE_TYPE_ID);
	}

/* APPLICATIONS */

/*
 * Function: db_save_application
 *
 * Description:
 *     Add or update application
 *
 * Author:
 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 * Comments: !!! Don't forget sync code with C !!!
 *       If applicationid is NULL add application, in other cases update
 */
	function db_save_application($name, $hostid, $applicationid=null, $templateid=0){
		if(!is_string($name)){
			error('Incorrect parameters for "db_save_application"');
			return false;
		}

		$host = get_host_by_hostid($hostid);

		$sql = 'SELECT applicationid, templateid
			FROM applications
			WHERE name='.zbx_dbstr($name).'
				AND hostid='.$hostid;
		if(!is_null($applicationid)){
			$sql .= ' AND applicationid<>'.$applicationid;
		}
		$db_app = DBfetch(DBselect($sql));
		if($db_app && (($templateid == 0) || ($templateid && $db_app['templateid'] && $templateid != $db_app['templateid']))){
			error(S_APPLICATION.SPACE."'$name'".SPACE.S_ALREADY_EXISTS_SMALL);
			return false;
		}

		// delete old application with same name
		if($db_app && !is_null($applicationid)){
			delete_application($db_app['applicationid']);
		}

		// if found application with same name update them, adding not needed
		if($db_app && is_null($applicationid)){
			$applicationid = $db_app['applicationid'];
		}


		if(is_null($applicationid)){
			$applicationid_new = get_dbid('applications', 'applicationid');

			$sql = 'INSERT INTO applications (applicationid, name, hostid, templateid) '.
				" VALUES ($applicationid_new, ".zbx_dbstr($name).", $hostid, $templateid)";
			if($result = DBexecute($sql)){
				info(S_ADDED_NEW_APPLICATION.SPACE.'"'.$host['host'].':'.$name.'"');
			}
		}
		else{
			$old_app = get_application_by_applicationid($applicationid);
			$result = DBexecute('UPDATE applications SET name='.zbx_dbstr($name).', hostid='.$hostid.', templateid='.$templateid.
				' WHERE applicationid='.$applicationid);
			if($result)
				info(S_APPLICATION.SPACE.'"'.$host['host'].':'.$old_app['name'].'"'.SPACE.S_UPDATED_SMALL);
		}

		if(!$result) return $result;

		if(is_null($applicationid)){// create application for childs
			$applicationid = $applicationid_new;

			$db_childs = get_hosts_by_templateid($hostid);
			while($db_child = DBfetch($db_childs)){// recursion
				$result = add_application($name, $db_child['hostid'], $applicationid);
				if(!$result) break;
			}
		}
		else{
			$db_applications = get_applications_by_templateid($applicationid);
			while($db_app = DBfetch($db_applications)){// recursion
				$result = update_application($db_app['applicationid'], $name, $db_app['hostid'], $applicationid);
				if(!$result) break;
			}
		}

		if($result)
			return $applicationid;

		if($templateid == 0){
			delete_application($applicationid);
		}
		return false;

	}

/*
 * Function: add_application
 *
 * Description:
 *     Add application
 *
 * Author:
 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 */
	function add_application($name,$hostid,$templateid=0){
		return db_save_application($name,$hostid,null,$templateid);
	}

/*
 * Function: update_application
 *
 * Description:
 *     Update application
 *
 * Author:
 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 */
	function update_application($applicationid,$name,$hostid,$templateid=0){
		return db_save_application($name,$hostid,$applicationid,$templateid);
	}

	function delete_application($applicationids){
		$applicationids = zbx_toHash($applicationids);

		$apps = array();
		$sql = 'SELECT a.applicationid, h.host, a.name, a.templateid '.
				' FROM applications a, hosts h '.
				' WHERE '.DBcondition('a.applicationid',$applicationids).
					' AND h.hostid=a.hostid';
		$res = DBselect($sql);
		while($db_app = DBfetch($res)){
			$apps[$db_app['applicationid']] = $db_app;
		}


// first delete child applications
		$tmp_appids = array();
		$sql = 'SELECT a.applicationid '.
				' FROM applications a '.
				' WHERE '.DBcondition('a.templateid',$applicationids);
		$db_applications = DBselect($sql);
		while($db_app = DBfetch($db_applications)){
			$tmp_appids[$db_app['applicationid']] = $db_app['applicationid'];
		}

		if(!empty($tmp_appids)){
// recursion!!!
			if(!delete_application($tmp_appids)) return false;
		}


		$unlink_apps = array();
		//check if app is used by web scenario
		$sql = 'SELECT ht.name, ht.applicationid '.
				' FROM httptest ht '.
				' WHERE '.DBcondition('ht.applicationid', $applicationids);
		$res = DBselect($sql);
		while ($info = DBfetch($res)) {
			if ($apps[$info['applicationid']]['templateid'] > 0) {
				$unlink_apps[$info['applicationid']] = $info['applicationid'];
				unset($applicationids[$info['applicationid']]);
			}
			else {
				error(S_APPLICATION.' ['.$apps[$info['applicationid']]['host'].':'.$apps[$info['applicationid']]['name'].'] '.S_USED_IN_WEB_SCENARIO);
				return false;
			}
		}

		$sql = 'SELECT i.itemid, i.key_, i.description, ia.applicationid '.
				' FROM items_applications ia, items i '.
				' WHERE i.type='.ITEM_TYPE_HTTPTEST.
					' AND i.itemid=ia.itemid '.
					' AND '.DBcondition('ia.applicationid', $applicationids);
		$res = DBselect($sql);
		if ($info = DBfetch($res)) {
			error(S_APPLICATION.SPACE.'"'.$apps[$info['applicationid']]['host'].':'.$apps[$info['applicationid']]['name'].'"'.SPACE.S_USED_BY_ITEM_SMALL.' ['.item_description($info).']');
			return false;
		}

		$result = DBexecute('UPDATE applications SET templateid=0 WHERE '.DBcondition('applicationid', $unlink_apps));
		$result &= DBexecute('DELETE FROM items_applications WHERE '.DBcondition('applicationid', $applicationids));
		$result &= DBexecute('DELETE FROM applications WHERE '.DBcondition('applicationid', $applicationids));

		if ($result) {
			foreach ($apps as $appid => $app) {
				if (isset($unlink_apps[$appid])) {
					info(S_APPLICATION.SPACE.'"'.$app['host'].':'.$app['name'].'"'.SPACE.S_USED_IN_WEB_SCENARIO.' ('.S_UNLINKED_SMALL.')');
				}
				else {
					info(S_APPLICATION.SPACE.'"'.$app['host'].':'.$app['name'].'"'.SPACE.S_DELETED_SMALL);
				}
			}
		}

		return $result;
	}

	function get_application_by_applicationid($applicationid,$no_error_message=0){
		$result = DBselect("select * from applications where applicationid=".$applicationid);
		$row=DBfetch($result);
		if($row)
		{
			return $row;
		}
		if($no_error_message == 0)
			error(S_NO_APPLICATION_WITH." id=[$applicationid]");
		return	false;

	}

	function get_applications_by_templateid($applicationid){
		return DBselect("select * from applications where templateid=".$applicationid);
	}

	function get_realhost_by_applicationid($applicationid){
		$application = get_application_by_applicationid($applicationid);
		if($application["templateid"] > 0)
			return get_realhost_by_applicationid($application["templateid"]);

		return get_host_by_applicationid($applicationid);
	}

	function get_host_by_applicationid($applicationid){
		$sql="select h.* from hosts h, applications a where a.hostid=h.hostid and a.applicationid=$applicationid";
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			return $row;
		}
		error(S_NO_HOST_WITH." applicationid=[$applicationid]");
		return	false;
	}

	function get_applications_by_hostid($hostid){
		return DBselect('select * from applications where hostid='.$hostid);
	}

	/*
	 * Function: delete_template_applications
	 *
	 * Description:
	 *     Delete applications from host by templates
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 *           $templateid can be numeric or numeric array
	 *
	 */
	function delete_template_applications($hostid, $templateids = null, $unlink_mode = false){
		zbx_value2array($templateids);

		$db_apps = get_applications_by_hostid($hostid);

		$host = get_host_by_hostid($hostid);

		while ($db_app = DBfetch($db_apps)) {
			if ($db_app["templateid"] == 0) {
				continue;
			}

			// check if application is from right template
			if (!is_null($templateids)) {
				if ($tmp_app_data = get_application_by_applicationid($db_app["templateid"])) {
					if (!uint_in_array($tmp_app_data["hostid"], $templateids)) {
						continue;
					}
				}
			}

			if ($unlink_mode) {
				if (DBexecute("update applications set templateid=0 where applicationid=".$db_app["applicationid"])) {
					info(S_APPLICATION.SPACE.'"'.$host["host"].':'.$db_app["name"].'"'.SPACE.S_UNLINKED_SMALL);
				}
			}
			else {
				delete_application($db_app["applicationid"]);
			}
		}
	}

	/*
	 * Function: copy_template_applications
	 *
	 * Description:
	 *     Copy applications from templates to host
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 *           $templateid can be numeric or numeric array
	 *
	 */
	function copy_template_applications($hostid, $templateid = null, $copy_mode = false){
		if(null == $templateid){
			$templateid = array_keys(get_templates_by_hostid($hostid));
		}

		if(is_array($templateid)){
			foreach($templateid as $id)
				copy_template_applications($hostid, $id, $copy_mode); // attention recursion
			return;
		}

		$db_tmp_applications = get_applications_by_hostid($templateid);

		while($db_tmp_app = DBfetch($db_tmp_applications)){
			add_application(
				$db_tmp_app['name'],
				$hostid,
				$copy_mode?0:$db_tmp_app['applicationid']);
		}
	}

	/*
	 * Function: validate_templates
	 *
	 * Description:
	 *     Check collisions between templates
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *           $templateid_list can be numeric or numeric array
	 *
	 */
	function validate_templates($templateid_list){
		if(is_numeric($templateid_list))return true;
		if(!is_array($templateid_list))	return false;
		if(count($templateid_list)<2)	return true;

		$result = true;

		$sql = 'SELECT key_,count(*) as cnt '.
			' FROM items '.
			' WHERE '.DBcondition('hostid',$templateid_list).
			' GROUP BY key_ '.
			' ORDER BY cnt DESC';
		$res = DBselect($sql);
		while($db_cnt = DBfetch($res)){
			if($db_cnt['cnt']>1){
				$result &= false;
				error(S_TEMPLATE_WITH_ITEM_KEY.SPACE.'['.htmlspecialchars($db_cnt['key_']).']'.SPACE.S_ALREADY_LINKED_TO_HOST_SMALL);
			}
		}


		$sql = 'SELECT name,count(*) as cnt '.
			' FROM applications '.
			' WHERE '.DBcondition('hostid',$templateid_list).
			' GROUP BY name '.
			' ORDER BY cnt DESC';
		$res = DBselect($sql);
		while($db_cnt = DBfetch($res)){
			if($db_cnt['cnt']>1){
				$result &= false;
				error(S_TEMPLATE_WITH_APPLICATION.SPACE.'['.htmlspecialchars($db_cnt['name']).']'.SPACE.S_ALREADY_LINKED_TO_HOST_SMALL);
			}
		}

	return $result;
	}

// Add Host Profile

	function add_host_profile($hostid,$devicetype,$name,$os,$serialno,$tag,$macaddress,$hardware,$software,$contact,$location,$notes){

		$result=DBselect('SELECT * FROM hosts_profiles WHERE hostid='.$hostid);
		if(DBfetch($result)){
			error(S_HOST_PROFILE.SPACE.S_ALREADY_EXISTS);
			return 0;
		}

		$result=DBexecute('INSERT INTO hosts_profiles '.
			' (hostid,devicetype,name,os,serialno,tag,macaddress,hardware,software,contact,location,notes) '.
			' VALUES ('.$hostid.','.zbx_dbstr($devicetype).','.zbx_dbstr($name).','.
			zbx_dbstr($os).','.zbx_dbstr($serialno).','.zbx_dbstr($tag).','.zbx_dbstr($macaddress).
			','.zbx_dbstr($hardware).','.zbx_dbstr($software).','.zbx_dbstr($contact).','.
			zbx_dbstr($location).','.zbx_dbstr($notes).')');

	return	$result;
	}

/*
 * Function: add_host_profile_ext
 *
 * Description:
 *  Add alternate host profile information.
 *
 * Author:
 *	John R Pritchard (john.r.pritchard@gmail.com)
 * 	modified by Aly
 * Comments:
 *  Extend original "add_host_profile" function for new hosts_profiles_ext data.
 *
 */
	function add_host_profile_ext($hostid,$ext_host_profiles=array()){

		$ext_profiles_fields = array('device_alias','device_type','device_chassis','device_os','device_os_short',
			'device_hw_arch','device_serial','device_model','device_tag','device_vendor','device_contract',
			'device_who','device_status','device_app_01','device_app_02','device_app_03','device_app_04',
			'device_app_05','device_url_1','device_url_2','device_url_3','device_networks','device_notes',
			'device_hardware','device_software','ip_subnet_mask','ip_router','ip_macaddress','oob_ip',
			'oob_subnet_mask','oob_router','date_hw_buy','date_hw_install','date_hw_expiry','date_hw_decomm','site_street_1',
			'site_street_2','site_street_3','site_city','site_state','site_country','site_zip','site_rack','site_notes',
			'poc_1_name','poc_1_email','poc_1_phone_1','poc_1_phone_2','poc_1_cell','poc_1_screen','poc_1_notes','poc_2_name',
			'poc_2_email','poc_2_phone_1','poc_2_phone_2','poc_2_cell','poc_2_screen','poc_2_notes');

		$result=DBselect('SELECT * FROM hosts_profiles_ext WHERE hostid='.$hostid);
		if(DBfetch($result)){
			error(S_HOST_PROFILE.SPACE.S_ALREADY_EXISTS);
			return false;
		}

		$sql = 'INSERT INTO hosts_profiles_ext (hostid,';
		$values = ' VALUES ('.$hostid.',';

		foreach($ext_host_profiles as $field => $value){
			if(str_in_array($field,$ext_profiles_fields)){
				$sql.=$field.',';
				$values.=zbx_dbstr($value).',';
			}
		}

		$sql = rtrim($sql,',').')';
		$values = rtrim($values,',').')';

		$result=DBexecute($sql.$values);
	return  $result;
	}


// Delete Host Profile
	function delete_host_profile($hostids){
		zbx_value2array($hostids);
		$result=DBexecute('DELETE FROM hosts_profiles WHERE '.DBcondition('hostid',$hostids));

	return $result;
	}

/*
 * Function: delete_host_profile_ext
 *
 * Description:
 *     Delete alternate host profile information.
 *
 * Author:
 *     John R Pritchard (john.r.pritchard@gmail.com)
 *
 * Comments:
 *     Extend original "delete_host_profile" function for new hosts_profiles_ext data.
 *
 */
	function delete_host_profile_ext($hostids){
		zbx_value2array($hostids);
		$result=DBexecute('DELETE FROM hosts_profiles_ext WHERE '.DBcondition('hostid',$hostids));

	return $result;
	}

	/**
	 * Get host ids of hosts which $groupids can be unlinked from.
	 * if $hostids is passed, function will check only these hosts.
	 *
	 * @param array $groupids
	 * @param array $hostids
	 *
	 * @return array
	 */
	function getUnlinkableHosts($groupids, $hostids = null) {
		zbx_value2array($groupids);
		zbx_value2array($hostids);

		$unlinkableHostIds = array();

		$sql_where = '';
		if ($hostids !== null) {
			$sql_where = ' AND '.DBcondition('hg.hostid', $hostids);
		}

		$result = DBselect(
				'SELECT hg.hostid,COUNT(hg.groupid) AS grp_count'.
				' FROM hosts_groups hg'.
				' WHERE '.DBcondition('hg.groupid', $groupids, true).
				$sql_where.
				' GROUP BY hg.hostid'.
				' HAVING COUNT(hg.groupid)>0'
		);
		while ($row = DBfetch($result)) {
			$unlinkableHostIds[] = $row['hostid'];
		}

		return $unlinkableHostIds;
	}

	function getDeletableHostGroups($groupids=null){
		zbx_value2array($groupids);

		$dlt_groupids = array();
		$hostids = getUnlinkableHosts($groupids);

		$sql_where = '';
		if(!is_null($groupids)){
			$sql_where.= ' AND '.DBcondition('g.groupid', $groupids);
		}

		$sql = 'SELECT DISTINCT g.groupid '.
				' FROM groups g '.
				' WHERE g.internal='.ZBX_NOT_INTERNAL_GROUP.
					$sql_where.
					' AND NOT EXISTS ('.
						'SELECT hg.groupid '.
						' FROM hosts_groups hg '.
						' WHERE g.groupid=hg.groupid '.
							' AND '.DBcondition('hg.hostid', $hostids, true).
						')';
		$res = DBselect($sql);
		while($group = DBfetch($res)){
			$dlt_groupids[$group['groupid']] = $group['groupid'];
		}

	return $dlt_groupids;
	}

?>
