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
	function	item_type2str($type)
	{
		switch($type)
		{
			case 0:	$type = S_ZABBIX_AGENT;			break;
			case 1:	$type = S_SNMPV1_AGENT;			break;
			case 2:	$type = S_ZABBIX_TRAPPER;		break;
			case 3:	$type = S_SIMPLE_CHECK;			break;
			case 4:	$type = S_SNMPV2_AGENT;			break;
			case 5:	$type = S_ZABBIX_INTERNAL;		break;
			case 6:	$type = S_SNMPV3_AGENT;			break;
			case 7:	$type = S_ZABBIX_AGENT_ACTIVE;		break;
			case 8:	$type = S_ZABBIX_AGGREGATE;		break;
			default:$type = S_UNKNOWN;			break;
		}
		return $type;
	}

	function	item_value_type2str($value_type)
	{
		switch($value_type)
		{
			case ITEM_VALUE_TYPE_UINT64:	$value_type = S_NUMERIC_UINT64;		break;
			case ITEM_VALUE_TYPE_FLOAT:	$value_type = S_NUMERIC_FLOAT;		break;
			case ITEM_VALUE_TYPE_STR:	$value_type = S_CHARACTER;		break;
			case ITEM_VALUE_TYPE_LOG:	$value_type = S_LOG;			break;
			case ITEM_VALUE_TYPE_TEXT:	$value_type = S_TEXT;			break;
			default:$value_type = S_UNKNOWN;			break;
		}
		return $value_type;
	}

	function	item_status2str($status)
	{
		switch($status)
		{
			case 0:	$status = S_ACTIVE;		break;
			case 1:	$status = S_DISABLED;		break;
			case 3:	
			default:
				$status = S_UNKNOWN;		break;
		}
		return $status;
	}
	
	function	item_status2style($status)
	{
		switch($status)
		{
			case 0:	$status = 'off';	break;
			case 1:	$status = 'on';		break;
			case 3:	
			default:
				$status = 'uncnown';	break;
		}
		return $status;
	}
	# Update Item definition for selected group

	function	update_item_in_group($groupid,$itemid,$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,$valuemapid,$delay_flex,$applications)
	{
		$sql="select i.itemid,i.hostid from hosts_groups hg,items i where hg.groupid=$groupid and i.key_=".zbx_dbstr($key)." and hg.hostid=i.hostid";
		$result=DBexecute($sql);
		while($row=DBfetch($result))
		{
			update_item($row["itemid"],$description,$key,$row["hostid"],$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,$valuemapid,$delay_flex,$applications);
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

	function	add_item_to_group($groupid,$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,$valuemapid,$delay_flex,$applications)
	{
		$sql="select hostid from hosts_groups where groupid=$groupid";
		$result=DBexecute($sql);
		while($row=DBfetch($result))
		{
			add_item($description,$key,$row["hostid"],$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,$valuemapid,$delay_flex,$applications);
		}
		return 1;
	}

	# Add Item definition

	function	add_item(
		$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,
		$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,
		$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,
		$valuemapid,$delay_flex,$applications,$templateid=0)
	{

		$host=get_host_by_hostid($hostid);

		if(($i = array_search(0,$applications)) !== FALSE)
			unset($applications[$i]);

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

		if( ($type == ITEM_TYPE_AGGREGATE) && ($value_type != ITEM_VALUE_TYPE_FLOAT))
		{
			error("Value type must be Float for aggregate items");
			return FALSE;
		}
		if($type == ITEM_TYPE_AGGREGATE)
		{
			/* grpfunc('group','key','itemfunc','numeric param') */
			if(eregi('^((.)*)(\(\'((.)*)\'\,\'((.)*)\'\,\'((.)*)\'\,\'([0-9]+)\'\))$', $key, $arr))
			{
				$g=$arr[1];
				if(!in_array($g,array("grpmax","grpmin","grpsum","grpavg")))
				{
					error("Group function [$g] is not one of [grpmax,grpmin,grpsum,grpavg]");
					return FALSE;
				}
				// Group
				$g=$arr[4];
				// Key
				$g=$arr[6];
				// Item function
				$g=$arr[8];
				if(!in_array($g,array("last", "min", "max", "avg", "sum","count")))
				{
					error("Item function [$g] is not one of [last, min, max, avg, sum,count]");
					return FALSE;
				}
				// Parameter
				$g=$arr[10];
			}
			else
			{
				error("Key does not match grpfunc('group','key','itemfunc','numeric param')");
				return FALSE;
			}
		}

		$db_item = DBfetch(DBselect("select itemid,hostid from items".
			" where hostid=$hostid and key_=".zbx_dbstr($key)));
		if($db_item && $templateid == 0)
		{
			error("An item with the same Key already exists for host ".$host["host"].".".
				" The key must be unique.");
			return FALSE;
		} elseif ($db_item && $templateid != 0){

			$result = update_item(
				$db_item["itemid"], $description, $key, $db_item["hostid"],
				$delay, $history, $status, $type, $snmp_community, $snmp_oid,
				$value_type, $trapper_hosts, $snmp_port, $units, $multiplier,
				$delta, $snmpv3_securityname, $snmpv3_securitylevel,
				$snmpv3_authpassphrase, $snmpv3_privpassphrase, $formula,
				$trends, $logtimefmt, $valuemapid, $delay_flex,
				get_same_applications_for_host($applications, $db_item["hostid"]),
				$templateid);

			return $result;
		}

		// first add mother item
		$itemid=get_dbid("items","itemid");
		$result=DBexecute("insert into items".
			" (itemid,description,key_,hostid,delay,history,nextcheck,status,type,".
			"snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,".
			"delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,".
			"snmpv3_privpassphrase,formula,trends,logtimefmt,valuemapid,delay_flex,templateid)".
			" values ($itemid,".zbx_dbstr($description).",".zbx_dbstr($key).",$hostid,$delay,$history,0,
			$status,$type,".zbx_dbstr($snmp_community).",".zbx_dbstr($snmp_oid).",$value_type,".
			zbx_dbstr($trapper_hosts).",$snmp_port,".zbx_dbstr($units).",$multiplier,$delta,".
			zbx_dbstr($snmpv3_securityname).",$snmpv3_securitylevel,".
			zbx_dbstr($snmpv3_authpassphrase).",".zbx_dbstr($snmpv3_privpassphrase).",".
			zbx_dbstr($formula).",$trends,".zbx_dbstr($logtimefmt).",$valuemapid,".
			zbx_dbstr($delay_flex).",$templateid)");

		if(!$result)
			return $result;

		foreach($applications as $appid)
		{
			$itemappid=get_dbid("items_applications","itemappid");
			DBexecute("insert into items_applications (itemappid,itemid,applicationid) values($itemappid,".$itemid.",".$appid.")");
		}

		info("Added new item ".$host["host"].":$key");

// add items to child hosts

		$db_hosts = get_hosts_by_templateid($host["hostid"]);
		while($db_host = DBfetch($db_hosts))
		{
		// recursion
			$result = add_item($description, $key, $db_host["hostid"],
				$delay, $history, $status, $type, $snmp_community, $snmp_oid,
				$value_type, $trapper_hosts, $snmp_port, $units, $multiplier,
				$delta, $snmpv3_securityname, $snmpv3_securitylevel,
				$snmpv3_authpassphrase, $snmpv3_privpassphrase, $formula,
				$trends, $logtimefmt, $valuemapid,$delay_flex,
				get_same_applications_for_host($applications, $db_host["hostid"]),
				$itemid);
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
		$formula,$trends,$logtimefmt,$valuemapid,$delay_flex,$applications,$templateid=0)
	{
		$host = get_host_by_hostid($hostid);

		if(($i = array_search(0,$applications)) !== FALSE)
			unset($applications[$i]);

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

		$db_item = DBfetch(DBselect("select itemid from items".
			" where hostid=$hostid and itemid<>$itemid and key_=".zbx_dbstr($key)));
		if($db_item && $templateid == 0)
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
				$trends, $logtimefmt, $valuemapid,$delay_flex,
				get_same_applications_for_host($applications, $db_tmp_item["hostid"]),
				$itemid);


			if(!$result)
				return $result;
		}

		if($db_item && $templateid != 0)
		{
			$result = delete_item($db_item["itemid"]);
			if(!$result) {
				error("Can't update item '".$host["host"].":$key'");
				return FALSE;
			}
		}

		DBexecute("update items set lastlogsize=0 where itemid=$itemid and key_<>".zbx_dbstr($key));

		$result = DBexecute("delete from items_applications where itemid=$itemid");
		foreach($applications as $appid)
		{
			$itemappid=get_dbid("items_applications","itemappid");
			DBexecute("insert into items_applications (itemappid,itemid,applicationid) values($itemappid,".$itemid.",".$appid.")");
		}

		$result=DBexecute(
			"update items set description=".zbx_dbstr($description).",key_=".zbx_dbstr($key).",".
			"hostid=$hostid,delay=$delay,history=$history,nextcheck=0,type=$type,status=".$status.','.
			"snmp_community=".zbx_dbstr($snmp_community).",snmp_oid=".zbx_dbstr($snmp_oid).",".
			"value_type=$value_type,trapper_hosts=".zbx_dbstr($trapper_hosts).",".
			"snmp_port=$snmp_port,units=".zbx_dbstr($units).",multiplier=$multiplier,delta=$delta,".
			"snmpv3_securityname=".zbx_dbstr($snmpv3_securityname).",".
			"snmpv3_securitylevel=$snmpv3_securitylevel,".
			"snmpv3_authpassphrase=".zbx_dbstr($snmpv3_authpassphrase).",".
			"snmpv3_privpassphrase=".zbx_dbstr($snmpv3_privpassphrase).",".
			"formula=".zbx_dbstr($formula).",trends=$trends,logtimefmt=".zbx_dbstr($logtimefmt).",".
			"valuemapid=$valuemapid,delay_flex=".zbx_dbstr($delay_flex).",".
			"templateid=$templateid where itemid=$itemid");
		if($result)
		{
			info("Item '".$host["host"].":$key' updated");

		}
		return $result;
	}

	function	delete_template_items($hostid, $templateid = null /* array format 'arr[id]=name' */, $unlink_mode = false)
	{
		$db_items = get_items_by_hostid($hostid);
		while($db_item = DBfetch($db_items))
		{
			if($db_item["templateid"] == 0)
				continue;

			if($templateid != null)
			{
				$db_tmp_item = get_item_by_itemid($db_item["templateid"]);
				if(is_array($templateid))
				{
					if(!isset($templateid[$db_tmp_item["hostid"]]))
						continue;
				}
				elseif($db_tmp_item["hostid"] != $templateid)
				{
					continue;
				}
			}

			if($unlink_mode)
			{
				if(DBexecute("update items set templateid=0 where itemid=".$db_item["itemid"]))
                                {
                                        info("Item '".$db_item["key_"]."' unlinked");
                                }
			}
			else
			{
				delete_item($db_item["itemid"]);
			}
		}
	}

	function	copy_item_to_host($itemid, $hostid, $copy_mode = false)
	{
		$db_tmp_item = get_item_by_itemid($itemid);

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
			$db_tmp_item["delay_flex"],
			get_same_applications_for_host(get_applications_by_itemid($db_tmp_item["itemid"]),$hostid),
			$copy_mode ? 0 : $db_tmp_item["itemid"]);
	}

	function	copy_template_items($hostid, $templateid = null /* array format 'arr[id]=name' */, $copy_mode = false)
	{
		if($templateid == null)
		{
			$templateid = get_templates_by_hostid($hostid);
		}
		
		if(is_array($templateid))
		{
			foreach($templateid as $id => $name)
				copy_template_items($hostid, $id, $copy_mode); // attention recursion
			return;
		}

		$db_tmp_items = get_items_by_hostid($templateid);

		
		while($db_tmp_item = DBfetch($db_tmp_items))
		{
			$apps = get_same_applications_for_host(get_applications_by_itemid($db_tmp_item["itemid"]),$hostid);
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
				$db_tmp_item["delay_flex"],
				$apps,
				$copy_mode ? 0 : $db_tmp_item["itemid"]);
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

	function	&get_items_by_hostid($hostid)
	{
		return DBselect("select * from items where hostid=$hostid"); 
	}

	function	get_item_by_itemid($itemid)
	{
		$row = DBfetch(DBselect("select * from items where itemid=$itemid")); 
		if($row)
		{
			return	$row;
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

		$db_gitems = DBselect('select * from graphs_items where itemid='.$itemid);
		while($db_gitem = DBfetch($db_gitems))
		{
			$result = delete_graph($db_gitem["graphid"]);
			if(!$result)	return	$result;
		}

		$result = delete_history_by_itemid($itemid, 1 /* use housekeeper */);
		if(!$result)	return	$result;


		$result = DBexecute("delete from items_applications where itemid=$itemid");
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

		$params = preg_split('/[\]\[,]/', $key);

		if(isset($params[$num]))
		{
			$param = $params[$num];
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

	function get_items_data_overview($groupid, $nodeid)
	{
		global	$USER_DETAILS;

		$table = new CTableInfo(S_NO_ITEMS_DEFINED);

		if($groupid > 0)
		{
			$group_where = ",hosts_groups hg where hg.groupid=$groupid and hg.hostid=h.hostid and";
		} else {
			$group_where = " where";
		}

COpt::profiling_start('prepare data');
		$result = DBselect('select distinct h.hostid, h.host,i.itemid, i.key_, i.value_type, i.lastvalue, i.units, '.
			' i.description, t.priority, t.value as tr_value'.
			' from hosts h,items i left join  functions f on f.itemid=i.itemid left join triggers t on t.triggerid=f.triggerid '.
			$group_where.
			' h.hostid in ('.get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY, null, null, $nodeid).') '.
			' and h.status='.HOST_STATUS_MONITORED.' and h.hostid=i.hostid and i.status='.ITEM_STATUS_ACTIVE.
			' order by i.description,i.itemid');

		unset($items);
		unset($hosts);
		while($row = DBfetch($result))
		{
			$hosts[$row['host']] = $row['host'];
			$items[item_description($row["description"],$row["key_"])][$row['host']] = array(
				'itemid'	=> $row['itemid'],
				'value_type'	=> $row['value_type'],
				'lastvalue'	=> $row['lastvalue'],
				'units'		=> $row['units'],
				'description'	=> $row['description'],
				'severity'	=> $row['priority'],
				'tr_value'	=> $row['tr_value']
				);
		}
		if(!isset($hosts))
		{
			return $table;
		}

		sort($hosts);
COpt::profiling_stop('prepare data');
COpt::profiling_start('prepare table');
		$header=array(new CCol(S_ITEMS,'center'));
		foreach($hosts as $hostname)
		{
			$header=array_merge($header,array(new CImg('vtext.php?text='.$hostname)));
		}
		$table->SetHeader($header,'vertical_header');
		$curr_rime = time();
		foreach($items as $descr => $ithosts)
		{
			$table_row = array(nbsp($descr));
			foreach($hosts as $hostname)
			{
				$style = NULL;
				$value = '-';
				if(isset($ithosts[$hostname]))
				{
					if($ithosts[$hostname]['tr_value'] == TRIGGER_VALUE_TRUE)
						$style = get_severity_style($ithosts[$hostname]['severity']);
					
					if($ithosts[$hostname]["value_type"] == 0)
						$value = convert_units($ithosts[$hostname]["lastvalue"],$ithosts[$hostname]["units"]);
					else
						$value = htmlspecialchars(substr($ithosts[$hostname]["lastvalue"],0,20)." ...");
				}
				array_push($table_row,new CCol($value,$style));
			}
			$table->AddRow($table_row);
		}
COpt::profiling_stop('prepare table');

		return $table;
	}

	function get_same_applications_for_host($applications, $hostid)
	{
		$child_applications = array();

		foreach($applications as $appid)
		{
			$db_apps = DBselect("select a1.applicationid from applications a1, applications a2".
					" where a1.name=a2.name and a1.hostid=".$hostid." and a2.applicationid=".$appid);
			$db_app = DBfetch($db_apps);
			if(!$db_app) continue;
			array_push($child_applications,$db_app["applicationid"]);
		}
		return $child_applications;
	}

	function get_applications_by_itemid($itemid, $field='applicationid')
	{
		$result = array();
		
		$db_applications = DBselect("select distinct app.*,app.".$field." as result from applications app, items_applications ia".
			" where app.applicationid=ia.applicationid and ia.itemid=".$itemid);
		while($db_application = DBfetch($db_applications))
			array_push($result,$db_application["result"]);

		return $result;
	}

	# Delete from History

	function	delete_history_by_itemid($itemid, $use_housekeeper=0)
	{
		SDI('TODO: Correct housekeeper scheduling [new elementid problem]!'); /* TODO */ /* think about housekeeper scheduling, must be housekeeperid - unneeded */
		
		$result = delete_trends_by_itemid($itemid,$use_housekeeper);
		if(!$result)	return $result;

		if($use_housekeeper)
		{
			$housekeeperid = get_dbid('housekeeper','housekeeperid');
			DBexecute("insert into housekeeper (housekeeperid,tablename,field,value)".
				" values ($housekeeperid,'history_log','itemid',$itemid)");
			$housekeeperid = get_dbid('housekeeper','housekeeperid');
			DBexecute("insert into housekeeper (housekeeperid,tablename,field,value)".
				" values ($housekeeperid,'history_uint','itemid',$itemid)");
			$housekeeperid = get_dbid('housekeeper','housekeeperid');
			DBexecute("insert into housekeeper (housekeeperid,tablename,field,value)".
				" values ($housekeeperid,'history_str','itemid',$itemid)");
			$housekeeperid = get_dbid('housekeeper','housekeeperid');
			DBexecute("insert into housekeeper (housekeeperid,tablename,field,value)".
				" values ($housekeeperid,'history','itemid',$itemid)");
			return TRUE;
		}

		DBexecute("delete from history_log where itemid=$itemid");
		DBexecute("delete from history_uint where itemid=$itemid");
		DBexecute("delete from history_str where itemid=$itemid");
		DBexecute("delete from history where itemid=$itemid");
		return TRUE;
	}

	# Delete from Trends

	function	delete_trends_by_itemid($itemid, $use_housekeeper=0)
	{
		SDI('TODO: Correct housekeeper scheduling [new elementid problem]!'); /* TODO */ /* think about housekeeper scheduling, must be housekeeperid - unneeded */

		if($use_housekeeper)
		{
			$housekeeperid = get_dbid('housekeeper','housekeeperid');
			DBexecute("insert into housekeeper (housekeeperid,tablename,field,value)".
				" values ($housekeeperid, 'trends','itemid',$itemid)");
			return TRUE;
		}
		return	DBexecute("delete from trends where itemid=$itemid");
	}
?>
