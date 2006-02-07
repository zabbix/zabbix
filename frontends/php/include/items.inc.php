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
	# Update Item definition for selected group

	function	update_item_in_group($groupid,$itemid,$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt)
	{
		$sql="select i.itemid,i.hostid from hosts_groups hg,items i where hg.groupid=$groupid and i.key_='".zbx_ads($key)."' and hg.hostid=i.hostid";
		$result=DBexecute($sql);
		while($row=DBfetch($result))
		{
			update_item($row["itemid"],$description,$key,$row["hostid"],$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt);
		}
		return 1;
	}

	# Delete Item definition from selected group

	function	delete_item_from_group($groupid,$itemid)
	{
		if(!isset($itemid))
		{
			return 0;
		}

		$item=get_item_by_itemid($itemid);
		if(!$item)
		{
			return 0;
		}

		$sql="select i.itemid from hosts_groups hg,items i where hg.groupid=$groupid and i.key_='".zbx_ads($item["key_"])."' and hg.hostid=i.hostid";
		$result=DBexecute($sql);
		while($row=DBfetch($result))
		{
			delete_item_from_templates($row["itemid"]);
			delete_item($row["itemid"]);
		}
		return 1;
	}

	# Add Item definition to selected group

	function	add_item_to_group($groupid,$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt)
	{
		$sql="select hostid from hosts_groups where groupid=$groupid";
		$result=DBexecute($sql);
		while($row=DBfetch($result))
		{
			add_item($description,$key,$row["hostid"],$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt);
		}
		return 1;
	}

	# Add Item definition

	function	add_item($description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt)
	{
		if(!check_right("Item","A",0))
		{
			error("Insufficient permissions");
			return 0;
		}

		$host=get_host_by_hostid($hostid);

		$sql="select count(*) as cnt from items where hostid=$hostid and key_='".zbx_ads($key)."'";
		$result=DBexecute($sql);
		$row = DBfetch($result);
		if($row["cnt"]>0)
		{
			error("An item with the same Key already exists for host ".$host["host"].". The key must be unique.");
			return 0;
		}

		if($delay<1)
		{
			error("Delay cannot be less than 1 second");
			return 0;
		}

		if( ($snmp_port<1)||($snmp_port>65535))
		{
			error("Invalid SNMP port");
			return 0;
		}

		if($value_type == ITEM_VALUE_TYPE_STR)
		{
			$delta=0;
		}

		$sql="insert into items (description,key_,hostid,delay,history,nextcheck,status,type,snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,trends,logtimefmt) values ('".zbx_ads($description)."','".zbx_ads($key)."',$hostid,$delay,$history,0,$status,$type,'".zbx_ads($snmp_community)."','".zbx_ads($snmp_oid)."',$value_type,'".zbx_ads($trapper_hosts)."',".zbx_ads($snmp_port).",'".zbx_ads($units)."',$multiplier,$delta,'".zbx_ads($snmpv3_securityname)."',".zbx_ads($snmpv3_securitylevel).",'".zbx_ads($snmpv3_authpassphrase)."','".zbx_ads($snmpv3_privpassphrase)."','".zbx_ads($formula)."',$trends,'".zbx_ads($logtimefmt)."')";
		$result=DBexecute($sql);
		if($result)
		{
			$host=get_host_by_hostid($hostid);
			info("Added new item ".$host["host"].":$key");
		}
		return DBinsert_id($result,"items","itemid");
	}

	# Update Item status

	function	update_item_status($itemid,$status)
	{
                if(!check_right("Item","U",0))
		{
                        error("Insufficient permissions");
                        return 0;
		}
		if($status==ITEM_STATUS_ACTIVE)
			$sql="update items set status=$status,error='' where itemid=$itemid";
		else
			$sql="update items set status=$status where itemid=$itemid";

		return	DBexecute($sql);
	}

	# Update Item definition

	function	update_item($itemid,$description,$key,$hostid,$delay,$history,$status,$type,
		$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,
		$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,
		$formula,$trends,$logtimefmt)
	{
		if(!check_right("Item","U",$itemid))
		{
			error("Insufficient permissions");
			return 0;
		}
		if($delay<1)
		{
			error("Delay cannot be less than 1 second");
			return 0;
		}

		if( ($snmp_port<1)||($snmp_port>65535))
		{
			error("Invalid SNMP port");
			return 0;
		}

		if($value_type == ITEM_VALUE_TYPE_STR)
		{
			$delta=0;
		}

		DBexecute("update items set lastlogsize=0 where itemid=$itemid and key_<>'".zbx_ads($key)."'");

		$sql="update items set description='".zbx_ads($description)."',key_='".zbx_ads($key)."',".
			"hostid=$hostid,delay=$delay,history=$history,nextcheck=0,status=$status,type=$type,".
			"snmp_community='".zbx_ads($snmp_community)."',snmp_oid='".zbx_ads($snmp_oid)."',".
			"value_type=$value_type,trapper_hosts='".zbx_ads($trapper_hosts)."',".
			"snmp_port=$snmp_port,units='".zbx_ads($units)."',multiplier=$multiplier,delta=$delta,".
			"snmpv3_securityname='".zbx_ads($snmpv3_securityname)."',".
			"snmpv3_securitylevel=".zbx_ads($snmpv3_securitylevel).",".
			"snmpv3_authpassphrase='".zbx_ads($snmpv3_authpassphrase)."',".
			"snmpv3_privpassphrase='".zbx_ads($snmpv3_privpassphrase)."',".
			"formula='".zbx_ads($formula)."',trends=$trends,logtimefmt='".zbx_ads($logtimefmt)."'".
			" where itemid=$itemid";
		$result=DBexecute($sql);
		if($result)
		{
			$host=get_host_by_hostid($hostid);
			info("Item ".$host["host"].":$key updated");
		}
		return $result;
	}

	function	sync_items_with_template_host($hostid,$host_templateid)
	{
		$sql="select itemid from items where hostid=$host_templateid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$item=get_item_by_itemid($row["itemid"]);

			$sql="select itemid from items where key_=\"".zbx_ads($item["key_"])."\" and hostid=$hostid";
			$result2=DBselect($sql);
			if(DBnum_rows($result2)==0)
			{
				add_item($item["description"],$item["key_"],$hostid,$item["delay"],$item["history"],$item["status"],$item["type"],$item["snmp_community"],$item["snmp_oid"],$item["value_type"],$item["trapper_hosts"],$item["snmp_port"],$item["units"],$item["multiplier"],$item["delta"],$item["snmpv3_securityname"],$item["snmpv3_securitylevel"],$item["snmpv3_authpassphrase"],$item["snmpv3_privpassphrase"],$item["formula"],$item["trends"],$item["logtimefmt"]);
			}
		}
	}

	# Add item to hardlinked hosts

	function	add_item_to_linked_hosts($itemid,$hostid=0)
	{
		if($itemid<=0)
		{
			return;
		}

		$item=get_item_by_itemid($itemid);

		// Link with one host only
		if($hostid!=0)
		{
			$sql="select hostid,templateid,items from hosts_templates".
				" where hostid=$hostid and templateid=".$item["hostid"];
		}
		else
		{
			$sql="select hostid,templateid,items from hosts_templates".
				" where templateid=".$item["hostid"];
		}
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if($row["items"]&1 == 0)	continue;
			$sql="select itemid from items where key_=\"".$item["key_"]."\"".
				" and hostid=".$row["hostid"];
			$result2=DBselect($sql);
			if(DBnum_rows($result2)==0)
			{
				add_item($item["description"],$item["key_"],$row["hostid"],$item["delay"],
					$item["history"],$item["status"],$item["type"],$item["snmp_community"],
					$item["snmp_oid"],$item["value_type"],$item["trapper_hosts"],
					$item["snmp_port"],$item["units"],$item["multiplier"],$item["delta"],
					$item["snmpv3_securityname"],$item["snmpv3_securitylevel"],
					$item["snmpv3_authpassphrase"],$item["snmpv3_privpassphrase"],
					$item["formula"],$item["trends"],$item["logtimefmt"]);
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
			$sql="select itemid from items where key_='".zbx_ads($item["key_"])."'".
				" and hostid=".$row["hostid"];
			$result2=DBselect($sql);
			while($row2=DBfetch($result2))
			{
				delete_item($row2["itemid"]);
			}
		}
	}

	# Update item in hardlinked hosts

	function	update_item_in_templates($itemid,$description,$key,$hostid,$delay,$history,$status,$type,
		$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,
		$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,
		$formula,$trends,$logtimefmt)
	{
		if($itemid<=0)
		{
			return;
		}
		$item = get_item_by_itemid($itemid);
		$db_child_hosts = DBselect("select hostid,templateid,items from hosts_templates".
			" where templateid=".$item["hostid"]);
		while($db_child_host=DBfetch($db_child_hosts))
		{
			if($db_child_host["items"]&2 == 0)	continue;
			$db_items = DBselect("select itemid from items where key_=\"".zbx_ads($item["key_"])."\"".
				" and hostid=".$db_child_host["hostid"]);
			if(DBnum_rows($db_items)==1)
			{
				$db_item = DBfetch($db_items);
				update_item($db_item["itemid"],$description,$key,
					$db_child_host["hostid"],$delay,$history,$status,
					$type,$snmp_community,$snmp_oid,
					$value_type,$trapper_hosts,$snmp_port,
					$units,$multiplier,$delta,
					$snmpv3_securityname,$snmpv3_securitylevel,
					$snmpv3_authpassphrase,$snmpv3_privpassphrase,
					$formula,$trends,$logtimefmt);
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

	function	get_items_by_hostid($hostid)
	{
		$sql="select * from items where hostid=$hostid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) != 0)
		{
			return	$result;
		}
		error("No items for hostid=[$hostid]");
		return	FALSE;
	}

	function	get_item_by_itemid($itemid)
	{
		$sql="select * from items where itemid=$itemid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		error("No item with itemid=[$itemid]");
		return	FALSE;
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
		$item=get_item_by_itemid($itemid);
		$host=get_host_by_hostid($item["hostid"]);
		$sql="delete from items where itemid=$itemid";
		$result=DBexecute($sql);
		if($result)
		{
			info("Item ".$host["host"].":".$item["key_"]." deleted");
		}
		return $result;
	}

	function	get_n_param($key, $num)
	{
		$param="";

//		echo $key." ".$num."<br>";

//		$params=split('[\[\]\,]', $description);
		$params=preg_split('/[\]\[,]/', $key);

		if(isset($params[$num]))
		{
			$param=$params[$num];
		}

		return $param;
	}

	function	item_description($description, $key)
	{
		$descr=$description;

		for($i=9;$i>0;$i--)
		{
			$descr=str_replace("$$i",get_n_param($key,$i),$descr);
		}

		return $descr;
	}
?>
