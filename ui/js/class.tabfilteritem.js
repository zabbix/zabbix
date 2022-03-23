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


const TABFILTERITEM_EVENT_COLLAPSE = 'collapse.item.tabfilter';
const TABFILTERITEM_EVENT_EXPAND = 'expand.item.tabfilter';
const TABFILTERITEM_EVENT_SELECT = 'select.item.tabfilter';
const TABFILTERITEM_EVENT_RENDER = 'render.item.tabfilter';
const TABFILTERITEM_EVENT_URLSET = 'urlset.item.tabfilter';
const TABFILTERITEM_EVENT_UPDATE = 'update.item.tabfilter';
const TABFILTERITEM_EVENT_DELETE = 'delete.item.tabfilter';
const TABFILTERITEM_EVENT_ACTION = 'action.item.tabfilter';

const TABFILTERITEM_STYLE_UNSAVED = 'unsaved';
const TABFILTERITEM_STYLE_EDIT_BTN = 'icon-edit';
const TABFILTERITEM_STYLE_SELECTED = 'selected';
const TABFILTERITEM_STYLE_EXPANDED = 'expanded';
const TABFILTERITEM_STYLE_DISABLED = 'disabled';
const TABFILTERITEM_STYLE_FOCUSED = 'focused';

class CTabFilterItem extends CBaseComponent {

	constructor(target, options) {
		super(target);

		this._parent = options.parent || null;
		this._idx_namespace = options.idx_namespace;
		this._index = options.index;
		this._content_container = options.container;
		this._data = options.data || {};
		this._template = options.template;
		this._expanded = options.expanded;
		this._support_custom_time = options.support_custom_time;
		this._template_rendered = false;
		this._src_url = null;
		this._apply_url = null;

		this.init();
		this.registerEvents();
	}

	init() {
		if (this._expanded) {
			this.renderContentTemplate();
			this.updateApplyUrl();
		}

		if (this._data.filter_show_counter) {
			this.setCounter('');
		}
	}

	/**
	 * Set results counter value.
	 *
	 * @param {int} value  Results counter value.
	 */
	setCounter(value) {
		this._target.setAttribute('data-counter', value);
	}

	/**
	 * Get results counter value.
	 */
	getCounter() {
		return this._target.getAttribute('data-counter');
	}

	/**
	 * Remove results counter value.
	 */
	removeCounter() {
		this._target.removeAttribute('data-counter');
	}

	/**
	 * Update item label counter when "show counter" is enabled otherwise will remove counter attribute.
	 *
	 * @param {string} value  Value to be shown in item label when "show counter" is enabled.
	 */
	updateCounter(value) {
		if (this._data.filter_show_counter) {
			this.setCounter(value);
		}
		else {
			this.removeCounter();
		}
	}

	/**
	 * Return item state of "show counter".
	 */
	hasCounter() {
		return parseInt(this._data.filter_show_counter) == 1;
	}

	/**
	 * Return filter form HTML element.
	 */
	getForm() {
		return this._content_container.querySelector('form');
	}

	/**
	 * Render tab template with data. Fire TABFILTERITEM_EVENT_RENDER on template container binding this as event this.
	 */
	renderContentTemplate() {
		if (this._template && !this._template_rendered) {
			this._template_rendered = true;
			this._content_container.innerHTML = (new Template(this._template.innerHTML)).evaluate(this._data);
			this._template.dispatchEvent(new CustomEvent(TABFILTERITEM_EVENT_RENDER, {detail: this}));
		}
	}

	/**
	 * Open tab filter configuration popup.
	 *
	 * @param {object} params    Object of params to be passed to ajax call when opening popup.
	 * @param {Node}   trigger_element  DOM element to broadcast popup update or delete event.
	 */
	openPropertiesDialog(params, trigger_element) {
		let defaults = {
			idx: this._idx_namespace,
			idx2: this._index,
			filter_show_counter: this._data.filter_show_counter,
			filter_custom_time: this._data.filter_custom_time,
			tabfilter_from: this._data.from || '',
			tabfilter_to: this._data.to || '',
			support_custom_time: +this._support_custom_time
		};

		if (this._data.filter_name !== '') {
			defaults.filter_name = this._data.filter_name;
		}

		this.updateUnsavedState();

		return PopUp('popup.tabfilter.edit', { ...defaults, ...params },
			{dialogueid: 'tabfilter_dialogue', trigger_element}
		);
	}

