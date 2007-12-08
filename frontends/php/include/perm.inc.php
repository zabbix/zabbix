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
	require_once "include/db.inc.php";

	function	permission2str($group_permission)
	{
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

	function	check_authorisation()
	{
		global	$page;
		global	$PHP_AUTH_USER,$PHP_AUTH_PW;
		global	$USER_DETAILS;
		global	$_REQUEST;
		global	$ZBX_LOCALNODEID;

		$USER_DETAILS = NULL;
		
		$sessionid = get_cookie("zbx_sessionid");

		if( !is_null($sessionid))
		{
			if(!($USER_DETAILS = DBfetch(DBselect("select u.*,s.* from sessions s,users u".
				" where s.sessionid=".zbx_dbstr($sessionid)." and s.userid=u.userid".
				" and ((s.lastaccess+u.autologout>".time().") or (u.autologout=0))".
				" and ".DBin_node('u.userid', $ZBX_LOCALNODEID)))))
			{
				zbx_unsetcookie('zbx_sessionid');
				DBexecute("delete from sessions where sessionid=".zbx_dbstr($sessionid));
				unset($sessionid);

				$incorrect_session = true;
			}
			else
			{
				zbx_setcookie("zbx_sessionid",$sessionid);
				DBexecute("update sessions set lastaccess=".time()." where sessionid=".zbx_dbstr($sessionid));
			}
		}
		
		if(!$USER_DETAILS)
		{
			if(!($USER_DETAILS = DBfetch(DBselect("select u.* from users u where u.alias='guest'".
				" and ".DBin_node('u.userid', $ZBX_LOCALNODEID)))))
			{
				$missed_user_guest = true;
			}
		}

		if($USER_DETAILS)
		{
			$USER_DETAILS['node'] = DBfetch(DBselect('select * from nodes where nodeid='.id2nodeid($USER_DETAILS['userid'])));
			if(empty($USER_DETAILS['node']))
			{
				$USER_DETAILS['node']['name'] = '- unknown -';
				$USER_DETAILS['node']['nodeid'] = $ZBX_LOCALNODEID;
			}
		}
		else
		{
			$USER_DETAILS = array(
				"alias"	=>"guest",
				"userid"=>0,
				"lang"	=>"en_gb",
				"type"	=>"0",
				"node"	=>array(
					"name"	=>'- unknown -',
					"nodeid"=>0));
		}
		
		if(isset($incorrect_session) || isset($missed_user_guest))
		{
			if(isset($incorrect_session))		$message = "Session was ended, please relogin!";
			else if(isset($missed_user_guest))	$message = "Database corrupted, missed default user 'guest'";
			
			global $_REQUEST;

			if(!isset($_REQUEST['message'])) $_REQUEST['message'] = $message;
			
			include('index.php');
			exit;
		}
	}

/***********************************************
	GET ACCESSIBLE RESOURCES BY USERID
************************************************/
	function	perm_mode2comparator($perm_mode)
	{
		switch($perm_mode)
		{
			case PERM_MODE_NE:	$perm_mode = '!='; break;
			case PERM_MODE_EQ:	$perm_mode = '=='; break;
			case PERM_MODE_GT:	$perm_mode = '>'; break;
			case PERM_MODE_LT:	$perm_mode = '<'; break;
			case PERM_MODE_LE:	$perm_mode = '<='; break;
			case PERM_MODE_GE:
			default:		$perm_mode = '>='; break;
		}
		return $perm_mode;
	}

	function	get_accessible_hosts_by_user(&$user_data,$perm,$perm_mode=null,$perm_res=null,$nodeid=null,$hostid=null)
	{
		if(is_null($perm_res))		$perm_res	= PERM_RES_STRING_LINE;
		if($perm == PERM_READ_LIST)	$perm		= PERM_READ_ONLY;

		$result = array();

		$userid =& $user_data['userid'];
		$user_type =& $user_data['type'];

		if(!isset($userid)) fatal_error('Incorrect user data in "get_accessible_hosts_by_user"');

		switch($perm_res)
		{
			case PERM_RES_DATA_ARRAY:	$resdata = '$host_data'; break;
			default:			$resdata = '$host_data["hostid"]'; break;
		}

COpt::counter_up('perm_host['.$userid.','.$perm.','.$perm_mode.','.$perm_res.','.$nodeid.']');
COpt::counter_up('perm');

		$where = array();

		if ( !is_null($nodeid) )	array_push($where, DBin_node('h.hostid', $nodeid));
	
		if(is_array($hostid))	array_push($where, ' h.hostid in ('.implode(',', $hostid).') ');
		elseif(isset($hostid))	array_push($where, ' h.hostid in ('.$hostid.') ');

		if(count($where)) 	$where = ' where '.implode(' and ',$where);
		else			$where = '';
	
		$sql = 'select distinct n.nodeid,n.name as node_name,h.hostid,h.host, min(r.permission) as permission,ug.userid '.
			' from hosts h left join hosts_groups hg on hg.hostid=h.hostid '.
			' left join groups g on g.groupid=hg.groupid '.
			' left join rights r on r.id=g.groupid and r.type='.RESOURCE_TYPE_GROUP.
			' left join users_groups ug on ug.usrgrpid=r.groupid and ug.userid='.$userid.
			' left join nodes n on '.DBid2nodeid('h.hostid').'=n.nodeid '.
			$where.' group by h.hostid,n.nodeid,n.name,h.host,ug.userid '.
			' order by n.name,n.nodeid, h.host, permission desc, userid desc';

		$db_hosts = DBselect($sql);

		$processed = array();
		while($host_data = DBfetch($db_hosts))
		{
//			It seems that host details are not required by the logic
//			$host_data += DBfetch(DBselect('select * from hosts where hostid='.$host_data['hostid']));

			if(is_null($host_data['nodeid'])) $host_data['nodeid'] = id2nodeid($host_data['hostid']);

			/* if no rights defined used node rights */
			if( (is_null($host_data['permission']) || is_null($host_data['userid'])) )
			{
				if( isset($processed[$host_data['hostid']]) )
					continue;

				if(!isset($nodes))
				{
					$nodes = get_accessible_nodes_by_user($user_data,
						PERM_DENY,PERM_MODE_GE,PERM_RES_DATA_ARRAY);
				}
				if( !isset($nodes[$host_data['nodeid']]) || $user_type==USER_TYPE_ZABBIX_USER )
					$host_data['permission'] = PERM_DENY;
				else
					$host_data['permission'] = $nodes[$host_data['nodeid']]['permission'];
			}

			$processed[$host_data['hostid']] = true;

			if(eval('return ('.$host_data["permission"].' '.perm_mode2comparator($perm_mode).' '.$perm.')? 0 : 1;'))
				continue;

			$result[$host_data['hostid']] = eval('return '.$resdata.';');
		}

		unset($processed, $host_data, $db_hosts);

		if($perm_res == PERM_RES_STRING_LINE) 
		{
			if(count($result) == 0) 
				$result = '-1';
			else
				$result = implode(',',$result);
		}

		return $result;
	}

	function	get_accessible_groups_by_user($user_data,$perm,$perm_mode=null,$perm_res=null,$nodeid=null)
	{
		global $ZBX_LOCALNODEID;

		if(is_null($perm_mode))		$perm_mode	= PERM_MODE_GE;
		if(is_null($perm_res))		$perm_res	= PERM_RES_STRING_LINE;

		$result = array();

		$userid =& $user_data['userid'];
		if(!isset($userid)) fatal_error('Incorrect user data in "get_accessible_groups_by_user"');
		$user_type =& $user_data['type'];

		switch($perm_res)
		{
			case PERM_RES_DATA_ARRAY:	$resdata = '$group_data'; break;
			default:			$resdata = '$group_data["groupid"]'; break;
		}

COpt::counter_up('perm_group['.$userid.','.$perm.','.$perm_mode.','.$perm_res.','.$nodeid.']');
COpt::counter_up('perm');

		$where = array();

		if ( !is_null($nodeid) )	array_push($where, DBin_node('hg.groupid', $nodeid));
	
		if(count($where)) 	$where = ' where '.implode(' and ',$where);
		else			$where = '';
	
		/* if no rights defined used node rights */
		$db_groups = DBselect('select n.nodeid as nodeid,n.name as node_name,hg.groupid,hg.name,min(r.permission) as permission,g.userid'.
			' from groups hg left join rights r on r.id=hg.groupid and r.type='.RESOURCE_TYPE_GROUP.
			' left join users_groups g on r.groupid=g.usrgrpid and g.userid='.$userid.
			' left join nodes n on '.DBid2nodeid('hg.groupid').'=n.nodeid '.
			$where.' group by n.nodeid, n.name, hg.groupid, hg.name, g.userid, g.userid '.
			' order by n.name, hg.name, permission desc');

		$processed = array();
		while($group_data = DBfetch($db_groups))
		{
			if(is_null($group_data['nodeid'])) $group_data['nodeid'] = id2nodeid($group_data['groupid']);

			/* deny if no rights defined */
			if( is_null($group_data['permission']) || is_null($group_data['userid']) )
			{
				if(isset($processed[$group_data['groupid']]))
					continue;

				if(!isset($nodes))
				{
					$nodes = get_accessible_nodes_by_user($user_data,
						PERM_DENY,PERM_MODE_GE,PERM_RES_DATA_ARRAY);
				}

				if( !isset($nodes[$group_data['nodeid']]) || $user_type==USER_TYPE_ZABBIX_USER )
					$group_data['permission'] = PERM_DENY;
				else
					$group_data['permission'] = $nodes[$group_data['nodeid']]['permission'];
			}

//			$processed[$group_data['permission']] = true;
			$processed[$group_data['groupid']] = true;

			if(eval('return ('.$group_data["permission"].' '.perm_mode2comparator($perm_mode).' '.$perm.')? 0 : 1;'))
				continue;

			$result[$group_data['groupid']] = eval('return '.$resdata.';');
		}

		unset($processed, $group_data, $db_groups);

		if($perm_res == PERM_RES_STRING_LINE) 
		{
			if(count($result) == 0) 
				$result = '-1';
			else
				$result = implode(',',$result);
		}

		return $result;
	}

	function	get_accessible_nodes_by_user(&$user_data,$perm,$perm_mode=null,$perm_res=null,$nodeid=null)
	{
		global $ZBX_LOCALNODEID;

		if(is_null($perm_mode)) $perm_mode=PERM_MODE_GE;
		if(is_null($perm_res))	$perm_res=PERM_RES_STRING_LINE;

		$userid		=& $user_data['userid'];
		$user_type	=& $user_data['type'];
		if(!isset($userid)) fatal_error('Incorrect user data in "get_accessible_nodes_by_user"');

		$result= array();

		switch($perm_res)
		{
			case PERM_RES_DATA_ARRAY:	$resdata = '$node_data'; break;
			default:			$resdata = '$node_data["nodeid"]'; break;
		}

COpt::counter_up('perm_nodes['.$userid.','.$perm.','.$perm_mode.','.$perm_res.','.$nodeid.']');
COpt::counter_up('perm');

		if(is_null($nodeid))		$where_nodeid = '';
		else if(is_array($nodeid))	$where_nodeid = ' where n.nodeid in ('.implode(',', $nodeid).') ';
		else 				$where_nodeid = ' where  n.nodeid in ('.$nodeid.') ';


		$db_nodes = DBselect('select n.nodeid,min(r.permission) as permission, g.userid'.
			' from nodes n left join rights r on r.id=n.nodeid and r.type='.RESOURCE_TYPE_NODE.
			' left join users_groups g on r.groupid=g.usrgrpid and g.userid='.$userid.
			$where_nodeid.' group by n.nodeid, g.userid order by nodeid desc, userid desc, permission desc');

		while(($node_data = DBfetch($db_nodes)) || (!isset($do_break) && !ZBX_DISTRIBUTED))
		{
			if($node_data && $perm_res == PERM_RES_DATA_ARRAY)
			{
				$node_data += DBfetch(DBselect('select * from nodes where nodeid='.$node_data['nodeid']));
			}

			if($node_data && isset($processed_nodeids[$node_data["nodeid"]])) continue;

			if(!ZBX_DISTRIBUTED)
			{
				if(!$node_data)
				{
					$node_data = array(
						'nodeid'	=> $ZBX_LOCALNODEID,
						'name'		=> 'local',
						'permission'	=> PERM_READ_WRITE,
						'userid'	=> null
						);

					$do_break = true;

					if(is_array($nodeid) && !in_array($node_data['nodeid'],$nodeid))	continue;
					else if(isset($nodeid) and $node_data['nodeid'] != $nodeid)		continue;
				}
				else
				{
					$node_data['permission'] = PERM_DENY;
				}
			}

			$processed_nodeids[$node_data["nodeid"]] = $node_data["nodeid"];

			/* deny if no rights defined (for local node read/write)*/
			if(is_null($node_data['permission']) || is_null($node_data['userid']))
			{
				if($user_type == USER_TYPE_SUPER_ADMIN)
					$node_data['permission'] = PERM_READ_WRITE;
				else
					$node_data['permission'] = 
						($node_data['nodeid'] == $ZBX_LOCALNODEID) ? PERM_READ_WRITE : PERM_DENY;
			}

			/* special processing for PERM_READ_LIST*/
			if(PERM_DENY == $node_data['permission'] && PERM_READ_LIST == $perm)
			{
				$groups = get_accessible_groups_by_user($user_data,
					$perm, PERM_MODE_GE,PERM_RES_DATA_ARRAY,$node_data['nodeid']);
				if(count($groups) == 0)  continue;
			}
			else
			{
				if(eval('return ('.$node_data["permission"].' '.perm_mode2comparator($perm_mode).' '.$perm.')? 0 : 1;'))
					continue;
			}

			$result[$node_data["nodeid"]] = eval('return '.$resdata.';');
		}

		if($perm_res == PERM_RES_STRING_LINE) 
		{
			if(count($result) == 0) 
				$result = '-1';
			else
				$result = implode(',',$result);
		}

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

	function	get_accessible_hosts_by_rights(&$rights,$user_type,$perm,$perm_mode=null,$perm_res=null,$nodeid=null)
	{
		if(is_null($perm_res))		$perm_res	= PERM_RES_STRING_LINE;
		if($perm == PERM_READ_LIST)	$perm		= PERM_READ_ONLY;

		$result = array();

		switch($perm_res)
		{
			case PERM_RES_DATA_ARRAY:	$resdata = '$host_data'; break;
			default:			$resdata = '$host_data["hostid"]'; break;
		}

		$where = array();

		if ( !is_null($nodeid) )	array_push($where, DBin_node('h.hostid', $nodeid));
	
		if(count($where)) 	$where = ' where '.implode(' and ',$where);
		else			$where = '';

		$db_hosts = DBselect('select n.nodeid as nodeid,n.name as node_name,hg.groupid as groupid,h.* '.
			' from hosts h left join hosts_groups hg on hg.hostid=h.hostid '.
			' left join nodes n on n.nodeid='.DBid2nodeid('h.hostid').
			$where.' order by n.name,h.host');

		$res_perm = array();
		foreach($rights as $right)
		{
			$res_perm[$right['type']][$right['id']] = $right['permission'];
		}

		$host_perm = array();

		while($host_data = DBfetch($db_hosts))
		{
			if(isset($host_data['groupid']) && isset($res_perm[RESOURCE_TYPE_GROUP][$host_data['groupid']]))
			{
				$host_perm[$host_data['hostid']][RESOURCE_TYPE_GROUP][$host_data['groupid']] =
					$res_perm[RESOURCE_TYPE_GROUP][$host_data['groupid']];
			}

			if(isset($res_perm[RESOURCE_TYPE_NODE][$host_data['nodeid']]))
			{
				$host_perm[$host_data['hostid']][RESOURCE_TYPE_NODE] = $res_perm[RESOURCE_TYPE_NODE][$host_data['nodeid']];
			}
			$host_perm[$host_data['hostid']]['data'] = $host_data;

		}

		foreach($host_perm as $hostid => $host_data)
		{
			$host_data = $host_data['data'];

			if(isset($host_perm[$hostid][RESOURCE_TYPE_GROUP]))
			{
				$host_data['permission'] = min($host_perm[$hostid][RESOURCE_TYPE_GROUP]);
			}
			else if(isset($host_perm[$hostid][RESOURCE_TYPE_NODE]))
			{
				$host_data['permission'] = $host_perm[$hostid][RESOURCE_TYPE_NODE];
			}
			else
			{
				if(is_null($host_data['nodeid'])) $host_data['nodeid'] = id2nodeid($host_data['groupid']);
				
				if(!isset($node_data[$host_data['nodeid']]))
				{
					$node_data = get_accessible_nodes_by_rights($rights,$user_type,
						PERM_DENY, PERM_MODE_GE, PERM_RES_DATA_ARRAY, $host_data['nodeid']);
				}
				if( !isset($node_data[$host_data['nodeid']]) || $user_type==USER_TYPE_ZABBIX_USER )
					$host_data['permission'] = PERM_DENY;
				else
					$host_data['permission'] = $node_data[$host_data['nodeid']]['permission'];
			}
			
			if(eval('return ('.$host_data["permission"].' '.perm_mode2comparator($perm_mode).' '.$perm.')? 0 : 1;'))
				continue;

			$result[$host_data['hostid']] = eval('return '.$resdata.';');

		}

		if($perm_res == PERM_RES_STRING_LINE) 
		{
			if(count($result) == 0) 
				$result = '-1';
			else
				$result = implode(',',$result);
		}

		return $result;
	}
	function	get_accessible_groups_by_rights(&$rights,$user_type,$perm,$perm_mode=null,$perm_res=null,$nodeid=null)
	{
		if(is_null($perm_mode)) $perm_mode=PERM_MODE_GE;
		if(is_null($perm_res))	$perm_res=PERM_RES_STRING_LINE;

		$result= array();

		switch($perm_res)
		{
			case PERM_RES_DATA_ARRAY:	$resdata = '$group_data'; break;
			default:			$resdata = '$group_data["groupid"]'; break;
		}

		$where = array();

		if ( !is_null($nodeid) )	array_push($where, DBin_node('g.groupid', $nodeid));
	
		if(count($where)) 	$where = ' where '.implode(' and ',$where);
		else			$where = '';

		$group_perm = array();
		foreach($rights as $right)
		{
			if($right['type'] != RESOURCE_TYPE_GROUP) continue;
			$group_perm[$right['id']] = $right['permission'];
		}

		$db_groups = DBselect('select n.nodeid as nodeid,n.name as node_name, g.*, '.PERM_DENY.' as permission from groups g '.
				' left join nodes n on '.DBid2nodeid('g.groupid').'=n.nodeid '.
				$where.' order by n.name, g.name');

		while($group_data = DBfetch($db_groups))
		{
			if(isset($group_perm[$group_data['groupid']]))
			{
				$group_data['permission'] = $group_perm[$group_data['groupid']];
			}
			else
			{
				if(is_null($group_data['nodeid'])) $group_data['nodeid'] = id2nodeid($group_data['groupid']);
				
				if(!isset($node_data[$group_data['nodeid']]))
				{
					$node_data = get_accessible_nodes_by_rights($rights,$user_type,
						PERM_DENY, PERM_MODE_GE, PERM_RES_DATA_ARRAY, $group_data['nodeid']);
				}
				if( !isset($node_data[$group_data['nodeid']]) || $user_type==USER_TYPE_ZABBIX_USER )
					$group_data['permission'] = PERM_DENY;
				else
					$group_data['permission'] = $node_data[$group_data['nodeid']]['permission'];
			}
					
			if(eval('return ('.$group_data["permission"].' '.perm_mode2comparator($perm_mode).' '.$perm.')? 0 : 1;'))
				continue;

			$result[$group_data["groupid"]] = eval('return '.$resdata.';');
		}

		if($perm_res == PERM_RES_STRING_LINE) 
		{
			if(count($result) == 0) 
				$result = '-1';
			else
				$result = implode(',',$result);
		}

		return $result;
	}

	function	get_accessible_nodes_by_rights(&$rights,$user_type,$perm,$perm_mode=null,$perm_res=null,$nodeid=null)
	{
		global $ZBX_LOCALNODEID;

		if(is_null($perm_mode)) $perm_mode=PERM_MODE_GE;
		if(is_null($perm_res))	$perm_res=PERM_RES_STRING_LINE;

		$result= array();

		if(is_null($user_type)) $user_type = USER_TYPE_ZABBIX_USER;

		switch($perm_res)
		{
			case PERM_RES_DATA_ARRAY:	$resdata = '$node_data'; break;
			default:			$resdata = '$node_data["nodeid"]'; break;
		}

		if(is_null($nodeid))		$where_nodeid = '';
		else if(is_array($nodeid))	$where_nodeid = ' where n.nodeid in ('.implode(',', $nodeid).') ';
		else 				$where_nodeid = ' where  n.nodeid in ('.$nodeid.') ';

		$node_perm = array();
		foreach($rights as $right)
		{
			if($right['type'] != RESOURCE_TYPE_NODE) continue;
			$node_perm[$right['id']] = $right['permission'];
		}

		$db_nodes = DBselect('select n.*, '.PERM_DENY.' as permission from nodes n '.$where_nodeid.' order by n.name');

		while(($node_data = DBfetch($db_nodes)) || (!isset($do_break) && !ZBX_DISTRIBUTED))
		{
			if(!ZBX_DISTRIBUTED)
			{
				if(!$node_data)
				{
					$node_data = array(
						'nodeid'	=> $ZBX_LOCALNODEID,
						'name'		=> 'local',
						'permission'	=> PERM_READ_WRITE
						);

					$do_break = true;

					if(is_array($nodeid) && !in_array($node_data['nodeid'],$nodeid))	continue;
					else if(isset($nodeid) and $node_data['nodeid'] != $nodeid)		continue;
				}
				else
				{
					$node_perm[$node_data['nodeid']] = PERM_DENY;
				}
			}

			if(isset($node_perm[$node_data['nodeid']]))
				$node_data['permission'] = $node_perm[$node_data['nodeid']];
			elseif($node_data['nodeid'] == $ZBX_LOCALNODEID || $user_type == USER_TYPE_SUPER_ADMIN)
			/* for local node or superuser default permission is READ_WRITE */
					$node_data['permission'] = PERM_READ_WRITE;


			/* special processing for PERM_READ_LIST*/
			if(PERM_DENY == $node_data['permission'] && PERM_READ_LIST == $perm)
			{
				$groups = get_accessible_groups_by_rights($rights,$user_type,
					$perm, PERM_MODE_GE, PERM_RES_DATA_ARRAY, $node_data['nodeid']);
				if(count($groups) == 0)  continue;
			}
			else
			{
				if(eval('return ('.$node_data["permission"].' '.perm_mode2comparator($perm_mode).' '.$perm.')? 0 : 1;'))
					continue;
			}

			$result[$node_data["nodeid"]] = eval('return '.$resdata.';');
		}

		if($perm_res == PERM_RES_STRING_LINE) 
		{
			if(count($result) == 0) 
				$result = '-1';
			else
				$result = implode(',',$result);
		}

		return $result;
	}

?>
