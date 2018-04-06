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

$widget = (new CWidget())
	->setTitle(_('Templates'))
	->addItem(get_header_host_table('', $data['templateid']));

$divTabs = new CTabView();
if (!isset($_REQUEST['form_refresh'])) {
	$divTabs->setSelected(0);
}

$host = getRequest('template_name', '');
$visiblename = getRequest('visiblename', '');
$newgroup = getRequest('newgroup', '');
$templateIds = getRequest('templates', []);
$clear_templates = getRequest('clear_templates', []);
$macros = getRequest('macros', []);

$frm_title = _('Template');

if ($data['templateid'] != 0) {
	$frm_title .= SPACE.' ['.$this->data['dbTemplate']['name'].']';
}
$frmHost = (new CForm())
	->setName('templatesForm')
	->addVar('form', $data['form']);

if ($data['templateid'] != 0) {
	$frmHost->addVar('templateid', $data['templateid']);
}

if ($data['templateid'] != 0 && !hasRequest('form_refresh')) {
	$host = $this->data['dbTemplate']['host'];
	$visiblename = $this->data['dbTemplate']['name'];

	// display empty visible name if equal to host name
	if ($visiblename === $host) {
		$visiblename = '';
	}

	$macros = $this->data['dbTemplate']['macros'];
	$templateIds = $this->data['original_templates'];
}

if ($data['show_inherited_macros']) {
	$macros = mergeInheritedMacros($macros, getInheritedMacros($templateIds));
}
$macros = array_values(order_macros($macros, 'macro'));

$clear_templates = array_intersect($clear_templates, array_keys($this->data['original_templates']));
$clear_templates = array_diff($clear_templates, array_keys($templateIds));
natcasesort($templateIds);
$frmHost->addVar('clear_templates', $clear_templates);

