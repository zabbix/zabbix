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
	$result=DBselect("select serviceid,name,triggerid,status from services order by name");
	echo "<table border=0 width=100% bgcolor='#CCCCCC' cellspacing=1 cellpadding=3>";
	echo "\n";
	echo "<tr>";
	echo "<td><b>Service</b></td>";
	echo "<td width=\"10%\"><b>Status</b></td>";
	echo "<td width=\"10%\"><b>Actions</b></td>";
	echo "</tr>";
	echo "\n";
	$col=0;
	if(isset($serviceid))
	{
		echo "<tr bgcolor=#EEEEEE>";
		$service=get_service_by_serviceid($serviceid);
		echo "<td><b><a href=\"srv_status.php?serviceid=".$service["serviceid"]."\">".$service["name"]."</a></b></td>";
		echo "<td>".$service["status"]."</td>";
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
		$childs=get_num_of_service_childs($row["serviceid"]);
		if(isset($row["triggerid"]))
		{
			$trigger=get_trigger_by_triggerid($row["triggerid"]);
			$description="[<a href=\"alarms.php?triggerid=".$row["triggerid"]."\">TRIGGER</a>] ".$trigger["description"];
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
				echo "<td> - <a href=\"srv_status.php?serviceid=".$row["serviceid"]."\">$description</a></td>";
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
		echo "<td>".$row["status"]."</td>";
		echo "<td>[Root of the problem]</td>";
		echo "</tr>"; 
	}
	echo "</table>";
?>

<?
	show_footer();
?>
