/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CFieldHidden extends CField {

	init() {
		super.init();

		const observer = new MutationObserver(() => this.onBlur());

		observer.observe(this._field, {
			attributes: true,
			attributeFilter: ['value']
		});
	}

	getName() {
		return this._field.getAttribute('name');
	}

	getValue() {
		if (this._field.disabled) {
			return null;
		}

		return this._field.value;
	}

	getValueTrimmed() {
		const value = this.getValue();

		if (value === null || !this._allow_trim) {
			return value;
		}

		return value.trim();
	}

	setTabId(tab_id) {
		this._tab_id = null;
	}

	setErrors({message, level}) {
		if (this._error_container !== null) {
			super.setErrors({message, level});
		}
		else if (message !== '') {
			this.setGlobalError(message);
		}
		else {
			this._global_errors = {};
		}
	}
}
