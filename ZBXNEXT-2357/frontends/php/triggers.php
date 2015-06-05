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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of triggers');
$page['file'] = 'triggers.php';

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'groupid' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'hostid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'triggerid' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'(isset({form}) && ({form} == "update"))'],
	'copy_type' =>			[T_ZBX_INT, O_OPT, P_SYS,	IN([COPY_TYPE_TO_HOST, COPY_TYPE_TO_TEMPLATE, COPY_TYPE_TO_HOST_GROUP]), 'isset({copy})'],
	'copy_mode' =>			[T_ZBX_INT, O_OPT, P_SYS,	IN('0'),	null],
	'type' =>				[T_ZBX_INT, O_OPT, null,	IN('0,1'),	null],
	'description' =>		[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Name')],
	'expression' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Expression')],
	'priority' =>			[T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4,5'), 'isset({add}) || isset({update})'],
	'comments' =>			[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'url' =>				[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'status' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'input_method' =>		[T_ZBX_INT, O_OPT, null,	NOT_EMPTY,	'isset({toggle_input_method})'],
	'expr_temp' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({add_expression}) || isset({and_expression}) || isset({or_expression}) || isset({replace_expression}))', _('Expression')],
	'expr_target_single' => [T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({and_expression}) || isset({or_expression}) || isset({replace_expression}))'],
	'dependencies' =>		[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'new_dependency' =>		[T_ZBX_INT, O_OPT, null,	DB_ID.'{}>0', 'isset({add_dependency})'],
	'g_triggerid' =>		[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'copy_targetid' =>		[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'copy_groupid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({copy}) && (isset({copy_type}) && {copy_type} == 0)'],
	'showdisabled' =>		[T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null],
	'visible' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	// actions
	'action' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
								IN('"trigger.masscopyto","trigger.massdelete","trigger.massdisable",'.
									'"trigger.massenable","trigger.massupdate","trigger.massupdateform"'
								),
								null
							],
	'toggle_input_method' =>[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_expression' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'and_expression' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'or_expression' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'replace_expression' =>	[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'remove_expression' =>	[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'test_expression' =>	[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add_dependency' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'group_enable' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'group_disable' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'group_delete' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'copy' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'add' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'massupdate' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>		[T_ZBX_INT, O_OPT, null,	null,		null],
	// sort and sortorder
	'sort' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"description","priority","status"'),		null],
	'sortorder' =>			[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
$_REQUEST['showdisabled'] = getRequest('showdisabled', CProfile::get('web.triggers.showdisabled', 1));

check_fields($fields);

$_REQUEST['status'] = isset($_REQUEST['status']) ? TRIGGER_STATUS_ENABLED : TRIGGER_STATUS_DISABLED;
$_REQUEST['type'] = isset($_REQUEST['type']) ? TRIGGER_MULT_EVENT_ENABLED : TRIGGER_MULT_EVENT_DISABLED;

// Validate permissions to single trigger.
$triggerId = getRequest('triggerid');

if ($triggerId !== null) {
	$trigger = API::Trigger()->get([
		'output' => ['triggerid'],
		'triggerids' => [$triggerId],
		'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
		'editable' => true
	]);

	if (!$trigger) {
		access_deny();
	}
}

// Validate permissions to a group of triggers for mass enable/disable actions.
$triggerIds = getRequest('g_triggerid', []);
$triggerIds = zbx_toArray($triggerIds);

if ($triggerIds && !API::Trigger()->isWritable($triggerIds)) {
	access_deny();
}

// Validate permissions to group.
$groupId = getRequest('groupid');
if ($groupId && !API::HostGroup()->isWritable([$groupId])) {
	access_deny();
}

// Validate permissions to host.
$hostId = getRequest('hostid');
if ($hostId && !API::Host()->isWritable([$hostId])) {
	access_deny();
}

/*
 * Actions
 */
$exprAction = null;
if (isset($_REQUEST['add_expression'])) {
	$_REQUEST['expression'] = $_REQUEST['expr_temp'];
	$_REQUEST['expr_temp'] = '';
}
elseif (isset($_REQUEST['and_expression'])) {
	$exprAction = 'and';
}
elseif (isset($_REQUEST['or_expression'])) {
	$exprAction = 'or';
}
elseif (isset($_REQUEST['replace_expression'])) {
	$exprAction = 'r';
}
elseif (getRequest('remove_expression')) {
	$exprAction = 'R';
	$_REQUEST['expr_target_single'] = $_REQUEST['remove_expression'];
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['triggerid'])) {
	unset($_REQUEST['triggerid']);
	$_REQUEST['form'] = 'clone';
}
elseif (hasRequest('add') || hasRequest('update')) {
	$trigger = [
		'expression' => getRequest('expression'),
		'description' => getRequest('description'),
		'url' => getRequest('url'),
		'status' => getRequest('status'),
		'priority' => getRequest('priority'),
		'comments' => getRequest('comments'),
		'type' => getRequest('type'),
		'dependencies' => zbx_toObject(getRequest('dependencies', []), 'triggerid')
	];

	if (hasRequest('update')) {
		// update only changed fields

		$oldTrigger = API::Trigger()->get([
			'output' => ['expression', 'description', 'url', 'status', 'priority', 'comments', 'type'],
			'selectDependencies' => ['triggerid'],
			'triggerids' => getRequest('triggerid')
		]);
		if (!$oldTrigger) {
			access_deny();
		}

		$oldTrigger = reset($oldTrigger);
		$oldTrigger['dependencies'] = zbx_toHash(zbx_objectValues($oldTrigger['dependencies'], 'triggerid'));
		$oldTrigger['expression'] = explode_exp($oldTrigger['expression']);

		$newDependencies = $trigger['dependencies'];
		$oldDependencies = $oldTrigger['dependencies'];

		unset($trigger['dependencies'], $oldTrigger['dependencies']);

		$triggerToUpdate = array_diff_assoc($trigger, $oldTrigger);
		$triggerToUpdate['triggerid'] = getRequest('triggerid');

		// dependencies
		$updateDepencencies = false;
		if (count($newDependencies) != count($oldDependencies)) {
			$updateDepencencies = true;
		}
		else {
			foreach ($newDependencies as $dependency) {
				if (!isset($oldDependencies[$dependency['triggerid']])) {
					$updateDepencencies = true;
				}
			}
		}
		if ($updateDepencencies) {
			$triggerToUpdate['dependencies'] = $newDependencies;
		}

		$result = API::Trigger()->update($triggerToUpdate);

		show_messages($result, _('Trigger updated'), _('Cannot update trigger'));
	}
	else {
		$result = API::Trigger()->create($trigger);

		show_messages($result, _('Trigger added'), _('Cannot add trigger'));
	}

	if ($result) {
		unset($_REQUEST['form']);
		uncheckTableRows(getRequest('hostid'));
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['triggerid'])) {
	DBstart();

	$result = API::Trigger()->delete([getRequest('triggerid')]);
	$result = DBend($result);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['triggerid']);
		uncheckTableRows(getRequest('hostid'));
	}
	show_messages($result, _('Trigger deleted'), _('Cannot delete trigger'));
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
elseif (hasRequest('action') && getRequest('action') === 'trigger.massupdate'
		&& hasRequest('massupdate') && hasRequest('g_triggerid')) {
	$result = true;
	$visible = getRequest('visible', []);

	if ($visible) {
		$triggersToUpdate = [];

		foreach (getRequest('g_triggerid') as $triggerid) {
			$trigger = ['triggerid' => $triggerid];

			if (isset($visible['priority'])) {
				$trigger['priority'] = getRequest('priority');
			}
			if (isset($visible['dependencies'])) {
				$trigger['dependencies'] = zbx_toObject(getRequest('dependencies', []), 'triggerid');
			}

			$triggersToUpdate[] = $trigger;
		}

		$result = (bool) API::Trigger()->update($triggersToUpdate);
	}

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['g_triggerid']);
		uncheckTableRows(getRequest('hostid'));
	}
	show_messages($result, _('Trigger updated'), _('Cannot update trigger'));
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['trigger.massenable', 'trigger.massdisable']) && hasRequest('g_triggerid')) {
	$enable = (getRequest('action') == 'trigger.massenable');
	$status = $enable ? TRIGGER_STATUS_ENABLED : TRIGGER_STATUS_DISABLED;
	$update = [];

	// get requested triggers with permission check
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
		uncheckTableRows(getRequest('hostid'));
		unset($_REQUEST['g_triggerid']);
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') == 'trigger.masscopyto' && hasRequest('copy') && hasRequest('g_triggerid')) {
	if (hasRequest('copy_targetid') && getRequest('copy_targetid') > 0 && hasRequest('copy_type')) {
		// hosts or templates
		if (getRequest('copy_type') == COPY_TYPE_TO_HOST || getRequest('copy_type') == COPY_TYPE_TO_TEMPLATE) {
			$hosts_ids = getRequest('copy_targetid');
		}
		// host groups
		else {
			$hosts_ids = [];
			$group_ids = getRequest('copy_targetid');

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

		if ($result) {
			uncheckTableRows(getRequest('hostid'));
			unset($_REQUEST['g_triggerid']);
		}
		show_messages($result, _('Trigger added'), _('Cannot add trigger'));
	}
	else {
		show_error_message(_('No target selected'));
	}
}
elseif (hasRequest('action') && getRequest('action') == 'trigger.massdelete' && hasRequest('g_triggerid')) {
	$result = API::Trigger()->delete(getRequest('g_triggerid'));

	if ($result) {
		uncheckTableRows(getRequest('hostid'));
	}
	show_messages($result, _('Triggers deleted'), _('Cannot delete triggers'));
}

/*
 * Display
 */
if (hasRequest('action') && getRequest('action') == 'trigger.massupdateform' && hasRequest('g_triggerid')) {
	$data = getTriggerMassupdateFormData();
	$data['action'] = 'trigger.massupdate';
	$triggersView = new CView('configuration.triggers.massupdate', $data);
	$triggersView->render();
	$triggersView->show();
}
elseif (isset($_REQUEST['form'])) {
	$triggersView = new CView('configuration.triggers.edit', getTriggerFormData($exprAction));
	$triggersView->render();
	$triggersView->show();
}
elseif (hasRequest('action') && getRequest('action') == 'trigger.masscopyto' && hasRequest('g_triggerid')) {
	$data = getCopyElementsFormData('g_triggerid', _('CONFIGURATION OF TRIGGERS'));
	$data['action'] = 'trigger.masscopyto';
	$triggersView = new CView('configuration.copy.elements', $data);
	$triggersView->render();
	$triggersView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'description'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$config = select_config();

	$data = [
		'showdisabled' => getRequest('showdisabled', 1),
		'triggers' => [],
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'config' => $config
	];

	CProfile::update('web.triggers.showdisabled', $data['showdisabled'], PROFILE_TYPE_INT);

	$data['pageFilter'] = new CPageFilter([
		'groups' => ['with_hosts_and_templates' => true, 'editable' => true],
		'hosts' => ['templated_hosts' => true, 'editable' => true],
		'triggers' => ['editable' => true],
		'groupid' => getRequest('groupid'),
		'hostid' => getRequest('hostid')
	]);
	$data['groupid'] = $data['pageFilter']->groupid;
	$data['hostid'] = $data['pageFilter']->hostid;

	// get triggers
	if ($data['pageFilter']->hostsSelected) {
		$options = [
			'editable' => true,
			'sortfield' => $sortField,
			'limit' => $config['search_limit'] + 1
		];

		if ($sortField === 'status') {
			$options['output'] = ['triggerid', 'status', 'state'];
		}
		else {
			$options['output'] = ['triggerid', $sortField];
		}

		if (empty($data['showdisabled'])) {
			$options['filter']['status'] = TRIGGER_STATUS_ENABLED;
		}
		if ($data['pageFilter']->hostid > 0) {
			$options['hostids'] = $data['pageFilter']->hostid;
		}
		elseif ($data['pageFilter']->groupid > 0) {
			$options['groupids'] = $data['pageFilter']->groupid;
		}
		$data['triggers'] = API::Trigger()->get($options);
	}

	$_REQUEST['hostid'] = getRequest('hostid', $data['pageFilter']->hostid);

	// sort for paging
	if ($sortField === 'status') {
		orderTriggersByStatus($data['triggers'], $sortOrder);
	}
	else {
		order_result($data['triggers'], $sortField, $sortOrder);
	}

	// paging
	$data['paging'] = getPagingLine($data['triggers'], $sortOrder);

	$data['triggers'] = API::Trigger()->get([
		'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'error', 'templateid', 'state'],
		'selectHosts' => ['hostid', 'host'],
		'selectItems' => ['itemid', 'hostid', 'key_', 'type', 'flags', 'status'],
		'selectFunctions' => ['functionid', 'itemid', 'function', 'parameter'],
		'selectDependencies' => ['triggerid', 'description'],
		'selectDiscoveryRule' => ['itemid', 'name'],
		'triggerids' => zbx_objectValues($data['triggers'], 'triggerid')
	]);

	// sort for displaying full results
	if ($sortField === 'status') {
		orderTriggersByStatus($data['triggers'], $sortOrder);
	}
	else {
		order_result($data['triggers'], $sortField, $sortOrder);
	}

	$depTriggerIds = [];
	foreach ($data['triggers'] as $trigger) {
		foreach ($trigger['dependencies'] as $depTrigger) {
			$depTriggerIds[$depTrigger['triggerid']] = true;
		}
	}

	$dependencyTriggers = [];
	if ($depTriggerIds) {
		$dependencyTriggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'status', 'flags'],
			'selectHosts' => ['hostid', 'name'],
			'triggerids' => array_keys($depTriggerIds),
			'preservekeys' => true
		]);

		foreach ($data['triggers'] as &$trigger) {
			order_result($trigger['dependencies'], 'description', ZBX_SORT_UP);
		}
		unset($trigger);

		foreach ($dependencyTriggers as &$dependencyTrigger) {
			order_result($dependencyTrigger['hosts'], 'name', ZBX_SORT_UP);
		}
		unset($dependencyTrigger);
	}

	$data['dependencyTriggers'] = $dependencyTriggers;

	// get real hosts
	$data['realHosts'] = getParentHostsByTriggers($data['triggers']);

	// do not show 'Info' column, if it is a template
	if ($data['hostid']) {
		$data['showInfoColumn'] = (bool) API::Host()->get([
			'output' => [],
			'hostids' => $data['hostid']
		]);
	}
	else {
		$data['showInfoColumn'] = true;
	}

	// render view
	$triggersView = new CView('configuration.triggers.list', $data);
	$triggersView->render();
	$triggersView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
