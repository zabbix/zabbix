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
		const HOST = document.querySelector('#hostsForm');
		const AUTHENTICATION = document.querySelector('#authenticationForm');
		const HOST_PROTOTYPE = document.querySelector('#hostPrototypeForm');
		const ITEM = document.querySelector('#itemForm');
		const ITEM_PROTOTYPE = document.querySelector('#itemPrototypeForm');
		const TRIGGER = document.querySelector('#triggersForm');
		const TRIGGER_PROTOTYPE = document.querySelector('#triggersPrototypeForm');
		const HOST_DISCOVERY = document.querySelector('#hostDiscoveryForm');

		switch (true) {
			case !!TEMPLATE:
				return TEMPLATE;
			case !!HOST:
				return HOST;
			case !!AUTHENTICATION:
				return AUTHENTICATION;
			case !!HOST_PROTOTYPE:
				return HOST_PROTOTYPE;
			case !!ITEM:
				return ITEM;
			case !!ITEM_PROTOTYPE:
				return ITEM_PROTOTYPE;
			case !!TRIGGER:
				return TRIGGER;
			case !!TRIGGER_PROTOTYPE:
				return TRIGGER_PROTOTYPE;
			case !!HOST_DISCOVERY:
				return HOST_DISCOVERY;
			default:
				throw 'Form not found.';
		}
	}

	activateIndicators() {
		const tabs = this.form.querySelectorAll('#tabs a');

		Object.values(tabs).map((elem) => {
			const indicator_class = this.getIndicatorClass(elem);

			this.addAttribute(elem, indicator_class?.getType(), indicator_class?.getValue());

			indicator_class?.initObserver(elem);
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

	getIndicatorClass(elem) {
		const class_name = elem
			.getAttribute('js-indicator')
			?.split('-')
			?.map((value) => value[0].toUpperCase() + value.slice(1))
			?.join('');

		return TabIndicatorFactory
			.createTabIndicator(class_name)
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
			case 'Inventory':
				return new InventoryTabIndicator;
			case 'Encryption':
				return new EncryptionTabIndicator;
			case 'Groups':
				return new GroupsTabIndicator;
			case 'Preprocessing':
				return new PreprocessingTabIndicator;
			case 'Dependency':
				return new DependencyTabIndicator;
			case 'LldMacros':
				return new LldMacrosTabIndicator;
			case 'Filters':
				return new FiltersTabIndicator;
			case 'Overrides':
				return new OverridesTabIndicator;
		}

		return null;
	}
}

class TabIndicatorNumber { }

class TabIndicatorStatus { }

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
		return document
			.querySelectorAll('#tbl_macros tr.form_row > td:first-child > textarea:not(:placeholder-shown):not([readonly])')
			.length;
	}

	initObserver(elem) {
		// FIXME: observer deleted after macro table refreshed by ajax.

		const target_node = document.querySelector('#tbl_macros');
		const observer_options = {
			childList: true,
			attributes: true,
			attributeFilter: ['value', 'style'], // Use style because textarea dont have value attribute.
			subtree: true
		};

		const observer_callback = (mutationList, observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
					case 'attributes':
						elem.setAttribute('data-indicator-count', this.getValue());
						break;
				}
			});
		};

		if (target_node) {
			const observer = new MutationObserver(observer_callback);
			observer.observe(target_node, observer_options);
		}
	}
}

class LinkedTemplateTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
	}

	getValue() {
		const target_node = document.querySelector('#linked-template');
		const multiselect_node = document.querySelector('#add_templates_');
		let count = 0;

		// Count saved templates.
		count += target_node
			?.querySelectorAll('tbody tr')
			.length;
		// Count new templates in multiselect.
		count += multiselect_node
			?.querySelectorAll('.selected li')
			.length;

		return isNaN(count) ? 0 : count;
	}

	initObserver(elem) {
		const target_node = document.querySelector('#add_templates_ .multiselect-list');
		const observer_options = {
			childList: true,
			attributes: false,
			subtree: true
		};

		const observer_callback = (mutationList, observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						elem.setAttribute('data-indicator-count', this.getValue());
						break;
				}
			});
		};

		if (target_node) {
			const observer = new MutationObserver(observer_callback);
			observer.observe(target_node, observer_options);
		}
	}
}

class TagsTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
	}

	getValue() {
		return document
			.querySelectorAll('#tags-table tr.form_row > td:first-child > textarea:not(:placeholder-shown):not([readonly])')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('#tags-table');
		const observer_options = {
			childList: true,
			attributes: true,
			attributeFilter: ['value', 'style'], // Use style because textarea dont have value attribute.
			subtree: true
		};

		const observer_callback = (mutationList, observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
					case 'attributes':
						elem.setAttribute('data-indicator-count', this.getValue());
						break;
				}
			});
		};

		const observer = new MutationObserver(observer_callback);
		observer.observe(target_node, observer_options);
	}
}

class HttpTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatus;
	}

	getValue() {
		return document
			.querySelector('#http_auth_enabled')
			.checked;
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
		return document
			.querySelector('#ldap_configured')
			?.checked;
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

class InventoryTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatus;
	}

	getValue() {
		const value = document
			.querySelector('[name=inventory_mode]:checked')
			?.value;

		return value === '0' || value === '1';
	}

	initObserver(elem) {
		[...document.querySelectorAll('[name=inventory_mode]')]?.map((value) => {
			value.addEventListener('click', () => {
				elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
			})
		});
	}
}

class EncryptionTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatus;
	}

	getValue() {
		const tls_connect = document
			.querySelector('[name=tls_connect]:checked')
			?.value;

		if (tls_connect === '2' || tls_connect === '4') {
			return true;
		}

		const tls_in_psk = !!document.querySelector('[name=tls_in_psk]:checked');
		const tls_in_cert = !!document.querySelector('[name=tls_in_cert]:checked');

		return tls_in_psk || tls_in_cert;
	}

	initObserver(elem) {
		[...document.querySelectorAll('[name=tls_connect]')]?.map((value) =>
			value.addEventListener('click', () => elem.setAttribute('data-indicator-status',
				!!this.getValue() ? 'enabled' : 'disabled'
			))
		);

		document
			.querySelector('[name=tls_in_psk]')
			?.addEventListener('click', () => elem.setAttribute('data-indicator-status',
				!!this.getValue() ? 'enabled' : 'disabled'
			));

		document
			.querySelector('[name=tls_in_cert]')
			?.addEventListener('click', () => elem.setAttribute('data-indicator-status',
				!!this.getValue() ? 'enabled' : 'disabled'
			));
	}
}

class GroupsTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
	}

	getValue() {
		return document
			.querySelector('#group_links_')
			?.querySelectorAll('.multiselect-list li')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('#group_links_ .multiselect-list');
		const observer_options = {
			childList: true,
			attributes: false,
			subtree: true
		};

		const observer_callback = (mutationList, observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						elem.setAttribute('data-indicator-count', this.getValue());
						break;
				}
			});
		};

		if (target_node) {
			const observer = new MutationObserver(observer_callback);
			observer.observe(target_node, observer_options);
		}
	}
}

class PreprocessingTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
	}

	getValue() {
		return document
			.querySelectorAll('#preprocessing .preprocessing-list-item')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('#preprocessing');
		const observer_options = {
			childList: true,
			attributes: false,
			subtree: true
		};

		const observer_callback = (mutationList, observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						elem.setAttribute('data-indicator-count', this.getValue());
						break;
				}
			});
		};

		if (target_node) {
			const observer = new MutationObserver(observer_callback);
			observer.observe(target_node, observer_options);
		}
	}
}

class DependencyTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
	}

	getValue() {
		return document
			.querySelectorAll('#dependency-table tbody tr')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('#dependency-table tbody');
		const observer_options = {
			childList: true,
			attributes: false,
			subtree: true
		};

		const observer_callback = (mutationList, observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						elem.setAttribute('data-indicator-count', this.getValue());
						break;
				}
			});
		};

		if (target_node) {
			const observer = new MutationObserver(observer_callback);
			observer.observe(target_node, observer_options);
		}
	}
}

class LldMacrosTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
	}

	getValue() {
		return document
			.querySelectorAll('#lld_macro_paths tbody tr.form_row > td:first-child > textarea:not(:placeholder-shown):not([readonly])')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('#lld_macro_paths');
		const observer_options = {
			childList: true,
			attributes: true,
			attributeFilter: ['value', 'style'], // Use style because textarea dont have value attribute.
			subtree: true
		};

		const observer_callback = (mutationList, observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
					case 'attributes':
						elem.setAttribute('data-indicator-count', this.getValue());
						break;
				}
			});
		};

		if (target_node) {
			const observer = new MutationObserver(observer_callback);
			observer.observe(target_node, observer_options);
		}
	}
}

class FiltersTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
	}

	getValue() {
		return document
			.querySelectorAll('#conditions tbody tr.form_row > td > input.macro:not(:placeholder-shown):not([readonly])')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('#conditions tbody');
		const observer_options = {
			childList: true,
			attributes: true,
			// attributeFilter: ['value', 'style'], // Use style because textarea dont have value attribute.
			subtree: true
		};

		const observer_callback = (mutationList, observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
					case 'attributes':
						elem.setAttribute('data-indicator-count', this.getValue());
						break;
				}
			});
		};

		if (target_node) {
			const observer = new MutationObserver(observer_callback);
			observer.observe(target_node, observer_options);
		}
	}
}

class OverridesTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
	}

	getValue() {
		return document
			.querySelectorAll('.lld-overrides-table tbody .sortable')
			.length;
	}

	initObserver(elem) {

	}
}
