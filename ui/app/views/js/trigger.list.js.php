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
<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate(); ?>
</script>

<script>
	const view = new class {
		init({checkbox_hash, checkbox_object, context, token, form_name}) {
			this.checkbox_hash = checkbox_hash;
			this.checkbox_object = checkbox_object;
			this.context = context;
			this.token = token;
			this.form = document.forms[form_name];

			this.#initFilter();
			this.#initActions();
			this.#initPopupListeners();
		}

		#initFilter() {
			$('#filter-tags')
				.dynamicRows({template: '#filter-tag-row-tmpl'})
				.on('afteradd.dynamicRows', function() {
					const rows = this.querySelectorAll('.form_row');
					new CTagFilterItem(rows[rows.length - 1]);
				});

			// Init existing fields once loaded.
			document.querySelectorAll('#filter-tags .form_row').forEach(row => {
				new CTagFilterItem(row);
			});

			if (this.context === 'host') {
				document.getElementById('filter_state').addEventListener('change', () => {
					const filter_state = document.querySelector('input[name=filter_state]:checked').value;
					const filter_status_fields = document.getElementsByName('filter_status');

					for (let i = 0; i < filter_status_fields.length; i++) {
						filter_status_fields[i].disabled = filter_state != -1;
					}
				})
			}

			const filter_fields = ['#filter_groupids_', '#filter_hostids_']

			filter_fields.forEach(filter => {
				$(filter).on('change', () => this.#updateMultiselect($(filter)));
				this.#updateMultiselect($(filter));
			})
		}

		#initActions() {
			this.form.addEventListener('click', e => {
				const target = e.target;

				if (target.classList.contains('js-enable-trigger')) {
					this.#enable(target, [target.dataset.triggerid]);
				}
				else if (target.classList.contains('js-disable-trigger')) {
					this.#disable(target, [target.dataset.triggerid]);
				}
			});

			document.getElementById('js-create')?.addEventListener('click', e => {
				ZABBIX.PopupManager.open('trigger.edit', {hostid: e.target.dataset.hostid, context: this.context});
			});
			document.getElementById('js-copy').addEventListener('click', () => this.#copy());
			document.getElementById('js-massenable-trigger').addEventListener('click', (e) => {
				this.#enable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
			});
			document.getElementById('js-massdisable-trigger').addEventListener('click', (e) => {
				this.#disable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
			});
			document.getElementById('js-massupdate-trigger').addEventListener('click', (e) => {
				this.#massupdate(e.target);
			});
			document.getElementById('js-massdelete-trigger').addEventListener('click', (e) => {
				this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
			});
		}

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: () => uncheckTableRows('trigger')
			});
		}

		#copy() {
			const overlay = this.openCopyPopup();
			const dialogue = overlay.$dialogue[0];

			dialogue.addEventListener('dialogue.submit', (e) => {
				postMessageOk(e.detail.success.title);

				const uncheckids = Object.keys(chkbxRange.getSelectedIds());
				uncheckTableRows('triggers_' + this.checkbox_hash, [], false);
				chkbxRange.checkObjects(this.checkbox_object, uncheckids, false);
				chkbxRange.update(this.checkbox_object);

				if ('messages' in e.detail.success) {
					postMessageDetails('success', e.detail.success.messages);
				}

				location.href = location.href;
			});
		}

		#enable(target, triggerids, massenable = false) {
			if (massenable) {
				const confirmation = triggerids.length > 1
					? <?= json_encode(_('Enable selected triggers?')) ?>
					: <?= json_encode(_('Enable selected trigger?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'trigger.enable');

			this.#post(target, triggerids, curl);
		}

		#disable(target, triggerids, massdisable = false) {
			if (massdisable) {
				const confirmation = triggerids.length > 1
					? <?= json_encode(_('Disable selected triggers?')) ?>
					: <?= json_encode(_('Disable selected trigger?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'trigger.disable');

			this.#post(target, triggerids, curl);
		}

		#delete(target, triggerids) {
			const confirmation = triggerids.length > 1
				? <?= json_encode(_('Delete selected triggers?')) ?>
				: <?= json_encode(_('Delete selected trigger?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'trigger.delete');

			this.#post(target, triggerids, curl);
		}

		#massupdate(target) {
			openMassupdatePopup('trigger.massupdate', {
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('trigger')) ?>
			}, {
				dialogue_class: 'modal-popup-static',
				trigger_element: target
			});
		}

		#updateMultiselect($ms) {
			$ms.multiSelect('setDisabledEntries', [...$ms.multiSelect('getData').map((entry) => entry.id)]);
		}

		#post(target, triggerids, url) {
			target.classList.add('is-loading');

			return fetch(url.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({...{triggerids: triggerids}, ...this.token})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('trigger', response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('trigger');
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

		openCopyPopup() {
			const parameters = {
				triggerids: Object.keys(chkbxRange.getSelectedIds()),
				source: 'triggers'
			};

			const filter_hostids = document.getElementsByName('filter_hostids[]');

			if (filter_hostids.length == 1) {
				parameters.src_hostid = filter_hostids[0].value;
			}

			return PopUp('copy.edit', parameters, {
				dialogueid: 'copy',
				dialogue_class: 'modal-popup-static'
			});
		}
	};
</script>
