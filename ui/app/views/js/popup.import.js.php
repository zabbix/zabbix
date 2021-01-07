<?php
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
?>

function confirmSubmit(overlay) {
	if (document.querySelectorAll('.deleteMissing:checked').length === 0) {
		return submitPopup(overlay);
	}
	else {
		overlayDialogue({
			'content': jQuery('<span>').text(<?= json_encode(_('Delete all elements that are not present in the import file?')) ?>),
			'buttons': [
				{
					'title': <?= json_encode(_('OK')) ?>,
					'focused': true,
					'action': function() {
						return submitPopup(overlay);
					}
				},
				{
					'title': <?= json_encode(_('Cancel')) ?>,
					'cancel': true,
					'class': '<?= ZBX_STYLE_BTN_ALT ?>',
					'action': function() {
						overlay.unsetLoading();
						return true;
					}
				}
			]
		}, overlay);
	}
}

function submitPopup(overlay) {
	const form = document.querySelector('#import-form');
	const file = form.querySelector('#import_file');
	const formData = new FormData();

	// Remove error message.
	overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

	// Append import file.
	formData.append('import_file', file.files.length ? file.files[0] : '');

	// Append all checkboxes to form.
	[...form.querySelectorAll('input[type=checkbox]:checked, input[type=hidden]')].map(
		(elem) => formData.append(elem.name, elem.value)
	);

	url = new Curl('zabbix.php', false),
	url.setArgument('action', 'popup.import');
	url.setArgument('output', 'ajax');

	fetch(url.getUrl(), {
		method: 'post',
		body: formData
	})
	.then((response) => response.json())
	.then((response) => {
		if ('errors' in response) {
			overlay.unsetLoading();
			$(response.errors).insertBefore(form);
			form.querySelector('#import_file').value = '';
		}
		else {
			postMessageOk(response['title']);
			if ('messages' in response) {
				postMessageDetails('success', response.messages);
			}
			overlayDialogueDestroy(overlay.dialogueid);
			location.href = location.href;
		}
	});
}

function updateWarning(obj, content) {
	if (jQuery(obj).is(':checked')) {
		overlayDialogue({
			'content': jQuery('<span>').text(content),
			'buttons': [
				{
					'title': <?= json_encode(_('OK')) ?>,
					'focused': true,
					'action': function() {}
				},
				{
					'title': <?= json_encode(_('Cancel')) ?>,
					'cancel': true,
					'class': '<?= ZBX_STYLE_BTN_ALT ?>',
					'action': function() {
						jQuery(obj).prop('checked', false);
					}
				}
			]
		}, obj);
	}
}
