<?
	$page["title"] = "Information about monitoring server";
	$page["file"] = "queue.php";

	include "include/config.inc";
	show_header($page["title"],10,0);
?>
 
<?
	show_table_header("QUEUE OF ITEMS TO BE UPDATED");

	echo "<br>";

	show_table_header("QUEUE");
?>
<?
	$now=time();
	$result=DBselect("select i.itemid, i.nextcheck, i.description, h.host from items i,hosts h where i.status=0 and h.status in (0,2) and i.hostid=h.hostid and i.nextcheck<$now order by i.nextcheck");
	echo "<table border=0 width=100% bgcolor='#CCCCCC' cellspacing=1 cellpadding=3>";
	echo "\n";
	echo "<tr><td><b>Next time to check</b></td><td><b>Host</b></td><td><b>Description</b></td></tr>";
	echo "\n";
	$col=0;
	while($row=DBfetch($result))
	{
		if($col++%2==0)	{ echo "<tr bgcolor=#EEEEEE>"; }
		else		{ echo "<tr bgcolor=#DDDDDD>"; }
		echo "<td>".date("m.d.Y H:i:s",$row["nextcheck"])."</td>";
		echo "<td>".$row["host"]."</td>";
		echo "<td>".$row["description"]."</td>";
		echo "</tr>"; 
	}
	echo "</table>";
?>
<?
	$i=DBnum_rows($result);
	show_table_header("Total:$i");
?>

<?
	show_footer();
?>
