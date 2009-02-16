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

	/*
	 * Function: item_type2str
	 *
	 * Description:
	 *     Represent integer value of item type as string
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function item_type2str($type){
		switch($type){
			case ITEM_TYPE_ZABBIX:		$type = S_ZABBIX_AGENT;			break;
			case ITEM_TYPE_SNMPV1:		$type = S_SNMPV1_AGENT;			break;
			case ITEM_TYPE_TRAPPER:		$type = S_ZABBIX_TRAPPER;		break;
			case ITEM_TYPE_SIMPLE:		$type = S_SIMPLE_CHECK;			break;
			case ITEM_TYPE_SNMPV2C:		$type = S_SNMPV2_AGENT;			break;
			case ITEM_TYPE_INTERNAL:	$type = S_ZABBIX_INTERNAL;		break;
			case ITEM_TYPE_SNMPV3:		$type = S_SNMPV3_AGENT;			break;
			case ITEM_TYPE_ZABBIX_ACTIVE:	$type = S_ZABBIX_AGENT_ACTIVE;		break;
			case ITEM_TYPE_AGGREGATE:	$type = S_ZABBIX_AGGREGATE;		break;
			case ITEM_TYPE_HTTPTEST:	$type = S_WEB_MONITORING;		break;
			case ITEM_TYPE_EXTERNAL:	$type = S_EXTERNAL_CHECK;		break;
			case ITEM_TYPE_DB_MONITOR:	$type = S_ZABBIX_DATABASE_MONITOR;	break;
			case ITEM_TYPE_IPMI:		$type = S_IPMI_AGENT;			break;
			default:$type = S_UNKNOWN;			break;
		}
	return $type;
	}

	/*
	 * Function: item_value_type2str
	 *
	 * Description:
	 *     Represent integer value of item value type as string
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function item_value_type2str($value_type){
		switch($value_type){
			case ITEM_VALUE_TYPE_UINT64:	$value_type = S_NUMERIC_UINT64;		break;
			case ITEM_VALUE_TYPE_FLOAT:	$value_type = S_NUMERIC_FLOAT;		break;
			case ITEM_VALUE_TYPE_STR:	$value_type = S_CHARACTER;		break;
			case ITEM_VALUE_TYPE_LOG:	$value_type = S_LOG;			break;
			case ITEM_VALUE_TYPE_TEXT:	$value_type = S_TEXT;			break;
			default:$value_type = S_UNKNOWN;			break;
		}
	return $value_type;
	}

	/*
	 * Function: item_value_type2str
	 *
	 * Description:
	 *     Represent integer value of item status as string
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function	item_status2str($status){
		switch($status){
			case ITEM_STATUS_ACTIVE:	$status = S_ACTIVE;		break;
			case ITEM_STATUS_DISABLED:	$status = S_DISABLED;		break;
			case ITEM_STATUS_NOTSUPPORTED:	$status = S_NOT_SUPPORTED;	break;
			default:
				$status = S_UNKNOWN;		break;
		}
	return $status;
	}
	
	/*
	 * Function: item_status2style
	 *
	 * Description:
	 *     Represent integer value of item status as CSS style name
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function	item_status2style($status){
		switch($status){
			case ITEM_STATUS_ACTIVE:	$status = 'off';	break;
			case ITEM_STATUS_DISABLED:	$status = 'on';		break;
			case ITEM_STATUS_NOTSUPPORTED:	
			default:
				$status = 'unknown';	break;
		}
	return $status;
	}
	# Update Item definition for selected group

	function	update_item_in_group($groupid,$itemid,$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,$valuemapid,$delay_flex,$params,$ipmi_sensor,$applications)
	{
		$sql="select i.itemid,i.hostid from hosts_groups hg,items i where hg.groupid=$groupid and i.key_=".zbx_dbstr($key)." and hg.hostid=i.hostid";
		$result=DBexecute($sql);
		while($row=DBfetch($result)){
			update_item($row["itemid"],$description,$key,$row["hostid"],$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,$valuemapid,$delay_flex,$params,$ipmi_sensor,$applications);
		}
	return 1;
	}

	# Delete Item definition from selected group
	function delete_item_from_group($groupid,$itemid){
		if(!isset($itemid)){
			return 0;
		}

		$item=get_item_by_itemid($itemid);
		if(!$item){
			return 0;
		}

		$del_items = array();
		$sql='SELECT i.itemid '.
			' FROM hosts_groups hg,items i'.
			' WHERE hg.groupid='.$groupid.
				' AND i.key_='.zbx_dbstr($item["key_"]).
				' AND hg.hostid=i.hostid';
		$result=DBexecute($sql);
		while($row=DBfetch($result)){
			$del_items[$row['itemid']] = $row['itemid'];
		}
		if(!empty($del_items)){
			delete_item($del_items);
		}
	return 1;
	}

	# Add Item definition to selected group

	function	add_item_to_group($groupid,$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,$valuemapid,$delay_flex,$params,$ipmi_sensor,$applications)
	{
		$sql="select hostid from hosts_groups where groupid=$groupid";
		$result=DBexecute($sql);
		while($row=DBfetch($result))
		{
			add_item($description,$key,$row["hostid"],$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,$valuemapid,$delay_flex,$params,$ipmi_sensor,$applications);
		}
		return 1;
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	add_item(
		$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,
		$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,$snmpv3_securityname,
		$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,$formula,$trends,$logtimefmt,
		$valuemapid,$delay_flex,$params,$ipmi_sensor,$applications,$templateid=0)
	{
		$host=get_host_by_hostid($hostid);

		if(($i = array_search(0,$applications)) !== FALSE)
			unset($applications[$i]);

		if( !eregi('^'.ZBX_EREG_ITEM_KEY_FORMAT.'$', $key) ){
			error("Incorrect key format 'key_name[param1,param2,...]'");
			return false;
		}

		if($delay<1){
			error("Delay cannot be less than 1 second");
			return FALSE;
		}

		if ($delay_flex != '')
		{
			$arr_of_delay = explode(';', $delay_flex);

			foreach($arr_of_delay as $one_delay_flex)
			{
				$arr = explode('/', $one_delay_flex);
				if ($arr[0] < 1)
				{
					error("Delay cannot be less than 1 second ");
					return FALSE;
				}
			}
		}

		if( ($snmp_port<1)||($snmp_port>65535)){
			error("Invalid SNMP port");
			return FALSE;
		}

		if($value_type == ITEM_VALUE_TYPE_STR){
			$delta=0;
		}

		if( ($type == ITEM_TYPE_AGGREGATE) && ($value_type != ITEM_VALUE_TYPE_FLOAT)){
			error("Value type must be Float for aggregate items");
			return FALSE;
		}
		if($type == ITEM_TYPE_AGGREGATE){
			/* grpfunc('group','key','itemfunc','numeric param') */
