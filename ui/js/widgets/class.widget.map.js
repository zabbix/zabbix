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


const WIDGET_SYSMAP_SOURCETYPE_MAP = 1;
const WIDGET_SYSMAP_SOURCETYPE_FILTER = 2;

const WIDGET_SYSMAP_EVENT_SUBMAP_SELECT = 'widget-sysmap-submap-select';

class CWidgetMap extends CWidget {

	_init() {
		super._init();

		this._map_svg = null;

		this._source_type = this._fields.source_type || WIDGET_SYSMAP_SOURCETYPE_MAP;
		this._filter_widget = null;
		this._filter_itemid = null;

		this._previous_maps = [];
		this._sysmapid = null;
		this._initial_load = true;

		this._has_contents = false;
	}

	_doActivate() {
		super._doActivate();

		if (this._has_contents) {
			this._activateContentsEvents();
		}
	}

	_doDeactivate() {
		super._doDeactivate();

		if (this._has_contents) {
			this._deactivateContentsEvents();
		}
	}

	_doDestroy() {
		super._doDestroy();

		if (this._filter_widget) {
			this._filter_widget.off(WIDGET_NAVTREE_EVENT_MARK, this._events.mark);
			this._filter_widget.off(WIDGET_NAVTREE_EVENT_SELECT, this._events.select);
		}
	}

	announceWidgets(widgets) {
		super.announceWidgets(widgets);

		if (this._filter_widget !== null) {
			this._filter_widget.off(WIDGET_NAVTREE_EVENT_MARK, this._events.mark);
			this._filter_widget.off(WIDGET_NAVTREE_EVENT_SELECT, this._events.select);
		}

		if (this._source_type == WIDGET_SYSMAP_SOURCETYPE_FILTER) {
			for (const widget of widgets) {
				if (widget instanceof CWidgetNavTree
						&& widget._fields.reference === this._fields.filter_widget_reference) {
					this._filter_widget = widget;

					this._filter_widget.on(WIDGET_NAVTREE_EVENT_MARK, this._events.mark);
					this._filter_widget.on(WIDGET_NAVTREE_EVENT_SELECT, this._events.select);
				}
			}
		}
	}

	_promiseUpdate() {
		if (!this._has_contents || this._map_svg === null) {
			if (this._sysmapid !== null
					|| this._source_type == WIDGET_SYSMAP_SOURCETYPE_MAP
					|| this._filter_widget === null) {
				return super._promiseUpdate();
			}

			return Promise.resolve();
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
					if (response.mapid > 0 && this._map_svg) {
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
			current_sysmapid: this._sysmapid ?? undefined,
			previous_maps: this._previous_maps.map((i) => i.sysmapid),
			unique_id: this._unique_id,
			initial_load: this._initial_load ? 1 : 0
		};
	}

	_processUpdateResponse(response) {
		if (this._has_contents) {
			this._deactivateContentsEvents();

			this._has_contents = false;
		}

		super._processUpdateResponse(response);

		if (response.sysmap_data !== undefined) {
			this._has_contents = true;

			this._sysmapid = response.sysmap_data.current_sysmapid;
			this._initial_load = false;

			const map_options = response.sysmap_data.map_options;

			if (map_options !== null) {
				this._makeSvgMap(map_options);

				if (this._state === WIDGET_STATE_ACTIVE) {
					this._activateContentsEvents();
				}
			}

			if (response.sysmap_data.error_msg !== undefined) {
				this._content_body.innerHTML = response.sysmap_data.error_msg;
			}
		}
	}

	_registerEvents() {
		super._registerEvents();

		this._events = {
			...this._events,

			back: () => {
				const item = this._previous_maps.pop();

				this._navigateToMap(item.sysmapid);

				this.fire(WIDGET_SYSMAP_EVENT_SUBMAP_SELECT, {
					sysmapid: item.sysmapid,
					parent_itemid: item.parent_itemid,
					back: true
				});
			},

			select: (e) => {
				this._previous_maps = [];
				this._filter_itemid = e.detail.itemid;

				this._navigateToMap(e.detail.sysmapid);
			},

			mark: (e) => {
				this._filter_itemid = e.detail.itemid;
			}
		}
	}

	_activateContentsEvents() {
		if (this._state === WIDGET_STATE_ACTIVE) {
			this._target.querySelectorAll('.js-previous-map').forEach((link) => {
				link.addEventListener('click', this._events.back);
			});
		}
	}

	_deactivateContentsEvents() {
		this._target.querySelectorAll('.js-previous-map').forEach((link) => {
			link.removeEventListener('click', this._events.back);
		});
	}

	_makeSvgMap(options) {
		options.canvas.useViewBox = true;
		options.show_timestamp = false;
		options.container = this._target.querySelector('.sysmap-widget-container');

		this._map_svg = new SVGMap(options);
	}

	navigateToSubmap(sysmapid) {
		this._previous_maps.push({sysmapid: this._sysmapid, parent_itemid: this._filter_itemid});

		this._navigateToMap(sysmapid);

		this.fire(WIDGET_SYSMAP_EVENT_SUBMAP_SELECT, {
			sysmapid,
			parent_itemid: this._filter_itemid
		});
	}

	_navigateToMap(sysmapid) {
		this._sysmapid = sysmapid;

		if (this._state === WIDGET_STATE_ACTIVE) {
			jQuery('.menu-popup').menuPopup('close', null);
			this._restartUpdating();
		}
	}

	_restartUpdating() {
		if (this._state === WIDGET_STATE_ACTIVE) {
			this._deactivateContentsEvents();
		}

		this._has_contents = false;
		this._map_svg = null;
		this._initial_load = 1;

		this._startUpdating();
	}
}
