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

$form_items = [$data['messages']];

if ($data['success']) {
	$row_decription = [
		(new CTextArea('', $data['output']))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->addClass('monospace-font')
			->addClass('active-readonly')
			->setReadonly(true)
	];

	if ($data['type'] == ZBX_SCRIPT_TYPE_WEBHOOK) {
		$row_decription[] = new CVar('debug', json_encode($data['debug']));
		$row_decription[] = new CDiv(
			(new CLinkAction(_('Open log')))
				->setId('script_execution_log')
				->addClass($data['debug'] ? '' : ZBX_STYLE_DISABLED)
		);
	}

	$form_items[] = (new CFormList())->addRow(
		new CLabel($data['type'] == ZBX_SCRIPT_TYPE_WEBHOOK ? _('Response') : _('Output')),
		$row_decription
	);
}

$form = (new CForm())->addItem($form_items);

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
