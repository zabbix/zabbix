<?php
/*
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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
		global	$ERROR_MSG;

		if(!check_right_on_trigger("A",$triggerid))
		{
                        $ERROR_MSG="Insufficient permissions";
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
			return	DBexecute($sql);
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
			return	DBexecute($sql);
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
?>
