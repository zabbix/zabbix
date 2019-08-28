<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
require_once dirname(__FILE__).'/include/screens.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/ident.inc.php';

$page['type'] = detect_page_type(PAGE_TYPE_HTML);
$page['title'] = _('Configuration of templates');
$page['file'] = 'templates.php';
$page['scripts'] = ['multiselect.js', 'textareaflexible.js'];

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR						TYPE		OPTIONAL FLAGS			VALIDATION	EXCEPTION
$fields = [
	'groups'			=> [T_ZBX_STR, O_OPT, null,			NOT_EMPTY,	'isset({add}) || isset({update})'],
	'mass_update_groups' => [T_ZBX_INT, O_OPT, null,	IN([ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
								null
							],
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
	'mass_update_tags'	=> [T_ZBX_INT, O_OPT, null,		IN([ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
								null
							],
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
if (hasRequest('add_template') && hasRequest('add_templates')) {
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
elseif (hasRequest('templateid') && (hasRequest('clone') || hasRequest('full_clone'))) {
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
elseif (hasRequest('action') && getRequest('action') === 'template.massupdate' && hasRequest('masssave')) {
	$templateids = getRequest('templates', []);
	$visible = getRequest('visible', []);

	try {
		DBstart();

		$options = [
			'output' => ['templateid'],
			'templateids' => $templateids
		];

		if (array_key_exists('groups', $visible)) {
			$options['selectGroups'] = ['groupid'];
		}

		if (array_key_exists('linked_templates', $visible) && !hasRequest('mass_replace_tpls')) {
			$options['selectParentTemplates'] = ['templateid'];
		}

		if (array_key_exists('tags', $visible)) {
			$mass_update_tags = getRequest('mass_update_tags', ZBX_ACTION_ADD);

			if ($mass_update_tags == ZBX_ACTION_ADD || $mass_update_tags == ZBX_ACTION_REMOVE) {
				$options['selectTags'] = ['tag', 'value'];
			}

			$unique_tags = [];

			foreach ($tags as $tag) {
				$unique_tags[$tag['tag'].':'.$tag['value']] = $tag;
			}

			$tags = array_values($unique_tags);
		}

		$templates = API::Template()->get($options);

		if (array_key_exists('groups', $visible)) {
			$new_groupids = [];
			$remove_groupids = [];
			$mass_update_groups = getRequest('mass_update_groups', ZBX_ACTION_ADD);

			if ($mass_update_groups == ZBX_ACTION_ADD || $mass_update_groups == ZBX_ACTION_REPLACE) {
				if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
					$ins_groups = [];

					foreach (getRequest('groups', []) as $new_group) {
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
					$new_groupids = getRequest('groups', []);
				}
			}
			elseif ($mass_update_groups == ZBX_ACTION_REMOVE) {
				$remove_groupids = getRequest('groups', []);
			}
		}

		$new_values = [];

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
			if (array_key_exists('groups', $visible)) {
				if ($new_groupids && $mass_update_groups == ZBX_ACTION_ADD) {
					$current_groupids = zbx_objectValues($template['groups'], 'groupid');
					$template['groups'] = zbx_toObject(array_unique(array_merge($current_groupids, $new_groupids)),
						'groupid'
					);
				}
				elseif ($new_groupids && $mass_update_groups == ZBX_ACTION_REPLACE) {
					$template['groups'] = zbx_toObject($new_groupids, 'groupid');
				}
				elseif ($remove_groupids) {
					$current_groupids = zbx_objectValues($template['groups'], 'groupid');
					$template['groups'] = zbx_toObject(array_diff($current_groupids, $remove_groupids), 'groupid');
				}
			}

			if ($linked_templateids && array_key_exists('parentTemplates', $template)) {
				$template['templates'] = array_unique(
					array_merge($linked_templateids, zbx_objectValues($template['parentTemplates'], 'templateid'))
				);
			}

			if (array_key_exists('tags', $visible)) {
				if ($tags && $mass_update_tags == ZBX_ACTION_ADD) {
					$unique_tags = [];

					foreach (array_merge($template['tags'], $tags) as $tag) {
						$unique_tags[$tag['tag'].':'.$tag['value']] = $tag;
					}

					$template['tags'] = array_values($unique_tags);
				}
				elseif ($mass_update_tags == ZBX_ACTION_REPLACE) {
					$template['tags'] = $tags;
				}
				elseif ($tags && $mass_update_tags == ZBX_ACTION_REMOVE) {
					$diff_tags = [];

					foreach ($template['tags'] as $a) {
						foreach ($tags as $b) {
							if ($a['tag'] === $b['tag'] && $a['value'] === $b['value']) {
								continue 2;
							}
						}

						$diff_tags[] = $a;
					}

					$template['tags'] = $diff_tags;
				}
			}

			unset($template['parentTemplates']);

			$template = $new_values + $template;
		}
		unset($template);

		if (!API::Template()->update($templates)) {
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
elseif (hasRequest('templateid') && hasRequest('delete')) {
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
elseif (hasRequest('templateid') && hasRequest('delete_and_clear')) {
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
elseif (hasRequest('templates') && hasRequest('action') && str_in_array(getRequest('action'), ['template.massdelete', 'template.massdeleteclear'])) {
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
	else {
		$templateids = API::Template()->get([
			'output' => [],
			'templateids' => $templates,
			'editable' => true
		]);
		uncheckTableRows(null, zbx_objectValues($templateids, 'templateid'));
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

if (hasRequest('templates') && (getRequest('action') === 'template.massupdateform' || hasRequest('masssave'))) {
	$data = [
		'templates' => getRequest('templates', []),
		'visible' => getRequest('visible', []),
		'mass_replace_tpls' => getRequest('mass_replace_tpls'),
		'mass_clear_tpls' => getRequest('mass_clear_tpls'),
		'groups' => getRequest('groups', []),
		'tags' => $tags,
		'description' => getRequest('description'),
		'linked_templates' => getRequest('linked_templates', [])
	];

	// sort templates
	natsort($data['linked_templates']);

	if (!$data['tags']) {
		$data['tags'][] = ['tag' => '', 'value' => ''];
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
		'linked_templates' => getRequest('templates', []),
		'original_templates' => [],
		'parent_templates' => [],
		'tags' => $tags,
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

		if (!hasRequest('form_refresh')) {
			$data['tags'] = $data['dbTemplate']['tags'];
		}
	}

	// description
	$data['description'] = ($data['templateid'] != 0 && !hasRequest('form_refresh'))
		? $data['dbTemplate']['description']
		: getRequest('description', '');

	// tags
	if (!$data['tags']) {
		$data['tags'][] = ['tag' => '', 'value' => ''];
	}
	else {
		CArrayHelper::sort($data['tags'], ['tag', 'value']);
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
		'evaltype' => CProfile::get('web.templates.filter.evaltype', TAG_EVAL_TYPE_AND_OR),
		'tags' => []
	];

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
