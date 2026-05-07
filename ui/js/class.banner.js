/*
** Copyright (C) 2001-2026 Zabbix SIA
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


const ZBX_STYLE_BANNER = 'banner';
const ZBX_STYLE_BANNER_CONTENT = 'banner-content';
const ZBX_STYLE_BANNER_CLOSE = 'banner-close';

class CBanner {

	static URL = 'https://services.zabbix.com/banners/v1';
	static DELAY_ON_PAGE_LOAD = 1; // 1 second
	static DELAY_ON_ERROR = 60; // 1 minute
	static NUMBER_OF_ATTEMPTS = 1;
	static CONTENT_LANG_ALL = 'all';
	static RESPONSE_DEFAULTS = {
		allow_banners: true,
		language: CBanner.CONTENT_LANG_ALL,
		storage_idx: null,
		dismissed_banner_ids: [],
		banners: []
	};

	#language = 'en_US';
	#storage_idx = null;
	#csrf_token = null;
	#container = null;
	#content = null;

	#active_banner_id = null;
	#dismissed_banner_ids = [];

	#abort_controller = null;
	#number_of_attempts = 0;
	#banners = [];

	#template = new Template(`
		<div class="${ZBX_STYLE_BANNER}">
			<div class="${ZBX_STYLE_BANNER_CONTENT}"></div>
			<button type="button" role="button" class="${ZBX_STYLE_BANNER_CLOSE} ${ZBX_ICON_TIMES} ${ZBX_STYLE_BTN_ICON}"></button>
		</div>
	`);

	constructor() {
		this.#startUpdating(CBanner.DELAY_ON_PAGE_LOAD);
	}

	#getSavedData() {
		const url = new URL('zabbix.php', location.href);
		url.searchParams.set('action', 'banner.get');

		const abort_controller = new AbortController();

		this.#abort_controller?.abort();
		this.#abort_controller = abort_controller;

		fetch(url.toString(), {
			signal: this.#abort_controller.signal
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw new Error(response.error);
				}

				response = Object.assign({}, CBanner.RESPONSE_DEFAULTS, response);

				if (!response.allow_banners) {
					return;
				}

				this.#language = response.language;
				this.#storage_idx = response.storage_idx;
				this.#dismissed_banner_ids = new Set(response.dismissed_banner_ids || []);

				if ('delay' in response && response.delay >= 0) {
					this.#banners = response.banners || [];

					this.#displayActiveBanner();

					this.#startUpdating(response.delay);
				}
				else if ('csrf_token' in response) {
					this.#csrf_token = response.csrf_token;

					this.#getCurrentData();
				}
			})
			.catch(error => console.log('Could not get saved data.', error))
			.finally(() => {
				if (this.#abort_controller === abort_controller) {
					this.#abort_controller = null;
				}
			});
	}

	#getCurrentData() {
		const abort_controller = new AbortController();

		this.#abort_controller?.abort();
		this.#abort_controller = abort_controller;

		fetch(CBanner.URL, {
			headers: {
				'Accept-Language': this.#language.replace('_', '-')
			},
			signal: this.#abort_controller.signal
		})
			.then(response => response.json())
			.then(response => {
				if (!('banners' in response)) {
					throw new Error('Invalid response format.');
				}

				this.#number_of_attempts = 0;

				this.#updateData(response.banners);
			})
			.catch(error => {
				console.log('Could not get current banner data.', error);

				if (this.#number_of_attempts === CBanner.NUMBER_OF_ATTEMPTS) {
					return;
				}

				this.#number_of_attempts++;

				setTimeout(() => this.#getCurrentData(), CBanner.DELAY_ON_ERROR * 1000);
			})
			.finally(() => {
				if (this.#abort_controller === abort_controller) {
					this.#abort_controller = null;
				}
			});
	}

	#updateBanner(content) {
		if (!this.#content) {
			return;
		}

		this.#content.innerHTML = content;
		this.#content.querySelectorAll('a[href]').forEach(link => link.setAttribute('target', '_blank'));
	}

	#createBanner() {
		if (this.#content) {
			return;
		}

		this.#container = this.#template.evaluateToElement();
		this.#content = this.#container.querySelector(`.${ZBX_STYLE_BANNER_CONTENT}`);

		const close_button = this.#container.querySelector(`.${ZBX_STYLE_BANNER_CLOSE}`);
		close_button.addEventListener('click', () => this.#closeBanner());

		const wrapper = document.querySelector(`.${ZBX_STYLE_LAYOUT_WRAPPER}`);
		wrapper.prepend(this.#container);
	}

	#closeBanner() {
		this.#dismissed_banner_ids.add(this.#active_banner_id);

		this.#displayActiveBanner();

		this.#updateUserProfile([...this.#dismissed_banner_ids]);
	}

	#displayActiveBanner() {
		const now = new Date();
		const banner_ids = [];

		const active_banner = this.#banners
			.filter(banner => {
				banner_ids.push(banner.id);

				const from = new Date(banner.from);
				const to = new Date(banner.to);

				return !this.#dismissed_banner_ids.has(banner.id)
					&& [this.#language, CBanner.CONTENT_LANG_ALL].some(key => key in banner.content)
					&& from <= now
					&& now <= to;
			})
			.sort((a, b) => a.id - b.id)
			.at(0);

		if (!active_banner || !active_banner.content) {
			this.#container?.remove();
			this.#container = null;
			this.#content = null;
			this.#active_banner_id = null;

			return;
		}

		let dismissed_banner_ids = [...this.#dismissed_banner_ids];

		// Check if there are banner IDs, which were previously dismissed and are no longer in the response.
		if (dismissed_banner_ids.some(id => banner_ids.indexOf(id) < 0)) {
			dismissed_banner_ids = dismissed_banner_ids.filter(banner_id => banner_ids.indexOf(banner_id) >= 0);

			this.#updateUserProfile(dismissed_banner_ids);
		}

		const content = active_banner.content[this.#language] || active_banner.content[CBanner.CONTENT_LANG_ALL];
		if (!content) {
			return;
		}

		this.#active_banner_id = active_banner.id;

		this.#createBanner();
		this.#updateBanner(content);
	}

	#updateUserProfile(dismissed_banner_ids) {
		if (!this.#storage_idx) {
			return;
		}

		/* global updateUserProfile */
		updateUserProfile(this.#storage_idx, JSON.stringify(dismissed_banner_ids), [], PROFILE_TYPE_STR);
	}

	#startUpdating(delay) {
		const update_interval = 60;

		if (delay > update_interval) {
			const next_check_time = Math.round(Date.now() / 1000) + delay;
			let remaining_delay = delay;
			const interval_id = setInterval(() => {
				if (Math.round(Date.now() / 1000) >= next_check_time) {
					clearInterval(interval_id);
					this.#getSavedData();
				}
				else {
					remaining_delay -= update_interval;

					if (remaining_delay < update_interval) {
						clearInterval(interval_id);
						setTimeout(() => this.#getSavedData(), remaining_delay * 1000);
					}
				}
			}, update_interval * 1000);
		}
		else {
			setTimeout(() => this.#getSavedData(), delay * 1000);
		}
	}

	#updateData(banners) {
		const now = new Date();

		const url = new URL('zabbix.php', location.href);
		url.searchParams.set('action', 'banner.update');

		banners = banners.filter(banner => {
			const to = new Date(banner.to);

			return 'id' in banner && 'content' in banner && Object.keys(banner.content).length > 0 && now <= to;
		});

		const abort_controller = new AbortController();

		this.#abort_controller?.abort();
		this.#abort_controller = abort_controller;

		fetch(url.toString(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({
				number_of_attempts: this.#number_of_attempts,
				banners,
				[CSRF_TOKEN_NAME]: this.#csrf_token
			}),
			signal: this.#abort_controller.signal
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw new Error(response.error);
				}

				this.#banners = response.banners || [];

				if ('delay' in response) {
					this.#startUpdating(response.delay);
				}

				this.#displayActiveBanner();
			})
			.catch(error => console.log('Could not update banner data.', error))
			.finally(() => {
				if (this.#abort_controller === abort_controller) {
					this.#abort_controller = null;
				}
			});
	}
}
