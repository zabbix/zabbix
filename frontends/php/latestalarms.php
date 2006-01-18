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
	$page["title"] = "S_LATEST_EVENTS";
	$page["file"] = "latestalarms.php";
	show_header($page["title"],1,0);
?>

<?php
	if(isset($_REQUEST["start"])&&isset($_REQUEST["do"])&&($_REQUEST["do"]=="<< Prev 100"))
	{
		$_REQUEST["start"]-=100;
	}
	if(isset($_REQUEST["do"])&&($_REQUEST["do"]=="Next 100 >>"))
	{
		if(isset($_REQUEST["start"]))
		{
			$_REQUEST["start"]+=100;
		}
		else
		{
			$_REQUEST["start"]=100;
		}
	}
	if(isset($_REQUEST["start"])&&($_REQUEST["start"]<=0))
	{
		unset($_REQUEST["start"]);
	}
	if(isset($_REQUEST["groupid"])&&($_REQUEST["groupid"]==0))
	{
		unset($_REQUEST{"groupid"});
	}
?>

<?php
	$_REQUEST["hostid"]=@iif(isset($_REQUEST["hostid"]),$_REQUEST["hostid"],get_profile("web.latest.hostid",0));
	update_profile("web.latest.hostid",$_REQUEST["hostid"]);
	update_profile("web.menu.view.last",$page["file"]);
?>

<?php
	$h1="&nbsp;".S_HISTORY_OF_EVENTS_BIG;

	$h2=S_GROUP."&nbsp;";
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

	$h2=$h2."&nbsp;".S_HOST."&nbsp;";
	$h2=$h2."<select class=\"biginput\" name=\"hostid\" onChange=\"submit()\">";
	$h2=$h2.form_select("hostid",0,S_SELECT_HOST_DOT_DOT_DOT);

	if(isset($_REQUEST["groupid"]))
	{
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host";
	}
	else
	{
		$sql="select h.hostid,h.host from hosts h,items i where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid group by h.hostid,h.host order by h.host";
	}

	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		$h2=$h2.form_select("hostid",$row["hostid"],$row["host"]);
	}
	$h2=$h2."</select>&nbsp;";

	if(isset($_REQUEST["start"]))
	{
		$h2=$h2."<input class=\"biginput\" name=\"start\" type=hidden value=".$_REQUEST["start"]." size=8>";
  		$h2=$h2."<input class=\"button\" type=\"submit\" name=\"do\" value=\"<< Prev 100\">";
	}
	else
	{
  		$h2=$h2."<input class=\"button\" type=\"submit\" disabled name=\"do\" value=\"<< Prev 100\">";
	}
  	$h2=$h2."<input class=\"button\" type=\"submit\" name=\"do\" value=\"Next 100 >>\">";

	show_header2($h1,$h2,"<form name=\"form2\" method=\"get\" action=\"latestalarms.php\">","</form>");
?>

<?php
	if(!isset($_REQUEST["start"]))
	{
		$_REQUEST["start"]=0;
	}
	if(isset($_REQUEST["hostid"])&&($_REQUEST["hostid"] == 0))
	{
		unset($_REQUEST["hostid"]);
	}
	$sql="select max(alarmid) as max from alarms";
	$result=DBselect($sql);
	$row=DBfetch($result);
	$maxalarmid=@iif(DBnum_rows($result)>0,$row["max"],0);

//	$sql="select t.description,a.clock,a.value,t.triggerid,t.priority from alarms a,triggers t where t.triggerid=a.triggerid and a.alarmid>$maxalarmid-".($_REQUEST["start"]+200)." order by clock desc limit ".($_REQUEST["start"]+200);
	if(isset($_REQUEST["hostid"]))
	{
		$sql="select t.description,a.clock,a.value,t.triggerid,t.priority from alarms a,triggers t,hosts h,items i,functions f where t.triggerid=a.triggerid and f.triggerid=t.triggerid and f.itemid=i.itemid and i.hostid=h.hostid and h.hostid=".$_REQUEST["hostid"]." and a.alarmid>$maxalarmid-".($_REQUEST["start"]+200)." order by clock desc limit ".($_REQUEST["start"]+200);
	}
	else
	{
		$sql="select t.description,a.clock,a.value,t.triggerid,t.priority from alarms a,triggers t,hosts h,items i,functions f where t.triggerid=a.triggerid and f.triggerid=t.triggerid and f.itemid=i.itemid and i.hostid=h.hostid and a.alarmid>$maxalarmid-".($_REQUEST["start"]+200)." order by clock desc limit ".($_REQUEST["start"]+200);
	}

	$result=DBselect($sql);

	$table = new CTableInfo();
	$table->setHeader(array(S_TIME, S_DESCRIPTION, S_VALUE, S_SEVERITY));
	$col=0;
	$i=0;
	while($row=DBfetch($result))
	{
		$i++;
		if(isset($_REQUEST["start"])&&($i<$_REQUEST["start"]))
		{
			continue;
		}
		if(!check_right_on_trigger("R",$row["triggerid"]))
		{
			continue;
		}
		if($col>100)	break;

		$description=expand_trigger_description($row["triggerid"]);
		$description="<a href=\"alarms.php?triggerid=".$row["triggerid"]."\">$description</a>";

		if($row["value"] == 0)
		{
			$value=new CCol(S_OFF,"off");
		}
		elseif($row["value"] == 1)
		{
			$value=new CCol(S_ON,"on");
		}
		else
		{
			$value=new CCol(S_UNKNOWN_BIG,"unknown");
		}
		if($row["priority"]==0)		$priority=S_NOT_CLASSIFIED;
		elseif($row["priority"]==1)	$priority=S_INFORMATION;
		elseif($row["priority"]==2)	$priority=S_WARNING;
		elseif($row["priority"]==3)	$priority=array("value"=>S_AVERAGE,"class"=>"average");
		elseif($row["priority"]==4)	$priority=array("value"=>S_HIGH,"class"=>"high");
		elseif($row["priority"]==5)	$priority=array("value"=>S_DISASTER,"class"=>"disaster");
		else				$priority=$row["priority"];

		$table->addRow(array(
			date("Y.M.d H:i:s",$row["clock"]),
			$description,
			$value,
			$priority));
	}
	$table->show();
?>

<?php
	show_page_footer();
?>
