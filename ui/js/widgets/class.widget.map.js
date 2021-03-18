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

		this._map_svg = null;

		this._filter_widget = null;

		this._previous_maps = [];
		this._current_sysmapid = null;
		this._initial_load = 1;

		this._has_contents = false;
		this._is_refreshing = false;
	}

	_doDestroy() {
		super._doDestroy();

		if (this._filter_widget) {
			this._filter_widget.off(WIDGET_NAVTREE_EVENT_SELECT, this._events.select);
		}
	}

	announceWidgets(widgets) {
		super.announceWidgets(widgets);

		if (this._filter_widget !== null) {
			this._filter_widget.off(WIDGET_NAVTREE_EVENT_SELECT, this._events.select);
		}

		if (this._fields.source_type == WIDGET_SYSMAP_SOURCETYPE_FILTER) {
			for (const widget of widgets) {
				if (widget instanceof CWidgetNavTree
						&& widget._fields.reference === this._fields.filter_widget_reference) {
					this._filter_widget = widget;

					this._filter_widget.on(WIDGET_NAVTREE_EVENT_SELECT, this._events.select);
				}
			}
		}
	}

	_getUpdateRequestData() {
		return {
			...super._getUpdateRequestData(),
			current_sysmapid: this._current_sysmapid ?? undefined,
			previous_maps: this._previous_maps,
			initial_load: 1
		};
	}

	_processUpdateResponse(response) {
		super._processUpdateResponse(response);

		if (response.sysmap_data !== undefined) {
			if (response.sysmap_data.current_sysmapid !== this._current_sysmapid) {
				this._startUpdating();
			}
			else {
				this._current_sysmapid = response.sysmap_data.current_sysmapid;
				this._initial_load = response.sysmap_data.initial_load;

				const map_options = response.sysmap_data.map_options;

				if (map_options !== null) {
					this._makeSvgMap(map_options);

					this._has_contents = true;
				}

				if (response.sysmap_data.error_msg !== null) {
					this._$content_body.append(response.sysmap_data.error_msg);
				}
			}
		}
		else {
			this._map_svg = null;
			this._initial_load = 1;
			this._has_contents = false;
		}
	}

	_registerEvents() {
		super._registerEvents();

		this._events = {
			...this._events,

			select: (e) => {
				if (this._current_sysmapid != e.detail.mapid) {
					this.navigateToMap(e.detail.mapid);
				}
			}
		}
	}

	navigateToMap(sysmapid) {
		this._current_sysmapid = sysmapid;

		if (this._state == WIDGET_STATE_ACTIVE) {
			this._startUpdating();

			// TODO
			// ZABBIX.Dashboard.widgetDataShare(widget[0], 'current_sysmapid',
			// 	{submapid: submapid, previous_maps: previous_maps, moving_upward: reset_previous ? 1 : 0}
			// );

			$('.menu-popup').menuPopup('close', null);
		}
	}

	navigateToSubmap(submapid, reset_previous = false) {
		if (this._current_sysmapid !== null) {
			if (reset_previous && this._previous_maps.length > 0) {
				this._previous_maps.pop();
			}
			else {
				this._previous_maps.push(this._current_sysmapid);
			}
		}

		this.navigateToMap(submapid);
	}

	_makeSvgMap(options) {
		options.canvas.useViewBox = true;
		options.show_timestamp = false;
		options.container = this._target.querySelector('.sysmap-widget-container');

		this._map_svg = new SVGMap(options);
	}

	_updateSvgMap() {
		if (this._has_contents && !this._is_refreshing) {
			this._is_refreshing = true;

			const curl = new Curl(this._map_svg.options.refresh);
			curl.setArgument('curtime', new CDate().getTime());
			curl.setArgument('initial_load', 0);

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				}
			})
				.then((response) => response.json())
				.then((response) => {
					this._is_refreshing = false;

					if (response.mapid > 0) {
						this._map_svg.update(response);
					}
					else {
						if (this._state === WIDGET_STATE_ACTIVE) {
							this._startUpdating();
						}
					}
				});
		}
	}
}
