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
			document.getElementById('js-create').addEventListener('click', () => {
				window.popupManagerInstance.openPopup('script.edit', {});
			});

			document.getElementById('js-massdelete').addEventListener('click', (e) => {
				this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()), true)
			});
		}

		#delete(target, scriptids) {
			const confirmation = scriptids.length > 1
				? <?= json_encode(_('Delete selected scripts?')) ?>
				: <?= json_encode(_('Delete selected script?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'script.delete');

			this.#post(target, scriptids, curl);
		}

		#post(target, scriptids, url) {
			url.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('script')) ?>);

			target.classList.add('is-loading');

			return fetch(url.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({scriptids: scriptids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}
						uncheckTableRows('script', response.keepids ?? []);

						postMessageDetails('error', response.error.messages);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('script');
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

				uncheckTableRows('script');
				location.href = location.href;
			});
		}
	};
</script>
