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

	$('#trigger, #trigger-btn, #showsla, #goodsla').prop('disabled', status_disabled);

	if (!status_disabled) {
		$('#showsla').trigger('change');
	}
}).trigger('change');

var counter = 0;
var service_time_tmpl = new Template(document.querySelector('#service-time-row-tmpl').innerHTML);

var ServiceTime = class {

	constructor(data, old_row = null) {
		data.counter = counter++;
		this.data = data;
		this.old_row = old_row;

		this.render();
	}

	render() {
		this.prepareNewRow();

		if (this.old_row instanceof Element) {
			return this.old_row.replaceWith(this.new_row);
		}

		return document
			.querySelector('#times-table tbody')
			.append(this.new_row);
	}

	prepareNewRow() {
		const row = document.createElement('tr');

		row.innerHTML = service_time_tmpl.evaluate(this.data);
		row.querySelector('.js-edit-service-time').addEventListener('click', (e) => {
			const popup_options = {
				edit: 1,
				type: row.querySelector('input[name*=type]').value,
				ts_from: row.querySelector('input[name*=ts_from]').value,
				ts_to: row.querySelector('input[name*=ts_to]').value,
				ts_note: row.querySelector('input[name*=note]').value
			};
			PopUp('popup.service.time.edit', popup_options, null, e.target);
		});
		row.querySelector('.js-remove-service-time').addEventListener('click', () => {
			row.remove();
		});

		this.new_row = row;
	}
}

document.querySelector('#times-table .js-add-service-time').addEventListener('click', (e) => {
	PopUp('popup.service.time.edit', {}, null, e.target);
});

var service_times = <?= json_encode(array_values($data['times'])) ?>;
service_times.forEach((service_time) => new ServiceTime(service_time));

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
