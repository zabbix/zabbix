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
require_once dirname(__FILE__).'/include/hostgroups.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/screens.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/ident.inc.php';

if (hasRequest('action') && getRequest('action') == 'template.export' && hasRequest('templates')) {
	$exportData = true;

	$page['type'] = detect_page_type(PAGE_TYPE_XML);
	$page['file'] = 'zbx_export_templates.xml';
}
else {
	$exportData = false;

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	$page['title'] = _('Configuration of templates');
	$page['file'] = 'templates.php';
	$page['scripts'] = ['multiselect.js'];
}

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR						TYPE		OPTIONAL FLAGS			VALIDATION	EXCEPTION
$fields = [
	'groups'			=> [T_ZBX_STR, O_OPT, null,			NOT_EMPTY,	'isset({add}) || isset({update})'],
	'new_groups'		=> [T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'remove_groups'		=> [T_ZBX_STR, O_OPT, P_SYS,		DB_ID,	null],
	'clear_templates'	=> [T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null],
	'templates'			=> [T_ZBX_INT, O_OPT, null,		DB_ID,	null],
	'linked_templates'	=> [T_ZBX_INT, O_OPT, null,		DB_ID,	null],
	'add_templates'		=> [T_ZBX_INT, O_OPT, null,		DB_ID,	null],
	'add_template' 		=> [T_ZBX_STR, O_OPT, null,		null,	null],
	'templateid'		=> [T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	'isset({form}) && {form} == "update"'],
	'template_name'		=> [T_ZBX_STR, O_OPT, null,		NOT_EMPTY, 'isset({add}) || isset({update})', _('Template name')],
	'visiblename'		=> [T_ZBX_STR, O_OPT, null,		null,	'isset({add}) || isset({update})'],
	'groupid'			=> [T_ZBX_INT, O_OPT, P_SYS,		DB_ID,	null],
	'tags'				=> [T_ZBX_STR, O_OPT, null,		null,	null],
	'new_tags'			=> [T_ZBX_STR, O_OPT, null,		null,	null],
	'remove_tags'		=> [T_ZBX_STR, O_OPT, null,		null,	null],
	'description'		=> [T_ZBX_STR, O_OPT, null,		null,	null],
	'macros'			=> [T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'visible'			=> [T_ZBX_STR, O_OPT, null,			null,	null],
	'mass_replace_tpls'	=> [T_ZBX_STR, O_OPT, null,			null,	null],
	'mass_clear_tpls'	=> [T_ZBX_STR, O_OPT, null,			null,	null],
	'show_inherited_macros' => [T_ZBX_INT, O_OPT, null,	IN([0,1]), null],
	// actions
	'action'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,
								IN('"template.export","template.massupdate","template.massupdateform",'.
									'"template.massdelete","template.massdeleteclear"'
								),
								null
							],
	'unlink'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'unlink_and_clear'	=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'add'				=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'update'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'masssave'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'clone'				=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'full_clone'		=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'delete'			=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'delete_and_clear'	=> [T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'cancel'			=> [T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'form'				=> [T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'form_refresh'		=> [T_ZBX_INT, O_OPT, null,		null,	null],
	// filter
	'filter_set'		=> [T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst'		=> [T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_name'		=> [T_ZBX_STR, O_OPT, null,		null,		null],
	'filter_templates' =>  [T_ZBX_INT, O_OPT, null,		DB_ID,		null],
	'filter_evaltype'	=> [T_ZBX_INT, O_OPT, null,
								IN([TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]),
								null
							],
	'filter_tags'		=> [T_ZBX_STR, O_OPT, null,		null,		null],
	// sort and sortorder
	'sort'				=> [T_ZBX_STR, O_OPT, P_SYS, IN('"name"'),									null],
	'sortorder'			=> [T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('groupid') && !isWritableHostGroups([getRequest('groupid')])) {
	access_deny();
}
if (getRequest('templateid')) {
	$templates = API::Template()->get([
		'output' => [],
		'templateids' => getRequest('templateid'),
		'editable' => true
	]);

	if (!$templates) {
		access_deny();
	}
}

$templateIds = getRequest('templates', []);

if ($exportData) {
	$export = new CConfigurationExport(['templates' => $templateIds]);
	$export->setBuilder(new CConfigurationExportBuilder());
	$export->setWriter(CExportWriterFactory::getWriter(CExportWriterFactory::XML));
	$exportData = $export->export();

	if (hasErrorMesssages()) {
		show_messages();
	}
	else {
		print($exportData);
	}

	exit;
}

// remove inherited macros data (actions: 'add', 'update' and 'form')
if (hasRequest('macros')) {
	$_REQUEST['macros'] = cleanInheritedMacros($_REQUEST['macros']);

	// remove empty new macro lines
	foreach ($_REQUEST['macros'] as $idx => $macro) {
		if (!array_key_exists('hostmacroid', $macro) && $macro['macro'] === '' && $macro['value'] === '') {
			unset($_REQUEST['macros'][$idx]);
		}
	}
}

/*
 * Actions
 */
if (isset($_REQUEST['add_template']) && isset($_REQUEST['add_templates'])) {
	$_REQUEST['templates'] = array_merge($templateIds, $_REQUEST['add_templates']);
}
if (hasRequest('unlink') || hasRequest('unlink_and_clear')) {
	$unlinkTemplates = [];

	if (hasRequest('unlink') && is_array(getRequest('unlink'))) {
		$unlinkTemplates = array_keys(getRequest('unlink'));
	}
	elseif (hasRequest('unlink_and_clear') && is_array(getRequest('unlink_and_clear'))) {
		$unlinkTemplates = array_keys(getRequest('unlink_and_clear'));
		$_REQUEST['clear_templates'] = array_merge($unlinkTemplates, getRequest('clear_templates', []));
	}

	foreach ($unlinkTemplates as $id) {
		unset($_REQUEST['templates'][array_search($id, $_REQUEST['templates'])]);
	}
}
elseif ((hasRequest('clone') || hasRequest('full_clone')) && hasRequest('templateid')) {
	$_REQUEST['form'] = hasRequest('clone') ? 'clone' : 'full_clone';

	$groups = getRequest('groups', []);
	$groupids = [];

	// Remove inaccessible groups from request, but leave "new".
	foreach ($groups as $group) {
		if (!is_array($group)) {
			$groupids[] = $group;
		}
	}

	if ($groupids) {
		$groups_allowed = API::HostGroup()->get([
			'output' => [],
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($groups as $idx => $group) {
			if (!is_array($group) && !array_key_exists($group, $groups_allowed)) {
				unset($groups[$idx]);
			}
		}

		$_REQUEST['groups'] = $groups;
	}

	if (hasRequest('clone')) {
		unset($_REQUEST['templateid']);
	}
}
elseif (hasRequest('action') && getRequest('action') == 'template.massupdate' && hasRequest('masssave')) {
	$templateids = getRequest('templates', []);
	$visible = getRequest('visible', []);

	try {
		DBstart();

		$options = [
			'output' => ['templateid'],
			'selectGroups' => ['groupid'],
			'templateids' => $templateids
		];

		if (array_key_exists('new_tags', $visible) || array_key_exists('remove_tags', $visible)) {
			$options['selectTags'] = ['tag', 'value'];
		}

		if (array_key_exists('linked_templates', $visible) && !hasRequest('mass_replace_tpls')) {
			$options['selectParentTemplates'] = ['templateid'];
		}

		$templates = API::Template()->get($options);

		$new_values = [];

		/*
		 * Step 2. Add new host groups. This is actually done later, but before we can do that we need to check what
		 * groups will be added and first of all actually create them and get the new IDs.
		 */
		$new_groupids = [];

		if (array_key_exists('new_groups', $visible)) {
			if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
				$ins_groups = [];

				foreach (getRequest('new_groups', []) as $new_group) {
					if (is_array($new_group) && array_key_exists('new', $new_group)) {
						$ins_groups[] = ['name' => $new_group['new']];
					}
					else {
						$new_groupids[] = $new_group;
					}
				}

				if ($ins_groups) {
					if (!$result = API::HostGroup()->create($ins_groups)) {
						throw new Exception();
					}

					$new_groupids = array_merge($new_groupids, $result['groupids']);
				}
			}
			else {
				$new_groupids = getRequest('new_groups', []);
			}
		}

		// Step 1. Replace existing groups.
		if (array_key_exists('groups', $visible)) {
			$replace_groupids = [];

			if (hasRequest('groups')) {
				// First (step 1.) we try to replace existing groups and add new groups in the process (step 2.).
				$replace_groupids = array_unique(array_merge(getRequest('groups'), $new_groupids));
			}
			elseif ($new_groupids) {
				/*
				 * If no groups need to be replaced, use same variable as if new groups are added. This is used in
				 * step 3. The only difference is that we try to remove all existing groups by replacing with nothing
				 * since we left it empty.
				 */
				$replace_groupids = $new_groupids;
			}

			$new_values['groups'] = zbx_toObject($replace_groupids, 'groupid');
		}

		$new_tags = [];
		if (array_key_exists('new_tags', $visible)) {
			foreach (getRequest('new_tags', []) as $tag) {
				if ($tag['tag'] === '' && $tag['value'] === '') {
					continue;
				}

				$new_tags[] = $tag;
			}
		}

		$replace_tags = [];
		if (array_key_exists('tags', $visible)) {
			foreach (getRequest('tags', []) as $tag) {
				if ($tag['tag'] === '' && $tag['value'] === '') {
					continue;
				}

				$replace_tags[] = $tag;
			}

			$unique_tags = [];
			foreach (array_merge($replace_tags, $new_tags) as $tag) {
				$unique_tags[$tag['tag'].':'.$tag['value']] = $tag;
			}

			$new_values['tags'] = array_values($unique_tags);
		}

		$remove_tags = [];
		if (array_key_exists('remove_tags', $visible)) {
			foreach (getRequest('remove_tags', []) as $tag) {
				if ($tag['tag'] === '' && $tag['value'] === '') {
					continue;
				}

				$remove_tags[] = $tag;
			}
		}

		if (array_key_exists('description', $visible)) {
			$new_values['description'] = getRequest('description');
		}

		$linked_templateids = [];
		if (array_key_exists('linked_templates', $visible)) {
			$linked_templateids = getRequest('linked_templates', []);
		}

		if (hasRequest('mass_replace_tpls')) {
			if (hasRequest('mass_clear_tpls')) {
				$template_templates = API::Template()->get([
					'output' => ['templateid'],
					'hostids' => $templateids
				]);

				$template_templateids = zbx_objectValues($template_templates, 'templateid');
				$templates_to_delete = array_diff($template_templateids, $linked_templateids);

				$new_values['templates_clear'] = zbx_toObject($templates_to_delete, 'templateid');
			}

			$new_values['templates'] = $linked_templateids;
		}

		foreach ($templates as &$template) {
			/*
			 * Step 3. Case when groups need to be removed. This is done inside the loop, since each host may have
			 * different existing groups. So we need to know what can we remove.
			 */
			if (array_key_exists('remove_groups', $visible)) {
				$remove_groups = getRequest('remove_groups', []);

				if (array_key_exists('groups', $visible)) {
					/*
					 * Previously we determined what groups for ALL hosts will be replaced.
					 * The $replace_groupids holds both - groups to replace and new groups to add.
					 * New $replace_groupids is the difference between the replaceable groups and removable groups.
					 */
					$replace_groupids = array_diff($replace_groupids, $remove_groups);
				}
				else {
					/*
					 * The $new_groupids holds only groups that need to be added. So $replace_groupids is
					 * the difference between the groups that already exist + groups that need to be added and
					 * removable groups.
					 */
					$current_groupids = zbx_objectValues($template['groups'], 'groupid');

					$replace_groupids = array_diff(array_unique(array_merge($current_groupids, $new_groupids)),
						$remove_groups
					);
				}

				$new_values['groups'] = zbx_toObject($replace_groupids, 'groupid');
			}

			// Case when we only need to add new groups to host.
			if ($new_groupids && !array_key_exists('groups', $visible)
					&& !array_key_exists('remove_groups', $visible)) {
				$current_groupids = zbx_objectValues($template['groups'], 'groupid');

				$template['groups'] = zbx_toObject(array_unique(array_merge($current_groupids, $new_groupids)),
					'groupid'
				);
			}
			else {
				// In all other cases we first clear out the old values. And simply replace with $new_values later.
				unset($template['groups']);
			}

			if (array_key_exists('remove_tags', $visible)) {
				if (!array_key_exists('tags', $visible)) {
					$unique_tags = [];
					foreach (array_merge($template['tags'], $new_tags) as $tag) {
						$unique_tags[$tag['tag'].':'.$tag['value']] = $tag;
					}
					$replace_tags = array_values($unique_tags);
				}

				$diff_tags = [];
				foreach ($replace_tags as $a) {
					foreach ($remove_tags as $b) {
						if ($a['tag'] === $b['tag'] && $a['value'] === $b['value']) {
							continue 2;
						}
					}
					$diff_tags[] = $a;
				}

				$new_values['tags'] = $diff_tags;
			}

			if ($new_tags && !array_key_exists('tags', $visible) && !array_key_exists('remove_tags', $visible)) {
				$unique_tags = [];
				foreach (array_merge($template['tags'], $new_tags) as $tag) {
					$unique_tags[$tag['tag'].':'.$tag['value']] = $tag;
				}
				$template['tags'] = array_values($unique_tags);
			}
			else {
				unset($template['tags']);
			}

			if ($linked_templateids && array_key_exists('parentTemplates', $template)) {
				$template['templates'] = array_unique(
					array_merge($linked_templateids, zbx_objectValues($template['parentTemplates'], 'templateid'))
				);
			}

			unset($template['parentTemplates']);

			$template = array_merge($template, $new_values);
		}
		unset($template);

		$result = (bool) API::Template()->update($templates);

		if ($result === false) {
			throw new Exception();
		}

		DBend(true);

		uncheckTableRows();
		show_message(_('Templates updated'));

		unset($_REQUEST['masssave'], $_REQUEST['form'], $_REQUEST['templates']);
	}
	catch (Exception $e) {
		DBend(false);
		show_error_message(_('Cannot update templates'));
	}
}
elseif (hasRequest('add') || hasRequest('update')) {
	try {
		DBstart();

		$templateId = getRequest('templateid', 0);
		$cloneTemplateId = 0;

		if (getRequest('form') === 'full_clone') {
			$cloneTemplateId = $templateId;
			$templateId = 0;
		}

		if ($templateId == 0) {
			$messageSuccess = _('Template added');
			$messageFailed = _('Cannot add template');
			$auditAction = AUDIT_ACTION_ADD;
		}
		else {
			$messageSuccess = _('Template updated');
			$messageFailed = _('Cannot update template');
			$auditAction = AUDIT_ACTION_UPDATE;
		}

		// Add new group.
		$groups = getRequest('groups', []);
		$new_groups = [];

		foreach ($groups as $idx => $group) {
			if (is_array($group) && array_key_exists('new', $group)) {
				$new_groups[] = ['name' => $group['new']];
				unset($groups[$idx]);
			}
		}

		if ($new_groups) {
			$new_groupid = API::HostGroup()->create($new_groups);

			if (!$new_groupid) {
				throw new Exception();
			}

			$groups = array_merge($groups, $new_groupid['groupids']);
		}

		// linked templates
		$linkedTemplates = getRequest('templates', []);
		$templates = [];
		foreach ($linkedTemplates as $linkedTemplateId) {
			$templates[] = ['templateid' => $linkedTemplateId];
		}

		$templatesClear = getRequest('clear_templates', []);
		$templatesClear = zbx_toObject($templatesClear, 'templateid');
		$templateName = getRequest('template_name', '');
		$tags = getRequest('tags', []);

		// Remove empty new tag lines.
		foreach ($tags as $key => $tag) {
			if ($tag['tag'] === '' && $tag['value'] === '') {
				unset($tags[$key]);
			}
		}

		// create / update template
		$template = [
			'host' => $templateName,
			'name' => getRequest('visiblename', ''),
			'groups' => zbx_toObject($groups, 'groupid'),
			'templates' => $templates,
			'macros' => getRequest('macros', []),
			'tags' => $tags,
			'description' => getRequest('description', '')
		];

		if ($templateId == 0) {
			$result = API::Template()->create($template);

			if ($result) {
				$templateId = reset($result['templateids']);
			}
			else {
				throw new Exception();
			}
		}
		else {
			$template['templateid'] = $templateId;
			$template['templates_clear'] = $templatesClear;

			$result = API::Template()->update($template);

			if (!$result) {
				throw new Exception();
			}
		}

		// full clone
		if ($cloneTemplateId != 0 && getRequest('form') === 'full_clone') {
			if (!copyApplications($cloneTemplateId, $templateId)) {
				throw new Exception();
			}

			/*
			 * First copy web scenarios with web items, so that later regular items can use web item as their master
			 * item.
			 */
			if (!copyHttpTests($cloneTemplateId, $templateId)) {
				throw new Exception();
			}

			if (!copyItems($cloneTemplateId, $templateId)) {
				throw new Exception();
			}

			// copy triggers
			$dbTriggers = API::Trigger()->get([
				'output' => ['triggerid'],
				'hostids' => $cloneTemplateId,
				'inherited' => false
			]);

			if ($dbTriggers) {
				$result &= copyTriggersToHosts(zbx_objectValues($dbTriggers, 'triggerid'),
						$templateId, $cloneTemplateId);

				if (!$result) {
					throw new Exception();
				}
			}

			// copy graphs
			$dbGraphs = API::Graph()->get([
				'output' => ['graphid'],
				'hostids' => $cloneTemplateId,
				'inherited' => false
			]);

			foreach ($dbGraphs as $dbGraph) {
				copyGraphToHost($dbGraph['graphid'], $templateId);
			}

			// copy discovery rules
			$dbDiscoveryRules = API::DiscoveryRule()->get([
				'output' => ['itemid'],
				'hostids' => $cloneTemplateId,
				'inherited' => false
			]);

			if ($dbDiscoveryRules) {
				$result &= API::DiscoveryRule()->copy([
					'discoveryids' => zbx_objectValues($dbDiscoveryRules, 'itemid'),
					'hostids' => [$templateId]
				]);

				if (!$result) {
					throw new Exception();
				}
			}

			// copy template screens
			$dbTemplateScreens = API::TemplateScreen()->get([
				'output' => ['screenid'],
				'templateids' => $cloneTemplateId,
				'preservekeys' => true,
				'inherited' => false
			]);

			if ($dbTemplateScreens) {
				$result &= API::TemplateScreen()->copy([
					'screenIds' => zbx_objectValues($dbTemplateScreens, 'screenid'),
					'templateIds' => $templateId
				]);

				if (!$result) {
					throw new Exception();
				}
			}
		}

		if ($result) {
			add_audit_ext($auditAction, AUDIT_RESOURCE_TEMPLATE, $templateId, $templateName, 'hosts', null, null);
		}

		unset($_REQUEST['form'], $_REQUEST['templateid']);
		$result = DBend($result);

		if ($result) {
			uncheckTableRows();
		}
		show_messages($result, $messageSuccess, $messageFailed);
	}
	catch (Exception $e) {
		DBend(false);
		show_error_message($messageFailed);
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['templateid'])) {
	DBstart();

	$result = API::Template()->massUpdate([
		'templates' => zbx_toObject($_REQUEST['templateid'], 'templateid'),
		'hosts' => []
	]);
	if ($result) {
		$result = API::Template()->delete([getRequest('templateid')]);
	}

	$result = DBend($result);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['templateid']);
		uncheckTableRows();
	}
	unset($_REQUEST['delete']);
	show_messages($result, _('Template deleted'), _('Cannot delete template'));
}
elseif (isset($_REQUEST['delete_and_clear']) && isset($_REQUEST['templateid'])) {
	DBstart();

	$result = API::Template()->delete([getRequest('templateid')]);

	$result = DBend($result);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['templateid']);
		uncheckTableRows();
	}
	unset($_REQUEST['delete']);
	show_messages($result, _('Template deleted'), _('Cannot delete template'));
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['template.massdelete', 'template.massdeleteclear']) && hasRequest('templates')) {
	$templates = getRequest('templates');

	DBstart();

	$result = true;

	if (getRequest('action') === 'template.massdelete') {
		$result = API::Template()->massUpdate([
			'templates' => zbx_toObject($templates, 'templateid'),
			'hosts' => []
		]);
	}

	if ($result) {
		$result = API::Template()->delete($templates);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Template deleted'), _('Cannot delete template'));
}

/*
 * Display
 */
$pageFilter = new CPageFilter([
	'config' => [
		'individual' => 1
	],
	'groups' => [
		'templated_hosts' => true,
		'editable' => true
	],
	'groupid' => getRequest('groupid')
]);
$_REQUEST['groupid'] = $pageFilter->groupid;

if ((getRequest('action') === 'template.massupdateform' || hasRequest('masssave')) && hasRequest('templates')) {
	$data = [
		'templates' => getRequest('templates', []),
		'visible' => getRequest('visible', []),
		'mass_replace_tpls' => getRequest('mass_replace_tpls'),
		'mass_clear_tpls' => getRequest('mass_clear_tpls'),
		'groups' => getRequest('groups', []),
		'tags' => getRequest('tags', []),
		'new_tags' => getRequest('tags', []),
		'remove_tags' => getRequest('tags', []),
		'description' => getRequest('description'),
		'linked_templates' => getRequest('linked_templates', [])
	];

	// sort templates
	natsort($data['linked_templates']);

	// get tags
	if (!$data['tags']) {
		$data['tags'][] = ['tag' => '', 'value' => ''];
	}
	if (!$data['new_tags']) {
		$data['new_tags'][] = ['tag' => '', 'value' => ''];
	}
	if (!$data['remove_tags']) {
		$data['remove_tags'][] = ['tag' => '', 'value' => ''];
	}

	// get templates data
	$data['linked_templates'] = $data['linked_templates']
		? CArrayHelper::renameObjectsKeys(API::Template()->get([
			'output' => ['templateid', 'name'],
			'templateids' => $data['linked_templates']
		]), ['templateid' => 'id'])
		: [];

	$view = new CView('configuration.template.massupdate', $data);
}
elseif (hasRequest('form')) {
	$data = [
		'form' => getRequest('form'),
		'templateid' => getRequest('templateid', 0),
		'show_inherited_macros' => getRequest('show_inherited_macros', 0)
	];

	if ($data['templateid'] != 0) {
		$dbTemplates = API::Template()->get([
			'output' => API_OUTPUT_EXTEND,
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => ['templateid', 'name'],
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectTags' => ['tag', 'value'],
			'templateids' => $data['templateid']
		]);
		$data['dbTemplate'] = reset($dbTemplates);

		$data['original_templates'] = [];
		foreach ($data['dbTemplate']['parentTemplates'] as $parentTemplate) {
			$data['original_templates'][$parentTemplate['templateid']] = $parentTemplate['templateid'];
		}
	}
	else {
		$data['original_templates'] = [];
	}

	// description
	$data['description'] = ($data['templateid'] != 0 && !hasRequest('form_refresh'))
		? $data['dbTemplate']['description']
		: getRequest('description', '');

	// Tags
	if ($data['templateid'] != 0 && !hasRequest('form_refresh')) {
		$data['tags'] = $data['dbTemplate']['tags'];
		CArrayHelper::sort($data['tags'], ['tag', 'value']);
	}
	else {
		$data['tags'] = getRequest('tags', []);
	}

	if (!$data['tags']) {
		$data['tags'][] = ['tag' => '', 'value' => ''];
	}

	$templateIds = getRequest('templates', hasRequest('form_refresh') ? [] : $data['original_templates']);

	// Get linked templates.
	$data['linkedTemplates'] = API::Template()->get([
		'output' => ['templateid', 'name'],
		'templateids' => $templateIds,
		'preservekeys' => true
	]);

	$data['writable_templates'] = API::Template()->get([
		'output' => ['templateid'],
		'templateids' => $templateIds,
		'editable' => true,
		'preservekeys' => true
	]);

	CArrayHelper::sort($data['linkedTemplates'], ['name']);

	$groups = [];

	if (!hasRequest('form_refresh')) {
		if ($data['templateid'] != 0) {
			$groups = zbx_objectValues($data['dbTemplate']['groups'], 'groupid');
		}
		elseif (getRequest('groupid', 0) != 0) {
			$groups[] = getRequest('groupid');
		}
	}
	else {
		$groups = getRequest('groups', []);
	}

	$groupids = [];

	foreach ($groups as $group) {
		if (is_array($group) && array_key_exists('new', $group)) {
			continue;
		}

		$groupids[] = $group;
	}

	// Groups with R and RW permissions.
	$groups_all = $groupids
		? API::HostGroup()->get([
			'output' => ['name'],
			'groupids' => $groupids,
			'preservekeys' => true
		])
		: [];

	// Groups with RW permissions.
	$groups_rw = $groupids && (CWebUser::getType() != USER_TYPE_SUPER_ADMIN)
		? API::HostGroup()->get([
			'output' => [],
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		])
		: [];

	$data['groups_ms'] = [];

	// Prepare data for multiselect.
	foreach ($groups as $group) {
		if (is_array($group) && array_key_exists('new', $group)) {
			$data['groups_ms'][] = [
				'id' => $group['new'],
				'name' => $group['new'].' ('._x('new', 'new element in multiselect').')',
				'isNew' => true
			];
		}
		elseif (array_key_exists($group, $groups_all)) {
			$data['groups_ms'][] = [
				'id' => $group,
				'name' => $groups_all[$group]['name'],
				'disabled' => (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) && !array_key_exists($group, $groups_rw)
			];
		}
	}
	CArrayHelper::sort($data['groups_ms'], ['name']);

	$view = new CView('configuration.template.edit', $data);
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	// filter
	if (hasRequest('filter_set')) {
		CProfile::update('web.templates.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
		CProfile::updateArray('web.templates.filter_templates', getRequest('filter_templates', []), PROFILE_TYPE_ID);
		CProfile::update('web.templates.filter.evaltype', getRequest('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
			PROFILE_TYPE_INT
		);

		$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];
		foreach (getRequest('filter_tags', []) as $filter_tag) {
			if ($filter_tag['tag'] === '' && $filter_tag['value'] === '') {
				continue;
			}

			$filter_tags['tags'][] = $filter_tag['tag'];
			$filter_tags['values'][] = $filter_tag['value'];
			$filter_tags['operators'][] = $filter_tag['operator'];
		}
		CProfile::updateArray('web.templates.filter.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
		CProfile::updateArray('web.templates.filter.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
		CProfile::updateArray('web.templates.filter.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);
	}
	elseif (hasRequest('filter_rst')) {
		CProfile::delete('web.templates.filter_name');
		CProfile::deleteIdx('web.templates.filter_templates');
		CProfile::delete('web.templates.filter.evaltype');
		CProfile::deleteIdx('web.templates.filter.tags.tag');
		CProfile::deleteIdx('web.templates.filter.tags.value');
		CProfile::deleteIdx('web.templates.filter.tags.operator');
	}

	$filter = [
		'name' => CProfile::get('web.templates.filter_name', ''),
		'templates' => CProfile::getArray('web.templates.filter_templates', null),
		'evaltype' => CProfile::get('web.templates.filter.evaltype', TAG_EVAL_TYPE_AND_OR)
	];
	$filter['tags'] = [];
	foreach (CProfile::getArray('web.templates.filter.tags.tag', []) as $i => $tag) {
		$filter['tags'][] = [
			'tag' => $tag,
			'value' => CProfile::get('web.templates.filter.tags.value', null, $i),
			'operator' => CProfile::get('web.templates.filter.tags.operator', null, $i)
		];
	}

	$config = select_config();

	// get templates
	$templates = [];

	$filter['templates'] = $filter['templates']
		? CArrayHelper::renameObjectsKeys(API::Template()->get([
			'output' => ['templateid', 'name'],
			'templateids' => $filter['templates'],
			'preservekeys' => true
		]), ['templateid' => 'id'])
		: [];

	if ($pageFilter->groupsSelected) {
		$templates = API::Template()->get([
			'output' => ['templateid', $sortField],
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'],
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name']
			],
			'parentTemplateids' => $filter['templates'] ? array_keys($filter['templates']) : null,
			'groupids' => $pageFilter->groupids,
			'editable' => true,
			'sortfield' => $sortField,
			'limit' => $config['search_limit'] + 1
		]);
	}
	order_result($templates, $sortField, $sortOrder);

	$url = (new CUrl('templates.php'))
		->setArgument('groupid', getRequest('groupid', 0));

	$paging = getPagingLine($templates, $sortOrder, $url);

	$templates = API::Template()->get([
		'output' => ['templateid', 'name'],
		'selectHosts' => ['hostid', 'name', 'status'],
		'selectTemplates' => ['templateid', 'name', 'status'],
		'selectParentTemplates' => ['templateid', 'name', 'status'],
		'selectItems' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectApplications' => API_OUTPUT_COUNT,
		'selectDiscoveries' => API_OUTPUT_COUNT,
		'selectScreens' => API_OUTPUT_COUNT,
		'selectHttpTests' => API_OUTPUT_COUNT,
		'selectTags' => ['tag', 'value'],
		'templateids' => zbx_objectValues($templates, 'templateid'),
		'editable' => true,
		'preservekeys' => true
	]);

	order_result($templates, $sortField, $sortOrder);

	// Select writable templates:
	$linked_template_ids = [];
	$writable_templates = [];
	$linked_hosts_ids = [];
	$writable_hosts = [];
	foreach ($templates as $template) {
		$linked_template_ids = array_merge(
			$linked_template_ids,
			zbx_objectValues($template['parentTemplates'], 'templateid'),
			zbx_objectValues($template['templates'], 'templateid'),
			zbx_objectValues($template['hosts'], 'hostid')
		);

		$linked_hosts_ids = array_merge(
			$linked_hosts_ids,
			zbx_objectValues($template['hosts'], 'hostid')
		);
	}
	if ($linked_template_ids) {
		$linked_template_ids = array_unique($linked_template_ids);
		$writable_templates = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => $linked_template_ids,
			'editable' => true,
			'preservekeys' => true
		]);
	}
	if ($linked_hosts_ids) {
		$linked_hosts_ids = array_unique($linked_hosts_ids);
		$writable_hosts = API::Host()->get([
			'output' => ['hostid'],
			'hostsids' => $linked_hosts_ids,
			'editable' => true,
			'preservekeys' => true
		]);
	}

	$data = [
		'pageFilter' => $pageFilter,
		'templates' => $templates,
		'paging' => $paging,
		'filter' => $filter,
		'sortField' => $sortField,
		'sortOrder' => $sortOrder,
		'config' => [
			'max_in_table' => $config['max_in_table']
		],
		'writable_templates' => $writable_templates,
		'writable_hosts' => $writable_hosts,
		'profileIdx' => 'web.templates.filter',
		'active_tab' => CProfile::get('web.templates.filter.active', 1),
		'tags' => makeTags($templates, true, 'templateid', ZBX_TAG_COUNT_DEFAULT, $filter['tags'])
	];

	$view = new CView('configuration.template.list', $data);
}

$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';
