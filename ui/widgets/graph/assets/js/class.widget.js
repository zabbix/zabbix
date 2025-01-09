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


class CWidgetGraph extends CWidget {

	onInitialize() {
		this._is_graph_mode = false;
	}

	onStart() {
		this.events_handlers = {
			rangeUpdate: (e) => {
				if (this._is_graph_mode && this.getState() === WIDGET_STATE_ACTIVE) {
					const time_period = e.detail;

					timeControl.objectUpdate.call(timeControl.objectList[`graph_${this._unique_id}`], time_period);
					timeControl.refreshObject(`graph_${this._unique_id}`);

					this.feedback({time_period});
					this.broadcast({
						[CWidgetsData.DATA_TYPE_TIME_PERIOD]: time_period
					});
				}
			}
		};
	}

	onActivate() {
		if (this._is_graph_mode) {
			this._activateGraph();
		}
	}

	onDeactivate() {
		if (this._is_graph_mode) {
			this._deactivateGraph();
		}
	}

	onResize() {
		if (this._is_graph_mode && this.getState() === WIDGET_STATE_ACTIVE) {
			const graph_size = this._getContentsSize();

			if (graph_size.width <= 0 || graph_size.height <= 0) {
				return;
			}

			const image = document.getElementById(`graph_${this._unique_id}`);

			if (!image.complete) {
				image.addEventListener('load', () => this.resize(), {once: true});

				return;
			}

			timeControl.objectList[`graph_${this._unique_id}`].objDims.width = graph_size.width;
			timeControl.objectList[`graph_${this._unique_id}`].objDims.graphHeight = graph_size.height;

			const image_curl = new Curl(image.src);

			image_curl.setArgument('width', graph_size.width);
			image_curl.setArgument('height', graph_size.height);
			image_curl.setArgument('_', (new Date).getTime().toString(34));
			image.src = image_curl.getUrl();
		}
	}

	onEdit() {
		if (this._is_graph_mode && this._graph_url !== null) {
			this._flickerfreescreen_container.href = 'javascript:void(0)';
			this._flickerfreescreen_container.setAttribute('role', 'button');
		}
	}

	onFeedback({type, value}) {
		if (type === CWidgetsData.DATA_TYPE_TIME_PERIOD && this.getFieldsReferredData().has('time_period')) {
			this._startUpdating();

			this.feedback({time_period: value});

			return true;
		}

		return false;
	}

	promiseUpdate() {
		const time_period = this.getFieldsData().time_period;

		if (!this.hasBroadcast(CWidgetsData.DATA_TYPE_TIME_PERIOD) || this.isFieldsReferredDataUpdated('time_period')) {
			this.broadcast({
				[CWidgetsData.DATA_TYPE_TIME_PERIOD]: time_period
			});
		}

		if (this.isFieldsReferredDataUpdated()) {
			this.clearContents();

			return super.promiseUpdate();
		}

		if (this._is_graph_mode) {
			if (this.isFieldsReferredDataUpdated()) {
				this.clearContents();

				return super.promiseUpdate();
			}

			timeControl.objectUpdate.call(timeControl.objectList[`graph_${this._unique_id}`], time_period);
			timeControl.refreshObject(`graph_${this._unique_id}`);

			return Promise.resolve();
		}

		return super.promiseUpdate();
	}

	promiseReady() {
		return new Promise((resolve) => {
			$(this._target).on('load.image', () => resolve(super.promiseReady()));
		});
	}

	getUpdateRequestData() {
		let has_custom_time_period = true;

		if (this.getFieldsReferredData().has('time_period')) {
			const descriptor = this.getFieldsReferredData().get('time_period').descriptor;

			if (descriptor !== null && descriptor.sender_type === 'widget'
					&& descriptor.widget_type === 'graphprototype') {
				const graph_prototype_widget = ZABBIX.Dashboard
					.getDashboardPage(this._dashboard_page.unique_id)
					.getWidget(descriptor.sender_unique_id);

				has_custom_time_period = graph_prototype_widget.hasCustomTimePeriod();
			}
			else {
				has_custom_time_period = false;
			}
		}

		return {
			...super.getUpdateRequestData(),
			has_custom_time_period: has_custom_time_period ? 1 : undefined
		}
	}

	setContents(response) {
		super.setContents(response);

		if (!this._is_graph_mode && 'async_data' in response) {
			this._is_graph_mode = true;

			this._graph_url = response.async_data.graph_url;

			this._flickerfreescreen = this._body.querySelector('.flickerfreescreen');
			this._flickerfreescreen.id = `flickerfreescreen_graph_${this._unique_id}`;

			this._flickerfreescreen_container = this._flickerfreescreen.querySelector('.dashboard-widget-graph-link');
			this._flickerfreescreen_container.id = `graph_container_${this._unique_id}`;

			if (this._graph_url !== null && this.isEditMode()) {
				this._flickerfreescreen_container.href = 'javascript:void(0)';
				this._flickerfreescreen_container.setAttribute('role', 'button');
			}

			this._time_control_data = {
				...response.async_data.time_control_data,
				id: `graph_${this._unique_id}`,
				containerid: `graph_container_${this._unique_id}`
			};

			this._flickerfreescreen_data = {
				...response.async_data.flickerfreescreen_data,
				id: `graph_${this._unique_id}`
			};

			this._activateGraph();
		}
	}

	_activateGraph() {
		const time_period = {...this.getFieldsData().time_period};

		if (this._graph_url !== null) {
			const curl = new Curl(this._graph_url);

			curl.setArgument('from', time_period.from);
			curl.setArgument('to', time_period.to);

			this._flickerfreescreen_container.href = curl.getUrl();
		}

		const graph_size = this._getContentsSize();

		this._time_control_data.objDims.width = graph_size.width;
		this._time_control_data.objDims.graphHeight = graph_size.height;

		const curl = new Curl(this._time_control_data.src);

		curl.setArgument('from', time_period.from);
		curl.setArgument('to', time_period.to);

		this._time_control_data.src = curl.getUrl();

		this._flickerfreescreen_data.timeline = time_period;

		timeControl.addObject(`graph_${this._unique_id}`, time_period, this._time_control_data);
		timeControl.processObjects();

		flickerfreeScreen.add(this._flickerfreescreen_data);

		this._flickerfreescreen_container.addEventListener('rangeupdate', this.events_handlers.rangeUpdate);
	}

	_deactivateGraph() {
		timeControl.removeObject(`graph_${this._unique_id}`);

		flickerfreeScreen.remove(this._flickerfreescreen_data);

		this._flickerfreescreen_container.removeEventListener('rangeupdate', this.events_handlers.rangeUpdate);
	}

	onClearContents() {
		if (this._is_graph_mode) {
			this._is_graph_mode = false;
			this._deactivateGraph();
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
			disabled: !this._is_graph_mode,
			clickCallback: () => {
				downloadPngImage(this._body.querySelector('img'), 'image.png');
			}
		});

		return menu;
	}

	hasPadding() {
		return true;
	}
}
