<?php
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

		init({checkbox_hash, checkbox_object, context, form_name, token}) {
			this.checkbox_hash = checkbox_hash;
			this.checkbox_object = checkbox_object;
			this.context = context;
			this.form = document.forms[form_name];
			this.token = token;

			this.#initActions();
			this.#initPopupListeners();
		}

		#initActions() {
			document.getElementById('js-create')?.addEventListener('click', e => {
				ZABBIX.PopupManager.open('graph.edit', {hostid: e.target.dataset.hostid, context: this.context});
			});
			document.getElementById('js-massdelete-graph').addEventListener('click', (e) => {
				this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
			});

			const copy = document.querySelector('.js-copy');

			if (copy !== null) {
				copy.addEventListener('click', () => {
					const overlay = this.#openCopyPopup();
					const dialogue = overlay.$dialogue[0];

					dialogue.addEventListener('dialogue.submit', (e) => {
						postMessageOk(e.detail.success.title);

						const uncheckids = Object.keys(chkbxRange.getSelectedIds());

						uncheckTableRows('graphs_' + this.checkbox_hash, [], false);
						chkbxRange.checkObjects(this.checkbox_object, uncheckids, false);
						chkbxRange.update(this.checkbox_object);

						if ('messages' in e.detail.success) {
							postMessageDetails('success', e.detail.success.messages);
						}

						location.href = location.href;
					});
				});
			}
		}

		#openCopyPopup() {
			const parameters = {
				graphids: Object.keys(chkbxRange.getSelectedIds()),
				source: 'graphs'
			};

			const filter_hostids = document.getElementsByName('filter_hostids[]');
			const context = document.getElementById('context');

			if (filter_hostids.length == 1) {
				parameters.src_hostid = context === 'host' ? filter_hostids[0].value : 0;
			}

			return PopUp('copy.edit', parameters, {
				dialogueid: 'copy',
				dialogue_class: 'modal-popup-static'
			});
		}

		#delete(target, graphids) {
			const confirmation = graphids.length > 1
				? <?= json_encode(_('Delete selected graphs?')) ?>
				: <?= json_encode(_('Delete selected graph?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'graph.delete');

			this.#post(target, graphids, curl);
		}

		#post(target, graphids, url) {
			target.classList.add('is-loading');

			return fetch(url.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({...{graphids: graphids}, ...this.token})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('graph', response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('graph');
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

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: ({data, event}) => {
					uncheckTableRows('graphs_' + this.checkbox_hash, [], false);

					if (data.submit.success.action === 'delete') {
						const url = new URL('zabbix.php', location.href);

						url.searchParams.set('action', 'graph.list');
						url.searchParams.set('context', this.context);

						event.setRedirectUrl(url.href);
					}
				}
			});
		}
	};
</script>
