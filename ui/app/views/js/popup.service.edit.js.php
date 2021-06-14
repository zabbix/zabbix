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

// Initialize form tabs.
$('#tabs').tabs();

$('#tabs').on('tabsactivate', () => {
	$('#tabs').resize();
});

$('#showsla').on('change', function() {
	$('#goodsla').prop('disabled', !this.checked);
});

$('#algorithm').on('change', function() {
	const status_disabled = ($(this).val() == <?= SERVICE_ALGORITHM_NONE ?>);

	$('#showsla, #goodsla').prop('disabled', status_disabled);

	if (!status_disabled) {
		$('#showsla').trigger('change');
		$('#triggerid').multiSelect('enable');
	}
	else {
		$('#triggerid').multiSelect('disable');
	}
}).trigger('change');

/**
 * @param {Overlay} overlay
 */
function submitService(overlay) {
	const $form = overlay.$dialogue.find('form');
	const url = new Curl($form.attr('action'));

	$form.trimValues(['#name']);

	overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

	fetch(url.getUrl(), {
		method: 'POST',
		body: new URLSearchParams(new FormData($form.get(0)))
	})
		.then(response => response.json())
		.then(response => {


			if ('errors' in response) {
				overlay.unsetLoading();
				$(response.errors).insertBefore($form);
			}
			else {
				postMessageOk(response['title']);
				if ('messages' in response) {
					postMessageDetails('success', response.messages);
				}
				overlayDialogueDestroy(overlay.dialogueid);
				location.href = location.href;
			}
		})
		.catch((e) => {
			document
				.querySelector(`.overlay-dialogue[data-dialogueid='${overlay.dialogueid}'] .overlay-dialogue-body`)
				.prepend(makeMessageBox('bad', e, null)[0]);
			overlay.unsetLoading();
		});
}
