<?
	include "include/config.inc.php";
	$page["title"] = "Configuration of network map";
	$page["file"] = "sysmap.php";
	show_header($page["title"],0,0);
?>

<?
	show_table_header("CONFIGURATION OF NETWORK MAP");
	echo "<br>";
?>

<?
	if(isset($HTTP_GET_VARS["register"]))
	{
		if($HTTP_GET_VARS["register"]=="add")
		{
			$result=add_host_to_sysmap($HTTP_GET_VARS["sysmapid"],$HTTP_GET_VARS["hostid"],$HTTP_GET_VARS["label"],$HTTP_GET_VARS["x"],$HTTP_GET_VARS["y"],$HTTP_GET_VARS["icon"]);
			show_messages($result,"Host added","Cannot add host");
		}
		if($HTTP_GET_VARS["register"]=="update")
		{
			$result=update_sysmap_host($HTTP_GET_VARS["shostid"],$HTTP_GET_VARS["sysmapid"],$HTTP_GET_VARS["hostid"],$HTTP_GET_VARS["label"],$HTTP_GET_VARS["x"],$HTTP_GET_VARS["y"],$HTTP_GET_VARS["icon"]);
			show_messages($result,"Host updated","Cannot update host");
		}
		if($HTTP_GET_VARS["register"]=="add link")
		{
			$result=add_link($HTTP_GET_VARS["sysmapid"],$HTTP_GET_VARS["shostid1"],$HTTP_GET_VARS["shostid2"]);
			show_messages($result,"Link added","Cannot add link");
		}
		if($HTTP_GET_VARS["register"]=="delete_link")
		{
			$result=delete_link($HTTP_GET_VARS["linkid"]);
			show_messages($result,"Link deleted","Cannot delete link");
			unset($HTTP_GET_VARS["linkid"]);
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_sysmaps_host($HTTP_GET_VARS["shostid"]);
			show_messages($result,"Host deleted","Cannot delete host");
			unset($HTTP_GET_VARS["shostid"]);
		}
	}
?>

<?
	$result=DBselect("select name from sysmaps where sysmapid=".$HTTP_GET_VARS["sysmapid"]);
	$map=DBget_field($result,0,0);
	show_table_header($map);
	echo "<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	if(isset($HTTP_GET_VARS["sysmapid"]))
	{
		$map="\n<map name=links>";
		$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,sh.x,sh.y,h.status from sysmaps_hosts sh,hosts h where sh.sysmapid=".$HTTP_GET_VARS["sysmapid"]." and h.hostid=sh.hostid");
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$host_=DBget_field($result,$i,0);
			$shostid_=DBget_field($result,$i,1);
			$sysmapid_=DBget_field($result,$i,2);
			$hostid_=DBget_field($result,$i,3);
			$label_=DBget_field($result,$i,4);
			$x_=DBget_field($result,$i,5);
			$y_=DBget_field($result,$i,6);
			$status_=DBget_field($result,$i,7);

			$map=$map."\n<area shape=rect coords=$x_,$y_,".($x_+32).",".($y_+32)." href=\"sysmap.php?sysmapid=$sysmapid_&shostid=$shostid_#form\" alt=\"$host_\">";
		}
		$map=$map."\n</map>";
		echo $map;
		echo "<IMG SRC=\"map.php?sysmapid=".$HTTP_GET_VARS["sysmapid"]."\" border=0 usemap=#links>";
	}

	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";

	show_table_header("DISPLAYED HOSTS");
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TD WIDTH=10% NOSAVE><B>Host</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Label</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>X</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Y</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Icon</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Actions</B></TD>";
	echo "</TR>";

	$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,sh.x,sh.y,sh.icon from sysmaps_hosts sh,hosts h where sh.sysmapid=".$HTTP_GET_VARS["sysmapid"]." and h.hostid=sh.hostid order by h.host");
	$col=0;
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		if($col==1)
		{
			echo "<TR BGCOLOR=#EEEEEE>";
			$col=0;
		} else
		{
			echo "<TR BGCOLOR=#DDDDDD>";
			$col=1;
		}
	
		$host=DBget_field($result,$i,0);
		$shostid_=DBget_field($result,$i,1);
		$sysmapid_=DBget_field($result,$i,2);
		$hostid_=DBget_field($result,$i,3);
		$label_=DBget_field($result,$i,4);
		$x_=DBget_field($result,$i,5);
		$y_=DBget_field($result,$i,6);
		$icon_=DBget_field($result,$i,7);

		echo "<TD>$host</TD>";
		echo "<TD>$label_</TD>";
		echo "<TD>$x_</TD>";
		echo "<TD>$y_</TD>";
		echo "<TD>$icon_</TD>";
		echo "<TD><A HREF=\"sysmap.php?sysmapid=$sysmapid_&shostid=$shostid_#form\">Change</A> - <A HREF=\"sysmap.php?register=delete&sysmapid=$sysmapid_&shostid=$shostid_\">Delete</A></TD>";
		echo "</TR>";
	}
	echo "</TABLE>";
?>

