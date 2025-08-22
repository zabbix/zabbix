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
?>


window.sla_excluded_downtime_edit_popup = new class {

	init({rules}) {
		this.overlay = overlays_stack.getById('sla_excluded_downtime_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);
	}

	submit() {
		this._removePopupMessages();

		const fields = this.form.getAllValues();

		this.overlay.setLoading();

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();

					return;
				}

				const curl = new Curl('zabbix.php');
				curl.setArgument('action', 'sla.excludeddowntime.validate');

				this._post(curl.getUrl(), fields);
			});
	}

	_post(url, data) {
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

					return;
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.body}));
			})
			.catch((exception) => {
				this._ajaxExceptionHandler(exception);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	_removePopupMessages() {
		for (const el of this.form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}

	_ajaxExceptionHandler(exception) {
		let title;
		let messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		this._addMessageBox(makeMessageBox('bad', messages, title)[0]);
	}

	_addMessageBox(message_box) {
		this._removeMessageBoxes();

		const step_form = this.dialogue.querySelector('.step-form');

		step_form.parentNode.insertBefore(message_box, step_form);
	}

	_removeMessageBoxes() {
		this.dialogue.querySelectorAll('.overlay-dialogue-body .msg-bad').forEach(message_box => message_box.remove());
	}
};
