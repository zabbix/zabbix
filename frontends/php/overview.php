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
	$page["title"] = S_OVERVIEW;
	$page["file"] = "overview.php";
	show_header($page["title"],0,0);
?>

<?php
	define("SHOW_TRIGGERS",0);
	define("SHOW_DATA",1);
?>


<?php
        if(!check_anyright("Host","R"))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_footer();
                exit;
        }
	if(isset($_GET["select"])&&($_GET["select"]!=""))
	{
		unset($_GET["groupid"]);
		unset($_GET["hostid"]);
	}
	
        if(isset($_GET["hostid"])&&!check_right("Host","R",$_GET["hostid"]))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_footer();
                exit;
        }
?>

<?php
	if(isset($_GET["groupid"])&&($_GET["groupid"]==0))
	{
		unset($_GET["groupid"]);
	}
?>

<?php
	$h1="&nbsp;".S_OVERVIEW_BIG;

	$h2=S_GROUP."&nbsp;";
	$h2=$h2."<select class=\"biginput\" name=\"groupid\" onChange=\"submit()\">";
	$h2=$h2.form_select("groupid",0,S_SELECT_GROUP_DOT_DOT_DOT);
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

	$h2=$h2."&nbsp;".S_TYPE."&nbsp;";
	$h2=$h2."<select class=\"biginput\" name=\"type\" onChange=\"submit()\">";
	$h2=$h2.form_select("type",0,S_TRIGGERS);
	$h2=$h2.form_select("type",1,S_DATA);
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"form2\" method=\"get\" action=\"overview.php\">", "</form>");
?>

<?php
	if(!isset($_GET["sort"]))
	{
		$_GET["sort"]="description";
	}

	if(isset($_GET["groupid"])&&isset($_GET["type"])&&($_GET["type"]==SHOW_DATA))
	{
		table_begin();
		$header=array("&nbsp;");
		$hosts=array();
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$_GET["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$header=array_merge($header,array($row["host"]));
			$hosts=array_merge($hosts,array($row["hostid"]));
		}
		table_header($header);

		$col=0;
		if(isset($_GET["sort"]))
		{
			switch ($_GET["sort"])
			{
				case "description":
					$_GET["sort"]="order by i.description";
					break;
				case "lastcheck":
					$_GET["sort"]="order by i.lastclock";
					break;
				default:
					$_GET["sort"]="order by i.description";
					break;
			}
		}
		else
		{
			$_GET["sort"]="order by i.description";
		}
//		$sql="select distinct description from items order by 1;";
		$sql="select distinct i.description from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$_GET["groupid"]." and hg.hostid=h.hostid order by 1";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$rows=array(nbsp($row["description"]));
			foreach($hosts as $hostid)
			{
				$sql="select itemid,value_type,lastvalue,units from items where hostid=$hostid and description='".$row["description"]."'";
				$result2=DBselect($sql);
				if(DBnum_rows($result2)==1)
				{
					$row2=DBfetch($result2);
					if(!isset($row2["lastvalue"]))	$value="-";
					else
					{
						$sql="select t.triggerid from triggers t, items i, functions f where i.hostid=$hostid and i.itemid=".$row2["itemid"]." and i.itemid=f.itemid and t.priority>1 and t.triggerid=f.triggerid and t.value=".TRIGGER_VALUE_TRUE;
						$result3=DBselect($sql);
						if(DBnum_rows($result3)>0)
						{
							if($row2["value_type"] == 0)
								$value=array("value"=>nbsp(convert_units($row2["lastvalue"],$row2["units"])),"class"=>"high");
							else
								$value=array("value"=>nbsp(htmlspecialchars(substr($row2["lastvalue"],0,20)." ...")),"class"=>"high");
						}
						else
						{
							if($row2["value_type"] == 0)
								$value=nbsp(convert_units($row2["lastvalue"],$row2["units"]));
							else
								$value=nbsp(htmlspecialchars(substr($row2["lastvalue"],0,20)." ..."));
						}
					}
				}
				else
				{
					$value="-";
				}
				$rows=array_merge($rows,array($value));
			}

			table_row($rows, $col++);
		}
		table_end();
	}
	else if(isset($_GET["groupid"])&&isset($_GET["type"])&&($_GET["type"]==SHOW_TRIGGERS))
	{
		table_begin();
		$header=array("&nbsp;");
		$hosts=array();
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg,functions f,triggers t where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$_GET["groupid"]." and hg.hostid=h.hostid and t.triggerid=f.triggerid and f.itemid=i.itemid group by h.hostid,h.host order by h.host";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$header=array_merge($header,array($row["host"]));
			$hosts=array_merge($hosts,array($row["hostid"]));
		}
		table_header($header);

		$col=0;
		if(isset($_GET["sort"]))
		{
			switch ($_GET["sort"])
			{
				case "description":
					$_GET["sort"]="order by i.description";
					break;
				case "lastcheck":
					$_GET["sort"]="order by i.lastclock";
					break;
				default:
					$_GET["sort"]="order by i.description";
					break;
			}
		}
		else
		{
			$_GET["sort"]="order by i.description";
		}
//		$sql="select distinct description from items order by 1;";
		$sql="select distinct t.description from hosts h,items i,hosts_groups hg,triggers t,functions f where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$_GET["groupid"]." and hg.hostid=h.hostid and t.triggerid=f.triggerid and f.itemid=i.itemid order by 1";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$rows=array(nbsp($row["description"]));
			foreach($hosts as $hostid)
			{
				$sql="select t.status,t.value,t.lastchange from triggers t,functions f,items i where f.triggerid=t.triggerid and i.itemid=f.itemid and i.hostid=$hostid and t.description='".addslashes($row["description"])."'";
				$result2=DBselect($sql);
				if(DBnum_rows($result2)==1)
				{
					$row2=DBfetch($result2);
					if($row2["status"]==0)
					{
						if($row2["value"] == TRIGGER_VALUE_FALSE)
							$value=array("value"=>"&nbsp;","class"=>"normal");
						else if($row2["value"] == TRIGGER_VALUE_UNKNOWN)
							$value=array("value"=>"&nbsp;","class"=>"unknown_trigger");
						else
							$value=array("value"=>"&nbsp;","class"=>"high");
					}
					else
					{
						$value="&nbsp;";
					}
				}
				else
				{
					$value="&nbsp;";
				}
				$rows=array_merge($rows,array($value));
			}

			table_row($rows, $col++);
		}
		table_end();
	}
	else
	{
		table_nodata();
	}
?>

<?php
	show_footer();
?>
