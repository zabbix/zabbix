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
	$page["title"] = "High-level representation of monitored data";
	$page["file"] = "services.php";

	include "include/config.inc.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	if(!check_anyright("Service","U"))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
?>

<?php
	if(isset($HTTP_GET_VARS["register"]))
	{
		if($HTTP_GET_VARS["register"]=="update")
		{
			$result=@update_service($HTTP_GET_VARS["serviceid"],$HTTP_GET_VARS["name"],$HTTP_GET_VARS["triggerid"],$HTTP_GET_VARS["linktrigger"],$HTTP_GET_VARS["algorithm"],$HTTP_GET_VARS["showsla"],$HTTP_GET_VARS["goodsla"],$HTTP_GET_VARS["sortorder"]);
			show_messages($result,"Service updated","Cannot update service");
		}
		if($HTTP_GET_VARS["register"]=="add")
		{
			$result=@add_service($HTTP_GET_VARS["serviceid"],$HTTP_GET_VARS["name"],$HTTP_GET_VARS["triggerid"],$HTTP_GET_VARS["linktrigger"],$HTTP_GET_VARS["algorithm"],$HTTP_GET_VARS["showsla"],$HTTP_GET_VARS["goodsla"],$HTTP_GET_VARS["goodsla"]);
			show_messages($result,"Service added","Cannot add service");
		}
		if($HTTP_GET_VARS["register"]=="add server")
		{
			$result=add_host_to_services($HTTP_GET_VARS["hostid"],$HTTP_GET_VARS["serviceid"]);
			show_messages($result,"Host trigger added","Cannot add host triggers");
		}
		if($HTTP_GET_VARS["register"]=="add link")
		{
			if(!isset($HTTP_GET_VARS["softlink"]))
			{
				$HTTP_GET_VARS["softlink"]=0;
			}
			else
			{
				$HTTP_GET_VARS["softlink"]=1;
			}
			$result=add_service_link($HTTP_GET_VARS["servicedownid"],$HTTP_GET_VARS["serviceupid"],$HTTP_GET_VARS["softlink"]);
			show_messages($result,"Service link added","Cannot add service link");
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_service($HTTP_GET_VARS["serviceid"]);
			show_messages($result,"Service deleted","Cannot delete service");
			unset($HTTP_GET_VARS["serviceid"]);
		}
		if($HTTP_GET_VARS["register"]=="delete_link")
		{
			$result=delete_service_link($HTTP_GET_VARS["linkid"]);
			show_messages($result,"Link deleted","Cannot delete link");
		}
	}
?>

<?php
	show_table_header("IT SERVICES");

	$now=time();
	$result=DBselect("select serviceid,name,algorithm from services order by sortorder,name");
	echo "<table border=0 width=100% bgcolor='#CCCCCC' cellspacing=1 cellpadding=3>";
	echo "<tr>";
	echo "<td><b>Service</b></td>";
	echo "<td width=20%><b>Status calculation</b></td>";
	echo "</tr>";

	$col=0;
	if(isset($HTTP_GET_VARS["serviceid"]))
	{
		echo "<tr bgcolor=#EEEEEE>";
		$service=get_service_by_serviceid($HTTP_GET_VARS["serviceid"]);
		echo "<td><b><a href=\"services.php?serviceid=".$service["serviceid"]."#form\">".$service["name"]."</a></b></td>";
		if($service["algorithm"] == SERVICE_ALGORITHM_NONE)
		{
			echo "<td>none</td>";
		}
		else if($service["algorithm"] == SERVICE_ALGORITHM_MAX)
		{
			echo "<td>MAX of childs</td>";
		}
		else if($service["algorithm"] == SERVICE_ALGORITHM_MIN)
		{
			echo "<td>MIN of childs</td>";
		}
		else
		{
			echo "<td>unknown</td>";
		}
		echo "</tr>"; 
		$col++;
	}
	while($row=DBfetch($result))
	{
		if(!isset($HTTP_GET_VARS["serviceid"]) && service_has_parent($row["serviceid"]))
		{
			continue;
		}
		if(isset($HTTP_GET_VARS["serviceid"]) && service_has_no_this_parent($HTTP_GET_VARS["serviceid"],$row["serviceid"]))
		{
			continue;
		}
		if(isset($HTTP_GET_VARS["serviceid"])&&($HTTP_GET_VARS["serviceid"]==$row["serviceid"]))
		{
			echo "<tr bgcolor=#99AABB>";
		}
		else
		{
			if($col++%2==0)	{ echo "<tr bgcolor=#EEEEEE>"; }
			else		{ echo "<tr bgcolor=#DDDDDD>"; }
		}
		$childs=get_num_of_service_childs($row["serviceid"]);
		if(isset($HTTP_GET_VARS["serviceid"]))
		{
			echo "<td> - <a href=\"services.php?serviceid=".$row["serviceid"]."#form\">".$row["name"]." [$childs]</a></td>";
		}
		else
		{
			echo "<td><a href=\"services.php?serviceid=".$row["serviceid"]."#form\">".$row["name"]." [$childs]</a></td>";
		}
		if($row["algorithm"] == SERVICE_ALGORITHM_NONE)
		{
			echo "<td>none</td>";
		}
		else if($row["algorithm"] == SERVICE_ALGORITHM_MAX)
		{
			echo "<td>MAX of childs</td>";
		}
		else if($row["algorithm"] == SERVICE_ALGORITHM_MIN)
		{
			echo "<td>MIN of childs</td>";
		}
		else
		{
			echo "<td>unknown</td>";
		}
		echo "</tr>";
	}
	echo "</table>";
