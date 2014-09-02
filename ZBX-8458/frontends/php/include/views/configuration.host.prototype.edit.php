<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

$widget = new CWidget(null, 'hostprototype-edit');
$widget->addPageHeader(_('CONFIGURATION OF HOST PROTOTYPES'));
$widget->addItem(get_header_host_table('hosts', $discoveryRule['hostid'], $discoveryRule['itemid']));

$divTabs = new CTabView();
if (!isset($_REQUEST['form_refresh'])) {
	$divTabs->setSelected(0);
}

$frmHost = new CForm();
$frmHost->setName('hostPrototypeForm.');
$frmHost->addVar('form', getRequest('form', 1));
$frmHost->addVar('parent_discoveryid', $discoveryRule['itemid']);

$hostList = new CFormList('hostlist');

if ($hostPrototype['templateid'] && $data['parents']) {
	$parents = array();
	foreach (array_reverse($data['parents']) as $parent) {
		$parents[] = new CLink(
			$parent['parentHost']['name'],
			'?form=update&hostid='.$parent['hostid'].'&parent_discoveryid='.$parent['discoveryRule']['itemid'],
			'highlight underline weight_normal'
		);
		$parents[] = SPACE.'&rArr;'.SPACE;
	}
	array_pop($parents);
	$hostList->addRow(_('Parent discovery rules'), $parents);
}

if (isset($hostPrototype['hostid'])) {
	$frmHost->addVar('hostid', $hostPrototype['hostid']);
}

$hostTB = new CTextBox('host', $hostPrototype['host'], ZBX_TEXTBOX_STANDARD_SIZE, (bool) $hostPrototype['templateid']);
$hostTB->setAttribute('maxlength', 64);
$hostTB->setAttribute('autofocus', 'autofocus');
$hostList->addRow(_('Host name'), $hostTB);

$name = ($hostPrototype['name'] != $hostPrototype['host']) ? $hostPrototype['name'] : '';
$visiblenameTB = new CTextBox('name', $name, ZBX_TEXTBOX_STANDARD_SIZE, (bool) $hostPrototype['templateid']);
$visiblenameTB->setAttribute('maxlength', 64);
$hostList->addRow(_('Visible name'), $visiblenameTB);

// display inherited parameters only for hosts prototypes on hosts
if ($parentHost['status'] != HOST_STATUS_TEMPLATE) {
	$existingInterfaceTypes = array();

	foreach ($parentHost['interfaces'] as $interface) {
		$existingInterfaceTypes[$interface['type']] = true;
	}

	zbx_add_post_js('hostInterfacesManager.add('.CJs::encodeJson($parentHost['interfaces']).');');
	zbx_add_post_js('hostInterfacesManager.disable();');

	// table for agent interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'agentInterfaces');
	$ifTab->setAttribute('data-type', 'agent');

	// header
	$ifTab->addRow(array(
		new CCol(SPACE, 'interface-drag-control'),
		new CCol(_('IP address'), 'interface-ip'),
		new CCol(_('DNS name'), 'interface-dns'),
		new CCol(_('Connect to'), 'interface-connect-to'),
		new CCol(_('Port'), 'interface-port'),
		new CCol(_('Default'), 'interface-default'),
		new CCol(SPACE, 'interface-control')
	));

	$row = new CRow(null, null, 'agentIterfacesFooter');
	if (!isset($existingInterfaceTypes[INTERFACE_TYPE_AGENT])) {
		$row->addItem(new CCol(null, 'interface-drag-control'));
		$row->addItem(new CCol(_('No agent interfaces found.'), null, 5));
	}
	$ifTab->addRow($row);

	$hostList->addRow(_('Agent interfaces'), new CDiv($ifTab, 'border_dotted objectgroup interface-group'), false, null, 'interface-row interface-row-first');

	// table for SNMP interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'SNMPInterfaces');
	$ifTab->setAttribute('data-type', 'snmp');

	$row = new CRow(null, null, 'SNMPIterfacesFooter');
	if (!isset($existingInterfaceTypes[INTERFACE_TYPE_SNMP])) {
		$row->addItem(new CCol(null, 'interface-drag-control'));
		$row->addItem(new CCol(_('No SNMP interfaces found.'), null, 5));
	}
	$ifTab->addRow($row);
	$hostList->addRow(_('SNMP interfaces'), new CDiv($ifTab, 'border_dotted objectgroup interface-group'), false, null, 'interface-row');

	// table for JMX interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'JMXInterfaces');
	$ifTab->setAttribute('data-type', 'jmx');

	$row = new CRow(null, null, 'JMXIterfacesFooter');
	if (!isset($existingInterfaceTypes[INTERFACE_TYPE_JMX])) {
		$row->addItem(new CCol(null, 'interface-drag-control'));
		$row->addItem(new CCol(_('No JMX interfaces found.'), null, 5));
	}
	$ifTab->addRow($row);
	$hostList->addRow(_('JMX interfaces'), new CDiv($ifTab, 'border_dotted objectgroup interface-group'), false, null, 'interface-row');

	// table for IPMI interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'IPMIInterfaces');
	$ifTab->setAttribute('data-type', 'ipmi');

	$row = new CRow(null, null, 'IPMIIterfacesFooter');
	if (!isset($existingInterfaceTypes[INTERFACE_TYPE_IPMI])) {
		$row->addItem(new CCol(null, 'interface-drag-control'));
		$row->addItem(new CCol(_('No IPMI interfaces found.'), null, 5));
	}
	$ifTab->addRow($row);
	$hostList->addRow(_('IPMI interfaces'), new CDiv($ifTab, 'border_dotted objectgroup interface-group'), false, null, 'interface-row interface-row-last');

	// proxy
	if ($parentHost['proxy_hostid']) {
		$proxyTb = new CTextBox('proxy_hostid', $this->data['proxy']['host'], null, true);
	}
	else {
		$proxyTb = new CTextBox('proxy_hostid', _('(no proxy)'), null, true);
	}
	$hostList->addRow(_('Monitored by proxy'), $proxyTb);
}

