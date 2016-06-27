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


$this->includeJSfile('app/views/administration.proxy.edit.js.php');

$widget = (new CWidget())->setTitle(_('Proxies'));

$tabs = new CTabView();

if ($data['form_refresh'] == 0) {
	$tabs->setSelected(0);
}

$proxyForm = (new CForm())
	->setId('proxyForm')
	->addVar('proxyid', $data['proxyid'])
	->addVar('tls_accept', $data['tls_accept']);

if ($data['status'] == HOST_STATUS_PROXY_PASSIVE && array_key_exists('interfaceid', $data)) {
	$proxyForm->addVar('interfaceid', $data['interfaceid']);
}

$interfaceTable = (new CTable())
	->setHeader([
		_('IP address'),
		_('DNS name'),
		_('Connect to'),
		_('Port')
	])
	->addRow([
		(new CTextBox('ip', $data['ip'], false, 64))->setWidth(ZBX_TEXTAREA_INTERFACE_IP_WIDTH),
		(new CTextBox('dns', $data['dns'], false, 64))->setWidth(ZBX_TEXTAREA_INTERFACE_DNS_WIDTH),
		(new CRadioButtonList('useip', (int) $data['useip']))
			->addValue(_('IP'), INTERFACE_USE_IP)
			->addValue(_('DNS'), INTERFACE_USE_DNS)
			->setModern(true),
		(new CTextBox('port', $data['port'], false, 64))->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
	]);

// append hosts to form list
$hosts_tween_box = new CTweenBox($proxyForm, 'proxy_hostids', $data['proxy_hostids']);
foreach ($data['all_hosts'] as $host) {
	// show only normal hosts, and discovered hosts monitored by the current proxy
	// for new proxies display only normal hosts
	if ($host['flags'] == ZBX_FLAG_DISCOVERY_NORMAL
			|| ($data['proxyid'] != 0 && bccomp($data['proxyid'], $host['proxy_hostid']) == 0)) {

		$hosts_tween_box->addItem(
			$host['hostid'],
			$host['name'],
			null,
			$host['proxy_hostid'] == 0
				|| (bccomp($host['proxy_hostid'], $data['proxyid']) == 0 && $host['flags'] == ZBX_FLAG_DISCOVERY_NORMAL)
		);
	}
}

$proxy_form_list = (new CFormList('proxyFormList'))
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
	->addRow(_('Hosts'), $hosts_tween_box->get(_('Proxy hosts'), _('Other hosts')))
	->addRow(_('Description'),
		(new CTextArea('description', $data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// append tabs to form
$proxyTab = (new CTabView())->addTab('proxyTab', _('Proxy'), $proxy_form_list);

// Encryption form list.
$encryption_form_list = (new CFormList('encryption'))
	->addRow(_('Connections to proxy'),
		(new CRadioButtonList('tls_connect', (int) $data['tls_connect']))
			->addValue(_('No encryption'), HOST_ENCRYPTION_NONE)
			->addValue(_('PSK'), HOST_ENCRYPTION_PSK)
			->addValue(_('Certificate'), HOST_ENCRYPTION_CERTIFICATE)
			->setModern(true)
	)
	->addRow(_('Connections from proxy'), [
		new CLabel([new CCheckBox('tls_in_none'), _('No encryption')]),
		BR(),
		new CLabel([new CCheckBox('tls_in_psk'), _('PSK')]),
		BR(),
		new CLabel([new CCheckBox('tls_in_cert'), _('Certificate')])
	])
	->addRow(_('PSK identity'),
		(new CTextBox('tls_psk_identity', $data['tls_psk_identity'], false, 128))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
	)
	->addRow(_('PSK'),
		(new CTextBox('tls_psk', $data['tls_psk'], false, 512))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
	)
	->addRow(_('Issuer'),
		(new CTextBox('tls_issuer', $data['tls_issuer'], false, 1024))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
	)
	->addRow(_x('Subject', 'encryption certificate'),
		(new CTextBox('tls_subject', $data['tls_subject'], false, 1024))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
	);

$tabs->addTab('proxyTab', _('Proxy'), $proxy_form_list);
$tabs->addTab('encryptionTab', _('Encryption'), $encryption_form_list);

// append buttons to form
$cancelButton = new CRedirectButton(_('Cancel'), 'zabbix.php?action=proxy.list');

if ($data['proxyid'] == 0) {
	$tabs->setFooter(makeFormFooter(
		new CSubmitButton(_('Add'), 'action', 'proxy.create'),
		[$cancelButton]
	));
}
else {
	$tabs->setFooter(makeFormFooter(
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

$proxyForm->addItem($tabs);
$widget->addItem($proxyForm)->show();
