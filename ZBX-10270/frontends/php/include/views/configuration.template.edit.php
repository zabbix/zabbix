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


$divTabs = new CTabView();
if (!isset($_REQUEST['form_refresh'])) {
	$divTabs->setSelected(0);
}

$templateid = get_request('templateid', 0);
$host = get_request('template_name', '');
$visiblename = get_request('visiblename', '');
$newgroup = get_request('newgroup', '');
$templateIds = get_request('templates', array());
$clear_templates = get_request('clear_templates', array());
$macros = get_request('macros', array());

$frm_title = _('Template');

if ($templateid > 0) {
	$frm_title .= SPACE.' ['.$this->data['dbTemplate']['name'].']';
}
$frmHost = new CForm();
$frmHost->setName('tpl_for');

$frmHost->addVar('form', get_request('form', 1));
$frmHost->addVar('groupid', $_REQUEST['groupid']);

if ($templateid) {
	$frmHost->addVar('templateid', $templateid);
}

if ($templateid > 0 && !hasRequest('form_refresh')) {
	$host = $this->data['dbTemplate']['host'];
	$visiblename = $this->data['dbTemplate']['name'];

	// display empty visible name if equal to host name
	if ($visiblename === $host) {
		$visiblename = '';
	}

	// get template groups from db
	$groups = $this->data['dbTemplate']['groups'];
	$groups = zbx_objectValues($groups, 'groupid');

	$macros = order_macros($this->data['dbTemplate']['macros'], 'macro');

	// get template hosts from db
	$hosts_linked_to = API::Host()->get(array(
		'output' => array('hostid'),
		'templateids' => $templateid,
		'templated_hosts' => true
	));

	$hosts_linked_to = zbx_objectValues($hosts_linked_to, 'hostid');
	$hosts_linked_to = zbx_toHash($hosts_linked_to, 'hostid');
	$templateIds = $this->data['original_templates'];
}
else {
	$groups = get_request('groups', array());
	if (isset($_REQUEST['groupid']) && ($_REQUEST['groupid'] > 0) && !uint_in_array($_REQUEST['groupid'], $groups)) {
		array_push($groups, $_REQUEST['groupid']);
	}
	$hosts_linked_to = get_request('hosts', array());
}

$clear_templates = array_intersect($clear_templates, array_keys($this->data['original_templates']));
$clear_templates = array_diff($clear_templates, array_keys($templateIds));
natcasesort($templateIds);
$frmHost->addVar('clear_templates', $clear_templates);

// TEMPLATE WIDGET {
$templateList = new CFormList('hostlist');

// FORM ITEM : Template name text box [  ]
$template_nameTB = new CTextBox('template_name', $host, 54);
$template_nameTB->setAttribute('maxlength', 64);
$template_nameTB->attr('autofocus', 'autofocus');
$templateList->addRow(_('Template name'), $template_nameTB);

$visiblenameTB = new CTextBox('visiblename', $visiblename, 54);
$visiblenameTB->setAttribute('maxlength', 64);
$templateList->addRow(_('Visible name'), $visiblenameTB);

// FORM ITEM : Groups tween box [  ] [  ]
// get all Groups
$group_tb = new CTweenBox($frmHost, 'groups', $groups, 10);
$options = array(
	'output' => API_OUTPUT_EXTEND,
	'preservekeys' => true
);
$all_groups = API::HostGroup()->get($options);
order_result($all_groups, 'name');

$groupsAllowed = API::HostGroup()->get(array(
	'output' => array('groupid', 'name'),
	'editable' => true,
	'preservekeys' => true
));
order_result($groupsAllowed, 'name');

if (getRequest('form') === 'update') {
	// add existing template groups to list and, depending on permissions show name as enabled or disabled
	$groupsInList = array();
	if ($groups) {
		$groups = array_combine($groups, $groups);
	}

	foreach ($all_groups as $group) {
		if (isset($groups[$group['groupid']])) {
			$group_tb->addItem($group['groupid'], $group['name'], true,
				isset($groupsAllowed[$group['groupid']])
			);
			$groupsInList[] = $group['groupid'];
		}
	}

	// then add other host groups that user has permissions to, if not yet added to list
	foreach ($groupsAllowed as $group) {
		if (!in_array($group['groupid'], $groupsInList)) {
			$group_tb->addItem($group['groupid'], $group['name']);
		}
	}
}
else {
	/*
	 * When cloning a template or creating a new one, don't show read-only host groups in left box
	 * show empty or posted groups in case of an error.
	 */
	foreach ($groupsAllowed as $group) {
		$group_tb->addItem($group['groupid'], $group['name']);
	}
}

