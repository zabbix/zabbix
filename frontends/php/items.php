<?
        $page["title"] = "Configuration of items";
        $page["file"] = "items.php";

        include "include/config.inc";
	show_header($page["title"],0,0);
?>

<?
	if(isset($register))
	{
		if($register=="update")
		{
			$result=update_item($itemid,$description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type);
			show_messages($result,"Item updated","Cannot update item");
			unset($itemid);
		}
		if($register=="changestatus")
		{
			$result=update_item_status($itemid,$status);
			show_messages($result,"Status of item changed","Cannot change item status");
			unset($itemid);
		}
		if($register=="add")
		{
			$result=add_item($description,$key,$hostid,$delay,$history,$status,$type,$snmp_community,$snmp_oid,$value_type);
			show_messages($result,"Item added","Cannot add item");
			unset($itemid);
		}
		if($register=="delete")
		{
			$result=delete_item($itemid);
			show_messages($result,"Item deleted","Cannot delete item");
			unset($itemid);
		}
	}
?>

<?
	show_table_header_begin();
	echo "CONFIGURATION OF ITEMS";
	show_table_v_delimiter();
?>

<?
	$result=DBselect("select hostid,host from hosts order by host");
	while($row=DBfetch($result))
	{
		if(isset($hostid) && ($hostid == $row["hostid"]))
		{
			echo "<b>[";
		}
		echo "<A HREF=\"items.php?hostid=".$row["hostid"]."\">".$row["host"]."</A>";
		if(isset($hostid) && ($hostid == $row["hostid"]))
		{
			echo "]</b>";
		}
		echo " ";
	}
	show_table_header_end();

	$lasthost="";
	if(isset($hostid)&&!isset($itemid)) 
	{
		$result=DBselect("select h.host,i.key_,i.itemid,i.description,h.port,i.delay,i.history,i.lastvalue,i.lastclock,i.status,i.lastdelete,i.nextcheck,h.hostid from hosts h,items i where h.hostid=i.hostid and h.hostid=$hostid order by h.host,i.key_,i.description");
		echo "<CENTER>";
		$col=0;
		while($row=DBfetch($result))
		{
			if($lasthost != $row["host"])
			{
				if($lasthost != "")
				{
					echo "</TABLE><BR>";
				}
				echo "<br>";
				show_table_header("<A HREF='items.php?hostid=".$row["hostid"]."'>".$row["host"]."</A>");
				echo "<TABLE BORDER=0 COLS=13  WIDTH=\"100%\" BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
				echo "<TR>";
				echo "<TD WIDTH=\"10%\" NOSAVE><B>Host</B></TD>";
				echo "<TD WIDTH=\"10%\" NOSAVE><B>Key</B></TD>";
				echo "<TD WIDTH=\"10%\" NOSAVE><B>Description</B></TD>";
				echo "<TD WIDTH=\"5%\"  NOSAVE><B>Delay</B></TD>";
				echo "<TD WIDTH=\"5%\"  NOSAVE><B>History</B></TD>";
				echo "<TD><B>Shortname</B></TD>";
				echo "<TD WIDTH=\"5%\" NOSAVE><B>Status</B></TD>";
				echo "<TD WIDTH=\"5%\" NOSAVE><B>Actions</B></TD>";
				echo "</TR>";
			}
			$lasthost=$row["host"];
		        if($col++%2 == 1)	{ echo "<TR BGCOLOR=#DDDDDD>"; }
			else			{ echo "<TR BGCOLOR=#EEEEEE>"; }

			echo "<TD>".$row["host"]."</TD>";
			echo "<TD>".$row["key_"]."</TD>";
			echo "<TD>".$row["description"]."</TD>";
			echo "<TD>".$row["delay"]."</TD>";
			echo "<TD>".$row["history"]."</TD>";
			echo "<TD>".$row["host"].":".$row["key_"]."</TD>";
	
			echo "<td align=center>";
			if(isset($hostid))
			{
				switch($row["status"])
				{
					case 0:
						echo "<a href=\"items.php?itemid=".$row["itemid"]."&hostid=$hostid&register=changestatus&status=1\">Active</a>";
						break;
					case 1:
						echo "<a href=\"items.php?itemid=".$row["itemid"]."&hostid=".$row["hostid"]."&register=changestatus&status=0\">Not active</a>";
						break;
					case 2:
						echo "Trapper";
						break;
					case 3:
						echo "Not supported";
						break;
					default:
						echo "<B>$status</B> Unknown";
				}
			}
			else
			{
				switch($row["status"])
				{
					case 0:
						echo "<a href=\"items.php?itemid=".$row["itemid"]."&register=changestatus&status=1\">Active</a>";
						break;
					case 1:
						echo "<a href=\"items.php?itemid=".$row["itemid"]."&register=changestatus&status=0\">Not active</a>";
						break;
					case 2:
						echo "Trapper";
						break;
					case 3:
						echo "Not supported";
						break;
					default:
						echo "<B>$status</B> Unknown";
				}
			}
			echo "</td>";
	
			echo "<TD><A HREF=\"items.php?itemid=".$row["itemid"]."#form\">Change</A></TD>";
			echo "</TR>";
		}
		echo "</TABLE>";
	}
	else
	{
//		echo "<center>Select Host</center>";
	}
?>

<?
	$result=DBselect("select count(*) from hosts");
	if(DBget_field($result,0,0)>0)
	{
		echo "<a name=\"form\"></a>";
		@insert_item_form($itemid);
	}
?>

<?
	show_footer();
?>