// TEMPLATE WIDGET {
$templateList = (new CFormList('hostlist'))
	->addRow(
		(new CLabel(_('Template name'), 'template_name'))->setAsteriskMark(),
		(new CTextBox('template_name', $host, false, 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Visible name'), (new CTextBox('visiblename', $visiblename, false, 128))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow((new CLabel(_('Groups'), 'groups[]'))->setAsteriskMark(),
		(new CMultiSelect([
			'name' => 'groups[]',
			'object_name' => 'hostGroup',
			'add_new' => (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN),
			'data' => $data['groups_ms'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $frmHost->getName(),
					'dstfld1' => 'groups_',
					'editable' => true
				]
			]
		]))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Description'),
		(new CTextArea('description', $data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

if ($data['form'] === 'full_clone') {
	// template applications
	$templateApps = API::Application()->get([
		'hostids' => $data['templateid'],
		'inherited' => false,
		'output' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	]);
	if (!empty($templateApps)) {
		$applicationsList = [];
		foreach ($templateApps as $tplAppId => $templateApp) {
			$applicationsList[$tplAppId] = $templateApp['name'];
		}
		order_result($applicationsList);

		$listBox = (new CListBox('applications', null, 8))
			->setAttribute('disabled', 'disabled')
			->addItems($applicationsList);
		$templateList->addRow(_('Applications'), $listBox);
	}

	// items
	$hostItems = API::Item()->get([
		'hostids' => $data['templateid'],
		'inherited' => false,
		'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
		'output' => ['itemid', 'key_', 'name', 'hostid']
	]);

	if ($hostItems) {
		$hostItems = CMacrosResolverHelper::resolveItemNames($hostItems);

		$itemsList = [];
		foreach ($hostItems as $hostItem) {
			$itemsList[$hostItem['itemid']] = $hostItem['name_expanded'];
		}
		order_result($itemsList);

		$listBox = (new CListBox('items', null, 8))
			->setAttribute('disabled', 'disabled')
			->addItems($itemsList);

		$templateList->addRow(_('Items'), $listBox);
	}

// Triggers
	$hostTriggers = API::Trigger()->get([
		'inherited' => false,
		'hostids' => $data['templateid'],
		'output' => API_OUTPUT_EXTEND,
		'filter' => ['flags' => [ZBX_FLAG_DISCOVERY_NORMAL]]
	]);
	if (!empty($hostTriggers)) {
		$triggersList = [];
		foreach ($hostTriggers as $hostTrigger) {
			$triggersList[$hostTrigger['triggerid']] = $hostTrigger['description'];
		}
		order_result($triggersList);

		$listBox = (new CListBox('triggers', null, 8))
			->setAttribute('disabled', 'disabled')
			->addItems($triggersList);

		$templateList->addRow(_('Triggers'), $listBox);
	}

// Graphs
	$hostGraphs = API::Graph()->get([
		'inherited' => false,
		'hostids' => $data['templateid'],
		'filter' => ['flags' => [ZBX_FLAG_DISCOVERY_NORMAL]],
		'output' => API_OUTPUT_EXTEND,
	]);
	if (!empty($hostGraphs)) {
		$graphsList = [];
		foreach ($hostGraphs as $hostGraph) {
			$graphsList[$hostGraph['graphid']] = $hostGraph['name'];
		}
		order_result($graphsList);

		$listBox = (new CListBox('graphs', null, 8))
			->setAttribute('disabled', 'disabled')
			->addItems($graphsList);

		$templateList->addRow(_('Graphs'), $listBox);
	}

	// discovery rules
	$hostDiscoveryRules = API::DiscoveryRule()->get([
		'inherited' => false,
		'hostids' => $data['templateid'],
		'output' => API_OUTPUT_EXTEND,
	]);

	if ($hostDiscoveryRules) {
		$hostDiscoveryRules = CMacrosResolverHelper::resolveItemNames($hostDiscoveryRules);

		$discoveryRuleList = [];
		foreach ($hostDiscoveryRules as $discoveryRule) {
			$discoveryRuleList[$discoveryRule['itemid']] = $discoveryRule['name_expanded'];
		}
		order_result($discoveryRuleList);
		$hostDiscoveryRuleids = array_keys($discoveryRuleList);

		$listBox = (new CListBox('discoveryRules', null, 8))
			->setAttribute('disabled', 'disabled')
			->addItems($discoveryRuleList);

		$templateList->addRow(_('Discovery rules'), $listBox);

		// item prototypes
		$hostItemPrototypes = API::ItemPrototype()->get([
			'hostids' => $data['templateid'],
			'discoveryids' => $hostDiscoveryRuleids,
			'inherited' => false,
			'output' => API_OUTPUT_EXTEND,
		]);

		if ($hostItemPrototypes) {
			$hostItemPrototypes = CMacrosResolverHelper::resolveItemNames($hostItemPrototypes);

			$prototypeList = [];
			foreach ($hostItemPrototypes as $itemPrototype) {
				$prototypeList[$itemPrototype['itemid']] = $itemPrototype['name_expanded'];
			}
			order_result($prototypeList);

			$listBox = (new CListBox('itemsPrototypes', null, 8))
				->setAttribute('disabled', 'disabled')
				->addItems($prototypeList);

			$templateList->addRow(_('Item prototypes'), $listBox);
		}

// Trigger prototypes
		$hostTriggerPrototypes = API::TriggerPrototype()->get([
			'hostids' => $data['templateid'],
			'discoveryids' => $hostDiscoveryRuleids,
			'inherited' => false,
			'output' => API_OUTPUT_EXTEND
		]);
		if (!empty($hostTriggerPrototypes)) {
			$prototypeList = [];
			foreach ($hostTriggerPrototypes as $triggerPrototype) {
				$prototypeList[$triggerPrototype['triggerid']] = $triggerPrototype['description'];
			}
			order_result($prototypeList);

			$listBox = (new CListBox('triggerprototypes', null, 8))
				->setAttribute('disabled', 'disabled')
				->addItems($prototypeList);

			$templateList->addRow(_('Trigger prototypes'), $listBox);
		}

// Graph prototypes
		$hostGraphPrototypes = API::GraphPrototype()->get([
			'hostids' => $data['templateid'],
			'discoveryids' => $hostDiscoveryRuleids,
			'inherited' => false,
			'output' => API_OUTPUT_EXTEND,
		]);
		if (!empty($hostGraphPrototypes)) {
			$prototypeList = [];
			foreach ($hostGraphPrototypes as $graphPrototype) {
				$prototypeList[$graphPrototype['graphid']] = $graphPrototype['name'];
			}
			order_result($prototypeList);

			$listBox = (new CListBox('graphPrototypes', null, 8))
				->setAttribute('disabled', 'disabled')
				->addItems($prototypeList);

			$templateList->addRow(_('Graph prototypes'), $listBox);
		}
	}

	// screens
	$screens = API::TemplateScreen()->get([
		'inherited' => false,
		'templateids' => $data['templateid'],
		'output' => ['screenid', 'name'],
	]);
	if (!empty($screens)) {
		$screensList = [];
		foreach ($screens as $screen) {
			$screensList[$screen['screenid']] = $screen['name'];
		}
		order_result($screensList);

		$listBox = (new CListBox('screens', null, 8))
			->setAttribute('disabled', 'disabled')
			->addItems($screensList);

		$templateList->addRow(_('Screens'), $listBox);
	}

	// web scenarios
	$httpTests = API::HttpTest()->get([
		'output' => ['httptestid', 'name'],
		'hostids' => $data['templateid'],
		'inherited' => false
	]);

	if ($httpTests) {
		$httpTestList = [];

		foreach ($httpTests as $httpTest) {
			$httpTestList[$httpTest['httptestid']] = $httpTest['name'];
		}

		order_result($httpTestList);

		$listBox = (new CListBox('httpTests', null, 8))
			->setAttribute('disabled', 'disabled')
			->addItems($httpTestList);
		$templateList->addRow(_('Web scenarios'), $listBox);
	}
}

$cloneOrFullClone = ($data['form'] === 'clone' || $data['form'] === 'full_clone');

$divTabs->addTab('templateTab', _('Template'), $templateList);
// FULL CLONE }

// } TEMPLATE WIDGET

// TEMPLATES{
$tmplList = new CFormList();

$ignored_templates = [];

if ($data['templateid'] != 0) {
	$ignored_templates[$data['templateid']] = $data['dbTemplate']['host'];
}

$linkedTemplateTable = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Name'), _('Action')]);

foreach ($data['linkedTemplates'] as $template) {
	$tmplList->addVar('templates[]', $template['templateid']);

	if (array_key_exists($template['templateid'], $data['writable_templates'])) {
		$template_link = (new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid']))
			->setTarget('_blank');
	}
	else {
		$template_link = new CSpan($template['name']);
	}

	$linkedTemplateTable->addRow([
		$template_link,
		(new CCol(
			new CHorList([
				(new CSimpleButton(_('Unlink')))
					->onClick('javascript: submitFormWithParam('.
						'"'.$frmHost->getName().'", "unlink['.$template['templateid'].']", "1"'.
					');')
					->addClass(ZBX_STYLE_BTN_LINK),
				(array_key_exists($template['templateid'], $data['original_templates']) && !$cloneOrFullClone)
					? (new CSimpleButton(_('Unlink and clear')))
						->onClick('javascript: submitFormWithParam('.
							'"'.$frmHost->getName().'", "unlink_and_clear['.$template['templateid'].']", "1"'.
						');')
						->addClass(ZBX_STYLE_BTN_LINK)
					: null
			])
		))->addClass(ZBX_STYLE_NOWRAP)
	], null, 'conditions_'.$template['templateid']);

	$ignored_templates[$template['templateid']] = $template['name'];
}

$tmplList->addRow(_('Linked templates'),
	(new CDiv($linkedTemplateTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

// create new linked template table
$newTemplateTable = (new CTable())
	->addRow([
		(new CMultiSelect([
			'name' => 'add_templates[]',
			'object_name' => 'templates',
			'ignored' => $ignored_templates,
			'popup' => [
				'parameters' => [
					'srctbl' => 'templates',
					'srcfld1' => 'hostid',
					'srcfld2' => 'host',
					'dstfrm' => $frmHost->getName(),
					'dstfld1' => 'add_templates_',
					'templated_hosts' => true,
					'templateid' => $data['templateid']
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	])
	->addRow([
		(new CSimpleButton(_('Add')))
			->onClick('javascript: submitFormWithParam("'.$frmHost->getName().'", "add_template", "1");')
			->addClass(ZBX_STYLE_BTN_LINK)
	]);

$tmplList->addRow(_('Link new templates'),
	(new CDiv($newTemplateTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$divTabs->addTab('tmplTab', _('Linked templates'), $tmplList);
// } TEMPLATES

// macros
if (!$macros) {
	$macro = ['macro' => '', 'value' => ''];
	if ($data['show_inherited_macros']) {
		$macro['type'] = MACRO_TYPE_HOSTMACRO;
	}
	$macros[] = $macro;
}

$macrosView = new CView('hostmacros', [
	'macros' => $macros,
	'show_inherited_macros' => $data['show_inherited_macros'],
	'is_template' => true,
	'readonly' => false
]);
$divTabs->addTab('macroTab', _('Macros'), $macrosView->render());


// Footer
if ($data['templateid'] != 0 && $data['form'] !== 'full_clone') {
	$divTabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CSubmit('clone', _('Clone')),
			new CSubmit('full_clone', _('Full clone')),
			new CButtonDelete(_('Delete template?'), url_param('form').url_param('templateid').url_param('groupid')),
			new CButtonQMessage(
				'delete_and_clear',
				_('Delete and clear'),
				_('Delete and clear template? (Warning: all linked hosts will be cleared!)'),
				url_param('form').url_param('templateid').url_param('groupid')
			),
			new CButtonCancel(url_param('groupid'))
		]
	));
}
else {
	$divTabs->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('groupid'))]
	));
}

$frmHost->addItem($divTabs);

$widget->addItem($frmHost);

return $widget;
