<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

	init({popup_url, form_name, host_interfaces, host_is_discovered, warnings}) {
		this.overlay = overlays_stack.getById('host_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		history.replaceState({}, '', popup_url);

		host_edit.init({form_name, host_interfaces, host_is_discovered});

		if (warnings.length) {
			const message_box = warnings.length == 1
				? makeMessageBox('warning', warnings, null, true, false)[0]
				: makeMessageBox('warning', warnings,
						<?= json_encode(_('Cloned host parameter values have been modified.')) ?>, true, false
					)[0];

			this.form.parentNode.insertBefore(message_box, this.form);
		}

		this.initial_form_fields = getFormFields(this.form);
		this.initEvents();
	},

	initEvents() {
		this.form.addEventListener('click', (e) => {
			const target = e.target;

			if (target.classList.contains('js-edit-linked-template')) {
				this.editTemplate({templateid: e.target.dataset.templateid});
			}
			else if (target.classList.contains('js-update-item')) {
				this.editItem(target, target.dataset);
			}
		});
	},

	editTemplate(parameters) {
		if (!this.isConfirmed()) {
			return;
		}

		overlayDialogueDestroy(this.overlay.dialogueid);

		const overlay = PopUp('template.edit', parameters, {
			dialogueid: 'templates-form',
			dialogue_class: 'modal-popup-large',
			prevent_navigation: true
		});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) =>
			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: e.detail}))
		);
	},

	editItem(target, data) {
		if (!this.isConfirmed()) {
			return;
		}

		overlayDialogueDestroy(this.overlay.dialogueid);

		const overlay = PopUp('item.edit', data, {
			dialogueid: 'item-edit',
			dialogue_class: 'modal-popup-large',
			trigger_element: target
		});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) =>
			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: e.detail})),
			{once: true}
		);
	},

	isConfirmed() {
		const form_fields = getFormFields(this.form);

		if (JSON.stringify(this.initial_form_fields) !== JSON.stringify(form_fields)) {
			if (!window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>)) {
				return false;
			}
		}

		return true;
	},

	submit() {
		this.removePopupMessages();

		const fields = host_edit.preprocessFormFields(getFormFields(this.form), false);
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

				if ('hostid' in fields) {
					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {
						detail: {
							success: response.success
						}
					}));
				}
				else {
					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {
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
		this.overlay.setLoading();
		const parameters = host_edit.preprocessFormFields(getFormFields(this.form), true);
		delete parameters.sid;
		parameters.clone = 1;

		PopUp('popup.host.edit', parameters, {
			dialogueid: 'host_edit',
			dialogue_class: 'modal-popup-large',
			prevent_navigation: true
		});
	},

	delete(hostid) {
		this.removePopupMessages();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'host.massdelete');
		curl.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>', <?= json_encode(CCsrfTokenHelper::get('host')) ?>);

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

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch(this.ajaxExceptionHandler)
			.finally(() => {
				this.overlay.unsetLoading();
			});
	},

	removePopupMessages() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	},

	ajaxExceptionHandler: (exception) => {
		const form = host_edit_popup.form;

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
	}
};
