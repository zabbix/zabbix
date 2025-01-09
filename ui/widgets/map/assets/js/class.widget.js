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

	/**
	 * @type {string|null}
	 */
	#selected_element_id = null;

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

		fields_data.sysmapid = fields_data.sysmapid ? fields_data.sysmapid[0] : fields_data.sysmapid;

		if (this.isFieldsReferredDataUpdated('sysmapid')) {
			this.#previous_maps = [];
		}

		if (this.#map_svg !== null || this.isFieldsReferredDataUpdated('sysmapid')) {
			if (this.#sysmapid != fields_data.sysmapid) {
				this.#sysmapid = fields_data.sysmapid;
			}

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

	promiseReady() {
		const readiness = [super.promiseReady()];

		if (this.#map_svg !== null) {
			readiness.push(this.#map_svg.promiseRendered());
		}

		return Promise.all(readiness);
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
		this.clearContents();

		super.processUpdateResponse(response);

		const sysmap_data = response.sysmap_data;

		if (sysmap_data === undefined) {
			return;
		}

		if (this.#sysmapid != sysmap_data.current_sysmapid) {
			this.#sysmapid = sysmap_data.current_sysmapid;
		}

		if (sysmap_data.error_msg !== undefined) {
			this.setContents({body: sysmap_data.error_msg});
		}

		if (sysmap_data.map_options === null) {
			return;
		}

		this.#makeSvgMap(sysmap_data.map_options);
		this.#activateContentEvents();

		if (!this.hasEverUpdated() && this.isReferred()) {
			const closest_element = this.#getDefaultSelectable(
				Object.values(sysmap_data.map_options.elements)
			);

			this.#selected_element_id = closest_element.selementid;

			this.#map_svg.select(this.#selected_element_id);
			this.#map_svg.selected_element_id = this.#selected_element_id;

			this.#broadcast(closest_element.elements[0].groupid, closest_element.elements[0].hostid);
		}
		else if (this.#selected_element_id !== null) {
			this.#map_svg.select(this.#selected_element_id);
			this.#map_svg.selected_element_id = this.#selected_element_id;
		}
	}

	onReferredUpdate() {
		if (Object.keys(this.#map_svg.elements).length === 0 || this.#selected_element_id !== null) {
			return;
		}

		const closest_element = this.#getDefaultSelectable(
			Object.values(this.#map_svg.elements).map(element => element.options)
		);

		this.#selected_element_id = closest_element.selementid;

		this.#map_svg.select(this.#selected_element_id);
		this.#map_svg.selected_element_id = this.#selected_element_id;

		this.#broadcast(closest_element.elements[0].groupid, closest_element.elements[0].hostid);
	}

	onClearContents() {
		this.#map_svg = null;
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
		options.can_select_element = true;

		this.#map_svg = new SVGMap(options);
	}

	#activateContentEvents() {
		this.#map_svg?.container.addEventListener(this.#map_svg.EVENT_ELEMENT_SELECT, this.#event_handlers.select);

		this._target.querySelectorAll('.js-previous-map').forEach((link) => {
			link.addEventListener('click', this.#event_handlers.back);
		});
	}

	#deactivateContentEvents() {
		this.#map_svg?.container.removeEventListener(this.#map_svg.EVENT_ELEMENT_SELECT, this.#event_handlers.select);

		this._target.querySelectorAll('.js-previous-map').forEach((link) => {
			link.removeEventListener('click', this.#event_handlers.back);
		});
	}

	#broadcast(hostgroupid, hostid) {
		this.broadcast({
			[CWidgetsData.DATA_TYPE_HOST_GROUP_ID]: hostgroupid !== null && hostgroupid !== undefined
				? [hostgroupid]
				: CWidgetsData.getDefault(CWidgetsData.DATA_TYPE_HOST_GROUP_ID),
			[CWidgetsData.DATA_TYPE_HOST_GROUP_IDS]: hostgroupid !== null && hostgroupid !== undefined
				? [hostgroupid]
				: CWidgetsData.getDefault(CWidgetsData.DATA_TYPE_HOST_GROUP_IDS),
			[CWidgetsData.DATA_TYPE_HOST_ID]: hostid !== null && hostid !== undefined
				? [hostid]
				: CWidgetsData.getDefault(CWidgetsData.DATA_TYPE_HOST_ID),
			[CWidgetsData.DATA_TYPE_HOST_IDS]: hostid !== null && hostid !== undefined
				? [hostid]
				: CWidgetsData.getDefault(CWidgetsData.DATA_TYPE_HOST_IDS)
		});
	}

	#registerEvents() {
		this.#event_handlers = {
			back: () => {
				const sysmap = this.#previous_maps.pop();

				this.#sysmapid = sysmap.sysmapid;
				this.#map_svg = null;

				this._startUpdating();
			},
			select: ({detail}) => {
				this.#selected_element_id = detail.selected_element_id;
				this.#map_svg.select(this.#selected_element_id);

				if (detail.hostgroupid !== null || detail.hostid !== null) {
					this.#broadcast(detail.hostgroupid, detail.hostid);
				}
			}
		};
	}

	#getDefaultSelectable(elements) {
		return elements.reduce((closest, current) => {
			if (current.elementtype == SYSMAP_ELEMENT_TYPE_HOST
					|| current.elementtype == SYSMAP_ELEMENT_TYPE_HOST_GROUP) {
				const current_distance = Math.sqrt(current.x * current.x + current.y * current.y);

				if (closest === null || current_distance < Math.sqrt(closest.x * closest.x + closest.y * closest.y)) {
					return current;
				}
			}

			return closest;
		}, null);
	}
}
