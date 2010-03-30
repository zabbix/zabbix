<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
require_once('include/config.inc.php');
require_once('include/hosts.inc.php');
require_once('include/triggers.inc.php');
require_once('include/forms.inc.php');

$page['title'] = 'S_CONFIGURATION_OF_TRIGGERS';
$page['file'] = 'triggers.php';
$page['hist_arg'] = array('hostid','groupid');

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>			array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID, null),
		'hostid'=>			array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID, null),

		'triggerid'=>		array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,'(isset({form})&&({form}=="update"))'),

		'copy_type'	=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0,1'),'isset({copy})'),
		'copy_mode'	=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0'),NULL),

		'type'=>			array(T_ZBX_INT, O_OPT,  NULL, 		IN('0,1'),	'isset({save})'),
		'description'=>		array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,'isset({save})'),
		'expression'=>		array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,'isset({save})'),
		'priority'=>		array(T_ZBX_INT, O_OPT,  NULL,  IN('0,1,2,3,4,5'),'isset({save})'),
		'comments'=>		array(T_ZBX_STR, O_OPT,  NULL,	NULL,'isset({save})'),
		'url'=>				array(T_ZBX_STR, O_OPT,  NULL,	NULL,'isset({save})'),
		'status'=>			array(T_ZBX_STR, O_OPT,  NULL,	NULL, NULL),

        'input_method'=>	array(T_ZBX_INT, O_OPT,  NULL,  NOT_EMPTY,'isset({toggle_input_method})'),
        'expr_temp'=>		array(T_ZBX_STR, O_OPT,  NULL,  NOT_EMPTY,'(isset({add_expression})||isset({and_expression})||isset({or_expression})||isset({replace_expression}))'),
        'expr_target_single'=>		array(T_ZBX_STR, O_OPT,  NULL,  NOT_EMPTY,'(isset({and_expression})||isset({or_expression})||isset({replace_expression}))'),

		'dependencies'=>	array(T_ZBX_INT, O_OPT,  NULL,	DB_ID, NULL),
		'new_dependence'=>	array(T_ZBX_INT, O_OPT,  NULL,	DB_ID.'{}>0','isset({add_dependence})'),
		'rem_dependence'=>	array(T_ZBX_INT, O_OPT,  NULL,	DB_ID, NULL),

		'g_triggerid'=>		array(T_ZBX_INT, O_OPT,  NULL,	DB_ID, NULL),
		'copy_targetid'=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID, NULL),
		'filter_groupid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, 'isset({copy})&&(isset({copy_type})&&({copy_type}==0))'),

		'showdisabled'=>	array(T_ZBX_INT, O_OPT, P_SYS, IN('0,1'),	NULL),

// mass update
		'massupdate'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'visible'=>			array(T_ZBX_STR, O_OPT,	null, 	null,	null),

// Actions
		'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),

// form
        'toggle_input_method'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
        'add_expression'=> 		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
        'and_expression'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
        'or_expression'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
        'replace_expression'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
        'remove_expression'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),
        'test_expression'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,    NULL,   NULL),

		'add_dependence'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'del_dependence'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'group_enable'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'group_disable'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'group_delete'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'copy'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'clone'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'mass_save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),

