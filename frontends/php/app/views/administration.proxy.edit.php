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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


$this->includeJSfile('app/views/administration.proxy.edit.js.php');

$proxyWidget = (new CWidget())->setTitle(_('Proxies'));

// create form
$proxyForm = new CForm();
$proxyForm->setAttribute('id', 'proxyForm');
$proxyForm->addVar('proxyid', $data['proxyid']);
if ($data['proxyid'] != 0 && $data['status'] == HOST_STATUS_PROXY_PASSIVE) {
	$proxyForm->addVar('interfaceid', $data['interfaceid']);
}

// create form list
$proxyFormList = new CFormList('proxyFormList');
$nameTextBox = new CTextBox('host', $data['host'], ZBX_TEXTBOX_STANDARD_SIZE, false, 128);
$nameTextBox->setAttribute('autofocus', 'autofocus');
$proxyFormList->addRow(_('Proxy name'), $nameTextBox);

// append status to form list
$proxyFormList->addRow(_('Proxy mode'), new CComboBox('status', $data['status'], null, [
	HOST_STATUS_PROXY_ACTIVE => _('Active'),
	HOST_STATUS_PROXY_PASSIVE => _('Passive')
]));

$interfaceTable = new CTable(null, 'formElementTable');
$interfaceTable->addRow([
	_('IP address'),
	_('DNS name'),
	_('Connect to'),
	_('Port')
]);

$connectByComboBox = new CRadioButtonList('useip', $data['useip']);
$connectByComboBox->addValue(_('IP'), 1);
$connectByComboBox->addValue(_('DNS'), 0);
$connectByComboBox->useJQueryStyle();

$interfaceTable->addRow([
	new CTextBox('ip', $data['ip'], ZBX_TEXTBOX_SMALL_SIZE, false, 64),
	new CTextBox('dns', $data['dns'], ZBX_TEXTBOX_SMALL_SIZE, false, 64),
	$connectByComboBox,
	new CTextBox('port', $data['port'], 18, false, 64)
]);
$proxyFormList->addRow(_('Interface'), new CDiv($interfaceTable, 'objectgroup inlineblock border_dotted ui-corner-all'),
	$data['status'] != HOST_STATUS_PROXY_PASSIVE);

// append hosts to form list
$hostsTweenBox = new CTweenBox($proxyForm, 'proxy_hostids', $data['proxy_hostids']);
foreach ($data['all_hosts'] as $host) {
	// show only normal hosts, and discovered hosts monitored by the current proxy
	// for new proxies display only normal hosts
	if ($host['flags'] == ZBX_FLAG_DISCOVERY_NORMAL
			|| ($data['proxyid'] != 0 && bccomp($data['proxyid'], $host['proxy_hostid']) == 0)) {

		$hostsTweenBox->addItem(
			$host['hostid'],
			$host['name'],
			null,
			$host['proxy_hostid'] == 0
				|| (bccomp($host['proxy_hostid'], $data['proxyid']) == 0 && $host['flags'] == ZBX_FLAG_DISCOVERY_NORMAL)
		);
	}
}
$proxyFormList->addRow(_('Hosts'), $hostsTweenBox->get(_('Proxy hosts'), _('Other hosts')));
$proxyFormList->addRow(_('Description'), new CTextArea('description', $data['description']));

// append tabs to form
$proxyTab = new CTabView();
$proxyTab->addTab('proxyTab', _('Proxy'), $proxyFormList);

// append buttons to form
$cancelButton = new CRedirectButton(_('Cancel'), 'zabbix.php?action=proxy.list');
$cancelButton->setAttribute('id', 'cancel');

if ($data['proxyid'] == 0) {
	$addButton = new CSubmitButton(_('Add'), 'action', 'proxy.create');
	$addButton->setAttribute('id', 'add');

	$proxyTab->setFooter(makeFormFooter(
		$addButton,
		[$cancelButton]
	));
}
else {
	$updateButton = new CSubmitButton(_('Update'), 'action', 'proxy.update');
	$updateButton->setAttribute('id', 'update');
	$cloneButton = new CSimpleButton(_('Clone'));
	$cloneButton->setAttribute('id', 'clone');
	$deleteButton = new CRedirectButton(_('Delete'),
		'zabbix.php?action=proxy.delete&sid='.$data['sid'].'&proxyids[]='.$data['proxyid'],
		_('Delete proxy?')
	);
	$deleteButton->setAttribute('id', 'delete');

	$proxyTab->setFooter(makeFormFooter(
		$updateButton,
		[
			$cloneButton,
			$deleteButton,
			$cancelButton
		]
	));
}

$proxyForm->addItem($proxyTab);
$proxyWidget->addItem($proxyForm)->show();
