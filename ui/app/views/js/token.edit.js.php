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

window.token_edit_popup = new class {
	overlay = null;
	dialogue = null;
	form = null;
	form_element = null;
	form_name = null;
	expires_at_field = null;
	expires_at_label = null;
	expires_at = null;
	expires_state = null;

	init({rules, admin_mode}) {
		this.overlay = overlays_stack.getById('token.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', admin_mode == 1 ? 'token.list' : 'user.token.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		this.expires_at_field = document.getElementById('expires-at-row').parentNode;
		this.expires_at_label = this.expires_at_field.previousSibling;
		this.expires_at = document.getElementById('expires_at');
		this.expires_state = document.getElementById('expires_state');
		this.#expiresAtHandler();
		this.#initActions();
	}

	#initActions() {
		const footer = this.overlay.$dialogue.$footer[0];

		footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
		footer.querySelector('.js-delete')?.addEventListener('click', () => this.#delete());
		footer.querySelector('.js-regenerate')?.addEventListener('click', () => this.#regenerate());

		// Name field value shall be unique per selected user, trigger API unique validation.
		jQuery(document.getElementById('userid')).change(() => this.form.validateChanges(['name', 'userid']));
		this.expires_state.addEventListener('change', () => this.#expiresAtHandler());
	}

	#submit() {
		this.#removePopupMessages();

		const fields = this.form.getAllValues();
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', fields.tokenid == 0 ? 'token.create' : 'token.update');

		this.form.validateSubmit(fields).then((result) => {
			if (!result) {
				this.overlay.unsetLoading();

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

					if ('data' in response) {
						this.#loadTokenView(response.data);
					}
					else {
						overlayDialogueDestroy(this.overlay.dialogueid);

						this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
					}
				})
				.catch((exception) => this.#ajaxExceptionHandler(exception))
				.finally(() => this.overlay.unsetLoading());
		});
	}

	#regenerate() {
		this.#removePopupMessages();

		const fields = {...this.form.getAllValues(), regenerate: '1'};

		this.form.validateSubmit(fields).then(result => {
			if (!result) {
				this.overlay.unsetLoading();

				return;
			}

			const confirmation = <?=
				json_encode(_('Regenerate selected API token? Previously generated token will become invalid.'))
			?>;

			if (!window.confirm(confirmation)) {
				this.overlay.unsetLoading();

				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', fields.tokenid == 0 ? 'token.create' : 'token.update');

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

					this.#loadTokenView(response.data);
				})
				.catch((exception) => this.#ajaxExceptionHandler(exception))
				.finally(() => this.overlay.unsetLoading());
		});
	}

	#delete() {
		this.#removePopupMessages();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'token.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('token')) ?>);

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({
				tokenids: [this.form.getAllValues().tokenid],
				admin_mode: '1'
			})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this.overlay.unsetLoading());
	}

	close() {
		this.overlay.$dialogue[0].dispatchEvent(new CustomEvent('token-edit.close'));
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

	#expiresAtHandler() {
		if (this.expires_state.checked == false) {
			let expires_state_hidden = document.createElement('input');
			expires_state_hidden.setAttribute('type', 'hidden');
			expires_state_hidden.setAttribute('name', 'expires_state');
			expires_state_hidden.setAttribute('value', '0');
			expires_state_hidden.setAttribute('id', 'expires_state_hidden');
			this.expires_state.append(expires_state_hidden);

			this.expires_at_field.style.display = 'none';
			this.expires_at_label.style.display = 'none';
			this.expires_at.disabled = true;
		}
		else {
			this.expires_at_field.style.display = '';
			this.expires_at_label.style.display = '';
			this.expires_at.disabled = false;
			let expires_state_hidden = document.getElementById('expires_state_hidden');
			if (expires_state_hidden) {
				expires_state_hidden.parentNode.removeChild(expires_state_hidden);
			}
		}
	}

	#loadTokenView(data) {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'popup.token.view');

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				// Popup Manager shall not intercept this event.
				this.overlay.$dialogue[0].addEventListener('dialogue.close', e => {
					e.preventDefault();
					e.stopImmediatePropagation();

					// Set timeout to overcome browser preventing page reload on ESC key.
					setTimeout(() => {
						this.overlay.$dialogue[0].dispatchEvent(new CustomEvent('dialogue.submit', {detail: {}}));
					});
				}, {capture: true});

				this.overlay.$dialogue[0].addEventListener('token-edit.close', () => {
					this.overlay.$dialogue[0].dispatchEvent(new CustomEvent('dialogue.submit', {detail: {}}));
				});

				this.overlay.setProperties({...response, prevent_navigation: false});
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => {
				this.overlay.unsetLoading();
				this.overlay.recoverFocus();
				this.overlay.containFocus();
			});
	}
};
