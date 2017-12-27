<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
require_once dirname(__FILE__).'/include/correlation.inc.php';

$page['title'] = _('Event correlation rules');
$page['file'] = 'correlation.php';
$page['scripts'] = ['multiselect.js'];

require_once dirname(__FILE__).'/include/page_header.php';
// VAR									TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'correlationid' =>					[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'],
	'name' =>							[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})',
											_('Name')
										],
	'description' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'evaltype' =>						[T_ZBX_INT, O_OPT, null,
											IN([CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND,
												CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION
											]),
											'isset({add}) || isset({update})'
										],
	'formula' =>						[T_ZBX_STR, O_OPT, null,   null,		'isset({add}) || isset({update})'],
	'status' =>							[T_ZBX_INT, O_OPT, null,
											IN([ZBX_CORRELATION_ENABLED, ZBX_CORRELATION_DISABLED]),
											null
										],
	'g_correlationid' =>				[T_ZBX_INT, O_OPT, null,	DB_ID,		'isset({action})'],
	'conditions' =>						[null,		O_OPT,	null,	null,		null],
	'new_condition' =>					[null,		O_OPT,	null,	null,		'isset({add_condition})'],
	'operations' =>						[null,		O_OPT,	null,	null,		null],
	'edit_operationid' =>				[T_ZBX_STR, O_OPT,	P_ACT,	null,		null],
	'new_operation' =>					[null,		O_OPT,	null,	null,		null],
	// actions
	'action' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
											IN('"correlation.massdelete","correlation.massdisable","correlation.massenable"'),
											null
										],
	'add_condition' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_operation' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>							[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'form' =>							[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'form_refresh' =>					[T_ZBX_INT, O_OPT, null,		null,	null],
	// filter
	'filter_set' =>						[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst' =>						[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_name' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_status' =>					[T_ZBX_INT, O_OPT, null,
											IN([-1, ZBX_CORRELATION_ENABLED, ZBX_CORRELATION_DISABLED]),
											null
										],
	// sort and sortorder
	'sort' =>							[T_ZBX_STR, O_OPT, P_SYS, IN('"name","status"'),						null],
	'sortorder' =>						[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];

check_fields($fields);

$correlationid = getRequest('correlationid');

if ($correlationid !== null) {
	$correlation = API::Correlation()->get([
		'output' => [],
		'correlationids' => [$correlationid],
		'editable' => true
	]);

	if (!$correlation) {
		access_deny();
	}
}

if (hasRequest('action')) {
	$correlations = API::Correlation()->get([
		'countOutput' => true,
		'correlationids' => getRequest('g_correlationid'),
		'editable' => true
	]);

	if ($correlations != count(getRequest('g_correlationid'))) {
		access_deny();
	}
}

