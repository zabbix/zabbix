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
// TODO !!! Correcr the help links !!! TODO

	include_once 	"include/defines.inc.php";
	include_once 	"include/db.inc.php";

	function	insert_new_message_form()
	{
		global $USER_DETAILS;
		global $_REQUEST;

		$db_acks = get_acknowledges_by_alarmid($_REQUEST["alarmid"]);
		if(!DBfetch($db_acks))
		{
			$title = S_ACKNOWLEDGE_ALARM_BY;
			$btn_txt = S_ACKNOWLEDGE;
		}
		else
		{
			$title = S_ADD_COMMENT_BY;
			$btn_txt = S_SAVE;
		}

		$frmMsg= new CFormTable($title." \"".$USER_DETAILS["alias"]."\"");
		$frmMsg->SetHelp("manual.php");
		$frmMsg->AddVar("alarmid",get_request("alarmid",0));

		$frmMsg->AddRow(S_MESSAGE, new CTextArea("message","",80,6));

		$frmMsg->AddItemToBottomRow(new CButton("save",$btn_txt));

		$frmMsg->Show();

		SetFocus($frmMsg->GetName(),"message");
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
		$frmUser->SetHelp("web.users.php");
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
		$cmbLang->AddItem("pt_br",S_BRAZILIAN_PT);
		$cmbLang->AddItem("cn_zh",S_CHINESE_CN);
		$cmbLang->AddItem("nl_nl",S_DUTCH_NL);
		$cmbLang->AddItem("fr_fr",S_FRENCH_FR);
		$cmbLang->AddItem("de_de",S_GERMAN_DE);
		$cmbLang->AddItem("it_it",S_ITALIAN_IT);
		$cmbLang->AddItem("lv_lv",S_LATVIAN_LV);
		$cmbLang->AddItem("ru_ru",S_RUSSIAN_RU);
		$cmbLang->AddItem("sp_sp",S_SPANISH_SP);
		$cmbLang->AddItem("sv_se",S_SWEDISH_SE);
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
		$frmPerm->SetHelp("web.users.php");

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
		$cmbRes->AddItem("Application","Application");
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
			$usrgrp=get_usergroup_by_groupid($usrgrpid);
			$frm_title = S_USER_GROUP." \"".$usrgrp["name"]."\"";
		}

		$users = get_request("users",array());
		if(isset($usrgrpid) && !isset($_REQUEST["form_refresh"]))
		{
			$name	= $usrgrp["name"];
			$db_users=DBselect("select distinct u.userid,u.alias from users u,users_groups ug ".
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
				array(
					new CCheckBox("users[]",
						in_array($db_user["userid"],$users) ? 'yes' : 'no',
						NULL,			/* action */
						$db_user["userid"]),	/* value */
					$db_user["alias"]
				),
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

		$frmItem = new CFormTable(S_ITEM,"items.php");
		$frmItem->SetHelp("web.items.item.php");

		$frmItem->AddVar("config",get_request("config",0));
		if(isset($_REQUEST["groupid"]))
			$frmItem->AddVar("groupid",$_REQUEST["groupid"]);

		$frmItem->AddVar("hostid",$_REQUEST["hostid"]);

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
		$value_type	= get_request("value_type"	,ITEM_VALUE_TYPE_UINT64);
		$trapper_hosts	= get_request("trapper_hosts"	,"");
		$units		= get_request("units"		,'');
		$valuemapid	= get_request("valuemapid"	,0);
		$multiplier	= get_request("multiplier"	,0);
		$delta		= get_request("delta"		,0);
		$trends		= get_request("trends"		,365);
		$applications	= get_request("applications"	,array());

		$snmpv3_securityname	= get_request("snmpv3_securityname"	,"");
		$snmpv3_securitylevel	= get_request("snmpv3_securitylevel"	,0);
		$snmpv3_authpassphrase	= get_request("snmpv3_authpassphrase"	,"");
		$snmpv3_privpassphrase	= get_request("snmpv3_privpassphrase"	,"");

		$formula	= get_request("formula"		,"1");
		$logtimefmt	= get_request("logtimefmt"	,"");

		$add_groupid	= get_request("add_groupid"	,get_request("groupid",0));


		if(is_null($host)){
			$host_info = get_host_by_hostid($_REQUEST["hostid"]);
			$host = $host_info["host"];
		}

		if(isset($_REQUEST["itemid"]))
		{
			$frmItem->AddVar("itemid",$_REQUEST["itemid"]);

			$result=DBselect("select i.*, h.host, h.hostid".
				" from items i,hosts h where i.itemid=".$_REQUEST["itemid"].
				" and h.hostid=i.hostid");
			$row=DBfetch($result);
		}

		if(isset($_REQUEST["itemid"]) && !isset($_REQUEST["form_refresh"]))
		{
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
			$valuemapid	= $row["valuemapid"];
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

			$db_applications = get_applications_by_itemid($_REQUEST["itemid"]);
			while($db_app = DBfetch($db_applications))
			{
				if(in_array($db_app["applicationid"],$applications))	continue;
				array_push($applications,$db_app["applicationid"]);
			}
			
		}
		if(count($applications)==0)  array_push($applications,0);

		if(isset($_REQUEST["itemid"])) {
			$frmItem->SetTitle(S_ITEM." '$host:".$row["description"]."'");
		} else {
			$frmItem->SetTitle(S_ITEM." '$host:$description'");
		}

		$frmItem->AddRow(S_DESCRIPTION, new CTextBox("description",$description,40));


		$cmbType = new CComboBox("type",$type,"submit()");
		$cmbType->AddItem(ITEM_TYPE_ZABBIX,S_ZABBIX_AGENT);
		$cmbType->AddItem(ITEM_TYPE_ZABBIX_ACTIVE,S_ZABBIX_AGENT_ACTIVE);
		$cmbType->AddItem(ITEM_TYPE_SIMPLE,S_SIMPLE_CHECK);
		$cmbType->AddItem(ITEM_TYPE_SNMPV1,S_SNMPV1_AGENT);
		$cmbType->AddItem(ITEM_TYPE_SNMPV2C,S_SNMPV2_AGENT);
		$cmbType->AddItem(ITEM_TYPE_SNMPV3,S_SNMPV3_AGENT);
		$cmbType->AddItem(ITEM_TYPE_TRAPPER,S_ZABBIX_TRAPPER);
		$cmbType->AddItem(ITEM_TYPE_INTERNAL,S_ZABBIX_INTERNAL);
		$cmbType->AddItem(ITEM_TYPE_AGGREGATE,S_ZABBIX_AGGREGATE);
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

		$btnSelect = new CButton('btn1',S_SELECT,
			"return PopUp('popup.php?dstfrm=".$frmItem->GetName().
			"&dstfld1=key&srctbl=help_items&srcfld1=key_','new_win',".
			"'width=650,height=450,resizable=1,scrollbars=1');");
		$btnSelect->SetAccessKey('T');

		$frmItem->AddRow(S_KEY, array(new CTextBox("key",$key,40), $btnSelect));

		$cmbValType = new CComboBox("value_type",$value_type,"submit()");
		$cmbValType->AddItem(ITEM_VALUE_TYPE_UINT64, S_NUMERIC_UINT64);
		$cmbValType->AddItem(ITEM_VALUE_TYPE_FLOAT, S_NUMERIC_FLOAT);
		$cmbValType->AddItem(ITEM_VALUE_TYPE_STR, S_CHARACTER);
		$cmbValType->AddItem(ITEM_VALUE_TYPE_LOG, S_LOG);
		$cmbValType->AddItem(ITEM_VALUE_TYPE_TEXT, S_TEXT);
		$frmItem->AddRow(S_TYPE_OF_INFORMATION,$cmbValType);

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

		$frmItem->AddRow(S_KEEP_HISTORY_IN_DAYS, array(
			new CTextBox("history",$history,8),
			(!isset($_REQUEST["itemid"])) ? NULL :
				new CButton("del_history",
					"Clean history",
					"return Confirm('History cleaning can take a long time. Continue?');")
			));
		$frmItem->AddRow(S_KEEP_TRENDS_IN_DAYS, new CTextBox("trends",$trends,8));

		$cmbStatus = new CComboBox("status",$status);
		$cmbStatus->AddItem(ITEM_STATUS_ACTIVE,S_MONITORED);
		$cmbStatus->AddItem(ITEM_STATUS_DISABLED,S_DISABLED);
#		$cmbStatus->AddItem(2,"Trapper");
		$cmbStatus->AddItem(ITEM_STATUS_NOTSUPPORTED,S_NOT_SUPPORTED);
		$frmItem->AddRow(S_STATUS,$cmbStatus);

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
		
		if(($value_type==ITEM_VALUE_TYPE_UINT64) || ($value_type == ITEM_VALUE_TYPE_STR))
		{
			$cmbMap = new CComboBox("valuemapid",$valuemapid);
			$cmbMap->AddItem(0,S_AS_IS);
			$db_valuemaps = DBselect("select * from valuemaps");
			while($db_valuemap = DBfetch($db_valuemaps))
				$cmbMap->AddItem($db_valuemap["valuemapid"],$db_valuemap["name"]);

			$link = new CLink("throw map","config.php?config=6","action");
			$link->AddOption("target","_blank");
			$frmItem->AddRow(array(S_SHOW_VALUE.SPACE,$link),$cmbMap);
			
		}
		else
		{
			$frmItem->AddVar("valuemapid",0);
		}

		if($type==2)
		{
			$frmItem->AddRow(S_ALLOWED_HOSTS, new CTextBox("trapper_hosts",$trapper_hosts,40));
		}
		else
		{
			$frmItem->AddVar("trapper_hosts",$trapper_hosts);
		}

		$cmbApps = new CListBox("applications[]",$applications,6);
		$cmbApps->AddItem(0,"-".S_NONE."-");
                $db_applications = DBselect("select distinct applicationid,name from applications".
                        " where hostid=".$_REQUEST["hostid"]." order by name");
                while($db_app = DBfetch($db_applications))
                {
                        $cmbApps->AddItem($db_app["applicationid"],$db_app["name"]);
                }
                $frmItem->AddRow(S_APPLICATIONS,$cmbApps);

		$frmRow = array(new CButton("save",S_SAVE));
		if(isset($_REQUEST["itemid"]))
		{
			array_push($frmRow,
				SPACE,
				new CButtonDelete("Delete selected item?",
					url_param("form").url_param("groupid").url_param("hostid").url_param("config").
					url_param("itemid"))
			);
		}
		array_push($frmRow,
			SPACE,
			new CButtonCancel(url_param("groupid").url_param("hostid").url_param("config")));

		$frmItem->AddSpanRow($frmRow,"form_row_last");

	        $cmbGroups = new CComboBox("add_groupid",$add_groupid);		

	        $groups=DBselect("select groupid,name from groups order by name");
	        while($group=DBfetch($groups))
	        {
// Check if at least one host with read permission exists for this group
	                $hosts=DBselect("select distinct h.hostid,h.host from hosts h,hosts_groups hg".
				" where hg.groupid=".$group["groupid"]." and hg.hostid=h.hostid".
				" and h.status<>".HOST_STATUS_DELETED." order by h.host");
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

	# Insert form for Trigger
	function	insert_trigger_form()
	{
		$frmTrig = new CFormTable(S_TRIGGER,"triggers.php");
		$frmTrig->SetHelp("config_triggers.php");

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
				array(
					new CCheckBox("rem_dependence[]", 'no', NULL, strval($val)),
					expand_trigger_description($val)
				),
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

		$cmbPrior = new CComboBox("priority",$priority);
		$cmbPrior->AddItem(0,"Not classified");
		$cmbPrior->AddItem(1,"Information");
		$cmbPrior->AddItem(2,"Warning");
		$cmbPrior->AddItem(3,"Average");
		$cmbPrior->AddItem(4,"High");
		$cmbPrior->AddItem(5,"Disaster");
		$frmTrig->AddRow(S_SEVERITY,$cmbPrior);

		$frmTrig->AddRow(S_COMMENTS,new CTextArea("comments",$comments,70,7));
		$frmTrig->AddRow(S_URL,new CTextBox("url",$url,70));
		$frmTrig->AddRow(S_DISABLED,new CCheckBox("status",$status));
 
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

	function insert_trigger_comment_form($triggerid)
	{
		$trigger=get_trigger_by_triggerid($triggerid);
		$comments=stripslashes($trigger["comments"]);

		$frmComent = new CFormTable(S_COMMENTS." for \"".expand_trigger_description_simple($triggerid)."\"");
		$frmComent->SetHelp("web.tr_comments.comments.php");
		$frmComent->AddVar("triggerid",$triggerid);
		$frmComent->AddRow(S_COMMENTS,new CTextArea("comments",$comments,100,25));
		$frmComent->AddItemToBottomRow(new CButton("register","update"));

		$frmComent->Show();
	}

	function	insert_graph_form()
	{
		global  $_REQUEST;

		$frmGraph = new CFormTable(S_GRAPH,"graphs.php");
		$frmGraph->SetHelp("web.graphs.graph.php");

		if(isset($_REQUEST["graphid"]))
		{
			$frmGraph->AddVar("graphid",$_REQUEST["graphid"]);

			$result=DBselect("select * from graphs where graphid=".$_REQUEST["graphid"]);
			$row=DBfetch($result);
			$frmGraph->SetTitle(S_GRAPH." \"".$row["name"]."\"");
		}

		if(isset($_REQUEST["graphid"])&&!isset($_REQUEST["name"]))
		{
			$name		=$row["name"];
			$width		=$row["width"];
			$height		=$row["height"];
			$yaxistype	=$row["yaxistype"];
			$yaxismin	=$row["yaxismin"];
			$yaxismax	=$row["yaxismax"];
			$showworkperiod = $row["show_work_period"];
			$showtriggers	= $row["show_triggers"];
		} else {
			$name		=get_request("name"	,"");
			$width		=get_request("width"	,900);
			$height		=get_request("height"	,200);
			$yaxistype	=get_request("yaxistype",GRAPH_YAXIS_TYPE_CALCULATED);
			$yaxismin	=get_request("yaxismin"	,0.00);
			$yaxismax	=get_request("yaxismax"	,100.00);
			$showworkperiod = get_request("showworkperiod",1);
			$showtriggers	= get_request("showtriggers",1);
		}
	
		$frmGraph->AddRow(S_NAME,new CTextBox("name",$name,32));
		$frmGraph->AddRow(S_WIDTH,new CTextBox("width",$width,5));
		$frmGraph->AddRow(S_HEIGHT,new CTextBox("height",$height,5));
		$frmGraph->AddRow(S_SHOW_WORKING_TIME,new CCheckBox("showworkperiod",$showworkperiod,NULL,1));
		$frmGraph->AddRow(S_SHOW_TRIGGERS,new CCheckBox("showtriggers",$showtriggers,NULL,1));

		$cmbYType = new CComboBox("yaxistype",$yaxistype,"submit()");
		$cmbYType->AddItem(GRAPH_YAXIS_TYPE_CALCULATED,S_CALCULATED);
		$cmbYType->AddItem(GRAPH_YAXIS_TYPE_FIXED,S_FIXED);
		$frmGraph->AddRow(S_YAXIS_TYPE,$cmbYType);

		if($yaxistype == GRAPH_YAXIS_TYPE_FIXED)
		{
			$frmGraph->AddRow(S_YAXIS_MIN_VALUE,new CTextBox("yaxismin",$yaxismin,9));
			$frmGraph->AddRow(S_YAXIS_MAX_VALUE,new CTextBox("yaxismax",$yaxismax,9));
		}
		else
		{
			$frmGraph->AddVar("yaxismin",$yaxismin);
			$frmGraph->AddVar("yaxismax",$yaxismax);
		}

		$frmGraph->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["graphid"]))
		{
			$frmGraph->AddItemToBottomRow(SPACE);
			$frmGraph->AddItemToBottomRow(new CButtonDelete(S_DELETE_GRAPH_Q,url_param("graphid").
				url_param("groupid").url_param("hostid")));
		}
		$frmGraph->AddItemToBottomRow(SPACE);
		$frmGraph->AddItemToBottomRow(new CButtonCancel(url_param("groupid").url_param("hostid")));

		$frmGraph->Show();

	}

	function	insert_graphitem_form()
	{
		$frmGItem = new CFormTable(S_NEW_ITEM_FOR_THE_GRAPH,"graph.php");
		$frmGItem->SetHelp("web.graph.item.php");
		

		$db_hosts = get_hosts_by_graphid($_REQUEST["graphid"]);
		$db_host = DBfetch($db_hosts);
		if(!$db_host)
		{
			// empty graph, can contain any item
			$host_condition = " and h.status in(".HOST_STATUS_MONITORED.",".HOST_STATUS_TEMPLATE.")";
		}
		else
		{
			if($db_host["status"]==HOST_STATUS_TEMPLATE)
			{// graph for template must use only one host
				$host_condition = " and h.hostid=".$db_host["hostid"];
			}
			else
			{
				$host_condition = " and h.status in(".HOST_STATUS_MONITORED.")";
			}
		}

		if(isset($_REQUEST["gitemid"]))
		{
			$result=DBselect("select itemid,color,drawtype,sortorder,yaxisside,calc_fnc,type,periods_cnt from graphs_items".
				" where gitemid=".$_REQUEST["gitemid"]);
			$row=DBfetch($result);

		}

		if(isset($_REQUEST["gitemid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$itemid		= $row["itemid"];
			$color		= $row["color"];
			$drawtype	= $row["drawtype"];
			$sortorder	= $row["sortorder"];
			$yaxisside	= $row["yaxisside"];
			$calc_fnc	= $row["calc_fnc"];
			$type		= $row["type"];
			$periods_cnt	= $row["periods_cnt"];
		}
		else
		{
			$itemid		= get_request("itemid", 	0);
			$color		= get_request("color", 		0);
			$drawtype	= get_request("drawtype",	0);
			$sortorder	= get_request("sortorder",	0);
			$yaxisside	= get_request("yaxisside",	1);
			$calc_fnc	= get_request("calc_fnc",	2);
			$type	= get_request("type",	0);
			$periods_cnt	= get_request("periods_cnt",	5);
		}


		$frmGItem->AddVar("graphid",$_REQUEST["graphid"]);
		if(isset($_REQUEST["gitemid"]))
		{
			$frmGItem->AddVar("gitemid",$_REQUEST["gitemid"]);
		}

		$cmbItems = new CComboBox("itemid", $itemid);
		$result=DBselect("select h.host,i.description,i.itemid,i.key_ from hosts h,items i".
			" where h.hostid=i.hostid".
			$host_condition.
			" and i.status=".ITEM_STATUS_ACTIVE." order by h.host,i.description");
		while($row=DBfetch($result))
		{
			$cmbItems->AddItem($row["itemid"],
				$row["host"].":".SPACE.item_description($row["description"],$row["key_"]));
		}
		$frmGItem->AddRow(S_PARAMETER, $cmbItems);

		$cmbType = new CComboBox("type",$type,"submit()");
		$cmbType->AddItem(GRAPH_ITEM_SIMPLE, S_SIMPLE);
		$cmbType->AddItem(GRAPH_ITEM_AGGREGATED, S_AGGREGATED);
		$frmGItem->AddRow(S_TYPE, $cmbType);

		if($type == GRAPH_ITEM_AGGREGATED)
		{
			$frmGItem->AddRow(S_AGGREGATED_PERIODS_COUNT,	new CTextBox("periods_cnt",$periods_cnt,15)); 

			$frmGItem->AddVar("calc_fnc",$calc_fnc);
			$frmGItem->AddVar("drawtype",$drawtype);
			$frmGItem->AddVar("color",$color);
		}
		else
		{
			$frmGItem->AddVar("periods_cnt",$periods_cnt);

			$cmbFnc = new CComboBox("calc_fnc",$calc_fnc);
			$cmbFnc->AddItem(CALC_FNC_ALL, S_ALL_SMALL);
			$cmbFnc->AddItem(CALC_FNC_MIN, S_MIN_SMALL);
			$cmbFnc->AddItem(CALC_FNC_AVG, S_AVG_SMALL);
			$cmbFnc->AddItem(CALC_FNC_MAX, S_MAX_SMALL);
			$frmGItem->AddRow(S_FUNCTION, $cmbFnc);

			$cmbType = new CComboBox("drawtype",$drawtype);
			$cmbType->AddItem(0,get_drawtype_description(0));
			$cmbType->AddItem(1,get_drawtype_description(1));
			$cmbType->AddItem(2,get_drawtype_description(2));
			$cmbType->AddItem(3,get_drawtype_description(3));
			$frmGItem->AddRow(S_DRAW_STYLE, $cmbType);

			$cmbColor = new CComboBox("color",$color);
			$cmbColor->AddItem("Black",		S_BLACK);
			$cmbColor->AddItem("Blue",		S_BLUE);
			$cmbColor->AddItem("Cyan",		S_CYAN);
			$cmbColor->AddItem("Dark Blue",		S_DARK_BLUE);
			$cmbColor->AddItem("Dark Green",	S_DARK_GREEN);
			$cmbColor->AddItem("Dark Red",		S_DARK_RED);
			$cmbColor->AddItem("Dark Yellow",	S_DARK_YELLOW);
			$cmbColor->AddItem("Green",		S_GREEN);
			$cmbColor->AddItem("Red",		S_RED);
			$cmbColor->AddItem("White",		S_WHITE);
			$cmbColor->AddItem("Yellow",		S_YELLOW);
			$frmGItem->AddRow(S_COLOR, $cmbColor);
		}

		$cmbYax = new CComboBox("yaxisside",$yaxisside);
		$cmbYax->AddItem(GRAPH_YAXIS_SIDE_RIGHT, S_RIGHT);
		$cmbYax->AddItem(GRAPH_YAXIS_SIDE_LEFT,	S_LEFT);
		$frmGItem->AddRow(S_YAXIS_SIDE, $cmbYax);

		$frmGItem->AddRow(S_SORT_ORDER_1_100, new CTextBox("sortorder",$sortorder,3));

		$frmGItem->AddItemToBottomRow(new CButton("save",S_SAVE));
		$frmGItem->AddItemToBottomRow(SPACE);
		if(isset($itemid))
		{
			$frmGItem->AddItemToBottomRow(new CButtonDelete("Delete graph element?",
				url_param("gitemid").url_param("graphid")));
			$frmGItem->AddItemToBottomRow(SPACE);
		}
		$frmGItem->AddItemToBottomRow(new CButtonCancel(url_param("graphid")));
		$frmGItem->Show();
	}

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
			new CTextBox("host",$host,32,'yes'),
			new CButton("btn1",S_SELECT,
				"return PopUp('popup.php?dstfrm=".$frmAutoReg->GetName().
				"&dstfld1=hostid&dstfld2=host&srctbl=hosts&srcfld1=hostid&srcfld2=host','new_win',".
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

	function	insert_value_mapping_form()
	{
		$frmValmap = new CFormTable(S_VALUE_MAP);
		$frmValmap->SetHelp("web.mapping.php");
		$frmValmap->AddVar("config",get_request("config",6));

		if(isset($_REQUEST["valuemapid"]))
		{
			$frmValmap->AddVar("valuemapid",$_REQUEST["valuemapid"]);
			$db_valuemaps = DBselect("select * from valuemaps".
				" where valuemapid=".$_REQUEST["valuemapid"]);

			$db_valuemap = DBfetch($db_valuemaps);

			$frmValmap->SetTitle(S_VALUE_MAP." \"".$db_valuemap["name"]."\"");
		}

		if(isset($_REQUEST["valuemapid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$valuemap = array();
			$mapname = $db_valuemap["name"];
			$mappings = DBselect("select * from mappings where valuemapid=".$_REQUEST["valuemapid"]);
			while($mapping = DBfetch($mappings))
			{
				$value = array(
					"value" => $mapping["value"],
					"newvalue" => $mapping["newvalue"]);

				array_push($valuemap, $value);
			}				
		}
		else
		{
			$mapname = get_request("mapname","");
			$valuemap = get_request("valuemap",array());
		}

		$frmValmap->AddRow(S_NAME, new CTextBox("mapname",$mapname,40));

		$i = 0;
		$valuemap_el = array();
		foreach($valuemap as $value)
		{
			array_push($valuemap_el,
				array(
					new CCheckBox("rem_value[]", 'no', NULL, $i),
					$value["value"].SPACE.RARR.SPACE.$value["newvalue"]
				),
				BR);
			$frmValmap->AddVar("valuemap[$i][value]",$value["value"]);
			$frmValmap->AddVar("valuemap[$i][newvalue]",$value["newvalue"]);
			$i++;
		}
		if(count($valuemap_el)==0)
			array_push($valuemap_el, S_NO_MAPPING_DEFINED);
		else
			array_push($valuemap_el, new CButton('del_map','delete selected'));

		$frmValmap->AddRow(S_MAPPING, $valuemap_el);
		$frmValmap->AddRow(S_NEW_MAPPING, array(
			new CTextBox("add_value","",10),
			new CSpan(RARR,"rarr"),
			new CTextBox("add_newvalue","",10),
			SPACE,
			new CButton("add_map",S_ADD)
			));

		$frmValmap->AddItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($_REQUEST["valuemapid"]))
		{
			$frmValmap->AddItemToBottomRow(SPACE);
			$frmValmap->AddItemToBottomRow(new CButtonDelete("Delete selected value mapping?",
				url_param("form").url_param("valuemapid").url_param("config")));
				
		} else {
		}
		$frmValmap->AddItemToBottomRow(SPACE);
		$frmValmap->AddItemToBottomRow(new CButtonCancel(url_param("config")));
	
		$frmValmap->Show();
	}

	function	insert_action_form()
	{
		global  $_REQUEST;

		$uid=NULL;

		$frmAction = new CFormTable(S_ACTION,'actionconf.php','post');
		$frmAction->SetHelp('web.actions.action.php');

		$conditions = get_request("conditions",array());

		$new_condition_type	= get_request("new_condition_type", 0);
		$new_condition_operator	= get_request("new_condition_operator", 0);
		$new_condition_value	= get_request("new_condition_value", 0);

		if(isset($_REQUEST["actionid"]))
		{
			$action=get_action_by_actionid($_REQUEST["actionid"]);
			$frmAction->AddVar('actionid',$_REQUEST["actionid"]);
		}
	
		if(isset($_REQUEST["actionid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$actiontype	= $action["actiontype"];
			$source		= $action["source"];
			$uid		= $action["userid"];
			$subject	= $action["subject"];
			$message	= $action["message"];
			$recipient	= $action["recipient"];
			$maxrepeats	= $action["maxrepeats"];
			$repeatdelay	= $action["repeatdelay"];
			$status 	= $action["status"];
			$scripts 	= $action["scripts"];

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

			while($condition=DBfetch($result))
			{
				$condition = array(
					"type" =>		$condition["conditiontype"],
					"operator" =>		$condition["operator"],
					"value" =>		$condition["value"]);

				if(in_array($condition, $conditions)) continue;
				array_push($conditions, $condition);
			}
		}
		else
		{
			$source		= get_request("source",0);
			$actiontype	= get_request("actiontype",ACTION_TYPE_MESSAGE);

			$subject	= get_request("subject","{TRIGGER.NAME}: {STATUS}");
			$message	= get_request("message","{TRIGGER.NAME}: {STATUS}");
			$scope		= get_request("scope",0);
			$recipient	= get_request("recipient",RECIPIENT_TYPE_GROUP);
			$severity	= get_request("severity",0);
			$maxrepeats	= get_request("maxrepeats",0);
			$repeatdelay	= get_request("repeatdelay",600);
			$repeat		= get_request("repeat",0);
			$status		= get_request("status",ACTION_STATUS_ENABLED);
			$uid 		= get_request("userid",0);
			$scripts	= get_request("scripts","");

		}

		$cmbActionType = new CComboBox('actiontype', $actiontype,'submit()');
		$cmbActionType->AddItem(ACTION_TYPE_MESSAGE,S_SEND_MESSAGE);
		$cmbActionType->AddItem(ACTION_TYPE_COMMAND,S_REMOTE_COMMAND);
		$frmAction->AddRow(S_ACTION_TYPE, $cmbActionType);


// prepare condition list
		$cond_el=array();
		$i=0;
		foreach($conditions as $val)
		{
			array_push($cond_el, 
				array(
					new CCheckBox("rem_condition[]", 'no', NULL,$i),
					get_condition_desc(
						$val["type"],
						$val["operator"],
						$val["value"]
					)
				),
				BR);
			$frmAction->AddVar("conditions[$i][type]", 	$val["type"]);
			$frmAction->AddVar("conditions[$i][operator]", 	$val["operator"]);
			$frmAction->AddVar("conditions[$i][value]", 	$val["value"]);
			$i++;
		}

		if(count($cond_el)==0)
			array_push($cond_el, S_NO_CONDITIONS_DEFINED);
		else
			array_push($cond_el, new CButton('del_condition','delete selected'));
// end of condition list preparation

		$cmbSource =  new CComboBox('source', $source);
		$cmbSource->AddItem(0, S_TRIGGER);
		$frmAction->AddRow(S_SOURCE, $cmbSource);

		$frmAction->AddRow(S_CONDITIONS, $cond_el); 

// prepare new condition
		$rowCondition=array();

// add condition type
		$cmbCondType = new CComboBox('new_condition_type',$new_condition_type,'submit()');
		$cmbCondType->AddItem(CONDITION_TYPE_GROUP,		S_HOST_GROUP);
		$cmbCondType->AddItem(CONDITION_TYPE_HOST,		S_HOST);
		$cmbCondType->AddItem(CONDITION_TYPE_TRIGGER,		S_TRIGGER);
		$cmbCondType->AddItem(CONDITION_TYPE_TRIGGER_NAME,	S_TRIGGER_NAME);
		$cmbCondType->AddItem(CONDITION_TYPE_TRIGGER_SEVERITY,	S_TRIGGER_SEVERITY);
		$cmbCondType->AddItem(CONDITION_TYPE_TRIGGER_VALUE,	S_TRIGGER_VALUE);
		$cmbCondType->AddItem(CONDITION_TYPE_TIME_PERIOD,	S_TIME_PERIOD);

		array_push($rowCondition,$cmbCondType);

// add condition operation
		$cmbCondOp = new CComboBox('new_condition_operator');
		if(in_array($new_condition_type, array(
				CONDITION_TYPE_GROUP,
				CONDITION_TYPE_HOST,
				CONDITION_TYPE_TRIGGER,
				CONDITION_TYPE_TRIGGER_SEVERITY,
				CONDITION_TYPE_TRIGGER_VALUE)))
			$cmbCondOp->AddItem(CONDITION_OPERATOR_EQUAL,		'=');
		if(in_array($new_condition_type,array(
				CONDITION_TYPE_GROUP,
				CONDITION_TYPE_HOST,
				CONDITION_TYPE_TRIGGER,
				CONDITION_TYPE_TRIGGER_SEVERITY)))
			$cmbCondOp->AddItem(CONDITION_OPERATOR_NOT_EQUAL,	'<>');
		if(in_array($new_condition_type,array(CONDITION_TYPE_TRIGGER_NAME)))
			$cmbCondOp->AddItem(CONDITION_OPERATOR_LIKE,		'like');
		if(in_array($new_condition_type,array(CONDITION_TYPE_TRIGGER_NAME)))
			$cmbCondOp->AddItem(CONDITION_OPERATOR_NOT_LIKE,	'not like');
		if(in_array($new_condition_type,array(CONDITION_TYPE_TIME_PERIOD)))
			$cmbCondOp->AddItem(CONDITION_OPERATOR_IN,		'in');
		if(in_array($new_condition_type,array(CONDITION_TYPE_TRIGGER_SEVERITY)))
			$cmbCondOp->AddItem(CONDITION_OPERATOR_MORE_EQUAL,	'>=');
		if(in_array($new_condition_type,array(CONDITION_TYPE_TRIGGER_SEVERITY)))
			$cmbCondOp->AddItem(CONDITION_OPERATOR_LESS_EQUAL,	'<=');

		array_push($rowCondition,$cmbCondOp);


// add condition value
		if($new_condition_type == CONDITION_TYPE_GROUP)
		{
			$cmbCondVal = new CComboBox('new_condition_value');
			$groups = DBselect("select groupid,name from groups order by name");
			while($group = DBfetch($groups))
			{
				$cmbCondVal->AddItem($group["groupid"],$group["name"]);
			}
			array_push($rowCondition,$cmbCondVal);
		}
		else if($new_condition_type == CONDITION_TYPE_HOST)
		{
			$frmAction->AddVar('new_condition_value','0');

			$txtCondVal = new CTextBox('host','',20);
			$txtCondVal->SetReadonly('yes');

			$btnSelect = new CButton('btn1',S_SELECT,
				"return PopUp('popup.php?dstfrm=".$frmAction->GetName().
				"&dstfld1=new_condition_value&dstfld2=host&srctbl=hosts&srcfld1=hostid&srcfld2=host','new_win',".
				"'width=450,height=450,resizable=1,scrollbars=1');");
			$btnSelect->SetAccessKey('T');

			array_push($rowCondition, $txtCondVal, $btnSelect);
		}
		else if($new_condition_type == CONDITION_TYPE_TRIGGER)
		{
			$frmAction->AddVar('new_condition_value','0');

			$txtCondVal = new CTextBox('trigger','',20);
			$txtCondVal->SetReadonly('yes');

			$btnSelect = new CButton('btn1',S_SELECT,
				"return PopUp('popup.php?dstfrm=".$frmAction->GetName().
				"&dstfld1=new_condition_value&dstfld2=trigger&srctbl=triggers&srcfld1=triggerid&srcfld2=description','new_win',".
				"'width=600,height=450,resizable=1,scrollbars=1');");
			$btnSelect->SetAccessKey('T');
			array_push($rowCondition, $txtCondVal, $btnSelect);
		}
		else if($new_condition_type == CONDITION_TYPE_TRIGGER_NAME)
		{
			array_push($rowCondition, new CTextBox('new_condition_value', "", 40));
		}
		else if($new_condition_type == CONDITION_TYPE_TRIGGER_VALUE)
		{
			$cmbCondVal = new CComboBox('new_condition_value');
			$cmbCondVal->AddItem(0,"OFF");
			$cmbCondVal->AddItem(1,"ON");
			array_push($rowCondition,$cmbCondVal);
		}
		else if($new_condition_type == CONDITION_TYPE_TIME_PERIOD)
		{
			array_push($rowCondition, new CTextBox('new_condition_value', "1-7,00:00-23:59", 40));
		}
		else if($new_condition_type == CONDITION_TYPE_TRIGGER_SEVERITY)
		{
			$cmbCondVal = new CComboBox('new_condition_value');
			$cmbCondVal->AddItem(0,S_NOT_CLASSIFIED);
			$cmbCondVal->AddItem(1,S_INFORMATION);
			$cmbCondVal->AddItem(2,S_WARNING);
			$cmbCondVal->AddItem(3,S_AVERAGE);
			$cmbCondVal->AddItem(4,S_HIGH);
			$cmbCondVal->AddItem(5,S_DISASTER);
			array_push($rowCondition,$cmbCondVal);
		}
// add condition button
		array_push($rowCondition,BR,new CButton('add_condition','add'));

// end of new condition preparation
		$frmAction->AddRow(S_CONDITION, $rowCondition);

/*		$frmAction->AddRow(
			$actiontype == ACTION_TYPE_MESSAGE ? S_DELAY_BETWEEN_MESSAGES_IN_SEC : S_DELAY_BETWEEN_EXECUTIONS_IN_SEC,
			new CTextBox('delay',$delay,5));*/

		if($actiontype == ACTION_TYPE_MESSAGE)
		{
			$cmbRecipient = new CComboBox('recipient', $recipient,'submit()');
			$cmbRecipient->AddItem(0,S_SINGLE_USER);
			$cmbRecipient->AddItem(1,S_USER_GROUP);
			$frmAction->AddRow(S_SEND_MESSAGE_TO, $cmbRecipient);

			if($recipient==RECIPIENT_TYPE_GROUP)
			{
				
				$cmbGroups = new CComboBox('userid', $uid);
		
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
			$frmAction->AddRow(S_SUBJECT, new CTextBox('subject',$subject,80));
			$frmAction->AddRow(S_MESSAGE, new CTextArea('message',$message,77,7));
			$frmAction->AddVar("scripts",$scripts); 
		}
		else
		{
			$frmAction->AddRow(S_REMOTE_COMMAND, new CTextArea('scripts',$scripts,77,7));
			$frmAction->AddVar("recipient",$recipient);
			$frmAction->AddVar("userid",$uid);
			$frmAction->AddVar("subject",$subject);
			$frmAction->AddVar("message",$message);
		}

		$cmbRepeat = new CComboBox('repeat',$repeat,'submit()');
		$cmbRepeat->AddItem(0,S_NO_REPEATS);
		$cmbRepeat->AddItem(1,S_REPEAT);
		$frmAction->AddRow(S_REPEAT, $cmbRepeat);

		if($repeat>0)
		{
			$frmAction->AddRow(S_NUMBER_OF_REPEATS, new CTextBox('maxrepeats',$maxrepeats,5));
			$frmAction->AddRow(S_DELAY_BETWEEN_REPEATS, new CTextBox('repeatdelay',$repeatdelay,5));
		} else {
			$frmAction->AddVar("maxrepeats",$maxrepeats);
			$frmAction->AddVar("repeatdelay",$repeatdelay);
		}

		$cmbStatus = new CComboBox('status',$status);
		$cmbStatus->AddItem(0,S_ENABLED);
		$cmbStatus->AddItem(1,S_DISABLED);
		$frmAction->AddRow(S_STATUS, $cmbStatus);

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
		$gsm_modem	= get_request("gsm_modem","/dev/ttyS0");

		if(isset($_REQUEST["mediatypeid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$result=DBselect("select mediatypeid,type,description,smtp_server,smtp_helo,smtp_email,exec_path,gsm_modem from media_type where mediatypeid=".$_REQUEST["mediatypeid"]);
			$row=DBfetch($result);
			$mediatypeid=$row["mediatypeid"];
			$type=@iif(isset($_REQUEST["type"]),$_REQUEST["type"],$row["type"]);
			$description=$row["description"];
			$smtp_server=$row["smtp_server"];
			$smtp_helo=$row["smtp_helo"];
			$smtp_email=$row["smtp_email"];
			$exec_path=$row["exec_path"];
			$gsm_modem=$row["gsm_modem"];
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
		$cmbType->AddItem(ALERT_TYPE_EMAIL,S_EMAIL);
		$cmbType->AddItem(ALERT_TYPE_EXEC,S_SCRIPT);
		$cmbType->AddItem(ALERT_TYPE_SMS,S_SMS);
		$frmMeadia->AddRow(S_TYPE,$cmbType);

		if($type==ALERT_TYPE_EMAIL)
		{
			$frmMeadia->AddVar("exec_path",$exec_path);
			$frmMeadia->AddVar("gsm_modem",$gsm_modem);
			$frmMeadia->AddRow(S_SMTP_SERVER,new CTextBox("smtp_server",$smtp_server,30));
			$frmMeadia->AddRow(S_SMTP_HELO,new CTextBox("smtp_helo",$smtp_helo,30));
			$frmMeadia->AddRow(S_SMTP_EMAIL,new CTextBox("smtp_email",$smtp_email,30));
		}elseif($type==ALERT_TYPE_SMS)
		{
			$frmMeadia->AddVar("exec_path",$exec_path);
			$frmMeadia->AddVar("smtp_server",$smtp_server);
			$frmMeadia->AddVar("smtp_helo",$smtp_helo);
			$frmMeadia->AddVar("smtp_email",$smtp_email);
			$frmMeadia->AddRow(S_GSM_MODEM,new CTextBox("gsm_modem",$gsm_modem,50));
		}elseif($type==ALERT_TYPE_EXEC)
		{
			$frmMeadia->AddVar("gsm_modem",$gsm_modem);
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
		$frmImages->AddVar("config",get_request("config",3));

		if(isset($_REQUEST["imageid"]))
		{
			$result=DBselect("select imageid,imagetype,name from images".
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
			$imageid	= get_request("imageid",0);
		}

		$frmImages->AddRow(S_NAME,new CTextBox("name",$name,64));
	
		$cmbImg = new CComboBox("imagetype",$imagetype);
		$cmbImg->AddItem(1,S_ICON);
		$cmbImg->AddItem(2,S_BACKGROUND);
		$frmImages->AddRow(S_TYPE,$cmbImg);

		$frmImages->AddRow(S_UPLOAD,new CFile("image"));

		if($imageid > 0)
		{
			$frmImages->AddRow(S_IMAGE,new CLink(
				new CImg("image.php?width=640&height=480&imageid=".$imageid,"no image",NULL),
				"image.php?imageid=".$row["imageid"]));
		}

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
			$result=DBselect("select screenid,name,hsize,vsize from screens g".
				" where screenid=".$_REQUEST["screenid"]);
			$row=DBfetch($result);
			$frm_title = S_SCREEN." \"".$row["name"]."\"";
		}
		if(isset($_REQUEST["screenid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$name=$row["name"];
			$hsize=$row["hsize"];
			$vsize=$row["vsize"];
		}
		else
		{
			$name=get_request("name","");
			$hsize=get_request("hsize",1);
			$vsize=get_request("bsize",1);
		}
		$frmScr = new CFormTable($frm_title,"screenconf.php");
		$frmScr->SetHelp("web.screenconf.screen.php");

		if(isset($_REQUEST["screenid"]))
		{
			$frmScr->AddVar("screenid",$_REQUEST["screenid"]);
		}
		$frmScr->AddRow(S_NAME, new CTextBox("name",$name,32));
		$frmScr->AddRow(S_COLUMNS, new CTextBox("hsize",$hsize,5));
		$frmScr->AddRow(S_ROWS, new CTextBox("vsize",$vsize,5));

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

		$form = new CFormTable(S_SCREEN_CELL_CONFIGURATION,"screenedit.php#form");
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
			$resourcetype	= $irow["resourcetype"];
			$resourceid	= $irow["resourceid"];
			$width		= $irow["width"];
			$height		= $irow["height"];
			$colspan	= $irow["colspan"];
			$rowspan	= $irow["rowspan"];
			$elements	= $irow["elements"];
			$valign		= $irow["valign"];
			$halign		= $irow["halign"];
			$style		= $irow["style"];
			$url		= $irow["url"];
		}
		else
		{
			$resourcetype	= get_request("resourcetype",	0);
			$resourceid	= get_request("resourceid",	0);
			$width		= get_request("width",		500);
			$height		= get_request("height",		100);
			$colspan	= get_request("colspan",	0);
			$rowspan	= get_request("rowspan",	0);
			$elements	= get_request("elements",	25);
			$valign		= get_request("valign",		VALIGN_DEFAULT);
			$halign		= get_request("halign",		HALIGN_DEFAULT);
			$style		= get_request("style",		0);
			$url		= get_request("url",		"");
		}

		$form->AddVar("screenid",$_REQUEST["screenid"]);

		$cmbRes = new CCombobox("resourcetype",$resourcetype,"submit()");
		$cmbRes->AddItem(SCREEN_RESOURCE_GRAPH,		S_GRAPH);
		$cmbRes->AddItem(SCREEN_RESOURCE_SIMPLE_GRAPH,	S_SIMPLE_GRAPH);
		$cmbRes->AddItem(SCREEN_RESOURCE_PLAIN_TEXT,	S_PLAIN_TEXT);
		$cmbRes->AddItem(SCREEN_RESOURCE_MAP,		S_MAP);
		$cmbRes->AddItem(SCREEN_RESOURCE_SCREEN,	S_SCREEN);
		$cmbRes->AddItem(SCREEN_RESOURCE_SERVER_INFO,	S_SERVER_INFO);
		$cmbRes->AddItem(SCREEN_RESOURCE_HOSTS_INFO,	S_HOSTS_INFO);
		$cmbRes->AddItem(SCREEN_RESOURCE_TRIGGERS_INFO,	S_TRIGGERS_INFO);
		$cmbRes->AddItem(SCREEN_RESOURCE_TRIGGERS_OVERVIEW,	S_TRIGGERS_OVERVIEW);
		$cmbRes->AddItem(SCREEN_RESOURCE_DATA_OVERVIEW,		S_DATA_OVERVIEW);
		$cmbRes->AddItem(SCREEN_RESOURCE_CLOCK,		S_CLOCK);
		$cmbRes->AddItem(SCREEN_RESOURCE_URL,		S_URL);
		$cmbRes->AddItem(SCREEN_RESOURCE_ACTIONS,	S_HISTORY_OF_ACTIONS);
                $cmbRes->AddItem(SCREEN_RESOURCE_EVENTS,       S_HISTORY_OF_EVENTS);
		$form->AddRow(S_RESOURCE,$cmbRes);

		if($resourcetype == SCREEN_RESOURCE_GRAPH)
		{
	// User-defined graph
			$result=DBselect("select distinct h.host,g.graphid,g.name ".
				" from graphs g,graphs_items gi,hosts h,items i ".
				" where gi.graphid=g.graphid and h.hostid=i.hostid and gi.itemid=i.itemid ".
				" and h.status=".HOST_STATUS_MONITORED." and i.status=".ITEM_STATUS_ACTIVE.
				" order by h.host,g.name");

			$cmbGraphs = new CComboBox("resourceid",$resourceid);
			while($row=DBfetch($result))
			{
				$name = $row["host"].":".$row["name"];
				$cmbGraphs->AddItem($row["graphid"],$name);
			}

			$form->AddRow(S_GRAPH_NAME,$cmbGraphs);
		}
		elseif($resourcetype == SCREEN_RESOURCE_SIMPLE_GRAPH)
		{
	// Simple graph
			$result=DBselect("select distinct h.host,i.description,i.itemid,i.key_".
				" from hosts h,items i where h.hostid=i.hostid".
				" and h.status=".HOST_STATUS_MONITORED." and i.status=".ITEM_STATUS_ACTIVE.
				" order by h.host,i.description");

			$cmbItems = new CCombobox("resourceid",$resourceid);
			while($row=DBfetch($result))
			{
				$description_=item_description($row["description"],$row["key_"]);
				$cmbItems->AddItem($row["itemid"],$row["host"].": ".$description_);

			}
			$form->AddRow(S_PARAMETER,$cmbItems);
		}
		elseif($resourcetype == SCREEN_RESOURCE_MAP)
		{
	// Map
			$result=DBselect("select sysmapid,name from sysmaps order by name");

			$cmbMaps = new CComboBox("resourceid",$resourceid);
			while($row=DBfetch($result))
			{
				$cmbMaps->AddItem($row["sysmapid"],$row["name"]);
			}

			$form->AddRow(S_MAP,$cmbMaps);
		}
		elseif($resourcetype == SCREEN_RESOURCE_PLAIN_TEXT)
		{
	// Plain text
			$result=DBselect("select h.host,i.description,i.itemid,i.key_".
				" from hosts h,items i where h.hostid=i.hostid".
				" and h.status=".HOST_STATUS_MONITORED." and i.status=".ITEM_STATUS_ACTIVE.
				" order by h.host,i.description");

			$cmbHosts = new CComboBox("resourceid",$resourceid);
			while($row=DBfetch($result))
			{
				$description_=item_description($row["description"],$row["key_"]);
				$cmbHosts->AddItem($row["itemid"],$row["host"].": ".$description_);

			}

			$form->AddRow(S_PARAMETER,$cmbHosts);
			$form->AddRow(S_SHOW_LINES, new CTextBox("elements",$elements,2));
		}
                elseif($resourcetype == SCREEN_RESOURCE_ACTIONS)
                {
        // History of actions
                        $form->AddRow(S_SHOW_LINES, new CTextBox("elements",$elements,2));
			$form->AddVar("resourceid",0);
                }
                elseif($resourcetype == SCREEN_RESOURCE_EVENTS)
                {
        // History of events
                        $form->AddRow(S_SHOW_LINES, new CTextBox("elements",$elements,2));
                        $form->AddVar("resourceid",0);
                }
		elseif(in_array($resourcetype,array(SCREEN_RESOURCE_TRIGGERS_OVERVIEW,SCREEN_RESOURCE_DATA_OVERVIEW)))
		{
	// Overiews
			$cmbGroup = new CComboBox("resourceid",$resourceid);

			$cmbGroup->AddItem(0,S_ALL_SMALL);
			$result=DBselect("select groupid,name from groups order by name");
			while($row=DBfetch($result))
			{
				$cmbGroup = new CComboBox("resourceid",$resourceid);

				$cmbGroup->AddItem(0,S_ALL_SMALL);
				$result=DBselect("select groupid,name from groups order by name");
				while($row=DBfetch($result))
				{
					$result2=DBselect("select distinct h.hostid,h.host from hosts h,items i,hosts_groups hg where".
						" h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$row["groupid"].
						" and hg.hostid=h.hostid order by h.host");
					while($row2=DBfetch($result2))
					{
						if(!check_right("Host","R",$row2["hostid"]))    continue;
						$cmbGroup->AddItem($row["groupid"],$row["name"]);
						break;
					}
				}
			}
			$form->AddRow(S_GROUP,$cmbGroup);

		}
		elseif($resourcetype == SCREEN_RESOURCE_SCREEN)
		{
			$cmbScreens = new CComboBox("resourceid",$resourceid);
			$result=DBselect("select screenid,name from screens");
			while($row=DBfetch($result))
			{
				if(check_screen_recursion($_REQUEST["screenid"],$row["screenid"]))
					continue;
				$cmbScreens->AddItem($row["screenid"],$row["name"]);

			}

			$form->AddRow(S_SCREEN,$cmbScreens);
		}
		else // SCREEN_RESOURCE_HOSTS_INFO,  SCREEN_RESOURCE_TRIGGERS_INFO,  SCREEN_RESOURCE_CLOCK
		{
			$form->AddVar("resourceid",0);
		}

		if(in_array($resourcetype,array(SCREEN_RESOURCE_HOSTS_INFO,SCREEN_RESOURCE_TRIGGERS_INFO)))
		{
			$cmbStyle = new CComboBox("style", $style);
			$cmbStyle->AddItem(STYLE_HORISONTAL,	S_HORISONTAL);
			$cmbStyle->AddItem(STYLE_VERTICAL,	S_VERTICAL);
			$form->AddRow(S_STYLE,	$cmbStyle);
		}
		elseif($resourcetype == SCREEN_RESOURCE_CLOCK)
		{
			$cmbStyle = new CComboBox("style", $style);
			$cmbStyle->AddItem(TIME_TYPE_LOCAL,	S_LOCAL_TIME);
			$cmbStyle->AddItem(TIME_TYPE_SERVER,	S_SERVER_TIME);
			$form->AddRow(S_TIME_TYPE,	$cmbStyle);
		}
		else
		{
			$form->AddVar("style",	0);
		}

		if(in_array($resourcetype,array(SCREEN_RESOURCE_URL)))
		{
			$form->AddRow(S_URL, new CTextBox("url",$url,60));
		}
		else
		{
			$form->AddVar("url",	"");
		}

		if(in_array($resourcetype,array(SCREEN_RESOURCE_GRAPH,SCREEN_RESOURCE_SIMPLE_GRAPH,SCREEN_RESOURCE_CLOCK,SCREEN_RESOURCE_URL)))
		{
			$form->AddRow(S_WIDTH,	new CTextBox("width",$width,5));
			$form->AddRow(S_HEIGHT,	new CTextBox("height",$height,5));
		}
		else
		{
			$form->AddVar("width",	0);
			$form->AddVar("height",	0);
		}

		if(in_array($resourcetype,array(SCREEN_RESOURCE_GRAPH,SCREEN_RESOURCE_SIMPLE_GRAPH,SCREEN_RESOURCE_MAP,
			SCREEN_RESOURCE_CLOCK,SCREEN_RESOURCE_URL)))
		{
			$cmbHalign = new CComboBox("halign",$halign);
			$cmbHalign->AddItem(HALIGN_CENTER,	S_CENTER);
			$cmbHalign->AddItem(HALIGN_LEFT,	S_LEFT);
			$cmbHalign->AddItem(HALIGN_RIGHT,	S_RIGHT);
			$form->AddRow(S_HORISONTAL_ALIGN,	$cmbHalign);
		}
		else
		{
			$form->AddVar("halign",	0);
		}

		$cmbValign = new CComboBox("valign",$valign);
		$cmbValign->AddItem(VALIGN_MIDDLE,	S_MIDDLE);
		$cmbValign->AddItem(VALIGN_TOP,		S_TOP);
		$cmbValign->AddItem(VALIGN_BOTTOM,	S_BOTTOM);
		$form->AddRow(S_VERTICAL_ALIGN,	$cmbValign);

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
				array(
					new CCheckBox(
						"severity[]",
						in_array($i,$severity)?'yes':'no', 
						NULL,		/* action */
						$i),		/* value */
					$label[$i]
				),
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
		$frmHouseKeep->AddVar("work_period",$config["work_period"]);
		$frmHouseKeep->AddRow(S_DO_NOT_KEEP_ACTIONS_OLDER_THAN,
			new CTextBox("alert_history",$config["alert_history"],8));
		$frmHouseKeep->AddRow(S_DO_NOT_KEEP_EVENTS_OLDER_THAN,
			new CTextBox("alarm_history",$config["alarm_history"],8));
		$frmHouseKeep->AddItemToBottomRow(new CButton("save",S_SAVE));
		$frmHouseKeep->Show();
	}

	function	insert_work_period_form()
	{
		$config=select_config();
		
		$frmHouseKeep = new CFormTable(S_WORKING_TIME,"config.php");
		$frmHouseKeep->SetHelp("web.config.workperiod.php");
		$frmHouseKeep->AddVar("config",get_request("config",7));
		$frmHouseKeep->AddVar("alert_history",$config["alert_history"]);
		$frmHouseKeep->AddVar("alarm_history",$config["alarm_history"]);
		$frmHouseKeep->AddVar("refresh_unsupported",$config["refresh_unsupported"]);
		$frmHouseKeep->AddRow(S_WORKING_TIME,
			new CTextBox("work_period",$config["work_period"],35));
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
		$frmHouseKeep->AddVar("work_period",$config["work_period"]);
		$frmHouseKeep->AddRow(S_REFRESH_UNSUPPORTED_ITEMS,
			new CTextBox("refresh_unsupported",$config["refresh_unsupported"],8));
		$frmHouseKeep->AddItemToBottomRow(new CButton("save",S_SAVE));
		$frmHouseKeep->Show();
	}

	function	insert_host_form($show_only_tmp=0)
	{

		global $_REQUEST;

		$groups= get_request("groups",array());

		$newgroup	= get_request("newgroup","");

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

		$templateid= get_request("templateid",0);

		$frm_title	= $show_only_tmp ? S_TEMPLATE : S_HOST;
		if(isset($_REQUEST["hostid"])){
			$db_host=get_host_by_hostid($_REQUEST["hostid"]);
			$frm_title	.= SPACE."\"".$db_host["host"]."\"";
		}

		if(isset($_REQUEST["hostid"]) && !isset($_REQUEST["form_refresh"]))
		{

			$host	= $db_host["host"];
			$port	= $db_host["port"];
			$status	= $db_host["status"];
			$useip	= $db_host["useip"]==1 ? 'yes' : 'no';
			$ip	= $db_host["ip"];

			$templateid = $db_host["templateid"];
// add groups
			$db_groups=DBselect("select groupid from hosts_groups where hostid=".$_REQUEST["hostid"]);
			while($db_group=DBfetch($db_groups)){
				if(in_array($db_group["groupid"],$groups)) continue;
				array_push($groups, $db_group["groupid"]);
			}
// read profile
			$db_profiles = DBselect("select * from hosts_profiles where hostid=".$_REQUEST["hostid"]);

			$useprofile = "no";
			$db_profile = DBfetch($db_profiles);
			if($db_profile)
			{
				$useprofile = "yes";


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
		if($show_only_tmp){
			$useip = "no";
		}

		$frmHost = new CFormTable($frm_title,"hosts.php");
		$frmHost->SetHelp("web.hosts.host.php");
		$frmHost->AddVar("config",get_request("config",0));

		if(isset($_REQUEST["hostid"]))		$frmHost->AddVar("hostid",$_REQUEST["hostid"]);
		if(isset($_REQUEST["groupid"]))		$frmHost->AddVar("groupid",$_REQUEST["groupid"]);
		
		$frmHost->AddRow(S_NAME,new CTextBox("host",$host,20));

		$frm_row = array();
		$db_groups=DBselect("select distinct groupid,name from groups order by name");
		while($db_group=DBfetch($db_groups))
		{
			array_push($frm_row,
				array(
					new CCheckBox("groups[]",
						in_array($db_group["groupid"],$groups) ? 'yes' : 'no', 
						NULL,
						$db_group["groupid"]
						),
					$db_group["name"]
				),
				BR);
		}
		$frmHost->AddRow(S_GROUPS,$frm_row);

		$frmHost->AddRow(S_NEW_GROUP,new CTextBox("newgroup",$newgroup));

// onChange does not work on some browsers: MacOS, KDE browser
		if($show_only_tmp)
		{
			$useip = "no";
			$frmHost->AddVar("useip",$useip);
		}
		else
		{
			$frmHost->AddRow(S_USE_IP_ADDRESS,new CCheckBox("useip",$useip,"submit()"));
		}

		if($useip=="yes")
		{
			$frmHost->AddRow(S_IP_ADDRESS,new CTextBox("ip",$ip,"15"));
		}
		else
		{
			$frmHost->AddVar("ip",$ip);
		}

		if($show_only_tmp)
		{
			$port = "10050";
			$status = HOST_STATUS_TEMPLATE;

			$frmHost->AddVar("port",$port);
			$frmHost->AddVar("status",$status);
		}
		else
		{
			$frmHost->AddRow(S_PORT,new CTextBox("port",$port,6));	

			$cmbStatus = new CComboBox("status",$status);
			$cmbStatus->AddItem(HOST_STATUS_MONITORED,	S_MONITORED);
//			$cmbStatus->AddItem(HOST_STATUS_TEMPLATE,	S_TEMPLATE);
			$cmbStatus->AddItem(HOST_STATUS_NOT_MONITORED,	S_NOT_MONITORED);
			$frmHost->AddRow(S_STATUS,$cmbStatus);	
		}

		$cmbHosts = new CComboBox("templateid",$templateid);
		$cmbHosts->AddItem(0,"...");
		$hosts=DBselect("select host,hostid from hosts where status in (".HOST_STATUS_TEMPLATE.")".
			" order by host");
		while($host=DBfetch($hosts))
		{
			$cmbHosts->AddItem($host["hostid"],$host["host"]);
		}
		$frmHost->AddRow(S_LINK_WITH_TEMPLATE,$cmbHosts);
	
		if($show_only_tmp)
		{
			$useprofile = "no";
			$frmHost->AddVar("useprofile",$useprofile);
		}
		else
		{
			$frmHost->AddRow(S_USE_PROFILE,new CCheckBox("useprofile",$useprofile,"submit()"));
		}
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
					url_param("form").url_param("config").url_param("hostid").
					url_param("groupid")
				)
			);
		}
		$frmHost->AddItemToBottomRow(SPACE);
		$frmHost->AddItemToBottomRow(new CButtonCancel(url_param("config").url_param("groupid")));
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
			$group = get_hostgroup_by_groupid($_REQUEST["groupid"]);
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

		$cmbHosts = new CListBox("hosts[]",$hosts,10);
		$db_hosts=DBselect("select distinct hostid,host from hosts".
			" where status<>".HOST_STATUS_DELETED." order by host");
		while($db_host=DBfetch($db_hosts))
		{
			$cmbHosts->AddItem($db_host["hostid"],$db_host["host"]);
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

		$row=DBfetch($result);
		if($row)
		{

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

			$frmHostP->AddRow(S_DEVICE_TYPE,new CTextBox("devicetype",$devicetype,61,'yes'));
			$frmHostP->AddRow(S_NAME,new CTextBox("name",$name,61,'yes'));
			$frmHostP->AddRow(S_OS,new CTextBox("os",$os,61,'yes'));
			$frmHostP->AddRow(S_SERIALNO,new CTextBox("serialno",$serialno,61,'yes'));
			$frmHostP->AddRow(S_TAG,new CTextBox("tag",$tag,61,'yes'));
			$frmHostP->AddRow(S_MACADDRESS,new CTextBox("macaddress",$macaddress,61,'yes'));
			$frmHostP->AddRow(S_HARDWARE,new CTextArea("hardware",$hardware,60,4,'yes'));
			$frmHostP->AddRow(S_SOFTWARE,new CTextArea("software",$software,60,4,'yes'));
			$frmHostP->AddRow(S_CONTACT,new CTextArea("contact",$contact,60,4,'yes'));
			$frmHostP->AddRow(S_LOCATION,new CTextArea("location",$location,60,4,'yes'));
			$frmHostP->AddRow(S_NOTES,new CTextArea("notes",$notes,60,4,'yes'));
		}
		else
		{
			$frmHostP->AddSpanRow("Profile for this host is missing","form_row_c");
		}
		$frmHostP->Show();
	}

	function insert_application_form()
	{
		global $_REQUEST;

		$frm_title = "New Application";

		if(isset($_REQUEST["applicationid"]))
		{
			$result=DBselect("select * from applications where applicationid=".$_REQUEST["applicationid"]);
			$row=DBfetch($result);
			$frm_title = "Application: \"".$row["name"]."\"";
		}
		if(isset($_REQUEST["applicationid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$appname = $row["name"];
			$apphostid = $row["hostid"];
		}
		else
		{
			$appname = get_request("appname","");
			$apphostid = get_request("apphostid",get_request("hostid",0));
		}

		$db_host = get_host_by_hostid($apphostid,1 /* no error message */);
		if($db_host)
		{
			$apphost = $db_host["host"];
		}
		else
		{
			$apphost = "";
			$apphostid = 0;
		}

		$frmApp = new CFormTable($frm_title);
		$frmApp->SetHelp("web.applications.php");

		if(isset($_REQUEST["applicationid"]))
			$frmApp->AddVar("applicationid",$_REQUEST["applicationid"]);

		$frmApp->AddRow(S_NAME,new CTextBox("appname",$appname,32));

		$frmApp->AddVar("apphostid",$apphostid);

		if(!isset($_REQUEST["applicationid"]))
		{ // anly new application can select host
			$frmApp->AddRow(S_HOST,array(
				new CTextBox("apphost",$apphost,32,'yes'),
				new CButton("btn1",S_SELECT,
					"return PopUp('popup.php?dstfrm=".$frmApp->GetName().
					"&dstfld1=apphostid&dstfld2=apphost&srctbl=hosts&srcfld1=hostid&srcfld2=host','new_win',".
					"'width=450,height=450,resizable=1,scrollbars=1');",
					'T')
				));
		}

		$frmApp->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["applicationid"]))
		{
			$frmApp->AddItemToBottomRow(SPACE);
			$frmApp->AddItemToBottomRow(new CButtonDelete("Delete this application?",
					url_param("config").url_param("hostid").url_param("groupid").
					url_param("form").url_param("applicationid")));
		}
		$frmApp->AddItemToBottomRow(SPACE);
		$frmApp->AddItemToBottomRow(new CButtonCancel(url_param("config").url_param("hostid").url_param("groupid")));

		$frmApp->Show();

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
		$cmbLabel->AddItem(0,S_LABEL);
		$cmbLabel->AddItem(1,S_IP_ADDRESS);
		$cmbLabel->AddItem(2,S_ELEMENT_NAME);
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

	function insert_map_element_form()
	{
		$frmEl = new CFormTable("New map element","sysmap.php");
		$frmEl->SetHelp("web.sysmap.host.php");
		$frmEl->AddVar("sysmapid",$_REQUEST["sysmapid"]);

		if(isset($_REQUEST["selementid"]))
		{
			$frmEl->AddVar("selementid",$_REQUEST["selementid"]);

			$element = get_sysmaps_element_by_selementid($_REQUEST["selementid"]);
			$frmEl->SetTitle("Map element \"".$element["label"]."\"");
		}

		if(isset($_REQUEST["selementid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$elementid	= $element["elementid"];
			$elementtype	= $element["elementtype"];
			$label		= $element["label"];
			$x		= $element["x"];
			$y		= $element["y"];
			$icon		= $element["icon"];
			$url		= $element["url"];
			$icon_on	= $element["icon_on"];
			$label_location	= $element["label_location"];
			if(is_null($label_location)) $label_location = -1;
		}
		else
		{
			$elementid 	= get_request("elementid", 	0);
			$elementtype	= get_request("elementtype", 	SYSMAP_ELEMENT_TYPE_HOST);
			$label		= get_request("label",		"");
			$x		= get_request("x",		0);
			$y		= get_request("y",		0);
			$icon		= get_request("icon",		"");
			$url		= get_request("url",		"");
			$icon_on	= get_request("icon_on",	"");
			$label_location	= get_request("label_location",	"-1");
		}

		$cmbType = new CComboBox("elementtype",$elementtype,"submit()");

		$db_hosts = DBselect("select hostid from hosts");
		if(DBfetch($db_hosts))
			$cmbType->AddItem(SYSMAP_ELEMENT_TYPE_HOST,	S_HOST);

		$db_maps = DBselect("select sysmapid from sysmaps where sysmapid!=".$_REQUEST["sysmapid"]);
		if(DBfetch($db_maps))
			$cmbType->AddItem(SYSMAP_ELEMENT_TYPE_MAP,	S_MAP);

		$cmbType->AddItem(SYSMAP_ELEMENT_TYPE_TRIGGER,		S_TRIGGER);
		$cmbType->AddItem(SYSMAP_ELEMENT_TYPE_HOST_GROUP,	S_HOST_GROUP);

		$frmEl->AddRow(S_TYPE,$cmbType);

		$frmEl->AddRow("Label", new CTextBox("label", $label, 32));

		$cmbLocation = new CComboBox("label_location",$label_location);
		$cmbLocation->AddItem(-1,'-');
		$cmbLocation->AddItem(0,S_BOTTOM);
		$cmbLocation->AddItem(1,S_LEFT);
		$cmbLocation->AddItem(2,S_RIGHT);
		$cmbLocation->AddItem(3,S_TOP);
		$frmEl->AddRow(S_LABEL_LOCATION,$cmbLocation);

		if($elementtype==SYSMAP_ELEMENT_TYPE_HOST) 
		{
			$host = "";
			$host_info = 0;

			$db_hosts = DBselect("select host from hosts where hostid=$elementid");
			$host_info = DBfetch($db_hosts);
			if($host_info)
				$host = $host_info["host"];
			else
				$elementid=0;

			if($elementid==0)
			{
				$db_hosts = DBselect("select hostid,host from hosts",1);
				$db_host = DBfetch($db_hosts);
				$host = $db_host["host"];
				$elementid = $db_host["hostid"];
			}

			$frmEl->AddVar("elementid",$elementid);
			$frmEl->AddRow(S_HOST, array(
				new CTextBox("host",$host,32,'yes'),
				new CButton("btn1",S_SELECT,"return PopUp('popup.php?dstfrm=".$frmEl->GetName().
					"&dstfld1=elementid&dstfld2=host&srctbl=hosts&srcfld1=hostid&srcfld2=host','new_win',".
					"'width=450,height=450,resizable=1,scrollbars=1');","T")
			));
		}
		elseif($elementtype==SYSMAP_ELEMENT_TYPE_MAP)
		{
			$cmbMaps = new CComboBox("elementid",$elementid);
			$db_maps = DBselect("select sysmapid,name from sysmaps");
			while($db_map = DBfetch($db_maps))
			{
				$cmbMaps->AddItem($db_map["sysmapid"],$db_map["name"]);
			}
			$frmEl->AddRow(S_MAP, $cmbMaps);
		}
		elseif($elementtype==SYSMAP_ELEMENT_TYPE_TRIGGER)
		{
			$trigger = "";

			$trigger_info = DBfetch(DBselect("select triggerid from triggers where triggerid=".$elementid));
			
			if($trigger_info)
				$trigger = expand_trigger_description($trigger_info["triggerid"]);
			else
				$elementid=0;

			if($elementid==0)
			{
				$trigger = "";
				$elementid = 0;
			}

			$frmEl->AddVar("elementid",$elementid);
			$frmEl->AddRow(S_TRIGGER, array(
				new CTextBox("trigger",$trigger,32,'yes'),
				new CButton("btn1",S_SELECT,"return PopUp('popup.php?dstfrm=".$frmEl->GetName().
					"&dstfld1=elementid&dstfld2=trigger&srctbl=triggers&srcfld1=triggerid&srcfld2=description','new_win',".
					"'width=550,height=450,resizable=1,scrollbars=1');","T")
			));
		}
		elseif($elementtype==SYSMAP_ELEMENT_TYPE_HOST_GROUP)
		{
			$group = "";

			$cmbGroup = new CComboBox('elementid', $elementid);
			
			$db_groups = DBselect('select distinct g.* from groups g');
			while($group = DBfetch($db_groups))
			{
				$cmbGroup->AddItem($group['groupid'], $group['name']);
			}
			$frmEl->AddRow(S_HOST_GROUP, $cmbGroup);
		}
		
		$cmbIcon = new CComboBox("icon",$icon);
		$result=DBselect("select name from images where imagetype=1 order by name");
		while($row=DBfetch($result))
			$cmbIcon->AddItem($row["name"],$row["name"]);
		$frmEl->AddRow("Icon (OFF)",$cmbIcon);

		$cmbIcon = new CComboBox("icon_on",$icon_on);
		$result=DBselect("select name from images where imagetype=1 order by name");
		while($row=DBfetch($result))
			$cmbIcon->AddItem($row["name"],$row["name"]);
		$frmEl->AddRow("Icon (ON)",$cmbIcon);

		$frmEl->AddRow("Coordinate X", new CTextBox("x", $x, 5));
		$frmEl->AddRow("Coordinate Y", new CTextBox("y", $y, 5));
		$frmEl->AddRow("URL", new CTextBox("url", $url, 64));

		$frmEl->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["selementid"]))
		{
			$frmEl->AddItemToBottomRow(SPACE);
			$frmEl->AddItemToBottomRow(new CButtonDelete("Delete element?",url_param("form").
				url_param("selementid").url_param("sysmapid")));
		}
		$frmEl->AddItemToBottomRow(SPACE);
		$frmEl->AddItemToBottomRow(new CButtonCancel(url_param("sysmapid")));

		$frmEl->Show();
	}

	function insert_map_link_form()
	{
		$frmCnct = new CFormTable("New connector","sysmap.php");
		$frmCnct->SetHelp("web.sysmap.connector.php");
		$frmCnct->AddVar("sysmapid",$_REQUEST["sysmapid"]);

		if(isset($_REQUEST["linkid"]))
		{
			$frmCnct->AddVar("linkid",$_REQUEST["linkid"]);
			$db_links = DBselect("select * from sysmaps_links where linkid=".$_REQUEST["linkid"]);
			$db_link = DBfetch($db_links);
		}

		if(isset($_REQUEST["linkid"]) && !isset($_REQUEST["form_refresh"]))
		{
			$selementid1	= $db_link["selementid1"];
			$selementid2	= $db_link["selementid2"];
			$triggerid	= $db_link["triggerid"];
			$drawtype_off	= $db_link["drawtype_off"];
			$drawtype_on	= $db_link["drawtype_on"];
			$color_off	= $db_link["color_off"];
			$color_on	= $db_link["color_on"];

			if(is_null($triggerid)) $triggerid = 0;
		}
		else
		{
			$selementid1	= get_request("selementid1",	0);
			$selementid2	= get_request("selementid2",	0);
			$triggerid	= get_request("triggerid",	0);
			$drawtype_off	= get_request("drawtype_off",	0);
			$drawtype_on	= get_request("drawtype_on",	0);
			$color_off	= get_request("color_off",	0);
			$color_on	= get_request("color_on",	0);
		}

/* START comboboxes preparations */
		$cmbElements1 = new CComboBox("selementid1",$selementid1);
		$cmbElements2 = new CComboBox("selementid2",$selementid2);
		$db_selements = DBselect("select selementid,label,elementid,elementtype from sysmaps_elements".
			" where sysmapid=".$_REQUEST["sysmapid"]);
		while($db_selement = DBfetch($db_selements))
		{
			$label = $db_selement["label"];
			if($db_selement["elementtype"] == SYSMAP_ELEMENT_TYPE_HOST)
			{
				$db_host = get_host_by_hostid($db_selement["elementid"]);
				$label .= ":".$db_host["host"];
			}
			elseif($db_selement["elementtype"] == SYSMAP_ELEMENT_TYPE_MAP)
			{
				$db_map = get_sysmap_by_sysmapid($db_selement["elementid"]);
				$label .= ":".$db_map["name"];
			}
			elseif($db_selement["elementtype"] == SYSMAP_ELEMENT_TYPE_TRIGGER)
			{
				if($db_selement["elementid"]>0)
				{
					$label .= ":".expand_trigger_description($db_selement["elementid"]);
				}
			}
			elseif($db_selement["elementtype"] == SYSMAP_ELEMENT_TYPE_HOST_GROUP)
			{
				if($db_selement["elementid"]>0)
				{
					$db_group = DBfetch(DBselect('select name from groups where groupid='.$db_selement["elementid"]));
					$label .= ":".$db_group['name'];
				}
			}
			$cmbElements1->AddItem($db_selement["selementid"],$label);
			$cmbElements2->AddItem($db_selement["selementid"],$label);
		}

		$cmbType_off = new CComboBox("drawtype_off",$drawtype_off);
		$cmbType_on = new CComboBox("drawtype_on",$drawtype_on);
		for($i=0; $i < 5; ++$i)
		{
			$value = get_drawtype_description($i);
			$cmbType_off->AddItem($i, $value);
			$cmbType_on->AddItem($i, $value);
		}

		
		$cmbColor_off = new CComboBox("color_off",$color_off);
		$cmbColor_on = new CComboBox("color_on",$color_on);
		foreach(array('Black','Blue','Cyan','Dark Blue','Dark Green',
			'Dark Red','Dark Yellow','Green','Red','White','Yellow') as $value)
		{
			$cmbColor_off->AddItem($value, $value);
			$cmbColor_on->AddItem($value, $value);
		}
/* END preparation */

		$frmCnct->AddRow("Element 1",$cmbElements1);
		$frmCnct->AddRow("Element 2",$cmbElements2);

		$frmCnct->AddVar('triggerid',$triggerid);

		if($triggerid > 0)
			$trigger = expand_trigger_description($triggerid);
		else
			$trigger = "";

		$txtTrigger = new CTextBox('trigger',$trigger,60);
		$txtTrigger->SetReadonly('yes');

		$btnSelect = new CButton('btn1',S_SELECT,
			"return PopUp('popup.php?dstfrm=".$frmCnct->GetName().
			"&dstfld1=triggerid&dstfld2=trigger&srctbl=triggers&srcfld1=triggerid&srcfld2=description','new_win',".
			"'width=600,height=450,resizable=1,scrollbars=1');");
		$btnSelect->SetAccessKey('T');
		$frmCnct->AddRow("Link status indicator",array($txtTrigger, $btnSelect));

		$frmCnct->AddRow("Type (OFF)",$cmbType_off);
		$frmCnct->AddRow("Color (OFF)",$cmbColor_off);

		$frmCnct->AddRow("Type (ON)",$cmbType_on);
		$frmCnct->AddRow("Color (ON)",$cmbColor_on);

		$frmCnct->AddItemToBottomRow(new CButton("save_link",S_SAVE));
		if(isset($_REQUEST["linkid"]))
		{
			$frmCnct->AddItemToBottomRow(SPACE);
			$frmCnct->AddItemToBottomRow(new CButtonDelete("Delete link?",
				url_param("linkid").url_param("sysmapid")));
		}
		$frmCnct->AddItemToBottomRow(SPACE);
		$frmCnct->AddItemToBottomRow(new CButtonCancel(url_param("sysmapid")));
		
		$frmCnct->Show();
	}
?>
