<?
	$page["title"] = "High-level representation of monitored data";
	$page["file"] = "services.php";

	include "include/config.inc.php";
	show_header($page["title"],0,0);
?>

<?
	if(!check_right("Service","U",0))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
?>

<?
	if(isset($HTTP_GET_VARS["register"]))
	{
		if($HTTP_GET_VARS["register"]=="update")
		{
			$result=@update_service($HTTP_GET_VARS["serviceid"],$HTTP_GET_VARS["name"],$HTTP_GET_VARS["triggerid"],$HTTP_GET_VARS["linktrigger"],$HTTP_GET_VARS["algorithm"]);
			show_messages($result,"Service updated","Cannot update service");
		}
		if($HTTP_GET_VARS["register"]=="add")
		{
			$result=@add_service($HTTP_GET_VARS["serviceid"],$HTTP_GET_VARS["name"],$HTTP_GET_VARS["triggerid"],$HTTP_GET_VARS["linktrigger"],$HTTP_GET_VARS["algorithm"]);
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

<?
	show_table_header("IT SERVICES");

	$now=time();
	$result=DBselect("select serviceid,name,algorithm from services order by name");
	echo "<table border=0 width=100% bgcolor='#CCCCCC' cellspacing=1 cellpadding=3>";
	echo "<tr>";
	echo "<td><b>Service</b></td>";
	echo "<td width=20%><b>Status calculation</b></td>";
	echo "<td width=10%><b>Actions</b></td>";
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
		else
		{
			echo "<td>unknown</td>";
		}
		echo "<td><a href=\"services.php?serviceid=".$service["serviceid"]."&register=delete\">Delete</a></td>";
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
		else
		{
			echo "<td>unknown</td>";
		}
		echo "<td><a href=\"services.php?serviceid=".$row["serviceid"]."&register=delete\">Delete</a></td>";
		echo "</tr>";
	}
	echo "</table>";
?>

<?
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

<?
	if(isset($HTTP_GET_VARS["serviceid"]))
	{
		$result=DBselect("select serviceid,triggerid,name,algorithm from services where serviceid=".$HTTP_GET_VARS["serviceid"]);
		$triggerid=DBget_field($result,0,1);
		$name=DBget_field($result,0,2);
		$algorithm=DBget_field($result,0,3);
	}
	else
	{
		$name="";
		unset($HTTP_GET_VARS["triggerid"]);
	}

	echo "<br>";
	echo "<a name=\"form\"></a>";
	show_table2_header_begin();
	echo "New service";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"services.php\">";
	if(isset($HTTP_GET_VARS["serviceid"]))
	{
		echo "<input name=\"serviceid\" type=\"hidden\" value=".$HTTP_GET_VARS["serviceid"].">";
	}
	echo "Name";
	show_table2_h_delimiter();
	echo "<input name=\"name\" value=\"$name\" size=32>";

	show_table2_v_delimiter();
	echo "Status calculation algorithm";
	show_table2_h_delimiter();
	$result=DBselect("select triggerid,description from triggers order by description");
	echo "<select name=\"algorithm\" size=1>";
	if(isset($HTTP_GET_VARS["algorithm"]))
	{
		if($HTTP_GET_VARS["algorithm"] == SERVICE_ALGORITHM_NONE)
		{
			echo "<OPTION VALUE='0' SELECTED>Do not calculate";
			echo "<OPTION VALUE='1'>MAX";
		}
		if($algorithm == SERVICE_ALGORITHM_MAX)
		{
			echo "<OPTION VALUE='0'>Do not calculate";
			echo "<OPTION VALUE='1' SELECTED>MAX";
		}
	}
	else
	{
		echo "<OPTION VALUE='0'>Do not calculate";
		echo "<OPTION VALUE='1' SELECTED>MAX";
	}
	echo "</SELECT>";

        show_table2_v_delimiter();
        echo "Link to trigger ?";
        show_table2_h_delimiter();
	if(isset($HTTP_GET_VARS["triggerid"])&&($HTTP_GET_VARS["triggerid"]!=""))
	{
        	echo "<INPUT TYPE=\"CHECKBOX\" NAME=\"linktrigger\" VALUE=\"true\" CHECKED>";
	}
	else
	{
        	echo "<INPUT TYPE=\"CHECKBOX\" NAME=\"linktrigger\">";
	}

	show_table2_v_delimiter();
	echo "Trigger";
	show_table2_h_delimiter();
        $result=DBselect("select triggerid,description from triggers order by description");
        echo "<select name=\"triggerid\" size=1>";
        for($i=0;$i<DBnum_rows($result);$i++)
        {
                $triggerid_=DBget_field($result,$i,0);
                $description_=DBget_field($result,$i,1);
		if( strstr($description_,"%s"))
		{
			$description_=expand_trigger_description($triggerid_);
		}
                if(isset($HTTP_GET_VARS["triggerid"]) && ($HTTP_GET_VARS["triggerid"]==$triggerid_))
                {
                        echo "<OPTION VALUE='$triggerid_' SELECTED>$description_";
                }
                else
                {
                        echo "<OPTION VALUE='$triggerid_'>$description_";
                }
        }
        echo "</SELECT>";

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($HTTP_GET_VARS["serviceid"]))
	{
		echo "<input type=\"submit\" name=\"register\" value=\"update\">";
	}

	show_table2_header_end();
?>

<?
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
	echo "Link to ...";

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
        echo "<select name=\"servicedownid\" size=1>";
        for($i=0;$i<DBnum_rows($result);$i++)
        {
                $servicedownid_=DBget_field($result,$i,0);
                $name_=DBget_field($result,$i,2);
		if( strstr($name_,"%s"))
		{
			$name_=expand_trigger_description(DBget_field($result,$i,1));
		}
		echo "<OPTION VALUE='$servicedownid_'>$name_";
        }
        echo "</SELECT>";

        show_table2_v_delimiter();
        echo "Soft link ?";
        show_table2_h_delimiter();
//	if(isset($HTTP_GET_VARS["softlink"])&&($HTTP_GET_VARS["triggerid"]!=""))
//	{
//      	echo "<INPUT TYPE=\"CHECKBOX\" NAME=\"softlink\" VALUE=\"true\">";
//	}
//	else
//	{
//       	echo "<INPUT TYPE=\"CHECKBOX\" NAME=\"softlink\">";
//	}
	echo "<INPUT TYPE=\"CHECKBOX\" NAME=\"softlink\" VALUE=\"true\" checked>";

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"add link\">";

	show_table2_header_end();
?>

<?
	if(isset($HTTP_GET_VARS["serviceid"]))
	{

	echo "<br>";
	show_table2_header_begin();
	echo "Add server details";

	show_table2_v_delimiter();
	echo "<form method=\"post\" action=\"services.php\">";
	if(isset($HTTP_GET_VARS["serviceid"]))
	{
		echo "<input name=\"serviceid\" type=\"hidden\" value=".$HTTP_GET_VARS["serviceid"].">";
	}
	echo "Server";
	show_table2_h_delimiter();
	$result=DBselect("select hostid,host from hosts order by host");
        echo "<select name=\"hostid\" size=1>";
        while($row=DBfetch($result))
        {
		echo "<OPTION VALUE='".$row["hostid"]."'>".$row["host"];
        }
        echo "</SELECT>";

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"add server\">";

	show_table2_header_end();
	}

?>

<?
	show_footer();
?>
