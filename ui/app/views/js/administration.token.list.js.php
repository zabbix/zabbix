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
			this.initActionButtons();
			this.filter_expires_state = document.getElementById('filter-expires-state');
			this.filter_expires_days = document.getElementById('filter-expires-days');
			this.expiresDaysHandler();
		},

		initActionButtons() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-create-token')) {
					this.createToken();
				}
				else if (e.target.classList.contains('js-edit-token')) {
					this.editToken(e.target.dataset.tokenid);
				}
				else if (e.target.classList.contains('js-massdelete-token')) {
					this.massDeleteToken(e.target, Object.values(chkbxRange.getSelectedIds()));
				}
			})
		},

		expiresDaysHandler() {
			if (this.filter_expires_state.checked) {
				this.filter_expires_days.disabled = false;
			}
			else {
				this.filter_expires_days.disabled = true;
			}
		},

		createToken() {
			this.openTokenPopup({admin_mode: '1'});
		},

		editToken(tokenid) {
			const token_data = {tokenid, admin_mode: '1'};
			this.openTokenPopup(token_data);
		},

		openTokenPopup(token_data) {
			const original_url = location.href;

			const overlay = PopUp('popup.token.edit', token_data, {
				dialogueid: 'token_edit',
				dialogue_class: 'modal-popup-generic'
			});

			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.tokenSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.tokenDelete, {once: true});
		},

		massDeleteToken(target, tokenids) {
			const confirmation = tokenids.length > 1
				? <?= json_encode(_('Delete selected tokens?')) ?>
				: <?= json_encode(_('Delete selected token?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			target.classList.add('is-loading');

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'token.delete');

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: urlEncodeData({
					tokenids: chkbxRange.getSelectedIds(),
					admin_mode: '1'
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

					uncheckTableRows('token', keepids);
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
			tokenSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				location.href = location.href;
			},

			tokenDelete(e) {
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
