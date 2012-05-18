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

function zbx_session_start($userid, $name, $password){
	$sessionid = md5(time().$password.$name.rand(0,10000000));
	zbx_setcookie('zbx_sessionid',$sessionid);

	DBexecute('INSERT INTO sessions (sessionid,userid,lastaccess,status) VALUES ('.zbx_dbstr($sessionid).','.$userid.','.time().','.ZBX_SESSION_ACTIVE.')');

return $sessionid;
}

function permission2str($group_permission){
	$str_perm[PERM_READ_WRITE]	= S_READ_WRITE;
	$str_perm[PERM_READ_ONLY]	= S_READ_ONLY;
	$str_perm[PERM_DENY]		= S_DENY;

	if(isset($str_perm[$group_permission]))
		return $str_perm[$group_permission];

	return S_UNKNOWN;
}

/*****************************************
	CHECK USER AUTHORISATION
*****************************************/

function check_authorisation(){
	global $USER_DETAILS;
	$sessionid = get_cookie('zbx_sessionid');

	$user = array('sessionid'=>$sessionid);
	if(!$auth = CUser::checkAuthentication($user)){
		include_once('include/locales/en_gb.inc.php');
		process_locales();

		if(!isset($_REQUEST['request'])){
			$parsedUrl = new Curl($_SERVER['REQUEST_URI']);
			if(!zbx_empty($parsedUrl->getPath()) && !preg_match('/.*\/(index.php)?$/i', $parsedUrl->getPath())){
				$_REQUEST['request'] = $_SERVER['REQUEST_URI'];
			}
		}

		include('index.php');
		exit();
	}

return $auth;
}


/***********************************************
	CHECK USER ACCESS TO SYSTEM STATUS
************************************************/
/* Function: check_perm2system()
 *
 * Description:
 * 		Checking user permissions to access system (affects server side: no notification will be sent)
 *
 * Comments:
 *		return true if permission is positive
 *
 * Author: Aly
 */
function  check_perm2system($userid){
	$sql = 'SELECT g.usrgrpid '.
		' FROM usrgrp g, users_groups ug '.
		' WHERE ug.userid = '.$userid.
			' AND g.usrgrpid = ug.usrgrpid '.
			' AND g.users_status = '.GROUP_STATUS_DISABLED;
	if($res = DBfetch(DBselect($sql,1))){
		return false;
	}
return true;
}

/* Function: check_perm2login()
 *
 * Description:
 * 		Checking user permissions to Login in frontend
 *
 * Comments:
 *		return true if permission is positive
 *
 * Author: Aly
 */

function check_perm2login($userid){
	$res = get_user_auth($userid);

return (GROUP_GUI_ACCESS_DISABLED == $res)?false:true;
}

/* Function: get_user_auth()
 *
 * Description:
 * 		Returns user authentication type
 *
 * Comments:
 *		default is SYSTEM auth
 *
 * Author: Aly
 */
function get_user_auth($userid){
	global $USER_DETAILS;

	if(($userid == $USER_DETAILS['userid']) && isset($USER_DETAILS['gui_access'])) return $USER_DETAILS['gui_access'];
	else $result = GROUP_GUI_ACCESS_SYSTEM;

	$sql = 'SELECT MAX(g.gui_access) as gui_access '.
		' FROM usrgrp g, users_groups ug '.
		' WHERE ug.userid='.$userid.
			' AND g.usrgrpid=ug.usrgrpid ';
	$acc = DBfetch(DBselect($sql));

	if(!zbx_empty($acc['gui_access'])){
		$result = $acc['gui_access'];
	}

return $result;
}

function get_user_debug_mode($userid){
	$sql = 'SELECT g.usrgrpid '.
			' FROM usrgrp g, users_groups ug '.
			' WHERE ug.userid = '.$userid.
				' AND g.usrgrpid = ug.usrgrpid '.
				' AND g.debug_mode = '.GROUP_DEBUG_MODE_ENABLED;
	if($res = DBfetch(DBselect($sql,1))){
		return true;
	}
return false;
}

/* Function: get_user_system_auth()
 *
 * Description:
 * 		Returns overal user authentication type in system
 *
 * Comments:
 *		default is INTERNAL auth
 *
 * Author: Aly
 */
function get_user_system_auth($userid){
	$config = select_config();

	$result = get_user_auth($userid);

	switch($result){
		case GROUP_GUI_ACCESS_SYSTEM:
			$result = $config['authentication_type'];
		break;
		case GROUP_GUI_ACCESS_INTERNAL:
			if($config['authentication_type'] == ZBX_AUTH_HTTP){
				$result = ZBX_AUTH_HTTP;
			}
			else{
				$result = ZBX_AUTH_INTERNAL;
			}
		break;
		case GROUP_GUI_ACCESS_DISABLED:
			$result = $config['authentication_type'];
		default:
			break;
	}

return $result;
}

