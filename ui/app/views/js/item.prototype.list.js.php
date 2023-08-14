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
<script>
	const view = new class {
		init({form_name, token, confirm_messages, context}) {
			this.token = token;
			this.context = context;
			this.form = document.forms[form_name];
			this.confirm_messages = confirm_messages;

			this.initEvents();
		}

		initEvents() {
			document.querySelector('.js-create-item-prototype')
				?.addEventListener('click', (e) => this.#edit(e.target, e.target.dataset));
			this.form.addEventListener('click', e => {
				const target = e.target;
				const selectedids = Object.keys(chkbxRange.getSelectedIds());

				if (target.matches('.js-update-item')) {
					this.#edit(target, target.dataset);
				}
				else if (target.matches('.js-enable-item')) {
					this.#enable(null, {...target.dataset, itemids: [target.dataset.itemid]});
				}
				else if (target.matches('.js-disable-item')) {
					this.#disable(null, {...target.dataset, itemids: [target.dataset.itemid]});
				}
				else if (target.matches('.js-massenable-item')) {
					this.#enable(target, {itemids: selectedids, context: this.context, field: 'status'});
				}
				else if (target.matches('.js-massdisable-item')) {
					this.#disable(target, {itemids: selectedids, context: this.context, field: 'status'});
				}
				else if (target.matches('.js-massdelete-item')) {
					this.#delete(target, {itemids: selectedids, context: this.context});
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
			parameters[this.token.token] = this.token.value;

			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(parameters)
			})
				.then((response) => response.json())
				.then((response) => {
					uncheckTableRows('itemprototype');
					this.#navigate(response, location.href);
				})
				.catch(() => {
					clearMessages();
					addMessage(makeMessageBox('bad', [t('Unexpected server error.')]));
				});
		}

		#edit(target, parameters = {}) {
			this.#popup('item.prototype.edit', parameters, {
				dialogueid: 'item-edit',
				dialogue_class: 'modal-popup-large',
				trigger_element: target
			});
		}

		#popup(action, parameters, overlay_options) {
			const overlay = PopUp(action, parameters, overlay_options);

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				uncheckTableRows('itemprototype');
				this.#navigate(e.detail, location.href);
			});

			return overlay;
		}

		editHost(e, hostid) {
			e.preventDefault();
			this.openHostPopup({hostid});
		}

		openHostPopup(host_data) {
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});
			const host_list = new Curl('zabbix.php');

			host_list.setArgument('action', 'host.list');
			overlay.$dialogue[0].addEventListener('dialogue.submit', e => this.#navigate(e.detail, location.href));
			overlay.$dialogue[0].addEventListener('dialogue.create', e => this.#navigate(e.detail, location.href));
			overlay.$dialogue[0].addEventListener('dialogue.update', e => this.#navigate(e.detail, location.href));
			overlay.$dialogue[0].addEventListener('dialogue.delete', e => this.#navigate(e.detail, host_list.getUrl()));
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', location.href);
			});
		}

		#navigate(response, url) {
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

			location.href = url;
		}
	};
</script>
