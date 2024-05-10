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

				if (!response.is_software_update_check_enabled) {
					return;
				}

				if (response.nextcheck <= response.now) {
					this.#data.major_version = response.major_version;
					this.#data.check_hash = response.check_hash;
					this.#data._csrf_token = response._csrf_token;

					this.#getCurrentData();
				}
				else {
					const delay = response.nextcheck - response.now + Math.floor(Math.random() * 60) + 1;

					setTimeout(() => this.#getSavedData(), delay * 1000);
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

		fetch(curl.getUrl())
			.then(response => response.json())
			.then(response => {
				if ('versions' in response) {
					this.#data.versions = response.versions;
				}
			})
			.finally(() => {
				this.#update();
			});
	}

	#update() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'softwareversioncheck.update');

		const data = {
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

				const delay = response.nextcheck + Math.floor(Math.random() * 60) + 1;

				setTimeout(() => this.#getSavedData(), delay * 1000);
			})
			.catch(exception => {
				console.log('Could not update data', exception);
			});
	}
}