/***********************************************
	GET ACCESSIBLE RESOURCES BY USERID
************************************************/

function available_groups($groupids, $editable=null){
	$options = array();
	$options['groupids'] = $groupids;
	$options['editable'] = $editable;

	$groups = CHostGroup::get($options);
return zbx_objectValues($groups, 'groupid');
}
function available_hosts($hostids, $editable=null){
	$options = array();
	$options['hostids'] = $hostids;
	$options['editable'] = $editable;
	$options['templated_hosts'] = 1;

	$hosts = CHost::get($options);

return zbx_objectValues($hosts, 'hostid');
}

function available_triggers($triggerids, $editable=null){
	$options = array(
		'triggerids' => $triggerids,
		'editable' => $editable,
		'nodes' => get_current_nodeid(true)
	);

	$triggers = CTrigger::get($options);

return zbx_objectValues($triggers, 'triggerid');
}

function get_accessible_hosts_by_user(&$user_data,$perm,$perm_res=null,$nodeid=null,$cache=1){
//		global $DB;
	static $available_hosts;

	if(is_null($perm_res)) $perm_res = PERM_RES_IDS_ARRAY;
	if($perm == PERM_READ_LIST)	$perm = PERM_READ_ONLY;

	$result = array();

	$userid =& $user_data['userid'];
	$user_type =& $user_data['type'];

	if(!isset($userid)) fatal_error('Incorrect user data in "get_accessible_hosts_by_user"');
	if(is_null($nodeid)) $nodeid = get_current_nodeid();

	$nodeid_str =(is_array($nodeid))?md5(implode('',$nodeid)):strval($nodeid);

	if($cache && isset($available_hosts[$userid][$perm][$perm_res][$nodeid_str])){
//SDI('Cache!!! '."[$userid][$perm][$perm_res]");
		return $available_hosts[$userid][$perm][$perm_res][$nodeid_str];
	}

	$where = array();

	if(!is_null($nodeid))
		array_push($where, DBin_node('h.hostid', $nodeid));

	if(count($where))
		$where = ' WHERE '.implode(' AND ',$where);
	else
		$where = '';

//		$sortorder = (isset($DB['TYPE']) && (($DB['TYPE'] == 'MYSQL') || ($DB['TYPE'] == 'SQLITE3')))?' DESC ':'';
//SDI($sql);
	$sql = 'SELECT DISTINCT n.nodeid, n.name as node_name, h.hostid, h.host, min(r.permission) as permission, ug.userid '.
		' FROM hosts h '.
			' LEFT JOIN hosts_groups hg ON hg.hostid=h.hostid '.
			' LEFT JOIN groups g ON g.groupid=hg.groupid '.
			' LEFT JOIN rights r ON r.id=g.groupid '.
			' LEFT JOIN users_groups ug ON ug.usrgrpid=r.groupid and ug.userid='.$userid.
			' LEFT JOIN nodes n ON '.DBid2nodeid('h.hostid').'=n.nodeid '.
		$where.
		' GROUP BY h.hostid,n.nodeid,n.name,h.host,ug.userid '.
		' ORDER BY n.name,n.nodeid, h.host, permission, ug.userid ';
//SDI($sql);
	$db_hosts = DBselect($sql);

	$processed = array();
	while($host_data = DBfetch($db_hosts)){
		if(zbx_empty($host_data['nodeid'])) $host_data['nodeid'] = id2nodeid($host_data['hostid']);

/* if no rights defined */
		if(USER_TYPE_SUPER_ADMIN == $user_type){
			$host_data['permission'] = PERM_MAX;
		}
		else{
			if(zbx_empty($host_data['permission']) || zbx_empty($host_data['userid'])) continue;

			if(isset($processed[$host_data['hostid']])){
				if(PERM_DENY == $host_data['permission']){
					unset($result[$host_data['hostid']]);
				}
				else if($processed[$host_data['hostid']] > $host_data['permission']){
					unset($processed[$host_data['hostid']]);
				}
				else{
					continue;
				}
			}
		}

		$processed[$host_data['hostid']] = $host_data['permission'];
		if($host_data['permission']<$perm)	continue;

		switch($perm_res){
			case PERM_RES_DATA_ARRAY:
				$result[$host_data['hostid']] = $host_data;
				break;
			default:
				$result[$host_data['hostid']] = $host_data['hostid'];
		}
	}

	unset($processed, $host_data, $db_hosts);

	if(PERM_RES_STRING_LINE == $perm_res){
		if(count($result) == 0)
			$result = '-1';
		else
			$result = implode(',',$result);
	}

	$available_hosts[$userid][$perm][$perm_res][$nodeid_str] = $result;

return $result;
}

