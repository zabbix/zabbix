<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of trigger prototypes');
$page['file'] = 'trigger_prototypes.php';
$page['hist_arg'] = array('parent_discoveryid');

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'parent_discoveryid' => array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null),
	'hostid' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'triggerid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'(isset({form})&&({form}=="update"))'),
	'copy_type' =>			array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	'isset({copy})'),
	'copy_mode' =>			array(T_ZBX_INT, O_OPT, P_SYS,	IN('0'),	null),
	'type' =>				array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'description' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})', _('Name')),
	'expression' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})'),
	'priority' =>			array(T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4,5'), 'isset({save})'),
	'comments' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'url' =>				array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'status' =>				array(T_ZBX_STR, O_OPT, null,	null,		null),
	'input_method' =>		array(T_ZBX_INT, O_OPT, null,	NOT_EMPTY,	'isset({toggle_input_method})'),
	'expr_temp' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({add_expression})||isset({and_expression})||isset({or_expression})||isset({replace_expression}))'),
	'expr_target_single' =>	array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({and_expression})||isset({or_expression})||isset({replace_expression}))'),
	'dependencies' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'new_dependence' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID.'{}>0', 'isset({add_dependence})'),
	'rem_dependence' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'g_triggerid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'copy_targetid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'filter_groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({copy})&&(isset({copy_type})&&({copy_type}==0))'),
	'showdisabled' =>		array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	// actions
	'massupdate' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'visible' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'go' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'toggle_input_method' =>array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'add_expression' => 	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'and_expression' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'or_expression' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'replace_expression' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'remove_expression' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'test_expression' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'add_dependence' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'del_dependence' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'group_enable' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'group_disable' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'group_delete' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'copy' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'save' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'mass_save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT, null,	null,		null)
);
$_REQUEST['showdisabled'] = get_request('showdisabled', CProfile::get('web.triggers.showdisabled', 1));

check_fields($fields);
validate_sort_and_sortorder('description', ZBX_SORT_UP);

$_REQUEST['status'] = isset($_REQUEST['status']) ? TRIGGER_STATUS_ENABLED : TRIGGER_STATUS_DISABLED;
$_REQUEST['type'] = isset($_REQUEST['type']) ? TRIGGER_MULT_EVENT_ENABLED : TRIGGER_MULT_EVENT_DISABLED;
$_REQUEST['go'] = get_request('go', 'none');

