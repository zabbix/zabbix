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

const WIDGET_SYSMAP_EVENT_SUBMAP_SELECT = 'submap-select';

class CWidgetMap extends CWidget {

	_init() {
		super._init();

		this._map_svg = null;

		this._filter_widget = null;

		this._previous_maps = [];
		this._current_sysmapid = null;
		this._initial_load = true;

		this._has_contents = false;
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

	_promiseUpdate() {
		if (!this._has_contents) {
			return super._promiseUpdate();
		}
		else {
			const curl = new Curl(this._map_svg.options.refresh);
			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: urlEncodeData({
					'curtime': new CDate().getTime(),
					'initial_load': 0
				})
			})
				.then((response) => response.json())
				.then((response) => {
					if (response.mapid > 0) {
						this._map_svg.update(response);
					}
					else {
						this._restartUpdating();
					}
				});
		}
	}

	_getUpdateRequestData() {
		return {
			...super._getUpdateRequestData(),
			current_sysmapid: this._current_sysmapid ?? undefined,
			previous_maps: this._previous_maps,
			unique_id: this._unique_id,
			initial_load: this._initial_load ? 1 : 0
		};
	}

	_processUpdateResponse(response) {
		if (this._has_contents) {
			this._unregisterContentEvents();

			this._has_contents = false;
		}

		super._processUpdateResponse(response);

		if (response.sysmap_data !== undefined) {
			this._current_sysmapid = response.sysmap_data.current_sysmapid;
			this._initial_load = false;

			const map_options = response.sysmap_data.map_options;

			if (map_options !== null) {
				this._makeSvgMap(map_options);

				if (this._state === WIDGET_STATE_ACTIVE) {
					this._registerContentsEvents();
				}

				this._has_contents = true;
			}

			if (response.sysmap_data.error_msg !== null) {
				jQuery(this._content_body).append(response.sysmap_data.error_msg);
			}
		}
	}

	_registerEvents() {
		super._registerEvents();

		this._events = {
			...this._events,

			back: (e) => {
				this.navigateToSubmap(e.target.getAttribute('data-previous-map'), true);
			},

			select: (e) => {
				if (this._current_sysmapid != e.detail.sysmapid) {
					this._previous_maps = [];
					this._navigateToMap(e.detail.sysmapid);
				}
			}
		}
	}

	_registerContentsEvents() {
		this._target.querySelectorAll('[data-previous-map]').forEach((link) => {
			link.addEventListener('click', this._events.back);
		});
	}

	_unregisterContentEvents() {
		this._target.querySelectorAll('[data-previous-map]').forEach((link) => {
			link.removeEventListener('click', this._events.back);
		});
	}

	_restartUpdating() {
		if (this._state === WIDGET_STATE_ACTIVE) {
			this._unregisterContentEvents();
		}

		this._has_contents = false;
		this._map_svg = null;
		this._initial_load = 1;

		this._startUpdating();
	}

	_makeSvgMap(options) {
		options.canvas.useViewBox = true;
		options.show_timestamp = false;
		options.container = this._target.querySelector('.sysmap-widget-container');

		this._map_svg = new SVGMap(options);
	}

	_navigateToMap(sysmapid) {
		this._current_sysmapid = sysmapid;

		jQuery('.menu-popup').menuPopup('close', null);

		if (this._state == WIDGET_STATE_ACTIVE) {
			this._restartUpdating();
		}
	}

	navigateToSubmap(sysmapid, back = false) {
		if (back && this._previous_maps.length > 0) {
			sysmapid = this._previous_maps.pop();
		}
		else {
			this._previous_maps.push(this._current_sysmapid);
		}

		this._navigateToMap(sysmapid);

		this.fire(WIDGET_SYSMAP_EVENT_SUBMAP_SELECT, {sysmapid, back});
	}
}
