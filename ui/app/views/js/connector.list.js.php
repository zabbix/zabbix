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
			this.#initPopupListeners();
		}

		#initActions() {
			document.querySelector('.js-create-connector').addEventListener('click', () => {
				ZABBIX.PopupManager.open('connector.edit');
			});

			const form = document.getElementById('connector-list');

			form.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-enable-connector')) {
					this.#enable(e.target, [e.target.dataset.connectorid]);
				}
				else if (e.target.classList.contains('js-disable-connector')) {
					this.#disable(e.target, [e.target.dataset.connectorid]);
				}
			});

			form.querySelector('.js-massenable-connector').addEventListener('click', (e) => {
				this.#enable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
			});

			form.querySelector('.js-massdisable-connector').addEventListener('click', (e) => {
				this.#disable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
			});

			form.querySelector('.js-massdelete-connector').addEventListener('click', (e) => {
				this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
			});
		}

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: () => uncheckTableRows('connector')
			});
		}

		#enable(target, connectorids, massenable = false) {
			if (massenable) {
				const confirmation = connectorids.length > 1
					? <?= json_encode(_('Enable selected connectors?')) ?>
					: <?= json_encode(_('Enable selected connector?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'connector.enable');

			this.#post(target, connectorids, curl);
		}

		#disable(target, connectorids, massdisable = false) {
			if (massdisable) {
				const confirmation = connectorids.length > 1
					? <?= json_encode(_('Disable selected connectors?')) ?>
					: <?= json_encode(_('Disable selected connector?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'connector.disable');

			this.#post(target, connectorids, curl);
		}

		#delete(target, connectorids) {
			const confirmation = connectorids.length > 1
				? <?= json_encode(_('Delete selected connectors?')) ?>
				: <?= json_encode(_('Delete selected connector?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'connector.delete');

			this.#post(target, connectorids, curl);
		}

		#post(target, connectorids, url) {
			url.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('connector')) ?>);

			target.classList.add('is-loading');

			return fetch(url.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({connectorids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('connector', response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('connector');
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
