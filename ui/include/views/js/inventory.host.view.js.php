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
		original_url: null,

		init() {
			this.current_url = new Curl('', false);
			// const url = this.current_url.getUrl();
			// history.pushState({}, '', url); // TODO VM: use this to restore url

			host_popup.init();
		},

		hostEdit({hostid}) {
			const overlay = PopUp('popup.host.edit', {hostid}, 'host_edit', document.activeElement);

			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostDelete);
		},

		events: {
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
