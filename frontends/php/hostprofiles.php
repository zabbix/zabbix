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
	require_once "include/config.inc.php";
	require_once "include/hosts.inc.php";

	$page["title"] = "S_HOST_PROFILES";
	$page["file"] = "hostprofiles.php";
	show_header($page["title"],0,0);
?>

<?php
	validate_group_with_host(PERM_READ_ONLY, array("allow_all_hosts","monitored_hosts","with_items"));
?>
<?php
	$form = new CForm();

	$form->AddItem(S_GROUP.SPACE);
	$cmbGroup = new CComboBox("groupid",get_request("groupid",0),"submit()");
	$cmbGroup->AddItem(0,S_ALL_SMALL);

	$result=DBselect("select groupid,name from groups where mod(groupid,100)=$ZBX_CURNODEID order by name");
	while($row=DBfetch($result))
	{
// Check if at least one host with read permission exists for this group
		$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg".
			" where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and".
			" hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host".
			" order by h.host");
		while($row2=DBfetch($result2))
		{
//			if(!check_right("Host","R",$row2["hostid"]))	continue; /* TODO */
			$cmbGroup->AddItem($row["groupid"],$row["name"]);
			break;
		}
	}
	$form->AddItem($cmbGroup);

	$form->AddItem(SPACE.S_HOST.SPACE);

	$cmbHost = new CComboBox("hostid",get_request("hostid",0),"submit()");

	if($_REQUEST["groupid"] > 0)
	{
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg".
			" where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and".
			" hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid".
			" group by h.hostid,h.host order by h.host";
	}
	else
	{
		$cmbHost->AddItem(0,S_ALL_SMALL);
		$sql="select h.hostid,h.host from hosts h,items i where h.status=".HOST_STATUS_MONITORED.
			" and h.hostid=i.hostid".
			" and mod(h.hostid,100)=".$ZBX_CURNODEID.
			" group by h.hostid,h.host order by h.host";
	}

	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
//		if(!check_right("Host","R",$row["hostid"]))	continue; /* TODO */
		$cmbHost->AddItem($row["hostid"],$row["host"]);
	}
	$form->AddItem($cmbHost);
	
	show_header2(S_HOST_PROFILES_BIG, $form);
?>

<?php
	if($_REQUEST["hostid"] > 0)
	{
		echo BR;
		insert_host_profile_form();
	}
	else
	{
		$table = new CTableInfo();
		$table->setHeader(array(S_HOST,S_NAME,S_OS,S_SERIALNO,S_TAG,S_MACADDRESS));

		if($_REQUEST["groupid"] > 0)
		{
			$sql="select h.hostid,h.host,p.name,p.os,p.serialno,p.tag,p.macaddress".
				" from hosts h,hosts_profiles p,hosts_groups hg where h.hostid=p.hostid".
				" and h.hostid=hg.hostid and hg.groupid=".$_REQUEST["groupid"].
				" order by h.host";
		}
		else
		{
			$sql="select h.hostid,h.host,p.name,p.os,p.serialno,p.tag,p.macaddress".
				" from hosts h,hosts_profiles p where h.hostid=p.hostid".
				" and mod(h.hostid,100)=$ZBX_CURNODEID order by h.host";
		}

		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
//        		if(!check_right("Host","R",$row["hostid"])) /* TODO */
			{
				continue;
			}

			$table->AddRow(array(
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
