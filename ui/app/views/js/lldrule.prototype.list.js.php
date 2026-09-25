<?php
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
	#context = null;
	#form = null;
	#hostid = null;
	#parent_discoveryid = null;
	#confirm_messages = null;

	init({context, form_name, confirm_messages, hostid, parent_discoveryid}) {
		this.#context = context;
		this.#form = document.forms[form_name];
		this.#confirm_messages = confirm_messages;
		this.#hostid = hostid;
		this.#parent_discoveryid = parent_discoveryid;

		this.#initEvents();
		this.#initPopupListeners();
	}

	#initEvents() {
		this.#form.addEventListener('click', e => {
			if (e.target.classList.contains('js-enable-item')) {
				this.#enable(null, {itemids: [e.target.dataset.itemid], context: this.#context});
			}
			else if (e.target.classList.contains('js-disable-item')) {
				this.#disable(null, {itemids: [e.target.dataset.itemid], context: this.#context});
			}
			if (e.target.classList.contains('js-discover-enable-item')) {
				this.#discoverUpdate(null, {itemids: [e.target.dataset.itemid], context: this.#context,
					discover: <?=ITEM_DISCOVER ?>
				});
			}
			else if (e.target.classList.contains('js-discover-disable-item')) {
				this.#discoverUpdate(null, {itemids: [e.target.dataset.itemid], context: this.#context,
					discover: <?=ITEM_NO_DISCOVER ?>
				});
			}
			else if (e.target.classList.contains('js-massenable-item')) {
				this.#enable(e.target, {itemids: Object.keys(chkbxRange.getSelectedIds()), context: this.#context});
			}
			else if (e.target.classList.contains('js-massdisable-item')) {
				this.#disable(e.target, {itemids: Object.keys(chkbxRange.getSelectedIds()), context: this.#context});
			}
			else if (e.target.classList.contains('js-massdelete-item')) {
				this.#delete(e.target, {itemids: Object.keys(chkbxRange.getSelectedIds()), context: this.#context});
			}
		});

		document.querySelector('.js-create-item')?.addEventListener('click', () => {
			ZABBIX.PopupManager.open('lldrule.prototype.edit',
				{parent_discoveryid: this.#parent_discoveryid, context: this.#context}
			);
		});
	}

	#enable(target, data) {
		const urlparams = {action: 'lldrule.prototype.enable'};

		if (target !== null) {
			this.#confirmAction(urlparams, data, target);
		}
		else {
			this.#post(urlparams, data);
		}
	}

	#disable(target, data) {
		const urlparams = {action: 'lldrule.prototype.disable'};

		if (target !== null) {
			this.#confirmAction(urlparams, data, target);
		}
		else {
			this.#post(urlparams, data);
		}
	}

	#discoverUpdate(target, data) {
		this.#post({action: 'lldrule.prototype.updatediscover'}, data);
	}

	#delete(target, data) {
		this.#confirmAction({action: 'lldrule.prototype.delete'}, data, target);
	}

	#confirmAction(urlparams, data, target) {
		const confirm = this.#confirm_messages[urlparams.action];
		const message = confirm ? confirm[data.itemids.length > 1 ? 1 : 0] : '';

		if (message != '' && !window.confirm(message)) {
			return;
		}

		target.classList.add('is-loading');
		this.#post(urlparams, data)
			.finally(() => {
				target.classList.remove('is-loading');
				target.blur();
			});
	}

	#post(urlparams, data) {
		urlparams[CSRF_TOKEN_NAME] = <?= json_encode(CCsrfTokenHelper::get('lldrule')) ?>;

		return fetch(zabbixUrl(urlparams), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				let new_href = location.href;

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
				}

				location.href = new_href;
			})
			.catch(() => {
				clearMessages();

				const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

				addMessage(message_box);
			});
	}

	#initPopupListeners() {
		ZABBIX.EventHub.subscribe({
			require: {
				context: CPopupManager.EVENT_CONTEXT,
				event: CPopupManagerEvent.EVENT_SUBMIT
			},
			callback: ({data, descriptor, event}) => {
				if ('error' in data.submit) {
					if ('title' in data.submit.error) {
						postMessageError(data.submit.error.title);
					}

					postMessageDetails('error', data.submit.error.messages);
				}
				else {
					chkbxRange.clearSelectedOnFilterChange();

					if (data.submit?.redirect_url) {
						const url = new URL(data.submit.redirect_url, location.href);
						event.setRedirectUrl(url.href);
					}
					else if (data.submit.success?.action === 'delete') {
						const url = new URL('zabbix.php', location.href);

						url.searchParams.set('action', 'lldrule.list');
						url.searchParams.set('context', this.#context);

						event.setRedirectUrl(url.href);
					}
				}
			}
		});
	}
};
</script>
