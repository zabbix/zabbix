/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

	/**
	 * @type {SVGMap|null}
	 */
	#map_svg = null;

	/**
	 * @type {string|null}
	 */
	#sysmapid = null;

	/**
	 * @type {Array.<{sysmapid: string}>}
	 */
	#previous_maps = [];

	/**
	 * @type {Object}
	 */
	#event_handlers;

	onStart() {
		this.#registerEvents();
	}

	onActivate() {
		this.#activateContentEvents();
	}

	onDeactivate() {
		this.#deactivateContentEvents();
	}

	promiseUpdate() {
		const fields_data = this.getFieldsData();

		if (this.isFieldsReferredDataUpdated('sysmapid')) {
			this.#previous_maps = [];
			this.#sysmapid = fields_data.sysmapid;
			this.#map_svg = null;
		}

		if (this.#map_svg === null || this.#sysmapid !== fields_data.sysmapid) {
			return super.promiseUpdate();
		}

		const curl = new Curl(this.#map_svg.options.refresh);

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: JSON.stringify(this.getUpdateRequestData())
		})
			.then((response) => response.json())
			.then((response) => {
				if (response.mapid != 0 && this.#map_svg !== null) {
					this.#map_svg.update(response);
				}
				else {
					this.#map_svg = null;
					this._startUpdating({delay_sec: this._update_retry_sec});
				}
			});
	}

	getUpdateRequestData() {
		return {
			...super.getUpdateRequestData(),
			current_sysmapid: this.#sysmapid ?? undefined,
			previous_maps: this.#previous_maps.map((map) => map.sysmapid),
			initial_load: this.#map_svg === null ? 1 : 0,
			unique_id: this.getUniqueId()
		};
	}

	processUpdateResponse(response) {
		this.#map_svg = null;

		super.processUpdateResponse(response);

		const sysmap_data = response.sysmap_data;

		if (sysmap_data !== undefined) {
			this.#sysmapid = sysmap_data.current_sysmapid;

			if (sysmap_data.map_options !== null) {
				this.#makeSvgMap(sysmap_data.map_options);
				this.#activateContentEvents();

				this.feedback({'sysmapid': this.#sysmapid});
			}

			if (sysmap_data.error_msg !== undefined) {
				this.setContents({body: sysmap_data.error_msg});
			}
		}
	}

	hasPadding() {
		return true;
	}

	navigateToSubmap(sysmapid) {
		jQuery('.menu-popup').menuPopup('close', null);

		this.#previous_maps.push({sysmapid: this.#sysmapid});
		this.#sysmapid = sysmapid;
		this.#map_svg = null;

		this._startUpdating();
	}

	#makeSvgMap(options) {
		options.canvas.useViewBox = true;
		options.show_timestamp = false;
		options.container = this._target.querySelector('.sysmap-widget-container');

		this.#map_svg = new SVGMap(options);
	}

	#activateContentEvents() {
		this._target.querySelectorAll('.js-previous-map').forEach((link) => {
			link.addEventListener('click', this.#event_handlers.back);
		});
	}

	#deactivateContentEvents() {
		this._target.querySelectorAll('.js-previous-map').forEach((link) => {
			link.addEventListener('click', this.#event_handlers.back);
		});
	}

	#registerEvents() {
		this.#event_handlers = {
			back: () => {
				const sysmap = this.#previous_maps.pop();

				this.#sysmapid = sysmap.sysmapid;
				this.#map_svg = null;

				this._startUpdating();
			}
		};
	}
}
