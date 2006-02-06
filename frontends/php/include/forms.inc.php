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
	include_once 	"include/defines.inc.php";
	include_once 	"include/db.inc.php";
//	include_once 	"include/local_en.inc.php";

	# Insert host template form
	function	insert_template_form()
	{
		global $_REQUEST;

		$frmTemplate = new CFormTable(S_TEMPLATE,'hosts.php');
		$frmTemplate->SetHelp('web.hosts.php');
		$frmTemplate->AddVar('config',$_REQUEST["config"]);

		if(isset($_REQUEST["hosttemplateid"]))
		{
			$frmTemplate->AddVar('hosttemplateid',$_REQUEST["hosttemplateid"]);
		}

		if(isset($_REQUEST["hosttemplateid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$result=DBselect("select * from hosts_templates".
				" where hosttemplateid=".$_REQUEST["hosttemplateid"]);
			$row=DBfetch($result);
	
			$hostid		= $row["hostid"];
			$templateid	= $row["templateid"];
		
			$items = array();	
			if(1 & $row["items"])	array_push($items,1);
			if(2 & $row["items"])	array_push($items,2);
			if(4 & $row["items"])	array_push($items,4);

			$triggers= array();	
			if(1 & $row["triggers"])	array_push($triggers,1);
			if(2 & $row["triggers"])	array_push($triggers,2);
			if(4 & $row["triggers"])	array_push($triggers,4);

			$graphs= array();	
			if(1 & $row["graphs"])	array_push($graphs,1);
			if(2 & $row["graphs"])	array_push($graphs,2);
			if(4 & $row["graphs"])	array_push($graphs,4);
		}
		else
		{
			$hostid		= get_request("hostid",0);
			$templateid	= get_request("templateid",0);

			$items		= get_request("items",array(1,2,4));
			$triggers 	= get_request("triggers",array(1,2,4));
			$graphs 	= get_request("graphs",array(1,2,4));
		}
		if($hostid!=0){
			$host	 = get_host_by_hostid($hostid);
			$frmTemplate->AddVar('hostid',$hostid);
		}

		if($templateid!=0)
			$template= get_host_by_hostid($templateid);

		$cmbTemplate = new CComboBox('templateid',$templateid);
	        $hosts=DBselect("select hostid,host from hosts order by host");
		while($host=DBfetch($hosts))
			$cmbTemplate->AddItem($host["hostid"],$host["host"]);

		$frmTemplate->AddRow(S_TEMPLATE,$cmbTemplate);

		$frmTemplate->AddRow(S_ITEMS,array(
			new CCheckBox('items[]',	in_array(1,$items)?'yes':'no',	S_ADD,	NULL,	1),
			new CCheckBox('items[]',	in_array(2,$items)?'yes':'no',	S_UPDATE,NULL,	2),
			new CCheckBox('items[]',	in_array(4,$items)?'yes':'no',	S_DELETE,NULL,	4)
		));

		$frmTemplate->AddRow(S_TRIGGERS,array(
			new CCheckBox('triggers[]',	in_array(1,$triggers)?'yes':'no',S_ADD,	NULL,	1),
			new CCheckBox('triggers[]',	in_array(2,$triggers)?'yes':'no',S_UPDATE,NULL,	2),
			new CCheckBox('triggers[]',	in_array(4,$triggers)?'yes':'no',S_DELETE,NULL,	4),
		));

		$frmTemplate->AddRow(S_GRAPHS,array(
			new CCheckBox('graphs[]',	in_array(1,$graphs)?'yes':'no',	S_ADD,	NULL,	1),
			new CCheckBox('graphs[]',	in_array(2,$graphs)?'yes':'no',	S_UPDATE,NULL,	2),
			new CCheckBox('graphs[]',	in_array(4,$graphs)?'yes':'no',	S_DELETE,NULL,	4),
		));

		$frmTemplate->AddItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($_REQUEST["hosttemplateid"]))
		{
			$frmTemplate->AddItemToBottomRow(SPACE);
			$frmTemplate->AddItemToBottomRow(new CButtonDelete('Delete selected linkage?',
				url_param("form").url_param("config").url_param("hostid").
				url_param("hosttemplateid")));
		} else {
		}
		$frmTemplate->AddItemToBottomRow(SPACE);
		$frmTemplate->AddItemToBottomRow(new CButtonCancel(url_param("config").url_param("hostid")));

		$frmTemplate->Show();
	}

	# Insert form for User
	function	insert_user_form($userid,$profile=0)
	{
		$frm_title = S_USER;
		if(isset($userid))
		{
			$user=get_user_by_userid($userid);
			$frm_title = S_USER." \"".$user["alias"]."\"";
		}
// TMP!!!
//    isset($_REQUEST["register"]) mus be deleted
//    needed rewrite permisions to delete id
// TMP!!!
		if(isset($userid) && (!isset($_REQUEST["form_refresh"]) || isset($_REQUEST["register"])))
		{
			$alias		= $user["alias"];
			$name		= $user["name"];
			$surname	= $user["surname"];
			$password	= "";
			$url		= $user["url"];
			$autologout	= $user["autologout"];
			$lang		= $user["lang"];
			$refresh	= $user["refresh"];
		}
		else
		{
			$alias		= get_request("alias","");
			$name		= get_request("name","");
			$surname	= get_request("surname","");
			$password	= "";
			$url 		= get_request("url","");
			$autologout	= get_request("autologout","900");
			$lang		= get_request("lang","en_gb");
			$refresh	= get_request("refresh","30");
		}

		$frmUser = new CFormTable($frm_title);
		$frmUser->SetHelp("web.users.users.php");
		$frmUser->AddVar("config",get_request("config",0));

		if($profile==0) 
			$frmUser->SetAction("users.php");
		else
			$frmUser->SetAction("profile.php");

		if(isset($userid))	$frmUser->AddVar("userid",$userid);

		if($profile==0)
		{
			$frmUser->AddRow(S_ALIAS,	new CTextBox("alias",$alias,20));
			$frmUser->AddRow(S_NAME,	new CTextBox("name",$name,20));
			$frmUser->AddRow(S_SURNAME,	new CTextBox("surname",$surname,20));
		}

		$frmUser->AddRow(S_PASSWORD,	new CPassBox("password1",$password,20));
		$frmUser->AddRow(S_PASSWORD_ONCE_AGAIN,	new CPassBox("password2",$password,20));

		$cmbLang = new CcomboBox('lang',$lang);
		$cmbLang->AddItem("en_gb",S_ENGLISH_GB);
		$cmbLang->AddItem("cn_zh",S_CHINESE_CN);
		$cmbLang->AddItem("fr_fr",S_FRENCH_FR);
		$cmbLang->AddItem("de_de",S_GERMAN_DE);
		$cmbLang->AddItem("it_it",S_ITALIAN_IT);
		$cmbLang->AddItem("lv_lv",S_LATVIAN_LV);
		$cmbLang->AddItem("ru_ru",S_RUSSIAN_RU);
		$cmbLang->AddItem("sp_sp",S_SPANISH_SP);
		$cmbLang->AddItem("ja_jp",S_JAPANESE_JP);

		$frmUser->AddRow(S_LANGUAGE, $cmbLang);

		$frmUser->AddRow(S_AUTO_LOGOUT_IN_SEC,	new CTextBox("autologout",$autologout,5));
		$frmUser->AddRow(S_URL_AFTER_LOGIN,	new CTextBox("url",$url,50));
		$frmUser->AddRow(S_SCREEN_REFRESH,	new CTextBox("refresh",$refresh,5));

		$frmUser->AddItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($userid))
		{
			$frmUser->AddItemToBottomRow(SPACE);
			$frmUser->AddItemToBottomRow(new CButtonDelete("Delete selected user?",
				url_param("form").url_param("config").url_param("userid")));
		}
		$frmUser->AddItemToBottomRow(SPACE);
		$frmUser->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmUser->Show();
	}

	# Insert form for User permissions
	function	insert_permissions_form()
	{
		global  $_REQUEST;

		$frmPerm = new CFormTable("New permission","users.php");
		$frmPerm->SetHelp("web.users.users.php");

		$frmPerm->AddVar("userid",$_REQUEST["userid"]);
		$frmPerm->AddVar("config",get_request("config",0));

		$cmbRes = new CComboBox("right");
		$cmbRes->AddItem("Configuration of Zabbix","Configuration of Zabbix");
		$cmbRes->AddItem("Default permission","Default permission");
		$cmbRes->AddItem("Graph","Graph");
		$cmbRes->AddItem("Host","Host");
		$cmbRes->AddItem("Screen","Screen");
		$cmbRes->AddItem("Service","IT Service");
		$cmbRes->AddItem("Item","Item");
		$cmbRes->AddItem("Network map","Network map");
		$cmbRes->AddItem("Trigger comment","Trigger comment");
		$cmbRes->AddItem("User","User");
		$frmPerm->AddRow(S_RESOURCE,$cmbRes);

		$cmbPerm = new CComboBox("permission");
		$cmbPerm->AddItem("R","Read-only");
		$cmbPerm->AddItem("U","Read-write");
		$cmbPerm->AddItem("H","Hide");
		$cmbPerm->AddItem("A","Add");
		$frmPerm->AddRow(S_PERMISSION,$cmbPerm);

		$frmPerm->AddRow("Resource ID (0 for all)",new CTextBox("id",0));
		$frmPerm->AddItemToBottomRow(new CButton("register","add permission"));
		$frmPerm->Show();
	}

	# Insert form for User Groups
	function	insert_usergroups_form($usrgrpid)
	{
		global  $_REQUEST;

		$frm_title = S_USER_GROUP;
		if(isset($usrgrpid))
		{
			$usrgrp=get_usergroup_by_usrgrpid($usrgrpid);
			$frm_title = S_USER_GROUP." \"".$usrgrp["name"]."\"";
		}

		$users = get_request("users",array());
		if(isset($usrgrpid) && !isset($_REQUEST["form_refresh"]))
		{
			$name	= $usrgrp["name"];
			$db_users=DBselect("select distinct u.userid from users u,users_groups ug ".
				"where u.userid=ug.userid and ug.usrgrpid=".$usrgrpid.
				" order by alias");

			while($db_user=DBfetch($db_users))
			{
				if(in_array($db_user["userid"], $users)) continue;
				array_push($users,$db_user["userid"]);
			}
		}
		else
		{
			$name	= get_request("gname","");
		}

		$frmUserG = new CFormTable($frm_title,"users.php");
		$frmUserG->SetHelp("web.users.groups.php");
		$frmUserG->AddVar("config",get_request("config",2));
		if(isset($usrgrpid))
		{
			$frmUserG->AddVar("usrgrpid",$usrgrpid);
		}
		$frmUserG->AddRow(S_GROUP_NAME,new CTextBox("gname",$name,30));

		$form_row = array();
		$db_users=DBselect("select distinct userid,alias from users order by alias");
		while($db_user=DBfetch($db_users))
		{
			array_push($form_row,
				new CCheckBox("users[]",
					in_array($db_user["userid"],$users) ? 'yes' : 'no',
					$db_user["alias"],	/* caption */
					NULL,			/* action */
					$db_user["userid"]),	/* value */
				BR);
		}
		$frmUserG->AddRow(S_USERS,$form_row);
	
		$frmUserG->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["usrgrpid"]))
		{
			$frmUserG->AddItemToBottomRow(SPACE);
			$frmUserG->AddItemToBottomRow(new CButtonDelete("Delete selected group?",
				url_param("form").url_param("config").url_param("usrgrpid")));
		}
		$frmUserG->AddItemToBottomRow(SPACE);
		$frmUserG->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmUserG->Show();
	}


	# Insert form for Item information
	function	insert_item_form()
	{
		global  $_REQUEST;

		$description	= get_request("description"	,"");
		$key		= get_request("key"		,"");
		$host		= get_request("host",		NULL);
		$delay		= get_request("delay"		,30);
		$history	= get_request("history"		,90);
		$status		= get_request("status"		,0);
		$type		= get_request("type"		,0);
		$snmp_community	= get_request("snmp_community"	,"public");
		$snmp_oid	= get_request("snmp_oid"	,"interfaces.ifTable.ifEntry.ifInOctets.1");
		$snmp_port	= get_request("snmp_port"	,161);
		$value_type	= get_request("value_type"	,0);
		$trapper_hosts	= get_request("trapper_hosts"	,"");
		$units		= get_request("units"		,'');
		$multiplier	= get_request("multiplier"	,0);
		$hostid		= get_request("hostid"		,0);
		$delta		= get_request("delta"		,0);
		$trends		= get_request("trends"		,365);

		$snmpv3_securityname	= get_request("snmpv3_securityname"	,"");
		$snmpv3_securitylevel	= get_request("snmpv3_securitylevel"	,0);
		$snmpv3_authpassphrase	= get_request("snmpv3_authpassphrase"	,"");
		$snmpv3_privpassphrase	= get_request("snmpv3_privpassphrase"	,"");

		$formula	= get_request("formula"		,"1");
		$logtimefmt	= get_request("logtimefmt"	,"");

		$add_groupid	= get_request("add_groupid"	,get_request("groupid",0));


		if(is_null($host)&&$hostid>0){
			$host_info = get_host_by_hostid($hostid);
			$host = $host_info["host"];
		}

		if(isset($_REQUEST["itemid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$result=DBselect("select i.description, i.key_, h.host, i.delay,".
				" i.history, i.status, i.type, i.snmp_community,i.snmp_oid,i.value_type,".
				" i.trapper_hosts,i.snmp_port,i.units,i.multiplier,h.hostid,i.delta,".
				" i.trends,i.snmpv3_securityname,i.snmpv3_securitylevel,".
				" i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.formula,i.logtimefmt".
				" from items i,hosts h where i.itemid=".$_REQUEST["itemid"].
				" and h.hostid=i.hostid");
			$row=DBfetch($result);
		
			$description	= $row["description"];
			$key		= $row["key_"];
			$host		= $row["host"];
			$delay		= $row["delay"];
			$history	= $row["history"];
			$status		= $row["status"];
			$type		= $row["type"];
			$snmp_community	= $row["snmp_community"];
			$snmp_oid	= $row["snmp_oid"];
			$snmp_port	= $row["snmp_port"];
			$value_type	= $row["value_type"];
			$trapper_hosts	= $row["trapper_hosts"];
			$units		= $row["units"];
			$multiplier	= $row["multiplier"];
			$hostid		= $row["hostid"];
			$delta		= $row["delta"];
			$trends		= $row["trends"];

			$snmpv3_securityname	= $row["snmpv3_securityname"];
			$snmpv3_securitylevel	= $row["snmpv3_securitylevel"];
			$snmpv3_authpassphrase	= $row["snmpv3_authpassphrase"];
			$snmpv3_privpassphrase	= $row["snmpv3_privpassphrase"];

			$formula	= $row["formula"];
			$logtimefmt	= $row["logtimefmt"];
		}

		$frmItem = new CFormTable(S_ITEM,"items.php#form");
		$frmItem->SetHelp("web.items.item.php");

		if(isset($_REQUEST["itemid"]))
			$frmItem->AddVar("itemid",$_REQUEST["itemid"]);
		if(isset($_REQUEST["groupid"]))
			$frmItem->AddVar("groupid",$_REQUEST["groupid"]);

		$frmItem->AddRow(S_DESCRIPTION, new CTextBox("description",$description,40));

		$frmItem->AddVar("hostid",$hostid);
		$frmItem->AddRow(S_HOST, array(
			new CTextBox("host",$host,30,NULL,'yes'),
			new CButton("btn1","Select","return PopUp('popup.php?form=".$frmItem->GetName().
				"&field1=hostid&field2=host','host','width=450,height=450,".
				"resizable=1,scrollbars=1');","T")
		));

		$cmbType = new CComboBox("type",$type,"submit()");
		$cmbType->AddItem(ITEM_TYPE_ZABBIX,'Zabbix agent');
		$cmbType->AddItem(ITEM_TYPE_ZABBIX_ACTIVE,'Zabbix agent (active)');
		$cmbType->AddItem(ITEM_TYPE_SIMPLE,'Simple check');
		$cmbType->AddItem(ITEM_TYPE_SNMPV1,'SNMPv1 agent');
		$cmbType->AddItem(ITEM_TYPE_SNMPV2C,'SNMPv2 agent');
		$cmbType->AddItem(ITEM_TYPE_SNMPV3,'SNMPv3 agent');
		$cmbType->AddItem(ITEM_TYPE_TRAPPER,'Zabbix trapper');
		$cmbType->AddItem(ITEM_TYPE_INTERNAL,'Zabbix internal');
		$frmItem->AddRow(S_TYPE, $cmbType);


		if(($type==ITEM_TYPE_SNMPV1)||($type==ITEM_TYPE_SNMPV2C))
		{ 
			$frmItem->AddVar("snmpv3_securityname",$snmpv3_securityname);
			$frmItem->AddVar("snmpv3_securitylevel",$snmpv3_securitylevel);
			$frmItem->AddVar("snmpv3_authpassphrase",$snmpv3_authpassphrase);
			$frmItem->AddVar("snmpv3_privpassphrase",$snmpv3_privpassphrase);

			$frmItem->AddRow(S_SNMP_COMMUNITY, new CTextBox("snmp_community",$snmp_community,16));
			$frmItem->AddRow(S_SNMP_OID, new CTextBox("snmp_oid",$snmp_oid,40));
			$frmItem->AddRow(S_SNMP_PORT, new CTextBox("snmp_port",$snmp_port,5));
		}
		else if($type==ITEM_TYPE_SNMPV3)
		{
			$frmItem->AddVar("snmp_community",$snmp_community);

			$frmItem->AddRow(S_SNMP_OID, new CTextBox("snmp_oid",$snmp_oid,40));

			$frmItem->AddRow(S_SNMPV3_SECURITY_NAME,
				new CTextBox("snmpv3_securityname",$snmpv3_securityname,64));

			$cmbSecLevel = new CComboBox("snmpv3_securitylevel",$snmpv3_securitylevel);
			$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,"NoAuthPriv");
			$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,"AuthNoPriv");
			$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,"AuthPriv");
			$frmItem->AddRow(S_SNMPV3_SECURITY_LEVEL, $cmbSecLevel);

			$frmItem->AddRow(S_SNMPV3_AUTH_PASSPHRASE,
				new CTextBox("snmpv3_authpassphrase",$snmpv3_authpassphrase,64));

			$frmItem->AddRow(S_SNMPV3_PRIV_PASSPHRASE,
				new CTextBox("snmpv3_privpassphrase",$snmpv3_privpassphrase,64));

			$frmItem->AddRow(S_SNMP_PORT, new CTextBox("snmp_port",$snmp_port,5));
		}
		else
		{
			$frmItem->AddVar("snmp_community",$snmp_community);
			$frmItem->AddVar("snmp_oid",$snmp_oid);
			$frmItem->AddVar("snmp_port",$snmp_port);
			$frmItem->AddVar("snmpv3_securityname",$snmpv3_securityname);
			$frmItem->AddVar("snmpv3_securitylevel",$snmpv3_securitylevel);
			$frmItem->AddVar("snmpv3_authpassphrase",$snmpv3_authpassphrase);
			$frmItem->AddVar("snmpv3_privpassphrase",$snmpv3_privpassphrase);
		}

		$frmItem->AddRow(S_KEY, new CTextBox("key",$key,40));

		if( ($value_type==ITEM_VALUE_TYPE_FLOAT) || ($value_type==ITEM_VALUE_TYPE_UINT64))
		{
			$frmItem->AddRow(S_UNITS, new CTextBox("units",$units,40));

			$cmbMultipler = new CComboBox("multiplier",$multiplier,"submit()");
			$cmbMultipler->AddItem(0,S_DO_NOT_USE);
			$cmbMultipler->AddItem(1,S_CUSTOM_MULTIPLIER);
			$frmItem->AddRow(S_USE_MULTIPLIER, $cmbMultipler);
		}
		else
		{
			$frmItem->AddVar("units",$units);
			$frmItem->AddVar("multiplier",$multiplier);
		}

		if($multiplier == 1)
		{
			$frmItem->AddRow(S_CUSTOM_MULTIPLIER, new CTextBox("formula",$formula,40));
		}
		else
		{
			$frmItem->AddVar("formula",$formula);
		}
		if($type != ITEM_TYPE_TRAPPER)
		{
			$frmItem->AddRow(S_UPDATE_INTERVAL_IN_SEC, new CTextBox("delay",$delay,5));
		}
		else
		{
			$frmItem->AddVar("delay",$delay);
		}

		$frmItem->AddRow(S_KEEP_HISTORY_IN_DAYS, new CTextBox("history",$history,8));
		$frmItem->AddRow(S_KEEP_TRENDS_IN_DAYS, new CTextBox("trends",$trends,8));

		$cmbStatus = new CComboBox("status",$status);
		$cmbStatus->AddItem(0,S_MONITORED);
		$cmbStatus->AddItem(1,S_DISABLED);