//			if(eregi('^((.)*)(\(\'((.)*)\'\,\'((.)*)\'\,\'((.)*)\'\,\'([0-9]+)\'\))$', $key, $arr))
			if(eregi('^((.)*)(\[\"((.)*)\"\,\"((.)*)\"\,\"((.)*)\"\,\"([0-9]+)\"\])$', $key, $arr)){
				$g=$arr[1];
				if(!str_in_array($g,array("grpmax","grpmin","grpsum","grpavg"))){
					error("Group function [$g] is not one of [grpmax,grpmin,grpsum,grpavg]");
					return FALSE;
				}
				// Group
				$g=$arr[4];
				// Key
				$g=$arr[6];
				// Item function
				$g=$arr[8];
				if(!str_in_array($g,array('last', 'min', 'max', 'avg', 'sum','count'))){
					error('Item function ['.$g.'] is not one of [last, min, max, avg, sum,count]');
					return FALSE;
				}
				// Parameter
				$g=$arr[10];
			}
			else{
				error('Key does not match grpfunc["group","key","itemfunc","numeric param")');
				return FALSE;
			}
		}

		$db_item = DBfetch(DBselect('SELECT itemid,hostid '.
								' FROM items '.
								' WHERE hostid='.$hostid.
									' AND key_='.zbx_dbstr($key)));
		if($db_item && $templateid == 0){
			error('An item with the Key ['.$key.'] already exists for host ['.$host['host'].']. The key must be unique.');
			return FALSE;
		} 
		else if ($db_item && $templateid != 0){

			$result = update_item(
				$db_item['itemid'], $description, $key, $db_item['hostid'],
				$delay, $history, $status, $type, $snmp_community, $snmp_oid,
				$value_type, $trapper_hosts, $snmp_port, $units, $multiplier,
				$delta, $snmpv3_securityname, $snmpv3_securitylevel,
				$snmpv3_authpassphrase, $snmpv3_privpassphrase, $formula,
				$trends, $logtimefmt, $valuemapid, $delay_flex, $params,$ipmi_sensor,
				get_same_applications_for_host($applications, $db_item['hostid']),
				$templateid);

			return $result;
		}

		// first add mother item
		$itemid=get_dbid('items','itemid');
		$result=DBexecute('INSERT INTO items '.
				' (itemid,description,key_,hostid,delay,history,nextcheck,status,type,'.
					'snmp_community,snmp_oid,value_type,trapper_hosts,'.
					'snmp_port,units,multiplier,'.
					'delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,'.
					'snmpv3_privpassphrase,formula,trends,logtimefmt,valuemapid,'.
					'delay_flex,params,ipmi_sensor,templateid)'.
			' VALUES ('.$itemid.','.zbx_dbstr($description).','.zbx_dbstr($key).','.$hostid.','.$delay.','.$history.',0,'.$status.','.$type.','.
					zbx_dbstr($snmp_community).','.zbx_dbstr($snmp_oid).','.$value_type.','.zbx_dbstr($trapper_hosts).','.
					$snmp_port.','.zbx_dbstr($units).','.$multiplier.','.
					$delta.','.zbx_dbstr($snmpv3_securityname).','.$snmpv3_securitylevel.','.zbx_dbstr($snmpv3_authpassphrase).','.
					zbx_dbstr($snmpv3_privpassphrase).','.zbx_dbstr($formula).','.$trends.','.zbx_dbstr($logtimefmt).','.$valuemapid.','.
					zbx_dbstr($delay_flex).','.zbx_dbstr($params).','.zbx_dbstr($ipmi_sensor).','.$templateid.')'
			);

		if(!$result)
			return $result;

		foreach($applications as $appid){
			$itemappid=get_dbid('items_applications','itemappid');
			DBexecute('INSERT INTO items_applications (itemappid,itemid,applicationid) VALUES('.$itemappid.','.$itemid.','.$appid.')');
		}

		info('Added new item '.$host['host'].':'.$key);

