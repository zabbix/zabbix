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
	#selected_key_ = null;

	setContents(response) {
		super.setContents(response);

		if (this.#abort_controller !== null) {
			this.#abort_controller.abort();
			this.#abort_controller = null;
		}

		this.#values_table = this._body.querySelector(`.${ZBX_STYLE_LIST_TABLE}`);

		if (this.#values_table === null) {
			return;
		}

		const items_data = new Map();
		this.#values_table.querySelectorAll('.has-broadcast-data').forEach(element => {
			const itemid = element.dataset.itemid;
			const clock = element.dataset.clock;
			const key_ = element.dataset.key_;

			if (!items_data.has(itemid)) {
				items_data.set(itemid, { itemid: itemid, clock: clock, key_: key_ });
			}
		});

		this.#loadThumbnails(this.#makeUrls());

		this.#values_table.addEventListener('click', e => {
			const element = e.target.closest('.has-broadcast-data');

			if (element !== null) {
				this.#selected_clock = element.dataset.clock;
				this.#selected_itemid = element.dataset.itemid;
				this.#selected_key_ = element.dataset.key_;

				this.#broadcast();
				this.#markSelected();
			}
		});

		if (!this.hasEverUpdated() && this.isReferred()) {
			const element = this.#getDefaultSelectable();

			if (element !== null) {
				this.#selected_clock = element.dataset.clock;
				this.#selected_itemid = element.dataset.itemid;
				this.#selected_key_ = element.dataset.key_;

				this.#broadcast();
				this.#markSelected();
			}
		}
		else if (this.#selected_itemid !== null) {
			if (!items_data.has(this.#selected_itemid)) {
				for (let [itemid, item] of items_data) {
					if (item.key_ === this.#selected_key_) {
						this.#selected_itemid = itemid;
						this.#selected_clock = item.clock;

						this.#broadcast();
						break;
					}
				}
			}

			this.#markSelected();
		}
	}

	#getDefaultSelectable() {
		return this.#values_table.querySelector('.has-broadcast-data');
	}

	onReferredUpdate() {
		if (this.#values_table === null || this.#selected_itemid !== null) {
			return;
		}

		const element = this.#getDefaultSelectable();

		if (element !== null) {
			this.#selected_clock = element.dataset.clock;
			this.#selected_itemid = element.dataset.itemid;

			this.#broadcast();
			this.#markSelected();
		}
	}

	promiseReady() {
		return Promise.all([super.promiseReady(), this.#thumbnail_loader].filter(promise => promise));
	}

	getUpdateRequestData() {
		return {
			...super.getUpdateRequestData(),
			has_custom_time_period: this.getFieldsReferredData().has('time_period') ? undefined : 1
		}
	}

	#broadcast() {
		this.broadcast({
			[CWidgetsData.DATA_TYPE_ITEM_ID]: [this.#selected_itemid],
			[CWidgetsData.DATA_TYPE_ITEM_IDS]: [this.#selected_itemid]
		});
	}

	#markSelected() {
		this.#values_table.querySelectorAll('.has-broadcast-data').forEach(element => {
			const data = element.dataset;

			element.classList.toggle('selected', data?.itemid === this.#selected_itemid
				&& data?.clock === this.#selected_clock
			);
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

			this.#binary_buttons.set(button, {url, clock, ns,
				itemid: cell.dataset.itemid,
				alt: cell.dataset.alt
			});

			urls.push(url);
		}

		return [...new Set(urls)];
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

			switch (binary_data.type) {
				case CWidgetItemHistory.VALUE_TYPE_IMAGE:
					const img = document.createElement('img');
					img.alt = button.dataset.alt;

					this.#addHintbox(button, img);
					break;

				case CWidgetItemHistory.VALUE_TYPE_RAW:
					const curl = this.#getHintboxContentCUrl(button);
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

			const img = document.createElement('img');
			img.alt = button.dataset.alt;
			img.src = '#';

			this.#addHintbox(button, img);
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

	#addHintbox(button, content, curl = null) {
		button.dataset.hintbox = '1';
		button.dataset.hintboxStatic = '1';
		button.dataset.hintboxClass = 'dashboard-widget-itemhistory-hintbox' + (content === '' ? ' nowrap' : '');

		if (content instanceof HTMLImageElement) {
			button.addEventListener('onShowHint.hintBox', e => {
				const hint_box = e.target.hintBoxItem[0];
				const container = hint_box.querySelector('.dashboard-widget-itemhistory-hintbox');

				hint_box.classList.add('dashboard-widget-itemhistory-hintbox-image');

				const curl = this.#getHintboxContentCUrl(button);

				curl.setArgument('action', 'widget.itemhistory.image_value.get');
				content.src = curl.getUrl();
				container.innerHTML = '';
				container.append(content);

				e.target.resize_observer = new ResizeObserver((entries) => {
					entries.forEach(entry => {
						if (entry.contentBoxSize) {
							const overlay = content.closest('.dashboard-widget-itemhistory-hintbox-image');
							const size = entry.contentBoxSize[0];

							overlay.style.width = `${size.inlineSize}px`;
							overlay.style.height = `${size.blockSize}px`;
						}
					})
				});
				e.target.resize_observer.observe(content);

				if (!content.complete) {
					hint_box.classList.add('is-loading');

					content.addEventListener('load', () => {
						hint_box.classList.remove('is-loading');
					});

					content.addEventListener('error', () => {
						hint_box.classList.remove('is-loading');

						container.text = t('Image loading error.');
					});
				}
			});

			button.addEventListener('onHideHint.hintBox', e => {
				if (e.target.resize_observer !== undefined) {
					e.target.resize_observer.disconnect();

					delete e.target.resize_observer;
				}
			})
		}
		else {
			if (curl !== null) {
				button.dataset.hintboxContents = '';
				button.dataset.hintboxPreload = JSON.stringify({action: curl.args.action, data: curl.args});
			}
			else {
				button.dataset.hintboxContents = content || t('Empty value.');
			}
		}
	}
}
