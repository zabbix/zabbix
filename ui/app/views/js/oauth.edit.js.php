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
window.oauth_edit_popup = new class {

	constructor() {
		this.overlay = null;
		this.dialogue = null;
		this.form = null;
		this.form_element = null;
		this.is_advanced_form = false;
		this.oauth_popup = null;
		this.messages = {};
	}

	init({rules, is_advanced_form, messages}) {
		this.overlay = overlays_stack.end();
		this.dialogue = this.overlay.$dialogue[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules)
		this.is_advanced_form = is_advanced_form;
		this.messages = messages;

		this.#initForm();
		this.#initFormEvents();

		this.#updateFieldsVisibility();
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
				curl.setArgument('action', 'oauth.check');

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
							throw null;
						}

						return response;
					})
					.then(response => this.#popupAuthenticate(response.oauth_popup_url, response.oauth)
						.then(response => this.#submitDataToOpener(response), (error) => {
							if (this.is_advanced_form) {
								this.form_element.querySelector('[name="authorization_mode"][value="manual"]').click();
								this.form_element.querySelector('[name="code"]').focus();

								return Promise.reject({title: null, messages: [this.messages.authorization_error]});
							}

							return Promise.reject(error);
						})
					)
					.catch(error => {
						if (error) {
							this.#ajaxExceptionHandler(error);
						}
					})
					.finally(() => this.overlay.unsetLoading());
			});
	}

	#initForm() {
		if (this.is_advanced_form) {
			const options = {
				template: '#oauth-parameter-row-tmpl',
				allow_empty: false,
				sortable: true,
				sortable_options: {
					target: 'tbody',
					selector_span: ':not(.error-container-row)',
					selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
					freeze_end: 1
				}
			}

			this.#initDynamicRows('[name="authorization_url"]', '#oauth-auth-parameters-table', options);
			this.#initDynamicRows('[name="token_url"]', '#oauth-token-parameters-table', options);
		}
	}

	#initFormEvents() {
		this.form_element.addEventListener('change', e => this.#formChangeEvent(e));

		const redirect_url_field = this.form_element.querySelector('#oauth-redirection-field');

		redirect_url_field.querySelector('.js-copy-button').addEventListener('click', e => {
			const input = redirect_url_field.querySelector('[name="redirection_url"]');

			writeTextClipboard(input.value);
			e.target.focus();
		});

		this.form_element.querySelector('button[name="client_secret_button"]')?.addEventListener('click', e => {
			const input = this.form_element.querySelector('[name="client_secret"]');

			e.target.remove();
			input.style.display = '';
			input.removeAttribute('disabled');
			input.focus();
		});

		this.overlay.$dialogue.$footer[0].querySelector('.js-add')
			.addEventListener('click', () => this.#submit());
	}

	#initDynamicRows(url_selector, parameters_selector, options) {
		const url_element = this.form_element.querySelector(url_selector);
		const url = parseUrlString(url_element.value);
		const input_name = url_element.getAttribute('name');

		options.rows = url.pairs.filter(row => row.name !== '' || row.value !== '');
		options.rows = options.rows.map(row => ({...row, input_name}));
		url_element.value = url.url;

		options.dataCallback = (row) => ({...row, input_name});

		jQuery(parameters_selector).dynamicRows(
			options.rows.length ? options : {...options, rows: [{name: '', value: '', input_name}]}
		);
	}

	#formChangeEvent(event) {
		const target = event.target;

		if (target.matches('[name="authorization_mode"]')) {
			this.#updateFieldsVisibility();
		}
	}

	#updateFieldsVisibility() {
		if (this.is_advanced_form) {
			const automatic = this.form_element.querySelector('[name="authorization_mode"][value="auto"]');

			this.form_element.querySelector('[name="code"]').toggleAttribute('disabled', automatic.checked);
		}
	}

	#submitDataToOpener(detail) {
		overlayDialogueDestroy(this.overlay.dialogueid);
		this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail}));
	}

	#popupAuthenticate(oauth_popup_url, server_data) {
		const width = 500;
		const height = 600;
		const popup = window.open(oauth_popup_url, 'oauthpopup',
			`width=${width},height=${height},left=${(screen.width - width)/2},top=${(screen.height - height)/2}`
		);

		this.oauth_popup = popup;

		return new Promise((resolve, reject) => {
			if (popup === null) {
				reject({title: null, messages: [this.messages.popup_blocked_error]});

				return;
			}

			window.addEventListener('message', function (e) {
				if (e.source === popup) {
					clearInterval(oauth_popup_guard);
					popup.close();
					resolve({...server_data, ...e.data});
				}

				window.removeEventListener('message', this);
			});

			const oauth_popup_guard = setInterval(() => {
				if (!popup.closed) {
					return;
				}

				clearInterval(oauth_popup_guard);
				reject({title: null, messages: [this.messages.popup_closed]});
			}, 500);
		});
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
}();
