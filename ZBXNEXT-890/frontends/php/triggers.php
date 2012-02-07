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
	if(get_request('triggerid', false)){
		$options = array(
			'triggerids' => $_REQUEST['triggerid'],
			'editable' => 1,
		);
		$triggers = CTrigger::get($options);
		if(empty($triggers)) access_deny();
	}
	else if(get_request('hostid', 0) > 0){
		$options = array(
			'hostids' => $_REQUEST['hostid'],
			'extendoutput' => 1,
			'templated_hosts' => 1,
			'editable' => 1
		);
		$hosts = CHost::get($options);
		if(empty($hosts)) access_deny();
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

		if(!check_right_on_trigger_by_expression(PERM_READ_WRITE, $_REQUEST['expression'])){
			if(isset($_REQUEST['triggerid']))
				show_messages(false, null, S_CANNOT_UPDATE_TRIGGER);
			else
				show_messages(false, null, S_CANNOT_ADD_TRIGGER);
		}
		else{
			$status = isset($_REQUEST['status'])?TRIGGER_STATUS_DISABLED:TRIGGER_STATUS_ENABLED;
			$deps = get_request('dependencies',array());

			if(isset($_REQUEST['triggerid'])){
				$triggerData = get_trigger_by_triggerid($_REQUEST['triggerid']);
				if($triggerData['templateid']){
					$_REQUEST['description'] = $triggerData['description'];
					$_REQUEST['expression'] = explode_exp($triggerData['expression']);
				}

				$current_deps = get_trigger_dependencies_by_triggerid($_REQUEST['triggerid']);
				sort($deps);
				sort($current_deps);
				if($deps == $current_deps){
					$deps = null;
				}

				$type = get_request('type');
				$priority = get_request('priority');
				$comments = get_request('comments');
				$url = get_request('url');
				if($triggerData['type'] == $_REQUEST['type']) $type = null;
				if($triggerData['priority'] == $_REQUEST['priority']) $priority = null;
				if(strcmp($triggerData['comments'], $_REQUEST['comments']) == 0) $comments = null;
				if(strcmp($triggerData['url'], $_REQUEST['url']) == 0) $url = null;
				if($triggerData['status'] == $status) $status = null;

				DBstart();

				$result = update_trigger($_REQUEST['triggerid'],
					$_REQUEST['expression'],$_REQUEST['description'],$type,
					$priority,$status,$comments,$url,
					$deps, $triggerData['templateid']);
				$result = DBend($result);

				$triggerid = $_REQUEST['triggerid'];

				show_messages($result, S_TRIGGER_UPDATED, S_CANNOT_UPDATE_TRIGGER);
			}
			else {
				DBstart();
				$triggerid = add_trigger($_REQUEST['expression'],$_REQUEST['description'],$_REQUEST['type'],
					$_REQUEST['priority'],$status,$_REQUEST['comments'],$_REQUEST['url'],
					$deps);
				$result = DBend($triggerid);
				show_messages($result, S_TRIGGER_ADDED, S_CANNOT_ADD_TRIGGER);
				if($result) $_REQUEST['triggerid'] = $triggerid;
			}

			if($result)
				unset($_REQUEST['form']);
		}
	}
	else if(isset($_REQUEST['delete'])&&isset($_REQUEST['triggerid'])){
		$result = false;

		$options = array(
			'triggerids'=> $_REQUEST['triggerid'],
			'editable'=> 1,
			'select_hosts'=> API_OUTPUT_EXTEND,
			'output'=> API_OUTPUT_EXTEND,
		);
		$triggers = CTrigger::get($options);

		if($triggerData = reset($triggers)){
			$host = reset($triggerData['hosts']);

			DBstart();
			$result = CTrigger::delete($triggerData['triggerid']);
			$result = DBend($result);
			if($result){
				add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TRIGGER, $_REQUEST['triggerid'], $host['host'].':'.$triggerData['description'], NULL, NULL, NULL);
			}
		}

		show_messages($result, S_TRIGGER_DELETED, S_CANNOT_DELETE_TRIGGER);

		if($result){
			unset($_REQUEST['form']);
			unset($_REQUEST['triggerid']);
		}
	}
