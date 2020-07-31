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


const TABFILTERITEM_EVENT_CLICK = 'click';
const TABFILTERITEM_EVENT_COLLAPSE = 'collapse.tabfilter';
const TABFILTERITEM_EVENT_EXPAND   = 'expand.tabfilter';
const TABFILTERITEM_EVENT_EXPAND_BEFORE = 'expandbefore.tabfilter';
const TABFILTERITEM_EVENT_RENDER = 'render.tabfilter';
const TABFILTERITEM_EVENT_DELETE = 'delete.tabfilter';
const TABFILTERITEM_EVENT_URLSET = 'urlset.tabfilter';
const TABFILTERITEM_EVENT_UPDATE = 'update.tabfilter'

class CTabFilterItem extends CBaseComponent {

	constructor(target, options) {
		super(target);

		this._parent = null;
		this._idx_namespace = options.idx_namespace;
		this._index = options.index;
		this._content_container = options.container;
		this._can_toggle = options.can_toggle;
		this._data = options.data||{};
		this._template = options.template;
		this._expanded = options.expanded;
		this._support_custom_time = options.support_custom_time;
		this._template_rendered = false;

		this.init();
		this.registerEvents();
	}

	init() {
		if (this._expanded) {
			this.renderContentTemplate();

			if (this._data.filter_configurable) {
				this.addActionIcons();
			}

			this.setBrowserLocation(this.getFilterParams());
		}

		if (this._data.filter_show_counter) {
			this.setCounter('-');
		}
	}

	registerEvents() {
		this._events = {
			click: () => {
				if (this.hasClass('disabled')) {
					return;
				}

				this._target.focus();

				if (!this._expanded) {
					this.fire(TABFILTERITEM_EVENT_EXPAND_BEFORE);
					this.fire(TABFILTERITEM_EVENT_EXPAND);
				}
				else if (this._can_toggle) {
					this.fire(TABFILTERITEM_EVENT_COLLAPSE);
				}
			},

			expand: () => {
				let event_consumer = this._template||this._content_container.querySelector('[data-template]');

				this._expanded = true;
				this.addClass('active');

				if (!this._template_rendered) {
					this.renderContentTemplate();
					this._template_rendered = true;
				}
				else if (event_consumer instanceof HTMLElement) {
					event_consumer.dispatchEvent(new CustomEvent(TABFILTERITEM_EVENT_EXPAND, {detail: this}));
				}

				let search_params = this.getFilterParams();

				if (search_params) {
					this.setBrowserLocation(search_params);
				}

				this._content_container.classList.remove('display-none');

				if (this._data.filter_configurable) {
					this.addActionIcons();
				}
			},

			collapse: () => {
				let event_consumer = (this._template||this._content_container.querySelector('[data-template]'));

				this._expanded = false;
				this.removeClass('active');
				this._content_container.classList.add('display-none');

				if (event_consumer instanceof HTMLElement) {
					event_consumer.dispatchEvent(new CustomEvent(TABFILTERITEM_EVENT_COLLAPSE, {detail: this}));
				}

				this.removeActionIcons();
			}
		}

		this
			.on(TABFILTERITEM_EVENT_EXPAND, this._events.expand)
			.on(TABFILTERITEM_EVENT_COLLAPSE, this._events.collapse)
			.on(TABFILTERITEM_EVENT_CLICK, this._events.click);
	}

	setCounter(value) {
		this._target.setAttribute('data-counter', value);
	}

	removeCounter() {
		this._target.removeAttribute('data-counter');
	}

	/**
	 * Render tab template with data. Fire TABFILTERITEM_EVENT_RENDER on template container binding this as event this.
	 */
	renderContentTemplate() {
		if (this._template) {
			this._content_container.innerHTML = (new Template(this._template.innerHTML)).evaluate(this._data);
			this._template.dispatchEvent(new CustomEvent(TABFILTERITEM_EVENT_RENDER, {detail: this}));
		}
	}

	/**
	 * Open tab filter configuration poup.
	 *
	 * @param {HTMLElement} edit_elm  HTML element to broadcast popup update or delete event.
	 */
	openPropertiesForm(edit_elm) {
		PopUp('popup.tabfilter.edit', {
			idx: this._idx_namespace,
			idx2: this._index,
			filter_name: this._data.filter_name,
			filter_show_counter: this._data.filter_show_counter,
			filter_custom_time: this._data.filter_custom_time,
			tabfilter_from: this._data.from||'',
			tabfilter_to: this._data.to||'',
			support_custom_time: +this._support_custom_time
		}, 'tabfilter_dialogue', edit_elm);
	}

	/**
	 * Add gear icon and it events to tab filter this._target element.
	 */
	addActionIcons() {
		let edit = document.createElement('a');

		edit.classList.add('icon-edit');
		edit.addEventListener('click', (ev) => this.openPropertiesForm(ev.target));
		edit.addEventListener('popup.tabfilter', (ev) => {
			let data = ev.detail;

			if (data.form_action === 'update') {
				this.update(Object.assign(data, {
					from: data.tabfilter_from,
					to: data.tabfilter_to
				}));
				this.fire(TABFILTERITEM_EVENT_UPDATE);
			}
			else {
				this.delete();
			}
		});
		this._target.parentNode.appendChild(edit);
	}

	removeActionIcons() {
		let edit = this._target.parentNode.querySelector('.icon-edit');

		edit && edit.remove();
	}

	select() {
		if (!this._expanded) {
			this._events.click();
		}
	}

	delete() {
		this._content_container.remove();
		this.fire(TABFILTERITEM_EVENT_DELETE);
	}

	/**
	 * Toggle item is item selectable or not.
	 *
	 * @param {boolean} state  Selectable when true.
	 */
	setDisabled(state) {
		this.toggleClass('disabled', state);
	}

	/**
	 * Check does item have custom time interval.
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
		var form = this._content_container.querySelector('form'),
			fields = {
				filter_name: form.querySelector('[name="filter_name"]'),
				filter_show_counter: form.querySelector('[name="filter_show_counter"]'),
				filter_custom_time: form.querySelector('[name="filter_custom_time"]')
			},
			search_params = this.getFilterParams();

		if (data.filter_custom_time) {
			this._data.from = data.from;
			this._data.to = data.to;
		}

		Object.keys(fields).forEach((key) => {
			if (fields[key] instanceof HTMLElement) {
				this._data[key] = data[key];
				fields[key].value = data[key];
			}

			search_params.set(key, this._data[key]);
		});

		if (data.filter_show_counter) {
			this.setCounter('');
		}
		else {
			this.removeCounter();
		}

		this._target.text = data.filter_name;
		this.setBrowserLocation(search_params);
	}

	/**
	 * Get filter parameters as URLSearchParams object, defining value of unchecked checkboxes equal to
	 * 'unchecked-value' attribute value.
	 *
	 * @return {URLSearchParams}
	 */
	getFilterParams() {
		let form = this._content_container.querySelector('form'),
			params = null;

		if (form instanceof HTMLFormElement) {
			params = new URLSearchParams(new FormData(form));

			for (const checkbox of form.querySelectorAll('input[type="checkbox"][unchecked-value]')) {
				if (!checkbox.checked) {
					params.set(checkbox.getAttribute('name'), checkbox.getAttribute('unchecked-value'))
				}
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
}
