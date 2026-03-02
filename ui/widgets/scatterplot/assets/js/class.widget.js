/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CWidgetScatterPlot extends CWidget {

	static DATASET_TYPE_SINGLE_ITEM = 0;

	/**
	 * @type {CSvgGraph|null}
	 */
	#graph = null;

	/**
	 *
	 * @type {SVGSVGElement|null}
	 */
	#svg = null;

	#selected_itemid = null;
	#selected_ds = null;

	#is_default_selected_itemid = true;

	onInitialize() {
		this._has_contents = false;
	}

	onActivate() {
		this.#activateGraph();
	}

	onDeactivate() {
		this.#deactivateGraph();
	}

	onResize() {
		if (this._state === WIDGET_STATE_ACTIVE) {
			this._startUpdating();
		}
	}

	onFeedback({type, value}) {
		if (type === CWidgetsData.DATA_TYPE_TIME_PERIOD && this.getFieldsReferredData().has('time_period')) {
			this._startUpdating();

			this.feedback({time_period: value});

			return true;
		}

		return false;
	}

	promiseUpdate() {
		const time_period = this.getFieldsData().time_period;

		if (!this.hasBroadcast(CWidgetsData.DATA_TYPE_TIME_PERIOD) || this.isFieldsReferredDataUpdated('time_period')) {
			this.broadcast({
				[CWidgetsData.DATA_TYPE_TIME_PERIOD]: time_period
			});
		}

		return super.promiseUpdate();
	}

	getUpdateRequestData() {
		const request_data = super.getUpdateRequestData();

		for (const [dataset_key, dataset] of request_data.fields.ds.entries()) {
			if (dataset.dataset_type != CWidgetSvgGraph.DATASET_TYPE_SINGLE_ITEM) {
				continue;
			}

			const dataset_new = {
				...dataset,
				x_axis_itemids: [],
				y_axis_itemids: []
			};

			for (const key of ['x_axis_itemids', 'y_axis_itemids']) {
				for (const itemid of dataset[key].values()) {
					if (Array.isArray(itemid)) {
						if (itemid.length === 1) {
							dataset_new[key].push(itemid[0]);
						}
					}
					else {
						dataset_new[key].push(itemid);
					}
				}
			}

			request_data.fields.ds[dataset_key] = dataset_new;
		}

		if (!this.getFieldsReferredData().has('time_period')) {
			request_data.has_custom_time_period = 1;
		}

		return request_data;
	}

	processUpdateResponse(response) {
		this.clearContents();

		super.processUpdateResponse(response);

		if (response.svg_options !== undefined) {
			this._has_contents = true;

			if (this.#is_default_selected_itemid && response.svg_options.first_metric_to_broadcast !== null) {
				const {itemid, ds} = response.svg_options.first_metric_to_broadcast;
				this.updateItemBroadcast([itemid], ds, true);
			}

			this.#initGraph({
				sbox: false,
				graph_type: GRAPH_TYPE_SCATTER_PLOT,
				min_period: 60,
				...response.svg_options.data
			});
		}
		else {
			this._has_contents = false;
		}
	}

	updateItemBroadcast(itemids, ds, is_default_selected_itemid = false) {
		this.#selected_itemid = itemids[0];
		this.#selected_ds = ds;

		this.#is_default_selected_itemid = is_default_selected_itemid;

		this.broadcast({
			[CWidgetsData.DATA_TYPE_ITEM_ID]: [this.#selected_itemid],
			[CWidgetsData.DATA_TYPE_ITEM_IDS]: [this.#selected_itemid]
		});
	}

	getItemBroadcast() {
		return {itemid: this.#selected_itemid, itemids: [this.#selected_itemid], ds: this.#selected_ds}
	}

	onClearContents() {
		if (this._has_contents) {
			this.#deactivateGraph();

			this._has_contents = false;
		}
	}

	getActionsContextMenu({can_copy_widget, can_paste_widget}) {
		const menu = super.getActionsContextMenu({can_copy_widget, can_paste_widget});

		if (this.isEditMode()) {
			return menu;
		}

		let menu_actions = null;

		for (const search_menu_actions of menu) {
			if ('label' in search_menu_actions && search_menu_actions.label === t('Actions')) {
				menu_actions = search_menu_actions;

				break;
			}
		}

		if (menu_actions === null) {
			menu_actions = {
				label: t('Actions'),
				items: []
			};

			menu.unshift(menu_actions);
		}

		menu_actions.items.push({
			label: t('Download image'),
			disabled: !this._has_contents,
			clickCallback: () => {
				downloadSvgImage(this.#svg, 'image.png', '.svg-scatter-plot-legend');
			}
		});

		return menu;
	}

	hasPadding() {
		return true;
	}

	#initGraph(options) {
		this.#svg = this._body.querySelector('svg');
		this.#graph = new CSvgGraph(this.#svg, this, options);

		this.#activateGraph();
	}

	#activateGraph() {
		if (this._has_contents && this.#graph !== null) {
			this.#graph.activate();
		}
	}

	#deactivateGraph() {
		if (this._has_contents && this.#graph !== null) {
			this.#graph.deactivate();
		}
	}
}
