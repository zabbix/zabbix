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
	->setId('mediatype_message_form')
	->setName('mediatype_message_form')
	->addVar('action', 'popup.mediatype.message')
	->addVar('type', $data['params']['type'])
	->addVar('content_type', $data['params']['content_type'])
	->addVar('old_message_type', $data['params']['old_message_type'])
	->addVar('message_types', $data['params']['message_types']);

if ($data['params']['old_message_type'] != -1) {
	foreach ($data['params']['message_types'] as $idx => $message_type) {
		if ($message_type == $data['params']['old_message_type']) {
			unset($data['params']['message_types'][$idx]);
		}
	}
}

$form_list = (new CFormList())->addRow(_('Message type'),
	(new CComboBox('message_type', $data['params']['old_message_type']))
		->addItem(CMediatypeHelper::MSG_TYPE_PROBLEM, _('Problem'), null,
			!in_array(CMediatypeHelper::MSG_TYPE_PROBLEM, $data['params']['message_types'])
		)
		->addItem(CMediatypeHelper::MSG_TYPE_RECOVERY, _('Problem recovery'), null,
			!in_array(CMediatypeHelper::MSG_TYPE_RECOVERY, $data['params']['message_types'])
		)
		->addItem(CMediatypeHelper::MSG_TYPE_UPDATE, _('Problem update'), null,
			!in_array(CMediatypeHelper::MSG_TYPE_UPDATE, $data['params']['message_types'])
		)
		->addItem(CMediatypeHelper::MSG_TYPE_DISCOVERY, _('Discovery'), null,
			!in_array(CMediatypeHelper::MSG_TYPE_DISCOVERY, $data['params']['message_types'])
		)
		->addItem(CMediatypeHelper::MSG_TYPE_AUTOREG, _('Autoregistration'), null,
			!in_array(CMediatypeHelper::MSG_TYPE_AUTOREG, $data['params']['message_types'])
		)
		->addItem(CMediatypeHelper::MSG_TYPE_INTERNAL, _('Internal problem'), null,
			!in_array(CMediatypeHelper::MSG_TYPE_INTERNAL, $data['params']['message_types'])
		)
		->addItem(CMediatypeHelper::MSG_TYPE_INTERNAL_RECOVERY, _('Internal problem recovery'), null,
			!in_array(CMediatypeHelper::MSG_TYPE_INTERNAL_RECOVERY, $data['params']['message_types'])
		)
);

if ($data['params']['type'] != MEDIA_TYPE_SMS) {
	$form_list->addRow(_('Subject'),
		(new CTextBox('subject', $data['params']['subject']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('maxlength', DB::getFieldLength('media_type_message', 'subject'))
	);
}

$form_list->addRow(_('Message'),
	(new CTextArea('message', $data['params']['message']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('maxlength', DB::getFieldLength('media_type_message', 'message'))
);

$form
	->addItem($form_list)
	->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'));

$output = [
	'header' => $data['title'],
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => ($data['params']['old_message_type'] == -1) ? _('Add') : _('Update'),
			'class' => 'dialogue-widget-save',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'submitMessageTemplate(overlay);'
		]
	],
	'params' => $data['params'],
	'script_inline' => $this->readJsFile('popup.mediatype.message.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
