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
			const $filter_groups = $('#filter_groups_');

			$filter_groups.on('change', () => this.#updateMultiselect($filter_groups));
			this.#updateMultiselect($filter_groups);

			this.#initActions();
			this.#initPopupListeners();
		}

		#initActions() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-create-maintenance')) {
					ZABBIX.PopupManager.open('maintenance.edit');
				}
				else if (e.target.classList.contains('js-massdelete-maintenance')) {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			});
		}

		#delete(target, maintenanceids) {
			const confirmation = maintenanceids.length > 1
				? <?= json_encode(_('Delete selected maintenance periods?')) ?>
				: <?= json_encode(_('Delete selected maintenance period?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'maintenance.delete');

			this.#post(target, maintenanceids, curl.getUrl());
		}

		#post(target, maintenanceids, url) {
			target.classList.add('is-loading');

			const post_data = {
				maintenanceids,
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('maintenance')) ?>
			};

			return fetch(url, {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(post_data)
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('maintenance', response.keepids ?? []);
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

		#updateMultiselect($ms) {
			$ms.multiSelect('setDisabledEntries', [...$ms.multiSelect('getData').map((entry) => entry.id)]);
		}

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: () => uncheckTableRows('maintenance')
			});
		}
	};
</script>
