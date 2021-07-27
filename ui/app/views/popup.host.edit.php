<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

$data['form_name'] = 'host-form';

if ($data['hostid'] == 0) {
	$buttons = [
		[
			'title' => _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'host_edit.submit(document.getElementById("'.$data['form_name'].'"));'
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Update'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'host_edit.submit(document.getElementById("'.$data['form_name'].'"));'
		],
		[
			'title' => _('Clone'),
			'class' => 'btn-alt',
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'return clone(overlay);'
		],
		[
			'title' => _('Full clone'),
			'class' => 'btn-alt',
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'return fullClone(overlay);'
		],
		[
			'title' => _('Delete selected host?'),
			'class' => 'btn-alt',
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'return Delete(overlay);'
		]
	];
}

$inline_js = 
	'document.getElementById("'.$data['form_name'].'").addEventListener("formSubmitted", function (event) {'.
		'let response = event.detail,'.
			'overlay = overlays_stack.end(),'.
			'$form = overlay.$dialogue.find("form");'.

		'overlay.unsetLoading();'.
		'overlay.$dialogue.find(".msg-bad, .msg-good").remove();'.

		'if ("errors" in response) {'.
			'jQuery(response.errors).insertBefore($form);'.
		'}'.
		'else if ("hostid" in response) {'.
			'clearMessages();'.
			'addMessage(makeMessageBox("good", [], response.message, true, false));'.

			'overlayDialogueDestroy(overlay.dialogueid);'.

			'const url = new Curl("zabbix.php", false);'.
			'url.setArgument("action", "host.list");'.
			'history.pushState({}, "", url.getUrl());'.

			// Todo: Call refresh on ?action=host.list page;
		'}'.
	'});';

$output = [
	'header' => ($data['hostid'] == 0) ? _('New host') : _('Host'),
	'body' => (new CPartial('configuration.host.edit.html', $data))->getOutput(),
	'script_inline' => $inline_js . getPagePostJs(),
	'buttons' => $buttons
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
