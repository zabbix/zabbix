<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/actions.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';

$page['title'] = _('Configuration of actions');
$page['file'] = 'actionconf.php';
$page['scripts'] = array('multiselect.js');
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'actionid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'),
	'name' =>				array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Name')),
	'eventsource' =>		array(T_ZBX_INT, O_OPT, null,
		IN(array(EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTO_REGISTRATION, EVENT_SOURCE_INTERNAL)),
		null
	),
	'evaltype' =>			array(T_ZBX_INT, O_OPT, null,
		IN(array(CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION)),
		'isset({add}) || isset({update})'),
	'formula' =>			array(T_ZBX_STR, O_OPT, null,   null,		'isset({add}) || isset({update})'),
	'esc_period' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(60, 999999), null, _('Default operation step duration')),
	'status' =>				array(T_ZBX_INT, O_OPT, null,	IN(array(ACTION_STATUS_ENABLED, ACTION_STATUS_DISABLED)), null),
	'def_shortdata' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'),
	'def_longdata' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'),
	'recovery_msg' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'r_shortdata' =>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({recovery_msg}) && (isset({add}) || isset({update}))', _('Recovery subject')),
	'r_longdata' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({recovery_msg}) && (isset({add}) || isset({update}))', _('Recovery message')),
	'g_actionid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'conditions' =>			array(null,		O_OPT,	null,	null,		null),
	'new_condition' =>		array(null,		O_OPT,	null,	null,		'isset({add_condition})'),
	'operations' =>			array(null,		O_OPT,	null,	null,		'isset({add}) || isset({update})'),
	'edit_operationid' =>	array(T_ZBX_STR, O_OPT,	P_ACT,	null,		null),
	'new_operation' =>		array(null,		O_OPT,	null,	null,		'isset({add_operation})'),
	'opconditions' =>		array(null,		O_OPT,	null,	null,		null),
	'new_opcondition' =>	array(null,		O_OPT,	null,	null,		'isset({add_opcondition})'),
	// actions
	'action' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,
								IN('"action.massdelete","action.massdisable","action.massenable"'),
								null
							),
	'add_condition' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel_new_condition' => array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'add_operation' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel_new_operation' => array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'add_opcondition' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel_new_opcondition' => array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'add' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'update' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	// sort and sortorder
	'sort' =>			array(T_ZBX_STR, O_OPT, P_SYS, IN('"name","status"'),						null),
	'sortorder' =>		array(T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null)
);

$dataValid = check_fields($fields);

if ($dataValid && hasRequest('eventsource') && !hasRequest('form')) {
	CProfile::update('web.actionconf.eventsource', getRequest('eventsource'), PROFILE_TYPE_INT);
}

if (isset($_REQUEST['actionid'])) {
	$actionPermissions = API::Action()->get(array(
		'output' => array('actionid'),
		'actionids' => $_REQUEST['actionid'],
		'editable' => true
	));
	if (empty($actionPermissions)) {
		access_deny();
	}
}

/*
 * Actions
 */
