<?php declare(strict_types = 1);
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
window.widget_tophostsdata_form = {

	init(options) {
		let form = document.querySelector('#'+options.form_id);

		this.initSortable(form.querySelector('#list_columns'));
		form.querySelectorAll('[name="order"]').forEach(checkbox => {
			checkbox.addEventListener('change', () => ZABBIX.Dashboard.reloadWidgetProperties());
		});
		form.addEventListener('click', this.processColumnsAction);

		// Modal triggers 'data.ready' event via jQuery.
		$(form).on('data.ready', (e, data) => {
			// data - added/updated data object, if data.edit is set data is updated.
			let thresholds = data.thresholds;
			let input = document.createElement('input');
			let tr = e.target.closest('tr');
			let index;

			input.setAttribute('type', 'hidden');
			form.querySelectorAll(`[name^="columns[${index}]["]`).forEach(element => element.remove());

			if (data.edit) {
				index = tr.querySelector('[name="sortorder[columns][]"]').value;
				delete data.edit;
			}
			else {
				index = tr.closest('table').querySelectorAll('tr').length;
				input.setAttribute('name', `sortorder[columns][]`);
				input.setAttribute('value', index);
				form.appendChild(input.cloneNode());
			}

			if (data.thresholds) {
				for (let [key, value] of Object.entries(data.thresholds)) {
					input.setAttribute('name', `columns[${index}][thresholds][${key}][color]`);
					input.setAttribute('value', value.color);
					form.appendChild(input.cloneNode());
					input.setAttribute('name', `columns[${index}][thresholds][${key}][threshold]`);
					input.setAttribute('value', value.threshold);
					form.appendChild(input.cloneNode());
				}

				delete data.thresholds;
			}

			for (let [key, value] of Object.entries(data)) {
				input.setAttribute('name', `columns[${index}][${key}]`);
				input.setAttribute('value', value);
				form.appendChild(input.cloneNode());
			}

			ZABBIX.Dashboard.reloadWidgetProperties();
		});
	},

	initSortable(element) {
		$(element).sortable({
			items: 'tbody tr.sortable',
			axis: 'y',
			containment: 'parent',
			cursor: 'grabbing',
			handle: 'div.drag-icon',
			tolerance: 'pointer',
			opacity: 0.6,
			helper: function(e, ui) {
				for (let td of ui.find('>td')) {
					let $td = $(td);
					$td.attr('width', $td.width())
				}

				// when dragging element on safari, it jumps out of the table
				if (SF) {
					// move back draggable element to proper position
					ui.css('left', (ui.offset().left - 2) + 'px');
				}

				return ui;
			},
			stop: function(e, ui) {
				ui.item.find('>td').removeAttr('width');
				ui.item.removeAttr('style');
			},
			start: function(e, ui) {
				$(ui.placeholder).height($(ui.helper).height());
			}
		});
	},

	processColumnsAction(e) {
		let elm = e.srcElement;

		switch (elm.getAttribute('name')) {
			case 'edit':
				let index = 0;
				let form_fields = getFormFields(elm.closest('form'));

				PopUp('popup.widget.columnlist.edit', {...form_fields.columns[index], edit: 1}, null, e.target);
				break;

			case 'add':
				PopUp('popup.widget.columnlist.edit', {}, null, e.target);
				break;

			case 'remove':
				e.srcElement.closest('tr').remove();
				ZABBIX.Dashboard.reloadWidgetProperties();
				break;
		}
	}
};
