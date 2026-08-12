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

window.user_device_create_popup = new class {

	#form;
	#form_element;
	#dialogue;
	#footer;
	#overlay;
	#qr_expires_at_ms = null;
	#device_uuid = null;
	#admin_mode;
	#refresh_interval_countdown = null;
	#refresh_interval_device_status = null;
	#abort_controller = null;

	init({rules, admin_mode}) {
		this.#overlay = overlays_stack.getById('user.device.init.view');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#footer = this.#overlay.$dialogue.$footer[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);
		this.#admin_mode = admin_mode;
		this.#abort_controller = new AbortController();

		this.#initEvents();

		if (!this.#admin_mode) {
			this.#footer.querySelector('.js-cancel').remove();
			this.#submit();
		}
	}

	#initEvents() {
		this.#footer.querySelector('.js-submit')?.addEventListener('click', () => this.#submit());
		this.#dialogue.addEventListener('dialogue.close', () => {
			this.#abort_controller.abort();
			clearInterval(this.#refresh_interval_countdown);
			clearInterval(this.#refresh_interval_device_status);
			this.#device_uuid = null;
			const redirect_url = zabbixUrl({action: this.#admin_mode ? 'user.device.list': 'userprofile.device.list'});
			setTimeout(() => location.href = redirect_url);
		});
	}

	#submit() {
		this.#removePopupMessages();
		const fields = this.#form.getAllValues();

		this.#form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.#overlay.unsetLoading();
					return;
				}

				this.#post(zabbixUrl({action: 'user.device.init'}), fields, (response) => {
					this.#footer.querySelector('.js-submit')?.remove();
					this.#footer.querySelector('.js-cancel')?.remove();
					this.#qr_expires_at_ms = response.expires_at * 1000;
					this.#refresh_interval_countdown = setInterval(() => this.#updateCountdownMessage(), 200);

					this.#form_element.querySelector('.form-grid').classList.add('hidden');
					this.#form_element.querySelector('.js-qr-code-loading').classList.add('hidden');
					this.#form_element.querySelector('.js-qr-code-wrapper').classList.remove('hidden');
					this.#form_element.querySelector('.qr-code-container').classList.remove('hidden');
					this.#displayQRCode(response.url);
					this.#device_uuid = response.uuid;

					this.#refresh_interval_device_status = setInterval(() => this.#checkDeviceStatus(), 2000);
				});
			});
	}

	#displayQRCode(url) {
		const qr_code_div = this.#form_element.querySelector('.qr-code');

		const size = qr_code_div.clientWidth;
		new QRCode(qr_code_div, {
			text: url,
			width: size,
			height: size,
			correctLevel : QRCode.CorrectLevel.L,
			useCanvas: true,
			draw_integer: true
		});

		qr_code_div.dataset.ref = url;
		qr_code_div.removeAttribute('title');
	}

	#updateCountdownMessage() {
		const expires_in = this.#qr_expires_at_ms - new CDate().getTime();
		const div_expiration = this.#form_element.querySelector('.qr-code-expiration');

		if (expires_in <= 0) {
			div_expiration.innerText = <?= json_encode(_('QR code has expired.')) ?>;
		}
		else {
			div_expiration.innerHTML = sprintf(<?= json_encode(_('QR code will expire in %1$s.')) ?>,
				`<b>${new Date(expires_in).toISOString().slice(14, 19)}</b>`
			);
		}
	}

	#post(url, data, success_callback) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data),
			signal: this.#abort_controller.signal
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				if ('form_errors' in response) {
					this.#form.setErrors(response.form_errors, true, true);
					this.#form.renderErrors();

					return;
				}

				if (this.#overlay.$dialogue[0].isConnected) {
					success_callback(response);
				}
			})
			.catch((exception) => {
				if (this.#admin_mode) {
					this.#ajaxExceptionHandler(exception)
				}
				else if (this.#overlay.$dialogue[0].isConnected) {
					if (typeof exception === 'object' && 'error' in exception) {
						if ('title' in exception.error) {
							postMessageError(exception.error.title);
						}

						postMessageDetails('error', exception.error.messages);
					}
					else {
						postMessageDetails('error', [<?= json_encode(_('Unexpected server error.')) ?>]);
					}

					overlayDialogueDestroy(this.#overlay.dialogueid);
				}
			})
			.finally(() => this.#overlay.unsetLoading());
	}

	#checkDeviceStatus() {
		if (this.#device_uuid === null) {
			return;
		}

		const url = zabbixUrl({action: 'user.device.status', uuid: this.#device_uuid});

		fetch(url, {
			method: 'GET',
			headers: {'Content-Type': 'application/json'}
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				this.#removePopupMessages();

				if ('success' in response && this.#device_uuid) {
					postMessageError(response.success.title);

					if ('messages' in response.success) {
						postMessageDetails('success', response.success.messages);
					}

					this.#device_uuid = null;
					overlayDialogueDestroy(this.#overlay.dialogueid);
				}
			})
			.catch((exception) => {
				this.#removePopupMessages();
				this.#ajaxExceptionHandler(exception);
			})
			.finally(() => {
				if (this.#device_uuid === null || new CDate().getTime() >= this.#qr_expires_at_ms) {
					clearInterval(this.#refresh_interval_device_status);
				}
			});
	}

	#removePopupMessages() {
		for (const el of this.#form_element.parentNode.children) {
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

		this.#form_element.parentNode.insertBefore(message_box, this.#form_element);
	}
};
