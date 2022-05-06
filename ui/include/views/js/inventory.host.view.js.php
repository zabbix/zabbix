<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

		constructor() {
			this._registerEvents();
		}

		editHost({hostid}) {
			this._openHostPopup({hostid});
		}

		_openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large'
			});

			overlay.$dialogue[0].addEventListener('dialogue.create', this._events.hostCreate);
			overlay.$dialogue[0].addEventListener('dialogue.update', this._events.hostUpdate);
			overlay.$dialogue[0].addEventListener('dialogue.delete', this._events.hostDelete);
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			});
		}

		_registerEvents() {
			this._events = {
				hostCreate(e) {
					if ('success' in e.detail) {
						clearMessages();

						const message_box = makeMessageBox('good', e.detail.success.messages ?? [],
							e.detail.success.title
						);

						addMessage(message_box);
					}
				},

				hostUpdate(e) {
					if ('success' in e.detail) {
						postMessageOk(e.detail.success.title);

						if ('messages' in e.detail.success) {
							postMessageDetails('success', e.detail.success.messages);
						}
					}

					location.href = location.href;
				},

				hostDelete(e) {
					if ('success' in e.detail) {
						postMessageOk(e.detail.success.title);

						if ('messages' in e.detail.success) {
							postMessageDetails('success', e.detail.success.messages);
						}
					}

					location.href = new Curl('hostinventories.php', false).getUrl();
				}
			};
		}
	};
</script>
