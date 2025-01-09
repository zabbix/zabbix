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

		init({eventsource}) {
			this.eventsource = eventsource;
			this.#initActions();
			this.#setSubmitCallback();
		}

		#initActions() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-action-create')) {
					window.popupManagerInstance.openPopup('action.edit', {eventsource: this.eventsource});
				}
				else if (e.target.classList.contains('js-enable-action')) {
					this.#enable(e.target, [e.target.dataset.actionid]);
				}
				else if (e.target.classList.contains('js-massenable-action')) {
					this.#enable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
				else if (e.target.classList.contains('js-disable-action')) {
					this.#disable(e.target, [e.target.dataset.actionid]);
				}
				else if (e.target.classList.contains('js-massdisable-action')) {
					this.#disable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
				else if (e.target.classList.contains('js-massdelete-action')) {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			})
		}

		#enable(target, actionids, massenable = false) {
			if (massenable) {
				const confirmation = actionids.length > 1
					? <?= json_encode(_('Enable selected actions?')) ?>
					: <?= json_encode(_('Enable selected action?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'action.enable');

			this.#post(target, actionids, curl);
		}

		#disable(target, actionids, massdisable = false) {
			if (massdisable) {
				const confirmation = actionids.length > 1
					? <?= json_encode(_('Disable selected actions?')) ?>
					: <?= json_encode(_('Disable selected action?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'action.disable');

			this.#post(target, actionids, curl);
		}

		#delete(target, actionids) {
			const confirmation = actionids.length > 1
				? <?= json_encode(_('Delete selected actions?')) ?>
				: <?= json_encode(_('Delete selected action?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'action.delete');

			this.#post(target, actionids, curl);
		}

		#post(target, actionids, url) {
			url.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('action')) ?>);

			target.classList.add('is-loading');

			return fetch(url.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({actionids: actionids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('action_' + this.eventsource, response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('action_' + this.eventsource);
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

				uncheckTableRows('action_' + this.eventsource);
				location.href = location.href;
			});
		}
	};
</script>
