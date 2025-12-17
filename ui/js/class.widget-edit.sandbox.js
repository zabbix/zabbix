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


/**
 * Widget editor component for maintaining sandbox widget.
 */
class CWidgetEditSandbox {

	#dashboard;

	#widget_defaults;
	#max_columns;
	#max_rows;

	#dashboard_page;
	#widget = null;
	#widget_original = null;
	#unique_id = null;
	#keep_size;

	#listeners = new Set();

	#abort_controller;

	constructor({dashboard}) {
		this.#dashboard = dashboard;

		this.#widget_defaults = dashboard.getWidgetDefaults();
		this.#max_columns = dashboard.getMaxColumns();
		this.#max_rows = dashboard.getMaxRows();
	}

	promiseInit({dashboard_page, widget = null, type = null, pos = null}) {
		return new Promise(resolve => {
			this.#dashboard_page = dashboard_page;

			this.#activate();

			if (widget !== null) {
				this.#widget = widget;
				this.#widget_original = widget;
				this.#unique_id = widget.getUniqueId();
				this.#keep_size = true;

				this.#collectListeners();

				this.#dashboard_page
					.promiseScrollIntoView(this.#widget.getPos())
					.then(resolve);
			}
			else {
				if (pos === null) {
					pos = this.#findPosForNewWidget(type);

					if (pos === null) {
						throw t('Cannot add widget: not enough free space on the dashboard.');
					}
				}

				this.#keep_size = 'width' in pos && 'height' in pos;

				if (!this.#keep_size) {
					pos = this.#fixSizeForType(pos, type);
				}

				this.#dashboard_page
					.promiseScrollIntoView(pos)
					.then(() => {
						this.#widget = this.#dashboard.addCreatePlaceholderWidget(this.#dashboard_page, {type, pos});

						resolve();
					});
			}
		});
	}

	update({type, name, view_mode, fields, is_configured}) {
		let pos = this.#widget.getPos();

		if (!this.#keep_size) {
			pos = this.#fixSizeForType(pos, type);
		}

		const widget_data = {
			type,
			name,
			view_mode,
			fields,
			widgetid: this.#widget_original !== null && type === this.#widget_original.getType()
				? this.#widget_original.getWidgetId()
				: null,
			pos,
			is_new: false,
			rf_rate: 0,
			unique_id: this.#unique_id,
			is_configured
		};

		const widget = this.#widget;

		if (widget === this.#widget_original) {
			this.#dashboard_page.deleteWidget(widget, {is_batch_mode: true});

			this.#widget = this.#dashboard.addWidgetFromData(this.#dashboard_page, widget_data);
		}
		else {
			this.#widget = this.#dashboard.replaceWidgetFromData(this.#dashboard_page, widget, widget_data);
		}

		this.#widget.enterWidgetEditing(true);

		if (widget_data.type !== widget.getType()) {
			this.#correctListeners();
		}
	}

	apply() {
		this.#deactivate();
	}

	cancel() {
		if (this.#widget_original === null) {
			this.#dashboard_page.deleteWidget(this.#widget);
		}
		else if (this.#widget_original !== this.#widget) {
			const widget = this.#widget;

			this.update({
				type: this.#widget_original.getType(),
				name: this.#widget_original.getName(),
				view_mode: this.#widget_original.getViewMode(),
				fields: this.#widget_original.getFields(),
				is_configured: !(this.#widget_original instanceof CWidgetMisconfigured)
					|| this.#widget_original.getMessageType() !== CWidgetMisconfigured.MESSAGE_TYPE_NOT_CONFIGURED
			});

			if (this.#widget_original.getType() !== widget.getType()) {
				this.#correctListeners();
			}
		}

		this.#deactivate();
	}

	getWidget() {
		return this.#widget;
	}

	#fixSizeForType(pos, type) {
		const result_pos = {
			...pos,
			...this.#widget_defaults[type].size
		};

		result_pos.width = Math.min(result_pos.width, this.#max_columns - result_pos.x);
		result_pos.height = Math.min(result_pos.height, this.#max_rows - result_pos.y);

		return this.#dashboard_page.accommodatePos(result_pos, {
			except_widgets: this.#widget !== null ? new Set([this.#widget]) : null
		});
	}

	#findPosForNewWidget(type) {
		const default_size = this.#widget_defaults[type].size;

		let pos_best = null;
		let pos_best_value = null;

		for (const pos of this.#dashboard_page.findFreePosAll()) {
			let pos_value = 0;

			for (const pos_size of pos.sizes) {
				pos_value = Math.max(pos_value,
					Math.min(pos_size.width, default_size.width) / default_size.width
						* Math.min(pos_size.height, default_size.height) / default_size.height
				);
			}

			if (pos_best === null || pos_value > pos_best_value
					|| (pos_value === pos_best_value && pos.y < pos_best.y)
					|| (pos_value === pos_best_value && pos.y === pos_best.y && pos.x < pos_best.x)) {
				pos_best = {
					x: pos.x,
					y: pos.y
				};
				pos_best_value = pos_value;
			}
		}

		return pos_best;
	}

	#collectListeners() {
		const broadcaster_fields = this.#widget.getFields();

		if (!('reference' in broadcaster_fields)) {
			return;
		}

		for (const widget of this.#dashboard_page.getWidgets()) {
			const fields = JSON.parse(JSON.stringify(widget.getFields()));

			const accessors = [];

			for (const accessor of CWidgetBase.getFieldsReferencesAccessors(fields).values()) {
				if (accessor.getTypedReference() === '') {
					continue;
				}

				const {reference, type} = CWidgetBase.parseTypedReference(accessor.getTypedReference());

				if (reference === broadcaster_fields.reference) {
					accessors.push({type, accessor});
				}
			}

			if (accessors.length > 0) {
				this.#listeners.add({widget, fields, accessors});
			}
		}
	}

	#correctListeners() {
		const broadcaster_reference = this.#widget.getFields().reference;
		const broadcast_types = this.#widget.getBroadcastTypes();

		for (const listener of this.#listeners) {
			for (const {type, accessor} of listener.accessors) {
				accessor.setTypedReference(
					CWidgetBase.createTypedReference(
						broadcast_types.includes(type)
							? {reference: broadcaster_reference, type}
							: {reference: ''}
					)
				);
			}

			listener.widget = this.#dashboard.replaceWidgetFromData(this.#dashboard_page, listener.widget, {
				...listener.widget.getDataCopy({is_single_copy: false}),
				fields: JSON.parse(JSON.stringify(listener.fields)),
				widgetid: listener.widget.getWidgetId(),
				is_new: false,
				is_configured: !(listener.widget instanceof CWidgetMisconfigured)
					|| listener.widget.getMessageType() !== CWidgetMisconfigured.MESSAGE_TYPE_NOT_CONFIGURED
			});
		}
	}

	#activate() {
		this.#abort_controller = new AbortController();

		this.#dashboard_page.on(DASHBOARD_PAGE_EVENT_WIDGET_RESIZE, e => this.#onResize(e),
			{signal: this.#abort_controller.signal}
		);
	}

	#deactivate() {
		this.#abort_controller.abort();
	}

	#onResize(e) {
		if (e.detail.widget === this.#widget) {
			this.#keep_size = true;
		}
	}
}
