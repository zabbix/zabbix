<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	'expression' =>								[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Expression')],
	'recovery_expression' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'(isset({add}) || isset({update})) && isset({recovery_mode}) && {recovery_mode} == '.ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION.'', _('Recovery expression')],
	'recovery_mode' =>							[T_ZBX_INT, O_OPT, null,	IN(ZBX_RECOVERY_MODE_EXPRESSION.','.ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION.','.ZBX_RECOVERY_MODE_NONE),	null],
	'priority' =>								[T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4,5'), 'isset({add}) || isset({update})'],
	'comments' =>								[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'url' =>									[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'correlation_mode' =>						[T_ZBX_STR, O_OPT, null,	IN(ZBX_TRIGGER_CORRELATION_NONE.','.ZBX_TRIGGER_CORRELATION_TAG),	null],
	'correlation_tag' =>						[T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})'],
	'status' =>									[T_ZBX_STR, O_OPT, null,	null,		null],
	'expression_constructor' =>					[T_ZBX_INT, O_OPT, null,	NOT_EMPTY,	'isset({toggle_expression_constructor})'],
	'recovery_expression_constructor' =>		[T_ZBX_INT, O_OPT, null,	NOT_EMPTY,		'isset({toggle_recovery_expression_constructor})'],
	'expr_temp' =>								[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({add_expression}) || isset({and_expression}) || isset({or_expression}) || isset({replace_expression}))', _('Expression')],
	'expr_target_single' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({and_expression}) || isset({or_expression}) || isset({replace_expression}))'],
	'recovery_expr_temp' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'(isset({add_recovery_expression}) || isset({and_recovery_expression}) || isset({or_recovery_expression}) || isset({replace_recovery_expression}))', _('Recovery expression')],
	'recovery_expr_target_single' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'(isset({and_recovery_expression}) || isset({or_recovery_expression}) || isset({replace_recovery_expression}))'],
	'dependencies' =>							[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'new_dependency' =>							[T_ZBX_INT, O_OPT, null,	DB_ID.NOT_ZERO, 'isset({add_dependency})'],
	'g_triggerid' =>							[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'tags' =>									[T_ZBX_STR, O_OPT, null,	null,		null],
	'manual_close' =>							[T_ZBX_INT, O_OPT, null,
													IN([ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
														ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED
													]),
													null
												],
	// actions
	'action' =>									[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
													IN('"triggerprototype.massdelete","triggerprototype.massdisable",'.
														'"triggerprototype.massenable","triggerprototype.massupdate",'.
														'"triggerprototype.massupdateform"'
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
	'massupdate' =>								[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>									[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>									[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>									[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>							[T_ZBX_INT, O_OPT, null,	null,		null],
	// sort and sortorder
	'sort' =>									[T_ZBX_STR, O_OPT, P_SYS, IN('"description","priority","status"'),		null],
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

$triggerPrototypeIds = getRequest('g_triggerid', []);
if (!is_array($triggerPrototypeIds)) {
	$triggerPrototypeIds = zbx_toArray($triggerPrototypeIds);
}

$triggerPrototypeId = getRequest('triggerid');
if ($triggerPrototypeId !== null) {
	$triggerPrototypeIds[] = $triggerPrototypeId;
}

if ($triggerPrototypeIds) {
	$triggerPrototypes = API::TriggerPrototype()->get([
		'output' => ['triggerid'],
		'triggerids' => $triggerPrototypeIds,
		'editable' => true,
		'preservekeys' => true
	]);

	foreach ($triggerPrototypeIds as $triggerPrototypeId) {
		if (!array_key_exists($triggerPrototypeId, $triggerPrototypes)) {
			access_deny();
		}
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
	$tags = getRequest('tags', []);
	$dependencies = zbx_toObject(getRequest('dependencies', []), 'triggerid');

	// Remove empty new tag lines.
	foreach ($tags as $key => $tag) {
		if ($tag['tag'] === '' && $tag['value'] === '') {
			unset($tags[$key]);
		}
	}

	$description = getRequest('description', '');
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
		$trigger_prototype = [
			'description' => $description,
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

		show_messages($result, _('Trigger prototype added'), _('Cannot add trigger prototype'));
	}
	else {
		$db_trigger_prototypes = API::TriggerPrototype()->get([
			'output' => ['expression', 'description', 'url', 'status', 'priority', 'comments', 'templateid', 'type',
				'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag', 'manual_close'
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

		if ($trigger_prototype) {
			$trigger_prototype['triggerid'] = getRequest('triggerid');

			$result = (bool) API::TriggerPrototype()->update($trigger_prototype);
		}
		else {
			$result = true;
		}

		show_messages($result, _('Trigger prototype updated'), _('Cannot update trigger prototype'));
	}

	if ($result) {
		unset($_REQUEST['form']);
		uncheckTableRows(getRequest('parent_discoveryid'));
	}
}
elseif (hasRequest('delete') && hasRequest('triggerid')) {
	$result = API::TriggerPrototype()->delete([getRequest('triggerid')]);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['triggerid']);
		uncheckTableRows(getRequest('parent_discoveryid'));
	}
	show_messages($result, _('Trigger prototype deleted'), _('Cannot delete trigger prototype'));
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
elseif (hasRequest('action') && getRequest('action') === 'triggerprototype.massupdate'
		&& hasRequest('massupdate') && hasRequest('g_triggerid')) {
	$result = true;
	$visible = getRequest('visible', []);

	if ($visible) {
		$triggersToUpdate = [];

		foreach (getRequest('g_triggerid') as $triggerId) {
			$trigger = ['triggerid' => $triggerId];

			if (isset($visible['priority'])) {
				$trigger['priority'] = getRequest('priority');
			}
			if (isset($visible['dependencies'])) {
				$trigger['dependencies'] = zbx_toObject(getRequest('dependencies', []), 'triggerid');
			}

			$triggersToUpdate[] = $trigger;
		}

		$result = (bool) API::TriggerPrototype()->update($triggersToUpdate);
	}

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['g_triggerid']);
		uncheckTableRows(getRequest('parent_discoveryid'));
	}
	show_messages($result, _('Trigger prototypes updated'), _('Cannot update trigger prototypes'));
}
elseif (getRequest('action') && str_in_array(getRequest('action'), ['triggerprototype.massenable', 'triggerprototype.massdisable']) && hasRequest('g_triggerid')) {
	$enable = (getRequest('action') == 'triggerprototype.massenable');
	$status = $enable ? TRIGGER_STATUS_ENABLED : TRIGGER_STATUS_DISABLED;
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

	$messageSuccess = $enable
		? _n('Trigger prototype enabled', 'Trigger prototypes enabled', $updated)
		: _n('Trigger prototype disabled', 'Trigger prototypes disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable trigger prototype', 'Cannot enable trigger prototypes', $updated)
		: _n('Cannot disable trigger prototype', 'Cannot disable trigger prototypes', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') == 'triggerprototype.massdelete' && hasRequest('g_triggerid')) {
	$result = API::TriggerPrototype()->delete(getRequest('g_triggerid'));

	if ($result) {
		uncheckTableRows(getRequest('parent_discoveryid'));
	}
	show_messages($result, _('Trigger prototypes deleted'), _('Cannot delete trigger prototypes'));
}

$config = select_config();

/*
 * Display
 */
if (hasRequest('action') && getRequest('action') === 'triggerprototype.massupdateform' && hasRequest('g_triggerid')) {
	$data = getTriggerMassupdateFormData();
	$data['action'] = 'triggerprototype.massupdate';
	$data['hostid'] = $discoveryRule['hostid'];

	$triggersView = new CView('configuration.trigger.prototype.massupdate', $data);
	$triggersView->render();
	$triggersView->show();
}
elseif (isset($_REQUEST['form'])) {
	$data = getTriggerFormData([
		'config' => $config,
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
		'type' => getRequest('type', 0),
		'priority' => getRequest('priority', TRIGGER_SEVERITY_NOT_CLASSIFIED),
		'status' => getRequest('status', TRIGGER_STATUS_ENABLED),
		'comments' => getRequest('comments', ''),
		'url' => getRequest('url', ''),
		'expression_constructor' => getRequest('expression_constructor', IM_ESTABLISHED),
		'recovery_expression_constructor' => getRequest('recovery_expression_constructor', IM_ESTABLISHED),
		'limited' => false,
		'templates' => [],
		'hostid' => getRequest('hostid', 0),
		'expression_action' => $expression_action,
		'recovery_expression_action' => $recovery_expression_action,
		'tags' => getRequest('tags', []),
		'correlation_mode' => getRequest('correlation_mode', ZBX_TRIGGER_CORRELATION_NONE),
		'correlation_tag' => getRequest('correlation_tag', ''),
		'manual_close' => getRequest('manual_close', ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED)
	]);

	$data['hostid'] = $discoveryRule['hostid'];

	$triggersView = new CView('configuration.trigger.prototype.edit', $data);
	$triggersView->render();
	$triggersView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'description'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$data = [
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'discovery_rule' => $discoveryRule,
		'hostid' => $discoveryRule['hostid'],
		'triggers' => [],
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'config' => $config,
		'dependencyTriggers' => []
	];

	// get triggers
	$options = [
		'editable' => true,
		'output' => ['triggerid', $sortField],
		'discoveryids' => $data['parent_discoveryid'],
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	];
	$data['triggers'] = API::TriggerPrototype()->get($options);

	order_result($data['triggers'], $sortField, $sortOrder);

	// paging
	$url = (new CUrl('trigger_prototypes.php'))
		->setArgument('parent_discoveryid', $data['parent_discoveryid']);

	$data['paging'] = getPagingLine($data['triggers'], $sortOrder, $url);

	$data['triggers'] = API::TriggerPrototype()->get([
		'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'templateid', 'recovery_mode',
			'recovery_expression'
		],
		'selectHosts' => ['hostid', 'host'],
		'selectDependencies' => ['triggerid', 'description'],
		'triggerids' => zbx_objectValues($data['triggers'], 'triggerid')
	]);
	order_result($data['triggers'], $sortField, $sortOrder);

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

	// get real hosts
	$data['realHosts'] = getParentHostsByTriggers($data['triggers']);

	// render view
	$triggersView = new CView('configuration.trigger.prototype.list', $data);
	$triggersView->render();
	$triggersView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
