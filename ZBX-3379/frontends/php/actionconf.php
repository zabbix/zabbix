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
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of actions');
$page['file'] = 'actionconf.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'actionid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'name' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})', _('Name')),
	'eventsource' =>		array(T_ZBX_INT, O_MAND, null,	IN(array(EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTO_REGISTRATION)), null),
	'evaltype' =>			array(T_ZBX_INT, O_OPT, null,	IN(array(ACTION_EVAL_TYPE_AND_OR, ACTION_EVAL_TYPE_AND, ACTION_EVAL_TYPE_OR)), 'isset({save})'),
	'esc_period' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(60, 999999), null, _('Default operation step duration')),
	'status' =>				array(T_ZBX_INT, O_OPT, null,	IN(array(ACTION_STATUS_ENABLED, ACTION_STATUS_DISABLED)), null),
	'def_shortdata' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'def_longdata' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'recovery_msg' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'r_shortdata' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({recovery_msg})&&isset({save})', _('Recovery subject')),
	'r_longdata' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({recovery_msg})&&isset({save})', _('Recovery message')),
	'g_actionid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'conditions' =>			array(null,		O_OPT,	null,	null,		null),
	'new_condition' =>		array(null,		O_OPT,	null,	null,		'isset({add_condition})'),
	'operations' =>			array(null,		O_OPT,	null,	null,		'isset({save})'),
	'edit_operationid' =>	array(null,		O_OPT,	P_ACT,	DB_ID,		null),
	'new_operation' =>		array(null,		O_OPT,	null,	null,		'isset({add_operation})'),
	'opconditions' =>		array(null,		O_OPT,	null,	null,		null),
	'new_opcondition' =>	array(null,		O_OPT,	null,	null,		'isset({add_opcondition})'),
	// actions
	'go' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'add_condition' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel_new_condition' => array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'add_operation' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel_new_operation' => array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'add_opcondition' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel_new_opcondition' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'save' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	// ajax
	'favobj' =>				array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>				array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>			array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})&&("filter"=={favobj})')
);
$_REQUEST['eventsource'] = get_request('eventsource', CProfile::get('web.actionconf.eventsource', EVENT_SOURCE_TRIGGERS));

check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

$_REQUEST['go'] = get_request('go', 'none');

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'filter') {
		CProfile::update('web.audit.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
}
if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}
if (isset($_REQUEST['actionid'])) {
	$actionPermissions = API::Action()->get(array(
		'actionids' => $_REQUEST['actionid'],
		'editable' => true
	));
	if (empty($actionPermissions)) {
		access_deny();
	}
}

CProfile::update('web.actionconf.eventsource', $_REQUEST['eventsource'], PROFILE_TYPE_INT);

/*
 * Actions
 */
