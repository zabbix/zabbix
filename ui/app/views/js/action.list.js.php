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

		init({eventsource}) {
			this.eventsource = eventsource;
			this._initActions();
		}

		_initActions() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-action-create')) {
					this._edit({eventsource: this.eventsource});
				}
				else if (e.target.classList.contains('js-action-edit')) {
					this._edit({actionid: e.target.dataset.actionid, eventsource: this.eventsource});
				}
				else if (e.target.classList.contains('js-enable-action')) {
					this._enable(e.target, [e.target.dataset.actionid]);
				}
				else if (e.target.classList.contains('js-massenable-action')) {
					this._enable(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-disable-action')) {
					this._disable(e.target, [e.target.dataset.actionid]);
				}
				else if (e.target.classList.contains('js-massdisable-action')) {
					this._disable(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-massdelete-action')) {
					this._delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			})
		}

		_edit(parameters = {}) {
			const overlay = PopUp('popup.action.edit', parameters, {
				dialogueid: 'action-edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				uncheckTableRows('action_' + this.eventsource);
				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});
		}

		_enable(target, actionids) {
			const confirmation = actionids.length > 1
				? <?= json_encode(_('Enable selected actions?')) ?>
				: <?= json_encode(_('Enable selected action?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'action.enable');

			this._post(target, actionids, curl);
		}

		_disable(target, actionids) {
			const confirmation = actionids.length > 1
				? <?= json_encode(_('Disable selected actions?')) ?>
				: <?= json_encode(_('Disable selected action?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'action.disable');

			this._post(target, actionids, curl);
		}

		_delete(target, actionids) {
			const confirmation = actionids.length > 1
				? <?= json_encode(_('Delete selected actions?')) ?>
				: <?= json_encode(_('Delete selected action?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'action.delete');

			this._post(target, actionids, curl);
		}

		_post(target, actionids, url) {
			url.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
				<?= json_encode(CCsrfTokenHelper::get('action')) ?>
			);

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
	};
</script>
