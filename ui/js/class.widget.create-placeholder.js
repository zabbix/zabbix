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


class CWidgetCreatePlaceholder extends CWidget {

	#update_promise;
	#update_promise_reject;

	onStart() {
		for (const button of this._header.querySelectorAll('button')) {
			button.disabled = true;
		}

		this.#update_promise = new Promise((resolve, reject) => {
			this.#update_promise_reject = reject;
		});
	}

	onDestroy() {
		this.#update_promise_reject();
	}

	promiseUpdate() {
		return this.#update_promise;
	}
}
