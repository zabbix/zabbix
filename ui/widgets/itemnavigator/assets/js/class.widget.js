/*
** Copyright (C) 2001-2024 Zabbix SIA
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


class CWidgetItemNavigator extends CWidget {

	/**
	 * Item navigator instance.
	 *
	 * @type {CItemNavigator|null}
	 */
	#item_navigator = null;

	/**
	 * Listeners of item navigator widget.
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
			with_config: this.#item_navigator === null ? 1 : undefined
		};
	}

	setContents(response) {
		if (response.items.length === 0) {
			this.clearContents();
			this.setCoverMessage({
				message: t('No data found'),
				icon: ZBX_ICON_SEARCH_LARGE
			});

			return;
		}

		if (this.#item_navigator === null) {
			this.clearContents();

			this.#item_navigator = new CItemNavigator(response.config);
			this._body.appendChild(this.#item_navigator.getContainer());

			this.#registerListeners();
			this.#activateListeners();
		}

		this.#item_navigator.setValue({
			items: response.items,
			hosts: response.hosts,
			is_limit_exceeded: response.is_limit_exceeded
		});
	}

	#registerListeners() {
		this.#listeners = {
			itemSelect: e => {
				this.broadcast({
					[CWidgetsData.DATA_TYPE_ITEM_ID]: [e.detail.itemid],
					[CWidgetsData.DATA_TYPE_ITEM_IDS]: [e.detail.itemid]
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
		this.#item_navigator.getContainer().addEventListener(CItemNavigator.EVENT_ITEM_SELECT,
			this.#listeners.itemSelect
		);
		this.#item_navigator.getContainer().addEventListener(CItemNavigator.EVENT_GROUP_TOGGLE,
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
			body: JSON.stringify({is_open, group_identifier, widgetid})
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
		if (this.#item_navigator !== null) {
			this.#item_navigator.destroy();
			this.#item_navigator = null;
		}
	}
}
