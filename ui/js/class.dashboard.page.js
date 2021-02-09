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


const ZBX_WIDGET_VIEW_MODE_NORMAL        = 0;
const ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER = 1;

class CDashboardPage {

	constructor($target, data) {
		this._$target = $target;

		this._data = {
			dashboard: {
				templateid: null,
				dashboardid: null,
				dynamic_hostid: null,
				...data.dashboard
			},
			options: {
				...data.options,
				'rows': 0,
				'updated': false,
				'widget-width': 100 / data.options['max-columns']
			},
			widget_defaults: {},
			widgets: [],
			triggers: {},
			widget_relation_submissions: [],
			widget_relations: {
				relations: [],
				tasks: {}
			},
			data_buffer: [],
			minimalHeight: this._calculateGridMinHeight(),
			storage: ZABBIX.namespace('instances.localStorage')
		};

		const add_new_widget_callback = (e) => {
			if (!this.isEditMode()) {
				this.editDashboard();
			}
			this.addNewWidget(e.target);

			return false;
		};

		// A single placeholder used for prompting to add a new widget.
		this._data.new_widget_placeholder = new newWidgetPlaceholder(this._data.options['widget-width'],
			this._data.options['widget-height'], add_new_widget_callback
		);

		// This placeholder is used while positioning/resizing widgets.
		this._data.placeholder = $('<div>', {'class': 'dashbrd-grid-widget-placeholder'}).append($('<div>')).hide();

		this._$target.append(this._data.new_widget_placeholder.getObject(), this._data.placeholder);

		if (this._data.options['editable']) {
			if (this._data.options['kioskmode']) {
				this._data.new_widget_placeholder.setState(this._data.new_widget_placeholder.STATE_KIOSK_MODE);
			}
			else {
				this._data.new_widget_placeholder.setState(this._data.new_widget_placeholder.STATE_ADD_NEW);
			}
		}
		else {
			this._data.new_widget_placeholder.setState(this._data.new_widget_placeholder.STATE_READONLY);
		}

		this._data.new_widget_placeholder.showAtDefaultPosition();

		let resize_timeout;

		if (this._data.options.edit_mode) {
			this._doAction('onEditStart');
			this._editDashboard();
		}

		$(window).on('resize', () => {
			clearTimeout(resize_timeout);
			resize_timeout = setTimeout(() => {
				this._data.widgets.forEach((widget) => {
					this._resizeWidget(widget);
				});
			}, 200);

			// Recalculate dashboard container minimal required height.
			this._data.minimalHeight = this._calculateGridMinHeight();
			this._data.cell_width = this._getCurrentCellWidth();
			this._data.new_widget_placeholder.resize();
			this._resizeDashboardGrid();
		});

		['onWidgetAdd', 'onWidgetDelete', 'onWidgetPosition'].forEach((action) => {
			this.addAction(action, this._hideMessageExhausted);
		});
	}

	getDashboardData() {
		return {...this._data.dashboard};
	}

	getWidgets() {
		return this._data.widgets;
	}

	getOptions() {
		return this._data.options;
	}

	/**
	 * Get copied widget (if compatible with the current dashboard) or null otherwise.
	 *
	 * @returns {object|null}  Copied widget or null.
	 */
	getCopiedWidget() {
		const copied_widget = this._data.storage.readKey('dashboard.copied_widget', null);

		if (copied_widget !== null && copied_widget.dashboard.templateid === this._data.dashboard.templateid) {
			return copied_widget.widget;
		}
		else {
			return null;
		}
	}

	updateDynamicHost(hostid) {
		this._data.dashboard.dynamic_hostid = hostid;

		for (const w of this._data.widgets) {
			if (w.fields.dynamic == 1) {
				this._updateWidgetContent(w);

				const widget_actions = $('.btn-widget-action', w.content_header).data('menu-popup').data;

				if (this._data.dashboard.dynamic_hostid !== null) {
					widget_actions.dynamic_hostid = this._data.dashboard.dynamic_hostid;
				}
				else {
					delete widget_actions.dynamic_hostid;
				}
			}
		}
	}

	setWidgetDefaults(defaults) {
		this._data.widget_defaults = {...this._data.widget_defaults, ...defaults};
	}

	addWidgets(widgets) {
		for (const w of widgets) {
			this.addWidget(w);
		}

		for (const w of this._data.widgets) {
			this._updateWidgetContent(w);
		}
	}

	addWidget(widget) {
		// Replace empty arrays (or anything non-object) with empty objects.
		if (typeof widget.fields !== 'object') {
			widget.fields = {};
		}

		if (typeof widget.configuration !== 'object') {
			widget.configuration = {};
		}

		widget = {
			'widgetid': '',
			'type': '',
			'header': '',
			'view_mode': ZBX_WIDGET_VIEW_MODE_NORMAL,
			'pos': {
				'x': 0,
				'y': 0,
				'width': 1,
				'height': 1
			},
			'rf_rate': 0,
			'preloader_timeout': 10000,	// in milliseconds
			'update_paused': false,
			'initial_load': true,
			'ready': false,
			'storage': {},
			...widget,
			'parent': false
		};

		if (typeof widget.new_widget === 'undefined') {
			widget.new_widget = !widget.widgetid.length;
		}

		let widget_local = JSON.parse(JSON.stringify(widget));

		const widget_type_defaults = this._data.widget_defaults[widget_local.type];

		widget_local.iterator = widget_type_defaults.iterator;

		if (widget_local.iterator) {
			widget_local = {
				...widget_local,
				'page': 1,
				'page_count': 1,
				'children': [],
				'update_pending': false
			};
		}

		widget_local.uniqueid = this._generateUniqueId();
		widget_local.div = this._makeWidgetDiv(widget_local);
		widget_local.div.data('widget-index', this._data.widgets.length);

		this._data.widgets.push(widget_local);
		this._$target.append(widget_local.div);

		this._setDivPosition(widget_local.div, widget_local.pos);

		if (this._data.pos_action !== 'updateWidgetConfig') {
			this._checkWidgetOverlap();
			this._resizeDashboardGrid();
		}

		this._showPreloader(widget_local);
		this._data.new_widget_placeholder.hide();

		if (widget_local.iterator) {
			// Placeholders will be shown while the iterator will be loading.
			this._addIteratorPlaceholders(widget_local,
				this._numIteratorColumns(widget_local) * this._numIteratorRows(widget_local)
			);
			this._alignIteratorContents(widget_local, widget_local.pos);

			this.addAction('onResizeEnd', this._onIteratorResizeEnd, widget_local.uniqueid, {
				trigger_name: `onIteratorResizeEnd_${widget_local.uniqueid}`,
				parameters: [this._data, widget_local]
			});
		}

		if (this._data.options.edit_mode) {
			widget_local.rf_rate = 0;
			this._setWidgetModeEdit(widget_local);
		}

		this._doAction('onWidgetAdd', widget_local);
	}

	addNewWidget(trigger_element, pos) {
		/*
		 * Unset if dimension width/height is equal to size of placeholder.
		 * Widget default size will be used.
		 */
		if (pos && pos.width === 2 && pos.height === 2) {
			delete pos.width;
			delete pos.height;
		}

		const widget = (pos && 'x' in pos && 'y' in pos) ? {pos: pos} : null;

		this._data.pos_action = 'addmodal';
		this._openConfigDialogue(widget, trigger_element);
	}

	setWidgetRefreshRate(widgetid, rf_rate) {
		for (const w of this._data.widgets) {
			if (w.widgetid === widgetid) {
				w.rf_rate = rf_rate;
				this._setUpdateWidgetContentTimer(w);
			}
		}
	}

	refreshWidget(widgetid) {
		for (const w of this._data.widgets) {
			if (w.widgetid === widgetid || w.uniqueid === widgetid) {
				this._updateWidgetContent(w);
			}
		}
	}

	/**
	 * Pause specific widget refresh.
	 */
	pauseWidgetRefresh(widgetid) {
		for (const w of this._data.widgets) {
			if (w.widgetid === widgetid || w.uniqueid === widgetid) {
				w.update_paused = true;
				break;
			}
		}
	}

	/**
	 * Unpause specific widget refresh.
	 */
	unpauseWidgetRefresh(widgetid) {
		for (const w of this._data.widgets) {
			if (w.widgetid === widgetid || w.uniqueid === widgetid) {
				w.update_paused = false;
				break;
			}
		}
	}

	setWidgetStorageValue(uniqueid, field, value) {
		for (const w of this._data.widgets) {
			if (w.uniqueid === uniqueid) {
				w.storage[field] = value;
			}
		}
	}

	editDashboard() {
		// Set before firing "onEditStart" for isEditMode to work correctly.
		this._data.options['edit_mode'] = true;

		this._doAction('onEditStart');
		this._editDashboard();

		// Event must not fire if the dashboard was initially loaded in edit mode.
		// TODO need to fix event trigger
		$.publish('dashboard.grid.editDashboard');
	}

	isDashboardUpdated() {
		return this._data.options.updated;
	}

	saveDashboard(callback) {
		this._doAction('beforeDashboardSave');
		callback(this._data.widgets);
	}

	/**
	 * After pressing "Edit" button on widget.
	 */
	editWidget(widget, trigger_element) {
		if (!this.isEditMode()) {
			this.editDashboard();
		}

		this._openConfigDialogue(widget, trigger_element);
	}

	/**
	 * Function to store copied widget into storage buffer.
	 *
	 * @param {object} widget  Widget object copied.
	 */
	copyWidget(widget) {
		this._doAction('onWidgetCopy', widget);

		this._data.storage.writeKey('dashboard.copied_widget', {
			dashboard: {
				templateid: this._data.dashboard.templateid
			},
			widget: {
				type: widget.type,
				pos: {
					width: widget.pos.width,
					height: widget.pos.height
				},
				header: widget.header,
				view_mode: widget.view_mode,
				rf_rate: widget.rf_rate,
				fields: widget.fields,
				configuration: widget.configuration
			}
		});

		$.publish('dashboard.grid.copyWidget');
	}

	/**
	 * Create new widget or replace existing widget in given position.
	 *
	 * @param {object} widget  (nullable) Widget to replace.
	 * @param {object} pos     (nullable) Position to paste new widget in.
	 */
	pasteWidget(widget, pos) {
		this._data.pos_action = 'paste';

		this._hideMessageExhausted();

		let new_widget = this.getCopiedWidget();

		// Regenerate reference field values.
		if ('reference' in new_widget.fields) {
			new_widget.fields['reference'] = this.makeReference();
		}

		// In case if selected space is 2x2 cells (represents simple click), use pasted widget size.
		if (widget === null && pos !== null && pos.width === 2 && pos.height === 2) {
			pos.width = new_widget.pos.width;
			pos.height = new_widget.pos.height;

			if (pos.x > this._data.options['max-columns'] - pos.width
					|| pos.y > this._data.options['max-rows'] - pos.height
					|| !this._isPosFree(pos)) {
				this._data.widgets.filter((w) => {
					return this._rectOverlap(w.pos, pos);
				}).forEach((w) => {
					if (pos.x + pos.width > w.pos.x && pos.x < w.pos.x) {
						pos.width = w.pos.x - pos.x;
					}
					else if (pos.y + pos.height > w.pos.y && pos.y < w.pos.y) {
						pos.height = w.pos.y - pos.y;
					}
				});
			}

			pos.width = Math.min(this._data.options['max-columns'] - pos.x, pos.width);
			pos.height = Math.min(this._data.options['max-rows'] - pos.y, pos.height);
		}

		// When no position is given, find first empty space. Use copied widget width and height.
		if (pos === null) {
			pos = this._findEmptyPosition({
				'width': new_widget.pos.width,
				'height': new_widget.pos.height
			});
			if (!pos) {
				this._showMessageExhausted();

				return;
			}

			new_widget.pos.x = pos.x;
			new_widget.pos.y = pos.y;
		}
		else {
			new_widget = {...new_widget, pos: pos};
		}

		const dashboard_busy_item = {};

		this._setDashboardBusy('pasteWidget', dashboard_busy_item);

		// Remove old widget.
		if (widget !== null) {
			this._removeWidget(widget);
		}

		this._promiseScrollIntoView(pos)
			.then(() => {
				this.addWidget(new_widget);
				new_widget = this._data.widgets.slice(-1)[0];

				// Restrict loading content prior to sanitizing widget fields.
				new_widget.update_paused = true;

				this._setWidgetModeEdit(new_widget);
				this._disableWidgetControls(new_widget);

				const url = new Curl('zabbix.php');
				url.setArgument('action', 'dashboard.widget.sanitize');

				return $.ajax({
					url: url.getUrl(),
					method: 'POST',
					dataType: 'json',
					data: {
						fields: JSON.stringify(new_widget.fields),
						type: new_widget.type
					}
				});
			})
			.then((response) => {
				if ('errors' in response) {
					return $.Deferred().reject();
				}

				new_widget.fields = response.fields;
				new_widget.update_paused = false;
				this._enableWidgetControls(new_widget);
				this._updateWidgetContent(new_widget);

				this._data.options['updated'] = true;
			})
			.fail(() => {
				this._deleteWidget(new_widget);
			})
			.always(() => {
				// Mark dashboard as updated.
				this._data.options['updated'] = true;
				this._data.pos_action = '';

				this._clearDashboardBusy('pasteWidget', dashboard_busy_item);
			});
	}

	/**
	 * After pressing "delete" button on widget.
	 */
	deleteWidget(widget) {
		this._deleteWidget(widget);
	}

