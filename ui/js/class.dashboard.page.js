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

	constructor($target) {
		this._$target = $target;

		this.initMethods();
	}

	// TODO unused function +
	makeWidgetDiv(widget) {
		const data = this._$target.data('dashboardGrid');

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

		if (widget.fields.dynamic && widget.fields.dynamic == 1 && data.dashboard.dynamic_hostid !== null) {
			widget_actions.dynamic_hostid = data.dashboard.dynamic_hostid;
		}

		widget.content_header = $('<div>', {'class': classes.head})
			.append(
				$('<h4>').text((widget.header !== '') ? widget.header : data.widget_defaults[widget.type].header)
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
								this.updateWidgetContent(widget);
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
								this.updateWidgetContent(widget);
							}
						})
					)
					: ''
				)
				.append($('<ul>', {'class': classes.actions})
					.append((data.options['editable'] && !data.options['kioskmode'])
						? $('<li>').append(
							$('<button>', {
								'type': 'button',
								'class': 'btn-widget-edit',
								'title': t('Edit')
							}).on('click', (e) => {
								this._methods.editWidget(widget, e.target);
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
		} else {
			widget.content_script = $('<div>');
			widget.container.append(widget.content_script);
		}

		const $div = $('<div>', {'class': classes.root})
			.toggleClass(classes.hidden_header, widget.view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER)
			.toggleClass('new-widget', widget.new_widget);

		if (!widget.parent) {
			$div.css({
				'min-height': `${data.options['widget-height']}px`,
				'min-width': `${data.options['widget-width']}%`
			});
		}

		// Used for disabling widget interactivity in edit mode while resizing.
		widget.mask = $('<div>', {'class': classes.mask});

		$div.append(widget.container, widget.mask);

		widget.content_header
			.on('focusin', () => {
				this.enterWidget(widget);
			})
			.on('focusout', (e) => {
				if (!widget.content_header.has(e.relatedTarget).length) {
					this.leaveWidget(widget);
				}
			})
			.on('focusin focusout', () => {
				// Skip mouse events caused by animations which were caused by focus change.
				data.options['mousemove_waiting'] = true;
			});

		$div
			// "Mouseenter" is required, since "mousemove" may not always bubble.
			.on('mouseenter mousemove', () => {
				this.enterWidget(widget);

				delete data.options['mousemove_waiting'];
			})
			.on('mouseleave', () => {
				if (!data.options['mousemove_waiting']) {
					this.leaveWidget(widget);
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
	// TODO unused function +
	isDashboardFrozen() {
		const data = this._$target.data('dashboardGrid');

		// Edit widget dialogue active?
		if (data.options['config_dialogue_active']) {
			return true;
		}

		// TODO need to check forEach +
		// data.widgets.forEach((widget) => {
		// 	// Widget placeholder doesn't have header.
		// 	if (!widget['content_header']) {
		// 		return;
		// 	}
		//
		// 	// Widget popup open (refresh rate)?
		// 	if (widget['content_header'].find('[data-expanded="true"]').length > 0
		// 		// Widget being dragged or resized in dashboard edit mode?
		// 		|| widget['div'].hasClass('ui-draggable-dragging')
		// 		|| widget['div'].hasClass('ui-resizable-resizing')) {
		// 		result = true;
		// 	}
		// });
		for (const widget of data.widgets) {
			// Widget placeholder doesn't have header.
			if (!widget.content_header) {
				continue;
			}

			// Widget popup open (refresh rate)?
			if (widget.content_header.find('[data-expanded="true"]').length > 0
				// Widget being dragged or resized in dashboard edit mode?
				|| widget.div.hasClass('ui-draggable-dragging')
				|| widget.div.hasClass('ui-resizable-resizing')) {
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
	// TODO unused function +
	enterWidget(widget) {
		if (widget.div.hasClass(widget.iterator ? 'dashbrd-grid-iterator-focus' : 'dashbrd-grid-widget-focus')) {
			return;
		}

		if (this.isDashboardFrozen()) {
			return;
		}

		if (widget.parent) {
			this.doLeaveWidgetsOfIteratorExcept(widget.parent, widget);
			this.doEnterWidgetOfIterator(widget);
		} else {
			this.doLeaveWidgetsExcept(widget);
			this.doEnterWidget(widget);
		}

		this.slideKiosk();
	}

	/**
	 * Blur specified widget or iterator. If iterator is specified, blur it's focused child widget as well.
	 * This top-level function should be called by mouse and focus event handlers.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	// TODO unused function +
	leaveWidget(widget) {
		if (!widget.div.hasClass(widget.iterator ? 'dashbrd-grid-iterator-focus' : 'dashbrd-grid-widget-focus')) {
			return;
		}

		if (this.isDashboardFrozen()) {
			return;
		}

		this.doLeaveWidget(widget);

		this.slideKiosk();
	}

	/**
	 * Focus specified top-level widget or iterator. If iterator is specified, focus it's hovered child widget as well.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	// TODO unused function +
	doEnterWidget(widget) {
		widget.div.addClass(widget.iterator ? 'dashbrd-grid-iterator-focus' : 'dashbrd-grid-widget-focus');

		if (widget.iterator) {
			let child_hovered = null;

			// widget['children'].forEach((child) => {
			// 	if (child['div'].is(':hover')) {
			// 		child_hovered = child;
			// 	}
			// });
			for (const child of widget.children) {
				if (child.div.is(':hover')) {
					child_hovered = child;
				}
			}

			if (child_hovered !== null) {
				this.doEnterWidgetOfIterator(child_hovered);
			}
		}
	}

	/**
	 * Focus specified child widget of iterator.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	// TODO unused function +
	doEnterWidgetOfIterator(widget) {
		widget.div.addClass('dashbrd-grid-widget-focus');

		if (widget.parent.div.hasClass('dashbrd-grid-iterator-hidden-header')) {
			widget.parent.div.toggleClass('iterator-double-header', widget.div.position().top == 0);
		}
	}

	/**
	 * Blur all top-level widgets and iterators, except the specified one.
	 *
	 * @param {object} except_widget  Dashboard widget object.
	 */
	// TODO unused function +
	doLeaveWidgetsExcept(except_widget) {
		const data = this._$target.data('dashboardGrid');

		if (data.widgets) {
			// data['widgets'].forEach((widget) => {
			// 	if (except_widget !== null && widget.uniqueid === except_widget.uniqueid) {
			// 		return;
			// 	}
			//
			// 	this.doLeaveWidget(widget);
			// });
			for (const widget of data.widgets) {
				if (except_widget !== null && widget.uniqueid === except_widget.uniqueid) {
					continue;
				}

				this.doLeaveWidget(widget);
			}
		}
	}

	/**
	 * Blur specified top-level widget or iterator. If iterator is specified, blur it's focused child widget as well.
	 *
	 * @param {object} except_widget  Dashboard widget object.
	 */
	// TODO unused function +
	doLeaveWidget(widget) {
		// Widget placeholder doesn't have header.
		if (!widget.content_header) {
			return;
		}

		if (widget.content_header.has(document.activeElement).length) {
			document.activeElement.blur();
		}

		if (widget.iterator) {
			this.doLeaveWidgetsOfIteratorExcept(widget);
			widget.div.removeClass('iterator-double-header');
		}

		widget.div.removeClass(widget.iterator ? 'dashbrd-grid-iterator-focus' : 'dashbrd-grid-widget-focus');
	}

	/**
	 * Blur all child widgets of iterator, except the specified one.
	 *
	 * @param {object} iterator       Iterator object.
	 * @param {object} except_widget  Dashboard widget object.
	 */
	// TODO unused function +
	doLeaveWidgetsOfIteratorExcept(iterator, except_child) {
		// iterator['children'].forEach((child) => {
		// 	if (except_child !== undefined && child.uniqueid === except_child.uniqueid) {
		// 		return;
		// 	}
		//
		// 	child['div'].removeClass('dashbrd-grid-widget-focus');
		// });
		for (const child of iterator.children) {
			if (except_child !== undefined && child.uniqueid === except_child.uniqueid) {
				continue;
			}

			child.div.removeClass('dashbrd-grid-widget-focus');
		}
	}

	/**
	 * Update dashboard sliding effect if in kiosk mode.
	 */
	// TODO unused function +
	slideKiosk() {
		const data = this._$target.data('dashboardGrid');

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

		for (const widget of data.widgets) {
			const classes = widget.iterator ? iterator_classes : widget_classes;

			if (!widget.div.hasClass(classes.focus)) {
				continue;
			}

			// Focused widget not on the first row of dashboard?
			if (widget.div.position().top != 0) {
				break;
			}

			if (widget.iterator) {
				slide_lines = widget.div.hasClass('iterator-double-header') ? 2 : 1;
			} else if (widget.div.hasClass(classes.hidden_header)) {
				slide_lines = 1;
			}

			break;
		}

		// Apply the calculated dashboard offset (0, 1 or 2 lines) slowly.

		const $wrapper = this._$target.closest('.layout-kioskmode');

		if (!$wrapper.length) {
			return;
		}

		if (typeof data.options['kiosk_slide_timeout'] !== 'undefined') {
			clearTimeout(data.options['kiosk_slide_timeout'])
			delete data.options['kiosk_slide_timeout'];
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
		} else if (slide_lines < slide_lines_current) {
			data.options['kiosk_slide_timeout'] = setTimeout(() => {
				$wrapper.removeClass('kiosk-slide-lines-' + slide_lines_current);
				if (slide_lines > 0) {
					$wrapper.addClass('kiosk-slide-lines-' + slide_lines);
				}
				delete data.options['kiosk_slide_timeout'];
			}, 2000);
		}
	}

	// TODO unused function +
	setWidgetViewMode(widget, view_mode) {
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

			// widget['children'].forEach((child) => {
			// 	this.setWidgetViewMode(child, view_mode);
			// });
			for (const child of widget.children) {
				this.setWidgetViewMode(child, view_mode);
			}
		}

		widget.div.toggleClass(hidden_header_class, view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER);
	}

	// TODO unused function +
	updateIteratorPager(iterator) {
		$('.dashbrd-grid-iterator-pager-info', iterator.content_header)
			.text(`${iterator.page} / ${iterator.page_count}`);

		iterator.content_header.addClass('pager-visible');

		const too_narrow = iterator.content_header.width() <
			$('.dashbrd-grid-iterator-pager', iterator.content_header).outerWidth(true)
			+ $('.dashbrd-grid-iterator-actions', iterator.content_header).outerWidth(true);

		const is_pager_visible = iterator.page_count > 1 && !too_narrow && !this.getIteratorTooSmallState(iterator);

		iterator.content_header.toggleClass('pager-visible', is_pager_visible);
	}

	// TODO unused function +
	addWidgetInfoButtons($content_header, buttons) {
		// Note: this function is used only for widgets and not iterators.

		const $widget_actions = $('.dashbrd-grid-widget-actions', $content_header);

		// buttons.forEach((button) => {
		// 	$widget_actions.prepend(
		// 		$('<li>', {'class': 'widget-info-button'})
		// 			.append(
		// 				$('<button>', {
		// 					'type': 'button',
		// 					'class': button.icon,
		// 					'data-hintbox': 1,
		// 					'data-hintbox-static': 1
		// 				})
		// 			)
		// 			.append(
		// 				$('<div>', {
		// 					'class': 'hint-box',
		// 					'html': button.hint
		// 				}).hide()
		// 			)
		// 	);
		// });
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

	// TODO unused function +
	removeWidgetInfoButtons($content_header) {
		// Note: this function is used only for widgets and not iterators.

		$('.dashbrd-grid-widget-actions', $content_header).find('.widget-info-button').remove();
	}

	// TODO unused function +
	setWidgetPadding(widget, padding) {
		// Note: this function is used only for widgets and not iterators.

		if (!widget.iterator && widget.configuration['padding'] !== padding) {
			widget.configuration['padding'] = padding;
			widget.content_body.toggleClass('no-padding', !padding);
			this.resizeWidget(widget);
		}
	}

	// TODO unused function +
	applyWidgetConfiguration(widget, configuration) {
		if ('padding' in configuration) {
			this.setWidgetPadding(widget, configuration['padding']);
		}
	}

	/**
	 * Set height of dashboard container DOM element.
	 *
	 * @param {int}    min_rows  Minimal desired rows count.
	 */
	resizeDashboardGrid(min_rows) {
		const data = this._$target.data('dashboardGrid');

		data.options['rows'] = 0;

		for (const widget of data.widgets) {
			data.options.rows = Math.max(widget.pos.y + widget.pos.height, data.options.rows);
		}

		if (min_rows !== undefined && data.options['rows'] < min_rows) {
			data.options['rows'] = min_rows;
		}

		let height = data.options['widget-height'] * data.options['rows'];

		if (data.options['edit_mode']) {
			// Occupy whole screen only if in edit mode, not to cause scrollbar in kiosk mode.
			height = Math.max(height, data.minimalHeight);
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
	// TODO unused function +
	calculateGridMinHeight() {
		let height = $(window).height() - $('footer').outerHeight() - this._$target.offset().top - $('.wrapper').scrollTop();

		this._$target.parentsUntil('.wrapper').each(function() {
			height -= parseInt($(this).css('padding-bottom'));
		});

		height -= parseInt(this._$target.css('margin-bottom'));

		return height;
	}

	generateRandomString(length) {
		const space = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		let	ret = '';

		for (let i = 0; length > i; i++) {
			ret += space.charAt(Math.floor(Math.random() * space.length));
		}
		return ret;
	}

	// TODO unused function +
	calcDivPosition($div) {
		const data = this._$target.data('dashboardGrid');

		const pos = $div.position();

		const cell_w = data.cell_width;
		const cell_h = data.options['widget-height'];

		let place_x, place_y, place_w, place_h;

		if (data.pos_action === 'resize') {
			// 0.49 refers to pixels in the following calculations.
			place_w = Math.round($div.width() / cell_w - 0.49);
			place_h = Math.round($div.height() / cell_h - 0.49);
			place_x = $div.hasClass('resizing-left')
				? (Math.round((pos.left + $div.width()) / cell_w) - place_w)
				: Math.round(pos.left / cell_w);
			place_y = $div.hasClass('resizing-top')
				? (Math.round((pos.top + $div.height()) / cell_h) - place_h)
				: Math.round(pos.top / cell_h);
		} else {
			place_x = Math.round(pos.left / cell_w);
			place_y = Math.round(pos.top / cell_h);
			place_w = Math.round(($div.width() + pos.left - place_x * cell_w) / cell_w);
			place_h = Math.round(($div.height() + pos.top - place_y * cell_h) / cell_h);
		}

		if (data.pos_action === 'resize') {
			place_w = Math.min(place_w, place_w + place_x, data.options['max-columns'] - place_x);
			place_h = Math.min(place_h, place_h + place_y, data.options['max-rows'] - place_y);
		}

		place_x = Math.min(place_x, data.options['max-columns'] - place_w);
		place_y = Math.min(place_y, data.options['max-rows'] - place_h);

		return {
			x: Math.max(place_x, 0),
			y: Math.max(place_y, 0),
			width: Math.max(place_w, 1),
			height: Math.max(place_h, data.options['widget-min-rows'])
		}
	}

	// TODO unused function +
	getCurrentCellWidth() {
		const data = this._$target.data('dashboardGrid');

		return $('.dashbrd-grid-container').width() / data.options['max-columns'];
	}

	// TODO unused function +
	setDivPosition($div, pos) {
		const data = this._$target.data('dashboardGrid');

		$div.css({
			left: `${data.options['widget-width'] * pos.x}%`,
			top: `${data.options['widget-height'] * pos.y}px`,
			width: `${data.options['widget-width'] * pos.width}%`,
			height: `${data.options['widget-height'] * pos.height}px`
		});
	}

	// TODO unused function +
	resetCurrentPositions(widgets) {
		// for (let i = 0; i < widgets.length; i++) {
		// 	// TODO check object clone +
		// 	// widgets[i].current_pos = $.extend({}, widgets[i].pos);
		// 	widgets[i].current_pos = Object.assign({}, widgets[i].pos);
		// }
		for (const widget of widgets) {
			widget.current_pos = Object.assign({}, widget.pos);
		}
	}

	// TODO unused function +
	startWidgetPositioning(widget, action) {
		const data = this._$target.data('dashboardGrid');

		data.pos_action = action;
		data.cell_width = this.getCurrentCellWidth();
		data.placeholder.css('visibility', (action === 'resize') ? 'hidden' : 'visible').show();
		data.new_widget_placeholder.hide();
		this.resetCurrentPositions(data.widgets);
	}

	// TODO unused function +
	posEquals(pos1, pos2) {
		// TODO need to check each +
		// var ret = true;
		//
		// $.each(['x', 'y', 'width', 'height'], function(index, key) {
		// 	if (pos1[key] !== pos2[key]) {
		// 		ret = false;
		// 		return false;
		// 	}
		// });
		//
		// return ret;
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
	// TODO unused function +
	rectOverlap(pos1, pos2) {
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
	// TODO unused function +
	realignWidget(widgets, widget, max_rows) {
		const realign = (widgets, widget, allow_reorder) => {
			const next = [];

			// TODO need to check forEach
			// widgets.forEach((w) => {
			// 	if (widget.uniqueid !== w.uniqueid && !overflow) {
			// 		if (this.rectOverlap(widget.current_pos, w.current_pos)
			// 			|| (!allow_reorder && 'affected_by_id' in w && w.affected_by_id === widget.uniqueid)) {
			// 			w.current_pos.y = Math.max(w.current_pos.y,
			// 				widget.current_pos.y + widget.current_pos.height
			// 			);
			// 			next.push(w);
			// 			overflow = (overflow || (w.current_pos.y + w.current_pos.height) > max_rows);
			// 		}
			// 	}
			// });
			for (const affected_widget of widgets) {
				if (widget.uniqueid !== affected_widget.uniqueid && !overflow) {
					if (this.rectOverlap(widget.current_pos, affected_widget.current_pos)
							|| (!allow_reorder && 'affected_by_id' in affected_widget
								&& affected_widget.affected_by_id === widget.uniqueid)) {
						affected_widget.current_pos.y = Math.max(affected_widget.current_pos.y,
							widget.current_pos.y + widget.current_pos.height
						);
						next.push(affected_widget);
						overflow = (overflow
							|| (affected_widget.current_pos.y + affected_widget.current_pos.height) > max_rows);
					}
				}
			}

			// TODO need to check forEach
			// next.forEach((widget) => {
			// 	if (!overflow) {
			// 		realign(widgets, widget, false);
			// 	}
			// });
			for (const widget of next) {
				if (!overflow) {
					realign(widgets, widget, false);
				}
			}
		};

		let overflow = false;

		// TODO need to check forEach
		// widgets.forEach((w) => {
		// 	if (widget.uniqueid !== w.uniqueid && !overflow) {
		// 		w.current_pos = Object.assign({}, w.pos);
		// 	}
		// });
		for (const affected_widget of widgets) {
			if (widget.uniqueid !== affected_widget.uniqueid && !overflow) {
				affected_widget.current_pos = Object.assign({}, affected_widget.pos);
			}
		}

		realign(this.sortWidgets(widgets), widget, true);

		return overflow;
	}

	// TODO unused function +
	sortWidgets(widgets) {
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
	// TODO unused function +
	dragPrepare(widgets, widget, max_rows) {
		const markAffected = (widgets, affected_by, affected_by_draggable) => {
			const w_pos = Object.assign({}, affected_by.pos);

			w_pos.height++;

			// $.map(widgets, (affected_widget) => {
			// 	return (!('affected' in affected_widget) && this.rectOverlap(w_pos, affected_widget.pos)) ? affected_widget : null;
			// }).forEach((affected_widget) => {
			// 	if (affected_widget.uniqueid !== widget.uniqueid) {
			// 		affected_widget.affected = true;
			// 		affected_widget.affected_by_id = affected_by.uniqueid;
			// 		if (affected_by_draggable) {
			// 			affected_widget.affected_by_draggable = affected_by.uniqueid;
			// 		}
			// 		markAffected(widgets, affected_widget, affected_by_draggable);
			// 	}
			// });
			widgets.filter((affected_widget) => {
				return !('affected' in affected_widget)
					&& this.rectOverlap(w_pos, affected_widget.pos)
					&& affected_widget.uniqueid !== widget.uniqueid;
			}).forEach((affected_widget) => {
				affected_widget.affected = true;
				affected_widget.affected_by_id = affected_by.uniqueid;
				if (affected_by_draggable) {
					affected_widget.affected_by_draggable = affected_by.uniqueid;
				}
				markAffected(widgets, affected_widget, affected_by_draggable);
			});
		};

		markAffected(widgets, widget, true);

		// widgets.forEach((w) => {
		// 	delete w.affected;
		// });
		for (const affected_widget of widgets) {
			delete affected_widget.affected;
		}

		// widgets.forEach((w) => {
		// 	markAffected(widgets, w, false);
		// });
		for (const affected_widget of widgets) {
			markAffected(widgets, affected_widget, false);
		}

		// TODO need to check each
		// $.each(widgets, (_, w) => {
		// 	if ('affected_by_draggable' in w) {
		// 		var pos = Object.assign({}, w.pos),
		// 			overlaps = false;
		//
		// 		pos.y -= widget.pos.height;
		// 		pos.height += widget.pos.height;
		//
		// 		// TODO need to check each
		// 		$.each(widgets, (_, b) => {
		// 			overlaps = (b.uniqueid !== w.uniqueid && b.uniqueid !== widget.uniqueid && this.rectOverlap(b.pos, pos));
		//
		// 			if (overlaps) {
		// 				pos.y = b.pos.y + b.pos.height;
		// 				pos.height -= w.pos.y - pos.y;
		// 				overlaps = (pos.height < w.pos.height || pos.y >= w.pos.y);
		// 			}
		//
		// 			return !overlaps;
		// 		});
		//
		// 		if (overlaps) {
		// 			return false;
		// 		}
		//
		// 		w.pos.y = pos.y;
		// 	}
		// });
		for (const affected_widget of widgets) {
			if ('affected_by_draggable' in affected_widget) {
				const pos = Object.assign({}, affected_widget.pos);
				let overlaps = false;

				pos.y -= widget.pos.height;
				pos.height += widget.pos.height;

				for (const box of widgets) {
					overlaps = (box.uniqueid !== affected_widget.uniqueid
							&& box.uniqueid !== widget.uniqueid
							&& this.rectOverlap(box.pos, pos));

					if (overlaps) {
						pos.y = box.pos.y + box.pos.height;
						pos.height -= affected_widget.pos.y - pos.y;
						overlaps = (pos.height < affected_widget.pos.height || pos.y >= affected_widget.pos.y);
					}

					return !overlaps;
				}

				if (overlaps) {
					return false;
				}

				affected_widget.pos.y = pos.y;
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
	// TODO unused function +
	fitWidgetsIntoBox(widgets, widget, axis) {
		const axis_key = axis.axis_key;
		const size_key = axis.size_key;

		const size_min = axis.size_min;
		const size_max = axis.size_max;
		const opposite_axis_key = (axis_key === 'x') ? 'y' : 'x';
		const opposite_size_key = (size_key === 'width') ? 'height' : 'width';

		const axis_pos = Object.assign({}, widget.current_pos);

		const getAffectedInBounds = (bounds) => {
			// TODO need to check map
			return $.map(affected, (box) => {
				return this.rectOverlap(bounds, box.current_pos) ? box : null;
			});
			// return affected.filter((affected_widget) => {
			// 	return this.rectOverlap(bounds, affected_widget.current_pos);
			// });
		};

		const markAffectedWidgets = (pos, uid) => {
			$.map(widgets, (box) => {
				return (!('affected_axis' in box) && box.uniqueid !== uid && this.rectOverlap(pos, box.current_pos))
					? box
					: null;
			})
				.forEach((box) => {
					var boundary = Object.assign({}, box.current_pos);

					if (box.uniqueid !== widget.uniqueid) {
						boundary[size_key] += pos[axis_key] + pos[size_key] - boundary[axis_key];
					}
					box.affected_axis = axis_key;

					markAffectedWidgets(boundary);
				});
			// widgets.filter((affected_widget) => {
			// 	return !('affected_axis' in affected_widget)
			// 		&& affected_widget.uniqueid !== uid
			// 		&& this.rectOverlap(pos, affected_widget.current_pos);
			// }).forEach((affected_widget) => {
			// 	const boundary = Object.assign({}, affected_widget.current_pos);
			//
			// 	if (affected_widget.uniqueid !== widget.uniqueid) {
			// 		boundary[size_key] += pos[axis_key] + pos[size_key] - boundary[axis_key];
			// 	}
			// 	affected_widget.affected_axis = axis_key;
			//
			// 	markAffectedWidgets(boundary);
			// });
		}

		let margins = {};
		let new_max = 0;
		let affected;
		let overlap = 0;

		// Resize action for left/up is mirrored right/down action.
		if ('mirrored' in axis) {
			// TODO need to check forEach
			widgets.forEach((box) => {
				box.current_pos[axis_key] = size_max - box.current_pos[axis_key] - box.current_pos[size_key];
				box.pos[axis_key] = size_max - box.pos[axis_key] - box.pos[size_key];
			});
			// for (const box of widgets) {
			// 	box.current_pos[axis_key] = size_max - box.current_pos[axis_key] - box.current_pos[size_key];
			// 	box.pos[axis_key] = size_max - box.pos[axis_key] - box.pos[size_key];
			// }
			axis_pos[axis_key] = size_max - axis_pos[axis_key] - axis_pos[size_key];
		}

		// Get array containing only widgets affected by resize operation.
		markAffectedWidgets(widget.current_pos, widget.uniqueid);

		// TODO need to check $.map
		affected = $.map(widgets, (box) => {
			return ('affected_axis' in box && box.affected_axis === axis_key && box.uniqueid !== widget.uniqueid)
				? box
				: null;
		})
		// affected = widgets
		// 	.filter((box) => {
		// 		return 'affected_axis' in box && box.affected_axis === axis_key && box.uniqueid !== widget.uniqueid;
		// 	})
			.sort((box1, box2) => {
				return box1.current_pos[axis_key] - box2.current_pos[axis_key];
			});

		/**
		 * Compact affected widgets removing empty space between them when possible. Additionally build overlap array
		 * which will contain maximal coordinate occupied by widgets on every opposite axis line.
		 */
		for (const box of affected) {
			const last = box.current_pos[opposite_axis_key] + box.current_pos[opposite_size_key];

			let new_pos = axis_pos[axis_key] + axis_pos[size_key];
			let i;

			for (i = box.current_pos[opposite_axis_key]; i < last; i++) {
				if (i in margins) {
					new_pos = Math.max(new_pos, margins[i]);
				}
			}

			if (box.current_pos[axis_key] > new_pos) {
				// Should keep widget original position if compacted value is less than original.
				for (i = box.current_pos[opposite_axis_key]; i < last; i++) {
					margins[i] = box.current_pos[axis_key] + box.current_pos[size_key];
				}

				continue;
			}

			for (i = box.current_pos[opposite_axis_key]; i < last; i++) {
				margins[i] = new_pos + box.current_pos[size_key];
			}

			box.current_pos[axis_key] = new_pos;
			new_max = Math.max(new_max, new_pos + box.current_pos[size_key]);
		}

		overlap = new_max - size_max;

		/*
		 * When previous step could not fit affected widgets into visible area resize should be done.
		 * Resize scan affected widgets line by line collapsing only widgets having size greater than minimal
		 * allowed 'size_min' and position overlapped by dashboard visible area.
		 */
		if (overlap > 0) {
			// Scanline is virtual box that utilizes whole width/height depending on its direction defined by size_key.
			const scanline = Object.assign({x: 0, y: 0}, axis.scanline);
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
			for (const affected_widget of affected) {
				const affected_box = Object.assign({}, affected_widget.current_pos);

				let min = affected_widget.current_pos[opposite_axis_key];
				let max = min + affected_widget.current_pos[opposite_size_key];
				let size = affected_widget.current_pos[size_key];
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

				axis_boundaries[affected_widget.uniqueid] = {debug: affected_widget.header, min: min, max: max};
			}

			// Scan affected line by line.
			while (slot < new_max && overlap > 0) {
				margins_backup = Object.assign({}, margins);
				collapsed_pos = {};
				scanline[axis_key] = slot;
				col = getAffectedInBounds(scanline);
				scanline[axis_key] += scanline[size_key];
				next_col = getAffectedInBounds(scanline);
				collapsed = next_col.length > 0;

				// TODO need to check each
				$.each(next_col, (_, box) => {
					if ('pos' in box && box.pos[axis_key] > slot) {
						return;
					}

					box.new_pos = Object.assign({}, box.current_pos);
					box.new_pos[axis_key] = slot;

					// TODO need to check each
					$.each(col, (_, col_box) => {
						if (col_box.uniqueid === box.uniqueid || this.rectOverlap(col_box.current_pos, box.new_pos)) {
							if (col_box.current_pos[size_key] > size_min) {
								let start_pos = axis_boundaries[col_box.uniqueid].min,
									stop_pos = axis_boundaries[col_box.uniqueid].max,
									margin = 0,
									i;

								// Find max overlap position value for checked widget.
								for (i = start_pos; i < stop_pos; i++) {
									margin = Math.max(margin, margins[i]);
								}

								if (margin && margin < size_max) {
									box.new_pos[axis_key] = box.current_pos[axis_key];
									return true;
								} else {
									for (i = start_pos; i < stop_pos; i++) {
										margins[i] = margins_backup[i] - scanline[size_key];
									}
								}

								col_box.new_pos = Object.assign({}, col_box.current_pos);
								col_box.new_pos[size_key] -= scanline[size_key];

								// Mark opposite axis coordinates as moveable.
								for (i = start_pos; i < stop_pos; i++) {
									collapsed_pos[i] = 1;
								}
							} else {
								collapsed = false;
							}
						}

						return collapsed;
					});

					return collapsed;
				});

				// for (const box of next_col) {
				// 	if ('pos' in box && box.pos[axis_key] > slot) {
				// 		continue;
				// 	}
				//
				// 	box.new_pos = Object.assign({}, box.current_pos);
				// 	box.new_pos[axis_key] = slot;
				//
				// 	for (const col_box of col) {
				// 		if (col_box.uniqueid === box.uniqueid || this.rectOverlap(col_box.current_pos, box.new_pos)) {
				// 			if (col_box.current_pos[size_key] > size_min) {
				// 				const start_pos = axis_boundaries[col_box.uniqueid].min;
				// 				const stop_pos = axis_boundaries[col_box.uniqueid].max;
				// 				let margin = 0;
				//
				// 				// Find max overlap position value for checked widget.
				// 				for (let i = start_pos; i < stop_pos; i++) {
				// 					margin = Math.max(margin, margins[i]);
				// 				}
				//
				// 				if (margin && margin < size_max) {
				// 					box.new_pos[axis_key] = box.current_pos[axis_key];
				// 					continue;
				// 				} else {
				// 					for (let i = start_pos; i < stop_pos; i++) {
				// 						margins[i] = margins_backup[i] - scanline[size_key];
				// 					}
				// 				}
				//
				// 				col_box.new_pos = Object.assign({}, col_box.current_pos);
				// 				col_box.new_pos[size_key] -= scanline[size_key];
				//
				// 				// Mark opposite axis coordinates as moveable.
				// 				for (let i = start_pos; i < stop_pos; i++) {
				// 					collapsed_pos[i] = 1;
				// 				}
				// 			} else {
				// 				collapsed = false;
				// 				break;
				// 			}
				// 		}
				// 	}
				//
				// 	if (!collapsed) {
				// 		break;
				// 	}
				// }

				if (collapsed) {
					affected.forEach((box) => {
						if (box.current_pos[axis_key] > slot && box.current_pos[opposite_axis_key] in collapsed_pos) {
							box.current_pos[axis_key] = Math.max(box.current_pos[axis_key] - scanline[size_key],
								box.pos[axis_key]
							);
						}
					});

					// Update margin values for collapsed lines on opposite axis.
					// TODO need to check each
					$.each(collapsed_pos, (index) => {
						margins[index] = margins_backup[index] - scanline[size_key];
					});
					// console.log(collapsed_pos);
					// for (const i in collapsed_pos) {
					// 	margins[i] = margins_backup[i] - scanline[size_key];
					// }

					overlap -= 1;
					new_max -= 1;
				} else {
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
		}).forEach((box) => {
			if (box.pos[size_key] > box.current_pos[size_key]) {
				const new_pos = Object.assign({}, box.current_pos);

				let size = Math.min(box.pos[size_key], size_max - box.current_pos[axis_key]);

				new_pos[size_key] = box.pos[size_key];
				// $.map(affected, (col_box) => {
				// 	return col_box.uniqueid !== box.uniqueid && this.rectOverlap(col_box.current_pos, new_pos)
				// 		? col_box
				// 		: null;
				// }).forEach((col_box) => {
				// 	size = Math.min(size, col_box.current_pos[axis_key] - box.current_pos[axis_key]);
				// });
				affected.filter((col_box) => {
					return col_box.uniqueid !== box.uniqueid && this.rectOverlap(col_box.current_pos, new_pos);
				}).forEach((col_box) => {
					size = Math.min(size, col_box.current_pos[axis_key] - box.current_pos[axis_key]);
				});

				box.current_pos[size_key] = Math.max(size, size_min);
			}
		});

		// Resize action for left/up is mirrored right/down action, mirror coordinates back.
		if ('mirrored' in axis) {
			// widgets.forEach((box) => {
			// 	box.current_pos[axis_key] = size_max - box.current_pos[axis_key] - box.current_pos[size_key];
			// 	box.pos[axis_key] = size_max - box.pos[axis_key] - box.pos[size_key];
			// });
			for (const box of widgets) {
				box.current_pos[axis_key] = size_max - box.current_pos[axis_key] - box.current_pos[size_key];
				box.pos[axis_key] = size_max - box.pos[axis_key] - box.pos[size_key];
			}
		}
	}

	/**
	 * Rearrange widgets. Modifies widget.current_pos if desired size is greater than allowed by resize.
	 *
	 * @param {object} widget        Moved widget object.
	 */
	// TODO unused function +
	realignResize(widget) {
		const data = this._$target.data('dashboardGrid');

		var axis,
			opposite_axis_key,
			opposite_size_key,
			process_order = (widget.prev_pos.x != widget.current_pos.x
				|| widget.prev_pos.width != widget.current_pos.width)
				? ['x', 'y']
				: ['y', 'x'];

		data.widgets.forEach(function(box) {
			if (box.uniqueid !== widget.uniqueid) {
				box.current_pos = $.extend({}, box.pos);
			}
		});

		if (widget.prev_pos.x > widget.current_pos.x) {
			widget.prev_pos.mirrored.x = true;
		}

		if (widget.prev_pos.y > widget.current_pos.y) {
			widget.prev_pos.mirrored.y = true;
		}

		// Situation when there are changes on both axes should be handled as special case.
		if (process_order[0] === 'x' && (widget.prev_pos.y != widget.current_pos.y
			|| widget.prev_pos.height != widget.current_pos.height)) {
			// Mark affected_axis as y if affected box is affected by only changing y position or height.
			var pos = {
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

			$.map(data.widgets, (box) => {
				return (!('affected_axis' in box) && widget.uniqueid !== box.uniqueid
					&& this.rectOverlap(widget.current_pos, box.current_pos))
					? box
					: null;
			}).forEach(function(box) {
				if (this.rectOverlap(pos, box.current_pos)) {
					box.affected_axis = 'y';
				}
			});
		}

		// Store current position as previous position for next steps.
		widget.prev_pos = $.extend(widget.prev_pos, widget.current_pos);

		// Process changes for every axis.
		process_order.forEach((axis_key) => {
			data.widgets.forEach((box) => {
				if ('affected_axis' in box && box.affected_axis === axis_key) {
					delete box.affected_axis;
				}
			});

			axis = {
				axis_key: axis_key,
				size_key: 'width',
				size_min: 1,
				size_max: data.options['max-columns'],
				scanline: {
					width: data.options['max-columns'],
					height: data.options['max-rows']
				}
			};

			if (axis_key === 'y') {
				axis.size_key = 'height';
				axis.size_min = data.options['widget-min-rows'];
				axis.size_max = data.options['max-rows'];
			}

			if (axis_key in widget.prev_pos.mirrored) {
				axis.mirrored = true;
			}

			opposite_axis_key = (axis_key === 'y') ? 'x' : 'y',
				opposite_size_key = (opposite_axis_key === 'x') ? 'width' : 'height';

			if (opposite_size_key in widget.prev_pos.axis_correction) {
				// Use 'corrected' size if it is less than current size.
				widget.current_pos[opposite_size_key] = Math.min(widget.prev_pos.axis_correction[opposite_size_key],
					widget.current_pos[opposite_size_key]);

				if (opposite_axis_key in widget.prev_pos.mirrored && opposite_axis_key in widget.prev_pos.axis_correction) {
					widget.current_pos[opposite_axis_key] = Math.max(widget.prev_pos.axis_correction[opposite_axis_key],
						widget.current_pos[opposite_axis_key]);
				}
			}

			this.fitWidgetsIntoBox(data.widgets, widget, axis);

			if ('overflow' in widget.current_pos) {
				// Store 'corrected' size.
				widget.prev_pos.axis_correction[axis.size_key] = widget.current_pos[axis.size_key];

				if (axis.mirrored) {
					widget.prev_pos.axis_correction[axis_key] = widget.current_pos[axis_key];
				}

				delete widget.current_pos.overflow;
			}
		});

		/*const process_order = (widget.prev_pos.x !== widget.current_pos.x
			|| widget.prev_pos.width !== widget.current_pos.width)
			? ['x', 'y']
			: ['y', 'x'];

		let axis;
		let opposite_axis_key;
		let opposite_size_key;

		data.widgets.forEach((box) => {
			if (box.uniqueid !== widget.uniqueid) {
				box.current_pos = Object.assign({}, box.pos);
			}
		});
		// for (const box of data.widgets) {
		// 	if (box.uniqueid !== widget.uniqueid) {
		// 		box.current_pos = Object.assign({}, box.pos);
		// 	}
		// }

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

			$.map(data.widgets, (box) => {
				return (!('affected_axis' in box) && widget.uniqueid !== box.uniqueid
					&& this.rectOverlap(widget.current_pos, box.current_pos))
					? box
					: null;
			}).forEach((box) => {
				if (this.rectOverlap(pos, box.current_pos)) {
					box.affected_axis = 'y';
				}
			});

			data.widgets = [];
		}

		// Store current position as previous position for next steps.
		widget.prev_pos = $.extend(widget.prev_pos, widget.current_pos);

		// Process changes for every axis.
		process_order.forEach((axis_key) => {
			data.widgets.forEach((box) => {
				if ('affected_axis' in box && box.affected_axis === axis_key) {
					delete box.affected_axis;
				}
			});

			axis = {
				axis_key: axis_key,
				size_key: 'width',
				size_min: 1,
				size_max: data.options['max-columns'],
				scanline: {
					width: data.options['max-columns'],
					height: data.options['max-rows']
				}
			};

			if (axis_key === 'y') {
				axis.size_key = 'height';
				axis.size_min = data.options['widget-min-rows'];
				axis.size_max = data.options['max-rows'];
			}

			if (axis_key in widget.prev_pos.mirrored) {
				axis.mirrored = true;
			}

			opposite_axis_key = (axis_key === 'y') ? 'x' : 'y',
				opposite_size_key = (opposite_axis_key === 'x') ? 'width' : 'height';

			if (opposite_size_key in widget.prev_pos.axis_correction) {
				// Use 'corrected' size if it is less than current size.
				widget.current_pos[opposite_size_key] = Math.min(widget.prev_pos.axis_correction[opposite_size_key],
					widget.current_pos[opposite_size_key]);

				if (opposite_axis_key in widget.prev_pos.mirrored && opposite_axis_key in widget.prev_pos.axis_correction) {
					widget.current_pos[opposite_axis_key] = Math.max(widget.prev_pos.axis_correction[opposite_axis_key],
						widget.current_pos[opposite_axis_key]);
				}
			}

			this.fitWidgetsIntoBox(data.widgets, widget, axis);

			if ('overflow' in widget.current_pos) {
				// Store 'corrected' size.
				widget.prev_pos.axis_correction[axis.size_key] = widget.current_pos[axis.size_key];

				if (axis.mirrored) {
					widget.prev_pos.axis_correction[axis_key] = widget.current_pos[axis_key];
				}

				delete widget.current_pos.overflow;
			}
		});*/
	}

	// TODO unused function +
	checkWidgetOverlap(data) {
		this.resetCurrentPositions(data.widgets);

		// TODO check each +
		// $.each(data['widgets'], () => {
		// 	if (!this.posEquals(this['pos'], this['current_pos'])) {
		// 		this['pos'] = this['current_pos'];
		// 		this.setDivPosition(this['div'], this['pos']);
		// 	}
		//
		// 	delete this['current_pos'];
		// });

		for (const box of data.widgets) {
			if (!this.posEquals(box.pos, box.current_pos)) {
				box.pos = box.current_pos;
				this.setDivPosition(box.div, box.pos);
			}

			delete box.current_pos;
		}
	}

	/**
	 * User action handler for resize of widget.
	 *
	 * @param {object} data    Dashboard data and options object.
	 * @param {object} widget  Dashboard widget object.
	 */
	// TODO unused function +
	doWidgetResize(data, widget) {
		let pos = this.calcDivPosition(widget.div),
			rows = 0;

		if (!this.posEquals(pos, widget.current_pos)) {
			widget.current_pos = pos;
			this.realignResize(widget);

			if (widget.iterator) {
				this.alignIteratorContents(data, widget, widget.current_pos);
			}

			// TODO check forEach
			data.widgets.forEach((box) => {
				if (widget.uniqueid !== box.uniqueid) {
					if (box.iterator) {
						var box_pos = this.calcDivPosition(box.div);
						if (box_pos.width !== box.current_pos.width
							|| box_pos.height !== box.current_pos.height) {
							this.alignIteratorContents(data, box, box.current_pos);
						}
					}

					this.setDivPosition(box.div, box.current_pos);
				}

				rows = Math.max(rows, box.current_pos.y + box.current_pos.height);
			});

			if (rows != data.options['rows']) {
				this.resizeDashboardGrid(rows);
			}
		}

		this.setDivPosition(data.placeholder, pos);
	}

	/**
	 * User action handler for drag of widget.
	 *
	 * @param {object} data    Dashboard data and options object.
	 * @param {object} widget  Dashboard widget object.
	 */
	// TODO unused function +
	doWidgetPositioning(data, widget) {
		let pos = this.calcDivPosition(widget.div),
			rows = 0,
			overflow = false;

		if (!this.posEquals(pos, widget.current_pos)) {
			widget.current_pos = pos;
			overflow = this.realignWidget(data.widgets, widget, data.options['max-rows']);

			if (overflow) {
				// restore last non-overflow position
				data.widgets.forEach((w) => {
					w.current_pos = Object.assign({}, data.undo_pos[w.uniqueid]);
				});
				pos = widget.current_pos;
			} else {
				// store all widget current_pos objects
				data.undo_pos = {};
				data.widgets.forEach((w) => {
					data.undo_pos[w.uniqueid] = Object.assign({}, w.current_pos);
				});

				data.widgets.forEach((w) => {
					if (widget.uniqueid !== w.uniqueid) {
						this.setDivPosition(w.div, w.current_pos);
					}

					rows = Math.max(rows, w.current_pos.y + w.current_pos.height);
				});

				if (rows > data.options['rows']) {
					data.options['rows_actual'] = rows;
					this.resizeDashboardGrid(rows);
				}
			}
		}

		this.setDivPosition(data.placeholder, pos);
	}

	// TODO unused function +
	stopWidgetPositioning(data, widget) {
		data.placeholder.hide();
		data.pos_action = '';

		// TODO need to check each
		// $.each(data['widgets'], function() {
		// 	// Check if position of widget changed
		// 	var new_pos = this['current_pos'],
		// 		old_pos = this['pos'],
		// 		changed = false;
		//
		// 	$.each(['x', 'y', 'width', 'height'], function(index, value) {
		// 		if (new_pos[value] !== old_pos[value]) {
		// 			changed = true;
		// 		}
		// 	});
		//
		// 	if (changed) {
		// 		data['options']['updated'] = true;
		// 		this['pos'] = this['current_pos'];
		// 	}
		//
		// 	// should be present only while dragging
		// 	delete this['current_pos'];
		// });

		for (const box of data.widgets) {
			// Check if position of widget changed
			if (!this.posEquals(box.current_pos, box.pos)) {
				data.options['updated'] = true;
				box.pos = box.current_pos;
			}

			// should be present only while dragging
			delete box.current_pos;
		};

		this.setDivPosition(widget.div, widget.pos);
		this.resizeDashboardGrid();

		this.doAction('onWidgetPosition', data, widget);
	}

	// TODO unused function +
	makeDraggable(data, widget) {
		widget.div.draggable({
			cursor: 'grabbing',
			handle: widget.content_header,
			scroll: true,
			scrollSensitivity: data.options['widget-height'],
			start: () => {
				this._$target.addClass('dashbrd-positioning');

				data.calculated = {
					'left-max': this._$target.width() - widget.div.width(),
					'top-max': data.options['max-rows'] * data.options['widget-height'] - widget.div.height()
				};

				this.setResizableState('disable', data.widgets, '');
				this.dragPrepare(data.widgets, widget, data.options['max-rows']);
				this.startWidgetPositioning(widget, 'drag');
				this.realignWidget(data.widgets, widget, data.options['max-rows']);

				widget.current_pos = Object.assign({}, widget.pos);
				data.undo_pos = {};
				data.widgets.forEach(function(w) {
					data.undo_pos[w.uniqueid] = Object.assign({}, w.current_pos);
				});
			},
			drag: (e, ui) => {
				// Limit element draggable area for X and Y axis.
				ui.position = {
					left: Math.max(0, Math.min(ui.position.left, data.calculated['left-max'])),
					top: Math.max(0, Math.min(ui.position.top, data.calculated['top-max']))
				};

				this.doWidgetPositioning(data, widget);
			},
			stop: () => {
				delete data.calculated;
				delete data.undo_pos;

				data.widgets = this.sortWidgets(data.widgets);
				data.widgets.forEach(function(widget) {
					delete widget.affected_by_draggable;
					delete widget.affected_by_id;
					delete widget.affected;
				});

				this.setResizableState('enable', data.widgets, '');
				this.stopWidgetPositioning(data, widget);

				if (widget.iterator && !widget.div.is(':hover')) {
					widget.div.removeClass('iterator-double-header');
				}

				data.options['rows'] = data.options['rows_actual'];
				this.resizeDashboardGrid(data.options['rows_actual']);

				this._$target.removeClass('dashbrd-positioning');
			}
		});
	}

	// TODO unused function +
	makeResizable(data, widget) {
		var	handles = {};

		$.each(['n', 'e', 's', 'w', 'ne', 'se', 'sw', 'nw'], function(index, key) {
			var	$handle = $('<div>').addClass('ui-resizable-handle').addClass('ui-resizable-' + key);

			if ($.inArray(key, ['n', 'e', 's', 'w']) >= 0) {
				$handle
					.append($('<div>', {'class': 'ui-resize-dot'}))
					.append($('<div>', {'class': 'ui-resizable-border-' + key}));
			}

			widget['div'].append($handle);
			handles[key] = $handle;
		});

		widget['div'].resizable({
			handles: handles,
			scroll: false,
			minWidth: this.getCurrentCellWidth(),
			minHeight: data['options']['widget-min-rows'] * data['options']['widget-height'],
			start: (event) => {
				this.doLeaveWidgetsExcept(widget);
				this.doEnterWidget(widget);

				this._$target.addClass('dashbrd-positioning');

				var handle_class = event.currentTarget.className;
				data['resizing_top'] = handle_class.match(/(^|\s)ui-resizable-(n|ne|nw)($|\s)/) !== null;
				data['resizing_left'] = handle_class.match(/(^|\s)ui-resizable-(w|sw|nw)($|\s)/) !== null;

				data.widgets.forEach(function(box) {
					delete box.affected_axis;
				});

				this.setResizableState('disable', data.widgets, widget.uniqueid);
				this.startWidgetPositioning(widget, 'resize');
				widget.prev_pos = $.extend({mirrored: {}}, widget.pos);
				widget.prev_pos.axis_correction = {};
			},
			resize: (event, ui) => {
				// Will break fast-resizing widget-top past minimum height, if moved to start section (jQuery UI bug?)
				widget['div']
					.toggleClass('resizing-top', data['resizing_top'])
					.toggleClass('resizing-left', data['resizing_left']);

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

				if (data['resizing_top']) {
					ui.position.top += Math.max(0,
						ui.size.height - data['options']['widget-max-rows'] * data['options']['widget-height']
					);
				}

				widget['div'].css({
					'left': ui.position.left,
					'top': ui.position.top,
					'max-width': Math.min(ui.size.width,
						data['cell-width'] * data['options']['max-columns'] - ui.position.left
					),
					'max-height': Math.min(ui.size.height,
						data['options']['widget-max-rows'] * data['options']['widget-height'],
						data['options']['max-rows'] * data['options']['widget-height'] - ui.position.top
					)
				});

				this.doWidgetResize(data, widget);

				widget['container'].css({
					'width': data['placeholder'].width(),
					'height': data['placeholder'].height()
				});
			},
			stop: () => {
				this.doLeaveWidget(widget);

				delete widget.prev_pos;

				this.setResizableState('enable', data.widgets, widget.uniqueid);
				this.stopWidgetPositioning(data, widget);

				widget['container'].removeAttr('style');

				if (widget['iterator']) {
					this.alignIteratorContents(data, widget, widget['pos']);
				}

				delete data['resizing_top'];
				delete data['resizing_left'];

				widget['div']
					.removeClass('resizing-top')
					.removeClass('resizing-left')
					.css({
						'max-width': '',
						'max-height': ''
					});

				// Invoke onResizeEnd on every affected widget.
				data.widgets.forEach((box) => {
					if ('affected_axis' in box || box.uniqueid === widget.uniqueid) {
						this.resizeWidget(box);
					}
				});

				this._$target.removeClass('dashbrd-positioning');
			}
		});
	}

	/**
	 * Set resizable state for dashboard widgets.
	 *
	 * @param {string} state     Enable or disable resizable for widgets. Available values: 'enable', 'disable'.
	 * @param {array}  widgets   Array of all widgets.
	 * @param {string} ignoreid  All widgets except widget with such uniqueid will be affected.
	 */
	// TODO unused function +
	setResizableState(state, widgets, ignoreid) {
		// widgets.forEach(function(widget) {
		// 	if (widget.uniqueid !== ignoreid) {
		// 		widget.div.resizable(state);
		// 	}
		// });
		for (const widget of widgets) {
			if (widget.uniqueid !== ignoreid) {
				widget.div.resizable(state);
			}
		}
	}






























	// TODO unused function +
	showPreloader(widget) {
		if (widget.iterator) {
			widget.div.find('.dashbrd-grid-iterator-content').addClass('is-loading');
		} else {
			widget.div.find('.dashbrd-grid-widget-content').addClass('is-loading');
		}
	}

	// TODO unused function +
	hidePreloader(widget) {
		if (widget.iterator) {
			widget.div.find('.dashbrd-grid-iterator-content').removeClass('is-loading');
		} else {
			widget.div.find('.dashbrd-grid-widget-content').removeClass('is-loading');
		}
	}

	// TODO unused function +
	startPreloader(widget, timeout) {
		timeout = timeout || widget.preloader_timeout;

		if (typeof widget.preloader_timeoutid !== 'undefined' || widget.div.find('.is-loading').length) {
			return;
		}

		widget.preloader_timeoutid = setTimeout(() => {
			delete widget.preloader_timeoutid;

			this.showPreloader(widget);
		}, timeout);
	}

	// TODO unused function +
	stopPreloader(widget) {
		if (typeof widget.preloader_timeoutid !== 'undefined') {
			clearTimeout(widget.preloader_timeoutid);
			delete widget.preloader_timeoutid;
		}

		this.hidePreloader(widget);
	}

	// TODO unused function +
	setUpdateWidgetContentTimer(data, widget, rf_rate) {
		this.clearUpdateWidgetContentTimer(widget);

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
				var active = widget.content_body.find('[data-expanded="true"]');

				if (!active.length && !this.doAction('timer_refresh', data, widget)) {
					// No active popup or hintbox AND no triggers executed => update now.
					this.updateWidgetContent(widget);
				} else {
					// Active popup or hintbox OR triggers executed => just setup the next cycle.
					this.setUpdateWidgetContentTimer(data, widget);
				}
			}, rf_rate * 1000);
		}
	}

	// TODO unused function +
	clearUpdateWidgetContentTimer(widget) {
		if (typeof widget.rf_timeoutid !== 'undefined') {
			clearTimeout(widget.rf_timeoutid);
			delete widget.rf_timeoutid;
		}
	}

	// TODO unused function +
	setIteratorTooSmallState(iterator, enabled) {
		iterator.div.toggleClass('iterator-too-small', enabled);
	}

	// TODO unused function +
	getIteratorTooSmallState(iterator) {
		return iterator.div.hasClass('iterator-too-small');
	}

	// TODO unused function +
	numIteratorColumns(iterator) {
		return iterator.fields['columns'] ? iterator.fields['columns'] : 2;
	}

	// TODO unused function +
	numIteratorRows(iterator) {
		return iterator.fields['rows'] ? iterator.fields['rows'] : 1;
	}

	// TODO unused function +
	isIteratorTooSmall(data, iterator, pos) {
		return pos.width < this.numIteratorColumns(iterator)
			|| pos.height < this.numIteratorRows(iterator) * data.options['widget-min-rows'];
	}

	// TODO unused function +
	addIteratorPlaceholders(data, iterator, count) {
		$('.dashbrd-grid-iterator-placeholder', iterator.content_body).remove();

		for (let index = 0; index < count; index++) {
			iterator.content_body.append($('<div>', {'class': 'dashbrd-grid-iterator-placeholder'})
				.append('<div>')
				.on('mouseenter', () => {
					// Set single-line header for the iterator.
					iterator.div.removeClass('iterator-double-header');

					if (data.options['kioskmode'] && iterator.div.position().top == 0) {
						this.slideKiosk();
					}
				})
			);
		}
	}

	// TODO unused function +
	alignIteratorContents(data, iterator, pos) {
		if (this.isIteratorTooSmall(data, iterator, pos)) {
			this.setIteratorTooSmallState(iterator, true);

			return;
		}

		if (this.getIteratorTooSmallState(iterator) && iterator.update_pending) {
			this.setIteratorTooSmallState(iterator, false);
			this.showPreloader(iterator);
			this.updateWidgetContent(iterator);

			return;
		}

		this.setIteratorTooSmallState(iterator, false);

		var $placeholders = iterator.content_body.find('.dashbrd-grid-iterator-placeholder'),
			num_columns = this.numIteratorColumns(iterator),
			num_rows = this.numIteratorRows(iterator);

		for (var index = 0, count = num_columns * num_rows; index < count; index++) {
			var cell_column = index % num_columns,
				cell_row = Math.floor(index / num_columns),
				cell_width_min = Math.floor(pos.width / num_columns),
				cell_height_min = Math.floor(pos.height / num_rows),
				num_enlarged_columns = pos.width - cell_width_min * num_columns,
				num_enlarged_rows = pos.height - cell_height_min * num_rows,
				x = cell_column * cell_width_min + Math.min(cell_column, num_enlarged_columns),
				y = cell_row * cell_height_min + Math.min(cell_row, num_enlarged_rows),
				width = cell_width_min + (cell_column < num_enlarged_columns ? 1 : 0),
				height = cell_height_min + (cell_row < num_enlarged_rows ? 1 : 0),
				css = {
					left: `${x / pos.width * 100}%`,
					top: `${y * data.options['widget-height']}px`,
					width: `${width / pos.width * 100}%`,
					height: `${height * data.options['widget-height']}px`
				};

			if (cell_column == num_columns - 1) {
				// Setting right side for last column of widgets (fixes IE11 and Opera issues).
				$.extend(css, {
					width: 'auto',
					right: '0px'
				});
			} else {
				$.extend(css, {
					width: Math.round(width / pos.width * 100 * 100) / 100 + '%',
					right: 'auto'
				});
			}

			if (index < iterator.children.length) {
				iterator.children[index].div.css(css);
			} else {
				$placeholders.eq(index - iterator.children.length).css(css);
			}
		}
	}

	// TODO unused function +
	addWidgetOfIterator(data, iterator, child) {
		// Replace empty arrays (or anything non-object) with empty objects.
		if (typeof child.fields !== 'object') {
			child.fields = {};
		}
		if (typeof child.configuration !== 'object') {
			child.configuration = {};
		}

		child = Object.assign({
			'widgetid': '',
			'type': '',
			'header': '',
			'view_mode': iterator.view_mode,
			'preloader_timeout': 10000,	// in milliseconds
			'update_paused': false,
			'initial_load': true,
			'ready': false,
			'storage': {}
		}, child, {
			'iterator': false,
			'parent': iterator,
			'new_widget': false
		});

		child.uniqueid = this.generateUniqueId(data);
		child.div = this.makeWidgetDiv(child);

		iterator.content_body.append(child.div);
		iterator.children.push(child);

		this.showPreloader(child);
	}

	// TODO unused function +
	hasEqualProperties(object_1, object_2) {
		if (Object.keys(object_1).length !== Object.keys(object_2).length) {
			return false;
		}

		for (var key in object_1) {
			if (object_1[key] !== object_2[key]) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Clear and reset the state of the iterator.
	 */
	// TODO unused function +
	clearIterator(data, iterator) {
		iterator.children.forEach((child) => {
			this.removeWidget(data, child);
		});

		iterator.content_body.empty();
		iterator.children = [];

		iterator.div.removeClass('iterator-alt-content');
	}

	// TODO unused function +
	updateIteratorCallback(data, iterator, response, options) {
		var has_alt_content = typeof response.messages !== 'undefined' || typeof response.body !== 'undefined';

		if (has_alt_content || this.getIteratorTooSmallState(iterator)) {
			this.clearIterator(data, iterator);

			if (has_alt_content) {
				var $alt_content = $('<div>');
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
				this.updateIteratorPager(iterator);
			} else {
				iterator.update_pending = true;
			}

			return;
		}

		if (iterator.div.hasClass('iterator-alt-content')) {
			// Returning from alt-content to normal mode.
			this.clearIterator(data, iterator);
		}

		iterator.page = response.page;
		iterator.page_count = response.page_count;
		this.updateIteratorPager(iterator);

		var current_children = iterator.children,
			current_children_by_widgetid = {};

		iterator.children = [];

		current_children.forEach((child) => {
			if (child.widgetid !== '') {
				current_children_by_widgetid[child.widgetid] = child;
			} else {
				// Child widgets without 'uniqueid' are never persisted.
				this.removeWidget(data, child);
			}
		});

		var reused_widgetids = [];
		response.children.slice(0, this.numIteratorColumns(iterator) * this.numIteratorRows(iterator))
			.forEach((child) => {
				if (typeof child.widgetid !== 'undefined' && current_children_by_widgetid[child.widgetid]
					&& this.hasEqualProperties(
						child.fields, current_children_by_widgetid[child.widgetid].fields)
					) {

					// Reuse widget, if it has 'widgetid' supplied, has exactly the same fields and fields data.
					// Please note, that the order of widgets inside of iterator['content_body'] is not important,
					// since the absolute positioning is done based on widget order in the iterator['children'].

					iterator.children.push(current_children_by_widgetid[child.widgetid]);
					reused_widgetids.push(child.widgetid);
				} else {
					this.addWidgetOfIterator(data, iterator, child);
				}
			});

		// TODO need to check each
		$.each(current_children_by_widgetid, (index, child) => {
			if ($.inArray(child.widgetid, reused_widgetids) === -1) {
				this.removeWidget(data, child);
			}
		});

		this.addIteratorPlaceholders(data, iterator,
			this.numIteratorColumns(iterator) * this.numIteratorRows(iterator) - iterator.children.length
		);

		this.alignIteratorContents(data, iterator,
			(typeof iterator.current_pos === 'object') ? iterator.current_pos : iterator.pos
		);

		iterator.children.forEach((child) => {
			/* Possible update policies for the child widgets:
				resize: execute 'onResizeEnd' action (widget won't update if there's no trigger or size hasn't changed).
					- Is used to propagate iterator's resize event.

				refresh: either execute 'timer_refresh' action (if trigger exists) or updateWidgetContent.
					- Is used when widget surely hasn't been resized, but needs to be refreshed.

				resize_or_refresh: either execute 'onResizeEnd' or 'timer_refresh' action, or updateWidgetContent.
					- Is used when widget might have been resized, and needs to be refreshed anyway.
			*/

			var update_policy = 'refresh';

			if ($.inArray(child.widgetid, reused_widgetids) !== -1 && 'update_policy' in options) {
				// Allow to override update_policy only for existing (not new) widgets.
				update_policy = options['update_policy'];
			}

			let success = false;
			switch (update_policy) {
				case 'resize':
				case 'resize_or_refresh':
					success = this.resizeWidget(child);
					if (update_policy === 'resize') {
						success = true;
					}
					if (success) {
						break;
					}
				// No break here.

				case 'refresh':
					success = this.doAction('timer_refresh', data, child);
					break;
			}

			if (!success) {
				// No triggers executed for the widget, therefore update the conventional way.
				this.updateWidgetContent(child);
			}
		});
	}

	// TODO unused function +
	updateWidgetCallback(data, widget, response, options) {
		widget.content_body.empty();
		if (typeof response.messages !== 'undefined') {
			widget.content_body.append(response.messages);
		}
		widget.content_body.append(response.body);

		if (typeof response.debug !== 'undefined') {
			$(response.debug).appendTo(widget.content_body);
		}

		this.removeWidgetInfoButtons(widget.content_header);
		if (typeof response.info !== 'undefined' && !data.options['edit_mode']) {
			this.addWidgetInfoButtons(widget.content_header, response.info);
		}

		// Creates new script elements and removes previous ones to force their re-execution.
		widget.content_script.empty();
		if (typeof response.script_inline !== 'undefined') {
			// NOTE: to execute script with current widget context, add unique ID for required div, and use it in script.
			widget.content_script.append($('<script>').text(response.script_inline));
		}
	}

	// TODO unused function +
	isDeletedWidget(data, widget) {
		let search_widgets = data.widgets;

		if (widget.parent) {
			if (this.isDeletedWidget(data, widget.parent)) {
				return true;
			}

			search_widgets = widget.parent.children;
		}

		const widgets_found = search_widgets.filter(function(w) {
			return (w.uniqueid === widget.uniqueid);
		});

		return !widgets_found.length;
	}

	// TODO unused function +
	setWidgetReady(data, widget) {
		if (widget.ready) {
			return;
		}

		let ready_updated = false,
			dashboard_was_ready = !data.widgets.filter(function(widget) {
				return !widget.ready;
			}).length;

		if (widget.iterator) {
			if (!widget.children.length) {
				// Set empty iterator to ready state.

				ready_updated = !widget.ready;
				widget.ready = true;
			}
		} else if (widget.parent) {
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
		} else {
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
				this._methods.registerDataExchangeCommit();
			} else {
				const dashboard_is_ready = !data.widgets.filter(function(widget) {
					return !widget.ready;
				}).length;

				if (dashboard_is_ready) {
					this._methods.registerDataExchangeCommit();
					this.doAction('onDashboardReady', data, null);
				}
			}
		}
	}

	// TODO unused function +
	getWidgetContentSize(widget) {
		return {
			'content_width': Math.floor(widget.content_body.width()),
			'content_height': Math.floor(widget.content_body.height())
		};
	}

	// TODO unused function +
	isEqualContentSize(size_1, size_2) {
		if (size_1 === undefined || size_2 === undefined) {
			return false;
		}

		return size_1.content_width === size_2.content_width && size_1.content_height === size_2.content_height;
	}

	// TODO unused function +
	updateWidgetContent(widget, options) {
		const data = this._$target.data('dashboardGrid');

		this.clearUpdateWidgetContentTimer(widget);

		if (widget.updating_content) {
			// Waiting for another AJAX request to either complete or fail.
			return;
		}

		if (widget.update_paused) {
			this.setUpdateWidgetContentTimer(data, widget);

			return;
		}

		if (widget.iterator) {
			var pos = (typeof widget.current_pos === 'object') ? widget.current_pos : widget.pos;

			if (this.isIteratorTooSmall(data, widget, pos)) {
				this.clearIterator(data, widget);

				this.stopPreloader(widget);
				this.setIteratorTooSmallState(widget, true);
				widget.update_pending = true;

				return;
			} else {
				this.setIteratorTooSmallState(widget, false);
				widget.update_pending = false;
			}
		}

		var url = new Curl('zabbix.php');
		url.setArgument('action', `widget.${widget.type}.view`);

		var ajax_data = {
			'templateid': (data.dashboard.templateid !== null) ? data.dashboard.templateid : undefined,
			'dashboardid': (data.dashboard.dashboardid !== null) ? data.dashboard.dashboardid : undefined,
			'dynamic_hostid': (data.dashboard.dynamic_hostid !== null) ? data.dashboard.dynamic_hostid : undefined,
			'widgetid': (widget.widgetid !== '') ? widget.widgetid : undefined,
			'uniqueid': widget.uniqueid,
			'name': (widget.header !== '') ? widget.header : undefined,
			'initial_load': widget.initial_load ? 1 : 0,
			'edit_mode': data.options.edit_mode ? 1 : 0,
			'storage': widget.storage,
			'view_mode': widget.view_mode
		};

		widget.content_size = this.getWidgetContentSize(widget);

		if (widget.iterator) {
			ajax_data.page = widget.page;
		} else {
			$.extend(ajax_data, widget.content_size);
		}

		if ('fields' in widget && Object.keys(widget.fields).length !== 0) {
			ajax_data.fields = JSON.stringify(widget.fields);
		}

		this.setDashboardBusy(data, 'updateWidgetContent', widget.uniqueid);

		this.startPreloader(widget);

		widget.updating_content = true;

		var request = $.ajax({
			url: url.getUrl(),
			method: 'POST',
			data: ajax_data,
			dataType: 'json'
		});

		request
			.then((response) => {
				delete widget.updating_content;

				this.stopPreloader(widget);

				if (this.isDeletedWidget(data, widget)) {
					return $.Deferred().reject();
				}

				var $content_header = $('h4', widget.content_header);

				$content_header.text(response.header);
				if (typeof response.aria_label !== 'undefined') {
					$content_header.attr('aria-label', (response.aria_label !== '') ? response.aria_label : null);
				}

				if (typeof options === 'undefined') {
					options = {};
				}

				if (widget.iterator) {
					this.updateIteratorCallback(data, widget, response, options);
				} else {
					this.updateWidgetCallback(data, widget, response, options);
				}

				this.doAction('onContentUpdated', data, null);
			})
			.then(() => {
				// Separate 'then' section allows to execute JavaScripts added by widgets in previous section first.

				this.setWidgetReady(data, widget);

				if (!widget.parent) {
					// Iterator child widgets are excluded here.
					this.setUpdateWidgetContentTimer(data, widget);
				}

				// The widget is loaded now, although possibly already resized.
				widget.initial_load = false;

				if (!widget.iterator) {
					// Update the widget, if it was resized before it was fully loaded.
					this.resizeWidget(widget);
				}

				// Call refreshCallback handler for expanded popup menu items.
				if (this._$target.find('[data-expanded="true"][data-menu-popup]').length) {
					this._$target.find('[data-expanded="true"][data-menu-popup]').menuPopup('refresh', widget);
				}
			})
			.always(() => {
				this.clearDashboardBusy(data, 'updateWidgetContent', widget.uniqueid);
			});

		request.fail(() => {
			delete widget.updating_content;
			this.setUpdateWidgetContentTimer(data, widget, 3);
		});
	}

	/**
	 * Smoothly scroll object of given position and dimension into view and return a promise.
	 *
	 * @param {object} data  Dashboard data and options object.
	 * @param {object} pos   Object with position and dimension.
	 *
	 * @returns {object}  jQuery Deferred object.
	 */
	// TODO unused function +
	promiseScrollIntoView(data, pos) {
		const $wrapper = $('.wrapper');

		let offset_top = $wrapper.scrollTop() + this._$target.offset().top,
			// Allow 5px free space around the object.
			margin = 5,
			widget_top = offset_top + pos.y * data.options['widget-height'] - margin,
			widget_height = pos.height * data.options['widget-height'] + margin * 2,
			wrapper_height = $wrapper.height(),
			wrapper_scrollTop = $wrapper.scrollTop(),
			wrapper_scrollTop_min = Math.max(0, widget_top + Math.min(0, widget_height - wrapper_height)),
			wrapper_scrollTop_max = widget_top;

		if (pos.y + pos.height > data.options['rows']) {
			this.resizeDashboardGrid(pos.y + pos.height);
		}

		if (wrapper_scrollTop < wrapper_scrollTop_min) {
			return $('.wrapper').animate({scrollTop: wrapper_scrollTop_min}).promise();
		} else if (wrapper_scrollTop > wrapper_scrollTop_max) {
			return $('.wrapper').animate({scrollTop: wrapper_scrollTop_max}).promise();
		} else {
			return $.Deferred().resolve();
		}
	}

	/**
	 * @param {object} data
	 * @param {object} widget
	 */
	// TODO unused function +
	updateWidgetConfig(data, widget) {
		if (data.options['updating_config']) {
			// Waiting for another AJAX request to either complete of fail.
			return;
		}

		var fields = $('form', data.dialogue.body).serializeJSON(),
			type = fields['type'],
			name = fields['name'],
			view_mode = (fields['show_header'] == 1) ? ZBX_WIDGET_VIEW_MODE_NORMAL : ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER,
			pos;

		delete fields['type'];
		delete fields['name'];
		delete fields['show_header'];

		if (widget === null || !('type' in widget) && !('pos' in widget)) {
			var area_size = {
				'width': data.widget_defaults[type].size.width,
				'height': data.widget_defaults[type].size.height
			};

			pos = this.findEmptyPosition(data, area_size);
			if (!pos) {
				this.showDialogMessageExhausted(data);

				return;
			}
		}

		data.options['updating_config'] = true;

		// Prepare to call dashboard.widget.check.

		var url = new Curl('zabbix.php');
		url.setArgument('action', 'dashboard.widget.check');

		var ajax_data = {
			templateid: data.dashboard.templateid || undefined,
			type: type,
			name: name,
			view_mode: view_mode
		};

		if (Object.keys(fields).length != 0) {
			ajax_data.fields = JSON.stringify(fields);
		}

		const $save_btn = data.dialogue.div.find('.dialogue-widget-save'),
			overlay = overlays_stack.getById('widgetConfg');

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

					$('.msg-bad', data.dialogue.body).remove();

					if (response.errors !== '') {
						data.dialogue.body.prepend(response.errors);
					}

					$save_btn.prop('disabled', false);

					return $.Deferred().reject();
				} else {
					// Set view mode of a reusable widget early to escape focus flickering.
					if (widget !== null && widget.type === type) {
						this.setWidgetViewMode(widget, view_mode);

						this.doLeaveWidgetsExcept(widget);
						this.doEnterWidget(widget);
					}
				}
			})
			.then(() => {
				// Prepare to call dashboard.widget.configure.
				const url = new Curl('zabbix.php');

				url.setArgument('action', 'dashboard.widget.configure');

				const ajax_data = {
					templateid: data.dashboard.templateid || undefined,
					type: type,
					view_mode: view_mode
				};

				if (Object.keys(fields).length != 0) {
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

				var configuration = {};
				if ('configuration' in response) {
					configuration = response.configuration;
				}

				if (widget === null || !('type' in widget)) {
					// In case of ADD widget, create and add widget to the dashboard.

					if (widget && 'pos' in widget) {
						pos = Object.assign({}, data.widget_defaults[type].size, widget.pos);

						$.map(data.widgets, (box) => {
							return this.rectOverlap(box.pos, pos) ? box : null;
						}).forEach((box) => {
							if (!this.rectOverlap(box.pos, pos)) {
								return;
							}

							if (pos.x + pos.width > box.pos.x && pos.x < box.pos.x) {
								pos.width = box.pos.x - pos.x;
							} else if (pos.y + pos.height > box.pos.y && pos.y < box.pos.y) {
								pos.height = box.pos.y - pos.y;
							}
						});

						pos.width = Math.min(data.options['max-columns'] - pos.x, pos.width);
						pos.height = Math.min(data.options['max-rows'] - pos.y, pos.height);
					}

					var widget_data = {
						'type': type,
						'header': name,
						'view_mode': view_mode,
						'pos': pos,
						'fields': fields,
						'configuration': configuration
					};

					this.promiseScrollIntoView(data, pos)
						.then(() => {
							this._methods.addWidget(widget_data);
							data.pos_action = '';

							// New widget is last element in data['widgets'] array.
							widget = data.widgets.slice(-1)[0];
							this.setWidgetModeEdit(data, widget);
							this.updateWidgetContent(widget);
						});
				} else if (widget.type === type) {
					// In case of EDIT widget, if type has not changed, update the widget.

					widget.header = name;
					widget.fields = fields;

					// Set preloader to widget content after overlayDialogueDestroy as fast as we can.
					this.startPreloader(widget, 100);

					// View mode was just set after the overlayDialogueDestroy was called in first 'then' section.

					this.applyWidgetConfiguration(widget, configuration);
					this.doAction('afterUpdateWidgetConfig', data, null);

					if (widget.iterator) {
						this.updateWidgetContent(widget, {
							'update_policy': 'resize_or_refresh'
						});
					} else {
						this.updateWidgetContent(widget);
					}
				} else {
					// In case of EDIT widget, if type has changed, replace the widget.

					this.removeWidget(data, widget);

					var widget_data = {
						'type': type,
						'header': name,
						'view_mode': view_mode,
						'pos': widget.pos,
						'fields': fields,
						'configuration': configuration,
						'new_widget': false
					};

					// Disable position/size checking during addWidget call.
					data.pos_action = 'updateWidgetConfig';
					this._methods.addWidget(widget_data);
					data.pos_action = '';

					// New widget is last element in data['widgets'] array.
					widget = data.widgets.slice(-1)[0];
					this.setWidgetModeEdit(data, widget);
					this.updateWidgetContent(widget);
				}

				data.options['updated'] = true;
			})
			.always(() => {
				$save_btn.prop('disabled', false);
				delete data.options['updating_config'];
				overlay.unsetLoading();
			});
	}

	/**
	 * Find first empty position in given size.
	 *
	 * @param {object} data               Dashboard 'dashboardGrid' object.
	 * @param {{width: (*|number|number), height: (*|number|number)}}   area_size          Seeked space.
	 * @param {int}    area_size[width]   Seeked space width.
	 * @param {int}    area_size[height]  Seeked space height.
	 *
	 * @returns {object|boolean}  area_size object extended with position or false in case if no empty space is found.
	 */
	// TODO unused function +
	findEmptyPosition(data, area_size) {
		const pos = $.extend(area_size, {'x': 0, 'y': 0});

		// Go y by row and try to position widget in each space.
		var max_col = data.options['max-columns'] - pos.width,
			max_row = data.options['max-rows'] - pos.height,
			found = false,
			x, y;

		for (y = 0; !found; y++) {
			if (y > max_row) {
				return false;
			}
			for (x = 0; x <= max_col && !found; x++) {
				pos.x = x;
				pos.y = y;
				found = this.isPosFree(data, pos);
			}
		}

		return pos;
	}

	// TODO unused function +
	isPosFree(data, pos) {
		var free = true;

		// TODO need to check each
		$.each(data.widgets, () => {
			if (this.rectOverlap(pos, this['pos'])) {
				free = false;
			}
		});

		return free;
	}

	// TODO unused function +
	openConfigDialogue(data, widget, trigger_elmnt) {
		this.doAction('beforeConfigLoad', data, widget);

		data.options['config_dialogue_active'] = true;

		const config_dialogue_close = () => {
			delete data.options['config_dialogue_active'];
			$.unsubscribe('overlay.close', config_dialogue_close);

			this.resetNewWidgetPlaceholderState(data);
		};

		$.subscribe('overlay.close', config_dialogue_close);

		var edit_mode = (widget !== null && 'type' in widget);

		data.dialogue = {};
		data.dialogue.widget = widget;

		var overlay = overlayDialogue({
			'title': (edit_mode ? t('Edit widget') : t('Add widget')),
			'class': 'modal-popup modal-popup-generic',
			'content': jQuery('<div>', {'height': '68px'}),
			'buttons': [
				{
					'title': (edit_mode ? t('Apply') : t('Add')),
					'class': 'dialogue-widget-save',
					'keepOpen': true,
					'isSubmit': true,
					'action': () => {
						this.updateWidgetConfig(data, widget);
					}
				},
				{
					'title': t('Cancel'),
					'class': 'btn-alt',
					'action': function() {
						// Clear action.
						data.pos_action = '';
					}
				}
			],
			'dialogueid': 'widgetConfg'
		}, trigger_elmnt);

		overlay.setLoading();

		data.dialogue.div = overlay.$dialogue;
		data.dialogue.body = overlay.$dialogue.$body;

		this._methods.updateWidgetConfigDialogue();
	}

	// TODO unused function +
	editDashboard(data) {
		this._$target.addClass('dashbrd-mode-edit');

		// Recalculate minimal height and expand dashboard to the whole screen.
		data.minimalHeight = this.calculateGridMinHeight();

		this.resizeDashboardGrid();

		data.widgets.forEach((widget) => {
			widget.rf_rate = 0;
			this.setWidgetModeEdit(data, widget);
		});

		data.pos_action = '';
		data.cell_width = this.getCurrentCellWidth();
		data.add_widget_dimension = {};

		// Add new widget user interaction handlers.
		$.subscribe('overlay.close', (e, dialogue) => {
			if (data.pos_action === 'addmodal' && dialogue.dialogueid === 'widgetConfg') {
				this.resetNewWidgetPlaceholderState(data);
			}
		});

		$(document).on('click mouseup dragend', (e) => {
			if (data.pos_action !== 'add') {
				return;
			}

			var dimension = Object.assign({}, data.add_widget_dimension);

			data.pos_action = 'addmodal';
			this.setResizableState('enable', data.widgets, '');

			if (this.getCopiedWidget(data) !== null) {
				var menu = getDashboardWidgetActionMenu(dimension),
					options = {
						position: {
							of: data.new_widget_placeholder.getObject(),
							my: ['left', 'top'],
							at: ['right', 'bottom'],
							collision: 'fit'
						},
						closeCallback: () => {
							data.pos_action = '';

							if (!data.options['config_dialogue_active']) {
								this.resetNewWidgetPlaceholderState(data);
							}
						}
					};

				// Adopt menu position to direction in which placeholder was drawn.
				if (dimension.x + dimension.width >= data.options['max-columns'] - 4) {
					options.position.my[0] = (dimension.left > dimension.x) ? 'left' : 'right';
				}
				if (dimension.left > dimension.x) {
					options.position.at[0] = 'left';
				}

				if (dimension.y == 0) {
					options.position.my[1] = 'top';
					options.position.at[1] = (dimension.top > dimension.y) ? 'top' : 'bottom';
				} else if (dimension.top > dimension.y) {
					options.position.my[1] = 'bottom';
					options.position.at[1] = 'top';
				}

				options.position.my = options.position.my.join(' ');
				options.position.at = options.position.at.join(' ');

				data.new_widget_placeholder.getObject().menuPopup(menu, e, options);
			} else {
				this._methods.addNewWidget(null, dimension);
			}
		});

		this._$target
			.on('mousedown', (e) => {
				const $target = $(e.target);

				if (e.which != 1 || data.pos_action !== ''
					|| (!$target.is(data.new_widget_placeholder.getObject())
						&& !data.new_widget_placeholder.getObject().has($target).length)) {
					return;
				}

				this.setResizableState('disable', data.widgets, '');

				data.pos_action = 'add';

				delete data.add_widget_dimension.left;
				delete data.add_widget_dimension.top;

				data.new_widget_placeholder
					.setState(data.new_widget_placeholder.STATE_RESIZING)
					.showAtPosition(data.add_widget_dimension);

				return false;
			})
			.on('mouseleave', () => {
				if (data.pos_action) {
					return;
				}

				data.add_widget_dimension = {};
				this.resetNewWidgetPlaceholderState(data);
			})
			.on('mouseenter mousemove', (e) => {
				var $target = $(e.target);

				if (data.pos_action !== '' && data.pos_action !== 'add') {
					return;
				}

				if (data.pos_action !== 'add' && data.pos_action !== 'addmodal' && !$target.is(this._$target)
					&& !$target.is(data.new_widget_placeholder.getObject())
					&& !data.new_widget_placeholder.getObject().has($target).length) {
					data.add_widget_dimension = {};
					data.new_widget_placeholder.hide();
					this.resizeDashboardGrid();

					return;
				}

				var offset = this._$target.offset(),
					y = Math.min(data.options['max-rows'] - 1,
						Math.max(0, Math.floor((e.pageY - offset.top) / data.options['widget-height']))
					),
					x = Math.min(data.options['max-columns'] - 1,
						Math.max(0, Math.floor((e.pageX - offset.left) / data.cell_width))
					),
					overlap = false;

				if (isNaN(x) || isNaN(y)) {
					return;
				}

				var pos = {
					x: x,
					y: y,
					width: (x < data.options['max-columns'] - 1) ? 1 : 2,
					height: data.options['widget-min-rows']
				};

				if (data.pos_action === 'add') {
					if (!('top' in data.add_widget_dimension)) {
						data.add_widget_dimension.left = x;
						data.add_widget_dimension.top = Math.min(y, data.add_widget_dimension.y);
					}

					pos = {
						x: Math.min(x, (data.add_widget_dimension.left < x)
							? data.add_widget_dimension.x
							: data.add_widget_dimension.left
						),
						y: Math.min(y, (data.add_widget_dimension.top < y)
							? data.add_widget_dimension.y
							: data.add_widget_dimension.top
						),
						width: Math.max(1, (data.add_widget_dimension.left < x)
							? x - data.add_widget_dimension.left + 1
							: data.add_widget_dimension.left - x + 1
						),
						height: Math.max(2, (data.add_widget_dimension.top < y)
							? y - data.add_widget_dimension.top + 1
							: data.add_widget_dimension.top - y + 2
						)
					};

					// TODO need to check each
					$.each(data.widgets, (_, box) => {
						overlap |= this.rectOverlap(box.pos, pos);

						return !overlap;
					});

					if (overlap) {
						pos = data.add_widget_dimension;
					}
				} else {
					if ((pos.x + pos.width) > data.options['max-columns']) {
						pos.x = data.options['max-columns'] - pos.width;
					} else if (data.add_widget_dimension.x < pos.x) {
						--pos.x;
					}

					if ((pos.y + pos.height) > data.options['max-rows']) {
						pos.y = data.options['max-rows'] - pos.height;
					} else if (data.add_widget_dimension.y < pos.y) {
						--pos.y;
					}

					/*
					 * If there is collision make additional check to ensure that mouse is not at the bottom of 1x2 free
					 * slot.
					 */
					var delta_check = [
						[0, 0, 2],
						[-1, 0, 2],
						[0, 0, 1],
						[0, -1, 2],
						[0, -1, 1]
					];

					// TODO need to check each
					$.each(delta_check, (i, val) => {
						var c_pos = Object.assign({}, {
							x: Math.max(0, (val[2] < 2 ? x : pos.x) + val[0]),
							y: Math.max(0, pos.y + val[1]),
							width: val[2],
							height: pos.height
						});

						if (x > c_pos.x + 1) {
							++c_pos.x;
						}

						overlap = false;

						if (this.rectOverlap({
							x: 0,
							y: 0,
							width: data.options['max-columns'],
							height: data.options['max-rows']
						}, c_pos)) {
							// TODO need to check each
							$.each(data.widgets, (_, box) => {
								overlap |= this.rectOverlap(box.pos, c_pos);

								return !overlap;
							});
						}

						if (!overlap) {
							pos = c_pos;

							return false;
						}
					});

					if (overlap) {
						data.add_widget_dimension = {};
						data.new_widget_placeholder.hide();

						return;
					}
				}

				if ((pos.y + pos.height) > data.options['rows']) {
					this.resizeDashboardGrid(pos.y + pos.height);
				}

				$.extend(data.add_widget_dimension, pos);

				// Hide widget headers, not to interfere with the new widget placeholder.
				this.doLeaveWidgetsExcept(null);

				data.new_widget_placeholder
					.setState((data.pos_action === 'add')
						? data.new_widget_placeholder.STATE_RESIZING
						: data.new_widget_placeholder.STATE_POSITIONING
					)
					.showAtPosition(data.add_widget_dimension);
			});
	}

	// TODO unused function +
	setWidgetModeEdit(data, widget) {
		this.clearUpdateWidgetContentTimer(widget);

		if (!widget.iterator) {
			this.removeWidgetInfoButtons(widget.content_header);
		}

		this.makeDraggable(data, widget);
		this.makeResizable(data, widget);
		this.resizeWidget(widget);
	}

	/**
	 * Remove widget actions added by addAction.
	 *
	 * @param {object} data    Dashboard data and options object.
	 * @param {object} widget  Dashboard widget object.
	 */
	// TODO unused function +
	removeWidgetActions(data, widget) {
		for (const hook_name in data.triggers) {
			for (let index = 0; index < data.triggers[hook_name].length; index++) {
				if (widget.uniqueid === data.triggers[hook_name][index].uniqueid) {
					data.triggers[hook_name].splice(index, 1);
				}
			}
		}
	}

	/**
	 * Enable user functional interaction with widget.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	// TODO unused function +
	enableWidgetControls(widget) {
		widget.content_header.find('button').prop('disabled', false);
	}

	/**
	 * Disable user functional interaction with widget.
	 *
	 * @param {object} widget  Dashboard widget object.
	 */
	// TODO unused function +
	disableWidgetControls(widget) {
		widget.content_header.find('button').prop('disabled', true);
	}

	/**
	 * Remove the widget without updating the dashboard.
	 */
	// TODO unused function +
	removeWidget(data, widget) {
		if (widget.iterator) {
			widget.children.forEach((child) => {
				this.doAction('onWidgetDelete', data, child);
				this.removeWidgetActions(data, child);
				child.div.remove();
			});
		}

		if (widget.parent) {
			this.doAction('onWidgetDelete', data, widget);
			this.removeWidgetActions(data, widget);
			widget.div.remove();
		} else {
			var index = widget.div.data('widget-index');

			this.doAction('onWidgetDelete', data, widget);
			this.removeWidgetActions(data, widget);
			widget.div.remove();

			data.widgets.splice(index, 1);

			for (var i = index; i < data.widgets.length; i++) {
				data.widgets[i].div.data('widget-index', i);
			}
		}
	}

	/**
	 * Delete the widget and update the dashboard.
	 */
	// TODO unused function +
	deleteWidget(data, widget) {
		this.removeWidget(data, widget);

		if (!widget.parent) {
			data.options['updated'] = true;

			this.resizeDashboardGrid();
			this.resetNewWidgetPlaceholderState(data);
		}
	}

	// TODO unused function +
	generateUniqueId(data) {
		let ref = false;

		while (!ref) {
			ref = this.generateRandomString(5);

			// TODO need to check each
			$.each(data.widgets, function(index, widget) {
				if (widget.uniqueid === ref) {
					ref = false;
					return false;
				}
			});
		}

		return ref;
	}

	// TODO unused function +
	onIteratorResizeEnd(data, iterator) {
		this.updateIteratorPager(iterator);

		if (this.getIteratorTooSmallState(iterator)) {
			return;
		}

		this.updateWidgetContent(iterator, {
			'update_policy': 'resize'
		});
	}

	// TODO unused function +
	resizeWidget(widget) {
		const data = this._$target.data('dashboardGrid');

		var success = false;

		if (widget.iterator) {
			// Iterators will sync first, then selectively propagate the resize event to the child widgets.
			success = this.doAction('onResizeEnd', data, widget);
		} else {
			var size_old = widget.content_size,
				size_new = this.getWidgetContentSize(widget);

			if (!this.isEqualContentSize(size_old, size_new)) {
				success = this.doAction('onResizeEnd', data, widget);

				if (success) {
					widget.content_size = size_new;
				}
			}
		}

		return success;
	}

	/**
	 * Show "dashboard is exhausted" warning message in dialog context.
	 *
	 * @param {object} data  Dashboard data and options object.
	 */
	// TODO unused function +
	showDialogMessageExhausted(data) {
		data.dialogue.body.children('.msg-warning').remove();
		data.dialogue.body.prepend(makeMessageBox(
			'warning', t('Cannot add widget: not enough free space on the dashboard.'), null, false
		));
	}

	/**
	 * Show "dashboard is exhausted" warning message in dashboard context.
	 *
	 * @param {object} data  Dashboard data and options object.
	 */
	// TODO unused function +
	showMessageExhausted(data) {
		if (data.options.message_exhausted) {
			return;
		}

		data.options.message_exhausted = makeMessageBox(
			'warning', [], t('Cannot add widget: not enough free space on the dashboard.'), true, false
		);
		addMessage(data.options.message_exhausted);
	}

	/**
	 * Hide "dashboard is exhausted" warning message in dashboard context.
	 *
	 * @param {object} data  Dashboard data and options object.
	 */
	// TODO unused function +
	hideMessageExhausted(data) {
		if (!data.options.message_exhausted) {
			return;
		}

		data.options.message_exhausted.remove();
		delete data.options.message_exhausted;
	}

	/**
	 * Performs action added by addAction function.
	 *
	 * @param {string} hook_name  Name of trigger that is currently being called.
	 * @param {object} data       Dashboard data and options object.
	 * @param {object} widget     Current widget object (can be null for generic actions).
	 *
	 * @returns {int}  Number of triggers, that were called.
	 */
	// TODO unused function +
	doAction(hook_name, data, widget) {
		if (typeof data.triggers[hook_name] === 'undefined') {
			return 0;
		}
		var triggers = [];

		if (widget === null) {
			triggers = data.triggers[hook_name];
		} else {
			// TODO need to check each
			$.each(data.triggers[hook_name], function(index, trigger) {
				if (trigger.uniqueid === null || widget.uniqueid === trigger.uniqueid) {
					triggers.push(trigger);
				}
			});
		}

		triggers.sort(function(a, b) {
			var priority_a = (typeof a.options['priority'] !== 'undefined') ? a.options['priority'] : 10,
				priority_b = (typeof b.options['priority'] !== 'undefined') ? b.options['priority'] : 10;

			if (priority_a < priority_b) {
				return -1;
			}
			if (priority_a > priority_b) {
				return 1;
			}
			return 0;
		});

		// TODO need to check each
		$.each(triggers, (index, trigger) => {
			let trigger_function = null;
			if (typeof trigger.function === typeof Function) {
				// A function given?
				trigger_function = trigger.function;
			} else if (typeof window[trigger.function] === typeof Function) {
				// A name of function given?
				trigger_function = window[trigger.function];
			}

			if (trigger_function === null) {
				return true;
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
					} else if (trigger.uniqueid !== null) {
						var widgets = this._methods.getWidgetsBy('uniqueid', trigger.uniqueid);
						if (widgets.length > 0) {
							grid.widget = widgets[0];
						}
					}
				}
				if (trigger.options['grid'].data) {
					grid.data = data;
				}
				if (trigger.options['grid'].obj) {
					grid.obj = this._$target;
				}
				params.push(grid);
			}

			try {
				trigger_function.apply(null, params);
			} catch (e) {
			}
		});

		return triggers.length;
	}

	/**
	 * Get copied widget (if compatible with the current dashboard) or null otherwise.
	 *
	 * @param {object} data  Dashboard data and options object.
	 *
	 * @returns {object|null}  Copied widget or null.
	 */
	// TODO unused function +
	getCopiedWidget(data) {
		var copied_widget = data.storage.readKey('dashboard.copied_widget', null);

		if (copied_widget !== null && copied_widget.dashboard.templateid === data.dashboard.templateid) {
			return copied_widget.widget;
		} else {
			return null;
		}
	}

	/**
	 * Set dashboard busy state by registering a blocker.
	 *
	 * @param {object} data  Dashboard data and options object.
	 * @param {string} type  Common type of the blocker.
	 * @param {*}      item  Unique item of the blocker.
	 */
	// TODO unused function +
	setDashboardBusy(data, type, item) {
		if (data.options.busy_blockers === undefined) {
			data.options.busy_blockers = [];

			$.publish('dashboard.grid.busy', {state: true});
		}

		data.options.busy_blockers.push({type: type, item: item});
	}

	/**
	 * Clear dashboard busy state by unregistering a blocker.
	 *
	 * @param {object} data  Dashboard data and options object.
	 * @param {string} type  Common type of the blocker.
	 * @param {*}      item  Unique item of the blocker.
	 */
	// TODO unused function +
	clearDashboardBusy(data, type, item) {
		if (data.options.busy_blockers === undefined) {
			return;
		}

		for (var i = 0; i < data.options.busy_blockers.length; i++) {
			var blocker = data.options.busy_blockers[i];

			if (type === blocker.type && Object.is(item, blocker.item)) {
				data.options.busy_blockers.splice(i, 1);

				break;
			}
		}

		if (!data.options.busy_blockers.length) {
			delete data.options.busy_blockers;

			$.publish('dashboard.grid.busy', {state: false});
		}
	}

	/**
	 * Reset new widget placeholder state.
	 *
	 * @param {object} data  Dashboard data and options object.
	 */
	// TODO unused function +
	resetNewWidgetPlaceholderState(data) {
		if (data.widgets.length) {
			data.new_widget_placeholder.hide();
		} else {
			data.new_widget_placeholder
				.setState(data.new_widget_placeholder.STATE_ADD_NEW)
				.showAtDefaultPosition();
		}
	}

	initMethods() {
		this._methods = {
			// TODO unused function
			init: (data) => {
				const dashboard = Object.assign({
						templateid: null,
						dashboardid: null,
						dynamic_hostid: null
					}, data.dashboard),
					options = Object.assign({}, data.options, {
						'rows': 0,
						'updated': false,
						'widget-width': 100 / data.options['max-columns']
					});

				const add_new_widget_callback = (e) => {
					if (!this._methods.isEditMode()) {
						this._methods.editDashboard();
					}
					this._methods.addNewWidget(e.target);

					return false;
				};

				const new_widget_placeholder = new newWidgetPlaceholder(options['widget-width'],
					options['widget-height'], add_new_widget_callback
				);

				// This placeholder is used while positioning/resizing widgets.
				const placeholder = $('<div>', {'class': 'dashbrd-grid-widget-placeholder'}).append($('<div>')).hide();

				this._$target.append(new_widget_placeholder.getObject(), placeholder);

				if (options['editable']) {
					if (options['kioskmode']) {
						new_widget_placeholder.setState(new_widget_placeholder.STATE_KIOSK_MODE);
					} else {
						new_widget_placeholder.setState(new_widget_placeholder.STATE_ADD_NEW);
					}
				} else {
					new_widget_placeholder.setState(new_widget_placeholder.STATE_READONLY);
				}

				new_widget_placeholder.showAtDefaultPosition();

				this._$target.data('dashboardGrid', {
					dashboard: Object.assign({}, dashboard),
					options: Object.assign({}, options),
					widget_defaults: {},
					widgets: [],
					triggers: {},
					// A single placeholder used for positioning and resizing a widget.
					placeholder: placeholder,
					// A single placeholder used for prompting to add a new widget.
					new_widget_placeholder: new_widget_placeholder,
					widget_relation_submissions: [],
					widget_relations: {
						relations: [],
						tasks: {}
					},
					data_buffer: [],
					minimalHeight: this.calculateGridMinHeight(),
					storage: ZABBIX.namespace('instances.localStorage')
				});

				data = this._$target.data('dashboardGrid');

				let resize_timeout;

				if (data.options.edit_mode) {
					this.doAction('onEditStart', data, null);
					this.editDashboard(data);
				}

				$(window).on('resize', () => {
					clearTimeout(resize_timeout);
					resize_timeout = setTimeout(() => {
						data.widgets.forEach((widget) => {
							this.resizeWidget(widget);
						});
					}, 200);

					// Recalculate dashboard container minimal required height.
					data.minimalHeight = this.calculateGridMinHeight();
					data.cell_width = this.getCurrentCellWidth();
					data.new_widget_placeholder.resize();
					this.resizeDashboardGrid();
				});

				['onWidgetAdd', 'onWidgetDelete', 'onWidgetPosition'].forEach(action => {
					this._methods.addAction(action, () => this.hideMessageExhausted(data), null, {});
				});
			},

			// TODO unused function +
			getDashboardData: () => {
				const data = this._$target.data('dashboardGrid');

				return Object.assign({}, data.dashboard);
			},

			/**
			 * Get copied widget (if compatible with the current dashboard) or null otherwise.
			 *
			 * @returns {object|null}  Copied widget or null.
			 */
			// TODO unused function +
			getCopiedWidget: () => {
				const data = this._$target.data('dashboardGrid');

				return this.getCopiedWidget(data);
			},

			// TODO unused function +
			updateDynamicHost: (hostid) => {
				const data = this._$target.data('dashboardGrid');

				data.dashboard.dynamic_hostid = hostid;

				// TODO need to check each
				$.each(data.widgets, (index, widget) => {
					if (widget.fields.dynamic == 1) {
						this.updateWidgetContent(widget);

						const widget_actions = $('.btn-widget-action', widget.content_header).data('menu-popup').data;

						if (data.dashboard.dynamic_hostid !== null) {
							widget_actions.dynamic_hostid = data.dashboard.dynamic_hostid;
						} else {
							delete widget_actions.dynamic_hostid;
						}
					}
				});
			},

			// TODO unused function +
			setWidgetDefaults: (defaults) => {
				const data = this._$target.data('dashboardGrid');

				defaults = Object.assign({}, data.widget_defaults, defaults);
				data.widget_defaults = defaults;
			},

			// TODO unused function +
			addWidget: (widget) => {
				// Replace empty arrays (or anything non-object) with empty objects.
				if (typeof widget.fields !== 'object') {
					widget.fields = {};
				}
				if (typeof widget.configuration !== 'object') {
					widget.configuration = {};
				}

				widget = Object.assign({
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
					'storage': {}
				}, widget, {
					'parent': false
				});

				if (typeof widget.new_widget === 'undefined') {
					widget.new_widget = !widget.widgetid.length;
				}

				const data = this._$target.data('dashboardGrid');
				const widget_local = JSON.parse(JSON.stringify(widget));

				const widget_type_defaults = data.widget_defaults[widget_local.type];

				widget_local.iterator = widget_type_defaults.iterator;

				if (widget_local.iterator) {
					$.extend(widget_local, {
						'page': 1,
						'page_count': 1,
						'children': [],
						'update_pending': false
					});
				}

				widget_local.uniqueid = this.generateUniqueId(data);
				widget_local.div = this.makeWidgetDiv(widget_local);
				widget_local.div.data('widget-index', data.widgets.length);

				data.widgets.push(widget_local);
				this._$target.append(widget_local.div);

				this.setDivPosition(widget_local.div, widget_local.pos);

				if (data.pos_action !== 'updateWidgetConfig') {
					this.checkWidgetOverlap(data);
					this.resizeDashboardGrid();
				}

				this.showPreloader(widget_local);
				data.new_widget_placeholder.hide();

				if (widget_local.iterator) {
					// Placeholders will be shown while the iterator will be loading.
					this.addIteratorPlaceholders(data, widget_local,
						this.numIteratorColumns(widget_local) * this.numIteratorRows(widget_local)
					);
					this.alignIteratorContents(data, widget_local, widget_local.pos);

					this._methods.addAction('onResizeEnd', this.onIteratorResizeEnd, widget_local.uniqueid, {
						parameters: [data, widget_local],
						trigger_name: 'onIteratorResizeEnd_' + widget_local.uniqueid
					});
				}

				if (data.options.edit_mode) {
					widget_local.rf_rate = 0;
					this.setWidgetModeEdit(data, widget_local);
				}

				this.doAction('onWidgetAdd', data, widget_local);
			},

			// TODO unused function +
			setWidgetRefreshRate: (widgetid, rf_rate) => {
				const data = this._$target.data('dashboardGrid');

				// TODO need to check each
				$.each(data.widgets, (index, widget) => {
					if (widget.widgetid == widgetid) {
						widget.rf_rate = rf_rate;
						this.setUpdateWidgetContentTimer(data, widget);
					}
				});
			},

			// TODO unused function +
			refreshWidget: (widgetid) => {
				const data = this._$target.data('dashboardGrid');

				// TODO need to check each
				$.each(data.widgets, (index, widget) => {
					if (widget.widgetid == widgetid || widget.uniqueid === widgetid) {
						this.updateWidgetContent(widget);
					}
				});
			},

			// Pause specific widget refresh.
			// TODO unused function +
			pauseWidgetRefresh: (widgetid) => {
				const data = this._$target.data('dashboardGrid');

				// TODO need to check each
				$.each(data.widgets, (index, widget) => {
					if (widget.widgetid == widgetid || widget.uniqueid === widgetid) {
						widget.update_paused = true;
						return false;
					}
				});
			},

			// Unpause specific widget refresh.
			// TODO unused function +
			unpauseWidgetRefresh: (widgetid) => {
				const data = this._$target.data('dashboardGrid');

				// TODO need to check each +
				// $.each(data['widgets'], (index, widget) => {
				// 	if (widget['widgetid'] == widgetid || widget['uniqueid'] === widgetid) {
				// 		widget['update_paused'] = false;
				// 		return false;
				// 	}
				// });
				for (const widget of data.widgets) {
					if (widget.widgetid === widgetid || widget.uniqueid === widgetid) {
						widget.update_paused = false;
						break;
					}
				}
			},

			// TODO unused function +
			setWidgetStorageValue: (uniqueid, field, value) => {
				const data = this._$target.data('dashboardGrid');

				// TODO need to check each +
				// $.each(data['widgets'], (index, widget) => {
				// 	if (widget['uniqueid'] === uniqueid) {
				// 		widget['storage'][field] = value;
				// 	}
				// });
				for (const widget of data.widgets) {
					if (widget.uniqueid === uniqueid) {
						widget.storage[field] = value;
					}
				}
			},

			// TODO unused function +
			addWidgets: (widgets) => {
				const data = this._$target.data('dashboardGrid');

				// TODO need to check each +
				// $.each(widgets, () => {
				// 	this._methods.addWidget(Array.prototype.slice.call(arguments, 1));
				// });

				for (const widget of widgets) {
					this._methods.addWidget(widget);
				}

				// TODO need to check each +
				// $.each(data['widgets'], (index, value) => {
				// 	this.updateWidgetContent(data, value);
				// });
				for (const widget of data.widgets) {
					this.updateWidgetContent(widget);
				}
			},

			// TODO unused function +
			editDashboard: () => {
				const data = this._$target.data('dashboardGrid');

				// Set before firing "onEditStart" for isEditMode to work correctly.
				data.options['edit_mode'] = true;

				this.doAction('onEditStart', data, null);
				this.editDashboard(data);

				// Event must not fire if the dashboard was initially loaded in edit mode.
				$.publish('dashboard.grid.editDashboard');
			},

			// TODO unused function +
			isDashboardUpdated: () => {
				const data = this._$target.data('dashboardGrid');

				return data.options.updated;
			},

			// TODO unused function +
			saveDashboard: (callback) => {
				const data = this._$target.data('dashboardGrid');

				this.doAction('beforeDashboardSave', data, null);
				callback(data.widgets);
			},

			// After pressing "Edit" button on widget.
			// TODO unused function +
			editWidget: (widget, trigger_element) => {
				const data = this._$target.data('dashboardGrid');

				if (!this._methods.isEditMode()) {
					this._methods.editDashboard();
				}

				this.openConfigDialogue(data, widget, trigger_element);
			},

			/**
			 * Function to store copied widget into storage buffer.
			 *
			 * @param {object} widget  Widget object copied.
			 *
			 * @returns {jQuery}
			 */
			// TODO unused function +
			copyWidget: (widget) => {
				const data = this._$target.data('dashboardGrid');

				this.doAction('onWidgetCopy', data, widget);

				data.storage.writeKey('dashboard.copied_widget', {
					dashboard: {
						templateid: data.dashboard.templateid
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
			},

			/**
			 * Create new widget or replace existing widget in given position.
			 *
			 * @param {object} widget  (nullable) Widget to replace.
			 * @param {object} pos     (nullable) Position to paste new widget in.
			 *
			 * @returns {jQuery}
			 */
			// TODO unused function +
			pasteWidget: (widget, pos) => {
				const data = this._$target.data('dashboardGrid');

				data.pos_action = 'paste';

				this.hideMessageExhausted(data);

				let new_widget = this.getCopiedWidget(data);

				// Regenerate reference field values.
				if ('reference' in new_widget.fields) {
					new_widget.fields['reference'] = this._methods.makeReference();
				}

				// In case if selected space is 2x2 cells (represents simple click), use pasted widget size.
				if (widget === null && pos !== null && pos.width == 2 && pos.height == 2) {
					pos.width = new_widget.pos.width;
					pos.height = new_widget.pos.height;

					if (pos.x > data.options['max-columns'] - pos.width
						|| pos.y > data.options['max-rows'] - pos.height
						|| !this.isPosFree(data, pos)) {
						$.map(data.widgets, (box) => {
							return this.rectOverlap(box.pos, pos) ? box : null;
						}).forEach((box) => {
							if (pos.x + pos.width > box.pos.x && pos.x < box.pos.x) {
								pos.width = box.pos.x - pos.x;
							} else if (pos.y + pos.height > box.pos.y && pos.y < box.pos.y) {
								pos.height = box.pos.y - pos.y;
							}
						});
					}

					pos.width = Math.min(data.options['max-columns'] - pos.x, pos.width);
					pos.height = Math.min(data.options['max-rows'] - pos.y, pos.height);
				}

				// When no position is given, find first empty space. Use copied widget width and height.
				if (pos === null) {
					pos = this.findEmptyPosition(data, {
						'width': new_widget.pos.width,
						'height': new_widget.pos.height
					});
					if (!pos) {
						this.showMessageExhausted(data);

						return;
					}

					new_widget.pos.x = pos.x;
					new_widget.pos.y = pos.y;
				} else {
					new_widget = Object.assign(new_widget, {pos: pos});
				}

				const dashboard_busy_item = {};

				this.setDashboardBusy(data, 'pasteWidget', dashboard_busy_item);

				// Remove old widget.
				if (widget !== null) {
					this.removeWidget(data, widget);
				}

				this.promiseScrollIntoView(data, pos)
					.then(() => {
						this._methods.addWidget(new_widget);
						new_widget = data.widgets.slice(-1)[0];

						// Restrict loading content prior to sanitizing widget fields.
						new_widget.update_paused = true;

						this.setWidgetModeEdit(data, new_widget);
						this.disableWidgetControls(new_widget);

						var url = new Curl('zabbix.php');
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
						this.enableWidgetControls(new_widget);
						this.updateWidgetContent(new_widget);

						data.options['updated'] = true;
					})
					.fail(() => {
						this.deleteWidget(data, new_widget);
					})
					.always(() => {
						// Mark dashboard as updated.
						data.options['updated'] = true;
						data.pos_action = '';

						this.clearDashboardBusy(data, 'pasteWidget', dashboard_busy_item);
					});
			},

			// After pressing "delete" button on widget.
			// TODO unused function +
			deleteWidget: (widget) => {
				const data = this._$target.data('dashboardGrid');

				this.deleteWidget(data, widget);
			},

			/*
			 * Add or update form on widget configuration dialogue (when opened, as well as when requested by 'onchange'
			 * attributes in form itself).
			 */
			// TODO unused function +
			updateWidgetConfigDialogue: () => {
				const data = this._$target.data('dashboardGrid');
				const $body = data.dialogue.body;
				const $footer = $('.overlay-dialogue-footer', data.dialogue.div);
				const $header = $('.dashbrd-widget-head', data.dialogue.div);
				const $form = $('form', $body);
				const widget = data.dialogue.widget; // Widget currently being edited.
				const url = new Curl('zabbix.php');
				const ajax_data = {};

				let fields;

				url.setArgument('action', 'dashboard.widget.edit');

				if (data.dashboard.templateid !== null) {
					ajax_data.templateid = data.dashboard.templateid;
				}

				if ($form.length) {
					// Take values from form.
					fields = $form.serializeJSON();
					ajax_data.type = fields['type'];
					ajax_data.prev_type = data.dialogue.widget_type;
					delete fields['type'];

					if (ajax_data.prev_type === ajax_data.type) {
						ajax_data.name = fields['name'];
						ajax_data.view_mode = (fields['show_header'] == 1)
							? ZBX_WIDGET_VIEW_MODE_NORMAL
							: ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER;

						delete fields['name'];
						delete fields['show_header'];
					} else {
						// Get default config if widget type changed.
						fields = {};
					}
				} else if (widget !== null) {
					// Open form with current config.
					ajax_data.type = widget.type;
					ajax_data.name = widget.header;
					ajax_data.view_mode = widget.view_mode;
					fields = widget.fields;
				} else {
					// Get default config for new widget.
					fields = {};
				}

				if (fields && Object.keys(fields).length != 0) {
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
					data.dialogue.widget_type = response.type;

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
						this.updateWidgetConfig(data, widget);
					});

					var $overlay = jQuery('[data-dialogueid="widgetConfg"]');
					$overlay.toggleClass('sticked-to-top', data.dialogue.widget_type === 'svggraph');

					Overlay.prototype.recoverFocus.call({'$dialogue': $overlay});
					Overlay.prototype.containFocus.call({'$dialogue': $overlay});

					overlay.unsetLoading();

					const area_size = {
						'width': data.widget_defaults[data.dialogue.widget_type].size.width,
						'height': data.widget_defaults[data.dialogue.widget_type].size.height
					};

					if (widget === null && !this.findEmptyPosition(data, area_size)) {
						this.showDialogMessageExhausted(data);
						$('.dialogue-widget-save', $footer).prop('disabled', true);
					}

					// Activate tab indicator for graph widget form.
					if (data.dialogue.widget_type === 'svggraph') {
						new TabIndicators();
					}
				});
			},

			// Returns list of widgets filterd by key=>value pair
			// TODO unused function +
			getWidgetsBy: (key, value) => {
				const data = this._$target.data('dashboardGrid');
				const widgets_found = [];

				// TODO need to check each
				$.each(data.widgets, (index, widget) => {
					if (widget[key] === value) {
						widgets_found.push(widget);
					}
				});

				return widgets_found;
			},

			// Register widget as data receiver shared by other widget.
			// TODO unused function +
			registerDataExchange: (obj) => {
				const data = this._$target.data('dashboardGrid');

				data.widget_relation_submissions.push(obj);
			},

			// TODO unused function +
			registerDataExchangeCommit: () => {
				const data = this._$target.data('dashboardGrid');
				const used_indexes = []

				let erase = false;

				// TODO need to check each
				$.each(data.widget_relation_submissions, (rel_index, rel) => {
					erase = false;

					// No linked widget reference given. Just register as data receiver.
					if (typeof rel.linkedto === 'undefined') {
						if (typeof data.widget_relations.tasks[rel.uniqueid] === 'undefined') {
							data.widget_relations.tasks[rel.uniqueid] = [];
						}

						data.widget_relations.tasks[rel.uniqueid].push({
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
						// TODO need to check each
						$.each(data.widgets, (index, widget) => {
							if (typeof widget.fields.reference !== 'undefined'
								&& widget.fields.reference === rel.linkedto) {
								if (typeof data.widget_relations.relations[widget.uniqueid] === 'undefined') {
									data.widget_relations.relations[widget.uniqueid] = [];
								}
								if (typeof data.widget_relations.relations[rel.uniqueid] === 'undefined') {
									data.widget_relations.relations[rel.uniqueid] = [];
								}
								if (typeof data.widget_relations.tasks[rel.uniqueid] === 'undefined') {
									data.widget_relations.tasks[rel.uniqueid] = [];
								}

								data.widget_relations.relations[widget.uniqueid].push(rel.uniqueid);
								data.widget_relations.relations[rel.uniqueid].push(widget.uniqueid);
								data.widget_relations.tasks[rel.uniqueid].push({
									data_name: rel.data_name,
									callback: rel.callback
								});
								erase = true;
							}
						});
					}

					if (erase) {
						used_indexes.push(rel_index);
					}
				});

				for (let i = used_indexes.length - 1; i >= 0; i--) {
					data.widget_relation_submissions.splice(used_indexes[i], 1);
				}

				this._methods.callWidgetDataShare();
			},

			/**
			 * Pushes received data in data buffer and calls sharing method.
			 *
			 * @param {object} widget     Data origin widget
			 * @param {string} data_name  String to identify data shared
			 *
			 * @returns {boolean}  Indicates either there was linked widget that was related to data origin widget
			 */
			// TODO unused function +
			widgetDataShare: (widget, data_name) => {
				const args = Array.prototype.slice.call(arguments, 2);
				const uniqueid = widget.uniqueid;

				let ret = true;

				if (!args.length) {
					return false;
				}

				const data = this._$target.data('dashboardGrid');
				let index = -1;

				if (typeof data.widget_relations.relations[widget.uniqueid] === 'undefined'
					|| data.widget_relations.relations[widget.uniqueid].length == 0) {
					ret = false;
				}

				if (typeof data.data_buffer[uniqueid] === 'undefined') {
					data.data_buffer[uniqueid] = [];
				} else if (typeof data.data_buffer[uniqueid] !== 'undefined') {
					// TODO need to check each
					$.each(data.data_buffer[uniqueid], (i, arr) => {
						if (arr.data_name === data_name) {
							index = i;
						}
					});
				}

				if (index === -1) {
					data.data_buffer[uniqueid].push({
						data_name: data_name,
						args: args,
						old: []
					});
				} else {
					if (data.data_buffer[uniqueid][index].args !== args) {
						data.data_buffer[uniqueid][index].args = args;
						data.data_buffer[uniqueid][index].old = [];
					}
				}

				this._methods.callWidgetDataShare();

				return ret;
			},

			// TODO unused function +
			callWidgetDataShare: () => {
				const data = this._$target.data('dashboardGrid');

				for (const src_uniqueid in data.data_buffer) {
					if (typeof data.data_buffer[src_uniqueid] === 'object') {
						// TODO need to check each
						$.each(data.data_buffer[src_uniqueid], (index, buffer_data) => {
							if (typeof data.widget_relations.relations[src_uniqueid] !== 'undefined') {
								// TODO need to check each
								$.each(data.widget_relations.relations[src_uniqueid], (index, dest_uid) => {
									if (buffer_data.old.indexOf(dest_uid) == -1) {
										if (typeof data.widget_relations.tasks[dest_uid] !== 'undefined') {
											const widget = this._methods.getWidgetsBy('uniqueid', dest_uid);
											if (widget.length) {
												// TODO need to check each
												$.each(data.widget_relations.tasks[dest_uid], (i, task) => {
													if (task.data_name === buffer_data.data_name) {
														task.callback.apply([widget[0], buffer_data.args]);
													}
												});

												buffer_data.old.push(dest_uid);
											}
										}
									}
								});
							}
						});
					}
				}
			},

			// TODO unused function +
			makeReference: () => {
				const data = this._$target.data('dashboardGrid');

				let ref = false;

				while (!ref) {
					ref = this.generateRandomString(5);

					for (let i = 0, l = data.widgets.length; l > i; i++) {
						if (typeof data.widgets[i].fields['reference'] !== 'undefined') {
							if (data.widgets[i].fields['reference'] === ref) {
								ref = false;
								break;
							}
						}
					}
				}

				return ref;
			},

			// TODO unused function +
			addNewWidget: (trigger_element, pos) => {
				/*
				 * Unset if dimension width/height is equal to size of placeholder.
				 * Widget default size will be used.
				 */
				if (pos && pos.width == 2 && pos.height == 2) {
					delete pos.width;
					delete pos.height;
				}

				const widget = (pos && 'x' in pos && 'y' in pos) ? {pos: pos} : null;

				const data = this._$target.data('dashboardGrid');

				data.pos_action = 'addmodal';
				this.openConfigDialogue(data, widget, trigger_element);
			},

			// TODO unused function +
			isEditMode: () => {
				const data = this._$target.data('dashboardGrid');

				return data.options.edit_mode;
			},

			/**
			 * Add action, that will be performed on $hook_name trigger.
			 *
			 * @param {string} hook_name                  Name of trigger, when $function_to_call should be called.
			 * @param {string} function_to_call           Name of function in global scope that will be called.
			 * @param {string} uniqueid                   A widget to receive the event for (null for all widgets).
			 * @param {array}  options                    Any key in options is optional.
			 * @param {array}  options['parameters']      Array of parameters with which the function will be called.
			 * @param {array}  options['grid']            Mark, what data from grid should be passed to $function_to_call.
			 *                                            If is empty, parameter 'grid' will not be added to function_to_call params.
			 * @param {string} options['grid']['widget']  True to pass the widget as argument.
			 * @param {string} options['grid']['data']    True to pass dashboard grid data as argument.
			 * @param {string} options['grid']['obj']     True to pass dashboard grid object as argument.
			 * @param {int}    options['priority']        Order, when it should be called, compared to others. Default = 10.
			 * @param {int}    options['trigger_name']    Unique name. There can be only one trigger with this name for each hook.
			 */
			// TODO unused function +
			addAction: (hook_name, function_to_call, uniqueid, options) => {
				const data = this._$target.data('dashboardGrid');

				let found = false,
					trigger_name = null;

				if (typeof data.triggers[hook_name] === 'undefined') {
					data.triggers[hook_name] = [];
				}

				// Add trigger with each name only once.
				if (typeof options['trigger_name'] !== 'undefined') {
					trigger_name = options['trigger_name'];
					// TODO need to check each
					$.each(data.triggers[hook_name], (index, trigger) => {
						if (typeof trigger.options['trigger_name'] !== 'undefined'
							&& trigger.options['trigger_name'] === trigger_name) {
							found = true;
						}
					});
				}

				if (!found) {
					data.triggers[hook_name].push({
						'function': function_to_call,
						'uniqueid': uniqueid,
						'options': options
					});
				}
			}
		};
	}
}

$(function(){
	ZABBIX.Dashboard = new CDashboardPage($('.dashbrd-grid-container'));
});


/*(function($) {
	"use strict";

	$.fn.dashboardGrid = function(method) {
		if (ZABBIX.Dashboard._methods[method]) {
			return ZABBIX.Dashboard._methods[method](arguments[0]);
		}
		else if (typeof method === 'object' || !method) {
			return ZABBIX.Dashboard._methods.init(arguments[0]);
		}
		else {
			$.error('Invalid method "' +  method + '".');
		}
	}
}(jQuery));*/




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