#		$cmbStatus->AddItem(2,"Trapper");
		$cmbStatus->AddItem(3,S_NOT_SUPPORTED);
		$frmItem->AddRow(S_STATUS,$cmbStatus);

		$cmbValType = new CComboBox("value_type",$value_type,"submit()");
		$cmbValType->AddItem(ITEM_VALUE_TYPE_FLOAT, S_NUMERIC_FLOAT);
		$cmbValType->AddItem(ITEM_VALUE_TYPE_UINT64, S_NUMERIC_UINT64);
		$cmbValType->AddItem(ITEM_VALUE_TYPE_STR, S_CHARACTER);
		$cmbValType->AddItem(ITEM_VALUE_TYPE_LOG, S_LOG);
		$frmItem->AddRow(S_TYPE_OF_INFORMATION,$cmbValType);

		if($value_type==ITEM_VALUE_TYPE_LOG)
		{
			$frmItem->AddRow(S_LOG_TIME_FORMAT, new CTextBox("logtimefmt",$logtimefmt,16));
		}
		else
		{
			$frmItem->AddVar("logtimefmt",$logtimefmt);
		}

		if( ($value_type==ITEM_VALUE_TYPE_FLOAT) || ($value_type==ITEM_VALUE_TYPE_UINT64))
		{
			$cmbDelta= new CComboBox("delta",$delta);
			$cmbDelta->AddItem(0,S_AS_IS);
			$cmbDelta->AddItem(1,S_DELTA_SPEED_PER_SECOND);
			$cmbDelta->AddItem(2,S_DELTA_SIMPLE_CHANGE);
			$frmItem->AddRow(S_STORE_VALUE,$cmbDelta);
		}
		else
		{
			$frmItem->AddVar("delta",0);
		}

		if($type==2)
		{
			$frmItem->AddRow(S_ALLOWED_HOSTS, new CTextBox("trapper_hosts",$trapper_hosts,40));
		}
		else
		{
			$frmItem->AddVar("trapper_hosts",$trapper_hosts);
		}

		$frmRow = array(new CButton("save",S_SAVE));
		if(isset($_REQUEST["itemid"]))
		{
			array_push($frmRow,
				SPACE,
				new CButtonDelete("Delete selected item?",
					url_param("form").url_param("groupid").url_param("hostid").
					url_param("itemid"))
			);
		}
		array_push($frmRow,
			SPACE,
			new CButtonCancel(url_param("groupid").url_param("hostid")));

		$frmItem->AddSpanRow($frmRow,"form_row_last");

	        $cmbGroups = new CComboBox("add_groupid",$add_groupid);		

	        $groups=DBselect("select groupid,name from groups order by name");
	        while($group=DBfetch($groups))
	        {
// Check if at least one host with read permission exists for this group
	                $hosts=DBselect("select h.hostid,h.host from hosts h,hosts_groups hg".
				" where hg.groupid=".$group["groupid"]." and hg.hostid=h.hostid".
				" and h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host".
				" order by h.host");
	                while($host=DBfetch($hosts))
	                {
	                        if(!check_right("Host","U",$host["hostid"])) continue;
				$cmbGroups->AddItem($group["groupid"],$group["name"]);
				break;
	                }
	        }
		$frmItem->AddRow(S_GROUP,$cmbGroups);

		$cmbAction = new CComboBox("action");
		$cmbAction->AddItem("add to group",S_ADD_TO_GROUP);
		if(isset($_REQUEST["itemid"]))
		{
			$cmbAction->AddItem("update in group",S_UPDATE_IN_GROUP);
			$cmbAction->AddItem("delete from group",S_DELETE_FROM_GROUP);
		}
		$frmItem->AddItemToBottomRow($cmbAction);
		$frmItem->AddItemToBottomRow(SPACE);
		$frmItem->AddItemToBottomRow(new CButton("register","do"));

		$frmItem->Show();
	}


	function	insert_login_form()
	{
		$frmLogin = new CFormTable('Login','index.php',"post","multipart/form-data");
		$frmLogin->SetHelp('web.index.login');
		$frmLogin->AddRow('Login name', new CTextBox('name'));
		$frmLogin->AddRow('Password', new CPassBox('password'));
		$frmLogin->AddItemToBottomRow(new CButton('enter','Enter'));
		$frmLogin->Show();

		SetFocus($frmLogin->GetName(),"name");
	}

