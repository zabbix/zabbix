<?php
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
 */

$form_items = [$data['messages']];

if ($data['success']) {
	$row_description = [
		(new CTextArea('', $data['output']))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->addClass('monospace-font')
			->addClass('active-readonly')
			->disableSpellcheck()
			->setId('execution-output')
			->setReadonly(true)
	];

	if ($data['type'] == ZBX_SCRIPT_TYPE_WEBHOOK) {
		$row_description[] = new CVar('debug', json_encode($data['debug']));
		$row_description[] = new CDiv(
			(new CLinkAction(_('Open log')))
				->setId('script_execution_log')
				->addClass($data['debug'] ? '' : ZBX_STYLE_DISABLED)
		);
	}

	$form_items[] = (new CFormList())->addRow(
		new CLabel($data['type'] == ZBX_SCRIPT_TYPE_WEBHOOK ? _('Response') : _('Output')),
		$row_description
	);
}

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('scriptexec')))->removeId())
	->addItem($form_items);

$output = [
	'header' => $data['title'],
	'script_inline' => $this->readJsFile('popup.scriptexec.js.php'),
	'body' => $form->toString(),
	'buttons' => null
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