if (isset($_REQUEST['clone']) && isset($_REQUEST['actionid'])) {
	unset($_REQUEST['actionid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['cancel_new_operation'])) {
	unset($_REQUEST['new_operation']);
}
elseif (isset($_REQUEST['cancel_new_opcondition'])) {
	unset($_REQUEST['new_opcondition']);
}
elseif (isset($_REQUEST['save'])) {
	if (!count(get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
		access_deny();
	}

	$action = array(
		'name'		=> get_request('name'),
		'eventsource'	=> get_request('eventsource', 0),
		'evaltype'	=> get_request('evaltype', 0),
		'status'	=> get_request('status', ACTION_STATUS_DISABLED),
		'esc_period'	=> get_request('esc_period', 0),
		'def_shortdata'	=> get_request('def_shortdata', ''),
		'def_longdata'	=> get_request('def_longdata', ''),
		'recovery_msg'	=> get_request('recovery_msg', 0),
		'r_shortdata'	=> get_request('r_shortdata', ''),
		'r_longdata'	=> get_request('r_longdata', ''),
		'conditions'	=> get_request('conditions', array()),
		'operations'	=> get_request('operations', array())
	);

	foreach ($action['operations'] as $num => $operation) {
		if (isset($operation['opmessage']) && !isset($operation['opmessage']['default_msg'])) {
			$action['operations'][$num]['opmessage']['default_msg'] = 0;
		}
	}
	DBstart();
	if (isset($_REQUEST['actionid'])) {
		$action['actionid']= $_REQUEST['actionid'];

		$result = API::Action()->update($action);
		show_messages($result, _('Action updated'), _('Cannot update action'));
	}
	else {
		$result = API::Action()->create($action);
		show_messages($result, _('Action added'), _('Cannot add action'));
	}

	$result = DBend($result);
	if ($result) {
		add_audit(
			!isset($_REQUEST['actionid']) ? AUDIT_ACTION_ADD : AUDIT_ACTION_UPDATE,
			AUDIT_RESOURCE_ACTION,
			_('Name').': '.$_REQUEST['name']
		);

		unset($_REQUEST['form']);
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['actionid'])) {
	if (!count(get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
		access_deny();
	}

	$result = API::Action()->delete($_REQUEST['actionid']);

	show_messages($result, _('Action deleted'), _('Cannot delete action'));
	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['actionid']);
	}
}
elseif (isset($_REQUEST['add_condition']) && isset($_REQUEST['new_condition'])) {
	$new_condition = $_REQUEST['new_condition'];

	try {
		CAction::validateConditions($new_condition);
		$_REQUEST['conditions'] = get_request('conditions', array());

		$exists = false;
		foreach ($_REQUEST['conditions'] as $condition) {
			if (($new_condition['conditiontype'] === $condition['conditiontype'])
					&& ($new_condition['operator'] === $condition['operator'])
					&& (!isset($new_condition['value']) || $new_condition['value'] === $condition['value'])) {
				$exists = true;
				break;
			}
		}

		if (!$exists) {
			array_push($_REQUEST['conditions'], $new_condition);
		}
	}
	catch (APIException $e) {
		error($e->getMessage());
	}
}
elseif (isset($_REQUEST['add_opcondition']) && isset($_REQUEST['new_opcondition'])) {
	$new_opcondition = $_REQUEST['new_opcondition'];

	try {
		CAction::validateOperationConditions($new_opcondition);
		$new_operation = get_request('new_operation', array());

		if (!isset($new_operation['opconditions'])) {
			$new_operation['opconditions'] = array();
		}
		if (!str_in_array($new_opcondition, $new_operation['opconditions'])) {
			array_push($new_operation['opconditions'], $new_opcondition);
		}

		$_REQUEST['new_operation'] = $new_operation;

		unset($_REQUEST['new_opcondition']);
	}
	catch (APIException $e) {
		error($e->getMessage());
	}
}
elseif (isset($_REQUEST['add_operation']) && isset($_REQUEST['new_operation'])) {
	$new_operation = $_REQUEST['new_operation'];
	$result = true;

	if (API::Action()->validateOperations($new_operation)) {
		$_REQUEST['operations'] = get_request('operations', array());

		$uniqOperations = array(
			OPERATION_TYPE_HOST_ADD => 0,
			OPERATION_TYPE_HOST_REMOVE => 0,
			OPERATION_TYPE_HOST_ENABLE => 0,
			OPERATION_TYPE_HOST_DISABLE => 0
		);
		if (isset($uniqOperations[$new_operation['operationtype']])) {
			foreach ($_REQUEST['operations'] as $operation) {
				if (isset($uniqOperations[$operation['operationtype']])) {
					$uniqOperations[$operation['operationtype']]++;
				}
			}
			if ($uniqOperations[$new_operation['operationtype']]) {
				$result = false;
				info(_s('Operation "%s" already exists.', operation_type2str($new_operation['operationtype'])));
				show_messages();
			}
		}

		if ($result) {
			if (isset($new_operation['id'])) {
				$_REQUEST['operations'][$new_operation['id']] = $new_operation;
			}
			else {
				$_REQUEST['operations'][] = $new_operation;
				sortOperations($_REQUEST['eventsource'], $_REQUEST['operations']);
			}
		}

		unset($_REQUEST['new_operation']);
	}
}
elseif (isset($_REQUEST['edit_operationid'])) {
	$_REQUEST['edit_operationid'] = array_keys($_REQUEST['edit_operationid']);
	$edit_operationid = $_REQUEST['edit_operationid'] = array_pop($_REQUEST['edit_operationid']);
	$_REQUEST['operations'] = get_request('operations', array());

	if (isset($_REQUEST['operations'][$edit_operationid])) {
		$_REQUEST['new_operation'] = $_REQUEST['operations'][$edit_operationid];
		$_REQUEST['new_operation']['id'] = $edit_operationid;
		$_REQUEST['new_operation']['action'] = 'update';
	}
}
elseif (str_in_array($_REQUEST['go'], array('activate', 'disable')) && isset($_REQUEST['g_actionid'])) {
	if (!count($nodes = get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
		access_deny();
	}

	$status = ($_REQUEST['go'] == 'activate') ? 0 : 1;
	$status_name = $status ? 'disabled' : 'enabled';

	DBstart();
	$actionids = array();

	$go_result = DBselect(
		'SELECT DISTINCT a.actionid'.
		' FROM actions a'.
		' WHERE '.DBin_node('a.actionid', $nodes).
			' AND '.dbConditionInt('a.actionid', $_REQUEST['g_actionid'])
	);
	while ($row = DBfetch($go_result)) {
		$res = DBexecute('UPDATE actions SET status='.$status.' WHERE actionid='.$row['actionid']);
		if ($res) {
			$actionids[] = $row['actionid'];
		}
	}
	$go_result = DBend($res);

	if ($go_result && isset($res)) {
		show_messages($go_result, _('Status updated'), _('Cannot update status'));
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ACTION, ' Actions ['.implode(',', $actionids).'] '.$status_name);
	}
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['g_actionid'])) {
	if (!count($nodes = get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
		access_deny();
	}

	$go_result = API::Action()->delete($_REQUEST['g_actionid']);
	show_messages($go_result, _('Selected actions deleted'), _('Cannot delete selected actions'));
}

if ($_REQUEST['go'] != 'none' && isset($go_result) && $go_result) {
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray("'.$path.'")');
}

/*
 * Display
 */
show_messages();

if (isset($_REQUEST['form'])) {
	$data = array(
		'form' => get_request('form'),
		'form_refresh' => get_request('form_refresh', 0),
		'actionid' => get_request('actionid'),
		'eventsource' => get_request('eventsource'),
		'new_condition' => get_request('new_condition', array()),
		'new_operation' => get_request('new_operation', null)
	);

	$action = null;
	if (!empty($data['actionid'])) {
		$data['action'] = API::Action()->get(array(
			'actionids' => $data['actionid'],
			'selectOperations' => API_OUTPUT_EXTEND,
			'selectConditions' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
			'editable' => true
		));
		$data['action'] = reset($data['action']);
	}
	else {
		$data['eventsource'] = get_request('eventsource');
		$data['evaltype'] = get_request('evaltype');
		$data['esc_period'] = get_request('esc_period');
	}

	if (isset($data['action']['actionid']) && !isset($_REQUEST['form_refresh'])) {
		sortOperations($data['action']['eventsource'], $data['action']['operations']);
	}
	else {
		$data['action']['name'] = get_request('name');
		$data['action']['eventsource'] = get_request('eventsource');
		$data['action']['evaltype'] = get_request('evaltype', 0);
		$data['action']['esc_period'] = get_request('esc_period', SEC_PER_HOUR);
		$data['action']['status'] = get_request('status', isset($_REQUEST['form_refresh']) ? 1 : 0);
		$data['action']['recovery_msg'] = get_request('recovery_msg', 0);
		$data['action']['r_shortdata'] = get_request('r_shortdata', ACTION_DEFAULT_SUBJ_TRIGGER);
		$data['action']['r_longdata'] = get_request('r_longdata', ACTION_DEFAULT_MSG_TRIGGER);
		$data['action']['conditions'] = get_request('conditions', array());
		$data['action']['operations'] = get_request('operations', array());

		if (!empty($data['actionid']) && isset($_REQUEST['form_refresh'])) {
			$data['action']['def_shortdata'] = get_request('def_shortdata');
			$data['action']['def_longdata'] = get_request('def_longdata');
		}
		else {
			if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
				$data['action']['def_shortdata'] = get_request('def_shortdata', ACTION_DEFAULT_SUBJ_TRIGGER);
				$data['action']['def_longdata'] = get_request('def_longdata', ACTION_DEFAULT_MSG_TRIGGER);
			}
			elseif ($data['eventsource'] == EVENT_SOURCE_DISCOVERY) {
				$data['action']['def_shortdata'] = get_request('def_shortdata', ACTION_DEFAULT_SUBJ_DISCOVERY);
				$data['action']['def_longdata'] = get_request('def_longdata', ACTION_DEFAULT_MSG_DISCOVERY);
			}
			elseif ($data['eventsource'] == EVENT_SOURCE_AUTO_REGISTRATION) {
				$data['action']['def_shortdata'] = get_request('def_shortdata', ACTION_DEFAULT_SUBJ_AUTOREG);
				$data['action']['def_longdata'] = get_request('def_longdata', ACTION_DEFAULT_MSG_AUTOREG);
			}
		}
	}

	if (empty($data['action']['actionid']) && !isset($_REQUEST['form_refresh'])) {
		$data['action']['conditions'] = array(
			array(
				'conditiontype' => CONDITION_TYPE_TRIGGER_VALUE,
				'operator' => CONDITION_OPERATOR_EQUAL,
				'value' => TRIGGER_VALUE_TRUE
			),
			array(
				'conditiontype' => CONDITION_TYPE_MAINTENANCE,
				'operator' => CONDITION_OPERATOR_NOT_IN,
				'value' => ''
			)
		);
	}

	$data['allowedConditions'] = get_conditions_by_eventsource($data['eventsource']);
	$data['allowedOperations'] = get_operations_by_eventsource($data['eventsource']);

	// sort conditions
	$sortFields = array(
		array('field' => 'conditiontype', 'order' => ZBX_SORT_DOWN),
		array('field' => 'operator', 'order' => ZBX_SORT_DOWN),
		array('field' => 'value', 'order' => ZBX_SORT_DOWN)
	);
	CArrayHelper::sort($data['action']['conditions'], $sortFields);

	// new condition
	$data['new_condition'] = array(
		'conditiontype' => isset($data['new_condition']['conditiontype']) ? $data['new_condition']['conditiontype'] : CONDITION_TYPE_TRIGGER_NAME,
		'operator' => isset($data['new_condition']['operator']) ? $data['new_condition']['operator'] : CONDITION_OPERATOR_LIKE,
		'value' => isset($data['new_condition']['value']) ? $data['new_condition']['value'] : ''
	);

	if (!str_in_array($data['new_condition']['conditiontype'], $data['allowedConditions'])) {
		$data['new_condition']['conditiontype'] = $data['allowedConditions'][0];
	}

	// new operation
	if (!empty($data['new_operation'])) {
		if (!is_array($data['new_operation'])) {
			$data['new_operation'] = array(
				'action' => 'create',
				'operationtype' => 0,
				'esc_period' => 0,
				'esc_step_from' => 1,
				'esc_step_to' => 1,
				'evaltype' => 0
			);
		}
	}

	// render view
	$actionView = new CView('configuration.action.edit', $data);
	$actionView->render();
	$actionView->show();
}
else {
	$data = array(
		'eventsource' => get_request('eventsource')
	);

	$sortfield = getPageSortField('name');

	$data['actions'] = API::Action()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'filter' => array('eventsource' => array($data['eventsource'])),
		'selectConditions' => API_OUTPUT_EXTEND,
		'selectOperations' => API_OUTPUT_EXTEND,
		'editable' => true,
		'sortfield' => $sortfield,
		'limit' => $config['search_limit'] + 1
	));

	// sorting && paging
	order_result($data['actions'], $sortfield, getPageSortOrder());
	$data['paging'] = getPagingLine($data['actions']);

	// render view
	$actionView = new CView('configuration.action.list', $data);
	$actionView->render();
	$actionView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
