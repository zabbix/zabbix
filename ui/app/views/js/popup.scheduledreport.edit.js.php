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
?>

function submitScheduledReport(overlay) {
	const $form = overlay.$dialogue.find('form');
	const url = new Curl($form.attr('action'));

	$form.trimValues(['#name', '#subject', '#message', '#description']);

	fetch(url.getUrl(), {
		method: 'POST',
		body: new URLSearchParams(new FormData($form.get(0)))
	})
		.then(response => response.json())
		.then(response => {
			if ('error' in response) {
				throw {error: response.error};
			}

			postMessageOk(response.success.title);

			if ('messages' in response.success) {
				postMessageDetails('success', response.success.messages);
			}

			overlayDialogueDestroy(overlay.dialogueid);

			location.href = location.href;
		})
		.catch((exception) => {
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

			message_box.insertBefore($form);
		})
		.finally(() => {
			overlay.unsetLoading();
		});
}
