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
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-enable-module')) {
					this.#enable(e.target, [e.target.dataset.moduleid]);
				}
				else if (e.target.classList.contains('js-massenable-module')) {
					this.#enable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
				else if (e.target.classList.contains('js-disable-module')) {
					this.#disable(e.target, [e.target.dataset.moduleid]);
				}
				else if (e.target.classList.contains('js-massdisable-module')) {
					this.#disable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
			});

			this.#setSubmitCallback();
		}

		#enable(target, moduleids, massenable = false) {
			if (massenable) {
				const confirmation = moduleids.length > 1
					? <?= json_encode(_('Enable selected modules?')) ?>
					: <?= json_encode(_('Enable selected module?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'module.enable');

			this.#post(target, moduleids, curl);
		}

		#disable(target, moduleids, massdisable = false) {
			if (massdisable) {
				const confirmation = moduleids.length > 1
					? <?= json_encode(_('Disable selected modules?')) ?>
					: <?= json_encode(_('Disable selected module?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'module.disable');

			this.#post(target, moduleids, curl);
		}

		#post(target, moduleids, curl) {
			curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('module')) ?>);

			target.classList.add('is-loading');

			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({moduleids: moduleids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('modules', response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('modules');
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

				uncheckTableRows('modules');
				location.href = location.href;
			});
		}

	};
</script>
