<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

$form = (new CForm())
	->cleanItems()
	->setId('popup.operation')
	->setName('popup.operation')
	->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'));

$form_list = new CFormList();

/*
 * Operation type row.
 */
$select_operationtype = (new CSelect('operationtype'))->setFocusableElementId('operationtype');

$form_list->addRow(new CLabel(_('Operation type'), $select_operationtype->getFocusableElementId()),
	$select_operationtype,
	'operation-type'
);

/*
 * Operation escalation steps row.
 */
$step_from = (new CNumericBox('operation[esc_step_from]', 1, 5))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);
$step_from->onChange($step_from->getAttribute('onchange').' if (this.value < 1) this.value = 1;');
$form_list->addRow(_('Steps'), [
		$step_from,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN), '-', (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CNumericBox('operation[esc_step_to]', 0, 5, false, false, false))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN), _('(0 - infinitely)')
	],
	'operation-step-range'
);

/*
 * Operation steps duration row.
 */
$form_list->addRow(_('Step duration'), [
		(new CTextBox('operation[esc_period]', 0))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		_('(0 - use action default)')
	],
	'operation-step-duration'
);

/*
 * Message recipient is required notice row.
 */
$form_list->addRow('', (new CLabel(_('At least one user or user group must be selected.')))->setAsteriskMark(),
	'operation-message-notice'
);

/*
 * Message recipient (user groups) row.
 */
$form_list->addRow(_('Send to user groups'), (new CDiv(
	(new CTable())
		->addStyle('width: 100%;')
		->setHeader([_('User group'), _('Action')])
		->addRow(
			(new CRow(
				(new CCol(
					(new CButton(null, _('Add')))->addClass(ZBX_STYLE_BTN_LINK)
				))->setColSpan(2)
			))->setId('operation-message-user-groups-footer')
		)
	))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
	'operation-message-user-groups'
);

/*
 * Message recipient (users) row.
 */
$form_list->addRow(_('Send to users'), (new CDiv(
	(new CTable())
		->addStyle('width: 100%;')
		->setHeader([_('User'), _('Action')])
		->addRow(
			(new CRow(
				(new CCol(
					(new CButton(null, _('Add')))->addClass(ZBX_STYLE_BTN_LINK)
				))->setColSpan(2)
			))->setId('operation-message-users-footer')
		)
	))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
	'operation-message-users'
);

/*
 * Operation message media type row.
 */
$select_opmessage_mediatype_default = (new CSelect('operation[opmessage][mediatypeid]'))
	->setFocusableElementId('operation-opmessage-mediatypeid');

$form_list->addRow(
	new CLabel(_('Default media type'), $select_opmessage_mediatype_default->getFocusableElementId()),
	$select_opmessage_mediatype_default,
	'operation-message-mediatype-default'
);

/*
 * Operation message media type row (explicit).
 */
$select_opmessage_mediatype = (new CSelect('operation[opmessage][mediatypeid]'))
	->setFocusableElementId('operation-opmessage-mediatypeid');

$form_list->addRow(new CLabel(_('Send only to'), $select_opmessage_mediatype->getFocusableElementId()),
	$select_opmessage_mediatype,
	'operation-message-mediatype-only'
);

/*
 * Operation custom message checkbox row.
 */
$form_list->addRow(_('Custom message'), new CCheckBox('operation[opmessage][default_msg]', 0),
	'operation-message-custom'
);

/*
 * Operation custom message subject row.
 */
$form_list->addRow(_('Subject'),
	(new CTextBox('operation[opmessage][subject]', ''))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'operation-message-subject'
);

/*
 * Operation custom message body row.
 */
$form_list->addRow(_('Message'),
	(new CTextArea('operation[opmessage][message]', ''))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'operation-message-body'
);

/*
 * Command execution targets row.
 */
