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
		const WEB_SCENARIO = document.querySelector('#httpForm');
		const ACTION = document.querySelector("[id='action.edit']");
		const SERVICE = document.querySelector('#servicesForm');
		const PROXY = document.querySelector('#proxyForm');
		const USER_GROUP = document.querySelector('#userGroupForm');
		const USER = document.querySelector('#userForm');
		const MEDIA_TYPE = document.querySelector('#media_type_form');
		const MAP = document.querySelector('#mapEditForm');

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

		return TabIndicatorFactory.createTabIndicator(class_name)
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
			case 'Steps':
				return new StepsTabIndicator;
			case 'HttpAuth':
				return new HttpAuthTabIndicator;
			case 'Operations':
				return new OperationsTabIndicator;
			case 'ServiceDependency':
				return new ServiceDependencyTabIndicator;
			case 'Time':
				return new TimeTabIndicator;
			case 'TagFilter':
				return new TagFilterTabIndicator;
			case 'Media':
				return new MediaTabIndicator;
			case 'MessageTemplate':
				return new MessageTemplateTabIndicator;
			case 'FrontendMessage':
				return new FrontendMessageTabIndicator;
			case 'Sharing':
				return new SharingTabIndicator;
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
		// FIXME: not working

		const target_node = document.querySelector('#conditions tbody');
		const observer_options = {
			childList: true,
			attributes: true,
			// attributeFilter: ['value', 'style'], // Use style because textarea dont have value attribute.
			subtree: true,
			characterData: true,
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

class StepsTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
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

class HttpAuthTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatus;
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

class OperationsTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
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

class ServiceDependencyTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
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

class TimeTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
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

class TagFilterTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatus;
	}

	getValue() {
		return document
			.querySelectorAll('#tag-filter-table tbody tr')
			.length > 0;
	}

	initObserver(elem) {
		// FIXME: table is replaced by ajax.

		const target_node = document.querySelector('#tag-filter-table');
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

class MediaTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
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

class MessageTemplateTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorNumber;
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

class FrontendMessageTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatus;
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

class SharingTabIndicator extends TabIndicatorCallback {

	constructor() {
		super();
		this.TYPE = new TabIndicatorStatus;
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
