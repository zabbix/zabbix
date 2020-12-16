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
	$('#mappings_table').dynamicRows({template: '#mapping-row-tmpl'});
});

function submitValueMap(overlay) {
	const form = document.querySelector('#valuemap-edit-form');
	const is_edit = !!form.querySelector('#edit');
	const name = form.querySelector('#name').value;
	const source_name = form.querySelector('#source-name').value;
	// const id = form.querySelector('#name').value;
	const names = [...document
			.querySelector('#valuemap-table')
			.querySelectorAll("input[name$='[name]'")
		].map((elem) => elem.value);

	overlay.$dialogue.find('.msg-bad, .msg-good').remove();

	if (name !== source_name) {
		if (names.includes(name)) {
			document
				.querySelector(`.overlay-dialogue[data-dialogueid='${overlay.dialogueid}']`)
				.querySelector('.overlay-dialogue-body')
				.prepend(makeMessageBox('bad', 'name is not unique', 'test title', true, true)[0]); // FIXME: add error message
			overlay.unsetLoading();
			return false;
		}
	}

	if (name === '') {
		document
			.querySelector(`.overlay-dialogue[data-dialogueid='${overlay.dialogueid}']`)
			.querySelector('.overlay-dialogue-body')
			.prepend(makeMessageBox('bad', 'name is empty', 'test title', true, true)[0]); // FIXME: add error message
		overlay.unsetLoading();
		return false;
	}

	const data = {name: name, mappings: []};

	if (form.querySelectorAll('[id$="_key"]').length === 0 || (form.querySelector('[id$="_key"]').value === ''
				|| form.querySelector('[id$="_value"]').value === '')) {
		document
			.querySelector(`.overlay-dialogue[data-dialogueid='${overlay.dialogueid}']`)
			.querySelector('.overlay-dialogue-body')
			.prepend(makeMessageBox('bad', 'need one mapping', 'test title', true, true)[0]); // FIXME: add error message
		overlay.unsetLoading();
		return false;
	}

	[...form.querySelectorAll('[id$="_key"]')].map(
		(elem) => {
			if (elem.value === '') {
				return false;
			}

			const key = elem.id.split('_')[1];
			data.mappings.push({
				key: elem.value,
				value: form.querySelector(`#mappings_${key}_value`).value
			});
		}
	);

	overlayDialogueDestroy(overlay.dialogueid);

	if (is_edit) {
		return new AddValueMap(data, document.querySelector(`[name$='[name]'][value=${JSON.stringify(source_name)}]`).closest('tr'));
	}

	return new AddValueMap(data);
}