<?
	show_table_header("CONNECTORS");
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TD WIDTH=10% NOSAVE><B>Host 1</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Host 2</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Actions</B></TD>";
	echo "</TR>";

	$result=DBselect("select linkid,shostid1,shostid2 from sysmaps_links where sysmapid=".$HTTP_GET_VARS["sysmapid"]." order by linkid");
	$col=0;
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		if($col==1)
		{
			echo "<TR BGCOLOR=#EEEEEE>";
			$col=0;
		} else
		{
			echo "<TR BGCOLOR=#DDDDDD>";
			$col=1;
		}
	
		$linkid=DBget_field($result,$i,0);
		$shostid1=DBget_field($result,$i,1);
		$shostid2=DBget_field($result,$i,2);

		$result1=DBselect("select label from sysmaps_hosts where shostid=$shostid1");
		$label1=DBget_field($result1,0,0);
		$result1=DBselect("select label from sysmaps_hosts where shostid=$shostid2");
		$label2=DBget_field($result1,0,0);

		echo "<TD>$label1</TD>";
		echo "<TD>$label2</TD>";
		echo "<TD><A HREF=\"sysmap.php?sysmapid=".$HTTP_GET_VARS["sysmapid"]."&register=delete_link&linkid=$linkid\">Delete</A></TD>";
		echo "</TR>";
	}
	echo "</TABLE>";
?>

<?
	echo "<br>";
	echo "<a name=\"form\"></a>";

	if(isset($HTTP_GET_VARS["shostid"]))
	{
		$result=DBselect("select hostid,label,x,y,icon from sysmaps_hosts where shostid=".$HTTP_GET_VARS["shostid"]);
		$hostid=DBget_field($result,0,0);
		$label=DBget_field($result,0,1);
		$x=DBget_field($result,0,2);
		$y=DBget_field($result,0,3);
		$icon=DBget_field($result,0,4);
	}
	else
	{
		$label="";
		$x=0;
		$y=0;
	}

	show_table2_header_begin();
	echo "New host to display";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"sysmap.php?sysmapid=".$HTTP_GET_VARS["sysmapid"].">";
	if(isset($HTTP_GET_VARS["shostid"]))
	{
		echo "<input name=\"shostid\" type=\"hidden\" value=".$HTTP_GET_VARS["shostid"].">";
	}
	echo "Host";
	show_table2_h_delimiter();
	$result=DBselect("select hostid,host from hosts order by host");
	echo "<select name=\"hostid\" size=1>";
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		$hostid_=DBget_field($result,$i,0);
		$host_=DBget_field($result,$i,1);
		if(isset($HTTP_GET_VARS["hostid"]) && ($HTTP_GET_VARS["hostid"]==$hostid_))
		{
			echo "<OPTION VALUE='$hostid_' SELECTED>$host_";
		}
		else
		{
			echo "<OPTION VALUE='$hostid_'>$host_";
		}
	}
	echo "</SELECT>";

	show_table2_v_delimiter();
	echo "Icon";
	show_table2_h_delimiter();
	echo "<select name=\"icon\" size=1>";
	$icons=array();
	$icons[0]="Server";
	$icons[1]="Workstation";
	$icons[2]="Printer";
	$icons[3]="Hub";
	for($i=0;$i<4;$i++)
	{
		if(isset($HTTP_GET_VARS["hostid"]) && ($HTTP_GET_VARS["icon"]==$icons[$i]))
		{
			echo "<OPTION VALUE='".$icons[$i]."' SELECTED>".$icons[$i];
		}
		else
		{
			echo "<OPTION VALUE='".$icons[$i]."'>".$icons[$i];
		}
	}
	echo "</SELECT>";

	show_table2_v_delimiter();
	echo "Label";
	show_table2_h_delimiter();
	echo "<input name=\"label\" size=32 value=\"$label\">";

	show_table2_v_delimiter();
	echo "Coordinate X";
	show_table2_h_delimiter();
	echo "<input name=\"x\" size=5 value=\"$x\">";

	show_table2_v_delimiter();
	echo "Coordinate Y";
	show_table2_h_delimiter();
	echo "<input name=\"y\" size=5 value=\"$y\">";

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($HTTP_GET_VARS["shostid"]))
	{
		echo "<input type=\"submit\" name=\"register\" value=\"update\">";
	}

	show_table2_header_end();
?>

<?
	echo "<br>";
	show_table2_header_begin();
	echo "New connector";

	show_table2_v_delimiter();
	echo "<form method=\"post\" action=\"sysmap.php?sysmapid=".$HTTP_GET_VARS["sysmapid"].">";
	echo "Host 1";
	show_table2_h_delimiter();
	$result=DBselect("select shostid,label from sysmaps_hosts where sysmapid=".$HTTP_GET_VARS["sysmapid"]." order by label");
	echo "<select name=\"shostid1\" size=1>";
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		$shostid_=DBget_field($result,$i,0);
		$label=DBget_field($result,$i,1);
		if(isset($HTTP_GET_VARS["shostid"])&&($HTTP_GET_VARS["shostid"]==$shostid_))
		{
			echo "<OPTION VALUE='$shostid_' SELECTED>$label";
		}
		else
		{
			echo "<OPTION VALUE='$shostid_'>$label";
		}
	}
	echo "</SELECT>";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"sysmap.php?sysmapid=".$HTTP_GET_VARS["sysmapid"].">";
	echo "Host 2";
	show_table2_h_delimiter();
	$result=DBselect("select shostid,label from sysmaps_hosts where sysmapid=".$HTTP_GET_VARS["sysmapid"]." order by label");
	echo "<select name=\"shostid2\" size=1>";
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		$shostid_=DBget_field($result,$i,0);
		$label=DBget_field($result,$i,1);
		echo "<OPTION VALUE='$shostid_'>$label";
	}
	echo "</SELECT>";

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"add link\">";

	show_table2_header_end();
?>

<?
	show_footer();
?>
