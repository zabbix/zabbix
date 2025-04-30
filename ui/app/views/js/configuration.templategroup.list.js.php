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
		init({delete_url}) {
			this.delete_url = delete_url;

			document.querySelector('.js-create-templategroup').addEventListener('click', () => {
				ZABBIX.PopupManager.open('templategroup.edit');
			});

			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-massdelete-templategroup')) {
					this.delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			});

			this.#initPopupListeners();
		}

		delete(target, groupids) {
			const confirmation = groupids.length > 1
				? <?= json_encode(_('Delete selected template groups?')) ?>
				: <?= json_encode(_('Delete selected template group?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			this.#post(target, groupids, this.delete_url);
		}

		#post(target, groupids, url) {
			target.classList.add('is-loading');

			return fetch(url, {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({groupids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('templategroup', response.error.keepids);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('templategroup');
					}

					location.href = location.href;
				})
				.catch(() => {
					clearMessages();

					const title = <?= json_encode(_('Unexpected server error.')) ?>;
					const message_box = makeMessageBox('bad', [], title, true, false)[0];

					addMessage(message_box);
				})
				.finally(() => {
					target.classList.remove('is-loading');
				});
		}

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: () => uncheckTableRows('templategroup')
			});
		}
	}
</script>
