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


const DASHBOARD_PAGE_EVENT_EDIT            = 'dashboard-page-edit';
const DASHBOARD_PAGE_EVENT_RESIZE          = 'dashboard-page-resize';
const DASHBOARD_PAGE_EVENT_ADD_WIDGET      = 'dashboard-page-add-widget';
const DASHBOARD_PAGE_EVENT_DELETE_WIDGET   = 'dashboard-page-delete-widget';
const DASHBOARD_PAGE_EVENT_POSITION_WIDGET = 'dashboard-page-position-widget';


class CDashboardPage extends CBaseComponent {

	_addWidget(config) {
		config = {
			...config,
			defaults: this._data.widget_defaults[config.type],
			uniqueid: this._generateUniqueId(),
			cell_width: this._options['widget-width'],
			cell_height: this._options['widget-height'],
			is_editable: this._options['editable'] && !this._options['kioskmode'],
			dynamic_hostid: this._data.dashboard.dynamic_hostid,
			index: this._widgets.length
		};

		let widget;

		if (config.defaults.iterator) {
			widget = new CWidgetIterator({
				min_rows: this._options['widget-min-rows'],
				...config
			});

			widget
				.on(WIDGET_ITERATOR_EVENT_PREVIOUS_PAGE_CLICK, (e) => {
					const w = e.detail.target;

					if (w.page > 1) {
						w.page--;
						this._updateWidgetContent(w);
					}
				})
				.on(WIDGET_ITERATOR_EVENT_NEXT_PAGE_CLICK, (e) => {
					const w = e.detail.target;

					if (w.page < w.page_count) {
						w.page++;
						this._updateWidgetContent(w);
					}
				})
				.on(WIDGET_EVENT_RESIZE, (e) => {
					const w = e.detail.target;

					this._onIteratorResizeEnd(w);
				});
		}
		else {
			widget = new CWidget(config);
		}

		widget
			.on(WIDGET_EVENT_EDIT_CLICK, (e) => {
				this.editWidget(e.detail.target, e.target);
			})
			.on(WIDGET_EVENT_ENTER, (e) => {
				this._enterWidget(e.detail.target);
			})
			.on(WIDGET_EVENT_LEAVE, (e) => {
				this._leaveWidget(e.detail.target);
			});

		this._widgets.push(widget);

		this._data.new_widget_placeholder.hide();

		this.fire(DASHBOARD_PAGE_EVENT_ADD_WIDGET);
	}

	/**
	 * Focus specified widget or iterator and blur all other widgets.
	 * If child widget of iterator is specified, blur all other child widgets of iterator.
	 * This top-level function should be called by mouse and focus event handlers.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	_enterWidget(widget) {
		if (widget.getView().hasClass(widget.getCssClass('focus'))) {
			return;
		}

		if (this._isDashboardFrozen()) {
			return;
		}

		if (widget.parent) {
			widget.parent.leaveChildrenExcept(widget);
			widget.parent.enterChild(widget);
		}
		else {
			this._doLeaveWidgetsExcept(widget);
			widget.enter();
		}

		this._slideKiosk();
	}


	/**
	 * Update dashboard sliding effect if in kiosk mode.
	 */
	_slideKiosk() {
		// Calculate the dashboard offset (0, 1 or 2 lines) based on focused widget.

		let slide_lines = 0;

		for (const widget of this._widgets) {
			if (!widget.getView().hasClass(widget.getCssClass('focus'))) {
				continue;
			}

			// Focused widget not on the first row of dashboard?
			if (widget.getView().position().top !== 0) {
				break;
			}

			if (widget instanceof CWidgetIterator) {
				slide_lines = widget.getView().hasClass('iterator-double-header') ? 2 : 1;
			}
			else if (widget.getView().hasClass(widget.getCssClass('hidden_header'))) {
				slide_lines = 1;
			}

			break;
		}

		// Apply the calculated dashboard offset (0, 1 or 2 lines) slowly.

		const $wrapper = this._$target.closest('.layout-kioskmode');

		if (!$wrapper.length) {
			return;
		}

		if (typeof this._options['kiosk_slide_timeout'] !== 'undefined') {
			clearTimeout(this._options['kiosk_slide_timeout'])
			delete this._options['kiosk_slide_timeout'];
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
			this._options['kiosk_slide_timeout'] = setTimeout(() => {
				$wrapper.removeClass('kiosk-slide-lines-' + slide_lines_current);
				if (slide_lines > 0) {
					$wrapper.addClass('kiosk-slide-lines-' + slide_lines);
				}
				delete this._options['kiosk_slide_timeout'];
			}, 2000);
		}
	}

