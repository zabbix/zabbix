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
?>//<script>
window.oauth_edit_popup = new class {

	constructor() {
		this.overlay = null;
		this.dialogue = null;
		this.form = null;
		this.advanced_form = false;
		this.oauth_popup = null;
		this.messages = {};
	}

	init({oauth, advanced_form, messages}) {
		this.overlay = overlays_stack.end();
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.advanced_form = advanced_form;
		this.messages = messages;

		this.#initForm();
		this.#initFormEvents();

		this.updateFieldsVisibility();
	}

	updateFieldsVisibility() {
		if (this.advanced_form) {
			const automatic = this.form.querySelector('[name="authorization_mode"][value="auto"]');

			this.form.querySelector('[name="code"]').toggleAttribute('disabled', automatic.checked);
		}
	}

	submit() {
		const oauth = getFormFields(this.form);
		const width = 500;
		const height = 600;
		this.oauth_popup = window.open(
			this.#getOauthPopupUrl(oauth),
			'oauthpopup',
			`width=${width},height=${height},left=${(screen.width - width)/2},top=${(screen.height - height)/2}`
		);

		this.#getTokensFromOauthService(this.oauth_popup)
			.then((tokens) => this.#addTokenFormFields(tokens), (reject) => {
				if (this.advanced_form) {
					this.form.querySelector('[name="authorization_mode"][value="manual"]').click();
					this.form.querySelector('[name="code"]').focus();

					throw new Error(this.messages.authorization_error);
				}

				if (reject.error) {
					throw new Error(reject.error);
				}
			})
			.then(() => {
				const values = getFormFields(this.form);
				const detail = {
					access_expires_in: values.access_expires_in,
					access_token: values.access_token,
					authorization_url: this.#getUrl(values.authorization_url, Object.values(values.authorization_url_parameters||{})),
					client_id: values.client_id,
					redirection_url: values.redirection_url,
					refresh_token: values.refresh_token,
					token_url: this.#getUrl(values.token_url, Object.values(values.token_url_parameters||{}))
				};

				if ('client_secret' in values) {
					detail.client_secret = values.client_secret;
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail}));
			})
			.catch(error => this.#addErrorMessage(error.message))
			.finally(() => this.overlay.unsetLoading());
	}

	#initForm() {
		if (this.advanced_form) {
			const options = {
				template: '#oauth-parameter-row-tmpl',
				allow_empty: false,
				sortable: true,
				sortable_options: {
					target: 'tbody',
					selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
					freeze_end: 1
				}
			}

			this.#initParameters('[name="authorization_url"]', '#oauth-auth-parameters-table', options);
			this.#initParameters('[name="token_url"]', '#oauth-token-parameters-table', options);
		}
	}

	#initFormEvents() {
		this.form.addEventListener('change', e => this.#formChangeEvent(e));

		const redirect_url_field = this.form.querySelector('#oauth-redirection-field');

		redirect_url_field.querySelector('.js-copy-button').addEventListener('click', e => {
			const input = redirect_url_field.querySelector('[name="redirection_url"]');

			writeTextClipboard(input.value);
			e.target.focus();
		});
	}

	#initParameters(url_selector, parameters_selector, options) {
		const url_element = this.form.querySelector(url_selector);
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

	#addTokenFormFields(fields) {
		for (const [name, value] of Object.entries(fields)) {
			const input = document.createElement('input');

			input.type = 'hidden';
			input.name = name;
			input.value = value;

			this.form.appendChild(input);
		}
	}

	#formChangeEvent(event) {
		const target = event.target;

		if (target.matches('[name="authorization_mode"]')) {
			this.updateFieldsVisibility();
		}
	}

	#getOauthPopupUrl(oauth) {
		if (oauth.authorization_mode === 'manual') {
			return this.#getUrl(window.location, [
				{name: 'action', value: 'oauth.authorize'},
				{name: 'code', value: oauth.code},
				{name: 'state', value: this.#getUrlStateParameter(oauth)}
			]);
		}

		return this.#getUrl(
			oauth.authorization_url,
			Object.values(oauth.authorization_url_parameters||{}).concat([
				{name: 'redirect_uri', value: oauth.redirection_url},
				{name: 'client_id', value: oauth.client_id},
				{name: 'state', value: this.#getUrlStateParameter(oauth)}
		]));
	}

	#getTokensFromOauthService(popup) {
		return new Promise((resolve, reject) => {
			if (popup === null) {
				reject({error: this.messages.popup_blocked_error});

				return;
			}

			window.addEventListener('message', function (e) {
				if (e.source !== popup) {
					reject({error: 'Wrong message source'});
				}
				else {
					clearInterval(oauth_popup_guard);
					popup.close();
					resolve(e.data);
				}

				window.removeEventListener('message', this);
			});

			const oauth_popup_guard = setInterval(() => {
				if (!popup.closed) {
					return;
				}

				clearInterval(oauth_popup_guard);
				reject({error: this.messages.popup_closed});
			}, 500);
		});
	}

	#getUrl(url_base, params) {
		const url = new URL(url_base);

		params.map(({name, value}) => url.searchParams.set(name, value));

		return url.toString();
	}

	#getUrlStateParameter(data) {
		return btoa(JSON.stringify(data));
	}

	#addErrorMessage(message) {
		[...this.form.parentNode.querySelectorAll('.msg-good,.msg-bad,.msg-warning')].map(el => el.remove());

		this.form.parentNode.insertBefore(makeMessageBox('bad', [message])[0], this.form);
	}
}();