if (hasRequest('add') || hasRequest('update')) {
	$correlation = [
		'name' => getRequest('name'),
		'description' => getRequest('description'),
		'status' => getRequest('status', ZBX_CORRELATION_DISABLED),
		'operations' => getRequest('operations', [])
	];

	$filter = [
		'conditions' => getRequest('conditions', []),
		'evaltype' => getRequest('evaltype')
	];

	if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
		if (count($filter['conditions']) > 1) {
			$filter['formula'] = getRequest('formula');
		}
		else {
			// If only one or no conditions are left, reset the evaltype to "and/or" and clear the formula.
			$filter['formula'] = '';
			$filter['evaltype'] = CONDITION_EVAL_TYPE_AND_OR;
		}
	}
	$correlation['filter'] = $filter;

	if (hasRequest('update')) {
		$correlation['correlationid'] = $correlationid;

		$result = API::Correlation()->update($correlation);

		$messageSuccess = _('Correlation updated');
		$messageFailed = _('Cannot update correlation');
	}
	else {
		$result = API::Correlation()->create($correlation);

		$messageSuccess = _('Correlation added');
		$messageFailed = _('Cannot add correlation');
	}

	if ($result) {
		uncheckTableRows();
		unset($_REQUEST['form']);
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('delete') && hasRequest('correlationid')) {
	$result = API::Correlation()->delete([getRequest('correlationid')]);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['correlationid']);
		uncheckTableRows();
	}
	show_messages($result, _('Correlation deleted'), _('Cannot delete correlation'));
}
elseif (hasRequest('add_condition') && hasRequest('new_condition')) {
	$new_condition = getRequest('new_condition');

	if ($new_condition) {
		$conditions = getRequest('conditions', []);

		// Add formulaid to new condition, so we can sort conditions.
		$used_formulaids = zbx_objectValues($conditions, 'formulaid');
		$new_condition['formulaid'] = CConditionHelper::getNextFormulaId($used_formulaids);
		$used_formulaids[] = $new_condition['formulaid'];

		// Check existing conditions and remove duplicate condition values.
		$valid_conditions = [];
		foreach ($conditions as $condition) {
			$valid_conditions[] = $condition;

			// Check if still exists in loop and if type is the same. Remove same values.
			if (isset($new_condition) && $new_condition['type'] == $condition['type']) {
				switch ($new_condition['type']) {
					case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
					case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
						if ($new_condition['tag'] === $condition['tag']) {
							unset($new_condition);
						}
						break;

					case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
						foreach ($new_condition['groupids'] as $i => $groupid) {
							if ($condition['groupid'] == $groupid) {
								unset($new_condition['groupids'][$i]);
							}
						}

						// If no group IDs are left, remove condition (adding will be skipped).
						if (!$new_condition['groupids']) {
							unset($new_condition);
						}
						break;

					case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
						if ($new_condition['oldtag'] === $condition['oldtag']
								&& $new_condition['newtag'] === $condition['newtag']) {
							unset($new_condition);
						}
						break;

					case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
					case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
						if ($new_condition['tag'] === $condition['tag']
								&& $new_condition['value'] === $condition['value']) {
							unset($new_condition);
						}
						break;
				}
			}
		}

		// Check if new condition is valid (tags cannot be empty) and host group IDs must be valid.
		if (isset($new_condition)) {
			switch ($new_condition['type']) {
				case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
				case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
					if ($new_condition['tag'] === '') {
						error(_s('Incorrect value for field "%1$s": %2$s.', 'tag', _('cannot be empty')));
						show_error_message(_('Cannot add correlation condition'));
					}
					else {
						$valid_conditions[] = $new_condition;
					}
					break;

				case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
					if (!$new_condition['groupids']) {
						error(_s('Incorrect value for field "%1$s": %2$s.', 'groupid', _('cannot be empty')));
						show_error_message(_('Cannot add correlation condition'));
					}
					else {
						foreach ($new_condition['groupids'] as $groupid) {
							if ($groupid == 0) {
								error(_s('Incorrect value for field "%1$s": %2$s.', 'groupid', _('cannot be empty')));
								show_error_message(_('Cannot add correlation condition'));
							}
							else {
								$valid_conditions[] = [
									'type' => $new_condition['type'],
									'operator' => $new_condition['operator'],
									'formulaid' => $new_condition['formulaid'],
									'groupid' => $groupid
								];

								$new_condition['formulaid'] = CConditionHelper::getNextFormulaId($used_formulaids);
								$used_formulaids[] = $new_condition['formulaid'];
							}
						}
					}
					break;

				case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
					if ($new_condition['oldtag'] === '') {
						error(_s('Incorrect value for field "%1$s": %2$s.', 'oldtag', _('cannot be empty')));
						show_error_message(_('Cannot add correlation condition'));
					}
					elseif ($new_condition['newtag'] === '') {
						error(_s('Incorrect value for field "%1$s": %2$s.', 'newtag', _('cannot be empty')));
						show_error_message(_('Cannot add correlation condition'));
					}
					else {
						$valid_conditions[] = $new_condition;
						unset($_REQUEST['new_condition']['oldtag']);
						unset($_REQUEST['new_condition']['newtag']);
					}
					break;

				case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
				case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
					if ($new_condition['tag'] === '') {
						error(_s('Incorrect value for field "%1$s": %2$s.', 'tag', _('cannot be empty')));
						show_error_message(_('Cannot add correlation condition'));
					}
					else {
						$valid_conditions[] = $new_condition;
						unset($_REQUEST['new_condition']['value']);
					}
					break;
			}
		}

		$_REQUEST['conditions'] = $valid_conditions;
	}
}
elseif (hasRequest('add_operation') && hasRequest('new_operation')) {
	$new_operation = getRequest('new_operation');
	$result = true;

	$_REQUEST['operations'] = getRequest('operations', []);

	$uniqOperations = [
		ZBX_CORR_OPERATION_CLOSE_OLD => 0,
		ZBX_CORR_OPERATION_CLOSE_NEW => 0
	];

	if (array_key_exists($new_operation['type'], $uniqOperations)) {
		$uniqOperations[$new_operation['type']]++;

		foreach ($_REQUEST['operations'] as $operationId => $operation) {
			if (array_key_exists($operation['type'], $uniqOperations)
					&& (!array_key_exists('id', $new_operation) || bccomp($new_operation['id'], $operationId) != 0)) {
				$uniqOperations[$operation['type']]++;
			}
		}

		if ($uniqOperations[$new_operation['type']] > 1) {
			$result = false;
			error(_s('Operation "%s" already exists.', corrOperationTypes($new_operation['type'])));
			show_messages();
		}
	}

	if ($result) {
		if (array_key_exists('id', $new_operation)) {
			$_REQUEST['operations'][$new_operation['id']] = $new_operation;
		}
		else {
			$_REQUEST['operations'][] = $new_operation;
		}

		CArrayHelper::sort($_REQUEST['operations'], ['type']);
	}

	unset($_REQUEST['new_operation']);
}
elseif (hasRequest('action')
		&& str_in_array(getRequest('action'), ['correlation.massenable', 'correlation.massdisable'])) {

	$enable = (getRequest('action') === 'correlation.massenable');
	$status = $enable ? ZBX_CORRELATION_ENABLED : ZBX_CORRELATION_DISABLED;

	$correlations_to_update = [];
	foreach (getRequest('g_correlationid') as $g_correlationid) {
		$correlations_to_update[] = [
			'correlationid' => $g_correlationid,
			'status' => $status
		];
	}

	$result = API::Correlation()->update($correlations_to_update);
	$updated = 0;

	if ($result) {
		$updated = count($result['correlationids']);
	}

	$messageSuccess = $enable
		? _n('Correlation enabled', 'Correlations enabled', $updated)
		: _n('Correlation disabled', 'Correlations disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable correlation', 'Cannot enable correlations', $updated)
		: _n('Cannot disable correlation', 'Cannot disable correlations', $updated);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') === 'correlation.massdelete') {
	$result = API::Correlation()->delete(getRequest('g_correlationid'));

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Selected correlations deleted'), _('Cannot delete selected correlations'));
}

