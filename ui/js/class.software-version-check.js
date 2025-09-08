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


class CSoftwareVersionCheck {

	static URL = 'https://services.zabbix.com/updates/v1';
	static TYPE = 'software_update_check';
	static DELAY_ON_PAGE_LOAD = 5; // 5 seconds
	static DELAY_ON_ERROR = 60; // 1 minute

	#versions = [];
	#csrf_token;

	constructor() {
		this.#startUpdating(CSoftwareVersionCheck.DELAY_ON_PAGE_LOAD);
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

	#getSavedData() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'softwareversioncheck.get');

		fetch(curl.getUrl())
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
				}

				if (!response.is_software_update_check_enabled) {
					return;
				}

				if ('delay' in response) {
					this.#startUpdating(response.delay);
				}
				else {
					this.#versions = [];
					this.#csrf_token = response.csrf_token;

					this.#getCurrentData(response.version, response.check_hash);
				}
			})
			.catch(exception => {
				console.log('Could not get data', exception);

				this.#startUpdating(CSoftwareVersionCheck.DELAY_ON_ERROR);
			});
	}

	#getCurrentData(version, check_hash) {
		const curl = new Curl(CSoftwareVersionCheck.URL);

		curl.setArgument('type', CSoftwareVersionCheck.TYPE);
		curl.setArgument('version', version);
		curl.setArgument('software_update_check_hash', check_hash);

		fetch(curl.getUrl(), {cache: 'no-store'})
			.then(response => response.json())
			.then(response => {
				if ('versions' in response) {
					this.#versions = response.versions;
				}
			})
			.catch(exception => {
				console.log('Could not get data', exception);
			})
			.finally(() => {
				this.#update();
			});
	}

	#update() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'softwareversioncheck.update');

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({
				versions: this.#versions,
				[CSRF_TOKEN_NAME]: this.#csrf_token
			})
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					console.log('Could not update data', {error: response.error});
				}

				this.#startUpdating(response.delay || CSoftwareVersionCheck.DELAY_ON_ERROR);
			})
			.catch(exception => {
				console.log('Could not update data', exception);

				this.#startUpdating(CSoftwareVersionCheck.DELAY_ON_ERROR);
			});
	}
}
