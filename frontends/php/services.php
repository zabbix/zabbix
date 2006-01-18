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
	include "include/config.inc.php";

	$page["title"] = "S_IT_SERVICES";
	$page["file"] = "services.php";

	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	if(!check_anyright("Service","U"))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}
	update_profile("web.menu.config.last",$page["file"]);
?>

<?php
	if(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="update")
		{
			$result=@update_service($_REQUEST["serviceid"],$_REQUEST["name"],$_REQUEST["triggerid"],$_REQUEST["linktrigger"],$_REQUEST["algorithm"],$_REQUEST["showsla"],$_REQUEST["goodsla"],$_REQUEST["sortorder"]);
			show_messages($result, S_SERVICE_UPDATED, S_CANNOT_UPDATE_SERVICE);
		}
		if($_REQUEST["register"]=="add")
		{
			$result=@add_service($_REQUEST["serviceid"],$_REQUEST["name"],$_REQUEST["triggerid"],$_REQUEST["linktrigger"],$_REQUEST["algorithm"],$_REQUEST["showsla"],$_REQUEST["goodsla"],$_REQUEST["goodsla"]);
			show_messages($result, S_SERVICE_ADDED, S_CANNOT_ADD_SERVICE);
		}
		if($_REQUEST["register"]=="add server")
		{
			$result=add_host_to_services($_REQUEST["serverid"],$_REQUEST["serviceid"]);
			show_messages($result, S_TRIGGER_ADDED, S_CANNOT_ADD_TRIGGER);
		}
		if($_REQUEST["register"]=="add link")
		{
			if(!isset($_REQUEST["softlink"]))
			{
				$_REQUEST["softlink"]=0;
			}
			else
			{
				$_REQUEST["softlink"]=1;
			}
			$result=add_service_link($_REQUEST["servicedownid"],$_REQUEST["serviceupid"],$_REQUEST["softlink"]);
			show_messages($result, S_LINK_ADDED, S_CANNOT_ADD_LINK);
		}
		if($_REQUEST["register"]=="delete")
		{
			$result=delete_service($_REQUEST["serviceid"]);
			show_messages($result, S_SERVICE_DELETED, S_CANNOT_DELETE_SERVICE);
			unset($_REQUEST["serviceid"]);
		}
		if($_REQUEST["register"]=="delete_link")
		{
			$result=delete_service_link($_REQUEST["linkid"]);
			show_messages($result, S_LINK_DELETED, S_CANNOT_DELETE_LINK);
		}
		if($_REQUEST["register"]=="Delete selected")
		{
			$result=DBselect("select serviceid from services");
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(isset($_REQUEST[$row["serviceid"]]))
				{
					delete_service($row["serviceid"]);
					if(isset($_REQUEST["serviceid"]))
					{
						if($row["serviceid"]==$_REQUEST["serviceid"])
							unset($_REQUEST["serviceid"]);
					}
				}
			}
			show_messages(TRUE, S_SERVICES_DELETED, S_CANNOT_DELETE_SERVICES);
		}
	}
?>

