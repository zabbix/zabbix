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
			document.getElementById('js-create').addEventListener('click', () => {
				window.popupManagerInstance.openPopup('discovery.edit', {});
			});

			document.getElementById('js-massenable').addEventListener('click', (e) => {
				this.#enable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
			});

			document.getElementById('js-massdisable').addEventListener('click', (e) => {
				this.#disable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
			});

			document.getElementById('js-massdelete').addEventListener('click', (e) => {
				this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
			});

			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-enable-drule')) {
					this.#enable(e.target, [e.target.dataset.druleid]);
				}
				else if (e.target.classList.contains('js-disable-drule')) {
					this.#disable(e.target, [e.target.dataset.druleid]);
				}
			});

			this.#setSubmitCallback();
		}

		#enable(target, druleids, massenable = false) {
			if (massenable) {
				const confirmation = druleids.length > 1
					? <?= json_encode(_('Enable selected discovery rules?')) ?>
					: <?= json_encode(_('Enable selected discovery rule?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'discovery.enable');
			this.#post(target, druleids, curl);
		}

		#disable(target, druleids, massdisable = false) {
			if (massdisable) {
				const confirmation = druleids.length > 1
					? <?= json_encode(_('Disable selected discovery rules?')) ?>
					: <?= json_encode(_('Disable selected discovery rule?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'discovery.disable');

			this.#post(target, druleids, curl);
		}

		#delete(target, druleids) {
			const confirmation = druleids.length > 1
				? <?= json_encode(_('Delete selected discovery rules?')) ?>
				: <?= json_encode(_('Delete selected discovery rule?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'discovery.delete');

			this.#post(target, druleids, curl);
		}

		#post(target, druleids, url) {
			url.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('discovery')) ?>);

			target.classList.add('is-loading');

			return fetch(url.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({druleids: druleids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);
						uncheckTableRows('discovery');
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('discovery');
					}

					location.href = location.href;
				})
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);

					target.classList.remove('is-loading');
					target.blur();
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

				uncheckTableRows('discovery');
				location.href = location.href;
			});
		}
	};
</script>
