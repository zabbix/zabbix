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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


$discoveryRule = $data['discovery_rule'];
$hostPrototype = $data['host_prototype'];
$parentHost = $data['parent_host'];

require_once dirname(__FILE__).'/js/configuration.host.edit.js.php';
require_once dirname(__FILE__).'/js/configuration.host.prototype.edit.js.php';

$widget = (new CWidget())
	->setTitle(_('Host prototypes'))
	->addItem(get_header_host_table('hosts', $discoveryRule['hostid'], $discoveryRule['itemid']));

$divTabs = new CTabView();
if (!isset($_REQUEST['form_refresh'])) {
	$divTabs->setSelected(0);
}

$frmHost = (new CForm())
	->setName('hostPrototypeForm.')
	->addVar('form', getRequest('form', 1))
	->addVar('parent_discoveryid', $discoveryRule['itemid']);

$hostList = new CFormList('hostlist');

if ($hostPrototype['templateid'] && $data['parents']) {
	$parents = [];
	foreach (array_reverse($data['parents']) as $parent) {
		$parents[] = (new CLink(
			$parent['parentHost']['name'],
			'?form=update&hostid='.$parent['hostid'].'&parent_discoveryid='.$parent['discoveryRule']['itemid'])
		)
			->addClass('highlight')
			->addClass('underline')
			->addClass('weight_normal');
		$parents[] = SPACE.'&rArr;'.SPACE;
	}
	array_pop($parents);
	$hostList->addRow(_('Parent discovery rules'), $parents);
}

if (isset($hostPrototype['hostid'])) {
	$frmHost->addVar('hostid', $hostPrototype['hostid']);
}

