<?
	include "include/config.inc.php";
	$page["title"] = "Network maps";
	$page["file"] = "maps.php";

	$nomenu=0;
	if(isset($fullscreen))
	{
		$nomenu=1;
	}
	if(isset($sysmapid))
	{
		show_header($page["title"],30,$nomenu);
	}
	else
	{
		show_header($page["title"],0,$nomenu);
	}
?>

<?
	if(isset($sysmapid)&&!check_right("Network map","R",$sysmapid))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
?>

<?
	if(!isset($fullscreen))
	{
		show_table_header_begin();
		echo "NETWORK MAPS";

		show_table_v_delimiter();

		echo "<font size=2>";

		$lasthost="";
		$result=DBselect("select sysmapid,name from sysmaps order by name");

		while($row=DBfetch($result))
		{
			if(!check_right("Network map","R",$row["sysmapid"]))
			{
				continue;
			}
			if( isset($sysmapid) && ($sysmapid == $row["sysmapid"]) )
			{
				echo "<b>[";
			}
			echo "<a href='maps.php?sysmapid=".$row["sysmapid"]."'>".$row["name"]."</a>";
			if(isset($sysmapid) && ($sysmapid == $row["sysmapid"]) )
			{
				echo "]</b>";
			}
			echo " ";
		}

		if(DBnum_rows($result) == 0)
		{
			echo "No maps to display";
		}

		echo "</font>";
		show_table_header_end();
		echo "<br>";
	}
?>

<?
	if(isset($sysmapid))
	{
		$result=DBselect("select name from sysmaps where sysmapid=$sysmapid");
		$map=DBget_field($result,0,0);
		if(isset($fullscreen))
		{
			$map="<a href=\"maps.php?sysmapid=$sysmapid\">".$map."</a>";;
		}
		else
		{
			$map="<a href=\"maps.php?sysmapid=$sysmapid&fullscreen=1\">".$map."</a>";;
		}
	}
	else
	{
		$map="Select map to display";
	}

	show_table_header($map);

	echo "<TABLE BORDER=0 COLS=4 WIDTH=\"100%\" BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#EEEEEE>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	if(isset($sysmapid))
	{
		$map="\n<map name=links>";
		$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,sh.x,sh.y,h.status from sysmaps_hosts sh,hosts h where sh.sysmapid=$sysmapid and h.hostid=sh.hostid");
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$host=DBget_field($result,$i,0);
			$shostid=DBget_field($result,$i,1);
			$sysmapid=DBget_field($result,$i,2);
			$hostid=DBget_field($result,$i,3);
			$label=DBget_field($result,$i,4);
			$x=DBget_field($result,$i,5);
			$y=DBget_field($result,$i,6);
			$status=DBget_field($result,$i,7);

			if( ($status==0)||($status==2))
			{
				$map=$map."\n<area shape=rect coords=$x,$y,".($x+32).",".($y+32)." href=\"tr_status.php?hostid=$hostid&noactions=true&onlytrue=true&compact=true\" alt=\"$host\">";
			}
		}
		$map=$map."\n</map>";
		echo $map;
		echo "<IMG SRC=\"map.php?noedit=1&sysmapid=$sysmapid\" border=0 usemap=#links>";
	}
	else
	{
		echo "...";
	}
	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";
?>

<?
	show_footer();
?>
