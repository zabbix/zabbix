<?php
/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
	$page["title"] = "Status of triggers";
	$page["file"] = "tr_status.php";

	include "include/config.inc.php";
?>
<?php
	$tr_hash=calc_trigger_hash();
	setcookie("triggers_hash",$tr_hash,time()+1800);

	if(!isset($HTTP_COOKIE_VARS["triggers_hash"]))
	{
		$triggers_hash="0,0";
	}
	else
	{
		$triggers_hash=$HTTP_COOKIE_VARS["triggers_hash"];
	}

	$new=explode(",",$tr_hash);
	$old=explode(",",$triggers_hash);
	setcookie("triggers_hash",$tr_hash,time()+1800);

	if(!isset($HTTP_COOKIE_VARS["triggers_hash"]))
	{
		$triggers_hash="0,0";
	}
	else
	{
		$triggers_hash=$HTTP_COOKIE_VARS["triggers_hash"];
	}

	$new=explode(",",$tr_hash);
	$old=explode(",",$triggers_hash);

//	Number of trigger decreased
//	echo $new[0]," ",$old[0];
	if(($old[1]!=$new[1])&&($new[0]<$old[0]))
	{
		$audio="warning_off.wav";
	}
//	Number of trigger increased
	if(($old[1]!=$new[1])&&($new[0]>=$old[0]))
	{
// DISASTER
		if(($new[0]-$old[0])/pow(10,5)>=1)
		{
			$audio="disaster_on.wav";
		}
		else
		{
			$audio="warning_on.wav";
		}
	}

//	echo "$tr_hash<br>$triggers_hash<br>".$old[1]."<br>".$new[1];
?>
<?php
	$refresh=10;
	if(!isset($HTTP_GET_VARS["onlytrue"])||isset($HTTP_GET_VARS["txt_select"]))
	{
		$refresh=0;
	}
	if(isset($HTTP_GET_VARS["fullscreen"]))
	{
		show_header($page["title"],$refresh,1);
	}
	else
	{
		show_header($page["title"],$refresh,0);
	}
?>
<?php
	if(!check_anyright("Host","R"))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
	if(isset($HTTP_GET_VARS["hostid"])&&!check_right("Host","R",$HTTP_GET_VARS["hostid"]))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
?>
<?php
	if(isset($audio))
	{
//		echo "AUDIO [$audio] [".$old[1].":".$new[1]."] [$triggers_hash] [$tr_hash]";
		echo "<BGSOUND src=\"audio/$audio\" loop=0>";
	}
?>                                                                                                             

<?php
 
	if(!isset($HTTP_GET_VARS["sort"]))
	{
		$sort='priority';
	}
	else
	{
		$sort=$HTTP_GET_VARS["sort"];
	}
	if(!isset($HTTP_GET_VARS["onlytrue"]))
	{
		$onlytrue='false';
	}
	else
	{
		$onlytrue=$HTTP_GET_VARS["onlytrue"];
	}
	if(isset($HTTP_GET_VARS["noactions"])&&($HTTP_GET_VARS["noactions"]!='true'))
	{
		$noactions='false';
	}
	else
	{
		$noactions='true';
	}
	if(isset($HTTP_GET_VARS["compact"])&&($HTTP_GET_VARS["compact"]!='true'))
	{
		$compact='false';
	}
	else
	{
		$compact='true';
	}
?>