function get_accessible_groups_by_user($user_data,$perm,$perm_res=null,$nodeid=null){
	global $ZBX_LOCALNODEID;

	if(is_null($perm_res)) $perm_res = PERM_RES_IDS_ARRAY;
	if(is_null($nodeid)) $nodeid = get_current_nodeid();

	$result = array();

	$userid =& $user_data['userid'];
	if(!isset($userid)) fatal_error(S_INCORRECT_USER_DATA_IN.SPACE.'"get_accessible_groups_by_user"');
	$user_type =& $user_data['type'];

	$processed = array();
	$where = array();

	if(!is_null($nodeid)){
		array_push($where, DBin_node('hg.groupid', $nodeid));
	}
	$where = count($where)?' WHERE '.implode(' AND ',$where):'';

	$sql = 'SELECT n.nodeid as nodeid,n.name as node_name,hg.groupid,hg.name,min(r.permission) as permission,g.userid'.
		' FROM groups hg '.
			' LEFT JOIN rights r ON r.id=hg.groupid '.
			' LEFT JOIN users_groups g ON r.groupid=g.usrgrpid AND g.userid='.$userid.
			' LEFT JOIN nodes n ON '.DBid2nodeid('hg.groupid').'=n.nodeid '.
		$where.
		' GROUP BY n.nodeid, n.name, hg.groupid, hg.name, g.userid, g.userid '.
		' ORDER BY node_name, hg.name, permission ';
	$db_groups = DBselect($sql);
	while($group_data = DBfetch($db_groups)){
		if(zbx_empty($group_data['nodeid'])) $group_data['nodeid'] = id2nodeid($group_data['groupid']);


/* deny if no rights defined */
		if(USER_TYPE_SUPER_ADMIN == $user_type){
			$group_data['permission'] = PERM_MAX;
		}
		else{
			if(zbx_empty($group_data['permission']) || zbx_empty($group_data['userid'])) continue;

			if(isset($processed[$group_data['groupid']])){
				if(PERM_DENY == $group_data['permission']){
					unset($result[$group_data['groupid']]);
				}
				else if($processed[$group_data['groupid']] > $group_data['permission']){
					unset($processed[$group_data['groupid']]);
				}
				else{
					continue;
				}
			}
		}

		$processed[$group_data['groupid']] = $group_data['permission'];
		if($group_data['permission'] < $perm) continue;

		switch($perm_res){
			case PERM_RES_DATA_ARRAY:
				$result[$group_data['groupid']] = $group_data;
				break;
			default:
				$result[$group_data['groupid']] = $group_data["groupid"];
				break;
		}
	}

	unset($processed, $group_data, $db_groups);

	if($perm_res == PERM_RES_STRING_LINE) {
		if(count($result) == 0)
			$result = '-1';
		else
			$result = implode(',',$result);
	}

return $result;
}

