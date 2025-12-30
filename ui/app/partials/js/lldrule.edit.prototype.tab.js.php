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
 * @var CPartial $this
 */
?>

var LldRuleEditPrototypeTab = class {
	#container;
	#field_switches;
	#host_interface_selector;

	constructor({container, field_switches, lldrule, interface_types, host_interfaces}) {
		this.#container = container;
		this.#field_switches = field_switches;

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
			rows: lldrule.delay_flex
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
		const input_selectors = ['[name="authtype"]', '[name="custom_timeout"]', '[name="lifetime_type"]',
			'[name="enabled_lifetime_type"]'
		];
		const inputs = this.#container.querySelectorAll(input_selectors.join(','));

		inputs.forEach(input => {
			input.addEventListener('change', () => this.#update());
		});

		this.#container.querySelector('[name="type"]').addEventListener('change', (e) => {
			this.#host_interface_selector.setType(parseInt(e.target.value, 10));
			this.#container.dispatchEvent(new CustomEvent('update'))
		});

		this.#container.querySelector('[name="key"]').addEventListener('change', () =>
			this.#container.dispatchEvent(new CustomEvent('update'))
		);

		this.#container.querySelector('.js-parseurl').addEventListener('click', (e) => this.#parseUrl(e.target));

		new CViewSwitcher('authtype', 'change', this.#field_switches.for_authtype);
		new CViewSwitcher('type', 'change', this.#field_switches.for_type);
		new CViewSwitcher('http_authtype', 'change', this.#field_switches.for_http_auth_type);
		new CViewSwitcher('allow_traps', 'change', this.#field_switches.for_traps);

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
		const lifetime_type = this.#container.querySelector('[name="lifetime_type"]:checked').value;
		const enabled_lifetime_type = this.#container.querySelector('[name="enabled_lifetime_type"]:checked').value;
		const delete_immediately = lifetime_type == <?= ZBX_LLD_DELETE_IMMEDIATELY ?>;

		this.#container.querySelector('#lifetime').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>',
			lifetime_type != <?= ZBX_LLD_DELETE_AFTER ?>
		);
		this.#container.querySelector('#enabled_lifetime').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>',
			delete_immediately || enabled_lifetime_type != <?= ZBX_LLD_DISABLE_AFTER ?>
		);

		this.#container.querySelectorAll('.js-item-disable-resources').forEach(el =>
			el.classList.toggle('<?=ZBX_STYLE_DISPLAY_NONE ?>', delete_immediately)
		);

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
