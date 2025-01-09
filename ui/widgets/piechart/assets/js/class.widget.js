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


class CWidgetPieChart extends CWidget {

	static ZBX_STYLE_DASHBOARD_WIDGET_PADDING_V = 8;
	static ZBX_STYLE_DASHBOARD_WIDGET_PADDING_H = 10;

	static ZBX_STYLE_PIE_CHART_LEGEND = 'svg-pie-chart-legend';
	static ZBX_STYLE_PIE_CHART_LEGEND_HEADER = 'svg-pie-chart-legend-header';
	static ZBX_STYLE_PIE_CHART_LEGEND_ITEM = 'svg-pie-chart-legend-item';
	static ZBX_STYLE_PIE_CHART_LEGEND_SHOW_VALUE = 'svg-pie-chart-legend-show-value';
	static ZBX_STYLE_PIE_CHART_LEGEND_VALUE = 'svg-pie-chart-legend-value';
	static ZBX_STYLE_PIE_CHART_LEGEND_NO_DATA = 'svg-pie-chart-legend-no-data';

	static DATASET_TYPE_SINGLE_ITEM = 0;

	// Legend single line height is 18px. Value should be synchronized with $svg-legend-line-height in scss.
	static LEGEND_LINE_HEIGHT = 18;

	static LEGEND_LINES_MODE_VARIABLE = 1;

	/**
	 * @type {CSVGPie|null}
	 */
	#pie_chart = null;

	onResize() {
		if (this.getState() === WIDGET_STATE_ACTIVE && this.#pie_chart !== null) {
			this.#pie_chart.setSize(this.#getSize());
		}
	}

	promiseReady() {
		const readiness = [super.promiseReady()];

		if (this.#pie_chart !== null) {
			readiness.push(this.#pie_chart.promiseRendered());
		}

		return Promise.all(readiness);
	}

	getUpdateRequestData() {
		const request_data = super.getUpdateRequestData();

		for (const [dataset_key, dataset] of request_data.fields.ds.entries()) {
			if (dataset.dataset_type != CWidgetPieChart.DATASET_TYPE_SINGLE_ITEM) {
				continue;
			}

			const dataset_new = {
				...dataset,
				itemids: [],
				type: [],
				color: []
			};

			for (const [item_index, itemid] of dataset.itemids.entries()) {
				if (Array.isArray(itemid)) {
					if (itemid.length === 1) {
						dataset_new.itemids.push(itemid[0]);
						dataset_new.type.push(dataset.type[item_index]);
						dataset_new.color.push(dataset.color[item_index]);
					}
				}
				else {
					dataset_new.itemids.push(itemid);
					dataset_new.type.push(dataset.type[item_index]);
					dataset_new.color.push(dataset.color[item_index]);
				}
			}

			request_data.fields.ds[dataset_key] = dataset_new;
		}


		if (!this.getFieldsReferredData().has('time_period')) {
			request_data.has_custom_time_period = 1;
		}

		if (this.#pie_chart === null) {
			request_data.with_config = 1;
		}

		return request_data;
	}

	setContents(response) {
		const legend = {...response.legend};

		let total_item = null;
		let total_item_index = -1;

		for (let i = 0; i < legend.data.length; i++) {
			if (legend.data[i].is_total) {
				total_item = legend.data[i];
				total_item_index = i;
				break;
			}
		}

		if (total_item !== null) {
			legend.data.splice(total_item_index, 1);
			legend.data.push(total_item);
		}

		this.#showLegend(legend, total_item);

		if (this.#pie_chart === null) {
			const padding = {
				vertical: CWidgetPieChart.ZBX_STYLE_DASHBOARD_WIDGET_PADDING_V,
				horizontal: CWidgetPieChart.ZBX_STYLE_DASHBOARD_WIDGET_PADDING_H,
			};

			this.#pie_chart = new CSVGPie(padding, response.config);
			this._body.prepend(this.#pie_chart.getSVGElement());
		}

		this.#pie_chart.setSize(this.#getSize());

		this.#pie_chart.setValue({
			sectors: response.sectors,
			all_sectorids: response.all_sectorids,
			total_value: response.total_value
		});
	}

