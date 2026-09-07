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
			if (e.target.classList.contains('js-device-delete')
					&& window.confirm(this.#confirm_messages['user.device.delete'])) {
				this.#delete(e.target, {deviceid: e.target.dataset.deviceid});
			}
		});

		document.querySelector('.js-create-device')?.addEventListener('click', () => {
			ZABBIX.PopupManager.open('user.device.init.view', {admin_mode: 1},
				{popup_options: {prevent_navigation: false}}
			);
		});
	}

	#delete(target, data) {
		target.classList.add('is-loading');
		data[CSRF_TOKEN_NAME] = <?= json_encode(CCsrfTokenHelper::get('user')) ?>;

		this.#post({action: 'user.device.delete'}, data, () => this.#confirmForceDelete(target, data))
			.finally(() => {
				target.classList.remove('is-loading');
				target.blur();
			});
	}

	#confirmForceDelete(target, data) {
		overlayDialogue({
			title: <?= json_encode(_('Remove device?')) ?>,
			content: document.createElement('span').innerText = <?= json_encode(
				_('Device not found. Remove device from the database?')
			) ?>,
			buttons: [
				{
					title: <?= json_encode(_('Cancel')) ?>,
					cancel: true,
					class: ZBX_STYLE_BTN_ALT,
					action: () => {
						location.href = location.href;
					}
				},
				{
					title: <?= json_encode(_('Remove')) ?>,
					focused: true,
					action: () => {
						data.force = 1;
						this.#delete(target, data);
					}
				}
			]
		}, {
			position: Overlay.prototype.POSITION_CENTER_TOP,
			trigger_element: target
		});
	}

	#post(urlparams, data, force_delete_callback) {
		return fetch(zabbixUrl(urlparams), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if (!('force' in data) && 'only_local_device' in response && response.only_local_device) {
					force_delete_callback();

					return;
				}

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