/*
 * Display
 */
show_messages();

$config = select_config();

if (hasRequest('form')) {
	$data = [
		'form' => getRequest('form'),
		'correlationid' => $correlationid,
		'new_condition' => getRequest('new_condition', []),
		'new_operation' => getRequest('new_operation'),
		'config' => $config
	];

	if ($correlationid !== null) {
		$data['correlation'] = API::Correlation()->get([
			'output' => ['correlationid', 'name', 'description', 'status'],
			'correlationids' => [$correlationid],
			'selectFilter' => ['formula', 'conditions', 'evaltype', 'eval_formula'],
			'selectOperations' => ['type'],
			'editable' => true
		]);
		$data['correlation'] = reset($data['correlation']);
	}

	if (isset($data['correlation']['correlationid']) && !hasRequest('form_refresh')) {
		CArrayHelper::sort($data['correlation']['operations'], ['type']);
	}
	else {
		$data['correlation']['name'] = getRequest('name');
		$data['correlation']['description'] = getRequest('description');
		$data['correlation']['status'] = getRequest('status', hasRequest('form_refresh') ? 1 : 0);
		$data['correlation']['operations'] = getRequest('operations', []);
		$data['correlation']['filter']['evaltype'] = getRequest('evaltype');
		$data['correlation']['filter']['formula'] = getRequest('formula');
		$data['correlation']['filter']['conditions'] = getRequest('conditions', []);
	}

	$data['allowedConditions'] = corrConditionTypes();
	$data['allowedOperations'] = corrOperationTypes();

	if (!hasRequest('add_condition')) {
		$data['correlation']['filter']['conditions'] = CConditionHelper::sortConditionsByFormulaId(
			$data['correlation']['filter']['conditions']
		);
	}

	if ($data['new_condition']) {
		switch ($data['new_condition']['type']) {
			case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
				if (!array_key_exists('oldtag', $data['new_condition'])) {
					$data['new_condition']['oldtag'] = '';
				}

				if (!array_key_exists('newtag', $data['new_condition'])) {
					$data['new_condition']['newtag'] = '';
				}
				break;

			case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
				if (!array_key_exists('value', $data['new_condition'])) {
					$data['new_condition']['value'] = '';
				}
				break;
		}
	}
	else {
		$data['new_condition'] = [
			'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
			'operator' => CONDITION_OPERATOR_EQUAL,
			'tag' => ''
		];
	}

	// Render view.
	$correlationView = new CView('configuration.correlation.edit', $data);
	$correlationView->render();
	$correlationView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	// filter
	if (hasRequest('filter_set')) {
		CProfile::update('web.correlation.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
		CProfile::update('web.correlation.filter_status', getRequest('filter_status', -1), PROFILE_TYPE_INT);
	}
	elseif (hasRequest('filter_rst')) {
		CProfile::delete('web.correlation.filter_name');
		CProfile::delete('web.correlation.filter_status');
	}

	$filter = [
		'name' => CProfile::get('web.correlation.filter_name', ''),
		'status' => CProfile::get('web.correlation.filter_status', -1)
	];

	$data = [
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'filter' => $filter,
		'config' => $config
	];

	$data['correlations'] = API::Correlation()->get([
		'output' => ['correlationid', 'name', 'description', 'status'],
		'search' => [
			'name' => ($filter['name'] === '') ? null : $filter['name'],
		],
		'filter' => [
			'status' => ($filter['status'] == -1) ? null : $filter['status']
		],
		'selectFilter' => ['formula', 'conditions', 'evaltype', 'eval_formula'],
		'selectOperations' => ['type'],
		'editable' => true,
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	]);

	// sorting && paging
	order_result($data['correlations'], $sortField, $sortOrder);
	$data['paging'] = getPagingLine($data['correlations'], $sortOrder, new CUrl('correlation.php'));

	// Render view.
	$correlationView = new CView('configuration.correlation.list', $data);
	$correlationView->render();
	$correlationView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
