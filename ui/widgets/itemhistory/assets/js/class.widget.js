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


class CWidgetItemHistory extends CWidget {

	static VALUE_TYPE_IMAGE = 'image';
	static VALUE_TYPE_RAW = 'raw';

	#binary_data_cache = new Map();
	#binary_buttons = new Map();

	#abort_controller = null;

	#thumbnail_loader = null;

	#values_table;

	#selected_itemid = null;
	#selected_clock = null;

	setContents(response) {
		super.setContents(response);

		if (this.#abort_controller !== null) {
			this.#abort_controller.abort();
			this.#abort_controller = null;
		}

		this.#values_table = this._body.querySelector(`.${ZBX_STYLE_LIST_TABLE}`);

		if (this.#values_table !== null) {
			this.#loadThumbnails(this.#makeUrls());

			this.#markSelected(this.#selected_itemid, this.#selected_clock);

			this.#values_table.addEventListener('click', (e) => {
				const element = e.target.closest('.has-broadcast-data');

				if (element !== null) {
					this.#selected_itemid = element.dataset.itemid;
					this.#selected_clock = element.dataset.clock;

					this.#markSelected(this.#selected_itemid, this.#selected_clock);

					this.broadcast({
						[CWidgetsData.DATA_TYPE_ITEM_ID]: [element.dataset.itemid],
						[CWidgetsData.DATA_TYPE_ITEM_IDS]: [element.dataset.itemid]
					});
				}
			});
		}
	}

	promiseReady() {
		return Promise.all([super.promiseReady(), this.#thumbnail_loader].filter(promise => promise));
	}

	#markSelected(itemid, clock) {
		this.#values_table.querySelectorAll('.has-broadcast-data').forEach(element => {
			const data = element.dataset;
			element.classList.toggle('selected', data?.itemid === itemid && data?.clock === clock);
		});
	}

	#makeUrls() {
		const urls = [];

		this.#binary_buttons = new Map();

		for (const button of this.#values_table.querySelectorAll('.js-show-binary')) {
			const cell = button.closest('td');
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'widget.itemhistory.value.check');
			curl.setArgument('itemid', cell.dataset.itemid);

			if (button.classList.contains('btn-thumbnail')) {
				curl.setArgument('show_thumbnail', 1);
			}

			const [clock, ns] = cell.dataset.clock.split('.');
			curl.setArgument('clock', clock);
			curl.setArgument('ns', ns);

			const url = curl.getUrl();

			this.#binary_buttons.set(button, {url, itemid: cell.dataset.itemid, clock, ns});

			urls.push(url);
		}

		return urls;
	}

	#loadThumbnails(urls) {
		const fetchValue = url => {
			const binary_data = this.#binary_data_cache.get(url);
			const buttons = [...this.#binary_buttons]
				.filter(([_, value]) => value.url === url)
				.map(([button]) => button);

			return new Promise((resolve, reject) => {
				if (binary_data !== undefined) {
					resolve(binary_data);
				}
				else {
					buttons.forEach(button => button.classList.add('is-loading', 'is-loading-fadein'));

					fetch(url, {signal: this.#abort_controller.signal})
						.then(response => response.json())
						.then(response => {
							if ('error' in response) {
								throw {error: response.error};
							}

							resolve(response);
						})
						.catch(exception => {
							reject(exception);
						});
				}
			})
				.then(binary_data => {
					this.#binary_data_cache.set(url, binary_data);

					switch (binary_data.type) {
						case CWidgetItemHistory.VALUE_TYPE_IMAGE:
							if ('thumbnail' in binary_data) {
								this.#makeThumbnailButton(buttons, binary_data);
							}
							else {
								this.#makeBinaryButton(buttons, binary_data);
							}
							break;

						case CWidgetItemHistory.VALUE_TYPE_RAW:
							this.#makeBinaryButton(buttons, binary_data);
							break;

						default:
							buttons.forEach(button => button.disabled = true);
					}
				})
				.catch(exception => {
					console.log('Could not load thumbnail', exception);
				})
				.finally(() => {
					buttons.forEach(button => button.classList.remove('is-loading', 'is-loading-fadein'));
				});
		}

		this.#abort_controller = new AbortController();

		for (const url of this.#binary_data_cache.keys()) {
			if (!urls.includes(url)) {
				this.#binary_data_cache.delete(url);
			}
		}

		this.#thumbnail_loader = Promise.all(urls.map(url => fetchValue(url)));
	}

	#makeBinaryButton(buttons, binary_data) {
		for (const button of buttons) {
			button.classList.add(ZBX_STYLE_BTN_LINK);

			const curl = this.#getHintboxContentCUrl(button);

			switch (binary_data.type) {
				case CWidgetItemHistory.VALUE_TYPE_IMAGE:
					curl.setArgument('action', 'widget.itemhistory.image_value.get');
					this.#addHintbox(button, `<img src="${curl.getUrl()}">`);
					break;

				case CWidgetItemHistory.VALUE_TYPE_RAW:
					curl.setArgument('action', 'widget.itemhistory.binary_value.get');
					this.#addHintbox(button, '', curl);
					break;

				default:
					binary_data.button.disabled = true;
			}
		}
	}

	#makeThumbnailButton(buttons, binary_data) {
		for (const button of buttons) {
			button.style.setProperty('--thumbnail', `url(data:image/png;base64,${binary_data.thumbnail})`);

			const curl = this.#getHintboxContentCUrl(button);
			curl.setArgument('action', 'widget.itemhistory.image_value.get');

			this.#addHintbox(button, `<img src="${curl.getUrl()}">`);
		}
	}

	#getHintboxContentCUrl(button) {
		const curl = new Curl('zabbix.php');
		const value = this.#binary_buttons.get(button);

		curl.setArgument('itemid', value.itemid);
		curl.setArgument('clock', value.clock);
		curl.setArgument('ns', value.ns);

		return curl;
	}

	#addHintbox(button, content = '', curl = null) {
		button.dataset.hintbox = '1';
		button.dataset.hintboxStatic = '1';
		button.dataset.hintboxClass = 'dashboard-widget-itemhistory-hintbox' + (content === '' ? ' nowrap' : '');
		button.dataset.hintboxContents = content || t('Empty value');

		if (curl !== null) {
			button.dataset.hintboxPreload = JSON.stringify({action: curl.args.action, data: curl.args});
		}
	}
}
