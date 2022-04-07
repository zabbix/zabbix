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
?>

function submitPopup(overlay) {
	if (document.getElementById('rules_preset').value === "template") {
		return openImportComparePopup(overlay);
	}
	else {
		if (isDeleteMissingChecked(overlay)) {
			return confirmSubmit(overlay);
		}

		return submitImportPopup(overlay);
	}
}

function isDeleteMissingChecked(import_overlay) {
	return import_overlay.$dialogue.get(0).querySelectorAll('.deleteMissing:checked').length > 0;
}

function confirmSubmit(import_overlay, compare_overlay) {
	overlayDialogue({
		class: 'position-middle',
		content: jQuery('<span>')
					.text(<?= json_encode(_('Delete all elements that are not present in the import file?')) ?>),
		buttons: [
			{
				title: <?= json_encode(_('OK')) ?>,
				focused: true,
				action: function() {
					if (compare_overlay !== undefined) {
						overlayDialogueDestroy(compare_overlay.dialogueid);
					}
					return submitImportPopup(import_overlay);
				}
			},
			{
				title: <?= json_encode(_('Cancel')) ?>,
				cancel: true,
				class: '<?= ZBX_STYLE_BTN_ALT ?>',
				action: function() {
					(compare_overlay || import_overlay).unsetLoading();
					return true;
				}
			}
		]
	}, (compare_overlay || import_overlay).$btn_submit);
}

function openImportComparePopup(overlay) {
	const form = document.getElementById('import-form');

	const url = new Curl('zabbix.php', false);
	url.setArgument('action', 'popup.import.compare');
	url.setArgument('import_overlayid', overlay.dialogueid);

	overlay.setLoading();

	fetch(url.getUrl(), {
		method: 'post',
		body: new FormData(form)
	})
		.then((response) => response.json())
		.then((response) => {
			if ('error' in response) {
				throw {error: response.error};
			}

			overlayDialogue({
				title: response.header,
				class: response.no_changes ? 'position-middle' : 'modal-popup modal-popup-fullscreen',
				content: response.body,
				buttons: response.buttons,
				script_inline: response.script_inline,
				debug: response.debug
			}, overlay.$btn_submit);
		})
		.catch((exception) => {
			document.getElementById('import_file').value = '';

			overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

			let title, messages;

			if (typeof exception === 'object' && 'error' in exception) {
				title = exception.error.title;
				messages = exception.error.messages;
			}
			else {
				messages = [<?= json_encode(_('Unexpected server error.')) ?>];
			}

			const message_box = makeMessageBox('bad', messages, title);

			message_box.insertBefore(form);
		})
		.finally(() => {
			overlay.unsetLoading();
		});
}

function submitImportPopup(overlay) {
	const form = document.getElementById('import-form');

	const url = new Curl('zabbix.php', false);
	url.setArgument('action', 'popup.import');

	overlay.setLoading();

	fetch(url.getUrl(), {
		method: 'post',
		body: new FormData(form)
	})
		.then((response) => response.json())
		.then((response) => {
			if ('error' in response) {
				throw {error: response.error};
			}

			postMessageOk(response.success.title);

			if ('messages' in response.success) {
				postMessageDetails('success', response.success.messages);
			}

			overlayDialogueDestroy(overlay.dialogueid);

			location.href = location.href.split('#')[0];
		})
		.catch((exception) => {
			document.getElementById('import_file').value = '';

			overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

			let title, messages;

			if (typeof exception === 'object' && 'error' in exception) {
				title = exception.error.title;
				messages = exception.error.messages;
			}
			else {
				messages = [<?= json_encode(_('Unexpected server error.')) ?>];
			}

			const message_box = makeMessageBox('bad', messages, title);

			message_box.insertBefore(form);
		})
		.finally(() => {
			overlay.unsetLoading();
		});
}

function updateWarning(obj, content) {
	if (jQuery(obj).is(':checked')) {
		overlayDialogue({
			class: 'position-middle',
			content: jQuery('<span>').text(content),
			buttons: [
				{
					title: <?= json_encode(_('OK')) ?>,
					focused: true,
					action: function() {}
				},
				{
					title: <?= json_encode(_('Cancel')) ?>,
					cancel: true,
					class: '<?= ZBX_STYLE_BTN_ALT ?>',
					action: function() {
						jQuery(obj).prop('checked', false);
					}
				}
			]
		}, obj);
	}
}
