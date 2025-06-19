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
?>


<script>
	const view = new class {

		init() {
			this.#initActions();
			this.#initPopupListeners();
		}

		#initActions() {
			document.querySelector('.js-create-proxy').addEventListener('click', () => {
				ZABBIX.PopupManager.open('proxy.edit');
			});

			const form = document.getElementById('proxy-list');

			form.querySelector('.js-refresh-proxy-config')
				.addEventListener('click', (e) => {
					this.#refreshConfig(e.target, Object.keys(chkbxRange.getSelectedIds()));
				});

			form
				.querySelector('.js-massenable-proxy-host')
				.addEventListener('click', (e) => {
					this.#enableHosts(e.target, Object.keys(chkbxRange.getSelectedIds()));
				});

			form
				.querySelector('.js-massdisable-proxy-host')
				.addEventListener('click', (e) => {
					this.#disableHosts(e.target, Object.keys(chkbxRange.getSelectedIds()));
				});

			form
				.querySelector('.js-massdelete-proxy')
				.addEventListener('click', (e) => {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				});
		}

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: () => uncheckTableRows('proxy')
			});
		}

		#refreshConfig(target, proxyids) {
			const confirmation = proxyids.length > 1
				? <?= json_encode(_('Refresh configuration of the selected proxies?')) ?>
				: <?= json_encode(_('Refresh configuration of the selected proxy?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'proxy.config.refresh');

			this.#post(target, proxyids, curl);
		}

		#enableHosts(target, proxyids) {
			const confirmation = proxyids.length > 1
				? <?= json_encode(_('Enable hosts monitored by selected proxies?')) ?>
				: <?= json_encode(_('Enable hosts monitored by selected proxy?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'proxy.host.enable');

			this.#post(target, proxyids, curl);
		}

		#disableHosts(target, proxyids) {
			const confirmation = proxyids.length > 1
				? <?= json_encode(_('Disable hosts monitored by selected proxies?')) ?>
				: <?= json_encode(_('Disable hosts monitored by selected proxy?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'proxy.host.disable');

			this.#post(target, proxyids, curl);
		}

		#delete(target, proxyids) {
			const confirmation = proxyids.length > 1
				? <?= json_encode(_('Delete selected proxies?')) ?>
				: <?= json_encode(_('Delete selected proxy?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'proxy.delete');

			this.#post(target, proxyids, curl);
		}

		#post(target, proxyids, url) {
			url.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('proxy')) ?>);

			target.classList.add('is-loading');

			return fetch(url.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({proxyids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('proxy', response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('proxy');
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
	};
</script>
