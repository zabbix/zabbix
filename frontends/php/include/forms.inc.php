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
			new CCheckBox('items_add',	S_ADD,		(1 & $items) ? "yes": "no"),
			new CCheckBox('items_update',	S_UPDATE,	(2 & $items) ? "yes": "no"),
			new CCheckBox('items_delete',	S_DELETE,	(4 & $items) ? "yes": "no")
		));

		$frmTemplate->AddRow(S_TRIGGERS,array(
			new CCheckBox('triggers_add',	S_ADD,		(1 & $triggers) ? "yes": "no"),
			new CCheckBox('triggers_update',S_UPDATE,	(2 & $triggers) ? "yes": "no"),
			new CCheckBox('triggers_delete',S_DELETE,	(4 & $triggers) ? "yes": "no")
		));

		$frmTemplate->AddRow(S_GRAPHS,array(
			new CCheckBox('graphs_add',	S_ADD,		(1 & $graphs) ? "yes": "no"),
			new CCheckBox('graphs_update',	S_UPDATE,	(2 & $graphs) ? "yes": "no"),
			new CCheckBox('graphs_delete',	S_DELETE,	(4 & $graphs) ? "yes": "no")
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
		if(isset($userid))
		{
			$user=get_user_by_userid($userid);
			$result=DBselect("select u.alias,u.name,u.surname,u.passwd,u.url,u.autologout,u.lang,u.refresh from users u where u.userid=$userid");
	
			$alias=$user["alias"];
			$name=$user["name"];
			$surname=$user["surname"];
			$password="";
			$url=$user["url"];
			$autologout=$user["autologout"];
			$lang=$user["lang"];
			$refresh=$user["refresh"];
		}
		else
		{
			$alias="";
			$name="";
			$surname="";
			$password="";
			$url="";
			$autologout="900";
			$lang="en_gb";
			$refresh="30";
		}

		$frmUser = new CFormTable(S_USER);
		$frmUser->SetHelp("web.users.users.php");

		if($profile==0) 
			$frmUser->SetAction("users.php");
		else
			$frmUser->SetAction("profile.php");

		$frmUser->AddVar("config",$_REQUEST["config"]);
		if(isset($userid))	$frmUser->AddVar("userid",$userid);

		if($profile==0)
		{
			$frmUser->AddRow(S_ALIAS,	new CTextBox("alias",$alias,20));
			$frmUser->AddRow(S_NAME,	new CTextBox("name",$name,20));
			$frmUser->AddRow(S_SURNAME,	new CTextBox("surname",$surname,20));
		}

		$frmUser->AddRow(S_PASSWORD,	new CPassBox("password",$password,20));
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
			$frmUser->AddItemToBottomRow(new CButton('delete',S_DELETE,
				"return Confirm('Delete selected user?');"));
		}
		$frmUser->AddItemToBottomRow(SPACE);
		$frmUser->AddItemToBottomRow(new CButton('cancel',S_CANCEL));
		$frmUser->Show();
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

	# Insert form for Host Groups
	function	insert_hostgroups_form($groupid)
	{
		global  $_REQUEST;

		$col=0;

		if(isset($groupid))
		{
			$groupid=get_group_by_groupid($groupid);
	
			$name=$groupid["name"];
		}
		else
		{
			$name="";
		}

		show_form_begin("hosts.group");
		echo S_HOST_GROUP;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"hosts.php\">";
		if(isset($_REQUEST["groupid"]))
		{
			echo "<input name=\"groupid\" type=\"hidden\" value=\"".$_REQUEST["groupid"]."\" size=8>";
		}
		echo S_GROUP_NAME;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=30>";

		show_table2_v_delimiter($col++);
		echo S_HOSTS;
		show_table2_h_delimiter();
		echo "<select multiple class=\"biginput\" name=\"hosts[]\" size=\"5\">";
		$result=DBselect("select distinct hostid,host from hosts order by host");
		while($row=DBfetch($result))
		{
			if(isset($_REQUEST["groupid"]))
			{
				$sql="select count(*) as count from hosts_groups where hostid=".$row["hostid"]." and groupid=".$_REQUEST["groupid"];
				$result2=DBselect($sql);
				$row2=DBfetch($result2);
				if($row2["count"]==0)
				{
					echo "<option value=\"".$row["hostid"]."\">".$row["host"];
				}
				else
				{
					echo "<option value=\"".$row["hostid"]."\" selected>".$row["host"];
				}
			}
			else
			{
				echo "<option value=\"".$row["hostid"]."\">".$row["host"];
			}
		}
		echo "</select>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add group\">";
		if(isset($_REQUEST["groupid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update group\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete group\" onClick=\"return Confirm('Delete selected group?');\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"start monitoring\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"stop monitoring\">";
		}
		echo "</form>";
		show_table2_header_end();
	}

	# Insert form for User Groups
	function	insert_usergroups_form($usrgrpid)
	{
		global  $_REQUEST;

		$col=0;

		if(isset($usrgrpid))
		{
			$usrgrp=get_usergroup_by_usrgrpid($usrgrpid);
	
			$name=$usrgrp["name"];
		}
		else
		{
			$name="";
		}

		show_form_begin("users.groups");
		echo S_USER_GROUP;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"users.php\">";
		if(isset($usrgrpid))
		{
			echo "<input name=\"usrgrpid\" type=\"hidden\" value=\"$usrgrpid\" size=8>";
		}
		echo "<input name=\"config\" type=\"hidden\" value=\"1\" size=8>";
		echo S_GROUP_NAME;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=30>";

/*		show_table2_v_delimiter($col++);
		echo S_USERS;
		show_table2_h_delimiter();
		echo "<select multiple class=\"biginput\" name=\"users[]\" size=\"5\">";
		$result=DBselect("select distinct userid,alias from users order by alias");
		while($row=DBfetch($result))
		{
			if(isset($_REQUEST["usrgrpid"]))
			{
				$sql="select count(*) as count from users_groups where userid=".$row["userid"]." and usrgrpid=".$_REQUEST["usrgrpid"];
				$result2=DBselect($sql);
				$row2=DBfetch($result2);
				if($row2["count"]==0)
				{
					echo "<option value=\"".$row["userid"]."\">".$row["alias"];
				}
				else
				{
					echo "<option value=\"".$row["userid"]."\" selected>".$row["alias"];
				}
			}
			else
			{
				echo "<option value=\"".$row["userid"]."\">".$row["alias"];
			}
		}
		echo "</select>";*/

		show_table2_v_delimiter($col++);
		echo S_USERS;
		show_table2_h_delimiter();
		$result=DBselect("select distinct userid,alias from users order by alias");
		while($row=DBfetch($result))
		{
			if(isset($_REQUEST["usrgrpid"]))
			{
				$sql="select count(*) as count from users_groups where userid=".$row["userid"]." and usrgrpid=".$_REQUEST["usrgrpid"];
				$result2=DBselect($sql);
				$row2=DBfetch($result2);
				if($row2["count"]==0)
				{
					echo "<input type=checkbox name=\"".$row["userid"]."\" \">".$row["alias"];
				}
				else
				{
					echo "<input type=checkbox checked name=\"".$row["userid"]."\" \">".$row["alias"];
				}
			}
			else
			{
				echo "<input type=checkbox name=\"".$row["userid"]."\" \">".$row["alias"];
			}
			echo "<br>";
		}

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"save\" value=\"".S_SAVE."\">";
		if(isset($_REQUEST["usrgrpid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"delete\" value=\"".S_DELETE."\" onClick=\"return Confirm('Delete selected group?');\">";
		}
		echo "<input class=\"button\" type=\"submit\" name=\"cancel\" value=\"".S_CANCEL."\">";
		echo "</form>";
		show_table2_header_end();
	}

	# Insert form for User permissions
	function	insert_permissions_form($userid)
	{
		show_form_begin("users.users");
		echo "New permission";

		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"users.php\">";
		if(isset($userid))
		{
			echo "<input name=\"userid\" type=\"hidden\" value=\"$userid\" size=8>";
		}
		echo S_RESOURCE;
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"right\">";
		echo "<option value=\"Configuration of Zabbix\">Configuration of Zabbix";
		echo "<option value=\"Default permission\">Default permission";
		echo "<option value=\"Graph\">Graph";
		echo "<option value=\"Host\">Host";
		echo "<option value=\"Screen\">Screen";
		echo "<option value=\"Service\">IT Service";
		echo "<option value=\"Item\">Item";
		echo "<option value=\"Network map\">Network map";
		echo "<option value=\"Trigger comment\">Trigger's comment";
		echo "<option value=\"User\">User";
		echo "</select>";

		show_table2_v_delimiter();
		echo S_PERMISSION;
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"permission\">";
		echo "<option value=\"R\">Read-only";
		echo "<option value=\"U\">Read-write";
		echo "<option value=\"H\">Hide";
		echo "<option value=\"A\">Add";
		echo "</select>";

		show_table2_v_delimiter();
		echo "Resource ID (0 for all)";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"id\" value=\"0\" size=4>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add permission\">";
		show_table2_header_end();
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

	# Insert form for Trigger
	function	insert_trigger_form($hostid,$triggerid)
	{
		$col=0;

		if(isset($triggerid))
		{
			$trigger=get_trigger_by_triggerid($triggerid);
	
			$expression=explode_exp($trigger["expression"],0);
			$description=htmlspecialchars(stripslashes($trigger["description"]));
			$priority=$trigger["priority"];
			$status=$trigger["status"];
			$comments=$trigger["comments"];
			$url=$trigger["url"];
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

		show_form_begin("triggers.trigger");
		echo S_TRIGGER;
 
		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"triggers.php\">";

		if(isset($hostid))
		{
			echo "<input class=\"biginput\" name=\"hostid\" type=\"hidden\" value=\"".$hostid."\">";
		}
		if(isset($triggerid))
		{
			echo "<input class=\"biginput\" name=\"triggerid\" type=\"hidden\" value=\"".$triggerid."\">";
		}
		echo S_NAME;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"description\" value=\"$description\" size=70>";

		show_table2_v_delimiter($col++);
		echo S_EXPRESSION;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"expression\" value=\"$expression\" size=70>";

		show_table2_v_delimiter($col++);
		echo S_SEVERITY;
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"priority\" size=\"1\">";
		echo "<OPTION VALUE=\"0\" "; if($priority==0) echo "SELECTED"; echo ">Not classified";
		echo "<OPTION VALUE=\"1\" "; if($priority==1) echo "SELECTED"; echo ">Information";
		echo "<OPTION VALUE=\"2\" "; if($priority==2) echo "SELECTED"; echo ">Warning";
		echo "<OPTION VALUE=\"3\" "; if($priority==3) echo "SELECTED"; echo ">Average";
		echo "<OPTION VALUE=\"4\" "; if($priority==4) echo "SELECTED"; echo ">High";
		echo "<OPTION VALUE=\"5\" "; if($priority==5) echo "SELECTED"; echo ">Disaster";
		echo "</SELECT>";

		show_table2_v_delimiter($col++);
		echo S_COMMENTS;
		show_table2_h_delimiter();
 		echo "<TEXTAREA class=\"biginput\" NAME=\"comments\" COLS=70 ROWS=\"7\" WRAP=\"SOFT\">$comments</TEXTAREA>";

		show_table2_v_delimiter($col++);
		echo S_URL;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"url\" value=\"$url\" size=70>";

		show_table2_v_delimiter($col++);
		echo S_DISABLED;
		show_table2_h_delimiter();
		echo "<INPUT TYPE=\"CHECKBOX\" ";
		if($status==1) { echo " CHECKED "; }
		echo "NAME=\"disabled\"  VALUE=\"true\">";

 
		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"save\" value=\"".S_SAVE."\">";
		if(isset($triggerid))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"delete\" value=\"".S_DELETE."\" onClick=\"return Confirm('Delete trigger?');\">";
		}
		echo "<input class=\"button\" type=\"submit\" name=\"cancel\" value=\"".S_CANCEL."\">";

		if(isset($triggerid))
		{
			show_table2_v_delimiter();
			echo "The trigger depends on";
			show_table2_h_delimiter();
			$sql="select t.triggerid,t.description from triggers t,trigger_depends d where t.triggerid=d.triggerid_up and d.triggerid_down=$triggerid";
			$result1=DBselect($sql);
			echo "<SELECT class=\"biginput\" NAME=\"dependency\" size=\"1\">";
			while($row1=DBfetch($result1))
			{
				$depid=$row1["triggerid"];
				$depdescr=expand_trigger_description($depid);
				echo "<OPTION VALUE=\"$depid\">$depdescr";
			}
			echo "</SELECT>";

			show_table2_v_delimiter();
			echo "New dependency";
			show_table2_h_delimiter();
			$sql="select t.triggerid,t.description from triggers t where t.triggerid!=$triggerid order by t.description";
			$result=DBselect($sql);
			echo "<SELECT class=\"biginput\" NAME=\"depid\" size=\"1\">";
			while($row1=DBfetch($result1))
			{
				$depid=$row1["triggerid"];
				$depdescr=expand_trigger_description($depid);
				echo "<OPTION VALUE=\"$depid\">$depdescr";
			}
			echo "</SELECT>";

			show_table2_v_delimiter2();
			if(isset($triggerid))
			{
				echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add dependency\">";
				if(DBnum_rows($result1)>0)
				{
					echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete dependency\">";
				}
			}
		}

		echo "</form>";
		show_table2_header_end();
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

		show_form_begin("graphs.graph");
		echo S_GRAPH;

		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"graphs.php\">";
		if(isset($_REQUEST["graphid"]))
		{
			echo "<input class=\"biginput\" name=\"graphid\" type=\"hidden\" value=".$_REQUEST["graphid"].">";
		}
		echo S_NAME; 
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=32>";

		show_table2_v_delimiter();
		echo S_WIDTH;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"width\" size=5 value=\"$width\">";

		show_table2_v_delimiter();
		echo S_HEIGHT;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"height\" size=5 value=\"$height\">";

		show_table2_v_delimiter();
		echo S_YAXIS_TYPE;
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"yaxistype\" size=\"1\" onChange=\"submit()\">";
		echo "<OPTION VALUE=\"0\" "; if($yaxistype==GRAPH_YAXIS_TYPE_CALCULATED)	echo "SELECTED"; echo ">".S_CALCULATED;
		echo "<OPTION VALUE=\"1\" "; if($yaxistype==GRAPH_YAXIS_TYPE_FIXED)		echo "SELECTED"; echo ">".S_FIXED;
		echo "</SELECT>";

		if($yaxistype == GRAPH_YAXIS_TYPE_FIXED)
		{
			show_table2_v_delimiter();
			echo S_YAXIS_MIN_VALUE;
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"yaxismin\" size=5 value=\"$yaxismin\">";

			show_table2_v_delimiter();
			echo S_YAXIS_MAX_VALUE;
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"yaxismax\" size=5 value=\"$yaxismax\">";
		}
		else
		{
			echo "<input class=\"biginput\" name=\"yaxismin\" type=hidden value=\"$yaxismin\">";
			echo "<input class=\"biginput\" name=\"yaxismax\" type=hidden value=\"$yaxismax\">";
		}

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
		if(isset($_REQUEST["graphid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('".S_DELETE_GRAPH_Q."');\">";
		}

		show_table2_header_end();
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

		$col=0;

		show_form_begin("escalations");
		echo S_ESCALATION;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"config.php\">";
		echo "<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"".$_REQUEST["config"]."\" size=8>";
		if(isset($escalationid))
		{
			echo "<input class=\"biginput\" name=\"escalationid\" type=\"hidden\" value=\"$escalationid\" size=8>";
		}

		echo S_NAME;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"name\" size=32 value=\"$name\">";

		show_table2_v_delimiter($col++);
		echo S_IS_DEFAULT;
		show_table2_h_delimiter();
		echo "<input type=checkbox ".iif($dflt==1,"checked","")." name=\"dflt\">";

		show_table2_v_delimiter2($col++);
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add escalation\">";
		if(isset($escalationid))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update escalation\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete escalation\" onClick=\"return Confirm('Delete selected escalation?');\">";
		}

		show_table2_header_end();
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

		$col=0;

		show_form_begin("escalationrule");
		echo S_ESCALATION_RULE;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"config.php\">";
		echo "<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"".$_REQUEST["config"]."\" size=8>";
		echo "<input class=\"biginput\" name=\"escalationid\" type=\"hidden\" value=\"$escalationid\" size=8>";
		if(isset($escalationruleid))
		{
			echo "<input class=\"biginput\" name=\"escalationruleid\" type=\"hidden\" value=\"$escalationruleid\" size=8>";
		}

		echo S_LEVEL;
		show_table2_h_delimiter();
		echo form_input("level",$level,2);

		show_table2_v_delimiter($col++);
		echo S_PERIOD;
		show_table2_h_delimiter();
		echo form_input("period",$period,32);

		show_table2_v_delimiter($col++);
		echo S_DELAY;
		show_table2_h_delimiter();
		echo form_input("delay",$delay,32);

		show_table2_v_delimiter($col++);
		echo S_DO;
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"actiontype\" size=\"1\">";
		echo "<OPTION VALUE=\"0\" "; if($actiontype==0) echo "SELECTED"; echo ">Do nothing";
		echo "<OPTION VALUE=\"1\" "; if($actiontype==1) echo "SELECTED"; echo ">Execute actions";
		echo "<OPTION VALUE=\"2\" "; if($actiontype==2) echo "SELECTED"; echo ">Increase severity";
		echo "<OPTION VALUE=\"3\" "; if($actiontype==3) echo "SELECTED"; echo ">Increase administrative hierarcy";
		echo "</SELECT>";


		show_table2_v_delimiter2($col++);
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add rule\">";
		if(isset($escalationid))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update rule\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete rule\" onClick=\"return Confirm('Delete selected escalation rule?');\">";
		}

		show_table2_header_end();
	}

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

		$col=0;

		show_form_begin("host_profile");
		echo S_HOST_PROFILE;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"hosts.php\">";
		if(isset($_REQUEST["config"]))
		{
			echo "<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"".$_REQUEST["config"]."\" size=8>";
		}
		echo "<input class=\"biginput\" name=\"hostid\" type=\"hidden\" value=\"$hostid\" size=8>";

		echo S_DEVICE_TYPE;
		show_table2_h_delimiter();
		echo form_input("devicetype",$devicetype,64);

		show_table2_v_delimiter($col++);
		echo S_NAME;
		show_table2_h_delimiter();
		echo form_input("name",$name,64);

		show_table2_v_delimiter($col++);
		echo S_OS;
		show_table2_h_delimiter();
		echo form_input("os",$os,64);

		show_table2_v_delimiter($col++);
		echo S_SERIALNO;
		show_table2_h_delimiter();
		echo form_input("serialno",$serialno,64);

		show_table2_v_delimiter($col++);
		echo S_TAG;
		show_table2_h_delimiter();
		echo form_input("tag",$tag,64);

		show_table2_v_delimiter($col++);
		echo S_MACADDRESS;
		show_table2_h_delimiter();
		echo form_input("macaddress",$macaddress,64);

		show_table2_v_delimiter($col++);
		echo S_HARDWARE;
		show_table2_h_delimiter();
		echo form_textarea("hardware",$hardware,50,4);

		show_table2_v_delimiter($col++);
		echo S_SOFTWARE;
		show_table2_h_delimiter();
		echo form_textarea("software",$software,50,4);

		show_table2_v_delimiter($col++);
		echo S_CONTACT;
		show_table2_h_delimiter();
		echo form_textarea("contact",$contact,50,4);

		show_table2_v_delimiter($col++);
		echo S_LOCATION;
		show_table2_h_delimiter();
		echo form_textarea("location",$location,50,4);

		show_table2_v_delimiter($col++);
		echo S_NOTES;
		show_table2_h_delimiter();
		echo form_textarea("notes",$notes,50,4);

		show_table2_v_delimiter2($col++);
		if($readonly==0)
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add profile\">";
			if(isset($hostid))
			{
				echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update profile\">";
				echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete profile\" onClick=\"return Confirm('Delete selected profile?');\">";
			}
		}
		else
		{
			echo "&nbsp;";
		}

		show_table2_header_end();
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

		show_form_begin("autoregistration");
		echo S_AUTOREGISTRATION;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" name=\"auto\" action=\"config.php\">";
		echo "<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"".$_REQUEST["config"]."\" size=8>";
		if(isset($id))
		{
			echo "<input class=\"biginput\" name=\"id\" type=\"hidden\" value=\"$id\" size=8>";
		}

		echo S_PATTERN;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"pattern\" size=64 value=\"$pattern\">";

		show_table2_v_delimiter($col++);
		echo S_PRIORITY;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"priority\" size=4 value=\"$priority\">";

		show_table2_v_delimiter($col++);
		echo S_HOST;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" type=\"hidden\" name=\"hostid\" value=\"$hostid\">";
		echo "<input class=\"biginput\" readonly name=\"host\" size=32 value=\"$host\">";
		echo "<input title=\"Select [Alt+T]\" accessKey=\"T\" type=\"button\" class=\"button\" value='Select' name=\"btn1\" onclick=\"window.open('popup.php?form=auto&field1=hostid&field2=host','new_win','width=450,height=450,resizable=1,scrollbars=1');\">";

		show_table2_v_delimiter2($col++);
		echo "<input class=\"button\" type=\"submit\" name=\"save\" value=\"".S_SAVE."\">";
		if(isset($id))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"delete\" value=\"".S_DELETE."\" onClick=\"return Confirm('Delete selected autoregistration rule?');\">";
		}
		echo "<input class=\"button\" type=\"submit\" name=\"cancel\" value=\"".S_CANCEL."\">";

		show_table2_header_end();
	}

	function	insert_action_form()
	{
		global  $_REQUEST;

		$uid=NULL;

		$frmAction = new CFormTable(S_ACTION,'actionconf.php');
		$frmAction->SetHelp('web.actions.action');
		if(isset($_REQUEST['form']))
			$frmAction->AddVar('form',$_REQUEST['form']);

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
		$cmbActionType->AddItem(1,S_REMOTE_COMMAND,'no');

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

		if(isset($_REQUEST["register"]) && ($_REQUEST["register"] == "change"))
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

		show_form_begin("config.medias");
		echo S_MEDIA;

		$col=0;

		show_table2_v_delimiter($col++);
		echo "<form name=\"selForm\" method=\"get\" action=\"config.php\">";
		if(isset($_REQUEST["mediatypeid"]))
		{
			echo "<input class=\"biginput\" name=\"mediatypeid\" type=\"hidden\" value=\"".$_REQUEST["mediatypeid"]."\" size=8>";
		}
		echo "<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"1\" size=8>";

		echo S_DESCRIPTION;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"description\" value=\"".$description."\" size=30>";

		show_table2_v_delimiter($col++);
		echo S_TYPE;
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"type\" size=\"1\" onChange=\"submit()\">";
		if($type==0)
		{
			echo "<option value=\"0\" selected>".S_EMAIL;
			echo "<option value=\"1\">".S_SCRIPT;
		}
		else
		{
			echo "<option value=\"0\">".S_EMAIL;
			echo "<option value=\"1\" selected>".S_SCRIPT;
		}
		echo "</select>";

		if($type==0)
		{
			echo "<input class=\"biginput\" name=\"exec_path\" type=\"hidden\" value=\"$exec_path\">";

			show_table2_v_delimiter($col++);
			echo nbsp(S_SMTP_SERVER);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"smtp_server\" value=\"".$smtp_server."\" size=30>";

			show_table2_v_delimiter($col++);
			echo nbsp(S_SMTP_HELO);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"smtp_helo\" value=\"".$smtp_helo."\" size=30>";

			show_table2_v_delimiter($col++);
			echo nbsp(S_SMTP_EMAIL);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"smtp_email\" value=\"".$smtp_email."\" size=30>";
		}
		if($type==1)
		{
			echo "<input class=\"biginput\" name=\"smtp_server\" type=\"hidden\" value=\"$smtp_server\">";
			echo "<input class=\"biginput\" name=\"smtp_helo\" type=\"hidden\" value=\"$smtp_helo\">";
			echo "<input class=\"biginput\" name=\"smtp_email\" type=\"hidden\" value=\"$smtp_email\">";

			show_table2_v_delimiter($col++);
			echo S_SCRIPT_NAME;
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"exec_path\" value=\"".$exec_path."\" size=50>";
		}

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"save\" value=\"".S_SAVE."\">";
		if(isset($_REQUEST["mediatypeid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"delete\" value=\"".S_DELETE."\" onClick=\"return Confirm('".S_DELETE_SELECTED_MEDIA."');\">";
		}
		echo "<input class=\"button\" type=\"submit\" name=\"calcel\" value=\"".S_CANCEL."\">";

		show_table2_header_end();
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

		$col=0;
		show_form_begin("config.images");
		echo S_IMAGE;
	
		show_table2_v_delimiter($col++);
#		echo "<form method=\"get\" action=\"config.php\">";
		echo "<form enctype=\"multipart/form-data\" method=\"post\" action=\"config.php\">";
		echo "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"".(1024*1024)."\">";
		echo "<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"3\" size=8>";
		if(isset($imageid))
		{
				echo "<input class=\"biginput\" name=\"imageid\" type=\"hidden\" value=\"$imageid\" size=8>";
		}
		echo nbsp(S_NAME);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"name\" value=\"".$name."\" size=64>";
	
		show_table2_v_delimiter($col++);
		echo S_TYPE;
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"imagetype\" size=\"1\">";
		if($imagetype==1)
		{
			echo "<option value=\"1\" selected>".S_ICON;
			echo "<option value=\"2\">".S_BACKGROUND;
		}
		else
		{
			echo "<option value=\"1\">".S_ICON;
			echo "<option value=\"2\" selected>".S_BACKGROUND;
		}
		echo "</select>";

		show_table2_v_delimiter($col++);
		echo S_UPLOAD;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"image\" type=\"file\">";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"save\" value=\"".S_SAVE."\">";
		if(isset($_REQUEST["imageid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"delete\" value=\"".S_DELETE."\" onClick=\"return Confirm('".S_DELETE_SELECTED_IMAGE."');\">";
		}
		echo "<input class=\"button\" type=\"submit\" name=\"cancel\" value=\"".S_CANCEL."\">";

		show_table2_header_end();
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

		show_form_begin("screenconf.screen");
		echo S_SCREEN;
		$col=0;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"screenconf.php\">";
		if(isset($_REQUEST["screenid"]))
		{
			echo "<input class=\"biginput\" name=\"screenid\" type=\"hidden\" value=".$_REQUEST["screenid"].">";
		}
		echo S_NAME;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=32>";

		show_table2_v_delimiter($col++);
		echo S_COLUMNS;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"cols\" size=5 value=\"$cols\">";

		show_table2_v_delimiter($col++);
		echo S_ROWS;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"rows\" size=5 value=\"$rows\">";
	
		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"save\" value=\"".S_SAVE."\">";
		if(isset($_REQUEST["screenid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"delete\" value=\"".S_DELETE."\" onClick=\"return Confirm('".S_DELETE_SCREEN_Q."');\">";
		}
		echo "<input class=\"button\" type=\"submit\" name=\"calcel\" value=\"".S_CANCEL."\">";
	
		show_table2_header_end();
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

		show_form_begin("media.media");
		echo S_NEW_MEDIA;

		$col=0;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"media.php\">";
		echo "<input name=\"userid\" type=\"hidden\" value=".$_REQUEST["userid"].">";
		if(isset($_REQUEST["mediaid"]))
		{
			echo "<input name=\"mediaid\" type=\"hidden\" value=".$_REQUEST["mediaid"].">";
		}
		echo S_TYPE;
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"mediatypeid\" size=1>";
		$sql="select mediatypeid,description from media_type order by type";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if($row["mediatypeid"] == $mediatypeid)
			{
				echo "<OPTION VALUE=\"".$row["mediatypeid"]."\" SELECTED>".$row["description"];
			}
			else
			{
				echo "<OPTION VALUE=\"".$row["mediatypeid"]."\">".$row["description"];
			}
			
		}
		echo "</SELECT>";
	
		show_table2_v_delimiter($col++);
		echo nbsp(S_SEND_TO);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"sendto\" size=20 value='$sendto'>";
	
		show_table2_v_delimiter($col++);
		echo nbsp(S_WHEN_ACTIVE);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"period\" size=48 value='$period'>";
	
		show_table2_v_delimiter($col++);
		echo nbsp(S_USE_IF_SEVERITY);
		show_table2_h_delimiter();
		$checked=iif( (1&$severity) == 1,"checked","");
		echo "<input type=checkbox name=\"0\" value=\"0\" $checked>".S_NOT_CLASSIFIED."<br>";
		$checked=iif( (2&$severity) == 2,"checked","");
		echo "<input type=checkbox name=\"1\" value=\"1\" $checked>".S_INFORMATION."<br>";
		$checked=iif( (4&$severity) == 4,"checked","");
		echo "<input type=checkbox name=\"2\" value=\"2\" $checked>".S_WARNING."<br>";
		$checked=iif( (8&$severity) == 8,"checked","");
		echo "<input type=checkbox name=\"3\" value=\"3\" $checked>".S_AVERAGE."<br>";
		$checked=iif( (16&$severity) ==16,"checked","");
		echo "<input type=checkbox name=\"4\" value=\"4\" $checked>".S_HIGH."<br>";
		$checked=iif( (32&$severity) ==32,"checked","");
		echo "<input type=checkbox name=\"5\" value=\"5\" $checked>".S_DISASTER."<br>";
	
		show_table2_v_delimiter($col++);
		echo "Status";
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"active\" size=1>";
		if($active == 0)
		{
			echo "<OPTION VALUE=\"0\" SELECTED>".S_ENABLED;
			echo "<OPTION VALUE=\"1\">".S_DISABLED;
		}
		else
		{
			echo "<OPTION VALUE=\"0\">".S_ENABLED;
			echo "<OPTION VALUE=\"1\" SELECTED>".S_DISABLED;
		}
		echo "</select>";
	
		show_table2_v_delimiter2($col++);
		echo "<input class=\"button\" type=\"submit\" name=\"save\" value=\"".S_SAVE."\">";
		if(isset($_REQUEST["mediaid"]))
		{
//			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input class=\"button\" type=\"submit\" name=\"delete\" value=\"".S_DELETE."\" onClick=\"return Confirm('".S_DELETE_SELECTED_MEDIA_Q."');\">";
		}
		echo "<input class=\"button\" type=\"submit\" name=\"cancel\" value=\"".S_CANCEL."\">";

		show_table2_header_end();
	}

	function	insert_host_form()
	{

		global $_REQUEST;

		$host=@iif(isset($_REQUEST["host"]),$_REQUEST["host"],"");
		$port=@iif(isset($_REQUEST["port"]),$_REQUEST["port"],get_profile("HOST_PORT",10050));
		$status=@iif(isset($_REQUEST["status"]),$_REQUEST["status"],HOST_STATUS_MONITORED);
		$useip=@iif(isset($_REQUEST["useip"]),$_REQUEST["useip"],"off");
		$newgroup=@iif(isset($_REQUEST["newgroup"]),$_REQUEST["newgroup"],"");
		$ip=@iif(isset($_REQUEST["ip"]),$_REQUEST["ip"],"");
		$host_templateid=@iif(isset($_REQUEST["host_templateid"]),$_REQUEST["host_templateid"],"");

		if($useip!="on")
		{
			$useip="";
		}
		else
		{
			$useip="checked";
		}

		if(isset($_REQUEST["register"]) && ($_REQUEST["register"] == "change"))
		{
			$result=get_host_by_hostid($_REQUEST["hostid"]);
			$host=$result["host"];
			$port=$result["port"];
			$status=$result["status"];
			$useip=$result["useip"];
			$ip=$result["ip"];
	
			if($useip==0)
			{
				$useip="";
			}
			else
			{
				$useip="checked";
			}
		}
		else
		{
		}


		echo "<a name=\"form\"></a>";
		show_form_begin("hosts.host");
		echo S_HOST;
		$col=0;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"hosts.php#form\">";
		if(isset($_REQUEST["hostid"]))
		{
			echo "<input class=\"biginput\" name=\"hostid\" type=\"hidden\" value=\"".$_REQUEST["hostid"]."\">";
		}
		if(isset($_REQUEST["groupid"]))
		{
			echo "<input class=\"biginput\" name=\"groupid\" type=\"hidden\" value=\"".$_REQUEST["groupid"]."\">";
		}
		echo S_HOST;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"host\" value=\"$host\" size=20>";

		show_table2_v_delimiter($col++);
		echo S_GROUPS;
		show_table2_h_delimiter();
		$result=DBselect("select distinct groupid,name from groups order by name");
		while($row=DBfetch($result))
		{
			if(isset($_REQUEST["hostid"]))
			{
				$sql="select count(*) as count from hosts_groups where hostid=".$_REQUEST["hostid"]." and groupid=".$row["groupid"];
				$result2=DBselect($sql);
				$row2=DBfetch($result2);
				if($row2["count"]==0)
				{
					echo "<input type=checkbox name=\"".$row["groupid"]."\">".$row["name"];
				}
				else
				{
					echo "<input type=checkbox name=\"".$row["groupid"]."\" checked>".$row["name"];
				}
			}
			else
			{
				echo "<input type=checkbox name=\"".$row["groupid"]."\">".$row["name"];
			}
			echo "<br>";
		}
		echo "</select>";

		show_table2_v_delimiter($col++);
		echo nbsp(S_NEW_GROUP);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"newgroup\" size=20 value=\"$newgroup\">";

		show_table2_v_delimiter($col++);
		echo nbsp(S_USE_IP_ADDRESS);
		show_table2_h_delimiter();
// onChange does not work on some browsers: MacOS, KDE browser
//	echo "<INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"useip\" $useip onChange=\"submit()\">";
		echo "<INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"useip\" $useip onClick=\"submit()\">";

		if($useip=="checked")
		{
			show_table2_v_delimiter($col++);
			echo S_IP_ADDRESS;
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"ip\" value=\"$ip\" size=15>";
		}
		else
		{
			echo "<input class=\"biginput\" type=\"hidden\"name=\"ip\" value=\"$ip\" size=15>";
		}
	
		show_table2_v_delimiter($col++);
		echo S_PORT;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"port\" size=6 value=\"$port\">";
	
		show_table2_v_delimiter($col++);
		echo S_STATUS;
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"status\" size=\"1\">";
		if($status==HOST_STATUS_MONITORED)
		{
			echo "<option value=\"0\" selected>".S_MONITORED;
			echo "<option value=\"1\">".S_NOT_MONITORED;
			echo "<option value=\"3\">".S_TEMPLATE;
		}
		else if($status==HOST_STATUS_TEMPLATE)
		{
			echo "<option value=\"0\">".S_MONITORED;
			echo "<option value=\"1\">".S_NOT_MONITORED;
			echo "<option value=\"3\" selected>".S_TEMPLATE;
		}
		else
		{
			echo "<option value=\"0\">".S_MONITORED;
			echo "<option value=\"1\" selected>".S_NOT_MONITORED;
			echo "<option value=\"3\">".S_TEMPLATE;
		}
		echo "</select>";

		show_table2_v_delimiter($col++);
//	echo nbsp(S_USE_THE_HOST_AS_A_TEMPLATE);
		echo nbsp(S_USE_TEMPLATES_OF_THIS_HOST);
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"host_templateid\" size=\"1\">";
		echo "<option value=\"0\" selected>...";
//	$result=DBselect("select host,hostid from hosts where status=3 order by host");
		$result=DBselect("select host,hostid from hosts where status not in (".HOST_STATUS_DELETED.") order by host");
		while($row=DBfetch($result))
		{
			if($host_templateid == $row["hostid"])
			{
				echo "<option value=\"".$row["hostid"]."\" selected>".$row["host"];
			}
			else
			{
				echo "<option value=\"".$row["hostid"]."\">".$row["host"];
			}
			
		}
		echo "</select>";
	
		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"save\" value=\"".S_SAVE."\">";
		if(isset($_REQUEST["hostid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add items from template\">";
			echo "<input class=\"button\" type=\"submit\" name=\"delete\" value=\"".S_DELETE."\" onClick=\"return Confirm('".S_DELETE_SELECTED_HOST_Q."');\">";
		}
		echo "<input class=\"button\" type=\"submit\" name=\"cancel\" value=\"".S_CANCEL."\">";

		show_table2_header_end();
	}
?>