// other
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	$_REQUEST['showdisabled'] = get_request('showdisabled', CProfile::get('web.triggers.showdisabled', 0));

	check_fields($fields);
	validate_sort_and_sortorder('description',ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go','none');

// PERMISSIONS
	if(get_request('triggerid',0) > 0){
		$triggers = available_triggers($_REQUEST['triggerid'], 1);
		if(empty($triggers)) access_deny();
	}
?>
<?php

	$showdisabled = get_request('showdisabled', 0);
	CProfile::update('web.triggers.showdisabled',$showdisabled,PROFILE_TYPE_INT);

// EXPRESSION ACTIONS
	if(isset($_REQUEST['add_expression'])){
		$_REQUEST['expression'] = $_REQUEST['expr_temp'];
		$_REQUEST['expr_temp'] = '';
	}
	else if(isset($_REQUEST['and_expression'])){
		$_REQUEST['expr_action'] = '&';
	}
	else if(isset($_REQUEST['or_expression'])){
		$_REQUEST['expr_action'] = '|';
	}
	else if(isset($_REQUEST['replace_expression'])){
		$_REQUEST['expr_action'] = 'r';
	}
	else if(isset($_REQUEST['remove_expression']) && zbx_strlen($_REQUEST['remove_expression'])){
		$_REQUEST['expr_action'] = 'R';
		$_REQUEST['expr_target_single'] = $_REQUEST['remove_expression'];
	}
/* FORM ACTIONS */
	else if(isset($_REQUEST['clone']) && isset($_REQUEST['triggerid'])){
		unset($_REQUEST['triggerid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['save'])){
		show_messages();

		if(!check_right_on_trigger_by_expression(PERM_READ_WRITE, $_REQUEST['expression']))
			access_deny();

		$status = isset($_REQUEST['status'])?TRIGGER_STATUS_DISABLED:TRIGGER_STATUS_ENABLED;

		$type = $_REQUEST['type'];

		$deps = get_request('dependencies',array());

		if(isset($_REQUEST['triggerid'])){
			$trigger_data = get_trigger_by_triggerid($_REQUEST['triggerid']);
			if($trigger_data['templateid']){
				$_REQUEST['description'] = $trigger_data['description'];
				$_REQUEST['expression'] = explode_exp($trigger_data['expression'],0);
			}

			DBstart();

			$result = update_trigger($_REQUEST['triggerid'],
				$_REQUEST['expression'],$_REQUEST['description'],$type,
				$_REQUEST['priority'],$status,$_REQUEST['comments'],$_REQUEST['url'],
				$deps, $trigger_data['templateid']);
			$result = DBend($result);

			$triggerid = $_REQUEST['triggerid'];

			show_messages($result, S_TRIGGER_UPDATED, S_CANNOT_UPDATE_TRIGGER);
		}
		else {
			DBstart();
			$triggerid = add_trigger($_REQUEST['expression'],$_REQUEST['description'],$type,
				$_REQUEST['priority'],$status,$_REQUEST['comments'],$_REQUEST['url'],
				$deps);
			$result = DBend($triggerid);
			show_messages($result, S_TRIGGER_ADDED, S_CANNOT_ADD_TRIGGER);
		}
		if($result)
			unset($_REQUEST['form']);
	}
	else if(isset($_REQUEST['delete'])&&isset($_REQUEST['triggerid'])){
		$result = false;

		$options = array('triggerids' => $_REQUEST['triggerid'], 'extendoutput'=>1);

		$triggers = CTrigger::get($options);
		if($trigger_data = reset($triggers)){
			DBstart();
			$result = CTrigger::delete($triggers);
			$result = DBend($result);
			if($result){
				add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TRIGGER, $_REQUEST['triggerid'], $trigger_data['description'], NULL, NULL, NULL);
			}
		}

		show_messages($result, S_TRIGGER_DELETED, S_CANNOT_DELETE_TRIGGER);

		if($result){
			//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TRIGGER,
			//	S_TRIGGER.' ['.$_REQUEST['triggerid'].'] ['.expand_trigger_description_by_data($trigger_data).'] ');
			unset($_REQUEST['form']);
			unset($_REQUEST['triggerid']);
		}
	}
// DEPENDENCE ACTIONS
	else if(isset($_REQUEST['add_dependence'])&&isset($_REQUEST['new_dependence'])){
		if(!isset($_REQUEST['dependencies']))
			$_REQUEST['dependencies'] = array();

			foreach($_REQUEST['new_dependence'] as $triggerid) {
			if(!uint_in_array($triggerid, $_REQUEST['dependencies']))
				array_push($_REQUEST['dependencies'], $triggerid);
		}
	}
	else if(isset($_REQUEST['del_dependence'])&&isset($_REQUEST['rem_dependence'])){
		if(isset($_REQUEST['dependencies'])){
			foreach($_REQUEST['dependencies'] as $key => $val){
				if(!uint_in_array($val, $_REQUEST['rem_dependence']))	continue;
				unset($_REQUEST['dependencies'][$key]);
			}
		}
	}
// ------- GO ---------
	else if(($_REQUEST['go'] == 'massupdate') && isset($_REQUEST['mass_save']) && isset($_REQUEST['g_triggerid'])){
		show_messages();

		$result = false;

		$visible = get_request('visible',array());
		$_REQUEST['dependencies'] = get_request('dependencies',array());

		$options = array(
			'triggerids' => $_REQUEST['g_triggerid'],
			'select_dependencies' => 1,
			'output' => API_OUTPUT_EXTEND,
			'editable' => 1
		);
		$triggers = CTrigger::get($options);

		DBstart();
		foreach($triggers as $tnum => $db_trig){
			foreach($db_trig as $key => $value){
				if(isset($visible[$key])){
					$db_trig[$key] = $_REQUEST[$key];
				}
			}

			$result = update_trigger($db_trig['triggerid'],
				null,null,null,
				$db_trig['priority'],null,null,null,
				$db_trig['dependencies'],null);

			if(!$result) break;
		}
		$result = DBend($result);

		show_messages($result, S_TRIGGER_UPDATED, S_CANNOT_UPDATE_TRIGGER);
		if($result){
			unset($_REQUEST['massupdate']);
			unset($_REQUEST['form']);
			$url = new CUrl();
			$path = $url->getPath();
			insert_js('cookie.eraseArray("'.$path.'")');
		}

		$go_result = $result;
	}
	else if(str_in_array($_REQUEST['go'], array('activate', 'disable')) && isset($_REQUEST['g_triggerid'])){

		$options = array('extendoutput'=>1, 'editable'=>1);
		$options['triggerids'] = $_REQUEST['g_triggerid'];

		$triggers = CTrigger::get($options);
		$triggerids = zbx_objectValues($triggers, 'triggerid');

		if(($_REQUEST['go'] == 'activate')){
			$status = TRIGGER_STATUS_ENABLED;
			$status_old = array('status'=>0);
			$status_new = array('status'=>1);
		}
		else {
			$status = TRIGGER_STATUS_DISABLED;
			$status_old = array('status'=>1);
			$status_new = array('status'=>0);
		}

		DBstart();
		$go_result = update_trigger_status($triggerids, $status);

		if($go_result){
			foreach($triggers as $tnum => $trigger){
				$serv_status = (isset($_REQUEST['group_enable']))?get_service_status_of_trigger($trigger['triggerid']):0;

				update_services($trigger['triggerid'], $serv_status); // updating status to all services by the dependency
				add_audit_ext(AUDIT_ACTION_UPDATE,
								AUDIT_RESOURCE_TRIGGER,
								$trigger['triggerid'],
								$trigger['description'],
								'triggers',
								$status_old,
								$status_new);
			}
		}

		$go_result = DBend($go_result);
		show_messages($go_result, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
	}
	else if(($_REQUEST['go'] == 'copy_to') && isset($_REQUEST['copy']) && isset($_REQUEST['g_triggerid'])){
		if(isset($_REQUEST['copy_targetid']) && ($_REQUEST['copy_targetid'] > 0) && isset($_REQUEST['copy_type'])){
			if(0 == $_REQUEST['copy_type']){ /* hosts */
				$hosts_ids = $_REQUEST['copy_targetid'];
			}
			else{
// groups
				$hosts_ids = array();
				$group_ids = $_REQUEST['copy_targetid'];

				$sql = 'SELECT DISTINCT h.hostid '.
					' FROM hosts h, hosts_groups hg'.
					' WHERE h.hostid=hg.hostid '.
						' AND '.DBcondition('hg.groupid',$group_ids);
				$db_hosts = DBselect($sql);
				while($db_host = DBfetch($db_hosts)){
					array_push($hosts_ids, $db_host['hostid']);
				}
			}

			$go_result = false;
			$new_triggerids = array();

			DBstart();
			foreach($hosts_ids as $num => $host_id){
				foreach($_REQUEST['g_triggerid'] as $tnum => $trigger_id){
					$newtrigid = copy_trigger_to_host($trigger_id, $host_id, true);

					$new_triggerids[$trigger_id] = $newtrigid;
					$go_result |= (bool) $newtrigid;
				}

//				replace_triggers_depenedencies($new_triggerids);
			}

			$go_result = DBend($go_result);
			$_REQUEST['go'] = 'none2';
		}
		else{
			error('No target selection.');
		}
		show_messages($go_result, S_TRIGGER_ADDED, S_CANNOT_ADD_TRIGGER);
	}
	else if(($_REQUEST['go'] == 'delete') && isset($_REQUEST['g_triggerid'])){
		$options = array('extendoutput'=>1, 'editable'=>1);
		$options['triggerids'] = $_REQUEST['g_triggerid'];

		$triggers = CTrigger::get($options);

		DBstart();
		foreach($triggers as $tnum => $trigger){
			$triggerid = $trigger['triggerid'];

			$description = expand_trigger_description($triggerid);
			if($trigger['templateid'] != 0){
				unset($triggers[$tnum]);
				error(S_CANNOT_DELETE_TRIGGER.' [ '.$description.' ] ('.S_TEMPLATED_TRIGGER.')');
				continue;
			}

			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TRIGGER, $triggerid, $description, NULL, NULL, NULL);
		}

		$go_result = !empty($triggers);
		if($go_result){
			$go_result = CTrigger::delete($triggers);
		}

		$go_result = DBend($go_result);
		show_messages($go_result, S_TRIGGERS_DELETED, S_CANNOT_DELETE_TRIGGERS);
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
		$_REQUEST['go'] = 'none';
	}

?>
<?php
	if(isset($_REQUEST['hostid']) && !isset($_REQUEST['groupid']) && !isset($_REQUEST['triggerid'])){
		$sql = 'SELECT DISTINCT hg.groupid '.
				' FROM hosts_groups hg '.
				' WHERE hg.hostid='.$_REQUEST['hostid'];
		if($group=DBfetch(DBselect($sql, 1))){
			$_REQUEST['groupid'] = $group['groupid'];
		}
	}

	if(isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid']>0)){
		$sql_from = '';
		$sql_where = '';
		if(isset($_REQUEST['groupid']) && ($_REQUEST['groupid'] > 0)){
			$sql_where.= ' AND hg.groupid='.$_REQUEST['groupid'];
		}

		if(isset($_REQUEST['hostid']) && ($_REQUEST['hostid'] > 0)){
			$sql_where.= ' AND hg.hostid='.$_REQUEST['hostid'];
		}

		$sql = 'SELECT DISTINCT hg.groupid, hg.hostid '.
				' FROM hosts_groups hg '.
				' WHERE EXISTS( SELECT i.itemid '.
								' FROM items i, functions f'.
								' WHERE i.hostid=hg.hostid '.
									' AND f.itemid=i.itemid '.
									' AND f.triggerid='.$_REQUEST['triggerid'].')'.
						$sql_where;
		if($host_group = DBfetch(DBselect($sql,1))){
			if(!isset($_REQUEST['groupid']) || !isset($_REQUEST['hostid'])){
				$_REQUEST['groupid'] = $host_group['groupid'];
				$_REQUEST['hostid'] = $host_group['hostid'];
			}
			else if(($_REQUEST['groupid']!=$host_group['groupid']) || ($_REQUEST['hostid']!=$host_group['hostid'])){
				$_REQUEST['triggerid'] = 0;
			}
		}
		else{
//			$_REQUEST['triggerid'] = 0;
		}
	}

	$params=array();
	$options = array('only_current_node','not_proxy_hosts');
	foreach($options as $option) $params[$option] = 1;

	$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $PAGE_GROUPS['selected'], $params);

	validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS);

	$available_groups = $PAGE_GROUPS['groupids'];
	$available_hosts = $PAGE_HOSTS['hostids'];

