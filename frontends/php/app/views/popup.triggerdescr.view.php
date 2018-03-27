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


$form = (new CForm())
	->setName('trigger_description')
	->addVar('triggerid', $data['trigger']['triggerid'])
	->addVar('comments_unresolved', $data['trigger']['comments'])
	->addItem(array_key_exists('messages', $data) ? $data['messages'] : null)
	->addItem(
		(new CFormList(_('Description')))->addRow(
			_('Description'),
			(new CTextArea('comments', $data['resolved'], ['rows' => 25, 'readonly' => $data['isCommentExist']]))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAttribute('autofocus', 'autofocus')
		));

$script_inline = '';

$buttons = [
	[
		'title' => _('Update'),
		'class' => 'trigger-descr-update-btn',
		'keepOpen' => true,
		'isSubmit' => false,
		'enabled' => !$data['isCommentExist'],
		'action' => 'jQuery("form[name='.$form->getName().']").trigger("submit");'
	]
];

if ($data['isTriggerEditable']) {
	$script_inline .=
		'jQuery(document).ready(function() {'.
			'jQuery("form[name='.$form->getName().']").submit(function(e) {'.
				'e.preventDefault();'.
				'var forms = jQuery(this);'.
				'jQuery.ajax({'.
					'url: "zabbix.php?action=triggerdesc.update",'.
					'data: {'.
						'"triggerid": '.$data['trigger']['triggerid'].','.
						'"comments": jQuery([name=comments], forms).val(),'.
						'"sid": jQuery([name=sid], forms).val()'.
					'},'.
					'success: function(r) {'.
						'if (typeof r.errors === "undefined") {'.
							'jQuery(forms).append(jQuery("<input>", {type: "hidden", "name": "success"}).val(1));'.
							'reloadPopup(forms[0], "popup.triggerdesc.view");'.
						'}'.
						'else {'.
							'var dialogue_body = jQuery(forms).closest(".overlay-dialogue-body"),'.
								'msg = jQuery(".msg-bad,.msg-good", dialogue_body);'.
							'(msg.length === 0)'.
								'? jQuery(dialogue_body).prepend(r.errors)'.
								': jQuery(msg).replaceWith(r.errors);'.
						'}'.
					'},'.
					'dataType: "json",'.
					'type: "post"'.
				'});'.
			'});'.
		'});';
}

if ($data['isCommentExist'] && $data['isTriggerEditable']) {
	$script_inline .=
		'function makeCommentEditable() {'.
			'var forms = jQuery("form[name='.$form->getName().']");'.
			'jQuery("[name=comments]", forms)'.
				'.text(jQuery("[name=comments_unresolved]", forms).val())'.
				'.removeProp("readonly")'.
				'.focus();'.
			'jQuery(".trigger-descr-update-btn").removeProp("disabled");'.
			'jQuery(".trigger-descr-edit-btn").attr("disabled", "disabled");'.
		'}';
}

if ($data['isCommentExist']) {
	$buttons[] = [
		'title' => _('Edit'),
		'class' => 'btn-alt trigger-descr-edit-btn',
		'keepOpen' => true,
		'enabled' => $data['isTriggerEditable'],
		'action' => $data['isTriggerEditable']
			? 'makeCommentEditable();'
			: 'return false;'
	];
}

echo (new CJson())->encode([
	'header' => $data['title'],
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => $script_inline
]);
