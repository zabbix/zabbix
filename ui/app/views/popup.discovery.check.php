<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $this
 */

$discovery_ckeck_types = discovery_check_type2str();
order_result($discovery_ckeck_types);

$form = (new CForm())
	->cleanItems()
	->setName('dcheck_form')
	->addVar('action', 'popup.discovery.check')
	->addVar('validate', 1);

if (array_key_exists('dcheckid', $data['params']) && $data['params']['dcheckid']) {
	$form->addVar('dcheckid', $data['params']['dcheckid']);
}

$select_type = (new CSelect('type'))
	->setId('type-select')
	->setValue($data['params']['type'])
	->setFocusableElementId('type')
	->addOptions(CSelect::createOptionsFromArray($discovery_ckeck_types));

$select_snmpv3_securitylevel = (new CSelect('snmpv3_securitylevel'))
	->setId('snmpv3-securitylevel')
	->setValue($data['params']['snmpv3_securitylevel'])
	->setFocusableElementId('snmpv3-securitylevel-button')
	->addOption(new CSelectOption(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, 'noAuthNoPriv'))
	->addOption(new CSelectOption(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'authNoPriv'))
	->addOption(new CSelectOption(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'authPriv'));

$form_list = (new CFormList())
	->cleanItems()
	->addRow(new CLabel(_('Check type'), $select_type->getFocusableElementId()), $select_type)
	->addRow((new CLabel(_('Port range'), 'ports'))->setAsteriskMark(),
		(new CTextBox('ports', $data['params']['ports']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired(),
		'row_dcheck_ports'
	)
	->addRow((new CLabel(_('Key'), 'key_'))->setAsteriskMark(),
		(new CTextBox('key_', $data['params']['key_'], false, DB::getFieldLength('items', 'key_')))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAriaRequired(),
		'row_dcheck_key'
	)
	->addRow((new CLabel(_('SNMP community'), 'snmp_community'))->setAsteriskMark(),
		(new CTextBox('snmp_community', $data['params']['snmp_community']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAriaRequired(),
		'row_dcheck_snmp_community'
	)
	->addRow((new CLabel(_('SNMP OID'), 'snmp_oid'))->setAsteriskMark(),
		(new CTextBox('snmp_oid', $data['params']['key_']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAriaRequired()
			->setAttribute('maxlength', 512),
		'row_dcheck_snmp_oid'
	)
	->addRow(new CLabel(_('Context name'), 'snmpv3_contextname'),
		(new CTextBox('snmpv3_contextname', $data['params']['snmpv3_contextname']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
		'row_dcheck_snmpv3_contextname'
	)
	->addRow(new CLabel(_('Security name'), 'snmpv3_securityname'),
		(new CTextBox('snmpv3_securityname', $data['params']['snmpv3_securityname']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAttribute('maxlength', 64),
		'row_dcheck_snmpv3_securityname'
	)
	->addRow(new CLabel(_('Security level'), $select_snmpv3_securitylevel->getFocusableElementId()),
		$select_snmpv3_securitylevel,
		'row_dcheck_snmpv3_securitylevel'
	)
	->addRow(new CLabel(_('Authentication protocol'), 'label-authprotocol'),
		(new CSelect('snmpv3_authprotocol'))
			->setValue((int) $data['params']['snmpv3_authprotocol'])
			->setFocusableElementId('label-authprotocol')
			->addOptions(CSelect::createOptionsFromArray(getSnmpV3AuthProtocols())),
		'row_dcheck_snmpv3_authprotocol'
	)
	->addRow(new CLabel(_('Authentication passphrase'), 'snmpv3_authpassphrase'),
		(new CTextBox('snmpv3_authpassphrase', $data['params']['snmpv3_authpassphrase']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAttribute('maxlength', 64)
			->disableAutocomplete(),
		'row_dcheck_snmpv3_authpassphrase'
	)
	->addRow(new CLabel(_('Privacy protocol'), 'label-privprotocol'),
		(new CSelect('snmpv3_privprotocol'))
			->setValue((int) $data['params']['snmpv3_privprotocol'])
			->setFocusableElementId('label-privprotocol')
			->addOptions(CSelect::createOptionsFromArray(getSnmpV3PrivProtocols())),
		'row_dcheck_snmpv3_privprotocol'
	)
	->addRow((new CLabel(_('Privacy passphrase'), 'snmpv3_privpassphrase'))->setAsteriskMark(),
		(new CTextBox('snmpv3_privpassphrase', $data['params']['snmpv3_privpassphrase']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAriaRequired()
			->setAttribute('maxlength', 64)
			->disableAutocomplete(),
		'row_dcheck_snmpv3_privpassphrase'
	);

$form->addItem([
	$form_list,
	(new CInput('submit', 'submit'))->addStyle('display: none;')
]);

$output = [
	'header' => $data['title'],
	'script_inline' => $this->readJsFile('popup.discovery.check.js.php'),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['update'] ? _('Update') : _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return submitDCheck(overlay);'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
