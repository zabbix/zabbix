<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

window.tag_filter_popup = new class {

	init({}) {
		this.tag_filter_template = new Template(document.getElementById('tag-filter-row-template').innerHTML);
		this.tag_filter_counter = 0;

		this.#addTagFilterRow();

		document.querySelector('.js-add-tag-filter-row').addEventListener('click', () => this.#addTagFilterRow());

		document.getElementById('tag-filter-add-form').addEventListener('click', event => {
			if (event.target.classList.contains('js-remove-table-row')) {
				event.target.closest('tr').remove();
			}
		});

		const multiselect = document.getElementById('ms_new_tag_filter_groupids_');

		jQuery(multiselect).multiSelect(jQuery(multiselect).data('params'));
	}

	#addTagFilterRow(tag_filter_group = []) {
		const rowid = this.tag_filter_counter++;
		const data = {
			'rowid': rowid
		};

		const new_row = this.tag_filter_template.evaluate(data);

		const placeholder_row = document.querySelector('.js-tag-filter-row-placeholder');
		placeholder_row.insertAdjacentHTML('beforebegin', new_row);

		if (tag_filter_group.length > 0) {
			const tag_id = 'tag_filter_tag_'+rowid;
			document.getElementById(tag_id).value = tag_filter_group[0]['tag'];

			const value_id = 'tag_filter_value_'+rowid;
			document.getElementById(value_id).value = tag_filter_group[0]['value'];
		}
	}
}
