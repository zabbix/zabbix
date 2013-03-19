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

$host = $data['host'];

require_once dirname(__FILE__).'/js/configuration.host.edit.js.php';

$divTabs = new CTabView(array('remember' => 1));
if (!isset($_REQUEST['form_refresh'])) {
	$divTabs->setSelected(0);
}

$frmHost = new CForm();
$frmHost->setName('web.hosts.host.php.');
$frmHost->addVar('form', get_request('form', 1));
$frmHost->addVar('hostid', $host['hostid']);

$hostList = new CFormList('hostlist');

$hostList->addRow(
	_('Discovered by'),
	new CLink($host['discoveryRule']['name'],
		'host_prototypes.php?parent_discoveryid='.$host['discoveryRule']['itemid'],
		'highlight underline weight_normal'
	)
);

$hostTB = new CTextBox('host', $host['host'], ZBX_TEXTBOX_STANDARD_SIZE, true);
$hostTB->setAttribute('maxlength', 64);
$hostTB->setAttribute('autofocus', 'autofocus');
$hostList->addRow(_('Host name'), $hostTB);

$name = ($host['name'] != $host['host']) ? $host['name'] : '';
$visiblenameTB = new CTextBox('visiblename', $name, ZBX_TEXTBOX_STANDARD_SIZE, true);
$visiblenameTB->setAttribute('maxlength', 64);
$hostList->addRow(_('Visible name'), $visiblenameTB);

// host groups
$groupBox = new CComboBox('groups');
$groupBox->setAttribute('readonly', true);
$groupBox->setAttribute('size', 10);
foreach ($host['groups'] as $group) {
	$groupBox->addItem($group['groupid'], $group['name']);
}
$hostList->addRow(_('Groups'), $groupBox);

$interfaces = array();
$existingInterfaceTypes = array();
foreach ($host['interfaces'] as $interface) {
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
if ($host['proxy_hostid']) {
	$proxyTb = new CTextBox('proxy_host', $this->data['proxy']['host'], null, true);
}
else {
	$proxyTb = new CTextBox('proxy_host', _('(no proxy)'), null, true);
}
$hostList->addRow(_('Monitored by proxy'), $proxyTb);

$cmbStatus = new CComboBox('status', $host['status']);
$cmbStatus->addItem(HOST_STATUS_MONITORED, _('Monitored'));
$cmbStatus->addItem(HOST_STATUS_NOT_MONITORED, _('Not monitored'));

$hostList->addRow(_('Status'), $cmbStatus);

$divTabs->addTab('hostTab', _('Host'), $hostList);

// templates
$tmplList = new CFormList('tmpllist');

if ($data['templates']) {
	foreach ($data['templates'] as $templateId => $name) {
		$row = array($name);
		$tmplList->addRow($name, '');
	}
}
else {
	$tmplList->addRow(_('No templates linked.'), ' ');
}

$divTabs->addTab('templateTab', _('Templates'), $tmplList);

// IPMI
$ipmiList = new CFormList('ipmilist');

$cmbIPMIAuthtype = new CTextBox('ipmi_authtype_name', ipmiAuthTypes($host['ipmi_authtype']), ZBX_TEXTBOX_SMALL_SIZE, true);
$ipmiList->addRow(_('Authentication algorithm'), $cmbIPMIAuthtype);

$cmbIPMIPrivilege = new CTextBox('ipmi_privilege_name', ipmiPrivileges($host['ipmi_privilege']), ZBX_TEXTBOX_SMALL_SIZE, true);
$ipmiList->addRow(_('Privilege level'), $cmbIPMIPrivilege);

$ipmiList->addRow(_('Username'), new CTextBox('ipmi_username', $host['ipmi_username'], ZBX_TEXTBOX_SMALL_SIZE, true));
$ipmiList->addRow(_('Password'), new CTextBox('ipmi_password', $host['ipmi_password'], ZBX_TEXTBOX_SMALL_SIZE, true));
$divTabs->addTab('ipmiTab', _('IPMI'), $ipmiList);

$frmHost->addItem($divTabs);

/*
 * footer
 */
$main = array(new CSubmit('save', _('Save')));
$others[] = new CSubmit('clone', _('Clone'));
$others[] = new CSubmit('full_clone', _('Full clone'));
$others[] = new CButtonDelete(_('Delete selected host?'), url_param('form').url_param('hostid').url_param('groupid'));
$others[] = new CButtonCancel(url_param('groupid'));

$frmHost->addItem(makeFormFooter($main, $others));

return $frmHost;
