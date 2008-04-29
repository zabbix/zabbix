<?php
/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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
	require_once "include/triggers.inc.php";
	require_once "include/forms.inc.php";


	$page["title"] = "S_CONFIGURATION_OF_TRIGGERS";
	$page["file"] = "triggers.php";
	$page['hist_arg'] = array('hostid','groupid');

include_once "include/page_header.php";

?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		'hostid'=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,'isset({save})'),

		'triggerid'=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,'(isset({form})&&({form}=="update"))'),

		'copy_type'	=>array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0,1'),'isset({copy})'),
		'copy_mode'	=>array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0'),NULL),

		'type'=>	array(T_ZBX_INT, O_OPT,  NULL, 		IN('0,1'),	'isset({save})'),
		'description'=>	array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,'isset({save})'),
		'expression'=>	array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,'isset({save})'),
		'priority'=>	array(T_ZBX_INT, O_OPT,  NULL,  IN('0,1,2,3,4,5'),'isset({save})'),
		'comments'=>	array(T_ZBX_STR, O_OPT,  NULL,	NULL,'isset({save})'),
		'url'=>		array(T_ZBX_STR, O_OPT,  NULL,	NULL,'isset({save})'),
		'status'=>	array(T_ZBX_STR, O_OPT,  NULL,	NULL,NULL),

		'dependencies'=>		array(T_ZBX_INT, O_OPT,  NULL,	DB_ID, NULL),
		'new_dependence'=>	array(T_ZBX_INT, O_OPT,  NULL,	DB_ID.'{}>0','isset({add_dependence})'),
		'rem_dependence'=>	array(T_ZBX_INT, O_OPT,  NULL,	DB_ID, NULL),

		'g_triggerid'=>	array(T_ZBX_INT, O_OPT,  NULL,	DB_ID, NULL),
		'copy_targetid'=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID, NULL),
		'filter_groupid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, 'isset({copy})&&(isset({copy_type})&&({copy_type}==0))'),

		'showdisabled'=>	array(T_ZBX_INT, O_OPT, P_SYS, IN('0,1'),	NULL),
		
/* actions */
		'add_dependence'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'del_dependence'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'group_enable'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'group_disable'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'group_delete'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'copy'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'clone'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
/* other */
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_copy_to'=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	$_REQUEST['showdisabled'] = get_request('showdisabled', get_profile('web.triggers.showdisabled', 0));
	
	check_fields($fields);
	validate_sort_and_sortorder('t.description',ZBX_SORT_UP);
	
	if(isset($_REQUEST['triggerid']))
		if(!check_right_on_trigger_by_triggerid(PERM_READ_WRITE, $_REQUEST['triggerid']))
			access_deny();

	$showdisabled = get_request('showdisabled', 0);

	validate_group_with_host(PERM_READ_WRITE,array('allow_all_hosts','always_select_first_host','with_items','only_current_node'),
		'web.last.conf.groupid', 'web.last.conf.hostid');
?>
<?php
	update_profile('web.triggers.showdisabled',$showdisabled);

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY);

