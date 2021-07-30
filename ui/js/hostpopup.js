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

const host_popup = {
	init() {
		this.initActionButtons();
	},

	initActionButtons() {
		document.addEventListener('click', event => {
			if (event.target.classList.contains('js-create-host')) {
				const options = (event.target.dataset.hostgroups !== undefined)
					? {groupids: JSON.parse(event.target.dataset.hostgroups)}
					: {};

				this.edit(options, {'backurl': window.location.href});

				const url = new Curl('zabbix.php', false);
				url.setArgument('action', 'host.create');
				history.pushState({}, '', url.getUrl());
			}
			else if (event.target.classList.contains('js-edit-host')) {
				let hostid = null;

				if (event.target.hostid !== undefined && event.target.dataset.hostid !== undefined) {
					hostid = event.target.dataset.hostid;
				}
				else {
					hostid = new Curl(event.target.href).getArgument('hostid')
				}

				this.edit({hostid:  hostid}, {'backurl': window.location.href});

				history.pushState({}, '', event.target.getAttribute('href'));

				event.preventDefault();
			}
		}, {capture: true});
	},

	edit(host_data = {}, options) {
		this.pauseRefresh();

		const overlay = PopUp('popup.host.edit', host_data, 'host_edit', document.activeElement);

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			postMessageOk(e.detail.title);

			if (e.detail.messages !== null) {
				postMessageDetails('success', e.detail.messages);
			}

			// reload || refresh;
		});

		overlay.$dialogue[0].addEventListener('overlay.close', () => {
			history.pushState({}, '', options.backurl);
			this.resumeRefresh()
		}, {once: true});
	},

	pauseRefresh() {},

	resumeRefresh() {}

};