	/**
	 * Add or update form on widget configuration dialogue (when opened, as well as when requested by 'onchange'
	 * attributes in form itself).
	 */
	updateWidgetConfigDialogue() {
		const $body = this._data.dialogue.body;
		const $footer = $('.overlay-dialogue-footer', this._data.dialogue.div);
		const $header = $('.dashbrd-widget-head', this._data.dialogue.div);
		const $form = $('form', $body);
		const widget = this._data.dialogue.widget; // Widget currently being edited.
		const url = new Curl('zabbix.php');
		const ajax_data = {};

		let fields;

		url.setArgument('action', 'dashboard.widget.edit');

		if (this._data.dashboard.templateid !== null) {
			ajax_data.templateid = this._data.dashboard.templateid;
		}

		if ($form.length) {
			// Take values from form.
			fields = $form.serializeJSON();
			ajax_data.type = fields['type'];
			ajax_data.prev_type = this._data.dialogue.widget_type;
			delete fields['type'];

			if (ajax_data.prev_type === ajax_data.type) {
				ajax_data.name = fields['name'];
				ajax_data.view_mode = (fields['show_header'] == 1)
					? ZBX_WIDGET_VIEW_MODE_NORMAL
					: ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER;

				delete fields['name'];
				delete fields['show_header'];
			}
			else {
				// Get default config if widget type changed.
				fields = {};
			}
		}
		else if (widget !== null) {
			// Open form with current config.
			ajax_data.type = widget.type;
			ajax_data.name = widget.header;
			ajax_data.view_mode = widget.view_mode;
			fields = widget.fields;
		}
		else {
			// Get default config for new widget.
			fields = {};
		}

		if (fields && Object.keys(fields).length !== 0) {
			ajax_data.fields = JSON.stringify(fields);
		}

		const overlay = overlays_stack.getById('widgetConfg');

		overlay.setLoading();

		if (overlay.xhr) {
			overlay.xhr.abort();
		}

		overlay.xhr = jQuery.ajax({
			url: url.getUrl(),
			method: 'POST',
			data: ajax_data,
			dataType: 'json'
		});

		overlay.xhr.done((response) => {
			this._data.dialogue.widget_type = response.type;

			$body.empty();
			$body.append(response.body);

			if (typeof response.debug !== 'undefined') {
				$body.append(response.debug);
			}

			if (typeof response.messages !== 'undefined') {
				$body.append(response.messages);
			}

			$body.find('form').attr('aria-labeledby', $header.find('h4').attr('id'));

			// Change submit function for returned form.
			$('#widget-dialogue-form', $body).on('submit', (e) => {
				e.preventDefault();
				this._updateWidgetConfig(widget);
			});

			const $overlay = jQuery('[data-dialogueid="widgetConfg"]');

			$overlay.toggleClass('sticked-to-top', this._data.dialogue.widget_type === 'svggraph');

			Overlay.prototype.recoverFocus.call({'$dialogue': $overlay});
			Overlay.prototype.containFocus.call({'$dialogue': $overlay});

			overlay.unsetLoading();

			const area_size = {
				'width': this._data.widget_defaults[this._data.dialogue.widget_type].size.width,
				'height': this._data.widget_defaults[this._data.dialogue.widget_type].size.height
			};

			if (widget === null && !this._findEmptyPosition(area_size)) {
				this._showDialogMessageExhausted();
				$('.dialogue-widget-save', $footer).prop('disabled', true);
			}

			// Activate tab indicator for graph widget form.
			if (this._data.dialogue.widget_type === 'svggraph') {
				new TabIndicators();
			}
		});
	}

	/**
	 * Returns list of widgets filter by key=>value pair.
	 */
	getWidgetsBy(key, value) {
		const widgets_found = [];

		for (const w of this._data.widgets) {
			if (w[key] === value) {
				widgets_found.push(w);
			}
		}

		return widgets_found;
	}

	/**
	 * Register widget as data receiver shared by other widget.
	 */
	registerDataExchange(obj) {
		this._data.widget_relation_submissions.push(obj);
	}

	/**
	 * Pushes received data in data buffer and calls sharing method.
	 *
	 * @param {object} widget     Data origin widget
	 * @param {string} data_name  String to identify data shared
	 *
	 * @returns {boolean}  Indicates either there was linked widget that was related to data origin widget
	 */
	widgetDataShare(widget, data_name) {
		const args = Array.prototype.slice.call(arguments, 2);
		const uniqueid = widget.uniqueid;

		let ret = true;
		let index = -1;

		if (!args.length) {
			return false;
		}

		if (typeof this._data.widget_relations.relations[widget.uniqueid] === 'undefined'
			|| this._data.widget_relations.relations[widget.uniqueid].length === 0) {
			ret = false;
		}

		if (typeof this._data.data_buffer[uniqueid] === 'undefined') {
			this._data.data_buffer[uniqueid] = [];
		}
		else if (typeof this._data.data_buffer[uniqueid] !== 'undefined') {
			for (const i in this._data.data_buffer[uniqueid]) {
				if (this._data.data_buffer[uniqueid][i].data_name === data_name) {
					index = i;
				}
			}
		}

		if (index === -1) {
			this._data.data_buffer[uniqueid].push({
				data_name: data_name,
				args: args,
				old: []
			});
		}
		else {
			if (this._data.data_buffer[uniqueid][index].args !== args) {
				this._data.data_buffer[uniqueid][index].args = args;
				this._data.data_buffer[uniqueid][index].old = [];
			}
		}

		this.callWidgetDataShare();

		return ret;
	}

	callWidgetDataShare() {
		for (const src_uniqueid in this._data.data_buffer) {
			if (typeof this._data.data_buffer[src_uniqueid] !== 'object') {
				continue;
			}

			for (const buffer_data of this._data.data_buffer[src_uniqueid]) {
				if (typeof this._data.widget_relations.relations[src_uniqueid] === 'undefined') {
					continue;
				}

				for (const dest_uid of this._data.widget_relations.relations[src_uniqueid]) {
					if (buffer_data.old.includes(dest_uid)) {
						continue;
					}

					if (typeof this._data.widget_relations.tasks[dest_uid] === 'undefined') {
						continue;
					}

					const widget = this.getWidgetsBy('uniqueid', dest_uid);

					if (widget.length) {
						for (const task of this._data.widget_relations.tasks[dest_uid]) {
							if (task.data_name === buffer_data.data_name) {
								task.callback.apply([widget[0], buffer_data.args]);
							}
						}

						buffer_data.old.push(dest_uid);
					}
				}
			}
		}
	}

	makeReference() {
		let ref = false;

		while (!ref) {
			ref = this._generateRandomString(5);

			for (let i = 0, l = this._data.widgets.length; l > i; i++) {
				if (typeof this._data.widgets[i].fields['reference'] !== 'undefined') {
					if (this._data.widgets[i].fields['reference'] === ref) {
						ref = false;
						break;
					}
				}
			}
		}

		return ref;
	}

	isEditMode() {
		return this._data.options.edit_mode;
	}

	/**
	 * Add action, that will be performed on $hook_name trigger.
	 *
	 * @param {string} hook_name                  Name of trigger, when $function_to_call should be called.
	 * @param {string} function_to_call           Name of function in global scope that will be called.
	 * @param {string|null} uniqueid              A widget to receive the event for (null for all widgets).
	 * @param {object} options                    Any key in options is optional.
	 * @param {array}  options['parameters']      Array of parameters with which the function will be called.
	 * @param {array}  options['grid']            Mark, what data from grid should be passed to $function_to_call.
	 *                                            If is empty, parameter 'grid' will not be added to function_to_call params.
	 * @param {string} options['grid']['widget']  True to pass the widget as argument.
	 * @param {string} options['grid']['data']    True to pass dashboard grid data as argument.
	 * @param {string} options['grid']['obj']     True to pass dashboard grid object as argument.
	 * @param {int}    options['priority']        Order, when it should be called, compared to others. Default = 10.
	 * @param {int}    options['trigger_name']    Unique name. There can be only one trigger with this name for each hook.
	 */
	addAction(hook_name, function_to_call, uniqueid = null, options = {}) {
		let found = false;

		if (typeof this._data.triggers[hook_name] === 'undefined') {
			this._data.triggers[hook_name] = [];
		}

		// Add trigger with each name only once.
		if (typeof options['trigger_name'] !== 'undefined') {
			let trigger_name = options['trigger_name'];

			for (const t of this._data.triggers[hook_name]) {
				if (typeof t.options['trigger_name'] !== 'undefined' && t.options['trigger_name'] === trigger_name) {
					found = true;
				}
			}
		}

		if (!found) {
			this._data.triggers[hook_name].push({
				'function': function_to_call,
				'uniqueid': uniqueid,
				'options': options
			});
		}
	}

	_makeWidgetDiv(widget) {
		const iterator_classes = {
			'root': 'dashbrd-grid-iterator',
			'container': 'dashbrd-grid-iterator-container',
			'head': 'dashbrd-grid-iterator-head',
			'content': 'dashbrd-grid-iterator-content',
			'focus': 'dashbrd-grid-iterator-focus',
			'actions': 'dashbrd-grid-iterator-actions',
			'mask': 'dashbrd-grid-iterator-mask',
			'hidden_header': 'dashbrd-grid-iterator-hidden-header'
		};

		const widget_classes = {
			'root': 'dashbrd-grid-widget',
			'container': 'dashbrd-grid-widget-container',
			'head': 'dashbrd-grid-widget-head',
			'content': 'dashbrd-grid-widget-content',
			'focus': 'dashbrd-grid-widget-focus',
			'actions': 'dashbrd-grid-widget-actions',
			'mask': 'dashbrd-grid-widget-mask',
			'hidden_header': 'dashbrd-grid-widget-hidden-header'
		};

		const widget_actions = {
			'widgetType': widget.type,
			'currentRate': widget.rf_rate,
			'widget_uniqueid': widget.uniqueid,
			'multiplier': '0'
		};

		const classes = widget.iterator ? iterator_classes : widget_classes;

		if ('graphid' in widget.fields) {
			widget_actions.graphid = widget.fields['graphid'];
		}

		if ('itemid' in widget.fields) {
			widget_actions.itemid = widget.fields['itemid'];
		}

		if (widget.fields.dynamic && widget.fields.dynamic == 1 && this._data.dashboard.dynamic_hostid !== null) {
			widget_actions.dynamic_hostid = this._data.dashboard.dynamic_hostid;
		}

		widget.content_header = $('<div>', {'class': classes.head})
			.append(
				$('<h4>').text((widget.header !== '') ? widget.header : this._data.widget_defaults[widget.type].header)
			);

		if (!widget.parent) {
			// Do not add action buttons for child widgets of iterators.
			widget.content_header
				.append(widget.iterator
					? $('<div>', {'class': 'dashbrd-grid-iterator-pager'}).append(
						$('<button>', {
							'type': 'button',
							'class': 'btn-iterator-page-previous',
							'title': t('Previous page')
						}).on('click', () => {
							if (widget.page > 1) {
								widget.page--;
								this._updateWidgetContent(widget);
							}
						}),
						$('<span>', {'class': 'dashbrd-grid-iterator-pager-info'}),
						$('<button>', {
							'type': 'button',
							'class': 'btn-iterator-page-next',
							'title': t('Next page')
						}).on('click', () => {
							if (widget.page < widget.page_count) {
								widget.page++;
								this._updateWidgetContent(widget);
							}
						})
					)
					: ''
				)
				.append($('<ul>', {'class': classes.actions})
					.append((this._data.options['editable'] && !this._data.options['kioskmode'])
						? $('<li>').append(
							$('<button>', {
								'type': 'button',
								'class': 'btn-widget-edit',
								'title': t('Edit')
							}).on('click', (e) => {
								this.editWidget(widget, e.target);
							})
						)
						: ''
					)
					.append(
						$('<li>').append(
							$('<button>', {
								'type': 'button',
								'class': 'btn-widget-action',
								'title': t('Actions'),
								'data-menu-popup': JSON.stringify({
									'type': 'widget_actions',
									'data': widget_actions
								}),
								'attr': {
									'aria-expanded': false,
									'aria-haspopup': true
								}
							})
						)
					)
				);
		}

		widget.content_body = $('<div>', {'class': classes.content})
			.toggleClass('no-padding', !widget.iterator && !widget.configuration['padding']);

		widget.container = $('<div>', {'class': classes.container})
			.append(widget.content_header)
			.append(widget.content_body);

		if (widget.iterator) {
			widget.container
				.append($('<div>', {'class': 'dashbrd-grid-iterator-too-small'})
					.append($('<div>').html(t('Widget is too small for the specified number of columns and rows.')))
				);
		}
		else {
			widget.content_script = $('<div>');
			widget.container.append(widget.content_script);
		}

		const $div = $('<div>', {'class': classes.root})
			.toggleClass(classes.hidden_header, widget.view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER)
			.toggleClass('new-widget', widget.new_widget);

		if (!widget.parent) {
			$div.css({
				'min-height': `${this._data.options['widget-height']}px`,
				'min-width': `${this._data.options['widget-width']}%`
			});
		}

		// Used for disabling widget interactivity in edit mode while resizing.
		widget.mask = $('<div>', {'class': classes.mask});

		$div.append(widget.container, widget.mask);

		widget.content_header
			.on('focusin', () => {
				this._enterWidget(widget);
			})
			.on('focusout', (e) => {
				if (!widget.content_header.has(e.relatedTarget).length) {
					this._leaveWidget(widget);
				}
			})
			.on('focusin focusout', () => {
				// Skip mouse events caused by animations which were caused by focus change.
				this._data.options['mousemove_waiting'] = true;
			});

		$div
			// "Mouseenter" is required, since "mousemove" may not always bubble.
			.on('mouseenter mousemove', () => {
				this._enterWidget(widget);

				delete this._data.options['mousemove_waiting'];
			})
			.on('mouseleave', () => {
				if (!this._data.options['mousemove_waiting']) {
					this._leaveWidget(widget);
				}
			});

		$div.on('load.image', () => {
			// Call refreshCallback handler for expanded popup menu items.
			if ($div.find('[data-expanded="true"][data-menu-popup]').length) {
				$div.find('[data-expanded="true"][data-menu-popup]').menuPopup('refresh', widget);
			}
		});

		return $div;
	}

