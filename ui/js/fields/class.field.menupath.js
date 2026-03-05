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
		if (!this._allow_trim) {
			return super.getValue();
		}

		return this.#splitPath(super.getValueTrimmed()).join('/');
	}

	#splitPath(path) {
		let path_items = [];
		let path_item = '';
		let escaped = false;

		for (let i = 0; i < path.length; i++) {
			const char = path[i];

			if (escaped) {
				// Take character after '\' literally.
				path_item += '\\'+char;
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

		if (this._allow_trim) {
			path_items = path_items.map(item => item.trim());
		}

		if (path_items[0] === '' && path_items[1] !== '') {
			path_items = path_items.slice(1);
		}

		if (path_items[path_items.length - 1] === '' && path_items[path_items.length - 2] !== '') {
			path_items = path_items.slice(0, -1);
		}

		return path_items;
	}
}