$hostList->addRow(_('Enabled'), new CCheckBox('status', (HOST_STATUS_MONITORED == $hostPrototype['status']), null, HOST_STATUS_MONITORED));

$divTabs->addTab('hostTab', _('Host'), $hostList);

// groups
$groupList = new CFormList('grouplist');

// existing groups
$groups = array();
foreach ($data['groups'] as $group) {
	$groups[] = array(
		'id' => $group['groupid'],
		'name' => $group['name']
	);
}
$groupList->addRow(_('Groups'), new CMultiSelect(array(
	'name' => 'group_links[]',
	'objectName' => 'hostGroup',
	'objectOptions' => array(
		'editable' => true,
		'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
	),
	'data' => $groups,
	'disabled' => (bool) $hostPrototype['templateid'],
	'popup' => array(
		'parameters' => 'srctbl=host_groups&dstfrm='.$frmHost->getName().'&dstfld1=group_links_'.
			'&srcfld1=groupid&writeonly=1&multiselect=1&normal_only=1',
		'width' => 450,
		'height' => 450
	)
)));

// new group prototypes
$customGroupTable = new CTable(SPACE, 'formElementTable');
$customGroupTable->setAttribute('id', 'tbl_group_prototypes');

// buttons
$addButton = new CButton('group_prototype_add', _('Add'), null, 'link_menu');
$buttonColumn = new CCol($addButton);
$buttonColumn->setAttribute('colspan', 5);

$buttonRow = new CRow();
$buttonRow->setAttribute('id', 'row_new_group_prototype');
$buttonRow->addItem($buttonColumn);

$customGroupTable->addRow($buttonRow);
$groupDiv = new CDiv($customGroupTable, 'objectgroup border_dotted ui-corner-all group-prototypes');
$groupList->addRow(_('Group prototypes'), $groupDiv);

$divTabs->addTab('groupTab', _('Groups'), $groupList);

// templates
$tmplList = new CFormList('tmpllist');

// create linked template table
$linkedTemplateTable = new CTable(_('No templates linked.'), 'formElementTable');
$linkedTemplateTable->attr('id', 'linkedTemplateTable');
$linkedTemplateTable->attr('style', 'min-width: 400px;');
$linkedTemplateTable->setHeader(array(_('Name'), _('Action')));

$ignoreTemplates = array();
if ($hostPrototype['templates']) {
	foreach ($hostPrototype['templates'] as $template) {
		$tmplList->addVar('templates['.$template['templateid'].']', $template['templateid']);
		$templateLink = new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid']);
		$templateLink->setTarget('_blank');

		$linkedTemplateTable->addRow(array(
			$templateLink,
			!$hostPrototype['templateid'] ? new CSubmit('unlink['.$template['templateid'].']', _('Unlink'), null, 'link_menu') : '',
		));

		$ignoreTemplates[$template['templateid']] = $template['name'];
	}

	$tmplList->addRow(_('Linked templates'), new CDiv($linkedTemplateTable, 'objectgroup inlineblock border_dotted ui-corner-all'));
}
// for inherited prototypes with no templates display a text message
elseif ($hostPrototype['templateid']) {
	$tmplList->addRow(_('No templates linked.'));
}

// create new linked template table
if (!$hostPrototype['templateid']) {
	$newTemplateTable = new CTable(null, 'formElementTable');
	$newTemplateTable->attr('id', 'newTemplateTable');
	$newTemplateTable->attr('style', 'min-width: 400px;');

	$newTemplateTable->addRow(array(new CMultiSelect(array(
		'name' => 'add_templates[]',
		'objectName' => 'templates',
		'ignored' => $ignoreTemplates,
		'popup' => array(
			'parameters' => 'srctbl=templates&srcfld1=hostid&srcfld2=host&dstfrm='.$frmHost->getName().
				'&dstfld1=add_templates_&templated_hosts=1&multiselect=1',
			'width' => 450,
			'height' => 450
		)
	))));

	$newTemplateTable->addRow(array(new CSubmit('add_template', _('Add'), null, 'link_menu')));

	$tmplList->addRow(_('Link new templates'), new CDiv($newTemplateTable, 'objectgroup inlineblock border_dotted ui-corner-all'));
}

