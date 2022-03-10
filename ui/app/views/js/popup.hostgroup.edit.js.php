<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

window.hostgroup_edit_popup = {
	groupid: null,
	subgroups: null,

	create_url: null,
	update_url: null,
	delete_url: null,

	overlay: null,
	dialogue: null,
	form: null,
	footer: null,

	init({groupid, subgroups, create_url, update_url, delete_url}) {
		this.groupid = groupid;

		this.create_url = create_url;
		this.update_url = update_url;
		this.delete_url = delete_url;

		this.overlay = overlays_stack.getById('hostgroup_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.footer = this.overlay.$dialogue.$footer[0];
	},

	submit() {
		const fields = getFormFields(this.form);

		if (this.groupid !== null) {
			fields.groupid = this.groupid;
		}

		fields.name = fields.name.trim();

		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}

		this.overlay.setLoading();

		const curl = new Curl(this.groupid !== null ? this.update_url : this.create_url);
console.log(curl);
		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(fields)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('errors' in response) {
					throw {html_string: response.errors};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {
					detail: {
						title: response.title,
						messages: ('messages' in response) ? response.messages : null
					}
				}));
			})
			.catch((error) => {
				let message_box;

				if (typeof error === 'object' && 'html_string' in error) {
					message_box =
						new DOMParser().parseFromString(error.html_string, 'text/html').body.firstElementChild;
				}
				else {
					const error = <?= json_encode(_('Unexpected server error.')) ?>;

					message_box = makeMessageBox('bad', [], error, true, false)[0];
				}

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	},

	cancel() {
		overlayDialogueDestroy(this.overlay.dialogueid);
	},

	clone({title, buttons}) {
		this.groupid = null;

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});
	},

	delete() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}

		this.overlay.setLoading();

		const curl = new Curl(this.delete_url);

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({groupids: [this.groupid]})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {response_error: response.error};
				}

				if ('success' in response) {
					postMessageOk(response.success.title);

					if ('messages' in response.success) {
						postMessageDetails('success', response.success.messages);
					}

					uncheckTableRows('hostgroup');
				}

				location.href = location.href;
			})
			.catch((error) => {
				this.overlay.unsetLoading();

				let title, messages;

				if (typeof error === 'object' && 'response_error' in error) {
					title = error.response_error.title;
					messages = error.response_error.messages;
				}
				else {
					title = <?= json_encode(_('Unexpected server error.')) ?>;
					messages = [];
				}

				const message_box = makeMessageBox('bad', messages, title, true, false)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
	}
}
