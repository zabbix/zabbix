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

		init({checkbox_hash, checkbox_object, context, parent_discoveryid, form_name}) {
			this.checkbox_hash = checkbox_hash;
			this.checkbox_object = checkbox_object;
			this.context = context;
			this.is_discovery = parent_discoveryid !== null;
			this.form = document.forms[form_name];

			this.#initActions();
			this.#initPopupListeners();
		}

		#initActions() {
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

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: ({data, event}) => {
					uncheckTableRows('graphs_' + this.checkbox_hash, [], false);

					if (data.submit.success.action === 'delete') {
						const url = new URL(this.is_discovery ? 'host_discovery.php' : 'graphs.php', location.href);

						url.searchParams.set('context', this.context);

						event.setRedirectUrl(url.href);
					}
				}
			});
		}
	};
</script>
