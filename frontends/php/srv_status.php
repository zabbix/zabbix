<?
	$page["title"] = "High-level representation of monitored data";
	$page["file"] = "srv_status.php";

	include "include/config.inc";
	show_header($page["title"],0,0);
?>
 
<?
	show_table_header("IT SERVICES");

	echo "<br>";

	show_table_header("SERVICES");
?>
<?
	$now=time();
	$result=DBselect("select serviceid,name,triggerid from services order by name");
	echo "<table border=0 width=100% bgcolor='#CCCCCC' cellspacing=1 cellpadding=3>";
	echo "\n";
	echo "<tr><td><b>Service</b></td></tr>";
	echo "\n";
	$col=0;
	if(isset($serviceid))
	{
		echo "<tr bgcolor=#EEEEEE>";
		$service=get_service_by_serviceid($serviceid);
		echo "<td><b><a href=\"srv_status.php?serviceid=".$service["serviceid"]."\">".$service["name"]."</a></b></td>";
		echo "</tr>"; 
		$col++;
	}
	while($row=DBfetch($result))
	{
		if(!isset($serviceid) && service_has_parent($row["serviceid"]))
		{
			continue;
		}
		if(isset($serviceid) && service_has_no_this_parent($serviceid,$row["serviceid"]))
		{
			continue;
		}
		if(isset($serviceid)&&($serviceid==$row["serviceid"]))
		{
			echo "<tr bgcolor=#99AABB>";
		}
		else
		{
			if($col++%2==0)	{ echo "<tr bgcolor=#EEEEEE>"; }
			else		{ echo "<tr bgcolor=#DDDDDD>"; }
		}
//		echo "<td><a href=\"srv_status.php?serviceid=".$row["serviceid"]."\">".$row["name"]."</a></td>";
		$childs=get_num_of_service_childs($row["serviceid"]);
		if(isset($row["triggerid"]))
		{
			$trigger=get_trigger_by_triggerid($row["triggerid"]);
			$description="[TRIGGER] ".$trigger["description"];
		}
		else
		{
			$trigger_link="";
			$description=$row["name"];
		}
		if(isset($serviceid))
		{
			if($childs == 0)
			{
				echo "<td> - $description</td>";
			}
			else
			{
				echo "<td><a href=\"srv_status.php?serviceid=".$row["serviceid"]."\"> - $description</a></td>";
			}
		}
		else
		{
			if($childs == 0)
			{
				echo "<td>$description</td>";
			}
			else
			{
				echo "<td><a href=\"srv_status.php?serviceid=".$row["serviceid"]."\"> $description</a></td>";
			}
		}
		echo "</tr>"; 
	}
	echo "</table>";
?>

<?
	show_footer();
?>
