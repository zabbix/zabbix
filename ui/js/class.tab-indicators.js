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
		// try {
			this.form = this.getForm();
			this.activateIndicators();
		// } catch (error) {
		// 	return false;
		// }
	}

	getForm() {
		const TEMPLATE = document.querySelector('#templatesForm');
		const AUTHENTICATION = document.querySelector('#authenticationForm');

		switch (true) {
			case !!TEMPLATE:
				return TEMPLATE;
			case !!AUTHENTICATION:
				return AUTHENTICATION;
			default:
				throw 'Form not found.';
		}
	}

	activateIndicators() {
		const tabs = this.form.querySelectorAll('#tabs a');

		Object.values(tabs).map((elem) => {
			const callback = this.getIndicatorCallback(elem);

			this.addAttribute(elem, callback?.getType(), callback?.getValue());

			callback?.initObserver(elem);
		});
	}

	addAttribute(elem, type, value) {
		if (type instanceof TabIndicatorStatus) {
			elem.setAttribute('data-indicator-status', value ? 'enabled' : 'disabled');
		}

		if (type instanceof TabIndicatorNumber) {
			elem.setAttribute('data-indicator-count', value);
		}
	}

	getIndicatorCallback(elem) {
		const callback_name = elem
			.getAttribute('js-indicator')
			?.split('-')
			?.map((value) => value[0].toUpperCase() + value.slice(1))
			?.join('');

		return TabIndicatorFactory
			.createTabIndicator(callback_name)
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
			case 'Http':
				return new HttpTabIndicator;
			case 'Ldap':
				return new LdapTabIndicator;
			case 'Saml':
				return new SamlTabIndicator;
		}

		return null;
	}
}

class TabIndicatorNumber {}

class TabIndicatorStatus {}

class TabIndicatorCallback {

	getType() {
		return this.TYPE;
	}

	getValue() {
		throw 'Fatal error: can not call abstract class.';
	}

	initObserver(elem) {
		throw 'Fatal error: can not call abstract class.';
	}
}

class MacrosTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
	}

	getValue() {
		return document.querySelectorAll('#tbl_macros tr.form_row > td:first-child > textarea:not(:placeholder-shown):not([readonly])').length;
	}

	initObserver(elem) {
		const targetNode = document.querySelector('#tbl_macros');
		const observerOptions = {
			childList: true,
			attributes: true,
			attributeFilter: ['value', 'style'], // Use style because textarea dont have value attribute.
			subtree: true
		};

		const observerCallback = (mutationList, observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
					case 'attributes':
						elem.setAttribute('data-indicator-count', this.getValue());
						break;
				}
			});
		};

		const observer = new MutationObserver(observerCallback);
		observer.observe(targetNode, observerOptions);
	}
}

class LinkedTemplateTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatus;
	}

	getValue() {
		return true;
	}

	initObserver(elem) {
		return true;
	}
}

class TagsTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
	}

	getValue() {
		return document.querySelectorAll('#tags-table tr.form_row > td:first-child > textarea:not(:placeholder-shown):not([readonly])').length;
	}

	initObserver(elem) {
		const targetNode = document.querySelector('#tags-table');
		const observerOptions = {
			childList: true,
			attributes: true,
			attributeFilter: ['value', 'style'], // Use style because textarea dont have value attribute.
			subtree: true
		};

		const observerCallback = (mutationList, observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
					case 'attributes':
						elem.setAttribute('data-indicator-count', this.getValue());
						break;
				}
			});
		};

		const observer = new MutationObserver(observerCallback);
		observer.observe(targetNode, observerOptions);
	}
}

class HttpTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatus;
	}

	getValue() {
		return document.querySelector('#http_auth_enabled').checked;
	}

	initObserver(elem) {
		document
			.querySelector('#http_auth_enabled')
			?.addEventListener('click', () => {
				elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
			});
	}
}

class LdapTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatus;
	}

	getValue() {
		return document.querySelector('#ldap_configured')?.checked;
	}

	initObserver(elem) {
		document
			.querySelector('#ldap_configured')
			?.addEventListener('click', () => {
				elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
			});
	}
}

class SamlTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatus;
	}

	getValue() {
		return document
			.querySelector('#saml_auth_enabled')
			?.checked;
	}

	initObserver(elem) {
		document
			.querySelector('#saml_auth_enabled')
			?.addEventListener('click', () => {
				elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
			});
	}
}
