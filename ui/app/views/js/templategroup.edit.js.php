<?php declare(strict_types = 1);
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
?>

window.templategroup_edit_popup = new class {

	init({rules, groupid, name}) {
		this.groupid = groupid;
		this.name = name;

		this.overlay = overlays_stack.getById('templategroup.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);
		this.footer = this.overlay.$dialogue.$footer[0];

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'templategroup.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);
	}

	submit() {
		const fields = this.form.getAllValues();
		fields.name = fields.name.trim();

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();

					return;
				}

				const submit_url = new URL('zabbix.php', location.href);
				submit_url.searchParams.set('action',
					this.groupid !== null ? 'templategroup.update' : 'templategroup.create')

				this.#post(submit_url.href, fields);
			});
	}

	clone({title, buttons}) {
		this.groupid = null;

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});
		this.overlay.recoverFocus();
		this.overlay.containFocus();
	}

	delete() {
		const url = new URL('zabbix.php', location.href);
		url.searchParams.set('action', 'templategroup.delete');
		url.searchParams.set(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('templategroup')) ?>);

		this.#post(url.href, {groupids: [this.groupid]});
	}

	#post(url, fields) {
		this.#removePopupMessages();

		fetch(url, {
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
				}
				else {
					postMessageOk(response.success.title);
					overlayDialogueDestroy(this.overlay.dialogueid);
					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
				}
			})
			.catch(exception => {
				this.#ajaxExceptionHandler(exception);
			})
			.finally(() => {
				this.overlay.unsetLoading();
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
}
