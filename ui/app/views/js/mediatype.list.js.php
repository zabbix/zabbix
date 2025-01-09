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
			document.getElementById('js-create').addEventListener('click', () =>
				window.popupManagerInstance.openPopup('mediatype.edit', {})
			);

			document.getElementById('js-massenable').addEventListener('click', (e) =>
				this.#enable(e.target, Object.keys(chkbxRange.getSelectedIds()), true)
			);

			document.getElementById('js-massdisable').addEventListener('click', (e) =>
				this.#disable(e.target, Object.keys(chkbxRange.getSelectedIds()), true)
			);

			document.getElementById('js-massdelete').addEventListener('click', (e) =>
				this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()))
			);

			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-test-edit')) {
					PopUp('mediatype.test.edit', {mediatypeid: e.target.dataset.mediatypeid},
						{
							dialogue_class: 'modal-popup-medium',
							dialogueid: 'mediatype_test_edit'
						}
					);
				}
				else if (e.target.classList.contains('js-enable')) {
					this.#enable(e.target, [e.target.dataset.mediatypeid]);
				}
				else if (e.target.classList.contains('js-disable')) {
					this.#disable(e.target, [e.target.dataset.mediatypeid]);
				}
			});

			this.#setSubmitCallback();
		}

		/**
		 * Shows confirmation window when single or multiple media type elements are selected for enabling
		 * and sends a POST request to enable action.
		 *
		 * @param {element} target        The target element that will be passed to post.
		 * @param {array}   mediatypeids  Media type IDs to enable.
		 * @param {bool}    massenable    True if footer button is pressed for mass enable action which brings up
		 *                                confirmation menu. False if only one element with single link is clicked.
		 */
		#enable(target, mediatypeids, massenable = false) {
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

			this.#post(target, mediatypeids, curl);
		}

		/**
		 * Shows confirmation window when single or multiple media type elements are selected for disabling
		 * and sends a POST request to disable action.
		 *
		 * @param {element} target        The target element that will be passed to post.
		 * @param {array}   mediatypeids  Media type IDs to disable.
		 * @param {bool}    massdisable   True if footer button is pressed for mass disable action which brings up
		 *                                confirmation menu. False if only one element with single link is clicked.
		 */
		#disable(target, mediatypeids, massdisable = false) {
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

			this.#post(target, mediatypeids, curl);
		}

		/**
		 * Shows confirmation window if multiple media type elements are selected for deletion and sends a POST request
		 * to delete action.
		 *
		 * @param {element} target        The target element that will be passed to post.
		 * @param {array}   mediatypeids  Media type IDs to delete.
		 */
		#delete(target, mediatypeids) {
			const confirmation = mediatypeids.length > 1
				? <?= json_encode(_('Delete selected media types?')) ?>
				: <?= json_encode(_('Delete selected media type?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'mediatype.delete');

			this.#post(target, mediatypeids, curl);
		}

		/**
		 * Sends a POST request to the specified URL with the provided data and handles the response.
		 *
		 * @param {element} target        The target element that will display a loading state during the request.
		 * @param {array}   mediatypeids  Mediatype IDs to send with the POST request.
		 * @param {object}  url           The URL to send the POST request to.
		 */
		#post(target, mediatypeids, url) {
			url.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('mediatype')) ?>);

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

						uncheckTableRows('mediatype', response.keepids ?? []);
						postMessageDetails('error', response.error.messages);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('mediatype');
					}

					location.href = location.href;
				})
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);
				})
				.finally(() => target.classList.remove('is-loading'));
		}

		#setSubmitCallback() {
			window.popupManagerInstance.setSubmitCallback((e) => {
				if ('success' in e.detail) {
					postMessageOk(e.detail.success.title);

					if ('messages' in e.detail.success) {
						postMessageDetails('success', e.detail.success.messages);
					}
				}

				uncheckTableRows('mediatype');
				location.href = location.href;
			});
		}
	};
</script>
