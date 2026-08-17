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


class CFieldMenuPath extends CFieldTextBox {

	getValueTrimmed() {
		const value = this.getValue();

		if (value === null || !this._allow_trim) {
			return value;
		}

		return this.#normalizePath(value);
	}

	#normalizePath(path) {
		let path_items = [];
		let path_item = '';
		let escaped = false;

		for (let i = 0; i < path.length; i++) {
			const char = path[i];

			if (escaped) {
				// Take character after '\' literally.
				path_item += (char === '/' || char === '\\')
					? '\\' + char
					: char;
				escaped = false;
			}
			else if (char === '\\') {
				// Escape the next character.
				escaped = true;
			}
			else if (char === '/') {
				// Split by non-escaped "/".
				path_items.push(path_item);
				path_item = '';
			}
			else {
				path_item += char;
			}
		}

		path_items.push(path_item);
		path_items = path_items.map(item => item.trim());

		if (path_items[0] === '') {
			path_items.shift();
		}

		if (path_items.at(-1) === '') {
			path_items.pop();
		}

		return path_items.join('/');
	}
}
