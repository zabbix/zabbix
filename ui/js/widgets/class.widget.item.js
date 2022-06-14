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


class CWidgetItem extends CWidget {

	_registerEvents() {
		super._registerEvents();

		this._events.resize = () => {
			const margin = 5;
			const padding = 10;
			const header_height = this._view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER ? 0 : 33;

			this._target.style.setProperty(
				'--content-height',
				`${this._cell_height * this._pos.height - margin * 2 - padding * 2 - header_height}px`
			);
		}
	}

	_activateEvents() {
		super._activateEvents();

		this._resize_observer = new ResizeObserver(this._events.resize);
		this._resize_observer.observe(this._target);
	}

	_deactivateEvents() {
		super._deactivateEvents();

		this._resize_observer.disconnect();
	}
}