	/**
	 * Find out if widgets should react on mouse and focus events.
	 *
	 * @returns {boolean}
	 */
	_isDashboardFrozen() {
		// Edit widget dialogue active?
		if (this._data.options['config_dialogue_active']) {
			return true;
		}

		for (const w of this._data.widgets) {
			// Widget placeholder doesn't have header.
			if (!w.content_header) {
				continue;
			}

			// Widget popup open (refresh rate)?
			if (w.content_header.find('[data-expanded="true"]').length > 0
				// Widget being dragged or resized in dashboard edit mode?
				|| w.div.hasClass('ui-draggable-dragging')
				|| w.div.hasClass('ui-resizable-resizing')) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Focus specified widget or iterator and blur all other widgets.
	 * If child widget of iterator is specified, blur all other child widgets of iterator.
	 * This top-level function should be called by mouse and focus event handlers.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	_enterWidget(widget) {
		if (widget.div.hasClass(widget.iterator ? 'dashbrd-grid-iterator-focus' : 'dashbrd-grid-widget-focus')) {
			return;
		}

		if (this._isDashboardFrozen()) {
			return;
		}

		if (widget.parent) {
			this._doLeaveWidgetsOfIteratorExcept(widget.parent, widget);
			this._doEnterWidgetOfIterator(widget);
		}
		else {
			this._doLeaveWidgetsExcept(widget);
			this._doEnterWidget(widget);
		}

		this._slideKiosk();
	}

	/**
	 * Blur specified widget or iterator. If iterator is specified, blur it's focused child widget as well.
	 * This top-level function should be called by mouse and focus event handlers.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	_leaveWidget(widget) {
		if (!widget.div.hasClass(widget.iterator ? 'dashbrd-grid-iterator-focus' : 'dashbrd-grid-widget-focus')) {
			return;
		}

		if (this._isDashboardFrozen()) {
			return;
		}

		this._doLeaveWidget(widget);

		this._slideKiosk();
	}

	/**
	 * Focus specified top-level widget or iterator. If iterator is specified, focus it's hovered child widget as well.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	_doEnterWidget(widget) {
		widget.div.addClass(widget.iterator ? 'dashbrd-grid-iterator-focus' : 'dashbrd-grid-widget-focus');

		if (widget.iterator) {
			let child_hovered = null;

			for (const child of widget.children) {
				if (child.div.is(':hover')) {
					child_hovered = child;
				}
			}

			if (child_hovered !== null) {
				this._doEnterWidgetOfIterator(child_hovered);
			}
		}
	}

	/**
	 * Focus specified child widget of iterator.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	_doEnterWidgetOfIterator(widget) {
		widget.div.addClass('dashbrd-grid-widget-focus');

		if (widget.parent.div.hasClass('dashbrd-grid-iterator-hidden-header')) {
			widget.parent.div.toggleClass('iterator-double-header', widget.div.position().top === 0);
		}
	}

	/**
	 * Blur all top-level widgets and iterators, except the specified one.
	 *
	 * @param {object} except_widget  Dashboard widget object.
	 */
	_doLeaveWidgetsExcept(except_widget) {
		if (this._data.widgets) {
			for (const w of this._data.widgets) {
				if (except_widget !== null && w.uniqueid === except_widget.uniqueid) {
					continue;
				}

				this._doLeaveWidget(w);
			}
		}
	}

	/**
	 * Blur specified top-level widget or iterator. If iterator is specified, blur it's focused child widget as well.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	_doLeaveWidget(widget) {
		// Widget placeholder doesn't have header.
		if (!widget.content_header) {
			return;
		}

		if (widget.content_header.has(document.activeElement).length) {
			document.activeElement.blur();
		}

		if (widget.iterator) {
			this._doLeaveWidgetsOfIteratorExcept(widget);
			widget.div.removeClass('iterator-double-header');
		}

		widget.div.removeClass(widget.iterator ? 'dashbrd-grid-iterator-focus' : 'dashbrd-grid-widget-focus');
	}

	/**
	 * Blur all child widgets of iterator, except the specified one.
	 *
	 * @param {object} iterator      Iterator object.
	 * @param {object} except_child  Dashboard widget object.
	 */
	_doLeaveWidgetsOfIteratorExcept(iterator, except_child = null) {
		for (const child of iterator.children) {
			if (except_child !== null && child.uniqueid === except_child.uniqueid) {
				continue;
			}

			child.div.removeClass('dashbrd-grid-widget-focus');
		}
	}

	/**
	 * Update dashboard sliding effect if in kiosk mode.
	 */
	_slideKiosk() {
		const iterator_classes = {
			'focus': 'dashbrd-grid-iterator-focus',
			'hidden_header': 'dashbrd-grid-iterator-hidden-header'
		};

		const widget_classes = {
			'focus': 'dashbrd-grid-widget-focus',
			'hidden_header': 'dashbrd-grid-widget-hidden-header'
		};

		// Calculate the dashboard offset (0, 1 or 2 lines) based on focused widget.

		let slide_lines = 0;

		for (const widget of this._data.widgets) {
			const classes = widget.iterator ? iterator_classes : widget_classes;

			if (!widget.div.hasClass(classes.focus)) {
				continue;
			}

			// Focused widget not on the first row of dashboard?
			if (widget.div.position().top !== 0) {
				break;
			}

			if (widget.iterator) {
				slide_lines = widget.div.hasClass('iterator-double-header') ? 2 : 1;
			}
			else if (widget.div.hasClass(classes.hidden_header)) {
				slide_lines = 1;
			}

			break;
		}

		// Apply the calculated dashboard offset (0, 1 or 2 lines) slowly.

		const $wrapper = this._$target.closest('.layout-kioskmode');

		if (!$wrapper.length) {
			return;
		}

		if (typeof this._data.options['kiosk_slide_timeout'] !== 'undefined') {
			clearTimeout(this._data.options['kiosk_slide_timeout'])
			delete this._data.options['kiosk_slide_timeout'];
		}

		let slide_lines_current = 0;
		for (let i = 2; i > 0; i--) {
			if ($wrapper.hasClass('kiosk-slide-lines-' + i)) {
				slide_lines_current = i;
				break;
			}
		}

		if (slide_lines > slide_lines_current) {
			if (slide_lines_current > 0) {
				$wrapper.removeClass('kiosk-slide-lines-' + slide_lines_current);
			}
			$wrapper.addClass('kiosk-slide-lines-' + slide_lines);
		}
		else if (slide_lines < slide_lines_current) {
			this._data.options['kiosk_slide_timeout'] = setTimeout(() => {
				$wrapper.removeClass('kiosk-slide-lines-' + slide_lines_current);
				if (slide_lines > 0) {
					$wrapper.addClass('kiosk-slide-lines-' + slide_lines);
				}
				delete this._data.options['kiosk_slide_timeout'];
			}, 2000);
		}
	}

	_setWidgetViewMode(widget, view_mode) {
		if (widget.view_mode == view_mode) {
			return;
		}

		widget.view_mode = view_mode;

		const hidden_header_class = widget.iterator
			? 'dashbrd-grid-iterator-hidden-header'
			: 'dashbrd-grid-widget-hidden-header';

		if (widget.iterator) {
			if (view_mode == ZBX_WIDGET_VIEW_MODE_NORMAL) {
				widget.div.removeClass('iterator-double-header');
			}

			for (const child of widget.children) {
				this._setWidgetViewMode(child, view_mode);
			}
		}

		widget.div.toggleClass(hidden_header_class, view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER);
	}

	_updateIteratorPager(iterator) {
		$('.dashbrd-grid-iterator-pager-info', iterator.content_header)
			.text(`${iterator.page} / ${iterator.page_count}`);

		iterator.content_header.addClass('pager-visible');

		const too_narrow = iterator.content_header.width() <
			$('.dashbrd-grid-iterator-pager', iterator.content_header).outerWidth(true)
			+ $('.dashbrd-grid-iterator-actions', iterator.content_header).outerWidth(true);

		const is_pager_visible = iterator.page_count > 1 && !too_narrow && !this._getIteratorTooSmallState(iterator);

		iterator.content_header.toggleClass('pager-visible', is_pager_visible);
	}

	_addWidgetInfoButtons($content_header, buttons) {
		// Note: this function is used only for widgets and not iterators.

		const $widget_actions = $('.dashbrd-grid-widget-actions', $content_header);

		for (const button of buttons) {
			$widget_actions.prepend(
				$('<li>', {'class': 'widget-info-button'})
					.append(
						$('<button>', {
							'type': 'button',
							'class': button.icon,
							'data-hintbox': 1,
							'data-hintbox-static': 1
						})
					)
					.append(
						$('<div>', {
							'class': 'hint-box',
							'html': button.hint
						}).hide()
					)
			);
		}
	}

	_removeWidgetInfoButtons($content_header) {
		// Note: this function is used only for widgets and not iterators.

		$('.dashbrd-grid-widget-actions', $content_header).find('.widget-info-button').remove();
	}

	_setWidgetPadding(widget, padding) {
		// Note: this function is used only for widgets and not iterators.

		if (!widget.iterator && widget.configuration['padding'] !== padding) {
			widget.configuration['padding'] = padding;
			widget.content_body.toggleClass('no-padding', !padding);
			this._resizeWidget(widget);
		}
	}

	_applyWidgetConfiguration(widget, configuration) {
		if ('padding' in configuration) {
			this._setWidgetPadding(widget, configuration['padding']);
		}
	}

	/**
	 * Set height of dashboard container DOM element.
	 *
	 * @param {int|null}    min_rows  Minimal desired rows count.
	 */
	_resizeDashboardGrid(min_rows= null) {
		this._data.options['rows'] = 0;

		for (const w of this._data.widgets) {
			this._data.options.rows = Math.max(w.pos.y + w.pos.height, this._data.options['rows']);
		}

		if (min_rows !== null && this._data.options['rows'] < min_rows) {
			this._data.options['rows'] = min_rows;
		}

		let height = this._data.options['widget-height'] * this._data.options['rows'];

		if (this._data.options['edit_mode']) {
			// Occupy whole screen only if in edit mode, not to cause scrollbar in kiosk mode.
			height = Math.max(height, this._data.minimalHeight);
		}

		this._$target.css({
			height: `${height}px`
		});
	}

	/**
	 * Calculate minimal height for dashboard grid in edit mode (maximal height without vertical page scrolling).
	 *
	 * @returns {int}
	 */
	_calculateGridMinHeight() {
		let height = $(window).height() - $('footer').outerHeight() - this._$target.offset().top - $('.wrapper').scrollTop();

		this._$target.parentsUntil('.wrapper').each(function() {
			height -= parseInt($(this).css('padding-bottom'));
		});

		height -= parseInt(this._$target.css('margin-bottom'));

		return height;
	}

	_generateRandomString(length) {
		const space = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		let	ret = '';

		for (let i = 0; length > i; i++) {
			ret += space.charAt(Math.floor(Math.random() * space.length));
		}
		return ret;
	}

	_calcDivPosition($div) {
		const pos = $div.position();

		const cell_w = this._data.cell_width;
		const cell_h = this._data.options['widget-height'];

		let place_x, place_y, place_w, place_h;

		if (this._data.pos_action === 'resize') {
			// 0.49 refers to pixels in the following calculations.
			place_w = Math.round($div.width() / cell_w - 0.49);
			place_h = Math.round($div.height() / cell_h - 0.49);
			place_x = $div.hasClass('resizing-left')
				? (Math.round((pos.left + $div.width()) / cell_w) - place_w)
				: Math.round(pos.left / cell_w);
			place_y = $div.hasClass('resizing-top')
				? (Math.round((pos.top + $div.height()) / cell_h) - place_h)
				: Math.round(pos.top / cell_h);
		}
		else {
			place_x = Math.round(pos.left / cell_w);
			place_y = Math.round(pos.top / cell_h);
			place_w = Math.round(($div.width() + pos.left - place_x * cell_w) / cell_w);
			place_h = Math.round(($div.height() + pos.top - place_y * cell_h) / cell_h);
		}

		if (this._data.pos_action === 'resize') {
			place_w = Math.min(place_w, place_w + place_x, this._data.options['max-columns'] - place_x);
			place_h = Math.min(place_h, place_h + place_y, this._data.options['max-rows'] - place_y);
		}

		place_x = Math.min(place_x, this._data.options['max-columns'] - place_w);
		place_y = Math.min(place_y, this._data.options['max-rows'] - place_h);

		return {
			x: Math.max(place_x, 0),
			y: Math.max(place_y, 0),
			width: Math.max(place_w, 1),
			height: Math.max(place_h, this._data.options['widget-min-rows'])
		}
	}

	_getCurrentCellWidth() {
		return $('.dashbrd-grid-container').width() / this._data.options['max-columns'];
	}

	_setDivPosition($div, pos) {
		$div.css({
			left: `${this._data.options['widget-width'] * pos.x}%`,
			top: `${this._data.options['widget-height'] * pos.y}px`,
			width: `${this._data.options['widget-width'] * pos.width}%`,
			height: `${this._data.options['widget-height'] * pos.height}px`
		});
	}

	_resetCurrentPositions(widgets) {
		for (const w of widgets) {
			w.current_pos = {...w.pos};
		}
	}

	_startWidgetPositioning(widget, action) {
		this._data.pos_action = action;
		this._data.cell_width = this._getCurrentCellWidth();
		this._data.placeholder.css('visibility', (action === 'resize') ? 'hidden' : 'visible').show();
		this._data.new_widget_placeholder.hide();
		this._resetCurrentPositions(this._data.widgets);
	}

	_posEquals(pos1, pos2) {
		for (const key of ['x', 'y', 'width', 'height']) {
			if (pos1[key] !== pos2[key]) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check is there collision between two position objects.
	 *
	 * @param {object} pos1  Object with position and dimension.
	 * @param {object} pos2  Object with position and dimension.
	 *
	 * @returns {boolean}
	 */
	_rectOverlap(pos1, pos2) {
		return (pos1.x < (pos2.x + pos2.width)
			&& (pos1.x + pos1.width) > pos2.x
			&& pos1.y < (pos2.y + pos2.height)
			&& (pos1.y + pos1.height) > pos2.y);
	}

	/**
	 * Rearrange widgets on drag operation.
	 *
	 * @param {array}  widgets   Array of widget objects.
	 * @param {object} widget    Moved widget object.
	 * @param {number} max_rows
	 *
	 * @returns {boolean}
	 */
	_realignWidget(widgets, widget, max_rows) {
		const realign = (widgets, widget, allow_reorder) => {
			const next = [];

			for (const w of widgets) {
				if (widget.uniqueid !== w.uniqueid && !overflow) {
					if (this._rectOverlap(widget.current_pos, w.current_pos)
							|| (!allow_reorder && 'affected_by_id' in w && w.affected_by_id === widget.uniqueid)) {
						w.current_pos.y = Math.max(w.current_pos.y,
							widget.current_pos.y + widget.current_pos.height
						);
						next.push(w);
						overflow = (overflow || (w.current_pos.y + w.current_pos.height) > max_rows);
					}
				}
			}

			for (const w of next) {
				if (!overflow) {
					realign(widgets, w, false);
				}
			}
		};

		let overflow = false;

		for (const w of widgets) {
			if (widget.uniqueid !== w.uniqueid && !overflow) {
				w.current_pos = {...w.pos};
			}
		}

		realign(this._sortWidgets(widgets), widget, true);

		return overflow;
	}

	_sortWidgets(widgets) {
		widgets
			.sort((box1, box2) => {
				return box1.pos.y - box2.pos.y;
			})
			.forEach((box, index) => {
				box.div.data('widget-index', index);
			});

		return widgets;
	}

	/**
	 * Collapse dragged widget position moving widgets below to it position.
	 *
	 * @param {array}  widgets   Array of widget objects.
	 * @param {object} widget    Dragged widget object.
	 * @param {number} max_rows  Dashboard rows count.
	 */
	_dragPrepare(widgets, widget, max_rows) {
		const markAffected = (widgets, affected_by, affected_by_draggable) => {
			const w_pos = {...affected_by.pos};

			w_pos.height++;

			widgets.filter((w) => {
				return !('affected' in w) && this._rectOverlap(w_pos, w.pos) && w.uniqueid !== widget.uniqueid;
			}).forEach((w) => {
				w.affected = true;
				w.affected_by_id = affected_by.uniqueid;
				if (affected_by_draggable) {
					w.affected_by_draggable = affected_by.uniqueid;
				}
				markAffected(widgets, w, affected_by_draggable);
			});
		};

		markAffected(widgets, widget, true);

		for (const w of widgets) {
			delete w.affected;
		}

		for (const w of widgets) {
			markAffected(widgets, w, false);
		}

		for (const w of widgets) {
			if ('affected_by_draggable' in w) {
				const pos = {...w.pos};

				let overlaps = false;

				pos.y -= widget.pos.height;
				pos.height += widget.pos.height;

				for (const box of widgets) {
					overlaps = (box.uniqueid !== w.uniqueid
						&& box.uniqueid !== widget.uniqueid
						&& this._rectOverlap(box.pos, pos));

					if (overlaps) {
						pos.y = box.pos.y + box.pos.height;
						pos.height -= w.pos.y - pos.y;
						overlaps = (pos.height < w.pos.height || pos.y >= w.pos.y);
					}
					else {
						break;
					}
				}

				if (overlaps) {
					break;
				}

				w.pos.y = pos.y;
			}
		}
	}

	/**
	 * Resize widgets.
	 *
	 * @param {array}  widgets        Array of widget objects.
	 * @param {object} widget         Resized widget object.
	 * @param {object} axis           Resized axis options.
	 * @param {string} axis.axis_key  Axis key as string: 'x', 'y'.
	 * @param {string} axis.size_key  Size key as string: 'width', 'height'.
	 * @param {number} axis.size_min  Minimum size allowed for one item.
	 * @param {number} axis.size_max  Maximum size allowed for one item, also is used as maximum size of dashboard.
	 */
	_fitWidgetsIntoBox(widgets, widget, axis) {
		const axis_key = axis.axis_key;
		const size_key = axis.size_key;

		const size_min = axis.size_min;
		const size_max = axis.size_max;
		const opposite_axis_key = (axis_key === 'x') ? 'y' : 'x';
		const opposite_size_key = (size_key === 'width') ? 'height' : 'width';

		const axis_pos = {...widget.current_pos};

		const getAffectedInBounds = (bounds) => {
			return affected.filter((w) => {
				return this._rectOverlap(bounds, w.current_pos);
			});
		};

		const markAffectedWidgets = (pos, uid) => {
			widgets.filter((w) => {
				return !('affected_axis' in w) && w.uniqueid !== uid && this._rectOverlap(pos, w.current_pos);
			}).forEach((w) => {
				const boundary = {...w.current_pos};

				if (w.uniqueid !== widget.uniqueid) {
					boundary[size_key] += pos[axis_key] + pos[size_key] - boundary[axis_key];
				}
				w.affected_axis = axis_key;

				markAffectedWidgets(boundary);
			});
		}

		let margins = {};
		let new_max = 0;
		let affected;
		let overlap;

		// Resize action for left/up is mirrored right/down action.
		if ('mirrored' in axis) {
			for (const w of widgets) {
				w.current_pos[axis_key] = size_max - w.current_pos[axis_key] - w.current_pos[size_key];
				w.pos[axis_key] = size_max - w.pos[axis_key] - w.pos[size_key];
			}
			axis_pos[axis_key] = size_max - axis_pos[axis_key] - axis_pos[size_key];
		}

		// Get array containing only widgets affected by resize operation.
		markAffectedWidgets(widget.current_pos, widget.uniqueid);

		affected = widgets
			.filter((w) => {
				return 'affected_axis' in w && w.affected_axis === axis_key && w.uniqueid !== widget.uniqueid;
			})
			.sort((box1, box2) => {
				return box1.current_pos[axis_key] - box2.current_pos[axis_key];
			});

		/**
		 * Compact affected widgets removing empty space between them when possible. Additionally build overlap array
		 * which will contain maximal coordinate occupied by widgets on every opposite axis line.
		 */
		for (const w of affected) {
			const last = w.current_pos[opposite_axis_key] + w.current_pos[opposite_size_key];

			let new_pos = axis_pos[axis_key] + axis_pos[size_key];
			let i;

			for (i = w.current_pos[opposite_axis_key]; i < last; i++) {
				if (i in margins) {
					new_pos = Math.max(new_pos, margins[i]);
				}
			}

			if (w.current_pos[axis_key] > new_pos) {
				// Should keep widget original position if compacted value is less than original.
				for (i = w.current_pos[opposite_axis_key]; i < last; i++) {
					margins[i] = w.current_pos[axis_key] + w.current_pos[size_key];
				}

				continue;
			}

			for (i = w.current_pos[opposite_axis_key]; i < last; i++) {
				margins[i] = new_pos + w.current_pos[size_key];
			}

			w.current_pos[axis_key] = new_pos;
			new_max = Math.max(new_max, new_pos + w.current_pos[size_key]);
		}

		overlap = new_max - size_max;

		/*
		 * When previous step could not fit affected widgets into visible area resize should be done.
		 * Resize scan affected widgets line by line collapsing only widgets having size greater than minimal
		 * allowed 'size_min' and position overlapped by dashboard visible area.
		 */
		if (overlap > 0) {
			// Scanline is virtual box that utilizes whole width/height depending on its direction defined by size_key.
			const scanline = {'x': 0, 'y': 0, ...axis.scanline};
			const axis_boundaries = {};

			let slot = axis_pos[axis_key] + axis_pos[size_key];
			let next_col;
			let col;
			let collapsed;
			let collapsed_pos;
			let margins_backup;

			scanline[size_key] = 1;

			/*
			 * Build affected boundaries object with minimum and maximum value on opposite axis for every widget.
			 * Key in axis_boundaries object will be widget uniqueid and value boundaries object described above.
			 */
			for (const w of affected) {
				const affected_box = {...w.current_pos};

				let min = w.current_pos[opposite_axis_key];
				let max = min + w.current_pos[opposite_size_key];
				let size = w.current_pos[size_key];
				let boxes = [];
				let bounds_changes = true;

				affected_box[size_key] = new_max - affected_box[axis_key] - affected_box[size_key];

				while (bounds_changes) {
					bounds_changes = false;
					affected_box[axis_key] += size;
					affected_box[opposite_axis_key] = min;
					affected_box[opposite_size_key] = max - min;
					size = new_max;
					boxes = getAffectedInBounds(affected_box);

					for (const box of boxes) {
						if (min > box.current_pos[opposite_axis_key]) {
							min = box.current_pos[opposite_axis_key];
							bounds_changes = true;
						}

						if (max < box.current_pos[opposite_axis_key] + box.current_pos[opposite_size_key]) {
							max = box.current_pos[opposite_axis_key] + box.current_pos[opposite_size_key];
							bounds_changes = true;
						}

						size = Math.min(size, box.current_pos[size_key]);
					}
				}

				axis_boundaries[w.uniqueid] = {debug: w.header, min: min, max: max};
			}

			// Scan affected line by line.
			while (slot < new_max && overlap > 0) {
				margins_backup = {...margins};
				collapsed_pos = {};
				scanline[axis_key] = slot;
				col = getAffectedInBounds(scanline);
				scanline[axis_key] += scanline[size_key];
				next_col = getAffectedInBounds(scanline);
				collapsed = next_col.length > 0;

				for (const box of next_col) {
					if ('pos' in box && box.pos[axis_key] > slot) {
						continue;
					}

					box.new_pos = {...box.current_pos};
					box.new_pos[axis_key] = slot;

					for (const col_box of col) {
						if (col_box.uniqueid === box.uniqueid || this._rectOverlap(col_box.current_pos, box.new_pos)) {
							if (col_box.current_pos[size_key] > size_min) {
								const start_pos = axis_boundaries[col_box.uniqueid].min;
								const stop_pos = axis_boundaries[col_box.uniqueid].max;
								let margin = 0

								// Find max overlap position value for checked widget.
								for (let i = start_pos; i < stop_pos; i++) {
									margin = Math.max(margin, margins[i]);
								}

								if (margin && margin < size_max) {
									box.new_pos[axis_key] = box.current_pos[axis_key];
									continue;
								}
								else {
									for (let i = start_pos; i < stop_pos; i++) {
										margins[i] = margins_backup[i] - scanline[size_key];
									}
								}

								col_box.new_pos = {...col_box.current_pos};
								col_box.new_pos[size_key] -= scanline[size_key];

								// Mark opposite axis coordinates as movable.
								for (let i = start_pos; i < stop_pos; i++) {
									collapsed_pos[i] = 1;
								}
							}
							else {
								collapsed = false;
								break;
							}
						}
					}

					if (!collapsed) {
						break;
					}
				}

				if (collapsed) {
					for (const w of affected) {
						if (w.current_pos[axis_key] > slot && w.current_pos[opposite_axis_key] in collapsed_pos) {
							w.current_pos[axis_key] = Math.max(w.current_pos[axis_key] - scanline[size_key],
								w.pos[axis_key]
							);
						}
					}

					// Update margin values for collapsed lines on opposite axis.
					for (const i in collapsed_pos) {
						margins[i] = margins_backup[i] - scanline[size_key];
					}

					overlap -= 1;
					new_max -= 1;
				}
				else {
					margins = margins_backup;
					slot += scanline[size_key];
				}

				for (const box of next_col.concat(col)) {
					if (collapsed && 'new_pos' in box) {
						box.current_pos = box.new_pos;
					}

					delete box.new_pos;
				}
			}
		}

		/*
		 * When resize failed to fit affected widgets move them into visible area and decrease size of widget
		 * which started resize operation, additionally setting 'overflow' property to widget.
		 */
		if (overlap > 0) {
			widget.current_pos[size_key] -= overlap;
			widget.current_pos.overflow = true;

			for (const box of affected) {
				box.current_pos[axis_key] = Math.max(box.current_pos[axis_key] - overlap, box.pos[axis_key]);
			}
		}

		/*
		 * Perform additional check on validity of collapsed size. Collapsing is done if there is collision between
		 * box on axis_key and box on {axis_key+scanline[size_key]} therefore box can be collapsed on collision with
		 * itself, such situation can lead to missdetection of ability to be collapsed.
		 */
		affected.sort((box1, box2) => {
			return box2.current_pos[axis_key] - box1.current_pos[axis_key];
		}).forEach((w) => {
			if (w.pos[size_key] > w.current_pos[size_key]) {
				const new_pos = {...w.current_pos};

				let size = Math.min(w.pos[size_key], size_max - w.current_pos[axis_key]);

				new_pos[size_key] = w.pos[size_key];

				affected.filter((col_box) => {
					return col_box.uniqueid !== w.uniqueid && this._rectOverlap(col_box.current_pos, new_pos);
				}).forEach((col_box) => {
					size = Math.min(size, col_box.current_pos[axis_key] - w.current_pos[axis_key]);
				});

				w.current_pos[size_key] = Math.max(size, size_min);
			}
		});

		// Resize action for left/up is mirrored right/down action, mirror coordinates back.
		if ('mirrored' in axis) {
			for (const w of widgets) {
				w.current_pos[axis_key] = size_max - w.current_pos[axis_key] - w.current_pos[size_key];
				w.pos[axis_key] = size_max - w.pos[axis_key] - w.pos[size_key];
			}
		}
	}

	/**
	 * Rearrange widgets. Modifies widget.current_pos if desired size is greater than allowed by resize.
	 *
	 * @param {object} widget        Moved widget object.
	 */
	_realignResize(widget) {
		const process_order = (widget.prev_pos.x !== widget.current_pos.x
			|| widget.prev_pos.width !== widget.current_pos.width)
			? ['x', 'y']
			: ['y', 'x'];

		let axis;
		let opposite_axis_key;
		let opposite_size_key;

		for (const w of this._data.widgets) {
			if (w.uniqueid !== widget.uniqueid) {
				w.current_pos = {...w.pos};
			}
		}

		if (widget.prev_pos.x > widget.current_pos.x) {
			widget.prev_pos.mirrored.x = true;
		}

		if (widget.prev_pos.y > widget.current_pos.y) {
			widget.prev_pos.mirrored.y = true;
		}

		// Situation when there are changes on both axes should be handled as special case.
		if (process_order[0] === 'x' && (widget.prev_pos.y !== widget.current_pos.y
			|| widget.prev_pos.height !== widget.current_pos.height)) {
			// Mark affected_axis as y if affected box is affected by only changing y position or height.
			const pos = {
				x: widget.prev_pos.x,
				y: widget.current_pos.y,
				width: widget.prev_pos.width,
				height: widget.current_pos.height
			};

			if ('width' in widget.prev_pos.axis_correction) {
				// Use 'corrected' size if it is less than current size.
				pos.width = Math.min(widget.prev_pos.axis_correction.width, pos.width);

				if ('x' in widget.prev_pos.mirrored && 'x' in widget.prev_pos.axis_correction) {
					pos.x = Math.max(widget.prev_pos.axis_correction.x, pos.x);
				}
			}

			this._data.widgets.filter((w) => {
				return !('affected_axis' in w) && widget.uniqueid !== w.uniqueid
					&& this._rectOverlap(widget.current_pos, w.current_pos);
			}).forEach((w) => {
				if (this._rectOverlap(pos, w.current_pos)) {
					w.affected_axis = 'y';
				}
			});
		}

		// Store current position as previous position for next steps.
		widget.prev_pos = {...widget.prev_pos, ...widget.current_pos};

		// Process changes for every axis.
		for (const axis_key of process_order) {
			for (const w of this._data.widgets) {
				if ('affected_axis' in w && w.affected_axis === axis_key) {
					delete w.affected_axis;
				}
			}

			axis = {
				axis_key: axis_key,
				size_key: 'width',
				size_min: 1,
				size_max: this._data.options['max-columns'],
				scanline: {
					width: this._data.options['max-columns'],
					height: this._data.options['max-rows']
				}
			};

			if (axis_key === 'y') {
				axis.size_key = 'height';
				axis.size_min = this._data.options['widget-min-rows'];
				axis.size_max = this._data.options['max-rows'];
			}

			if (axis_key in widget.prev_pos.mirrored) {
				axis.mirrored = true;
			}

			opposite_axis_key = (axis_key === 'y') ? 'x' : 'y';
			opposite_size_key = (opposite_axis_key === 'x') ? 'width' : 'height';

			if (opposite_size_key in widget.prev_pos.axis_correction) {
				// Use 'corrected' size if it is less than current size.
				widget.current_pos[opposite_size_key] = Math.min(widget.prev_pos.axis_correction[opposite_size_key],
					widget.current_pos[opposite_size_key]);

				if (opposite_axis_key in widget.prev_pos.mirrored
						&& opposite_axis_key in widget.prev_pos.axis_correction) {
					widget.current_pos[opposite_axis_key] = Math.max(widget.prev_pos.axis_correction[opposite_axis_key],
						widget.current_pos[opposite_axis_key]);
				}
			}

			this._fitWidgetsIntoBox(this._data.widgets, widget, axis);

			if ('overflow' in widget.current_pos) {
				// Store 'corrected' size.
				widget.prev_pos.axis_correction[axis.size_key] = widget.current_pos[axis.size_key];

				if (axis.mirrored) {
					widget.prev_pos.axis_correction[axis_key] = widget.current_pos[axis_key];
				}

				delete widget.current_pos.overflow;
			}
		}
	}

	_checkWidgetOverlap() {
		this._resetCurrentPositions(this._data.widgets);

		for (const w of this._data.widgets) {
			if (!this._posEquals(w.pos, w.current_pos)) {
				w.pos = w.current_pos;
				this._setDivPosition(w.div, w.pos);
			}

			delete w.current_pos;
		}
	}

	/**
	 * User action handler for resize of widget.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	_doWidgetResize(widget) {
		let pos = this._calcDivPosition(widget.div);
		let rows = 0;

		if (!this._posEquals(pos, widget.current_pos)) {
			widget.current_pos = pos;
			this._realignResize(widget);

			if (widget.iterator) {
				this._alignIteratorContents(widget, widget.current_pos);
			}

			for (const w of this._data.widgets) {
				if (widget.uniqueid !== w.uniqueid) {
					if (w.iterator) {
						const box_pos = this._calcDivPosition(w.div);

						if (box_pos.width !== w.current_pos.width || box_pos.height !== w.current_pos.height) {
							this._alignIteratorContents(w, w.current_pos);
						}
					}

					this._setDivPosition(w.div, w.current_pos);
				}

				rows = Math.max(rows, w.current_pos.y + w.current_pos.height);
			}

			if (rows != this._data.options['rows']) {
				this._resizeDashboardGrid(rows);
			}
		}

		this._setDivPosition(this._data.placeholder, pos);
	}

	/**
	 * User action handler for drag of widget.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	_doWidgetPositioning(widget) {
		let pos = this._calcDivPosition(widget.div);
		let rows = 0;

		if (!this._posEquals(pos, widget.current_pos)) {
			widget.current_pos = pos;

			if (this._realignWidget(this._data.widgets, widget, this._data.options['max-rows'])) {
				// restore last non-overflow position
				for (const w of this._data.widgets) {
					w.current_pos = {...this._data.undo_pos[w.uniqueid]};
				}
				pos = widget.current_pos;
			}
			else {
				// store all widget current_pos objects
				this._data.undo_pos = {};
				for (const w of this._data.widgets) {
					this._data.undo_pos[w.uniqueid] = {...w.current_pos};
				}

				for (const w of this._data.widgets) {
					if (widget.uniqueid !== w.uniqueid) {
						this._setDivPosition(w.div, w.current_pos);
					}

					rows = Math.max(rows, w.current_pos.y + w.current_pos.height);
				}

				if (rows > this._data.options['rows']) {
					this._data.options['rows_actual'] = rows;
					this._resizeDashboardGrid(rows);
				}
			}
		}

		this._setDivPosition(this._data.placeholder, pos);
	}

	_stopWidgetPositioning(widget) {
		this._data.placeholder.hide();
		this._data.pos_action = '';

		for (const w of this._data.widgets) {
			// Check if position of widget changed
			const new_pos = w.current_pos;
			const old_pos = w.pos;

			for (const value of ['x', 'y', 'width', 'height']) {
				if (new_pos[value] !== old_pos[value]) {
					this._data.options['updated'] = true;
					w.pos = w.current_pos;
					break;
				}
			}

			// should be present only while dragging
			delete w.current_pos;
		}

		this._setDivPosition(widget.div, widget.pos);
		this._resizeDashboardGrid();

		this._doAction('onWidgetPosition', widget);
	}

	_makeDraggable(widget) {
		widget.div.draggable({
			cursor: 'grabbing',
			handle: widget.content_header,
			scroll: true,
			scrollSensitivity: this._data.options['widget-height'],
			start: () => {
				this._$target.addClass('dashbrd-positioning');

				this._data.calculated = {
					'left-max': this._$target.width() - widget.div.width(),
					'top-max': this._data.options['max-rows'] * this._data.options['widget-height'] - widget.div.height()
				};

				this._setResizableState(this._data.widgets, 'disable');
				this._dragPrepare(this._data.widgets, widget, this._data.options['max-rows']);
				this._startWidgetPositioning(widget, 'drag');
				this._realignWidget(this._data.widgets, widget, this._data.options['max-rows']);

				widget.current_pos = {...widget.pos};
				this._data.undo_pos = {};

				for (const w of this._data.widgets) {
					this._data.undo_pos[w.uniqueid] = {...w.current_pos};
				}
			},
			drag: (e, ui) => {
				// Limit element draggable area for X and Y axis.
				ui.position = {
					left: Math.max(0, Math.min(ui.position.left, this._data.calculated['left-max'])),
					top: Math.max(0, Math.min(ui.position.top, this._data.calculated['top-max']))
				};

				this._doWidgetPositioning(widget);
			},
			stop: () => {
				delete this._data.calculated;
				delete this._data.undo_pos;

				this._data.widgets = this._sortWidgets(this._data.widgets);

				for (const w of this._data.widgets) {
					delete w.affected_by_draggable;
					delete w.affected_by_id;
					delete w.affected;
				}

				this._setResizableState(this._data.widgets, 'enable');
				this._stopWidgetPositioning(widget);

				if (widget.iterator && !widget.div.is(':hover')) {
					widget.div.removeClass('iterator-double-header');
				}

				this._data.options['rows'] = this._data.options['rows_actual'];
				this._resizeDashboardGrid(this._data.options['rows_actual']);

				this._$target.removeClass('dashbrd-positioning');
			}
		});
	}

	_makeResizable(widget) {
		const handles = {};

		for (const direction of ['n', 'e', 's', 'w', 'ne', 'se', 'sw', 'nw']) {
			const $handle = $('<div>').addClass('ui-resizable-handle').addClass(`ui-resizable-${direction}`);

			if (['n', 'e', 's', 'w'].includes(direction)) {
				$handle
					.append($('<div>', {'class': 'ui-resize-dot'}))
					.append($('<div>', {'class': `ui-resizable-border-${direction}`}));
			}

			widget.div.append($handle);
			handles[direction] = $handle;
		}

		widget.div.resizable({
			handles: handles,
			scroll: false,
			minWidth: this._getCurrentCellWidth(),
			minHeight: this._data.options['widget-min-rows'] * this._data.options['widget-height'],
			start: (e) => {
				this._doLeaveWidgetsExcept(widget);
				this._doEnterWidget(widget);

				this._$target.addClass('dashbrd-positioning');

				const handle_class = e.currentTarget.className;
				this._data.resizing_top = handle_class.match(/(^|\s)ui-resizable-(n|ne|nw)($|\s)/) !== null;
				this._data.resizing_left = handle_class.match(/(^|\s)ui-resizable-(w|sw|nw)($|\s)/) !== null;

				for (const w of this._data.widgets) {
					delete w.affected_axis;
				}

				this._setResizableState(this._data.widgets, 'disable', widget.uniqueid);
				this._startWidgetPositioning(widget, 'resize');

				widget.prev_pos = {'mirrored': {}, ...widget.pos};
				widget.prev_pos.axis_correction = {};
			},
			resize: (e, ui) => {
				// Will break fast-resizing widget-top past minimum height, if moved to start section (jQuery UI bug?)
				widget.div
					.toggleClass('resizing-top', this._data.resizing_top)
					.toggleClass('resizing-left', this._data.resizing_left);

				/*
				 * 1. Prevent physically resizing widgets beyond the allowed limits.
				 * 2. Prevent browser's vertical scrollbar from appearing when resizing right size of the widgets.
				 */

				if (ui.position.left < 0) {
					ui.size.width += ui.position.left;
					ui.position.left = 0;
				}

				if (ui.position.top < 0) {
					ui.size.height += ui.position.top;
					ui.position.top = 0;
				}

				if (this._data.resizing_top) {
					ui.position.top += Math.max(0,
						ui.size.height - this._data.options['widget-max-rows'] * this._data.options['widget-height']
					);
				}

				widget.div.css({
					'left': ui.position.left,
					'top': ui.position.top,
					'max-width': Math.min(ui.size.width,
						this._data.cell_width * this._data.options['max-columns'] - ui.position.left
					),
					'max-height': Math.min(ui.size.height,
						this._data.options['widget-max-rows'] * this._data.options['widget-height'],
						this._data.options['max-rows'] * this._data.options['widget-height'] - ui.position.top
					)
				});

				this._doWidgetResize(widget);

				widget.container.css({
					'width': this._data.placeholder.width(),
					'height': this._data.placeholder.height()
				});
			},
			stop: () => {
				this._doLeaveWidget(widget);

				delete widget.prev_pos;

				this._setResizableState(this._data.widgets, 'enable', widget.uniqueid);
				this._stopWidgetPositioning(widget);

				widget.container.removeAttr('style');

				if (widget.iterator) {
					this._alignIteratorContents(widget, widget.pos);
				}

				delete this._data.resizing_top;
				delete this._data.resizing_left;

				widget.div
					.removeClass('resizing-top')
					.removeClass('resizing-left')
					.css({
						'max-width': '',
						'max-height': ''
					});

				// Invoke onResizeEnd on every affected widget.
				for (const w of this._data.widgets) {
					if ('affected_axis' in w || w.uniqueid === widget.uniqueid) {
						this._resizeWidget(w);
					}
				}

				this._$target.removeClass('dashbrd-positioning');
			}
		});
	}

