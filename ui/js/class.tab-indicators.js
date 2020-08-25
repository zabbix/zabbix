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

/**
 * Main class to initialize tab indicator.
 */
class TabIndicators {

	constructor() {
		try {
			this.form = this.getForm();
			this.activateIndicators();
		} catch (error) {
			return false;
		}
	}

	/**
	 * Get main form
	 *
	 * @return {HTMLElement} Main form
	 */
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
		const WEB_SCENARIO = document.querySelector('#httpForm');
		const ACTION = document.querySelector("[id='action.edit']");
		const SERVICE = document.querySelector('#servicesForm');
		const PROXY = document.querySelector('#proxyForm');
		const USER_GROUP = document.querySelector('#userGroupForm');
		const USER = document.querySelector('#userForm');
		const MEDIA_TYPE = document.querySelector('#media_type_form');
		const MAP = document.querySelector('#mapEditForm');
		const GRAPH = document.querySelector('#widget_dialogue_form');

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
			case !!WEB_SCENARIO:
				return WEB_SCENARIO;
			case !!ACTION:
				return ACTION;
			case !!SERVICE:
				return SERVICE;
			case !!PROXY:
				return PROXY;
			case !!USER_GROUP:
				return USER_GROUP;
			case !!USER:
				return USER;
			case !!MEDIA_TYPE:
				return MEDIA_TYPE;
			case !!MAP:
				return MAP;
			case !!GRAPH:
				return GRAPH;
			default:
				throw 'Form not found.';
		}
	}

	/**
	 * Activate tab indicators.
	 */
	activateIndicators() {
		const tabs = this.form.querySelectorAll('#tabs a');

		Object.values(tabs).map((elem) => {
			const indicator_item = this.getIndicatorItem(this.getIndicatorNameByElement(elem));

			this.addAttribute(elem, indicator_item?.getType(), indicator_item?.getValue());

			indicator_item?.initObserver(elem);
		});
	}

	/**
	 * Add tab indicator attribute to tab element.
	 *
	 * @param {HTMLElement} elem  tab element
	 * @param {null|TabIndicatorStatusType|TabIndicatorNumberType} type
	 * @param {null|boolean|number} value
	 */
	addAttribute(elem, type, value) {
		if (type instanceof TabIndicatorStatusType) {
			elem.setAttribute('data-indicator-status', value ? 'enabled' : 'disabled');
		}

		if (type instanceof TabIndicatorNumberType) {
			elem.setAttribute('data-indicator-count', value);
		}
	}

	/**
	 * Get tab indicator name.
	 *
	 * @param {HTMLElement} elem  tab element.
	 *
	 * @return {?string}
	 */
	getIndicatorNameByElement(elem) {
		return elem
			.getAttribute('js-indicator')
			?.split('-')
			?.map((value) => value[0].toUpperCase() + value.slice(1))
			?.join('');
	}

	/**
	 * Get tab indicator item class.
	 *
	 * @param {string} indicator_name
	 *
	 * @return {?TabIndicatorItem}
	 */
	getIndicatorItem(indicator_name) {
		return TabIndicatorFactory.createTabIndicator(indicator_name);
	}
}

/**
 * Factory for tab indicator items.
 */
class TabIndicatorFactory {

	/**
	 * Get tab indicator item class.
	 *
	 * @param {string} name
	 *
	 * @return {?TabIndicatorItem}
	 */
	static createTabIndicator(name) {
		switch (name) {
			case 'Macros':
				return new MacrosTabIndicatorItem;
			case 'LinkedTemplate':
				return new LinkedTemplateTabIndicatorItem;
			case 'Tags':
				return new TagsTabIndicatorItem;
			case 'Http':
				return new HttpTabIndicatorItem;
			case 'Ldap':
				return new LdapTabIndicatorItem;
			case 'Saml':
				return new SamlTabIndicatorItem;
			case 'Inventory':
				return new InventoryTabIndicatorItem;
			case 'Encryption':
				return new EncryptionTabIndicatorItem;
			case 'Groups':
				return new GroupsTabIndicatorItem;
			case 'Preprocessing':
				return new PreprocessingTabIndicatorItem;
			case 'Dependency':
				return new DependencyTabIndicatorItem;
			case 'LldMacros':
				return new LldMacrosTabIndicatorItem;
			case 'Filters':
				return new FiltersTabIndicatorItem;
			case 'Overrides':
				return new OverridesTabIndicatorItem;
			case 'Steps':
				return new StepsTabIndicatorItem;
			case 'HttpAuth':
				return new HttpAuthTabIndicatorItem;
			case 'Operations':
				return new OperationsTabIndicatorItem;
			case 'ServiceDependency':
				return new ServiceDependencyTabIndicatorItem;
			case 'Time':
				return new TimeTabIndicatorItem;
			case 'TagFilter':
				return new TagFilterTabIndicatorItem;
			case 'Media':
				return new MediaTabIndicatorItem;
			case 'MessageTemplate':
				return new MessageTemplateTabIndicatorItem;
			case 'FrontendMessage':
				return new FrontendMessageTabIndicatorItem;
			case 'Sharing':
				return new SharingTabIndicatorItem;
			case 'GraphDataset':
				return new GraphDatasetTabIndicatorItem;
			case 'GraphOptions':
				return new GraphOptionsTabIndicatorItem;
			case 'GraphTime':
				return new GraphTimeTabIndicatorItem;
			case 'GraphAxes':
				return new GraphAxesTabIndicatorItem;
			case 'GraphLegend':
				return new GraphLegendTabIndicatorItem;
			case 'GraphProblems':
				return new GraphProblemsTabIndicatorItem;
			case 'GraphOverrides':
				return new GraphOverridesTabIndicatorItem;
		}

		return null;
	}
}

