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

window.token_edit_popup = {
	overlay: null,
	dialogue: null,
	form: null,
	form_name: null,
	expires_at_field: null,
	expires_at_label: null,
	expires_at: null,
	expires_state: null,

	init({admin_mode}) {
		this.overlay = overlays_stack.getById('token.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		const backurl = new Curl('zabbix.php');

		backurl.setArgument('action', admin_mode == 1 ? 'token.list' : 'user.token.list');
		this.overlay.backurl = backurl.getUrl();

		this.expires_at_field = document.getElementById('expires-at-row').parentNode;
		this.expires_at_label = this.expires_at_field.previousSibling;
		this.expires_at = document.getElementById('expires_at');
		this.expires_state = document.getElementById('expires_state');
		this.expiresAtHandler();

		this.expires_state.addEventListener('change', () => this.expiresAtHandler());
	},

	submit() {
		this.removePopupMessages();

		const fields = this.preprocessFormFields(getFormFields(this.form));
		const curl = new Curl(this.form.getAttribute('action'));

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

				if ('data' in response) {
					this.loadTokenView(response.data);
				}
				else {
					overlayDialogueDestroy(this.overlay.dialogueid);

					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
				}
			})
			.catch(this.ajaxExceptionHandler)
			.finally(() => {
				this.overlay.unsetLoading();
			});
	},

	regenerate() {
		this.removePopupMessages();

		const fields = this.preprocessFormFields(getFormFields(this.form));
		fields.regenerate = '1';

		const curl = new Curl(this.form.getAttribute('action'));

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

				this.loadTokenView(response.data);
			})
			.catch(this.ajaxExceptionHandler)
			.finally(() => {
				this.overlay.unsetLoading();
			});
	},

	delete(tokenid) {
		this.removePopupMessages();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'token.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('token')) ?>);

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({
				tokenids: [tokenid],
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
			.catch(this.ajaxExceptionHandler)
			.finally(() => {
				this.overlay.unsetLoading();
			});
	},

	close() {
		this.overlay.$dialogue[0].dispatchEvent(new CustomEvent('dialogue.close', {detail: 'token.edit'}));
	},

	removePopupMessages() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	},

	ajaxExceptionHandler: (exception) => {
		const form = token_edit_popup.form;

		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		form.parentNode.insertBefore(message_box, form);
	},

	preprocessFormFields(fields) {
		this.trimFields(fields);
		fields.status = fields.status || <?= ZBX_AUTH_TOKEN_DISABLED ?>;

		return fields;
	},

	trimFields(fields) {
		const fields_to_trim = ['name', 'description'];
		for (const field of fields_to_trim) {
			if (field in fields) {
				fields[field] = fields[field].trim();
			}
		}
	},

	expiresAtHandler() {
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
	},

	loadTokenView(data) {
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

				this.overlay.$dialogue[0].addEventListener('dialogue.close', this.events.overlayCloseAfterUpdate,
					{once: true}
				);

				this.overlay.setProperties({...response, prevent_navigation: false});
			})
			.catch(this.ajaxExceptionHandler)
			.finally(() => {
				this.overlay.unsetLoading();
				this.overlay.recoverFocus();
				this.overlay.containFocus();
			});
	},

	events: {
		overlayCloseAfterUpdate() {
			token_edit_popup.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {}));
		}
	}
};
