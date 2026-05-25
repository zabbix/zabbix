<?php declare(strict_types = 0);
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
 * @var CView $this
 */
?>

window.script_edit_popup = new class {

	init({rules, clone_rules, script}) {
		this.overlay = overlays_stack.getById('script.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.footer = this.overlay.$dialogue.$footer[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);
		this.script = script;
		this.scriptid = script.scriptid;
		this.saved_scope_value = script.scope;
		this.saved_menu_path_value = '';
		this.clone_rules = clone_rules;

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'script.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		this.#initView(script);
		this.#initActions(rules);
	}

	#initView(script) {
		for (const parameter of script.parameters) {
			this.#addParameter(parameter);
		}

		new CFormFieldsetCollapsible(document.getElementById('advanced-configuration'));

		this.#updateForm();
		this.form_element.removeAttribute('style');
		this.overlay.recoverFocus();
	}

	#initActions(rules) {
		this.form_element.querySelectorAll('#scope, #type, #authtype, #hgstype-select, #manualinput,' +
				' #manualinput_validator_type ,#enable_confirmation, #confirmation').forEach(
			node => node.addEventListener('change', (e) => {
				if (e.target.name === 'scope') {
					const is_scope_action =
						this.form.findFieldByName('scope').getValue() == <?= ZBX_SCRIPT_SCOPE_ACTION ?>;
					const is_type_url =
						this.form.findFieldByName('type').getValue() == <?= ZBX_SCRIPT_TYPE_URL ?>;

					if (is_scope_action && is_type_url) {
						this.form_element.querySelector(`#type [value="${<?= ZBX_SCRIPT_TYPE_WEBHOOK ?>}"]`)
							.checked = true;
					}

					if (is_scope_action) {
						this.saved_menu_path_value = this.form.findFieldByName('menu_path').getValue();
						this.form_element.querySelector('[name="menu_path"]').value = '';
					}
					else if (this.saved_scope_value == <?= ZBX_SCRIPT_SCOPE_ACTION ?>) {
						this.form_element.querySelector('[name="menu_path"]').value = this.saved_menu_path_value;
					}

					this.saved_scope_value = this.form.findFieldByName('scope').getValue();
				}
				else if (e.target.name === 'type') {
					const command_ipmi = document.getElementById('commandipmi');
					const command = document.getElementById('command');

					switch (this.form.findFieldByName('type').getValue()) {
						case <?= ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT ?>:
						case <?= ZBX_SCRIPT_TYPE_SSH ?>:
						case <?= ZBX_SCRIPT_TYPE_TELNET ?>:
							if (command_ipmi.value !== '') {
								command.value = command_ipmi.value;
								command_ipmi.value = '';
							}
							break;

						case <?= ZBX_SCRIPT_TYPE_IPMI ?>:
							if (command.value !== '') {
								command_ipmi.value = command.value;
								command.value = '';
							}
							break;
					}
				}

				this.#updateForm();
			})
		);

		this.form_element.querySelector('.js-parameter-add').addEventListener('click', () => {
			this.#addParameter({name: '', value: ''});
		});

		this.footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
		this.footer.querySelector('.js-clone')?.addEventListener('click', () => this.#clone());
		this.footer.querySelector('.js-delete')?.addEventListener('click', () => this.#delete());

		this.form_element.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-remove')) {
				const row = e.target.closest('tr');
				row.nextSibling.remove();
				row.remove();
			}
		});

		// Test user input button.
		document.getElementById('test_user_input').addEventListener('click', () => {
			this.overlay.setLoading();
			this.form.findFieldByName('manualinput_prompt').setChanged();
			this.form.findFieldByName('manualinput_validator').setChanged();
			this.form.findFieldByName('dropdown_options').setChanged();
			this.form.validateFieldsForAction(
				['manualinput_prompt', 'manualinput_validator', 'dropdown_options'],
				rules
			).then((result) => {
				this.overlay.unsetLoading();

				if (result) {
					this.#openManualinputTestPopup();
				}
			});
		});

		// Test confirmation button.
		document.getElementById('test_confirmation').addEventListener('click', event => {
			this.overlay.setLoading();
			this.form.findFieldByName('confirmation').setChanged();
			this.form.validateFieldsForAction(['confirmation'], rules)
				.then((result) => {
					this.overlay.unsetLoading();

					if (result) {
						if (this.form.findFieldByName('type').getValue() == <?= ZBX_SCRIPT_TYPE_URL ?>) {
							Script.openUrl(null, this.form.findFieldByName('confirmation').getValue(), event.target);
						}
						else {
							Script.execute(null, this.form.findFieldByName('confirmation').getValue(), event.target);
						}
					}
				});
		});
	}

	#updateForm() {
		const data = this.form.getAllValues();
		const is_scope_manual = data.scope == <?= ZBX_SCRIPT_SCOPE_HOST ?>
			|| data.scope == <?= ZBX_SCRIPT_SCOPE_EVENT ?>;

		const is_enable_user_input = data.manualinput == 1;
		const is_enable_confirmation = data.enable_confirmation == 1;
		// manualinput_validator_type radio might be disabled. In this case it's field return value as null.
		const input_type_value =
			this.form_element.querySelector('input[name="manualinput_validator_type"]:checked').value;
		const is_input_type_string = input_type_value == <?= ZBX_SCRIPT_MANUALINPUT_TYPE_STRING ?>;
		const is_input_type_list = input_type_value == <?= ZBX_SCRIPT_MANUALINPUT_TYPE_LIST ?>;

		this.form_element.querySelectorAll('#menu-path-label, #menu-path, #usergroup-label, #usergroup,' +
				' #host-access-label, #host-access-field, #advanced-configuration').forEach(
			node => node.style.display = is_scope_manual ? '' : 'none'
		);

		const type_url_radio_button = this.form_element.querySelector(
			`#type input[type="radio"][value="${<?= ZBX_SCRIPT_TYPE_URL ?>}"]`
		);

		type_url_radio_button.closest('li').style.display = is_scope_manual ? '' : 'none';

		this.form_element.querySelectorAll('#execute-on-label, #execute-on').forEach(
			node => node.style.display = data.type == <?= ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT ?> ? '' : 'none'
		);

		this.form_element.querySelectorAll('#auth-type-label, #auth-type').forEach(
			node => node.style.display = data.type == <?= ZBX_SCRIPT_TYPE_SSH ?> ? '' : 'none'
		);

		this.form_element.querySelectorAll('#username-label, #username-field, #port-label, #port-field').forEach(
			node => node.style.display =
				data.type == <?= ZBX_SCRIPT_TYPE_SSH ?>
					|| data.type == <?= ZBX_SCRIPT_TYPE_TELNET ?>
				? '' : 'none'
		);

		this.form_element.querySelectorAll('#password-label, #password-field').forEach(
			node => node.style.display =
				data.type == <?= ZBX_SCRIPT_TYPE_TELNET ?>
					|| (data.type == <?= ZBX_SCRIPT_TYPE_SSH ?>
						&& data.authtype == <?= ITEM_AUTHTYPE_PASSWORD ?>)
				? '' : 'none'
		);

		this.form_element.querySelectorAll('#publickey-label, #publickey-field, #privatekey-label, #privatekey-field,' +
				' #passphrase-label, #passphrase-field').forEach(
			node => node.style.display =
				data.type == <?= ZBX_SCRIPT_TYPE_SSH ?>
					&& data.authtype == <?= ITEM_AUTHTYPE_PUBLICKEY ?>
				? '' : 'none'
		);

		this.form_element.querySelectorAll('#commands-label, #commands').forEach(
			node => node.style.display =
				data.type == <?= ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT ?>
					|| data.type == <?= ZBX_SCRIPT_TYPE_SSH ?>
					|| data.type == <?= ZBX_SCRIPT_TYPE_TELNET ?>
			? '' : 'none'
		);

		this.form_element.querySelectorAll('#command-ipmi-label, #command-ipmi').forEach(
			node => node.style.display = data.type == <?= ZBX_SCRIPT_TYPE_IPMI ?> ? '' : 'none'
		);

		this.form_element.querySelectorAll('#webhook-parameters-label, #webhook-parameters, #script-label,' +
				' #js-item-script-field, #timeout-label, #timeout-field').forEach(
			node => node.style.display = data.type == <?= ZBX_SCRIPT_TYPE_WEBHOOK ?> ? '' : 'none'
		);

		this.form_element.querySelectorAll('#url-label, #url, #new-window-label, #new-window').forEach(
			node => node.style.display = data.type == <?= ZBX_SCRIPT_TYPE_URL ?> ? '' : 'none'
		);

		document.getElementById('host-group-selection').style.display = data.hgstype == 1 ? '' : 'none';
		jQuery(document.getElementById('groupid')).multiSelect(data.hgstype == 1 ? 'enable' : 'disable');

		// Advanced configuration

		this.form_element.querySelectorAll('label[for=manualinput_default_value], label[for=manualinput_validator]')
			.forEach(
				node => node.style.display = is_input_type_string ? '' : 'none'
			);

		this.form_element.querySelectorAll('#manualinput_default_value, #manualinput_validator').forEach(
			node => node.parentNode.style.display = is_input_type_string ? '' : 'none'
		);

		this.form_element.querySelector('label[for=dropdown_options]').style.display = is_input_type_list ? '' : 'none';
		this.form_element.querySelector('#dropdown_options').parentNode.style.display = is_input_type_list
			? '' : 'none';

		this.form_element.querySelectorAll('#manualinput_prompt, input[name=manualinput_validator_type],' +
				' #manualinput_default_value, #manualinput_validator, #dropdown_options, #test_user_input').forEach(
			node => node.disabled = !is_enable_user_input
		);

		this.form_element.querySelectorAll('#confirmation, #test_confirmation').forEach(
			node => node.disabled = !is_enable_confirmation
		);

		document.querySelector(`label[for="manualinput_prompt"]`).classList
			.toggle('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>', is_enable_user_input);
		document.querySelector(`label[for="manualinput_validator"]`).classList
			.toggle('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>', is_enable_user_input);
		document.querySelector(`label[for="dropdown_options"]`).classList
			.toggle('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>', is_enable_user_input);
		document.querySelector(`label[for="confirmation"]`).classList
			.toggle('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>', is_enable_confirmation);
	}

	/**
	 * Adds a new row to the Parameters table with the given parameter data (name, value).
	 *
	 * @param {object} parameter  The parameter object.
	 */
	#addParameter(parameter) {
		const template = new Template(document.getElementById('script-parameter-template').innerHTML);
		const parameters_table_element = this.form_element.querySelector('#parameters-table tbody');
		let row_index = 0;

		while (parameters_table_element.querySelector(`[name="parameters[${row_index}][name]"]`) !== null) {
			row_index++;
		}

		parameters_table_element.insertAdjacentHTML('beforeend', template.evaluate({row_index, ...parameter}));
	}

	#openManualinputTestPopup() {
		const fields = this.form.getAllValues();

		const parameters = {
			manualinput_prompt: fields.manualinput_prompt,
			manualinput_default_value: fields.manualinput_validator_type == <?= ZBX_SCRIPT_MANUALINPUT_TYPE_STRING ?>
				? fields.manualinput_default_value
				: '',
			manualinput_validator_type: fields.manualinput_validator_type,
			manualinput_validator: fields.manualinput_validator_type == <?= ZBX_SCRIPT_MANUALINPUT_TYPE_STRING ?>
				? fields.manualinput_validator
				: fields.dropdown_options,
			test: 1
		};

		PopUp('script.userinput.edit', parameters, {
			dialogueid: 'script-userinput-form',
			dialogue_class: 'modal-popup-small'
		});
	}

	#clone() {
		this.#clearMessages();
		this.scriptid = null;
		document.getElementById('scriptid').remove();

		this.form.reload(this.clone_rules);

		for (const input of this.form_element.querySelectorAll('input[name=scope]')) {
			input.readOnly = false;
		}

		this.overlay.setProperties({
			title: <?= json_encode(_('New script')) ?>,
			buttons: [
				{
					title: <?= json_encode(_('Add')) ?>,
					class: 'js-submit',
					keepOpen: true,
					isSubmit: true
				},
				{
					title: <?= json_encode(_('Cancel')) ?>,
					class: <?= json_encode(ZBX_STYLE_BTN_ALT) ?>,
					cancel: true,
					action: ''
				}
			]
		});

		this.footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());

		this.overlay.unsetLoading();
		this.overlay.recoverFocus();
		this.overlay.containFocus();
	}

	#delete() {
		if (window.confirm(<?= json_encode(_('Delete script?')) ?>)) {
			this.#clearMessages();
			const url_params = {
				action: 'script.delete',
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('script')) ?>
			};

			this.#post(zabbixUrl(url_params), {scriptids: [this.scriptid]}, (response) => {
				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			});
		}
		else {
			this.overlay.unsetLoading();
		}
	}

	#submit() {
		this.#clearMessages();
		const fields = this.form.getAllValues();

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();

					return;
				}

				const action = this.scriptid === null ? 'script.create' : 'script.update';

				this.#post(zabbixUrl({action}), fields, (response) => {
					overlayDialogueDestroy(this.overlay.dialogueid);

					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
				});
			});
	}

	/**
	 * Sends a POST request to the specified URL with the provided data and executes the success_callback function.
	 *
	 * @param {string}   url               The URL to send the POST request to.
	 * @param {object}   data              The data to send with the POST request.
	 * @param {callback} success_callback  The function to execute when a successful response is received.
	 */
	#post(url, data, success_callback) {
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

				if ('form_errors' in response) {
					this.form.setErrors(response.form_errors, true, true);
					this.form.renderErrors();
				}
				else {
					success_callback(response);
				}
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this.overlay.unsetLoading());
	}

	#clearMessages() {
		for (const element of this.form_element.parentNode.children) {
			if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
				element.parentNode.removeChild(element);
			}
		}
	}

	#ajaxExceptionHandler(exception) {
		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		this.form_element.parentNode.insertBefore(message_box, this.form_element);
	}
};