$hostTB = (new CTextBox('host', $hostPrototype['host'], (bool) $hostPrototype['templateid']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAttribute('maxlength', 64)
	->setAttribute('autofocus', 'autofocus');
$hostList->addRow(_('Host name'), $hostTB);

$name = ($hostPrototype['name'] != $hostPrototype['host']) ? $hostPrototype['name'] : '';
$visiblenameTB = (new CTextBox('name', $name, (bool) $hostPrototype['templateid']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAttribute('maxlength', 64);
$hostList->addRow(_('Visible name'), $visiblenameTB);

// display inherited parameters only for hosts prototypes on hosts
if ($parentHost['status'] != HOST_STATUS_TEMPLATE) {
	$existingInterfaceTypes = [];
	foreach ($parentHost['interfaces'] as $interface) {
		$existingInterfaceTypes[$interface['type']] = true;
	}

	zbx_add_post_js('hostInterfacesManager.add('.CJs::encodeJson(array_values($parentHost['interfaces'])).');');
	zbx_add_post_js('hostInterfacesManager.disable();');

	// Zabbix agent interfaces
	$ifTab = (new CTable())
		->setId('agentInterfaces')
		->setHeader([
		new CColHeader(),
		new CColHeader(_('IP address')),
		new CColHeader(_('DNS name')),
		new CColHeader(_('Connect to')),
		new CColHeader(_('Port')),
		(new CColHeader(_('Default')))->setColSpan(2)
	]);

	$row = (new CRow())->setId('agentInterfacesFooter');
	if (!array_key_exists(INTERFACE_TYPE_AGENT, $existingInterfaceTypes)) {
		$row->addItem(new CCol());
		$row->addItem((new CCol(_('No agent interfaces found.')))->setColSpan(6));
	}
	$ifTab->addRow($row);

	$hostList->addRow(_('Agent interfaces'),
		(new CDiv($ifTab))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('data-type', 'agent')
	);

	// SNMP interfaces
	$ifTab = (new CTable())->setId('SNMPInterfaces');

	$row = (new CRow())->setId('SNMPInterfacesFooter');
	if (!array_key_exists(INTERFACE_TYPE_SNMP, $existingInterfaceTypes)) {
		$row->addItem(new CCol());
		$row->addItem((new CCol(_('No SNMP interfaces found.')))->setColSpan(6));
	}
	$ifTab->addRow($row);

	$hostList->addRow(_('SNMP interfaces'),
		(new CDiv($ifTab))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('data-type', 'snmp')
	);

	// JMX interfaces
	$ifTab = (new CTable())->setId('JMXInterfaces');

	$row = (new CRow())->setId('JMXInterfacesFooter');
	if (!array_key_exists(INTERFACE_TYPE_JMX, $existingInterfaceTypes)) {
		$row->addItem(new CCol());
		$row->addItem((new CCol(_('No JMX interfaces found.')))->setColSpan(6));
	}
	$ifTab->addRow($row);

	$hostList->addRow(_('JMX interfaces'),
		(new CDiv($ifTab))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('data-type', 'jmx')
	);

	// IPMI interfaces
	$ifTab = (new CTable())->setId('IPMIInterfaces');

	$row = (new CRow())->setId('IPMIInterfacesFooter');
	if (!array_key_exists(INTERFACE_TYPE_IPMI, $existingInterfaceTypes)) {
		$row->addItem(new CCol());
		$row->addItem((new CCol(_('No IPMI interfaces found.')))->setColSpan(6));
	}
	$ifTab->addRow($row);

	$hostList->addRow(
		_('IPMI interfaces'),
		(new CDiv($ifTab))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('data-type', 'ipmi')
	);

	// proxy
	$proxyTb = (new CTextBox('proxy_hostid',
		$parentHost['proxy_hostid'] != 0 ? $this->data['proxy']['host'] : _('(no proxy)'), true
	))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
	$hostList->addRow(_('Monitored by proxy'), $proxyTb);
}

$hostList->addRow(_('Enabled'),
	(new CCheckBox('status', HOST_STATUS_MONITORED))
		->setChecked(HOST_STATUS_MONITORED == $hostPrototype['status'])
);

$divTabs->addTab('hostTab', _('Host'), $hostList);

// groups
$groupList = new CFormList();

// existing groups
$groups = [];
foreach ($data['groups'] as $group) {
	$groups[] = [
		'id' => $group['groupid'],
		'name' => $group['name']
	];
}
$groupList->addRow(_('Groups'),
	(new CMultiSelect([
		'name' => 'group_links[]',
		'objectName' => 'hostGroup',
		'objectOptions' => [
			'editable' => true,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
		],
		'data' => $groups,
		'disabled' => (bool) $hostPrototype['templateid'],
		'popup' => [
			'parameters' => 'srctbl=host_groups&dstfrm='.$frmHost->getName().'&dstfld1=group_links_'.
				'&srcfld1=groupid&writeonly=1&multiselect=1&normal_only=1'
		]
	]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// new group prototypes
$customGroupTable = (new CTable())->setId('tbl_group_prototypes');

// buttons
$addButton = (new CButton('group_prototype_add', _('Add')))->addClass(ZBX_STYLE_BTN_LINK);
$buttonColumn = (new CCol($addButton))->setAttribute('colspan', 5);

$buttonRow = (new CRow())
	->setId('row_new_group_prototype')
	->addItem($buttonColumn);

$customGroupTable->addRow($buttonRow);
$groupList->addRow(_('Group prototypes'), (new CDiv($customGroupTable))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR));

$divTabs->addTab('groupTab', _('Groups'), $groupList);

// templates
$tmplList = new CFormList();

if ($hostPrototype['templateid']) {
	$linkedTemplateTable = (new CTable())
		->setNoDataMessage(_('No templates linked.'))
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->setHeader([_('Name')]);

	foreach ($hostPrototype['templates'] as $template) {
		$tmplList->addVar('templates['.$template['templateid'].']', $template['templateid']);
		$templateLink = (new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid']))
			->setTarget('_blank');

		$linkedTemplateTable->addRow([$templateLink]);
	}

	$tmplList->addRow(_('Linked templates'),
		(new CDiv($linkedTemplateTable))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	);
}
else {
	$ignoreTemplates = [];

	$linkedTemplateTable = (new CTable())
		->setNoDataMessage(_('No templates linked.'))
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->setHeader([_('Name'), _('Action')]);

	foreach ($hostPrototype['templates'] as $template) {
		$tmplList->addVar('templates['.$template['templateid'].']', $template['templateid']);
		$templateLink = (new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid']))
			->setTarget('_blank');

		$linkedTemplateTable->addRow([
			$templateLink,
			(new CSubmit('unlink['.$template['templateid'].']', _('Unlink')))->addClass(ZBX_STYLE_BTN_LINK)
		]);

		$ignoreTemplates[$template['templateid']] = $template['name'];
	}

	$tmplList->addRow(_('Linked templates'),
		(new CDiv($linkedTemplateTable))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	);

	// create new linked template table
	$newTemplateTable = (new CTable())
		->addRow([
			(new CMultiSelect([
				'name' => 'add_templates[]',
				'objectName' => 'templates',
				'ignored' => $ignoreTemplates,
				'popup' => [
					'parameters' => 'srctbl=templates&srcfld1=hostid&srcfld2=host&dstfrm='.$frmHost->getName().
						'&dstfld1=add_templates_&templated_hosts=1&multiselect=1'
				]
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		])
		->addRow([(new CSubmit('add_template', _('Add')))->addClass(ZBX_STYLE_BTN_LINK)]);

	$tmplList->addRow(_('Link new templates'),
		(new CDiv($newTemplateTable))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	);
}

$divTabs->addTab('templateTab', _('Templates'), $tmplList);

// display inherited parameters only for hosts prototypes on hosts
if ($parentHost['status'] != HOST_STATUS_TEMPLATE) {
	// IPMI
	$ipmiList = new CFormList();

	$ipmiList->addRow(_('Authentication algorithm'),
		(new CTextBox('ipmi_authtype', ipmiAuthTypes($parentHost['ipmi_authtype']), true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);
	$ipmiList->addRow(_('Privilege level'),
		(new CTextBox('ipmi_privilege', ipmiPrivileges($parentHost['ipmi_privilege']), true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);
	$ipmiList->addRow(_('Username'),
		(new CTextBox('ipmi_username', $parentHost['ipmi_username'], true))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);
	$ipmiList->addRow(_('Password'),
		(new CTextBox('ipmi_password', $parentHost['ipmi_password'], true))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);

	$divTabs->addTab('ipmiTab', _('IPMI'), $ipmiList);

	// macros
	$macros = $parentHost['macros'];
	if ($data['show_inherited_macros']) {
		$macros = mergeInheritedMacros($macros,
			getInheritedMacros(zbx_objectValues($hostPrototype['templates'], 'templateid'))
		);
	}
	$macros = array_values(order_macros($macros, 'macro'));

	$macrosView = new CView('hostmacros', [
		'macros' => $macros,
		'show_inherited_macros' => $data['show_inherited_macros'],
		'readonly' => true
	]);
	$divTabs->addTab('macroTab', _('Macros'), $macrosView->render());
}

$inventoryFormList = new CFormList('inventorylist');

// radio buttons for inventory type choice
$inventoryMode = (isset($hostPrototype['inventory']['inventory_mode'])) ? $hostPrototype['inventory']['inventory_mode'] : HOST_INVENTORY_DISABLED;
$inventoryDisabledBtn = (new CRadioButton('inventory_mode', HOST_INVENTORY_DISABLED, $inventoryMode == HOST_INVENTORY_DISABLED))
	->setId('host_inventory_radio_'.HOST_INVENTORY_DISABLED)
	->setEnabled(!$hostPrototype['templateid']);

$inventoryManualBtn = (new CRadioButton('inventory_mode', HOST_INVENTORY_MANUAL, $inventoryMode == HOST_INVENTORY_MANUAL))
	->setId('host_inventory_radio_'.HOST_INVENTORY_MANUAL)
	->setEnabled(!$hostPrototype['templateid']);

$inventoryAutomaticBtn = (new CRadioButton('inventory_mode', HOST_INVENTORY_AUTOMATIC, $inventoryMode == HOST_INVENTORY_AUTOMATIC))
	->setId('host_inventory_radio_'.HOST_INVENTORY_AUTOMATIC)
	->setEnabled(!$hostPrototype['templateid']);

$inventoryTypeRadioButton = [
	$inventoryDisabledBtn,
	new CLabel(_('Disabled'), 'host_inventory_radio_'.HOST_INVENTORY_DISABLED),
	$inventoryManualBtn,
	new CLabel(_('Manual'), 'host_inventory_radio_'.HOST_INVENTORY_MANUAL),
	$inventoryAutomaticBtn,
	new CLabel(_('Automatic'), 'host_inventory_radio_'.HOST_INVENTORY_AUTOMATIC),
];
$inventoryFormList->addRow((new CDiv($inventoryTypeRadioButton))->addClass('jqueryinputset')->addClass('radioset'));

// clearing the float
$clearFixDiv = (new CDiv())->addStyle('clear: both;');
$inventoryFormList->addRow('', $clearFixDiv);

$divTabs->addTab('inventoryTab', _('Host inventory'), $inventoryFormList);

/*
 * footer
 */
if (isset($hostPrototype['hostid'])) {
	$btnDelete = new CButtonDelete(
		_('Delete selected host prototype?'),
		url_param('form').url_param('hostid').url_param('parent_discoveryid')
	);
	$btnDelete->setEnabled($hostPrototype['templateid'] == 0);

	$divTabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CSubmit('clone', _('Clone')),
			$btnDelete,
			new CButtonCancel(url_param('parent_discoveryid'))
		]
	));
}
else {
	$divTabs->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('parent_discoveryid'))]
	));
}

$frmHost->addItem($divTabs);
$widget->addItem($frmHost);

return $widget;