$templateList->addRow(_('Groups'), $group_tb->get(_('In groups'), _('Other groups')));

// FORM ITEM : new group text box [  ]
$newgroupTB = new CTextBox('newgroup', $newgroup);
$newgroupTB->setAttribute('maxlength', 64);
$tmp_label = _('New group');
if (CWebUser::$data['type'] != USER_TYPE_SUPER_ADMIN) {
	$tmp_label .= SPACE._('(Only super admins can create groups)');
	$newgroupTB->setReadonly(true);
}
$templateList->addRow(SPACE, array($tmp_label, BR(), $newgroupTB), null, null, 'new');

// FORM ITEM : linked Hosts tween box [  ] [  ]
$twb_groupid = get_request('twb_groupid', 0);
if ($twb_groupid == 0) {
	$gr = reset($groupsAllowed);
	$twb_groupid = $gr['groupid'];
}
$cmbGroups = new CComboBox('twb_groupid', $twb_groupid, 'submit()');
foreach ($groupsAllowed as $group) {
	$cmbGroups->addItem($group['groupid'], $group['name']);
}

$host_tb = new CTweenBox($frmHost, 'hosts', $hosts_linked_to, 20);

// get hosts from selected twb_groupid combo
$params = array(
	'groupids' => $twb_groupid,
	'templated_hosts' => 1,
	'editable' => 1,
	'output' => API_OUTPUT_EXTEND,
	'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
	'preservekeys' => true
);
$hostsAllowedToAdd = API::Host()->get($params);
order_result($hostsAllowedToAdd, 'name');

// add all except selected hosts
foreach ($hostsAllowedToAdd as $host) {
	if (isset($hosts_linked_to[$host['hostid']])) {
		continue;
	}
	$host_tb->addItem($host['hostid'], $host['name']);
}

// select allowed selected hosts
$params = array(
	'output' => array('hostid', 'name', 'flags'),
	'hostids' => $hosts_linked_to,
	'templated_hosts' => 1,
	'editable' => 1,
	'output' => API_OUTPUT_EXTEND,
	'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
	'preservekeys' => true
);
$hostsAllowed = API::Host()->get($params);
order_result($hostsAllowed, 'name');

// select selected hosts including read only and add them
$hostsAll = API::Host()->get(array(
	'output' => array('hostid', 'name', 'flags'),
	'hostids' => $hosts_linked_to,
	'templated_hosts' => true
));
order_result($hostsAll, 'name');

foreach ($hostsAll as $host) {
	$host_tb->addItem($host['hostid'], $host['name'], true, isset($hostsAllowed[$host['hostid']]));
}

$templateList->addRow(_('Hosts / templates'), $host_tb->Get(_('In'), array(
	_('Other | group').SPACE,
	$cmbGroups
)));

