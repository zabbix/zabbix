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
		$sql="select i.itemid,i.hostid from hosts_groups hg,items i where hg.groupid=$groupid and i.key_=".zbx_dbstr($key)." and hg.hostid=i.hostid";
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

		$sql="select i.itemid from hosts_groups hg,items i".
			" where hg.groupid=$groupid and i.key_=".zbx_dbstr($item["key_"]).
			" and hg.hostid=i.hostid";
		$result=DBexecute($sql);
		while($row=DBfetch($result))
		{
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

	function	add_item(
		$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,
		$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,
		$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,
		$templateid=0)
	{
		$host=get_host_by_hostid($hostid);

		if(!check_right("Item","A",0))
		{
			error("Insufficient permissions to item '".$host["host"].":$key'");
			return FALSE;
		}

		if($delay<1)
		{
			error("Delay cannot be less than 1 second");
			return FALSE;
		}

		if( ($snmp_port<1)||($snmp_port>65535))
		{
			error("Invalid SNMP port");
			return FALSE;
		}

		if($value_type == ITEM_VALUE_TYPE_STR)
		{
			$delta=0;
		}

		$db_items = DBexecute("select itemid,hostid from items".
			" where hostid=$hostid and key_=".zbx_dbstr($key));
		if(DBnum_rows($db_items) > 0 && $templateid == 0)
		{
			error("An item with the same Key already exists for host ".$host["host"].".".
				" The key must be unique.");
			return FALSE;
		} elseif (DBnum_rows($db_items) > 0 && $templateid != 0){
			$db_item = DBfetch($db_items);

			$result = update_item(
				$db_item["itemid"], $description, $key, $db_item["hostid"],
				$delay, $history, $status, $type, $snmp_community, $snmp_oid,
				$value_type, $trapper_hosts, $snmp_port, $units, $multiplier,
				$delta, $snmpv3_securityname, $snmpv3_securitylevel,
				$snmpv3_authpassphrase, $snmpv3_privpassphrase, $formula,
				$trends, $logtimefmt, $itemid);

			return $result;
		}

		// first add mother item
		$result=DBexecute("insert into items".
			" (description,key_,hostid,delay,history,nextcheck,status,type,".
			"snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,".
			"delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,".
			"snmpv3_privpassphrase,formula,trends,logtimefmt,templateid)".
			" values (".zbx_dbstr($description).",".zbx_dbstr($key).",$hostid,$delay,$history,0,
			$status,$type,".zbx_dbstr($snmp_community).",".zbx_dbstr($snmp_oid).",$value_type,".
			zbx_dbstr($trapper_hosts).",$snmp_port,".zbx_dbstr($units).",$multiplier,$delta,".
			zbx_dbstr($snmpv3_securityname).",$snmpv3_securitylevel,".
			zbx_dbstr($snmpv3_authpassphrase).",".zbx_dbstr($snmpv3_privpassphrase).",".
			zbx_dbstr($formula).",$trends,".zbx_dbstr($logtimefmt).",$templateid)");


		if(!$result)
			return $result;

		$itemid =  DBinsert_id($result,"items","itemid");
		info("Added new item ".$host["host"].":$key");

// add items to child hosts

		$db_hosts = DBselect("select hostid from hosts where templateid=".$host["hostid"]);
		while($db_host = DBfetch($db_hosts))
		{
		// recursion
			$result = add_item($description, $key, $db_host["hostid"],
				$delay, $history, $status, $type, $snmp_community, $snmp_oid,
				$value_type, $trapper_hosts, $snmp_port, $units, $multiplier,
				$delta, $snmpv3_securityname, $snmpv3_securitylevel,
				$snmpv3_authpassphrase, $snmpv3_privpassphrase, $formula,
				$trends, $logtimefmt, $itemid);
			if(!$result)
				break;
		}
		if($result)
			return $itemid;

		if($templateid == 0){
			delete_item($itemid);
		}
		
		return $result;
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
		$formula,$trends,$logtimefmt,$templateid=0)
	{
		$host = get_host_by_hostid($hostid);

		if(!check_right("Item","U",$itemid))
		{
			error("Insufficient permissions to item '".$host["host"].":$key'");
			return FALSE;
		}

		if($delay<1)
		{
			error("Delay cannot be less than 1 second");
			return FALSE;
		}

		if( ($snmp_port<1)||($snmp_port>65535))
		{
			error("Invalid SNMP port");
			return FALSE;
		}

		if($value_type == ITEM_VALUE_TYPE_STR)
		{
			$delta=0;
		}

		$db_items = DBexecute("select itemid as cnt from items".
			" where hostid=$hostid and itemid<>$itemid and key_=".zbx_dbstr($key));
		if(DBnum_rows($db_items) > 0 && $templateid == 0)
		{
			error("An item with the same Key already exists for host ".$host["host"].".".
				" The key must be unique.");
			return FALSE;
		}

		 // first update child items
		$db_tmp_items = DBselect("select itemid, hostid from items where templateid=$itemid");
		while($db_tmp_item = DBfetch($db_tmp_items))
		{
		// recursion
			$result = update_item(
				$db_tmp_item["itemid"], $description, $key, $db_tmp_item["hostid"],
				$delay, $history, $status, $type, $snmp_community, $snmp_oid,
				$value_type, $trapper_hosts, $snmp_port, $units, $multiplier,
				$delta, $snmpv3_securityname, $snmpv3_securitylevel,
				$snmpv3_authpassphrase, $snmpv3_privpassphrase, $formula,
				$trends, $logtimefmt, $itemid);

			if(!$result)
				return $result;
		}

		if(DBnum_rows($db_items) > 0 && $templateid != 0)
		{
			$result = delete_item($itemid);
			if(!$result) {
				error("Can't update item '".$host["host"].":$key'");
				return FALSE;
			}
			$db_item = DBfetch($db_items);
			$itemid = $db_item("itemid");
		}

		DBexecute("update items set lastlogsize=0 where itemid=$itemid and key_<>".zbx_dbstr($key));

		if($templateid==0){
			update_item_status($itemid, $status);
		}

		$result=DBexecute(
			"update items set description=".zbx_dbstr($description).",key_=".zbx_dbstr($key).",".
			"hostid=$hostid,delay=$delay,history=$history,nextcheck=0,type=$type,".
			"snmp_community=".zbx_dbstr($snmp_community).",snmp_oid=".zbx_dbstr($snmp_oid).",".
			"value_type=$value_type,trapper_hosts=".zbx_dbstr($trapper_hosts).",".
			"snmp_port=$snmp_port,units=".zbx_dbstr($units).",multiplier=$multiplier,delta=$delta,".
			"snmpv3_securityname=".zbx_dbstr($snmpv3_securityname).",".
			"snmpv3_securitylevel=$snmpv3_securitylevel,".
			"snmpv3_authpassphrase=".zbx_dbstr($snmpv3_authpassphrase).",".
			"snmpv3_privpassphrase=".zbx_dbstr($snmpv3_privpassphrase).",".
			"formula=".zbx_dbstr($formula).",trends=$trends,logtimefmt=".zbx_dbstr($logtimefmt).
			",templateid=$templateid where itemid=$itemid");
		if($result)
		{
			info("Item '".$host["host"].":$key' updated");

		}
		return $result;
	}

	function	delete_template_items_by_hostid($hostid)
	{
		$db_items = DBselect("select itemid from items where hostid=$hostid and templateid<>0");
		while($db_item = DBfetch($db_items))
		{
			delete_item($db_item["itemid"]);
		}
	}

	function	sync_items_with_template($hostid)
	{
		$host = get_host_by_hostid($hostid);

//SDI("sync host: ".$host['host']);

		$db_tmp_items = DBselect("select * from items where hostid=".$host["templateid"]);

		while($db_tmp_item = DBfetch($db_tmp_items))
		{
			add_item(
				$db_tmp_item["description"],
				$db_tmp_item["key_"],
				$hostid,
				$db_tmp_item["delay"],
				$db_tmp_item["history"],
				$db_tmp_item["status"],
				$db_tmp_item["type"],
				$db_tmp_item["snmp_community"],
				$db_tmp_item["snmp_oid"],
				$db_tmp_item["value_type"],
				$db_tmp_item["trapper_hosts"],
				$db_tmp_item["snmp_port"],
				$db_tmp_item["units"],
				$db_tmp_item["multiplier"],
				$db_tmp_item["delta"],
				$db_tmp_item["snmpv3_securityname"],
				$db_tmp_item["snmpv3_securitylevel"],
				$db_tmp_item["snmpv3_authpassphrase"],
				$db_tmp_item["snmpv3_privpassphrase"],
				$db_tmp_item["formula"],
				$db_tmp_item["trends"],
				$db_tmp_item["logtimefmt"],
				$db_tmp_item["itemid"]);
		}
	}

	# Add item to hardlinked hosts
/*
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
*/
	# Add item to hardlinked hosts
/*
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
			$sql="select itemid from items where key_=".zbx_dbstr($item["key_"]).
				" and hostid=".$row["hostid"];
			$result2=DBselect($sql);
			while($row2=DBfetch($result2))
			{
				delete_item($row2["itemid"]);
			}
		}
	}
*/
	# Update item in hardlinked hosts
/*
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
			$db_items = DBselect("select itemid from items where key_=".zbx_dbstr($item["key_"]).
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
*/
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
		$item = get_item_by_itemid($itemid);
		$host = get_host_by_itemid($itemid);

		// first delete child items
		$db_items = DBselect("select itemid from items where templateid=$itemid");
		while($db_item = DBfetch($db_items))
		{// recursion
			$result = delete_item($db_item["itemid"]);
			if(!$result)	return	$result;
		}
 
		$result = delete_triggers_by_itemid($itemid);
		if(!$result)	return	$result;

		$result = delete_trends_by_itemid($itemid);
		if(!$result)	return	$result;

		$result = delete_history_by_itemid($itemid);
		if(!$result)	return	$result;

		$result = DBexecute("delete from graphs_items where itemid=$itemid");
		if(!$result)	return	$result;

		$result = DBexecute("delete from items where itemid=$itemid");
		if($result)
		{
			info("Item '".$host["host"].":".$item["key_"]."' deleted");
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
	
	function	get_realhost_by_itemid($itemid)
	{
		$itme = get_item_by_itemid($itemid);
		if($itme["templateid"] <> 0)
			return get_realhost_by_itemid($itme["templateid"]);

		return get_host_by_itemid($itemid);
	}
?>
