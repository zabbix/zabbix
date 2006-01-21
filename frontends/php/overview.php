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
	$page["title"] = "S_OVERVIEW";
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
                show_page_footer();
                exit;
        }
	if(isset($_REQUEST["select"])&&($_REQUEST["select"]!=""))
	{
		unset($_REQUEST["groupid"]);
		unset($_REQUEST["hostid"]);
	}
	
        if(isset($_REQUEST["hostid"])&&!check_right("Host","R",$_REQUEST["hostid"]))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_page_footer();
                exit;
        }
?>

<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535),	NULL),
		"type"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),		NULL)
	);

	check_fields($fields);
?>

<?php
	if(isset($_REQUEST["groupid"])&&($_REQUEST["groupid"]==0))
	{
		unset($_REQUEST["groupid"]);
	}
	update_profile("web.menu.view.last",$page["file"]);
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
	if(!isset($_REQUEST["sort"]))
	{
		$_REQUEST["sort"]="description";
	}

	if(isset($_REQUEST["groupid"])&&isset($_REQUEST["type"])&&($_REQUEST["type"]==SHOW_DATA))
	{
		$table = new CTableInfo();
		$header=array("&nbsp;");
		$hosts=array();
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$header=array_merge($header,array($row["host"]));
			$hosts=array_merge($hosts,array($row["hostid"]));
		}
		$table->setHeader($header);

		if(isset($_REQUEST["sort"]))
		{
			switch ($_REQUEST["sort"])
			{
				case "description":
					$_REQUEST["sort"]="order by i.description";
					break;
				case "lastcheck":
					$_REQUEST["sort"]="order by i.lastclock";
					break;
				default:
					$_REQUEST["sort"]="order by i.description";
					break;
			}
		}
		else
		{
			$_REQUEST["sort"]="order by i.description";
		}
//		$sql="select distinct description from items order by 1;";
		$sql="select distinct i.description from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid order by 1";
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
								$value=new CCol(nbsp(convert_units($row2["lastvalue"],$row2["units"])),"high");
							else
								$value=new CCol(nbsp(htmlspecialchars(substr($row2["lastvalue"],0,20)." ...")),"high");
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

			$table->addRow($rows);
		}
		$table->show();
	}
	else if(isset($_REQUEST["groupid"])&&isset($_REQUEST["type"])&&($_REQUEST["type"]==SHOW_TRIGGERS))
	{
		$table  = new CTableInfo();
		$header=array("&nbsp;");
		$hosts=array();
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg,functions f,triggers t where h.status=".HOST_STATUS_MONITORED." and t.status=".TRIGGER_STATUS_ENABLED." and h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid and t.triggerid=f.triggerid and f.itemid=i.itemid group by h.hostid,h.host order by h.host";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$header=array_merge($header,array($row["host"]));
			$hosts=array_merge($hosts,array($row["hostid"]));
		}
		$table->setHeader($header);

		$col=0;
		if(isset($_REQUEST["sort"]))
		{
			switch ($_REQUEST["sort"])
			{
				case "description":
					$_REQUEST["sort"]="order by i.description";
					break;
				case "lastcheck":
					$_REQUEST["sort"]="order by i.lastclock";
					break;
				default:
					$_REQUEST["sort"]="order by i.description";
					break;
			}
		}
		else
		{
			$_REQUEST["sort"]="order by i.description";
		}
//		$sql="select distinct description from items order by 1;";
		$sql="select distinct t.description from hosts h,items i,hosts_groups hg,triggers t,functions f where h.status=".HOST_STATUS_MONITORED." and t.status=".TRIGGER_STATUS_ENABLED." and h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid and t.triggerid=f.triggerid and f.itemid=i.itemid order by 1";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$rows=array(nbsp($row["description"]));
			foreach($hosts as $hostid)
			{
				$sql="select t.status,t.value,t.lastchange from triggers t,functions f,items i where f.triggerid=t.triggerid and i.itemid=f.itemid and t.status=".TRIGGER_STATUS_ENABLED." and i.hostid=$hostid and t.description='".addslashes($row["description"])."'";
				$result2=DBselect($sql);
				if(DBnum_rows($result2)==1)
				{
					$row2=DBfetch($result2);
					if($row2["status"]==0)
					{
						if($row2["value"] == TRIGGER_VALUE_FALSE)
							$value=new CCol("&nbsp;","normal");
						else if($row2["value"] == TRIGGER_VALUE_UNKNOWN)
							$value=new CCol("&nbsp;","unknown_trigger");
						else
							$value=new CCol("&nbsp;","high");
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

			$table->addRow($rows);
		}
		$table->show();
	}
	else
	{
		table_nodata();
	}
?>

<?php
	show_page_footer();
?>