<?php
	if(!isset($HTTP_GET_VARS["select"]))
	{
		$select="";
	}
	else
	{
		$select=$HTTP_GET_VARS["select"];
	}

	if(!isset($HTTP_GET_VARS["txt_select"]))
	{
		$txt_select="";
	}
	else
	{
		$txt_select=$HTTP_GET_VARS["txt_select"];
	}

	if(isset($HTTP_GET_VARS["btnSelect"])&&($HTTP_GET_VARS["btnSelect"]=="Inverse select"))
	{
		$select_cond="not like '%$txt_select%'";
	}
	else
	{
		$select_cond="like '%$txt_select%'";
	}

	if(!isset($HTTP_GET_VARS["fullscreen"]))
	{
		show_table_header_begin();
		echo "STATUS OF TRIGGERS";
	        show_table_v_delimiter();

	        $result=DBselect("select hostid,host from hosts where status in (0,2) order by host");
	        if(isset($HTTP_GET_VARS["hostid"]))
	        {
	                echo "<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&sort=$sort\">all</A>  ";
	        }
	        else
	        {
	                echo "<b>[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&sort=$sort\">all</A>]</b>  ";
	        }
		while($row=DBfetch($result))
	        {
        		if(!check_right("Host","R",$row["hostid"]))
		        {
				continue;
			}
	                if(isset($HTTP_GET_VARS["hostid"]) && ($row["hostid"] == $HTTP_GET_VARS["hostid"]))
	                {
	                        echo "<b>[<A HREF=\"tr_status.php?hostid=".$row["hostid"]."&onlytrue=$onlytrue&noactions=$noactions&compact=$compact&sort=$sort\">".$row["host"]."</A>]</b>  ";
	                }
	                else
	                {
	                        echo "<A HREF=\"tr_status.php?hostid=".$row["hostid"]."&onlytrue=$onlytrue&noactions=$noactions&compact=$compact&sort=$sort\">".$row["host"]."</A>  ";
	                }
	        }
	 
		show_table_v_delimiter();
?>

<?php
		if(isset($HTTP_GET_VARS["hostid"]))
		{
			$cond="&hostid=".$HTTP_GET_VARS["hostid"];
		}
		else
		{
			$cond="";
		}

		if($onlytrue!='true')
		{
			echo "[<A HREF=\"tr_status.php?onlytrue=true&noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select&sort=$sort$cond\">Show only true</a>] ";
		}
		else
		{
			echo "[<A HREF=\"tr_status.php?noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select&sort=$sort$cond\">Show all triggers</A>] ";
		}
		if($noactions!='true')
		{
			echo "[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=true&compact=$compact&select=$select&txt_select=$txt_select&sort=$sort$cond\">Hide Actions</A>] ";
		}
		else
		{
			echo "[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=false&compact=$compact&select=$select&txt_select=$txt_select&sort=$sort$cond\">Show Actions</A>] ";
		}
		if($compact!='true')
		{
			echo "[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=true&select=$select&txt_select=$txt_select&sort=$sort$cond\">Hide Details</A>] ";
		}
		else
		{
			echo "[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=false&select=$select&txt_select=$txt_select&sort=$sort$cond\">Show Details</A>] ";
		}
		
		if($select!='true')
		{
			echo "[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=true&txt_select=$txt_select&sort=$sort$cond\">Select</A>] ";
		}
		else
		{
			echo "[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=false&sort=$sort$cond\">Hide Select</A>] "; 
			show_table_v_delimiter();
			echo "<form name=\"form1\" method=\"get\" action=\"tr_status.php?select=true\">
  			<input class=\"biginput\" type=\"text\" name=\"txt_select\" value=\"$txt_select\">
  			<input class=\"button\" type=\"submit\" name=\"btnSelect\" value=\"Select\">
  			<input class=\"button\" type=\"submit\" name=\"btnSelect\" value=\"Inverse select\">
			<INPUT NAME=\"compact\" TYPE=\"HIDDEN\" value=\"$compact\">
			<INPUT NAME=\"onlytrue\" TYPE=\"HIDDEN\" value=\"$onlytrue\">
			<INPUT NAME=\"noactions\" TYPE=\"HIDDEN\" value=\"$noactions\">			
		        <INPUT NAME=\"select\" TYPE=\"HIDDEN\" value=\"$select\">
			</form>";
		}
		show_table_header_end();
		echo "<br>";
	}

 	$time=date("[H:i:s]",time());
  	if(isset($HTTP_GET_VARS["fullscreen"]))
	{
		show_table_header("<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&sort=$sort\">TRIGGERS $time</A>");

		$cond="";
		if(isset($HTTP_GET_VARS["hostid"]))
		{
			$cond=" and h.hostid=".$HTTP_GET_VARS["hostid"]." ";
		}

		if($onlytrue=='true')
		{
			$sql="select t.priority,count(*) from triggers t,hosts h,items i,functions f  where t.value=1 and t.status=0 and f.itemid=i.itemid and h.hostid=i.hostid and h.status in (0,2) and t.triggerid=f.triggerid and t.description $select_cond and i.status=0 $cond group by 1";
		}
		else
		{
			$sql="select t.priority,count(*) from triggers t,hosts h,items i,functions f  where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and t.status=0 and h.status in (0,2) and t.description $select_cond and i.status=0 $cond group by 1";
		}
		$result=DBselect($sql);
		$p0=$p1=$p2=$p3=$p4=$p5=0;
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$priority=DBget_field($result,$i,0);
			$count=DBget_field($result,$i,1);
			if($priority==0) $p0=$count;
			if($priority==1) $p1=$count;
			if($priority==2) $p2=$count;
			if($priority==3) $p3=$count;
			if($priority==4) $p4=$count;
			if($priority==5) $p5=$count;
		}
		echo "\n<TABLE BORDER=0 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=0>";
		echo "<TR ALIGN=CENTER>";
		echo "<TD><B>Not classified: $p0</B></TD>";
		echo "<TD><B>Information: $p1</B></TD>";
		echo "<TD><B>Warning: $p2</B></TD>";
		echo "<TD BGCOLOR=#DDAAAA><B>Average: $p3</B></TD>";
		echo "<TD BGCOLOR=#FF8888><B>High: $p4</B></TD>";
		echo "<TD BGCOLOR=RED><B>Disaster: $p5</B></TD>";
		echo "</TR>";
		echo "</TABLE>";
	}
	else
	{
		show_table_header("<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&fullscreen=1&sort=$sort\">TRIGGERS $time</A>");
	}
  
	echo "<TABLE BORDER=0 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";

	echo "<TR ALIGN=CENTER>";
	if(isset($HTTP_GET_VARS["fullscreen"]))
	{
		$fullscreen="&fullscreen=1";
	}
	else
	{
		$fullscreen="";
	}
	if(isset($sort) && $sort=="description")
	{
		echo "<TD ALIGN=LEFT><B>DESCRIPTION";
	}
	else
	{
		if($select=="TRUE")
		{
			echo "<TD ALIGN=LEFT><B><A HREF=\"tr_status.php?sort=description&onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select$fullscreen$cond\">Description</a>";
		}
		else
		{
			echo "<TD ALIGN=LEFT><B><A HREF=\"tr_status.php?sort=description&onlytrue=$onlytrue&noactions=$noactions&compact=$compact$fullscreen$cond\">Description</a>";
		}
	}
	if($compact!='true') {echo "<BR><FONT SIZE=-1>Expression</FONT></B>";}
	echo "</TD>";

	echo "<TD WIDTH=5%><B>Status</B></TD>";

	if(!isset($sort)||(isset($sort) && $sort=="priority"))
	{
		echo "<TD WIDTH=9% ALIGN=CENTER><B>SEVERITY</B></TD>";
	}
	else
	{
		if($select=="TRUE")
		{
			echo "<TD ALIGN=CENTER><B><A HREF=\"tr_status.php?sort=priority&onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select$fullscreen$cond\">Severity</a>";
		}
		else
		{
			echo "<TD ALIGN=CENTER><B><A HREF=\"tr_status.php?sort=priority&onlytrue=$onlytrue&noactions=$noactions&compact=$compact$fullscreen$cond\">Severity</a>";
		}
	}
	if($noactions=='true')
		echo "<TD WIDTH=12% ALIGN=CENTER>";
	else
		echo "<TD WIDTH=5% ALIGN=CENTER>";

	if(isset($sort) && $sort=="lastchange")
	{
		echo "<B>LAST CHANGE";
	}
	else
	{
		if($select=="TRUE")
		{
			echo "<B><A HREF=\"tr_status.php?sort=lastchange&onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select$fullscreen$cond\">Last change</a>";
		}
		else
		{
			echo "<B><A HREF=\"tr_status.php?sort=lastchange&onlytrue=$onlytrue&noactions=$noactions&compact=$compact$fullscreen$cond\">Last change</a>";
		}
	}
	echo "</TD>";
   
	if($noactions!='true')
	{  
		echo "<TD WIDTH=8% NOSAVE><B>Actions</B></TD>";
	}
	echo "<TD WIDTH=9%><B>Comments</B></TD>";
	echo "</TR>\n";

	if(isset($HTTP_GET_VARS["hostid"]))
	{
		$cond=" and h.hostid=".$HTTP_GET_VARS["hostid"]." ";
	}
	else
	{
		$cond="";
	}

	if(!isset($sort))
	{
		$sort="priority";
	}

	switch ($sort)
	{
		case "description":
			$sort="order by t.description";
			break;
		case "priority":
			$sort="order by t.priority desc, t.description";
			break;
		case "lastchange":
			$sort="order by t.lastchange desc, t.priority";
			break;
		default:
			$sort="order by t.priority desc, t.description";
	}

	if($onlytrue=='true')
	{
		$result=DBselect("select distinct t.triggerid,t.status,t.description,t.expression,t.priority,t.lastchange,t.comments,t.url,t.value from triggers t,hosts h,items i,functions f  where t.value=1 and t.status=0 and f.itemid=i.itemid and h.hostid=i.hostid and t.description $select_cond and t.triggerid=f.triggerid and i.status in (0,2) and h.status in (0,2) $cond $sort");
	}
	else
	{
		$result=DBselect("select distinct t.triggerid,t.status,t.description,t.expression,t.priority,t.lastchange,t.comments,t.url,t.value from triggers t,hosts h,items i,functions f  where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and t.status=0 and t.description $select_cond and i.status in (0,2) and h.status in (0,2) $cond $sort");
	}
	$col=0;
	while($row=DBfetch($result))
	{
		if(!check_right_on_trigger("R",$row["triggerid"]))
		{
			continue;
		}

// Check for dependencies

		$sql="select count(*) from trigger_depends d, triggers t where d.triggerid_down=".$row["triggerid"]." and d.triggerid_up=t.triggerid and t.value=1";
		$result2=DBselect($sql);

		if(DBget_field($result2,0,0)>0)
		{
			continue;
		}


		if($col++%2 == 1)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else			{ echo "<TR BGCOLOR=#DDDDDD>"; }

		echo "<TD>";

//		$description=$row["description"];

//		if( strstr($description,"%s"))
//		{
			$description=expand_trigger_description($row["triggerid"]);
//		}

		if($row["url"] == "")
		{
			echo $description;
		}
		else
		{
			echo "<a href='".$row["url"]."'>$description</a>";
		}

		if($compact!='true')
		{
			echo "<BR><FONT COLOR=\"#000000\" SIZE=-2>".explode_exp($row["expression"],1)."</FONT>";
		}
		echo "</TD>";
		if( (time(NULL)-$row["lastchange"])<300)
		{
			$blink1="<blink>";
			$blink2="</blink>";
		}
		else
		{
			$blink1="";
			$blink2="";
		}
		if($row["value"]==0)
			{ echo "<TD ALIGN=CENTER>$blink1<FONT COLOR=\"00AA00\">FALSE</FONT>$blink2</TD>";}
		else if($row["value"]==2)
			{  echo "<TD ALIGN=CENTER>$blink1<FONT COLOR=\"AAAAAA\">UNKNOWN</FONT>$blink2</TD>"; }
		else
			{  echo "<TD ALIGN=CENTER>$blink1<FONT COLOR=\"AA0000\">TRUE</FONT>$blink2</TD>"; }

		if($row["priority"]==0)		echo "<TD ALIGN=CENTER>Not classified</TD>";
		elseif($row["priority"]==1)	echo "<TD ALIGN=CENTER>Information</TD>";
		elseif($row["priority"]==2)	echo "<TD ALIGN=CENTER>Warning</TD>";
		elseif($row["priority"]==3)	echo "<TD ALIGN=CENTER BGCOLOR=#DDAAAA>Average</TD>";
		elseif($row["priority"]==4)	echo "<TD ALIGN=CENTER BGCOLOR=#FF8888>High</TD>";
		elseif($row["priority"]==5)	echo "<TD ALIGN=CENTER BGCOLOR=RED>Disaster !!!</TD>";
		else				echo "<TD ALIGN=CENTER><B>".$row["priority"]."</B></TD>";

		echo "<TD ALIGN=CENTER><A HREF=\"alarms.php?triggerid=".$row["triggerid"]."\">".date("d M H:i:s",$row["lastchange"])."</a>";
		echo "</TD>";

		if($noactions!='true')
		{
			echo "<TD>";
			echo "<A HREF=\"actions.php?triggerid=".$row["triggerid"]."\">Show actions</A> - ";
			echo "<A HREF=\"alarms.php?triggerid=".$row["triggerid"]."\">History</A> - ";
			if(isset($HTTP_GET_VARS["hostid"]))
			{
				echo "<A HREF=\"triggers.php?hostid=".$HTTP_GET_VARS["hostid"]."&triggerid=".$row["triggerid"]."#form\">Change</A>";
			}
			else
			{
				echo "<A HREF=\"triggers.php?triggerid=".$row["triggerid"]."#form\">Change</A>";
			}
			echo "</TD>";
		}
		if($row["comments"] != "")
		{
			echo "<TD ALIGN=CENTER><A HREF=\"tr_comments.php?triggerid=".$row["triggerid"]."\">Show</a></TD>";
		}
		else
		{
			echo "<TD ALIGN=CENTER><A HREF=\"tr_comments.php?triggerid=".$row["triggerid"]."\">Add</a></TD>";
		}
		if($row["value"] == 0)	echo "</TR>\n";
		cr();
	}
	echo "</TABLE>";

	show_table_header("Total:$col");
?>

<?php
	show_footer();
?>
