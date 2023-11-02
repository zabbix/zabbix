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
		init({context, hostid, parent_discoveryid, token}) {
			this.context = context;
			this.hostid = hostid;
			this.parent_discoveryid = parent_discoveryid;
			this.token = token;

			this.#initActions();
		}

		#initActions() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-trigger-edit')) {
					this.#edit('trigger.edit', {
						triggerid: e.target.dataset.triggerid,
						hostid: this.hostid,
						context: e.target.dataset.context
					})
				}
				else if (e.target.id === 'js-create') {
					this.#edit('trigger.prototype.edit', {
						parent_discoveryid: this.parent_discoveryid,
						hostid: this.hostid,
						context: this.context
					})
				}
				else if (e.target.classList.contains('js-trigger-prototype-edit')) {
					this.#edit('trigger.prototype.edit', {
						parent_discoveryid: this.parent_discoveryid,
						triggerid: e.target.dataset.triggerid,
						hostid: this.hostid,
						context: this.context
					})
				}
				else if (e.target.classList.contains('js-enable-trigger')) {
					this.#enable(e.target, [e.target.dataset.triggerid]);
				}
				else if (e.target.classList.contains('js-disable-trigger')) {
					this.#disable(e.target, [e.target.dataset.triggerid]);
				}
				else if (e.target.id === 'js-massenable-trigger') {
					this.#enable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
				else if (e.target.id === 'js-massdisable-trigger') {
					this.#disable(e.target, Object.keys(chkbxRange.getSelectedIds()), true);
				}
				else if (e.target.id === 'js-massupdate-trigger') {
					this.#massupdate(e.target);
				}
				else if (e.target.id === 'js-massdelete-trigger') {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			})
		}

		#edit(action, parameters) {
			const overlay = PopUp(action, parameters, {
				dialogueid: 'trigger-edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				uncheckTableRows(this.parent_discoveryid);
				postMessageOk(e.detail.title);

				if ('success' in e.detail) {
					postMessageOk(e.detail.success.title);

					if ('messages' in e.detail.success) {
						postMessageDetails('success', e.detail.success.messages);
					}
				}

				location.href = location.href;
			});
		}

		#enable(target, triggerids, massenable = false) {
			if (massenable) {
				const confirmation = triggerids.length > 1
					? <?= json_encode(_('Create triggers from selected prototypes as enabled?')) ?>
					: <?= json_encode(_('Create triggers from selected prototype as enabled?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'trigger.prototype.enable');

			this.#post(target, triggerids, curl);
		}

		#disable(target, triggerids, massdisable = false) {
			if (massdisable) {
				const confirmation = triggerids.length > 1
					? <?= json_encode(_('Create triggers from selected prototypes as disabled?')) ?>
					: <?= json_encode(_('Create triggers from selected prototype as disabled?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'trigger.prototype.disable');

			this.#post(target, triggerids, curl);
		}

		#massupdate(target) {
			openMassupdatePopup('trigger.prototype.massupdate', { <?= json_encode(CCsrfTokenHelper::CSRF_TOKEN_NAME) ?>:
					<?= json_encode(CCsrfTokenHelper::get('trigger')) ?>
			}, {
				dialogue_class: 'modal-popup-static',
				trigger_element: target
			});
		}

		#delete(target, triggerids) {
			const confirmation = triggerids.length > 1
				? <?= json_encode(_('Delete selected trigger prototypes?')) ?>
				: <?= json_encode(_('Delete selected trigger prototype?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'trigger.prototype.delete');

			this.#post(target, triggerids, curl);
		}

		#post(target, triggerids, url) {
			let fields = {
				triggerids: triggerids
			};

			if (target.dataset.status !== null) {
				fields.status = target.dataset.status;
			}
			else if (target.dataset.discover !== null) {
				fields.discover = target.dataset.discover;
			}

			target.classList.add('is-loading');

			return fetch(url.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({...this.token, ...fields})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows(this.parent_discoveryid, response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows(this.parent_discoveryid);
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

		editItem(target, data) {
			const overlay = PopUp('item.edit', data, {
				dialogueid: 'item-edit',
				dialogue_class: 'modal-popup-large',
				trigger_element: target
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.elementSuccess.bind(this, this.context),
				{once: true}
			);
		}

		editItemPrototype(target, data) {
			const overlay = PopUp('item.prototype.edit', data, {
				dialogueid: 'item-edit',
				dialogue_class: 'modal-popup-large',
				trigger_element: target
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.elementSuccess.bind(this, this.context),
				{once: true}
			);
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

			overlay.$dialogue[0].addEventListener('dialogue.submit',
				this.elementSuccess.bind(this, this.context), {once: true}
			);

			overlay.$dialogue[0].addEventListener('dialogue.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		}

		editTemplate(e, templateid) {
			e.preventDefault();
			const template_data = {templateid};

			this.openTemplatePopup(template_data);
		}

		openTemplatePopup(template_data) {
			const overlay =  PopUp('template.edit', template_data, {
				dialogueid: 'templates-form',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit',
				this.elementSuccess.bind(this, this.context), {once: true}
			);
		}

		elementSuccess(context, e) {
			const data = e.detail;
			let curl = null;

			if ('success' in data) {
				postMessageOk(data.success.title);

				if ('messages' in data.success) {
					postMessageDetails('success', data.success.messages);
				}

				if ('action' in data.success && data.success.action === 'delete') {
					curl = new Curl('host_discovery.php');
					curl.setArgument('context', context);
				}
			}

			uncheckTableRows('trigger_prototypes_' + this.parent_discoveryid, [] ,false);

			if (curl) {
				location.href = curl.getUrl();
			}
			else {
				location.href = location.href;
			}
		}
	}
</script>