<?php
	show_table_header(S_IT_SERVICES_BIG);

	$now=time();
	$result=DBselect("select serviceid,name,algorithm from services order by sortorder,name");
	echo "<table border=0 width=100% bgcolor='#AAAAAA' cellspacing=1 cellpadding=3>";
	echo "<tr bgcolor='#CCCCCC'>";
	echo "<td><b>".S_ID."</b></td>";
	echo "<td><b>".S_SERVICE."</b></td>";
	echo "<td width=20%><b>".S_STATUS_CALCULATION."</b></td>";
	echo "</tr>";

	echo "<form method=\"get\" action=\"services.php\">";
	if(isset($_REQUEST["serviceid"]))
	{
		echo "<input class=\"biginput\" name=\"serviceid\" type=hidden value=".$_REQUEST["serviceid"]." size=8>";
	}

	$col=0;
	if(isset($_REQUEST["serviceid"]))
	{
		echo "<tr bgcolor=#EEEEEE>";

		$service=get_service_by_serviceid($_REQUEST["serviceid"]);

		$input="<INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"".$service["serviceid"]."\"> ".$service["serviceid"];
		echo "<td>$input</td>";

		echo "<td><b><a href=\"services.php?serviceid=".$service["serviceid"]."#form\">".$service["name"]."</a></b></td>";
		if($service["algorithm"] == SERVICE_ALGORITHM_NONE)
		{
			echo "<td>".S_NONE."</td>";
		}
		else if($service["algorithm"] == SERVICE_ALGORITHM_MAX)
		{
			echo "<td>".S_MAX_OF_CHILDS."</td>";
		}
		else if($service["algorithm"] == SERVICE_ALGORITHM_MIN)
		{
			echo "<td>".S_MIN_OF_CHILDS."</td>";
		}
		else
		{
			echo "<td>".S_UNKNOWN."</td>";
		}
		echo "</tr>"; 
		$col++;
	}
	while($row=DBfetch($result))
	{
		if(!isset($_REQUEST["serviceid"]) && service_has_parent($row["serviceid"]))
		{
			continue;
		}
		if(isset($_REQUEST["serviceid"]) && service_has_no_this_parent($_REQUEST["serviceid"],$row["serviceid"]))
		{
			continue;
		}
		if(isset($_REQUEST["serviceid"])&&($_REQUEST["serviceid"]==$row["serviceid"]))
		{
			echo "<tr bgcolor=#99AABB>";
		}
		else
		{
			if($col++%2==0)	{ echo "<tr bgcolor=#EEEEEE>"; }
			else		{ echo "<tr bgcolor=#DDDDDD>"; }
		}

		$input="<INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"".$row["serviceid"]."\"> ".$row["serviceid"];

		echo "<td>$input</td>";

		$childs=get_num_of_service_childs($row["serviceid"]);
		if(isset($_REQUEST["serviceid"]))
		{
			echo "<td> - <a href=\"services.php?serviceid=".$row["serviceid"]."#form\">".$row["name"]." [$childs]</a></td>";
		}
		else
		{
			echo "<td><a href=\"services.php?serviceid=".$row["serviceid"]."#form\">".$row["name"]." [$childs]</a></td>";
		}
		if($row["algorithm"] == SERVICE_ALGORITHM_NONE)
		{
			echo "<td>".S_NONE."</td>";
		}
		else if($row["algorithm"] == SERVICE_ALGORITHM_MAX)
		{
			echo "<td>".S_MAX_OF_CHILDS."</td>";
		}
		else if($row["algorithm"] == SERVICE_ALGORITHM_MIN)
		{
			echo "<td>".S_MIN_OF_CHILDS."</td>";
		}
		else
		{
			echo "<td>".S_UNKNOWN."</td>";
		}
		echo "</tr>";
	}
	echo "</table>";
?>

<?php
		show_form_begin();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"Delete selected\" onClick=\"return Confirm('".S_DELETE_SELECTED_SERVICES."');\">";
		show_table2_header_end();
		echo "</form>";
?>

<?php
	if(isset($_REQUEST["serviceid"]))
	{
		show_table_header("LINKS");
		echo "<table border=0 width=100% bgcolor='#AAAAAA' cellspacing=1 cellpadding=3>";
		echo "<tr bgcolor='#CCCCCC'>";
		echo "<td><b>".S_SERVICE_1."</b></td>";
		echo "<td><b>".S_SERVICE_2."</b></td>";
		echo "<td><b>".S_SOFT_HARD_LINK."</b></td>";
		echo "<td><b>".S_ACTIONS."</b></td>";
		echo "</tr>";
		$sql="select linkid,servicedownid,serviceupid,soft from services_links where serviceupid=".$_REQUEST["serviceid"]." or servicedownid=".$_REQUEST["serviceid"];
		$result=DBselect($sql);
		$col=0;
		while($row=DBfetch($result))
		{
			if($col++%2==0)	{ echo "<tr bgcolor=#EEEEEE>"; }
			else		{ echo "<tr bgcolor=#DDDDDD>"; }
			$service=get_service_by_serviceid($row["serviceupid"]);
			echo "<td>".$service["name"]."</td>";
			$service=get_service_by_serviceid($row["servicedownid"]);
			echo "<td>".$service["name"]."</td>";
			if($row["soft"] == 0)
			{	
				echo "<td>".S_HARD."</td>";
			}
			else
			{
				echo "<td>".S_SOFT."</td>";
			}
			echo "<td><a href=\"services.php?register=delete_link&serviceid=".$_REQUEST["serviceid"]."&linkid=".$row["linkid"]."\">".S_DELETE."</a></td>";
			echo "</tr>";
		}
		echo "</table>";
	}
?>

