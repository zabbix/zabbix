<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


/**
 * @var CView $this
 */

?><script>((config) => {
const ITEM_TYPE_ZABBIX_ACTIVE = <?= ITEM_TYPE_ZABBIX_ACTIVE ?>;
const ITEM_TYPE_DEPENDENT = <?= ITEM_TYPE_DEPENDENT ?>;
const ITEM_TYPE_SIMPLE = <?= ITEM_TYPE_SIMPLE ?>;
const ITEM_VALUE_TYPE_BINARY = <?= ITEM_VALUE_TYPE_BINARY ?>;
const ZBX_STYLE_DISPLAY_NONE = <?= json_encode(ZBX_STYLE_DISPLAY_NONE) ?>;
const ZBX_STYLE_FIELD_LABEL_ASTERISK = <?= json_encode(ZBX_STYLE_FIELD_LABEL_ASTERISK) ?>;

(new class {
	init({testable_item_types, optional_interfaces, field_switches, value_type_keys, type_with_key_select}) {
		this.testable_item_types = testable_item_types;
		this.optional_interfaces = optional_interfaces;
		this.value_type_keys = value_type_keys;
		this.type_with_key_select = type_with_key_select;

		this.overlay = overlays_stack.end();
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.footer = this.overlay.$dialogue.$footer[0];

		this.initForm(field_switches);
		this.initEvents();
		this.updateFieldsVisibility();
	}

	initForm(field_switches) {
		new CViewSwitcher('authtype', 'change', field_switches.for_authtype);
		new CViewSwitcher('type', 'change', field_switches.for_type);
		new CViewSwitcher('http_authtype', 'change', field_switches.for_http_auth_type);
		new CViewSwitcher('allow_traps', 'change', field_switches.for_traps);

		this.field = {
			key: this.form.querySelector('[name="key"]'),
			key_button: this.form.querySelector('[name="key"] ~ .js-select-key'),
			type: this.form.querySelector('[name="type"]'),
			value_type: this.form.querySelector('[name="value_type"]'),
			value_type_hint: this.form.querySelector('#js-item-type-hint'),// TODO: do not use id
			value_type_steps: this.form.querySelector('[name="value_type_steps"]')
		};
	}

	initEvents() {
		// Item tab events.
		this.field.key.addEventListener('help_items.paste', e => this.#keyChangeHandler());
		this.field.key.addEventListener('keyup', e => this.#keyChangeHandler());
		this.field.key_button?.addEventListener('click', e => this.#keySelectClickHandler(e));
		this.field.type.addEventListener('click', e => this.#typeChangeHandler(e));
		this.field.value_type.addEventListener('change', e => this.#valueTypeChangeHandler(e));

		// Tags tab events.
		this.form.querySelectorAll('[name="show_inherited_tags"]')
			.forEach(o => o.addEventListener('click', e => this.#inheritedTagsChangeHandler(e)));

		// Preprocessing tab events.
		this.field.value_type_steps.addEventListener('change', e => this.#valueTypeChangeHandler(e));

		// Form actions.
		this.footer.addEventListener('click', e => {
			const classList = e.target.classList;

			if (classList.contains('js-update-item')) {
				this.updateItem();
			}
			else if (classList.contains('js-clone-item')) {
				this.cloneItem();
			}
			else if (classList.contains('js-create-item')) {
				this.createItem();
			}
		});
	}

	cloneItem() {
		// TODO: replace title, buttons of overlay, remove id, remove 'latest data'. do not make additional request
		const action = 'item.edit';
		const form_refresh = document.createElement('input');

		form_refresh.setAttribute('type', 'hidden');
		form_refresh.setAttribute('name', 'form_refresh');
		form_refresh.setAttribute('value', 1);

		this.form.append(form_refresh);
		this.form.querySelector('[name="itemid"]').remove();

		reloadPopup(this.form, action);
	}

	createItem() {
		const action = 'item.create';
		console.warn('createItem not implemented');
	}

	updateItem() {
		const action = 'item.update';
		console.warn('updateItem not implemented');
	}

	isTestableItem() {
		const key = this.field.key.value;
		const type = parseInt(this.field.type.value, 10);

		return (type != ITEM_TYPE_SIMPLE || (key.substr(0, 7) === 'vmware.' || key.substr(0, 8) === 'icmpping'))
			|| this.testable_item_types.indexOf(type) != -1;
	}

	updateActionButtons() {
		this.footer.querySelector('.js-test-item').toggleAttribute('disabled', !this.isTestableItem());
	}

	updateFieldsVisibility() {
		const type = parseInt(this.field.type.value, 10);
		const key = this.field.key.value;
		const interface_optional = this.optional_interfaces.indexOf(type) != -1;

		if (type == ITEM_TYPE_ZABBIX_ACTIVE) {
			// const toggle_fields = [
			// 	'delay',
			// 	'js-item-delay-label',
			// 	'js-item-delay-field',
			// 	'js-item-flex-intervals-label',
			// 	'js-item-flex-intervals-field'
			// ];
			// const set_hidden = (key.substr(0, 8) === 'mqtt.get'),
			// 	object_switcher = globalAllObjForViewSwitcher['type'];

			// toggle_fields.forEach((element_id) =>
			// 	object_switcher[set_hidden ? 'hideObj' : 'showObj']({id: element_id})
			// );
		}

		// this.#field('[for="interfaceid"]')
		// 	.classList.toggle(ZBX_STYLE_FIELD_LABEL_ASTERISK, !interface_optional);
		// this.#field('input[name=interfaceid]').toggleAttribute('aria-required', !interface_optional);

		this.updateActionButtons();
		this.#updateValueTypeHintVisibility();
		this.field.key_button.toggleAttribute('disabled',
			this.type_with_key_select.indexOf(parseInt(this.field.type.value, 10)) == -1
		);
	}

	#updateValueTypeHintVisibility() {
		const key = this.field.key.value;
		const value_type = this.field.value_type.value;
		const inferred_type = this.#getInferredValueType(key);

		this.field.value_type_hint
			.classList.toggle(ZBX_STYLE_DISPLAY_NONE, inferred_type === null || value_type == inferred_type);
	}

	#getInferredValueType(key) {
		const type = this.field.type.value;

		if (!(type in this.value_type_keys)) {
			return null;
		}

		if (key in this.value_type_keys[type]) {
			return this.value_type_keys[type][key];
		}

		const matches = Object.entries(this.value_type_keys[type])
							.filter(([key_name, value_type]) => key_name.startsWith(key));

		return (matches.length && matches.every(([_, value_type]) => value_type == matches[0][1]))
			? matches[0][1] : null;
	}

	#typeChangeHandler(e) {
		const disable_binary = e.target.value != ITEM_TYPE_DEPENDENT;

		if (disable_binary && this.field.value_type.value == ITEM_VALUE_TYPE_BINARY) {
			const value = this.field.value_type.getOptions().find(o => o.value != ITEM_VALUE_TYPE_BINARY).value;

			this.field.value_type.value = value;
		}

		this.field.value_type.getOptionByValue(ITEM_VALUE_TYPE_BINARY).hidden = disable_binary;
		this.field.value_type_steps.getOptionByValue(ITEM_VALUE_TYPE_BINARY).hidden = disable_binary;
		this.updateFieldsVisibility();
	}

	#valueTypeChangeHandler(e) {
		this.field.value_type.value = e.target.value;
		this.field.value_type_steps.value = e.target.value;
		this.updateFieldsVisibility();
	}

	#keyChangeHandler(e) {
		const inferred_type = this.#getInferredValueType(this.field.key.value);

		if (inferred_type !== null) {
			this.field.value_type.value = inferred_type;
		}

		this.updateFieldsVisibility();
	}

	#keySelectClickHandler(e) {
		PopUp('popup.generic', {
			srctbl: 'help_items',
			srcfld1: 'key',
			dstfrm: this.form.getAttribute('name'),
			dstfld1: 'key',
			itemtype: this.field.type.value
		}, {dialogue_class: 'modal-popup-generic'});
	}

	#inheritedTagsChangeHandler(e) {
		const form_refresh = document.createElement('input');

		form_refresh.setAttribute('type', 'hidden');
		form_refresh.setAttribute('name', 'form_refresh');
		form_refresh.setAttribute('value', 1);
		this.form.append(form_refresh);

		reloadPopup(this.form, 'item.edit');
	}

}).init(config);
})(<?= json_encode([
	'field_switches' => $data['field_switches'],
	'value_type_keys' => $data['value_type_keys'],
	'optional_interfaces' => $data['optional_interfaces'],
	'testable_item_types' => $data['testable_item_types'],
	'type_with_key_select' => $data['type_with_key_select']
]) ?>);

// function updateItemFormElements(){}// common.item.edit.js.php TODO: remove when prototype will be moved to popup