function get_accessible_nodes_by_user(&$user_data,$perm,$perm_res=null,$nodeid=null,$cache=1){
	global $ZBX_LOCALNODEID, $ZBX_NODES_IDS;
	static $available_nodes;

	if(is_null($perm_res)) $perm_res = PERM_RES_IDS_ARRAY;
	if(is_null($nodeid)) $nodeid = $ZBX_NODES_IDS;
	if(!is_array($nodeid)) $nodeid = array($nodeid);

	$userid		=& $user_data['userid'];
	$user_type	=& $user_data['type'];
	if(!isset($userid)) fatal_error(S_INCORRECT_USER_DATA_IN.SPACE.'"get_accessible_nodes_by_user"');


	$nodeid_str =(is_array($nodeid))?md5(implode('',$nodeid)):strval($nodeid);

	if($cache && isset($available_nodes[$userid][$perm][$perm_res][$nodeid_str])){
//SDI('Cache!!! '."[$userid][$perm][$perm_res]");
		return $available_nodes[$userid][$perm][$perm_res][$nodeid_str];
	}

	$node_data = array();
	$result = array();

//COpt::counter_up('perm');
	if(USER_TYPE_SUPER_ADMIN == $user_type){
		$nodes = DBselect('SELECT nodeid FROM nodes');
		while($node = DBfetch($nodes)){
			$node_data[$node['nodeid']] = $node;
			$node_data[$node['nodeid']]['permission'] = PERM_READ_WRITE;
		}
		if(empty($node_data)) $node_data[0]['nodeid'] = 0;
	}
	else{
		$available_groups = get_accessible_groups_by_user($user_data,$perm,PERM_RES_DATA_ARRAY,$nodeid,$cache);

		foreach($available_groups as $id => $group){
			$nodeid = id2nodeid($group['groupid']);
			$permission = (isset($node_data[$nodeid]) && ($permission < $node_data[$nodeid]['permission']))?$node_data[$nodeid]['permission']:$group['permission'];

			$node_data[$nodeid]['nodeid'] = $nodeid;
			$node_data[$nodeid]['permission'] = $permission;
		}
	}

	foreach($node_data as $nodeid => $node){
		switch($perm_res){
			case PERM_RES_DATA_ARRAY:
				$db_node = DBfetch(DBselect('SELECT * FROM nodes WHERE nodeid='.$nodeid.' ORDER BY name'));

				if(!ZBX_DISTRIBUTED){
					if(!$node){
						$db_node = array(
							'nodeid'	=> $ZBX_LOCALNODEID,
							'name'		=> 'local',
							'permission'	=> PERM_READ_WRITE,
							'userid'	=> null
							);
					}
					else{
						continue;
					}
				}

				$result[$nodeid] = zbx_array_merge($db_node,$node);

				break;
			default:
				$result[$nodeid] = $nodeid;
				break;
		}
	}

	if($perm_res == PERM_RES_STRING_LINE) {
		if(count($result) == 0)
			$result = '-1';
		else
			$result = implode(',',$result);
	}

	$available_nodes[$userid][$perm][$perm_res][$nodeid_str] = $result;

return $result;
}

/***********************************************
	GET ACCESSIBLE RESOURCES BY RIGHTS
************************************************/
	/* NOTE: right structure is

		$rights[i]['type']	= type of resource
		$rights[i]['permission']= permission for resource
		$rights[i]['id']	= resource id

	*/

function get_accessible_hosts_by_rights(&$rights,$user_type,$perm,$perm_res=null,$nodeid=null){
	if(is_null($perm_res))		$perm_res	= PERM_RES_STRING_LINE;
	if($perm == PERM_READ_LIST)	$perm		= PERM_READ_ONLY;

	$result = array();
	$res_perm = array();

	foreach($rights as $id => $right){
		$res_perm[$right['id']] = $right['permission'];
	}

	$host_perm = array();

	$where = array();
	if(!is_null($nodeid))	array_push($where, DBin_node('h.hostid', $nodeid));
	$where = count($where)?$where = ' WHERE '.implode(' AND ',$where):'';

	$sql = 'SELECT n.nodeid as nodeid,n.name as node_name,hg.groupid as groupid,h.hostid, h.host '.
				' FROM hosts h '.
					' LEFT JOIN hosts_groups hg ON hg.hostid=h.hostid '.
					' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('h.hostid').
				$where.
				' ORDER BY n.name,h.host';

	$perm_by_host = array();
	$db_hosts = DBselect($sql);
	while($host_data = DBfetch($db_hosts)){
		if(isset($host_data['groupid']) && isset($res_perm[$host_data['groupid']])){
			if(!isset($perm_by_host[$host_data['hostid']])) $perm_by_host[$host_data['hostid']] = array();

			$perm_by_host[$host_data['hostid']][] = $res_perm[$host_data['groupid']];

			$host_perm[$host_data['hostid']][$host_data['groupid']] = $res_perm[$host_data['groupid']];
		}
		$host_perm[$host_data['hostid']]['data'] = $host_data;
	}

	foreach($host_perm as $hostid => $host_data){
		$host_data = $host_data['data'];

// Select Min rights from groups
		if(USER_TYPE_SUPER_ADMIN == $user_type){
			$host_data['permission'] = PERM_MAX;
		}
		else{
			if(isset($perm_by_host[$hostid])){
				$host_data['permission'] = min($perm_by_host[$hostid]);
			}
			else{
				if(is_null($host_data['nodeid'])) $host_data['nodeid'] = id2nodeid($host_data['groupid']);

				$host_data['permission'] = PERM_DENY;
			}
		}

		if($host_data['permission']<$perm) continue;
		switch($perm_res){
			case PERM_RES_DATA_ARRAY:
				$result[$host_data['hostid']] = $host_data;
				break;
			default:
				$result[$host_data['hostid']] = $host_data['hostid'];
		}
	}

	if($perm_res == PERM_RES_STRING_LINE) {
		if(count($result) == 0)
			$result = '-1';
		else
			$result = implode(',',$result);
	}

return $result;
}

