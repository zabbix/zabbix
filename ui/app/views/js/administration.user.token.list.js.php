<?php declare(strict_types = 0);
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


/**
 * @var CView $this
 */
?>

<script>
	const view = new class {

		init() {
			this.#initActionButtons();
			this.#setSubmitCallback();
			this.expiresDaysHandler();
		}

		#initActionButtons() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-create-token')) {
					window.popupManagerInstance.openPopup('token.edit', {admin_mode: '0'});
				}
				else if (e.target.classList.contains('js-massdelete-token')) {
					this.#massDeleteUserToken(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			});
		}

		expiresDaysHandler() {
			const filter_expires_state = document.getElementById('filter-expires-state');
			const filter_expires_days = document.getElementById('filter-expires-days');

			filter_expires_days.disabled = !filter_expires_state.checked;
		}

		#massDeleteUserToken(target, tokenids) {
			const confirmation = tokenids.length > 1
				? <?= json_encode(_('Delete selected tokens?')) ?>
				: <?= json_encode(_('Delete selected token?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			target.classList.add('is-loading');

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'token.delete');
			curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('token')) ?>);

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({
					tokenids: Object.keys(chkbxRange.getSelectedIds())
				})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('user.token', response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('user.token');
					}

					location.href = location.href;
				})
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);
				})
				.finally(() => {
					target.classList.remove('is-loading');
				});
		}

		#setSubmitCallback() {
			window.popupManagerInstance.setSubmitCallback((e) => {
				if (e.detail && 'success' in e.detail) {
					postMessageOk(e.detail.success.title);

					if ('messages' in e.detail.success) {
						postMessageDetails('success', e.detail.success.messages);
					}
				}

				uncheckTableRows('user.token');
				location.href = location.href;
			});
		}
	};
</script>