?>
<?php

	$form = new CForm();
	$form->setMethod('get');

// Config
	$cmbConf = new CComboBox('config','triggers.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConf->addItem('templates.php',S_TEMPLATES);
		$cmbConf->addItem('hosts.php',S_HOSTS);
		$cmbConf->addItem('items.php',S_ITEMS);
		$cmbConf->addItem('triggers.php',S_TRIGGERS);
		$cmbConf->addItem('graphs.php',S_GRAPHS);
		$cmbConf->addItem('applications.php',S_APPLICATIONS);

	$form->addItem($cmbConf);
	if(!isset($_REQUEST['form'])){
		$form->addItem(new CButton('form', S_CREATE_TRIGGER));
	}

	show_table_header(S_CONFIGURATION_OF_TRIGGERS_BIG, $form);
	echo SBR;
?>
<?php
	if(($_REQUEST['go'] == 'massupdate') && isset($_REQUEST['g_triggerid'])){
		insert_mass_update_trigger_form();
	}
	else if(isset($_REQUEST['form'])){
		insert_trigger_form();
	}
	else if(($_REQUEST['go'] == 'copy_to') && isset($_REQUEST['g_triggerid'])){
		insert_copy_elements_to_forms('g_triggerid');
	}
	else{
/* TABLE */
		$triggers_wdgt = new CWidget();

// Triggers Header
		$r_form = new CForm();
		$r_form->setMethod('get');

		$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
		$cmbHosts = new CComboBox('hostid',$PAGE_HOSTS['selected'],'javascript: submit();');

		foreach($PAGE_GROUPS['groups'] as $groupid => $name){
			$cmbGroups->addItem($groupid, $name);
		}
		foreach($PAGE_HOSTS['hosts'] as $hostid => $name){
			$cmbHosts->addItem($hostid, $name);
		}

		$r_form->addItem(array(S_GROUP.SPACE,$cmbGroups));
		$r_form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));

		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');

		$tr_link = new CLink($showdisabled?S_HIDE_DISABLED_TRIGGERS : S_SHOW_DISABLED_TRIGGERS,'triggers.php?showdisabled='.($showdisabled?0:1));

		$triggers_wdgt->addHeader(S_TRIGGERS_BIG, $r_form);
		$triggers_wdgt->addHeader($numrows, array('[ ',$tr_link,' ]'));