if (isset($_REQUEST['cancel_new_operation'])) {
	unset($_REQUEST['new_operation']);
}
elseif (isset($_REQUEST['cancel_new_opcondition'])) {
	unset($_REQUEST['new_opcondition']);
}
elseif (hasRequest('add') || hasRequest('update')) {
	$action = array(
		'name' => getRequest('name'),
		'status' => getRequest('status', ACTION_STATUS_DISABLED),
		'esc_period' => getRequest('esc_period', 0),
		'def_shortdata' => getRequest('def_shortdata', ''),
		'def_longdata' => getRequest('def_longdata', ''),
		'recovery_msg' => getRequest('recovery_msg', 0),
		'r_shortdata' => getRequest('r_shortdata', ''),
		'r_longdata' => getRequest('r_longdata', ''),
		'operations' => getRequest('operations', array())
	);

	foreach ($action['operations'] as $num => $operation) {
		if (isset($operation['opmessage']) && !isset($operation['opmessage']['default_msg'])) {
			$action['operations'][$num]['opmessage']['default_msg'] = 0;
		}
	}

	$filter = array(
		'conditions' => getRequest('conditions', array()),
		'evaltype' => getRequest('evaltype')
	);

	if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
		if (count($filter['conditions']) > 1) {
			$filter['formula'] = getRequest('formula');
		}
		else {
			// if only one or no conditions are left, reset the evaltype to "and/or" and clear the formula
			$filter['formula'] = '';
			$filter['evaltype'] = CONDITION_EVAL_TYPE_AND_OR;
		}
	}
	$action['filter'] = $filter;

	DBstart();

	if (hasRequest('update')) {
		$action['actionid'] = getRequest('actionid');

		$result = API::Action()->update($action);

		$messageSuccess = _('Action updated');
		$messageFailed = _('Cannot update action');
		$auditAction = AUDIT_ACTION_UPDATE;
	}
	else {
		$action['eventsource'] = getRequest('eventsource',
			CProfile::get('web.actionconf.eventsource', EVENT_SOURCE_TRIGGERS)
		);

		$result = API::Action()->create($action);

		$messageSuccess = _('Action added');
		$messageFailed = _('Cannot add action');
		$auditAction = AUDIT_ACTION_ADD;
	}

	if ($result) {
		add_audit($auditAction, AUDIT_RESOURCE_ACTION, _('Name').NAME_DELIMITER.$action['name']);
		unset($_REQUEST['form']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['actionid'])) {
	$result = API::Action()->delete(array(getRequest('actionid')));

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['actionid']);
		uncheckTableRows();
	}
	show_messages($result, _('Action deleted'), _('Cannot delete action'));
}
elseif (isset($_REQUEST['add_condition']) && isset($_REQUEST['new_condition'])) {
	$newCondition = getRequest('new_condition');

	if ($newCondition) {
		$conditions = getRequest('conditions', array());

		// when adding new maintenance, in order to check for an existing maintenance, it must have a not null value
		if ($newCondition['conditiontype'] == CONDITION_TYPE_MAINTENANCE) {
			$newCondition['value'] = '';
		}

		// check existing conditions and remove duplicate condition values
		foreach ($conditions as $condition) {
			if ($newCondition['conditiontype'] == $condition['conditiontype']) {
				if (is_array($newCondition['value'])) {
					foreach ($newCondition['value'] as $key => $newValue) {
						if ($condition['value'] == $newValue) {
							unset($newCondition['value'][$key]);
						}
					}
				}
				else {
					if ($newCondition['value'] == $condition['value']) {
						$newCondition['value'] = null;
					}
				}
			}
		}

		$usedFormulaIds = zbx_objectValues($conditions, 'formulaid');

		$validateConditions = $conditions;

		if (isset($newCondition['value'])) {
			$newConditionValues = zbx_toArray($newCondition['value']);
			foreach ($newConditionValues as $newValue) {
				$condition = $newCondition;
				$condition['value'] = $newValue;
				$condition['formulaid'] = CConditionHelper::getNextFormulaId($usedFormulaIds);
				$usedFormulaIds[] = $condition['formulaid'];
				$validateConditions[] = $condition;
			}
		}

		$conditionsValid = true;
		if ($validateConditions) {
			$filterConditionValidator = new CActionCondValidator();
			foreach ($validateConditions as $condition) {
				if (!$filterConditionValidator->validate($condition)) {
					$conditionsValid = false;
					break;
				}
			}
		}

		if ($conditionsValid) {
			$_REQUEST['conditions'] = $validateConditions;
		}
		else {
			error($filterConditionValidator->getError());
			show_error_message(_('Cannot add action condition'));
		}
	}
}
elseif (isset($_REQUEST['add_opcondition']) && isset($_REQUEST['new_opcondition'])) {
	$new_opcondition = $_REQUEST['new_opcondition'];

	try {
		CAction::validateOperationConditions($new_opcondition);
		$new_operation = getRequest('new_operation', array());

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

	if (API::Action()->validateOperationsIntegrity($new_operation)) {
		$_REQUEST['operations'] = getRequest('operations', array());

		$uniqOperations = array(
			OPERATION_TYPE_HOST_ADD => 0,
			OPERATION_TYPE_HOST_REMOVE => 0,
			OPERATION_TYPE_HOST_ENABLE => 0,
			OPERATION_TYPE_HOST_DISABLE => 0
		);
		if (isset($uniqOperations[$new_operation['operationtype']])) {
			$uniqOperations[$new_operation['operationtype']]++;
			foreach ($_REQUEST['operations'] as $operationId => $operation) {
				if (array_key_exists($operation['operationtype'], $uniqOperations)
					&& (!array_key_exists('id', $new_operation)
						|| bccomp($new_operation['id'], $operationId) != 0)) {
					$uniqOperations[$operation['operationtype']]++;
				}
			}
			if ($uniqOperations[$new_operation['operationtype']] > 1) {
				$result = false;
				info(_s('Operation "%s" already exists.', operation_type2str($new_operation['operationtype'])));
				show_messages();
			}
		}

		if ($result) {
			$eventsource = getRequest('eventsource',
				CProfile::get('web.actionconf.eventsource', EVENT_SOURCE_TRIGGERS)
			);

			if (isset($new_operation['id'])) {
				$_REQUEST['operations'][$new_operation['id']] = $new_operation;
			}
			else {
				$_REQUEST['operations'][] = $new_operation;
			}

			sortOperations($eventsource, $_REQUEST['operations']);
		}

		unset($_REQUEST['new_operation']);
	}
}
elseif (isset($_REQUEST['edit_operationid'])) {
	$_REQUEST['edit_operationid'] = array_keys($_REQUEST['edit_operationid']);
	$edit_operationid = $_REQUEST['edit_operationid'] = array_pop($_REQUEST['edit_operationid']);
	$_REQUEST['operations'] = getRequest('operations', array());

	if (isset($_REQUEST['operations'][$edit_operationid])) {
		$_REQUEST['new_operation'] = $_REQUEST['operations'][$edit_operationid];
		$_REQUEST['new_operation']['id'] = $edit_operationid;
	}
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), array('action.massenable', 'action.massdisable')) && hasRequest('g_actionid')) {
	$result = true;
	$enable = (getRequest('action') == 'action.massenable');
	$status = $enable ? ACTION_STATUS_ENABLED : ACTION_STATUS_DISABLED;
	$statusName = $enable ? 'enabled' : 'disabled';
	$actionIds = array();
	$updated = 0;

	DBstart();

	$dbActions = DBselect(
		'SELECT a.actionid'.
		' FROM actions a'.
		' WHERE '.dbConditionInt('a.actionid', getRequest('g_actionid'))
	);
	while ($row = DBfetch($dbActions)) {
		$result &= DBexecute(
			'UPDATE actions'.
			' SET status='.zbx_dbstr($status).
			' WHERE actionid='.zbx_dbstr($row['actionid'])
		);
		if ($result) {
			$actionIds[] = $row['actionid'];
		}
		$updated++;
	}

	if ($result) {
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ACTION, ' Actions ['.implode(',', $actionIds).'] '.$statusName);
	}

	$messageSuccess = $enable
		? _n('Action enabled', 'Actions enabled', $updated)
		: _n('Action disabled', 'Actions disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable action', 'Cannot enable actions', $updated)
		: _n('Cannot disable action', 'Cannot disable actions', $updated);

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') == 'action.massdelete' && hasRequest('g_actionid')) {
	$result = API::Action()->delete(getRequest('g_actionid'));

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Selected actions deleted'), _('Cannot delete selected actions'));
}

