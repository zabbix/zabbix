<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
$page['scripts'] = ['popup.condition.common.js', 'popup.operation.common.js'];

require_once dirname(__FILE__).'/include/page_header.php';
// VAR							TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'actionid' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'],
	'name' =>							[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})',
											_('Name')
										],
	'eventsource' =>					[T_ZBX_INT, O_OPT, P_SYS,
											IN([EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY,
												EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL,
												EVENT_SOURCE_SERVICE
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
	'g_actionid' =>						[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'conditions' =>						[null,		O_OPT,	null,	null,		null],
	'new_condition' =>					[null,		O_OPT,	null,	null,		'isset({add_condition})'],
	'operations' =>						[null,		O_OPT,	null,	null,		null],
	'edit_operationid' =>				[T_ZBX_STR, O_OPT,	P_ACT,	null,		null],
	'new_operation' =>					[null,		O_OPT,	null,	null,		null],
	'recovery_operations' =>			[null,		O_OPT,	null,	null,		null],
	'edit_recovery_operationid' =>		[T_ZBX_STR, O_OPT,	P_ACT,	null,		null],
	'new_recovery_operation' =>			[null,		O_OPT,	null,	null,		null],
	'update_operations' =>				[null,		O_OPT,	null,	null,		null],
	'edit_update_operationid' =>		[T_ZBX_STR, O_OPT,	P_ACT,	null,		null],
	'new_update_operation' =>			[null,		O_OPT,	null,	null,		null],
	'opconditions' =>					[null,		O_OPT,	null,	null,		null],
	// actions
	'action' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
											IN('"action.massdelete","action.massdisable","action.massenable"'),
											null
										],
	'add_condition' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel_new_condition' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_operation' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_recovery_operation' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_update_operation' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'pause_suppressed' =>				[T_ZBX_STR, O_OPT, null,
											IN([ACTION_PAUSE_SUPPRESSED_FALSE, ACTION_PAUSE_SUPPRESSED_TRUE]),
											null,
											_('Pause operations for suppressed problems')
										],
	'notify_if_canceled' =>				[T_ZBX_STR, O_OPT, null,
											IN([ACTION_NOTIFY_IF_CANCELED_FALSE, ACTION_NOTIFY_IF_CANCELED_TRUE]),
											null,
											_('Notify about canceled escalations')
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
$edit_update_operationid = null;
$new_update_operation = getRequest('new_update_operation', []);
$update_operations = getRequest('update_operations', []);
$eventsource = getRequest('eventsource', EVENT_SOURCE_TRIGGERS);

$check_actionids = [];

if (hasRequest('actionid')) {
	$check_actionids[getRequest('actionid')] = true;
}

if (hasRequest('g_actionid')) {
	$check_actionids += array_fill_keys((array) getRequest('g_actionid'), true);
}

if ($check_actionids) {
	$actions = API::Action()->get([
		'output' => [],
		'actionids' => array_keys($check_actionids),
		'filter' => [
			'eventsource' => $eventsource
		],
		'editable' => true
	]);

	if (count($actions) != count($check_actionids)) {
		access_deny();
	}

	unset($check_actionids, $actions);
}

/*
 * Actions
 */
if (hasRequest('add') || hasRequest('update')) {
	$action = [
		'name' => getRequest('name'),
		'status' => getRequest('status', ACTION_STATUS_DISABLED),
		'operations' => getRequest('operations', []),
		'recovery_operations' => getRequest('recovery_operations', []),
		'update_operations' => getRequest('update_operations', [])
	];

	foreach (['operations', 'recovery_operations', 'update_operations'] as $operation_group) {
		foreach ($action[$operation_group] as &$operation) {
			if ($operation_group === 'operations') {
				if ($eventsource == EVENT_SOURCE_TRIGGERS) {
					if (array_key_exists('opconditions', $operation)) {
						foreach ($operation['opconditions'] as &$opcondition) {
							unset($opcondition['opconditionid'], $opcondition['operationid']);
						}
						unset($opcondition);
					}
					else {
						$operation['opconditions'] = [];
					}
				}
				elseif ($eventsource == EVENT_SOURCE_DISCOVERY || $eventsource == EVENT_SOURCE_AUTOREGISTRATION) {
					unset($operation['esc_period'], $operation['esc_step_from'], $operation['esc_step_to'],
						$operation['evaltype']
					);
				}
				elseif ($eventsource == EVENT_SOURCE_INTERNAL || $eventsource == EVENT_SOURCE_SERVICE) {
					unset($operation['evaltype']);
				}
			}
			elseif ($operation_group === 'recovery_operations') {
				if ($operation['operationtype'] != OPERATION_TYPE_MESSAGE) {
					unset($operation['opmessage']['mediatypeid']);
				}

				if ($operation['operationtype'] == OPERATION_TYPE_COMMAND) {
					unset($operation['opmessage']);
				}
			}

			if (array_key_exists('opmessage', $operation)) {
				if (!array_key_exists('default_msg', $operation['opmessage'])) {
					$operation['opmessage']['default_msg'] = 1;
				}

				if ($operation['opmessage']['default_msg'] == 1) {
					unset($operation['opmessage']['subject'], $operation['opmessage']['message']);
				}
			}

			if (array_key_exists('opmessage_grp', $operation) || array_key_exists('opmessage_usr', $operation)) {
				if (!array_key_exists('opmessage_grp', $operation)) {
					$operation['opmessage_grp'] = [];
				}

				if (!array_key_exists('opmessage_usr', $operation)) {
					$operation['opmessage_usr'] = [];
				}
			}

			if (array_key_exists('opcommand_grp', $operation) || array_key_exists('opcommand_hst', $operation)) {
				if (array_key_exists('opcommand_grp', $operation)) {
					foreach ($operation['opcommand_grp'] as &$opcommand_grp) {
						unset($opcommand_grp['opcommand_grpid']);
					}
					unset($opcommand_grp);
				}
				else {
					$operation['opcommand_grp'] = [];
				}

				if (array_key_exists('opcommand_hst', $operation)) {
					foreach ($operation['opcommand_hst'] as &$opcommand_hst) {
						unset($opcommand_hst['opcommand_hstid']);
					}
					unset($opcommand_hst);
				}
				else {
					$operation['opcommand_hst'] = [];
				}
			}

			unset($operation['operationid'], $operation['actionid'], $operation['eventsource'], $operation['recovery'],
				$operation['id']
			);
		}
		unset($operation);
	}

	$filter = [
		'conditions' => getRequest('conditions', []),
		'evaltype' => getRequest('evaltype')
	];

	if ($filter['conditions'] || hasRequest('update')) {
		if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
			if (count($filter['conditions']) > 1) {
				$filter['formula'] = getRequest('formula');
			}
			else {
				// If only one or no conditions are left, reset the evaltype to "and/or".
				$filter['evaltype'] = CONDITION_EVAL_TYPE_AND_OR;
			}
		}

		foreach ($filter['conditions'] as &$condition) {
			if ($filter['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
				unset($condition['formulaid']);
			}

			if ($condition['conditiontype'] == CONDITION_TYPE_SUPPRESSED) {
				unset($condition['value']);
			}

			if ($condition['conditiontype'] != CONDITION_TYPE_EVENT_TAG_VALUE) {
				unset($condition['value2']);
			}
		}
		unset($condition);

		$action['filter'] = $filter;
	}

	if (in_array($eventsource, [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
		$action['esc_period'] = getRequest('esc_period', DB::getDefault('actions', 'esc_period'));
	}

	if ($eventsource == EVENT_SOURCE_TRIGGERS) {
		$action['pause_suppressed'] = getRequest('pause_suppressed', ACTION_PAUSE_SUPPRESSED_FALSE);
		$action['notify_if_canceled'] = getRequest('notify_if_canceled', ACTION_NOTIFY_IF_CANCELED_FALSE);
	}

	switch ($eventsource) {
		case EVENT_SOURCE_DISCOVERY:
		case EVENT_SOURCE_AUTOREGISTRATION:
			unset($action['recovery_operations']);
			// break; is not missing here

		case EVENT_SOURCE_INTERNAL:
			unset($action['update_operations']);
			break;
	}

	DBstart();

	if (hasRequest('update')) {
		$action['actionid'] = getRequest('actionid');

		$result = API::Action()->update($action);

		$messageSuccess = _('Action updated');
		$messageFailed = _('Cannot update action');
	}
	else {
		$action['eventsource'] = $eventsource;

		$result = API::Action()->create($action);

		$messageSuccess = _('Action added');
		$messageFailed = _('Cannot add action');
	}

	if ($result) {
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

		// When adding new condition, in order to check for an existing condition, it must have a not null value.
		if ($newCondition['conditiontype'] == CONDITION_TYPE_SUPPRESSED) {
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
					if ($newCondition['value'] == $condition['value'] && (!array_key_exists('value2', $newCondition)
							|| $newCondition['value2'] === $condition['value2'])) {
						$newCondition['value'] = null;
					}
				}
			}
		}

		$usedFormulaIds = zbx_objectValues($conditions, 'formulaid');

		if (isset($newCondition['value'])) {
			$newConditionValues = zbx_toArray($newCondition['value']);
			foreach ($newConditionValues as $newValue) {
				$condition = $newCondition;
				$condition['value'] = $newValue;
				$condition['formulaid'] = CConditionHelper::getNextFormulaId($usedFormulaIds);
				$usedFormulaIds[] = $condition['formulaid'];
				$conditions[] = $condition;
			}
		}

		$_REQUEST['conditions'] = $conditions;
	}
}
elseif (hasRequest('add_operation') && hasRequest('new_operation')) {
	$new_operation = getRequest('new_operation');
	$result = true;

	$new_operation['eventsource'] = $eventsource;

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
			error(_s('Operation "%1$s" already exists.', operation_type2str($new_operation['operationtype'])));
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
elseif (hasRequest('add_recovery_operation') && hasRequest('new_recovery_operation')) {
	$new_recovery_operation = getRequest('new_recovery_operation');

	$new_recovery_operation['recovery'] = ACTION_RECOVERY_OPERATION;
	$new_recovery_operation['eventsource'] = $eventsource;

	$_REQUEST['recovery_operations'] = getRequest('recovery_operations', []);

	if (isset($_REQUEST['new_recovery_operation']['id'])) {
		$_REQUEST['recovery_operations'][$_REQUEST['new_recovery_operation']['id']] = $_REQUEST['new_recovery_operation'];
	}
	else {
		$_REQUEST['recovery_operations'][] = $_REQUEST['new_recovery_operation'];
	}

	unset($_REQUEST['new_recovery_operation']);
}
elseif (hasRequest('add_update_operation') && $new_update_operation) {
	$new_update_operation['recovery'] = ACTION_UPDATE_OPERATION;
	$new_update_operation['eventsource'] = $eventsource;

	if (array_key_exists('id', $new_update_operation)) {
		$update_operations[$new_update_operation['id']] = $new_update_operation;
	}
	else {
		$update_operations[] = $new_update_operation;
	}
	$new_update_operation = [];
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
elseif (hasRequest('edit_update_operationid')) {
	$edit_update_operationid = key(getRequest('edit_update_operationid'));

	if (array_key_exists($edit_update_operationid, $update_operations)) {
		$new_update_operation = $update_operations[$edit_update_operationid];
		$new_update_operation['id'] = $edit_update_operationid;
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

	$result = API::Action()->update($actions);

	if ($result && array_key_exists('actionids', $result)) {
		$message = $status == ACTION_STATUS_ENABLED
			? _n('Action enabled', 'Actions enabled', $actions_count)
			: _n('Action disabled', 'Actions disabled', $actions_count);

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

if (hasRequest('action') && hasRequest('g_actionid') && !$result) {
	$actions = API::Action()->get([
		'actionids' => getRequest('g_actionid'),
		'output' => [],
		'editable' => true
	]);
	uncheckTableRows(null, zbx_objectValues($actions, 'actionid'));
}

/*
 * Display
 */
show_messages();

if (hasRequest('form')) {
	$data = [
		'form' => getRequest('form'),
		'actionid' => getRequest('actionid', '0'),
		'eventsource' => $eventsource,
		'new_condition' => getRequest('new_condition', []),
		'new_operation' => getRequest('new_operation'),
		'new_recovery_operation' => getRequest('new_recovery_operation'),
		'new_update_operation' => $new_update_operation
	];

	if ($data['actionid']) {
		$data['action'] = API::Action()->get([
			'output' => ['actionid', 'name', 'eventsource', 'status', 'esc_period', 'pause_suppressed',
				'notify_if_canceled'
			],
			'selectFilter' => ['evaltype', 'formula', 'conditions'],
			'selectOperations' => ['operationtype', 'esc_period', 'esc_step_from', 'esc_step_to', 'evaltype',
				'opconditions', 'opmessage', 'opmessage_grp', 'opmessage_usr', 'opcommand', 'opcommand_grp',
				'opcommand_hst', 'opgroup', 'optemplate', 'opinventory'
			],
			'selectRecoveryOperations' => ['operationtype', 'opmessage', 'opmessage_grp', 'opmessage_usr', 'opcommand',
				'opcommand_grp', 'opcommand_hst'
			],
			'selectUpdateOperations' => ['operationtype', 'opmessage', 'opmessage_grp', 'opmessage_usr', 'opcommand',
				'opcommand_grp', 'opcommand_hst'
			],
			'actionids' => $data['actionid'],
			'editable' => true
		]);
		$data['action'] = reset($data['action']);
	}
	else {
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
		$data['action']['update_operations'] = $update_operations;

		$data['action']['filter']['evaltype'] = getRequest('evaltype');
		$data['action']['filter']['formula'] = getRequest('formula');
		$data['action']['filter']['conditions'] = getRequest('conditions', []);

		if ($data['actionid'] && hasRequest('form_refresh')) {
			if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
				$data['action']['pause_suppressed'] = getRequest('pause_suppressed', ACTION_PAUSE_SUPPRESSED_FALSE);
				$data['action']['notify_if_canceled'] = getRequest('notify_if_canceled',
					ACTION_NOTIFY_IF_CANCELED_FALSE
				);
			}
		}
		else {
			if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
				$data['action']['pause_suppressed'] = getRequest('pause_suppressed',
					hasRequest('form_refresh') ? ACTION_PAUSE_SUPPRESSED_FALSE : ACTION_PAUSE_SUPPRESSED_TRUE
				);
				$data['action']['notify_if_canceled'] = getRequest('notify_if_canceled',
					hasRequest('form_refresh') ? ACTION_NOTIFY_IF_CANCELED_FALSE : ACTION_NOTIFY_IF_CANCELED_TRUE
				);
			}
		}
	}

	foreach (['operations', 'recovery_operations', 'update_operations'] as $operations) {
		foreach ($data['action'][$operations] as &$operation) {
			if (($operation['operationtype'] == OPERATION_TYPE_MESSAGE
					|| $operation['operationtype'] == OPERATION_TYPE_RECOVERY_MESSAGE
					|| $operation['operationtype'] == OPERATION_TYPE_UPDATE_MESSAGE)
					&& !array_key_exists('default_msg', $operation['opmessage'])) {
				$operation['opmessage']['default_msg'] = 1;
				$operation['opmessage']['subject'] = '';
				$operation['opmessage']['message'] = '';
			}
		}
		unset($operation);
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

	// New recovery operation.
	if ($data['new_recovery_operation'] && !is_array($data['new_recovery_operation'])) {
		$data['new_recovery_operation'] = ['operationtype' => OPERATION_TYPE_MESSAGE];
	}

	if ($data['new_update_operation'] && !is_array($data['new_update_operation'])) {
		$data['new_update_operation'] = ['operationtype' => OPERATION_TYPE_MESSAGE];
	}
	if ($data['new_update_operation'] && !array_key_exists('opmessage', $data['new_update_operation'])
			&& $data['new_update_operation']['operationtype'] != OPERATION_TYPE_COMMAND) {
		$data['new_update_operation']['opmessage'] = [
			'default_msg' => 1,
			'mediatypeid' => 0,
			'subject' => '',
			'message' => ''
		];
	}

	// Render view.
	echo (new CView('configuration.action.edit', $data))->getOutput();
}
else {
	$eventsource = getRequest('eventsource', EVENT_SOURCE_TRIGGERS);

	if ($eventsource == EVENT_SOURCE_SERVICE) {
		$sortField = getRequest('sort', CProfile::get('web.service_actions.sort', 'name'));
		$sortOrder = getRequest('sortorder', CProfile::get('web.service_actions.sortorder', ZBX_SORT_UP));

		CProfile::update('web.service_actions.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.service_actions.sortorder', $sortOrder, PROFILE_TYPE_STR);

		if (hasRequest('filter_set')) {
			CProfile::update('web.service_actions.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.service_actions.filter_status', getRequest('filter_status', -1), PROFILE_TYPE_INT);
		}
		elseif (hasRequest('filter_rst')) {
			CProfile::delete('web.service_actions.filter_name');
			CProfile::delete('web.service_actions.filter_status');
		}

		$filter = [
			'name' => CProfile::get('web.service_actions.filter_name', ''),
			'status' => CProfile::get('web.service_actions.filter_status', -1)
		];

		$profile = 'web.service_actions.filter';
		$active_tab = 'web.service_actions.filter.active';
	}
	else {
		$sortField = getRequest('sort', CProfile::get('web.actionconf.php.sort', 'name'));
		$sortOrder = getRequest('sortorder', CProfile::get('web.actionconf.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.actionconf.php.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.actionconf.php.sortorder', $sortOrder, PROFILE_TYPE_STR);

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

		$profile = 'web.actionconf.filter';
		$active_tab = 'web.actionconf.filter.active';
	}

	$data = [
		'eventsource' => $eventsource,
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'filter' => $filter,
		'profileIdx' => $profile,
		'active_tab' => CProfile::get($active_tab, 1)
	];

	$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
	$data['actions'] = API::Action()->get([
		'output' => API_OUTPUT_EXTEND,
		'search' => [
			'name' => ($filter['name'] === '') ? null : $filter['name']
		],
		'filter' => [
			'eventsource' => $data['eventsource'],
			'status' => ($filter['status'] == -1) ? null : $filter['status']
		],
		'selectFilter' => ['formula', 'conditions', 'evaltype'],
		'selectOperations' => API_OUTPUT_EXTEND,
		'editable' => true,
		'sortfield' => $sortField,
		'limit' => $limit
	]);
	order_result($data['actions'], $sortField, $sortOrder);

	// pager
	if (hasRequest('page')) {
		$page_num = getRequest('page');
	}
	elseif (isRequestMethod('get') && !hasRequest('cancel')) {
		$page_num = 1;
	}
	else {
		$page_num = CPagerHelper::loadPage($page['file']);
	}

	CPagerHelper::savePage($page['file'], $page_num);

	$data['paging'] = CPagerHelper::paginate($page_num, $data['actions'], $sortOrder, (new CUrl('actionconf.php'))
		->setArgument('eventsource', $eventsource)
	);

	// render view
	echo (new CView('configuration.action.list', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';
