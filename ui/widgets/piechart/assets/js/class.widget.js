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
		this.pie = null;
	}

	onResize() {
		if (this._state === WIDGET_STATE_ACTIVE && this.pie !== null) {
			this.pie.setSize(super._getContentsSize());
		}
	}

	updateProperties({name, view_mode, fields}) {
		if (this.pie !== null) {
			this.pie.destroy();
			this.pie = null;
		}

		super.updateProperties({name, view_mode, fields});
	}

	getUpdateRequestData() {
		return {
			...super.getUpdateRequestData(),
		};
	}

	setContents(response) {
		if (this.pie !== null) {
			this.pie.setValue(response.sectors);

			return;
		}

		const use_padding_top = this.getViewMode() === ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER;

		const padding = {
			top: use_padding_top ? this.constructor.ZBX_STYLE_DASHBOARD_WIDGET_PADDING_V : 0,
			right: this.constructor.ZBX_STYLE_DASHBOARD_WIDGET_PADDING_H,
			bottom: this.constructor.ZBX_STYLE_DASHBOARD_WIDGET_PADDING_V,
			left: this.constructor.ZBX_STYLE_DASHBOARD_WIDGET_PADDING_H
		};

		this.pie = new CSVGPie(this._body, padding, response.config);
		this.pie.setSize(super._getContentsSize());
		this.pie.setValue(sectors_data);
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
			disabled: this.pie === null,
			clickCallback: () => {
				downloadSvgImage(this.pie.getSVGElement(), 'piechart.png');
			}
		});

		return menu;
	}

	hasPadding() {
		return true;
	}
}
