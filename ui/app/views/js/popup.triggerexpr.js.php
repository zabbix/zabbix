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


?>

window.trigger_edit_expression_popup = new class {

	init({functions, function_types, is_new}) {
		this.overlay = overlays_stack.getById('trigger-expr');
		this.dialogue = this.overlay.$dialogue[0];
		this.form_element = document.getElementById('expression-form');
		this.form = new CForm(this.form_element, {});
		this.functions = functions;
		this.function_types = function_types;
		this.#setFilterFunctions(document.getElementById('item_value_type').getAttribute('value'), false);
		this.#functionSelectChanged(document.getElementById('function-select').getAttribute('value'), false);
		this.#initEvents();

		this.form_element.style.display = '';
		this.overlay.recoverFocus();

		if (!is_new) {
			this.form.validateSubmit(this.form.getAllValues());
		}
	}

	#setFilterFunctions(item_type, change_function = true) {
		item_type = (item_type !== null) ? parseInt(item_type) : item_type;

		const zselect = document.getElementById('function-select');
		let current_value = zselect.getAttribute('value');
		let current_function_added = false

		const option_groups = {};

		const attachOption = (type, function_name, label, is_disabled) => {
			if (!(type in option_groups)) {
				option_groups[type] = {
					label: this.function_types[type],
					options: []
				};
			}

			option_groups[type].options.push({
				value: `${type}_${function_name}`,
				label,
				is_disabled
			});
		};

		for (const [function_name, function_config] of Object.entries(this.functions)) {
			for (const [type, type_config] of Object.entries(function_config.types)) {
				if (item_type === null || type_config.allowed_types.includes(item_type)) {
					attachOption(type, function_name, function_config['description'], false);

					if (!current_function_added && current_value === `${type}_${function_name}`) {
						current_function_added = true;
					}
				}
				else if (!change_function && current_value === `${type}_${function_name}`) {
					attachOption(type, function_name, function_config['description'], true);
				}
			}
		}

		zselect.clearOptions();

		if (change_function && !current_function_added) {
			zselect.setAttribute('value', '3_last');
			this.#functionSelectChanged('3_last', true);
		}
		else {
			zselect.setAttribute('value', current_value);
		}

		zselect.addOptions(Object.values(option_groups));
		zselect.preselectHightlighted();
	}

	#setVisibleParamtypes(zselect, options, reset_value) {
		const mapped_options = options.reduce((res, value) => {
			res[value] = 1;
			return res;
		}, {});

		let current_value = zselect.getAttribute('value');

		if (reset_value || !mapped_options[current_value]) {
			current_value = options[0];
			zselect.setAttribute('value', options[0]);
		}

		const paramtype = zselect.parentNode.querySelector('.paramtype');

		if (Object.values(options).length > 1) {
			zselect.style.display = 'inline-grid';
			paramtype.style.display = 'none';
		}
		else {
			zselect.style.display = 'none';
			paramtype.style.display = 'inline-grid';
			paramtype.innerText = zselect.querySelector(`ul li[value="${current_value}"]`).innerText;
		}
	}

	#setVisibleOptions(zselect, options, reset_value) {
		const mapped_options = options.map(option => {
			return {label: option, value: option};
		});

		let current_value = zselect.getAttribute('value');

		if (!options.includes(current_value)) {
			if (reset_value) {
				current_value = options[0];
			}
			else {
				mapped_options.push({label: current_value, value: current_value});
			}
		}

		zselect.clearOptions();
		zselect.addOptions(mapped_options);
		zselect.setAttribute('value', current_value);
	}

	#functionSelectChanged(value, reset_paramtype = true) {
		const [function_type, function_name] = value.split('_');
		document.getElementById('function').value = function_name;
		document.getElementById('function_type').value = function_type;

		this.#functionChanged(function_name, function_type, reset_paramtype);
	}

	#getAllowedFieldsKeyedByName(name, type) {
		if (!(name in this.functions)) {
			return {};
		}

		const result = {};

		for (const [key, config] of Object.entries(this.functions[name]['types'][type].parameters)) {
			if (key === 'params') {
				for (const [param_key, param_config] of Object.entries(config)) {
					result[`params[${param_key}]`] = param_config;
				}
			}
			else {
				result[key] = config;
			}
		}

		return result;
	}

	#functionChanged(name, type, reset_paramtype) {
		const allowed_field_configs = this.#getAllowedFieldsKeyedByName(name, type);

		for (const discovered_field of CForm.findAllFields(this.form_element)) {
			const field_name = discovered_field.getAttribute('name');

			if (!(field_name.startsWith('params[')
				|| ['itemid', 'result', 'operator', 'paramtype'].includes(field_name))) {
				continue;
			}

			const field_config = field_name in allowed_field_configs ? allowed_field_configs[field_name] : null;

			if (field_config) {
				if (field_name === 'paramtype') {
					this.#setVisibleParamtypes(discovered_field.closest('z-select'), field_config.options,
						reset_paramtype
					)
				}
				else if (field_name === 'operator') {
					this.#setVisibleOptions(discovered_field.closest('z-select'), field_config.options,
						reset_paramtype
					);
				}
				else if (field_config.placeholder) {
					discovered_field.setAttribute('placeholder', field_config.placeholder);
				}

				if (field_config.label) {
					const label = discovered_field.closest('li').querySelector('label');
					label.innerText = field_config.label;

					if (field_config.required) {
						label.classList.add('form-label-asterisk');
					}
					else {
						label.classList.remove('form-label-asterisk');
					}
				}

				discovered_field.removeAttribute('disabled', 'disabled');
				discovered_field.closest('li').style.display = '';
			}
			else {
				discovered_field.setAttribute('disabled', 'disabled');
				discovered_field.closest('li').style.display = 'none';
			}
		}

		if (document.getElementById('itemid').value === '') {
			document.getElementById('itemid').setAttribute('disabled', 'disabled');
			document.getElementById('item_value_type').setAttribute('disabled', 'disabled');
		}

		if (name in this.functions) {
			const rules = this.functions[name]['types'][type].rules;
			this.form.reload(rules, false);
			const fields = CForm.findAllFields(this.form_element).map(el => el.getAttribute('name'));
			this.form.validateChanges(fields);
		}
	}

	selectItem(is_prototype) {
		const fields = this.form.getAllValues();
		let popup_options;

		if (is_prototype) {
			popup_options = {
				srctbl: 'item_prototypes',
				srcfld1: 'itemid',
				srcfld2: 'name',
				dstfrm: this.form_element.getAttribute('name'),
				dstfld1: 'itemid',
				dstfld2: 'item_description',
				parent_discoveryid: fields.parent_discoveryid,
				value_types: [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG,
					ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT
				]
			}
		}
		else {
			popup_options = {
				srctbl: fields.context === 'host' ? 'items' : 'template_items',
				srcfld1: 'itemid',
				srcfld2: 'name',
				dstfrm: this.form_element.getAttribute('name'),
				dstfld1: 'itemid',
				dstfld2: 'item_description',
				writeonly: 1,
				value_types: [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG,
					ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT
				]
			}

			if (fields.context === 'host') {
				if ('hostid' in fields && fields.hostid && fields.hostid !== 0) {
					popup_options.hostid = fields.hostid;
				}

				popup_options.real_hosts = 1;

				if (!('parent_discoveryid' in fields)) {
					popup_options.normal_only = 1;
				}
			}
			else if ('hostid' in fields && fields.hostid !== 0) {
				popup_options.templateid = fields.hostid;
			}
		}

		const item_field = document.getElementById('item_description');
		const orig_value = fields.item_description;
		const mode = is_prototype ? 'item_prototype' : 'item';
		const popup = PopUp('popup.generic', popup_options, {dialogue_class: 'modal-popup-generic'});

		popup.$dialogue[0].addEventListener('dialogue.close', () => {
			if (item_field.value !== orig_value) {
				this.#promiseGetItemType(document.getElementById('itemid').value, mode)
					.then((type) => {
						document.getElementById('item_value_type').setAttribute('value', type);
						document.getElementById('itemid').removeAttribute('disabled');
						document.getElementById('item_value_type').removeAttribute('disabled');
						this.form.validateChanges(['itemid', 'item_value_type']);
						this.#setFilterFunctions(type);
					});
			}
		});
	}

	#submit() {
		this.overlay.setLoading();
		this.#clearMessages();
		const fields = this.form.getAllValues();

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();

					return;
				}

				const curl = new Curl(this.form_element.getAttribute('action'));

				curl.setArgument('action', 'popup.triggerexpr.check');

				fetch(curl.getUrl(), {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify(fields)
				})
					.then((response) => response.json())
					.then((response) => {
						if ('error' in response) {
							throw {error: response.error};
						}

						if ('form_errors' in response) {
							this.form.setErrors(response.form_errors, true, true);
							this.form.renderErrors();

							return;
						}

						this.overlay.$dialogue[0]
							.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
					})
					.catch(this.#ajaxExceptionHandler)
					.finally(() => {
						this.overlay.unsetLoading();
					});
			});
	}

	#initEvents() {
		document.getElementById('function-select').addEventListener('change', (e) => {
			this.#functionSelectChanged(e.target.value);
		});

		document.getElementById('select').addEventListener('click', () => {
			this.selectItem(0);
		});

		document.getElementById('select-item-prototype')?.addEventListener('click', () => {
			this.selectItem(1);
		});

		const select_paramtype = this.form_element.querySelector('z-select[name="paramtype"]');

		if (select_paramtype) {
			select_paramtype.addEventListener('change', () => {
				this.form.validateChanges(['params[last]']);
			})
		}

		this.overlay.$dialogue.$footer[0].querySelector('.js-submit')
			.addEventListener('click', () => this.#submit());
	}

	#promiseGetItemType(itemid, mode) {
		const curl = new Curl('jsrpc.php');

		curl.setArgument('method', `${mode}_value_type.get`);
		curl.setArgument('type', <?= PAGE_TYPE_TEXT_RETURN_JSON ?>);
		curl.setArgument('itemid', itemid);

		return fetch(curl.getUrl())
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return parseInt(response.result);
			})
			.catch((exception) => {
				console.log('Could not get item type', exception);

				return null;
			});
	}

	#clearMessages() {
		for (const element of this.form_element.parentNode.children) {
			if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
				element.parentNode.removeChild(element);
			}
		}
	}

	#ajaxExceptionHandler(exception) {
		const form = window.trigger_edit_expression_popup.form_element;

		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		form.parentNode.insertBefore(message_box, form);
	}
}
