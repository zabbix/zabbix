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


class CWidgetGauge extends CWidget {

	static ZBX_STYLE_DASHBOARD_WIDGET_PADDING_V = 8;
	static ZBX_STYLE_DASHBOARD_WIDGET_PADDING_H = 10;

	onInitialize() {
		this.gauge = null;
		this.gauge_link = document.createElement('a');
	}

	onResize() {
		if (this._state === WIDGET_STATE_ACTIVE && this.gauge !== null) {
			this.gauge.setSize(super._getContentsSize());
		}
	}

	promiseReady() {
		const readiness = [super.promiseReady()];

		if (this.gauge !== null) {
			readiness.push(this.gauge.promiseRendered());
		}

		return Promise.all(readiness);
	}

	getUpdateRequestData() {
		return {
			...super.getUpdateRequestData(),
			with_config: (this.gauge === null || this.isFieldsReferredDataUpdated()) ? 1 : undefined
		};
	}

	setContents(response) {
		if (this.isFieldsReferredDataUpdated()) {
			this.clearContents();
		}

		if ('body' in response) {
			if (this.gauge !== null) {
				this.clearContents();
			}

			this._body.innerHTML = response.body;

			return;
		}

		this.gauge_link.href = response.url;

		const value_data = {
			value: response.value,
			value_text: response.value_text || null,
			units_text: response.units_text || null
		};

		if (this.gauge !== null) {
			this.gauge.setValue(value_data);

			return;
		}

		this._body.innerHTML = '';
		this._body.appendChild(this.gauge_link);

		const padding = {
			vertical: CWidgetGauge.ZBX_STYLE_DASHBOARD_WIDGET_PADDING_V,
			horizontal: CWidgetGauge.ZBX_STYLE_DASHBOARD_WIDGET_PADDING_H
		};

		this.gauge = new CSVGGauge(this.gauge_link, padding, response.config);
		this.gauge.setSize(super._getContentsSize());
		this.gauge.setValue(value_data);
	}

	onClearContents() {
		if (this.gauge !== null) {
			this.gauge.destroy();
			this.gauge = null;
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
			disabled: this.gauge === null,
			clickCallback: () => {
				downloadSvgImage(this.gauge.getSVGElement(), 'image.png');
			}
		});

		return menu;
	}

	hasPadding() {
		return false;
	}
}
