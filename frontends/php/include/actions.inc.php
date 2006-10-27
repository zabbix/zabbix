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
	function	action_accessiable($actionid,$perm)
	{
		global $USER_DETAILS;

		$result = false;

		if(DBselect("select actionid from actions where actionid=".$actionid.
			" and ".DBid2nodeid('actionid')." in (".get_accessible_nodes_by_user($USER_DETAILS,$perm).")"))
		{
			$result = true;
			
			$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY, PERM_MODE_LT);
			$denyed_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY, PERM_MODE_LT);
			
			$db_result = DBselect("select * from conditions where actionid=".$actionid);
			while(($ac_data = DBfetch($db_result)) && $result)
			{
				if($ac_data['operator'] != 0) continue;

				switch($ac_data['conditiontype'])
				{
					case CONDITION_TYPE_GROUP:
						if(in_array($ac_data['value'],explode(',',$denyed_groups)))
						{
							$result = false;
						}
						break;
					case CONDITION_TYPE_HOST:
						if(in_array($ac_data['value'],explode(',',$denyed_hosts)))
						{
							$result = false;
						}
						break;
					case CONDITION_TYPE_TRIGGER:
						if(!DBfetch(DBselect("select distinct t.*".
							" from triggers t,items i,functions f".
							" where f.itemid=i.itemid and t.triggerid=f.triggerid".
							" and i.hostid not in (".$denyed_hosts.") and t.triggerid=".$ac_data['value'])))
						{
							$result = false;
						}
						break;
				}
			}
		}
		return $result;
	}

	function	get_action_by_actionid($actionid)
	{
		$sql="select * from actions where actionid=$actionid"; 
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		else
		{
			error("No action with actionid=[$actionid]");
		}
		return	$result;
	}

	# Add Action

	function	add_action($actiontype,$userid,$subject,$message,$recipient,$maxrepeats,$repeatdelay,$status,$scripts)
	{
		// TODO check permission by new value.

		if($actiontype == ACTION_TYPE_MESSAGE)
		{
			$scripts = "";
		}
		elseif($actiontype == ACTION_TYPE_COMMAND)
		{
			$subject = $message = "";
			$userid = 0;			
			$recipient = 0;	
			if(!check_commands($scripts))	return FALSE;
		}
		$actionid=get_dbid("actions","actionid");
		$sql="insert into actions (actionid,actiontype,userid,subject,message,recipient,".
			"maxrepeats,repeatdelay,status,scripts) values ($actionid,$actiontype,$userid,".zbx_dbstr($subject).",".
			zbx_dbstr($message).",$recipient,$maxrepeats,$repeatdelay,$status,".zbx_dbstr($scripts).")";
		$result=DBexecute($sql);
		if(!$result)
			return $result;
		return $actionid;
	}

	# Update Action

	function	update_action($actionid,$actiontype,$userid,$subject,$message,$recipient,$maxrepeats,$repeatdelay,$status,$scripts)
	{
		// TODO check permission by new value.

		if($actiontype == ACTION_TYPE_MESSAGE)
		{
			$scripts = "";
		}
		elseif($actiontype == ACTION_TYPE_COMMAND)
		{
			$subject = $message = "";
			$userid = 0;
			$recipient = 0;	
			if(!check_commands($scripts))	return FALSE;
		}

		$result=DBexecute("update actions set actiontype=$actiontype,userid=$userid,subject=".zbx_dbstr($subject).",message=".zbx_dbstr($message).",recipient=$recipient,maxrepeats=$maxrepeats, repeatdelay=$repeatdelay,status=$status,scripts=".zbx_dbstr($scripts)." where actionid=$actionid");
		return $result;
	}

	# Delete Action

	function	delete_action( $actionid )
	{
		$return = DBexecute('delete from conditions where actionid='.$actionid);

		if($return)
			$result = DBexecute('delete from alerts where actionid='.$actionid);

		if($return)
			$result = DBexecute('delete from actions where actionid='.$actionid);

		return $result;
	}

	function	get_source_description($source)
	{
		$desc = S_UNKNOWN;
		if($source==1)
		{
			$desc=S_IT_SERVICE;
		}
		elseif($source==0)
		{
			$desc=S_TRIGGER;
		}
		return $desc;
	}

	function	get_condition_desc($conditiontype, $operator, $value)
	{
		if($operator == CONDITION_OPERATOR_EQUAL)
		{
			$op="=";
		}
		else if($operator == CONDITION_OPERATOR_NOT_EQUAL)
		{
			$op="<>";
		}
		else if($operator == CONDITION_OPERATOR_LIKE)
		{
			$op="like";
		}
		else if($operator == CONDITION_OPERATOR_NOT_LIKE)
		{
			$op="not like";
		}
		else if($operator == CONDITION_OPERATOR_IN)
		{
			$op="in";
		}
		else if($operator == CONDITION_OPERATOR_MORE_EQUAL)
		{
			$op=">=";
		}
		else if($operator == CONDITION_OPERATOR_LESS_EQUAL)
		{
			$op="<=";
		}

		$desc=S_UNKNOWN;
		if($conditiontype==CONDITION_TYPE_GROUP)
		{
			$group=get_hostgroup_by_groupid($value);
			if($group) $desc=S_HOST_GROUP." $op "."\"".$group["name"]."\"";
		}
		if($conditiontype==CONDITION_TYPE_TRIGGER)
		{
			$desc=S_TRIGGER." $op "."\"".expand_trigger_description($value)."\"";
		}
		else if($conditiontype==CONDITION_TYPE_HOST)
		{
			$host=get_host_by_hostid($value);
			if($host) $desc=S_HOST." $op "."\"".$host["host"]."\"";
		}
		else if($conditiontype==CONDITION_TYPE_TRIGGER_NAME)
		{
			$desc=S_TRIGGER_DESCRIPTION." $op "."\"".$value."\"";
		}
		else if($conditiontype==CONDITION_TYPE_TRIGGER_VALUE)
		{
			if($value==0)
				$desc=S_TRIGGER_VALUE." $op "."\"OFF\"";
			if($value==1)
				$desc=S_TRIGGER_VALUE." $op "."\"ON\"";
		}
		else if($conditiontype==CONDITION_TYPE_TRIGGER_SEVERITY)
		{
			$desc=S_TRIGGER_SEVERITY." $op "."\"".get_severity_description($value)."\"";
		}
		else if($conditiontype==CONDITION_TYPE_TIME_PERIOD)
		{
			$desc=S_TIME." $op "."\"".$value."\"";
		}
		else
		{
		}
		return $desc;
	}

	# Add Action's condition

	function	add_action_condition($actionid, $conditiontype, $operator, $value)
	{
		$conditionid=get_dbid("conditions","conditionid");
		$sql="insert into conditions (conditionid,actionid,conditiontype,operator,value)".
			" values ($conditionid,$actionid,$conditiontype,$operator,".zbx_dbstr($value).")";
		$result=DBexecute($sql);
		if(!$result)
			return $result;
		return $conditionid;
	}

	function	update_action_status($actionid, $status)
	{
		return DBexecute("update actions set status=$status where actionid=$actionid");
	}

	function check_commands($commands)
	{
		$cmd_list = split("\n",$commands);
		foreach($cmd_list as $cmd)
		{
			$cmd = trim($cmd, "\x00..\x1F");
			if(!ereg("^([0-9a-zA-Z\_\.-]{1,})(:|#)[[:print:]]*$",$cmd,$cmd_items)){
				error("incorrect command: '$cmd'");
				return FALSE;
			}
			if($cmd_items[2] == "#")
			{ // group
				if(!DBfetch(DBselect("select groupid from groups where name=".zbx_dbstr($cmd_items[1]))))
				{
					error("Uncnown group name: '".$cmd_items[1]."' in command ".$cmd."'");
					return FALSE;
				}
			}
			elseif($cmd_items[2] == ":")
			{ // host
				if(!DBfetch(DBselect("select hostid from hosts where host=".zbx_dbstr($cmd_items[1]))))
				{
					error("Uncnown host name '".$cmd_items[1]."' in command '".$cmd."'");
					return FALSE;
				}
			}
		}
		return TRUE;
	}

	function get_history_of_actions($start,$num)
	{
		global $USER_DETAILS;
		
		$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_ONLY, PERM_MODE_LT);
		
		$result=DBselect("select a.alertid,a.clock,mt.description,a.sendto,a.subject,a.message,a.status,a.retries,".
				"a.error from alerts a,media_type mt,functions f,items i ".
				" where mt.mediatypeid=a.mediatypeid and a.triggerid=f.triggerid and f.itemid=i.itemid ".
				" and i.hostid not in (".$denyed_hosts.")".
				" order by a.clock".
				" desc",
			10*$start+$num);

		$table = new CTableInfo(S_NO_ACTIONS_FOUND);
		$table->SetHeader(array(S_TIME, S_TYPE, S_STATUS, S_RECIPIENTS, S_SUBJECT, S_MESSAGE, S_ERROR));
		$col=0;
		$skip=$start;
		while(($row=DBfetch($result))&&($col<$num))
		{
			if($skip > 0) 
			{
				$skip--;
				continue;
			}
			$time=date("Y.M.d H:i:s",$row["clock"]);

			if($row["status"] == 1)
			{
				$status=new CCol(S_SENT,"off");
			}
			else
			{
				$status=new CCol(S_NOT_SENT,"on");
			}
			$sendto=htmlspecialchars($row["sendto"]);
			$subject="<pre>".htmlspecialchars($row["subject"])."</pre>";
			$message="<pre>".htmlspecialchars($row["message"])."</pre>";
			if($row["error"] == "")
			{
				$error=new CSpan(SPACE,"off");
			}
			else
			{
				$error=new CSpan($row["error"],"on");
			}
			$table->AddRow(array(
				$time,
				$row["description"],
				$status,
				$sendto,
				$subject,
				$message,
				$error));
			$col++;
		}

		return $table;
	}
?>