/*
	# Insert form for Problem
	function	insert_problem_form($problemid)
	{
		show_form_begin();
		echo "Problem definition";
		show_table2_v_delimiter();
		echo "<form method=\"post\" action=\"helpdesk.php\">";
		echo "<input name=\"problemid\" type=hidden value=$problemid size=8>";
		echo "Description";
		show_table2_h_delimiter();
		echo "<input name=\"description\" value=\"$description\" size=70>";

		show_table2_v_delimiter();
		echo "Severity";
		show_table2_h_delimiter();
		echo "<SELECT NAME=\"priority\" size=\"1\">";
		echo "<OPTION VALUE=\"0\" "; if($priority==0) echo "SELECTED"; echo ">Not classified";
		echo "<OPTION VALUE=\"1\" "; if($priority==1) echo "SELECTED"; echo ">Information";
		echo "<OPTION VALUE=\"2\" "; if($priority==2) echo "SELECTED"; echo ">Warning";
		echo "<OPTION VALUE=\"3\" "; if($priority==3) echo "SELECTED"; echo ">Average";
		echo "<OPTION VALUE=\"4\" "; if($priority==4) echo "SELECTED"; echo ">High";
		echo "<OPTION VALUE=\"5\" "; if($priority==5) echo "SELECTED"; echo ">Disaster";
		echo "</SELECT>";

		show_table2_v_delimiter();
		echo "Status";
		show_table2_h_delimiter();
		echo "<SELECT NAME=\"status\" value=\"$status\" size=\"1\">";
		echo "<OPTION VALUE=\"0\"";
		if($status==0) echo "SELECTED";
		echo ">Opened";
		echo "<OPTION VALUE=\"1\"";
		if($status==1) echo "SELECTED";
		echo ">Closed";
		echo "</SELECT>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
		if(isset($problemid))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\">";
		}

		show_table2_header_end();
	}
*/
	# Insert form for Trigger
	function	insert_trigger_form()
	{
		$frmTrig = new CFormTable(S_TRIGGER,"triggers.php");
		$frmTrig->SetHelp("web.triggers.trigger.php");

		if(isset($_REQUEST["hostid"]))
		{
			$frmTrig->AddVar("hostid",$_REQUEST["hostid"]);
		}

		$dep_el=array();
		$dependences = get_request("dependences",array());
	
		if(isset($_REQUEST["triggerid"]))
		{
			$frmTrig->AddVar("triggerid",$_REQUEST["triggerid"]);
			$trigger=get_trigger_by_triggerid($_REQUEST["triggerid"]);
			$description	= htmlspecialchars(stripslashes($trigger["description"]));

			$frmTrig->SetTitle(S_TRIGGER." \"".$description."\"");
		}

		if(isset($_REQUEST["triggerid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$expression	= explode_exp($trigger["expression"],0);
			$priority	= $trigger["priority"];
			$status		= $trigger["status"];
			$comments	= $trigger["comments"];
			$url		= $trigger["url"];

			$trigs=DBselect("select t.triggerid,t.description from triggers t,trigger_depends d".
				" where t.triggerid=d.triggerid_up and d.triggerid_down=".$_REQUEST["triggerid"]);
			while($trig=DBfetch($trigs))
			{
				if(in_array($trig["triggerid"],$dependences))	continue;
				array_push($dependences,$trig["triggerid"]);
			}
		}
		else
		{
			$expression	= get_request("expression"	,"");
			$description	= get_request("description"	,"");
			$priority	= get_request("priority"	,0);
			$status		= get_request("status"		,0);
			$comments	= get_request("comments"	,"");
			$url		= get_request("url"		,"");
		}

		$frmTrig->AddRow(S_NAME, new CTextBox("description",$description,70));
		$frmTrig->AddRow(S_EXPRESSION,new CTextBox("expression",$expression,70));

	/* dependences */
		foreach($dependences as $val){
			array_push($dep_el,
				new CCheckBox("rem_dependence[]",
					'no'
					,expand_trigger_description($val),
					NULL,
					strval($val)),
				BR);
			$frmTrig->AddVar("dependences[]",strval($val));
		}

		if(count($dep_el)==0)
			array_push($dep_el, "No dependences defined");
		else
			array_push($dep_el, new CButton('del_dependence','delete selected'));
		$frmTrig->AddRow("The trigger depends on",$dep_el);
	/* end dependences */

	/* new dependence */
		$cmbDepID = new CComboBox("new_dependence");
		if(isset($_REQUEST["triggerid"]))
			$sql="select t.triggerid,t.description from triggers t".
				" where t.triggerid!=".$_REQUEST["triggerid"]." order by t.description";
		else
			$sql="select t.triggerid,t.description from triggers t order by t.description";

		$db_trigs=DBselect($sql);
		while($db_trig=DBfetch($db_trigs))
		{
			$cmbDepID->AddItem($db_trig["triggerid"],
				expand_trigger_description($db_trig["triggerid"]));
		}
		$frmTrig->AddRow("New dependency",array(
			$cmbDepID,SPACE,
			new CButton("add_dependence","add")));
	/* end new dwpendence */

		$cmbPrior = new CComboBox("priority");
		$cmbPrior->AddItem(0,"Not classified");
		$cmbPrior->AddItem(1,"Information");
		$cmbPrior->AddItem(2,"Warning");
		$cmbPrior->AddItem(3,"Average");
		$cmbPrior->AddItem(4,"High");
		$cmbPrior->AddItem(5,"Disaster");
		$frmTrig->AddRow(S_SEVERITY,$cmbPrior);

		$frmTrig->AddRow(S_COMMENTS,new CTextArea("comments",$comments,70,7));
		$frmTrig->AddRow(S_URL,new CTextBox("url",$url,70));
		$frmTrig->AddRow(S_DISABLED,new CCheckBox("disabled",$status));
 
		$frmTrig->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["triggerid"]))
		{
			$frmTrig->AddItemToBottomRow(SPACE);
			$frmTrig->AddItemToBottomRow(new CButtonDelete("Delete trigger?",
				url_param("form").url_param("groupid").url_param("hostid").
				url_param("triggerid")));
		}
		$frmTrig->AddItemToBottomRow(SPACE);
		$frmTrig->AddItemToBottomRow(new CButtonCancel(url_param("groupid").url_param("hostid")));
		$frmTrig->Show();
	}

	function	insert_graph_form()
	{
		global  $_REQUEST;

		$name=@iif(isset($_REQUEST["name"]),$_REQUEST["name"],"");
		$width=@iif(isset($_REQUEST["width"]),$_REQUEST["width"],900);
		$height=@iif(isset($_REQUEST["height"]),$_REQUEST["height"],200);
		$yaxistype=@iif(isset($_REQUEST["yaxistype"]),$_REQUEST["yaxistype"],GRAPH_YAXIS_TYPE_CALCULATED);
		$yaxismin=@iif(isset($_REQUEST["yaxismin"]),$_REQUEST["yaxismin"],0.00);
		$yaxismax=@iif(isset($_REQUEST["yaxismax"]),$_REQUEST["yaxismax"],100.00);

		if(isset($_REQUEST["graphid"])&&!isset($_REQUEST["name"]))
		{
			$result=DBselect("select g.graphid,g.name,g.width,g.height,g.yaxistype,g.yaxismin,g.yaxismax from graphs g where graphid=".$_REQUEST["graphid"]);
			$row=DBfetch($result);
			$name=$row["name"];
			$width=$row["width"];
			$height=$row["height"];
			$yaxistype=$row["yaxistype"];
			$yaxismin=$row["yaxismin"];
			$yaxismax=$row["yaxismax"];
		}
	
		$frmGraph = new CFormTable(S_GRAPH,"graphs.php");
		$frmGraph->SetHelp("web.graphs.graph.php");

		if(isset($_REQUEST["graphid"]))
		{
			$frmGraph->AddVar("graphid",$_REQUEST["graphid"]);
		}
		$frmGraph->AddRow(S_NAME,new CTextBox("name",$name,32));
		$frmGraph->AddRow(S_WIDTH,new CTextBox("width",$width,5));
		$frmGraph->AddRow(S_HEIGHT,new CTextBox("height",$height,5));

		$cmbYType = new CComboBox("yaxistype",$yaxistype,"submit()");
		$cmbYType->AddItem(GRAPH_YAXIS_TYPE_CALCULATED,S_CALCULATED);
		$cmbYType->AddItem(GRAPH_YAXIS_TYPE_FIXED,S_FIXED);
		$frmGraph->AddRow(S_YAXIS_TYPE,$cmbYType);

		if($yaxistype == GRAPH_YAXIS_TYPE_FIXED)
		{
			$frmGraph->AddRow(S_YAXIS_MIN_VALUE,new CTextBox("yaxismin",$yaxismin,5));
			$frmGraph->AddRow(S_YAXIS_MAX_VALUE,new CTextBox("yaxismax",$yaxismax,5));
		}
		else
		{
			$frmGraph->AddVar("yaxismin",$yaxismin);
			$frmGraph->AddVar("yaxismax",$yaxismax);
		}

		$frmGraph->AddItemToBottomRow(new CButton("register","add"));
		if(isset($_REQUEST["graphid"]))
		{
			$frmGraph->AddItemToBottomRow(SPACE);
			$frmGraph->AddItemToBottomRow(new CButton("register","update"));
			$frmGraph->AddItemToBottomRow(SPACE);
			$frmGraph->AddItemToBottomRow(new CButton("register","delete","return Confirm('".S_DELETE_GRAPH_Q."');"));
		}
		 $frmGraph->Show();

	}