/*
 * Display
 */
show_messages();

if (hasRequest('form')) {
	$data = array(
		'form' => getRequest('form'),
		'actionid' => getRequest('actionid'),
		'new_condition' => getRequest('new_condition', array()),
		'new_operation' => getRequest('new_operation')
	);

	$action = null;
	if ($data['actionid']) {
		$data['action'] = API::Action()->get(array(
			'actionids' => $data['actionid'],
			'selectOperations' => API_OUTPUT_EXTEND,
			'selectFilter' => array('formula', 'conditions', 'evaltype'),
			'output' => API_OUTPUT_EXTEND,
			'editable' => true
		));
		$data['action'] = reset($data['action']);

		$data['eventsource'] = $data['action']['eventsource'];
	}
	else {
		$data['eventsource'] = getRequest('eventsource',
			CProfile::get('web.actionconf.eventsource', EVENT_SOURCE_TRIGGERS)
		);
		$data['esc_period'] = getRequest('esc_period');
	}

	if (isset($data['action']['actionid']) && !hasRequest('form_refresh')) {
		sortOperations($data['eventsource'], $data['action']['operations']);
	}
	else {
		$data['action']['name'] = getRequest('name');
		$data['action']['esc_period'] = getRequest('esc_period', SEC_PER_HOUR);
		$data['action']['status'] = getRequest('status', hasRequest('form_refresh') ? 1 : 0);
		$data['action']['recovery_msg'] = getRequest('recovery_msg', 0);
		$data['action']['operations'] = getRequest('operations', array());

		$data['action']['filter']['evaltype'] = getRequest('evaltype');
		$data['action']['filter']['formula'] = getRequest('formula');
		$data['action']['filter']['conditions'] = getRequest('conditions', array());

		if ($data['actionid'] && hasRequest('form_refresh')) {
			$data['action']['def_shortdata'] = getRequest('def_shortdata');
			$data['action']['def_longdata'] = getRequest('def_longdata');
		}
		else {
			if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
				$data['action']['def_shortdata'] = getRequest('def_shortdata', ACTION_DEFAULT_SUBJ_TRIGGER);
				$data['action']['def_longdata'] = getRequest('def_longdata', ACTION_DEFAULT_MSG_TRIGGER);
				$data['action']['r_shortdata'] = getRequest('r_shortdata', ACTION_DEFAULT_SUBJ_TRIGGER);
				$data['action']['r_longdata'] = getRequest('r_longdata', ACTION_DEFAULT_MSG_TRIGGER);
			}
			elseif ($data['eventsource'] == EVENT_SOURCE_DISCOVERY) {
				$data['action']['def_shortdata'] = getRequest('def_shortdata', ACTION_DEFAULT_SUBJ_DISCOVERY);
				$data['action']['def_longdata'] = getRequest('def_longdata', ACTION_DEFAULT_MSG_DISCOVERY);
			}
			elseif ($data['eventsource'] == EVENT_SOURCE_AUTO_REGISTRATION) {
				$data['action']['def_shortdata'] = getRequest('def_shortdata', ACTION_DEFAULT_SUBJ_AUTOREG);
				$data['action']['def_longdata'] = getRequest('def_longdata', ACTION_DEFAULT_MSG_AUTOREG);
			}
			else {
				$data['action']['def_shortdata'] = getRequest('def_shortdata');
				$data['action']['def_longdata'] = getRequest('def_longdata');
				$data['action']['r_shortdata'] = getRequest('r_shortdata');
				$data['action']['r_longdata'] = getRequest('r_longdata');
			}
		}
	}

	if (!$data['actionid'] && !hasRequest('form_refresh') && $data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
		$data['action']['filter']['conditions'] = array(
			array(
				'formulaid' => 'A',
				'conditiontype' => CONDITION_TYPE_MAINTENANCE,
				'operator' => CONDITION_OPERATOR_NOT_IN,
				'value' => ''
			),
			array(
				'formulaid' => 'B',
				'conditiontype' => CONDITION_TYPE_TRIGGER_VALUE,
				'operator' => CONDITION_OPERATOR_EQUAL,
				'value' => TRIGGER_VALUE_TRUE
			)
		);
	}

	$data['allowedConditions'] = get_conditions_by_eventsource($data['eventsource']);
	$data['allowedOperations'] = get_operations_by_eventsource($data['eventsource']);

	if (!hasRequest('add_condition')) {
		$data['action']['filter']['conditions'] = CConditionHelper::sortConditionsByFormulaId(
			$data['action']['filter']['conditions']
		);
	}

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
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$data = array(
		'eventsource' => getRequest('eventsource', CProfile::get('web.actionconf.eventsource', EVENT_SOURCE_TRIGGERS)),
		'sort' => $sortField,
		'sortorder' => $sortOrder
	);

	$data['actions'] = API::Action()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'filter' => array('eventsource' => array($data['eventsource'])),
		'selectFilter' => array('formula', 'conditions', 'evaltype'),
		'selectOperations' => API_OUTPUT_EXTEND,
		'editable' => true,
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	));

	// sorting && paging
	order_result($data['actions'], $sortField, $sortOrder);
	$data['paging'] = getPagingLine($data['actions'], $sortOrder);

	// render view
	$actionView = new CView('configuration.action.list', $data);
	$actionView->render();
	$actionView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