	/**
	 * Set resizable state for dashboard widgets.
	 *
	 * @param {array}  widgets   Array of all widgets.
	 * @param {string} state     Enable or disable resizable for widgets. Available values: 'enable', 'disable'.
	 * @param {string} ignoreid  All widgets except widget with such uniqueid will be affected.
	 */
	_setResizableState(widgets, state, ignoreid = '') {
		for (const w of widgets) {
			if (w.uniqueid !== ignoreid) {
				w.div.resizable(state);
			}
		}
	}

	_showPreloader(widget) {
		if (widget.iterator) {
			widget.div.find('.dashbrd-grid-iterator-content').addClass('is-loading');
		}
		else {
			widget.div.find('.dashbrd-grid-widget-content').addClass('is-loading');
		}
	}

	_hidePreloader(widget) {
		if (widget.iterator) {
			widget.div.find('.dashbrd-grid-iterator-content').removeClass('is-loading');
		}
		else {
			widget.div.find('.dashbrd-grid-widget-content').removeClass('is-loading');
		}
	}

	_startPreloader(widget, timeout) {
		timeout = timeout || widget.preloader_timeout;

		if (typeof widget.preloader_timeoutid !== 'undefined' || widget.div.find('.is-loading').length) {
			return;
		}

		widget.preloader_timeoutid = setTimeout(() => {
			delete widget.preloader_timeoutid;

			this._showPreloader(widget);
		}, timeout);
	}

