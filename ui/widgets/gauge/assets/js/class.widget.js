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
		this.gauge = null;
	}

	start() {
		super.start();

		const container = this._target.querySelector('.' + ZBX_STYLE_SVG_GAUGE_CONTAINER);
	}

	_getUpdateRequestData() {
		return {
			...super._getUpdateRequestData(),
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

	_processUpdateResponse(response) {
		// If there will a necesity for tooltips due to threshold overhaul, we probably need to stop all widget activity first
		super._processUpdateResponse(response);
		this._initial_load = false;

		if (response.gauge_data !== undefined) {
			const container = this._target.querySelector('.' + ZBX_STYLE_SVG_GAUGE_CONTAINER);
			const padding = this.#getContainerPadding(container);
			const width = response.gauge_data.content_width - padding.left - padding.right;
			const height = response.gauge_data.content_height - padding.top - padding.bottom;

			this.gauge = new CSVGGauge({
				container: container,
				theme: response.gauge_data.user.theme,
				bg_color: '#' + response.gauge_data.bg_color,
				canvas: {
					width: width,
					height: height
				},
			}, response.gauge_data.data);

			if (response.gauge_data.error_msg !== undefined) {
				this._content_body.innerHTML = response.gauge_data.error_msg;
			}
		}
	}

	_hasPadding() {
		return false;
	}
}
