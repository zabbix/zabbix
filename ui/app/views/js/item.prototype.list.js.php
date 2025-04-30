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
 * @var array $data
 */

?>
<script>
	const view = new class {
		init({form_name, token, confirm_messages, context}) {
			this.token = token;
			this.context = context;
			this.form = document.forms[form_name];
			this.confirm_messages = confirm_messages;

			this.initFilterForm();
			this.initEvents();
			this.#initPopupListeners();
		}

		initFilterForm() {
			this.field = {
				parent_discoveryid: this.form.querySelector('#form_parent_discoveryid').value
			}
		}

		initEvents() {
			document.querySelector('.js-create-item-prototype').addEventListener('click', e => {
				ZABBIX.PopupManager.open('item.prototype.edit', e.target.dataset);
			});

			this.form.addEventListener('click', e => {
				const target = e.target;
				const itemids = Object.keys(chkbxRange.getSelectedIds());

				if (target.classList.contains('js-enable-itemprototype')) {
					this.#enable(null, {...target.dataset, itemids: [target.dataset.itemid]});
				}
				else if (target.classList.contains('js-disable-itemprototype')) {
					this.#disable(null, {...target.dataset, itemids: [target.dataset.itemid]});
				}
				else if (target.classList.contains('js-massenable-itemprototype')) {
					this.#enable(target, {itemids: itemids, context: this.context, field: 'status'});
				}
				else if (target.classList.contains('js-massdisable-itemprototype')) {
					this.#disable(target, {itemids: itemids, context: this.context, field: 'status'});
				}
				else if (target.classList.contains('js-massupdate-itemprototype')) {
					this.#massupdate(target, {ids: itemids, context: this.context,
						parent_discoveryid: this.field.parent_discoveryid
					});
				}
				else if (target.classList.contains('js-massdelete-itemprototype')) {
					this.#delete(target, {itemids: itemids, context: this.context});
				}
			});
		}

		#enable(target, parameters) {
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'item.prototype.enable');

			if (target !== null) {
				this.#confirmAction(curl, parameters, target);
			}
			else {
				this.#post(curl, parameters);
			}
		}

		#disable(target, parameters) {
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'item.prototype.disable');

			if (target !== null) {
				this.#confirmAction(curl, parameters, target);
			}
			else {
				this.#post(curl, parameters);
			}
		}

		#delete(target, parameters) {
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'item.prototype.delete');

			if (target !== null) {
				this.#confirmAction(curl, parameters, target);
			}
			else {
				this.#post(curl, parameters);
			}
		}

		#confirmAction(curl, data, target) {
			const confirm = this.confirm_messages[curl.getArgument('action')];
			const message = confirm ? confirm[data.itemids.length > 1 ? 1 : 0] : '';

			if (message != '' && !window.confirm(message)) {
				return;
			}

			target.classList.add('is-loading');
			this.#post(curl, data)
				.finally(() => {
					target.classList.remove('is-loading');
					target.blur();
				});
		}

		#post(curl, parameters) {
			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({...this.token, ...parameters})
			})
				.then((response) => response.json())
				.then((response) => this.elementSuccess({detail: response}))
				.catch(() => {
					clearMessages();
					addMessage(makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]));
				});
		}

		#massupdate(target, parameters) {
			const overlay = PopUp('item.prototype.massupdate', {...this.token, ...parameters, prototype: 1}, {
				dialogue_class: 'modal-popup-preprocessing',
				trigger_element: target
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit',
				e => this.elementSuccess('title' in e.detail ? {detail: {success: e.detail}} : e),
				{once: true}
			);
		}

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: ({data, event}) => {
					if ('error' in data.submit) {
						if ('title' in data.submit.error) {
							postMessageError(data.submit.error.title);
						}

						postMessageDetails('error', data.submit.error.messages);
					}
					else {
						chkbxRange.clearSelectedOnFilterChange();
					}

					if (data.submit.success.action === 'delete') {
						const url = new URL('host_discovery.php', location.href);

						url.searchParams.set('context', this.context);
						url.searchParams.set('filter_set', 1);

						event.setRedirectUrl(url.href);
					}
				}
			});
		}

		elementSuccess(e) {
			let new_href = location.href;
			const response = e.detail;

			if ('error' in response) {
				if ('title' in response.error) {
					postMessageError(response.error.title);
				}

				postMessageDetails('error', response.error.messages);
			}
			else if ('success' in response) {
				chkbxRange.clearSelectedOnFilterChange();
				postMessageOk(response.success.title);

				if ('messages' in response.success) {
					postMessageDetails('success', response.success.messages);
				}

				if (response.success.action === 'delete') {
					let list_url = new Curl('host_discovery.php');

					list_url.setArgument('context', this.context);
					list_url.setArgument('filter_set', 1);
					new_href = list_url.getUrl();
				}
			}

			location.href = new_href;
		}
	};
</script>
