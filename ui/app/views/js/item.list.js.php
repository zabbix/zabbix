<?php
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

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate() ?>
</script>

<script>
	const view = new class {

		init({confirm_messages, token}) {
			this.confirm_messages = confirm_messages;
			this.token = token;

			this._initFilterForm();
			this._initActions();
		}

		_initFilterForm() {
			document.querySelector('#filter_state').addEventListener('change', e => {
				const state = e.target.getAttribute('value');

				document.querySelectorAll('input[name=filter_status]').forEach(checkbox => {
					checkbox.toggleAttribute('disabled', state != -1);
				});
			});

			this._initTagFilter();
		}

		_initTagFilter() {
			$('#filter-tags')
				.dynamicRows({template: '#filter-tag-row-tmpl'})
				.on('afteradd.dynamicRows', function() {
					const rows = this.querySelectorAll('.form_row');

					new CTagFilterItem(rows[rows.length - 1]);
				});

			document.querySelectorAll('#filter-tags .form_row').forEach(row => {
				new CTagFilterItem(row);
			});
		}

		_initActions() {
			document.addEventListener('click', (e) => {
				const target = e.target;

				if (target.classList.contains('js-enable-item')) {
					this._enable(target, [target.dataset.itemid]);
				}
				else if (target.classList.contains('js-disable-item')) {
					this._disable(target, [target.dataset.itemid]);
				}
				else if (target.classList.contains('js-massenable-item')) {
					this._enable(target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (target.classList.contains('js-massdisable-item')) {
					this._disable(target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (target.classList.contains('js-execute-item')) {
					this._execute(target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (target.classList.contains('js-massclearhistory-item')) {
					this._clear(target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (target.classList.contains('js-masscopy-item')) {
					this._copy(target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (target.classList.contains('js-massupdate-item')) {
					this._update(target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (target.classList.contains('js-massdelete-item')) {
					this._delete(target, Object.keys(chkbxRange.getSelectedIds()));
				}
			});
		}

		_enable(target, itemids) {
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.enable');

			this._confirmWithPost(target, {itemids}, curl);
		}

		_disable(target, itemids) {
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.disable');

			this._confirmWithPost(target, {itemids}, curl);
		}

		_execute(target, itemids) {
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.execute');

			this._post(target, {itemids}, curl);
		}

		_clear(target, itemids) {
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.clear');

			this._confirmWithPost(target, {itemids}, curl);
		}

		_copy(target, itemids) {
			console.error('Not implemented');
		}

		_update(target, itemids) {
			console.error('Not implemented');
		}

		_delete(target, itemids) {
			console.error('Not implemented');
		}

		_confirmWithPost(target, data, curl) {
			const confirm = this.confirm_messages[curl.getArgument('action')];
			const message = confirm[data.itemids.length > 1 ? 1 : 0];

			if (message != '' && !window.confirm(message)) {
				return;
			}

			this._post(target, data, curl);
		}

		_post(target, data, curl) {
			target.classList.add('is-loading');
			data[this.token[0]] = this.token[1];

			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(data)
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

					uncheckTableRows('item');
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



		oldActions() {
			document.querySelector('.js-copy').addEventListener('click', () => {
				const overlay = this.openCopyPopup();
				const dialogue = overlay.$dialogue[0];

				dialogue.addEventListener('dialogue.submit', (e) => {
					postMessageOk(e.detail.title);

					const uncheckids = Object.keys(chkbxRange.getSelectedIds());
					uncheckTableRows('items_' + this.checkbox_hash, [], false);
					chkbxRange.checkObjects(this.checkbox_object, uncheckids, false);
					chkbxRange.update(this.checkbox_object);

					if ('messages' in e.detail) {
						postMessageDetails('success', e.detail.messages);
					}

					location.href = location.href;
				});
			});

			const execute_now = document.querySelector('.js-execute-now');

			if (execute_now !== null) {
				execute_now.addEventListener('click', () => {
					this.massCheckNow();
				});
			}
		}

		editHost(e, hostid) {
			e.preventDefault();
			const host_data = {hostid};

			this.openHostPopup(host_data);
		}

		openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});
			const events = {
				hostSuccess(e) {
					const data = e.detail;

					if ('success' in data) {
						postMessageOk(data.success.title);

						if ('messages' in data.success) {
							postMessageDetails('success', data.success.messages);
						}
					}

					location.href = location.href;
				},

				hostDelete(e) {
					const data = e.detail;

					if ('success' in data) {
						postMessageOk(data.success.title);

						if ('messages' in data.success) {
							postMessageDetails('success', data.success.messages);
						}
					}

					const curl = new Curl('zabbix.php');
					curl.setArgument('action', 'host.list');

					location.href = curl.getUrl();
				}
			}

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostDelete, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		}

		openCopyPopup() {
			const parameters = {
				itemids: Object.keys(chkbxRange.getSelectedIds()),
				source: 'items'
			};

			return PopUp('copy.edit', parameters, {
				dialogueid: 'copy',
				dialogue_class: 'modal-popup-static'
			});
		}

		massCheckNow() {
			document.activeElement.classList.add('is-loading');

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.masscheck_now');
			curl.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
				<?= json_encode(CCsrfTokenHelper::get('item')) ?>
			);

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({itemids: Object.keys(chkbxRange.getSelectedIds())})
			})
				.then((response) => response.json())
				.then((response) => {
					clearMessages();

					if ('error' in response) {
						addMessage(makeMessageBox('bad', [response.error.messages], response.error.title, true, true));
					}
					else if('success' in response) {
						addMessage(makeMessageBox('good', [], response.success.title, true, false));

						const uncheckids = Object.keys(chkbxRange.getSelectedIds());
						uncheckTableRows('items_' + this.checkbox_hash, [], false);
						chkbxRange.checkObjects(this.checkbox_object, uncheckids, false);
						chkbxRange.update(this.checkbox_object);
					}
				})
				.catch(() => {
					const title = <?= json_encode(_('Unexpected server error.')) ?>;
					const message_box = makeMessageBox('bad', [], title)[0];

					clearMessages();
					addMessage(message_box);
				})
				.finally(() => {
					// Deselect the "Execute now" button in both success and error cases, since there is no page reload.
					document.activeElement.blur();
				});

			document.activeElement.classList.remove('is-loading');
		}

		checkNow(itemid) {
			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.masscheck_now');
			curl.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
				<?= json_encode(CCsrfTokenHelper::get('item')) ?>
			);

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({itemids: [itemid]})
			})
				.then((response) => response.json())
				.then((response) => {
					clearMessages();

					/*
					 * Using postMessageError or postMessageOk would mean that those messages are stored in session
					 * messages and that would mean to reload the page and show them. Also postMessageError would be
					 * displayed right after header is loaded. Meaning message is not inside the page form like that is
					 * in postMessageOk case. Instead show message directly that comes from controller.
					 */
					if ('error' in response) {
						addMessage(makeMessageBox('bad', [response.error.messages], response.error.title, true, true));
					}
					else if('success' in response) {
						addMessage(makeMessageBox('good', [], response.success.title, true, false));
					}
				})
				.catch(() => {
					const title = <?= json_encode(_('Unexpected server error.')) ?>;
					const message_box = makeMessageBox('bad', [], title)[0];

					clearMessages();
					addMessage(message_box);
				});
		}
	};
</script>
