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


class CWidgetSvgGraph extends CWidget {

	static DATASET_TYPE_SINGLE_ITEM = 0;

	onInitialize() {
		this._has_contents = false;
		this._svg_options = {};
	}

	onActivate() {
		this._activateGraph();
	}

	onDeactivate() {
		this._deactivateGraph();
	}

	onResize() {
		if (this._state === WIDGET_STATE_ACTIVE) {
			this._startUpdating();
		}
	}

	onEdit() {
		this._deactivateGraph();
	}

	onFeedback({type, value}) {
		if (type === CWidgetsData.DATA_TYPE_TIME_PERIOD) {
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
				itemids: [],
				color: []
			};

			for (const [item_index, itemid] of dataset.itemids.entries()) {
				if (Array.isArray(itemid)) {
					if (itemid.length === 1) {
						dataset_new.itemids.push(itemid[0]);
						dataset_new.color.push(dataset.color[item_index]);
					}
				}
				else {
					dataset_new.itemids.push(itemid);
					dataset_new.color.push(dataset.color[item_index]);
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

			this._initGraph({
				sbox: false,
				show_problems: true,
				show_simple_triggers: true,
				hint_max_rows: 20,
				min_period: 60,
				...response.svg_options.data
			});
		}
		else {
			this._has_contents = false;
		}
	}

	onClearContents() {
		if (this._has_contents) {
			this._deactivateGraph();

			this._has_contents = false;
		}
	}

	_initGraph(options) {
		this._svg_options = options;
		this._svg = this._body.querySelector('svg');
		jQuery(this._svg).svggraph(this);

		this._activateGraph();
	}

	_activateGraph() {
		if (this._has_contents) {
			jQuery(this._svg).svggraph('activate');
		}
	}

	_deactivateGraph() {
		if (this._has_contents) {
			jQuery(this._svg).svggraph('deactivate');
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
				downloadSvgImage(this._svg, 'image.png', '.svg-graph-legend');
			}
		});

		return menu;
	}

	hasPadding() {
		return true;
	}
}
