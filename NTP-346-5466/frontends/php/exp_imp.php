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

	require_once "include/config.inc.php";
	require_once "include/forms.inc.php";
?>
<?php
	if(isset($_REQUEST['export']) && isset($_REQUEST['hosts']))
	{
		$EXPORT_DATA = true;
        	$page["type"] = PAGE_TYPE_XML;
        	$page["file"] = "zabbix_export.xml";
	}
	else
	{
	        $page["title"] = "S_EXPORT_IMPORT";
        	$page["file"] = "exp_imp.php";
	}

include_once "include/page_header.php";

	$_REQUEST["config"] = get_request("config",get_profile("web.exp_imp.config",0));
	
?>
<?php
	$fields=array(
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
		"config"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	null), /* 0 - export, 1 - import */

		"groupid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"hosts"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"items"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"triggers"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"graphs"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		
		"update"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		"rules"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		/*,
		"screens"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null) */
/* actions */
		"preview"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"export"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"import"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL)
	);

	check_fields($fields);

	$preview = isset($_REQUEST['preview']) ? true : false;
	$config = get_request('config', 0);
	$update = get_request('update', null);
	
	update_profile("web.exp_imp.config", $config);
?>
<?php
	if($config == 1)
	{
		$rules = get_request('rules', array());
		foreach(array('host', 'item', 'trigger', 'graph') as $key)
		{
			if(!isset($rules[$key]['exist']))	$rules[$key]['exist']	= 0;
			if(!isset($rules[$key]['missed']))	$rules[$key]['missed']	= 0;
		}

	}
	else
	{
		validate_group(PERM_READ_ONLY);
	
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,null,PERM_RES_IDS_ARRAY,get_current_nodeid());

		$hosts		= get_request('hosts', array());
		$items		= get_request('items', array());
		$graphs		= get_request('graphs', array());
		$triggers	= get_request('triggers', array());
		
		function &zbx_array_val_inc($arr, $inc_size = 1)
		{
			foreach($arr as $id => $val)
			{
				$arr[$id] = $val + $inc_size;
			}
			return $arr;
		}
		
		$hosts		= zbx_array_val_inc(array_flip(array_intersect(array_keys($hosts),	$available_hosts)));
		$items		= zbx_array_val_inc(array_flip(array_intersect(array_keys($items),	array_keys($hosts))));
		$graphs		= zbx_array_val_inc(array_flip(array_intersect(array_keys($graphs),	array_keys($hosts))));
		$triggers	= zbx_array_val_inc(array_flip(array_intersect(array_keys($triggers),	array_keys($hosts))));

		if(count($hosts)==0) $hosts[-1] = 1;

		$available_hosts = implode(',', $available_hosts);
	}
		
	if(isset($EXPORT_DATA))
	{
		include_once "include/export.inc.php";
		
		$exporter = new CZabbixXMLExport();
		$exporter->Export($hosts,$items,$triggers,$graphs);

		unset($exporter);
	}
