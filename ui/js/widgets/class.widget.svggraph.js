/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CWidgetSvgGraph extends CWidget {

	_init() {
		super._init();

		this._is_svg_loaded = false;
		this._svg_options = {};
	}

	_doActivate() {
		super._doActivate();

		this._activateGraph();
	}

	_doDeactivate() {
		super._doDeactivate();

		this._deactivateGraph();
	}

	resize() {
		super.resize();

		if (this._state === WIDGET_STATE_ACTIVE) {
			this._startUpdating();
		}
	}

	setEditMode() {
		super.setEditMode();

		this._deactivateGraph();
	}

	setTimePeriod(time_period) {
		super.setTimePeriod(time_period);

		if (this._state === WIDGET_STATE_ACTIVE) {
			this._startUpdating();
		}
	}

	_processUpdateResponse(response) {
		this._destroyGraph();
		super._processUpdateResponse(response);

		if (response.svg_options !== undefined) {
			this._is_svg_loaded = true;

			this._initGraph({
				sbox: false,
				show_problems: true,
				hint_max_rows: 20,
				min_period: 60,
				...response.svg_options.data
			});
		}
		else {
			this._is_svg_loaded = false;
		}
	}

	_initGraph(options) {
		this._svg_options = options;
		this._$svg = $('svg', this._content_body);
		this._$svg.svggraph(this);
		this._activateGraph();
	}

	_activateGraph() {
		if (this._is_svg_loaded) {
			this._$svg.svggraph('activate');
		}
	}

	_deactivateGraph() {
		if (this._is_svg_loaded) {
			this._$svg.svggraph('deactivate');
		}
	}

	_destroyGraph() {
		if (this._is_svg_loaded) {
			this._deactivateGraph();
			this._$svg.remove();
		}
	}
}
