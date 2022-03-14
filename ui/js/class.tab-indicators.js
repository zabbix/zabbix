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

	constructor(tabs_id = 'tabs') {
		try {
			this.tabs_id = tabs_id;
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
		const ACTION = document.querySelector('#action-form');
		const AUTHENTICATION = document.querySelector('#authentication-form');
		const GRAPH = document.querySelector('#widget-dialogue-form');
		const HOST = document.querySelector('#host-form');
		const HOST_DISCOVERY = document.querySelector('#host-discovery-form');
		const HOST_PROTOTYPE = document.querySelector('#host-prototype-form');
		const ITEM = document.querySelector('#item-form');
		const ITEM_PROTOTYPE = document.querySelector('#item-prototype-form');
		const MAP = document.querySelector('#sysmap-form');
		const MEDIA_TYPE = document.querySelector('#media-type-form');
		const PROXY = document.querySelector('#proxy-form');
		const SERVICE = document.querySelector('#service-form');
		const SLA = document.querySelector('#sla-form');
		const TEMPLATE = document.querySelector('#templates-form');
		const TRIGGER = document.querySelector('#triggers-form');
		const TRIGGER_PROTOTYPE = document.querySelector('#triggers-prototype-form');
		const USER = document.querySelector('#user-form');
		const USER_GROUP = document.querySelector('#user-group-form');
		const WEB_SCENARIO = document.querySelector('#http-form');

		switch (true) {
			case !!ACTION:
				return ACTION;
			case !!AUTHENTICATION:
				return AUTHENTICATION;
			case !!GRAPH:
				return GRAPH;
			case !!HOST:
				return HOST;
			case !!HOST_DISCOVERY:
				return HOST_DISCOVERY;
			case !!HOST_PROTOTYPE:
				return HOST_PROTOTYPE;
			case !!ITEM:
				return ITEM;
			case !!ITEM_PROTOTYPE:
				return ITEM_PROTOTYPE;
			case !!MAP:
				return MAP;
			case !!MEDIA_TYPE:
				return MEDIA_TYPE;
			case !!PROXY:
				return PROXY;
			case !!SERVICE:
				return SERVICE;
			case !!SLA:
				return SLA;
			case !!TEMPLATE:
				return TEMPLATE;
			case !!TRIGGER:
				return TRIGGER;
			case !!TRIGGER_PROTOTYPE:
				return TRIGGER_PROTOTYPE;
			case !!USER:
				return USER;
			case !!USER_GROUP:
				return USER_GROUP;
			case !!WEB_SCENARIO:
				return WEB_SCENARIO;
			default:
				throw 'Form not found.';
		}
	}

	/**
	 * Activate tab indicators.
	 */
	activateIndicators() {
		for (const element of this.form.querySelectorAll('#' + this.tabs_id + ' a')) {
			const indicator_item = this.getIndicatorItem(this.getIndicatorNameByElement(element));

			if (indicator_item instanceof TabIndicatorItem) {
				indicator_item
					.addAttributes(element)
					.initObserver(element);
			}
		}
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

		if (attr !== null) {
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
			case 'ChildServices':
				return new ChildServicesTabIndicatorItem;
			case 'Dependency':
				return new DependencyTabIndicatorItem;
			case 'Encryption':
				return new EncryptionTabIndicatorItem;
			case 'Filters':
				return new FiltersTabIndicatorItem;
			case 'FrontendMessage':
				return new FrontendMessageTabIndicatorItem;
			case 'GraphDataset':
				return new GraphDatasetTabIndicatorItem;
			case 'GraphLegend':
				return new GraphLegendTabIndicatorItem;
			case 'GraphOptions':
				return new GraphOptionsTabIndicatorItem;
			case 'GraphOverrides':
				return new GraphOverridesTabIndicatorItem;
			case 'GraphProblems':
				return new GraphProblemsTabIndicatorItem;
			case 'GraphTime':
				return new GraphTimeTabIndicatorItem;
			case 'HttpAuth':
				return new HttpAuthTabIndicatorItem;
			case 'Media':
				return new MediaTabIndicatorItem;
			case 'MessageTemplate':
				return new MessageTemplateTabIndicatorItem;
			case 'Http':
				return new HttpTabIndicatorItem;
			case 'Inventory':
				return new InventoryTabIndicatorItem;
			case 'Ldap':
				return new LdapTabIndicatorItem;
			case 'LldMacros':
				return new LldMacrosTabIndicatorItem;
			case 'Macros':
				return new MacrosTabIndicatorItem;
			case 'Overrides':
				return new OverridesTabIndicatorItem;
			case 'Operations':
				return new OperationsTabIndicatorItem;
			case 'Permissions':
				return new PermissionsTabIndicatorItem;
			case 'Preprocessing':
				return new PreprocessingTabIndicatorItem;
			case 'Saml':
				return new SamlTabIndicatorItem;
			case 'Sharing':
				return new SharingTabIndicatorItem;
			case 'ExcludedDowntimes':
				return new ExcludedDowntimesTabIndicatorItem;
			case 'Steps':
				return new StepsTabIndicatorItem;
			case 'TagFilter':
				return new TagFilterTabIndicatorItem;
			case 'Tags':
				return new TagsTabIndicatorItem;
			case 'Time':
				return new TimeTabIndicatorItem;
			case 'Valuemaps':
				return new ValuemapsTabIndicatorItem;
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
		throw 'Fatal error: cannot call abstract method.';
	}

	/**
	 * Init observer for html changes.
	 *
	 * @param {HTMLElement} element
	 */
	initObserver(element) {
		throw 'Fatal error: cannot call abstract method.';
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

	static ZBX_PROPERTY_INHERITED = 1;

	constructor() {
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return [...document.querySelectorAll('#tbl_macros .form_row')]
			.filter((row) => {
				const macro = row.querySelector('textarea[name$="[macro]"]');
				const inherited_type = row.querySelector('input[name$="[inherited_type]"]');

				if (inherited_type !== null
						&& parseInt(inherited_type.value, 10) == MacrosTabIndicatorItem.ZBX_PROPERTY_INHERITED) {
					return false;
				}

				return (macro !== null && macro.value !== '');
			})
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

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				attributes: true,
				attributeFilter: ['value', 'style'], // Use style because textarea don't have value attribute.
				subtree: true
			});
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

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				attributes: true,
				attributeFilter: ['value', 'style'], // Use style because textarea don't have value attribute.
				subtree: true
			});
		}
	}
}

class HttpTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const element = document.querySelector('#http_auth_enabled');

		if (element !== null) {
			return element.checked;
		}

		return false;
	}

	initObserver(element) {
		const target_node = document.querySelector('#http_auth_enabled');

		if (target_node !== null) {
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

		if (element !== null) {
			return element.checked;
		}

		return false;
	}

	initObserver(element) {
		const target_node = document.querySelector('#ldap_configured');

		if (target_node !== null) {
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

		if (element !== null) {
			return element.checked;
		}

		return false;
	}

	initObserver(element) {
		const target_node = document.querySelector('#saml_auth_enabled');

		if (target_node !== null) {
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

		if (element !== null) {
			return (element.value === '0' || element.value === '1');
		}

		return false;
	}

	initObserver(element) {
		for (const input of document.querySelectorAll('[name=inventory_mode]')) {
			input.addEventListener('click', () => {
				this.addAttributes(element);
			});
		}
	}
}

class EncryptionTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const tls_connect = document.querySelector('[name=tls_connect]:checked');

		if (tls_connect !== null && (tls_connect.value === '2' || tls_connect.value === '4')) {
			return true;
		}

		const tls_in_psk = !!document.querySelector('[name=tls_in_psk]:checked');
		const tls_in_cert = !!document.querySelector('[name=tls_in_cert]:checked');

		return tls_in_psk || tls_in_cert;
	}

	initObserver(element) {
		const tls_in_psk_node = document.querySelector('[name=tls_in_psk]');

		if (tls_in_psk_node !== null) {
			['click', 'change'].forEach(event =>
				tls_in_psk_node.addEventListener(event, () => this.addAttributes(element))
			);
		}

		const tls_in_cert_node = document.querySelector('[name=tls_in_cert]');

		if (tls_in_cert_node !== null) {
			['click', 'change'].forEach(event =>
				tls_in_cert_node.addEventListener(event, () => this.addAttributes(element))
			);
		}

		for (const input of document.querySelectorAll('[name=tls_connect]')) {
			input.addEventListener('click', () => {
				this.addAttributes(element);
			});
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

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				subtree: true
			});
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

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				subtree: true
			});
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

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				attributes: true,
				attributeFilter: ['value', 'style'], // Use style because textarea don't have value attribute.
				subtree: true
			});
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

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				attributes: true,
				attributeFilter: ['value'],
				subtree: true
			});
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

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				subtree: true
			});
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

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				subtree: true
			});
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

		if (auth_node !== null) {
			auth_node.addEventListener('change', () => {
				this.addAttributes(element);
			});
		}

		for (const input of document.querySelectorAll('#verify_peer, #verify_host')) {
			input.addEventListener('click', () => {
				this.addAttributes(element);
			});
		}

		for (const input of document.querySelectorAll('#ssl_cert_file, #ssl_key_file, #ssl_key_password')) {
			input.addEventListener('change', () => {
				this.addAttributes(element);
			});
		}
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
			.querySelectorAll('#upd-table tbody tr:not(:last-child)')
			.length;

		return count;
	}

	initObserver(element) {
		const target_node_op = document.querySelector('#op-table tbody');

		if (target_node_op !== null) {
			const observer_op = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer_op.observe(target_node_op, {
				childList: true,
				subtree: true
			});
		}

		const target_node_rec = document.querySelector('#rec-table tbody');

		if (target_node_rec !== null) {
			const observer_rec = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer_rec.observe(target_node_rec, {
				childList: true,
				subtree: true
			});
		}

		const target_node_upd = document.querySelector('#upd-table tbody');

		if (target_node_upd !== null) {
			const observer_upd = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer_upd.observe(target_node_upd, {
				childList: true,
				subtree: true
			});
		}
	}
}

class ExcludedDowntimesTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('#excluded-downtimes tbody tr')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('#excluded-downtimes tbody');

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				subtree: true
			});
		}
	}
}

class ChildServicesTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelector('#children')
			.dataset
			.tabIndicator;
	}

	initObserver(element) {
		const target_node = document.querySelector('#children');

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				attributes: true,
				attributeFilter: ['data-tab-indicator']
			});
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

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				subtree: true
			});
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
		return document.querySelectorAll('#media-table tbody tr').length;
	}

	initObserver(element) {
		const target_node = document.querySelector('#media-table tbody');

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				subtree: true
			});
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

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				subtree: true
			});
		}
	}
}

class FrontendMessageTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const element = document.querySelector('#messages_enabled');

		if (element !== null) {
			return element.checked;
		}

		return false;
	}

	initObserver(element) {
		const target_node = document.querySelector('#messages_enabled');

		if (target_node !== null) {
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

		if (element !== null && element.value == 0) {
			return true;
		}

		return document.querySelectorAll('#user-group-share-table tbody tr:not(:last-child)').length > 0
			|| document.querySelectorAll('#user-share-table tbody tr:not(:last-child)').length > 0;
	}

	initObserver(element) {
		for (const input of document.querySelectorAll('[name=private]')) {
			input.addEventListener('click', () => {
				this.addAttributes(element);
			});
		}

		const target_node_group = document.querySelector('#user-group-share-table tbody');

		if (target_node_group !== null) {
			const observer_group = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer_group.observe(target_node_group, {
				childList: true,
				subtree: true
			});
		}

		const target_node_user = document.querySelector('#user-share-table tbody');

		if (target_node_user !== null) {
			const observer_user = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer_user.observe(target_node_user, {
				childList: true,
				subtree: true
			});
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

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				subtree: true
			});
		}
	}
}

class GraphOptionsTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const element = document.querySelector("[name='source']:checked");

		if (element !== null) {
			return element.value > 0;
		}

		return false;
	}

	initObserver(element) {
		for (const input of document.querySelectorAll("[name='source']")) {
			input.addEventListener('click', () => {
				this.addAttributes(element);
			});
		}
	}
}

class GraphTimeTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_MARK);
	}

	getValue() {
		const element = document.querySelector('#graph_time');

		if (element !== null) {
			return element.checked;
		}

		return false;
	}

	initObserver(element) {
		const target_node = document.querySelector('#graph_time');

		if (target_node !== null) {
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

		if (element !== null) {
			return element.checked;
		}

		return false;
	}

	initObserver(element) {
		const target_node = document.querySelector('#legend');

		if (target_node !== null) {
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

		if (element !== null) {
			return element.checked;
		}

		return false;
	}

	initObserver(element) {
		const target_node = document.querySelector('#show_problems');

		if (target_node !== null) {
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

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				subtree: true
			});
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
		document.addEventListener(TAB_INDICATOR_UPDATE_EVENT, () => {
			this.addAttributes(element);
		});
	}
}

class ValuemapsTabIndicatorItem extends TabIndicatorItem {

	constructor() {
		super(TAB_INDICATOR_TYPE_COUNT);
	}

	getValue() {
		return document
			.querySelectorAll('#valuemap-table tbody tr')
			.length;
	}

	initObserver(element) {
		const target_node = document.querySelector('#valuemap-table');

		if (target_node !== null) {
			const observer = new MutationObserver(() => {
				this.addAttributes(element);
			});

			observer.observe(target_node, {
				childList: true,
				subtree: true
			});
		}
	}
}
