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


class CWidgetFieldMultiselect {

	static #reference_icon_template = `
		<li class="reference">
			<span class="${ZBX_ICON_REFERENCE}" data-hintbox="1" data-hintbox-contents="#{hint_text}"></span>
		</li>
	`;

	/**
	 * Multiselect jQuery element.
	 *
	 * @type {Object}
	 */
	#multiselect;

	/**
	 * @type {HTMLUListElement}
	 */
	#multiselect_list;

	/**
	 * @type {Object}
	 */
	#multiselect_params;

	/**
	 * Field name.
	 *
	 * @type {string}
	 */
	#field_name;

	/**
	 * Data type accepted from referred data sources.
	 *
	 * @type {string}
	 */
	#in_type;

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

	/**
	 * Field labels for single and multiple objects.
	 */
	#labels;

	/**
	 * @type {int}
	 */
	#selected_limit = 0;

	/**
	 * @type {boolean}
	 */
	#is_multiple = true;

	/**
	 * @type {boolean}
	 */
	#is_disabled = false;

	/**
	 * @type {boolean}
	 */
	#is_selecting_typed_reference = false;

	/**
	 * @type {boolean}
	 */
	#is_selected_typed_reference = false;

	constructor({
		multiselect_id,
		field_name,
		field_value,
		in_type,
		default_prevented = false,
		widget_accepted = false,
		dashboard_accepted = false,
		object_labels,
		params
	}) {
		this.#field_name = field_name;
		this.#labels = object_labels;
		this.#in_type = in_type;
		this.#default_prevented = default_prevented;
		this.#widget_accepted = widget_accepted;
		this.#dashboard_accepted = dashboard_accepted;
		this.#multiselect_params = params;

		if ('selectedLimit' in params) {
			this.#selected_limit = params.selectedLimit;
			this.#is_multiple = this.#selected_limit != 1;
		}

		this.#initField(multiselect_id);

		if (CWidgetBase.FOREIGN_REFERENCE_KEY in field_value) {
			this.#selectTypedReference(field_value[CWidgetBase.FOREIGN_REFERENCE_KEY]);
		}
	}

	get disabled() {
		return this.#is_disabled;
	}

	set disabled(is_disabled) {
		this.#is_disabled = is_disabled;

		if (!this.#is_disabled) {
			this.#multiselect.multiSelect('enable');
		}
		else {
			this.#multiselect.multiSelect('disable');
		}
	}

	#initField(multiselect_id) {
		const has_optional_sources = this.#widget_accepted && (!this.#default_prevented || this.#dashboard_accepted);

		const $multiselect = jQuery(`#${multiselect_id}`);

