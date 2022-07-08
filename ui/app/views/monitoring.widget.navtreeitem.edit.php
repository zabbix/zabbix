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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 */

$form = (new CForm('post'))
	->cleanItems()
	->setId('widget-dialogue-form')
	->setName('widget_dialogue_form')
	->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'));

$form_list = (new CFormList())
	->addRow(
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['name']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAttribute('autofocus', 'autofocus')
			->setAriaRequired()
	)
	->addRow(_('Linked map'), [
		new CVar('sysmapid', $data['sysmap']['sysmapid']),
		(new CTextBox('sysmapname', $data['sysmap']['name'], true))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton('select', _('Select')))->addClass(ZBX_STYLE_BTN_GREY)
	]);

if ($data['depth'] >= WIDGET_NAVIGATION_TREE_MAX_DEPTH) {
	$form_list->addRow(null, _('Cannot add submaps. Max depth reached.'));
}
else {
	$form_list->addRow(null, [
		new CCheckBox('add_submaps', 1),
		new CLabel(_('Add submaps'), 'add_submaps')
	]);
}

$form
	->addItem($form_list)
	->addItem((new CScriptTag('navtreeitem_edit_popup.init();'))->setOnDocumentReady());

$output = [
	'body' => $form->toString(),
	'script_inline' => $this->readJsFile('monitoring.widget.navtreeitem.edit.js.php')
];

if ($messages = get_and_clear_messages()) {
	$output['messages'] = array_column($messages, 'message');
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
