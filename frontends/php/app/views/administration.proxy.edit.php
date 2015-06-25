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
$proxyForm->addVar('tls_accept', $data['tls_accept']);
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
	->addRow(_('Interface'), (new CDiv($interfaceTable))->addClass('objectgroup inlineblock border_dotted'))
	->addRow(_('Hosts'), $hostsTweenBox->get(_('Proxy hosts'), _('Other hosts')))
	->addRow(_('Description'),
		(new CTextArea('description', $data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// encryption tab
$encryptionFormList = new CFormList('encryption');

$encryptionFormList->addRow(_('Connections to proxy'), new CComboBox('tls_connect', $data['tls_connect'], null, [
	HOST_ENCRYPTION_NONE => _('No encryption'),
	HOST_ENCRYPTION_PSK => _('PSK'),
	HOST_ENCRYPTION_CERTIFICATE => _('Certificate')
]));
$encryptionFormList->addRow(_('Connections from proxy'), [
	[new CCheckBox('tls_in_none'), _('No encryption')],
	BR(),
	[new CCheckBox('tls_in_psk'), _('PSK')],
	BR(),
	[new CCheckBox('tls_in_cert'), _('Certificate')]
]);
$encryptionFormList->addRow(_('PSK identity'), new CTextBox('tls_psk_identity', $data['tls_psk_identity'], false,
	128
));
$encryptionFormList->addRow(_('PSK'), new CTextBox('tls_psk', $data['tls_psk'], false, 512));
$encryptionFormList->addRow(_('Issuer'), new CTextBox('tls_issuer', $data['tls_issuer'], false, 1024));
$encryptionFormList->addRow(_('Subject'), new CTextBox('tls_subject', $data['tls_subject'], false, 1024));

// append tabs to form
$proxyTab = (new CTabView())->addTab('proxyTab', _('Proxy'), $proxyFormList);
$proxyTab = (new CTabView())->addTab('proxyTab', _('Encryption'), $encryptionFormList);

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