class TabIndicatorNumberType { }

class TabIndicatorStatusType { }

/**
 * Tab indicator item.
 */
class TabIndicatorItem {

	/**
	 * Get tab indicator type.
	 *
	 * @return {TabIndicatorNumberType|TabIndicatorStatusType}
	 */
	getType() {
		return this.TYPE;
	}

	/**
	 * Get tab indicator value.
	 *
	 * @return {boolean|number} Boolean for TabIndicatorStatusType or number for TabIndicatorNumberType
	 */
	getValue() {
		throw 'Fatal error: can not call abstract class.';
	}

	/**
	 * Init observer for html changes.
	 *
	 * @param {HTMLElement} elem
	 */
	initObserver(elem) {
		throw 'Fatal error: can not call abstract class.';
	}
}

class MacrosTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
	}

	getValue() {
		return document
			.querySelectorAll('#tbl_macros tr.form_row > td:first-child > textarea:not(:placeholder-shown):not([readonly])')
			.length;
	}

	/**
	 * @inheritdoc
	 * This observer yet init in include\views\js\common.template.edit.js.php.
	 *
	 * @param {HTMLElement} elem
	 */
	initObserver(elem) {
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

class LinkedTemplateTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
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

class TagsTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
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

class HttpTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatusType;
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

class LdapTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatusType;
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

class SamlTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatusType;
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

class InventoryTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatusType;
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
			});
		});
	}
}

class EncryptionTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatusType;
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

class GroupsTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
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

class PreprocessingTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
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

class DependencyTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
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

class LldMacrosTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
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

class FiltersTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
	}

	getValue() {
		return document
			.querySelectorAll('#conditions tbody .form_row > td > input.macro:not(:placeholder-shown):not([readonly])')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('#conditions');
		const observer_options = {
			childList: true,
			attributes: true,
			attributeFilter: ['value'],
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

class OverridesTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
	}

	getValue() {
		return document
			.querySelectorAll('.lld-overrides-table tbody [data-index]')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('.lld-overrides-table tbody');
		const observer_options = {
			childList: true,
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

class StepsTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
	}

	getValue() {
		return document
			.querySelectorAll('.httpconf-steps-dynamic-row [data-index]')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('.httpconf-steps-dynamic-row tbody');
		const observer_options = {
			childList: true,
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

class HttpAuthTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatusType;
	}

	getValue() {
		if (document.querySelector('#authentication').value > 0) {
			return true;
		}

		if (document.querySelector('#verify_peer:checked') || document.querySelector('#verify_host:checked')) {
			return true;
		}

		if (document.querySelector('#ssl_cert_file').value !== ''
				|| document.querySelector('#ssl_key_file').value !== ''
				|| document.querySelector('#ssl_key_password').value !== '') {
			return true;
		}

		return false;
	}

	initObserver(elem) {
		document
			.querySelector('#authentication')
			?.addEventListener('change', () => {
				elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
			});

		[...document.querySelectorAll('#verify_peer, #verify_host')].map((value) => {
			value.addEventListener('click', () => {
				elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
			});
		});

		[...document.querySelectorAll('#ssl_cert_file, #ssl_key_file, #ssl_key_password')].map((value) => {
			value.addEventListener('change', () => {
				elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
			});
		});
	}
}

class OperationsTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
	}

	getValue() {
		let count = 0;
		count += document.querySelectorAll('#op-table tbody tr:not(:last-child)').length;
		count += document.querySelectorAll('#rec-table tbody tr:not(:last-child)').length;
		count += document.querySelectorAll('#ack-table tbody tr:not(:last-child)').length;

		return count;
	}

	initObserver(elem) {
		const target_node_op = document.querySelector('#op-table tbody');
		const target_node_rec = document.querySelector('#rec-table tbody');
		const target_node_ack = document.querySelector('#ack-table tbody');
		const observer_options = {
			childList: true,
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

		if (target_node_op) {
			const observer = new MutationObserver(observer_callback);
			observer.observe(target_node_op, observer_options);
		}

		if (target_node_rec) {
			const observer = new MutationObserver(observer_callback);
			observer.observe(target_node_rec, observer_options);
		}

		if (target_node_ack) {
			const observer = new MutationObserver(observer_callback);
			observer.observe(target_node_ack, observer_options);
		}
	}
}

class ServiceDependencyTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
	}

	getValue() {
		return document
			.querySelectorAll('#service_children tbody tr')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('#service_children tbody');
		const observer_options = {
			childList: true,
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

class TimeTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
	}

	getValue() {
		return document
			.querySelectorAll('#time-table tbody tr')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('#time-table tbody');
		const observer_options = {
			childList: true,
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

class TagFilterTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatusType;
	}

	getValue() {
		return document
			.querySelectorAll('#tag-filter-table tbody tr')
			.length > 0;
	}

	initObserver(elem) {
		const target_node = document.querySelector('#tagFilterFormList > li');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
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

class MediaTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
	}

	getValue() {
		return document
			.querySelectorAll('#media-table tbody tr')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('#media-table tbody');
		const observer_options = {
			childList: true,
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

class MessageTemplateTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
	}

	getValue() {
		return document
			.querySelectorAll('#message-templates tbody tr:not(:last-child)')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('#message-templates tbody');
		const observer_options = {
			childList: true,
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

class FrontendMessageTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatusType;
	}

	getValue() {
		return document
			.querySelector('#messages_enabled')
			.checked;
	}

	initObserver(elem) {
		document
			.querySelector('#messages_enabled')
			?.addEventListener('click', () => {
				elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
			});
	}
}

class SharingTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatusType;
	}

	getValue() {
		if (document.querySelector("[name='private']:checked").value > 0) {
			return true;
		}

		if (document.querySelectorAll('#user-group-share-table tbody tr:not(:last-child)').length > 0
				|| document.querySelectorAll('#user-share-table tbody tr:not(:last-child)').length > 0) {
			return true;
		}

		return false;
	}

	initObserver(elem) {
		[...document.querySelectorAll('[name=private]')].map((value) => {
			value?.addEventListener('click', () => {
				elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
			});
		});

		const target_node_group = document.querySelector('#user-group-share-table tbody');
		const target_node_user = document.querySelector('#user-share-table tbody');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
						break;
				}
			});
		};

		if (target_node_group) {
			const observer = new MutationObserver(observer_callback);
			observer.observe(target_node_group, observer_options);
		}

		if (target_node_user) {
			const observer = new MutationObserver(observer_callback);
			observer.observe(target_node_user, observer_options);
		}
	}
}

class GraphDatasetTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
	}

	getValue() {
		return document
			.querySelectorAll('#data_sets .list-accordion-item')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('#data_sets');
		const observer_options = {
			childList: true,
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

class GraphOptionsTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatusType;
	}

	getValue() {
		return document
			.querySelector("[name='source']:checked")
			?.value > 0;
	}

	initObserver(elem) {
		[...document.querySelectorAll("[name='source']")].map((value) => {
			value.addEventListener('click', () => {
				elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
			});
		});
	}
}


class GraphTimeTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatusType;
	}

	getValue() {
		return document
			.querySelector('#graph_time')
			?.checked;
	}

	initObserver(elem) {
		document
			.querySelector('#graph_time')
			?.addEventListener('click', () => {
				elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
			});
	}
}

class GraphAxesTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatusType;
	}

	getValue() {
		if (document.querySelector('#lefty:checked') || document.querySelector('#righty:checked')
				|| document.querySelector('#axisx:checked')) {
			return true;
		}

		return false;
	}

	initObserver(elem) {
		[document.querySelector('#lefty:checked'), document.querySelector('#righty:checked'),
			document.querySelector('#axisx:checked')
		].map((value) => {
			value.addEventListener('click', () => {
				elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
			});
		});
	}
}

class GraphLegendTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatusType;
	}

	getValue() {
		return document
			.querySelector('#legend')
			?.checked;
	}

	initObserver(elem) {
		document
			.querySelector('#legend')
			?.addEventListener('click', () => {
				elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
			});
	}
}

class GraphProblemsTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatusType;
	}

	getValue() {
		return document
			.querySelector('#show_problems')
			?.checked;
	}

	initObserver(elem) {
		document
			.querySelector('#show_problems')
			?.addEventListener('click', () => {
				elem.setAttribute('data-indicator-status', !!this.getValue() ? 'enabled' : 'disabled');
			});
	}
}

class GraphOverridesTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumberType;
	}

	getValue() {
		return document
			.querySelectorAll('.overrides-list .overrides-list-item')
			.length;
	}

	initObserver(elem) {
		const target_node = document.querySelector('.overrides-list');
		const observer_options = {
			childList: true,
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