	_stopPreloader(widget) {
		if (typeof widget.preloader_timeoutid !== 'undefined') {
			clearTimeout(widget.preloader_timeoutid);
			delete widget.preloader_timeoutid;
		}

		this._hidePreloader(widget);
	}

	_setUpdateWidgetContentTimer(widget, rf_rate) {
		this._clearUpdateWidgetContentTimer(widget);

		if (widget.updating_content) {
			// Waiting for another AJAX request to either complete of fail.
			return;
		}

		if (rf_rate === undefined) {
			rf_rate = widget.rf_rate;
		}

		if (rf_rate > 0) {
			widget.rf_timeoutid = setTimeout(() => {
				// Do not update widget if displaying static hintbox.
				const active = widget.content_body.find('[data-expanded="true"]');

				if (!active.length && !this._doAction('timer_refresh', widget)) {
					// No active popup or hintbox AND no triggers executed => update now.
					this._updateWidgetContent(widget);
				}
				else {
					// Active popup or hintbox OR triggers executed => just setup the next cycle.
					this._setUpdateWidgetContentTimer(widget);
				}
			}, rf_rate * 1000);
		}
	}

	_clearUpdateWidgetContentTimer(widget) {
		if (typeof widget.rf_timeoutid !== 'undefined') {
			clearTimeout(widget.rf_timeoutid);
			delete widget.rf_timeoutid;
		}
	}

	_setIteratorTooSmallState(iterator, enabled) {
		iterator.div.toggleClass('iterator-too-small', enabled);
	}

	_getIteratorTooSmallState(iterator) {
		return iterator.div.hasClass('iterator-too-small');
	}

	_numIteratorColumns(iterator) {
		return iterator.fields['columns'] ? iterator.fields['columns'] : 2;
	}

	_numIteratorRows(iterator) {
		return iterator.fields['rows'] ? iterator.fields['rows'] : 1;
	}

	_isIteratorTooSmall(iterator, pos) {
		return pos.width < this._numIteratorColumns(iterator)
			|| pos.height < this._numIteratorRows(iterator) * this._data.options['widget-min-rows'];
	}

	_addIteratorPlaceholders(iterator, count) {
		$('.dashbrd-grid-iterator-placeholder', iterator.content_body).remove();

		for (let index = 0; index < count; index++) {
			iterator.content_body.append($('<div>', {'class': 'dashbrd-grid-iterator-placeholder'})
				.append('<div>')
				.on('mouseenter', () => {
					// Set single-line header for the iterator.
					iterator.div.removeClass('iterator-double-header');

					if (this._data.options['kioskmode'] && iterator.div.position().top === 0) {
						this._slideKiosk();
					}
				})
			);
		}
	}

	_alignIteratorContents(iterator, pos) {
		if (this._isIteratorTooSmall(iterator, pos)) {
			this._setIteratorTooSmallState(iterator, true);

			return;
		}

		if (this._getIteratorTooSmallState(iterator) && iterator.update_pending) {
			this._setIteratorTooSmallState(iterator, false);
			this._showPreloader(iterator);
			this._updateWidgetContent(iterator);

			return;
		}

		this._setIteratorTooSmallState(iterator, false);

		const $placeholders = iterator.content_body.find('.dashbrd-grid-iterator-placeholder');
		const num_columns = this._numIteratorColumns(iterator);
		const num_rows = this._numIteratorRows(iterator);

		for (let index = 0, count = num_columns * num_rows; index < count; index++) {
			const cell_column = index % num_columns;
			const cell_row = Math.floor(index / num_columns);
			const cell_width_min = Math.floor(pos.width / num_columns);
			const cell_height_min = Math.floor(pos.height / num_rows);

			const num_enlarged_columns = pos.width - cell_width_min * num_columns;
			const num_enlarged_rows = pos.height - cell_height_min * num_rows;

			const x = cell_column * cell_width_min + Math.min(cell_column, num_enlarged_columns);
			const y = cell_row * cell_height_min + Math.min(cell_row, num_enlarged_rows);
			const width = cell_width_min + (cell_column < num_enlarged_columns ? 1 : 0);
			const height = cell_height_min + (cell_row < num_enlarged_rows ? 1 : 0);

			let css = {
				left: `${x / pos.width * 100}%`,
				top: `${y * this._data.options['widget-height']}px`,
				width: `${width / pos.width * 100}%`,
				height: `${height * this._data.options['widget-height']}px`
			};

			if (cell_column === (num_columns - 1)) {
				// Setting right side for last column of widgets (fixes IE11 and Opera issues).
				css = {
					...css,
					'width': 'auto',
					'right': '0px'
				};
			}
			else {
				css = {
					...css,
					'width': `${Math.round(width / pos.width * 100 * 100) / 100}%`,
					'right': 'auto'
				};
			}

			if (index < iterator.children.length) {
				iterator.children[index].div.css(css);
			}
			else {
				$placeholders.eq(index - iterator.children.length).css(css);
			}
		}
	}