// DEPENDENCE ACTIONS
	else if(isset($_REQUEST['add_dependence']) && isset($_REQUEST['new_dependence'])){
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
	elseif (str_in_array($_REQUEST['go'], array('activate', 'disable')) && isset($_REQUEST['g_triggerid'])) {
		$go_result = true;

		if ($_REQUEST['go'] == 'activate') {
			$status = TRIGGER_STATUS_ENABLED;
			$statusOld = array('status' => TRIGGER_STATUS_DISABLED);
			$statusNew = array('status' => TRIGGER_STATUS_ENABLED);
		}
		else {
			$status = TRIGGER_STATUS_DISABLED;
			$statusOld = array('status' => TRIGGER_STATUS_ENABLED);
			$statusNew = array('status' => TRIGGER_STATUS_DISABLED);
		}

		DBstart();

		// get requested triggers with permission check
		$options = array(
			'triggerids' => $_REQUEST['g_triggerid'],
			'editable' => true,
			'output' => array('triggerid', 'status'),
			'preservekeys' => true
		);
		$triggers = CTrigger::get($options);

		// triggerids to gather child triggers
		$childTriggerIds = array_keys($triggers);

		// triggerids which status must be changed
		$triggerIdsToUpdate = array();
		foreach ($triggers as $triggerid => $trigger){
			if ($trigger['status'] != $status) {
				$triggerIdsToUpdate[] = $triggerid;
			}
		}


		do {
			// gather all triggerids which status should be changed including child triggers
			$options = array(
				'filter' => array('templateid' => $childTriggerIds),
				'output' => array('triggerid', 'status'),
				'preservekeys' => true,
				'nopermissions' => true
			);
			$triggers = CTrigger::get($options);

			$childTriggerIds = array_keys($triggers);

			foreach ($triggers as $triggerid => $trigger) {
				if ($trigger['status'] != $status) {
					$triggerIdsToUpdate[] = $triggerid;
				}
			}

		} while (!empty($childTriggerIds));

		$sql = 'UPDATE triggers SET status='.$status.' WHERE '.DBcondition('triggerid', $triggerIdsToUpdate);
		DBexecute($sql);

		// if disable trigger, unknown event must be created
		if ($status != TRIGGER_STATUS_ENABLED) {
			addEvent($triggerIdsToUpdate, TRIGGER_VALUE_UNKNOWN);
			$sql = 'UPDATE triggers SET lastchange='.time().', value='.TRIGGER_VALUE_UNKNOWN.
					' WHERE '.DBcondition('triggerid', $triggerIdsToUpdate).' AND value<>'.TRIGGER_VALUE_UNKNOWN;
			DBexecute($sql);
		}


		// get updated triggers with additional data
		$options = array(
			'triggerids' => $triggerIdsToUpdate,
			'output' => array('triggerid', 'description'),
			'preservekeys' => true,
			'select_hosts' => API_OUTPUT_EXTEND,
			'nopermissions' => true
		);
		$triggers = CTrigger::get($options);
		foreach ($triggers as $triggerid => $trigger) {
			$servStatus = (isset($_REQUEST['activate'])) ? get_service_status_of_trigger($triggerid) : 0;

			// updating status to all services by the dependency
			update_services($trigger['triggerid'], $servStatus);

			$host = reset($trigger['hosts']);
			add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER, $triggerid,
				$host['host'].':'.$trigger['description'], 'triggers', $statusOld, $statusNew);
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
			$newTriggerIds = array();

			DBstart();
			foreach($hosts_ids as $host_id){
				foreach($_REQUEST['g_triggerid'] as $trigger_id){
					$newtrigid = copy_trigger_to_host($trigger_id, $host_id, true);
					$newTriggerIds[$trigger_id] = $newtrigid;
					$go_result |= (bool) $newtrigid;
				}
				if ($go_result) {
					// copy trigger dependencies
					foreach ($_REQUEST['g_triggerid'] as $trigger_id) {
						$dependencies = get_trigger_dependencies_by_triggerid($trigger_id);
						foreach ($dependencies as $depTriggerId) {
							// if the trigger we just copied is dependant on another trigger we copied,
							// map the dependency to the new trigger
							if (isset($newTriggerIds[$depTriggerId])) {
								$depTriggerId = $newTriggerIds[$depTriggerId];
							}

							add_trigger_dependency($newTriggerIds[$trigger_id], $depTriggerId);
						}
					}
				}
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
		DBstart();

		$triggerids = array();
		$options = array(
			'triggerids' => $_REQUEST['g_triggerid'],
			'editable'=>1,
			'select_hosts' => API_OUTPUT_EXTEND,
			'output'=>API_OUTPUT_EXTEND,
			'expandDescription' => 1
		);
		$triggers = CTrigger::get($options);

		foreach($triggers as $tnum => $trigger){
			if($trigger['templateid'] != 0){
				unset($triggers[$tnum]);
				error(S_CANNOT_DELETE_TRIGGER.' [ '.$trigger['description'].' ] ('.S_TEMPLATED_TRIGGER.')');
				continue;
			}

			$triggerids[] = $trigger['triggerid'];
			$host = reset($trigger['hosts']);

			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_TRIGGER, $trigger['triggerid'], $host['host'].':'.$trigger['description'], NULL, NULL, NULL);
		}

		$go_result = !empty($triggerids);
		if($go_result) $go_result = CTrigger::delete($triggerids);

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

	$options = array(
		'groups' => array('not_proxy_hosts' => 1, 'editable' => 1),
		'hosts' => array('templated_hosts' => 1, 'editable' => 1),
		'triggers' => array('editable' => 1),
		'groupid' => get_request('groupid', null),
		'hostid' => get_request('hostid', null),
		'triggerid' => get_request('triggerid', null),
	);
	$pageFilter = new CPageFilter($options);
	$_REQUEST['groupid'] = $pageFilter->groupid;
	$_REQUEST['hostid'] = $pageFilter->hostid;

	if($pageFilter->triggerid > 0){
		$_REQUEST['triggerid'] = $pageFilter->triggerid;
	}

?>
<?php
	$triggers_wdgt = new CWidget();

	$form = new CForm(null, 'get');

// Config
	if(!isset($_REQUEST['form'])){
		$form->addItem(new CButton('form', S_CREATE_TRIGGER));
	}

	$triggers_wdgt->addPageHeader(S_CONFIGURATION_OF_TRIGGERS_BIG, $form);
?>
<?php
	if(($_REQUEST['go'] == 'massupdate') && isset($_REQUEST['g_triggerid'])){
		$triggers_wdgt->addItem(insert_mass_update_trigger_form());
	}
	else if(isset($_REQUEST['form'])){
		$triggers_wdgt->addItem(insert_trigger_form());
	}
	else if(($_REQUEST['go'] == 'copy_to') && isset($_REQUEST['g_triggerid'])){
		$triggers_wdgt->addItem(insert_copy_elements_to_forms('g_triggerid'));
	}
	else{
/* TABLE */

// Triggers Header
		$r_form = new CForm(null, 'get');

		$r_form->addItem(array(S_GROUP.SPACE,$pageFilter->getGroupsCB()));
		$r_form->addItem(array(SPACE.S_HOST.SPACE,$pageFilter->getHostsCB()));

		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');

		$tr_link = new CLink($showdisabled?S_HIDE_DISABLED_TRIGGERS : S_SHOW_DISABLED_TRIGGERS,'triggers.php?showdisabled='.($showdisabled?0:1));

		$triggers_wdgt->addHeader(S_TRIGGERS_BIG, $r_form);
		$triggers_wdgt->addHeader($numrows, array('[ ',$tr_link,' ]'));
// ----------------

		$form = new CForm('triggers.php', 'post');
		$table = new CTableInfo(S_NO_TRIGGERS_DEFINED);

// Header Host
		if($_REQUEST['hostid'] > 0){
			$tbl_header_host = get_header_host_table($_REQUEST['hostid'], array('items', 'applications', 'graphs'));
			$triggers_wdgt->addItem($tbl_header_host);
		}

		$form->setName('triggers');
		$form->addVar('hostid', $_REQUEST['hostid']);

		$table->setHeader(array(
			new CCheckBox('all_triggers',NULL,"checkAll('".$form->getName()."','all_triggers','g_triggerid');"),
			make_sorting_header(S_SEVERITY,'priority'),
			make_sorting_header(S_STATUS,'status'),
			($_REQUEST['hostid'] > 0)?NULL:S_HOST,
			make_sorting_header(S_NAME,'description'),
			S_EXPRESSION,
			S_ERROR
		));
// get Triggers
		$triggers = array();

		$sortfield = getPageSortField('description');
		$sortorder = getPageSortOrder();

		if($pageFilter->hostsSelected){
			$options = array(
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'filter' => array(),
				'sortfield' => $sortfield,
				'sortorder' => $sortorder,
				'limit' => ($config['search_limit']+1)
			);

			if($showdisabled == 0) $options['filter']['status'] = TRIGGER_STATUS_ENABLED;

			if($pageFilter->hostid > 0) $options['hostids'] = $pageFilter->hostid;
			else if($pageFilter->groupid > 0) $options['groupids'] = $pageFilter->groupid;


			$triggers = CTrigger::get($options);
		}

// sorting && paging
		order_result($triggers, $sortfield, $sortorder);
		$paging = getPagingLine($triggers);
//---

		$options = array(
			'triggerids' => zbx_objectValues($triggers, 'triggerid'),
			'output' => API_OUTPUT_EXTEND,
			'select_hosts' => API_OUTPUT_EXTEND,
			'select_items' => API_OUTPUT_EXTEND,
			'select_functions' => API_OUTPUT_EXTEND,
			'select_dependencies' => API_OUTPUT_EXTEND,
		);

		$triggers = CTrigger::get($options);
		order_result($triggers, $sortfield, $sortorder);

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

			$description[] = new CLink($trigger['description'], 'triggers.php?form=update&triggerid='.$triggerid);

//add dependencies {
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

			if($trigger['value'] != TRIGGER_VALUE_UNKNOWN) $trigger['error'] = '';

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
		//$goOption->setAttribute('confirm',S_MASS_UPDATE_SELECTED_TRIGGERS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('copy_to',S_COPY_SELECTED_TO);
		//$goOption->setAttribute('confirm',S_COPY_SELECTED_TRIGGERS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_TRIGGERS_Q);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO);
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "g_triggerid";');

		$footer = get_table_header(array($goBox, $goButton));

		$table = array($paging,$table,$paging,$footer);

		$form->addItem($table);
		$triggers_wdgt->addItem($form);
	}

	$triggers_wdgt->show();
?>
<?php

include_once('include/page_footer.php');

?>
