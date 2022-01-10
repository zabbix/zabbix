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

window.user_token_edit_popup = {
	overlay: null,
	dialogue: null,
	form: null,

	init({popup_url, form_name}) {
		this.overlay = overlays_stack.getById('user_token_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		this.addEventListeners();

		history.replaceState({}, '', popup_url);

		user_token_edit.init({form_name});
	},

	addEventListeners() {
		this.enableNavigationWarning();
		this.overlay.$dialogue[0].addEventListener('overlay.close', this.events.overlayClose, {once: true});
	},

	submit() {
		this.removePopupMessages();

		const fields = user_token_edit.trimFields(getFormFields(this.form));
		const curl = new Curl(this.form.getAttribute('action'), false);

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData(fields)
		})
			.then((response) => response.json())
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
				} else {
					this.overlay.setProperties({
						title: t('API token'),
						content: response.data,
						buttons: [{
							title: t('Close'),
							class: '',
							keepOpen: true,
							isSubmit: true,
							action: 'user_token_edit_popup.close();'
						}],
						data: response.data
					});
				}
			})
			.catch(this.ajaxExceptionHandler)
			.finally(() => {
				this.overlay.unsetLoading();
			});
	},

	regenerate() {
		this.removePopupMessages();

		const fields = user_token_edit.trimFields(getFormFields(this.form));
		fields.regenerate = '1';

		const curl = new Curl(this.form.getAttribute('action'), false);

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData(fields)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				this.overlay.setProperties({
					title: t('API token'),
					content: response.data,
					buttons: [{
						title: t('Close'),
						class: '',
						keepOpen: true,
						isSubmit: true,
						action: 'user_token_edit_popup.close();'
					}],
					data: response.data
				});

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

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData({
				tokenids: [tokenid],
				action_src: 'user.token.list'
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
		const form = user_token_edit_popup.form;
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

	events: {
		beforeUnload(e) {
			// Display confirmation message.
			e.preventDefault();
			e.returnValue = '';
		},

		overlayClose() {
			user_token_edit_popup.disableNavigationWarning();
		}
	}
};
