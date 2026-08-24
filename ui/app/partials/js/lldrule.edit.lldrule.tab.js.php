<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * @var CPartial $this
 */
?>

var LldRuleEditLldRuleTab = class {
	#container;
	#field_switches;
	#host_interface_selector;
	#inherited_timeouts;
	#readonly;

	constructor({container, field_switches, lldrule, interface_types, host_interfaces, readonly, inherited_timeouts}) {
		this.#container = container;
		this.#field_switches = field_switches;
		this.#readonly = readonly;
		this.#inherited_timeouts = inherited_timeouts;

		if (!(lldrule.templated || lldrule.discovered)) {
			if (lldrule.parameters.length == 0) {
				lldrule.parameters.push({});
			}

			if (lldrule.query_fields.length == 0) {
				lldrule.query_fields.push({});
			}

			if (lldrule.headers.length == 0) {
				lldrule.headers.push({});
			}
		}

		if (!lldrule.discovered && lldrule.delay_flex.length == 0) {
			lldrule.delay_flex.push({});
		}

		$('#parameters-table').dynamicRows({
			template: '#parameter-row-tmpl',
			rows: lldrule.parameters,
			allow_empty: true
		});

		$('#query-fields-table').dynamicRows({
			template: '#query-field-row-tmpl',
			rows: lldrule.query_fields,
			allow_empty: true,
			sortable: true,
			sortable_options: {
				target: 'tbody',
				selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				selector_span: ':not(.error-container-row)',
				freeze_end: 1,
				enable_sorting: !(lldrule.templated || lldrule.discovered)
			}
		});

		jQuery('#headers-table').dynamicRows({
			template: '#item-header-row-tmpl',
			rows: lldrule.headers,
			allow_empty: true,
			sortable: true,
			sortable_options: {
				target: 'tbody',
				selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				selector_span: ':not(.error-container-row)',
				freeze_end: 1,
				enable_sorting: !(lldrule.templated || lldrule.discovered)
			}
		});

		jQuery('#delay-flex-table').dynamicRows({
			template: '#delay-flex-row-tmpl',
			rows: lldrule.delay_flex,
			allow_empty: true
		});

		this.#host_interface_selector = new HostInterfaceSelector({
			container: this.#container, interface_types, host_interfaces,
			type: lldrule.type
		});

		this.#initEvents();
		this.#update();

		this.#container.querySelectorAll('#delay-flex-table tr.form_row').forEach(row => {
			this.#setVisibleCustomIntervalParameters(row);
		})
	}

	#initEvents() {
		new CViewSwitcher('allow_traps', 'change', this.#field_switches.for_traps);
		new CViewSwitcher('authtype', 'change', this.#field_switches.for_authtype);
		new CViewSwitcher('http_authtype', 'change', this.#field_switches.for_http_auth_type);
		new CViewSwitcher('type', 'change', this.#field_switches.for_type);

		const input_selectors = ['[name="authtype"]', '[name="custom_timeout"]', '[name="lifetime_type"]',
			'[name="enabled_lifetime_type"]', '[name="key"]', '[name="request_method"]'
		];
		const inputs = this.#container.querySelectorAll(input_selectors.join(','));

		inputs.forEach(input => {
			input.addEventListener('change', () => this.#update());
		});

		this.#container.querySelector('[name="type"]').addEventListener('change', (e) => {
			this.#host_interface_selector.setType(parseInt(e.target.value));
			this.#container.dispatchEvent(new CustomEvent('update'));

			const inherited_timeout_input = this.#container.querySelector('[name="inherited_timeout"]');
			inherited_timeout_input.value = this.#inherited_timeouts[e.target.value] || '';
			const custom_timeout_input = this.#container.querySelector('[name="custom_timeout"]:checked');

			if (custom_timeout_input.value == <?= ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED ?>) {
				this.#container.querySelector('[name="timeout"]').value = inherited_timeout_input.value;
			}

			this.#update();
		});

		this.#container.querySelector('[name="key"]').addEventListener('change', () =>
			this.#container.dispatchEvent(new CustomEvent('update'))
		);

		this.#container.querySelector('[name="snmp_oid"]').addEventListener('keyup', () =>
			this.#update()
		);

		this.#container.querySelector('.js-parseurl').addEventListener('click', (e) => this.#parseUrl(e.target));

		this.#container.querySelector('#delay-flex-table').addEventListener('click', e => {
			if (e.target.type === 'radio') {
				this.#setVisibleCustomIntervalParameters(e.target.closest('tr'))
			}
		});

		jQuery('#query-fields-table').on('tableupdate.dynamicRows', (e) => this.#updateSortOrder(e.target, 'query_fields'));
		jQuery('#headers-table').on('tableupdate.dynamicRows', (e) => this.#updateSortOrder(e.target, 'headers'));
	}

	getContainer() {
		return this.#container;
	}

	#update() {
		const type = parseInt(this.#container.querySelector('[name="type"]').value);
		const key = this.#container.querySelector('[name="key"]').value
		const username_required = type == <?= ITEM_TYPE_SSH ?> || type == <?= ITEM_TYPE_TELNET ?>;
		const ipmi_sensor_required = type == <?= ITEM_TYPE_IPMI ?> && key !== 'ipmi.get';

		this.#container.querySelector('[name="username"]')
			[username_required ? 'setAttribute' : 'removeAttribute']('aria-required', 'true');
		this.#container.querySelector('label[for="username"]').classList
			.toggle('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>', username_required);
		this.#container.querySelector('[name="ipmi_sensor"]')
			[ipmi_sensor_required ? 'setAttribute' : 'removeAttribute']('aria-required', 'true');
		this.#container.querySelector('label[for="ipmi_sensor"]').classList
			.toggle('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>', ipmi_sensor_required);

		const lifetime_type = this.#container.querySelector('[name="lifetime_type"]:checked').value;
		const enabled_lifetime_type = this.#container.querySelector('[name="enabled_lifetime_type"]:checked').value;
		const delete_immediately = lifetime_type == <?= ZBX_LLD_DELETE_IMMEDIATELY ?>;

		this.#container.querySelector('#lifetime').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>',
			lifetime_type != <?= ZBX_LLD_DELETE_AFTER ?>
		);
		this.#container.querySelector('#enabled_lifetime').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>',
			delete_immediately || enabled_lifetime_type != <?= ZBX_LLD_DISABLE_AFTER ?>
		);

		let set_hidden = false;
		const toggle_field_ids = [];

		if (type == <?= ITEM_TYPE_SIMPLE ?>) {
			toggle_field_ids.push('js-item-timeout-label', 'js-item-timeout-field');
			set_hidden = key.substring(0, 8) === 'icmpping' || key.substring(0, 7) === 'vmware.';
		}
		else if (type == <?= ITEM_TYPE_ZABBIX_ACTIVE ?>) {
			toggle_field_ids.push('delay', 'js-item-delay-label', 'js-item-delay-field',
				'js-item-flex-intervals-label', 'js-item-flex-intervals-field'
			);
			set_hidden = key.substring(0, 8) === 'mqtt.get';
		}
		else if (type == <?= ITEM_TYPE_SNMP ?>) {
			toggle_field_ids.push('js-item-timeout-label', 'js-item-timeout-field');
			const snmp_oid = this.#container.querySelector('[name="snmp_oid"]').value;
			set_hidden = snmp_oid.substring(0, 4) !== 'get[' && snmp_oid.substring(0, 5) !== 'walk[';
		}

		const object_switcher = globalAllObjForViewSwitcher['type'];

		toggle_field_ids.forEach(id =>
			object_switcher[set_hidden ? 'hideObj' : 'showObj']({id})
		);

		this.#container.querySelectorAll('.js-item-disable-resources').forEach(el =>
			el.classList.toggle('<?=ZBX_STYLE_DISPLAY_NONE ?>', delete_immediately)
		);

		if (type == <?= ITEM_TYPE_HTTPAGENT ?>) {
			const request_method = this.#container.querySelector('[name="request_method"]').value;
			const is_retrieve_mode_read_only = request_method == <?= HTTPCHECK_REQUEST_HEAD ?>;

			this.#container.querySelectorAll('[name="retrieve_mode"]').forEach(radio => {
				if (is_retrieve_mode_read_only && radio.value == <?= HTTPTEST_STEP_RETRIEVE_MODE_HEADERS ?>) {
					radio.checked = true;
				}

				radio.readOnly = is_retrieve_mode_read_only || this.#readonly;
			})
		}

		const custom_timeout = this.#container.querySelector(['[name="custom_timeout"]:checked']).value;
		const inherited_hidden = custom_timeout == <?= ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED; ?>

		this.#container.querySelector('[name="inherited_timeout"]').classList
			.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', inherited_hidden);
		this.#container.querySelector('[name="timeout"]').classList
			.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !inherited_hidden);
	}

	#updateSortOrder(table, name_field) {
		table.querySelectorAll('.form_row').forEach((row, index) => {
			for (const field of row.querySelectorAll(`[name^="${name_field}["]`)) {
				field.name = field.name.replace(/\[\d+]/g, `[${index}]`);
			}
		});
	}

	#setVisibleCustomIntervalParameters(row) {
		const flexible = row.querySelector('[name$="[type]"]:checked').value == <?= ITEM_DELAY_FLEXIBLE ?>;

		row.querySelector('[name$="[delay]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, !flexible);
		row.querySelector('[name$="[period]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, !flexible);
		row.querySelector('[name$="[schedule]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, flexible);
	}

	#parseUrl(trigger_element) {
		const field = this.#container.querySelector('[name="url"]');
		const url = parseUrlString(field.value);

		if (url === false) {
			return this.#showErrorDialog(
				<?= json_encode(_('Failed to parse URL.').BR().BR()._('URL is not properly encoded.')) ?>,
				trigger_element
			);
		}

		const has_pairs = url.pairs.length != 0;

		if (has_pairs) {
			const dynamic_rows = jQuery('#query-fields-table').data('dynamicRows');

			dynamic_rows.addRows(url.pairs);
			dynamic_rows.removeRows(row => [].filter.call(
					row.querySelectorAll('[type="text"]'),
					input => input.value === ''
				).length == 2
			);
		}

		field.value = url.url;

		if (has_pairs) {
			this.#container.dispatchEvent(new CustomEvent('update'));
		}
	}

	#showErrorDialog(body, trigger_element) {
		overlayDialogue({
			title: <?= json_encode(_('Error')) ?>,
			class: 'modal-popup',
			content: jQuery('<span>').html(body),
			buttons: [{
				title: <?= json_encode(_('Ok')) ?>,
				class: 'btn-alt',
				focused: true,
				action: function() {}
			}]
		}, {
			position: Overlay.prototype.POSITION_CENTER,
			trigger_element: jQuery(trigger_element)
		});
	}
};
