<?php
	$page["file"]="history.php";

	include "include/config.inc.php";


	$now=time();

	$result=DBselect("select h.host,i.description,i.nextcheck-$now,h.hostid from items i,hosts h where i.itemid=".$HTTP_GET_VARS["itemid"]." and h.hostid=i.hostid");
	$host=DBget_field($result,0,0);
	$description=DBget_field($result,0,1);
	$beforenextcheck=DBget_field($result,0,2)+5;
	if($beforenextcheck<=0)
	{
		$beforenextcheck=5;
	}
	$hostid=DBget_field($result,0,3);

	if($HTTP_GET_VARS["action"]=="showhistory")
	{
		if(isset($HTTP_GET_VARS["year"]))
		{
			show_header("$host:$description",0,0);
		}
		else
		{
			show_header("$host:$description",$beforenextcheck,0);
		}
	}
	if($HTTP_GET_VARS["action"]=="showhistory2")
	{
		show_header("$host:$description",0,0);
	}
	if($HTTP_GET_VARS["action"]=="showvalues")
	{
		show_header("$host:$description",0,0);
	}
	if($HTTP_GET_VARS["action"]=="showfreehist")
	{
		show_header("$host:$description",0,0);
	}
	if($HTTP_GET_VARS["action"]=="showplaintxt")
	{
		show_header("$host:$description",0,0);
	}

	if(!check_right("Item","R",$HTTP_GET_VARS["itemid"]))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}

?>

<?php
	if($HTTP_GET_VARS["action"]=="plaintext")
	{
		$from=mktime($fromhour,$frommin,0,$frommonth,$fromday,$fromyear);
		$till=mktime($tillhour,$tillmin,0,$tillmonth,$tillday,$tillyear);
		show_plaintext($HTTP_GET_VARS["itemid"], $from, $till);
		exit;
	}

?>

