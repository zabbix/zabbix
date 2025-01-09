<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */

$discovery_check_types = discovery_check_type2str();
order_result($discovery_check_types);

$inline_js = getPagePostJs().$this->readJsFile('discovery.check.edit.js.php');

$form = (new CForm())->addStyle('display: none;');

if (array_key_exists('dcheckid', $data['params']) && $data['params']['dcheckid']) {
	$form->addVar('dcheckid', $data['params']['dcheckid']);
}

$select_type = (new CSelect('type'))
	->setId('type-select')
	->setValue($data['params']['type'])
	->setFocusableElementId('type')
	->addOptions(CSelect::createOptionsFromArray($discovery_check_types));

$select_snmpv3_securitylevel = (new CSelect('snmpv3_securitylevel'))
	->setId('snmpv3-securitylevel')
	->setValue($data['params']['snmpv3_securitylevel'])
	->setFocusableElementId('snmpv3-securitylevel-button')
	->addOption(new CSelectOption(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, 'noAuthNoPriv'))
	->addOption(new CSelectOption(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'authNoPriv'))
	->addOption(new CSelectOption(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'authPriv'));

$form_grid = (new CFormGrid())
	->cleanItems()
	->addItem([
		(new CLabel(_('Check type'), $select_type->getFocusableElementId())),
		new CFormField($select_type)
	])
	->addItem([
		(new CLabel(_('Port range'), 'ports'))
			->setId('dcheck_ports_label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('ports', $data['params']['ports']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		))->setId('dcheck_ports')
	])
	->addItem([
		(new CLabel(_('Key'), 'key_'))
			->setId('dcheck_key_label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('key_', $data['params']['key_'], false, DB::getFieldLength('items', 'key_')))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAriaRequired())
		)->setId('dcheck_key')
	])
	->addItem([
		(new CLabel(_('SNMP community'), 'snmp_community'))
			->setId('dcheck_snmp_community_label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('snmp_community', $data['params']['snmp_community']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAriaRequired()
		))->setId('dcheck_snmp_community')
	])
	->addItem([
		(new CLabel(_('SNMP OID'), 'snmp_oid'))
			->setId('dcheck_snmp_oid_label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('snmp_oid', $data['params']['key_']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAriaRequired()
				->setAttribute('maxlength', 512)
		))->setId('dcheck_snmp_oid')
	])
	->addItem([
		(new CLabel(_('Context name'), 'snmpv3_contextname'))->setId('dcheck_snmpv3_contextname_label'),
		(new CFormField(
			(new CTextBox('snmpv3_contextname', $data['params']['snmpv3_contextname']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		))->setId('dcheck_snmpv3_contextname')
	])
	->addItem([
		(new CLabel(_('Security name'), 'snmpv3_securityname'))->setId('dcheck_snmpv3_securityname_label'),
		(new CFormField(
			(new CTextBox('snmpv3_securityname', $data['params']['snmpv3_securityname']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAttribute('maxlength', DB::getFieldLength('dchecks', 'snmpv3_securityname'))
		))->setId('dcheck_snmpv3_securityname')
	])
	->addItem([
		(new CLabel(_('Security level'), $select_snmpv3_securitylevel->getFocusableElementId()))
			->setId('dcheck_snmpv3_securitylevel_label'),
		(new CFormField($select_snmpv3_securitylevel))->setId('dcheck_snmpv3_securitylevel')
	])
	->addItem([
		(new CLabel(_('Authentication protocol'), 'label-authprotocol'))->setId('dcheck_snmpv3_authprotocol_label'),
		(new CFormField(
			(new CSelect('snmpv3_authprotocol'))
				->setValue((int) $data['params']['snmpv3_authprotocol'])
				->setFocusableElementId('label-authprotocol')
				->addOptions(CSelect::createOptionsFromArray(getSnmpV3AuthProtocols()))
		))->setId('dcheck_snmpv3_authprotocol')
	])
	->addItem([
		(new CLabel(_('Authentication passphrase'), 'snmpv3_authpassphrase'))
			->setId('dcheck_snmpv3_authpassphrase_label'),
		(new CFormField(
			(new CTextBox('snmpv3_authpassphrase', $data['params']['snmpv3_authpassphrase']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAttribute('maxlength', DB::getFieldLength('dchecks', 'snmpv3_authpassphrase'))
				->disableAutocomplete()
		))->setId('dcheck_snmpv3_authpassphrase')
	])
	->addItem([
		(new CLabel(_('Privacy protocol'), 'label-privprotocol'))->setId('dcheck_snmpv3_privprotocol_label'),
		(new CFormField(
			(new CSelect('snmpv3_privprotocol'))
				->setValue((int) $data['params']['snmpv3_privprotocol'])
				->setFocusableElementId('label-privprotocol')
				->addOptions(CSelect::createOptionsFromArray(getSnmpV3PrivProtocols()))
		))->setId('dcheck_snmpv3_privprotocol')
	])
	->addItem([
		(new CLabel(_('Privacy passphrase'), 'snmpv3_privpassphrase'))
			->setId('dcheck_snmpv3_privpassphrase_label')
			->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('snmpv3_privpassphrase', $data['params']['snmpv3_privpassphrase']))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAriaRequired()
				->setAttribute('maxlength', DB::getFieldLength('dchecks', 'snmpv3_privpassphrase'))
				->disableAutocomplete()
		))->setId('dcheck_snmpv3_privpassphrase')
	])
	->addItem([
		(new CLabel(_('Allow redirect'), 'allow_redirect'))->setId('allow_redirect_label'),
		(new CFormField(
			(new CCheckBox('allow_redirect'))->setChecked($data['params']['allow_redirect'] == 1)
		))->setId('allow_redirect_field')
	]);

$form
	->addItem([
		$form_grid,
		(new CInput('submit', 'submit'))->addStyle('display: none;'),
		(new CScriptTag(
			'check_popup.init();'
		))->setOnDocumentReady()
	]);

$output = [
	'header' => $data['title'],
	'script_inline' => getPagePostJs().$this->readJsFile('discovery.check.edit.js.php'),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['update'] ? _('Update') : _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'check_popup.submit()'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
