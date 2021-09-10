<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
	const view = {

		init() {
			host_popup.init();
		},

		editHost({hostid}) {
			const original_url = location.href;

			const overlay = PopUp('popup.host.edit', {hostid}, 'host_edit', document.activeElement);

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostCreate);
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostUpdate);
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostDelete);
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		},

		events: {
			hostCreate: (e) => {
				const data = e.detail;

				if ('success' in data) {
					const title = data.success.title;
					let messages = [];

					if ('messages' in data.success) {
						messages = data.success.messages;
					}

					const message_box = makeMessageBox('good', messages, title)[0];

					clearMessages();
					addMessage(message_box);
				}
			},

			hostUpdate: (e) => {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				location.href = location.href;
			},

			hostDelete: (e) => {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				location.href = new Curl('hostinventories.php', false).getUrl();
			}
		}
	}
</script>
