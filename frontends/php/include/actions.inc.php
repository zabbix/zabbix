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
	# Update Action

	function	update_action( $actionid, $triggerid, $userid, $good, $delay, $subject, $message, $scope, $severity, $recipient, $usrgrpid)
	{
		delete_action($actionid);
		return add_action( $triggerid, $userid, $good, $delay, $subject, $message, $scope, $severity, $recipient, $usrgrpid);
	}

	# Add Action

	function	add_action( $triggerid, $userid, $good, $delay, $subject, $message, $scope, $severity, $recipient, $usrgrpid)
	{
		if(!check_right_on_trigger("A",$triggerid))
		{
                        error("Insufficient permissions");
                        return 0;
		}

		if($recipient == RECIPIENT_TYPE_USER)
		{
			$id = $userid;
		}
		else
		{
			$id = $usrgrpid;
		}

		if($scope==2)
		{
			$sql="insert into actions (triggerid,userid,good,delay,nextcheck,subject,message,scope,severity,recipient) values (0,$id,$good,$delay,0,'*Automatically generated*','*Automatically generated*',$scope,$severity,$recipient)";
			$result=DBexecute($sql);
			return DBinsert_id($result,"actions","actionid");
		}
		elseif($scope==1)
		{
			$sql="select h.hostid from triggers t,hosts h,functions f,items i where f.triggerid=t.triggerid and h.hostid=i.hostid and i.itemid=f.itemid and t.triggerid=$triggerid";
//			echo "$sql<br>";
			$result=DBselect($sql);
			while($row=DBfetch($result))
			{
				$sql="insert into actions (triggerid,userid,good,delay,nextcheck,subject,message,scope,severity,recipient) values (".$row["hostid"].",$id,$good,$delay,0,'*Automatically generated*','*Automatically generated*',$scope,$severity,$recipient)";
//				echo "$sql<br>";
				DBexecute($sql);
			}
			return TRUE;
		}
		else
		{
			$sql="insert into actions (triggerid,userid,good,delay,nextcheck,subject,message,scope,severity,recipient) values ($triggerid,$id,$good,$delay,0,'$subject','$message',$scope,$severity,$recipient)";
			$result=DBexecute($sql);
			return DBinsert_id($result,"actions","actionid");
		}
	}

	# Delete Action by userid

	function	delete_actions_by_userid( $userid )
	{
		$sql="select actionid from actions where userid=$userid";
		$result=DBexecute($sql);
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$actionid=DBget_field($result,$i,0);
			delete_alert_by_actionid($actionid);
		}

		$sql="delete from actions where userid=$userid";
		return	DBexecute($sql);
	}

	# Delete Action

	function	delete_action( $actionid )
	{
		$sql="delete from actions where actionid=$actionid";
		$result=DBexecute($sql);

		return delete_alert_by_actionid($actionid);
	}

	# Add action to hardlinked hosts

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

	# Delete action from hardlinked hosts

	function	delete_action_from_templates($actionid)
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

		$hostid=$row["hostid"];

		$sql="select hostid,templateid,actions from hosts_templates where templateid=$hostid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if($row["actions"]&4 == 0)	continue;

			$sql="select distinct f.triggerid from functions f,items i,triggers t where t.description='".addslashes($trigger["description"])."' and t.triggerid=f.triggerid and i.itemid=f.itemid and i.hostid=".$row["hostid"];
			$result2=DBselect($sql);
			while($row2=DBfetch($result2))
			{
				$sql="select actionid from actions where triggerid=".$row2["triggerid"]." and subject='".addslashes($action["subject"])."' and message='".addslashes($action["message"])."' and userid=".$action["userid"]." and good=".$action["good"]." and scope=".$action["scope"]." and recipient=".$action["recipient"]." and severity=".$action["severity"];
				$result3=DBselect($sql);
				while($row3=DBfetch($result3))
				{
					delete_action($row3["actionid"]);
				}
			}
		}
	}
?>
