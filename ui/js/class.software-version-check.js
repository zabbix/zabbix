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


class CSoftwareVersionCheck {

	static URL = 'https://services.zabbix.com/updates/v1';
	static TYPE = 'software_update_check';
	static DELAY = 28800; // 8 hours
	static DELAY_ON_FAIL = 259200; // 72 hours

	#data = {};

	constructor() {
		this.#getSavedData();
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

				this.#data = response;

				if (this.#data.is_software_update_check_enabled) {
					if (this.#data.nextcheck <= this.#data.now) {
						this.#getCurrentData();
					}
					else {
						const delay = this.#data.nextcheck - this.#data.now + Math.floor(Math.random() * 60) + 1;

						setTimeout(() => this.#getSavedData(), delay * 1000);
					}
				}
			})
			.catch(exception => {
				console.log('Could not get data', exception);
			});
	}

	#getCurrentData() {
		const curl = new Curl(CSoftwareVersionCheck.URL);

		curl.setArgument('type', CSoftwareVersionCheck.TYPE);
		curl.setArgument('version', this.#data.major_version);
		curl.setArgument('software_update_check_hash', this.#data.check_hash);

		fetch(curl.getUrl(), {mode: 'no-cors'})
			.then(response => response.json())
			.then(response => {
				this.#data.lastcheck_success = this.#data.now;
				this.#data.nextcheck = this.#data.now + CSoftwareVersionCheck.DELAY;

				if ('versions' in response) {
					this.#data.versions = response.versions;
				}
			})
			.catch(() => {
				this.#data.nextcheck = this.#data.now + CSoftwareVersionCheck.DELAY_ON_FAIL;
			})
			.finally(() => {
				this.#data.lastcheck = this.#data.now;

				this.#update();
			});
	}

	#update() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'softwareversioncheck.update');

		const data = {
			lastcheck: this.#data.lastcheck,
			lastcheck_success: this.#data.lastcheck_success,
			nextcheck: this.#data.nextcheck,
			versions: this.#data.versions || [],
			_csrf_token: this.#data._csrf_token
		};

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
				}

				const delay = data.nextcheck + Math.floor(Math.random() * 60) + 1;

				setTimeout(() => this.#getSavedData(), delay * 1000);
			})
			.catch(exception => {
				console.log('Could not update data', exception);
			});
	}
}

ZABBIX.SoftwareVersionCheck = new CSoftwareVersionCheck();