		$multiselect[0].dataset.params = JSON.stringify(this.#multiselect_params);

		this.#multiselect = $multiselect.multiSelect();

		if (has_optional_sources) {
			this.#multiselect
				.multiSelect('setSuggestListModifier', (entities) => this.#modifySuggestedList(entities));

			this.#multiselect
				.multiSelect('customSuggestSelectHandler', (entity) => this.#selectSuggested(entity));
		}

		this.#multiselect
			.on('before-add', () => {
				if (this.#is_selecting_typed_reference !== this.#is_selected_typed_reference) {
					for (const item of this.#multiselect.multiSelect('getData')) {
						this.#multiselect.multiSelect('removeSelected', item.id);
					}

					if (this.#is_selecting_typed_reference) {
						this.#multiselect.multiSelect('modify', {
							name: `${this.#field_name}[${CWidgetBase.FOREIGN_REFERENCE_KEY}]`,
							selectedLimit: 1
						});
					}
					else {
						this.#multiselect.multiSelect('modify', {
							name: `${this.#field_name}${this.#is_multiple ? '[]' : ''}`,
							selectedLimit: this.#selected_limit
						});
					}

					this.#is_selected_typed_reference = this.#is_selecting_typed_reference;
				}

				this.#is_selecting_typed_reference = false;
			})
			.on('before-remove', () => {
				if (this.#is_selected_typed_reference) {
					this.#multiselect_list.innerHTML = '';
				}
			});

		this.#multiselect_list = this.#multiselect[0].querySelector('.multiselect-list');

		const select_button = this.#multiselect.multiSelect('getSelectButton');

		if (select_button !== null) {
			$(select_button).off('click');
			select_button.addEventListener('click', (e) => {
				if (!this.#default_prevented) {
					this.#selectDefaultPopup(e);
				}
				else if(this.#widget_accepted) {
					this.#selectWidgetPopup(e);
				}
			});
		}

		if (has_optional_sources) {
			if (!this.#default_prevented) {
				this.#multiselect.multiSelect('addOptionalSelect',
					this.#is_multiple ? this.#labels.objects : this.#labels.object,
					(e) => this.#selectDefaultPopup(e)
				);
			}

			this.#multiselect.multiSelect('addOptionalSelect', t('Widget'), (e) => {
				this.#selectWidgetPopup(e);
			});

			if (this.#dashboard_accepted) {
				this.#multiselect.multiSelect('addOptionalSelect', t('Dashboard'), () => {
					this.#selectTypedReference(
						CWidgetBase.createTypedReference({
							reference: CDashboard.REFERENCE_DASHBOARD,
							type: this.#in_type
						})
					);
				});
			}
		}
	}

	#selectDefaultPopup(e) {
		this.#multiselect.multiSelect('openSelectPopup', e.target);
	}

	#selectWidgetPopup() {
		const popup = new CWidgetSelectPopup(this.#getWidgets());

		popup.on('dialogue.submit', (e) => {
			this.#selectTypedReference(e.detail.reference);
		});
	}

	#selectTypedReference(typed_reference) {
		const typed_reference_dashboard = CWidgetBase.createTypedReference({
			reference: CDashboard.REFERENCE_DASHBOARD,
			type: this.#in_type
		});

		let caption = null;
		let hint_text = null;

		if (typed_reference === typed_reference_dashboard) {
			caption = {id: typed_reference_dashboard, name: t('Dashboard')}
			hint_text = t('Dashboard is used as data source.');
		}
		else if (typed_reference === '') {
			caption = {
				id: '',
				name: t('Unavailable widget'),
				inaccessible: true
			};
			hint_text = t('Another widget is used as data source.');
		}
		else {
			for (const widget of this.#getWidgets()) {
				if (widget.id === typed_reference) {
					caption = widget;
					hint_text = t('Another widget is used as data source.');
					break;
				}
			}
		}

		if (caption !== null) {
			this.#is_selecting_typed_reference = true;
			this.#multiselect.multiSelect('addData', [caption]);

			if (hint_text !== null) {
				const reference_icon = new Template(CWidgetFieldMultiselect.#reference_icon_template)
					.evaluateToElement({hint_text});

				this.#multiselect_list.prepend(reference_icon);
			}
		}
	}

	#selectSuggested(entity) {
		if (entity.source !== undefined) {
			this.#selectTypedReference(entity.id);
		}
		else {
			this.#multiselect.multiSelect('addData', [entity]);
		}
	}

	#modifySuggestedList(entities) {
		const search = this.#multiselect.multiSelect('getSearch').toLowerCase();

		const result_entities = new Map();

		if (this.#dashboard_accepted && t('Dashboard').toLowerCase().includes(search)) {
			const id = CWidgetBase.createTypedReference({
				reference: CDashboard.REFERENCE_DASHBOARD,
				type: this.#in_type
			});

			result_entities.set(id, {
				id,
				name: t('Dashboard'),
				source: 'dashboard'
			})
		}

		if (this.#widget_accepted) {
			const widgets = [];
			for (const widget of this.#getWidgets()) {
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

	#getWidgets() {
		const widgets = ZABBIX.Dashboard.getReferableWidgets({
			type: this.#in_type,
			widget_context: ZABBIX.Dashboard.getEditingWidgetContext()
		});

		widgets.sort((a, b) => a.getHeaderName().localeCompare(b.getHeaderName()));

		const result = [];

		for (const widget of widgets) {
			result.push({
				id: CWidgetBase.createTypedReference({reference: widget.getFields().reference, type: this.#in_type}),
				name: widget.getHeaderName()
			});
		}

		return result;
	}
}
