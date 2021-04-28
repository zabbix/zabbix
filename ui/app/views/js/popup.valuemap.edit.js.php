<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
$(() => {
	let VALUEMAP_MAPPING_TYPE_DEFAULT = 5;
	let table = document.querySelector('#mappings_table');
	let observer = new MutationObserver(mutationHandler);

	observer.observe(table, {
		childList: true,
		subtree: true,
		attributes: true,
		attributeFilter: ['value']
	});
	updateOnTypeChange();

	function updateOnTypeChange() {
		let default_select = table.querySelector(`z-select[value="${VALUEMAP_MAPPING_TYPE_DEFAULT}"]`);
		let have_default_type = !!default_select;
		let table_row = have_default_type ? default_select.closest('tr') : null;
		let value_input = have_default_type ? table_row.querySelector('input[name$="[value]"]') : null;

		table.querySelectorAll('z-select[name$="[type]"]').forEach((zselect) => {
			if (zselect.closest('tr') !== table_row) {
				zselect.getOptionByValue(VALUEMAP_MAPPING_TYPE_DEFAULT).disabled = have_default_type;
			}
		});
		table.querySelectorAll('input[name$="[value]"]').forEach((input) => {
			input.classList.toggle('visibility-hidden', have_default_type && input.closest('tr') === table_row);
			input.disabled = (have_default_type && input.closest('tr') === table_row);
		});
	}

	function mutationHandler(mutation_records, observer) {
		mutation_records.forEach((mutation) => {
			if (mutation.target.tagName === 'INPUT' && mutation.target.getAttribute('name').substr(-6) === '[type]') {
				updateOnTypeChange();
			}
			else if (mutation.type === 'childList' && mutation.removedNodes.length > 0) {
				updateOnTypeChange();
			}
		});
	}
});

function submitValueMap(overlay) {
	var $form = overlay.$dialogue.find('form'),
		url = new Curl($form.attr('action'));

	$form.trimValues(['input[type="text"]']);

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

			new AddValueMap(response, response.edit ? overlay.element.closest('tr') : null);
			overlayDialogueDestroy(overlay.dialogueid);
		})
		.catch((e) => {
			document
				.querySelector(`.overlay-dialogue[data-dialogueid='${overlay.dialogueid}'] .overlay-dialogue-body`)
				.prepend(makeMessageBox('bad', e, null)[0]);
			overlay.unsetLoading();
		});

	return;
}
