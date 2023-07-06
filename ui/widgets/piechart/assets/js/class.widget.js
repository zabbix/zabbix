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

	onInitialize() {
		this.pie_chart = null;
	}

	onResize() {
		if (this._state === WIDGET_STATE_ACTIVE && this.pie_chart !== null) {
			this.pie_chart.setSize(super._getContentsSize());
		}
	}

	setTimePeriod(time_period) {
		super.setTimePeriod(time_period);

		if (this._state === WIDGET_STATE_ACTIVE) {
			this._startUpdating();
		}
	}

	getUpdateRequestData() {
		return {
			...super.getUpdateRequestData(),
			from: this._time_period.from,
			to: this._time_period.to,
			with_config: this.pie_chart === null ? 1 : undefined
		};
	}

	updateProperties({name, view_mode, fields}) {
		if (this.pie_chart !== null) {
			this.pie_chart.destroy();
			this.pie_chart = null;
		}

		super.updateProperties({name, view_mode, fields});
	}

	setContents(response) {
		if (this.pie_chart !== null) {
			this.pie_chart.setValue(response.sectors);

			return;
		}

		const padding = {
			vertical: CWidgetPieChart.ZBX_STYLE_DASHBOARD_WIDGET_PADDING_V,
			horizontal: CWidgetPieChart.ZBX_STYLE_DASHBOARD_WIDGET_PADDING_H,
		};

		this.pie_chart = new CSVGPie(padding, response.config);
		this.pie_chart.setSize(super._getContentsSize());
		this.pie_chart.setValue(response.sectors);
	}

	getActionsContextMenu({can_paste_widget}) {
		const menu = super.getActionsContextMenu({can_paste_widget});

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
			disabled: this.pie_chart === null,
			clickCallback: () => {
				downloadSvgImage(this.pie_chart.getSVGElement(), 'image.png', '.pie-chart-legend');
			}
		});

		return menu;
	}

	hasPadding() {
		return false;
	}
}