// FULL CLONE {
if ($_REQUEST['form'] == 'full_clone') {
	// template applications
	$templateApps = API::Application()->get(array(
		'hostids' => $templateid,
		'inherited' => false,
		'output' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	));
	if (!empty($templateApps)) {
		$applicationsList = array();
		foreach ($templateApps as $tplAppId => $templateApp) {
			$applicationsList[$tplAppId] = $templateApp['name'];
		}
		order_result($applicationsList);

		$listBox = new CListBox('applications', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($applicationsList);
		$templateList->addRow(_('Applications'), $listBox);
	}

	// items
	$hostItems = API::Item()->get(array(
		'hostids' => $templateid,
		'inherited' => false,
		'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
		'output' => array('itemid', 'key_', 'name', 'hostid')
	));

	if ($hostItems) {
		$hostItems = CMacrosResolverHelper::resolveItemNames($hostItems);

		$itemsList = array();
		foreach ($hostItems as $hostItem) {
			$itemsList[$hostItem['itemid']] = $hostItem['name_expanded'];
		}
		order_result($itemsList);

		$listBox = new CListBox('items', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($itemsList);

		$templateList->addRow(_('Items'), $listBox);
	}

// Triggers
	$hostTriggers = API::Trigger()->get(array(
		'inherited' => false,
		'hostids' => $templateid,
		'output' => API_OUTPUT_EXTEND,
		'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL))
	));
	if (!empty($hostTriggers)) {
		$triggersList = array();
		foreach ($hostTriggers as $hostTrigger) {
			$triggersList[$hostTrigger['triggerid']] = $hostTrigger['description'];
		}
		order_result($triggersList);

		$listBox = new CListBox('triggers', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($triggersList);

		$templateList->addRow(_('Triggers'), $listBox);
	}

// Graphs
	$hostGraphs = API::Graph()->get(array(
		'inherited' => false,
		'hostids' => $templateid,
		'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL)),
		'output' => API_OUTPUT_EXTEND,
	));
	if (!empty($hostGraphs)) {
		$graphsList = array();
		foreach ($hostGraphs as $hostGraph) {
			$graphsList[$hostGraph['graphid']] = $hostGraph['name'];
		}
		order_result($graphsList);

		$listBox = new CListBox('graphs', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($graphsList);

		$templateList->addRow(_('Graphs'), $listBox);
	}

	// discovery rules
	$hostDiscoveryRules = API::DiscoveryRule()->get(array(
		'inherited' => false,
		'hostids' => $templateid,
		'output' => API_OUTPUT_EXTEND,
	));

	if ($hostDiscoveryRules) {
		$hostDiscoveryRules = CMacrosResolverHelper::resolveItemNames($hostDiscoveryRules);

		$discoveryRuleList = array();
		foreach ($hostDiscoveryRules as $discoveryRule) {
			$discoveryRuleList[$discoveryRule['itemid']] = $discoveryRule['name_expanded'];
		}
		order_result($discoveryRuleList);
		$hostDiscoveryRuleids = array_keys($discoveryRuleList);

		$listBox = new CListBox('discoveryRules', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($discoveryRuleList);

		$templateList->addRow(_('Discovery rules'), $listBox);

		// item prototypes
		$hostItemPrototypes = API::ItemPrototype()->get(array(
			'hostids' => $templateid,
			'discoveryids' => $hostDiscoveryRuleids,
			'inherited' => false,
			'output' => API_OUTPUT_EXTEND,
		));

		if ($hostItemPrototypes) {
			$hostItemPrototypes = CMacrosResolverHelper::resolveItemNames($hostItemPrototypes);

			$prototypeList = array();
			foreach ($hostItemPrototypes as $itemPrototype) {
				$prototypeList[$itemPrototype['itemid']] = $itemPrototype['name_expanded'];
			}
			order_result($prototypeList);

			$listBox = new CListBox('itemsPrototypes', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($prototypeList);

			$templateList->addRow(_('Item prototypes'), $listBox);
		}

// Trigger prototypes
		$hostTriggerPrototypes = API::TriggerPrototype()->get(array(
			'hostids' => $templateid,
			'discoveryids' => $hostDiscoveryRuleids,
			'inherited' => false,
			'output' => API_OUTPUT_EXTEND
		));
		if (!empty($hostTriggerPrototypes)) {
			$prototypeList = array();
			foreach ($hostTriggerPrototypes as $triggerPrototype) {
				$prototypeList[$triggerPrototype['triggerid']] = $triggerPrototype['description'];
			}
			order_result($prototypeList);

			$listBox = new CListBox('triggerprototypes', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($prototypeList);

			$templateList->addRow(_('Trigger prototypes'), $listBox);
		}

// Graph prototypes
		$hostGraphPrototypes = API::GraphPrototype()->get(array(
			'hostids' => $templateid,
			'discoveryids' => $hostDiscoveryRuleids,
			'inherited' => false,
			'output' => API_OUTPUT_EXTEND,
		));
		if (!empty($hostGraphPrototypes)) {
			$prototypeList = array();
			foreach ($hostGraphPrototypes as $graphPrototype) {
				$prototypeList[$graphPrototype['graphid']] = $graphPrototype['name'];
			}
			order_result($prototypeList);

			$listBox = new CListBox('graphPrototypes', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($prototypeList);

			$templateList->addRow(_('Graph prototypes'), $listBox);
		}
	}

	// screens
	$screens = API::TemplateScreen()->get(array(
		'inherited' => false,
		'templateids' => $templateid,
		'output' => array('screenid', 'name'),
	));
	if (!empty($screens)) {
		$screensList = array();
		foreach ($screens as $screen) {
			$screensList[$screen['screenid']] = $screen['name'];
		}
		order_result($screensList);

		$listBox = new CListBox('screens', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($screensList);

		$templateList->addRow(_('Screens'), $listBox);
	}

	// web scenarios
	$httpTests = API::HttpTest()->get(array(
		'output' => array('httptestid', 'name'),
		'hostids' => $templateid,
		'inherited' => false
	));

	if ($httpTests) {
		$httpTestList = array();

		foreach ($httpTests as $httpTest) {
			$httpTestList[$httpTest['httptestid']] = $httpTest['name'];
		}

		order_result($httpTestList);

		$listBox = new CListBox('httpTests', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($httpTestList);
		$templateList->addRow(_('Web scenarios'), $listBox);
	}
}

$divTabs->addTab('templateTab', _('Template'), $templateList);
// FULL CLONE }

// } TEMPLATE WIDGET

// TEMPLATES{
$tmplList = new CFormList('tmpllist');

// create linked template table
$linkedTemplateTable = new CTable(_('No templates linked.'), 'formElementTable');
$linkedTemplateTable->attr('id', 'linkedTemplateTable');
$linkedTemplateTable->attr('style', 'min-width: 400px;');
$linkedTemplateTable->setHeader(array(_('Name'), _('Action')));

$ignoredTemplates = array();
foreach ($this->data['linkedTemplates'] as $template) {
	$tmplList->addVar('templates[]', $template['templateid']);

	$linkedTemplateTable->addRow(
		array(
			$template['name'],
			array(
				new CSubmit('unlink['.$template['templateid'].']', _('Unlink'), null, 'link_menu'),
				SPACE,
				SPACE,
				isset($this->data['original_templates'][$template['templateid']])
					? new CSubmit('unlink_and_clear['.$template['templateid'].']', _('Unlink and clear'), null, 'link_menu')
					: SPACE
			)
		),
		null, 'conditions_'.$template['templateid']
	);

	$ignoredTemplates[$template['templateid']] = $template['name'];
}

$tmplList->addRow(_('Linked templates'), new CDiv($linkedTemplateTable, 'objectgroup inlineblock border_dotted ui-corner-all'));

// create new linked template table
$newTemplateTable = new CTable(null, 'formElementTable');
$newTemplateTable->attr('id', 'newTemplateTable');
$newTemplateTable->attr('style', 'min-width: 400px;');

$newTemplateTable->addRow(array(new CMultiSelect(array(
	'name' => 'add_templates[]',
	'objectName' => 'templates',
	'ignored' => $ignoredTemplates,
	'popup' => array(
		'parameters' => 'srctbl=templates&srcfld1=hostid&srcfld2=host&dstfrm='.$frmHost->getName().
			'&dstfld1=add_templates_&templated_hosts=1&multiselect=1',
		'width' => 450,
		'height' => 450
	)
))));

$newTemplateTable->addRow(
	array(
		new CSubmit('add_template', _('Add'), null, 'link_menu')
	)
);

$tmplList->addRow(_('Link new templates'), new CDiv($newTemplateTable, 'objectgroup inlineblock border_dotted ui-corner-all'));

$divTabs->addTab('tmplTab', _('Linked templates'), $tmplList);
// } TEMPLATES

// macros
if (empty($macros)) {
	$macros = array(array('macro' => '', 'value' => ''));
}
$macrosView = new CView('common.macros', array(
	'macros' => $macros
));
$divTabs->addTab('macroTab', _('Macros'), $macrosView->render());

$frmHost->addItem($divTabs);

// Footer
$others = array();
if (($templateid > 0) && ($_REQUEST['form'] != 'full_clone')) {
	$others[] = new CSubmit('clone', _('Clone'));
	$others[] = new CSubmit('full_clone', _('Full clone'));
	$others[] = new CButtonDelete(_('Delete template?'), url_param('form').url_param('templateid').url_param('groupid'));
	$others[] = new CButtonQMessage('delete_and_clear', _('Delete and clear'), _('Delete and clear template? (Warning: all linked hosts will be cleared!)'), url_param('form').url_param('templateid').url_param('groupid'));
}
$others[] = new CButtonCancel(url_param('groupid'));

$frmHost->addItem(makeFormFooter(new CSubmit('save', _('Save')), $others));

return $frmHost;
