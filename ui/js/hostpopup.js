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


const ZBX_STYLE_ZABBIX_HOST_POPUPEDIT = 'js-edit-host';
const ZBX_STYLE_ZABBIX_HOST_POPUPCREATE = 'js-create-host';

const host_popup = {
	/**
	 * General entry point to be called on pages that need host popup functionality.
	 */
	init() {
		this.initActionButtons();

		this.original_url = location.href;
	},

	/**
	 * Sets up listeners for elements marked to start host edit/create popup.
	 */
	initActionButtons() {
		document.addEventListener('click', (e) => {
			const node = e.target;

			if (node.classList.contains(ZBX_STYLE_ZABBIX_HOST_POPUPCREATE)) {
				const host_data = (node.dataset.hostgroups !== undefined)
					? { groupids: JSON.parse(node.dataset.hostgroups) }
					: {},
					url = new Curl('zabbix.php', false);

				this.edit(host_data);
				url.setArgument('action', 'host.create');
				history.pushState({}, '', url.getUrl());
			}
			else if (node.classList.contains(ZBX_STYLE_ZABBIX_HOST_POPUPEDIT)) {
				let hostid = null;

				if (node.hostid !== undefined && node.dataset.hostid !== undefined) {
					hostid = node.dataset.hostid;
				}
				else {
					hostid = new Curl(node.href).getArgument('hostid')
				}

				e.preventDefault();
				this.edit({hostid});
				history.pushState({}, '', node.getAttribute('href'));
			}
		}, {capture: true});
	},

	/**
	 * Sets up and opens host edit popup.
	 *
	 * @param {object} host_data                 Host data used to initalize host form.
	 * @param {object} host_data{hostid}         ID of host to edit.
	 * @param {object} host_data{groupids}       Host groups to pre-fill when creating new host.
	 */
	edit(host_data = {}) {
		this.pauseRefresh();

		const overlay = PopUp('popup.host.edit', host_data, 'host_edit', document.activeElement);

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			postMessageOk(e.detail.title);

			if (e.detail.messages !== null) {
				postMessageDetails('success', e.detail.messages);
			}

			// TODO: reload || refresh;
		});

		overlay.$dialogue[0].addEventListener('overlay.close', () => {
			history.replaceState({}, '', this.original_url);
			this.resumeRefresh();
		}, {once: true});
	},

	pauseRefresh() {},

	resumeRefresh() {}
};
