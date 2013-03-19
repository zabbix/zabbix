<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

$widget = new CWidget(null, 'hostprototype-edit');
$widget->addPageHeader(_('CONFIGURATION OF HOST PROTOTYPES'));
$widget->addItem(get_header_host_table('hosts', $discoveryRule['hostid'], $discoveryRule['itemid']));

$divTabs = new CTabView(array('remember' => 1));
if (!isset($_REQUEST['form_refresh'])) {
	$divTabs->setSelected(0);
}

$frmHost = new CForm();
$frmHost->setName('hostPrototypeForm.');
$frmHost->addVar('form', get_request('form', 1));
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
		$parents[] = SPACE.RARR.SPACE;
	}
	array_pop($parents);
	$hostList->addRow(_('Parent discovery rules'), $parents);
}

if ($hostPrototype['hostid']) {
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
	// host groups
	$groupBox = new CComboBox('groups');
	$groupBox->setAttribute('readonly', true);
	$groupBox->setAttribute('size', 10);
	foreach ($parentHost['groups'] as $group) {
		$groupBox->addItem($group['groupid'], $group['name']);
	}
	$hostList->addRow(_('Groups'), $groupBox);

	$interfaces = array();
	$existingInterfaceTypes = array();
	foreach ($parentHost['interfaces'] as $interface) {
		$interface['locked'] = true;
		$existingInterfaceTypes[$interface['type']] = true;
		$interfaces[$interface['interfaceid']] = $interface;
	}
	zbx_add_post_js('hostInterfacesManager.add('.CJs::encodeJson($interfaces).');');
	zbx_add_post_js('hostInterfacesManager.disable()');

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
		$row->addItem(new CCol(_('No agent interfaces defined.'), null, 5));
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
		$row->addItem(new CCol(_('No SNMP interfaces defined.'), null, 5));
	}
	$ifTab->addRow($row);
	$hostList->addRow(_('SNMP interfaces'), new CDiv($ifTab, 'border_dotted objectgroup'), false, null, 'interface-row');

	// table for JMX interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'JMXInterfaces');
	$ifTab->setAttribute('data-type', 'jmx');

	$row = new CRow(null, null, 'JMXIterfacesFooter');
	if (!isset($existingInterfaceTypes[INTERFACE_TYPE_JMX])) {
		$row->addItem(new CCol(null, 'interface-drag-control'));
		$row->addItem(new CCol(_('No JMX interfaces defined.'), null, 5));
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
		$row->addItem(new CCol(_('No IPMI interfaces defined.'), null, 5));
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

$cmbStatus = new CComboBox('status', $hostPrototype['status']);
$cmbStatus->addItem(HOST_STATUS_MONITORED, _('Monitored'));
$cmbStatus->addItem(HOST_STATUS_NOT_MONITORED, _('Not monitored'));

$hostList->addRow(_('Status'), $cmbStatus);

$divTabs->addTab('hostTab', _('Host'), $hostList);

// templates
$tmplList = new CFormList('tmpllist');

if ($hostPrototype['templates']) {
	foreach ($hostPrototype['templates'] as $templateId => $name) {
		$frmHost->addVar('templates['.$templateId.']', $name);
		$tmplList->addRow(
			$name,
			(!$hostPrototype['templateid']) ? new CSubmit('unlink['.$templateId.']', _('Unlink and clear'), null, 'link_menu') : ''
		);
	}
}
// for inherited prototypes with no templates display a text message
elseif ($hostPrototype['templateid']) {
	$tmplList->addRow(_('No templates linked.'), ' ');
}

if (!$hostPrototype['templateid']) {
	$tmplAdd = new CButton('add', _('Add'),
		'return PopUp("popup.php?srctbl=templates&srcfld1=hostid&srcfld2=host'.
			'&dstfrm='.$frmHost->getName().'&dstfld1=new_template&templated_hosts=1'.
			url_param($hostPrototype['templates'], false, 'existed_templates').'", 450, 450)',
		'link_menu'
	);
	$tmplList->addRow($tmplAdd, SPACE);
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
}

$frmHost->addItem($divTabs);

/*
 * footer
 */
$main = array(new CSubmit('save', _('Save')));
$others = array();
if ($hostPrototype['hostid']) {
	$btnDelete = new CButtonDelete(_('Delete selected host prototype?'), url_param('form').url_param('hostid').url_param('parent_discoveryid'));
	$btnDelete->setEnabled(!$hostPrototype['templateid']);

	$others[] = new CSubmit('clone', _('Clone'));
	$others[] = $btnDelete;
}
$others[] = new CButtonCancel(url_param('parent_discoveryid'));

$frmHost->addItem(makeFormFooter($main, $others));

$widget->addItem($frmHost);

return $widget;
