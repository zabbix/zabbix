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

	function get_history_of_events($start,$num, $groupid=0, $hostid=0, $nodeid=null)
	{
		global $ZBX_CURNODEID;
		global $USER_DETAILS;
		
		if(is_null($nodeid)) $nodeid = $ZBX_CURNODEID;
			
		$sql_from = $sql_cond = "";
		
		if($hostid > 0)
		{
			$sql_cond = " and h.hostid=".$hostid;
		}
		elseif($groupid > 0)
		{
			$sql_from = ", hosts_groups hg ";
			$sql_cond = " and h.hostid=hg.hostid and hg.groupid=".$groupid;
		}

		$result = DBselect(" select t.triggerid,t.priority,t.description,h.host,e.clock,e.value ".
			" from events e, triggers t, functions f, items i, hosts h ".$sql_from.
			" where ".DBid2nodeid("t.triggerid")."=".$nodeid.
			" and e.triggerid=t.triggerid and t.triggerid=f.triggerid and f.itemid=i.itemid ".
			" and i.hostid=h.hostid ".$sql_cond." and h.status=".HOST_STATUS_MONITORED.
			" and h.hostid not in (".get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE, PERM_MODE_LT).") ".
			" order by e.clock desc,h.host,t.priority,t.description,t.triggerid ",
			10*($start+$num)
			);
       
		$table = new CTableInfo(S_NO_EVENTS_FOUND); 
		$table->SetHeader(array(S_TIME, $hostid == 0 ? S_HOST : null, S_DESCRIPTION, S_VALUE, S_SEVERITY));
		$col=0;
		
		$skip = $start;
		while(($row=DBfetch($result))&&($col<$num))
		{
			if($skip > 0) 
			{
				$skip--;
				continue;
			}

			if($row["value"] == 0)
			{
				$value=new CCol(S_OFF,"off");
			}
			elseif($row["value"] == 1)
			{
				$value=new CCol(S_ON,"on");
			}
			else
			{
				$value=new CCol(S_UNKNOWN_BIG,"unknown");
			}

			$table->AddRow(array(
				date("Y.M.d H:i:s",$row["clock"]),
				$hostid == 0 ? $row['host'] : null,
				new CLink(expand_trigger_description_by_data($row),"tr_events.php?triggerid=".$row["triggerid"],"action"),
				$value,
				new CCol(get_severity_description($row["priority"]), get_severity_style($row["priority"]))));

			$col++;
		}
		return $table;
	}
?>
