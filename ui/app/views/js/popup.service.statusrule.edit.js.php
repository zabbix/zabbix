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
?>


window.service_status_rule_edit_popup = new class {

	init({rules}) {
		this.overlay = overlays_stack.getById('service_status_rule_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.footer = this.overlay.$dialogue.$footer[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);

		document
			.getElementById('service-status-rule-type')
			.addEventListener('change', () => this.#update());

		this.#update();

		this.footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
	}

	#update() {
		const type = document.getElementById('service-status-rule-type').value;

		const limit_value_label = document.getElementById('service-status-rule-limit-value-label');
		const limit_value_unit = document.getElementById('service-status-rule-limit-value-unit');

		switch (type) {
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_N_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_N_L ?>':
				limit_value_label.innerText = 'N';
				limit_value_unit.style.display = 'none';

				break;

			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_NP_L ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_WP_L ?>':
				limit_value_label.innerText = 'N';
				limit_value_unit.style.display = '';

				break;

			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_W_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_W_L ?>':
				limit_value_label.innerText = 'W';
				limit_value_unit.style.display = 'none';

				break;
		}
	}

	#submit() {
		this.#removePopupMessages();
		const fields = this.form.getAllValues();

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();

					return;
				}

				const curl = new Curl('zabbix.php');

				curl.setArgument('action', 'service.statusrule.validate');

				this.#post(curl.getUrl(), fields, (response) => {
					overlayDialogueDestroy(this.overlay.dialogueid);

					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.body}));
				});
			});
	}

	#post(url, data, success_callback) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(getFormFields(this.form_element))
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

				success_callback(response);
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this.overlay.unsetLoading());
	}

	#removePopupMessages() {
		for (const el of this.form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
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
