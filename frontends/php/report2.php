<?
	include "include/config.inc.php";
	$page["title"] = "Availability report";
	$page["file"] = "report2.php";
	show_header($page["title"],0,0);
?>

<?
	if(!isset($yes))
	{
		echo "<center>";
		echo "<a href=\"report2.php?yes=1\">";
		echo "Click here to run report";
		echo "</a>";
		echo "</center>";
		show_footer();
		exit;
	}

?>

<?
	show_table_header("AVAILABILITY REPORT");
	echo "<br>";
?>

<?

	$result=DBselect("select h.hostid,h.host,t.triggerid,t.expression,t.description,t.istrue from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.istrue!=2 and t.triggerid=f.triggerid and h.status in (0,2) and i.status=0 order by h.host,t.lastchange desc, t.description");

	$lasthost="";
	$col=0;
	while($row=DBfetch($result))
	{
		if($lasthost!=$row["host"])
		{
			if($lasthost!="")
			{
				echo "</TABLE><BR>";
			}
			show_table_header($row["host"]);
			echo "<TABLE BORDER=0 COLS=3 WIDTH=\"100%\" BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
			echo "<TR>";
			echo "<TD><B>Description</B></TD>";
			echo "<TD><B>Expression</B></TD>";
			echo "<TD WIDTH=\"5%\"><B>True (%)</B></TD>";
			echo "<TD WIDTH=\"5%\"><B>False (%)</B></TD>";
			echo "<TD WIDTH=\"5%\"><B>Unknown (%)</B></TD>";
			echo "</TR>\n";
		}
		$lasthost=$row["host"];

	        if($col++%2 == 1)	{ echo "<TR BGCOLOR=#DDDDDD>"; }
		else			{ echo "<TR BGCOLOR=#EEEEEE>"; }

		echo "<TD>".$row["description"]."</TD>";
		$description=rawurlencode($row["description"]);

		echo "<TD>".explode_exp($row["expression"],1)."</TD>";
		$availability=calculate_availability($row["triggerid"]);
		echo "<TD>";
		printf("%.4f%%",$availability["true"]);
		echo "</TD>";
		echo "<TD>";
		printf("%.4f%%",$availability["false"]);
		echo "</TD>";
		echo "<TD>";
		printf("%.4f%%",$availability["unknown"]);
		echo "</TD>";
		echo "</TR>\n";
	}
	echo "</table>\n";
?>

<?
	show_footer();
?>
