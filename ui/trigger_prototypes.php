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

$page['title'] = _('Configuration of trigger prototypes');
$page['file'] = 'trigger_prototypes.php';

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR											TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'parent_discoveryid' =>						[T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null],
	'triggerid' =>								[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'(isset({form}) && ({form} == "update"))'],
	'type' =>									[T_ZBX_INT, O_OPT, null,	IN('0,1'),	null],
	'description' =>							[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Name')],
	'event_name' =>								[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'opdata' =>									[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'expression' =>								[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Expression')],
	'recovery_expression' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'(isset({add}) || isset({update})) && isset({recovery_mode}) && {recovery_mode} == '.ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION.'', _('Recovery expression')],
	'recovery_mode' =>							[T_ZBX_INT, O_OPT, null,	IN(ZBX_RECOVERY_MODE_EXPRESSION.','.ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION.','.ZBX_RECOVERY_MODE_NONE),	null],
	'priority' =>								[T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4,5'), 'isset({add}) || isset({update})'],
	'comments' =>								[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'url' =>									[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'correlation_mode' =>						[T_ZBX_STR, O_OPT, null,	IN(ZBX_TRIGGER_CORRELATION_NONE.','.ZBX_TRIGGER_CORRELATION_TAG),	null],
	'correlation_tag' =>						[T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})'],
	'status' =>									[T_ZBX_STR, O_OPT, null,	null,		null],
	'discover' =>								[T_ZBX_INT, O_OPT, null,	IN([ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]), null],
	'expression_constructor' =>					[T_ZBX_INT, O_OPT, null,	NOT_EMPTY,	'isset({toggle_expression_constructor})'],
	'recovery_expression_constructor' =>		[T_ZBX_INT, O_OPT, null,	NOT_EMPTY,		'isset({toggle_recovery_expression_constructor})'],
	'expr_temp' =>								[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({add_expression}) || isset({and_expression}) || isset({or_expression}) || isset({replace_expression}))', _('Expression')],
	'expr_target_single' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({and_expression}) || isset({or_expression}) || isset({replace_expression}))', _('Target')],
	'recovery_expr_temp' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'(isset({add_recovery_expression}) || isset({and_recovery_expression}) || isset({or_recovery_expression}) || isset({replace_recovery_expression}))', _('Recovery expression')],
	'recovery_expr_target_single' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'(isset({and_recovery_expression}) || isset({or_recovery_expression}) || isset({replace_recovery_expression}))', _('Target')],
	'dependencies' =>							[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'new_dependency' =>							[T_ZBX_INT, O_OPT, null,	DB_ID.NOT_ZERO, 'isset({add_dependency})'],
	'g_triggerid' =>							[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'tags' =>									[T_ZBX_STR, O_OPT, null,	null,		null],
	'show_inherited_tags' =>					[T_ZBX_INT, O_OPT, null,	IN([0,1]),	null],
	'manual_close' =>							[T_ZBX_INT, O_OPT, null,
													IN([ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
														ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED
													]),
													null
												],
	'context' =>								[T_ZBX_STR, O_MAND, P_SYS,	IN('"host", "template"'),	null],
	// actions
	'action' =>									[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
													IN('"triggerprototype.massdelete","triggerprototype.massdisable",'.
														'"triggerprototype.massenable","triggerprototype.discover.enable",'.
														'"triggerprototype.discover.disable"'
													),
													null
												],
	'visible' =>								[T_ZBX_STR, O_OPT, null,	null,		null],
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
	'backurl' =>								[T_ZBX_STR, O_OPT, null,	null,		null],
	// sort and sortorder
	'sort' =>									[T_ZBX_STR, O_OPT, P_SYS, IN('"description","priority","status","discover"'),		null],
	'sortorder' =>								[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];

check_fields($fields);

$_REQUEST['status'] = isset($_REQUEST['status']) ? TRIGGER_STATUS_ENABLED : TRIGGER_STATUS_DISABLED;

// validate permissions
$discoveryRule = API::DiscoveryRule()->get([
	'output' => ['name', 'itemid', 'hostid'],
	'itemids' => getRequest('parent_discoveryid'),
	'editable' => true
]);
$discoveryRule = reset($discoveryRule);

if (!$discoveryRule) {
	access_deny();
}

if (hasRequest('triggerid')) {
	$triggerPrototypes = API::TriggerPrototype()->get([
		'output' => [],
		'triggerids' => getRequest('triggerid'),
		'editable' => true
	]);

	if (!$triggerPrototypes) {
		access_deny();
	}
}

// Validate backurl.
if (hasRequest('backurl') && !CHtmlUrlValidator::validateSameSite(getRequest('backurl'))) {
	access_deny();
}

$tags = getRequest('tags', []);
foreach ($tags as $key => $tag) {
	// remove empty new tag lines
	if ($tag['tag'] === '' && $tag['value'] === '') {
		unset($tags[$key]);
		continue;
	}

	// remove inherited tags
	if (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN)) {
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
	$discover = getRequest('discover', DB::getDefault('triggers', 'discover'));

	if (hasRequest('add')) {
		$trigger_prototype = [
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
			'status' => $status,
			'discover' => $discover
		];
		switch ($recovery_mode) {
			case ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION:
				$trigger_prototype['recovery_expression'] = $recovery_expression;
				// break; is not missing here

			case ZBX_RECOVERY_MODE_EXPRESSION:
				$trigger_prototype['correlation_mode'] = $correlation_mode;
				if ($correlation_mode == ZBX_TRIGGER_CORRELATION_TAG) {
					$trigger_prototype['correlation_tag'] = $correlation_tag;
				}
				break;
		}

		$result = (bool) API::TriggerPrototype()->create($trigger_prototype);

		if ($result) {
			CMessageHelper::setSuccessTitle(_('Trigger prototype added'));
		}
		else {
			CMessageHelper::setErrorTitle(_('Cannot add trigger prototype'));
		}
	}
	else {
		$db_trigger_prototypes = API::TriggerPrototype()->get([
			'output' => ['expression', 'description', 'url', 'status', 'priority', 'comments', 'templateid', 'type',
				'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag', 'manual_close', 'opdata',
				'discover', 'event_name'
			],
			'selectDependencies' => ['triggerid'],
			'selectTags' => ['tag', 'value'],
			'triggerids' => getRequest('triggerid')
		]);

		$db_trigger_prototypes = CMacrosResolverHelper::resolveTriggerExpressions($db_trigger_prototypes,
			['sources' => ['expression', 'recovery_expression']]
		);

		$db_trigger_prototype = reset($db_trigger_prototypes);

		$trigger_prototype = [];

		if ($db_trigger_prototype['templateid'] == 0) {
			if ($db_trigger_prototype['description'] !== $description) {
				$trigger_prototype['description'] = $description;
			}
			if ($db_trigger_prototype['event_name'] !== $event_name) {
				$trigger_prototype['event_name'] = $event_name;
			}
			if ($db_trigger_prototype['opdata'] !== $opdata) {
				$trigger_prototype['opdata'] = $opdata;
			}
			if ($db_trigger_prototype['expression'] !== $expression) {
				$trigger_prototype['expression'] = $expression;
			}
			if ($db_trigger_prototype['recovery_mode'] != $recovery_mode) {
				$trigger_prototype['recovery_mode'] = $recovery_mode;
			}
			switch ($recovery_mode) {
				case ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION:
					if ($db_trigger_prototype['recovery_expression'] !== $recovery_expression) {
						$trigger_prototype['recovery_expression'] = $recovery_expression;
					}
					// break; is not missing here

				case ZBX_RECOVERY_MODE_EXPRESSION:
					if ($db_trigger_prototype['correlation_mode'] != $correlation_mode) {
						$trigger_prototype['correlation_mode'] = $correlation_mode;
					}
					if ($correlation_mode == ZBX_TRIGGER_CORRELATION_TAG
							&& $db_trigger_prototype['correlation_tag'] !== $correlation_tag) {
						$trigger_prototype['correlation_tag'] = $correlation_tag;
					}
					break;
			}
		}

		if ($db_trigger_prototype['type'] != $type) {
			$trigger_prototype['type'] = $type;
		}
		if ($db_trigger_prototype['url'] !== $url) {
			$trigger_prototype['url'] = $url;
		}
		if ($db_trigger_prototype['priority'] != $priority) {
			$trigger_prototype['priority'] = $priority;
		}
		if ($db_trigger_prototype['comments'] !== $comments) {
			$trigger_prototype['comments'] = $comments;
		}

		$db_tags = $db_trigger_prototype['tags'];
		CArrayHelper::sort($db_tags, ['tag', 'value']);
		CArrayHelper::sort($tags, ['tag', 'value']);
		if (array_values($db_tags) !== array_values($tags)) {
			$trigger_prototype['tags'] = $tags;
		}

		if ($db_trigger_prototype['manual_close'] != $manual_close) {
			$trigger_prototype['manual_close'] = $manual_close;
		}

		$db_dependencies = $db_trigger_prototype['dependencies'];
		CArrayHelper::sort($db_dependencies, ['triggerid']);
		CArrayHelper::sort($dependencies, ['triggerid']);
		if (array_values($db_dependencies) !== array_values($dependencies)) {
			$trigger_prototype['dependencies'] = $dependencies;
		}

		if ($db_trigger_prototype['status'] != $status) {
			$trigger_prototype['status'] = $status;
		}
		if ($db_trigger_prototype['discover'] != $discover) {
			$trigger_prototype['discover'] = $discover;
		}

		if ($trigger_prototype) {
			$trigger_prototype['triggerid'] = getRequest('triggerid');

			$result = (bool) API::TriggerPrototype()->update($trigger_prototype);
		}
		else {
			$result = true;
		}

		if ($result) {
			CMessageHelper::setSuccessTitle(_('Trigger prototype updated'));
		}
		else {
			CMessageHelper::setErrorTitle(_('Cannot update trigger prototype'));
		}
	}

	if ($result) {
		unset($_REQUEST['form']);
		uncheckTableRows(getRequest('parent_discoveryid'));

		if (hasRequest('backurl')) {
			$response = new CControllerResponseRedirect(getRequest('backurl'));
			$response->redirect();
		}
	}
}
elseif (hasRequest('delete') && hasRequest('triggerid')) {
	$result = API::TriggerPrototype()->delete([getRequest('triggerid')]);

	if ($result) {
		CMessageHelper::setSuccessTitle(_('Trigger prototype deleted'));
		unset($_REQUEST['form'], $_REQUEST['triggerid']);
		uncheckTableRows(getRequest('parent_discoveryid'));

		if (hasRequest('backurl')) {
			$response = new CControllerResponseRedirect(getRequest('backurl'));
			$response->redirect();
		}
	}
	else {
		CMessageHelper::setErrorTitle(_('Cannot delete trigger prototype'));
	}
}
elseif (hasRequest('add_dependency') && hasRequest('new_dependency')) {
	if (!hasRequest('dependencies')) {
		$_REQUEST['dependencies'] = [];
	}
	foreach (getRequest('new_dependency') as $triggerId) {
		if (!uint_in_array($triggerId, getRequest('dependencies'))) {
			$_REQUEST['dependencies'][] = $triggerId;
		}
	}
}
elseif (getRequest('action') && str_in_array(getRequest('action'), ['triggerprototype.massenable', 'triggerprototype.massdisable']) && hasRequest('g_triggerid')) {
	$status = (getRequest('action') === 'triggerprototype.massenable')
		? TRIGGER_STATUS_ENABLED
		: TRIGGER_STATUS_DISABLED;
	$update = [];

	// get requested triggers with permission check
	$dbTriggerPrototypes = API::TriggerPrototype()->get([
		'output' => ['triggerid', 'status'],
		'triggerids' => getRequest('g_triggerid'),
		'editable' => true
	]);

	if ($dbTriggerPrototypes) {
		foreach ($dbTriggerPrototypes as $dbTriggerPrototype) {
			$update[] = [
				'triggerid' => $dbTriggerPrototype['triggerid'],
				'status' => $status
			];
		}

		$result = API::TriggerPrototype()->update($update);
	}
	else {
		$result = true;
	}

	if ($result) {
		uncheckTableRows(getRequest('parent_discoveryid'));
	}

	$updated = count($update);

	$messageSuccess = _n('Trigger prototype updated', 'Trigger prototypes updated', $updated);
	$messageFailed = _n('Cannot update trigger prototype', 'Cannot update trigger prototypes', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') === 'triggerprototype.massdelete' && hasRequest('g_triggerid')) {
	$result = API::TriggerPrototype()->delete(getRequest('g_triggerid'));

	if ($result) {
		uncheckTableRows(getRequest('parent_discoveryid'));
	}
	show_messages($result, _('Trigger prototypes deleted'), _('Cannot delete trigger prototypes'));
}
elseif (getRequest('action') && hasRequest('g_triggerid')
		&& in_array(getRequest('action'), ['triggerprototype.discover.enable', 'triggerprototype.discover.disable'])) {
	$triggerids = getRequest('g_triggerid');
	$discover = (getRequest('action') === 'triggerprototype.discover.enable')
		? TRIGGER_DISCOVER
		: TRIGGER_NO_DISCOVER;
	$update = [];

	// Get requested triggers with permission check.
	$db_trigger_prototypes = API::TriggerPrototype()->get([
		'output' => [],
		'triggerids' => getRequest('g_triggerid'),
		'editable' => true
	]);

	if ($db_trigger_prototypes) {
		foreach ($db_trigger_prototypes as $db_trigger_prototype) {
			$update[] = [
				'triggerid' => $db_trigger_prototype['triggerid'],
				'discover' => $discover
			];
		}

		$result = API::TriggerPrototype()->update($update);
	}
	else {
		$result = true;
	}

	if ($result) {
		uncheckTableRows(getRequest('parent_discoveryid'));
	}

	$updated = count($update);

	$messageSuccess = _n('Trigger prototype updated', 'Trigger prototypes updated', $updated);
	$messageFailed = _n('Cannot update trigger prototype', 'Cannot update trigger prototypes', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = getTriggerFormData([
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh'),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'dependencies' => getRequest('dependencies', []),
		'db_dependencies' => [],
		'triggerid' => getRequest('triggerid'),
		'expression' => getRequest('expression', ''),
		'recovery_expression' => getRequest('recovery_expression', ''),
		'expr_temp' => getRequest('expr_temp', ''),
		'recovery_expr_temp' => getRequest('recovery_expr_temp', ''),
		'recovery_mode' => getRequest('recovery_mode', ZBX_RECOVERY_MODE_EXPRESSION),
		'description' => getRequest('description', ''),
		'event_name' => getRequest('event_name', ''),
		'opdata' => getRequest('opdata', ''),
		'type' => getRequest('type', 0),
		'priority' => getRequest('priority', TRIGGER_SEVERITY_NOT_CLASSIFIED),
		'status' => getRequest('status', TRIGGER_STATUS_ENABLED),
		'discover' => getRequest('discover', DB::getDefault('triggers', 'discover')),
		'comments' => getRequest('comments', ''),
		'url' => getRequest('url', ''),
		'expression_constructor' => getRequest('expression_constructor', IM_ESTABLISHED),
		'recovery_expression_constructor' => getRequest('recovery_expression_constructor', IM_ESTABLISHED),
		'limited' => false,
		'templates' => [],
		'parent_templates' => [],
		'hostid' => $discoveryRule['hostid'],
		'expression_action' => $expression_action,
		'recovery_expression_action' => $recovery_expression_action,
		'tags' => array_values($tags),
		'show_inherited_tags' => getRequest('show_inherited_tags', 0),
		'correlation_mode' => getRequest('correlation_mode', ZBX_TRIGGER_CORRELATION_NONE),
		'correlation_tag' => getRequest('correlation_tag', ''),
		'manual_close' => getRequest('manual_close', ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED),
		'context' => getRequest('context'),
		'backurl' => getRequest('backurl')
	]);

	// render view
	echo (new CView('configuration.trigger.prototype.edit', $data))->getOutput();
}
else {
	$prefix = (getRequest('context') === 'host') ? 'web.hosts.' : 'web.templates.';

	$sortField = getRequest('sort', CProfile::get($prefix.$page['file'].'.sort', 'description'));
	$sortOrder = getRequest('sortorder', CProfile::get($prefix.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update($prefix.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update($prefix.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$data = [
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'discovery_rule' => $discoveryRule,
		'hostid' => $discoveryRule['hostid'],
		'triggers' => [],
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'dependencyTriggers' => [],
		'context' => getRequest('context')
	];

	// get triggers
	$options = [
		'editable' => true,
		'output' => ['triggerid', $sortField],
		'discoveryids' => $data['parent_discoveryid'],
		'sortfield' => $sortField,
		'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
	];
	$data['triggers'] = API::TriggerPrototype()->get($options);

	order_result($data['triggers'], $sortField, $sortOrder);

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

	$data['paging'] = CPagerHelper::paginate($page_num, $data['triggers'], $sortOrder,
		(new CUrl('trigger_prototypes.php'))
			->setArgument('parent_discoveryid', $data['parent_discoveryid'])
			->setArgument('context', $data['context'])
	);

	$data['triggers'] = API::TriggerPrototype()->get([
		'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'templateid', 'recovery_mode',
			'recovery_expression', 'opdata', 'discover'
		],
		'selectHosts' => ['hostid', 'host'],
		'selectDependencies' => ['triggerid', 'description'],
		'selectTags' => ['tag', 'value'],
		'triggerids' => zbx_objectValues($data['triggers'], 'triggerid')
	]);
	order_result($data['triggers'], $sortField, $sortOrder);

	$data['tags'] = makeTags($data['triggers'], true, 'triggerid');

	$depTriggerIds = [];
	foreach ($data['triggers'] as $trigger) {
		foreach ($trigger['dependencies'] as $depTrigger) {
			$depTriggerIds[$depTrigger['triggerid']] = true;
		}
	}

	if ($depTriggerIds) {
		$dependencyTriggers = [];
		$dependencyTriggerPrototypes = [];

		$depTriggerIds = array_keys($depTriggerIds);

		$dependencyTriggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'status', 'flags'],
			'selectHosts' => ['hostid', 'name'],
			'triggerids' => $depTriggerIds,
			'filter' => [
				'flags' => [ZBX_FLAG_DISCOVERY_NORMAL]
			],
			'preservekeys' => true
		]);

		$dependencyTriggerPrototypes = API::TriggerPrototype()->get([
			'output' => ['triggerid', 'description', 'status', 'flags'],
			'selectHosts' => ['hostid', 'name'],
			'triggerids' => $depTriggerIds,
			'preservekeys' => true
		]);

		$data['dependencyTriggers'] = $dependencyTriggers + $dependencyTriggerPrototypes;

		foreach ($data['triggers'] as &$trigger) {
			order_result($trigger['dependencies'], 'description', ZBX_SORT_UP);
		}
		unset($trigger);

		foreach ($data['dependencyTriggers'] as &$dependencyTrigger) {
			order_result($dependencyTrigger['hosts'], 'name', ZBX_SORT_UP);
		}
		unset($dependencyTrigger);
	}

	$data['parent_templates'] = getTriggerParentTemplates($data['triggers'], ZBX_FLAG_DISCOVERY_PROTOTYPE);
	$data['allowed_ui_conf_templates'] = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);

	// Render view.
	echo (new CView('configuration.trigger.prototype.list', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';
