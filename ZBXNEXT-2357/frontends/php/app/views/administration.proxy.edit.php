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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

$this->includeJSfile('app/views/administration.proxy.edit.js.php');

$proxyWidget = new CWidget();
$proxyWidget->addPageHeader(_('CONFIGURATION OF PROXIES'));

// create form
$proxyForm = new CForm();
$proxyForm->setName('proxyForm');
$proxyForm->addVar('form', 1);
$proxyForm->addVar('proxyid', $data['proxyid']);

// create form list
$proxyFormList = new CFormList('proxyFormList');
$nameTextBox = new CTextBox('host', $data['name'], ZBX_TEXTBOX_STANDARD_SIZE, false, 128);
$nameTextBox->attr('autofocus', 'autofocus');
$proxyFormList->addRow(_('Proxy name'), $nameTextBox);

// append status to form list
$statusBox = new CComboBox('status', $data['status']);
$statusBox->addItem(HOST_STATUS_PROXY_ACTIVE, _('Active'));
$statusBox->addItem(HOST_STATUS_PROXY_PASSIVE, _('Passive'));
$proxyFormList->addRow(_('Proxy mode'), $statusBox);

if ($data['status'] == HOST_STATUS_PROXY_PASSIVE) {
	$proxyForm->addVar('interface[interfaceid]', $data['interface']['interfaceid']);
//	$proxyForm->addVar('interface[hostid]', $data['interface']['hostid']);
}

$interfaceTable = new CTable(null, 'formElementTable');
$interfaceTable->addRow(array(
	_('IP address'),
	_('DNS name'),
	_('Connect to'),
	_('Port')
));

$connectByComboBox = new CRadioButtonList('interface[useip]', $data['status'] == HOST_STATUS_PROXY_PASSIVE ? $data['interface']['useip'] : 1);
$connectByComboBox->addValue(_('IP'), 1);
$connectByComboBox->addValue(_('DNS'), 0);
$connectByComboBox->useJQueryStyle();

$interfaceTable->addRow(array(
	new CTextBox('interface[ip]', $data['interface']['ip'], ZBX_TEXTBOX_SMALL_SIZE, false, 64),
	new CTextBox('interface[dns]', $data['interface']['dns'], ZBX_TEXTBOX_SMALL_SIZE, false, 64),
	$connectByComboBox,
	new CTextBox('interface[port]', $data['interface']['port'], 18, false, 64)
));
$proxyFormList->addRow(_('Interface'), new CDiv($interfaceTable, 'objectgroup inlineblock border_dotted ui-corner-all'),
	$data['status'] == HOST_STATUS_PROXY_ACTIVE);

// append hosts to form list
$hostsTweenBox = new CTweenBox($proxyForm, 'proxy_hostids', $data['proxy_hostids']);
foreach ($data['all_hosts'] as $host) {
	// show only normal hosts, and discovered hosts monitored by the current proxy
	// for new proxies display only normal hosts
	if (($data['proxyid'] != 0 && bccomp($data['proxyid'], $host['proxy_hostid']) == 0) || $host['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		$hostsTweenBox->addItem(
			$host['hostid'],
			$host['name'],
			null,
			empty($host['proxy_hostid']) || (isset($data['proxyid']) && bccomp($host['proxy_hostid'], $data['proxyid']) == 0 && $host['flags'] == ZBX_FLAG_DISCOVERY_NORMAL)
		);
	}
}
$proxyFormList->addRow(_('Hosts'), $hostsTweenBox->get(_('Proxy hosts'), _('Other hosts')));
$proxyFormList->addRow(_('Description'), new CTextArea('description', $data['description']));

// append tabs to form
$proxyTab = new CTabView();
$proxyTab->addTab('proxyTab', _('Proxy'), $proxyFormList);
$proxyForm->addItem($proxyTab);

// append buttons to form
$cancelButton = new CRedirectButton(_('Cancel'), 'proxies.php?action=proxy.list');
$cancelButton->setAttribute('id', 'cancel');

if ($data['proxyid'] == 0) {
	$addButton = new CSubmitButton(_('Add'), 'action', 'proxy.create');
	$addButton->setAttribute('id', 'add');

	$proxyForm->addItem(makeFormFooter(
		$addButton,
		array($cancelButton)
	));
}
else {
	$updateButton = new CSubmitButton(_('Update'), 'action', 'proxy.update');
	$updateButton->setAttribute('id', 'update');
	$cloneButton = new CSimpleButton(_('Clone'));
	$cloneButton->setAttribute('id', 'clone');
	$deleteButton = new CRedirectButton(_('Delete'), 'proxies.php?action=proxy.delete'.url_param('proxyid'),_('Delete proxy?'));
	$deleteButton->setAttribute('id', 'delete');

	$proxyForm->addItem(makeFormFooter(
		$updateButton,
		array(
			$cloneButton,
			$deleteButton,
			$cancelButton
		)
	));
}

// append form to widget
$proxyWidget->addItem($proxyForm);

$proxyWidget->show();
