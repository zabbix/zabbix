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
				if (e.target.classList.contains('js-edit')) {
					this._edit({mediatypeid: e.target.dataset.mediatypeid});
				}
				else if (e.target.classList.contains('js-action-edit')) {
					this._actionEdit({actionid: e.target.dataset.actionid, eventsource: e.target.dataset.eventsource});
				}
				else if (e.target.classList.contains('js-enable')) {
					this._enable(e.target, [e.target.dataset.mediatypeid]);
				}
				else if (e.target.classList.contains('js-disable')) {
					this._disable(e.target, [e.target.dataset.mediatypeid]);
				}
				else if (e.target.classList.contains('js-massdelete')) {
					this._delete(e.target, [e.target.dataset.mediatypeid]);
				}
			})
		}

		_edit(parameters = {}) {
			const overlay = PopUp('mediatype.edit', parameters, {
				dialogueid: 'media-type-form',
				dialogue_class: 'modal-popup-static',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				uncheckTableRows('mediatype');
				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});

			overlay.$dialogue[0].addEventListener('dialogue.delete', (e) => {
				uncheckTableRows('mediatype');

				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});
		}

		_enable(target, mediatypeids, massenable = false) {
			if (massenable) {
				const confirmation = mediatypeids.length > 1
					? <?= json_encode(_('Enable selected media types?')) ?>
					: <?= json_encode(_('Enable selected media type?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'mediatype.enable');

			this._post(target, mediatypeids, curl);
		}

		_disable(target, mediatypeids, massdisable = false) {
			if (massdisable) {
				const confirmation = mediatypeids.length > 1
					? <?= json_encode(_('Disable selected media types?')) ?>
					: <?= json_encode(_('Disable selected media type?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'mediatype.disable');

			this._post(target, mediatypeids, curl);
		}

		_delete(target, mediatypeids) {
			const confirmation = mediatypeids.length > 1
				? <?= json_encode(_('Delete selected media types?')) ?>
				: <?= json_encode(_('Delete selected media type?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'mediatype.delete');

			this._post(target, mediatypeids, curl);
		}

		_actionEdit(parameters = {}) {
			const overlay = PopUp('popup.action.edit', parameters, {
				dialogueid: 'action-edit',
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
				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});
		}

		_post(target, mediatypeids, url) {
			url.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
				<?= json_encode(CCsrfTokenHelper::get('mediatype')) ?>
			);

			target.classList.add('is-loading');

			return fetch(url.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({mediatypeids: mediatypeids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}
					}
					uncheckTableRows('mediatype');

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
