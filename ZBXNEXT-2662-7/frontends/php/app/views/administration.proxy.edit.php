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

$widget = (new CWidget())->setTitle(_('Proxies'));

$proxyForm = (new CForm())
	->setId('proxyForm')
	->addVar('proxyid', $data['proxyid']);
if ($data['proxyid'] != 0 && $data['status'] == HOST_STATUS_PROXY_PASSIVE) {
	$proxyForm->addVar('interfaceid', $data['interfaceid']);
}

$interfaceTable = (new CTable())
	->addClass('formElementTable')
	->setHeader([
		_('IP address'),
		_('DNS name'),
		_('Connect to'),
		_('Port')
	])
	->addRow([
		(new CTextBox('ip', $data['ip'], false, 64))->setWidth(ZBX_TEXTAREA_INTERFACE_IP_WIDTH),
		(new CTextBox('dns', $data['dns'], false, 64))->setWidth(ZBX_TEXTAREA_INTERFACE_DNS_WIDTH),
		(new CRadioButtonList('useip', $data['useip']))
			->addValue(_('IP'), 1)
			->addValue(_('DNS'), 0),
		(new CTextBox('port', $data['port'], false, 64))->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
	]);

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

$proxyFormList = (new CFormList('proxyFormList'))
	->addRow(_('Proxy name'),
		(new CTextBox('host', $data['host'], false, 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Proxy mode'), new CComboBox('status', $data['status'], null, [
		HOST_STATUS_PROXY_ACTIVE => _('Active'),
		HOST_STATUS_PROXY_PASSIVE => _('Passive')
	]))
	->addRow(_('Interface'), (new CDiv($interfaceTable))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR))
	->addRow(_('Hosts'), $hostsTweenBox->get(_('Proxy hosts'), _('Other hosts')))
	->addRow(_('Description'),
		(new CTextArea('description', $data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// append tabs to form
$proxyTab = (new CTabView())->addTab('proxyTab', _('Proxy'), $proxyFormList);

// append buttons to form
$cancelButton = new CRedirectButton(_('Cancel'), 'zabbix.php?action=proxy.list');

if ($data['proxyid'] == 0) {
	$proxyTab->setFooter(makeFormFooter(
		new CSubmitButton(_('Add'), 'action', 'proxy.create'),
		[$cancelButton]
	));
}
else {
	$proxyTab->setFooter(makeFormFooter(
		new CSubmitButton(_('Update'), 'action', 'proxy.update'),
		[
			(new CSimpleButton(_('Clone')))->setId('clone'),
			new CRedirectButton(_('Delete'),
				'zabbix.php?action=proxy.delete&sid='.$data['sid'].'&proxyids[]='.$data['proxyid'],
				_('Delete proxy?')
			),
			$cancelButton
		]
	));
}

$proxyForm->addItem($proxyTab);
$widget->addItem($proxyForm)->show();
