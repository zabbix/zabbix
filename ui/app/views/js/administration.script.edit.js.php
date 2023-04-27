<?php declare(strict_types = 0);
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
?>

window.script_edit_popup = new class {

	init({script}) {
		this.overlay = overlays_stack.getById('script-form');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.script = script;
		this.scriptid = script.scriptid;

		this._loadView(script);
		this._initActions();

		for (const parameter of script.parameters) {
			this._addParameter(parameter);
		}
	}

	_initActions() {
		document.querySelector('#scope').dispatchEvent(new Event('change'));
		document.querySelector('#type').dispatchEvent(new Event('change'));
		document.querySelector('#enable-confirmation').dispatchEvent(new Event('change'));

		document.getElementById('js-add').addEventListener('click', () => {
			const template = new Template(document.getElementById('script-parameter-template').innerHTML);

			document
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
	_addParameter(parameter) {
		const template = new Template(document.getElementById('script-parameter-template').innerHTML);

		document
			.querySelector('#parameters-table tbody')
			.insertAdjacentHTML('beforeend', template.evaluate(parameter));
	}

	/**
	 * Compiles necessary fields for popup based on scope, type, confirmation and host group type fields.
	 *
	 * @param {object} script  The script object.
	 */
	_loadView(script) {
		this.scope = parseInt(script.scope);
		this.type = parseInt(script.type);
		this.confirmation = script.enable_confirmation;
		const type = document.querySelector('#type');

		// Load scope fields.
		document.querySelector('#scope').onchange = (e) => {
			this._hideFormFields('all');
			this._loadScopeFields(e);
			type.dispatchEvent(new Event('change'));
		};

		// Load type fields.
		type.onchange = (e) => this._loadTypeFields(script, e);

		// Update confirmation fields.
		document.querySelector('#enable-confirmation').onchange = (e) => this._loadConfirmationFields(e);

		// Test confirmation button.
		document.querySelector('#test-confirmation').addEventListener('click', (e) =>
			executeScript(null, document.querySelector('#confirmation').value, e.target)
		);

		// Host group selection.
		const hgstype = document.querySelector('#hgstype-select');
		const hostgroup_selection = document.querySelector('#host-group-selection');

		hgstype.onchange = function () {
			if (hgstype.value === '1') {
				hostgroup_selection.style.display = '';
			}
			else {
				hostgroup_selection.style.display = 'none';
			}
		}

		hgstype.dispatchEvent(new Event('change'));
		this.form.removeAttribute('style');
		this.overlay.recoverFocus();
	}

	clone({title, buttons}) {
		this.scriptid = null;

		this.overlay.setProperties({title, buttons});
		this.overlay.unsetLoading();
		this.overlay.recoverFocus();
	}

	delete() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'script.delete');
		curl.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
			<?= json_encode(CCsrfTokenHelper::get('script'), JSON_THROW_ON_ERROR) ?>
		);

		this._post(curl.getUrl(), {scriptids: [this.scriptid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.delete', {detail: response.success}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		for (let key in fields) {
			if (typeof fields[key] === 'string') {
				fields[key] = fields[key].trim();
			}
		}

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', this.scriptid === null ? 'script.create' : 'script.update');

		this._post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.success}));
		});
	}

	/**
	 * Sends a POST request to the specified URL with the provided data and executes the success_callback function.
	 *
	 * @param {string} url                 The URL to send the POST request to.
	 * @param {object} data                The data to send with the POST request.
	 * @param {callback} success_callback  The function to execute when a successful response is received.
	 */
	_post(url, data, success_callback) {
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
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	/**
	 * Displays or hides fields in the popup based on the value of selected scope.
	 *
	 * @param {object} event  The event object.
	 */
	_loadScopeFields(event) {
		if (event.target.value) {
			this.scope = parseInt(event.target.value);
		}

		const url_radio_button = document.querySelector(
			`#type input[type="radio"][value="${<?= ZBX_SCRIPT_TYPE_URL ?>}"]`
		);

		switch (this.scope) {
			case <?= ZBX_SCRIPT_SCOPE_HOST ?>:
			case <?= ZBX_SCRIPT_SCOPE_EVENT ?>:
				const show_fields = [
					'#menu-path', '#menu-path-label', '#usergroup-label', '#usergroup', '#host-access-label',
					'#host-access-field', '#enable-confirmation-label', '#enable-confirmation-field',
					'#confirmation-label', '#confirmation-field'
				];

				show_fields.forEach((field) => {
					document.querySelector(field).style.display = '';
				});

				url_radio_button.closest('li').style.display = '';
				break;

			case <?= ZBX_SCRIPT_SCOPE_ACTION ?>:
				const hide_fields = ['#menu-path', '#menu-path-label'];

				hide_fields.forEach((field) => {
					document.querySelector(field).style.display = 'none';
				});

				url_radio_button.closest('li').style.display = 'none';

				if (document.querySelector('input[name="type"]:checked').value == <?= ZBX_SCRIPT_TYPE_URL ?>) {
					const webhook = document.querySelector(`#type [value="${<?= ZBX_SCRIPT_TYPE_WEBHOOK ?>}"]`);

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
	_loadTypeFields(script, event) {
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
			document.querySelector(field).style.display = 'none';
		})

		const command_ipmi = document.querySelector('#commandipmi');
		const command = document.querySelector('#command');

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
				const authtype = document.querySelector('#authtype');

				authtype.onchange = (e) => this._loadAuthFields(e);
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

		show_fields.forEach((field) => {
			document.querySelector(field).style.display = '';
		})
	}

	/**
	 * Displays or hides fields in the popup based on the value of selected authentication method.
	 * This is relevant only when the script type is SSH.
	 *
	 * @param {object} event  The event object.
	 */
	_loadAuthFields(event) {
		this._hideFormFields('auth');

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

		show_fields.forEach((field) => {
			document.querySelector(field).style.display = '';
		});
	}

	/**
	 * Displays or hides confirmation fields in the popup based on the value of selected scope.
	 * This is relevant only when scope value is ZBX_SCRIPT_SCOPE_HOST or ZBX_SCRIPT_SCOPE_ACTION.
	 *
	 * @param {object} event  The event object.
	 */
	_loadConfirmationFields(event) {
		if (event.target.value) {
			this.confirmation = event.target.checked;
		}

		const confirmation = document.querySelector('#confirmation');
		const test_confirmation = document.querySelector('#test-confirmation');

		if (this.confirmation) {
			confirmation.removeAttribute('disabled');

			confirmation.onkeyup = function () {
				if (confirmation.value != '') {
					test_confirmation.removeAttribute('disabled');
				}
				else {
					test_confirmation.setAttribute('disabled', 'disabled');
				}
			}

			confirmation.dispatchEvent(new Event('keyup'));
		}
		else {
			confirmation.setAttribute('disabled', 'disabled');
			test_confirmation.setAttribute('disabled', 'disabled');
		}
	}

	/**
	 * Hides the specified fields from the form based on input parameter type.
	 *
	 * @param {string} type  A string indicating the type of fields to hide.
	 */
	_hideFormFields(type) {
		let fields = [];

		if (type === 'auth') {
			fields = [
				'#privatekey-label', '#privatekey-field', '#privatekey-label', '#privatekey-field', '#passphrase-label',
				'#passphrase-field', '#publickey-label', '#publickey-field', '#password-label', '#password-field'
			];
		}

		if (type === 'all') {
			document.querySelector(`#type input[type="radio"][value="${<?= ZBX_SCRIPT_TYPE_URL ?>}"]`)
				.closest('li').style.display = 'none';

			fields = [
				'#menu-path', '#menu-path-label', '#url', '#url-label', '#new-window-label', '#new-window',
				'#webhook-parameters', '#webhook-parameters-label', '#js-item-script-field', '#script-label',
				'#timeout-label', '#timeout-field', '#commands-label', '#commands', '#command-ipmi-label',
				'#command-ipmi', '#auth-type-label', '#auth-type', '#username-label', '#username-field',
				'#password-label', '#password-field', '#port-label', '#port-field', '#publickey-label',
				'#publickey-field', '#privatekey-label', '#privatekey-field', '#passphrase-label', '#passphrase-field',
				'#usergroup-label', '#usergroup', '#host-access-label', '#host-access-field',
				'#enable-confirmation-label', '#enable-confirmation-field', '#confirmation-label', '#confirmation-field'
			];
		}

		fields.forEach((field) => {
			document.querySelector(field).style.display = 'none';
		});
	}
}
