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

	function	update_item_in_group($groupid,$itemid,$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,$valuemapid)
	{
		$sql="select i.itemid,i.hostid from hosts_groups hg,items i where hg.groupid=$groupid and i.key_=".zbx_dbstr($key)." and hg.hostid=i.hostid";
		$result=DBexecute($sql);
		while($row=DBfetch($result))
		{
			update_item($row["itemid"],$description,$key,$row["hostid"],$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,$valuemapid);
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

	function	add_item_to_group($groupid,$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,$valuemapid)
	{
		$sql="select hostid from hosts_groups where groupid=$groupid";
		$result=DBexecute($sql);
		while($row=DBfetch($result))
		{
			add_item($description,$key,$row["hostid"],$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,$valuemapid);
		}
		return 1;
	}

	# Add Item definition

	function	add_item(
		$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,
		$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,
		$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,
		$valuemapid,$templateid=0)
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
				$trends, $logtimefmt, $valuemapid, $templateid);

			return $result;
		}

		// first add mother item
		$result=DBexecute("insert into items".
			" (description,key_,hostid,delay,history,nextcheck,status,type,".
			"snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,".
			"delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,".
			"snmpv3_privpassphrase,formula,trends,logtimefmt,valuemapid,templateid)".
			" values (".zbx_dbstr($description).",".zbx_dbstr($key).",$hostid,$delay,$history,0,
			$status,$type,".zbx_dbstr($snmp_community).",".zbx_dbstr($snmp_oid).",$value_type,".
			zbx_dbstr($trapper_hosts).",$snmp_port,".zbx_dbstr($units).",$multiplier,$delta,".
			zbx_dbstr($snmpv3_securityname).",$snmpv3_securitylevel,".
			zbx_dbstr($snmpv3_authpassphrase).",".zbx_dbstr($snmpv3_privpassphrase).",".
			zbx_dbstr($formula).",$trends,".zbx_dbstr($logtimefmt).",$valuemapid,$templateid)");


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
				$trends, $logtimefmt, $valuemapid, $itemid);
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

		$result = DBexecute($sql);
		return $result;
	}

	# Update Item definition

	function	update_item($itemid,$description,$key,$hostid,$delay,$history,$status,$type,
		$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,
		$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,
		$formula,$trends,$logtimefmt,$valuemapid,$templateid=0)
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
				$trends, $logtimefmt, $valuemapid, $itemid);

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
			"formula=".zbx_dbstr($formula).",trends=$trends,logtimefmt=".zbx_dbstr($logtimefmt).",".
			"valuemapid=$valuemapid,templateid=$templateid where itemid=$itemid");
		if($result)
		{
			info("Item '".$host["host"].":$key' updated");

		}
		return $result;
	}

	function	delete_template_items_by_hostid($hostid)
	{
		$db_items = get_items_by_hostid($hostid);
		while($db_item = DBfetch($db_items))
		{
			if($db_item["templateid"] == 0)	continue;
			delete_item($db_item["itemid"]);
		}
	}

	function	sync_items_with_template($hostid)
	{
		$host = get_host_by_hostid($hostid);

//SDI("sync host: ".$host['host']);

		$db_tmp_items = get_items_by_hostid($host["templateid"]);

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
				$db_tmp_item["valuemapid"],
				$db_tmp_item["itemid"]);
		}
	}

	# Activate Item

	function	activate_item($itemid)
	{
		 // first update status for child items
		$db_tmp_items = DBselect("select itemid, hostid from items where templateid=$itemid");
		while($db_tmp_item = DBfetch($db_tmp_items))
		{
		// recursion
			activate_item($db_tmp_item["itemid"]);
		}

		$result = DBexecute("update items set status=".ITEM_STATUS_ACTIVE.",error='' where itemid=$itemid");
		return $result;
	}

	# Disable Item

	function	disable_item($itemid)
	{
		 // first update status for child items
		$db_tmp_items = DBselect("select itemid, hostid from items where templateid=$itemid");
		while($db_tmp_item = DBfetch($db_tmp_items))
		{
		// recursion
			disable_item($db_tmp_item["itemid"]);
		}

		$result = DBexecute("update items set status=".ITEM_STATUS_DISABLED." where itemid=$itemid");
		return $result;
	}

	function	get_items_by_hostid($hostid)
	{
		return DBselect("select * from items where hostid=$hostid"); 
	}

	function	get_item_by_itemid($itemid)
	{
		$result=DBselect("select * from items where itemid=$itemid"); 
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

		$db_gitems = get_graphitem_by_itemid($itemid);
		while($db_gitem = DBfetch($db_gitems))
		{
			$result = delete_graph_by_itemid($db_gitem["graphid"]);
			if(!$result)	return	$result;
		}

		$result = delete_history_by_itemid($itemid, 1 /* use housekeeper */);
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

	function get_items_data_overview($groupid)
	{
		$table = new CTableInfo();

		if($groupid > 0)
		{
			$group_where = ",hosts_groups hg where hg.groupid=$groupid and hg.hostid=h.hostid and";
		} else {
			$group_where = " where";
		}

		$header=array(new CCol(S_ITEMS,"center"));

		$hosts=array();
		$result=DBselect("select h.hostid,h.host from hosts h,items i $group_where".
			" h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and i.status=".ITEM_STATUS_ACTIVE.
			" group by h.host,h.hostid order by h.host");
		while($row=DBfetch($result))
		{
			if(!check_right("Host","R",$row["hostid"])) continue; //TODO optimize duplication check !!!! see buttom

			array_push($header,new CImg("vtext.php?text=".$row["host"]));
			array_push($hosts,$row["hostid"]);
		}
		$table->SetHeader($header,"vertical_header");

		$db_items = DBselect("select distinct i.description,h.hostid from hosts h,items i $group_where".
			" h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and i.status=".ITEM_STATUS_ACTIVE.
			" order by 1");
		while($item = DBfetch($db_items))
		{
			if(!check_right("Host","R",$row["hostid"])) continue; //TODO optimize duplication check !!!! see top

			$table_row = array(nbsp($item["description"]));
			foreach($hosts as $hostid)
			{
				$db_host_items = DBselect("select itemid,value_type,lastvalue,units from items where".
					" hostid=$hostid and description=".zbx_dbstr($item["description"]));
				if(DBnum_rows($db_host_items)!=1)
				{
					array_push($table_row,"-");
					continue;
				}
				$host_item = DBfetch($db_host_items);

				if(!check_right("Item","R",$host_item["itemid"])) continue;

				if(!isset($host_item["lastvalue"]))
				{
					array_push($table_row,"-");
					continue;
				}
				$db_item_triggers = DBselect("select t.triggerid from triggers t, items i, functions f where".
					" i.hostid=$hostid and i.itemid=".$host_item["itemid"]." and i.itemid=f.itemid".
					" and t.priority>1 and t.triggerid=f.triggerid and t.value=".TRIGGER_VALUE_TRUE);
				if(DBnum_rows($db_item_triggers) > 0)		$style = "high";
				else						$style = NULL;

				if($host_item["value_type"] == 0)
					$value = convert_units($host_item["lastvalue"],$host_item["units"]);
				else
					$value = htmlspecialchars(substr($host_item["lastvalue"],0,20)." ...");

				array_push($table_row,new CCol(nbsp($value),$style));
			}

			$table->AddRow($table_row);
		}
		return $table;
	}
?>