?>

<?php
	if(isset($HTTP_GET_VARS["serviceid"]))
	{
		show_table_header("LINKS");
		echo "<table border=0 width=100% bgcolor='#CCCCCC' cellspacing=1 cellpadding=3>";
		echo "<tr>";
		echo "<td><b>Service 1</b></td>";
		echo "<td><b>Service 2</b></td>";
		echo "<td><b>Soft/hard link</b></td>";
		echo "<td><b>Actions</b></td>";
		echo "</tr>";
		$sql="select linkid,servicedownid,serviceupid,soft from services_links where serviceupid=".$HTTP_GET_VARS["serviceid"]." or servicedownid=".$HTTP_GET_VARS["serviceid"];
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
				echo "<td>Hard</td>";
			}
			else
			{
				echo "<td>Soft</td>";
			}
			echo "<td><a href=\"services.php?register=delete_link&serviceid=".$HTTP_GET_VARS["serviceid"]."&linkid=".$row["linkid"]."\">Delete</a></td>";
			echo "</tr>";
		}
		echo "</table>";
	}
?>

<?php
	if(isset($HTTP_GET_VARS["serviceid"]))
	{
		$result=DBselect("select serviceid,triggerid,name,algorithm,showsla,goodsla,sortorder from services where serviceid=".$HTTP_GET_VARS["serviceid"]);
		$triggerid=DBget_field($result,0,1);
		$name=DBget_field($result,0,2);
		$algorithm=DBget_field($result,0,3);
		$showsla=DBget_field($result,0,4);
		$goodsla=DBget_field($result,0,5);
		$sortorder=DBget_field($result,0,6);
	}
	else
	{
		$name="";
		$showsla=0;
		$goodsla=99.05;
		$sortorder=0;
		unset($triggerid);
	}

	echo "<br>";
	echo "<a name=\"form\"></a>";
	show_table2_header_begin();
	echo "Service";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"services.php\">";
	if(isset($HTTP_GET_VARS["serviceid"]))
	{
		echo "<input class=\"biginput\" name=\"serviceid\" type=\"hidden\" value=".$HTTP_GET_VARS["serviceid"].">";
	}
	echo "Name";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=32>";

	show_table2_v_delimiter();
	echo nbsp("Status calculation algorithm");
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"algorithm\" size=1>";
//	if(isset($HTTP_GET_VARS["algorithm"]))
	if(isset($algorithm))
	{
//		if($HTTP_GET_VARS["algorithm"] == SERVICE_ALGORITHM_NONE)
		if($algorithm == SERVICE_ALGORITHM_NONE)
		{
			echo "<OPTION VALUE='0' SELECTED>Do not calculate";
			echo "<OPTION VALUE='1'>MAX";
			echo "<OPTION VALUE='2'>MIN";
		}
//		else if($HTTP_GET_VARS["algorithm"] == SERVICE_ALGORITHM_MAX)
		else if($algorithm == SERVICE_ALGORITHM_MAX)
		{
			echo "<OPTION VALUE='0'>Do not calculate";
			echo "<OPTION VALUE='1' SELECTED>MAX";
			echo "<OPTION VALUE='2'>MIN";
		}
		else if($HTTP_GET_VARS["algorithm"] == SERVICE_ALGORITHM_MIN)
		{
			echo "<OPTION VALUE='0'>Do not calculate";
			echo "<OPTION VALUE='1'>MAX";
			echo "<OPTION VALUE='2' SELECTED>MIN";
		}
	}
	else
	{
		echo "<OPTION VALUE='0'>Do not calculate";
		echo "<OPTION VALUE='1' SELECTED>MAX";
		echo "<OPTION VALUE='2'>MIN";
	}
	echo "</SELECT>";

        show_table2_v_delimiter();
        echo nbsp("Show SLA");
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

	show_table2_v_delimiter();
	echo nbsp("Acceptable SLA (in %)");
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"goodsla\" value=\"$goodsla\" size=6>";

        show_table2_v_delimiter();
        echo nbsp("Link to trigger ?");
        show_table2_h_delimiter();
	if(isset($triggerid)&&($triggerid!=""))
	{
        	echo "<INPUT class=\"biginput\" TYPE=\"CHECKBOX\" NAME=\"linktrigger\" VALUE=\"on\" CHECKED>";
	}
	else
	{
        	echo "<INPUT class=\"biginput\" TYPE=\"CHECKBOX\" NAME=\"linktrigger\">";
	}

	show_table2_v_delimiter();
	echo "Trigger";
	show_table2_h_delimiter();
        $result=DBselect("select triggerid,description from triggers order by description");
        echo "<select class=\"biginput\" name=\"triggerid\" size=1>";
        for($i=0;$i<DBnum_rows($result);$i++)
        {
                $triggerid_=DBget_field($result,$i,0);
//                $description_=DBget_field($result,$i,1);
//		if( strstr($description_,"%s"))
//		{
			$description_=expand_trigger_description($triggerid_);
//		}
//		if(isset($HTTP_GET_VARS["triggerid"]) && ($HTTP_GET_VARS["triggerid"]==$triggerid_))
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

	show_table2_v_delimiter();
	echo nbsp("Sort order (0->999)");
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"sortorder\" value=\"$sortorder\" size=3>";


	show_table2_v_delimiter2();
	if(!isset($triggerid)||($triggerid==""))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
	}
	if(isset($HTTP_GET_VARS["serviceid"]))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\"  onClick=\"return Confirm('Delete service?');\">";
	}

	show_table2_header_end();
