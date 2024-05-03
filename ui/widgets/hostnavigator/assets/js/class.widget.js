/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CWidgetHostNavigator extends CWidget {

	/**
	 * Host navigator instance.
	 *
	 * @type {CHostNavigator|null}
	 */
	#host_navigator = null;

	/**
	 * Listeners of host navigator widget.
	 *
	 * @type {Object}
	 */
	#listeners = {};

	/**
	 * Scroll amount of contents.
	 *
	 * @type {number}
	 */
	#contents_scroll_top = 0;

	onActivate() {
		this._contents.scrollTop = this.#contents_scroll_top;
	}

	onDeactivate() {
		this.#contents_scroll_top = this._contents.scrollTop;
	}

	onDestroy() {
		this.#updateProfiles(false, [], this._widgetid);
	}

	getUpdateRequestData() {
		return {
			...super.getUpdateRequestData(),
			with_config: this.#host_navigator === null ? 1 : undefined
		};
	}

	setContents(response) {
		if (response.hosts.length === 0) {
			this.clearContents();
			this.setCoverMessage({
				message: t('No data found'),
				icon: ZBX_ICON_SEARCH_LARGE
			});

			return;
		}

		if (this.#host_navigator === null) {
			this.#host_navigator = new CHostNavigator(response.config);

			this.#registerListeners();
			this.#activateListeners();
		}

		this._body.appendChild(this.#host_navigator.getContainer());

		this.#host_navigator.setValue({
			hosts: response.hosts,
			maintenances: response.maintenances,
			is_limit_exceeded: response.is_limit_exceeded
		});
	}

	#registerListeners() {
		this.#listeners = {
			hostSelect: e => {
				this.broadcast({
					[CWidgetsData.DATA_TYPE_HOST_ID]: [e.detail.hostid],
					[CWidgetsData.DATA_TYPE_HOST_IDS]: [e.detail.hostid]
				});
			},

			groupToggle: e => {
				if (this._widgetid) {
					this.#updateProfiles(e.detail.is_open, e.detail.group_identifier, this._widgetid);
				}
			}
		};
	}

	#activateListeners() {
		this.#host_navigator.getContainer().addEventListener(CHostNavigator.EVENT_HOST_SELECT,
			this.#listeners.hostSelect
		);
		this.#host_navigator.getContainer().addEventListener(CHostNavigator.EVENT_GROUP_TOGGLE,
			this.#listeners.groupToggle
		);
	}

	/**
	 * Update expanded and collapsed group state in user profile.
	 *
	 * @param {boolean} is_open          Indicator whether the group is open or closed.
	 * @param {array}   group_identifier Group path identifier.
	 * @param {string}  widgetid         Widget ID.
	 */
	#updateProfiles(is_open, group_identifier, widgetid) {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'widget.navigation.tree.toggle');

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({
				is_open: is_open,
				group_identifier: group_identifier,
				widgetid: widgetid
			})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return response;
			})
			.catch((exception) => {
				let title;
				let messages = [];

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					title = t('Unexpected server error.');
				}

				this._updateMessages(messages, title);
			});
	}

	hasPadding() {
		return false;
	}
}
