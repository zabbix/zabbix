<?php declare(strict_types = 0);
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
?>


window.dashboard_share_edit_popup = new class {

	init({dashboard, user_group_row_template, user_row_template}) {
		this.user_group_row_template = new Template(user_group_row_template);
		this.user_row_template = new Template(user_row_template);

		this._addPopupValues({'object': 'private', 'values': [dashboard.private]});
		this._addPopupValues({'object': 'userid', 'values': dashboard.users});
		this._addPopupValues({'object': 'usrgrpid', 'values': dashboard.userGroups});

		/**
		* @see init.js add.popup event
		*/
		window.addPopupValues = (list) => {
			this._addPopupValues(list);
		};
	}

	submit() {
		const overlay = overlays_stack.getById('dashboard_share_edit');
		const form = overlay.$dialogue.$body[0].querySelector('form');

		overlay.setLoading();

		const curl = new Curl('zabbix.php', false);
		curl.setArgument('action', 'dashboard.share.update');

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(getFormFields(form))
		})
			.then((response) => response.json())
			.then((response) => {
				clearMessages();

				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(overlay.dialogueid);

				const message_box = makeMessageBox('good', response.success.messages, response.success.title);

				addMessage(message_box);
			})
			.catch((exception) => {
				for (const element of form.parentNode.children) {
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
					messages = [<?= json_encode(_('Failed to update dashboard sharing.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				form.parentNode.insertBefore(message_box, form);
			})
			.finally(() => {
				overlay.unsetLoading();
			});
	}

	removeUserGroupShares(usrgrpid) {
		const element = document.getElementById(`user-group-shares-${usrgrpid}`);

		if (element !== null) {
			element.remove();
		}
	}

	removeUserShares(userid) {
		const element = document.getElementById(`user-shares-${userid}`);

		if (element !== null) {
			element.remove();
		}
	}

	_addPopupValues(list) {
		for (let i = 0; i < list.values.length; i++) {
			const value = list.values[i];

			if (list.object === 'usrgrpid' || list.object === 'userid') {
				if (value.permission === undefined) {
					if (document.querySelector('input[name="private"]:checked').value == <?= PRIVATE_SHARING ?>) {
						value.permission = <?= PERM_READ ?>;
					}
					else {
						value.permission = <?= PERM_READ_WRITE ?>;
					}
				}
			}

			switch (list.object) {
				case 'private':
					document
						.querySelector(`input[name="private"][value="${value}"]`)
						.checked = true;

					break;

				case 'usrgrpid':
					if (document.getElementById(`user-group-shares-${value.usrgrpid}`) !== null) {
						continue;
					}

					document.getElementById('user-group-list-footer')
						.insertAdjacentHTML('beforebegin', this.user_group_row_template.evaluate(value));

					document
						.getElementById(`user-group-${value.usrgrpid}-permission-${value.permission}`)
						.checked = true;

					break;

				case 'userid':
					if (document.getElementById(`user-shares-${value.id}`) !== null) {
						continue;
					}

					document.getElementById('user-list-footer')
						.insertAdjacentHTML('beforebegin', this.user_row_template.evaluate(value));

					document
						.getElementById(`user_${value.id}_permission_${value.permission}`)
						.checked = true;

					break;
			}
		}
	}
};