?>
<?php	
	switch($config)
	{
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
	$form->SetMethod('get');
	
	$cmbConfig = new CComboBox('config', $config, 'submit()');
	$cmbConfig->AddItem(0, S_EXPORT);
	$cmbConfig->AddItem(1, S_IMPORT);
	$form->AddItem($cmbConfig);

	show_table_header($title, $form);
	echo BR;

	if($config == 1)
	{
		if(isset($_FILES['import_file']))
		{
			include_once "include/import.inc.php";

			$importer = new CZabbixXMLImport();
			$importer->SetRules($rules['host'],$rules['item'],$rules['trigger'],$rules['graph']);
			$importer->Parse($_FILES['import_file']['tmp_name']);

			unset($importer);
		}
		show_messages();
		
		$form = new CFormTable($frm_title,null,"post","multipart/form-data");
		$form->AddVar('config', $config);
		$form->AddRow(S_IMPORT_FILE, new CFile('import_file'));

		$table = new CTable();
		$table->SetHeader(array(S_ELEMENT, S_EXISTING, S_MISSING),'bold');

		foreach(array(	'host'		=> S_HOST,
				'item'		=> S_ITEM,
				'trigger'	=> S_TRIGGER,
				'graph'		=> S_GRAPH)
			as $key => $title)
		{
			$cmbExist = new CComboBox('rules['.$key.'][exist]', $rules[$key]['exist']);
			$cmbExist->AddItem(0, S_UPDATE);
			$cmbExist->AddItem(1, S_SKIP);
			
			$cmbMissed = new CComboBox('rules['.$key.'][missed]', $rules[$key]['missed']);
			$cmbMissed->AddItem(0, S_ADD);
			$cmbMissed->AddItem(1, S_SKIP);

			$table->AddRow(array($title, $cmbExist, $cmbMissed));
		}

		$form->AddRow(S_RULES, $table);

		$form->AddItemToBottomRow(new CButton('import', S_IMPORT));
		$form->Show();
	}
	else
	{

		if($preview)
		{
			$table = new CTableInfo(S_NO_DATA_FOR_EXPORT);
			$table->SetHeader(array(S_HOST, S_ELEMENTS));
			$table->ShowStart();

			$db_hosts = DBselect('select * from hosts where hostid in ('.implode(',',array_keys($hosts)).')');
			while($host = DBfetch($db_hosts))
			{
				$el_table = new CTableInfo(S_ONLY_HOST_INFO);
				$sqls = array(
					S_ITEM		=> !isset($items[$host['hostid']]) ? null : 
								' select hostid, description as info, 1 as cnt from items'.
								' where hostid='.$host['hostid'],
					S_TRIGGER	=> !isset($triggers[$host['hostid']]) ? null :
								'select i.hostid, t.description as info, count(distinct i.hostid) as cnt'.
								' from functions f, items i, triggers t'.
								' where t.triggerid=f.triggerid and f.itemid=i.itemid'.
								' group by f.triggerid, i.hostid, t.description',
					S_GRAPH		=> !isset($graphs[$host['hostid']]) ? null :
								'select g.name as info, i.hostid, count(distinct i.hostid) as cnt'.
								' from graphs_items gi, items i, graphs g '.
								' where g.graphid=gi.graphid and gi.itemid=i.itemid'.
								' group by gi.graphid, i.hostid'

					);
				foreach($sqls as $el_type => $sql)
				{
					if(!isset($sql)) continue;

					$db_els = DBselect($sql);
					while($el = DBfetch($db_els))
					{
						if($el['cnt'] != 1 || $el['hostid'] != $host['hostid']) continue;
						$el_table->AddRow(array($el_type, $el['info']));
					}
				}
				
				$table->ShowRow(array(new CCol($host['host'], 'top'),$el_table));
				unset($el_table);
			}
			
			$form = new CForm(null,'post');
			$form->SetName('hosts');
			$form->AddVar("config",		$config);
			$form->AddVar('update',		true);
			$form->AddVar('hosts',		$hosts);
			$form->AddVar('items', 		$items);
			$form->AddVar('graphs', 	$graphs);
			$form->AddVar('triggers',	$triggers);

			$form->AddItem(array(
				new CButton('back', S_BACK),
				new CButton('preview', S_REFRESH),
				new CButton('export', S_EXPORT)
				));
			
			$table->SetFooter(new CCol($form));
			
			$table->ShowEnd();
		}
		else
		{
	/* table HOSTS */
			$form = new CForm(null,'post');
			$form->SetName('hosts');
			$form->AddVar("config",$config);
			$form->AddVar('update', true);
			
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

			$form->AddItem($header);

			$table = new CTableInfo(S_NO_HOSTS_DEFINED);
			$table->SetHeader(array(
				array(	new CCheckBox("all_hosts",true, "CheckAll('".$form->GetName()."','all_hosts','hosts');"),
					S_NAME),
				S_DNS,
				S_IP,
				S_PORT,
				S_STATUS,
				array(	new CCheckBox("all_items",true, "CheckAll('".$form->GetName()."','all_items','items');"),
					S_ITEMS),
				array(	new CCheckBox("all_triggers",true, "CheckAll('".$form->GetName()."','all_triggers','triggers');"),
					S_TRIGGERS),
				array(	new CCheckBox("all_graphs",true, "CheckAll('".$form->GetName()."','all_graphs','graphs');"),
					S_GRAPHS)
				/*
				array(	new CCheckBox("all_screens",true, "CheckAll('".$form->GetName()."','all_screens','screens');")
					S_GRAPHS)
				*/
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
						isset($hosts[$row['hostid']]) || !isset($update),
						NULL,true),
					SPACE,
					$row["host"]
					));
				
				if($row["status"] == HOST_STATUS_MONITORED){
					$status=new CSpan(S_MONITORED, "off");
				} else if($row["status"] == HOST_STATUS_NOT_MONITORED) {
					$status=new CSpan(S_NOT_MONITORED, "on");
				} else if($row["status"] == HOST_STATUS_TEMPLATE)
					$status=new CCol(S_TEMPLATE,"unknown");
				else if($row["status"] == HOST_STATUS_DELETED)
					$status=new CCol(S_DELETED,"unknown");
				else
					$status=S_UNKNOWN;
				
				/* calculate items */
				$item_cnt = DBfetch(DBselect('select count(itemid) as cnt from items where hostid='.$row['hostid']));
				if($item_cnt['cnt'] > 0)
				{
					$item_cnt = array(new CCheckBox('items['.$row['hostid'].']',
							isset($items[$row['hostid']]) || !isset($update),
							NULL,true),
						$item_cnt['cnt']);
				}
				else
				{
					$item_cnt = '-';
				}
				
				/* calculate triggers */
				$trigger_cnt = 0;
				$db_triggers = DBselect('select f.triggerid, i.hostid, count(distinct i.hostid) as cnt from functions f, items i '.
					' where f.itemid=i.itemid group by f.triggerid, i.hostid');
				while($db_tr = DBfetch($db_triggers)) if($db_tr['cnt'] == 1 && $db_tr['hostid'] == $row['hostid']) $trigger_cnt++;
				if($trigger_cnt > 0)
				{
					$trigger_cnt = array(new CCheckBox('triggers['.$row['hostid'].']',
							isset($triggers[$row['hostid']]) || !isset($update),
							NULL,true),
						$trigger_cnt);
				}
				else
				{
					$trigger_cnt = '-';
				}
			
				/* calculate graphs */
				$graph_cnt = 0;
				$db_graphs = DBselect('select gi.graphid, i.hostid, count(distinct i.hostid) as cnt'.
					' from graphs_items gi, items i '.
					' where gi.itemid=i.itemid group by gi.graphid, i.hostid');
				while($db_tr = DBfetch($db_graphs)) if($db_tr['cnt'] == 1 && $db_tr['hostid'] == $row['hostid']) $graph_cnt++;
				if($graph_cnt > 0)
				{
					$graph_cnt = array(new CCheckBox('graphs['.$row['hostid'].']',
							isset($graphs[$row['hostid']]) || !isset($update),
							NULL,true),
						$graph_cnt);
				}
				else
				{
					$graph_cnt = '-';
				}
				
				/* $screens = 0; */

				if($row["status"] == HOST_STATUS_TEMPLATE)
				{
					$ip = $dns = $port = '-';
				}
				else
				{
					$ip = $row["ip"];
					$dns = $row["dns"];

					if($row["useip"]==1)
						$ip = bold($ip);
					else
						$dns = bold($dns);

					$port = $row["port"];
				}

				$table->AddRow(array(
					$host,
					$dns,
					$ip,
					$port,
					$status,
					$item_cnt,
					$trigger_cnt,
					$graph_cnt
					/*,
					array(new CCheckBox('screens['.$row['hostid'].']',
							isset($screens[$row['hostid']]) || !isset($update),
							NULL,true),
						$screens)*/
					));
				$table->SetFooter(new CCol(array(
					new CButton('preview', S_PREVIEW),
					new CButton('export', S_EXPORT)
					)));
			}
			$form->AddItem($table);
			$form->Show();
		}
	}
	
?>
<?php

include_once "include/page_footer.php";

?>
