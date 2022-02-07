<?php declare(strict_types=1);
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

<script>
	const view = {
		filter_expires_state: null,
		filter_expires_days: null,

		init() {
			this.filter_expires_state = document.getElementById('filter-expires-state');
			this.filter_expires_days = document.getElementById('filter-expires-days');
			this.expiresDaysHandler();
		},

		expiresDaysHandler() {
			if (this.filter_expires_state.checked) {
				this.filter_expires_days.disabled = false;
			}
			else {
				this.filter_expires_days.disabled = true;
			}
		},

		createUserToken() {
			this.openUserTokenPopup({admin_mode: '0'});
		},

		editUserToken(e, tokenid) {
			e.preventDefault();
			const user_token_data = {tokenid, admin_mode: '0'};
			this.openUserTokenPopup(user_token_data);
		},

		openUserTokenPopup(user_token_data) {
			const original_url = location.href;

			const overlay = PopUp('popup.token.edit', user_token_data, {
				dialogueid: 'token_edit',
				dialogue_class: 'modal-popup-generic'
			});

			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.userTokenSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.userTokenDelete, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		},

		massDeleteUserToken(button) {
			const confirm_text = button.getAttribute('confirm');
			if (!confirm(confirm_text)) {
				return;
			}

			button.classList.add('is-loading');

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'token.delete');

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: urlEncodeData({
					tokenids: chkbxRange.getSelectedIds(),
					admin_mode: '0'
				})
			})
				.then((response) => response.json())
				.then((response) => {
					const keepids = ('keepids' in response) ? response.keepids : [];

					if ('error' in response) {
						postMessageError(response.error.title);
						postMessageDetails('error', response.error.messages);
					}
					else if('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}
					}

					uncheckTableRows('user.token', keepids);
					location.href = location.href;
				})
				.catch(() => {
					const title = <?= json_encode(_('Unexpected server error.')) ?>;
					const message_box = makeMessageBox('bad', [], title)[0];

					clearMessages();
					addMessage(message_box);
				})
				.finally(() => {
					button.classList.remove('is-loading');
				});
		},



		events: {
			userTokenSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				location.href = location.href;
			},

			userTokenDelete(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				uncheckTableRows('hosts');
				location.href = location.href;
			}
		}
	}
</script>