$form_list->addRow((new CLabel(_('Target list')))->setAsteriskMark(),
	(new CDiv(
		(new CFormList())
			->cleanItems()
			->addRow(_('Current host'),
				(new CCheckBox('operation[opcommand_hst][][hostid]', '0'))->setId('operation-command-chst')
			)
			->addRow(new CLabel(_('Host')),
				(new CMultiSelect([
					'name' => 'operation[opcommand_hst][][hostid]',
					'object_name' => 'hosts',
					'add_post_js' => false
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
			->addRow(
				(new CLabel(_('Host group'))),
				(new CMultiSelect([
					'name' => 'operation[opcommand_grp][][groupid]',
					'object_name' => 'hostGroup',
					'add_post_js' => false
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
	))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
	'operation-command-targets'
);

/*
 * Command type row.
 */
$select_operation_opcommand_type = (new CSelect('operation[opcommand][type]'))
	->setValue((string) ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT)
	->setFocusableElementId('operation-opcommand-type')
	->addOptions(CSelect::createOptionsFromArray([
		ZBX_SCRIPT_TYPE_IPMI => _('IPMI'),
		ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT => _('Custom script'),
		ZBX_SCRIPT_TYPE_SSH => _('SSH'),
		ZBX_SCRIPT_TYPE_TELNET => _('Telnet'),
		ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT => _('Global script')
	]))
	->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);

$form_list->addRow(new CLabel(_('Type'), $select_operation_opcommand_type->getFocusableElementId()),
	$select_operation_opcommand_type,
	'operation-command-type'
);

/*
 * Command global script row.
 */
$form_list->addRow((new CLabel(_('Script name'), 'operation_opcommand_script'))->setAsteriskMark(), (new CDiv([
		(new CVar('operation[opcommand][scriptid]', 0)),
		(new CTextBox('operation[opcommand][script]', '', true))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton('operation-command-global-script-select', _('Select')))->addClass(ZBX_STYLE_BTN_GREY)
	]))->addClass(ZBX_STYLE_NOWRAP),
	'operation-command-global-script'
);

/*
 * Command execute target row.
 */
$form_list->addRow((new CLabel(_('Execute on'), 'operation_opcommand_execute_on')),
	(new CRadioButtonList('operation[opcommand][execute_on]', ZBX_SCRIPT_EXECUTE_ON_AGENT))
		->addValue(_('Zabbix agent'), ZBX_SCRIPT_EXECUTE_ON_AGENT)
		->addValue(_('Zabbix server (proxy)'), ZBX_SCRIPT_EXECUTE_ON_PROXY)
		->addValue(_('Zabbix server'), ZBX_SCRIPT_EXECUTE_ON_SERVER)
		->setModern(true),
	'operation-command-script-target'
);

/*
 * Command authentication method row.
 */
$select_operation_opcommand_authtype = (new CSelect('operation[opcommand][authtype]'))
	->setFocusableElementId('operation-opcommand-authtype')
	->addOption(new CSelectOption(ITEM_AUTHTYPE_PASSWORD, _('Password')))
	->addOption(new CSelectOption(ITEM_AUTHTYPE_PUBLICKEY, _('Public key')))
	->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);

$form_list->addRow(
	new CLabel(_('Authentication method'), $select_operation_opcommand_authtype->getFocusableElementId()),
	$select_operation_opcommand_authtype,
	'operation-command-authtype'
);

/*
 * Command user name row.
 */
$form_list->addRow((new CLabel(_('User name'), 'operation_opcommand_username'))->setAsteriskMark(),
	(new CTextBox('operation[opcommand][username]'))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->setAriaRequired(),
	'operation-command-username'
);

/*
 * Command public key row.
 */
$form_list->addRow((new CLabel(_('Public key file'), 'operation_opcommand_publickey'))->setAsteriskMark(),
	(new CTextBox('operation[opcommand][publickey]'))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->setAriaRequired(),
	'operation-command-pubkey'
);

/*
 * Command private key row.
 */
$form_list->addRow((new CLabel(_('Private key file'), 'operation_opcommand_privatekey'))->setAsteriskMark(),
	(new CTextBox('operation[opcommand][privatekey]'))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->setAriaRequired(),
	'operation-command-privatekey'
);

/*
 * Command password row.
 */
$form_list->addRow(_('Password'), (new CTextBox('operation[opcommand][password]'))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'operation-command-password'
);

/*
 * Command passphrase row.
 */
$form_list->addRow(_('Key passphrase'), (new CTextBox('operation[opcommand][password]'))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->setId('opcommand_passphrase'),
	'operation-command-passphrase'
);

/*
 * Command port row.
 */
$form_list->addRow(_('Port'), (new CTextBox('operation[opcommand][port]'))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'operation-command-port'
);

/*
 * Command input row.
 */
$form_list->addRow((new CLabel(_('Commands'), 'operation_opcommand_command'))->setAsteriskMark(),
	(new CTextArea('operation[opcommand][command]'))
		->addClass(ZBX_STYLE_MONOSPACE_FONT)
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	'operation-command-cmd'
);

/*
 * Command input row (ipmi).
 */
$form_list->addRow((new CLabel(_('Commands'), 'operation_opcommand_command_ipmi'))->setAsteriskMark(),
	(new CTextBox('operation[opcommand][command]'))
		->addClass(ZBX_STYLE_MONOSPACE_FONT)
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setId('operation_opcommand_command_ipmi')
		->setAriaRequired(),
	'operation-command-cmd-ipmi'
);

/*
 * Add / remove host group attribute row.
 */
$form_list->addRow((new CLabel(_('Host groups')))->setAsteriskMark(), (new CMultiSelect([
		'name' => 'operation[opgroup][][groupid]',
		'object_name' => 'hostGroup',
		'add_post_js' => false
	]))
		->setAriaRequired()
		->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
	'operation-attr-hostgroups'
);

/*
 * Link / unlink templates attribute row.
 */
$form_list->addRow((new CLabel(_('Templates')))->setAsteriskMark(), (new CMultiSelect([
		'name' => 'operation[optemplate][][templateid]',
		'object_name' => 'templates',
		'add_post_js' => false
	]))
		->setAriaRequired()
		->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
	'operation-attr-templates'
);

/*
 * Host inventory mode attribute row.
 */
$form_list->addRow(new CLabel(_('Inventory mode'), 'operation_opinventory_inventory_mode'),
	(new CRadioButtonList('operation[opinventory][inventory_mode]', HOST_INVENTORY_MANUAL))
		->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
		->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
		->setModern(true),
	'operation-attr-inventory'
);

/*
 * Conditions type of calculation row.
 */
$select_operation_evaltype = (new CSelect('operation[evaltype]'))
	->setValue((string) CONDITION_EVAL_TYPE_AND_OR)
	->setFocusableElementId('operation-evaltype')
	->addOption(new CSelectOption(CONDITION_EVAL_TYPE_AND_OR, _('And/Or')))
	->addOption(new CSelectOption(CONDITION_EVAL_TYPE_AND, _('And')))
	->addOption(new CSelectOption(CONDITION_EVAL_TYPE_OR, _('Or')));

$form_list->addRow(new CLabel(_('Type of calculation'), $select_operation_evaltype->getFocusableElementId()), [
		$select_operation_evaltype,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CSpan())->setId('operation-condition-evaltype-formula')
	],
	'operation-condition-evaltype'
);

/*
 * Conditions row.
 */
$form_list->addRow(_('Conditions'), (new CDiv(
	(new CTable())
		->addStyle('width: 100%;')
		->setHeader([_('Label'), _('Name'), _('Action')])
		->addRow(
			(new CRow(
				(new CCol(
					(new CButton(null, _('Add')))->addClass(ZBX_STYLE_BTN_LINK)
				))->setColSpan(3)
			))->setId('operation-condition-list-footer')
		)
	))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
	'operation-condition-list'
);

echo $form
	->addItem($form_list)
	->toString();
