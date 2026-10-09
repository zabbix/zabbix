<?php declare(strict_types = 0);
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
 */
?>

<script>
	const view = new class {
		init() {
			this.#initActions();
			this.#initPopupListeners();
		}

		#initActions() {
			document.querySelector('.js-create-regexp').addEventListener('click', () => {
				ZABBIX.PopupManager.open('regex.edit');
			});

			document.getElementById('js-massdelete').addEventListener('click', e => {
				this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()), true)
			});

			document.getElementById('js-import').addEventListener('click', () => {
				PopUp("popup.import", {
					rules_preset: "regex",
					[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('import')); ?>
				}, {
					dialogueid: "popup_import",
					dialogue_class: "modal-popup-generic"
				});
			});
		}

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: () => {
					uncheckTableRows('regexp');
					location.href = location.href;
				}
			});
		}

		#delete(target, regexpids) {
			const confirmation = regexpids.length > 1
				? <?= json_encode(_('Delete selected regular expressions?')) ?>
				: <?= json_encode(_('Delete selected regular expression?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'regex.delete');

			this.#post(target, regexpids, curl);
		}

		#post(target, regexpids, url) {
			url.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('regex')) ?>);

			target.classList.add('is-loading');

			return fetch(url.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({regexpids: regexpids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}
						uncheckTableRows('regexp', response.keepids ?? []);

						postMessageDetails('error', response.error.messages);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('regexp');
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
