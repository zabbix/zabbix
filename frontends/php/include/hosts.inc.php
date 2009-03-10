<?php
/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
require_once "include/graphs.inc.php";
require_once "include/triggers.inc.php";
require_once "include/items.inc.php";
require_once "include/httptest.inc.php";

/* HOST GROUP functions */
	function add_host_to_group($hostid, $groupid){
		if(!is_numeric($hostid) || !is_numeric($groupid)){
			error("incorrect parameters for 'add_host_to_group' [hostid:".$hostid."][groupid:".$groupid."]");
			return false;
		}
		$hostgroupid=get_dbid("hosts_groups","hostgroupid");
		$result=DBexecute("insert into hosts_groups (hostgroupid,hostid,groupid) values ($hostgroupid,$hostid,$groupid)");
		if(!$result)
			return $result;
		return $hostgroupid;
	}

	function delete_host_from_group($hostid, $groupid){
		if(!is_numeric($hostid) || !is_numeric($groupid)){
			error("incorrect parameters for 'add_host_to_group' [hostid:".$hostid."][groupid:".$groupid."]");
			return false;
		}
		return DBexecute('delete from hosts_groups where hostid='.$hostid.' and groupid='.$groupid);
	}

	/*
	 * Function: db_save_group
	 *
	 * Description:
	 *     Add new or update host group
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function db_save_group($name,$groupid=null){
		if(!is_string($name)){
			error("incorrect parameters for 'db_save_group'");
			return false;
		}
	
		if(is_null($groupid))
			$result = DBselect("select * from groups where ".DBin_node('groupid')." AND name=".zbx_dbstr($name));
		else
			$result = DBselect("select * from groups where ".DBin_node('groupid')." AND name=".zbx_dbstr($name).
				" and groupid<>$groupid");
		
		if(DBfetch($result)){
			error("Group '$name' already exists");
			return false;
		}
		
		if(is_null($groupid)){
			$groupid=get_dbid("groups","groupid");
			if(!DBexecute("insert into groups (groupid,name) values (".$groupid.",".zbx_dbstr($name).")"))
				return false;
			return $groupid;

		}
		else
			return DBexecute("update groups set name=".zbx_dbstr($name)." where groupid=$groupid");
	}

	function add_group_to_host($hostid,$newgroup=''){
		if(zbx_empty($newgroup))
			 return true;

		$groupid = db_save_group($newgroup);
		if(!$groupid)
			return	$groupid;
		
		return add_host_to_group($hostid, $groupid);
	}

	function update_host_groups_by_groupid($groupid,$hosts=array()){
		DBexecute("delete from hosts_groups where groupid=$groupid");

		foreach($hosts as $hostid){
			add_host_to_group($hostid, $groupid);
		}
	}

	function update_host_groups($hostid,$groups=array()){
		DBexecute("delete from hosts_groups where hostid=$hostid");

		foreach($groups as $groupid){
			add_host_to_group($hostid, $groupid);
		}
	}

	function add_host_group($name,$hosts=array()){
		$groupid = db_save_group($name);
		if(!$groupid)
			return	$groupid;
		
		update_host_groups_by_groupid($groupid,$hosts);

		return $groupid;
	}

	function update_host_group($groupid,$name,$hosts){
		$result = db_save_group($name,$groupid);
		if(!$result)
			return	$result;
		
		update_host_groups_by_groupid($groupid,$hosts);

		return $result;
	}

	/*
	 * Function: check_circle_host_link
	 *
	 * Description:
	 *     Check templates linage circeling
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
	 * Function: db_save_host
	 *
	 * Description:
	 *     Add or update host
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *     if hostid is NULL add new host, in other cases update
	 *
	 *     NOTE: templates = array(id => name, id2 => name2, ...)
	 */
	function db_save_host($host,$port,$status,$useip,$dns,$ip,$proxy_hostid,$templates,$useipmi,$ipmi_ip,$ipmi_port,$ipmi_authtype,$ipmi_privilege,$ipmi_username,$ipmi_password,$hostid=null)
	{
		if(!eregi('^'.ZBX_EREG_HOST_FORMAT.'$', $host)){
			error("Incorrect characters used for Hostname");
			return false;
		}

 		if(!empty($dns) && !eregi('^'.ZBX_EREG_DNS_FORMAT.'$', $dns)){
			error("Incorrect characters used for DNS");
			return false;
		}


		if(DBfetch(DBselect('SELECT h.host '.
				' FROM hosts h '.
				' WHERE h.host='.zbx_dbstr($host).
					' AND '.DBin_node('h.hostid', get_current_nodeid(false)).
					' AND status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')'.
					(isset($hostid)?' AND h.hostid<>'.$hostid:'')
			)))
		{
			error("Host '$host' already exists");
			return false;
		}

		if ($useipmi == 'yes')
		{
			if ($useip)
				$dns = $ipmi_ip;
			else
				$ip = $ipmi_ip;
		}

		if(is_null($hostid)){
			$hostid = get_dbid("hosts","hostid");
			$result = DBexecute('insert into hosts '.
				' (hostid,proxy_hostid,host,port,status,useip,dns,ip,disable_until,available,useipmi,ipmi_port,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password) '.
				' values ('.$hostid.','.$proxy_hostid.','.zbx_dbstr($host).','.$port.','.$status.','.$useip.','.zbx_dbstr($dns).','.zbx_dbstr($ip).',0,'
					.HOST_AVAILABLE_UNKNOWN.','.($useipmi == 'yes' ? 1 : 0).','.$ipmi_port.','.$ipmi_authtype.','.$ipmi_privilege.','.zbx_dbstr($ipmi_username).','
					.zbx_dbstr($ipmi_password).')');
		}
		else{
			if(check_circle_host_link($hostid, $templates)){
				error("Circle link can't be created");
				return false;
			}
			$result = DBexecute('UPDATE hosts SET proxy_hostid='.$proxy_hostid.
							',host='.zbx_dbstr($host).
							',port='.$port.
							',status='.$status.
							',useip='.$useip.
							',dns='.zbx_dbstr($dns).
							',ip='.zbx_dbstr($ip).
							',useipmi='.($useipmi == 'yes' ? 1 : 0).
							',ipmi_port='.$ipmi_port.
							',ipmi_authtype='.$ipmi_authtype.
							',ipmi_privilege='.$ipmi_privilege.
							',ipmi_username='.zbx_dbstr($ipmi_username).
							',ipmi_password='.zbx_dbstr($ipmi_password).
				' WHERE hostid='.$hostid);

			update_host_status($hostid, $status);
		}
		
		foreach($templates as $id => $name){
			$hosttemplateid = get_dbid('hosts_templates', 'hosttemplateid');
			if(!($result = DBexecute('insert into hosts_templates values ('.$hosttemplateid.','.$hostid.','.$id.')')))
				break;
		}

		if($result) $result = $hostid;

		return $result;
	}