/*
	# Insert escalation form
	function	insert_escalation_form($escalationid)
	{
		if(isset($escalationid))
		{
			$result=DBselect("select * from escalations  where escalationid=$escalationid");

			$row=DBfetch($result);
	
			$name=$row["name"];
			$dflt=$row["dflt"];
		}
		else
		{
			$name="";
			$dflt=0;
		}

		$frmEscal = new CFormTable(S_ESCALATION,"config.php");
		$frmEscal->SetHelp("web.escalations.php");

		$frmEscal->AddVar("config",$_REQUEST["config"]);

		if(isset($escalationid))
		{
			$frmEscal->AddVar("escalationid",$escalationid);
		}

		$frmEscal->AddRow(S_NAME,new CTextBox("name",$name,32));
		$frmEscal->AddRow(S_IS_DEFAULT,new CCheckBox("dflt",$dflt));

		$frmEscal->AddItemToBottomRow(new CButton("register","add escalation"));
		if(isset($escalationid))
		{
			$frmEscal->AddItemToBottomRow(SPACE);
			$frmEscal->AddItemToBottomRow(new CButton("register","update escalation"));
			$frmEscal->AddItemToBottomRow(SPACE);
			$frmEscal->AddItemToBottomRow(new CButton("register","delete escalation",
				"return Confirm('Delete selected escalation?');"));
		}
		$frmEscal->Show();
	}

	# Insert escalation rule form
	function	insert_escalation_rule_form($escalationid,$escalationruleid)
	{
		if(isset($escalationruleid))
		{
			$result=DBselect("select * from escalation_rules  where escalationruleid=$escalationruleid");

			$row=DBfetch($result);
	
			$level=$row["level"];
			$period=$row["period"];
			$delay=$row["delay"];
			$actiontype=$row["actiontype"];
		}
		else
		{
			$level=1;
			$period="1-7,00:00-23:59";
			$delay=0;
			$actiontype=0;
		}

		$frmEacalRul = new CFormTable(S_ESCALATION_RULE,"config.php");
		$frmEacalRul->SetHelp("web.escalationrule.php");
		$frmEacalRul->AddVar("config",$_REQUEST["config"]);
		$frmEacalRul->AddVar("escalationid",$escalationid);
		if(isset($escalationruleid))
		{
			$frmEacalRul->AddVar("calationruleid",$escalationruleid);
		}

		$frmEacalRul->AddRow(S_LEVEL,new CTextBox("level",$level,2));
		$frmEacalRul->AddRow(S_PERIOD,new CTextBox("period",$period,32));
		$frmEacalRul->AddRow(S_DELAY,new CTextBox("delay",$delay,32));

		$cmbAction = new CComboBox("actiontype",$actiontype);
		$cmbAction->AddItem(0,"Do nothing");
		$cmbAction->AddItem(1,"Execute actions");
		$cmbAction->AddItem(2,"Increase severity");
		$cmbAction->AddItem(3,"Increase administrative hierarcy");
		$frmEacalRul->AddRow(S_DO,$cmbAction);

		$frmEacalRul->AddItemToBottomRow(new CButton("register","add rule"));
		if(isset($escalationid))
		{
			$frmEacalRul->AddItemToBottomRow(SPACE);
			$frmEacalRul->AddItemToBottomRow(new CButton("register","update rule"));
			$frmEacalRul->AddItemToBottomRow(SPACE);
			$frmEacalRul->AddItemToBottomRow(new CButton("register","delete rule","return Confirm('Delete selected escalation rule?');"));
		}
		$frmEacalRul->Show();
	}
*/
	# Insert autoregistration form
	function	insert_autoregistration_form()
	{
		$frmAutoReg = new CFormTable(S_AUTOREGISTRATION,"config.php");
		$frmAutoReg->SetHelp("web.autoregistration.php");
		$frmAutoReg->AddVar("config",$_REQUEST["config"]);

		if(isset($_REQUEST["autoregid"]))
		{
			$frmAutoReg->AddVar("autoregid",$_REQUEST["autoregid"]);
			$result	= DBselect("select * from autoreg  where id=".$_REQUEST["autoregid"]);

			$row	= DBfetch($result);
			$pattern= $row["pattern"];

			$frmAutoReg->SetTitle(S_AUTOREGISTRATION." \"".$pattern."\"");
		}
		
		if(isset($_REQUEST["autoregid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$priority	= $row["priority"];
			$hostid		= $row["hostid"];
			$h		= get_host_by_hostid($hostid);
			$host		= $h["host"];
		}
		else
		{
			$pattern	= get_request("pattern", "*");
			$priority	= get_request("priority", 10);
			$hostid		= get_request("hostid", 0);
			$host		= get_request("host", "");
		}

		$col=0;

		$frmAutoReg->AddRow(S_PATTERN,new CTextBox("pattern",$pattern,64));
		$frmAutoReg->AddRow(S_PRIORITY,new CTextBox("priority",$priority,4));
		$frmAutoReg->AddRow(S_HOST,array(
			new CTextBox("host",$host,32,NULL,'yes'),
			new CButton("btn1","Select",
				"return PopUp('popup.php?form=".$frmAutoReg->GetName().
				"&field1=hostid&field2=host','new_win',".
				"'width=450,height=450,resizable=1,scrollbars=1');",
				'T')
			));
		$frmAutoReg->AddVar("hostid",$hostid);
		
		$frmAutoReg->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["autoregid"]))
		{
			$frmAutoReg->AddItemToBottomRow(SPACE);
			$frmAutoReg->AddItemToBottomRow(new CButtonDelete(
				"Delete selected autoregistration rule?",
				url_param("form").url_param("config").url_param("autoregid").
				"&pattern=".$pattern));
		}
		$frmAutoReg->AddItemToBottomRow(SPACE);
		$frmAutoReg->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmAutoReg->Show();
	}

	function	insert_action_form()
	{
		global  $_REQUEST;

		$uid=NULL;

		$frmAction = new CFormTable(S_ACTION,'actionconf.php');
		$frmAction->SetHelp('web.actions.action.php');

		$conditiontype = get_request("conditiontype",0);

		if(isset($_REQUEST["actionid"]))
		{
			$action=get_action_by_actionid($_REQUEST["actionid"]);
			$frmAction->AddVar('actionid',$_REQUEST["actionid"]);
		}
	
		if(isset($_REQUEST["actionid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$actiontype	= $action["actiontype"];
			$source		= $action["source"];
			$delay		= $action["delay"];
			$uid		= $action["userid"];
			$subject	= $action["subject"];
			$message	= $action["message"];
			$recipient	= $action["recipient"];
			$maxrepeats	= $action["maxrepeats"];
			$repeatdelay	= $action["repeatdelay"];

			if(isset($_REQUEST["repeat"]))
			{
				$repeat=$_REQUEST["repeat"];
			}
			else if($maxrepeats==0)
			{
				$repeat=0;
			}
			else
			{
				$repeat=1;
			}
			$result=DBselect("select conditiontype, operator, value from conditions".
				" where actionid=".$_REQUEST["actionid"]." order by conditiontype");
			$i=1;
			while($condition=DBfetch($result))
			{
				$_REQUEST["conditiontype$i"]=$condition["conditiontype"];
				$_REQUEST["conditionop$i"]=$condition["operator"];
				$_REQUEST["conditionvalue$i"]=$condition["value"];
				$i++;
			}
		}
		else
		{
			$source		= get_request("source",0);
			$actiontype	= get_request("actiontype",0);

			$delay		= get_request("delay",30);
			$subject	= get_request("subject","{TRIGGER.NAME}: {STATUS}");
			$message	= get_request("message","{TRIGGER.NAME}: {STATUS}");
			$scope		= get_request("scope",0);
			$recipient	= get_request("recipient",RECIPIENT_TYPE_GROUP);
			$severity	= get_request("severity",0);
			$maxrepeats	= get_request("maxrepeats",0);
			$repeatdelay	= get_request("repeatdelay",600);
			$repeat		= get_request("repeat",0);

			if($recipient==RECIPIENT_TYPE_GROUP)
				$uid = get_request("usrgrpid",NULL);
			else
				$uid = get_request("userid",NULL);
		}


// prepare condition list
		$cond_el=array();
		for($i=1; $i<=1000; $i++)
		{
			if(!isset($_REQUEST["conditiontype$i"])) continue;
			array_push($cond_el, new CCheckBox(
					"conditionchecked$i", 'no',
					get_condition_desc(
						$_REQUEST["conditiontype$i"],
						$_REQUEST["conditionop$i"],
						$_REQUEST["conditionvalue$i"]
					)),BR
				);
			$frmAction->AddVar("conditiontype$i", $_REQUEST["conditiontype$i"]);
			$frmAction->AddVar("conditionop$i", $_REQUEST["conditionop$i"]);
			$frmAction->AddVar("conditionvalue$i", $_REQUEST["conditionvalue$i"]);
		}

		if(count($cond_el)==0)
			array_push($cond_el, S_NO_CONDITIONS_DEFINED);
		else
			array_push($cond_el, new CButton('register','delete selected'));
// end of condition list preparation

		$cmbSource =  new CComboBox('source', $source);
		$cmbSource->AddItem(0, S_TRIGGER);
		$frmAction->AddRow(S_SOURCE, $cmbSource);

		$frmAction->AddRow(S_CONDITIONS, $cond_el); 

// prepare new condition
		$rowCondition=array();

// add condition type
		$cmbCondType = new CComboBox('conditiontype',$conditiontype,'submit()');
		$cmbCondType->AddItem(CONDITION_TYPE_GROUP,		S_HOST_GROUP);
		$cmbCondType->AddItem(CONDITION_TYPE_HOST,		S_HOST);
		$cmbCondType->AddItem(CONDITION_TYPE_TRIGGER,		S_TRIGGER);
		$cmbCondType->AddItem(CONDITION_TYPE_TRIGGER_NAME,	S_TRIGGER_NAME);
		$cmbCondType->AddItem(CONDITION_TYPE_TRIGGER_SEVERITY,	S_TRIGGER_SEVERITY);
		$cmbCondType->AddItem(CONDITION_TYPE_TRIGGER_VALUE,	S_TRIGGER_VALUE);
		$cmbCondType->AddItem(CONDITION_TYPE_TIME_PERIOD,	S_TIME_PERIOD);

		array_push($rowCondition,$cmbCondType);

// add condition operation
		$cmbCondOp = new CComboBox('operator');
		if(in_array($conditiontype, array(
				CONDITION_TYPE_GROUP,
				CONDITION_TYPE_HOST,
				CONDITION_TYPE_TRIGGER,
				CONDITION_TYPE_TRIGGER_SEVERITY,
				CONDITION_TYPE_TRIGGER_VALUE)))
			$cmbCondOp->AddItem(CONDITION_OPERATOR_EQUAL,		'=');
		if(in_array($conditiontype,array(
				CONDITION_TYPE_GROUP,
				CONDITION_TYPE_HOST,
				CONDITION_TYPE_TRIGGER,
				CONDITION_TYPE_TRIGGER_SEVERITY)))
			$cmbCondOp->AddItem(CONDITION_OPERATOR_NOT_EQUAL,	'<>');
		if(in_array($conditiontype,array(CONDITION_TYPE_TRIGGER_NAME)))
			$cmbCondOp->AddItem(CONDITION_OPERATOR_LIKE,		'like');
		if(in_array($conditiontype,array(CONDITION_TYPE_TRIGGER_NAME)))
			$cmbCondOp->AddItem(CONDITION_OPERATOR_NOT_LIKE,	'not like');
		if(in_array($conditiontype,array(CONDITION_TYPE_TIME_PERIOD)))
			$cmbCondOp->AddItem(CONDITION_OPERATOR_IN,		'in');
		if(in_array($conditiontype,array(CONDITION_TYPE_TRIGGER_SEVERITY)))
			$cmbCondOp->AddItem(CONDITION_OPERATOR_MORE_EQUAL,	'>=');

		array_push($rowCondition,$cmbCondOp);


// add condition value
		if($conditiontype == CONDITION_TYPE_GROUP)
		{
			$cmbCondVal = new CComboBox('value');
			$groups = DBselect("select groupid,name from groups order by name");
			while($group = DBfetch($groups))
			{
				$cmbCondVal->AddItem($group["groupid"],$group["name"]);
			}
			array_push($rowCondition,$cmbCondVal);
		}
		else if($conditiontype == CONDITION_TYPE_HOST)
		{
			$frmAction->AddVar('value','0');

			$txtCondVal = new CTextBox('host','',20);
			$txtCondVal->SetReadonly('yes');

			$btnSelect = new CButton('btn1','Select',
				"return PopUp('popup.php?form=".$frmAction->GetName().
				"&field1=value&field2=host','new_win',".
				"'width=450,height=450,resizable=1,scrollbars=1');");
			$btnSelect->SetAccessKey('T');

			array_push($rowCondition, $txtCondVal, $btnSelect);
		}
		else if($conditiontype == CONDITION_TYPE_TRIGGER)
		{
			$cmbCondVal = new CComboBox('value');
			$triggers = DBselect("select distinct h.host,t.triggerid,t.description".
				" from triggers t, functions f,items i, hosts h".
				" where t.triggerid=f.triggerid and f.itemid=i.itemid".
				" and h.hostid=i.hostid order by h.host");
			while($trigger = DBfetch($triggers))
			{
				$cmbCondVal->AddItem($trigger["triggerid"],
					$trigger["host"].":&nbsp;".$trigger["description"]);
			}
			array_push($rowCondition,$cmbCondVal);
		}
		else if($conditiontype == CONDITION_TYPE_TRIGGER_NAME)
		{
			array_push($rowCondition, new CTextBox('value', "", 40));
		}
		else if($conditiontype == CONDITION_TYPE_TRIGGER_VALUE)
		{
			$cmbCondVal = new CComboBox('value');
			$cmbCondVal->AddItem(0,"OFF");
			$cmbCondVal->AddItem(1,"ON");
			array_push($rowCondition,$cmbCondVal);
		}
		else if($conditiontype == CONDITION_TYPE_TIME_PERIOD)
		{
			array_push($rowCondition, new CTextBox('value', "1-7,00:00-23:59", 40));
		}
		else if($conditiontype == CONDITION_TYPE_TRIGGER_SEVERITY)
		{
			$cmbCondVal = new CComboBox('value');
			$cmbCondVal->AddItem(0,S_NOT_CLASSIFIED);
			$cmbCondVal->AddItem(1,S_INFORMATION);
			$cmbCondVal->AddItem(2,S_WARNING);
			$cmbCondVal->AddItem(3,S_AVERAGE);
			$cmbCondVal->AddItem(4,S_HIGH);
			$cmbCondVal->AddItem(5,S_DISASTER);
			array_push($rowCondition,$cmbCondVal);
		}
// add condition button
		array_push($rowCondition,BR,new CButton('register','add condition'));

// end of new condition preparation
		$frmAction->AddRow(S_CONDITION, $rowCondition);

		$cmbActionType = new CComboBox('actiontype', $actiontype,'submit()');
		$cmbActionType->AddItem(0,S_SEND_MESSAGE);
		$cmbActionType->AddItem(1,S_REMOTE_COMMAND,NULL,'no');

		$frmAction->AddRow(S_ACTION_TYPE, $cmbActionType);

		$cmbRecipient = new CComboBox('recipient', $recipient,'submit()');
		$cmbRecipient->AddItem(0,S_SINGLE_USER);
		$cmbRecipient->AddItem(1,S_USER_GROUP);

		$frmAction->AddRow(S_SEND_MESSAGE_TO, $cmbRecipient);

		if($recipient==RECIPIENT_TYPE_GROUP)
		{
			
			$cmbGroups = new CComboBox('usrgrpid', $uid);
	
			$sql="select usrgrpid,name from usrgrp order by name";
			$groups=DBselect($sql);
			while($group=DBfetch($groups))
			{
				$cmbGroups->AddItem($group['usrgrpid'],$group['name']);
			}

			$frmAction->AddRow(S_GROUP, $cmbGroups);
		}
		else
		{
			$cmbUser = new CComboBox('userid', $uid);
			
			$sql="select userid,alias from users order by alias";
			$users=DBselect($sql);
			while($user=DBfetch($users))
			{
				$cmbUser->AddItem($user['userid'],$user['alias']);
			}

			$frmAction->AddRow(S_USER, $cmbUser);
		}

		$frmAction->AddRow(S_DELAY_BETWEEN_MESSAGES_IN_SEC, new CTextBox('delay',$delay,5));
		$frmAction->AddRow(S_SUBJECT, new CTextBox('subject',$subject,80));
		$frmAction->AddRow(S_MESSAGE, new CTextArea('message',$message,77,7));

		$cmbRepeat = new CComboBox('repeat',$repeat,'submit()');
		$cmbRepeat->AddItem(0,S_NO_REPEATS);
		$cmbRepeat->AddItem(1,S_REPEAT);
		$frmAction->AddRow(S_REPEAT, $cmbRepeat);

		if($repeat>0)
		{
			$frmAction->AddRow(S_NUMBER_OF_REPEATS, new CTextBox('maxrepeats',$maxrepeats,2));
			$frmAction->AddRow(S_DELAY_BETWEEN_REPEATS, new CTextBox('repeatdelay',$repeatdelay,2));
		}

		$frmAction->AddItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($_REQUEST["actionid"]))
		{
			$frmAction->AddItemToBottomRow(SPACE);
			$frmAction->AddItemToBottomRow(new CButtonDelete("Delete selected action?",
				url_param("form").url_param("actiontype").url_param("actionid").
				"&subject=".$subject));
				
		} else {
		}
		$frmAction->AddItemToBottomRow(SPACE);
		$frmAction->AddItemToBottomRow(new CButtonCancel(url_param("actiontype")));
	
		$frmAction->Show();
	}

	function	insert_media_type_form()
	{
		$type		= get_request("type",0);
		$description	= get_request("description","");
		$smtp_server	= get_request("smtp_server","localhost");
		$smtp_helo	= get_request("smtp_helo","localhost");
		$smtp_email	= get_request("smtp_email","zabbix@localhost");
		$exec_path	= get_request("exec_path","");

		if(isset($_REQUEST["mediatypeid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$result=DBselect("select mediatypeid,type,description,smtp_server,smtp_helo,smtp_email,exec_path from media_type where mediatypeid=".$_REQUEST["mediatypeid"]);
			$row=DBfetch($result);
			$mediatypeid=$row["mediatypeid"];
			$type=@iif(isset($_REQUEST["type"]),$_REQUEST["type"],$row["type"]);
			$description=$row["description"];
			$smtp_server=$row["smtp_server"];
			$smtp_helo=$row["smtp_helo"];
			$smtp_email=$row["smtp_email"];
			$exec_path=$row["exec_path"];
		}

		$frmMeadia = new CFormTable(S_MEDIA,"config.php");
		$frmMeadia->SetHelp("web.config.medias.php");

		if(isset($_REQUEST["mediatypeid"]))
		{
			$frmMeadia->AddVar("mediatypeid",$_REQUEST["mediatypeid"]);
		}
		$frmMeadia->AddVar("config",1);

		$frmMeadia->AddRow(S_DESCRIPTION,new CTextBox("description",$description,30));
		$cmbType = new CComboBox("type",$type,"submit()");
		$cmbType->AddItem(0,S_EMAIL);
		$cmbType->AddItem(1,S_SCRIPT);
		$frmMeadia->AddRow(S_TYPE,$cmbType);

		if($type==0)
		{
			$frmMeadia->AddVar("exec_path",$exec_path);
			$frmMeadia->AddRow(S_SMTP_SERVER,new CTextBox("smtp_server",$smtp_server,30));
			$frmMeadia->AddRow(S_SMTP_HELO,new CTextBox("smtp_helo",$smtp_helo,30));
			$frmMeadia->AddRow(S_SMTP_EMAIL,new CTextBox("smtp_email",$smtp_email,30));
		}elseif($type==1)
		{
			$frmMeadia->AddVar("smtp_server",$smtp_server);
			$frmMeadia->AddVar("smtp_helo",$smtp_helo);
			$frmMeadia->AddVar("smtp_email",$smtp_email);

			$frmMeadia->AddRow(S_SCRIPT_NAME,new CTextBox("exec_path",$exec_path,50));
		}

		$frmMeadia->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["mediatypeid"]))
		{
			$frmMeadia->AddItemToBottomRow(SPACE);
			$frmMeadia->AddItemToBottomRow(new CButtonDelete(S_DELETE_SELECTED_MEDIA,
				url_param("form").url_param("config").url_param("mediatypeid")));
		}
		$frmMeadia->AddItemToBottomRow(SPACE);
		$frmMeadia->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmMeadia->Show();
	}

	function	insert_image_form()
	{
		$frmImages = new CFormTable(S_IMAGE,"config.php","post","multipart/form-data");
		$frmImages->SetHelp("web.config.images.php");
		$frmImages->AddVar("MAX_FILE_SIZE",(1024*1024));	
		$frmImages->AddVar("config",get_request("config",3));

		if(isset($_REQUEST["imageid"]))
		{
			$result=DBselect("select imageid,imagetype,name,image from images".
				" where imageid=".$_REQUEST["imageid"]);

			$row=DBfetch($result);
			$frmImages->SetTitle(S_IMAGE." \"".$row["name"]."\"");
			$frmImages->AddVar("imageid",$_REQUEST["imageid"]);
		}

		if(isset($_REQUEST["imageid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$name		= $row["name"];
			$imagetype	= $row["imagetype"];
			$imageid	= $row["imageid"];
		}
		else
		{
			$name		= get_request("name","");
			$imagetype	= get_request("imagetype",1);
		}

		$frmImages->AddRow(S_NAME,new CTextBox("name",$name,64));
	
		$cmbImg = new CComboBox("imagetype",$imagetype);
		$cmbImg->AddItem(1,S_ICON);
		$cmbImg->AddItem(2,S_BACKGROUND);
		$frmImages->AddRow(S_TYPE,$cmbImg);
		$frmImages->AddRow(S_UPLOAD,new CFile("image"));

		$frmImages->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["imageid"]))
		{
			$frmImages->AddItemToBottomRow(SPACE);
			$frmImages->AddItemToBottomRow(new CButtonDelete(S_DELETE_SELECTED_IMAGE,
				url_param("form").url_param("config").url_param("imageid")));
		}
		$frmImages->AddItemToBottomRow(SPACE);
		$frmImages->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmImages->Show();
	}

	function insert_screen_form()
	{
		global $_REQUEST;

		$frm_title = S_SCREEN;
		if(isset($_REQUEST["screenid"]))
		{
			$result=DBselect("select screenid,name,cols,rows from screens g".
				" where screenid=".$_REQUEST["screenid"]);
			$row=DBfetch($result);
			$frm_title = S_SCREEN." \"".$row["name"]."\"";
		}
		if(isset($_REQUEST["screenid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$name=$row["name"];
			$cols=$row["cols"];
			$rows=$row["rows"];
		}
		else
		{
			$name=get_request("name","");
			$cols=get_request("cols",1);
			$rows=get_request("rows",1);
		}
		$frmScr = new CFormTable($frm_title,"screenconf.php");
		$frmScr->SetHelp("web.screenconf.screen.php");

		if(isset($_REQUEST["screenid"]))
		{
			$frmScr->AddVar("screenid",$_REQUEST["screenid"]);
		}
		$frmScr->AddRow(S_NAME, new CTextBox("name",$name,32));
		$frmScr->AddRow(S_COLUMNS, new CTextBox("cols",$cols,5));
		$frmScr->AddRow(S_ROWS, new CTextBox("rows",$rows,5));

		$frmScr->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["screenid"]))
		{
			$frmScr->AddItemToBottomRow(SPACE);
			$frmScr->AddItemToBottomRow(new CButtonDelete(S_DELETE_SCREEN_Q,
				url_param("form").url_param("screenid")));
		}
		$frmScr->AddItemToBottomRow(SPACE);
		$frmScr->AddItemToBottomRow(new CButtonCancel());
		$frmScr->Show();	
	}

	function&	get_screen_item_form()
	{
		global $_REQUEST;

		$form = new CFormTable(S_SCREEN_CELL_CONFIGURATION,"screenedit.php");
		$form->SetHelp("web.screenedit.cell.php");

		if(isset($_REQUEST["screenitemid"]))
		{
			$iresult=DBSelect("select * from screens_items".
				" where screenid=".$_REQUEST["screenid"].
				" and screenitemid=".$_REQUEST["screenitemid"]);

			$form->AddVar("screenitemid",$_REQUEST["screenitemid"]);
		} else {
			$form->AddVar("x",$_REQUEST["x"]);
			$form->AddVar("y",$_REQUEST["y"]);
		}

		if(isset($_REQUEST["screenitemid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$irow = DBfetch($iresult);
			$resource	= $irow["resource"];
			$resourceid	= $irow["resourceid"];
			$width		= $irow["width"];
			$height		= $irow["height"];
			$colspan	= $irow["colspan"];
			$rowspan	= $irow["rowspan"];
			$elements	= $irow["elements"];

		}
		else
		{
			$resource	= get_request("resource",	0);
			$resourceid	= get_request("resourceid",	0);
			$width		= get_request("width",		500);
			$height		= get_request("height",		100);
			$colspan	= get_request("colspan",	0);
			$rowspan	= get_request("rowspan",	0);
			$elements	= get_request("elements",	25);
		}


		$form->AddVar("screenid",$_REQUEST["screenid"]);

		$cmbRes = new CCombobox("resource",$resource,"submit()");
		$cmbRes->AddItem(0,S_GRAPH);
		$cmbRes->AddItem(1,S_SIMPLE_GRAPH);
		$cmbRes->AddItem(2,S_MAP);
		$cmbRes->AddItem(3,S_PLAIN_TEXT);
		$form->AddRow(S_RESOURCE,$cmbRes);

		if($resource == 0)
		{
	// User-defined graph
			$result=DBselect("select graphid,name from graphs order by name");

			$cmbGraphs = new CComboBox("resourceid",$resourceid);
			$cmbGraphs->AddItem(0,"(none)");
			while($row=DBfetch($result))
			{
				$cmbGraphs->AddItem($row["graphid"],$row["name"]);
			}

			$form->AddRow(S_GRAPH_NAME,$cmbGraphs);
		}
		elseif($resource == 1)
		{
	// Simple graph
			$result=DBselect("select h.host,i.description,i.itemid,i.key_".
				" from hosts h,items i where h.hostid=i.hostid".
				" and h.status=".HOST_STATUS_MONITORED." and i.status=0".
				" order by h.host,i.description");


			$cmbItems = new CCombobox("resourceid",$resourceid);
			$cmbItems->AddItem(0,"(none)");
			while($row=DBfetch($result))
			{
				$description_=item_description($row["description"],$row["key_"]);
				$cmbItems->AddItem($row["itemid"],$row["host"].": ".$description_);

			}
			$form->AddRow(S_PARAMETER,$cmbItems);
		}
		else if($resource == 2)
		{
	// Map
			$result=DBselect("select sysmapid,name from sysmaps order by name");

			$cmbMaps = new CComboBox("resourceid",$resourceid);
			$cmbMaps->AddItem(0,"(none)");
			while($row=DBfetch($result))
			{
				$cmbMaps->AddItem($row["sysmapid"],$row["name"]);
			}

			$form->AddRow(S_MAP,$cmbMaps);
		}
		else if($resource == 3)
		{
	// Plain text
			$result=DBselect("select h.host,i.description,i.itemid,i.key_".
				" from hosts h,items i where h.hostid=i.hostid".
				" and h.status=".HOST_STATUS_MONITORED." and i.status=0".
				" order by h.host,i.description");

			$cmbHosts = new CComboBox("resourceid",$resourceid);
			$cmbHosts->AddItem(0,"(none)");
			while($row=DBfetch($result))
			{
				$description_=item_description($row["description"],$row["key_"]);
				$cmbHosts->AddItem($row["itemid"],$row["host"].": ".$description_);

			}

			$form->AddRow(S_PARAMETER,$cmbHosts);
			$form->AddRow(S_SHOW_LINES, new CTextBox("elements",$elements,2));
		}
		else
		{
			$form->AddVar("resouceid",$resourceid);
		}

		if($resource!=3)
		{
			$form->AddRow(S_WIDTH,	new CTextBox("width",$width,5));
			$form->AddRow(S_HEIGHT,	new CTextBox("height",$height,5));
		}
		else
		{
			$form->AddVar("width",	$width);
			$form->AddVar("height",	$height);
		}

		$form->AddRow(S_COLUMN_SPAN,	new CTextBox("colspan",$colspan,2));
		$form->AddRow(S_ROW_SPAN,	new CTextBox("rowspan",$rowspan,2));

		$form->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["screenitemid"]))
		{
			$form->AddItemToBottomRow(SPACE);
			$form->AddItemToBottomRow(new CButtonDelete(NULL,
				url_param("form").url_param("screenid").url_param("screenitemid")));
		}
		$form->AddItemToBottomRow(SPACE);
		$form->AddItemToBottomRow(new CButtonCancel(url_param("screenid")));
		return $form;
	}

	function	insert_media_form()
	{
		global $_REQUEST;

		$severity = get_request("severity",array());

		if(isset($_REQUEST["mediaid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$media=get_media_by_mediaid($_REQUEST["mediaid"]);

			$sendto		= $media["sendto"];
			$mediatypeid	= $media["mediatypeid"];
			$active		= $media["active"];
			$period		= $media["period"];

			if($media["severity"] & 1)	array_push($severity,0);
			if($media["severity"] & 2)	array_push($severity,1);
			if($media["severity"] & 4)	array_push($severity,2);
			if($media["severity"] & 8)	array_push($severity,3);
			if($media["severity"] & 16)	array_push($severity,4);
			if($media["severity"] & 32)	array_push($severity,5);
		}
		else
		{
			$sendto		= get_request("sendto","");
			$mediatypeid	= get_request("mediatypeid",0);
			$active		= get_request("active",0);
			$period		= get_request("period","1-7,00:00-23:59");
		}

		$frmMedia = new CFormTable(S_NEW_MEDIA,"media.php");
		$frmMedia->SetHelp("web.media.media.php");

		$frmMedia->AddVar("userid",$_REQUEST["userid"]);
		if(isset($_REQUEST["mediaid"]))
		{
			$frmMedia->AddVar("mediaid",$_REQUEST["mediaid"]);
		}

		$cmbType = new CComboBox("mediatypeid",$mediatypeid);
		$types=DBselect("select mediatypeid,description from media_type order by type");
		while($type=DBfetch($types))
		{
			$cmbType->AddItem($type["mediatypeid"],$type["description"]);
		}
		$frmMedia->AddRow(S_TYPE,$cmbType);

		$frmMedia->AddRow(S_SEND_TO,new CTextBox("sendto",$sendto,20));	
		$frmMedia->AddRow(S_WHEN_ACTIVE,new CTextBox("period",$period,48));	
	

		$label[0] = S_NOT_CLASSIFIED;
		$label[1] = S_INFORMATION;
		$label[2] = S_WARNING;
		$label[3] = S_AVERAGE;
		$label[4] = S_HIGH;
		$label[5] = S_DISASTER;

		$frm_row = array();
		for($i=0; $i<=5; $i++){
			array_push($frm_row, 
				new CCheckBox(
					"severity[]",
					in_array($i,$severity)?'yes':'no', 
					$label[$i],	/* label */
					NULL,		/* action */
					$i),		/* value */
				BR);
		}
		$frmMedia->AddRow(S_USE_IF_SEVERITY,$frm_row);

		$cmbStat = new CComboBox("active",$active);
		$cmbStat->AddItem(0,S_ENABLED);
		$cmbStat->AddItem(1,S_DISABLED);
		$frmMedia->AddRow("Status",$cmbStat);
	
		$frmMedia->AddItemToBottomRow(new CButton("save", S_SAVE));
		if(isset($_REQUEST["mediaid"]))
		{
			$frmMedia->AddItemToBottomRow(SPACE);
			$frmMedia->AddItemToBottomRow(new CButtonDelete(S_DELETE_SELECTED_MEDIA_Q,
				url_param("form").url_param("userid").url_param("mediaid")));
		}
		$frmMedia->AddItemToBottomRow(SPACE);
		$frmMedia->AddItemToBottomRow(new CButtonCancel(url_param("userid")));
		$frmMedia->Show();
	}

	function	insert_housekeeper_form()
	{
		$config=select_config();
		
		$frmHouseKeep = new CFormTable(S_HOUSEKEEPER,"config.php");
		$frmHouseKeep->SetHelp("web.config.housekeeper.php");
		$frmHouseKeep->AddVar("config",get_request("config",0));
		$frmHouseKeep->AddVar("refresh_unsupported",$config["refresh_unsupported"]);
		$frmHouseKeep->AddRow(S_DO_NOT_KEEP_ACTIONS_OLDER_THAN,
			new CTextBox("alert_history",$config["alert_history"],8));
		$frmHouseKeep->AddRow(S_DO_NOT_KEEP_EVENTS_OLDER_THAN,
			new CTextBox("alarm_history",$config["alarm_history"],8));
		$frmHouseKeep->AddItemToBottomRow(new CButton("save",S_SAVE));
		$frmHouseKeep->Show();
	}

	function	insert_other_parameters_form()
	{
		$config=select_config();
		
		$frmHouseKeep = new CFormTable(S_OTHER_PARAMETERS,"config.php");
		$frmHouseKeep->SetHelp("web.config.other.php");
		$frmHouseKeep->AddVar("config",get_request("config",5));
		$frmHouseKeep->AddVar("alert_history",$config["alert_history"]);
		$frmHouseKeep->AddVar("alarm_history",$config["alarm_history"]);
		$frmHouseKeep->AddRow(S_REFRESH_UNSUPPORTED_ITEMS,
			new CTextBox("refresh_unsupported",$config["refresh_unsupported"],8));
		$frmHouseKeep->AddItemToBottomRow(new CButton("save",S_SAVE));
		$frmHouseKeep->Show();
	}

	function	insert_host_form()
	{

		global $_REQUEST;

		$groups= get_request("groups",array());

		$newgroup	= get_request("newgroup","");
		$host_templateid= get_request("host_templateid","");

		$host 	= get_request("host",	"");
		$port 	= get_request("port",	get_profile("HOST_PORT",10050));
		$status	= get_request("status",	HOST_STATUS_MONITORED);
		$useip	= get_request("useip",	"no");
		$ip	= get_request("ip",	"");

		$useprofile = get_request("useprofile","no");

		$devicetype	= get_request("devicetype","");
		$name		= get_request("name","");
		$os		= get_request("os","");
		$serialno	= get_request("serialno","");
		$tag		= get_request("tag","");
		$macaddress	= get_request("macaddress","");
		$hardware	= get_request("hardware","");
		$software	= get_request("software","");
		$contact	= get_request("contact","");
		$location	= get_request("location","");
		$notes		= get_request("notes","");

		if(isset($_REQUEST["hostid"])){
			$db_host=get_host_by_hostid($_REQUEST["hostid"]);
			$frm_title	= S_HOST.SPACE."\"".$db_host["host"]."\"";
		} else 
			$frm_title	= S_HOST;

		if(isset($_REQUEST["hostid"]) && !isset($_REQUEST["form_refresh"]))
		{

			$host	= $db_host["host"];
			$port	= $db_host["port"];
			$status	= $db_host["status"];
			$useip	= $db_host["useip"]==1 ? 'yes' : 'no';
			$ip	= $db_host["ip"];
// add groups
			$db_groups=DBselect("select groupid from hosts_groups where hostid=".$_REQUEST["hostid"]);
			while($db_group=DBfetch($db_groups)){
				if(in_array($db_group["groupid"],$groups)) continue;
				array_push($groups, $db_group["groupid"]);
			}
// read profile
			$db_profiles = DBselect("select * from hosts_profiles where hostid=".$_REQUEST["hostid"]);

			$useprofile = "no";
			if(DBnum_rows($db_profiles)==1)
			{
				$useprofile = "yes";

				$db_profile = DBfetch($db_profiles);

				$devicetype	= $db_profile["devicetype"];
				$name		= $db_profile["name"];
				$os		= $db_profile["os"];
				$serialno	= $db_profile["serialno"];
				$tag		= $db_profile["tag"];
				$macaddress	= $db_profile["macaddress"];
				$hardware	= $db_profile["hardware"];
				$software	= $db_profile["software"];
				$contact	= $db_profile["contact"];
				$location	= $db_profile["location"];
				$notes		= $db_profile["notes"];
			}
		}

		$frmHost = new CFormTable($frm_title,"hosts.php");
		$frmHost->SetHelp("web.hosts.host.php");
		$frmHost->AddVar("config",get_request("config",0));

		if(isset($_REQUEST["hostid"]))		$frmHost->AddVar("hostid",$_REQUEST["hostid"]);
		if(isset($_REQUEST["groupid"]))		$frmHost->AddVar("groupid",$_REQUEST["groupid"]);
		
		$frmHost->AddRow(S_HOST,new CTextBox("host",$host,20));

		$frm_row = array();
		$db_groups=DBselect("select distinct groupid,name from groups order by name");
		while($db_group=DBfetch($db_groups))
		{
			array_push($frm_row,
				new CCheckBox("groups[]",
					in_array($db_group["groupid"],$groups) ? 'yes' : 'no', 
					$db_group["name"],
					NULL,
					$db_group["groupid"]
					),
				BR);
		}
		$frmHost->AddRow(S_GROUPS,$frm_row);

		$frmHost->AddRow(S_NEW_GROUP,new CTextBox("newgroup",$newgroup));

// onChange does not work on some browsers: MacOS, KDE browser
		$frmHost->AddRow(S_USE_IP_ADDRESS,new CCheckBox("useip",$useip,NULL,"submit()"));
		if($useip=="yes")
		{
			$frmHost->AddRow(S_IP_ADDRESS,new CTextBox("ip",$ip,"15"));
		}
		else
		{
			$frmHost->AddVar("ip",$ip);
		}

		$frmHost->AddRow(S_PORT,new CTextBox("port",$port,6));	
		$cmbStatus = new CComboBox("status",$status);
		$cmbStatus->AddItem(HOST_STATUS_MONITORED,	S_MONITORED);
		$cmbStatus->AddItem(HOST_STATUS_TEMPLATE,	S_TEMPLATE);
		$cmbStatus->AddItem(HOST_STATUS_NOT_MONITORED,	S_NOT_MONITORED);
		$frmHost->AddRow(S_STATUS,$cmbStatus);	

		$cmbHosts = new CComboBox("host_templateid",$host_templateid);
		$cmbHosts->AddItem(0,"...");
		$hosts=DBselect("select host,hostid from hosts where status not in (".HOST_STATUS_DELETED.") order by host");
		while($host=DBfetch($hosts))
		{
			$cmbHosts->AddItem($host["hostid"],$host["host"]);
		}
		$frmHost->AddRow(S_LINK_WITH_HOST,$cmbHosts);
	
		$frmHost->AddRow(S_USE_PROFILE,new CCheckBox("useprofile",$useprofile,NULL,"submit()"));
		if($useprofile=="yes")
		{
			$frmHost->AddRow(S_DEVICE_TYPE,new CTextBox("devicetype",$devicetype,61));
			$frmHost->AddRow(S_NAME,new CTextBox("name",$name,61));
			$frmHost->AddRow(S_OS,new CTextBox("os",$os,61));
			$frmHost->AddRow(S_SERIALNO,new CTextBox("serialno",$serialno,61));
			$frmHost->AddRow(S_TAG,new CTextBox("tag",$tag,61));
			$frmHost->AddRow(S_MACADDRESS,new CTextBox("macaddress",$macaddress,61));
			$frmHost->AddRow(S_HARDWARE,new CTextArea("hardware",$hardware,60,4));
			$frmHost->AddRow(S_SOFTWARE,new CTextArea("software",$software,60,4));
			$frmHost->AddRow(S_CONTACT,new CTextArea("contact",$contact,60,4));
			$frmHost->AddRow(S_LOCATION,new CTextArea("location",$location,60,4));
			$frmHost->AddRow(S_NOTES,new CTextArea("notes",$notes,60,4));
		}
		else
		{
			$frmHost->AddVar("devicetype",	$devicetype);
			$frmHost->AddVar("name",	$name);
			$frmHost->AddVar("os",		$os);
			$frmHost->AddVar("serialno",	$serialno);
			$frmHost->AddVar("tag",		$tag);
			$frmHost->AddVar("macaddress",	$macaddress);
			$frmHost->AddVar("hardware",	$hardware);
			$frmHost->AddVar("software",	$software);
			$frmHost->AddVar("contact",	$contact);
			$frmHost->AddVar("location",	$location);
			$frmHost->AddVar("notes",	$notes);
		}

		$frmHost->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["hostid"]))
		{
//			$frmHost->AddItemToBottomRow(SPACE);
//			$frmHost->AddItemToBottomRow(new CButton("register","add items from template"));
			$frmHost->AddItemToBottomRow(SPACE);
			$frmHost->AddItemToBottomRow(
				new CButtonDelete(S_DELETE_SELECTED_HOST_Q,
					url_param("form").url_param("config").url_param("hostid")
				)
			);
		}
		$frmHost->AddItemToBottomRow(SPACE);
		$frmHost->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmHost->Show();
	}

	# Insert form for Host Groups
	function	insert_hostgroups_form()
	{
		global  $_REQUEST;

		$hosts = get_request("hosts",array());
		$frm_title = S_HOST_GROUP;
		if(isset($_REQUEST["groupid"]))
		{
			$group=get_group_by_groupid($_REQUEST["groupid"]);
			$frm_title = S_HOST_GROUP." \"".$group["name"]."\"";
		}
		if(isset($_REQUEST["groupid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$name=$group["name"];
			$db_hosts=DBselect("select distinct h.hostid from hosts h, hosts_groups hg".
				" where h.status<>".HOST_STATUS_DELETED.
				" and h.hostid=hg.hostid".
				" and hg.groupid=".$_REQUEST["groupid"].
				" order by host");
			while($db_host=DBfetch($db_hosts))
			{
				if(in_array($db_host["hostid"],$hosts)) continue;
				array_push($hosts, $db_host["hostid"]);
			}
		}
		else
		{
			$name=get_request("gname","");
		}
		$frmHostG = new CFormTable($frm_title,"hosts.php");
		$frmHostG->SetHelp("web.hosts.group.php");
		$frmHostG->AddVar("config",get_request("config",1));
		if(isset($_REQUEST["groupid"]))
		{
			$frmHostG->AddVar("groupid",$_REQUEST["groupid"]);
		}

		$frmHostG->AddRow(S_GROUP_NAME,new CTextBox("gname",$name,30));

		$cmbHosts = new CListBox("hosts[]",10);
		$db_hosts=DBselect("select distinct hostid,host from hosts".
			" where status<>".HOST_STATUS_DELETED." order by host");
		while($db_host=DBfetch($db_hosts))
		{
			$cmbHosts->AddItem($db_host["hostid"],$db_host["host"],
				in_array($db_host["hostid"],$hosts) ? 'yes' : 'no');
		}
		$frmHostG->AddRow(S_HOSTS,$cmbHosts);

		$frmHostG->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["groupid"]))
		{
			$frmHostG->AddItemToBottomRow(SPACE);
			$frmHostG->AddItemToBottomRow(
				new CButtonDelete("Delete selected group?",
					url_param("form").url_param("config").url_param("groupid")
				)
			);
		}
		$frmHostG->AddItemToBottomRow(SPACE);
		$frmHostG->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmHostG->Show();
	}

	# Insert host profile ReadOnly form
	function	insert_host_profile_form()
	{
		$frmHostP = new CFormTable(S_HOST_PROFILE,"hosts.php");
		$frmHostP->SetHelp("web.host_profile.php");

		$result=DBselect("select * from hosts_profiles where hostid=".$_REQUEST["hostid"]);

		if(DBnum_rows($result)==1)
		{
			$row=DBfetch($result);

			$devicetype=$row["devicetype"];
			$name=$row["name"];
			$os=$row["os"];
			$serialno=$row["serialno"];
			$tag=$row["tag"];
			$macaddress=$row["macaddress"];
			$hardware=$row["hardware"];
			$software=$row["software"];
			$contact=$row["contact"];
			$location=$row["location"];
			$notes=$row["notes"];

			$frmHostP->AddRow(S_DEVICE_TYPE,new CTextBox("devicetype",$devicetype,61,NULL,'yes'));
			$frmHostP->AddRow(S_NAME,new CTextBox("name",$name,61,NULL,'yes'));
			$frmHostP->AddRow(S_OS,new CTextBox("os",$os,61,NULL,'yes'));
			$frmHostP->AddRow(S_SERIALNO,new CTextBox("serialno",$serialno,61,NULL,'yes'));
			$frmHostP->AddRow(S_TAG,new CTextBox("tag",$tag,61,NULL,'yes'));
			$frmHostP->AddRow(S_MACADDRESS,new CTextBox("macaddress",$macaddress,61,NULL,'yes'));
			$frmHostP->AddRow(S_HARDWARE,new CTextArea("hardware",$hardware,60,4,NULL,'yes'));
			$frmHostP->AddRow(S_SOFTWARE,new CTextArea("software",$software,60,4,NULL,'yes'));
			$frmHostP->AddRow(S_CONTACT,new CTextArea("contact",$contact,60,4,NULL,'yes'));
			$frmHostP->AddRow(S_LOCATION,new CTextArea("location",$location,60,4,NULL,'yes'));
			$frmHostP->AddRow(S_NOTES,new CTextArea("notes",$notes,60,4,NULL,'yes'));
		}
		else
		{
			$frmHostP->AddSpanRow("Profile for this host is missing","form_row_c");
		}
		$frmHostP->Show();
	}

	function insert_map_form()
	{
		global $_REQUEST;

		$frm_title = "New system map";

		if(isset($_REQUEST["sysmapid"]))
		{
			$result=DBselect("select * from sysmaps where sysmapid=".$_REQUEST["sysmapid"]);
			$row=DBfetch($result);
			$frm_title = "System map: \"".$row["name"]."\"";
		}
		if(isset($_REQUEST["sysmapid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$name		= $row["name"];
			$width		= $row["width"];
			$height		= $row["height"];
			$background	= $row["background"];
			$label_type	= $row["label_type"];
			$label_location	= $row["label_location"];
		}
		else
		{
			$name		= get_request("name","");
			$width		= get_request("width",800);
			$height		= get_request("height",600);
			$background	= get_request("background","");
			$label_type	= get_request("label_type",0);
			$label_location	= get_request("label_location",0);
		}


		$frmMap = new CFormTable($frm_title,"sysmaps.php");
		$frmMap->SetHelp("web.sysmaps.map.php");

		if(isset($_REQUEST["sysmapid"]))
			$frmMap->AddVar("sysmapid",$_REQUEST["sysmapid"]);

		$frmMap->AddRow(S_NAME,new CTextBox("name",$name,32));
		$frmMap->AddRow(S_WIDTH,new CTextBox("width",$width,5));
		$frmMap->AddRow(S_HEIGHT,new CTextBox("height",$height,5));

		$cmbImg = new CComboBox("background",$background);
		$cmbImg->AddItem('',"No image...");
		$result=DBselect("select name from images where imagetype=2 order by name");
		while($row=DBfetch($result))
			$cmbImg->AddItem($row["name"],$row["name"]);
		$frmMap->AddRow(S_BACKGROUND_IMAGE,$cmbImg);

		$cmbLabel = new CComboBox("label_type",$label_type);
		$cmbLabel->AddItem(0,S_HOST_LABEL);
		$cmbLabel->AddItem(1,S_IP_ADDRESS);
		$cmbLabel->AddItem(2,S_HOST_NAME);
		$cmbLabel->AddItem(3,S_STATUS_ONLY);
		$cmbLabel->AddItem(4,S_NOTHING);
		$frmMap->AddRow(S_ICON_LABEL_TYPE,$cmbLabel);

		$cmbLocation = new CComboBox("label_location",$label_location);

		$cmbLocation->AddItem(0,S_BOTTOM);
		$cmbLocation->AddItem(1,S_LEFT);
		$cmbLocation->AddItem(2,S_RIGHT);
		$cmbLocation->AddItem(3,S_TOP);
		$frmMap->AddRow(S_ICON_LABEL_LOCATION,$cmbLocation);

		$frmMap->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["sysmapid"]))
		{
			$frmMap->AddItemToBottomRow(SPACE);
			$frmMap->AddItemToBottomRow(new CButtonDelete("Delete system map?",
					url_param("form").url_param("sysmapid")));
		}
		$frmMap->AddItemToBottomRow(SPACE);
		$frmMap->AddItemToBottomRow(new CButtonCancel());

		$frmMap->Show();
		
	}

	function insert_map_host_form()
	{
		if(isset($_REQUEST["shostid"]))
		{
			$shost=get_sysmaps_hosts_by_shostid($_REQUEST["shostid"]);

			$hostid	= $shost["hostid"];
			$label	= $shost["label"];
			$x	= $shost["x"];
			$y	= $shost["y"];
			$icon	= $shost["icon"];
			$url	= $shost["url"];
			$icon_on= $shost["icon_on"];
		}
		else
		{
			$hostid	= 0;

			$label	= "";
			$x	= 0;
			$y	= 0;
			$icon	= "";
			$url	= "";
			$icon_on= "";
		}
		if($hostid) 
		{
			$host_info = get_host_by_hostid($hostid);
			$host = $host_info["host"];
		} else {
			$host = "";
		}

		$frmHost = new CFormTable("New host to display","sysmap.php");
		$frmHost->SetHelp("web.sysmap.host.php");
		if(isset($_REQUEST["shostid"]))
		{
			$frmHost->AddVar("shostid",$_REQUEST["shostid"]);
		}
		if(isset($_REQUEST["sysmapid"]))
		{
			$frmHost->AddVar("sysmapid",$_REQUEST["sysmapid"]);
		}

		$frmHost->AddVar("hostid",$hostid);
		$frmHost->AddRow("Host",array(
			new CTextBox("host",$host,32,NULL,'yes'),
			new CButton("btn1","Select","return PopUp('popup.php?form=".$frmHost->GetName().
				"&field1=hostid&field2=host','new_win',".
				"'width=450,height=450,resizable=1,scrollbars=1');","T")
		));

		$cmbIcon = new CComboBox("icon",$icon);
		$result=DBselect("select name from images where imagetype=1 order by name");
		while($row=DBfetch($result))
			$cmbIcon->AddItem($row["name"],$row["name"]);
		$frmHost->AddRow("Icon (OFF)",$cmbIcon);

		$cmbIcon = new CComboBox("icon_on",$icon_on);
		$result=DBselect("select name from images where imagetype=1 order by name");
		while($row=DBfetch($result))
			$cmbIcon->AddItem($row["name"],$row["name"]);
		$frmHost->AddRow("Icon (ON)",$cmbIcon);

		$frmHost->AddRow("Label", new CTextBox("label", $label, 32));

		$frmHost->AddRow("Coordinate X", new CTextBox("x", $x, 5));
		$frmHost->AddRow("Coordinate Y", new CTextBox("y", $y, 5));
		$frmHost->AddRow("URL", new CTextBox("url", $url, 64));

		$frmHost->AddItemToBottomRow(new CButton("register","add"));
		if(isset($_REQUEST["shostid"]))
		{
			$frmHost->AddItemToBottomRow(SPACE);
			$frmHost->AddItemToBottomRow(new CButton("register","update"));
		}
		$frmHost->AddItemToBottomRow(SPACE);
		$frmHost->AddItemToBottomRow(new CButtonCancel(url_param("sysmapid")));

		$frmHost->Show();
	}

	function insert_map_link_form()
	{
		$frmCnct = new CFormTable("New connector","sysmap.php");
		$frmCnct->SetHelp("web.sysmap.connector.php");
		$frmCnct->AddVar("sysmapid",$_REQUEST["sysmapid"]);

		$cmbHosts = new CComboBox("shostid1");

		$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,sh.x,".
			"sh.y,sh.icon from sysmaps_hosts sh,hosts h".
			" where sh.sysmapid=".$_REQUEST["sysmapid"].
			" and h.status not in (".HOST_STATUS_DELETED.") and h.hostid=sh.hostid".
			" order by h.host");

		while($row=DBfetch($result))
		{
			$host=get_host_by_hostid($row["hostid"]);
			$cmbHosts->AddItem($row["shostid"],$host["host"].": ".$row["label"]);
		}
		$frmCnct->AddRow("Host 1",$cmbHosts);

		$cmbHosts->SetName("shostid2");
		$frmCnct->AddRow("Host 2",$cmbHosts);

		$cmbIndic = new CComboBox("triggerid");
		$cmbIndic->AddItem(0,"-");
	        $result=DBselect("select triggerid from triggers order by description");
		while($row=DBfetch($result))
	        {
			$cmbIndic->AddItem($row["triggerid"],expand_trigger_description($row["triggerid"]));
	        }
		$frmCnct->AddRow("Link status indicator",$cmbIndic);

		$cmbType = new CComboBox("drawtype_off");
		$cmbType->AddItem(0,get_drawtype_description(0));
		$cmbType->AddItem(1,get_drawtype_description(1));
		$cmbType->AddItem(2,get_drawtype_description(2));
		$cmbType->AddItem(3,get_drawtype_description(3));
		$cmbType->AddItem(4,get_drawtype_description(4));

		$cmbColor = new CComboBox("color_off");
		$cmbColor->AddItem('Black',"Black");
		$cmbColor->AddItem('Blue',"Blue");
		$cmbColor->AddItem('Cyan',"Cyan");
		$cmbColor->AddItem('Dark Blue',"Dark Blue");
		$cmbColor->AddItem('Dark Green',"Dark Green");
		$cmbColor->AddItem('Dark Red',"Dark Red");
		$cmbColor->AddItem('Dark Yellow',"Dark Yellow");
		$cmbColor->AddItem('Green',"Green");
		$cmbColor->AddItem('Red',"Red");
		$cmbColor->AddItem('White',"White");
		$cmbColor->AddItem('Yellow',"Yellow");

		$frmCnct->AddRow("Type (OFF)",$cmbType);
		$frmCnct->AddRow("Color (OFF)",$cmbColor);

		$cmbType->SetName("drawtype_on");
		$cmbColor->SetName("color_on");

		$frmCnct->AddRow("Type (ON)",$cmbType);
		$frmCnct->AddRow("Color (ON)",$cmbColor);

		$frmCnct->AddItemToBottomRow(new CButton("register","add link"));
		$frmCnct->AddItemToBottomRow(SPACE);
		$frmCnct->AddItemToBottomRow(new CButtonCancel(url_param("sysmapid")));
		
		$frmCnct->Show();
	}
?>
