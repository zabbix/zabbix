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
	include "include/forms.inc.php";
	$page["title"] = "S_HOST_PROFILES";
	$page["file"] = "hostprofiles.php";
	show_header($page["title"],0,0);
?>

<?php
        if(!check_anyright("Host","R"))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_footer();
                exit;
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
	$_GET["hostid"]=@iif(isset($_GET["hostid"]),$_GET["hostid"],get_profile("web.latest.hostid",0));
	update_profile("web.latest.hostid",$_GET["hostid"]);
	update_profile("web.menu.view.last",$page["file"]);
?>

<?php
	$h1="&nbsp;".S_HOST_PROFILES_BIG;

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

	if(isset($_GET["groupid"]))
	{
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$_GET["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host";
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
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"form2\" method=\"get\" action=\"hostprofiles.php\">", "</form>");
?>

<?php
	if(isset($_GET["hostid"])&&($_GET["hostid"]!=0))
	{
		insert_host_profile_form($_GET["hostid"],1);
	}
	else
	{
		table_begin();
		$header=array();
		$header=array_merge($header,array(S_HOST,S_NAME,S_OS,S_SERIALNO,S_TAG,S_MACADDRESS));

		table_header($header);

		$col=0;
		if(isset($_GET["groupid"])&&($_GET["groupid"]!=0))
		{
			$sql="select h.hostid,h.host,p.name,p.os,p.serialno,p.tag,p.macaddress from hosts h,hosts_profiles p,hosts_groups hg where h.hostid=p.hostid and h.hostid=hg.hostid and hg.groupid=".$_GET["groupid"]." order by h.host";
		}
		else
		{
			$sql="select h.hostid,h.host,p.name,p.os,p.serialno,p.tag,p.macaddress from hosts h,hosts_profiles p where h.hostid=p.hostid order by h.host";
		}

		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
        		if(!check_right("Host","R",$row["hostid"]))
			{
				continue;
			}

			table_row(array(
				$row["host"],
				$row["name"],
				$row["os"],
				$row["serialno"],
				$row["tag"],
				$row["macaddress"]
				),
			$col++);
		}


		table_end();
		show_table_header_end();
	}
?>

<?php
	show_footer();
?>
