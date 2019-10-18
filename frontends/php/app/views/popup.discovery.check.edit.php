<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

$discovery_ckeck_types = discovery_check_type2str();
order_result($discovery_ckeck_types);

$form_list = (new CFormList())
	->addRow(
		(new CLabel(_('Check type'), 'type')),
		(new CComboBox('type', $data['type'], null, $discovery_ckeck_types))
	)
	->addRow(
		(new CLabel(_('Port range'), 'ports'))->setAsteriskMark(),
		(new CTextBox('ports', $data['ports']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired(),
		'row_dcheck_ports'
	)
	->addRow(
		(new CLabel(_('Key'), 'key_'))->setAsteriskMark(),
		(new CTextBox('key_', $data['key_']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAriaRequired(),
		'row_dcheck_key'
	)
	->addRow(
		(new CLabel(_('SNMP community'), 'snmp_community'))->setAsteriskMark(),
		(new CTextBox('snmp_community', $data['snmp_community']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAriaRequired(),
		'row_dcheck_snmp_community'
	)
	->addRow(
		(new CLabel(_('SNMP OID'), 'snmp_oid'))->setAsteriskMark(),
		(new CTextBox('snmp_oid', $data['snmp_oid']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAriaRequired()
			->setAttribute('maxlength', 512),
		'row_dcheck_snmp_oid'
	)
	->addRow(
		(new CLabel(_('Context name'), 'snmpv3_contextname')),
		(new CTextBox('snmpv3_contextname', $data['snmpv3_contextname']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
		'row_dcheck_snmpv3_contextname'
	)
	->addRow(
		(new CLabel(_('Security name'), 'snmpv3_securityname')),
		(new CTextBox('snmpv3_securityname', $data['snmpv3_securityname']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAttribute('maxlength', 64),
		'row_dcheck_snmpv3_securityname'
	)
	->addRow(
		new CLabel(_('Security level'), 'snmpv3_securitylevel'),
		new CComboBox('snmpv3_securitylevel', $data['snmpv3_securitylevel'], null, [
			ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
			ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
			ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
		]),
		'row_dcheck_snmpv3_securitylevel'
	)
	->addRow(
		(new CLabel(_('Authentication protocol'), 'snmpv3_authprotocol')),
		(new CRadioButtonList('snmpv3_authprotocol', $data['snmpv3_authprotocol']))
			->addValue(_('MD5'), ITEM_AUTHPROTOCOL_MD5, 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_MD5)
			->addValue(_('SHA'), ITEM_AUTHPROTOCOL_SHA, 'snmpv3_authprotocol_'.ITEM_AUTHPROTOCOL_SHA)
			->setModern(true),
		'row_dcheck_snmpv3_authprotocol'
	)
	->addRow(
		(new CLabel(_('Authentication passphrase'), 'snmpv3_authpassphrase')),
		(new CTextBox('snmpv3_authpassphrase', $data['snmpv3_authpassphrase']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAttribute('maxlength', 64),
		'row_dcheck_snmpv3_authpassphrase'
	)
	->addRow(
		(new CLabel(_('Privacy protocol'), 'snmpv3_privprotocol')),
		(new CRadioButtonList('snmpv3_privprotocol', $options['snmpv3_privprotocol']))
			->addValue(_('DES'), ITEM_PRIVPROTOCOL_DES, 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_DES)
			->addValue(_('AES'), ITEM_PRIVPROTOCOL_AES, 'snmpv3_privprotocol_'.ITEM_PRIVPROTOCOL_AES)
			->setModern(true),
		'row_dcheck_snmpv3_privprotocol'
	)
	->addRow(
		(new CLabel(_('Privacy passphrase'), 'snmpv3_privpassphrase'))->setAsteriskMark(),
		(new CTextBox('snmpv3_privpassphrase', $data['snmpv3_privpassphrase']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAriaRequired()
			->setAttribute('maxlength', 64),
		'row_dcheck_snmpv3_privpassphrase'
	);

$form = (new CForm())
	->cleanItems()
	->setName('dcheck_form')
	->addVar('action', 'popup.discovery.check.edit')
	->addVar('refresh', 1)
	->addVar('update', $data['update'])
	->addVar('index', $data['index'])
	->addItem($form_list);

if ($data['dcheckid']) {
	$form->addVar('dcheckid', $data['dcheckid']);
}

$output = [
	'header' => $data['title'],
	'params' => $data['params'],
	'script_inline' => require 'app/views/popup.discovery.check.edit.js.php',
	'body' => (new CDiv([$data['errors'], $form]))->toString(),
	'buttons' => [
		[
			'title' => $data['update'] ? _('Update') : _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'submitDCheck("'.$form->getName().'");'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