// ----------------


// Header Host
		if($PAGE_HOSTS['selected'] > 0){
			$tbl_header_host = get_header_host_table($PAGE_HOSTS['selected'], array('items', 'applications', 'graphs'));
			$triggers_wdgt->addItem($tbl_header_host);
		}

		$form = new CForm('triggers.php');
		$form->setName('triggers');
		$form->setMethod('post');
		$form->addVar('hostid', $_REQUEST['hostid']);

		$table = new CTableInfo(S_NO_TRIGGERS_DEFINED);
		$table->setHeader(array(
			new CCheckBox('all_triggers',NULL,"checkAll('".$form->getName()."','all_triggers','g_triggerid');"),
			make_sorting_header(S_SEVERITY,'priority'),
			make_sorting_header(S_STATUS,'status'),
			($_REQUEST['hostid'] > 0)?NULL:S_HOST,
//			($_REQUEST['hostid'] > 0)?NULL:make_sorting_header(S_HOST,'host'),
			make_sorting_header(S_NAME,'description'),
			S_EXPRESSION,
			S_ERROR));

		$sortfield = getPageSortField('description');
		$sortorder = getPageSortOrder();
		$options = array(
			'editable' => 1,
			'select_hosts' => API_OUTPUT_EXTEND,
			'select_items' => API_OUTPUT_EXTEND,
			'select_functions' => API_OUTPUT_EXTEND,
			'select_dependencies' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);

		if($showdisabled == 0){
		    $options['status'] = TRIGGER_STATUS_ENABLED;
		}

		if(($PAGE_HOSTS['selected'] > 0) || empty($PAGE_HOSTS['hostids'])){
			$options['hostids'] = $PAGE_HOSTS['selected'];
		}
		if(($PAGE_GROUPS['selected'] > 0) || empty($PAGE_GROUPS['groupids'])){
			$options['groupids'] = $PAGE_GROUPS['selected'];
		}

		$triggers = CTrigger::get($options);

