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


class CWidgetMap extends CWidget {

	_init() {
		super._init();

		this._filter_widget_reference = null;
		this._map_options = null;
	}

	_startUpdating(delay_sec = 0) {
		super._startUpdating(delay_sec);

		// this._$target.zbx_mapwidget('update', this);
	}

	_processUpdateResponse(response) {
		super._processUpdateResponse(response);

		if (response.sysmap_data !== undefined) {
			this._filter_widget_reference = response.sysmap_data.filter_widget_reference;
			this._map_options = response.sysmap_data.sysmap_data;

			if (response.sysmap_data._current_sysmapid !== null) {
				this.storeValue('current_sysmapid', response.sysmap_data._current_sysmapid);
			}

			if (this._filter_widget_reference !== null) {
				this._registerDataExchange();
			}

			if (this._map_options !== null) {
				this._$target.zbx_mapwidget(this._map_options);
			}

			if (response.sysmap_data.error_msg !== null) {
				this._$content_body.append(response.sysmap_data.error_msg);
			}
		}
	}

	_registerDataExchange() {
		// ZABBIX.Dashboard.registerDataExchange({
		// 	uniqueid: this._uniqueid,
		// 	linkedto: this._filter_widget_reference,
		// 	data_name: 'selected_mapid',
		// 	callback: (widget, data) => {
		// 		widget.storeValue('current_sysmapid', data.mapid);
		// 		ZABBIX.Dashboard.setWidgetStorageValue(widget._uniqueid, 'previous_maps', '');
		// 		ZABBIX.Dashboard.refreshWidget(widget._widgetid);
		// 	}
		// });

		// ZABBIX.Dashboard.callWidgetDataShare();

		// ZABBIX.Dashboard.addAction(DASHBOARD_PAGE_EVENT_EDIT,
		// 	'zbx_sysmap_widget_trigger', this._uniqueid, {
		// 		parameters: [DASHBOARD_PAGE_EVENT_EDIT],
		// 		grid: {widget: 1},
		// 		trigger_name: `map_widget_on_edit_start_${this._uniqueid}`
		// 	});
	}

	_registerEvents() {
		super._registerEvents();

		this._events = {
			...this._events,

			afterUpdateConfig: () => {
				this.storeValue('current_sysmapid', this._fields.sysmapid);
			}
		}

		// this
			// .on(WIDGET_EVENT_CONFIG_AFTER_UPDATE, this._events.afterUpdateConfig);
	}

	_unregisterEvents() {
		super._unregisterEvents();

		// this
			// .off(WIDGET_EVENT_CONFIG_AFTER_UPDATE, this._events.afterUpdateConfig);
	}
}