?>

<?php
	if(isset($HTTP_GET_VARS["serviceid"]))
	{
		$result=DBselect("select serviceid,triggerid,name from services where serviceid=".$HTTP_GET_VARS["serviceid"]);
		$triggerid=DBget_field($result,0,1);
		$name=DBget_field($result,0,2);
	}
	else
	{
		$name="";
		unset($HTTP_GET_VARS["triggerid"]);
	}

	echo "<br>";
	show_table2_header_begin();
	echo nbsp("Link to ...");

	show_table2_v_delimiter();
	echo "<form method=\"post\" action=\"services.php\">";
	if(isset($HTTP_GET_VARS["serviceid"]))
	{
		echo "<input name=\"serviceid\" type=\"hidden\" value=".$HTTP_GET_VARS["serviceid"].">";
		echo "<input name=\"serviceupid\" type=\"hidden\" value=".$HTTP_GET_VARS["serviceid"].">";
	}
	echo "Name";
	show_table2_h_delimiter();
	$result=DBselect("select serviceid,triggerid,name from services order by name");
        echo "<select class=\"biginput\" name=\"servicedownid\" size=1>";
        for($i=0;$i<DBnum_rows($result);$i++)
        {
                $servicedownid_=DBget_field($result,$i,0);
//                $name_=DBget_field($result,$i,2);
//		if( strstr($name_,"%s"))
//		{
			if(DBget_field($result,$i,1)>0)
			{
				$name_=expand_trigger_description(DBget_field($result,$i,1));
			}
			else
			{
				$name_=DBget_field($result,$i,2);
			}
//		}
		echo "<OPTION VALUE='$servicedownid_'>$name_";
        }
        echo "</SELECT>";

        show_table2_v_delimiter();
        echo nbsp("Soft link ?");
        show_table2_h_delimiter();
//	if(isset($HTTP_GET_VARS["softlink"])&&($HTTP_GET_VARS["triggerid"]!=""))
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
	if(isset($HTTP_GET_VARS["serviceid"]))
	{

	echo "<br>";
	show_table2_header_begin();
	echo nbsp("Add server details");

	show_table2_v_delimiter();
	echo "<form method=\"post\" action=\"services.php\">";
	if(isset($HTTP_GET_VARS["serviceid"]))
	{
		echo "<input name=\"serviceid\" type=\"hidden\" value=".$HTTP_GET_VARS["serviceid"].">";
	}
	echo "Server";
	show_table2_h_delimiter();
	$result=DBselect("select hostid,host from hosts order by host");
        echo "<select class=\"biginput\" name=\"hostid\" size=1>";
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
	show_footer();
?>
