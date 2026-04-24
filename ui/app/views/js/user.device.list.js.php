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
	#confirm_messages = null;
	#form = null;

	init({confirm_messages}) {
		this.#form = document.forms.devices;
		this.#confirm_messages = confirm_messages;
		this.#initEvents();
	}

	#initEvents() {
		this.#form.addEventListener('click', e => {
			if (e.target.classList.contains('js-device-delete')) {
				this.#delete(e.target, {deviceids: [e.target.dataset.deviceid]});
			}
		});

		document.querySelector('.js-create-device')?.addEventListener('click', () => {
			ZABBIX.PopupManager.open('user.device.create', {admin_mode: 1},
				{popup_options: {prevent_navigation: false}}
			);
		});
	}

	#delete(target, data) {
		data[CSRF_TOKEN_NAME] = <?= json_encode(CCsrfTokenHelper::get('user')) ?>;
		const urlparams = {action: 'user.device.delete'};

		if (target !== null) {
			this.#confirmAction(urlparams, data, target);
		}
		else {
			this.#post(urlparams, data);
		}
	}

	#confirmAction(urlparams, data, target) {
		const confirm = this.#confirm_messages[urlparams.action];
		const message = confirm ? confirm[data.deviceids.length > 1 ? 1 : 0] : '';

		if (!window.confirm(message)) {
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
		return fetch(zabbixUrl(urlparams), {
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

					if ('error_messages' in response.success) {
						postMessageDetails('error', response.success.error_messages);
					}
				}

				location.href = location.href;
			})
			.catch(() => {
				clearMessages();

				const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

				addMessage(message_box);
			});
	}
};
</script>
