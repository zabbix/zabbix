<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
	const view = new class {

		init() {
			document.getElementById('js-create').addEventListener('click', () => this._edit());

			document.getElementById('js-massenable').addEventListener('click', (e) => {
				this._enable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
			});

			document.getElementById('js-massdisable').addEventListener('click', (e) => {
				this._disable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
			});

			document.getElementById('js-massdelete').addEventListener('click', (e) => {
				this._delete(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
			});

			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-discovery-edit')) {
					this._edit({druleid: e.target.dataset.druleid});
				}
				else if (e.target.classList.contains('js-enable-drule')) {
					this._enable(e.target, [e.target.dataset.druleid]);
				}
				else if (e.target.classList.contains('js-disable-drule')) {
					this._disable(e.target, [e.target.dataset.druleid]);
				}
			});
		}

		_edit(parameters = {}) {
			const overlay = PopUp('discovery.edit', parameters, {
				dialogueid: 'discoveryForm',
				dialogue_class: 'modal-popup-medium',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				uncheckTableRows('discovery');
				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});
		}

		_enable(target, druleids, massenable = false) {
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
			this._post(target, druleids, curl);
		}

		_disable(target, druleids, massdisable = false) {
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

			this._post(target, druleids, curl);
		}

		_delete(target, druleids) {
			const confirmation = druleids.length > 1
				? <?= json_encode(_('Delete selected discovery rules?')) ?>
				: <?= json_encode(_('Delete selected discovery rule?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'discovery.delete');

			this._post(target, druleids, curl);
		}

		_post(target, druleids, url) {
			url.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
				<?= json_encode(CCsrfTokenHelper::get('discovery')) ?>
			);

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
				})
				.finally(() => {
					target.classList.remove('is-loading');
				});
		}
	};
</script>
