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

$form = (new CForm())
	->setId('mediatype-message-form')
	->addVar('action', 'mediatype.message.edit')
	->addVar('type', $data['params']['type'])
	->addVar('message_format', $data['params']['message_format'])
	->addVar('old_message_type', $data['params']['old_message_type'])
	->addVar('message_types', $data['params']['message_types']);

if ($data['params']['old_message_type'] != -1) {
	foreach ($data['params']['message_types'] as $idx => $message_type) {
		if ($message_type == $data['params']['old_message_type']) {
			unset($data['params']['message_types'][$idx]);
		}
	}
}

$message_type_select = (new CSelect('message_type'))
	->setId('message_type')
	->setFocusableElementId('message-type')
	->setValue($data['params']['old_message_type'])
	->addOption((new CSelectOption(CMediatypeHelper::MSG_TYPE_PROBLEM, _('Problem')))
		->setDisabled(in_array(CMediatypeHelper::MSG_TYPE_PROBLEM, $data['params']['message_types']))
	)
	->addOption((new CSelectOption(CMediatypeHelper::MSG_TYPE_RECOVERY, _('Problem recovery')))
		->setDisabled(in_array(CMediatypeHelper::MSG_TYPE_RECOVERY, $data['params']['message_types']))
	)
	->addOption((new CSelectOption(CMediatypeHelper::MSG_TYPE_UPDATE, _('Problem update')))
		->setDisabled(in_array(CMediatypeHelper::MSG_TYPE_UPDATE, $data['params']['message_types']))
	)
	->addOption((new CSelectOption(CMediatypeHelper::MSG_TYPE_SERVICE, _('Service')))
		->setDisabled(in_array(CMediatypeHelper::MSG_TYPE_SERVICE, $data['params']['message_types']))
	)
	->addOption((new CSelectOption(CMediatypeHelper::MSG_TYPE_SERVICE_RECOVERY, _('Service recovery')))
		->setDisabled(in_array(CMediatypeHelper::MSG_TYPE_SERVICE_RECOVERY, $data['params']['message_types']))
	)
	->addOption((new CSelectOption(CMediatypeHelper::MSG_TYPE_SERVICE_UPDATE, _('Service update')))
		->setDisabled(in_array(CMediatypeHelper::MSG_TYPE_SERVICE_UPDATE, $data['params']['message_types']))
	)
	->addOption((new CSelectOption(CMediatypeHelper::MSG_TYPE_DISCOVERY, _('Discovery')))
		->setDisabled(in_array(CMediatypeHelper::MSG_TYPE_DISCOVERY, $data['params']['message_types']))
	)
	->addOption((new CSelectOption(CMediatypeHelper::MSG_TYPE_AUTOREG, _('Autoregistration')))
		->setDisabled(in_array(CMediatypeHelper::MSG_TYPE_AUTOREG, $data['params']['message_types']))
	)
	->addOption((new CSelectOption(CMediatypeHelper::MSG_TYPE_INTERNAL, _('Internal problem')))
		->setDisabled(in_array(CMediatypeHelper::MSG_TYPE_INTERNAL, $data['params']['message_types']))
	)
	->addOption((new CSelectOption(CMediatypeHelper::MSG_TYPE_INTERNAL_RECOVERY, _('Internal problem recovery')))
		->setDisabled(in_array(CMediatypeHelper::MSG_TYPE_INTERNAL_RECOVERY, $data['params']['message_types']))
	);

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Message type'), $message_type_select->getFocusableElementId()))->setId('label-message-type'),
		new CFormField($message_type_select)
	]);

if ($data['params']['type'] != MEDIA_TYPE_SMS) {
	$form_grid->addItem([
		new CLabel(_('Subject'), 'subject'),
		new CFormField(
			(new CTextBox('subject', $data['params']['subject']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('maxlength', DB::getFieldLength('media_type_message', 'subject'))
		)
	]);
}

$form_grid->addItem([
	new CLabel(_('Message'), 'message'),
	new CFormField((new CTextArea('message', $data['params']['message']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('maxlength', DB::getFieldLength('media_type_message', 'message'))
	)
]);

$form
	->addItem($form_grid)
	->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'))
	->addItem(
		(new CScriptTag('mediatype_message_popup.init('.json_encode([
			'message_templates' => CMediatypeHelper::getAllMessageTemplates()
		]).');'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['title'],
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['params']['old_message_type'] == -1 ? _('Add') : _('Update'),
			'class' => 'dialogue-widget-save',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'mediatype_message_popup.submit();'
		]
	],
	'script_inline' => getPagePostJs().$this->readJsFile('mediatype.message.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
