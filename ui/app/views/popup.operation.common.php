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

require_once dirname(__FILE__).'/../../include/actions.inc.php';

$inline_js = '';
$opr_data = $data['data'];

$form = (new CForm())
	->cleanItems()
	->setId('popup.operation')
	->setName('popup.operation')
	->addVar('action', $data['action'])
	->addVar('type', $data['type'])
	->addVar('source', $data['source']);

if ($data['update'] > 0) {
	// For popup purpose.
	$form->addVar('update', '1');
}

if ($data['actionid']) {
	// For popup purpose.
	$form->addVar('actionid', $data['actionid']);
	// For edit purpose.
	$form->addVar('operation[actionid]', $data['actionid']);
}

if (array_key_exists('id', $opr_data)) {
	// For edit purpose.
	$form->addVar('operation[id]', $opr_data['id']);
}

if (array_key_exists('operationid', $opr_data)) {
	// For edit purpose.
	$form->addVar('operation[operationid]', $opr_data['operationid']);
}

$form_list = (new CFormList())->cleanItems();

// If only one operation is available - show only the label.
if (count($data['allowed_operations'][$data['type']]) == 1) {
	$operation = $data['allowed_operations'][$data['type']][0];
	$form_list->addRow(_('Operation type'), [operation_type2str($operation), new CVar('operationtype', $operation)]);
}
else {
	// Show select with options.
	$operation_type_combobox = new CComboBox(
		'operationtype',
		$data['operationtype'],
		"resetOpmessage(); reloadPopup(this.form, '".$data['action']."');"
	);
	foreach ($data['allowed_operations'][$data['type']] as $operation) {
		$operation_type_combobox->addItem($operation, operation_type2str($operation));
	}

	$form_list->addRow(_('Operation type'), $operation_type_combobox);
}

