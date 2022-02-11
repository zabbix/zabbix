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


class CWidgetGraph extends CWidget {

	_init() {
		super._init();

		this._is_graph_mode = false;
	}

	_doActivate() {
		if (this._is_graph_mode) {
			this._activateGraph();
		}

		super._doActivate();
	}

	_doDeactivate() {
		if (this._is_graph_mode) {
			this._deactivateGraph();
		}

		super._doDeactivate();
	}

	resize() {
		super.resize();

		if (this._is_graph_mode && this.getState() === WIDGET_STATE_ACTIVE) {
			const graph_size = this._getGraphSize();

			if (graph_size.width <= 0 || graph_size.height <= 0) {
				return;
			}

			const image = document.getElementById('graph_' + this._unique_id);

			if (!image.complete) {
				image.addEventListener('load', () => this.resize(), {once: true});

				return;
			}

			timeControl.objectList['graph_' + this._unique_id].objDims.width = graph_size.width;
			timeControl.objectList['graph_' + this._unique_id].objDims.graphHeight = graph_size.height;

			const image_curl = new Curl(image.src, false);

			image_curl.setArgument('width', graph_size.width);
			image_curl.setArgument('height', graph_size.height);
			image_curl.setArgument('_', (new Date).getTime().toString(34));
			image.src = image_curl.getUrl();
		}
	}

	updateProperties({name, view_mode, fields, configuration}) {
		if (this._state === WIDGET_STATE_ACTIVE) {
			this._stopUpdating(true);
		}

		this._is_graph_mode = false;

		super.updateProperties({name, view_mode, fields, configuration});
	}

	setEditMode() {
		super.setEditMode();

		if (this._is_graph_mode && this._graph_url !== null) {
			this._flickerfreescreen_container.href = 'javascript:void(0)';
			this._flickerfreescreen_container.setAttribute('role', 'button');
		}
	}

	setDynamicHost(dynamic_hostid) {
		if (this._state === WIDGET_STATE_ACTIVE) {
			this._stopUpdating(true);
		}

		if (this._is_graph_mode) {
			this._is_graph_mode = false;
			this._deactivateGraph();
		}

		super.setDynamicHost(dynamic_hostid);
	}

	_promiseUpdate() {
		if (this._is_graph_mode) {
			timeControl.refreshObject('graph_' + this._unique_id);

			return Promise.resolve();
		}

		return super._promiseUpdate();
	}

	_processUpdateResponse(response) {
		super._processUpdateResponse(response);

		if (!this._is_graph_mode && response.async_data !== undefined) {
			this._is_graph_mode = true;

			this._graph_url = response.async_data.graph_url;

			this._flickerfreescreen = this._content_body.querySelector('.flickerfreescreen');
			this._flickerfreescreen.id = 'flickerfreescreen_graph_' + this._unique_id;

			this._flickerfreescreen_container = this._flickerfreescreen.querySelector('.dashboard-widget-graph-link');
			this._flickerfreescreen_container.id = 'graph_container_' + this._unique_id;

			if (this._graph_url !== null && this.isEditMode()) {
				this._flickerfreescreen_container.href = 'javascript:void(0)';
				this._flickerfreescreen_container.setAttribute('role', 'button');
			}

			this._time_control_data = {
				...response.async_data.time_control_data,
				id: 'graph_' + this._unique_id,
				containerid: 'graph_container_' + this._unique_id
			};

			this._flickerfreescreen_data = {
				...response.async_data.flickerfreescreen_data,
				id: 'graph_' + this._unique_id
			};

			this._activateGraph();
		}
	}

	_activateGraph() {
		if (this._graph_url !== null) {
			const curl = new Curl(this._graph_url, false);

			curl.setArgument('from', this._time_period.from);
			curl.setArgument('to', this._time_period.to);

			this._flickerfreescreen_container.href = curl.getUrl();
		}

		const graph_size = this._getGraphSize();

		this._time_control_data.objDims.width = graph_size.width;
		this._time_control_data.objDims.graphHeight = graph_size.height;

		const curl = new Curl(this._time_control_data.src, false);

		curl.setArgument('from', this._time_period.from);
		curl.setArgument('to', this._time_period.to);

		this._time_control_data.src = curl.getUrl();

		this._flickerfreescreen_data.timeline = this._time_period;

		timeControl.addObject('graph_' + this._unique_id, this._time_period, this._time_control_data);
		timeControl.processObjects();

		flickerfreeScreen.add(this._flickerfreescreen_data);
	}

	_deactivateGraph() {
		timeControl.removeObject('graph_' + this._unique_id);
		flickerfreeScreen.remove(this._flickerfreescreen_data);
	}

	_getGraphSize() {
		const content = this._content_body;
		const style = getComputedStyle(content);

		return {
			width: Math.floor(content.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight)),
			height: Math.floor(content.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom))
		};
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
			disabled: !this._is_graph_mode,
			clickCallback: () => {
				downloadPngImage(this._content_body.querySelector('img'), 'graph.png');
			}
		});

		return menu;
	}
}