/*
 * Function: add_host
 *
 * Description:
 *     Add new  host
 *
 * Author:
 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 * Comments:
 *
 *     NOTE: templates = array(id => name, id2 => name2, ...)
 */
	function add_host($host,$port,$status,$useip,$dns,$ip,$proxy_hostid,$templates,$useipmi,$ipmi_ip,$ipmi_port,$ipmi_authtype,$ipmi_privilege,$ipmi_username,$ipmi_password,$newgroup,$groups)
	{
		$hostid = db_save_host($host,$port,$status,$useip,$dns,$ip,$proxy_hostid,$templates,$useipmi,$ipmi_ip,$ipmi_port,$ipmi_authtype,$ipmi_privilege,$ipmi_username,$ipmi_password);
		if(!$hostid)
			return $hostid;
		else
			info('Added new host ['.$host.']');

		update_host_groups($hostid,$groups);

		add_group_to_host($hostid,$newgroup);

		sync_host_with_templates($hostid);
		
		return	$hostid;
	}

	/*
	 * Function: update_host
	 *
	 * Description:
	 *     Update host
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 *     NOTE: templates = array(id => name, id2 => name2, ...)
	 */	
	function update_host($hostid,$host,$port,$status,$useip,$dns,$ip,$proxy_hostid,$templates,$useipmi,$ipmi_ip,$ipmi_port,$ipmi_authtype,$ipmi_privilege,$ipmi_username,$ipmi_password,$newgroup,$groups)
	{
	
		$old_templates = get_templates_by_hostid($hostid);
		$unlinked_templates = array_diff($old_templates, $templates);
		
		foreach($unlinked_templates as $id => $name){
			unlink_template($hostid, $id);
		}
		
		$old_host = get_host_by_hostid($hostid);

		$new_templates = array_diff($templates, $old_templates);

		$result = (bool) db_save_host($host,$port,$status,$useip,$dns,$ip,$proxy_hostid,$new_templates,$useipmi,$ipmi_ip,$ipmi_port,$ipmi_authtype,$ipmi_privilege,$ipmi_username,$ipmi_password,$hostid);
		if(!$result)
			return $result;

		update_host_groups($hostid, $groups);
		add_group_to_host($hostid,$newgroup);

		if(count($new_templates) > 0){
			sync_host_with_templates($hostid,array_keys($new_templates));
		}

	return	$result;
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

	/*
	 * Function: copy_template_elements 
	 *
	 * Description:
	 *     Copy all elements from template to host
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function copy_template_elements($hostid, $templateid = null, $copy_mode = false){
		copy_template_applications($hostid, $templateid, $copy_mode);
		copy_template_items($hostid, $templateid, $copy_mode);
		copy_template_triggers($hostid, $templateid, $copy_mode);
		copy_template_graphs($hostid, $templateid, $copy_mode);
	}

	/*
	 * Function: sync_host_with_templates
	 *
	 * Description:
	 *     Synchronize template elements with host
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function sync_host_with_templates($hostid, $templateid = null){
		delete_template_elements($hostid, $templateid);		
		copy_template_elements($hostid, $templateid);
	}

	function delete_groups_by_hostid($hostid){
		$sql="select groupid from hosts_groups where hostid=$hostid";
		$result=DBselect($sql);
		while($row=DBfetch($result)){
		
			$sql="delete from hosts_groups where hostid=$hostid and groupid=".$row["groupid"];
			DBexecute($sql);
			
			$sql="select count(*) as count from hosts_groups where groupid=".$row["groupid"];
			$result2=DBselect($sql);
			$row2=DBfetch($result2);
			if($row2["count"]==0){
				delete_host_group($row["groupid"]);
			}
		}
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
		
		$ret = false;		
// unlink child hosts
		$db_childs = get_hosts_by_templateid($hostids);
		while($db_child = DBfetch($db_childs)){
			unlink_template($db_child['hostid'], $hostids, $unlink_mode);
		}

// delete items -> triggers -> graphs
		$del_items = array();
		$db_items = get_items_by_hostid($hostids);
		while($db_item = DBfetch($db_items)){
			$del_items[$db_item['itemid']] = $db_item['itemid'];
		}
		if(!empty($del_items)){
			delete_item($del_items);
		}

// delete host from maps
		delete_sysmaps_elements_with_hostid($hostids);
		
// delete host from group
		DBexecute('DELETE FROM hosts_groups WHERE '.DBcondition('hostid',$hostids));

// delete host from template linkages
		DBexecute('DELETE FROM hosts_templates WHERE '.DBcondition('hostid',$hostids));

// delete host applications
		DBexecute('DELETE FROM applications WHERE '.DBcondition('hostid',$hostids));
		
// disable actions
		$actionids = array();
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


// delete action conditions
		DBexecute('DELETE FROM conditions '.
					' WHERE conditiontype='.CONDITION_TYPE_HOST.
						' AND '.DBcondition('value',$hostids, false, true)); 	// FIXED[POSIBLE value type violation]!!!

// delete host profile
		delete_host_profile($hostids);
		delete_host_profile_ext($hostids);
		
// delete web tests
		$del_httptests = array();
		$db_httptests = get_httptests_by_hostid($hostids);
		while($db_httptest = DBfetch($db_httptests)){
			$del_httptests[$db_httptest['httptestid']] = $db_httptest['httptestid'];
		}
		if(!empty($del_httptests)){
			delete_httptest($del_httptests);
		}

// delete host
	return DBexecute('DELETE FROM hosts WHERE '.DBcondition('hostid',$hostids));
	}

	function delete_host_group($groupids){
		zbx_value2array($groupids);
		
		if(!delete_sysmaps_elements_with_groupid($groupids))
			return false;
		
// disable actions
		$actionids = array();
		$sql = 'SELECT DISTINCT actionid '.
				' FROM conditions '.
				' WHERE conditiontype='.CONDITION_TYPE_HOST_GROUP.
					' AND '.DBcondition('value',$groupids, false, true);		// FIXED[POSIBLE value type violation]!!!
		$db_actions = DBselect($sql);
		while($db_action = DBfetch($db_actions)){
			$actionids[$db_action['actionid']] = $db_action['actionid'];
		}

		DBexecute('UPDATE actions '.
					' SET status='.ACTION_STATUS_DISABLED.
					' WHERE '.DBcondition('actionid',$actionids));

// delete action conditions
		DBexecute('DELETE FROM conditions '.
					' WHERE conditiontype='.CONDITION_TYPE_HOST_GROUP.
						' AND '.DBcondition('value',$groupids, false, true));		// FIXED[POSIBLE value type violation]!!!
						
		if(!DBexecute('DELETE FROM hosts_groups WHERE '.DBcondition('groupid',$groupids)))
			return false;

	return DBexecute('DELETE FROM groups WHERE '.DBcondition('groupid',$groupids));
	}

	function get_hostgroup_by_groupid($groupid){
		$result=DBselect("select * from groups where groupid=".$groupid);
		$row=DBfetch($result);
		if($row){
			return $row;
		}
		error("No host groups with groupid=[$groupid]");
		return  false;
	}
	
	function get_groupids_by_host($hostid){
		$groupids = array();
		
		$result=DBselect('SELECT DISTINCT hg.groupid '.
						' FROM hosts_groups hg '.
						' WHERE hg.hostid='.$hostid);
		while($row=DBfetch($result)){
			$groupids[$row['groupid']] = $row['groupid'];
		}
		
	return $groupids;
	}

	function db_save_proxy($name,$proxyid=null){
		if(!is_string($name)){
			error("incorrect parameters for 'db_save_proxy'");
			return false;
		}
	
		if(is_null($proxyid))
			$result = DBselect('SELECT * FROM hosts WHERE status IN ('.HOST_STATUS_PROXY.')'.
					' and '.DBin_node('hostid').' AND host='.zbx_dbstr($name));
		else
			$result = DBselect('SELECT * FROM hosts WHERE status IN ('.HOST_STATUS_PROXY.')'.
					' and '.DBin_node('hostid').' AND host='.zbx_dbstr($name).
					' and hostid<>'.$proxyid);
		
		if(DBfetch($result)){
			error("Proxy '$name' already exists");
			return false;
		}
		
		if(is_null($proxyid)){
			$proxyid=get_dbid('hosts','hostid');
			if(!DBexecute('INSERT INTO hosts (hostid,host,status)'.
				' values ('.$proxyid.','.zbx_dbstr($name).','.HOST_STATUS_PROXY.')'))
			{
				return false;
			}
			
			return $proxyid;
		}
		else
			return DBexecute('update hosts set host='.zbx_dbstr($name).' where hostid='.$proxyid);
	}

	function delete_proxy($proxyids){
		zbx_value2array($proxyids);
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

	function add_proxy($name,$hosts=array()){
		$proxyid = db_save_proxy($name);
		if(!$proxyid)
			return	$proxyid;

		update_hosts_by_proxyid($proxyid,$hosts);

		return $proxyid;
	}

	function update_proxy($proxyid,$name,$hosts){
		$result = db_save_proxy($name,$proxyid);
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
		
		$sql = 'SELECT i.itemid, h.* FROM hosts h, items i WHERE i.hostid=h.hostid AND '.DBcondition('i.itemid',$itemids);

		$res=DBselect($sql);
		while($row=DBfetch($res)){
			if ($row['useipmi'] == 1)
				$row['ipmi_ip'] = $row['useip'] ? $row['dns'] : $row['ip'];
			else
				$row['ipmi_ip'] = '';

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
			if ($row['useipmi'] == 1)
				$row['ipmi_ip'] = $row['useip'] ? $row['dns'] : $row['ip'];
			else
				$row['ipmi_ip'] = '';

			return $row;
		}

		if($no_error_message == 0)
			error("No host with hostid=[$hostid]");
		return	false;
	}

	function get_hosts_by_templateid($templateids){
		zbx_value2array($templateids);
		return DBselect('SELECT h.* '.
						' FROM hosts h, hosts_templates ht '.
						' WHERE h.hostid=ht.hostid '.
							' AND '.DBcondition('ht.templateid',$templateids));
	}

// Update Host status

	function update_host_status($hostids,$status){
		zbx_value2array($hostids);
		
		$hosts = array();
		$result = DBselect('SELECT host, hostid, status FROM hosts WHERE '.DBcondition('hostid',$hostids));
		while($host=DBfetch($result)){
			if($status != $host['status']){
				$hosts[$host['hostid']] = $host['hostid'];
				info('Updated status of host '.$host['host']);
			}
		}

		if(!empty($hosts)){
			update_trigger_value_to_unknown_by_hostid($hosts);

			return	DBexecute('UPDATE hosts SET status='.$status.
							' WHERE '.DBcondition('hostid',$hosts).
								' AND status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'
						);
		}
		else{
			return 1;
		}
	}
	
/*
 * Function: get_templates_by_hostid
 *
 * Description:
 *     Retrive templates for specified host
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
 *     Retrive groups for dropdown
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
				'from' =>	array('groups g'),
				'where' =>	array(),
			);
			
	$def_options = array(
				'allow_all' =>					0,
				'select_first_group'=>			0,
				'select_first_group_if_empty'=>	0,
				'do_not_select' =>				0,
				'do_not_select_if_empty' =>		0,
				'monitored_hosts' =>			0,
				'templated_hosts' =>			0,
				'real_hosts' =>					0,
				'not_proxy_hosts' =>			0,
				'with_items' =>					0,
				'with_monitored_items' =>		0,
				'with_triggers' =>				0,
				'with_monitored_triggers'=>		0,
				'with_httptests' =>				0,
				'with_monitored_httptests'=>	0,
				'with_graphs'=>					0,
				'only_current_node' =>			0,
			);	
	$def_options = array_merge($def_options, $options);
	
	$dd_first_entry = ZBX_DROPDOWN_FIRST_ENTRY;
//	if($page['menu'] == 'config') $dd_first_entry = ZBX_DROPDOWN_FIRST_NONE;
	if($def_options['allow_all']) $dd_first_entry = ZBX_DROPDOWN_FIRST_ALL;

	$result = array('original'=> -1, 'selected'=>0, 'groups'=> array(), 'groupids'=> array());
	$groups = &$result['groups'];
	$groupids = &$result['groupids'];
	
	$first_entry = ($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE)?S_NOT_SELECTED_SMALL:S_ALL_SMALL;
	$groups['0'] = $first_entry;
	$groupids['0'] = '0';
	
	$_REQUEST['groupid'] = $result['original'] = get_request('groupid', -1);
	$_REQUEST['hostid'] = get_request('hostid', -1);
//-----
	
	$nodeid = is_null($nodeid)?get_current_nodeid(!$def_options['only_current_node']):$nodeid;
	$available_groups = get_accessible_groups_by_user($USER_DETAILS,$perm,PERM_RES_IDS_ARRAY,$nodeid,AVAILABLE_NOCACHE);

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
	
// htptests	
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
		$def_sql['where'][] = 'EXISTS( SELECT a.applicationid '.
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
	foreach($sql as $key => $value){
		zbx_value2array($value);

		if(isset($def_sql[$key])) $def_sql[$key] = array_merge($def_sql[$key], $value);
		else $def_sql[$key] = $value;
	}
	
	$def_sql['from'] = array_unique($def_sql['from']);
	$def_sql['where'] = array_unique($def_sql['where']);

	$sql_from = '';
	$sql_where = '';
	if(!empty($def_sql['from'])) $sql_from.= implode(',',$def_sql['from']);
	if(!empty($def_sql['where'])) $sql_where.= ' AND '.implode(' AND ',$def_sql['where']);

	$sql = 'SELECT DISTINCT g.groupid,g.name '.
			' FROM '.$sql_from.
			' WHERE '.DBcondition('g.groupid',$available_groups).
				$sql_where.
			' ORDER BY g.name';
//SDI($sql);
	$res = DBselect($sql);
	while($group = DBfetch($res)){
		$groups[$group['groupid']] = $group['name'];
		$groupids[$group['groupid']] = $group['groupid'];
		
		if(bccomp($_REQUEST['groupid'],$group['groupid']) == 0) $result['selected'] = $group['groupid'];
	}

//-----
	if($def_options['do_not_select']){
		$result['selected'] = $_REQUEST['groupid'] = 0;
	}
	else if($def_options['do_not_select_if_empty'] && ($_REQUEST['groupid'] == -1)){
		$result['selected'] = $_REQUEST['groupid'] = 0;
	}
	else if(($def_options['select_first_group']) || 
			($def_options['select_first_group_if_empty'] && ($_REQUEST['groupid'] == -1)))
	{
		$first_groupid = next($groupids);
		reset($groupids);
		
		if($first_groupid !== FALSE)
			$_REQUEST['groupid'] = $result['selected'] = $first_groupid;
		else 
			$_REQUEST['groupid'] = $result['selected'] = 0;
	}
	else{
		if(ZBX_DROPDOWN_FIRST_REMEMBER){
			if($_REQUEST['groupid'] == -1) $_REQUEST['groupid'] = get_profile('web.'.$page['menu'].'.groupid', '0', PROFILE_TYPE_ID);
			if(uint_in_array($_REQUEST['groupid'], $groupids)){
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
 *     Retrive groups for dropdown
 *
 * Author:
 *		Artem "Aly" Suharev
 *
 * Comments:
 *	
 */
function get_viewed_hosts($perm, $groupid=0, $options=array(), $nodeid=null, $sql=array('monitored_hosts'=>1)){
	global $USER_DETAILS;
	global $page;

	$def_sql = array(
				'from' =>	array('hosts h'),
				'where' =>	array(),
			);

	$def_options = array(
				'allow_all' =>					0,
				'select_first_host'=>			0,
				'select_first_host_if_empty'=>	0,
				'select_host_on_group_switch'=>	0,
				'do_not_select' =>				0,
				'do_not_select_if_empty' =>		0,
				'monitored_hosts' =>			0,
				'templated_hosts' =>			0,
				'real_hosts' =>					0,
				'not_proxy_hosts' =>			0,
				'with_items' =>					0,
				'with_monitored_items' =>		0,
				'with_triggers' =>				0,
				'with_monitored_triggers'=>		0,
				'with_httptests' =>				0,
				'with_monitored_httptests'=>	0,
				'with_graphs'=>					0,
				'only_current_node' =>			0,
			);
			
	$def_options = array_merge($def_options, $options);

	$dd_first_entry = ZBX_DROPDOWN_FIRST_ENTRY;
	if($def_options['allow_all']) $dd_first_entry = ZBX_DROPDOWN_FIRST_ALL;
	if($dd_first_entry == ZBX_DROPDOWN_FIRST_ALL) $def_options['select_host_on_group_switch'] = 1;

	$result = array('original'=> -1, 'selected'=>0, 'hosts'=> array(), 'hostids'=> array());
	$hosts = &$result['hosts'];
	$hostids = &$result['hostids'];
	
	$first_entry = ($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE)?S_NOT_SELECTED_SMALL:S_ALL_SMALL;
	$hosts['0'] = $first_entry;
	$hostids['0'] = '0';
	
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
	
	$nodeid = is_null($nodeid)?get_current_nodeid(!$def_options['only_current_node']):$nodeid;
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,$perm,PERM_RES_IDS_ARRAY,$nodeid,AVAILABLE_NOCACHE);
	
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

	foreach($sql as $key => $value){
		zbx_value2array($value);

		if(isset($def_sql[$key])) $def_sql[$key] = array_merge($def_sql[$key], $value);
		else $def_sql[$key] = $value;
	}
	
	$def_sql['from'] = array_unique($def_sql['from']);
	$def_sql['where'] = array_unique($def_sql['where']);

	$sql_from = '';
	$sql_where = '';
	if(!empty($def_sql['from'])) $sql_from.= implode(',',$def_sql['from']);
	if(!empty($def_sql['where'])) $sql_where.= ' AND '.implode(' AND ',$def_sql['where']);
	
	$sql = 'SELECT DISTINCT h.hostid, h.host '.
			' FROM '.$sql_from.
			' WHERE '.DBcondition('h.hostid',$available_hosts).
				$sql_where.
			' ORDER BY h.host';	
	$res = DBselect($sql);
	while($host = DBfetch($res)){
		$hosts[$host['hostid']] = $host['host'];
		$hostids[$host['hostid']] = $host['hostid'];
		
		if(bccomp($_REQUEST['hostid'],$host['hostid']) == 0) $result['selected'] = $host['hostid'];
	}

//-----	
	if($def_options['do_not_select']){
		$_REQUEST['hostid'] = $result['selected'] = 0;	
	}
	else if($def_options['do_not_select_if_empty'] && ($_REQUEST['hostid'] == -1)){
		$_REQUEST['hostid'] = $result['selected'] = 0;
	}
	else if(($def_options['select_first_host']) || 
			($def_options['select_first_host_if_empty'] && ($_REQUEST['hostid'] == -1)) || 
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
		if(ZBX_DROPDOWN_FIRST_REMEMBER){
			if($_REQUEST['hostid'] == -1) $_REQUEST['hostid'] = get_profile('web.'.$page['menu'].'.hostid', '0', PROFILE_TYPE_ID);
			if(uint_in_array($_REQUEST['hostid'], $hostids)){
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
		
		$dd_first_entry = ZBX_DROPDOWN_FIRST_ENTRY;
//		if($page['menu'] == 'config') $dd_first_entry = ZBX_DROPDOWN_FIRST_NONE;
//		if(($PAGE_GROUPS['selected'] == 0) && (ZBX_DROPDOWN_FIRST_ENTRY == ZBX_DROPDOWN_FIRST_ALL)) $dd_first_entry = ZBX_DROPDOWN_FIRST_ALL;

		$group_var = 'web.latest.groupid';
		$host_var = 'web.latest.hostid';

		$_REQUEST['groupid']    = get_request('groupid', get_profile($group_var, -1));
		$_REQUEST['hostid']     = get_request('hostid', get_profile($host_var, -1));
		
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
		
		if(($PAGE_HOSTS['selected'] == 0) && ($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE) && $reset_host){
			$PAGE_HOSTS['hostids'] = array(0);
		}
		
		if($PAGE_GROUPS['original'] > -1)
			update_profile('web.'.$page['menu'].'.groupid', $_REQUEST['groupid'], PROFILE_TYPE_ID);
			
		if($PAGE_HOSTS['original'] > -1)
			update_profile('web.'.$page['menu'].'.hostid', $_REQUEST['hostid'], PROFILE_TYPE_ID);

		update_profile($group_var, $_REQUEST['groupid'], PROFILE_TYPE_ID);
		update_profile($host_var, $_REQUEST['hostid'], PROFILE_TYPE_ID);
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
		$group_var = 'web.latest.groupid';
		$host_var = 'web.latest.hostid';
		
		$_REQUEST['groupid']    = get_request('groupid', get_profile($group_var, -1));
		
		if($_REQUEST['groupid'] < 0){
			$PAGE_HOSTS['selected'] = $_REQUEST['groupid'] = 0;
			$PAGE_HOSTS['selected'] = $_REQUEST['hostid'] = 0;
		}
		
		if(!isset($_REQUEST['hostid']) || $reset_host){
			$PAGE_HOSTS['selected'] = $_REQUEST['hostid'] = 0;
		}
		
		$PAGE_GROUPS['selected'] = $_REQUEST['groupid'];

		if($PAGE_GROUPS['original'] > -1)
			update_profile('web.'.$page['menu'].'.groupid', $_REQUEST['groupid'], PROFILE_TYPE_ID);
			
		if($PAGE_HOSTS['original'] > -1)
			update_profile('web.'.$page['menu'].'.hostid', $_REQUEST['hostid'], PROFILE_TYPE_ID);

		update_profile($group_var, $_REQUEST['groupid'], PROFILE_TYPE_ID);
		update_profile($host_var, $_REQUEST['hostid'], PROFILE_TYPE_ID);
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
	function db_save_application($name,$hostid,$applicationid=null,$templateid=0){
		if(!is_string($name)){
			error("Incorrect parameters for 'db_save_application'");
			return false;
		}
	
		if(is_null($applicationid))
			$result = DBselect('SELECT * FROM applications WHERE name='.zbx_dbstr($name).' AND hostid='.$hostid);
		else
			$result = DBselect('SELECT * '.
						' FROM applications '.
						' WHERE name='.zbx_dbstr($name).
							' AND hostid='.$hostid.
							' AND applicationid<>'.$applicationid);

		$db_app = DBfetch($result);
		if($db_app && $templateid==0){
			error('Application "'.$name.'" already exists');
			return false;
		}
		
		if($db_app && $applicationid!=null){ // delete old application with same name
			delete_application($db_app["applicationid"]);
		}

		if($db_app && $applicationid==null){ // if found application with same name update them, adding not needed
			$applicationid = $db_app["applicationid"];
		}

		$host = get_host_by_hostid($hostid);
		
		if(is_null($applicationid)){
			$applicationid_new = get_dbid('applications','applicationid');
			
			$sql = 'INSERT INTO applications (applicationid,name,hostid,templateid) '.
					" VALUES ($applicationid_new,".zbx_dbstr($name).",$hostid,$templateid)";
			if($result = DBexecute($sql)){
				info("Added new application ".$host["host"].":$name");
			}
		}
		else{
			$old_app = get_application_by_applicationid($applicationid);
			if($result = DBexecute('UPDATE applications '.
								' SET name='.zbx_dbstr($name).',hostid='.$hostid.',templateid='.$templateid.
                                ' WHERE applicationid='.$applicationid))
					info("Updated application ".$host["host"].":".$old_app["name"]);
		}

		if(!$result)	return $result;

		if(is_null($applicationid)){// create application for childs
			$applicationid = $applicationid_new;

			$db_childs = get_hosts_by_templateid($hostid);
			while($db_child = DBfetch($db_childs)){// recursion
				$result = add_application($name,$db_child["hostid"],$applicationid);
				if(!$result) break;
			}
		}
		else{
			$db_applications = get_applications_by_templateid($applicationid);
			while($db_app = DBfetch($db_applications)){// recursion
				$result = update_application($db_app["applicationid"],$name,$db_app["hostid"],$applicationid);
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
	
	/*
	 * Function: delete_application
	 *
	 * Description:
	 *     Delete application with all linkages
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function delete_application($applicationid){
		$app = get_application_by_applicationid($applicationid);
		$host = get_host_by_hostid($app["hostid"]);

		// first delete child applications
		$db_applications = DBselect("select applicationid from applications where templateid=$applicationid");
		while($db_app = DBfetch($db_applications))
		{// recursion
			$result = delete_application($db_app["applicationid"]);
			if(!$result)	return	$result;
		}

		if($info = DBfetch(DBselect('select name from httptest where applicationid='.$applicationid)))
		{
			info("Application '".$host["host"].":".$app["name"]."' used by scenario '".$info['name']."'");
			return false;
		}
 
		if($info = DBfetch(DBselect('select i.itemid,i.key_,i.description from items_applications ia, items i '.
			' where i.type='.ITEM_TYPE_HTTPTEST.' and i.itemid=ia.itemid and ia.applicationid='.$applicationid)))
		{
			info("Application '".$host["host"].":".$app["name"]."' used by item '".
				item_description($info)."'");
			return false;
		}

		$result = DBexecute("delete from items_applications where applicationid=$applicationid");

		$result = DBexecute("delete from applications where applicationid=$applicationid");
		if($result)
		{
			info("Application '".$host["host"].":".$app["name"]."' deleted");
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
			error("No application with id=[$applicationid]");
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
		error("No host with applicationid=[$applicationid]");
		return	false;
	}

	function get_items_by_applicationid($applicationid){
		return DBselect("select i.* from items i,items_applications ia where i.itemid=ia.itemid and ia.applicationid=$applicationid");
	}

	function get_applications_by_hostid($hostid){
		return DBselect('select * from applications where hostid='.$hostid);
	}

	/*
	 * Function: delete_template_applications
	 *
	 * Description:
	 *     Delete applicatios from host by templates
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
		while($db_app = DBfetch($db_apps)){
			if($db_app["templateid"] == 0)
				continue;

			if(!is_null($templateids)){

				unset($skip);
				if($tmp_app_data = get_application_by_applicationid($db_app["templateid"])){
					if(!uint_in_array($tmp_app_data["hostid"], $templateids)){
						$skip = true;
						break;
					}
				}
				if(isset($skip)) continue;
				
			}
			
			if($unlink_mode){
				if(DBexecute("update applications set templateid=0 where applicationid=".$db_app["applicationid"])){
					info("Application '".$db_app["name"]."' unlinked");
				}
			}
			else{
				delete_application($db_app["applicationid"]);
			}
		}
	}

	/*
	 * Function: copy_template_applications
	 *
	 * Description:
	 *     Copy applicatios from templates to host
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
		
		$sql = 'SELECT key_,type,count(*) as cnt '.
			' FROM items '.
			' WHERE '.DBcondition('hostid',$templateid_list).
			' GROUP BY key_,type '.
			' ORDER BY cnt DESC';
		$res = DBselect($sql);
		while($db_cnt = DBfetch($res)){
			if($db_cnt['cnt']>1){
				$result &= false;
				error('Template with item key ['.htmlspecialchars($db_cnt['key_']).'] already linked to the host');
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
				error('Template with application ['.htmlspecialchars($db_cnt['name']).'] already linked to the host');
			}
		}

	return $result;
	}
				
	function host_status2str($status){
		switch($status){
			case HOST_STATUS_MONITORED:	$status = S_MONITORED;		break;
			case HOST_STATUS_NOT_MONITORED:	$status = S_NOT_MONITORED;	break;
			case HOST_STATUS_TEMPLATE:	$status = S_TEMPLATE;		break;
			case HOST_STATUS_DELETED:	$status = S_DELETED;		break;
			default:
				$status = S_UNKNOWN;		break;
		}
		return $status;
	}
	
	function host_status2style($status){
		switch($status){
			case HOST_STATUS_MONITORED:	$status = 'off';	break;
			case HOST_STATUS_NOT_MONITORED:	$status = 'on';		break;
			default:
				$status = 'unknown';	break;
		}
		return $status;
	}
	
// Add Host Profile

	function add_host_profile($hostid,$devicetype,$name,$os,$serialno,$tag,$macaddress,$hardware,$software,$contact,$location,$notes){
		
		$result=DBselect('SELECT * FROM hosts_profiles WHERE hostid='.$hostid);
		if(DBfetch($result)){
			error('Host profile already exists');
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
			error('Host profile already exists');
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

	function set_hosts_jsmenu_array($hostids = array()){
		$menu_all = array();
					
 		$db_groups = DBselect('SELECT DISTINCT g.groupid, g.name '.
		 				' FROM groups g '.
						' WHERE '.DBin_node('g.groupid').
 						' ORDER BY g.name,g.groupid');
		
		while($group=DBfetch($db_groups)){
			$group['name'] = htmlspecialchars($group['name']);
			$menu_all[] = $group;
		}
		insert_js('var menu_hstgrp_all='.zbx_jsvalue($menu_all).";\n");
	}
	
	
	function host_js_menu($hostid,$link_text = S_SELECT){
		$hst_grp_all_in = array();
		
		$db_groups = DBselect('SELECT DISTINCT g.groupid, g.name '.
				' FROM groups g, hosts_groups hg '.
 				' WHERE g.groupid=hg.groupid '.
					' AND hg.hostid='.$hostid.
 				' ORDER BY g.name');

		while($group = DBfetch($db_groups)){
			$group['name'] = htmlspecialchars($group['name']);
			$hst_grp_all_in[] = $group;	
		}
				
		$action = new CSpan($link_text);
		$script = new CScript('javascript: create_host_menu(event,'.$hostid.','.zbx_jsvalue($hst_grp_all_in).');');
							 
		$action->AddAction('onclick',$script);
		$action->AddOption('onmouseover','javascript: this.style.cursor = "pointer";');
		
	return $action;
	}

	function expand_host_ipmi_ip_by_data($ipmi_ip, $host){
		if (zbx_strstr($ipmi_ip, '{HOSTNAME}'))
			$ipmi_ip = str_replace('{HOSTNAME}', $host['host'], $ipmi_ip);
		else if (zbx_strstr($ipmi_ip, '{IPADDRESS}'))
			$ipmi_ip = str_replace('{IPADDRESS}', $host['ip'], $ipmi_ip);
		else if (zbx_strstr($ipmi_ip, '{HOST.DNS}'))
			$ipmi_ip = str_replace('{HOST.DNS}', $host['dns'], $ipmi_ip);
		else if (zbx_strstr($ipmi_ip, '{HOST.CONN}'))
			$ipmi_ip = str_replace('{HOST.CONN}', $host['useip'] ? $host['ip'] : $host['dns'], $ipmi_ip);

		return $ipmi_ip;
	}

?>
