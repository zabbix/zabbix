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
	 * Field labels for single and multiple objects.
	 */
	#labels;

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

	/**
	 * @type {boolean}
	 */
	#default_prevented;

	/**
	 * @type {boolean}
	 */
	#widget_accepted;

	/**
	 * @type {boolean}
	 */
	#dashboard_accepted;

	constructor(element, multiselect_params, {
		field_name,
		field_value,
		object_labels,
		default_prevented,
		widget_accepted,
		dashboard_accepted
	}) {
		this.#name = field_name;
		this.#labels = object_labels;
		this.#default_prevented = default_prevented;
		this.#widget_accepted = widget_accepted;
		this.#dashboard_accepted = dashboard_accepted;

		const has_optional_sources = widget_accepted && (!default_prevented || dashboard_accepted);

		this.#multiselect = jQuery(element).multiSelect({
			...multiselect_params,
			suggest_list_modifier: has_optional_sources ? (entities) => this.#modifySuggestedList(entities) : null,
			custom_suggest_select_handler: has_optional_sources ? (entity) => this.#selectSuggested(entity) : null
		});

		this.#selected_limit = this.#multiselect.multiSelect('getOption', 'selectedLimit');
		this.#is_multiple = this.#selected_limit != 1;

		const select_button = this.#multiselect.multiSelect('getSelectButton');

		if (select_button !== null) {
			$(select_button).off('click');
			select_button.addEventListener('click', (e) => {
				if (!default_prevented) {
					this.#selectDefaultPopup(e);
				}
				else if(widget_accepted) {
					this.#selectWidgetPopup(e);
				}
			});
		}

		if (has_optional_sources) {
			if (!default_prevented) {
				this.#multiselect.multiSelect('addOptionalSelect',
					this.#is_multiple ? object_labels.objects : object_labels.object,
					(e) => this.#selectDefaultPopup(e)
				);
			}

			this.#multiselect.multiSelect('addOptionalSelect', t('Widget'), (e) => {
				this.#selectWidgetPopup(e);
			});

			if (dashboard_accepted) {
				this.#multiselect.multiSelect('addOptionalSelect', t('Dashboard'), () => {
					this.#selectReference('DASHBOARD');
				});
			}
		}

		if ('reference' in field_value) {
			this.#selectReference(field_value.reference);
		}
	}

	#selectDefaultPopup(e) {
		this.#multiselect.multiSelect('modify', {
			name: `${this.#name}${this.#is_multiple ? '[]' : ''}`,
			selectedLimit: this.#selected_limit
		});

		if (this.#selected_reference !== null) {
			this.#multiselect.multiSelect('removeSelected', this.#selected_reference);
			this.#selected_reference = null;
		}

		this.#multiselect.multiSelect('openSelectPopup', e.target);
	}

	#selectWidgetPopup(e) {
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

			this.#multiselect.multiSelect('addData', [caption]);
		}
	}

	#selectSuggested(entity) {
		console.log(entity)
		console.log(entity.source)
		if (entity.source !== undefined) {
			this.#selectReference(entity.id);
		}
		else {
			this.#multiselect.multiSelect('modify', {
				name: `${this.#name}${this.#is_multiple ? '[]' : ''}`,
				selectedLimit: this.#selected_limit
			});

			if (this.#selected_reference !== null) {
				this.#multiselect.multiSelect('removeSelected', this.#selected_reference);
				this.#selected_reference = null;
			}

			this.#multiselect.multiSelect('addData', [entity]);
		}
	}

	#modifySuggestedList(entities) {
		const search = this.#multiselect.multiSelect('getSearch');

		const result_entities = new Map();

		if (this.#dashboard_accepted && t('Dashboard').toLowerCase().includes(search)) {
			result_entities.set('DASHBOARD', {id: 'DASHBOARD', name: t('Dashboard'), source: 'dashboard'})
		}

		if (this.#widget_accepted) {
			const widgets = [];
			for (const widget of this.DASHBOARD_getWidgets()) {
				if (widget.name.toLowerCase().includes(search)) {
					widgets.push({...widget, source: 'widget'});
				}
			}

			if (widgets.length > 0) {
				result_entities.set('widgets', {group_label: t('Widgets')});
				for (const widget of widgets) {
					result_entities.set(widget.id, widget);
				}
			}
		}

		if (!this.#default_prevented && entities.size > 0) {
			result_entities.set('entities', {group_label: this.#labels.objects});

			for (const [id, entity] of entities.entries()) {
				result_entities.set(id, entity);
			}
		}

		return result_entities;
	}


	DASHBOARD_getWidgets() {
		return [
			{id: 'DD56KD', prefix: 'Page1'+': ', name: 'URL'},
			{id: 'AFG87E', prefix: 'Page1'+': ', name: 'Problems'},
			{id: '67AFCB', prefix: 'Page2'+': ', name: 'Problems'}
		];
	}
}
