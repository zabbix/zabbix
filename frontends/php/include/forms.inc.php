<?php
/*
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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

		show_table2_header_begin();
		echo "User";

		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"users.php\">";
		if(isset($userid))
		{
			echo "<input class=\"biginput\" name=\"userid\" type=\"hidden\" value=\"$userid\" size=8>";
		}
		echo "Alias";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"alias\" value=\"$alias\" size=20>";

		show_table2_v_delimiter();
		echo "Name";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=20>";

		show_table2_v_delimiter();
		echo "Surname";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"surname\" value=\"$surname\" size=20>";

		show_table2_v_delimiter();
		echo "Password";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" type=\"password\" name=\"password1\" value=\"$password\" size=20>";

		show_table2_v_delimiter();
		echo nbsp("Password (once again)");
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" type=\"password\" name=\"password2\" value=\"$password\" size=20>";

		show_table2_v_delimiter();
		echo "URL (after login)";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"url\" value=\"$url\" size=50>";

		show_table2_v_delimiter2();
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
		$port=@iif(isset($_GET["port"]),$_GET["port"],10000);
		$delay=@iif(isset($_GET["delay"]),$_GET["delay"],30);
		$history=@iif(isset($_GET["history"]),$_GET["history"],365);
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

		if(isset($_GET["register"])&&($_GET["register"] == "change"))
		{
			$result=DBselect("select i.description, i.key_, h.host, h.port, i.delay, i.history, i.status, i.type, i.snmp_community,i.snmp_oid,i.value_type,i.trapper_hosts,i.snmp_port,i.units,i.multiplier,h.hostid,i.delta from items i,hosts h where i.itemid=".$_GET["itemid"]." and h.hostid=i.hostid");
		
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
		}

		echo "<br>";

		show_table2_header_begin();
		echo "Item";
 
		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"items.php\">";
		if(isset($_GET["itemid"]))
		{
			echo "<input class=\"biginput\" name=\"itemid\" type=hidden value=".$_GET["itemid"].">";
		}
		echo "Description";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"description\" value=\"$description\"size=40>";

		show_table2_v_delimiter();
		echo "Host";
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

		show_table2_v_delimiter();
		echo "Type";
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"type\" value=\"$type\" size=\"1\" onChange=\"submit()\">";
		echo "<OPTION VALUE=\"0\"";
		if($type==0) echo "SELECTED";
		echo ">Zabbix agent";
		echo "<OPTION VALUE=\"3\"";
		if($type==3) echo "SELECTED";
		echo ">Simple check";
		echo "<OPTION VALUE=\"1\"";
		if($type==1) echo "SELECTED";
		echo ">SNMPv1 agent";
		echo "<OPTION VALUE=\"4\"";
		if($type==4) echo "SELECTED";
		echo ">SNMPv2 agent";
		echo "<OPTION VALUE=\"2\"";
		if($type==2) echo "SELECTED";
		echo ">Zabbix trapper";
		echo "<OPTION VALUE=\"5\"";
		if($type==5) echo "SELECTED";
		echo ">Zabbix internal";
		echo "</SELECT>";

		if(($type==1)||($type==4))
		{ 
			show_table2_v_delimiter();
			echo nbsp("SNMP community");
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"snmp_community\" value=\"$snmp_community\" size=16>";

			show_table2_v_delimiter();
			echo nbsp("SNMP OID");
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"snmp_oid\" value=\"$snmp_oid\" size=40>";

			show_table2_v_delimiter();
			echo nbsp("SNMP port");
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"snmp_port\" value=\"$snmp_port\" size=5>";
		}
		else
		{
			echo "<input class=\"biginput\" name=\"snmp_community\" type=hidden value=\"$snmp_community\">";
			echo "<input class=\"biginput\" name=\"snmp_oid\" type=hidden value=\"$snmp_oid\">";
			echo "<input class=\"biginput\" name=\"snmp_port\" type=hidden value=\"$snmp_port\">";
		}

		show_table2_v_delimiter();
		echo "Key";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"key\" value=\"$key\" size=40>";

		show_table2_v_delimiter();
		echo "Units";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"units\" value=\"$units\" size=10>";

		show_table2_v_delimiter();
		echo "Multiplier";
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"multiplier\" value=\"$multiplier\" size=\"1\">";
		echo "<OPTION VALUE=\"0\"";
		if($multiplier==0) echo "SELECTED";
		echo ">-";
		echo "<OPTION VALUE=\"1\"";
		if($multiplier==1) echo "SELECTED";
		echo ">K (1024)";
		echo "<OPTION VALUE=\"2\"";
		if($multiplier==2) echo "SELECTED";
		echo ">M (1024^2)";
		echo "<OPTION VALUE=\"3\"";
		if($multiplier==3) echo "SELECTED";
		echo ">G (1024^3)";
		echo "</SELECT>";

		if($type!=2)
		{
			show_table2_v_delimiter();
			echo nbsp("Update interval (in sec)");
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"delay\" value=\"$delay\" size=5>";
		}
		else
		{
			echo "<input class=\"biginput\" name=\"delay\" type=hidden value=\"$delay\">";
		}

		show_table2_v_delimiter();
		echo nbsp("Keep history (in days)");
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"history\" value=\"$history\" size=8>";

		show_table2_v_delimiter();
		echo "Status";
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"status\" value=\"$status\" size=\"1\">";
		echo "<OPTION VALUE=\"0\"";
		if($status==0) echo "SELECTED";
		echo ">Monitored";
		echo "<OPTION VALUE=\"1\"";
		if($status==1) echo "SELECTED";
		echo ">Disabled";
#		echo "<OPTION VALUE=\"2\"";
#		if($status==2) echo "SELECTED";
#		echo ">Trapper";
		echo "<OPTION VALUE=\"3\"";
		if($status==3) echo "SELECTED";
		echo ">Not supported";
		echo "</SELECT>";

		show_table2_v_delimiter();
		echo nbsp("Type of information");
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"value_type\" value=\"$value_type\" size=\"1\">";
		echo "<OPTION VALUE=\"0\"";
		if($value_type==0) echo "SELECTED";
		echo ">Numeric";
		echo "<OPTION VALUE=\"1\"";
		if($value_type==1) echo "SELECTED";
		echo ">Character";
		echo "</SELECT>";

		show_table2_v_delimiter();
		echo nbsp("Store value");
		show_table2_h_delimiter();
		echo "<SELECT class=\"biginput\" NAME=\"delta\" value=\"$delta\" size=\"1\">";
		echo "<OPTION VALUE=\"0\" "; if($delta==0) echo "SELECTED"; echo ">As is";
		echo "<OPTION VALUE=\"1\" "; if($delta==1) echo "SELECTED"; echo ">Delta (speed per second)";
		echo "<OPTION VALUE=\"2\" "; if($delta==2) echo "SELECTED"; echo ">Delta (simple change)";
		echo "</SELECT>";

		if($type==2)
		{
			show_table2_v_delimiter();
			echo nbsp("Allowed hosts");
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

		if(isset($groupid))
		{
			$groupid=get_group_by_groupid($groupid);
	
			$name=$groupid["name"];
		}
		else
		{
			$name="";
		}

		show_table2_header_begin();
		echo "Host group";

		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"hosts.php\">";
		if(isset($_GET["groupid"]))
		{
			echo "<input name=\"groupid\" type=\"hidden\" value=\"".$_GET["groupid"]."\" size=8>";
		}
		echo "Group name";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=30>";

		show_table2_v_delimiter();
		echo "Hosts";
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
		}
		echo "</form>";
		show_table2_header_end();
	}

	# Insert form for User Groups
	function	insert_usergroups_form($usrgrpid)
	{
		global  $_GET;

		if(isset($usrgrpid))
		{
			$usrgrp=get_usergroup_by_usrgrpid($usrgrpid);
	
			$name=$usrgrp["name"];
		}
		else
		{
			$name="";
		}

		show_table2_header_begin();
		echo "User group";

		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"users.php\">";
		if(isset($usrgrpid))
		{
			echo "<input name=\"usrgrpid\" type=\"hidden\" value=\"$usrgrpid\" size=8>";
		}
		echo "Group name";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=30>";

		show_table2_v_delimiter();
		echo "Users";
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
		echo "</select>";

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
		echo "<br>";

		show_table2_header_begin();
		echo "New permission";

		show_table2_v_delimiter();
		echo "<form method=\"get\" action=\"users.php\">";
		if(isset($userid))
		{
			echo "<input name=\"userid\" type=\"hidden\" value=\"$userid\" size=8>";
		}
		echo "Resource";
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
		echo "Permission";
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

		show_table2_header_begin();
		echo "Login";

		show_table2_v_delimiter();
		echo "<form method=\"post\" action=\"index.php\">";

		echo "Login name";
		show_table2_h_delimiter();
//		echo "<input name=\"name\" value=\"".$_GET["name"]."\" size=20>";
		echo "<input class=\"biginput\" name=\"name\" value=\"\" size=20>";

		show_table2_v_delimiter();
		echo "Password";
		show_table2_h_delimiter();
//		echo "<input type=\"password\" name=\"password\" value=\"$password\" size=20>";
		echo "<input class=\"biginput\" type=\"password\" name=\"password\" value=\"\" size=20>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" class=\"button\" type=\"submit\" name=\"register\" value=\"Enter\">";
		show_table2_header_end();
	}

	# Insert form for Problem
	function	insert_problem_form($problemid)
	{
		echo "<br>";

		show_table2_header_begin();
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
		
		echo "<br>";

		show_table2_header_begin();
		echo "Trigger configuration";
 
		show_table2_v_delimiter();
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

		show_table2_v_delimiter();
		echo "Expression";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"expression\" value=\"$expression\" size=70>";

		show_table2_v_delimiter();
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

		show_table2_v_delimiter();
		echo "Comments";
		show_table2_h_delimiter();
 		echo "<TEXTAREA class=\"biginput\" NAME=\"comments\" COLS=70 ROWS=\"7\" WRAP=\"SOFT\">$comments</TEXTAREA>";

		show_table2_v_delimiter();
		echo "URL";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"url\" value=\"$url\" size=70>";

		show_table2_v_delimiter();
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
?>
