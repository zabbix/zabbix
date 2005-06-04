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
	# Add escalation definition

	function	add_escalation($name,$dflt)
	{
		if(!check_right("Configuration of Zabbix","U",0))
		{
			error("Insufficient permissions");
			return	0;
		}

		$sql="insert into escalations (name,dflt) values ('$name',$dflt)";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$escalationid=DBinsert_id($result,"escalation","escalationid");

		if($dflt==1)
		{
			$sql="update escalations set dflt=0 where escalationid<>$escalationid";
			$result=DBexecute($sql);
			info("Default escalation is set to '$name'");
		}
		
		return	$result;
	}

	# Update escalation definition

	function	update_escalation($escalationid,$name,$dflt)
	{
		if(!check_right("Configuration of Zabbix","U",0))
		{
			error("Insufficient permissions");
			return	0;
		}

		$sql="update escalations set name='$name',dflt=$dflt where escalationid=$escalationid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}

		if($dflt==1)
		{
			$sql="update escalations set dflt=0 where escalationid<>$escalationid";
			$result=DBexecute($sql);
			info("Default escalation is set to '$name'");
		}
		
		return	$result;
	}


	# Delete escalation definition

	function	delete_escalation($escalationid)
	{
		if(!check_right("Configuration of Zabbix","U",0))
		{
			error("Insufficient permissions");
			return	0;
		}

		$sql="delete from escalation_rules where escalationid=$escalationid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}

		$sql="delete from escalations where escalationid=$escalationid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}

		return	$result;
	}

	# Add escalation rule definition

	function	add_escalation_rule($escalationid,$level,$period,$delay,$actiontype)
	{
		if(!check_right("Configuration of Zabbix","U",0))
		{
			error("Insufficient permissions");
			return	0;
		}

		$sql="insert into escalation_rules (escalationid,level,period,delay,actiontype) values ($escalationid,$level,'$period',$delay,$actiontype)";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$escalationruleid=DBinsert_id($result,"escalation_rules","escalationruleid");

		return	$result;
	}

	# Update escalation rule definition

	function	update_escalation_rule($escalationruleid,$level,$period,$delay,$actiontype)
	{
		if(!check_right("Configuration of Zabbix","U",0))
		{
			error("Insufficient permissions");
			return	0;
		}

		$sql="update escalation_rules set level=$level,period='$period',delay=$delay,actiontype=$actiontype where escalationruleid=$escalationruleid";
		$result=DBexecute($sql);
		return	$result;
	}

	# Delete escalation rule definition

	function	delete_escalation_rule($escalationruleid)
	{
		if(!check_right("Configuration of Zabbix","U",0))
		{
			error("Insufficient permissions");
			return	0;
		}

		$sql="delete from escalation_rules where escalationruleid=$escalationruleid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}

		return	$result;
	}
?>