	/**
	 * Add gear icon and bind click event.
	 */
	addActionIcons() {
		if (this._target.parentNode.querySelector('.' + TABFILTERITEM_STYLE_EDIT_BTN)) {
			return;
		}

		let edit = document.createElement('a');
		edit.classList.add(TABFILTERITEM_STYLE_EDIT_BTN);
		edit.addEventListener('click', () => this.openPropertiesDialog({}, this._target));
		this._target.parentNode.appendChild(edit);
	}

	/**
	 * Remove gear icon HTMLElement.
	 */
	removeActionIcons() {
		let icon = this._target.parentNode.querySelector('.' + TABFILTERITEM_STYLE_EDIT_BTN);

		if (icon) {
			icon.remove();
		}
	}

	/**
	 * Get selected state of item.
	 *
	 * @return {boolean}
	 */
	isSelected() {
		return this._target.parentNode.classList.contains(TABFILTERITEM_STYLE_SELECTED);
	}

	/**
	 * Set browser focus to filter label element.
	 */
	setFocused() {
		this._target.focus();
	}

	/**
	 * Set selected state of item.
	 */
	setSelected() {
		this._target.parentNode.classList.add(TABFILTERITEM_STYLE_SELECTED);
		this.renderContentTemplate();

		if (this._data.filter_configurable) {
			this.addActionIcons();
		}

		if (this._template) {
			this._template.dispatchEvent(new CustomEvent(TABFILTERITEM_EVENT_SELECT, {detail: this}));
		}
	}

	/**
	 * Remove selected state of item.
	 */
	removeSelected() {
		this._target.parentNode.classList.remove(TABFILTERITEM_STYLE_SELECTED);

		if (this._data.filter_configurable) {
			this.removeActionIcons();
		}
	}

	/**
	 * Set expanded state of item and it content container, render content from template if it was not rendered yet.
	 * Fire TABFILTERITEM_EVENT_EXPAND event on template.
	 */
	setExpanded() {
		let item_template = this._template || this._content_container.querySelector('[data-template]');

		this._target.parentNode.classList.add(TABFILTERITEM_STYLE_EXPANDED);

		if (item_template instanceof HTMLElement && !this._expanded) {
			item_template.dispatchEvent(new CustomEvent(TABFILTERITEM_EVENT_EXPAND, {detail: this}));
		}

		this._expanded = true;
		this._content_container.classList.remove('display-none');
	}

	/**
	 * Remove expanded state of item and it content. Fire TABFILTERITEM_EVENT_COLLAPSE on item template.
	 */
	removeExpanded() {
		let item_template = this._template || this._content_container.querySelector('[data-template]');

		this._expanded = false;
		this._target.parentNode.classList.remove(TABFILTERITEM_STYLE_EXPANDED);
		this._content_container.classList.add('display-none');

		if (item_template instanceof HTMLElement) {
			item_template.dispatchEvent(new CustomEvent(TABFILTERITEM_EVENT_COLLAPSE, {detail: this}));
		}
	}

	/**
	 * Delete item, clean up all related HTMLElement nodes.
	 */
	delete() {
		this._target.parentNode.remove();
		this._content_container.remove();
	}

	/**
	 * Toggle item is item selectable or not.
	 *
	 * @param {boolean} state  Selectable when true.
	 */
	setDisabled(state) {
		this.toggleClass(TABFILTERITEM_STYLE_DISABLED, state);
		this._target.parentNode.classList.toggle(TABFILTERITEM_STYLE_DISABLED, state);
	}

	/**
	 * Check if item have custom time interval.
	 *
	 * @return {boolean}
	 */
	hasCustomTime() {
		return !!this._data.filter_custom_time;
	}