	_addWidgetOfIterator(iterator, child) {
		// Replace empty arrays (or anything non-object) with empty objects.
		if (typeof child.fields !== 'object') {
			child.fields = {};
		}
		if (typeof child.configuration !== 'object') {
			child.configuration = {};
		}

		child = {
			'widgetid': '',
			'type': '',
			'header': '',
			'view_mode': iterator.view_mode,
			'preloader_timeout': 10000,	// in milliseconds
			'update_paused': false,
			'initial_load': true,
			'ready': false,
			'storage': {},
			...child,
			'iterator': false,
			'parent': iterator,
			'new_widget': false
		};

		child.uniqueid = this._generateUniqueId();
		child.div = this._makeWidgetDiv(child);

		iterator.content_body.append(child.div);
		iterator.children.push(child);

		this._showPreloader(child);
	}

	_hasEqualProperties(object_1, object_2) {
		if (Object.keys(object_1).length !== Object.keys(object_2).length) {
			return false;
		}

		for (const key in object_1) {
			if (object_1[key] !== object_2[key]) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Clear and reset the state of the iterator.
	 */
	_clearIterator(iterator) {
		for (const child of iterator.children) {
			this._removeWidget(child);
		}

		iterator.content_body.empty();
		iterator.children = [];

		iterator.div.removeClass('iterator-alt-content');
	}

	_updateIteratorCallback(iterator, response, options) {
		const has_alt_content = typeof response.messages !== 'undefined' || typeof response.body !== 'undefined';

		if (has_alt_content || this._getIteratorTooSmallState(iterator)) {
			this._clearIterator(iterator);

			if (has_alt_content) {
				const $alt_content = $('<div>');

				if (typeof response.messages !== 'undefined') {
					$alt_content.append(response.messages);
				}
				if (typeof response.body !== 'undefined') {
					$alt_content.append(response.body);
				}
				iterator.content_body.append($alt_content);
				iterator.div.addClass('iterator-alt-content');

				iterator.page = 1;
				iterator.page_count = 1;
				this._updateIteratorPager(iterator);
			}
			else {
				iterator.update_pending = true;
			}

			return;
		}

		if (iterator.div.hasClass('iterator-alt-content')) {
			// Returning from alt-content to normal mode.
			this._clearIterator(iterator);
		}

		iterator.page = response.page;
		iterator.page_count = response.page_count;
		this._updateIteratorPager(iterator);

		const current_children = iterator.children;
		const current_children_by_widgetid = {};

		iterator.children = [];

		for (const child of current_children) {
			if (child.widgetid !== '') {
				current_children_by_widgetid[child.widgetid] = child;
			}
			else {
				// Child widgets without 'uniqueid' are never persisted.
				this._removeWidget(child);
			}
		}

		const reused_widgetids = [];

		response.children.slice(0, this._numIteratorColumns(iterator) * this._numIteratorRows(iterator))
			.forEach((child) => {
				if (typeof child.widgetid !== 'undefined' && current_children_by_widgetid[child.widgetid]
					&& this._hasEqualProperties(
						child.fields, current_children_by_widgetid[child.widgetid].fields)
					) {

					// Reuse widget, if it has 'widgetid' supplied, has exactly the same fields and fields data.
					// Please note, that the order of widgets inside of iterator['content_body'] is not important,
					// since the absolute positioning is done based on widget order in the iterator['children'].

					iterator.children.push(current_children_by_widgetid[child.widgetid]);
					reused_widgetids.push(child.widgetid);
				}
				else {
					this._addWidgetOfIterator(iterator, child);
				}
			});

		for (const child of current_children_by_widgetid) {
			if (reused_widgetids.includes(child.widgetid)) {
				this._removeWidget(child);
			}
		}

		this._addIteratorPlaceholders(iterator,
			this._numIteratorColumns(iterator) * this._numIteratorRows(iterator) - iterator.children.length
		);

		this._alignIteratorContents(iterator,
			(typeof iterator.current_pos === 'object') ? iterator.current_pos : iterator.pos
		);

		for (const child of iterator.children) {
			/* Possible update policies for the child widgets:
				resize: execute 'onResizeEnd' action (widget won't update if there's no trigger or size hasn't changed).
					- Is used to propagate iterator's resize event.

				refresh: either execute 'timer_refresh' action (if trigger exists) or updateWidgetContent.
					- Is used when widget surely hasn't been resized, but needs to be refreshed.

				resize_or_refresh: either execute 'onResizeEnd' or 'timer_refresh' action, or updateWidgetContent.
					- Is used when widget might have been resized, and needs to be refreshed anyway.
			*/

			let update_policy = 'refresh';

			if (reused_widgetids.includes(child.widgetid) && 'update_policy' in options) {
				// Allow to override update_policy only for existing (not new) widgets.
				update_policy = options['update_policy'];
			}

			let success = false;
			switch (update_policy) {
				case 'resize':
				case 'resize_or_refresh':
					success = this._resizeWidget(child);
					if (update_policy === 'resize') {
						success = true;
					}
					if (success) {
						break;
					}
				// No break here.

				case 'refresh':
					success = this._doAction('timer_refresh', child);
					break;
			}

			if (!success) {
				// No triggers executed for the widget, therefore update the conventional way.
				this._updateWidgetContent(child);
			}
		}
	}

	_updateWidgetCallback(widget, response) {
		widget.content_body.empty();
		if (typeof response.messages !== 'undefined') {
			widget.content_body.append(response.messages);
		}
		widget.content_body.append(response.body);

		if (typeof response.debug !== 'undefined') {
			$(response.debug).appendTo(widget.content_body);
		}

		this._removeWidgetInfoButtons(widget.content_header);
		if (typeof response.info !== 'undefined' && !this._data.options['edit_mode']) {
			this._addWidgetInfoButtons(widget.content_header, response.info);
		}

		// Creates new script elements and removes previous ones to force their re-execution.
		widget.content_script.empty();
		if (typeof response.script_inline !== 'undefined') {
			// NOTE: to execute script with current widget context, add unique ID for required div, and use it in script.
			widget.content_script.append($('<script>').text(response.script_inline));
		}
	}

	_isDeletedWidget(widget) {
		let search_widgets = this._data.widgets;

		if (widget.parent) {
			if (this._isDeletedWidget(widget.parent)) {
				return true;
			}

			search_widgets = widget.parent.children;
		}

		const widgets_found = search_widgets.filter(function(w) {
			return (w.uniqueid === widget.uniqueid);
		});

		return !widgets_found.length;
	}

	_setWidgetReady(widget) {
		if (widget.ready) {
			return;
		}


		const dashboard_was_ready = !this._data.widgets.filter((w) => {
			return !w.ready;
		}).length;

		let ready_updated = false;

		if (widget.iterator) {
			if (!widget.children.length) {
				// Set empty iterator to ready state.

				ready_updated = !widget.ready;
				widget.ready = true;
			}
		}
		else if (widget.parent) {
			widget.ready = true;

			let children = widget.parent.children,
				children_not_ready = children.filter(function(widget) {
					return !widget.ready;
				});

			if (!children_not_ready.length) {
				// Set parent iterator to ready state.

				ready_updated = !widget.parent.ready;
				widget.parent.ready = true;
			}
		}
		else {
			ready_updated = !widget.ready;
			widget.ready = true;
		}

		if (ready_updated) {
			/*
			 * The conception:
			 *   - Hold 'registerDataExchangeCommit' until all widgets are loaded.
			 *   - Call 'registerDataExchangeCommit' and 'onDashboardReady' once, as soon as all widgets are loaded.
			 *   - Call 'registerDataExchangeCommit' and 'onDashboardReady' for each new widget added in edit mode.
			 */

			if (dashboard_was_ready) {
				this._registerDataExchangeCommit();
			}
			else {
				const dashboard_is_ready = !this._data.widgets.filter(function(widget) {
					return !widget.ready;
				}).length;

				if (dashboard_is_ready) {
					this._registerDataExchangeCommit();
					this._doAction('onDashboardReady');
				}
			}
		}
	}

	_registerDataExchangeCommit() {
		const used_indexes = []

		let erase = false;

		this._data.widget_relation_submissions.forEach((rel, rel_index) => {
			erase = false;

			// No linked widget reference given. Just register as data receiver.
			if (typeof rel.linkedto === 'undefined') {
				if (typeof this._data.widget_relations.tasks[rel.uniqueid] === 'undefined') {
					this._data.widget_relations.tasks[rel.uniqueid] = [];
				}

				this._data.widget_relations.tasks[rel.uniqueid].push({
					data_name: rel.data_name,
					callback: rel.callback
				});
				erase = true;
			}
			/*
			 * Linked widget reference is given. Register two direction relationship as well as
			 * register data receiver.
			 */
			else {
				for (const w of this._data.widgets) {
					if (typeof w.fields.reference !== 'undefined' && w.fields.reference === rel.linkedto) {
						if (typeof this._data.widget_relations.relations[w.uniqueid] === 'undefined') {
							this._data.widget_relations.relations[w.uniqueid] = [];
						}
						if (typeof this._data.widget_relations.relations[rel.uniqueid] === 'undefined') {
							this._data.widget_relations.relations[rel.uniqueid] = [];
						}
						if (typeof this._data.widget_relations.tasks[rel.uniqueid] === 'undefined') {
							this._data.widget_relations.tasks[rel.uniqueid] = [];
						}

						this._data.widget_relations.relations[w.uniqueid].push(rel.uniqueid);
						this._data.widget_relations.relations[rel.uniqueid].push(w.uniqueid);
						this._data.widget_relations.tasks[rel.uniqueid].push({
							data_name: rel.data_name,
							callback: rel.callback
						});
						erase = true;
					}
				}
			}

			if (erase) {
				used_indexes.push(rel_index);
			}
		});

		for (let i = used_indexes.length - 1; i >= 0; i--) {
			this._data.widget_relation_submissions.splice(used_indexes[i], 1);
		}

		this.callWidgetDataShare();
	}

	_getWidgetContentSize(widget) {
		return {
			'content_width': Math.floor(widget.content_body.width()),
			'content_height': Math.floor(widget.content_body.height())
		};
	}

	_isEqualContentSize(size_1, size_2) {
		if (size_1 === undefined || size_2 === undefined) {
			return false;
		}

		return size_1.content_width === size_2.content_width && size_1.content_height === size_2.content_height;
	}

	_updateWidgetContent(widget, options = {}) {
		this._clearUpdateWidgetContentTimer(widget);

		if (widget.updating_content) {
			// Waiting for another AJAX request to either complete or fail.
			return;
		}

		if (widget.update_paused) {
			this._setUpdateWidgetContentTimer(widget);

			return;
		}

		if (widget.iterator) {
			const pos = (typeof widget.current_pos === 'object') ? widget.current_pos : widget.pos;

			if (this._isIteratorTooSmall(widget, pos)) {
				this._clearIterator(widget);

				this._stopPreloader(widget);
				this._setIteratorTooSmallState(widget, true);
				widget.update_pending = true;

				return;
			}
			else {
				this._setIteratorTooSmallState(widget, false);
				widget.update_pending = false;
			}
		}

		const url = new Curl('zabbix.php');
		url.setArgument('action', `widget.${widget.type}.view`);

		let ajax_data = {
			'templateid': this._data.dashboard.templateid !== null
				? this._data.dashboard.templateid
				: undefined,
			'dashboardid': this._data.dashboard.dashboardid !== null
				? this._data.dashboard.dashboardid
				: undefined,
			'dynamic_hostid': this._data.dashboard.dynamic_hostid !== null
				? this._data.dashboard.dynamic_hostid
				: undefined,
			'widgetid': (widget.widgetid !== '') ? widget.widgetid : undefined,
			'uniqueid': widget.uniqueid,
			'name': (widget.header !== '') ? widget.header : undefined,
			'initial_load': widget.initial_load ? 1 : 0,
			'edit_mode': this._data.options.edit_mode ? 1 : 0,
			'storage': widget.storage,
			'view_mode': widget.view_mode
		};

		widget.content_size = this._getWidgetContentSize(widget);

		if (widget.iterator) {
			ajax_data.page = widget.page;
		}
		else {
			ajax_data = {...ajax_data, ...widget.content_size};
		}

		if ('fields' in widget && Object.keys(widget.fields).length !== 0) {
			ajax_data.fields = JSON.stringify(widget.fields);
		}

		this._setDashboardBusy('updateWidgetContent', widget.uniqueid);

		this._startPreloader(widget);

		widget.updating_content = true;

		const request = $.ajax({
			url: url.getUrl(),
			method: 'POST',
			data: ajax_data,
			dataType: 'json'
		});

		request
			.then((response) => {
				delete widget.updating_content;

				this._stopPreloader(widget);

				if (this._isDeletedWidget(widget)) {
					return $.Deferred().reject();
				}

				const $content_header = $('h4', widget.content_header);

				$content_header.text(response.header);
				if (typeof response.aria_label !== 'undefined') {
					$content_header.attr('aria-label', (response.aria_label !== '') ? response.aria_label : null);
				}

				if (widget.iterator) {
					this._updateIteratorCallback(widget, response, options);
				}
				else {
					this._updateWidgetCallback(widget, response);
				}

				this._doAction('onContentUpdated');
			})
			.then(() => {
				// Separate 'then' section allows to execute JavaScripts added by widgets in previous section first.

				this._setWidgetReady(widget);

				if (!widget.parent) {
					// Iterator child widgets are excluded here.
					this._setUpdateWidgetContentTimer(widget);
				}

				// The widget is loaded now, although possibly already resized.
				widget.initial_load = false;

				if (!widget.iterator) {
					// Update the widget, if it was resized before it was fully loaded.
					this._resizeWidget(widget);
				}

				// Call refreshCallback handler for expanded popup menu items.
				if (this._$target.find('[data-expanded="true"][data-menu-popup]').length) {
					this._$target.find('[data-expanded="true"][data-menu-popup]').menuPopup('refresh', widget);
				}
			})
			.always(() => {
				this._clearDashboardBusy('updateWidgetContent', widget.uniqueid);
			});

		request.fail(() => {
			delete widget.updating_content;
			this._setUpdateWidgetContentTimer(widget, 3);
		});
	}

	/**
	 * Smoothly scroll object of given position and dimension into view and return a promise.
	 *
	 * @param {object} pos   Object with position and dimension.
	 *
	 * @returns {object}  jQuery Deferred object.
	 */
	_promiseScrollIntoView(pos) {
		const $wrapper = $('.wrapper');

		const offset_top = $wrapper.scrollTop() + this._$target.offset().top;
		const margin = 5;  // Allow 5px free space around the object.
		const widget_top = offset_top + pos.y * this._data.options['widget-height'] - margin;
		const widget_height = pos.height * this._data.options['widget-height'] + margin * 2;
		const wrapper_height = $wrapper.height();
		const wrapper_scrollTop = $wrapper.scrollTop();
		const wrapper_scrollTop_min = Math.max(0, widget_top + Math.min(0, widget_height - wrapper_height));
		const wrapper_scrollTop_max = widget_top;

		if (pos.y + pos.height > this._data.options['rows']) {
			this._resizeDashboardGrid(pos.y + pos.height);
		}

		if (wrapper_scrollTop < wrapper_scrollTop_min) {
			return $wrapper.animate({scrollTop: wrapper_scrollTop_min}).promise();
		}
		else if (wrapper_scrollTop > wrapper_scrollTop_max) {
			return $wrapper.animate({scrollTop: wrapper_scrollTop_max}).promise();
		}
		else {
			return $.Deferred().resolve();
		}
	}

	/**
	 * @param {object} widget
	 */
	_updateWidgetConfig(widget) {
		if (this._data.options['updating_config']) {
			// Waiting for another AJAX request to either complete of fail.
			return;
		}

		const fields = $('form', this._data.dialogue.body).serializeJSON();
		const type = fields['type'];
		const name = fields['name'];
		const view_mode = (fields['show_header'] == 1)
			? ZBX_WIDGET_VIEW_MODE_NORMAL
			: ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER;

		delete fields['type'];
		delete fields['name'];
		delete fields['show_header'];

		let pos;

		if (widget === null || !(('type' in widget) && ('pos' in widget))) {
			const area_size = {
				'width': this._data.widget_defaults[type].size.width,
				'height': this._data.widget_defaults[type].size.height
			};

			pos = this._findEmptyPosition(area_size);
			if (!pos) {
				this._showDialogMessageExhausted();

				return;
			}
		}

		this._data.options['updating_config'] = true;

		// Prepare to call dashboard.widget.check.

		const url = new Curl('zabbix.php');
		url.setArgument('action', 'dashboard.widget.check');

		const ajax_data = {
			templateid: this._data.dashboard.templateid || undefined,
			type: type,
			name: name,
			view_mode: view_mode
		};

		if (Object.keys(fields).length !== 0) {
			ajax_data.fields = JSON.stringify(fields);
		}

		const $save_btn = this._data.dialogue.div.find('.dialogue-widget-save');
		const overlay = overlays_stack.getById('widgetConfg');

		$save_btn.prop('disabled', true);

		overlay.setLoading();
		overlay.xhr = $.ajax({
			url: url.getUrl(),
			method: 'POST',
			dataType: 'json',
			data: ajax_data
		});

		overlay.xhr
			.then((response) => {
				if ('errors' in response) {
					// Error returned. Remove previous errors.

					$('.msg-bad', this._data.dialogue.body).remove();

					if (response.errors !== '') {
						this._data.dialogue.body.prepend(response.errors);
					}

					$save_btn.prop('disabled', false);

					return $.Deferred().reject();
				}
				else {
					// Set view mode of a reusable widget early to escape focus flickering.
					if (widget !== null && widget.type === type) {
						this._setWidgetViewMode(widget, view_mode);

						this._doLeaveWidgetsExcept(widget);
						this._doEnterWidget(widget);
					}
				}
			})
			.then(() => {
				// Prepare to call dashboard.widget.configure.
				const url = new Curl('zabbix.php');

				url.setArgument('action', 'dashboard.widget.configure');

				const ajax_data = {
					templateid: this._data.dashboard.templateid || undefined,
					type: type,
					view_mode: view_mode
				};

				if (Object.keys(fields).length !== 0) {
					ajax_data.fields = JSON.stringify(fields);
				}

				return $.ajax({
					url: url.getUrl(),
					method: 'POST',
					dataType: 'json',
					data: ajax_data
				});
			})
			.then((response) => {
				overlayDialogueDestroy('widgetConfg');

				let configuration = response.configuration || {};

				if (widget === null || !('type' in widget)) {
					// In case of ADD widget, create and add widget to the dashboard.

					if (widget && 'pos' in widget) {
						pos = {...this._data.widget_defaults[type].size, ...widget.pos};

						this._data.widgets.filter((w) => {
							return this._rectOverlap(w.pos, pos);
						}).forEach((w) => {
							if (this._rectOverlap(w.pos, pos)) {
								if (pos.x + pos.width > w.pos.x && pos.x < w.pos.x) {
									pos.width = w.pos.x - pos.x;
								}
								else if (pos.y + pos.height > w.pos.y && pos.y < w.pos.y) {
									pos.height = w.pos.y - pos.y;
								}
							}
						});

						pos.width = Math.min(this._data.options['max-columns'] - pos.x, pos.width);
						pos.height = Math.min(this._data.options['max-rows'] - pos.y, pos.height);
					}

					const widget_data = {
						'type': type,
						'header': name,
						'view_mode': view_mode,
						'pos': pos,
						'fields': fields,
						'configuration': configuration
					};

					this._promiseScrollIntoView(pos)
						.then(() => {
							this.addWidget(widget_data);
							this._data.pos_action = '';

							// New widget is last element in data['widgets'] array.
							widget = this._data.widgets.slice(-1)[0];
							this._setWidgetModeEdit(widget);
							this._updateWidgetContent(widget);
						});
				}
				else if (widget.type === type) {
					// In case of EDIT widget, if type has not changed, update the widget.

					widget.header = name;
					widget.fields = fields;

					// Set preloader to widget content after overlayDialogueDestroy as fast as we can.
					this._startPreloader(widget, 100);

					// View mode was just set after the overlayDialogueDestroy was called in first 'then' section.

					this._applyWidgetConfiguration(widget, configuration);
					this._doAction('afterUpdateWidgetConfig');

					if (widget.iterator) {
						this._updateWidgetContent(widget, {
							'update_policy': 'resize_or_refresh'
						});
					}
					else {
						this._updateWidgetContent(widget);
					}
				}
				else {
					// In case of EDIT widget, if type has changed, replace the widget.

					this._removeWidget(widget);

					const widget_data = {
						'type': type,
						'header': name,
						'view_mode': view_mode,
						'pos': widget.pos,
						'fields': fields,
						'configuration': configuration,
						'new_widget': false
					};

					// Disable position/size checking during addWidget call.
					this._data.pos_action = 'updateWidgetConfig';
					this.addWidget(widget_data);
					this._data.pos_action = '';

					// New widget is last element in data['widgets'] array.
					widget = this._data.widgets.slice(-1)[0];
					this._setWidgetModeEdit(widget);
					this._updateWidgetContent(widget);
				}

				this._data.options['updated'] = true;
			})
			.always(() => {
				$save_btn.prop('disabled', false);
				delete this._data.options['updating_config'];
				overlay.unsetLoading();
			});
	}

	/**
	 * Find first empty position of the given size.
	 *
	 * @param {{width: (*|number|number), height: (*|number|number)}} area_size
	 * @param {int} area_size[width]
	 * @param {int} area_size[height]
	 *
	 * @returns {object|boolean}  area_size object extended with position or false in case if no empty space is found.
	 */
	_findEmptyPosition(area_size) {
		const pos = {...area_size, 'x': 0, 'y': 0};

		// Go y by row and try to position widget in each space.
		const max_col = this._data.options['max-columns'] - pos.width;
		const max_row = this._data.options['max-rows'] - pos.height;

		let found = false;
		let x, y;

		for (y = 0; !found; y++) {
			if (y > max_row) {
				return false;
			}
			for (x = 0; x <= max_col && !found; x++) {
				pos.x = x;
				pos.y = y;
				found = this._isPosFree(pos);
			}
		}

		return pos;
	}

	_isPosFree(pos) {
		for (const w of this._data.widgets) {
			if (this._rectOverlap(pos, w.pos)) {
				return false;
			}
		}

		return true;
	}

	_openConfigDialogue(widget, trigger_element) {
		this._doAction('beforeConfigLoad', widget);

		this._data.options['config_dialogue_active'] = true;

		const config_dialogue_close = () => {
			delete this._data.options['config_dialogue_active'];
			$.unsubscribe('overlay.close', config_dialogue_close);

			this._resetNewWidgetPlaceholderState();
		};

		$.subscribe('overlay.close', config_dialogue_close);

		const edit_mode = (widget !== null && 'type' in widget);

		this._data.dialogue = {widget: widget};

		const overlay = overlayDialogue({
			'title': edit_mode ? t('Edit widget') : t('Add widget'),
			'class': 'modal-popup modal-popup-generic',
			'content': jQuery('<div>', {'height': '68px'}),
			'buttons': [
				{
					'title': edit_mode ? t('Apply') : t('Add'),
					'class': 'dialogue-widget-save',
					'keepOpen': true,
					'isSubmit': true,
					'action': () => {
						this._updateWidgetConfig(widget);
					}
				},
				{
					'title': t('Cancel'),
					'class': 'btn-alt',
					'action': () => {
						// Clear action.
						this._data.pos_action = '';
					}
				}
			],
			'dialogueid': 'widgetConfg'
		}, trigger_element);

		overlay.setLoading();

		this._data.dialogue.div = overlay.$dialogue;
		this._data.dialogue.body = overlay.$dialogue.$body;

		this.updateWidgetConfigDialogue();
	}

	_editDashboard() {
		this._$target.addClass('dashbrd-mode-edit');

		// Recalculate minimal height and expand dashboard to the whole screen.
		this._data.minimalHeight = this._calculateGridMinHeight();

		this._resizeDashboardGrid();

		for (const w of this._data.widgets) {
			w.rf_rate = 0;
			this._setWidgetModeEdit(w);
		}

		this._data.pos_action = '';
		this._data.cell_width = this._getCurrentCellWidth();
		this._data.add_widget_dimension = {};

		// Add new widget user interaction handlers.
		$.subscribe('overlay.close', (e, dialogue) => {
			if (this._data.pos_action === 'addmodal' && dialogue.dialogueid === 'widgetConfg') {
				this._resetNewWidgetPlaceholderState();
			}
		});

		$(document).on('click mouseup dragend', (e) => {
			if (this._data.pos_action !== 'add') {
				return;
			}

			const dimension = {...this._data.add_widget_dimension};

			this._data.pos_action = 'addmodal';
			this._setResizableState(this._data.widgets, 'enable');

			if (this.getCopiedWidget() !== null) {
				const menu = getDashboardWidgetActionMenu(dimension);
				const options = {
					position: {
						of: this._data.new_widget_placeholder.getObject(),
						my: ['left', 'top'],
						at: ['right', 'bottom'],
						collision: 'fit'
					},
					closeCallback: () => {
						this._data.pos_action = '';

						if (!this._data.options['config_dialogue_active']) {
							this._resetNewWidgetPlaceholderState();
						}
					}
				};

				// Adopt menu position to direction in which placeholder was drawn.
				if (dimension.x + dimension.width >= this._data.options['max-columns'] - 4) {
					options.position.my[0] = (dimension.left > dimension.x) ? 'left' : 'right';
				}
				if (dimension.left > dimension.x) {
					options.position.at[0] = 'left';
				}

				if (dimension.y === 0) {
					options.position.my[1] = 'top';
					options.position.at[1] = (dimension.top > dimension.y) ? 'top' : 'bottom';
				}
				else if (dimension.top > dimension.y) {
					options.position.my[1] = 'bottom';
					options.position.at[1] = 'top';
				}

				options.position.my = options.position.my.join(' ');
				options.position.at = options.position.at.join(' ');

				this._data.new_widget_placeholder.getObject().menuPopup(menu, e, options);
			}
			else {
				this.addNewWidget(null, dimension);
			}
		});

		this._$target
			.on('mousedown', (e) => {
				const $target = $(e.target);

				if (e.which !== 1 || this._data.pos_action !== ''
					|| (!$target.is(this._data.new_widget_placeholder.getObject())
						&& !this._data.new_widget_placeholder.getObject().has($target).length)) {
					return;
				}

				this._setResizableState(this._data.widgets, 'disable');

				this._data.pos_action = 'add';

				delete this._data.add_widget_dimension.left;
				delete this._data.add_widget_dimension.top;

				this._data.new_widget_placeholder
					.setState(this._data.new_widget_placeholder.STATE_RESIZING)
					.showAtPosition(this._data.add_widget_dimension);

				return false;
			})
			.on('mouseleave', () => {
				if (this._data.pos_action) {
					return;
				}

				this._data.add_widget_dimension = {};
				this._resetNewWidgetPlaceholderState();
			})
			.on('mouseenter mousemove', (e) => {
				const $target = $(e.target);

				if (this._data.pos_action !== '' && this._data.pos_action !== 'add') {
					return;
				}

				if (this._data.pos_action !== 'add' && this._data.pos_action !== 'addmodal' && !$target.is(this._$target)
					&& !$target.is(this._data.new_widget_placeholder.getObject())
					&& !this._data.new_widget_placeholder.getObject().has($target).length) {
					this._data.add_widget_dimension = {};
					this._data.new_widget_placeholder.hide();
					this._resizeDashboardGrid();

					return;
				}

				const offset = this._$target.offset();

				let y = Math.min(this._data.options['max-rows'] - 1,
						Math.max(0, Math.floor((e.pageY - offset.top) / this._data.options['widget-height']))
					);
				let x = Math.min(this._data.options['max-columns'] - 1,
						Math.max(0, Math.floor((e.pageX - offset.left) / this._data.cell_width))
					);
				let overlap = false;

				if (isNaN(x) || isNaN(y)) {
					return;
				}

				let pos = {
					x: x,
					y: y,
					width: (x < this._data.options['max-columns'] - 1) ? 1 : 2,
					height: this._data.options['widget-min-rows']
				};

				if (this._data.pos_action === 'add') {
					if (!('top' in this._data.add_widget_dimension)) {
						this._data.add_widget_dimension.left = x;
						this._data.add_widget_dimension.top = Math.min(y, this._data.add_widget_dimension.y);
					}

					pos = {
						x: Math.min(x, (this._data.add_widget_dimension.left < x)
							? this._data.add_widget_dimension.x
							: this._data.add_widget_dimension.left
						),
						y: Math.min(y, (this._data.add_widget_dimension.top < y)
							? this._data.add_widget_dimension.y
							: this._data.add_widget_dimension.top
						),
						width: Math.max(1, (this._data.add_widget_dimension.left < x)
							? x - this._data.add_widget_dimension.left + 1
							: this._data.add_widget_dimension.left - x + 1
						),
						height: Math.max(2, (this._data.add_widget_dimension.top < y)
							? y - this._data.add_widget_dimension.top + 1
							: this._data.add_widget_dimension.top - y + 2
						)
					};

					for (const box of this._data.widgets) {
						overlap |= this._rectOverlap(box.pos, pos);

						if (!overlap) {
							break;
						}
					}

					if (overlap) {
						pos = this._data.add_widget_dimension;
					}
				}
				else {
					if ((pos.x + pos.width) > this._data.options['max-columns']) {
						pos.x = this._data.options['max-columns'] - pos.width;
					}
					else if (this._data.add_widget_dimension.x < pos.x) {
						--pos.x;
					}

					if ((pos.y + pos.height) > this._data.options['max-rows']) {
						pos.y = this._data.options['max-rows'] - pos.height;
					}
					else if (this._data.add_widget_dimension.y < pos.y) {
						--pos.y;
					}

					/*
					 * If there is collision make additional check to ensure that mouse is not at the bottom of 1x2 free
					 * slot.
					 */
					const delta_check = [
						[0, 0, 2],
						[-1, 0, 2],
						[0, 0, 1],
						[0, -1, 2],
						[0, -1, 1]
					];

					for (const val of delta_check) {
						const c_pos = {
							x: Math.max(0, (val[2] < 2 ? x : pos.x) + val[0]),
							y: Math.max(0, pos.y + val[1]),
							width: val[2],
							height: pos.height
						};

						if (x > c_pos.x + 1) {
							++c_pos.x;
						}

						overlap = false;

						if (this._rectOverlap({
							x: 0,
							y: 0,
							width: this._data.options['max-columns'],
							height: this._data.options['max-rows']
						}, c_pos)) {
							for (const box of this._data.widgets) {
								overlap |= this._rectOverlap(box.pos, c_pos);

								if (!overlap) {
									break;
								}
							}
						}

						if (!overlap) {
							pos = c_pos;

							return false;
						}
					}

					if (overlap) {
						this._data.add_widget_dimension = {};
						this._data.new_widget_placeholder.hide();

						return;
					}
				}

				if ((pos.y + pos.height) > this._data.options['rows']) {
					this._resizeDashboardGrid(pos.y + pos.height);
				}

				this._data.add_widget_dimension = {...this._data.add_widget_dimension, ...pos};

				// Hide widget headers, not to interfere with the new widget placeholder.
				this._doLeaveWidgetsExcept(null);

				this._data.new_widget_placeholder
					.setState((this._data.pos_action === 'add')
						? this._data.new_widget_placeholder.STATE_RESIZING
						: this._data.new_widget_placeholder.STATE_POSITIONING
					)
					.showAtPosition(this._data.add_widget_dimension);
			});
	}

	_setWidgetModeEdit(widget) {
		this._clearUpdateWidgetContentTimer(widget);

		if (!widget.iterator) {
			this._removeWidgetInfoButtons(widget.content_header);
		}

		this._makeDraggable(widget);
		this._makeResizable(widget);
		this._resizeWidget(widget);
	}

	/**
	 * Remove widget actions added by addAction.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	_removeWidgetActions(widget) {
		for (const hook_name in this._data.triggers) {
			for (let i = 0; i < this._data.triggers[hook_name].length; i++) {
				if (widget.uniqueid === this._data.triggers[hook_name][i].uniqueid) {
					this._data.triggers[hook_name].splice(i, 1);
				}
			}
		}
	}

	/**
	 * Enable user functional interaction with widget.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	_enableWidgetControls(widget) {
		widget.content_header.find('button').prop('disabled', false);
	}

	/**
	 * Disable user functional interaction with widget.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	_disableWidgetControls(widget) {
		widget.content_header.find('button').prop('disabled', true);
	}

	/**
	 * Remove the widget without updating the dashboard.
	 */
	_removeWidget(widget) {
		if (widget.iterator) {
			for (const child of widget.children) {
				this._doAction('onWidgetDelete', child);
				this._removeWidgetActions(child);
				child.div.remove();
			}
		}

		if (widget.parent) {
			this._doAction('onWidgetDelete', widget);
			this._removeWidgetActions(widget);
			widget.div.remove();
		}
		else {
			const index = widget.div.data('widget-index');

			this._doAction('onWidgetDelete', widget);
			this._removeWidgetActions(widget);
			widget.div.remove();

			this._data.widgets.splice(index, 1);

			for (let i = index; i < this._data.widgets.length; i++) {
				this._data.widgets[i].div.data('widget-index', i);
			}
		}
	}

	/**
	 * Delete the widget and update the dashboard.
	 */
	_deleteWidget(widget) {
		this._removeWidget(widget);

		if (!widget.parent) {
			this._data.options['updated'] = true;

			this._resizeDashboardGrid();
			this._resetNewWidgetPlaceholderState();
		}
	}

	_generateUniqueId() {
		let ref = false;

		while (!ref) {
			ref = this._generateRandomString(5);

			for (const w of this._data.widgets) {
				if (w.uniqueid === ref) {
					ref = false;
					break;
				}
			}
		}

		return ref;
	}

	_onIteratorResizeEnd(iterator) {
		this._updateIteratorPager(iterator);

		if (this._getIteratorTooSmallState(iterator)) {
			return;
		}

		this._updateWidgetContent(iterator, {
			update_policy: 'resize'
		});
	}

	_resizeWidget(widget) {
		let success = false;

		if (widget.iterator) {
			// Iterators will sync first, then selectively propagate the resize event to the child widgets.
			success = this._doAction('onResizeEnd', widget);
		}
		else {
			const size_old = widget.content_size;
			const size_new = this._getWidgetContentSize(widget);

			if (!this._isEqualContentSize(size_old, size_new)) {
				success = this._doAction('onResizeEnd', widget);

				if (success) {
					widget.content_size = size_new;
				}
			}
		}

		return success;
	}

	/**
	 * Show "dashboard is exhausted" warning message in dialog context.
	 */
	_showDialogMessageExhausted() {
		this._data.dialogue.body.children('.msg-warning').remove();
		this._data.dialogue.body.prepend(makeMessageBox(
			'warning', t('Cannot add widget: not enough free space on the dashboard.'), null, false
		));
	}

	/**
	 * Show "dashboard is exhausted" warning message in dashboard context.
	 */
	_showMessageExhausted() {
		if (this._data.options.message_exhausted) {
			return;
		}

		this._data.options.message_exhausted = makeMessageBox(
			'warning', [], t('Cannot add widget: not enough free space on the dashboard.'), true, false
		);

		addMessage(this._data.options.message_exhausted);
	}

	/**
	 * Hide "dashboard is exhausted" warning message in dashboard context.
	 */
	_hideMessageExhausted() {
		if (this._data.options.message_exhausted) {
			return;
		}

		this._data.options.message_exhausted.remove();
		delete this._data.options.message_exhausted;
	}

	/**
	 * Set dashboard busy state by registering a blocker.
	 *
	 * @param {string} type  Common type of the blocker.
	 * @param {*}      item  Unique item of the blocker.
	 */
	_setDashboardBusy(type, item) {
		if (this._data.options.busy_blockers === undefined) {
			this._data.options.busy_blockers = [];

			$.publish('dashboard.grid.busy', {state: true});
		}

		this._data.options.busy_blockers.push({type: type, item: item});
	}

	/**
	 * Clear dashboard busy state by unregistering a blocker.
	 *
	 * @param {string} type  Common type of the blocker.
	 * @param {*}      item  Unique item of the blocker.
	 */
	_clearDashboardBusy(type, item) {
		if (this._data.options.busy_blockers === undefined) {
			return;
		}

		for (let i = 0; i < this._data.options.busy_blockers.length; i++) {
			const blocker = this._data.options.busy_blockers[i];

			if (type === blocker.type && Object.is(item, blocker.item)) {
				this._data.options.busy_blockers.splice(i, 1);

				break;
			}
		}

		if (!this._data.options.busy_blockers.length) {
			delete this._data.options.busy_blockers;

			$.publish('dashboard.grid.busy', {state: false});
		}
	}

	/**
	 * Reset new widget placeholder state.
	 */
	_resetNewWidgetPlaceholderState() {
		if (this._data.widgets.length) {
			this._data.new_widget_placeholder.hide();
		}
		else {
			this._data.new_widget_placeholder
				.setState(this._data.new_widget_placeholder.STATE_ADD_NEW)
				.showAtDefaultPosition();
		}
	}

	/**
	 * Performs action added by addAction function.
	 *
	 * @param {string} hook_name    Name of trigger that is currently being called.
	 * @param {object|null} widget  Current widget object (can be null for generic actions).
	 *
	 * @returns {int}  Number of triggers, that were called.
	 */
	_doAction(hook_name, widget = null) {
		if (typeof this._data.triggers[hook_name] === 'undefined') {
			return 0;
		}

		let triggers = [];

		if (widget === null) {
			triggers = this._data.triggers[hook_name];
		}
		else {
			for (const trigger of this._data.triggers[hook_name]) {
				if (trigger.uniqueid === null || widget.uniqueid === trigger.uniqueid) {
					triggers.push(trigger);
				}
			}
		}

		triggers.sort(function(a, b) {
			const priority_a = a.options['priority'] || 10;
			const priority_b = b.options['priority'] || 10;

			if (priority_a < priority_b) {
				return -1;
			}
			if (priority_a > priority_b) {
				return 1;
			}
			return 0;
		});

		for (const trigger of triggers) {
			let trigger_function = null;

			if (typeof trigger.function === typeof Function) {
				// A function given?
				trigger_function = trigger.function;
			}
			else if (typeof window[trigger.function] === typeof Function) {
				// A name of function given?
				trigger_function = window[trigger.function];
			}

			if (trigger_function === null) {
				continue;
			}

			let params = [];
			if (trigger.options['parameters'] !== undefined) {
				params = trigger.options['parameters'];
			}

			if (trigger.options['grid']) {
				let grid = {};

				if (trigger.options['grid'].widget) {
					if (widget !== null) {
						grid.widget = widget;
					}
					else if (trigger.uniqueid !== null) {
						const widgets = this.getWidgetsBy('uniqueid', trigger.uniqueid);
						if (widgets.length > 0) {
							grid.widget = widgets[0];
						}
					}
				}
				if (trigger.options['grid'].data) {
					grid.data = this._data;
				}
				if (trigger.options['grid'].obj) {
					grid.obj = this._$target;
				}
				params.push(grid);
			}

			try {
				trigger_function.apply(this, params);
			} catch (e) {
			}
		}

		return triggers.length;
	}
}

/**
 * TODO
 */

newWidgetPlaceholder.prototype.STATE_ADD_NEW = 0;
newWidgetPlaceholder.prototype.STATE_RESIZING = 1;
newWidgetPlaceholder.prototype.STATE_POSITIONING = 2;
newWidgetPlaceholder.prototype.STATE_KIOSK_MODE = 3;
newWidgetPlaceholder.prototype.STATE_READONLY = 4;

newWidgetPlaceholder.prototype.classes = {
	placeholder: 'dashbrd-grid-new-widget-placeholder',
	placeholder_box: 'dashbrd-grid-widget-new-box',
	placeholder_box_label: 'dashbrd-grid-new-widget-label',
	resizing: 'dashbrd-grid-widget-set-size',
	positioning: 'dashbrd-grid-widget-set-position'
};

/**
 * Create new widget placeholder instance.
 *
 * @param {int}      cell_width        Dashboard grid cell width in percents.
 * @param {int}      cell_height       Dashboard grid cell height in pixels.
 * @param {callback} add_new_callback  Callback to execute on click on "Add new widget".

 * @returns {object}  Placeholder instance.
 */
function newWidgetPlaceholder(cell_width, cell_height, add_new_callback) {
	this.cell_width = cell_width;
	this.cell_height = cell_height;
	this.add_new_callback = add_new_callback;

	this.$placeholder = $('<div>', {'class': this.classes.placeholder});
	this.$placeholder_box = $('<div>', {'class': this.classes.placeholder_box});
	this.$placeholder_box_label = $('<div>', {'class': this.classes.placeholder_box_label});
	this.$placeholder_box_label_wrap = $('<span>');

	this.$placeholder_box_label_wrap.appendTo(this.$placeholder_box_label);
	this.$placeholder_box_label.appendTo(this.$placeholder_box);
	this.$placeholder_box.appendTo(this.$placeholder);

	this.setState(this.STATE_ADD_NEW);
}

/**
 * Get jQuery object of the new widget placeholder.
 *
 * @returns {jQuery}
 */
newWidgetPlaceholder.prototype.getObject = function() {
	return this.$placeholder;
};

/**
 * Set state of the new widget placeholder.
 *
 * @param {int} state  newWidgetPlaceholder.prototype.STATE_* constant.
 *
 * @returns {this}
 */
newWidgetPlaceholder.prototype.setState = function(state) {
	this.$placeholder.hide();

	if (state === this.state) {
		return this;
	}

	this.$placeholder.off('click');
	this.$placeholder.removeClass('disabled');
	this.$placeholder_box.removeClass(this.classes.resizing + ' ' + this.classes.positioning);
	this.$placeholder_box_label_wrap.empty();

	switch (state) {
		case this.STATE_ADD_NEW:
			this.$placeholder_box_label_wrap.append(
				$('<a>', {href: '#'}).text(t('Add a new widget'))
			);

			this.$placeholder.on('click', this.add_new_callback);

			break;

		case this.STATE_RESIZING:
			this.$placeholder_box.addClass(this.classes.resizing);
			this.$placeholder_box_label_wrap.text(t('Release to create a widget.'));

			break;

		case this.STATE_POSITIONING:
			this.$placeholder_box.addClass(this.classes.positioning);
			this.$placeholder_box_label_wrap.text(t('Click and drag to desired size.'));

			break;

		case this.STATE_KIOSK_MODE:
			this.$placeholder_box_label_wrap.text(t('Cannot add widgets in kiosk mode'));
			this.$placeholder.addClass('disabled');

			break;

		case this.STATE_READONLY:
			this.$placeholder_box_label_wrap.text(t('You do not have permissions to edit dashboard'));
			this.$placeholder.addClass('disabled');

			break;
	}

	return this;
};

/**
 * Resize the new widget placeholder. Use to update visibility of the label of the placeholder.
 *
 * @returns {this}
 */
newWidgetPlaceholder.prototype.resize = function() {
	if (this.$placeholder.is(':visible')) {
		this.$placeholder_box_label_wrap.show();
		if (this.$placeholder_box_label[0].scrollHeight > this.$placeholder_box_label.outerHeight()) {
			this.$placeholder_box_label_wrap.hide();
		}
	}

	return this;
};

/**
 * Show new widget placeholder at given position.
 *
 * @param {object} pos  Object with position and dimension.
 *
 * @returns {this}
 */
newWidgetPlaceholder.prototype.showAtPosition = function(pos) {
	this.$placeholder
		.css({
			position: 'absolute',
			left: (pos.x * this.cell_width) + '%',
			top: (pos.y * this.cell_height) + 'px',
			width: (pos.width * this.cell_width) + '%',
			height: (pos.height * this.cell_height) + 'px'
		})
		.show();

	this.resize();

	return this;
};

/**
 * Show new widget placeholder at the default position.
 *
 * @returns {this}
 */
newWidgetPlaceholder.prototype.showAtDefaultPosition = function() {
	this.$placeholder
		.css({
			position: '',
			top: '',
			left: '',
			height: '',
			width: ''
		})
		.show();

	this.resize();

	return this;
};

/**
 * Hide new widget placeholder.
 *
 * @returns {this}
 */
newWidgetPlaceholder.prototype.hide = function() {
	this.$placeholder.hide();

	return this;
};
