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

			this.initFilterForm();
			this.initEvents();
		}

		initFilterForm() {
			this.field = {
				parent_discoveryid: this.form.querySelector('#form_parent_discoveryid').value
			}
		}

		initEvents() {
			document.querySelector('.js-create-item-prototype')?.addEventListener('click', (e) => this.#edit(
				e.target,
				{...e.target.dataset, action: 'item.prototype.edit'}
			));
			this.form.addEventListener('click', e => {
				const target = e.target;
				const itemids = Object.keys(chkbxRange.getSelectedIds());

				if (target.matches('.js-update-item')) {
					this.#edit(target, {...target.dataset, action: 'item.edit'});
				}
				else if (target.matches('.js-update-itemprototype')) {
					this.#edit(target, {...target.dataset, action: 'item.prototype.edit'});
				}
				else if (target.matches('.js-enable-itemprototype')) {
					this.#enable(null, {...target.dataset, itemids: [target.dataset.itemid]});
				}
				else if (target.matches('.js-disable-itemprototype')) {
					this.#disable(null, {...target.dataset, itemids: [target.dataset.itemid]});
				}
				else if (target.matches('.js-massenable-itemprototype')) {
					this.#enable(target, {itemids: itemids, context: this.context, field: 'status'});
				}
				else if (target.matches('.js-massdisable-itemprototype')) {
					this.#disable(target, {itemids: itemids, context: this.context, field: 'status'});
				}
				else if (target.classList.contains('js-massupdate-itemprototype')) {
					this.#massupdate(target, {ids: itemids, context: this.context,
						parent_discoveryid: this.field.parent_discoveryid
					});
				}
				else if (target.matches('.js-massdelete-itemprototype')) {
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
				.then((response) => {
					uncheckTableRows(this.field.parent_discoveryid);
					this.#navigate(response, location.href);
				})
				.catch(() => {
					clearMessages();
					addMessage(makeMessageBox('bad', [t('Unexpected server error.')]));
				});
		}

		#edit(target, parameters = {}) {
			const action = parameters.action;

			delete parameters.action;
			this.#popup(action, parameters, {
				dialogueid: 'item-edit',
				dialogue_class: 'modal-popup-large',
				trigger_element: target
			});
		}

		#massupdate(target, parameters) {
			this.#popup('item.prototype.massupdate', {...this.token, ...parameters, prototype: 1}, {
				dialogue_class: 'modal-popup-preprocessing',
				trigger_element: target
			});
		}

		#popup(action, parameters, overlay_options) {
			const overlay = PopUp(action, parameters, overlay_options);

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}

					uncheckTableRows(this.field.parent_discoveryid);
				}

				this.#navigate(e.detail, location.href);
			});

			return overlay;
		}

		editItemPrototype(target, data) {
			this.#edit(target, {...data, action: 'item.prototype.edit'});
		}

		editTriggerPrototype(trigger_data) {
			clearMessages();

			const overlay = PopUp('trigger.prototype.edit', trigger_data, {
				dialogueid: 'trigger-edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => this.#navigate(e.detail, location.href),
				{once: true}
			);
		}

		editHost(e, hostid) {
			e.preventDefault();
			this.openHostPopup({hostid});
		}

		editTemplate(e, templateid) {
			e.preventDefault();
			const template_data = {templateid};

			this.openTemplatePopup(template_data);
		}

		openHostPopup(host_data) {
			let original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', e => {
				if (e.detail.success.action === 'delete') {
					let list_url = new Curl('zabbix.php');
					list_url.setArgument('action', 'host.list');
					original_url = list_url.getUrl();
				}

				history.replaceState({}, '', original_url);
				this.#navigate(e.detail, original_url);
			});
		}

		openTemplatePopup(template_data) {
			let original_url = location.href;
			const overlay =  PopUp('template.edit', template_data, {
				dialogueid: 'templates-form',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', e => {
				if (e.detail.success.action === 'delete') {
					let list_url = new Curl('zabbix.php');
					list_url.setArgument('action', 'template.list');
					original_url = list_url.getUrl();
				}

				this.#navigate(e.detail, original_url);
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
