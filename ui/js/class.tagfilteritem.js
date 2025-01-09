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


const TAG_OPERATOR_EXISTS = '4';
const TAG_OPERATOR_NOT_EXISTS = '5';

class CTagFilterItem extends CBaseComponent {

	constructor(target) {
		super(target);

		this._operation = new CBaseComponent(this._target.querySelector('z-select'));
		this._value = new CBaseComponent(this._target.querySelector('[name*="value"]'));

		this.registerEvents();
		this.init();
	}

	init() {
		this._operation.fire('change');
	}

	/**
	 * Register events.
	 */
	registerEvents() {
		this._events = {
			/**
			 * Event called when operation field changes.
			 */
			changeOperation: (ev) => {
				const show_value = ev.target.value != TAG_OPERATOR_EXISTS && ev.target.value != TAG_OPERATOR_NOT_EXISTS;

				if (this._value.hasClass('display-none') != show_value) {
					return;
				}

				if (show_value) {
					this._value.removeClass('display-none');
					this._value._target.closest('td').style.paddingRight = null;

					this._value._target.value = this._value._target.dataset.text;
					this._value._target.dataset.text = '';
				}
				else {
					this._value.addClass('display-none');
					this._value._target.closest('td').style.paddingRight = 0;

					this._value._target.dataset.text = this._value._target.value;
					this._value._target.value = '';
				}
			}
		}

		this._operation.on('change', this._events.changeOperation);
	}
}
