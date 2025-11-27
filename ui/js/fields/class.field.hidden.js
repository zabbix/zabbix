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


class CFieldHidden extends CField {

	init() {
		super.init();

		const observer = new MutationObserver((observations) => {
			observations.forEach((obs) => {
				if (obs.target.value !== obs.oldValue) {
					this.onBlur();
				}
			});
		});

		observer.observe(this._field, {
			attributes: true,
			attributeFilter: ['value'],
			attributeOldValue: true
		});
	}

	getName() {
		return this._field.getAttribute('name');
	}

	getValue() {
		if (this.isDisabled()) {
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

	isDisabled() {
		return this._field.disabled;
	}

	setErrors({message, level}) {
		if (super._error_container !== null) {
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
