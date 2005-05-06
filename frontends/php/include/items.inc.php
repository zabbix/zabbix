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
	# Add Item definition

	function	add_item($description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends)
	{
		global	$ERROR_MSG;

		if(!check_right("Item","A",0))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}

		$sql="select count(*) from items where hostid=$hostid and key_='$key'";
		$result=DBexecute($sql);
		if(DBget_field($result,0,0)>0)
		{
			$ERROR_MSG="An item with the same Key already exists for this host. The key must be unique.";
			return 0;
		}

		if($delay<1)
		{
			$ERROR_MSG="Delay cannot be less than 1 second";
			return 0;
		}

		if( ($snmp_port<1)||($snmp_port>65535))
		{
			$ERROR_MSG="Invalid SNMP port";
			return 0;
		}

		if($value_type == ITEM_VALUE_TYPE_STR)
		{
			$delta=0;
		}

		$key=addslashes($key);
		$description=addslashes($description);
		$snmpv3_securityname=addslashes($snmpv3_securityname);
		$snmpv3_authpassphrase=addslashes($snmpv3_authpassphrase);
		$snmpv3_privpassphrase=addslashes($snmpv3_privpassphrase);

		$sql="insert into items (description,key_,hostid,delay,history,nextcheck,status,type,snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,trends) values ('$description','$key',$hostid,$delay,$history,0,$status,$type,'$snmp_community','$snmp_oid',$value_type,'$trapper_hosts',$snmp_port,'$units',$multiplier,$delta,'$snmpv3_securityname',$snmpv3_securitylevel,'$snmpv3_authpassphrase','$snmpv3_privpassphrase','$formula',$trends)";
		$result=DBexecute($sql);
		return DBinsert_id($result,"items","itemid");
	}

	# Update Item status

	function	update_item_status($itemid,$status)
	{
		global	$ERROR_MSG;

                if(!check_right("Item","U",0))
		{
                        $ERROR_MSG="Insufficient permissions";
                        return 0;
		}
		if($status==ITEM_STATUS_ACTIVE)
			$sql="update items set status=$status,error=\"\" where itemid=$itemid";
		else
			$sql="update items set status=$status where itemid=$itemid";

		return	DBexecute($sql);
	}

	# Update Item definition

	function	update_item($itemid,$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends)
	{
		global	$ERROR_MSG;

		if(!check_right("Item","U",$itemid))
		{
			$ERROR_MSG="Insufficient permissions";
			return 0;
		}
		if($delay<1)
		{
			$ERROR_MSG="Delay cannot be less than 1 second";
			return 0;
		}

		if( ($snmp_port<1)||($snmp_port>65535))
		{
			$ERROR_MSG="Invalid SNMP port";
			return 0;
		}

		if($value_type == ITEM_VALUE_TYPE_STR)
		{
			$delta=0;
		}

		$key=addslashes($key);
		$description=addslashes($description);
		$snmpv3_securityname=addslashes($snmpv3_securityname);
		$snmpv3_authpassphrase=addslashes($snmpv3_authpassphrase);
		$snmpv3_privpassphrase=addslashes($snmpv3_privpassphrase);

		$sql="update items set description='$description',key_='$key',hostid=$hostid,delay=$delay,history=$history,nextcheck=0,status=$status,type=$type,snmp_community='$snmp_community',snmp_oid='$snmp_oid',value_type=$value_type,trapper_hosts='$trapper_hosts',snmp_port=$snmp_port,units='$units',multiplier=$multiplier,delta=$delta,snmpv3_securityname='$snmpv3_securityname',snmpv3_securitylevel=$snmpv3_securitylevel,snmpv3_authpassphrase='$snmpv3_authpassphrase',snmpv3_privpassphrase='$snmpv3_privpassphrase',formula='$formula',trends=$trends where itemid=$itemid";
		return	DBexecute($sql);
	}

	# Add item to hardlinked hosts

	function	add_item_to_templates($itemid)
	{
		if($itemid<=0)
		{
			return;
		}

		$item=get_item_by_itemid($itemid);

		$sql="select hostid,templateid,items from hosts_templates where templateid=".$item["hostid"];
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if($row["items"]&1 == 0)	continue;
			$sql="select itemid from items where key_=\"".$item["key_"]."\" and hostid=".$row["hostid"];
			$result2=DBselect($sql);
			if(DBnum_rows($result2)==0)
			{
				add_item($item["description"],$item["key_"],$row["hostid"],$item["delay"],$item["history"],$item["status"],$item["type"],$item["snmp_community"],$item["snmp_oid"],$item["value_type"],$item["trapper_hosts"],$item["snmp_port"],$item["units"],$item["multiplier"],$item["delta"],$item["snmpv3_securityname"],$item["snmpv3_securitylevel"],$item["snmpv3_authpassphrase"],$item["snmpv3_privpassphrase"],$item["formula"],$item["trends"]);
			}
		}
	}

	# Add item to hardlinked hosts

	function	delete_item_from_templates($itemid)
	{
		if($itemid<=0)
		{
			return;
		}

		$item=get_item_by_itemid($itemid);

		$sql="select hostid,templateid,items from hosts_templates where templateid=".$item["hostid"];
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if($row["items"]&4 == 0)	continue;
			$sql="select itemid from items where key_=\"".$item["key_"]."\" and hostid=".$row["hostid"];
			$result2=DBselect($sql);
			while($row2=DBfetch($result2))
			{
				delete_item($row2["itemid"]);
			}
		}
	}

	# Update item in hardlinked hosts

	function	update_item_in_templates($itemid)
	{
		if($itemid<=0)
		{
			return;
		}

		$item=get_item_by_itemid($itemid);

		$sql="select hostid,templateid,items from hosts_templates where templateid=".$item["hostid"];
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if($row["items"]&2 == 0)	continue;
			$sql="select itemid from items where key_=\"".$item["key_"]."\" and hostid=".$row["hostid"];
			$result2=DBselect($sql);
			if(DBnum_rows($result2)==1)
			{
				$row2=DBfetch($result2);
				update_item($row2["itemid"],$item["description"],$item["key_"],$row["hostid"],$item["delay"],$item["history"],$item["status"],$item["type"],$item["snmp_community"],$item["snmp_oid"],$item["value_type"],$item["trapper_hosts"],$item["snmp_port"],$item["units"],$item["multiplier"],$item["delta"],$item["snmpv3_securityname"],$item["snmpv3_securitylevel"],$item["snmpv3_authpassphrase"],$item["snmpv3_privpassphrase"],$item["formula"],$item["trends"]);
			}
		}
	}

	# Activate Item

	function	activate_item($itemid)
	{
		$sql="update items set status=".ITEM_STATUS_ACTIVE." where itemid=$itemid";
		return	DBexecute($sql);
	}

	# Disable Item

	function	disable_item($itemid)
	{
		$sql="update items set status=".ITEM_STATUS_DISABLED." where itemid=$itemid";
		return	DBexecute($sql);
	}

	function	get_item_by_itemid($itemid)
	{
		global	$ERROR_MSG;

		$sql="select * from items where itemid=$itemid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			$ERROR_MSG="No item with itemid=[$itemid]";
		}
		return	$item;
	}

	# Delete Item definition

	function	delete_item($itemid)
	{
		$result=delete_triggers_by_itemid($itemid);
		if(!$result)
		{
			return	$result;
		}
		$result=delete_trends_by_itemid($itemid);
		$result=delete_history_by_itemid($itemid);
		$sql="delete from graphs_items where itemid=$itemid";
		if(!$result)
		{
			return	$result;
		}
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$sql="delete from items where itemid=$itemid";
		return	DBexecute($sql);
	}
?>
