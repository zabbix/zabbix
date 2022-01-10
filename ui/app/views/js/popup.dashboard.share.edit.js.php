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

window.dashboard_share_edit_popup = {
	user_group_row_template: null,
	user_row_template: null,

	init({dashboard, user_group_row_template, user_row_template}) {
		this.user_group_row_template = new Template(user_group_row_template);
		this.user_row_template = new Template(user_row_template);

		this.addPopupValues({'object': 'private', 'values': [dashboard.private]});
		this.addPopupValues({'object': 'userid', 'values': dashboard.users});
		this.addPopupValues({'object': 'usrgrpid', 'values': dashboard.userGroups});

		/**
		* @see init.js add.popup event
		*/
		window.addPopupValues = (list) => {
			this.addPopupValues(list);
		};
	},

	submit() {
		clearMessages();

		const overlay = overlays_stack.getById('dashboard_share_edit');
		const form = overlay.$dialogue.$body[0].querySelector('form');

		const curl = new Curl('zabbix.php', false);

		curl.setArgument('action', 'dashboard.share.update');

		overlay.setLoading();

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(getFormFields(form))
		})
			.then((response) => response.json())
			.then((response) => {
				if ('errors' in response) {
					throw {html_string: response.errors};
				}

				overlay.unsetLoading();

				addMessage(response.messages);
				overlayDialogueDestroy(overlay.dialogueid);
			})
			.catch((error) => {
				overlay.unsetLoading();

				for (const el of form.parentNode.children) {
					if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
						el.parentNode.removeChild(el);
					}
				}

				const message_box = (typeof error === 'object' && 'html_string' in error)
					? new DOMParser().parseFromString(error.html_string, 'text/html').body.firstElementChild
					: makeMessageBox('bad', [], t('Failed to update dashboard sharing.'), true, false)[0];

				form.parentNode.insertBefore(message_box, form);
			});
	},

	removeUserGroupShares(usrgrpid) {
		const element = document.getElementById(`user-group-shares-${usrgrpid}`);

		if (element !== null) {
			element.remove();
		}
	},

	removeUserShares(userid) {
		const element = document.getElementById(`user-shares-${userid}`);

		if (element !== null) {
			element.remove();
		}
	},

	addPopupValues(list) {
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
