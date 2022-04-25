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
$(() => {
	let VALUEMAP_MAPPING_TYPE_DEFAULT = <?= VALUEMAP_MAPPING_TYPE_DEFAULT ?>;
	let type_placeholder = <?= json_encode([
		VALUEMAP_MAPPING_TYPE_EQUAL => _('value'),
		VALUEMAP_MAPPING_TYPE_GREATER_EQUAL => _('value'),
		VALUEMAP_MAPPING_TYPE_LESS_EQUAL => _('value'),
		VALUEMAP_MAPPING_TYPE_IN_RANGE => _('value'),
		VALUEMAP_MAPPING_TYPE_REGEXP => _('regexp')
	]) ?>;
	let table = document.querySelector('#mappings_table');
	let observer = new MutationObserver(mutationHandler);

	// Observe changes for form fields: type, value.
	observer.observe(table, {
		childList: true,
		subtree: true,
		attributes: true,
		attributeFilter: ['value']
	});
	updateOnTypeChange();

	function updateOnTypeChange() {
		let default_select = table.querySelector(`z-select[value="${VALUEMAP_MAPPING_TYPE_DEFAULT}"]`);

		table.querySelectorAll('tr').forEach((row) => {
			let zselect = row.querySelector('z-select[name$="[type]"]');
			let input = row.querySelector('input[name$="[value]"]');

			if (zselect) {
				zselect.getOptionByValue(VALUEMAP_MAPPING_TYPE_DEFAULT).disabled = (default_select
					&& zselect !== default_select
				);
				input.classList.toggle('visibility-hidden', (zselect === default_select));
				input.disabled = (zselect === default_select);
				input.setAttribute('placeholder', type_placeholder[zselect.value]||'');
			}
		});
	}

	function mutationHandler(mutation_records, observer) {
		let update = mutation_records.filter((mutation) => {
			return (mutation.target.tagName === 'INPUT' && mutation.target.getAttribute('name').substr(-6) === '[type]')
				|| (mutation.target.tagName === 'TBODY' && mutation.removedNodes.length > 0);
		});

		if (update.length) {
			updateOnTypeChange();
		}
	}
});

function submitValueMap(overlay) {
	const form = overlay.$dialogue.$body[0].querySelector('form');

	jQuery(form).trimValues(['input[type="text"]']);
	const curl = new Curl(form.getAttribute('action'));

	fetch(curl.getUrl(), {
		method: 'POST',
		body: new URLSearchParams(new FormData(form))
	})
		.then(response => response.json())
		.then(response => {
			if ('error' in response) {
				throw {error: response.error};
			}

			new AddValueMap(response, response.edit ? overlay.element.closest('tr') : null);

			overlayDialogueDestroy(overlay.dialogueid);
		})
		.catch((exception) => {
			for (const element of form.parentNode.children) {
				if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
					element.parentNode.removeChild(element);
				}
			}

			let title, messages;

			if (typeof exception === 'object' && 'error' in exception) {
				title = exception.error.title;
				messages = exception.error.messages;
			}
			else {
				messages = [<?= json_encode(_('Unexpected server error.')) ?>];
			}

			const message_box = makeMessageBox('bad', messages, title)[0];

			form.parentNode.insertBefore(message_box, form);
		})
		.finally(() => {
			overlay.unsetLoading();
		});
}