<?php
	if(isset($_REQUEST["serviceid"]))
	{
		$service=get_service_by_serviceid($_REQUEST["serviceid"]);
		$triggerid=$service["triggerid"];
		$name=$service["name"];
		$algorithm=$service["algorithm"];
		$showsla=$service["showsla"];
		$goodsla=$service["goodsla"];
		$sortorder=$service["sortorder"];
	}
	else
	{
		$name="";
		$showsla=0;
		$goodsla=99.05;
		$sortorder=0;
		$algorithm=0;
		unset($triggerid);
	}

	echo "<a name=\"form\"></a>";
	show_form_begin("services.service");
	echo S_SERVICE;
	$col=0;

	if(isset($_REQUEST["groupid"])&&($_REQUEST["groupid"]==0))
	{
		unset($_REQUEST["groupid"]);
	}

	show_table2_v_delimiter($col++);
	echo "<form method=\"get\" action=\"services.php\">";
	if(isset($_REQUEST["serviceid"]))
	{
		echo "<input class=\"biginput\" name=\"serviceid\" type=\"hidden\" value=".$_REQUEST["serviceid"].">";
	}
	echo S_NAME;
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=32>";

	show_table2_v_delimiter($col++);
	echo nbsp(S_STATUS_CALCULATION_ALGORITHM);
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"algorithm\" size=1>";
//	if(isset($_REQUEST["algorithm"]))
	if(isset($algorithm))
	{
//		if($_REQUEST["algorithm"] == SERVICE_ALGORITHM_NONE)
		if($algorithm == SERVICE_ALGORITHM_NONE)
		{
			echo "<OPTION VALUE='0' SELECTED>".S_DO_NOT_CALCULATE;
			echo "<OPTION VALUE='1'>".S_MAX_BIG;
			echo "<OPTION VALUE='2'>".S_MIN_BIG;
		}
//		else if($_REQUEST["algorithm"] == SERVICE_ALGORITHM_MAX)
		else if($algorithm == SERVICE_ALGORITHM_MAX)
		{
			echo "<OPTION VALUE='0'>".S_DO_NOT_CALCULATE;
			echo "<OPTION VALUE='1' SELECTED>".S_MAX_BIG;
			echo "<OPTION VALUE='2'>".S_MIN_BIG;
		}
		else if($algorithm == SERVICE_ALGORITHM_MIN)
		{
			echo "<OPTION VALUE='0'>".S_DO_NOT_CALCULATE;
			echo "<OPTION VALUE='1'>".S_MAX_BIG;
			echo "<OPTION VALUE='2' SELECTED>".S_MIN_BIG;
		}
	}
	else
	{
		echo "<OPTION VALUE='0'>".S_DO_NOT_CALCULATE;
		echo "<OPTION VALUE='1' SELECTED>".S_MAX_BIG;
		echo "<OPTION VALUE='2'>".S_MIN_BIG;
	}
	echo "</SELECT>";

        show_table2_v_delimiter($col++);
        echo nbsp(S_SHOW_SLA);
        show_table2_h_delimiter();
	if($showsla==1)
	{
   //     	echo "<INPUT class=\"biginput\" TYPE=\"CHECKBOX\" NAME=\"showsla\" VALUE=\"true\" CHECKED>";
        	echo "<INPUT class=\"biginput\" TYPE=\"CHECKBOX\" NAME=\"showsla\" VALUE=\"on\" CHECKED>";
	}
	else
	{
        	echo "<INPUT class=\"biginput\" TYPE=\"CHECKBOX\" NAME=\"showsla\">";
	}

	show_table2_v_delimiter($col++);
	echo nbsp(S_ACCEPTABLE_SLA_IN_PERCENT);
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"goodsla\" value=\"$goodsla\" size=6>";

        show_table2_v_delimiter($col++);
        echo nbsp(S_LINK_TO_TRIGGER_Q);
        show_table2_h_delimiter();
	if(isset($triggerid)&&($triggerid!=""))
	{
        	echo "<INPUT class=\"biginput\" TYPE=\"CHECKBOX\" NAME=\"linktrigger\" VALUE=\"on\" CHECKED>";
	}
	else
	{
        	echo "<INPUT class=\"biginput\" TYPE=\"CHECKBOX\" NAME=\"linktrigger\">";
	}

	show_table2_v_delimiter($col++);
	echo S_TRIGGER;
	show_table2_h_delimiter();
	$h2="<select class=\"biginput\" name=\"groupid\" onChange=\"submit()\">";
	$h2=$h2.form_select("groupid",0,S_ALL_SMALL);
	$result=DBselect("select groupid,name from groups order by name");
	while($row=DBfetch($result))
	{
// Check if at least one host with read permission exists for this group
		$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host");
		$cnt=0;
		while($row2=DBfetch($result2))
		{
			if(!check_right("Host","R",$row2["hostid"]))
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
	$h2=$h2."</select>&nbsp;";

	$h2=$h2."<select class=\"biginput\" name=\"hostid\" onChange=\"submit()\">";
	$h2=$h2.form_select("hostid",0,S_SELECT_HOST_DOT_DOT_DOT);

	if(isset($_REQUEST["groupid"]))
	{
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host";
	}
	else
	{
		$sql="select h.hostid,h.host from hosts h,items i where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid group by h.hostid,h.host order by h.host";
	}

	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		$h2=$h2.form_select("hostid",$row["hostid"],$row["host"]);
	}
	$h2=$h2."</select>&nbsp;";
	echo $h2;

	if(isset($_REQUEST["hostid"]))
	{
		show_table2_v_delimiter($col++);
		echo "&nbsp;";
		show_table2_h_delimiter();
	        $result=DBselect("select t.triggerid,t.description from triggers t,functions f, hosts h, items i where h.hostid=i.hostid and f.itemid=i.itemid and t.triggerid=f.triggerid and h.hostid=".$_REQUEST["hostid"]." order by t.description");
	        echo "<select class=\"biginput\" name=\"triggerid\" size=1>";
		while($row=DBfetch($result))
	        {
	                $triggerid_=$row["triggerid"];
			$description_=expand_trigger_description($triggerid_);
			if(isset($triggerid) && ($triggerid==$triggerid_))
	                {
	                        echo "<OPTION VALUE='$triggerid_' SELECTED>$description_";
	                }
	                else
	                {
	                        echo "<OPTION VALUE='$triggerid_'>$description_";
	                }
	        }
	        echo "</SELECT>";
	}

	show_table2_v_delimiter($col++);
	echo nbsp(S_SORT_ORDER_0_999);
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"sortorder\" value=\"$sortorder\" size=3>";


	show_table2_v_delimiter2();
	if(!isset($triggerid)||($triggerid==""))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
	}
	if(isset($_REQUEST["serviceid"]))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\"  onClick=\"return Confirm('".S_DELETE_SERVICE_Q."');\">";
	}

	show_table2_header_end();
