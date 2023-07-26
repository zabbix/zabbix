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

class CWidgetFieldMultiselect {

	/**
	 * Multiselect jQuery element.
	 *
	 * @type {Object}
	 */
	#multiselect;

	/**
	 * @type {Overlay}
	 */
	#overlay;

	/**
	 * Field name.
	 *
	 * @type {string}
	 */
	#name;

	/**
	 * @type {string|null}
	 */
	#selected_reference = null;

	/**
	 * @type {boolean}
	 */
	#is_multiple;

	/**
	 * @type {int}
	 */
	#selected_limit;

	constructor(element, multiselect_params, {
		field_name,
		field_value,
		object_label,
		default_prevented,
		widget_accepted,
		dashboard_accepted
	}) {
		this.#name = field_name;
		this.#multiselect = jQuery(element).multiSelect(multiselect_params);
		this.#selected_limit = this.#multiselect.multiSelect('getOption', 'selectedLimit');
		this.#is_multiple = this.#selected_limit != 1;

		const select_button = this.#multiselect.multiSelect('getSelectButton');

		if (select_button !== null) {
			$(select_button).off('click');
			select_button.addEventListener('click', (e) => {
				if (!default_prevented) {
					this.#selectEntity(e);
				}
				else if(widget_accepted) {
					this.#selectWidget(e);
				}
			});
		}

		if (widget_accepted) {
			if (!default_prevented || dashboard_accepted) {
				if (!default_prevented) {
					this.#multiselect.multiSelect('addOptionalSelect', object_label, (e) => this.#selectEntity(e));
				}

				this.#multiselect.multiSelect('addOptionalSelect', t('Widget'), (e) => this.#selectWidget(e));

				if (dashboard_accepted) {
					this.#multiselect.multiSelect('addOptionalSelect', t('Dashboard'), (e) => this.#selectDashboard(e));
				}
			}
		}

		if ('reference' in field_value) {
			this.#selectReference(field_value.reference);
		}
	}

	#selectEntity(e) {
		this.#multiselect.multiSelect('modify', {
			name: `${this.#name}${this.#is_multiple ? '[]' : ''}`,
			selectedLimit: this.#selected_limit
		});

		if (this.#selected_reference !== null) {
			this.#multiselect.multiSelect('removeSelected', this.#selected_reference);
		}

		this.#multiselect.multiSelect('openSelectPopup', e.target);
	}

	#selectWidget(e) {
		const widgets_table = new Template(
			document.getElementById(`${this.#name}-reference-table-tmpl`).innerHTML
		).evaluateToElement();

		const widgets = this.DASHBOARD_getWidgets();

		let rows_html = '';

		if (widgets.length > 0) {
			const widget_row = new Template(document.getElementById(`${this.#name}-reference-row-tmpl`).innerHTML);

			for (const widget of widgets) {
				rows_html += widget_row.evaluate(widget);
			}
		}
		else {
			rows_html = document.getElementById(`${this.#name}-reference-empty-tmpl`).innerHTML;
		}

		widgets_table.querySelector('tbody').innerHTML = rows_html;

		widgets_table.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-select-reference')) {
				this.#selectReference(e.target.dataset.reference);

				overlayDialogueDestroy(this.#overlay.dialogueid);
			}
		});

		this.#overlay = overlayDialogue({
			title: t('Widget'),
			class: 'modal-popup modal-popup-medium',
			content: widgets_table,
			buttons: [{
				title: t('Cancel'),
				cancel: true,
				class: ZBX_STYLE_BTN_ALT,
				action: () => {}
			}],
			element: e.target
		});
	}

	#selectDashboard() {
		this.#selectReference('DASHBOARD');
	}

	#selectReference(reference) {
		let caption = null;

		if (reference === 'DASHBOARD') {
			caption = {id: 'DASHBOARD', name: t('Dashboard')}
		}
		else {
			for (const widget of this.DASHBOARD_getWidgets()) {
				if (widget.id === reference) {
					caption = widget
					break;
				}
			}
		}

		if (caption !== null) {
			this.#multiselect.multiSelect('modify', {
				name: `${this.#name}[reference]`,
				selectedLimit: 1
			});

			this.#selected_reference = reference;

			this.#multiselect.multiSelect('addData', [caption], true);
		}
	}

	DASHBOARD_getWidgets() {
		return [
			{id: 'DD56KD', prefix: 'Page1'+': ', name: 'URL'},
			{id: 'AFG87E', prefix: 'Page1'+': ', name: 'Problems'},
			{id: '67AFCB', prefix: 'Page2'+': ', name: 'Problems'}
		];
	}
}
