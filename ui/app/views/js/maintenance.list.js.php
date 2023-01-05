<?php declare(strict_types = 0);
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
	const view = new class {

		init() {
			this._initActions();
		}

		_initActions() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-maintenance-create')) {
					this._edit();
				}
				else if (e.target.classList.contains('js-maintenance-edit')) {
					this._edit({maintenanceid: e.target.dataset.maintenanceid});
				}
				else if (e.target.classList.contains('js-massdelete-maintenance')) {
					this._delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			})
		}

		_edit(parameters = {}) {
			const overlay = PopUp('popup.maintenance.edit', parameters, {
				dialogueid: 'maintenance-edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});

			overlay.$dialogue[0].addEventListener('dialogue.delete', (e) => {
				uncheckTableRows('maintenance');

				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});
		}

		_delete(target, maintenanceids) {
			const confirmation = maintenanceids.length > 1
				? <?= json_encode(_('Delete selected maintenance periods?')) ?>
				: <?= json_encode(_('Delete selected maintenance period?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'maintenance.delete');

			this._post(target, maintenanceids, curl.getUrl());
		}

		_post(target, maintenanceids, url) {
			target.classList.add('is-loading');

			return fetch(url, {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({maintenanceids: maintenanceids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('maintenance', response.keepids ?? []);//TODO row num
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('maintenance');
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
