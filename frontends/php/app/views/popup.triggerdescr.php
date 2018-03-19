<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


$resolved_description = CMacrosResolverHelper::resolveTriggerDescription($data['trigger']);

$form = (new CForm())
	->setName('trigger_description')
	->addVar('action', 'popup.triggerdesc')
	->addVar('save', '1')
	->addVar('triggerid', $data['trigger']['triggerid'])
	->addVar('comments_unresolved', $data['trigger']['comments'])
	->addItem(array_key_exists('messages', $data) ? $data['messages'] : null)
	->addItem(
		(new CFormList(_('Description')))->addRow(
			_('Description'),
			(new CTextArea('comments', $resolved_description, ['rows' => 25, 'readonly' => $data['isCommentExist']]))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAttribute('autofocus', 'autofocus')
		));

$buttons = [
	[
		'title' => _('Update'),
		'class' => 'trigger-descr-update-btn',
		'keepOpen' => true,
		'isSubmit' => true,
		'enabled' => !$data['isCommentExist'],
		'action' => 'return reloadPopup(jQuery("form[name='.$form->getName().']").get(0), "popup.triggerdesc");'
	]
];

if ($data['isCommentExist']) {
	$buttons[] = [
		'title' => _('Edit'),
		'class' => 'btn-alt trigger-descr-edit-btn',
		'keepOpen' => true,
		'enabled' => $data['isCommentExist'],
		'action' => 'return makeCommentEditable("'.$form->getName().'");'
	];
}

echo (new CJson())->encode([
	'header' => $data['title'],
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' =>
		'function makeCommentEditable(form_name) {'.
			'var form = jQuery("form[name="+form_name+"]");'.
			'jQuery("[name=comments]", form)'.
				'.text(jQuery("[name=comments_unresolved]", form).val())'.
				'.removeProp("readonly")'.
				'.focus();'.
			'jQuery(".trigger-descr-update-btn").removeProp("disabled");'.
			'jQuery(".trigger-descr-edit-btn").attr("disabled", "disabled");'.
		'}'
]);
