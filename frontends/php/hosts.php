<?
	include "include/config.inc.php";
	$page["title"] = "Hosts";
	$page["file"] = "hosts.php";
	show_header($page["title"],0,0);
?>

<?
        if(!check_right("Host","R",0))
        {
                show_table_header("<font color=\"AA0000\">No permissions !</font
>");
                show_footer();
                exit;
        }
?>

<?
	if(isset($register))
	{
		if($register=="add")
		{
			$result=add_host($host,$port,$status,$template,$useip,$ip,$host_templateid);
			show_messages($result,"Host added","Cannot add host");
		}
		if($register=="update")
		{
			$result=update_host($hostid,$host,$port,$status,$useip,$ip);
			show_messages($result,"Host details updated","Cannot update host details");
		}
		if($register=="changestatus")
		{
			$result=update_host_status($hostid,$status);
			show_messages($result,"Host status updated","Cannot update host status");
		}
		if($register=="delete")
		{
			$result=delete_host($hostid);
			show_messages($result,"Host deleted","Cannot delete host");
			unset($hostid);
		}
	}
?>

<?
	show_table_header("CONFIGURATION OF HOSTS");
?>

<?
	echo "<TABLE BORDER=0 COLS=4 WIDTH=\"100%\" BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR>";
	echo "<TD WIDTH=\"3%\" NOSAVE><B>Id</B></TD>";
	echo "<TD WIDTH=\"10%\" NOSAVE><B>Host</B></TD>";
	echo "<TD WIDTH=\"10%\" NOSAVE><B>Port</B></TD>";
	echo "<TD WIDTH=\"10%\" NOSAVE><B>Status</B></TD>";
	echo "<TD WIDTH=\"10%\" NOSAVE><B>Actions</B></TD>";
	echo "</TR>";

	$result=DBselect("select h.hostid,h.host,h.port,h.status from hosts h order by h.host");
	echo "<CENTER>";
	$col=0;
	while($row=DBfetch($result))
//	for($i=0;$i<DBnum_rows($result);$i++)
	{
        	if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else 		{ echo "<TR BGCOLOR=#DDDDDD>"; }
	
		echo "<TD>".$row["hostid"]."</TD>";
		echo "<TD><a href=\"items.php?hostid=".$row["hostid"]."\">".$row["host"]."</a></TD>";
		echo "<TD>".$row["port"]."</TD>";
		echo "<TD>";
        	if(check_right("Host","U",$row["hostid"]))
		{
			if($row["status"] == 0)	
				echo "<a href=\"hosts.php?hostid=".$row["hostid"]."&register=changestatus&status=1\">Monitored</a>";
			else if($row["status"] == 1)
				echo "<a href=\"hosts.php?hostid=".$row["hostid"]."&register=changestatus&status=0\">Not monitored</a>";
			else if($row["status"] == 2)
				echo "Unreachable";
			else
				echo "Unknown";
		}
		else
		{
			if($row["status"] == 0)	
				echo "Monitored";
			else if($row["status"] == 1)
				echo "Not monitored";
			else if($row["status"] == 2)
				echo "Unreachable";
			else
				echo "Unknown";
		}
		echo "</TD>";
        	if(check_right("Host","U",$row["hostid"]))
		{
			echo "<TD><A HREF=\"hosts.php?register=change&hostid=".$row["hostid"]."#form\">Change</A></TD>";
		}
		else
		{
			echo "<TD>Change</TD>";
		}
		echo "</TR>";
	}
	echo "</TABLE>";
?>

<?
	if(isset($register) && ($register == "change"))
	{
		$result=DBselect("select host,port,status,useip,ip from hosts where hostid=$hostid"); 
		$host=DBget_field($result,0,0);
		$port=DBget_field($result,0,1);
		$status=DBget_field($result,0,2);
		$useip=DBget_field($result,0,3);
		$ip=DBget_field($result,0,4);

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
		$host="";
		$port=10000;
		$status=0;
		$useip="";
		$ip="";
	}

	echo "<br>";
	echo "<a name=\"form\"></a>";
	show_table2_header_begin();
	echo "Host details";

	show_table2_v_delimiter();
	echo "<form method=\"post\" action=\"hosts.php\">";
	if(isset($hostid))
	{
		echo "<input name=\"hostid\" type=\"hidden\" value=$hostid>";
	}
	echo "Host";
	show_table2_h_delimiter();
	echo "<input name=\"host\" value=\"$host\" size=20>";

	show_table2_v_delimiter();
	echo "Use IP address";
	show_table2_h_delimiter();
	echo "<INPUT TYPE=\"CHECKBOX\" NAME=\"useip\" $useip>";

	show_table2_v_delimiter();
	echo "IP address";
	show_table2_h_delimiter();
	echo "<input name=\"ip\" value=\"$ip\" size=15>";

	show_table2_v_delimiter();
	echo "Port";
	show_table2_h_delimiter();
	echo "<input name=\"port\" size=5 value=\"$port\">";

	show_table2_v_delimiter();
	echo "Status";
	show_table2_h_delimiter();
	echo "<select name=\"status\" size=\"1\">";
	if($status==0)
	{
		echo "<option value=\"0\" selected>Monitored";
		echo "<option value=\"1\">Not monitored";
	}
	else
	{
		echo "<option value=\"0\">Monitored";
		echo "<option value=\"1\" selected>Not monitored";
	}
	echo "</select>";

	show_table2_v_delimiter();
	echo "Add parameters supported by zabbix_agent";
	show_table2_h_delimiter();
	echo "<INPUT NAME=\"template\" TYPE=\"CHECKBOX\"  VALUE=\"true\" CHECKED>";

	show_table2_v_delimiter();
	echo "Use the host as a template";
	show_table2_h_delimiter();
	echo "<select name=\"host_templateid\" size=\"1\">";
	echo "<option value=\"0\" selected>...";
	$result=DBselect("select host,hostid from hosts order by host");
	while($row=DBfetch($result))
	{
		echo "<option value=\"".$row["hostid"]."\">".$row["host"];
	}
	echo "</select>";

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($hostid))
	{
		echo "<input type=\"submit\" name=\"register\" value=\"update\">";
		echo "<input type=\"submit\" name=\"register\" value=\"delete\">";
	}

	show_table2_header_end();
?>

<?
	show_footer();
?>