// sorting && paginf
		order_result($triggers, $sortfield, $sortorder);
		$paging = getPagingLine($triggers);
//---------

		$realHosts = getParentHostsByTriggers($triggers);

		foreach($triggers as $tnum => $trigger){
			$triggerid = $trigger['triggerid'];

			$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');
			$trigger['items'] = zbx_toHash($trigger['items'], 'itemid');
			$trigger['functions'] = zbx_toHash($trigger['functions'], 'functionid');

			$description = array();
			if($trigger['templateid'] > 0){
				if(!isset($realHosts[$triggerid])){
					$description[] = new CSpan('HOST','unknown');
					$description[] = ':';
				}
				else{
					$real_hosts = $realHosts[$triggerid];
					$real_host = reset($real_hosts);
					$description[] = new CLink($real_host['host'], 'triggers.php?&hostid='.$real_host['hostid'], 'unknown');
					$description[] = ':';
				}
			}

			$description[] = new CLink(expandTriggerDescription($trigger), 'triggers.php?form=update&triggerid='.$triggerid);

//add dependencies{
			$deps = $trigger['dependencies'];
			if(count($deps) > 0){
				$description[] = array(BR(), bold(S_DEPENDS_ON.' : '));
				foreach($deps as $dnum => $dep_trigger) {
					$description[] = BR();

					$hosts = get_hosts_by_triggerid($dep_trigger['triggerid']);
					while($host = DBfetch($hosts)){
						$description[] = $host['host'];
						$description[] = ', ';
					}

					array_pop($description);
					$description[] = ' : ';
					$description[] = expand_trigger_description_by_data($dep_trigger);
				}
			}
// } add dependencies

			if($trigger['status'] != TRIGGER_STATUS_UNKNOWN) $trigger['error'] = '';

			$templated = false;
			foreach($trigger['hosts'] as $hostid => $host){
				$templated |= (HOST_STATUS_TEMPLATE == $host['status']);
			}

			if(!zbx_empty($trigger['error']) && !$templated){
				$error = new CDiv(SPACE,'iconerror');
				$error->setHint($trigger['error'], '', 'on');
			}
			else{
				$error = new CDiv(SPACE,'iconok');
			}

			switch($trigger['priority']){
				case 0: $priority = S_NOT_CLASSIFIED; break;
				case 1: $priority = new CCol(S_INFORMATION, 'information'); break;
				case 2: $priority = new CCol(S_WARNING, 'warning'); break;
				case 3: $priority = new CCol(S_AVERAGE, 'average'); break;
				case 4: $priority = new CCol(S_HIGH, 'high'); break;
				case 5: $priority = new CCol(S_DISASTER, 'disaster'); break;
				default: $priority = $trigger['priority'];
			}

			$status_link = 'triggers.php?go='.(($trigger['status'] == TRIGGER_STATUS_DISABLED) ? 'activate' : 'disable').
				'&g_triggerid%5B%5D='.$triggerid;

			if($trigger['status'] == TRIGGER_STATUS_DISABLED){
				$status = new CLink(S_DISABLED, $status_link, 'disabled');
			}
			else if($trigger['status'] == TRIGGER_STATUS_UNKNOWN){
				$status = new CLink(S_UNKNOWN, $status_link, 'unknown');
			}
			else if($trigger['status'] == TRIGGER_STATUS_ENABLED){
				$status = new CLink(S_ENABLED, $status_link, 'enabled');
			}

			$hosts = null;
			if($_REQUEST['hostid'] == 0){
				$hosts = array();
				foreach($trigger['hosts'] as $hostid => $host){
					if(!empty($hosts)) $hosts[] = ', ';
					$hosts[] = $host['host'];
				}
			}

			$table->addRow(array(
				new CCheckBox('g_triggerid['.$triggerid.']', NULL, NULL, $triggerid),
				$priority,
				$status,
				$hosts,
				$description,
				triggerExpression($trigger,1),
//				explode_exp($trigger['expression'], 1),
				$error
			));

			$triggers[$tnum] = $trigger;
		}

