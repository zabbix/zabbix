<?php
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

<script>
	window.autoreg_edit = new class {
		init({rules}) {
			this.form_element = document.getElementById('autoreg-form');
			this.form = new CForm(this.form_element, rules);
			this.psk_required = this.form_element.querySelector('#psk_required');
			this.#addEventListeners();
		}

		#addEventListeners() {
			this.form_element.addEventListener('submit', (e) => {
				if (!this.submit()) {
					e.preventDefault();
				}
			});

			this.form_element.querySelector('#tls_in_psk').addEventListener('change', () => this.#updateFormFields());

			const change_psk = this.form_element.querySelector('#change_psk');

			if (change_psk) {
				change_psk.addEventListener('click', () => {
					this.psk_required.value = 1;
					this.#updateFormFields();
				});
			}
		}

		submit() {
			this.#removePopupMessages();

			for (const el of this.form_element.parentNode.children) {
				if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
					el.parentNode.removeChild(el);
				}
			}

			const fields = this.form.getAllValues();
			const curl = new Curl(this.form_element.getAttribute('action'));

			this.form.validateSubmit(fields)
				.then((result) => {
					if (!result) {
						return;
					}

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

						if ('success' in response) {
							postMessageOk(response.success.title);

							if ('messages' in response.success) {
								postMessageDetails('success', response.success.messages);
							}

							location.href = location.href;
						}
					})
					.catch(this.ajaxExceptionHandler.bind(this));
				});
		}

		#updateFormFields() {
			const tls_in_psk = this.form_element.querySelector('#tls_in_psk').checked;

			if (tls_in_psk) {
				this.#toggle('change_psk', this.psk_required.value == 0);

				for (const field of ['tls_psk_identity', 'tls_psk']) {
					this.#toggle(field, this.psk_required.value == 1, true);
				}
			}
			else {
				this.#toggle('change_psk', false);

				for (const field of ['tls_psk_identity', 'tls_psk']) {
					this.#toggle(field, false, true);
				}
			}
		}

		#toggle(id, show, disable = false) {
			const field = this.form_element.querySelector(`#${id}`);
			const label = this.form_element.querySelector(`label[for="${id}"]`);

			if (show) {
				field.parentElement.classList.remove('<?= ZBX_STYLE_DISPLAY_NONE ?>');
				label.classList.remove('<?= ZBX_STYLE_DISPLAY_NONE ?>');
			}
			else {
				field.parentElement.classList.add('<?= ZBX_STYLE_DISPLAY_NONE ?>');
				label.classList.add('<?= ZBX_STYLE_DISPLAY_NONE ?>');
			}

			if (disable) {
				field.disabled = !show;
			}
		}

		ajaxExceptionHandler(exception) {
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

		#removePopupMessages() {
			// Clear (mostly success) messages that reside outside the <main> tag.
			clearMessages();

			// Clear (mostly error) messages from controller (if any).
			for (const el of this.form_element.parentNode.children) {
				if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
					el.parentNode.removeChild(el);
				}
			}
		}
	};
</script>