/* FORM ACTIONS */
	if(isset($_REQUEST['clone']) && isset($_REQUEST['triggerid'])){
		unset($_REQUEST['triggerid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['save'])){
		show_messages();
		if(!check_right_on_trigger_by_expression(PERM_READ_WRITE, $_REQUEST['expression']))
			access_deny();

		$now=time();
		if(isset($_REQUEST['status']))	{ $status=TRIGGER_STATUS_DISABLED; }
		else{ $status=TRIGGER_STATUS_ENABLED; }

		$type = $_REQUEST['type'];

		$deps = get_request('dependencies',array());

		if(isset($_REQUEST['triggerid'])){
			$trigger_data = get_trigger_by_triggerid($_REQUEST['triggerid']);
			if($trigger_data['templateid']){
				$_REQUEST['description'] = $trigger_data['description'];
				$_REQUEST['expression'] = explode_exp($trigger_data['expression'],0);
			}

			DBstart();
			$result=update_trigger($_REQUEST['triggerid'],
				$_REQUEST['expression'],$_REQUEST['description'],$type,
				$_REQUEST['priority'],$status,$_REQUEST['comments'],$_REQUEST['url'],
				$deps, $trigger_data['templateid']);
			$result = DBend();
			
			$triggerid = $_REQUEST['triggerid'];
			$audit_action = AUDIT_ACTION_UPDATE;

			show_messages($result, S_TRIGGER_UPDATED, S_CANNOT_UPDATE_TRIGGER);
		} 
		else {
			DBstart();
			$triggerid=add_trigger($_REQUEST['expression'],$_REQUEST['description'],$type,
				$_REQUEST['priority'],$status,$_REQUEST['comments'],$_REQUEST['url'],
				$deps);
			$result = DBend();
						
			$audit_action = AUDIT_ACTION_ADD;
			show_messages($result, S_TRIGGER_ADDED, S_CANNOT_ADD_TRIGGER);
		}

		if($result){
			add_audit($audit_action, AUDIT_RESOURCE_TRIGGER,S_TRIGGER.' ['.$triggerid.'] ['.expand_trigger_description($triggerid).'] ');
			unset($_REQUEST['form']);
		}
	}
	else if(isset($_REQUEST['delete'])&&isset($_REQUEST['triggerid'])){
		$result = false;
		
		if($trigger_data = DBfetch(
			DBselect('SELECT DISTINCT t.triggerid,t.description,t.expression,h.host '.
				' FROM triggers t '.
					' LEFT JOIN functions f on t.triggerid=f.triggerid '.
					' LEFT JOIN items i on f.itemid=i.itemid '.
					' LEFT JOIN hosts h on i.hostid=h.hostid '.
				' WHERE t.triggerid='.$_REQUEST['triggerid'].
					' AND t.templateid=0')
			))
		{
			DBstart();
			$result = delete_trigger($_REQUEST['triggerid']);
			$result = DBend();
		}
		
		show_messages($result, S_TRIGGER_DELETED, S_CANNOT_DELETE_TRIGGER);
		
		if($result){
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TRIGGER,
				S_TRIGGER.' ['.$_REQUEST['triggerid'].'] ['.expand_trigger_description_by_data($trigger_data).'] ');
			
			unset($_REQUEST['form']);
			unset($_REQUEST['triggerid']);
		}
	}
	else if(isset($_REQUEST['copy'])&&isset($_REQUEST['g_triggerid'])&&isset($_REQUEST['form_copy_to'])){
		if(isset($_REQUEST['copy_targetid']) && $_REQUEST['copy_targetid'] > 0 && isset($_REQUEST['copy_type'])){
			if(0 == $_REQUEST['copy_type']){ /* hosts */
				$hosts_ids = $_REQUEST['copy_targetid'];
			}
			else{ /* groups */
				$hosts_ids = array();
				$group_ids = '';
				foreach($_REQUEST['copy_targetid'] as $group_id){
					$group_ids .= $group_id.',';
				}
				$group_ids = trim($group_ids,',');

				$db_hosts = DBselect('SELECT DISTINCT h.hostid '.
					' FROM hosts h, hosts_groups hg'.
					' WHERE h.hostid=hg.hostid '.
						' AND hg.groupid in ('.$group_ids.')');
				while($db_host = DBfetch($db_hosts)){
					array_push($hosts_ids, $db_host['hostid']);
				}
			}

			foreach($_REQUEST['g_triggerid'] as $trigger_id)
				foreach($hosts_ids as $host_id){
					copy_trigger_to_host($trigger_id, $host_id, true);
				}
			unset($_REQUEST['form_copy_to']);
		}
		else{
			error('No target selection.');
		}
		show_messages();
	}
/* DEPENDENCE ACTIONS */
	else if(isset($_REQUEST['add_dependence'])&&isset($_REQUEST['new_dependence'])){
		if(!isset($_REQUEST['dependencies']))
			$_REQUEST['dependencies'] = array();

		if(!uint_in_array($_REQUEST["new_dependence"], $_REQUEST["dependencies"]))
			array_push($_REQUEST["dependencies"], $_REQUEST["new_dependence"]);
	}
	else if(isset($_REQUEST["del_dependence"])&&isset($_REQUEST["rem_dependence"])){
		if(isset($_REQUEST["dependencies"])){
			foreach($_REQUEST["dependencies"]as $key => $val){
				if(!uint_in_array($val, $_REQUEST["rem_dependence"]))	continue;
				unset($_REQUEST["dependencies"][$key]);
			}
		}
	}
/* GROUP ACTIONS */
	else if(isset($_REQUEST["group_enable"])&&isset($_REQUEST["g_triggerid"])){
	
		foreach($_REQUEST["g_triggerid"] as $triggerid){
			if(!check_right_on_trigger_by_triggerid(null, $triggerid)) continue;

			$result=DBselect("SELECT triggerid FROM triggers t WHERE t.triggerid=".zbx_dbstr($triggerid));
			if(!($row = DBfetch($result))) continue;
			
			if($result = update_trigger_status($row['triggerid'],0)){
				
				$status = get_trigger_priority($row['triggerid']);
				update_services($triggerid, $status); // updating status to all services by the dependency
				
				add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER,
					S_TRIGGER." [".$triggerid."] [".expand_trigger_description($triggerid)."] ".S_ENABLED);
			}
			$result2 = isset($result2) ? $result2 | $result : $result;
		}
		
		if(isset($result2)){
			show_messages($result2, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
		}
	}
	else if(isset($_REQUEST["group_disable"])&&isset($_REQUEST["g_triggerid"])){
		foreach($_REQUEST["g_triggerid"] as $triggerid){
			if(!check_right_on_trigger_by_triggerid(null, $triggerid)) continue;

			$result=DBselect("SELECT triggerid FROM triggers t WHERE t.triggerid=".zbx_dbstr($triggerid));
			if(!($row = DBfetch($result))) continue;
			if($result = update_trigger_status($row["triggerid"],1));{
			
				update_services($triggerid, 0); // updating status to all services by the dependency
				
				add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER,
					S_TRIGGER." [".$triggerid."] [".expand_trigger_description($triggerid)."] ".S_DISABLED);
			}
			$result2 = isset($result2) ? $result2 | $result : $result;
		}
		if(isset($result2)){
			show_messages($result2, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
		}
	}
	else if(isset($_REQUEST["group_delete"])&&isset($_REQUEST["g_triggerid"])){
		
		foreach($_REQUEST["g_triggerid"] as $triggerid){
			if(!check_right_on_trigger_by_triggerid(null, $triggerid)) continue;

			$result=DBselect("SELECT triggerid,templateid FROM triggers t WHERE t.triggerid=".zbx_dbstr($triggerid));
			if(!($row = DBfetch($result))) continue;
			if($row["templateid"] <> 0)	continue;
			
			$description = expand_trigger_description($triggerid);
			
			DBstart();
			$result = delete_trigger($row["triggerid"]);
			$result = DBend();
			
			if($result){
				add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER,
					S_TRIGGER." [".$triggerid."] [".$description."] ".S_DISABLED);
			}

			$result2 = isset($result2) ? $result2 | $result : $result;
		}
		if(isset($result2)){
			show_messages($result2, S_TRIGGERS_DELETED, S_CANNOT_DELETE_TRIGGERS);
		}
	}
