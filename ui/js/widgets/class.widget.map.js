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


const WIDGET_SYSMAP_SOURCETYPE_MAP = 1;
const WIDGET_SYSMAP_SOURCETYPE_FILTER = 2;

class CWidgetMap extends CWidget {

	_init() {
		super._init();

		this._filter_widget = null;
		this._filter_widget_reference = null;
		this._map_options = null;

		this._previous_maps = new Array();
		this._current_sysmapid = null;

		this._is_map_loaded = false;
	}

	_doActivate() {
		super._doActivate();
	}

	_doDeactivate() {
		super._doDeactivate();
	}

	announceWidgets(widgets) {
		super.announceWidgets(widgets);

		// if (this._fields.source_type == WIDGET_SYSMAP_SOURCETYPE_FILTER) {
		// 	for (const widget of widgets) {
		// 		if (widget instanceof CWidgetNavTree
		// 				&& widget._fields.reference === this._fields.filter_widget_reference) {
		// 			this._filter_widget = widget;
		// 		}
		// 	}
		// }
	}

	// setConfiguration(configuration) {
	// 	super.setConfiguration(configuration);

	// 	this._current_sysmapid = this._fields.sysmapid;
	// }

	// navigateToMap(sysmapid) {
	// 	this._current_sysmapid = sysmapid;

	// 	if (this._state == WIDGET_STATE_ACTIVE) {
	// 		this._startUpdating();

	// 		// TODO
	// 		// ZABBIX.Dashboard.widgetDataShare(widget[0], 'current_sysmapid',
	// 		// 	{submapid: submapid, previous_maps: previous_maps, moving_upward: reset_previous ? 1 : 0}
	// 		// );

	// 		$('.menu-popup').menuPopup('close', null);
	// 	}
	// }

	// navigateToSubmap(submapid, reset_previous = false) {
	// 	if (this._current_sysmapid !== null) {
	// 		if (reset_previous && this._previous_maps.length > 0) {
	// 			this._previous_maps.pop();
	// 		}
	// 		else {
	// 			this._previous_maps.push(this._current_sysmapid);
	// 		}
	// 	}

	// 	this.navigateToMap(submapid);
	// }

	_startUpdating(delay_sec = 0) {
		super._startUpdating(delay_sec);

		if (this._is_map_loaded) {

			// const $this = $(this);

			// const widget = $this.data('widget');
			// const widget_data = $this.data('widgetData');

			// if (widget._is_map_loaded && widget_data['is_refreshing'] === false) {
			// 	widget_data['is_refreshing'] = true;

			// 	const url = new Curl(widget_data['map_instance'].options.refresh);
			// 	url.setArgument('curtime', new CDate().getTime());
			// 	url.setArgument('uniqueid', widget.getUniqueId());

			// 	$.ajax({
			// 		'url': url.getUrl()
			// 	})
			// 		.done(function(data) {
			// 			widget_data['is_refreshing'] = false;
			// 			if (data.mapid > 0) {
			// 				widget_data['map_instance'].update(data);
			// 			}
			// 			else {
			// 				if (widget.getState() === WIDGET_STATE_ACTIVE) {
			// 					widget._startUpdating();
			// 				}
			// 			}
			// 		});
			// }

			// this._$target.zbx_mapwidget('update');



		}
	}

	_processUpdateResponse(response) {
		super._processUpdateResponse(response);

		// if (response.sysmap_data !== undefined) {
		// 	this._current_sysmapid = response.sysmap_data.current_sysmapid;
		// 	this._filter_widget_reference = response.sysmap_data.filter_widget_reference;
		// 	this._map_options = response.sysmap_data.map_options;
		// 	this._is_map_loaded = true;

		// 	if (this._filter_widget_reference !== null) {

		// 	}

		// 	if (this._map_options !== null) {
		// 		console.log(this._map_options);
		// 		this._$target.zbx_mapwidget(this._map_options, this);
		// 		const widget_data = {...this._map_options};

		// 		this._map_options.canvas.useViewBox = true;
		// 		this._map_options.show_timestamp = false;
		// 		widget_data.map_instance = new SVGMap(options['map_options']);
		// 		widget_data.is_refreshing = false;
		// 		this._$target.data('widgetData', widget_data);
		// 		this._$target.data('widget', this);
		// 	}

		// 	if (response.sysmap_data.error_msg !== null) {
		// 		this._$content_body.append(response.sysmap_data.error_msg);
		// 	}
		// }
	}

	_registerEvents() {
		super._registerEvents();

		// this._events = {
		// 	...this._events,

		// 	select: (e) => {
		// 		if (this._mapid != e.detail.mapid) {
		// 			this._current_sysmapid = e.detail.mapid;
		// 			this._is_map_loaded = false;
		// 		}
		// 	}
		// }

		// if (this._fields.source_type == WIDGET_SYSMAP_SOURCETYPE_FILTER && this._filter_widget !== null) {
		// 	this._filter_widget.on(WIDGET_NAVTREE_EVENT_SELECT, this._events.select);
		// }
	}

	_unregisterEvents() {
		super._unregisterEvents();

		// if (this._fields.source_type == WIDGET_SYSMAP_SOURCETYPE_FILTER && this._filter_widget !== null) {
		// 	this._filter_widget.off(WIDGET_NAVTREE_EVENT_SELECT, this._events.select);
		// }
	}
}
