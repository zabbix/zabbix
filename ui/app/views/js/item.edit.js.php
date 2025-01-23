<?php
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


/**
 * @var CView $this
 */

?>
(() => {
const INTERFACE_TYPE_OPT = <?= INTERFACE_TYPE_OPT ?>;
const ITEM_DELAY_FLEXIBLE = <?= ITEM_DELAY_FLEXIBLE ?>;
const ITEM_STORAGE_OFF = <?= ITEM_STORAGE_OFF ?>;
const ITEM_TYPE_DEPENDENT = <?= ITEM_TYPE_DEPENDENT ?>;
const ITEM_TYPE_IPMI = <?= ITEM_TYPE_IPMI ?>;
const ITEM_TYPE_SIMPLE = <?= ITEM_TYPE_SIMPLE ?>;
const ITEM_TYPE_SSH = <?= ITEM_TYPE_SSH ?>;
const ITEM_TYPE_SNMP = <?= ITEM_TYPE_SNMP ?>;
const ITEM_TYPE_TELNET = <?= ITEM_TYPE_TELNET ?>;
const ITEM_TYPE_ZABBIX_ACTIVE = <?= ITEM_TYPE_ZABBIX_ACTIVE ?>;
const ITEM_VALUE_TYPE_BINARY = <?= ITEM_VALUE_TYPE_BINARY ?>;
const HTTPCHECK_REQUEST_HEAD = <?= HTTPCHECK_REQUEST_HEAD ?>;
const ZBX_PROPERTY_OWN = <?= ZBX_PROPERTY_OWN ?>;
const ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED = <?= ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED ?>;
const ZBX_STYLE_BTN_GREY = <?= json_encode(ZBX_STYLE_BTN_GREY) ?>;
const ZBX_STYLE_DISPLAY_NONE = <?= json_encode(ZBX_STYLE_DISPLAY_NONE) ?>;
const ZBX_STYLE_FIELD_LABEL_ASTERISK = <?= json_encode(ZBX_STYLE_FIELD_LABEL_ASTERISK) ?>;
const ZBX_STYLE_FORM_INPUT_MARGIN = <?= json_encode(ZBX_STYLE_FORM_INPUT_MARGIN) ?>;
const ZBX_STYLE_TEXTAREA_FLEXIBLE = <?= json_encode(ZBX_STYLE_TEXTAREA_FLEXIBLE) ?>;

window.item_edit_form = new class {

	init({
		actions, field_switches, form_data, host, interface_types, inherited_timeouts, readonly, testable_item_types,
		type_with_key_select, value_type_keys, source, return_url
	}) {
		this.actions = actions;
		this.form_data = form_data;
		this.form_readonly = readonly;
		this.host = host;
		this.interface_types = interface_types;
		this.inherited_timeouts = inherited_timeouts;
		this.optional_interfaces = [];
		this.source = source;
		this.testable_item_types = testable_item_types;
		this.type_interfaceids = {};
		this.type_with_key_select = type_with_key_select;
		this.value_type_keys = value_type_keys;

		for (const type in interface_types) {
			if (interface_types[type] == INTERFACE_TYPE_OPT) {
				this.optional_interfaces.push(parseInt(type, 10));
			}
		}

		for (const host_interface of Object.values(host.interfaces)) {
			if (host_interface.type in this.type_interfaceids) {
				this.type_interfaceids[host_interface.type].push(host_interface.interfaceid);
			}
			else {
				this.type_interfaceids[host_interface.type] = [host_interface.interfaceid];
			}
		}

		this.overlay = overlays_stack.end();
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.footer = this.overlay.$dialogue.$footer[0];

		ZABBIX.PopupManager.setReturnUrl(return_url);

		this.initForm(field_switches);
		this.initEvents();
		this.#initPopupListeners();

		if (this.source === 'itemprototype') {
			this.initItemPrototypeForm();
			this.initItemPrototypeEvents();
		}

		this.updateFieldsVisibility();

		this.initial_form_fields = this.#getFormFields(this.form);
		this.form.style.display = '';
		this.overlay.recoverFocus();
	}

	initForm(field_switches) {
		new CViewSwitcher('allow_traps', 'change', field_switches.for_traps);
		new CViewSwitcher('authtype', 'change', field_switches.for_authtype);
		new CViewSwitcher('http_authtype', 'change', field_switches.for_http_auth_type);
		new CViewSwitcher('type', 'change', field_switches.for_type);
		new CViewSwitcher('value_type', 'change', field_switches.for_value_type);

		this.field = {
			custom_timeout: this.form.querySelectorAll('[name="custom_timeout"]'),
			history: this.form.querySelector('[name="history"]'),
			history_mode: this.form.querySelectorAll('[name="history_mode"]'),
			interfaceid: this.form.querySelector('[name="interfaceid"]'),
			key: this.form.querySelector('[name="key"]'),
			key_button: this.form.querySelector('[name="key"] ~ .js-select-key'),
			inherited_timeout: this.form.querySelector('[name="inherited_timeout"]'),
			snmp_oid: this.form.querySelector('[name="snmp_oid"]'),
			timeout: this.form.querySelector('[name="timeout"]'),
			trends: this.form.querySelector('[name="trends"]'),
			trends_mode: this.form.querySelectorAll('[name="trends_mode"]'),
			type: this.form.querySelector('[name="type"]'),
			url: this.form.querySelector('[name="url"]'),
			username: this.form.querySelector('[name=username]'),
			value_type: this.form.querySelector('[name="value_type"]'),
			value_type_steps: this.form.querySelector('[name="value_type_steps"]'),
			ipmi_sensor: this.form.querySelector('[name="ipmi_sensor"]'),
			request_method: this.form.querySelector('[name="request_method"'),
			retrieve_mode: this.form.querySelectorAll('[name="retrieve_mode"]')
		};
		this.label = {
			interfaceid: this.form.querySelector('[for="interfaceid"]'),
			value_type_hint: this.form.querySelector('[for="label-value-type"] .js-hint'),
			username: this.form.querySelector('[for=username]'),
			ipmi_sensor: this.form.querySelector('[for="ipmi_sensor"]'),
			history_hint: this.form.querySelector('[for="history"] .js-hint'),
			trends_hint: this.form.querySelector('[for="trends"] .js-hint')
		};
		jQuery('#parameters-table').dynamicRows({
			template: '#parameter-row-tmpl',
			rows: this.form_data.parameters,
			allow_empty: true
		});
		jQuery('#query-fields-table').dynamicRows({
			template: '#query-field-row-tmpl',
			rows: this.form_data.query_fields,
			allow_empty: true,
			sortable: true,
			sortable_options: {
				target: 'tbody',
				selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				freeze_end: 1,
				enable_sorting: !this.form_readonly
			}
		});
		jQuery('#headers-table').dynamicRows({
			template: '#item-header-row-tmpl',
			rows: this.form_data.headers,
			allow_empty: true,
			sortable: true,
			sortable_options: {
				target: 'tbody',
				selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				freeze_end: 1,
				enable_sorting: !this.form_readonly
			}
		});
		jQuery('#delay-flex-table').dynamicRows({
			template: '#delay-flex-row-tmpl',
			rows: this.form_data.delay_flex,
			allow_empty: true
		});
		this.form.querySelectorAll('#delay-flex-table .form_row')?.forEach(row => {
			const flexible = row.querySelector('[name$="[type]"]:checked').value == ITEM_DELAY_FLEXIBLE;

			row.querySelector('[name$="[delay]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, !flexible);
			row.querySelector('[name$="[period]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, !flexible);
			row.querySelector('[name$="[schedule]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, flexible);
		});

		const authtype = document.getElementById('authtype');
		const passphrase = document.getElementById('passphrase');
		const password = document.getElementById('password');

		authtype.addEventListener('change', () => {
			if (authtype.value == <?= ITEM_AUTHTYPE_PASSWORD ?>) {
				password.value = passphrase.value;
			}
			else {
				passphrase.value = password.value;
			}
		});
	}

	initItemPrototypeForm() {
		let node;
		const master_item = this.form.querySelector('#master_itemid').closest('.multiselect-control');

		node = document.createElement('div');
		node.classList.add(ZBX_STYLE_FORM_INPUT_MARGIN);
		master_item.append(node);

		node = document.createElement('button');
		node.classList.add(ZBX_STYLE_BTN_GREY);
		node.setAttribute('name', 'master-item-prototype');
		node.disabled = this.form_readonly;
		node.textContent = <?= json_encode(_('Select prototype')) ?>;
		master_item.append(node);
	}

	initEvents() {
		// Item tab events.
		this.field.key.addEventListener('help_items.paste', this.#keyChangeHandler.bind(this));
		this.field.key.addEventListener('keyup', this.#keyChangeHandler.bind(this));
		this.field.key_button?.addEventListener('click', this.#keySelectClickHandler.bind(this));
		this.field.snmp_oid.addEventListener('keyup', this.updateFieldsVisibility.bind(this));
		this.field.type.addEventListener('change', this.#typeChangeHandler.bind(this));
		this.field.value_type.addEventListener('change', this.#valueTypeChangeHandler.bind(this));
		this.field.request_method.addEventListener('change', this.updateFieldsVisibility.bind(this));
		this.form.addEventListener('click', e => {
			const target = e.target;

			switch (target.getAttribute('name')) {
				case 'custom_timeout':
				case 'history_mode':
				case 'trends_mode':
					this.updateFieldsVisibility();

					break;

				case 'parseurl':
					const url = parseUrlString(this.field.url.value);

					if (url === false) {
						return this.#showErrorDialog(target.getAttribute('error-message'), target);
					}

					if (url.pairs.length) {
						const dynamic_rows = jQuery('#query-fields-table').data('dynamicRows');

						dynamic_rows.addRows(url.pairs);
						dynamic_rows.removeRows(row => [].filter.call(
								row.querySelectorAll('[type="text"]'),
								input => input.value === ''
							).length == 2
						);
					}

					this.field.url.value = url.url;

					break;
			}
		});
		this.form.querySelector('#delay-flex-table').addEventListener('click', e => this.#intervalTypeChangeHandler(e));

		const updateSortOrder = (table, field_name) => {
			table.querySelectorAll('.form_row').forEach((row, index) => {
				for (const field of row.querySelectorAll(`[name^="${field_name}["]`)) {
					field.name = field.name.replace(/\[\d+]/g, `[${index}]`);
				}
			});
		};
		jQuery('#query-fields-table').on('tableupdate.dynamicRows', (e) => updateSortOrder(e.target, 'query_fields'));
		jQuery('#headers-table').on('tableupdate.dynamicRows', (e) => updateSortOrder(e.target, 'headers'));

		// Tags tab events.
		this.form.querySelectorAll('[name="show_inherited_tags"]')
			.forEach(o => o.addEventListener('change', this.#inheritedTagsChangeHandler.bind(this)));

		// Preprocessing tab events.
		this.field.value_type_steps.addEventListener('change', e => this.#valueTypeChangeHandler(e));
		this.form.querySelector('#processing-tab').addEventListener('click', e => {
			const target = e.target;

			if (target.matches('.element-table-add') || target.matches('.element-table-remove')) {
				this.updateFieldsVisibility();
			}
		});
	}

	#initPopupListeners() {
		const subscriptions = [];

		for (const action of ['template.edit', 'proxy.edit', 'item.edit', 'item.prototype.edit']) {
			subscriptions.push(
				ZABBIX.EventHub.subscribe({
					require: {
						context: CPopupManager.EVENT_CONTEXT,
						event: CPopupManagerEvent.EVENT_OPEN,
						action
					},
					callback: ({data, event}) => {
						if (data.action_parameters.itemid === this.form_data.itemid || this.form_data.itemid === 0) {
							return;
						}

						if (!this.#isConfirmed()) {
							event.preventDefault();
						}
					}
				})
			);
		}

		subscriptions.push(
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_END_SCRIPTING,
					action: this.overlay.dialogueid
				},
				callback: () => ZABBIX.EventHub.unsubscribeAll(subscriptions)
			})
		);
	}

	#isConfirmed() {
		const fields = this.#getFormFields(this.form);

		fields.show_inherited_tags = this.initial_form_fields.show_inherited_tags;

		if (!fields.tags.length) {
			fields.tags.push({tag: '', value: ''});
		}

		return JSON.stringify(this.initial_form_fields) === JSON.stringify(fields)
			|| window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>);
	}

	initItemPrototypeEvents() {
		this.form.querySelector('[name="master-item-prototype"]').addEventListener('click', (e) => {
			this.#openMasterItemPrototypePopup();

			return cancelEvent(e);
		});
	}

	clone() {
		this.overlay = ZABBIX.PopupManager.open(
			this.source === 'itemprototype' ? 'item.prototype.edit' : 'item.edit',
			{clone: 1, ...this.#getFormFields()}
		);
	}

	create() {
		const fields = this.#getFormFields();
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', this.actions.create);
		this.#post(curl.getUrl(), fields);
	}

	update() {
		const fields = this.#getFormFields();
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', this.actions.update);
		this.#post(curl.getUrl(), fields);
	}

	test() {
		const indexes = [].map.call(
			this.form.querySelectorAll('z-select[name^="preprocessing"][name$="[type]"]'),
			type => type.getAttribute('name').match(/preprocessing\[(?<step>[\d]+)\]/).groups.step
		);

		this.overlay.unsetLoading();
		this.#updateActionButtons();
		// Method requires form name to be set to itemForm.
		openItemTestDialog(indexes, true, true, this.footer.querySelector('.js-test-item'), -2);
	}

	delete() {
		const data = {
			context: this.form_data.context,
			itemids: [this.form_data.itemid]
		}
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', this.actions.delete);
		this.#post(curl.getUrl(), data);
	}

	clear() {
		const data = {
			context: this.form_data.context,
			itemids: [this.form_data.itemid]
		}
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'item.clear');
		this.#post(curl.getUrl(), data, true);
	}

	execute() {
		const data = {
			discovery_rule: this.form_data.discovery_rule,
			itemids: [this.form_data.itemid]
		}
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'item.execute');
		this.#post(curl.getUrl(), data, true);
	}

	updateFieldsVisibility() {
		const type = parseInt(this.field.type.value, 10);
		const key = this.field.key.value;
		const username_required = type == ITEM_TYPE_SSH || type == ITEM_TYPE_TELNET;
		const ipmi_sensor_required = type == ITEM_TYPE_IPMI && key !== 'ipmi.get';
		const interface_optional = this.optional_interfaces.indexOf(type) != -1;
		const preprocessing_active = this.form.querySelector('[name^="preprocessing"][name$="[type]"]') !== null;

		this.#updateActionButtons();
		this.#updateCustomIntervalVisibility();
		this.#updateHistoryModeVisibility();
		this.#updateTrendsModeVisibility();
		this.#updateValueTypeHintVisibility(preprocessing_active);
		this.#updateValueTypeOptionVisibility();
		this.#updateRetrieveModeVisibility();
		this.#updateTimeoutVisibility();
		this.#updateTimeoutOverrideVisibility();
		this.field.key_button?.toggleAttribute('disabled', this.type_with_key_select.indexOf(type) == -1);
		this.field.username[username_required ? 'setAttribute' : 'removeAttribute']('aria-required', 'true');
		this.label.username.classList.toggle(ZBX_STYLE_FIELD_LABEL_ASTERISK, username_required);
		this.field.interfaceid?.toggleAttribute('aria-required', !interface_optional);
		this.label.interfaceid?.classList.toggle(ZBX_STYLE_FIELD_LABEL_ASTERISK, !interface_optional);
		this.field.ipmi_sensor[ipmi_sensor_required ? 'setAttribute' : 'removeAttribute']('aria-required', 'true');
		this.label.ipmi_sensor.classList.toggle(ZBX_STYLE_FIELD_LABEL_ASTERISK, ipmi_sensor_required);
		organizeInterfaces(this.type_interfaceids, this.interface_types, parseInt(this.field.type.value, 10));
		this.form.querySelectorAll('.js-item-preprocessing-type').forEach(
			node => node.classList.toggle(ZBX_STYLE_DISPLAY_NONE, !preprocessing_active)
		);
	}

	#showErrorDialog(body, trigger_element) {
		overlayDialogue({
			title: <?= json_encode(_('Error')) ?>,
			class: 'modal-popup position-middle',
			content: jQuery('<span>').html(body),
			buttons: [{
				title: <?= json_encode(_('Ok')) ?>,
				class: 'btn-alt',
				focused: true,
				action: function() {}
			}]
		}, jQuery(trigger_element));
	}

	#getFormFields() {
		const fields = getFormFields(this.form);

		for (let key in fields) {
			switch (key) {
				case 'ipmi_sensor':
					// Value of ipmi sensor should not be trimmed.
					break;

				case 'query_fields':
				case 'headers':
					fields[key] = Object.values(fields[key]).sort(
						(a, b) => parseFloat(a.sortorder) - parseFloat(b.sortorder)
					);
					// falls through

				case 'parameters':
					for (const [i, param] of Object.entries(fields[key])) {
						fields[key][i] = {name: param.name.trim(), value: param.value.trim()}
					}

					break;

				case 'delay_flex':
					for (const [i, custom_interval] of Object.entries(fields.delay_flex)) {
						fields.delay_flex[i] = {
							type: custom_interval.type,
							delay: custom_interval.delay.trim(),
							schedule: custom_interval.schedule.trim(),
							period: custom_interval.period.trim()
						}
					}

					break;

				case 'tags':
					fields.tags = Object.values(fields.tags).reduce((tags, tag) => {
						if (!('type' in tag) || (tag.type & ZBX_PROPERTY_OWN)) {
							tags.push({tag: tag.tag.trim(), value: tag.value.trim()});
						}

						return tags;
					}, []);

					break;

				default:
					if (typeof fields[key] === 'string') {
						fields[key] = fields[key].trim();
					}

					break;
			}
		}

		if (fields.preprocessing) {
			fields.preprocessing = Object.entries(fields.preprocessing).map(p => p[1]);
		}

		return fields;
	}

	#post(url, data, keep_open = false) {
		if (this.form[CSRF_TOKEN_NAME]) {
			data[CSRF_TOKEN_NAME] = this.form[CSRF_TOKEN_NAME].value;
		}

		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				if (keep_open) {
					const message_box = makeMessageBox('good', response.success.messages, response.success.title)[0];

					this.form.parentNode.querySelectorAll('.msg-good,.msg-bad,.msg-warning')
						.forEach(node => node.remove());
					this.form.parentNode.insertBefore(message_box, this.form);
				}
				else {
					const action = (new Curl(url)).getArgument('action');

					overlayDialogueDestroy(this.overlay.dialogueid);

					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: {action, ...response}}));
				}
			})
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
				this.#updateActionButtons();
			})
			.finally(() => {
				this.overlay.unsetLoading();
				this.#updateActionButtons();
			});
	}

	#isTestableItem() {
		const key = this.field.key.value;
		const type = parseInt(this.field.type.value, 10);

		return type == ITEM_TYPE_SIMPLE
			? key.substr(0, 7) !== 'vmware.' && key.substr(0, 8) !== 'icmpping'
			: this.testable_item_types.indexOf(type) != -1;
	}

	#updateActionButtons() {
		this.footer.querySelector('.js-test-item')?.toggleAttribute('disabled', !this.#isTestableItem());
	}

	#updateCustomIntervalVisibility() {
		if (parseInt(this.field.type.value, 10) != ITEM_TYPE_ZABBIX_ACTIVE) {
			return;
		}

		const fields = ['delay', 'js-item-delay-label', 'js-item-delay-field', 'js-item-flex-intervals-label',
			'js-item-flex-intervals-field'
		];
		const action = (this.field.key.value.substr(0, 8) === 'mqtt.get') ? 'hideObj' : 'showObj';
		const switcher = globalAllObjForViewSwitcher['type'];

		fields.forEach(id => switcher[action]({id}));
	}

	#updateTagsList(table) {
		const fields = this.#getFormFields();
		const url = new Curl('zabbix.php');
		const data = {
			tags: fields.tags,
			show_inherited_tags: fields.show_inherited_tags,
			itemid: fields.itemid,
			hostid: fields.hostid
		}

		url.setArgument('action', 'item.tags.list');
		this.overlay.setLoading();

		fetch(url.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				const div = document.createElement('div');

				div.innerHTML = response.body;

				const new_table = div.firstChild;

				table.replaceWith(new_table);
				this.#initTagsTableEvents(new_table);
			})
			.finally(() => {
				this.overlay.unsetLoading();
				this.#updateActionButtons();
			});
	}

	#initTagsTableEvents(table) {
		const $table = jQuery(table);

		$table
			.dynamicRows({template: '#tag-row-tmpl', allow_empty: true})
			.on('afteradd.dynamicRows', () => {
				$(`.${ZBX_STYLE_TEXTAREA_FLEXIBLE}`, $table).textareaFlexible();
			})
			.find(`.${ZBX_STYLE_TEXTAREA_FLEXIBLE}`)
			.textareaFlexible();

		table.addEventListener('click', e => {
			const target = e.target;

			if (target.matches('.element-table-disable')) {
				const type_input = target.closest('.form_row').querySelector('input[name$="[type]"]');

				type_input.value &= ~ZBX_PROPERTY_OWN;
			}
		});
	}

	#updateTimeoutOverrideVisibility() {
		const custom_timeout = [].filter.call(this.field.custom_timeout, e => e.matches(':checked')).pop();
		const inherited_hidden = custom_timeout.value == ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED;

		this.form.inherited_timeout.classList.toggle(ZBX_STYLE_DISPLAY_NONE, inherited_hidden);
		this.form.timeout.classList.toggle(ZBX_STYLE_DISPLAY_NONE, !inherited_hidden);
	}

	#updateTimeoutVisibility() {
		let action = '';
		const key = this.field.key.value;
		const type = parseInt(this.field.type.value, 10);
		const switcher = globalAllObjForViewSwitcher['type'];
		const toggle_fields = ['js-item-timeout-label', 'js-item-timeout-field'];

		switch (type) {
			case ITEM_TYPE_SIMPLE:
				action = key.substring(0, 8) === 'icmpping' || key.substring(0, 7) === 'vmware.' ? 'hideObj' : 'showObj';
				break;

			case ITEM_TYPE_ZABBIX_ACTIVE:
				action = key.substring(0, 8) === 'mqtt.get' ? 'hideObj' : 'showObj';
				break;

			case ITEM_TYPE_SNMP:
				let snmp = this.field.snmp_oid.value.substring(0, 5);

				action = snmp.substring(0, 5) !== 'walk[' && snmp.substring(0, 4) !== 'get[' ? 'hideObj' : 'showObj';
				break;
		}

		if (action !== '') {
			toggle_fields.forEach(id => switcher[action]({id}));
		}
	}

	#updateRetrieveModeVisibility() {
		const is_readonly = this.field.request_method.value == HTTPCHECK_REQUEST_HEAD;

		this.field.retrieve_mode.forEach(radio => {
			if (is_readonly && radio.value == <?= HTTPTEST_STEP_RETRIEVE_MODE_HEADERS ?>) {
				radio.checked = true;
			}

			radio.readOnly = is_readonly || this.form_readonly;
		});
	}

	#updateValueTypeHintVisibility(preprocessing_active) {
		const key = this.field.key.value;
		const value_type = this.field.value_type.value;
		const inferred_type = this.#getInferredValueType(key);

		this.label.value_type_hint.classList.toggle(ZBX_STYLE_DISPLAY_NONE,
			preprocessing_active || inferred_type === null || value_type == inferred_type
		);
	}

	#updateHistoryModeVisibility() {
		const mode_field = [].filter.call(this.field.history_mode, e => e.matches(':checked')).pop();
		const disabled = mode_field.value == ITEM_STORAGE_OFF && !mode_field.readOnly;

		this.field.history.toggleAttribute('disabled', disabled);
		this.field.history.classList.toggle(ZBX_STYLE_DISPLAY_NONE, disabled);
		this.label.history_hint?.classList.toggle(ZBX_STYLE_DISPLAY_NONE, disabled);
	}

	#updateTrendsModeVisibility() {
		const mode_field = [].filter.call(this.field.trends_mode, e => e.matches(':checked')).pop();
		const disabled = mode_field.value == ITEM_STORAGE_OFF && !mode_field.readOnly;

		this.field.trends.toggleAttribute('disabled', disabled);
		this.field.trends.classList.toggle(ZBX_STYLE_DISPLAY_NONE, disabled);
		this.label.trends_hint?.classList.toggle(ZBX_STYLE_DISPLAY_NONE, disabled);
	}

	#updateValueTypeOptionVisibility() {
		const disable_binary = this.field.type.value != ITEM_TYPE_DEPENDENT;

		if (disable_binary && this.field.value_type.value == ITEM_VALUE_TYPE_BINARY) {
			const value = this.field.value_type.getOptions().find(o => o.value != ITEM_VALUE_TYPE_BINARY).value;

			this.field.value_type.value = value;
			this.field.value_type_steps.value = value;
		}

		this.field.value_type.getOptionByValue(ITEM_VALUE_TYPE_BINARY).hidden = disable_binary;
		this.field.value_type_steps.getOptionByValue(ITEM_VALUE_TYPE_BINARY).hidden = disable_binary;
	}

	#getInferredValueType(key) {
		const type = this.field.type.value;
		const search = key.split('[')[0].trim().toLowerCase();

		if (!(type in this.value_type_keys) || search === '') {
			return null;
		}

		if (search in this.value_type_keys[type]) {
			return this.value_type_keys[type][search];
		}

		const matches = Object.entries(this.value_type_keys[type])
							.filter(([key_name, value_type]) => key_name.startsWith(search));

		return (matches.length && matches.every(([_, value_type]) => value_type == matches[0][1]))
			? matches[0][1] : null;
	}

	#intervalTypeChangeHandler(e) {
		const target = e.target;

		if (!target.matches('[name$="[type]"]') || target.hasAttribute('readonly')) {
			return;
		}

		const row = target.closest('.form_row');
		const flexible = target.value == ITEM_DELAY_FLEXIBLE;

		row.querySelector('[name$="[delay]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, !flexible);
		row.querySelector('[name$="[period]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, !flexible);
		row.querySelector('[name$="[schedule]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, flexible);
	}

	#valueTypeChangeHandler(e) {
		this.field.value_type.value = e.target.value;
		this.field.value_type_steps.value = e.target.value;
		this.updateFieldsVisibility();
	}

	#typeChangeHandler(e) {
		this.field.inherited_timeout.value = this.inherited_timeouts[e.target.value] || '';

		const custom_timeout_value = [...this.field.custom_timeout].filter(element => element.checked)[0].value;

		if (custom_timeout_value != ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED) {
			this.field.timeout.value = this.field.inherited_timeout.value;
		}

		this.updateFieldsVisibility();
	}

	#keyChangeHandler() {
		const inferred_type = this.#getInferredValueType(this.field.key.value);

		if (inferred_type !== null) {
			this.field.value_type.value = inferred_type;
		}

		this.updateFieldsVisibility();
	}

	#keySelectClickHandler() {
		PopUp('popup.generic', {
			srctbl: 'help_items',
			srcfld1: 'key',
			dstfrm: this.form.getAttribute('name'),
			dstfld1: 'key',
			itemtype: this.field.type.value
		}, {dialogue_class: 'modal-popup-generic'});
	}

	#inheritedTagsChangeHandler(e) {
		this.#updateTagsList(this.form.querySelector('.tags-table'));
	}

	#openMasterItemPrototypePopup() {
		const parameters = {
			srctbl: 'item_prototypes',
			srcfld1: 'itemid',
			srcfld2: 'name',
			dstfrm: this.form.getAttribute('name'),
			dstfld1: 'master_itemid',
			parent_discoveryid: this.form_data.parent_discoveryid,
			excludeids: 'itemid' in this.form_data ? [this.form_data.itemid] : [],
		}

		PopUp('popup.generic', parameters, {dialogue_class: 'modal-popup-generic'});
	}
}
})();
