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

	init({tag_filters}) {
		this.tag_filter_template = new Template(document.getElementById('tag-filter-row-template').innerHTML);
		this.tag_filter_counter = 0;

		if (tag_filters.length !== 0 && tag_filters[0]['tag'] !== '') {
			const tag_list_option = document.querySelector(`input[name="filter_type"][value='<?= TAG_FILTER_LIST ?>']`);
			tag_list_option.checked = true;

			for (const tag of tag_filters) {
				this.#addTagFilterRow(tag);
			}
		}

		this.#toggleTagList();

		document.querySelectorAll('[name=filter_type]').forEach((type) => {
			type.addEventListener('change', () =>
				this.#toggleTagList()
			);
		});

		document.querySelector('.js-add-tag-filter-row').addEventListener('click', () => this.#addTagFilterRow());

		document.getElementById('tag-filter-add-form').addEventListener('click', event => {
			if (event.target.classList.contains('js-remove-table-row')) {
				event.target.closest('tr').remove();
			}
		});

		const multiselect = document.getElementById('ms_new_tag_filter_groupids_');
		jQuery(multiselect).multiSelect(jQuery(multiselect).data('params'));
	}

	#addTagFilterRow(tag = []) {
		const rowid = this.tag_filter_counter++;
		const data = {
			'rowid': rowid,
			'tag': tag.length !== 0 ? tag.tag : '',
			'value': tag.length !== 0 ? tag.value : ''
		};

		const new_row = this.tag_filter_template.evaluate(data);

		const placeholder_row = document.querySelector('.js-tag-filter-row-placeholder');
		placeholder_row.insertAdjacentHTML('beforebegin', new_row);
	}

	#toggleTagList() {
		const tag_list_radio = document.querySelector('[name="filter_type"]:checked').value;
		const tags = document.getElementById('tag-list-form-field');
		const tags_label = document.querySelector("label[for='tag_filters']");
		const show_tags = tag_list_radio == '<?= TAG_FILTER_LIST?>';

		tags.style.display = show_tags ? '' : 'none';
		tags_label.style.display = show_tags ? '' : 'none';
	}
}