	onClearContents() {
		if (this.#pie_chart !== null) {
			this.#pie_chart.destroy();
			this.#pie_chart = null;
		}
	}

	#showLegend(legend, total_item) {
		this.#removeLegend();

		if (!legend.show || legend.data.length === 0) {
			return;
		}

		if (total_item !== null) {
			legend.data.pop();
			legend.data.unshift(total_item);
		}

		const container = document.createElement('div');
		container.classList.add(CWidgetPieChart.ZBX_STYLE_PIE_CHART_LEGEND);

		const legend_columns = legend.value_show ? 1 : legend.columns;
		const legend_lines = legend.lines_mode === CWidgetPieChart.LEGEND_LINES_MODE_VARIABLE
			? Math.min(Math.ceil(legend.data.length / legend_columns), legend.lines)
			: legend.lines;

		if (legend.value_show) {
			container.classList.add(CWidgetPieChart.ZBX_STYLE_PIE_CHART_LEGEND_SHOW_VALUE);
			container.style.setProperty('--lines', legend_lines + 1);

			const header = document.createElement('div');
			header.textContent = t('Value');
			header.classList.add(CWidgetPieChart.ZBX_STYLE_PIE_CHART_LEGEND_HEADER);

			container.appendChild(header);
		}
		else {
			container.style.setProperty('--lines', legend_lines);
			container.style.setProperty('--columns', legend.columns);
		}

		for (const item of legend.data.slice(0, legend.lines * (legend.value_show ? 1 : legend.columns))) {
			const item_name_column = document.createElement('div');
			item_name_column.classList.add(CWidgetPieChart.ZBX_STYLE_PIE_CHART_LEGEND_ITEM);
			item_name_column.style.setProperty('--color', item.color);

			const name = document.createElement('span');
			name.textContent = item.name;
			item_name_column.append(name);

			container.append(item_name_column);

			if (legend.value_show) {
				const value_column = document.createElement('div');

				if (item.value !== null && item.value !== '') {
					value_column.textContent = item.value;
					value_column.className = CWidgetPieChart.ZBX_STYLE_PIE_CHART_LEGEND_VALUE;
				}
				else {
					value_column.textContent = `[${t('no data')}]`;
					value_column.className = CWidgetPieChart.ZBX_STYLE_PIE_CHART_LEGEND_NO_DATA;
				}

				container.append(value_column);
			}
		}

		this._body.append(container);
	}

	#removeLegend() {
		const container = this._body.querySelector(`.${CWidgetPieChart.ZBX_STYLE_PIE_CHART_LEGEND}`);

		if (container !== null) {
			container.remove();
		}
	}

	#getSize() {
		const size = super._getContentsSize();
		const container = this._body.querySelector(`.${CWidgetPieChart.ZBX_STYLE_PIE_CHART_LEGEND}`);

		if (container !== null) {
			const legend_lines = getComputedStyle(container).getPropertyValue('--lines');

			let pie_chart_height = size.height
				- (legend_lines * CWidgetPieChart.LEGEND_LINE_HEIGHT
					+ CWidgetPieChart.ZBX_STYLE_DASHBOARD_WIDGET_PADDING_V);

			if (pie_chart_height < 0) {
				pie_chart_height = 0;
			}

			size.height = pie_chart_height;
		}

		return size;
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
			disabled: this.#pie_chart === null,
			clickCallback: () => {
				downloadSvgImage(
					this.#pie_chart.getSVGElement(),
					'image.png',
					`.${CWidgetPieChart.ZBX_STYLE_PIE_CHART_LEGEND}`
				);
			}
		});

		return menu;
	}

	hasPadding() {
		return false;
	}
}