$divTabs->addTab('templateTab', _('Templates'), $tmplList);

// display inherited parameters only for hosts prototypes on hosts
if ($parentHost['status'] != HOST_STATUS_TEMPLATE) {
	// IPMI
	$ipmiList = new CFormList('ipmilist');

	$cmbIPMIAuthtype = new CTextBox('ipmi_authtype', ipmiAuthTypes($parentHost['ipmi_authtype']), ZBX_TEXTBOX_SMALL_SIZE, true);
	$ipmiList->addRow(_('Authentication algorithm'), $cmbIPMIAuthtype);

	$cmbIPMIPrivilege = new CTextBox('ipmi_privilege', ipmiPrivileges($parentHost['ipmi_privilege']), ZBX_TEXTBOX_SMALL_SIZE, true);
	$ipmiList->addRow(_('Privilege level'), $cmbIPMIPrivilege);

	$ipmiList->addRow(_('Username'), new CTextBox('ipmi_username', $parentHost['ipmi_username'], ZBX_TEXTBOX_SMALL_SIZE, true));
	$ipmiList->addRow(_('Password'), new CTextBox('ipmi_password', $parentHost['ipmi_password'], ZBX_TEXTBOX_SMALL_SIZE, true));
	$divTabs->addTab('ipmiTab', _('IPMI'), $ipmiList);

	// macros
	$macrosView = new CView('common.macros', array(
		'macros' => $parentHost['macros'],
		'readonly' => true
	));
	$divTabs->addTab('macroTab', _('Macros'), $macrosView->render());
}

$inventoryFormList = new CFormList('inventorylist');

// radio buttons for inventory type choice
$inventoryMode = (isset($hostPrototype['inventory']['inventory_mode'])) ? $hostPrototype['inventory']['inventory_mode'] : HOST_INVENTORY_DISABLED;
$inventoryDisabledBtn = new CRadioButton('inventory_mode', HOST_INVENTORY_DISABLED, null, 'host_inventory_radio_'.HOST_INVENTORY_DISABLED,
	$inventoryMode == HOST_INVENTORY_DISABLED
);
$inventoryDisabledBtn->setEnabled(!$hostPrototype['templateid']);

$inventoryManualBtn = new CRadioButton('inventory_mode', HOST_INVENTORY_MANUAL, null, 'host_inventory_radio_'.HOST_INVENTORY_MANUAL,
	$inventoryMode == HOST_INVENTORY_MANUAL
);
$inventoryManualBtn->setEnabled(!$hostPrototype['templateid']);

$inventoryAutomaticBtn = new CRadioButton('inventory_mode', HOST_INVENTORY_AUTOMATIC, null, 'host_inventory_radio_'.HOST_INVENTORY_AUTOMATIC,
	$inventoryMode == HOST_INVENTORY_AUTOMATIC
);
$inventoryAutomaticBtn->setEnabled(!$hostPrototype['templateid']);

$inventoryTypeRadioButton = array(
	$inventoryDisabledBtn,
	new CLabel(_('Disabled'), 'host_inventory_radio_'.HOST_INVENTORY_DISABLED),
	$inventoryManualBtn,
	new CLabel(_('Manual'), 'host_inventory_radio_'.HOST_INVENTORY_MANUAL),
	$inventoryAutomaticBtn,
	new CLabel(_('Automatic'), 'host_inventory_radio_'.HOST_INVENTORY_AUTOMATIC),
);
$inventoryFormList->addRow(new CDiv($inventoryTypeRadioButton, 'jqueryinputset'));

// clearing the float
$clearFixDiv = new CDiv();
$clearFixDiv->addStyle('clear: both;');
$inventoryFormList->addRow('', $clearFixDiv);

$divTabs->addTab('inventoryTab', _('Host inventory'), $inventoryFormList);

$frmHost->addItem($divTabs);

/*
 * footer
 */
if (isset($hostPrototype['hostid'])) {
	$btnDelete = new CButtonDelete(
		_('Delete selected host prototype?'),
		url_param('form').url_param('hostid').url_param('parent_discoveryid')
	);
	$btnDelete->setEnabled($hostPrototype['templateid'] == 0);

	$frmHost->addItem(makeFormFooter(
		new CSubmit('update', _('Update')),
		array (
			new CSubmit('clone', _('Clone')),
			$btnDelete,
			new CButtonCancel(url_param('parent_discoveryid'))
		)
	));
}
else {
	$frmHost->addItem(makeFormFooter(
		new CSubmit('add', _('Add')),
		new CButtonCancel(url_param('parent_discoveryid'))
	));
}

$widget->addItem($frmHost);

return $widget;
