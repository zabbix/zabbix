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

window.host_edit_popup = {
	overlay: null,
	dialogue: null,
	form: null,

	init({popup_url, form_name}) {
		this.enableNavigationWarning();

		this.overlay = overlays_stack.getById('host_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		history.replaceState({}, '', popup_url);

		host_edit.init({form_name});
	},

	submit() {
		this.removePopupMessages();
		this.overlay.setLoading();

		const fields = host_edit.preprocessFormFields(getFormFields(this.form));
		const curl = new Curl(this.form.getAttribute('action'));

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

				overlayDialogueDestroy(this.overlay.dialogueid);
				this.disableNavigationWarning();

				if ('hostid' in fields) {
					this.dialogue.dispatchEvent(new CustomEvent('dialogue.update', {
						detail: {
							success: response.success
						}
					}));
				}
				else {
					this.dialogue.dispatchEvent(new CustomEvent('dialogue.create', {
						detail: {
							success: response.success
						}
					}));
				}
			})
			.catch(this.ajaxExceptionHandler)
			.finally(() => {
				this.overlay.unsetLoading();
			});
	},

	clone() {
		const options = host_edit.preprocessFormFields(getFormFields(this.form));
		delete options.sid;
		options.clone = 1;

		this.disableNavigationWarning();
		PopUp('popup.host.edit', options, 'host_edit');
	},

	fullClone() {
		const options = host_edit.preprocessFormFields(getFormFields(this.form));
		delete options.sid;
		options.full_clone = 1;

		this.disableNavigationWarning();
		PopUp('popup.host.edit', options, 'host_edit');
	},

	delete(hostid) {
		this.removePopupMessages();
		this.overlay.setLoading();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'host.massdelete');

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData({hostids: [hostid]})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);
				this.disableNavigationWarning();

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

	closePopup() {
		this.disableNavigationWarning();
		this.dialogue.dispatchEvent(new CustomEvent('dialogue.close'));
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
		const form = host_edit_popup.form;
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
		}
	}
}
