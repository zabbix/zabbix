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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
				if (ev.target.value == TAG_OPERATOR_EXISTS || ev.target.value == TAG_OPERATOR_NOT_EXISTS) {
					this._value.addClass('display-none');
				}
				else {
					this._value.removeClass('display-none');
				}
			}
		}

		this._operation.on('change', this._events.changeOperation);
	}
}
