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
                show_page_footer();
                exit;
        }
	
        if(isset($_REQUEST["hostid"])&&!check_right("Host","R",$_REQUEST["hostid"]))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_page_footer();
                exit;
        }
?>

<?php
	if(isset($_REQUEST["groupid"])&&($_REQUEST["groupid"]==0))
	{
		unset($_REQUEST["groupid"]);
	}
?>

<?php
	$_REQUEST["hostid"]=@iif(isset($_REQUEST["hostid"]),$_REQUEST["hostid"],get_profile("web.latest.hostid",0));
	update_profile("web.latest.hostid",$_REQUEST["hostid"]);
	update_profile("web.menu.cm.last",$page["file"]);
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
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"form2\" method=\"get\" action=\"hostprofiles.php\">", "</form>");
?>

<?php
	if(isset($_REQUEST["hostid"])&&($_REQUEST["hostid"]!=0))
	{
		insert_host_profile_form($_REQUEST["hostid"],1);
	}
	else
	{
		$table = new CTableInfo();
		$header=array();
		$header=array_merge($header,array(S_HOST,S_NAME,S_OS,S_SERIALNO,S_TAG,S_MACADDRESS));

		$table->setHeader($header);

		if(isset($_REQUEST["groupid"])&&($_REQUEST["groupid"]!=0))
		{
			$sql="select h.hostid,h.host,p.name,p.os,p.serialno,p.tag,p.macaddress from hosts h,hosts_profiles p,hosts_groups hg where h.hostid=p.hostid and h.hostid=hg.hostid and hg.groupid=".$_REQUEST["groupid"]." order by h.host";
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

			$table->addRow(array(
				$row["host"],
				$row["name"],
				$row["os"],
				$row["serialno"],
				$row["tag"],
				$row["macaddress"]
				));
		}
		$table->show();
	}
?>

<?php
	show_page_footer();
?>
