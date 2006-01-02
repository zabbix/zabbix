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

		$col=0;

		show_form_begin("hosts");
		echo S_TEMPLATE;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"hosts.php\">";
		echo "<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"".$_REQUEST["config"]."\" size=8>";
		echo "<input class=\"biginput\" name=\"hostid\" type=\"hidden\" value=\"".$_REQUEST["hostid"]."\" size=8>";
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
		echo S_GRAPHS;
		show_table2_h_delimiter();
		echo "<input type=checkbox ".iif((1&$graphs)==1,"checked","")." name=\"graphs_add\" \">".S_ADD;
		echo "<input type=checkbox ".iif((2&$graphs)==2,"checked","")." name=\"graphs_update\" \">".S_UPDATE;
		echo "<input type=checkbox ".iif((4&$graphs)==4,"checked","")." name=\"graphs_delete\" \">".S_DELETE;

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

		$col=0;

		show_form_begin("users.users");
		echo S_USER;

		if($profile==0) echo "<form method=\"get\" action=\"users.php\">";
		else echo "<form method=\"get\" action=\"profile.php\">";

		echo "<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"".$_REQUEST["config"]."\" size=8>";
		if(isset($userid))
		{
			echo "<input class=\"biginput\" name=\"userid\" type=\"hidden\" value=\"$userid\" size=8>";
		}

		if($profile==0)
		{
			show_table2_v_delimiter($col++);
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
		}

		show_table2_v_delimiter($col++);
		echo S_PASSWORD;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" type=\"password\" name=\"password1\" value=\"$password\" size=20>";

		show_table2_v_delimiter($col++);
		echo nbsp(S_PASSWORD_ONCE_AGAIN);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" type=\"password\" name=\"password2\" value=\"$password\" size=20>";

		$languages=array(	"en_gb"=>S_ENGLISH_GB,
					"cn_zh"=>S_CHINESE_CN,
					"fr_fr"=>S_FRENCH_FR,
					"de_de"=>S_GERMAN_DE,
					"it_it"=>S_ITALIAN_IT,
					"lv_lv"=>S_LATVIAN_LV,
					"ru_ru"=>S_RUSSIAN_RU,
					"sp_sp"=>S_SPANISH_SP,
					"ja_jp"=>S_JAPANESE_JP
				);

		show_table2_v_delimiter($col++);
		echo S_LANGUAGE;
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"lang\" value=\"$lang\">";
		foreach($languages as $l=>$language)
		{
			echo "<OPTION VALUE=\"$l\""; if($lang==$l) echo "SELECTED"; echo ">".$language;
		}
		echo "</SELECT>";

		show_table2_v_delimiter($col++);
		echo S_AUTO_LOGOUT_IN_SEC;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"autologout\" value=\"$autologout\" size=5>";

		show_table2_v_delimiter($col++);
		echo S_URL_AFTER_LOGIN;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"url\" value=\"$url\" size=50>";

		show_table2_v_delimiter($col++);
		echo S_SCREEN_REFRESH;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"refresh\" value=\"$refresh\" size=5>";

		show_table2_v_delimiter2($col++);
		if($profile==0)
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
			if(isset($userid))
			{
				echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
				echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete selected user?');\">";
			}
		}
		else
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update profile\">";
		}

		show_table2_header_end();
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

		show_form_begin("items.item");
		echo S_ITEM;

		$col=0; 
		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"items.php#form\">";
		if(isset($_REQUEST["itemid"]))
		{
			echo "<input class=\"biginput\" name=\"itemid\" type=hidden value=".$_REQUEST["itemid"].">";
		}
		echo S_DESCRIPTION;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"description\" value=\"$description\"size=40>";

		show_table2_v_delimiter($col++);
		echo S_HOST;
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"hostid\" value=\"3\">";
	        $result=DBselect("select hostid,host from hosts where status not in (".HOST_STATUS_DELETED.")order by host");
		while($row=DBfetch($result))
	        {
	                $hostid_=$row["hostid"];
	                $host_=$row["host"];
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

		if( ($value_type==ITEM_VALUE_TYPE_FLOAT) || ($value_type==ITEM_VALUE_TYPE_UINT64))
		{
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
		}
		else
		{
			echo "<input class=\"biginput\" name=\"units\" type=hidden value=\"$units\">";
			echo "<input class=\"biginput\" name=\"multiplier\" type=hidden value=\"0\">";
		}

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
		echo "<SELECT class=\"biginput\" NAME=\"value_type\" value=\"$value_type\" size=\"1\" onChange=\"submit()\">";
		echo "<OPTION VALUE=\"0\"";
		if($value_type==ITEM_VALUE_TYPE_FLOAT) echo "SELECTED";
		echo ">".S_NUMERIC_FLOAT;
		echo "<OPTION VALUE=\"3\"";
		if($value_type==ITEM_VALUE_TYPE_UINT64) echo "SELECTED";
		echo ">".S_NUMERIC_UINT64;
		echo "<OPTION VALUE=\"1\"";
		if($value_type==ITEM_VALUE_TYPE_STR) echo "SELECTED";
		echo ">".S_CHARACTER;
		echo "<OPTION VALUE=\"2\"";
		if($value_type==ITEM_VALUE_TYPE_LOG) echo "SELECTED";
		echo ">".S_LOG;
		echo "</SELECT>";

		if($value_type==ITEM_VALUE_TYPE_LOG)
		{
			show_table2_v_delimiter($col++);
			echo nbsp(S_LOG_TIME_FORMAT);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"logtimefmt\" value=\"$logtimefmt\" size=16>";
		}
		else
		{
			echo "<input class=\"biginput\" name=\"logtimefmt\" type=hidden value=\"$logtimefmt\">";
		}

		if( ($value_type==ITEM_VALUE_TYPE_FLOAT) || ($value_type==ITEM_VALUE_TYPE_UINT64))
		{
			show_table2_v_delimiter($col++);
			echo nbsp(S_STORE_VALUE);
			show_table2_h_delimiter();
			echo "<SELECT class=\"biginput\" NAME=\"delta\" value=\"$delta\" size=\"1\">";
			echo "<OPTION VALUE=\"0\" "; if($delta==0) echo "SELECTED"; echo ">".S_AS_IS;
			echo "<OPTION VALUE=\"1\" "; if($delta==1) echo "SELECTED"; echo ">".S_DELTA_SPEED_PER_SECOND;
			echo "<OPTION VALUE=\"2\" "; if($delta==2) echo "SELECTED"; echo ">".S_DELTA_SIMPLE_CHANGE;
			echo "</SELECT>";
		}
		else
		{
			echo "<input class=\"biginput\" name=\"delta\" type=hidden value=\"1\">";
		}

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
		if(isset($_REQUEST["itemid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete selected item?');\">";
		}

		show_table2_v_delimiter($col++);
		echo S_GROUP;
		show_table2_h_delimiter();
		$h2="";
	        $h2=$h2."<select class=\"biginput\" name=\"groupid\" onChange=\"submit()\">";

	        $result=DBselect("select groupid,name from groups order by name");
	        while($row=DBfetch($result))
	        {
// Check if at least one host with read permission exists for this group
	                $result2=DBselect("select h.hostid,h.host from hosts h,hosts_groups hg where hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid and h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host order by h.host");
	                $cnt=0;
	                while($row2=DBfetch($result2))
	                {
	                        if(!check_right("Host","U",$row2["hostid"]))
	                        {
	                                continue;
	                        }
	                        $cnt=1; break;
	                }
	                if($cnt!=0)
       	         {
	                        $h2=$h2.form_select("groupid",$row["groupid"],$row["name"]);
	                }
	        }
		$h2=$h2."</select>";
		echo $h2;

		show_table2_v_delimiter2();
		echo "<select class=\"biginput\" name=\"action\">";
		echo "<option value=\"add to group\">".S_ADD_TO_GROUP;
		if(isset($_REQUEST["itemid"]))
		{
			echo "<option value=\"update in group\">".S_UPDATE_IN_GROUP;
			echo "<option value=\"delete from group\">".S_DELETE_FROM_GROUP;
		}
		echo "</select>";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"do\">";
 
		show_table2_header_end();
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
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add group\">";
		if(isset($_REQUEST["usrgrpid"]))
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
		global	$_REQUEST;

		$col=0;

		show_form_begin("index.login");
		echo "Login";

		show_table2_v_delimiter($col++);
		echo "<form method=\"post\" action=\"index.php\">";

		echo "Login name";
		show_table2_h_delimiter();
//		echo "<input name=\"name\" value=\"".$_REQUEST["name"]."\" size=20>";
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
		echo S_TRIGGER;
 
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
			$hopstid=$row["hostid"];
		}
		else
		{
			$pattern="*";
			$priority=10;
			$hostid=0;
		}

		$col=0;

		show_form_begin("autoregistration");
		echo S_AUTOREGISTRATION;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"config.php\">";
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

		echo "<select class=\"biginput\" name=\"hostid\">";
		echo form_select("hostid",0,S_SELECT_HOST_DOT_DOT_DOT);

		$sql="select h.hostid,h.host from hosts h where h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host order by h.host";

		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			echo form_select("hostid",$row["hostid"],$row["host"]);
		}
		echo "</select>";

		show_table2_v_delimiter2($col++);
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add autoregistration\">";
		if(isset($id))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update autoregistration\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete autoregistration\" onClick=\"return Confirm('Delete selected autoregistration rule?');\">";
		}

		show_table2_header_end();
	}

	function	insert_action_form()
	{
		global  $_REQUEST;

		if(isset($_REQUEST["actionid"]))
		{
			$action=get_action_by_actionid($_REQUEST["actionid"]);

			$actionid=$action["actionid"];
			$actiontype=$action["actiontype"];
			$source=$action["source"];
			$delay=$action["delay"];
			// Otherwise symbols like ",' will not be shown
			$subject=htmlspecialchars($action["subject"]);
			$message=$action["message"];
			$uid=$action["userid"];
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
			$source=0;
			$actiontype=0;
			$filter_trigger_name="";
			$filter_triggerid=0;
			$filter_groupid=0;
			$filter_hostid=0;
			$description="";

	//		$delay=30;
			$delay=@iif(isset($_REQUEST["delay"]),$_REQUEST["delay"],30);
//		$subject=$description;
			$subject=@iif(isset($_REQUEST["subject"]),$_REQUEST["subject"],"{TRIGGER.NAME}: {STATUS}");
			$message=@iif(isset($_REQUEST["message"]),$_REQUEST["message"],"{TRIGGER.NAME}: {STATUS}");
			$scope=@iif(isset($_REQUEST["scope"]),$_REQUEST["scope"],0);
			$recipient=@iif(isset($_REQUEST["recipient"]),$_REQUEST["recipient"],RECIPIENT_TYPE_GROUP);
//		$severity=0;
			$severity=@iif(isset($_REQUEST["severity"]),$_REQUEST["severity"],0);
			$maxrepeats=@iif(isset($_REQUEST["maxrepeats"]),$_REQUEST["maxrepeats"],0);
			$repeatdelay=@iif(isset($_REQUEST["repeatdelay"]),$_REQUEST["repeatdelay"],600);
			$repeat=@iif(isset($_REQUEST["repeat"]),$_REQUEST["repeat"],0);
		}

		$conditiontype=@iif(isset($_REQUEST["conditiontype"]),$_REQUEST["conditiontype"],0);


		show_form_begin("actions.action");
		echo nbsp(S_ACTION);
		$col=0;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"actionconf.php\">";
		if(isset($_REQUEST["actionid"]))
		{
			echo "<input name=\"actionid\" type=\"hidden\" value=".$_REQUEST["actionid"].">";
		}
		echo nbsp(S_SOURCE);
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"source\" size=1>";
		echo "<OPTION VALUE=\"0\""; if($source==0) echo "SELECTED"; echo ">".S_TRIGGER;
		echo "</SELECT>";

		show_table2_v_delimiter($col);
		echo nbsp(S_CONDITIONS);
		show_table2_h_delimiter();
		$found=0;
		for($i=1;$i<=1000;$i++)
		{
			if(isset($_REQUEST["conditiontype$i"]))
			{
				echo "<input type=checkbox name=\"conditionchecked$i\">".get_condition_desc($_REQUEST["conditiontype$i"],$_REQUEST["conditionop$i"],$_REQUEST["conditionvalue$i"]);
				echo "<br>";
				$found=1;
			}
		}

		for($i=1;$i<=1000;$i++)
		{
			if(isset($_REQUEST["conditiontype$i"]))
			{
				echo "<input name=\"conditiontype$i\" type=\"hidden\" value=\"".$_REQUEST["conditiontype$i"]."\">";
				echo "<input name=\"conditionop$i\" type=\"hidden\" value=\"".$_REQUEST["conditionop$i"]."\">";
				echo "<input name=\"conditionvalue$i\" type=\"hidden\" value=\"".$_REQUEST["conditionvalue$i"]."\">";
			}
		}
		if($found==0) echo S_NO_CONDITIONS_DEFINED;

		show_table2_v_delimiter($col++);
		echo nbsp(" ");
		show_table2_h_delimiter();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete selected\">";

//		show_table2_v_delimiter($col);
//		echo nbsp("                                        "."Condition");
//		show_table2_h_delimiter();
		$h2="<select class=\"biginput\" name=\"conditiontype\" onChange=\"submit()\">";
		$h2=$h2.form_select("conditiontype",CONDITION_TYPE_GROUP,S_HOST_GROUP);
		$h2=$h2.form_select("conditiontype",CONDITION_TYPE_HOST,S_HOST);
		$h2=$h2.form_select("conditiontype",CONDITION_TYPE_TRIGGER,S_TRIGGER);
		$h2=$h2.form_select("conditiontype",CONDITION_TYPE_TRIGGER_NAME,S_TRIGGER_NAME);
		$h2=$h2.form_select("conditiontype",CONDITION_TYPE_TRIGGER_SEVERITY,S_TRIGGER_SEVERITY);
		$h2=$h2.form_select("conditiontype",CONDITION_TYPE_TRIGGER_VALUE,S_TRIGGER_VALUE);
		$h2=$h2.form_select("conditiontype",CONDITION_TYPE_TIME_PERIOD,S_TIME_PERIOD);
		$h2=$h2."</SELECT>";
//		echo $h2;

		$h2=$h2."<select class=\"biginput\" name=\"operator\">";
		if(in_array($conditiontype,array(CONDITION_TYPE_GROUP, CONDITION_TYPE_HOST,CONDITION_TYPE_TRIGGER,CONDITION_TYPE_TRIGGER_SEVERITY,CONDITION_TYPE_TRIGGER_VALUE)))
			$h2=$h2.form_select("operator",CONDITION_OPERATOR_EQUAL,"=");
		if(in_array($conditiontype,array(CONDITION_TYPE_GROUP, CONDITION_TYPE_HOST,CONDITION_TYPE_TRIGGER,CONDITION_TYPE_TRIGGER_SEVERITY)))
			$h2=$h2.form_select("operator",CONDITION_OPERATOR_NOT_EQUAL,"<>");
		if(in_array($conditiontype,array(CONDITION_TYPE_TRIGGER_NAME)))
			$h2=$h2.form_select("operator",CONDITION_OPERATOR_LIKE,"like");
		if(in_array($conditiontype,array(CONDITION_TYPE_TRIGGER_NAME)))
			$h2=$h2.form_select("operator",CONDITION_OPERATOR_NOT_LIKE,"not like");
		if(in_array($conditiontype,array(CONDITION_TYPE_TIME_PERIOD)))
			$h2=$h2.form_select("operator",CONDITION_OPERATOR_IN,"in");
		if(in_array($conditiontype,array(CONDITION_TYPE_TRIGGER_SEVERITY)))
			$h2=$h2.form_select("operator",CONDITION_OPERATOR_MORE_EQUAL,">=");
		$h2=$h2."</SELECT>";

		show_table2_v_delimiter($col);
		echo nbsp(S_CONDITION);
		show_table2_h_delimiter();

		if($conditiontype == CONDITION_TYPE_GROUP)
		{
			$h2=$h2."<select class=\"biginput\" name=\"value\">";
			$result=DBselect("select groupid,name from groups order by name");
			while($row=DBfetch($result))
			{
				$h2=$h2.form_select("value",$row["groupid"],$row["name"]);
			}
			$h2=$h2."</SELECT>";
		}
		else if($conditiontype == CONDITION_TYPE_HOST)
		{
			$h2=$h2."<select class=\"biginput\" name=\"value\">";
			$result=DBselect("select hostid,host from hosts order by host");
			while($row=DBfetch($result))
			{
				$h2=$h2.form_select("value",$row["hostid"],$row["host"]);
			}
			$h2=$h2."</SELECT>";
		}
		else if($conditiontype == CONDITION_TYPE_TRIGGER)
		{
			$h2=$h2."<select class=\"biginput\" name=\"value\">";
			$result=DBselect("select distinct h.host,t.triggerid,t.description from triggers t, functions f,items i, hosts h where t.triggerid=f.triggerid and f.itemid=i.itemid and h.hostid=i.hostid order by h.host");
			while($row=DBfetch($result))
			{
				$h2=$h2.form_select("value",$row["triggerid"],$row["host"].":&nbsp;".$row["description"]);
			}
			$h2=$h2."</SELECT>";
		}
		else if($conditiontype == CONDITION_TYPE_TRIGGER_NAME)
		{
			$h2=$h2."<input class=\"biginput\" name=\"value\" value=\"\" size=40>";
		}
		else if($conditiontype == CONDITION_TYPE_TRIGGER_VALUE)
		{
			$h2=$h2."<select class=\"biginput\" name=\"value\">";
			$h2=$h2.form_select("value",0,"OFF");
			$h2=$h2.form_select("value",1,"ON");
			$h2=$h2."</SELECT>";
		}
		else if($conditiontype == CONDITION_TYPE_TIME_PERIOD)
		{
			$h2=$h2."<input class=\"biginput\" name=\"value\" value=\"1-7,00:00-23:59\" size=40>";
		}
		else if($conditiontype == CONDITION_TYPE_TRIGGER_SEVERITY)
		{
			$h2=$h2."<select class=\"biginput\" name=\"severity\" size=1>";
			$h2=$h2."<OPTION VALUE=\"0\">".S_NOT_CLASSIFIED;
			$h2=$h2."<OPTION VALUE=\"1\">".S_INFORMATION;
			$h2=$h2."<OPTION VALUE=\"2\">".S_WARNING;
			$h2=$h2."<OPTION VALUE=\"3\">".S_AVERAGE;
			$h2=$h2."<OPTION VALUE=\"4\">".S_HIGH;
			$h2=$h2."<OPTION VALUE=\"5\">".S_DISASTER;
			$h2=$h2."</SELECT>";
		}
		echo $h2;

		show_table2_v_delimiter($col++);
		echo nbsp(" ");
		show_table2_h_delimiter();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add condition\">";

		show_table2_v_delimiter($col++);
		echo nbsp(S_ACTION_TYPE);
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"actiontype\" size=\"1\" onChange=\"submit()\">";
		echo "<option value=\"0\""; if($actiontype==0) echo " selected"; echo ">".S_SEND_MESSAGE;
		echo "<option value=\"1\""; if($actiontype==1) echo " selected"; echo ">".S_REMOTE_COMMAND;
		echo "</select>";


		show_table2_v_delimiter($col++);
		echo nbsp(S_SEND_MESSAGE_TO);
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"recipient\" size=\"1\" onChange=\"submit()\">";

		echo "<option value=\"0\""; if($recipient==RECIPIENT_TYPE_USER) echo " selected"; echo ">".S_SINGLE_USER;
		echo "<option value=\"1\""; if($recipient==RECIPIENT_TYPE_GROUP) echo " selected"; echo ">".S_USER_GROUP;
		echo "</select>";

		if($recipient==RECIPIENT_TYPE_GROUP)
		{
			show_table2_v_delimiter($col++);
			echo nbsp(S_GROUP);
			show_table2_h_delimiter();
			echo "<select class=\"biginput\" name=\"usrgrpid\" size=\"1\">";
	
			$sql="select usrgrpid,name from usrgrp order by name";
			$result=DBselect($sql);
			while($row=DBfetch($result))
			{
//			if(isset($usrgrpid) && ($row["usrgrpid"] == $usrgrpid))
				if(isset($uid) && ($row["usrgrpid"] == $uid))
				{
					echo "<option value=\"".$row["usrgrpid"]."\" selected>".$row["name"];
				}
				else
				{
					echo "<option value=\"".$row["usrgrpid"]."\">".$row["name"];
				}
			}
			echo "</select>";
		}
		else
		{
			show_table2_v_delimiter($col++);
			echo nbsp(S_USER);
			show_table2_h_delimiter();
			echo "<select class=\"biginput\" name=\"userid\" size=\"1\">";
	
			$sql="select userid,alias from users order by alias";
			$result=DBselect($sql);
			while($row=DBfetch($result))
			{
				if(isset($uid) && ($row["userid"] == $uid))
				{
					echo "<option value=\"".$row["userid"]."\" selected>".$row["alias"];
				}
				else
				{
					echo "<option value=\"".$row["userid"]."\">".$row["alias"];
				}
			}
			echo "</select>";
		}
	

		show_table2_v_delimiter($col++);
		echo nbsp(S_DELAY_BETWEEN_MESSAGES_IN_SEC);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"delay\" value=\"$delay\" size=5>";

		show_table2_v_delimiter($col++);
		echo S_SUBJECT;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"subject\" value=\"$subject\" size=80>";

		show_table2_v_delimiter($col++);
		echo S_MESSAGE;
		show_table2_h_delimiter();
	 	echo "<textarea class=\"biginput\" name=\"message\" cols=77 ROWS=\"7\" wrap=\"soft\">$message</TEXTAREA>";

		show_table2_v_delimiter($col++);
		echo nbsp(S_REPEAT);
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"repeat\" size=\"1\" onChange=\"submit()\">";
	
		echo "<option value=\"0\""; if($repeat==0) echo " selected"; echo ">".S_NO_REPEATS;
		echo "<option value=\"1\""; if($repeat==1) echo " selected"; echo ">".S_REPEAT;
		echo "</select>";

		if($repeat>0)
		{
			show_table2_v_delimiter($col++);
			echo S_NUMBER_OF_REPEATS;
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"maxrepeats\" value=\"$maxrepeats\" size=2>";
	
			show_table2_v_delimiter($col++);
			echo S_DELAY_BETWEEN_REPEATS;
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"repeatdelay\" value=\"$repeatdelay\" size=2>";
		}
	
		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
		if(isset($actionid))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete selected action?');\">";
		}
	
		show_table2_header_end();
	}
?>
