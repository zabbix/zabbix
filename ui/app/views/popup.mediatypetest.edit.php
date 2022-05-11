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

$form_list = (new CFormList());

if ($data['type'] == MEDIA_TYPE_WEBHOOK) {
	$i = 0;

	foreach ($data['parameters'] as $parameter) {
		$fieldid = 'parameters['.$i.']';
		$form_list
			->addRow(new CLabel($parameter['name'], $fieldid.'[value]'), [
				new CVar($fieldid.'[name]', $parameter['name']),
				(new CTextBox($fieldid.'[value]', $parameter['value']))->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			]);
		$i++;
	}

	if (!$i) {
		$form_list->addRow(_('Webhook does not have parameters.'));
	}

	$form_list
		->addRow(new CLabel(_('Response')), [
			(new CTextArea(''))
				->setId('webhook_response_value')
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setEnabled(false),
			(new CDiv(''))->setId('webhook_response_type'),
			(new CDiv((new CLinkAction('Open log'))
				->setId('mediatypetest_log')
				->addClass(ZBX_STYLE_DISABLED)
				->onClick('openLogPopup(this)')))
		]);
}
else {
	$form_list
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
}

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
	'script_inline' => $this->readJsFile('popup.mediatypetest.edit.js.php'),
	'body' => (new CDiv([$data['errors'], $form]))->toString(),
	'buttons' => [
		[
			'title' => _('Test'),
			'keepOpen' => true,
			'isSubmit' => true,
			'enabled' => $data['enabled'],
			'action' => 'return mediatypeTestSend(overlay);'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
