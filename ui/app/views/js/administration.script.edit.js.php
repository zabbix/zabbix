<?php declare(strict_types = 0);
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

window.script_edit_popup = new class {

	init({script}) {
		this.overlay = overlays_stack.getById('script.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.script = script;
		this.scriptid = script.scriptid;

		const backurl = new Curl('zabbix.php');

		backurl.setArgument('action', 'script.list');
		this.overlay.backurl = backurl.getUrl();

		this.#loadView(script);
		this.#initActions();

		for (const parameter of script.parameters) {
			this.#addParameter(parameter);
		}

		new CFormFieldsetCollapsible(document.getElementById('advanced-configuration'));
	}

	#initActions() {
		this.form.querySelector('#scope').dispatchEvent(new Event('change'));
		this.form.querySelector('#type').dispatchEvent(new Event('change'));
		this.form.querySelector('#enable_confirmation').dispatchEvent(new Event('change'));

		this.form.querySelector('.js-parameter-add').addEventListener('click', () => {
			const template = new Template(this.form.querySelector('#script-parameter-template').innerHTML);

			this.form
				.querySelector('#parameters-table tbody')
				.insertAdjacentHTML('beforeend', template.evaluate({}));
		});

		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-remove')) {
				e.target.closest('tr').remove();
			}
		});
	}

	/**
	 * Adds a new row to the Parameters table with the given parameter data (name, value).
	 *
	 * @param {object} parameter  The parameter object.
	 */
	#addParameter(parameter) {
		const template = new Template(this.form.querySelector('#script-parameter-template').innerHTML);

		this.form
			.querySelector('#parameters-table tbody')
			.insertAdjacentHTML('beforeend', template.evaluate(parameter));
	}

	/**
	 * Compiles necessary fields for popup based on scope, type, confirmation and host group type fields.
	 *
	 * @param {object} script  The script object.
	 */
	#loadView(script) {
		this.scope = parseInt(script.scope);
		this.type = parseInt(script.type);
		this.confirmation = script.enable_confirmation;

		const type = this.form.querySelector('#type');

		// Load scope fields.
		this.form.querySelector('#scope').addEventListener('change', (e) => {
			this.#hideFormFields('all');
			this.#loadScopeFields(e);
			type.dispatchEvent(new Event('change'));
		});

		// Load type fields.
		type.addEventListener('change', (e) => this.#loadTypeFields(script, e));

		// Update user input fields.
		this.form.querySelector('#manualinput').addEventListener('change', (e) => this.#loadUserInputFields(e));
		this.form.querySelector('#manualinput').dispatchEvent(new Event('change'));


		// Update confirmation fields.
		this.form.querySelector('#enable_confirmation').addEventListener('change', (e) =>
			this.#loadConfirmationFields(e)
		);

		// Test user input button.
		this.form.querySelector('#test_user_input').addEventListener('click', () => this.#openManualinputTestPopup());

		// Test confirmation button.
		this.form.querySelector('#test_confirmation').addEventListener('click', (e) => {
			if (this.form.querySelector('input[name="type"]:checked').value == <?= ZBX_SCRIPT_TYPE_URL ?>) {
				Script.openUrl(null, this.form.querySelector('#confirmation').value, e.target);
			}
			else {
				Script.execute(null, this.form.querySelector('#confirmation').value, e.target)
			}
		});

		// Host group selection.
		const hgstype = this.form.querySelector('#hgstype-select');
		const hostgroup_selection = this.form.querySelector('#host-group-selection');

		hgstype.addEventListener('change', () =>
			hostgroup_selection.style.display = hgstype.value === '1' ? '' : 'none'
		);

		hgstype.dispatchEvent(new Event('change'));
		this.form.removeAttribute('style');
		this.overlay.recoverFocus();

		// Load manual input fields based on input type.
		const input_prompt = this.form.querySelector('#manualinput_prompt');
		const test_user_input = this.form.querySelector('#test_user_input');
		const dropdown_options = this.form.querySelector('#dropdown_options');

		for (const button of document.querySelectorAll('[name="manualinput_validator_type"]')) {
			button.addEventListener('click', (e) => {
				if (e.target.value != undefined) {
					this.input_type = e.target.value;
				}

				if (this.input_type == <?= ZBX_SCRIPT_MANUALINPUT_TYPE_STRING ?>) {
					this.#updateManualinputFields(test_user_input, input_prompt,
						this.form.querySelector('#manualinput_validator'), this.input_type
					);
				}
				else {
					dropdown_options.disabled = !this.user_input_checked;

					this.#updateManualinputFields(test_user_input, input_prompt, dropdown_options, this.input_type);
				}
			});
		}
	}

	#openManualinputTestPopup() {
		const input_validation = this.input_type == <?= ZBX_SCRIPT_MANUALINPUT_TYPE_STRING ?>
			? this.form.querySelector('#manualinput_validator').value
			: this.form.querySelector('#dropdown_options').value;

		const default_input = this.input_type == <?= ZBX_SCRIPT_MANUALINPUT_TYPE_STRING ?>
			? this.form.querySelector('#manualinput_default_value').value
			: '';

		const parameters = {
			manualinput_prompt: this.form.querySelector('#manualinput_prompt').value,
			manualinput_default_value: default_input,
			manualinput_validator_type: this.input_type,
			manualinput_validator: input_validation,
			test: 1
		};

		PopUp('script.userinput.edit', parameters, {
			dialogueid: 'script-userinput-form',
			dialogue_class: 'modal-popup-small'
		});
	}

	clone({title, buttons}) {
		this.scriptid = null;

		for (const input of this.form.querySelectorAll('input[name=scope]')) {
			input.disabled = false;
		}

		this.overlay.setProperties({title, buttons});
		this.overlay.unsetLoading();
		this.overlay.recoverFocus();
	}

	delete() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'script.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('script')) ?>);

		this.#post(curl.getUrl(), {scriptids: [this.scriptid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		for (let key in fields) {
			if (typeof fields[key] === 'string' && key !== 'confirmation') {
				fields[key] = fields[key].trim();
			}
		}

		if (typeof fields.parameters !== 'undefined') {
			fields.parameters.name = fields.parameters.name.map(name => name.trim());
			fields.parameters.value = fields.parameters.value.map(value => value.trim());
		}

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', this.scriptid === null ? 'script.create' : 'script.update');

		this.#post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
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

				return response;
			})
			.then(success_callback)
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title;
				let messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => this.overlay.unsetLoading());
	}

	/**
	 * Displays or hides fields in the popup based on the value of selected scope.
	 *
	 * @param {object} event  The event object.
	 */
	#loadScopeFields(event) {
		if (event.target.value) {
			this.scope = parseInt(event.target.value);
		}

		const url_radio_button = this.form.querySelector(
			`#type input[type="radio"][value="${<?= ZBX_SCRIPT_TYPE_URL ?>}"]`
		);

		switch (this.scope) {
			case <?= ZBX_SCRIPT_SCOPE_HOST ?>:
			case <?= ZBX_SCRIPT_SCOPE_EVENT ?>:
				const show_fields = [
					'#menu-path', '#menu-path-label', '#usergroup-label', '#usergroup', '#host-access-label',
					'#host-access-field', '#advanced-configuration'
				];

				show_fields.forEach((field) => {
					this.form.querySelector(field).style.display = '';
				});

				url_radio_button.closest('li').style.display = '';
				break;

			case <?= ZBX_SCRIPT_SCOPE_ACTION ?>:
				const hide_fields = ['#menu-path', '#menu-path-label'];

				hide_fields.forEach((field) => {
					this.form.querySelector(field).style.display = 'none';
				});

				url_radio_button.closest('li').style.display = 'none';

				if (this.form.querySelector('input[name="type"]:checked').value == <?= ZBX_SCRIPT_TYPE_URL ?>) {
					const webhook = this.form.querySelector(`#type [value="${<?= ZBX_SCRIPT_TYPE_WEBHOOK ?>}"]`);

					webhook.checked = true;
					this.type = parseInt(<?= ZBX_SCRIPT_TYPE_WEBHOOK ?>);
				}
				break;
		}
	}

	/**
	 * Displays or hides fields in the popup based on the value of selected type.
	 *
	 * @param {object} script  The script object.
	 * @param {object} event   The event object.
	 */
	#loadTypeFields(script, event) {
		if (event.target.value) {
			this.type = parseInt(event.target.value);
		}

		let show_fields = [];
		const hide_fields = [
			'#command-ipmi-label', '#command-ipmi', '#webhook-parameters', '#webhook-parameters-label',
			'#js-item-script-field', '#script-label', '#timeout-label', '#timeout-field', '#auth-type-label',
			'#auth-type', '#username-label', '#username-field', '#password-label', '#password-field',
			'#publickey-label', '#publickey-field', '#privatekey-label', '#privatekey-field', '#passphrase-label',
			'#passphrase-field', '#port-label', '#port-field', '#url', '#url-label', '#new-window-label', '#new-window',
			'#execute-on-label', '#execute-on', '#commands-label', '#commands'
		];

		hide_fields.forEach((field) => {
			this.form.querySelector(field).style.display = 'none';
		})

		const command_ipmi = this.form.querySelector('#commandipmi');
		const command = this.form.querySelector('#command');

		switch (this.type) {
			case <?= ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT ?>:
				if (command_ipmi.value !== '') {
					command.value = command_ipmi.value;
					command_ipmi.value = '';
				}

				show_fields = ['#execute-on-label', '#execute-on', '#commands-label', '#commands'];
				break;

			case <?= ZBX_SCRIPT_TYPE_IPMI ?>:
				if (command.value !== '') {
					command_ipmi.value = command.value;
					command.value = '';
				}

				show_fields = ['#command-ipmi-label', '#command-ipmi'];
				break;

			case <?= ZBX_SCRIPT_TYPE_SSH ?>:
				if (command_ipmi.value !== '') {
					command.value = command_ipmi.value;
					command_ipmi.value = '';
				}

				show_fields = [
					'#auth-type-label', '#auth-type', '#username-label', '#username-field', '#port-label',
					'#port-field', '#commands-label', '#commands'
				];

				// Load authentication fields.
				this.authtype = parseInt(script.authtype);

				const authtype = this.form.querySelector('#authtype');

				authtype.addEventListener('change', (e) => this.#loadAuthFields(e));
				authtype.dispatchEvent(new Event('change'));
				break;

			case <?= ZBX_SCRIPT_TYPE_TELNET ?>:
				if (command_ipmi.value !== '') {
					command.value = command_ipmi.value;
					command_ipmi.value = '';
				}

				show_fields = [
					'#username-label', '#username-field', '#port-label', '#port-field', '#password-label',
					'#password-field', '#commands-label', '#commands'
				];
				break;

			case <?= ZBX_SCRIPT_TYPE_WEBHOOK ?>:
				show_fields = [
					'#webhook-parameters', '#webhook-parameters-label', '#js-item-script-field', '#script-label',
					'#timeout-label', '#timeout-field'
				];

				break;

			case <?= ZBX_SCRIPT_TYPE_URL ?>:
				show_fields = ['#url', '#url-label', '#new-window-label', '#new-window'];
				break;
		}

		show_fields.forEach((field) => this.form.querySelector(field).style.display = '');
	}

	/**
	 * Displays or hides fields in the popup based on the value of selected authentication method.
	 * This is relevant only when the script type is SSH.
	 *
	 * @param {object} event  The event object.
	 */
	#loadAuthFields(event) {
		this.#hideFormFields('auth');

		let show_fields = [];

		if (event.target.value) {
			this.authtype = parseInt(event.target.value);
		}

		switch (this.authtype) {
			case <?= ITEM_AUTHTYPE_PASSWORD ?>:
				show_fields = ['#password-label', '#password-field', '#commands-label', '#commands'];
				break;

			case <?= ITEM_AUTHTYPE_PUBLICKEY ?>:
				show_fields = [
					'#publickey-label', '#publickey-field', '#privatekey-label', '#privatekey-field',
					'#passphrase-label', '#passphrase-field'
				];
				break;
		}

		show_fields.forEach((field) => this.form.querySelector(field).style.display = '');
	}

	/**
	 * Displays or hides and enables or disables user input fields in the Advanced configuration.
	 * This is relevant only when scope value is ZBX_SCRIPT_SCOPE_HOST or ZBX_SCRIPT_SCOPE_EVENTS.
	 *
	 * @param {object} event  The event object.
	 */
	#loadUserInputFields(event) {
		if (event.target.value) {
			this.user_input_checked = event.target.checked;
		}

		const input_prompt = this.form.querySelector('#manualinput_prompt');
		const test_user_input = this.form.querySelector('#test_user_input');
		const input_type = this.form.querySelector('#manualinput_validator_type');
		const default_input = this.form.querySelector('#manualinput_default_value');
		const input_validation = this.form.querySelector('#manualinput_validator');
		const dropdown_options = this.form.querySelector('#dropdown_options');

		this.input_type = this.form.querySelector('input[name="manualinput_validator_type"]:checked').value;

		this.#updateManualinputFields(test_user_input, input_prompt, input_validation, this.input_type);

		const elements = [input_prompt, test_user_input, default_input, input_validation, dropdown_options];

		elements.forEach(element => element.disabled = !this.user_input_checked);
		input_type.querySelectorAll('input').forEach((element) => element.disabled = !this.user_input_checked);

		if (this.user_input_checked) {
			this.form.querySelector('label[for="manualinput_prompt"]').classList
				.add('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');
		}
		else {
			this.form.querySelector('label[for="manualinput_prompt"]').classList
				.remove('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');
		}

		const validator = this.input_type == <?= ZBX_SCRIPT_MANUALINPUT_DISABLED ?>
			? this.form.querySelector('#manualinput_validator')
			: this.form.querySelector('#dropdown_options');

		this.#updateManualinputFields(test_user_input, input_prompt, validator, this.input_type);
	}

	#updateManualinputFields(test_user_input, input_prompt, validator) {
		const is_input_type_string = this.input_type == <?= ZBX_SCRIPT_MANUALINPUT_TYPE_STRING ?>

		this.form.querySelector('label[for=manualinput_default_value]').style.display = is_input_type_string
			? ''
			: 'none';
		this.form.querySelector('#manualinput_default_value').parentNode.style.display = is_input_type_string
			? ''
			: 'none';

		this.form.querySelector('label[for=manualinput_validator]').style.display = is_input_type_string ? '' : 'none';
		this.form.querySelector('#manualinput_validator').parentNode.style.display = is_input_type_string ? '' : 'none';

		this.form.querySelector('label[for=dropdown_options]').style.display = is_input_type_string ? 'none' : '';
		this.form.querySelector('#dropdown_options').parentNode.style.display = is_input_type_string ? 'none' : '';

		if (this.user_input_checked) {
			document.querySelector(`label[for="${validator.name}"]`).classList
				.add('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');
		}
		else {
			document.querySelector(`label[for="${validator.name}"]`).classList
				.remove('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');
		}

		const updateTestUserInput = () => test_user_input.disabled = !(
			input_prompt.value.trim() !== '' && validator.value.trim() !== '' && this.user_input_checked
		);

		input_prompt.onkeyup = updateTestUserInput;
		validator.onkeyup = updateTestUserInput;

		input_prompt.dispatchEvent(new Event('keyup'));
		validator.dispatchEvent(new Event('keyup'));
	}

	/**
	 * Displays or hides confirmation fields in the popup based on the value of selected scope.
	 * This is relevant only when scope value is ZBX_SCRIPT_SCOPE_HOST or ZBX_SCRIPT_SCOPE_EVENT.
	 *
	 * @param {object} event  The event object.
	 */
	#loadConfirmationFields(event) {
		if (event.target.value) {
			this.confirmation = event.target.checked;
		}

		const confirmation = this.form.querySelector('#confirmation');
		const test_confirmation = this.form.querySelector('#test_confirmation');

		if (this.confirmation) {
			this.form.querySelector('label[for="confirmation"]').classList.add('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');

			confirmation.removeAttribute('disabled');

			confirmation.onkeyup = () => confirmation.value !== ''
				? test_confirmation.removeAttribute('disabled')
				: test_confirmation.setAttribute('disabled', 'disabled');

			confirmation.dispatchEvent(new Event('keyup'));
		}
		else {
			this.form.querySelector('label[for="confirmation"]').classList
				.remove('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>');
			confirmation.setAttribute('disabled', 'disabled');
			test_confirmation.setAttribute('disabled', 'disabled');
		}
	}

	/**
	 * Hides the specified fields from the form based on input parameter type.
	 *
	 * @param {string} type  A string indicating the type of fields to hide.
	 */
	#hideFormFields(type) {
		let fields = [];

		if (type === 'auth') {
			fields = [
				'#privatekey-label', '#privatekey-field', '#privatekey-label', '#privatekey-field', '#passphrase-label',
				'#passphrase-field', '#publickey-label', '#publickey-field', '#password-label', '#password-field'
			];
		}

		if (type === 'all') {
			this.form.querySelector(`#type input[type="radio"][value="${<?= ZBX_SCRIPT_TYPE_URL ?>}"]`)
				.closest('li').style.display = 'none';

			fields = [
				'#menu-path', '#menu-path-label', '#url', '#url-label', '#new-window-label', '#new-window',
				'#webhook-parameters', '#webhook-parameters-label', '#js-item-script-field', '#script-label',
				'#timeout-label', '#timeout-field', '#commands-label', '#commands', '#command-ipmi-label',
				'#command-ipmi', '#auth-type-label', '#auth-type', '#username-label', '#username-field',
				'#password-label', '#password-field', '#port-label', '#port-field', '#publickey-label',
				'#publickey-field', '#privatekey-label', '#privatekey-field', '#passphrase-label', '#passphrase-field',
				'#usergroup-label', '#usergroup', '#host-access-label', '#host-access-field',
				'#advanced-configuration'
			];
		}

		fields.forEach((field) => this.form.querySelector(field).style.display = 'none');
	}
}
