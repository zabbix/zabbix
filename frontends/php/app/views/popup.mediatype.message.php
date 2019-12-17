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


$form = (new CForm())
	->cleanItems()
	->setId('mediatype_message_form')
	->setName('mediatype_message_form')
	->addVar('action', 'popup.mediatype.message')
	->addVar('update', $data['update'])
	->addVar('index', $data['params']['index'])
	->addVar('type', $data['params']['type'])
	->addVar('content_type', $data['params']['content_type']);

$form_list = (new CFormList())->addRow(_('Message type'),
	new CComboBox('message_type', $data['params']['message_type'], null, CMediatypeHelper::getAllMessageTypeNames())
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
			'title' => $data['update'] ? _('Update') : _('Add'),
			'class' => 'dialogue-widget-save',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'submitMessageTemplate();'
		]
	],
	'params' => $data['params'],
	'script_inline' => require 'app/views/popup.mediatype.message.js.php'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
