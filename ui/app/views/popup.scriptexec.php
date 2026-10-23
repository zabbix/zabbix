<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

$form_items = $data['is_ping'] ? [] : [$data['messages']];

if ($data['is_ping']) {
	$form_items[] = (new CDiv())->setId('ping-result')->setAttribute('role', 'status')
		->setAttribute('data-host', $data['ping_host'])
		->setAttribute('data-ip', $data['ping_ip'])
		->setAttribute('data-output', $data['output'])
		->setAttribute('data-success', $data['success'] ? '1' : '0');
}


if ($data['success'] && !$data['is_ping']) {
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
	'script_inline' => $this->readJsFile('popup.scriptexec.js.php')
		. ($data['is_ping'] ? file_get_contents(__DIR__.'/../../js/ping-result.js') : ''),
	'body' => $form->toString(),
	'buttons' => null
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
