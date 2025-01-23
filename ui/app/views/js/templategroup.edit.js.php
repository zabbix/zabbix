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

	init({groupid, name}) {
		this.groupid = groupid;
		this.name = name;

		this.overlay = overlays_stack.getById('templategroup.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.footer = this.overlay.$dialogue.$footer[0];

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'templategroup.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);
	}

	submit() {
		const fields = getFormFields(this.form);
		fields.name = fields.name.trim();

		this.overlay.setLoading();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', this.groupid !== null ? 'templategroup.update' : 'templategroup.create');

		this._post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
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
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'templategroup.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('templategroup')) ?>);

		this._post(curl.getUrl(), {groupids: [this.groupid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	_post(url, data, success_callback) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return response;
			})
			.then(success_callback)
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
}
