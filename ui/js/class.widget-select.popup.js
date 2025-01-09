/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CWidgetSelectPopup {

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
		<tr>
			<td>${t('No compatible widgets.')}</td>
		</tr>
	`;

	/**
	 * @type {Overlay}
	 */
	#overlay;

	constructor(widgets) {
		const widgets_table = new Template(CWidgetSelectPopup.#table_template).evaluateToElement();

		let rows_html = '';

		if (widgets.length > 0) {
			const widget_row = new Template(CWidgetSelectPopup.#row_template);

			for (const widget of widgets) {
				rows_html += widget_row.evaluate(widget);
			}
		}
		else {
			widgets_table.classList.add(ZBX_STYLE_NO_DATA);
			rows_html = CWidgetSelectPopup.#nothing_to_show_template;
		}

		widgets_table.querySelector('tbody').innerHTML = rows_html;

		widgets_table.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-select-reference')) {
				overlayDialogueDestroy(this.#overlay.dialogueid);
				this.fire('dialogue.submit', {
					name: e.target.textContent,
					reference: e.target.dataset.reference
				});
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
	 * @returns {CWidgetSelectPopup}
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
	 * @returns {CWidgetSelectPopup}
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