// add items to child hosts

		$db_hosts = get_hosts_by_templateid($host['hostid']);
		while($db_host = DBfetch($db_hosts)){
// recursion
			$result = add_item($description, $key, $db_host["hostid"],
				$delay, $history, $status, $type, $snmp_community, $snmp_oid,
				$value_type, $trapper_hosts, $snmp_port, $units, $multiplier,
				$delta, $snmpv3_securityname, $snmpv3_securitylevel,
				$snmpv3_authpassphrase, $snmpv3_privpassphrase, $formula,
				$trends, $logtimefmt, $valuemapid,$delay_flex, $params, $ipmi_sensor,
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

	function update_trigger_value_to_unknown_by_itemid($itemids){
		zbx_value2array($itemids);
		
		$now = time();
		$result = DBselect('SELECT DISTINCT t.triggerid '.
				' FROM triggers t,functions f '.
				' WHERE f.triggerid=t.triggerid '.
					' AND '.DBcondition('f.itemid',$itemids));

		$triggerids = array();
		while($row = DBfetch($result)){
			if(!add_event($row['triggerid'], TRIGGER_VALUE_UNKNOWN, $now))
				continue;
			$triggerids[$row['triggerid']] = $row['triggerid'];
		}
		
		if(!empty($triggers)){
			DBexecute('UPDATE triggers SET value='.TRIGGER_VALUE_UNKNOWN.' WHERE '.DBcondition('triggerid',$triggerids));
		}
	}

// Update Item status

	function update_item_status($itemids, $status){
		zbx_value2array($itemids);
		$result = true;
		
		$db_items = DBselect('SELECT itemid, status FROM items WHERE '.DBcondition('itemid',$itemids));
		while($row = DBfetch($db_items)){
			$old_status=$row['status'];

			if($status == $old_status){
				unset($itemids[$row['itemid']]);
			}
		}
		if(!empty($itemids)){
			update_trigger_value_to_unknown_by_itemid($itemids);
			
			if($status==ITEM_STATUS_ACTIVE)
				$sql='UPDATE items SET status='.$status.",error='',nextcheck=0 ".
					' WHERE '.DBcondition('itemid',$itemids);
			else
				$sql='UPDATE items SET status='.$status.
					' WHERE '.DBcondition('itemid',$itemids);
					
			$result = DBexecute($sql);
		}

	return $result;
	}
	
	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function	update_item($itemid,$description,$key,$hostid,$delay,$history,$status,$type,
		$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,
		$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,
		$formula,$trends,$logtimefmt,$valuemapid,$delay_flex,$params,$ipmi_sensor,$applications,$templateid=0)
	{
		$host = get_host_by_hostid($hostid);

		if(($i = array_search(0,$applications)) !== FALSE)
			unset($applications[$i]);

		if( !eregi('^'.ZBX_EREG_ITEM_KEY_FORMAT.'$', $key) ){
			error("Incorrect key format 'key_name[param1,param2,...]'");
			return false;
		}

		if($delay<1){
			error("Delay cannot be less than 1 second");
			return FALSE;
		}

		if ($delay_flex != '')
		{
			$arr_of_delay = explode(';', $delay_flex);

			foreach($arr_of_delay as $one_delay_flex)
			{
				$arr = explode('/', $one_delay_flex);
				if ($arr[0] < 1)
				{
					error("Delay cannot be less than 1 second ");
					return FALSE;
				}
			}
		}

		if( ($snmp_port<1)||($snmp_port>65535)){
			error("Invalid SNMP port");
			return FALSE;
		}

		if($value_type == ITEM_VALUE_TYPE_STR){
			$delta=0;
		}

		$db_item = DBfetch(DBselect("select itemid from items".
			" where hostid=$hostid and itemid<>$itemid and key_=".zbx_dbstr($key)));
		if($db_item && $templateid == 0){
			error("An item with the same Key already exists for host ".$host["host"].".".
				" The key must be unique.");
			return FALSE;
		}

		 // first update child items
		$db_tmp_items = DBselect("select itemid, hostid from items where templateid=$itemid");
		while($db_tmp_item = DBfetch($db_tmp_items)){
		// recursion
			$result = update_item(
				$db_tmp_item["itemid"], $description, $key, $db_tmp_item["hostid"],
				$delay, $history, $status, $type, $snmp_community, $snmp_oid,
				$value_type, $trapper_hosts, $snmp_port, $units, $multiplier,
				$delta, $snmpv3_securityname, $snmpv3_securitylevel,
				$snmpv3_authpassphrase, $snmpv3_privpassphrase, $formula,
				$trends, $logtimefmt, $valuemapid,$delay_flex, $params, $ipmi_sensor,
				get_same_applications_for_host($applications, $db_tmp_item["hostid"]),
				$itemid);


			if(!$result)
				return $result;
		}

		if($db_item && $templateid != 0){
			$result = delete_item($db_item["itemid"]);
			if(!$result) {
				error("Can't update item '".$host["host"].":$key'");
				return FALSE;
			}
		}

		DBexecute("update items set lastlogsize=0 where itemid=$itemid and key_<>".zbx_dbstr($key));

		if(isset($_REQUEST['applications_visible'])){	
			$result = DBexecute("delete from items_applications where itemid=$itemid");
			foreach($applications as $appid){
				$itemappid=get_dbid("items_applications","itemappid");
				DBexecute("insert into items_applications (itemappid,itemid,applicationid) values($itemappid,".$itemid.",".$appid.")");
			}
		}

		if ($status == ITEM_STATUS_ACTIVE)
			DBexecute("update items set error='' where itemid=$itemid and status<>$status");

		$result=DBexecute(
			'UPDATE items '.
			' SET description='.zbx_dbstr($description).',key_='.zbx_dbstr($key).','.
				'hostid='.$hostid.',delay='.$delay.',history='.$history.',type='.$type.','.
				'snmp_community='.zbx_dbstr($snmp_community).",snmp_oid=".zbx_dbstr($snmp_oid).','.
				'value_type='.$value_type.',trapper_hosts='.zbx_dbstr($trapper_hosts).','.
				'snmp_port='.$snmp_port.',units='.zbx_dbstr($units).',multiplier='.$multiplier.',delta='.$delta.','.
				'snmpv3_securityname='.zbx_dbstr($snmpv3_securityname).','.
				'snmpv3_securitylevel='.$snmpv3_securitylevel.','.
				'snmpv3_authpassphrase='.zbx_dbstr($snmpv3_authpassphrase).','.
				'snmpv3_privpassphrase='.zbx_dbstr($snmpv3_privpassphrase).','.
				'formula='.zbx_dbstr($formula).",trends=$trends,logtimefmt=".zbx_dbstr($logtimefmt).','.
				'valuemapid='.$valuemapid.',delay_flex='.zbx_dbstr($delay_flex).',params='.zbx_dbstr($params).','.
				'ipmi_sensor='.zbx_dbstr($ipmi_sensor).','.
				'templateid='.$templateid.
			' WHERE itemid='.$itemid);

		update_item_status($itemid, $status);

		if($result){
			info("Item '".$host["host"].":$key' updated");
		}
		
	return $result;
	}

	/*
	 * Function: smart_update_item
	 *
	 * Description:
	 *     Update specified fields of item
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function	smart_update_item($itemid,$description,$key,$hostid,$delay,$history,$status,$type,
		$snmp_community,$snmp_oid,$value_type,$trapper_hosts,$snmp_port,$units,$multiplier,$delta,
		$snmpv3_securityname,$snmpv3_securitylevel,$snmpv3_authpassphrase,$snmpv3_privpassphrase,
		$formula,$trends,$logtimefmt,$valuemapid,$delay_flex,$params,$ipmi_sensor,$applications)
	{
		$restore_rules= array(
					"description"		=> array(),
					"key"			=> array( 'db_varname' => 'key_' ),
					"hostid"		=> array(),
					"delay"			=> array('template' => 1),
					"history"		=> array('template' => 1 , 'httptest' => 1),
					"status"		=> array('template' => 1 , 'httptest' => 1),
					"type"			=> array(),
					"snmp_community"	=> array(),
					"snmp_oid"		=> array(),
					"value_type"		=> array(),
					"trapper_hosts"		=> array(),
					"snmp_port"		=> array(),
					"units"			=> array(),
					"multiplier"		=> array(),
					"delta"		=> array('template' => 1 , 'httptest' => 1),
					"snmpv3_securityname"	=> array(),
					"snmpv3_securitylevel"	=> array(),
					"snmpv3_authpassphrase"	=> array(),
					"snmpv3_privpassphrase"	=> array(),
					"formula"		=> array(),
					"trends"		=> array('template' => 1 , 'httptest' => 1),
					"logtimefmt"		=> array(),
					"valuemapid"		=> array('httptest' => 1),
					"params"		=> array(),
					"delay_flex"		=> array(),
					"ipmi_sensor"		=> array());

		$item_data = get_item_by_itemid($itemid);

		foreach($restore_rules as $var_name => $info){
			if (!isset($info['db_varname'])) $info['db_varname'] = $var_name;

			if ($item_data['type'] == ITEM_TYPE_HTTPTEST && !isset($info['httptest']))
				$$var_name = $item_data[$info['db_varname']];
			if (0 !=$item_data['templateid'] && !isset($info['template']))
				$$var_name = $item_data[$info['db_varname']];
			if(!isset($$var_name))
				$$var_name = $item_data[$info['db_varname']];
		}

		if($item_data['type'] == ITEM_TYPE_HTTPTEST)
			$applications = get_applications_by_itemid($itemid);

		return update_item($itemid,
			$description,$key,$hostid,$delay,
			$history,$status,$type,
			$snmp_community,$snmp_oid,$value_type,
			$trapper_hosts,$snmp_port,$units,
			$multiplier,$delta,$snmpv3_securityname,
			$snmpv3_securitylevel,$snmpv3_authpassphrase,
			$snmpv3_privpassphrase,$formula,$trends,
			$logtimefmt,$valuemapid,$delay_flex,$params,
			$ipmi_sensor,$applications,
			$item_data['templateid']);
	}

	/*
	 * Function: delete_template_items
	 *
	 * Description:
	 *     Delete items from host by templateid
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function delete_template_items($hostid, $templateids = null, $unlink_mode = false){
		zbx_value2array($templateids);
		
		$db_items = get_items_by_hostid($hostid);
		while($db_item = DBfetch($db_items)){
			if($db_item["templateid"] == 0)
				continue;

			if( !is_null($templateids)){
				$db_tmp_item = get_item_by_itemid($db_item["templateid"]);

				if(!uint_in_array($db_tmp_item["hostid"], $templateids)) continue;
			}

			if($unlink_mode){
				if(DBexecute('UPDATE items SET templateid=0 WHERE itemid='.$db_item["itemid"])){
					info("Item '".$db_item["key_"]."' unlinked");
				}
			}
			else{
				delete_item($db_item["itemid"]);
			}
		}
	}

	/*
	 * Function: copy_item_to_host
	 *
	 * Description:
	 *     Copy specified item to the host
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *
	 */
	function copy_item_to_host($itemid, $hostid, $copy_mode = false){
		$db_tmp_item = get_item_by_itemid($itemid);

		$result = add_item(
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
			$db_tmp_item["params"],
			$db_tmp_item["ipmi_sensor"],
			get_same_applications_for_host(get_applications_by_itemid($db_tmp_item["itemid"]),$hostid),
			$copy_mode ? 0 : $db_tmp_item["itemid"]);
			
	return $result;
	}

	/*
	 * Function: copy_template_items
	 *
	 * Description:
	 *     Copy items from template to the host
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: !!! Don't forget sync code with C !!!
	 *
	 */
	function copy_template_items($hostid, $templateid = null, $copy_mode = false){
		if($templateid == null){
			$templateid = array_keys(get_templates_by_hostid($hostid));
		}
		
		if(is_array($templateid)){
			foreach($templateid as $id)
				copy_template_items($hostid, $id, $copy_mode); // attention recursion
			return;
		}

		$db_tmp_items = get_items_by_hostid($templateid);

		
		while($db_tmp_item = DBfetch($db_tmp_items)){
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
				$db_tmp_item["params"],
				$db_tmp_item["ipmi_sensor"],
				get_same_applications_for_host(get_applications_by_itemid($db_tmp_item["itemid"]),$hostid),
				$copy_mode ? 0 : $db_tmp_item["itemid"]);
		}
	}

// Activate Item

	function activate_item($itemids){
		zbx_value2array($itemids);
		
// first update status for child items
		$chd_items = array();
		$db_tmp_items = DBselect('SELECT itemid, hostid FROM items WHERE '.DBcondition('templateid',$itemids));
		while($db_tmp_item = DBfetch($db_tmp_items)){ 
			$chd_items[$db_tmp_item['itemid']] = $db_tmp_item['itemid'];
		}
		if(!empty($chd_items)){ 
			activate_item($chd_items);  // Recursion !!!
		}

	return update_item_status($itemids, ITEM_STATUS_ACTIVE);
	}

// Disable Item
	function disable_item($itemids){
		zbx_value2array($itemids);

// first update status for child items
		$chd_items = array();
		$db_tmp_items = DBselect('SELECT itemid, hostid FROM items WHERE '.DBcondition('templateid',$itemids));
		while($db_tmp_item = DBfetch($db_tmp_items)){ 
			$chd_items[$db_tmp_item['itemid']] = $db_tmp_item['itemid'];
		}
		if(!empty($chd_items)){ 
			disable_item($chd_items);  // Recursion !!!
		}

		return update_item_status($itemids, ITEM_STATUS_DISABLED);
	}

	function get_item_by_key($key,$host=''){
		$item = false;

		$sql_from = '';
		$sql_where = '';
		if(!empty($host)){
			$sql_from = ',hosts h ';
			$sql_where = ' AND h.host='.zbx_dbstr($host).' AND i.hostid=h.hostid ';
		}
		$sql = 'SELECT DISTINCT i.* '.
			' FROM items i '.$sql_from.
			' WHERE i.key_='.zbx_dbstr($key).
				$sql_where;
		if($item = DBfetch(DBselect($sql))){
			$item = $item;
		}
	return $item;
	}

	function get_items_by_hostid($hostids){
		zbx_value2array($hostids);
	return DBselect('SELECT * FROM items WHERE '.DBcondition('hostid',$hostids));
	}

	function get_item_by_itemid($itemid){
		$row = DBfetch(DBselect('select * from items where itemid='.$itemid)); 
		if($row){
			return	$row;
		}
		error("No item with itemid=[$itemid]");
	return	FALSE;
	}
	
/*
 * Function: get_same_items_for_host
 *
 * Description:
 *		Replace items for specified host
 *
 * Author:
 *		Aly 
 *
 * Comments: 
 *		$error= true : rise Error if item doesn't exists(error generated), false: special processing (NO error generated)
 */
	function get_same_item_for_host($item,$dest_hostid, $error=true){
	
		if(!is_array($item)){
			$itemid = $item;
		}
		else if(isset($item['itemid'])){
			$itemid = $item['itemid'];
		}
		
		if(isset($itemid)){
			$sql = 'SELECT src.itemid '.
							' FROM items src, items dest '.
							' WHERE dest.itemid='.$itemid.
								' AND src.key_=dest.key_ '.
								' AND src.hostid='.$dest_hostid;
								
			$db_item = DBfetch(DBselect($sql));
			if (!$db_item && $error){
				$item = get_item_by_itemid($db_item['itemid']);
				$host = get_host_by_hostid($dest_hostid);
				error('Missed key "'.$item['key_'].'" for host "'.$host['host'].'"');
			}
			else{
				if(is_array($item)){
					return get_item_by_itemid($db_item['itemid']);
				}
				else{
					return $db_item['itemid'];
				}
			}
		}
	return false;	
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function delete_item($itemids){
		zbx_value2array($itemids);

// Get items INFO before delete them!
		$items = array();
		$item_res = DBselect('SELECT itemid, description, key_ FROM items WHERE '.DBcondition('itemid',$itemids));
		while($item_rows = DBfetch($item_res)){
			$items[$item_rows['itemid']] = $item_rows;
		}
// --
		$hosts = array();
		$hosts = get_host_by_itemid($itemids);
// first delete child items
		$del_cld_items = array();
		$db_items = DBselect('SELECT itemid FROM items WHERE '.DBcondition('templateid',$itemids));
		while($db_item = DBfetch($db_items)){		// recursion !!!!
			$del_cld_items[$db_item['itemid']] = $db_item['itemid'];
		}
		if(!empty($del_cld_items)){
			$result = delete_item($del_cld_items);
			if(!$result)	return	$result;
		}
//--
// triggers
		$result = delete_triggers_by_itemid($itemids);
		if(!$result)	return	$result;
//--

// delete graphs
		$del_graphs = array();
		$db_gitems = DBselect('SELECT DISTINCT graphid FROM graphs_items WHERE '.DBcondition('itemid',$itemids));
		while($db_gitem = DBfetch($db_gitems)){
			$del_graphs[$db_gitem['graphid']] = $db_gitem['graphid'];
		}
		if(!empty($del_graphs)){
			$result = delete_graph($del_graphs);
			if(!$result)	return	$result;
		}
//--

		$result = delete_history_by_itemid($itemids, 1 /* use housekeeper */);
		if(!$result)	return	$result;

		$temp_arr = array(SCREEN_RESOURCE_SIMPLE_GRAPH,SCREEN_RESOURCE_PLAIN_TEXT);
		
		DBexecute('DELETE FROM screens_items WHERE '.DBcondition('resourceid',$itemids).' AND '.DBcondition('resourcetype', $temp_arr));
		DBexecute('DELETE FROM items_applications WHERE '.DBcondition('itemid',$itemids));
		DBexecute("DELETE FROM profiles WHERE idx='web.favorite.graphids' AND source='itemid' AND ".DBcondition('value_id',$itemids));
		
		$result = DBexecute('DELETE FROM items WHERE '.DBcondition('itemid',$itemids));
		if($result){
			foreach($items as $itemid => $item){
				info("Item '".$hosts[$itemid]['host'].':'.$item['key_']."' deleted");
			}
		}
	return $result;
	}

	/*
	 * Function: get_n_param
	 *
	 * Description:
	 *     Return key parameter by index
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments: indexes between 1-x
	 *
	 */
	function get_n_param($key, $num){
		$param="";

		$num--;

		if(ereg('^'.ZBX_EREG_ITEM_KEY_FORMAT.'$', $key, $arr)){
			$params = zbx_get_params($arr[ZBX_KEY_PARAM_ID]);

			if(isset($params[$num])){
				$param = $params[$num];
			}
		}

	return $param;
	}

	function expand_item_key_by_data($item)
	{
		$key = $item['key_'];

		if (zbx_strstr($key, '{HOSTNAME}'))
		{
			$host = get_host_by_itemid($item['itemid']);
			$key = str_replace('{HOSTNAME}', $host['host'], $key);
		}
		else if (zbx_strstr($key, '{IPADDRESS}'))
		{
			$host = get_host_by_itemid($item['itemid']);
			$key = str_replace('{IPADDRESS}', $host['ip'], $key);
		}
		else if (zbx_strstr($key, '{HOST.DNS}'))
		{
			$host = get_host_by_itemid($item['itemid']);
			$key = str_replace('{HOST.DNS}', $host['dns'], $key);
		}
		else if (zbx_strstr($key, '{HOST.CONN}'))
		{
			$host = get_host_by_itemid($item['itemid']);
			$key = str_replace('{HOST.CONN}', $host['useip'] ? $host['ip'] : $host['dns'], $key);
		}

		return $key;
	}

	function item_description($item){
		$descr=$item['description'];
		$key=expand_item_key_by_data($item);

		for($i=9;$i>0;$i--){
			$descr=str_replace("$$i",get_n_param($key,$i),$descr);
		}

	return $descr;
	}
	
	function get_realhost_by_itemid($itemid){
		$item = get_item_by_itemid($itemid);
		if($item['templateid'] <> 0)
			return get_realhost_by_itemid($item['templateid']);

	return get_host_by_itemid($itemid);
	}

/*
 * Function: get_items_data_overview
 *
 * Description:
 *     Retrive overview table object for items
 *
 * Author:
 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 * Comments:
 *
 */
	function get_items_data_overview($hostids,$view_style=null){

		if(is_null($view_style)) $view_style = get_profile('web.overview.view.style',STYLE_TOP);
		
		$table = new CTableInfo(S_NO_ITEMS_DEFINED);

COpt::profiling_start('prepare data');
		$result = DBselect('SELECT DISTINCT h.hostid, h.host,i.itemid, i.key_, i.value_type, i.lastvalue, i.units, '.
				' i.description, t.priority, i.valuemapid, t.value as tr_value, t.triggerid '.
			' FROM hosts h, items i '.
				' LEFT JOIN functions f on f.itemid=i.itemid '.
				' LEFT JOIN triggers t on t.triggerid=f.triggerid '.
			' WHERE '.DBcondition('h.hostid',$hostids).
				' AND h.status='.HOST_STATUS_MONITORED.
				' AND h.hostid=i.hostid '.
				' AND i.status='.ITEM_STATUS_ACTIVE.
			' ORDER BY i.description,i.itemid');

		unset($items);
		unset($hosts);
		// get rid of warnings about $triggers undefined
		$items = array();
		while($row = DBfetch($result)){
			$descr = item_description($row);
			$row['host'] = get_node_name_by_elid($row['hostid']).$row['host'];
			$hosts[strtolower($row['host'])] = $row['host'];

			// A little tricky check for attempt to overwrite active trigger (value=1) with
			// inactive or active trigger with lower priority.
			if (!isset($items[$descr][$row['host']]) ||
				(
					(($items[$descr][$row['host']]['tr_value'] == TRIGGER_VALUE_FALSE) && ($row['tr_value'] == TRIGGER_VALUE_TRUE)) ||
					(
						(($items[$descr][$row['host']]['tr_value'] == TRIGGER_VALUE_FALSE) || ($row['tr_value'] == TRIGGER_VALUE_TRUE)) &&
						($row['priority'] > $items[$descr][$row['host']]['severity'])
					)
				)
			)
			{
				$items[$descr][$row['host']] = array(
					'itemid'	=> $row['itemid'],
					'value_type'=> $row['value_type'],
					'lastvalue'	=> $row['lastvalue'],
					'units'		=> $row['units'],
					'description'=> $row['description'],
					'valuemapid' => $row['valuemapid'],
					'severity'	=> $row['priority'],
					'tr_value'	=> $row['tr_value'],
					'triggerid'	=> $row['triggerid']
				);
			}
		}
		
		if(!isset($hosts)){
			return $table;
		}

		ksort($hosts);
COpt::profiling_stop('prepare data');
COpt::profiling_start('prepare table');

		if($view_style == STYLE_TOP){
			$header=array(new CCol(S_ITEMS,'center'));
			foreach($hosts as $hostname){
				$header=array_merge($header,array(new CImg('vtext.php?text='.$hostname)));
			}
			
			$table->SetHeader($header,'vertical_header');
			$curr_rime = time();
			
			foreach($items as $descr => $ithosts){
				$table_row = array(nbsp($descr));
				foreach($hosts as $hostname){
					$table_row = get_item_data_overview_cells($table_row,$ithosts,$hostname);
				}
				$table->AddRow($table_row);
			}
		}
		else{
			$header=array(new CCol(S_HOSTS,'center'));
			foreach($items as $descr => $ithosts){
				$header=array_merge($header,array(new CImg('vtext.php?text='.$descr)));
			}
			
			$table->SetHeader($header,'vertical_header');
			$curr_rime = time();
			
			foreach($hosts as $hostname){
				$table_row = array(nbsp($hostname));
				foreach($items as $descr => $ithosts){
					$table_row = get_item_data_overview_cells($table_row,$ithosts,$hostname);
				}
				$table->AddRow($table_row);
			}
		}
COpt::profiling_stop('prepare table');

		return $table;
	}
	
	function get_item_data_overview_cells(&$table_row,&$ithosts,$hostname){
		$css_class = NULL;
		unset($it_ov_menu);

		$value = '-';
		$ack = null;
		if(isset($ithosts[$hostname])){
			if($ithosts[$hostname]['tr_value'] == TRIGGER_VALUE_TRUE){
				$css_class = get_severity_style($ithosts[$hostname]['severity']);
				$ack = get_last_event_by_triggerid($ithosts[$hostname]['triggerid']);
				if ( 1 == $ack['acknowledged'] )
					$ack = array(SPACE, new CImg('images/general/tick.png','ack'));
				else
					$ack = null;
			}
			
			$value = format_lastvalue($ithosts[$hostname]);

			$it_ov_menu = array(
				array(S_VALUES,	null, null, 
					array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader'))),
				array(S_500_LATEST_VALUES, 'history.php?action=showlatest&itemid='.$ithosts[$hostname]['itemid'],
					array('tw'=>'_blank'))
				);

			switch($ithosts[$hostname]['value_type']){
				case ITEM_VALUE_TYPE_UINT64:
				case ITEM_VALUE_TYPE_FLOAT:
					$it_ov_menu = array_merge(array(
						/* name, url, (target [tw], statusbar [sb]), css, submenu */
						array(S_GRAPHS, null,  null, 
							array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader'))
							),
						array(S_LAST_HOUR_GRAPH, 'history.php?period=3600&action=showgraph&itemid='.
							$ithosts[$hostname]['itemid'], array('tw'=>'_blank')),
						array(S_LAST_WEEK_GRAPH, 'history.php?period=604800&action=showgraph&itemid='.
							$ithosts[$hostname]['itemid'], array('tw'=>'_blank')),
						array(S_LAST_MONTH_GRAPH, 'history.php?period=2678400&action=showgraph&itemid='.
							$ithosts[$hostname]['itemid'], array('tw'=>'_blank'))
						), $it_ov_menu);
					break;
				default:
					break;
			}
		}

//		if($value == '-')	$css_class = 'center';
		$value_col = new CCol(array($value,$ack),$css_class);

		if(isset($it_ov_menu)){
			$it_ov_menu  = new CPUMenu($it_ov_menu,170);
			$value_col->OnClick($it_ov_menu->GetOnActionJS());
			$value_col->AddOption('style', 'cursor: pointer;');
			$value_col->AddAction('onmouseover',
				'this.old_border=this.style.border; this.style.border=\'1px dotted #0C0CF0\'');
			$value_col->AddAction('onmouseout', 'this.style.border=this.old_border;');
			unset($it_ov_menu);
		}

		array_push($table_row,$value_col);
	return $table_row;
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function get_same_applications_for_host($applications, $hostid){
		$child_applications = array();

		foreach($applications as $appid){
			$db_apps = DBselect("select a1.applicationid from applications a1, applications a2".
					" where a1.name=a2.name and a1.hostid=".$hostid." and a2.applicationid=".$appid);
			$db_app = DBfetch($db_apps);
			if(!$db_app) continue;
			array_push($child_applications,$db_app["applicationid"]);
		}
	return $child_applications;
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function get_applications_by_itemid($itemids, $field='applicationid'){
		zbx_value2array($itemids);
		
		$result = array();
		
		$db_applications = DBselect('SELECT DISTINCT app.'.$field.' as result '.
										' FROM applications app, items_applications ia '.
										' WHERE app.applicationid=ia.applicationid '.
											' AND '.DBcondition('ia.itemid',$itemids));
		while($db_application = DBfetch($db_applications))
			array_push($result,$db_application['result']);

	return $result;
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function delete_history_by_itemid($itemids, $use_housekeeper=0){
		zbx_value2array($itemids);

		$result = delete_trends_by_itemid($itemids,$use_housekeeper);
		if(!$result)	return $result;

		if($use_housekeeper){
			foreach($itemids as $id => $itemid){
				$housekeeperid = get_dbid('housekeeper','housekeeperid');
				$sql = 'INSERT INTO housekeeper (housekeeperid,tablename,field,value)'.
							" VALUES ($housekeeperid,'history_text','itemid',$itemid)";
				DBexecute($sql);
				
				$housekeeperid = get_dbid('housekeeper','housekeeperid');
				$sql = 'INSERT INTO housekeeper (housekeeperid,tablename,field,value)'.
							" VALUES ($housekeeperid,'history_log','itemid',$itemid)";
				DBexecute($sql);
							
				$housekeeperid = get_dbid('housekeeper','housekeeperid');
				$sql = 'INSERT INTO housekeeper (housekeeperid,tablename,field,value)'.
							" VALUES ($housekeeperid,'history_uint','itemid',$itemid)";
				DBexecute($sql);
							
				$housekeeperid = get_dbid('housekeeper','housekeeperid');
				$sql = 'INSERT INTO housekeeper (housekeeperid,tablename,field,value)'.
							" VALUES ($housekeeperid,'history_str','itemid',$itemid)";
				DBexecute($sql);
							
				$housekeeperid = get_dbid('housekeeper','housekeeperid');
				$sql = 'INSERT INTO housekeeper (housekeeperid,tablename,field,value)'.
							" VALUES ($housekeeperid,'history','itemid',$itemid)";
				DBexecute($sql);
			}
			return TRUE;
		}

		DBexecute('DELETE FROM history_text WHERE '.DBcondition('itemid',$itemids));
		DBexecute('DELETE FROM history_log WHERE '.DBcondition('itemid',$itemids));
		DBexecute('DELETE FROM history_uint WHERE '.DBcondition('itemid',$itemids));
		DBexecute('DELETE FROM history_str WHERE '.DBcondition('itemid',$itemids));
		DBexecute('DELETE FROM history WHERE '.DBcondition('itemid',$itemids));
	return TRUE;
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function delete_trends_by_itemid($itemids, $use_housekeeper=0){
		zbx_value2array($itemids);
		
		if($use_housekeeper){
			foreach($itemids as $id => $itemid){
				$housekeeperid = get_dbid('housekeeper','housekeeperid');
				DBexecute('INSERT INTO housekeeper (housekeeperid,tablename,field,value)'.
					" VALUES ($housekeeperid, 'trends','itemid',$itemid)");
			}
			return TRUE;
		}
	return	DBexecute('DELETE FROM trends WHERE '.DBcondition('itemid',$itemids));
	}
	
	function format_lastvalue($db_item){
		if($db_item["value_type"] == ITEM_VALUE_TYPE_LOG){
			$row=DBfetch(DBselect('SELECT value FROM history_log WHERE itemid='.$db_item['itemid'].' ORDER BY clock DESC', 1));
			if($row){
				$lastvalue=/*nbsp(htmlspecialchars(*/$row['value']/*))*/;
				if(strlen($lastvalue) > 20)
					$lastvalue = substr($lastvalue,0,20)." ...";
				$lastvalue = nbsp(htmlspecialchars($lastvalue));
			}
			else{
				$lastvalue='-';
			}

		}
		else if(isset($db_item["lastvalue"])){
			if($db_item["value_type"] == ITEM_VALUE_TYPE_FLOAT){
				$lastvalue=convert_units($db_item["lastvalue"],$db_item["units"]);
			}
			else if($db_item["value_type"] == ITEM_VALUE_TYPE_UINT64){
				$lastvalue=convert_units($db_item["lastvalue"],$db_item["units"]);
			}
			else if($db_item["value_type"] == ITEM_VALUE_TYPE_TEXT){
				$lastvalue="...";
			}
			else if($db_item["value_type"] == ITEM_VALUE_TYPE_STR){
					$lastvalue=nbsp(htmlspecialchars(substr($db_item["lastvalue"],0,20)));
					if(strlen($db_item["lastvalue"]) > 20)
						$lastvalue .= " ...";
			}
			else{
				$lastvalue="Unknown value type";
			}
			if($db_item["valuemapid"] > 0);
				$lastvalue = replace_value_by_map($lastvalue, $db_item["valuemapid"]);

		}
		else{
			$lastvalue = "-";
		}
	return $lastvalue;
	}

	/*
	 * Function: item_get_history
	 *
	 * Description:
	 *     Get value from history
	 *
	 * Peremeters:
	 *     itemid - item ID
	 *     last  - 0 - last value (clock is used), 1 - last value
	 *
	 * Author:
	 *     Alexei Vladishev
	 *
	 * Comments:
	 *
	 */
	function item_get_history($db_item, $last = 1, $clock = 0){
		$value = NULL;

		switch($db_item["value_type"]){
			case ITEM_VALUE_TYPE_FLOAT:
				$table = "history";
				break;
			case ITEM_VALUE_TYPE_UINT64:
				$table = "history_uint";
				break;
			case ITEM_VALUE_TYPE_TEXT:
				$table = "history_text";
				break;
			case ITEM_VALUE_TYPE_STR:
				$table = "history_str";
				break;
			case ITEM_VALUE_TYPE_LOG:
			default:
				$table = "history_log";
				break;
		}
		
		if($last == 0){
			$sql = "select value from $table where itemid=".$db_item["itemid"]." and clock=$clock";
			$row = DBfetch(DBselect($sql, 1));
			if($row)
				$value = $row["value"];
		}
		else{
			$sql = "select max(clock) as clock from $table where itemid=".$db_item["itemid"];
			$row = DBfetch(DBselect($sql));
			if($row && !is_null($row["clock"])){
				$clock = $row["clock"];
				$sql = "select value from $table where itemid=".$db_item["itemid"]." and clock=$clock";
				$row = DBfetch(DBselect($sql, 1));
				if($row)
					$value = $row["value"];
			}
		}
		return $value;
	}
?>
