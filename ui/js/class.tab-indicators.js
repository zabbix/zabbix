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


'use strict';

// templatesForm
// hostsForm
// hostPrototypeForm
// itemForm
// itemPrototypeForm
// triggersForm
// triggersPrototypeForm
// hostDiscoveryForm
// httpForm
// action.edit
// servicesForm
// proxyForm
// authenticationForm
// userGroupForm
// userForm ???
// media_type_form
// widget_dialogue_form ???
// mapEditForm
// userForm

// Add mutationObserve

class TabIndicators {

	constructor() {
		try {
			this.form = this.getForm();
			this.activateIndicators();
		} catch (error) {
			return false;
		}
	}

	getForm() {
		const TEMPLATE = document.querySelector('#templatesForm');

		switch (true) {
			case !!TEMPLATE:
				return TEMPLATE;
			default:
				throw 'Form not found.';
		}
	}

	activateIndicators() {
		const tabs = this.form.querySelectorAll('#tabs a');

		Object.values(tabs).map((value) => {
			this.addAttribute(value);
		});
	}

	addAttribute(elem) {
		const callback_name = elem.getAttribute('js-indicator')?.split('-')?.map((value) => value[0].toUpperCase() + value.slice(1))?.join('');
		const callback = TabIndicatorFactory.createTabIndicator(callback_name);

		const type = callback?.getType();

		if (type instanceof TabIndicatorStatus) {
			elem.setAttribute('data-indicator-status', callback.event() ? 'enabled' : 'disabled');
		}

		if (type instanceof TabIndicatorNumber) {
			elem.setAttribute('data-indicator-count', callback.event());

			setInterval(() => {
				elem.setAttribute('data-indicator-count', callback.event());
			}, 5000);
		}
	}
}

class TabIndicatorFactory {

	static createTabIndicator(name) {
		switch (name) {
			case 'Macros':
				return new MacrosTabIndicator;
			case 'LinkedTemplate':
				return new LinkedTemplateTabIndicator;
			case 'Tags':
				return new TagsTabIndicator;
		}

		return null;
	}
}

class TabIndicatorCallback {

	getType() {
		return this.TYPE;
	}

	event() { // FIXME: rename
		return false;
	}
}

class TabIndicatorNumber {}

class TabIndicatorStatus {}

class MacrosTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
	}

	event() {
		return document.querySelectorAll('#tbl_macros tr.form_row > td:first-child > textarea:not(:placeholder-shown):not([readonly])').length;
	}
}

class LinkedTemplateTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatus;
	}

	event() {
		return true;
	}
}

class TagsTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
	}

	event() {
		return document.querySelectorAll('#tags-table tr.form_row > td:first-child > textarea:not(:placeholder-shown):not([readonly])').length;
	}
}