?>
<?php
	$r_form = new CForm();
	$r_form->SetMethod('get');
	$r_form->AddItem(array('[', 
		new CLink($showdisabled ? S_HIDE_DISABLED_TRIGGERS : S_SHOW_DISABLED_TRIGGERS,
			'triggers.php?showdisabled='.($showdisabled ? 0 : 1),NULL),
		']', SPACE));

	$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit()");
	$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit()");

	$cmbGroup->AddItem(0,S_ALL_SMALL);
	
	$result=DBselect('SELECT DISTINCT g.groupid,g.name '.
		' FROM groups g, hosts_groups hg, hosts h, items i '.
		' WHERE '.DBcondition('h.hostid',$available_hosts).
			' AND hg.groupid=g.groupid '.
			' AND h.hostid=i.hostid '.
			' AND hg.hostid=h.hostid '.
		' ORDER BY g.name');
	while($row=DBfetch($result)){
		$cmbGroup->AddItem($row['groupid'],$row['name']);
	}
	$r_form->AddItem(array(S_GROUP.SPACE,$cmbGroup));
	
	if($_REQUEST['groupid'] > 0){
		$sql='SELECT h.hostid,h.host '.
			' FROM hosts h,items i,hosts_groups hg '.
			' WHERE h.hostid=i.hostid '.
				' AND hg.groupid='.$_REQUEST['groupid'].
				' AND hg.hostid=h.hostid'.
				' AND '.DBcondition('h.hostid',$available_hosts).
			' GROUP BY h.hostid,h.host '.
			' ORDER BY h.host';
	}
	else{
		$cmbHosts->AddItem(0,S_ALL_SMALL);
		$sql='SELECT h.hostid,h.host '.
			' FROM hosts h,items i '.
			' WHERE h.hostid=i.hostid '.
				' AND '.DBcondition('h.hostid',$available_hosts).
			' GROUP BY h.hostid,h.host '.
			' ORDER BY h.host';
	}
	
	$result=DBselect($sql);
	while($row=DBfetch($result)){
		$cmbHosts->AddItem($row["hostid"],$row["host"]);
	}

	$r_form->AddItem(array(SPACE.S_HOST.SPACE,$cmbHosts));

	$r_form->AddItem(array(SPACE, new CButton("form", S_CREATE_TRIGGER)));

	show_table_header(S_TRIGGERS_BIG, $r_form);