//----- GO ------
		$goBox = new CComboBox('go');
		$goOption = new CComboItem('activate',S_ACTIVATE_SELECTED);
		$goOption->setAttribute('confirm',S_ENABLE_SELECTED_TRIGGERS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('disable',S_DISABLE_SELECTED);
		$goOption->setAttribute('confirm',S_DISABLE_SELECTED_TRIGGERS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('massupdate',S_MASS_UPDATE);
		$goOption->setAttribute('confirm',S_MASS_UPDATE_SELECTED_TRIGGERS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('copy_to',S_COPY_SELECTED_TO);
		$goOption->setAttribute('confirm',S_COPY_SELECTED_TRIGGERS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_TRIGGERS_Q);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO);
		$goButton->setAttribute('id','goButton');

		$jsLocale = array(
			'S_CLOSE',
			'S_NO_ELEMENTS_SELECTED'
		);

		zbx_addJSLocale($jsLocale);

		zbx_add_post_js('chkbxRange.pageGoName = "g_triggerid";');

		$footer = get_table_header(array($goBox, $goButton));
//----

// PAGING FOOTER
		$table = array($paging,$table,$paging,$footer);
//---------

		$form->addItem($table);
		$triggers_wdgt->addItem($form);
		$triggers_wdgt->show();
	}
?>
<?php

include_once('include/page_footer.php');

?>
