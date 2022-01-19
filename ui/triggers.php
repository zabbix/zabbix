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
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of triggers');
$page['file'] = 'triggers.php';
$page['scripts'] = ['class.tagfilteritem.js'];

require_once dirname(__FILE__).'/include/page_header.php';

// VAR											TYPE	OPTIONAL	FLAGS	VALIDATION		EXCEPTION
$fields = [
	'hostid' =>									[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null],
	'triggerid' =>								[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			'(isset({form}) && ({form} == "update"))'],
	'copy_type' =>								[T_ZBX_INT, O_OPT, P_SYS,
													IN([COPY_TYPE_TO_HOST_GROUP, COPY_TYPE_TO_HOST,
														COPY_TYPE_TO_TEMPLATE
													]),
													'isset({copy})'
												],
	'copy_mode' =>								[T_ZBX_INT, O_OPT, P_SYS,	IN('0'),		null],
	'type' =>									[T_ZBX_INT, O_OPT, null,	IN('0,1'),		null],
	'description' =>							[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'isset({add}) || isset({update})', _('Name')],
	'event_name' =>								[T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})'],
	'opdata' =>									[T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})'],
	'expression' =>								[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'isset({add}) || isset({update})', _('Expression')],
	'recovery_expression' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'(isset({add}) || isset({update})) && isset({recovery_mode}) && {recovery_mode} == '.ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION.'', _('Recovery expression')],
	'recovery_mode' =>							[T_ZBX_INT, O_OPT, null,	IN(ZBX_RECOVERY_MODE_EXPRESSION.','.ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION.','.ZBX_RECOVERY_MODE_NONE),	null],
	'priority' =>								[T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4,5'), 'isset({add}) || isset({update})'],
	'comments' =>								[T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})'],
	'url' =>									[T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})'],
	'correlation_mode' =>						[T_ZBX_STR, O_OPT, null,	IN(ZBX_TRIGGER_CORRELATION_NONE.','.ZBX_TRIGGER_CORRELATION_TAG),	null],
	'correlation_tag' =>						[T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})'],
	'status' =>									[T_ZBX_STR, O_OPT, null,	null,			null],
	'expression_constructor' =>					[T_ZBX_INT, O_OPT, null,	NOT_EMPTY,		'isset({toggle_expression_constructor})'],
	'recovery_expression_constructor' =>		[T_ZBX_INT, O_OPT, null,	NOT_EMPTY,		'isset({toggle_recovery_expression_constructor})'],
	'expr_temp' =>								[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'(isset({add_expression}) || isset({and_expression}) || isset({or_expression}) || isset({replace_expression}))', _('Expression')],
	'expr_target_single' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'(isset({and_expression}) || isset({or_expression}) || isset({replace_expression}))', _('Target')],
	'recovery_expr_temp' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'(isset({add_recovery_expression}) || isset({and_recovery_expression}) || isset({or_recovery_expression}) || isset({replace_recovery_expression}))', _('Recovery expression')],
	'recovery_expr_target_single' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'(isset({and_recovery_expression}) || isset({or_recovery_expression}) || isset({replace_recovery_expression}))', _('Target')],
	'dependencies' =>							[T_ZBX_INT, O_OPT, null,	DB_ID,			null],
	'new_dependency' =>							[T_ZBX_INT, O_OPT, null,	DB_ID.'{}>0',	'isset({add_dependency})'],
	'g_triggerid' =>							[T_ZBX_INT, O_OPT, null,	DB_ID,			null],
	'copy_targetids' =>							[T_ZBX_INT, O_OPT, null,	DB_ID,			null],
	'visible' =>								[T_ZBX_STR, O_OPT, null,	null,			null],
	'tags' =>									[T_ZBX_STR, O_OPT, null,	null,			null],
	'show_inherited_tags' =>					[T_ZBX_INT, O_OPT, null,	IN([0,1]),		null],
	'manual_close' =>							[T_ZBX_INT, O_OPT, null,
													IN([ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
														ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED
													]),
													null
												],
	'context' =>								[T_ZBX_STR, O_MAND, P_SYS,	IN('"host", "template"'),	null],
	// Filter related fields.
	'filter_set' =>								[T_ZBX_STR, O_OPT, P_SYS,	null,			null],
	'filter_rst' =>								[T_ZBX_STR, O_OPT, P_SYS,	null,			null],
	'filter_priority' =>						[T_ZBX_INT, O_OPT, null,
													IN([
														TRIGGER_SEVERITY_NOT_CLASSIFIED,
														TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_WARNING,
														TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH,
														TRIGGER_SEVERITY_DISASTER
													]), null
												],
	'filter_groupids' =>						[T_ZBX_INT, O_OPT, null, DB_ID, null],
	'filter_hostids' =>							[T_ZBX_INT, O_OPT, null, DB_ID, null],
	'filter_inherited' =>						[T_ZBX_INT, O_OPT, null, IN([-1, 0, 1]), null],
	'filter_discovered' =>						[T_ZBX_INT, O_OPT, null, IN([-1, 0, 1]), null],
	'filter_dependent' =>						[T_ZBX_INT, O_OPT, null, IN([-1, 0, 1]), null],
	'filter_name' =>							[T_ZBX_STR, O_OPT, null, null, null],
	'filter_state' =>							[T_ZBX_INT, O_OPT, null,
													IN([-1, TRIGGER_STATE_NORMAL, TRIGGER_STATE_UNKNOWN]), null
												],
	'filter_status' =>							[T_ZBX_INT, O_OPT, null,
													IN([-1, TRIGGER_STATUS_ENABLED, TRIGGER_STATUS_DISABLED]), null
												],
	'filter_value' =>							[T_ZBX_INT, O_OPT, null,
													IN([-1, TRIGGER_VALUE_FALSE, TRIGGER_VALUE_TRUE]), null
												],
	'filter_evaltype' =>						[T_ZBX_INT, O_OPT, null,
													IN([TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]), null
												],
	'filter_tags' =>							[T_ZBX_STR, O_OPT, null,	null,			null],
	// Action related fields.
	'action' =>									[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
													IN('"trigger.masscopyto","trigger.massdelete","trigger.massdisable",'.
														'"trigger.massenable"'
													),
													null
												],
	'toggle_expression_constructor' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'toggle_recovery_expression_constructor' =>	[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_expression' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_recovery_expression' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'and_expression' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'and_recovery_expression' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'or_expression' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'or_recovery_expression' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'replace_expression' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'replace_recovery_expression' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'remove_expression' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'remove_recovery_expression' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'test_expression' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_dependency' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'group_enable' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'group_disable' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'group_delete' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'copy' =>									[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>									[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add' =>									[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>									[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>									[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>									[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>									[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>							[T_ZBX_INT, O_OPT, null,	null,		null],
	'checkbox_hash' =>							[T_ZBX_STR, O_OPT, null,	null,		null],
	'backurl' =>								[T_ZBX_STR, O_OPT, null,	null,		null],
	// Sort and sortorder.
	'sort' =>									[T_ZBX_STR, O_OPT, P_SYS, IN('"description","priority","status"'),		null],
	'sortorder' =>								[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];

check_fields($fields);

$_REQUEST['status'] = isset($_REQUEST['status']) ? TRIGGER_STATUS_ENABLED : TRIGGER_STATUS_DISABLED;

// Validate permissions to single trigger.
$triggerId = getRequest('triggerid');

if ($triggerId !== null) {
	$trigger = API::Trigger()->get([
		'output' => ['triggerid'],
		'triggerids' => [$triggerId],
		'editable' => true
	]);

	if (!$trigger) {
		access_deny();
	}
}

// Validate permissions to a group of triggers for mass enable/disable actions.
$triggerIds = getRequest('g_triggerid', []);
$triggerIds = zbx_toArray($triggerIds);

if ($triggerIds) {
	$triggerIds = array_unique($triggerIds);

	$triggers = API::Trigger()->get([
		'output' => [],
		'triggerids' => $triggerIds,
		'editable' => true
	]);

	if (count($triggers) != count($triggerIds)) {
		uncheckTableRows(getRequest('checkbox_hash'), zbx_objectValues($triggers, 'triggerid'));
	}
}

if (getRequest('hostid') && !isWritableHostTemplates([getRequest('hostid')])) {
	access_deny();
}

// Validate backurl.
if (hasRequest('backurl') && !CHtmlUrlValidator::validateSameSite(getRequest('backurl'))) {
	access_deny();
}

$tags = getRequest('tags', []);

// Unset empty and inherited tags.
foreach ($tags as $key => $tag) {
	if ($tag['tag'] === '' && $tag['value'] === '') {
		unset($tags[$key]);
	}
	elseif (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN)) {
		unset($tags[$key]);
	}
	else {
		unset($tags[$key]['type']);
	}
}

/*
 * Actions
 */
$expression_action = '';
if (hasRequest('add_expression')) {
	$_REQUEST['expression'] = getRequest('expr_temp');
	$_REQUEST['expr_temp'] = '';
}
elseif (hasRequest('and_expression')) {
	$expression_action = 'and';
}
elseif (hasRequest('or_expression')) {
	$expression_action = 'or';
}
elseif (hasRequest('replace_expression')) {
	$expression_action = 'r';
}
elseif (hasRequest('remove_expression')) {
	$expression_action = 'R';
	$_REQUEST['expr_target_single'] = getRequest('remove_expression');
}

$recovery_expression_action = '';
if (hasRequest('add_recovery_expression')) {
	$_REQUEST['recovery_expression'] = getRequest('recovery_expr_temp');
	$_REQUEST['recovery_expr_temp'] = '';
}
elseif (hasRequest('and_recovery_expression')) {
	$recovery_expression_action = 'and';
}
elseif (hasRequest('or_recovery_expression')) {
	$recovery_expression_action = 'or';
}
elseif (hasRequest('replace_recovery_expression')) {
	$recovery_expression_action = 'r';
}
elseif (hasRequest('remove_recovery_expression')) {
	$recovery_expression_action = 'R';
	$_REQUEST['recovery_expr_target_single'] = getRequest('remove_recovery_expression');
}

if (hasRequest('clone') && hasRequest('triggerid')) {
	unset($_REQUEST['triggerid']);
	$_REQUEST['form'] = 'clone';
}
elseif (hasRequest('add') || hasRequest('update')) {
	$dependencies = zbx_toObject(getRequest('dependencies', []), 'triggerid');
	$description = getRequest('description', '');
	$event_name = getRequest('event_name', '');
	$opdata = getRequest('opdata', '');
	$expression = getRequest('expression', '');
	$recovery_mode = getRequest('recovery_mode', ZBX_RECOVERY_MODE_EXPRESSION);
	$recovery_expression = getRequest('recovery_expression', '');
	$type = getRequest('type', 0);
	$url = getRequest('url', '');
	$priority = getRequest('priority', TRIGGER_SEVERITY_NOT_CLASSIFIED);
	$comments = getRequest('comments', '');
	$correlation_mode = getRequest('correlation_mode', ZBX_TRIGGER_CORRELATION_NONE);
	$correlation_tag = getRequest('correlation_tag', '');
	$manual_close = getRequest('manual_close', ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED);
	$status = getRequest('status', TRIGGER_STATUS_ENABLED);

	if (hasRequest('add')) {
		$trigger = [
			'description' => $description,
			'event_name' => $event_name,
			'opdata' => $opdata,
			'expression' => $expression,
			'recovery_mode' => $recovery_mode,
			'type' => $type,
			'url' => $url,
			'priority' => $priority,
			'comments' => $comments,
			'tags' => $tags,
			'manual_close' => $manual_close,
			'dependencies' => $dependencies,
			'status' => $status
		];

		switch ($recovery_mode) {
			case ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION:
				$trigger['recovery_expression'] = $recovery_expression;
				// break; is not missing here.

			case ZBX_RECOVERY_MODE_EXPRESSION:
				$trigger['correlation_mode'] = $correlation_mode;

				if ($correlation_mode == ZBX_TRIGGER_CORRELATION_TAG) {
					$trigger['correlation_tag'] = $correlation_tag;
				}
				break;
		}

		$result = (bool) API::Trigger()->create($trigger);

		if ($result) {
			CMessageHelper::setSuccessTitle(_('Trigger added'));
		}
		else {
			CMessageHelper::setErrorTitle(_('Cannot add trigger'));
		}
	}
	else {
		$db_triggers = API::Trigger()->get([
			'output' => ['expression', 'description', 'url', 'status', 'priority', 'comments', 'templateid', 'type',
				'flags', 'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag', 'manual_close',
				'opdata', 'event_name'
			],
			'selectDependencies' => ['triggerid'],
			'selectTags' => ['tag', 'value'],
			'triggerids' => getRequest('triggerid')
		]);

		$db_triggers = CMacrosResolverHelper::resolveTriggerExpressions($db_triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		$db_trigger = reset($db_triggers);

		$trigger = [];

		if ($db_trigger['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
			if ($db_trigger['templateid'] == 0) {
				if ($db_trigger['description'] !== $description) {
					$trigger['description'] = $description;
				}
				if ($db_trigger['event_name'] !== $event_name) {
					$trigger['event_name'] = $event_name;
				}
				if ($db_trigger['opdata'] !== $opdata) {
					$trigger['opdata'] = $opdata;
				}
				if ($db_trigger['expression'] !== $expression) {
					$trigger['expression'] = $expression;
				}
				if ($db_trigger['recovery_mode'] != $recovery_mode) {
					$trigger['recovery_mode'] = $recovery_mode;
				}

				switch ($recovery_mode) {
					case ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION:
						if ($db_trigger['recovery_expression'] !== $recovery_expression) {
							$trigger['recovery_expression'] = $recovery_expression;
						}
						// break; is not missing here.
					case ZBX_RECOVERY_MODE_EXPRESSION:
						if ($db_trigger['correlation_mode'] != $correlation_mode) {
							$trigger['correlation_mode'] = $correlation_mode;
						}

						if ($correlation_mode == ZBX_TRIGGER_CORRELATION_TAG
								&& $db_trigger['correlation_tag'] !== $correlation_tag) {
							$trigger['correlation_tag'] = $correlation_tag;
						}
						break;
				}
			}

			if ($db_trigger['type'] != $type) {
				$trigger['type'] = $type;
			}
			if ($db_trigger['url'] !== $url) {
				$trigger['url'] = $url;
			}
			if ($db_trigger['priority'] != $priority) {
				$trigger['priority'] = $priority;
			}
			if ($db_trigger['comments'] !== $comments) {
				$trigger['comments'] = $comments;
			}

			$db_tags = $db_trigger['tags'];
			CArrayHelper::sort($db_tags, ['tag', 'value']);
			CArrayHelper::sort($tags, ['tag', 'value']);

			if (array_values($db_tags) !== array_values($tags)) {
				$trigger['tags'] = $tags;
			}

			if ($db_trigger['manual_close'] != $manual_close) {
				$trigger['manual_close'] = $manual_close;
			}

			$db_dependencies = $db_trigger['dependencies'];
			CArrayHelper::sort($db_dependencies, ['triggerid']);
			CArrayHelper::sort($dependencies, ['triggerid']);

			if (array_values($db_dependencies) !== array_values($dependencies)) {
				$trigger['dependencies'] = $dependencies;
			}
		}

		if ($db_trigger['status'] != $status) {
			$trigger['status'] = $status;
		}

		if ($trigger) {
			$trigger['triggerid'] = getRequest('triggerid');

			$result = (bool) API::Trigger()->update($trigger);
		}
		else {
			$result = true;
		}

		if ($result) {
			CMessageHelper::setSuccessTitle(_('Trigger updated'));
		}
		else {
			CMessageHelper::setErrorTitle(_('Cannot update trigger'));
		}
	}

	if ($result) {
		unset($_REQUEST['form']);
		uncheckTableRows(getRequest('checkbox_hash'));

		if (hasRequest('backurl')) {
			$response = new CControllerResponseRedirect(getRequest('backurl'));
			$response->redirect();
		}
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['triggerid'])) {
	$result = API::Trigger()->delete([getRequest('triggerid')]);

	if ($result) {
		CMessageHelper::setSuccessTitle(_('Trigger deleted'));
		unset($_REQUEST['form'], $_REQUEST['triggerid']);
		uncheckTableRows(getRequest('checkbox_hash'));

		if (hasRequest('backurl')) {
			$response = new CControllerResponseRedirect(getRequest('backurl'));
			$response->redirect();
		}
	}
	else {
		CMessageHelper::setErrorTitle(_('Cannot delete trigger'));
	}
}
elseif (isset($_REQUEST['add_dependency']) && isset($_REQUEST['new_dependency'])) {
	if (!isset($_REQUEST['dependencies'])) {
		$_REQUEST['dependencies'] = [];
	}

	foreach ($_REQUEST['new_dependency'] as $triggerid) {
		if (!uint_in_array($triggerid, $_REQUEST['dependencies'])) {
			array_push($_REQUEST['dependencies'], $triggerid);
		}
	}
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['trigger.massenable', 'trigger.massdisable']) && hasRequest('g_triggerid')) {
	$enable = (getRequest('action') === 'trigger.massenable');
	$status = $enable ? TRIGGER_STATUS_ENABLED : TRIGGER_STATUS_DISABLED;
	$update = [];

	// Get requested triggers with permission check.
	$dbTriggers = API::Trigger()->get([
		'output' => ['triggerid', 'status'],
		'triggerids' => getRequest('g_triggerid'),
		'editable' => true
	]);

	if ($dbTriggers) {
		foreach ($dbTriggers as $dbTrigger) {
			$update[] = [
				'triggerid' => $dbTrigger['triggerid'],
				'status' => $status
			];
		}

		$result = API::Trigger()->update($update);
	}
	else {
		$result = true;
	}

	$updated = count($update);
	$messageSuccess = $enable
		? _n('Trigger enabled', 'Triggers enabled', $updated)
		: _n('Trigger disabled', 'Triggers disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable trigger', 'Cannot enable triggers', $updated)
		: _n('Cannot disable trigger', 'Cannot disable triggers', $updated);

	if ($result) {
		uncheckTableRows(getRequest('checkbox_hash'));
		unset($_REQUEST['g_triggerid']);
	}

	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') === 'trigger.masscopyto' && hasRequest('copy')
		&& hasRequest('g_triggerid')) {

	if (getRequest('copy_targetids', []) && hasRequest('copy_type')) {

		// Hosts or templates.
		if (getRequest('copy_type') == COPY_TYPE_TO_HOST || getRequest('copy_type') == COPY_TYPE_TO_TEMPLATE) {
			$hosts_ids = getRequest('copy_targetids');
		}
		// Host groups.
		else {
			$hosts_ids = [];
			$group_ids = getRequest('copy_targetids');
			$db_hosts = DBselect(
				'SELECT DISTINCT h.hostid'.
				' FROM hosts h,hosts_groups hg'.
				' WHERE h.hostid=hg.hostid'.
					' AND '.dbConditionInt('hg.groupid', $group_ids)
			);

			while ($db_host = DBfetch($db_hosts)) {
				$hosts_ids[] = $db_host['hostid'];
			}
		}

		DBstart();

		$result = copyTriggersToHosts(getRequest('g_triggerid'), $hosts_ids, getRequest('hostid'));
		$result = DBend($result);

		$triggers_count = count(getRequest('g_triggerid'));

		if ($result) {
			uncheckTableRows(getRequest('checkbox_hash'));
			unset($_REQUEST['g_triggerid']);
		}

		show_messages($result,
			_n('Trigger copied', 'Triggers copied', $triggers_count),
			_n('Cannot copy trigger', 'Cannot copy triggers', $triggers_count)
		);
	}
	else {
		show_error_message(_('No target selected'));
	}
}
elseif (hasRequest('action') && getRequest('action') === 'trigger.massdelete' && hasRequest('g_triggerid')) {
	$result = API::Trigger()->delete(getRequest('g_triggerid'));

	if ($result) {
		uncheckTableRows(getRequest('checkbox_hash'));
	}

	show_messages($result, _('Triggers deleted'), _('Cannot delete triggers'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = [
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh'),
		'parent_discoveryid' => null,
		'dependencies' => getRequest('dependencies', []),
		'db_dependencies' => [],
		'triggerid' => getRequest('triggerid'),
		'expression' => getRequest('expression', ''),
		'recovery_expression' => getRequest('recovery_expression', ''),
		'expr_temp' => getRequest('expr_temp', ''),
		'recovery_expr_temp' => getRequest('recovery_expr_temp', ''),
		'recovery_mode' => getRequest('recovery_mode', 0),
		'description' => getRequest('description', ''),
		'event_name' => getRequest('event_name', ''),
		'opdata' => getRequest('opdata', ''),
		'type' => getRequest('type', 0),
		'priority' => getRequest('priority', TRIGGER_SEVERITY_NOT_CLASSIFIED),
		'status' => getRequest('status', TRIGGER_STATUS_ENABLED),
		'comments' => getRequest('comments', ''),
		'url' => getRequest('url', ''),
		'expression_constructor' => getRequest('expression_constructor', IM_ESTABLISHED),
		'recovery_expression_constructor' => getRequest('recovery_expression_constructor', IM_ESTABLISHED),
		'limited' => false,
		'templates' => [],
		'parent_templates' => [],
		'hostid' => getRequest('hostid', 0),
		'expression_action' => $expression_action,
		'recovery_expression_action' => $recovery_expression_action,
		'tags' => array_values($tags),
		'show_inherited_tags' => getRequest('show_inherited_tags', 0),
		'correlation_mode' => getRequest('correlation_mode', ZBX_TRIGGER_CORRELATION_NONE),
		'correlation_tag' => getRequest('correlation_tag', ''),
		'manual_close' => getRequest('manual_close', ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED),
		'context' => getRequest('context'),
		'backurl' => getRequest('backurl')
	];

	// render view
	echo (new CView('configuration.triggers.edit', getTriggerFormData($data)))->getOutput();
}
elseif (hasRequest('action') && getRequest('action') === 'trigger.masscopyto' && hasRequest('g_triggerid')) {
	$data = getCopyElementsFormData('g_triggerid', _('Triggers'));
	$data['action'] = 'trigger.masscopyto';

	// render view
	echo (new CView('configuration.copy.elements', $data))->getOutput();
}
else {
	$data = [
		'context' => getRequest('context')
	];

	$prefix = ($data['context'] === 'host') ? 'web.hosts.' : 'web.templates.';

	$filter_groupids_ms = [];
	$filter_hostids_ms = [];

	if (getRequest('filter_set')) {
		$filter_inherited = getRequest('filter_inherited', -1);
		$filter_discovered = getRequest('filter_discovered', -1);
		$filter_dependent = getRequest('filter_dependent', -1);
		$filter_name = getRequest('filter_name', '');
		$filter_priority = getRequest('filter_priority', []);
		$filter_groupids = getRequest('filter_groupids', []);
		$filter_hostids = getRequest('filter_hostids', []);
		$filter_state = getRequest('filter_state', -1);
		$filter_status = getRequest('filter_status', -1);
		$filter_value = getRequest('filter_value', -1);
		$filter_evaltype = getRequest('filter_evaltype', TAG_EVAL_TYPE_AND_OR);
		$filter_tags = getRequest('filter_tags', []);
	}
	elseif (getRequest('filter_rst')) {
		$filter_inherited = -1;
		$filter_discovered = -1;
		$filter_dependent = -1;
		$filter_name = '';
		$filter_priority = [];
		$filter_groupids = [];
		$filter_hostids = getRequest('filter_hostids', CProfile::getArray($prefix.'triggers.filter_hostids', []));
		if (count($filter_hostids) != 1) {
			$filter_hostids = [];
		}
		$filter_state = -1;
		$filter_status = -1;
		$filter_value = -1;
		$filter_evaltype = TAG_EVAL_TYPE_AND_OR;
		$filter_tags = [];
	}
	else {
		$filter_inherited = CProfile::get($prefix.'triggers.filter_inherited', -1);
		$filter_discovered = CProfile::get($prefix.'triggers.filter_discovered', -1);
		$filter_dependent = CProfile::get($prefix.'triggers.filter_dependent', -1);
		$filter_name = CProfile::get($prefix.'triggers.filter_name', '');
		$filter_priority = CProfile::getArray($prefix.'triggers.filter_priority', []);
		$filter_groupids = CProfile::getArray($prefix.'triggers.filter_groupids', []);
		$filter_hostids = CProfile::getArray($prefix.'triggers.filter_hostids', []);
		$filter_state = CProfile::get($prefix.'triggers.filter_state', -1);
		$filter_status = CProfile::get($prefix.'triggers.filter_status', -1);
		$filter_value = CProfile::get($prefix.'triggers.filter_value', -1);
		$filter_evaltype = CProfile::get($prefix.'triggers.filter.evaltype', TAG_EVAL_TYPE_AND_OR);

		$filter_tags = [];

		foreach (CProfile::getArray($prefix.'triggers.filter.tags.tag', []) as $i => $tag) {
			$filter_tags[] = [
				'tag' => $tag,
				'value' => CProfile::get($prefix.'triggers.filter.tags.value', null, $i),
				'operator' => CProfile::get($prefix.'triggers.filter.tags.operator', null, $i)
			];
		}
	}

	$filter_groupids_enriched =  [];
	if ($filter_groupids) {
		$filter_groupids = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $filter_groupids,
			'editable' => true,
			'preservekeys' => true
		]);
		$filter_groupids_ms = CArrayHelper::renameObjectsKeys($filter_groupids, ['groupid' => 'id']);
		$filter_groupids = array_keys($filter_groupids);
		$filter_groupids_enriched = getSubGroups($filter_groupids);
	}

	if ($filter_hostids) {
		if ($data['context'] === 'host') {
			$filter_hostids = API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $filter_hostids,
				'editable' => true,
				'preservekeys' => true
			]);

			$filter_hostids_ms = CArrayHelper::renameObjectsKeys($filter_hostids, ['hostid' => 'id']);
		}
		else {
			$filter_hostids = API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => $filter_hostids,
				'editable' => true,
				'preservekeys' => true
			]);

			$filter_hostids_ms = CArrayHelper::renameObjectsKeys($filter_hostids, ['templateid' => 'id']);
		}

		$filter_hostids = array_keys($filter_hostids_ms);
	}

	// Skip empty tags.
	$filter_tags = array_filter($filter_tags, function ($v) {
		return (bool) $v['tag'];
	});

	$sort = getRequest('sort', CProfile::get($prefix.$page['file'].'.sort', 'description'));
	$sortorder = getRequest('sortorder', CProfile::get($prefix.$page['file'].'.sortorder', ZBX_SORT_UP));
	$active_tab = CProfile::get($prefix.'triggers.filter.active', 1);

	// Get triggers (build options).
	$options = [
		'output' => ['triggerid', $sort],
		'hostids' => $filter_hostids ? $filter_hostids : null,
		'groupids' => $filter_groupids ? $filter_groupids_enriched : null,
		'editable' => true,
		'dependent' => ($filter_dependent != -1) ? $filter_dependent : null,
		'templated' => ($filter_value == -1) ? ($data['context'] === 'template') : false,
		'inherited' => ($filter_inherited != -1) ? $filter_inherited : null,
		'preservekeys' => true,
		'sortfield' => $sort,
		'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
	];

	if ($sort === 'status') {
		$options['output'][] = 'state';
	}

	if ($filter_discovered != -1) {
		$options['filter']['flags'] = ($filter_discovered == 1)
			? ZBX_FLAG_DISCOVERY_CREATED
			: ZBX_FLAG_DISCOVERY_NORMAL;
	}

	if ($filter_value != -1) {
		$options['filter']['value'] = $filter_value;
	}

	if ($filter_name !== '') {
		$options['search']['description'] = $filter_name;
	}
	if ($filter_priority) {
		$options['filter']['priority'] = $filter_priority;
	}

	switch ($filter_state) {
		case TRIGGER_STATE_NORMAL:
			$options['filter']['state'] = TRIGGER_STATE_NORMAL;
			$options['filter']['status'] = TRIGGER_STATUS_ENABLED;
			break;

		case TRIGGER_STATE_UNKNOWN:
			$options['filter']['state'] = TRIGGER_STATE_UNKNOWN;
			$options['filter']['status'] = TRIGGER_STATUS_ENABLED;
			break;

		default:
			if ($filter_status != -1) {
				$options['filter']['status'] = $filter_status;
			}
	}

	if ($filter_tags) {
		$options['evaltype'] = $filter_evaltype;
		$options['tags'] = $filter_tags;
	}

	$prefetched_triggers = API::Trigger()->get($options);
	if ($sort === 'status') {
		orderTriggersByStatus($prefetched_triggers, $sortorder);
	}
	else {
		order_result($prefetched_triggers, $sort, $sortorder);
	}

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

	$paging = CPagerHelper::paginate($page_num, $prefetched_triggers, $sortorder,
		(new CUrl('triggers.php'))->setArgument('context', $data['context'])
	);

	// fetch triggers
	$triggers = [];
	if ($prefetched_triggers) {
		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'error', 'templateid', 'state',
				'recovery_mode', 'recovery_expression', 'value', 'opdata', $sort
			],
			'selectHosts' => ['hostid', 'host', 'name', 'status'],
			'selectDependencies' => ['triggerid', 'description'],
			'selectDiscoveryRule' => ['itemid', 'name'],
			'selectTriggerDiscovery' => ['ts_delete'],
			'selectTags' => ['tag', 'value'],
			'triggerids' => array_keys($prefetched_triggers),
			'preservekeys' => true,
			'nopermissions' => true
		]);

		$items = API::Item()->get([
			'output' => ['itemid'],
			'selectTriggers' => ['triggerid'],
			'selectItemDiscovery' => ['ts_delete'],
			'triggerids' => array_keys($triggers),
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_CREATED]
		]);

		foreach ($items as $item) {
			$ts_delete = $item['itemDiscovery']['ts_delete'];

			if ($ts_delete == 0) {
				continue;
			}

			foreach (array_column($item['triggers'], 'triggerid') as $triggerid) {
				if (!array_key_exists($triggerid, $triggers)) {
					continue;
				}

				if (!array_key_exists('ts_delete', $triggers[$triggerid]['triggerDiscovery'])) {
					$triggers[$triggerid]['triggerDiscovery']['ts_delete'] = $ts_delete;
				}
				else {
					$trigger_ts_delete = $triggers[$triggerid]['triggerDiscovery']['ts_delete'];
					$triggers[$triggerid]['triggerDiscovery']['ts_delete'] = ($trigger_ts_delete > 0)
						? min($ts_delete, $trigger_ts_delete)
						: $ts_delete;
				}
			}
		}

		// We must maintain sort order that is applied on prefetched_triggers array.
		foreach ($triggers as $triggerid => $trigger) {
			$prefetched_triggers[$triggerid] = $trigger;
		}
		$triggers = $prefetched_triggers;
	}

	$show_info_column = false;
	$show_value_column = false;

	if ($data['context'] === 'host') {
		foreach ($triggers as $trigger) {
			foreach ($trigger['hosts'] as $trigger_host) {
				if (in_array($trigger_host['status'], [HOST_STATUS_NOT_MONITORED, HOST_STATUS_MONITORED])) {
					$show_value_column = true;
					$show_info_column = true;
					break 2;
				}
			}
		}
	}

	$dep_triggerids = [];
	foreach ($triggers as $trigger) {
		foreach ($trigger['dependencies'] as $dep_trigger) {
			$dep_triggerids[$dep_trigger['triggerid']] = true;
		}
	}

	$dep_triggers = [];
	if ($dep_triggerids) {
		$dep_triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'status', 'flags'],
			'selectHosts' => ['hostid', 'name'],
			'triggerids' => array_keys($dep_triggerids),
			'templated' => ($filter_value != -1) ? false : null,
			'preservekeys' => true
		]);

		foreach ($triggers as &$trigger) {
			order_result($trigger['dependencies'], 'description', ZBX_SORT_UP);
		}
		unset($trigger);

		foreach ($dep_triggers as &$dependencyTrigger) {
			order_result($dependencyTrigger['hosts'], 'name', ZBX_SORT_UP);
		}
		unset($dependencyTrigger);
	}

	CProfile::update($prefix.$page['file'].'.sort', $sort, PROFILE_TYPE_STR);
	CProfile::update($prefix.$page['file'].'.sortorder', $sortorder, PROFILE_TYPE_STR);

	if (getRequest('filter_set')) {
		CProfile::update($prefix.'triggers.filter_inherited', $filter_inherited, PROFILE_TYPE_INT);
		CProfile::update($prefix.'triggers.filter_discovered', $filter_discovered, PROFILE_TYPE_INT);
		CProfile::update($prefix.'triggers.filter_dependent', $filter_dependent, PROFILE_TYPE_INT);
		CProfile::update($prefix.'triggers.filter_name', $filter_name, PROFILE_TYPE_STR);
		CProfile::updateArray($prefix.'triggers.filter_priority', $filter_priority, PROFILE_TYPE_INT);
		CProfile::updateArray($prefix.'triggers.filter_groupids', $filter_groupids, PROFILE_TYPE_ID);
		CProfile::updateArray($prefix.'triggers.filter_hostids', $filter_hostids, PROFILE_TYPE_ID);
		CProfile::update($prefix.'triggers.filter_state', $filter_state, PROFILE_TYPE_INT);
		CProfile::update($prefix.'triggers.filter_status', $filter_status, PROFILE_TYPE_INT);
		CProfile::update($prefix.'triggers.filter.evaltype', $filter_evaltype, PROFILE_TYPE_INT);

		$filter_tags_fmt = ['tags' => [], 'values' => [], 'operators' => []];

		foreach ($filter_tags as $filter_tag) {
			if ($filter_tag['tag'] === '' && $filter_tag['value'] === '') {
				continue;
			}

			$filter_tags_fmt['tags'][] = $filter_tag['tag'];
			$filter_tags_fmt['values'][] = $filter_tag['value'];
			$filter_tags_fmt['operators'][] = $filter_tag['operator'];
		}

		CProfile::updateArray($prefix.'triggers.filter.tags.tag', $filter_tags_fmt['tags'], PROFILE_TYPE_STR);
		CProfile::updateArray($prefix.'triggers.filter.tags.value', $filter_tags_fmt['values'], PROFILE_TYPE_STR);
		CProfile::updateArray($prefix.'triggers.filter.tags.operator', $filter_tags_fmt['operators'], PROFILE_TYPE_INT);

		if ($show_value_column) {
			CProfile::update($prefix.'triggers.filter_value', $filter_value, PROFILE_TYPE_INT);
		}
	}
	elseif (getRequest('filter_rst')) {
		CProfile::deleteIdx($prefix.'triggers.filter_inherited');
		CProfile::deleteIdx($prefix.'triggers.filter_discovered');
		CProfile::deleteIdx($prefix.'triggers.filter_dependent');
		CProfile::deleteIdx($prefix.'triggers.filter_name');
		CProfile::deleteIdx($prefix.'triggers.filter_priority');
		CProfile::deleteIdx($prefix.'triggers.filter_groupids');

		if (count($filter_hostids) != 1) {
			CProfile::deleteIdx($prefix.'triggers.filter_hostids');
		}

		CProfile::deleteIdx($prefix.'triggers.filter_state');
		CProfile::deleteIdx($prefix.'triggers.filter_status');
		CProfile::deleteIdx($prefix.'triggers.filter.evaltype');
		CProfile::deleteIdx($prefix.'triggers.filter.tags.tag');
		CProfile::deleteIdx($prefix.'triggers.filter.tags.value');
		CProfile::deleteIdx($prefix.'triggers.filter.tags.operator');

		if ($show_value_column) {
			CProfile::deleteIdx($prefix.'triggers.filter_value');
		}
	}

	$single_selected_hostid = 0;
	if (count($filter_hostids) == 1) {
		$single_selected_hostid = reset($filter_hostids);
	}

	sort($filter_hostids);
	$checkbox_hash = crc32(implode('', $filter_hostids));

	$data += [
		'triggers' => $triggers,
		'profileIdx' => $prefix.'triggers.filter',
		'active_tab' => $active_tab,
		'sort' => $sort,
		'sortorder' => $sortorder,
		'filter_groupids_ms' => $filter_groupids_ms,
		'filter_hostids_ms' => $filter_hostids_ms,
		'filter_name' => $filter_name,
		'filter_priority' => $filter_priority,
		'filter_state' => $filter_state,
		'filter_status' => $filter_status,
		'filter_value' => $filter_value,
		'filter_tags' => $filter_tags,
		'filter_evaltype' => $filter_evaltype,
		'filter_inherited' => $filter_inherited,
		'filter_discovered' => $filter_discovered,
		'filter_dependent' => $filter_dependent,
		'checkbox_hash' => $checkbox_hash,
		'show_info_column' => $show_info_column,
		'show_value_column' => $show_value_column,
		'single_selected_hostid' => $single_selected_hostid,
		'parent_templates' => getTriggerParentTemplates($triggers, ZBX_FLAG_DISCOVERY_NORMAL),
		'paging' => $paging,
		'dep_triggers' => $dep_triggers,
		'tags' => makeTags($triggers, true, 'triggerid', ZBX_TAG_COUNT_DEFAULT, $filter_tags),
		'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
	];

	// render view
	echo (new CView('configuration.triggers.list', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';
