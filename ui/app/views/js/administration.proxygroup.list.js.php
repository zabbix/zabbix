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
			this.#initActions();
			this.#setSubmitCallback();
		}

		#initActions() {
			document.querySelector('.js-create-proxy-group').addEventListener('click', () => {
				window.popupManagerInstance.openPopup('proxygroup.edit', {});
			});

			const form = document.getElementById('proxy-group-list');

			form.querySelector('.js-massdelete-proxy-group').addEventListener('click', (e) => {
				this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
			});
		}

		#delete(target, proxy_groupids) {
			const confirmation = proxy_groupids.length > 1
				? <?= json_encode(_('Delete selected proxy groups?')) ?>
				: <?= json_encode(_('Delete selected proxy group?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'proxygroup.delete');

			this.#post(target, proxy_groupids, curl);
		}

		#post(target, proxy_groupids, curl) {
			curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('proxygroup')) ?>);

			target.classList.add('is-loading');

			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({proxy_groupids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('proxygroup', response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('proxygroup');
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
				if ('success' in e.detail) {
					postMessageOk(e.detail.success.title);

					if ('messages' in e.detail.success) {
						postMessageDetails('success', e.detail.success.messages);
					}
				}

				uncheckTableRows('proxygroup');
				location.href = location.href;
			});
		}
	};
</script>
