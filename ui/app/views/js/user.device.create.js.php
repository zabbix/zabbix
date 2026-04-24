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

	init({rules, admin_mode}) {
		this.#overlay = overlays_stack.getById('user.device.create');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#footer = this.#overlay.$dialogue.$footer[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);
		this.#device_uuid = null;

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', admin_mode ? 'user.device.list': 'userprofile.device.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		this.#initEvents();

		if (!admin_mode) {
			this.#overlay.setLoading();
			this.#footer.querySelector('.js-submit').classList.add('is-loading');
			this.#submit();
		}
	}

	#initEvents() {
		this.#footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
		this.#dialogue.addEventListener('dialogue.close', () => this.#device_uuid = null);
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
					this.#footer.querySelector('.js-submit').remove();
					this.#footer.querySelector('.js-cancel').remove();
					this.#overlay.setProperties({title: <?= json_encode(_('Add a device')) ?>});
					this.#form_element.querySelector('.js-qr-expires-at').textContent = response.expires_at_text;
					this.#form_element.querySelector('.form-grid').style.display = 'none';
					this.#form_element.querySelector('.qr-code-container').style.display = '';
					this.#displayQRCode(response.url);
					this.#device_uuid = response.uuid;
					this.#qr_expires_at_ms = response.expires_at * 1000;

					setTimeout(() => this.#checkDeviceStatus(), 2000);
				});
			});
	}

	#displayQRCode(url) {
		const qr_code_div = this.#form_element.querySelector('.qr-code');

		const size = qr_code_div.clientWidth;
		const qr = new QRCode(qr_code_div, {
			text: url,
			width: size,
			height: size,
			correctLevel : QRCode.CorrectLevel.L
		});
		const module_width = Math.ceil(size / qr._oQRCode.moduleCount);
		const qr_margin_width = module_width * 4;
		const margin_color = qr._htOption.colorLight;

		qr_code_div.style.border = `${qr_margin_width}px solid ${margin_color}`;
	}

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
					this.#form.setErrors(response.form_errors, true, true);
					this.#form.renderErrors();

					return;
				}

				success_callback(response);
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this.#overlay.unsetLoading());
	}

	#checkDeviceStatus() {
		if (!this.#device_uuid) {
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

				if ('success' in response && this.#device_uuid) {
					this.#device_uuid = null;
					overlayDialogueDestroy(this.#overlay.dialogueid);

					this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
				}
			})
			.catch((exception) => {
				this.#device_uuid = null;
				this.#ajaxExceptionHandler(exception);
			})
			.finally(() => {
				if (this.#device_uuid && new CDate().getTime() < this.#qr_expires_at_ms) {
					setTimeout(() => this.#checkDeviceStatus(), 1000);
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
