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
	#context = null;
	#form = null;
	#tokens = null;
	#hostid = null;
	#confirm_messages = null;

	init({context, tokens, form_name, confirm_messages, hostid}) {
		this.#context = context;
		this.#tokens = tokens;
		this.#form = document.forms[form_name];
		this.#confirm_messages = confirm_messages;
		this.#hostid = hostid;

		this.#initEvents();
		this.#initPopupListeners();
	}

	#initEvents() {
		if (this.#context === 'host') {
			document.getElementById('filter_state').addEventListener('change', (e) => {
				const disabled = e.target.value != -1;
				document.querySelectorAll('[name="filter_status"]').forEach(radio => radio.disabled = disabled);
			});
		}

		this.#form.addEventListener('click', e => {
			if (e.target.classList.contains('js-enable-item')) {
				this.#enable(null, {itemids: [e.target.dataset.itemid], context: this.#context});
			}
			else if (e.target.classList.contains('js-disable-item')) {
				this.#disable(null, {itemids: [e.target.dataset.itemid], context: this.#context});
			}
			else if (e.target.classList.contains('js-massenable-item')) {
				this.#enable(e.target, {itemids: Object.keys(chkbxRange.getSelectedIds()), context: this.#context});
			}
			else if (e.target.classList.contains('js-massdisable-item')) {
				this.#disable(e.target, {itemids: Object.keys(chkbxRange.getSelectedIds()), context: this.#context});
			}
			else if (e.target.classList.contains('js-massexecute-item')) {
				this.#execute(e.target, {itemids: Object.keys(chkbxRange.getSelectedIds()), context: this.#context});
			}
			else if (e.target.classList.contains('js-massdelete-item')) {
				this.#delete(e.target, {itemids: Object.keys(chkbxRange.getSelectedIds()), context: this.#context});
			}
		});

		document.querySelectorAll('#filter_lifetime_type, #filter_enabled_lifetime_type').forEach(element => {
			element.addEventListener('change', () => this.#update());
		});

		document.querySelector('.js-create-item')?.addEventListener('click', () => {
			ZABBIX.PopupManager.open('lldrule.edit', {hostid: this.#hostid, context: this.#context});
		});

		this.#update();
	}

	#update() {
		const lifetime_type = document.querySelector('[name="filter_lifetime_type"]:checked').value;
		const enabled_lifetime_type = document.querySelector('[name="filter_enabled_lifetime_type"]:checked').value;

		document.querySelectorAll('[name="filter_enabled_lifetime_type"]').forEach(radio =>
			radio.disabled = lifetime_type == <?= ZBX_LLD_DELETE_IMMEDIATELY ?>
		);

		document.getElementById('filter_lifetime').disabled = lifetime_type != <?= ZBX_LLD_DELETE_AFTER ?>;
		document.getElementById('filter_enabled_lifetime').disabled =
			enabled_lifetime_type != <?= ZBX_LLD_DISABLE_AFTER ?>
			|| lifetime_type == <?= ZBX_LLD_DELETE_IMMEDIATELY ?>;
	}

	#enable(target, parameters) {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'lldrule.enable');

		if (target !== null) {
			this.#confirmAction(curl, parameters, target);
		}
		else {
			this.#post(curl, parameters);
		}
	}

	#disable(target, parameters) {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'lldrule.disable');

		if (target !== null) {
			this.#confirmAction(curl, parameters, target);
		}
		else {
			this.#post(curl, parameters);
		}
	}

	#execute(target, parameters) {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'item.execute');
		parameters.discovery_rule = 1;

		this.#confirmAction(curl, parameters, target);
	}

	#delete(target, parameters) {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'lldrule.delete');

		this.#confirmAction(curl, parameters, target);
	}

	#confirmAction(curl, data, target) {
		const confirm = this.#confirm_messages[curl.getArgument('action')];
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

	#post(curl, data) {
		const action = curl.getArgument('action');
		const token_name = action === 'item.execute' ? 'item' : 'lldrule';

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({...this.#tokens[token_name], ...data})
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

					if (data.submit.success?.action === 'delete') {
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
