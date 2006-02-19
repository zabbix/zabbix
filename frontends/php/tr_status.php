<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	include "include/config.inc.php";
	$page["file"] = "tr_status.php";
	$page["title"] = "S_STATUS_OF_TRIGGERS";
?>
<?php

	$tr_hash=calc_trigger_hash();
	setcookie("triggers_hash",$tr_hash,time()+1800);

	if(!isset($_COOKIE["triggers_hash"]))
	{
		$triggers_hash="0,0";
	}
	else
	{
		$triggers_hash=$_COOKIE["triggers_hash"];
	}

	$new=explode(",",$tr_hash);
	$old=explode(",",$triggers_hash);
	setcookie("triggers_hash",$tr_hash,time()+1800);

	if(!isset($_COOKIE["triggers_hash"]))
	{
		$triggers_hash="0,0";
	}
	else
	{
		$triggers_hash=$_COOKIE["triggers_hash"];
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
	if(!isset($_REQUEST["onlytrue"]))
	{
		$_REQUEST["onlytrue"]="true";
	}
	if(!isset($_REQUEST["noactions"]))
	{
		$_REQUEST["noactions"]="true";
	}
	if(isset($_REQUEST["fullscreen"]))
	{
		show_header($page["title"],1,1);
	}
	else
	{
		show_header($page["title"],1,0);
	}
?>
<?php
	if(!check_anyright("Host","R"))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}
	if(isset($_REQUEST["hostid"])&&!check_right("Host","R",$_REQUEST["hostid"]))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}
	update_profile("web.menu.view.last",$page["file"]);
?>
<?php
	if(isset($audio))
	{
//		echo "AUDIO [$audio] [".$old[1].":".$new[1]."] [$triggers_hash] [$tr_hash]";
		echo "<BGSOUND src=\"audio/$audio\" loop=0>";
	}
?>                                                                                                             

<?php
 
	if(!isset($_REQUEST["sort"]))
	{
		$sort='priority';
	}
	else
	{
		$sort=$_REQUEST["sort"];
	}
	$onlytrue=$_REQUEST["onlytrue"];
	if(isset($_REQUEST["noactions"])&&($_REQUEST["noactions"]!='true'))
	{
		$noactions='false';
	}
	else
	{
		$noactions='true';
	}
	if(isset($_REQUEST["compact"])&&($_REQUEST["compact"]!='true'))
	{
		$compact='false';
	}
	else
	{
		$compact='true';
	}
?>

<?php
	if(!isset($_REQUEST["select"]))
	{
		$select="";
	}
	else
	{
		$select=$_REQUEST["select"];
	}

	if(!isset($_REQUEST["txt_select"]))
	{
		$txt_select="";
	}
	else
	{
		$txt_select=$_REQUEST["txt_select"];
	}

	if(isset($_REQUEST["btnSelect"])&&($_REQUEST["btnSelect"]=="Inverse select"))
	{
		$select_cond="not like '%$txt_select%'";
	}
	else
	{
		$select_cond="like '%$txt_select%'";
	}
?>





<?php
	if(isset($_REQUEST["groupid"])&&($_REQUEST["groupid"]==0))
	{
		unset($_REQUEST["groupid"]);
	}
?>

<?php
	$h1=SPACE.S_STATUS_OF_TRIGGERS_BIG;

	$h2="";
	$h2=$h2."<input name=\"onlytrue\" type=\"hidden\" value=\"".$_REQUEST["onlytrue"]."\">";
	$h2=$h2."<input name=\"noactions\" type=\"hidden\" value=\"".$_REQUEST["noactions"]."\">";
	$h2=$h2.S_GROUP.SPACE;
	$h2=$h2."<select class=\"biginput\" name=\"groupid\" onChange=\"submit()\">";
	$h2=$h2.form_select("groupid",0,S_ALL_SMALL);

	$result=DBselect("select groupid,name from groups order by name");
	while($row=DBfetch($result))
	{
// Check if at least one host with read permission exists for this group
		$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host");
		$cnt=0;
		while($row2=DBfetch($result2))
		{
			if(!check_right("Host","R",$row2["hostid"]))
			{
				continue;
			}
			$cnt=1; break;
		}
		if($cnt!=0)
		{
			$h2=$h2.form_select("groupid",$row["groupid"],$row["name"]);
		}
	}
	$h2=$h2."</select>";

	$h2=$h2.SPACE.S_HOST.SPACE;
	$h2=$h2."<select class=\"biginput\" name=\"hostid\" onChange=\"submit()\">";
	$h2=$h2.form_select("hostid",0,S_SELECT_HOST_DOT_DOT_DOT);

	if(isset($_REQUEST["groupid"])&&($_REQUEST["groupid"]!=0))
	{
		$groupcond=" and hg.hostid=h.hostid and hg.groupid=".$_REQUEST["groupid"]." ";
		$groupname=",hosts_groups hg";
	}
	else
	{
		$groupcond="";
		$groupname="";
	}
	$sql="select h.hostid,h.host from hosts h,items i".$groupname." where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid $groupcond group by h.hostid,h.host order by h.host";

