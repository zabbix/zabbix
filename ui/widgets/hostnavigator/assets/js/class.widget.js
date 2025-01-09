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

	/**
	 * ID of selected host.
	 *
	 * @type {string|null}
	 */
	#selected_hostid = null;

	/**
	 * CSRF token for navigation.tree.toggle action.
	 *
	 * @type {string|null}
	 */
	#csrf_token = null;

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

		this.#csrf_token = response[CSRF_TOKEN_NAME];

		if (this.#host_navigator === null) {
			this.clearContents();

			this.#host_navigator = new CHostNavigator(response.config);
			this._body.appendChild(this.#host_navigator.getContainer());

			this.#registerListeners();
			this.#activateListeners();
		}

		this.#host_navigator.setValue({
			hosts: response.hosts,
			maintenances: response.maintenances,
			is_limit_exceeded: response.is_limit_exceeded,
			selected_hostid: this.#selected_hostid
		});

		if (!this.hasEverUpdated() && this.isReferred()) {
			this.#selected_hostid = this.#getDefaultSelectable();

			if (this.#selected_hostid !== null) {
				this.#host_navigator.selectItem(this.#selected_hostid);
			}
		}
	}

	#broadcast() {
		this.broadcast({
			[CWidgetsData.DATA_TYPE_HOST_ID]: [this.#selected_hostid],
			[CWidgetsData.DATA_TYPE_HOST_IDS]: [this.#selected_hostid]
		});
	}

	#getDefaultSelectable() {
		const selected_element = this._body.querySelector(`.${CNavigationTree.ZBX_STYLE_NODE_IS_ITEM}`);

		return selected_element !== null ? selected_element.dataset.id : null;
	}

	onReferredUpdate() {
		if (this.#host_navigator === null || this.#selected_hostid !== null) {
			return;
		}

		this.#selected_hostid = this.#getDefaultSelectable();

		if (this.#selected_hostid !== null) {
			this.#host_navigator.selectItem(this.#selected_hostid);
		}
	}

	#registerListeners() {
		this.#listeners = {
			hostSelect: ({detail}) => {
				this.#selected_hostid = detail.hostid;

				this.#broadcast();
			},

			groupToggle: ({detail}) => {
				if (this._widgetid) {
					this.#updateProfiles(detail.is_open, detail.group_identifier, this._widgetid);
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
			body: JSON.stringify({is_open, group_identifier, widgetid, [CSRF_TOKEN_NAME]: this.#csrf_token})
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

	onClearContents() {
		if (this.#host_navigator !== null) {
			this.#host_navigator.destroy();
			this.#host_navigator = null;
		}
	}
}
