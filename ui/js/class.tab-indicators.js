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

const TAB_INDICATOR_ATTR_TYPE    = 'data-indicator';
const TAB_INDICATOR_ATTR_VALUE   = 'data-indicator-value';

const TAB_INDICATOR_TYPE_COUNT   = 'count';
const TAB_INDICATOR_TYPE_MARK    = 'mark';

const TAB_INDICATOR_UPDATE_EVENT = 'tab-indicator-update';

/**
 * Main class to initialize tab indicators.
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
	 * Get main form.
	 *
	 * @return {HTMLElement} Main form
	 */
	getForm() {
		const TEMPLATE = document.querySelector('#templates-form');
		const HOST = document.querySelector('#hosts-form');
		const AUTHENTICATION = document.querySelector('#authentication-form');
		const HOST_PROTOTYPE = document.querySelector('#host-prototype-form');
		const ITEM = document.querySelector('#item-form');
		const ITEM_PROTOTYPE = document.querySelector('#item-prototype-form');
		const TRIGGER = document.querySelector('#triggers-form');
		const TRIGGER_PROTOTYPE = document.querySelector('#triggers-prototype-form');
		const HOST_DISCOVERY = document.querySelector('#host-discovery-form');
		const WEB_SCENARIO = document.querySelector('#http-form');
		const ACTION = document.querySelector('#action-form');
		const SERVICE = document.querySelector('#services-form');
		const PROXY = document.querySelector('#proxy-form');
		const USER_GROUP = document.querySelector('#user-group-form');
		const USER = document.querySelector('#user-form');
		const MEDIA_TYPE = document.querySelector('#media-type-form');
		const MAP = document.querySelector('#sysmap-form');
		const GRAPH = document.querySelector('#widget-dialogue-form');

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

		Object.values(tabs).map((element) => {
			const indicator_item = this.getIndicatorItem(this.getIndicatorNameByElement(element));

			if (indicator_item instanceof TabIndicatorItem) {
				indicator_item
					.addAttributes(element)
					.initObserver(element);
			}
		});
	}

	/**
	 * Get tab indicator name.
	 *
	 * @param {HTMLElement} element  tab element.
	 *
	 * @return {?string}
	 */
	getIndicatorNameByElement(element) {
		const attr = element.getAttribute('js-indicator');

		if (attr) {
			return attr
				.split('-')
				.map((value) => value[0].toUpperCase() + value.slice(1))
				.join('');
		}

		return null;
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
			case 'GraphLegend':
				return new GraphLegendTabIndicatorItem;
			case 'GraphProblems':
				return new GraphProblemsTabIndicatorItem;
			case 'GraphOverrides':
				return new GraphOverridesTabIndicatorItem;
			case 'Permissions':
				return new PermissionsTabIndicatorItem;
		}

		return null;
	}
}

/**
 * Tab indicator item.
 */
class TabIndicatorItem {

	constructor(type) {
		this._type = type;
	}

	/**
	 * Get tab indicator type.
	 *
	 * @return {string}
	 */
	getType() {
		return this._type;
	}

	/**
	 * Get tab indicator value.
	 *
	 * @return {boolean|number} Boolean for mark indicator and number for count indicator
	 */
	getValue() {
		throw 'Fatal error: can not call abstract method.';
	}

	/**
	 * Init observer for html changes.
	 *
	 * @param {HTMLElement} element
	 */
	initObserver(element) {
		throw 'Fatal error: can not call abstract method.';
	}

	/**
	 * Add tab indicator attribute to tab element.
	 *
	 * @param {HTMLElement} element  tab element
	 *
	 * @return {TabIndicatorItem}
	 */
	addAttributes(element) {
		element.setAttribute(TAB_INDICATOR_ATTR_TYPE, this.getType());

		switch (this.getType()) {
			case TAB_INDICATOR_TYPE_COUNT:
				element.setAttribute(TAB_INDICATOR_ATTR_VALUE, this.getValue().toString());
				break;
			case TAB_INDICATOR_TYPE_MARK:
				element.setAttribute(TAB_INDICATOR_ATTR_VALUE, !!this.getValue() ? '1' : '0');
				break;
		}

		return this;
	}
}

class MacrosTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_COUNT);
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
	 * @param {HTMLElement} element
	 */
	initObserver(element) {
		const target_node = document.querySelector('#tbl_macros');
		const observer_options = {
			childList: true,
			attributes: true,
			attributeFilter: ['value', 'style'], // Use style because textarea don't have value attribute.
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
					case 'attributes':
						this.addAttributes(element);
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
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		const target_node = document.querySelector('#linked-template');
		const multiselect_node = document.querySelector('#add_templates_');
		let count = 0;

		// Count saved templates.
		if (target_node) {
			count += target_node
				.querySelectorAll('tbody tr')
				.length;
		}

		// Count new templates in multiselect.
		if (multiselect_node) {
			count += multiselect_node
				.querySelectorAll('.selected li')
				.length;
		}

		return isNaN(count) ? 0 : count;
	}

	initObserver(element) {
		const target_node = document.querySelector('#add_templates_ .multiselect-list');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						this.addAttributes(element);
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
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('#tags-table tr.form_row > td:first-child > textarea:not(:placeholder-shown):not([readonly])')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('#tags-table');
		const observer_options = {
			childList: true,
			attributes: true,
			attributeFilter: ['value', 'style'], // Use style because textarea don't have value attribute.
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
					case 'attributes':
						this.addAttributes(element);
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

class HttpTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const element = document.querySelector('#http_auth_enabled');

		if (element) {
			return element.checked;
		}

		return false;
	}

	initObserver(element) {
		const target_node = document.querySelector('#http_auth_enabled');

		if (target_node) {
			target_node.addEventListener('click', () => {
				this.addAttributes(element);
			});
		}
	}
}

class LdapTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const element = document.querySelector('#ldap_configured');

		if (element) {
			return element.checked;
		}

		return false;
	}

	initObserver(element) {
		const target_node = document.querySelector('#ldap_configured');

		if (target_node) {
			target_node.addEventListener('click', () => {
				this.addAttributes(element);
			});
		}
	}
}

class SamlTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const element = document.querySelector('#saml_auth_enabled');

		if (element) {
			return element.checked;
		}

		return false;
	}

	initObserver(element) {
		const target_node = document.querySelector('#saml_auth_enabled');

		if (target_node) {
			target_node.addEventListener('click', () => {
				this.addAttributes(element);
			});
		}
	}
}

class InventoryTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const element = document.querySelector('[name=inventory_mode]:checked');

		if (element) {
			return (element.value === '0' || element.value === '1');
		}

		return false;
	}

	initObserver(element) {
		[...document.querySelectorAll('[name=inventory_mode]')].map((value) => {
			value.addEventListener('click', () => {
				this.addAttributes(element);
			});
		});
	}
}

class EncryptionTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const tls_connect = document.querySelector('[name=tls_connect]:checked');

		if (tls_connect && (tls_connect.value === '2' || tls_connect.value === '4')) {
			return true;
		}

		const tls_in_psk = !!document.querySelector('[name=tls_in_psk]:checked');
		const tls_in_cert = !!document.querySelector('[name=tls_in_cert]:checked');

		return tls_in_psk || tls_in_cert;
	}

	initObserver(element) {
		const tls_in_psk_node = document.querySelector('[name=tls_in_psk]');
		const tls_in_cert_node = document.querySelector('[name=tls_in_cert]');

		[...document.querySelectorAll('[name=tls_connect]')].map((value) =>
			value.addEventListener('click', () => {
				this.addAttributes(element);
			})
		);

		if (tls_in_psk_node) {
			tls_in_psk_node.addEventListener('click', () => {
				this.addAttributes(element);
			});
		}

		if (tls_in_cert_node) {
			tls_in_cert_node.addEventListener('click', () => {
				this.addAttributes(element);
			});
		}
	}
}

class GroupsTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('#group_links_ .multiselect-list li')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('#group_links_ .multiselect-list');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						this.addAttributes(element);
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
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('#preprocessing .preprocessing-list-item')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('#preprocessing');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						this.addAttributes(element);
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
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('#dependency-table tbody tr')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('#dependency-table tbody');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						this.addAttributes(element);
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
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('#lld_macro_paths tbody tr.form_row > td:first-child > textarea:not(:placeholder-shown):not([readonly])')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('#lld_macro_paths');
		const observer_options = {
			childList: true,
			attributes: true,
			attributeFilter: ['value', 'style'], // Use style because textarea don't have value attribute.
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
					case 'attributes':
						this.addAttributes(element);
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
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('#conditions tbody .form_row > td > input.macro:not(:placeholder-shown):not([readonly])')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('#conditions');
		const observer_options = {
			childList: true,
			attributes: true,
			attributeFilter: ['value'],
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
					case 'attributes':
						this.addAttributes(element);
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
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('.lld-overrides-table tbody [data-index]')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('.lld-overrides-table tbody');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						this.addAttributes(element);
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
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('.httpconf-steps-dynamic-row [data-index]')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('.httpconf-steps-dynamic-row tbody');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						this.addAttributes(element);
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
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		if (document.querySelector('#authentication').value > 0) {
			return true;
		}

		if (document.querySelector('#verify_peer:checked') || document.querySelector('#verify_host:checked')) {
			return true;
		}

		return document.querySelector('#ssl_cert_file').value !== ''
			|| document.querySelector('#ssl_key_file').value !== ''
			|| document.querySelector('#ssl_key_password').value !== '';
	}

	initObserver(element) {
		const auth_node = document.querySelector('#authentication');

		if (auth_node) {
			auth_node.addEventListener('change', () => {
				this.addAttributes(element);
			});
		}

		[...document.querySelectorAll('#verify_peer, #verify_host')].map((value) => {
			value.addEventListener('click', () => {
				this.addAttributes(element);
			});
		});

		[...document.querySelectorAll('#ssl_cert_file, #ssl_key_file, #ssl_key_password')].map((value) => {
			value.addEventListener('change', () => {
				this.addAttributes(element);
			});
		});
	}
}

class OperationsTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		let count = 0;

		count += document
			.querySelectorAll('#op-table tbody tr:not(:last-child)')
			.length;
		count += document
			.querySelectorAll('#rec-table tbody tr:not(:last-child)')
			.length;
		count += document
			.querySelectorAll('#ack-table tbody tr:not(:last-child)')
			.length;

		return count;
	}

	initObserver(element) {
		const target_node_op = document.querySelector('#op-table tbody');
		const target_node_rec = document.querySelector('#rec-table tbody');
		const target_node_ack = document.querySelector('#ack-table tbody');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						this.addAttributes(element);
						break;
				}
			});
		};

		if (target_node_op) {
			const observer_op = new MutationObserver(observer_callback);
			observer_op.observe(target_node_op, observer_options);
		}

		if (target_node_rec) {
			const observer_rec = new MutationObserver(observer_callback);
			observer_rec.observe(target_node_rec, observer_options);
		}

		if (target_node_ack) {
			const observer_ack = new MutationObserver(observer_callback);
			observer_ack.observe(target_node_ack, observer_options);
		}
	}
}

class ServiceDependencyTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('#service_children tbody tr')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('#service_children tbody');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						this.addAttributes(element);
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
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('#time-table tbody tr')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('#time-table tbody');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						this.addAttributes(element);
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
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		return document
			.querySelectorAll('#tag-filter-table tbody tr')
			.length > 0;
	}

	initObserver(element) {
		// This event triggered in app/views/js/administration.usergroup.edit.js.php:179
		document.addEventListener(TAB_INDICATOR_UPDATE_EVENT, () => {
			this.addAttributes(element);
		});
	}
}

class MediaTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('#media-table tbody tr')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('#media-table tbody');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						this.addAttributes(element);
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
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('#message-templates tbody tr:not(:last-child)')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('#message-templates tbody');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						this.addAttributes(element);
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
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const element = document.querySelector('#messages_enabled');

		if (element) {
			return element.checked;
		}

		return false;
	}

	initObserver(element) {
		const target_node = document.querySelector('#messages_enabled');

		if (target_node) {
			target_node.addEventListener('click', () => {
				this.addAttributes(element);
			});
		}
	}
}

class SharingTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const element = document.querySelector("[name='private']:checked");

		if (element && element.value > 0) {
			return true;
		}

		return document.querySelectorAll('#user-group-share-table tbody tr:not(:last-child)').length > 0
			|| document.querySelectorAll('#user-share-table tbody tr:not(:last-child)').length > 0;
	}

	initObserver(element) {
		[...document.querySelectorAll('[name=private]')].map((value) => {
			value.addEventListener('click', () => {
				this.addAttributes(element);
			});
		});

		const target_node_group = document.querySelector('#user-group-share-table tbody');
		const target_node_user = document.querySelector('#user-share-table tbody');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						this.addAttributes(element);
						break;
				}
			});
		};

		if (target_node_group) {
			const observer_group = new MutationObserver(observer_callback);
			observer_group.observe(target_node_group, observer_options);
		}

		if (target_node_user) {
			const observer_user = new MutationObserver(observer_callback);
			observer_user.observe(target_node_user, observer_options);
		}
	}
}

class GraphDatasetTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('#data_sets .list-accordion-item')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('#data_sets');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						this.addAttributes(element);
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
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const element = document.querySelector("[name='source']:checked");

		if (element) {
			return element.value > 0;
		}

		return false;
	}

	initObserver(element) {
		[...document.querySelectorAll("[name='source']")].map((value) => {
			value.addEventListener('click', () => {
				this.addAttributes(element);
			});
		});
	}
}

class GraphTimeTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const element = document.querySelector('#graph_time');

		if (element) {
			return element.checked;
		}

		return false;
	}

	initObserver(element) {
		const target_node = document.querySelector('#graph_time');

		if (target_node) {
			target_node.addEventListener('click', () => {
				this.addAttributes(element);
			});
		}
	}
}

class GraphLegendTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const element = document.querySelector('#legend');

		if (element) {
			return element.checked;
		}

		return false;
	}

	initObserver(element) {
		const target_node = document.querySelector('#legend');

		if (target_node) {
			target_node.addEventListener('click', () => {
				this.addAttributes(element);
			});
		}
	}
}

class GraphProblemsTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const element = document.querySelector('#show_problems');

		if (element) {
			return element.checked;
		}

		return false;
	}

	initObserver(element) {
		const target_node = document.querySelector('#show_problems');

		if (target_node) {
			target_node.addEventListener('click', () => {
				this.addAttributes(element);
			});
		}
	}
}

class GraphOverridesTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('.overrides-list .overrides-list-item')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('.overrides-list');
		const observer_options = {
			childList: true,
			subtree: true
		};

		const observer_callback = (mutationList, _observer) => {
			mutationList.forEach((mutation) => {
				switch (mutation.type) {
					case 'childList':
						this.addAttributes(element);
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

class PermissionsTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		return document
			.querySelectorAll('#group-right-table tbody tr')
			.length > 1;
	}

	initObserver(element) {
		// This event triggered in app/views/js/administration.usergroup.edit.js.php:164
		document.addEventListener(TAB_INDICATOR_UPDATE_EVENT, () => {
			this.addAttributes(element);
		});
	}
}