/*
	if(isset($_REQUEST["groupid"]))
	{
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host";
	}
	else
	{
		$sql="select h.hostid,h.host from hosts h,items i where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid group by h.hostid,h.host order by h.host";
	}
*/

	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		$h2=$h2.form_select("hostid",$row["hostid"],$row["host"]);
	}
	$h2=$h2."</select>";

	$h2=$h2.nbsp("  ");

	if(isset($_REQUEST["select"])&&($_REQUEST["select"]==""))
	{
		unset($_REQUEST["select"]);
	}
	if(isset($_REQUEST["select"]))
	{
  		$h2=$h2."<input class=\"biginput\" type=\"text\" name=\"select\" value=\"".$_REQUEST["select"]."\">";
	}
	else
	{
 		$h2=$h2."<input class=\"biginput\" type=\"text\" name=\"select\" value=\"\">";
	}
	$h2=$h2.nbsp(" ");
  	$h2=$h2."<input class=\"button\" type=\"submit\" name=\"do\" value=\"select\">";
//	show_table_header_end();
	show_header2($h1, $h2, "<form name=\"form2\" method=\"get\" action=\"tr_status.php\">", "</form>");
?>

<?php
	if(!isset($_REQUEST["fullscreen"]))
	{
/*		show_table_header_begin();
		echo S_STATUS_OF_TRIGGERS_BIG;
	        show_table_v_delimiter();

	        $result=DBselect("select hostid,host from hosts where status in (0,2) order by host");
	        if(isset($_REQUEST["hostid"]))
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
	                if(isset($_REQUEST["hostid"]) && ($row["hostid"] == $_REQUEST["hostid"]))
	                {
	                        echo "<b>[<A HREF=\"tr_status.php?hostid=".$row["hostid"]."&onlytrue=$onlytrue&noactions=$noactions&compact=$compact&sort=$sort\">".$row["host"]."</A>]</b>  ";
	                }
	                else
	                {
	                        echo "<A HREF=\"tr_status.php?hostid=".$row["hostid"]."&onlytrue=$onlytrue&noactions=$noactions&compact=$compact&sort=$sort\">".$row["host"]."</A>  ";
	                }
	        }*/
//		show_table_v_delimiter(2);
//		show_table_header_begin();
?>

<?php
		$h1="";
		if(isset($_REQUEST["hostid"]))
		{
			$cond="&hostid=".$_REQUEST["hostid"];
		}
		else
		{
			$cond="";
		}

		if($onlytrue!='true')
		{
			$h1=$h1."[<A HREF=\"tr_status.php?onlytrue=true&noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select&sort=$sort$cond\">".S_SHOW_ONLY_TRUE."</a>] ";
		}
		else
		{
			$h1=$h1."[<A HREF=\"tr_status.php?onlytrue=false&noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select&sort=$sort$cond\">".S_SHOW_ALL_TRIGGERS."</A>] ";
		}
		if($noactions!='true')
		{
			$h1=$h1."[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=true&compact=$compact&select=$select&txt_select=$txt_select&sort=$sort$cond\">".S_HIDE_ACTIONS."</A>] ";
		}
		else
		{
			$h1=$h1."[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=false&compact=$compact&select=$select&txt_select=$txt_select&sort=$sort$cond\">".S_SHOW_ACTIONS."</A>] ";
		}
		if($compact!='true')
		{
			$h1=$h1."[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=true&select=$select&txt_select=$txt_select&sort=$sort$cond\">".S_HIDE_DETAILS."</A>] ";
		}
		else
		{
			$h1=$h1."[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=false&select=$select&txt_select=$txt_select&sort=$sort$cond\">".S_SHOW_DETAILS."</A>] ";
		}
		
		if($select!='true')
		{
			$h1=$h1."[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=true&txt_select=$txt_select&sort=$sort$cond\">".S_SELECT."</A>] ";
		}
		else
		{
			$h1=$h1."[<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=false&sort=$sort$cond\">".S_HIDE_SELECT."</A>] "; 
			$h1=$h1."<form name=\"form1\" method=\"get\" action=\"tr_status.php?select=true\">
  			<input class=\"biginput\" type=\"text\" name=\"txt_select\" value=\"$txt_select\">
  			<input class=\"button\" type=\"submit\" name=\"btnSelect\" value=\"Select\">
  			<input class=\"button\" type=\"submit\" name=\"btnSelect\" value=\"Inverse select\">
			<INPUT NAME=\"compact\" TYPE=\"HIDDEN\" value=\"$compact\">
			<INPUT NAME=\"onlytrue\" TYPE=\"HIDDEN\" value=\"$onlytrue\">
			<INPUT NAME=\"noactions\" TYPE=\"HIDDEN\" value=\"$noactions\">			
		        <INPUT NAME=\"select\" TYPE=\"HIDDEN\" value=\"$select\">
			</form>";
		}
		show_table_header($h1);
//		echo "<br>";
	}

 	$time=date("[H:i:s]",time());
  	if(isset($_REQUEST["fullscreen"]))
	{
		show_table_header("<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&sort=$sort\">".S_TRIGGERS_BIG." $time</A>");

		$cond="";
		if(isset($_REQUEST["hostid"]))
		{
			$cond=" and h.hostid=".$_REQUEST["hostid"]." ";
		}

		if($onlytrue=='true')
		{
			$sql="select t.priority,count(*) as cnt from triggers t,hosts h,items i,functions f".$groupname."  where t.value=1 and t.status=0 and f.itemid=i.itemid and h.hostid=i.hostid and h.status=".HOST_STATUS_MONITORED." and t.triggerid=f.triggerid and t.description $select_cond and i.status=0 $cond $groupcond group by 1";
		}
		else
		{
			$sql="select t.priority,count(*) as cnt from triggers t,hosts h,items i,functions f".$groupname." where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and t.status=0 and h.status=".HOST_STATUS_MONITORED." and t.description $select_cond and i.status=0 $cond $groupcond group by 1";
		}
		$result=DBselect($sql);
		$p0=$p1=$p2=$p3=$p4=$p5=0;
		while($row=DBfetch($result))
		{
			$priority=$row["priority"];
			$count=$row["cnt"];
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
//		show_table_header("<A HREF=\"tr_status.php?onlytrue=$onlytrue&noactions=$noactions&compact=$compact&fullscreen=1&sort=$sort\">".S_TRIGGERS_BIG." $time</A>");
	}

	$table  = new CTableInfo();
	$header=array();
  
	echo "<TR ALIGN=CENTER BGCOLOR=\"#CCCCCC\">";
	if(isset($_REQUEST["fullscreen"]))
	{
		$fullscreen="&fullscreen=1";
	}
	else
	{
		$fullscreen="";
	}
	if(isset($sort) && $sort=="description")
	{
		$description=S_NAME_BIG;
	}
	else
	{
		if($select=="TRUE")
			$description="<A HREF=\"tr_status.php?sort=description&onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select$fullscreen$cond\">".S_NAME;
		else
			$description="<A HREF=\"tr_status.php?sort=description&onlytrue=$onlytrue&noactions=$noactions&compact=$compact$fullscreen$cond\">".S_NAME."</a>";
	}
	if($compact!='true') {$description=$description."<BR><FONT SIZE=-1>".S_EXPRESSION."</FONT></B>";}
	$header=array_merge($header,array($description));
	$header=array_merge($header,array(S_STATUS));

	if(!isset($sort)||(isset($sort) && $sort=="priority"))
	{
		$header=array_merge($header,array(S_SEVERITY_BIG));
	}
	else
	{
		if($select=="TRUE")
			$header=array_merge($header,array("<A HREF=\"tr_status.php?sort=priority&onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select$fullscreen$cond\">".S_SEVERITY."</a>"));
		else
			$header=array_merge($header,array("<A HREF=\"tr_status.php?sort=priority&onlytrue=$onlytrue&noactions=$noactions&compact=$compact$fullscreen$cond\">".S_SEVERITY."</a>"));
	}

	if(isset($sort) && $sort=="lastchange")
	{
		$header=array_merge($header,array(nbsp(S_LAST_CHANGE_BIG)));
	}
	else
	{
		if($select=="TRUE")
		{
			$header=array_merge($header,array("<A HREF=\"tr_status.php?sort=lastchange&onlytrue=$onlytrue&noactions=$noactions&compact=$compact&select=$select&txt_select=$txt_select$fullscreen$cond\">".nbsp(S_LAST_CHANGE)."</a>"));
		}
		else
		{
			$header=array_merge($header,array("<A HREF=\"tr_status.php?sort=lastchange&onlytrue=$onlytrue&noactions=$noactions&compact=$compact$fullscreen$cond\">".nbsp(S_LAST_CHANGE)."</a>"));
		}
	}
	echo "</TD>";
   
	if($noactions!='true')
	{
		$header=array_merge($header,array(S_ACTIONS));
	}
	array_push($header,S_COMMENTS);
	$table->setHeader($header);
	unset($header);

	if(isset($_REQUEST["hostid"]))
	{
		$cond=" and h.hostid=".$_REQUEST["hostid"]." ";
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
		$result=DBselect("select distinct t.triggerid,t.status,t.description,t.expression,t.priority,t.lastchange,t.comments,t.url,t.value from triggers t,hosts h,items i,functions f".$groupname." where t.value=1 and t.status=0 and f.itemid=i.itemid and h.hostid=i.hostid and t.description $select_cond and t.triggerid=f.triggerid and i.status in (0,2) and h.status=".HOST_STATUS_MONITORED." $cond $groupcond $sort");
	}
	else
	{
		$result=DBselect("select distinct t.triggerid,t.status,t.description,t.expression,t.priority,t.lastchange,t.comments,t.url,t.value from triggers t,hosts h,items i,functions f".$groupname." where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and t.status=0 and t.description $select_cond and i.status in (0,2) and h.status=".HOST_STATUS_MONITORED." $cond $groupcond $sort");
	}

	$col=0;
	while($row=DBfetch($result))
	{
		if(!check_right_on_trigger("R",$row["triggerid"]))
		{
			continue;
		}

// Check for dependencies

		$sql="select count(*) as cnt from trigger_depends d, triggers t where d.triggerid_down=".$row["triggerid"]." and d.triggerid_up=t.triggerid and t.value=1";
		$result2=DBselect($sql);
		$row2=DBfetch($result2);

		if($row2["cnt"]>0)
		{
			continue;
		}

		$elements=array();


		$description=expand_trigger_description($row["triggerid"]);

		if($row["url"] != "")
		{
			$description="<a href='".$row["url"]."'>$description</a>";
		}

		if($compact!='true')
		{
			$description=$description."<BR><FONT COLOR=\"#000000\" SIZE=-2>".explode_exp($row["expression"],1)."</FONT>";
		}

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
				$value=new CSpan("$blink1".S_FALSE_BIG."$blink2","off");
		else if($row["value"]==2)
				$value=new CSpan("$blink1".S_UNKNOWN_BIG."$blink2","unknown");
		else
				$value=new CSpan(S_TRUE_BIG,"on");

		$priority_style=NULL;
		if($row["priority"]==0)		$priority=S_NOT_CLASSIFIED;
		elseif($row["priority"]==1)	$priority=S_INFORMATION;
		elseif($row["priority"]==2)	$priority=S_WARNING;
		elseif($row["priority"]==3)
		{
			$priority=S_AVERAGE;
			$priority_style="average";
		}
		elseif($row["priority"]==4)
		{
			$priority=S_HIGH;
			$priority_style="high";
		}
		elseif($row["priority"]==5)
		{
			$priority=S_DISASTER;
			$priority_style="disaster";
		}
		else				$priority=$row["priority"];

		$lastchange=new CLink(date(S_DATE_FORMAT_YMDHMS,$row["lastchange"]),"alarms.php?triggerid=".$row["triggerid"],"action");

		$actions=NULL;
		if($noactions!='true')
		{
//			$actions="<A HREF=\"actions.php?triggerid=".$row["triggerid"]."\">".S_SHOW_ACTIONS."</A> - ";
			$actions=array(new CLink(S_HISTORY,"alarms.php?triggerid=".$row["triggerid"],"action"));
			array_push($actions, " - ");
			if(isset($_REQUEST["hostid"]))
			{
				array_push($actions, new CLink(S_CHANGE,"triggers.php?hostid=".$_REQUEST["hostid"]."&triggerid=".$row["triggerid"]."#form","action"));
			}
			else
			{
				array_push($actions, new CLink(S_CHANGE,"triggers.php?triggerid=".$row["triggerid"]."#form","action"));
			}
		}
		$comments=NULL;
		if($row["comments"] != "")
		{
			$comments=new CLink(S_SHOW,"tr_comments.php?triggerid=".$row["triggerid"],"action");
		}
		else
		{
			$comments=new CLink(S_ADD,"tr_comments.php?triggerid=".$row["triggerid"],"action");
		}

		$table->AddRow(array(
				$description,
				$value,
				new CSpan($priority,$priority_style),
				$lastchange,
				$actions,
				$comments
				));
		$col++;
	}
	$table->show();

	show_table_header(S_TOTAL.":$col");
?>

<?php
	show_page_footer();
?>
