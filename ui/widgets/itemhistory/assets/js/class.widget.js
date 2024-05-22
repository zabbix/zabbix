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

	#binary_data_cache = new Map();

	#abort_controller = null;

	#thumbnail_loader = null;

	#show_thumbnail = false;

	#selected_itemid = null;
	#selected_clock = null;

	setContents(response) {
		super.setContents(response);

		if (this.#abort_controller !== null) {
			this.#abort_controller.abort();
			this.#abort_controller = null;
		}

		const table = this._body.querySelector(`.${ZBX_STYLE_LIST_TABLE}`);

		if (table !== null) {
			this.#show_thumbnail = table.classList.contains('show-thumbnail');

			this.#loadThumbnails(this.#makeUrls());

			table.querySelectorAll('.has-broadcast-data').forEach(element => {
				const data = element.dataset;

				if (data?.itemid === this.#selected_itemid && data?.clock === this.#selected_clock) {
					element.classList.add('selected');
				}
			});

			table.addEventListener('click', (e) => {
				const element = e.target.closest('.has-broadcast-data');

				if (element !== null) {
					table.querySelectorAll('.has-broadcast-data.selected').forEach(selected => {
						selected.classList.remove('selected');
					});

					element.classList.add('selected');
					this.#selected_itemid = element.dataset.itemid;
					this.#selected_clock = element.dataset.clock;

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

	#makeUrls() {
		const urls = [];

		for (const button of this._body.querySelectorAll('.js-show-binary')) {
			const cell = button.closest('td');
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'widget.itemhistory.binary_value.get');
			curl.setArgument('preview', 1);
			curl.setArgument('itemid', cell.dataset.itemid);

			const [clock, ns] = cell.dataset.clock.split('.');
			curl.setArgument('clock', clock);
			curl.setArgument('ns', ns);

			const url = curl.getUrl();

			this.#binary_data_cache.set(url, {
				...(this.#binary_data_cache.get(url) || {}),
				button, clock, ns, itemid: cell.dataset.itemid
			});

			urls.push(url);
		}

		return urls;
	}

	#loadThumbnails(urls) {
		const fetchValue = url => {
			const binary_data = this.#binary_data_cache.get(url);

			return new Promise((resolve, reject) => {
				if (binary_data.loaded) {
					resolve(binary_data);
				}
				else {
					binary_data.button.classList.add('is-loading', 'is-loading-fadein');

					fetch(url, {signal: this.#abort_controller.signal})
						.then(response => response.json())
						.then(response => {
							if ('error' in response) {
								throw {error: response.error};
							}

							resolve({...binary_data, ...response, loaded: true});
						})
						.catch(exception => {
							reject(exception);
						});
				}
			})
				.then(binary_data => {
					this.#binary_data_cache.set(url, binary_data);

					if ('thumbnail' in binary_data && this.#show_thumbnail) {
						this.#makeThumbnailButton(binary_data);
					}
					else {
						this.#makeBinaryButton(binary_data);
					}
				})
				.catch(exception => {
					console.log('Could not load thumbnail', exception);
				})
				.finally(() => {
					binary_data.button.classList.remove('is-loading', 'is-loading-fadein');
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

	#makeBinaryButton(binary_data) {
		const button = binary_data.button;
		button.classList.add(ZBX_STYLE_BTN_LINK);

		const curl = this.#getHintboxContentCUrl(binary_data);

		if ('thumbnail' in binary_data) {
			curl.setArgument('action', 'widget.itemhistory.image_value.get');
			this.#addHintbox(button, `<img src="${curl.getUrl()}">`);
		}
		else {
			if (!binary_data.has_more) {
				this.#addHintbox(button, binary_data.value);
			}
			else {
				curl.setArgument('action', 'widget.itemhistory.binary_value.get');
				this.#addHintbox(button, '', curl);
			}
		}
	}

	#makeThumbnailButton(binary_data) {
		const button = binary_data.button;
		button.style.setProperty('--thumbnail', `url(data:image/png;base64,${binary_data.thumbnail})`);

		const curl = this.#getHintboxContentCUrl(binary_data);
		curl.setArgument('action', 'widget.itemhistory.image_value.get');

		this.#addHintbox(button, `<img src="${curl.getUrl()}">`)
	}

	#getHintboxContentCUrl(binary_data) {
		const curl = new Curl('zabbix.php');

		curl.setArgument('itemid', binary_data.itemid);
		curl.setArgument('clock', binary_data.clock);
		curl.setArgument('ns', binary_data.ns);

		return curl;
	}

	#addHintbox(button, content = '', curl = null) {
		button.dataset.hintbox = '1';
		button.dataset.hintboxStatic = '1';
		button.dataset.hintboxClass = 'dashboard-widget-itemhistory-hintbox';
		button.dataset.hintboxContents = content;

		if (curl !== null) {
			button.dataset.hintboxPreload = JSON.stringify({action: curl.args.action, data: curl.args});
		}
	}
}