	/**
	 * Update tab filter configuration: name, show_counter, custom_time. Set browser URL according new values.
	 *
	 * @param {object} data  Updated tab properties object.
	 */
	update(data) {
		var form = this.getForm(),
			fields = {
				filter_name: form.querySelector('[name="filter_name"]'),
				filter_show_counter: form.querySelector('[name="filter_show_counter"]'),
				filter_custom_time: form.querySelector('[name="filter_custom_time"]')
			};

		if (data.filter_custom_time) {
			this._data.from = data.from;
			this._data.to = data.to;
		}

		Object.keys(fields).forEach((key) => {
			this._data[key] = data[key];

			if (fields[key] instanceof HTMLElement) {
				fields[key].value = data[key];
			}
		});

		if (data.filter_show_counter) {
			this.setCounter('');
		}
		else {
			this.removeCounter();
		}

		if (!this._unsaved) {
			this.updateApplyUrl();
		}

		this._target.text = data.filter_name;
		this.setBrowserLocationToApplyUrl();
	}

	/**
	 * Get filter parameters as URLSearchParams object, defining value of unchecked checkboxes equal to
	 * 'unchecked-value' attribute value.
	 *
	 * @return {URLSearchParams}
	 */
	getFilterParams() {
		let form = this.getForm(),
			params = null;

		const TAG_OPERATOR_EXISTS = '4';
		const TAG_OPERATOR_NOT_EXISTS = '5';

		if (form instanceof HTMLFormElement) {
			var form_data = new FormData(form);

			// Unset tag filter values for exists/not exists tag filters.
			for (const [key, value] of form_data.entries()) {
				let check_tag = key.match(/tags\[(\d+)\]\[operator\]/);
				if (check_tag && (value === TAG_OPERATOR_EXISTS || value === TAG_OPERATOR_NOT_EXISTS)) {
					form_data.set('tags['+check_tag[1]+'][value]', '');
				}
			}

			params = new URLSearchParams(form_data);

			for (const checkbox of form.querySelectorAll('input[type="checkbox"][unchecked-value]')) {
				if (!checkbox.checked) {
					params.set(checkbox.getAttribute('name'), checkbox.getAttribute('unchecked-value'));
				}
			}

			if (this._data.filter_custom_time) {
				params.set('from', this._data.from);
				params.set('to', this._data.to);
			}

			if ('page' in this._data && this._data.page > 1) {
				params.set('page', this._data.page);
			}
		}

		return params;
	}

	/**
	 * Set browser location URL according to passed values. Argument 'action' from already set URL is preserved.
	 * Create TABFILTER_EVENT_URLSET event with detail.target equal instance of CTabFilter.
	 *
	 * @param {URLSearchParams} search_params  Filter field values to be set in URL.
	 */
	setBrowserLocation(search_params) {
		let url = new Curl('', false);

		search_params.set('action', url.getArgument('action'));
		url.query = search_params.toString();
		url.formatArguments();
		history.replaceState(history.state, '', url.getUrl());
		this.fire(TABFILTERITEM_EVENT_URLSET);
	}

	/**
	 * Set argument to URL used for data pooling (_apply_url) and URL used to track unsaved changes (_src_url).
	 * Allow to change argument without affecting "unsaved" state of filter.
	 *
	 * @param {string} name   Argument name.
	 * @param {string} value  Argument value.
	 */
	setUrlArgument(name, value) {
		let apply_url = new URLSearchParams(this._apply_url || (this.getFilterParams()).toString()),
			src_url = new URLSearchParams(this._src_url);

		apply_url.set(name, value);
		src_url.set(name, value);
		this._apply_url = apply_url.toString();
		this._src_url = src_url.toString();
	}

	/**
	 * Keep filter tab results request parameters.
	 */
	updateApplyUrl() {
		this._apply_url = (this.getFilterParams()).toString();
	}

	/**
	 * Request filter results for fields defined before last 'Apply' being used.
	 */
	setBrowserLocationToApplyUrl() {
		if (this._apply_url === null) {
			this.updateApplyUrl();
		}

		this.setBrowserLocation(new URLSearchParams(this._apply_url));
	}