function get_accessible_groups_by_rights(&$rights,$user_type,$perm,$perm_res=null,$nodeid=null){
	if(is_null($perm_res))	$perm_res=PERM_RES_STRING_LINE;
	$result= array();

	$where = array();

	if(!is_null($nodeid))
		array_push($where, DBin_node('g.groupid', $nodeid));

	if(count($where)) $where = ' WHERE '.implode(' AND ',$where);
	else $where = '';

	$group_perm = array();
	foreach($rights as $id => $right){
		$group_perm[$right['id']] = $right['permission'];
	}

	$sql = 'SELECT n.nodeid as nodeid,n.name as node_name, g.*, '.PERM_DENY.' as permission '.
						' FROM groups g '.
							' LEFT JOIN nodes n ON '.DBid2nodeid('g.groupid').'=n.nodeid '.
						$where.
						' ORDER BY n.name, g.name';

	$db_groups = DBselect($sql);

	while($group_data = DBfetch($db_groups)){

		if(USER_TYPE_SUPER_ADMIN == $user_type){
			$group_data['permission'] = PERM_MAX;
		}
		else{
			if(isset($group_perm[$group_data['groupid']])){
				$group_data['permission'] = $group_perm[$group_data['groupid']];
			}
			else{
				if(is_null($group_data['nodeid'])) $group_data['nodeid'] = id2nodeid($group_data['groupid']);
				$group_data['permission'] = PERM_DENY;
			}
		}

		if($group_data['permission']<$perm) continue;

		switch($perm_res){
			case PERM_RES_DATA_ARRAY:
				$result[$group_data['groupid']] = $group_data;
				break;
			default:
				$result[$group_data['groupid']] = $group_data['groupid'];
		}
	}

	if($perm_res == PERM_RES_STRING_LINE) {
		if(count($result) == 0)
			$result = '-1';
		else
			$result = implode(',',$result);
	}

return $result;
}

function get_accessible_nodes_by_rights(&$rights,$user_type,$perm,$perm_res=null){
	global $ZBX_LOCALNODEID;

	$nodeid = get_current_nodeid(true);

	if(is_null($perm_res))	$perm_res=PERM_RES_STRING_LINE;
	if(is_null($user_type)) $user_type = USER_TYPE_ZABBIX_USER;

	$node_data = array();
	$result = array();

//COpt::counter_up('perm_nodes['.$userid.','.$perm.','.$perm_mode.','.$perm_res.','.$nodeid.']');
//COpt::counter_up('perm');
//SDI(get_accessible_groups_by_rights($rights,$user_type,$perm,PERM_RES_DATA_ARRAY,$nodeid));
	$available_groups = get_accessible_groups_by_rights($rights,$user_type,$perm,PERM_RES_DATA_ARRAY,$nodeid);
	foreach($available_groups as $id => $group){
		$nodeid = id2nodeid($group['groupid']);
		$permission = $group['permission'];

		if(isset($node_data[$nodeid]) && ($permission < $node_data[$nodeid]['permission'])){
			$permission = $node_data[$nodeid]['permission'];
		}

		$node_data[$nodeid]['nodeid'] = $nodeid;
		$node_data[$nodeid]['permission'] = $permission;
	}

	$available_hosts = get_accessible_hosts_by_rights($rights,$user_type,$perm,PERM_RES_DATA_ARRAY,$nodeid);
	foreach($available_hosts as $id => $host){
		$nodeid = id2nodeid($host['hostid']);
		$permission = $host['permission'];

		if(isset($node_data[$nodeid]) && ($permission < $node_data[$nodeid]['permission'])){
			$permission = $node_data[$nodeid]['permission'];
		}

		$node_data[$nodeid]['nodeid'] = $nodeid;
		$node_data[$nodeid]['permission'] = $permission;
	}

	foreach($node_data as $nodeid => $node){
		switch($perm_res){
			case PERM_RES_DATA_ARRAY:
				$db_node = DBfetch(DBselect('SELECT * FROM nodes WHERE nodeid='.$nodeid));

				if(!ZBX_DISTRIBUTED){
					if(!$node){
						$db_node = array(
							'nodeid'	=> $ZBX_LOCALNODEID,
							'name'		=> 'local',
							'permission'	=> PERM_READ_WRITE,
							'userid'	=> null
							);
					}
					else{
						continue;
					}
				}

				$result[$nodeid] = zbx_array_merge($db_node,$node);

				break;
			default:
				$result[$nodeid] = $nodeid;
				break;
		}
	}

	if($perm_res == PERM_RES_STRING_LINE) {
		if(count($result) == 0)
			$result = '-1';
		else
			$result = implode(',',$result);
	}

return $result;
}
?>
