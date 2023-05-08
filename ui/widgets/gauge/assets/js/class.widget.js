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


// Must be synced with PHP.
const ZBX_STYLE_SVG_GAUGE_CONTAINER = 'svg-gauge-container';

class CWidgetGauge extends CWidget {
	_init() {
		super._init();

		this._initial_load = true;
		this._has_contents = false;
		this._svg = false;
		this.gauge = null;
	}

	start() {
		super.start();

		const container = this._target.querySelector('.' + ZBX_STYLE_SVG_GAUGE_CONTAINER);
	}

	getUpdateRequestData() {
		return {
			...super.getUpdateRequestData(),
			initial_load: this._initial_load ? 1 : 0
		};
	}

	resize() {
		super.resize();

		if (this.gauge !== null) {
			const container = this._target.querySelector('.' + ZBX_STYLE_SVG_GAUGE_CONTAINER);
			const padding = this.#getContainerPadding(container);
			const width = this._getContentSize().content_width - padding.left - padding.right;
			const height = this._getContentSize().content_height - padding.top - padding.bottom;

			this.gauge.resize(width, height);
		}
	}

	#getContainerPadding(container) {
		const top = parseInt(window.getComputedStyle(container).getPropertyValue('padding-top'));
		const right = parseInt(window.getComputedStyle(container).getPropertyValue('padding-right'));
		const left = parseInt(window.getComputedStyle(container).getPropertyValue('padding-left'));
		const bottom = parseInt( window.getComputedStyle(container).getPropertyValue('padding-bottom'));

		return {top, right, left, bottom}
	}

	processUpdateResponse(response) {
		// If there will a necesity for tooltips due to threshold overhaul, we probably need to stop all widget activity first

		if (response.gauge_data !== undefined) {
			this._has_contents = true;

			if (this._initial_load) {
				super.processUpdateResponse(response);

				const container = this._target.querySelector('.' + ZBX_STYLE_SVG_GAUGE_CONTAINER);
				const padding = this.#getContainerPadding(container);
				const width = response.gauge_data.contents_width - padding.left - padding.right;
				const height = response.gauge_data.contents_height - padding.top - padding.bottom;

				this.gauge = new CSVGGauge({
					container: container,
					theme: response.gauge_data.user.theme,
					canvas: {
						width: width,
						height: height
					},
				}, response.gauge_data.data);

				this._svg = this._body.querySelector('svg');
			}
			else {
				this.gauge.update(response.gauge_data.data);
			}

			if (response.gauge_data.error_msg !== undefined) {
				this._content_body.innerHTML = response.gauge_data.error_msg;
			}
		}
		else {
			this._has_contents = false;
		}

		this._initial_load = false;
	}

	getActionsContextMenu({can_paste_widget}) {
		const menu = super.getActionsContextMenu({can_paste_widget});

		if (this._is_edit_mode) {
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
		return false;
	}
}