	/**
	 * Checks difference between original form values and to be posted values.
	 * Updates this._unsaved according to check results
	 *
	 * @param {URLSearchParams} search_params  Filter field values to compare against.
	 */
	updateUnsavedState() {
		let search_params = this.getFilterParams(),
			src_query = new URLSearchParams(this._src_url),
			ignore_fields = ['filter_name', 'filter_custom_time', 'filter_show_counter', 'from', 'to', 'action', 'page'];

		if (search_params === null || !this._data.filter_configurable) {
			// Not templated tabs does not contain form fields, no need to update unsaved state.
			return;
		}

		for (const field of ignore_fields) {
			src_query.delete(field);
			search_params.delete(field);
		}

		src_query.sort();
		search_params.sort();
		this._unsaved = (src_query.toString() !== search_params.toString());
		this._target.parentNode.classList.toggle(TABFILTERITEM_STYLE_UNSAVED, this._unsaved);
	}

	/**
	 * Reset item unsaved state. Set this._src_url to filter parameters.
	 */
	resetUnsavedState() {
		let src_query = this.getFilterParams();

		if (src_query ===  null) {
			return;
		}

		if (src_query.get('filter_custom_time') !== '1') {
			src_query.delete('from');
			src_query.delete('to');
		}

		src_query.delete('action');
		src_query.sort();

		this._src_url = src_query.toString();
		this._target.parentNode.classList.remove(TABFILTERITEM_STYLE_UNSAVED);
	}

	/**
	 * Initialize _src_url property from item rendered form fields values.
	 */
	initUnsavedState() {
		if (this._src_url === null) {
			this.resetUnsavedState();
		}
	}

	/**
	 * Unset selected subfilters.
	 */
	emptySubfilter() {
		[...this.getForm().elements]
			.filter(el => el.name.substr(0, 10) === 'subfilter_')
			.forEach(el => el.remove());
	}

	/**
	 * Shorthand function to check if subfilter has given value.
	 *
	 * @param {string} key   Subfilter parameter name.
	 * @param {string} value Subfilter parameter value.
	 *
	 * @return {bool}
	 */
	hasSubfilter(key, value) {
		return Boolean([...this.getForm().elements].filter(el => (el.name === key && el.value === value)).length);
	}

	/**
	 * Set new subfilter field.
	 *
	 * @param {string} key    Subfilter parameter name.
	 * @param {string} value  Subfilter parameter value.
	 */
	setSubfilter(key, value) {
		value = String(value);

		if (!this.hasSubfilter(key, value)) {
			const el = document.createElement('input');
			el.type = 'hidden';
			el.name = key;
			el.value = value;
			this.getForm().appendChild(el);
		}
	}

	/**
	 * Remove some of existing subfilter field.
	 *
	 * @param {string} key    Subfilter parameter name.
	 * @param {string} value  Subfilter parameter value.
	 */
	unsetSubfilter(key, value) {
		value = String(value);

		if (this.hasSubfilter(key, value)) {
			[...this.getForm().elements]
				.filter(el => (el.name === key && el.value === value))
				.forEach(el => el.remove());
		}
	}

	registerEvents() {
		this._events = {
			click: () => {
				if (this.hasClass(TABFILTERITEM_STYLE_DISABLED)) {
					return;
				}

				this.setFocused();
				this.fire(TABFILTERITEM_EVENT_SELECT);
			},

			expand: () => {
				this.setExpanded();

				if (this._src_url === null) {
					this.resetUnsavedState();
				}
			},

			collapse: () => {
				this.removeExpanded();
			},

			focusin: () => {
				this._target.parentNode.classList.add(TABFILTERITEM_STYLE_FOCUSED);
			},

			focusout: () => {
				this._target.parentNode.classList.remove(TABFILTERITEM_STYLE_FOCUSED);
			}
		}

		this
			.on(TABFILTERITEM_EVENT_EXPAND, this._events.expand)
			.on(TABFILTERITEM_EVENT_COLLAPSE, this._events.collapse)
			.on('focusin', this._events.focusin)
			.on('focusout', this._events.focusout)
			.on('click', this._events.click);
	}
}
