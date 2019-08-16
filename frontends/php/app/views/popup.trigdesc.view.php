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
	->setName('trigger_description')
	->addVar('triggerid', $data['trigger']['triggerid'])
	->addVar('comments_unresolved', $data['trigger']['comments'])
	->addItem(array_key_exists('messages', $data) ? $data['messages'] : null)
	->addItem(
		(new CFormList(_('Description')))->addRow(
			_('Description'),
			(new CTextArea('comments', $data['resolved'],
				['rows' => 25, 'readonly' => ($data['isTriggerEditable'] ? $data['isCommentExist'] : true)]
			))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAttribute('autofocus', 'autofocus')
		));

if (array_key_exists('eventid', $data)) {
	$form->addVar('eventid', $data['eventid']);
}

$script_inline = '';

if ($data['isTriggerEditable']) {
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

	$script_inline .=
		'jQuery(document).ready(function() {'.
			'jQuery("form[name='.$form->getName().']").submit(function(e) {'.
				'e.preventDefault();'.
				'var forms = jQuery(this);'.
				'jQuery.ajax({'.
					'url: "zabbix.php?action=trigdesc.update",'.
					'data: {'.
						'"triggerid": '.$data['trigger']['triggerid'].','.
						(array_key_exists('eventid', $data) ? '"eventid": '.$data['eventid'].',' : '').
						'"comments": jQuery("[name=comments]", forms).val(),'.
						'"sid": jQuery("[name=sid]", forms).val()'.
					'},'.
					'success: function(r) {'.
						'if (typeof r.errors === "undefined") {'.
							/**
							 * Before reloadPopup call:
							 * - add input[name=success][value=1] to tell "popup.trigdesc.view" display success message;
							 * - remove [name=comments] and [name=comments_unresolved] to avoid unneeded data transfer.
							 */
							'jQuery(forms).append(jQuery("<input>", {type: "hidden", "name": "success"}).val(1));'.
							'jQuery("[name=comments], [name=comments_unresolved]", jQuery(forms)).remove();'.
							'reloadPopup(forms[0], "popup.trigdesc.view");'.
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
				'.prop("readonly", false)'.
				'.focus();'.
			'jQuery(".trigger-descr-update-btn").prop("disabled", false);'.
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

$output = [
	'header' => $data['title'],
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => $script_inline
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
