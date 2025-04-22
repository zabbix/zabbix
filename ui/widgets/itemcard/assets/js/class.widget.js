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


class CWidgetItemCard extends CWidget {

	#abort_controller = null;
	#binary_button = null;
	#binary_data_cache = new Map();
	#thumbnail_loader = null;

	setContents(response) {
		super.setContents(response);

		if (this.#abort_controller !== null) {
			this.#abort_controller.abort();
			this.#abort_controller = null;
		}

		const button = this._contents.querySelector('.js-show-binary');

		if (button !== null) {
			const cell = button.closest('div');
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'widget.itemcard.value.check');
			curl.setArgument('itemid', cell.dataset.itemid);

			const [clock, ns] = cell.dataset.clock.split('.');
			curl.setArgument('clock', clock);
			curl.setArgument('ns', ns);

			const url = curl.getUrl();

			this.#binary_button = {button, url, clock, ns,
				itemid: cell.dataset.itemid,
				alt: cell.dataset.alt
			};

			this.#loadThumbnail(url);
		}

		this.#adjustSections();
	}

	onResize() {
		this.#adjustSections();
	}

	promiseReady() {
		return Promise.all([super.promiseReady(), this.#thumbnail_loader].filter(promise => promise));
	}

	#adjustSections() {
		this.#adjustSectionTriggers();
		this.#adjustSectionTags();
	}

	#adjustSectionTriggers() {
		const section = this._contents.querySelector('.section-triggers');

		if (section === null) {
			return;
		}

		const button_more = section.querySelector(`.${ZBX_STYLE_LINK_ALT}`);

		if (button_more === null) {
			return;
		}

		const container = section.querySelector('.triggers');
		const elements = container.querySelectorAll('.trigger');

		this.#adjustButtonMore(container, elements, button_more);
	}

	#adjustSectionTags() {
		const section = this._contents.querySelector('.section-tags');

		if (section === null) {
			return;
		}

		const button_more = section.querySelector(`.${ZBX_ICON_MORE}`);

		if (button_more === null) {
			return;
		}

		const container = section.querySelector('.tags');
		const elements = container.querySelectorAll('.tag');

		this.#adjustButtonMore(container, elements, button_more);
	}

	#adjustButtonMore(container, elements, button_more) {
		button_more.style.display = 'none';
		container.classList.remove('has-ellipsis');

		for (const element of elements) {
			element.style.display = '';
		}

		if (container.offsetHeight === container.scrollHeight) {
			return;
		}

		container.classList.add('has-ellipsis');

		if (container.offsetHeight === container.scrollHeight) {
			return;
		}

		button_more.style.display = '';

		for (const element of [...elements].reverse()) {
			if (button_more.getBoundingClientRect().bottom > container.getBoundingClientRect().bottom) {
				element.style.display = 'none';
			}
		}
	}

	#loadThumbnail(url) {
		const fetchValue = url => {
			const binary_data = this.#binary_data_cache.get(url);
			const button = url === this.#binary_button.url ? this.#binary_button.button : null;

			return new Promise((resolve, reject) => {
				if (binary_data !== undefined) {
					resolve(binary_data);
				}
				else {
					if (button !== null) {
						button.classList.add('is-loading', 'is-loading-fadein');
					}

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

					if (button !== null) {
						switch (binary_data.type) {
							case CWidgetItemHistory.VALUE_TYPE_IMAGE:
								if ('thumbnail' in binary_data) {
									this.#makeThumbnailButton(button, binary_data);
								} else {
									this.#makeBinaryButton(button, binary_data);
								}
								break;

							case CWidgetItemHistory.VALUE_TYPE_RAW:
								this.#makeBinaryButton(button, binary_data);
								break;

							default:
								button.disabled = true
						}
					}
				})
				.catch(exception => {
					console.log('Could not load thumbnail', exception);
				})
				.finally(() => {
					if (button !== null) {
						button.classList.remove('is-loading', 'is-loading-fadein');
					}
				});
		}

		this.#abort_controller = new AbortController();

		for (const cached_url of this.#binary_data_cache.keys()) {
			if (cached_url !== url) {
				this.#binary_data_cache.delete(cached_url);
			}
		}

		this.#thumbnail_loader = fetchValue(url);
	}

	#makeBinaryButton(button, binary_data) {
		button.classList.add(ZBX_STYLE_BTN_LINK);

		switch (binary_data.type) {
			case CWidgetItemHistory.VALUE_TYPE_IMAGE:
				const img = document.createElement('img');
				img.alt = button.dataset.alt;

				this.#addHintbox(button, img);
				break;

			case CWidgetItemHistory.VALUE_TYPE_RAW:
				const curl = this.#getHintboxContentCUrl();
				curl.setArgument('action', 'widget.itemcard.binary_value.get');

				this.#addHintbox(button, '', curl);
				break;

			default:
				button.disabled = true;
		}
	}

	#makeThumbnailButton(button, binary_data) {
		button.style.setProperty('--thumbnail', `url(data:image/png;base64,${binary_data.thumbnail})`);

		const img = document.createElement('img');
		img.alt = button.dataset.alt;
		img.src = '#';

		this.#addHintbox(button, img);
	}

	#getHintboxContentCUrl() {
		const curl = new Curl('zabbix.php');
		const value = this.#binary_button;

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

				curl.setArgument('action', 'widget.itemcard.image_value.get');
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
