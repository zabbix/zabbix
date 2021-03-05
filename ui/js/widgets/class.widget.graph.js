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


class CWidgetGraph extends CWidget {

	_init() {
		super._init();

		this._is_async = false;
	}

	_doActivate() {
		if (this._is_async) {
			this._activateGraph();
		}

		super._doActivate();
	}

	_doDeactivate() {
		if (this._is_async) {
			this._deactivateGraph();
		}

		super._doDeactivate();
	}

	resize() {
		super.resize();

		if (this._is_async && this.getState() === WIDGET_STATE_ACTIVE) {
			const image = document.getElementById('graph_' + this._uniqueid);

			if (image.src === '') {
				image.addEventListener('load', () => this.resize(), {once: true});

				return;
			}

			const graph_size = this._getGraphSize();

			timeControl.objectList['graph_' + this._uniqueid].objDims.width = graph_size.width;
			timeControl.objectList['graph_' + this._uniqueid].objDims.graphHeight = graph_size.height;

			const image_curl = new Curl(image.src, false);

			image_curl.setArgument('width', graph_size.width);
			image_curl.setArgument('height', graph_size.height);
			image_curl.setArgument('_', (new Date).getTime().toString(34));
			image.src = image_curl.getUrl();
		}
	}

	setEditMode() {
		super.setEditMode();

		if (this._is_async && this._graph_url !== null) {
			this._flickerfreescreen_container.href = 'javascript:void(0)';
			this._flickerfreescreen_container.setAttribute('role', 'button');
		}
	}

	_promiseUpdate() {
		if (this._is_async) {
			timeControl.refreshObject('graph_' + this._uniqueid);

			return Promise.resolve();
		}

		return super._promiseUpdate();
	}

	_processUpdateResponse(response) {
		super._processUpdateResponse(response);

		if (!this._is_async && response.async_data !== undefined) {
			this._is_async = true;

			this._graph_url = response.async_data.graph_url;

			this._flickerfreescreen = this._$content_body[0].firstElementChild;
			this._flickerfreescreen.id = 'flickerfreescreen_graph_' + this._uniqueid;

			this._flickerfreescreen_container = this._flickerfreescreen.firstElementChild;
			this._flickerfreescreen_container.id = 'graph_container_' + this._uniqueid;

			if (this._graph_url !== null && this.isEditMode()) {
				this._flickerfreescreen_container.href = 'javascript:void(0)';
				this._flickerfreescreen_container.setAttribute('role', 'button');
			}

			this._time_control_data = {
				...response.async_data.time_control_data,
				id: 'graph_' + this._uniqueid,
				containerid: 'graph_container_' + this._uniqueid
			};

			this._flickerfreescreen_data = {
				...response.async_data.flickerfreescreen_data,
				id: 'graph_' + this._uniqueid
			};

			this._activateGraph();
		}
	}

	_activateGraph() {
		const time_selector = ZABBIX.Dashboard.getTimeSelector();

		if (this._graph_url !== null) {
			const curl = new Curl(this._graph_url, false);

			curl.setArgument('from', time_selector.from);
			curl.setArgument('to', time_selector.to);

			this._flickerfreescreen_container.href = curl.getUrl();
		}

		const graph_size = this._getGraphSize();

		this._time_control_data.objDims.width = graph_size.width;
		this._time_control_data.objDims.graphHeight = graph_size.height;

		const curl = new Curl(this._time_control_data.src, false);

		curl.setArgument('from', time_selector.from);
		curl.setArgument('to', time_selector.to);

		this._time_control_data.src = curl.getUrl();

		this._flickerfreescreen_data.timeline = time_selector;

		timeControl.addObject('graph_' + this._uniqueid, time_selector, this._time_control_data);
		timeControl.processObjects();

		flickerfreeScreen.add(this._flickerfreescreen_data);
	}

	_deactivateGraph() {
		timeControl.removeObject('graph_' + this._uniqueid);
		flickerfreeScreen.remove(this._flickerfreescreen_data);
	}

	_getGraphSize() {
		const content = this._$content_body[0];
		const style = getComputedStyle(content);

		return {
			width: Math.floor(content.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight)),
			height: Math.floor(content.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom))
		};
	}
}
