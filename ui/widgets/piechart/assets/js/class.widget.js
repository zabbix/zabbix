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


class CWidgetPieChart extends CWidget {

	static ZBX_STYLE_DASHBOARD_WIDGET_PADDING_V = 8;
	static ZBX_STYLE_DASHBOARD_WIDGET_PADDING_H = 10;

	/**
	 * @type {CSVGPie|null}
	 */
	#pie_chart = null;

	onResize() {
		if (this.getState() === WIDGET_STATE_ACTIVE && this.#pie_chart !== null) {
			this.#pie_chart.setSize(this.#getSize());
		}
	}

	getUpdateRequestData() {
		return {
			...super.getUpdateRequestData(),
			has_custom_time_period: this.getFieldsReferredData().has('time_period') ? undefined : 1,
			with_config: this.#pie_chart === null ? 1 : undefined
		};
	}

	updateProperties({name, view_mode, fields}) {
		if (this.#pie_chart !== null) {
			this.#pie_chart.destroy();
			this.#pie_chart = null;
		}

		let legend = this._body.querySelector('.svg-pie-chart-legend');

		if (legend !== null) {
			// Hide legend while widget is loading
			legend.style.display = 'none';
		}

		super.updateProperties({name, view_mode, fields});
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

		this.#setLegend(legend, total_item);

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

	#setLegend(legend, total_item) {
		let container = this._body.querySelector('.svg-pie-chart-legend');

		if (container !== null) {
			if (legend.show) {
				container.innerHTML = '';
			}
			else {
				container.remove();
				return;
			}
		}

		if (legend.show && legend.data.length > 0) {
			if (container === null) {
				container = document.createElement('div');
				container.classList.add('svg-pie-chart-legend');

				this._body.append(container);
			}

			container.style.setProperty('--lines', legend.lines);
			container.style.setProperty('--columns', legend.columns);

			if (total_item !== null) {
				legend.data.pop();
				legend.data.unshift(total_item);
			}

			for (let i = 0; i < legend.data.length; i++) {
				const item = document.createElement('div');
				item.classList.add('svg-pie-chart-legend-item');
				item.style.setProperty('--color', legend.data[i].color);

				const name = document.createElement('span');
				name.append(legend.data[i].name);

				item.append(name);

				container.append(item);
			}

			container.style.display = '';
		}
	}

	#getSize() {
		const size = super._getContentsSize();

		const legend = this._body.querySelector('.svg-pie-chart-legend');

		if (legend !== null) {
			const box = legend.getBoundingClientRect();

			size.height -= box.height + CWidgetPieChart.ZBX_STYLE_DASHBOARD_WIDGET_PADDING_V;
		}

		if (size.height < 0) {
			size.height = 0;
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
				downloadSvgImage(this.#pie_chart.getSVGElement(), 'image.png', '.svg-pie-chart-legend');
			}
		});

		return menu;
	}

	hasPadding() {
		return false;
	}
}
