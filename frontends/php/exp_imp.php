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
	require_once "include/forms.inc.php";

        $page["title"] = "S_EXPORT_IMPORT";
        $page["file"] = "exp_imp.php";

include_once "include/page_header.php";

	insert_confirm_javascript();
?>
<?php
	$fields=array(
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
		"config"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1,2"),	null), /* 0 - export, 1 - import, 2 - import hostlist */

		"groupid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"hosts"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"items"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"triggers"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"graphs"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		
		"update"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null)
		/*,
		"screens"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null) */
/* actions */
	);

	check_fields($fields);
	
	$config = get_request('config', 0);
		
	validate_group(PERM_READ_ONLY);
?>
<?php
	switch($config)
	{
		case 2:
			$title = S_IMPORT_HOSTS_BIG;
			$frm_title = S_IMPORT_HOSTS;
			break;
		case 1:
			$title = S_IMPORT_BIG;
			$frm_title = S_IMPORT;
			break;
		case 0:
		default:
			$title = S_EXPORT_BIG;
			$frm_title = S_EXPORT;
	}

	$form = new CForm();
	$cmbConfig = new CComboBox('config', $config, 'submit()');
	$cmbConfig->AddItem(0, S_EXPORT);
	$cmbConfig->AddItem(1, S_IMPORT);
	$cmbConfig->AddItem(2, S_IMPORT_HOSTS);
	$form->AddItem($cmbConfig);

	show_table_header($title, $form);
	echo BR;

	if($config == 1 || $config == 2)
	{
		$form = new CFormTable($frm_title,null,'post');
		$form->AddVar('config', $config);
		$form->AddRow(S_IMPORT_FILE, new CFile('import'));
		$form->AddItemToBottomRow(new CButton('import', S_IMPORT));
		$form->Show();
	}
	else
	{
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,null,null,$ZBX_CURNODEID);
		
		$cmbGroups = new CComboBox("groupid",get_request("groupid",0),"submit()");
		$cmbGroups->AddItem(0,S_ALL_SMALL);
		$result=DBselect("select distinct g.groupid,g.name from groups g,hosts_groups hg,hosts h".
				" where h.hostid in (".$available_hosts.") ".
				" and g.groupid=hg.groupid and h.hostid=hg.hostid".
				" order by g.name");
		while($row=DBfetch($result))
		{
			$cmbGroups->AddItem($row["groupid"],$row["name"]);
			if($row["groupid"] == $_REQUEST["groupid"]) $correct_host = 1;
		}
		if(!isset($correct_host))
		{
			unset($_REQUEST["groupid"]);
			$cmbGroups->SetValue(0);
		}
		
		$header =& get_table_header(S_HOSTS_BIG, array(S_GROUP.SPACE, $cmbGroups));

	/* table HOSTS */
		$update = get_request('update', null);
		 
		$form = new CForm(null,'post');
		$form->SetName('hosts');
		$form->AddVar("config",$config);
		$form->AddVar('update', true);
		$form->AddItem($header);

		$table = new CTableInfo(S_NO_HOSTS_DEFINED);
		$table->SetHeader(array(
			S_NAME,
			S_IP,
			S_PORT,
			S_STATUS,
			S_ITEMS,
			S_TRIGGERS,
			S_GRAPHS
			/*,
			S_SCREENS */
			));
	
		$sql = "select h.* from";
		if(isset($_REQUEST["groupid"]))
		{
			$sql .= " hosts h,hosts_groups hg where";
			$sql .= " hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid and";
		} else  $sql .= " hosts h where";
		$sql .=	" h.hostid in (".$available_hosts.") ".
			" order by h.host";

		$result=DBselect($sql);
	
		while($row=DBfetch($result))
		{
			$host=new CCol(array(
				new CCheckBox('hosts['.$row['hostid'].']',
					isset($_REQUEST['hosts'][$row['hostid']]) || !isset($update),
					NULL,true),
				SPACE,
				$row["host"]
				));
			
			if($row["status"] == HOST_STATUS_MONITORED){
				$status=new CSpan(S_MONITORED, "off");
			} else if($row["status"] == HOST_STATUS_NOT_MONITORED) {
				$status=new CLink(S_NOT_MONITORED, "on");
			} else if($row["status"] == HOST_STATUS_TEMPLATE)
				$status=new CCol(S_TEMPLATE,"unknown");
			else if($row["status"] == HOST_STATUS_DELETED)
				$status=new CCol(S_DELETED,"unknown");
			else
				$status=S_UNKNOWN;
			
			$items = DBfetch(DBselect('select count(itemid) as cnt from items where hostid='.$row['hostid']));
			$triggers['cnt'] = 0;
			$db_triggers = DBselect('select f.triggerid, i.hostid, count(distinct i.hostid) as cnt from functions f, items i '.
				' where f.itemid=i.itemid group by f.triggerid');
			while($db_tr = DBfetch($db_triggers)) if($db_tr['cnt'] == 1 && $db_tr['hostid'] == $row['hostid']) $triggers['cnt']++;
			
			$graphs['cnt'] = 0;
			$db_graphs = DBselect('select gi.graphid, i.hostid, count(distinct i.hostid) as cnt from graphs_items gi, items i '.
				' where gi.itemid=i.itemid group by gi.graphid');
			while($db_tr = DBfetch($db_graphs)) if($db_tr['cnt'] == 1 && $db_tr['hostid'] == $row['hostid']) $graphs['cnt']++;
			
			/* $screens['cnt'] = 3; */

			$table->AddRow(array(
				$host,
				$row["useip"]==1 ? $row["ip"] : "-",
				$row["port"],
				$status,
				array(new CCheckBox('items['.$row['hostid'].']',
						isset($_REQUEST['items'][$row['hostid']]) || !isset($update),
						NULL,true),
					$items['cnt']),
				array(new CCheckBox('triggers['.$row['hostid'].']',
						isset($_REQUEST['triggers'][$row['hostid']]) || !isset($update),
						NULL,true),
					$triggers['cnt']),
				array(new CCheckBox('graphs['.$row['hostid'].']',
						isset($_REQUEST['graphs'][$row['hostid']]) || !isset($update),
						NULL,true),
					$graphs['cnt'])
				/*,
				array(new CCheckBox('screens['.$row['hostid'].']',
						isset($_REQUEST['screens'][$row['hostid']]) || !isset($update),
						NULL,true),
					$screens['cnt'])*/
				));
			$table->SetFooter(new CCol(new CButton('export', S_EXPORT)));
		}

		$form->AddItem($table);
		
		$form->Show();
	}
	
?>
<?php

include_once "include/page_footer.php"

?>
