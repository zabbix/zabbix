/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


const WIDGET_NAVTREE_EVENT_SELECT = 'select';

class CWidgetNavTree extends CWidget {

	_init() {
		super._init();

		this._is_map_loaded = false;
	}

	announceWidgets(widgets) {
		super.announceWidgets(widgets);

		// if (this._is_map_loaded) {
		// 	this._$target.zbx_navtree('onDashboardReady');
		// }
	}

	setEditMode() {
		super.setEditMode();

		// this._$target.zbx_navtree('switchToEditMode');
	}

	_startUpdating(delay_sec = 0, {do_update_once = false} = {}) {
		super._startUpdating(delay_sec, {do_update_once});

		// if (this._is_map_loaded) {
		// 	this._$target.zbx_navtree('beforeConfigLoad');
		// }
	}

	_processUpdateResponse(response) {
		super._processUpdateResponse(response);

		if (response.navtree_data !== undefined) {
			this._$target.zbx_navtree({
				problems: response.navtree_data.problems,
				severity_levels: response.navtree_data.severity_levels,
				navtree: response.navtree_data.navtree,
				navtree_items_opened: response.navtree_data.navtree_items_opened,
				navtree_item_selected: response.navtree_data.navtree_item_selected,
				maps_accessible: response.navtree_data.maps_accessible,
				show_unavailable: response.navtree_data.show_unavailable,
				initial_load: response.navtree_data.initial_load,
				uniqueid: this._unique_id,
				max_depth: response.navtree_data.max_depth
			}, this);

			this._is_map_loaded = true;
		}
	}

	_registerEvents() {
		super._registerEvents();

		this._events = {
			...this._events,

			copy: () => {
				// this._target.zbx_navtree('onWidgetCopy')
			}
		}


		this.on(WIDGET_EVENT_COPY, this._events.copy);
	}

	_unregisterEvents() {
		super._unregisterEvents();

		this.off(WIDGET_EVENT_COPY, this._events.copy);
	}
}
