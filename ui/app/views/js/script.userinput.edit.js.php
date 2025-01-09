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

window.script_userinput_popup = new class {

	#abort_controller = null;

	/**
	 * Manualinput form setup.
	 *
	 * @param {boolean} test             Indicator if this is test form.
	 * @param {int}     input_type       Manualinput type.
	 * @param {string}  default_input    Manualinput default value.
	 * @param {string}  input_validator  Manualinput validator value.
	 */
	init({test, input_type, default_input, input_validator}) {
		this.overlay = overlays_stack.getById('script-userinput-form');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.is_test = test;
		this.input_type = input_type;
		this.input_validator = input_validator;
		this.default_input = default_input;

		this.dialogue.addEventListener('dialogue.close', () => {
			if (this.#abort_controller !== null) {
				this.#abort_controller.abort();
			}
		});
	}

	submitTestForm() {
		const curl = new Curl('zabbix.php');
		const fields = getFormFields(this.form);

		fields.manualinput_validator_type = this.input_type;
		fields.manualinput_validator = this.input_validator;
		fields.manualinput_default_value = this.default_input;

		if (this.is_test) {
			fields.test = 1;
		}

		curl.setArgument('action', 'script.userinput.check');
		this.overlay.recoverFocus();

		this.#post(curl.getUrl(), fields);
	}

	submit() {
		const fields = getFormFields(this.form);

		fields.manualinput_validator_type = this.input_type;
		fields.manualinput_validator = this.input_validator;
		fields.manualinput_default_value = this.default_input;

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'script.userinput.check');

		this.#post(curl.getUrl(), fields);
	}

	#post(url, data) {
		this.#abort_controller = new AbortController();

		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data),
			signal: this.#abort_controller.signal
		})
			.then((response) => response.json())
			.then((response) => {
				let messages;

				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				if ('error' in response) {
					this.overlay.unsetLoading();
					this.overlay.recoverFocus();

					throw {error: response.error};
				}
				else if ('success' in response) {
					this.overlay.unsetLoading();
					messages = response.success.messages;

					const message_box = makeMessageBox('good', messages)[0];

					this.form.parentNode.insertBefore(message_box, this.form);
				}
				else if ('data' in response) {
					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
				}
			})
			.catch((exception) => {
				if (this.#abort_controller.signal.aborted) {
					return;
				}

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
			})
			.finally(() => {
				this.#abort_controller = null;
			});
	}
}
