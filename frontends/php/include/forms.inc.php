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
	include_once 	"include/local_en.inc.php";

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
			$actions=$row["actions"];
			$graphs=$row["graphs"];
			$screens=$row["screens"];
		}
		else
		{
			$hostid=0;
			$templateid=0;
			$items=7;
			$triggers=7;
			$actions=7;
			$graphs=7;
			$screens=7;
		}

		$col=0;

		show_form_begin("hosts");
		echo S_TEMPLATE;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"hosts.php\">";
		echo "<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"".$_GET["config"]."\" size=8>";
		echo "<input class=\"biginput\" name=\"hostid\" type=\"hidden\" value=\"".$_GET["hostid"]."\" size=8>";
		if(isset($hosttemplateid))
		{
			echo "<input class=\"biginput\" name=\"hosttemplateid\" type=\"hidden\" value=\"$hosttemplateid\" size=8>";
		}
		if($hostid!=0)
		{
			echo "<input class=\"biginput\" name=\"hostid\" type=\"hidden\" value=\"$hostid\" size=8>";
		}
		echo S_TEMPLATE;
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"templateid\" value=\"3\">";
	        $result=DBselect("select hostid,host from hosts order by host");
		while($row=DBfetch($result))
	        {
			if($templateid==$row["hostid"])
			{
	                	echo "<option value=\"".$row["hostid"]."\" selected>".$row["host"];
			}
			else
			{
	                	echo "<option value=\"".$row["hostid"]."\">".$row["host"];
			}
	        }
		echo "</select>";


		show_table2_v_delimiter($col++);
		echo S_ITEMS;
		show_table2_h_delimiter();
		echo "<input type=checkbox ".iif((1&$items)==1,"checked","")." name=\"items_add\" \">".S_ADD;
		echo "<input type=checkbox ".iif((2&$items)==2,"checked","")." name=\"items_update\" \">".S_UPDATE;
		echo "<input type=checkbox ".iif((4&$items)==4,"checked","")." name=\"items_delete\" \">".S_DELETE;

		show_table2_v_delimiter($col++);
		echo S_TRIGGERS;
		show_table2_h_delimiter();
		echo "<input type=checkbox ".iif((1&$triggers)==1,"checked","")." name=\"triggers_add\" \">".S_ADD;
		echo "<input type=checkbox ".iif((2&$triggers)==2,"checked","")." name=\"triggers_update\" \">".S_UPDATE;
		echo "<input type=checkbox ".iif((4&$triggers)==4,"checked","")." name=\"triggers_delete\" \">".S_DELETE;

		show_table2_v_delimiter($col++);
		echo S_ACTIONS;
		show_table2_h_delimiter();
		echo "<input type=checkbox ".iif((1&$actions)==1,"checked","")." name=\"actions_add\" \">".S_ADD;
		echo "<input type=checkbox ".iif((2&$actions)==2,"checked","")." name=\"actions_update\" \">".S_UPDATE;
		echo "<input type=checkbox ".iif((4&$actions)==4,"checked","")." name=\"actions_delete\" \">".S_DELETE;

		show_table2_v_delimiter($col++);
		echo S_GRAPHS;
		show_table2_h_delimiter();
		echo "<input type=checkbox ".iif((1&$graphs)==1,"checked","")." name=\"graphs_add\" \">".S_ADD;
		echo "<input type=checkbox ".iif((2&$graphs)==2,"checked","")." name=\"graphs_update\" \">".S_UPDATE;
		echo "<input type=checkbox ".iif((4&$graphs)==4,"checked","")." name=\"graphs_delete\" \">".S_DELETE;

		show_table2_v_delimiter($col++);
		echo S_SCREENS;
		show_table2_h_delimiter();
		echo "<input type=checkbox ".iif((1&$screens)==1,"checked","")." name=\"screens_add\" \">".S_ADD;
		echo "<input type=checkbox ".iif((2&$screens)==2,"checked","")." name=\"screens_update\" \">".S_UPDATE;
		echo "<input type=checkbox ".iif((4&$screens)==4,"checked","")." name=\"screens_delete\" \">".S_DELETE;

		show_table2_v_delimiter2($col++);
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add linkage\">";
		if(isset($hosttemplateid))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update linkage\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete linkage\" onClick=\"return Confirm('Delete selected linkage?');\">";
		}

		show_table2_header_end();
	}

	# Insert form for User
	function	insert_user_form($userid)
	{
		if(isset($userid))
		{
			$result=DBselect("select u.alias,u.name,u.surname,u.passwd,u.url from users u where u.userid=$userid");
	
			$alias=DBget_field($result,0,0);
			$name=DBget_field($result,0,1);
			$surname=DBget_field($result,0,2);
#			$password=DBget_field($result,0,3);
			$password="";
			$url=DBget_field($result,0,4);
		}
		else
		{
			$alias="";
			$name="";
			$surname="";
			$password="";
			$url="";
		}

		$col=0;

		show_form_begin("users.users");
		echo S_USER;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"users.php\">";
		echo "<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"".$_GET["config"]."\" size=8>";
		if(isset($userid))
		{
			echo "<input class=\"biginput\" name=\"userid\" type=\"hidden\" value=\"$userid\" size=8>";
		}
		echo S_ALIAS;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"alias\" value=\"$alias\" size=20>";

		show_table2_v_delimiter($col++);
		echo S_NAME;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=20>";

		show_table2_v_delimiter($col++);
		echo S_SURNAME;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"surname\" value=\"$surname\" size=20>";

		show_table2_v_delimiter($col++);
		echo S_PASSWORD;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" type=\"password\" name=\"password1\" value=\"$password\" size=20>";

		show_table2_v_delimiter($col++);
		echo nbsp(S_PASSWORD_ONCE_AGAIN);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" type=\"password\" name=\"password2\" value=\"$password\" size=20>";

		show_table2_v_delimiter($col++);
		echo S_URL_AFTER_LOGIN;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"url\" value=\"$url\" size=50>";

		show_table2_v_delimiter2($col++);
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
		if(isset($userid))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete selected user?');\">";
		}

		show_table2_header_end();
	}

	# Insert form for Item information
	function	insert_item_form()
	{
		global  $_GET;

		$description=@iif(isset($_GET["description"]),$_GET["description"],"");
		$key=@iif(isset($_GET["key"]),$_GET["key"],"");
		$host=@iif(isset($_GET["host"]),$_GET["host"],"");
		$port=@iif(isset($_GET["port"]),$_GET["port"],10050);
		$delay=@iif(isset($_GET["delay"]),$_GET["delay"],30);
		$history=@iif(isset($_GET["history"]),$_GET["history"],90);
		$trends=@iif(isset($_GET["trends"]),$_GET["trends"],365);
		$status=@iif(isset($_GET["status"]),$_GET["status"],0);
		$type=@iif(isset($_GET["type"]),$_GET["type"],0);
		$snmp_community=@iif(isset($_GET["snmp_community"]),$_GET["snmp_community"],"public");
		$snmp_oid=@iif(isset($_GET["snmp_oid"]),$_GET["snmp_oid"],"interfaces.ifTable.ifEntry.ifInOctets.1");
		$value_type=@iif(isset($_GET["value_type"]),$_GET["value_type"],0);
		$trapper_hosts=@iif(isset($_GET["trapper_hosts"]),$_GET["trapper_hosts"],"");
		$snmp_port=@iif(isset($_GET["snmp_port"]),$_GET["snmp_port"],161);
		$units=@iif(isset($_GET["units"]),$_GET["units"],'');
		$multiplier=@iif(isset($_GET["multiplier"]),$_GET["multiplier"],0);
		$hostid=@iif(isset($_GET["hostid"]),$_GET["hostid"],0);
		$delta=@iif(isset($_GET["delta"]),$_GET["delta"],0);

		$snmpv3_securityname=@iif(isset($_GET["snmpv3_securityname"]),$_GET["snmpv3_securityname"],"");
		$snmpv3_securitylevel=@iif(isset($_GET["snmpv3_securitylevel"]),$_GET["snmpv3_securitylevel"],0);
		$snmpv3_authpassphrase=@iif(isset($_GET["snmpv3_authpassphrase"]),$_GET["snmpv3_authpassphrase"],"");
		$snmpv3_privpassphrase=@iif(isset($_GET["snmpv3_privpassphrase"]),$_GET["snmpv3_privpassphrase"],"")
;
		$formula=@iif(isset($_GET["formula"]),$_GET["formula"],"1");

		if(isset($_GET["register"])&&($_GET["register"] == "change"))
		{
			$result=DBselect("select i.description, i.key_, h.host, h.port, i.delay, i.history, i.status, i.type, i.snmp_community,i.snmp_oid,i.value_type,i.trapper_hosts,i.snmp_port,i.units,i.multiplier,h.hostid,i.delta,i.trends,i.snmpv3_securityname,i.snmpv3_securitylevel,i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.formula from items i,hosts h where i.itemid=".$_GET["itemid"]." and h.hostid=i.hostid");
		
			$description=DBget_field($result,0,0);
			$key=DBget_field($result,0,1);
			$host=DBget_field($result,0,2);
			$port=DBget_field($result,0,3);
			$delay=DBget_field($result,0,4);
			$history=DBget_field($result,0,5);
			$status=DBget_field($result,0,6);
			$type=iif(isset($_GET["type"]),isset($_GET["type"]),DBget_field($result,0,7));
			$snmp_community=DBget_field($result,0,8);
			$snmp_oid=DBget_field($result,0,9);
			$value_type=DBget_field($result,0,10);
			$trapper_hosts=DBget_field($result,0,11);
			$snmp_port=DBget_field($result,0,12);
			$units=DBget_field($result,0,13);
			$multiplier=DBget_field($result,0,14);
			$hostid=DBget_field($result,0,15);
			$delta=DBget_field($result,0,16);
			$trends=DBget_field($result,0,17);

			$snmpv3_securityname=DBget_field($result,0,18);
			$snmpv3_securitylevel=DBget_field($result,0,19);
			$snmpv3_authpassphrase=DBget_field($result,0,20);
			$snmpv3_privpassphrase=DBget_field($result,0,21);

			$formula=DBget_field($result,0,22);
		}

		show_form_begin("items.item");
		echo S_ITEM;

		$col=0; 
		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"items.php\">";
		if(isset($_GET["itemid"]))
		{
			echo "<input class=\"biginput\" name=\"itemid\" type=hidden value=".$_GET["itemid"].">";
		}
		echo S_DESCRIPTION;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"description\" value=\"$description\"size=40>";

		show_table2_v_delimiter($col++);
		echo S_HOST;
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"hostid\" value=\"3\">";
	        $result=DBselect("select hostid,host from hosts order by host");
	        for($i=0;$i<DBnum_rows($result);$i++)
	        {
	                $hostid_=DBget_field($result,$i,0);
	                $host_=DBget_field($result,$i,1);
			if($hostid==$hostid_)
			{
	                	echo "<option value=\"$hostid_\" selected>$host_";
			}
			else
			{
	                	echo "<option value=\"$hostid_\">$host_";
			}
	        }
		echo "</select>";

		show_table2_v_delimiter($col++);
		echo S_TYPE;
		show_table2_h_delimiter();

		echo "<SELECT class=\"biginput\" NAME=\"type\" value=\"$type\" size=\"1\" onChange=\"submit()\">";
		echo "<OPTION VALUE=\"0\"";
		if($type==ITEM_TYPE_ZABBIX) echo "SELECTED";
		echo ">Zabbix agent";

		echo "<OPTION VALUE=\"7\"";
		if($type==ITEM_TYPE_ZABBIX_ACTIVE) echo "SELECTED";
		echo ">Zabbix agent (active)";

		echo "<OPTION VALUE=\"3\"";
		if($type==ITEM_TYPE_SIMPLE) echo "SELECTED";
		echo ">Simple check";

		echo "<OPTION VALUE=\"1\"";
		if($type==ITEM_TYPE_SNMPV1) echo "SELECTED";
		echo ">SNMPv1 agent";

		echo "<OPTION VALUE=\"4\"";
		if($type==ITEM_TYPE_SNMPV2C) echo "SELECTED";
		echo ">SNMPv2 agent";

		echo "<OPTION VALUE=\"6\"";
		if($type==ITEM_TYPE_SNMPV3) echo "SELECTED";
		echo ">SNMPv3 agent";


		echo "<OPTION VALUE=\"2\"";
		if($type==ITEM_TYPE_TRAPPER) echo "SELECTED";
		echo ">Zabbix trapper";

		echo "<OPTION VALUE=\"5\"";
		if($type==ITEM_TYPE_INTERNAL) echo "SELECTED";
		echo ">Zabbix internal";

		echo "</SELECT>";

		if(($type==ITEM_TYPE_SNMPV1)||($type==ITEM_TYPE_SNMPV2C))
		{ 
			show_table2_v_delimiter($col++);
			echo nbsp(S_SNMP_COMMUNITY);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"snmp_community\" value=\"$snmp_community\" size=16>";

			show_table2_v_delimiter($col++);
			echo nbsp(S_SNMP_OID);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"snmp_oid\" value=\"$snmp_oid\" size=40>";

			show_table2_v_delimiter($col++);
			echo nbsp(S_SNMP_PORT);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"snmp_port\" value=\"$snmp_port\" size=5>";

			echo "<input class=\"biginput\" name=\"snmpv3_securityname\" type=hidden value=\"$snmpv3_securityname\">";
			echo "<input class=\"biginput\" name=\"snmpv3_securitylevel\" type=hidden value=\"$snmpv3_securitylevel\">";
			echo "<input class=\"biginput\" name=\"snmpv3_authpassphrase\" type=hidden value=\"$snmpv3_authpassphrase\">";
			echo "<input class=\"biginput\" name=\"snmpv3_privpassphrase\" type=hidden value=\"$snmpv3_privpassphrase\">";
		}
		else if($type==ITEM_TYPE_SNMPV3)
		{
			show_table2_v_delimiter($col++);
			echo nbsp(S_SNMP_OID);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"snmp_oid\" value=\"$snmp_oid\" size=40>";

			show_table2_v_delimiter($col++);
			echo nbsp(S_SNMPV3_SECURITY_NAME);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"snmpv3_securityname\" value=\"$snmpv3_securityname\" size=64>";

			show_table2_v_delimiter($col++);
			echo nbsp(S_SNMPV3_SECURITY_LEVEL);
			show_table2_h_delimiter();
			echo "<SELECT class=\"biginput\" NAME=\"snmpv3_securitylevel\" value=\"$snmpv3_securitylevel\" size=\"1\">";
			echo "<OPTION VALUE=\"0\"";
			if($snmpv3_securitylevel==ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV) echo "SELECTED";
			echo ">NoAuthPriv";

			echo "<OPTION VALUE=\"1\"";
			if($snmpv3_securitylevel==ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) echo "SELECTED";
			echo ">AuthNoPriv";

			echo "<OPTION VALUE=\"2\"";
			if($snmpv3_securitylevel==ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) echo "SELECTED";
			echo ">AuthPriv";

			echo "</SELECT>";

			show_table2_v_delimiter($col++);
			echo nbsp(S_SNMPV3_AUTH_PASSPHRASE);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"snmpv3_authpassphrase\" value=\"$snmpv3_authpassphrase\" size=64>";

			show_table2_v_delimiter($col++);
			echo nbsp(S_SNMPV3_PRIV_PASSPHRASE);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"snmpv3_privpassphrase\" value=\"$snmpv3_privpassphrase\" size=64>";

			show_table2_v_delimiter($col++);
			echo nbsp(S_SNMP_PORT);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"snmp_port\" value=\"$snmp_port\" size=5>";

			echo "<input class=\"biginput\" name=\"snmp_community\" type=hidden value=\"$snmp_community\">";
		}
		else
		{
			echo "<input class=\"biginput\" name=\"snmp_community\" type=hidden value=\"$snmp_community\">";
			echo "<input class=\"biginput\" name=\"snmp_oid\" type=hidden value=\"$snmp_oid\">";
			echo "<input class=\"biginput\" name=\"snmp_port\" type=hidden value=\"$snmp_port\">";

			echo "<input class=\"biginput\" name=\"snmpv3_securityname\" type=hidden value=\"$snmpv3_securityname\">";
			echo "<input class=\"biginput\" name=\"snmpv3_securitylevel\" type=hidden value=\"$snmpv3_securitylevel\">";
			echo "<input class=\"biginput\" name=\"snmpv3_authpassphrase\" type=hidden value=\"$snmpv3_authpassphrase\">";
			echo "<input class=\"biginput\" name=\"snmpv3_privpassphrase\" type=hidden value=\"$snmpv3_privpassphrase\">";
		}

		show_table2_v_delimiter($col++);
		echo S_KEY;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"key\" value=\"$key\" size=40>";

		show_table2_v_delimiter($col++);
		echo S_UNITS;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"units\" value=\"$units\" size=10>";

		show_table2_v_delimiter($col++);
		echo S_USE_MULTIPLIER;
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"multiplier\" value=\"$multiplier\" size=\"1\" onChange=\"submit()\">";
		echo "<OPTION VALUE=\"0\""; if($multiplier==0) echo "SELECTED"; echo ">".S_DO_NOT_USE;
		echo "<OPTION VALUE=\"1\" "; if($multiplier==1) echo "SELECTED"; echo ">".S_CUSTOM_MULTIPLIER;
		echo "</SELECT>";

		if($multiplier == 1)
		{
			show_table2_v_delimiter($col++);
			echo nbsp(S_CUSTOM_MULTIPLIER);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"formula\" value=\"$formula\" size=40>";
		}
		else
		{
			echo "<input class=\"biginput\" name=\"formula\" type=hidden value=\"$formula\">";
		}

		if($type!=2)
		{
			show_table2_v_delimiter($col++);
			echo nbsp(S_UPDATE_INTERVAL_IN_SEC);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"delay\" value=\"$delay\" size=5>";
		}
		else
		{
			echo "<input class=\"biginput\" name=\"delay\" type=hidden value=\"$delay\">";
		}

		show_table2_v_delimiter($col++);
		echo nbsp(S_KEEP_HISTORY_IN_DAYS);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"history\" value=\"$history\" size=8>";

		show_table2_v_delimiter($col++);
		echo nbsp(S_KEEP_TRENDS_IN_DAYS);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"trends\" value=\"$trends\" size=8>";

		show_table2_v_delimiter($col++);
		echo S_STATUS;
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"status\" value=\"$status\" size=\"1\">";
		echo "<OPTION VALUE=\"0\"";
		if($status==0) echo "SELECTED";
		echo ">".S_MONITORED;
		echo "<OPTION VALUE=\"1\"";
		if($status==1) echo "SELECTED";
		echo ">".S_DISABLED;
#		echo "<OPTION VALUE=\"2\"";
#		if($status==2) echo "SELECTED";
#		echo ">Trapper";
		echo "<OPTION VALUE=\"3\"";
		if($status==3) echo "SELECTED";
		echo ">".S_NOT_SUPPORTED;
		echo "</SELECT>";

		show_table2_v_delimiter($col++);
		echo nbsp(S_TYPE_OF_INFORMATION);
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"value_type\" value=\"$value_type\" size=\"1\">";
		echo "<OPTION VALUE=\"0\"";
		if($value_type==0) echo "SELECTED";
		echo ">".S_NUMERIC;
		echo "<OPTION VALUE=\"1\"";
		if($value_type==1) echo "SELECTED";
		echo ">".S_CHARACTER;
		echo "<OPTION VALUE=\"2\"";
		if($value_type==2) echo "SELECTED";
		echo ">".S_LOG;
		echo "</SELECT>";

		show_table2_v_delimiter($col++);
		echo nbsp(S_STORE_VALUE);
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"delta\" value=\"$delta\" size=\"1\">";
		echo "<OPTION VALUE=\"0\" "; if($delta==0) echo "SELECTED"; echo ">".S_AS_IS;
		echo "<OPTION VALUE=\"1\" "; if($delta==1) echo "SELECTED"; echo ">".S_DELTA_SPEED_PER_SECOND;
		echo "<OPTION VALUE=\"2\" "; if($delta==2) echo "SELECTED"; echo ">".S_DELTA_SIMPLE_CHANGE;
		echo "</SELECT>";

		if($type==2)
		{
			show_table2_v_delimiter($col++);
			echo nbsp(S_ALLOWED_HOSTS);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"trapper_hosts\" value=\"$trapper_hosts\" size=40>";
		}
		else
		{
			echo "<input class=\"biginput\" name=\"trapper_hosts\" type=hidden value=\"$trapper_hosts\">";
		}

 
		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add to all hosts\" onClick=\"return Confirm('Add item to all hosts?');\">";
		if(isset($_GET["itemid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete selected item?');\">";
		}
 
		show_table2_header_end();
	}

	# Insert form for Host Groups
	function	insert_hostgroups_form($groupid)
	{
		global  $_GET;

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
		if(isset($_GET["groupid"]))
		{
			echo "<input name=\"groupid\" type=\"hidden\" value=\"".$_GET["groupid"]."\" size=8>";
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
			if(isset($_GET["groupid"]))
			{
				$sql="select count(*) as count from hosts_groups where hostid=".$row["hostid"]." and groupid=".$_GET["groupid"];
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
		if(isset($_GET["groupid"]))
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
		global  $_GET;

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
			if(isset($_GET["usrgrpid"]))
			{
				$sql="select count(*) as count from users_groups where userid=".$row["userid"]." and usrgrpid=".$_GET["usrgrpid"];
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
			if(isset($_GET["usrgrpid"]))
			{
				$sql="select count(*) as count from users_groups where userid=".$row["userid"]." and usrgrpid=".$_GET["usrgrpid"];
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
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add group\">";
		if(isset($_GET["usrgrpid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update group\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete group\" onClick=\"return Confirm('Delete selected group?');\">";
		}
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
		global	$_GET;

		$col=0;

		show_form_begin("index.login");
		echo "Login";

		show_table2_v_delimiter($col++);
		echo "<form method=\"post\" action=\"index.php\">";

		echo "Login name";
		show_table2_h_delimiter();
//		echo "<input name=\"name\" value=\"".$_GET["name"]."\" size=20>";
		echo "<input class=\"biginput\" name=\"name\" value=\"\" size=20>";

		show_table2_v_delimiter($col++);
		echo "Password";
		show_table2_h_delimiter();
//		echo "<input type=\"password\" name=\"password\" value=\"$password\" size=20>";
		echo "<input class=\"biginput\" type=\"password\" name=\"password\" value=\"\" size=20>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"Enter\">";
		show_table2_header_end();
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
		echo "Trigger configuration";
 
		show_table2_v_delimiter($col++);
		if(isset($hostid))
		{
			echo "<form method=\"get\" action=\"triggers.php?hostid=$hostid\">";
		}
		else
		{
			echo "<form method=\"get\" action=\"triggers.php\">";
		}
		echo "<input class=\"biginput\" name=\"triggerid\" type=hidden value=$triggerid size=8>";
		echo "Description";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"description\" value=\"$description\" size=70>";

		show_table2_v_delimiter($col++);
		echo "Expression";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"expression\" value=\"$expression\" size=70>";

		show_table2_v_delimiter($col++);
		echo "Severity";
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
		echo "Comments";
		show_table2_h_delimiter();
 		echo "<TEXTAREA class=\"biginput\" NAME=\"comments\" COLS=70 ROWS=\"7\" WRAP=\"SOFT\">$comments</TEXTAREA>";

		show_table2_v_delimiter($col++);
		echo "URL";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"url\" value=\"$url\" size=70>";

		show_table2_v_delimiter($col++);
		echo "Disabled";
		show_table2_h_delimiter();
		echo "<INPUT TYPE=\"CHECKBOX\" ";
		if($status==1) { echo " CHECKED "; }
		echo "NAME=\"disabled\"  VALUE=\"true\">";

 
		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
		if(isset($triggerid))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete trigger?');\">";
		}

		if(isset($triggerid))
		{
			show_table2_v_delimiter();
			echo "The trigger depends on";
			show_table2_h_delimiter();
			$sql="select t.triggerid,t.description from triggers t,trigger_depends d where t.triggerid=d.triggerid_up and d.triggerid_down=$triggerid";
			$result1=DBselect($sql);
			echo "<SELECT class=\"biginput\" NAME=\"dependency\" size=\"1\">";
			for($i=0;$i<DBnum_rows($result1);$i++)
			{
				$depid=DBget_field($result1,$i,0);
//				$depdescr=DBget_field($result1,$i,1);
//				if( strstr($depdescr,"%s"))
//				{
					$depdescr=expand_trigger_description($depid);
//				}
				echo "<OPTION VALUE=\"$depid\">$depdescr";
			}
			echo "</SELECT>";

			show_table2_v_delimiter();
			echo "New dependency";
			show_table2_h_delimiter();
			$sql="select t.triggerid,t.description from triggers t where t.triggerid!=$triggerid order by t.description";
			$result=DBselect($sql);
			echo "<SELECT class=\"biginput\" NAME=\"depid\" size=\"1\">";
			for($i=0;$i<DBnum_rows($result);$i++)
			{
				$depid=DBget_field($result,$i,0);
//				$depdescr=DBget_field($result,$i,1);

//				if( strstr($depdescr,"%s"))
//				{
					$depdescr=expand_trigger_description($depid);
//				}
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
		global  $_GET;

		$name=@iif(isset($_GET["name"]),$_GET["name"],"");
		$width=@iif(isset($_GET["width"]),$_GET["width"],900);
		$height=@iif(isset($_GET["height"]),$_GET["height"],200);
		$yaxistype=@iif(isset($_GET["yaxistype"]),$_GET["yaxistype"],GRAPH_YAXIS_TYPE_CALCULATED);
		$yaxismin=@iif(isset($_GET["yaxismin"]),$_GET["yaxismin"],0.00);
		$yaxismax=@iif(isset($_GET["yaxismax"]),$_GET["yaxismax"],100.00);

		if(isset($_GET["graphid"])&&!isset($_GET["name"]))
		{
			$result=DBselect("select g.graphid,g.name,g.width,g.height,g.yaxistype,g.yaxismin,g.yaxismax from graphs g where graphid=".$_GET["graphid"]);
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
		if(isset($_GET["graphid"]))
		{
			echo "<input class=\"biginput\" name=\"graphid\" type=\"hidden\" value=".$_GET["graphid"].">";
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
		if(isset($_GET["graphid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('".S_DELETE_GRAPH_Q."');\">";
		}

		show_table2_header_end();
	}
?>
