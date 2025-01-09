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


class CWidgetTopHosts extends CWidget {

	static VALUE_TYPE_IMAGE = 'image';
	static VALUE_TYPE_RAW = 'raw';

	#binary_data_cache = new Map();
	#binary_buttons = new Map();

	#abort_controller = null;

	#thumbnail_loader = null;

	/**
	 * Table body of top hosts.
	 *
	 * @type {HTMLElement|null}
	 */
	#table_body = null;

	/**
	 * ID of selected host.
	 *
	 * @type {string|null}
	 */
	#selected_hostid = null;

	setContents(response) {
		super.setContents(response);

		if (this.#abort_controller !== null) {
			this.#abort_controller.abort();
			this.#abort_controller = null;
		}

		this.#table_body = this._body.querySelector(`.${ZBX_STYLE_LIST_TABLE}`);

		if (this.#table_body === null) {
			return;
		}

		this.#table_body.addEventListener('click', e => this.#onTableBodyClick(e));

		this.#loadThumbnails(this.#makeUrls());

		if (!this.hasEverUpdated() && this.isReferred()) {
			this.#selected_hostid = this.#getDefaultSelectable();

			if (this.#selected_hostid !== null) {
				this.#selectHost();
				this.#broadcastSelected();
			}
		}
		else if (this.#selected_hostid !== null) {
			this.#selectHost();
		}
	}

	onReferredUpdate() {
		if (this.#table_body === null || this.#selected_hostid !== null) {
			return;
		}

		this.#selected_hostid = this.#getDefaultSelectable();

		if (this.#selected_hostid !== null) {
			this.#selectHost();
			this.#broadcastSelected();
		}
	}


	promiseReady() {
		return Promise.all([super.promiseReady(), this.#thumbnail_loader].filter(promise => promise));
	}

	#getDefaultSelectable() {
		const row = this.#table_body.querySelector('[data-hostid]');

		return row !== null ? row.dataset.hostid : null;
	}

	#selectHost() {
		const rows = this.#table_body.querySelectorAll('[data-hostid]');

		for (const row of rows) {
			row.classList.toggle(ZBX_STYLE_ROW_SELECTED, row.dataset.hostid === this.#selected_hostid);
		}
	}

	#broadcastSelected() {
		this.broadcast({
			[CWidgetsData.DATA_TYPE_HOST_ID]: [this.#selected_hostid],
			[CWidgetsData.DATA_TYPE_HOST_IDS]: [this.#selected_hostid]
		});
	}

	#onTableBodyClick(e) {
		if (e.target.closest('a') !== null || e.target.closest('[data-hintbox="1"]') !== null) {
			return;
		}

		const row = e.target.closest('[data-hostid]');

		if (row !== null) {
			this.#selected_hostid = row.dataset.hostid

			this.#selectHost();
			this.#broadcastSelected();
		}
	}

	#makeUrls() {
		const urls = [];

		this.#binary_buttons.clear();

		for (const button of this.#table_body.querySelectorAll('.js-show-binary')) {
			const cell = button.closest('td');
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'widget.tophosts.value.check');
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
						case CWidgetTopHosts.VALUE_TYPE_IMAGE:
							if ('thumbnail' in binary_data) {
								this.#makeThumbnailButton(buttons, binary_data);
							}
							else {
								this.#makeBinaryButton(buttons, binary_data);
							}
							break;

						case CWidgetTopHosts.VALUE_TYPE_RAW:
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
				case CWidgetTopHosts.VALUE_TYPE_IMAGE:
					const img = document.createElement('img');
					img.alt = button.dataset.alt;

					this.#addHintbox(button, img);
					break;

				case CWidgetTopHosts.VALUE_TYPE_RAW:
					const curl = this.#getHintboxContentCUrl(button);
					curl.setArgument('action', 'widget.tophosts.binary_value.get');

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
		button.dataset.hintboxClass = 'dashboard-widget-tophosts-hintbox' + (content === '' ? ' nowrap' : '');

		if (content instanceof HTMLImageElement) {
			button.addEventListener('onShowHint.hintBox', e => {
				const hint_box = e.target.hintBoxItem[0];
				const container = hint_box.querySelector('.dashboard-widget-tophosts-hintbox');

				hint_box.classList.add('dashboard-widget-tophosts-hintbox-image');

				const curl = this.#getHintboxContentCUrl(button);

				curl.setArgument('action', 'widget.tophosts.image_value.get');
				content.src = curl.getUrl();
				container.innerHTML = '';
				container.append(content);

				e.target.resize_observer = new ResizeObserver((entries) => {
					entries.forEach(entry => {
						if (entry.contentBoxSize) {
							const overlay = content.closest('.dashboard-widget-tophosts-hintbox-image');

							if (overlay instanceof Element) {
								const size = entry.contentBoxSize[0];

								overlay.style.width = `${size.inlineSize}px`;
								overlay.style.height = `${size.blockSize}px`;
							}
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
