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
	function	insert_template_form($hostid, $hosttemplateid)
	{
		if(isset($hosttemplateid))
		{
			$result=DBselect("select * from hosts_templates  where hosttemplateid=$hosttemplateid");

			$row=DBfetch($result);
	
			$hostid=$row["hostid"];
			$host=get_host_by_hostid($hostid);
			$templateid=$row["templateid"];
			$template=get_host_by_hostid($templateid);
			$items=$row["items"];
			$triggers=$row["triggers"];
			$graphs=$row["graphs"];
		}
		else
		{
			$hostid=0;
			$templateid=0;
			$items=7;
			$triggers=7;
			$graphs=7;
		}
# create form
		$frmTemplate = new CFormTable(S_TEMPLATE,'hosts.php');
		$frmTemplate->SetHelp('web.hosts.php');
# init vars
		$frmTemplate->AddVar('config',$_REQUEST["config"]);
		if($hostid!=0)			$frmTemplate->AddVar('hostid',$hostid);
		if(isset($hosttemplateid))	$frmTemplate->AddVar('hosttemplateid',$_REQUEST["hosttemplateid"]);
# init rows

		$cmbTemplate = new CComboBox('templateid',$templateid);

	        $hosts=DBselect("select hostid,host from hosts order by host");
		while($host=DBfetch($hosts))
			$cmbTemplate->AddItem($host["hostid"],$host["host"]);

		$frmTemplate->AddRow(S_TEMPLATE,$cmbTemplate);

		$frmTemplate->AddRow(S_ITEMS,array(
			new CCheckBox('items_add',	1 & $items,	S_ADD),
			new CCheckBox('items_update',	2 & $items,	S_UPDATE),
			new CCheckBox('items_delete',	4 & $items,	S_DELETE)
		));

		$frmTemplate->AddRow(S_TRIGGERS,array(
			new CCheckBox('triggers_add',	1 & $triggers,	S_ADD),
			new CCheckBox('triggers_update',2 & $triggers,	S_UPDATE),
			new CCheckBox('triggers_delete',4 & $triggers,	S_DELETE),
		));

		$frmTemplate->AddRow(S_GRAPHS,array(
			new CCheckBox('graphs_add',	1 & $graphs,	S_ADD),
			new CCheckBox('graphs_update',	2 & $graphs,	S_UPDATE),
			new CCheckBox('graphs_delete',	4 & $graphs,	S_DELETE),
		));

		$frmTemplate->AddItemToBottomRow(new CButton('register','add linkage'));
		if(isset($hosttemplateid))
		{
			$frmTemplate->AddItemToBottomRow(SPACE);
			$frmTemplate->AddItemToBottomRow(new CButton('register','update linkage'));
			$frmTemplate->AddItemToBottomRow(SPACE);
			$frmTemplate->AddItemToBottomRow(new CButton('register','delete linkage',"return Confirm('Delete selected linkage?');"));
		}

		$frmTemplate->Show();
	}

	# Insert form for User
	function	insert_user_form($userid,$profile=0)
	{
		$frm_title = S_USER;
		if(isset($userid)&&$_REQUEST["form"]!=1)
		{
			$user=get_user_by_userid($userid);
			$frm_title = S_USER." \"".$user["alias"]."\"";
		}
		if(isset($userid)&&$_REQUEST["form"]!=1)
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
			$frmUser->AddItemToBottomRow(new CButtonDelete(
				"Delete selected user?",url_param("config").url_param("userid")));
		}
		$frmUser->AddItemToBottomRow(SPACE);
		$frmUser->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmUser->Show();
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

		if(isset($usrgrpid)&&$_REQUEST["form"]!=1)
		{
			$name	= $usrgrp["name"];
		}
		else
		{
			$name	= get_request("name","");
		}

		$frmUserG = new CFormTable($frm_title,"users.php");
		$frmUserG->SetHelp("web.users.groups.php");
		$frmUserG->AddVar("config",get_request("config",2));
		if(isset($usrgrpid))
		{
			$frmUserG->AddVar("usrgrpid",$usrgrpid);
		}
		$frmUserG->AddRow(S_GROUP_NAME,new CTextBox("name",$name,30));

		$form_row = array();
		$users=DBselect("select distinct userid,alias from users order by alias");
		while($user=DBfetch($users))
		{
			if(isset($_REQUEST["usrgrpid"]))
			{
				$sql="select count(*) as count from users_groups where userid=".
					$user["userid"]." and usrgrpid=".$_REQUEST["usrgrpid"];
				$result=DBselect($sql);
				$res_row=DBfetch($result);
				array_push($form_row,
					new CCheckBox($user["userid"],$res_row["count"], $user["alias"]),
					BR);
			}
			else
			{
				array_push($form_row,
					new CCheckBox($user["userid"],
						isset($_REQUEST[$user["userid"]]),$user["alias"]),
					BR);
			}
		}
		$frmUserG->AddRow(S_USERS,$form_row);
	
		$frmUserG->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["usrgrpid"]))
		{
			$frmUserG->AddItemToBottomRow(SPACE);
			$frmUserG->AddItemToBottomRow(new CButtonDelete(
				"Delete selected group?",url_param("config").url_param("usrgrpid")));
		}
		$frmUserG->AddItemToBottomRow(SPACE);
		$frmUserG->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmUserG->Show();
	}


	# Insert form for Item information
	function	insert_item_form()
	{
		global  $_REQUEST;

		$description=@iif(isset($_REQUEST["description"]),$_REQUEST["description"],"");
		$key=@iif(isset($_REQUEST["key"]),$_REQUEST["key"],"");
		$host=@iif(isset($_REQUEST["host"]),$_REQUEST["host"],"");
		$port=@iif(isset($_REQUEST["port"]),$_REQUEST["port"],10050);
		$delay=@iif(isset($_REQUEST["delay"]),$_REQUEST["delay"],30);
		$history=@iif(isset($_REQUEST["history"]),$_REQUEST["history"],90);
		$trends=@iif(isset($_REQUEST["trends"]),$_REQUEST["trends"],365);
		$status=@iif(isset($_REQUEST["status"]),$_REQUEST["status"],0);
		$type=@iif(isset($_REQUEST["type"]),$_REQUEST["type"],0);
		$snmp_community=@iif(isset($_REQUEST["snmp_community"]),$_REQUEST["snmp_community"],"public");
		$snmp_oid=@iif(isset($_REQUEST["snmp_oid"]),$_REQUEST["snmp_oid"],"interfaces.ifTable.ifEntry.ifInOctets.1");
		$value_type=@iif(isset($_REQUEST["value_type"]),$_REQUEST["value_type"],0);
		$trapper_hosts=@iif(isset($_REQUEST["trapper_hosts"]),$_REQUEST["trapper_hosts"],"");
		$snmp_port=@iif(isset($_REQUEST["snmp_port"]),$_REQUEST["snmp_port"],161);
		$units=@iif(isset($_REQUEST["units"]),$_REQUEST["units"],'');
		$multiplier=@iif(isset($_REQUEST["multiplier"]),$_REQUEST["multiplier"],0);
		$hostid=@iif(isset($_REQUEST["hostid"]),$_REQUEST["hostid"],0);
		$delta=@iif(isset($_REQUEST["delta"]),$_REQUEST["delta"],0);

		$snmpv3_securityname=@iif(isset($_REQUEST["snmpv3_securityname"]),$_REQUEST["snmpv3_securityname"],"");
		$snmpv3_securitylevel=@iif(isset($_REQUEST["snmpv3_securitylevel"]),$_REQUEST["snmpv3_securitylevel"],0);
		$snmpv3_authpassphrase=@iif(isset($_REQUEST["snmpv3_authpassphrase"]),$_REQUEST["snmpv3_authpassphrase"],"");
		$snmpv3_privpassphrase=@iif(isset($_REQUEST["snmpv3_privpassphrase"]),$_REQUEST["snmpv3_privpassphrase"],"")
;
		$formula=@iif(isset($_REQUEST["formula"]),$_REQUEST["formula"],"1");
		$logtimefmt=@iif(isset($_REQUEST["logtimefmt"]),$_REQUEST["logtimefmt"],"");
		$groupid=@iif(isset($_REQUEST["groupid"]),$_REQUEST["groupid"],0);

		if(isset($_REQUEST["register"])&&($_REQUEST["register"] == "change"))
		{
			$result=DBselect("select i.description, i.key_, h.host, h.port, i.delay, i.history, i.status, i.type, i.snmp_community,i.snmp_oid,i.value_type,i.trapper_hosts,i.snmp_port,i.units,i.multiplier,h.hostid,i.delta,i.trends,i.snmpv3_securityname,i.snmpv3_securitylevel,i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.formula,i.logtimefmt from items i,hosts h where i.itemid=".$_REQUEST["itemid"]." and h.hostid=i.hostid");
			$row=DBfetch($result);
		
			$description=$row["description"];
			$key=$row["key_"];
			$host=$row["host"];
			$port=$row["port"];
			$delay=$row["delay"];
			$history=$row["history"];
			$status=$row["status"];
			$type=iif(isset($_REQUEST["type"]),isset($_REQUEST["type"]),$row["type"]);
			$snmp_community=$row["snmp_community"];
			$snmp_oid=$row["snmp_oid"];
			$value_type=$row["value_type"];
			$trapper_hosts=$row["trapper_hosts"];
			$snmp_port=$row["snmp_port"];
			$units=$row["units"];
			$multiplier=$row["multiplier"];
			$hostid=$row["hostid"];
			$delta=$row["delta"];
			$trends=$row["trends"];

			$snmpv3_securityname=$row["snmpv3_securityname"];
			$snmpv3_securitylevel=$row["snmpv3_securitylevel"];
			$snmpv3_authpassphrase=$row["snmpv3_authpassphrase"];
			$snmpv3_privpassphrase=$row["snmpv3_privpassphrase"];

			$formula=$row["formula"];
			$logtimefmt=$row["logtimefmt"];
		}

		$frmItem = new CFormTable(S_ITEM,"items.php#form");
		$frmItem->SetHelp("web.items.item.php");

		$frmItem->AddVar("hostid",$hostid);
		if(isset($_REQUEST["itemid"]))
			$frmItem->AddVar("itemid",$_REQUEST["itemid"]);

		$frmItem->AddRow(S_DESCRIPTION, new CTextBox("description",$description,40));
		$frmItem->AddRow(S_HOST, array(
			new CTextBox("host",$host,30,NULL,'yes'),
			new CButton("btn1","Select","window.open('popup.php?form=item&field1=hostid&field2=host','new_win','width=450,height=450,resizable=1,scrollbars=1');","T")
		));

/*		
		$cmbHosts = new CComboBox("hostid",$hostid);
	        $hosts=DBselect("select hostid,host from hosts where status not in (".HOST_STATUS_DELETED.")order by host");
		while($host=DBfetch($hosts))
	        {
			$cmbHosts->AddItem($host["hostid"],$host["host"]);
	        }
		$frmItem->AddRow(S_HOST, $cmbHosts);
*/

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
			$frmItem->AddRow(S_SNMP_OID, new CTextBox("snmp_oid",$snmp_oid,40));
			$frmItem->AddRow(S_SNMPV3_SECURITY_NAME, new CTextBox("snmpv3_securityname",$snmpv3_securityname,64));

			$cmbSecLevel = new CComboBox("snmpv3_securitylevel",$snmpv3_securitylevel);
			$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,"NoAuthPriv");
			$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,"AuthNoPriv");
			$cmbSecLevel->AddItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,"AuthPriv");
			$frmItem->AddRow(S_SNMPV3_SECURITY_LEVEL, $cmbSecLevel);

			$frmItem->AddRow(S_SNMPV3_AUTH_PASSPHRASE, new CTextBox("snmpv3_authpassphrase",$snmpv3_authpassphrase,64));
			$frmItem->AddRow(S_SNMPV3_PRIV_PASSPHRASE, new CTextBox("snmpv3_privpassphrase",$snmpv3_privpassphrase,64));
			$frmItem->AddRow(S_SNMP_PORT, new CTextBox("snmp_port",$snmp_port,5));
			$frmItem->AddVar("snmp_community",$snmp_community);
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

		if($multiplier == S_CUSTOM_MULTIPLIER)
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

		$frmRow = array(
			new CButton("register","add"),
			SPACE,
			new CButton("register","add to all hosts","return Confirm('Add item to all hosts?');")
		);
		if(isset($_REQUEST["itemid"]))
		{
			array_push($frmRow,
				SPACE,
				new CButton("register","update"),
				SPACE,
				new CButton("register","delete","return Confirm('Delete selected item?');")
			);
		}
		$frmItem->AddSpanRow($frmRow,"form_row_last");

	        $cmbGroups = new CComboBox("groupid",$groupid,"submit()");		

	        $groups=DBselect("select groupid,name from groups order by name");
	        while($group=DBfetch($groups))
	        {
// Check if at least one host with read permission exists for this group
	                $hosts=DBselect("select h.hostid,h.host from hosts h,hosts_groups hg where hg.groupid=".$group["groupid"]." and hg.hostid=h.hostid and h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host order by h.host");
	                while($host=DBfetch($hosts))
	                {
	                        if(check_right("Host","U",$host["hostid"]))
	                        {
					$cmbGroups->AddItem($group["groupid"],$group["name"]);
	                                break;
	                        }
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


	# Insert form for User permissions
	function	insert_permissions_form($userid)
	{
		$frmPerm = new CFormTable("New permission","users.php");
		$frmPerm->SetHelp("web.users.users.php");

		if(isset($userid))
		{
			$frmPerm->AddVar("userid",$userid);
		}
		
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

	function	insert_login_form()
	{
		$frmLogin = new CFormTable('Login','index.php');
		$frmLogin->SetHelp('web.index.login');
		$frmLogin->AddRow('Login name', new CTextBox('name'));
		$frmLogin->AddRow('Password', new CPassBox('password'));
		$frmLogin->AddItemToBottomRow(new CButton('register','Enter'));
		$frmLogin->Show();
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
	function	insert_trigger_form($hostid,$triggerid)
	{
		$frmTrig = new CFormTable(S_TRIGGER,"triggers.php");
		$frmTrig->SetHelp("web.triggers.trigger.php");

		$dep_el=array();
		$i=1;
		for($i=1; $i<=1000; $i++)
		{
			if(!isset($_REQUEST["dependence$i"])) continue;
			array_push($dep_el, 
				new CCheckBox(
					$_REQUEST["dependence$i"],
					'no',
					expand_trigger_description($_REQUEST["dependence$i"])
				),
				BR
			);
			$frmTrig->AddVar("dependence$i", $_REQUEST["dependence$i"]);
		}

		if(isset($triggerid))
		{
			$trigger=get_trigger_by_triggerid($triggerid);
	
			$expression=explode_exp($trigger["expression"],0);
			$description=htmlspecialchars(stripslashes($trigger["description"]));
			$priority=$trigger["priority"];
			$status=$trigger["status"];
			$comments=$trigger["comments"];
			$url=$trigger["url"];

			$sql="select t.triggerid,t.description from triggers t,trigger_depends d where t.triggerid=d.triggerid_up and d.triggerid_down=$triggerid";
			$trigs=DBselect($sql);
//			$i=1; // CONTINUE ITERATION !!! DONT UNHIDE THIS ROW!!!
			while($trig=DBfetch($trigs))
			{
				array_push($dep_el, 
					new CCheckBox(
						$trig["triggerid"],
						'no',
						expand_trigger_description($trig["triggerid"])
					),
					BR
				);
				$frmTrig->AddVar("dependence$i", $trig["triggerid"]);
				$i++;
			}
		}
		else
		{
			$expression="";
			$description="";
			$priority=0;
			$status=0;
			$comments="";
			$url="";
		}

		if(isset($hostid))
		{
			$frmTrig->AddVar("hostid",$hostid);
		}
		if(isset($triggerid))
		{
			$frmTrig->AddVar("triggerid",$triggerid);
		}
		$frmTrig->AddRow(S_NAME, new CTextBox("description",$description,70));
		$frmTrig->AddRow(S_EXPRESSION,new CTextBox("expression",$expression,70));

		if(count($dep_el)==0)
			array_push($dep_el, "No dependences defined");
		else
			array_push($dep_el, new CButton('register','delete selected'));
		$frmTrig->AddRow("The trigger depends on",$dep_el);

		$cmbDepID = new CComboBox("depid");
		if(isset($triggerid))
			$sql="select t.triggerid,t.description from triggers t where t.triggerid!=$triggerid order by t.description";
		else
			$sql="select t.triggerid,t.description from triggers t order by t.description";

		$trigs=DBselect($sql);
		while($trig=DBfetch($trigs))
		{
			$cmbDepID->AddItem($trig["triggerid"],expand_trigger_description($trig["triggerid"]));
		}
		$frmTrig->AddRow("New dependency",array($cmbDepID,BR,new CButton("register","add dependency")));

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
		if(isset($triggerid))
		{
			$frmTrig->AddItemToBottomRow(SPACE);
			$frmTrig->AddItemToBottomRow(new CButton("delete",S_DELETE,
				"return Confirm('Delete trigger?');"));
		}
		$frmTrig->AddItemToBottomRow(SPACE);
		$frmTrig->AddItemToBottomRow(new CButton("cancel",S_CANCEL));
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

	# Insert autoregistration form
	function	insert_autoregistration_form($id)
	{
		if(isset($id))
		{
			$result=DBselect("select * from autoreg  where id=$id");

			$row=DBfetch($result);
	
			$pattern=$row["pattern"];
			$priority=$row["priority"];
			$hostid=$row["hostid"];
			$h=get_host_by_hostid($hostid);
			$host=$h["host"];
		}
		else
		{
			$pattern="*";
			$priority=10;
			$hostid=0;
			$host="";
		}

		$col=0;

		$frmAutoReg = new CFormTable(S_AUTOREGISTRATION,"config.php");
		$frmAutoReg->SetHelp("web.autoregistration.php");
		$frmAutoReg->AddVar("config",$_REQUEST["config"]);
		if(isset($id))
		{
			$frmAutoReg->AddVar("id",$id);
		}
		$frmAutoReg->AddRow(S_PATTERN,new CTextBox("pattern",$pattern,64));
		$frmAutoReg->AddRow(S_PRIORITY,new CTextBox("priority",$priority,4));
		$frmAutoReg->AddRow(S_HOST,array(
			new CTextBox("host",$host,32,NULL,'yes'),
			new CButton("btn1","Select",
				"window.open('popup.php?form=auto&field1=hostid&field2=host','new_win','width=450,height=450,resizable=1,scrollbars=1');",
				'T')
			));
		$frmAutoReg->AddVar("hostid",$hostid);
		
		$frmAutoReg->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($id))
		{
			$frmAutoReg->AddItemToBottomRow(SPACE);
			$frmAutoReg->AddItemToBottomRow(new CButton("delete",S_DELETE,
				"return Confirm('Delete selected autoregistration rule?');"));
		}
		$frmAutoReg->AddItemToBottomRow(SPACE);
		$frmAutoReg->AddItemToBottomRow(new CButton("cancel",S_CANCEL));
		$frmAutoReg->Show();
	}

	function	insert_action_form()
	{
		global  $_REQUEST;

		$uid=NULL;

		$frmAction = new CFormTable(S_ACTION,'actionconf.php');
		$frmAction->SetHelp('web.actions.action');

		$conditiontype=@iif(isset($_REQUEST["conditiontype"]),$_REQUEST["conditiontype"],0);

		if(isset($_REQUEST["actionid"]))
		{
			$action=get_action_by_actionid($_REQUEST["actionid"]);
			$frmAction->AddVar('actionid',$_REQUEST["actionid"]);

			$actionid=$action["actionid"];
			$actiontype=$action["actiontype"];
			$source=$action["source"];
			$delay=$action["delay"];
			$uid=$action["userid"];
			// Otherwise symbols like ",' will not be shown
			$subject=htmlspecialchars($action["subject"]);
			$message=$action["message"];
			$recipient=@iif(isset($_REQUEST["recipient"]),$_REQUEST["recipient"],$action["recipient"]);
			$maxrepeats=$action["maxrepeats"];
			$repeatdelay=$action["repeatdelay"];
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
			$sql="select conditiontype, operator, value from conditions where actionid=".$_REQUEST["actionid"]." order by conditiontype";
			$result=DBselect($sql);
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
			$source=@iif(isset($_REQUEST["source"]),$_REQUEST["source"],0);
			$actiontype=@iif(isset($_REQUEST["actiontype"]),$_REQUEST["actiontype"],0);

			$delay=@iif(isset($_REQUEST["delay"]),$_REQUEST["delay"],30);
			$subject=@iif(isset($_REQUEST["subject"]),$_REQUEST["subject"],"{TRIGGER.NAME}: {STATUS}");
			$message=@iif(isset($_REQUEST["message"]),$_REQUEST["message"],"{TRIGGER.NAME}: {STATUS}");
			$scope=@iif(isset($_REQUEST["scope"]),$_REQUEST["scope"],0);
			$recipient=@iif(isset($_REQUEST["recipient"]),$_REQUEST["recipient"],RECIPIENT_TYPE_GROUP);
			$severity=@iif(isset($_REQUEST["severity"]),$_REQUEST["severity"],0);
			$maxrepeats=@iif(isset($_REQUEST["maxrepeats"]),$_REQUEST["maxrepeats"],0);
			$repeatdelay=@iif(isset($_REQUEST["repeatdelay"]),$_REQUEST["repeatdelay"],600);
			$repeat=@iif(isset($_REQUEST["repeat"]),$_REQUEST["repeat"],0);

			if($recipient==RECIPIENT_TYPE_GROUP)
				$uid=@iif(isset($_REQUEST["usrgrpid"]),$_REQUEST["usrgrpid"],NULL);
			else
				$uid=@iif(isset($_REQUEST["userid"]),$_REQUEST["userid"],NULL);
		}


// prepare condition list
		$cond_el=array();
		for($i=1; $i<=1000; $i++)
		{
			if(!isset($_REQUEST["conditiontype$i"])) continue;
			array_push($cond_el, 
				new CCheckBox(
					"conditionchecked$i",
					'no',
					get_condition_desc(
						$_REQUEST["conditiontype$i"],
						$_REQUEST["conditionop$i"],
						$_REQUEST["conditionvalue$i"]
					)
				),
				BR
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

			$btnSelect = new CButton('btn1','Select',"window.open('popup.php?form=action&field1=value&field2=host','new_win','width=450,height=450,resizable=1,scrollbars=1');");
			$btnSelect->SetAccessKey('T');

			array_push($rowCondition, $txtCondVal, $btnSelect);
//			$cmbCondVal = new CComboBox('value');
//			$hosts = DBselect("select hostid,host from hosts order by host");
//			while($host = DBfetch($hosts))
//			{
//				$cmbCondVal->AddItem($host["hostid"],$host["host"]);
//			}
		}
		else if($conditiontype == CONDITION_TYPE_TRIGGER)
		{
			$cmbCondVal = new CComboBox('value');
			$triggers = DBselect("select distinct h.host,t.triggerid,t.description from triggers t, functions f,items i, hosts h where t.triggerid=f.triggerid and f.itemid=i.itemid and h.hostid=i.hostid order by h.host");
			while($trigger = DBfetch($triggers))
			{
				$cmbCondVal->AddItem($trigger["triggerid"],$trigger["host"].":&nbsp;".$trigger["description"]);
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

		if(isset($actionid))
		{
			$frmAction->AddItemToBottomRow(new CButton('register','update'));
			$frmAction->AddItemToBottomRow(SPACE);
			$frmAction->AddItemToBottomRow(new CButton('register','delete','return Confirm("Delete selected action?");'));
		} else {
			$frmAction->AddItemToBottomRow(new CButton('register','add'));
		}
		$frmAction->AddItemToBottomRow(SPACE);
		$frmAction->AddItemToBottomRow(new CButton('register','cancel'));
	
		$frmAction->Show();
	}

	function	insert_media_type_form()
	{
		$type=@iif(isset($_REQUEST["type"]),$_REQUEST["type"],0);
		$description=@iif(isset($_REQUEST["description"]),$_REQUEST["description"],"");
		$smtp_server=@iif(isset($_REQUEST["smtp_server"]),$_REQUEST["smtp_server"],"localhost");
		$smtp_helo=@iif(isset($_REQUEST["smtp_helo"]),$_REQUEST["smtp_helo"],"localhost");
		$smtp_email=@iif(isset($_REQUEST["smtp_email"]),$_REQUEST["smtp_email"],"zabbix@localhost");
		$exec_path=@iif(isset($_REQUEST["exec_path"]),$_REQUEST["exec_path"],"");

		if(isset($_REQUEST["mediatypeid"])&&$_REQUEST["form"]!=1)
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
		$frmMeadia->SetHelp("web.config.medias");

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
			$frmMeadia->AddItemToBottomRow(new CButton("delete",S_DELETE,"return Confirm('".S_DELETE_SELECTED_MEDIA."');"));
		}
		$frmMeadia->AddItemToBottomRow(SPACE);
		$frmMeadia->AddItemToBottomRow(new CButton("cancel",S_CANCEL));
		$frmMeadia->Show();
	}

function	insert_image_form()
{
		if(!isset($_REQUEST["imageid"]))
		{
			$name="";
			$imagetype=1;
		}
		else
		{
			$result=DBselect("select imageid,imagetype,name,image from images where imageid=".$_REQUEST["imageid"]);
			$row=DBfetch($result);
			$name=$row["name"];
			$imagetype=$row["imagetype"];
			$imageid=$row["imageid"];
		}

		$frmImages = new CFormTable(S_IMAGE,"config.php","post","multipart/form-data");
		$frmImages->SetHelp("web.config.images.php");
		$frmImages->AddVar("MAX_FILE_SIZE",(1024*1024));	
		$frmImages->AddVar("config",3);	
		if(isset($imageid))
		{
			$frmImages->AddVar("imageid",$imageid);	
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
			$frmImages->AddItemToBottomRow(new CButton("delete",S_DELETE,"return Confirm('".S_DELETE_SELECTED_IMAGE."');"));
		}
		$frmImages->AddItemToBottomRow(SPACE);
		$frmImages->AddItemToBottomRow(new CButton("cancel",S_CANCEL));
		$frmImages->Show();
	}

	function insert_screen_form()
	{
		global $_REQUEST;

		if(isset($_REQUEST["screenid"]))
		{
			$result=DBselect("select screenid,name,cols,rows from screens g where screenid=".$_REQUEST["screenid"]);
			$row=DBfetch($result);
			$name=$row["name"];
			$cols=$row["cols"];
			$rows=$row["rows"];
		}
		else
		{
			$name="";
			$cols=1;
			$rows=1;
		}
		$frmScr = new CFormTable(S_SCREEN,"screenconf.php");
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
			$frmScr->AddItemToBottomRow(new CButton("delete",S_DELETE,"return Confirm('".S_DELETE_SCREEN_Q."');"));
		}
		$frmScr->AddItemToBottomRow(SPACE);
		$frmScr->AddItemToBottomRow(new CButton("cancel",S_CANCEL));
		$frmScr->Show();	
	}

	function	insert_media_form()
	{
		global $_REQUEST;

		if(isset($_REQUEST["mediaid"]))
		{
			$media=get_media_by_mediaid($_REQUEST["mediaid"]);
			$severity=$media["severity"];
			$sendto=$media["sendto"];
			$active=$media["active"];
			$mediatypeid=$media["mediatypeid"];
			$period=$media["period"];
		}
		else
		{
			$sendto="";
			$severity=63;
			$mediatypeid=-1;
			$active=0;
			$period="1-7,00:00-23:59";
		}

		$frmMedia = new CFormTable(S_NEW_MEDIA,"media.php");
		$frmMedia->SetHelp("web.media.media.php");

		$frmMedia->AddVar("userid",$_REQUEST["userid"]);
		if(isset($_REQUEST["mediaid"]))
		{
			$frmMedia->AddVar("mediaid",$_REQUEST["mediaid"]);
		}

		$cmbType = new CComboBox("mediatypeid",$mediatypeid);
		$sql="select mediatypeid,description from media_type order by type";
		$types=DBselect($sql);
		while($type=DBfetch($types))
		{
			$cmbType->AddItem($type["mediatypeid"],$type["description"]);
		}
		$frmMedia->AddRow(S_TYPE,$cmbType);

		$frmMedia->AddRow(S_SEND_TO,new CTextBox("sendto",$sendto,20));	
		$frmMedia->AddRow(S_WHEN_ACTIVE,new CTextBox("period",$period,48));	
	
		$frm_row = array();
		array_push($frm_row, new CCheckBox(0,	1 & $severity, S_NOT_CLASSIFIED),	BR);
		array_push($frm_row, new CCheckBox(1,	2 & $severity, S_INFORMATION),	BR);
		array_push($frm_row, new CCheckBox(2,	4 & $severity, S_WARNING),	BR);
		array_push($frm_row, new CCheckBox(3,	8 & $severity, S_AVERAGE),	BR);
		array_push($frm_row, new CCheckBox(4,	16 & $severity, S_HIGH),	BR);
		array_push($frm_row, new CCheckBox(5,	32 & $severity, S_DISASTER),	BR);
		$frmMedia->AddRow(S_USE_IF_SEVERITY,$frm_row);

		$cmbStat = new CComboBox("active",$active);
		$cmbStat->AddItem(0,S_ENABLED);
		$cmbStat->AddItem(1,S_DISABLED);
		$frmMedia->AddRow("Status",$cmbStat);
	
		$frmMedia->AddItemToBottomRow(new CButton("save", S_SAVE));
		if(isset($_REQUEST["mediaid"]))
		{
			$frmMedia->AddItemToBottomRow(SPACE);
			$frmMedia->AddItemToBottomRow(new CButton("delete",S_DELETE,"return Confirm('".S_DELETE_SELECTED_MEDIA_Q."');"));
		}
		$frmMedia->AddItemToBottomRow(SPACE);
		$frmMedia->AddItemToBottomRow(new CButton("cancel",S_CANCEL));
		$frmMedia->Show();
	}

	function	insert_host_form()
	{

		global $_REQUEST;

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

		if(isset($_REQUEST["hostid"]) && $_REQUEST["form"]!=1)
		{

			$host	= $db_host["host"];
			$port	= $db_host["port"];
			$status	= $db_host["status"];
			$useip	= $db_host["useip"]==1 ? 'yes' : 'no';
			$ip	= $db_host["ip"];
// add groups
			$db_groups=DBselect("select groupid from hosts_groups where hostid=".$_REQUEST["hostid"]);
			while($db_group=DBfetch($db_groups))	$_REQUEST[$db_group["groupid"]]="yes";
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

		$frmHost = new CFormTable($frm_title,"hosts.php#form");
		$frmHost->SetHelp("web.hosts.host.php");
		$frmHost->AddVar("config",get_request("config",0));

		if(isset($_REQUEST["hostid"]))		$frmHost->AddVar("hostid",$_REQUEST["hostid"]);
		if(isset($_REQUEST["groupid"]))		$frmHost->AddVar("groupid",$_REQUEST["groupid"]);
		
		$frmHost->AddRow(S_HOST,new CTextBox("host",$host,20));

		$frm_row = array();
		$db_groups=DBselect("select distinct groupid,name from groups order by name");
		while($db_group=DBfetch($db_groups))
		{
			$selected = isset($_REQUEST[$db_group["groupid"]]) ? 'yes' : 'no';
			array_push($frm_row,new CCheckBox($db_group["groupid"],$selected, $db_group["name"]),BR);
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
			$frmHost->AddVar("notes	",	$notes);
		}

		$frmHost->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["hostid"]))
		{
//			$frmHost->AddItemToBottomRow(SPACE);
//			$frmHost->AddItemToBottomRow(new CButton("register","add items from template"));
			$frmHost->AddItemToBottomRow(SPACE);
			$frmHost->AddItemToBottomRow(
				new CButtonDelete(S_DELETE_SELECTED_HOST_Q,
					url_param("config").url_param("hostid")
				)
			);
		}
		$frmHost->AddItemToBottomRow(SPACE);
		$frmHost->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmHost->Show();
	}

	# Insert form for Host Groups
	function	insert_hostgroups_form($groupid)
	{
		global  $_REQUEST;

		$frm_title = S_HOST_GROUP;
		if(isset($groupid))
		{
			$groupid=get_group_by_groupid($groupid);
			$frm_title = S_HOST_GROUP." \"".$groupid["name"]."\"";
			if($_REQUEST["form"]!=1)
				$name=$groupid["name"];
			else
				$name = get_request("name","");
		}
		else
		{
			$name=get_request("name","");
		}
		$frmHostG = new CFormTable($frm_title,"hosts.php");
		$frmHostG->SetHelp("web.hosts.group.php");
		$frmHostG->AddVar("config",get_request("config",1));
		if(isset($_REQUEST["groupid"]))
		{
			$frmHostG->AddVar("groupid",$_REQUEST["groupid"]);
		}

		$frmHostG->AddRow(S_GROUP_NAME,new CTextBox("name",$name,30));

		$cmbHosts = new CListBox("hosts[]",5);
		$hosts=DBselect("select distinct hostid,host from hosts where status<>".HOST_STATUS_DELETED." order by host");
		while($host=DBfetch($hosts))
		{
			if(isset($_REQUEST["groupid"]))
			{
				$result=DBselect("select count(*) as count from hosts_groups".
					" where hostid=".$host["hostid"]." and groupid=".$_REQUEST["groupid"]);
				$res_row=DBfetch($result);
				$cmbHosts->AddItem($host["hostid"],$host["host"], 
					($res_row["count"]==0) ? 'no' : 'yes');
			}
			else
			{
				$cmbHosts->AddItem($host["hostid"],$host["host"]);
			}
		}
		$frmHostG->AddRow(S_HOSTS,$cmbHosts);

		$frmHostG->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["groupid"]))
		{
			$frmHostG->AddItemToBottomRow(SPACE);
			$frmHostG->AddItemToBottomRow(
				new CButtonDelete("Delete selected group?",
					url_param("config").url_param("groupid")
				)
			);
		}
		$frmHostG->AddItemToBottomRow(SPACE);
		$frmHostG->AddItemToBottomRow(new CButtonCancel(url_param("config")));
		$frmHostG->Show();
	}

/*
	# Insert host profile form
	function	insert_host_profile_form($hostid,$readonly=0)
	{
		$selected=0;

		if(isset($hostid))
		{
			$result=DBselect("select * from hosts_profiles where hostid=$hostid");

			if(DBnum_rows($result)==1)
			{
				$row=DBfetch($result);

				$selected=1;
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
			}
		}
		if($selected==0)
		{
			$devicetype="";
			$name="";
			$os="";
			$serialno="";
			$tag="";
			$macaddress="";
			$hardware="";
			$software="";
			$contact="";
			$location="";
			$notes="";
		}

		$frmHostP = new CFormTable(S_HOST_PROFILE,"hosts.php");
		$frmHostP->SetHelp("web.host_profile.php");

		if(isset($_REQUEST["config"]))
		{
			$frmHostP->AddVar("config",$_REQUEST["config"]);
		}
		$frmHostP->AddVar("hostid",$hostid);

		$frmHostP->AddRow(S_DEVICE_TYPE,new CTextBox("devicetype",$devicetype,61));
		$frmHostP->AddRow(S_NAME,new CTextBox("name",$name,61));
		$frmHostP->AddRow(S_OS,new CTextBox("os",$os,61));
		$frmHostP->AddRow(S_SERIALNO,new CTextBox("serialno",$serialno,61));
		$frmHostP->AddRow(S_TAG,new CTextBox("tag",$tag,61));
		$frmHostP->AddRow(S_MACADDRESS,new CTextBox("macaddress",$macaddress,61));
		$frmHostP->AddRow(S_HARDWARE,new CTextArea("hardware",$hardware,60,4));
		$frmHostP->AddRow(S_SOFTWARE,new CTextArea("software",$software,60,4));
		$frmHostP->AddRow(S_CONTACT,new CTextArea("contact",$contact,60,4));
		$frmHostP->AddRow(S_LOCATION,new CTextArea("location",$location,60,4));
		$frmHostP->AddRow(S_NOTES,new CTextArea("notes",$notes,60,4));

		if($readonly==0)
		{
			$frmHostP->AddItemToBottomRow(new CButton("register","add profile"));
			if(isset($hostid))
			{
				$frmHostP->AddItemToBottomRow(SPACE);
				$frmHostP->AddItemToBottomRow(new CButton("register","update profile"));
				$frmHostP->AddItemToBottomRow(SPACE);
				$frmHostP->AddItemToBottomRow(new CButton("register","delete profile",
						"return Confirm('Delete selected profile?');"));
			}
		}
		else
		{
			$frmHostP->AddItemToBottomRow(SPACE);
		}
		$frmHostP->Show();
	}
*/
?>
