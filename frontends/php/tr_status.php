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
	$page["title"] = S_STATUS_OF_TRIGGERS;
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
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_footer();
		exit;
	}
	if(isset($HTTP_GET_VARS["hostid"])&&!check_right("Host","R",$HTTP_GET_VARS["hostid"]))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
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
		echo S_STATUS_OF_TRIGGERS_BIG;
	        show_table_v_delimiter();

	        $result=DBselect("select hostid,host from hosts where status in (0,2) order by host");
	        if(isset($HTTP_GET_VARS["hostid"]))
	        {
	                echo "<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&sort=$sort\">".S_ALL_SMALL."</A>  ";
	        }
	        else
	        {
	                echo "<b>[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&sort=$sort\">".S_ALL_SMALL."</A>]</b>  ";
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
			echo "[<A HREF=\"tr_status.php?onlytrue=true&noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select&sort=$sort$cond\">".S_SHOW_ONLY_TRUE."</a>] ";
		}
		else
		{
			echo "[<A HREF=\"tr_status.php?noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select&sort=$sort$cond\">".S_SHOW_ALL_TRIGGERS."</A>] ";
		}
		if($noactions!='true')
		{
			echo "[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=true&compact=$compact&select=$select&txt_select=$txt_select&sort=$sort$cond\">".S_HIDE_ACTIONS."</A>] ";
		}
		else
		{
			echo "[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=false&compact=$compact&select=$select&txt_select=$txt_select&sort=$sort$cond\">".S_SHOW_ACTIONS."</A>] ";
		}
		if($compact!='true')
		{
			echo "[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=true&select=$select&txt_select=$txt_select&sort=$sort$cond\">".S_HIDE_DETAILS."</A>] ";
		}
		else
		{
			echo "[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=false&select=$select&txt_select=$txt_select&sort=$sort$cond\">".S_SHOW_DETAILS."</A>] ";
		}
		
		if($select!='true')
		{
			echo "[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=true&txt_select=$txt_select&sort=$sort$cond\">".S_SELECT."</A>] ";
		}
		else
		{
			echo "[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=false&sort=$sort$cond\">".S_HIDE_SELECT."</A>] "; 
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
		show_table_header("<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&sort=$sort\">".S_TRIGGERS_BIG." $time</A>");

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
		table_td("<B>".S_NOT_CLASSIFIED.": $p0</B>","");
		table_td("<B>".S_INFORMATION.": $p1</B>","");
		table_td("<B>".S_WARNING.": $p2</B>","");
		table_td("<B>".S_AVERAGE.": $p3</B>","BGCOLOR=#DDAAAA");
		table_td("<B>".S_HIGH.": $p4</B>","BGCOLOR=#FF8888");
		table_td("<B>".S_DISASTER.": $p5</B>","BGCOLOR=RED");
		echo "</TR>";
		echo "</TABLE>";
	}
	else
	{
		show_table_header("<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&fullscreen=1&sort=$sort\">".S_TRIGGERS_BIG." $time</A>");
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
		echo "<TD ALIGN=LEFT><B>".S_DESCRIPTION_BIG;
	}
	else
	{
		if($select=="TRUE")
		{
			echo "<TD ALIGN=LEFT><B><A HREF=\"tr_status.php?sort=description&onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select$fullscreen$cond\">".S_DESCRIPTION."</a>";
		}
		else
		{
			echo "<TD ALIGN=LEFT><B><A HREF=\"tr_status.php?sort=description&onlytrue=$onlytrue&noactions=$noactions&compact=$compact$fullscreen$cond\">".S_DESCRIPTION."</a>";
		}
	}
	if($compact!='true') {echo "<BR><FONT SIZE=-1>".S_EXPRESSION."</FONT></B>";}
	echo "</TD>";

	table_td("<B>".S_STATUS."</B>","WIDTH=%5");

	if(!isset($sort)||(isset($sort) && $sort=="priority"))
	{
		echo "<TD WIDTH=9% ALIGN=CENTER><B>".S_SEVERITY_BIG."</B></TD>";
	}
	else
	{
		if($select=="TRUE")
		{
			echo "<TD ALIGN=CENTER><B><A HREF=\"tr_status.php?sort=priority&onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select$fullscreen$cond\">".S_SEVERITY."</a>";
		}
		else
		{
			echo "<TD ALIGN=CENTER><B><A HREF=\"tr_status.php?sort=priority&onlytrue=$onlytrue&noactions=$noactions&compact=$compact$fullscreen$cond\">".S_SEVERITY."</a>";
		}
	}
	if($noactions=='true')
		echo "<TD WIDTH=12% ALIGN=CENTER>";
	else
		echo "<TD WIDTH=5% ALIGN=CENTER>";

	if(isset($sort) && $sort=="lastchange")
	{
		echo "<B>".S_LAST_CHANGE_BIG;
	}
	else
	{
		if($select=="TRUE")
		{
			echo "<B><A HREF=\"tr_status.php?sort=lastchange&onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select$fullscreen$cond\">".S_LAST_CHANGE."</a>";
		}
		else
		{
			echo "<B><A HREF=\"tr_status.php?sort=lastchange&onlytrue=$onlytrue&noactions=$noactions&compact=$compact$fullscreen$cond\">".S_LAST_CHANGE."</a>";
		}
	}
	echo "</TD>";
   
	if($noactions!='true')
	{  
		echo "<TD WIDTH=8% NOSAVE><B>".S_ACTIONS."</B></TD>";
	}
	echo "<TD WIDTH=9%><B>".S_COMMENTS."</B></TD>";
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

		if($row["priority"]==0)		echo "<TD ALIGN=CENTER>".S_NOT_CLASSIFIED."</TD>";
		elseif($row["priority"]==1)	echo "<TD ALIGN=CENTER>".S_INFORMATION."</TD>";
		elseif($row["priority"]==2)	echo "<TD ALIGN=CENTER>".S_WARNING."</TD>";
		elseif($row["priority"]==3)	echo "<TD ALIGN=CENTER BGCOLOR=#DDAAAA>".S_AVERAGE."</TD>";
		elseif($row["priority"]==4)	echo "<TD ALIGN=CENTER BGCOLOR=#FF8888>".S_HIGH."</TD>";
		elseif($row["priority"]==5)	echo "<TD ALIGN=CENTER BGCOLOR=RED>".S_DISASTER."</TD>";
		else				echo "<TD ALIGN=CENTER><B>".$row["priority"]."</B></TD>";

		echo "<TD ALIGN=CENTER><A HREF=\"alarms.php?triggerid=".$row["triggerid"]."\">".date("d M H:i:s",$row["lastchange"])."</a>";
		echo "</TD>";

		if($noactions!='true')
		{
			echo "<TD>";
			echo "<A HREF=\"actions.php?triggerid=".$row["triggerid"]."\">".S_SHOW_ACTIONS."</A> - ";
			echo "<A HREF=\"alarms.php?triggerid=".$row["triggerid"]."\">".S_HISTORY."</A> - ";
			if(isset($HTTP_GET_VARS["hostid"]))
			{
				echo "<A HREF=\"triggers.php?hostid=".$HTTP_GET_VARS["hostid"]."&triggerid=".$row["triggerid"]."#form\">".S_CHANGE."</A>";
			}
			else
			{
				echo "<A HREF=\"triggers.php?triggerid=".$row["triggerid"]."#form\">".S_CHANGE."</A>";
			}
			echo "</TD>";
		}
		if($row["comments"] != "")
		{
			echo "<TD ALIGN=CENTER><A HREF=\"tr_comments.php?triggerid=".$row["triggerid"]."\">".S_SHOW."</a></TD>";
		}
		else
		{
			echo "<TD ALIGN=CENTER><A HREF=\"tr_comments.php?triggerid=".$row["triggerid"]."\">".S_ADD."</a></TD>";
		}
		if($row["value"] == 0)	echo "</TR>\n";
		cr();
	}
	echo "</TABLE>";

	show_table_header(S_TOTAL.":$col");
?>

<?php
	show_footer();
?>
