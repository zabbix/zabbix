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

	function get_history_of_events($start,$num, $groupid=0, $hostid=0)
	{
		if($hostid > 0)
		{
			$sql="select distinct a.clock,a.value,a.triggerid from alarms a,functions f,items i where a.triggerid=f.triggerid and f.itemid=i.itemid and i.hostid=".$hostid." order by clock desc";
		}
		elseif($groupid > 0)
		{
			$sql="select distinct a.clock,a.value,a.triggerid from alarms a,functions f,items i,hosts_groups hg where a.triggerid=f.triggerid and f.itemid=i.itemid and i.hostid=hg.hostid and hg.groupid=".$groupid." order by clock desc";
		}
		else
		{
			$sql="select distinct triggerid,clock,value from alarms order by clock desc";
		}
		$result=DBselect($sql,10*($start+$num));
       
		$table = new CTableInfo(S_NO_EVENTS_FOUND); 
		$table->setHeader(array(S_TIME, S_DESCRIPTION, S_VALUE, S_SEVERITY));
		$col=0;
		$skip=$start;
		while(($row=DBfetch($result))&&($col<$num))
		{
			if(!check_right_on_trigger("R",$row["triggerid"]))
			{
				continue;
			}
			if($skip > 0) 
			{
				$skip--;
				continue;
			}
			$description=expand_trigger_description($row["triggerid"]);
			$description=new CLink($description,"alarms.php?triggerid=".$row["triggerid"],"action");

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

			$trigger = get_trigger_by_triggerid($row["triggerid"]);

			if($trigger["priority"]==0)     $priority=S_NOT_CLASSIFIED;
			elseif($trigger["priority"]==1) $priority=new CCol(S_INFORMATION, "information");
			elseif($trigger["priority"]==2) $priority=new CCol(S_WARNING,"warning");
			elseif($trigger["priority"]==3) $priority=new CCol(S_AVERAGE,"average");
			elseif($trigger["priority"]==4) $priority=new CCol(S_HIGH,"high");
			elseif($trigger["priority"]==5) $priority=new CCol(S_DISASTER,"disaster");
			else                            $priority=$trigger["priority"];

			$table->addRow(array(
			date("Y.M.d H:i:s",$row["clock"]),
			$description,
			$value,
			$priority));

			$col++;
		}
		return $table;
	}
?>
