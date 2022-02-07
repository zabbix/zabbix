<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

window.token_edit_popup = {
	overlay: null,
	dialogue: null,
	form: null,
	form_name: null,
	expires_at_row: null,
	expires_at_label: null,
	expires_at: null,
	expires_state: null,

	init() {
		this.overlay = overlays_stack.getById('token_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		this.addEventListeners();

		this.expires_at_row = document.getElementById('expires-at-row');
		this.expires_at_label = this.expires_at_row.previousSibling;
		this.expires_at = document.getElementById('expires_at');
		this.expires_state = document.getElementById('expires_state');
		this.expiresAtHandler();
	},

	addEventListeners() {
		this.enableNavigationWarning();
		this.overlay.$dialogue[0].addEventListener('overlay.close', this.events.overlayClose, {once: true});
	},

	submit() {
		this.removePopupMessages();

		const fields = this.preprocessFormFields(getFormFields(this.form));
		const curl = new Curl(this.form.getAttribute('action'), false);

		this.postData(curl, fields)
			.then((response) => {
					if ('error' in response) {
						throw {error: response.error};
					}

					if (fields.tokenid !== '0') {
						overlayDialogueDestroy(this.overlay.dialogueid);
						this.dialogue.dispatchEvent(new CustomEvent('dialogue.update', {
							detail: {
								success: response.success
							}
						}));
					}
					else {
						this.getTokenView(response.data);
					}
			});
	},

	regenerate() {
		this.removePopupMessages();

		const fields = this.preprocessFormFields(getFormFields(this.form));
		fields.regenerate = '1';

		const curl = new Curl(this.form.getAttribute('action'), false);

		this.postData(curl, fields)
			.then((response) => {
				this.getTokenView(response.data);
			});
	},

	delete(tokenid) {
		this.removePopupMessages();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'token.delete');

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData({
				tokenids: [tokenid],
				action_src: 'token.list'
			})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.delete', {
					detail: {
						success: response.success
					}
				}));
			})
			.catch(this.ajaxExceptionHandler)
			.finally(() => {
				this.overlay.unsetLoading();
			});
	},

	close() {
		overlayDialogueDestroy(this.overlay.dialogueid);
		location.href = location.href;
	},

	removePopupMessages() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	},

	enableNavigationWarning() {
		window.addEventListener('beforeunload', this.events.beforeUnload, {passive: false});
	},

	disableNavigationWarning() {
		window.removeEventListener('beforeunload', this.events.beforeUnload);
	},

	ajaxExceptionHandler: (exception) => {
		const form = token_edit_popup.form;
		let title;
		let messages = [];

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			title = <?= json_encode(_('Unexpected server error.')) ?>;
		}

		const message_box = makeMessageBox('bad', messages, title, true, true)[0];

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
		return fields;
	},

	expiresAtHandler() {
		if (this.expires_state.checked == false) {
			let expires_state_hidden = document.createElement('input');
			expires_state_hidden.setAttribute('type', 'hidden');
			expires_state_hidden.setAttribute('name', 'expires_state');
			expires_state_hidden.setAttribute('value', '0');
			expires_state_hidden.setAttribute('id', 'expires_state_hidden');
			this.expires_state.append(expires_state_hidden);

			this.expires_at_row.style.display = 'none';
			this.expires_at_label.style.display = 'none';
			this.expires_at.disabled = true;
		}
		else {
			this.expires_at_row.style.display = "";
			this.expires_at_label.style.display = "";
			this.expires_at.disabled = false;
			let expires_state_hidden = document.getElementById('expires_state_hidden');
			if (expires_state_hidden) {
				expires_state_hidden.parentNode.removeChild(expires_state_hidden);
			}
		}
	},

	postData(url, data) {
		return fetch(url.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData(data)
		})
			.then((response) => response.json())
			.catch(this.ajaxExceptionHandler)
			.finally(() => {
				this.overlay.unsetLoading()
			});
	},

	getTokenView(data) {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'popup.token.view');
		return this.postData(curl, data)
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}
				this.overlay.setProperties(response);
			});
	},

	events: {
		beforeUnload(e) {
			// Display confirmation message.
			e.preventDefault();
			e.returnValue = '';
		},

		overlayClose() {
			token_edit_popup.disableNavigationWarning();
		}
	}
};

