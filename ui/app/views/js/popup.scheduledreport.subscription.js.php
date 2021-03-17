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

function submitScheduledReportSubscription(overlay) {
	var $form = overlay.$dialogue.find('form');
	var url = new Curl($form.attr('action'));

	fetch(url.getUrl(), {
		method: 'POST',
		body: new URLSearchParams(new FormData($form.get(0)))
	})
		.then(response => response.json())
		.then(response => {
			overlay.$dialogue.find('.msg-bad, .msg-good').remove();

			if (response.errors) {
				document
					.querySelector(`.overlay-dialogue[data-dialogueid='${overlay.dialogueid}'] .overlay-dialogue-body`)
					.prepend($(response.errors).get(0));
				overlay.unsetLoading();

				return;
			}

			new ReportSubscription(response, response.edit ? overlay.element.closest('tr') : null);
			overlayDialogueDestroy(overlay.dialogueid);
		})
		.catch((e) => {
			document
				.querySelector(`.overlay-dialogue[data-dialogueid='${overlay.dialogueid}'] .overlay-dialogue-body`)
				.prepend(makeMessageBox('bad', e, null)[0]);
			overlay.unsetLoading();
		});
}
