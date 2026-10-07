<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
* @var array $data
*/
?>

<script>
	const view = new class {

		init() {
			this.#initActions();
			this.#initPopupListeners();
		}

		#initActions() {
			document.addEventListener('click', e => {
				if (e.target.classList.contains('js-create-scheduledreport')) {
					ZABBIX.PopupManager.open('scheduledreport.edit');
				}
				else if (e.target.classList.contains('js-enable-scheduledreport')) {
					this.#enable(e.target, [e.target.dataset.reportid]);
				}
				else if (e.target.classList.contains('js-disable-scheduledreport')) {
					this.#disable(e.target, [e.target.dataset.reportid]);
				}
				else if (e.target.classList.contains('js-massenable-scheduledreport')) {
					this.#enable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
				else if (e.target.classList.contains('js-massdisable-scheduledreport')) {
					this.#disable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
				else if (e.target.classList.contains('js-massdelete-scheduledreport')) {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			});
		}

		#enable(target, reportids, massenable = false) {
			if (massenable) {
				const confirmation = reportids.length > 1
					? <?= json_encode(_('Enable selected scheduled reports?')) ?>
					: <?= json_encode(_('Enable selected scheduled report?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'scheduledreport.enable');

			this.#post(target, reportids, curl);
		}

		#disable(target, reportids, massdisable = false) {
			if (massdisable) {
				const confirmation = reportids.length > 1
					? <?= json_encode(_('Disable selected scheduled reports?')) ?>
					: <?= json_encode(_('Disable selected scheduled report?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'scheduledreport.disable');

			this.#post(target, reportids, curl);
		}

		#delete(target, reportids) {
			const confirmation = reportids.length > 1
				? <?= json_encode(_('Delete selected scheduled reports?')) ?>
				: <?= json_encode(_('Delete selected scheduled report?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'scheduledreport.delete');

			this.#post(target, reportids, curl);
		}

		#post(target, reportids, curl) {
			target.classList.add('is-loading');

			curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('scheduledreport')) ?>);

			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({reportids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('scheduledreport', response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('scheduledreport');
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

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: () => uncheckTableRows('scheduledreport')
			});
		}
	};
</script>