<?php
	show_table_header_begin();
	$item=get_item_by_itemid($HTTP_GET_VARS["itemid"]);
	if($item["value_type"] == 0)
	{
		echo "<A HREF='latest.php?hostid=$hostid'>$host</A> : <a href='trends.php?itemid=".$HTTP_GET_VARS["itemid"]."'>$description</a>";
	}
	else
	{
		echo "<A HREF='latest.php?hostid=".$HTTP_GET_VARS["hostid"]."'>$host</A> : $description";
	}
	show_table_v_delimiter();
	echo("<DIV ALIGN=CENTER>");
	if($HTTP_GET_VARS["action"] =="showhistory")
	{
		echo("<b>[<A HREF=\"history.php?action=showhistory&itemid=".$HTTP_GET_VARS["itemid"]."\">Last hour graph</A>]</b> ");
	}
	else
	{
		$item=get_item_by_itemid($HTTP_GET_VARS["itemid"]);
		if($item["value_type"] == 0)
		{
			echo("<A HREF=\"history.php?action=showhistory&itemid=".$HTTP_GET_VARS["itemid"]."\">Last hour graph</A> ");
		}
	}
	if($HTTP_GET_VARS["action"] =="showhistory2")
	{
		echo("<b>[<A HREF=\"history.php?action=showhistory2&itemid=".$HTTP_GET_VARS["itemid"]."\">Last hour graph (diff)</A>]</b> ");
	}
	else
	{
		$item=get_item_by_itemid($HTTP_GET_VARS["itemid"]);
		if($item["value_type"] == 0)
		{
			echo("<A HREF=\"history.php?action=showhistory2&itemid=".$HTTP_GET_VARS["itemid"]."\">Last hour graph (diff)</A> ");
		}
	}
	if($HTTP_GET_VARS["action"] =="showvalues")
	{
		echo("<b>[<A HREF=\"history.php?action=showvalues&itemid=".$HTTP_GET_VARS["itemid"]."&period=3600\">Values of last hour</A>]</b> ");
	}
	else
	{
		echo("<A HREF=\"history.php?action=showvalues&itemid=".$HTTP_GET_VARS["itemid"]."&period=3600\">Values of last hour</A> ");
	}
	if($HTTP_GET_VARS["action"] =="showfreehist")
	{
		echo("<b>[<A HREF=\"history.php?action=showfreehist&itemid=".$HTTP_GET_VARS["itemid"]."\">Values of specified period</A>]</b> ");
	}
	else
	{
		echo("<A HREF=\"history.php?action=showfreehist&itemid=".$HTTP_GET_VARS["itemid"]."\">Values of specified period</A> ");
	}
	if($HTTP_GET_VARS["action"] =="showplaintxt")
	{
		echo("<b>[<A HREF=\"history.php?action=showplaintxt&itemid=".$HTTP_GET_VARS["itemid"]."\">Values in plaint text format</A>]</b> ");
	}
	else
	{
		echo("<A HREF=\"history.php?action=showplaintxt&itemid=".$HTTP_GET_VARS["itemid"]."\">Values in plaint text format</A> ");
	}
	echo("</DIV>\n");
	show_table_header_end();
	echo("<br>");

	if($HTTP_GET_VARS["action"]=="showfreehist")
	{
		if(!isset($HTTP_GET_VARS["period"]))
		{
			show_freehist($HTTP_GET_VARS["itemid"],$HTTP_GET_VARS["period"]);
		} 
		exit;
 
	}

	if($HTTP_GET_VARS["action"]=="showplaintxt")
	{
		if(!isset($HTTP_GET_VARS["period"]))
		{
			show_plaintxt($HTTP_GET_VARS["itemid"],$HTTP_GET_VARS["period"]);
		} 
		exit;
   
	}

	if($HTTP_GET_VARS["action"]=="showvalues")
	{
		if(!isset($HTTP_GET_VARS["from"]))
		{
			$HTTP_GET_VARS["from"]=0;
		}
		if(!isset($HTTP_GET_VARS["period"]))
		{
			$HTTP_GET_VARS["period"]=3600;
		}
		$time=time(NULL)-$HTTP_GET_VARS["period"]-$HTTP_GET_VARS["from"]*3600;
		$till=time(NULL)-$HTTP_GET_VARS["from"]*3600;
		$hours=$HTTP_GET_VARS["period"]/3600;

		show_table_header("Showing history of $period seconds($hours h)<BR>[from: ".date("Y.M.d H:i:s",$time)."] [till: ".date("Y.M.d H:i:s",$till)."]");

		echo "<TABLE BORDER=0 COLS=2 ALIGN=CENTER WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
		echo "<TR>";
		echo "<TD><B>Clock</B></TD>";
		echo "<TD><B>Value</B></TD>";
		echo "</TR>";

		$item=get_item_by_itemid($HTTP_GET_VARS["itemid"]);
		if($item["value_type"]==0)
		{
			$result=DBselect("select clock,value from history where itemid=".$HTTP_GET_VARS["itemid"]." and clock>$time and clock<$till order by clock desc");
		}
		else
		{
			$result=DBselect("select clock,value from history_str where itemid=".$HTTP_GET_VARS["itemid"]." and clock>$time and clock<$till order by clock desc");
		}
		$col=0;
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			if($col==1)
			{
				echo "<TR BGCOLOR=#DDDDDD>";
				$col=0;
			} else
			{
				echo "<TR BGCOLOR=#EEEEEE>";
				$col=1;
			}
			$clock=DBget_field($result,$i,0);
			$value=DBget_field($result,$i,1);
			$clock=date("Y.M.d H:i:s",$clock);
			echo "<TD>$clock</TD>";
			echo "<TD>$value</TD>";
			echo "</TR>";
		}
		echo "</TABLE>";        
 
		show_footer();
		exit;
	}

	if($HTTP_GET_VARS["action"]=="showhistory")
	{
		if(!isset($HTTP_GET_VARS["period"]))
		{
			$HTTP_GET_VARS["period"]=3600;
		}
		if(!isset($HTTP_GET_VARS["from"]))
		{
			$HTTP_GET_VARS["from"]=0;
		}
		if(isset($HTTP_GET_VARS["year"]))
		{
			$HTTP_GET_VARS["from"]=($now-mktime($HTTP_GET_VARS["hour"], 0, 0, $HTTP_GET_VARS["month"], $HTTP_GET_VARS["day"],$HTTP_GET_VARS["year"]))/3600;
		}
		@show_history($HTTP_GET_VARS["itemid"],$HTTP_GET_VARS["from"],$HTTP_GET_VARS["period"],0);
		show_footer();
		exit;
	}

	if($HTTP_GET_VARS["action"]=="showhistory2")
	{
		@show_history($HTTP_GET_VARS["itemid"],$HTTP_GET_VARS["from"],$HTTP_GET_VARS["period"],1);
		show_footer();
		exit;
	}

?>
