/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

		this._has_contents = false;
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

	_getUpdateRequestData() {
		return {
			...super._getUpdateRequestData(),
			from: this._time_period.from,
			to: this._time_period.to
		};
	}

	_processUpdateResponse(response) {
		this._destroyGraph();

		super._processUpdateResponse(response);

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

	_initGraph(options) {
		this._svg_options = options;
		this._svg = this._content_body.querySelector('svg');
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

	_destroyGraph() {
		if (this._has_contents) {
			this._deactivateGraph();
			this._svg.remove();
		}
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
				downloadSvgImage(this._svg, 'graph.png');
			}
		});

		return menu;
	}
}
