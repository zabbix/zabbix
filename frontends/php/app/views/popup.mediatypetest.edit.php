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


$form_list = (new CFormList())
	->addRow(
		(new CLabel(_('Send to'), 'sendto'))->setAsteriskMark(),
		(new CTextBox('sendto', $data['sendto'], false, 1024))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAttribute('autofocus', 'autofocus')
			->setAriaRequired()
			->setEnabled($data['enabled'])
	)
	->addRow(
		new CLabel(_('Subject'), 'subject'),
		(new CTextBox('subject', $data['subject'], false, 1024))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setEnabled($data['enabled'])
	)
	->addRow(
		(new CLabel(_('Message'), 'message'))->setAsteriskMark($data['type'] != MEDIA_TYPE_EXEC),
		(new CTextArea('message', $data['message'], ['rows' => 10]))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAriaRequired($data['type'] != MEDIA_TYPE_EXEC)
			->setEnabled($data['enabled'])
	);

$form = (new CForm())
	->cleanItems()
	->setName('mediatypetest_form')
	->addVar('action', 'popup.mediatypetest.send')
	->addVar('mediatypeid', $data['mediatypeid'])
	->addItem([
		$form_list,
		(new CInput('submit', 'submit'))->addStyle('display: none;')
	]);

$output = [
	'header' => $data['title'],
	'script_inline' => require 'app/views/popup.mediatypetest.edit.js.php',
	'body' => (new CDiv([$data['errors'], $form]))->toString(),
	'buttons' => [
		[
			'title' => _('Test'),
			'class' => 'submit-test-btn',
			'keepOpen' => true,
			'isSubmit' => true,
			'enabled' => $data['enabled'],
			'action' => 'mediatypeTestSend("'.$form->getName().'");'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
