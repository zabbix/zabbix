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


#include <stdlib.h>
#include <stdio.h>

#include <string.h>
#include <strings.h>

#include "db.h"
#include "log.h"
#include "zlog.h"
#include "common.h"


	function	add_action_to_linked_hosts($actionid,$hostid=0)
	{
		if($actionid<=0)
		{
			return;
		}

		$action=get_action_by_actionid($actionid);
		$trigger=get_trigger_by_triggerid($action["triggerid"]);

		$sql="select distinct h.hostid from hosts h,functions f, items i where i.itemid=f.itemid and h.hostid=i.hostid and f.triggerid=".$action["triggerid"];
		$result=DBselect($sql);
		if(DBnum_rows($result)!=1)
		{
			return;
		}
		$row=DBfetch($result);

		$host_template=get_host_by_hostid($row["hostid"]);

		if($hostid==0)
		{
			$sql="select hostid,templateid,actions from hosts_templates where templateid=".$row["hostid"];
		}
		else
		{
			$sql="select hostid,templateid,actions from hosts_templates where hostid=$hostid and templateid=".$row["hostid"];
		}
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if($row["actions"]&1 == 0)	continue;

			$sql="select distinct f.triggerid from functions f,items i,triggers t where t.description='".addslashes($trigger["description"])."' and t.triggerid=f.triggerid and i.itemid=f.itemid and i.hostid=".$row["hostid"];
			$result2=DBselect($sql);
			while($row2=DBfetch($result2))
			{
				$host=get_host_by_hostid($row["hostid"]);
				$message=str_replace("{".$host_template["host"].":", "{".$host["host"].":", $action["message"]);
				add_action($row2["triggerid"], $action["userid"], $action["good"], $action["delay"], $action["subject"], $message, $action["scope"], $action["severity"], $action["recipient"], $action["userid"]);
			}
		}
	}