// Show step inputs only for operation with triggers or internal source.
if ($data['type'] == ACTION_OPERATION
		&& ($data['source'] == EVENT_SOURCE_TRIGGERS || $data['source'] == EVENT_SOURCE_INTERNAL)) {
	$step_from = (new CNumericBox('operation[esc_step_from]', $opr_data['esc_step_from'], 5))
		->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);
	$step_from->onChange($step_from->getAttribute('onchange').' if (this.value < 1) this.value = 1;');

	$step_to = (new CNumericBox('operation[esc_step_to]', $opr_data['esc_step_to'], 5))
		->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);

	$form_list->addRow(_('Steps'), [
		$step_from,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN), '-', (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$step_to,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN), _('(0 - infinitely)')
	]);

	$form_list->addRow(_('Step duration'), [
		(new CTextBox('operation[esc_period]', $opr_data['esc_period']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		_('(0 - use action default)')
	]);
}

// Set default message.
if (in_array($data['operationtype'], [
	OPERATION_TYPE_MESSAGE,
	OPERATION_TYPE_ACK_MESSAGE,
	OPERATION_TYPE_RECOVERY_MESSAGE
])) {
	if (!array_key_exists('opmessage', $opr_data)) {
		$opr_data['opmessage_usr'] = [];
		$opr_data['opmessage'] = [
			'default_msg' => 1,
			'mediatypeid' => 0,
			'subject' => '',
			'message' => ''
		];
	}

	if (!array_key_exists('default_msg', $opr_data['opmessage'])) {
		$opr_data['opmessage']['default_msg'] = 1;
	}

	if (!array_key_exists('mediatypeid', $opr_data['opmessage'])) {
		$opr_data['opmessage']['mediatypeid'] = 0;
	}
}

switch ($data['operationtype']) {
	// Send message form elements.
	case OPERATION_TYPE_MESSAGE:
		$form_list->addRow('', (new CLabel(_('At least one user or user group must be selected.')))->setAsteriskMark());

		// Send to user group elements.
		$user_group_list = (new CTable())
			->setAttribute('style', 'width: 100%;')
			->setHeader([_('User group'), _('Action')]);

		$user_group_add_btn = (new CButton(null, _('Add')))
			->onClick('return PopUp("popup.generic",'.
				json_encode([
					'srctbl' => 'usrgrp',
					'srcfld1' => 'usrgrpid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'opmsgUsrgrpListFooter',
					'multiselect' => '1'
				]).', null, this);'
			)
			->addClass(ZBX_STYLE_BTN_LINK);

		$user_group_list->addRow(
			(new CRow((new CCol($user_group_add_btn))->setColSpan(2)))->setId('opmsgUsrgrpListFooter')
		);

		$user_groupids = array_key_exists('opmessage_grp', $opr_data)
			? zbx_objectValues($opr_data['opmessage_grp'], 'usrgrpid')
			: [];

		$user_groups = API::UserGroup()->get([
			'usrgrpids' => $user_groupids,
			'output' => ['name']
		]);
		order_result($user_groups, 'name');

		$inline_js .= 'addPopupValues('.
			zbx_jsvalue(['object' => 'usrgrpid', 'values' => $user_groups, 'parentId' => 'opmsgUsrgrpListFooter']).
		');';

		$form_list
			->addRow(_('Send to User groups'),
				(new CDiv($user_group_list))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
			);

		// Send to user elements.
		$user_list = (new CTable())
			->setAttribute('style', 'width: 100%;')
			->setHeader([_('User'), _('Action')]);

		$user_add_btn = (new CButton(null, _('Add')))
			->onClick('return PopUp("popup.generic",'.
				json_encode([
					'srctbl' => 'users',
					'srcfld1' => 'userid',
					'srcfld2' => 'fullname',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'opmsgUserListFooter',
					'multiselect' => '1'
				]).', null, this);'
			)
			->addClass(ZBX_STYLE_BTN_LINK);

		$user_list->addRow((new CRow((new CCol($user_add_btn))->setColSpan(2)))->setId('opmsgUserListFooter'));

		$userids = array_key_exists('opmessage_usr', $opr_data)
			? zbx_objectValues($opr_data['opmessage_usr'], 'userid')
			: [];

		$users = API::User()->get([
			'userids' => $userids,
			'output' => ['userid', 'alias', 'name', 'surname']
		]);
		order_result($users, 'alias');

		foreach ($users as &$user) {
			$user['id'] = $user['userid'];
			$user['name'] = getUserFullname($user);
		}
		unset($user);

		$inline_js .= 'addPopupValues('.
			zbx_jsvalue(['object' => 'userid', 'values' => $users, 'parentId' => 'opmsgUserListFooter']).
		');';

		$form_list
			->addRow(_('Send to Users'),
				(new CDiv($user_list))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
			);
		// break; is not missing here

	// Notify all involved form elements.
	case OPERATION_TYPE_ACK_MESSAGE:
		// Media types elements.
		$media_types = API::MediaType()->get([
			'output' => ['name'],
			'preservekeys' => true
		]);
		order_result($media_types, 'name');

		$media_type_options = [0 => '- '._('All').' -'];
		foreach ($media_types as $key => $value) {
			$media_type_options[$key] = $value['name'];
		}

		$media_type_combobox = (new CComboBox(
			'operation[opmessage][mediatypeid]',
			$opr_data['opmessage']['mediatypeid'],
			null,
			$media_type_options
		));

		if ($data['operationtype'] == OPERATION_TYPE_ACK_MESSAGE) {
			$form_list->addRow(_('Default media type'), $media_type_combobox);
		}
		else {
			$form_list->addRow(_('Send only to'), $media_type_combobox);
		}
		// break; is not missing here

	// Notify all involved form elements.
	case OPERATION_TYPE_RECOVERY_MESSAGE:
		$form_list
			->addRow(_('Custom message'),
				(new CCheckBox('operation[opmessage][default_msg]', 0))
					->setChecked($opr_data['opmessage']['default_msg'] == 0)
			)
			->addRow(_('Subject'),
				(new CTextBox('operation[opmessage][subject]', $opr_data['opmessage']['subject']))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
			->addRow(_('Message'),
				(new CTextArea('operation[opmessage][message]', $opr_data['opmessage']['message']))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			);

			$inline_js .=
				"jQuery('#".zbx_formatDomId('operation[opmessage][default_msg]')."').on('change', function() {".
					"var default_message = jQuery(this).is(':checked');".

					"jQuery('".
						"#".zbx_formatDomId('operation[opmessage][subject]').",".
						"#".zbx_formatDomId('operation[opmessage][message]').
					"')".
						".closest('li')".
						".toggle(default_message);".
				"});".
				"jQuery('#".zbx_formatDomId('operation[opmessage][default_msg]')."').trigger('change');";
		break;

	// Remote command form elements.
	case OPERATION_TYPE_COMMAND:
		// Set default value to fields.
		foreach (['opcommand', 'opcommand_grp', 'opcommand_hst'] as $field_name) {
			if (!array_key_exists($field_name, $opr_data)) {
				$opr_data[$field_name] = [];
			}
		}

		foreach (['scriptid', 'publickey', 'privatekey', 'username', 'password', 'port', 'command'] as $field_name) {
			if (!array_key_exists($field_name, $opr_data['opcommand'])) {
				$opr_data['opcommand'][$field_name] = '';
			}
		}

		if (!array_key_exists('type', $opr_data['opcommand'])) {
			$opr_data['opcommand']['type'] = ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT;
		}

		if (!array_key_exists('execute_on', $opr_data['opcommand'])) {
			$opr_data['opcommand']['execute_on'] = ZBX_SCRIPT_EXECUTE_ON_AGENT;
		}

		if (!array_key_exists('authtype', $opr_data['opcommand'])) {
			$opr_data['opcommand']['authtype'] = ITEM_AUTHTYPE_PASSWORD;
		}

		$opr_data['opcommand']['script'] = '';
		if (!zbx_empty($opr_data['opcommand']['scriptid'])) {
			$user_scripts = API::Script()->get([
				'output' => ['name'],
				'scriptids' => $opr_data['opcommand']['scriptid']
			]);
			if ($user_script = reset($user_scripts)) {
				$opr_data['opcommand']['script'] = $user_script['name'];
			}
		}

		// For new target list.
		$checked_current_host = array_key_exists('0', $opr_data['opcommand_hst'])
			? ($opr_data['opcommand_hst']['0'] === '0')
			: false;

		if ($checked_current_host) {
			unset($opr_data['opcommand_hst']['0']);
		}
		else {
			$checked_current_host = array_key_exists('opcommand_chst', $opr_data);
		}

		$hosts = API::Host()->get([
			'hostids' => zbx_objectValues($opr_data['opcommand_hst'], 'hostid'),
			'output' => ['name'],
			'preservekeys' => true,
			'editable' => true
		]);

		$opr_data['opcommand_hst'] = array_values($opr_data['opcommand_hst']);
		foreach ($opr_data['opcommand_hst'] as $key => $value) {
			$opr_data['opcommand_hst'][$key] = [
				'hostid' => $value,
				'name' => $hosts[$value]['name']
			];
		}
		order_result($opr_data['opcommand_hst'], 'name');

		$groups = API::HostGroup()->get([
			'groupids' => zbx_objectValues($opr_data['opcommand_grp'], 'groupid'),
			'output' => ['groupid', 'name'],
			'preservekeys' => true,
			'editable' => true
		]);

		$opr_data['opcommand_grp'] = array_values($opr_data['opcommand_grp']);
		foreach ($opr_data['opcommand_grp'] as $key => $value) {
			$opr_data['opcommand_grp'][$key] = [
				'groupid' => $value,
				'name' => $groups[$value]['name']
			];
		}
		order_result($opr_data['opcommand_grp'], 'name');

		// JS add commands.
		$host_values = zbx_jsvalue([
			'object' => 'hostid',
			'values' => $opr_data['opcommand_hst'],
			'parentId' => 'opCmdListFooter'
		]);

		$inline_js .= 'addPopupValues('.$host_values.');';

		$group_values = zbx_jsvalue([
			'object' => 'groupid',
			'values' => $opr_data['opcommand_grp'],
			'parentId' => 'opCmdListFooter'
		]);

		$inline_js .= 'addPopupValues('.$group_values.');';

		// New target list.
		$form_list->addRow(
			(new CLabel(_('Target list'), 'opCmdList'))->setAsteriskMark(),
			(new CDiv(
				(new CFormList())
					->cleanItems()
					->addRow(
						(new CLabel(_('Current host'))),
						(new CCheckBox('operation[opcommand_chst]', '0'))->setChecked($checked_current_host)
					)
					->addRow(
						(new CLabel(_('Host'))),
						$target_list_multiselect_host = (new CMultiSelect([
							'name' => 'operation[opcommand_hst][]',
							'object_name' => 'hosts',
							'data' => CArrayHelper::renameObjectsKeys($opr_data['opcommand_hst'], ['hostid' => 'id']),
							'popup' => [
								'parameters' => [
									'srctbl' => 'hosts',
									'srcfld1' => 'hostid',
									'dstfrm' => 'action.edit',
									'dstfld1' => 'operation_opcommand_hst_',
									'editable' => '1',
									'multiselect' => '1',
								]
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
					->addRow(
						(new CLabel(_('Host group'))),
						$target_list_multiselect_hostgroup = (new CMultiSelect([
							'name' => 'operation[opcommand_grp][]',
							'object_name' => 'hostGroup',
							'data' => CArrayHelper::renameObjectsKeys($opr_data['opcommand_grp'], ['groupid' => 'id']),
							'popup' => [
								'parameters' => [
									'srctbl' => 'host_groups',
									'srcfld1' => 'groupid',
									'dstfrm' => 'action.edit',
									'dstfld1' => 'operation_opcommand_grp_',
									'editable' => '1',
									'multiselect' => '1',
								]
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
			))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
				->setId('opCmdList')
		);

		$inline_js .= $target_list_multiselect_host->getPostJS();
		$inline_js .= $target_list_multiselect_hostgroup->getPostJS();

		$user_script = [
			new CVar('operation[opcommand][scriptid]', $opr_data['opcommand']['scriptid']),
			(new CTextBox('operation[opcommand][script]', $opr_data['opcommand']['script'], true))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('select_operation_opcommand_script', _('Select')))->addClass(ZBX_STYLE_BTN_GREY)
		];

		$form_list
			// Type.
			->addRow(
				(new CLabel(_('Type'), 'operation[opcommand][type]')),
				(new CComboBox('operation[opcommand][type]',
					$opr_data['opcommand']['type'],
					'showOpTypeForm()',
					[
						ZBX_SCRIPT_TYPE_IPMI => _('IPMI'),
						ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT => _('Custom script'),
						ZBX_SCRIPT_TYPE_SSH => _('SSH'),
						ZBX_SCRIPT_TYPE_TELNET => _('Telnet'),
						ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT => _('Global script')
					]
				))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			)
			->addRow(
				(new CLabel(_('Script name'), 'operation_opcommand_script'))->setAsteriskMark(),
				(new CDiv($user_script))->addClass(ZBX_STYLE_NOWRAP)
			)
			// Script.
			->addRow(
				(new CLabel(_('Execute on'), 'operation[opcommand][execute_on]')),
				(new CRadioButtonList('operation[opcommand][execute_on]', (int) $opr_data['opcommand']['execute_on']))
					->addValue(_('Zabbix agent'), ZBX_SCRIPT_EXECUTE_ON_AGENT)
					->addValue(_('Zabbix server (proxy)'), ZBX_SCRIPT_EXECUTE_ON_PROXY)
					->addValue(_('Zabbix server'), ZBX_SCRIPT_EXECUTE_ON_SERVER)
					->setModern(true)
			)
			// SSH
			->addRow(_('Authentication method'),
				(new CComboBox('operation[opcommand][authtype]',
					$opr_data['opcommand']['authtype'],
					'showOpTypeAuth('.ACTION_OPERATION.')', [
						ITEM_AUTHTYPE_PASSWORD => _('Password'),
						ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
					]
				))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			)
			->addRow(
				(new CLabel(_('User name'), 'operation[opcommand][username]'))->setAsteriskMark(),
				(new CTextBox('operation[opcommand][username]', $opr_data['opcommand']['username']))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->setAriaRequired()
			)
			->addRow(
				(new CLabel(_('Public key file'), 'operation[opcommand][publickey]'))->setAsteriskMark(),
				(new CTextBox('operation[opcommand][publickey]', $opr_data['opcommand']['publickey']))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->setAriaRequired()
			)
			->addRow(
				(new CLabel(_('Private key file'), 'operation[opcommand][privatekey]'))->setAsteriskMark(),
				(new CTextBox('operation[opcommand][privatekey]', $opr_data['opcommand']['privatekey']))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->setAriaRequired()
			)
			->addRow(_('Password'),
				(new CTextBox('operation[opcommand][password]', $opr_data['opcommand']['password']))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			)
			// Set custom id because otherwise they are set based on name (sick!) and produce duplicate ids.
			->addRow(_('Key passphrase'),
				(new CTextBox('operation[opcommand][password]', $opr_data['opcommand']['password']))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->setId('opcommand_passphrase')
			)
			// SSH && telnet.
			->addRow(_('Port'),
				(new CTextBox('operation[opcommand][port]', $opr_data['opcommand']['port']))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			)
			->addRow(
				(new CLabel(_('Commands'), 'operation[opcommand][command]'))->setAsteriskMark(),
				(new CTextArea('operation[opcommand][command]', $opr_data['opcommand']['command']))
					->addClass(ZBX_STYLE_MONOSPACE_FONT)
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
			)
			->addRow(
				(new CLabel(_('Commands'), 'operation_opcommand_command_ipmi'))->setAsteriskMark(),
				(new CTextBox('operation[opcommand][command]', $opr_data['opcommand']['command']))
					->addClass(ZBX_STYLE_MONOSPACE_FONT)
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setId('operation_opcommand_command_ipmi')
					->setAriaRequired()
			);

		$inline_js .=
			"jQuery('#select_operation_opcommand_script').click(function(event) {".
				"PopUp('popup.generic', {".
					"srctbl: 'scripts',".
					"srcfld1: 'scriptid',".
					"srcfld2: 'name',".
					"dstfrm: 'popup.operation',".
					"dstfld1: 'operation_opcommand_scriptid',".
					"dstfld2: 'operation_opcommand_script'".
				"}, null, event.target);".
			"});";

		$inline_js .= "showOpTypeForm();";
		break;

	case OPERATION_TYPE_HOST_ADD:
	case OPERATION_TYPE_HOST_REMOVE:
	case OPERATION_TYPE_HOST_ENABLE:
	case OPERATION_TYPE_HOST_DISABLE:
		$form->addVar('object', 0);
		$form->addVar('objectid', 0);
		$form->addVar('shortdata', '');
		$form->addVar('longdata', '');
		break;

	case OPERATION_TYPE_GROUP_ADD:
	case OPERATION_TYPE_GROUP_REMOVE:
		if (!array_key_exists('opgroup', $opr_data)) {
			$opr_data['opgroup'] = [];
		}

		$groups = API::HostGroup()->get([
			'groupids' => zbx_objectValues($opr_data['opgroup'], 'groupid'),
			'output' => ['groupid', 'name'],
			'editable' => true,
			'preservekeys' => true
		]);

		foreach($opr_data['opgroup'] as &$val) {
			$val['name'] = $groups[$val['groupid']]['name'];
		}
		unset($val);

		$opr_groups = (new CMultiSelect([
			'name' => 'operation[groupids][]',
			'object_name' => 'hostGroup',
			'data' => CArrayHelper::renameObjectsKeys($opr_data['opgroup'], ['groupid' => 'id']),
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'operation_groupids_',
					'editable' => true
				]
			]
		]))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

		$form_list->addRow((new CLabel(_('Host groups')))->setAsteriskMark(), $opr_groups);

		$inline_js .= $opr_groups->getPostJS();
		break;

	case OPERATION_TYPE_TEMPLATE_ADD:
	case OPERATION_TYPE_TEMPLATE_REMOVE:
		if (!array_key_exists('optemplate', $opr_data)) {
			$opr_data['optemplate'] = [];
		}

		$templates = API::Template()->get([
			'templateids' => zbx_objectValues($opr_data['optemplate'], 'templateid'),
			'output' => ['templateid', 'name'],
			'editable' => true,
			'preservekeys' => true
		]);

		foreach($opr_data['optemplate'] as &$val) {
			$val['name'] = $templates[$val['templateid']]['name'];
		}
		unset($val);

		$opr_templates = (new CMultiSelect([
			'name' => 'operation[templateids][]',
			'object_name' => 'templates',
			'data' => CArrayHelper::renameObjectsKeys($opr_data['optemplate'], ['templateid' => 'id']),
			'popup' => [
				'parameters' => [
					'srctbl' => 'templates',
					'srcfld1' => 'hostid',
					'srcfld2' => 'host',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'operation_templateids_',
					'editable' => true
				]
			]
		]))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);

		$form_list->addRow((new CLabel(_('Templates')))->setAsteriskMark(), $opr_templates);

		$inline_js .= $opr_templates->getPostJS();
		break;

	case OPERATION_TYPE_HOST_INVENTORY:
		$value = array_key_exists('opinventory', $opr_data)
			? (int) $opr_data['opinventory']['inventory_mode']
			: HOST_INVENTORY_MANUAL;

		$form_list->addRow(
			new CLabel(_('Inventory mode'), 'operation[opinventory][inventory_mode]'),
			(new CRadioButtonList('operation[opinventory][inventory_mode]', $value))
				->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
				->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
				->setModern(true)
		);
		break;
}

// Append operation conditions to form list.
if ($data['type'] == ACTION_OPERATION && $data['source'] == EVENT_SOURCE_TRIGGERS) {
	if (!array_key_exists('opconditions', $opr_data)) {
		$opr_data['opconditions'] = [];
	}
	zbx_rksort($opr_data['opconditions']);

	$allowed_opconditions = get_opconditions_by_eventsource(EVENT_SOURCE_TRIGGERS);
	$grouped_opconditions = [];

	$opcondition_table = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setId('operationConditionTable')
		->setHeader([_('Label'), _('Name'), _('Action')]);

	$i = 0;
	foreach ($opr_data['opconditions'] as $index => $opcondition) {
		if (!array_key_exists('conditiontype', $opcondition) || !$opcondition['conditiontype']) {
			$opcondition['conditiontype'] = 0;
		}
		if (!array_key_exists('operator', $opcondition) || !$opcondition['operator']) {
			$opcondition['operator'] = 0;
		}
		if (!array_key_exists('value', $opcondition) || !$opcondition['value']) {
			$opcondition['value'] = 0;
		}
		if (!str_in_array($opcondition['conditiontype'], $allowed_opconditions)) {
			continue;
		}

		$label = num2letter($i);
		$labelCol = (new CCol($label))
			->addClass('label')
			->setAttribute('data-conditiontype', $opcondition['conditiontype'])
			->setAttribute('data-formulaid', $label);
		$opcondition_table->addRow([
				$labelCol,
				getConditionDescription($opcondition['conditiontype'], $opcondition['operator'],
					$opcondition['value'], ''
				),
				(new CCol([
					(new CButton('remove', _('Remove')))
						->onClick('removeOperationCondition('.$i.');')
						->addClass(ZBX_STYLE_BTN_LINK)
						->removeId(),
					new CVar('operation[opconditions]['.$i.'][conditiontype]', $opcondition['conditiontype']),
					new CVar('operation[opconditions]['.$i.'][operator]', $opcondition['operator']),
					new CVar('operation[opconditions]['.$i.'][value]', $opcondition['value'])
				]))->addClass(ZBX_STYLE_NOWRAP)
			],
			null, 'opconditions_'.$i
		);

		$i++;
	}

	$calc_type_combobox = (new CComboBox(
		'operation[evaltype]',
		$opr_data['evaltype'],
		'processOperationTypeOfCalculation()',
		[
			CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
			CONDITION_EVAL_TYPE_AND => _('And'),
			CONDITION_EVAL_TYPE_OR => _('Or')
		]
	))->setId('operationEvaltype');

	$form_list->addRow(_('Type of calculation'), [
		$calc_type_combobox,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CSpan())->setId('operationConditionLabel')
	]);

	$inline_js .= "processOperationTypeOfCalculation();";

	$opcondition_table->addRow([
		(new CSimpleButton(_('Add')))
			->onClick('return PopUp("popup.condition.operations",'.json_encode([
				'type' => ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION,
				'source' => $data['source']
			]).', null, this);')
			->addClass(ZBX_STYLE_BTN_LINK)
	]);

	$form_list->addRow(_('Conditions'),
		(new CDiv($opcondition_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	);
}

$form
	->addItem($form_list)
	->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'));

$output = [
	'header' => $data['title'],
	'script_inline' => $inline_js,
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['update'] ? _('Update') : _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return validateOperationPopup(overlay);'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