?>

<?php
	if(isset($_REQUEST["serviceid"]))
	{
		$service=get_service_by_serviceid($_REQUEST["serviceid"]);
		$triggerid=$service["triggerid"];
		$name=$service["name"];
	}
	else
	{
		$name="";
		unset($_REQUEST["triggerid"]);
	}

	show_form_begin("services.link");
	echo nbsp(S_LINK_TO);
	$col=0;

	show_table2_v_delimiter($col++);
	echo "<form method=\"post\" action=\"services.php\">";
	if(isset($_REQUEST["serviceid"]))
	{
		echo "<input name=\"serviceid\" type=\"hidden\" value=".$_REQUEST["serviceid"].">";
		echo "<input name=\"serviceupid\" type=\"hidden\" value=".$_REQUEST["serviceid"].">";
	}
	echo S_NAME;
	show_table2_h_delimiter();
	$result=DBselect("select serviceid,triggerid,name from services order by name");
        echo "<select class=\"biginput\" name=\"servicedownid\" size=1>";
	while($row=Dbfetch($result))
        {
                $servicedownid_=$row["serviceid"];
		if($row["triggerid"]>0)
		{
			$name_=expand_trigger_description($row["triggerid"]);
		}
		else
		{
			$name_=$row["name"];
		}
		echo "<OPTION VALUE='$servicedownid_'>$name_";
        }
        echo "</SELECT>";

        show_table2_v_delimiter($col++);
        echo nbsp(S_SOFT_LINK_Q);
        show_table2_h_delimiter();
//	if(isset($_REQUEST["softlink"])&&($_REQUEST["triggerid"]!=""))
//	{
//      	echo "<INPUT TYPE=\"CHECKBOX\" NAME=\"softlink\" VALUE=\"true\">";
//	}
//	else
//	{
//       	echo "<INPUT TYPE=\"CHECKBOX\" NAME=\"softlink\">";
//	}
	echo "<INPUT class=\"biginput\" TYPE=\"CHECKBOX\" NAME=\"softlink\" VALUE=\"true\" checked>";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add link\">";

	show_table2_header_end();
?>

<?php
	if(isset($_REQUEST["serviceid"]))
	{

	show_form_begin("services.server");
	echo nbsp(S_ADD_SERVER_DETAILS);
	$col=0;

	show_table2_v_delimiter($col++);
	echo "<form method=\"post\" action=\"services.php\">";
	if(isset($_REQUEST["serviceid"]))
	{
		echo "<input name=\"serviceid\" type=\"hidden\" value=".$_REQUEST["serviceid"].">";
	}
	echo S_SERVER;
	show_table2_h_delimiter();
	$result=DBselect("select hostid,host from hosts order by host");
        echo "<select class=\"biginput\" name=\"serverid\" size=1>";
        while($row=DBfetch($result))
        {
		echo "<OPTION VALUE='".$row["hostid"]."'>".$row["host"];
        }
        echo "</SELECT>";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add server\">";

	show_table2_header_end();
	}

?>

<?php
	show_page_footer();
?>
