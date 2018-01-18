<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
$page['scripts'] = ['multiselect.js'];

require_once dirname(__FILE__).'/include/page_header.php';
// VAR							TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'actionid' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'],
	'name' =>							[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})',
											_('Name')
										],
	'eventsource' =>					[T_ZBX_INT, O_OPT, null,
											IN([EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY,
												EVENT_SOURCE_AUTO_REGISTRATION, EVENT_SOURCE_INTERNAL
											]),
											null
										],
	'evaltype' =>						[T_ZBX_INT, O_OPT, null,
											IN([CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND,
												CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION
											]),
											'isset({add}) || isset({update})'
										],
	'formula' =>						[T_ZBX_STR, O_OPT, null,	null,	'isset({add}) || isset({update})'],
	'esc_period' =>						[T_ZBX_STR, O_OPT, null,	null,	null, _('Default operation step duration')],
	'status' =>							[T_ZBX_INT, O_OPT, null,	IN([ACTION_STATUS_ENABLED, ACTION_STATUS_DISABLED]),
											null
										],
	'def_shortdata' =>					[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'def_longdata' =>					[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'r_shortdata' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'r_longdata' =>						[T_ZBX_STR, O_OPT, null,	null,		null],
	'ack_shortdata' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'ack_longdata' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'g_actionid' =>						[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'conditions' =>						[null,		O_OPT,	null,	null,		null],
	'new_condition' =>					[null,		O_OPT,	null,	null,		'isset({add_condition})'],
	'operations' =>						[null,		O_OPT,	null,	null,		null],
	'edit_operationid' =>				[T_ZBX_STR, O_OPT,	P_ACT,	null,		null],
	'new_operation' =>					[null,		O_OPT,	null,	null,		null],
	'recovery_operations' =>			[null,		O_OPT,	null,	null,		null],
	'edit_recovery_operationid' =>		[T_ZBX_STR, O_OPT,	P_ACT,	null,		null],
	'new_recovery_operation' =>			[null,		O_OPT,	null,	null,		null],
	'ack_operations' =>					[null,		O_OPT,	null,	null,		null],
	'edit_ack_operationid' =>			[T_ZBX_STR, O_OPT,	P_ACT,	null,		null],
	'new_ack_operation' =>				[null,		O_OPT,	null,	null,		null],
	'opconditions' =>					[null,		O_OPT,	null,	null,		null],
	'new_opcondition' =>				[null,		O_OPT,	null,	null,		'isset({add_opcondition})'],
	// actions
	'action' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
											IN('"action.massdelete","action.massdisable","action.massenable"'),
											null
										],
	'add_condition' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel_new_condition' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_operation' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel_new_operation' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_recovery_operation' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel_new_recovery_operation' =>	[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_ack_operation' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel_new_ack_operation' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_opcondition' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel_new_opcondition' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'maintenance_mode' =>				[T_ZBX_STR, O_OPT, null,
											IN([ACTION_MAINTENANCE_MODE_NORMAL, ACTION_MAINTENANCE_MODE_PAUSE]),
											null,
											_('Pause operations while in maintenance')
										],
	'add' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>							[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'form' =>							[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'form_refresh' =>					[T_ZBX_INT, O_OPT, null,		null,	null],
	// filter
	'filter_set' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_name' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_status' =>			[T_ZBX_INT, O_OPT, null,	IN([-1, ACTION_STATUS_ENABLED, ACTION_STATUS_DISABLED]),		null],
	// sort and sortorder
	'sort' =>							[T_ZBX_STR, O_OPT, P_SYS, IN('"name","status"'),						null],
	'sortorder' =>						[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];

$dataValid = check_fields($fields);
$edit_ack_operationid = null;
$new_ack_operation = getRequest('new_ack_operation', []);
$ack_operations = getRequest('ack_operations', []);

if ($dataValid && hasRequest('eventsource') && !hasRequest('form')) {
	CProfile::update('web.actionconf.eventsource', getRequest('eventsource'), PROFILE_TYPE_INT);
}

if (isset($_REQUEST['actionid'])) {
	$actionPermissions = API::Action()->get([
		'output' => ['actionid'],
		'actionids' => $_REQUEST['actionid'],
		'editable' => true
	]);
	if (empty($actionPermissions)) {
		access_deny();
	}
}

/*
 * Actions
 */
if (hasRequest('cancel_new_operation')) {
	unset($_REQUEST['new_operation']);
}
elseif (hasRequest('cancel_new_opcondition')) {
	unset($_REQUEST['new_opcondition']);
}
elseif (hasRequest('cancel_new_recovery_operation')) {
	unset($_REQUEST['new_recovery_operation']);
}
elseif (hasRequest('cancel_new_ack_operation')) {
	$new_ack_operation = [];
}
elseif (hasRequest('add') || hasRequest('update')) {
	$action = [
		'name' => getRequest('name'),
		'status' => getRequest('status', ACTION_STATUS_DISABLED),
		'esc_period' => getRequest('esc_period', DB::getDefault('actions', 'esc_period')),
		'def_shortdata' => getRequest('def_shortdata', ''),
		'def_longdata' => getRequest('def_longdata', ''),
		'r_shortdata' => getRequest('r_shortdata', ''),
		'r_longdata' => getRequest('r_longdata', ''),
		'ack_shortdata' => getRequest('ack_shortdata', ''),
		'ack_longdata' => getRequest('ack_longdata', ''),
		'operations' => getRequest('operations', []),
		'recovery_operations' => getRequest('recovery_operations', []),
		'acknowledge_operations' => getRequest('ack_operations', [])
	];

	foreach (['operations', 'recovery_operations', 'acknowledge_operations'] as $operation_key) {
		foreach ($action[$operation_key] as &$operation) {
			if (array_key_exists('opmessage', $operation)
					&& !array_key_exists('default_msg', $operation['opmessage'])) {
				$operation['opmessage']['default_msg'] = 0;
			}
		}
		unset($operation);
	}

	foreach ($action['operations'] as &$operation) {
		if ($operation['operationtype'] == OPERATION_TYPE_GROUP_ADD
				|| $operation['operationtype'] == OPERATION_TYPE_GROUP_REMOVE) {
			$operation['opgroup'] = [];

			foreach ($operation['groupids'] as $groupid) {
				$operation['opgroup'][] = ['groupid' => $groupid];
			}
			unset($operation['groupids']);
		}
		elseif ($operation['operationtype'] == OPERATION_TYPE_TEMPLATE_ADD
				|| $operation['operationtype'] == OPERATION_TYPE_TEMPLATE_REMOVE) {
			$operation['optemplate'] = [];

			foreach ($operation['templateids'] as $templateid) {
				$operation['optemplate'][] = ['templateid' => $templateid];
			}
			unset($operation['templateids']);
		}
	}
	unset($operation);

	$filter = [
		'conditions' => getRequest('conditions', []),
		'evaltype' => getRequest('evaltype')
	];

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

	$eventsource = getRequest('eventsource', CProfile::get('web.actionconf.eventsource', EVENT_SOURCE_TRIGGERS));

	if ($eventsource == EVENT_SOURCE_TRIGGERS) {
		$action['maintenance_mode'] = getRequest('maintenance_mode', ACTION_MAINTENANCE_MODE_NORMAL);
	}

	DBstart();

	if (hasRequest('update')) {
		$action['actionid'] = getRequest('actionid');

		$result = API::Action()->update($action);

		$messageSuccess = _('Action updated');
		$messageFailed = _('Cannot update action');
		$auditAction = AUDIT_ACTION_UPDATE;
	}
	else {
		$action['eventsource'] = $eventsource;

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
elseif (hasRequest('delete') && hasRequest('actionid')) {
	$result = API::Action()->delete([getRequest('actionid')]);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['actionid']);
		uncheckTableRows();
	}
	show_messages($result, _('Action deleted'), _('Cannot delete action'));
}
elseif (hasRequest('add_condition') && hasRequest('new_condition')) {
	$newCondition = getRequest('new_condition');

	if ($newCondition) {
		$conditions = getRequest('conditions', []);

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
elseif (hasRequest('add_opcondition') && hasRequest('new_opcondition')) {
	$new_opcondition = getRequest('new_opcondition');

	try {
		CAction::validateOperationConditions($new_opcondition);
		$new_operation = getRequest('new_operation', []);

		if (!isset($new_operation['opconditions'])) {
			$new_operation['opconditions'] = [];
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
elseif (hasRequest('add_operation') && hasRequest('new_operation')) {
	$new_operation = $_REQUEST['new_operation'];
	$result = true;

	switch ($new_operation['operationtype']) {
		case OPERATION_TYPE_GROUP_ADD:
		case OPERATION_TYPE_GROUP_REMOVE:
			$new_operation['opgroup'] = [];

			if (array_key_exists('groupids', $new_operation)) {
				foreach ($new_operation['groupids'] as $groupid) {
					$new_operation['opgroup'][] = ['groupid' => $groupid];
				}
				unset($new_operation['groupids']);
			}
			break;

		case OPERATION_TYPE_TEMPLATE_ADD:
		case OPERATION_TYPE_TEMPLATE_REMOVE:
			$new_operation['optemplate'] = [];

			if (array_key_exists('templateids', $new_operation)) {
				foreach ($new_operation['templateids'] as $templateid) {
					$new_operation['optemplate'][] = ['templateid' => $templateid];
				}
				unset($new_operation['templateids']);
			}
			break;
	}

	$eventsource = getRequest('eventsource', CProfile::get('web.actionconf.eventsource', EVENT_SOURCE_TRIGGERS));

	$new_operation['recovery'] = ACTION_OPERATION;
	$new_operation['eventsource'] = $eventsource;

	if (API::Action()->validateOperationsIntegrity($new_operation)) {
		$_REQUEST['operations'] = getRequest('operations', []);

		$uniqOperations = [
			OPERATION_TYPE_HOST_ADD => 0,
			OPERATION_TYPE_HOST_REMOVE => 0,
			OPERATION_TYPE_HOST_ENABLE => 0,
			OPERATION_TYPE_HOST_DISABLE => 0,
			OPERATION_TYPE_HOST_INVENTORY => 0
		];

		if (array_key_exists($new_operation['operationtype'], $uniqOperations)) {
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
				error(_s('Operation "%s" already exists.', operation_type2str($new_operation['operationtype'])));
				show_messages();
			}
		}

		if ($result) {
			if (isset($_REQUEST['new_operation']['id'])) {
				$_REQUEST['operations'][$_REQUEST['new_operation']['id']] = $_REQUEST['new_operation'];
			}
			else {
				$_REQUEST['operations'][] = $_REQUEST['new_operation'];
			}

			sortOperations($eventsource, $_REQUEST['operations']);
		}

		unset($_REQUEST['new_operation']);
	}
}
elseif (hasRequest('add_recovery_operation') && hasRequest('new_recovery_operation')) {
	$new_recovery_operation = getRequest('new_recovery_operation');

	$eventsource = getRequest('eventsource', CProfile::get('web.actionconf.eventsource', EVENT_SOURCE_TRIGGERS));

	$new_recovery_operation['recovery'] = ACTION_RECOVERY_OPERATION;
	$new_recovery_operation['eventsource'] = $eventsource;

	if (API::Action()->validateOperationsIntegrity($new_recovery_operation)) {
		$_REQUEST['recovery_operations'] = getRequest('recovery_operations', []);

		if (isset($_REQUEST['new_recovery_operation']['id'])) {
			$_REQUEST['recovery_operations'][$_REQUEST['new_recovery_operation']['id']] = $_REQUEST['new_recovery_operation'];
		}
		else {
			$_REQUEST['recovery_operations'][] = $_REQUEST['new_recovery_operation'];
		}

		unset($_REQUEST['new_recovery_operation']);
	}
}
elseif (hasRequest('add_ack_operation') && $new_ack_operation) {
	$new_ack_operation['recovery'] = ACTION_ACKNOWLEDGE_OPERATION;
	$new_ack_operation['eventsource'] = EVENT_SOURCE_TRIGGERS;

	if (API::Action()->validateOperationsIntegrity($new_ack_operation)) {
		if (array_key_exists('id', $new_ack_operation)) {
			$ack_operations[$new_ack_operation['id']] = $new_ack_operation;
		}
		else {
			$ack_operations[] = $new_ack_operation;
		}
		$new_ack_operation = [];
	}
}
elseif (hasRequest('edit_operationid')) {
	$_REQUEST['edit_operationid'] = array_keys($_REQUEST['edit_operationid']);
	$edit_operationid = $_REQUEST['edit_operationid'] = array_pop($_REQUEST['edit_operationid']);
	$_REQUEST['operations'] = getRequest('operations', []);

	if (isset($_REQUEST['operations'][$edit_operationid])) {
		$_REQUEST['new_operation'] = $_REQUEST['operations'][$edit_operationid];
		$_REQUEST['new_operation']['id'] = $edit_operationid;
	}
}
elseif (hasRequest('edit_recovery_operationid')) {
	$_REQUEST['edit_recovery_operationid'] = array_keys($_REQUEST['edit_recovery_operationid']);
	$edit_recovery_operationid = array_pop($_REQUEST['edit_recovery_operationid']);
	$_REQUEST['edit_recovery_operationid'] = $edit_recovery_operationid;
	$_REQUEST['recovery_operations'] = getRequest('recovery_operations', []);

	if (array_key_exists($edit_recovery_operationid, $_REQUEST['recovery_operations'])) {
		$_REQUEST['new_recovery_operation'] = $_REQUEST['recovery_operations'][$edit_recovery_operationid];
		$_REQUEST['new_recovery_operation']['id'] = $edit_recovery_operationid;
	}
}
elseif (hasRequest('edit_ack_operationid')) {
	$edit_ack_operationid = key(getRequest('edit_ack_operationid'));

	if (array_key_exists($edit_ack_operationid, $ack_operations)) {
		$new_ack_operation = $ack_operations[$edit_ack_operationid];
		$new_ack_operation['id'] = $edit_ack_operationid;
	}
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['action.massenable', 'action.massdisable']) && hasRequest('g_actionid')) {
	$status = (getRequest('action') == 'action.massenable') ? ACTION_STATUS_ENABLED : ACTION_STATUS_DISABLED;
	$actionids = (array) getRequest('g_actionid', []);
	$actions_count = count($actionids);
	$actions = [];

	foreach ($actionids as $actionid) {
		$actions[] = ['actionid' => $actionid, 'status' => $status];
	}

	$response = API::Action()->update($actions);

	if ($response && array_key_exists('actionids', $response)) {
		$message = $status == ACTION_STATUS_ENABLED
			? _n('Action enabled', 'Actions enabled', $actions_count)
			: _n('Action disabled', 'Actions disabled', $actions_count);

		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ACTION, ' Actions ['.implode(',', $response['actionids']).'] '.
			($status == ACTION_STATUS_ENABLED ? 'enabled' : 'disabled')
		);
		show_messages(true, $message);
		uncheckTableRows();
	}
	else {
		$message = $status == ACTION_STATUS_ENABLED
			? _n('Cannot enable action', 'Cannot enable actions', $actions_count)
			: _n('Cannot disable action', 'Cannot disable actions', $actions_count);

		show_messages(false, null, $message);
	}
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

$config = select_config();

if (hasRequest('form')) {
	$data = [
		'form' => getRequest('form'),
		'actionid' => getRequest('actionid'),
		'new_condition' => getRequest('new_condition', []),
		'new_operation' => getRequest('new_operation'),
		'new_recovery_operation' => getRequest('new_recovery_operation'),
		'new_ack_operation' => $new_ack_operation,
		'config' => $config
	];

	if ($data['actionid']) {
		$data['action'] = API::Action()->get([
			'actionids' => $data['actionid'],
			'selectOperations' => API_OUTPUT_EXTEND,
			'selectRecoveryOperations' => ['operationid', 'actionid', 'operationtype', 'opmessage', 'opmessage_grp',
				'opmessage_usr', 'opcommand', 'opcommand_hst', 'opcommand_grp'
			],
			'selectAcknowledgeOperations' => ['operationid', 'actionid', 'operationtype', 'opmessage', 'opmessage_grp',
				'opmessage_usr', 'opcommand', 'opcommand_hst', 'opcommand_grp'
			],
			'selectFilter' => ['formula', 'conditions', 'evaltype'],
			'output' => API_OUTPUT_EXTEND,
			'editable' => true
		]);
		$data['action'] = reset($data['action']);

		$data['action']['recovery_operations'] = $data['action']['recoveryOperations'];
		$data['action']['ack_operations'] = $data['action']['acknowledgeOperations'];
		unset($data['action']['recoveryOperations'], $data['action']['acknowledgeOperations']);

		foreach ($data['action']['operations'] as &$operation) {
			if ($operation['operationtype'] == OPERATION_TYPE_GROUP_ADD
					|| $operation['operationtype'] == OPERATION_TYPE_GROUP_REMOVE) {
				$operation = [
					'actionid' => $operation['actionid'],
					'operationid' => $operation['operationid'],
					'operationtype' => $operation['operationtype'],
					'groupids' => zbx_objectValues($operation['opgroup'], 'groupid')
				];
			}
			elseif ($operation['operationtype'] == OPERATION_TYPE_TEMPLATE_ADD
					|| $operation['operationtype'] == OPERATION_TYPE_TEMPLATE_REMOVE) {
				$operation = [
					'actionid' => $operation['actionid'],
					'operationid' => $operation['operationid'],
					'operationtype' => $operation['operationtype'],
					'templateids' => zbx_objectValues($operation['optemplate'], 'templateid')
				];
			}
		}
		unset($operation);

		$data['eventsource'] = $data['action']['eventsource'];
	}
	else {
		$data['eventsource'] = getRequest('eventsource',
			CProfile::get('web.actionconf.eventsource', EVENT_SOURCE_TRIGGERS)
		);
		$data['esc_period'] = getRequest('esc_period');
	}

	if (array_key_exists('action', $data) && array_key_exists('actionid', $data['action'])
			&& !hasRequest('form_refresh')) {
		sortOperations($data['eventsource'], $data['action']['operations']);
	}
	else {
		$data['action']['name'] = getRequest('name');
		$data['action']['esc_period'] = getRequest('esc_period', DB::getDefault('actions', 'esc_period'));
		$data['action']['status'] = getRequest('status', hasRequest('form_refresh') ? 1 : 0);
		$data['action']['operations'] = getRequest('operations', []);
		$data['action']['recovery_operations'] = getRequest('recovery_operations', []);
		$data['action']['ack_operations'] = $ack_operations;

		$data['action']['filter']['evaltype'] = getRequest('evaltype');
		$data['action']['filter']['formula'] = getRequest('formula');
		$data['action']['filter']['conditions'] = getRequest('conditions', []);

		if ($data['actionid'] && hasRequest('form_refresh')) {
			$data['action']['def_shortdata'] = getRequest('def_shortdata', '');
			$data['action']['def_longdata'] = getRequest('def_longdata', '');
			$data['action']['r_shortdata'] = getRequest('r_shortdata', '');
			$data['action']['r_longdata'] = getRequest('r_longdata', '');
			$data['action']['ack_shortdata'] = getRequest('ack_shortdata', '');
			$data['action']['ack_longdata'] = getRequest('ack_longdata', '');

			if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
				$data['action']['maintenance_mode'] = getRequest('maintenance_mode', ACTION_MAINTENANCE_MODE_NORMAL);
			}
		}
		else {
			if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
				$data['action']['def_shortdata'] = getRequest('def_shortdata', ACTION_DEFAULT_SUBJ_PROBLEM);
				$data['action']['def_longdata'] = getRequest('def_longdata', ACTION_DEFAULT_MSG_PROBLEM);
				$data['action']['r_shortdata'] = getRequest('r_shortdata', ACTION_DEFAULT_SUBJ_RECOVERY);
				$data['action']['r_longdata'] = getRequest('r_longdata', ACTION_DEFAULT_MSG_RECOVERY);
				$data['action']['ack_shortdata'] = getRequest('ack_shortdata', ACTION_DEFAULT_SUBJ_ACKNOWLEDGE);
				$data['action']['ack_longdata'] = getRequest('ack_longdata', ACTION_DEFAULT_MSG_ACKNOWLEDGE);
				$data['action']['maintenance_mode'] = getRequest('maintenance_mode',
					hasRequest('form_refresh') ? ACTION_MAINTENANCE_MODE_NORMAL : ACTION_MAINTENANCE_MODE_PAUSE
				);
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
				$data['action']['def_shortdata'] = getRequest('def_shortdata', '');
				$data['action']['def_longdata'] = getRequest('def_longdata', '');
				$data['action']['r_shortdata'] = getRequest('r_shortdata', '');
				$data['action']['r_longdata'] = getRequest('r_longdata', '');
				$data['action']['ack_shortdata'] = getRequest('ack_shortdata', '');
				$data['action']['ack_longdata'] = getRequest('ack_longdata', '');
			}
		}
	}

	if (!$data['actionid'] && !hasRequest('form_refresh') && $data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
		$data['action']['filter']['conditions'] = [[
			'formulaid' => 'A',
			'conditiontype' => CONDITION_TYPE_MAINTENANCE,
			'operator' => CONDITION_OPERATOR_NOT_IN,
			'value' => ''
		]];
	}

	$data['allowedConditions'] = get_conditions_by_eventsource($data['eventsource']);
	$data['allowedOperations'] = getAllowedOperations($data['eventsource']);

	if (!hasRequest('add_condition')) {
		$data['action']['filter']['conditions'] = CConditionHelper::sortConditionsByFormulaId(
			$data['action']['filter']['conditions']
		);
	}

	// Add default values for new condition.
	$data['new_condition'] += [
		'conditiontype'	=> CONDITION_TYPE_TRIGGER_NAME,
		'operator'		=> CONDITION_OPERATOR_LIKE,
		'value'			=> ''
	];

	if (!str_in_array($data['new_condition']['conditiontype'], $data['allowedConditions'])) {
		$data['new_condition']['conditiontype'] = $data['allowedConditions'][0];
	}

	// New operation.
	if ($data['new_operation'] && !is_array($data['new_operation'])) {
		$data['new_operation'] = [
			'operationtype' => OPERATION_TYPE_MESSAGE,
			'esc_period' => '0',
			'esc_step_from' => 1,
			'esc_step_to' => 1,
			'evaltype' => 0
		];
	}

	if (is_array($data['new_operation'])) {
		switch ($data['new_operation']['operationtype']) {
			case OPERATION_TYPE_GROUP_ADD:
			case OPERATION_TYPE_GROUP_REMOVE:
				if (!array_key_exists('groupids', $data['new_operation'])) {
					$data['new_operation']['groupids'] = [];
				}

				if ($data['new_operation']['groupids']) {
					$data['new_operation']['groups'] = API::HostGroup()->get([
						'groupids' => $data['new_operation']['groupids'],
						'output' => ['groupid', 'name'],
						'editable' => true
					]);

					foreach ($data['new_operation']['groups'] as &$group) {
						$group['id'] = $group['groupid'];
						unset($group['groupid']);
					}
					unset($group);
				}
				else {
					$data['new_operation']['groups'] = [];
				}
				break;

			case OPERATION_TYPE_TEMPLATE_ADD:
			case OPERATION_TYPE_TEMPLATE_REMOVE:
				if (!array_key_exists('templateids', $data['new_operation'])) {
					$data['new_operation']['templateids'] = [];
				}

				if ($data['new_operation']['templateids']) {
					$data['new_operation']['templates'] = API::Template()->get([
						'templateids' => $data['new_operation']['templateids'],
						'output' => ['templateid', 'name'],
						'editable' => true
					]);

					foreach ($data['new_operation']['templates'] as &$template) {
						$template['id'] = $template['templateid'];
						unset($template['templateid']);
					}
					unset($template);
				}
				else {
					$data['new_operation']['templates'] = [];
				}
				break;

			case OPERATION_TYPE_HOST_INVENTORY:
				if (!array_key_exists('opinventory', $data['new_operation'])) {
					$data['new_operation']['opinventory'] = ['inventory_mode' => HOST_INVENTORY_MANUAL];
				}
				break;
		}
	}

	// New recovery operation.
	if ($data['new_recovery_operation'] && !is_array($data['new_recovery_operation'])) {
		$data['new_recovery_operation'] = ['operationtype' => OPERATION_TYPE_MESSAGE];
	}

	$data['available_mediatypes'] = API::MediaType()->get(['output' => ['mediatypeid', 'description']]);
	order_result($data['available_mediatypes'], 'description');

	if ($data['new_ack_operation'] && !is_array($data['new_ack_operation'])) {
		$data['new_ack_operation'] = [
			'operationtype' => OPERATION_TYPE_MESSAGE,
			'ack_short' => ACTION_DEFAULT_SUBJ_ACKNOWLEDGE,
			'ack_long' => ACTION_DEFAULT_MSG_ACKNOWLEDGE,
		];
	}
	if ($data['new_ack_operation'] && !array_key_exists('opmessage', $data['new_ack_operation'])
			&& $data['new_ack_operation']['operationtype'] != OPERATION_TYPE_COMMAND) {
		$data['new_ack_operation']['opmessage'] = [
			'default_msg'	=> 1,
			'mediatypeid'	=> 0,
			'subject'	=> ACTION_DEFAULT_SUBJ_ACKNOWLEDGE,
			'message'	=> ACTION_DEFAULT_MSG_ACKNOWLEDGE
		];
	}

	// Render view.
	$actionView = new CView('configuration.action.edit', $data);
	$actionView->render();
	$actionView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	// filter
	if (hasRequest('filter_set')) {
		CProfile::update('web.actionconf.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
		CProfile::update('web.actionconf.filter_status', getRequest('filter_status', -1), PROFILE_TYPE_INT);
	}
	elseif (hasRequest('filter_rst')) {
		CProfile::delete('web.actionconf.filter_name');
		CProfile::delete('web.actionconf.filter_status');
	}

	$filter = [
		'name' => CProfile::get('web.actionconf.filter_name', ''),
		'status' => CProfile::get('web.actionconf.filter_status', -1)
	];

	$data = [
		'eventsource' => getRequest('eventsource', CProfile::get('web.actionconf.eventsource', EVENT_SOURCE_TRIGGERS)),
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'filter' => $filter,
		'config' => $config
	];

	$data['actions'] = API::Action()->get([
		'output' => API_OUTPUT_EXTEND,
		'search' => [
			'name' => ($filter['name'] === '') ? null : $filter['name'],
		],
		'filter' => [
			'eventsource' => $data['eventsource'],
			'status' => ($filter['status'] == -1) ? null : $filter['status']
		],
		'selectFilter' => ['formula', 'conditions', 'evaltype'],
		'selectOperations' => API_OUTPUT_EXTEND,
		'editable' => true,
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	]);

	foreach ($data['actions'] as &$action) {
		foreach ($action['operations'] as &$operation) {
			switch ($operation['operationtype']) {
				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					$operation = [
						'operationtype' => $operation['operationtype'],
						'groupids' => zbx_objectValues($operation['opgroup'], 'groupid')
					];
					break;

				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					$operation = [
						'operationtype' => $operation['operationtype'],
						'templateids' => zbx_objectValues($operation['optemplate'], 'templateid')
					];
					break;
			}
		}
		unset($operation);
	}
	unset($action);

	// sorting && paging
	order_result($data['actions'], $sortField, $sortOrder);
	$data['paging'] = getPagingLine($data['actions'], $sortOrder, new CUrl('actionconf.php'));

	// render view
	$actionView = new CView('configuration.action.list', $data);
	$actionView->render();
	$actionView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
