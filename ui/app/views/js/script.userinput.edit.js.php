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

window.script_userinput_popup = new class {

	init({test, input_type, default_input, input_validation}) {
		this.overlay = overlays_stack.getById('script-userinput-form');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.is_test = test;
		this.input_type = input_type;
		this.input_validation = input_validation;
		this.default_input = default_input;

		if (input_type == <?= SCRIPT_MANUALINPUT_TYPE_LIST ?> && test) {
			document.querySelector('.userinput-submit').disabled = true;
		}
	}

	test() {
		const curl = new Curl('zabbix.php');
		const fields = getFormFields(this.form);
		fields.input_type = this.input_type;
		fields.input_validation = this.input_validation;
		fields.default_input = this.default_input;

		if (this.is_test) {
			fields.test = 1;
		}

		curl.setArgument('action', 'script.userinput.check');

		this.#post(curl.getUrl(), fields);
	}

	submit() {
		const fields = getFormFields(this.form);
		fields.input_type = this.input_type;
		fields.input_validation = this.input_validation;
		fields.default_input = this.default_input;

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'script.userinput.check');
		this.#post(curl.getUrl(), fields);
	}

	#post(url, data) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
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
					throw {error: response.error};
				}
				else if ('success' in response) {
					messages = response.success.messages;

					const message_box = makeMessageBox('good', messages)[0];
					this.form.parentNode.insertBefore(message_box, this.form);
				}
				else if ('data' in response) {
					overlayDialogueDestroy(this.overlay.dialogueid);
					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
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
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
}
