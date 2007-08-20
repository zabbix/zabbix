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
	require_once 	"include/db.inc.php";
?>
<?php
	function	detect_node_type($node_data)
	{
		global $ZBX_CURMASTERID;

		if($node_data['nodeid'] == get_current_nodeid(false))		$node_type = ZBX_NODE_LOCAL;
		else if($node_data['nodeid'] == $ZBX_CURMASTERID)		$node_type = ZBX_NODE_MASTER;
		else if($node_data['masterid'] == get_current_nodeid(false))	$node_type = ZBX_NODE_REMOTE;
		else $node_type = -1;

		return $node_type;
	}

	function	node_type2str($node_type)
	{
		$result = '';
		switch($node_type)
		{
			case ZBX_NODE_REMOTE:	$result = S_REMOTE;	break;
			case ZBX_NODE_MASTER:	$result = S_MASTER;	break;
			case ZBX_NODE_LOCAL:	$result = S_LOCAL;	break;
			default:		$result = S_UNKNOWN;	break;
		}

		return $result;
	}

	function	add_node($new_nodeid,$name,$timezone,$ip,$port,$slave_history,$slave_trends,$node_type)
	{
		global $ZBX_CURMASTERID;

		if( !eregi('^'.ZBX_EREG_NODE_FORMAT.'$', $name) )
		{
			error("Incorrect characters used for Node name");
			return false;
		}

		switch($node_type)
		{
			case ZBX_NODE_REMOTE:
				$masterid = get_current_nodeid(false);
				$nodetype = 0;
				break;
			case ZBX_NODE_MASTER:
				$masterid = 0;
				$nodetype = 0;
				if($ZBX_CURMASTERID)
				{
					error('Master node already exist');
					return false;
				}
				break;
			case ZBX_NODE_LOCAL:
				$masterid = $ZBX_CURMASTERID;
				$nodetype = 1;
				break;
			default:
				error('Incorrect node type');
				return false;
				break;
		}

		if(DBfetch(DBselect('select nodeid from nodes where nodeid='.$new_nodeid)))
		{
			error('Node with same ID already exist.');
			return false;
		}

		$result = DBexecute('insert into nodes (nodeid,name,timezone,ip,port,slave_history,slave_trends,'.
				'event_lastid,history_lastid,nodetype,masterid) values ('.
				$new_nodeid.','.zbx_dbstr($name).','.$timezone.','.zbx_dbstr($ip).','.$port.','.$slave_history.','.$slave_trends.','.
				'0,0,'.$nodetype.','.$masterid.')');

		if($result && $node_type == ZBX_NODE_MASTER)
		{
			DBexecute('update nodes set masterid='.$new_nodeid.' where nodeid='.get_current_nodeid(false));
			$ZBX_CURMASTERID = $new_nodeid; /* applay Master node for this script */
		}

		return ($result ? $new_nodeid : $result);
	}

	function	update_node($nodeid,$new_nodeid,$name,$timezone,$ip,$port,$slave_history,$slave_trends)
	{
		if( !eregi('^'.ZBX_EREG_NODE_FORMAT.'$', $name) )
		{
			error("Incorrect characters used for Node name");
			return false;
		}

		$result = DBexecute('update nodes set nodeid='.$new_nodeid.',name='.zbx_dbstr($name).','.
				'timezone='.$timezone.',ip='.zbx_dbstr($ip).',port='.$port.','.
				'slave_history='.$slave_history.',slave_trends='.$slave_trends.
				' where nodeid='.$nodeid);
		return $result;
	}

	function	delete_node($nodeid)
	{
		$result = false;
		$node_data = DBfetch(DBselect('select * from nodes where nodeid='.$nodeid));

		$node_type = detect_node_type($node_data);

		if($node_type == ZBX_NODE_LOCAL)
		{
			error('Unable to remove local node');
		}
		else
		{
			$housekeeperid = get_dbid('housekeeper','housekeeperid');
			$result = (
				DBexecute("insert into housekeeper (housekeeperid,tablename,field,value)".
					" values ($housekeeperid,'nodes','nodeid',$nodeid)") &&
				DBexecute('delete from nodes where nodeid='.$nodeid) &&
				DBexecute('update nodes set masterid=0 where masterid='.$nodeid)
				);
			error('Please be aware that database still contains data related to the deleted Node');
		}
		return $result;
	}

	function	get_node_by_nodeid($nodeid)
	{
		return DBfetch(DBselect('select * from nodes where nodeid='.$nodeid));
	}

	function	get_node_path($nodeid, $result='/')
	{
		if($node_data = get_node_by_nodeid($nodeid))
		{
			if($node_data['masterid'])
			{
				$result = get_node_path($node_data['masterid'],$result);
			}
			$result .= $node_data['name'].'/';
		}
		return $result;
	}
?>