	_setWidgetPadding(widget, padding) {
		// Note: this function is used only for widgets and not iterators.

		if (!(widget instanceof CWidgetIterator) && widget.configuration['padding'] !== padding) {
			widget.configuration['padding'] = padding;
			widget.content_body.toggleClass('no-padding', !padding);
			this._resizeWidget(widget);
		}
	}

	_makeDraggable(widget) {
		widget.getView().draggable({
			cursor: 'grabbing',
			handle: widget.content_header,
			scroll: true,
			scrollSensitivity: this._options['widget-height'],
			start: () => {
				this._$target.addClass('dashbrd-positioning');

				this._data.calculated = {
					'left-max': this._$target.width() - widget.getView().width(),
					'top-max': this._options['max-rows'] * this._options['widget-height'] - widget.getView().height()
				};

				this._setResizableState('disable');
				this._dragPrepare(widget, this._options['max-rows']);
				this._startWidgetPositioning(widget, 'drag');
				this._realignWidget(widget, this._options['max-rows']);

				widget.current_pos = {...widget.pos};
				this._data.undo_pos = {};

				for (const w of this._widgets) {
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

				this._widgets = this._sortWidgets(this._widgets);

				for (const w of this._widgets) {
					delete w.affected_by_draggable;
					delete w.affected_by_id;
					delete w.affected;
				}

				this._setResizableState('enable');
				this._stopWidgetPositioning(widget);

				if (widget instanceof CWidgetIterator && !widget.getView().is(':hover')) {
					widget.getView().removeClass('iterator-double-header');
				}

				this._options['rows'] = this._options['rows_actual'];
				this._resizeDashboardGrid(this._options['rows_actual']);

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

			widget.getView().append($handle);
			handles[direction] = $handle;
		}

		widget.getView().resizable({
			handles: handles,
			scroll: false,
			minWidth: this._getCurrentCellWidth(),
			minHeight: this._options['widget-min-rows'] * this._options['widget-height'],
			start: (e) => {
				this._doLeaveWidgetsExcept(widget);
				widget.enter();

				this._$target.addClass('dashbrd-positioning');

				const handle_class = e.currentTarget.className;
				this._data.resizing_top = handle_class.match(/(^|\s)ui-resizable-(n|ne|nw)($|\s)/) !== null;
				this._data.resizing_left = handle_class.match(/(^|\s)ui-resizable-(w|sw|nw)($|\s)/) !== null;

				for (const w of this._widgets) {
					delete w.affected_axis;
				}

				this._setResizableState('disable', widget.uniqueid);
				this._startWidgetPositioning(widget, 'resize');

				widget.prev_pos = {'mirrored': {}, ...widget.pos};
				widget.prev_pos.axis_correction = {};
			},
			resize: (e, ui) => {
				// Will break fast-resizing widget-top past minimum height, if moved to start section (jQuery UI bug?)
				widget.getView()
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
						ui.size.height - this._options['widget-max-rows'] * this._options['widget-height']
					);
				}

				widget.getView().css({
					'left': ui.position.left,
					'top': ui.position.top,
					'max-width': Math.min(ui.size.width,
						this._data.cell_width * this._options['max-columns'] - ui.position.left
					),
					'max-height': Math.min(ui.size.height,
						this._options['widget-max-rows'] * this._options['widget-height'],
						this._options['max-rows'] * this._options['widget-height'] - ui.position.top
					)
				});

				this._doWidgetResize(widget);

				widget.container.css({
					'width': this._data.placeholder.width(),
					'height': this._data.placeholder.height()
				});
			},
			stop: () => {
				widget.leave();

				delete widget.prev_pos;

				this._setResizableState('enable', widget.uniqueid);
				this._stopWidgetPositioning(widget);

				widget.container.removeAttr('style');

				if (widget instanceof CWidgetIterator) {
					if (widget.alignContents(widget.pos)) {
						this._updateWidgetContent(widget);
					}
				}

				delete this._data.resizing_top;
				delete this._data.resizing_left;

				widget.getView()
					.removeClass('resizing-top')
					.removeClass('resizing-left')
					.css({
						'max-width': '',
						'max-height': ''
					});

				// Invoke onResizeEnd on every affected widget.
				for (const w of this._widgets) {
					if ('affected_axis' in w || w.uniqueid === widget.uniqueid) {
						this._resizeWidget(w);
					}
				}

				this._$target.removeClass('dashbrd-positioning');
			}
		});
	}

	_updateIteratorCallback(iterator, response, options) {
		const has_alt_content = typeof response.messages !== 'undefined' || typeof response.body !== 'undefined';

		if (has_alt_content || iterator.getTooSmallState()) {
			iterator.clear();

			if (has_alt_content) {
				const $alt_content = $('<div>');

				if (typeof response.messages !== 'undefined') {
					$alt_content.append(response.messages);
				}
				if (typeof response.body !== 'undefined') {
					$alt_content.append(response.body);
				}
				iterator.content_body.append($alt_content);
				iterator.getView().addClass('iterator-alt-content');

				iterator.updatePager(1, 1);
			}
			else {
				iterator.update_pending = true;
			}

			return;
		}

		if (iterator.getView().hasClass('iterator-alt-content')) {
			// Returning from alt-content to normal mode.
			iterator.clear();
		}

		iterator.updatePager(response.page, response.page_count);

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

		response.children.slice(0, iterator.getNumColumns() * iterator.getNumRows())
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
					child = new CDashboardWidget({
						...child,
						view_mode: iterator.getViewMode(),
						defaults: this._data.widget_defaults[child.type],
						uniqueid: this._generateUniqueId(),
						cell_width: this._options['widget-width'],
						cell_height: this._options['widget-height'],
						is_editable: iterator.isEditable(),
						parent: iterator,
						is_new: false
					});

					child
//						.activate()
//						.showPreloader()
						.on(WIDGET_EVENT_ENTER, (e) => {
							this._enterWidget(e.detail.target);
						})
						.on(WIDGET_EVENT_LEAVE, (e) => {
							this._leaveWidget(e.detail.target);
						});

					iterator.children.push(child);
					iterator.content_body.append(child.getView());
				}
			});

		for (const child of Object.values(current_children_by_widgetid)) {
			if (!reused_widgetids.includes(child.widgetid)) {
				this._removeWidget(child);
			}
		}

		iterator._addPlaceholders(iterator.getNumColumns() * iterator.getNumRows() - iterator.children.length);
		if (this._options['kioskmode'] && iterator.getView().position().top === 0) {
			this._slideKiosk();
		}

		if (iterator.alignContents((typeof iterator.current_pos === 'object') ? iterator.current_pos : iterator.pos)) {
			this._updateWidgetContent(iterator);
		}

		for (const child of iterator.children) {
			/* Possible update policies for the child widgets:
				resize: execute 'onResizeEnd' action (widget won't update if there's no trigger or size hasn't changed).
					- Is used to propagate iterator's resize event.

				refresh: either execute WIDGET_EVENT_REFRESH action (if trigger exists) or updateWidgetContent.
					- Is used when widget surely hasn't been resized, but needs to be refreshed.

				resize_or_refresh: either execute 'onResizeEnd' or WIDGET_EVENT_REFRESH action, or updateWidgetContent.
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
					success = child.fire(WIDGET_EVENT_REFRESH);
					break;
			}

			if (!success) {
				// No triggers executed for the widget, therefore update the conventional way.
				this._updateWidgetContent(child);
			}
		}
	}

	_updateWidgetContent(widget, options = {}) {
		if (widget instanceof CWidgetIterator) {
			const pos = (typeof widget.current_pos === 'object') ? widget.current_pos : widget.pos;

			if (widget.isTooSmall(pos)) {
				widget.clear();

				widget.stopPreloader();
				widget.setTooSmallState(true);
				widget.update_pending = true;

				return;
			}
			else {
				widget.setTooSmallState(false);
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
			'edit_mode': this._options.edit_mode ? 1 : 0,
			'storage': widget.storage,
			'view_mode': widget.view_mode
		};

		widget.content_size = widget.getContentSize();

		if (widget instanceof CWidgetIterator) {
			ajax_data.page = widget.page;
		}
		else {
			ajax_data = {...ajax_data, ...widget.content_size};
		}

		if ('fields' in widget && Object.keys(widget.fields).length !== 0) {
			ajax_data.fields = JSON.stringify(widget.fields);
		}

		this._setDashboardBusy('updateWidgetContent', widget.uniqueid);

		widget.startPreloader();

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

				widget.stopPreloader();

				if (!this._is_active) {
					return $.Deferred().reject();
				}

				if (this._isDeletedWidget(widget)) {
					return $.Deferred().reject();
				}

				const $content_header = $('h4', widget.content_header);

				$content_header.text(response.header);
				if (typeof response.aria_label !== 'undefined') {
					$content_header.attr('aria-label', (response.aria_label !== '') ? response.aria_label : null);
				}

				if (widget instanceof CWidgetIterator) {
					this._updateIteratorCallback(widget, response, options);
				}
				else {
					widget.update({
						body: response.body,
						messages: response.messages,
						info: response.info,
						debug: response.debug
					});
				}

				if (widget.initial_load && response.scripts !== undefined) {
					eval(response.scripts)(widget, this, response.scripts_data);
				}

				if (!widget.is_ready) {
					const dashboard_was_ready = !this._widgets.filter((w) => {
						return !w.is_ready;
					}).length;

					if (widget.updateReady()) {
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
							const dashboard_is_ready = !this._widgets.filter(function(w) {
								return !w.is_ready;
							}).length;

							if (dashboard_is_ready) {
								this._registerDataExchangeCommit();
								this._doAction('onDashboardReady');
							}
						}
					}
				}

				if (!widget.parent) {
					// Iterator child widgets are excluded here.
					this._setUpdateWidgetContentTimer(widget);
				}

				// The widget is loaded now, although possibly already resized.
				widget.initial_load = false;

				if (!(widget instanceof CWidgetIterator)) {
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

			if (widget.isActive()) {
				this._setUpdateWidgetContentTimer(widget, 3);
			}
		});
	}

	/**
	 * @param {object} widget
	 */
	_updateWidgetConfig(widget) {
		if (this._options['updating_config']) {
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

		this._options['updating_config'] = true;

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
						widget.setViewMode(view_mode);

						this._doLeaveWidgetsExcept(widget);
						widget.enter();
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



						this._widgets
							.filter((w) => {
								return this._rectOverlap(w.pos, pos);
							})
							.forEach((w) => {
								if (this._rectOverlap(w.pos, pos)) {
									if (pos.x + pos.width > w.pos.x && pos.x < w.pos.x) {
										pos.width = w.pos.x - pos.x;
									}
									else if (pos.y + pos.height > w.pos.y && pos.y < w.pos.y) {
										pos.height = w.pos.y - pos.y;
									}
								}
							});

						pos.width = Math.min(this._options['max-columns'] - pos.x, pos.width);
						pos.height = Math.min(this._options['max-rows'] - pos.y, pos.height);
					}

					const widget_data = {
						type: type,
						header: name,
						view_mode: view_mode,
						pos: pos,
						fields: fields,
						configuration: configuration
					};

					this
						._promiseScrollIntoView(pos)
						.then(() => {
							this._addWidget(widget_data);
							this._data.pos_action = '';

							// New widget is last element in data['widgets'] array.
							widget = this._widgets.slice(-1)[0];
							this._setWidgetModeEdit(widget);
							this._updateWidgetContent(widget);
						});
				}
				else if (widget.type === type) {
					// In case of EDIT widget, if type has not changed, update the widget.

					widget.header = name;
					widget.fields = fields;

					// Set preloader to widget content after overlayDialogueDestroy as fast as we can.
					widget.startPreloader(100);

					// View mode was just set after the overlayDialogueDestroy was called in first 'then' section.

					this._applyWidgetConfiguration(widget, configuration);
					this._doAction('afterUpdateWidgetConfig');

					if (widget instanceof CWidgetIterator) {
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
						type: type,
						header: name,
						view_mode: view_mode,
						pos: widget.pos,
						fields: fields,
						configuration: configuration,
						is_new: false
					};

					// Disable position/size checking during addWidget call.
					this._data.pos_action = 'updateWidgetConfig';
					this._addWidget(widget_data);
					this._data.pos_action = '';

					// New widget is last element in data['widgets'] array.
					widget = this._widgets.slice(-1)[0];
					this._setWidgetModeEdit(widget);
					this._updateWidgetContent(widget);
				}

				this._options['updated'] = true;
			})
			.always(() => {
				$save_btn.prop('disabled', false);
				delete this._options['updating_config'];
				overlay.unsetLoading();
			});
	}

	_openConfigDialogue(widget, trigger_element) {
//		widget.fire('beforeConfigLoad');

		this._options['config_dialogue_active'] = true;

		const config_dialogue_close = () => {
			delete this._options['config_dialogue_active'];
			$.unsubscribe('overlay.close', config_dialogue_close);

			this._data.pos_action = '';
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
					'action': () => {}
				}
			],
			'dialogueid': 'widgetConfg'
		}, trigger_element);

		overlay.setLoading();

		this._data.dialogue.div = overlay.$dialogue;
		this._data.dialogue.body = overlay.$dialogue.$body;

		this.updateWidgetConfigDialogue();
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

			// TODO move to CWidgetConfig.
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

	_setWidgetModeEdit(widget) {
		widget.clearUpdateContentTimer();

		if (!(widget instanceof CWidgetIterator)) {
			widget.removeInfoButtons();
		}

		// TODO: The widget shoud already be activated. This method must not interact with widget activation.
		if (!widget.isActive()) {
			widget.activate();
			widget.getView().appendTo(this._$target);
		}

		this._makeDraggable(widget);
		this._makeResizable(widget);
		this._resizeWidget(widget);
	}

	_onIteratorResizeEnd(iterator) {
		iterator.updatePager();

		if (iterator.getTooSmallState()) {
			return;
		}

		this._updateWidgetContent(iterator, {update_policy: 'resize'});
	}

	_resizeWidget(widget) {
		let success = false;

		if (widget instanceof CWidgetIterator) {
			// Iterators will sync first, then selectively propagate the resize event to the child widgets.
			success = widget.fire('onResizeEnd');
		}
		else {
			const size_old = widget.content_size;
			const size_new = widget.getContentSize();

			if (!this._isEqualContentSize(size_old, size_new)) {
				success = widget.fire('onResizeEnd');

				if (success) {
					widget.content_size = size_new;
				}
			}
		}

		return success;
	}
}
