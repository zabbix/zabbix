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


class CWidgetGauge extends CWidget {

	onInitialize() {
		this.gauge_container = null;
		this.gauge = null;
	}

	onStart() {
		this.gauge_container = document.createElement('div');
		this._body.appendChild(this.gauge_container);
	}

	onResize() {
		if (this._state === WIDGET_STATE_ACTIVE && this.gauge !== null) {
			this.gauge.setSize(this.#getGaugeContainerSize());
		}
	}

	updateProperties({name, view_mode, fields}) {
		if (this.gauge !== null) {
			this.gauge.destroy();
			this.gauge = null;
		}

		super.updateProperties({name, view_mode, fields});
	}

	getUpdateRequestData() {
		return {
			...super.getUpdateRequestData(),
			with_config: this.gauge === null ? 1 : undefined
		};
	}

	setContents(response) {
		if (this.gauge === null) {
			if (!('config' in response)) {
				throw new Error('Unexpected server error.');
			}

			this.gauge = new CSVGGauge(this.gauge_container, response.config);
			this.gauge.setSize(this.#getGaugeContainerSize());
		}

		this.gauge.setValue({
			value: response.value,
			value_text: response.value_text,
			units_text: response.units_text
		});
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
			disabled: !this._has_contents,
			clickCallback: () => {
				downloadSvgImage(this._svg, 'gauge.png');
			}
		});

		return menu;
	}

	hasPadding() {
		return true;
	}

	#getGaugeContainerSize() {
		const computed_style = getComputedStyle(this.gauge_container);

		const width = Math.floor(
			parseFloat(computed_style.width)
				- parseFloat(computed_style.paddingLeft) - parseFloat(computed_style.paddingRight)
				- parseFloat(computed_style.borderLeftWidth) - parseFloat(computed_style.borderRightWidth)
		);

		const height = Math.floor(
			parseFloat(computed_style.height)
				- parseFloat(computed_style.paddingTop) - parseFloat(computed_style.paddingBottom)
				- parseFloat(computed_style.borderTopWidth) - parseFloat(computed_style.borderBottomWidth)
		);

		return {width, height};
	}
}