?>
<?php
	if(isset($_REQUEST["form"])){
/* FORM */
		echo SBR;
		insert_trigger_form();
	} 
	else if(isset($_REQUEST["form_copy_to"]) && isset($_REQUEST["g_triggerid"])){
		echo SBR;
		insert_copy_elements_to_forms("g_triggerid");
	} 
	else{
/* TABLE */
		$form = new CForm('triggers.php');
		$form->SetName('triggers');
		$form->SetMethod('post');
		$form->AddVar('hostid',$_REQUEST["hostid"]);

		$table = new CTableInfo(S_NO_TRIGGERS_DEFINED);
		$table->setHeader(array(
			make_sorting_link(S_SEVERITY,'t.priority'), 
			make_sorting_link(S_STATUS,'t.status'), 

			$_REQUEST["hostid"] > 0 ? NULL : make_sorting_link(S_HOST,'h.host'),
			array(	new CCheckBox("all_triggers",NULL,
					"CheckAll('".$form->GetName()."','all_triggers');")
				,make_sorting_link(S_NAME,'t.description'),
			),
			S_EXPRESSION, 
			S_ERROR));

		$sql = 'SELECT DISTINCT h.hostid,h.host,t.*'.
			' FROM triggers t '.
				' LEFT JOIN functions f ON t.triggerid=f.triggerid '.
				' LEFT JOIN items i ON f.itemid=i.itemid '.
				' LEFT JOIN hosts h ON h.hostid=i.hostid '.
			' WHERE '.DBin_node('t.triggerid');

		if($showdisabled == 0)
		    $sql .= ' AND t.status <> '.TRIGGER_STATUS_DISABLED;

		if($_REQUEST['hostid'] > 0) 
			$sql .= ' AND h.hostid='.$_REQUEST['hostid'];

		$sql .= order_by('h.host,t.description,t.priority,t.status');

		$result=DBselect($sql);
		while($row=DBfetch($result)){
			if(!check_right_on_trigger_by_triggerid(null, $row['triggerid']))
				continue;

			if(is_null($row['host'])) $row['host'] = '';
			if(is_null($row['hostid'])) $row['hostid'] = '0';


			$description = array(	new CCheckBox(
							"g_triggerid[]",        /* name */
							NULL,                   /* checked */
							NULL,                   /* action */
							$row["triggerid"]),     /* value */
						SPACE);

			if($row["templateid"]){
				$real_hosts = get_realhosts_by_triggerid($row["triggerid"]);
				$real_host = DBfetch($real_hosts);
				if($real_host){
					$description[] = new CLink($real_host["host"],
							"triggers.php?&hostid=".$real_host["hostid"], 'unknown');
				}
				else{
					$description[] = new CSpan("error","on");
				}
				$description[] = ':';
			}

			$description[] = new CLink(expand_trigger_description($row["triggerid"]),
				"triggers.php?form=update&triggerid=".$row["triggerid"].
					"&hostid=".$row["hostid"], 'action');

			//add dependencies
			$deps = get_trigger_dependencies_by_triggerid($row["triggerid"]);
			if(count($deps) > 0){
				$description[] = array(BR(),BR(),bold(S_DEPENDS_ON.':'),SPACE,BR());
				foreach($deps as $val)
					$description[] = array(expand_trigger_description($val),BR());

				$description[] = BR();
			}
	
			if($row["priority"]==0)		$priority=S_NOT_CLASSIFIED;
			elseif($row["priority"]==1)	$priority=new CCol(S_INFORMATION,"information");
			elseif($row["priority"]==2)	$priority=new CCol(S_WARNING,"warning");
			elseif($row["priority"]==3)	$priority=new CCol(S_AVERAGE,"average");
			elseif($row["priority"]==4)	$priority=new CCol(S_HIGH,"high");
			elseif($row["priority"]==5)	$priority=new CCol(S_DISASTER,"disaster");
			else $priority=$row["priority"];

			if($row["status"] == TRIGGER_STATUS_DISABLED){
				$status= new CLink(S_DISABLED,
					"triggers.php?group_enable=1&g_triggerid%5B%5D=".$row["triggerid"].
						"&hostid=".$row["hostid"],
					'disabled');
			}
			else if($row["status"] == TRIGGER_STATUS_UNKNOWN){
				$status= new CLink(S_UNKNOWN,
					"triggers.php?group_disable=1&g_triggerid%5B%5D=".$row["triggerid"].
						"&hostid=".$row["hostid"],
					'unknown');
			}
			else if($row["status"] == TRIGGER_STATUS_ENABLED){
				$status= new CLink(S_ENABLED,
					"triggers.php?group_disable=1&g_triggerid%5B%5D=".$row["triggerid"].
						"&hostid=".$row["hostid"],
					'enabled');
			}

			if($row["status"] != TRIGGER_STATUS_UNKNOWN)	$row["error"]=SPACE;

			if($row["error"]=="")		$row["error"]=SPACE;

			$table->addRow(array(
				$priority,
				$status,
				$_REQUEST["hostid"] > 0 ? NULL : $row["host"],
				$description,
				explode_exp($row["expression"],1),
				$row["error"]
			));
		}
		
		$table->SetFooter(new CCol(array(
			new CButtonQMessage('group_enable',S_ENABLE_SELECTED,S_ENABLE_SELECTED_TRIGGERS_Q),
			SPACE,
			new CButtonQMessage('group_disable',S_DISABLE_SELECTED,S_DISABLE_SELECTED_TRIGGERS_Q),
			SPACE,
			new CButtonQMessage('group_delete',S_DELETE_SELECTED,S_DELETE_SELECTED_TRIGGERS_Q),
			SPACE,
			new CButton('form_copy_to',S_COPY_SELECTED_TO)
		)));

		$form->AddItem($table);
		$form->Show();
	}
?>

<?php

include_once "include/page_footer.php";

?>
