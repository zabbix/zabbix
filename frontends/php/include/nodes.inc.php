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
	function	add_node($name,$timezone,$ip,$port,$slave_history,$slave_trends)
	{
		global $ZBX_CURNODEID;

		$nodeid = DBfetch(DBselect('select max(nodeid) as max from nodes'));
		$nodeid = $nodeid['max'] + 1;
		$result = DBexecute('insert into nodes (nodeid,name,timezone,ip,port,slave_history,slave_trends,'.
				'event_lastid,history_lastid,nodetype,masterid) values ('.
				$nodeid.','.zbx_dbstr($name).','.$timezone.','.zbx_dbstr($ip).','.$port.','.$slave_history.','.$slave_trends.','.
				'0,0,0,'.$ZBX_CURNODEID.')');

		return ($result ? $nodeid : $result);
	}

	function	update_node($nodeid,$name,$timezone,$ip,$port,$slave_history,$slave_trends)
	{
		$result = DBexecute('update nodes set name='.zbx_dbstr($name).',timezone='.$timezone.',ip='.zbx_dbstr($ip).',port='.$port.','.
				'slave_history='.$slave_history.',slave_trends='.$slave_trends.
				' where nodeid='.$nodeid);
		return $result;
	}

	function	delete_node($nodeid)
	{
		$result = false;
		if(!DBfetch(DBselect('select * from nodes where masterid='.$nodeid)))
		{
			$result = DBexecute('delete from nodes where nodeid='.$nodeid);
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