// validate permissions
if (get_request('parent_discoveryid')) {
	$discovery_rule = API::DiscoveryRule()->get(array(
		'itemids' => $_REQUEST['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'editable' => true,
		'preservekeys' => true
	));
	$discovery_rule = reset($discovery_rule);
	if (!$discovery_rule) {
		access_deny();
	}

	if (isset($_REQUEST['triggerid'])) {
		$triggerPrototype = API::TriggerPrototype()->get(array(
			'triggerids' => $_REQUEST['triggerid'],
			'output' => array('triggerid'),
			'editable' => true,
			'preservekeys' => true
		));
		if (empty($triggerPrototype)) {
			access_deny();
		}
	}
}
else {
	access_deny();
}

$showdisabled = get_request('showdisabled', 0);
CProfile::update('web.triggers.showdisabled', $showdisabled, PROFILE_TYPE_INT);

/*
 * Actions
 */
if (isset($_REQUEST['add_expression'])) {
	$_REQUEST['expression'] = $_REQUEST['expr_temp'];
	$_REQUEST['expr_temp'] = '';
}
elseif (isset($_REQUEST['and_expression'])) {
	$_REQUEST['expr_action'] = '&';
}
elseif (isset($_REQUEST['or_expression'])) {
	$_REQUEST['expr_action'] = '|';
}
elseif (isset($_REQUEST['replace_expression'])) {
	$_REQUEST['expr_action'] = 'r';
}
elseif (isset($_REQUEST['remove_expression']) && zbx_strlen($_REQUEST['remove_expression'])) {
	$_REQUEST['expr_action'] = 'R';
	$_REQUEST['expr_target_single'] = $_REQUEST['remove_expression'];
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['triggerid'])) {
	unset($_REQUEST['triggerid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	$trigger = array(
		'expression' => $_REQUEST['expression'],
		'description' => $_REQUEST['description'],
		'type' => $_REQUEST['type'],
		'priority' => $_REQUEST['priority'],
		'status' => $_REQUEST['status'],
		'comments' => $_REQUEST['comments'],
		'url' => $_REQUEST['url'],
		'flags' => ZBX_FLAG_DISCOVERY_CHILD
	);

	if (isset($_REQUEST['triggerid'])) {
		$trigger['triggerid'] = $_REQUEST['triggerid'];
		$result = API::TriggerPrototype()->update($trigger);

		show_messages($result, _('Trigger updated'), _('Cannot update trigger'));
	}
	else {
		$result = API::TriggerPrototype()->create($trigger);

		show_messages($result, _('Trigger added'), _('Cannot add trigger'));
	}
	if ($result) {
		unset($_REQUEST['form']);
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['triggerid'])) {
	$result = API::TriggerPrototype()->delete($_REQUEST['triggerid']);

	show_messages($result, _('Trigger deleted'), _('Cannot delete trigger'));
	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['triggerid']);
	}
}
elseif ($_REQUEST['go'] == 'massupdate' && isset($_REQUEST['mass_save']) && isset($_REQUEST['g_triggerid'])) {
	$visible = get_request('visible');

	if (isset($visible['priority'])) {
		$priority = get_request('priority');

		foreach ($_REQUEST['g_triggerid'] as $triggerid) {
			$result = API::TriggerPrototype()->update(array(
				'triggerid' => $triggerid,
				'priority' => $priority
			));
			if (!$result) {
				break;
			}
		}
	}
	else {
		$result = true;
	}

	show_messages($result, _('Trigger updated'), _('Cannot update trigger'));
	if ($result) {
		unset($_REQUEST['massupdate'], $_REQUEST['form']);
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
	$triggers = API::TriggerPrototype()->get(array(
		'triggerids' => $_REQUEST['g_triggerid'],
		'editable' => true,
		'output' => array('triggerid', 'status'),
		'preservekeys' => true
	));

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
		$triggers = API::TriggerPrototype()->get($options);

		$childTriggerIds = array_keys($triggers);

		foreach ($triggers as $triggerid => $trigger) {
			if ($trigger['status'] != $status) {
				$triggerIdsToUpdate[] = $triggerid;
			}
		}
	} while (!empty($childTriggerIds));

	DB::update('triggers', array(
		'values' => array('status' => $status),
		'where' => array('triggerid' => $triggerIdsToUpdate)
	));

	// get updated triggers with additional data
	$options = array(
		'triggerids' => $triggerIdsToUpdate,
		'output' => array('triggerid', 'description'),
		'preservekeys' => true,
		'selectHosts' => API_OUTPUT_EXTEND,
		'nopermissions' => true
	);
	$triggers = API::TriggerPrototype()->get($options);
	foreach ($triggers as $triggerid => $trigger) {
		$host = reset($trigger['hosts']);
		add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER_PROTOTYPE, $triggerid,
			$host['host'].':'.$trigger['description'], 'triggers', $statusOld, $statusNew);
	}

	$go_result = DBend($go_result);
	show_messages($go_result, _('Status updated'), _('Cannot update status'));
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['g_triggerid'])) {
	$go_result = API::TriggerPrototype()->delete($_REQUEST['g_triggerid']);
	show_messages($go_result, _('Triggers deleted'), _('Cannot delete triggers'));
}

if ($_REQUEST['go'] != 'none' && !empty($go_result)) {
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray(\''.$path.'\')');
	$_REQUEST['go'] = 'none';
}

/*
 * Display
 */
if ($_REQUEST['go'] == 'massupdate' && isset($_REQUEST['g_triggerid'])) {
	$triggersView = new CView('configuration.triggers.massupdate', getTriggerMassupdateFormData());
	$triggersView->render();
	$triggersView->show();
}
elseif (isset($_REQUEST['form'])) {
	$triggersView = new CView('configuration.triggers.edit', getTriggerFormData());
	$triggersView->render();
	$triggersView->show();
}
else {
	$data = array(
		'parent_discoveryid' => get_request('parent_discoveryid'),
		'discovery_rule' => $discovery_rule,
		'hostid' => get_request('hostid'),
		'showdisabled' => get_request('showdisabled', 1),
		'triggers' => array()
	);
	CProfile::update('web.triggers.showdisabled', $data['showdisabled'], PROFILE_TYPE_INT);

	// get triggers
	$sortfield = getPageSortField('description');
	$options = array(
		'editable' => true,
		'output' => array('triggerid'),
		'discoveryids' => $data['parent_discoveryid'],
		'sortfield' => $sortfield,
		'limit' => $config['search_limit'] + 1
	);
	if (empty($data['showdisabled'])) {
		$options['filter']['status'] = TRIGGER_STATUS_ENABLED;
	}
	$data['triggers'] = API::TriggerPrototype()->get($options);
	$data['paging'] = getPagingLine($data['triggers']);

	$data['triggers'] = API::TriggerPrototype()->get(array(
		'triggerids' => zbx_objectValues($data['triggers'], 'triggerid'),
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => API_OUTPUT_EXTEND,
		'selectItems' => API_OUTPUT_EXTEND,
		'selectFunctions' => API_OUTPUT_EXTEND
	));
	order_result($data['triggers'], $sortfield, getPageSortOrder());

	// get real hosts
	$data['realHosts'] = getParentHostsByTriggers($data['triggers']);

	// render view
	$triggersView = new CView('configuration.triggers.list', $data);
	$triggersView->render();
	$triggersView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
