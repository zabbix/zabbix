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
	$page["title"] = "S_TRIGGERS_TOP_100";
	$page["file"] = "report5.php";
	show_header($page["title"],0,0);
?>

<?php
	if(!check_right("Host","R",0))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_page_footer();
		exit;
	}
?>

<?php
	if(!isset($_REQUEST["period"]))
	{
		$_REQUEST["period"]="day";
	}

	$h1=S_TRIGGERS_TOP_100_BIG;

	$year=date("Y");

	$h2=SPACE.S_LAST.SPACE;
	$h2=$h2."<select class=\"biginput\" name=\"period\" onChange=\"submit()\">";
	$h2=$h2.form_select("period","day",S_DAY);
	$h2=$h2.form_select("period","week",S_WEEK);
	$h2=$h2.form_select("period","month",S_MONTH);
	$h2=$h2.form_select("period","year",S_YEAR);
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"report5.php\">", "</form>");
?>

<?php
	$table = new CTableInfo();
	$table->setHeader(array(S_HOST,S_TRIGGER,S_SEVERITY,S_NUMBER_OF_STATUS_CHANGES));
	$time_now=time();
	if($_REQUEST["period"]=="day")
	{
		$time_dif=24*3600;
	}
	elseif($_REQUEST["period"]=="week")
	{
		$time_dif=7*24*3600;
	}
	elseif($_REQUEST["period"]=="month")
	{
		$time_dif=30*24*3600;
	}
	elseif($_REQUEST["period"]=="year")
	{
		$time_dif=365*24*3600;
	}
//	$result=DBselect("select h.host, t.triggerid, t.description, t.priority, count(a.alarmid) as alarmcount
	$result=DBselect("select h.host, t.triggerid, t.description, t.priority, count(distinct a.alarmid) as alarmcount
	from hosts h, triggers t, functions f, items i, alarms a where 
	h.hostid = i.hostid and
	i.itemid = f.itemid and
	t.triggerid=f.triggerid and
	t.triggerid=a.triggerid and
	a.clock>$time_now-$time_dif
	group by h.host,t.triggerid,t.description,t.priority
	order by 5 desc,1,3", 100);

        while($row=DBfetch($result))
        {
                $priority_style=NULL;
                if($row["priority"]==0)       $priority=S_NOT_CLASSIFIED;
                elseif($row["priority"]==1)     
		{
			$priority=S_INFORMATION;
                        $priority_style="information";
		}
                elseif($row["priority"]==2)
		{
		     $priority=S_WARNING;
                        $priority_style="warning";
		}
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
                else                            $priority=$row["priority"];
		$severity=new CSpan($priority,$priority_style);
            	$table->addRow(array(
			$row["host"],
			new CLink(expand_trigger_description($row["triggerid"]),
				"alarms.php?limit=100&triggerid=".$row["triggerid"]),
			new CCol($priority,$priority_style),
//			$row["count"],
			$row["alarmcount"],
			));
	}
	$table->show();

	show_page_footer();
?>
