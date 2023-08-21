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

class ClassWidgetSelectPopup {

	static #table_template = `
		<table class="${ZBX_STYLE_LIST_TABLE}">
			<thead>
				<tr>
					<th>${t('Name')}</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	`;

	static #row_template = `
		<tr>
			<td>
				<a class="js-select-reference" data-reference="#{id}" role="button" href="javascript:void(0)">#{name}</a>
			</td>
		</tr>
	`;

	static #nothing_to_show_template = `
		<tr class="${ZBX_STYLE_NOTHING_TO_SHOW}">
			<td>${t('No compatible widgets.')}</td>
		</tr>
	`;

	/**
	 * @type {Overlay}
	 */
	#overlay;

	constructor(widgets) {
		const widgets_table = new Template(ClassWidgetSelectPopup.#table_template).evaluateToElement();

		let rows_html = '';

		if (widgets.length > 0) {
			const widget_row = new Template(ClassWidgetSelectPopup.#row_template);

			for (const widget of widgets) {
				rows_html += widget_row.evaluate(widget);
			}
		}
		else {
			rows_html = ClassWidgetSelectPopup.#nothing_to_show_template;
		}

		widgets_table.querySelector('tbody').innerHTML = rows_html;

		widgets_table.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-select-reference')) {
				overlayDialogueDestroy(this.#overlay.dialogueid);
				this.fire('dialogue.submit', {reference: e.target.dataset.reference});
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
			element: document.activeElement ?? undefined
		});
	}

	/**
	 * Attach event listener to events.
	 *
	 * @param {string}        type
	 * @param {function}      listener
	 * @param {Object|false}  options
	 *
	 * @returns {ClassWidgetSelectPopup}
	 */
	on(type, listener, options = false) {
		this.#overlay.$dialogue[0].addEventListener(type, listener, options);

		return this;
	}

	/**
	 * Detach event listener from events.
	 *
	 * @param {string}        type
	 * @param {function}      listener
	 * @param {Object|false}  options
	 *
	 * @returns {ClassWidgetSelectPopup}
	 */
	off(type, listener, options = false) {
		this.#overlay.$dialogue[0].removeEventListener(type, listener, options);

		return this;
	}

	/**
	 * Dispatch event.
	 *
	 * @param {string}  type
	 * @param {Object}  detail
	 * @param {Object}  options
	 *
	 * @returns {boolean}
	 */
	fire(type, detail = {}, options = {}) {
		return this.#overlay.$dialogue[0].dispatchEvent(
			new CustomEvent(type, {...options, detail: {target: this, ...detail}})
		);
	}
}
